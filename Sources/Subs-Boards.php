<?php

/**
 * This file is mainly concerned with minor tasks relating to boards, such as
 * marking them read, collapsing categories, or quick moderation.
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
 * Mark a board or multiple boards read.
 *
 * @param int|array $boards The ID of a single board or an array of boards
 * @param bool $unread Whether we're marking them as unread
 */
function markBoardsRead($boards, $unread = false)
{
	global $user_info, $modSettings, $smcFunc;

	// Force $boards to be an array.
	if (!is_array($boards))
		$boards = array($boards);
	else
		$boards = array_unique($boards);

	// No boards, nothing to mark as read.
	if (empty($boards))
		return;

	// Allow the user to mark a board as unread.
	if ($unread)
	{
		// Clear out all the places where this lovely info is stored.
		// @todo Maybe not log_mark_read?
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_mark_read
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'board_list' => $boards,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_boards
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'board_list' => $boards,
			)
		);
	}
	// Otherwise mark the board as read.
	else
	{
		$markRead = array();
		foreach ($boards as $board)
			$markRead[] = array($modSettings['maxMsgID'], $user_info['id'], $board);

		// Update log_mark_read and log_boards.
		$smcFunc['db_insert']('replace',
			'{db_prefix}log_mark_read',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			$markRead,
			array('id_board', 'id_member')
		);

		$smcFunc['db_insert']('replace',
			'{db_prefix}log_boards',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			$markRead,
			array('id_board', 'id_member')
		);
	}

	// Get rid of useless log_topics data, because log_mark_read is better for it - even if marking unread - I think so...
	// @todo look at this...
	// The call to markBoardsRead() in Display() used to be simply
	// marking log_boards (the previous query only)
	$result = $smcFunc['db_query']('', '
		SELECT MIN(id_topic)
		FROM {db_prefix}log_topics
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $user_info['id'],
		)
	);
	list ($lowest_topic) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if (empty($lowest_topic))
		return;

	// @todo SLOW This query seems to eat it sometimes.
	$result = $smcFunc['db_query']('', '
		SELECT lt.id_topic
		FROM {db_prefix}log_topics AS lt
			INNER JOIN {db_prefix}topics AS t /*!40000 USE INDEX (PRIMARY) */ ON (t.id_topic = lt.id_topic
				AND t.id_board IN ({array_int:board_list}))
		WHERE lt.id_member = {int:current_member}
			AND lt.id_topic >= {int:lowest_topic}
			AND lt.unwatched != 1',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $boards,
			'lowest_topic' => $lowest_topic,
		)
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$topics[] = $row['id_topic'];
	$smcFunc['db_free_result']($result);

	if (!empty($topics))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_topics
			WHERE id_member = {int:current_member}
				AND id_topic IN ({array_int:topic_list})',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
			)
		);
}

/**
 * Mark one or more boards as read.
 */
