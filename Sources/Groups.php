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

/* This file currently just shows group info, and allows certain privaledged members to add/remove members.

	void Groups()
		- allows moderators and users to access the group showing functions.
		- handles permission checks, and puts the moderation bar on as required.

	void MembergroupMembers()
		- can be called from ManageMembergroups if it needs templating within the admin environment.
		- show a list of members that are part of a given membergroup.
		- called by ?action=moderate;area=viewgroups;sa=members;group=x
		- requires the manage_membergroups permission.
		- uses the group_members sub template of ManageMembergroups.
		- allows to add and remove members from the selected membergroup.
		- allows sorting on several columns.
		- redirects to itself.

	int list_getGroupRequestCount(string where)
		- callback function for createList()
		- returns the count of group requests

	array list_getGroupRequests(int start, int items_per_page, string sort, string where)
		- callback function for createList()
		- returns an array of group requests
		- each group request has:
			'id'
			'member_link'
			'group_link'
			'reason'
			'time_submitted'

*/

// Entry point, permission checks, admin bars, etc.
function Groups()
{
	global $context, $txt, $scripturl, $sourcedir, $user_info;

	// The sub-actions that we can do. Format "Function Name, Mod Bar Index if appropriate".
	$subActions = array(
		'index' => array('GroupList', 'view_groups'),
		'members' => array('MembergroupMembers', 'view_groups'),
		'requests' => array('GroupRequests', 'group_requests'),
	);

	// Default to sub action 'index' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'index';

	// Get the template stuff up and running.
	loadLanguage('ManageMembers');
	loadLanguage('ModerationCenter');
	loadTemplate('ManageMembergroups');

	// If we can see the moderation center, and this has a mod bar entry, add the mod center bar.
	if (allowedTo('access_mod_center') || $user_info['mod_cache']['bq'] != '0=1' || $user_info['mod_cache']['gq'] != '0=1' || allowedTo('manage_membergroups'))
	{
		require_once($sourcedir . '/ModerationCenter.php');
		$_GET['area'] = $_REQUEST['sa'] == 'requests' ? 'groups' : 'viewgroups';
		ModerationMain(true);
	}
	// Otherwise add something to the link tree, for normal people.
	else
	{
		isAllowedTo('view_mlist');

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=groups',
			'name' => $txt['groups'],
		);
	}

	// Call the actual function.
	$subActions[$_REQUEST['sa']][0]();
}

