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

class SmilBoardPermissionsViews extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update permissions system board_permissions_view';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		// Create table board_permissions_view
		if ($start <= 0) {
			$table = new \SMF\Maintenance\Database\Schema\v2_1\BoardPermissionsView();
			$existing_tables = Db::$db->list_tables();

			if (!in_array(Config::$db_prefix . $table->name, $existing_tables)) {
				$table->create();
			}

			$this->handleTimeout(++$start);
		}

		// if one of source col is missing skip this step.
		$table_columns = Db::$db->list_columns('{db_prefix}membergroups');
		$table_columns2 = Db::$db->list_columns('{db_prefix}boards');

		if (!in_array('id_group', $table_columns) || !in_array('member_groups', $table_columns2) || !in_array('deny_member_groups', $table_columns2)) {
			return true;
		}

		if ($start <= 1) {
			$this->query('', 'TRUNCATE {db_prefix}board_permissions_view');

			$this->handleTimeout(++$start);
		}

		// Update board_permissions_view table with membergroups
		if ($start <= 2) {
			$inserts = [];

			$request = $this->query('', '
				SELECT id_board, mg.id_group, 0
				FROM {db_prefix}boards b
				JOIN {$db_prefix}membergroups mg ON (FIND_IN_SET(mg.id_group, b.member_groups) != 0)');

			while ($row = Db::$db->fetch_row($request)) {
				$inserts[] = $row;
			}
			Db::$db->free_result($request);

			Db::$db->insert(
				'ignore',
				'{db_prefix}board_permissions_view',
				['id_board' => 'int', 'id_group' => 'int', 'deny' => 'int'],
				$inserts,
				['id_board', 'id_group'],
			);

			$this->handleTimeout(++$start);
		}

		// Update board_permissions_view table with -1
		if ($start <= 3) {
			$inserts = [];

			$request = $this->query('', '
				SELECT id_board, -1, 0
				FROM {db_prefix}boards b
				WHERE (FIND_IN_SET(-1, b.member_groups) != 0)');

			while ($row = Db::$db->fetch_row($request)) {
				$inserts[] = $row;
			}
			Db::$db->free_result($request);

			Db::$db->insert(
				'ignore',
				'{db_prefix}board_permissions_view',
				['id_board' => 'int', 'id_group' => 'int', 'deny' => 'int'],
				$inserts,
				['id_board', 'id_group'],
			);

			$this->handleTimeout(++$start);
		}

		// Update board_permissions_view table with 0
		if ($start <= 4) {
			$inserts = [];

			$request = $this->query('', '
				SELECT id_board, 0, 0
				FROM {db_prefix}boards b
				WHERE (FIND_IN_SET(0, b.member_groups) != 0)');

			while ($row = Db::$db->fetch_row($request)) {
				$inserts[] = $row;
			}
			Db::$db->free_result($request);

			Db::$db->insert(
				'ignore',
				'{db_prefix}board_permissions_view',
				['id_board' => 'int', 'id_group' => 'int', 'deny' => 'int'],
				$inserts,
				['id_board', 'id_group'],
			);

			$this->handleTimeout(++$start);
		}

		// Update deny board_permissions_view table with membergroups
		if ($start <= 5) {
			$inserts = [];

			$request = $this->query('', '
				SELECT id_board, mg.id_group, 1
				FROM {db_prefix}boards b
				JOIN {db_prefix}membergroups mg ON (FIND_IN_SET(mg.id_group, b.deny_member_groups) != 0)');

			while ($row = Db::$db->fetch_row($request)) {
				$inserts[] = $row;
			}
			Db::$db->free_result($request);

			Db::$db->insert(
				'ignore',
				'{db_prefix}board_permissions_view',
				['id_board' => 'int', 'id_group' => 'int', 'deny' => 'int'],
				$inserts,
				['id_board', 'id_group'],
			);

			$this->handleTimeout(++$start);
		}

		// Update deny board_permissions_view table with -1
		if ($start <= 5) {
			$inserts = [];

			$request = $this->query('', '
				SELECT id_board, -1, 1
				FROM {db_prefix}boards b
				WHERE (FIND_IN_SET(-1, b.deny_member_groups) != 0)');

			while ($row = Db::$db->fetch_row($request)) {
				$inserts[] = $row;
			}
			Db::$db->free_result($request);

			Db::$db->insert(
				'ignore',
				'{db_prefix}board_permissions_view',
				['id_board' => 'int', 'id_group' => 'int', 'deny' => 'int'],
				$inserts,
				['id_board', 'id_group'],
			);

			$this->handleTimeout(++$start);
		}

		// Update deny board_permissions_view table with 0
		if ($start <= 6) {
			$inserts = [];

			$request = $this->query('', '
				SELECT id_board, 0, 1
				FROM {db_prefix}boards b
				WHERE (FIND_IN_SET(0, b.deny_member_groups) != 0)');

			while ($row = Db::$db->fetch_row($request)) {
				$inserts[] = $row;
			}
			Db::$db->free_result($request);

			Db::$db->insert(
				'ignore',
				'{db_prefix}board_permissions_view',
				['id_board' => 'int', 'id_group' => 'int', 'deny' => 'int'],
				$inserts,
				['id_board', 'id_group'],
			);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>