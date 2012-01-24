<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file contains functions regarding manipulation of and information
	about membergroups.

	bool deleteMembergroups(array groups)
		- delete one of more membergroups.
		- requires the manage_membergroups permission.
		- returns true on success or false on failure.
		- has protection against deletion of protected membergroups.
		- deletes the permissions linked to the membergroup.
		- takes members out of the deleted membergroups.

	bool removeMembersFromGroups(array members, array groups = null)
		- remove one or more members from one or more membergroups.
		- requires the manage_membergroups permission.
		- returns true on success or false on failure.
		- if groups is null, the specified members are stripped from all their
		  membergroups.
		- function includes a protection against removing from implicit groups.
		- non-admins are not able to remove members from the admin group.

	bool addMembersToGroup(array members, group, type = 'auto')
		- add one or more members to a specified group.
		- requires the manage_membergroups permission.
		- returns true on success or false on failure.
		- the type parameter specifies whether the group is added as primary or
		  as additional group.
		- function has protection against adding members to implicit groups.
		- non-admins are not able to add members to the admin group.

	bool listMembergroupMembers_Href(&array members, int membergroup, int limit = null)
		- get a list of all members that are part of a membergroup.
		- if limit is set to null, all members are returned.
		- returns a list of href-links in $members.
		- returns true if there are more than limit members.

*/

