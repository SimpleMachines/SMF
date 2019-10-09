<?php

/**
 * Handle all of the searching from here.
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
	global $txt, $scripturl, $modSettings, $user_info, $context, $smcFunc, $sourcedir;

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	loadLanguage('Search');
	// Don't load this in XML mode.
	if (!isset($_REQUEST['xml']))
	{
		loadTemplate('Search');
		loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
	}

	// Check the user's permissions.
	isAllowedTo('search_posts');

	// Link tree....
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search',
		'name' => $txt['search']
	);

	// This is hard coded maximum string length.
	$context['search_string_limit'] = 100;

	$context['require_verification'] = $user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);
	if ($context['require_verification'])
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'search',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// If you got back from search2 by using the linktree, you get your original search parameters back.
	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));
		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}
		if (isset($context['search_params']['brd']))
			$context['search_params']['brd'] = $context['search_params']['brd'] == '' ? array() : explode(',', $context['search_params']['brd']);
	}

	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = $smcFunc['htmlspecialchars']($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = $smcFunc['htmlspecialchars']($context['search_params']['userspec']);
	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;
	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];
	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

	// Load the error text strings if there were errors in the search.
	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error === 'messages')
				continue;

			if ($search_error == 'string_too_long')
				$txt['error_string_too_long'] = sprintf($txt['error_string_too_long'], $context['search_string_limit']);

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	// Find all the boards this user is allowed to see.
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}',
		array(
			'empty_string' => '',
		)
	);
	$context['num_boards'] = $smcFunc['db_num_rows']($request);
	$context['boards_check_all'] = true;
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
			'selected' => (empty($context['search_params']['brd']) && (empty($modSettings['recycle_enable']) || $row['id_board'] != $modSettings['recycle_board']) && !in_array($row['id_board'], $user_info['ignoreboards'])) || (!empty($context['search_params']['brd']) && in_array($row['id_board'], $context['search_params']['brd']))
		);

		// If a board wasn't checked that probably should have been ensure the board selection is selected, yo!
		if (!$context['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] && (empty($modSettings['recycle_enable']) || $row['id_board'] != $modSettings['recycle_board']))
			$context['boards_check_all'] = false;
	}
	$smcFunc['db_free_result']($request);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($context['categories']);

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

	// Now, alternate them so they can be shown left and right ;).
	$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++)
	{
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = array();
	}

	if (!empty($_REQUEST['topic']))
	{
		$context['search_params']['topic'] = (int) $_REQUEST['topic'];
		$context['search_params']['show_complete'] = true;
	}
	if (!empty($context['search_params']['topic']))
	{
		$context['search_params']['topic'] = (int) $context['search_params']['topic'];

		$context['search_topic'] = array(
			'id' => $context['search_params']['topic'],
			'href' => $scripturl . '?topic=' . $context['search_params']['topic'] . '.0',
		);

		$request = $smcFunc['db_query']('', '
			SELECT subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_message_board} ' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved_true}' : '') . '
			LIMIT 1',
			array(
				'is_approved_true' => 1,
				'search_topic_id' => $context['search_params']['topic'],
			)
		);

		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('topic_gone', false);

		list ($context['search_topic']['subject']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['search_topic']['link'] = '<a href="' . $context['search_topic']['href'] . '">' . $context['search_topic']['subject'] . '</a>';
	}

	$context['page_title'] = $txt['set_parameters'];

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
	global $scripturl, $modSettings, $sourcedir, $txt;
	global $user_info, $context, $options, $messages_request, $boards_can;
	global $excludedWords, $participants, $smcFunc, $cache_enable;

	// if comming from the quick search box, and we want to search on members, well we need to do that ;)
	if (isset($_REQUEST['search_selection']) && $_REQUEST['search_selection'] === 'members')
		redirectexit($scripturl . '?action=mlist;sa=search;fields=name,email;search=' . urlencode($_REQUEST['search']));

	if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		send_http_status(403);
		die;
	}

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
		$weight[$weight_factor] = empty($modSettings['search_weight_' . $weight_factor]) ? 0 : (int) $modSettings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// Zero weight.  Weightless :P.
	if (empty($weight_total))
		fatal_lang_error('search_invalid_weights');

	// These vars don't require an interface, they're just here for tweaking.
	$recentPercentage = 0.30;
	$humungousTopicPosts = 200;
	$maxMembersToSearch = 500;
	$maxMessageResults = empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] * 5;

	// Start with no errors.
	$context['search_errors'] = array();

	// Number of pages hard maximum - normally not set at all.
	$modSettings['search_max_results'] = empty($modSettings['search_max_results']) ? 200 * $modSettings['search_results_per_page'] : (int) $modSettings['search_max_results'];

	// Maximum length of the string.
	$context['search_string_limit'] = 100;

	loadLanguage('Search');
	if (!isset($_REQUEST['xml']))
		loadTemplate('Search');
	//If we're doing XML we need to use the results template regardless really.
	else
		$context['sub_template'] = 'results';

	// Are you allowed?
	isAllowedTo('search_posts');

	require_once($sourcedir . '/Display.php');
	require_once($sourcedir . '/Subs-Package.php');

	// Search has a special database set.
	db_extend('search');

	// Load up the search API we are going to use.
	$searchAPI = findSearchAPI();

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
		$request = $smcFunc['db_query']('', '
			SELECT ' . (empty($search_params['maxage']) ? '0, ' : 'COALESCE(MIN(id_msg), -1), ') . (empty($search_params['minage']) ? '0' : 'COALESCE(MAX(id_msg), -1)') . '
			FROM {db_prefix}messages
			WHERE 1=1' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved_true}' : '') . (empty($search_params['minage']) ? '' : '
				AND poster_time <= {int:timestamp_minimum_age}') . (empty($search_params['maxage']) ? '' : '
				AND poster_time >= {int:timestamp_maximum_age}'),
			array(
				'timestamp_minimum_age' => empty($search_params['minage']) ? 0 : time() - 86400 * $search_params['minage'],
				'timestamp_maximum_age' => empty($search_params['maxage']) ? 0 : time() - 86400 * $search_params['maxage'],
				'is_approved_true' => 1,
			)
		);
		list ($minMsgID, $maxMsgID) = $smcFunc['db_fetch_row']($request);
		if ($minMsgID < 0 || $maxMsgID < 0)
			$context['search_errors']['no_messages_in_time_frame'] = true;
		$smcFunc['db_free_result']($request);
	}

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr($smcFunc['htmlspecialchars']($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}

		// Create a list of database-escaped search names.
		$realNameMatches = array();
		foreach ($possible_users as $possible_user)
			$realNameMatches[] = $smcFunc['db_quote'](
				'{string:possible_user}',
				array(
					'possible_user' => $possible_user
				)
			);

		// Retrieve a list of possible members.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE {raw:match_possible_users}',
			array(
				'match_possible_users' => 'real_name LIKE ' . implode(' OR real_name LIKE ', $realNameMatches),
			)
		);
		// Simply do nothing if there're too many members matching the criteria.
		if ($smcFunc['db_num_rows']($request) > $maxMembersToSearch)
			$userQuery = '';
		elseif ($smcFunc['db_num_rows']($request) == 0)
		{
			$userQuery = $smcFunc['db_quote'](
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
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$memberlist[] = $row['id_member'];
			$userQuery = $smcFunc['db_quote'](
				'(m.id_member IN ({array_int:matched_members}) OR (m.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})))',
				array(
					'matched_members' => $memberlist,
					'id_member_guest' => 0,
					'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
				)
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// If the boards were passed by URL (params=), temporarily put them back in $_REQUEST.
	if (!empty($search_params['brd']) && is_array($search_params['brd']))
		$_REQUEST['brd'] = $search_params['brd'];

	// Ensure that brd is an array.
	if ((!empty($_REQUEST['brd']) && !is_array($_REQUEST['brd'])) || (!empty($_REQUEST['search_selection']) && $_REQUEST['search_selection'] == 'board'))
	{
		if (!empty($_REQUEST['brd']))
			$_REQUEST['brd'] = strpos($_REQUEST['brd'], ',') !== false ? explode(',', $_REQUEST['brd']) : array($_REQUEST['brd']);
		else
			$_REQUEST['brd'] = isset($_REQUEST['sd_brd']) ? array($_REQUEST['sd_brd']) : array();
	}

	// Make sure all boards are integers.
	if (!empty($_REQUEST['brd']))
		foreach ($_REQUEST['brd'] as $id => $brd)
			$_REQUEST['brd'][$id] = (int) $brd;

	// Special case for boards: searching just one topic?
	if (!empty($search_params['topic']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT t.id_board
			FROM {db_prefix}topics AS t
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_topic_board}' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved_true}' : '') . '
			LIMIT 1',
			array(
				'search_topic_id' => $search_params['topic'],
				'is_approved_true' => 1,
			)
		);

		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('topic_gone', false);

		$search_params['brd'] = array();
		list ($search_params['brd'][0]) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	// Select all boards you've selected AND are allowed to see.
	elseif ($user_info['is_admin'] && (!empty($search_params['advanced']) || !empty($_REQUEST['brd'])))
		$search_params['brd'] = empty($_REQUEST['brd']) ? array() : $_REQUEST['brd'];
	else
	{
		$see_board = empty($search_params['advanced']) ? 'query_wanna_see_board' : 'query_see_board';
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {raw:boards_allowed_to_see}
				AND redirect = {string:empty_string}' . (empty($_REQUEST['brd']) ? (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board_id}' : '') : '
				AND b.id_board IN ({array_int:selected_search_boards})'),
			array(
				'boards_allowed_to_see' => $user_info[$see_board],
				'empty_string' => '',
				'selected_search_boards' => empty($_REQUEST['brd']) ? array() : $_REQUEST['brd'],
				'recycle_board_id' => $modSettings['recycle_board'],
			)
		);
		$search_params['brd'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$search_params['brd'][] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		// This error should pro'bly only happen for hackers.
		if (empty($search_params['brd']))
			$context['search_errors']['no_boards_selected'] = true;
	}

	if (count($search_params['brd']) != 0)
	{
		foreach ($search_params['brd'] as $k => $v)
			$search_params['brd'][$k] = (int) $v;

		// If we've selected all boards, this parameter can be left empty.
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}boards
			WHERE redirect = {string:empty_string}',
			array(
				'empty_string' => '',
			)
		);
		list ($num_boards) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (count($search_params['brd']) == $num_boards)
			$boardQuery = '';
		elseif (count($search_params['brd']) == $num_boards - 1 && !empty($modSettings['recycle_board']) && !in_array($modSettings['recycle_board'], $search_params['brd']))
			$boardQuery = '!= ' . $modSettings['recycle_board'];
		else
			$boardQuery = 'IN (' . implode(', ', $search_params['brd']) . ')';
	}
	else
		$boardQuery = '';

	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);
	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);

	$context['compact'] = !$search_params['show_complete'];

	// Get the sorting parameters right. Default to sort by relevance descending.
	$sort_columns = array(
		'relevance',
		'num_replies',
		'id_msg',
	);
	call_integration_hook('integrate_search_sort_columns', array(&$sort_columns));
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'relevance';
	if (!empty($search_params['topic']) && $search_params['sort'] === 'num_replies')
		$search_params['sort'] = 'id_msg';

	// Sorting direction: descending unless stated otherwise.
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Determine some values needed to calculate the relevance.
	$minMsg = (int) ((1 - $recentPercentage) * $modSettings['maxMsgID']);
	$recentMsg = $modSettings['maxMsgID'] - $minMsg;

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
		$context['search_errors']['invalid_search_string'] = true;
	// Too long?
	elseif ($smcFunc['strlen']($search_params['search']) > $context['search_string_limit'])
	{
		$context['search_errors']['string_too_long'] = true;
	}

	// Change non-word characters into spaces.
	$stripped_query = preg_replace('~(?:[\x0B\0' . ($context['utf8'] ? '\x{A0}' : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . ($context['utf8'] ? 'u' : ''), ' ', $search_params['search']);

	// Make the query lower case. It's gonna be case insensitive anyway.
	$stripped_query = un_htmlspecialchars($smcFunc['strtolower']($stripped_query));

	// This (hidden) setting will do fulltext searching in the most basic way.
	if (!empty($modSettings['search_simple_fulltext']))
		$stripped_query = strtr($stripped_query, array('"' => ''));

	$no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
	$phraseArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~' . ($context['utf8'] ? 'u' : ''), ' ', $search_params['search']);
	$wordArray = explode(' ', $smcFunc['htmlspecialchars'](un_htmlspecialchars($wordArray), ENT_QUOTES));

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

	$context['search_ignored'] = array();
	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		// Skip anything practically empty.
		if (($searchArray[$index] = trim($value, '-_\' ')) === '')
			unset($searchArray[$index]);
		// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
		elseif (in_array($searchArray[$index], $blacklisted_words))
		{
			$foundBlackListedWords = true;
			unset($searchArray[$index]);
		}
		// Don't allow very, very short words.
		elseif ($smcFunc['strlen']($value) < 2)
		{
			$context['search_ignored'][] = $value;
			unset($searchArray[$index]);
		}
	}
	$searchArray = array_slice(array_unique($searchArray), 0, 10);

	// Create an array of replacements for highlighting.
	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

	// Initialize two arrays storing the words that have to be searched for.
	$orParts = array();
	$searchWords = array();

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
		$context['search_errors']['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;
	// All words/sentences must match.
	elseif (empty($search_params['searchtype']))
		$orParts[0] = $searchArray;
	// Any word/sentence must match.
	else
		foreach ($searchArray as $index => $value)
			$orParts[$index] = array($value);

	// Don't allow duplicate error messages if one string is too short.
	if (isset($context['search_errors']['search_string_small_words'], $context['search_errors']['invalid_search_string']))
		unset($context['search_errors']['invalid_search_string']);
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
		if (!empty($modSettings['search_force_index']) && empty($searchWords[$orIndex]['indexed_words']))
		{
			$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}
		elseif ($search_params['subject_only'] && empty($searchWords[$orIndex]['subject_words']) && empty($excludedSubjectWords))
		{
			$context['search_errors']['query_not_specific_enough'] = true;
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
	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && (function_exists('pspell_new') || (function_exists('enchant_broker_init') && ($txt['lang_character_set'] == 'UTF-8' || function_exists('iconv'))));
	if ($context['show_spellchecking'])
	{
		require_once($sourcedir . '/Subs-Post.php');

		// Don't hardcode spellchecking functions!
		$link = spell_init();

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
				$did_you_mean['display'][] = '&quot;' . $smcFunc['htmlspecialchars']($word) . '&quot;';
				continue;
			}
			// For some strange reason spell check can crash PHP on decimals.
			elseif (preg_match('~\d~', $word) === 1)
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = $smcFunc['htmlspecialchars']($word);
				continue;
			}
			elseif (spell_check($link, $word))
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = $smcFunc['htmlspecialchars']($word);
				continue;
			}

			$suggestions = spell_suggest($link, $word);
			foreach ($suggestions as $i => $s)
			{
				// Search is case insensitive.
				if ($smcFunc['strtolower']($s) == $smcFunc['strtolower']($word))
					unset($suggestions[$i]);
				// Plus, don't suggest something the user thinks is rude!
				elseif ($suggestions[$i] != censorText($s))
					unset($suggestions[$i]);
			}

			// Anything found?  If so, correct it!
			if (!empty($suggestions))
			{
				$suggestions = array_values($suggestions);
				$did_you_mean['search'][] = $suggestions[0];
				$did_you_mean['display'][] = '<em><strong>' . $smcFunc['htmlspecialchars']($suggestions[0]) . '</strong></em>';
				$found_misspelling = true;
			}
			else
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = $smcFunc['htmlspecialchars']($word);
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
					$temp_excluded['display'][] = '-&quot;' . $smcFunc['htmlspecialchars']($word) . '&quot;';
				}
				else
				{
					$temp_excluded['search'][] = '-' . $word;
					$temp_excluded['display'][] = '-' . $smcFunc['htmlspecialchars']($word);
				}
			}

			$did_you_mean['search'] = array_merge($did_you_mean['search'], $temp_excluded['search']);
			$did_you_mean['display'] = array_merge($did_you_mean['display'], $temp_excluded['display']);

			$temp_params = $search_params;
			$temp_params['search'] = implode(' ', $did_you_mean['search']);
			if (isset($temp_params['brd']))
				$temp_params['brd'] = implode(',', $temp_params['brd']);
			$context['params'] = array();
			foreach ($temp_params as $k => $v)
				$context['did_you_mean_params'][] = $k . '|\'|' . $v;
			$context['did_you_mean_params'] = base64_encode(implode('|"|', $context['did_you_mean_params']));
			$context['did_you_mean'] = implode(' ', $did_you_mean['display']);
		}
	}

	// Let the user adjust the search query, should they wish?
	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = $smcFunc['htmlspecialchars']($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = $smcFunc['htmlspecialchars']($context['search_params']['userspec']);

	// Do we have captcha enabled?
	if ($user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'search',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['require_verification']))
		{
			foreach ($context['require_verification'] as $error)
				$context['search_errors'][$error] = true;
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
	$context['params'] = array();
	foreach ($temp_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;

	if (!empty($context['params']))
	{
		// Due to old IE's 2083 character limit, we have to compress long search strings
		$params = @gzcompress(implode('|"|', $context['params']));
		// Gzcompress failed, use try non-gz
		if (empty($params))
			$params = implode('|"|', $context['params']);
		// Base64 encode, then replace +/= with uri safe ones that can be reverted
		$context['params'] = str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode($params));
	}

	// ... and add the links to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search;params=' . $context['params'],
		'name' => $txt['search']
	);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search2;params=' . $context['params'],
		'name' => $txt['search_results']
	);

	// *** A last error check
	call_integration_hook('integrate_search_errors');

	// One or more search errors? Go back to the first search screen.
	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
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
		$update_cache = empty($_SESSION['search_cache']) || ($_SESSION['search_cache']['params'] != $context['params']);
		// Are the result fresh?
		if (!$update_cache && !empty($_SESSION['search_cache']['id_search']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_search
				FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}
				LIMIT 1',
				array(
					'search_id' => $_SESSION['search_cache']['id_search'],
				)
			);

			if ($smcFunc['db_num_rows']($request) === 0)
				$update_cache = true;
		}

		if ($update_cache)
		{
			// Increase the pointer...
			$modSettings['search_pointer'] = empty($modSettings['search_pointer']) ? 0 : (int) $modSettings['search_pointer'];
			// ...and store it right off.
			updateSettings(array('search_pointer' => $modSettings['search_pointer'] >= 255 ? 0 : $modSettings['search_pointer'] + 1));
			// As long as you don't change the parameters, the cache result is yours.
			$_SESSION['search_cache'] = array(
				'id_search' => $modSettings['search_pointer'],
				'num_results' => -1,
				'params' => $context['params'],
			);

			// Clear the previous cache of the final results cache.
			$smcFunc['db_search_query']('delete_log_search_results', '
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
					$subject_query_params = array();
					$subject_query = array(
						'from' => '{db_prefix}topics AS t',
						'inner_join' => array(),
						'left_join' => array(),
						'where' => array(),
					);

					if ($modSettings['postmod_active'])
						$subject_query['where'][] = 't.approved = {int:is_approved}';

					$numTables = 0;
					$prev_join = 0;
					$numSubjectResults = 0;
					foreach ($words['subject_words'] as $subjectWord)
					{
						$numTables++;
						if (in_array($subjectWord, $excludedSubjectWords))
						{
							$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
							$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
						}
						else
						{
							$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
							$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');
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
							$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
						}
						$count = 0;
						foreach ($excludedPhrases as $phrase)
						{
							$subject_query['where'][] = 'm.subject NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:excluded_phrases_' . $count . '}';
							$subject_query_params['excluded_phrases_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
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

					$ignoreRequest = $smcFunc['db_search_query']('insert_log_search_results_subject',
						($smcFunc['db_support_ignore'] ? '
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
							AND ', $subject_query['where']) . (empty($modSettings['search_max_results']) ? '' : '
						LIMIT ' . ($modSettings['search_max_results'] - $numSubjectResults)),
						array_merge($subject_query_params, array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
							'is_approved' => 1,
						))
					);

					// If the database doesn't support IGNORE to make this fast we need to do some tracking.
					if (!$smcFunc['db_support_ignore'])
					{
						while ($row = $smcFunc['db_fetch_row']($ignoreRequest))
						{
							// No duplicates!
							if (isset($inserts[$row[1]]))
								continue;

							foreach ($row as $key => $value)
								$inserts[$row[1]][] = (int) $row[$key];
						}
						$smcFunc['db_free_result']($ignoreRequest);
						$numSubjectResults = count($inserts);
					}
					else
						$numSubjectResults += $smcFunc['db_affected_rows']();

					if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
						break;
				}

				// If there's data to be inserted for non-IGNORE databases do it here!
				if (!empty($inserts))
				{
					$smcFunc['db_insert']('',
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
					'where' => array(),
					'group_by' => array(),
					'parameters' => array(
						'min_msg' => $minMsg,
						'recent_message' => $recentMsg,
						'huge_topic_posts' => $humungousTopicPosts,
						'is_approved' => 1,
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
					$smcFunc['db_search_query']('drop_tmp_log_search_topics', '
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_topics',
						array(
						)
					);
					$createTemporary = $smcFunc['db_search_query']('create_tmp_log_search_topics', '
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
						$smcFunc['db_search_query']('delete_log_search_topics', '
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
							'where' => array(),
							'params' => array(),
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
									$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
									$excluded = true;
								}
								$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
								$subject_query['params']['subject_not_' . $count] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

								$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
								$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:body_not_' . $count . '}';
								$subject_query['params']['body_not_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($subjectWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $subjectWord), '\\\'') . '[[:>:]]';
							}
							else
							{
								$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
								$subject_query['where'][] = 'subj' . $numTables . '.word LIKE {string:subject_like_' . $count . '}';
								$subject_query['params']['subject_like_' . $count++] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;
								$prev_join = $numTables;
							}
						}

						if (!empty($userQuery))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
							{
								$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
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
								$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
							}
							$count = 0;
							foreach ($excludedPhrases as $phrase)
							{
								$subject_query['where'][] = 'm.subject NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:exclude_phrase_' . $count . '}';
								$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:exclude_phrase_' . $count . '}';
								$subject_query['params']['exclude_phrase_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
							}
						}
						call_integration_hook('integrate_subject_search_query', array(&$subject_query));

						// Nothing to search for?
						if (empty($subject_query['where']))
							continue;

						$ignoreRequest = $smcFunc['db_search_query']('insert_log_search_topics', ($smcFunc['db_support_ignore'] ? ('
							INSERT IGNORE INTO {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics
								(' . ($createTemporary ? '' : 'id_search, ') . 'id_topic)') : '') . '
							SELECT ' . ($createTemporary ? '' : $_SESSION['search_cache']['id_search'] . ', ') . 't.id_topic
							FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
								INNER JOIN ' . implode('
								INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
								LEFT JOIN ' . implode('
								LEFT JOIN ', $subject_query['left_join'])) . '
							WHERE ' . implode('
								AND ', $subject_query['where']) . (empty($modSettings['search_max_results']) ? '' : '
							LIMIT ' . ($modSettings['search_max_results'] - $numSubjectResults)),
							$subject_query['params']
						);
						// Don't do INSERT IGNORE? Manually fix this up!
						if (!$smcFunc['db_support_ignore'])
						{
							while ($row = $smcFunc['db_fetch_row']($ignoreRequest))
							{
								$ind = $createTemporary ? 0 : 1;
								// No duplicates!
								if (isset($inserts[$row[$ind]]))
									continue;

								$inserts[$row[$ind]] = $row;
							}
							$smcFunc['db_free_result']($ignoreRequest);
							$numSubjectResults = count($inserts);
						}
						else
							$numSubjectResults += $smcFunc['db_affected_rows']();

						if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
							break;
					}

					// Got some non-MySQL data to plonk in?
					if (!empty($inserts))
					{
						$smcFunc['db_insert']('',
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
					$smcFunc['db_search_query']('drop_tmp_log_search_messages', '
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_messages',
						array(
						)
					);

					$createTemporary = $smcFunc['db_search_query']('create_tmp_log_search_messages', '
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
						$smcFunc['db_search_query']('delete_log_search_messages', '
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

							if (!$smcFunc['db_support_ignore'])
							{
								while ($row = $smcFunc['db_fetch_row']($ignoreRequest))
								{
									// No duplicates!
									if (isset($inserts[$row[0]]))
										continue;

									$inserts[$row[0]] = $row;
								}
								$smcFunc['db_free_result']($ignoreRequest);
								$indexedResults = count($inserts);
							}
							else
								$indexedResults += $smcFunc['db_affected_rows']();

							if (!empty($maxMessageResults) && $indexedResults >= $maxMessageResults)
								break;
						}
					}

					// More non-MySQL stuff needed?
					if (!empty($inserts))
					{
						$smcFunc['db_insert']('',
							'{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
							$createTemporary ? array('id_msg' => 'int') : array('id_msg' => 'int', 'id_search' => 'int'),
							$inserts,
							$createTemporary ? array('id_msg') : array('id_msg', 'id_search')
						);
					}

					if (empty($indexedResults) && empty($numSubjectResults) && !empty($modSettings['search_force_index']))
					{
						$context['search_errors']['query_not_specific_enough'] = true;
						$_REQUEST['params'] = $context['params'];
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
							$where[] = 'm.body' . (in_array($regularWord, $excludedWords) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
							if (in_array($regularWord, $excludedWords))
								$where[] = 'm.subject NOT' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
							$main_query['parameters']['all_word_body_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($regularWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $regularWord), '\\\'') . '[[:>:]]';
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

					$ignoreRequest = $smcFunc['db_search_query']('insert_log_search_results_no_index', ($smcFunc['db_support_ignore'] ? ('
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
						GROUP BY ' . implode(', ', $main_query['group_by'])) . (empty($modSettings['search_max_results']) ? '' : '
						LIMIT ' . $modSettings['search_max_results']),
						$main_query['parameters']
					);

					// We love to handle non-good databases that don't support our ignore!
					if (!$smcFunc['db_support_ignore'])
					{
						$inserts = array();
						while ($row = $smcFunc['db_fetch_row']($ignoreRequest))
						{
							// No duplicates!
							if (isset($inserts[$row[2]]))
								continue;

							foreach ($row as $key => $value)
								$inserts[$row[2]][] = (int) $row[$key];
						}
						$smcFunc['db_free_result']($ignoreRequest);

						// Now put them in!
						if (!empty($inserts))
						{
							$query_columns = array();
							foreach ($main_query['select'] as $k => $v)
								$query_columns[$k] = 'int';

							$smcFunc['db_insert']('',
								'{db_prefix}log_search_results',
								$query_columns,
								$inserts,
								array('id_search', 'id_topic')
							);
						}
						$_SESSION['search_cache']['num_results'] += count($inserts);
					}
					else
						$_SESSION['search_cache']['num_results'] = $smcFunc['db_affected_rows']();
				}

				// Insert subject-only matches.
				if ($_SESSION['search_cache']['num_results'] < $modSettings['search_max_results'] && $numSubjectResults !== 0)
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
					$ignoreRequest = $smcFunc['db_search_query']('insert_log_search_results_sub_only', ($smcFunc['db_support_ignore'] ? ('
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
						. (empty($modSettings['search_max_results']) ? '' : '
						LIMIT ' . ($modSettings['search_max_results'] - $_SESSION['search_cache']['num_results'])),
						array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
						)
					);
					// Once again need to do the inserts if the database don't support ignore!
					if (!$smcFunc['db_support_ignore'])
					{
						$inserts = array();
						while ($row = $smcFunc['db_fetch_row']($ignoreRequest))
						{
							// No duplicates!
							if (isset($usedIDs[$row[1]]))
								continue;

							$usedIDs[$row[1]] = true;
							$inserts[] = $row;
						}
						$smcFunc['db_free_result']($ignoreRequest);

						// Now put them in!
						if (!empty($inserts))
						{
							$smcFunc['db_insert']('',
								'{db_prefix}log_search_results',
								array('id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'float', 'id_msg' => 'int', 'num_matches' => 'int'),
								$inserts,
								array('id_search', 'id_topic')
							);
						}
						$_SESSION['search_cache']['num_results'] += count($inserts);
					}
					else
						$_SESSION['search_cache']['num_results'] += $smcFunc['db_affected_rows']();
				}
				elseif ($_SESSION['search_cache']['num_results'] == -1)
					$_SESSION['search_cache']['num_results'] = 0;
			}
		}

		if (!empty($modSettings['postmod_active']))
		{
			$approve_boards = boardsAllowedTo('approve_posts');

			// Can approve everywhere, so search all topics.
			if ($approve_boards === array(0))
		 		$approve_query = '';

		 	// Can't approve anywhere, so search ony their own topics and approved topics.
		 	elseif (empty($approve_boards))
		 		$approve_query = '
				AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member})';

		 	// Can approve in some boards, so search own, approved, and approvable topics.
		 	else
		 		$approve_query = '
				AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member} OR t.id_board IN ({array_int:approve_boards}))';
		}

		// *** Retrieve the results to be shown on the page
		$participants = array();
		$request = $smcFunc['db_search_query']('', '
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
				'max' => $modSettings['search_results_per_page'],
				'is_approved' => 1,
				'current_member' => $user_info['id'],
				'approve_boards' => !empty($approve_boards) ? $approve_boards : array(0),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$context['topics'][$row['id_msg']] = array(
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => array(),
			);
			// By default they didn't participate in the topic!
			$participants[$row['id_topic']] = false;
		}
		$smcFunc['db_free_result']($request);
	}

	$num_results = 0;
	if (!empty($context['topics']))
	{
		// Create an array for the permissions.
		$perms = array('post_reply_own', 'post_reply_any');

		if (!empty($options['display_quick_mod']))
			$perms = array_merge($perms, array('lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any'));

		if (!empty($modSettings['postmod_active']) && !isset($approve_boards))
			$perms[] = 'approve_posts';

		$boards_can = boardsAllowedTo($perms, true, false);

		// How's about some quick moderation?
		if (!empty($options['display_quick_mod']))
		{
			$context['can_lock'] = in_array(0, $boards_can['lock_any']);
			$context['can_sticky'] = in_array(0, $boards_can['make_sticky']);
			$context['can_move'] = in_array(0, $boards_can['move_any']);
			$context['can_remove'] = in_array(0, $boards_can['remove_any']);
			$context['can_merge'] = in_array(0, $boards_can['merge_any']);
		}

		if (!empty($modSettings['postmod_active']))
		{
			if (!isset($approve_boards))
				$approve_boards = $boards_can['approve_posts'];

			if ($approve_boards === array(0))
		 		$approve_query = '';

		 	elseif (empty($approve_boards))
		 		$approve_query = '
				AND (m.approved = {int:is_approved} OR m.id_member = {int:current_member})';

		 	else
		 		$approve_query = '
				AND (m.approved = {int:is_approved} OR m.id_member = {int:current_member} OR m.id_board IN ({array_int:approve_boards}))';
		}

		// What messages are we using?
		$msg_list = array_keys($context['topics']);

		// Load the posters...
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_member != {int:no_member}
				AND id_msg IN ({array_int:message_list})
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'no_member' => 0,
				'limit' => count($context['topics']),
			)
		);
		$posters = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$posters[] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		call_integration_hook('integrate_search_message_list', array(&$msg_list, &$posters));

		if (!empty($posters))
			loadMemberData(array_unique($posters));

		// Get the messages out for the callback - select enough that it can be made to look just like Display.
		$messages_request = $smcFunc['db_query']('', '
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
			ORDER BY ' . $smcFunc['db_custom_order']('m.id_msg', $msg_list) . '
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'is_approved' => 1,
				'current_member' => $user_info['id'],
				'approve_boards' => !empty($approve_boards) ? $approve_boards : array(0),
				'limit' => count($context['topics']),
			)
		);

		// How many results will the user be able to see?
		$num_results = $smcFunc['db_num_rows']($messages_request);

		// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
		if ($num_results == 0)
			$context['topics'] = array();

		// If we want to know who participated in what then load this now.
		if (!empty($modSettings['enableParticipation']) && !$user_info['is_guest'])
		{
			$result = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT {int:limit}',
				array(
					'current_member' => $user_info['id'],
					'topic_list' => array_keys($participants),
					'limit' => count($participants),
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($result))
				$participants[$row['id_topic']] = true;

			$smcFunc['db_free_result']($result);
		}
	}

	// Now that we know how many results to expect we can start calculating the page numbers.
	$context['page_index'] = constructPageIndex($scripturl . '?action=search2;params=' . $context['params'], $_REQUEST['start'], $num_results, $modSettings['search_results_per_page'], false);

	// Consider the search complete!
	if (!empty($cache_enable) && $cache_enable >= 2)
		cache_put_data('search_start:' . ($user_info['is_guest'] ? $user_info['ip'] : $user_info['id']), null, 90);

	$context['key_words'] = &$searchArray;

	// Setup the default topic icons... for checking they exist and the like!
	$context['icon_sources'] = array();
	foreach ($context['stable_icons'] as $icon)
		$context['icon_sources'][$icon] = 'images_url';

	$context['sub_template'] = 'results';
	$context['page_title'] = $txt['search_results'];
	$context['get_topics'] = 'prepareSearchContext';
	$context['can_restore_perm'] = allowedTo('move_any') && !empty($modSettings['recycle_enable']);
	$context['can_restore'] = false; // We won't know until we handle the context later whether we can actually restore...

	$context['jump_to'] = array(
		'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
		'board_name' => addslashes(un_htmlspecialchars($txt['select_destination'])),
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
	global $txt, $modSettings, $scripturl, $user_info;
	global $memberContext, $context, $settings, $options, $messages_request;
	global $boards_can, $participants, $smcFunc;
	static $recycle_board = null;

	if ($recycle_board === null)
		$recycle_board = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? (int) $modSettings['recycle_board'] : 0;

	// Remember which message this is.  (ie. reply #83)
	static $counter = null;
	if ($counter == null || $reset)
		$counter = $_REQUEST['start'] + 1;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Start from the beginning...
	if ($reset)
		return @$smcFunc['db_data_seek']($messages_request, 0);

	// Attempt to get the next message.
	$message = $smcFunc['db_fetch_assoc']($messages_request);
	if (!$message)
		return false;

	// Can't have an empty subject can we?
	$message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

	$message['first_subject'] = $message['first_subject'] != '' ? $message['first_subject'] : $txt['no_subject'];
	$message['last_subject'] = $message['last_subject'] != '' ? $message['last_subject'] : $txt['no_subject'];

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member']))
	{
		// Notice this information isn't used anywhere else.... *cough guest table cough*.
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		$memberContext[$message['id_member']]['link'] = $message['poster_name'];
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
	}
	$memberContext[$message['id_member']]['ip'] = inet_dtop($message['poster_ip']);

	// Do the censor thang...
	censorText($message['body']);
	censorText($message['subject']);

	censorText($message['first_subject']);
	censorText($message['last_subject']);

	// Shorten this message if necessary.
	if ($context['compact'])
	{
		// Set the number of characters before and after the searched keyword.
		$charLimit = 50;

		$message['body'] = strtr($message['body'], array("\n" => ' ', '<br>' => "\n"));
		$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);
		$message['body'] = strip_tags(strtr($message['body'], array('</div>' => '<br>', '</li>' => '<br>')), '<br>');

		if ($smcFunc['strlen']($message['body']) > $charLimit)
		{
			if (empty($context['key_words']))
				$message['body'] = $smcFunc['substr']($message['body'], 0, $charLimit) . '<strong>...</strong>';
			else
			{
				$matchString = '';
				$force_partial_word = false;
				foreach ($context['key_words'] as $keyword)
				{
					$keyword = un_htmlspecialchars($keyword);
					$keyword = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', strtr($keyword, array('\\\'' => '\'', '&' => '&amp;')));

					if (preg_match('~[\'\.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) != 0 || preg_match('~^[\'\.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword) != 0)
						$force_partial_word = true;
					$matchString .= strtr(preg_quote($keyword, '/'), array('\*' => '.+?')) . '|';
				}
				$matchString = un_htmlspecialchars(substr($matchString, 0, -1));

				$message['body'] = un_htmlspecialchars(strtr($message['body'], array('&nbsp;' => ' ', '<br>' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@')));

				if (empty($modSettings['search_method']) || $force_partial_word)
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/is' . ($context['utf8'] ? 'u' : ''), $message['body'], $matches);
				else
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/is' . ($context['utf8'] ? 'u' : ''), $message['body'], $matches);

				$message['body'] = '';
				foreach ($matches[0] as $index => $match)
				{
					$match = strtr($smcFunc['htmlspecialchars']($match, ENT_QUOTES), array("\n" => '&nbsp;'));
					$message['body'] .= '<strong>......</strong>&nbsp;' . $match . '&nbsp;<strong>......</strong>';
				}
			}

			// Re-fix the international characters.
			$message['body'] = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $message['body']);
		}
		$message['subject_highlighted'] = highlight($message['subject'], $context['key_words']);
		$message['body_highlighted'] = highlight($message['body'], $context['key_words']);
	}
	else
	{
		$message['subject_highlighted'] = highlight($message['subject'], $context['key_words']);
		$message['body_highlighted'] = highlight($message['body'], $context['key_words']);

		// Run BBC interpreter on the message.
		$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);
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
	if (!empty($modSettings['messageIconChecks_enable']))
	{
		if (!isset($context['icon_sources'][$message['first_icon']]))
			$context['icon_sources'][$message['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
		if (!isset($context['icon_sources'][$message['last_icon']]))
			$context['icon_sources'][$message['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		if (!isset($context['icon_sources'][$message['icon']]))
			$context['icon_sources'][$message['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['icon'] . '.png') ? 'images_url' : 'default_images_url';
	}
	else
	{
		if (!isset($context['icon_sources'][$message['first_icon']]))
			$context['icon_sources'][$message['first_icon']] = 'images_url';
		if (!isset($context['icon_sources'][$message['last_icon']]))
			$context['icon_sources'][$message['last_icon']] = 'images_url';
		if (!isset($context['icon_sources'][$message['icon']]))
			$context['icon_sources'][$message['icon']] = 'images_url';
	}

	// Do we have quote tag enabled?
	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

	// Reference the main color class.
	$colorClass = 'windowbg';

	// Sticky topics should get a different color, too.
	if ($message['is_sticky'])
		$colorClass .= ' sticky';

	// Locked topics get special treatment as well.
	if ($message['locked'])
		$colorClass .= ' locked';

	$output = array_merge($context['topics'][$message['id_msg']], array(
		'id' => $message['id_topic'],
		'is_sticky' => !empty($message['is_sticky']),
		'is_locked' => !empty($message['locked']),
		'css_class' => $colorClass,
		'is_poll' => $modSettings['pollMode'] == '1' && $message['id_poll'] > 0,
		'posted_in' => !empty($participants[$message['id_topic']]),
		'views' => $message['num_views'],
		'replies' => $message['num_replies'],
		'can_reply' => in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any']),
		'can_quote' => (in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any'])) && $quote_enabled,
		'first_post' => array(
			'id' => $message['first_msg'],
			'time' => timeformat($message['first_poster_time']),
			'timestamp' => forum_time(true, $message['first_poster_time']),
			'subject' => $message['first_subject'],
			'href' => $scripturl . '?topic=' . $message['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $message['id_topic'] . '.0">' . $message['first_subject'] . '</a>',
			'icon' => $message['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$message['first_icon']]] . '/post/' . $message['first_icon'] . '.png',
			'member' => array(
				'id' => $message['first_member_id'],
				'name' => $message['first_member_name'],
				'href' => !empty($message['first_member_id']) ? $scripturl . '?action=profile;u=' . $message['first_member_id'] : '',
				'link' => !empty($message['first_member_id']) ? '<a href="' . $scripturl . '?action=profile;u=' . $message['first_member_id'] . '" title="' . $txt['profile_of'] . ' ' . $message['first_member_name'] . '">' . $message['first_member_name'] . '</a>' : $message['first_member_name']
			)
		),
		'last_post' => array(
			'id' => $message['last_msg'],
			'time' => timeformat($message['last_poster_time']),
			'timestamp' => forum_time(true, $message['last_poster_time']),
			'subject' => $message['last_subject'],
			'href' => $scripturl . '?topic=' . $message['id_topic'] . ($message['num_replies'] == 0 ? '.0' : '.msg' . $message['last_msg']) . '#msg' . $message['last_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $message['id_topic'] . ($message['num_replies'] == 0 ? '.0' : '.msg' . $message['last_msg']) . '#msg' . $message['last_msg'] . '">' . $message['last_subject'] . '</a>',
			'icon' => $message['last_icon'],
			'icon_url' => $settings[$context['icon_sources'][$message['last_icon']]] . '/post/' . $message['last_icon'] . '.png',
			'member' => array(
				'id' => $message['last_member_id'],
				'name' => $message['last_member_name'],
				'href' => !empty($message['last_member_id']) ? $scripturl . '?action=profile;u=' . $message['last_member_id'] : '',
				'link' => !empty($message['last_member_id']) ? '<a href="' . $scripturl . '?action=profile;u=' . $message['last_member_id'] . '" title="' . $txt['profile_of'] . ' ' . $message['last_member_name'] . '">' . $message['last_member_name'] . '</a>' : $message['last_member_name']
			)
		),
		'board' => array(
			'id' => $message['id_board'],
			'name' => $message['board_name'],
			'href' => $scripturl . '?board=' . $message['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $message['id_board'] . '.0">' . $message['board_name'] . '</a>'
		),
		'category' => array(
			'id' => $message['id_cat'],
			'name' => $message['cat_name'],
			'href' => $scripturl . '#c' . $message['id_cat'],
			'link' => '<a href="' . $scripturl . '#c' . $message['id_cat'] . '">' . $message['cat_name'] . '</a>'
		)
	));

	if (!empty($options['display_quick_mod']))
	{
		$started = $output['first_post']['member']['id'] == $user_info['id'];

		$output['quick_mod'] = array(
			'lock' => in_array(0, $boards_can['lock_any']) || in_array($output['board']['id'], $boards_can['lock_any']) || ($started && (in_array(0, $boards_can['lock_own']) || in_array($output['board']['id'], $boards_can['lock_own']))),
			'sticky' => (in_array(0, $boards_can['make_sticky']) || in_array($output['board']['id'], $boards_can['make_sticky'])),
			'move' => in_array(0, $boards_can['move_any']) || in_array($output['board']['id'], $boards_can['move_any']) || ($started && (in_array(0, $boards_can['move_own']) || in_array($output['board']['id'], $boards_can['move_own']))),
			'remove' => in_array(0, $boards_can['remove_any']) || in_array($output['board']['id'], $boards_can['remove_any']) || ($started && (in_array(0, $boards_can['remove_own']) || in_array($output['board']['id'], $boards_can['remove_own']))),
			'restore' => $context['can_restore_perm'] && ($modSettings['recycle_board'] == $output['board']['id']),
		);

		$context['can_lock'] |= $output['quick_mod']['lock'];
		$context['can_sticky'] |= $output['quick_mod']['sticky'];
		$context['can_move'] |= $output['quick_mod']['move'];
		$context['can_remove'] |= $output['quick_mod']['remove'];
		$context['can_merge'] |= in_array($output['board']['id'], $boards_can['merge_any']);
		$context['can_restore'] |= $output['quick_mod']['restore'];
		$context['can_markread'] = $context['user']['is_logged'];

		$context['qmod_actions'] = array('remove', 'lock', 'sticky', 'move', 'merge', 'restore', 'markread');
		call_integration_hook('integrate_quick_mod_actions_search');
	}

	$output['matches'][] = array(
		'id' => $message['id_msg'],
		'attachment' => array(),
		'member' => &$memberContext[$message['id_member']],
		'icon' => $message['icon'],
		'icon_url' => $settings[$context['icon_sources'][$message['icon']]] . '/post/' . $message['icon'] . '.png',
		'subject' => $message['subject'],
		'subject_highlighted' => $message['subject_highlighted'],
		'time' => timeformat($message['poster_time']),
		'timestamp' => forum_time(true, $message['poster_time']),
		'counter' => $counter,
		'modified' => array(
			'time' => timeformat($message['modified_time']),
			'timestamp' => forum_time(true, $message['modified_time']),
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
 * Creates a search API and returns the object.
 *
 * @return search_api_interface An instance of the search API interface
 */
