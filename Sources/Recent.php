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

/*	This file had one very clear purpose.  It is here expressly to find and
	retrieve information about recently posted topics, messages, and the like.

	array getLastPost()
		// !!!

	array getLastPosts(int number_of_posts)
		// !!!

	void RecentPosts()
		// !!!

	void UnreadTopics()
		// !!!
*/

// Get the latest post.
function getLastPost()
{
	global $user_info, $scripturl, $modSettings, $smcFunc;

	// Find it by the board - better to order by board than sort the entire messages table.
	$request = $smcFunc['db_query']('substring', '
		SELECT ml.poster_time, ml.subject, ml.id_topic, ml.poster_name, SUBSTRING(ml.body, 1, 385) AS body,
			ml.smileys_enabled
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = b.id_last_msg)
		WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND ml.approved = {int:is_approved}
		ORDER BY b.id_msg_updated DESC
		LIMIT 1',
		array(
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		return array();
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Censor the subject and post...
	censorText($row['subject']);
	censorText($row['body']);

	$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled']), array('<br />' => '&#10;')));
	if ($smcFunc['strlen']($row['body']) > 128)
		$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

	// Send the data.
	return array(
		'topic' => $row['id_topic'],
		'subject' => $row['subject'],
		'short_subject' => shorten_subject($row['subject'], 24),
		'preview' => $row['body'],
		'time' => timeformat($row['poster_time']),
		'timestamp' => forum_time(true, $row['poster_time']),
		'href' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
		'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new">' . $row['subject'] . '</a>'
	);
}

// Find the ten most recent posts.
function RecentPosts()
{
	global $txt, $scripturl, $user_info, $context, $modSettings, $sourcedir, $board, $smcFunc;

	loadTemplate('Recent');
	$context['page_title'] = $txt['recent_posts'];

	if (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
		$_REQUEST['start'] = 95;

	$query_parameters = array();
	if (!empty($_REQUEST['c']) && empty($board))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		if (count($_REQUEST['c']) == 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:id_cat}
				LIMIT 1',
				array(
					'id_cat' => $_REQUEST['c'][0],
				)
			);
			list ($name) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (empty($name))
				fatal_lang_error('no_access', false);

			$context['linktree'][] = array(
				'url' => $scripturl . '#c' . (int) $_REQUEST['c'],
				'name' => $name
			);
		}

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:category_list})
				AND {query_see_board}',
			array(
				'category_list' => $_REQUEST['c'],
			)
		);
		$total_cat_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$boards[] = $row['id_board'];
			$total_cat_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'b.id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;

		// If this category has a significant number of posts in it...
		if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 400 - $_REQUEST['start'] * 7);
		}

		$context['page_index'] = constructPageIndex($scripturl . '?action=recent;c=' . implode(',', $_REQUEST['c']), $_REQUEST['start'], min(100, $total_cat_posts), 10, false);
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND {query_see_board}
			LIMIT {int:limit}',
			array(
				'board_list' => $_REQUEST['boards'],
				'limit' => count($_REQUEST['boards']),
			)
		);
		$total_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$boards[] = $row['id_board'];
			$total_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'b.id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;

		// If these boards have a significant number of posts in them...
		if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 500 - $_REQUEST['start'] * 9);
		}

		$context['page_index'] = constructPageIndex($scripturl . '?action=recent;boards=' . implode(',', $_REQUEST['boards']), $_REQUEST['start'], min(100, $total_posts), 10, false);
	}
	elseif (!empty($board))
	{
		$request = $smcFunc['db_query']('', '
			SELECT num_posts
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);
		list ($total_posts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$query_this_board = 'b.id_board = {int:board}';
		$query_parameters['board'] = $board;

		// If this board has a significant number of posts in it...
		if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 600 - $_REQUEST['start'] * 10);
		}

		$context['page_index'] = constructPageIndex($scripturl . '?action=recent;board=' . $board . '.%1$d', $_REQUEST['start'], min(100, $total_posts), 10, true);
	}
	else
	{
		$query_this_board = '{query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
					AND b.id_board != {int:recycle_board}' : ''). '
					AND m.id_msg >= {int:max_id_msg}';
		$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 100 - $_REQUEST['start'] * 6);
		$query_parameters['recycle_board'] = $modSettings['recycle_board'];

		// !!! This isn't accurate because we ignore the recycle bin.
		$context['page_index'] = constructPageIndex($scripturl . '?action=recent', $_REQUEST['start'], min(100, $modSettings['totalMessages']), 10, false);
	}

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=recent' . (empty($board) ? (empty($_REQUEST['c']) ? '' : ';c=' . (int) $_REQUEST['c']) : ';board=' . $board . '.0'),
		'name' => $context['page_title']
	);

	$key = 'recent-' . $user_info['id'] . '-' . md5(serialize(array_diff_key($query_parameters, array('max_id_msg' => 0)))) . '-' . (int) $_REQUEST['start'];
	if (empty($modSettings['cache_enable']) || ($messages = cache_get_data($key, 120)) == null)
	{
		$done = false;
		while (!$done)
		{
			// Find the 10 most recent messages they can *view*.
			// !!!SLOW This query is really slow still, probably?
			$request = $smcFunc['db_query']('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				WHERE ' . $query_this_board . '
					AND m.approved = {int:is_approved}
				ORDER BY m.id_msg DESC
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'is_approved' => 1,
					'offset' => $_REQUEST['start'],
					'limit' => 10,
				))
			);
			// If we don't have 10 results, try again with an unoptimized version covering all rows, and cache the result.
			if (isset($query_parameters['max_id_msg']) && $smcFunc['db_num_rows']($request) < 10)
			{
				$smcFunc['db_free_result']($request);
				$query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $query_this_board);
				$cache_results = true;
				unset($query_parameters['max_id_msg']);
			}
			else
				$done = true;
		}
		$messages = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$messages[] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
		if (!empty($cache_results))
			cache_put_data($key, $messages, 120);
	}

	// Nothing here... Or at least, nothing you can see...
	if (empty($messages))
	{
		$context['posts'] = array();
		return;
	}

	// Get all the most recent posts.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic, t.id_board, b.id_cat,
			b.name AS bname, c.name AS cname, t.num_replies, m.id_member, m2.id_member AS id_first_member,
			IFNULL(mem2.real_name, m2.poster_name) AS first_poster_name, t.id_first_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
		ORDER BY m.id_msg DESC
		LIMIT ' . count($messages),
		array(
			'message_list' => $messages,
		)
	);
	$counter = $_REQUEST['start'] + 1;
	$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor everything.
		censorText($row['body']);
		censorText($row['subject']);

		// BBC-atize the message.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And build the array.
		$context['posts'][$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cname'],
				'href' => $scripturl . '#c' . $row['id_cat'],
				'link' => '<a href="' . $scripturl . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'first_poster' => array(
				'id' => $row['id_first_member'],
				'name' => $row['first_poster_name'],
				'href' => empty($row['id_first_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_first_member'],
				'link' => empty($row['id_first_member']) ? $row['first_poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '">' . $row['first_poster_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'message' => $row['body'],
			'can_reply' => false,
			'can_mark_notify' => false,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
		);

		if ($user_info['id'] == $row['id_first_member'])
			$board_ids['own'][$row['id_board']][] = $row['id_msg'];
		$board_ids['any'][$row['id_board']][] = $row['id_msg'];
	}
	$smcFunc['db_free_result']($request);

	// There might be - and are - different permissions between any and own.
	$permissions = array(
		'own' => array(
			'post_reply_own' => 'can_reply',
			'delete_own' => 'can_delete',
		),
		'any' => array(
			'post_reply_any' => 'can_reply',
			'mark_any_notify' => 'can_mark_notify',
			'delete_any' => 'can_delete',
		)
	);

	// Now go through all the permissions, looking for boards they can do it on.
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// They can do it on these boards...
			$boards = boardsAllowedTo($permission);

			// If 0 is the only thing in the array, they can do it everywhere!
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Go through the boards, and look for posts they can do this on.
			foreach ($boards as $board_id)
			{
				// Hmm, they have permission, but there are no topics from that board on this page.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Okay, looks like they can do it for these posts.
				foreach ($board_ids[$type][$board_id] as $counter)
					if ($type == 'any' || $context['posts'][$counter]['poster']['id'] == $user_info['id'])
						$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		// Some posts - the first posts - can't just be deleted.
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];

		// And some cannot be quoted...
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}
}

