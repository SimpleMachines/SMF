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

class Membergroups1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting membergroups, part 1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Membergroups();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'minPosts') === [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		Db::$db->rename_table('{db_prefix}membergroups', '{db_prefix}old_membergroups');

		$table = new Schema\v1_0\Membergroups();
		$table->create();

		// Migrate the administrator group.
		Db::$db->query(
			'INSERT INTO {db_prefix}membergroups
				(ID_GROUP, groupName, onlineColor, minPosts, stars)
			SELECT {int:new_id}, membergroup, {string:color}, -1, {string:icons}
			FROM {db_prefix}old_membergroups
			WHERE ID_GROUP = {int:old_id}',
			[
				'old_id' => 1,
				'new_id' => 1,
				'color' => '#FF0000',
				'icons' => '5#staradmin.gif',
			],
		);

		// Migrate the global moderator group.
		Db::$db->query(
			'INSERT INTO {db_prefix}membergroups
				(ID_GROUP, groupName, onlineColor, minPosts, stars)
			SELECT {int:new_id}, membergroup, {string:color}, -1, {string:icons}
			FROM {db_prefix}old_membergroups
			WHERE ID_GROUP = {int:old_id}',
			[
				'old_id' => 8,
				'new_id' => 2,
				'color' => '#0000FF',
				'icons' => '5#stargmod.gif',
			],
		);

		// Migrate the local moderator group.
		Db::$db->query(
			'INSERT INTO {db_prefix}membergroups
				(ID_GROUP, groupName, onlineColor, minPosts, stars)
			SELECT {int:new_id}, membergroup, {string:color}, -1, {string:icons}
			FROM {db_prefix}old_membergroups
			WHERE ID_GROUP = {int:old_id}',
			[
				'old_id' => 2,
				'new_id' => 3,
				'color' => '',
				'icons' => '5#starmod.gif',
			],
		);

		// Migrate the post-count groups.
		// In YaBB SE, the minimum post values for post-count groups were stored
		// as global variables in Settings.php.
		Db::$db->query(
			'INSERT INTO {db_prefix}membergroups
				(ID_GROUP, groupName, onlineColor, minPosts, stars)
			SELECT
				ID_GROUP + 1,
				membergroup,
				{string:color},
				CASE ID_GROUP
					WHEN 3 THEN 0
					WHEN 4 THEN {int:JrPostNum}
					WHEN 5 THEN {int:FullPostNum}
					WHEN 6 THEN {int:SrPostNum}
					WHEN 7 THEN {int:GodPostNum}
				END,
				CONCAT(ID_GROUP - 2, "#star.gif")
			FROM {db_prefix}old_membergroups
			WHERE ID_GROUP IN {array_int:old_groups}',
			[
				'color' => '',
				'JrPostNum' => (int) ($GLOBALS['JrPostNum'] ?? 50),
				'FullPostNum' => (int) ($GLOBALS['FullPostNum'] ?? 100),
				'SrPostNum' => (int) ($GLOBALS['SrPostNum'] ?? 250),
				'GodPostNum' => (int) ($GLOBALS['GodPostNum'] ?? 500),
				'old_groups' => [3, 4, 5, 6, 7],
			],
		);

		// Migrate any custom groups.
		Db::$db->query(
			'INSERT INTO {db_prefix}membergroups
				(ID_GROUP, groupName, onlineColor, minPosts, stars)
			SELECT ID_GROUP, membergroup, {string:color}, -1, {string:icons}
			FROM {db_prefix}old_membergroups
			WHERE ID_GROUP > {int:eight}',
			[
				'eight' => 8,
				'color' => '',
				'icons' => '',
			],
		);

		// Drop the old table.
		Db::$db->drop_table('{db_prefix}old_membergroups');

		// Add new permissions for the membergroups.
		$permissions = [
			'view_mlist',
			'search_posts',
			'profile_view_own',
			'profile_view_any',
			'pm_read',
			'pm_send',
			'calendar_view',
			'view_stats',
			'who_view',
			'profile_identity_own',
			'profile_extra_own',
			'profile_remote_avatar',
			'profile_remove_own',
		];

		foreach ($permissions as $perm) {
			$this->query(
				'INSERT INTO {db_prefix}permissions
					(ID_GROUP, permission)
				SELECT IF(ID_GROUP = 1, 0, ID_GROUP), {string:perm}
				FROM {db_prefix}membergroups
				WHERE ID_GROUP != 3
					AND minPosts = -1',
				[
					'perm' => $perm,
				],
			);
		}

		$board_permissions = [
			'remove_own',
			'lock_own',
			'mark_any_notify',
			'mark_notify',
			'modify_own',
			'poll_add_own',
			'poll_edit_own',
			'poll_lock_own',
			'poll_post',
			'poll_view',
			'poll_vote',
			'post_attachment',
			'post_new',
			'post_reply_any',
			'post_reply_own',
			'delete_own',
			'report_any',
			'send_topic',
			'view_attachments',
		];

		foreach ($board_permissions as $perm) {
			$this->query(
				'INSERT INTO {db_prefix}board_permissions
					(ID_GROUP, permission)
				SELECT IF(ID_GROUP = 1, 0, ID_GROUP), {string:perm}
				FROM {db_prefix}membergroups
				WHERE minPosts = -1',
				[
					'perm' => $perm,
				],
			);
		}

		// Done.
		$this->handleTimeout();

		return true;
	}
}
