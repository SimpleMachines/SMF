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

class OldBoardPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Removing obsolete board permissions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Deleting some very old permissions.
		$request = $this->query(
			'DELETE FROM {db_prefix}board_permissions
			WHERE permission IN ({array_string:old_perms})',
			[
				'old_perms' => ['view_threads', 'poll_delete_own', 'poll_delete_any', 'profile_edit_own', 'profile_edit_any'],
			],
		);

		$this->handleTimeout();

		return true;
	}
}
