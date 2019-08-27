<?php

/**
 * The contents of this file handle the deletion of topics, posts, and related
 * paraphernalia.
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

/*	The contents of this file handle the deletion of topics, posts, and related
	paraphernalia.  It has the following functions:

*/

/**
 * Completely remove an entire topic.
 * Redirects to the board when completed.
 */
function RemoveTopic2()
{
	global $user_info, $topic, $board, $sourcedir, $smcFunc, $modSettings;

	// Make sure they aren't being lead around by someone. (:@)
	checkSession('get');

	// This file needs to be included for sendNotifications().
	require_once($sourcedir . '/Subs-Post.php');

	// Trying to fool us around, are we?
	if (empty($topic))
		redirectexit();

	removeDeleteConcurrence();

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, ms.subject, t.approved, t.locked
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($starter, $subject, $approved, $locked) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($starter == $user_info['id'] && !allowedTo('remove_any'))
		isAllowedTo('remove_own');
	else
		isAllowedTo('remove_any');

	// Can they see the topic?
	if ($modSettings['postmod_active'] && !$approved && $starter != $user_info['id'])
		isAllowedTo('approve_posts');

	// Ok, we got that far, but is it locked?
	if ($locked)
	{
		if (!($locked == 1 && $starter == $user_info['id'] || allowedTo('lock_any')))
			fatal_lang_error('cannot_remove_locked', 'user');
	}

	// Notify people that this topic has been removed.
	sendNotifications($topic, 'remove');

	removeTopics($topic);

	// Note, only log topic ID in native form if it's not gone forever.
	if (allowedTo('remove_any') || (allowedTo('remove_own') && $starter == $user_info['id']))
		logAction('remove', array((empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $board ? 'topic' : 'old_topic_id') => $topic, 'subject' => $subject, 'member' => $starter, 'board' => $board));

	redirectexit('board=' . $board . '.0');
}

/**
 * Remove just a single post.
 * On completion redirect to the topic or to the board.
 */
