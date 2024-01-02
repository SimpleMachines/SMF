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

use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Theme;

/**
 * Does some weekly maintenance.
 */
class WeeklyMaintenance extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// Delete some settings that needn't be set if they are otherwise empty.
		$emptySettings = [
			'warning_mute',
			'warning_moderate',
			'warning_watch',
			'warning_show',
			'disableCustomPerPage',
			'spider_mode',
			'spider_group',
			'paid_currency_code',
			'paid_currency_symbol',
			'paid_email_to',
			'paid_email',
			'paid_enabled',
			'paypal_email',
			'search_enable_captcha',
			'search_floodcontrol_time',
			'show_spider_online',
		];

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})
				AND (value = {string:zero_value} OR value = {string:blank_value})',
			[
				'zero_value' => '0',
				'blank_value' => '',
				'setting_list' => $emptySettings,
			],
		);

		// Some settings we never want to keep; they are just there for temporary purposes.
		$deleteAnywaySettings = [
			'attachment_full_notified',
		];

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})',
			[
				'setting_list' => $deleteAnywaySettings,
			],
		);

		// Ok should we prune the logs?
		if (!empty(Config::$modSettings['pruningOptions']) && strpos(Config::$modSettings['pruningOptions'], ',') !== false) {
			list(Config::$modSettings['pruneErrorLog'], Config::$modSettings['pruneModLog'], Config::$modSettings['pruneBanLog'], Config::$modSettings['pruneReportLog'], Config::$modSettings['pruneScheduledTaskLog'], Config::$modSettings['pruneSpiderHitLog']) = explode(',', Config::$modSettings['pruningOptions']);

			if (!empty(Config::$modSettings['pruneErrorLog'])) {
				// Figure out when our cutoff time is. 1 day = 86400 seconds.
				$t = time() - Config::$modSettings['pruneErrorLog'] * 86400;

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_errors
					WHERE log_time < {int:log_time}',
					[
						'log_time' => $t,
					],
				);
			}

			if (!empty(Config::$modSettings['pruneModLog'])) {
				// Figure out when our cutoff time is. 1 day = 86400 seconds.
				$t = time() - Config::$modSettings['pruneModLog'] * 86400;

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_actions
					WHERE log_time < {int:log_time}
						AND id_log = {int:moderation_log}',
					[
						'log_time' => $t,
						'moderation_log' => 1,
					],
				);
			}

			if (!empty(Config::$modSettings['pruneBanLog'])) {
				// Figure out when our cutoff time is. 1 day = 86400 seconds.
				$t = time() - Config::$modSettings['pruneBanLog'] * 86400;

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_banned
					WHERE log_time < {int:log_time}',
					[
						'log_time' => $t,
					],
				);
			}

			if (!empty(Config::$modSettings['pruneReportLog'])) {
				// Figure out when our cutoff time is. 1 day = 86400 seconds.
				$t = time() - Config::$modSettings['pruneReportLog'] * 86400;

				// This one is more complex then the other logs.
				// First we need to figure out which reports are too old.
				$reports = [];

				$result = Db::$db->query(
					'',
					'SELECT id_report
					FROM {db_prefix}log_reported
					WHERE time_started < {int:time_started}
						AND closed = {int:closed}
						AND ignore_all = {int:not_ignored}',
					[
						'time_started' => $t,
						'closed' => 1,
						'not_ignored' => 0,
					],
				);

				while ($row = Db::$db->fetch_row($result)) {
					$reports[] = $row[0];
				}
				Db::$db->free_result($result);

				if (!empty($reports)) {
					// Now delete the reports...
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}log_reported
						WHERE id_report IN ({array_int:report_list})',
						[
							'report_list' => $reports,
						],
					);

					// And delete the comments for those reports...
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}log_reported_comments
						WHERE id_report IN ({array_int:report_list})',
						[
							'report_list' => $reports,
						],
					);
				}
			}

			if (!empty(Config::$modSettings['pruneScheduledTaskLog'])) {
				// Figure out when our cutoff time is. 1 day = 86400 seconds.
				$t = time() - Config::$modSettings['pruneScheduledTaskLog'] * 86400;

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_scheduled_tasks
					WHERE time_run < {int:time_run}',
					[
						'time_run' => $t,
					],
				);
			}

			if (!empty(Config::$modSettings['pruneSpiderHitLog'])) {
				// Figure out when our cutoff time is. 1 day = 86400 seconds.
				$t = time() - Config::$modSettings['pruneSpiderHitLog'] * 86400;

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_spider_hits
					WHERE log_time < {int:log_time}',
					[
						'log_time' => $t,
					],
				);
			}
		}

		// Get rid of any paid subscriptions that were never actioned.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_subscribed
			WHERE end_time = {int:no_end_time}
				AND status = {int:not_active}
				AND start_time < {int:start_time}
				AND payments_pending < {int:payments_pending}',
			[
				'no_end_time' => 0,
				'not_active' => 0,
				'start_time' => time() - 60,
				'payments_pending' => 1,
			],
		);

		// Some OS's don't seem to clean out their sessions.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			[
				'last_update' => time() - 86400,
			],
		);

		// Update the regex of top level domains with the IANA's latest official list.
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				'SMF\\Tasks\\UpdateTldRegex',
				'',
				0,
			],
			[],
		);

		// Ensure Unicode data files are up to date
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int'],
			[
				'SMF\\Tasks\\UpdateUnicode',
				'',
				0,
			],
			[],
		);

		// Run Cache housekeeping
		if (!empty(CacheApi::$enable) && !empty(CacheApi::$loadedApi)) {
			CacheApi::$loadedApi->housekeeping();
		}

		// Prevent stale minimized CSS and JavaScript from cluttering up the theme directories.
		Theme::deleteAllMinified();

		// Maybe there's more to do.
		IntegrationHook::call('integrate_weekly_maintenance');

		return true;
	}
}

?>