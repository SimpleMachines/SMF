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

use SMF\Actions\Notify;
use SMF\Alert;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Mail;
use SMF\Theme;
use SMF\Utils;

/**
 * This class contains code used to notify a member when a group moderator has
 * taken action on that member's request to join a group.
 */
class GroupAct_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		// Get the details of all the members concerned...
		$request = Db::$db->query(
			'',
			'SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address,
				mem.lngfile, mem.member_name,  mg.group_name, mg.hidden
			FROM {db_prefix}log_group_requests AS lgr
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
			WHERE lgr.id_request IN ({array_int:request_list})
			ORDER BY mem.lngfile',
			[
				'request_list' => $this->_details['request_list'],
			],
		);
		$affected_users = [];
		$members = [];
		$alert_rows = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
			$row['lngfile'] = empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $row['lngfile'];

			// Build the required information array
			$affected_users[$row['id_member']] = [
				'rid' => $row['id_request'],
				'member_id' => $row['id_member'],
				'member_name' => $row['member_name'],
				'group_id' => $row['id_group'],
				'group_name' => $row['group_name'],
				'email' => $row['email_address'],
				'language' => $row['lngfile'],
			];
		}
		Db::$db->free_result($request);

		// Ensure everyone who is online gets their changes right away.
		Config::updateModSettings(['settings_updated' => time()]);

		if (!empty($affected_users)) {
			$prefs = Notify::getNotifyPrefs($members, ['groupr_approved', 'groupr_rejected'], true);

			// Same as for approving, kind of.
			foreach ($affected_users as $user) {
				$custom_reason = isset($this->_details['reason']) && isset($this->_details['reason'][$user['rid']]) ? $this->_details['reason'][$user['rid']] : '';

				// They are being approved?
				if ($this->_details['status'] == 'approve') {
					$pref_name = 'approved';
					$email_template_name = 'mc_group_approve';
					$email_message_id_prefix = 'grpapp';
				}

				// Otherwise, they are getting rejected (With or without a reason).
				else {
					$pref_name = 'rejected';
					$email_template_name = empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason';
					$email_message_id_prefix = 'grprej';
				}

				$pref = !empty($prefs[$user['member_id']]['groupr_' . $pref_name]) ? $prefs[$user['member_id']]['groupr_' . $pref_name] : 0;

				if ($pref & self::RECEIVE_NOTIFY_ALERT) {
					$alert_rows[] = [
						'alert_time' => time(),
						'id_member' => $user['member_id'],
						'content_type' => 'groupr',
						'content_id' => 0,
						'content_action' => $pref_name,
						'is_read' => 0,
						'extra' => Utils::jsonEncode(['group_name' => $user['group_name'], 'reason' => !empty($custom_reason) ? '<br><br>' . $custom_reason : '']),
					];
				}

				if ($pref & self::RECEIVE_NOTIFY_EMAIL) {
					// Emails are a bit complicated. We have to do language stuff.
					Theme::loadEssential();

					$replacements = [
						'USERNAME' => $user['member_name'],
						'GROUPNAME' => $user['group_name'],
					];

					if (!empty($custom_reason)) {
						$replacements['REASON'] = $custom_reason;
					}

					$emaildata = Mail::loadEmailTemplate($email_template_name, $replacements, $user['language']);

					Mail::send($user['email'], $emaildata['subject'], $emaildata['body'], null, $email_message_id_prefix . $user['rid'], $emaildata['is_html'], 2);
				}
			}

			// Insert the alerts if any
			if (!empty($alert_rows)) {
				Alert::createBatch($alert_rows);
			}
		}

		return true;
	}
}

?>