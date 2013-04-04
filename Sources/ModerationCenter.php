<?php

/**
 * Moderation Center.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Entry point for the moderation center.
 *
 * @param bool $dont_call = false
 */
function ModerationMain($dont_call = false)
{
	global $txt, $context, $scripturl, $sc, $modSettings, $user_info, $settings, $sourcedir, $options, $smcFunc;

	// Don't run this twice... and don't conflict with the admin bar.
	if (isset($context['admin_area']))
		return;

	$context['can_moderate_boards'] = $user_info['mod_cache']['bq'] != '0=1';
	$context['can_moderate_groups'] = $user_info['mod_cache']['gq'] != '0=1';
	$context['can_moderate_approvals'] = $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']);

	// Everyone using this area must be allowed here!
	if (!$context['can_moderate_boards'] && !$context['can_moderate_groups'] && !$context['can_moderate_approvals'])
		isAllowedTo('access_mod_center');

	// We're gonna want a menu of some kind.
	require_once($sourcedir . '/Subs-Menu.php');

	// Load the language, and the template.
	loadLanguage('ModerationCenter');
	loadTemplate(false, 'admin');

	$context['admin_preferences'] = !empty($options['admin_preferences']) ? unserialize($options['admin_preferences']) : array();
	$context['robot_no_index'] = true;

	// This is the menu structure - refer to Subs-Menu.php for the details.
	$moderation_areas = array(
		'main' => array(
			'title' => $txt['mc_main'],
			'areas' => array(
				'index' => array(
					'label' => $txt['moderation_center'],
					'function' => 'ModerationHome',
				),
				'settings' => array(
					'label' => $txt['mc_settings'],
					'function' => 'ModerationSettings',
				),
				'modlogoff' => array(
					'label' => $txt['mc_logoff'],
					'function' => 'ModEndSession',
					'enabled' => empty($modSettings['securityDisable_moderate']),
				),
				'notice' => array(
					'file' => 'ModerationCenter.php',
					'function' => 'ShowNotice',
					'select' => 'index'
				),
			),
		),
		'logs' => array(
			'title' => $txt['mc_logs'],
			'areas' => array(
				'modlog' => array(
					'label' => $txt['modlog_view'],
					'enabled' => !empty($modSettings['modlog_enabled']) && $context['can_moderate_boards'],
					'file' => 'Modlog.php',
					'function' => 'ViewModlog',
				),
				'warnings' => array(
					'label' => $txt['mc_warnings'],
					'enabled' => in_array('w', $context['admin_features']) && $modSettings['warning_settings'][0] == 1 && $context['can_moderate_boards'],
					'function' => 'ViewWarnings',
					'subsections' => array(
						'log' => array($txt['mc_warning_log']),
						'templates' => array($txt['mc_warning_templates'], 'issue_warning'),
					),
				),
			),
		),
		'posts' => array(
			'title' => $txt['mc_posts'],
			'enabled' => $context['can_moderate_boards'] || $context['can_moderate_approvals'],
			'areas' => array(
				'postmod' => array(
					'label' => $txt['mc_unapproved_posts'],
					'enabled' => $context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'custom_url' => $scripturl . '?action=moderate;area=postmod',
					'subsections' => array(
						'posts' => array($txt['mc_unapproved_replies']),
						'topics' => array($txt['mc_unapproved_topics']),
					),
				),
				'attachmod' => array(
					'label' => $txt['mc_unapproved_attachments'],
					'enabled' => $context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'custom_url' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
				),
				'reports' => array(
					'label' => $txt['mc_reported_posts'],
					'enabled' => $context['can_moderate_boards'],
					'file' => 'ModerationCenter.php',
					'function' => 'ReportedPosts',
					'subsections' => array(
						'open' => array($txt['mc_reportedp_active']),
						'closed' => array($txt['mc_reportedp_closed']),
					),
				),
			),
		),
		'groups' => array(
			'title' => $txt['mc_groups'],
			'enabled' => $context['can_moderate_groups'],
			'areas' => array(
				'userwatch' => array(
					'label' => $txt['mc_watched_users_title'],
					'enabled' => in_array('w', $context['admin_features']) && $modSettings['warning_settings'][0] == 1 && $context['can_moderate_boards'],
					'function' => 'ViewWatchedUsers',
					'subsections' => array(
						'member' => array($txt['mc_watched_users_member']),
						'post' => array($txt['mc_watched_users_post']),
					),
				),
				'groups' => array(
					'label' => $txt['mc_group_requests'],
					'file' => 'Groups.php',
					'function' => 'Groups',
					'custom_url' => $scripturl . '?action=moderate;area=groups;sa=requests',
				),
				'viewgroups' => array(
					'label' => $txt['mc_view_groups'],
					'file' => 'Groups.php',
					'function' => 'Groups',
				),
			),
		),
	);

	// Make sure the administrator has a valid session...
	validateSession('moderate');

	// I don't know where we're going - I don't know where we've been...
	$menuOptions = array(
		'action' => 'moderate',
		'disable_url_session_check' => true,
	);
	$mod_include_data = createMenu($moderation_areas, $menuOptions);
	unset($moderation_areas);

	// We got something - didn't we? DIDN'T WE!
	if ($mod_include_data == false)
		fatal_lang_error('no_access', false);

	// Retain the ID information in case required by a subaction.
	$context['moderation_menu_id'] = $context['max_menu_id'];
	$context['moderation_menu_name'] = 'menu_data_' . $context['moderation_menu_id'];

	// @todo: html in here is not good
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['moderation_center'],
		'help' => '',
		'description' => '
			<strong>' . $txt['hello_guest'] . ' ' . $context['user']['name'] . '!</strong>
			<br /><br />
			' . $txt['mc_description']);

	// What a pleasant shortcut - even tho we're not *really* on the admin screen who cares...
	$context['admin_area'] = $mod_include_data['current_area'];

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=moderate',
		'name' => $txt['moderation_center'],
	);
	if (isset($mod_include_data['current_area']) && $mod_include_data['current_area'] != 'index')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=moderate;area=' . $mod_include_data['current_area'],
			'name' => $mod_include_data['label'],
		);
	if (!empty($mod_include_data['current_subsection']) && $mod_include_data['subsections'][$mod_include_data['current_subsection']][0] != $mod_include_data['label'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=moderate;area=' . $mod_include_data['current_area'] . ';sa=' . $mod_include_data['current_subsection'],
			'name' => $mod_include_data['subsections'][$mod_include_data['current_subsection']][0],
		);

	// Now - finally - the bit before the encore - the main performance of course!
	if (!$dont_call)
	{
		if (isset($mod_include_data['file']))
			require_once($sourcedir . '/' . $mod_include_data['file']);

		$mod_include_data['function']();
	}
}

