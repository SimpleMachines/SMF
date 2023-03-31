<?php

/**
 * ManagePermissions handles all possible permission stuff.
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
use SMF\Category;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Dispatches to the right function based on the given subaction.
 * Checks the permissions, based on the sub-action.
 * Called by ?action=managepermissions.
 *
 * Uses ManagePermissions language file.
 */

function ModifyPermissions()
{
	Lang::load('ManagePermissions+ManageMembers');
	Theme::loadTemplate('ManagePermissions');

	// Format: 'sub-action' => array('function_to_call', 'permission_needed'),
	$subActions = array(
		'board' => array('PermissionByBoard', 'manage_permissions'),
		'index' => array('PermissionIndex', 'manage_permissions'),
		'modify' => array('ModifyMembergroup', 'manage_permissions'),
		'modify2' => array('ModifyMembergroup2', 'manage_permissions'),
		'quick' => array('SetQuickGroups', 'manage_permissions'),
		'quickboard' => array('SetQuickBoards', 'manage_permissions'),
		'postmod' => array('ModifyPostModeration', 'manage_permissions'),
		'profiles' => array('EditPermissionProfiles', 'manage_permissions'),
		'settings' => array('GeneralPermissionSettings', 'admin_forum'),
	);

	// Create the tabs for the template.
	Utils::$context[Utils::$context['admin_menu_name']]['tab_data'] = array(
		'title' => Lang::$txt['permissions_title'],
		'help' => 'permissions',
		'description' => '',
		'tabs' => array(
			'index' => array(
				'description' => Lang::$txt['permissions_groups'],
			),
			'board' => array(
				'description' => Lang::$txt['permission_by_board_desc'],
			),
			'profiles' => array(
				'description' => Lang::$txt['permissions_profiles_desc'],
			),
			'postmod' => array(
				'description' => Lang::$txt['permissions_post_moderation_desc'],
			),
			'settings' => array(
				'description' => Lang::$txt['permission_settings_desc'],
			),
		),
	);

	call_integration_hook('integrate_manage_permissions', array(&$subActions));

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && empty($subActions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : (allowedTo('manage_permissions') ? 'index' : 'settings');

	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Sets up the permissions by membergroup index page.
 * Called by ?action=managepermissions
 * Creates an array of all the groups with the number of members and permissions.
 *
 * Uses ManagePermissions language file.
 * Uses ManagePermissions template file.
 * @uses template_permission_index()
 */
function PermissionIndex()
{
	Utils::$context['page_title'] = Lang::$txt['permissions_title'];

	// Load all the permissions. We'll need them in the template.
	loadAllPermissions();

	// Also load profiles, we may want to reset.
	loadPermissionProfiles();

	// Are we going to show the advanced options?
	Utils::$context['show_advanced_options'] = empty(Utils::$context['admin_preferences']['app']);

	// Determine the number of ungrouped members.
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE id_group = {int:regular_group}',
		array(
			'regular_group' => 0,
		)
	);
	list ($num_members) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	// Fill the context variable with 'Guests' and 'Regular Members'.
	Utils::$context['groups'] = array(
		-1 => array(
			'id' => -1,
			'name' => Lang::$txt['membergroups_guests'],
			'num_members' => Lang::$txt['membergroups_guests_na'],
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => '',
			'link' => '',
			'help' => 'membergroup_guests',
			'is_post_group' => false,
			'color' => '',
			'icons' => '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => 0,
				// Can't deny guest permissions!
				'denied' => '(' . Lang::$txt['permissions_none'] . ')'
			),
			'access' => false
		),
		0 => array(
			'id' => 0,
			'name' => Lang::$txt['membergroups_members'],
			'num_members' => $num_members,
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => Config::$scripturl . '?action=moderate;area=viewgroups;sa=members;group=0',
			'help' => 'membergroup_regular_members',
			'is_post_group' => false,
			'color' => '',
			'icons' => '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => 0,
				'denied' => 0
			),
			'access' => false
		),
	);

	$postGroups = array();
	$normalGroups = array();

	// Query the database defined membergroups.
	$query = Db::$db->query('', '
		SELECT id_group, id_parent, group_name, min_posts, online_color, icons
		FROM {db_prefix}membergroups' . (empty(Config::$modSettings['permission_enable_postgroups']) ? '
		WHERE min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent = {int:not_inherited} DESC, min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'min_posts' => -1,
			'not_inherited' => -2,
			'newbie_group' => 4,
		)
	);
	while ($row = Db::$db->fetch_assoc($query))
	{
		// If it's inherited, just add it as a child.
		if ($row['id_parent'] != -2)
		{
			if (isset(Utils::$context['groups'][$row['id_parent']]))
				Utils::$context['groups'][$row['id_parent']]['children'][$row['id_group']] = $row['group_name'];
			continue;
		}

		$row['icons'] = explode('#', $row['icons']);
		Utils::$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'num_members' => $row['id_group'] != 3 ? 0 : Lang::$txt['membergroups_guests_na'],
			'allow_delete' => $row['id_group'] > 4,
			'allow_modify' => $row['id_group'] > 1,
			'can_search' => $row['id_group'] != 3,
			'href' => Config::$scripturl . '?action=moderate;area=viewgroups;sa=members;group=' . $row['id_group'],
			'help' => $row['id_group'] == 1 ? 'membergroup_administrator' : ($row['id_group'] == 3 ? 'membergroup_moderator' : ''),
			'is_post_group' => $row['min_posts'] != -1,
			'color' => empty($row['online_color']) ? '' : $row['online_color'],
			'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . Theme::$current->settings['images_url'] . '/' . $row['icons'][1] . '" alt="*">', $row['icons'][0]) : '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => $row['id_group'] == 1 ? '(' . Lang::$txt['permissions_all'] . ')' : 0,
				'denied' => $row['id_group'] == 1 ? '(' . Lang::$txt['permissions_none'] . ')' : 0
			),
			'access' => false,
		);

		if ($row['min_posts'] == -1)
			$normalGroups[$row['id_group']] = $row['id_group'];
		else
			$postGroups[$row['id_group']] = $row['id_group'];
	}
	Db::$db->free_result($query);

	// Get the number of members in this post group.
	if (!empty($postGroups))
	{
		$query = Db::$db->query('', '
			SELECT id_post_group AS id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_post_group IN ({array_int:post_group_list})
			GROUP BY id_post_group',
			array(
				'post_group_list' => $postGroups,
			)
		);
		while ($row = Db::$db->fetch_assoc($query))
			Utils::$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		Db::$db->free_result($query);
	}

	if (!empty($normalGroups))
	{
		// First, the easy one!
		$query = Db::$db->query('', '
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			array(
				'normal_group_list' => $normalGroups,
			)
		);
		while ($row = Db::$db->fetch_assoc($query))
			Utils::$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		Db::$db->free_result($query);

		// This one is slower, but it's okay... careful not to count twice!
		$query = Db::$db->query('', '
			SELECT mg.id_group, COUNT(*) AS num_members
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			array(
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			)
		);
		while ($row = Db::$db->fetch_assoc($query))
			Utils::$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		Db::$db->free_result($query);
	}

	foreach (Utils::$context['groups'] as $id => $data)
	{
		if ($data['href'] != '')
			Utils::$context['groups'][$id]['link'] = '<a href="' . $data['href'] . '">' . $data['num_members'] . '</a>';
	}

	if (empty($_REQUEST['pid']))
	{
		$request = Db::$db->query('', '
			SELECT id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}permissions
			' . (empty(Utils::$context['hidden_permissions']) ? '' : ' WHERE permission NOT IN ({array_string:hidden_permissions})') . '
			GROUP BY id_group, add_deny',
			array(
				'hidden_permissions' => !empty(Utils::$context['hidden_permissions']) ? Utils::$context['hidden_permissions'] : array(),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			if (isset(Utils::$context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				Utils::$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
		Db::$db->free_result($request);

		// Get the "default" profile permissions too.
		$request = Db::$db->query('', '
			SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:default_profile}
			' . (empty(Utils::$context['hidden_permissions']) ? '' : ' AND permission NOT IN ({array_string:hidden_permissions})') . '
			GROUP BY id_profile, id_group, add_deny',
			array(
				'default_profile' => 1,
				'hidden_permissions' => !empty(Utils::$context['hidden_permissions']) ? Utils::$context['hidden_permissions'] : array(),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (isset(Utils::$context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				Utils::$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
		}
		Db::$db->free_result($request);
	}
	else
	{
		$_REQUEST['pid'] = (int) $_REQUEST['pid'];

		if (!isset(Utils::$context['profiles'][$_REQUEST['pid']]))
			fatal_lang_error('no_access', false);

		// Change the selected tab to better reflect that this really is a board profile.
		Utils::$context[Utils::$context['admin_menu_name']]['current_subsection'] = 'profiles';

		$request = Db::$db->query('', '
			SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}
			GROUP BY id_profile, id_group, add_deny',
			array(
				'current_profile' => $_REQUEST['pid'],
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (isset(Utils::$context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				Utils::$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
		}
		Db::$db->free_result($request);

		Utils::$context['profile'] = array(
			'id' => $_REQUEST['pid'],
			'name' => Utils::$context['profiles'][$_REQUEST['pid']]['name'],
		);
	}

	// We can modify any permission set apart from the read only, reply only and no polls ones as they are redefined.
	Utils::$context['can_modify'] = empty($_REQUEST['pid']) || $_REQUEST['pid'] == 1 || $_REQUEST['pid'] > 4;

	// Load the proper template.
	Utils::$context['sub_template'] = 'permission_index';
	createToken('admin-mpq');
}

/**
 * Handle permissions by board... more or less. :P
 */
function PermissionByBoard()
{
	Utils::$context['page_title'] = Lang::$txt['permissions_boards'];
	Utils::$context['edit_all'] = isset($_GET['edit']);

	// Saving?
	if (!empty($_POST['save_changes']) && !empty($_POST['boardprofile']))
	{
		checkSession('request');
		validateToken('admin-mpb');

		$changes = array();
		foreach ($_POST['boardprofile'] as $pBoard => $profile)
		{
			$changes[(int) $profile][] = (int) $pBoard;
		}

		if (!empty($changes))
		{
			foreach ($changes as $profile => $boards)
				Db::$db->query('', '
					UPDATE {db_prefix}boards
					SET id_profile = {int:current_profile}
					WHERE id_board IN ({array_int:board_list})',
					array(
						'board_list' => $boards,
						'current_profile' => $profile,
					)
				);
		}

		Utils::$context['edit_all'] = false;
	}

	// Load all permission profiles.
	loadPermissionProfiles();

	// Get the board tree.
	Category::getTree();

	// Build the list of the boards.
	Utils::$context['categories'] = array();
	foreach (Category::$loaded as $catid => $tree)
	{
		Utils::$context['categories'][$catid] = array(
			'name' => &$tree->name,
			'id' => &$tree->id,
			'boards' => array()
		);
		foreach (Category::$boardList[$catid] as $boardid)
		{
			if (!isset(Utils::$context['profiles'][Board::$loaded[$boardid]->profile]))
				Board::$loaded[$boardid]->profile = 1;

			Utils::$context['categories'][$catid]['boards'][$boardid] = array(
				'id' => &Board::$loaded[$boardid]->id,
				'name' => &Board::$loaded[$boardid]->name,
				'description' => &Board::$loaded[$boardid]->description,
				'child_level' => &Board::$loaded[$boardid]->child_level,
				'profile' => &Board::$loaded[$boardid]->profile,
				'profile_name' => Utils::$context['profiles'][Board::$loaded[$boardid]->profile]['name'],
			);
		}
	}

	Utils::$context['sub_template'] = 'by_board';
	createToken('admin-mpb');
}

/**
 * Handles permission modification actions from the upper part of the
 * permission manager index.
 */
function SetQuickGroups()
{
	checkSession();
	validateToken('admin-mpq', 'quick');

	loadIllegalPermissions();
	loadIllegalGuestPermissions();
	loadIllegalBBCHtmlGroups();

	// Make sure only one of the quick options was selected.
	if ((!empty($_POST['predefined']) && ((isset($_POST['copy_from']) && $_POST['copy_from'] != 'empty') || !empty($_POST['permissions']))) || (!empty($_POST['copy_from']) && $_POST['copy_from'] != 'empty' && !empty($_POST['permissions'])))
		fatal_lang_error('permissions_only_one_option', false);

	if (empty($_POST['group']) || !is_array($_POST['group']))
		$_POST['group'] = array();

	// Only accept numeric values for selected membergroups.
	foreach ($_POST['group'] as $id => $group_id)
		$_POST['group'][$id] = (int) $group_id;
	$_POST['group'] = array_unique($_POST['group']);

	if (empty($_REQUEST['pid']))
		$_REQUEST['pid'] = 0;
	else
		$_REQUEST['pid'] = (int) $_REQUEST['pid'];

	// Fix up the old global to the new default!
	$bid = max(1, $_REQUEST['pid']);

	// No modifying the predefined profiles.
	if ($_REQUEST['pid'] > 1 && $_REQUEST['pid'] < 5)
		fatal_lang_error('no_access', false);

	// Clear out any cached authority.
	Config::updateModSettings(array('settings_updated' => time()));

	// No groups were selected.
	if (empty($_POST['group']))
		redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

	// Set a predefined permission profile.
	if (!empty($_POST['predefined']))
	{
		// Make sure it's a predefined permission set we expect.
		if (!in_array($_POST['predefined'], array('restrict', 'standard', 'moderator', 'maintenance')))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		foreach ($_POST['group'] as $group_id)
		{
			if (!empty($_REQUEST['pid']))
				setPermissionLevel($_POST['predefined'], $group_id, $_REQUEST['pid']);
			else
				setPermissionLevel($_POST['predefined'], $group_id);
		}
	}
	// Set a permission profile based on the permissions of a selected group.
	elseif ($_POST['copy_from'] != 'empty')
	{
		// Just checking the input.
		if (!is_numeric($_POST['copy_from']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		// Make sure the group we're copying to is never included.
		$_POST['group'] = array_diff($_POST['group'], array($_POST['copy_from']));

		// No groups left? Too bad.
		if (empty($_POST['group']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		if (empty($_REQUEST['pid']))
		{
			// Retrieve current permissions of group.
			$request = Db::$db->query('', '
				SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $_POST['copy_from'],
				)
			);
			$target_perm = array();
			while ($row = Db::$db->fetch_assoc($request))
				$target_perm[$row['permission']] = $row['add_deny'];
			Db::$db->free_result($request);

			$inserts = array();
			foreach ($_POST['group'] as $group_id)
				foreach ($target_perm as $perm => $add_deny)
				{
					// No dodgy permissions please!
					if (!empty(Utils::$context['illegal_permissions']) && in_array($perm, Utils::$context['illegal_permissions']))
						continue;
					if (isset(Utils::$context['permissions_excluded'][$perm]) && in_array($group_id, Utils::$context['permissions_excluded'][$perm]))
						continue;

					if ($group_id != 1 && $group_id != 3)
						$inserts[] = array($perm, $group_id, $add_deny);
				}

			// Delete the previous permissions...
			Db::$db->query('', '
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:group_list})
					' . (empty(Utils::$context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
				array(
					'group_list' => $_POST['group'],
					'illegal_permissions' => !empty(Utils::$context['illegal_permissions']) ? Utils::$context['illegal_permissions'] : array(),
				)
			);

			if (!empty($inserts))
			{
				// ..and insert the new ones.
				Db::$db->insert('',
					'{db_prefix}permissions',
					array(
						'permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int',
					),
					$inserts,
					array('permission', 'id_group')
				);
			}
		}

		// Now do the same for the board permissions.
		$request = Db::$db->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group = {int:copy_from}
				AND id_profile = {int:current_profile}',
			array(
				'copy_from' => $_POST['copy_from'],
				'current_profile' => $bid,
			)
		);
		$target_perm = array();
		while ($row = Db::$db->fetch_assoc($request))
			$target_perm[$row['permission']] = $row['add_deny'];
		Db::$db->free_result($request);

		$inserts = array();
		foreach ($_POST['group'] as $group_id)
			foreach ($target_perm as $perm => $add_deny)
			{
				// Are these for guests?
				if ($group_id == -1 && in_array($perm, Utils::$context['non_guest_permissions']))
					continue;

				$inserts[] = array($perm, $group_id, $bid, $add_deny);
			}

		// Delete the previous global board permissions...
		Db::$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:current_group_list})
				AND id_profile = {int:current_profile}',
			array(
				'current_group_list' => $_POST['group'],
				'current_profile' => $bid,
			)
		);

		// And insert the copied permissions.
		if (!empty($inserts))
		{
			// ..and insert the new ones.
			Db::$db->insert('',
				'{db_prefix}board_permissions',
				array('permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'),
				$inserts,
				array('permission', 'id_group', 'id_profile')
			);
		}

		// Update any children out there!
		updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}
	// Set or unset a certain permission for the selected groups.
	elseif (!empty($_POST['permissions']))
	{
		// Unpack two variables that were transported.
		list ($permissionType, $permission) = explode('/', $_POST['permissions']);

		// Check whether our input is within expected range.
		if (!in_array($_POST['add_remove'], array('add', 'clear', 'deny')) || !in_array($permissionType, array('membergroup', 'board')))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		if ($_POST['add_remove'] == 'clear')
		{
			if ($permissionType == 'membergroup')
			{
				Db::$db->query('', '
					DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND permission = {string:current_permission}
						' . (empty(Utils::$context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
					array(
						'current_group_list' => $_POST['group'],
						'current_permission' => $permission,
						'illegal_permissions' => !empty(Utils::$context['illegal_permissions']) ? Utils::$context['illegal_permissions'] : array(),
					)
				);

				// Did these changes make anyone lose eligibility for the bbc_html permission?
				$bbc_html_groups = array_diff($_POST['group'], Utils::$context['permissions_excluded']['bbc_html']);
				if (!empty($bbc_html_groups))
					removeIllegalBBCHtmlPermission(true);
			}
			else
				Db::$db->query('', '
					DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND id_profile = {int:current_profile}
						AND permission = {string:current_permission}',
					array(
						'current_group_list' => $_POST['group'],
						'current_profile' => $bid,
						'current_permission' => $permission,
					)
				);
		}
		// Add a permission (either 'set' or 'deny').
		else
		{
			$add_deny = $_POST['add_remove'] == 'add' ? '1' : '0';
			$permChange = array();
			foreach ($_POST['group'] as $groupID)
			{
				if (isset(Utils::$context['permissions_excluded'][$permission]) && in_array($groupID, Utils::$context['permissions_excluded'][$permission]))
					continue;

				if ($permissionType == 'membergroup' && $groupID != 1 && $groupID != 3 && (empty(Utils::$context['illegal_permissions']) || !in_array($permission, Utils::$context['illegal_permissions'])))
					$permChange[] = array($permission, $groupID, $add_deny);
				elseif ($permissionType != 'membergroup')
					$permChange[] = array($permission, $groupID, $bid, $add_deny);
			}

			if (!empty($permChange))
			{
				if ($permissionType == 'membergroup')
					Db::$db->insert('replace',
						'{db_prefix}permissions',
						array('permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'),
						$permChange,
						array('permission', 'id_group')
					);
				// Board permissions go into the other table.
				else
					Db::$db->insert('replace',
						'{db_prefix}board_permissions',
						array('permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'),
						$permChange,
						array('permission', 'id_group', 'id_profile')
					);
			}
		}

		// Another child update!
		updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}

	updateBoardManagers();

	redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
}

/**
 * Initializes the necessary to modify a membergroup's permissions.
 */
function ModifyMembergroup()
{
	if (!isset($_GET['group']))
		fatal_lang_error('no_access', false);

	Utils::$context['group']['id'] = (int) $_GET['group'];

	// It's not likely you'd end up here with this setting disabled.
	if ($_GET['group'] == 1)
		redirectexit('action=admin;area=permissions');

	loadAllPermissions();
	loadPermissionProfiles();
	Utils::$context['hidden_perms'] = array();

	if (Utils::$context['group']['id'] > 0)
	{
		$result = Db::$db->query('', '
			SELECT group_name, id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => Utils::$context['group']['id'],
			)
		);
		list(Utils::$context['group']['name'], $parent) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// Cannot edit an inherited group!
		if ($parent != -2)
			fatal_lang_error('cannot_edit_permissions_inherited');
	}
	elseif (Utils::$context['group']['id'] == -1)
		Utils::$context['group']['name'] = Lang::$txt['membergroups_guests'];
	else
		Utils::$context['group']['name'] = Lang::$txt['membergroups_members'];

	Utils::$context['profile']['id'] = empty($_GET['pid']) ? 0 : (int) $_GET['pid'];

	// If this is a moderator and they are editing "no profile" then we only do boards.
	if (Utils::$context['group']['id'] == 3 && empty(Utils::$context['profile']['id']))
	{
		// For sanity just check they have no general permissions.
		Db::$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:moderator_group}',
			array(
				'moderator_group' => 3,
			)
		);

		Utils::$context['profile']['id'] = 1;
	}

	Utils::$context['permission_type'] = empty(Utils::$context['profile']['id']) ? 'membergroup' : 'board';
	Utils::$context['profile']['can_modify'] = !Utils::$context['profile']['id'] || Utils::$context['profiles'][Utils::$context['profile']['id']]['can_modify'];

	// Set up things a little nicer for board related stuff...
	if (Utils::$context['permission_type'] == 'board')
	{
		Utils::$context['profile']['name'] = Utils::$context['profiles'][Utils::$context['profile']['id']]['name'];
		Utils::$context[Utils::$context['admin_menu_name']]['current_subsection'] = 'profiles';
	}

	// Fetch the current permissions.
	$permissions = array(
		'membergroup' => array('allowed' => array(), 'denied' => array()),
		'board' => array('allowed' => array(), 'denied' => array())
	);

	// General permissions?
	if (Utils::$context['permission_type'] == 'membergroup')
	{
		$result = Db::$db->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_GET['group'],
			)
		);
		while ($row = Db::$db->fetch_assoc($result))
			$permissions['membergroup'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		Db::$db->free_result($result);
	}

	// Fetch current board permissions...
	$result = Db::$db->query('', '
		SELECT permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		array(
			'current_group' => Utils::$context['group']['id'],
			'current_profile' => Utils::$context['permission_type'] == 'membergroup' ? 1 : Utils::$context['profile']['id'],
		)
	);
	while ($row = Db::$db->fetch_assoc($result))
		$permissions['board'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
	Db::$db->free_result($result);

	// Loop through each permission and set whether it's checked.
	foreach (Utils::$context['permissions'] as $permissionType => $tmp)
	{
		foreach ($tmp['columns'] as $position => $permissionGroups)
		{
			foreach ($permissionGroups as $permissionGroup => $permissionArray)
			{
				foreach ($permissionArray['permissions'] as $perm)
				{
					// Create a shortcut for the current permission.
					$curPerm = &Utils::$context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['permissions'][$perm['id']];

					if ($perm['has_own_any'])
					{
						$curPerm['any']['select'] = in_array($perm['id'] . '_any', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $permissions[$permissionType]['denied']) ? 'deny' : 'off');
						$curPerm['own']['select'] = in_array($perm['id'] . '_own', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $permissions[$permissionType]['denied']) ? 'deny' : 'off');
					}
					else
						$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'deny' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');

					// Keep the last value if it's hidden.
					if ($perm['hidden'] || $permissionArray['hidden'])
					{
						if ($perm['has_own_any'])
						{
							Utils::$context['hidden_perms'][] = array(
								$permissionType,
								$perm['own']['id'],
								$curPerm['own']['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $curPerm['own']['select'],
							);
							Utils::$context['hidden_perms'][] = array(
								$permissionType,
								$perm['any']['id'],
								$curPerm['any']['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $curPerm['any']['select'],
							);
						}
						else
							Utils::$context['hidden_perms'][] = array(
								$permissionType,
								$perm['id'],
								$curPerm['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $curPerm['select'],
							);
					}
				}
			}
		}
	}
	Utils::$context['sub_template'] = 'modify_group';
	Utils::$context['page_title'] = Lang::$txt['permissions_modify_group'];

	createToken('admin-mp');
}

/**
 * This function actually saves modifications to a membergroup's board permissions.
 */
function ModifyMembergroup2()
{
	checkSession();
	validateToken('admin-mp');

	loadIllegalPermissions();

	$_GET['group'] = (int) $_GET['group'];
	$_GET['pid'] = (int) $_GET['pid'];

	// Cannot modify predefined profiles.
	if ($_GET['pid'] > 1 && $_GET['pid'] < 5)
		fatal_lang_error('no_access', false);

	// Verify this isn't inherited.
	if ($_GET['group'] == -1 || $_GET['group'] == 0)
		$parent = -2;
	else
	{
		$result = Db::$db->query('', '
			SELECT id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => $_GET['group'],
			)
		);
		list ($parent) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);
	}

	if ($parent != -2)
		fatal_lang_error('cannot_edit_permissions_inherited');

	$givePerms = array('membergroup' => array(), 'board' => array());

	// Guest group, we need illegal, guest permissions.
	if ($_GET['group'] == -1)
	{
		loadIllegalGuestPermissions();
		Utils::$context['illegal_permissions'] = array_merge(Utils::$context['illegal_permissions'], Utils::$context['non_guest_permissions']);
	}

	// Prepare all permissions that were set or denied for addition to the DB.
	if (isset($_POST['perm']) && is_array($_POST['perm']))
	{
		foreach ($_POST['perm'] as $perm_type => $perm_array)
		{
			if (is_array($perm_array))
			{
				foreach ($perm_array as $permission => $value)
					if ($value == 'on' || $value == 'deny')
					{
						// Don't allow people to escalate themselves!
						if (!empty(Utils::$context['illegal_permissions']) && in_array($permission, Utils::$context['illegal_permissions']))
							continue;

						$givePerms[$perm_type][] = array($_GET['group'], $permission, $value == 'deny' ? 0 : 1);
					}
			}
		}
	}

	// Insert the general permissions.
	if ($_GET['group'] != 3 && empty($_GET['pid']))
	{
		Db::$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty(Utils::$context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $_GET['group'],
				'illegal_permissions' => !empty(Utils::$context['illegal_permissions']) ? Utils::$context['illegal_permissions'] : array(),
			)
		);

		if (!empty($givePerms['membergroup']))
		{
			Db::$db->insert('replace',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$givePerms['membergroup'],
				array('id_group', 'permission')
			);
		}
	}

	// Insert the boardpermissions.
	$profileid = max(1, $_GET['pid']);
	Db::$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		array(
			'current_group' => $_GET['group'],
			'current_profile' => $profileid,
		)
	);
	if (!empty($givePerms['board']))
	{
		foreach ($givePerms['board'] as $k => $v)
			$givePerms['board'][$k][] = $profileid;

		Db::$db->insert('replace',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int', 'id_profile' => 'int'),
			$givePerms['board'],
			array('id_group', 'permission', 'id_profile')
		);
	}

	// Update any inherited permissions as required.
	updateChildPermissions($_GET['group'], $_GET['pid']);

	removeIllegalBBCHtmlPermission();

	// Make sure Config::$modSettings['board_manager_groups'] is up to date.
	if (!in_array('manage_boards', Utils::$context['illegal_permissions']))
		updateBoardManagers();

	// Clear cached privs.
	Config::updateModSettings(array('settings_updated' => time()));

	redirectexit('action=admin;area=permissions;pid=' . $_GET['pid']);
}

