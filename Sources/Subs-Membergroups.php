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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

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