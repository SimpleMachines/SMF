<?php

/**
 * This file currently just shows group info, and allows certain priviledged members to add/remove members.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Entry point function, permission checks, admin bars, etc.
 * It allows moderators and users to access the group showing functions.
 * It handles permission checks, and puts the moderation bar on as required.
 */
function Groups()
{
	global $context, $txt, $scripturl, $sourcedir, $user_info;

	// The sub-actions that we can do. Format "Function Name, Mod Bar Index if appropriate".
	$subActions = array(
		'index' => array('GroupList', 'view_groups'),
		'members' => array('MembergroupMembers', 'view_groups'),
		'requests' => array('GroupRequests', 'group_requests'),
	);

	// Default to sub action 'index'.
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

	// CRUD $subActions as needed.
	call_integration_hook('integrate_manage_groups', array(&$subActions));

	// Call the actual function.
	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * This very simply lists the groups, nothing snazy.
 */
function GroupList()
{
	global $txt, $context, $sourcedir, $scripturl;

	$context['page_title'] = $txt['viewing_groups'];

	// Making a list is not hard with this beauty.
	require_once($sourcedir . '/Subs-List.php');

	// Use the standard templates for showing this.
	$listOptions = array(
		'id' => 'group_lists',
		'title' => $context['page_title'],
		'base_href' => $scripturl . '?action=moderate;area=viewgroups;sa=view',
		'default_sort_col' => 'group',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Membergroups.php',
			'function' => 'list_getMembergroups',
			'params' => array(
				'regular',
			),
		),
		'columns' => array(
			'group' => array(
				'header' => array(
					'value' => $txt['name'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl)
					{
						// Since the moderator group has no explicit members, no link is needed.
						if ($rowData['id_group'] == 3)
							$group_name = $rowData['group_name'];
						else
						{
							$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

							if (allowedTo('manage_membergroups'))
							{
								$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', $scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
							}
							else
							{
								$group_name = sprintf('<a href="%1$s?action=groups;sa=members;group=%2$d"%3$s>%4$s</a>', $scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
							}
						}

						// Add a help option for moderator and administrator.
						if ($rowData['id_group'] == 1)
							$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)', $scripturl);
						elseif ($rowData['id_group'] == 3)
							$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)', $scripturl);

						return $group_name;
					},
				),
				'sort' => array(
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
				),
			),
			'icons' => array(
				'header' => array(
					'value' => $txt['membergroups_icons'],
				),
				'data' => array(
					'db' => 'icons',
				),
				'sort' => array(
					'default' => 'mg.icons',
					'reverse' => 'mg.icons DESC',
				)
			),
			'moderators' => array(
				'header' => array(
					'value' => $txt['moderators'],
				),
				'data' => array(
					'function' => function($group) use ($txt)
					{
						return empty($group['moderators']) ? '<em>' . $txt['membergroups_new_copy_none'] . '</em>' : implode(', ', $group['moderators']);
					},
				),
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						// No explicit members for the moderator group.
						return $rowData['id_group'] == 3 ? $txt['membergroups_guests_na'] : comma_format($rowData['num_members']);
					},
					'class' => 'centercol',
				),
				'sort' => array(
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
				),
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'group_lists';
}

