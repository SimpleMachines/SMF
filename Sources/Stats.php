<?php

/**
 * Provide a display for forum statistics
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Display some useful/interesting board statistics.
 *
 * gets all the statistics in order and puts them in.
 * uses the Stats template and language file. (and main sub template.)
 * requires the view_stats permission.
 * accessed from ?action=stats.
 */
function DisplayStats()
{
	global $txt, $scripturl, $modSettings, $context, $smcFunc;

	isAllowedTo('view_stats');
	// Page disabled - redirect them out
	if (empty($modSettings['trackStats']))
		fatal_lang_error('feature_disabled', true);

	if (!empty($_REQUEST['expand']))
	{
		$context['robot_no_index'] = true;

		$month = (int) substr($_REQUEST['expand'], 4);
		$year = (int) substr($_REQUEST['expand'], 0, 4);
		if ($year > 1900 && $year < 2200 && $month >= 1 && $month <= 12)
			$_SESSION['expanded_stats'][$year][] = $month;
	}
	elseif (!empty($_REQUEST['collapse']))
	{
		$context['robot_no_index'] = true;

		$month = (int) substr($_REQUEST['collapse'], 4);
		$year = (int) substr($_REQUEST['collapse'], 0, 4);
		if (!empty($_SESSION['expanded_stats'][$year]))
			$_SESSION['expanded_stats'][$year] = array_diff($_SESSION['expanded_stats'][$year], array($month));
	}

	// Handle the XMLHttpRequest.
	if (isset($_REQUEST['xml']))
	{
		// Collapsing stats only needs adjustments of the session variables.
		if (!empty($_REQUEST['collapse']))
			obExit(false);

		$context['sub_template'] = 'stats';
		$context['yearly'] = array();

		if (empty($month) || empty($year))
			return;

		getDailyStats('YEAR(date) = {int:year} AND MONTH(date) = {int:month}', array('year' => $year, 'month' => $month));
		$context['yearly'][$year]['months'][$month]['date'] = array(
			'month' => sprintf('%02d', $month),
			'year' => $year,
		);
		return;
	}

	loadLanguage('Stats');
	loadTemplate('Stats');
	loadJavaScriptFile('stats.js', array('default_theme' => true, 'defer' => false, 'minimize' => true), 'smf_stats');

	// Build the link tree......
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=stats',
		'name' => $txt['stats_center']
	);
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['stats_center'];

	$context['show_member_list'] = allowedTo('view_mlist');

	// Get averages...
	$result = $smcFunc['db_query']('', '
		SELECT
			SUM(posts) AS posts, SUM(topics) AS topics, SUM(registers) AS registers,
			SUM(most_on) AS most_on, MIN(date) AS date, SUM(hits) AS hits
		FROM {db_prefix}log_activity',
		array(
		)
	);
	$row = $smcFunc['db_fetch_assoc']($result);
	$smcFunc['db_free_result']($result);

	// This would be the amount of time the forum has been up... in days...
	$total_days_up = ceil((time() - strtotime($row['date'])) / (60 * 60 * 24));

	$context['average_posts'] = comma_format(round($row['posts'] / $total_days_up, 2));
	$context['average_topics'] = comma_format(round($row['topics'] / $total_days_up, 2));
	$context['average_members'] = comma_format(round($row['registers'] / $total_days_up, 2));
	$context['average_online'] = comma_format(round($row['most_on'] / $total_days_up, 2));
	$context['average_hits'] = comma_format(round($row['hits'] / $total_days_up, 2));

	$context['num_hits'] = comma_format($row['hits'], 0);

	// How many users are online now.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_online',
		array(
		)
	);
	list ($context['users_online']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Statistics such as number of boards, categories, etc.
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}boards AS b
		WHERE b.redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
		)
	);
	list ($context['num_boards']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}categories AS c',
		array(
		)
	);
	list ($context['num_categories']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Format the numbers nicely.
	$context['users_online'] = comma_format($context['users_online']);
	$context['num_boards'] = comma_format($context['num_boards']);
	$context['num_categories'] = comma_format($context['num_categories']);

	$context['num_members'] = comma_format($modSettings['totalMembers']);
	$context['num_posts'] = comma_format($modSettings['totalMessages']);
	$context['num_topics'] = comma_format($modSettings['totalTopics']);
	$context['most_members_online'] = array(
		'number' => comma_format($modSettings['mostOnline']),
		'date' => timeformat($modSettings['mostDate'])
	);
	$context['latest_member'] = &$context['common_stats']['latest_member'];

	// Let's calculate gender stats only every four minutes.
	$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
	if (!in_array('gender', $disabled_fields))
	{
		if (($context['gender'] = cache_get_data('stats_gender', 240)) == null)
		{
			$result = $smcFunc['db_query']('', '
				SELECT COUNT(id_member) AS total_members, value AS gender
				FROM {db_prefix}themes
				WHERE variable = {string:gender_var} AND id_theme = {int:default_theme}
				GROUP BY value',
				array(
					'gender_var' => 'cust_gender',
					'default_theme' => 1,
				)
			);
			$context['gender'] = array();
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$context['gender'][$row['gender']] = $row['total_members'];
			}
			$smcFunc['db_free_result']($result);

			cache_put_data('stats_gender', $context['gender'], 240);
		}
	}

	$date = strftime('%Y-%m-%d', forum_time(false));

	// Members online so far today.
	$result = $smcFunc['db_query']('', '
		SELECT most_on
		FROM {db_prefix}log_activity
		WHERE date = {date:today_date}
		LIMIT 1',
		array(
			'today_date' => $date,
		)
	);
	list ($context['online_today']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	$context['online_today'] = comma_format((int) $context['online_today']);

	// Poster top 10.
	$members_result = $smcFunc['db_query']('', '
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		WHERE posts > {int:no_posts}
		ORDER BY posts DESC
		LIMIT 10',
		array(
			'no_posts' => 0,
		)
	);
	$context['stats_blocks']['posters'] = array();
	$max_num_posts = 1;
	while ($row_members = $smcFunc['db_fetch_assoc']($members_result))
	{
		$context['stats_blocks']['posters'][] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num' => $row_members['posts'],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		if ($max_num_posts < $row_members['posts'])
			$max_num_posts = $row_members['posts'];
	}
	$smcFunc['db_free_result']($members_result);

	foreach ($context['stats_blocks']['posters'] as $i => $poster)
	{
		$context['stats_blocks']['posters'][$i]['percent'] = round(($poster['num'] * 100) / $max_num_posts);
		$context['stats_blocks']['posters'][$i]['num'] = comma_format($context['stats_blocks']['posters'][$i]['num']);
	}

	// Board top 10.
	$boards_result = $smcFunc['db_query']('', '
		SELECT id_board, name, num_posts
		FROM {db_prefix}boards AS b
		WHERE {query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND b.redirect = {string:blank_redirect}
		ORDER BY num_posts DESC
		LIMIT 10',
		array(
			'recycle_board' => $modSettings['recycle_board'],
			'blank_redirect' => '',
		)
	);
	$context['stats_blocks']['boards'] = array();
	$max_num_posts = 1;
	while ($row_board = $smcFunc['db_fetch_assoc']($boards_result))
	{
		$context['stats_blocks']['boards'][] = array(
			'id' => $row_board['id_board'],
			'name' => $row_board['name'],
			'num' => $row_board['num_posts'],
			'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['name'] . '</a>'
		);

		if ($max_num_posts < $row_board['num_posts'])
			$max_num_posts = $row_board['num_posts'];
	}
	$smcFunc['db_free_result']($boards_result);

	foreach ($context['stats_blocks']['boards'] as $i => $board)
	{
		$context['stats_blocks']['boards'][$i]['percent'] = round(($board['num'] * 100) / $max_num_posts);
		$context['stats_blocks']['boards'][$i]['num'] = comma_format($context['stats_blocks']['boards'][$i]['num']);
	}

	// Are you on a larger forum?  If so, let's try to limit the number of topics we search through.
	if ($modSettings['totalMessages'] > 100000)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_replies != {int:no_replies}' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved}' : '') . '
			ORDER BY num_replies DESC
			LIMIT 100',
			array(
				'no_replies' => 0,
				'is_approved' => 1,
			)
		);
		$topic_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topic_ids[] = $row['id_topic'];
		$smcFunc['db_free_result']($request);
	}
	else
		$topic_ids = array();

	// Topic replies top 10.
	$topic_reply_result = $smcFunc['db_query']('', '
		SELECT m.subject, t.num_replies, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '')) . '
		ORDER BY t.num_replies DESC
		LIMIT 10',
		array(
			'topic_list' => $topic_ids,
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	$context['stats_blocks']['topics_replies'] = array();
	$max_num_replies = 1;

	while ($row_topic_reply = $smcFunc['db_fetch_assoc']($topic_reply_result))
	{
		censorText($row_topic_reply['subject']);

		$context['stats_blocks']['topics_replies'][] = array(
			'id' => $row_topic_reply['id_topic'],
			'board' => array(
				'id' => $row_topic_reply['id_board'],
				'name' => $row_topic_reply['name'],
				'href' => $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0">' . $row_topic_reply['name'] . '</a>'
			),
			'subject' => $row_topic_reply['subject'],
			'num' => $row_topic_reply['num_replies'],
			'href' => $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0">' . $row_topic_reply['subject'] . '</a>'
		);

		if ($max_num_replies < $row_topic_reply['num_replies'])
			$max_num_replies = $row_topic_reply['num_replies'];
	}
	$smcFunc['db_free_result']($topic_reply_result);

	foreach ($context['stats_blocks']['topics_replies'] as $i => $topic)
	{
		$context['stats_blocks']['topics_replies'][$i]['percent'] = round(($topic['num'] * 100) / $max_num_replies);
		$context['stats_blocks']['topics_replies'][$i]['num'] = comma_format($context['stats_blocks']['topics_replies'][$i]['num']);
	}

	// Large forums may need a bit more prodding...
	if ($modSettings['totalMessages'] > 100000)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_views != {int:no_views}
			ORDER BY num_views DESC
			LIMIT 100',
			array(
				'no_views' => 0,
			)
		);
		$topic_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topic_ids[] = $row['id_topic'];
		$smcFunc['db_free_result']($request);
	}
	else
		$topic_ids = array();

	// Topic views top 10.
	$topic_view_result = $smcFunc['db_query']('', '
		SELECT m.subject, t.num_views, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '')) . '
		ORDER BY t.num_views DESC
		LIMIT 10',
		array(
			'topic_list' => $topic_ids,
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	$context['stats_blocks']['topics_views'] = array();
	$max_num = 1;
	while ($row_topic_views = $smcFunc['db_fetch_assoc']($topic_view_result))
	{
		censorText($row_topic_views['subject']);

		$context['stats_blocks']['topics_views'][] = array(
			'id' => $row_topic_views['id_topic'],
			'board' => array(
				'id' => $row_topic_views['id_board'],
				'name' => $row_topic_views['name'],
				'href' => $scripturl . '?board=' . $row_topic_views['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_views['id_board'] . '.0">' . $row_topic_views['name'] . '</a>'
			),
			'subject' => $row_topic_views['subject'],
			'num' => $row_topic_views['num_views'],
			'href' => $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0">' . $row_topic_views['subject'] . '</a>'
		);

		if ($max_num < $row_topic_views['num_views'])
			$max_num = $row_topic_views['num_views'];
	}
	$smcFunc['db_free_result']($topic_view_result);

	foreach ($context['stats_blocks']['topics_views'] as $i => $topic)
	{
		$context['stats_blocks']['topics_views'][$i]['percent'] = round(($topic['num'] * 100) / $max_num);
		$context['stats_blocks']['topics_views'][$i]['num'] = comma_format($context['stats_blocks']['topics_views'][$i]['num']);
	}

	// Try to cache this when possible, because it's a little unavoidably slow.
	if (($members = cache_get_data('stats_top_starters', 360)) == null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member_started, COUNT(*) AS hits
			FROM {db_prefix}topics' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			WHERE id_board != {int:recycle_board}' : '') . '
			GROUP BY id_member_started
			ORDER BY hits DESC
			LIMIT 20',
			array(
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[$row['id_member_started']] = $row['hits'];
		$smcFunc['db_free_result']($request);

		cache_put_data('stats_top_starters', $members, 360);
	}

	if (empty($members))
		$members = array(0 => 0);

	// Topic poster top 10.
	$members_result = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})',
		array(
			'member_list' => array_keys($members),
		)
	);
	$context['stats_blocks']['starters'] = array();
	$max_num = 1;
	while ($row_members = $smcFunc['db_fetch_assoc']($members_result))
	{
		$i = array_search($row_members['id_member'], array_keys($members));
		// skip all not top 10
		if ($i >= 10)
			continue;

		$context['stats_blocks']['starters'][$i] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num' => $members[$row_members['id_member']],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		if ($max_num < $members[$row_members['id_member']])
			$max_num = $members[$row_members['id_member']];
	}
	ksort($context['stats_blocks']['starters']);
	$smcFunc['db_free_result']($members_result);

	foreach ($context['stats_blocks']['starters'] as $i => $topic)
	{
		$context['stats_blocks']['starters'][$i]['percent'] = round(($topic['num'] * 100) / $max_num);
		$context['stats_blocks']['starters'][$i]['num'] = comma_format($context['stats_blocks']['starters'][$i]['num']);
	}

	// Time online top 10.
	$temp = cache_get_data('stats_total_time_members', 600);
	$members_result = $smcFunc['db_query']('', '
		SELECT id_member, real_name, total_time_logged_in
		FROM {db_prefix}members' . (!empty($temp) ? '
		WHERE id_member IN ({array_int:member_list_cached})' : '') . '
		ORDER BY total_time_logged_in DESC
		LIMIT 20',
		array(
			'member_list_cached' => $temp,
		)
	);
	$context['stats_blocks']['time_online'] = array();
	$temp2 = array();
	$max_time_online = 1;
	while ($row_members = $smcFunc['db_fetch_assoc']($members_result))
	{
		$temp2[] = (int) $row_members['id_member'];
		if (count($context['stats_blocks']['time_online']) >= 10)
			continue;

		// Figure out the days, hours and minutes.
		$timeDays = floor($row_members['total_time_logged_in'] / 86400);
		$timeHours = floor(($row_members['total_time_logged_in'] % 86400) / 3600);

		// Figure out which things to show... (days, hours, minutes, etc.)
		$timelogged = '';
		if ($timeDays > 0)
			$timelogged .= $timeDays . $txt['total_time_logged_d'];
		if ($timeHours > 0)
			$timelogged .= $timeHours . $txt['total_time_logged_h'];
		$timelogged .= floor(($row_members['total_time_logged_in'] % 3600) / 60) . $txt['total_time_logged_m'];

		$context['stats_blocks']['time_online'][] = array(
			'id' => $row_members['id_member'],
			'name' => $row_members['real_name'],
			'num' => $timelogged,
			'seconds_online' => $row_members['total_time_logged_in'],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		if ($max_time_online < $row_members['total_time_logged_in'])
			$max_time_online = $row_members['total_time_logged_in'];
	}
	$smcFunc['db_free_result']($members_result);

	foreach ($context['stats_blocks']['time_online'] as $i => $member)
		$context['stats_blocks']['time_online'][$i]['percent'] = round(($member['seconds_online'] * 100) / $max_time_online);

	// Cache the ones we found for a bit, just so we don't have to look again.
	if ($temp !== $temp2)
		cache_put_data('stats_total_time_members', $temp2, 480);

	// Likes.
	if (!empty($modSettings['enable_likes']))
	{
		// Liked messages top 10.
		$context['stats_blocks']['liked_messages'] = array();
		$max_liked_message = 1;
		$liked_messages = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.subject, m.likes, m.id_board, m.id_topic, t.approved
			FROM {db_prefix}messages as m
				INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
					AND b.id_board != {int:recycle_board}' : '') . ')
			WHERE {query_see_board}' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY m.likes DESC
			LIMIT 10',
			array(
				'recycle_board' => $modSettings['recycle_board'],
				'is_approved' => 1,
			)
		);

		while ($row_liked_message = $smcFunc['db_fetch_assoc']($liked_messages))
		{
			censorText($row_liked_message['subject']);

			$context['stats_blocks']['liked_messages'][] = array(
				'id' => $row_liked_message['id_topic'],
				'subject' => $row_liked_message['subject'],
				'num' => $row_liked_message['likes'],
				'href' => $scripturl . '?msg=' . $row_liked_message['id_msg'],
				'link' => '<a href="' . $scripturl . '?msg=' . $row_liked_message['id_msg'] . '">' . $row_liked_message['subject'] . '</a>'
			);

			if ($max_liked_message < $row_liked_message['likes'])
				$max_liked_message = $row_liked_message['likes'];
		}
		$smcFunc['db_free_result']($liked_messages);

		foreach ($context['stats_blocks']['liked_messages'] as $i => $liked_messages)
			$context['stats_blocks']['liked_messages'][$i]['percent'] = round(($liked_messages['num'] * 100) / $max_liked_message);

		// Liked users top 10.
		$context['stats_blocks']['liked_users'] = array();
		$max_liked_users = 1;
		$liked_users = $smcFunc['db_query']('', '
			SELECT m.id_member AS liked_user, COUNT(l.content_id) AS count, mem.real_name
			FROM {db_prefix}user_likes AS l
				INNER JOIN {db_prefix}messages AS m ON (l.content_id = m.id_msg)
				INNER JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
			WHERE content_type = {literal:msg}
				AND m.id_member > {int:zero}
			GROUP BY m.id_member, mem.real_name
			ORDER BY count DESC
			LIMIT 10',
			array(
				'no_posts' => 0,
				'zero' => 0,
			)
		);

		while ($row_liked_users = $smcFunc['db_fetch_assoc']($liked_users))
		{
			$context['stats_blocks']['liked_users'][] = array(
				'id' => $row_liked_users['liked_user'],
				'num' => $row_liked_users['count'],
				'href' => $scripturl . '?action=profile;u=' . $row_liked_users['liked_user'],
				'name' => $row_liked_users['real_name'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_liked_users['liked_user'] . '">' . $row_liked_users['real_name'] . '</a>',
			);

			if ($max_liked_users < $row_liked_users['count'])
				$max_liked_users = $row_liked_users['count'];
		}

		$smcFunc['db_free_result']($liked_users);

		foreach ($context['stats_blocks']['liked_users'] as $i => $liked_users)
			$context['stats_blocks']['liked_users'][$i]['percent'] = round(($liked_users['num'] * 100) / $max_liked_users);
	}

	// Activity by month.
	$months_result = $smcFunc['db_query']('', '
		SELECT
			YEAR(date) AS stats_year, MONTH(date) AS stats_month, SUM(hits) AS hits, SUM(registers) AS registers, SUM(topics) AS topics, SUM(posts) AS posts, MAX(most_on) AS most_on, COUNT(*) AS num_days
		FROM {db_prefix}log_activity
		GROUP BY stats_year, stats_month',
		array()
	);

	$context['yearly'] = array();
	while ($row_months = $smcFunc['db_fetch_assoc']($months_result))
	{
		$ID_MONTH = $row_months['stats_year'] . sprintf('%02d', $row_months['stats_month']);
		$expanded = !empty($_SESSION['expanded_stats'][$row_months['stats_year']]) && in_array($row_months['stats_month'], $_SESSION['expanded_stats'][$row_months['stats_year']]);

		if (!isset($context['yearly'][$row_months['stats_year']]))
			$context['yearly'][$row_months['stats_year']] = array(
				'year' => $row_months['stats_year'],
				'new_topics' => 0,
				'new_posts' => 0,
				'new_members' => 0,
				'most_members_online' => 0,
				'hits' => 0,
				'num_months' => 0,
				'months' => array(),
				'expanded' => false,
				'current_year' => $row_months['stats_year'] == date('Y'),
			);

		$context['yearly'][$row_months['stats_year']]['months'][(int) $row_months['stats_month']] = array(
			'id' => $ID_MONTH,
			'date' => array(
				'month' => sprintf('%02d', $row_months['stats_month']),
				'year' => $row_months['stats_year']
			),
			'href' => $scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $ID_MONTH . '#m' . $ID_MONTH,
			'link' => '<a href="' . $scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $ID_MONTH . '#m' . $ID_MONTH . '">' . $txt['months_titles'][(int) $row_months['stats_month']] . ' ' . $row_months['stats_year'] . '</a>',
			'month' => $txt['months_titles'][(int) $row_months['stats_month']],
			'year' => $row_months['stats_year'],
			'new_topics' => comma_format($row_months['topics']),
			'new_posts' => comma_format($row_months['posts']),
			'new_members' => comma_format($row_months['registers']),
			'most_members_online' => comma_format($row_months['most_on']),
			'hits' => comma_format($row_months['hits']),
			'num_days' => $row_months['num_days'],
			'days' => array(),
			'expanded' => $expanded
		);

		$context['yearly'][$row_months['stats_year']]['new_topics'] += $row_months['topics'];
		$context['yearly'][$row_months['stats_year']]['new_posts'] += $row_months['posts'];
		$context['yearly'][$row_months['stats_year']]['new_members'] += $row_months['registers'];
		$context['yearly'][$row_months['stats_year']]['hits'] += $row_months['hits'];
		$context['yearly'][$row_months['stats_year']]['num_months']++;
		$context['yearly'][$row_months['stats_year']]['expanded'] |= $expanded;
		$context['yearly'][$row_months['stats_year']]['most_members_online'] = max($context['yearly'][$row_months['stats_year']]['most_members_online'], $row_months['most_on']);
	}

	krsort($context['yearly']);

	$context['collapsed_years'] = array();
	foreach ($context['yearly'] as $year => $data)
	{
		// This gets rid of the filesort on the query ;).
		krsort($context['yearly'][$year]['months']);

		$context['yearly'][$year]['new_topics'] = comma_format($data['new_topics']);
		$context['yearly'][$year]['new_posts'] = comma_format($data['new_posts']);
		$context['yearly'][$year]['new_members'] = comma_format($data['new_members']);
		$context['yearly'][$year]['most_members_online'] = comma_format($data['most_members_online']);
		$context['yearly'][$year]['hits'] = comma_format($data['hits']);

		// Keep a list of collapsed years.
		if (!$data['expanded'] && !$data['current_year'])
			$context['collapsed_years'][] = $year;
	}

	// Custom stats (just add a template_layer to add it to the template!)
	call_integration_hook('integrate_forum_stats');

	if (empty($_SESSION['expanded_stats']))
		return;

	$condition_text = array();
	$condition_params = array();
	foreach ($_SESSION['expanded_stats'] as $year => $months)
		if (!empty($months))
		{
			$condition_text[] = 'YEAR(date) = {int:year_' . $year . '} AND MONTH(date) IN ({array_int:months_' . $year . '})';
			$condition_params['year_' . $year] = $year;
			$condition_params['months_' . $year] = $months;
		}

	// No daily stats to even look at?
	if (empty($condition_text))
		return;

	getDailyStats(implode(' OR ', $condition_text), $condition_params);
}

