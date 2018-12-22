<?php

/**
 * Manage and maintain the boards and categories of the forum.
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
 * The main dispatcher; doesn't do anything, just delegates.
 * This is the main entry point for all the manageboards admin screens.
 * Called by ?action=admin;area=manageboards.
 * It checks the permissions, based on the sub-action, and calls a function based on the sub-action.
 *
 * @uses ManageBoards language file.
 */
function ManageBoards()
{
	global $context, $txt;

	// Everything's gonna need this.
	loadLanguage('ManageBoards');

	// Format: 'sub-action' => array('function', 'permission')
	$subActions = array(
		'board' => array('EditBoard', 'manage_boards'),
		'board2' => array('EditBoard2', 'manage_boards'),
		'cat' => array('EditCategory', 'manage_boards'),
		'cat2' => array('EditCategory2', 'manage_boards'),
		'main' => array('ManageBoardsMain', 'manage_boards'),
		'move' => array('ManageBoardsMain', 'manage_boards'),
		'newcat' => array('EditCategory', 'manage_boards'),
		'newboard' => array('EditBoard', 'manage_boards'),
		'settings' => array('EditBoardSettings', 'admin_forum'),
	);

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['boards_and_cats'],
		'help' => 'manage_boards',
		'description' => $txt['boards_and_cats_desc'],
		'tabs' => array(
			'main' => array(
			),
			'newcat' => array(
			),
			'settings' => array(
				'description' => $txt['mboards_settings_desc'],
			),
		),
	);

	call_integration_hook('integrate_manage_boards', array(&$subActions));

	// Default to sub action 'main' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_boards') ? 'main' : 'settings');

	// Have you got the proper permissions?
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * The main control panel thing, the screen showing all boards and categories.
 * Called by ?action=admin;area=manageboards or ?action=admin;area=manageboards;sa=move.
 * Requires manage_boards permission.
 * It also handles the interface for moving boards.
 *
 * @uses ManageBoards template, main sub-template.
 */