/**
 * A screen to set some general settings for permissions.
 *
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the config_vars array if $return_config is true
 */
function GeneralPermissionSettings($return_config = false)
{
	// All the setting variables
	$config_vars = array(
		array('title', 'settings'),
		// Inline permissions.
		array('permissions', 'manage_permissions'),
		'',

		// A few useful settings
		array('check', 'permission_enable_deny', 0, Lang::$txt['permission_settings_enable_deny'], 'help' => 'permissions_deny'),
		array('check', 'permission_enable_postgroups', 0, Lang::$txt['permission_settings_enable_postgroups'], 'help' => 'permissions_postgroups'),
	);

	call_integration_hook('integrate_modify_permission_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	Utils::$context['page_title'] = Lang::$txt['permission_settings_title'];
	Utils::$context['sub_template'] = 'show_settings';

	// Needed for the inline permission functions, and the settings template.
	require_once(Config::$sourcedir . '/ManageServer.php');

	Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=permissions;save;sa=settings';

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();
		call_integration_hook('integrate_save_permission_settings');
		saveDBSettings($config_vars);

		// Clear all deny permissions...if we want that.
		if (empty(Config::$modSettings['permission_enable_deny']))
		{
			Db::$db->query('', '
				DELETE FROM {db_prefix}permissions
				WHERE add_deny = {int:denied}',
				array(
					'denied' => 0,
				)
			);
			Db::$db->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE add_deny = {int:denied}',
				array(
					'denied' => 0,
				)
			);
		}

		// Make sure there are no postgroup based permissions left.
		if (empty(Config::$modSettings['permission_enable_postgroups']))
		{
			// Get a list of postgroups.
			$post_groups = array();
			$request = Db::$db->query('', '
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE min_posts != {int:min_posts}',
				array(
					'min_posts' => -1,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
				$post_groups[] = $row['id_group'];
			Db::$db->free_result($request);

			// Remove'em.
			Db::$db->query('', '
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
				)
			);
			Db::$db->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
				)
			);
			Db::$db->query('', '
				UPDATE {db_prefix}membergroups
				SET id_parent = {int:not_inherited}
				WHERE id_parent IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
					'not_inherited' => -2,
				)
			);
		}

		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=permissions;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

