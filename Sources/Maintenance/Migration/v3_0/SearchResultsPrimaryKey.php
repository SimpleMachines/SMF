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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
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

	/****************************
	 * Internal static properties
	 ****************************/

	private static array $columns = ['id_search', 'id_topic', 'id_msg'];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v3_0\LogSearchResults();
		$existing_structure = $table->getCurrentStructure();

		$idx = $existing_structure['indexes']['primary'] ?? null;

		return $idx == null || array_intersect($idx['columns'], self::$columns) !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\LogSearchResults();
		Db::$db->remove_index('{db_prefix}' . $table->name, 'primary');

		$this->handleTimeout();

		Db::$db->add_index('{db_prefix}' . $table->name, ['type' => 'primary', 'columns' => self::$columns]);

		return true;
	}
}
