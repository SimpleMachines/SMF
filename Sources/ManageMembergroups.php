<?php

/**
 * This file is concerned with anything in the Manage Membergroups admin screen.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

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
	global $context, $txt, $sourcedir;

	$subActions = array(
		'add' => array('AddMembergroup', 'manage_membergroups'),
		'delete' => array('DeleteMembergroup', 'manage_membergroups'),
		'edit' => array('EditMembergroup', 'manage_membergroups'),
		'index' => array('MembergroupIndex', 'manage_membergroups'),
		'members' => array('MembergroupMembers', 'manage_membergroups', 'Groups.php'),
		'settings' => array('ModifyMembergroupsettings', 'admin_forum'),
	);

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

	call_integration_hook('integrate_manage_membergroups', array(&$subActions));

	// Call the right function.
	call_helper($subActions[$_REQUEST['sa']][0]);
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
	global $txt, $scripturl, $context, $sourcedir;

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
					'function' => function($rowData) use ($scripturl)
					{
						// Since the moderator group has no explicit members, no link is needed.
						if ($rowData['id_group'] == 3)
							$group_name = $rowData['group_name'];
						else
						{
							$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);
							$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', $scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
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
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
					'class' => 'centercol',
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
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'above_table_headers',
				'value' => '<a class="button" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<a class="button" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
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
					'function' => function($rowData) use ($scripturl)
					{
						$colorStyle = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);
						return sprintf('<a href="%1$s?action=moderate;area=viewgroups;sa=members;group=%2$d"%3$s>%4$s</a>', $scripturl, $rowData['id_group'], $colorStyle, $rowData['group_name']);
					},
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
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
					'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons',
					'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
					'class' => 'centercol',
				),
				'data' => array(
					'db' => 'num_members',
					'class' => 'centercol',
				),
				'sort' => array(
					'default' => '1 DESC',
					'reverse' => '1',
				),
			),
			'required_posts' => array(
				'header' => array(
					'value' => $txt['membergroups_min_posts'],
					'class' => 'centercol',
				),
				'data' => array(
					'db' => 'min_posts',
					'class' => 'centercol',
				),
				'sort' => array(
					'default' => 'mg.min_posts',
					'reverse' => 'mg.min_posts DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<a class="button" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . $txt['membergroups_add_group'] . '</a>',
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

		call_integration_hook('integrate_pre_add_membergroup', array());

		$id_group = $smcFunc['db_insert']('',
			'{db_prefix}membergroups',
			array(
				'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
				'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int',
			),
			array(
				'', $smcFunc['htmlspecialchars']($_POST['group_name'], ENT_QUOTES), ($postCountBasedGroup ? (int) $_POST['min_posts'] : '-1'),
				'1#icon.png', '', $_POST['group_type'],
			),
			array('id_group'),
			1
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
			{
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

				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}board_permissions_view
					WHERE id_board IN ({array_int:board_list})
						AND id_group = {int:group_id}
						AND deny = {int:deny}',
					array(
						'board_list' => $changed_boards[$board_action],
						'group_id' => $id_group,
						'deny' => $board_action == 'allow' ? 0 : 1,
					)
				);

				$insert = array();
				foreach ($changed_boards[$board_action] as $board_id)
					$insert[] = array($id_group, $board_id, $board_action == 'allow' ? 0 : 1);

				$smcFunc['db_insert']('insert',
					'{db_prefix}board_permissions_view',
					array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
					$insert,
					array('id_group', 'id_board', 'deny')
				);
			}

		}

		// If this is joinable then set it to show group membership in people's profiles.
		if (empty($modSettings['show_group_membership']) && $_POST['group_type'] > 1)
			updateSettings(array('show_group_membership' => 1));

		// Rebuild the group cache.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// We did it.
		logAction('add_group', array('group' => $smcFunc['htmlspecialchars']($_POST['group_name'])), 'admin');

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
	$result = deleteMembergroups((int) $_REQUEST['group']);
	// Need to throw a warning if it went wrong, but this is the only one we have a message for...
	if ($result === 'group_cannot_delete_sub')
		fatal_lang_error('membergroups_cannot_delete_paid', false);

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

	// People who can manage boards are a bit special.
	require_once($sourcedir . '/Subs-Members.php');
	$board_managers = groupsAllowedTo('manage_boards', null);
	$context['can_manage_boards'] = in_array($_REQUEST['group'], $board_managers['allowed']);

	// Can this group moderate any boards?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_board)
		FROM {db_prefix}moderator_groups
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $_REQUEST['group'],
		)
	);

	// Why don't we have a $smcFunc['db_result'] function?
	$result = $smcFunc['db_fetch_row']($request);
	$context['is_moderator_group'] = ($result[0] > 0);
	$smcFunc['db_free_result']($request);

	// The delete this membergroup button was pressed.
	if (isset($_POST['delete']))
	{
		checkSession();
		validateToken('admin-mmg');

		require_once($sourcedir . '/Subs-Membergroups.php');
		$result = deleteMembergroups($_REQUEST['group']);
		// Need to throw a warning if it went wrong, but this is the only one we have a message for...
		if ($result === 'group_cannot_delete_sub')
			fatal_lang_error('membergroups_cannot_delete_paid', false);

		redirectexit('action=admin;area=membergroups;');
	}
	// A form was submitted with the new membergroup settings.
	elseif (isset($_POST['save']))
	{
		// Validate the session.
		checkSession();
		validateToken('admin-mmg');

		// Can they really inherit from this group?
		if ($_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
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
		$_POST['group_tfa_force'] = (empty($modSettings['tfa_mode']) || $modSettings['tfa_mode'] != 2 || empty($_POST['group_tfa_force'])) ? 0 : 1;

		//@todo Don't set online_color for the Moderators group?

		// Do the update of the membergroup settings.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}membergroups
			SET group_name = {string:group_name}, online_color = {string:online_color},
				max_messages = {int:max_messages}, min_posts = {int:min_posts}, icons = {string:icons},
				description = {string:group_desc}, group_type = {int:group_type}, hidden = {int:group_hidden},
				id_parent = {int:group_inherit}, tfa_required = {int:tfa_required}
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
				'tfa_required' => $_POST['group_tfa_force'],
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

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}board_permissions_view
				WHERE id_group = {int:group_id}',
				array(
					'group_id' => (int) $_REQUEST['group'],
				)
			);

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
				{
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

					$insert = array();
					foreach ($changed_boards[$board_action] as $board_id)
						$insert[] = array((int) $_REQUEST['group'], $board_id, $board_action == 'allow' ? 0 : 1);

					$smcFunc['db_insert']('insert',
						'{db_prefix}board_permissions_view',
						array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
						$insert,
						array('id_group', 'id_board', 'deny')
					);
				}
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

			// Sorry, but post groups can't moderate boards
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}moderator_groups
				WHERE id_group = {int:current_group}',
				array(
					'current_group' => (int) $_REQUEST['group'],
				)
			);
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
				{
					$new_groups = (!empty($additional_groups) ? $additional_groups . ',' : '') . $_REQUEST['group']; // We already validated this a while ago.
					updateMemberData($memberArray, array('additional_groups' => $new_groups));
				}

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}members
					SET id_group = {int:regular_member}
					WHERE id_group = {int:current_group}',
					array(
						'regular_member' => 0,
						'current_group' => $_REQUEST['group'],
					)
				);

				// Hidden groups can't moderate boards
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}moderator_groups
					WHERE id_group = {int:current_group}',
					array(
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
			$group_moderators = array();

			// Get all the usernames from the string
			if (!empty($moderator_string))
			{
				$moderator_string = strtr(preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $smcFunc['htmlspecialchars']($moderator_string, ENT_QUOTES)), array('&quot;' => '"'));
				preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
				$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
				for ($k = 0, $n = count($moderators); $k < $n; $k++)
				{
					$moderators[$k] = trim($moderators[$k]);

					if (strlen($moderators[$k]) == 0)
						unset($moderators[$k]);
				}

				// Find all the id_member's for the member_name's in the list.
				if (!empty($moderators))
				{
					$request = $smcFunc['db_query']('', '
						SELECT id_member
						FROM {db_prefix}members
						WHERE member_name IN ({array_string:moderators}) OR real_name IN ({array_string:moderators})
						LIMIT {int:count}',
						array(
							'moderators' => $moderators,
							'count' => count($moderators),
						)
					);
					while ($row = $smcFunc['db_fetch_assoc']($request))
						$group_moderators[] = $row['id_member'];
					$smcFunc['db_free_result']($request);
				}
			}

			if (!empty($_POST['moderator_list']))
			{
				$moderators = array();
				foreach ($_POST['moderator_list'] as $moderator)
					$moderators[] = (int) $moderator;

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

			// Make sure we don't have any duplicates first...
			$group_moderators = array_unique($group_moderators);

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
		logAction('edited_group', array('group' => $smcFunc['htmlspecialchars']($_POST['group_name'])), 'admin');

		redirectexit('action=admin;area=membergroups');
	}

	// Fetch the current group information.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, description, min_posts, online_color, max_messages, icons, group_type, hidden, id_parent, tfa_required
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
		'description' => $smcFunc['htmlspecialchars']($row['description'], ENT_QUOTES),
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
		'tfa_required' => $row['tfa_required'],
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
	}

	// Get a list of all the image formats we can select.
	$imageExts = array('png', 'jpg', 'jpeg', 'bmp', 'gif');

	// Scan the directory.
	$context['possible_icons'] = array();
	if ($files = scandir($settings['default_theme_dir'] . '/images/membericons'))
	{
		// Loop through every file in the directory.
		foreach ($files as $value)
		{
			// Grab the image extension.
			$ext = pathinfo($settings['default_theme_dir'] . '/images/membericons/' . $value, PATHINFO_EXTENSION);

			// If the extension is not empty, and it is valid
			if (!empty($ext) && in_array($ext, $imageExts))
				$context['possible_icons'][] = $value;
		}
	}

	// Insert our JS, if we have possible icons.
	if (!empty($context['possible_icons']))
		loadJavaScriptFile('icondropdown.js', array('validate' => true, 'minimize' => true), 'smf_icondropdown');

	loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');

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
	global $context, $sourcedir, $scripturl, $txt;

	$context['sub_template'] = 'show_settings';
	$context['page_title'] = $txt['membergroups_settings'];

	// Needed for the settings functions.
	require_once($sourcedir . '/ManageServer.php');

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
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=membergroups;sa=settings');
	}

	// Some simple context.
	$context['post_url'] = $scripturl . '?action=admin;area=membergroups;save;sa=settings';
	$context['settings_title'] = $txt['membergroups_settings'];

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

?>