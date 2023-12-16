<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Calendar;

use SMF\Actions\Calendar;
use SMF\ArrayAccessHelper;
use SMF\Config;
use SMF\Time;

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

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'eventid' => 'id_event',
		'id_board' => 'board',
		'id_topic' => 'topic',
		'id_first_msg' => 'msg',
		'sequence' => 'modified_time',
		'id_member' => 'member',
		'poster' => 'member',
		'real_name' => 'name',
		'realname' => 'name',
		'member_groups' => 'groups',
		'allowed_groups' => 'groups',
		'start_object' => 'start',
		'end_object' => 'end',
	];

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

		$this->set($props);

		if (!isset($this->id)) {
			$this->id = $this->allday ? $this->start->format('Ymd') : (clone $this->start)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
		}
	}

	/**
	 * Builds an iCalendar document for this occurrence of the event.
	 *
	 * @return string An iCalendar VEVENT document.
	 */
	public function getVEvent(): string
	{
		// Check the title isn't too long - iCal requires some formatting if so.
		$title = str_split($this->title, 30);

		foreach ($title as $id => $line) {
			if ($id != 0) {
				$title[$id] = ' ' . $title[$id];
			}
		}

		// This is what we will be sending later.
		$filecontents = [];
		$filecontents[] = 'BEGIN:VEVENT';
		$filecontents[] = 'ORGANIZER;CN="' . $this->name . '":MAILTO:' . Config::$webmaster_email;
		$filecontents[] = 'DTSTAMP:' . date('Ymd\\THis\\Z', time());
		$filecontents[] = 'DTSTART' . ($this->allday ? ';VALUE=DATE' : (!in_array($this->tz, RRule::UTC_SYNONYMS) ? ';TZID=' . $this->tz : '')) . ':' . $this->start->format('Ymd' . ($this->allday ? '' : '\\THis' . (in_array($this->tz, RRule::UTC_SYNONYMS) ? '\\Z' : '')));

		// Event has a duration/
		if (
			(!$this->allday && $this->start_iso_gmdate != $this->end_iso_gmdate)
			|| ($this->allday && $this->start_date != $this->end_date)
		) {
			$filecontents[] = 'DTEND' . ($this->allday ? ';VALUE=DATE' : (!in_array($this->tz, RRule::UTC_SYNONYMS) ? ';TZID=' . $this->tz : '')) . ':' . $this->end->format('Ymd' . ($this->allday ? '' : '\\THis' . (in_array($this->tz, RRule::UTC_SYNONYMS) ? '\\Z' : '')));
		}

		// Event has changed? Advance the sequence for this UID.
		if ($this->sequence > 0) {
			$filecontents[] = 'SEQUENCE:' . $this->sequence;
		}

		if (!empty($this->location)) {
			$filecontents[] = 'LOCATION:' . str_replace(',', '\\,', $this->location);
		}

		$filecontents[] = 'SUMMARY:' . implode('', $title);
		$filecontents[] = 'UID:' . $this->uid;
		$filecontents[] = 'RECURRENCE-ID' . ($this->allday ? ';VALUE=DATE' : '') . ':' . $this->id;
		$filecontents[] = 'END:VEVENT';

		return implode("\n", $filecontents);
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
						$value = new \DateInterval((string) $value);
					} catch (\Throwable $e) {
						break;
					}
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
			case 'new':
			case 'is_selected':
			case 'href':
			case 'link':
			case 'can_edit':
			case 'modify_href':
			case 'can_export':
			case 'export_href':
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
	 * Retrieves the Event that this EventOccurrence is an occurrence of.
	 *
	 * @return Event The parent Event.
	 */
	public function getParentEvent(): Event
	{
		if (!isset(Event::$loaded[$this->id_event])) {
			Event::load($this->id_event);
		}

		return Event::$loaded[$this->id_event];
	}
}

?>