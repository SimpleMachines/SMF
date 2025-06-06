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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class LogActivity extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Removing index on hits in log_activity table';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure this obsolete index has been deleted.
		$table = new Schema\v2_0\LogActivity();
		$table->dropIndex('hits');

		return true;
	}
}
