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

/* This file is concerned with anything in the Manage Membergroups screen.

	void ModifyMembergroups()
		- entrance point of the 'Manage Membergroups' center.
		- called by ?action=admin;area=membergroups.
		- loads the ManageMembergroups template.
		- loads the MangeMembers language file.
		- requires the manage_membergroups or the admin_forum permission.
		- calls a function based on the given subaction.
		- defaults to sub action 'index' or without manage_membergroup
		  permissions to 'settings'.

	void MembergroupIndex()
		- shows an overview of the current membergroups.
		- called by ?action=admin;area=membergroups.
		- requires the manage_membergroups permission.
		- uses the main ManageMembergroups template.
		- splits the membergroups in regular ones and post count based groups.
		- also counts the number of members part of each membergroup.

	void AddMembergroup()
		- allows to add a membergroup and set some initial properties.
		- called by ?action=admin;area=membergroups;sa=add.
		- requires the manage_membergroups permission.
		- uses the new_group sub template of ManageMembergroups.
		- allows to use a predefined permission profile or copy one from
		  another group.
		- redirects to action=admin;area=membergroups;sa=edit;group=x.

	void DeleteMembergroup()
		- deletes a membergroup by URL.
		- called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
		- requires the manage_membergroups permission.
		- redirects to ?action=admin;area=membergroups.

	void EditMembergroup()
		- screen to edit a specific membergroup.
		- called by ?action=admin;area=membergroups;sa=edit;group=x.
		- requires the manage_membergroups permission.
		- uses the edit_group sub template of ManageMembergroups.
		- also handles the delete button of the edit form.
		- redirects to ?action=admin;area=membergroups.

	void ModifyMembergroupsettings()
		- set some general membergroup settings and permissions.
		- called by ?action=admin;area=membergroups;sa=settings
		- requires the admin_forum permission (and manage_permissions for
		  changing permissions)
		- uses membergroup_settings sub template of ManageMembergroups.
		- redirects to itself.
*/

// The entrance point for all 'Manage Membergroup' actions.
function ModifyMembergroups()
{
	global $context, $txt, $scripturl, $sourcedir;

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

	// Call the right function.
	$subActions[$_REQUEST['sa']][0]();
}

