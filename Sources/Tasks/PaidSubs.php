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

use SMF\Actions\Admin\Subscriptions;
use SMF\Actions\Notify;
use SMF\Alert;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Mail;
use SMF\Theme;
use SMF\Time;
use SMF\Utils;

/**
 * Performs the standard checks on expiring/near expiring subscriptions.
 */
class PaidSubs extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// Start off by checking for removed subscriptions.
		$request = Db::$db->query(
			'',
			'SELECT id_subscribe, id_member
			FROM {db_prefix}log_subscribed
			WHERE status = {int:is_active}
				AND end_time < {int:time_now}',
			[
				'is_active' => 1,
				'time_now' => time(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Subscriptions::remove($row['id_subscribe'], $row['id_member']);
		}
		Db::$db->free_result($request);

		// Get all those about to expire that have not had a reminder sent.
		$subs_reminded = [];
		$members = [];

		$request = Db::$db->query(
			'',
			'SELECT ls.id_sublog, m.id_member, m.member_name, m.email_address, m.lngfile, s.name, ls.end_time
			FROM {db_prefix}log_subscribed AS ls
				JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
				JOIN {db_prefix}members AS m ON (m.id_member = ls.id_member)
			WHERE ls.status = {int:is_active}
				AND ls.reminder_sent = {int:reminder_sent}
				AND s.reminder > {int:reminder_wanted}
				AND ls.end_time < ({int:time_now} + s.reminder * 86400)',
			[
				'is_active' => 1,
				'reminder_sent' => 0,
				'reminder_wanted' => 0,
				'time_now' => time(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// If this is the first one load the important bits.
			if (empty($subs_reminded)) {
				// Need the below for loadLanguage to work!
				Theme::loadEssential();
			}

			$subs_reminded[] = $row['id_sublog'];
			$members[$row['id_member']] = $row;
		}
		Db::$db->free_result($request);

		// Load alert preferences
		$notifyPrefs = Notify::getNotifyPrefs(array_keys($members), 'paidsubs_expiring', true);
		$alert_rows = [];

		foreach ($members as $row) {
			$replacements = [
				'PROFILE_LINK' => Config::$scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
				'REALNAME' => $row['member_name'],
				'SUBSCRIPTION' => $row['name'],
				'END_DATE' => strip_tags(Time::create('@' . $row['end_time'])->format()),
			];

			$emaildata = Mail::loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile']);

			// Send the actual email.
			if ($notifyPrefs[$row['id_member']] & self::RECEIVE_NOTIFY_EMAIL) {
				Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'paid_sub_remind', $emaildata['is_html'], 2);
			}

			if ($notifyPrefs[$row['id_member']] & self::RECEIVE_NOTIFY_ALERT) {
				$alert_rows[] = [
					'alert_time' => time(),
					'id_member' => $row['id_member'],
					'id_member_started' => $row['id_member'],
					'member_name' => $row['member_name'],
					'content_type' => 'paidsubs',
					'content_id' => $row['id_sublog'],
					'content_action' => 'expiring',
					'is_read' => 0,
					'extra' => Utils::jsonEncode([
						'subscription_name' => $row['name'],
						'end_time' => $row['end_time'],
					]),
				];
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows)) {
			Alert::createBatch($alert_rows);
		}

		// Mark the reminder as sent.
		if (!empty($subs_reminded)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_subscribed
				SET reminder_sent = {int:reminder_sent}
				WHERE id_sublog IN ({array_int:subscription_list})',
				[
					'subscription_list' => $subs_reminded,
					'reminder_sent' => 1,
				],
			);
		}

		return true;
	}
}

?>