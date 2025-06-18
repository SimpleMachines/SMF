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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class Themes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting theme settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// A bunch of theme settings that were global settings in YaBB SE.
		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}themes',
			columns: [
				'id_member' => 'int',
				'id_theme' => 'int',
				'variable' => 'string',
				'value' => 'string',
			],
			data: [
				[0, 1, 'show_latest_member',  $GLOBALS['showlatestmember'] ?? 0],
				[0, 1, 'show_bbc',  $GLOBALS['showyabbcbutt'] ?? $GLOBALS['showbbcbutt'] ?? 0],
				[0, 1, 'show_modify',  $GLOBALS['showmodify'] ?? 0],
				[0, 1, 'show_user_images',  $GLOBALS['showuserpic'] ?? 0],
				[0, 1, 'show_blurb',  $GLOBALS['showusertext'] ?? 0],
				[0, 1, 'show_gender',  $GLOBALS['showgenderimage'] ?? 0],
				[0, 1, 'show_newsfader',  $GLOBALS['shownewsfader'] ?? 0],
				[0, 1, 'display_recent_bar',  $GLOBALS['Show_RecentBar'] ?? 0],
				[0, 1, 'show_member_bar',  $GLOBALS['Show_MemberBar'] ?? 0],
				[0, 1, 'linktree_link',  $GLOBALS['curposlinks'] ?? 0],
				[0, 1, 'show_profile_buttons',  $GLOBALS['profilebutton'] ?? 0],
				[0, 1, 'show_mark_read',  $GLOBALS['showmarkread'] ?? 0],
				[0, 1, 'show_board_desc',  $GLOBALS['ShowBDescrip'] ?? 0],
				[0, 1, 'newsfader_time',  $GLOBALS['fadertime'] ?? 0],
				[0, 1, 'use_image_buttons',  empty($GLOBALS['MenuType']) ? 1 : 0],
				[0, 1, 'enable_news',  $GLOBALS['enable_news'] ?? 0],
				[0, 1, 'linktree_inline',  Config::$modSettings['enableInlineLinks'] ?? 0],
				[0, 1, 'return_to_post',  Config::$modSettings['returnToPost'] ?? 0],
			],
			keys: ['id_member', 'id_theme', 'variable'],
		);

		// This theme setting changed both its name and the meaning of its values.
		$request = $this->query(
			'SELECT ID_THEME, IF(value = {literal:2}, 5, value) AS value
			FROM {db_prefix}themes
			WHERE variable = {string:var}',
			[
				'var' => 'display_recent_bar',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$inserts[] = [$row['ID_THEME'], 'number_recent_posts', $row['value']];
		}

		Db::$db->free_result($request);

		if (!empty($inserts)) {
			Db::$db->insert(
				method: 'ignore',
				table: '{db_prefix}themes',
				columns: [
					'id_theme' => 'int',
					'variable' => 'string',
					'value' => 'string',
				],
				data: $inserts,
				keys: ['id_member', 'id_theme', 'variable'],
			);
		}

		$this->query(
			'DELETE FROM {db_prefix}themes
			WHERE variable = {string:var}',
			[
				'var' => 'display_recent_bar',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
