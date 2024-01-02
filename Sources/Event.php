<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Actions\Calendar;
use SMF\Db\DatabaseApi as Db;

/**
 * Represents a calendar event.
 *
 * @todo Implement recurring events.
 * @todo Use this class to represent holidays and birthdays. They're all-day
 * events, after all.
 */
class Event implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'create' => 'insertEvent',
			'modify' => 'modifyEvent',
			'remove' => 'removeEvent',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	public const TYPE_EVENT_SIMPLE = 0;
	public const TYPE_EVENT_RECURRING = 1; // Not yet implemented.
	public const TYPE_BIRTHDAY = 2; // Not yet implemented.
	public const TYPE_HOLIDAY = 4; // Not yet implemented.

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
	 * @var int
	 *
	 * This event's type.
	 * Value must be one of this class's TYPE_* constants.
	 */
	public int $type = self::TYPE_EVENT_SIMPLE;

	/**
	 * @var SMF\Time
	 *
	 * An SMF\Time object representing the start of the event.
	 */
	public object $start;

	/**
	 * @var SMF\Time
	 *
	 * An SMF\Time object representing the end of the event.
	 */
	public object $end;

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

	/**************************
	 * Public static properties
	 **************************/

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

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var bool
	 *
	 * If true, Event::get() will not destroy instances after yielding them.
	 * This is used internally by Event::load().
	 */
	protected static bool $keep_all = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the event.
	 * @param array $props Properties to set for this event.
	 * @return object An instance of this class.
	 */
	public function __construct(int $id = 0, array $props = [])
	{
		// Preparing default data to show in the calendar posting form.
		if ($id < 0) {
			$this->id = $id;
			$props['start_timestamp'] = time();
			$props['end_timestamp'] = time() + 3600;
			$props['timezone'] = User::getTimezone();
			$props['member'] = User::$me->id;
			$props['name'] = User::$me->name;
		}
		// Creating a new event.
		elseif ($id == 0) {
			$props['member'] = $props['member'] ?? User::$me->id;
			$props['name'] = $props['name'] ?? User::$me->name;
		}
		// Loading an existing event.
		else {
			$this->id = $id;
			self::$loaded[$this->id] = $this;
		}

		$this->set($props);

		// This shouldn't happen, but just in case...
		if (!isset($this->start)) {
			$this->start = new Time('today');
			$this->end = new Time('today');
			$this->allday = true;
		}
	}

	/**
	 * Saves this event to the database.
	 */
	public function save(): void
	{
		$is_edit = !empty($this->id);

		// Saving a new event.
		if (empty($this->id)) {
			$columns = [
				'start_date' => 'date',
				'end_date' => 'date',
				'id_board' => 'int',
				'id_topic' => 'int',
				'title' => 'string-60',
				'id_member' => 'int',
				'location' => 'string-255',
			];

			$params = [
				$this->start->format('Y-m-d'),
				$this->end->format('Y-m-d'),
				$this->board,
				$this->topic,
				$this->title,
				$this->member,
				$this->location,
			];

			if (!$this->allday) {
				$columns['start_time'] = 'time';
				$params[] = $this->start->format('H:i:s');

				$columns['end_time'] = 'time';
				$params[] = $this->end->format('H:i:s');

				$columns['timezone'] = 'string';
				$params[] = $this->start->format('e');
			}

			IntegrationHook::call('integrate_create_event', [(array) $this, &$columns, &$params]);

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
				'title = SUBSTRING({string:title}, 1, 60)',
				'id_board = {int:id_board}',
				'id_topic = {int:id_topic}',
				'location = SUBSTRING({string:location}, 1, 255)',
			];

			$params = [
				'id' => $this->id,
				'start_date' => $this->start->format('Y-m-d'),
				'end_date' => $this->end->format('Y-m-d'),
				'title' => $this->title,
				'location' => $this->location,
				'id_board' => $this->board,
				'id_topic' => $this->topic,
			];

			if ($this->allday) {
				$set[] = 'start_time = NULL';
				$set[] = 'end_time = NULL';
				$set[] = 'timezone = NULL';
			} else {
				$set[] = 'start_time = {time:start_time}';
				$params['start_time'] = $this->start->format('H:i:s');

				$set[] = 'end_time = {time:end_time}';
				$params['end_time'] = $this->end->format('H:i:s');

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
	 *
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
		$filecontents[] = 'DTSTART' . (!$this->allday ? ';TZID=' . $this->tz : ';VALUE=DATE') . ':' . $this->start->format('Ymd' . ($this->allday ? '' : '\\THis'));

		// Event has a duration/
		if (
			(!$this->allday && $this->start_iso_gmdate != $this->end_iso_gmdate)
			|| ($this->allday && $this->start_date != $this->end_date)
		) {
			$filecontents[] = 'DTEND' . (!$this->allday ? ';TZID=' . $this->tz : ';VALUE=DATE') . ':' . $this->end->format('Ymd' . ($this->allday ? '' : '\\THis'));
		}

		// Event has changed? Advance the sequence for this UID.
		if ($this->sequence > 0) {
			$filecontents[] = 'SEQUENCE:' . $this->sequence;
		}

		if (!empty($this->location)) {
			$filecontents[] = 'LOCATION:' . str_replace(',', '\\,', $this->location);
		}

		$filecontents[] = 'SUMMARY:' . implode('', $title);
		$filecontents[] = 'UID:' . $this->id . '@' . str_replace(' ', '-', Config::$mbname);
		$filecontents[] = 'END:VEVENT';

		return implode("\n", $filecontents);
	}

	/**
	 *
	 */
	public function fixTimezone(): void
	{
		$all_timezones = Utils::$context['all_timezones'] ?? TimeZone::list($this->start_date);

		if (!isset($all_timezones[$this->timezone])) {
			$later = strtotime('@' . $this->start_timestamp . ' + 1 year');
			$tzinfo = timezone_transitions_get(timezone_open($this->timezone), $this->start_timestamp, $later);

			$found = false;

			foreach ($all_timezones as $possible_tzid => $dummy) {
				// Ignore the "-----" option
				if (empty($possible_tzid)) {
					continue;
				}

				$possible_tzinfo = timezone_transitions_get(timezone_open($possible_tzid), $this->start_timestamp, $later);

				if ($tzinfo === $possible_tzinfo) {
					$this->timezone = $possible_tzid;
					$found = true;
					break;
				}
			}

			// Hm. That's weird. Well, just prepend it to the list and let the user deal with it.
			if (!$found) {
				$all_timezones = [$this->timezone => '[UTC' . $this->start->format('P') . '] - ' . $this->timezone] + $all_timezones;
			}
		}
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		if (property_exists($this, $prop)) {
			$this->{$prop} = $value;
		} elseif (array_key_exists($prop, $this->prop_aliases)) {
			// Can't unset a virtual property.
			if (is_null($value)) {
				return;
			}

			$real_prop = $this->prop_aliases[$prop];

			if (strpos($real_prop, '!') === 0) {
				$real_prop = ltrim($real_prop, '!');
				$value = !$value;
			}

			if (strpos($real_prop, '[') !== false) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				$this->{$real_prop[0]}[$real_prop[1]] = $value;
			} else {
				$this->{$real_prop} = $value;
			}
		} else {
			// For simplicity's sake...
			if (in_array($prop, ['year', 'month', 'day', 'hour', 'minute', 'second'])) {
				$prop = 'start_' . $prop;
			}

			if (($start_end = substr($prop, 0, (int) strpos($prop, '_'))) !== 'end') {
				$start_end = 'start';
			}

			if (!isset($this->{$start_end})) {
				$this->{$start_end} = new Time();
			}

			switch ($prop) {
				case 'start_datetime':
				case 'end_datetime':
					$this->{$start_end}->datetime = $value;
					break;

				case 'start_date':
				case 'end_date':
					$this->{$start_end}->date = $value;
					break;

				case 'start_time':
				case 'end_time':
					$this->{$start_end}->time = $value;
					break;

				case 'start_date_orig':
				case 'end_date_orig':
					$this->{$start_end}->date_orig = $value;
					break;

				case 'start_time_orig':
				case 'end_time_orig':
					$this->{$start_end}->time_orig = $value;
					break;

				case 'start_date_local':
				case 'end_date_local':
					$this->{$start_end}->date_local = $value;
					break;

				case 'start_time_local':
				case 'end_time_local':
					$this->{$start_end}->time_local = $value;
					break;

				case 'start_year':
				case 'end_year':
					$this->{$start_end}->year = $value;
					break;

				case 'start_month':
				case 'end_month':
					$this->{$start_end}->month = $value;
					break;

				case 'start_day':
				case 'end_day':
					$this->{$start_end}->day = $value;
					break;

				case 'start_hour':
				case 'end_hour':
					$this->{$start_end}->hour = $value;
					break;

				case 'start_minute':
				case 'end_minute':
					$this->{$start_end}->minute = $value;
					break;

				case 'start_second':
				case 'end_second':
					$this->{$start_end}->second = $value;
					// no break

				case 'start_timestamp':
				case 'end_timestamp':
					$this->{$start_end}->timestamp = $value;
					break;

				case 'start_iso_gmdate':
				case 'end_iso_gmdate':
					$this->{$start_end}->iso_gmdate = $value;
					break;

				case 'tz':
				case 'tzid':
				case 'timezone':
					if ($value instanceof \DateTimeZone) {
						$this->start->setTimezone($value);
						$this->end->setTimezone($value);
					} else {
						$this->start->timezone = $value;
						$this->end->timezone = $value;
					}

					break;

				case 'span':
				case 'num_days':
					$value = $this->setNumDays($value);
					break;

				// These computed properties are read-only.
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
					$this->custom[$prop] = $value;
					break;
			}
		}

		// Ensure that the dates still make sense with each other.
		if (isset($this->start, $this->end)) {
			self::fixEndDate($this->start, $this->end);
		}
	}

	/**
	 * Gets custom property values.
	 *
	 * @param string $prop The property name.
	 */
	public function __get(string $prop): mixed
	{
		if (property_exists($this, $prop)) {
			return $this->{$prop} ?? null;
		}

		if (array_key_exists($prop, $this->prop_aliases)) {
			$real_prop = $this->prop_aliases[$prop];

			if (($not = strpos($real_prop, '!') === 0)) {
				$real_prop = ltrim($real_prop, '!');
			}

			if (strpos($real_prop, '[') !== false) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				$value = $this->{$real_prop[0]}[$real_prop[1]];
			} else {
				$value = $this->{$real_prop};
			}

			return $not ? !$value : $value;
		}

		if (in_array($prop, ['year', 'month', 'day', 'hour', 'minute', 'second'])) {
			$prop = 'start_' . $prop;
		}

		if (($start_end = substr($prop, 0, (int) strpos($prop, '_'))) !== 'end') {
			$start_end = 'start';
		}

		switch ($prop) {
			case 'start_datetime':
			case 'end_datetime':
				$value = $this->{$start_end}->datetime;
				break;

			case 'start_date':
			case 'end_date':
				$value = $this->{$start_end}->date;
				break;

			case 'start_date_local':
			case 'end_date_local':
				$value = $this->{$start_end}->date_local;
				break;

			case 'start_date_orig':
			case 'end_date_orig':
				$value = $this->{$start_end}->date_orig;
				break;

			case 'start_time':
			case 'end_time':
				$value = $this->{$start_end}->time;
				break;

			case 'start_time_local':
			case 'end_time_local':
				$value = $this->{$start_end}->time_local;
				break;

			case 'start_time_orig':
			case 'end_time_orig':
				$value = $this->{$start_end}->time_orig;
				break;

			case 'start_year':
			case 'end_year':
				$value = $this->{$start_end}->format('Y');
				break;

			case 'start_month':
			case 'end_month':
				$value = $this->{$start_end}->format('m');
				break;

			case 'start_day':
			case 'end_day':
				$value = $this->{$start_end}->format('d');
				break;

			case 'start_hour':
			case 'end_hour':
				$value = $this->{$start_end}->format('H');
				break;

			case 'start_minute':
			case 'end_minute':
				$value = $this->{$start_end}->format('i');
				break;

			case 'start_second':
			case 'end_second':
				$value = $this->{$start_end}->format('s');
				break;

			case 'start_timestamp':
			case 'end_timestamp':
				$value = $this->{$start_end}->getTimestamp() - ($this->allday ? $this->{$start_end}->getTimestamp() % 86400 : 0);
				break;

			case 'start_iso_gmdate':
			case 'end_iso_gmdate':
				$value = $this->allday ? preg_replace('/T\d\d:\d\d:\d\d/', 'T00:00:00', $this->{$start_end}->iso_gmdate) : $this->{$start_end}->iso_gmdate;
				break;

			case 'tz':
			case 'tzid':
			case 'timezone':
				$value = $this->start->timezone;
				break;

			case 'tz_abbrev':
				$value = $this->start->tz_abbrev;
				break;

			case 'span':
			case 'num_days':
				$value = $this->getNumDays();
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

			default:
				$value = $this->custom[$prop] ?? null;
				break;
		}

		return $value;
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param string $prop The property name.
	 */
	public function __isset(string $prop): bool
	{
		if (property_exists($this, $prop)) {
			return isset($this->{$prop});
		}

		if (array_key_exists($prop, $this->prop_aliases)) {
			$real_prop = ltrim($this->prop_aliases[$prop], '!');

			if (strpos($real_prop, '[') !== false) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				return isset($this->{$real_prop[0]}[$real_prop[1]]);
			}

			return isset($this->{$real_prop});
		}

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
				return property_exists($this, 'start');

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
				return property_exists($this, 'end');

			case 'span':
			case 'num_days':
				return property_exists($this, 'start') && property_exists($this, 'end');

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
				return isset($this->custom[$prop]);
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads events by ID number or by topic.
	 *
	 * @param int|array $id ID number of the event or topic.
	 * @param bool $is_topic If true, $id is the topic ID. Default: false.
	 * @param bool $use_permissions Whether to use permissions. Default: true.
	 * @return array Instances of this class for the loaded events.
	 */
	public static function load(int $id, bool $is_topic = false, bool $use_permissions = true): array
	{
		if ($id <= 0) {
			return $is_topic ? false : new self($id);
		}

		$loaded = [];

		self::$keep_all = true;

		$query_customizations['where'][] = 'cal.id_' . ($is_topic ? 'topic' : 'event') . ' = {int:id}';
		$query_customizations['limit'] = 1;
		$query_customizations['params']['id'] = $id;

		if ($use_permissions) {
			$query_customizations['where'][] = '(cal.id_board = {int:no_board_link} OR {query_wanna_see_board})';

			$query_customizations['params']['no_board_link'] = 0;
		}

		foreach (self::get('', '', $use_permissions, $query_customizations) as $event) {
			$loaded[] = $event;
		}

		self::$keep_all = false;

		return $loaded;
	}

	/**
	 * Loads events within the given date range.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $use_permissions Whether to use permissions. Default: true.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return array Instances of this class for the loaded events.
	 */
	public static function loadRange(string $low_date, string $high_date, bool $use_permissions = true, array $query_customizations = []): array
	{
		$loaded = [];

		self::$keep_all = true;

		foreach (self::get($low_date, $high_date, $use_permissions, $query_customizations) as $event) {
			$loaded[$event->id] = $event;
		}

		self::$keep_all = false;

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Generator that yields instances of this class.
	 *
	 * @todo SMF does not yet take advantage of this generator very well.
	 * Instead of loading all the events in the range via Event::loadRange(),
	 * it would be better to call Event::get() directly in order to reduce
	 * memory load.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $use_permissions Whether to use permissions. Default: true.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<object> Iterating over result gives Event instances.
	 */
	public static function get(string $low_date, string $high_date, bool $use_permissions = true, array $query_customizations = [])
	{
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
		];
		$order = $query_customizations['order'] ?? [];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [
			'high_date' => $high_date,
			'low_date' => $low_date,
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

			yield (new self($id, $row));

			if (!self::$keep_all) {
				unset(self::$loaded[$id]);
			}
		}
	}

	/**
	 * Creates a event and saves it to the database.
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
		self::setStartEnd($eventOptions);

		$event = new self(0, $eventOptions);
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
		// Sanitize the title and location.
		foreach (['title', 'location'] as $key) {
			$eventOptions[$key] = Utils::htmlspecialchars($eventOptions[$key] ?? '', ENT_QUOTES);
		}

		// Set the new start and end dates.
		self::setStartEnd($eventOptions);

		list($event) = self::load($id);
		$event->set($eventOptions);
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the number of days across which this event occurs.
	 *
	 * For example, if the event starts and ends on the same day, span is 1.
	 * If the event starts Monday night and ends Wednesday morning, span is 3.
	 *
	 * @return int Number of days that this event spans, or 0 on error.
	 */
	protected function getNumDays(): int
	{
		if (!($this->start instanceof \DateTimeInterface) || !($this->end instanceof \DateTimeInterface) || $this->end->getTimestamp() < $this->start->getTimestamp()) {
			return 0;
		}

		return date_interval_format(date_diff($this->start, $this->end), '%a') + ($this->end->format('H') < $this->start->format('H') ? 2 : 1);
	}

	/**
	 * Adjusts the end date so that the number of days across which this event
	 * occurs will be $num_days.
	 *
	 * @param int $num_days The target number of days the event should span.
	 *    If $num_days is set to a value less than 1, no change will be made.
	 *    This method imposes no upper limit on $num_days, but if $num_days
	 *    exceeds the value of Config::$modSettings['cal_maxspan'], other parts
	 *    of this class will impose that limit.
	 */
	protected function setNumDays(int $num_days): void
	{
		if (!($this->start instanceof \DateTimeInterface) || !($this->end instanceof \DateTimeInterface) || $num_days < 1) {
			return;
		}

		$current_span = $this->getNumDays();

		if ($current_span == $num_days) {
			return;
		}

		$this->end->modify($current_span < $num_days ? '+' : '-' . ($num_days - $current_span) . ' days');
	}

	/**
	 * Ensures that the start and end dates have a sane relationship.
	 */
	protected function fixEndDate(): void
	{
		// Must always use the same time zone for both dates.
		if ($this->end->format('e') !== $this->start->format('e')) {
			$this->end->setTimezone($this->start->getTimezone());
		}

		// End date can't be before the start date.
		if ($this->end->getTimestamp() < $this->start->getTimestamp()) {
			$this->end->setTimestamp($this->start->getTimestamp());
		}

		// If the event is too long, cap it at the max.
		if (!empty(Config::$modSettings['cal_maxspan']) && $this->getNumDays() > Config::$modSettings['cal_maxspan']) {
			$this->setNumDays(Config::$modSettings['cal_maxspan']);
		}
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
	 * @return Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0)
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
			$row['allday'] = !isset($row['start_time']) || !isset($row['end_time']) || !isset($row['timezone']) || !in_array($row['timezone'], timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC));

			// Replace time and date scalars with Time objects.
			$row['start'] = new Time($row['start_date'] . (!$row['allday'] ? ' ' . $row['start_time'] . ' ' . $row['timezone'] : ''));

			$row['end'] = new Time($row['end_date'] . (!$row['allday'] ? ' ' . $row['end_time'] . ' ' . $row['timezone'] : ''));

			unset($row['start_date'], $row['start_time'], $row['end_date'], $row['end_time'], $row['timezone']);

			// The groups should be an array.
			$row['member_groups'] = isset($row['member_groups']) ? explode(',', $row['member_groups']) : [];

			yield $row;
		}
		Db::$db->free_result($request);
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
	protected static function setStartEnd(array &$eventOptions): void
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
	protected static function standardizeEventOptions($input): array
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

		foreach (['start', 'end'] as $var) {
			// Input might come as individual parameters...
			$year = $input[$var . '_year'] ?? null;
			$month = $input[$var . '_month'] ?? null;
			$day = $input[$var . '_day'] ?? null;
			$hour = $input[$var . '_hour'] ?? null;
			$minute = $input[$var . '_minute'] ?? null;
			$second = $input[$var . '_second'] ?? null;

			// ... or as datetime strings ...
			$datetime_string = $input[$var . '_datetime'] ?? null;

			// ... or as date strings and time strings.
			$date_string = $input[$var . '_date'] ?? null;
			$time_string = $input[$var . '_time'] ?? null;

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
			foreach (['year', 'month', 'day', 'hour', 'minute', 'second', 'date', 'time', 'datetime'] as $key) {
				unset($input[$var . '_' . $key]);
			}

			if ($date_is_valid) {
				$input[$var . '_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
			}

			if ($time_is_valid && !$input['allday']) {
				$input[$var . '_time'] = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
			}
		}

		// Uh-oh...
		if (!isset($input['start_date'])) {
			ErrorHandler::fatalLang('invalid_date', false);
		}

		// Make sure we use valid values for everything
		if (!isset($input['end_date'])) {
			$input['end_date'] = $input['start_date'];
		}

		if ($input['allday'] || !isset($input['start_time'])) {
			$input['allday'] = true;
			$input['start_time'] = '00:00:00';
		}

		if ($input['allday'] || !isset($input['end_time'])) {
			$input['allday'] = true;
			$input['end_time'] = $input['start_time'];
		}

		return $input;
	}
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Event::exportStatic')) {
	Event::exportStatic();
}

?>