/**
 * Loads the statistics on a daily basis in $context.
 * called by DisplayStats().
 *
 * @param string $condition_string An SQL condition string
 * @param array $condition_parameters Parameters for $condition_string
 */
function getDailyStats($condition_string, $condition_parameters = array())
{
	global $context, $smcFunc;

	// Activity by day.
	$days_result = $smcFunc['db_query']('', '
		SELECT YEAR(date) AS stats_year, MONTH(date) AS stats_month, DAYOFMONTH(date) AS stats_day, topics, posts, registers, most_on, hits
		FROM {db_prefix}log_activity
		WHERE ' . $condition_string . '
		ORDER BY stats_day ASC',
		$condition_parameters
	);
	while ($row_days = $smcFunc['db_fetch_assoc']($days_result))
		$context['yearly'][$row_days['stats_year']]['months'][(int) $row_days['stats_month']]['days'][] = array(
			'day' => sprintf('%02d', $row_days['stats_day']),
			'month' => sprintf('%02d', $row_days['stats_month']),
			'year' => $row_days['stats_year'],
			'new_topics' => comma_format($row_days['topics']),
			'new_posts' => comma_format($row_days['posts']),
			'new_members' => comma_format($row_days['registers']),
			'most_members_online' => comma_format($row_days['most_on']),
			'hits' => comma_format($row_days['hits'])
		);
	$smcFunc['db_free_result']($days_result);
}

