<?php

/**
 * This file contains the functions required to move topics from one board to
 * another board.
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

use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\MessageIndex;
use SMF\Msg;
use SMF\Mail;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;

if (!defined('SMF'))
	die('No direct access...');

/**
 * This function allows to move a topic, making sure to ask the moderator
 * to give reason for topic move.
 * It must be called with a topic specified. (that is, Topic::$topic_id must
 * be set... @todo fix this thing.)
 * If the member is the topic starter requires the move_own permission,
 * otherwise the move_any permission.
 * Accessed via ?action=movetopic.
 *
 * Uses the MoveTopic template, main sub-template.
 */
function MoveTopic()
{
	if (empty(Topic::$topic_id))
		fatal_lang_error('no_access', false);

	$request = Db::$db->query('', '
		SELECT t.id_member_started, ms.subject, t.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => Topic::$topic_id,
		)
	);
	list ($id_member_started, Utils::$context['subject'], Utils::$context['is_approved']) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	// Can they see it - if not approved?
	if (Config::$modSettings['postmod_active'] && !Utils::$context['is_approved'])
		isAllowedTo('approve_posts');

	// Permission check!
	// @todo
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == User::$me->id)
		{
			isAllowedTo('move_own');
		}
		else
			isAllowedTo('move_any');
	}

	Utils::$context['move_any'] = User::$me->is_admin || Config::$modSettings['topic_move_any'];
	$boards = array();

	if (!Utils::$context['move_any'])
	{
		$boards = array_diff(boardsAllowedTo('post_new', true), array(Board::$info->id));
		if (empty($boards))
		{
			// No boards? Too bad...
			fatal_lang_error('moveto_no_boards');
		}
	}

	loadTemplate('MoveTopic');

	$options = array(
		'not_redirection' => true,
		'use_permissions' => Utils::$context['move_any'],
	);

	if (!empty($_SESSION['move_to_topic']) && $_SESSION['move_to_topic'] != Board::$info->id)
		$options['selected_board'] = $_SESSION['move_to_topic'];

	if (!Utils::$context['move_any'])
		$options['included_boards'] = $boards;

	Utils::$context['categories'] = MessageIndex::getBoardList($options);

	Utils::$context['page_title'] = Lang::$txt['move_topic'];

	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?topic=' . Topic::$topic_id . '.0',
		'name' => Utils::$context['subject'],
	);

	Utils::$context['linktree'][] = array(
		'name' => Lang::$txt['move_topic'],
	);

	Utils::$context['back_to_topic'] = isset($_REQUEST['goback']);

	if (User::$me->language != Lang::$default)
	{
		Lang::load('index', Lang::$default);
		$temp = Lang::$txt['movetopic_default'];
		Lang::load('index');

		Lang::$txt['movetopic_default'] = $temp;
	}

	Utils::$context['sub_template'] = 'move';

	moveTopicConcurrence();

	// Register this form and get a sequence number in Utils::$context.
	checkSubmitOnce('register');
}

/**
 * Execute the move of a topic.
 * It is called on the submit of MoveTopic.
 * This function logs that topics have been moved in the moderation log.
 * If the member is the topic starter requires the move_own permission,
 * otherwise requires the move_any permission.
 * Upon successful completion redirects to message index.
 * Accessed via ?action=movetopic2.
 */