function MarkRead()
{
	global $board, $topic, $user_info, $board_info, $modSettings, $smcFunc;

	// No Guests allowed!
	is_not_guest();

	checkSession('get');

	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all')
	{
		// Find all the boards this user can see.
		$result = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}',
			array(
			)
		);
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$boards[] = $row['id_board'];
		$smcFunc['db_free_result']($result);

		if (!empty($boards))
			markBoardsRead($boards, isset($_REQUEST['unread']));

		$_SESSION['id_msg_last_visit'] = $modSettings['maxMsgID'];
		if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false)
			redirectexit('action=unread');

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		redirectexit();
	}
	elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'unreadreplies')
	{
		// Make sure all the topics are integers!
		$topics = array_map('intval', explode('-', $_REQUEST['topics']));

		$request = $smcFunc['db_query']('', '
			SELECT id_topic, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:selected_topics})
				AND id_member = {int:current_user}',
			array(
				'selected_topics' => $topics,
				'current_user' => $user_info['id'],
			)
		);
		$logged_topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$logged_topics[$row['id_topic']] = $row['unwatched'];
		$smcFunc['db_free_result']($request);

		$markRead = array();
		foreach ($topics as $id_topic)
			$markRead[] = array($modSettings['maxMsgID'], $user_info['id'], $id_topic, (isset($logged_topics[$topic]) ? $logged_topics[$topic] : 0));

		$smcFunc['db_insert']('replace',
			'{db_prefix}log_topics',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'),
			$markRead,
			array('id_member', 'id_topic')
		);

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		redirectexit('action=unreadreplies');
	}

	// Special case: mark a topic unread!
	elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'topic')
	{
		// First, let's figure out what the latest message is.
		$result = $smcFunc['db_query']('', '
			SELECT t.id_first_msg, t.id_last_msg, COALESCE(lt.unwatched, 0) as unwatched
			FROM {db_prefix}topics as t
				LEFT JOIN {db_prefix}log_topics as lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			WHERE t.id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
				'current_member' => $user_info['id'],
			)
		);
		$topicinfo = $smcFunc['db_fetch_assoc']($result);
		$smcFunc['db_free_result']($result);

		if (!empty($_GET['t']))
		{
			// If they read the whole topic, go back to the beginning.
			if ($_GET['t'] >= $topicinfo['id_last_msg'])
				$earlyMsg = 0;
			// If they want to mark the whole thing read, same.
			elseif ($_GET['t'] <= $topicinfo['id_first_msg'])
				$earlyMsg = 0;
			// Otherwise, get the latest message before the named one.
			else
			{
				$result = $smcFunc['db_query']('', '
					SELECT MAX(id_msg)
					FROM {db_prefix}messages
					WHERE id_topic = {int:current_topic}
						AND id_msg >= {int:id_first_msg}
						AND id_msg < {int:topic_msg_id}',
					array(
						'current_topic' => $topic,
						'topic_msg_id' => (int) $_GET['t'],
						'id_first_msg' => $topicinfo['id_first_msg'],
					)
				);
				list ($earlyMsg) = $smcFunc['db_fetch_row']($result);
				$smcFunc['db_free_result']($result);
			}
		}
		// Marking read from first page?  That's the whole topic.
		elseif ($_REQUEST['start'] == 0)
			$earlyMsg = 0;
		else
		{
			$result = $smcFunc['db_query']('', '
				SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
				ORDER BY id_msg
				LIMIT {int:start}, 1',
				array(
					'current_topic' => $topic,
					'start' => (int) $_REQUEST['start'],
				)
			);
			list ($earlyMsg) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			$earlyMsg--;
		}

		// Blam, unread!
		$smcFunc['db_insert']('replace',
			'{db_prefix}log_topics',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'),
			array($earlyMsg, $user_info['id'], $topic, $topicinfo['unwatched']),
			array('id_member', 'id_topic')
		);

		redirectexit('board=' . $board . '.0');
	}
	else
	{
		$categories = array();
		$boards = array();

		if (isset($_REQUEST['c']))
		{
			$_REQUEST['c'] = explode(',', $_REQUEST['c']);
			foreach ($_REQUEST['c'] as $c)
				$categories[] = (int) $c;
		}
		if (isset($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}
		if (!empty($board))
			$boards[] = (int) $board;

		if (isset($_REQUEST['children']) && !empty($boards))
		{
			// They want to mark the entire tree starting with the boards specified
			// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them

			$request = $smcFunc['db_query']('', '
				SELECT b.id_board, b.id_parent
				FROM {db_prefix}boards AS b
				WHERE {query_see_board}
					AND b.child_level > {int:no_parents}
					AND b.id_board NOT IN ({array_int:board_list})
				ORDER BY child_level ASC',
				array(
					'no_parents' => 0,
					'board_list' => $boards,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				if (in_array($row['id_parent'], $boards))
					$boards[] = $row['id_board'];

			$smcFunc['db_free_result']($request);
		}

		$clauses = array();
		$clauseParameters = array();
		if (!empty($categories))
		{
			$clauses[] = 'id_cat IN ({array_int:category_list})';
			$clauseParameters['category_list'] = $categories;
		}
		if (!empty($boards))
		{
			$clauses[] = 'id_board IN ({array_int:board_list})';
			$clauseParameters['board_list'] = $boards;
		}

		if (empty($clauses))
			redirectexit();

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.' . implode(' OR b.', $clauses),
			array_merge($clauseParameters, array(
			))
		);
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		if (empty($boards))
			redirectexit();

		markBoardsRead($boards, isset($_REQUEST['unread']));

		foreach ($boards as $b)
		{
			if (isset($_SESSION['topicseen_cache'][$b]))
				$_SESSION['topicseen_cache'][$b] = array();
		}

		if (!isset($_REQUEST['unread']))
		{
			// Find all the boards this user can see.
			$result = $smcFunc['db_query']('', '
				SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE b.id_parent IN ({array_int:parent_list})
					AND {query_see_board}',
				array(
					'parent_list' => $boards,
				)
			);
			if ($smcFunc['db_num_rows']($result) > 0)
			{
				$logBoardInserts = array();
				while ($row = $smcFunc['db_fetch_assoc']($result))
					$logBoardInserts[] = array($modSettings['maxMsgID'], $user_info['id'], $row['id_board']);

				$smcFunc['db_insert']('replace',
					'{db_prefix}log_boards',
					array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
					$logBoardInserts,
					array('id_member', 'id_board')
				);
			}
			$smcFunc['db_free_result']($result);

			if (empty($board))
				redirectexit();
			else
				redirectexit('board=' . $board . '.0');
		}
		else
		{
			if (empty($board_info['parent']))
				redirectexit();
			else
				redirectexit('board=' . $board_info['parent'] . '.0');
		}
	}
}

/**
 * Get the id_member associated with the specified message.
 *
 * @param int $messageID The ID of the message
 * @return int The ID of the member associated with that post
 */
function getMsgMemberID($messageID)
{
	global $smcFunc;

	// Find the topic and make sure the member still exists.
	$result = $smcFunc['db_query']('', '
		SELECT COALESCE(mem.id_member, 0)
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:selected_message}
		LIMIT 1',
		array(
			'selected_message' => (int) $messageID,
		)
	);
	if ($smcFunc['db_num_rows']($result) > 0)
		list ($memberID) = $smcFunc['db_fetch_row']($result);
	// The message doesn't even exist.
	else
		$memberID = 0;

	$smcFunc['db_free_result']($result);

	return (int) $memberID;
}

