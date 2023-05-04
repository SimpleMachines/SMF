<?php

/**
 * Handle all of the searching from here.
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
use SMF\Board;
use SMF\Category;
use SMF\Config;
use SMF\Lang;
use SMF\Msg;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\QuickModeration;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Ask the user what they want to search for.
 * What it does:
 * - shows the screen to search forum posts (action=search)
 * - uses the main sub template of the Search template.
 * - uses the Search language file.
 * - requires the search_posts permission.
 * - decodes and loads search parameters given in the URL (if any).
 * - the form redirects to index.php?action=search2.
 */
function PlushSearch1()
{
	// Is the load average too high to allow searching just now?
	if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_search']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	Lang::load('Search');
	// Don't load this in XML mode.
	if (!isset($_REQUEST['xml']))
	{
		Theme::loadTemplate('Search');
		Theme::loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
	}

	// Check the user's permissions.
	isAllowedTo('search_posts');

	// Link tree....
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=search',
		'name' => Lang::$txt['search']
	);

	// This is hard coded maximum string length.
	Utils::$context['search_string_limit'] = 100;

	Utils::$context['require_verification'] = User::$me->is_guest && !empty(Config::$modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);
	if (Utils::$context['require_verification'])
	{
		require_once(Config::$sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'search',
		);
		Utils::$context['require_verification'] = create_control_verification($verificationOptions);
		Utils::$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// If you got back from search2 by using the linktree, you get your original search parameters back.
	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));
		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

		Utils::$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			Utils::$context['search_params'][$k] = $v;
		}
		if (isset(Utils::$context['search_params']['brd']))
			Utils::$context['search_params']['brd'] = Utils::$context['search_params']['brd'] == '' ? array() : explode(',', Utils::$context['search_params']['brd']);
	}

	if (isset($_REQUEST['search']))
		Utils::$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

	if (isset(Utils::$context['search_params']['search']))
		Utils::$context['search_params']['search'] = Utils::htmlspecialchars(Utils::$context['search_params']['search']);
	if (isset(Utils::$context['search_params']['userspec']))
		Utils::$context['search_params']['userspec'] = Utils::htmlspecialchars(Utils::$context['search_params']['userspec']);
	if (!empty(Utils::$context['search_params']['searchtype']))
		Utils::$context['search_params']['searchtype'] = 2;
	if (!empty(Utils::$context['search_params']['minage']))
		Utils::$context['search_params']['minage'] = (int) Utils::$context['search_params']['minage'];
	if (!empty(Utils::$context['search_params']['maxage']))
		Utils::$context['search_params']['maxage'] = (int) Utils::$context['search_params']['maxage'];

	Utils::$context['search_params']['show_complete'] = !empty(Utils::$context['search_params']['show_complete']);
	Utils::$context['search_params']['subject_only'] = !empty(Utils::$context['search_params']['subject_only']);

	// Load the error text strings if there were errors in the search.
	if (!empty(Utils::$context['search_errors']))
	{
		Lang::load('Errors');
		Utils::$context['search_errors']['messages'] = array();
		foreach (Utils::$context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error === 'messages')
				continue;

			if ($search_error == 'string_too_long')
				Lang::$txt['error_string_too_long'] = sprintf(Lang::$txt['error_string_too_long'], Utils::$context['search_string_limit']);

			Utils::$context['search_errors']['messages'][] = Lang::$txt['error_' . $search_error];
		}
	}

	// Find all the boards this user is allowed to see.
	$request = Db::$db->query('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}',
		array(
			'empty_string' => '',
		)
	);
	Utils::$context['num_boards'] = Db::$db->num_rows($request);
	Utils::$context['boards_check_all'] = true;
	Utils::$context['categories'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// This category hasn't been set up yet..
		if (!isset(Utils::$context['categories'][$row['id_cat']]))
			Utils::$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => array()
			);

		// Set this board up, and let the template know when it's a child.  (indent them..)
		Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => (empty(Utils::$context['search_params']['brd']) && (empty(Config::$modSettings['recycle_enable']) || $row['id_board'] != Config::$modSettings['recycle_board']) && !in_array($row['id_board'], User::$me->ignoreboards)) || (!empty(Utils::$context['search_params']['brd']) && in_array($row['id_board'], Utils::$context['search_params']['brd']))
		);

		// If a board wasn't checked that probably should have been ensure the board selection is selected, yo!
		if (!Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] && (empty(Config::$modSettings['recycle_enable']) || $row['id_board'] != Config::$modSettings['recycle_board']))
			Utils::$context['boards_check_all'] = false;
	}
	Db::$db->free_result($request);

	Category::sort(Utils::$context['categories']);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = array();
	foreach (Utils::$context['categories'] as $category)
	{
		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));

		// Include a list of boards per category for easy toggling.
		Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	// Now, alternate them so they can be shown left and right ;).
	Utils::$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++)
	{
		Utils::$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			Utils::$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			Utils::$context['board_columns'][] = array();
	}

	if (!empty($_REQUEST['topic']))
	{
		Utils::$context['search_params']['topic'] = (int) $_REQUEST['topic'];
		Utils::$context['search_params']['show_complete'] = true;
	}
	if (!empty(Utils::$context['search_params']['topic']))
	{
		Utils::$context['search_params']['topic'] = (int) Utils::$context['search_params']['topic'];

		Utils::$context['search_topic'] = array(
			'id' => Utils::$context['search_params']['topic'],
			'href' => Config::$scripturl . '?topic=' . Utils::$context['search_params']['topic'] . '.0',
		);

		$request = Db::$db->query('', '
			SELECT subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_message_board} ' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved_true}' : '') . '
			LIMIT 1',
			array(
				'is_approved_true' => 1,
				'search_topic_id' => Utils::$context['search_params']['topic'],
			)
		);

		if (Db::$db->num_rows($request) == 0)
			fatal_lang_error('topic_gone', false);

		list (Utils::$context['search_topic']['subject']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Utils::$context['search_topic']['link'] = '<a href="' . Utils::$context['search_topic']['href'] . '">' . Utils::$context['search_topic']['subject'] . '</a>';
	}

	Utils::$context['page_title'] = Lang::$txt['set_parameters'];

	call_integration_hook('integrate_search');
}

/**
 * Gather the results and show them.
 * What it does:
 * - checks user input and searches the messages table for messages matching the query.
 * - requires the search_posts permission.
 * - uses the results sub template of the Search template.
 * - uses the Search language file.
 * - stores the results into the search cache.
 * - show the results of the search query.
 */
