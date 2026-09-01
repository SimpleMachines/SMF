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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class LocalPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading by-board permissions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_1\Boards();
		$table->alterColumn($table->columns['permission_mode'], 'use_local_permissions');

		if (!isset(Config::$modSettings['permission_enable_by_board'])) {
			// Enable by-board permissions if there's >= 1 local permission board.
			$request = $this->query(
				'SELECT ID_BOARD
				FROM {db_prefix}boards
				WHERE permission_mode = 1
				LIMIT 1',
			);

			$enable_by_board = Db::$db->num_rows($request);

			Db::$db->free_result($request);

			Config::updateModSettings([
				'permission_enable_by_board' => $enable_by_board,
			]);
		}

		$this->handleTimeout();

		return true;
	}
}