/**
 * Modify the settings and position of a board.
 * Used by ManageBoards.php to change the settings of a board.
 *
 * @param int $board_id The ID of the board
 * @param array &$boardOptions An array of options related to the board
 */
function modifyBoard($board_id, &$boardOptions)
{
	global $cat_tree, $boards, $smcFunc;

	// Get some basic information about all boards and categories.
	getBoardTree();

	// Make sure given boards and categories exist.
	if (!isset($boards[$board_id]) || (isset($boardOptions['target_board']) && !isset($boards[$boardOptions['target_board']])) || (isset($boardOptions['target_category']) && !isset($cat_tree[$boardOptions['target_category']])))
		fatal_lang_error('no_board');

	$id = $board_id;
	call_integration_hook('integrate_pre_modify_board', array($id, &$boardOptions));

	// All things that will be updated in the database will be in $boardUpdates.
	$boardUpdates = array();
	$boardUpdateParameters = array();

	// In case the board has to be moved
	if (isset($boardOptions['move_to']))
	{
		// Move the board to the top of a given category.
		if ($boardOptions['move_to'] == 'top')
		{
			$id_cat = $boardOptions['target_category'];
			$child_level = 0;
			$id_parent = 0;
			$after = $cat_tree[$id_cat]['last_board_order'];
		}

		// Move the board to the bottom of a given category.
		elseif ($boardOptions['move_to'] == 'bottom')
		{
			$id_cat = $boardOptions['target_category'];
			$child_level = 0;
			$id_parent = 0;
			$after = 0;
			foreach ($cat_tree[$id_cat]['children'] as $id_board => $dummy)
				$after = max($after, $boards[$id_board]['order']);
		}

		// Make the board a child of a given board.
		elseif ($boardOptions['move_to'] == 'child')
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'] + 1;
			$id_parent = $boardOptions['target_board'];

			// People can be creative, in many ways...
			if (isChildOf($id_parent, $board_id))
				fatal_lang_error('mboards_parent_own_child_error', false);
			elseif ($id_parent == $board_id)
				fatal_lang_error('mboards_board_own_child_error', false);

			$after = $boards[$boardOptions['target_board']]['order'];

			// Check if there are already children and (if so) get the max board order.
			if (!empty($boards[$id_parent]['tree']['children']) && empty($boardOptions['move_first_child']))
				foreach ($boards[$id_parent]['tree']['children'] as $childBoard_id => $dummy)
					$after = max($after, $boards[$childBoard_id]['order']);
		}

		// Place a board before or after another board, on the same child level.
		elseif (in_array($boardOptions['move_to'], array('before', 'after')))
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'];
			$id_parent = $boards[$boardOptions['target_board']]['parent'];
			$after = $boards[$boardOptions['target_board']]['order'] - ($boardOptions['move_to'] == 'before' ? 1 : 0);
		}

		// Oops...?
		else
			trigger_error('modifyBoard(): The move_to value \'' . $boardOptions['move_to'] . '\' is incorrect', E_USER_ERROR);

		// Get a list of children of this board.
		$childList = array();
		recursiveBoards($childList, $boards[$board_id]['tree']);

		// See if there are changes that affect children.
		$childUpdates = array();
		$levelDiff = $child_level - $boards[$board_id]['level'];
		if ($levelDiff != 0)
			$childUpdates[] = 'child_level = child_level ' . ($levelDiff > 0 ? '+ ' : '') . '{int:level_diff}';
		if ($id_cat != $boards[$board_id]['category'])
			$childUpdates[] = 'id_cat = {int:category}';

		// Fix the children of this board.
		if (!empty($childList) && !empty($childUpdates))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET ' . implode(',
					', $childUpdates) . '
				WHERE id_board IN ({array_int:board_list})',
				array(
					'board_list' => $childList,
					'category' => $id_cat,
					'level_diff' => $levelDiff,
				)
			);

		// Make some room for this spot.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET board_order = board_order + {int:new_order}
			WHERE board_order > {int:insert_after}
				AND id_board != {int:selected_board}',
			array(
				'insert_after' => $after,
				'selected_board' => $board_id,
				'new_order' => 1 + count($childList),
			)
		);

		$boardUpdates[] = 'id_cat = {int:id_cat}';
		$boardUpdates[] = 'id_parent = {int:id_parent}';
		$boardUpdates[] = 'child_level = {int:child_level}';
		$boardUpdates[] = 'board_order = {int:board_order}';
		$boardUpdateParameters += array(
			'id_cat' => $id_cat,
			'id_parent' => $id_parent,
			'child_level' => $child_level,
			'board_order' => $after + 1,
		);
	}

	// This setting is a little twisted in the database...
	if (isset($boardOptions['posts_count']))
	{
		$boardUpdates[] = 'count_posts = {int:count_posts}';
		$boardUpdateParameters['count_posts'] = $boardOptions['posts_count'] ? 0 : 1;
	}

	// Set the theme for this board.
	if (isset($boardOptions['board_theme']))
	{
		$boardUpdates[] = 'id_theme = {int:id_theme}';
		$boardUpdateParameters['id_theme'] = (int) $boardOptions['board_theme'];
	}

	// Should the board theme override the user preferred theme?
	if (isset($boardOptions['override_theme']))
	{
		$boardUpdates[] = 'override_theme = {int:override_theme}';
		$boardUpdateParameters['override_theme'] = $boardOptions['override_theme'] ? 1 : 0;
	}

	// Who's allowed to access this board.
	if (isset($boardOptions['access_groups']))
	{
		$boardUpdates[] = 'member_groups = {string:member_groups}';
		$boardUpdateParameters['member_groups'] = implode(',', $boardOptions['access_groups']);
	}

	// And who isn't.
	if (isset($boardOptions['deny_groups']))
	{
		$boardUpdates[] = 'deny_member_groups = {string:deny_groups}';
		$boardUpdateParameters['deny_groups'] = implode(',', $boardOptions['deny_groups']);
	}

	if (isset($boardOptions['board_name']))
	{
		$boardUpdates[] = 'name = {string:board_name}';
		$boardUpdateParameters['board_name'] = $boardOptions['board_name'];
	}

	if (isset($boardOptions['board_description']))
	{
		$boardUpdates[] = 'description = {string:board_description}';
		$boardUpdateParameters['board_description'] = $boardOptions['board_description'];
	}

	if (isset($boardOptions['profile']))
	{
		$boardUpdates[] = 'id_profile = {int:profile}';
		$boardUpdateParameters['profile'] = (int) $boardOptions['profile'];
	}

	if (isset($boardOptions['redirect']))
	{
		$boardUpdates[] = 'redirect = {string:redirect}';
		$boardUpdateParameters['redirect'] = $boardOptions['redirect'];
	}

	if (isset($boardOptions['num_posts']))
	{
		$boardUpdates[] = 'num_posts = {int:num_posts}';
		$boardUpdateParameters['num_posts'] = (int) $boardOptions['num_posts'];
	}

	$id = $board_id;
	call_integration_hook('integrate_modify_board', array($id, $boardOptions, &$boardUpdates, &$boardUpdateParameters));

	// Do the updates (if any).
	if (!empty($boardUpdates))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET
				' . implode(',
				', $boardUpdates) . '
			WHERE id_board = {int:selected_board}',
			array_merge($boardUpdateParameters, array(
				'selected_board' => $board_id,
			))
		);

	// Before we add new access_groups or deny_groups, remove all of the old entries
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}board_permissions_view
		WHERE id_board = {int:selected_board}',
		array(
			'selected_board' => $board_id,
		)
	);

	// Do permission sync
	if (!empty($boardUpdateParameters['deny_groups']))
	{
		$insert = array();
		foreach ($boardOptions['deny_groups'] as $value)
			$insert[] = array($value, $board_id, 1);

		$smcFunc['db_insert']('insert',
			'{db_prefix}board_permissions_view',
			array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
			$insert,
			array('id_group', 'id_board', 'deny')
		);
	}

	if (!empty($boardUpdateParameters['member_groups']))
	{
		$insert = array();
		foreach ($boardOptions['access_groups'] as $value)
			$insert[] = array($value, $board_id, 0);

		$smcFunc['db_insert']('insert',
			'{db_prefix}board_permissions_view',
			array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
			$insert,
			array('id_group', 'id_board', 'deny')
		);
	}

	// Set moderators of this board.
	if (isset($boardOptions['moderators']) || isset($boardOptions['moderator_string']) || isset($boardOptions['moderator_groups']) || isset($boardOptions['moderator_group_string']))
	{
		// Reset current moderators for this board - if there are any!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}moderators
			WHERE id_board = {int:board_list}',
			array(
				'board_list' => $board_id,
			)
		);

		// Validate and get the IDs of the new moderators.
		if (isset($boardOptions['moderator_string']) && trim($boardOptions['moderator_string']) != '')
		{
			// Divvy out the usernames, remove extra space.
			$moderator_string = strtr($smcFunc['htmlspecialchars']($boardOptions['moderator_string'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
			$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
			for ($k = 0, $n = count($moderators); $k < $n; $k++)
			{
				$moderators[$k] = trim($moderators[$k]);

				if (strlen($moderators[$k]) == 0)
					unset($moderators[$k]);
			}

			// Find all the id_member's for the member_name's in the list.
			if (empty($boardOptions['moderators']))
				$boardOptions['moderators'] = array();
			if (!empty($moderators))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:moderator_list}) OR real_name IN ({array_string:moderator_list})
					LIMIT {int:limit}',
					array(
						'moderator_list' => $moderators,
						'limit' => count($moderators),
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$boardOptions['moderators'][] = $row['id_member'];
				$smcFunc['db_free_result']($request);
			}
		}

		// Add the moderators to the board.
		if (!empty($boardOptions['moderators']))
		{
			$inserts = array();
			foreach ($boardOptions['moderators'] as $moderator)
				$inserts[] = array($board_id, $moderator);

			$smcFunc['db_insert']('insert',
				'{db_prefix}moderators',
				array('id_board' => 'int', 'id_member' => 'int'),
				$inserts,
				array('id_board', 'id_member')
			);
		}

		// Reset current moderator groups for this board - if there are any!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}moderator_groups
			WHERE id_board = {int:board_list}',
			array(
				'board_list' => $board_id,
			)
		);

		// Validate and get the IDs of the new moderator groups.
		if (isset($boardOptions['moderator_group_string']) && trim($boardOptions['moderator_group_string']) != '')
		{
			// Divvy out the group names, remove extra space.
			$moderator_group_string = strtr($smcFunc['htmlspecialchars']($boardOptions['moderator_group_string'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $moderator_group_string, $matches);
			$moderator_groups = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_group_string)));
			for ($k = 0, $n = count($moderator_groups); $k < $n; $k++)
			{
				$moderator_groups[$k] = trim($moderator_groups[$k]);

				if (strlen($moderator_groups[$k]) == 0)
					unset($moderator_groups[$k]);
			}

			/* 	Find all the id_group's for all the group names in the list
				But skip any invalid ones (invisible/post groups/Administrator/Moderator) */
			if (empty($boardOptions['moderator_groups']))
				$boardOptions['moderator_groups'] = array();
			if (!empty($moderator_groups))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_group
					FROM {db_prefix}membergroups
					WHERE group_name IN ({array_string:moderator_group_list})
						AND hidden = {int:visible}
						AND min_posts = {int:negative_one}
						AND id_group NOT IN ({array_int:invalid_groups})
					LIMIT {int:limit}',
					array(
						'visible' => 0,
						'negative_one' => -1,
						'invalid_groups' => array(1, 3),
						'moderator_group_list' => $moderator_groups,
						'limit' => count($moderator_groups),
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$boardOptions['moderator_groups'][] = $row['id_group'];
				}
				$smcFunc['db_free_result']($request);
			}
		}

		// Add the moderator groups to the board.
		if (!empty($boardOptions['moderator_groups']))
		{
			$inserts = array();
			foreach ($boardOptions['moderator_groups'] as $moderator_group)
				$inserts[] = array($board_id, $moderator_group);

			$smcFunc['db_insert']('insert',
				'{db_prefix}moderator_groups',
				array('id_board' => 'int', 'id_group' => 'int'),
				$inserts,
				array('id_board', 'id_group')
			);
		}

		// Note that caches can now be wrong!
		updateSettings(array('settings_updated' => time()));
	}

	if (isset($boardOptions['move_to']))
		reorderBoards();

	clean_cache('data');

	if (empty($boardOptions['dont_log']))
		logAction('edit_board', array('board' => $board_id), 'admin');
}

