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

class IdxMessages extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Clean up indexes (Messages)';

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

		$table = new \SMF\Maintenance\Database\Schema\v2_1\Messages();
		$existing_structure = $table->getCurrentStructure();

		if ($start <= 0) {
			$oldIdx = new DbIndex(
				['id_topic'],
				'index',
				'idx_id_topic',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			$oldIdx = new DbIndex(
				['id_topic'],
				'index',
				'idx_topic',
			);

			$table->dropIndex($oldIdx);

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			foreach ($table->indexes as $idx) {
				if ($idx->name === 'idx_likes' && isset($existing_structure['indexes']['idx_likes'])) {
					$table->dropIndex($oldIdx);
				}
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 3) {
			foreach ($table->indexes as $idx) {
				if ($idx->name === 'idx_likes') {
					$table->addIndex($oldIdx);
				}
			}

			$this->handleTimeout(++$start);
		}

		// Updating messages drop old ipIndex
		if ($start <= 4) {
			$oldIdx = new DbIndex(['member_ip'], 'index', 'ipIndex');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop old ip_index
		if ($start <= 5) {
			$oldIdx = new DbIndex(['member_ip'], 'index', 'ip_index');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop old related_ip
		if ($start <= 6) {
			$oldIdx = new DbIndex(['member_ip'], 'index', 'related_ip');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop old topic ix
		if ($start <= 7) {
			$oldIdx = new DbIndex(['id_topic'], 'index', 'topic');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop another old topic ix
		if ($start <= 8) {
			$oldIdx = new DbIndex(['id_topic'], 'index', 'id_topic');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop another old topic ix
		if ($start <= 9) {
			$oldIdx = new DbIndex(['approved'], 'index', 'approved');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop another old topic ix
		if ($start <= 10) {
			$oldIdx = new DbIndex(['approved'], 'index', 'idx_approved');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop id_board ix
		if ($start <= 11) {
			$oldIdx = new DbIndex(['id_board'], 'index', 'id_board');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages drop id_board ix alt name
		if ($start <= 12) {
			$oldIdx = new DbIndex(['id_board'], 'index', 'idx_id_board');
			$table->dropIndex($idx);

			$this->handleTimeout(++$start);
		}

		// Updating messages add new id_board ix
		if ($start <= 12) {
			foreach ($table->indexes as $idx) {
				if ($idx->name === 'idx_id_board') {
					$table->addIndex($idx);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>