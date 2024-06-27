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
use SMF\Time;
use SMF\User;

/**
 * Represents a member's birthday.
 */
class Birthday extends Event
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * A value to add to the ID in order to avoid conflicts with regular events.
	 */
	public const ID_MODIFIER = 10 ** 6;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This event's type.
	 * Value must be one of the parent class's TYPE_* constants.
	 */
	public int $type = self::TYPE_BIRTHDAY;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var bool
	 *
	 * If true, Birthday::get() will not destroy instances after yielding them.
	 * This is used internally by Birthday::getOccurrencesInRange().
	 */
	protected static bool $keep_all = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 */
	public function save(): void
	{
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
	 * Loads a member's birthday.
	 *
	 * @param int|array $id ID number of the member.
	 * @param bool $is_topic Ignored.
	 * @param bool $use_permissions Ignored.
	 * @return array Instances of this class for the loaded events.
	 */
	public static function load(int $id, bool $is_topic = false, bool $use_permissions = true): array
	{
		if ($id <= 0) {
			return [];
		}

		$loaded = [];

		$selects = [
			'm.id_member',
			'm.real_name',
			'm.birthdate',
		];
		$where = [
			'm.id_member = {int:id}',
		];
		$params = [
			'id' => $id,
		];

		foreach(self::queryData($selects, $params, [], $where) as $row) {
			// Add a value to the ID in order to avoid conflicts with regular events.
			$id = self::ID_MODIFIER + (int) $row['id_member'];

			$row['view_start'] = \DateTimeImmutable::createFromInterface($row['view_start']);
			$row['view_end'] = $row['view_start']->add(new \DateInterval('P1D'));

			$loaded[] = new self($id, $row);
		}

		return $loaded;
	}

	/**
	 * Generator that yields instances of this class.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format.
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format.
	 * @param bool $use_permissions Ignored.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return Generator<Event> Iterating over result gives Event instances.
	 */
	public static function get(string $low_date, string $high_date, bool $use_permissions = true, array $query_customizations = []): \Generator
	{
		$low_date = !empty($low_date) ? $low_date : (new \DateTime('first day of this month, midnight'))->format('Y-m-d');
		$high_date = !empty($high_date) ? $high_date : (new \DateTime('first day of next month, midnight'))->format('Y-m-d');

		// We need to search for any birthday in this range, and whatever year that birthday is on.
		$year_low = (int) substr($low_date, 0, 4);
		$year_high = (int) substr($high_date, 0, 4);

		$selects = $query_customizations['selects'] ?? [
			'm.id_member',
			'm.real_name',
			'm.birthdate',
		];
		$joins = $query_customizations['joins'] ?? [];
		$order = $query_customizations['order'] ?? [];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;

		switch (Db::$db->title) {
			case POSTGRE_TITLE:
				$where = $query_customizations['where'] ?? [
					'birthdate != {date:no_birthdate}',
					'is_activated = {int:is_activated}',
					'(
						indexable_month_day(birthdate) BETWEEN indexable_month_day({date:year_low_low_date}) AND indexable_month_day({date:year_low_high_date})' . ($year_low == $year_high ? '' : '
						OR indexable_month_day(birthdate) BETWEEN indexable_month_day({date:year_high_low_date}) AND indexable_month_day({date:year_high_high_date})') . '
					)',
				];
				$params = $query_customizations['params'] ?? [
					'is_activated' => User::ACTIVATED,
					'no_birthdate' => '1004-01-01',
					'year_low_low_date' => $low_date,
					'year_low_high_date' => $year_low == $year_high ? $high_date : $year_low . '-12-31',
					'year_high_low_date' => $year_low == $year_high ? $low_date : $year_high . '-01-01',
					'year_high_high_date' => $high_date,
				];
				break;

			default:
				$where = $query_customizations['where'] ?? [
					'birthdate != {date:no_birthdate}',
					'is_activated = {int:is_activated}',
					'(
						DATE_FORMAT(birthdate, {string:year_low}) BETWEEN {date:low_date} AND {date:high_date}' . ($year_low == $year_high ? '' : '
						OR DATE_FORMAT(birthdate, {string:year_high}) BETWEEN {date:low_date} AND {date:high_date}') . '
					)',
				];
				$params = $query_customizations['params'] ?? [
					'is_activated' => User::ACTIVATED,
					'no_birthdate' => '1004-01-01',
					'year_low' => $year_low . '-%m-%d',
					'year_high' => $year_high . '-%m-%d',
					'low_date' => $low_date,
					'high_date' => $high_date,
				];
				break;
		}

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			// Add a value to the ID in order to avoid conflicts with regular events.
			$id = self::ID_MODIFIER + (int) $row['id_member'];

			$row['view_start'] = new \DateTimeImmutable($low_date);
			$row['view_end'] = new \DateTimeImmutable($high_date);

			yield (new self($id, $row));

			if (!self::$keep_all) {
				unset(self::$loaded[$id]);
			}
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
		self::$keep_all = true;

		foreach (self::get($low_date, $high_date, $use_permissions, $query_customizations) as $event) {
			foreach ($event->getAllVisibleOccurrences() as $occurrence) {
				yield $occurrence;
			}
		}

		self::$keep_all = false;
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param array $eventOptions Event data ('title', 'start_date', etc.)
	 */
	public static function create(array $eventOptions): void
	{
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param int $id The ID of the event
	 * @param array $eventOptions An array of event information.
	 */
	public static function modify(int $id, array &$eventOptions): void
	{
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param int $id The event's ID.
	 */
	public static function remove(int $id): void
	{
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param array $eventOptions An array of optional time and date parameters
	 *    (span, start_year, end_month, etc., etc.)
	 */
	public static function setRequestedStartAndDuration(array &$eventOptions): void
	{
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
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0): \Generator
	{
		$request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}members AS m' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($group) ? '' : '
			GROUP BY ' . implode(', ', $group)) . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (!empty($limit) ? '
			LIMIT ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['start'] = new Time($row['birthdate']);
			unset($row['birthdate']);

			$row['recurrence_end'] = (clone $row['start'])->modify('+ 130 years');

			$row['rrule'] = 'FREQ=YEARLY;INTERVAL=1';
			$row['duration'] = new \DateInterval('P1D');
			$row['allday'] = true;

			$row['rdates'] = [];
			$row['exdates'] = [];

			yield $row;
		}
		Db::$db->free_result($request);
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param array $eventOptions An array of optional time and date parameters
	 *    (span, start_year, end_month, etc., etc.)
	 */
	protected static function setRequestedRRule(array &$eventOptions): void
	{
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param Event $event An event that is being created or modified.
	 */
	protected static function setRequestedRDatesAndExDates(Event $event): void
	{
	}

	/**
	 * Not applicable. Birthday info is updated via the user's profile.
	 *
	 * @param array $input Array of info about event start and end times.
	 * @return array Standardized version of $input array.
	 */
	protected static function standardizeEventOptions(array $input): array
	{
		return $input;
	}
}

?>