/**
 * This function basically is the home page of the moderation center.
 */
function ModerationHome()
{
	global $txt, $context, $scripturl, $modSettings, $user_info, $user_settings;

	loadTemplate('ModerationCenter');
	loadJavascriptFile('admin.js', array('default_theme' => true), 'admin.js');

	$context['page_title'] = $txt['moderation_center'];
	$context['sub_template'] = 'moderation_center';

	// Load what blocks the user actually can see...
	$valid_blocks = array(
		'n' => 'LatestNews',
		'p' => 'Notes',
	);
	if ($context['can_moderate_groups'])
		$valid_blocks['g'] = 'GroupRequests';
	if ($context['can_moderate_boards'])
	{
		$valid_blocks['r'] = 'ReportedPosts';
		$valid_blocks['w'] = 'WatchedUsers';
	}

	if (empty($user_settings['mod_prefs']))
		$user_blocks = 'n' . ($context['can_moderate_boards'] ? 'wr' : '') . ($context['can_moderate_groups'] ? 'g' : '');
	else
		list (, $user_blocks) = explode('|', $user_settings['mod_prefs']);

	$user_blocks = str_split($user_blocks);

	$context['mod_blocks'] = array();
	foreach ($valid_blocks as $k => $block)
	{
		if (in_array($k, $user_blocks))
		{
			$block = 'ModBlock' . $block;
			if (function_exists($block))
				$context['mod_blocks'][] = $block();
		}
	}
}

/**
 * Just prepares the time stuff for the simple machines latest news.
 */
function ModBlockLatestNews()
{
	global $context, $user_info;

	$context['time_format'] = urlencode($user_info['time_format']);

	// Return the template to use.
	return 'latest_news';
}

/**
 * Show a list of the most active watched users.
 */
function ModBlockWatchedUsers()
{
	global $context, $smcFunc, $scripturl, $modSettings;

	if (($watched_users = cache_get_data('recent_user_watches', 240)) === null)
	{
		$modSettings['warning_watch'] = empty($modSettings['warning_watch']) ? 1 : $modSettings['warning_watch'];
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, last_login
			FROM {db_prefix}members
			WHERE warning >= {int:warning_watch}
			ORDER BY last_login DESC
			LIMIT 10',
			array(
				'warning_watch' => $modSettings['warning_watch'],
			)
		);
		$watched_users = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$watched_users[] = $row;
		$smcFunc['db_free_result']($request);

		cache_put_data('recent_user_watches', $watched_users, 240);
	}

	$context['watched_users'] = array();
	foreach ($watched_users as $user)
	{
		$context['watched_users'][] = array(
			'id' => $user['id_member'],
			'name' => $user['real_name'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $user['id_member'] . '">' . $user['real_name'] . '</a>',
			'href' => $scripturl . '?action=profile;u=' . $user['id_member'],
			'last_login' => !empty($user['last_login']) ? timeformat($user['last_login']) : '',
		);
	}

	return 'watched_users';
}

/**
 * Show an area for the moderator to type into.
 */