/**
 * Set the permission level for a specific profile, group, or group for a profile.
 *
 * @internal
 *
 * @param string $level The level ('restrict', 'standard', etc.)
 * @param int $group The group to set the permission for
 * @param string|int $profile The ID of the permissions profile or 'null' if we're setting it for a group
 */
function setPermissionLevel($level, $group, $profile = 'null')
{
	loadIllegalPermissions();
	loadIllegalGuestPermissions();
	loadIllegalBBCHtmlGroups();

	// Levels by group... restrict, standard, moderator, maintenance.
	$groupLevels = array(
		'board' => array('inherit' => array()),
		'group' => array('inherit' => array())
	);
	// Levels by board... standard, publish, free.
	$boardLevels = array('inherit' => array());

	// Restrictive - ie. guests.
	$groupLevels['global']['restrict'] = array(
		'search_posts',
		'calendar_view',
		'view_stats',
		'who_view',
		'profile_identity_own',
	);
	$groupLevels['board']['restrict'] = array(
		'poll_view',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'report_any',
	);

	// Standard - ie. members.  They can do anything Restrictive can.
	$groupLevels['global']['standard'] = array_merge($groupLevels['global']['restrict'], array(
		'view_mlist',
		'likes_like',
		'mention',
		'pm_read',
		'pm_send',
		'profile_view',
		'profile_extra_own',
		'profile_signature_own',
		'profile_forum_own',
		'profile_website_own',
		'profile_password_own',
		'profile_server_avatar',
		'profile_displayed_name',
		'profile_upload_avatar',
		'profile_remote_avatar',
		'profile_remove_own',
		'report_user',
	));
	$groupLevels['board']['standard'] = array_merge($groupLevels['board']['restrict'], array(
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'post_attachment',
		'lock_own',
		'remove_own',
		'view_attachments',
	));

	// Moderator - ie. moderators :P.  They can do what standard can, and more.
	$groupLevels['global']['moderator'] = array_merge($groupLevels['global']['standard'], array(
		'calendar_post',
		'calendar_edit_own',
		'access_mod_center',
		'issue_warning',
	));
	$groupLevels['board']['moderator'] = array_merge($groupLevels['board']['standard'], array(
		'make_sticky',
		'poll_edit_any',
		'delete_any',
		'modify_any',
		'lock_any',
		'remove_any',
		'move_any',
		'merge_any',
		'split_any',
		'poll_lock_any',
		'poll_remove_any',
		'poll_add_any',
		'approve_posts',
	));

	// Maintenance - wannabe admins.  They can do almost everything.
	$groupLevels['global']['maintenance'] = array_merge($groupLevels['global']['moderator'], array(
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'moderate_forum',
		'manage_membergroups',
		'manage_bans',
		'admin_forum',
		'bbc_html',
		'manage_permissions',
		'edit_news',
		'calendar_edit_any',
		'profile_identity_any',
		'profile_extra_any',
		'profile_signature_any',
		'profile_website_any',
		'profile_displayed_name_any',
		'profile_password_any',
		'profile_title_any',
	));
	$groupLevels['board']['maintenance'] = array_merge($groupLevels['board']['moderator'], array(
	));

	// Standard - nothing above the group permissions. (this SHOULD be empty.)
	$boardLevels['standard'] = array(
	);

	// Locked - just that, you can't post here.
	$boardLevels['locked'] = array(
		'poll_view',
		'report_any',
		'view_attachments',
	);

	// Publisher - just a little more...
	$boardLevels['publish'] = array_merge($boardLevels['locked'], array(
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'delete_replies',
		'modify_replies',
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'poll_remove_own',
		'post_attachment',
		'lock_own',
		'remove_own',
	));

	// Free for All - Scary.  Just scary.
	$boardLevels['free'] = array_merge($boardLevels['publish'], array(
		'poll_lock_any',
		'poll_edit_any',
		'poll_add_any',
		'poll_remove_any',
		'make_sticky',
		'lock_any',
		'remove_any',
		'delete_any',
		'split_any',
		'merge_any',
		'modify_any',
		'approve_posts',
	));

	call_integration_hook('integrate_load_permission_levels', array(&$groupLevels, &$boardLevels));

	// Make sure we're not granting someone too many permissions!
	foreach ($groupLevels['global'][$level] as $k => $permission)
	{
		if (!empty(Utils::$context['illegal_permissions']) && in_array($permission, Utils::$context['illegal_permissions']))
			unset($groupLevels['global'][$level][$k]);

		if (isset(Utils::$context['permissions_excluded'][$permission]) && in_array($group, Utils::$context['permissions_excluded'][$permission]))
			unset($groupLevels['global'][$level][$k]);
	}
	foreach ($groupLevels['board'][$level] as $k => $permission)
		if (isset(Utils::$context['permissions_excluded'][$permission]) && in_array($group, Utils::$context['permissions_excluded'][$permission]))
			unset($groupLevels['board'][$level][$k]);

	// Reset all cached permissions.
	Config::updateModSettings(array('settings_updated' => time()));

	// Setting group permissions.
	if ($profile === 'null' && $group !== 'null')
	{
		$group = (int) $group;

		if (empty($groupLevels['global'][$level]))
			return;

		Db::$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty(Utils::$context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $group,
				'illegal_permissions' => !empty(Utils::$context['illegal_permissions']) ? Utils::$context['illegal_permissions'] : array(),
			)
		);
		Db::$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:default_profile}',
			array(
				'current_group' => $group,
				'default_profile' => 1,
			)
		);

		$groupInserts = array();
		foreach ($groupLevels['global'][$level] as $permission)
			$groupInserts[] = array($group, $permission);

		Db::$db->insert('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string'),
			$groupInserts,
			array('id_group')
		);

		$boardInserts = array();
		foreach ($groupLevels['board'][$level] as $permission)
			$boardInserts[] = array(1, $group, $permission);

		Db::$db->insert('insert',
			'{db_prefix}board_permissions',
			array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
			$boardInserts,
			array('id_profile', 'id_group')
		);

		removeIllegalBBCHtmlPermission();
	}
	// Setting profile permissions for a specific group.
	elseif ($profile !== 'null' && $group !== 'null' && ($profile == 1 || $profile > 4))
	{
		$group = (int) $group;
		$profile = (int) $profile;

		if (!empty($groupLevels['global'][$level]))
		{
			Db::$db->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group = {int:current_group}
					AND id_profile = {int:current_profile}',
				array(
					'current_group' => $group,
					'current_profile' => $profile,
				)
			);
		}

		if (!empty($groupLevels['board'][$level]))
		{
			$boardInserts = array();
			foreach ($groupLevels['board'][$level] as $permission)
				$boardInserts[] = array($profile, $group, $permission);

			Db::$db->insert('insert',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
		}
	}
	// Setting profile permissions for all groups.
	elseif ($profile !== 'null' && $group === 'null' && ($profile == 1 || $profile > 4))
	{
		$profile = (int) $profile;

		Db::$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}',
			array(
				'current_profile' => $profile,
			)
		);

		if (empty($boardLevels[$level]))
			return;

		// Get all the groups...
		$query = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group > {int:moderator_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'newbie_group' => 4,
			)
		);
		while ($row = Db::$db->fetch_row($query))
		{
			$group = $row[0];

			$boardInserts = array();
			foreach ($boardLevels[$level] as $permission)
				$boardInserts[] = array($profile, $group, $permission);

			Db::$db->insert('insert',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
		}
		Db::$db->free_result($query);

		// Add permissions for ungrouped members.
		$boardInserts = array();
		foreach ($boardLevels[$level] as $permission)
			$boardInserts[] = array($profile, 0, $permission);

		Db::$db->insert('insert',
			'{db_prefix}board_permissions',
			array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
			$boardInserts,
			array('id_profile', 'id_group')
		);
	}
	// $profile and $group are both null!
	else
		fatal_lang_error('no_access', false);

	// Make sure Config::$modSettings['board_manager_groups'] is up to date.
	if (!in_array('manage_boards', Utils::$context['illegal_permissions']))
		updateBoardManagers();
}

