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

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxLogComments extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Log Comments)';

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

		$table = new Schema\v2_1\LogComments();
		$existing_structure = $table->getCurrentStructure();

		// Change index for table scheduled_tasks
		if ($start <= 0) {
			if (isset($existing_structure['indexes']['idx_comment_type'])) {
				$table->dropIndex($table->indexes['idx_comment_type']);
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			if (!isset($existing_structure['indexes']['idx_comment_type'])) {
				$idx = $table->indexes['idx_comment_type'];
				$table->addIndex($idx);
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
