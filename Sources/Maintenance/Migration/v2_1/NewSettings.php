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
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Security;

class NewSettings extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding new settings';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $newSettings = [
		'topic_move_any' => 1,
		'enable_ajax_alerts' => 1,
		'alerts_auto_purge' => 30,
		'minimize_files' => 1,
		'additional_options_collapsable' => 1,
		'defaultMaxListItems' => 15,
		'loginHistoryDays' => 30,
		'securityDisable_moderate' => 1,
		'httponlyCookies' => 1,
		'samesiteCookies' => 'lax',
		'export_expiry' => 7,
		'export_min_diskspace_pct' => 5,
		'export_rate' => 250,
		'mark_read_beyond' => 90,
		'mark_read_delete_beyond' => 365,
		'mark_read_max_users' => 500,
		'enableThemes' => 1,
		'theme_guests' => 1
	];

	protected array $removedSettings = [
		'enableStickyTopics',
		'guest_hideContacts',
		'notify_new_registration',
		'attachmentEncryptFilenames',
		'hotTopicPosts',
		'hotTopicVeryPosts',
		'fixLongWords',
		'admin_feature',
		'log_ban_hits',
		'topbottomEnable',
		'simpleSearch',
		'enableVBStyleLogin',
		'admin_bbc',
		'enable_unwatch',
		'cache_memcached',
		'cache_enable',
		'cookie_no_auth_secret'
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
		$newSettings = [];

		// Copying the current package backup setting.
		if (!isset(Config::$modSettings['package_make_full_backups']) && isset(Config::$modSettings['package_make_backups'])) {
			$newSettings['package_make_full_backups'] = Config::$modSettings['package_make_backups'];
		}

		// Copying the current "allow users to disable word censor" setting.
		if (!isset(Config::$modSettings['allow_no_censored'])) {
			$request = $this->query(
				'',
				'
				SELECT value
				FROM {db_prefix}themes
				WHERE variable={string:allow_no_censored}
				AND id_theme = 1 OR id_theme = {int:default_theme}',
				[
					'allow_no_censored' => 'allow_no_censored',
					'default_theme' => Config::$modSettings['theme_default'],
				],
			);

			// Is it set for either "default" or the one they've set as default?
			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['value'] == 1) {
					$newSettings['allow_no_censored'] = 1;

					// Don't do this twice...
					break;
				}
			}
		}

		// Add all any settings to the settings table.
		foreach ($this->newSettings as $key => $default) {
			if (!isset(Config::$modSettings[$key])) {
				$newSettings[$key] = $default;
			}
		}

		// Enable some settings we ripped from Theme settings.
		$ripped_settings = ['show_modify', 'show_user_images', 'show_blurb', 'show_profile_buttons', 'subject_toggle', 'hide_post_group'];

		$request = Db::$db->query(
			'',
			'
			SELECT variable, value
			FROM {db_prefix}themes
			WHERE variable IN({array_string:ripped_settings})
				AND id_member = 0
				AND id_theme = 1',
			[
				'ripped_settings' => $ripped_settings,
			],
		);

		$inserts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset(Config::$modSettings[$row['variable']])) {
				$newSettings[$row['variable']] = $row['value'];
			}
		}
		Db::$db->free_result($request);

		// Calculate appropriate hash cost.
		if (!isset(Config::$modSettings['bcrypt_hash_cost'])) {
			$newSettings['bcrypt_hash_cost'] = Security::hashBenchmark();
		}

		// Adding new profile data export settings.
		if (!isset(Config::$modSettings['export_dir'])) {
			$newSettings['export_dir'] = Config::$boarddir . '/exports';
		}

		// Deleting integration hooks.
		foreach (Config::$modSettings as $key => $val) {
			if (substr($key, 0, strlen('integrate_'))  == 'integrate_') {
				$newSettings[$key] = null;
			}
		}

		// Fixing a deprecated option.
		if (isset(Config::$modSettings['avatar_action_too_large']) && (Config::$modSettings['avatar_action_too_large'] == 'option_html_resize' || Config::$modSettings['avatar_action_too_large'] == 'option_js_resize')) {
			$newSettings['avatar_action_too_large'] = 'option_css_resize';
		}

		// Cleaning up the old Core Features page.
		if (isset(Config::$modSettings['admin_features'])) {
			$admin_features = explode(',', Config::$modSettings['admin_features']);

			// cd = calendar, should also have set cal_enabled already
			// cp = custom profile fields, which already has several fields that cover tracking
			// ps = paid subs, should also have set paid_enabled already
			// rg = reports generation, which is now permanently on
			// sp = spider tracking, should also have set spider_mode already
			// w = warning system, which will be covered with warning_settings

			// The rest we have to deal with manually.
			// Moderation log - modlog_enabled itself should be set but we have others now
			if (in_array('ml', $admin_features))
			{
				$newSettings[] = array('adminlog_enabled', '1');
				$newSettings[] = array('userlog_enabled', '1');
			}

			// Post moderation
			if (in_array('pm', $admin_features))
			{
				$newSettings[] = array('postmod_active', '1');
			}
		}

		foreach ($this->removedSettings as $key) {
			$newSettings[$key] = null;
		}

		// Renamed setting.
		if (isset(Config::$modSettings['allow_sm_stats'])) {
			$newSettings['sm_stats_key'] = Config::$modSettings['allow_sm_stats'];
			$newSettings['allow_sm_stats'] = null;
			$newSettings['enable_sm_stats'] = 1;
		}
	
		Config::updateModSettings($newSettings);

		return true;
	}
}

?>