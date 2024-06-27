<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class ThemeSettings extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Updating Theme settings';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $updatedThemeSettings = [
		'newsfader_time' => '3000',
	];

	protected array $removedThemeSettings = [
		'show_board_desc',
		'display_quick_reply',
		'show_mark_read',
		'show_member_bar',
		'linktree_link',
		'show_bbc',
		'additional_options_collapsable',
		'subject_toggle',
		'show_modify',
		'show_profile_buttons',
		'show_user_images',
		'show_blurb',
		'show_gender',
		'hide_post_group',
		'drafts_autosave_enabled',
		'forum_width',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		if ($start === 0) {

			$this->query(
				'',
				'UPDATE {db_prefix}themes
				SET value = {string:new_theme_name}
				WHERE value LIKE {string:old_theme_name}',
				[
					'new_theme_name' => 'SMF Default Theme - Curve2',
					'old_theme_name' => 'SMF Default Theme%',
				],
			);

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			foreach ($this->updatedThemeSettings as $key => $value) {
				$this->query(
					'',
					'UPDATE {db_prefix}themes
					SET value = {string:value}
					WHERE value = {string:key}',
					[
						'value' => $value,
						'key' => $key,
					],
				);
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			$this->query(
				'',
				'UPDATE {db_prefix}boards
				SET id_theme = 0',
			);

			$this->handleTimeout(++$start);
		}

		if ($start <= 3) {
			$this->query(
				'',
				'UPDATE {db_prefix}members
				SET id_theme = 0',
			);

			$this->handleTimeout(++$start);
		}

		if ($start <= 4) {
			// Fetch list of theme directories
			$request = $this->query(
				'',
				'SELECT id_theme, variable, value
				FROM {db_prefix}themes
				WHERE variable = {string:theme_dir}
					AND id_theme != {int:default_theme};',
				[
					'default_theme' => 1,
					'theme_dir' => 'theme_dir',
				],
			);

			// Check which themes exist in the filesystem & save off their IDs
			// Don't delete default theme(start with 1 in the array), & make sure to delete old core theme
			$known_themes = ['1'];
			$core_dir = Config::$boarddir . '/Themes/core';

			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['value'] != $core_dir && is_dir($row['value'])) {
					$known_themes[] = $row['id_theme'];
				}
			}

			// Cleanup unused theme settings
			$this->query(
				'',
				'DELETE FROM {db_prefix}themes
				WHERE id_theme NOT IN ({array_int:known_themes});',
				[
					'known_themes' => $known_themes,
				],
			);

			// Set knownThemes
			$known_themes = implode(',', $known_themes);
			$this->query(
				'',
				'UPDATE {db_prefix}settings
				SET value = {string:known_themes}
				WHERE variable = {string:known_theme_str};',
				[
					'known_theme_str' => 'knownThemes',
					'known_themes' => $known_themes,
				],
			);

			$this->handleTimeout(++$start);
		}

		if ($start <= 5) {
			$this->query(
				'',
				'DELETE FROM {db_prefix}themes
				WHERE variable NOT IN ({array_int:removed_settings});',
				[
					'removed_settings' => $this->removedThemeSettings,
				],
			);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>