/**
 * This is the function which returns stats to simplemachines.org IF enabled!
 * called by simplemachines.org.
 * only returns anything if stats was enabled during installation.
 * can also be accessed by the admin, to show what stats sm.org collects.
 * does not return any data directly to sm.org, instead starts a new request for security.
 *
 * @link https://www.simplemachines.org/about/stats.php for more info.
 */
function SMStats()
{
	global $modSettings, $user_info, $sourcedir;

	// First, is it disabled?
	if (empty($modSettings['enable_sm_stats']) || empty($modSettings['sm_stats_key']))
		die();

	// Are we saying who we are, and are we right? (OR an admin)
	if (!$user_info['is_admin'] && (!isset($_GET['sid']) || $_GET['sid'] != $modSettings['sm_stats_key']))
		die();

	// Verify the referer...
	if (!$user_info['is_admin'] && (!isset($_SERVER['HTTP_REFERER']) || md5($_SERVER['HTTP_REFERER']) != '746cb59a1a0d5cf4bd240e5a67c73085'))
		die();

	// Get some server versions.
	require_once($sourcedir . '/Subs-Admin.php');
	$checkFor = array(
		'php',
		'db_server',
	);
	$serverVersions = getServerVersions($checkFor);

	// Get the actual stats.
	$stats_to_send = array(
		'UID' => $modSettings['sm_stats_key'],
		'time_added' => time(),
		'members' => $modSettings['totalMembers'],
		'messages' => $modSettings['totalMessages'],
		'topics' => $modSettings['totalTopics'],
		'boards' => 0,
		'php_version' => $serverVersions['php']['version'],
		'database_type' => strtolower($serverVersions['db_engine']['version']),
		'database_version' => $serverVersions['db_server']['version'],
		'smf_version' => SMF_FULL_VERSION,
		'smfd_version' => $modSettings['smfVersion'],
	);

	// Encode all the data, for security.
	foreach ($stats_to_send as $k => $v)
		$stats_to_send[$k] = urlencode($k) . '=' . urlencode($v);

	// Turn this into the query string!
	$stats_to_send = implode('&', $stats_to_send);

	// If we're an admin, just plonk them out.
	if ($user_info['is_admin'])
		echo $stats_to_send;
	else
	{
		// Connect to the collection script.
		$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
		if ($fp)
		{
			$length = strlen($stats_to_send);

			$out = 'POST /smf/stats/collect_stats.php HTTP/1.1' . "\r\n";
			$out .= 'Host: www.simplemachines.org' . "\r\n";
			$out .= 'content-type: application/x-www-form-urlencoded' . "\r\n";
			$out .= 'connection: Close' . "\r\n";
			$out .= 'content-length: ' . $length . "\r\n\r\n";
			$out .= $stats_to_send . "\r\n";
			fwrite($fp, $out);
			fclose($fp);
		}
	}

	// Die.
	die('OK');
}

?>