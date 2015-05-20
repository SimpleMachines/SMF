<?php

/**
 * This file currently only contains one function to collect the data needed to
 * show a list of boards for the board index and the message index.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
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
 * @param array $boardIndexOptions
 * @return array
 */

function getBoardIndex($boardIndexOptions)
{
	global $smcFunc, $scripturl, $user_info, $modSettings, $txt;
	global $settings, $options, $context, $sourcedir;

	require_once($sourcedir . '/Subs-Boards.php');

	// For performance, track the latest post while going through the boards.
	if (!empty($boardIndexOptions['set_latest_post']))
		$latest_post = array(
			'timestamp' => 0,
			'ref' => 0,
		);

	// Find all boards and categories, as well as related information.  This will be sorted by the natural order of boards and categories, which we control.
	$result_boards = $smcFunc['db_query']('boardindex_fetch_boards', '
		SELECT' . ($boardIndexOptions['include_categories'] ? '
			c.id_cat, c.name AS cat_name, c.description AS cat_desc,' : '') . '
			b.id_board, b.name AS board_name, b.description,
			CASE WHEN b.redirect != {string:blank_string} THEN 1 ELSE 0 END AS is_redirect,
			b.num_posts, b.num_topics, b.unapproved_posts, b.unapproved_topics, b.id_parent,
			IFNULL(m.poster_time, 0) AS poster_time, IFNULL(mem.member_name, m.poster_name) AS poster_name,
			m.subject, m.id_topic, IFNULL(mem.real_name, m.poster_name) AS real_name,
			' . ($user_info['is_guest'] ? ' 1 AS is_read, 0 AS new_from,' : '
			(IFNULL(lb.id_msg, 0) >= b.id_msg_updated) AS is_read, IFNULL(lb.id_msg, -1) + 1 AS new_from,' . ($boardIndexOptions['include_categories'] ? '
			c.can_collapse,' : '')) . '
			IFNULL(mem.id_member, 0) AS id_member, mem.avatar, m.id_msg' . (!empty($settings['avatars_on_indexes']) ? ',  mem.email_address, mem.avatar' : '') . '
		FROM {db_prefix}boards AS b' . ($boardIndexOptions['include_categories'] ? '
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' : '') . '
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})') . '
		WHERE {query_see_board}' . (empty($boardIndexOptions['countChildPosts']) ? (empty($boardIndexOptions['base_level']) ? '' : '
			AND b.child_level >= {int:child_level}') : '
			AND b.child_level BETWEEN ' . $boardIndexOptions['base_level'] . ' AND ' . ($boardIndexOptions['base_level'] + 1)) . '
			ORDER BY ' . (!empty($boardIndexOptions['include_categories']) ? 'c.cat_order, ' : '') . 'b.child_level, b.board_order',
		array(
			'current_member' => $user_info['id'],
			'child_level' => $boardIndexOptions['base_level'],
			'blank_string' => '',
		)
	);

	// Start with an empty array.
	if ($boardIndexOptions['include_categories'])
		$categories = array();
	else
		$this_category = array();
	$boards = array();

	// Run through the categories and boards (or only boards)....
	while ($row_board = $smcFunc['db_fetch_assoc']($result_boards))
	{
		// Perhaps we are ignoring this board?
		$ignoreThisBoard = in_array($row_board['id_board'], $user_info['ignoreboards']);
		$row_board['is_read'] = !empty($row_board['is_read']) || $ignoreThisBoard ? '1' : '0';

		// Add parent boards to the $boards list later used to fetch moderators
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'])
			$boards[] = $row_board['id_board'];

		if ($boardIndexOptions['include_categories'])
		{
			// Haven't set this category yet.
			if (empty($categories[$row_board['id_cat']]))
			{
				$categories[$row_board['id_cat']] = array(
					'id' => $row_board['id_cat'],
					'name' => $row_board['cat_name'],
					'description' => $row_board['cat_desc'],
					'is_collapsed' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1 && !empty($options['collapse_category_' . $row_board['id_cat']]),
					'can_collapse' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1,
					'href' => $scripturl . '#c' . $row_board['id_cat'],
					'boards' => array(),
					'new' => false,
					'css_class' => '',
				);
				$categories[$row_board['id_cat']]['link'] = '<a id="c' . $row_board['id_cat'] . '"></a>' . (!$context['user']['is_guest'] ? '<a href="' . $scripturl . '?action=unread;c='. $row_board['id_cat'] . '" title="' . sprintf($txt['new_posts_in_category'], strip_tags($row_board['cat_name'])) . '">' . $row_board['cat_name'] . '</a>' : $row_board['cat_name']);
			}

			// If this board has new posts in it (and isn't the recycle bin!) then the category is new.
			if (empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $row_board['id_board'])
				$categories[$row_board['id_cat']]['new'] |= empty($row_board['is_read']) && $row_board['poster_name'] != '';

			// Avoid showing category unread link where it only has redirection boards.
			$categories[$row_board['id_cat']]['show_unread'] = !empty($categories[$row_board['id_cat']]['show_unread']) ? 1 : !$row_board['is_redirect'];

			// Let's save some typing.  Climbing the array might be slower, anyhow.
			$this_category = &$categories[$row_board['id_cat']]['boards'];
		}

		// This is a parent board.
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'])
		{
			// Is this a new board, or just another moderator?
			if (!isset($this_category[$row_board['id_board']]))
			{
				// Not a child.
				$isChild = false;

				$this_category[$row_board['id_board']] = array(
					'new' => empty($row_board['is_read']),
					'id' => $row_board['id_board'],
					'name' => $row_board['board_name'],
					'description' => $row_board['description'],
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
					'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['board_name'] . '</a>',
					'board_class' => 'off',
					'css_class' => '',
				);

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
		// Found a child board.... make sure we've found its parent and the child hasn't been set already.
		elseif (isset($this_category[$row_board['id_parent']]['children']) && !isset($this_category[$row_board['id_parent']]['children'][$row_board['id_board']]))
		{
			// A valid child!
			$isChild = true;

			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']] = array(
				'id' => $row_board['id_board'],
				'name' => $row_board['board_name'],
				'description' => $row_board['description'],
				'short_description' => shorten_subject(strip_tags($row_board['description']), 128),
				'new' => empty($row_board['is_read']) && $row_board['poster_name'] != '',
				'topics' => $row_board['num_topics'],
				'posts' => $row_board['num_posts'],
				'is_redirect' => $row_board['is_redirect'],
				'unapproved_topics' => $row_board['unapproved_topics'],
				'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
				'can_approve_posts' => !empty($user_info['mod_cache']['ap']) && ($user_info['mod_cache']['ap'] == array(0) || in_array($row_board['id_board'], $user_info['mod_cache']['ap'])),
				'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['board_name'] . '</a>'
			);

			// Counting child board posts is... slow :/.
			if (!empty($boardIndexOptions['countChildPosts']) && !$row_board['is_redirect'])
			{
				$this_category[$row_board['id_parent']]['posts'] += $row_board['num_posts'];
				$this_category[$row_board['id_parent']]['topics'] += $row_board['num_topics'];
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
		// Child of a child... just add it on...
		elseif (!empty($boardIndexOptions['countChildPosts']))
		{
			if (!isset($parent_map))
				$parent_map = array();

			if (!isset($parent_map[$row_board['id_parent']]))
				foreach ($this_category as $id => $board)
				{
					if (!isset($board['children'][$row_board['id_parent']]))
						continue;

					$parent_map[$row_board['id_parent']] = array(&$this_category[$id], &$this_category[$id]['children'][$row_board['id_parent']]);
					$parent_map[$row_board['id_board']] = array(&$this_category[$id], &$this_category[$id]['children'][$row_board['id_parent']]);

					break;
				}

			if (isset($parent_map[$row_board['id_parent']]) && !$row_board['is_redirect'])
			{
				$parent_map[$row_board['id_parent']][0]['posts'] += $row_board['num_posts'];
				$parent_map[$row_board['id_parent']][0]['topics'] += $row_board['num_topics'];
				$parent_map[$row_board['id_parent']][1]['posts'] += $row_board['num_posts'];
				$parent_map[$row_board['id_parent']][1]['topics'] += $row_board['num_topics'];

				continue;
			}

			continue;
		}
		// Found a child of a child - skip.
		else
			continue;

		// Prepare the subject, and make sure it's not too long.
		censorText($row_board['subject']);
		$row_board['short_subject'] = shorten_subject($row_board['subject'], 24);
		$this_last_post = array(
			'id' => $row_board['id_msg'],
			'time' => $row_board['poster_time'] > 0 ? timeformat($row_board['poster_time']) : $txt['not_applicable'],
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

		if (!empty($settings['avatars_on_indexes']))
		{
			if (!empty($modSettings['gravatarOverride']))
			{
				if (!empty($modSettings['gravatarAllowExtraEmail']) && !empty($row_board['avatar']) && stristr($row_board['avatar'], 'gravatar://'))
					$image = get_gravatar_url($smcFunc['substr']($row_board['avatar'], 11));
				else
					$image = get_gravatar_url($row_board['email_address']);
			}
			else
			{
				// So it's stored in the member table?
				if (!empty($row_board['avatar']))
				{
					if (stristr($row_board['avatar'], 'gravatar://'))
					{
						if ($row_board['avatar'] == 'gravatar://')
							$image = get_gravatar_url($row_board['email_address']);
						elseif (!empty($modSettings['gravatarAllowExtraEmail']))
							$image = get_gravatar_url($smcFunc['substr']($row_board['avatar'], 11));
					}
					else
						$image = stristr($row_board['avatar'], 'http://') ? $row_board['avatar'] : $modSettings['avatar_url'] . '/' . $row_board['avatar'];
				}
				// Right... no avatar...
				else
					$this_last_post['member']['avatar'] = array(
						'name' => '',
						'image' => '',
						'href' => '',
						'url' => '',
					);
			}
			if (!empty($image))
				$this_last_post['member']['avatar'] = array(
					'name' => $row_board['avatar'],
					'image' => '<img class="avatar" src="' . $image . '" />',
					'href' => $image,
					'url' => $image,
				);
		}

		// Provide the href and link.
		if ($row_board['subject'] != '')
		{
			$this_last_post['href'] = $scripturl . '?topic=' . $row_board['id_topic'] . '.msg' . ($user_info['is_guest'] ? $row_board['id_msg'] : $row_board['new_from']) . (empty($row_board['is_read']) ? ';boardseen' : '') . '#new';
			$this_last_post['link'] = '<a href="' . $this_last_post['href'] . '" title="' . $row_board['subject'] . '">' . $row_board['short_subject'] . '</a>';
			/* The board's and children's 'last_post's have:
			time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
			link, href, subject, start (where they should go for the first unread post.),
			and member. (which has id, name, link, href, username in it.) */
			$this_last_post['last_post_message'] = sprintf($txt['last_post_message'], $this_last_post['member']['link'], $this_last_post['link'], $this_last_post['time']);
		}
		else
		{
			$this_last_post['href'] = '';
			$this_last_post['link'] = $txt['not_applicable'];
			$this_last_post['last_post_message'] = '';
		}

		// Set the last post in the parent board.
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'] || ($isChild && !empty($row_board['poster_time']) && $this_category[$row_board['id_parent']]['last_post']['timestamp'] < forum_time(true, $row_board['poster_time'])))
			$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'] = $this_last_post;
		// Just in the child...?
		if ($isChild)
		{
			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['last_post'] = $this_last_post;

			// If there are no posts in this board, it really can't be new...
			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['new'] &= $row_board['poster_name'] != '';
		}
		// No last post for this board?  It's not new then, is it..?
		elseif ($row_board['poster_name'] == '')
			$this_category[$row_board['id_board']]['new'] = false;

		// Determine a global most recent topic.
		if (!empty($boardIndexOptions['set_latest_post']) && !empty($row_board['poster_time']) && $row_board['poster_time'] > $latest_post['timestamp'] && !$ignoreThisBoard)
			$latest_post = array(
				'timestamp' => $row_board['poster_time'],
				'ref' => &$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'],
			);
	}
	$smcFunc['db_free_result']($result_boards);

	// Fetch the board's moderators and moderator groups
	$boards = array_unique($boards);
	$moderators = getBoardModerators($boards);
	$groups = getBoardModeratorGroups($boards);
	if ($boardIndexOptions['include_categories'])
	{
		foreach ($categories as $k => $category)
		{
			foreach ($category['boards'] as $j => $board)
			{
				if (!empty($moderators[$board['id']]))
				{
					$categories[$k]['boards'][$j]['moderators'] = $moderators[$board['id']];
					foreach ($moderators[$board['id']] as $moderator)
						$categories[$k]['boards'][$j]['link_moderators'][] = $moderator['link'];
				}
				if (!empty($groups[$board['id']]))
				{
					$categories[$k]['boards'][$j]['moderator_groups'] = $groups[$board['id']];
					foreach ($groups[$board['id']] as $group)
					{
						$categories[$k]['boards'][$j]['link_moderators'][] = $group['link'];
						$categories[$k]['boards'][$j]['link_moderator_groups'][] = $group['link'];
					}
				}
			}
		}
	}
	else
	{
		foreach ($this_category as $k => $board)
		{
			if (!empty($moderators[$board['id']]))
			{
				$this_category[$k]['moderators'] = $moderators[$board['id']];
				foreach ($moderators[$board['id']] as $moderator)
					$this_category[$k]['link_moderators'][] = $moderator['link'];
			}
			if (!empty($groups[$board['id']]))
			{
				$this_category[$k]['moderator_groups'] = $groups[$board['id']];
				foreach ($groups[$board['id']] as $group)
				{
					$this_category[$k]['link_moderators'][] = $group['link'];
					$this_category[$k]['link_moderator_groups'][] = $group['link'];
				}
			}
		}
	}

	if ($boardIndexOptions['include_categories'])
		sortCategories($categories);
	else
		sortBoards($this_category);

	// By now we should know the most recent post...if we wanna know it that is.
	if (!empty($boardIndexOptions['set_latest_post']) && !empty($latest_post['ref']))
		$context['latest_post'] = $latest_post['ref'];

	// I can't remember why but trying to make a ternary to get this all in one line is actually a Very Bad Idea.
	if ($boardIndexOptions['include_categories'])
		call_integration_hook('integrate_getboardtree', array($boardIndexOptions, &$categories));
	else
		call_integration_hook('integrate_getboardtree', array($boardIndexOptions, &$this_category));

	return $boardIndexOptions['include_categories'] ? $categories : $this_category;
}

?>