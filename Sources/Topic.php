<?php

/**
 * This file takes care of actions on topics:
 * lock/unlock a topic, sticky/unsticky it,
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
 * Locks a topic... either by way of a moderator or the topic starter.
 * What this does:
 *  - locks a topic, toggles between locked/unlocked/admin locked.
 *  - only admins can unlock topics locked by other admins.
 *  - requires the lock_own or lock_any permission.
 *  - logs the action to the moderator log.
 *  - returns to the topic after it is done.
 *  - it is accessed via ?action=lock.
*/
function LockTopic()
{
	global $topic, $user_info, $sourcedir, $board, $smcFunc;

	// Just quit if there's no topic to lock.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// Get Subs-Post.php for sendNotifications.
	require_once($sourcedir . '/Subs-Post.php');

	// Find out who started the topic - in case User Topic Locking is enabled.
	$request = $smcFunc['db_query']('', '
		SELECT id_member_started, locked
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($starter, $locked) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Can you lock topics here, mister?
	$user_lock = !allowedTo('lock_any');
	if ($user_lock && $starter == $user_info['id'])
		isAllowedTo('lock_own');
	else
		isAllowedTo('lock_any');

	// Locking with high privileges.
	if ($locked == '0' && !$user_lock)
		$locked = '1';
	// Locking with low privileges.
	elseif ($locked == '0')
		$locked = '2';
	// Unlocking - make sure you don't unlock what you can't.
	elseif ($locked == '2' || ($locked == '1' && !$user_lock))
		$locked = '0';
	// You cannot unlock this!
	else
		fatal_lang_error('locked_by_admin', 'user');

	// Actually lock the topic in the database with the new value.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET locked = {int:locked}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'locked' => $locked,
		)
	);

	// If they are allowed a "moderator" permission, log it in the moderator log.
	if (!$user_lock)
		logAction($locked ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $board));
	// Notify people that this topic has been locked?
	sendNotifications($topic, empty($locked) ? 'unlock' : 'lock');

	// Back to the topic!
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . (WIRELESS ? ';moderate' : ''));
}

/**
 * Sticky a topic.
 * Can't be done by topic starters - that would be annoying!
 * What this does:
 *  - stickies a topic - toggles between sticky and normal.
 *  - requires the make_sticky permission.
 *  - adds an entry to the moderator log.
 *  - when done, sends the user back to the topic.
 *  - accessed via ?action=sticky.
 */
function Sticky()
{
	global $topic, $board, $sourcedir, $smcFunc;

	// Make sure the user can sticky it, and they are stickying *something*.
	isAllowedTo('make_sticky');

	// You can't sticky a board or something!
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// We need Subs-Post.php for the sendNotifications() function.
	require_once($sourcedir . '/Subs-Post.php');

	// Is this topic already stickied, or no?
	$request = $smcFunc['db_query']('', '
		SELECT is_sticky
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($is_sticky) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Toggle the sticky value.... pretty simple ;).
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET is_sticky = {int:is_sticky}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'is_sticky' => empty($is_sticky) ? 1 : 0,
		)
	);

	// Log this sticky action - always a moderator thing.
	logAction(empty($is_sticky) ? 'sticky' : 'unsticky', array('topic' => $topic, 'board' => $board));
	// Notify people that this topic has been stickied?
	if (empty($is_sticky))
		sendNotifications($topic, 'sticky');

	// Take them back to the now stickied topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . (WIRELESS ? ';moderate' : ''));
}

?>