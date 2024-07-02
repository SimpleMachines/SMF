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
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSqlTime extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Time and date fixes';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type === POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		// FROM_UNIXTIME fix
		if ($start <= 0) {
			// Drop the old int version
			$this->query('', '
				DROP FUNCTION IF EXISTS FROM_UNIXTIME(int)');

			$this->query('', '
				CREATE OR REPLACE FUNCTION FROM_UNIXTIME(bigint) RETURNS timestamp AS
				\'SELECT timestamp \'\'epoch\'\' + $1 * interval \'\'1 second\'\' AS result\'
				LANGUAGE \'sql\'');

			$this->handleTimeout(++$start);
		}

		// bigint versions of date functions
		if ($start <= 1) {
			// MONTH(bigint)
			$this->query('', '
				CREATE OR REPLACE FUNCTION MONTH (bigint) RETURNS integer AS
				\'SELECT CAST (EXTRACT(MONTH FROM TO_TIMESTAMP($1)) AS integer) AS result\'
				LANGUAGE \'sql\'');

			// DAYOFMONTH(bigint)
			$this->query('', '
				CREATE OR REPLACE FUNCTION DAYOFMONTH (bigint) RETURNS integer AS
				\'SELECT CAST (EXTRACT(DAY FROM TO_TIMESTAMP($1)) AS integer) AS result\'
				LANGUAGE \'sql\'');

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>