<?php

/**
 * Moderation Center.
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
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Msg;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Entry point for the moderation center.
 *
 * @param bool $dont_call If true, doesn't call the function for the appropriate mod area
 */
function ModerationMain($dont_call = false)
{
	// Don't run this twice... and don't conflict with the admin bar.
	if (isset(Utils::$context['admin_area']))
		return;

	Utils::$context['can_moderate_boards'] = User::$me->mod_cache['bq'] != '0=1';
	Utils::$context['can_moderate_groups'] = User::$me->mod_cache['gq'] != '0=1';
	Utils::$context['can_moderate_approvals'] = Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']);
	Utils::$context['can_moderate_users'] = allowedTo('moderate_forum');

	// Everyone using this area must be allowed here!
	if (!Utils::$context['can_moderate_boards'] && !Utils::$context['can_moderate_groups'] && !Utils::$context['can_moderate_approvals'] && !Utils::$context['can_moderate_users'])
		isAllowedTo('access_mod_center');

	// Load the language, and the template.
	Lang::load('ModerationCenter');
	Theme::loadTemplate(false, 'admin');

	Utils::$context['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : array();
	Utils::$context['robot_no_index'] = true;

	// This is the menu structure - refer to Menu.php for the details.
	$moderation_areas = array(
		'main' => array(
			'title' => Lang::$txt['mc_main'],
			'areas' => array(
				'index' => array(
					'label' => Lang::$txt['moderation_center'],
					'function' => '\\SMF\\Actions\\Moderation\\Home::call',
					'icon' => 'administration',
				),
				'settings' => array(
					'label' => Lang::$txt['mc_settings'],
					'function' => 'ModerationSettings',
					'icon' => 'features',
				),
				'modlogoff' => array(
					'label' => Lang::$txt['mc_logoff'],
					'function' => '\\SMF\\Actions\\Moderation\\EndSession::call',
					'enabled' => empty(Config::$modSettings['securityDisable_moderate']),
					'icon' => 'exit',
				),
				'notice' => array(
					'function' => '\\SMF\\Actions\\Moderation\\ShowNotice::call',
					'select' => 'index'
				),
			),
		),
		'logs' => array(
			'title' => Lang::$txt['mc_logs'],
			'areas' => array(
				'modlog' => array(
					'label' => Lang::$txt['modlog_view'],
					'enabled' => !empty(Config::$modSettings['modlog_enabled']) && Utils::$context['can_moderate_boards'],
					'file' => 'Modlog.php',
					'function' => 'ViewModlog',
					'icon' => 'logs',
				),
				'warnings' => array(
					'label' => Lang::$txt['mc_warnings'],
					'enabled' => Config::$modSettings['warning_settings'][0] == 1 && allowedTo(array('issue_warning', 'view_warning_any')),
					'function' => 'ViewWarnings',
					'icon' => 'warning',
					'subsections' => array(
						'log' => array(
							'label' => Lang::$txt['mc_warning_log'],
							'permission' => array('view_warning_any', 'moderate_forum'),
						),
						'templates' => array(
							'label' => Lang::$txt['mc_warning_templates'],
							'permission' => 'issue_warning',
						),
					),
				),
			),
		),
		'posts' => array(
			'title' => Lang::$txt['mc_posts'],
			'enabled' => Utils::$context['can_moderate_boards'] || Utils::$context['can_moderate_approvals'],
			'areas' => array(
				'postmod' => array(
					'label' => Lang::$txt['mc_unapproved_posts'],
					'enabled' => Utils::$context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'posts',
					'custom_url' => Config::$scripturl . '?action=moderate;area=postmod',
					'subsections' => array(
						'posts' => array(
							'label' => Lang::$txt['mc_unapproved_replies'],
						),
						'topics' => array(
							'label' => Lang::$txt['mc_unapproved_topics'],
						),
					),
				),
				'attachmod' => array(
					'label' => Lang::$txt['mc_unapproved_attachments'],
					'enabled' => Utils::$context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'post_moderation_attach',
					'custom_url' => Config::$scripturl . '?action=moderate;area=attachmod;sa=attachments',
				),
				'reportedposts' => array(
					'label' => Lang::$txt['mc_reported_posts'],
					'enabled' => Utils::$context['can_moderate_boards'],
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'reports',
					'subsections' => array(
						'show' => array(
							'label' => Lang::$txt['mc_reportedp_active'],
						),
						'closed' => array(
							'label' => Lang::$txt['mc_reportedp_closed'],
						),
					),
				),
			),
		),
		'groups' => array(
			'title' => Lang::$txt['mc_groups'],
			'enabled' => Utils::$context['can_moderate_groups'],
			'areas' => array(
				'groups' => array(
					'label' => Lang::$txt['mc_group_requests'],
					'file' => 'Actions/Groups.php',
					'function' => 'Groups',
					'icon' => 'members_request',
					'custom_url' => Config::$scripturl . '?action=moderate;area=groups;sa=requests',
				),
				'viewgroups' => array(
					'label' => Lang::$txt['mc_view_groups'],
					'file' => 'Actions/Groups.php',
					'function' => 'Groups',
					'icon' => 'membergroups',
				),
			),
		),
		'members' => array(
			'title' => Lang::$txt['mc_members'],
			'enabled' => Utils::$context['can_moderate_users'] || (Config::$modSettings['warning_settings'][0] == 1 && Utils::$context['can_moderate_boards']),
			'areas' => array(
				'userwatch' => array(
					'label' => Lang::$txt['mc_watched_users_title'],
					'enabled' => Config::$modSettings['warning_settings'][0] == 1 && Utils::$context['can_moderate_boards'],
					'function' => 'ViewWatchedUsers',
					'icon' => 'members_watched',
					'subsections' => array(
						'member' => array(
							'label' => Lang::$txt['mc_watched_users_member'],
						),
						'post' => array(
							'label' => Lang::$txt['mc_watched_users_post'],
						),
					),
				),
				'reportedmembers' => array(
					'label' => Lang::$txt['mc_reported_members_title'],
					'enabled' => Utils::$context['can_moderate_users'],
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'members_watched',
					'subsections' => array(
						'open' => array(
							'label' => Lang::$txt['mc_reportedp_active'],
						),
						'closed' => array(
							'label' => Lang::$txt['mc_reportedp_closed'],
						),
					),
				),
			),
		)
	);

	// Make sure the administrator has a valid session...
	validateSession('moderate');

	// I don't know where we're going - I don't know where we've been...
	$menuOptions = array(
		'action' => 'moderate',
		'disable_url_session_check' => true,
	);

	$menu = new Menu($moderation_areas, $menuOptions);

	unset($moderation_areas);

	// We got something - didn't we? DIDN'T WE!
	if (empty($menu->include_data))
		fatal_lang_error('no_access', false);

	// Retain the ID information in case required by a subaction.
	Utils::$context['moderation_menu_id'] = $menu->id;
	Utils::$context['moderation_menu_name'] = $menu->name;

	// @todo: html in here is not good
	$menu->tab_data = array(
		'title' => Lang::$txt['moderation_center'],
		'help' => '',
		'description' => '
			<strong>' . Lang::$txt['hello_guest'] . ' ' . User::$me->name . '!</strong>
			<br><br>
			' . Lang::$txt['mc_description']
	);

	// What a pleasant shortcut - even tho we're not *really* on the admin screen who cares...
	Utils::$context['admin_area'] = $menu->current_area;

	// Build the link tree.
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=moderate',
		'name' => Lang::$txt['moderation_center'],
	);

	if (isset($menu->current_area) && $menu->current_area != 'index')
	{
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=moderate;area=' . $menu->current_area,
			'name' => $menu->include_data['label'],
		);
	}

	if (!empty($menu->current_subsection) && $menu->include_data['subsections'][$menu->current_subsection]['label'] != $menu->include_data['label'])
	{
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=moderate;area=' . $menu->current_area . ';sa=' . $menu->current_subsection,
			'name' => $menu->include_data['subsections'][$menu->current_subsection]['label'],
		);
	}

	// Now - finally - the bit before the encore - the main performance of course!
	if (!$dont_call)
	{
		if (isset($menu->include_data['file']))
			require_once(Config::$sourcedir . '/' . $menu->include_data['file']);

		call_helper($menu->include_data['function']);
	}
}

