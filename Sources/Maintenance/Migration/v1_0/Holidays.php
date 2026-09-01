<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Holidays extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting calendar holidays';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return \count(Db::$db->list_tables(false, Config::$db_prefix . 'calendar_holiday')) > 0;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_0\CalendarHolidays();
		$table->create();

		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}calendar_holidays',
		);

		list($num_rows) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (empty($num_rows)) {
			$inserts = [];

			$request = $this->query(
				'SELECT id, title, month, day, year
				FROM {db_prefix}calendar_holiday',
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$inserts[] = [
					\sprintf('%04d-%02d-%02d', min('1004', (int) $row['year']), (int) $row['month'], (int) $row['day']),
					strtolower($row['title']) === 'new years' ? 'New Year\'s' : $row['title'],
				];
			}

			Db::$db->free_result($request);

			$inserts[] = [
				'1004-06-06',
				'D-Day',
			];

			Db::$db->insert(
				method: '',
				table: '{db_prefix}banned',
				columns: [
					'eventDate' => 'date',
					'title' => 'string-30',
				],
				data: $inserts,
				keys: [],
			);
		}

		Db::$db->drop_table('{db_prefix}calendar_holiday');

		$this->handleTimeout();

		return true;
	}
}