// Delete one or more membergroups.
function deleteMembergroups($groups)
{
	global $sourcedir, $smcFunc, $modSettings;

	// Make sure it's an array.
	if (!is_array($groups))
		$groups = array((int) $groups);
	else
	{
		$groups = array_unique($groups);

		// Make sure all groups are integer.
		foreach ($groups as $key => $value)
			$groups[$key] = (int) $value;
	}

	// Some groups are protected (guests, administrators, moderators, newbies).
	$protected_groups = array(-1, 0, 1, 3, 4);

	// There maybe some others as well.
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$protected_groups[] = $row['id_group'];
		$smcFunc['db_free_result']($request);
	}

	// Make sure they don't delete protected groups!
	$groups = array_diff($groups, array_unique($protected_groups));
	if (empty($groups))
		return false;

	// Log the deletion.
	$request = $smcFunc['db_query']('', '
		SELECT group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		logAction('delete_group', array('group' => $row['group_name']), 'admin');
	$smcFunc['db_free_result']($request);

	// Remove the membergroups themselves.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Remove the permissions of the membergroups.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Delete any outstanding requests.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Update the primary groups of members.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_group}
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'regular_group' => 0,
		)
	);

	// Update any inherited groups (Lose inheritance).
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}membergroups
		SET id_parent = {int:uninherited}
		WHERE id_parent IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'uninherited' => -2,
		)
	);

	// Update the additional groups of members.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:additional_groups_explode}, additional_groups) != 0',
		array(
			'additional_groups_explode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups))));

	// No boards can provide access to these membergroups anymore.
	$request = $smcFunc['db_query']('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE FIND_IN_SET({raw:member_groups_explode}, member_groups) != 0',
		array(
			'member_groups_explode' => implode(', member_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$updates[$row['member_groups']][] = $row['id_board'];
	$smcFunc['db_free_result']($request);

	foreach ($updates as $member_groups => $boardArray)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET member_groups = {string:member_groups}
			WHERE id_board IN ({array_int:board_lists})',
			array(
				'board_lists' => $boardArray,
				'member_groups' => implode(',', array_diff(explode(',', $member_groups), $groups)),
			)
		);

	// Recalculate the post groups, as they likely changed.
	updateStats('postgroups');

	// Make a note of the fact that the cache may be wrong.
	$settings_update = array('settings_updated' => time());
	// Have we deleted the spider group?
	if (isset($modSettings['spider_group']) && in_array($modSettings['spider_group'], $groups))
		$settings_update['spider_group'] = 0;

	updateSettings($settings_update);

	// It was a success.
	return true;
}

// Remove one or more members from one or more membergroups.
function removeMembersFromGroups($members, $groups = null, $permissionCheckDone = false)
{
	global $smcFunc, $user_info, $modSettings;

	// You're getting nowhere without this permission, unless of course you are the group's moderator.
	if (!$permissionCheckDone)
		isAllowedTo('manage_membergroups');

	// Assume something will happen.
	updateSettings(array('settings_updated' => time()));

	// Cleaning the input.
	if (!is_array($members))
		$members = array((int) $members);
	else
	{
		$members = array_unique($members);

		// Cast the members to integer.
		foreach ($members as $key => $value)
			$members[$key] = (int) $value;
	}

	// Before we get started, let's check we won't leave the admin group empty!
	if ($groups === null || $groups == 1 || (is_array($groups) && in_array(1, $groups)))
	{
		$admins = array();
		listMembergroupMembers_Href($admins, 1);

		// Remove any admins if there are too many.
		$non_changing_admins = array_diff(array_keys($admins), $members);

		if (empty($non_changing_admins))
			$members = array_diff($members, array_keys($admins));
	}

	// Just in case.
	if (empty($members))
		return false;
	elseif ($groups === null)
	{
		// Wanna remove all groups from these members? That's easy.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				id_group = {int:regular_member},
				additional_groups = {string:blank_string}
			WHERE id_member IN ({array_int:member_list})' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND FIND_IN_SET({int:admin_group}, additional_groups) = 0'),
			array(
				'member_list' => $members,
				'regular_member' => 0,
				'admin_group' => 1,
				'blank_string' => '',
			)
		);

		updateStats('postgroups', $members);

		// Log what just happened.
		foreach ($members as $member)
			logAction('removed_all_groups', array('member' => $member), 'admin');

		return true;
	}
	elseif (!is_array($groups))
		$groups = array((int) $groups);
	else
	{
		$groups = array_unique($groups);

		// Make sure all groups are integer.
		foreach ($groups as $key => $value)
			$groups[$key] = (int) $value;
	}

	// Fetch a list of groups members cannot be assigned to explicitely, and the group names of the ones we want.
	$implicitGroups = array(-1, 0, 3);
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	$group_names = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['min_posts'] != -1)
			$implicitGroups[] = $row['id_group'];
		else
			$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// Now get rid of those groups.
	$groups = array_diff($groups, $implicitGroups);

	// Don't forget the protected groups.
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		$protected_groups = array(1);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$protected_groups[] = $row['id_group'];
		$smcFunc['db_free_result']($request);

		// If you're not an admin yourself, you can't touch protected groups!
		$groups = array_diff($groups, array_unique($protected_groups));
	}

	// Only continue if there are still groups and members left.
	if (empty($groups) || empty($members))
		return false;

	// First, reset those who have this as their primary group - this is the easy one.
	$log_inserts = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_group
		FROM {db_prefix}members AS members
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		array(
			'group_list' => $groups,
			'member_list' => $members,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$log_inserts[] = array(
			time(), 3, $user_info['id'], $user_info['ip'], 'removed_from_group',
			0, 0, 0, serialize(array('group' => $group_names[$row['id_group']], 'member' => $row['id_member'])),
		);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		array(
			'group_list' => $groups,
			'member_list' => $members,
			'regular_member' => 0,
		)
	);

	// Those who have it as part of their additional group must be updated the long way... sadly.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE (FIND_IN_SET({raw:additional_groups_implode}, additional_groups) != 0)
			AND id_member IN ({array_int:member_list})
		LIMIT ' . count($members),
		array(
			'member_list' => $members,
			'additional_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// What log entries must we make for this one, eh?
		foreach (explode(',', $row['additional_groups']) as $group)
			if (in_array($group, $groups))
				$log_inserts[] = array(
					time(), 3, $user_info['id'], $user_info['ip'], 'removed_from_group',
					0, 0, 0, serialize(array('group' => $group_names[$group], 'member' => $row['id_member'])),
				);

		$updates[$row['additional_groups']][] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);

	foreach ($updates as $additional_groups => $memberArray)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET additional_groups = {string:additional_groups}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $memberArray,
				'additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups)),
			)
		);

	// Their post groups may have changed now...
	updateStats('postgroups', $members);

	// Do the log.
	if (!empty($log_inserts) && !empty($modSettings['modlog_enabled']))
		$smcFunc['db_insert']('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts,
			array('id_action')
		);

	// Mission successful.
	return true;
}