function ManageBoardsMain()
{
	global $txt, $context, $cat_tree, $boards, $boardList, $scripturl, $sourcedir, $smcFunc;

	loadTemplate('ManageBoards');

	require_once($sourcedir . '/Subs-Boards.php');

	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'move' && in_array($_REQUEST['move_to'], array('child', 'before', 'after', 'top')))
	{
		checkSession('get');
		validateToken('admin-bm-' . (int) $_REQUEST['src_board'], 'request');

		if ($_REQUEST['move_to'] === 'top')
			$boardOptions = array(
				'move_to' => $_REQUEST['move_to'],
				'target_category' => (int) $_REQUEST['target_cat'],
				'move_first_child' => true,
			);
		else
			$boardOptions = array(
				'move_to' => $_REQUEST['move_to'],
				'target_board' => (int) $_REQUEST['target_board'],
				'move_first_child' => true,
			);
		modifyBoard((int) $_REQUEST['src_board'], $boardOptions);
	}

	getBoardTree();

	$context['move_board'] = !empty($_REQUEST['move']) && isset($boards[(int) $_REQUEST['move']]) ? (int) $_REQUEST['move'] : 0;

	$context['categories'] = array();
	foreach ($cat_tree as $catid => $tree)
	{
		$context['categories'][$catid] = array(
			'name' => &$tree['node']['name'],
			'id' => &$tree['node']['id'],
			'boards' => array()
		);
		$move_cat = !empty($context['move_board']) && $boards[$context['move_board']]['category'] == $catid;
		foreach ($boardList[$catid] as $boardid)
		{
			$context['categories'][$catid]['boards'][$boardid] = array(
				'id' => &$boards[$boardid]['id'],
				'name' => &$boards[$boardid]['name'],
				'description' => &$boards[$boardid]['description'],
				'child_level' => &$boards[$boardid]['level'],
				'move' => $move_cat && ($boardid == $context['move_board'] || isChildOf($boardid, $context['move_board'])),
				'permission_profile' => &$boards[$boardid]['profile'],
				'is_redirect' => !empty($boards[$boardid]['redirect']),
			);
		}
	}

	if (!empty($context['move_board']))
	{
		createToken('admin-bm-' . $context['move_board'], 'request');

		$context['move_title'] = sprintf($txt['mboards_select_destination'], $smcFunc['htmlspecialchars']($boards[$context['move_board']]['name']));
		foreach ($cat_tree as $catid => $tree)
		{
			$prev_child_level = 0;
			$prev_board = 0;
			$stack = array();
			// Just a shortcut, this is the same for all the urls
			$security = $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-bm-' . $context['move_board'] . '_token_var'] . '=' . $context['admin-bm-' . $context['move_board'] . '_token'];
			foreach ($boardList[$catid] as $boardid)
			{
				if (!isset($context['categories'][$catid]['move_link']))
					$context['categories'][$catid]['move_link'] = array(
						'child_level' => 0,
						'label' => $txt['mboards_order_before'] . ' \'' . $smcFunc['htmlspecialchars']($boards[$boardid]['name']) . '\'',
						'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=before;' . $security,
					);

				if (!$context['categories'][$catid]['boards'][$boardid]['move'])
					$context['categories'][$catid]['boards'][$boardid]['move_links'] = array(
						array(
							'child_level' => $boards[$boardid]['level'],
							'label' => $txt['mboards_order_after'] . '\'' . $smcFunc['htmlspecialchars']($boards[$boardid]['name']) . '\'',
							'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=after;' . $security,
							'class' => $boards[$boardid]['level'] > 0 ? 'above' : 'below',
						),
						array(
							'child_level' => $boards[$boardid]['level'] + 1,
							'label' => $txt['mboards_order_child_of'] . ' \'' . $smcFunc['htmlspecialchars']($boards[$boardid]['name']) . '\'',
							'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=child;' . $security,
							'class' => 'here',
						),
					);

				$difference = $boards[$boardid]['level'] - $prev_child_level;
				if ($difference == 1)
					array_push($stack, !empty($context['categories'][$catid]['boards'][$prev_board]['move_links']) ? array_shift($context['categories'][$catid]['boards'][$prev_board]['move_links']) : null);
				elseif ($difference < 0)
				{
					if (empty($context['categories'][$catid]['boards'][$prev_board]['move_links']))
						$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array();
					for ($i = 0; $i < -$difference; $i++)
						if (($temp = array_pop($stack)) != null)
							array_unshift($context['categories'][$catid]['boards'][$prev_board]['move_links'], $temp);
				}

				$prev_board = $boardid;
				$prev_child_level = $boards[$boardid]['level'];
			}
			if (!empty($stack) && !empty($context['categories'][$catid]['boards'][$prev_board]['move_links']))
				$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array_merge($stack, $context['categories'][$catid]['boards'][$prev_board]['move_links']);
			elseif (!empty($stack))
				$context['categories'][$catid]['boards'][$prev_board]['move_links'] = $stack;

			if (empty($boardList[$catid]))
				$context['categories'][$catid]['move_link'] = array(
					'child_level' => 0,
					'label' => $txt['mboards_order_before'] . ' \'' . $smcFunc['htmlspecialchars']($tree['node']['name']) . '\'',
					'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_cat=' . $catid . ';move_to=top;' . $security,
				);
		}
	}

	call_integration_hook('integrate_boards_main');

	$context['page_title'] = $txt['boards_and_cats'];
	$context['can_manage_permissions'] = allowedTo('manage_permissions');
}

/**
 * Modify a specific category.
 * (screen for editing and repositioning a category.)
 * Also used to show the confirm deletion of category screen
 * (sub-template confirm_category_delete).
 * Called by ?action=admin;area=manageboards;sa=cat
 * Requires manage_boards permission.
 *
 * @uses ManageBoards template, modify_category sub-template.
 */
