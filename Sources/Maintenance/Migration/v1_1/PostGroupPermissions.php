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
use SMF\Maintenance\Migration\MigrationBase;

class PostGroupPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading post based group permissions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return !isset(Config::$modSettings['permission_enable_postgroups']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT ID_GROUP
			FROM {db_prefix}membergroups
			WHERE minPosts != -1',
		);

		list($groups) = array_map(
			fn($row) => $row['ID_GROUP'],
			Db::$db->fetch_all($request),
		);

		Db::$db->free_result($request);

		// Only disable if no post group permissions are used.
		$disable_postgroup_permissions = true;

		$request = $this->query(
			'SELECT p.permission
			FROM {db_prefix}permissions AS p
			WHERE p.ID_GROUP IN ({array_int:groups})
			LIMIT 1',
			[
				'groups' => $groups,
			],
		);

		$disable_postgroup_permissions &= Db::$db->num_rows($request) == 0;

		Db::$db->free_result($request);

		// Still wanna disable postgroup permissions? Check board permissions.
		if ($disable_postgroup_permissions) {
			$request = $this->query(
				'SELECT bp.permission
				FROM {db_prefix}board_permissions AS bp
				WHERE bp.ID_GROUP IN ({array_int:groups})
				LIMIT 1',
				[
					'groups' => $groups,
				],
			);

			$disable_postgroup_permissions &= Db::$db->num_rows($request) == 0;

			Db::$db->free_result($request);
		}

		// Update the setting.
		Config::updatedModSettings([
			'permission_enable_postgroups' => $disable_postgroup_permissions ? 0 : 1,
		]);

		$this->handleTimeout();

		return true;
	}
}