/**
 * Create a new board and set its properties and position.
 * Allows (almost) the same options as the modifyBoard() function.
 * With the option inherit_permissions set, the parent board permissions
 * will be inherited.
 *
 * @param array $boardOptions An array of information for the new board
 * @return int The ID of the new board
 */
function createBoard($boardOptions)
{
	global $boards, $smcFunc;

	// Trigger an error if one of the required values is not set.
	if (!isset($boardOptions['board_name']) || trim($boardOptions['board_name']) == '' || !isset($boardOptions['move_to']) || !isset($boardOptions['target_category']))
		trigger_error('createBoard(): One or more of the required options is not set', E_USER_ERROR);

	if (in_array($boardOptions['move_to'], array('child', 'before', 'after')) && !isset($boardOptions['target_board']))
		trigger_error('createBoard(): Target board is not set', E_USER_ERROR);

	// Set every optional value to its default value.
	$boardOptions += array(
		'posts_count' => true,
		'override_theme' => false,
		'board_theme' => 0,
		'access_groups' => array(),
		'board_description' => '',
		'profile' => 1,
		'moderators' => '',
		'inherit_permissions' => true,
		'dont_log' => true,
	);

	$default_memgrps = '-1,0';

	$board_columns = array(
		'id_cat' => 'int', 'name' => 'string-255', 'description' => 'string', 'board_order' => 'int',
		'member_groups' => 'string', 'redirect' => 'string',
	);
	$board_parameters = array(
		$boardOptions['target_category'], $boardOptions['board_name'], '', 0,
		$default_memgrps, '',
	);

	call_integration_hook('integrate_create_board', array(&$boardOptions, &$board_columns, &$board_parameters));

	// Insert a board, the settings are dealt with later.
	$board_id = $smcFunc['db_insert']('',
		'{db_prefix}boards',
		$board_columns,
		$board_parameters,
		array('id_board'),
		1
	);

	$insert = array();

	foreach (explode(',', $default_memgrps) as $value)
		$insert[] = array($value, $board_id, 0);

	$smcFunc['db_insert']('',
		'{db_prefix}board_permissions_view',
		array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
		$insert,
		array('id_group', 'id_board', 'deny'),
		1
	);

	if (empty($board_id))
		return 0;

	// Change the board according to the given specifications.
	modifyBoard($board_id, $boardOptions);

	// Do we want the parent permissions to be inherited?
	if ($boardOptions['inherit_permissions'])
	{
		getBoardTree();

		if (!empty($boards[$board_id]['parent']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_profile
				FROM {db_prefix}boards
				WHERE id_board = {int:board_parent}
				LIMIT 1',
				array(
					'board_parent' => (int) $boards[$board_id]['parent'],
				)
			);
			list ($boardOptions['profile']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET id_profile = {int:new_profile}
				WHERE id_board = {int:current_board}',
				array(
					'new_profile' => $boardOptions['profile'],
					'current_board' => $board_id,
				)
			);
		}
	}

	// Clean the data cache.
	clean_cache('data');

	// Created it.
	logAction('add_board', array('board' => $board_id), 'admin');

	// Here you are, a new board, ready to be spammed.
	return $board_id;
}