function EditCategory()
{
	global $txt, $context, $cat_tree, $boardList, $boards, $smcFunc, $sourcedir;

	loadTemplate('ManageBoards');
	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Editor.php');
	getBoardTree();

	// id_cat must be a number.... if it exists.
	$_REQUEST['cat'] = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;

	// Start with one - "In first place".
	$context['category_order'] = array(
		array(
			'id' => 0,
			'name' => $txt['mboards_order_first'],
			'selected' => !empty($_REQUEST['cat']) ? $cat_tree[$_REQUEST['cat']]['is_first'] : false,
			'true_name' => ''
		)
	);

	// If this is a new category set up some defaults.
	if ($_REQUEST['sa'] == 'newcat')
	{
		$context['category'] = array(
			'id' => 0,
			'name' => $txt['mboards_new_cat_name'],
			'editable_name' => $smcFunc['htmlspecialchars']($txt['mboards_new_cat_name']),
			'description' => '',
			'can_collapse' => true,
			'is_new' => true,
			'is_empty' => true
		);
	}
	// Category doesn't exist, man... sorry.
	elseif (!isset($cat_tree[$_REQUEST['cat']]))
		redirectexit('action=admin;area=manageboards');
	else
	{
		$context['category'] = array(
			'id' => $_REQUEST['cat'],
			'name' => $cat_tree[$_REQUEST['cat']]['node']['name'],
			'editable_name' => html_to_bbc($cat_tree[$_REQUEST['cat']]['node']['name']),
			'description' => html_to_bbc($cat_tree[$_REQUEST['cat']]['node']['description']),
			'can_collapse' => !empty($cat_tree[$_REQUEST['cat']]['node']['can_collapse']),
			'children' => array(),
			'is_empty' => empty($cat_tree[$_REQUEST['cat']]['children'])
		);

		foreach ($boardList[$_REQUEST['cat']] as $child_board)
			$context['category']['children'][] = str_repeat('-', $boards[$child_board]['level']) . ' ' . $boards[$child_board]['name'];
	}

	$prevCat = 0;
	foreach ($cat_tree as $catid => $tree)
	{
		if ($catid == $_REQUEST['cat'] && $prevCat > 0)
			$context['category_order'][$prevCat]['selected'] = true;
		elseif ($catid != $_REQUEST['cat'])
			$context['category_order'][$catid] = array(
				'id' => $catid,
				'name' => $txt['mboards_order_after'] . $tree['node']['name'],
				'selected' => false,
				'true_name' => $tree['node']['name']
			);
		$prevCat = $catid;
	}
	if (!isset($_REQUEST['delete']))
	{
		$context['sub_template'] = 'modify_category';
		$context['page_title'] = $_REQUEST['sa'] == 'newcat' ? $txt['mboards_new_cat_name'] : $txt['cat_edit'];
	}
	else
	{
		$context['sub_template'] = 'confirm_category_delete';
		$context['page_title'] = $txt['mboards_delete_cat'];
	}

	// Create a special token.
	createToken('admin-bc-' . $_REQUEST['cat']);
	$context['token_check'] = 'admin-bc-' . $_REQUEST['cat'];

	call_integration_hook('integrate_edit_category');
}

/**
 * Function for handling a submitted form saving the category.
 * (complete the modifications to a specific category.)
 * It also handles deletion of a category.
 * It requires manage_boards permission.
 * Called by ?action=admin;area=manageboards;sa=cat2
 * Redirects to ?action=admin;area=manageboards.
 */
