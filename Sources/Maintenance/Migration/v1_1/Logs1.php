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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Logs1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading log tables, part 1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Updating flood control log.
		$table = new Schema\v1_1\LogFloodcontrol();
		$table->dropIndex('logTime');

		// Updating ip address storage.
		$table = new Schema\v1_1\LogActions();
		$table->alterColumn($table->columns['ip'], 'IP');

		$table = new Schema\v1_1\LogBanned();
		$table->alterColumn($table->columns['ip'], 'IP');
		$table->dropColumn('ban_ids');

		$table = new Schema\v1_1\LogErrors();
		$table->dropIndex('IP');
		$table->alterColumn($table->columns['ip'], 'IP');

		// Converting "log_online".
		$table = new Schema\v1_1\LogOnline();
		$table->drop();
		$table->create();

		$this->handleTimeout();

		return true;
	}
}