/**
 * Act as an entrace for all group related activity.
 *
 * @todo As for most things in this file, this needs to be moved somewhere appropriate?
 */
function ModerateGroups()
{
	// You need to be allowed to moderate groups...
	if (User::$me->mod_cache['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Load the group templates.
	Theme::loadTemplate('ModerationCenter');

	// Setup the subactions...
	$subActions = array(
		'requests' => 'GroupRequests',
		'view' => 'ViewGroups',
	);

	if (!isset($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'view';
	Utils::$context['sub_action'] = $_GET['sa'];

	// Call the relevant function.
	call_helper($subActions[Utils::$context['sub_action']]);
}

/**
 * View watched users.
 */
function ViewWatchedUsers()
{
	// Some important context!
	Utils::$context['page_title'] = Lang::$txt['mc_watched_users_title'];
	Utils::$context['view_posts'] = isset($_GET['sa']) && $_GET['sa'] == 'post';
	Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

	Theme::loadTemplate('ModerationCenter');

	// Get some key settings!
	Config::$modSettings['warning_watch'] = empty(Config::$modSettings['warning_watch']) ? 1 : Config::$modSettings['warning_watch'];

	// Put some pretty tabs on cause we're gonna be doing hot stuff here...
	Menu::$loaded['moderate']->tab_data = array(
		'title' => Lang::$txt['mc_watched_users_title'],
		'help' => '',
		'description' => Lang::$txt['mc_watched_users_desc'],
	);

	// First off - are we deleting?
	if (!empty($_REQUEST['delete']))
	{
		checkSession(!is_array($_REQUEST['delete']) ? 'get' : 'post');

		$toDelete = array();
		if (!is_array($_REQUEST['delete']))
			$toDelete[] = (int) $_REQUEST['delete'];
		else
			foreach ($_REQUEST['delete'] as $did)
				$toDelete[] = (int) $did;

		if (!empty($toDelete))
		{
			// If they don't have permission we'll let it error - either way no chance of a security slip here!
			foreach ($toDelete as $did)
				Msg::remove($did);
		}
	}

	// Start preparing the list by grabbing relevant permissions.
	if (!Utils::$context['view_posts'])
	{
		$approve_query = '';
		$delete_boards = array();
	}
	else
	{
		// Still obey permissions!
		$approve_boards = boardsAllowedTo('approve_posts');
		$delete_boards = boardsAllowedTo('delete_any');

		if ($approve_boards == array(0))
			$approve_query = '';
		elseif (!empty($approve_boards))
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		// Nada, zip, etc...
		else
			$approve_query = ' AND 1=0';
	}

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'watch_user_list',
		'title' => Lang::$txt['mc_watched_users_title'] . ' - ' . (Utils::$context['view_posts'] ? Lang::$txt['mc_watched_users_post'] : Lang::$txt['mc_watched_users_member']),
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Utils::$context['view_posts'] ? Lang::$txt['mc_watched_users_no_posts'] : Lang::$txt['mc_watched_users_none'],
		'base_href' => Config::$scripturl . '?action=moderate;area=userwatch;sa=' . (Utils::$context['view_posts'] ? 'post' : 'member'),
		'default_sort_col' => Utils::$context['view_posts'] ? '' : 'member',
		'get_items' => array(
			'function' => Utils::$context['view_posts'] ? 'list_getWatchedUserPosts' : 'list_getWatchedUsers',
			'params' => array(
				$approve_query,
				$delete_boards,
			),
		),
		'get_count' => array(
			'function' => Utils::$context['view_posts'] ? 'list_getWatchedUserPostsCount' : 'list_getWatchedUserCount',
			'params' => array(
				$approve_query,
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'member' => array(
				'header' => array(
					'value' => Lang::$txt['mc_watched_users_member'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id' => false,
							'name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'real_name',
					'reverse' => 'real_name DESC',
				),
			),
			'warning' => array(
				'header' => array(
					'value' => Lang::$txt['mc_watched_users_warning'],
				),
				'data' => array(
					'function' => function($member)
					{
						return allowedTo('issue_warning') ? '<a href="' . Config::$scripturl . '?action=profile;area=issuewarning;u=' . $member['id'] . '">' . $member['warning'] . '%</a>' : $member['warning'] . '%';
					},
				),
				'sort' => array(
					'default' => 'warning',
					'reverse' => 'warning DESC',
				),
			),
			'posts' => array(
				'header' => array(
					'value' => Lang::$txt['posts'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?action=profile;u=%1$d;area=showposts;sa=messages">%2$s</a>',
						'params' => array(
							'id' => false,
							'posts' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'posts',
					'reverse' => 'posts DESC',
				),
			),
			'last_login' => array(
				'header' => array(
					'value' => Lang::$txt['mc_watched_users_last_login'],
				),
				'data' => array(
					'db' => 'last_login',
				),
				'sort' => array(
					'default' => 'last_login',
					'reverse' => 'last_login DESC',
				),
			),
			'last_post' => array(
				'header' => array(
					'value' => Lang::$txt['mc_watched_users_last_post'],
				),
				'data' => array(
					'function' => function($member)
					{
						if ($member['last_post_id'])
							return '<a href="' . Config::$scripturl . '?msg=' . $member['last_post_id'] . '">' . $member['last_post'] . '</a>';
						else
							return $member['last_post'];
					},
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . '?action=moderate;area=userwatch;sa=post',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				Utils::$context['session_var'] => Utils::$context['session_id'],
			),
		),
		'additional_rows' => array(
			Utils::$context['view_posts'] ?
				array(
					'position' => 'bottom_of_list',
					'value' => '
					<input type="submit" name="delete_selected" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button">',
					'class' => 'floatright',
				) : array(),
		),
	);

	// If this is being viewed by posts we actually change the columns to call a template each time.
	if (Utils::$context['view_posts'])
	{
		$listOptions['columns'] = array(
			'posts' => array(
				'data' => array(
					'function' => function($post)
					{
						return template_user_watch_post_callback($post);
					},
					'class' => 'unique'
				),
			),
		);
	}

	// Create the watched user list.
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'watch_user_list';
}

/**
 * Callback for SMF\ItemList().
 *
 * @param string $approve_query Not used here
 * @return int The number of users on the watch list
 */
function list_getWatchedUserCount($approve_query)
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}',
		array(
			'warning_watch' => Config::$modSettings['warning_watch'],
		)
	);
	list ($totalMembers) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $totalMembers;
}

/**
 * Callback for SMF\ItemList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort things
 * @param string $approve_query A query for approving things. Not used here.
 * @param string $dummy Not used here.
 */
function list_getWatchedUsers($start, $items_per_page, $sort, $approve_query, $dummy)
{
	$request = Db::$db->query('', '
		SELECT id_member, real_name, last_login, posts, warning
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'warning_watch' => Config::$modSettings['warning_watch'],
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		)
	);
	$watched_users = array();
	$members = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$watched_users[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'last_login' => $row['last_login'] ? timeformat($row['last_login']) : Lang::$txt['never'],
			'last_post' => Lang::$txt['not_applicable'],
			'last_post_id' => 0,
			'warning' => $row['warning'],
			'posts' => $row['posts'],
		);
		$members[] = $row['id_member'];
	}
	Db::$db->free_result($request);

	if (!empty($members))
	{
		// First get the latest messages from these users.
		$request = Db::$db->query('', '
			SELECT m.id_member, MAX(m.id_msg) AS last_post_id
			FROM {db_prefix}messages AS m' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
			WHERE {query_see_message_board}
				AND m.id_member IN ({array_int:member_list})' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		);
		$latest_posts = array();
		while ($row = Db::$db->fetch_assoc($request))
			$latest_posts[$row['id_member']] = $row['last_post_id'];

		if (!empty($latest_posts))
		{
			// Now get the time those messages were posted.
			$request = Db::$db->query('', '
				SELECT id_member, poster_time
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:message_list})',
				array(
					'message_list' => $latest_posts,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$watched_users[$row['id_member']]['last_post'] = timeformat($row['poster_time']);
				$watched_users[$row['id_member']]['last_post_id'] = $latest_posts[$row['id_member']];
			}

			Db::$db->free_result($request);
		}

		$request = Db::$db->query('', '
			SELECT MAX(m.poster_time) AS last_post, MAX(m.id_msg) AS last_post_id, m.id_member
			FROM {db_prefix}messages AS m
			WHERE {query_see_message_board}
				AND m.id_member IN ({array_int:member_list})' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$watched_users[$row['id_member']]['last_post'] = timeformat($row['last_post']);
			$watched_users[$row['id_member']]['last_post_id'] = $row['last_post_id'];
		}
		Db::$db->free_result($request);
	}

	return $watched_users;
}

/**
 * Callback for SMF\ItemList().
 *
 * @param string $approve_query A query to pull only approved items
 * @return int The total number of posts by watched users
 */
function list_getWatchedUserPostsCount($approve_query)
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning >= {int:warning_watch}
			AND {query_see_board}
			' . $approve_query,
		array(
			'warning_watch' => Config::$modSettings['warning_watch'],
		)
	);
	list ($totalMemberPosts) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $totalMemberPosts;
}

/**
 * Callback for SMF\ItemList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results (not used here)
 * @param string $approve_query A query to only pull approved items
 * @param int[] $delete_boards An array containing the IDs of boards we can delete posts in
 * @return array An array of info about posts by watched users
 */
function list_getWatchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards)
{
	$request = Db::$db->query('', '
		SELECT m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
			m.approved, mem.real_name, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning >= {int:warning_watch}
			AND {query_see_board}
			' . $approve_query . '
		ORDER BY m.id_msg DESC
		LIMIT {int:start}, {int:max}',
		array(
			'warning_watch' => Config::$modSettings['warning_watch'],
			'start' => $start,
			'max' => $items_per_page,
		)
	);
	$member_posts = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$row['subject'] = Lang::censorText($row['subject']);
		$row['body'] = Lang::censorText($row['body']);

		$member_posts[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'author_link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'subject' => $row['subject'],
			'body' => BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']),
			'poster_time' => timeformat($row['poster_time']),
			'approved' => $row['approved'],
			'can_delete' => $delete_boards == array(0) || in_array($row['id_board'], $delete_boards),
		);
	}
	Db::$db->free_result($request);

	return $member_posts;
}