function DeleteMessage()
{
	global $user_info, $topic, $board, $modSettings, $smcFunc;

	checkSession('get');

	$_REQUEST['msg'] = (int) $_REQUEST['msg'];

	// Is $topic set?
	if (empty($topic) && isset($_REQUEST['topic']))
		$topic = (int) $_REQUEST['topic'];

	removeDeleteConcurrence();

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, m.id_member, m.subject, m.poster_time, m.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = {int:id_msg} AND m.id_topic = {int:current_topic})
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		)
	);
	list ($starter, $poster, $subject, $post_time, $approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Verify they can see this!
	if ($modSettings['postmod_active'] && !$approved && !empty($poster) && $poster != $user_info['id'])
		isAllowedTo('approve_posts');

	if ($poster == $user_info['id'])
	{
		if (!allowedTo('delete_own'))
		{
			if ($starter == $user_info['id'] && !allowedTo('delete_any'))
				isAllowedTo('delete_replies');
			elseif (!allowedTo('delete_any'))
				isAllowedTo('delete_own');
		}
		elseif (!allowedTo('delete_any') && ($starter != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $post_time + $modSettings['edit_disable_time'] * 60 < time())
			fatal_lang_error('modify_post_time_passed', false);
	}
	elseif ($starter == $user_info['id'] && !allowedTo('delete_any'))
		isAllowedTo('delete_replies');
	else
		isAllowedTo('delete_any');

	// If the full topic was removed go back to the board.
	$full_topic = removeMessage($_REQUEST['msg']);

	if (allowedTo('delete_any') && (!allowedTo('delete_own') || $poster != $user_info['id']))
		logAction('delete', array('topic' => $topic, 'subject' => $subject, 'member' => $poster, 'board' => $board));

	// We want to redirect back to recent action.
	if (isset($_REQUEST['modcenter']))
		redirectexit('action=moderate;area=reportedposts;done');
	elseif (isset($_REQUEST['recent']))
		redirectexit('action=recent');
	elseif (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u']))
		redirectexit('action=profile;u=' . $_REQUEST['u'] . ';area=showposts;start=' . $_REQUEST['start']);
	elseif ($full_topic)
		redirectexit('board=' . $board . '.0');
	else
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * So long as you are sure... all old posts will be gone.
 * Used in ManageMaintenance.php to prune old topics.
 */
function RemoveOldTopics2()
{
	global $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('post', 'admin');

	// No boards at all?  Forget it then :/.
	if (empty($_POST['boards']))
		redirectexit('action=admin;area=maintain;sa=topics');

	// This should exist, but we can make sure.
	$_POST['delete_type'] = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'nothing';

	// Custom conditions.
	$condition = '';
	$condition_params = array(
		'boards' => array_keys($_POST['boards']),
		'poster_time' => time() - 3600 * 24 * $_POST['maxdays'],
	);

	// Just moved notice topics?
	// Note that this ignores redirection topics unless it's a non-expiring one
	if ($_POST['delete_type'] == 'moved')
	{
		$condition .= '
			AND m.icon = {string:icon}
			AND t.locked = {int:locked}
			AND t.redirect_expires = {int:not_expiring}';
		$condition_params['icon'] = 'moved';
		$condition_params['locked'] = 1;
		$condition_params['not_expiring'] = 0;
	}
	// Otherwise, maybe locked topics only?
	elseif ($_POST['delete_type'] == 'locked')
	{
		// Exclude moved/merged notices since we have another option for those...
		$condition .= '
			AND t.icon != {string:icon}
			AND t.locked = {int:locked}';
		$condition_params['icon'] = 'moved';
		$condition_params['locked'] = 1;
	}

	// Exclude stickies?
	if (isset($_POST['delete_old_not_sticky']))
	{
		$condition .= '
			AND t.is_sticky = {int:is_sticky}';
		$condition_params['is_sticky'] = 0;
	}

	// All we're gonna do here is grab the id_topic's and send them to removeTopics().
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
		WHERE
			m.poster_time < {int:poster_time}' . $condition . '
			AND t.id_board IN ({array_int:boards})',
		$condition_params
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row['id_topic'];
	$smcFunc['db_free_result']($request);

	removeTopics($topics, false, true);

	// Log an action into the moderation log.
	logAction('pruned', array('days' => $_POST['maxdays']));

	redirectexit('action=admin;area=maintain;sa=topics;done=purgeold');
}

/**
 * Removes the passed id_topic's. (permissions are NOT checked here!).
 *
 * @param array|int $topics The topics to remove (can be an id or an array of ids).
 * @param bool $decreasePostCount Whether to decrease the users' post counts
 * @param bool $ignoreRecycling Whether to ignore recycling board settings
 * @param bool $updateBoardCount Whether to adjust topic counts for the boards
 */
function removeTopics($topics, $decreasePostCount = true, $ignoreRecycling = false, $updateBoardCount = true)
{
	global $sourcedir, $modSettings, $smcFunc;

	// Nothing to do?
	if (empty($topics))
		return;
	// Only a single topic.
	if (is_numeric($topics))
		$topics = array($topics);

	$recycle_board = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? (int) $modSettings['recycle_board'] : 0;

	// Do something before?
	call_integration_hook('integrate_remove_topics_before', array($topics, $recycle_board));

	// Decrease the post counts.
	if ($decreasePostCount)
	{
		$requestMembers = $smcFunc['db_query']('', '
			SELECT m.id_member, COUNT(*) AS posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_topic IN ({array_int:topics})' . (!empty($recycle_board) ? '
				AND m.id_board != {int:recycled_board}' : '') . '
				AND b.count_posts = {int:do_count_posts}
				AND m.approved = {int:is_approved}
			GROUP BY m.id_member',
			array(
				'do_count_posts' => 0,
				'recycled_board' => $recycle_board,
				'topics' => $topics,
				'is_approved' => 1,
			)
		);
		if ($smcFunc['db_num_rows']($requestMembers) > 0)
		{
			while ($rowMembers = $smcFunc['db_fetch_assoc']($requestMembers))
				updateMemberData($rowMembers['id_member'], array('posts' => 'posts - ' . $rowMembers['posts']));
		}
		$smcFunc['db_free_result']($requestMembers);
	}

	// Recycle topics that aren't in the recycle board...
	if (!empty($recycle_board) && !$ignoreRecycling)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_board, unapproved_posts, approved
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})
				AND id_board != {int:recycle_board}
			LIMIT {int:limit}',
			array(
				'recycle_board' => $recycle_board,
				'topics' => $topics,
				'limit' => count($topics),
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			// Get topics that will be recycled.
			$recycleTopics = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				$recycleTopics[] = $row['id_topic'];

				// Set the id_previous_board for this topic - and make it not sticky.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_previous_board = {int:id_previous_board}, is_sticky = {int:not_sticky}
					WHERE id_topic = {int:id_topic}',
					array(
						'id_previous_board' => $row['id_board'],
						'id_topic' => $row['id_topic'],
						'not_sticky' => 0,
					)
				);
			}
			$smcFunc['db_free_result']($request);

			// Move the topics to the recycle board.
			require_once($sourcedir . '/MoveTopic.php');
			moveTopics($recycleTopics, $modSettings['recycle_board']);

			// Close reports that are being recycled.
			require_once($sourcedir . '/ModerationCenter.php');

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_reported
				SET closed = {int:is_closed}
				WHERE id_topic IN ({array_int:recycle_topics})',
				array(
					'recycle_topics' => $recycleTopics,
					'is_closed' => 1,
				)
			);

			updateSettings(array('last_mod_report_action' => time()));

			require_once($sourcedir . '/Subs-ReportedContent.php');
			recountOpenReports('posts');

			// Topics that were recycled don't need to be deleted, so subtract them.
			$topics = array_diff($topics, $recycleTopics);
		}
		else
			$smcFunc['db_free_result']($request);
	}

	// Still topics left to delete?
	if (empty($topics))
		return;

	// Callback for search APIs to do their thing
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if ($searchAPI->supportsMethod('topicsRemoved'))
		$searchAPI->topicsRemoved($topics);

	$adjustBoards = array();

	// Find out how many posts we are deleting.
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($adjustBoards[$row['id_board']]['num_posts']))
		{
			$adjustBoards[$row['id_board']] = array(
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
				'id_board' => $row['id_board']
			);
		}
		// Posts = (num_replies + 1) for each approved topic.
		$adjustBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
		$adjustBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

		// Add the topics to the right type.
		if ($row['approved'])
			$adjustBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
		else
			$adjustBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
	}
	$smcFunc['db_free_result']($request);

	if ($updateBoardCount)
	{
		// Decrease the posts/topics...
		foreach ($adjustBoards as $stats)
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

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
		}
	}
	// Remove Polls.
	$request = $smcFunc['db_query']('', '
		SELECT id_poll
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
			AND id_poll > {int:no_poll}
		LIMIT {int:limit}',
		array(
			'no_poll' => 0,
			'topics' => $topics,
			'limit' => count($topics),
		)
	);
	$polls = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$polls[] = $row['id_poll'];
	$smcFunc['db_free_result']($request);

	if (!empty($polls))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}polls
			WHERE id_poll IN ({array_int:polls})',
			array(
				'polls' => $polls,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}poll_choices
			WHERE id_poll IN ({array_int:polls})',
			array(
				'polls' => $polls,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll IN ({array_int:polls})',
			array(
				'polls' => $polls,
			)
		);
	}

	// Get rid of the attachment, if it exists.
	require_once($sourcedir . '/ManageAttachments.php');
	$attachmentQuery = array(
		'attachment_type' => 0,
		'id_topic' => $topics,
	);
	removeAttachments($attachmentQuery, 'messages');

	// Delete possible search index entries.
	if (!empty($modSettings['search_custom_index_config']))
	{
		$customIndexSettings = $smcFunc['json_decode']($modSettings['search_custom_index_config'], true);

		$words = array();
		$messages = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, body
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$words = array_merge($words, text2words($row['body'], $customIndexSettings['bytes_per_word'], true));
			$messages[] = $row['id_msg'];
		}
		$smcFunc['db_free_result']($request);
		$words = array_unique($words);

		if (!empty($words) && !empty($messages))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg IN ({array_int:message_list})',
				array(
					'word_list' => $words,
					'message_list' => $messages,
				)
			);
	}

	// Delete anything related to the topic.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}calendar
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_search_subjects
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);

	// Maybe there's a mod that wants to delete topic related data of its own
	call_integration_hook('integrate_remove_topics', array($topics));

	// Update the totals...
	updateStats('message');
	updateStats('topic');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	require_once($sourcedir . '/Subs-Post.php');
	$updates = array();
	foreach ($adjustBoards as $stats)
		$updates[] = $stats['id_board'];
	updateLastMessages($updates);
}

