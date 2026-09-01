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

class EmailDigests extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding email digest functionality';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Note: there is also a table that needs to be created, but the table
		// normalization substeps will take care of that part.

		// Change the old 'notifyOnce' column to handle digest options.
		$table = new Schema\v2_0\Members();
		$table->alterColumn($table->columns['notify_regularity'], 'notifyOnce');

		$this->handleTimeout();

		return true;
	}
}