/**
 * Remove one or more boards.
 * Allows to move the children of the board before deleting it
 * if moveChildrenTo is set to null, the child boards will be deleted.
 * Deletes:
 *   - all topics that are on the given boards;
 *   - all information that's associated with the given boards;
 * updates the statistics to reflect the new situation.
 *
 * @param array $boards_to_remove The boards to remove
 * @param int $moveChildrenTo The ID of the board to move the child boards to (null to remove the child boards, 0 to make them a top-level board)
 */
function deleteBoards($boards_to_remove, $moveChildrenTo = null)
{
	global $sourcedir, $boards, $smcFunc;

	// No boards to delete? Return!
	if (empty($boards_to_remove))
		return;

	getBoardTree();

	call_integration_hook('integrate_delete_board', array($boards_to_remove, &$moveChildrenTo));

	// If $moveChildrenTo is set to null, include the children in the removal.
	if ($moveChildrenTo === null)
	{
		// Get a list of the child boards that will also be removed.
		$child_boards_to_remove = array();
		foreach ($boards_to_remove as $board_to_remove)
			recursiveBoards($child_boards_to_remove, $boards[$board_to_remove]['tree']);

		// Merge the children with their parents.
		if (!empty($child_boards_to_remove))
			$boards_to_remove = array_unique(array_merge($boards_to_remove, $child_boards_to_remove));
	}
	// Move the children to a safe home.
	else
	{
		foreach ($boards_to_remove as $id_board)
		{
			// @todo Separate category?
			if ($moveChildrenTo === 0)
				fixChildren($id_board, 0, 0);
			else
				fixChildren($id_board, $boards[$moveChildrenTo]['level'] + 1, $moveChildrenTo);
		}
	}

	// Delete ALL topics in the selected boards (done first so topics can't be marooned.)
	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row['id_topic'];
	$smcFunc['db_free_result']($request);

	require_once($sourcedir . '/RemoveTopic.php');
	removeTopics($topics, false);

	// Delete the board's logs.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_boards
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete this board's moderators.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}moderators
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete this board's moderator groups.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}moderator_groups
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete any extra events in the calendar.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}calendar
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete any message icons that only appear on these boards.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}message_icons
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete the boards.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}boards
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete permissions
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}board_permissions_view
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Latest message/topic might not be there anymore.
	updateStats('message');
	updateStats('topic');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// Plus reset the cache to stop people getting odd results.
	updateSettings(array('settings_updated' => time()));

	// Clean the cache as well.
	clean_cache('data');

	// Let's do some serious logging.
	foreach ($boards_to_remove as $id_board)
		logAction('delete_board', array('boardname' => $boards[$id_board]['name']), 'admin');

	reorderBoards();
}

