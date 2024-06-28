<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Sources\Tasks;

use SMF\Sources\Actions\Notify;
use SMF\Sources\Alert;
use SMF\Sources\Config;
use SMF\Sources\Db\DatabaseApi as Db;
use SMF\Sources\Mail;
use SMF\Sources\Theme;
use SMF\Sources\User;
use SMF\Sources\Utils;

/**
 * This class contains code used to notify a moderator when another moderator
 * replies to a message moderation report that the first mod has commented on.
 */
class MsgReportReply_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		// Let's see. Let us, first of all, establish the list of possible people.
		$possible_members = [];
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}log_comments
			WHERE id_notice = {int:report}
				AND comment_type = {literal:reportc}
				AND id_comment < {int:last_comment}',
			[
				'report' => $this->_details['report_id'],
				'last_comment' => $this->_details['comment_id'],
			],
		);

		while ($row = Db::$db->fetch_row($request)) {
			$possible_members[] = $row[0];
		}
		Db::$db->free_result($request);

		// Presumably, there are some people?
		if (!empty($possible_members)) {
			$possible_members = array_flip(array_flip($possible_members));
			$possible_members = array_diff($possible_members, [$this->_details['sender_id']]);
		}

		if (empty($possible_members)) {
			return true;
		}

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

		// So now we have two lists: the people who replied to a report in the past,
		// and all the possible people who could see said report.
		$members = array_intersect($possible_members, $members);

		// Having successfully figured this out, now let's get the preferences of everyone.
		$prefs = Notify::getNotifyPrefs($members, 'msg_report_reply', true);

		// So now we find out who wants what.
		$alert_bits = [
			'alert' => self::RECEIVE_NOTIFY_ALERT,
			'email' => self::RECEIVE_NOTIFY_EMAIL,
		];
		$notifies = [];

		foreach ($prefs as $member => $pref_option) {
			foreach ($alert_bits as $type => $bitvalue) {
				if ($pref_option['msg_report_reply'] & $bitvalue) {
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
					'content_action' => 'report_reply',
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
				'SELECT lr.subject, lr.membername, lr.body
				FROM {db_prefix}log_reported AS lr
				WHERE id_report = {int:report}',
				[
					'report' => $this->_details['report_id'],
				],
			);
			list($subject, $poster_name, $comment) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Third, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients) {
				$replacements = [
					'TOPICSUBJECT' => $subject,
					'POSTERNAME' => $poster_name,
					'COMMENTERNAME' => $this->_details['sender_name'],
					'TOPICLINK' => Config::$scripturl . '?topic=' . $this->_details['topic_id'] . '.msg' . $this->_details['msg_id'] . '#msg' . $this->_details['msg_id'],
					'REPORTLINK' => Config::$scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'],
				];

				$emaildata = Mail::loadEmailTemplate('reply_to_moderator', $replacements, empty(Config::$modSettings['userLanguage']) ? Config::$language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $id_member => $email_address) {
					Mail::send($email_address, $emaildata['subject'], $emaildata['body'], null, 'rptrpy' . $this->_details['comment_id'], $emaildata['is_html'], 3);
				}
			}
		}

		// And now we're all done.
		return true;
	}
}

?>