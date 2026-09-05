<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

/**
 * @todo Find a SQL standard way of handling this.
 *    Maybe DATEADD with a calc on YEAR() to find what it takes to make it 1004?
 *    PostgreSQL does not have DATEFROMPARTS, but does have make_date (9.4>),
 *    which would be similar the more standard DATEFROMPARTS.
 */
class FixDates extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Fixing dates';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		if (Db::$db->title === POSTGRE_TITLE) {
			$boilerplate = 'UPDATE {db_prefix}%1$s
				SET %2$s = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM %2$s) < 1004 THEN 1004 END, EXTRACT(MONTH FROM %2$s), EXTRACT(DAY FROM %2$s))::date
				WHERE EXTRACT(YEAR FROM %2$s) < 1004';

			$bday_query = 'UPDATE {db_prefix}members
				SET birthdate = concat_ws({literal:-}, CASE WHEN EXTRACT(YEAR FROM birthdate) < 1004 THEN 1004 END, CASE WHEN EXTRACT(MONTH FROM birthdate) < 1 THEN 1 ELSE EXTRACT(MONTH FROM birthdate) END, CASE WHEN EXTRACT(DAY FROM birthdate) < 1 THEN 1 ELSE EXTRACT(DAY FROM birthdate) END)::date
				WHERE EXTRACT(YEAR FROM birthdate) < 1004 OR EXTRACT(MONTH FROM birthdate) < 1 OR EXTRACT(DAY FROM birthdate) < 1';

		} else {
			$boilerplate = 'UPDATE {db_prefix}%1$s
				SET %2$s = DATE(CONCAT(1004, {literal:-}, MONTH(%2$s), {literal:-}, DAY(%2$s)))
				WHERE YEAR(%2$s) < 1004';

			$bday_query = 'UPDATE {db_prefix}members
				SET birthdate = DATE(CONCAT(IF(YEAR(birthdate) < 1004, 1004, YEAR(birthdate)), {literal:-}, IF(MONTH(birthdate) < 1, 1, MONTH(birthdate)), {literal:-}, IF(DAY(birthdate) < 1, 1, DAY(birthdate))))
				WHERE YEAR(birthdate) < 1004 OR MONTH(birthdate) < 1 OR DAY(birthdate) < 1';
		}

		if (Maintenance::getCurrentStart() < 1) {
			$this->query(\sprintf($boilerplate, 'calendar', 'start_date'));

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 2) {
			$this->query(\sprintf($boilerplate, 'calendar', 'end_date'));

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 3) {
			if ($this->holidaysTableExists()) {
				$this->query(\sprintf($boilerplate, 'calendar_holidays', 'event_date'));
			}

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 4) {
			$this->query(\sprintf($boilerplate, 'log_spider_stats', 'stat_date'));

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 5) {
			$this->query($bday_query);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 6) {
			Db::$db->change_column(
				'{db_prefix}log_activity',
				'DATE',
				[
					'not_null' => true,
					'default' => null,
				],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 7) {
			Db::$db->change_column(
				'{db_prefix}calendar',
				'start_date',
				['default' => '1004-01-01'],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 8) {
			Db::$db->change_column(
				'{db_prefix}calendar',
				'end_date',
				['default' => '1004-01-01'],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 9) {
			if ($this->holidaysTableExists()) {
				Db::$db->change_column(
					'{db_prefix}calendar_holidays',
					'event_date',
					['default' => '1004-01-01'],
				);
			}

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 10) {
			Db::$db->change_column(
				'{db_prefix}log_spider_stats',
				'stat_date',
				['default' => '1004-01-01'],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		if (Maintenance::getCurrentStart() < 11) {
			Db::$db->change_column(
				'{db_prefix}members',
				'stat_date',
				['birthdate' => '1004-01-01'],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Whether the holidays table is still there to be fixed.
	 *
	 * The 3.0 migrations fold that table into the calendar and then drop it, so
	 * an upgrade that reaches them and is started again finds it gone. The rest
	 * of this migration still has work to do on the tables that remain.
	 *
	 * @return bool Whether the table exists.
	 */
	private function holidaysTableExists(): bool
	{
		return Db::$db->list_tables(false, Config::$db_prefix . 'calendar_holidays') !== [];
	}
}
