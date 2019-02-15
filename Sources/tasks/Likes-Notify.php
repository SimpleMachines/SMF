<?php

/**
 * This task handles notifying users when something is liked.
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
 * Class Likes_Notify_Background
 */
class Likes_Notify_Background extends SMF_BackgroundTask
{
	/**
	 * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir;

		$author = false;
		// We need to figure out who the owner of this is.
		if ($this->_details['content_type'] == 'msg')
		{
			$request = $smcFunc['db_query']('', '
				SELECT mem.id_member, mem.id_group, mem.id_post_group, mem.additional_groups, b.member_groups,
					mem.pm_ignore_list
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE id_msg = {int:msg}',
				array(
					'msg' => $this->_details['content_id'],
				)
			);
			if ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Before we assign the author, let's just check that the author can see the board this is in...
				// as it'd suck to notify someone their post was liked when in a board they can't see.
				// Use an empty array if additional_groups is blank to avoid a fringe case... (see https://github.com/SimpleMachines/SMF2.1/issues/2987)
				$groups = array_merge(array($row['id_group'], $row['id_post_group']), (empty($row['additional_groups']) ? array() : explode(',', $row['additional_groups'])));
				$allowed = explode(',', $row['member_groups']);
				$ignored_members = explode(',', $row['pm_ignore_list']);

				// If the user is in group 1 anywhere, they can see everything anyway.
				if (in_array(1, $groups) || count(array_intersect($allowed, $groups)) != 0)
					$author = $row['id_member'];
			}
			$smcFunc['db_free_result']($request);
		}
		else
		{
			// This isn't something we know natively how to support. Call the hooks, if they're dealing with it, return false, otherwise return the user id.
			$hook_results = call_integration_hook('integrate_find_like_author', array($this->_details['content_type'], $this->_details['content_id']));
			foreach ($hook_results as $result)
				if (!empty($result))
				{
					$author = $result;
					break;
				}
		}

		// If we didn't have a member... leave.
		if (empty($author))
			return true;

		// If the person who sent the notification is the person whose content it is, do nothing.
		if ($author == $this->_details['sender_id'])
			return true;

		// If the person who sent the notification is on this person's ignore list, do nothing.
		if (!empty($ignored_members) && in_array($this->_details['sender_id'], $ignored_members))
			return true;

		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($author, $this->_details['content_type'] . '_like', true);

		// The likes setup doesn't support email notifications because that would be too many emails.
		// As a result, the value should really just be non empty.

		// Check the value. If no value or it's empty, they didn't want alerts, oh well.
		if (empty($prefs[$author][$this->_details['content_type'] . '_like']))
			return true;

		// Don't spam the alerts: if there is an existing unread alert of the
		// requested type for the target user from the sender, don't make a new one.
		$request = $smcFunc['db_query']('', '
			SELECT id_alert
			FROM {db_prefix}user_alerts
			WHERE id_member = {int:id_member}
				AND is_read = 0
				AND content_type = {string:content_type}
				AND content_id = {int:content_id}
				AND content_action = {string:content_action}',
			array(
				'id_member' => $author,
				'content_type' => $this->_details['content_type'],
				'content_id' => $this->_details['content_id'],
				'content_action' => 'like',
			)
		);

		if ($smcFunc['db_num_rows']($request) > 0)
			return true;
		$smcFunc['db_free_result']($request);

		// Issue, update, move on.
		$smcFunc['db_insert']('insert',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			array($this->_details['time'], $author, $this->_details['sender_id'], $this->_details['sender_name'],
				$this->_details['content_type'], $this->_details['content_id'], 'like', 0, ''),
			array('id_alert')
		);

		updateMemberData($author, array('alerts' => '+'));

		return true;
	}
}

?>