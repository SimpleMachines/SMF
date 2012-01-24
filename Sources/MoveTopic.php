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

/*	This file contains the functions required to move topics from one board to
	another board.

	void MoveTopic()
		- is called to allow moderator to give reason for topic move.
		- must be called with a topic specified.
		- uses the MoveTopic template and main sub template.
		- if the member is the topic starter requires the move_own permission,
		  otherwise the move_any permission.
		- is accessed via ?action=movetopic.

	void MoveTopic2()
		- is called on the submit of MoveTopic.
		- requires the use of the Subs-Post.php file.
		- logs that topics have been moved in the moderation log.
		- if the member is the topic starter requires the move_own permission,
		  otherwise requires the move_any permission.
		- upon successful completion redirects to message index.
		- is accessed via ?action=movetopic2.

	void moveTopics(array topics, int destination_board)
		- performs the changes needed to move topics to new boards.
		- topics is an array of the topics to move, and destination_board is
		  where they should be moved to.
		- updates message, topic and calendar statistics.
		- does not check permissions. (assumes they have been checked!)
*/

// Move a topic.  Give the moderator a chance to post a reason.
function MoveTopic()
{
	global $txt, $board, $topic, $user_info, $context, $language, $scripturl, $settings, $smcFunc, $modSettings;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, ms.subject, t.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($id_member_started, $context['subject'], $context['is_approved']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Can they see it - if not approved?
	if ($modSettings['postmod_active'] && !$context['is_approved'])
		isAllowedTo('approve_posts');

	// Permission check!
	// !!!
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == $user_info['id'])
		{
			isAllowedTo('move_own');
			//$boards = array_merge(boardsAllowedTo('move_own'), boardsAllowedTo('move_any'));
		}
		else
			isAllowedTo('move_any');
	}
	//else
		//$boards = boardsAllowedTo('move_any');

	loadTemplate('MoveTopic');

	// Get a list of boards this moderator can move to.
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND b.redirect = {string:blank_redirect}
			AND b.id_board != {int:current_board}',
		array(
			'blank_redirect' => '',
			'current_board' => $board,
		)
	);
	$context['boards'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array (
				'name' => strip_tags($row['cat_name']),
				'boards' => array(),
			);

		$context['categories'][$row['id_cat']]['boards'][] = array(
			'id' => $row['id_board'],
			'name' => strip_tags($row['name']),
			'category' => strip_tags($row['cat_name']),
			'child_level' => $row['child_level'],
			'selected' => !empty($_SESSION['move_to_topic']) && $_SESSION['move_to_topic'] == $row['id_board'] && $row['id_board'] != $board,
		);
	}
	$smcFunc['db_free_result']($request);

	if (empty($context['categories']))
		fatal_lang_error('moveto_noboards', false);

	$context['page_title'] = $txt['move_topic'];

	$context['linktree'][] = array(
		'url' => $scripturl . '?topic=' . $topic . '.0',
		'name' => $context['subject'],
		'extra_before' => $settings['linktree_inline'] ? $txt['topic'] . ': ' : '',
	);

	$context['linktree'][] = array(
		'name' => $txt['move_topic'],
	);

	$context['back_to_topic'] = isset($_REQUEST['goback']);

	if ($user_info['language'] != $language)
	{
		loadLanguage('index', $language);
		$temp = $txt['movetopic_default'];
		loadLanguage('index');

		$txt['movetopic_default'] = $temp;
	}

	// Register this form and get a sequence number in $context.
	checkSubmitOnce('register');
}

