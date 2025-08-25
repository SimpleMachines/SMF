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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class SearchResultsPrimaryKey extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Improving search results storage';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		Db::$db->query(
			'ALTER TABLE {db_prefix}log_search_results DROP PRIMARY KEY',
			[],
		);

		Db::$db->query(
			'ALTER TABLE {db_prefix}log_search_results ADD PRIMARY KEY (id_search, id_topic, id_msg)',
			[],
		);

		return true;
	}
}