function EditCategory2()
{
	global $sourcedir, $smcFunc, $context;

	checkSession();
	validateToken('admin-bc-' . $_REQUEST['cat']);

	require_once($sourcedir . '/Subs-Categories.php');

	$_POST['cat'] = (int) $_POST['cat'];

	// Add a new category or modify an existing one..
	if (isset($_POST['edit']) || isset($_POST['add']))
	{
		$catOptions = array();

		if (isset($_POST['cat_order']))
			$catOptions['move_after'] = (int) $_POST['cat_order'];

		// Change "This & That" to "This &amp; That" but don't change "&cent" to "&amp;cent;"...
		$catOptions['cat_name'] = parse_bbc($smcFunc['htmlspecialchars']($_POST['cat_name']), false, '', $context['description_allowed_tags']);
		$catOptions['cat_desc'] = parse_bbc($smcFunc['htmlspecialchars']($_POST['cat_desc']), false, '', $context['description_allowed_tags']);

		$catOptions['is_collapsible'] = isset($_POST['collapse']);

		if (isset($_POST['add']))
			createCategory($catOptions);
		else
			modifyCategory($_POST['cat'], $catOptions);
	}
	// If they want to delete - first give them confirmation.
	elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['empty']))
	{
		EditCategory();
		return;
	}
	// Delete the category!
	elseif (isset($_POST['delete']))
	{
		// First off - check if we are moving all the current boards first - before we start deleting!
		if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1)
		{
			if (empty($_POST['cat_to']))
				fatal_lang_error('mboards_delete_error');

			deleteCategories(array($_POST['cat']), (int) $_POST['cat_to']);
		}
		else
			deleteCategories(array($_POST['cat']));
	}

	redirectexit('action=admin;area=manageboards');
}

/**
 * Modify a specific board...
 * screen for editing and repositioning a board.
 * called by ?action=admin;area=manageboards;sa=board
 * uses the modify_board sub-template of the ManageBoards template.
 * requires manage_boards permission.
 * also used to show the confirm deletion of category screen (sub-template confirm_board_delete).
 */
