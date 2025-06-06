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
use SMF\Maintenance\Migration\MigrationBase;

class PostModeration extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding post moderation';

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
			'2.0',
			'<',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Note: there are some tables that need to be created, and some changes
		// that need to be made to other tables, but the table normalization
		// substeps will take care of that part.

		// Anyone who can currently edit posts we assume can approve them...
		$request = $this->query(
			'SELECT id_group, id_board, add_deny, permission
			FROM {db_prefix}board_permissions
			WHERE permission = {string:perm}',
			[
				'perm' => 'modify_any',
			],
		);

		$inserts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$inserts[] = [
				$row['id_group'],
				$row['id_board'],
				'approve_posts',
				$row['add_deny'],
			];
		}

		Db::$db->free_result($request);

		// Add moderation center permissions.
		if (!empty($inserts)) {
			Db::$db->insert(
				method: 'ignore',
				table: '{db_prefix}board_permissions',
				columns: [
					'id_group' => 'int',
					'id_board' => 'int',
					'permission' => 'string-30',
					'add_deny' => 'int',
				],
				data: $inserts,
				keys: [],
			);
		}

		$this->handleTimeout();

		return true;
	}
}