/**
 * Put all boards in the right order and sorts the records of the boards table.
 * Used by modifyBoard(), deleteBoards(), modifyCategory(), and deleteCategories() functions
 */
function reorderBoards()
{
	global $cat_tree, $boardList, $boards, $smcFunc;

	getBoardTree();

	// Set the board order for each category.
	$board_order = 0;
	foreach ($cat_tree as $catID => $dummy)
	{
		foreach ($boardList[$catID] as $boardID)
			if ($boards[$boardID]['order'] != ++$board_order)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET board_order = {int:new_order}
					WHERE id_board = {int:selected_board}',
					array(
						'new_order' => $board_order,
						'selected_board' => $boardID,
					)
				);
	}

	// Empty the board order cache
	cache_put_data('board_order', null, -3600);
}

/**
 * Fixes the children of a board by setting their child_levels to new values.
 * Used when a board is deleted or moved, to affect its children.
 *
 * @param int $parent The ID of the parent board
 * @param int $newLevel The new child level for each of the child boards
 * @param int $newParent The ID of the new parent board
 */
function fixChildren($parent, $newLevel, $newParent)
{
	global $smcFunc;

	// Grab all children of $parent...
	$result = $smcFunc['db_query']('', '
		SELECT id_board
		FROM {db_prefix}boards
		WHERE id_parent = {int:parent_board}',
		array(
			'parent_board' => $parent,
		)
	);
	$children = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$children[] = $row['id_board'];
	$smcFunc['db_free_result']($result);

	// ...and set it to a new parent and child_level.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET id_parent = {int:new_parent}, child_level = {int:new_child_level}
		WHERE id_parent = {int:parent_board}',
		array(
			'new_parent' => $newParent,
			'new_child_level' => $newLevel,
			'parent_board' => $parent,
		)
	);

	// Recursively fix the children of the children.
	foreach ($children as $child)
		fixChildren($child, $newLevel + 1, $child);
}

