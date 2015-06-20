<?php
/**
 * This file contains background notification code for any create post action
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

class ApprovePost_Notify_Background extends SMF_BackgroundTask
{
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $modSettings, $language;

		$msgOptions = $this->_details['msgOptions'];
		$topicOptions = $this->_details['topicOptions'];
		$posterOptions = $this->_details['posterOptions'];

		$members = array();
		$alert_rows = array();

		// We need to know who can approve this post.
		require_once($sourcedir . '/Subs-Members.php');
		$modMembers = membersAllowedTo('approve_posts', $topicOptions['board']);

		// Find the people interested in receiving notifications for this topic
		$request = $smcFunc['db_query']('', '
			SELECT id_member, email_address, lngfile
			FROM {db_prefix}members
			WHERE id_member IN({array_int:members})',
			array(
				'members' => $modMembers,
			)
		);

		$watched = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$members[] = $row['id_member'];
			$watched[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);

		if (empty($members))
			return true;

		require_once($sourcedir . '/Subs-Notify.php');
		$members = array_unique($members);
		$prefs = getNotifyPrefs($members, 'unapproved_post');
		foreach ($watched as $member => $data)
		{
			$pref = !empty($prefs[$member]['unapproved_post']) ? $prefs[$member]['unapproved_post'] : 0;

			if ($pref & 0x02)
			{
				// Emails are a bit complicated. We have to do language stuff.
				require_once($sourcedir . '/Subs-Post.php');
				require_once($sourcedir . '/ScheduledTasks.php');
				loadEssentialThemeData();

				$replacements = array(
					'SUBJECT' => $msgOptions['subject'],
					'LINK' => $scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
				);

				$emaildata = loadEmailTemplate('alert_unapproved_post', $replacements, empty($data['lngfile']) || empty($modSettings['userLanguage']) ? $language : $data['lngfile']);
				sendmail($data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id']);
			}

			if ($pref & 0x01)
			{
				$alert_rows[] = array(
					'alert_time' => time(),
					'id_member' => $member,
					'id_member_started' => $posterOptions['id'],
					'member_name' => $posterOptions['name'],
					'content_type' => 'unapproved',
					'content_id' => $topicOptions['id'],
					'content_action' => 'post',
					'is_read' => 0,
					'extra' => serialize(array(
						'topic' => $topicOptions['id'],
						'board' => $topicOptions['board'],
						'content_subject' => $msgOptions['subject'],
						'content_link' => $scripturl . '?topic=' . $topicOptions['id'] . '.new;topicseen#new',
					)),
				);
				updateMemberData($member, array('alerts' => '+'));
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows))
			$smcFunc['db_insert']('',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$alert_rows,
				array()
			);

		return true;
	}
}

?>