<?php

/**
 * This task handles notifying users when a message gets reported.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * Class MsgReport_Notify_Background
 */
class MsgReport_Notify_Background extends SMF_BackgroundTask
{
	/**
     * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $language, $scripturl;

		// We need to know who can moderate this board - and therefore who can see this report.
		// First up, people who have moderate_board in the board this topic was in.
		require_once($sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_board', $this->_details['board_id']);

		// Second, anyone assigned to be a moderator of this board directly.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}moderators
			WHERE id_board = {int:current_board}',
			array(
				'current_board' => $this->_details['board_id'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		// Thirdly, anyone assigned to be a moderator of this group as a group->board moderator.
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member
			FROM {db_prefix}members AS mem, {db_prefix}moderator_groups AS bm
			WHERE bm.id_board = {int:current_board}
				AND(
					mem.id_group = bm.id_group
					OR FIND_IN_SET(bm.id_group, mem.additional_groups) != 0
				)',
			array(
				'current_board' => $this->_details['board_id'],
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		// And now weed out the duplicates.
		$members = array_flip(array_flip($members));

		// And don't send it to them if they're the one who reported it.
		$members = array_diff($members, array($this->_details['sender_id']));

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'msg_report', true);

		// So now we find out who wants what.
		$alert_bits = array(
			'alert' => self::RECEIVE_NOTIFY_ALERT,
			'email' => self::RECEIVE_NOTIFY_EMAIL,
		);
		$notifies = array();

		foreach ($prefs as $member => $pref_option)
		{
			foreach ($alert_bits as $type => $bitvalue)
				if ($pref_option['msg_report'] & $bitvalue)
					$notifies[$type][] = $member;
		}

		// Firstly, anyone who wants alerts.
		if (!empty($notifies['alert']))
		{
			// Alerts are relatively easy.
			$insert_rows = array();
			foreach ($notifies['alert'] as $member)
			{
				$insert_rows[] = array(
					'alert_time' => $this->_details['time'],
					'id_member' => $member,
					'id_member_started' => $this->_details['sender_id'],
					'member_name' => $this->_details['sender_name'],
					'content_type' => 'msg',
					'content_id' => $this->_details['msg_id'],
					'content_action' => 'report',
					'is_read' => 0,
					'extra' => $smcFunc['json_encode'](
						array(
							'report_link' => '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'], // We don't put $scripturl in these!
						)
					),
				);
			}

			$smcFunc['db_insert']('insert',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int',
					'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int',
					'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$insert_rows,
				array('id_alert')
			);

			// And update the count of alerts for those people.
			updateMemberData($notifies['alert'], array('alerts' => '+'));
		}

		// Secondly, anyone who wants emails.
		if (!empty($notifies['email']))
		{
			// Emails are a bit complicated. We have to do language stuff.
			require_once($sourcedir . '/Subs-Post.php');
			require_once($sourcedir . '/ScheduledTasks.php');
			loadEssentialThemeData();

			// First, get everyone's language and details.
			$emails = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_member, lngfile, email_address
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				array(
					'members' => $notifies['email'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (empty($row['lngfile']))
					$row['lngfile'] = $language;
				$emails[$row['lngfile']][$row['id_member']] = $row['email_address'];
			}
			$smcFunc['db_free_result']($request);

			// Second, get some details that might be nice for the report email.
			// We don't bother cluttering up the tasks data for this, when it's really no bother to fetch it.
			$request = $smcFunc['db_query']('', '
				SELECT lr.subject, lr.membername, lr.body
				FROM {db_prefix}log_reported AS lr
				WHERE id_report = {int:report}',
				array(
					'report' => $this->_details['report_id'],
				)
			);
			list ($subject, $poster_name, $comment) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Third, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = array(
					'TOPICSUBJECT' => $subject,
					'POSTERNAME' => $poster_name,
					'REPORTERNAME' => $this->_details['sender_name'],
					'TOPICLINK' => $scripturl . '?topic=' . $this->_details['topic_id'] . '.msg' . $this->_details['msg_id'] . '#msg' . $this->_details['msg_id'],
					'REPORTLINK' => $scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $this->_details['report_id'],
					'COMMENT' => $comment,
				);

				$emaildata = loadEmailTemplate('report_to_moderator', $replacements, empty($modSettings['userLanguage']) ? $language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $id_member => $email_address)
					sendmail($email_address, $emaildata['subject'], $emaildata['body'], null, 'report' . $this->_details['report_id'], $emaildata['is_html'], 2);
			}
		}

		// And now we're all done.
		return true;
	}
}

?>