/**
 * Load permissions into Utils::$context['permissions'].
 *
 * @internal
 */
function loadAllPermissions()
{
	// List of all the groups dependent on the currently selected view - for the order so it looks pretty, yea?
	// Note to Mod authors - you don't need to stick your permission group here if you don't mind SMF sticking it the last group of the page.
	$permissionGroups = array(
		'membergroup' => array(
			'general',
			'pm',
			'calendar',
			'maintenance',
			'member_admin',
			'profile',
			'likes',
			'mentions',
			'bbc',
		),
		'board' => array(
			'general_board',
			'topic',
			'post',
			'poll',
			'notification',
			'attachment',
		),
	);

	/*   The format of this list is as follows:
		'membergroup' => array(
			'permissions_inside' => array(has_multiple_options, view_group),
		),
		'board' => array(
			'permissions_inside' => array(has_multiple_options, view_group),
		);
	*/
	$permissionList = array(
		'membergroup' => array(
			'view_stats' => array(false, 'general'),
			'view_mlist' => array(false, 'general'),
			'who_view' => array(false, 'general'),
			'search_posts' => array(false, 'general'),
			'pm_read' => array(false, 'pm'),
			'pm_send' => array(false, 'pm'),
			'pm_draft' => array(false, 'pm'),
			'calendar_view' => array(false, 'calendar'),
			'calendar_post' => array(false, 'calendar'),
			'calendar_edit' => array(true, 'calendar'),
			'admin_forum' => array(false, 'maintenance'),
			'manage_boards' => array(false, 'maintenance'),
			'manage_attachments' => array(false, 'maintenance'),
			'manage_smileys' => array(false, 'maintenance'),
			'edit_news' => array(false, 'maintenance'),
			'access_mod_center' => array(false, 'maintenance'),
			'moderate_forum' => array(false, 'member_admin'),
			'manage_membergroups' => array(false, 'member_admin'),
			'manage_permissions' => array(false, 'member_admin'),
			'manage_bans' => array(false, 'member_admin'),
			'send_mail' => array(false, 'member_admin'),
			'issue_warning' => array(false, 'member_admin'),
			'profile_view' => array(false, 'profile'),
			'profile_forum' => array(true, 'profile'),
			'profile_extra' => array(true, 'profile'),
			'profile_signature' => array(true, 'profile'),
			'profile_website' => array(true, 'profile'),
			'profile_title' => array(true, 'profile'),
			'profile_blurb' => array(true, 'profile'),
			'profile_server_avatar' => array(false, 'profile'),
			'profile_upload_avatar' => array(false, 'profile'),
			'profile_remote_avatar' => array(false, 'profile'),
			'report_user' => array(false, 'profile'),
			'profile_identity' => array(true, 'profile_account'),
			'profile_displayed_name' => array(true, 'profile_account'),
			'profile_password' => array(true, 'profile_account'),
			'profile_remove' => array(true, 'profile_account'),
			'view_warning' => array(true, 'profile_account'),
			'likes_like' => array(false, 'likes'),
			'mention' => array(false, 'mentions'),
		),
		'board' => array(
			'moderate_board' => array(false, 'general_board'),
			'approve_posts' => array(false, 'general_board'),
			'post_new' => array(false, 'topic'),
			'post_unapproved_topics' => array(false, 'topic'),
			'post_reply' => array(true, 'topic'),
			'post_unapproved_replies' => array(true, 'topic'),
			'post_draft' => array(false, 'topic'),
			'merge_any' => array(false, 'topic'),
			'split_any' => array(false, 'topic'),
			'make_sticky' => array(false, 'topic'),
			'move' => array(true, 'topic', 'moderate'),
			'lock' => array(true, 'topic', 'moderate'),
			'remove' => array(true, 'topic', 'modify'),
			'modify_replies' => array(false, 'topic'),
			'delete_replies' => array(false, 'topic'),
			'announce_topic' => array(false, 'topic'),
			'delete' => array(true, 'post'),
			'modify' => array(true, 'post'),
			'report_any' => array(false, 'post'),
			'poll_view' => array(false, 'poll'),
			'poll_vote' => array(false, 'poll'),
			'poll_post' => array(false, 'poll'),
			'poll_add' => array(true, 'poll'),
			'poll_edit' => array(true, 'poll'),
			'poll_lock' => array(true, 'poll'),
			'poll_remove' => array(true, 'poll'),
			'view_attachments' => array(false, 'attachment'),
			'post_unapproved_attachments' => array(false, 'attachment'),
			'post_attachment' => array(false, 'attachment'),
		),
	);

	// In case a mod screwed things up...
	if (!in_array('html', Utils::$context['restricted_bbc']))
		Utils::$context['restricted_bbc'][] = 'html';

	// Add the permissions for the restricted BBCodes
	foreach (Utils::$context['restricted_bbc'] as $bbc)
	{
		$permissionList['membergroup']['bbc_' . $bbc] = array(false, 'bbc');
		Lang::$txt['permissionname_bbc_' . $bbc] = sprintf(Lang::$txt['permissionname_bbc'], $bbc);
	}

	// All permission groups that will be shown in the left column on classic view.
	$leftPermissionGroups = array(
		'general',
		'calendar',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	);

	// We need to know what permissions we can't give to guests.
	loadIllegalGuestPermissions();

	// We also need to know which groups can't be given the bbc_html permission.
	loadIllegalBBCHtmlGroups();

	// Some permissions are hidden if features are off.
	$hiddenPermissions = array();
	$relabelPermissions = array(); // Permissions to apply a different label to.
	if (empty(Config::$modSettings['cal_enabled']))
	{
		$hiddenPermissions[] = 'calendar_view';
		$hiddenPermissions[] = 'calendar_post';
		$hiddenPermissions[] = 'calendar_edit';
	}
	if (Config::$modSettings['warning_settings'][0] == 0)
	{
		$hiddenPermissions[] = 'issue_warning';
		$hiddenPermissions[] = 'view_warning';
	}

	// Post moderation?
	if (!Config::$modSettings['postmod_active'])
	{
		$hiddenPermissions[] = 'approve_posts';
		$hiddenPermissions[] = 'post_unapproved_topics';
		$hiddenPermissions[] = 'post_unapproved_replies';
		$hiddenPermissions[] = 'post_unapproved_attachments';
	}
	// If post moderation is enabled, these are named differently...
	else
	{
		// Relabel the topics permissions
		$relabelPermissions['post_new'] = 'auto_approve_topics';

		// Relabel the reply permissions
		$relabelPermissions['post_reply'] = 'auto_approve_replies';

		// Relabel the attachment permissions
		$relabelPermissions['post_attachment'] = 'auto_approve_attachments';
	}

	// Are attachments enabled?
	if (empty(Config::$modSettings['attachmentEnable']))
	{
		$hiddenPermissions[] = 'manage_attachments';
		$hiddenPermissions[] = 'view_attachments';
		$hiddenPermissions[] = 'post_unapproved_attachments';
		$hiddenPermissions[] = 'post_attachment';
	}

	// Hide Likes/Mentions permissions...
	if (empty(Config::$modSettings['enable_likes']))
	{
		$hiddenPermissions[] = 'likes_like';
	}
	if (empty(Config::$modSettings['enable_mentions']))
	{
		$hiddenPermissions[] = 'mention';
	}

	// Provide a practical way to modify permissions.
	call_integration_hook('integrate_load_permissions', array(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions));

	$permissionList['membergroup']['bbc_cowsay'] = array(false, 'bbc');
	$hiddenPermissions[] = 'bbc_cowsay';
	Lang::$txt['permissionname_bbc_cowsay'] = sprintf(Lang::$txt['permissionname_bbc'], 'cowsay');

	Utils::$context['permissions'] = array();
	Utils::$context['hidden_permissions'] = array();
	foreach ($permissionList as $permissionType => $permissionList)
	{
		Utils::$context['permissions'][$permissionType] = array(
			'id' => $permissionType,
			'columns' => array()
		);
		foreach ($permissionList as $permission => $permissionArray)
		{
			// If this permission shouldn't be given to certain groups (e.g. guests), don't.
			if (isset(Utils::$context['group']['id']) && isset(Utils::$context['permissions_excluded'][$permission]) && in_array(Utils::$context['group']['id'], Utils::$context['permissions_excluded'][$permission]))
				continue;

			// What groups will this permission be in?
			$own_group = $permissionArray[1];

			// First, Do these groups actually exist - if not add them.
			if (!isset($permissionGroups[$permissionType][$own_group]))
				$permissionGroups[$permissionType][$own_group] = true;

			// What column should this be located into?
			$position = !in_array($own_group, $leftPermissionGroups) ? 1 : 0;

			// If the groups have not yet been created be sure to create them.
			$bothGroups = array('own' => $own_group);

			foreach ($bothGroups as $group)
				if (!isset(Utils::$context['permissions'][$permissionType]['columns'][$position][$group]))
					Utils::$context['permissions'][$permissionType]['columns'][$position][$group] = array(
						'type' => $permissionType,
						'id' => $group,
						'name' => Lang::$txt['permissiongroup_' . $group],
						'icon' => isset(Lang::$txt['permissionicon_' . $group]) ? Lang::$txt['permissionicon_' . $group] : Lang::$txt['permissionicon'],
						'help' => isset(Lang::$txt['permissionhelp_' . $group]) ? Lang::$txt['permissionhelp_' . $group] : '',
						'hidden' => false,
						'permissions' => array()
					);

			Utils::$context['permissions'][$permissionType]['columns'][$position][$own_group]['permissions'][$permission] = array(
				'id' => $permission,
				'name' => !isset($relabelPermissions[$permission]) ? Lang::$txt['permissionname_' . $permission] : Lang::$txt[$relabelPermissions[$permission]],
				'show_help' => isset(Lang::$txt['permissionhelp_' . $permission]),
				'note' => isset(Lang::$txt['permissionnote_' . $permission]) ? Lang::$txt['permissionnote_' . $permission] : '',
				'has_own_any' => $permissionArray[0],
				'own' => array(
					'id' => $permission . '_own',
					'name' => $permissionArray[0] ? Lang::$txt['permissionname_' . $permission . '_own'] : ''
				),
				'any' => array(
					'id' => $permission . '_any',
					'name' => $permissionArray[0] ? Lang::$txt['permissionname_' . $permission . '_any'] : ''
				),
				'hidden' => in_array($permission, $hiddenPermissions),
			);

			if (in_array($permission, $hiddenPermissions))
			{
				if ($permissionArray[0])
				{
					Utils::$context['hidden_permissions'][] = $permission . '_own';
					Utils::$context['hidden_permissions'][] = $permission . '_any';
				}
				else
					Utils::$context['hidden_permissions'][] = $permission;
			}
		}
		ksort(Utils::$context['permissions'][$permissionType]['columns']);

		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach (Utils::$context['permissions'][$permissionType]['columns'] as $column => $groups)
			foreach ($groups as $id => $group)
			{
				if (empty($group['permissions']))
					unset(Utils::$context['permissions'][$permissionType]['columns'][$column][$id]);
				else
				{
					$foundNonHidden = false;
					foreach ($group['permissions'] as $permission)
						if (empty($permission['hidden']))
							$foundNonHidden = true;
					if (!$foundNonHidden)
						Utils::$context['permissions'][$permissionType]['columns'][$column][$id]['hidden'] = true;
				}
			}
	}
}