function MoveTopic2()
{
	if (empty(Topic::$topic_id))
		fatal_lang_error('no_access', false);

	// You can't choose to have a redirection topic and use an empty reason.
	if (isset($_POST['postRedirect']) && (!isset($_POST['reason']) || trim($_POST['reason']) == ''))
		fatal_lang_error('movetopic_no_reason', false);

	moveTopicConcurrence();

	// Make sure this form hasn't been submitted before.
	checkSubmitOnce('check');

	$request = Db::$db->query('', '
		SELECT id_member_started, id_first_msg, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => Topic::$topic_id,
		)
	);
	list ($id_member_started, $id_first_msg, Utils::$context['is_approved']) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	// Can they see it?
	if (!Utils::$context['is_approved'])
		isAllowedTo('approve_posts');

	// Can they move topics on this board?
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == User::$me->id)
			isAllowedTo('move_own');
		else
			isAllowedTo('move_any');
	}

	checkSession();

	// The destination board must be numeric.
	$_POST['toboard'] = (int) $_POST['toboard'];

	// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
	$request = Db::$db->query('', '
		SELECT b.count_posts, b.name, m.subject
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE {query_see_board}
			AND b.id_board = {int:to_board}
			AND b.redirect = {string:blank_redirect}
		LIMIT 1',
		array(
			'current_topic' => Topic::$topic_id,
			'to_board' => $_POST['toboard'],
			'blank_redirect' => '',
		)
	);
	if (Db::$db->num_rows($request) == 0)
		fatal_lang_error('no_board');

	list ($pcounter, $board_name, $subject) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	// Remember this for later.
	$_SESSION['move_to_topic'] = $_POST['toboard'];

	// Rename the topic...
	if (isset($_POST['reset_subject'], $_POST['custom_subject']) && $_POST['custom_subject'] != '')
	{
		$_POST['custom_subject'] = strtr(Utils::htmlTrim(Utils::htmlspecialchars($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));
		// Keep checking the length.
		if (Utils::entityStrlen($_POST['custom_subject']) > 100)
			$_POST['custom_subject'] = Utils::entitySubstr($_POST['custom_subject'], 0, 100);

		// If it's still valid move onwards and upwards.
		if ($_POST['custom_subject'] != '')
		{
			if (isset($_POST['enforce_subject']))
			{
				// Get a response prefix, but in the forum's default language.
				if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix')))
				{
					if (Lang::$default === User::$me->language)
						Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
					else
					{
						Lang::load('index', Lang::$default, false);
						Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
						Lang::load('index');
					}
					CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
				}

				Db::$db->query('', '
					UPDATE {db_prefix}messages
					SET subject = {string:subject}
					WHERE id_topic = {int:current_topic}',
					array(
						'current_topic' => Topic::$topic_id,
						'subject' => Utils::$context['response_prefix'] . $_POST['custom_subject'],
					)
				);
			}

			Db::$db->query('', '
				UPDATE {db_prefix}messages
				SET subject = {string:custom_subject}
				WHERE id_msg = {int:id_first_msg}',
				array(
					'id_first_msg' => $id_first_msg,
					'custom_subject' => $_POST['custom_subject'],
				)
			);

			// Fix the subject cache.
			updateStats('subject', Topic::$topic_id, $_POST['custom_subject']);
		}
	}

	// Create a link to this in the old board.
	// @todo Does this make sense if the topic was unapproved before? I'd just about say so.
	if (isset($_POST['postRedirect']))
	{
		// Replace tokens with links in the reason.
		$reason_replacements = array(
			Lang::$txt['movetopic_auto_board'] => '[url="' . Config::$scripturl . '?board=' . $_POST['toboard'] . '.0"]' . $board_name . '[/url]',
			Lang::$txt['movetopic_auto_topic'] => '[iurl]' . Config::$scripturl . '?topic=' . Topic::$topic_id . '.0[/iurl]',
		);

		// Should be in the boardwide language.
		if (User::$me->language != Lang::$default)
		{
			Lang::load('index', Lang::$default);

			// Make sure we catch both languages in the reason.
			$reason_replacements += array(
				Lang::$txt['movetopic_auto_board'] => '[url="' . Config::$scripturl . '?board=' . $_POST['toboard'] . '.0"]' . $board_name . '[/url]',
				Lang::$txt['movetopic_auto_topic'] => '[iurl]' . Config::$scripturl . '?topic=' . Topic::$topic_id . '.0[/iurl]',
			);
		}

		$_POST['reason'] = Utils::htmlspecialchars($_POST['reason'], ENT_QUOTES);
		Msg::preparsecode($_POST['reason']);

		// Insert real links into the reason.
		$_POST['reason'] = strtr($_POST['reason'], $reason_replacements);

		// auto remove this MOVED redirection topic in the future?
		$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

		// redirect to the MOVED topic from topic list?
		$redirect_topic = isset($_POST['redirect_topic']) ? Topic::$topic_id : 0;

		$msgOptions = array(
			'subject' => Lang::$txt['moved'] . ': ' . $subject,
			'body' => $_POST['reason'],
			'icon' => 'moved',
			'smileys_enabled' => 1,
		);
		$topicOptions = array(
			'board' => Board::$info->id,
			'lock_mode' => 1,
			'mark_as_read' => true,
			'redirect_expires' => $redirect_expires,
			'redirect_topic' => $redirect_topic,
		);
		$posterOptions = array(
			'id' => User::$me->id,
			'update_post_count' => empty($pcounter),
		);
		Msg::create($msgOptions, $topicOptions, $posterOptions);
	}

	$request = Db::$db->query('', '
		SELECT count_posts
		FROM {db_prefix}boards
		WHERE id_board = {int:current_board}
		LIMIT 1',
		array(
			'current_board' => Board::$info->id,
		)
	);
	list ($pcounter_from) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if ($pcounter_from != $pcounter)
	{
		$request = Db::$db->query('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND approved = {int:is_approved}',
			array(
				'current_topic' => Topic::$topic_id,
				'is_approved' => 1,
			)
		);
		$posters = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (!isset($posters[$row['id_member']]))
				$posters[$row['id_member']] = 0;

			$posters[$row['id_member']]++;
		}
		Db::$db->free_result($request);

		foreach ($posters as $id_member => $posts)
		{
			// The board we're moving from counted posts, but not to.
			if (empty($pcounter_from))
				User::updateMemberData($id_member, array('posts' => 'posts - ' . $posts));
			// The reverse: from didn't, to did.
			else
				User::updateMemberData($id_member, array('posts' => 'posts + ' . $posts));
		}
	}

	// Do the move (includes statistics update needed for the redirect topic).
	moveTopics(Topic::$topic_id, $_POST['toboard']);

	// Log that they moved this topic.
	if (!allowedTo('move_own') || $id_member_started != User::$me->id)
		logAction('move', array('topic' => Topic::$topic_id, 'board_from' => Board::$info->id, 'board_to' => $_POST['toboard']));
	// Notify people that this topic has been moved?
	Mail::sendNotifications(Topic::$topic_id, 'move');

	call_integration_hook('integrate_movetopic2_end');

	// Why not go back to the original board in case they want to keep moving?
	if (!isset($_REQUEST['goback']))
		redirectexit('board=' . Board::$info->id . '.0');
	else
		redirectexit('topic=' . Topic::$topic_id . '.0');
}

