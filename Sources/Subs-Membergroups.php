<?php

/**
 * This file contains functions regarding manipulation of and information about membergroups.
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

use SMF\BBCodeParser;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Delete one of more membergroups.
 * Requires the manage_membergroups permission.
 * Returns true on success or false on failure.
 * Has protection against deletion of protected membergroups.
 * Deletes the permissions linked to the membergroup.
 * Takes members out of the deleted membergroups.
 *
 * @param int|array $groups The ID of the group to delete or an array of IDs of groups to delete
 * @return bool|string True for success, otherwise an identifier as to reason for failure
 */
function deleteMembergroups($groups)
{
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
		$request = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		Db::$db->free_result($request);
	}

	// Make sure they don't delete protected groups!
	$groups = array_diff($groups, array_unique($protected_groups));
	if (empty($groups))
		return 'no_group_found';

	// Make sure they don't try to delete a group attached to a paid subscription.
	$subscriptions = array();
	$request = Db::$db->query('', '
		SELECT id_subscribe, name, id_group, add_groups
		FROM {db_prefix}subscriptions
		ORDER BY name');
	while ($row = Db::$db->fetch_assoc($request))
	{
		if (in_array($row['id_group'], $groups))
			$subscriptions[] = $row['name'];
		else
		{
			$add_groups = explode(',', $row['add_groups']);
			if (count(array_intersect($add_groups, $groups)) != 0)
				$subscriptions[] = $row['name'];
		}
	}
	Db::$db->free_result($request);
	if (!empty($subscriptions))
	{
		// Uh oh. But before we return, we need to update a language string because we want the names of the groups.
		Lang::load('ManageMembers');
		Lang::$txt['membergroups_cannot_delete_paid'] = sprintf(Lang::$txt['membergroups_cannot_delete_paid'], implode(', ', $subscriptions));
		return 'group_cannot_delete_sub';
	}

	// Log the deletion.
	$request = Db::$db->query('', '
		SELECT group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
		logAction('delete_group', array('group' => $row['group_name']), 'admin');
	Db::$db->free_result($request);

	call_integration_hook('integrate_delete_membergroups', array($groups));

	// Remove the membergroups themselves.
	Db::$db->query('', '
		DELETE FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Remove the permissions of the membergroups.
	Db::$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	Db::$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	Db::$db->query('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	Db::$db->query('', '
		DELETE FROM {db_prefix}moderator_groups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Delete any outstanding requests.
	Db::$db->query('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Update the primary groups of members.
	Db::$db->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_group}
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'regular_group' => 0,
		)
	);

	// Update any inherited groups (Lose inheritance).
	Db::$db->query('', '
		UPDATE {db_prefix}membergroups
		SET id_parent = {int:uninherited}
		WHERE id_parent IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'uninherited' => -2,
		)
	);

	// Update the additional groups of members.
	$request = Db::$db->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:additional_groups_explode}, additional_groups) != 0',
		array(
			'additional_groups_explode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = Db::$db->fetch_assoc($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	Db::$db->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		User::updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups))));

	// No boards can provide access to these membergroups anymore.
	$request = Db::$db->query('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE FIND_IN_SET({raw:member_groups_explode}, member_groups) != 0',
		array(
			'member_groups_explode' => implode(', member_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = Db::$db->fetch_assoc($request))
		$updates[$row['member_groups']][] = $row['id_board'];
	Db::$db->free_result($request);

	foreach ($updates as $member_groups => $boardArray)
		Db::$db->query('', '
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
	if (isset(Config::$modSettings['spider_group']) && in_array(Config::$modSettings['spider_group'], $groups))
		$settings_update['spider_group'] = 0;

	Config::updateModSettings($settings_update);

	// It was a success.
	return true;
}
/**
 * Retrieve a list of (visible) membergroups used by the cache.
 *
 * @return array An array of information about the cache
 */
function cache_getMembergroupList()
{
	$request = Db::$db->query('', '
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups
		WHERE min_posts = {int:min_posts}
			AND hidden = {int:not_hidden}
			AND id_group != {int:mod_group}
		ORDER BY group_name',
		array(
			'min_posts' => -1,
			'not_hidden' => 0,
			'mod_group' => 3,
		)
	);
	$groupCache = array();
	$group = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$group[$row['id_group']] = $row;
		$groupCache[$row['id_group']] = '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" ' . ($row['online_color'] ? 'style="color: ' . $row['online_color'] . '"' : '') . '>' . $row['group_name'] . '</a>';
	}
	Db::$db->free_result($request);

	call_integration_hook('integrate_getMembergroupList', array(&$groupCache, $group));

	return array(
		'data' => $groupCache,
		'expires' => time() + 3600,
		'refresh_eval' => 'return \SMF\Config::$modSettings[\'settings_updated\'] > ' . time() . ';',
	);
}

/**
 * Retrieves a list of membergroups with the given permissions.
 *
 * @param array $group_permissions
 * @param array $board_permissions
 * @param int   $profile_id
 *
 * @return array An array containing two arrays - 'allowed', which has which groups are allowed to do it and 'denied' which has the groups that are denied
 */
function getGroupsWithPermissions(array $group_permissions = array(), array $board_permissions = array(), $profile_id = 1)
{
	$member_groups = array();
	if (!empty($group_permissions))
	{
		foreach ($group_permissions as $group_permission)
			// Admins are allowed to do anything.
			$member_groups[$group_permission] = array(
				'allowed' => array(1),
				'denied' => array(),
			);

		$request = Db::$db->query('', '
			SELECT id_group, permission, add_deny
			FROM {db_prefix}permissions
			WHERE permission IN ({array_string:group_permissions})',
			array(
				'group_permissions' => $group_permissions,
			)
		);
		while (list ($id_group, $permission, $add_deny) = Db::$db->fetch_row($request))
			$member_groups[$permission][$add_deny === '1' ? 'allowed' : 'denied'][] = $id_group;
		Db::$db->free_result($request);
	}

	if (!empty($board_permissions))
	{
		foreach ($board_permissions as $board_permission)
			$member_groups[$board_permission] = array(
				'allowed' => array(1),
				'denied' => array(),
			);

		$request = Db::$db->query('', '
			SELECT id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE permission IN ({array_string:board_permissions})
				AND id_profile = {int:profile_id}',
			array(
				'profile_id' => $profile_id,
				'board_permissions' => $board_permissions,
			)
		);
		while (list ($id_group, $permission, $add_deny) = Db::$db->fetch_row($request))
			$member_groups[$permission][$add_deny === '1' ? 'allowed' : 'denied'][] = $id_group;
		Db::$db->free_result($request);
	}

	// Denied is never allowed.
	foreach ($member_groups as $permission => $groups)
		$member_groups[$permission]['allowed'] = array_diff($member_groups[$permission]['allowed'], $member_groups[$permission]['denied']);

	return $member_groups;
}

?>