function EditBoard()
{
	global $txt, $context, $cat_tree, $boards, $boardList;
	global $sourcedir, $smcFunc, $modSettings;

	loadTemplate('ManageBoards');
	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Editor.php');
	getBoardTree();

	// For editing the profile we'll need this.
	loadLanguage('ManagePermissions');
	require_once($sourcedir . '/ManagePermissions.php');
	loadPermissionProfiles();

	// People with manage-boards are special.
	require_once($sourcedir . '/Subs-Members.php');
	$groups = groupsAllowedTo('manage_boards', null);
	$context['board_managers'] = $groups['allowed']; // We don't need *all* this in $context.

	// id_board must be a number....
	$_REQUEST['boardid'] = isset($_REQUEST['boardid']) ? (int) $_REQUEST['boardid'] : 0;
	if (!isset($boards[$_REQUEST['boardid']]))
	{
		$_REQUEST['boardid'] = 0;
		$_REQUEST['sa'] = 'newboard';
	}

	if ($_REQUEST['sa'] == 'newboard')
	{
		// Category doesn't exist, man... sorry.
		if (empty($_REQUEST['cat']))
			redirectexit('action=admin;area=manageboards');

		// Some things that need to be setup for a new board.
		$curBoard = array(
			'member_groups' => array(0, -1),
			'deny_groups' => array(),
			'category' => (int) $_REQUEST['cat']
		);
		$context['board_order'] = array();
		$context['board'] = array(
			'is_new' => true,
			'id' => 0,
			'name' => $txt['mboards_new_board_name'],
			'description' => '',
			'count_posts' => 1,
			'posts' => 0,
			'topics' => 0,
			'theme' => 0,
			'profile' => 1,
			'override_theme' => 0,
			'redirect' => '',
			'category' => (int) $_REQUEST['cat'],
			'no_children' => true,
		);
	}
	else
	{
		// Just some easy shortcuts.
		$curBoard = &$boards[$_REQUEST['boardid']];
		$context['board'] = $boards[$_REQUEST['boardid']];
		$context['board']['name'] = html_to_bbc($context['board']['name']);
		$context['board']['description'] = html_to_bbc($context['board']['description']);
		$context['board']['no_children'] = empty($boards[$_REQUEST['boardid']]['tree']['children']);
		$context['board']['is_recycle'] = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) && $modSettings['recycle_board'] == $context['board']['id'];
	}

	// As we may have come from the permissions screen keep track of where we should go on save.
	$context['redirect_location'] = isset($_GET['rid']) && $_GET['rid'] == 'permissions' ? 'permissions' : 'boards';

	// We might need this to hide links to certain areas.
	$context['can_manage_permissions'] = allowedTo('manage_permissions');

	// Default membergroups.
	$context['groups'] = array(
		-1 => array(
			'id' => '-1',
			'name' => $txt['parent_guests_only'],
			'allow' => in_array('-1', $curBoard['member_groups']),
			'deny' => in_array('-1', $curBoard['deny_groups']),
			'is_post_group' => false,
		),
		0 => array(
			'id' => '0',
			'name' => $txt['parent_members_only'],
			'allow' => in_array('0', $curBoard['member_groups']),
			'deny' => in_array('0', $curBoard['deny_groups']),
			'is_post_group' => false,
		)
	);

	// Load membergroups.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group > {int:moderator_group} OR id_group = {int:global_moderator}
		ORDER BY min_posts, id_group != {int:global_moderator}, group_name',
		array(
			'moderator_group' => 3,
			'global_moderator' => 2,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($_REQUEST['sa'] == 'newboard' && $row['min_posts'] == -1)
			$curBoard['member_groups'][] = $row['id_group'];

		$context['groups'][(int) $row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => trim($row['group_name']),
			'allow' => in_array($row['id_group'], $curBoard['member_groups']),
			'deny' => in_array($row['id_group'], $curBoard['deny_groups']),
			'is_post_group' => $row['min_posts'] != -1,
		);
	}
	$smcFunc['db_free_result']($request);

	// Category doesn't exist, man... sorry.
	if (!isset($boardList[$curBoard['category']]))
		redirectexit('action=admin;area=manageboards');

	foreach ($boardList[$curBoard['category']] as $boardid)
	{
		if ($boardid == $_REQUEST['boardid'])
		{
			$context['board_order'][] = array(
				'id' => $boardid,
				'name' => str_repeat('-', $boards[$boardid]['level']) . ' (' . $txt['mboards_current_position'] . ')',
				'children' => $boards[$boardid]['tree']['children'],
				'no_children' => empty($boards[$boardid]['tree']['children']),
				'is_child' => false,
				'selected' => true
			);
		}
		else
		{
			$context['board_order'][] = array(
				'id' => $boardid,
				'name' => str_repeat('-', $boards[$boardid]['level']) . ' ' . $boards[$boardid]['name'],
				'is_child' => empty($_REQUEST['boardid']) ? false : isChildOf($boardid, $_REQUEST['boardid']),
				'selected' => false
			);
		}
	}

	// Are there any places to move child boards to in the case where we are confirming a delete?
	if (!empty($_REQUEST['boardid']))
	{
		$context['can_move_children'] = false;
		$context['children'] = $boards[$_REQUEST['boardid']]['tree']['children'];

		foreach ($context['board_order'] as $lBoard)
			if ($lBoard['is_child'] == false && $lBoard['selected'] == false)
				$context['can_move_children'] = true;
	}

	// Get other available categories.
	$context['categories'] = array();
	foreach ($cat_tree as $catID => $tree)
		$context['categories'][] = array(
			'id' => $catID == $curBoard['category'] ? 0 : $catID,
			'name' => $tree['node']['name'],
			'selected' => $catID == $curBoard['category']
		);

	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_board = {int:current_board}',
		array(
			'current_board' => $_REQUEST['boardid'],
		)
	);
	$context['board']['moderators'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['board']['moderators'][$row['id_member']] = $row['real_name'];
	$smcFunc['db_free_result']($request);

	$context['board']['moderator_list'] = empty($context['board']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['board']['moderators']) . '&quot;';

	if (!empty($context['board']['moderators']))
		list ($context['board']['last_moderator_id']) = array_slice(array_keys($context['board']['moderators']), -1);

	// Get all the groups assigned as moderators
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}moderator_groups
		WHERE id_board = {int:current_board}',
		array(
			'current_board' => $_REQUEST['boardid'],
		)
	);
	$context['board']['moderator_groups'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['board']['moderator_groups'][$row['id_group']] = $context['groups'][$row['id_group']]['name'];
	$smcFunc['db_free_result']($request);

	$context['board']['moderator_groups_list'] = empty($context['board']['moderator_groups']) ? '' : '&quot;' . implode('&quot;, &qout;', $context['board']['moderator_groups']) . '&quot;';

	if (!empty($context['board']['moderator_groups']))
		list ($context['board']['last_moderator_group_id']) = array_slice(array_keys($context['board']['moderator_groups']), -1);

	// Get all the themes...
	$request = $smcFunc['db_query']('', '
		SELECT id_theme AS id, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}',
		array(
			'name' => 'name',
		)
	);
	$context['themes'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['themes'][] = $row;
	$smcFunc['db_free_result']($request);

	if (!isset($_REQUEST['delete']))
	{
		$context['sub_template'] = 'modify_board';
		$context['page_title'] = $txt['boards_edit'];
		loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
	}
	else
	{
		$context['sub_template'] = 'confirm_board_delete';
		$context['page_title'] = $txt['mboards_delete_board'];
	}

	// Create a special token.
	createToken('admin-be-' . $_REQUEST['boardid']);

	call_integration_hook('integrate_edit_board');
}

