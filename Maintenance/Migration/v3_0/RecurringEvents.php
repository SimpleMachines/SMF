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

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class RecurringEvents extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for recurring events';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v3_0\Calendar();
		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			if (!isset($existing_structure['columns'][$column->name])) {
				$table->addColumn($column);
			}
		}

		if (Db::$db->title === MYSQL_TITLE) {
			Db::$db->query(
				'',
				'ALTER TABLE {db_prefix}calendar
				MODIFY COLUMN start_date DATE AFTER id_member',
				[],
			);

			Db::$db->query(
				'',
				'ALTER TABLE {db_prefix}calendar
				MODIFY COLUMN end_date DATE AFTER start_date',
				[],
			);

		}

		$updates = [];

		$request = Db::$db->query(
			'',
			'SELECT id_event, start_date, end_date, start_time, end_time, timezone
			FROM {db_prefix}calendar',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row = array_diff($row, array_filter($row, 'is_null'));

			$allday = (
				!isset($row['start_time'])
				|| !isset($row['end_time'])
				|| !isset($row['timezone'])
				|| !in_array($row['timezone'], timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))
			);

			$start = new \DateTime($row['start_date'] . (!$allday ? ' ' . $row['start_time'] . ' ' . $row['timezone'] : ''));
			$end = new \DateTime($row['end_date'] . (!$allday ? ' ' . $row['end_time'] . ' ' . $row['timezone'] : ''));

			if ($allday) {
				$end->modify('+1 day');
			}

			$duration = date_diff($start, $end);

			$format = '';

			foreach (['y', 'm', 'd', 'h', 'i', 's'] as $part) {
				if ($part === 'h') {
					$format .= 'T';
				}

				if (!empty($duration->{$part})) {
					$format .= '%' . $part . ($part === 'i' ? 'M' : strtoupper($part));
				}
			}
			$format = rtrim('P' . $format, 'PT');

			$updates[$row['id_event']] = [
				'id_event' => $row['id_event'],
				'duration' => $duration->format($format),
				'end_date' => $end->format('Y-m-d'),
				'rrule' => 'FREQ=YEARLY;COUNT=1',
			];
		}
		Db::$db->free_result($request);

		foreach ($updates as $id_event => $changes) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}calendar
				SET duration = {string:duration}, end_date = {date:end_date}, rrule = {string:rrule}
				WHERE id_event = {int:id_event}',
				$changes,
			);
		}

		Db::$db->remove_column('{db_prefix}calendar', 'end_time');

		return true;
	}
}

?>