/**
 * Entry point for viewing warning related stuff.
 */
function ViewWarnings()
{
	$subActions = array(
		'log' => array('ViewWarningLog', array('view_warning_any', 'moderate_forum')),
		'templates' => array('ViewWarningTemplates', 'issue_warning'),
		'templateedit' => array('ModifyWarningTemplate', 'issue_warning'),
	);

	call_integration_hook('integrate_warning_log_actions', array(&$subActions));

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && (empty($subActions[$_REQUEST['sa']][1]) || allowedTo($subActions[$_REQUEST['sa']][1])) ? $_REQUEST['sa'] : '';

	// In theory, 'log' is the default subaction. But if this user can't view the log, more work is needed.
	if (empty($_REQUEST['sa']))
	{
		foreach ($subActions as $sa => $subAction)
		{
			if (empty($subAction[1]) || allowedTo($subAction[1]))
			{
				// If they can view the log, we can proceed as usual.
				if ($sa === 'log')
					$_REQUEST['sa'] = $sa;

				// Otherwise, redirect them to the first allowed subaction.
				else
					redirectexit('action=moderate;area=warnings;sa=' . $sa);
			}
		}

		// This shouldn't happen, but just in case...
		if (empty($_REQUEST['sa']))
			redirectexit('action=moderate;area=index');
	}

	// Some of this stuff is overseas, so to speak.
	Theme::loadTemplate('ModerationCenter');
	Lang::load('Profile');

	// Setup the admin tabs.
	Menu::$loaded['moderate']->tab_data = array(
		'title' => Lang::$txt['mc_warnings'],
		'description' => Lang::$txt['mc_warnings_description'],
	);

	// Call the right function.
	call_helper($subActions[$_REQUEST['sa']][0]);
}

