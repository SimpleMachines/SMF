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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSqlTime extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Time and date fixes';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return Db::$db->title === POSTGRE_TITLE;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		// FROM_UNIXTIME fix
		if ($start <= 0) {
			// Drop the old int version
			$this->query(
				'DROP FUNCTION IF EXISTS FROM_UNIXTIME(int)',
			);

			$this->query(
				'CREATE OR REPLACE FUNCTION FROM_UNIXTIME(bigint) RETURNS timestamp AS
				\'SELECT timestamp \'\'epoch\'\' + $1 * interval \'\'1 second\'\' AS result\'
				LANGUAGE \'sql\'',
			);

			$this->handleTimeout(++$start);
		}

		// bigint versions of date functions
		if ($start <= 1) {
			// MONTH(bigint)
			$this->query(
				'CREATE OR REPLACE FUNCTION MONTH (bigint) RETURNS integer AS
				\'SELECT CAST (EXTRACT(MONTH FROM TO_TIMESTAMP($1)) AS integer) AS result\'
				LANGUAGE \'sql\'',
			);

			// DAYOFMONTH(bigint)
			$this->query(
				'CREATE OR REPLACE FUNCTION DAYOFMONTH (bigint) RETURNS integer AS
				\'SELECT CAST (EXTRACT(DAY FROM TO_TIMESTAMP($1)) AS integer) AS result\'
				LANGUAGE \'sql\'',
			);

			$this->handleTimeout(++$start);
		}

		// Indexable month and day. Used for birthdays.
		if ($start <= 2) {
			$this->query(
				'CREATE OR REPLACE FUNCTION indexable_month_day(date) RETURNS TEXT as \'
				SELECT to_char($1, \'\'MM-DD\'\');\'
				LANGUAGE \'sql\' IMMUTABLE STRICT',
			);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
