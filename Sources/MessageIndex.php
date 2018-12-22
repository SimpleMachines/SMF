<?php

/**
 * This file is what shows the listing of topics in a board.
 * It's just one or two functions, but don't under estimate it ;).
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
 * Show the list of topics in this board, along with any child boards.
 */
function MessageIndex()
{
	global $txt, $scripturl, $board, $modSettings, $context;
	global $options, $settings, $board_info, $user_info, $smcFunc, $sourcedir;

	// If this is a redirection board head off.
	if ($board_info['redirect'])
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1
			WHERE id_board = {int:current_board}',
			array(
				'current_board' => $board,
			)
		);

		redirectexit($board_info['redirect']);
	}

	loadTemplate('MessageIndex');

	if (!$user_info['is_guest'])
	{
		// We can't know they read it if we allow prefetches.
		// But we'll actually mark it read later after we've done everything else.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		{
			ob_end_clean();
			send_http_status(403, 'Prefetch Forbidden');
			die;
		}
	}

	$context['name'] = $board_info['name'];
	$context['description'] = $board_info['description'];
	if (!empty($board_info['description']))
		$context['meta_description'] = strip_tags($board_info['description']);

	// How many topics do we have in total?
	$board_info['total_topics'] = allowedTo('approve_posts') ? $board_info['num_topics'] + $board_info['unapproved_topics'] : $board_info['num_topics'] + $board_info['unapproved_user_topics'];

	// View all the topics, or just a few?
	$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
	$context['maxindex'] = isset($_REQUEST['all']) && !empty($modSettings['enableAllMessages']) ? $board_info['total_topics'] : $context['topics_per_page'];

	// Right, let's only index normal stuff!
	if (count($_GET) > 1)
	{
		$session_name = session_name();
		foreach ($_GET as $k => $v)
		{
			if (!in_array($k, array('board', 'start', $session_name)))
				$context['robot_no_index'] = true;
		}
	}
	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;

	// If we can view unapproved messages and there are some build up a list.
	if (allowedTo('approve_posts') && ($board_info['unapproved_topics'] || $board_info['unapproved_posts']))
	{
		$untopics = $board_info['unapproved_topics'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=topics;brd=' . $board . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
		$unposts = $board_info['unapproved_posts'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=posts;brd=' . $board . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;
		$context['unapproved_posts_message'] = sprintf($txt['there_are_unapproved_topics'], $untopics, $unposts, $scripturl . '?action=moderate;area=postmod;sa=' . ($board_info['unapproved_topics'] ? 'topics' : 'posts') . ';brd=' . $board);
	}

	// We only know these.
	if (isset($_REQUEST['sort']) && !in_array($_REQUEST['sort'], array('subject', 'starter', 'last_poster', 'replies', 'views', 'first_post', 'last_post')))
		$_REQUEST['sort'] = 'last_post';

	// Make sure the starting place makes sense and construct the page index.
	if (isset($_REQUEST['sort']))
		$context['page_index'] = constructPageIndex($scripturl . '?board=' . $board . '.%1$d;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $board_info['total_topics'], $context['maxindex'], true);
	else
		$context['page_index'] = constructPageIndex($scripturl . '?board=' . $board . '.%1$d', $_REQUEST['start'], $board_info['total_topics'], $context['maxindex'], true);
	$context['start'] = &$_REQUEST['start'];

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?board=' . $board . '.' . $context['start'];

	$can_show_all = !empty($modSettings['enableAllMessages']) && $context['maxindex'] > $modSettings['enableAllMessages'];

	if (!($can_show_all && isset($_REQUEST['all'])))
	{
		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?board=' . $board . '.0' : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?board=' . $board . '.' . ($_REQUEST['start'] - $context['topics_per_page']) : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? $scripturl . '?board=' . $board . '.' . ($_REQUEST['start'] + $context['topics_per_page']) : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? $scripturl . '?board=' . $board . '.' . (floor(($board_info['total_topics'] - 1) / $context['topics_per_page']) * $context['topics_per_page']) : '',
			'up' => $board_info['parent'] == 0 ? $scripturl . '?' : $scripturl . '?board=' . $board_info['parent'] . '.0'
		);
	}

	$context['page_info'] = array(
		'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
		'num_pages' => floor(($board_info['total_topics'] - 1) / $context['topics_per_page']) + 1
	);

	if (isset($_REQUEST['all']) && $can_show_all)
	{
		$context['maxindex'] = $modSettings['enableAllMessages'];
		$_REQUEST['start'] = 0;
	}

	// Build a list of the board's moderators.
	$context['moderators'] = &$board_info['moderators'];
	$context['moderator_groups'] = &$board_info['moderator_groups'];
	$context['link_moderators'] = array();
	if (!empty($board_info['moderators']))
	{
		foreach ($board_info['moderators'] as $mod)
			$context['link_moderators'][] = '<a href="' . $scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';
	}
	if (!empty($board_info['moderator_groups']))
	{
		// By default just tack the moderator groups onto the end of the members
		foreach ($board_info['moderator_groups'] as $mod_group)
			$context['link_moderators'][] = '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $mod_group['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod_group['name'] . '</a>';
	}

	// Now we tack the info onto the end of the linktree
	if (!empty($context['link_moderators']))
	{
		$context['linktree'][count($context['linktree']) - 1]['extra_after'] = '<span class="board_moderators">(' . (count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators']) . ': ' . implode(', ', $context['link_moderators']) . ')</span>';
	}

	// 'Print' the header and board info.
	$context['page_title'] = strip_tags($board_info['name']);

	// Set the variables up for the template.
	$context['can_mark_notify'] = !$user_info['is_guest'];
	$context['can_post_new'] = allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
	$context['can_post_poll'] = $modSettings['pollMode'] == '1' && allowedTo('poll_post') && $context['can_post_new'];
	$context['can_moderate_forum'] = allowedTo('moderate_forum');
	$context['can_approve_posts'] = allowedTo('approve_posts');

	require_once($sourcedir . '/Subs-BoardIndex.php');
	$boardIndexOptions = array(
		'include_categories' => false,
		'base_level' => $board_info['child_level'] + 1,
		'parent_id' => $board_info['id'],
		'set_latest_post' => false,
		'countChildPosts' => !empty($modSettings['countChildPosts']),
	);
	$context['boards'] = getBoardIndex($boardIndexOptions);

	// Nosey, nosey - who's viewing this topic?
	if (!empty($settings['display_who_viewing']))
	{
		$context['view_members'] = array();
		$context['view_members_list'] = array();
		$context['view_num_hidden'] = 0;

		$request = $smcFunc['db_query']('', '
			SELECT
				lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
				mg.online_color, mg.id_group, mg.group_name
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_member_group} THEN mem.id_post_group ELSE mem.id_group END)
			WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
			array(
				'reg_member_group' => 0,
				'in_url_string' => '"board":' . $board,
				'session' => $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id(),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['id_member']))
				continue;

			if (!empty($row['online_color']))
				$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
			else
				$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';

			$is_buddy = in_array($row['id_member'], $user_info['buddies']);
			if ($is_buddy)
				$link = '<strong>' . $link . '</strong>';

			if (!empty($row['show_online']) || allowedTo('moderate_forum'))
				$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;
			// @todo why are we filling this array of data that are just counted (twice) and discarded? ???
			$context['view_members'][$row['log_time'] . $row['member_name']] = array(
				'id' => $row['id_member'],
				'username' => $row['member_name'],
				'name' => $row['real_name'],
				'group' => $row['id_group'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => $link,
				'is_buddy' => $is_buddy,
				'hidden' => empty($row['show_online']),
			);

			if (empty($row['show_online']))
				$context['view_num_hidden']++;
		}
		$context['view_num_guests'] = $smcFunc['db_num_rows']($request) - count($context['view_members']);
		$smcFunc['db_free_result']($request);

		// Put them in "last clicked" order.
		krsort($context['view_members_list']);
		krsort($context['view_members']);
	}

	// Default sort methods.
	$sort_methods = array(
		'subject' => 'mf.subject',
		'starter' => 'COALESCE(memf.real_name, mf.poster_name)',
		'last_poster' => 'COALESCE(meml.real_name, ml.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	// Default sort methods tables.
	$sort_methods_table = array(
		'subject' => 'JOIN {db_prefix}messages mf ON (mf.id_msg = t.id_first_msg)',
		'starter' => 'JOIN {db_prefix}messages mf ON (mf.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)',
		'last_poster' => 'JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)',
		'replies' => '',
		'views' => '',
		'first_post' => '',
		'last_post' => ''
	);

	// They didn't pick one, default to by last post descending.
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = 'last_post';
		$_REQUEST['sort'] = 'id_last_msg';
		$ascending = isset($_REQUEST['asc']);
	}
	// Otherwise default to ascending.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);
	}

	$context['sort_direction'] = $ascending ? 'up' : 'down';
	$txt['starter'] = $txt['started_by'];

	// Bring in any changes we want to make before the query.
	call_integration_hook('integrate_pre_messageindex', array(&$sort_methods));

	foreach ($sort_methods as $key => $val)
		$context['topics_headers'][$key] = '<a href="' . $scripturl . '?board=' . $context['current_board'] . '.' . $context['start'] . ';sort=' . $key . ($context['sort_by'] == $key && $context['sort_direction'] == 'up' ? ';desc' : '') . '">' . $txt[$key] . ($context['sort_by'] == $key ? '<span class="sort sort_' . $context['sort_direction'] . '"></span>' : '') . '</a>';

	// Calculate the fastest way to get the topics.
	$start = (int) $_REQUEST['start'];
	if ($start > ($board_info['total_topics'] - 1) / 2)
	{
		$ascending = !$ascending;
		$fake_ascending = true;
		$context['maxindex'] = $board_info['total_topics'] < $start + $context['maxindex'] + 1 ? $board_info['total_topics'] - $start : $context['maxindex'];
		$start = $board_info['total_topics'] < $start + $context['maxindex'] + 1 ? 0 : $board_info['total_topics'] - $start - $context['maxindex'];
	}
	else
		$fake_ascending = false;

	// Setup the default topic icons...
	$context['icon_sources'] = array();
	foreach ($context['stable_icons'] as $icon)
		$context['icon_sources'][$icon] = 'images_url';

	$topic_ids = array();
	$context['topics'] = array();

	// Grab the appropriate topic information...
	// For search engine effectiveness we'll link guests differently.
	$context['pageindex_multiplier'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	$message_index_parameters = array(
		'current_board' => $board,
		'current_member' => $user_info['id'],
		'topic_list' => $topic_ids,
		'is_approved' => 1,
		'find_set_topics' => implode(',', $topic_ids),
		'start' => $start,
		'maxindex' => $context['maxindex'],
	);
	$message_index_selects = array();
	$message_index_tables = array();
	$message_index_wheres = array();
	call_integration_hook('integrate_message_index', array(&$message_index_selects, &$message_index_tables, &$message_index_parameters, &$message_index_wheres, &$topic_ids));

	if (!empty($modSettings['enableParticipation']) && !$user_info['is_guest'])
		$enableParticipation = true;
	else
		$enableParticipation = false;

	$sort_table = '
		SELECT t.id_topic, t.id_first_msg, t.id_last_msg
		FROM {db_prefix}topics t
		' . (empty($sort_methods_table[$context['sort_by']]) ? '' : $sort_methods_table[$context['sort_by']]) . '
		WHERE t.id_board = {int:current_board} '
			. (!$modSettings['postmod_active'] || $context['can_approve_posts'] ? '' : '
			AND (t.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
		ORDER BY is_sticky' . ($fake_ascending ? '' : ' DESC') . ', ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
		LIMIT {int:maxindex}
			OFFSET {int:start} ';

	$result = $smcFunc['db_query']('substring', '
		SELECT
			t.id_topic, t.num_replies, t.locked, t.num_views, t.is_sticky, t.id_poll, t.id_previous_board,
			' . ($user_info['is_guest'] ? '0' : 'COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1') . ' AS new_from,
			' . ($enableParticipation ? ' COALESCE(( SELECT 1 FROM {db_prefix}messages AS parti WHERE t.id_topic = parti.id_topic and parti.id_member = {int:current_member} LIMIT 1) , 0) as is_posted_in,
			' : '') . '
			t.id_last_msg, t.approved, t.unapproved_posts, ml.poster_time AS last_poster_time, t.id_redirect_topic,
			ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
			ml.poster_name AS last_member_name, ml.id_member AS last_id_member,' . (!empty($settings['avatars_on_indexes']) ? ' meml.avatar, meml.email_address, memf.avatar AS first_member_avatar, memf.email_address AS first_member_mail, COALESCE(af.id_attach, 0) AS first_member_id_attach, af.filename AS first_member_filename, af.attachment_type AS first_member_attach_type, COALESCE(al.id_attach, 0) AS last_member_id_attach, al.filename AS last_member_filename, al.attachment_type AS last_member_attach_type,' : '') . '
			COALESCE(meml.real_name, ml.poster_name) AS last_display_name, t.id_first_msg,
			mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
			mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
			COALESCE(memf.real_name, mf.poster_name) AS first_display_name, ' . (!empty($modSettings['preview_characters']) ? '
			SUBSTRING(ml.body, 1, ' . ($modSettings['preview_characters'] + 256) . ') AS last_body,
			SUBSTRING(mf.body, 1, ' . ($modSettings['preview_characters'] + 256) . ') AS first_body,' : '') . 'ml.smileys_enabled AS last_smileys, mf.smileys_enabled AS first_smileys
			' . (!empty($message_index_selects) ? (', ' . implode(', ', $message_index_selects)) : '') . '
		FROM (' . $sort_table . ') as st
			JOIN {db_prefix}topics AS t ON (st.id_topic = t.id_topic)
			JOIN {db_prefix}messages AS ml ON (ml.id_msg = st.id_last_msg)
			JOIN {db_prefix}messages AS mf ON (mf.id_msg = st.id_first_msg)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . (!empty($settings['avatars_on_indexes']) ? '
			LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = memf.id_member)
			LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') . '
			' . (!empty($message_index_tables) ? implode("\n\t\t\t\t", $message_index_tables) : '') . '
			' . (!empty($message_index_wheres) ? ' WHERE ' . implode("\n\t\t\t\tAND ", $message_index_wheres) : '') . '
		ORDER BY is_sticky' . ($fake_ascending ? '' : ' DESC') . ', ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
		$message_index_parameters
	);

	// Begin 'printing' the message index for current board.
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
			continue;

		$topic_ids[] = $row['id_topic'];

		// Reference the main color class.
		$colorClass = 'windowbg';

		// Does the theme support message previews?
		if (!empty($modSettings['preview_characters']))
		{
			// Limit them to $modSettings['preview_characters'] characters
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br>' => '&#10;')));
			if ($smcFunc['strlen']($row['first_body']) > $modSettings['preview_characters'])
				$row['first_body'] = $smcFunc['substr']($row['first_body'], 0, $modSettings['preview_characters']) . '...';

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
				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br>' => '&#10;')));
				if ($smcFunc['strlen']($row['last_body']) > $modSettings['preview_characters'])
					$row['last_body'] = $smcFunc['substr']($row['last_body'], 0, $modSettings['preview_characters']) . '...';

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
		if ($row['num_replies'] + 1 > $context['messages_per_page'])
		{
			// We can't pass start by reference.
			$start = -1;
			$pages = constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $row['num_replies'] + 1, $context['messages_per_page'], true, false);

			// If we can use all, show all.
			if (!empty($modSettings['enableAllMessages']) && $row['num_replies'] + 1 < $modSettings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
		}
		else
			$pages = '';

		// We need to check the topic icons exist...
		if (!empty($modSettings['messageIconChecks_enable']))
		{
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		}
		else
		{
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = 'images_url';
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = 'images_url';
		}

		if (!empty($board_info['recycle']))
			$row['first_icon'] = 'recycled';

		// Is this topic pending approval, or does it have any posts pending approval?
		if ($context['can_approve_posts'] && $row['unapproved_posts'])
			$colorClass .= (!$row['approved'] ? ' approvetopic' : ' approvepost');

		// Sticky topics should get a different color, too.
		if ($row['is_sticky'])
			$colorClass .= ' sticky';

		// Locked topics get special treatment as well.
		if ($row['locked'])
			$colorClass .= ' locked';

		// 'Print' the topic info.
		$context['topics'][$row['id_topic']] = array_merge($row, array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'username' => $row['first_member_name'],
					'name' => $row['first_display_name'],
					'id' => $row['first_id_member'],
					'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
					'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
				),
				'time' => timeformat($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>',
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'username' => $row['last_member_name'],
					'name' => $row['last_display_name'],
					'id' => $row['last_id_member'],
					'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
					'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
				),
				'time' => timeformat($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'icon' => $row['last_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
			),
			'is_sticky' => !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'is_redirect' => !empty($row['id_redirect_topic']),
			'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
			'is_posted_in' => ($enableParticipation ? $row['is_posted_in'] : false),
			'is_watched' => false,
			'icon' => $row['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
			'subject' => $row['first_subject'],
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'newtime' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views']),
			'approved' => $row['approved'],
			'unapproved_posts' => $row['unapproved_posts'],
			'css_class' => $colorClass,
		));
		if (!empty($settings['avatars_on_indexes']))
		{
			// Last post member avatar
			$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = set_avatar_data(array(
				'avatar' => $row['avatar'],
				'email' => $row['email_address'],
				'filename' => !empty($row['last_member_filename']) ? $row['last_member_filename'] : '',
			));

			// First post member avatar
			$context['topics'][$row['id_topic']]['first_post']['member']['avatar'] = set_avatar_data(array(
				'avatar' => $row['first_member_avatar'],
				'email' => $row['first_member_mail'],
				'filename' => !empty($row['first_member_filename']) ? $row['first_member_filename'] : '',
			));
		}
	}
	$smcFunc['db_free_result']($result);

	// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
	if ($fake_ascending)
		$context['topics'] = array_reverse($context['topics'], true);

	$context['jump_to'] = array(
		'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
		'board_name' => $smcFunc['htmlspecialchars'](strtr(strip_tags($board_info['name']), array('&amp;' => '&'))),
		'child_level' => $board_info['child_level'],
	);

	// Is Quick Moderation active/needed?
	if (!empty($options['display_quick_mod']) && !empty($context['topics']))
	{
		$context['can_markread'] = $context['user']['is_logged'];
		$context['can_lock'] = allowedTo('lock_any');
		$context['can_sticky'] = allowedTo('make_sticky');
		$context['can_move'] = allowedTo('move_any');
		$context['can_remove'] = allowedTo('remove_any');
		$context['can_merge'] = allowedTo('merge_any');
		// Ignore approving own topics as it's unlikely to come up...
		$context['can_approve'] = $modSettings['postmod_active'] && allowedTo('approve_posts') && !empty($board_info['unapproved_topics']);
		// Can we restore topics?
		$context['can_restore'] = allowedTo('move_any') && !empty($board_info['recycle']);

		if ($user_info['is_admin'] || $modSettings['topic_move_any'])
			$context['can_move_any'] = true;
		else
		{
			// We'll use this in a minute
			$boards_allowed = boardsAllowedTo('post_new');

			// How many boards can you do this on besides this one?
			$context['can_move_any'] = count($boards_allowed) > 1;
		}

		// Set permissions for all the topics.
		foreach ($context['topics'] as $t => $topic)
		{
			$started = $topic['first_post']['member']['id'] == $user_info['id'];
			$context['topics'][$t]['quick_mod'] = array(
				'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
				'sticky' => allowedTo('make_sticky'),
				'move' => (allowedTo('move_any') || ($started && allowedTo('move_own')) && $context['can_move_any']),
				'modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
				'remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
				'approve' => $context['can_approve'] && $topic['unapproved_posts']
			);
			$context['can_lock'] |= ($started && allowedTo('lock_own'));
			$context['can_move'] |= ($started && allowedTo('move_own') && $context['can_move_any']);
			$context['can_remove'] |= ($started && allowedTo('remove_own'));
		}

		// Can we use quick moderation checkboxes?
		if ($options['display_quick_mod'] == 1)
			$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_approve'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'] || $context['can_merge'] || $context['can_restore'];
		// Or the icons?
		else
			$context['can_quick_mod'] = $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'];
	}

	if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
	{
		$context['qmod_actions'] = array('approve', 'remove', 'lock', 'sticky', 'move', 'merge', 'restore', 'markread');
		call_integration_hook('integrate_quick_mod_actions');
	}

	// Mark current and parent boards as seen.
	if (!$user_info['is_guest'])
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}log_boards',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			array($modSettings['maxMsgID'], $user_info['id'], $board),
			array('id_member', 'id_board')
		);

		if (!empty($board_info['parent_boards']))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_boards
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_board IN ({array_int:board_list})',
				array(
					'current_member' => $user_info['id'],
					'board_list' => array_keys($board_info['parent_boards']),
					'id_msg' => $modSettings['maxMsgID'],
				)
			);

			// We've seen all these boards now!
			foreach ($board_info['parent_boards'] as $k => $dummy)
				if (isset($_SESSION['topicseen_cache'][$k]))
					unset($_SESSION['topicseen_cache'][$k]);
		}

		if (isset($_SESSION['topicseen_cache'][$board]))
			unset($_SESSION['topicseen_cache'][$board]);

		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_board, sent
			FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND (' . (!empty($context['topics']) ? 'id_topic IN ({array_int:topics}) OR ' : '') . 'id_board = {int:current_board})',
			array(
				'current_board' => $board,
				'topics' => !empty($context['topics']) ? array_keys($context['topics']) : array(),
				'current_member' => $user_info['id'],
			)
		);
		$context['is_marked_notify'] = false; // this is for the *board* only
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($row['id_board']))
			{
				$context['is_marked_notify'] = true;
				$board_sent = $row['sent'];
			}
			if (!empty($row['id_topic']))
				$context['topics'][$row['id_topic']]['is_watched'] = true;
		}
		$smcFunc['db_free_result']($request);

		if ($context['is_marked_notify'] && !empty($board_sent))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_notify
				SET sent = {int:is_sent}
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
					'is_sent' => 0,
				)
			);
		}

		require_once($sourcedir . '/Subs-Notify.php');
		$pref = getNotifyPrefs($user_info['id'], array('board_notify', 'board_notify_' . $board), true);
		$pref = !empty($pref[$user_info['id']]) ? $pref[$user_info['id']] : array();
		$pref = isset($pref['board_notify_' . $board]) ? $pref['board_notify_' . $board] : (!empty($pref['board_notify']) ? $pref['board_notify'] : 0);
		$context['board_notification_mode'] = !$context['is_marked_notify'] ? 1 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));
	}
	else
	{
		$context['is_marked_notify'] = false;
		$context['board_notification_mode'] = 1;
	}

	// If there are children, but no topics and no ability to post topics...
	$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];

	// Show a message in case a recently posted message became unapproved.
	$context['becomesUnapproved'] = !empty($_SESSION['becomesUnapproved']) ? true : false;

	// Don't want to show this forever...
	if ($context['becomesUnapproved'])
		unset($_SESSION['becomesUnapproved']);

	// Build the message index button array.
	$context['normal_buttons'] = array();

	if ($context['can_post_new'])
		$context['normal_buttons']['new_topic'] = array('text' => 'new_topic', 'image' => 'new_topic.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0', 'active' => true);

	if ($context['can_post_poll'])
		$context['normal_buttons']['post_poll'] = array('text' => 'new_poll', 'image' => 'new_poll.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll');

	if ($context['user']['is_logged'])
		$context['normal_buttons']['markread'] = array('text' => 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'custom' => 'data-confirm="' . $txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id']);

	if ($context['can_mark_notify'])
		$context['normal_buttons']['notify'] = array(
			'lang' => true,
			'text' => 'notify_board_' . $context['board_notification_mode'],
			'sub_buttons' => array(
				array(
					'text' => 'notify_board_1',
					'url' => $scripturl . '?action=notifyboard;board=' . $board . ';mode=1;' . $context['session_var'] . '=' . $context['session_id'],
				),
				array(
					'text' => 'notify_board_2',
					'url' => $scripturl . '?action=notifyboard;board=' . $board . ';mode=2;' . $context['session_var'] . '=' . $context['session_id'],
				),
				array(
					'text' => 'notify_board_3',
					'url' => $scripturl . '?action=notifyboard;board=' . $board . ';mode=3;' . $context['session_var'] . '=' . $context['session_id'],
				),
			),
		);

	// Javascript for inline editing.
	loadJavaScriptFile('topic.js', array('defer' => false, 'minimize' => true), 'smf_topic');

	// Allow adding new buttons easily.
	// Note: $context['normal_buttons'] is added for backward compatibility with 2.0, but is deprecated and should not be used
	call_integration_hook('integrate_messageindex_buttons', array(&$context['normal_buttons']));
}