// This very simply lists the groups, nothing snazy.
function GroupList()
{
	global $txt, $scripturl, $user_profile, $user_info, $context, $settings, $modSettings, $smcFunc, $sourcedir;

	// Yep, find the groups...
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			mg.stars, IFNULL(gm.id_member, 0) AS can_moderate
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:mod_group}' . (allowedTo('admin_forum') ? '' : '
			AND mg.group_type != {int:is_protected}') . '
		ORDER BY group_name',
		array(
			'current_member' => $user_info['id'],
			'min_posts' => -1,
			'mod_group' => 3,
			'is_protected' => 1,
		)
	);
	// This is where we store our groups.
	$context['groups'] = array();
	$group_ids = array();
	$context['can_moderate'] = allowedTo('manage_membergroups');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We only list the groups they can see.
		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
			continue;

		$row['stars'] = explode('#', $row['stars']);

		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'desc' => $row['description'],
			'color' => $row['online_color'],
			'type' => $row['group_type'],
			'num_members' => 0,
			'stars' => !empty($row['stars'][0]) && !empty($row['stars'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $row['stars'][1] . '" alt="*" />', $row['stars'][0]) : '',
		);

		$context['can_moderate'] |= $row['can_moderate'];
		$group_ids[] = $row['id_group'];
	}
	$smcFunc['db_free_result']($request);

	// Count up the members separately...
	if (!empty($group_ids))
	{
		$query = $smcFunc['db_query']('', '
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:group_list})
			GROUP BY id_group',
			array(
				'group_list' => $group_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db_free_result']($query);

		// Only do additional groups if we can moderate...
		if ($context['can_moderate'])
		{
			$query = $smcFunc['db_query']('', '
				SELECT mg.id_group, COUNT(*) AS num_members
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_screen}
						AND mem.id_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
				WHERE mg.id_group IN ({array_int:group_list})
				GROUP BY mg.id_group',
				array(
					'group_list' => $group_ids,
					'blank_screen' => '',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($query))
				$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
			$smcFunc['db_free_result']($query);
		}
	}

	$context['sub_template'] = 'group_index';
	$context['page_title'] = $txt['viewing_groups'];

	// Making a list is not hard with this beauty.
	require_once($sourcedir . '/Subs-List.php');

	// Use the standard templates for showing this.
	$listOptions = array(
		'id' => 'group_lists',
		'title' => $context['page_title'],
		'get_items' => array(
			'function' => 'list_getGroups',
		),
		'columns' => array(
			'group' => array(
				'header' => array(
					'value' => $txt['name'],
				),
				'data' => array(
					'function' => create_function('$group', '
						global $scripturl, $context;

						$output = \'<a href="\' . $scripturl . \'?action=\' . $context[\'current_action\'] . (isset($context[\'admin_area\']) ? \';area=\' . $context[\'admin_area\'] : \'\') . \';sa=members;group=\' . $group[\'id\'] . \'" \' . ($group[\'color\'] ? \'style="color: \' . $group[\'color\'] . \';"\' : \'\') . \'>\' . $group[\'name\'] . \'</a>\';

						if ($group[\'desc\'])
							$output .= \'<div class="smalltext">\' . $group[\'desc\'] . \'</div>\';

						return $output;
					'),
					'style' => 'width: 50%;',
				),
			),
			'stars' => array(
				'header' => array(
					'value' => $txt['membergroups_stars'],
				),
				'data' => array(
					'db' => 'stars',
				),
			),
			'moderators' => array(
				'header' => array(
					'value' => $txt['moderators'],
				),
				'data' => array(
					'function' => create_function('$group', '
						global $txt;

						return empty($group[\'moderators\']) ? \'<em>\' . $txt[\'membergroups_new_copy_none\'] . \'</em>\' : implode(\', \', $group[\'moderators\']);
					'),
				),
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
				),
				'data' => array(
					'comma_format' => true,
					'db' => 'num_members',
				),
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'group_lists';
}

// Get the group information for the list.
function list_getGroups($start, $items_per_page, $sort)
{
	global $smcFunc, $txt, $scripturl, $user_info, $settings;

	// Yep, find the groups...
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			mg.stars, IFNULL(gm.id_member, 0) AS can_moderate
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:mod_group}' . (allowedTo('admin_forum') ? '' : '
			AND mg.group_type != {int:is_protected}') . '
		ORDER BY group_name',
		array(
			'current_member' => $user_info['id'],
			'min_posts' => -1,
			'mod_group' => 3,
			'is_protected' => 1,
		)
	);
	// Start collecting the data.
	$groups = array();
	$group_ids = array();
	$context['can_moderate'] = allowedTo('manage_membergroups');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We only list the groups they can see.
		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
			continue;

		$row['stars'] = explode('#', $row['stars']);

		$groups[$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'desc' => $row['description'],
			'color' => $row['online_color'],
			'type' => $row['group_type'],
			'num_members' => 0,
			'moderators' => array(),
			'stars' => !empty($row['stars'][0]) && !empty($row['stars'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $row['stars'][1] . '" alt="*" />', $row['stars'][0]) : '',
		);

		$context['can_moderate'] |= $row['can_moderate'];
		$group_ids[] = $row['id_group'];
	}
	$smcFunc['db_free_result']($request);

	// Count up the members separately...
	if (!empty($group_ids))
	{
		$query = $smcFunc['db_query']('', '
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:group_list})
			GROUP BY id_group',
			array(
				'group_list' => $group_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$groups[$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db_free_result']($query);

		// Only do additional groups if we can moderate...
		if ($context['can_moderate'])
		{
			$query = $smcFunc['db_query']('', '
				SELECT mg.id_group, COUNT(*) AS num_members
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_screen}
						AND mem.id_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
				WHERE mg.id_group IN ({array_int:group_list})
				GROUP BY mg.id_group',
				array(
					'group_list' => $group_ids,
					'blank_screen' => '',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($query))
				$groups[$row['id_group']]['num_members'] += $row['num_members'];
			$smcFunc['db_free_result']($query);
		}
	}

	// Get any group moderators.
	// Count up the members separately...
	if (!empty($group_ids))
	{
		$query = $smcFunc['db_query']('', '
			SELECT mods.id_group, mods.id_member, mem.member_name, mem.real_name
			FROM {db_prefix}group_moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE mods.id_group IN ({array_int:group_list})',
			array(
				'group_list' => $group_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$groups[$row['id_group']]['moderators'][] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		$smcFunc['db_free_result']($query);
	}

	return $groups;
}

// How many groups are there that are visible?
function list_getGroupCount()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_group) AS group_count
		FROM {db_prefix}membergroups
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:mod_group}' . (allowedTo('admin_forum') ? '' : '
			AND mg.group_type != {int:is_protected}'),
		array(
			'min_posts' => -1,
			'mod_group' => 3,
			'is_protected' => 1,
		)
	);
	list ($group_count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $group_count;
}

// Display members of a group, and allow adding of members to a group. Silly function name though ;)
function MembergroupMembers()
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir, $user_info, $settings, $smcFunc;

	$_REQUEST['group'] = isset($_REQUEST['group']) ? (int) $_REQUEST['group'] : 0;

	// No browsing of guests, membergroup 0 or moderators.
	if (in_array($_REQUEST['group'], array(-1, 0, 3)))
		fatal_lang_error('membergroup_does_not_exist', false);

	// Load up the group details.
	$request = $smcFunc['db_query']('', '
		SELECT id_group AS id, group_name AS name, CASE WHEN min_posts = {int:min_posts} THEN 1 ELSE 0 END AS assignable, hidden, online_color,
			stars, description, CASE WHEN min_posts != {int:min_posts} THEN 1 ELSE 0 END AS is_post_group, group_type
		FROM {db_prefix}membergroups
		WHERE id_group = {int:id_group}
		LIMIT 1',
		array(
			'min_posts' => -1,
			'id_group' => $_REQUEST['group'],
		)
	);
	// Doesn't exist?
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('membergroup_does_not_exist', false);
	$context['group'] = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Fix the stars.
	$context['group']['stars'] = explode('#', $context['group']['stars']);
	$context['group']['stars'] = !empty($context['group']['stars'][0]) && !empty($context['group']['stars'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $context['group']['stars'][1] . '" alt="*" />', $context['group']['stars'][0]) : '';
	$context['group']['can_moderate'] = allowedTo('manage_membergroups') && (allowedTo('admin_forum') || $context['group']['group_type'] != 1);

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=groups;sa=members;group=' . $context['group']['id'],
		'name' => $context['group']['name'],
	);

	// Load all the group moderators, for fun.
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}group_moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_group = {int:id_group}',
		array(
			'id_group' => $_REQUEST['group'],
		)
	);
	$context['group']['moderators'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['group']['moderators'][] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name']
		);

		if ($user_info['id'] == $row['id_member'] && $context['group']['group_type'] != 1)
			$context['group']['can_moderate'] = true;
	}
	$smcFunc['db_free_result']($request);

	// If this group is hidden then it can only "exists" if the user can moderate it!
	if ($context['group']['hidden'] && !$context['group']['can_moderate'])
		fatal_lang_error('membergroup_does_not_exist', false);

	// You can only assign membership if you are the moderator and/or can manage groups!
	if (!$context['group']['can_moderate'])
		$context['group']['assignable'] = 0;
	// Non-admins cannot assign admins.
	elseif ($context['group']['id'] == 1 && !allowedTo('admin_forum'))
		$context['group']['assignable'] = 0;

	// Removing member from group?
	if (isset($_POST['remove']) && !empty($_REQUEST['rem']) && is_array($_REQUEST['rem']) && $context['group']['assignable'])
	{
		checkSession();

		// Make sure we're dealing with integers only.
		foreach ($_REQUEST['rem'] as $key => $group)
			$_REQUEST['rem'][$key] = (int) $group;

		require_once($sourcedir . '/Subs-Membergroups.php');
		removeMembersFromGroups($_REQUEST['rem'], $_REQUEST['group'], true);
	}
	// Must be adding new members to the group...
	elseif (isset($_REQUEST['add']) && (!empty($_REQUEST['toAdd']) || !empty($_REQUEST['member_add'])) && $context['group']['assignable'])
	{
		checkSession();

		$member_query = array();
		$member_parameters = array();

		// Get all the members to be added... taking into account names can be quoted ;)
		$_REQUEST['toAdd'] = strtr($smcFunc['htmlspecialchars']($_REQUEST['toAdd'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_REQUEST['toAdd'], $matches);
		$member_names = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_REQUEST['toAdd']))));

		foreach ($member_names as $index => $member_name)
		{
			$member_names[$index] = trim($smcFunc['strtolower']($member_names[$index]));

			if (strlen($member_names[$index]) == 0)
				unset($member_names[$index]);
		}

		// Any passed by ID?
		$member_ids = array();
		if (!empty($_REQUEST['member_add']))
			foreach ($_REQUEST['member_add'] as $id)
				if ($id > 0)
					$member_ids[] = (int) $id;

		// Construct the query pelements.
		if (!empty($member_ids))
		{
			$member_query[] = 'id_member IN ({array_int:member_ids})';
			$member_parameters['member_ids'] = $member_ids;
		}
		if (!empty($member_names))
		{
			$member_query[] = 'LOWER(member_name) IN ({array_string:member_names})';
			$member_query[] = 'LOWER(real_name) IN ({array_string:member_names})';
			$member_parameters['member_names'] = $member_names;
		}

		$members = array();
		if (!empty($member_query))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (' . implode(' OR ', $member_query) . ')
					AND id_group != {int:id_group}
					AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
				array_merge($member_parameters, array(
					'id_group' => $_REQUEST['group'],
				))
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$members[] = $row['id_member'];
			$smcFunc['db_free_result']($request);
		}

		// !!! Add $_POST['additional'] to templates!

		// Do the updates...
		if (!empty($members))
		{
			require_once($sourcedir . '/Subs-Membergroups.php');
			addMembersToGroup($members, $_REQUEST['group'], isset($_POST['additional']) || $context['group']['hidden'] ? 'only_additional' : 'auto', true);
		}
	}

	// Sort out the sorting!
	$sort_methods = array(
		'name' => 'real_name',
		'email' => allowedTo('moderate_forum') ? 'email_address' : 'hide_email ' . (isset($_REQUEST['desc']) ? 'DESC' : 'ASC') . ', email_address',
		'active' => 'last_login',
		'registered' => 'date_registered',
		'posts' => 'posts',
	);

	// They didn't pick one, default to by name..
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = 'name';
		$querySort = 'real_name';
	}
	// Otherwise default to ascending.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$querySort = $sort_methods[$_REQUEST['sort']];
	}

	$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

	// The where on the query is interesting. Non-moderators should only see people who are in this group as primary.
	if ($context['group']['can_moderate'])
		$where = $context['group']['is_post_group'] ? 'id_post_group = {int:group}' : 'id_group = {int:group} OR FIND_IN_SET({int:group}, additional_groups) != 0';
	else
		$where = $context['group']['is_post_group'] ? 'id_post_group = {int:group}' : 'id_group = {int:group}';

	// Count members of the group.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE ' . $where,
		array(
			'group' => $_REQUEST['group'],
		)
	);
	list ($context['total_members']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$context['total_members'] = comma_format($context['total_members']);

	// Create the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=' . ($context['group']['can_moderate'] ? 'moderate;area=viewgroups' : 'groups') . ';sa=members;group=' . $_REQUEST['group'] . ';sort=' . $context['sort_by'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $context['total_members'], $modSettings['defaultMaxMembers']);
	$context['start'] = $_REQUEST['start'];
	$context['can_moderate_forum'] = allowedTo('moderate_forum');

	// Load up all members of this group.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, email_address, member_ip, date_registered, last_login,
			hide_email, posts, is_activated, real_name
		FROM {db_prefix}members
		WHERE ' . $where . '
		ORDER BY ' . $querySort . ' ' . ($context['sort_direction'] == 'down' ? 'DESC' : 'ASC') . '
		LIMIT ' . $context['start'] . ', ' . $modSettings['defaultMaxMembers'],
		array(
			'group' => $_REQUEST['group'],
		)
	);
	$context['members'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$last_online = empty($row['last_login']) ? $txt['never'] : timeformat($row['last_login']);

		// Italicize the online note if they aren't activated.
		if ($row['is_activated'] % 10 != 1)
			$last_online = '<em title="' . $txt['not_activated'] . '">' . $last_online . '</em>';

		$context['members'][] = array(
			'id' => $row['id_member'],
			'name' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'email' => $row['email_address'],
			'show_email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']),
			'ip' => '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>',
			'registered' => timeformat($row['date_registered']),
			'last_online' => $last_online,
			'posts' => comma_format($row['posts']),
			'is_activated' => $row['is_activated'] % 10 == 1,
		);
	}
	$smcFunc['db_free_result']($request);

	// Select the template.
	$context['sub_template'] = 'group_members';
	$context['page_title'] = $txt['membergroups_members_title'] . ': ' . $context['group']['name'];
}