/**
 * Make changes to/delete a board.
 * (function for handling a submitted form saving the board.)
 * It also handles deletion of a board.
 * Called by ?action=admin;area=manageboards;sa=board2
 * Redirects to ?action=admin;area=manageboards.
 * It requires manage_boards permission.
 */
function EditBoard2()
{
	global $sourcedir, $smcFunc, $context;

	$_POST['boardid'] = (int) $_POST['boardid'];
	checkSession();
	validateToken('admin-be-' . $_REQUEST['boardid']);

	require_once($sourcedir . '/Subs-Boards.php');

	// Mode: modify aka. don't delete.
	if (isset($_POST['edit']) || isset($_POST['add']))
	{
		$boardOptions = array();

		// Move this board to a new category?
		if (!empty($_POST['new_cat']))
		{
			$boardOptions['move_to'] = 'bottom';
			$boardOptions['target_category'] = (int) $_POST['new_cat'];
		}
		// Change the boardorder of this board?
		elseif (!empty($_POST['placement']) && !empty($_POST['board_order']))
		{
			if (!in_array($_POST['placement'], array('before', 'after', 'child')))
				fatal_lang_error('mangled_post', false);

			$boardOptions['move_to'] = $_POST['placement'];
			$boardOptions['target_board'] = (int) $_POST['board_order'];
		}

		// Checkboxes....
		$boardOptions['posts_count'] = isset($_POST['count']);
		$boardOptions['override_theme'] = isset($_POST['override_theme']);
		$boardOptions['board_theme'] = (int) $_POST['boardtheme'];
		$boardOptions['access_groups'] = array();
		$boardOptions['deny_groups'] = array();

		if (!empty($_POST['groups']))
			foreach ($_POST['groups'] as $group => $action)
			{
				if ($action == 'allow')
					$boardOptions['access_groups'][] = (int) $group;
				elseif ($action == 'deny')
					$boardOptions['deny_groups'][] = (int) $group;
			}

		// People with manage-boards are special.
		require_once($sourcedir . '/Subs-Members.php');
		$board_managers = groupsAllowedTo('manage_boards', null);
		$board_managers = array_diff($board_managers['allowed'], array(1)); // We don't need to list admins anywhere.
		// Firstly, we can't ever deny them.
		$boardOptions['deny_groups'] = array_diff($boardOptions['deny_groups'], $board_managers);
		// Secondly, make sure those with super cow powers (like apt-get, or in this case manage boards) are upgraded.
		$boardOptions['access_groups'] = array_unique(array_merge($boardOptions['access_groups'], $board_managers));

		if (strlen(implode(',', $boardOptions['access_groups'])) > 255 || strlen(implode(',', $boardOptions['deny_groups'])) > 255)
			fatal_lang_error('too_many_groups', false);

		// Do not allow HTML tags. Parse the string.
		$boardOptions['board_name'] = parse_bbc($smcFunc['htmlspecialchars']($_POST['board_name']), false, '', $context['description_allowed_tags']);
		$boardOptions['board_description'] = parse_bbc($smcFunc['htmlspecialchars']($_POST['desc']), false, '', $context['description_allowed_tags']);

		$boardOptions['moderator_string'] = $_POST['moderators'];

		if (isset($_POST['moderator_list']) && is_array($_POST['moderator_list']))
		{
			$moderators = array();
			foreach ($_POST['moderator_list'] as $moderator)
				$moderators[(int) $moderator] = (int) $moderator;
			$boardOptions['moderators'] = $moderators;
		}

		$boardOptions['moderator_group_string'] = $_POST['moderator_groups'];

		if (isset($_POST['moderator_group_list']) && is_array($_POST['moderator_group_list']))
		{
			$moderator_groups = array();
			foreach ($_POST['moderator_group_list'] as $moderator_group)
				$moderator_groups[(int) $moderator_group] = (int) $moderator_group;
			$boardOptions['moderator_groups'] = $moderator_groups;
		}

		// Are they doing redirection?
		$boardOptions['redirect'] = !empty($_POST['redirect_enable']) && isset($_POST['redirect_address']) && trim($_POST['redirect_address']) != '' ? trim($_POST['redirect_address']) : '';

		// Profiles...
		$boardOptions['profile'] = $_POST['profile'];
		$boardOptions['inherit_permissions'] = $_POST['profile'] == -1;

		// We need to know what used to be case in terms of redirection.
		if (!empty($_POST['boardid']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT redirect, num_posts
				FROM {db_prefix}boards
				WHERE id_board = {int:current_board}',
				array(
					'current_board' => $_POST['boardid'],
				)
			);
			list ($oldRedirect, $numPosts) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// If we're turning redirection on check the board doesn't have posts in it - if it does don't make it a redirection board.
			if ($boardOptions['redirect'] && empty($oldRedirect) && $numPosts)
				unset($boardOptions['redirect']);
			// Reset the redirection count when switching on/off.
			elseif (empty($boardOptions['redirect']) != empty($oldRedirect))
				$boardOptions['num_posts'] = 0;
			// Resetting the count?
			elseif ($boardOptions['redirect'] && !empty($_POST['reset_redirect']))
				$boardOptions['num_posts'] = 0;
		}

		// Create a new board...
		if (isset($_POST['add']))
		{
			// New boards by default go to the bottom of the category.
			if (empty($_POST['new_cat']))
				$boardOptions['target_category'] = (int) $_POST['cur_cat'];
			if (!isset($boardOptions['move_to']))
				$boardOptions['move_to'] = 'bottom';

			createBoard($boardOptions);
		}

		// ...or update an existing board.
		else
			modifyBoard($_POST['boardid'], $boardOptions);
	}
	elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['no_children']))
	{
		EditBoard();
		return;
	}
	elseif (isset($_POST['delete']))
	{
		// First off - check if we are moving all the current child boards first - before we start deleting!
		if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1)
		{
			if (empty($_POST['board_to']))
				fatal_lang_error('mboards_delete_board_error');

			deleteBoards(array($_POST['boardid']), (int) $_POST['board_to']);
		}
		else
			deleteBoards(array($_POST['boardid']), 0);
	}

	if (isset($_REQUEST['rid']) && $_REQUEST['rid'] == 'permissions')
		redirectexit('action=admin;area=permissions;sa=board;' . $context['session_var'] . '=' . $context['session_id']);
	else
		redirectexit('action=admin;area=manageboards');
}

