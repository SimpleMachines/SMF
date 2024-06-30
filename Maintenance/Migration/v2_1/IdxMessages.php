<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxMessages extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Remove redundant indexes';

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

		$table = new \SMF\Maintenance\Database\Schema\v3_0\Messages();

		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['id_topic'],
				'index',
				'idx_id_topic',
			);

			$table->dropIndex($oldIdx);
		}

		$this->handleTimeout(++$start);

		if ($start <= 1) {
			$oldIdx = new DbIndex(
				['id_topic'],
				'index',
				'idx_topic',
			);

			$table->dropIndex($oldIdx);
		}

		$this->handleTimeout(++$start);

		return true;
	}
}

?>