/**
 * Initialize a form with inline permissions settings.
 * It loads a context variable for each permission.
 * This function is used by several settings screens to set specific permissions.
 *
 * To exclude groups from the form for a given permission, add the group IDs as
 * an array to Utils::$context['excluded_permissions'][$permission]. For backwards
 * compatibility, it is also possible to pass group IDs in via the
 * $excluded_groups parameter, which will exclude the groups from the forms for
 * all of the permissions passed in via $permissions.
 *
 * @internal
 *
 * @param array $permissions The permissions to display inline
 * @param array $excluded_groups The IDs of one or more groups to exclude
 *
 * Uses ManagePermissions language
 * Uses ManagePermissions template
 */
function init_inline_permissions($permissions, $excluded_groups = array())
{
	Lang::load('ManagePermissions');
	Theme::loadTemplate('ManagePermissions');
	Utils::$context['can_change_permissions'] = allowedTo('manage_permissions');

	// Nothing to initialize here.
	if (!Utils::$context['can_change_permissions'])
		return;

	// Load the permission settings for guests
	foreach ($permissions as $permission)
		Utils::$context[$permission] = array(
			-1 => array(
				'id' => -1,
				'name' => Lang::$txt['membergroups_guests'],
				'is_postgroup' => false,
				'status' => 'off',
			),
			0 => array(
				'id' => 0,
				'name' => Lang::$txt['membergroups_members'],
				'is_postgroup' => false,
				'status' => 'off',
			),
		);

	$request = Db::$db->query('', '
		SELECT id_group, CASE WHEN add_deny = {int:denied} THEN {string:deny} ELSE {string:on} END AS status, permission
		FROM {db_prefix}permissions
		WHERE id_group IN (-1, 0)
			AND permission IN ({array_string:permissions})',
		array(
			'denied' => 0,
			'permissions' => $permissions,
			'deny' => 'deny',
			'on' => 'on',
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
		Utils::$context[$row['permission']][$row['id_group']]['status'] = $row['status'];
	Db::$db->free_result($request);

	$request = Db::$db->query('', '
		SELECT mg.id_group, mg.group_name, mg.min_posts, COALESCE(p.add_deny, -1) AS status, p.permission
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission IN ({array_string:permissions}))
		WHERE mg.id_group NOT IN (1, 3)
			AND mg.id_parent = {int:not_inherited}' . (empty(Config::$modSettings['permission_enable_postgroups']) ? '
			AND mg.min_posts = {int:min_posts}' : '') . '
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'not_inherited' => -2,
			'min_posts' => -1,
			'newbie_group' => 4,
			'permissions' => $permissions,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Initialize each permission as being 'off' until proven otherwise.
		foreach ($permissions as $permission)
			if (!isset(Utils::$context[$permission][$row['id_group']]))
				Utils::$context[$permission][$row['id_group']] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'is_postgroup' => $row['min_posts'] != -1,
					'status' => 'off',
				);

		Utils::$context[$row['permission']][$row['id_group']]['status'] = empty($row['status']) ? 'deny' : ($row['status'] == 1 ? 'on' : 'off');
	}
	Db::$db->free_result($request);

	// Make sure we honor the "illegal guest permissions"
	loadIllegalGuestPermissions();

	// Only special people can have this permission
	if (in_array('bbc_html', $permissions))
		loadIllegalBBCHtmlGroups();

	// Are any of these permissions that guests can't have?
	$non_guest_perms = array_intersect(str_replace(array('_any', '_own'), '', $permissions), Utils::$context['non_guest_permissions']);
	foreach ($non_guest_perms as $permission)
	{
		if (!isset(Utils::$context['permissions_excluded'][$permission]) || !in_array(-1, Utils::$context['permissions_excluded'][$permission]))
			Utils::$context['permissions_excluded'][$permission][] = -1;
	}

	// Any explicitly excluded groups for this call?
	if (!empty($excluded_groups))
	{
		// Make sure this is an array of integers
		$excluded_groups = array_filter(
			(array) $excluded_groups,
			function ($v)
			{
				return is_int($v) || is_string($v) && (string) intval($v) === $v;
			}
		);

		foreach ($permissions as $permission)
			Utils::$context['permissions_excluded'][$permission] = array_unique(array_merge(Utils::$context['permissions_excluded'][$permission], $excluded_groups));
	}

	// Some permissions cannot be given to certain groups. Remove the groups.
	foreach ($permissions as $permission)
	{
		if (!isset(Utils::$context['permissions_excluded'][$permission]))
			continue;

		foreach (Utils::$context['permissions_excluded'][$permission] as $group)
		{
			if (isset(Utils::$context[$permission][$group]))
				unset(Utils::$context[$permission][$group]);
		}

		// There's no point showing a form with nobody in it
		if (empty(Utils::$context[$permission]))
			unset(Utils::$context['config_vars'][$permission], Utils::$context[$permission]);
	}

	// Create the token for the separate inline permission verification.
	createToken('admin-mp');
}

