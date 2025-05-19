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

use SMF\Db\Schema\DbIndex;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxLogActivity extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Log Activity)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Db\Schema\v2_1\LogActivity();

		// Updating log_activity mostOn
		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['most_on'],
				'index',
				'mostOn',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		// Updating topics drop old id_board ix
		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['most_on'],
				'index',
				'most_on',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
