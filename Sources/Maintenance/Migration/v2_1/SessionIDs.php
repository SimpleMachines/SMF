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

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class SessionIDs extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding more space for session ids';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$log_online_table = new Schema\v2_1\LogOnline();
		$log_errors_table = new Schema\v2_1\LogErrors();
		$sessions_table = new Schema\v2_1\Sessions();

		foreach ($log_online_table->columns as $column) {
			if ($column->name !== 'session') {
				continue;
			}

			$log_online_table->alterColumn($column);
		}

		foreach ($log_errors_table->columns as $column) {
			if ($column->name !== 'session') {
				continue;
			}

			$log_errors_table->alterColumn($column);
		}

		foreach ($sessions_table->columns as $column) {
			if ($column->name !== 'session_id') {
				continue;
			}

			$sessions_table->alterColumn($column);
		}

		return true;
	}
}