/**
 * Remove a specific message (including permission checks).
 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
 * - uses boardurl to determine these two things.
 *
 * @param int $message The message id
 * @param bool $decreasePostCount Whether to decrease users' post counts
 * @return bool Whether the operation succeeded
 */
function removeMessage($message, $decreasePostCount = true)
{
	global $board, $sourcedir, $modSettings, $user_info, $smcFunc;

	if (empty($message) || !is_numeric($message))
		return false;

	$recycle_board = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? (int) $modSettings['recycle_board'] : 0;

	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_member, m.icon, m.poster_time, m.subject,' . (empty($modSettings['search_custom_index_config']) ? '' : ' m.body,') . '
			m.approved, t.id_topic, t.id_first_msg, t.id_last_msg, t.num_replies, t.id_board,
			t.id_member_started AS id_member_poster,
			b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE m.id_msg = {int:id_msg}
		LIMIT 1',
		array(
			'id_msg' => $message,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		return false;

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($board) || $row['id_board'] != $board)
	{
		$delete_any = boardsAllowedTo('delete_any');

		if (!in_array(0, $delete_any) && !in_array($row['id_board'], $delete_any))
		{
			$delete_own = boardsAllowedTo('delete_own');
			$delete_own = in_array(0, $delete_own) || in_array($row['id_board'], $delete_own);
			$delete_replies = boardsAllowedTo('delete_replies');
			$delete_replies = in_array(0, $delete_replies) || in_array($row['id_board'], $delete_replies);

			if ($row['id_member'] == $user_info['id'])
			{
				if (!$delete_own)
				{
					if ($row['id_member_poster'] == $user_info['id'])
					{
						if (!$delete_replies)
							fatal_lang_error('cannot_delete_replies', 'permission');
					}
					else
						fatal_lang_error('cannot_delete_own', 'permission');
				}
				elseif (($row['id_member_poster'] != $user_info['id'] || !$delete_replies) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
			}
			elseif ($row['id_member_poster'] == $user_info['id'])
			{
				if (!$delete_replies)
					fatal_lang_error('cannot_delete_replies', 'permission');
			}
			else
				fatal_lang_error('cannot_delete_any', 'permission');
		}

		// Can't delete an unapproved message, if you can't see it!
		if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !(in_array(0, $delete_any) || in_array($row['id_board'], $delete_any)))
		{
			$approve_posts = boardsAllowedTo('approve_posts');
			if (!in_array(0, $approve_posts) && !in_array($row['id_board'], $approve_posts))
				return false;
		}
	}
	else
	{
		// Check permissions to delete this message.
		if ($row['id_member'] == $user_info['id'])
		{
			if (!allowedTo('delete_own'))
			{
				if ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
					isAllowedTo('delete_replies');
				elseif (!allowedTo('delete_any'))
					isAllowedTo('delete_own');
			}
			elseif (!allowedTo('delete_any') && ($row['id_member_poster'] != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
		}
		elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
			isAllowedTo('delete_replies');
		else
			isAllowedTo('delete_any');

		if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !allowedTo('delete_own'))
			isAllowedTo('approve_posts');
	}

	// Delete the *whole* topic, but only if the topic consists of one message.
	if ($row['id_first_msg'] == $message)
	{
		if (empty($board) || $row['id_board'] != $board)
		{
			$remove_any = boardsAllowedTo('remove_any');
			$remove_any = in_array(0, $remove_any) || in_array($row['id_board'], $remove_any);
			if (!$remove_any)
			{
				$remove_own = boardsAllowedTo('remove_own');
				$remove_own = in_array(0, $remove_own) || in_array($row['id_board'], $remove_own);
			}

			if ($row['id_member'] != $user_info['id'] && !$remove_any)
				fatal_lang_error('cannot_remove_any', 'permission');
			elseif (!$remove_any && !$remove_own)
				fatal_lang_error('cannot_remove_own', 'permission');
		}
		else
		{
			// Check permissions to delete a whole topic.
			if ($row['id_member'] != $user_info['id'])
				isAllowedTo('remove_any');
			elseif (!allowedTo('remove_any'))
				isAllowedTo('remove_own');
		}

		// ...if there is only one post.
		if (!empty($row['num_replies']))
			fatal_lang_error('delFirstPost', false);

		removeTopics($row['id_topic']);
		return true;
	}

	// Deleting a recycled message can not lower anyone's post count.
	if (!empty($recycle_board) && $row['id_board'] == $recycle_board)
		$decreasePostCount = false;

	// This is the last post, update the last post on the board.
	if ($row['id_last_msg'] == $message)
	{
		// Find the last message, set it, and decrease the post count.
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:id_topic}
				AND id_msg != {int:id_msg}
			ORDER BY ' . ($modSettings['postmod_active'] ? 'approved DESC, ' : '') . 'id_msg DESC
			LIMIT 1',
			array(
				'id_topic' => $row['id_topic'],
				'id_msg' => $message,
			)
		);
		$row2 = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET
				id_last_msg = {int:id_last_msg},
				id_member_updated = {int:id_member_updated}' . (!$modSettings['postmod_active'] || $row['approved'] ? ',
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : ',
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'id_last_msg' => $row2['id_msg'],
				'id_member_updated' => $row2['id_member'],
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			)
		);
	}
	// Only decrease post counts.
	else
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET ' . ($row['approved'] ? '
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : '
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			)
		);

	// Default recycle to false.
	$recycle = false;

	// If recycle topics has been set, make a copy of this message in the recycle board.
	// Make sure we're not recycling messages that are already on the recycle board.
	if (!empty($modSettings['recycle_enable']) && $row['id_board'] != $modSettings['recycle_board'] && $row['icon'] != 'recycled')
	{
		// Check if the recycle board exists and if so get the read status.
		$request = $smcFunc['db_query']('', '
			SELECT (COALESCE(lb.id_msg, 0) >= b.id_msg_updated) AS is_seen, id_last_msg
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
			WHERE b.id_board = {int:recycle_board}',
			array(
				'current_member' => $user_info['id'],
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('recycle_no_valid_board');
		list ($isRead, $last_board_msg) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Is there an existing topic in the recycle board to group this post with?
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_previous_topic = {int:id_previous_topic}
				AND id_board = {int:recycle_board}',
			array(
				'id_previous_topic' => $row['id_topic'],
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		list ($id_recycle_topic, $first_topic_msg, $last_topic_msg) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Insert a new topic in the recycle board if $id_recycle_topic is empty.
		if (empty($id_recycle_topic))
			$id_topic = $smcFunc['db_insert']('',
				'{db_prefix}topics',
				array(
					'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
					'id_last_msg' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int', 'id_previous_topic' => 'int',
				),
				array(
					$modSettings['recycle_board'], $row['id_member'], $row['id_member'], $message,
					$message, 0, 1, $row['id_topic'],
				),
				array('id_topic'),
				1
			);

		// Capture the ID of the new topic...
		$topicID = empty($id_recycle_topic) ? $id_topic : $id_recycle_topic;

		// If the topic creation went successful, move the message.
		if ($topicID > 0)
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET
					id_topic = {int:id_topic},
					id_board = {int:recycle_board},
					approved = {int:is_approved}
				WHERE id_msg = {int:id_msg}',
				array(
					'id_topic' => $topicID,
					'recycle_board' => $modSettings['recycle_board'],
					'id_msg' => $message,
					'is_approved' => 1,
				)
			);

			// Take any reported posts with us...
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_reported
				SET
					id_topic = {int:id_topic},
					id_board = {int:recycle_board}
				WHERE id_msg = {int:id_msg}',
				array(
					'id_topic' => $topicID,
					'recycle_board' => $modSettings['recycle_board'],
					'id_msg' => $message,
				)
			);

			// Mark recycled topic as read.
			if (!$user_info['is_guest'])
				$smcFunc['db_insert']('replace',
					'{db_prefix}log_topics',
					array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'),
					array($topicID, $user_info['id'], $modSettings['maxMsgID'], 0),
					array('id_topic', 'id_member')
				);

			// Mark recycle board as seen, if it was marked as seen before.
			if (!empty($isRead) && !$user_info['is_guest'])
				$smcFunc['db_insert']('replace',
					'{db_prefix}log_boards',
					array('id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
					array($modSettings['recycle_board'], $user_info['id'], $modSettings['maxMsgID']),
					array('id_board', 'id_member')
				);

			// Add one topic and post to the recycle bin board.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET
					num_topics = num_topics + {int:num_topics_inc},
					num_posts = num_posts + 1' .
						($message > $last_board_msg ? ', id_last_msg = {int:id_merged_msg}' : '') . '
				WHERE id_board = {int:recycle_board}',
				array(
					'num_topics_inc' => empty($id_recycle_topic) ? 1 : 0,
					'recycle_board' => $modSettings['recycle_board'],
					'id_merged_msg' => $message,
				)
			);

			// Lets increase the num_replies, and the first/last message ID as appropriate.
			if (!empty($id_recycle_topic))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET num_replies = num_replies + 1' .
						($message > $last_topic_msg ? ', id_last_msg = {int:id_merged_msg}' : '') .
						($message < $first_topic_msg ? ', id_first_msg = {int:id_merged_msg}' : '') . '
					WHERE id_topic = {int:id_recycle_topic}',
					array(
						'id_recycle_topic' => $id_recycle_topic,
						'id_merged_msg' => $message,
					)
				);

			// Make sure this message isn't getting deleted later on.
			$recycle = true;

			// Make sure we update the search subject index.
			updateStats('subject', $topicID, $row['subject']);
		}

		// If it wasn't approved don't keep it in the queue.
		if (!$row['approved'])
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg = {int:id_msg}
					AND id_attach = {int:id_attach}',
				array(
					'id_msg' => $message,
					'id_attach' => 0,
				)
			);
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET ' . ($row['approved'] ? '
			num_posts = CASE WHEN num_posts = {int:no_posts} THEN 0 ELSE num_posts - 1 END' : '
			unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
		WHERE id_board = {int:id_board}',
		array(
			'no_posts' => 0,
			'no_unapproved' => 0,
			'id_board' => $row['id_board'],
		)
	);

	// If the poster was registered and the board this message was on incremented
	// the member's posts when it was posted, decrease his or her post count.
	if (!empty($row['id_member']) && $decreasePostCount && empty($row['count_posts']) && $row['approved'])
		updateMemberData($row['id_member'], array('posts' => '-'));

	// Only remove posts if they're not recycled.
	if (!$recycle)
	{
		// Callback for search APIs to do their thing
		require_once($sourcedir . '/Search.php');
		$searchAPI = findSearchAPI();
		if ($searchAPI->supportsMethod('postRemoved'))
			$searchAPI->postRemoved($message);

		// Remove the message!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $message,
			)
		);

		if (!empty($modSettings['search_custom_index_config']))
		{
			$customIndexSettings = $smcFunc['json_decode']($modSettings['search_custom_index_config'], true);
			$words = text2words($row['body'], $customIndexSettings['bytes_per_word'], true);
			if (!empty($words))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_search_words
					WHERE id_word IN ({array_int:word_list})
						AND id_msg = {int:id_msg}',
					array(
						'word_list' => $words,
						'id_msg' => $message,
					)
				);
		}

		// Delete attachment(s) if they exist.
		require_once($sourcedir . '/ManageAttachments.php');
		$attachmentQuery = array(
			'attachment_type' => 0,
			'id_msg' => $message,
		);
		removeAttachments($attachmentQuery);
	}

	// Allow mods to remove message related data of their own (likes, maybe?)
	call_integration_hook('integrate_remove_message', array($message, $row, $recycle));

	// Update the pesky statistics.
	updateStats('message');
	updateStats('topic');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// And now to update the last message of each board we messed with.
	require_once($sourcedir . '/Subs-Post.php');
	if ($recycle)
		updateLastMessages(array($row['id_board'], $modSettings['recycle_board']));
	else
		updateLastMessages($row['id_board']);

	// Close any moderation reports for this message.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET closed = {int:is_closed}
		WHERE id_msg = {int:id_msg}',
		array(
			'is_closed' => 1,
			'id_msg' => $message,
		)
	);
	if ($smcFunc['db_affected_rows']() != 0)
	{
		require_once($sourcedir . '/ModerationCenter.php');
		updateSettings(array('last_mod_report_action' => time()));
		recountOpenReports('posts');
	}

	return false;
}

