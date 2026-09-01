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

class DenyPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading "deny" permissions.';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return !isset(Config::$modSettings['permission_enable_deny']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Only disable if no deny permissions are used.
		$request = $this->query(
			'SELECT permission
			FROM {db_prefix}permissions
			WHERE addDeny = 0
			LIMIT 1',
		);

		$disable_deny_permissions = Db::$db->num_rows($request) == 0;

		Db::$db->free_result($request);

		// Still wanna disable deny permissions? Check board permissions.
		if ($disable_deny_permissions) {
			$request = $this->query(
				'SELECT permission
				FROM {db_prefix}board_permissions
				WHERE addDeny = 0
				LIMIT 1',
			);

			$disable_deny_permissions &= Db::$db->num_rows($request) == 0;

			Db::$db->free_result($request);
		}

		// Update the setting.
		Config::updatedModSettings([
			'permission_enable_postgroups' => $disable_deny_permissions ? 0 : 1,
		]);

		$this->handleTimeout();

		return true;
	}
}
