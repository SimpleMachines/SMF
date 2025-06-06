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

class PermissionProfiles3 extends MigrationBase
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
	private array $mod_permissions = [
		'moderate_board',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'poll_post',
		'poll_add_any',
		'poll_remove_any',
		'poll_view',
		'poll_vote',
		'poll_lock_any',
		'poll_edit_any',
		'report_any',
		'lock_own',
		'send_topic',
		'mark_any_notify',
		'mark_notify',
		'delete_own',
		'modify_own',
		'make_sticky',
		'lock_any',
		'remove_any',
		'move_any',
		'merge_any',
		'split_any',
		'delete_any',
		'modify_any',
		'approve_posts',
		'post_attachment',
		'view_attachments',
		'post_unapproved_replies_any',
		'post_unapproved_replies_own',
		'post_unapproved_attachments',
		'post_unapproved_topics',
	];

	/**
	 *
	 */
	private array $no_poll_reg = [
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'poll_view',
		'poll_vote',
		'report_any',
		'lock_own',
		'send_topic',
		'mark_any_notify',
		'mark_notify',
		'delete_own',
		'modify_own',
		'post_attachment',
		'view_attachments',
		'remove_own',
		'post_unapproved_replies_any',
		'post_unapproved_replies_own',
		'post_unapproved_attachments',
		'post_unapproved_topics',
	];

	/**
	 *
	 */
	private array $reply_only_reg = [
		'post_reply_own',
		'post_reply_any',
		'poll_view',
		'poll_vote',
		'report_any',
		'lock_own',
		'send_topic',
		'mark_any_notify',
		'mark_notify',
		'delete_own',
		'modify_own',
		'post_attachment',
		'view_attachments',
		'remove_own',
		'post_unapproved_replies_any',
		'post_unapproved_replies_own',
		'post_unapproved_attachments',
	];

	/**
	 *
	 */
	private array $read_only_reg = [
		'poll_view',
		'poll_vote',
		'report_any',
		'send_topic',
		'mark_any_notify',
		'mark_notify',
		'view_attachments',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table is structured correctly.
		$table = new Schema\v2_0\BoardPermissions();
		$table->normalize();

		// Clear all the current predefined profiles.
		$this->query(
			'DELETE FROM {db_prefix}board_permissions
			WHERE id_profile IN (2,3,4)',
			[],
		);

		// Get all the membergroups - cheating to use the fact id_group = 1 exists to get a group of 0.
		$request = $this->query(
			'SELECT IF(id_group = 1, 0, id_group) AS id_group
			FROM {db_prefix}membergroups
			WHERE id_group != 0
				AND min_posts = -1',
			[],
		);

		$inserts = [
			[-1, 2, 'poll_view'],
			[-1, 3, 'poll_view'],
			[-1, 4, 'poll_view'],
		];

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['id_group'] == 2 || $row['id_group'] == 3) {
				foreach ($this->mod_permissions as $permission) {
					$inserts[] = [$row['id_group'], 2, $permission];
					$inserts[] = [$row['id_group'], 3, $permission];
					$inserts[] = [$row['id_group'], 4, $permission];
				}
			} else {
				foreach ($this->no_poll_reg as $permission) {
					$inserts[] = [$row['id_group'], 2, $permission];
				}

				foreach ($reply_only_reg as $permission) {
					$inserts[] = [$row['id_group'], 3, $permission];
				}

				foreach ($read_only_reg as $permission) {
					$inserts[] = [$row['id_group'], 4, $permission];
				}
			}
		}

		Db::$db->free_result($request);

		Db::$db->insert(
			method: '',
			table: '{db_prefix}board_permissions',
			columns: [
				'id_group' => 'int',
				'id_profile' => 'int',
				'permission' => 'string-30',
			],
			data: $inserts,
			keys: ['id_profile'],
		);

		// Make sure admins and moderators don't inherit.
		$table = new Schema\v2_0\Membergroups();
		$table->normalize();

		$this->query(
			'UPDATE {db_prefix}membergroups
			SET id_parent = -2
			WHERE id_group IN ({array_int:groups})',
			[
				'groups' => [1, 3],
			],
		);

		// Delete old permission settings.
		Config::updateModSettings([
			'permission_enable_by_board' => null,
		]);

		$this->handleTimeout();

		return true;
	}
}
