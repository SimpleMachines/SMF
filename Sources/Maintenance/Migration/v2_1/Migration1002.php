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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;

class Migration1002 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Fixing dates';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		// @@ TODO: Find a SQL standard way of handling this.  Maybe DATEADD with a calc on YEAR() to find what it takes to make it 1004?
		// PostgreSQL does not have DATEFROMPARTS, but does have make_date (9.4>), which would be similar the more standard DATEFROMPARTS.

		// PostgreSQL does the query a bit different.
		$is_pgsql = Config::$db_type == POSTGRE_TITLE;

		if (Maintenance::getCurrentStart() < 1 && $is_pgsql) {
			$this->query('', '
                UPDATE {db_prefix}calendar
                SET start_date = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM start_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM start_date), EXTRACT(DAY FROM start_date))::date
                WHERE EXTRACT(YEAR FROM start_date) < 1004');
		} elseif (Maintenance::getCurrentStart() < 1) {
			$this->query('', '
                UPDATE {db_prefix}calendar
                SET start_date = DATE(CONCAT(1004, {literal:-}, MONTH(start_date), {literal:-}, DAY(start_date)))
                WHERE YEAR(start_date) < 1004');
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		if (Maintenance::getCurrentStart() < 2 && $is_pgsql) {
			$this->query('', '
                UPDATE {db_prefix}calendar
                SET end_date = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM end_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM end_date), EXTRACT(DAY FROM end_date))::date
                WHERE EXTRACT(YEAR FROM end_date) < 1004');
		} elseif (Maintenance::getCurrentStart() < 2) {
			$this->query('', '
                UPDATE {$db_prefix}calendar
                SET end_date = DATE(CONCAT(1004, {literal:-}, MONTH(end_date), {literal:-}, DAY(end_date)))
                WHERE YEAR(end_date) < 1004');
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		if (Maintenance::getCurrentStart() < 3 && $is_pgsql) {
			$this->query('', '
                UPDATE {db_prefix}calendar_holidays
                SET event_date = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM event_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM event_date), EXTRACT(DAY FROM event_date))::date
                WHERE EXTRACT(YEAR FROM event_date) < 1004');
		} elseif (Maintenance::getCurrentStart() < 3) {
			$this->query('', '
                UPDATE {$db_prefix}calendar_holidays
                SET event_date = DATE(CONCAT(1004, {literal:-}, MONTH(event_date), {literal:-}, DAY(event_date)))
                WHERE YEAR(event_date) < 1004');
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		if (Maintenance::getCurrentStart() < 4 && $is_pgsql) {
			$this->query('', '
                UPDATE {db_prefix}log_spider_stats
                SET stat_date = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM stat_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM stat_date), EXTRACT(DAY FROM stat_date))::date
                WHERE EXTRACT(YEAR FROM stat_date) < 1004', []);
		} elseif (Maintenance::getCurrentStart() < 4) {
			$this->query('', '
                UPDATE {$db_prefix}log_spider_stats
                SET stat_date = DATE(CONCAT(1004, {literal:-}, MONTH(stat_date), {literal:-}, DAY(stat_date)))
                WHERE YEAR(stat_date) < 1004');
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		if (Maintenance::getCurrentStart() < 5 && $is_pgsql) {
			$this->query('', '
                UPDATE {db_prefix}log_spider_stats
                SET birthdate = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM birthdate) < 1004 THEN 1004 END, CASE WHEN EXTRACT(MONTH FROM birthdate) < 1 THEN 1 ELSE EXTRACT(MONTH FROM birthdate) END, CASE WHEN EXTRACT(DAY FROM birthdate) < 1 THEN 1 ELSE EXTRACT(DAY FROM birthdate) END)::date
                WHERE EXTRACT(YEAR FROM birthdate) < 1004 OR EXTRACT(MONTH FROM birthdate) < 1 OR EXTRACT(DAY FROM birthdate) < 1');
		} elseif (Maintenance::getCurrentStart() < 5) {
			$this->query('', '
                UPDATE {$db_prefix}members
                SET birthdate = DATE(CONCAT(IF(YEAR(birthdate) < 1004, 1004, YEAR(birthdate)), {literal:-}, IF(MONTH(birthdate) < 1, 1, MONTH(birthdate)), {literal:-}, IF(DAY(birthdate) < 1, 1, DAY(birthdate))))
                WHERE YEAR(birthdate) < 1004 OR MONTH(birthdate) < 1 OR DAY(birthdate) < 1');
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		if (Maintenance::getCurrentStart() < 6 && $is_pgsql) {
			$this->query('', '
                UPDATE {$db_prefix}members
                SET birthdate = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM birthdate) < 1004 THEN 1004 END, CASE WHEN EXTRACT(MONTH FROM birthdate) < 1 THEN 1 ELSE EXTRACT(MONTH FROM birthdate) END, CASE WHEN EXTRACT(DAY FROM birthdate) < 1 THEN 1 ELSE EXTRACT(DAY FROM birthdate) END)::date
                WHERE EXTRACT(YEAR FROM birthdate) < 1004 OR EXTRACT(MONTH FROM birthdate) < 1 OR EXTRACT(DAY FROM birthdate) < 1', []);
		} elseif (Maintenance::getCurrentStart() < 6) {
			$this->query('', '
                UPDATE {db_prefix}members
                SET birthdate = DATE(CONCAT(IF(YEAR(birthdate) < 1004, 1004, YEAR(birthdate)), {literal:-}, IF(MONTH(birthdate) < 1, 1, MONTH(birthdate)), {literal:-}, IF(DAY(birthdate) < 1, 1, DAY(birthdate))))
                WHERE YEAR(birthdate) < 1004 OR MONTH(birthdate) < 1 OR DAY(birthdate) < 1');
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		if (Maintenance::getCurrentStart() < 7) {
			Db::$db->change_column(
				'{db_prefix}log_activity',
				'DATE',
				[
					'not_null' => true,
					'default' => null,
				],
			);
		}
		Maintenance::setCurrentStart();
		$this->handleTimeout();

		$fixes = [
			['tbl' => '{db_prefix}calendar', 'col' => 'start_date'],
			['tbl' => '{db_prefix}calendar', 'col' => 'end_date'],
			['tbl' => '{db_prefix}calendar_holidays', 'col' => 'event_date'],
			['tbl' => '{db_prefix}log_spider_stats', 'col' => 'stat_date'],
			['tbl' => '{db_prefix}members', 'col' => 'birthdate'],
		];

		for ($key = Maintenance::getCurrentStart(); $key < count($fixes); Maintenance::setCurrentStart()) {
			$fix = $fixes[$key - 7];

			Db::$db->change_column($fix['tbl'], $fix['col'], ['default' => '1004-01-01']);
			$this->handleTimeout();
		}

		return true;
	}
}

?>