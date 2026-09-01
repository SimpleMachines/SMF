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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PermissionProfiles2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Migrating old board profiles to profile system';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private int $num_profiles = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		if (!empty(Db::$db->list_tables(false, Config::$db_prefix . 'permission_profiles'))) {
			$request = $this->query(
				'SELECT COUNT(*)
				FROM {db_prefix}permission_profiles',
				[],
			);

			list($this->num_profiles) = Db::$db->fetch_row($request);

			Db::$db->free_result($request);
		}

		return empty($this->num_profiles);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the tables are structured correctly.
		$table = new Schema\v2_0\PermissionProfiles();
		$table->normalize();

		$table = new Schema\v2_0\BoardPermissions();
		$table->normalize();

		// Everything starts off invalid.
		$this->query(
			'UPDATE {db_prefix}board_permissions
			SET id_profile = 0',
			[],
		);

		// Insert the default profile permissions.
		Db::$db->insert(
			method: '',
			table: '{db_prefix}permission_profiles',
			columns: [
				'id_profile' => 'int',
				'profile_name' => 'string-255',
			],
			data: [
				[1, 'default'],
				[2, 'no_polls'],
				[3, 'reply_only'],
				[4, 'read_only'],
			],
			keys: ['id_profile'],
		);

		// Update the default permissions, this is easy!
		$this->query(
			'UPDATE {db_prefix}board_permissions
			SET id_profile = 1
			WHERE id_board = 0',
			[],
		);

		// Load all the other permissions
		$request = $this->query(
			'SELECT id_board, id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = 0',
			[],
		);

		$all_perms = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$all_perms[$row['id_board']][$row['id_group']][$row['permission']] = $row['add_deny'];
		}

		Db::$db->free_result($request);

		// Now we have the profile profiles for this installation. We now need to go through each board and work out what the permission profile should be!
		$request = $this->query(
			'SELECT id_board, name, permission_mode
			FROM {db_prefix}boards',
			[],
		);

		$board_updates = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['name'] = addslashes($row['name']);

			// Is it a truly local permission board? If so this is a new profile!
			if ($row['permission_mode'] == 1) {
				Db::$db->insert(
					method: '',
					table: '{db_prefix}permission_profiles',
					columns: [
						'profile_name' => 'string-255',
					],
					data: [
						[$row['name']],
					],
					keys: ['id_profile'],
				);

				$board_updates[Db::$db->insert_id()][] = $row['id_board'];
			}
			// Otherwise, dear god, this is an old school "simple" permission...
			elseif ($row['permission_mode'] > 1 && $row['permission_mode'] < 5) {
				$board_updates[$row['permission_mode']][] = $row['id_board'];
			}
			// Otherwise this is easy. It becomes default.
			else {
			$board_updates[1][] = $row['id_board'];
			}
		}

		Db::$db->free_result($request);

		// Update the board tables.
		foreach ($board_updates as $profile => $boards) {
			if (empty($boards) || empty($profile)) {
				continue;
			}

			$boards = implode(',', $boards);

			$this->query(
				'UPDATE {db_prefix}boards
				SET id_profile = {int:profile}
				WHERE id_board IN ({array_int:boards})',
				[
					'profile' => $profile,
					'boards' => $boards,
				],
			);

			// If it's a custom profile then update this too.
			if ($profile > 4) {
				$this->query(
					'UPDATE {db_prefix}board_permissions
					SET id_profile = {int:profile}
					WHERE id_board IN ({array_int:boards})
						AND id_profile = 0',
					[
						'profile' => $profile,
						'boards' => $boards,
					],
				);
			}
		}

		// Just in case we have any random permissions that didn't have boards.
		$this->query(
			'DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = 0',
			[],
		);

		$this->handleTimeout();

		return true;
	}
}
