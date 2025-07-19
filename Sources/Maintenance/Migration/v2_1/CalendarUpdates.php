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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class CalendarUpdates extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update holidays';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\CalendarHolidays();

		$this->query(
			'DELETE FROM {db_prefix}calendar_holidays
			WHERE title in ({array_string:titles})',
			[
				'titles' => array_unique(array_column($table->initial_data, 'title')),
			],
		);

		$table->populate();

		return true;
	}
}