/**
 * Simply put, look at the warning log!
 */
function ViewWarningLog()
{
	// Setup context as always.
	Utils::$context['page_title'] = Lang::$txt['mc_warning_log_title'];

	Lang::load('Modlog');

	// If we're coming from a search, get the variables.
	if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
	{
		$search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
		$search_params = Utils::jsonDecode($search_params, true);
	}

	// This array houses all the valid search types.
	$searchTypes = array(
		'member' => array('sql' => 'member_name_col', 'label' => Lang::$txt['profile_warning_previous_issued']),
		'recipient' => array('sql' => 'recipient_name', 'label' => Lang::$txt['mc_warnings_recipient']),
	);

	// Do the column stuff!
	$sort_types = array(
		'member' => 'member_name_col',
		'recipient' => 'recipient_name',
	);

	// Setup the direction stuff...
	Utils::$context['order'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'member';

	if (!isset($search_params['string']) || (!empty($_REQUEST['search']) && $search_params['string'] != $_REQUEST['search']))
		$search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
	else
		$search_params_string = $search_params['string'];

	if (isset($_REQUEST['search_type']) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
		$search_params_type = isset($_REQUEST['search_type']) && isset($searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[Utils::$context['order']]) ? Utils::$context['order'] : 'member');
	else
		$search_params_type = $search_params['type'];

	$search_params = array(
		'string' => $search_params_string,
		'type' => $search_params_type,
	);

	Utils::$context['url_start'] = '?action=moderate;area=warnings;sa=log;sort=' . Utils::$context['order'];

	// Setup the search context.
	Utils::$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(Utils::jsonEncode($search_params));
	Utils::$context['search'] = array(
		'string' => $search_params['string'],
		'type' => $search_params['type'],
		'label' => $searchTypes[$search_params_type]['label'],
	);

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'warning_list',
		'title' => Lang::$txt['mc_warning_log_title'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['mc_warnings_none'],
		'base_href' => Config::$scripturl . '?action=moderate;area=warnings;sa=log;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getWarnings',
		),
		'get_count' => array(
			'function' => 'list_getWarningCount',
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'issuer' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_issued'],
				),
				'data' => array(
					'db' => 'issuer_link',
				),
				'sort' => array(
					'default' => 'member_name_col',
					'reverse' => 'member_name_col DESC',
				),
			),
			'recipient' => array(
				'header' => array(
					'value' => Lang::$txt['mc_warnings_recipient'],
				),
				'data' => array(
					'db' => 'recipient_link',
				),
				'sort' => array(
					'default' => 'recipient_name',
					'reverse' => 'recipient_name DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_time'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_reason'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						$output = '
							<div class="floatleft">
								' . $rowData['reason'] . '
							</div>';

						if (!empty($rowData['id_notice']))
							$output .= '
								&nbsp;<a href="' . Config::$scripturl . '?action=moderate;area=notice;nid=' . $rowData['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" rel="noopener" title="' . Lang::$txt['profile_warning_previous_notice'] . '"><span class="main_icons filter centericon"></span></a>';
						return $output;
					},
				),
			),
			'points' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_level'],
				),
				'data' => array(
					'db' => 'counter',
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . Utils::$context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				Utils::$context['session_var'] => Utils::$context['session_id'],
				'params' => false
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					' . Lang::$txt['modlog_search'] . ':
					<input type="text" name="search" size="18" value="' . Utils::htmlspecialchars(Utils::$context['search']['string']) . '">
					<input type="submit" name="is_search" value="' . Lang::$txt['modlog_go'] . '" class="button">',
				'class' => 'floatright',
			),
		),
	);

	// Create the watched user list.
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'warning_list';
}

