<?php

/**
 * This file contains the functions for voting, locking, removing and
 * editing polls. Note that that posting polls is done in Post.php.
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

/**
 * Allow the user to vote.
 * It is called to register a vote in a poll.
 * Must be called with a topic and option specified.
 * Requires the poll_vote permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=vote.
 *
 * @uses Post language file.
 */
function Vote()
{
	global $topic, $user_info, $smcFunc, $sourcedir, $modSettings;

	// Make sure you can vote.
	isAllowedTo('poll_vote');

	loadLanguage('Post');

	// Check if they have already voted, or voting is locked.
	$request = $smcFunc['db_query']('', '
		SELECT COALESCE(lp.id_choice, -1) AS selected, p.voting_locked, p.id_poll, p.expire_time, p.max_votes, p.change_vote,
			p.guest_vote, p.reset_poll, p.num_guest_voters
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			LEFT JOIN {db_prefix}log_polls AS lp ON (p.id_poll = lp.id_poll AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'not_guest' => 0,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('poll_error', false);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// If this is a guest can they vote?
	if ($user_info['is_guest'])
	{
		// Guest voting disabled?
		if (!$row['guest_vote'])
			fatal_lang_error('guest_vote_disabled');
		// Guest already voted?
		elseif (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . $row['id_poll'] . ',') !== false)
		{
			// ;id,timestamp,[vote,vote...]; etc
			$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);
			// Find the poll we're after.
			foreach ($guestinfo as $i => $guestvoted)
			{
				$guestvoted = explode(',', $guestvoted);
				if ($guestvoted[0] == $row['id_poll'])
					break;
			}
			// Has the poll been reset since guest voted?
			if ($row['reset_poll'] > $guestvoted[1])
			{
				// Remove the poll info from the cookie to allow guest to vote again
				unset($guestinfo[$i]);
				if (!empty($guestinfo))
					$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
				else
					unset($_COOKIE['guest_poll_vote']);
			}
			else
				fatal_lang_error('poll_error', false);
			unset($guestinfo, $guestvoted, $i);
		}
	}

	// Is voting locked or has it expired?
	if (!empty($row['voting_locked']) || (!empty($row['expire_time']) && time() > $row['expire_time']))
		fatal_lang_error('poll_error', false);

	// If they have already voted and aren't allowed to change their vote - hence they are outta here!
	if (!$user_info['is_guest'] && $row['selected'] != -1 && empty($row['change_vote']))
		fatal_lang_error('poll_error', false);
	// Otherwise if they can change their vote yet they haven't sent any options... remove their vote and redirect.
	elseif (!empty($row['change_vote']) && !$user_info['is_guest'] && empty($_POST['options']))
	{
		checkSession('request');
		$pollOptions = array();

		// Find out what they voted for before.
		$request = $smcFunc['db_query']('', '
			SELECT id_choice
			FROM {db_prefix}log_polls
			WHERE id_member = {int:current_member}
				AND id_poll = {int:id_poll}',
			array(
				'current_member' => $user_info['id'],
				'id_poll' => $row['id_poll'],
			)
		);
		while ($choice = $smcFunc['db_fetch_row']($request))
			$pollOptions[] = $choice[0];
		$smcFunc['db_free_result']($request);

		// Just skip it if they had voted for nothing before.
		if (!empty($pollOptions))
		{
			// Update the poll totals.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}poll_choices
				SET votes = votes - 1
				WHERE id_poll = {int:id_poll}
					AND id_choice IN ({array_int:poll_options})
					AND votes > {int:votes}',
				array(
					'poll_options' => $pollOptions,
					'id_poll' => $row['id_poll'],
					'votes' => 0,
				)
			);

			// Delete off the log.
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_polls
				WHERE id_member = {int:current_member}
					AND id_poll = {int:id_poll}',
				array(
					'current_member' => $user_info['id'],
					'id_poll' => $row['id_poll'],
				)
			);
		}

		// Redirect back to the topic so the user can vote again!
		if (empty($_POST['options']))
			redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	checkSession('request');

	// Make sure the option(s) are valid.
	if (empty($_POST['options']))
		fatal_lang_error('didnt_select_vote', false);

	// Too many options checked!
	if (count($_REQUEST['options']) > $row['max_votes'])
		fatal_lang_error('poll_too_many_votes', false, array($row['max_votes']));

	$pollOptions = array();
	$inserts = array();
	foreach ($_REQUEST['options'] as $id)
	{
		$id = (int) $id;

		$pollOptions[] = $id;
		$inserts[] = array($row['id_poll'], $user_info['id'], $id);
	}

	// Add their vote to the tally.
	$smcFunc['db_insert']('insert',
		'{db_prefix}log_polls',
		array('id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'),
		$inserts,
		array('id_poll', 'id_member', 'id_choice')
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}poll_choices
		SET votes = votes + 1
		WHERE id_poll = {int:id_poll}
			AND id_choice IN ({array_int:poll_options})',
		array(
			'poll_options' => $pollOptions,
			'id_poll' => $row['id_poll'],
		)
	);

	// If it's a guest don't let them vote again.
	if ($user_info['is_guest'] && count($pollOptions) > 0)
	{
		// Time is stored in case the poll is reset later, plus what they voted for.
		$_COOKIE['guest_poll_vote'] = empty($_COOKIE['guest_poll_vote']) ? '' : $_COOKIE['guest_poll_vote'];
		// ;id,timestamp,[vote,vote...]; etc
		$_COOKIE['guest_poll_vote'] .= ';' . $row['id_poll'] . ',' . time() . ',' . implode(',', $pollOptions);

		// Increase num guest voters count by 1
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}polls
			SET num_guest_voters = num_guest_voters + 1
			WHERE id_poll = {int:id_poll}',
			array(
				'id_poll' => $row['id_poll'],
			)
		);

		require_once($sourcedir . '/Subs-Auth.php');
		$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
		smf_setcookie('guest_poll_vote', $_COOKIE['guest_poll_vote'], time() + 2500000, $cookie_url[1], $cookie_url[0], false, false);
	}

	// Maybe let a social networking mod log this, or something?
	call_integration_hook('integrate_poll_vote', array(&$row['id_poll'], &$pollOptions));

	// Return to the post...
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * Lock the voting for a poll.
 * Must be called with a topic specified in the URL.
 * An admin always has over riding permission to lock a poll.
 * If not an admin must have poll_lock_any permission, otherwise must
 * be poll starter with poll_lock_own permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=lockvoting.
 */
