<?php

/**
 * This file is concerned with anything in the Manage Membergroups admin screen.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines Forum contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');


/**
 * Main dispatcher, the entrance point for all 'Manage Membergroup' actions.
 * It forwards to a function based on the given subaction, default being subaction 'index', or, without manage_membergroup
 * permissions, then 'settings'.
 * Called by ?action=admin;area=membergroups.
 * Requires the manage_membergroups or the admin_forum permission.
 *
 * @uses ManageMembergroups template.
 * @uses ManageMembers language file.
*/
function ModifyMembergroups()
{
	global $context, $txt, $scripturl, $sourcedir;

	$subActions = array(
		'add' => array('AddMembergroup', 'manage_membergroups'),
		'delete' => array('DeleteMembergroup', 'manage_membergroups'),
		'edit' => array('EditMembergroup', 'manage_membergroups'),
		'index' => array('MembergroupIndex', 'manage_membergroups'),
		'members' => array('MembergroupMembers', 'manage_membergroups', 'Groups.php'),
		'icons' => array('ModifyMembergroupicons', 'manage_membergroups'),
		'settings' => array('ModifyMembergroupsettings', 'admin_forum'),

	);

	call_integration_hook('integrate_manage_membergroups', array(&$subActions));

	// Default to sub action 'index' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_membergroups') ? 'index' : 'settings');

	// Is it elsewhere?
	if (isset($subActions[$_REQUEST['sa']][2]))
		require_once($sourcedir . '/' . $subActions[$_REQUEST['sa']][2]);

	// Do the permission check, you might not be allowed her.
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Language and template stuff, the usual.
	loadLanguage('ManageMembers');
	loadTemplate('ManageMembergroups');

	// Setup the admin tabs.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['membergroups_title'],
		'help' => 'membergroups',
		'description' => $txt['membergroups_description'],
	);

	// Call the right function.
	$subActions[$_REQUEST['sa']][0]();
}

/**
 * Shows an overview of the current membergroups.
 * Called by ?action=admin;area=membergroups.
 * Requires the manage_membergroups permission.
 * Splits the membergroups in regular ones and post count based groups.
 * It also counts the number of members part of each membergroup.
 *
 * @uses ManageMembergroups template, main.
 */