/**
 * Callback for SMF\ItemList().
 *
 * @return int The total number of warnings that have been issued
 */
function list_getWarningCount()
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:warning}',
		array(
			'warning' => 'warning',
		)
	);
	list ($totalWarns) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $totalWarns;
}

/**
 * Callback for SMF\ItemList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of data about warning log entries
 */
function list_getWarnings($start, $items_per_page, $sort)
{
	$request = Db::$db->query('', '
		SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name_col,
			COALESCE(mem2.id_member, 0) AS id_recipient, COALESCE(mem2.real_name, lc.recipient_name) AS recipient_name,
			lc.log_time, lc.body, lc.id_notice, lc.counter
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = lc.id_recipient)
		WHERE lc.comment_type = {string:warning}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'warning' => 'warning',
			'start' => $start,
			'max' => $items_per_page,
			'sort' => $sort,
		)
	);
	$warnings = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$warnings[] = array(
			'issuer_link' => $row['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name_col'] . '</a>') : $row['member_name_col'],
			'recipient_link' => $row['id_recipient'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_recipient'] . '">' . $row['recipient_name'] . '</a>') : $row['recipient_name'],
			'time' => timeformat($row['log_time']),
			'reason' => $row['body'],
			'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
			'id_notice' => $row['id_notice'],
		);
	}
	Db::$db->free_result($request);

	return $warnings;
}