// Show and manage all group requests.
function GroupRequests()
{
	global $txt, $context, $scripturl, $user_info, $sourcedir, $smcFunc, $modSettings, $language;

	// Set up the template stuff...
	$context['page_title'] = $txt['mc_group_requests'];
	$context['sub_template'] = 'show_list';

	// Verify we can be here.
	if ($user_info['mod_cache']['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Normally, we act normally...
	$where = $user_info['mod_cache']['gq'] == '1=1' || $user_info['mod_cache']['gq'] == '0=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq'];
	$where_parameters = array();

	// We've submitted?
	if (isset($_POST[$context['session_var']]) && !empty($_POST['groupr']) && !empty($_POST['req_action']))
	{
		checkSession('post');

		// Clean the values.
		foreach ($_POST['groupr'] as $k => $request)
			$_POST['groupr'][$k] = (int) $request;

		// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
		if ($_POST['req_action'] == 'reason')
		{
			// Different sub template...
			$context['sub_template'] = 'group_request_reason';
			// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
			$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
			$where_parameters['request_ids'] = $_POST['groupr'];

			$context['group_requests'] = list_getGroupRequests(0, $modSettings['defaultMaxMessages'], 'lgr.id_request', $where, $where_parameters);

			// Let obExit etc sort things out.
			obExit();
		}
		// Otherwise we do something!
		else
		{
			// Get the details of all the members concerned...
			$request = $smcFunc['db_query']('', '
				SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address, mem.id_group AS primary_group,
					mem.additional_groups AS additional_groups, mem.lngfile, mem.member_name, mem.notify_types,
					mg.hidden, mg.group_name
				FROM {db_prefix}log_group_requests AS lgr
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
					INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
				WHERE ' . $where . '
					AND lgr.id_request IN ({array_int:request_list})
				ORDER BY mem.lngfile',
				array(
					'request_list' => $_POST['groupr'],
				)
			);
			$email_details = array();
			$group_changes = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$row['lngfile'] = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

				// If we are approving work out what their new group is.
				if ($_POST['req_action'] == 'approve')
				{
					// For people with more than one request at once.
					if (isset($group_changes[$row['id_member']]))
					{
						$row['additional_groups'] = $group_changes[$row['id_member']]['add'];
						$row['primary_group'] = $group_changes[$row['id_member']]['primary'];
					}
					else
						$row['additional_groups'] = explode(',', $row['additional_groups']);

					// Don't have it already?
					if ($row['primary_group'] == $row['id_group'] || in_array($row['id_group'], $row['additional_groups']))
						continue;

					// Should it become their primary?
					if ($row['primary_group'] == 0 && $row['hidden'] == 0)
						$row['primary_group'] = $row['id_group'];
					else
						$row['additional_groups'][] = $row['id_group'];

					// Add them to the group master list.
					$group_changes[$row['id_member']] = array(
						'primary' => $row['primary_group'],
						'add' => $row['additional_groups'],
					);
				}

				// Add required information to email them.
				if ($row['notify_types'] != 4)
					$email_details[] = array(
						'rid' => $row['id_request'],
						'member_id' => $row['id_member'],
						'member_name' => $row['member_name'],
						'group_id' => $row['id_group'],
						'group_name' => $row['group_name'],
						'email' => $row['email_address'],
						'language' => $row['lngfile'],
					);
			}
			$smcFunc['db_free_result']($request);

			// Remove the evidence...
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_group_requests
				WHERE id_request IN ({array_int:request_list})',
				array(
					'request_list' => $_POST['groupr'],
				)
			);

			// Ensure everyone who is online gets their changes right away.
			updateSettings(array('settings_updated' => time()));

			if (!empty($email_details))
			{
				require_once($sourcedir . '/Subs-Post.php');

				// They are being approved?
				if ($_POST['req_action'] == 'approve')
				{
					// Make the group changes.
					foreach ($group_changes as $id => $groups)
					{
						// Sanity check!
						foreach ($groups['add'] as $key => $value)
							if ($value == 0 || trim($value) == '')
								unset($groups['add'][$key]);

						$smcFunc['db_query']('', '
							UPDATE {db_prefix}members
							SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
							WHERE id_member = {int:selected_member}',
							array(
								'primary_group' => $groups['primary'],
								'selected_member' => $id,
								'additional_groups' => implode(',', $groups['add']),
							)
						);
					}

					$lastLng = $user_info['language'];
					foreach ($email_details as $email)
					{
						$replacements = array(
							'USERNAME' => $email['member_name'],
							'GROUPNAME' => $email['group_name'],
						);

						$emaildata = loadEmailTemplate('mc_group_approve', $replacements, $email['language']);

						sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
					}
				}
				// Otherwise, they are getting rejected (With or without a reason).
				else
				{
					// Same as for approving, kind of.
					$lastLng = $user_info['language'];
					foreach ($email_details as $email)
					{
						$custom_reason = isset($_POST['groupreason']) && isset($_POST['groupreason'][$email['rid']]) ? $_POST['groupreason'][$email['rid']] : '';

						$replacements = array(
							'USERNAME' => $email['member_name'],
							'GROUPNAME' => $email['group_name'],
						);

						if (!empty($custom_reason))
							$replacements['REASON'] = $custom_reason;

						$emaildata = loadEmailTemplate(empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason', $replacements, $email['language']);

						sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
					}
				}
			}

			// Restore the current language.
			loadLanguage('ModerationCenter');
		}
	}

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a group listing.
	$listOptions = array(
		'id' => 'group_request_list',
		'title' => $txt['mc_group_requests'],
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['mc_groupr_none_found'],
		'base_href' => $scripturl . '?action=groups;sa=requests',
		'default_sort_col' => 'member',
		'get_items' => array(
			'function' => 'list_getGroupRequests',
			'params' => array(
				$where,
				$where_parameters,
			),
		),
		'get_count' => array(
			'function' => 'list_getGroupRequestCount',
			'params' => array(
				$where,
				$where_parameters,
			),
		),
		'columns' => array(
			'member' => array(
				'header' => array(
					'value' => $txt['mc_groupr_member'],
				),
				'data' => array(
					'db' => 'member_link',
				),
				'sort' => array(
					'default' => 'mem.member_name',
					'reverse' => 'mem.member_name DESC',
				),
			),
			'group' => array(
				'header' => array(
					'value' => $txt['mc_groupr_group'],
				),
				'data' => array(
					'db' => 'group_link',
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['mc_groupr_reason'],
				),
				'data' => array(
					'db' => 'reason',
				),
			),
			'action' => array(
				'header' => array(
					'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
					'style' => 'width: 4%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="groupr[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=groups;sa=requests',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<select name="req_action" onchange="if (this.value != 0 &amp;&amp; (this.value == \'reason\' || confirm(\'' . $txt['mc_groupr_warning'] . '\'))) this.form.submit();">
						<option value="0">' . $txt['with_selected'] . ':</option>
						<option value="0">---------------------</option>
						<option value="approve">' . $txt['mc_groupr_approve'] . '</option>
						<option value="reject">' . $txt['mc_groupr_reject'] . '</option>
						<option value="reason">' . $txt['mc_groupr_reject_w_reason'] . '</option>
					</select>
					<input type="submit" name="go" value="' . $txt['go'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['mc_groupr_warning'] . '\')) return false;" class="button_submit" />',
				'align' => 'right',
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['default_list'] = 'group_request_list';
}

function list_getGroupRequestCount($where, $where_parameters)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_group_requests AS lgr
		WHERE ' . $where,
		array_merge($where_parameters, array(
		))
	);
	list ($totalRequests) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $totalRequests;
}

function list_getGroupRequests($start, $items_per_page, $sort, $where, $where_parameters)
{
	global $smcFunc, $txt, $scripturl;

	$request = $smcFunc['db_query']('', '
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, lgr.reason,
			mem.member_name, mg.group_name, mg.online_color, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . $where . '
		ORDER BY {raw:sort}
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_parameters, array(
			'sort' => $sort,
		))
	);
	$group_requests = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$group_requests[] = array(
			'id' => $row['id_request'],
			'member_link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'group_link' => '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
			'reason' => censorText($row['reason']),
			'time_submitted' => timeformat($row['time_applied']),
		);
	}
	$smcFunc['db_free_result']($request);

	return $group_requests;
}

?>