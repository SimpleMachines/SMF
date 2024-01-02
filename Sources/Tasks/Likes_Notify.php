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
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\User;

/**
 * This class contains code used to notify members when something is liked.
 */
class Likes_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		$author = false;

		// We need to figure out who the owner of this is.
		if ($this->_details['content_type'] == 'msg') {
			$request = Db::$db->query(
				'',
				'SELECT mem.id_member, mem.id_group, mem.id_post_group, mem.additional_groups, b.member_groups,
					mem.pm_ignore_list
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE id_msg = {int:msg}',
				[
					'msg' => $this->_details['content_id'],
				],
			);

			if ($row = Db::$db->fetch_assoc($request)) {
				// Before we assign the author, let's just check that the author can see the board this is in...
				// as it'd suck to notify someone their post was liked when in a board they can't see.
				// Use an empty array if additional_groups is blank to avoid a fringe case... (see https://github.com/SimpleMachines/SMF2.1/issues/2987)
				$groups = array_merge([$row['id_group'], $row['id_post_group']], (empty($row['additional_groups']) ? [] : explode(',', $row['additional_groups'])));
				$allowed = explode(',', $row['member_groups']);
				$ignored_members = explode(',', $row['pm_ignore_list']);

				// If the user is in group 1 anywhere, they can see everything anyway.
				if (in_array(1, $groups) || count(array_intersect($allowed, $groups)) != 0) {
					$author = $row['id_member'];
				}
			}
			Db::$db->free_result($request);
		} else {
			// This isn't something we know natively how to support. Call the hooks, if they're dealing with it, return false, otherwise return the user id.
			$hook_results = IntegrationHook::call('integrate_find_like_author', [$this->_details['content_type'], $this->_details['content_id']]);

			foreach ($hook_results as $result) {
				if (!empty($result)) {
					$author = $result;
					break;
				}
			}
		}

		// If we didn't have a member... leave.
		if (empty($author)) {
			return true;
		}

		// If the person who sent the notification is the person whose content it is, do nothing.
		if ($author == $this->_details['sender_id']) {
			return true;
		}

		// If the person who sent the notification is on this person's ignore list, do nothing.
		if (!empty($ignored_members) && in_array($this->_details['sender_id'], $ignored_members)) {
			return true;
		}

		$prefs = Notify::getNotifyPrefs($author, $this->_details['content_type'] . '_like', true);

		// The likes setup doesn't support email notifications because that would be too many emails.
		// As a result, the value should really just be non empty.

		// Check the value. If no value or it's empty, they didn't want alerts, oh well.
		if (empty($prefs[$author][$this->_details['content_type'] . '_like'])) {
			return true;
		}

		// Don't spam the alerts: if there is an existing unread alert of the
		// requested type for the target user from the sender, don't make a new one.
		$request = Db::$db->query(
			'',
			'SELECT id_alert
			FROM {db_prefix}user_alerts
			WHERE id_member = {int:id_member}
				AND is_read = 0
				AND content_type = {string:content_type}
				AND content_id = {int:content_id}
				AND content_action = {string:content_action}',
			[
				'id_member' => $author,
				'content_type' => $this->_details['content_type'],
				'content_id' => $this->_details['content_id'],
				'content_action' => 'like',
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			return true;
		}
		Db::$db->free_result($request);

		// Issue, update, move on.
		Alert::create([
			'alert_time' => $this->_details['time'],
			'id_member' => $author,
			'id_member_started' => $this->_details['sender_id'],
			'member_name' => $this->_details['sender_name'],
			'content_type' => $this->_details['content_type'],
			'content_id' => $this->_details['content_id'],
			'content_action' => 'like',
			'is_read' => 0,
			'extra' => '',
		]);

		return true;
	}
}

?>