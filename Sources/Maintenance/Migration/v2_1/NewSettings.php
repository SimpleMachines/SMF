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
		foreach ($newSettings as $key => $default) {
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

		Config::updateModSettings($newSettings);

		return true;
	}
}

?>