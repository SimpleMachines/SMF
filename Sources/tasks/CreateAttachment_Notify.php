<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

use SMF\Config;
use SMF\Msg;
use SMF\Mail;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

/**
 * This class contains code used to notify moderators when there are attachments
 * that need to be approved.
 */
class CreateAttachment_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		// Validate the attachment does exist and is the right approval state.
		$request = Db::$db->query('', '
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
		if (Db::$db->num_rows($request) == 0)
			return true;
		list ($id_attach, $id_board, $id_msg, $id_topic, $id_member, $subject) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// We need to know who can approve this attachment.
		require_once(Config::$sourcedir . '/Subs-Members.php');
		$modMembers = membersAllowedTo('approve_posts', $id_board);

		$request = Db::$db->query('', '
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
		while ($row = Db::$db->fetch_assoc($request))
		{
			if ($row['id_member'] == $id_member)
				$real_name = $row['real_name'];
			else
			{
				$members[] = $row['id_member'];
				$watched[$row['id_member']] = $row;
			}
		}
		Db::$db->free_result($request);

		if (empty($members))
			return true;

		require_once(Config::$sourcedir . '/Subs-Notify.php');
		$members = array_unique($members);
		$prefs = getNotifyPrefs($members, 'unapproved_attachment', true);
		foreach ($watched as $member => $data)
		{
			$pref = !empty($prefs[$member]['unapproved_attachment']) ? $prefs[$member]['unapproved_attachment'] : 0;

			if ($pref & self::RECEIVE_NOTIFY_EMAIL)
			{
				// Emails are a bit complicated. (That's what she said)
				require_once(Config::$sourcedir . '/ScheduledTasks.php');
				loadEssentialThemeData();

				$emaildata = Mail::loadEmailTemplate(
					'unapproved_attachment',
					array(
						'SUBJECT' => $subject,
						'LINK' => Config::$scripturl . '?topic=' . $id_topic . '.msg' . $id_msg . '#msg' . $id_msg,
					),
					empty($data['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $data['lngfile']
				);
				Mail::send(
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
					'content_type' => 'msg',
					'content_id' => $id_msg,
					'content_action' => 'unapproved_attachment',
					'is_read' => 0,
					'extra' => Utils::jsonEncode(
						array(
							'topic' => $id_topic,
							'board' => $id_board,
							'content_subject' => $subject,
							'content_link' => Config::$scripturl . '?msg=' . $id_msg,
						)
					),
				);
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows))
		{
			Db::$db->insert(
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

			User::updateMemberData(array_keys($watched), array('alerts' => '+'));
		}

		return true;
	}
}

?>