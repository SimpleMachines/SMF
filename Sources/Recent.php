<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
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
use SMF\Config;
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get the latest post made on the system
 *
 * - respects approved, recycled, and board permissions
 *
 * @return array An array of information about the last post that you can see
 */
function getLastPost()
{
	// Find it by the board - better to order by board than sort the entire messages table.
	$request = Db::$db->query('substring', '
		SELECT m.poster_time, m.subject, m.id_topic, m.poster_name, SUBSTRING(m.body, 1, 385) AS body,
			m.smileys_enabled
		FROM {db_prefix}messages AS m' . (!empty(Config::$modSettings['postmod_active']) ? '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
		WHERE {query_wanna_see_message_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
			AND m.id_board != {int:recycle_board}' : '') . (!empty(Config::$modSettings['postmod_active']) ? '
			AND m.approved = {int:is_approved}
			AND t.approved = {int:is_approved}' : '') . '
		ORDER BY m.id_msg DESC
		LIMIT 1',
		array(
			'recycle_board' => Config::$modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	if (Db::$db->num_rows($request) == 0)
		return array();
	$row = Db::$db->fetch_assoc($request);
	Db::$db->free_result($request);

	// Censor the subject and post...
	Lang::censorText($row['subject']);
	Lang::censorText($row['body']);

	$row['body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['body'], $row['smileys_enabled']), array('<br>' => '&#10;')));
	if (Utils::entityStrlen($row['body']) > 128)
		$row['body'] = Utils::entitySubstr($row['body'], 0, 128) . '...';

	// Send the data.
	return array(
		'topic' => $row['id_topic'],
		'subject' => $row['subject'],
		'short_subject' => shorten_subject($row['subject'], 24),
		'preview' => $row['body'],
		'time' => timeformat($row['poster_time']),
		'timestamp' => $row['poster_time'],
		'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
		'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new">' . $row['subject'] . '</a>'
	);
}

/**
 * Find the ten most recent posts.
 */
function RecentPosts()
{
	loadTemplate('Recent');
	Utils::$context['page_title'] = Lang::$txt['recent_posts'];
	Utils::$context['sub_template'] = 'recent';

	Utils::$context['is_redirect'] = false;

	if (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
		$_REQUEST['start'] = 95;

	$_REQUEST['start'] = (int) $_REQUEST['start'];

	$query_parameters = array();
	if (!empty($_REQUEST['c']) && empty(Board::$info->id))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		if (count($_REQUEST['c']) == 1)
		{
			$request = Db::$db->query('', '
				SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:id_cat}
				LIMIT 1',
				array(
					'id_cat' => $_REQUEST['c'][0],
				)
			);
			list ($name) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (empty($name))
				fatal_lang_error('no_access', false);

			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '#c' . (int) $_REQUEST['c'],
				'name' => $name
			);
		}

		$recycling = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']);

		$request = Db::$db->query('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:category_list})
				AND b.redirect = {string:empty}' . ($recycling ? '
				AND b.id_board != {int:recycle_board}' : '') . '
				AND {query_wanna_see_board}',
			array(
				'category_list' => $_REQUEST['c'],
				'empty' => '',
				'recycle_board' => !empty(Config::$modSettings['recycle_board']) ? Config::$modSettings['recycle_board'] : 0,
			)
		);
		$total_cat_posts = 0;
		$boards = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			$boards[] = $row['id_board'];
			$total_cat_posts += $row['num_posts'];
		}
		Db::$db->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'm.id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;

		// If this category has a significant number of posts in it...
		if ($total_cat_posts > 100 && $total_cat_posts > Config::$modSettings['totalMessages'] / 15)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 400 - $_REQUEST['start'] * 7);
		}

		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=recent;c=' . implode(',', $_REQUEST['c']), $_REQUEST['start'], min(100, $total_cat_posts), 10, false);
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = Db::$db->query('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND b.redirect = {string:empty}
				AND {query_see_board}
			LIMIT {int:limit}',
			array(
				'board_list' => $_REQUEST['boards'],
				'limit' => count($_REQUEST['boards']),
				'empty' => '',
			)
		);
		$total_posts = 0;
		$boards = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			$boards[] = $row['id_board'];
			$total_posts += $row['num_posts'];
		}
		Db::$db->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'm.id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;

		// If these boards have a significant number of posts in them...
		if ($total_posts > 100 && $total_posts > Config::$modSettings['totalMessages'] / 12)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 500 - $_REQUEST['start'] * 9);
		}

		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=recent;boards=' . implode(',', $_REQUEST['boards']), $_REQUEST['start'], min(100, $total_posts), 10, false);
	}
	elseif (!empty(Board::$info->id))
	{
		$request = Db::$db->query('', '
			SELECT num_posts, redirect
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => Board::$info->id,
			)
		);
		list ($total_posts, $redirect) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// If this is a redirection board, don't bother counting topics here...
		if ($redirect != '')
		{
			$total_posts = 0;
			Utils::$context['is_redirect'] = true;
		}

		$query_this_board = 'm.id_board = {int:board}';
		$query_parameters['board'] = Board::$info->id;

		// If this board has a significant number of posts in it...
		if ($total_posts > 80 && $total_posts > Config::$modSettings['totalMessages'] / 10)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 600 - $_REQUEST['start'] * 10);
		}

		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=recent;board=' . Board::$info->id . '.%1$d', $_REQUEST['start'], min(100, $total_posts), 10, true);
	}
	else
	{
		$query_this_board = '{query_wanna_see_message_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
					AND m.id_board != {int:recycle_board}' : '') . '
					AND m.id_msg >= {int:max_id_msg}';
		$query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 100 - $_REQUEST['start'] * 6);
		$query_parameters['recycle_board'] = Config::$modSettings['recycle_board'];

		$query_these_boards = '{query_wanna_see_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
					AND b.id_board != {int:recycle_board}' : '');
		$query_these_boards_params = $query_parameters;
		unset($query_these_boards_params['max_id_msg']);

		$get_num_posts = Db::$db->query('', '
			SELECT COALESCE(SUM(b.num_posts), 0)
			FROM {db_prefix}boards AS b
			WHERE ' . $query_these_boards . '
				AND b.redirect = {string:empty}',
			array_merge($query_these_boards_params, array('empty' => ''))
		);

		list($db_num_posts) = Db::$db->fetch_row($get_num_posts);
		$num_posts = min(100, $db_num_posts);

		Db::$db->free_result($get_num_posts);

		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=recent', $_REQUEST['start'], $num_posts, 10, false);
	}

	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=recent' . (empty(Board::$info->id) ? (empty($_REQUEST['c']) ? '' : ';c=' . (int) $_REQUEST['c']) : ';board=' . Board::$info->id . '.0'),
		'name' => Utils::$context['page_title']
	);

	// If you selected a redirection board, don't try getting posts for it...
	if (Utils::$context['is_redirect'])
		$messages = 0;

	$key = 'recent-' . User::$me->id . '-' . md5(Utils::jsonEncode(array_diff_key($query_parameters, array('max_id_msg' => 0)))) . '-' . (int) $_REQUEST['start'];
	if (!Utils::$context['is_redirect'] && (empty(CacheApi::$enable) || ($messages = CacheApi::get($key, 120)) == null))
	{
		$done = false;
		while (!$done)
		{
			// Find the 10 most recent messages they can *view*.
			// @todo SLOW This query is really slow still, probably?
			$request = Db::$db->query('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m ' . (!empty(Config::$modSettings['postmod_active']) ? '
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
				WHERE ' . $query_this_board . (!empty(Config::$modSettings['postmod_active']) ? '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}' : '') . '
				ORDER BY m.id_msg DESC
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'is_approved' => 1,
					'offset' => $_REQUEST['start'],
					'limit' => 10,
				))
			);
			// If we don't have 10 results, try again with an unoptimized version covering all rows, and cache the result.
			if (isset($query_parameters['max_id_msg']) && Db::$db->num_rows($request) < 10)
			{
				Db::$db->free_result($request);
				$query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $query_this_board);
				$cache_results = true;
				unset($query_parameters['max_id_msg']);
			}
			else
				$done = true;
		}
		$messages = array();
		while ($row = Db::$db->fetch_assoc($request))
			$messages[] = $row['id_msg'];
		Db::$db->free_result($request);
		if (!empty($cache_results))
			CacheApi::put($key, $messages, 120);
	}

	// Nothing here... Or at least, nothing you can see...
	if (empty($messages))
	{
		Utils::$context['posts'] = array();
		return;
	}

	// Get all the most recent posts.
	$request = Db::$db->query('', '
		SELECT
			m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic, t.id_board, b.id_cat,
			b.name AS bname, c.name AS cname, t.num_replies, m.id_member, m2.id_member AS id_first_member,
			COALESCE(mem2.real_name, m2.poster_name) AS first_poster_name, t.id_first_msg,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'message_list' => $messages,
			'limit' => count($messages),
		)
	);
	$counter = $_REQUEST['start'] + 1;
	Utils::$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Censor everything.
		Lang::censorText($row['body']);
		Lang::censorText($row['subject']);

		// BBC-atize the message.
		$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And build the array.
		Utils::$context['posts'][$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cname'],
				'href' => Config::$scripturl . '#c' . $row['id_cat'],
				'link' => '<a href="' . Config::$scripturl . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow" title="' . $row['subject'] . '">' . shorten_subject($row['subject'], 30) . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'shorten_subject' => shorten_subject($row['subject'], 30),
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time'],
			'first_poster' => array(
				'id' => $row['id_first_member'],
				'name' => $row['first_poster_name'],
				'href' => empty($row['id_first_member']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_first_member'],
				'link' => empty($row['id_first_member']) ? $row['first_poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_first_member'] . '">' . $row['first_poster_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'message' => $row['body'],
			'can_reply' => false,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty(Config::$modSettings['edit_disable_time']) || $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 >= time()),
			'css_class' => 'windowbg',
		);

		if (User::$me->id == $row['id_first_member'])
			$board_ids['own'][$row['id_board']][] = $row['id_msg'];
		$board_ids['any'][$row['id_board']][] = $row['id_msg'];
	}
	Db::$db->free_result($request);

	// There might be - and are - different permissions between any and own.
	$permissions = array(
		'own' => array(
			'post_reply_own' => 'can_reply',
			'delete_own' => 'can_delete',
		),
		'any' => array(
			'post_reply_any' => 'can_reply',
			'delete_any' => 'can_delete',
		)
	);

	// Create an array for the permissions.
	$boards_can = boardsAllowedTo(array_keys(iterator_to_array(
		new RecursiveIteratorIterator(new RecursiveArrayIterator($permissions)))
	), true, false);

	// Now go through all the permissions, looking for boards they can do it on.
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// They can do it on these boards...
			$boards = $boards_can[$permission];

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
					if ($type == 'any' || Utils::$context['posts'][$counter]['poster']['id'] == User::$me->id)
						Utils::$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	$quote_enabled = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));
	foreach (Utils::$context['posts'] as $counter => $dummy)
	{
		// Some posts - the first posts - can't just be deleted.
		Utils::$context['posts'][$counter]['can_delete'] &= Utils::$context['posts'][$counter]['delete_possible'];

		// And some cannot be quoted...
		Utils::$context['posts'][$counter]['can_quote'] = Utils::$context['posts'][$counter]['can_reply'] && $quote_enabled;
	}

	// Last but not least, the quickbuttons
	foreach (Utils::$context['posts'] as $key => $post)
	{
		Utils::$context['posts'][$key]['quickbuttons'] = array(
			'reply' => array(
				'label' => Lang::$txt['reply'],
				'href' => Config::$scripturl.'?action=post;topic='.$post['topic'].'.'.$post['start'],
				'icon' => 'reply_button',
				'show' => $post['can_reply']
			),
			'quote' => array(
				'label' => Lang::$txt['quote_action'],
				'href' => Config::$scripturl.'?action=post;topic='.$post['topic'].'.'.$post['start'].';quote='.$post['id'],
				'icon' => 'quote',
				'show' => $post['can_quote']
			),
			'delete' => array(
				'label' => Lang::$txt['remove'],
				'href' => Config::$scripturl.'?action=deletemsg;msg='.$post['id'].';topic='.$post['topic'].';recent;'.Utils::$context['session_var'].'='.Utils::$context['session_id'],
				'javascript' => 'data-confirm="'.Lang::$txt['remove_message'].'"',
				'class' => 'you_sure',
				'icon' => 'remove_button',
				'show' => $post['can_delete']
			),
		);
	}

	// Allow last minute changes.
	call_integration_hook('integrate_recent_RecentPosts');
}