function LockVoting()
{
	global $topic, $user_info, $smcFunc;

	checkSession('get');

	// Get the poll starter, ID, and whether or not it is locked.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, t.id_poll, p.voting_locked
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($memberID, $pollID, $voting_locked) = $smcFunc['db_fetch_row']($request);

	// If the user _can_ modify the poll....
	if (!allowedTo('poll_lock_any'))
		isAllowedTo('poll_lock_' . ($user_info['id'] == $memberID ? 'own' : 'any'));

	// It's been locked by a non-moderator.
	if ($voting_locked == '1')
		$voting_locked = '0';
	// Locked by a moderator, and this is a moderator.
	elseif ($voting_locked == '2' && allowedTo('moderate_board'))
		$voting_locked = '0';
	// Sorry, a moderator locked it.
	elseif ($voting_locked == '2' && !allowedTo('moderate_board'))
		fatal_lang_error('locked_by_admin', 'user');
	// A moderator *is* locking it.
	elseif ($voting_locked == '0' && allowedTo('moderate_board'))
		$voting_locked = '2';
	// Well, it's gonna be locked one way or another otherwise...
	else
		$voting_locked = '1';

	// Lock!  *Poof* - no one can vote.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}polls
		SET voting_locked = {int:voting_locked}
		WHERE id_poll = {int:id_poll}',
		array(
			'voting_locked' => $voting_locked,
			'id_poll' => $pollID,
		)
	);

	logAction(($voting_locked ? '' : 'un') . 'lock_poll', array('topic' => $topic));

	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * Display screen for editing or adding a poll.
 * Must be called with a topic specified in the URL.
 * If the user is adding a poll to a topic, must contain the variable
 * 'add' in the url.
 * User must have poll_edit_any/poll_add_any permission for the
 * relevant action, otherwise must be poll starter with poll_edit_own
 * permission for editing, or be topic starter with poll_add_any permission for adding.
 * Accessed via ?action=editpoll.
 *
 * @uses Post language file.
 * @uses Poll template, main sub-template.
 */