// Add one or more members to a membergroup.
/* Supported types:
	- only_primary      - Assigns a membergroup as primary membergroup, but only
						  if a member has not yet a primary membergroup assigned,
						  unless the member is already part of the membergroup.
	- only_additional   - Assigns a membergroup to the additional membergroups,
						  unless the member is already part of the membergroup.
	- force_primary     - Assigns a membergroup as primary membergroup no matter
						  what the previous primary membergroup was.
	- auto              - Assigns a membergroup to the primary group if it's still
						  available. If not, assign it to the additional group. */
function addMembersToGroup($members, $group, $type = 'auto', $permissionCheckDone = false)
{
	global $smcFunc, $user_info, $modSettings;

	// Show your licence, but only if it hasn't been done yet.
	if (!$permissionCheckDone)
		isAllowedTo('manage_membergroups');

	// Make sure we don't keep old stuff cached.
	updateSettings(array('settings_updated' => time()));

	if (!is_array($members))
		$members = array((int) $members);
	else
	{
		$members = array_unique($members);

		// Make sure all members are integer.
		foreach ($members as $key => $value)
			$members[$key] = (int) $value;
	}
	$group = (int) $group;

	// Some groups just don't like explicitly having members.
	$implicitGroups = array(-1, 0, 3);
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $group,
		)
	);
	$group_names = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['min_posts'] != -1)
			$implicitGroups[] = $row['id_group'];
		else
			$group_names[$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// Sorry, you can't join an implicit group.
	if (in_array($group, $implicitGroups) || empty($members))
		return false;

	// Only admins can add admins...
	if (!allowedTo('admin_forum') && $group == 1)
		return false;
	// ... and assign protected groups!
	elseif (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			array(
				'current_group' => $group,
				'limit' => 1,
			)
		);
		list ($is_protected) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Is it protected?
		if ($is_protected == 1)
			return false;
	}

	// Do the actual updates.
	if ($type == 'only_additional')
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET additional_groups = CASE WHEN additional_groups = {string:blank_string} THEN {string:id_group_string} ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'id_group' => $group,
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
				'blank_string' => '',
			)
		);
	elseif ($type == 'only_primary' || $type == 'force_primary')
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET id_group = {int:id_group}
			WHERE id_member IN ({array_int:member_list})' . ($type == 'force_primary' ? '' : '
				AND id_group = {int:regular_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0'),
			array(
				'member_list' => $members,
				'id_group' => $group,
				'regular_group' => 0,
			)
		);
	elseif ($type == 'auto')
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				id_group = CASE WHEN id_group = {int:regular_group} THEN {int:id_group} ELSE id_group END,
				additional_groups = CASE WHEN id_group = {int:id_group} THEN additional_groups
					WHEN additional_groups = {string:blank_string} THEN {string:id_group_string}
					ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'regular_group' => 0,
				'id_group' => $group,
				'blank_string' => '',
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
			)
		);
	// Ack!!?  What happened?
	else
		trigger_error('addMembersToGroup(): Unknown type \'' . $type . '\'', E_USER_WARNING);

	// Update their postgroup statistics.
	updateStats('postgroups', $members);

	// Log the data.
	$log_inserts = array();
	foreach ($members as $member)
		$log_inserts[] = array(
			time(), 3, $user_info['id'], $user_info['ip'], 'added_to_group',
			0, 0, 0, serialize(array('group' => $group_names[$group], 'member' => $member)),
		);

	if (!empty($log_inserts) && !empty($modSettings['modlog_enabled']))
		$smcFunc['db_insert']('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts,
			array('id_action')
		);

	return true;
}

