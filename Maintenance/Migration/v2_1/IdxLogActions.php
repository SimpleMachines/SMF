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

use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxLogActions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Clean up indexes (Log Actions)';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogActions();
		$existing_structure = $table->getCurrentStructure();

		// Updating log_actions
		if ($start <= 0) {
			foreach ($table->indexes as $idx) {
				if ($idx->name === 'id_topic_id_log' && !isset($existing_structure['indexes']['id_topic_id_log'])) {
					$table->addIndex($idx);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>