function MembergroupIndex()
{
	global $txt, $scripturl, $context, $settings, $smcFunc, $sourcedir;

	$context['page_title'] = $txt['membergroups_title'];

	// The first list shows the regular membergroups.
	$listOptions = array(
		'id' => 'regular_membergroups_list',
		'title' => $txt['membergroups_regular'],
		'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : ''),
		'default_sort_col' => 'name',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Membergroups.php',
			'function' => 'list_getMembergroups',
			'params' => array(
				'regular',
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['membergroups_name'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl;

						// Since the moderator group has no explicit members, no link is needed.
						if ($rowData[\'id_group\'] == 3)
							$group_name = $rowData[\'group_name\'];
						else
						{
							$color_style = empty($rowData[\'online_color\']) ? \'\' : sprintf(\' style="color: %1$s;"\', $rowData[\'online_color\']);
							$group_name = sprintf(\'<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>\', $scripturl, $rowData[\'id_group\'], $color_style, $rowData[\'group_name\']);
						}

						// Add a help option for moderator and administrator.
						if ($rowData[\'id_group\'] == 1)
							$group_name .= sprintf(\' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);
						elseif ($rowData[\'id_group\'] == 3)
							$group_name .= sprintf(\' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);

						return $group_name;
					'),
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name DESC',
				),
			),
			'icons' => array(
				'header' => array(
					'value' => $txt['membergroups_icons'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $settings;

						$icons = explode(\'#\', $rowData[\'icons\']);

						// In case no icons are setup, return with nothing
						if (empty($icons[0]) || empty($icons[1]))
							return \'\';

						// Otherwise repeat the image a given number of times.
						else
						{
							$image = sprintf(\'<img src="%1$s/%2$s" alt="*" />\', $settings[\'images_url\'] . \'/membergroup_icons\', $icons[1]);
							return str_repeat($image, $icons[0]);
						}
					'),

				),
				'sort' => array(
					'default' => 'icons',
					'reverse' => 'icons DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],

				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						// No explicit members for the moderator group.
						return $rowData[\'id_group\'] == 3 ? $txt[\'membergroups_guests_na\'] : $rowData[\'num_members\'];
					'),
					'style' => 'text-align: center',

				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, 1',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, 1 DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],

				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'style' => 'text-align: center',

				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// The second list shows the post count based groups.
	$listOptions = array(
		'id' => 'post_count_membergroups_list',
		'title' => $txt['membergroups_post'],
		'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort']) ? ';sort=' . urlencode($_REQUEST['sort']) : ''),
		'default_sort_col' => 'required_posts',
		'request_vars' => array(
			'sort' => 'sort2',
			'desc' => 'desc2',
		),
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Membergroups.php',
			'function' => 'list_getMembergroups',
			'params' => array(
				'post_count',
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['membergroups_name'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl;

						$colorStyle = empty($rowData[\'online_color\']) ? \'\' : sprintf(\' style="color: %1$s;"\', $rowData[\'online_color\']);
						return sprintf(\'<a href="%1$s?action=moderate;area=viewgroups;sa=members;group=%2$d"%3$s>%4$s</a>\', $scripturl, $rowData[\'id_group\'], $colorStyle, $rowData[\'group_name\']);
					'),
				),
				'sort' => array(
					'default' => 'group_name',
					'reverse' => 'group_name DESC',
				),
			),
			'icons' => array(
				'header' => array(
					'value' => $txt['membergroups_icons'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $settings;

						$icons = explode(\'#\', $rowData[\'icons\']);

						if (empty($icons[0]) || empty($icons[1]))
							return \'\';
						else
						{
							$icon_image = sprintf(\'<img src="%1$s/%2$s" alt="*" />\', $settings[\'images_url\'] . \'/membergroup_icons\', $icons[1]);
							return str_repeat($icon_image, $icons[0]);
						}
					'),
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, icons',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, icons DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],

				),
				'data' => array(
					'db' => 'num_members',
					'style' => 'text-align: center',

				),
				'sort' => array(
					'default' => '1 DESC',
					'reverse' => '1',
				),
			),
			'required_posts' => array(
				'header' => array(
					'value' => $txt['membergroups_min_posts'],

				),
				'data' => array(
					'db' => 'min_posts',
					'style' => 'text-align: center',

				),
				'sort' => array(
					'default' => 'min_posts',
					'reverse' => 'min_posts DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],

				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'style' => 'text-align: center',

				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . $txt['membergroups_add_group'] . '</a>',
			),
		),
	);

	createList($listOptions);
}

/**
 * This function handles adding a membergroup and setting some initial properties.
 * Called by ?action=admin;area=membergroups;sa=add.
 * It requires the manage_membergroups permission.
 * Allows to use a predefined permission profile or copy one from another group.
 * Redirects to action=admin;area=membergroups;sa=edit;group=x.
 *
 * @uses the new_group sub template of ManageMembergroups.
 */
function AddMembergroup()
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc;

	// A form was submitted, we can start adding.
	if (isset($_POST['group_name']) && trim($_POST['group_name']) != '')
	{
		checkSession();
		validateToken('admin-mmg');

		$postCountBasedGroup = isset($_POST['min_posts']) && (!isset($_POST['postgroup_based']) || !empty($_POST['postgroup_based']));
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

		// @todo Check for members with same name too?

		$request = $smcFunc['db_query']('', '
			SELECT MAX(id_group)
			FROM {db_prefix}membergroups',
			array(
			)
		);
		list ($id_group) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$id_group++;

		$smcFunc['db_insert']('',
			'{db_prefix}membergroups',
			array(
				'id_group' => 'int', 'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
				'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int',
			),
			array(
				$id_group, '', $smcFunc['htmlspecialchars']($_POST['group_name'], ENT_QUOTES), ($postCountBasedGroup ? (int) $_POST['min_posts'] : '-1'),
				'1#star.png', '', $_POST['group_type'],
			),
			array('id_group')
		);

		call_integration_hook('integrate_add_membergroup', array($id_group, $postCountBasedGroup));

		// Update the post groups now, if this is a post group!
		if (isset($_POST['min_posts']))
			updateStats('postgroups');

		// You cannot set permissions for post groups if they are disabled.
		if ($postCountBasedGroup && empty($modSettings['permission_enable_postgroups']))
			$_POST['perm_type'] = '';

		if ($_POST['perm_type'] == 'predefined')
		{
			// Set default permission level.
			require_once($sourcedir . '/ManagePermissions.php');
			setPermissionLevel($_POST['level'], $id_group, 'null');
		}
		// Copy or inherit the permissions!
		elseif ($_POST['perm_type'] == 'copy' || $_POST['perm_type'] == 'inherit')
		{
			$copy_id = $_POST['perm_type'] == 'copy' ? (int) $_POST['copyperm'] : (int) $_POST['inheritperm'];

			// Are you a powerful admin?
			if (!allowedTo('admin_forum'))
			{
				$request = $smcFunc['db_query']('', '
					SELECT group_type
					FROM {db_prefix}membergroups
					WHERE id_group = {int:copy_from}
					LIMIT {int:limit}',
					array(
						'copy_from' => $copy_id,
						'limit' => 1,
					)
				);
				list ($copy_type) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// Protected groups are... well, protected!
				if ($copy_type == 1)
					fatal_lang_error('membergroup_does_not_exist');
			}

			// Don't allow copying of a real priviledged person!
			require_once($sourcedir . '/ManagePermissions.php');
			loadIllegalPermissions();

			$request = $smcFunc['db_query']('', '
				SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $copy_id,
				)
			);
			$inserts = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (empty($context['illegal_permissions']) || !in_array($row['permission'], $context['illegal_permissions']))
					$inserts[] = array($id_group, $row['permission'], $row['add_deny']);
			}
			$smcFunc['db_free_result']($request);

			if (!empty($inserts))
				$smcFunc['db_insert']('insert',
					'{db_prefix}permissions',
					array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$inserts,
					array('id_group', 'permission')
				);

			$request = $smcFunc['db_query']('', '
				SELECT id_profile, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $copy_id,
				)
			);
			$inserts = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$inserts[] = array($id_group, $row['id_profile'], $row['permission'], $row['add_deny']);
			$smcFunc['db_free_result']($request);

			if (!empty($inserts))
				$smcFunc['db_insert']('insert',
					'{db_prefix}board_permissions',
					array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$inserts,
					array('id_group', 'id_profile', 'permission')
				);

			// Also get some membergroup information if we're copying and not copying from guests...
			if ($copy_id > 0 && $_POST['perm_type'] == 'copy')
			{
				$request = $smcFunc['db_query']('', '
					SELECT online_color, max_messages, icons
					FROM {db_prefix}membergroups
					WHERE id_group = {int:copy_from}
					LIMIT 1',
					array(
						'copy_from' => $copy_id,
					)
				);
				$group_info = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);

				// ...and update the new membergroup with it.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}membergroups
					SET
						online_color = {string:online_color},
						max_messages = {int:max_messages},
						icons = {string:icons}
					WHERE id_group = {int:current_group}',
					array(
						'max_messages' => $group_info['max_messages'],
						'current_group' => $id_group,
						'online_color' => $group_info['online_color'],
						'icons' => $group_info['icons'],
					)
				);
			}
			// If inheriting say so...
			elseif ($_POST['perm_type'] == 'inherit')
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}membergroups
					SET id_parent = {int:copy_from}
					WHERE id_group = {int:current_group}',
					array(
						'copy_from' => $copy_id,
						'current_group' => $id_group,
					)
				);
			}
		}

		// Make sure all boards selected are stored in a proper array.
		$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
		$changed_boards['allow'] = array();
		$changed_boards['deny'] = array();
		$changed_boards['ignore'] = array();
		foreach ($accesses as $group_id => $action)
			$changed_boards[$action][] = (int) $group_id;

		foreach (array('allow', 'deny') as $board_action)
		{
			// Only do this if they have special access requirements.
			if (!empty($changed_boards[$board_action]))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
					WHERE id_board IN ({array_int:board_list})',
					array(
						'board_list' => $changed_boards[$board_action],
						'blank_string' => '',
						'group_id_string' => (string) $id_group,
						'comma_group' => ',' . $id_group,
						'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
					)
				);
		}

		// If this is joinable then set it to show group membership in people's profiles.
		if (empty($modSettings['show_group_membership']) && $_POST['group_type'] > 1)
			updateSettings(array('show_group_membership' => 1));

		// Rebuild the group cache.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// We did it.
		logAction('add_group', array('group' => $_POST['group_name']), 'admin');

		// Go change some more settings.
		redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
	}

	// Just show the 'add membergroup' screen.
	$context['page_title'] = $txt['membergroups_new_group'];
	$context['sub_template'] = 'new_group';
	$context['post_group'] = isset($_REQUEST['postgroup']);
	$context['undefined_group'] = !isset($_REQUEST['postgroup']) && !isset($_REQUEST['generalgroup']);
	$context['allow_protected'] = allowedTo('admin_forum');

	if (!empty($modSettings['deny_boards_access']))
		loadLanguage('ManagePermissions');

	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE (id_group > {int:moderator_group} OR id_group = {int:global_mod_group})' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY min_posts, id_group != {int:global_mod_group}, group_name',
		array(
			'moderator_group' => 3,
			'global_mod_group' => 2,
			'min_posts' => -1,
			'is_protected' => 1,
		)
	);
	$context['groups'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['groups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	$smcFunc['db_free_result']($result);

	$request = $smcFunc['db_query']('', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		ORDER BY board_order',
		array(
		)
	);
	$context['num_boards'] = $smcFunc['db_num_rows']($request);

	$context['categories'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// This category hasn't been set up yet..
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => array()
			);

		// Set this board up, and let the template know when it's a child.  (indent them..)
		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'allow' => false,
			'deny' => false
		);

	}
	$smcFunc['db_free_result']($request);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = array();
	foreach ($context['categories'] as $category)
	{
		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));

		// Include a list of boards per category for easy toggling.
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
	}

	createToken('admin-mmg');
}

/**
 * Deleting a membergroup by URL (not implemented).
 * Called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
 * Requires the manage_membergroups permission.
 * Redirects to ?action=admin;area=membergroups.
 *
 * @todo look at this
 */
function DeleteMembergroup()
{
	global $sourcedir;

	checkSession('get');

	require_once($sourcedir . '/Subs-Membergroups.php');
	deleteMembergroups((int) $_REQUEST['group']);

	// Go back to the membergroup index.
	redirectexit('action=admin;area=membergroups;');
}

/**
 * Editing a membergroup.
 * Screen to edit a specific membergroup.
 * Called by ?action=admin;area=membergroups;sa=edit;group=x.
 * It requires the manage_membergroups permission.
 * Also handles the delete button of the edit form.
 * Redirects to ?action=admin;area=membergroups.
 *
 * @uses the edit_group sub template of ManageMembergroups.
 */
function EditMembergroup()
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc, $settings;

	$_REQUEST['group'] = isset($_REQUEST['group']) && $_REQUEST['group'] > 0 ? (int) $_REQUEST['group'] : 0;

	if (!empty($modSettings['deny_boards_access']))
		loadLanguage('ManagePermissions');


	// Make sure this group is editable.
	if (!empty($_REQUEST['group']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}' . (allowedTo('admin_forum') ? '' : '
				AND group_type != {int:is_protected}') . '
			LIMIT {int:limit}',
			array(
				'current_group' => $_REQUEST['group'],
				'is_protected' => 1,
				'limit' => 1,
			)
		);
		list ($_REQUEST['group']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Now, do we have a valid id?
	if (empty($_REQUEST['group']))
		fatal_lang_error('membergroup_does_not_exist', false);

	// The delete this membergroup button was pressed.
	if (isset($_POST['delete']))
	{
		checkSession();
		validateToken('admin-mmg');

		require_once($sourcedir . '/Subs-Membergroups.php');
		deleteMembergroups($_REQUEST['group']);

		redirectexit('action=admin;area=membergroups;');
	}
	// A form was submitted with the new membergroup settings.
	elseif (isset($_POST['save']))
	{
		// Validate the session.
		checkSession();
		validateToken('admin-mmg');

		// Can they really inherit from this group?
		if (isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
		{
			$request = $smcFunc['db_query']('', '
				SELECT group_type
				FROM {db_prefix}membergroups
				WHERE id_group = {int:inherit_from}
				LIMIT {int:limit}',
				array(
					'inherit_from' => $_POST['group_inherit'],
					'limit' => 1,
				)
			);
			list ($inherit_type) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Set variables to their proper value.
		$_POST['max_messages'] = isset($_POST['max_messages']) ? (int) $_POST['max_messages'] : 0;
		$_POST['min_posts'] = isset($_POST['min_posts']) && isset($_POST['group_type']) && $_POST['group_type'] == -1 && $_REQUEST['group'] > 3 ? abs($_POST['min_posts']) : ($_REQUEST['group'] == 4 ? 0 : -1);
		$_POST['icons'] = (empty($_POST['icon_count']) || $_POST['icon_count'] < 0) ? '' : min((int) $_POST['icon_count'], 99) . '#' . $_POST['icon_image'];
		$_POST['group_desc'] = isset($_POST['group_desc']) && ($_REQUEST['group'] == 1 || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? trim($_POST['group_desc']) : '';
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];
		$_POST['group_hidden'] = empty($_POST['group_hidden']) || $_POST['min_posts'] != -1 || $_REQUEST['group'] == 3 ? 0 : (int) $_POST['group_hidden'];
		$_POST['group_inherit'] = $_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && (empty($inherit_type) || $inherit_type != 1) ? (int) $_POST['group_inherit'] : -2;

		//@todo Don't set online_color for the Moderators group?

		// Do the update of the membergroup settings.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}membergroups
			SET group_name = {string:group_name}, online_color = {string:online_color},
				max_messages = {int:max_messages}, min_posts = {int:min_posts}, icons = {string:icons},
				description = {string:group_desc}, group_type = {int:group_type}, hidden = {int:group_hidden},
				id_parent = {int:group_inherit}
			WHERE id_group = {int:current_group}',
			array(
				'max_messages' => $_POST['max_messages'],
				'min_posts' => $_POST['min_posts'],
				'group_type' => $_POST['group_type'],
				'group_hidden' => $_POST['group_hidden'],
				'group_inherit' => $_POST['group_inherit'],
				'current_group' => (int) $_REQUEST['group'],
				'group_name' => $smcFunc['htmlspecialchars']($_POST['group_name']),
				'online_color' => $_POST['online_color'],
				'icons' => $_POST['icons'],
				'group_desc' => $_POST['group_desc'],
			)
		);

		call_integration_hook('integrate_save_membergroup', array((int) $_REQUEST['group']));

		// Time to update the boards this membergroup has access to.
		if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
		{
			$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
			$changed_boards['allow'] = array();
			$changed_boards['deny'] = array();
			$changed_boards['ignore'] = array();
			foreach ($accesses as $group_id => $action)
				$changed_boards[$action][] = (int) $group_id;

			foreach (array('allow', 'deny') as $board_action)
			{
				// Find all board this group is in, but shouldn't be in.
				$request = $smcFunc['db_query']('', '
					SELECT id_board, {raw:column}
					FROM {db_prefix}boards
					WHERE FIND_IN_SET({string:current_group}, {raw:column}) != 0' . (empty($changed_boards[$board_action]) ? '' : '
						AND id_board NOT IN ({array_int:board_access_list})'),
					array(
						'current_group' => (int) $_REQUEST['group'],
						'board_access_list' => $changed_boards[$board_action],
						'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET {raw:column} = {string:member_group_access}
						WHERE id_board = {int:current_board}',
						array(
							'current_board' => $row['id_board'],
							'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($_REQUEST['group']))),
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						)
					);
				$smcFunc['db_free_result']($request);

				// Add the membergroup to all boards that hadn't been set yet.
				if (!empty($changed_boards[$board_action]))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
						WHERE id_board IN ({array_int:board_list})
							AND FIND_IN_SET({int:current_group}, {raw:column}) = 0',
						array(
							'board_list' => $changed_boards[$board_action],
							'blank_string' => '',
							'current_group' => (int) $_REQUEST['group'],
							'group_id_string' => (string) (int) $_REQUEST['group'],
							'comma_group' => ',' . $_REQUEST['group'],
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						)
					);
			}
		}

		// Remove everyone from this group!
		if ($_POST['min_posts'] != -1)
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}members
				SET id_group = {int:regular_member}
				WHERE id_group = {int:current_group}',
				array(
					'regular_member' => 0,
					'current_group' => (int) $_REQUEST['group'],
				)
			);

			$request = $smcFunc['db_query']('', '
				SELECT id_member, additional_groups
				FROM {db_prefix}members
				WHERE FIND_IN_SET({string:current_group}, additional_groups) != 0',
				array(
					'current_group' => (int) $_REQUEST['group'],
				)
			);
			$updates = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$updates[$row['additional_groups']][] = $row['id_member'];
			$smcFunc['db_free_result']($request);

			foreach ($updates as $additional_groups => $memberArray)
				updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), array((int) $_REQUEST['group'])))));
		}
		elseif ($_REQUEST['group'] != 3)
		{
			// Making it a hidden group? If so remove everyone with it as primary group (Actually, just make them additional).
			if ($_POST['group_hidden'] == 2)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member, additional_groups
					FROM {db_prefix}members
					WHERE id_group = {int:current_group}
						AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
					array(
						'current_group' => (int) $_REQUEST['group'],
					)
				);
				$updates = array();
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$updates[$row['additional_groups']][] = $row['id_member'];
				$smcFunc['db_free_result']($request);

				foreach ($updates as $additional_groups => $memberArray)
					updateMemberData($memberArray, array('additional_groups' => implode(',', array_merge(explode(',', $additional_groups), array((int) $_REQUEST['group'])))));

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}members
					SET id_group = {int:regular_member}
					WHERE id_group = {int:current_group}',
					array(
						'regular_member' => 0,
						'current_group' => $_REQUEST['group'],
					)
				);
			}

			// Either way, let's check our "show group membership" setting is correct.
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}membergroups
				WHERE group_type > {int:non_joinable}',
				array(
					'non_joinable' => 1,
				)
			);
			list ($have_joinable) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Do we need to update the setting?
			if ((empty($modSettings['show_group_membership']) && $have_joinable) || (!empty($modSettings['show_group_membership']) && !$have_joinable))
				updateSettings(array('show_group_membership' => $have_joinable ? 1 : 0));
		}

		// Do we need to set inherited permissions?
		if ($_POST['group_inherit'] != -2 && $_POST['group_inherit'] != $_POST['old_inherit'])
		{
			require_once($sourcedir . '/ManagePermissions.php');
			updateChildPermissions($_POST['group_inherit']);
		}

		// Finally, moderators!
		$moderator_string = isset($_POST['group_moderators']) ? trim($_POST['group_moderators']) : '';
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}group_moderators
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_REQUEST['group'],
			)
		);
		if ((!empty($moderator_string) || !empty($_POST['moderator_list'])) && $_POST['min_posts'] == -1 && $_REQUEST['group'] != 3)
		{
			// Get all the usernames from the string
			if (!empty($moderator_string))
			{
				$moderator_string = strtr(preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', htmlspecialchars($moderator_string), ENT_QUOTES), array('&quot;' => '"'));
				preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
				$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
				for ($k = 0, $n = count($moderators); $k < $n; $k++)
				{
					$moderators[$k] = trim($moderators[$k]);

					if (strlen($moderators[$k]) == 0)
						unset($moderators[$k]);
				}

				// Find all the id_member's for the member_name's in the list.
				$group_moderators = array();
				if (!empty($moderators))
				{
					$request = $smcFunc['db_query']('', '
						SELECT id_member
						FROM {db_prefix}members
						WHERE member_name IN ({array_string:moderators}) OR real_name IN ({array_string:moderators})
						LIMIT ' . count($moderators),
						array(
							'moderators' => $moderators,
						)
					);
					while ($row = $smcFunc['db_fetch_assoc']($request))
						$group_moderators[] = $row['id_member'];
					$smcFunc['db_free_result']($request);
				}
			}
			else
			{
				$moderators = array();
				foreach ($_POST['moderator_list'] as $moderator)
					$moderators[] = (int) $moderator;

				$group_moderators = array();
				if (!empty($moderators))
				{
					$request = $smcFunc['db_query']('', '
						SELECT id_member
						FROM {db_prefix}members
						WHERE id_member IN ({array_int:moderators})
						LIMIT {int:num_moderators}',
						array(
							'moderators' => $moderators,
							'num_moderators' => count($moderators),
						)
					);
					while ($row = $smcFunc['db_fetch_assoc']($request))
						$group_moderators[] = $row['id_member'];
					$smcFunc['db_free_result']($request);
				}
			}

			// Found some?
			if (!empty($group_moderators))
			{
				$mod_insert = array();
				foreach ($group_moderators as $moderator)
					$mod_insert[] = array($_REQUEST['group'], $moderator);

				$smcFunc['db_insert']('insert',
					'{db_prefix}group_moderators',
					array('id_group' => 'int', 'id_member' => 'int'),
					$mod_insert,
					array('id_group', 'id_member')
				);
			}
		}

		// There might have been some post group changes.
		updateStats('postgroups');
		// We've definitely changed some group stuff.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// Log the edit.
		logAction('edited_group', array('group' => $_POST['group_name']), 'admin');

		redirectexit('action=admin;area=membergroups');
	}

	// Fetch the current group information.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, description, min_posts, online_color, max_messages, icons, group_type, hidden, id_parent
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}
		LIMIT 1',
		array(
			'current_group' => (int) $_REQUEST['group'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('membergroup_does_not_exist', false);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$row['icons'] = explode('#', $row['icons']);

	$context['group'] = array(
		'id' => $_REQUEST['group'],
		'name' => $row['group_name'],
		'description' => htmlspecialchars($row['description']),
		'editable_name' => $row['group_name'],
		'color' => $row['online_color'],
		'min_posts' => $row['min_posts'],
		'max_messages' => $row['max_messages'],
		'icon_count' => (int) $row['icons'][0],
		'icon_image' => isset($row['icons'][1]) ? $row['icons'][1] : '',
		'is_post_group' => $row['min_posts'] != -1,
		'type' => $row['min_posts'] != -1 ? 0 : $row['group_type'],
		'hidden' => $row['min_posts'] == -1 ? $row['hidden'] : 0,
		'inherited_from' => $row['id_parent'],
		'allow_post_group' => $_REQUEST['group'] == 2 || $_REQUEST['group'] > 4,
		'allow_delete' => $_REQUEST['group'] == 2 || $_REQUEST['group'] > 4,
		'allow_protected' => allowedTo('admin_forum'),
	);

	// Get any moderators for this group
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}group_moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_group = {int:current_group}',
		array(
			'current_group' => $_REQUEST['group'],
		)
	);
	$context['group']['moderators'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['group']['moderators'][$row['id_member']] = $row['real_name'];
	$smcFunc['db_free_result']($request);

	$context['group']['moderator_list'] = empty($context['group']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['group']['moderators']) . '&quot;';

	if (!empty($context['group']['moderators']))
		list ($context['group']['last_moderator_id']) = array_slice(array_keys($context['group']['moderators']), -1);

	// Get a list of boards this membergroup is allowed to see.
	$context['boards'] = array();
	if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
	{
		$request = $smcFunc['db_query']('', '
			SELECT b.id_cat, c.name as cat_name, b.id_board, b.name, b.child_level,
			FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access, FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY board_order',
			array(
				'current_group' => (int) $_REQUEST['group'],
			)
		);
		$context['categories'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// This category hasn't been set up yet..
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => array()
				);

			// Set this board up, and let the template know when it's a child.  (indent them..)
			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'allow' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
				'deny' => !(empty($row['cannot_access']) || $row['cannot_access'] == 'f'),
			);
		}
		$smcFunc['db_free_result']($request);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = array();
		foreach ($context['categories'] as $category)
		{
			$temp_boards[] = array(
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards'])
			);
			$temp_boards = array_merge($temp_boards, array_values($category['boards']));

			// Include a list of boards per category for easy toggling.
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}

		$max_boards = ceil(count($temp_boards) / 2);
		if ($max_boards == 1)
			$max_boards = 2;
	}

	// Finally, get all the groups this could be inherited off.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group != {int:current_group}' .
			(empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
			AND id_group NOT IN (1, 3)
			AND id_parent = {int:not_inherited}',
		array(
			'current_group' => (int) $_REQUEST['group'],
			'min_posts' => -1,
			'not_inherited' => -2,
			'is_protected' => 1,
		)
	);
	$context['inheritable_groups'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['inheritable_groups'][$row['id_group']] = $row['group_name'];
	$smcFunc['db_free_result']($request);


	// Get available star icons - will only fetch image for the admins current theme!
	$allowedTypes = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
	$context['membergroup_icons'] = array();
	$directory = $settings['theme_dir'] . '/images/membergroup_icons';
	$files = scandir($directory, 0);
	foreach ($files as $file => $find)
	{
		if (in_array(substr(strrchr($find, '.'), 1), $allowedTypes) && !is_dir($find))
		{
			$context['membergroup_icons'][] = $find;
		}
	}

	call_integration_hook('integrate_view_membergroup');

	$context['sub_template'] = 'edit_group';
	$context['page_title'] = $txt['membergroups_edit_group'];

	createToken('admin-mmg');
}

