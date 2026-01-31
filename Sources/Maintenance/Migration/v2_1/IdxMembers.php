<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxMembers extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Members)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new Schema\v2_1\Members();
		$existing_structure = $table->getCurrentStructure();

		// Drop various indexes that we want to ditch or change.
		// Some will be added again in a later step.
		if ($start <= 0) {
			$table->dropIndex('member_name_low');
			$table->dropIndex('idx_member_name_low');

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			$table->dropIndex('real_name_low');
			$table->dropIndex('idx_real_name_low');

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			$table->dropIndex('active_real_name');
			$table->dropIndex('idx_active_real_name');

			$this->handleTimeout(++$start);
		}

		if ($start <= 3) {
			$table->dropIndex('memberName');

			$this->handleTimeout(++$start);
		}

		if ($start <= 4) {
			$table->dropIndex('birthdate2');
			$table->dropIndex('idx_birthdate2');

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