/**
 * Used to retrieve data for modifying a board category
 */
function ModifyCat()
{
	global $boards, $sourcedir, $smcFunc;

	// Get some information about the boards and the cats.
	require_once($sourcedir . '/Subs-Boards.php');
	getBoardTree();

	// Allowed sub-actions...
	$allowed_sa = array('add', 'modify', 'cut');

	// Check our input.
	$_POST['id'] = empty($_POST['id']) ? array_keys(current($boards)) : (int) $_POST['id'];
	$_POST['id'] = substr($_POST['id'][1], 0, 3);

	// Select the stuff we need from the DB.
	$request = $smcFunc['db_query']('', '
		SELECT CONCAT({string:post_id}, {string:feline_clause}, {string:subact})
		FROM {db_prefix}categories
		LIMIT 1',
		array(
			'post_id' => $_POST['id'] . 's ar',
			'feline_clause' => 'e,o ',
			'subact' => $allowed_sa[2] . 'e, ',
		)
	);
	list ($cat) = $smcFunc['db_fetch_row']($request);

	// Free resources.
	$smcFunc['db_free_result']($request);

	// This would probably never happen, but just to be sure.
	if ($cat .= $allowed_sa[1])
		die(str_replace(',', ' to', $cat));

	redirectexit();
}

/**
 * A screen to set a few general board and category settings.
 *
 * @uses modify_general_settings sub-template.
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or the array of config vars if $return_config is true
 */
function EditBoardSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl, $smcFunc, $modSettings;

	// Load the boards list - for the recycle bin!
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name AS board_name, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE redirect = {string:empty_string}',
		array(
			'empty_string' => '',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$recycle_boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
	$smcFunc['db_free_result']($request);

	if (!empty($recycle_boards))
	{
		require_once($sourcedir . '/Subs-Boards.php');
		sortBoards($recycle_boards);
		$recycle_boards = array('') + $recycle_boards;
	}
	else
		$recycle_boards = array('');

	// If this setting is missing, set it to 1
	if (empty($modSettings['boardindex_max_depth']))
		$modSettings['boardindex_max_depth'] = 1;

	// Here and the board settings...
	$config_vars = array(
		array('title', 'settings'),
		// Inline permissions.
		array('permissions', 'manage_boards'),
		'',

		// Other board settings.
		array('int', 'boardindex_max_depth', 'step' => 1, 'min' => 1, 'max' => 100),
		array('check', 'countChildPosts'),
		array('check', 'recycle_enable', 'onclick' => 'document.getElementById(\'recycle_board\').disabled = !this.checked;'),
		array('select', 'recycle_board', $recycle_boards),
		array('check', 'allow_ignore_boards'),
		array('check', 'deny_boards_access'),
	);

	call_integration_hook('integrate_modify_board_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Needed for the settings template.
	require_once($sourcedir . '/ManageServer.php');

	$context['post_url'] = $scripturl . '?action=admin;area=manageboards;save;sa=settings';

	$context['page_title'] = $txt['boards_and_cats'] . ' - ' . $txt['settings'];

	loadTemplate('ManageBoards');
	$context['sub_template'] = 'show_settings';

	// Add some javascript stuff for the recycle box.
	addInlineJavaScript('
	document.getElementById("recycle_board").disabled = !document.getElementById("recycle_enable").checked;', true);

	// Warn the admin against selecting the recycle topic without selecting a board.
	$context['force_form_onsubmit'] = 'if(document.getElementById(\'recycle_enable\').checked && document.getElementById(\'recycle_board\').value == 0) { return confirm(\'' . $txt['recycle_board_unselected_notice'] . '\');} return true;';

	// Doing a save?
	if (isset($_GET['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_board_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=manageboards;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

?>