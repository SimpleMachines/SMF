<?php

/**
 * This file currently only contains one function to collect the data needed to
 * show a list of boards for the board index and the message index.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Fetches a list of boards and (optional) categories including
 * statistical information, child boards and moderators.
 * 	- Used by both the board index (main data) and the message index (child
 * boards).
 * 	- Depending on the include_categories setting returns an associative
 * array with categories->boards->child_boards or an associative array
 * with boards->child_boards.
 *
 * @param array $board_index_options An array of boardindex options
 * @return array An array of information for displaying the boardindex
 */

function getBoardIndex($board_index_options)
{
	global $smcFunc, $scripturl, $user_info, $modSettings, $txt;
	global $settings, $options, $context, $sourcedir;

	require_once($sourcedir . '/Subs-Boards.php');

	// For performance, track the latest post while going through the boards.
	if (!empty($board_index_options['set_latest_post']))
		$latest_post = array(
			'timestamp' => 0,
			'ref' => 0
		);

	// This setting is not allowed to be empty
	if (empty($modSettings['boardindex_max_depth']))
		$modSettings['boardindex_max_depth'] = 1;

	$board_index_selects = array(
		'b.id_board',
		'b.name AS board_name',
		'b.description',
		'CASE WHEN b.redirect != {string:blank_string} THEN 1 ELSE 0 END AS is_redirect',
		'b.num_posts',
		'b.num_topics',
		'b.unapproved_posts',
		'b.unapproved_topics',
		'b.id_parent'
	);

	$board_index_parameters = array(
		'current_member' => $user_info['id'],
		'child_level' => $board_index_options['base_level'],
		'max_child_level' => $board_index_options['base_level'] + $modSettings['boardindex_max_depth'],
		'blank_string' => ''
	);

	call_integration_hook('integrate_pre_boardindex', array(&$board_index_selects, &$board_index_parameters));

	// Find all boards and categories, as well as related information.  This will be sorted by the natural order of boards and categories, which we control.
	if ($board_index_options['parent_id'] != 0 && $smcFunc['db_cte_support']())
		$result_boards = $smcFunc['db_query']('', '
			WITH RECURSIVE
				boards_cte (child_level, id_board, name, description, redirect, num_posts, num_topics, unapproved_posts, unapproved_topics, id_parent, id_msg_updated, id_cat, id_last_msg, board_order)
			AS
			(
				SELECT b.child_level, b.id_board, b.name, b.description, b.redirect, b.num_posts, b.num_topics, b.unapproved_posts, b.unapproved_topics, b.id_parent, b.id_msg_updated, b.id_cat, b.id_last_msg, b.board_order
				FROM {db_prefix}boards AS b
				WHERE {query_see_board} AND b.id_board = {int:id_parent}
					UNION ALL
				SELECT b.child_level, b.id_board, b.name, b.description, b.redirect, b.num_posts, b.num_topics, b.unapproved_posts, b.unapproved_topics, b.id_parent, b.id_msg_updated, b.id_cat, b.id_last_msg, b.board_order
				FROM {db_prefix}boards AS b
					JOIN boards_cte AS bc ON (b.id_parent = bc.id_board)
				WHERE {query_see_board}
					AND b.child_level BETWEEN {int:child_level} AND {int:max_child_level}
			)
			SELECT' . ($board_index_options['include_categories'] ? '
				c.id_cat, c.name AS cat_name, c.description AS cat_desc,' : '') . '
				' . (!empty($board_index_selects) ? implode(', ', $board_index_selects) : '') . ',
				COALESCE(m.poster_time, 0) AS poster_time, COALESCE(mem.member_name, m.poster_name) AS poster_name,
				m.subject, m.id_topic, COALESCE(mem.real_name, m.poster_name) AS real_name,
				' . ($user_info['is_guest'] ? ' 1 AS is_read, 0 AS new_from,' : '
				(CASE WHEN COALESCE(lb.id_msg, 0) >= b.id_last_msg THEN 1 ELSE 0 END) AS is_read, COALESCE(lb.id_msg, -1) + 1 AS new_from,' . ($board_index_options['include_categories'] ? '
				c.can_collapse,' : '')) . '
				COALESCE(mem.id_member, 0) AS id_member, mem.avatar, m.id_msg' . (!empty($settings['avatars_on_boardIndex']) ? ',  mem.email_address, mem.avatar, COALESCE(am.id_attach, 0) AS member_id_attach, am.filename AS member_filename, am.attachment_type AS member_attach_type' : '') . '
			FROM boards_cte AS b' . ($board_index_options['include_categories'] ? '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' : '') . '
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!empty($settings['avatars_on_boardIndex']) ? '
				LEFT JOIN {db_prefix}attachments AS am ON (am.id_member = m.id_member)' : '') . '' . ($user_info['is_guest'] ? '' : '
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})') . '
			WHERE b.id_parent != 0
			ORDER BY ' . (!empty($board_index_options['include_categories']) ? 'c.cat_order, ' : '') . 'b.child_level DESC, b.board_order DESC',
			array_merge($board_index_parameters, array(
				'id_parent' => $board_index_options['parent_id']
			))
		);
	else
		$result_boards = $smcFunc['db_query']('', '
			SELECT' . ($board_index_options['include_categories'] ? '
				c.id_cat, c.name AS cat_name, c.description AS cat_desc,' : 'b.id_cat,') . '
				' . (!empty($board_index_selects) ? implode(', ', $board_index_selects) : '') . ',
				COALESCE(m.poster_time, 0) AS poster_time, COALESCE(mem.member_name, m.poster_name) AS poster_name,
				m.subject, m.id_topic, COALESCE(mem.real_name, m.poster_name) AS real_name,
				' . ($user_info['is_guest'] ? ' 1 AS is_read, 0 AS new_from,' : '
				(CASE WHEN COALESCE(lb.id_msg, 0) >= b.id_last_msg THEN 1 ELSE 0 END) AS is_read, COALESCE(lb.id_msg, -1) + 1 AS new_from,' . ($board_index_options['include_categories'] ? '
				c.can_collapse,' : '')) . '
				COALESCE(mem.id_member, 0) AS id_member, mem.avatar, m.id_msg' . (!empty($settings['avatars_on_boardIndex']) ? ',  mem.email_address, mem.avatar, COALESCE(am.id_attach, 0) AS member_id_attach, am.filename AS member_filename, am.attachment_type AS member_attach_type' : '') . '
			FROM {db_prefix}boards AS b' . ($board_index_options['include_categories'] ? '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' : '') . '
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!empty($settings['avatars_on_boardIndex']) ? '
				LEFT JOIN {db_prefix}attachments AS am ON (am.id_member = m.id_member)' : '') . '' . ($user_info['is_guest'] ? '' : '
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})') . '
			WHERE {query_see_board}
				AND b.child_level BETWEEN {int:child_level} AND {int:max_child_level}
			ORDER BY ' . (!empty($board_index_options['include_categories']) ? 'c.cat_order, ' : '') . 'b.child_level DESC, b.board_order DESC',
			$board_index_parameters
		);

	// Start with an empty array.
	if ($board_index_options['include_categories'])
		$categories = array();

	else
		$this_category = array();

	$boards = array();
	$boards_ids = array();
	$categories_ids = array();

	// Children can affect parents, so we need to gather all the boards first and then process them after.
	$row_boards = array();

	foreach ($smcFunc['db_fetch_all']($result_boards) as $row)
	{
		$row_boards[$row['id_board']] = $row;
		$boards_ids[] = $row['id_board'];
		$categories_ids[] = $row['id_cat'];
	}

	$smcFunc['db_free_result']($result_boards);

	// mmmm memory, delicious!
	$parsed_descriptions = getParsedDescriptions($categories_ids);
	$to_parse = array();

	// Run through the categories and boards (or only boards)....
	// Done like this so the modified values can be used.
	for (reset($row_boards); key($row_boards) !== null; next($row_boards))
	{
		$row_board = current($row_boards);

		// Perhaps we are ignoring this board?
		$ignoreThisBoard = in_array($row_board['id_board'], $user_info['ignoreboards']);
		$row_board['is_read'] = !empty($row_board['is_read']) || $ignoreThisBoard ? '1' : '0';

		// Add parent boards to the $boards list later used to fetch moderators
		if ($row_board['id_parent'] == $board_index_options['parent_id'])
			$boards[] = $row_board['id_board'];

		if ($board_index_options['include_categories'])
		{
			// Haven't set this category yet.
			if (empty($categories[$row_board['id_cat']]))
			{
				$name = parse_bbc($row_board['cat_name'], false, '', $context['description_allowed_tags']);
				$description = parse_bbc($row_board['cat_desc'], false, '', $context['description_allowed_tags']);

				$categories[$row_board['id_cat']] = array(
					'id' => $row_board['id_cat'],
					'name' => $name,
					'description' => $description,
					'is_collapsed' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1 && !empty($options['collapse_category_' . $row_board['id_cat']]),
					'can_collapse' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1,
					'href' => $scripturl . '#c' . $row_board['id_cat'],
					'boards' => array(),
					'new' => false,
					'css_class' => ''
				);

				$categories[$row_board['id_cat']]['link'] = '' . (!$context['user']['is_guest'] ? '<a href="' . $scripturl . '?action=unread;c=' . $row_board['id_cat'] . '" title="' . sprintf($txt['new_posts_in_category'], $name) . '" id="c' . $row_board['id_cat'] . '">' . $name . '</a>' : '<span id="c' . $row_board['id_cat'] . '">' . $name . '</span>');
			}

			// If this board has new posts in it (and isn't the recycle bin!) then the category is new.
			if (empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $row_board['id_board'])
				$categories[$row_board['id_cat']]['new'] |= empty($row_board['is_read']);

			// Avoid showing category unread link where it only has redirection boards.
			$categories[$row_board['id_cat']]['show_unread'] = !empty($categories[$row_board['id_cat']]['show_unread']) ? 1 : !$row_board['is_redirect'];

			// Let's save some typing.  Climbing the array might be slower, anyhow.
			$this_category = &$categories[$row_board['id_cat']]['boards'];
		}

		// This is a parent board.
		if ($row_board['id_parent'] == $board_index_options['parent_id'])
		{
			// Is this a new board, or just another moderator?
			if (!isset($this_category[$row_board['id_board']]['type']))
			{
				// Not a child.
				$isChild = false;

				// We might or might not have already added this board, so...
				if (!isset($this_category[$row_board['id_board']]))
					$this_category[$row_board['id_board']] = array();

				$board_name = parse_bbc($row_board['board_name'], false, '', $context['description_allowed_tags']);
				$board_description = parse_bbc($row_board['description'], false, '', $context['description_allowed_tags']);

				$this_category[$row_board['id_board']] += array(
					'new' => empty($row_board['is_read']),
					'id' => $row_board['id_board'],
					'type' => $row_board['is_redirect'] ? 'redirect' : 'board',
					'name' => $board_name,
					'description' => $board_description,
					'moderators' => array(),
					'moderator_groups' => array(),
					'link_moderators' => array(),
					'link_moderator_groups' => array(),
					'children' => array(),
					'link_children' => array(),
					'children_new' => false,
					'topics' => $row_board['num_topics'],
					'posts' => $row_board['num_posts'],
					'is_redirect' => $row_board['is_redirect'],
					'unapproved_topics' => $row_board['unapproved_topics'],
					'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
					'can_approve_posts' => !empty($user_info['mod_cache']['ap']) && ($user_info['mod_cache']['ap'] == array(0) || in_array($row_board['id_board'], $user_info['mod_cache']['ap'])),
					'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
					'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $board_name . '</a>',
					'board_class' => 'off',
					'css_class' => ''
				);

				call_integration_hook('integrate_boardindex_board', array(&$this_category, $row_board));

				// We can do some of the figuring-out-what-icon now.
				// For certain types of thing we also set up what the tooltip is.
				if ($this_category[$row_board['id_board']]['is_redirect'])
				{
					$this_category[$row_board['id_board']]['board_class'] = 'redirect';
					$this_category[$row_board['id_board']]['board_tooltip'] = $txt['redirect_board'];
				}
				elseif ($this_category[$row_board['id_board']]['new'] || $context['user']['is_guest'])
				{
					// If we're showing to guests, we want to give them the idea that something interesting is going on!
					$this_category[$row_board['id_board']]['board_class'] = 'on';
					$this_category[$row_board['id_board']]['board_tooltip'] = $txt['new_posts'];
				}

				else
				{
					$this_category[$row_board['id_board']]['board_tooltip'] = $txt['old_posts'];
				}
			}
		}

		// This is a child board.
		elseif (isset($row_boards[$row_board['id_parent']]['id_parent']) && $row_boards[$row_board['id_parent']]['id_parent'] == $board_index_options['parent_id'])
		{
			$isChild = true;

			// Ensure the parent has at least the most important info defined
			if (!isset($this_category[$row_board['id_parent']]))
				$this_category[$row_board['id_parent']] = array(
					'children' => array(),
					'children_new' => false,
					'board_class' => 'off'
				);

			$board_name = parse_bbc($row_board['board_name'], false, '', $context['description_allowed_tags']);
			$board_description = parse_bbc($row_board['description'], false, '', $context['description_allowed_tags']);

			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']] = array(
				'id' => $row_board['id_board'],
				'name' => $board_name,
				'description' => $board_description,
				'short_description' => shorten_subject($board_description, 128),
				'new' => empty($row_board['is_read']),
				'topics' => $row_board['num_topics'],
				'posts' => $row_board['num_posts'],
				'is_redirect' => $row_board['is_redirect'],
				'unapproved_topics' => $row_board['unapproved_topics'],
				'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
				'can_approve_posts' => !empty($user_info['mod_cache']['ap']) && ($user_info['mod_cache']['ap'] == array(0) || in_array($row_board['id_board'], $user_info['mod_cache']['ap'])),
				'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $board_name . '</a>'
			);

			// Counting child board posts in the parent's totals?
			if (!empty($board_index_options['countChildPosts']) && !$row_board['is_redirect'])
			{
				$row_boards[$row_board['id_parent']]['num_posts'] += $row_board['num_posts'];
				$row_boards[$row_board['id_parent']]['num_topics'] += $row_board['num_topics'];
			}

			// Does this board contain new boards?
			$this_category[$row_board['id_parent']]['children_new'] |= empty($row_board['is_read']);

			// Update the icon if appropriate
			if ($this_category[$row_board['id_parent']]['children_new'] && $this_category[$row_board['id_parent']]['board_class'] == 'off')
			{
				$this_category[$row_board['id_parent']]['board_class'] = 'on2';
				$this_category[$row_board['id_parent']]['board_tooltip'] = $txt['new_posts'];
			}

			// This is easier to use in many cases for the theme....
			$this_category[$row_board['id_parent']]['link_children'][] = &$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['link'];
		}

		// A further descendent (grandchild, great-grandchild, etc.)
		else
		{
			// Propagate some values to the parent board
			if (isset($row_boards[$row_board['id_parent']]))
			{
				if (empty($row_board['is_read']))
					$row_boards[$row_board['id_parent']]['is_read'] = $row_board['is_read'];

				if (!empty($board_index_options['countChildPosts']) && !$row_board['is_redirect'])
				{
					$row_boards[$row_board['id_parent']]['num_posts'] += $row_board['num_posts'];
					$row_boards[$row_board['id_parent']]['num_topics'] += $row_board['num_topics'];
				}

				if ($row_boards[$row_board['id_parent']]['poster_time'] < $row_board['poster_time'])
				{
					$row_boards[$row_board['id_parent']]['id_msg'] = $row_board['id_msg'];
					$row_boards[$row_board['id_parent']]['subject'] = $row_board['subject'];
					$row_boards[$row_board['id_parent']]['poster_time'] = $row_board['poster_time'];
					$row_boards[$row_board['id_parent']]['short_subject'] = (!empty($row_board['short_subject']) ? $row_board['short_subject'] : '');
					$row_boards[$row_board['id_parent']]['poster_name'] = $row_board['poster_name'];
					$row_boards[$row_board['id_parent']]['real_name'] = $row_board['real_name'];
					$row_boards[$row_board['id_parent']]['id_member'] = $row_board['id_member'];
					$row_boards[$row_board['id_parent']]['id_topic'] = $row_board['id_topic'];
					$row_boards[$row_board['id_parent']]['new_from'] = $row_board['new_from'];

					if (!empty($settings['avatars_on_boardIndex']))
					{
						$row_boards[$row_board['id_parent']]['avatar'] = $row_board['avatar'];
						$row_boards[$row_board['id_parent']]['email_address'] = $row_board['email_address'];
						$row_boards[$row_board['id_parent']]['member_filename'] = !empty($row_board['member_filename']) ? $row_board['member_filename'] : '';
					}
				}
			}

			continue;
		}

		// Prepare the subject, and make sure it's not too long.
		censorText($row_board['subject']);
		$row_board['short_subject'] = shorten_subject($row_board['subject'], 24);
		$this_last_post = array(
			'id' => $row_board['id_msg'],
			'time' => $row_board['poster_time'],
			'timestamp' => forum_time(true, $row_board['poster_time']),
			'subject' => $row_board['short_subject'],
			'member' => array(
				'id' => $row_board['id_member'],
				'username' => $row_board['poster_name'] != '' ? $row_board['poster_name'] : $txt['not_applicable'],
				'name' => $row_board['real_name'],
				'href' => $row_board['poster_name'] != '' && !empty($row_board['id_member']) ? $scripturl . '?action=profile;u=' . $row_board['id_member'] : '',
				'link' => $row_board['poster_name'] != '' ? (!empty($row_board['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_board['id_member'] . '">' . $row_board['real_name'] . '</a>' : $row_board['real_name']) : $txt['not_applicable'],
			),
			'start' => 'msg' . $row_board['new_from'],
			'topic' => $row_board['id_topic']
		);

		if (!empty($settings['avatars_on_boardIndex']))
			$this_last_post['member']['avatar'] = set_avatar_data(array(
				'avatar' => $row_board['avatar'],
				'email' => $row_board['email_address'],
				'filename' => !empty($row_board['member_filename']) ? $row_board['member_filename'] : ''
			));

		// Provide the href and link.
		if ($row_board['subject'] != '')
		{
			$this_last_post['href'] = $scripturl . '?topic=' . $row_board['id_topic'] . '.msg' . ($user_info['is_guest'] ? $row_board['id_msg'] : $row_board['new_from']) . (empty($row_board['is_read']) ? ';boardseen' : '') . '#new';
			$this_last_post['link'] = '<a href="' . $this_last_post['href'] . '" title="' . $row_board['subject'] . '">' . $row_board['short_subject'] . '</a>';
		}

		else
		{
			$this_last_post['href'] = '';
			$this_last_post['link'] = $txt['not_applicable'];
			$this_last_post['last_post_message'] = '';
		}

		// Set the last post in the parent board.
		if ($isChild && !empty($row_board['poster_time'])
			&& $row_boards[$row_board['id_parent']]['poster_time'] < $row_board['poster_time'])
			$this_category[$row_board['id_parent']]['last_post'] = $this_last_post;

		// Set the last post in the root board
		if (!$isChild && !empty($row_board['poster_time'])
			&& (empty($this_category[$row_board['id_board']]['last_post']['timestamp'])
				|| $this_category[$row_board['id_board']]['last_post']['timestamp'] < forum_time(true, $row_board['poster_time'])
			)
		)
			$this_category[$row_board['id_board']]['last_post'] = $this_last_post;

		// Just in the child...?
		if ($isChild)
			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['last_post'] = $this_last_post;

		// Determine a global most recent topic.
		if (!empty($board_index_options['set_latest_post']) && !empty($row_board['poster_time']) && $row_board['poster_time'] > $latest_post['timestamp'] && !$ignoreThisBoard)
			$latest_post = array(
				'timestamp' => $row_board['poster_time'],
				'ref' => &$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post']
			);
	}

	/* The board's and children's 'last_post's have:
	time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
	link, href, subject, start (where they should go for the first unread post.),
	and member. (which has id, name, link, href, username in it.)
	timeformat is a pricy call do it only for thos how get shown */
	// Fetch the board's moderators and moderator groups
	$boards = array_unique($boards);
	$moderators = getBoardModerators($boards);
	$groups = getBoardModeratorGroups($boards);
	if ($board_index_options['include_categories'])
		foreach ($categories as &$category)
		{
			foreach ($category['boards'] as &$board)
			{
				if (!empty($moderators[$board['id']]))
				{
					$board['moderators'] = $moderators[$board['id']];
					foreach ($moderators[$board['id']] as $moderator)
						$board['link_moderators'][] = $moderator['link'];
				}
				if (!empty($groups[$board['id']]))
				{
					$board['moderator_groups'] = $groups[$board['id']];
					foreach ($groups[$board['id']] as $group)
					{
						$board['link_moderators'][] = $group['link'];
						$board['link_moderator_groups'][] = $group['link'];
					}
				}
				if (!empty($board['last_post']))
					$board['last_post']['last_post_message'] = sprintf($txt['last_post_message'], $board['last_post']['member']['link'], $board['last_post']['link'], $board['last_post']['time'] > 0 ? timeformat($board['last_post']['time']) : $txt['not_applicable']);
			}
		}
	else
		foreach ($this_category as &$board)
		{
			if (!empty($moderators[$board['id']]))
			{
				$board['moderators'] = $moderators[$board['id']];
				foreach ($moderators[$board['id']] as $moderator)
					$board['link_moderators'][] = $moderator['link'];
			}
			if (!empty($groups[$board['id']]))
			{
				$board['moderator_groups'] = $groups[$board['id']];
				foreach ($groups[$board['id']] as $group)
				{
					$board['link_moderators'][] = $group['link'];
					$board['link_moderator_groups'][] = $group['link'];
				}
			}
			if (!empty($board['last_post']))
				$board['last_post']['last_post_message'] = sprintf($txt['last_post_message'], $board['last_post']['member']['link'], $board['last_post']['link'], $board['last_post']['time'] > 0 ? timeformat($board['last_post']['time']) : $txt['not_applicable']);
		}

	unset($category, $board);

	if ($board_index_options['include_categories'])
		sortCategories($categories);
	else
		sortBoards($this_category);

	// By now we should know the most recent post...if we wanna know it that is.
	if (!empty($board_index_options['set_latest_post']) && !empty($latest_post['ref']))
	{
		$latest_post['ref']['time'] = timeformat($latest_post['ref']['time']);
		$context['latest_post'] = $latest_post['ref'];
	}

	// I took my time, I hurried up, the choice was mine I didn't think enough
	$parsed_descriptions = setparsedDescriptions($to_parse);

	// I can't remember why but trying to make a ternary to get this all in one line is actually a Very Bad Idea.
	if ($board_index_options['include_categories'])
	{
		$categories = appendCategoriesParsedDescriptions($categories, $parsed_descriptions);
		call_integration_hook('integrate_getboardtree', array($board_index_options, &$categories));
	}

	else
	{
		$this_category = appendBoardsParsedDescriptions($this_category, $parsed_descriptions);
		call_integration_hook('integrate_getboardtree', array($board_index_options, &$this_category));
	}

	// I took my time, I hurried up, the choice was mine I didn't think enough
	setparsedDescriptions($to_parse);

	return $board_index_options['include_categories'] ? $categories : $this_category;
}

/**
 * retrieves parsed names and descriptions for categories and its corresponding boards.
 *
 * @param array $cat_ids
 * @return array
 */
function getParsedDescriptions($cat_ids = array())
{
	$parsed_results = array();

	// Yield anyone? :(
	foreach ($cat_ids as $cat_id)
		$parsed_results[$cat_id] = cache_get_data('parsed_cat_description_'. $cat_id);

	return $parsed_results;
}

/**
 * @param array $dataToParse
 * @return array Parsed data
 */
function setParsedDescriptions($dataToParse = array())
{
	global $context;

	if (empty($dataToParse))
		return array();

	$already_parsed_data = array();

	// If you're here it means your data isn't cached... or so the theory dictates...
	foreach ($dataToParse as $cat_id => $category)
	{
		$to_cache = array();

		// Sometimes we just want to update boards
		if (!empty($category['name']))
			$to_cache[$cat_id] = array(
				'name' => parse_bbc($category['name'], false, '', $context['description_allowed_tags']),
				'description' => parse_bbc($category['description'], false, '', $context['description_allowed_tags']),
				'boards' => array(),
			);

		if (!empty($category['boards']))
			foreach ($category['boards'] as $board_id => $board)
			{
				$to_cache[$cat_id]['boards'][$board_id]['name'] = parse_bbc($board['name'], false, '', $context['description_allowed_tags']);
				$to_cache[$cat_id]['boards'][$board_id]['description'] = parse_bbc($board['description'], false, '', $context['description_allowed_tags']);
			}

		// Let's have some fun shall we?
		if (!empty($cat_id))
		{
			$already_parsed_data[$cat_id] = cache_get_data('parsed_cat_description_'. $cat_id);

			foreach ($to_cache as $to_cache_cat_id => $to_cache_data)
			{
				// Append data!
				$already_parsed_data[$cat_id] = array(
					'name' => $category['name'],
					'description' => $category['description'],
					'boards' => array(),
				);

				if (!empty($category['boards']))
					foreach ($category['boards'] as $board)
						$already_parsed_data[$cat_id]['boards'] = array(
							'name' => $board['name'],
							'description' => $board['description'],
						);

				cache_put_data('parsed_cat_description_'. $cat_id, $already_parsed_data[$cat_id], 864000);
			}
		}
	}

	return $already_parsed_data;
}

/**
 * @param array $categories
 * @param array $parsed_descriptions
 *
 * @return array
 */
function appendCategoriesParsedDescriptions($categories = array(), $parsed_descriptions = array())
{
	if (empty($categories) || empty($parsed_description))
		return $categories;

	foreach ($parsed_descriptions as $id_cat => $parsed_description)
	{
		$categories[$id_cat]['name'] = $parsed_description['name'];
		$categories[$id_cat]['description'] = $parsed_description['description'];
	}

	return $categories;
}

/**
 * @param $this_category
 * @param $parsed_descriptions
 *
 * @return array
 */
function appendBoardsParsedDescriptions($this_category = array(), $parsed_descriptions = array())
{
	if (empty($this_category) || empty($parsed_descriptions))
		return $this_category;

	foreach ($parsed_descriptions as $id_cat => $parsed_description)
		foreach ($parsed_description['boards'] as $id_board => $board)
		{
			$this_category[$id_board]['name'] = $board['name'];
			$this_category[$id_board]['description'] = $board['description'];
		}

	return $this_category;
}

?>