function EditPoll()
{
	global $txt, $user_info, $context, $topic, $board, $smcFunc, $sourcedir, $scripturl;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	loadLanguage('Post');
	loadTemplate('Poll');

	$context['start'] = (int) $_REQUEST['start'];
	$context['is_edit'] = isset($_REQUEST['add']) ? 0 : 1;

	// Check if a poll currently exists on this topic, and get the id, question and starter.
	$request = $smcFunc['db_query']('', '
		SELECT
			t.id_member_started, p.id_poll, p.question, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
			m.subject, p.guest_vote, p.id_member AS poll_starter
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);

	// Assume the the topic exists, right?
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board');

	// Get the poll information.
	$pollinfo = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// If we are adding a new poll - make sure that there isn't already a poll there.
	if (!$context['is_edit'] && !empty($pollinfo['id_poll']))
		fatal_lang_error('poll_already_exists');
	// Otherwise, if we're editing it, it does exist I assume?
	elseif ($context['is_edit'] && empty($pollinfo['id_poll']))
		fatal_lang_error('poll_not_found');

	// Can you do this?
	if ($context['is_edit'] && !allowedTo('poll_edit_any'))
		isAllowedTo('poll_edit_' . ($user_info['id'] == $pollinfo['id_member_started'] || ($pollinfo['poll_starter'] != 0 && $user_info['id'] == $pollinfo['poll_starter']) ? 'own' : 'any'));
	elseif (!$context['is_edit'] && !allowedTo('poll_add_any'))
		isAllowedTo('poll_add_' . ($user_info['id'] == $pollinfo['id_member_started'] ? 'own' : 'any'));
	$context['can_moderate_poll'] = isset($_REQUEST['add']) ? true : allowedTo('poll_edit_' . ($user_info['id'] == $pollinfo['id_member_started'] || ($pollinfo['poll_starter'] != 0 && $user_info['id'] == $pollinfo['poll_starter']) ? 'own' : 'any'));

	// Do we enable guest voting?
	require_once($sourcedir . '/Subs-Members.php');
	$groupsAllowedVote = groupsAllowedTo('poll_vote', $board);

	// Want to make sure before you actually submit?  Must be a lot of options, or something.
	if (isset($_POST['preview']))
	{
		$question = $smcFunc['htmlspecialchars']($_POST['question']);

		// Basic theme info...
		$context['poll'] = array(
			'id' => $pollinfo['id_poll'],
			'question' => $question,
			'hide_results' => empty($_POST['poll_hide']) ? 0 : $_POST['poll_hide'],
			'change_vote' => isset($_POST['poll_change_vote']),
			'guest_vote' => isset($_POST['poll_guest_vote']),
			'guest_vote_allowed' => in_array(-1, $groupsAllowedVote['allowed']),
			'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
		);

		// Start at number one with no last id to speak of.
		$number = 1;
		$last_id = 0;

		// Get all the choices - if this is an edit.
		if ($context['is_edit'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT label, votes, id_choice
				FROM {db_prefix}poll_choices
				WHERE id_poll = {int:id_poll}',
				array(
					'id_poll' => $pollinfo['id_poll'],
				)
			);
			$context['choices'] = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Get the highest id so we can add more without reusing.
				if ($row['id_choice'] >= $last_id)
					$last_id = $row['id_choice'] + 1;

				// They cleared this by either omitting it or emptying it.
				if (!isset($_POST['options'][$row['id_choice']]) || $_POST['options'][$row['id_choice']] == '')
					continue;

				censorText($row['label']);

				// Add the choice!
				$context['choices'][$row['id_choice']] = array(
					'id' => $row['id_choice'],
					'number' => $number++,
					'votes' => $row['votes'],
					'label' => $row['label'],
					'is_last' => false
				);
			}
			$smcFunc['db_free_result']($request);
		}

		// Work out how many options we have, so we get the 'is_last' field right...
		$totalPostOptions = 0;
		foreach ($_POST['options'] as $id => $label)
			if ($label != '')
				$totalPostOptions++;

		$count = 1;
		// If an option exists, update it.  If it is new, add it - but don't reuse ids!
		foreach ($_POST['options'] as $id => $label)
		{
			$label = $smcFunc['htmlspecialchars']($label);
			censorText($label);

			if (isset($context['choices'][$id]))
				$context['choices'][$id]['label'] = $label;
			elseif ($label != '')
				$context['choices'][] = array(
					'id' => $last_id++,
					'number' => $number++,
					'label' => $label,
					'votes' => -1,
					'is_last' => $count++ == $totalPostOptions && $totalPostOptions > 1 ? true : false,
				);
		}

		// Make sure we have two choices for sure!
		if ($totalPostOptions < 2)
		{
			// Need two?
			if ($totalPostOptions == 0)
				$context['choices'][] = array(
					'id' => $last_id++,
					'number' => $number++,
					'label' => '',
					'votes' => -1,
					'is_last' => false
				);
			$poll_errors[] = 'poll_few';
		}

		// Always show one extra box...
		$context['choices'][] = array(
			'id' => $last_id++,
			'number' => $number++,
			'label' => '',
			'votes' => -1,
			'is_last' => true
		);

		$context['last_choice_id'] = $last_id;

		if ($context['can_moderate_poll'])
			$context['poll']['expiration'] = $_POST['poll_expire'];

		// Check the question/option count for errors.
		if (trim($_POST['question']) == '' && empty($context['poll_error']))
			$poll_errors[] = 'no_question';

		// No check is needed, since nothing is really posted.
		checkSubmitOnce('free');

		// Take a check for any errors... assuming we haven't already done so!
		if (!empty($poll_errors) && empty($context['poll_error']))
		{
			loadLanguage('Errors');

			$context['poll_error'] = array('messages' => array());
			foreach ($poll_errors as $poll_error)
			{
				$context['poll_error'][$poll_error] = true;
				$context['poll_error']['messages'][] = $txt['error_' . $poll_error];
			}
		}
	}
	else
	{
		// Basic theme info...
		$context['poll'] = array(
			'id' => $pollinfo['id_poll'],
			'question' => $pollinfo['question'],
			'hide_results' => $pollinfo['hide_results'],
			'max_votes' => $pollinfo['max_votes'],
			'change_vote' => !empty($pollinfo['change_vote']),
			'guest_vote' => !empty($pollinfo['guest_vote']),
			'guest_vote_allowed' => in_array(-1, $groupsAllowedVote['allowed']),
		);

		// Poll expiration time?
		$context['poll']['expiration'] = empty($pollinfo['expire_time']) || !$context['can_moderate_poll'] ? '' : ceil($pollinfo['expire_time'] <= time() ? -1 : ($pollinfo['expire_time'] - time()) / (3600 * 24));

		// Get all the choices - if this is an edit.
		if ($context['is_edit'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT label, votes, id_choice
				FROM {db_prefix}poll_choices
				WHERE id_poll = {int:id_poll}',
				array(
					'id_poll' => $pollinfo['id_poll'],
				)
			);
			$context['choices'] = array();
			$number = 1;
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				censorText($row['label']);

				$context['choices'][$row['id_choice']] = array(
					'id' => $row['id_choice'],
					'number' => $number++,
					'votes' => $row['votes'],
					'label' => $row['label'],
					'is_last' => false
				);
			}
			$smcFunc['db_free_result']($request);

			$last_id = max(array_keys($context['choices'])) + 1;

			// Add an extra choice...
			$context['choices'][] = array(
				'id' => $last_id,
				'number' => $number,
				'votes' => -1,
				'label' => '',
				'is_last' => true
			);
			$context['last_choice_id'] = $last_id;
		}
		// New poll?
		else
		{
			// Setup the default poll options.
			$context['poll'] = array(
				'id' => 0,
				'question' => '',
				'hide_results' => 0,
				'max_votes' => 1,
				'change_vote' => 0,
				'guest_vote' => 0,
				'guest_vote_allowed' => in_array(-1, $groupsAllowedVote['allowed']),
				'expiration' => '',
			);

			// Make all five poll choices empty.
			$context['choices'] = array(
				array('id' => 0, 'number' => 1, 'votes' => -1, 'label' => '', 'is_last' => false),
				array('id' => 1, 'number' => 2, 'votes' => -1, 'label' => '', 'is_last' => false),
				array('id' => 2, 'number' => 3, 'votes' => -1, 'label' => '', 'is_last' => false),
				array('id' => 3, 'number' => 4, 'votes' => -1, 'label' => '', 'is_last' => false),
				array('id' => 4, 'number' => 5, 'votes' => -1, 'label' => '', 'is_last' => true)
			);
			$context['last_choice_id'] = 4;
		}
	}
	$context['page_title'] = $context['is_edit'] ? $txt['poll_edit'] : $txt['add_poll'];

	// Build the link tree.
	censorText($pollinfo['subject']);
	$context['linktree'][] = array(
		'url' => $scripturl . '?topic=' . $topic . '.0',
		'name' => $pollinfo['subject'],
	);
	$context['linktree'][] = array(
		'name' => $context['page_title'],
	);

	// Register this form in the session variables.
	checkSubmitOnce('register');
}