function findSearchAPI()
{
	global $sourcedir, $modSettings, $searchAPI, $txt;

	require_once($sourcedir . '/Subs-Package.php');
	require_once($sourcedir . '/Class-SearchAPI.php');

	// Search has a special database set.
	db_extend('search');

	// Load up the search API we are going to use.
	$modSettings['search_index'] = empty($modSettings['search_index']) ? 'standard' : $modSettings['search_index'];
	if (!file_exists($sourcedir . '/SearchAPI-' . ucwords($modSettings['search_index']) . '.php'))
		fatal_lang_error('search_api_missing');
	require_once($sourcedir . '/SearchAPI-' . ucwords($modSettings['search_index']) . '.php');

	// Create an instance of the search API and check it is valid for this version of SMF.
	$search_class_name = $modSettings['search_index'] . '_search';
	$searchAPI = new $search_class_name();

	// An invalid Search API.
	if (!$searchAPI || !($searchAPI instanceof search_api_interface) || ($searchAPI->supportsMethod('isValid') && !$searchAPI->isValid()) || !matchPackageVersion(SMF_VERSION, $searchAPI->min_smf_version . '-' . $searchAPI->version_compatible))
	{
		// Log the error.
		loadLanguage('Errors');
		log_error(sprintf($txt['search_api_not_compatible'], 'SearchAPI-' . ucwords($modSettings['search_index']) . '.php'), 'critical');

		require_once($sourcedir . '/SearchAPI-Standard.php');
		$searchAPI = new standard_search();
	}

	return $searchAPI;
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
	$words = implode('|', array_map('preg_quote', $words));
	$highlighted = preg_filter('/' . $words . '/i', '<span class="highlight">$0</span>', $text);

	if (!empty($highlighted))
		$text = $highlighted;

	return $text;
}

?>