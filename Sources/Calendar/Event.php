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
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\TimeInterval;
use SMF\TimeZone;
use SMF\User;
use SMF\Utils;
use SMF\Uuid;

/**
 * Represents a (possibly recurring) calendar event.
 *
 * Overview the process by which the complete recurrence set is built:
 *
 *  1. The $start and $duration values are used to determine when the first
 *     occurrence happens.
 *  2. A RecurrenceIterator is constructed using the RRule, RDates, and ExDates.
 *  3. The RecurrenceIterator generates the list of occurrences.
 *  4. Individual occurrences are instantiated as EventOccurrence objects when
 *     needed.
 *  5. When an EventOccurrence is instantiated, it may have adjustments applied
 *     to it via an EventAdjustment object.
 */
class Event implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*****************
	 * Class constants
	 *****************/

	public const TYPE_EVENT = 0;
	public const TYPE_HOLIDAY = 1;
	public const TYPE_BIRTHDAY = 2;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This event's ID number.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * This event's UID string. (Note: UID is not synonymous with UUID!)
	 *
	 * For events that were created by SMF, the UID will in fact be a UUID.
	 * But if the event was imported from iCal data, it could be anything.
	 */
	public string $uid;

	/**
	 * @var string
	 *
	 * The recurrence ID of the first individual occurrence of the event.
	 *
	 * See RFC 5545, section 3.8.4.4.
	 */
	public string $id_first;

	/**
	 * @var int
	 *
	 * This event's type.
	 * Value must be one of this class's TYPE_* constants.
	 */
	public int $type = self::TYPE_EVENT;

	/**
	 * @var \SMF\Time
	 *
	 * A Time object representing the start of the event's first occurrence.
	 */
	public Time $start;

	/**
	 * @var SMF\TimeInterval
	 *
	 * A TimeInterval object representing the duration of each occurrence of
	 * the event.
	 */
	public TimeInterval $duration;

	/**
	 * @var RecurrenceIterator
	 *
	 * A RecurrenceIterator object to get individual occurrences of the event.
	 */
	public RecurrenceIterator $recurrence_iterator;

	/**
	 * @var bool
	 *
	 * Whether this is an all-day event.
	 */
	public bool $allday = true;

	/**
	 * @var string
	 *
	 * Title of this event.
	 */
	public string $title = '';

	/**
	 * @var string
	 *
	 * Location of this event.
	 */
	public string $location = '';

	/**
	 * @var int
	 *
	 * ID of the board that contains the event's topic.
	 */
	public int $board = 0;

	/**
	 * @var int
	 *
	 * ID of the event's topic.
	 */
	public int $topic = 0;

	/**
	 * @var int
	 *
	 * ID of the first message in the event's topic.
	 */
	public int $msg = 0;

	/**
	 * @var int
	 *
	 * Timestamp when the event's message was last modified.
	 */
	public int $modified_time = 0;

	/**
	 * @var int
	 *
	 * ID of the member who created this event.
	 */
	public int $member = 0;

	/**
	 * @var string
	 *
	 * Displayed name of the member who created this event.
	 */
	public string $name = '';

	/**
	 * @var array
	 *
	 * IDs of member groups that can view this event.
	 */
	public array $groups = [];

	/**
	 * @var array
	 *
	 * Weekdays sorted according to the WKST value, or the current user's
	 * preferences if WKST is not already set.
	 */
	public array $sorted_weekdays = [];

	/**
	 * @var array
	 *
	 * Possible values for the RRule select menu in the UI.
	 *
	 * The descriptions will be overwritten using the language strings in
	 * Lang::$txt['calendar_repeat_rrule_presets']
	 */
	public array $rrule_presets = [
		'never' => 'Never',
		'FREQ=DAILY' => 'Every day',
		'FREQ=WEEKLY' => 'Every week',
		'FREQ=MONTHLY' => 'Every month',
		'FREQ=YEARLY' => 'Every year',
		'custom' => 'Custom...',
	];

	/**
	 * @var array
	 *
	 * Maps frequency values to unit strings.
	 *
	 * The descriptions will be overwritten using the language strings in
	 * Lang::$txt['calendar_repeat_frequency_units']
	 */
	public array $frequency_units = [
		'DAILY' => 'day(s)',
		'WEEKLY' => 'week(s)',
		'MONTHLY' => 'month(s)',
		'YEARLY' => 'year(s)',
	];

	/**
	 * @var array
	 *
	 * Possible values for the BYDAY_num select menu in the UI.
	 */
	public array $byday_num_options = [];

	/**
	 * @var array
	 *
	 * Existing values for the BYDAY_* select menus in the UI.
	 */
	public array $byday_items = [];

	/**
	 * @var array
	 *
	 * Used to override the usual handling of RRule values.
	 *
	 * Value is an array containing a 'base' and a 'modifier'.
	 * The 'base' is one of the keys from self:special_rrules.
	 * The 'modifier' is a + or - sign followed by a duration string, or null.
	 */
	public array $special_rrule;

	/**
	 * @var array
	 *
	 * Info about adjustments to apply to subsets of occurrences of this event.
	 *
	 * Keys are the IDs of EventOccurrence objects.
	 * Values are EventAdjustment objects.
	 */
	public array $adjustments = [];

	/**
	 * @var EventOccurrence
	 *
	 * When editing an event, this is the occurrence being edited.
	 */
	public EventOccurrence $selected_occurrence;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Known special values for the 'rrule' field. These are used when dealing
	 * with recurring events whose recurrence patterns cannot be expressed using
	 * RFC 5545's notation.
	 *
	 * Keys are special strings that may be found in the 'rrule' field in the
	 * database.
	 *
	 * Values are arrays containing the following options:
	 *
	 * 'txt_key' indicates a Lang::$txt string for this special RRule.
	 * If not set, defaults to 'calendar_repeat_special'.
	 *
	 * 'group' indicates special RRules that should be listed as alternatives
	 * to each other. For example, 'group' => ['EASTER_W', 'EASTER_E'] means
	 * that the Western and Eastern ways of calculating the date of Easter
	 * should be listed as the two options for Easter. If not set, defaults to
	 * a list containing only the special string itself.
	 */
	public static array $special_rrules = [
		// Easter (Western)
		'EASTER_W' => [
			'txt_key' => 'calendar_repeat_easter_w',
			'group' => ['EASTER_W', 'EASTER_E'],
		],
		// Easter (Eastern)
		'EASTER_E' => [
			'txt_key' => 'calendar_repeat_easter_e',
			'group' => ['EASTER_W', 'EASTER_E'],
		],
	];

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Increments every time the event is modified.
	 */
	protected int $sequence = 0;

	/**
	 * @var bool
	 *
	 * Whether this event is enabled.
	 * Always true for events and birthdays. May be false for holidays.
	 */
	protected bool $enabled = true;

	/**
	 * @var bool
	 *
	 * Whether we checked access permissions when loading this event.
	 */
	protected bool $use_permissions = true;

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_event' => 'id',
		'eventid' => 'id',
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
	 * @var \DateTimeImmutable
	 *
	 * Occurrences before this date will be skipped when returning results.
	 */
	protected \DateTimeInterface $view_start;

	/**
	 * @var \DateTimeImmutable
	 *
	 * Occurrences after this date will be skipped when returning results.
	 */
	protected \DateTimeInterface $view_end;

	/**
	 * @var string
	 *
	 * The recurrence rule for the RecurrenceIterator.
	 */
	protected string $rrule = '';

	/**
	 * @var array
	 *
	 * Arbitrary dates to add to the recurrence set.
	 */
	protected array $rdates = [];

	/**
	 * @var array
	 *
	 * Arbitrary dates to exclude from the recurrence set.
	 */
	protected array $exdates = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the event.
	 * @param array $props Properties to set for this event.
	 */
	public function __construct(int $id = 0, array $props = [])
	{
		Lang::load('Calendar');

		// Just in case someone passes -2 or something.
		$id = max(-1, $id);

		// Give mods access early in the process.
		IntegrationHook::call('integrate_construct_event', [$id, &$props]);

		$this->handleSpecialRRule($id, $props);

		switch ($id) {
			// Preparing default data to show in the calendar posting form.
			case -1:
				$this->id = $id;
				$props['start'] = $props['start'] ?? new Time('now ' . User::getTimezone());
				$props['duration'] = $props['duration'] ?? new TimeInterval('PT1H');
				$props['member'] = $props['member'] ?? User::$me->id;
				$props['name'] = $props['name'] ?? User::$me->name;
				break;

			// Creating a new event.
			case 0:
				if (!isset($props['start']) || !($props['start'] instanceof \DateTimeInterface)) {
					ErrorHandler::fatalLang('invalid_date', false);
				} elseif (!($props['start'] instanceof Time)) {
					$props['start'] = Time::createFromInterface($props['start']);
				}

				if (!isset($props['duration'])) {
					if (!isset($props['end']) || !($props['end'] instanceof \DateTimeInterface)) {
						ErrorHandler::fatalLang('invalid_date', false);
					} else {
						$props['duration'] = $props['start']->diff($props['end']);
						unset($props['end']);
					}
				}

				if (!isset($props['rrule'])) {
					$props['rrule'] = 'FREQ=YEARLY;COUNT=1';
				} else {
					// The RRule's week start value can affect recurrence results,
					// so make sure to save it using the current user's preference.
					$props['rrule'] = new RRule($props['rrule']);
					$props['rrule']->wkst = RRule::WEEKDAYS[$start_day = ((Theme::$current->options['calendar_start_day'] ?? 0) + 6) % 7];
					$props['rrule'] = (string) $props['rrule'];
				}

				$props['member'] = $props['member'] ?? User::$me->id;
				$props['name'] = $props['name'] ?? User::$me->name;

				break;

			// Loading an existing event.
			default:
				$this->id = $id;
				self::$loaded[$this->id] = $this;
				break;
		}

		$props['rdates'] = array_filter($props['rdates'] ?? []);
		$props['exdates'] = array_filter($props['exdates'] ?? []);

		// Set essential properties.
		$this->uid = empty($props['uid']) ? (string) new Uuid() : $props['uid'];

		$this->rrule = empty($props['rrule']) ? 'FREQ=YEARLY;COUNT=1' : $props['rrule'];

		$this->start = $props['start'] instanceof Time ? $props['start'] : Time::createFromInterface($props['start']);

		$this->allday = !empty($props['allday']);

		$this->duration = TimeInterval::createFromDateInterval($props['duration']) ?? new TimeInterval(!empty($this->allday) ? 'P1D' : 'PT1H');

		if (isset($props['view_start'])) {
			$this->view_start = $props['view_start'] instanceof \DateTimeInterface ? $props['view_start'] : new \DateTimeImmutable($props['view_start']);
		} else {
			$this->view_start = clone $this->start;
		}

		if (isset($props['view_end'])) {
			$this->view_end = $props['view_end'] instanceof \DateTimeInterface ? $props['view_end'] : new \DateTimeImmutable($props['view_end']);
		} else {
			$this->view_end = (clone $this->view_start)->add(new TimeInterval('P1Y'));
		}

		if (!empty($props['rdates'])) {
			$this->rdates = is_array($props['rdates']) ? $props['rdates'] : explode(',', $props['rdates']);

			$vs = $this->view_start->format('Ymd');
			$ve = $this->view_end->format('Ymd');

			foreach ($this->rdates as $key => $rdate) {
				$d = substr($rdate, 0, 8);

				if ($d < $vs || $d > $ve) {
					unset($this->rdates[$key]);

					continue;
				}

				$rdate = explode('/', $rdate);
				$this->rdates[$key] = [
					new \DateTimeImmutable($rdate[0]),
					isset($rdate[1]) ? new TimeInterval($rdate[1]) : null,
				];
			}
		}

		if (!empty($props['exdates'])) {
			$this->exdates = is_array($props['exdates']) ? $props['exdates'] : explode(',', $props['exdates']);

			foreach ($this->exdates as $key => $exdate) {
				$this->exdates[$key] = new \DateTimeImmutable($exdate);
			}
		}

		if ((string) ($props['adjustments'] ?? '') !== '') {
			$this->adjustments = EventAdjustment::createBatch($props['adjustments']);
		}

		unset(
			$props['rrule'],
			$props['start'],
			$props['allday'],
			$props['duration'],
			$props['view_start'],
			$props['view_end'],
			$props['rdates'],
			$props['exdates'],
			$props['adjustments'],
		);

		$this->id_first = $this->allday ? $this->start->format('Ymd') : (clone $this->start)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');

		$this->createRecurrenceIterator();

		// Set any other properties.
		$this->set($props);

		// Now set all the options for the UI.
		foreach ($this->frequency_units as $freq => $unit) {
			$this->frequency_units[$freq] = Lang::$txt['calendar_repeat_frequency_units'][$freq] ?? $unit;
		}

		// Our Lang::$txt arrays use Sunday = 0, but ISO day numbering uses Monday = 0.
		$this->sorted_weekdays = array_flip(RRule::WEEKDAYS);

		while (key($this->sorted_weekdays) != RRule::WEEKDAYS[((Theme::$current->options['calendar_start_day'] ?? 0) + 6) % 7]) {
			$temp_key = key($this->sorted_weekdays);
			$temp_val = array_shift($this->sorted_weekdays);
			$this->sorted_weekdays[$temp_key] = $temp_val;
		}

		foreach ($this->sorted_weekdays as $abbrev => $iso_num) {
			$txt_key = ($iso_num + 1) % 7;
			$this->sorted_weekdays[$abbrev] = [
				'iso_num' => $iso_num,
				'txt_key' => $txt_key,
				'abbrev' => $abbrev,
				'short' => Lang::$txt['days_short'][$txt_key],
				'long' => Lang::$txt['days'][$txt_key],
			];
		}

		foreach (Lang::$txt['calendar_repeat_rrule_presets'] as $rrule => $description) {
			if (isset($this->rrule_presets[$rrule])) {
				$this->rrule_presets[$rrule] = $description;
			}
		}

		$this->byday_num_options = Lang::$txt['calendar_repeat_byday_num_options'];

		uksort(
			$this->byday_num_options,
			function ($a, $b) {
				if ($a < 0 && $b > 0) {
					return 1;
				}

				if ($a > 0 && $b < 0) {
					return -1;
				}

				return abs($a) <=> abs($b);
			},
		);

		// Populate $this->byday_items.
		if (!empty($this->recurrence_iterator->getRRule()->byday)) {
			foreach ($this->recurrence_iterator->getRRule()->byday as $item) {
				list($num, $name) = preg_split('/(?=MO|TU|WE|TH|FR|SA|SU)/', $item);
				$num = empty($num) ? 1 : (int) $num;
				$this->byday_items[] = ['num' => $num, 'name' => $name];
			}
		} else {
			$this->byday_items[] = ['num' => 0, 'name' => ''];
		}

		// Give mods access again at the end of the process.
		IntegrationHook::call('integrate_constructed_event', [$this]);
	}

	/**
	 * Saves this event to the database.
	 */
	public function save(): void
	{
		$is_edit = ($this->id ?? 0) > 0;

		$recurrence_end = $this->getRecurrenceEnd();

		$rrule = !empty($this->special_rrule) ? implode('', $this->special_rrule) : (string) $this->recurrence_iterator->getRRule();

		$rdates = array_unique($this->recurrence_iterator->getRDates());
		$exdates = array_unique($this->recurrence_iterator->getExDates());

		$rdates = array_diff($rdates, $exdates);
		$exdates = array_intersect($exdates, $this->recurrence_iterator->getRRuleOccurrences());

		foreach ($exdates as $key => $exdate) {
			if (new Time($exdate) > $recurrence_end) {
				unset($exdates[$key]);
			}
		}

		foreach ($this->adjustments as $recurrence_id => $adjustment) {
			if (new Time((string) $recurrence_id) > $recurrence_end) {
				unset($this->adjustments[$recurrence_id]);

				continue;
			}

			if (
				(
					!isset($adjustment->offset)
					|| (string) $adjustment->offset === 'PT0S'
				)
				&& (
					!isset($adjustment->duration)
					|| (string) $adjustment->duration === (string) $this->duration
				)
				&& (
					!isset($adjustment->location)
					|| $adjustment->location === $this->location
				)
				&& (
					!isset($adjustment->title)
					|| $adjustment->title === $this->title
				)
			) {
				unset($this->adjustments[$recurrence_id]);
			}
		}

		ksort($this->adjustments);

		// Saving a new event.
		if (!$is_edit) {
			$columns = [
				'start_date' => 'date',
				'end_date' => 'date',
				'id_board' => 'int',
				'id_topic' => 'int',
				'title' => 'string-255',
				'id_member' => 'int',
				'location' => 'string-255',
				'duration' => 'string-32',
				'rrule' => 'string',
				'rdates' => 'string',
				'exdates' => 'string',
				'adjustments' => 'string',
				'sequence' => 'int',
				'uid' => 'string-255',
				'type' => 'int',
				'enabled' => 'int',
			];

			$params = [
				$this->start->format('Y-m-d'),
				$recurrence_end->format('Y-m-d'),
				$this->board,
				$this->topic,
				Utils::truncate($this->title, 255),
				$this->member,
				Utils::truncate($this->location, 255),
				(string) $this->duration,
				$rrule,
				implode(',', $rdates),
				implode(',', $exdates),
				json_encode($this->adjustments),
				0,
				$this->uid,
				$this->type,
				(int) $this->enabled,
			];

			if (!$this->allday) {
				$columns['start_time'] = 'time';
				$params[] = $this->start->format('H:i:s');

				$columns['timezone'] = 'string';
				$params[] = $this->start->format('e');
			}

			IntegrationHook::call('integrate_create_event', [$this, &$columns, &$params]);

			if (isset($this->id)) {
				unset(self::$loaded[$this->id]);
			}

			$this->id = Db::$db->insert(
				'',
				'{db_prefix}calendar',
				$columns,
				$params,
				['id_event'],
				1,
			);

			self::$loaded[$this->id] = $this;
		}
		// Updating an existing event.
		else {
			$set = [
				'start_date = {date:start_date}',
				'end_date = {date:end_date}',
				'title = {string:title}',
				'id_board = {int:id_board}',
				'id_topic = {int:id_topic}',
				'location = {string:location}',
				'duration = {string:duration}',
				'rrule = {string:rrule}',
				'rdates = {string:rdates}',
				'exdates = {string:exdates}',
				'adjustments = {string:adjustments}',
				'sequence = {int:sequence}',
				'uid = {string:uid}',
				'type = {int:type}',
				'enabled = {int:enabled}',
			];

			$params = [
				'id' => $this->id,
				'start_date' => $this->start->format('Y-m-d'),
				'end_date' => $recurrence_end->format('Y-m-d'),
				'title' => Utils::truncate($this->title, 255),
				'location' => Utils::truncate($this->location, 255),
				'id_board' => $this->board,
				'id_topic' => $this->topic,
				'duration' => (string) $this->duration,
				'rrule' => $rrule,
				'rdates' => implode(',', $rdates),
				'exdates' => implode(',', $exdates),
				'adjustments' => json_encode($this->adjustments),
				'sequence' => ++$this->sequence,
				'uid' => $this->uid,
				'type' => $this->type,
				'enabled' => (int) $this->enabled,
			];

			if ($this->allday) {
				$set[] = 'start_time = NULL';
				$set[] = 'timezone = NULL';
			} else {
				$set[] = 'start_time = {time:start_time}';
				$params['start_time'] = $this->start->format('H:i:s');

				$set[] = 'timezone = {string:timezone}';
				$params['timezone'] = $this->start->format('e');
			}

			// Why pass `$this->id` if we're also passing `$this`, you ask? For historical reasons.
			IntegrationHook::call('integrate_modify_event', [$this->id, $this, &$set, &$params]);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}calendar
				SET ' . (implode(', ', $set)) . '
				WHERE id_event = {int:id}',
				$params,
			);
		}

		Config::updateModSettings([
			'calendar_updated' => time(),
		]);
	}

	/**
	 * Builds iCalendar components for events, including recurrence info.
	 *
	 * @return string One or more VEVENT components for an iCalendar document.
	 */
	public function export(): string
	{
		$filecontents = [];
		$filecontents[] = 'BEGIN:VEVENT';

		$filecontents[] = 'SUMMARY:' . $this->title;

		if (!empty($this->location)) {
			$filecontents[] = 'LOCATION:' . str_replace(',', '\\,', $this->location);
		}

		if (!empty($this->topic)) {
			$filecontents[] = 'URL:' . Config::$scripturl . '?topic=' . $this->topic . '.0';
		}

		$filecontents[] = 'UID:' . $this->uid;
		$filecontents[] = 'SEQUENCE:' . $this->sequence;

		$filecontents[] = 'DTSTAMP:' . date('Ymd\\THis\\Z', $this->modified_time ?? time());
		$filecontents[] = 'DTSTART' . ($this->allday ? ';VALUE=DATE' : (!in_array($this->tz, RRule::UTC_SYNONYMS) ? ';TZID=' . $this->tz : '')) . ':' . $this->start->format('Ymd' . ($this->allday ? '' : '\\THis' . (in_array($this->tz, RRule::UTC_SYNONYMS) ? '\\Z' : '')));
		$filecontents[] = 'DURATION:' . (string) $this->duration;

		if ((string) $this->recurrence_iterator->getRRule() !== 'FREQ=YEARLY;COUNT=1') {
			$filecontents[] = 'RRULE:' . (string) $this->recurrence_iterator->getRRule();
		}

		if ($this->recurrence_iterator->getRDates() !== []) {
			$filecontents[] = 'RDATE' . ($this->allday ? ';VALUE=DATE' : '') . ':' . implode(",\r\n ", $this->recurrence_iterator->getRDates());
		}

		if ($this->recurrence_iterator->getExDates() !== []) {
			$filecontents[] = 'EXDATE' . ($this->allday ? ';VALUE=DATE' : '') . ':' . implode(",\r\n ", $this->recurrence_iterator->getExDates());
		}

		$filecontents[] = 'END:VEVENT';

		// Fit all lines within iCalendar's line width restraint.
		foreach ($filecontents as $line_num => $line) {
			$filecontents[$line_num] = self::foldICalLine($line);
		}

		// Adjusted occurrences need their own VEVENTs.
		foreach ($this->adjustments as $recurrence_id => $adjustment) {
			$occurrence = $this->getOccurrence($recurrence_id);
			$filecontents[] = $occurrence->export();
		}

		return implode("\r\n", $filecontents);
	}

	/**
	 * Adds an arbitrary date to the recurrence set.
	 *
	 * Used for making exceptions to the general recurrence rule.
	 *
	 * @param \DateTimeInterface $date The date to add.
	 * @param ?\DateInterval $duration Optional duration for this occurrence.
	 *    Only necessary if the duration for this occurrence differs from the
	 *    usual duration of the event.
	 */
	public function addOccurrence(\DateTimeInterface $date, ?\DateInterval $duration = null): void
	{
		// The recurrence iterator ignores dates beyond $this->view_end.
		if ($date > $this->view_end) {
			$this->view_end = \DateTimeImmutable::createFromInterface($date)->modify('+1 second');
			$this->createRecurrenceIterator();
		}

		$this->recurrence_iterator->add($date, $duration);
	}

	/**
	 * Removes a date from the recurrence set.
	 *
	 * Used for making exceptions to the general recurrence rule.
	 *
	 * @param \DateTimeInterface $date The date to remove.
	 */
	public function removeOccurrence(\DateTimeInterface $date): void
	{
		$this->recurrence_iterator->remove($date);
	}

	/**
	 * Returns a generator that yields all occurrences of the event between
	 * $this->view_start and $this->view_end.
	 *
	 * @return Generator<EventOccurrence> Iterating over result gives
	 *    EventOccurrence instances.
	 */
	public function getAllVisibleOccurrences(): \Generator
	{
		// Where are we currently?
		$orig_key = $this->recurrence_iterator->key();

		// Go to the start.
		$this->recurrence_iterator->rewind();

		while ($this->recurrence_iterator->valid()) {
			yield $this->createOccurrence($this->recurrence_iterator->current());
			$this->recurrence_iterator->next();
		}

		// Go back to where we were before.
		$this->recurrence_iterator->setKey($orig_key);
	}

	/**
	 * Gets the next occurrence of the event after the date given by $when.
	 *
	 * @param ?\DateTimeInterface $when The moment from which we should start
	 *    looking for the next occurrence. If null, uses now.
	 * @return EventOccurrence|false An EventOccurrence object, or false if no
	 *    occurrences happen after $when.
	 */
	public function getUpcomingOccurrence(?\DateTimeInterface $when = null): EventOccurrence|false
	{
		if (!isset($when)) {
			$when = new \DateTimeImmutable('now');
		}

		if (!$this->recurrence_iterator->valid() || $this->recurrence_iterator->current() > $when) {
			$this->recurrence_iterator->rewind();
		}

		if (!$this->recurrence_iterator->valid()) {
			return false;
		}

		while ($this->recurrence_iterator->valid() && $this->recurrence_iterator->current() < $when) {
			$this->recurrence_iterator->next();

			if (!$this->recurrence_iterator->valid()) {
				return false;
			}
		}

		return $this->createOccurrence($this->recurrence_iterator->current());
	}

	/**
	 * Gets the first occurrence of the event.
	 *
	 * @return EventOccurrence|false EventOccurrence object, or false on error.
	 */
	public function getFirstOccurrence(): EventOccurrence|false
	{
		// Where are we currently?
		$orig_key = $this->recurrence_iterator->key();

		// Go to the start.
		$this->recurrence_iterator->rewind();

		// Create the occurrence object.
		if ($this->recurrence_iterator->valid()) {
			$occurrence = $this->createOccurrence($this->recurrence_iterator->current());
		}

		// Go back to where we were before.
		$this->recurrence_iterator->setKey($orig_key);

		// Return the occurrence object, or false on error.
		return $occurrence ?? false;
	}

	/**
	 * Gets the last occurrence of the event.
	 *
	 * @return EventOccurrence|false EventOccurrence object, or false on error.
	 */
	public function getLastOccurrence(): EventOccurrence|false
	{
		// Where are we currently?
		$orig_key = $this->recurrence_iterator->key();

		// Go to the end.
		$this->recurrence_iterator->end();

		// Create the occurrence object.
		if ($this->recurrence_iterator->valid()) {
			$occurrence = $this->createOccurrence($this->recurrence_iterator->current());
		}

		// Go back to where we were before.
		$this->recurrence_iterator->setKey($orig_key);

		// Return the occurrence object, or false on error.
		return $occurrence ?? false;
	}

	/**
	 * Gets an occurrence of the event by its recurrence ID.
	 *
	 * @param string $id The recurrence ID string.
	 * @return EventOccurrence|false EventOccurrence object, or false on error.
	 */
	public function getOccurrence(string $id): EventOccurrence|false
	{
		// Where are we currently?
		$orig_key = $this->recurrence_iterator->key();

		// Search for the requested ID.
		if (($key = $this->recurrence_iterator->search($id)) !== false) {
			// Select the requested occurrence.
			$this->recurrence_iterator->setKey($key);

			// Create the occurrence object.
			$occurrence = $this->createOccurrence($this->recurrence_iterator->current());
		}

		// Go back to where we were before.
		$this->recurrence_iterator->setKey($orig_key);

		// Return the occurrence object, or false on error.
		return $occurrence ?? false;
	}

	/**
	 * Updates the RRule to set a new UNTIL date.
	 *
	 * This is somewhat complicated because it requires updating the recurrence
	 * iterator and its underlying RRule.
	 */
	public function changeUntil(\DateTimeInterface $until): void
	{
		if ($until < $this->start) {
			throw new \ValueError();
		}

		$rrule = $this->recurrence_iterator->getRRule();

		unset($rrule->count);
		$rrule->until = Time::createFromInterface($until);
		$rrule->until_type = $rrule->until_type ?? ($this->allday ? RecurrenceIterator::TYPE_ALLDAY : RecurrenceIterator::TYPE_ABSOLUTE);

		$this->rrule = (string) $rrule;

		$this->createRecurrenceIterator();
	}
	/**
	 * Gets the date after which no more occurrences happen.
	 *
	 * @return Time When the recurrence ends.
	 */
	public function getRecurrenceEnd(): Time
	{
		// If there's no recurrence, there's nothing to do.
		if (!isset($this->recurrence_iterator)) {
			return $this->start;
		}

		// When we have an until value, life is easy.
		if (!empty($this->recurrence_iterator->getRRule()->until)) {
			return Time::createFromInterface($this->recurrence_iterator->getRRule()->until)->modify('-1 second');
		}

		// A count value takes more work.
		if (!empty($this->recurrence_iterator->getRRule()->count)) {
			// If the count is 1, then the start is the end.
			if ($this->recurrence_iterator->getRRule()->count == 1) {
				return $this->start;
			}

			// Save current values.
			$view_start = clone $this->view_start;
			$view_end = clone $this->view_end;
			$recurrence_iterator = clone $this->recurrence_iterator;

			// Make new recurrence iterator that gets all occurrences.
			$this->rrule = (string) $recurrence_iterator->getRRule();
			$this->view_start = clone $this->start;
			$this->view_end = new Time('9999-12-31');

			unset($this->recurrence_iterator);
			$this->createRecurrenceIterator();

			// Get last occurrence.
			$this->recurrence_iterator->end();
			$value = Time::createFromInterface($this->recurrence_iterator->current());

			// Put everything back.
			$this->view_start = $view_start;
			$this->view_end = $view_end;
			$this->recurrence_iterator = $recurrence_iterator;

			return $value;
		}

		// Forever.
		return new Time('9999-12-31');
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, mixed $value): void
	{
		if (!isset($this->start)) {
			$this->start = new Time();
		}

		if (!isset($this->duration)) {
			$this->duration = new TimeInterval(!empty($this->allday) ? 'P1D' : 'PT1H');
		}

		if (str_starts_with($prop, 'end') || str_starts_with($prop, 'last')) {
			$end = (clone $this->start)->add($this->duration);
		}

		if (str_starts_with($prop, 'last')) {
			$last = (clone $end)->modify('-1 ' . ($this->allday ? 'day' : 'second'));
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
				$this->duration = $value;
				break;

			case 'end':
				if (!($value instanceof \DateTimeInterface)) {
					try {
						$value = new \DateTimeImmutable((is_numeric($value) ? '@' : '') . $value);
					} catch (\Throwable $e) {
						break;
					}
				}
				$this->duration = TimeInterval::createFromDateInterval($this->start->diff($value));
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
				$this->duration = $this->start->diff($end);
				break;

			case 'last':
				if (!($value instanceof \DateTimeInterface)) {
					try {
						$value = new \DateTimeImmutable((is_numeric($value) ? '@' : '') . $value);
					} catch (\Throwable $e) {
						break;
					}
				}
				$this->duration = $this->start->diff($value->modify('+1 ' . ($this->allday ? 'day' : 'second')));
				break;

			case 'last_datetime':
			case 'last_date':
			case 'last_date_local':
			case 'last_date_orig':
			case 'last_time':
			case 'last_time_local':
			case 'last_time_orig':
			case 'last_year':
			case 'last_month':
			case 'last_day':
			case 'last_hour':
			case 'last_minute':
			case 'last_second':
			case 'last_timestamp':
			case 'last_iso_gmdate':
				$last->{substr($prop, 5)} = $value;
				$end = (clone $last)->modify('+1 ' . ($this->allday ? 'day' : 'second'));
				$this->duration = $this->start->diff($end);
				break;

			// Special handling for stuff that affects recurrence.
			case 'rrule':
			case 'view_start':
			case 'view_end':
				$this->{$prop} = $value;
				$this->createRecurrenceIterator();
				break;

			case 'rdates':
				$this->rdates = is_array($value) ? $value : explode(',', (string) $value);

				foreach ($this->rdates as $key => $rdate) {
					$rdate = explode('/', $rdate);
					$this->rdates[$key] = [
						new \DateTimeImmutable($rdate[0]),
						isset($rdate[1]) ? new TimeInterval($rdate[1]) : null,
					];
				}
				$this->createRecurrenceIterator();
				break;

			case 'exdates':
				$this->exdates = is_array($value) ? $value : explode(',', (string) $value);

				foreach ($this->exdates as $key => $exdate) {
					$this->exdates[$key] = new \DateTimeImmutable($exdate);
				}
				$this->createRecurrenceIterator();
				break;

			// These computed properties are read-only.
			case 'new':
			case 'is_selected':
			case 'href':
			case 'link':
			case 'can_edit':
			case 'modify_href':
			case 'can_export':
			case 'export_href':
				break;

			// Everything else.
			default:
				$this->customPropertySet($prop, $value);
				break;
		}
	}

	/**
	 * Gets custom property values.
	 *
	 * @param string $prop The property name.
	 * @return mixed The property value.
	 */
	public function __get(string $prop): mixed
	{
		if (str_starts_with($prop, 'end') || str_starts_with($prop, 'last')) {
			$end = (clone $this->start)->add($this->duration);
		}

		if (str_starts_with($prop, 'last')) {
			$last = (clone $end)->modify('-1 ' . ($this->allday ? 'day' : 'second'));
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
				$value = $this->start->{$prop};
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
				$value = $this->start->{substr($prop, 6)};
				break;

			case 'end':
				$value = $end;
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
				$value = $end->{substr($prop, 4)};
				break;

			case 'last':
				$value = $last;
				break;

			case 'last_datetime':
			case 'last_date':
			case 'last_date_local':
			case 'last_date_orig':
			case 'last_time':
			case 'last_time_local':
			case 'last_time_orig':
			case 'last_year':
			case 'last_month':
			case 'last_day':
			case 'last_hour':
			case 'last_minute':
			case 'last_second':
			case 'last_timestamp':
			case 'last_iso_gmdate':
				$value = $last->{substr($prop, 5)};
				break;

			case 'new':
				$value = !isset($this->id) || $this->id < 1;
				break;

			case 'is_selected':
				$value = !empty(Utils::$context['selected_event']) && Utils::$context['selected_event'] == $this->id;
				break;

			case 'href':
				$value = empty($this->topic) ? '' : Config::$scripturl . '?topic=' . $this->topic . '.0';
				break;

			case 'link':
				$value = empty($this->topic) ? $this->title : '<a href="' . Config::$scripturl . '?topic=' . $this->topic . '.0">' . $this->title . '</a>';
				break;

			case 'can_edit':
				$value = $this->use_permissions ? User::$me->allowedTo('calendar_edit_any') || ($this->member == User::$me->id && User::$me->allowedTo('calendar_edit_own')) : false;
				break;

			case 'modify_href':
				$value = Config::$scripturl . '?action=' . ($this->board == 0 ? 'calendar;sa=post;' : 'post;msg=' . $this->msg . ';topic=' . $this->topic . '.0;calendar;') . 'eventid=' . $this->id . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
				break;

			case 'can_export':
				$value = !empty(Config::$modSettings['cal_export']);
				break;

			case 'export_href':
				$value = Config::$scripturl . '?action=calendar;sa=ical;eventid=' . $this->id . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
				break;

			case 'rrule':
				$value = isset($this->recurrence_iterator) ? $this->recurrence_iterator->getRRule() : null;
				break;

			case 'rrule_preset':
				if (!empty($this->special_rrule)) {
					$value = $this->special_rrule['base'];
					break;
				}

				if (isset($this->recurrence_iterator)) {
					$value = $this->recurrence_iterator->getRRule();
				} else {
					$value = null;
					break;
				}

				if (($value->count ?? 0) === 1) {
					$value = 'never';
				} else {
					unset($value->count, $value->until, $value->until_type, $value->wkst);
					$value = (string) $value;
				}
				break;

			case 'rdates':
				$value = isset($this->recurrence_iterator) ? $this->recurrence_iterator->getRDates() : null;
				break;

			case 'exdates':
				$value = isset($this->recurrence_iterator) ? $this->recurrence_iterator->getExDates() : null;
				break;

			default:
				$value = $this->customPropertyGet($prop);
				break;
		}

		unset($end, $last);

		return $value;
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param string $prop The property name.
	 * @return bool Whether the property has been set.
	 */
	public function __isset(string $prop): bool
	{
		switch ($prop) {
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
				return isset($this->start);

			case 'end':
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
			case 'last':
			case 'last_datetime':
			case 'last_date':
			case 'last_date_local':
			case 'last_date_orig':
			case 'last_time':
			case 'last_time_local':
			case 'last_time_orig':
			case 'last_year':
			case 'last_month':
			case 'last_day':
			case 'last_hour':
			case 'last_minute':
			case 'last_second':
			case 'last_timestamp':
			case 'last_iso_gmdate':
				return isset($this->start, $this->duration);

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

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads an event by ID number or by topic ID.
	 *
	 * @param int $id ID number of the event or topic.
	 * @param bool $is_topic If true, $id is the topic ID. Default: false.
	 * @param bool $use_permissions Whether to use permissions. Default: true.
	 * @return array Instances of this class for the loaded events.
	 */
	public static function load(int $id, bool $is_topic = false, bool $use_permissions = true): array
	{
		if ($id <= 0) {
			return [];
		}

		if (!$is_topic && isset(self::$loaded[$id])) {
			return [self::$loaded[$id]];
		}

		$loaded = [];

		$selects = [
			'cal.*',
			'b.id_board',
			'b.member_groups',
			't.id_first_msg',
			't.approved',
			'm.modified_time',
			'mem.real_name',
		];
		$joins = [
			'LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)',
			'LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)',
			'LEFT JOIN {db_prefix}messages AS m ON (m.id_msg  = t.id_first_msg)',
			'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)',
		];
		$where = ['cal.id_' . ($is_topic ? 'topic' : 'event') . ' = {int:id}'];
		$order = ['cal.start_date'];
		$group = [];
		$limit = 0;
		$params = [
			'id' => $id,
			'no_board_link' => 0,
		];

		if ($use_permissions) {
			$where[] = '(cal.id_board = {int:no_board_link} OR {query_wanna_see_board})';
		}

		IntegrationHook::call('integrate_query_event', [&$selects, &$params, &$joins, &$where, &$order, &$group, &$limit]);

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			// If the attached topic is not approved then for the moment pretend it doesn't exist.
			if (!empty($row['id_first_msg']) && Config::$modSettings['postmod_active'] && !$row['approved']) {
				continue;
			}

			unset($row['approved']);

			$id = (int) $row['id_event'];
			$row['use_permissions'] = $use_permissions;

			$rrule = new RRule($row['rrule']);

			switch ($rrule->freq) {
				case 'SECONDLY':
					$unit = 'seconds';
					break;

				case 'MINUTELY':
					$unit = 'minutes';
					break;

				case 'HOURLY':
					$unit = 'hours';
					break;

				case 'DAILY':
					$unit = 'days';
					break;

				case 'WEEKLY':
					$unit = 'weeks';
					break;

				case 'MONTHLY':
					$unit = 'months';
					break;

				default:
					$unit = 'years';
					break;
			}

			$row['view_end'] = new \DateTimeImmutable('now + ' . (RecurrenceIterator::DEFAULT_COUNTS[$rrule->freq]) . ' ' . $unit);

			$loaded[] = (new self($id, $row));
		}

		return $loaded;
	}

	/**
	 * Generator that yields instances of this class.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $use_permissions Whether to use permissions. Default: true.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<Event> Iterating over result gives Event instances.
	 */
	public static function get(string $low_date, string $high_date, bool $use_permissions = true, array $query_customizations = []): \Generator
	{
		$low_date = !empty($low_date) ? $low_date : '1000-01-01';
		$high_date = !empty($high_date) ? $high_date : '9999-12-31';

		$selects = $query_customizations['selects'] ?? [
			'cal.*',
			'b.id_board',
			'b.member_groups',
			't.id_first_msg',
			't.approved',
			'm.modified_time',
			'mem.real_name',
		];
		$joins = $query_customizations['joins'] ?? [
			'LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)',
			'LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)',
			'LEFT JOIN {db_prefix}messages AS m ON (m.id_msg  = t.id_first_msg)',
			'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)',
		];
		$where = $query_customizations['where'] ?? [
			'cal.start_date <= {date:high_date}',
			'cal.end_date >= {date:low_date}',
			'type = {int:type}',
		];
		$order = $query_customizations['order'] ?? ['cal.start_date'];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [
			'high_date' => $high_date,
			'low_date' => $low_date,
			'no_board_link' => 0,
			'type' => self::TYPE_EVENT,
		];

		if ($use_permissions) {
			$where[] = '(cal.id_board = {int:no_board_link} OR {query_wanna_see_board})';
		}

		IntegrationHook::call('integrate_query_event', [&$selects, &$params, &$joins, &$where, &$order, &$group, &$limit]);

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			// If the attached topic is not approved then for the moment pretend it doesn't exist.
			if (!empty($row['id_first_msg']) && Config::$modSettings['postmod_active'] && !$row['approved']) {
				continue;
			}

			unset($row['approved']);

			$id = (int) $row['id_event'];
			$row['use_permissions'] = $use_permissions;

			$row['view_start'] = new \DateTimeImmutable($low_date);
			$row['view_end'] = new \DateTimeImmutable($high_date);

			yield (new self($id, $row));
		}
	}

	/**
	 * Gets events within the given date range, and returns a generator that
	 * yields all occurrences of those events within that range.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $use_permissions Whether to use permissions. Default: true.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<EventOccurrence> Iterating over result gives
	 *    EventOccurrence instances.
	 */
	public static function getOccurrencesInRange(string $low_date, string $high_date, bool $use_permissions = true, array $query_customizations = []): \Generator
	{
		foreach (self::get($low_date, $high_date, $use_permissions, $query_customizations) as $event) {
			foreach ($event->getAllVisibleOccurrences() as $occurrence) {
				yield $occurrence;
			}
		}
	}

	/**
	 * Creates an event and saves it to the database.
	 *
	 * Does not check permissions.
	 *
	 * @param array $eventOptions Event data ('title', 'start_date', etc.)
	 */
	public static function create(array $eventOptions): void
	{
		// Sanitize the title and location.
		foreach (['title', 'location'] as $key) {
			$eventOptions[$key] = Utils::htmlspecialchars($eventOptions[$key] ?? '', ENT_QUOTES);
		}

		// Set the start and end dates.
		self::setRequestedStartAndDuration($eventOptions);

		$eventOptions['view_start'] = \DateTimeImmutable::createFromInterface($eventOptions['start']);
		$eventOptions['view_end'] = $eventOptions['view_start']->modify('+1 month');

		self::setRequestedRRule($eventOptions);

		$event = new self(0, $eventOptions);

		self::setRequestedRDatesAndExDates($event);

		$event->save();

		// If this isn't tied to a topic, we need to notify people about it.
		if (empty($event->topic)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\EventNew_Notify',
					Utils::jsonEncode([
						'event_title' => $event->title,
						'event_id' => $event->id,
						'sender_id' => $event->member,
						'sender_name' => $event->member == User::$me->id ? User::$me->name : '',
						'time' => time(),
					]),
					0,
				],
				['id_task'],
			);
		}
	}

	/**
	 * Modifies an event.
	 *
	 * Does not check permissions.
	 *
	 * @param int $id The ID of the event
	 * @param array $eventOptions An array of event information.
	 */
	public static function modify(int $id, array &$eventOptions): void
	{
		list($event) = self::load($id);

		// If request was to modify a specific occurrence, do that instead.
		if (
			!empty($eventOptions['recurrenceid'])
			&& $event->getFirstOccurrence()->id != $eventOptions['recurrenceid']
		) {
			$rid = $eventOptions['recurrenceid'];
			unset($eventOptions['recurrenceid']);

			EventOccurrence::modify($id, $rid, $eventOptions);

			return;
		}

		unset($eventOptions['recurrenceid']);

		// Sanitize the title and location.
		foreach (['title', 'location'] as $key) {
			$eventOptions[$key] = Utils::htmlspecialchars($eventOptions[$key] ?? '', ENT_QUOTES);
		}

		// Set the new start date and duration.
		self::setRequestedStartAndDuration($eventOptions);

		$eventOptions['view_start'] = \DateTimeImmutable::createFromInterface($eventOptions['start']);
		$eventOptions['view_end'] = $eventOptions['view_start']->modify('+1 month');

		self::setRequestedRRule($eventOptions);

		$event->set($eventOptions);

		self::setRequestedRDatesAndExDates($event);

		$event->save();
	}

	/**
	 * Removes an event.
	 *
	 * Does not check permissions.
	 *
	 * @param int $id The event's ID.
	 */
	public static function remove(int $id): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}calendar
			WHERE id_event = {int:id_event}',
			[
				'id_event' => $id,
			],
		);

		IntegrationHook::call('integrate_remove_event', [$id]);

		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		unset(self::$loaded[$id]);
	}

	/**
	 * Imports events from iCalendar data and saves them to the database.
	 *
	 * @param string $ics Some iCalendar data (e.g. the content of an ICS file).
	 * @return array An array of instances of this class.
	 */
	public static function import(string $ics): array
	{
		$events = self::constructFromICal($ics, self::TYPE_EVENT);

		foreach ($events as $event) {
			$event->save();
		}

		return $events;
	}

	/**
	 * Constructs instances of this class from iCalendar data.
	 *
	 * @param string $ics Some iCalendar data (e.g. the content of an ICS file).
	 * @param ?int $type Forces all events to be imported as the specified type.
	 *    Values can be one of this class's TYPE_* constants, or null for auto.
	 * @return array An array of instances of this class.
	 */
	public static function constructFromICal(string $ics, ?int $type = null): array
	{
		$events = [];

		$ics = preg_replace('/\R\h/', '', $ics);

		$lines = preg_split('/\R/', $ics);

		$in_event = false;
		$props = [];

		foreach ($lines as $line) {
			if ($line === 'BEGIN:VEVENT') {
				$in_event = true;
			}

			if ($line === 'END:VEVENT') {
				if (
					isset(
						$props['start'],
						$props['duration'],
						$props['title'],
					)
				) {
					if (isset($type)) {
						$props['type'] = $type;
					}

					if (isset($props['type']) && $props['type'] === self::TYPE_HOLIDAY) {
						$events[] = new Holiday(0, $props);
					} else {
						unset($props['type']);
						$events[] = new self(0, $props);
					}
				}

				$in_event = false;
				$props = [];
			}

			if (!$in_event) {
				continue;
			}

			if (str_starts_with($line, 'DTSTART')) {
				if (preg_match('/;TZID=([^:;]+)[^:]*:(\d+T\d+)/', $line, $matches)) {
					$props['start'] = new Time($matches[2] . ' ' . $matches[1]);
					$props['allday'] = false;
				} elseif (preg_match('/:(\d+T\d+)(Z?)/', $line, $matches)) {
					$props['start'] = new Time($matches[1] . ($matches[2] === 'Z' ? ' UTC' : ''));
					$props['allday'] = false;
				} elseif (preg_match('/:(\d+)/', $line, $matches)) {
					$props['start'] = new Time($matches[1] . ' ' . User::getTimezone());
					$props['allday'] = true;
				}
			}

			if (str_starts_with($line, 'DTEND')) {
				if (preg_match('/;TZID=([^:;]+)[^:]*:(\d+T\d+)/', $line, $matches)) {
					$end = new Time($matches[2] . ' ' . $matches[1]);
				} elseif (preg_match('/:(\d+T\d+)(Z?)/', $line, $matches)) {
					$end = new Time($matches[1] . ($matches[2] === 'Z' ? ' UTC' : ''));
				} elseif (preg_match('/:(\d+)/', $line, $matches)) {
					$end = new Time($matches[1] . ' ' . User::getTimezone());
				}

				$props['duration'] = TimeInterval::createFromDateInterval($props['start']->diff($end));
			}

			if (str_starts_with($line, 'DURATION')) {
				$props['duration'] = new TimeInterval(substr($line, strpos($line, ':') + 1));
			}

			if (!isset($type) && str_starts_with($line, 'CATEGORIES')) {
				$props['type'] = str_contains(strtolower($line), 'holiday') ? self::TYPE_HOLIDAY : self::TYPE_EVENT;
			}

			if (str_starts_with($line, 'SUMMARY')) {
				$props['title'] = substr($line, strpos($line, ':') + 1);
			}

			if (str_starts_with($line, 'LOCATION')) {
				$props['location'] = substr($line, strpos($line, ':') + 1);
			}

			if (str_starts_with($line, 'RRULE')) {
				$props['rrule'] = substr($line, strpos($line, ':') + 1);
			}

			if (str_starts_with($line, 'RDATE')) {
				$props['rdates'] = explode(',', substr($line, strpos($line, ':') + 1));
			}

			if (str_starts_with($line, 'EXDATE')) {
				$props['exdates'] = explode(',', substr($line, strpos($line, ':') + 1));
			}
		}

		return $events;
	}

	/**
	 * Folds lines of text to fit within the iCalendar line width restraint.
	 *
	 * @param string $line The line of text to fold.
	 * @return string $line The folded version of $line.
	 */
	public static function foldICalLine(string $line): string
	{
		$folded = [];

		$temp = '';

		foreach (mb_str_split($line) as $char) {
			if (strlen($temp . $char) > 75) {
				$folded[] = $temp;
				$temp = '';
			}

			$temp .= $char;
		}

		$folded[] = $temp;

		return implode("\r\n ", $folded);
	}

	/**
	 * Set the start and end dates and times for a posted event for insertion
	 * into the database.
	 *
	 *  - Validates all date and times given to it.
	 *  - Makes sure events do not exceed the maximum allowed duration (if any).
	 *  - If passed an array that defines any time or date parameters, they will
	 *    be used. Otherwise, gets the values from $_POST.
	 *
	 * @param array $eventOptions An array of optional time and date parameters
	 *    (span, start_year, end_month, etc., etc.)
	 */
	public static function setRequestedStartAndDuration(array &$eventOptions): void
	{
		// Convert unprefixed time unit parameters to start_* parameters.
		foreach (['year', 'month', 'day', 'hour', 'minute', 'second'] as $key) {
			foreach ([$eventOptions, $_POST] as &$array) {
				if (isset($array[$key])) {
					$array['start_' . $key] = $array[$key];
					unset($array[$key]);
				}
			}
		}

		// Try to fill missing values in $eventOptions with values from $_POST.
		foreach (['year', 'month', 'day', 'hour', 'minute', 'second', 'date', 'time', 'datetime'] as $key) {
			$eventOptions['start_' . $key] = $eventOptions['start_' . $key] ?? ($_POST['start_' . $key] ?? null);

			$eventOptions['end_' . $key] = $eventOptions['end_' . $key] ?? ($_POST['end_' . $key] ?? null);
		}

		foreach (['allday', 'timezone'] as $key) {
			$eventOptions[$key] = $eventOptions[$key] ?? ($_POST[$key] ?? null);
		}

		// Standardize the input.
		$eventOptions = self::standardizeEventOptions($eventOptions);

		// Create our two Time objects.
		$eventOptions['start'] = new Time(implode(' ', [$eventOptions['start_date'], $eventOptions['start_time'], $eventOptions['timezone']]));

		$eventOptions['end'] = new Time(implode(' ', [$eventOptions['end_date'], $eventOptions['end_time'], $eventOptions['timezone']]));

		// Make sure the two dates have a sane relationship.
		if ($eventOptions['end']->getTimestamp() < $eventOptions['start']->getTimestamp()) {
			$eventOptions['end']->setTimestamp($eventOptions['start']->getTimestamp());
		}

		// Ensure 'allday' is a boolean.
		$eventOptions['allday'] = !empty($eventOptions['allday']);

		// Now replace 'end' with 'duration'.
		if ($eventOptions['allday']) {
			$eventOptions['end']->modify('+1 day');
		}
		$eventOptions['duration'] = TimeInterval::createFromDateInterval($eventOptions['start']->diff($eventOptions['end']));
		unset($eventOptions['end']);

		// Unset all null values and all scalar date/time parameters.
		$scalars = [
			'year',
			'month',
			'day',
			'hour',
			'minute',
			'second',
			'start_datetime',
			'start_date',
			'start_time',
			'start_year',
			'start_month',
			'start_day',
			'start_hour',
			'start_minute',
			'start_second',
			'start_date_local',
			'start_date_orig',
			'start_time_local',
			'start_time_orig',
			'start_timestamp',
			'start_iso_gmdate',
			'end_datetime',
			'end_date',
			'end_time',
			'end_year',
			'end_month',
			'end_day',
			'end_hour',
			'end_minute',
			'end_second',
			'end_date_local',
			'end_date_orig',
			'end_time_local',
			'end_time_orig',
			'end_timestamp',
			'end_iso_gmdate',
			'timezone',
			'tz',
			'tzid',
			'tz_abbrev',
		];

		foreach($eventOptions as $key => $value) {
			if (is_null($value) || in_array($key, $scalars)) {
				unset($eventOptions[$key]);
			}
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Some holidays have special values in the 'rrule' field in the database.
	 * This method detects them and does whatever special handling is necessary.
	 */
	protected function handleSpecialRRule($id, &$props): void
	{
		if (!isset($props['rrule'])) {
			return;
		}

		list($base, $modifier) = array_pad(preg_split('/(?=[+-]P\w+$)/', $props['rrule']), 2, '');

		// Do nothing with unrecognized ones.
		if (!isset(self::$special_rrules[$base])) {
			return;
		}

		// Record what the special RRule value is.
		$this->special_rrule = compact('base', 'modifier');

		// Special RRules get special handling in the rrule_presets menu in the UI.
		if (empty(self::$special_rrules[$base]['group'])) {
			self::$special_rrules[$base]['group'] = [$base];
		}

		foreach (self::$special_rrules[$base]['group'] as $special_rrule) {
			$props['rrule_presets'][Lang::$txt['calendar_repeat_special']][$special_rrule] = Lang::$txt['calendar_repeat_rrule_presets'][self::$special_rrules[$special_rrule]['txt_key']] ?? Lang::$txt[self::$special_rrules[$special_rrule]['txt_key']] ?? Lang::$txt['calendar_repeat_rrule_presets'][$special_rrule] ?? Lang::$txt[$special_rrule] ?? $special_rrule;
		}

		switch ($base) {
			case 'EASTER_W':
			case 'EASTER_E':
				// For Easter, we manually calculate the date for each visible year,
				// then save that date as an RDate, and then update the RRule to
				// pretend that it is otherwise a one day occurrence.
				$low = isset($props['view_start']) ? Time::createFromInterface($props['view_start']) : new Time('first day of this month, midnight');
				$high = $props['view_end'] ?? new Time('first day of next month, midnight');

				while ($low->format('Y') <= $high->format('Y')) {
					$rdate = new Time(implode('-', Holiday::easter((int) $low->format('Y'), $base === 'EASTER_E' ? 'Eastern' : 'Western')));

					if ($low->getTimestamp() >= $rdate->getTimestamp()) {
						$rdate = new Time(implode('-', Holiday::easter((int) $low->format('Y') + 1, $base === 'EASTER_E' ? 'Eastern' : 'Western')));
					}

					if (!empty($modifier)) {
						if (str_starts_with($modifier, '-')) {
							$rdate->sub(new \DateInterval(substr($modifier, 1)));
						} else {
							$rdate->add(new \DateInterval(substr($modifier, 1)));
						}
					}

					$props['rdates'][] = $rdate->format('Ymd');

					$low->modify('+1 year');
				}

				$props['rrule'] = 'FREQ=YEARLY;COUNT=1';
				break;

			default:
				// Allow mods to handle other holidays with complex rules.
				IntegrationHook::call('integrate_handle_special_rrule', [$id, &$props]);

				// Ensure the RRule is valid.
				if (!str_starts_with($props['rrule'], 'FREQ=')) {
					$props['rrule'] = 'FREQ=YEARLY;COUNT=1';
				}
				break;
		}
	}

	/**
	 * Sets $this->recurrence_iterator, but only if all necessary properties
	 * have been set.
	 *
	 * @return bool Whether the recurrence iterator was created successfully.
	 */
	protected function createRecurrenceIterator(): bool
	{
		static $args_hash;

		if (
			empty($this->rrule)
			|| !isset($this->start, $this->view_start, $this->view_end, $this->allday)
		) {
			return false;
		}

		$temp_hash = md5($this->rrule . $this->start->format('c') . $this->view_start->format('c') . $this->view_end->format('c') . (int) $this->allday);

		if (isset($this->recurrence_iterator, $args_hash) && $args_hash === $temp_hash) {
			return true;
		}

		$args_hash = $temp_hash;

		$this->recurrence_iterator = new RecurrenceIterator(
			new RRule($this->rrule),
			$this->start,
			$this->view_start->diff($this->view_end),
			$this->view_start,
			$this->allday ? RecurrenceIterator::TYPE_ALLDAY : RecurrenceIterator::TYPE_ABSOLUTE,
			$this->rdates ?? [],
			$this->exdates ?? [],
		);

		return true;
	}

	/**
	 * Creates an EventOccurrence object for the given start time.
	 *
	 * @param \DateTimeInterface $start The start time.
	 * @param ?TimeInterval $duration Custom duration for this occurrence. If
	 *    this is left null, the duration of the parent event will be used.
	 * @return EventOccurrence
	 */
	protected function createOccurrence(\DateTimeInterface $start, ?TimeInterval $duration = null): EventOccurrence
	{
		// Set up the basic properties for the occurrence.
		$props = [
			'start' => Time::createFromInterface($start),
		];

		if (isset($duration)) {
			$props['duration'] = $duration;
		}

		$props['id'] = $this->allday ? $props['start']->format('Ymd') : (clone $props['start'])->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');

		// Are their any adjustments to apply?
		foreach ($this->adjustments as $adjustment) {
			if ($adjustment->id > $props['id']) {
				break;
			}

			if (!$adjustment->affects_future && $adjustment->id < $props['id']) {
				continue;
			}

			$props['adjustment'] = $adjustment;
		}

		return new EventOccurrence($this->id, $props);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Generator that runs queries about event data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)'
	 *    Note that 'FROM {db_prefix}boards AS b' is always part of the query.
	 * @param array $where Zero or more conditions for the WHERE clause.
	 *    Conditions will be placed in parentheses and concatenated with AND.
	 *    If this is left empty, no WHERE clause will be used.
	 * @param array $order Zero or more conditions for the ORDER BY clause.
	 *    If this is left empty, no ORDER BY clause will be used.
	 * @param array $group Zero or more conditions for the GROUP BY clause.
	 *    If this is left empty, no GROUP BY clause will be used.
	 * @param int|string $limit Maximum number of results to retrieve.
	 *    If this is left empty, all results will be retrieved.
	 *
	 * @return \Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0): \Generator
	{
		$request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}calendar AS cal' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($group) ? '' : '
			GROUP BY ' . implode(', ', $group)) . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (!empty($limit) ? '
			LIMIT ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// First, clear out any null values.
			$row = array_diff($row, array_filter($row, 'is_null'));

			// Is this an all-day event?
			$row['allday'] = !isset($row['start_time']) || !isset($row['timezone']) || !in_array($row['timezone'], timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC));

			// Replace start time and date scalars with a Time object.
			$row['start'] = new Time($row['start_date'] . (!$row['allday'] ? ' ' . $row['start_time'] . ' ' . $row['timezone'] : ' ' . User::getTimezone()));
			unset($row['start_date'], $row['start_time'], $row['timezone']);

			// Replace duration string with a TimeInterval object.
			$row['duration'] = new TimeInterval($row['duration']);

			// end_date is only used for narrowing the query.
			unset($row['end_date']);

			// Are there any adjustments to the calculated recurrence dates?
			$row['rdates'] = explode(',', $row['rdates'] ?? '');
			$row['exdates'] = explode(',', $row['exdates'] ?? '');

			// The groups should be an array.
			$row['member_groups'] = isset($row['member_groups']) ? explode(',', $row['member_groups']) : [];

			yield $row;
		}
		Db::$db->free_result($request);
	}

	/**
	 * Set the RRule for a posted event for insertion into the database.
	 *
	 * @param array $eventOptions An array of optional time and date parameters
	 *    (span, start_year, end_month, etc., etc.)
	 */
	protected static function setRequestedRRule(array &$eventOptions): void
	{
		if (isset($_REQUEST['COUNT']) && (int) $_REQUEST['COUNT'] <= 1) {
			$_REQUEST['RRULE'] = 'never';
		}

		if (!empty($_REQUEST['RRULE']) && $_REQUEST['RRULE'] !== 'custom') {
			if ($_REQUEST['RRULE'] === 'never') {
				unset($_REQUEST['RRULE']);
				$eventOptions['rrule'] = 'FREQ=YEARLY;COUNT=1';

				return;
			}

			if (!empty($_REQUEST['UNTIL'])) {
				$_REQUEST['RRULE'] .= ';UNTIL=' . $_REQUEST['UNTIL'];
			} elseif (!empty($_REQUEST['COUNT'])) {
				$_REQUEST['RRULE'] .= ';COUNT=' . $_REQUEST['COUNT'];
			}

			try {
				$eventOptions['rrule'] = new RRule(Utils::htmlspecialchars($_REQUEST['RRULE']));

				if (
					$eventOptions['rrule']->freq === 'WEEKLY'
					|| !empty($eventOptions['rrule']->byday)
				) {
					$eventOptions['rrule']->wkst = RRule::WEEKDAYS[((Theme::$current->options['calendar_start_day'] ?? 0) + 6) % 7];
				}

				$eventOptions['rrule'] = (string) $eventOptions['rrule'];
			} catch (\Throwable $e) {
				unset($_REQUEST['RRULE']);
				$eventOptions['rrule'] = 'FREQ=YEARLY;COUNT=1';
			}
		} elseif (in_array($_REQUEST['FREQ'] ?? null, RRule::FREQUENCIES)) {
			$rrule = [];

			if (isset($_REQUEST['BYDAY_num'], $_REQUEST['BYDAY_name'])) {
				foreach ($_REQUEST['BYDAY_num'] as $key => $value) {
					// E.g. "second Tuesday" = "BYDAY=2TU"
					if (!str_contains($_REQUEST['BYDAY_name'][$key], ',')) {
						$_REQUEST['BYDAY'][$key] = ((int) $_REQUEST['BYDAY_num'][$key]) . $_REQUEST['BYDAY_name'][$key];
					}
					// E.g. "last weekday" = "BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1"
					else {
						$_REQUEST['BYDAY'] = [];
						$_REQUEST['BYDAY'][0] = $_REQUEST['BYDAY_name'][$key];
						$_REQUEST['BYSETPOS'] = $_REQUEST['BYDAY_num'][$key];
						break;
					}
				}

				$_REQUEST['BYDAY'] = implode(',', array_unique($_REQUEST['BYDAY']));
				unset($_REQUEST['BYDAY_num'], $_REQUEST['BYDAY_name']);
			}

			foreach (
				[
					'FREQ',
					'INTERVAL',
					'UNTIL',
					'COUNT',
					'BYMONTH',
					'BYWEEKNO',
					'BYYEARDAY',
					'BYMONTHDAY',
					'BYDAY',
					'BYHOUR',
					'BYMINUTE',
					'BYSECOND',
					'BYSETPOS',
				] as $part
			) {
				if (isset($_REQUEST[$part])) {
					if (is_array($_REQUEST[$part])) {
						$rrule[] = $part . '=' . Utils::htmlspecialchars(implode(',', $_REQUEST[$part]));
					} else {
						$rrule[] = $part . '=' . Utils::htmlspecialchars($_REQUEST[$part]);
					}
				}
			}

			$rrule = implode(';', $rrule);

			try {
				$eventOptions['rrule'] = new RRule(Utils::htmlspecialchars($rrule));

				if (
					$eventOptions['rrule']->freq === 'WEEKLY'
					|| !empty($eventOptions['rrule']->byday)
				) {
					$eventOptions['rrule']->wkst = RRule::WEEKDAYS[((Theme::$current->options['calendar_start_day'] ?? 0) + 6) % 7];
				}

				$eventOptions['rrule'] = (string) $eventOptions['rrule'];
			} catch (\Throwable $e) {
				$eventOptions['rrule'] = 'FREQ=YEARLY;COUNT=1';
			}

			unset($rrule);
		}
	}

	/**
	 * Set the RDates for a posted event for insertion into the database.
	 *
	 * @param Event $event An event that is being created or modified.
	 */
	protected static function setRequestedRDatesAndExDates(Event $event): void
	{
		// Clear out all existing RDates and ExDates.
		$rdates = $event->recurrence_iterator->getRDates();
		$exdates = $event->recurrence_iterator->getExDates();

		rsort($rdates);
		rsort($exdates);

		foreach ($rdates as $rdate) {
			$event->removeOccurrence(new \DateTimeImmutable($rdate));
		}

		foreach ($exdates as $exdate) {
			$event->addOccurrence(new \DateTimeImmutable($exdate));
		}

		// Events with special RRules can't have RDates or ExDates.
		if (!empty($event->special_rrule)) {
			return;
		}

		// Add all the RDates and ExDates.
		foreach (['RDATE', 'EXDATE'] as $date_type) {
			if (!isset($_REQUEST[$date_type . '_date'])) {
				continue;
			}

			foreach ($_REQUEST[$date_type . '_date'] as $key => $date) {
				if (empty($date)) {
					continue;
				}

				if (empty($event->allday) && isset($_REQUEST[$date_type . '_time'][$key])) {
					$date = new Time($date . 'T' . $_REQUEST[$date_type . '_time'][$key] . ' ' . $event->start->format('e'));
				} else {
					$date = new Time($date . ' ' . $event->start->format('e'));
				}

				$date->setTimezone(new \DateTimeZone('UTC'));

				if ($date_type === 'RDATE') {
					$event->addOccurrence($date);
				} else {
					$event->removeOccurrence($date);
				}
			}
		}
	}

	/**
	 * Standardizes various forms of input about start and end times.
	 *
	 * The $input array can include any combination of the following keys:
	 * allday, timezone, year, month, day, hour, minute, second, start_datetime,
	 * start_date, start_time, start_year, start_month, start_day, start_hour,
	 * start_minute, start_second, end_datetime, end_date, end_time, end_year,
	 * end_month, end_day, end_hour, end_minute, end_second.
	 *
	 * The returned array will replace all of those with the following:
	 * start_date, start_time, end_date, end_time, timezone, allday.
	 *
	 * If $input contains keys besides the ones mentioned above, those key-value
	 * pairs will remain unchanged in the returned array.
	 *
	 * @param array $input Array of info about event start and end times.
	 * @return array Standardized version of $input array.
	 */
	protected static function standardizeEventOptions(array $input): array
	{
		foreach (['year', 'month', 'day', 'hour', 'minute', 'second'] as $key) {
			if (isset($input[$key])) {
				$input['start_' . $key] = $input[$key];
				unset($input[$key]);
			}
		}

		$input['allday'] = !empty($input['allday']);

		if (!isset($input['timezone']) || ($tz = @timezone_open($input['timezone'])) === false) {
			$tz = timezone_open(User::getTimezone());
		}

		$input['timezone'] = $tz->getName();

		foreach (['start', 'end'] as $prefix) {
			// Input might come as individual parameters...
			$year = $input[$prefix . '_year'] ?? null;
			$month = $input[$prefix . '_month'] ?? null;
			$day = $input[$prefix . '_day'] ?? null;
			$hour = $input[$prefix . '_hour'] ?? null;
			$minute = $input[$prefix . '_minute'] ?? null;
			$second = $input[$prefix . '_second'] ?? null;

			// ... or as datetime strings ...
			$datetime_string = $input[$prefix . '_datetime'] ?? null;

			// ... or as date strings and time strings.
			$date_string = $input[$prefix . '_date'] ?? null;
			$time_string = $input[$prefix . '_time'] ?? null;

			// If the date and time were given in individual parameters, combine them.
			if (empty($time_string) && isset($hour, $minute, $second)) {
				$time_string = implode(':', [(int) $hour, (int) $minute, (int) $second]);
			}

			if (empty($date_string) && isset($year, $month, $day)) {
				$date_string = implode('-', [(int) $year, (int) $month, (int) $day]);
			}

			// If the date and time were given in separate strings, combine them.
			if (empty($datetime_string) && isset($date_string)) {
				$datetime_string = $date_string . (isset($time_string) ? ' ' . $time_string : '');
			}

			// If string input was given, override individually defined options with it.
			if (isset($datetime_string)) {
				$datetime_string_parsed = date_parse(str_replace(',', '', Calendar::convertDateToEnglish($datetime_string)));

				if (is_array($datetime_string_parsed) && empty($datetime_string_parsed['error_count']) && empty($datetime_string_parsed['warning_count'])) {
					$datetime_string_parsed = array_filter(
						$datetime_string_parsed,
						function ($key) {
							return in_array($key, ['year', 'month', 'day', 'hour', 'minute', 'second']);
						},
						ARRAY_FILTER_USE_KEY,
					);

					$datetime_string_parsed = array_filter($datetime_string_parsed, 'is_int');

					extract($datetime_string_parsed);
				} else {
					unset($year, $month, $day, $hour, $minute, $second);
				}
			}
			// Individually defined options only, so ensure they are all integers.
			else {
				foreach (['year', 'month', 'day', 'hour', 'minute', 'second'] as $var) {
					if (isset($$var)) {
						$$var = (int) $$var;
					}
				}
			}

			// Validate input.
			$date_is_valid = isset($month, $day, $year) && (implode('-', [$month, $day, $year]) === implode('-', array_map('intval', [$month, $day, $year]))) && checkdate($month, $day, $year);

			$time_is_valid = isset($hour, $minute, $second) && $hour >= 0 && $hour < 25 && $minute >= 0 && $minute < 60 && $second >= 0 && $second < 60;

			// Replace whatever was supplied with our validated strings.
			foreach (['year', 'month', 'day', 'hour', 'minute', 'second', 'date', 'time', 'datetime'] as $var) {
				unset($input[$prefix . '_' . $var]);
			}

			if ($date_is_valid) {
				$input[$prefix . '_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
			}

			if ($time_is_valid && !$input['allday']) {
				$input[$prefix . '_time'] = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
			}
		}

		// Uh-oh...
		if (!isset($input['start_date'])) {
			ErrorHandler::fatalLang('invalid_date', false);
		}

		// Make sure we use valid values for everything
		if ($input['allday'] || !isset($input['start_time'])) {
			$input['allday'] = true;
			$input['start_time'] = '00:00:00';
		}

		if (!isset($input['end_date'])) {
			if (isset($input['span'])) {
				$start = new \DateTimeImmutable($input['start_date'] . (empty($input['allday']) ? ' ' . $input['start_time'] . ' ' . $input['timezone'] : ''));

				$end = $start->modify('+' . max(0, (int) ($input['span'] - 1)) . ' days');

				$input['end_date'] = $end->format('Y-m-d');

				if (!$input['allday']) {
					$input['end_time'] = $end->format('H:i:s');
				}
			} else {
				$input['end_date'] = $input['start_date'];
			}
		}

		if ($input['allday'] || !isset($input['end_time'])) {
			$input['allday'] = true;
			$input['end_time'] = $input['start_time'];
		}

		return $input;
	}
}

?>