/**
 * Display members of a group, and allow adding of members to a group. Silly function name though ;)
 * It can be called from ManageMembergroups if it needs templating within the admin environment.
 * It shows a list of members that are part of a given membergroup.
 * It is called by ?action=moderate;area=viewgroups;sa=members;group=x
 * It requires the manage_membergroups permission.
 * It allows to add and remove members from the selected membergroup.
 * It allows sorting on several columns.
 * It redirects to itself.
 *
 * @uses ManageMembergroups template, group_members sub template.
 * @todo: use createList
 */
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
			icons, description, CASE WHEN min_posts != {int:min_posts} THEN 1 ELSE 0 END AS is_post_group, group_type
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

	// Fix the membergroup icons.
	$context['group']['icons'] = explode('#', $context['group']['icons']);
	$context['group']['icons'] = !empty($context['group']['icons'][0]) && !empty($context['group']['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/membericons/' . $context['group']['icons'][1] . '" alt="*">', $context['group']['icons'][0]) : '';
	$context['group']['can_moderate'] = allowedTo('manage_membergroups') && (allowedTo('admin_forum') || $context['group']['group_type'] != 1);

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=groups;sa=members;group=' . $context['group']['id'],
		'name' => $context['group']['name'],
	);
	$context['can_send_email'] = allowedTo('moderate_forum');

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
		validateToken('mod-mgm');

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
		validateToken('mod-mgm');

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

		// @todo Add $_POST['additional'] to templates!

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
		'email' => 'email_address',
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
			posts, is_activated, real_name
		FROM {db_prefix}members
		WHERE ' . $where . '
		ORDER BY ' . $querySort . ' ' . ($context['sort_direction'] == 'down' ? 'DESC' : 'ASC') . '
		LIMIT {int:start}, {int:max}',
		array(
			'group' => $_REQUEST['group'],
			'start' => $context['start'],
			'max' => $modSettings['defaultMaxMembers'],
		)
	);
	$context['members'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['member_ip'] = inet_dtop($row['member_ip']);
		$last_online = empty($row['last_login']) ? $txt['never'] : timeformat($row['last_login']);

		// Italicize the online note if they aren't activated.
		if ($row['is_activated'] % 10 != 1)
			$last_online = '<em title="' . $txt['not_activated'] . '">' . $last_online . '</em>';

		$context['members'][] = array(
			'id' => $row['id_member'],
			'name' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'email' => $row['email_address'],
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
	createToken('mod-mgm');

	if ($context['group']['assignable'])
		loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
}

/**
 * Show and manage all group requests.
 */
function GroupRequests()
{
	global $txt, $context, $scripturl, $user_info, $sourcedir, $smcFunc, $modSettings;

	// Set up the template stuff...
	$context['page_title'] = $txt['mc_group_requests'];
	$context['sub_template'] = 'show_list';

	// Verify we can be here.
	if ($user_info['mod_cache']['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Normally, we act normally...
	$where = ($user_info['mod_cache']['gq'] == '1=1' || $user_info['mod_cache']['gq'] == '0=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq']);

	if (isset($_GET['closed']))
		$where .= ' AND lgr.status != {int:status_open}';
	else
		$where .= ' AND lgr.status = {int:status_open}';

	$where_parameters = array(
		'status_open' => 0,
	);

	// We've submitted?
	if (isset($_POST[$context['session_var']]) && !empty($_POST['groupr']) && !empty($_POST['req_action']))
	{
		checkSession();
		validateToken('mod-gr');

		// Clean the values.
		foreach ($_POST['groupr'] as $k => $request)
			$_POST['groupr'][$k] = (int) $request;

		$log_changes = array();

		// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
		if ($_POST['req_action'] == 'reason')
		{
			// Different sub template...
			$context['sub_template'] = 'group_request_reason';
			// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
			$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
			$where_parameters['request_ids'] = $_POST['groupr'];

			$context['group_requests'] = list_getGroupRequests(0, $modSettings['defaultMaxListItems'], 'lgr.id_request', $where, $where_parameters);

			// Need to make another token for this.
			createToken('mod-gr');

			// Let obExit etc sort things out.
			obExit();
		}
		// Otherwise we do something!
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT lgr.id_request
				FROM {db_prefix}log_group_requests AS lgr
				WHERE ' . $where . '
					AND lgr.id_request IN ({array_int:request_list})',
				array(
					'request_list' => $_POST['groupr'],
					'status_open' => 0,
				)
			);
			$request_list = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (!isset($log_changes[$row['id_request']]))
					$log_changes[$row['id_request']] = array(
						'id_request' => $row['id_request'],
						'status' => $_POST['req_action'] == 'approve' ? 1 : 2, // 1 = approved, 2 = rejected
						'id_member_acted' => $user_info['id'],
						'member_name_acted' => $user_info['name'],
						'time_acted' => time(),
						'act_reason' => $_POST['req_action'] != 'approve' && !empty($_POST['groupreason']) && !empty($_POST['groupreason'][$row['id_request']]) ? $smcFunc['htmlspecialchars']($_POST['groupreason'][$row['id_request']], ENT_QUOTES) : '',
					);
				$request_list[] = $row['id_request'];
			}
			$smcFunc['db_free_result']($request);

			// Add a background task to handle notifying people of this request
			$data = $smcFunc['json_encode'](array('member_id' => $user_info['id'], 'member_ip' => $user_info['ip'], 'request_list' => $request_list, 'status' => $_POST['req_action'], 'reason' => isset($_POST['groupreason']) ? $_POST['groupreason'] : '', 'time' => time()));
			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/GroupAct-Notify.php', 'GroupAct_Notify_Background', $data, 0), array()
			);

			// Some changes to log?
			if (!empty($log_changes))
			{
				foreach ($log_changes as $id_request => $details)
				{
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}log_group_requests
						SET status = {int:status},
							id_member_acted = {int:id_member_acted},
							member_name_acted = {string:member_name_acted},
							time_acted = {int:time_acted},
							act_reason = {string:act_reason}
						WHERE id_request = {int:id_request}',
						$details
					);
				}
			}
		}
	}

	// We're going to want this for making our list.
	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a group listing.
	$listOptions = array(
		'id' => 'group_request_list',
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxListItems'],
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
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
					'style' => 'width: 18%; white-space:nowrap;',
				),
				'data' => array(
					'db' => 'time_submitted',
				),
			),
			'action' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="groupr[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'class' => 'centercol',
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
			'token' => 'mod-gr',
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<select name="req_action" onchange="if (this.value != 0 &amp;&amp; (this.value == \'reason\' || confirm(\'' . $txt['mc_groupr_warning'] . '\'))) this.form.submit();">
						<option value="0">' . $txt['with_selected'] . ':</option>
						<option value="0" disabled>---------------------</option>
						<option value="approve">' . $txt['mc_groupr_approve'] . '</option>
						<option value="reject">' . $txt['mc_groupr_reject'] . '</option>
						<option value="reason">' . $txt['mc_groupr_reject_w_reason'] . '</option>
					</select>
					<input type="submit" name="go" value="' . $txt['go'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['mc_groupr_warning'] . '\')) return false;" class="button">',
				'class' => 'floatright',
			),
		),
	);

	if (isset($_GET['closed']))
	{
		// Closed requests don't require interaction.
		unset($listOptions['columns']['action'], $listOptions['form'], $listOptions['additional_rows'][0]);
		$listOptions['base_href'] .= 'closed';
	}

	// Create the request list.
	createToken('mod-gr');
	createList($listOptions);

	$context['default_list'] = 'group_request_list';
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_group_requests'],
	);
}