/**
 * Find unread topics and replies.
 */
function UnreadTopics()
{
	global $settings, $options;

	// Guests can't have unread things, we don't know anything about them.
	is_not_guest();

	// Prefetching + lots of MySQL work = bad mojo.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		send_http_status(403);
		die;
	}

	Utils::$context['showCheckboxes'] = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1;

	Utils::$context['showing_all_topics'] = isset($_GET['all']);
	Utils::$context['start'] = (int) $_REQUEST['start'];
	Utils::$context['topics_per_page'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'];
	if ($_REQUEST['action'] == 'unread')
		Utils::$context['page_title'] = Utils::$context['showing_all_topics'] ? Lang::$txt['unread_topics_all'] : Lang::$txt['unread_topics_visit'];
	else
		Utils::$context['page_title'] = Lang::$txt['unread_replies'];

	if (Utils::$context['showing_all_topics'] && !empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_allunread']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_allunread'])
		fatal_lang_error('loadavg_allunread_disabled', false);
	elseif ($_REQUEST['action'] != 'unread' && !empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_unreadreplies']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_unreadreplies'])
		fatal_lang_error('loadavg_unreadreplies_disabled', false);
	elseif (!Utils::$context['showing_all_topics'] && $_REQUEST['action'] == 'unread' && !empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_unread']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_unread'])
		fatal_lang_error('loadavg_unread_disabled', false);

	// Parameters for the main query.
	$query_parameters = array();

	// Are we specifying any specific board?
	if (isset($_REQUEST['children']) && (!empty(Board::$info->id) || !empty($_REQUEST['boards'])))
	{
		$boards = array();

		if (!empty($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}

		if (!empty(Board::$info->id))
			$boards[] = (int) Board::$info->id;

		// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
		$request = Db::$db->query('', '
			SELECT b.id_board, b.id_parent
			FROM {db_prefix}boards AS b
			WHERE {query_wanna_see_board}
				AND b.child_level > {int:no_child}
				AND b.id_board NOT IN ({array_int:boards})
			ORDER BY child_level ASC',
			array(
				'no_child' => 0,
				'boards' => $boards,
			)
		);

		while ($row = Db::$db->fetch_assoc($request))
			if (in_array($row['id_parent'], $boards))
				$boards[] = $row['id_board'];

		Db::$db->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		Utils::$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%d';
	}
	elseif (!empty(Board::$info->id))
	{
		$query_this_board = 'id_board = {int:board}';
		$query_parameters['board'] = Board::$info->id;
		Utils::$context['querystring_board_limits'] = ';board=' . Board::$info->id . '.%1$d';
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = Db::$db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})',
			array(
				'board_list' => $_REQUEST['boards'],
			)
		);
		$boards = array();
		while ($row = Db::$db->fetch_assoc($request))
			$boards[] = $row['id_board'];
		Db::$db->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		Utils::$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%1$d';
	}
	elseif (!empty($_REQUEST['c']))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		$see_board = isset($_REQUEST['action']) && $_REQUEST['action'] == 'unreadreplies' ? 'query_see_board' : 'query_wanna_see_board';

		$request = Db::$db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE ' . User::$me->{$see_board} . '
				AND b.id_cat IN ({array_int:id_cat})',
			array(
				'id_cat' => $_REQUEST['c'],
			)
		);
		$boards = array();
		while ($row = Db::$db->fetch_assoc($request))
			$boards[] = $row['id_board'];
		Db::$db->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		Utils::$context['querystring_board_limits'] = ';c=' . implode(',', $_REQUEST['c']) . ';start=%1$d';
	}
	else
	{
		$see_board = isset($_REQUEST['action']) && $_REQUEST['action'] == 'unreadreplies' ? 'query_see_board' : 'query_wanna_see_board';
		// Don't bother to show deleted posts!
		$request = Db::$db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE ' . User::$me->{$see_board} . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : ''),
			array(
				'recycle_board' => (int) Config::$modSettings['recycle_board'],
			)
		);
		$boards = array();
		while ($row = Db::$db->fetch_assoc($request))
			$boards[] = $row['id_board'];
		Db::$db->free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_available', false);

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		Utils::$context['querystring_board_limits'] = ';start=%1$d';
		Utils::$context['no_board_limits'] = true;
	}

	$sort_methods = array(
		'subject' => 'ms.subject',
		'starter' => 'COALESCE(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	// The default is the most logical: newest first.
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		Utils::$context['sort_by'] = 'last_post';
		$_REQUEST['sort'] = 't.id_last_msg';
		$ascending = isset($_REQUEST['asc']);

		Utils::$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
	}
	// But, for other methods the default sort is ascending.
	else
	{
		Utils::$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);

		Utils::$context['querystring_sort_limits'] = ';sort=' . Utils::$context['sort_by'] . ($ascending ? '' : ';desc');
	}
	Utils::$context['sort_direction'] = $ascending ? 'up' : 'down';

	if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1)
	{
		$request = Db::$db->query('', '
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat = {int:id_cat}
			LIMIT 1',
			array(
				'id_cat' => (int) $_REQUEST['c'][0],
			)
		);
		list ($name) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '#c' . (int) $_REQUEST['c'][0],
			'name' => $name
		);
	}

	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=' . $_REQUEST['action'] . sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits'],
		'name' => $_REQUEST['action'] == 'unread' ? Lang::$txt['unread_topics_visit'] : Lang::$txt['unread_replies']
	);

	if (Utils::$context['showing_all_topics'])
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=' . $_REQUEST['action'] . ';all' . sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits'],
			'name' => Lang::$txt['unread_topics_all']
		);
	else
		Lang::$txt['unread_topics_visit_none'] = strtr(sprintf(Lang::$txt['unread_topics_visit_none'], Config::$scripturl), array('?action=unread;all' => '?action=unread;all' . sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits']));

	loadTemplate('Recent');
	loadTemplate('MessageIndex');
	Utils::$context['sub_template'] = $_REQUEST['action'] == 'unread' ? 'unread' : 'replies';

	// Setup the default topic icons... for checking they exist and the like ;)
	Utils::$context['icon_sources'] = array();
	foreach (Utils::$context['stable_icons'] as $icon)
		Utils::$context['icon_sources'][$icon] = 'images_url';

	$is_topics = $_REQUEST['action'] == 'unread';

	// This part is the same for each query.
	$select_clause = '
		ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
		t.num_replies, t.num_views, ms.id_member AS id_first_member, ml.id_member AS id_last_member,' . (!empty($settings['avatars_on_indexes']) ? ' meml.avatar, meml.email_address, mems.avatar AS first_poster_avatar, mems.email_address AS first_poster_email, COALESCE(af.id_attach, 0) AS first_poster_id_attach, af.filename AS first_poster_filename, af.attachment_type AS first_poster_attach_type, COALESCE(al.id_attach, 0) AS last_poster_id_attach, al.filename AS last_poster_filename, al.attachment_type AS last_poster_attach_type,' : '') . '
		ml.poster_time AS last_poster_time, COALESCE(mems.real_name, ms.poster_name) AS first_poster_name,
		COALESCE(meml.real_name, ml.poster_name) AS last_poster_name, ml.subject AS last_subject,
		ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
		COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from, SUBSTRING(ml.body, 1, 385) AS last_body,
		SUBSTRING(ms.body, 1, 385) AS first_body, ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg';

	if (Utils::$context['showing_all_topics'])
	{
		if (!empty(Board::$info->id))
		{
			$request = Db::$db->query('', '
				SELECT MIN(id_msg)
				FROM {db_prefix}log_mark_read
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				array(
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
				)
			);
			list ($earliest_msg) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
		else
		{
			$request = Db::$db->query('', '
				SELECT MIN(lmr.id_msg)
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
				WHERE {query_see_board}',
				array(
					'current_member' => User::$me->id,
				)
			);
			list ($earliest_msg) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
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
				$request = Db::$db->query('', '
					SELECT MIN(id_msg)
					FROM {db_prefix}log_topics
					WHERE id_member = {int:current_member}',
					array(
						'current_member' => User::$me->id,
					)
				);
				list ($earliest_msg2) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// In theory this could be zero, if the first ever post is unread, so fudge it ;)
				if ($earliest_msg2 == 0)
					$earliest_msg2 = -1;

				$_SESSION['cached_log_time'] = array(time(), $earliest_msg2);
			}

			$earliest_msg = min($earliest_msg2, $earliest_msg);
		}
	}

	// @todo Add modified_time in for log_time check?

	if (Config::$modSettings['totalMessages'] > 100000 && Utils::$context['showing_all_topics'])
	{
		Db::$db->query('', '
			DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
			array(
			)
		);

		// Let's copy things out of the log_topics table, to reduce searching.
		$have_temp_table = Db::$db->query('', '
			CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg, lt.unwatched
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}
				AND t.' . $query_this_board . (empty($earliest_msg) ? '' : '
				AND t.id_last_msg > {int:earliest_msg}') . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : ''),
			array_merge($query_parameters, array(
				'current_member' => User::$me->id,
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
				'db_error_skip' => true,
			))
		) !== false;
	}
	else
		$have_temp_table = false;

	if (Utils::$context['showing_all_topics'] && $have_temp_table)
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . (!empty($earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : '') . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1',
			array_merge($query_parameters, array(
				'current_member' => User::$me->id,
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Make sure the starting place makes sense and construct the page index.
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . Utils::$context['querystring_board_limits'] . Utils::$context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, Utils::$context['topics_per_page'], true);
		Utils::$context['current_page'] = (int) $_REQUEST['start'] / Utils::$context['topics_per_page'];

		Utils::$context['links'] = array(
			'first' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start'] - Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < $num_topics ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start'] + Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < $num_topics ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], floor(($num_topics - 1) / Utils::$context['topics_per_page']) * Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'up' => Config::$scripturl,
		);
		Utils::$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / Utils::$context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / Utils::$context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			// Mark the boards as read if there are no unread topics!
			Board::markBoardsRead(empty($boards) ? Board::$info->id : $boards);

			Utils::$context['topics'] = array();
			Utils::$context['no_topic_listing'] = true;
			if (Utils::$context['querystring_board_limits'] == ';start=%1$d')
				Utils::$context['querystring_board_limits'] = '';
			else
				Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = Db::$db->query('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE b.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => User::$me->id,
				'min_message' => $min_message,
				'is_approved' => 1,
				'sort' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => Utils::$context['topics_per_page'],
			))
		);
	}
	elseif ($is_topics)
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . (Utils::$context['showing_all_topics'] && !empty($earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : (!Utils::$context['showing_all_topics'] && empty($_SESSION['first_login']) ? '
				AND t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1',
			array_merge($query_parameters, array(
				'current_member' => User::$me->id,
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'id_msg_last_visit' => $_SESSION['id_msg_last_visit'],
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Make sure the starting place makes sense and construct the page index.
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . Utils::$context['querystring_board_limits'] . Utils::$context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, Utils::$context['topics_per_page'], true);
		Utils::$context['current_page'] = (int) $_REQUEST['start'] / Utils::$context['topics_per_page'];

		Utils::$context['links'] = array(
			'first' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start'] - Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < $num_topics ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start'] + Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < $num_topics ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], floor(($num_topics - 1) / Utils::$context['topics_per_page']) * Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'up' => Config::$scripturl,
		);
		Utils::$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / Utils::$context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / Utils::$context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			// Is this an all topics query?
			if (Utils::$context['showing_all_topics'])
			{
				// Since there are no unread topics, mark the boards as read!
				Board::markBoardsRead(empty($boards) ? Board::$info->id : $boards);
			}

			Utils::$context['topics'] = array();
			Utils::$context['no_topic_listing'] = true;
			if (Utils::$context['querystring_board_limits'] == ';start=%d')
				Utils::$context['querystring_board_limits'] = '';
			else
				Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = Db::$db->query('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < ml.id_msg' . (Config::$modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => User::$me->id,
				'min_message' => $min_message,
				'is_approved' => 1,
				'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => Utils::$context['topics_per_page'],
			))
		);
	}
	else
	{
		if (Config::$modSettings['totalMessages'] > 100000)
		{
			Db::$db->query('', '
				DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
				array(
				)
			);

			Db::$db->query('', '
				DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
				array(
				)
			);

			$sortKey_joins = array(
				'ms.subject' => '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
				'COALESCE(mems.real_name, ms.poster_name)' => '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
			);

			// The main benefit of this temporary table is not that it's faster; it's that it avoids locks later.
			$have_temp_table = Db::$db->query('', '
				CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
					id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
					id_board smallint(5) unsigned NOT NULL default {string:string_zero},
					id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
					id_msg int(10) unsigned NOT NULL default {string:string_zero},
					PRIMARY KEY (id_topic)
				)
				SELECT t.id_topic, t.id_board, t.id_last_msg, COALESCE(lmr.id_msg, 0) AS id_msg' . (!in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? ', ' . $_REQUEST['sort'] . ' AS sort_key' : '') . '
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$_REQUEST['sort']]) ? $sortKey_joins[$_REQUEST['sort']] : '') . '
				WHERE m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
					AND t.id_board = {int:current_board}' : '') . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . '
					AND COALESCE(lt.unwatched, 0) != 1
				GROUP BY m.id_topic',
				array(
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
					'is_approved' => 1,
					'string_zero' => '0',
					'db_error_skip' => true,
				)
			) !== false;

			// If that worked, create a sample of the log_topics table too.
			if ($have_temp_table)
				$have_temp_table = Db::$db->query('', '
					CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
						PRIMARY KEY (id_topic)
					)
					SELECT lt.id_topic, lt.id_msg
					FROM {db_prefix}log_topics AS lt
						INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
					WHERE lt.id_member = {int:current_member}',
					array(
						'current_member' => User::$me->id,
						'db_error_skip' => true,
					)
				) !== false;
		}

		if (!empty($have_temp_table))
		{
			$request = Db::$db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}topics_posted_in AS pi
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
				WHERE pi.' . $query_this_board . '
					AND COALESCE(lt.id_msg, pi.id_msg) < pi.id_last_msg',
				array_merge($query_parameters, array(
				))
			);
			list ($num_topics) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
		else
		{
			$request = Db::$db->query('unread_fetch_topic_count', '
				SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . '
					AND m.id_member = {int:current_member}
					AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . '
					AND COALESCE(lt.unwatched, 0) != 1',
				array_merge($query_parameters, array(
					'current_member' => User::$me->id,
					'is_approved' => 1,
				))
			);
			list ($num_topics, $min_message) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// Make sure the starting place makes sense and construct the page index.
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=' . $_REQUEST['action'] . Utils::$context['querystring_board_limits'] . Utils::$context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, Utils::$context['topics_per_page'], true);
		Utils::$context['current_page'] = (int) $_REQUEST['start'] / Utils::$context['topics_per_page'];

		Utils::$context['links'] = array(
			'first' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start'] - Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < $num_topics ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start'] + Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < $num_topics ? Config::$scripturl . '?action=' . $_REQUEST['action'] . (Utils::$context['showing_all_topics'] ? ';all' : '') . sprintf(Utils::$context['querystring_board_limits'], floor(($num_topics - 1) / Utils::$context['topics_per_page']) * Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'] : '',
			'up' => Config::$scripturl,
		);
		Utils::$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / Utils::$context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / Utils::$context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			Utils::$context['topics'] = array();
			Utils::$context['no_topic_listing'] = true;
			if (Utils::$context['querystring_board_limits'] == ';start=%d')
				Utils::$context['querystring_board_limits'] = '';
			else
				Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		if (!empty($have_temp_table))
			$request = Db::$db->query('', '
				SELECT t.id_topic
				FROM {db_prefix}topics_posted_in AS t
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
				WHERE t.' . $query_this_board . '
					AND COALESCE(lt.id_msg, t.id_msg) < t.id_last_msg
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'order' => (in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? $_REQUEST['sort'] : 't.sort_key') . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => Utils::$context['topics_per_page'],
				))
			);
		else
			$request = Db::$db->query('', '
				SELECT DISTINCT t.id_topic,' . $_REQUEST['sort'] . '
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($_REQUEST['sort'], 'ms.') === false ? '' : '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($_REQUEST['sort'], 'mems.') === false ? '' : '
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . '
					AND t.id_last_msg >= {int:min_message}
					AND (COALESCE(lt.id_msg, lmr.id_msg, 0)) < t.id_last_msg
					AND t.approved = {int:is_approved}
					AND COALESCE(lt.unwatched, 0) != 1
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'current_member' => User::$me->id,
					'min_message' => (int) $min_message,
					'is_approved' => 1,
					'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => Utils::$context['topics_per_page'],
					'sort' => $_REQUEST['sort'],
				))
			);

		$topics = array();
		while ($row = Db::$db->fetch_assoc($request))
			$topics[] = $row['id_topic'];
		Db::$db->free_result($request);

		// Sanity... where have you gone?
		if (empty($topics))
		{
			Utils::$context['topics'] = array();
			Utils::$context['no_topic_listing'] = true;
			if (Utils::$context['querystring_board_limits'] == ';start=%d')
				Utils::$context['querystring_board_limits'] = '';
			else
				Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		$request = Db::$db->query('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY {raw:sort}' . ($ascending ? '' : ' DESC') . '
			LIMIT {int:limit}',
			array(
				'current_member' => User::$me->id,
				'topic_list' => $topics,
				'sort' => $_REQUEST['sort'],
				'limit' => count($topics),
			)
		);
	}

	Utils::$context['topics'] = array();
	$topic_ids = array();
	$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? Config::$modSettings['recycle_board'] : 0;

	while ($row = Db::$db->fetch_assoc($request))
	{
		if ($row['id_poll'] > 0 && Config::$modSettings['pollMode'] == '0')
			continue;

		$topic_ids[] = $row['id_topic'];

		if (!empty(Config::$modSettings['preview_characters']))
		{
			// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br>' => '&#10;')));
			if (Utils::entityStrlen($row['first_body']) > 128)
				$row['first_body'] = Utils::entitySubstr($row['first_body'], 0, 128) . '...';
			$row['last_body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br>' => '&#10;')));
			if (Utils::entityStrlen($row['last_body']) > 128)
				$row['last_body'] = Utils::entitySubstr($row['last_body'], 0, 128) . '...';

			// Censor the subject and message preview.
			Lang::censorText($row['first_subject']);
			Lang::censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				Lang::censorText($row['last_subject']);
				Lang::censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			Lang::censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				Lang::censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		$messages_per_page = empty(Config::$modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];
		if ($topic_length > $messages_per_page)
		{
			$start = -1;
			$pages = constructPageIndex(Config::$scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $topic_length, $messages_per_page, true, false);

			// If we can use all, show all.
			if (!empty(Config::$modSettings['enableAllMessages']) && $topic_length < Config::$modSettings['enableAllMessages'])
				$pages .= sprintf(strtr($settings['page_index']['page'], array('{URL}' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0;all')), '', Lang::$txt['all']);
		}

		else
			$pages = '';

		// We need to check the topic icons exist... you can never be too sure!
		if (!empty(Config::$modSettings['messageIconChecks_enable']))
		{
			// First icon first... as you'd expect.
			if (!isset(Utils::$context['icon_sources'][$row['first_icon']]))
				Utils::$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
			// Last icon... last... duh.
			if (!isset(Utils::$context['icon_sources'][$row['last_icon']]))
				Utils::$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		}
		else
		{
			if (!isset(Utils::$context['icon_sources'][$row['first_icon']]))
				Utils::$context['icon_sources'][$row['first_icon']] = 'images_url';
			if (!isset(Utils::$context['icon_sources'][$row['last_icon']]))
				Utils::$context['icon_sources'][$row['last_icon']] = 'images_url';
		}

		// Force the recycling icon if appropriate
		if ($recycle_board == $row['id_board'])
		{
			$row['first_icon'] = 'recycled';
			$row['last_icon'] = 'recycled';
		}

		// Reference the main color class.
		$colorClass = 'windowbg';

		// Sticky topics should get a different color, too.
		if ($row['is_sticky'])
			$colorClass .= ' sticky';

		// Locked topics get special treatment as well.
		if ($row['locked'])
			$colorClass .= ' locked';

		// And build the array.
		Utils::$context['topics'][$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'name' => $row['first_poster_name'],
					'id' => $row['id_first_member'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_first_member'],
					'link' => !empty($row['id_first_member']) ? '<a class="preview" href="' . Config::$scripturl . '?action=profile;u=' . $row['id_first_member'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $row['first_poster_name']) . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
				),
				'time' => timeformat($row['first_poster_time']),
				'timestamp' => $row['first_poster_time'],
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'icon' => $row['first_icon'],
				'icon_url' => $settings[Utils::$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'name' => $row['last_poster_name'],
					'id' => $row['id_last_member'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_last_member'],
					'link' => !empty($row['id_last_member']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_last_member'] . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name']
				),
				'time' => timeformat($row['last_poster_time']),
				'timestamp' => $row['last_poster_time'],
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'icon' => $row['last_icon'],
				'icon_url' => $settings[Utils::$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'],
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
			),
			'new_from' => $row['new_from'],
			'new_href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
			'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen' . ($row['num_replies'] == 0 ? '' : 'new'),
			'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
			'is_sticky' => !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'css_class' => $colorClass,
			'is_poll' => Config::$modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
			'is_posted_in' => false,
			'icon' => $row['first_icon'],
			'icon_url' => $settings[Utils::$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
			'subject' => $row['first_subject'],
			'pages' => $pages,
			'replies' => Lang::numberFormat($row['num_replies']),
			'views' => Lang::numberFormat($row['num_views']),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			)
		);
		if (!empty($settings['avatars_on_indexes']))
		{
			Utils::$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = User::setAvatarData(array(
				'avatar' => $row['avatar'],
				'email' => $row['email_address'],
				'filename' => $row['last_poster_filename'],
			));

			Utils::$context['topics'][$row['id_topic']]['first_post']['member']['avatar'] = User::setAvatarData(array(
				'avatar' => $row['first_poster_avatar'],
				'email' => $row['first_poster_email'],
				'filename' => $row['first_poster_filename'],
			));
		}

		Utils::$context['topics'][$row['id_topic']]['first_post']['started_by'] = sprintf(Lang::$txt['topic_started_by'], Utils::$context['topics'][$row['id_topic']]['first_post']['member']['link'], Utils::$context['topics'][$row['id_topic']]['board']['link']);
	}
	Db::$db->free_result($request);

	if ($is_topics && !empty(Config::$modSettings['enableParticipation']) && !empty($topic_ids))
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
				'topic_list' => $topic_ids,
				'limit' => count($topic_ids),
			)
		);
		while ($row = Db::$db->fetch_assoc($result))
		{
			if (empty(Utils::$context['topics'][$row['id_topic']]['is_posted_in']))
				Utils::$context['topics'][$row['id_topic']]['is_posted_in'] = true;
		}
		Db::$db->free_result($result);
	}

	Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], $_REQUEST['start']);
	Utils::$context['topics_to_mark'] = implode('-', $topic_ids);

	// Build the recent button array.
	if ($is_topics)
	{
		Utils::$context['recent_buttons'] = array(
			'markread' => array('text' => !empty(Utils::$context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'image' => 'markread.png', 'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=markasread;sa=' . (!empty(Utils::$context['no_board_limits']) ? 'all' : 'board' . Utils::$context['querystring_board_limits']) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']),
		);

		if (Utils::$context['showCheckboxes'])
			Utils::$context['recent_buttons']['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.png',
				'url' => 'javascript:document.quickModForm.submit();',
			);

		if (!empty(Utils::$context['topics']) && !Utils::$context['showing_all_topics'])
			Utils::$context['recent_buttons']['readall'] = array('text' => 'unread_topics_all', 'image' => 'markreadall.png', 'url' => Config::$scripturl . '?action=unread;all' . Utils::$context['querystring_board_limits'], 'active' => true);
	}
	elseif (!$is_topics && isset(Utils::$context['topics_to_mark']))
	{
		Utils::$context['recent_buttons'] = array(
			'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=markasread;sa=unreadreplies;topics=' . Utils::$context['topics_to_mark'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']),
		);

		if (Utils::$context['showCheckboxes'])
			Utils::$context['recent_buttons']['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.png',
				'url' => 'javascript:document.quickModForm.submit();',
			);
	}

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_recent_buttons');

	Utils::$context['no_topic_listing'] = empty(Utils::$context['topics']);

	// Allow helpdesks and bug trackers and what not to add their own unread data (just add a template_layer to show custom stuff in the template!)
	call_integration_hook('integrate_unread_list');
}

?>