function ModBlockNotes()
{
	global $context, $smcFunc, $scripturl, $txt, $user_info;

	// Are we saving a note?
	if (isset($_POST['makenote']) && isset($_POST['new_note']))
	{
		checkSession();

		$_POST['new_note'] = $smcFunc['htmlspecialchars'](trim($_POST['new_note']));
		// Make sure they actually entered something.
		if (!empty($_POST['new_note']) && $_POST['new_note'] !== $txt['mc_click_add_note'])
		{
			// Insert it into the database then!
			$smcFunc['db_insert']('',
				'{db_prefix}log_comments',
				array(
					'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
					'body' => 'string', 'log_time' => 'int',
				),
				array(
					$user_info['id'], $user_info['name'], 'modnote', '', $_POST['new_note'], time(),
				),
				array('id_comment')
			);

			// Clear the cache.
			cache_put_data('moderator_notes', null, 240);
			cache_put_data('moderator_notes_total', null, 240);
		}

		// Redirect otherwise people can resubmit.
		redirectexit('action=moderate');
	}

	// Bye... bye...
	if (isset($_GET['notes']) && isset($_GET['delete']) && is_numeric($_GET['delete']))
	{
		checkSession('get');

		// Lets delete it.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment = {int:note}
				AND comment_type = {string:type}',
			array(
				'note' => $_GET['delete'],
				'type' => 'modnote',
			)
		);

		// Clear the cache.
		cache_put_data('moderator_notes', null, 240);
		cache_put_data('moderator_notes_total', null, 240);

		redirectexit('action=moderate');
	}

	// How many notes in total?
	if (($moderator_notes_total = cache_get_data('moderator_notes_total', 240)) === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {string:modnote}',
			array(
				'modnote' => 'modnote',
			)
		);
		list ($moderator_notes_total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		cache_put_data('moderator_notes_total', $moderator_notes_total, 240);
	}

	// Grab the current notes. We can only use the cache for the first page of notes.
	$offset = isset($_GET['notes']) && isset($_GET['start']) ? $_GET['start'] : 0;
	if ($offset != 0 || ($moderator_notes = cache_get_data('moderator_notes', 240)) === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lc.member_name) AS member_name,
				lc.log_time, lc.body, lc.id_comment AS id_note
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {string:modnote}
			ORDER BY id_comment DESC
			LIMIT {int:offset}, 10',
			array(
				'modnote' => 'modnote',
				'offset' => $offset,
			)
		);
		$moderator_notes = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$moderator_notes[] = $row;
		$smcFunc['db_free_result']($request);

		if ($offset == 0)
			cache_put_data('moderator_notes', $moderator_notes, 240);
	}

	// Lets construct a page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=index;notes', $_GET['start'], $moderator_notes_total, 10);
	$context['start'] = $_GET['start'];

	$context['notes'] = array();
	foreach ($moderator_notes as $note)
	{
		$context['notes'][] = array(
			'author' => array(
				'id' => $note['id_member'],
				'link' => $note['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $note['id_member'] . '" title="' . $txt['on'] . ' ' . strip_tags(timeformat($note['log_time'])) . '">' . $note['member_name'] . '</a>') : $note['member_name'],
			),
			'time' => timeformat($note['log_time']),
			'text' => parse_bbc($note['body']),
			'delete_href' => $scripturl . '?action=moderate;area=index;notes;delete=' . $note['id_note'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		);
	}

	return 'notes';
}

/**
 * Show a list of the most recent reported posts.
 */
function ModBlockReportedPosts()
{
	global $context, $user_info, $scripturl, $smcFunc;

	// Got the info already?
	$cachekey = md5(serialize($user_info['mod_cache']['bq']));
	$context['reported_posts'] = array();
	if ($user_info['mod_cache']['bq'] == '0=1')
		return 'reported_posts_block';

	if (($reported_posts = cache_get_data('reported_posts_' . $cachekey, 90)) === null)
	{
		// By George, that means we in a position to get the reports, jolly good.
		$request = $smcFunc['db_query']('', '
			SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject,
				lr.num_reports, IFNULL(mem.real_name, lr.membername) AS author_name,
				IFNULL(mem.id_member, 0) AS id_author
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
				AND lr.closed = {int:not_closed}
				AND lr.ignore_all = {int:not_ignored}
			ORDER BY lr.time_updated DESC
			LIMIT 10',
			array(
				'not_closed' => 0,
				'not_ignored' => 0,
			)
		);
		$reported_posts = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$reported_posts[] = $row;
		$smcFunc['db_free_result']($request);

		// Cache it.
		cache_put_data('reported_posts_' . $cachekey, $reported_posts, 90);
	}

	$context['reported_posts'] = array();
	foreach ($reported_posts as $i => $row)
	{
		$context['reported_posts'][] = array(
			'id' => $row['id_report'],
			'alternate' => $i % 2,
			'topic_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'report_href' => $scripturl . '?action=moderate;area=reports;report=' . $row['id_report'],
			'author' => array(
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
			),
			'comments' => array(),
			'subject' => $row['subject'],
			'num_reports' => $row['num_reports'],
		);
	}

	return 'reported_posts_block';
}

/**
 * Show a list of all the group requests they can see.
 */
function ModBlockGroupRequests()
{
	global $context, $user_info, $scripturl, $smcFunc;

	$context['group_requests'] = array();
	// Make sure they can even moderate someone!
	if ($user_info['mod_cache']['gq'] == '0=1')
		return 'group_requests_block';

	// What requests are outstanding?
	$request = $smcFunc['db_query']('', '
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, mem.member_name, mg.group_name, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . ($user_info['mod_cache']['gq'] == '1=1' || $user_info['mod_cache']['gq'] == '0=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq']) . '
		ORDER BY lgr.id_request DESC
		LIMIT 10',
		array(
		)
	);
	for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i ++)
	{
		$context['group_requests'][] = array(
			'id' => $row['id_request'],
			'alternate' => $i % 2,
			'request_href' => $scripturl . '?action=groups;sa=requests;gid=' . $row['id_group'],
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			),
			'group' => array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
			),
			'time_submitted' => timeformat($row['time_applied']),
		);
	}
	$smcFunc['db_free_result']($request);

	return 'group_requests_block';
}

/**
 * Browse all the reported posts...
 * @todo this needs to be given its own file?
 */
function ReportedPosts()
{
	global $txt, $context, $scripturl, $modSettings, $user_info, $smcFunc;

	loadTemplate('ModerationCenter');

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_reported_posts'],
		'help' => '',
		'description' => $txt['mc_reported_posts_desc'],
	);

	// This comes under the umbrella of moderating posts.
	if ($user_info['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	// Are they wanting to view a particular report?
	if (!empty($_REQUEST['report']))
		return ModReport();

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_posts'];
	$context['sub_template'] = 'reported_posts';

	// Are we viewing open or closed reports?
	$context['view_closed'] = isset($_GET['sa']) && $_GET['sa'] == 'closed' ? 1 : 0;

	// Are we doing any work?
	if ((isset($_GET['ignore']) || isset($_GET['close'])) && isset($_GET['rid']))
	{
		checkSession('get');
		$_GET['rid'] = (int) $_GET['rid'];

		// Update the report...
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_reported
			SET ' . (isset($_GET['ignore']) ? 'ignore_all = {int:ignore_all}' : 'closed = {int:closed}') . '
			WHERE id_report = {int:id_report}
				AND ' . $user_info['mod_cache']['bq'],
			array(
				'ignore_all' => isset($_GET['ignore']) ? (int) $_GET['ignore'] : 0,
				'closed' => isset($_GET['close']) ? (int) $_GET['close'] : 0,
				'id_report' => $_GET['rid'],
			)
		);

		// Time to update.
		updateSettings(array('last_mod_report_action' => time()));
		recountOpenReports();
	}
	elseif (isset($_POST['close']) && isset($_POST['close_selected']))
	{
		checkSession('post');

		// All the ones to update...
		$toClose = array();
		foreach ($_POST['close'] as $rid)
			$toClose[] = (int) $rid;

		if (!empty($toClose))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_reported
				SET closed = {int:is_closed}
				WHERE id_report IN ({array_int:report_list})
					AND ' . $user_info['mod_cache']['bq'],
				array(
					'report_list' => $toClose,
					'is_closed' => 1,
				)
			);

			// Time to update.
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();
		}
	}

	// How many entries are we viewing?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']),
		array(
			'view_closed' => $context['view_closed'],
		)
	);
	list ($context['total_reports']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// So, that means we can page index, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reports' . ($context['view_closed'] ? ';sa=closed' : ''), $_GET['start'], $context['total_reports'], 10);
	$context['start'] = $_GET['start'];

	// By George, that means we in a position to get the reports, golly good.
	$request = $smcFunc['db_query']('', '
		SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.closed = {int:view_closed}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
		ORDER BY lr.time_updated DESC
		LIMIT ' . $context['start'] . ', 10',
		array(
			'view_closed' => $context['view_closed'],
		)
	);
	$context['reports'] = array();
	$report_ids = array();
	for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i++)
	{
		$report_ids[] = $row['id_report'];
		$context['reports'][$row['id_report']] = array(
			'id' => $row['id_report'],
			'alternate' => $i % 2,
			'topic_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'report_href' => $scripturl . '?action=moderate;area=reports;report=' . $row['id_report'],
			'author' => array(
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
			),
			'comments' => array(),
			'time_started' => timeformat($row['time_started']),
			'last_updated' => timeformat($row['time_updated']),
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body']),
			'num_reports' => $row['num_reports'],
			'closed' => $row['closed'],
			'ignore' => $row['ignore_all']
		);
	}
	$smcFunc['db_free_result']($request);

	// Now get all the people who reported it.
	if (!empty($report_ids))
	{
		$request = $smcFunc['db_query']('', '
			SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment,
				IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lrc.membername) AS reporter
			FROM {db_prefix}log_reported_comments AS lrc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
			WHERE lrc.id_report IN ({array_int:report_list})',
			array(
				'report_list' => $report_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$context['reports'][$row['id_report']]['comments'][] = array(
				'id' => $row['id_comment'],
				'message' => $row['comment'],
				'time' => timeformat($row['time_sent']),
				'member' => array(
					'id' => $row['id_member'],
					'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
					'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				),
			);
		}
		$smcFunc['db_free_result']($request);
	}
}