/**
 * Handles moderation from the message index.
 *
 * @todo refactor this...
 */
function QuickModeration()
{
	global $sourcedir, $board, $user_info, $modSettings, $smcFunc, $context;

	// Check the session = get or post.
	checkSession('request');

	// Lets go straight to the restore area.
	if (isset($_REQUEST['qaction']) && $_REQUEST['qaction'] == 'restore' && !empty($_REQUEST['topics']))
		redirectexit('action=restoretopic;topics=' . implode(',', $_REQUEST['topics']) . ';' . $context['session_var'] . '=' . $context['session_id']);

	if (isset($_SESSION['topicseen_cache']))
		$_SESSION['topicseen_cache'] = array();

	// This is going to be needed to send off the notifications and for updateLastMessages().
	require_once($sourcedir . '/Subs-Post.php');

	// Remember the last board they moved things to.
	if (isset($_REQUEST['move_to']))
		$_SESSION['move_to_topic'] = $_REQUEST['move_to'];

	// Only a few possible actions.
	$possibleActions = array();

	if (!empty($board))
	{
		$boards_can = array(
			'make_sticky' => allowedTo('make_sticky') ? array($board) : array(),
			'move_any' => allowedTo('move_any') ? array($board) : array(),
			'move_own' => allowedTo('move_own') ? array($board) : array(),
			'remove_any' => allowedTo('remove_any') ? array($board) : array(),
			'remove_own' => allowedTo('remove_own') ? array($board) : array(),
			'lock_any' => allowedTo('lock_any') ? array($board) : array(),
			'lock_own' => allowedTo('lock_own') ? array($board) : array(),
			'merge_any' => allowedTo('merge_any') ? array($board) : array(),
			'approve_posts' => allowedTo('approve_posts') ? array($board) : array(),
		);

		$redirect_url = 'board=' . $board . '.' . $_REQUEST['start'];
	}
	else
	{
		/**
		 * @todo Ugly. There's no getting around this, is there?
		 * @todo Maybe just do this on the actions people want to use?
		 */
		$boards_can = boardsAllowedTo(array('make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'lock_any', 'lock_own', 'merge_any', 'approve_posts'), true, false);

		$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : (isset($_SESSION['old_url']) ? $_SESSION['old_url'] : '');
	}

	// Are we enforcing the "no moving topics to boards where you can't post new ones" rule?
	if (!$user_info['is_admin'] && !$modSettings['topic_move_any'])
	{
		// Don't count this board, if it's specified
		if (!empty($board))
		{
			$boards_can['post_new'] = array_diff(boardsAllowedTo('post_new'), array($board));
		}
		else
		{
			$boards_can['post_new'] = boardsAllowedTo('post_new');
		}

		if (empty($boards_can['post_new']))
		{
			$boards_can['move_any'] = $boards_can['move_own'] = array();
		}
	}

	if (!$user_info['is_guest'])
		$possibleActions[] = 'markread';
	if (!empty($boards_can['make_sticky']))
		$possibleActions[] = 'sticky';
	if (!empty($boards_can['move_any']) || !empty($boards_can['move_own']))
		$possibleActions[] = 'move';
	if (!empty($boards_can['remove_any']) || !empty($boards_can['remove_own']))
		$possibleActions[] = 'remove';
	if (!empty($boards_can['lock_any']) || !empty($boards_can['lock_own']))
		$possibleActions[] = 'lock';
	if (!empty($boards_can['merge_any']))
		$possibleActions[] = 'merge';
	if (!empty($boards_can['approve_posts']))
		$possibleActions[] = 'approve';

	// Two methods: $_REQUEST['actions'] (id_topic => action), and $_REQUEST['topics'] and $_REQUEST['qaction'].
	// (if action is 'move', $_REQUEST['move_to'] or $_REQUEST['move_tos'][$topic] is used.)
	if (!empty($_REQUEST['topics']))
	{
		// If the action isn't valid, just quit now.
		if (empty($_REQUEST['qaction']) || !in_array($_REQUEST['qaction'], $possibleActions))
			redirectexit($redirect_url);

		// Merge requires all topics as one parameter and can be done at once.
		if ($_REQUEST['qaction'] == 'merge')
		{
			// Merge requires at least two topics.
			if (empty($_REQUEST['topics']) || count($_REQUEST['topics']) < 2)
				redirectexit($redirect_url);

			require_once($sourcedir . '/SplitTopics.php');
			return MergeExecute($_REQUEST['topics']);
		}

		// Just convert to the other method, to make it easier.
		foreach ($_REQUEST['topics'] as $topic)
			$_REQUEST['actions'][(int) $topic] = $_REQUEST['qaction'];
	}

	// Weird... how'd you get here?
	if (empty($_REQUEST['actions']))
		redirectexit($redirect_url);

	// Validate each action.
	$temp = array();
	foreach ($_REQUEST['actions'] as $topic => $action)
	{
		if (in_array($action, $possibleActions))
			$temp[(int) $topic] = $action;
	}
	$_REQUEST['actions'] = $temp;

	if (!empty($_REQUEST['actions']))
	{
		// Find all topics...
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_member_started, id_board, locked, approved, unapproved_posts
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:action_topic_ids})
			LIMIT {int:limit}',
			array(
				'action_topic_ids' => array_keys($_REQUEST['actions']),
				'limit' => count($_REQUEST['actions']),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($board))
			{
				if ($row['id_board'] != $board || ($modSettings['postmod_active'] && !$row['approved'] && !allowedTo('approve_posts')))
					unset($_REQUEST['actions'][$row['id_topic']]);
			}
			else
			{
				// Don't allow them to act on unapproved posts they can't see...
				if ($modSettings['postmod_active'] && !$row['approved'] && !in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))
					unset($_REQUEST['actions'][$row['id_topic']]);
				// Goodness, this is fun.  We need to validate the action.
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'sticky' && !in_array(0, $boards_can['make_sticky']) && !in_array($row['id_board'], $boards_can['make_sticky']))
					unset($_REQUEST['actions'][$row['id_topic']]);
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'move' && !in_array(0, $boards_can['move_any']) && !in_array($row['id_board'], $boards_can['move_any']) && ($row['id_member_started'] != $user_info['id'] || (!in_array(0, $boards_can['move_own']) && !in_array($row['id_board'], $boards_can['move_own']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'remove' && !in_array(0, $boards_can['remove_any']) && !in_array($row['id_board'], $boards_can['remove_any']) && ($row['id_member_started'] != $user_info['id'] || (!in_array(0, $boards_can['remove_own']) && !in_array($row['id_board'], $boards_can['remove_own']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
				// @todo $locked is not set, what are you trying to do? (taking the change it is supposed to be $row['locked'])
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'lock' && !in_array(0, $boards_can['lock_any']) && !in_array($row['id_board'], $boards_can['lock_any']) && ($row['id_member_started'] != $user_info['id'] || $row['locked'] == 1 || (!in_array(0, $boards_can['lock_own']) && !in_array($row['id_board'], $boards_can['lock_own']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
				// If the topic is approved then you need permission to approve the posts within.
				elseif ($_REQUEST['actions'][$row['id_topic']] == 'approve' && (!$row['unapproved_posts'] || (!in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))))
					unset($_REQUEST['actions'][$row['id_topic']]);
			}
		}
		$smcFunc['db_free_result']($request);
	}

	$stickyCache = array();
	$moveCache = array(0 => array(), 1 => array());
	$removeCache = array();
	$lockCache = array();
	$markCache = array();
	$approveCache = array();

	// Separate the actions.
	foreach ($_REQUEST['actions'] as $topic => $action)
	{
		$topic = (int) $topic;

		if ($action == 'markread')
			$markCache[] = $topic;
		elseif ($action == 'sticky')
			$stickyCache[] = $topic;
		elseif ($action == 'move')
		{
			require_once($sourcedir . '/MoveTopic.php');
			moveTopicConcurrence();

			// $moveCache[0] is the topic, $moveCache[1] is the board to move to.
			$moveCache[1][$topic] = (int) (isset($_REQUEST['move_tos'][$topic]) ? $_REQUEST['move_tos'][$topic] : $_REQUEST['move_to']);

			if (empty($moveCache[1][$topic]))
				continue;

			$moveCache[0][] = $topic;
		}
		elseif ($action == 'remove')
			$removeCache[] = $topic;
		elseif ($action == 'lock')
			$lockCache[] = $topic;
		elseif ($action == 'approve')
			$approveCache[] = $topic;
	}

	if (empty($board))
		$affectedBoards = array();
	else
		$affectedBoards = array($board => array(0, 0));

	// Do all the stickies...
	if (!empty($stickyCache))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET is_sticky = CASE WHEN is_sticky = {int:is_sticky} THEN 0 ELSE 1 END
			WHERE id_topic IN ({array_int:sticky_topic_ids})',
			array(
				'sticky_topic_ids' => $stickyCache,
				'is_sticky' => 1,
			)
		);

		// Get the board IDs and Sticky status
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_board, is_sticky
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:sticky_topic_ids})
			LIMIT {int:limit}',
			array(
				'sticky_topic_ids' => $stickyCache,
				'limit' => count($stickyCache),
			)
		);
		$stickyCacheBoards = array();
		$stickyCacheStatus = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$stickyCacheBoards[$row['id_topic']] = $row['id_board'];
			$stickyCacheStatus[$row['id_topic']] = empty($row['is_sticky']);
		}
		$smcFunc['db_free_result']($request);
	}

	// Move sucka! (this is, by the by, probably the most complicated part....)
	if (!empty($moveCache[0]))
	{
		// I know - I just KNOW you're trying to beat the system.  Too bad for you... we CHECK :P.
		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, t.id_board, b.count_posts
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			WHERE t.id_topic IN ({array_int:move_topic_ids})' . (!empty($board) && !allowedTo('move_any') ? '
				AND t.id_member_started = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			array(
				'current_member' => $user_info['id'],
				'move_topic_ids' => $moveCache[0],
				'limit' => count($moveCache[0])
			)
		);
		$moveTos = array();
		$moveCache2 = array();
		$countPosts = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$to = $moveCache[1][$row['id_topic']];

			if (empty($to))
				continue;

			// Does this topic's board count the posts or not?
			$countPosts[$row['id_topic']] = empty($row['count_posts']);

			if (!isset($moveTos[$to]))
				$moveTos[$to] = array();

			$moveTos[$to][] = $row['id_topic'];

			// For reporting...
			$moveCache2[] = array($row['id_topic'], $row['id_board'], $to);
		}
		$smcFunc['db_free_result']($request);

		$moveCache = $moveCache2;

		require_once($sourcedir . '/MoveTopic.php');

		// Do the actual moves...
		foreach ($moveTos as $to => $topics)
			moveTopics($topics, $to);

		// Does the post counts need to be updated?
		if (!empty($moveTos))
		{
			$topicRecounts = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_board, count_posts
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:move_boards})',
				array(
					'move_boards' => array_keys($moveTos),
				)
			);

			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$cp = empty($row['count_posts']);

				// Go through all the topics that are being moved to this board.
				foreach ($moveTos[$row['id_board']] as $topic)
				{
					// If both boards have the same value for post counting then no adjustment needs to be made.
					if ($countPosts[$topic] != $cp)
					{
						// If the board being moved to does count the posts then the other one doesn't so add to their post count.
						$topicRecounts[$topic] = $cp ? '+' : '-';
					}
				}
			}

			$smcFunc['db_free_result']($request);

			if (!empty($topicRecounts))
			{
				$members = array();

				// Get all the members who have posted in the moved topics.
				$request = $smcFunc['db_query']('', '
					SELECT id_member, id_topic
					FROM {db_prefix}messages
					WHERE id_topic IN ({array_int:moved_topic_ids})',
					array(
						'moved_topic_ids' => array_keys($topicRecounts),
					)
				);

				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					if (!isset($members[$row['id_member']]))
						$members[$row['id_member']] = 0;

					if ($topicRecounts[$row['id_topic']] === '+')
						$members[$row['id_member']] += 1;
					else
						$members[$row['id_member']] -= 1;
				}

				$smcFunc['db_free_result']($request);

				// And now update them member's post counts
				foreach ($members as $id_member => $post_adj)
					updateMemberData($id_member, array('posts' => 'posts + ' . $post_adj));
			}
		}
	}

	// Now delete the topics...
	if (!empty($removeCache))
	{
		// They can only delete their own topics. (we wouldn't be here if they couldn't do that..)
		$result = $smcFunc['db_query']('', '
			SELECT id_topic, id_board
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:removed_topic_ids})' . (!empty($board) && !allowedTo('remove_any') ? '
				AND id_member_started = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			array(
				'current_member' => $user_info['id'],
				'removed_topic_ids' => $removeCache,
				'limit' => count($removeCache),
			)
		);

		$removeCache = array();
		$removeCacheBoards = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$removeCache[] = $row['id_topic'];
			$removeCacheBoards[$row['id_topic']] = $row['id_board'];
		}
		$smcFunc['db_free_result']($result);

		// Maybe *none* were their own topics.
		if (!empty($removeCache))
		{
			// Gotta send the notifications *first*!
			foreach ($removeCache as $topic)
			{
				// Only log the topic ID if it's not in the recycle board.
				logAction('remove', array((empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $removeCacheBoards[$topic] ? 'topic' : 'old_topic_id') => $topic, 'board' => $removeCacheBoards[$topic]));
				sendNotifications($topic, 'remove');
			}

			require_once($sourcedir . '/RemoveTopic.php');
			removeTopics($removeCache);
		}
	}

	// Approve the topics...
	if (!empty($approveCache))
	{
		// We need unapproved topic ids and their authors!
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_member_started
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:approve_topic_ids})
				AND approved = {int:not_approved}
			LIMIT {int:limit}',
			array(
				'approve_topic_ids' => $approveCache,
				'not_approved' => 0,
				'limit' => count($approveCache),
			)
		);
		$approveCache = array();
		$approveCacheMembers = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$approveCache[] = $row['id_topic'];
			$approveCacheMembers[$row['id_topic']] = $row['id_member_started'];
		}
		$smcFunc['db_free_result']($request);

		// Any topics to approve?
		if (!empty($approveCache))
		{
			// Handle the approval part...
			approveTopics($approveCache);

			// Time for some logging!
			foreach ($approveCache as $topic)
				logAction('approve_topic', array('topic' => $topic, 'member' => $approveCacheMembers[$topic]));
		}
	}

	// And (almost) lastly, lock the topics...
	if (!empty($lockCache))
	{
		$lockStatus = array();

		// Gotta make sure they CAN lock/unlock these topics...
		if (!empty($board) && !allowedTo('lock_any'))
		{
			// Make sure they started the topic AND it isn't already locked by someone with higher priv's.
			$result = $smcFunc['db_query']('', '
				SELECT id_topic, locked, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:locked_topic_ids})
					AND id_member_started = {int:current_member}
					AND locked IN (2, 0)
				LIMIT {int:limit}',
				array(
					'current_member' => $user_info['id'],
					'locked_topic_ids' => $lockCache,
					'limit' => count($lockCache),
				)
			);
			$lockCache = array();
			$lockCacheBoards = array();
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$lockCache[] = $row['id_topic'];
				$lockCacheBoards[$row['id_topic']] = $row['id_board'];
				$lockStatus[$row['id_topic']] = empty($row['locked']);
			}
			$smcFunc['db_free_result']($result);
		}
		else
		{
			$result = $smcFunc['db_query']('', '
				SELECT id_topic, locked, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:locked_topic_ids})
				LIMIT {int:limit}',
				array(
					'locked_topic_ids' => $lockCache,
					'limit' => count($lockCache)
				)
			);
			$lockCacheBoards = array();
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$lockStatus[$row['id_topic']] = empty($row['locked']);
				$lockCacheBoards[$row['id_topic']] = $row['id_board'];
			}
			$smcFunc['db_free_result']($result);
		}

		// It could just be that *none* were their own topics...
		if (!empty($lockCache))
		{
			// Alternate the locked value.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}topics
				SET locked = CASE WHEN locked = {int:is_locked} THEN ' . (allowedTo('lock_any') ? '1' : '2') . ' ELSE 0 END
				WHERE id_topic IN ({array_int:locked_topic_ids})',
				array(
					'locked_topic_ids' => $lockCache,
					'is_locked' => 0,
				)
			);
		}
	}

	if (!empty($markCache))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:selected_topics})
				AND id_member = {int:current_user}',
			array(
				'selected_topics' => $markCache,
				'current_user' => $user_info['id'],
			)
		);
		$logged_topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$logged_topics[$row['id_topic']] = $row['unwatched'];

		$smcFunc['db_free_result']($request);

		$markArray = array();
		foreach ($markCache as $topic)
			$markArray[] = array($modSettings['maxMsgID'], $user_info['id'], $topic, (isset($logged_topics[$topic]) ? $logged_topics[$topic] : 0));

		$smcFunc['db_insert']('replace',
			'{db_prefix}log_topics',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'),
			$markArray,
			array('id_member', 'id_topic')
		);
	}

	foreach ($moveCache as $topic)
	{
		// Didn't actually move anything!
		if (!isset($topic[0]))
			break;

		logAction('move', array('topic' => $topic[0], 'board_from' => $topic[1], 'board_to' => $topic[2]));
		sendNotifications($topic[0], 'move');
	}
	foreach ($lockCache as $topic)
	{
		logAction($lockStatus[$topic] ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $lockCacheBoards[$topic]));
		sendNotifications($topic, $lockStatus[$topic] ? 'lock' : 'unlock');
	}
	foreach ($stickyCache as $topic)
	{
		logAction($stickyCacheStatus[$topic] ? 'unsticky' : 'sticky', array('topic' => $topic, 'board' => $stickyCacheBoards[$topic]));
		sendNotifications($topic, 'sticky');
	}

	updateStats('topic');
	updateStats('message');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	if (!empty($affectedBoards))
		updateLastMessages(array_keys($affectedBoards));

	redirectexit($redirect_url);
}

?>