function listMembergroupMembers_Href(&$members, $membergroup, $limit = null)
{
	global $scripturl, $txt, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_group = {int:id_group} OR FIND_IN_SET({int:id_group}, additional_groups) != 0' . ($limit === null ? '' : '
		LIMIT ' . ($limit + 1)),
		array(
			'id_group' => $membergroup,
		)
	);
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
	$smcFunc['db_free_result']($request);

	// If there are more than $limit members, add a 'more' link.
	if ($limit !== null && count($members) > $limit)
	{
		array_pop($members);
		return true;
	}
	else
		return false;
}

// Retrieve a list of (visible) membergroups used by the cache.
function cache_getMembergroupList()
{
	global $scripturl, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups
		WHERE min_posts = {int:min_posts}
			AND hidden = {int:not_hidden}
			AND id_group != {int:mod_group}
			AND online_color != {string:blank_string}
		ORDER BY group_name',
		array(
			'min_posts' => -1,
			'not_hidden' => 0,
			'mod_group' => 3,
			'blank_string' => '',
		)
	);
	$groupCache = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groupCache[] = '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" ' . ($row['online_color'] ? 'style="color: ' . $row['online_color'] . '"' : '') . '>' . $row['group_name'] . '</a>';
	$smcFunc['db_free_result']($request);

	return array(
		'data' => $groupCache,
		'expires' => time() + 3600,
		'refresh_eval' => 'return $GLOBALS[\'modSettings\'][\'settings_updated\'] > ' . time() . ';',
	);
}

function list_getMembergroups($start, $items_per_page, $sort, $membergroup_type)
{
	global $txt, $scripturl, $context, $settings, $smcFunc;

	$groups = array();

	// Get the basic group data.
	$request = $smcFunc['db_query']('substring_membergroups', '
		SELECT id_group, group_name, min_posts, online_color, stars, 0 AS num_members
		FROM {db_prefix}membergroups
		WHERE min_posts ' . ($membergroup_type === 'post_count' ? '!=' : '=') . ' -1' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY {raw:sort}',
		array(
			'is_protected' => 1,
			'sort' => $sort,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groups[$row['id_group']] = array(
			'id_group' => $row['id_group'],
			'group_name' => $row['group_name'],
			'min_posts' => $row['min_posts'],
			'online_color' => $row['online_color'],
			'stars' => $row['stars'],
			'num_members' => $row['num_members'],
		);
	$smcFunc['db_free_result']($request);

	// If we found any membergroups, get the amount of members in them.
	if (!empty($groups))
	{
		if ($membergroup_type === 'post_count')
		{
			$query = $smcFunc['db_query']('', '
				SELECT id_post_group AS id_group, COUNT(*) AS num_members
				FROM {db_prefix}members
				WHERE id_post_group IN ({array_int:group_list})
				GROUP BY id_post_group',
				array(
					'group_list' => array_keys($groups),
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			$smcFunc['db_free_result']($query);
		}

		else
		{
			$query = $smcFunc['db_query']('', '
				SELECT id_group, COUNT(*) AS num_members
				FROM {db_prefix}members
				WHERE id_group IN ({array_int:group_list})
				GROUP BY id_group',
				array(
					'group_list' => array_keys($groups),
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			$smcFunc['db_free_result']($query);

			$query = $smcFunc['db_query']('', '
				SELECT mg.id_group, COUNT(*) AS num_members
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
						AND mem.id_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
				WHERE mg.id_group IN ({array_int:group_list})
				GROUP BY mg.id_group',
				array(
					'group_list' => array_keys($groups),
					'blank_string' => '',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			$smcFunc['db_free_result']($query);
		}
	}

	// Apply manual sorting if the 'number of members' column is selected.
	if (substr($sort, 0, 1) == '1' || strpos($sort, ', 1') !== false)
	{
		$sort_ascending = strpos($sort, 'DESC') === false;

		foreach ($groups as $group)
			$sort_array[] = $group['id_group'] != 3 ? (int) $group['num_members'] : -1;

		array_multisort($sort_array, $sort_ascending ? SORT_ASC : SORT_DESC, SORT_REGULAR, $groups);
	}

	return $groups;
}

?>