/**
 * Update the settings for a poll, or add a new one.
 * Must be called with a topic specified in the URL.
 * The user must have poll_edit_any/poll_add_any permission
 * for the relevant action. Otherwise they must be poll starter
 * with poll_edit_own permission for editing, or be topic starter
 * with poll_add_any permission for adding.
 * In the case of an error, this function will redirect back to
 * EditPoll and display the relevant error message.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=editpoll2.
 */
function EditPoll2()
{
	global $txt, $topic, $board, $context;
	global $user_info, $smcFunc, $sourcedir;

	// Sneaking off, are we?
	if (empty($_POST))
		redirectexit('action=editpoll;topic=' . $topic . '.0');

	if (checkSession('post', '', false) != '')
		$poll_errors[] = 'session_timeout';

	if (isset($_POST['preview']))
		return EditPoll();

	// HACKERS (!!) can't edit :P.
	if (empty($topic))
		fatal_lang_error('no_access', false);

	// Is this a new poll, or editing an existing?
	$isEdit = isset($_REQUEST['add']) ? 0 : 1;

	// Get the starter and the poll's ID - if it's an edit.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, t.id_poll, p.id_member AS poll_starter, p.expire_time
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board');
	$bcinfo = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Check their adding/editing is valid.
	if (!$isEdit && !empty($bcinfo['id_poll']))
		fatal_lang_error('poll_already_exists');
	// Are we editing a poll which doesn't exist?
	elseif ($isEdit && empty($bcinfo['id_poll']))
		fatal_lang_error('poll_not_found');

	// Check if they have the power to add or edit the poll.
	if ($isEdit && !allowedTo('poll_edit_any'))
		isAllowedTo('poll_edit_' . ($user_info['id'] == $bcinfo['id_member_started'] || ($bcinfo['poll_starter'] != 0 && $user_info['id'] == $bcinfo['poll_starter']) ? 'own' : 'any'));
	elseif (!$isEdit && !allowedTo('poll_add_any'))
		isAllowedTo('poll_add_' . ($user_info['id'] == $bcinfo['id_member_started'] ? 'own' : 'any'));

	$optionCount = 0;
	$idCount = 0;
	// Ensure the user is leaving a valid amount of options - there must be at least two.
	foreach ($_POST['options'] as $k => $option)
	{
		if (trim($option) != '')
		{
			$optionCount++;
			$idCount = max($idCount, $k);
		}
	}
	if ($optionCount < 2)
		$poll_errors[] = 'poll_few';
	elseif ($optionCount > 256 || $idCount > 255)
		$poll_errors[] = 'poll_many';

	// Also - ensure they are not removing the question.
	if (trim($_POST['question']) == '')
		$poll_errors[] = 'no_question';

	// Got any errors to report?
	if (!empty($poll_errors))
	{
		loadLanguage('Errors');
		// Previewing.
		$_POST['preview'] = true;

		$context['poll_error'] = array('messages' => array());
		foreach ($poll_errors as $poll_error)
		{
			$context['poll_error'][$poll_error] = true;
			$context['poll_error']['messages'][] = $txt['error_' . $poll_error];
		}

		return EditPoll();
	}

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// Now we've done all our error checking, let's get the core poll information cleaned... question first.
	$_POST['question'] = $smcFunc['htmlspecialchars']($_POST['question']);
	$_POST['question'] = $smcFunc['truncate']($_POST['question'], 255);

	$_POST['poll_hide'] = (int) $_POST['poll_hide'];
	$_POST['poll_expire'] = isset($_POST['poll_expire']) ? (int) $_POST['poll_expire'] : 0;
	$_POST['poll_change_vote'] = isset($_POST['poll_change_vote']) ? 1 : 0;
	$_POST['poll_guest_vote'] = isset($_POST['poll_guest_vote']) ? 1 : 0;

	// Make sure guests are actually allowed to vote generally.
	if ($_POST['poll_guest_vote'])
	{
		require_once($sourcedir . '/Subs-Members.php');
		$allowedGroups = groupsAllowedTo('poll_vote', $board);
		if (!in_array(-1, $allowedGroups['allowed']))
			$_POST['poll_guest_vote'] = 0;
	}

	// Ensure that the number options allowed makes sense, and the expiration date is valid.
	if (!$isEdit || allowedTo('moderate_board'))
	{
		$_POST['poll_expire'] = $_POST['poll_expire'] > 9999 ? 9999 : ($_POST['poll_expire'] < 0 ? 0 : $_POST['poll_expire']);

		if (empty($_POST['poll_expire']) && $_POST['poll_hide'] == 2)
			$_POST['poll_hide'] = 1;
		elseif (!$isEdit || $_POST['poll_expire'] != ceil($bcinfo['expire_time'] <= time() ? -1 : ($bcinfo['expire_time'] - time()) / (3600 * 24)))
			$_POST['poll_expire'] = empty($_POST['poll_expire']) ? '0' : time() + $_POST['poll_expire'] * 3600 * 24;
		else
			$_POST['poll_expire'] = $bcinfo['expire_time'];

		if (empty($_POST['poll_max_votes']) || $_POST['poll_max_votes'] <= 0)
			$_POST['poll_max_votes'] = 1;
		else
			$_POST['poll_max_votes'] = (int) $_POST['poll_max_votes'];
	}

	// If we're editing, let's commit the changes.
	if ($isEdit)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}polls
			SET question = {string:question}, change_vote = {int:change_vote},' . (allowedTo('moderate_board') ? '
				hide_results = {int:hide_results}, expire_time = {int:expire_time}, max_votes = {int:max_votes},
				guest_vote = {int:guest_vote}' : '
				hide_results = CASE WHEN expire_time = {int:expire_time_zero} AND {int:hide_results} = 2 THEN 1 ELSE {int:hide_results} END') . '
			WHERE id_poll = {int:id_poll}',
			array(
				'change_vote' => $_POST['poll_change_vote'],
				'hide_results' => $_POST['poll_hide'],
				'expire_time' => !empty($_POST['poll_expire']) ? $_POST['poll_expire'] : 0,
				'max_votes' => !empty($_POST['poll_max_votes']) ? $_POST['poll_max_votes'] : 0,
				'guest_vote' => $_POST['poll_guest_vote'],
				'expire_time_zero' => 0,
				'id_poll' => $bcinfo['id_poll'],
				'question' => $_POST['question'],
			)
		);
	}
	// Otherwise, let's get our poll going!
	else
	{
		// Create the poll.
		$bcinfo['id_poll'] = $smcFunc['db_insert']('',
			'{db_prefix}polls',
			array(
				'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int', 'expire_time' => 'int', 'id_member' => 'int',
				'poster_name' => 'string-255', 'change_vote' => 'int', 'guest_vote' => 'int'
			),
			array(
				$_POST['question'], $_POST['poll_hide'], $_POST['poll_max_votes'], $_POST['poll_expire'], $user_info['id'],
				$user_info['username'], $_POST['poll_change_vote'], $_POST['poll_guest_vote'],
			),
			array('id_poll'),
			1
		);

		// Link the poll to the topic
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET id_poll = {int:id_poll}
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
				'id_poll' => $bcinfo['id_poll'],
			)
		);
	}

	// Get all the choices.  (no better way to remove all emptied and add previously non-existent ones.)
	$request = $smcFunc['db_query']('', '
		SELECT id_choice
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $bcinfo['id_poll'],
		)
	);
	$choices = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$choices[] = $row['id_choice'];
	$smcFunc['db_free_result']($request);

	$delete_options = array();
	foreach ($_POST['options'] as $k => $option)
	{
		// Make sure the key is numeric for sanity's sake.
		$k = (int) $k;

		// They've cleared the box.  Either they want it deleted, or it never existed.
		if (trim($option) == '')
		{
			// They want it deleted.  Bye.
			if (in_array($k, $choices))
				$delete_options[] = $k;

			// Skip the rest...
			continue;
		}

		// Dress the option up for its big date with the database.
		$option = $smcFunc['htmlspecialchars']($option);

		// If it's already there, update it.  If it's not... add it.
		if (in_array($k, $choices))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}poll_choices
				SET label = {string:option_name}
				WHERE id_poll = {int:id_poll}
					AND id_choice = {int:id_choice}',
				array(
					'id_poll' => $bcinfo['id_poll'],
					'id_choice' => $k,
					'option_name' => $option,
				)
			);
		else
			$smcFunc['db_insert']('',
				'{db_prefix}poll_choices',
				array(
					'id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255', 'votes' => 'int',
				),
				array(
					$bcinfo['id_poll'], $k, $option, 0,
				),
				array()
			);
	}

	// I'm sorry, but... well, no one was choosing you.  Poor options, I'll put you out of your misery.
	if (!empty($delete_options))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}
				AND id_choice IN ({array_int:delete_options})',
			array(
				'delete_options' => $delete_options,
				'id_poll' => $bcinfo['id_poll'],
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}poll_choices
			WHERE id_poll = {int:id_poll}
				AND id_choice IN ({array_int:delete_options})',
			array(
				'delete_options' => $delete_options,
				'id_poll' => $bcinfo['id_poll'],
			)
		);
	}

	// Shall I reset the vote count, sir?
	if (isset($_POST['resetVoteCount']))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}polls
			SET num_guest_voters = {int:no_votes}, reset_poll = {int:time}
			WHERE id_poll = {int:id_poll}',
			array(
				'no_votes' => 0,
				'id_poll' => $bcinfo['id_poll'],
				'time' => time(),
			)
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}poll_choices
			SET votes = {int:no_votes}
			WHERE id_poll = {int:id_poll}',
			array(
				'no_votes' => 0,
				'id_poll' => $bcinfo['id_poll'],
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}',
			array(
				'id_poll' => $bcinfo['id_poll'],
			)
		);
	}

	call_integration_hook('integrate_poll_add_edit', array($bcinfo['id_poll'], $isEdit));

	/* Log this edit, but don't go crazy.
		Only specifically adding a poll	or resetting votes is logged.
		Everything else is simply an edit.*/
	if (isset($_REQUEST['add']))
	{
		// Added a poll
		logAction('add_poll', array('topic' => $topic));
	}
	elseif (isset($_REQUEST['deletevotes']))
	{
		// Reset votes
		logAction('reset_poll', array('topic' => $topic));
	}
	else
	{
		// Something else
		logAction('edit_poll', array('topic' => $topic));
	}

	// Off we go.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * Remove a poll from a topic without removing the topic.
 * Must be called with a topic specified in the URL.
 * Requires poll_remove_any permission, unless it's the poll starter
 * with poll_remove_own permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=removepoll.
 */
function RemovePoll()
{
	global $topic, $user_info, $smcFunc;

	// Make sure the topic is not empty.
	if (empty($topic))
		fatal_lang_error('no_access', false);

	// Verify the session.
	checkSession('get');

	// Check permissions.
	if (!allowedTo('poll_remove_any'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT t.id_member_started, p.id_member AS poll_starter
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_access', false);
		list ($topicStarter, $pollStarter) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		isAllowedTo('poll_remove_' . ($topicStarter == $user_info['id'] || ($pollStarter != 0 && $user_info['id'] == $pollStarter) ? 'own' : 'any'));
	}

	// Retrieve the poll ID.
	$request = $smcFunc['db_query']('', '
		SELECT id_poll
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($pollID) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Remove all user logs for this poll.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);
	// Remove all poll choices.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);
	// Remove the poll itself.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);
	// Finally set the topic poll ID back to 0!
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_poll = {int:no_poll}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'no_poll' => 0,
		)
	);

	// A mod might have logged this (social network?), so let them remove, it too
	call_integration_hook('integrate_poll_remove', array($pollID));

	// Log this!
	logAction('remove_poll', array('topic' => $topic));

	// Take the moderator back to the topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

?>