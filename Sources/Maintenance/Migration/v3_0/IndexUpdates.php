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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class IndexUpdates extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Ensuring indexes are correct on certain tables';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\LogNotify();
		$table->alterIndex($table->indexes['id_board']);

		$table = new Schema\v3_0\LogSearchResults();
		$table->alterIndex($table->indexes['primary']);

		return true;
	}
}
