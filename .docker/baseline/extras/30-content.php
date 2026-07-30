<?php

/**
 * The forum features Populate.php leaves out: polls, personal messages,
 * drafts, likes, mentions, edited posts, moved topics and look-alike names.
 *
 * Populate builds topics and messages and nothing else, which means an upgrade
 * run against it would never touch a poll, never see a personal message label,
 * and never have to decide what to do with a draft. All of those have
 * migrations waiting for them.
 *
 * Exercises: UserDrafts, Likes, Mentions, PersonalMessageLabels,
 * PersonalMessageNotification, MovedTopics, MessagesModifiedReason,
 * v3_0\EditHistory, v3_0\MessageVersion, v3_0\SpoofDetector.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

$baseline_name = '30-content';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
else
{
	global $smcFunc, $sourcedir;

	$now = time();
	$members = baseline_member_ids(30);
	$made = array();

	// ------------------------------------------------------------------ polls
	// One still open, one long expired. The expired one matters: expire_time is
	// a column the upgrade carries across, and a zero in it means something
	// different from a date in the past.
	// Not named $topic: that is one of SMF's own globals (the topic currently
	// being viewed), and writing to it at global scope would confuse anything
	// loaded afterwards in the same process.
	$poll_topics = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_topic, id_member_started
		FROM {db_prefix}topics
		ORDER BY id_topic
		LIMIT {int:limit}',
		array('limit' => 2)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$poll_topics[] = $row;

	$smcFunc['db_free_result']($request);

	$made['polls'] = 0;

	foreach ($poll_topics as $index => $poll_topic)
	{
		$id_poll = $smcFunc['db_insert']('',
			'{db_prefix}polls',
			array(
				'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int',
				'expire_time' => 'int', 'id_member' => 'int', 'poster_name' => 'string-255',
				'change_vote' => 'int', 'guest_vote' => 'int',
			),
			array(
				$index === 0 ? 'Which database engine are you on?' : 'Did this poll expire?',
				0, 1,
				$index === 0 ? 0 : $now - 86400,
				(int) $poll_topic['id_member_started'], 'Member ' . $poll_topic['id_member_started'],
				1, $index === 0 ? 1 : 0,
			),
			array('id_poll'),
			1
		);

		$choices = $index === 0
			? array('MySQL', 'PostgreSQL', 'Both, obviously')
			: array('Yes', 'No');

		$choice_rows = array();

		foreach ($choices as $choice_index => $label)
			$choice_rows[] = array($id_poll, $choice_index, $label, 0);

		$smcFunc['db_insert']('insert',
			'{db_prefix}poll_choices',
			array('id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255', 'votes' => 'int'),
			$choice_rows,
			array('id_poll', 'id_choice')
		);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET id_poll = {int:id_poll}
			WHERE id_topic = {int:id_topic}',
			array('id_poll' => $id_poll, 'id_topic' => $poll_topic['id_topic'])
		);

		// Some votes, so log_polls is not empty either.
		$votes = array();

		foreach (array_slice($members, 0, 12) as $vote_index => $id_member)
			$votes[] = array($id_poll, $id_member, $vote_index % count($choices));

		$smcFunc['db_insert']('insert',
			'{db_prefix}log_polls',
			array('id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'),
			$votes,
			array('id_poll', 'id_member')
		);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}poll_choices
			SET votes = {int:votes}
			WHERE id_poll = {int:id_poll}',
			array('votes' => (int) (12 / count($choices)), 'id_poll' => $id_poll)
		);

		$made['polls']++;
	}

	// -------------------------------------------------------- personal messages
	require_once $sourcedir . '/Subs-Post.php';

	$made['pms'] = 0;
	$conversations = array_slice($members, 1, 5);

	foreach ($conversations as $index => $id_member)
	{
		$recipients = array(
			'to' => array($members[0]),
			'bcc' => array(),
		);

		$sent = sendpm(
			$recipients,
			'Baseline conversation ' . ($index + 1),
			'This personal message exists so the upgrade has something to migrate.',
			false,
			array(
				'id' => $id_member,
				'name' => 'Member ' . $id_member,
				'username' => 'Member ' . $id_member,
			)
		);

		if (!empty($sent))
			$made['pms']++;
	}

	// Labels, and a rule to file things under one. Both are 2.1-era structures
	// that SMF 3.0 reworks.
	$id_label = $smcFunc['db_insert']('',
		'{db_prefix}pm_labels',
		array('id_member' => 'int', 'name' => 'string-30'),
		array($members[0], 'Baseline'),
		array('id_label'),
		1
	);

	$labelled = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_pm
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:id_member}
		LIMIT {int:limit}',
		array('id_member' => $members[0], 'limit' => 3)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$labelled[] = array((int) $row['id_pm'], $id_label);

	$smcFunc['db_free_result']($request);

	if (!empty($labelled))
		$smcFunc['db_insert']('ignore',
			'{db_prefix}pm_labeled_messages',
			array('id_pm' => 'int', 'id_label' => 'int'),
			$labelled,
			array('id_pm', 'id_label')
		);

	$smcFunc['db_insert']('insert',
		'{db_prefix}pm_rules',
		array(
			'id_member' => 'int', 'rule_name' => 'string-60', 'criteria' => 'string',
			'actions' => 'string', 'delete_pm' => 'int', 'is_or' => 'int',
		),
		array(
			$members[0], 'Baseline rule',
			json_encode(array(array('t' => 'sub', 'v' => 'Baseline'))),
			json_encode(array(array('t' => 'lab', 'v' => $id_label))),
			0, 0,
		),
		array('id_rule')
	);

	// ----------------------------------------------------------------- drafts
	$made['drafts'] = 0;
	$draft_rows = array();

	foreach (array_slice($members, 0, 5) as $index => $id_member)
	{
		$draft_rows[] = array(
			$index < 3 ? 0 : 0,
			$index < 3 ? 1 : 0,
			0,
			// type 0 is a post draft, type 1 a personal message draft.
			$index < 3 ? 0 : 1,
			$now - (3600 * ($index + 1)),
			$id_member,
			'Unfinished thought ' . ($index + 1),
			1,
			'Started writing this and never came back to it.',
			'xx',
			0,
			0,
			$index < 3 ? '' : json_encode(array($members[0])),
		);
	}

	$smcFunc['db_insert']('insert',
		'{db_prefix}user_drafts',
		array(
			'id_topic' => 'int', 'id_board' => 'int', 'id_reply' => 'int', 'type' => 'int',
			'poster_time' => 'int', 'id_member' => 'int', 'subject' => 'string-255',
			'smileys_enabled' => 'int', 'body' => 'string-65534', 'icon' => 'string-16',
			'locked' => 'int', 'is_sticky' => 'int', 'to_list' => 'string-255',
		),
		$draft_rows,
		array('id_draft')
	);

	$made['drafts'] = count($draft_rows);

	// ------------------------------------------------------- likes and mentions
	$messages = baseline_message_ids(30);
	$likes = array();
	$mentions = array();

	foreach ($messages as $index => $id_msg)
	{
		$likes[] = array($members[$index % count($members)], 'msg', $id_msg, $now - ($index * 60));

		if ($index % 2 === 0)
			$mentions[] = array($id_msg, 'msg', $members[($index + 1) % count($members)], $members[$index % count($members)], $now - ($index * 60));
	}

	$smcFunc['db_insert']('ignore',
		'{db_prefix}user_likes',
		array('id_member' => 'int', 'content_type' => 'string-6', 'content_id' => 'int', 'like_time' => 'int'),
		$likes,
		array('id_member', 'content_type', 'content_id')
	);

	$smcFunc['db_insert']('ignore',
		'{db_prefix}mentions',
		array('content_id' => 'int', 'content_type' => 'string-6', 'id_mentioned' => 'int', 'id_member' => 'int', 'time' => 'int'),
		$mentions,
		array('content_id', 'content_type', 'id_mentioned')
	);

	$made['likes'] = count($likes);
	$made['mentions'] = count($mentions);

	// ------------------------------------------------------------ edited posts
	// SMF 3.0 builds an edit history out of these three columns, so they need to
	// be populated on something.
	$edited = array_slice($messages, 0, 20);

	if (!empty($edited))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET
				modified_time = {int:modified_time},
				modified_name = {string:modified_name},
				modified_reason = {string:modified_reason}
			WHERE id_msg IN ({array_int:messages})',
			array(
				'modified_time' => $now - 7200,
				'modified_name' => 'Member ' . $members[0],
				'modified_reason' => 'Fixed a typo while building the baseline.',
				'messages' => $edited,
			)
		);

	$made['edited'] = count($edited);

	// ----------------------------------------------------------- moved topics
	// A moved topic is an ordinary topic whose first message body is a MOVED:
	// marker. There is a migration that has to recognise them.
	$moved = array();

	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic, t.id_first_msg
		FROM {db_prefix}topics AS t
		ORDER BY t.id_topic DESC
		LIMIT {int:limit}',
		array('limit' => 3)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$moved[] = $row;

	$smcFunc['db_free_result']($request);

	foreach ($moved as $index => $moved_topic)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET
				subject = {string:subject},
				body = {string:body}
			WHERE id_msg = {int:id_msg}',
			array(
				'subject' => 'MOVED: A topic that went somewhere else',
				'body' => 'This topic has been moved to [iurl=&quot;http://localhost/index.php?topic=' . ($index + 1) . '.0&quot;]another board[/iurl].',
				'id_msg' => (int) $moved_topic['id_first_msg'],
			)
		);

	$made['moved'] = count($moved);

	// ------------------------------------------------------------- look-alikes
	// SMF 3.0 ships a spoof detector that looks for display names which are
	// confusable with one another. Give it something to find: same name in a
	// different case, and the same name with a Cyrillic A.
	require_once $sourcedir . '/Subs-Members.php';

	$spoofs = array('Alice Baseline', 'alice baseline', "\xD0\x90lice Baseline");
	$made['spoofs'] = 0;

	foreach ($spoofs as $index => $name)
	{
		// registerMember() takes its options by reference, so they have to be a
		// variable rather than a literal.
		$reg_options = array(
			'interface' => 'admin',
			'username' => 'spoof_' . $index,
			'email' => 'spoof_' . $index . '@example.com',
			'password' => 'baseline',
			// Required whenever a password is supplied: registerMember only
			// defaults this to match when the password is left empty.
			'password_check' => 'baseline',
			'require' => 'nothing',
			'send_welcome_email' => false,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'memberGroup' => 0,
		);

		$id = registerMember($reg_options, false);

		if (empty($id))
			continue;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET real_name = {string:real_name}
			WHERE id_member = {int:id_member}',
			array('real_name' => $name, 'id_member' => $id)
		);

		$made['spoofs']++;
	}

	updateStats('member');
	updateStats('message');
	updateStats('topic');

	$summary = array();

	foreach ($made as $what => $count)
		$summary[] = $count . ' ' . $what;

	baseline_say($baseline_name . ': ' . implode(', ', $summary));

	baseline_mark_applied($baseline_name);
}
