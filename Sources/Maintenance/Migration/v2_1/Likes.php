<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Likes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for likes';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$tables = Db::$db->list_tables();

		return !in_array(Config::$db_prefix . 'user_likes', $tables);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$LikesTable = new \SMF\Db\Schema\v3_0\UserLikes();

		$tables = Db::$db->list_tables();

		// Creating draft table.
		if ($start <= 0 && !in_array(Config::$db_prefix . 'user_likes', $tables)) {
			$LikesTable->create();

			$this->handleTimeout(++$start);
		}

		// Adding likes column to the messages table. (May take a while)
		if ($start <= 1) {
			$MessagesTable = new \SMF\Db\Schema\v3_0\Messages();

			$existing_columns = Db::$db->list_columns('{db_prefix}' . $MessagesTable->name);

			foreach ($MessagesTable->columns as $column) {
				// Add the columns.
				if ($column->name === 'likes' && !in_array($column->name, $existing_columns)) {
					$MessagesTable->addColumn($column);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>