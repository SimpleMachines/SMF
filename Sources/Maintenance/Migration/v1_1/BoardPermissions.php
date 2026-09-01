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
use SMF\Maintenance\Migration\MigrationBase;

class BoardPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Renaming board permissions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return version_compare(
			str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
			'1.1',
			'<',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Step 1
		$this->query(
			'UPDATE {db_prefix}board_permissions
			SET
				permission = REPLACE(
					permission,
					{literal:remove_replies},
					{literal:delete_replies},
				),
				permission = REPLACE(
					permission,
					{literal:remove_own},
					{literal:delete2_own},
				),
				permission = REPLACE(
					permission,
					{literal:remove_any},
					{literal:delete2_any},
				)',
		);

		// Step 2
		$this->query(
			'UPDATE {db_prefix}board_permissions
			SET
				permission = REPLACE(
					permission,
					{literal:delete_own},
					{literal:remove_own},
				),
				permission = REPLACE(
					permission,
					{literal:delete_any},
					{literal:remove_any},
				)',
		);

		// Step 3
		$this->query(
			'UPDATE {db_prefix}board_permissions
			SET
				permission = REPLACE(
					permission,
					{literal:delete2_own},
					{literal:delete_own},
				),
				permission = REPLACE(
					permission,
					{literal:delete2_any},
					{literal:delete_any},
				)',
		);

		$this->handleTimeout();

		return true;
	}
}