/**
 * Load all the warning templates.
 */
function ViewWarningTemplates()
{
	// Submitting a new one?
	if (isset($_POST['add']))
		return ModifyWarningTemplate();
	elseif (isset($_POST['delete']) && !empty($_POST['deltpl']))
	{
		checkSession();
		validateToken('mod-wt');

		// Log the actions.
		$request = Db::$db->query('', '
			SELECT recipient_name
			FROM {db_prefix}log_comments
			WHERE id_comment IN ({array_int:delete_ids})
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			array(
				'delete_ids' => $_POST['deltpl'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => User::$me->id,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			logAction('delete_warn_template', array('template' => $row['recipient_name']));
		Db::$db->free_result($request);

		// Do the deletes.
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment IN ({array_int:delete_ids})
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			array(
				'delete_ids' => $_POST['deltpl'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => User::$me->id,
			)
		);
	}

	// Setup context as always.
	Utils::$context['page_title'] = Lang::$txt['mc_warning_templates_title'];

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'warning_template_list',
		'title' => Lang::$txt['mc_warning_templates_title'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['mc_warning_templates_none'],
		'base_href' => Config::$scripturl . '?action=moderate;area=warnings;sa=templates;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
		'default_sort_col' => 'title',
		'get_items' => array(
			'function' => 'list_getWarningTemplates',
		),
		'get_count' => array(
			'function' => 'list_getWarningTemplateCount',
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'title' => array(
				'header' => array(
					'value' => Lang::$txt['mc_warning_templates_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?action=moderate;area=warnings;sa=templateedit;tid=%1$d">%2$s</a>',
						'params' => array(
							'id_comment' => false,
							'title' => false,
							'body' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'template_title',
					'reverse' => 'template_title DESC',
				),
			),
			'creator' => array(
				'header' => array(
					'value' => Lang::$txt['mc_warning_templates_creator'],
				),
				'data' => array(
					'db' => 'creator',
				),
				'sort' => array(
					'default' => 'creator_name',
					'reverse' => 'creator_name DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => Lang::$txt['mc_warning_templates_time'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="deltpl[]" value="' . $rowData['id_comment'] . '">';
					},
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . '?action=moderate;area=warnings;sa=templates',
			'token' => 'mod-wt',
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '&nbsp;<input type="submit" name="delete" value="' . Lang::$txt['mc_warning_template_delete'] . '" data-confirm="' . Lang::$txt['mc_warning_template_delete_confirm'] . '" class="button you_sure">',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="add" value="' . Lang::$txt['mc_warning_template_add'] . '" class="button">',
			),
		),
	);

	// Create the watched user list.
	createToken('mod-wt');
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'warning_template_list';
}

/**
 * Callback for SMF\ItemList().
 *
 * @return int The total number of warning templates
 */
function list_getWarningTemplateCount()
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:warntpl}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})',
		array(
			'warntpl' => 'warntpl',
			'generic' => 0,
			'current_member' => User::$me->id,
		)
	);
	list ($totalWarns) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $totalWarns;
}

