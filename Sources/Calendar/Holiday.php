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

use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\Utils;

/**
 * Represents a holiday.
 */
class Holiday extends Event
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This event's type.
	 * Value must be one of the parent class's TYPE_* constants.
	 */
	public int $type = self::TYPE_HOLIDAY;

	/**
	 * @var bool
	 *
	 * Whether this holiday is enabled.
	 */
	public bool $enabled = true;

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
		$props['type'] = self::TYPE_HOLIDAY;

		parent::__construct($id, $props);

		// For new events, provide all known special RRule options.
		if ($id <= 0) {
			foreach (self::$special_rrules as $special_rrule => $info) {
				$this->rrule_presets[Lang::$txt['calendar_repeat_special']][$special_rrule] = Lang::$txt['calendar_repeat_rrule_presets'][$info['txt_key']] ?? Lang::$txt[$info['txt_key']] ?? Lang::$txt['calendar_repeat_rrule_presets'][$special_rrule] ?? Lang::$txt[$special_rrule] ?? $special_rrule;
			}

			Theme::addJavaScriptVar('special_rrules', array_keys(self::$special_rrules), true);
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
		parent::__set($prop, $value);
	}

	/**
	 * Gets custom property values.
	 *
	 * @param string $prop The property name.
	 */
	public function __get(string $prop): mixed
	{
		return parent::__get($prop);
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param string $prop The property name.
	 */
	public function __isset(string $prop): bool
	{
		return parent::__isset($prop);
	}

	/**
	 * Unsets custom properties.
	 *
	 * @param string $prop The property name.
	 */
	public function __unset(string $prop): void
	{
		parent::__unset($prop);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads holidays by ID number.
	 *
	 * @param int|array $id ID number of the holiday event.
	 * @param bool $is_topic Ignored.
	 * @param bool $use_permissions Ignored.
	 * @return array Instances of this class for the loaded events.
	 */
	public static function load(int $id, bool $is_topic = false, bool $use_permissions = true): array
	{
		if ($id <= 0) {
			return [];
		}

		if (isset(self::$loaded[$id])) {
			return [self::$loaded[$id]];
		}

		$loaded = [];

		$selects = [
			'cal.*',
		];
		$where = ['cal.id_event = {int:id}'];
		$params = [
			'id' => $id,
		];

		foreach(self::queryData($selects, $params, [], $where) as $row) {
			$id = (int) $row['id_event'];
			$row['use_permissions'] = false;

			if (!empty($row['rdates'])) {
				$max_rdate = max($row['rdates']);
				$row['view_end'] = (new \DateTimeImmutable(substr($max_rdate, 0, strcspn($max_rdate, '/'))))->modify('+1 day');
			}

			$loaded[] = new self($id, $row);
		}

		return $loaded;
	}

	/**
	 * Generator that yields instances of this class.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $only_enabled If true, only show enabled holidays.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<Holiday> Iterating over result gives Holiday instances.
	 */
	public static function get(string $low_date, string $high_date, bool $only_enabled = true, array $query_customizations = []): \Generator
	{
		$low_date = new \DateTimeImmutable(!empty($low_date) ? $low_date : 'first day of this month, midnight');
		$high_date = new \DateTimeImmutable(!empty($high_date) ? $high_date : 'first day of next month, midnight');

		$selects = $query_customizations['selects'] ?? [
			'cal.*',
		];
		$joins = $query_customizations['joins'] ?? [];
		$where = $query_customizations['where'] ?? [
			'type = {int:type}',
		];
		$order = $query_customizations['order'] ?? ['cal.id_event'];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [
			'type' => self::TYPE_HOLIDAY,
		];

		if ($only_enabled) {
			$where[] = 'enabled = {int:enabled}';
			$params['enabled'] = 1;
		}

		// Filter by month (if we are showing 11 or fewer months).
		if ($high_date->diff($low_date)->days < 335) {
			$months = [];

			$temp = \DateTime::createFromImmutable($low_date);

			while ($temp->format('Ym') <= $high_date->format('Ym')) {
				$months[] = (int) $temp->format('m');
				$temp->modify('+1 month');
			}

			$months = array_unique($months);

			switch (Db::$db->title) {
				case POSTGRE_TITLE:
					$where[] = 'EXTRACT(MONTH FROM cal.start_date) IN ({array_int:months}) OR cal.rrule ~ {string:bymonth_regex}' . (array_intersect([3, 4], $months) !== [] ? ' OR cal.rrule IN ({array_string:easter})' : '');
					break;

				default:
					$where[] = 'MONTH(cal.start_date) IN ({array_int:months}) OR cal.rrule REGEXP {string:bymonth_regex}' . (array_intersect([3, 4], $months) !== [] ? ' OR cal.rrule IN ({array_string:easter})' : '');
					break;
			}

			$params['months'] = $months;
			$params['bymonth_regex'] = 'BYMONTH=(?:(?:' . implode('|', array_diff(range(1, 12), $months)) . '),)*(' . implode('|', $months) . ')';
			$params['easter'] = ['EASTER_W', 'EASTER_E'];
		}

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$id = (int) $row['id_event'];
			$row['use_permissions'] = false;

			$row['view_start'] = $low_date;
			$row['view_end'] = $high_date;

			yield (new self($id, $row));
		}
	}

	/**
	 * Returns Holiday instances for the holidays recorded in the database.
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of Holiday instances.
	 */
	public static function list(int $start, int $items_per_page, string $sort = 'cal.title ASC, cal.start_date ASC'): array
	{
		$loaded = [];

		$selects = ['cal.*'];
		$joins = [];
		$where = ['type = {int:type}'];
		$order = [$sort];
		$group = [];
		$limit = implode(', ', [$start, $items_per_page]);
		$params = ['type' => self::TYPE_HOLIDAY];

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$id = (int) $row['id_event'];
			$row['use_permissions'] = false;

			if (!empty($row['rdates'])) {
				$max_rdate = max($row['rdates']);
				$row['view_end'] = (new \DateTimeImmutable(substr($max_rdate, 0, strcspn($max_rdate, '/'))))->modify('+1 day');
			}

			$loaded[] = new self($id, $row);
		}

		return $loaded;
	}

	/**
	 * Gets the total number of holidays recorded in the database.
	 *
	 * @return int The total number of known holidays.
	 */
	public static function count(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}calendar
			WHERE type = {int:type}',
			[
				'type' => self::TYPE_HOLIDAY,
			],
		);
		list($num_items) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $num_items;
	}

	/**
	 * Gets events within the given date range, and returns a generator that
	 * yields all occurrences of those events within that range.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $only_enabled If true, only show enabled holidays.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<EventOccurrence> Iterating over result gives
	 *    EventOccurrence instances.
	 */
	public static function getOccurrencesInRange(string $low_date, string $high_date, bool $only_enabled = true, array $query_customizations = []): \Generator
	{
		foreach (self::get($low_date, $high_date, $only_enabled, $query_customizations) as $event) {
			foreach ($event->getAllVisibleOccurrences() as $occurrence) {
				yield $occurrence;
			}
		}
	}

	/**
	 * Creates a holiday and saves it to the database.
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

		$eventOptions['type'] = self::TYPE_HOLIDAY;

		// Set the start and end dates.
		self::setRequestedStartAndDuration($eventOptions);

		$eventOptions['view_start'] = isset($eventOptions['start']) ? \DateTimeImmutable::createFromInterface($eventOptions['start']) : new \DateTimeImmutable('now');
		$eventOptions['view_end'] = $eventOptions['view_start']->add(new \DateInterval('P1D'));

		self::setRequestedRRule($eventOptions);

		$event = new self(0, $eventOptions);

		self::setRequestedRDatesAndExDates($event);

		$event->save();
	}

	/**
	 * Modifies a holiday.
	 *
	 * Does not check permissions.
	 *
	 * @param int $id The ID of the event.
	 * @param array $eventOptions An array of event information.
	 */
	public static function modify(int $id, array &$eventOptions): void
	{
		list($event) = self::load($id);

		// Double check that the loaded event is in fact a holiday.
		if (!$event->type === self::TYPE_HOLIDAY) {
			return;
		}

		// Sanitize the title and location.
		foreach (['title', 'location'] as $prop) {
			$eventOptions[$prop] = Utils::htmlspecialchars($eventOptions[$prop] ?? '', ENT_QUOTES);
		}

		foreach (['allday', 'start_date', 'end_date', 'start_time', 'end_time', 'timezone'] as $prop) {
			$eventOptions[$prop] = $eventOptions[$prop] ?? $_POST[$prop] ?? $event->{$prop} ?? null;
		}

		// Set the new start date and duration.
		self::setRequestedStartAndDuration($eventOptions);

		$eventOptions['view_start'] = isset($eventOptions['start']) ? \DateTimeImmutable::createFromInterface($eventOptions['start']) : new \DateTimeImmutable('now');
		$eventOptions['view_end'] = $eventOptions['view_start']->add(new \DateInterval('P1D'));

		if (!empty($event->special_rrule)) {
			unset(
				$eventOptions['start_date'],
				$eventOptions['end_date'],
				$eventOptions['start_time'],
				$eventOptions['timezone'],
				$eventOptions['duration'],
				$eventOptions['rrule'],
				$eventOptions['rdates'],
				$eventOptions['exdates'],
			);

			if (isset(self::$special_rrules[$_POST['RRULE']])) {
				$event->special_rrule = $_POST['RRULE'];

				if (in_array($event->special_rrule, ['EASTER_W', 'EASTER_E'])) {
					$eventOptions['start_date'] = implode('-', self::easter((int) $event->start->format('Y'), $event->special_rrule === 'EASTER_E' ? 'Eastern' : 'Western'));
				}
			}
		} else {
			self::setRequestedRRule($eventOptions);
		}

		$event->set($eventOptions);

		self::setRequestedRDatesAndExDates($event);

		$event->save();
	}

	/**
	 * Removes a holiday.
	 *
	 * Does not check permissions.
	 *
	 * @param int $id The event's ID.
	 */
	public static function remove(int $id): void
	{
		$event = current(self::load($id));

		if ($event === false) {
			return;
		}

		if (!$event->type === self::TYPE_HOLIDAY) {
			return;
		}

		parent::remove($id);
	}

	/**
	 * Imports holidays from iCalendar data and saves them to the database.
	 *
	 * @param string $ics Some iCalendar data (e.g. the content of an ICS file).
	 * @return array An array of instances of this class.
	 */
	public static function import(string $ics): array
	{
		$holidays = self::constructFromICal($ics, self::TYPE_HOLIDAY);

		foreach ($holidays as $holiday) {
			$holiday->save();
		}

		return $holidays;
	}

	/**
	 * Computes the Western and Eastern dates of Easter for the given year.
	 *
	 * In the returned array:
	 *
	 *  - 'Western' gives the Gregorian calendar date of Easter as observed by
	 *    Roman Catholic and Protestant Christians. This is computed using the
	 *    Gregorian algorithm and presented as a Gregorian date.
	 *
	 *  - 'Eastern' gives the Gregorian calendar date of Easter as observed by
	 *    Eastern Orthodox Christians. This is computed using the Julian
	 *    algorithm and then converted to a Gregorian date.
	 *
	 *  - 'Julian' gives the same date as 'Eastern', except that it is left as a
	 *    Julian calendar date rather than being converted to a Gregorian date.
	 *
	 * For years before 1583, 'Eastern' and 'Western' are the same.
	 *
	 * Beware that using 'Julian' to construct a \DateTime object will probably
	 * have unexpected results. \DateTime only understands Gregorian calendar
	 * dates.
	 *
	 * Instead, use 'Julian' to show dates as they were reckoned in a given
	 * country prior to that country's adoption of the Gregorian calendar. For
	 * example, Greece didn't adopt the Gregorian calendar until 1923, so you
	 * can use the Julian date to show the date of Easter in 1920 the way that
	 * Greek people would have reckoned it at the time (March 29 rather than
	 * March 11).
	 *
	 * Caveats:
	 *
	 *  - The computation of the date of Easter was not standardized until A.D.
	 *    325, and some regional variations persisted until approx. A.D. 664.
	 *
	 *  - Western Christians did not all adopt the Gregorian method of computing
	 *    the date of Easter at the same time, which means that the observed
	 *    date of Easter varied across regions in Western Europe for several
	 *    centuries. Moreover, the adoption of the Gregorian calendar by a
	 *    country as its civil calendar does not necessarily coincide with the
	 *    adoption of the Gregorian date of Easter in that country. Choosing the
	 *    correct computation for a particular location in a particular year
	 *    therefore requires historical knowledge.
	 *
	 * @param ?int $year The year. Must be greater than 30.
	 * @param ?string $type One of 'Western', 'Eastern', or 'Julian'. If set to
	 *     'Western', 'Eastern', or 'Julian', only that date will be returned.
	 *     Otherwise all three dates will be returned in an array.
	 * @return ?array Info about the Western and/or Eastern dates for Easter,
	 *     or null on error.
	 */
	public static function easter(?int $year = null, ?string $type = null): ?array
	{
		if (!isset($year)) {
			$now = getdate();
			$year = $now['year'];
		}

		if ($year <= 30) {
			return null;
		}

		$type = ucfirst(strtolower($type));

		$return = [
			'Western' => ['year' => null, 'month' => null, 'day' => null],
			'Eastern' => ['year' => null, 'month' => null, 'day' => null],
			'Julian' => ['year' => null, 'month' => null, 'day' => null],
		];

		// Compute according to Julian calendar.
		// https://en.wikipedia.org/w/index.php?title=Date_of_Easter&oldid=1124654731#Meeus's_Julian_algorithm
		$a = $year % 4;
		$b = $year % 7;
		$c = $year % 19;
		$d = (19 * $c + 15) % 30;
		$e = (2 * $a + 4 * $b - $d + 34) % 7;
		$f = ($d + $e + 114);

		$return['Julian']['year'] = $year;
		$return['Julian']['month'] = intdiv($f, 31);
		$return['Julian']['day'] = $f % 31 + 1;

		// Convert Julian date to Gregorian date:

		// 1. Covert Julian calendar date to Julian Day number.
		// https://en.wikipedia.org/w/index.php?title=Julian_day&oldid=996614725#Converting_Julian_calendar_date_to_Julian_Day_Number
		$jd = 367 * $year - intdiv((7 * ($year + 5001 + intdiv(($return['Julian']['month'] - 9), 7))), 4) + intdiv((275 * $return['Julian']['month']), 9) + $return['Julian']['day'] + 1729777;

		// 2. Convert Julian Day number to Gregorian date.
		// https://en.wikipedia.org/w/index.php?title=Julian_day&oldid=996614725#Julian_or_Gregorian_calendar_from_Julian_day_number
		$y = 4716;
		$j = 1401;
		$m = 2;
		$n = 12;
		$r = 4;
		$p = 1461;
		$v = 3;
		$u = 5;
		$s = 153;
		$w = 2;
		$B = 274277;

		$f = $jd + $j + intdiv(intdiv(4 * $jd + $B, 146097) * 3, 4) - 38;

		$e = $r * $f + $v;
		$g = intdiv($e % $p, $r);
		$h = $u * $g + $w;

		$return['Eastern']['day'] = intdiv($h % $s, $u) + 1;
		$return['Eastern']['month'] = ((intdiv($h, $s) + $m) % $n) + 1;
		$return['Eastern']['year'] = intdiv($e, $p) - $y + intdiv($n + $m - $return['Eastern']['month'], $n);

		// Compute according to Gregorian calendar.
		// https://en.wikipedia.org/w/index.php?title=Date_of_Easter&oldid=1180071644#Anonymous_Gregorian_algorithm
		if ($year > 1582) {
			$a = $year % 19;
			$b = intdiv($year, 100);
			$c = $year % 100;
			$d = intdiv($b, 4);
			$e = $b % 4;
			$g = intdiv((8 * $b + 13), 25);
			$h = (19 * $a + $b - $d - $g + 15) % 30;
			$i = intdiv($c, 4);
			$k = $c % 4;
			$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
			$m = intdiv(($a + 11 * $h + 19 * $l), 433);

			$return['Western']['year'] = $year;
			$return['Western']['month'] = intdiv(($h + $l - 7 * $m + 90), 25);
			$return['Western']['day'] = ($h + $l - 7 * $m + 33 * $return['Western']['month'] + 19) % 32;
		} else {
			$return['Western'] = $return['Eastern'];
		}

		if (isset($return[$type])) {
			return $return[$type];
		}

		return $return;
	}
}

?>