/**
 * Act as an entrace for all group related activity.
 * @todo As for most things in this file, this needs to be moved somewhere appropriate?
 */
function ModerateGroups()
{
	global $txt, $context, $scripturl, $modSettings, $user_info;

	// You need to be allowed to moderate groups...
	if ($user_info['mod_cache']['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Load the group templates.
	loadTemplate('ModerationCenter');

	// Setup the subactions...
	$subactions = array(
		'requests' => 'GroupRequests',
		'view' => 'ViewGroups',
	);

	if (!isset($_GET['sa']) || !isset($subactions[$_GET['sa']]))
		$_GET['sa'] = 'view';
	$context['sub_action'] = $_GET['sa'];

	// Call the relevant function.
	$subactions[$context['sub_action']]();
}

/**
 * How many open reports do we have?
 */
function recountOpenReports()
{
	global $user_info, $context, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported
		WHERE ' . $user_info['mod_cache']['bq'] . '
			AND closed = {int:not_closed}
			AND ignore_all = {int:not_ignored}',
		array(
			'not_closed' => 0,
			'not_ignored' => 0,
		)
	);
	list ($open_reports) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$_SESSION['rc'] = array(
		'id' => $user_info['id'],
		'time' => time(),
		'reports' => $open_reports,
	);

	$context['open_mod_reports'] = $open_reports;
}

/**
 * Get details about the moderation report... specified in
 * $_REQUEST['report'].
 */
function ModReport()
{
	global $user_info, $context, $sourcedir, $scripturl, $txt, $smcFunc;

	// Have to at least give us something
	if (empty($_REQUEST['report']))
		fatal_lang_error('mc_no_modreport_specified');

	// Integers only please
	$_REQUEST['report'] = (int) $_REQUEST['report'];

	// Get the report details, need this so we can limit access to a particular board
	$request = $smcFunc['db_query']('', '
		SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.id_report = {int:id_report}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
		LIMIT 1',
		array(
			'id_report' => $_REQUEST['report'],
		)
	);

	// So did we find anything?
	if (!$smcFunc['db_num_rows']($request))
		fatal_lang_error('mc_no_modreport_found');

	// Woohoo we found a report and they can see it!  Bad news is we have more work to do
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// If they are adding a comment then... add a comment.
	if (isset($_POST['add_comment']) && !empty($_POST['mod_comment']))
	{
		checkSession();

		$newComment = trim($smcFunc['htmlspecialchars']($_POST['mod_comment']));

		// In it goes.
		if (!empty($newComment))
		{
			$smcFunc['db_insert']('',
				'{db_prefix}log_comments',
				array(
					'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
					'id_notice' => 'int', 'body' => 'string', 'log_time' => 'int',
				),
				array(
					$user_info['id'], $user_info['name'], 'reportc', '',
					$_REQUEST['report'], $newComment, time(),
				),
				array('id_comment')
			);

			// Redirect to prevent double submittion.
			redirectexit($scripturl . '?action=moderate;area=reports;report=' . $_REQUEST['report']);
		}
	}

	$context['report'] = array(
		'id' => $row['id_report'],
		'topic_id' => $row['id_topic'],
		'board_id' => $row['id_board'],
		'message_id' => $row['id_msg'],
		'message_href' => $scripturl . '?msg=' . $row['id_msg'],
		'message_link' => '<a href="' . $scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
		'report_href' => $scripturl . '?action=moderate;area=reports;report=' . $row['id_report'],
		'author' => array(
			'id' => $row['id_author'],
			'name' => $row['author_name'],
			'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
			'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
		),
		'comments' => array(),
		'mod_comments' => array(),
		'time_started' => timeformat($row['time_started']),
		'last_updated' => timeformat($row['time_updated']),
		'subject' => $row['subject'],
		'body' => parse_bbc($row['body']),
		'num_reports' => $row['num_reports'],
		'closed' => $row['closed'],
		'ignore' => $row['ignore_all']
	);

	// So what bad things do the reporters have to say about it?
	$request = $smcFunc['db_query']('', '
		SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment, lrc.member_ip,
			IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lrc.membername) AS reporter
		FROM {db_prefix}log_reported_comments AS lrc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
		WHERE lrc.id_report = {int:id_report}',
		array(
			'id_report' => $context['report']['id'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['report']['comments'][] = array(
			'id' => $row['id_comment'],
			'message' => strtr($row['comment'], array("\n" => '<br />')),
			'time' => timeformat($row['time_sent']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
				'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'ip' => !empty($row['member_ip']) && allowedTo('moderate_forum') ? '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>' : '',
			),
		);
	}
	$smcFunc['db_free_result']($request);

	// Hang about old chap, any comments from moderators on this one?
	$request = $smcFunc['db_query']('', '
		SELECT lc.id_comment, lc.id_notice, lc.log_time, lc.body,
			IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lc.member_name) AS moderator
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_notice = {int:id_report}
			AND lc.comment_type = {string:reportc}',
		array(
			'id_report' => $context['report']['id'],
			'reportc' => 'reportc',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['report']['mod_comments'][] = array(
			'id' => $row['id_comment'],
			'message' => parse_bbc($row['body']),
			'time' => timeformat($row['log_time']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['moderator'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['moderator'] . '</a>' : $row['moderator'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			),
		);
	}
	$smcFunc['db_free_result']($request);

	// What have the other moderators done to this message?
	require_once($sourcedir . '/Modlog.php');
	require_once($sourcedir . '/Subs-List.php');
	loadLanguage('Modlog');

	// This is all the information from the moderation log.
	$listOptions = array(
		'id' => 'moderation_actions_list',
		'title' => $txt['mc_modreport_modactions'],
		'items_per_page' => 15,
		'no_items_label' => $txt['modlog_no_entries_found'],
		'base_href' => $scripturl . '?action=moderate;area=reports;report=' . $context['report']['id'],
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getModLogEntries',
			'params' => array(
				'lm.id_topic = {int:id_topic}',
				array('id_topic' => $context['report']['topic_id']),
				1,
			),
		),
		'get_count' => array(
			'function' => 'list_getModLogEntryCount',
			'params' => array(
				'lm.id_topic = {int:id_topic}',
				array('id_topic' => $context['report']['topic_id']),
				1,
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['modlog_action'],
				),
				'data' => array(
					'db' => 'action_text',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.action',
					'reverse' => 'lm.action DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['modlog_date'],
				),
				'data' => array(
					'db' => 'time',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.log_time',
					'reverse' => 'lm.log_time DESC',
				),
			),
			'moderator' => array(
				'header' => array(
					'value' => $txt['modlog_member'],
				),
				'data' => array(
					'db' => 'moderator_link',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'position' => array(
				'header' => array(
					'value' => $txt['modlog_position'],
				),
				'data' => array(
					'db' => 'position',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['modlog_ip'],
				),
				'data' => array(
					'db' => 'ip',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.ip',
					'reverse' => 'lm.ip DESC',
				),
			),
		),
	);

	// Create the watched user list.
	createList($listOptions);

	// Make sure to get the correct tab selected.
	if ($context['report']['closed'])
		$context[$context['moderation_menu_name']]['current_subsection'] = 'closed';

	// Finally we are done :P
	loadTemplate('ModerationCenter');
	$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
	$context['sub_template'] = 'viewmodreport';
}

/**
 * Show a notice sent to a user.
 */
function ShowNotice()
{
	global $smcFunc, $txt, $context;

	$context['page_title'] = $txt['show_notice'];
	$context['sub_template'] = 'show_notice';
	$context['template_layers'] = array();

	loadTemplate('ModerationCenter');

	// @todo Assumes nothing needs permission more than accessing moderation center!
	$id_notice = (int) $_GET['nid'];
	$request = $smcFunc['db_query']('', '
		SELECT body, subject
		FROM {db_prefix}log_member_notices
		WHERE id_notice = {int:id_notice}',
		array(
			'id_notice' => $id_notice,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);
	list ($context['notice_body'], $context['notice_subject']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['notice_body'] = parse_bbc($context['notice_body'], false);
}

/**
 * View watched users.
 */
function ViewWatchedUsers()
{
	global $smcFunc, $modSettings, $context, $txt, $scripturl, $user_info, $sourcedir;

	// Some important context!
	$context['page_title'] = $txt['mc_watched_users_title'];
	$context['view_posts'] = isset($_GET['sa']) && $_GET['sa'] == 'post';
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

	loadTemplate('ModerationCenter');

	// Get some key settings!
	$modSettings['warning_watch'] = empty($modSettings['warning_watch']) ? 1 : $modSettings['warning_watch'];

	// Put some pretty tabs on cause we're gonna be doing hot stuff here...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_watched_users_title'],
		'help' => '',
		'description' => $txt['mc_watched_users_desc'],
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
			require_once($sourcedir . '/RemoveTopic.php');
			// If they don't have permission we'll let it error - either way no chance of a security slip here!
			foreach ($toDelete as $did)
				removeMessage($did);
		}
	}

	// Start preparing the list by grabbing relevant permissions.
	if (!$context['view_posts'])
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

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'watch_user_list',
		'title' => $txt['mc_watched_users_title'] . ' - ' . ($context['view_posts'] ? $txt['mc_watched_users_post'] : $txt['mc_watched_users_member']),
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $context['view_posts'] ? $txt['mc_watched_users_no_posts'] : $txt['mc_watched_users_none'],
		'base_href' => $scripturl . '?action=moderate;area=userwatch;sa=' . ($context['view_posts'] ? 'post' : 'member'),
		'default_sort_col' => $context['view_posts'] ? '' : 'member',
		'get_items' => array(
			'function' => $context['view_posts'] ? 'list_getWatchedUserPosts' : 'list_getWatchedUsers',
			'params' => array(
				$approve_query,
				$delete_boards,
			),
		),
		'get_count' => array(
			'function' => $context['view_posts'] ? 'list_getWatchedUserPostsCount' : 'list_getWatchedUserCount',
			'params' => array(
				$approve_query,
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'member' => array(
				'header' => array(
					'value' => $txt['mc_watched_users_member'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
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
					'value' => $txt['mc_watched_users_warning'],
				),
				'data' => array(
					'function' => create_function('$member', '
						global $scripturl;

						return allowedTo(\'issue_warning\') ? \'<a href="\' . $scripturl . \'?action=profile;area=issuewarning;u=\' . $member[\'id\'] . \'">\' . $member[\'warning\'] . \'%</a>\' : $member[\'warning\'] . \'%\';
					'),
				),
				'sort' => array(
					'default' => 'warning',
					'reverse' => 'warning DESC',
				),
			),
			'posts' => array(
				'header' => array(
					'value' => $txt['posts'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d;area=showposts;sa=messages">%2$s</a>',
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
					'value' => $txt['mc_watched_users_last_login'],
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
					'value' => $txt['mc_watched_users_last_post'],
				),
				'data' => array(
					'function' => create_function('$member', '
						global $scripturl;

						if ($member[\'last_post_id\'])
							return \'<a href="\' . $scripturl . \'?msg=\' . $member[\'last_post_id\'] . \'">\' . $member[\'last_post\'] . \'</a>\';
						else
							return $member[\'last_post\'];
					'),
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=moderate;area=userwatch;sa=post',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			$context['view_posts'] ?
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="delete_selected" value="' . $txt['quickmod_delete_selected'] . '" class="button_submit" />',
				'align' => 'right',
			) : array(),
		),
	);

	// If this is being viewed by posts we actually change the columns to call a template each time.
	if ($context['view_posts'])
	{
		$listOptions['columns'] = array(
			'posts' => array(
				'data' => array(
					'function' => create_function('$post', '
						return template_user_watch_post_callback($post);
					'),
				),
			),
		);
	}

	// Create the watched user list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'watch_user_list';
}

/**
 * Callback for createList().
 * @param $approve_query
 */
function list_getWatchedUserCount($approve_query)
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}',
		array(
			'warning_watch' => $modSettings['warning_watch'],
		)
	);
	list ($totalMembers) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $totalMembers;
}

/**
 * Callback for createList().
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 * @param $approve_query
 * @param $dummy
 */
function list_getWatchedUsers($start, $items_per_page, $sort, $approve_query, $dummy)
{
	global $smcFunc, $txt, $scripturl, $modSettings, $user_info, $context;

	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, last_login, posts, warning
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}
		ORDER BY {raw:sort}
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'warning_watch' => $modSettings['warning_watch'],
			'sort' => $sort,
		)
	);
	$watched_users = array();
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$watched_users[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'last_login' => $row['last_login'] ? timeformat($row['last_login']) : $txt['never'],
			'last_post' => $txt['not_applicable'],
			'last_post_id' => 0,
			'warning' => $row['warning'],
			'posts' => $row['posts'],
		);
		$members[] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);

	if (!empty($members))
	{
		// First get the latest messages from these users.
		$request = $smcFunc['db_query']('', '
			SELECT m.id_member, MAX(m.id_msg) AS last_post_id
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		);
		$latest_posts = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$latest_posts[$row['id_member']] = $row['last_post_id'];

		if (!empty($latest_posts))
		{
			// Now get the time those messages were posted.
			$request = $smcFunc['db_query']('', '
				SELECT id_member, poster_time
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:message_list})',
				array(
					'message_list' => $latest_posts,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$watched_users[$row['id_member']]['last_post'] = timeformat($row['poster_time']);
				$watched_users[$row['id_member']]['last_post_id'] = $latest_posts[$row['id_member']];
			}

			$smcFunc['db_free_result']($request);
		}

		$request = $smcFunc['db_query']('', '
			SELECT MAX(m.poster_time) AS last_post, MAX(m.id_msg) AS last_post_id, m.id_member
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$watched_users[$row['id_member']]['last_post'] = timeformat($row['last_post']);
			$watched_users[$row['id_member']]['last_post_id'] = $row['last_post_id'];
		}
		$smcFunc['db_free_result']($request);
	}

	return $watched_users;
}

/**
 * Callback for createList().
 *
 * @param $approve_query
 */
function list_getWatchedUserPostsCount($approve_query)
{
	global $smcFunc, $modSettings, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE mem.warning >= {int:warning_watch}
				AND {query_see_board}
				' . $approve_query,
		array(
			'warning_watch' => $modSettings['warning_watch'],
		)
	);
	list ($totalMemberPosts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $totalMemberPosts;
}

/**
 * Callback for createList().
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 * @param $approve_query
 * @param $delete_boards
 */
function list_getWatchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards)
{
	global $smcFunc, $txt, $scripturl, $modSettings, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
			m.approved, mem.real_name, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning >= {int:warning_watch}
			AND {query_see_board}
			' . $approve_query . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'warning_watch' => $modSettings['warning_watch'],
		)
	);
	$member_posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['subject'] = censorText($row['subject']);
		$row['body'] = censorText($row['body']);

		$member_posts[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'author_link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']),
			'poster_time' => timeformat($row['poster_time']),
			'approved' => $row['approved'],
			'can_delete' => $delete_boards == array(0) || in_array($row['id_board'], $delete_boards),
		);
	}
	$smcFunc['db_free_result']($request);

	return $member_posts;
}

