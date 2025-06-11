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
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Logs6 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading log tables, part 6';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_1\LogTopics();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'logTime') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Here we remove all the unused indexes.
		while (true) {
			switch (Maintenance::getCurrentStart()) {
				case 0:
					$table = new Schema\v1_1\Boards();
					$table->dropIndex('lastUpdated');
					$table->dropColumn('lastUpdated');
					break;

				case 1:
					$table = new Schema\v1_1\Messages();
					$table->dropIndex('posterTime');
					break;

				case 2:
					$table = new Schema\v1_1\Messages();
					$table->dropIndex('modifiedTime');
					break;

				case 3:
					$table = new Schema\v1_1\LogTopics();
					$table->dropIndex('logTime');
					$table->dropColumn('logTime');
					break;

				case 4:
					$table = new Schema\v1_1\LogBoards();
					$table->dropIndex('logTime');
					$table->dropColumn('logTime');
					break;

				case 5:
					$table = new Schema\v1_1\LogMarkRead();
					$table->dropIndex('logTime');
					$table->dropColumn('logTime');
					break;

				default:
					break 2;
			}

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		return true;
	}
}
