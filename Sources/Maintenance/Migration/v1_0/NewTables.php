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

class NewTables extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Creating new tables and inserting default data';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Themes table.
		$table = new Schema\v1_0\Themes();

		if (!$table->create(if_exists: 'false')) {
			$table->alterColumn($table->columns['ID_MEMBER']);
			$table->alterColumn($table->columns['value']);
		}

		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}' . $table->name,
			columns: [
				'ID_MEMBER' => 'int',
				'ID_THEME' => 'int',
				'variable' => 'string',
				'value' => 'string',
			],
			data: [
				[0, 1, 'name', 'SMF Default Theme'],
				[0, 1, 'theme_url', Config::$boardurl . '/Themes/default'],
				[0, 1, 'images_url', Config::$boardurl . '/Themes/default/images'],
				[0, 1, 'theme_dir', Config::$boarddir . '/Themes/default'],
				[0, 1, 'allow_no_censored', 0],
				[0, 1, 'additional_options_collapsable', 1],
				[0, 2, 'name', 'Classic YaBB SE Theme'],
				[0, 2, 'theme_url', Config::$boardurl . '/Themes/classic'],
				[0, 2, 'images_url', Config::$boardurl . '/Themes/classic/images'],
				[0, 2, 'theme_dir', Config::$boarddir . '/Themes/classic'],
			],
			keys: [],
		);

		$this->handleTimeout();

		// Permissions table.
		$table = new Schema\v1_0\Permissions();

		if (!$table->create(if_exists: 'false')) {
			$table->addColumn($table->columns['addDeny']);
			$table->alterColumn($table->columns['permission']);
		}

		$this->query(
			'UPDATE IGNORE {db_prefix}permissions
			SET
				permission = REPLACE(permission, "profile_own_identity", "profile_identity_own"),
				permission = REPLACE(permission, "profile_any_identity", "profile_identity_any"),
				permission = REPLACE(permission, "profile_own_extra", "profile_extra_own"),
				permission = REPLACE(permission, "profile_any_extra", "profile_extra_any"),
				permission = REPLACE(permission, "profile_own_title", "profile_title_own"),
				permission = REPLACE(permission, "profile_any_title", "profile_title_any"),
				permission = REPLACE(permission, "im_read", "pm_read"),
				permission = REPLACE(permission, "im_send", "pm_send")',
		);

		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}' . $table->name,
			columns: [
				'ID_GROUP' => 'int',
				'permission' => 'string-30',
			],
			data: [
				[-1, 'search_posts'],
				[-1, 'calendar_view'],
				[-1, 'view_stats'],
				[-1, 'profile_view_any'],
				[2, 'calendar_post'],
				[2, 'calendar_edit_any'],
				[2, 'calendar_edit_own'],
			],
			keys: [],
		);

		$this->handleTimeout();

		// Board permissions table.
		$table = new Schema\v1_0\BoardPermissions();

		if (!$table->create(if_exists: 'false')) {
			$table->addColumn($table->columns['addDeny']);
			$table->alterColumn($table->columns['permission']);
		}

		Db::$db->insert(
			method: '',
			table: '{db_prefix}' . $table->name,
			columns: [
				'ID_GROUP' => 'int',
				'ID_BOARD' => 'int',
				'permission' => 'string-30',
			],
			data: [
				[-1, 0, 'poll_view'],
				[3, 0, 'make_sticky'],
				[3, 0, 'lock_any'],
				[3, 0, 'remove_any'],
				[3, 0, 'move_any'],
				[3, 0, 'merge_any'],
				[3, 0, 'split_any'],
				[3, 0, 'delete_any'],
				[3, 0, 'modify_any'],
				[2, 0, 'make_sticky'],
				[2, 0, 'lock_any'],
				[2, 0, 'remove_any'],
				[2, 0, 'move_any'],
				[2, 0, 'merge_any'],
				[2, 0, 'split_any'],
				[2, 0, 'delete_any'],
				[2, 0, 'modify_any'],
				[2, 0, 'poll_lock_any'],
				[2, 0, 'poll_lock_any'],
				[2, 0, 'poll_add_any'],
				[2, 0, 'poll_remove_any'],
				[2, 0, 'poll_remove_any'],
			],
			keys: [],
		);

		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}' . $table->name,
			columns: [
				'ID_GROUP' => 'int',
				'ID_BOARD' => 'int',
				'permission' => 'string-30',
			],
			data: [
				[3, 0, 'moderate_board'],
				[2, 0, 'moderate_board'],
			],
			keys: [],
		);

		$this->handleTimeout();

		// Other tables.
		$table = new Schema\v1_0\Moderators();
		$table->create();

		$table = new Schema\v1_0\Attachments();
		$table->create();

		$table = new Schema\v1_0\LogNotify();
		$table->create();

		$table = new Schema\v1_0\LogPolls();
		$table->create();

		$table = new Schema\v1_0\LogActions();
		$table->create();

		$table = new Schema\v1_0\PollChoices();
		$table->create();

		$table = new Schema\v1_0\Smileys();
		$table->create();
		$table->populate();

		$table = new Schema\v1_0\LogSearch();
		$table->drop();
		$table->create();

		$table = new Schema\v1_0\Sessions();
		$table->drop();
		$table->create();

		$table = new Schema\v1_0\Settings();
		$table->dropIndex($this->indexes['primary']);
		$table->addIndex($this->indexes['primary']);

		$table = new Schema\v1_0\ImRecipients();
		$table->create();

		$this->handleTimeout();

		return true;
	}
}
