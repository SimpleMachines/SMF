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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class AdminInfoFiles extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Create a repository for the JavaScript files from Simple Machines';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table is structured correctly.
		$table = new Schema\v2_0\AdminInfoFiles();
		$table->normalize();

		// Add in the files to get from Simple Machines.
		$table->populate();

		// Set the filetype for the files.
		$this->query(
			'UPDATE {db_prefix}admin_info_files
			SET filetype = {string:type}
			WHERE id_file IN ({array_int:ids})',
			[
				'type' => 'text/javascript',
				'ids' => [1, 2, 3, 4, 5, 6, 7],
			],
		);

		// Ensure that the files from Simple Machines get updated.
		$this->query(
			'UPDATE {db_prefix}scheduled_tasks
			SET next_time = {int:now}
			WHERE task = {string:task}',
			[
				'now' => time(),
				'task' => 'fetchSMfiles',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