// Execute the move.
function MoveTopic2()
{
	global $txt, $board, $topic, $scripturl, $sourcedir, $modSettings, $context;
	global $board, $language, $user_info, $smcFunc;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	// You can't choose to have a redirection topic and use an empty reason.
	if (isset($_POST['postRedirect']) && (!isset($_POST['reason']) || trim($_POST['reason']) == ''))
		fatal_lang_error('movetopic_no_reason', false);

	// Make sure this form hasn't been submitted before.
	checkSubmitOnce('check');

	$request = $smcFunc['db_query']('', '
		SELECT id_member_started, id_first_msg, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($id_member_started, $id_first_msg, $context['is_approved']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Can they see it?
	if (!$context['is_approved'])
		isAllowedTo('approve_posts');

	// Can they move topics on this board?
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == $user_info['id'])
		{
			isAllowedTo('move_own');
			$boards = array_merge(boardsAllowedTo('move_own'), boardsAllowedTo('move_any'));
		}
		else
			isAllowedTo('move_any');
	}
	else
		$boards = boardsAllowedTo('move_any');

	// If this topic isn't approved don't let them move it if they can't approve it!
	if ($modSettings['postmod_active'] && !$context['is_approved'] && !allowedTo('approve_posts'))
	{
		// Only allow them to move it to other boards they can't approve it in.
		$can_approve = boardsAllowedTo('approve_posts');
		$boards = array_intersect($boards, $can_approve);
	}

	checkSession();
	require_once($sourcedir . '/Subs-Post.php');

	// The destination board must be numeric.
	$_POST['toboard'] = (int) $_POST['toboard'];

	// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
	$request = $smcFunc['db_query']('', '
		SELECT b.count_posts, b.name, m.subject
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE {query_see_board}
			AND b.id_board = {int:to_board}
			AND b.redirect = {string:blank_redirect}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'to_board' => $_POST['toboard'],
			'blank_redirect' => '',
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board');
	list ($pcounter, $board_name, $subject) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Remember this for later.
	$_SESSION['move_to_topic'] = $_POST['toboard'];

	// Rename the topic...
	if (isset($_POST['reset_subject'], $_POST['custom_subject']) && $_POST['custom_subject'] != '')
	{
		$_POST['custom_subject'] = strtr($smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));
		// Keep checking the length.
		if ($smcFunc['strlen']($_POST['custom_subject']) > 100)
			$_POST['custom_subject'] = $smcFunc['substr']($_POST['custom_subject'], 0, 100);

		// If it's still valid move onwards and upwards.
		if ($_POST['custom_subject'] != '')
		{
			if (isset($_POST['enforce_subject']))
			{
				// Get a response prefix, but in the forum's default language.
				if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
				{
					if ($language === $user_info['language'])
						$context['response_prefix'] = $txt['response_prefix'];
					else
					{
						loadLanguage('index', $language, false);
						$context['response_prefix'] = $txt['response_prefix'];
						loadLanguage('index');
					}
					cache_put_data('response_prefix', $context['response_prefix'], 600);
				}

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET subject = {string:subject}
					WHERE id_topic = {int:current_topic}',
					array(
						'current_topic' => $topic,
						'subject' => $context['response_prefix'] . $_POST['custom_subject'],
					)
				);
			}

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET subject = {string:custom_subject}
				WHERE id_msg = {int:id_first_msg}',
				array(
					'id_first_msg' => $id_first_msg,
					'custom_subject' => $_POST['custom_subject'],
				)
			);

			// Fix the subject cache.
			updateStats('subject', $topic, $_POST['custom_subject']);
		}
	}

	// Create a link to this in the old board.
	//!!! Does this make sense if the topic was unapproved before? I'd just about say so.
	if (isset($_POST['postRedirect']))
	{
		// Should be in the boardwide language.
		if ($user_info['language'] != $language)
			loadLanguage('index', $language);

		$_POST['reason'] = $smcFunc['htmlspecialchars']($_POST['reason'], ENT_QUOTES);
		preparsecode($_POST['reason']);

		// Add a URL onto the message.
		$_POST['reason'] = strtr($_POST['reason'], array(
			$txt['movetopic_auto_board'] => '[url=' . $scripturl . '?board=' . $_POST['toboard'] . '.0]' . $board_name . '[/url]',
			$txt['movetopic_auto_topic'] => '[iurl]' . $scripturl . '?topic=' . $topic . '.0[/iurl]'
		));

		$msgOptions = array(
			'subject' => $txt['moved'] . ': ' . $subject,
			'body' => $_POST['reason'],
			'icon' => 'moved',
			'smileys_enabled' => 1,
		);
		$topicOptions = array(
			'board' => $board,
			'lock_mode' => 1,
			'mark_as_read' => true,
		);
		$posterOptions = array(
			'id' => $user_info['id'],
			'update_post_count' => empty($pcounter),
		);
		createPost($msgOptions, $topicOptions, $posterOptions);
	}

	$request = $smcFunc['db_query']('', '
		SELECT count_posts
		FROM {db_prefix}boards
		WHERE id_board = {int:current_board}
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);
	list ($pcounter_from) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($pcounter_from != $pcounter)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND approved = {int:is_approved}',
			array(
				'current_topic' => $topic,
				'is_approved' => 1,
			)
		);
		$posters = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($posters[$row['id_member']]))
				$posters[$row['id_member']] = 0;

			$posters[$row['id_member']]++;
		}
		$smcFunc['db_free_result']($request);

		foreach ($posters as $id_member => $posts)
		{
			// The board we're moving from counted posts, but not to.
			if (empty($pcounter_from))
				updateMemberData($id_member, array('posts' => 'posts - ' . $posts));
			// The reverse: from didn't, to did.
			else
				updateMemberData($id_member, array('posts' => 'posts + ' . $posts));
		}
	}

	// Do the move (includes statistics update needed for the redirect topic).
	moveTopics($topic, $_POST['toboard']);

	// Log that they moved this topic.
	if (!allowedTo('move_own') || $id_member_started != $user_info['id'])
		logAction('move', array('topic' => $topic, 'board_from' => $board, 'board_to' => $_POST['toboard']));
	// Notify people that this topic has been moved?
	sendNotifications($topic, 'move');

	// Why not go back to the original board in case they want to keep moving?
	if (!isset($_REQUEST['goback']))
		redirectexit('board=' . $board . '.0');
	else
		redirectexit('topic=' . $topic . '.0');
}

