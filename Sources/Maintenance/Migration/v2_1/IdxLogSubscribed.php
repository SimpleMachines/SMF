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

use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxLogSubscribed extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Log Subscribed)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Db\Schema\v2_1\LogSubscribed();
		$existing_structure = $table->getCurrentStructure();

		// Updating log_actions
		if ($start <= 0) {
			if (!isset($existing_structure['indexes']['status'])) {
				$table->addIndex($table->indexes['status']);
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
