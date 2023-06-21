<?php

/**
 * This file contains some useful functions for members and membergroups.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\Mail;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\User');
class_exists('SMF\\Actions\\Register2');

/**
 * Retrieves a list of membergroups that have the given permission, either on
 * a given board or in general.
 *
 * If board_id is not null, a board permission is assumed.
 * The function takes different permission settings into account.
 *
 * @param string $permission The permission to check
 * @param int $board_id = null If set, checks permissions for the specified board
 * @return array An array containing two arrays - 'allowed', which has which groups are allowed to do it and 'denied' which has the groups that are denied
 */
function groupsAllowedTo($permission, $board_id = null)
{
	// Admins are allowed to do anything.
	$member_groups = array(
		'allowed' => array(1),
		'denied' => array(),
	);

	// Assume we're dealing with regular permissions (like profile_view).
	if ($board_id === null)
	{
		$request = Db::$db->query('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}',
			array(
				'permission' => $permission,
			)
		);

		while ($row = Db::$db->fetch_assoc($request))
		{
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		}

		Db::$db->free_result($request);
	}

	// Otherwise it's time to look at the board.
	else
	{
		$board_id = (int) $board_id;

		// First get the profile of the given board.
		if (isset(Board::$info->id) && Board::$info->id == $board_id)
		{
			$profile_id = Board::$info->profile;
		}
		elseif ($board_id !== 0)
		{
			$request = Db::$db->query('', '
				SELECT id_profile
				FROM {db_prefix}boards
				WHERE id_board = {int:id_board}
				LIMIT 1',
				array(
					'id_board' => $board_id,
				)
			);

			if (Db::$db->num_rows($request) == 0)
			{
				fatal_lang_error('no_board');
			}

			list ($profile_id) = Db::$db->fetch_row($request);

			Db::$db->free_result($request);
		}
		else
			$profile_id = 1;

		$request = Db::$db->query('', '
			SELECT bp.id_group, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
			WHERE bp.permission = {string:permission}
				AND bp.id_profile = {int:profile_id}',
			array(
				'profile_id' => $profile_id,
				'permission' => $permission,
			)
		);

		while ($row = Db::$db->fetch_assoc($request))
		{
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		}

		Db::$db->free_result($request);

		$moderator_groups = array();

		// "Inherit" any moderator permissions as needed
		if (isset(Board::$info->moderator_groups))
		{
			$moderator_groups = array_keys(Board::$info->moderator_groups);
		}
		elseif ($board_id !== 0)
		{
			// Get the groups that can moderate this board
			$request = Db::$db->query('', '
				SELECT id_group
				FROM {db_prefix}moderator_groups
				WHERE id_board = {int:board_id}',
				array(
					'board_id' => $board_id,
				)
			);

			while ($row = Db::$db->fetch_assoc($request))
			{
				$moderator_groups[] = $row['id_group'];
			}

			Db::$db->free_result($request);
		}

		// "Inherit" any additional permissions from the "Moderators" group
		foreach ($moderator_groups as $mod_group)
		{
			// If they're not specifically allowed, but the moderator group is, then allow it
			if (in_array(3, $member_groups['allowed']) && !in_array($mod_group, $member_groups['allowed']))
			{
				$member_groups['allowed'][] = $mod_group;
			}

			// They're not denied, but the moderator group is, so deny it
			if (in_array(3, $member_groups['denied']) && !in_array($mod_group, $member_groups['denied']))
			{
				$member_groups['denied'][] = $mod_group;
			}
		}
	}

	// Maybe a mod needs to tweak the list of allowed groups on the fly?
	call_integration_hook('integrate_groups_allowed_to', array(&$member_groups, $permission, $board_id));

	// Denied is never allowed.
	$member_groups['allowed'] = array_diff($member_groups['allowed'], $member_groups['denied']);

	return $member_groups;
}

/**
 * Retrieves a list of members that have a given permission
 * (on a given board).
 * If board_id is not null, a board permission is assumed.
 * Takes different permission settings into account.
 * Takes possible moderators (on board 'board_id') into account.
 *
 * @param string $permission The permission to check
 * @param int $board_id If set, checks permission for that specific board
 * @return array An array containing the IDs of the members having that permission
 */