/**
 * Show a collapsible box to set a specific permission.
 * The function is called by templates to show a list of permissions settings.
 * Calls the template function template_inline_permissions().
 *
 * @param string $permission The permission to display inline
 */
function theme_inline_permissions($permission)
{
	Utils::$context['current_permission'] = $permission;
	Utils::$context['member_groups'] = Utils::$context[$permission];

	template_inline_permissions();
}

/**
 * Save the permissions of a form containing inline permissions.
 *
 * @internal
 *
 * @param array $permissions The permissions to save
 */
function save_inline_permissions($permissions)
{
	// No permissions? Not a great deal to do here.
	if (!allowedTo('manage_permissions'))
		return;

	// Almighty session check, verify our ways.
	checkSession();
	validateToken('admin-mp');

	// Check they can't do certain things.
	loadIllegalPermissions();
	if (in_array('bbc_html', $permissions))
		loadIllegalBBCHtmlGroups();

	$insertRows = array();
	foreach ($permissions as $permission)
	{
		if (!isset($_POST[$permission]))
			continue;

		foreach ($_POST[$permission] as $id_group => $value)
		{
			if ($value == 'on' && !empty(Utils::$context['excluded_permissions'][$permission]) && in_array($id_group, Utils::$context['excluded_permissions'][$permission]))
				continue;

			if (in_array($value, array('on', 'deny')) && (empty(Utils::$context['illegal_permissions']) || !in_array($permission, Utils::$context['illegal_permissions'])))
				$insertRows[] = array((int) $id_group, $permission, $value == 'on' ? 1 : 0);
		}
	}

	// Remove the old permissions...
	Db::$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE permission IN ({array_string:permissions})
			' . (empty(Utils::$context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		array(
			'illegal_permissions' => !empty(Utils::$context['illegal_permissions']) ? Utils::$context['illegal_permissions'] : array(),
			'permissions' => $permissions,
		)
	);

	// ...and replace them with new ones.
	if (!empty($insertRows))
		Db::$db->insert('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$insertRows,
			array('id_group', 'permission')
		);

	// Do a full child update.
	updateChildPermissions(array(), -1);

	// Make sure Config::$modSettings['board_manager_groups'] is up to date.
	if (!in_array('manage_boards', Utils::$context['illegal_permissions']))
		updateBoardManagers();

	Config::updateModSettings(array('settings_updated' => time()));
}

