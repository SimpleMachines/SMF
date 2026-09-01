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

use SMF\Maintenance\Migration\MigrationBase;

class GuestPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Removing illegal guest permissions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Removing all guest deny permissions.
		$this->query(
			'DELETE FROM {db_prefix}permissions
			WHERE ID_GROUP = -1
				AND addDeny = 0',
		);

		$this->query(
			'DELETE FROM {db_prefix}board_permissions
			WHERE ID_GROUP = -1
				AND addDeny = 0',
		);

		// Removing guest admin permissions (if any).
		$this->query(
			'DELETE FROM {db_prefix}permissions
			WHERE ID_GROUP = -1
				AND permission IN ({array_string:perms})',
			[
				'perms' => ['admin_forum', 'manage_boards', 'manage_attachments', 'manage_smileys', 'edit_news', 'moderate_forum', 'manage_membergroups', 'manage_permissions', 'manage_bans', 'send_mail'],
			],
		);

		$this->query(
			'DELETE FROM {db_prefix}board_permissions
			WHERE ID_GROUP = -1
				AND permission IN ({array_string:perms})',
			[
				'perms' => ['admin_forum', 'manage_boards', 'manage_attachments', 'manage_smileys', 'edit_news', 'moderate_forum', 'manage_membergroups', 'manage_permissions', 'manage_bans', 'send_mail'],
			],
		);

		$this->handleTimeout();

		return true;
	}
}
