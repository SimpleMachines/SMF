<?php
/**
 * This file contains background notification code for moderators
 * to approve attachments.
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
 * Class CreateAttachment_Notify_Background
 */
class CreateAttachment_Notify_Background extends SMF_BackgroundTask
{
	/**
	 * This executes the task - loads up the info, puts the email in the queue and inserts any alerts as needed.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $modSettings, $language;

		// Validate the attachment does exist and is the right approval state.
		$request = $smcFunc['db_query']('', '
			SELECT a.id_attach, m.id_board, m.id_msg, m.id_topic, m.id_member, m.subject
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			WHERE a.id_attach = {int:attachment}
				AND a.attachment_type = {int:attachment_type}
				AND a.approved = {int:is_approved}',
			array(
				'attachment' => $this->_details['id'],
				'attachment_type' => 0,
				'is_approved' => 0,
			)
		);
		// Return true if either not found or invalid so that the cron runner deletes this task.
		if ($smcFunc['db_num_rows']($request) == 0)
			return true;
		list ($id_attach, $id_board, $id_msg, $id_topic, $id_member, $subject) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// We need to know who can approve this attachment.
		require_once($sourcedir . '/Subs-Members.php');
		$modMembers = membersAllowedTo('approve_posts', $id_board);

		$request = $smcFunc['db_query']('', '
			SELECT id_member, email_address, lngfile, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => array_merge($modMembers, array($id_member)),
			)
		);

		$members = array();
		$watched = array();
		$real_name = '';
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['id_member'] == $id_member)
				$real_name = $row['real_name'];
			else
			{
				$members[] = $row['id_member'];
				$watched[$row['id_member']] = $row;
			}
		}
		$smcFunc['db_free_result']($request);

		if (empty($members))
			return true;

		require_once($sourcedir . '/Subs-Notify.php');
		$members = array_unique($members);
		$prefs = getNotifyPrefs($members, 'unapproved_attachment', true);
		foreach ($watched as $member => $data)
		{
			$pref = !empty($prefs[$member]['unapproved_attachment']) ? $prefs[$member]['unapproved_attachment'] : 0;

			if ($pref & self::RECEIVE_NOTIFY_EMAIL)
			{
				// Emails are a bit complicated. (That's what she said)
				require_once($sourcedir . '/Subs-Post.php');
				require_once($sourcedir . '/ScheduledTasks.php');
				loadEssentialThemeData();

				$emaildata = loadEmailTemplate(
					'unapproved_attachment',
					array(
						'SUBJECT' => $subject,
						'LINK' => $scripturl . '?topic=' . $id_topic . '.msg' . $id_msg . '#msg' . $id_msg,
					),
					empty($data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $data['lngfile']
				);
				sendmail(
					$data['email_address'],
					$emaildata['subject'],
					$emaildata['body'],
					null,
					'ma' . $id_attach,
					$emaildata['is_html']
				);
			}

			if ($pref & self::RECEIVE_NOTIFY_ALERT)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member,
					'id_member_started' => $id_member,
					'member_name' => $real_name,
					'content_type' => 'unapproved',
					'content_id' => $id_attach,
					'content_action' => 'attachment',
					'is_read' => 0,
					'extra' => $smcFunc['json_encode'](
						array(
							'topic' => $id_topic,
							'board' => $id_board,
							'content_subject' => $subject,
							'content_link' => $scripturl . '?topic=' . $id_topic . '.msg' . $id_msg . '#msg' . $id_msg,
						)
					),
				);
				updateMemberData($member, array('alerts' => '+'));
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows))
			$smcFunc['db_insert'](
				'insert',
				'{db_prefix}user_alerts',
				array(
					'alert_time' => 'int',
					'id_member' => 'int',
					'id_member_started' => 'int',
					'member_name' => 'string',
					'content_type' => 'string',
					'content_id' => 'int',
					'content_action' => 'string',
					'is_read' => 'int',
					'extra' => 'string',
				),
				$alert_rows,
				array()
			);

		return true;
	}
}

?>