// Moves one or more topics to a specific board. (doesn't check permissions.)
function moveTopics($topics, $toBoard)
{
	global $sourcedir, $user_info, $modSettings, $smcFunc;

	// Empty array?
	if (empty($topics))
		return;
	// Only a single topic.
	elseif (is_numeric($topics))
		$topics = array($topics);
	$num_topics = count($topics);
	$fromBoards = array();

	// Destination board empty or equal to 0?
	if (empty($toBoard))
		return;

	// Are we moving to the recycle board?
	$isRecycleDest = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $toBoard;

	// Determine the source boards...
	$request = $smcFunc['db_query']('', '
		SELECT id_board, approved, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
			SUM(num_replies) AS num_replies
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_board, approved',
		array(
			'topics' => $topics,
		)
	);
	// Num of rows = 0 -> no topics found. Num of rows > 1 -> topics are on multiple boards.
	if ($smcFunc['db_num_rows']($request) == 0)
		return;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($fromBoards[$row['id_board']]['num_posts']))
		{
			$fromBoards[$row['id_board']] = array(
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
				'id_board' => $row['id_board']
			);
		}
		// Posts = (num_replies + 1) for each approved topic.
		$fromBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
		$fromBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

		// Add the topics to the right type.
		if ($row['approved'])
			$fromBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
		else
			$fromBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
	}
	$smcFunc['db_free_result']($request);

	// Move over the mark_read data. (because it may be read and now not by some!)
	$SaveAServer = max(0, $modSettings['maxMsgID'] - 50000);
	$request = $smcFunc['db_query']('', '
		SELECT lmr.id_member, lmr.id_msg, t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board
				AND lmr.id_msg > t.id_first_msg AND lmr.id_msg > {int:protect_lmr_msg})
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = lmr.id_member)
		WHERE t.id_topic IN ({array_int:topics})
			AND lmr.id_msg > IFNULL(lt.id_msg, 0)',
		array(
			'protect_lmr_msg' => $SaveAServer,
			'topics' => $topics,
		)
	);
	$log_topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$log_topics[] = array($row['id_topic'], $row['id_member'], $row['id_msg']);

		// Prevent queries from getting too big. Taking some steam off.
		if (count($log_topics) > 500)
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				$log_topics,
				array('id_topic', 'id_member')
			);

			$log_topics = array();
		}
	}
	$smcFunc['db_free_result']($request);

	// Now that we have all the topics that *should* be marked read, and by which members...
	if (!empty($log_topics))
	{
		// Insert that information into the database!
		$smcFunc['db_insert']('replace',
			'{db_prefix}log_topics',
			array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
			$log_topics,
			array('id_topic', 'id_member')
		);
	}

	// Update the number of posts on each board.
	$totalTopics = 0;
	$totalPosts = 0;
	$totalUnapprovedTopics = 0;
	$totalUnapprovedPosts = 0;
	foreach ($fromBoards as $stats)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET
				num_posts = CASE WHEN {int:num_posts} > num_posts THEN 0 ELSE num_posts - {int:num_posts} END,
				num_topics = CASE WHEN {int:num_topics} > num_topics THEN 0 ELSE num_topics - {int:num_topics} END,
				unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END,
				unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $stats['id_board'],
				'num_posts' => $stats['num_posts'],
				'num_topics' => $stats['num_topics'],
				'unapproved_posts' => $stats['unapproved_posts'],
				'unapproved_topics' => $stats['unapproved_topics'],
			)
		);
		$totalTopics += $stats['num_topics'];
		$totalPosts += $stats['num_posts'];
		$totalUnapprovedTopics += $stats['unapproved_topics'];
		$totalUnapprovedPosts += $stats['unapproved_posts'];
	}
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET
			num_topics = num_topics + {int:total_topics},
			num_posts = num_posts + {int:total_posts},' . ($isRecycleDest ? '
			unapproved_posts = {int:no_unapproved}, unapproved_topics = {int:no_unapproved}' : '
			unapproved_posts = unapproved_posts + {int:total_unapproved_posts},
			unapproved_topics = unapproved_topics + {int:total_unapproved_topics}') . '
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $toBoard,
			'total_topics' => $totalTopics,
			'total_posts' => $totalPosts,
			'total_unapproved_topics' => $totalUnapprovedTopics,
			'total_unapproved_posts' => $totalUnapprovedPosts,
			'no_unapproved' => 0,
		)
	);

	// Move the topic.  Done.  :P
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',
			unapproved_posts = {int:no_unapproved}, approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
			'no_unapproved' => 0,
		)
	);

	// If this was going to the recycle bin, check what messages are being recycled, and remove them from the queue.
	if ($isRecycleDest && ($totalUnapprovedTopics || $totalUnapprovedPosts))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
				and approved = {int:not_approved}',
			array(
				'topics' => $topics,
				'not_approved' => 0,
			)
		);
		$approval_msgs = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$approval_msgs[] = $row['id_msg'];
		$smcFunc['db_free_result']($request);

		// Empty the approval queue for these, as we're going to approve them next.
		if (!empty($approval_msgs))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg IN ({array_int:message_list})
					AND id_attach = {int:id_attach}',
				array(
					'message_list' => $approval_msgs,
					'id_attach' => 0,
				)
			);

		// Get all the current max and mins.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		$topicMaxMin = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$topicMaxMin[$row['id_topic']] = array(
				'min' => $row['id_first_msg'],
				'max' => $row['id_last_msg'],
			);
		}
		$smcFunc['db_free_result']($request);

		// Check the MAX and MIN are correct.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_topic',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If not, update.
			if ($row['first_msg'] != $topicMaxMin[$row['id_topic']]['min'] || $row['last_msg'] != $topicMaxMin[$row['id_topic']]['max'])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_first_msg = {int:first_msg}, id_last_msg = {int:last_msg}
					WHERE id_topic = {int:selected_topic}',
					array(
						'first_msg' => $row['first_msg'],
						'last_msg' => $row['last_msg'],
						'selected_topic' => $row['id_topic'],
					)
				);
		}
		$smcFunc['db_free_result']($request);
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}calendar
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
		)
	);

	// Mark target board as seen, if it was already marked as seen before.
	$request = $smcFunc['db_query']('', '
		SELECT (IFNULL(lb.id_msg, 0) >= b.id_msg_updated) AS isSeen
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE b.id_board = {int:id_board}',
		array(
			'current_member' => $user_info['id'],
			'id_board' => $toBoard,
		)
	);
	list ($isSeen) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($isSeen) && !$user_info['is_guest'])
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}log_boards',
			array('id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
			array($toBoard, $user_info['id'], $modSettings['maxMsgID']),
			array('id_board', 'id_member')
		);
	}

	// Update the cache?
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
		foreach ($topics as $topic_id)
			cache_put_data('topic_board-' . $topic_id, null, 120);

	require_once($sourcedir . '/Subs-Post.php');

	$updates = array_keys($fromBoards);
	$updates[] = $toBoard;

	updateLastMessages(array_unique($updates));

	// Update 'em pesky stats.
	updateStats('topic');
	updateStats('message');
	updateSettings(array(
		'calendar_updated' => time(),
	));
}

?>