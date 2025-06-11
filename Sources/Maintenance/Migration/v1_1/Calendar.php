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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Calendar extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating calendar tables and data';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the tables are structured correctly.
		$table = new Schema\v1_1\Calendar();
		$table->alterColumn($table->columns['startDate'], 'eventDate');
		$table->dropIndex('ID_TOPIC');
		$table->normalize();

		$table = new Schema\v1_1\CalendarHolidays();
		$table->normalize();

		// Data updates.
		$this->query(
			'UPDATE {db_prefix}calendar
			SET endDate = startDate
			WHERE endDate = {string:date_zero}
				OR endDate = {string:date_one}',
			[
				'date_zero' => '0000-00-00',
				'date_one' => '0001-01-01',
			],
		);

		$this->query(
			'UPDATE {db_prefix}calendar_holidays
			SET eventDate = {string:date_one}
			WHERE eventDate = {string:date_zero}',
			[
				'date_zero' => '0000-00-00',
				'date_one' => '0001-01-01',
			],
		);

		// This only works on MySQL, but that's fine because SMF 1.0 never
		// supported other databases anyway.
		$this->query(
			'UPDATE {db_prefix}calendar_holidays
			SET eventDate = CONCAT_WS({string:hyphen}, {string:year_four}, MONTH(eventDate), DAYOFMONTH(eventDate))
			WHERE YEAR(eventDate) = 0',
			[
				'year_four' => '0004',
				'hyphen' => '-',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