/**
 * Set some general membergroup settings and permissions.
 * Called by ?action=admin;area=membergroups;sa=settings
 * Requires the admin_forum permission (and manage_permissions for changing permissions)
 * Redirects to itself.
 *
 * @uses membergroup_settings sub template of ManageMembergroups.
 */
function ModifyMembergroupsettings()
{
	global $context, $sourcedir, $scripturl, $modSettings, $txt;

	$context['sub_template'] = 'show_settings';
	$context['page_title'] = $txt['membergroups_settings'];

	// Needed for the settings functions.
	require_once($sourcedir . '/ManageServer.php');

	// Don't allow assignment of guests.
	$context['permissions_excluded'] = array(-1);

	// Only one thing here!
	$config_vars = array(
			array('permissions', 'manage_membergroups'),
	);

	call_integration_hook('integrate_modify_membergroup_settings', array(&$config_vars));

	if (isset($_REQUEST['save']))
	{
		checkSession();
		call_integration_hook('integrate_save_membergroup_settings');

		// Yeppers, saving this...
		saveDBSettings($config_vars);
		redirectexit('action=admin;area=membergroups;sa=settings');
	}

	// Some simple context.
	$context['post_url'] = $scripturl . '?action=admin;area=membergroups;save;sa=settings';
	$context['settings_title'] = $txt['membergroups_settings'];

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

function ModifyMembergroupIcons()

{
	global $context, $sourcedir, $scripturl, $modSettings, $smcFunc, $txt;

	$context['sub_template'] = 'member_icons';
	$context['page_title'] = $txt['membergroups_icons'];

	// Get the theme name, path and descriptions.
	$context['available_themes'] = array();
	if (!empty($modSettings['knownThemes']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes' ,
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($context['available_themes'][$row['id_theme']]))
				$context['available_themes'][$row['id_theme']] = array(
					'id' => $row['id_theme']
				);
			$context['available_themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		$smcFunc['db_free_result']($request);
	}

	$allowedTypes = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
	$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');

	// Copy an image
	if (isset($_REQUEST['copy']))
	{
		checkSession();
		if (!in_array(strtolower(substr(strrchr($_POST['copy_image'], '.'), 1)), $allowedTypes))
			fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));

		$img_source = $context['available_themes'][(int)$_POST['copy_from_theme']]['theme_dir']. '/images/membergroup_icons/'. $_POST['copy_image'];
		if (file_exists($img_source) && file_exists($context['available_themes'][$_POST['copy_to_theme']]['theme_dir']. '/images/membergroup_icons/'))
		{
			$img_dest = $context['available_themes'][(int)$_POST['copy_to_theme']]['theme_dir']. '/images/membergroup_icons/'. $_POST['dest_name'];
			copy($img_source, $img_dest);
		}
		redirectexit('action=admin;area=membergroups;sa=icons#table');
	}

	// Delete doubleclicked image
	if (isset($_REQUEST['delete']))
	{
		checkSession();
		$imgfile = $context['available_themes'][$_POST['delete_theme']]['theme_dir']. '/images/membergroup_icons/'. $_POST['delete_img'];
		if ($_POST['delete_img'] != "" && (int)$_POST['delete_theme'] > 0)
		{
			echo ' Path: \'', $context['available_themes'][(int)$_POST['delete_theme']]['theme_dir'], '/images/membergroup_icons/', $_POST['delete_img'], '\'';
			if (file_exists($imgfile))
				if (!unlink($imgfile))
					$error = ' Not deleted!'; // What here?
		}

		redirectexit('action=admin;area=membergroups;sa=icons#table');
	}

	// Upload new file(s)
	if (isset($_REQUEST['save']))
	{
		checkSession();
		if (!is_uploaded_file($_FILES['uploadIcon']['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['uploadIcon']['tmp_name'])))
				fatal_lang_error('smileys_upload_error');

		$_POST['rename_file'] = isset($_POST['rename_file']) ? htmltrim__recursive($_POST['rename_file']) : '';

		// Sorry, no spaces, dots, or anything else but letters allowed.
		$_FILES['uploadIcon']['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['uploadIcon']['name']);

		// We only allow image files - it's THAT simple - no messing around here...
		if (!in_array(strtolower(substr(strrchr($_FILES['uploadIcon']['name'], '.'), 1)), $allowedTypes))
			fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));

		// We only need the filename...
		$destName = $_POST['rename_file'] != '' ? $_POST['rename_file'] . substr($_FILES['uploadIcon']['name'], strrpos($_FILES['uploadIcon']['name'], '.')) : basename($_FILES['uploadIcon']['name']);

		// Make sure they aren't trying to upload a nasty file - for their own good here!
		if (in_array(strtolower($destName), $disabledFiles))
			fatal_lang_error('icons_upload_error_illegal');

		// Copy it to the theme(s) selected
		foreach($_POST['theme'] as $theme => $id)
		{
			if (array_key_exists((int)$theme, $context['available_themes']))
			{
				$iconLocation = $context['available_themes'][$theme]['theme_dir'] . '/images/membergroup_icons/' . $destName;
				if (!isset($_POST['overwrite']) && file_exists($iconLocation))
				{
					continue;
				}
				else
				{
					echo ' iconLocation: ' .$iconLocation;
					copy($_FILES['uploadIcon']['tmp_name'], $iconLocation);
					//@chmod($smileyLocation, 0644);
				}
			}
		}
		unlink($_FILES['uploadIcon']['tmp_name']);
		//unset($context['available_themes']);
		redirectexit('action=admin;area=membergroups;sa=icons');
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, icons
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$context['membergroup_icons'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['membergroup_icons'][$row['id_group']] = array($row['group_name'], explode('#', $row['icons']), 'icon_present' => 0);
	$smcFunc['db_free_result']($request);

	$context['unique_membergroup_icons'] = array();
	for($i = 1; $i <= count($context['available_themes']); $i++)
	{
		$directory = $context['available_themes'][$i]['theme_dir'] . '/images/membergroup_icons';
		if (!is_dir($directory))
		{
			if (!@mkdir($directory, 0755))
			{
				$context['dir_creation_error'] = 'attachments_no_create'; // What about this?
				return false; // and this?!
			}
		}
		else
		{
			$context['available_themes'][$i]['star_icons'] = array();
			// php 5 only!
			$files = scandir($directory, 0);
			foreach ($files as $file => $find)
			{
				if (in_array(substr(strrchr($find, '.'), 1), $allowedTypes) && !is_dir($find))
				{
					$context['available_themes'][$i]['star_icons'][] = $find;
					$context['unique_membergroup_icons'][$find] = $find;
				}
			}
		}
	}
	// We want it nice!
	asort($context['unique_membergroup_icons']);

	$y = 0;
	foreach ($context['unique_membergroup_icons'] as $icon)
	{
		$context['icons'][$y]['name'] = $icon;
		$context['icons'][$y]['used_by'] = array();
		foreach ($context['membergroup_icons'] as $mg => $id)
		{
			if ($icon == $id[1][1])
			{
				$context['icons'][$y]['used_by'][] = $id[0];
				// Any group that *does not* get a 1 here, has an image chosen that doesn't exists in any theme
				$context['membergroup_icons'][$mg]['icon_present'] = 1;
			}
		}
		$y++;
	}
	$context['post_url'] = $scripturl . '?action=admin;area=membergroups;save;sa=icons';
	createToken('admin-mmg');
}

?>