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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class AnnouncementPermissions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting announcement permissions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Boards();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'notifyAnnouncements') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$conversions = [
			'moderate_forum' => ['manage_membergroups', 'manage_bans'],
			'admin_forum' => ['manage_permissions'],
			'edit_forum' => ['manage_boards', 'manage_smileys', 'manage_attachments'],
		];

		foreach ($conversions as $original_permission => $new_permissions) {
			$inserts = [];

			$result = $this->query(
				'SELECT ID_GROUP, addDeny
				FROM {db_prefix}permissions
				WHERE permission = {string:perm}',
				[
					'perm' => $original_permission,
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				foreach ($new_permissions as $new_perm) {
					$inserts[] = [
						$new_perm,
						$row['ID_GROUP'],
						$row['addDeny'],
					];
				}
			}

			Db::$db->free_result($result);

			if (!empty($inserts)) {
				Db::$db->insert(
					method: 'ignore',
					table: '{db_prefix}permissions',
					columns: [
						'permission' => 'string',
						'ID_GROUP' => 'int',
						'addDeny' => 'int',
					],
					data: $inserts,
					keys: [],
				);
			}
		}

		$this->query(
			'DELETE FROM {db_prefix}permissions
			WHERE permission = {string:perm}',
			[
				'perm' => 'edit_forum',
			],
		);

		$table = new Schema\v1_0\Boards();
		$table->dropColumn('notifyAnnouncements');

		$this->handleTimeout();

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 *
	 */
	private function getMemberGroups(): array
	{
		$request = $this->query(
			'SELECT groupName, ID_GROUP
			FROM {db_prefix}membergroups
			WHERE ID_GROUP = 1 OR ID_GROUP > 7',
		);

		if (!$request || Db::$db->num_rows($request) === 0) {
			$request = $this->query(
				'SELECT membergroup, ID_GROUP
				FROM {db_prefix}membergroups
				WHERE ID_GROUP = 1 OR ID_GROUP > 7',
			);
		}

		while ($row = Db::$db->fetch_row($request)) {
			$member_groups[trim($row[0])] = (int) $row[1];
		}

		Db::$db->free_result($request);

		return $member_groups;
	}
}
