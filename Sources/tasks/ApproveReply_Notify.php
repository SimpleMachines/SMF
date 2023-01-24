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
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

/**
 * This class contains code used to notify a member when a moderator replied to
 * the member's own unapproved topic.
 */
class ApproveReply_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		$msgOptions = $this->_details['msgOptions'];
		$topicOptions = $this->_details['topicOptions'];
		$posterOptions = $this->_details['posterOptions'];

		$members = array();
		$alert_rows = array();

		$request = Db::$db->query('', '
			SELECT id_member, email_address, lngfile
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
			WHERE id_topic = {int:topic}',
			array(
				'topic' => $topicOptions['id'],
			)
		);

		$watched = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			$members[] = $row['id_member'];
			$watched[$row['id_member']] = $row;
		}
		Db::$db->free_result($request);

		require_once(Config::$sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'unapproved_reply', true);
		foreach ($watched as $member => $data)
		{
			$pref = !empty($prefs[$member]['unapproved_reply']) ? $prefs[$member]['unapproved_reply'] : 0;

			if ($pref & self::RECEIVE_NOTIFY_EMAIL)
			{
				// Emails are a bit complicated. We have to do language stuff.
				require_once(Config::$sourcedir . '/Subs-Post.php');
				require_once(Config::$sourcedir . '/ScheduledTasks.php');
				loadEssentialThemeData();

				$replacements = array(
					'SUBJECT' => $msgOptions['subject'],
					'LINK' => Config::$scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
					'POSTERNAME' => un_htmlspecialchars($posterOptions['name']),
				);

				$emaildata = loadEmailTemplate('alert_unapproved_reply', $replacements, empty($data['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $data['lngfile']);
				sendmail($data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id'], $emaildata['is_html']);
			}

			if ($pref & self::RECEIVE_NOTIFY_ALERT)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member,
					'id_member_started' => $posterOptions['id'],
					'member_name' => $posterOptions['name'],
					'content_type' => 'topic',
					'content_id' => $topicOptions['id'],
					'content_action' => 'unapproved_reply',
					'is_read' => 0,
					'extra' => Utils::jsonEncode(array(
						'topic' => $topicOptions['id'],
						'board' => $topicOptions['board'],
						'content_subject' => $msgOptions['subject'],
						'content_link' => Config::$scripturl . '?topic=' . $topicOptions['id'] . '.new;topicseen#new',
					)),
				);
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows))
		{
			Db::$db->insert('',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$alert_rows,
				array()
			);

			updateMemberData(array_keys($watched), array('alerts' => '+'));
		}

		return true;
	}
}

?>