/**
 * Move back a topic from the recycle board to its original board.
 */
function RestoreTopic()
{
	global $smcFunc, $modSettings, $sourcedir;

	// Check session.
	checkSession('get');

	// Is recycled board enabled?
	if (empty($modSettings['recycle_enable']))
		fatal_lang_error('restored_disabled', 'critical');

	// Can we be in here?
	isAllowedTo('move_any', $modSettings['recycle_board']);

	// We need this file.
	require_once($sourcedir . '/MoveTopic.php');

	$unfound_messages = array();
	$topics_to_restore = array();

	// Restoring messages?
	if (!empty($_REQUEST['msgs']))
	{
		$msgs = explode(',', $_REQUEST['msgs']);
		foreach ($msgs as $k => $msg)
			$msgs[$k] = (int) $msg;

		// Get the id_previous_board and id_previous_topic.
		$request = $smcFunc['db_query']('', '
			SELECT m.id_topic, m.id_msg, m.id_board, m.subject, m.id_member, t.id_previous_board, t.id_previous_topic,
				t.id_first_msg, b.count_posts, COALESCE(pt.id_board, 0) AS possible_prev_board
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}topics AS pt ON (pt.id_topic = t.id_previous_topic)
			WHERE m.id_msg IN ({array_int:messages})',
			array(
				'messages' => $msgs,
			)
		);

		$actioned_messages = array();
		$previous_topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Restoring the first post means topic.
			if ($row['id_msg'] == $row['id_first_msg'] && $row['id_previous_topic'] == $row['id_topic'])
			{
				$topics_to_restore[] = $row['id_topic'];
				continue;
			}
			// Don't know where it's going?
			if (empty($row['id_previous_topic']))
			{
				$unfound_messages[$row['id_msg']] = $row['subject'];
				continue;
			}

			$previous_topics[] = $row['id_previous_topic'];
			if (empty($actioned_messages[$row['id_previous_topic']]))
				$actioned_messages[$row['id_previous_topic']] = array(
					'msgs' => array(),
					'count_posts' => $row['count_posts'],
					'subject' => $row['subject'],
					'previous_board' => $row['id_previous_board'],
					'possible_prev_board' => $row['possible_prev_board'],
					'current_topic' => $row['id_topic'],
					'current_board' => $row['id_board'],
					'members' => array(),
				);

			$actioned_messages[$row['id_previous_topic']]['msgs'][$row['id_msg']] = $row['subject'];
			if ($row['id_member'])
				$actioned_messages[$row['id_previous_topic']]['members'][] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		// Check for topics we are going to fully restore.
		foreach ($actioned_messages as $topic => $data)
			if (in_array($topic, $topics_to_restore))
				unset($actioned_messages[$topic]);

		// Load any previous topics to check they exist.
		if (!empty($previous_topics))
		{
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic, t.id_board, m.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_topic IN ({array_int:previous_topics})',
				array(
					'previous_topics' => $previous_topics,
				)
			);
			$previous_topics = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$previous_topics[$row['id_topic']] = array(
					'board' => $row['id_board'],
					'subject' => $row['subject'],
				);
			$smcFunc['db_free_result']($request);
		}

		// Restore each topic.
		$messages = array();
		foreach ($actioned_messages as $topic => $data)
		{
			// If we have topics we are going to restore the whole lot ignore them.
			if (in_array($topic, $topics_to_restore))
			{
				unset($actioned_messages[$topic]);
				continue;
			}

			// Move the posts back then!
			if (isset($previous_topics[$topic]))
			{
				mergePosts(array_keys($data['msgs']), $data['current_topic'], $topic);
				// Log em.
				logAction('restore_posts', array('topic' => $topic, 'subject' => $previous_topics[$topic]['subject'], 'board' => empty($data['previous_board']) ? $data['possible_prev_board'] : $data['previous_board']));
				$messages = array_merge(array_keys($data['msgs']), $messages);
			}
			else
			{
				foreach ($data['msgs'] as $msg)
					$unfound_messages[$msg['id']] = $msg['subject'];
			}
		}
	}

	// Now any topics?
	if (!empty($_REQUEST['topics']))
	{
		$topics = explode(',', $_REQUEST['topics']);
		foreach ($topics as $id)
			$topics_to_restore[] = (int) $id;
	}

	if (!empty($topics_to_restore))
	{
		// Lets get the data for these topics.
		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, t.id_previous_board, t.id_board, t.id_first_msg, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics_to_restore,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// We can only restore if the previous board is set.
			if (empty($row['id_previous_board']))
			{
				$unfound_messages[$row['id_first_msg']] = $row['subject'];
				continue;
			}

			// Ok we got here so me move them from here to there.
			moveTopics($row['id_topic'], $row['id_previous_board']);

			// Lets see if the board that we are returning to has post count enabled.
			$request2 = $smcFunc['db_query']('', '
				SELECT count_posts
				FROM {db_prefix}boards
				WHERE id_board = {int:board}',
				array(
					'board' => $row['id_previous_board'],
				)
			);
			list ($count_posts) = $smcFunc['db_fetch_row']($request2);
			$smcFunc['db_free_result']($request2);

			if (empty($count_posts))
			{
				// Lets get the members that need their post count restored.
				$request2 = $smcFunc['db_query']('', '
					SELECT id_member, COUNT(id_msg) AS post_count
					FROM {db_prefix}messages
					WHERE id_topic = {int:topic}
						AND approved = {int:is_approved}
					GROUP BY id_member',
					array(
						'topic' => $row['id_topic'],
						'is_approved' => 1,
					)
				);

				while ($member = $smcFunc['db_fetch_assoc']($request2))
					updateMemberData($member['id_member'], array('posts' => 'posts + ' . $member['post_count']));
				$smcFunc['db_free_result']($request2);
			}

			// Log it.
			logAction('restore_topic', array('topic' => $row['id_topic'], 'board' => $row['id_board'], 'board_to' => $row['id_previous_board']));
		}
		$smcFunc['db_free_result']($request);
	}

	// Didn't find some things?
	if (!empty($unfound_messages))
		fatal_lang_error('restore_not_found', false, array(implode('<br>', $unfound_messages)));

	// Just send them to the index if they get here.
	redirectexit();
}

