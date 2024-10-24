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

use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxTopics extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Clean up indexes (Topics)';

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

		$table = new \SMF\Maintenance\Database\Schema\v2_1\Topics();

		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['id_topic'],
				'index',
				'idx_id_board',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		// Updating topics drop old id_board ix
		if ($start <= 0) {
			$oldIdx = new DbIndex(['id_board'], 'index', 'id_board');
			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>