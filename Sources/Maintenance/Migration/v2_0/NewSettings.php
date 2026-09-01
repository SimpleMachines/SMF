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

class NewSettings extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding new forum settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Don't call Config::reloadModSettings(), because we only want the
		// plain values without any other stuff happening.
		$request = $this->query(
			'SELECT variable, value
			FROM {db_prefix}settings',
			[],
		);

		foreach (Db::$db->fetch_all($request) as $row) {
			Config::$modSettings[$row['variable']] = $row['value'];
		}

		Db::$db->free_result($request);

		// Resetting settings_updated.
		Config::updateModSettings([
			'settings_updated' => '0',
			'last_mod_report_action' => '0',
			'search_floodcontrol_time' => '5',
			'next_task_time' => time(),
		]);

		// Changing stats settings.
		$request = $this->query(
			'SELECT value
			FROM {db_prefix}themes
			WHERE variable = {string:var}',
			[
				'var' => 'show_sp1_info',
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			$this->query(
				'DELETE FROM {db_prefix}themes
				WHERE variable = {string:var}',
				[
					'var' => 'show_stats_index',
				],
			);

			$this->query(
				'UPDATE {db_prefix}themes
				SET variable = {string:new}
				WHERE variable = {string:old}',
				[
					'new' => 'show_stats_index',
					'old' => 'show_sp1_info',
				],
			);
		}

		Db::$db->free_result($request);

		$this->query(
			'DELETE FROM {db_prefix}themes
			WHERE variable = {string:var}',
			[
				'var' => 'show_sp1_info',
			],
		);

		// Enable cache if upgrading from 2.0 Beta 1 and lower.
		if (
			version_compare(
				str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
				'2.0.beta.1',
				'<=',
			)
		) {
			Config::updateModSettings([
				'cache_enable' => '1',
			]);
		}

		// Changing visual verification setting.
		if (isset(Config::$modSettings['disable_visual_verification'])) {
			$vv_type = Config::$modSettings['disable_visual_verification'] == 4 ? 5 : Config::$modSettings['disable_visual_verification'];

			Config::updateModSettings([
				'visual_verification_type' => $vv_type,
				'disable_visual_verification' => null,
			]);
		}

		// Changing visual verification setting, again.
		if (!isset(Config::$modSettings['reg_verification'])) {
			$vv_type = !isset(Config::$modSettings['visual_verification_type']) ? 3 : (!empty(Config::$modSettings['visual_verification_type']) ? Config::$modSettings['visual_verification_type'] - 1 : Config::$modSettings['visual_verification_type']);

			Config::updateModSettings([
				'visual_verification_type' => $vv_type,
				'reg_verification' => !empty($vv_type) ? 1 : 0,
			]);
		}

		Config::updateModSettings([
			// Changing default personal text setting.
			'default_personal_text' => Config::$modSettings['default_personalText'] ?? '',
			'default_personalText' => null,
			// Removing allow hide email setting.
			'allow_hideEmail' => null,
			'allow_hide_email' => null,
		]);

		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}themes',
			columns: [
				'id_theme' => 'int',
				'variable' => 'string-255',
				'value' => 'string-65535',
			],
			data: [
				// Ensuring stats index setting present...
				[1, 'show_stats_index', '0'],
				// Ensuring forum width setting present...
				[1, 'forum_width', '90%'],
			],
			keys: [],
		);

		// Replacing old calendar settings.
		// Only try it if one of the "new" settings doesn't yet exist.
		if (
			!isset(Config::$modSettings['cal_showholidays'])
			|| !isset(Config::$modSettings['cal_showbdays'])
			|| !isset(Config::$modSettings['cal_showevents'])
		) {
			// Default to just the calendar setting.
			Config::updateModSettings([
				'cal_showholidays' => (int) !empty(Config::$modSettings['cal_showholidaysoncalendar']),
				'cal_showbdays' => (int) !empty(Config::$modSettings['cal_showbdaysoncalendar']),
				'cal_showevents' => (int) !empty(Config::$modSettings['cal_showeventsoncalendar']),
			]);

			// Then take into account board index.
			if (!empty(Config::$modSettings['cal_showholidaysonindex'])) {
				Config::updateModSettings([
					'cal_showholidays' => Config::$modSettings['cal_showholidays'] === 1 ? 2 : 3,
				]);
			}

			if (!empty(Config::$modSettings['cal_showbdaysonindex'])) {
				Config::updateModSettings([
					'cal_showbdays' => Config::$modSettings['cal_showbdays'] === 1 ? 2 : 3,
				]);
			}

			if (!empty(Config::$modSettings['cal_showeventsonindex'])) {
				Config::updateModSettings([
					'cal_showevents' => Config::$modSettings['cal_showevents'] === 1 ? 2 : 3,
				]);
			}

			// We no longer need these.
			Config::updateModSettings([
				'cal_showholidaysoncalendar' => null,
				'cal_showbdaysoncalendar' => null,
				'cal_showeventsoncalendar' => null,
				'cal_showholidaysonindex' => null,
				'cal_showbdaysonindex' => null,
				'cal_showeventsonindex' => null,
			]);
		}

		// Adjusting calendar maximum year.
		Config::updateModSettings([
			'cal_maxyear' => 2030,
		]);

		// Adding advanced signature settings...
		if (empty(Config::$modSettings['signature_settings'])) {
			Config::updateModSettings([
				'signature_settings' => '1,' . (Config::$modSettings['max_signatureLength'] ?? '300') . ',0,0,0,0,0,0:',
				'max_signatureLength' => null,
			]);
		}

		// Updating spam protection settings.
		if (empty(Config::$modSettings['pm_spam_settings'])) {
			Config::updateModSettings([
				'pm_spam_settings' => (Config::$modSettings['max_pm_recipients'] ?? '10') . ',5,20',
				'max_pm_recipients' => null,
			]);
		} elseif (substr_count(Config::$modSettings['pm_spam_settings'], ',') == 1) {
			Config::updateModSettings([
				'pm_spam_settings' => Config::$modSettings['pm_spam_settings'] . ',20',
				'max_pm_recipients' => null,
			]);
		}

		// Checking theme layers are correct for default themes.
		$request = $this->query(
			'SELECT id_theme, value, variable
			FROM {db_prefix}themes
			WHERE variable = {literal:theme_layers}
				OR variable = {literal:theme_dir}',
		);

		$theme_layer_changes = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$theme_layer_changes[$row['id_theme']][$row['variable']] = $row['value'];
		}

		Db::$db->free_result($request);

		foreach ($theme_layer_changes as $id_theme => $data) {
			// Has to be a SMF provided theme and have custom layers defined.
			if (
				!isset($data['theme_layers'])
				|| !isset($data['theme_dir'])
				|| !\in_array(
					substr($data['theme_dir'], -7),
					['default', 'babylon', 'classic'],
				)
			) {
				continue;
			}

			$layers = explode(',', $data['theme_layers']);

			foreach ($layers as $k => $v) {
				if ($v == 'main') {
					$layers[$k] = 'html,body';

					$this->query(
						'UPDATE {db_prefix}themes
						SET value = {string:layers}
						WHERE id_theme = {int:id}
							AND variable = {string:var}',
						[
							'layers' => implode(',', $layers),
							'id' => $id_theme,
							'var' => 'theme_layers',
						],
					);

					break;
				}
			}
		}

		// Adding index to log_notify table.
		Db::$db->add_index(
			table_name: '{db_prefix}log_notify',
			index_info: [
				'name' => 'id_topic',
				'columns' => ['id_topic', 'id_member'],
			],
			if_exists: 'ignore',
		);

		// GDPR compliance settings.
		if (!isset(Config::$modSettings['requirePolicyAgreement'])) {
			Config::updateModSettings([
				'requirePolicyAgreement' => 0,
			]);
		}

		// Adding weekly maintenance task.
		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}scheduled_tasks',
			columns: [
				'next_time' => 'int',
				'time_offset' => 'int',
				'time_regularity' => 'int',
				'time_unit' => 'string-1',
				'disabled' => 'int',
				'task' => 'string-24',
			],
			data: [
				[0, 0, 1, 'w', 0, 'weekly_maintenance'],
			],
			keys: [],
		);

		// Adding pruning option.
		Config::updateModSettings([
			'pruningOptions' => '30,180,180,180,30,0',
		]);

		// Adding settings for attachments and avatars.
		Config::updateModSettings([
			// Enable image re-encoding by default.
			'attachment_image_reencode' => Config::$modSettings['attachment_image_reencode'] ?? 1,
			'avatar_reencode' => Config::$modSettings['avatar_reencode'] ?? 1,
			// Disable draconic checks by default.
			'attachment_image_paranoid' => Config::$modSettings['attachment_image_paranoid'] ?? 0,
			'avatar_paranoid' => Config::$modSettings['avatar_paranoid'] ?? 0,
			// Make image attachment thumbnail as PNG by default.
			'attachment_thumb_png' => Config::$modSettings['attachment_thumb_png'] ?? 1,
		]);

		$this->handleTimeout();

		return true;
	}
}