/**
 * Entry point for viewing warning related stuff.
 */
function ViewWarnings()
{
	global $context, $txt;

	$subActions = array(
		'log' => array('ViewWarningLog'),
		'templateedit' => array('ModifyWarningTemplate', 'issue_warning'),
		'templates' => array('ViewWarningTemplates', 'issue_warning'),
	);

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && (empty($subActions[$_REQUEST['sa']][1]) || allowedTo($subActions[$_REQUEST['sa']]))? $_REQUEST['sa'] : 'log';

	// Some of this stuff is overseas, so to speak.
	loadTemplate('ModerationCenter');
	loadLanguage('Profile');

	// Setup the admin tabs.
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_warnings'],
		'description' => $txt['mc_warnings_description'],
	);

	// Call the right function.
	$subActions[$_REQUEST['sa']][0]();
}

/**
 * Simply put, look at the warning log!
 */
function ViewWarningLog()
{
	global $smcFunc, $modSettings, $context, $txt, $scripturl, $sourcedir;

	// Setup context as always.
	$context['page_title'] = $txt['mc_warning_log_title'];

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'warning_list',
		'title' => $txt['mc_warning_log_title'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['mc_warnings_none'],
		'base_href' => $scripturl . '?action=moderate;area=warnings;sa=log;' . $context['session_var'] . '=' . $context['session_id'],
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
					'value' => $txt['profile_warning_previous_issued'],
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
					'value' => $txt['mc_warnings_recipient'],
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
					'value' => $txt['profile_warning_previous_time'],
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
					'value' => $txt['profile_warning_previous_reason'],
				),
				'data' => array(
					'function' => create_function('$warning', '
						global $scripturl, $settings, $txt;

						$output = \'
							<div class="floatleft">
								\' . $warning[\'reason\'] . \'
							</div>\';

						if (!empty($warning[\'id_notice\']))
							$output .= \'
								<a href="\' . $scripturl . \'?action=moderate;area=notice;nid=\' . $warning[\'id_notice\'] . \'" onclick="window.open(this.href, \\\'\\\', \\\'scrollbars=yes,resizable=yes,width=400,height=250\\\');return false;" target="_blank" class="new_win" title="\' . $txt[\'profile_warning_previous_notice\'] . \'"><img src="\' . $settings[\'default_images_url\'] . \'/filter.png" alt="\' . $txt[\'profile_warning_previous_notice\'] . \'" /></a>\';
						return $output;
					'),
				),
			),
			'points' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_level'],
				),
				'data' => array(
					'db' => 'counter',
				),
			),
		),
	);

	// Create the watched user list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'warning_list';
}

