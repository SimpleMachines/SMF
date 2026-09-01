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

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class SearchCache extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating search cache';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		Db::$db->drop_table('{db_prefix}log_search_fulltext');
		Db::$db->drop_table('{db_prefix}log_search_messages');
		Db::$db->drop_table('{db_prefix}log_search_topics');
		Db::$db->drop_table('{db_prefix}log_search');

		$table = new Schema\v1_1\LogSearchMessages();
		$table->create();

		$table = new Schema\v1_1\LogSearchTopics();
		$table->create();

		$table = new Schema\v1_1\LogSearchResults();
		$table->create();

		$table = new Schema\v1_1\LogSearchSubjects();
		$table->create();


		$this->handleTimeout();

		return true;
	}
}
