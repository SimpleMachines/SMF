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

namespace SMF\Tasks;

use SMF\Actions\Admin\SearchEngines;
use SMF\Alert;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\ProxyServer;

/**
 * Does some daily cleaning up.
 */
class DailyMaintenance extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// First clean out the cache.
		CacheApi::clean();

		// If warning decrement is enabled and we have people who have not had a new warning in 24 hours, lower their warning level.
		list(, , Config::$modSettings['warning_decrement']) = explode(',', Config::$modSettings['warning_settings']);

		if (Config::$modSettings['warning_decrement']) {
			$members = [];

			// Find every member who has a warning level...
			$request = Db::$db->query(
				'',
				'SELECT id_member, warning
				FROM {db_prefix}members
				WHERE warning > {int:no_warning}',
				[
					'no_warning' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$members[$row['id_member']] = $row['warning'];
			}
			Db::$db->free_result($request);

			// Have some members to check?
			if (!empty($members)) {
				$member_changes = [];

				// Find out when they were last warned.
				$request = Db::$db->query(
					'',
					'SELECT id_recipient, MAX(log_time) AS last_warning
					FROM {db_prefix}log_comments
					WHERE id_recipient IN ({array_int:member_list})
						AND comment_type = {string:warning}
					GROUP BY id_recipient',
					[
						'member_list' => array_keys($members),
						'warning' => 'warning',
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					// More than 24 hours ago?
					if ($row['last_warning'] <= time() - 86400) {
						$member_changes[] = [
							'id' => $row['id_recipient'],
							'warning' => $members[$row['id_recipient']] >= Config::$modSettings['warning_decrement'] ? $members[$row['id_recipient']] - Config::$modSettings['warning_decrement'] : 0,
						];
					}
				}
				Db::$db->free_result($request);

				// Have some members to change?
				if (!empty($member_changes)) {
					foreach ($member_changes as $change) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}members
							SET warning = {int:warning}
							WHERE id_member = {int:id_member}',
							[
								'warning' => $change['warning'],
								'id_member' => $change['id'],
							],
						);
					}
				}
			}
		}

		// Do any spider stuff.
		if (!empty(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] > 1) {
			SearchEngines::consolidateSpiderStats();
		}

		// Clean up some old login history information.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}member_logins
			WHERE time < {int:oldLogins}',
			[
				'oldLogins' => time() - (!empty(Config::$modSettings['loginHistoryDays']) ? 60 * 60 * 24 * Config::$modSettings['loginHistoryDays'] : 2592000),
			],
		);

		// Run Imageproxy housekeeping
		if (!empty(Config::$image_proxy_enabled)) {
			$proxy = new ProxyServer();
			$proxy->housekeeping();
		}

		// Delete old profile exports
		if (!empty(Config::$modSettings['export_expiry']) && file_exists(Config::$modSettings['export_dir']) && is_dir(Config::$modSettings['export_dir'])) {
			$expiry_date = round(TIME_START - Config::$modSettings['export_expiry'] * 86400);
			$export_files = glob(rtrim(Config::$modSettings['export_dir'], '/\\') . DIRECTORY_SEPARATOR . '*');

			foreach ($export_files as $export_file) {
				if (!in_array(basename($export_file), ['index.php', '.htaccess']) && filemtime($export_file) <= $expiry_date) {
					@unlink($export_file);
				}
			}
		}

		// Delete old alerts.
		if (!empty(Config::$modSettings['alerts_auto_purge'])) {
			Alert::purge(-1, time() - 86400 * Config::$modSettings['alerts_auto_purge']);
		}

		// Anyone else have something to do?
		IntegrationHook::call('integrate_daily_maintenance');

		return true;
	}
}

?>