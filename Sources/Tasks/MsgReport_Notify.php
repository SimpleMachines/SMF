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
use SMF\User;
use SMF\Utils;

/**
 * This class contains code used to notify moderators when someone files a report
 * about a message.
 */
class MsgReport_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// We need to know who can moderate this board - and therefore who can see this report.
		// First up, people who have moderate_board in the board this topic was in.
		$members = User::membersAllowedTo('moderate_board', $this->_details['board_id']);

		// Second, anyone assigned to be a moderator of this board directly.
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}moderators
			WHERE id_board = {int:current_board}',
			[
				'current_board' => $this->_details['board_id'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		// Thirdly, anyone assigned to be a moderator of this group as a group->board moderator.
		$request = Db::$db->query(
			'',
			'SELECT mem.id_member
			FROM {db_prefix}members AS mem, {db_prefix}moderator_groups AS bm
			WHERE bm.id_board = {int:current_board}
				AND(
					mem.id_group = bm.id_group
					OR FIND_IN_SET(bm.id_group, mem.additional_groups) != 0
				)',
			[
				'current_board' => $this->_details['board_id'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		// And now weed out the duplicates.
		$members = array_flip(array_flip($members));

		// And don't send it to them if they're the one who reported it.
		$members = array_diff($members, [$this->_details['sender_id']]);

		// Having successfully figured this out, now let's get the preferences of everyone.
		$prefs = Notify::getNotifyPrefs($members, 'msg_report', true);

		// So now we find out who wants what.
		$alert_bits = [
			'alert' => self::RECEIVE_NOTIFY_ALERT,
			'email' => self::RECEIVE_NOTIFY_EMAIL,
		];
		$notifies = [];

		foreach ($prefs as $member => $pref_option) {
			foreach ($alert_bits as $type => $bitvalue) {
				if ($pref_option['msg_report'] & $bitvalue) {
					$notifies[$type][] = $member;
				}
			}
		}

		// Firstly, anyone who wants alerts.
		if (!empty($notifies['alert'])) {
			// Alerts are relatively easy.
			$insert_rows = [];

			foreach ($notifies['alert'] as $member) {
				$insert_rows[] = [
					'alert_time' => $this->_details['time'],
					'id_member' => $member,
					'id_member_started' => $this->_details['sender_id'],
					'member_name' => $this->_details['sender_name'],
					'content_type' => 'msg',
					'content_id' => $this->_details['msg_id'],
					'content_action' => 'report',
					'is_read' => 0,
					'extra' => Utils::jsonEncode(
						[
							'report_link' => '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'], // We don't put Config::$scripturl in these!
						],
					),
				];
			}

			Alert::createBatch($insert_rows);
		}

		// Secondly, anyone who wants emails.
		if (!empty($notifies['email'])) {
			// Emails are a bit complicated. We have to do language stuff.
			Theme::loadEssential();

			// First, get everyone's language and details.
			$emails = [];
			$request = Db::$db->query(
				'',
				'SELECT id_member, lngfile, email_address
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				[
					'members' => $notifies['email'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (empty($row['lngfile'])) {
					$row['lngfile'] = Config::$language;
				}
				$emails[$row['lngfile']][$row['id_member']] = $row['email_address'];
			}
			Db::$db->free_result($request);

			// Second, get some details that might be nice for the report email.
			// We don't bother cluttering up the tasks data for this, when it's really no bother to fetch it.
			$request = Db::$db->query(
				'',
				'SELECT lr.subject, lr.membername, lrc.comment
				FROM {db_prefix}log_reported AS lr
					INNER JOIN {db_prefix}log_reported_comments AS lrc ON (lr.id_report = lrc.id_report)
				WHERE lr.id_report = {int:report}
					AND lrc.id_comment = {int:comment}',
				[
					'report' => $this->_details['report_id'],
					'comment' => $this->_details['comment_id'],
				],
			);
			list($subject, $poster_name, $comment) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Third, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients) {
				$replacements = [
					'TOPICSUBJECT' => $subject,
					'POSTERNAME' => $poster_name,
					'REPORTERNAME' => $this->_details['sender_name'],
					'TOPICLINK' => Config::$scripturl . '?topic=' . $this->_details['topic_id'] . '.msg' . $this->_details['msg_id'] . '#msg' . $this->_details['msg_id'],
					'REPORTLINK' => Config::$scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'],
					'COMMENT' => $comment,
				];

				$emaildata = Mail::loadEmailTemplate('report_to_moderator', $replacements, empty(Config::$modSettings['userLanguage']) ? Config::$language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $id_member => $email_address) {
					Mail::send($email_address, $emaildata['subject'], $emaildata['body'], null, 'report' . $this->_details['report_id'], $emaildata['is_html'], 2);
				}
			}
		}

		// And now we're all done.
		return true;
	}
}

?>