function PlushSearch2()
{
	global $messages_request, $boards_can;
	global $excludedWords, $participants;

	// if coming from the quick search box, and we want to search on members, well we need to do that ;)
	if (isset($_REQUEST['search_selection']) && $_REQUEST['search_selection'] === 'members')
		redirectexit(Config::$scripturl . '?action=mlist;sa=search;fields=name,email;search=' . urlencode($_REQUEST['search']));

	if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_search']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		send_http_status(403);
		die;
	}

	if (isset($_REQUEST['start']))
		$_REQUEST['start'] = (int) $_REQUEST['start'];

	$weight_factors = array(
		'frequency' => array(
			'search' => 'COUNT(*) / (MAX(t.num_replies) + 1)',
			'results' => '(t.num_replies + 1)',
		),
		'age' => array(
			'search' => 'CASE WHEN MAX(m.id_msg) < {int:min_msg} THEN 0 ELSE (MAX(m.id_msg) - {int:min_msg}) / {int:recent_message} END',
			'results' => 'CASE WHEN t.id_first_msg < {int:min_msg} THEN 0 ELSE (t.id_first_msg - {int:min_msg}) / {int:recent_message} END',
		),
		'length' => array(
			'search' => 'CASE WHEN MAX(t.num_replies) < {int:huge_topic_posts} THEN MAX(t.num_replies) / {int:huge_topic_posts} ELSE 1 END',
			'results' => 'CASE WHEN t.num_replies < {int:huge_topic_posts} THEN t.num_replies / {int:huge_topic_posts} ELSE 1 END',
		),
		'subject' => array(
			'search' => 0,
			'results' => 0,
		),
		'first_message' => array(
			'search' => 'CASE WHEN MIN(m.id_msg) = MAX(t.id_first_msg) THEN 1 ELSE 0 END',
		),
		'sticky' => array(
			'search' => 'MAX(t.is_sticky)',
			'results' => 't.is_sticky',
		),
	);

	call_integration_hook('integrate_search_weights', array(&$weight_factors));

	$weight = array();
	$weight_total = 0;
	foreach ($weight_factors as $weight_factor => $value)
	{
		$weight[$weight_factor] = empty(Config::$modSettings['search_weight_' . $weight_factor]) ? 0 : (int) Config::$modSettings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// Zero weight.  Weightless :P.
	if (empty($weight_total))
		fatal_lang_error('search_invalid_weights');

	// These vars don't require an interface, they're just here for tweaking.
	$recentPercentage = 0.30;
	$humungousTopicPosts = 200;
	$maxMembersToSearch = 500;
	$maxMessageResults = empty(Config::$modSettings['search_max_results']) ? 0 : Config::$modSettings['search_max_results'] * 5;

	// Start with no errors.
	Utils::$context['search_errors'] = array();

	// Number of pages hard maximum - normally not set at all.
	Config::$modSettings['search_max_results'] = empty(Config::$modSettings['search_max_results']) ? 200 * Config::$modSettings['search_results_per_page'] : (int) Config::$modSettings['search_max_results'];

	// Maximum length of the string.
	Utils::$context['search_string_limit'] = 100;

	Lang::load('Search');
	if (!isset($_REQUEST['xml']))
		Theme::loadTemplate('Search');
	//If we're doing XML we need to use the results template regardless really.
	else
		Utils::$context['sub_template'] = 'results';

	// Are you allowed?
	isAllowedTo('search_posts');

	require_once(Config::$sourcedir . '/Display.php');

	// Load up the search API we are going to use.
	$searchAPI = SearchApi::load();

	// $search_params will carry all settings that differ from the default search parameters.
	// That way, the URLs involved in a search page will be kept as short as possible.
	$search_params = array();

	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));

		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', (!empty($temp_params2) ? $temp_params2 : $temp_params));

		foreach ($temp_params as $i => $data)
		{
			@list($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}

		if (isset($search_params['brd']))
			$search_params['brd'] = empty($search_params['brd']) ? array() : explode(',', $search_params['brd']);
	}

	// Store whether simple search was used (needed if the user wants to do another query).
	if (!isset($search_params['advanced']))
		$search_params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;

	// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;

	// Minimum age of messages. Default to zero (don't set param in that case).
	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

	// Maximum age of messages. Default to infinite (9999 days: param not set).
	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	// Searching a specific topic?
	if (!empty($_REQUEST['topic']) || (!empty($_REQUEST['search_selection']) && $_REQUEST['search_selection'] == 'topic'))
	{
		$search_params['topic'] = empty($_REQUEST['search_selection']) ? (int) $_REQUEST['topic'] : (isset($_REQUEST['sd_topic']) ? (int) $_REQUEST['sd_topic'] : '');
		$search_params['show_complete'] = true;
	}
	elseif (!empty($search_params['topic']))
		$search_params['topic'] = (int) $search_params['topic'];

	if (!empty($search_params['minage']) || !empty($search_params['maxage']))
	{
		$request = Db::$db->query('', '
			SELECT ' . (empty($search_params['maxage']) ? '0, ' : 'COALESCE(MIN(id_msg), -1), ') . (empty($search_params['minage']) ? '0' : 'COALESCE(MAX(id_msg), -1)') . '
			FROM {db_prefix}messages
			WHERE 1=1' . (Config::$modSettings['postmod_active'] ? '
				AND approved = {int:is_approved_true}' : '') . (empty($search_params['minage']) ? '' : '
				AND poster_time <= {int:timestamp_minimum_age}') . (empty($search_params['maxage']) ? '' : '
				AND poster_time >= {int:timestamp_maximum_age}'),
			array(
				'timestamp_minimum_age' => empty($search_params['minage']) ? 0 : time() - 86400 * $search_params['minage'],
				'timestamp_maximum_age' => empty($search_params['maxage']) ? 0 : time() - 86400 * $search_params['maxage'],
				'is_approved_true' => 1,
			)
		);
		list ($minMsgID, $maxMsgID) = Db::$db->fetch_row($request);
		if ($minMsgID < 0 || $maxMsgID < 0)
			Utils::$context['search_errors']['no_messages_in_time_frame'] = true;
		Db::$db->free_result($request);
	}

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr(Utils::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"', '%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}

		if (empty($possible_users))
		{
			$userQuery = '';
		}
		else
		{
			// Create a list of database-escaped search names.
			$realNameMatches = array();

			foreach ($possible_users as $possible_user)
				$realNameMatches[] = Db::$db->quote(
					'{string:possible_user}',
					array(
						'possible_user' => $possible_user
					)
				);

			// Retrieve a list of possible members.
			$request = Db::$db->query('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE {raw:match_possible_users}',
				array(
					'match_possible_users' => 'real_name LIKE ' . implode(' OR real_name LIKE ', $realNameMatches),
				)
			);

			// Simply do nothing if there're too many members matching the criteria.
			if (Db::$db->num_rows($request) > $maxMembersToSearch)
			{
				$userQuery = '';
			}
			elseif (Db::$db->num_rows($request) == 0)
			{
				$userQuery = Db::$db->quote(
					'm.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})',
					array(
						'id_member_guest' => 0,
						'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
					)
				);
			}
			else
			{
				$memberlist = array();

				while ($row = Db::$db->fetch_assoc($request))
					$memberlist[] = $row['id_member'];

				$userQuery = Db::$db->quote(
					'(m.id_member IN ({array_int:matched_members}) OR (m.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})))',
					array(
						'matched_members' => $memberlist,
						'id_member_guest' => 0,
						'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
					)
				);
			}
			Db::$db->free_result($request);
		}
	}

	// If the boards were passed by URL (params=), temporarily put them back in $_REQUEST.
	if (!empty($search_params['brd']) && is_array($search_params['brd']))
		$_REQUEST['brd'] = $search_params['brd'];

	// Ensure that brd is an array.
	if ((!empty($_REQUEST['brd']) && !is_array($_REQUEST['brd'])) || (!empty($_REQUEST['search_selection']) && $_REQUEST['search_selection'] == 'board'))
	{
		if (!empty($_REQUEST['brd']))
		{
			$_REQUEST['brd'] = strpos($_REQUEST['brd'], ',') !== false ? explode(',', $_REQUEST['brd']) : array($_REQUEST['brd']);
		}
		else
			$_REQUEST['brd'] = isset($_REQUEST['sd_brd']) ? array($_REQUEST['sd_brd']) : array();
	}

	// Make sure all boards are integers.
	if (!empty($_REQUEST['brd']))
	{
		foreach ($_REQUEST['brd'] as $id => $brd)
			$_REQUEST['brd'][$id] = (int) $brd;
	}

	// Special case for boards: searching just one topic?
	if (!empty($search_params['topic']))
	{
		$request = Db::$db->query('', '
			SELECT t.id_board
			FROM {db_prefix}topics AS t
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_topic_board}' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved_true}' : '') . '
			LIMIT 1',
			array(
				'search_topic_id' => $search_params['topic'],
				'is_approved_true' => 1,
			)
		);

		if (Db::$db->num_rows($request) == 0)
			fatal_lang_error('topic_gone', false);

		$search_params['brd'] = array();
		list ($search_params['brd'][0]) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
	}
	// Select all boards you've selected AND are allowed to see.
	elseif (User::$me->is_admin && (!empty($search_params['advanced']) || !empty($_REQUEST['brd'])))
	{
		$search_params['brd'] = empty($_REQUEST['brd']) ? array() : $_REQUEST['brd'];
	}
	else
	{
		$see_board = empty($search_params['advanced']) ? 'query_wanna_see_board' : 'query_see_board';

		$request = Db::$db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {raw:boards_allowed_to_see}
				AND redirect = {string:empty_string}' . (empty($_REQUEST['brd']) ? (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board_id}' : '') : '
				AND b.id_board IN ({array_int:selected_search_boards})'),
			array(
				'boards_allowed_to_see' => User::$me->{$see_board},
				'empty_string' => '',
				'selected_search_boards' => empty($_REQUEST['brd']) ? array() : $_REQUEST['brd'],
				'recycle_board_id' => Config::$modSettings['recycle_board'],
			)
		);

		$search_params['brd'] = array();

		while ($row = Db::$db->fetch_assoc($request))
			$search_params['brd'][] = $row['id_board'];

		Db::$db->free_result($request);

		// This error should pro'bly only happen for hackers.
		if (empty($search_params['brd']))
			Utils::$context['search_errors']['no_boards_selected'] = true;
	}

	if (count($search_params['brd']) != 0)
	{
		foreach ($search_params['brd'] as $k => $v)
			$search_params['brd'][$k] = (int) $v;

		// If we've selected all boards, this parameter can be left empty.
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}boards
			WHERE redirect = {string:empty_string}',
			array(
				'empty_string' => '',
			)
		);

		list ($num_boards) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		if (count($search_params['brd']) == $num_boards)
		{
			$boardQuery = '';
		}
		elseif (count($search_params['brd']) == $num_boards - 1 && !empty(Config::$modSettings['recycle_board']) && !in_array(Config::$modSettings['recycle_board'], $search_params['brd']))
		{
			$boardQuery = '!= ' . Config::$modSettings['recycle_board'];
		}
		else
			$boardQuery = 'IN (' . implode(', ', $search_params['brd']) . ')';
	}
	else
		$boardQuery = '';

	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);

	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);

	Utils::$context['compact'] = !$search_params['show_complete'];

	// Get the sorting parameters right. Default to sort by relevance descending.
	$sort_columns = array(
		'relevance',
		'num_replies',
		'id_msg',
	);

	call_integration_hook('integrate_search_sort_columns', array(&$sort_columns));

	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
	{
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	}

	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'relevance';

	if (!empty($search_params['topic']) && $search_params['sort'] === 'num_replies')
		$search_params['sort'] = 'id_msg';

	// Sorting direction: descending unless stated otherwise.
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Remember current sort type and sort direction
	Utils::$context['current_sorting'] = $search_params['sort'] . '|' . $search_params['sort_dir'];

	// Determine some values needed to calculate the relevance.
	$minMsg = (int) ((1 - $recentPercentage) * Config::$modSettings['maxMsgID']);
	$recentMsg = Config::$modSettings['maxMsgID'] - $minMsg;

	// *** Parse the search query
	call_integration_hook('integrate_search_params', array(&$search_params));

	/*
	 * Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
	 *
	 * @todo Setting to add more here?
	 * @todo Maybe only blacklist if they are the only word, or "any" is used?
	 */
	$blacklisted_words = array('img', 'url', 'quote', 'www', 'http', 'the', 'is', 'it', 'are', 'if');

	call_integration_hook('integrate_search_blacklisted_words', array(&$blacklisted_words));

	// What are we searching for?
	if (empty($search_params['search']))
	{
		if (isset($_GET['search']))
			$search_params['search'] = un_htmlspecialchars($_GET['search']);

		elseif (isset($_POST['search']))
			$search_params['search'] = $_POST['search'];

		else
			$search_params['search'] = '';
	}

	// Nothing??
	if (!isset($search_params['search']) || $search_params['search'] == '')
	{
		Utils::$context['search_errors']['invalid_search_string'] = true;
	}
	// Too long?
	elseif (Utils::entityStrlen($search_params['search']) > Utils::$context['search_string_limit'])
	{
		Utils::$context['search_errors']['string_too_long'] = true;
	}

	// Change non-word characters into spaces.
	$stripped_query = preg_replace('~(?:[\x0B\0' . (Utils::$context['utf8'] ? '\x{A0}' : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', $search_params['search']);

	// Make the query lower case. It's gonna be case insensitive anyway.
	$stripped_query = un_htmlspecialchars(Utils::strtolower($stripped_query));

	// This (hidden) setting will do fulltext searching in the most basic way.
	if (!empty(Config::$modSettings['search_simple_fulltext']))
		$stripped_query = strtr($stripped_query, array('"' => ''));

	$no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;
	$is_search_regex = !empty(Config::$modSettings['search_match_words']) && !$no_regexp;

	// Specify the function to search with. Regex is for word boundaries.
	$query_match_type = $is_search_regex ? 'RLIKE' : 'LIKE';
	$word_boundary_wrapper = function(string $str): string
	{
		return sprintf(Db::$db->supports_pcre ? '\\b%s\\b' : '[[:<:]]%s[[:>:]]', $str);
	};
	$escape_sql_regex = function(string $str): string
	{
		return addcslashes(preg_replace('/[\[\]$.+*?&^|{}()]/', '[$0]', $str), '\\\'');
	};

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);

	$phraseArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$wordArray = preg_replace('~(?:^|\s)[-]?"[^"]+"(?:$|\s)~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', $search_params['search']);

	$wordArray = explode(' ', Utils::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

	// A minus sign in front of a word excludes the word.... so...
	$excludedWords = array();
	$excludedIndexWords = array();
	$excludedSubjectWords = array();
	$excludedPhrases = array();

	// .. first, we check for things like -"some words", but not "-some words".
	foreach ($matches[1] as $index => $word)
	{
		if ($word === '-')
		{
			if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($phraseArray[$index]);
		}
	}

	// Now we look for -test, etc.... normaller.
	foreach ($wordArray as $index => $word)
	{
		if (strpos(trim($word), '-') === 0)
		{
			if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;

			unset($wordArray[$index]);
		}
	}

	// The remaining words and phrases are all included.
	$searchArray = array_merge($phraseArray, $wordArray);

	Utils::$context['search_ignored'] = array();
	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		// Skip anything practically empty.
		if (($searchArray[$index] = trim($value, '-_\' ')) === '')
		{
			unset($searchArray[$index]);
		}
		// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
		elseif (in_array($searchArray[$index], $blacklisted_words))
		{
			$foundBlackListedWords = true;
			unset($searchArray[$index]);
		}
		// Don't allow very, very short words.
		elseif (Utils::entityStrlen($value) < 2)
		{
			Utils::$context['search_ignored'][] = $value;
			unset($searchArray[$index]);
		}
	}
	$searchArray = array_slice(array_unique($searchArray), 0, 10);

	// Create an array of replacements for highlighting.
	Utils::$context['mark'] = array();

	foreach ($searchArray as $word)
		Utils::$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

	// Initialize two arrays storing the words that have to be searched for.
	$orParts = array();
	$searchWords = array();

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
	{
		Utils::$context['search_errors']['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;
	}
	// All words/sentences must match.
	elseif (empty($search_params['searchtype']))
	{
		$orParts[0] = $searchArray;
	}
	// Any word/sentence must match.
	else
	{
		foreach ($searchArray as $index => $value)
			$orParts[$index] = array($value);
	}

	// Don't allow duplicate error messages if one string is too short.
	if (isset(Utils::$context['search_errors']['search_string_small_words'], Utils::$context['search_errors']['invalid_search_string']))
		unset(Utils::$context['search_errors']['invalid_search_string']);

	// Make sure the excluded words are in all or-branches.
	foreach ($orParts as $orIndex => $andParts)
		foreach ($excludedWords as $word)
			$orParts[$orIndex][] = $word;

	// Determine the or-branches and the fulltext search words.
	foreach ($orParts as $orIndex => $andParts)
	{
		$searchWords[$orIndex] = array(
			'indexed_words' => array(),
			'words' => array(),
			'subject_words' => array(),
			'all_words' => array(),
			'complex_words' => array(),
		);

		// Sort the indexed words (large words -> small words -> excluded words).
		if ($searchAPI->supportsMethod('searchSort'))
			usort($orParts[$orIndex], 'searchSort');

		foreach ($orParts[$orIndex] as $word)
		{
			$is_excluded = in_array($word, $excludedWords);

			$searchWords[$orIndex]['all_words'][] = $word;

			$subjectWords = text2words($word);
			if (!$is_excluded || count($subjectWords) === 1)
			{
				$searchWords[$orIndex]['subject_words'] = array_merge($searchWords[$orIndex]['subject_words'], $subjectWords);
				if ($is_excluded)
					$excludedSubjectWords = array_merge($excludedSubjectWords, $subjectWords);
			}
			else
				$excludedPhrases[] = $word;

			// Have we got indexes to prepare?
			if ($searchAPI->supportsMethod('prepareIndexes'))
				$searchAPI->prepareIndexes($word, $searchWords[$orIndex], $excludedIndexWords, $is_excluded);
		}

		// Search_force_index requires all AND parts to have at least one fulltext word.
		if (!empty(Config::$modSettings['search_force_index']) && empty($searchWords[$orIndex]['indexed_words']))
		{
			Utils::$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}
		elseif ($search_params['subject_only'] && empty($searchWords[$orIndex]['subject_words']) && empty($excludedSubjectWords))
		{
			Utils::$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}

		// Make sure we aren't searching for too many indexed words.
		else
		{
			$searchWords[$orIndex]['indexed_words'] = array_slice($searchWords[$orIndex]['indexed_words'], 0, 7);
			$searchWords[$orIndex]['subject_words'] = array_slice($searchWords[$orIndex]['subject_words'], 0, 7);
			$searchWords[$orIndex]['words'] = array_slice($searchWords[$orIndex]['words'], 0, 4);
		}
	}

	// *** Spell checking
	if (Utils::$context['show_spellchecking'])
	{
		// Don't hardcode spellchecking functions!
		$link = Msg::spell_init();

		$did_you_mean = array('search' => array(), 'display' => array());
		$found_misspelling = false;
		foreach ($searchArray as $word)
		{
			if (empty($link))
				continue;

			// Don't check phrases.
			if (preg_match('~^\w+$~', $word) === 0)
			{
				$did_you_mean['search'][] = '"' . $word . '"';
				$did_you_mean['display'][] = '&quot;' . Utils::htmlspecialchars($word) . '&quot;';
				continue;
			}
			// For some strange reason spell check can crash PHP on decimals.
			elseif (preg_match('~\d~', $word) === 1)
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = Utils::htmlspecialchars($word);
				continue;
			}
			elseif (Msg::spell_check($link, $word))
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = Utils::htmlspecialchars($word);
				continue;
			}

			$suggestions = Msg::spell_suggest($link, $word);
			foreach ($suggestions as $i => $s)
			{
				// Search is case insensitive.
				if (Utils::strtolower($s) == Utils::strtolower($word))
					unset($suggestions[$i]);

				// Plus, don't suggest something the user thinks is rude!
				elseif ($suggestions[$i] != Lang::censorText($s))
					unset($suggestions[$i]);
			}

			// Anything found?  If so, correct it!
			if (!empty($suggestions))
			{
				$suggestions = array_values($suggestions);
				$did_you_mean['search'][] = $suggestions[0];
				$did_you_mean['display'][] = '<em><strong>' . Utils::htmlspecialchars($suggestions[0]) . '</strong></em>';
				$found_misspelling = true;
			}
			else
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = Utils::htmlspecialchars($word);
			}
		}

		if ($found_misspelling)
		{
			// Don't spell check excluded words, but add them still...
			$temp_excluded = array('search' => array(), 'display' => array());

			foreach ($excludedWords as $word)
			{
				if (preg_match('~^\w+$~', $word) == 0)
				{
					$temp_excluded['search'][] = '-"' . $word . '"';
					$temp_excluded['display'][] = '-&quot;' . Utils::htmlspecialchars($word) . '&quot;';
				}
				else
				{
					$temp_excluded['search'][] = '-' . $word;
					$temp_excluded['display'][] = '-' . Utils::htmlspecialchars($word);
				}
			}

			$did_you_mean['search'] = array_merge($did_you_mean['search'], $temp_excluded['search']);
			$did_you_mean['display'] = array_merge($did_you_mean['display'], $temp_excluded['display']);

			$temp_params = $search_params;
			$temp_params['search'] = implode(' ', $did_you_mean['search']);

			if (isset($temp_params['brd']))
				$temp_params['brd'] = implode(',', $temp_params['brd']);

			Utils::$context['params'] = array();

			foreach ($temp_params as $k => $v)
				Utils::$context['did_you_mean_params'][] = $k . '|\'|' . $v;

			Utils::$context['did_you_mean_params'] = base64_encode(implode('|"|', Utils::$context['did_you_mean_params']));
			Utils::$context['did_you_mean'] = implode(' ', $did_you_mean['display']);
		}
	}

	// Let the user adjust the search query, should they wish?
	Utils::$context['search_params'] = $search_params;

	if (isset(Utils::$context['search_params']['search']))
		Utils::$context['search_params']['search'] = Utils::htmlspecialchars(Utils::$context['search_params']['search']);

	if (isset(Utils::$context['search_params']['userspec']))
		Utils::$context['search_params']['userspec'] = Utils::htmlspecialchars(Utils::$context['search_params']['userspec']);

	// Do we have captcha enabled?
	if (User::$me->is_guest && !empty(Config::$modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search']))
	{
		require_once(Config::$sourcedir . '/Subs-Editor.php');

		$verificationOptions = array(
			'id' => 'search',
		);

		Utils::$context['require_verification'] = create_control_verification($verificationOptions, true);

		if (is_array(Utils::$context['require_verification']))
		{
			foreach (Utils::$context['require_verification'] as $error)
				Utils::$context['search_errors'][$error] = true;
		}
		// Don't keep asking for it - they've proven themselves worthy.
		else
			$_SESSION['ss_vv_passed'] = true;
	}

	// *** Encode all search params

	// All search params have been checked, let's compile them to a single string... made less simple by PHP 4.3.9 and below.
	$temp_params = $search_params;
	if (isset($temp_params['brd']))
		$temp_params['brd'] = implode(',', $temp_params['brd']);

	Utils::$context['params'] = array();

	foreach ($temp_params as $k => $v)
		Utils::$context['params'][] = $k . '|\'|' . $v;

	if (!empty(Utils::$context['params']))
	{
		// Due to old IE's 2083 character limit, we have to compress long search strings
		$params = @gzcompress(implode('|"|', Utils::$context['params']));

		// Gzcompress failed, use try non-gz
		if (empty($params))
			$params = implode('|"|', Utils::$context['params']);

		// Base64 encode, then replace +/= with uri safe ones that can be reverted
		Utils::$context['params'] = str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode($params));
	}

	// ... and add the links to the link tree.
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=search;params=' . Utils::$context['params'],
		'name' => Lang::$txt['search']
	);
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=search2;params=' . Utils::$context['params'],
		'name' => Lang::$txt['search_results']
	);

	// *** A last error check
	call_integration_hook('integrate_search_errors');

	// One or more search errors? Go back to the first search screen.
	if (!empty(Utils::$context['search_errors']))
	{
		$_REQUEST['params'] = Utils::$context['params'];
		return PlushSearch1();
	}

	// Spam me not, Spam-a-lot?
	if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search'])
		spamProtection('search');

	// Store the last search string to allow pages of results to be browsed.
	$_SESSION['last_ss'] = $search_params['search'];

	// *** Reserve an ID for caching the search results.
	$query_params = array_merge($search_params, array(
		'min_msg_id' => isset($minMsgID) ? (int) $minMsgID : 0,
		'max_msg_id' => isset($maxMsgID) ? (int) $maxMsgID : 0,
		'memberlist' => !empty($memberlist) ? $memberlist : array(),
	));

	// Can this search rely on the API given the parameters?
	if ($searchAPI->supportsMethod('searchQuery', $query_params))
	{
		$participants = array();
		$searchArray = array();

		$searchAPI->searchQuery($query_params, $searchWords, $excludedIndexWords, $participants, $searchArray);
	}

	// Update the cache if the current search term is not yet cached.
	else
	{
		$update_cache = empty($_SESSION['search_cache']) || ($_SESSION['search_cache']['params'] != Utils::$context['params']);

		// Are the result fresh?
		if (!$update_cache && !empty($_SESSION['search_cache']['id_search']))
		{
			$request = Db::$db->query('', '
				SELECT id_search
				FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}
				LIMIT 1',
				array(
					'search_id' => $_SESSION['search_cache']['id_search'],
				)
			);

			if (Db::$db->num_rows($request) === 0)
				$update_cache = true;
		}

		if ($update_cache)
		{
			// Increase the pointer...
			Config::$modSettings['search_pointer'] = empty(Config::$modSettings['search_pointer']) ? 0 : (int) Config::$modSettings['search_pointer'];

			// ...and store it right off.
			Config::updateModSettings(array('search_pointer' => Config::$modSettings['search_pointer'] >= 255 ? 0 : Config::$modSettings['search_pointer'] + 1));

			// As long as you don't change the parameters, the cache result is yours.
			$_SESSION['search_cache'] = array(
				'id_search' => Config::$modSettings['search_pointer'],
				'num_results' => -1,
				'params' => Utils::$context['params'],
			);

			// Clear the previous cache of the final results cache.
			Db::$db->search_query('delete_log_search_results', '
				DELETE FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}',
				array(
					'search_id' => $_SESSION['search_cache']['id_search'],
				)
			);

			if ($search_params['subject_only'])
			{
				// We do this to try and avoid duplicate keys on databases not supporting INSERT IGNORE.
				$inserts = array();

				foreach ($searchWords as $orIndex => $words)
				{
					$subject_query_params = array(
						'not_redirected' => 0,
						'never_expires' => 0,
					);
					$subject_query = array(
						'from' => '{db_prefix}topics AS t',
						'inner_join' => array(),
						'left_join' => array(),
						'where' => array(
							't.id_redirect_topic = {int:not_redirected}',
						    't.redirect_expires = {int:never_expires}',
						),
					);

					if (Config::$modSettings['postmod_active'])
						$subject_query['where'][] = 't.approved = {int:is_approved}';

					$numTables = 0;
					$prev_join = 0;
					$numSubjectResults = 0;

					foreach ($words['subject_words'] as $subjectWord)
					{
						$numTables++;

						if (in_array($subjectWord, $excludedSubjectWords))
						{
							$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty(Config::$modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';

							$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
						}
						else
						{
							$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';

							$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty(Config::$modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');

							$prev_join = $numTables;
						}

						$subject_query_params['subject_words_' . $numTables] = $subjectWord;
						$subject_query_params['subject_words_' . $numTables . '_wild'] = '%' . $subjectWord . '%';
					}

					if (!empty($userQuery))
					{
						if ($subject_query['from'] != '{db_prefix}messages AS m')
						{
							$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_topic = t.id_topic)';
						}

						$subject_query['where'][] = $userQuery;
					}

					if (!empty($search_params['topic']))
						$subject_query['where'][] = 't.id_topic = ' . $search_params['topic'];

					if (!empty($minMsgID))
						$subject_query['where'][] = 't.id_first_msg >= ' . $minMsgID;

					if (!empty($maxMsgID))
						$subject_query['where'][] = 't.id_last_msg <= ' . $maxMsgID;

					if (!empty($boardQuery))
						$subject_query['where'][] = 't.id_board ' . $boardQuery;

					if (!empty($excludedPhrases))
					{
						if ($subject_query['from'] != '{db_prefix}messages AS m')
						{
							$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
						}

						$count = 0;

						foreach ($excludedPhrases as $phrase)
						{
							$subject_query['where'][] = 'm.subject NOT ' . $query_match_type . ' {string:excluded_phrases_' . $count . '}';

							if ($is_search_regex)
								$subject_query_params['excluded_phrases_' . $count++] = $word_boundary_wrapper($escape_sql_regex($phrase));
							else
								$subject_query_params['excluded_phrases_' . $count++] = '%' . Db::$db->escape_wildcard_string($phrase) . '%';
						}
					}

					call_integration_hook('integrate_subject_only_search_query', array(&$subject_query, &$subject_query_params));

					$relevance = '1000 * (';

					foreach ($weight_factors as $type => $value)
					{
						$relevance .= $weight[$type];

						if (!empty($value['results']))
							$relevance .= ' * ' . $value['results'];

						$relevance .= ' + ';
					}

					$relevance = substr($relevance, 0, -3) . ') / ' . $weight_total . ' AS relevance';

					$ignoreRequest = Db::$db->search_query('insert_log_search_results_subject',
						(Db::$db->support_ignore ? '
						INSERT IGNORE INTO {db_prefix}log_search_results
							(id_search, id_topic, relevance, id_msg, num_matches)' : '') . '
						SELECT
							{int:id_search},
							t.id_topic,
							' . $relevance . ',
							' . (empty($userQuery) ? 't.id_first_msg' : 'm.id_msg') . ',
							1
						FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
							INNER JOIN ' . implode('
							INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
							LEFT JOIN ' . implode('
							LEFT JOIN ', $subject_query['left_join'])) . '
						WHERE ' . implode('
							AND ', $subject_query['where']) . (empty(Config::$modSettings['search_max_results']) ? '' : '
						LIMIT ' . (Config::$modSettings['search_max_results'] - $numSubjectResults)),
						array_merge($subject_query_params, array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
							'is_approved' => 1,
						))
					);

					// If the database doesn't support IGNORE to make this fast we need to do some tracking.
					if (!Db::$db->support_ignore)
					{
						while ($row = Db::$db->fetch_row($ignoreRequest))
						{
							// No duplicates!
							if (isset($inserts[$row[1]]))
								continue;

							foreach ($row as $key => $value)
								$inserts[$row[1]][] = (int) $row[$key];
						}
						Db::$db->free_result($ignoreRequest);
						$numSubjectResults = count($inserts);
					}
					else
						$numSubjectResults += Db::$db->affected_rows();

					if (!empty(Config::$modSettings['search_max_results']) && $numSubjectResults >= Config::$modSettings['search_max_results'])
						break;
				}

				// If there's data to be inserted for non-IGNORE databases do it here!
				if (!empty($inserts))
				{
					Db::$db->insert('',
						'{db_prefix}log_search_results',
						array('id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'int', 'id_msg' => 'int', 'num_matches' => 'int'),
						$inserts,
						array('id_search', 'id_topic')
					);
				}

				$_SESSION['search_cache']['num_results'] = $numSubjectResults;
			}
			else
			{
				$main_query = array(
					'select' => array(
						'id_search' => $_SESSION['search_cache']['id_search'],
						'relevance' => '0',
					),
					'weights' => array(),
					'from' => '{db_prefix}topics AS t',
					'inner_join' => array(
						'{db_prefix}messages AS m ON (m.id_topic = t.id_topic)'
					),
					'left_join' => array(),
					'where' => array(
						't.id_redirect_topic = {int:not_redirected}',
						't.redirect_expires = {int:never_expires}',
					),
					'group_by' => array(),
					'parameters' => array(
						'min_msg' => $minMsg,
						'recent_message' => $recentMsg,
						'huge_topic_posts' => $humungousTopicPosts,
						'is_approved' => 1,
						'not_redirected' => 0,
						'never_expires' => 0,
					),
				);

				if (empty($search_params['topic']) && empty($search_params['show_complete']))
				{
					$main_query['select']['id_topic'] = 't.id_topic';
					$main_query['select']['id_msg'] = 'MAX(m.id_msg) AS id_msg';
					$main_query['select']['num_matches'] = 'COUNT(*) AS num_matches';

					$main_query['weights'] = $weight_factors;

					$main_query['group_by'][] = 't.id_topic';
				}
				else
				{
					// This is outrageous!
					$main_query['select']['id_topic'] = 'm.id_msg AS id_topic';
					$main_query['select']['id_msg'] = 'm.id_msg';
					$main_query['select']['num_matches'] = '1 AS num_matches';

					$main_query['weights'] = array(
						'age' => array(
							'search' => '((m.id_msg - t.id_first_msg) / CASE WHEN t.id_last_msg = t.id_first_msg THEN 1 ELSE t.id_last_msg - t.id_first_msg END)',
						),
						'first_message' => array(
							'search' => 'CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END',
						),
					);

					if (!empty($search_params['topic']))
					{
						$main_query['where'][] = 't.id_topic = {int:topic}';
						$main_query['parameters']['topic'] = $search_params['topic'];
					}
					if (!empty($search_params['show_complete']))
						$main_query['group_by'][] = 'm.id_msg, t.id_first_msg, t.id_last_msg';
				}

				// *** Get the subject results.
				$numSubjectResults = 0;
				if (empty($search_params['topic']))
				{
					$inserts = array();
					// Create a temporary table to store some preliminary results in.
					Db::$db->search_query('drop_tmp_log_search_topics', '
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_topics',
						array(
						)
					);
					$createTemporary = Db::$db->search_query('create_tmp_log_search_topics', '
						CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_topics (
							id_topic int NOT NULL default {string:string_zero},
							PRIMARY KEY (id_topic)
						) ENGINE=MEMORY',
						array(
							'string_zero' => '0',
						)
					) !== false;

					// Clean up some previous cache.
					if (!$createTemporary)
						Db::$db->search_query('delete_log_search_topics', '
							DELETE FROM {db_prefix}log_search_topics
							WHERE id_search = {int:search_id}',
							array(
								'search_id' => $_SESSION['search_cache']['id_search'],
							)
						);

					foreach ($searchWords as $orIndex => $words)
					{
						$subject_query = array(
							'from' => '{db_prefix}topics AS t',
							'inner_join' => array(),
							'left_join' => array(),
							'where' => array(
								't.id_redirect_topic = {int:not_redirected}',
								't.redirect_expires = {int:never_expires}',
							),
							'params' => array(
								'not_redirected' => 0,
								'never_expires' => 0,
							),
						);

						$numTables = 0;
						$prev_join = 0;
						$count = 0;
						$excluded = false;
						foreach ($words['subject_words'] as $subjectWord)
						{
							$numTables++;
							if (in_array($subjectWord, $excludedSubjectWords))
							{
								if (($subject_query['from'] != '{db_prefix}messages AS m') && !$excluded)
								{
									$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
									$excluded = true;
								}
								$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty(Config::$modSettings['search_match_words']) ? 'LIKE {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
								$subject_query['params']['subject_not_' . $count] = empty(Config::$modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

								$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
								$subject_query['where'][] = 'm.body NOT ' . $query_match_type . ' {string:body_not_' . $count . '}';

								if ($is_search_regex)
									$subject_query['params']['body_not_' . $count++] = $word_boundary_wrapper($escape_sql_regex($subjectWord));
								else
									$subject_query['params']['body_not_' . $count++] = '%' . Db::$db->escape_wildcard_string($subjectWord) . '%';
							}
							else
							{
								$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
								$subject_query['where'][] = 'subj' . $numTables . '.word LIKE {string:subject_like_' . $count . '}';
								$subject_query['params']['subject_like_' . $count++] = empty(Config::$modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;
								$prev_join = $numTables;
							}
						}

						if (!empty($userQuery))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
							{
								$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
							}
							$subject_query['where'][] = '{raw:user_query}';
							$subject_query['params']['user_query'] = $userQuery;
						}
						if (!empty($search_params['topic']))
						{
							$subject_query['where'][] = 't.id_topic = {int:topic}';
							$subject_query['params']['topic'] = $search_params['topic'];
						}
						if (!empty($minMsgID))
						{
							$subject_query['where'][] = 't.id_first_msg >= {int:min_msg_id}';
							$subject_query['params']['min_msg_id'] = $minMsgID;
						}
						if (!empty($maxMsgID))
						{
							$subject_query['where'][] = 't.id_last_msg <= {int:max_msg_id}';
							$subject_query['params']['max_msg_id'] = $maxMsgID;
						}
						if (!empty($boardQuery))
						{
							$subject_query['where'][] = 't.id_board {raw:board_query}';
							$subject_query['params']['board_query'] = $boardQuery;
						}
						if (!empty($excludedPhrases))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
							{
								$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
							}
							$count = 0;
							foreach ($excludedPhrases as $phrase)
							{
								$subject_query['where'][] = 'm.subject NOT ' . $query_match_type . ' {string:exclude_phrase_' . $count . '}';
								$subject_query['where'][] = 'm.body NOT ' . $query_match_type . ' {string:exclude_phrase_' . $count . '}';

								if ($is_search_regex)
									$subject_query['params']['exclude_phrase_' . $count++] = $word_boundary_wrapper($escape_sql_regex($phrase));
								else
									$subject_query['params']['exclude_phrase_' . $count++] = '%' . Db::$db->escape_wildcard_string($phrase) . '%';
							}
						}
						call_integration_hook('integrate_subject_search_query', array(&$subject_query));

						// Nothing to search for?
						if (empty($subject_query['where']))
							continue;

						$ignoreRequest = Db::$db->search_query('insert_log_search_topics', (Db::$db->support_ignore ? ('
							INSERT IGNORE INTO {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics
								(' . ($createTemporary ? '' : 'id_search, ') . 'id_topic)') : '') . '
							SELECT ' . ($createTemporary ? '' : $_SESSION['search_cache']['id_search'] . ', ') . 't.id_topic
							FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
								INNER JOIN ' . implode('
								INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
								LEFT JOIN ' . implode('
								LEFT JOIN ', $subject_query['left_join'])) . '
							WHERE ' . implode('
								AND ', $subject_query['where']) . (empty(Config::$modSettings['search_max_results']) ? '' : '
							LIMIT ' . (Config::$modSettings['search_max_results'] - $numSubjectResults)),
							$subject_query['params']
						);
						// Don't do INSERT IGNORE? Manually fix this up!
						if (!Db::$db->support_ignore)
						{
							while ($row = Db::$db->fetch_row($ignoreRequest))
							{
								$ind = $createTemporary ? 0 : 1;
								// No duplicates!
								if (isset($inserts[$row[$ind]]))
									continue;

								$inserts[$row[$ind]] = $row;
							}
							Db::$db->free_result($ignoreRequest);
							$numSubjectResults = count($inserts);
						}
						else
							$numSubjectResults += Db::$db->affected_rows();

						if (!empty(Config::$modSettings['search_max_results']) && $numSubjectResults >= Config::$modSettings['search_max_results'])
							break;
					}

					// Got some non-MySQL data to plonk in?
					if (!empty($inserts))
					{
						Db::$db->insert('',
							('{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics'),
							$createTemporary ? array('id_topic' => 'int') : array('id_search' => 'int', 'id_topic' => 'int'),
							$inserts,
							$createTemporary ? array('id_topic') : array('id_search', 'id_topic')
						);
					}

					if ($numSubjectResults !== 0)
					{
						$main_query['weights']['subject']['search'] = 'CASE WHEN MAX(lst.id_topic) IS NULL THEN 0 ELSE 1 END';
						$main_query['left_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (' . ($createTemporary ? '' : 'lst.id_search = {int:id_search} AND ') . 'lst.id_topic = t.id_topic)';
						if (!$createTemporary)
							$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
					}
				}

				$indexedResults = 0;
				// We building an index?
				if ($searchAPI->supportsMethod('indexedWordQuery', $query_params))
				{
					$inserts = array();
					Db::$db->search_query('drop_tmp_log_search_messages', '
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_messages',
						array(
						)
					);

					$createTemporary = Db::$db->search_query('create_tmp_log_search_messages', '
						CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_messages (
							id_msg int NOT NULL default {string:string_zero},
							PRIMARY KEY (id_msg)
						) ENGINE=MEMORY',
						array(
							'string_zero' => '0',
						)
					) !== false;

					// Clear, all clear!
					if (!$createTemporary)
						Db::$db->search_query('delete_log_search_messages', '
							DELETE FROM {db_prefix}log_search_messages
							WHERE id_search = {int:id_search}',
							array(
								'id_search' => $_SESSION['search_cache']['id_search'],
							)
						);

					foreach ($searchWords as $orIndex => $words)
					{
						// Search for this word, assuming we have some words!
						if (!empty($words['indexed_words']))
						{
							// Variables required for the search.
							$search_data = array(
								'insert_into' => ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
								'no_regexp' => $no_regexp,
								'max_results' => $maxMessageResults,
								'indexed_results' => $indexedResults,
								'params' => array(
									'id_search' => !$createTemporary ? $_SESSION['search_cache']['id_search'] : 0,
									'excluded_words' => $excludedWords,
									'user_query' => !empty($userQuery) ? $userQuery : '',
									'board_query' => !empty($boardQuery) ? $boardQuery : '',
									'topic' => !empty($search_params['topic']) ? $search_params['topic'] : 0,
									'min_msg_id' => !empty($minMsgID) ? $minMsgID : 0,
									'max_msg_id' => !empty($maxMsgID) ? $maxMsgID : 0,
									'excluded_phrases' => !empty($excludedPhrases) ? $excludedPhrases : array(),
									'excluded_index_words' => !empty($excludedIndexWords) ? $excludedIndexWords : array(),
									'excluded_subject_words' => !empty($excludedSubjectWords) ? $excludedSubjectWords : array(),
								),
							);

							$ignoreRequest = $searchAPI->indexedWordQuery($words, $search_data);

							if (!Db::$db->support_ignore)
							{
								while ($row = Db::$db->fetch_row($ignoreRequest))
								{
									// No duplicates!
									if (isset($inserts[$row[0]]))
										continue;

									$inserts[$row[0]] = $row;
								}
								Db::$db->free_result($ignoreRequest);
								$indexedResults = count($inserts);
							}
							else
								$indexedResults += Db::$db->affected_rows();

							if (!empty($maxMessageResults) && $indexedResults >= $maxMessageResults)
								break;
						}
					}

					// More non-MySQL stuff needed?
					if (!empty($inserts))
					{
						Db::$db->insert('',
							'{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
							$createTemporary ? array('id_msg' => 'int') : array('id_msg' => 'int', 'id_search' => 'int'),
							$inserts,
							$createTemporary ? array('id_msg') : array('id_msg', 'id_search')
						);
					}

					if (empty($indexedResults) && empty($numSubjectResults) && !empty(Config::$modSettings['search_force_index']))
					{
						Utils::$context['search_errors']['query_not_specific_enough'] = true;
						$_REQUEST['params'] = Utils::$context['params'];
						return PlushSearch1();
					}
					elseif (!empty($indexedResults))
					{
						$main_query['inner_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages AS lsm ON (lsm.id_msg = m.id_msg)';
						if (!$createTemporary)
						{
							$main_query['where'][] = 'lsm.id_search = {int:id_search}';
							$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
						}
					}
				}

				// Not using an index? All conditions have to be carried over.
				else
				{
					$orWhere = array();
					$count = 0;
					foreach ($searchWords as $orIndex => $words)
					{
						$where = array();
						foreach ($words['all_words'] as $regularWord)
						{
							if (in_array($regularWord, $excludedWords))
							{
								$where[] = 'm.subject NOT ' . $query_match_type . ' {string:all_word_body_' . $count . '}';
								$where[] = 'm.body NOT ' . $query_match_type . ' {string:all_word_body_' . $count . '}';
							}
							else
								$where[] = 'm.body ' . $query_match_type . ' {string:all_word_body_' . $count . '}';

							if ($is_search_regex)
								$main_query['parameters']['all_word_body_' . $count++] = $word_boundary_wrapper($escape_sql_regex($regularWord));
							else
								$main_query['parameters']['all_word_body_' . $count++] = '%' . Db::$db->escape_wildcard_string($regularWord) . '%';
						}
						if (!empty($where))
							$orWhere[] = count($where) > 1 ? '(' . implode(' AND ', $where) . ')' : $where[0];
					}
					if (!empty($orWhere))
						$main_query['where'][] = count($orWhere) > 1 ? '(' . implode(' OR ', $orWhere) . ')' : $orWhere[0];

					if (!empty($userQuery))
					{
						$main_query['where'][] = '{raw:user_query}';
						$main_query['parameters']['user_query'] = $userQuery;
					}
					if (!empty($search_params['topic']))
					{
						$main_query['where'][] = 'm.id_topic = {int:topic}';
						$main_query['parameters']['topic'] = $search_params['topic'];
					}
					if (!empty($minMsgID))
					{
						$main_query['where'][] = 'm.id_msg >= {int:min_msg_id}';
						$main_query['parameters']['min_msg_id'] = $minMsgID;
					}
					if (!empty($maxMsgID))
					{
						$main_query['where'][] = 'm.id_msg <= {int:max_msg_id}';
						$main_query['parameters']['max_msg_id'] = $maxMsgID;
					}
					if (!empty($boardQuery))
					{
						$main_query['where'][] = 'm.id_board {raw:board_query}';
						$main_query['parameters']['board_query'] = $boardQuery;
					}
				}
				call_integration_hook('integrate_main_search_query', array(&$main_query));

				// Did we either get some indexed results, or otherwise did not do an indexed query?
				if (!empty($indexedResults) || !$searchAPI->supportsMethod('indexedWordQuery', $query_params))
				{
					$relevance = '1000 * (';
					$new_weight_total = 0;
					foreach ($main_query['weights'] as $type => $value)
					{
						$relevance .= $weight[$type];
						if (!empty($value['search']))
							$relevance .= ' * ' . $value['search'];
						$relevance .= ' + ';
						$new_weight_total += $weight[$type];
					}
					$main_query['select']['relevance'] = substr($relevance, 0, -3) . ') / ' . $new_weight_total . ' AS relevance';

					$ignoreRequest = Db::$db->search_query('insert_log_search_results_no_index', (Db::$db->support_ignore ? ('
						INSERT IGNORE INTO ' . '{db_prefix}log_search_results
							(' . implode(', ', array_keys($main_query['select'])) . ')') : '') . '
						SELECT
							' . implode(',
							', $main_query['select']) . '
						FROM ' . $main_query['from'] . (empty($main_query['inner_join']) ? '' : '
							INNER JOIN ' . implode('
							INNER JOIN ', $main_query['inner_join'])) . (empty($main_query['left_join']) ? '' : '
							LEFT JOIN ' . implode('
							LEFT JOIN ', $main_query['left_join'])) . (!empty($main_query['where']) ? '
						WHERE ' : '') . implode('
							AND ', $main_query['where']) . (empty($main_query['group_by']) ? '' : '
						GROUP BY ' . implode(', ', $main_query['group_by'])) . (empty(Config::$modSettings['search_max_results']) ? '' : '
						LIMIT ' . Config::$modSettings['search_max_results']),
						$main_query['parameters']
					);

					// We love to handle non-good databases that don't support our ignore!
					if (!Db::$db->support_ignore)
					{
						$inserts = array();
						while ($row = Db::$db->fetch_row($ignoreRequest))
						{
							// No duplicates!
							if (isset($inserts[$row[2]]))
								continue;

							foreach ($row as $key => $value)
								$inserts[$row[2]][] = (int) $row[$key];
						}
						Db::$db->free_result($ignoreRequest);

						// Now put them in!
						if (!empty($inserts))
						{
							$query_columns = array();
							foreach ($main_query['select'] as $k => $v)
								$query_columns[$k] = 'int';

							Db::$db->insert('',
								'{db_prefix}log_search_results',
								$query_columns,
								$inserts,
								array('id_search', 'id_topic')
							);
						}
						$_SESSION['search_cache']['num_results'] += count($inserts);
					}
					else
						$_SESSION['search_cache']['num_results'] = Db::$db->affected_rows();
				}

				// Insert subject-only matches.
				if ($_SESSION['search_cache']['num_results'] < Config::$modSettings['search_max_results'] && $numSubjectResults !== 0)
				{
					$relevance = '1000 * (';
					foreach ($weight_factors as $type => $value)
						if (isset($value['results']))
						{
							$relevance .= $weight[$type];
							if (!empty($value['results']))
								$relevance .= ' * ' . $value['results'];
							$relevance .= ' + ';
						}
					$relevance = substr($relevance, 0, -3) . ') / ' . $weight_total . ' AS relevance';

					$usedIDs = array_flip(empty($inserts) ? array() : array_keys($inserts));
					$ignoreRequest = Db::$db->search_query('insert_log_search_results_sub_only', (Db::$db->support_ignore ? ('
						INSERT IGNORE INTO {db_prefix}log_search_results
							(id_search, id_topic, relevance, id_msg, num_matches)') : '') . '
						SELECT
							{int:id_search},
							t.id_topic,
							' . $relevance . ',
							t.id_first_msg,
							1
						FROM {db_prefix}topics AS t
							INNER JOIN {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (lst.id_topic = t.id_topic)'
						. ($createTemporary ? '' : ' WHERE lst.id_search = {int:id_search}')
						. (empty(Config::$modSettings['search_max_results']) ? '' : '
						LIMIT ' . (Config::$modSettings['search_max_results'] - $_SESSION['search_cache']['num_results'])),
						array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
						)
					);
					// Once again need to do the inserts if the database don't support ignore!
					if (!Db::$db->support_ignore)
					{
						$inserts = array();
						while ($row = Db::$db->fetch_row($ignoreRequest))
						{
							// No duplicates!
							if (isset($usedIDs[$row[1]]))
								continue;

							$usedIDs[$row[1]] = true;
							$inserts[] = $row;
						}
						Db::$db->free_result($ignoreRequest);

						// Now put them in!
						if (!empty($inserts))
						{
							Db::$db->insert('',
								'{db_prefix}log_search_results',
								array('id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'float', 'id_msg' => 'int', 'num_matches' => 'int'),
								$inserts,
								array('id_search', 'id_topic')
							);
						}
						$_SESSION['search_cache']['num_results'] += count($inserts);
					}
					else
						$_SESSION['search_cache']['num_results'] += Db::$db->affected_rows();
				}
				elseif ($_SESSION['search_cache']['num_results'] == -1)
					$_SESSION['search_cache']['num_results'] = 0;
			}
		}

		$approve_query = '';
		if (!empty(Config::$modSettings['postmod_active']))
		{
			// Exclude unapproved topics, but show ones they started.
			if (empty(User::$me->mod_cache['ap']))
				$approve_query = '
				AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member})';

			// Show unapproved topics in boards they have access to.
			elseif (User::$me->mod_cache['ap'] !== array(0))
				$approve_query = '
				AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member} OR t.id_board IN ({array_int:approve_boards}))';
		}

		// *** Retrieve the results to be shown on the page
		$participants = array();
		$request = Db::$db->search_query('', '
			SELECT ' . (empty($search_params['topic']) ? 'lsr.id_topic' : $search_params['topic'] . ' AS id_topic') . ', lsr.id_msg, lsr.relevance, lsr.num_matches
			FROM {db_prefix}log_search_results AS lsr' . ($search_params['sort'] == 'num_replies' || !empty($approve_query) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = lsr.id_topic)' : '') . '
			WHERE lsr.id_search = {int:id_search}' . $approve_query . '
			ORDER BY {raw:sort} {raw:sort_dir}
			LIMIT {int:start}, {int:max}',
			array(
				'id_search' => $_SESSION['search_cache']['id_search'],
				'sort' => $search_params['sort'],
				'sort_dir' => $search_params['sort_dir'],
				'start' => $_REQUEST['start'],
				'max' => Config::$modSettings['search_results_per_page'],
				'is_approved' => 1,
				'current_member' => User::$me->id,
				'approve_boards' => !empty(Config::$modSettings['postmod_active']) ? User::$me->mod_cache['ap'] : array(),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['topics'][$row['id_msg']] = array(
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => array(),
			);
			// By default they didn't participate in the topic!
			$participants[$row['id_topic']] = false;
		}
		Db::$db->free_result($request);
	}

	$num_results = 0;
	if (!empty(Utils::$context['topics']))
	{
		// Create an array for the permissions.
		$perms = array('post_reply_own', 'post_reply_any');

		if (!empty(Theme::$current->options['display_quick_mod']))
			$perms = array_merge($perms, array('lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any'));

		$boards_can = boardsAllowedTo($perms, true, false);

		// How's about some quick moderation?
		if (!empty(Theme::$current->options['display_quick_mod']))
		{
			Utils::$context['can_lock'] = in_array(0, $boards_can['lock_any']);
			Utils::$context['can_sticky'] = in_array(0, $boards_can['make_sticky']);
			Utils::$context['can_move'] = in_array(0, $boards_can['move_any']);
			Utils::$context['can_remove'] = in_array(0, $boards_can['remove_any']);
			Utils::$context['can_merge'] = in_array(0, $boards_can['merge_any']);
		}

		$approve_query = '';
		if (!empty(Config::$modSettings['postmod_active']))
		{
			if (empty(User::$me->mod_cache['ap']))
				$approve_query = '
				AND (m.approved = {int:is_approved} OR m.id_member = {int:current_member})';

			elseif (User::$me->mod_cache['ap'] !== array(0))
				$approve_query = '
				AND (m.approved = {int:is_approved} OR m.id_member = {int:current_member} OR m.id_board IN ({array_int:approve_boards}))';
		}

		// What messages are we using?
		$msg_list = array_keys(Utils::$context['topics']);

		// Load the posters...
		$request = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_member != {int:no_member}
				AND id_msg IN ({array_int:message_list})
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'no_member' => 0,
				'limit' => count(Utils::$context['topics']),
			)
		);
		$posters = array();
		while ($row = Db::$db->fetch_assoc($request))
			$posters[] = $row['id_member'];
		Db::$db->free_result($request);

		call_integration_hook('integrate_search_message_list', array(&$msg_list, &$posters));

		if (!empty($posters))
			User::load(array_unique($posters));

		// Get the messages out for the callback - select enough that it can be made to look just like Display.
		$messages_request = Db::$db->query('', '
			SELECT
				m.id_msg, m.subject, m.poster_name, m.poster_email, m.poster_time, m.id_member,
				m.icon, m.poster_ip, m.body, m.smileys_enabled, m.modified_time, m.modified_name,
				first_m.id_msg AS first_msg, first_m.subject AS first_subject, first_m.icon AS first_icon, first_m.poster_time AS first_poster_time,
				first_mem.id_member AS first_member_id, COALESCE(first_mem.real_name, first_m.poster_name) AS first_member_name,
				last_m.id_msg AS last_msg, last_m.poster_time AS last_poster_time, last_mem.id_member AS last_member_id,
				COALESCE(last_mem.real_name, last_m.poster_name) AS last_member_name, last_m.icon AS last_icon, last_m.subject AS last_subject,
				t.id_topic, t.is_sticky, t.locked, t.id_poll, t.num_replies, t.num_views,
				b.id_board, b.name AS board_name, c.id_cat, c.name AS cat_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				INNER JOIN {db_prefix}messages AS first_m ON (first_m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS last_m ON (last_m.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS first_mem ON (first_mem.id_member = first_m.id_member)
				LEFT JOIN {db_prefix}members AS last_mem ON (last_mem.id_member = first_m.id_member)
			WHERE m.id_msg IN ({array_int:message_list})' . $approve_query . '
			ORDER BY ' . Db::$db->custom_order('m.id_msg', $msg_list) . '
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'is_approved' => 1,
				'current_member' => User::$me->id,
				'approve_boards' => !empty(Config::$modSettings['postmod_active']) ? User::$me->mod_cache['ap'] : array(),
				'limit' => count(Utils::$context['topics']),
			)
		);

		// How many results will the user be able to see?
		if (!empty($_SESSION['search_cache']['num_results']))
			$num_results = $_SESSION['search_cache']['num_results'];
		else
			$num_results = Db::$db->num_rows($messages_request);

		// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
		if ($num_results == 0)
			Utils::$context['topics'] = array();

		// If we want to know who participated in what then load this now.
		if (!empty(Config::$modSettings['enableParticipation']) && !User::$me->is_guest)
		{
			$result = Db::$db->query('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT {int:limit}',
				array(
					'current_member' => User::$me->id,
					'topic_list' => array_keys($participants),
					'limit' => count($participants),
				)
			);
			while ($row = Db::$db->fetch_assoc($result))
				$participants[$row['id_topic']] = true;

			Db::$db->free_result($result);
		}
	}

	// Now that we know how many results to expect we can start calculating the page numbers.
	Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=search2;params=' . Utils::$context['params'], $_REQUEST['start'], $num_results, Config::$modSettings['search_results_per_page'], false);

	// Consider the search complete!
	if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
		CacheApi::put('search_start:' . (User::$me->is_guest ? User::$me->ip : User::$me->id), null, 90);

	Utils::$context['key_words'] = &$searchArray;

	// Setup the default topic icons... for checking they exist and the like!
	Utils::$context['icon_sources'] = array();
	foreach (Utils::$context['stable_icons'] as $icon)
		Utils::$context['icon_sources'][$icon] = 'images_url';

	Utils::$context['sub_template'] = 'results';
	Utils::$context['page_title'] = Lang::$txt['search_results'];
	Utils::$context['get_topics'] = 'prepareSearchContext';
	Utils::$context['can_restore_perm'] = allowedTo('move_any') && !empty(Config::$modSettings['recycle_enable']);
	Utils::$context['can_restore'] = false; // We won't know until we handle the context later whether we can actually restore...

	Utils::$context['jump_to'] = array(
		'label' => addslashes(un_htmlspecialchars(Lang::$txt['jump_to'])),
		'board_name' => addslashes(un_htmlspecialchars(Lang::$txt['select_destination'])),
	);
}

/**
 * Callback to return messages - saves memory.
 *
 * What it does:
 * - callback function for the results sub template.
 * - loads the necessary contextual data to show a search result.
 *
 * @param bool $reset Whether to reset the counter
 * @return array An array of contextual info related to this search
 */
function prepareSearchContext($reset = false)
{
	global $messages_request;
	global $boards_can, $participants;
	static $recycle_board = null;

	if ($recycle_board === null)
		$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;

	// Remember which message this is.  (ie. reply #83)
	static $counter = null;
	if ($counter == null || $reset)
		$counter = ((int) $_REQUEST['start']) + 1;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Start from the beginning...
	if ($reset)
		return @Db::$db->data_seek($messages_request, 0);

	// Attempt to get the next message.
	$message = Db::$db->fetch_assoc($messages_request);
	if (!$message)
		return false;

	// Can't have an empty subject can we?
	$message['subject'] = $message['subject'] != '' ? $message['subject'] : Lang::$txt['no_subject'];

	$message['first_subject'] = $message['first_subject'] != '' ? $message['first_subject'] : Lang::$txt['no_subject'];
	$message['last_subject'] = $message['last_subject'] != '' ? $message['last_subject'] : Lang::$txt['no_subject'];

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (empty($message['id_member']) || !isset(User::$loaded[$message['id_member']]))
	{
		// Notice this information isn't used anywhere else.... *cough guest table cough*.
		$author['name'] = $message['poster_name'];
		$author['id'] = 0;
		$author['group'] = Lang::$txt['guest_title'];
		$author['link'] = $message['poster_name'];
		$author['email'] = $message['poster_email'];
	}
	else
		$author = User::$loaded[$message['id_member']]->format(true);

	$author['ip'] = inet_dtop($message['poster_ip']);

	// Do the censor thang...
	Lang::censorText($message['body']);
	Lang::censorText($message['subject']);

	Lang::censorText($message['first_subject']);
	Lang::censorText($message['last_subject']);

	// Shorten this message if necessary.
	if (Utils::$context['compact'])
	{
		// Set the number of characters before and after the searched keyword.
		$charLimit = 50;

		$message['body'] = strtr($message['body'], array("\n" => ' ', '<br>' => "\n", '<br/>' => "\n", '<br />' => "\n"));
		$message['body'] = BBCodeParser::load()->parse($message['body'], $message['smileys_enabled'], $message['id_msg']);
		$message['body'] = strip_tags(strtr($message['body'], array('</div>' => '<br>', '</li>' => '<br>')), '<br>');

		if (Utils::entityStrlen($message['body']) > $charLimit)
		{
			if (empty(Utils::$context['key_words']))
				$message['body'] = Utils::entitySubstr($message['body'], 0, $charLimit) . '<strong>...</strong>';
			else
			{
				$matchString = '';
				$force_partial_word = false;
				foreach (Utils::$context['key_words'] as $keyword)
				{
					$keyword = un_htmlspecialchars($keyword);
					$keyword = Utils::sanitizeEntities(Utils::entityFix(strtr($keyword, array('\\\'' => '\'', '&' => '&amp;'))));

					if (preg_match('~[\'\.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) != 0 || preg_match('~^[\'\.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword) != 0)
						$force_partial_word = true;
					$matchString .= strtr(preg_quote($keyword, '/'), array('\*' => '.+?')) . '|';
				}
				$matchString = un_htmlspecialchars(substr($matchString, 0, -1));

				$message['body'] = un_htmlspecialchars(strtr($message['body'], array('&nbsp;' => ' ', '<br>' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@')));

				if (empty(Config::$modSettings['search_method']) || $force_partial_word)
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/is' . (Utils::$context['utf8'] ? 'u' : ''), $message['body'], $matches);
				else
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/is' . (Utils::$context['utf8'] ? 'u' : ''), $message['body'], $matches);

				$message['body'] = '';
				foreach ($matches[0] as $index => $match)
				{
					$match = strtr(Utils::htmlspecialchars($match, ENT_QUOTES), array("\n" => '&nbsp;'));
					$message['body'] .= '<strong>......</strong>&nbsp;' . $match . '&nbsp;<strong>......</strong>';
				}
			}

			// Re-fix the international characters.
			$message['body'] = Utils::sanitizeEntities(Utils::entityFix($message['body']));
		}
		$message['subject_highlighted'] = highlight($message['subject'], Utils::$context['key_words']);
		$message['body_highlighted'] = highlight($message['body'], Utils::$context['key_words']);
	}
	else
	{
		// Run BBC interpreter on the message.
		$message['body'] = BBCodeParser::load()->parse($message['body'], $message['smileys_enabled'], $message['id_msg']);

		$message['subject_highlighted'] = highlight($message['subject'], Utils::$context['key_words']);
		$message['body_highlighted'] = highlight($message['body'], Utils::$context['key_words']);
	}

	// Make sure we don't end up with a practically empty message body.
	$message['body'] = preg_replace('~^(?:&nbsp;)+$~', '', $message['body']);

	if (!empty($recycle_board) && $message['id_board'] == $recycle_board)
	{
		$message['first_icon'] = 'recycled';
		$message['last_icon'] = 'recycled';
		$message['icon'] = 'recycled';
	}

	// Sadly, we need to check the icon ain't broke.
	if (!empty(Config::$modSettings['messageIconChecks_enable']))
	{
		if (!isset(Utils::$context['icon_sources'][$message['first_icon']]))
			Utils::$context['icon_sources'][$message['first_icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $message['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
		if (!isset(Utils::$context['icon_sources'][$message['last_icon']]))
			Utils::$context['icon_sources'][$message['last_icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $message['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		if (!isset(Utils::$context['icon_sources'][$message['icon']]))
			Utils::$context['icon_sources'][$message['icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $message['icon'] . '.png') ? 'images_url' : 'default_images_url';
	}
	else
	{
		if (!isset(Utils::$context['icon_sources'][$message['first_icon']]))
			Utils::$context['icon_sources'][$message['first_icon']] = 'images_url';
		if (!isset(Utils::$context['icon_sources'][$message['last_icon']]))
			Utils::$context['icon_sources'][$message['last_icon']] = 'images_url';
		if (!isset(Utils::$context['icon_sources'][$message['icon']]))
			Utils::$context['icon_sources'][$message['icon']] = 'images_url';
	}

	// Do we have quote tag enabled?
	$quote_enabled = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));

	// Reference the main color class.
	$colorClass = 'windowbg';

	// Sticky topics should get a different color, too.
	if ($message['is_sticky'])
		$colorClass .= ' sticky';

	// Locked topics get special treatment as well.
	if ($message['locked'])
		$colorClass .= ' locked';

	$output = array_merge(Utils::$context['topics'][$message['id_msg']], array(
		'id' => $message['id_topic'],
		'is_sticky' => !empty($message['is_sticky']),
		'is_locked' => !empty($message['locked']),
		'css_class' => $colorClass,
		'is_poll' => Config::$modSettings['pollMode'] == '1' && $message['id_poll'] > 0,
		'posted_in' => !empty($participants[$message['id_topic']]),
		'views' => $message['num_views'],
		'replies' => $message['num_replies'],
		'can_reply' => in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any']),
		'can_quote' => (in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any'])) && $quote_enabled,
		'first_post' => array(
			'id' => $message['first_msg'],
			'time' => timeformat($message['first_poster_time']),
			'timestamp' => $message['first_poster_time'],
			'subject' => $message['first_subject'],
			'href' => Config::$scripturl . '?topic=' . $message['id_topic'] . '.0',
			'link' => '<a href="' . Config::$scripturl . '?topic=' . $message['id_topic'] . '.0">' . $message['first_subject'] . '</a>',
			'icon' => $message['first_icon'],
			'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$message['first_icon']]] . '/post/' . $message['first_icon'] . '.png',
			'member' => array(
				'id' => $message['first_member_id'],
				'name' => $message['first_member_name'],
				'href' => !empty($message['first_member_id']) ? Config::$scripturl . '?action=profile;u=' . $message['first_member_id'] : '',
				'link' => !empty($message['first_member_id']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $message['first_member_id'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $message['first_member_name']) . '">' . $message['first_member_name'] . '</a>' : $message['first_member_name']
			)
		),
		'last_post' => array(
			'id' => $message['last_msg'],
			'time' => timeformat($message['last_poster_time']),
			'timestamp' => $message['last_poster_time'],
			'subject' => $message['last_subject'],
			'href' => Config::$scripturl . '?topic=' . $message['id_topic'] . ($message['num_replies'] == 0 ? '.0' : '.msg' . $message['last_msg']) . '#msg' . $message['last_msg'],
			'link' => '<a href="' . Config::$scripturl . '?topic=' . $message['id_topic'] . ($message['num_replies'] == 0 ? '.0' : '.msg' . $message['last_msg']) . '#msg' . $message['last_msg'] . '">' . $message['last_subject'] . '</a>',
			'icon' => $message['last_icon'],
			'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$message['last_icon']]] . '/post/' . $message['last_icon'] . '.png',
			'member' => array(
				'id' => $message['last_member_id'],
				'name' => $message['last_member_name'],
				'href' => !empty($message['last_member_id']) ? Config::$scripturl . '?action=profile;u=' . $message['last_member_id'] : '',
				'link' => !empty($message['last_member_id']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $message['last_member_id'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $message['last_member_name']) . '">' . $message['last_member_name'] . '</a>' : $message['last_member_name']
			)
		),
		'board' => array(
			'id' => $message['id_board'],
			'name' => $message['board_name'],
			'href' => Config::$scripturl . '?board=' . $message['id_board'] . '.0',
			'link' => '<a href="' . Config::$scripturl . '?board=' . $message['id_board'] . '.0">' . $message['board_name'] . '</a>'
		),
		'category' => array(
			'id' => $message['id_cat'],
			'name' => $message['cat_name'],
			'href' => Config::$scripturl . '#c' . $message['id_cat'],
			'link' => '<a href="' . Config::$scripturl . '#c' . $message['id_cat'] . '">' . $message['cat_name'] . '</a>'
		)
	));

	if (!empty(Theme::$current->options['display_quick_mod']))
	{
		$started = $output['first_post']['member']['id'] == User::$me->id;

		$output['quick_mod'] = array(
			'lock' => in_array(0, $boards_can['lock_any']) || in_array($output['board']['id'], $boards_can['lock_any']) || ($started && (in_array(0, $boards_can['lock_own']) || in_array($output['board']['id'], $boards_can['lock_own']))),
			'sticky' => (in_array(0, $boards_can['make_sticky']) || in_array($output['board']['id'], $boards_can['make_sticky'])),
			'move' => in_array(0, $boards_can['move_any']) || in_array($output['board']['id'], $boards_can['move_any']) || ($started && (in_array(0, $boards_can['move_own']) || in_array($output['board']['id'], $boards_can['move_own']))),
			'remove' => in_array(0, $boards_can['remove_any']) || in_array($output['board']['id'], $boards_can['remove_any']) || ($started && (in_array(0, $boards_can['remove_own']) || in_array($output['board']['id'], $boards_can['remove_own']))),
			'restore' => Utils::$context['can_restore_perm'] && (Config::$modSettings['recycle_board'] == $output['board']['id']),
		);

		Utils::$context['can_lock'] |= $output['quick_mod']['lock'];
		Utils::$context['can_sticky'] |= $output['quick_mod']['sticky'];
		Utils::$context['can_move'] |= $output['quick_mod']['move'];
		Utils::$context['can_remove'] |= $output['quick_mod']['remove'];
		Utils::$context['can_merge'] |= in_array($output['board']['id'], $boards_can['merge_any']);
		Utils::$context['can_restore'] |= $output['quick_mod']['restore'];
		Utils::$context['can_markread'] = User::$me->is_logged;

		// Sets Utils::$context['qmod_actions']
		// This is also where the integrate_quick_mod_actions_search hook now lives.
		QuickModeration::getActions(true);
	}

	$output['matches'][] = array(
		'id' => $message['id_msg'],
		'attachment' => array(),
		'member' => &$author,
		'icon' => $message['icon'],
		'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$message['icon']]] . '/post/' . $message['icon'] . '.png',
		'subject' => $message['subject'],
		'subject_highlighted' => $message['subject_highlighted'],
		'time' => timeformat($message['poster_time']),
		'timestamp' => $message['poster_time'],
		'counter' => $counter,
		'modified' => array(
			'time' => timeformat($message['modified_time']),
			'timestamp' => $message['modified_time'],
			'name' => $message['modified_name']
		),
		'body' => $message['body'],
		'body_highlighted' => $message['body_highlighted'],
		'start' => 'msg' . $message['id_msg']
	);
	$counter++;

	call_integration_hook('integrate_search_message_context', array(&$output, &$message, $counter));

	return $output;
}

/**
 * This function compares the length of two strings plus a little.
 * What it does:
 * - callback function for usort used to sort the fulltext results.
 * - passes sorting duty to the current API.
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function searchSort($a, $b)
{
	global $searchAPI;

	return $searchAPI->searchSort($a, $b);
}

/**
 * Highlighting matching string
 *
 * @param string $text Text to search through
 * @param array $words List of keywords to search
 *
 * @return string Text with highlighted keywords
 */
function highlight($text, array $words)
{
	$words = build_regex($words, '~');

	$highlighted = '';

	// Don't mess with the content of HTML tags.
	$parts = preg_split('~(<[^>]+>)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

	for ($i = 0, $n = count($parts); $i < $n; $i++)
		$highlighted .= $i % 2 === 0 ? preg_replace('~' . $words . '~iu', '<mark class="highlight">$0</mark>', $parts[$i]) : $parts[$i];

	if (!empty($highlighted))
		$text = $highlighted;

	return $text;
}

?>