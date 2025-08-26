<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxMessages extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Messages)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new Schema\v2_1\Messages();
		$existing_structure = $table->getCurrentStructure();

		// Drop various indexes that we want to ditch or change.
		// Some will be added again in a later step.
		if ($start <= 0) {
			$table->dropIndex('idx_id_topic');

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			$table->dropIndex('idx_topic');

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			$table->dropIndex('idx_likes');

			$this->handleTimeout(++$start);
		}

		if ($start <= 4) {
			$table->dropIndex('ipIndex');

			$this->handleTimeout(++$start);
		}

		if ($start <= 5) {
			$table->dropIndex('ip_index');

			$this->handleTimeout(++$start);
		}

		if ($start <= 6) {
			$table->dropIndex('related_ip');

			$this->handleTimeout(++$start);
		}

		if ($start <= 7) {
			$table->dropIndex('topic');

			$this->handleTimeout(++$start);
		}

		if ($start <= 8) {
			$table->dropIndex('id_topic');

			$this->handleTimeout(++$start);
		}

		if ($start <= 9) {
			$table->dropIndex('approved');

			$this->handleTimeout(++$start);
		}

		if ($start <= 10) {
			$table->dropIndex('idx_approved');

			$this->handleTimeout(++$start);
		}

		if ($start <= 11) {
			$table->dropIndex('id_board');

			$this->handleTimeout(++$start);
		}

		if ($start <= 12) {
			$table->dropIndex('idx_id_board');

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