/**
 * Tries to load up the entire board order and category very very quickly
 * Returns an array with two elements, cats and boards
 *
 * @return array An array of categories and boards
 */
function getTreeOrder()
{
	global $smcFunc;

	static $tree_order = array(
		'cats' => array(),
		'boards' => array(),
	);

	if (!empty($tree_order['boards']))
		return $tree_order;

	if (($cached = cache_get_data('board_order', 86400)) !== null)
	{
		$tree_order = $cached;
		return $cached;
	}

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board, b.id_cat
		FROM {db_prefix}boards AS b
		ORDER BY b.board_order',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!in_array($row['id_cat'], $tree_order['cats']))
			$tree_order['cats'][] = $row['id_cat'];
		$tree_order['boards'][] = $row['id_board'];
	}
	$smcFunc['db_free_result']($request);

	cache_put_data('board_order', $tree_order, 86400);

	return $tree_order;
}

/**
 * Takes a board array and sorts it
 *
 * @param array &$boards The boards
 */
function sortBoards(array &$boards)
{
	$tree = getTreeOrder();

	$ordered = array();
	foreach ($tree['boards'] as $board)
		if (!empty($boards[$board]))
		{
			$ordered[$board] = $boards[$board];

			if (is_array($ordered[$board]) && !empty($ordered[$board]['boards']))
				sortBoards($ordered[$board]['boards']);

			if (is_array($ordered[$board]) && !empty($ordered[$board]['children']))
				sortBoards($ordered[$board]['children']);
		}

	$boards = $ordered;
}

/**
 * Takes a category array and sorts it
 *
 * @param array &$categories The categories
 */
function sortCategories(array &$categories)
{
	$tree = getTreeOrder();

	$ordered = array();
	foreach ($tree['cats'] as $cat)
		if (!empty($categories[$cat]))
		{
			$ordered[$cat] = $categories[$cat];
			if (!empty($ordered[$cat]['boards']))
				sortBoards($ordered[$cat]['boards']);
		}

	$categories = $ordered;
}

/**
 * Returns the given board's moderators, with their names and links
 *
 * @param array $boards The boards to get moderators of
 * @return array An array containing information about the moderators of each board
 */
function getBoardModerators(array $boards)
{
	global $smcFunc, $scripturl, $txt;

	if (empty($boards))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name, mo.id_board
		FROM {db_prefix}moderators AS mo
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mo.id_member)
		WHERE mo.id_board IN ({array_int:boards})',
		array(
			'boards' => $boards,
		)
	);
	$moderators = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($moderators[$row['id_board']]))
			$moderators[$row['id_board']] = array();

		$moderators[$row['id_board']][] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" title="' . $txt['board_moderator'] . '">' . $row['real_name'] . '</a>',
		);
	}
	$smcFunc['db_free_result']($request);

	return $moderators;
}

/**
 * Returns board's moderator groups with their names and link
 *
 * @param array $boards The boards to get moderator groups of
 * @return array An array containing information about the groups assigned to moderate each board
 */
function getBoardModeratorGroups(array $boards)
{
	global $smcFunc, $scripturl, $txt;

	if (empty($boards))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, bg.id_board
		FROM {db_prefix}moderator_groups AS bg
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bg.id_group)
		WHERE bg.id_board IN ({array_int:boards})',
		array(
			'boards' => $boards,
		)
	);
	$groups = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($groups[$row['id_board']]))
			$groups[$row['id_board']] = array();

		$groups[$row['id_board']][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'href' => $scripturl . '?action=groups;sa=members;group=' . $row['id_group'],
			'link' => '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" title="' . $txt['board_moderator'] . '">' . $row['group_name'] . '</a>',
		);
	}

	return $groups;
}

/**
 * Load a lot of useful information regarding the boards and categories.
 * The information retrieved is stored in globals:
 *  $boards		properties of each board.
 *  $boardList	a list of boards grouped by category ID.
 *  $cat_tree	properties of each category.
 */