/**
 * Callback for SMF\ItemList().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of info about the available warning templates
 */
function list_getWarningTemplates($start, $items_per_page, $sort)
{

	$request = Db::$db->query('', '
		SELECT lc.id_comment, COALESCE(mem.id_member, 0) AS id_member,
			COALESCE(mem.real_name, lc.member_name) AS creator_name, recipient_name AS template_title,
			lc.log_time, lc.body
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.comment_type = {string:warntpl}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'warntpl' => 'warntpl',
			'generic' => 0,
			'current_member' => User::$me->id,
		)
	);
	$templates = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$templates[] = array(
			'id_comment' => $row['id_comment'],
			'creator' => $row['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['creator_name'] . '</a>') : $row['creator_name'],
			'time' => timeformat($row['log_time']),
			'title' => $row['template_title'],
			'body' => Utils::htmlspecialchars($row['body']),
		);
	}
	Db::$db->free_result($request);

	return $templates;
}

/**
 * Edit a warning template.
 */
function ModifyWarningTemplate()
{
	Utils::$context['id_template'] = isset($_REQUEST['tid']) ? (int) $_REQUEST['tid'] : 0;
	Utils::$context['is_edit'] = Utils::$context['id_template'];

	// Standard template things.
	Utils::$context['page_title'] = Utils::$context['is_edit'] ? Lang::$txt['mc_warning_template_modify'] : Lang::$txt['mc_warning_template_add'];
	Utils::$context['sub_template'] = 'warn_template';
	Menu::$loaded['moderate']['current_subsection'] = 'templates';

	// Defaults.
	Utils::$context['template_data'] = array(
		'title' => '',
		'body' => Lang::$txt['mc_warning_template_body_default'],
		'personal' => false,
		'can_edit_personal' => true,
	);

	// If it's an edit load it.
	if (Utils::$context['is_edit'])
	{
		$request = Db::$db->query('', '
			SELECT id_member, id_recipient, recipient_name AS template_title, body
			FROM {db_prefix}log_comments
			WHERE id_comment = {int:id}
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			array(
				'id' => Utils::$context['id_template'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => User::$me->id,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['template_data'] = array(
				'title' => $row['template_title'],
				'body' => Utils::htmlspecialchars($row['body']),
				'personal' => $row['id_recipient'],
				'can_edit_personal' => $row['id_member'] == User::$me->id,
			);
		}
		Db::$db->free_result($request);
	}

	// Wait, we are saving?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('mod-wt');

		// Bit of cleaning!
		$_POST['template_body'] = trim($_POST['template_body']);
		$_POST['template_title'] = trim($_POST['template_title']);

		// Need something in both boxes.
		if (!empty($_POST['template_body']) && !empty($_POST['template_title']))
		{
			// Safety first.
			$_POST['template_title'] = Utils::htmlspecialchars($_POST['template_title']);

			// Clean up BBC.
			Msg::preparsecode($_POST['template_body']);
			// But put line breaks back!
			$_POST['template_body'] = strtr($_POST['template_body'], array('<br>' => "\n"));

			// Is this personal?
			$recipient_id = !empty($_POST['make_personal']) ? User::$me->id : 0;

			// If we are this far it's save time.
			if (Utils::$context['is_edit'])
			{
				// Simple update...
				Db::$db->query('', '
					UPDATE {db_prefix}log_comments
					SET id_recipient = {int:personal}, recipient_name = {string:title}, body = {string:body}
					WHERE id_comment = {int:id}
						AND comment_type = {string:warntpl}
						AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})'.
						($recipient_id ? ' AND id_member = {int:current_member}' : ''),
					array(
						'personal' => $recipient_id,
						'title' => $_POST['template_title'],
						'body' => $_POST['template_body'],
						'id' => Utils::$context['id_template'],
						'warntpl' => 'warntpl',
						'generic' => 0,
						'current_member' => User::$me->id,
					)
				);

				// If it wasn't visible and now is they've effectively added it.
				if (Utils::$context['template_data']['personal'] && !$recipient_id)
					logAction('add_warn_template', array('template' => $_POST['template_title']));
				// Conversely if they made it personal it's a delete.
				elseif (!Utils::$context['template_data']['personal'] && $recipient_id)
					logAction('delete_warn_template', array('template' => $_POST['template_title']));
				// Otherwise just an edit.
				else
					logAction('modify_warn_template', array('template' => $_POST['template_title']));
			}
			else
			{
				Db::$db->insert('',
					'{db_prefix}log_comments',
					array(
						'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
						'recipient_name' => 'string-255', 'body' => 'string-65535', 'log_time' => 'int',
					),
					array(
						User::$me->id, User::$me->name, 'warntpl', $recipient_id,
						$_POST['template_title'], $_POST['template_body'], time(),
					),
					array('id_comment')
				);

				logAction('add_warn_template', array('template' => $_POST['template_title']));
			}

			// Get out of town...
			redirectexit('action=moderate;area=warnings;sa=templates');
		}
		else
		{
			Utils::$context['warning_errors'] = array();
			Utils::$context['template_data']['title'] = !empty($_POST['template_title']) ? $_POST['template_title'] : '';
			Utils::$context['template_data']['body'] = !empty($_POST['template_body']) ? $_POST['template_body'] : Lang::$txt['mc_warning_template_body_default'];
			Utils::$context['template_data']['personal'] = !empty($_POST['make_personal']);

			if (empty($_POST['template_title']))
				Utils::$context['warning_errors'][] = Lang::$txt['mc_warning_template_error_no_title'];
			if (empty($_POST['template_body']))
				Utils::$context['warning_errors'][] = Lang::$txt['mc_warning_template_error_no_body'];
		}
	}

	createToken('mod-wt');
}

/**
 * Change moderation preferences.
 */
function ModerationSettings()
{
	// Some useful context stuff.
	Theme::loadTemplate('ModerationCenter');
	Utils::$context['page_title'] = Lang::$txt['mc_settings'];
	Utils::$context['sub_template'] = 'moderation_settings';
	Menu::$loaded['moderate']->tab_data = array(
		'title' => Lang::$txt['mc_prefs_title'],
		'help' => '',
		'description' => Lang::$txt['mc_prefs_desc']
	);

	$pref_binary = 5;

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('mod-set');

		/* Current format of mod_prefs is:
			x|ABCD|yyy

			WHERE:
				x = Show report count on forum header.
				ABCD = Block indexes to show on moderation main page.
				yyy = Integer with the following bit status:
					- yyy & 4 = Notify about posts awaiting approval.
		*/

		// Now check other options!
		$pref_binary = 0;

		// Put it all together.
		$mod_prefs = '0||' . $pref_binary;
		User::updateMemberData(User::$me->id, array('mod_prefs' => $mod_prefs));
	}

	// What blocks does the user currently have selected?
	Utils::$context['mod_settings'] = array(
	);

	createToken('mod-set');
}

?>