/**
 * Callback function for createList().
 *
 * @param string $where The WHERE clause for the query
 * @param array $where_parameters The parameters for the WHERE clause
 * @return int The number of group requests
 */
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

/**
 * Callback function for createList()
 *
 * @param int $start The result to start with
 * @param int $items_per_page The number of items per page
 * @param string $sort An SQL sort expression (column/direction)
 * @param string $where Data for the WHERE clause
 * @param string $where_parameters Parameter values to be inserted into the WHERE clause
 * @return array An array of group requests
 * Each group request has:
 * 		'id'
 * 		'member_link'
 * 		'group_link'
 * 		'reason'
 * 		'time_submitted'
 */
function list_getGroupRequests($start, $items_per_page, $sort, $where, $where_parameters)
{
	global $smcFunc, $scripturl, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT
			lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, lgr.reason,
			lgr.status, lgr.id_member_acted, lgr.member_name_acted, lgr.time_acted, lgr.act_reason,
			mem.member_name, mg.group_name, mg.online_color, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . $where . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array_merge($where_parameters, array(
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		))
	);
	$group_requests = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($row['reason']))
			$reason = '<em>(' . $txt['mc_groupr_no_reason'] . ')</em>';
		else
			$reason = censorText($row['reason']);

		if (isset($_GET['closed']))
		{
			if ($row['status'] == 1)
				$reason .= '<br><br><strong>' . $txt['mc_groupr_approved'] . '</strong>';
			elseif ($row['status'] == 2)
				$reason .= '<br><br><strong>' . $txt['mc_groupr_rejected'] . '</strong>';

			$reason .= ' (' . timeformat($row['time_acted']) . ')';
			if (!empty($row['act_reason']))
				$reason .= '<br><br>' . censorText($row['act_reason']);
		}

		$group_requests[] = array(
			'id' => $row['id_request'],
			'member_link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'group_link' => '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
			'reason' => $reason,
			'time_submitted' => timeformat($row['time_applied']),
		);
	}
	$smcFunc['db_free_result']($request);

	return $group_requests;
}

?>