/**
 * Callback for createList().
 */
function list_getWarningCount()
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:warning}',
		array(
			'warning' => 'warning',
		)
	);
	list ($totalWarns) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $totalWarns;
}

/**
 * Callback for createList().
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 */
function list_getWarnings($start, $items_per_page, $sort)
{
	global $smcFunc, $txt, $scripturl, $modSettings, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lc.member_name) AS member_name_col,
			IFNULL(mem2.id_member, 0) AS id_recipient, IFNULL(mem2.real_name, lc.recipient_name) AS recipient_name,
			lc.log_time, lc.body, lc.id_notice, lc.counter
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = lc.id_recipient)
		WHERE lc.comment_type = {string:warning}
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'warning' => 'warning',
		)
	);
	$warnings = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$warnings[] = array(
			'issuer_link' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name_col'] . '</a>') : $row['member_name_col'],
			'recipient_link' => $row['id_recipient'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_recipient'] . '">' . $row['recipient_name'] . '</a>') : $row['recipient_name'],
			'time' => timeformat($row['log_time']),
			'reason' => $row['body'],
			'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
			'id_notice' => $row['id_notice'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $warnings;
}

/**
 * Load all the warning templates.
 */
function ViewWarningTemplates()
{
	global $smcFunc, $modSettings, $context, $txt, $scripturl, $sourcedir, $user_info;

	// Submitting a new one?
	if (isset($_POST['add']))
		return ModifyWarningTemplate();
	elseif (isset($_POST['delete']) && !empty($_POST['deltpl']))
	{
		checkSession('post');
		validateToken('mod-wt');

		// Log the actions.
		$request = $smcFunc['db_query']('', '
			SELECT recipient_name
			FROM {db_prefix}log_comments
			WHERE id_comment IN ({array_int:delete_ids})
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			array(
				'delete_ids' => $_POST['deltpl'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => $user_info['id'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			logAction('delete_warn_template', array('template' => $row['recipient_name']));
		$smcFunc['db_free_result']($request);

		// Do the deletes.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment IN ({array_int:delete_ids})
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			array(
				'delete_ids' => $_POST['deltpl'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => $user_info['id'],
			)
		);
	}

	// Setup context as always.
	$context['page_title'] = $txt['mc_warning_templates_title'];

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'warning_template_list',
		'title' => $txt['mc_warning_templates_title'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['mc_warning_templates_none'],
		'base_href' => $scripturl . '?action=moderate;area=warnings;sa=templates;' . $context['session_var'] . '=' . $context['session_id'],
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
					'value' => $txt['mc_warning_templates_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=moderate;area=warnings;sa=templateedit;tid=%1$d">%2$s</a>',
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
					'value' => $txt['mc_warning_templates_creator'],
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
					'value' => $txt['mc_warning_templates_time'],
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
					'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $context, $txt, $scripturl;

						return \'<input type="checkbox" name="deltpl[]" value="\' . $rowData[\'id_comment\'] . \'" class="input_check" />\';
					'),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=moderate;area=warnings;sa=templates',
			'token' => 'mod-wt',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '&nbsp;<input type="submit" name="delete" value="' . $txt['mc_warning_template_delete'] . '" onclick="return confirm(\'' . $txt['mc_warning_template_delete_confirm'] . '\');" class="button_submit" />',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="add" value="' . $txt['mc_warning_template_add'] . '" class="button_submit" />',
			),
		),
	);

	// Create the watched user list.
	createToken('mod-wt');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'warning_template_list';
}

/**
  * Callback for createList().
  */
function list_getWarningTemplateCount()
{
	global $smcFunc, $modSettings, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:warntpl}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})',
		array(
			'warntpl' => 'warntpl',
			'generic' => 0,
			'current_member' => $user_info['id'],
		)
	);
	list ($totalWarns) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $totalWarns;
}

/**
 * Callback for createList().
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 */
function list_getWarningTemplates($start, $items_per_page, $sort)
{
	global $smcFunc, $txt, $scripturl, $modSettings, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT lc.id_comment, IFNULL(mem.id_member, 0) AS id_member,
			IFNULL(mem.real_name, lc.member_name) AS creator_name, recipient_name AS template_title,
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
			'current_member' => $user_info['id'],
		)
	);
	$templates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$templates[] = array(
			'id_comment' => $row['id_comment'],
			'creator' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['creator_name'] . '</a>') : $row['creator_name'],
			'time' => timeformat($row['log_time']),
			'title' => $row['template_title'],
			'body' => $smcFunc['htmlspecialchars']($row['body']),
		);
	}
	$smcFunc['db_free_result']($request);

	return $templates;
}