function membersAllowedTo($permission, $board_id = null)
{
	$member_groups = groupsAllowedTo($permission, $board_id);

	$all_groups = array_merge($member_groups['allowed'], $member_groups['denied']);

	$include_moderators = in_array(3, $member_groups['allowed']) && $board_id !== null;
	$member_groups['allowed'] = array_diff($member_groups['allowed'], array(3));

	$exclude_moderators = in_array(3, $member_groups['denied']) && $board_id !== null;
	$member_groups['denied'] = array_diff($member_groups['denied'], array(3));

	$request = Db::$db->query('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem' . ($include_moderators || $exclude_moderators ? '
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member AND mods.id_board = {int:board_id})' : '') . '
		WHERE (' . ($include_moderators ? 'mods.id_member IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_allowed}) OR FIND_IN_SET({raw:member_group_allowed_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_allowed}))' . (empty($member_groups['denied']) ? '' : '
			AND NOT (' . ($exclude_moderators ? 'mods.id_member IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_denied}) OR FIND_IN_SET({raw:member_group_denied_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_denied}))'),
		array(
			'member_groups_allowed' => $member_groups['allowed'],
			'member_groups_denied' => $member_groups['denied'],
			'all_member_groups' => $all_groups,
			'board_id' => $board_id,
			'member_group_allowed_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['allowed']),
			'member_group_denied_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['denied']),
		)
	);
	$members = array();
	while ($row = Db::$db->fetch_assoc($request))
		$members[] = $row['id_member'];
	Db::$db->free_result($request);

	return $members;
}

/**
 * This function is used to reassociate members with relevant posts.
 * Reattribute guest posts to a specified member.
 * Does not check for any permissions.
 * If add_to_post_count is set, the member's post count is increased.
 *
 * @param int $memID The ID of the original poster
 * @param bool|string $email If set, should be the email of the poster
 * @param bool|string $membername If set, the membername of the poster
 * @param bool $post_count Whether to adjust post counts
 * @return array An array containing the number of messages, topics and reports updated
 */
function reattributePosts($memID, $email = false, $membername = false, $post_count = false)
{
	$updated = array(
		'messages' => 0,
		'topics' => 0,
		'reports' => 0,
	);

	// Firstly, if email and username aren't passed find out the members email address and name.
	if ($email === false && $membername === false)
	{
		$request = Db::$db->query('', '
			SELECT email_address, member_name
			FROM {db_prefix}members
			WHERE id_member = {int:memID}
			LIMIT 1',
			array(
				'memID' => $memID,
			)
		);
		list ($email, $membername) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
	}

	// If they want the post count restored then we need to do some research.
	if ($post_count)
	{
		$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND b.count_posts = {int:count_posts})
			WHERE m.id_member = {int:guest_id}
				AND m.approved = {int:is_approved}' . (!empty($recycle_board) ? '
				AND m.id_board != {int:recycled_board}' : '') . (empty($email) ? '' : '
				AND m.poster_email = {string:email_address}') . (empty($membername) ? '' : '
				AND m.poster_name = {string:member_name}'),
			array(
				'count_posts' => 0,
				'guest_id' => 0,
				'email_address' => $email,
				'member_name' => $membername,
				'is_approved' => 1,
				'recycled_board' => $recycle_board,
			)
		);
		list ($messageCount) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		User::updateMemberData($memID, array('posts' => 'posts + ' . $messageCount));
	}

	$query_parts = array();
	if (!empty($email))
		$query_parts[] = 'poster_email = {string:email_address}';
	if (!empty($membername))
		$query_parts[] = 'poster_name = {string:member_name}';
	$query = implode(' AND ', $query_parts);

	// Finally, update the posts themselves!
	Db::$db->query('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:memID}
		WHERE ' . $query,
		array(
			'memID' => $memID,
			'email_address' => $email,
			'member_name' => $membername,
		)
	);
	$updated['messages'] = Db::$db->affected_rows();

	// Did we update any messages?
	if ($updated['messages'] > 0)
	{
		// First, check for updated topics.
		Db::$db->query('', '
			UPDATE {db_prefix}topics AS t
			SET id_member_started = {int:memID}
			WHERE t.id_first_msg = (
				SELECT m.id_msg
				FROM {db_prefix}messages m
				WHERE m.id_member = {int:memID}
					AND m.id_msg = t.id_first_msg
					AND ' . $query . '
				)',
			array(
				'memID' => $memID,
				'email_address' => $email,
				'member_name' => $membername,
			)
		);
		$updated['topics'] = Db::$db->affected_rows();

		// Second, check for updated reports.
		Db::$db->query('', '
			UPDATE {db_prefix}log_reported AS lr
			SET id_member = {int:memID}
			WHERE lr.id_msg = (
				SELECT m.id_msg
				FROM {db_prefix}messages m
				WHERE m.id_member = {int:memID}
					AND m.id_msg = lr.id_msg
					AND ' . $query . '
				)',
			array(
				'memID' => $memID,
				'email_address' => $email,
				'member_name' => $membername,
			)
		);
		$updated['reports'] = Db::$db->affected_rows();
	}

	// Allow mods with their own post tables to reattribute posts as well :)
	call_integration_hook('integrate_reattribute_posts', array($memID, $email, $membername, $post_count, &$updated));

	return $updated;
}

?>