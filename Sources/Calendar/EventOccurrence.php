<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Calendar;

use SMF\Actions\Calendar;
use SMF\ArrayAccessHelper;
use SMF\Config;
use SMF\Time;
use SMF\TimeInterval;
use SMF\TimeZone;
use SMF\Utils;

/**
 * Represents a single occurrence of a calendar event.
 */
class EventOccurrence implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The recurrence ID of this individual occurrence of the event.
	 *
	 * See RFC 5545, section 3.8.4.4.
	 */
	public string $id;

	/**
	 * @var int
	 *
	 * The ID number of the parent event.
	 *
	 * All occurrences of an event have the same $id_event value.
	 */
	public int $id_event;

	/**
	 * @var SMF\Time
	 *
	 * A Time object representing the start of this occurrence of the event.
	 */
	public Time $start;

	/**
	 * @var SMF\Time
	 *
	 * An EventAdjustment object representing changes made to this occurrence of
	 * the event, if any.
	 */
	public EventAdjustment $adjustment;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'recurrenceid' => 'id',
		'eventid' => 'id_event',
		'id_board' => 'board',
		'id_topic' => 'topic',
		'id_first_msg' => 'msg',
		'id_member' => 'member',
		'poster' => 'member',
		'real_name' => 'name',
		'realname' => 'name',
		'member_groups' => 'groups',
		'allowed_groups' => 'groups',
		'start_object' => 'start',
		'end_object' => 'end',
	];

	/**
	 * @var SMF\Time
	 *
	 * A Time object representing the unadjusted start of this occurrence.
	 */
	protected Time $unadjusted_start;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id_event The ID number of the parent event.
	 * @param array $props Properties to set for this occurrence.
	 * @return EventOccurrence An instance of this class.
	 */
	public function __construct(int $id_event = 0, array $props = [])
	{
		$this->id_event = $id_event;

		if (!isset($props['start'])) {
			throw new \ValueError();
		}

		$this->unadjusted_start = clone $props['start'];
		$this->start = clone $props['start'];
		unset($props['start']);

		$this->id = $props['id'] ?? ($this->allday ? $this->start->format('Ymd') : (clone $this->start)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z'));
		unset($props['id']);

		if (isset($props['adjustment'])) {
			$this->adjustment = $props['adjustment'];
			unset($props['adjustment']);
		}

		// Set any other properties we were given.
		$this->set($props);

		// Apply any adjustments.
		if (isset($this->adjustment)) {
			if (isset($this->adjustment->offset)) {
				$this->start->add($this->adjustment->offset);
			}

			if (isset($this->adjustment->duration)) {
				$this->duration = clone $this->adjustment->duration;
			}

			if (isset($this->adjustment->location)) {
				$this->location = $this->adjustment->location;
			}

			if (isset($this->adjustment->title)) {
				$this->title = $this->adjustment->title;
			}
		}
	}

	/**
	 * Saving an individual occurrence means updating the parent event's
	 * adjustments property and then saving the parent event.
	 */
	public function save()
	{
		// Just in case...
		ksort($this->getParentEvent()->adjustments);

		foreach ($this->getParentEvent()->adjustments as $adjustment) {
			// Adjustment takes effect after this occurrence, so stop.
			if ($adjustment->id > $this->id) {
				break;
			}

			// Adjustment takes effect before this occurrence but doesn't
			// affect it, so skip.
			if (!$adjustment->affects_future && $adjustment->id < $this->id) {
				continue;
			}

			// If the found adjustment has all the same values that this
			// occurrence's current adjustment also has, then there's nothing
			// that needs to change.
			if (
				(string) ($this->adjustment->offset ?? '') === (string) ($adjustment->offset ?? '')
				&& (string) ($this->adjustment->duration ?? '') === (string) ($adjustment->duration ?? '')
				&& (string) ($this->adjustment->location ?? '') === (string) ($adjustment->location ?? '')
				&& (string) ($this->adjustment->title ?? '') === (string) ($adjustment->title ?? '')
			) {
				return;
			}
		}

		// Add a new entry to the parent event's list of adjustments.
		$this->adjustment->id = $this->id;
		$this->getParentEvent()->adjustments[$this->id] = clone $this->adjustment;
		$this->getParentEvent()->save();
	}

	/**
	 * Builds an iCalendar component for this occurrence of the event.
	 *
	 * @return string A VEVENT component for an iCalendar document.
	 */
	public function export(): string
	{
		$filecontents = [];
		$filecontents[] = 'BEGIN:VEVENT';

		$filecontents[] = 'SUMMARY:' . $this->title;

		if (!empty($this->location)) {
			$filecontents[] = 'LOCATION:' . str_replace(',', '\\,', $this->location);
		}

		$filecontents[] = 'UID:' . $this->uid;
		$filecontents[] = 'SEQUENCE:' . $this->sequence;
		$filecontents[] = 'RECURRENCE-ID' . (isset($this->adjustment) && $this->adjustment->affects_future ? ';RANGE=THISANDFUTURE' : '') . ($this->allday ? ';VALUE=DATE' : '') . ':' . $this->id;

		$filecontents[] = 'DTSTAMP:' . date('Ymd\\THis\\Z', $this->modified_time ?? time());
		$filecontents[] = 'DTSTART' . ($this->allday ? ';VALUE=DATE' : (!in_array($this->tz, RRule::UTC_SYNONYMS) ? ';TZID=' . $this->tz : '')) . ':' . $this->start->format('Ymd' . ($this->allday ? '' : '\\THis' . (in_array($this->tz, RRule::UTC_SYNONYMS) ? '\\Z' : '')));
		$filecontents[] = 'DURATION:' . (string) $this->duration;

		$filecontents[] = 'END:VEVENT';

		foreach ($filecontents as $line_num => $line) {
			$filecontents[$line_num] = Event::foldICalLine($line);
		}

		return implode("\r\n", $filecontents);
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		if (!isset($this->start)) {
			$this->start = new Time();
		}

		if (str_starts_with($prop, 'end')) {
			$end = (clone $this->start)->add($this->duration);
		}

		switch ($prop) {
			// Special handling for stuff that affects start.
			case 'start':
				if ($value instanceof \DateTimeInterface) {
					$this->start = Time::createFromInterface($value);
				}
				break;

			case 'datetime':
			case 'date':
			case 'date_local':
			case 'date_orig':
			case 'time':
			case 'time_local':
			case 'time_orig':
			case 'year':
			case 'month':
			case 'day':
			case 'hour':
			case 'minute':
			case 'second':
			case 'timestamp':
			case 'iso_gmdate':
			case 'tz':
			case 'tzid':
			case 'timezone':
				$this->start->{$prop} = $value;
				break;

			case 'start_datetime':
			case 'start_date':
			case 'start_date_local':
			case 'start_date_orig':
			case 'start_time':
			case 'start_time_local':
			case 'start_time_orig':
			case 'start_year':
			case 'start_month':
			case 'start_day':
			case 'start_hour':
			case 'start_minute':
			case 'start_second':
			case 'start_timestamp':
			case 'start_iso_gmdate':
				$this->start->{substr($prop, 6)} = $value;
				break;

			// Special handling for duration.
			case 'duration':
				if (!($value instanceof \DateInterval)) {
					try {
						$value = new TimeInterval((string) $value);
					} catch (\Throwable $e) {
						break;
					}
				} elseif (!($value instanceof TimeInterval)) {
					$value = TimeInterval::createFromDateInterval($value);
				}
				$this->custom['duration'] = $value;
				break;

			case 'end':
				if (!($value instanceof \DateTimeInterface)) {
					try {
						$value = new \DateTimeImmutable((is_numeric($value) ? '@' : '') . $value);
					} catch (\Throwable $e) {
						break;
					}
				}
				$this->custom['duration'] = $this->start->diff($value);
				break;

			case 'end_datetime':
			case 'end_date':
			case 'end_date_local':
			case 'end_date_orig':
			case 'end_time':
			case 'end_time_local':
			case 'end_time_orig':
			case 'end_year':
			case 'end_month':
			case 'end_day':
			case 'end_hour':
			case 'end_minute':
			case 'end_second':
			case 'end_timestamp':
			case 'end_iso_gmdate':
				$end->{substr($prop, 4)} = $value;
				$this->custom['duration'] = $this->start->diff($end);
				break;

			// These properties are read-only.
			case 'age':
			case 'uid':
			case 'tz_abbrev':
			case 'sequence':
			case 'new':
			case 'is_selected':
			case 'href':
			case 'link':
			case 'can_edit':
			case 'modify_href':
			case 'can_export':
			case 'export_href':
				break;

			default:
				$this->customPropertySet($prop, $value);
				break;
		}
	}

	/**
	 * Gets custom property values.
	 *
	 * @param string $prop The property name.
	 */
	public function __get(string $prop): mixed
	{
		if (str_starts_with($prop, 'end')) {
			$end = (clone $this->start)->add($this->custom['duration'] ?? $this->getParentEvent()->duration);
		}

		switch ($prop) {
			case 'datetime':
			case 'date':
			case 'date_local':
			case 'date_orig':
			case 'time':
			case 'time_local':
			case 'time_orig':
			case 'year':
			case 'month':
			case 'day':
			case 'hour':
			case 'minute':
			case 'second':
			case 'timestamp':
			case 'iso_gmdate':
			case 'tz':
			case 'tzid':
			case 'timezone':
			case 'tz_abbrev':
				return $this->start->{$prop};

			case 'start_datetime':
			case 'start_date':
			case 'start_date_local':
			case 'start_date_orig':
			case 'start_time':
			case 'start_time_local':
			case 'start_time_orig':
			case 'start_year':
			case 'start_month':
			case 'start_day':
			case 'start_hour':
			case 'start_minute':
			case 'start_second':
			case 'start_timestamp':
			case 'start_iso_gmdate':
				return $this->start->{substr($prop, 6)};

			case 'end':
				return $end;

			case 'end_datetime':
			case 'end_date':
			case 'end_date_local':
			case 'end_date_orig':
			case 'end_time':
			case 'end_time_local':
			case 'end_time_orig':
			case 'end_year':
			case 'end_month':
			case 'end_day':
			case 'end_hour':
			case 'end_minute':
			case 'end_second':
			case 'end_timestamp':
			case 'end_iso_gmdate':
				return $end->{substr($prop, 4)};

			case 'age':
				if ($this->getParentEvent()->type === Event::TYPE_BIRTHDAY && $this->getParentEvent()->start->format('Y') === 1004) {
					return null;
				}

				return date_diff($this->start, $this->getParentEvent()->start)->y;

			case 'is_first':
				return $this->unadjusted_start == $this->getParentEvent()->start;

			case 'can_affect_future':
				return !isset($this->adjustment) || $this->adjustment->affects_future === true;

			// These are set separately for each occurrence.
			case 'modify_href':
				return Config::$scripturl . '?action=' . ($this->getParentEvent()->board == 0 ? 'calendar;sa=post;' : 'post;msg=' . $this->getParentEvent()->msg . ';topic=' . $this->getParentEvent()->topic . '.0;calendar;') . 'eventid=' . $this->id_event . ';recurrenceid=' . $this->id . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];

			case 'export_href':
				return Config::$scripturl . '?action=calendar;sa=ical;eventid=' . $this->id_event . ';recurrenceid=' . $this->id . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];

			// These inherit from the parent event unless overridden for this occurrence.
			case 'allday':
			case 'duration':
			case 'title':
			case 'location':
				return $this->custom[$prop] ?? $this->getParentEvent()->{$prop};

			// These always inherit from the parent event.
			case 'uid':
			case 'type':
			case 'board':
			case 'topic':
			case 'msg':
			case 'modified_time':
			case 'member':
			case 'name':
			case 'groups':
			case 'sequence':
			case 'new':
			case 'is_selected':
			case 'href':
			case 'link':
			case 'can_edit':
			case 'can_export':
				return $this->getParentEvent()->{$prop};

			default:
				return $this->customPropertyGet($prop);
		}
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param string $prop The property name.
	 */
	public function __isset(string $prop): bool
	{
		if (in_array($prop, ['year', 'month', 'day', 'hour', 'minute', 'second'])) {
			$prop = 'start_' . $prop;
		}

		switch ($prop) {
			case 'start_datetime':
			case 'start_date':
			case 'start_time':
			case 'start_year':
			case 'start_month':
			case 'start_day':
			case 'start_hour':
			case 'start_minute':
			case 'start_second':
			case 'start_date_local':
			case 'start_date_orig':
			case 'start_time_local':
			case 'start_time_orig':
			case 'start_timestamp':
			case 'start_iso_gmdate':
			case 'tz':
			case 'tzid':
			case 'timezone':
			case 'tz_abbrev':
			case 'end_datetime':
			case 'end_date':
			case 'end_time':
			case 'end_year':
			case 'end_month':
			case 'end_day':
			case 'end_hour':
			case 'end_minute':
			case 'end_second':
			case 'end_date_local':
			case 'end_date_orig':
			case 'end_time_local':
			case 'end_time_orig':
			case 'end_timestamp':
			case 'end_iso_gmdate':
				return property_exists($this, 'start');

			case 'uid':
			case 'age':
			case 'type':
			case 'allday':
			case 'duration':
			case 'title':
			case 'location':
			case 'board':
			case 'topic':
			case 'msg':
			case 'modified_time':
			case 'member':
			case 'name':
			case 'groups':
			case 'sequence':
			case 'new':
			case 'is_selected':
			case 'href':
			case 'link':
			case 'can_edit':
			case 'modify_href':
			case 'can_export':
			case 'export_href':
				return true;

			default:
				return $this->customPropertyIsset($prop);
		}
	}

	/**
	 *
	 */
	public function fixTimezone(): void
	{
		$all_timezones = TimeZone::list($this->start->date);

		if (!isset($all_timezones[$this->start->timezone])) {
			$later = strtotime('@' . $this->start->timestamp . ' + 1 year');
			$tzinfo = (new \DateTimeZone($this->start->timezone))->getTransitions($this->start->timestamp, $later);

			$found = false;

			foreach ($all_timezones as $possible_tzid => $dummy) {
				// Ignore the "-----" option
				if (empty($possible_tzid)) {
					continue;
				}

				$possible_tzinfo = (new \DateTimeZone($possible_tzid))->getTransitions($this->start->timestamp, $later);

				if ($tzinfo === $possible_tzinfo) {
					$this->start->timezone = $possible_tzid;
					$found = true;
					break;
				}
			}

			// Hm. That's weird. Well, just prepend it to the list and let the user deal with it.
			if (!$found) {
				$all_timezones = [$this->start->timezone => '[UTC' . $this->start->format('P') . '] - ' . $this->start->timezone] + $all_timezones;
			}
		}
	}

	/**
	 * Retrieves the Event that this EventOccurrence is an occurrence of.
	 *
	 * @return Event The parent Event.
	 */
	public function getParentEvent(): Event
	{
		if (!isset(Event::$loaded[$this->id_event])) {
			Event::load($this->id_event);
		}

		return Event::$loaded[$this->id_event] ?? new Event(-1);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Modifies an individual occurrence of an event.
	 *
	 * @param int $id_event The ID of the parent event.
	 * @param string $id The recurrence ID of the occurrence.
	 * @param array $eventOptions An array of event information.
	 */
	public static function modify(int $id_event, string $id, array &$eventOptions): void
	{
		// Set the new start date and duration.
		Event::setRequestedStartAndDuration($eventOptions);

		$eventOptions['view_start'] = \DateTimeImmutable::createFromInterface($eventOptions['start']);
		$eventOptions['view_end'] = $eventOptions['view_start']->add(new TimeInterval('P1D'));

		list($event) = Event::load($id_event);

		$occurrence = $event->getOccurrence($id);

		$offset = TimeInterval::createFromDateInterval(date_diff($occurrence->unadjusted_start, $eventOptions['start']));

		$occurrence->adjustment = new EventAdjustment(
			$id,
			isset($occurrence->adjustment) && $occurrence->adjustment->affects_future ? !empty($eventOptions['affects_future']) : false,
			(string) $offset !== 'PT0S' ? (array) $offset : null,
			(string) $eventOptions['duration'] !== (string) $occurrence->getParentEvent()->duration ? (array) $eventOptions['duration'] : null,
			isset($eventOptions['location']) && $eventOptions['location'] !== $occurrence->getParentEvent()->location ? Utils::htmlspecialchars($eventOptions['location'], ENT_QUOTES) : null,
			isset($eventOptions['title']) && $eventOptions['title'] !== $occurrence->getParentEvent()->title ? Utils::htmlspecialchars($eventOptions['title'], ENT_QUOTES) : null,
		);

		$occurrence->save();
	}

	/**
	 * Removes an event occurrence from the recurrence set.
	 *
	 * @param int $id_event The parent event's ID.
	 * @param string $id The recurrence ID.
	 */
	public static function remove(int $id_event, string $id, bool $affects_future = false): void
	{
		list($event) = Event::load($id_event);

		if ($event->getFirstOccurrence()->id === $id) {
			Event::remove($id_event);
		} elseif ($affects_future) {
			$event->changeUntil(new Time($id));
			$event->save();
		} else {
			$event->removeOccurrence(new Time($id));
			$event->save();
		}
	}
}

?>