/**
 * Moves one or more topics to a specific board. (doesn't check permissions.)
 * Determines the source boards for the supplied topics
 * Handles the moving of mark_read data
 * Updates the posts count of the affected boards
 *
 * @param int|int[] $topics The ID of a single topic to move or an array containing the IDs of multiple topics to move
 * @param int $toBoard The ID of the board to move the topics to
 */
function moveTopics($topics, $toBoard)
{
	// Empty array?
	if (empty($topics))
		return;

	// Only a single topic.
	if (is_numeric($topics))
		$topics = array($topics);

	$fromBoards = array();

	// Destination board empty or equal to 0?
	if (empty($toBoard))
		return;

	// Are we moving to the recycle board?
	$isRecycleDest = !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $toBoard;

	// Callback for search APIs to do their thing
	$searchAPI = SearchApi::load();

	if ($searchAPI->supportsMethod('topicsMoved'))
		$searchAPI->topicsMoved($topics, $toBoard);

	// Determine the source boards...
	$request = Db::$db->query('', '
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
	if (Db::$db->num_rows($request) == 0)
		return;
	while ($row = Db::$db->fetch_assoc($request))
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
	Db::$db->free_result($request);

	// Move over the mark_read data. (because it may be read and now not by some!)
	$SaveAServer = max(0, Config::$modSettings['maxMsgID'] - 50000);
	$request = Db::$db->query('', '
		SELECT lmr.id_member, lmr.id_msg, t.id_topic, COALESCE(lt.unwatched, 0) AS unwatched
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board
				AND lmr.id_msg > t.id_first_msg AND lmr.id_msg > {int:protect_lmr_msg})
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = lmr.id_member)
		WHERE t.id_topic IN ({array_int:topics})
			AND lmr.id_msg > COALESCE(lt.id_msg, 0)',
		array(
			'protect_lmr_msg' => $SaveAServer,
			'topics' => $topics,
		)
	);
	$log_topics = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$log_topics[] = array($row['id_topic'], $row['id_member'], $row['id_msg'], (is_null($row['unwatched']) ? 0 : $row['unwatched']));

		// Prevent queries from getting too big. Taking some steam off.
		if (count($log_topics) > 500)
		{
			Db::$db->insert('replace',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'),
				$log_topics,
				array('id_topic', 'id_member')
			);

			$log_topics = array();
		}
	}
	Db::$db->free_result($request);

	// Now that we have all the topics that *should* be marked read, and by which members...
	if (!empty($log_topics))
	{
		// Insert that information into the database!
		Db::$db->insert('replace',
			'{db_prefix}log_topics',
			array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'),
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
		Db::$db->query('', '
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
	Db::$db->query('', '
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
	Db::$db->query('', '
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
		$request = Db::$db->query('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
				AND approved = {int:not_approved}',
			array(
				'topics' => $topics,
				'not_approved' => 0,
			)
		);
		$approval_msgs = array();
		while ($row = Db::$db->fetch_assoc($request))
			$approval_msgs[] = $row['id_msg'];

		Db::$db->free_result($request);

		// Empty the approval queue for these, as we're going to approve them next.
		if (!empty($approval_msgs))
			Db::$db->query('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg IN ({array_int:message_list})
					AND id_attach = {int:id_attach}',
				array(
					'message_list' => $approval_msgs,
					'id_attach' => 0,
				)
			);

		// Get all the current max and mins.
		$request = Db::$db->query('', '
			SELECT id_topic, id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		$topicMaxMin = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			$topicMaxMin[$row['id_topic']] = array(
				'min' => $row['id_first_msg'],
				'max' => $row['id_last_msg'],
			);
		}
		Db::$db->free_result($request);

		// Check the MAX and MIN are correct.
		$request = Db::$db->query('', '
			SELECT id_topic, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_topic',
			array(
				'topics' => $topics,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			// If not, update.
			if ($row['first_msg'] != $topicMaxMin[$row['id_topic']]['min'] || $row['last_msg'] != $topicMaxMin[$row['id_topic']]['max'])
				Db::$db->query('', '
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
		Db::$db->free_result($request);
	}

	Db::$db->query('', '
		UPDATE {db_prefix}messages
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
		)
	);
	Db::$db->query('', '
		UPDATE {db_prefix}log_reported
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
		)
	);
	Db::$db->query('', '
		UPDATE {db_prefix}calendar
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
		)
	);

	// Mark target board as seen, if it was already marked as seen before.
	$request = Db::$db->query('', '
		SELECT (COALESCE(lb.id_msg, 0) >= b.id_msg_updated) AS isSeen
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE b.id_board = {int:id_board}',
		array(
			'current_member' => User::$me->id,
			'id_board' => $toBoard,
		)
	);
	list ($isSeen) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if (!empty($isSeen) && !User::$me->is_guest)
	{
		Db::$db->insert('replace',
			'{db_prefix}log_boards',
			array('id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
			array($toBoard, User::$me->id, Config::$modSettings['maxMsgID']),
			array('id_board', 'id_member')
		);
	}

	// Update the cache?
	if (!empty(CacheApi::$enable) && CacheApi::$enable >= 3)
		foreach ($topics as $topic_id)
			CacheApi::put('topic_board-' . $topic_id, null, 120);

	$updates = array_keys($fromBoards);
	$updates[] = $toBoard;

	Msg::updateLastMessages(array_unique($updates));

	// Update 'em pesky stats.
	updateStats('topic');
	updateStats('message');
	Config::updateModSettings(array(
		'calendar_updated' => time(),
	));
}

/**
 * Called after a topic is moved to update $board_link and $topic_link to point to new location
 */
function moveTopicConcurrence()
{
	if (isset($_GET['current_board']))
		$move_from = (int) $_GET['current_board'];

	if (empty($move_from) || empty(Board::$info->id) || empty(Topic::$topic_id))
		return true;

	if ($move_from == Board::$info->id)
		return true;
	else
	{
		$request = Db::$db->query('', '
			SELECT m.subject, b.name
			FROM {db_prefix}topics as t
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE t.id_topic = {int:topic_id}
			LIMIT 1',
			array(
				'topic_id' => Topic::$topic_id,
			)
		);
		list($topic_subject, $board_name) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		$board_link = '<a href="' . Config::$scripturl . '?board=' . Board::$info->id . '.0">' . $board_name . '</a>';
		$topic_link = '<a href="' . Config::$scripturl . '?topic=' . Topic::$topic_id . '.0">' . $topic_subject . '</a>';
		fatal_lang_error('topic_already_moved', false, array($topic_link, $board_link));
	}
}

?>