function getBoardTree()
{
	global $cat_tree, $boards, $boardList, $smcFunc;

	$boardColumns = array(
		'COALESCE(b.id_board, 0) AS id_board', 'b.id_parent', 'b.name AS board_name',
		'b.description', 'b.child_level', 'b.board_order', 'b.count_posts', 'b.member_groups',
		'b.id_theme', 'b.override_theme', 'b.id_profile', 'b.redirect', 'b.num_posts',
		'b.num_topics', 'b.deny_member_groups', 'c.id_cat', 'c.name AS cat_name',
		'c.description AS cat_desc', 'c.cat_order', 'c.can_collapse',
	);

	// Let mods add extra columns and parameters to the SELECT query
	$extraBoardColumns = array();
	$extraBoardParameters = array();
	call_integration_hook('integrate_pre_boardtree', array(&$extraBoardColumns, &$extraBoardParameters));

	$boardColumns = array_unique(array_merge($boardColumns, $extraBoardColumns));
	$boardParameters = array_unique($extraBoardParameters);

	// Getting all the board and category information you'd ever wanted.
	$request = $smcFunc['db_query']('', '
		SELECT
			' . implode(', ', $boardColumns) . '
		FROM {db_prefix}categories AS c
			LEFT JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)
		WHERE {query_see_board}
		ORDER BY c.cat_order, b.child_level, b.board_order',
		$boardParameters
	);
	$cat_tree = array();
	$boards = array();
	$last_board_order = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($cat_tree[$row['id_cat']]))
		{
			$cat_tree[$row['id_cat']] = array(
				'node' => array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'description' => $row['cat_desc'],
					'order' => $row['cat_order'],
					'can_collapse' => $row['can_collapse']
				),
				'is_first' => empty($cat_tree),
				'last_board_order' => $last_board_order,
				'children' => array()
			);
			$prevBoard = 0;
			$curLevel = 0;
		}

		if (!empty($row['id_board']))
		{
			if ($row['child_level'] != $curLevel)
				$prevBoard = 0;

			$boards[$row['id_board']] = array(
				'id' => $row['id_board'],
				'category' => $row['id_cat'],
				'parent' => $row['id_parent'],
				'level' => $row['child_level'],
				'order' => $row['board_order'],
				'name' => $row['board_name'],
				'member_groups' => explode(',', $row['member_groups']),
				'deny_groups' => explode(',', $row['deny_member_groups']),
				'description' => $row['description'],
				'count_posts' => empty($row['count_posts']),
				'posts' => $row['num_posts'],
				'topics' => $row['num_topics'],
				'theme' => $row['id_theme'],
				'override_theme' => $row['override_theme'],
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'prev_board' => $prevBoard
			);
			$prevBoard = $row['id_board'];
			$last_board_order = $row['board_order'];

			if (empty($row['child_level']))
			{
				$cat_tree[$row['id_cat']]['children'][$row['id_board']] = array(
					'node' => &$boards[$row['id_board']],
					'is_first' => empty($cat_tree[$row['id_cat']]['children']),
					'children' => array()
				);
				$boards[$row['id_board']]['tree'] = &$cat_tree[$row['id_cat']]['children'][$row['id_board']];
			}
			else
			{
				// Parent doesn't exist!
				if (!isset($boards[$row['id_parent']]['tree']))
					fatal_lang_error('no_valid_parent', false, array($row['board_name']));

				// Wrong childlevel...we can silently fix this...
				if ($boards[$row['id_parent']]['tree']['node']['level'] != $row['child_level'] - 1)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET child_level = {int:new_child_level}
						WHERE id_board = {int:selected_board}',
						array(
							'new_child_level' => $boards[$row['id_parent']]['tree']['node']['level'] + 1,
							'selected_board' => $row['id_board'],
						)
					);

				$boards[$row['id_parent']]['tree']['children'][$row['id_board']] = array(
					'node' => &$boards[$row['id_board']],
					'is_first' => empty($boards[$row['id_parent']]['tree']['children']),
					'children' => array()
				);
				$boards[$row['id_board']]['tree'] = &$boards[$row['id_parent']]['tree']['children'][$row['id_board']];
			}
		}

		// If mods want to do anything with this board before we move on, now's the time
		call_integration_hook('integrate_boardtree_board', array($row));
	}
	$smcFunc['db_free_result']($request);

	// Get a list of all the boards in each category (using recursion).
	$boardList = array();
	foreach ($cat_tree as $catID => $node)
	{
		$boardList[$catID] = array();
		recursiveBoards($boardList[$catID], $node);
	}
}

/**
 * Recursively get a list of boards.
 * Used by getBoardTree
 *
 * @param array &$_boardList The board list
 * @param array &$_tree The board tree
 */
function recursiveBoards(&$_boardList, &$_tree)
{
	if (empty($_tree['children']))
		return;

	foreach ($_tree['children'] as $id => $node)
	{
		$_boardList[] = $id;
		recursiveBoards($_boardList, $node);
	}
}

/**
 * Returns whether the child board id is actually a child of the parent (recursive).
 *
 * @param int $child The ID of the child board
 * @param int $parent The ID of a parent board
 * @return boolean Whether the specified child board is actually a child of the specified parent board.
 */
function isChildOf($child, $parent)
{
	global $boards;

	if (empty($boards[$child]['parent']))
		return false;

	if ($boards[$child]['parent'] == $parent)
		return true;

	return isChildOf($boards[$child]['parent'], $parent);
}

?>