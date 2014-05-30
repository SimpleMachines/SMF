<?php
/**
 * This file contains background notification code for Mentions
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

class Mentions_Notify_Background extends SMF_BackgroundTask
{
	/**
	 * Executes the task
	 *
	 * @access public
	 * @return bool
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $scripturl, $txt, $language, $modSettings;

		require_once($sourcedir . '/Mentions.php');
		require_once($sourcedir . '/Subs-Notify.php');
		require_once($sourcedir . '/Subs-Post.php');

		// Get the mentions
		$members = Mentions::getMentionsByContent($this->_details['content_type'], $this->_details['content_id'],
													array_keys($this->_details['members']));

		if (empty($members))
			return true;

		// And their notification preferences!
		$notif_prefs = getNotifyPrefs(array_keys($members), $this->_details['content_type'] . '_mention', true);

		if ($this->_details['content_type'] == 'msg')
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_msg, id_topic, subject, id_board
				FROM {db_prefix}messages
				WHERE id_msg = {int:id}',
				array(
					'id' => $this->_details['content_id'],
				)
			);
			list ($msg, $topic, $subject, $board) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Check for board permissions
			$request = $smcFunc['db_query']('', '
				SELECT b.member_groups
				FROM {db_prefix}boards AS b
				WHERE id_board = {int:board}',
				array(
					'board' => $board,
				)
			);
			list ($member_groups) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
			$member_groups = explode(',', $member_groups);
			foreach ($member_groups as $k => $group)
				// Dunno why
				if (strlen($group) == 0)
					unset($member_groups[$k]);

			foreach ($members as $k => $member)
				if (!in_array(1, $member['groups']) && count(array_intersect($member['groups'], $member_groups)) == 0)
					unset($members[$k]);

			$notify_data = array(
				'content_subject' => $subject,
				'content_link' => $scripturl . '?msg=' . $msg,
			);
		}
		else
		{
			$result = call_integration_hook('mention_notify_' . $this->_details['content_type'], array($this->_details, &$members, &$notif_prefs));

			if (in_array(false, $result))
				return true;

			foreach ($result as $row)
				if (is_array($row) && isset($row['content_subject']))
					$notify_data = $row;
		}

		if (empty($members))
			return true;

		$alert_rows = array();

		foreach ($members as $id => $member)
		{
			if ($notif_prefs[$id][$this->_details['content_type'] . '_mention'] & 0x02)
			{
				$replacements = array(
					'CONTENTSUBJECT' => $notify_data['content_subject'],
					'MENTIONNAME' => $member['mentioned_by']['name'],
					'MEMBERNAME' => $member['real_name'],
					'CONTENTLINK' => $notify_data['content_link'],
				);

				$emaildata = loadEmailTemplate($this->_details['content_type'] . '_mention', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
				sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, $this->_details['content_type'] . '_mention_' . $this->_details['content_id'], false, 2);
			}

			if ($notif_prefs[$id][$this->_details['content_type'] . '_mention'] & 0x01)
			{
				$alert_rows[] = array(
					'alert_time' => $this->_details['time'],
					'id_member' => $member['id'],
					'id_member_started' => $member['mentioned_by']['id'],
					'member_name' => $member['mentioned_by']['name'],
					'content_type' => $this->_details['content_type'],
					'content_id' => $this->_details['content_id'],
					'content_action' => 'mention',
					'is_read' => 0,
					'extra' => serialize($notify_data),
				);
				updateMemberData($member['id'], array('alerts' => '+'));
			}
		}

		if (empty($alert_rows))
			return true;

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