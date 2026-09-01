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

class Logs3 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading log tables, part 3';

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
		while (true) {
			switch (Maintenance::getCurrentStart()) {
				case 0:
					$table = new Schema\v1_1\LogTopics();
					$table->dropIndex('ID_MEMBER');
					break;

				case 1:
					$table = new Schema\v1_1\LogTopics();
					$table->dropIndex('primary');
					$table->addIndex($table->indexes['primary']);
					break;

				case 2:
					$table = new Schema\v1_1\LogTopics();
					$table->addIndex(new Schema\DbIndex(
						name: 'logTime',
						columns: ['logTime'],
					));
					break;

				case 3:
					$table = new Schema\v1_1\LogBoards();
					$table->addColumn($table->columns['ID_MSG']);
					break;

				case 4:
					$table = new Schema\v1_1\LogMarkRead();
					$table->addColumn($table->columns['ID_MSG']);
					break;

				case 5:
					$table = new Schema\v1_1\LogTopics();
					$table->addColumn($table->columns['ID_MSG']);
					break;

				case 6:
					$table = new Schema\v1_1\Messages();
					$table->addColumn($table->columns['ID_MSG_MODIFIED']);
					break;

				case 7:
					$table = new Schema\v1_1\Boards();
					$table->addColumn($table->columns['ID_MSG_UPDATED']);
					break;

				case 8:
					$table = new Schema\v1_1\Boards();
					$table->addIndex($table->indexes['ID_MSG_UPDATED']);
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