/**
 * Edit a warning template.
 */
function ModifyWarningTemplate()
{
	global $smcFunc, $context, $txt, $user_info, $sourcedir;

	$context['id_template'] = isset($_REQUEST['tid']) ? (int) $_REQUEST['tid'] : 0;
	$context['is_edit'] = $context['id_template'];

	// Standard template things.
	$context['page_title'] = $context['is_edit'] ? $txt['mc_warning_template_modify'] : $txt['mc_warning_template_add'];
	$context['sub_template'] = 'warn_template';
	$context[$context['moderation_menu_name']]['current_subsection'] = 'templates';

	// Defaults.
	$context['template_data'] = array(
		'title' => '',
		'body' => $txt['mc_warning_template_body_default'],
		'personal' => false,
		'can_edit_personal' => true,
	);

	// If it's an edit load it.
	if ($context['is_edit'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, id_recipient, recipient_name AS template_title, body
			FROM {db_prefix}log_comments
			WHERE id_comment = {int:id}
				AND comment_type = {string:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			array(
				'id' => $context['id_template'],
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => $user_info['id'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$context['template_data'] = array(
				'title' => $row['template_title'],
				'body' => $smcFunc['htmlspecialchars']($row['body']),
				'personal' => $row['id_recipient'],
				'can_edit_personal' => $row['id_member'] == $user_info['id'],
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Wait, we are saving?
	if (isset($_POST['save']))
	{
		checkSession('post');
		validateToken('mod-wt');

		// To check the BBC is pretty good...
		require_once($sourcedir . '/Subs-Post.php');

		// Bit of cleaning!
		$_POST['template_body'] = trim($_POST['template_body']);
		$_POST['template_title'] = trim($_POST['template_title']);

		// Need something in both boxes.
		if (!empty($_POST['template_body']) && !empty($_POST['template_title']))
		{
			// Safety first.
			$_POST['template_title'] = $smcFunc['htmlspecialchars']($_POST['template_title']);

			// Clean up BBC.
			preparsecode($_POST['template_body']);
			// But put line breaks back!
			$_POST['template_body'] = strtr($_POST['template_body'], array('<br />' => "\n"));

			// Is this personal?
			$recipient_id = !empty($_POST['make_personal']) ? $user_info['id'] : 0;

			// If we are this far it's save time.
			if ($context['is_edit'])
			{
				// Simple update...
				$smcFunc['db_query']('', '
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
						'id' => $context['id_template'],
						'warntpl' => 'warntpl',
						'generic' => 0,
						'current_member' => $user_info['id'],
					)
				);

				// If it wasn't visible and now is they've effectively added it.
				if ($context['template_data']['personal'] && !$recipient_id)
					logAction('add_warn_template', array('template' => $_POST['template_title']));
				// Conversely if they made it personal it's a delete.
				elseif (!$context['template_data']['personal'] && $recipient_id)
					logAction('delete_warn_template', array('template' => $_POST['template_title']));
				// Otherwise just an edit.
				else
					logAction('modify_warn_template', array('template' => $_POST['template_title']));
			}
			else
			{
				$smcFunc['db_insert']('',
					'{db_prefix}log_comments',
					array(
						'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
						'recipient_name' => 'string-255', 'body' => 'string-65535', 'log_time' => 'int',
					),
					array(
						$user_info['id'], $user_info['name'], 'warntpl', $recipient_id,
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
			$context['warning_errors'] = array();
			$context['template_data']['title'] = !empty($_POST['template_title']) ? $_POST['template_title'] : '';
			$context['template_data']['body'] = !empty($_POST['template_body']) ? $_POST['template_body'] : $txt['mc_warning_template_body_default'];
			$context['template_data']['personal'] = !empty($_POST['make_personal']);
			if (empty($_POST['template_title']))
				$context['warning_errors'][] = $txt['mc_warning_template_error_no_title'];
			if (empty($_POST['template_body']))
				$context['warning_errors'][] = $txt['mc_warning_template_error_no_body'];
		}
	}

	createToken('mod-wt');
}

/**
 * Change moderation preferences.
 */
function ModerationSettings()
{
	global $context, $smcFunc, $txt, $sourcedir, $scripturl, $user_settings, $user_info;

	// Some useful context stuff.
	loadTemplate('ModerationCenter');
	$context['page_title'] = $txt['mc_settings'];
	$context['sub_template'] = 'moderation_settings';
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_prefs_title'],
		'help' => '',
		'description' => $txt['mc_prefs_desc']
	);

	// What blocks can this user see?
	$context['homepage_blocks'] = array(
		'n' => $txt['mc_prefs_latest_news'],
		'p' => $txt['mc_notes'],
	);
	if ($context['can_moderate_groups'])
		$context['homepage_blocks']['g'] = $txt['mc_group_requests'];
	if ($context['can_moderate_boards'])
	{
		$context['homepage_blocks']['r'] = $txt['mc_reported_posts'];
		$context['homepage_blocks']['w'] = $txt['mc_watched_users'];
	}

	// Does the user have any settings yet?
	if (empty($user_settings['mod_prefs']))
	{
		$mod_blocks = 'n' . ($context['can_moderate_boards'] ? 'wr' : '') . ($context['can_moderate_groups'] ? 'g' : '');
		$pref_binary = 5;
		$show_reports = 1;
	}
	else
	{
		list ($show_reports, $mod_blocks, $pref_binary) = explode('|', $user_settings['mod_prefs']);
	}

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession('post');
		validateToken('mod-set');

		/* Current format of mod_prefs is:
			x|ABCD|yyy

			WHERE:
				x = Show report count on forum header.
				ABCD = Block indexes to show on moderation main page.
				yyy = Integer with the following bit status:
					- yyy & 1 = Always notify on reports.
					- yyy & 2 = Notify on reports for moderators only.
					- yyy & 4 = Notify about posts awaiting approval.
		*/

		// Do blocks first!
		$mod_blocks = '';
		if (!empty($_POST['mod_homepage']))
			foreach ($_POST['mod_homepage'] as $k => $v)
			{
				// Make sure they can add this...
				if (isset($context['homepage_blocks'][$k]))
					$mod_blocks .= $k;
			}

		// Now check other options!
		$pref_binary = 0;

		if ($context['can_moderate_approvals'] && !empty($_POST['mod_notify_approval']))
			$pref_binary |= 4;

		if ($context['can_moderate_boards'])
		{
			if (!empty($_POST['mod_notify_report']))
				$pref_binary |= ($_POST['mod_notify_report'] == 2 ? 1 : 2);

			$show_reports = !empty($_POST['mod_show_reports']) ? 1 : 0;
		}

		// Put it all together.
		$mod_prefs = $show_reports . '|' . $mod_blocks . '|' . $pref_binary;
		updateMemberData($user_info['id'], array('mod_prefs' => $mod_prefs));
	}

	// What blocks does the user currently have selected?
	$context['mod_settings'] = array(
		'show_reports' => $show_reports,
		'notify_report' => $pref_binary & 2 ? 1 : ($pref_binary & 1 ? 2 : 0),
		'notify_approval' => $pref_binary & 4,
		'user_blocks' => str_split($mod_blocks),
	);

	createToken('mod-set');
}

/**
 * This ends a moderator session, requiring authentication to access the MCP again.
 */
function ModEndSession()
{
	// This is so easy!
	unset($_SESSION['moderate_time']);

	// Clean any moderator tokens as well.
	foreach ($_SESSION['token'] as $key => $token)
		if (strpos($key, '-mod') !== false)
			unset($_SESSION['token'][$key]);

	redirectexit('action=moderate');
}

?>