// An overview of the current membergroups.
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
							$group_name .= sprintf(\' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqWin(this.href);">?</a>)\', $scripturl);
						elseif ($rowData[\'id_group\'] == 3)
							$group_name .= sprintf(\' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqWin(this.href);">?</a>)\', $scripturl);

						return $group_name;
					'),
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name DESC',
				),
			),
			'stars' => array(
				'header' => array(
					'value' => $txt['membergroups_stars'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $settings;

						$stars = explode(\'#\', $rowData[\'stars\']);

						// In case no stars are setup, return with nothing
						if (empty($stars[0]) || empty($stars[1]))
							return \'\';

						// Otherwise repeat the image a given number of times.
						else
						{
							$image = sprintf(\'<img src="%1$s/%2$s" alt="*" />\', $settings[\'images_url\'], $stars[1]);
							return str_repeat($image, $stars[0]);
						}
					'),

				),
				'sort' => array(
					'default' => 'stars',
					'reverse' => 'stars DESC',
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
				'value' => '[<a href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>]',
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
			'stars' => array(
				'header' => array(
					'value' => $txt['membergroups_stars'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $settings;

						$stars = explode(\'#\', $rowData[\'stars\']);

						if (empty($stars[0]) || empty($stars[1]))
							return \'\';
						else
						{
							$star_image = sprintf(\'<img src="%1$s/%2$s" alt="*" />\', $settings[\'images_url\'], $stars[1]);
							return str_repeat($star_image, $stars[0]);
						}
					'),
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, stars',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, stars DESC',
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
				'value' => '[<a href="' . $scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . $txt['membergroups_add_group'] . '</a>]',
			),
		),
	);

	createList($listOptions);
}

// Add a membergroup.
function AddMembergroup()
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc;

	// A form was submitted, we can start adding.
	if (!empty($_POST['group_name']))
	{
		checkSession();

		$postCountBasedGroup = isset($_POST['min_posts']) && (!isset($_POST['postgroup_based']) || !empty($_POST['postgroup_based']));
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

		// !!! Check for members with same name too?

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
				'stars' => 'string', 'online_color' => 'string', 'group_type' => 'int',
			),
			array(
				$id_group, '', $_POST['group_name'], ($postCountBasedGroup ? (int) $_POST['min_posts'] : '-1'),
				'1#star.gif', '', $_POST['group_type'],
			),
			array('id_group')
		);

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
					SELECT online_color, max_messages, stars
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
						stars = {string:stars}
					WHERE id_group = {int:current_group}',
					array(
						'max_messages' => $group_info['max_messages'],
						'current_group' => $id_group,
						'online_color' => $group_info['online_color'],
						'stars' => $group_info['stars'],
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
		$_POST['boardaccess'] = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
		foreach ($_POST['boardaccess'] as $key => $value)
			$_POST['boardaccess'][$key] = (int) $value;

		// Only do this if they have special access requirements.
		if (!empty($_POST['boardaccess']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET member_groups = CASE WHEN member_groups = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT(member_groups, {string:comma_group}) END
				WHERE id_board IN ({array_int:board_list})',
				array(
					'board_list' => $_POST['boardaccess'],
					'blank_string' => '',
					'group_id_string' => (string) $id_group,
					'comma_group' => ',' . $id_group,
				)
			);

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

	$result = $smcFunc['db_query']('', '
		SELECT id_board, name, child_level
		FROM {db_prefix}boards
		ORDER BY board_order',
		array(
		)
	);
	$context['boards'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['boards'][] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => false
		);
	$smcFunc['db_free_result']($result);
}

// Deleting a membergroup by URL (not implemented).
function DeleteMembergroup()
{
	global $sourcedir;

	checkSession('get');

	require_once($sourcedir . '/Subs-Membergroups.php');
	deleteMembergroups((int) $_REQUEST['group']);

	// Go back to the membergroup index.
	redirectexit('action=admin;area=membergroups;');
}

// Editing a membergroup.
function EditMembergroup()
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc;

	$_REQUEST['group'] = isset($_REQUEST['group']) && $_REQUEST['group'] > 0 ? (int) $_REQUEST['group'] : 0;

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

		require_once($sourcedir . '/Subs-Membergroups.php');
		deleteMembergroups($_REQUEST['group']);

		redirectexit('action=admin;area=membergroups;');
	}
	// A form was submitted with the new membergroup settings.
	elseif (isset($_POST['submit']))
	{
		// Validate the session.
		checkSession();

		// Can they really inherit from this group?
		if ($_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
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
		$_POST['stars'] = (empty($_POST['star_count']) || $_POST['star_count'] < 0) ? '' : min((int) $_POST['star_count'], 99) . '#' . $_POST['star_image'];
		$_POST['group_desc'] = isset($_POST['group_desc']) && ($_REQUEST['group'] == 1 || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? trim($_POST['group_desc']) : '';
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];
		$_POST['group_hidden'] = empty($_POST['group_hidden']) || $_POST['min_posts'] != -1 || $_REQUEST['group'] == 3 ? 0 : (int) $_POST['group_hidden'];
		$_POST['group_inherit'] = $_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && (empty($inherit_type) || $inherit_type != 1) ? (int) $_POST['group_inherit'] : -2;

		// !!! Don't set online_color for the Moderators group?

		// Do the update of the membergroup settings.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}membergroups
			SET group_name = {string:group_name}, online_color = {string:online_color},
				max_messages = {int:max_messages}, min_posts = {int:min_posts}, stars = {string:stars},
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
				'group_name' => $_POST['group_name'],
				'online_color' => $_POST['online_color'],
				'stars' => $_POST['stars'],
				'group_desc' => $_POST['group_desc'],
			)
		);

		// Time to update the boards this membergroup has access to.
		if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
		{
			$_POST['boardaccess'] = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
			foreach ($_POST['boardaccess'] as $key => $value)
				$_POST['boardaccess'][$key] = (int) $value;

			// Find all board this group is in, but shouldn't be in.
			$request = $smcFunc['db_query']('', '
				SELECT id_board, member_groups
				FROM {db_prefix}boards
				WHERE FIND_IN_SET({string:current_group}, member_groups) != 0' . (empty($_POST['boardaccess']) ? '' : '
					AND id_board NOT IN ({array_int:board_access_list})'),
				array(
					'current_group' => (int) $_REQUEST['group'],
					'board_access_list' => $_POST['boardaccess'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET member_groups = {string:member_group_access}
					WHERE id_board = {int:current_board}',
					array(
						'current_board' => $row['id_board'],
						'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($_REQUEST['group']))),
					)
				);
			$smcFunc['db_free_result']($request);

			// Add the membergroup to all boards that hadn't been set yet.
			if (!empty($_POST['boardaccess']))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET member_groups = CASE WHEN member_groups = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT(member_groups, {string:comma_group}) END
					WHERE id_board IN ({array_int:board_list})
						AND FIND_IN_SET({int:current_group}, member_groups) = 0',
					array(
						'board_list' => $_POST['boardaccess'],
						'blank_string' => '',
						'current_group' => (int) $_REQUEST['group'],
						'group_id_string' => (string) (int) $_REQUEST['group'],
						'comma_group' => ',' . $_REQUEST['group'],
					)
				);
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
		// We've definetely changed some group stuff.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// Log the edit.
		logAction('edited_group', array('group' => $_POST['group_name']), 'admin');

		redirectexit('action=admin;area=membergroups');
	}

	// Fetch the current group information.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, description, min_posts, online_color, max_messages, stars, group_type, hidden, id_parent
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

	$row['stars'] = explode('#', $row['stars']);

	$context['group'] = array(
		'id' => $_REQUEST['group'],
		'name' => $row['group_name'],
		'description' => htmlspecialchars($row['description']),
		'editable_name' => htmlspecialchars($row['group_name']),
		'color' => $row['online_color'],
		'min_posts' => $row['min_posts'],
		'max_messages' => $row['max_messages'],
		'star_count' => (int) $row['stars'][0],
		'star_image' => isset($row['stars'][1]) ? $row['stars'][1] : '',
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
		$result = $smcFunc['db_query']('', '
			SELECT id_board, name, child_level, FIND_IN_SET({string:current_group}, member_groups) != 0 AS can_access
			FROM {db_prefix}boards
			ORDER BY board_order',
			array(
				'current_group' => (int) $_REQUEST['group'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$context['boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'selected' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
			);
		$smcFunc['db_free_result']($result);
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

	$context['sub_template'] = 'edit_group';
	$context['page_title'] = $txt['membergroups_edit_group'];
}

// Set general membergroup settings.
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

	if (isset($_REQUEST['save']))
	{
		checkSession();

		// Yeppers, saving this...
		saveDBSettings($config_vars);
		redirectexit('action=admin;area=membergroups;sa=settings');
	}

	// Some simple context.
	$context['post_url'] = $scripturl . '?action=admin;area=membergroups;save;sa=settings';
	$context['settings_title'] = $txt['membergroups_settings'];

	prepareDBSettingContext($config_vars);
}

?>