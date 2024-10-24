<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxLogComments extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Clean up indexes (Log Comments)';

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

		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogComments();
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
				$table->addIndex($idx, Config::$db_type === POSTGRE_TITLE ? 'replace' : 'ignore', ['varchar_pattern_ops' => $idx->columns[0]]);
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>