// Find unread topics and replies.
function UnreadTopics()
{
	global $board, $txt, $scripturl, $sourcedir;
	global $user_info, $context, $settings, $modSettings, $smcFunc, $options;

	// Guests can't have unread things, we don't know anything about them.
	is_not_guest();

	// Prefetching + lots of MySQL work = bad mojo.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Forbidden');
		die;
	}

	$context['showing_all_topics'] = isset($_GET['all']);
	$context['start'] = (int) $_REQUEST['start'];
	$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) && !WIRELESS ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	if ($_REQUEST['action'] == 'unread')
		$context['page_title'] = $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'];
	else
		$context['page_title'] = $txt['unread_replies'];

	if ($context['showing_all_topics'] && !empty($context['load_average']) && !empty($modSettings['loadavg_allunread']) && $context['load_average'] >= $modSettings['loadavg_allunread'])
		fatal_lang_error('loadavg_allunread_disabled', false);
	elseif ($_REQUEST['action'] != 'unread' && !empty($context['load_average']) && !empty($modSettings['loadavg_unreadreplies']) && $context['load_average'] >= $modSettings['loadavg_unreadreplies'])
		fatal_lang_error('loadavg_unreadreplies_disabled', false);
	elseif (!$context['showing_all_topics'] && $_REQUEST['action'] == 'unread' && !empty($context['load_average']) && !empty($modSettings['loadavg_unread']) && $context['load_average'] >= $modSettings['loadavg_unread'])
		fatal_lang_error('loadavg_unread_disabled', false);

	// Parameters for the main query.
	$query_parameters = array();

	// Are we specifying any specific board?
	if (isset($_REQUEST['children']) && (!empty($board) || !empty($_REQUEST['boards'])))
	{
		$boards = array();

		if (!empty($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}

		if (!empty($board))
			$boards[] = (int) $board;

		// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.id_parent
			FROM {db_prefix}boards AS b
			WHERE {query_wanna_see_board}
				AND b.child_level > {int:no_child}
				AND b.id_board NOT IN ({array_int:boards})
			ORDER BY child_level ASC
			',
			array(
				'no_child' => 0,
				'boards' => $boards,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (in_array($row['id_parent'], $boards))
				$boards[] = $row['id_board'];

		$smcFunc['db_free_result']($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%d';
	}
	elseif (!empty($board))
	{
		$query_this_board = 'id_board = {int:board}';
		$query_parameters['board'] = $board;
		$context['querystring_board_limits'] = ';board=' . $board . '.%1$d';
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})',
			array(
				'board_list' => $_REQUEST['boards'],
			)
		);
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%1$d';
	}
	elseif (!empty($_REQUEST['c']))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		$see_board = isset($_REQUEST['action']) && $_REQUEST['action'] == 'unreadreplies' ? 'query_see_board' : 'query_wanna_see_board';
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE ' . $user_info[$see_board] . '
				AND b.id_cat IN ({array_int:id_cat})',
			array(
				'id_cat' => $_REQUEST['c'],
			)
		);
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';c=' . implode(',', $_REQUEST['c']) . ';start=%1$d';
	}
	else
	{
		$see_board = isset($_REQUEST['action']) && $_REQUEST['action'] == 'unreadreplies' ? 'query_see_board' : 'query_wanna_see_board';
		// Don't bother to show deleted posts!
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE ' . $user_info[$see_board] . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : ''),
			array(
				'recycle_board' => (int) $modSettings['recycle_board'],
			)
		);
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';start=%1$d';
		$context['no_board_limits'] = true;
	}

	$sort_methods = array(
		'subject' => 'ms.subject',
		'starter' => 'IFNULL(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	// The default is the most logical: newest first.
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = 'last_post';
		$_REQUEST['sort'] = 't.id_last_msg';
		$ascending = isset($_REQUEST['asc']);

		$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
	}
	// But, for other methods the default sort is ascending.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);

		$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
	}
	$context['sort_direction'] = $ascending ? 'up' : 'down';

	if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1)
	{
		$request = $smcFunc['db_query']('', '
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat = {int:id_cat}
			LIMIT 1',
			array(
				'id_cat' => (int) $_REQUEST['c'][0],
			)
		);
		list ($name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['linktree'][] = array(
			'url' => $scripturl . '#c' . (int) $_REQUEST['c'][0],
			'name' => $name
		);
	}

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=' . $_REQUEST['action'] . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
		'name' => $_REQUEST['action'] == 'unread' ? $txt['unread_topics_visit'] : $txt['unread_replies']
	);

	if ($context['showing_all_topics'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=' . $_REQUEST['action'] . ';all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
			'name' => $txt['unread_topics_all']
		);
	else
		$txt['unread_topics_visit_none'] = strtr($txt['unread_topics_visit_none'], array('?action=unread;all' => '?action=unread;all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits']));

	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_recent';
	else
	{
		loadTemplate('Recent');
		$context['sub_template'] = $_REQUEST['action'] == 'unread' ? 'unread' : 'replies';
	}

	// Setup the default topic icons... for checking they exist and the like ;)
	$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless', 'clip');
	$context['icon_sources'] = array();
	foreach ($stable_icons as $icon)
		$context['icon_sources'][$icon] = 'images_url';

	$is_topics = $_REQUEST['action'] == 'unread';

	// This part is the same for each query.
	$select_clause = '
				ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
				t.num_replies, t.num_views, ms.id_member AS id_first_member, ml.id_member AS id_last_member,
				ml.poster_time AS last_poster_time, IFNULL(mems.real_name, ms.poster_name) AS first_poster_name,
				IFNULL(meml.real_name, ml.poster_name) AS last_poster_name, ml.subject AS last_subject,
				ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
				IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from, SUBSTRING(ml.body, 1, 385) AS last_body,
				SUBSTRING(ms.body, 1, 385) AS first_body, ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg';

	if ($context['showing_all_topics'])
	{
		if (!empty($board))
		{
			$request = $smcFunc['db_query']('', '
				SELECT MIN(id_msg)
				FROM {db_prefix}log_mark_read
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
				)
			);
			list ($earliest_msg) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT MIN(lmr.id_msg)
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
				WHERE {query_see_board}',
				array(
					'current_member' => $user_info['id'],
				)
			);
			list ($earliest_msg) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// This is needed in case of topics marked unread.
		if (empty($earliest_msg))
			$earliest_msg = 0;
		else
		{
			// Using caching, when possible, to ignore the below slow query.
			if (isset($_SESSION['cached_log_time']) && $_SESSION['cached_log_time'][0] + 45 > time())
				$earliest_msg2 = $_SESSION['cached_log_time'][1];
			else
			{
				// This query is pretty slow, but it's needed to ensure nothing crucial is ignored.
				$request = $smcFunc['db_query']('', '
					SELECT MIN(id_msg)
					FROM {db_prefix}log_topics
					WHERE id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
					)
				);
				list ($earliest_msg2) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// In theory this could be zero, if the first ever post is unread, so fudge it ;)
				if ($earliest_msg2 == 0)
					$earliest_msg2 = -1;

				$_SESSION['cached_log_time'] = array(time(), $earliest_msg2);
			}

			$earliest_msg = min($earliest_msg2, $earliest_msg);
		}
	}

	// !!! Add modified_time in for log_time check?

	if ($modSettings['totalMessages'] > 100000 && $context['showing_all_topics'])
	{
		$smcFunc['db_query']('', '
			DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
			array(
			)
		);

		// Let's copy things out of the log_topics table, to reduce searching.
		$have_temp_table = $smcFunc['db_query']('', '
			CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}
				AND t.' . $query_this_board . (empty($earliest_msg) ? '' : '
				AND t.id_last_msg > {int:earliest_msg}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
				'db_error_skip' => true,
			))
		) !== false;
	}
	else
		$have_temp_table = false;

	if ($context['showing_all_topics'] && $have_temp_table)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . (!empty($earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : '') . '
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			// Mark the boards as read if there are no unread topics!
			require_once($sourcedir . '/Subs-Boards.php');
			markBoardsRead(empty($boards) ? $board : $boards);

			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%1$d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = $smcFunc['db_query']('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE b.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . '
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'min_message' => $min_message,
				'is_approved' => 1,
				'sort' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			))
		);
	}
	elseif ($is_topics)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . ($context['showing_all_topics'] && !empty($earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : (!$context['showing_all_topics'] && empty($_SESSION['first_login']) ? '
				AND t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'id_msg_last_visit' => $_SESSION['id_msg_last_visit'],
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			// Is this an all topics query?
			if ($context['showing_all_topics'])
			{
				// Since there are no unread topics, mark the boards as read!
				require_once($sourcedir . '/Subs-Boards.php');
				markBoardsRead(empty($boards) ? $board : $boards);
			}

			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = $smcFunc['db_query']('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < ml.id_msg' . ($modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . '
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'min_message' => $min_message,
				'is_approved' => 1,
				'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			))
		);
	}
	else
	{
		if ($modSettings['totalMessages'] > 100000)
		{
			$smcFunc['db_query']('', '
				DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
				array(
				)
			);

			$smcFunc['db_query']('', '
				DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
				array(
				)
			);

			$sortKey_joins = array(
				'ms.subject' => '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
				'IFNULL(mems.real_name, ms.poster_name)' => '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
			);

			// The main benefit of this temporary table is not that it's faster; it's that it avoids locks later.
			$have_temp_table = $smcFunc['db_query']('', '
				CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
					id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
					id_board smallint(5) unsigned NOT NULL default {string:string_zero},
					id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
					id_msg int(10) unsigned NOT NULL default {string:string_zero},
					PRIMARY KEY (id_topic)
				)
				SELECT t.id_topic, t.id_board, t.id_last_msg, IFNULL(lmr.id_msg, 0) AS id_msg' . (!in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? ', ' . $_REQUEST['sort'] . ' AS sort_key' : '') . '
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$_REQUEST['sort']]) ? $sortKey_joins[$_REQUEST['sort']] : '') . '
				WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
					AND t.id_board = {int:current_board}' : '') . ($modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . '
				GROUP BY m.id_topic',
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
					'is_approved' => 1,
					'string_zero' => '0',
					'db_error_skip' => true,
				)
			) !== false;

			// If that worked, create a sample of the log_topics table too.
			if ($have_temp_table)
				$have_temp_table = $smcFunc['db_query']('', '
					CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
						PRIMARY KEY (id_topic)
					)
					SELECT lt.id_topic, lt.id_msg
					FROM {db_prefix}log_topics AS lt
						INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
					WHERE lt.id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'db_error_skip' => true,
					)
				) !== false;
		}

		if (!empty($have_temp_table))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}topics_posted_in AS pi
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
				WHERE pi.' . $query_this_board . '
					AND IFNULL(lt.id_msg, pi.id_msg) < pi.id_last_msg',
				array_merge($query_parameters, array(
				))
			);
			list ($num_topics) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$request = $smcFunc['db_query']('unread_fetch_topic_count', '
				SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . '
					AND m.id_member = {int:current_member}
					AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : ''),
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'is_approved' => 1,
				))
			);
			list ($num_topics, $min_message) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		if (!empty($have_temp_table))
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic
				FROM {db_prefix}topics_posted_in AS t
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
				WHERE t.' . $query_this_board . '
					AND IFNULL(lt.id_msg, t.id_msg) < t.id_last_msg
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'order' => (in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? $_REQUEST['sort'] : 't.sort_key') . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => $context['topics_per_page'],
				))
			);
		else
			$request = $smcFunc['db_query']('unread_replies', '
				SELECT DISTINCT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($_REQUEST['sort'], 'ms.') === false ? '' : '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($_REQUEST['sort'], 'mems.') === false ? '' : '
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . '
					AND t.id_last_msg >= {int:min_message}
					AND (IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0))) < t.id_last_msg
					AND t.approved = {int:is_approved}
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'min_message' => (int) $min_message,
					'is_approved' => 1,
					'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => $context['topics_per_page'],
					'sort' => $_REQUEST['sort'],
				))
			);

		$topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topics[] = $row['id_topic'];
		$smcFunc['db_free_result']($request);

		// Sanity... where have you gone?
		if (empty($topics))
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		$request = $smcFunc['db_query']('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
			LIMIT ' . count($topics),
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
			)
		);
	}

	$context['topics'] = array();
	$topic_ids = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
			continue;

		$topic_ids[] = $row['id_topic'];

		if (!empty($settings['message_index_preview']))
		{
			// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br />' => '&#10;')));
			if ($smcFunc['strlen']($row['first_body']) > 128)
				$row['first_body'] = $smcFunc['substr']($row['first_body'], 0, 128) . '...';
			$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br />' => '&#10;')));
			if ($smcFunc['strlen']($row['last_body']) > 128)
				$row['last_body'] = $smcFunc['substr']($row['last_body'], 0, 128) . '...';

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) && !WIRELESS ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		if ($topic_length > $messages_per_page)
		{
			$tmppages = array();
			$tmpa = 1;
			for ($tmpb = 0; $tmpb < $topic_length; $tmpb += $messages_per_page)
			{
				$tmppages[] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.' . $tmpb . ';topicseen">' . $tmpa . '</a>';
				$tmpa++;
			}
			// Show links to all the pages?
			if (count($tmppages) <= 5)
				$pages = '&#171; ' . implode(' ', $tmppages);
			// Or skip a few?
			else
				$pages = '&#171; ' . $tmppages[0] . ' ' . $tmppages[1] . ' ... ' . $tmppages[count($tmppages) - 2] . ' ' . $tmppages[count($tmppages) - 1];

			if (!empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
			$pages .= ' &#187;';
		}
		else
			$pages = '';

		// We need to check the topic icons exist... you can never be too sure!
		if (empty($modSettings['messageIconChecks_disable']))
		{
			// First icon first... as you'd expect.
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.gif') ? 'images_url' : 'default_images_url';
			// Last icon... last... duh.
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.gif') ? 'images_url' : 'default_images_url';
		}

		// And build the array.
		$context['topics'][$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'name' => $row['first_poster_name'],
					'id' => $row['id_first_member'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_first_member'],
					'link' => !empty($row['id_first_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_poster_name'] . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
				),
				'time' => timeformat($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.gif',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'name' => $row['last_poster_name'],
					'id' => $row['id_last_member'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_last_member'],
					'link' => !empty($row['id_last_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_last_member'] . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name']
				),
				'time' => timeformat($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'icon' => $row['last_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.gif',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
			),
			'new_from' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen' . ($row['num_replies'] == 0 ? '' : 'new'),
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
			'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
			'is_hot' => $row['num_replies'] >= $modSettings['hotTopicPosts'],
			'is_very_hot' => $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
			'is_posted_in' => false,
			'icon' => $row['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.gif',
			'subject' => $row['first_subject'],
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views']),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			)
		);

		determineTopicClass($context['topics'][$row['id_topic']]);
	}
	$smcFunc['db_free_result']($request);

	if ($is_topics && !empty($modSettings['enableParticipation']) && !empty($topic_ids))
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
				'topic_list' => $topic_ids,
				'limit' => count($topic_ids),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			if (empty($context['topics'][$row['id_topic']]['is_posted_in']))
			{
				$context['topics'][$row['id_topic']]['is_posted_in'] = true;
				$context['topics'][$row['id_topic']]['class'] = 'my_' . $context['topics'][$row['id_topic']]['class'];
			}
		}
		$smcFunc['db_free_result']($result);
	}

	$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
	$context['topics_to_mark'] = implode('-', $topic_ids);
}

?>