/**
 * Load permissions profiles.
 */
function loadPermissionProfiles()
{
	$request = Db::$db->query('', '
		SELECT id_profile, profile_name
		FROM {db_prefix}permission_profiles
		ORDER BY id_profile',
		array(
		)
	);
	Utils::$context['profiles'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Format the label nicely.
		if (isset(Lang::$txt['permissions_profile_' . $row['profile_name']]))
			$name = Lang::$txt['permissions_profile_' . $row['profile_name']];
		else
			$name = $row['profile_name'];

		Utils::$context['profiles'][$row['id_profile']] = array(
			'id' => $row['id_profile'],
			'name' => $name,
			'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
			'unformatted_name' => $row['profile_name'],
		);
	}
	Db::$db->free_result($request);
}

/**
 * Add/Edit/Delete profiles.
 */
function EditPermissionProfiles()
{
	// Setup the template, first for fun.
	Utils::$context['page_title'] = Lang::$txt['permissions_profile_edit'];
	Utils::$context['sub_template'] = 'edit_profiles';

	// If we're creating a new one do it first.
	if (isset($_POST['create']) && trim($_POST['profile_name']) != '')
	{
		checkSession();
		validateToken('admin-mpp');

		$_POST['copy_from'] = (int) $_POST['copy_from'];
		$_POST['profile_name'] = Utils::htmlspecialchars($_POST['profile_name']);

		// Insert the profile itself.
		$profile_id = Db::$db->insert('',
			'{db_prefix}permission_profiles',
			array(
				'profile_name' => 'string',
			),
			array(
				$_POST['profile_name'],
			),
			array('id_profile'),
			1
		);

		// Load the permissions from the one it's being copied from.
		$request = Db::$db->query('', '
			SELECT id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:copy_from}',
			array(
				'copy_from' => $_POST['copy_from'],
			)
		);
		$inserts = array();
		while ($row = Db::$db->fetch_assoc($request))
			$inserts[] = array($profile_id, $row['id_group'], $row['permission'], $row['add_deny']);
		Db::$db->free_result($request);

		if (!empty($inserts))
			Db::$db->insert('insert',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$inserts,
				array('id_profile', 'id_group', 'permission')
			);
	}
	// Renaming?
	elseif (isset($_POST['rename']))
	{
		checkSession();
		validateToken('admin-mpp');

		// Just showing the boxes?
		if (!isset($_POST['rename_profile']))
			Utils::$context['show_rename_boxes'] = true;
		else
		{
			foreach ($_POST['rename_profile'] as $id => $value)
			{
				$value = Utils::htmlspecialchars($value);

				if (trim($value) != '' && $id > 4)
					Db::$db->query('', '
						UPDATE {db_prefix}permission_profiles
						SET profile_name = {string:profile_name}
						WHERE id_profile = {int:current_profile}',
						array(
							'current_profile' => (int) $id,
							'profile_name' => $value,
						)
					);
			}
		}
	}
	// Deleting?
	elseif (isset($_POST['delete']) && !empty($_POST['delete_profile']))
	{
		checkSession();
		validateToken('admin-mpp');

		$profiles = array();
		foreach ($_POST['delete_profile'] as $profile)
			if ($profile > 4)
				$profiles[] = (int) $profile;

		// Verify it's not in use...
		$request = Db::$db->query('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_profile IN ({array_int:profile_list})
			LIMIT 1',
			array(
				'profile_list' => $profiles,
			)
		);
		if (Db::$db->num_rows($request) != 0)
			fatal_lang_error('no_access', false);
		Db::$db->free_result($request);

		// Oh well, delete.
		Db::$db->query('', '
			DELETE FROM {db_prefix}permission_profiles
			WHERE id_profile IN ({array_int:profile_list})',
			array(
				'profile_list' => $profiles,
			)
		);
	}

	// Clearly, we'll need this!
	loadPermissionProfiles();

	// Work out what ones are in use.
	$request = Db::$db->query('', '
		SELECT id_profile, COUNT(*) AS board_count
		FROM {db_prefix}boards
		GROUP BY id_profile',
		array(
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
		if (isset(Utils::$context['profiles'][$row['id_profile']]))
		{
			Utils::$context['profiles'][$row['id_profile']]['in_use'] = true;
			Utils::$context['profiles'][$row['id_profile']]['boards'] = $row['board_count'];
			Utils::$context['profiles'][$row['id_profile']]['boards_text'] = $row['board_count'] > 1 ? sprintf(Lang::$txt['permissions_profile_used_by_many'], $row['board_count']) : Lang::$txt['permissions_profile_used_by_' . ($row['board_count'] ? 'one' : 'none')];
		}
	Db::$db->free_result($request);

	// What can we do with these?
	Utils::$context['can_edit_something'] = false;
	foreach (Utils::$context['profiles'] as $id => $profile)
	{
		// Can't delete special ones.
		Utils::$context['profiles'][$id]['can_edit'] = isset(Lang::$txt['permissions_profile_' . $profile['unformatted_name']]) ? false : true;
		if (Utils::$context['profiles'][$id]['can_edit'])
			Utils::$context['can_edit_something'] = true;

		// You can only delete it if you can edit it AND it's not in use.
		Utils::$context['profiles'][$id]['can_delete'] = Utils::$context['profiles'][$id]['can_edit'] && empty($profile['in_use']) ? true : false;
	}

	createToken('admin-mpp');
}

/**
 * This function updates the permissions of any groups based off this group.
 *
 * @param null|array $parents The parent groups
 * @param null|int $profile the ID of a permissions profile to update
 * @return void|false Returns nothing if successful or false if there are no child groups to update
 */
function updateChildPermissions($parents, $profile = null)
{
	// All the parent groups to sort out.
	if (!is_array($parents))
		$parents = array($parents);

	// Find all the children of this group.
	$request = Db::$db->query('', '
		SELECT id_parent, id_group
		FROM {db_prefix}membergroups
		WHERE id_parent != {int:not_inherited}
			' . (empty($parents) ? '' : 'AND id_parent IN ({array_int:parent_list})'),
		array(
			'parent_list' => $parents,
			'not_inherited' => -2,
		)
	);
	$children = array();
	$parents = array();
	$child_groups = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$children[$row['id_parent']][] = $row['id_group'];
		$child_groups[] = $row['id_group'];
		$parents[] = $row['id_parent'];
	}
	Db::$db->free_result($request);

	$parents = array_unique($parents);

	// Not a sausage, or a child?
	if (empty($children))
		return false;

	// First off, are we doing general permissions?
	if ($profile < 1 || $profile === null)
	{
		// Fetch all the parent permissions.
		$request = Db::$db->query('', '
			SELECT id_group, permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:parent_list})',
			array(
				'parent_list' => $parents,
			)
		);
		$permissions = array();
		while ($row = Db::$db->fetch_assoc($request))
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = array($child, $row['permission'], $row['add_deny']);
		Db::$db->free_result($request);

		Db::$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:child_groups})',
			array(
				'child_groups' => $child_groups,
			)
		);

		// Finally insert.
		if (!empty($permissions))
		{
			Db::$db->insert('insert',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$permissions,
				array('id_group', 'permission')
			);
		}
	}

	// Then, what about board profiles?
	if ($profile != -1)
	{
		$profileQuery = $profile === null ? '' : ' AND id_profile = {int:current_profile}';

		// Again, get all the parent permissions.
		$request = Db::$db->query('', '
			SELECT id_profile, id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:parent_groups})
				' . $profileQuery,
			array(
				'parent_groups' => $parents,
				'current_profile' => $profile !== null && $profile ? $profile : 1,
			)
		);
		$permissions = array();
		while ($row = Db::$db->fetch_assoc($request))
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = array($child, $row['id_profile'], $row['permission'], $row['add_deny']);
		Db::$db->free_result($request);

		Db::$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:child_groups})
				' . $profileQuery,
			array(
				'child_groups' => $child_groups,
				'current_profile' => $profile !== null && $profile ? $profile : 1,
			)
		);

		// Do the insert.
		if (!empty($permissions))
		{
			Db::$db->insert('insert',
				'{db_prefix}board_permissions',
				array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$permissions,
				array('id_group', 'id_profile', 'permission')
			);
		}
	}
}

/**
 * Load permissions someone cannot grant.
 */
function loadIllegalPermissions()
{
	Utils::$context['illegal_permissions'] = array();
	if (!allowedTo('admin_forum'))
	{
		Utils::$context['illegal_permissions'][] = 'admin_forum';
		Utils::$context['illegal_permissions'][] = 'bbc_html';
	}
	if (!allowedTo('manage_membergroups'))
		Utils::$context['illegal_permissions'][] = 'manage_membergroups';
	if (!allowedTo('manage_permissions'))
		Utils::$context['illegal_permissions'][] = 'manage_permissions';

	call_integration_hook('integrate_load_illegal_permissions');
}

/**
 * Loads the permissions that can not be given to guests.
 * Stores the permissions in Utils::$context['non_guest_permissions'].
 * Also populates Utils::$context['permissions_excluded'] with the info.
 */
