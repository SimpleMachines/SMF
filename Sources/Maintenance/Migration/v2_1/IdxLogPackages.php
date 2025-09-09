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

class IdxLogPackages extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clean up indexes (Log Packages)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new Schema\v2_1\LogActions();

		// Drop various indexes that we want to ditch or change.
		// Some will be added again in a later step.
		if ($start <= 0) {
			$table->dropIndex('filename');

			$this->handleTimeout(++$start);
		}

		if ($start <= 0) {
			$table->dropIndex('idx_filename');

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