/**
 * Take a load of messages from one place and stick them in a topic
 *
 * @param array $msgs The IDs of the posts to merge
 * @param integer $from_topic The ID of the topic the messages were originally in
 * @param integer $target_topic The ID of the topic the messages are being merged into
 */
function mergePosts($msgs, $from_topic, $target_topic)
{
	global $smcFunc, $sourcedir;

	//!!! This really needs to be rewritten to take a load of messages from ANY topic, it's also inefficient.

	// Is it an array?
	if (!is_array($msgs))
		$msgs = array($msgs);

	// Lets make sure they are int.
	foreach ($msgs as $key => $msg)
		$msgs[$key] = (int) $msg;

	// Get the source information.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_board, t.id_first_msg, t.num_replies, t.unapproved_posts
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_topic = {int:from_topic}',
		array(
			'from_topic' => $from_topic,
		)
	);
	list ($from_board, $from_first_msg, $from_replies, $from_unapproved_posts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Get some target topic and board stats.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_board, t.id_first_msg, t.num_replies, t.unapproved_posts, b.count_posts
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_topic = {int:target_topic}',
		array(
			'target_topic' => $target_topic,
		)
	);
	list ($target_board, $target_first_msg, $target_replies, $target_unapproved_posts, $count_posts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Lets see if the board that we are returning to has post count enabled.
	if (empty($count_posts))
	{
		// Lets get the members that need their post count restored.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:messages})
				AND approved = {int:is_approved}',
			array(
				'messages' => $msgs,
				'is_approved' => 1,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('posts' => '+'));
	}

	// Time to move the messages.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET
			id_topic = {int:target_topic},
			id_board = {int:target_board}
		WHERE id_msg IN({array_int:msgs})',
		array(
			'target_topic' => $target_topic,
			'target_board' => $target_board,
			'msgs' => $msgs,
		)
	);

	// Fix the id_first_msg and id_last_msg for the target topic.
	$target_topic_data = array(
		'num_replies' => 0,
		'unapproved_posts' => 0,
		'id_first_msg' => 9999999999,
	);
	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved
		FROM {db_prefix}messages
		WHERE id_topic = {int:target_topic}
		GROUP BY id_topic, approved
		ORDER BY approved ASC
		LIMIT 2',
		array(
			'target_topic' => $target_topic,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_first_msg'] < $target_topic_data['id_first_msg'])
			$target_topic_data['id_first_msg'] = $row['id_first_msg'];
		$target_topic_data['id_last_msg'] = $row['id_last_msg'];
		if (!$row['approved'])
			$target_topic_data['unapproved_posts'] = $row['message_count'];
		else
			$target_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
	}
	$smcFunc['db_free_result']($request);

	// We have a new post count for the board.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET
			num_posts = num_posts + {int:diff_replies},
			unapproved_posts = unapproved_posts + {int:diff_unapproved_posts}
		WHERE id_board = {int:target_board}',
		array(
			'diff_replies' => $target_topic_data['num_replies'] - $target_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
			'diff_unapproved_posts' => $target_topic_data['unapproved_posts'] - $target_unapproved_posts,
			'target_board' => $target_board,
		)
	);

	// In some cases we merged the only post in a topic so the topic data is left behind in the topic table.
	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}messages
		WHERE id_topic = {int:from_topic}',
		array(
			'from_topic' => $from_topic,
		)
	);

	// Remove the topic if it doesn't have any messages.
	$topic_exists = true;
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		removeTopics($from_topic, false, true);
		$topic_exists = false;
	}
	$smcFunc['db_free_result']($request);

	// Recycled topic.
	if ($topic_exists == true)
	{
		// Fix the id_first_msg and id_last_msg for the source topic.
		$source_topic_data = array(
			'num_replies' => 0,
			'unapproved_posts' => 0,
			'id_first_msg' => 9999999999,
		);
		$request = $smcFunc['db_query']('', '
			SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved, subject
			FROM {db_prefix}messages
			WHERE id_topic = {int:from_topic}
			GROUP BY id_topic, approved
			ORDER BY approved ASC
			LIMIT 2',
			array(
				'from_topic' => $from_topic,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['id_first_msg'] < $source_topic_data['id_first_msg'])
				$source_topic_data['id_first_msg'] = $row['id_first_msg'];
			$source_topic_data['id_last_msg'] = $row['id_last_msg'];
			if (!$row['approved'])
				$source_topic_data['unapproved_posts'] = $row['message_count'];
			else
				$source_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
		}
		$smcFunc['db_free_result']($request);

		// Update the topic details for the source topic.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:from_topic}',
			array(
				'id_first_msg' => $source_topic_data['id_first_msg'],
				'id_last_msg' => $source_topic_data['id_last_msg'],
				'num_replies' => $source_topic_data['num_replies'],
				'unapproved_posts' => $source_topic_data['unapproved_posts'],
				'from_topic' => $from_topic,
			)
		);

		// We have a new post count for the source board.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET
				num_posts = num_posts + {int:diff_replies},
				unapproved_posts = unapproved_posts + {int:diff_unapproved_posts}
			WHERE id_board = {int:from_board}',
			array(
				'diff_replies' => $source_topic_data['num_replies'] - $from_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
				'diff_unapproved_posts' => $source_topic_data['unapproved_posts'] - $from_unapproved_posts,
				'from_board' => $from_board,
			)
		);
	}

	// Finally get around to updating the destination topic, now all indexes etc on the source are fixed.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			num_replies = {int:num_replies},
			unapproved_posts = {int:unapproved_posts}
		WHERE id_topic = {int:target_topic}',
		array(
			'id_first_msg' => $target_topic_data['id_first_msg'],
			'id_last_msg' => $target_topic_data['id_last_msg'],
			'num_replies' => $target_topic_data['num_replies'],
			'unapproved_posts' => $target_topic_data['unapproved_posts'],
			'target_topic' => $target_topic,
		)
	);

	// Need it to update some stats.
	require_once($sourcedir . '/Subs-Post.php');

	// Update stats.
	updateStats('topic');
	updateStats('message');

	// Subject cache?
	$cache_updates = array();
	if ($target_first_msg != $target_topic_data['id_first_msg'])
		$cache_updates[] = $target_topic_data['id_first_msg'];
	if (!empty($source_topic_data['id_first_msg']) && $from_first_msg != $source_topic_data['id_first_msg'])
		$cache_updates[] = $source_topic_data['id_first_msg'];

	if (!empty($cache_updates))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, subject
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:first_messages})',
			array(
				'first_messages' => $cache_updates,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateStats('subject', $row['id_topic'], $row['subject']);
		$smcFunc['db_free_result']($request);
	}

	updateLastMessages(array($from_board, $target_board));
}

/**
 * Try to determine if the topic has already been deleted by another user.
 *
 * @return bool False if it can't be deleted (recycling not enabled or no recycling board set), true if we've confirmed it can be deleted. Dies with an error if it's already been deleted.
 */
function removeDeleteConcurrence()
{
	global $modSettings, $board, $scripturl, $context;

	// No recycle no need to go further
	if (empty($modSettings['recycle_enable']) || empty($modSettings['recycle_board']))
		return false;

	// If it's confirmed go on and delete (from recycle)
	if (isset($_GET['confirm_delete']))
		return true;

	if (empty($board))
		return false;

	if ($modSettings['recycle_board'] != $board)
		return true;
	elseif (isset($_REQUEST['msg']))
		$confirm_url = $scripturl . '?action=deletemsg;confirm_delete;topic=' . $context['current_topic'] . '.0;msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	else
		$confirm_url = $scripturl . '?action=removetopic2;confirm_delete;topic=' . $context['current_topic'] . '.0;' . $context['session_var'] . '=' . $context['session_id'];

	fatal_lang_error('post_already_deleted', false, array($confirm_url));
}

?>