function loadIllegalGuestPermissions()
{
	Utils::$context['non_guest_permissions'] = array(
		'access_mod_center',
		'admin_forum',
		'announce_topic',
		'approve_posts',
		'bbc_html',
		'calendar_edit',
		'delete',
		'delete_replies',
		'edit_news',
		'issue_warning',
		'likes_like',
		'lock',
		'make_sticky',
		'manage_attachments',
		'manage_bans',
		'manage_boards',
		'manage_membergroups',
		'manage_permissions',
		'manage_smileys',
		'merge_any',
		'moderate_board',
		'moderate_forum',
		'modify',
		'modify_replies',
		'move',
		'pm_autosave_draft',
		'pm_draft',
		'pm_read',
		'pm_send',
		'poll_add',
		'poll_edit',
		'poll_lock',
		'poll_remove',
		'post_autosave_draft',
		'post_draft',
		'profile_blurb',
		'profile_displayed_name',
		'profile_extra',
		'profile_forum',
		'profile_identity',
		'profile_website',
		'profile_password',
		'profile_remove',
		'profile_remote_avatar',
		'profile_server_avatar',
		'profile_signature',
		'profile_title',
		'profile_upload_avatar',
		'view_warning_own',
		'remove',
		'report_any',
		'report_user',
		'send_mail',
		'split_any',
	);

	call_integration_hook('integrate_load_illegal_guest_permissions');

	// Also add this info to Utils::$context['permissions_excluded'] to make life easier for everyone
	foreach (Utils::$context['non_guest_permissions'] as $permission)
	{
		if (empty(Utils::$context['permissions_excluded'][$permission]) || !in_array($permission, Utils::$context['permissions_excluded'][$permission]))
			Utils::$context['permissions_excluded'][$permission][] = -1;
	}
}

/**
 * Loads a list of membergroups who cannot be granted the bbc_html permission.
 * Stores the groups in Utils::$context['permissions_excluded']['bbc_html'].
 */
function loadIllegalBBCHtmlGroups()
{
	Utils::$context['permissions_excluded']['bbc_html'] = array(-1, 0);

	$request = Db::$db->query('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE id_group != 1 AND id_group NOT IN (
			SELECT DISTINCT id_group
			FROM {db_prefix}permissions
			WHERE permission IN ({array_string:permissions})
				AND add_deny = {int:add}
		)',
		array(
			'permissions' => array('admin_forum', 'manage_membergroups', 'manage_permissions'),
			'add' => 1,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
		Utils::$context['permissions_excluded']['bbc_html'][] = $row['id_group'];
	Db::$db->free_result($request);

	Utils::$context['permissions_excluded']['bbc_html'] = array_unique(Utils::$context['permissions_excluded']['bbc_html']);
}

/**
 * Removes the bbc_html permission from anyone who shouldn't have it
 *
 * @param bool $reload Before acting, refresh the list of membergroups who cannot be granted the bbc_html permission
 */
function removeIllegalBBCHtmlPermission($reload = false)
{
	if (empty(Utils::$context['permissions_excluded']['bbc_html']) || $reload)
		loadIllegalBBCHtmlGroups();

	Db::$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND permission = {string:current_permission}
			AND add_deny = {int:add}',
		array(
			'current_group_list' => Utils::$context['permissions_excluded']['bbc_html'],
			'current_permission' => 'bbc_html',
			'add' => 1,
		)
	);
}

/**
 * Makes sure Config::$modSettings['board_manager_groups'] is up to date.
 */
function updateBoardManagers()
{
	require_once(Config::$sourcedir . '/Subs-Members.php');
	$board_managers = groupsAllowedTo('manage_boards', null);
	$board_managers = implode(',', $board_managers['allowed']);

	Config::updateModSettings(array('board_manager_groups' => $board_managers), true);
}

/**
 * Present a nice way of applying post moderation.
 */
function ModifyPostModeration()
{
	// Just in case.
	checkSession('get');

	Utils::$context['page_title'] = Lang::$txt['permissions_post_moderation'];
	Utils::$context['sub_template'] = 'postmod_permissions';
	Utils::$context['current_profile'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 1;

	// Load all the permission profiles.
	loadPermissionProfiles();

	// Mappings, our key => array(can_do_moderated, can_do_all)
	$mappings = array(
		'new_topic' => array('post_new', 'post_unapproved_topics'),
		'replies_own' => array('post_reply_own', 'post_unapproved_replies_own'),
		'replies_any' => array('post_reply_any', 'post_unapproved_replies_any'),
		'attachment' => array('post_attachment', 'post_unapproved_attachments'),
	);

	call_integration_hook('integrate_post_moderation_mapping', array(&$mappings));

	// Start this with the guests/members.
	Utils::$context['profile_groups'] = array(
		-1 => array(
			'id' => -1,
			'name' => Lang::$txt['membergroups_guests'],
			'color' => '',
			'new_topic' => 'disallow',
			'replies_own' => 'disallow',
			'replies_any' => 'disallow',
			'attachment' => 'disallow',
			'children' => array(),
		),
		0 => array(
			'id' => 0,
			'name' => Lang::$txt['membergroups_members'],
			'color' => '',
			'new_topic' => 'disallow',
			'replies_own' => 'disallow',
			'replies_any' => 'disallow',
			'attachment' => 'disallow',
			'children' => array(),
		),
	);

	// Load the groups.
	$request = Db::$db->query('', '
		SELECT id_group, group_name, online_color, id_parent
		FROM {db_prefix}membergroups
		WHERE id_group != {int:admin_group}
			' . (empty(Config::$modSettings['permission_enable_postgroups']) ? ' AND min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent ASC',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		if ($row['id_parent'] == -2)
		{
			Utils::$context['profile_groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'color' => $row['online_color'],
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'attachment' => 'disallow',
				'children' => array(),
			);
		}
		elseif (isset(Utils::$context['profile_groups'][$row['id_parent']]))
			Utils::$context['profile_groups'][$row['id_parent']]['children'][] = $row['group_name'];
	}
	Db::$db->free_result($request);

	// What are the permissions we are querying?
	$all_permissions = array();
	foreach ($mappings as $perm_set)
		$all_permissions = array_merge($all_permissions, $perm_set);

	// If we're saving the changes then do just that - save them.
	if (!empty($_POST['save_changes']) && (Utils::$context['current_profile'] == 1 || Utils::$context['current_profile'] > 4))
	{
		validateToken('admin-mppm');

		// First, are we saving a new value for enabled post moderation?
		$new_setting = !empty($_POST['postmod_active']);
		if ($new_setting != Config::$modSettings['postmod_active'])
		{
			if ($new_setting)
			{
				// Turning it on. This seems easy enough.
				Config::updateModSettings(array('postmod_active' => 1));
			}
			else
			{
				// Turning it off. Not so straightforward. We have to turn off warnings to moderation level, and make everything approved.
				Config::updateModSettings(array(
					'postmod_active' => 0,
					'warning_moderate' => 0,
				));

				require_once(Config::$sourcedir . '/PostModeration.php');
				approveAllData();
			}
		}
		elseif (Config::$modSettings['postmod_active'])
		{
			// We're not saving a new setting - and if it's still enabled we have more work to do.

			// Start by deleting all the permissions relevant.
			Db::$db->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_profile = {int:current_profile}
					AND permission IN ({array_string:permissions})
					AND id_group IN ({array_int:profile_group_list})',
				array(
					'profile_group_list' => array_keys(Utils::$context['profile_groups']),
					'current_profile' => Utils::$context['current_profile'],
					'permissions' => $all_permissions,
				)
			);

			// Do it group by group.
			$new_permissions = array();
			foreach (Utils::$context['profile_groups'] as $id => $group)
			{
				foreach ($mappings as $index => $data)
				{
					if (isset($_POST[$index][$group['id']]))
					{
						if ($_POST[$index][$group['id']] == 'allow')
						{
							// Give them both sets for fun.
							$new_permissions[] = array(Utils::$context['current_profile'], $group['id'], $data[0], 1);
							$new_permissions[] = array(Utils::$context['current_profile'], $group['id'], $data[1], 1);
						}
						elseif ($_POST[$index][$group['id']] == 'moderate')
							$new_permissions[] = array(Utils::$context['current_profile'], $group['id'], $data[1], 1);
					}
				}
			}

			// Insert new permissions.
			if (!empty($new_permissions))
				Db::$db->insert('',
					'{db_prefix}board_permissions',
					array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$new_permissions,
					array('id_profile', 'id_group', 'permission')
				);
		}
	}

	// Now get all the permissions!
	$request = Db::$db->query('', '
		SELECT id_group, permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_profile = {int:current_profile}
			AND permission IN ({array_string:permissions})
			AND id_group IN ({array_int:profile_group_list})',
		array(
			'profile_group_list' => array_keys(Utils::$context['profile_groups']),
			'current_profile' => Utils::$context['current_profile'],
			'permissions' => $all_permissions,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		foreach ($mappings as $key => $data)
		{
			foreach ($data as $index => $perm)
			{
				if ($perm == $row['permission'])
				{
					// Only bother if it's not denied.
					if ($row['add_deny'])
					{
						// Full allowance?
						if ($index == 0)
							Utils::$context['profile_groups'][$row['id_group']][$key] = 'allow';
						// Otherwise only bother with moderate if not on allow.
						elseif (Utils::$context['profile_groups'][$row['id_group']][$key] != 'allow')
							Utils::$context['profile_groups'][$row['id_group']][$key] = 'moderate';
					}
				}
			}
		}
	}
	Db::$db->free_result($request);

	createToken('admin-mppm');
}

?>