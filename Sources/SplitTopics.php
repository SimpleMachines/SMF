<?php

/**
 * Handle merging and splitting of topics
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 *
 * Original module by Mach8 - We'll never forget you.
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * splits a topic into two topics.
 * delegates to the other functions (based on the URL parameter 'sa').
 * loads the SplitTopics template.
 * requires the split_any permission.
 * is accessed with ?action=splittopics.
 */
function SplitTopics()
{
	global $topic, $sourcedir;

	// And... which topic were you splitting, again?
	if (empty($topic))
		fatal_lang_error('numbers_one_to_nine', false);

	// Are you allowed to split topics?
	isAllowedTo('split_any');

	// Load up the "dependencies" - the template, getMsgMemberID(), and sendNotifications().
	if (!isset($_REQUEST['xml']))
		loadTemplate('SplitTopics');
	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Post.php');

	$subActions = array(
		'selectTopics' => 'SplitSelectTopics',
		'execute' => 'SplitExecute',
		'index' => 'SplitIndex',
		'splitSelection' => 'SplitSelectionExecute',
	);

	// ?action=splittopics;sa=LETSBREAKIT won't work, sorry.
	if (empty($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		SplitIndex();

	else
		call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * screen shown before the actual split.
 * is accessed with ?action=splittopics;sa=index.
 * default sub action for ?action=splittopics.
 * uses 'ask' sub template of the SplitTopics template.
 * redirects to SplitSelectTopics if the message given turns out to be
 * the first message of a topic.
 * shows the user three ways to split the current topic.
 */
function SplitIndex()
{
	global $txt, $topic, $context, $smcFunc, $modSettings;

	// Validate "at".
	if (empty($_GET['at']))
		fatal_lang_error('numbers_one_to_nine', false);
	$_GET['at'] = (int) $_GET['at'];

	// Retrieve the subject and stuff of the specific topic/message.
	$request = $smcFunc['db_query']('', '
		SELECT m.subject, t.num_replies, t.unapproved_posts, t.id_first_msg, t.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {int:split_at}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = 1') . '
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'split_at' => $_GET['at'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('cant_find_messages');

	list ($_REQUEST['subname'], $num_replies, $unapproved_posts, $id_first_msg, $approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// If not approved validate they can see it.
	if ($modSettings['postmod_active'] && !$approved)
		isAllowedTo('approve_posts');

	// If this topic has unapproved posts, we need to count them too...
	if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
		$num_replies += $unapproved_posts - ($approved ? 0 : 1);

	// Check if there is more than one message in the topic.  (there should be.)
	if ($num_replies < 1)
		fatal_lang_error('topic_one_post', false);

	// Check if this is the first message in the topic (if so, the first and second option won't be available)
	if ($id_first_msg == $_GET['at'])
		return SplitSelectTopics();

	// Basic template information....
	$context['message'] = array(
		'id' => $_GET['at'],
		'subject' => $_REQUEST['subname']
	);
	$context['sub_template'] = 'ask';
	$context['page_title'] = $txt['split'];
}

/**
 * do the actual split.
 * is accessed with ?action=splittopics;sa=execute.
 * uses the main SplitTopics template.
 * supports three ways of splitting:
 * (1) only one message is split off.
 * (2) all messages after and including a given message are split off.
 * (3) select topics to split (redirects to SplitSelectTopics()).
 * uses splitTopic function to do the actual splitting.
 */
function SplitExecute()
{
	global $txt, $topic, $context, $smcFunc;

	// Check the session to make sure they meant to do this.
	checkSession();

	// Clean up the subject.
	if (!isset($_POST['subname']) || $_POST['subname'] == '')
		$_POST['subname'] = $txt['new_topic'];

	// Redirect to the selector if they chose selective.
	if ($_POST['step2'] == 'selective')
	{
		$_REQUEST['subname'] = $_POST['subname'];
		return SplitSelectTopics();
	}

	$_POST['at'] = (int) $_POST['at'];
	$messagesToBeSplit = array();

	if ($_POST['step2'] == 'afterthis')
	{
		// Fetch the message IDs of the topic that are at or after the message.
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg >= {int:split_at}',
			array(
				'current_topic' => $topic,
				'split_at' => $_POST['at'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$messagesToBeSplit[] = $row['id_msg'];

		$smcFunc['db_free_result']($request);
	}
	// Only the selected message has to be split. That should be easy.
	elseif ($_POST['step2'] == 'onlythis')
		$messagesToBeSplit[] = $_POST['at'];
	// There's another action?!
	else
		fatal_lang_error('no_access', false);

	$context['old_topic'] = $topic;
	$context['new_topic'] = splitTopic($topic, $messagesToBeSplit, $_POST['subname']);
	$context['page_title'] = $txt['split'];
}

/**
 * allows the user to select the messages to be split.
 * is accessed with ?action=splittopics;sa=selectTopics.
 * uses 'select' sub template of the SplitTopics template or (for
 * XMLhttp) the 'split' sub template of the Xml template.
 * supports XMLhttp for adding/removing a message to the selection.
 * uses a session variable to store the selected topics.
 * shows two independent page indexes for both the selected and
 * not-selected messages (;topic=1.x;start2=y).
 */
function SplitSelectTopics()
{
	global $txt, $scripturl, $topic, $context, $modSettings, $original_msgs, $smcFunc, $options;

	$context['page_title'] = $txt['split'] . ' - ' . $txt['select_split_posts'];

	// Haven't selected anything have we?
	$_SESSION['split_selection'][$topic] = empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic];

	// This is a special case for split topics from quick-moderation checkboxes
	if (isset($_REQUEST['subname_enc']))
		$_REQUEST['subname'] = urldecode($_REQUEST['subname_enc']);

	$context['not_selected'] = array(
		'num_messages' => 0,
		'start' => empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'],
		'messages' => array(),
	);

	$context['selected'] = array(
		'num_messages' => 0,
		'start' => empty($_REQUEST['start2']) ? 0 : (int) $_REQUEST['start2'],
		'messages' => array(),
	);

	$context['topic'] = array(
		'id' => $topic,
		'subject' => urlencode($_REQUEST['subname']),
	);

	// Some stuff for our favorite template.
	$context['new_subject'] = $_REQUEST['subname'];

	// Using the "select" sub template.
	$context['sub_template'] = isset($_REQUEST['xml']) ? 'split' : 'select';

	// Are we using a custom messages per page?
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	// Get the message ID's from before the move.
	if (isset($_REQUEST['xml']))
	{
		$original_msgs = array(
			'not_selected' => array(),
			'selected' => array(),
		);
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][$topic]) ? '' : '
				AND id_msg NOT IN ({array_int:no_split_msgs})') . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . '
				' . (empty($settings['view_newest_first']) ? '' : 'ORDER BY id_msg DESC') . '
				LIMIT {int:start}, {int:messages_per_page}',
			array(
				'current_topic' => $topic,
				'no_split_msgs' => empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
				'start' => $context['not_selected']['start'],
				'messages_per_page' => $context['messages_per_page'],
			)
		);
		// You can't split the last message off.
		if (empty($context['not_selected']['start']) && $smcFunc['db_num_rows']($request) <= 1 && $_REQUEST['move'] == 'down')
			$_REQUEST['move'] = '';
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$original_msgs['not_selected'][] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
		if (!empty($_SESSION['split_selection'][$topic]))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg IN ({array_int:split_msgs})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}') . '
				' . (empty($options['view_newest_first']) ? '' : 'ORDER BY id_msg DESC') . '
				LIMIT {int:start}, {int:messages_per_page}',
				array(
					'current_topic' => $topic,
					'split_msgs' => $_SESSION['split_selection'][$topic],
					'is_approved' => 1,
					'start' => $context['selected']['start'],
					'messages_per_page' => $context['messages_per_page'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$original_msgs['selected'][] = $row['id_msg'];

			$smcFunc['db_free_result']($request);
		}
	}

	// (De)select a message..
	if (!empty($_REQUEST['move']))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		if ($_REQUEST['move'] == 'reset')
			$_SESSION['split_selection'][$topic] = array();
		elseif ($_REQUEST['move'] == 'up')
			$_SESSION['split_selection'][$topic] = array_diff($_SESSION['split_selection'][$topic], array($_REQUEST['msg']));
		else
			$_SESSION['split_selection'][$topic][] = $_REQUEST['msg'];
	}

	// Make sure the selection is still accurate.
	if (!empty($_SESSION['split_selection'][$topic]))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg IN ({array_int:split_msgs})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}'),
			array(
				'current_topic' => $topic,
				'split_msgs' => $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
			)
		);
		$_SESSION['split_selection'][$topic] = array();

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$_SESSION['split_selection'][$topic][] = $row['id_msg'];

		$smcFunc['db_free_result']($request);
	}

	// Get the number of messages (not) selected to be split.
	$request = $smcFunc['db_query']('', '
		SELECT ' . (empty($_SESSION['split_selection'][$topic]) ? '0' : 'm.id_msg IN ({array_int:split_msgs})') . ' AS is_selected, COUNT(*) AS num_messages
		FROM {db_prefix}messages AS m
		WHERE m.id_topic = {int:current_topic}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND approved = {int:is_approved}') . (empty($_SESSION['split_selection'][$topic]) ? '' : '
		GROUP BY is_selected'),
		array(
			'current_topic' => $topic,
			'split_msgs' => !empty($_SESSION['split_selection'][$topic]) ? $_SESSION['split_selection'][$topic] : array(),
			'is_approved' => 1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context[empty($row['is_selected']) || $row['is_selected'] == 'f' ? 'not_selected' : 'selected']['num_messages'] = $row['num_messages'];
	$smcFunc['db_free_result']($request);

	// Fix an oversized starting page (to make sure both pageindexes are properly set).
	if ($context['selected']['start'] >= $context['selected']['num_messages'])
		$context['selected']['start'] = $context['selected']['num_messages'] <= $context['messages_per_page'] ? 0 : ($context['selected']['num_messages'] - (($context['selected']['num_messages'] % $context['messages_per_page']) == 0 ? $context['messages_per_page'] : ($context['selected']['num_messages'] % $context['messages_per_page'])));

	// Build a page list of the not-selected topics...
	$context['not_selected']['page_index'] = constructPageIndex($scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), array('%' => '%%')) . ';topic=' . $topic . '.%1$d;start2=' . $context['selected']['start'], $context['not_selected']['start'], $context['not_selected']['num_messages'], $context['messages_per_page'], true);
	// ...and one of the selected topics.
	$context['selected']['page_index'] = constructPageIndex($scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), array('%' => '%%')) . ';topic=' . $topic . '.' . $context['not_selected']['start'] . ';start2=%1$d', $context['selected']['start'], $context['selected']['num_messages'], $context['messages_per_page'], true);

	// Get the messages and stick them into an array.
	$request = $smcFunc['db_query']('', '
		SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS real_name, m.poster_time, m.body, m.id_msg, m.smileys_enabled
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][$topic]) ? '' : '
			AND id_msg NOT IN ({array_int:no_split_msgs})') . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND approved = {int:is_approved}') . '
			' . (empty($options['view_newest_first']) ? '' : 'ORDER BY m.id_msg DESC') . '
			LIMIT {int:start}, {int:messages_per_page}',
		array(
			'current_topic' => $topic,
			'no_split_msgs' => !empty($_SESSION['split_selection'][$topic]) ? $_SESSION['split_selection'][$topic] : array(),
			'is_approved' => 1,
			'start' => $context['not_selected']['start'],
			'messages_per_page' => $context['messages_per_page'],
		)
	);
	$context['messages'] = array();
	for ($counter = 0; $row = $smcFunc['db_fetch_assoc']($request); $counter++)
	{
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		$context['not_selected']['messages'][$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => $row['body'],
			'poster' => $row['real_name'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Now get the selected messages.
	if (!empty($_SESSION['split_selection'][$topic]))
	{
		// Get the messages and stick them into an array.
		$request = $smcFunc['db_query']('', '
			SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS real_name,  m.poster_time, m.body, m.id_msg, m.smileys_enabled
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_topic = {int:current_topic}
				AND m.id_msg IN ({array_int:split_msgs})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . '
			' . (empty($options['view_newest_first']) ? '' : 'ORDER BY m.id_msg DESC') . '
			LIMIT {int:start}, {int:messages_per_page}',
			array(
				'current_topic' => $topic,
				'split_msgs' => $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
				'start' => $context['selected']['start'],
				'messages_per_page' => $context['messages_per_page'],
			)
		);
		$context['messages'] = array();
		for ($counter = 0; $row = $smcFunc['db_fetch_assoc']($request); $counter++)
		{
			censorText($row['subject']);
			censorText($row['body']);

			$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

			$context['selected']['messages'][$row['id_msg']] = array(
				'id' => $row['id_msg'],
				'subject' => $row['subject'],
				'time' => timeformat($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'body' => $row['body'],
				'poster' => $row['real_name']
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// The XMLhttp method only needs the stuff that changed, so let's compare.
	if (isset($_REQUEST['xml']))
	{
		$changes = array(
			'remove' => array(
				'not_selected' => array_diff($original_msgs['not_selected'], array_keys($context['not_selected']['messages'])),
				'selected' => array_diff($original_msgs['selected'], array_keys($context['selected']['messages'])),
			),
			'insert' => array(
				'not_selected' => array_diff(array_keys($context['not_selected']['messages']), $original_msgs['not_selected']),
				'selected' => array_diff(array_keys($context['selected']['messages']), $original_msgs['selected']),
			),
		);

		$context['changes'] = array();
		foreach ($changes as $change_type => $change_array)
			foreach ($change_array as $section => $msg_array)
			{
				if (empty($msg_array))
					continue;

				foreach ($msg_array as $id_msg)
				{
					$context['changes'][$change_type . $id_msg] = array(
						'id' => $id_msg,
						'type' => $change_type,
						'section' => $section,
					);
					if ($change_type == 'insert')
						$context['changes']['insert' . $id_msg]['insert_value'] = $context[$section]['messages'][$id_msg];
				}
			}
	}
}

/**
 * do the actual split of a selection of topics.
 * is accessed with ?action=splittopics;sa=splitSelection.
 * uses the main SplitTopics template.
 * uses splitTopic function to do the actual splitting.
 */
function SplitSelectionExecute()
{
	global $txt, $topic, $context;

	// Make sure the session id was passed with post.
	checkSession();

	// Default the subject in case it's blank.
	if (!isset($_POST['subname']) || $_POST['subname'] == '')
		$_POST['subname'] = $txt['new_topic'];

	// You must've selected some messages!  Can't split out none!
	if (empty($_SESSION['split_selection'][$topic]))
		fatal_lang_error('no_posts_selected', false);

	$context['old_topic'] = $topic;
	$context['new_topic'] = splitTopic($topic, $_SESSION['split_selection'][$topic], $_POST['subname']);
	$context['page_title'] = $txt['split'];
}

/**
 * general function to split off a topic.
 * creates a new topic and moves the messages with the IDs in
 * array messagesToBeSplit to the new topic.
 * the subject of the newly created topic is set to 'newSubject'.
 * marks the newly created message as read for the user splitting it.
 * updates the statistics to reflect a newly created topic.
 * logs the action in the moderation log.
 * a notification is sent to all users monitoring this topic.
 *
 * @param int $split1_ID_TOPIC The ID of the topic we're splitting
 * @param array $splitMessages The IDs of the messages being split
 * @param string $new_subject The subject of the new topic
 * @return int The ID of the new split topic.
 */
function splitTopic($split1_ID_TOPIC, $splitMessages, $new_subject)
{
	global $smcFunc, $txt, $sourcedir;

	// Nothing to split?
	if (empty($splitMessages))
		fatal_lang_error('no_posts_selected', false);

	// Get some board info.
	$request = $smcFunc['db_query']('', '
		SELECT id_board, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:id_topic}
		LIMIT 1',
		array(
			'id_topic' => $split1_ID_TOPIC,
		)
	);
	list ($id_board, $split1_approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Find the new first and last not in the list. (old topic)
	$request = $smcFunc['db_query']('', '
		SELECT
			MIN(m.id_msg) AS myid_first_msg, MAX(m.id_msg) AS myid_last_msg, COUNT(*) AS message_count, m.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:id_topic})
		WHERE m.id_msg NOT IN ({array_int:no_msg_list})
			AND m.id_topic = {int:id_topic}
		GROUP BY m.approved
		ORDER BY m.approved DESC
		LIMIT 2',
		array(
			'id_topic' => $split1_ID_TOPIC,
			'no_msg_list' => $splitMessages,
		)
	);
	// You can't select ALL the messages!
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('selected_all_posts', false);

	$split1_first_msg = null;
	$split1_last_msg = null;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Get the right first and last message dependant on approved state...
		if (empty($split1_first_msg) || $row['myid_first_msg'] < $split1_first_msg)
			$split1_first_msg = $row['myid_first_msg'];
		if (empty($split1_last_msg) || $row['approved'])
			$split1_last_msg = $row['myid_last_msg'];

		// Get the counts correct...
		if ($row['approved'])
		{
			$split1_replies = $row['message_count'] - 1;
			$split1_unapprovedposts = 0;
		}
		else
		{
			if (!isset($split1_replies))
				$split1_replies = 0;
			// If the topic isn't approved then num replies must go up by one... as first post wouldn't be counted.
			elseif (!$split1_approved)
				$split1_replies++;

			$split1_unapprovedposts = $row['message_count'];
		}
	}
	$smcFunc['db_free_result']($request);
	$split1_firstMem = getMsgMemberID($split1_first_msg);
	$split1_lastMem = getMsgMemberID($split1_last_msg);

	// Find the first and last in the list. (new topic)
	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg) AS myid_first_msg, MAX(id_msg) AS myid_last_msg, COUNT(*) AS message_count, approved
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:msg_list})
			AND id_topic = {int:id_topic}
		GROUP BY id_topic, approved
		ORDER BY approved DESC
		LIMIT 2',
		array(
			'msg_list' => $splitMessages,
			'id_topic' => $split1_ID_TOPIC,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// As before get the right first and last message dependant on approved state...
		if (empty($split2_first_msg) || $row['myid_first_msg'] < $split2_first_msg)
			$split2_first_msg = $row['myid_first_msg'];
		if (empty($split2_last_msg) || $row['approved'])
			$split2_last_msg = $row['myid_last_msg'];

		// Then do the counts again...
		if ($row['approved'])
		{
			$split2_approved = true;
			$split2_replies = $row['message_count'] - 1;
			$split2_unapprovedposts = 0;
		}
		else
		{
			// Should this one be approved??
			if ($split2_first_msg == $row['myid_first_msg'])
				$split2_approved = false;

			if (!isset($split2_replies))
				$split2_replies = 0;
			// As before, fix number of replies.
			elseif (!$split2_approved)
				$split2_replies++;

			$split2_unapprovedposts = $row['message_count'];
		}
	}
	$smcFunc['db_free_result']($request);
	$split2_firstMem = getMsgMemberID($split2_first_msg);
	$split2_lastMem = getMsgMemberID($split2_last_msg);

	// No database changes yet, so let's double check to see if everything makes at least a little sense.
	if ($split1_first_msg <= 0 || $split1_last_msg <= 0 || $split2_first_msg <= 0 || $split2_last_msg <= 0 || $split1_replies < 0 || $split2_replies < 0 || $split1_unapprovedposts < 0 || $split2_unapprovedposts < 0 || !isset($split1_approved) || !isset($split2_approved))
		fatal_lang_error('cant_find_messages');

	// You cannot split off the first message of a topic.
	if ($split1_first_msg > $split2_first_msg)
		fatal_lang_error('split_first_post', false);

	// We're off to insert the new topic!  Use 0 for now to avoid UNIQUE errors.
	$split2_ID_TOPIC = $smcFunc['db_insert']('',
		'{db_prefix}topics',
		array(
			'id_board' => 'int',
			'id_member_started' => 'int',
			'id_member_updated' => 'int',
			'id_first_msg' => 'int',
			'id_last_msg' => 'int',
			'num_replies' => 'int',
			'unapproved_posts' => 'int',
			'approved' => 'int',
			'is_sticky' => 'int',
		),
		array(
			(int) $id_board, $split2_firstMem, $split2_lastMem, 0,
			0, $split2_replies, $split2_unapprovedposts, (int) $split2_approved, 0,
		),
		array('id_topic'),
		1
	);
	if ($split2_ID_TOPIC <= 0)
		fatal_lang_error('cant_insert_topic');

	// Move the messages over to the other topic.
	$new_subject = strtr($smcFunc['htmltrim']($smcFunc['htmlspecialchars']($new_subject)), array("\r" => '', "\n" => '', "\t" => ''));
	// Check the subject length.
	if ($smcFunc['strlen']($new_subject) > 100)
		$new_subject = $smcFunc['substr']($new_subject, 0, 100);
	// Valid subject?
	if ($new_subject != '')
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET
				id_topic = {int:id_topic},
				subject = CASE WHEN id_msg = {int:split_first_msg} THEN {string:new_subject} ELSE {string:new_subject_replies} END
			WHERE id_msg IN ({array_int:split_msgs})',
			array(
				'split_msgs' => $splitMessages,
				'id_topic' => $split2_ID_TOPIC,
				'new_subject' => $new_subject,
				'split_first_msg' => $split2_first_msg,
				'new_subject_replies' => $txt['response_prefix'] . $new_subject,
			)
		);

		// Cache the new topics subject... we can do it now as all the subjects are the same!
		updateStats('subject', $split2_ID_TOPIC, $new_subject);
	}

	// Any associated reported posts better follow...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET id_topic = {int:id_topic}
		WHERE id_msg IN ({array_int:split_msgs})',
		array(
			'split_msgs' => $splitMessages,
			'id_topic' => $split2_ID_TOPIC,
		)
	);

	// Mess with the old topic's first, last, and number of messages.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			num_replies = {int:num_replies},
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			id_member_started = {int:id_member_started},
			id_member_updated = {int:id_member_updated},
			unapproved_posts = {int:unapproved_posts}
		WHERE id_topic = {int:id_topic}',
		array(
			'num_replies' => $split1_replies,
			'id_first_msg' => $split1_first_msg,
			'id_last_msg' => $split1_last_msg,
			'id_member_started' => $split1_firstMem,
			'id_member_updated' => $split1_lastMem,
			'unapproved_posts' => $split1_unapprovedposts,
			'id_topic' => $split1_ID_TOPIC,
		)
	);

	// Now, put the first/last message back to what they should be.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_first_msg' => $split2_first_msg,
			'id_last_msg' => $split2_last_msg,
			'id_topic' => $split2_ID_TOPIC,
		)
	);

	// If the new topic isn't approved ensure the first message flags this just in case.
	if (!$split2_approved)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET approved = {int:approved}
			WHERE id_msg = {int:id_msg}
				AND id_topic = {int:id_topic}',
			array(
				'approved' => 0,
				'id_msg' => $split2_first_msg,
				'id_topic' => $split2_ID_TOPIC,
			)
		);

	// The board has more topics now (Or more unapproved ones!).
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET ' . ($split2_approved ? '
			num_topics = num_topics + 1' : '
			unapproved_topics = unapproved_topics + 1') . '
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $id_board,
		)
	);

	// Copy log topic entries.
	// @todo This should really be chunked.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_msg, unwatched
		FROM {db_prefix}log_topics
		WHERE id_topic = {int:id_topic}',
		array(
			'id_topic' => (int) $split1_ID_TOPIC,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$replaceEntries = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$replaceEntries[] = array($row['id_member'], $split2_ID_TOPIC, $row['id_msg'], $row['unwatched']);

		$smcFunc['db_insert']('ignore',
			'{db_prefix}log_topics',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'),
			$replaceEntries,
			array('id_member', 'id_topic')
		);
		unset($replaceEntries);
	}
	$smcFunc['db_free_result']($request);

	// Housekeeping.
	updateStats('topic');
	updateLastMessages($id_board);

	logAction('split', array('topic' => $split1_ID_TOPIC, 'new_topic' => $split2_ID_TOPIC, 'board' => $id_board));

	// Notify people that this topic has been split?
	sendNotifications($split1_ID_TOPIC, 'split');

	// If there's a search index that needs updating, update it...
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'topicSplit')))
		$searchAPI->topicSplit($split2_ID_TOPIC, $splitMessages);

	// Maybe we want to let an external CMS know about this split
	$split1 = array(
		'num_replies' => $split1_replies,
		'id_first_msg' => $split1_first_msg,
		'id_last_msg' => $split1_last_msg,
		'id_member_started' => $split1_firstMem,
		'id_member_updated' => $split1_lastMem,
		'unapproved_posts' => $split1_unapprovedposts,
		'id_topic' => $split1_ID_TOPIC,
	);
	$split2 = array(
		'num_replies' => $split2_replies,
		'id_first_msg' => $split2_first_msg,
		'id_last_msg' => $split2_last_msg,
		'id_member_started' => $split2_firstMem,
		'id_member_updated' => $split2_lastMem,
		'unapproved_posts' => $split2_unapprovedposts,
		'id_topic' => $split2_ID_TOPIC,
	);
	call_integration_hook('integrate_split_topic', array($split1, $split2, $new_subject, $id_board));

	// Return the ID of the newly created topic.
	return $split2_ID_TOPIC;
}

/**
 * merges two or more topics into one topic.
 * delegates to the other functions (based on the URL parameter sa).
 * loads the SplitTopics template.
 * requires the merge_any permission.
 * is accessed with ?action=mergetopics.
 */
function MergeTopics()
{
	// Load the template....
	loadTemplate('MoveTopic');

	$subActions = array(
		'done' => 'MergeDone',
		'execute' => 'MergeExecute',
		'index' => 'MergeIndex',
		'options' => 'MergeExecute',
	);

	// ?action=mergetopics;sa=LETSBREAKIT won't work, sorry.
	if (empty($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		MergeIndex();

	else
		call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * allows to pick a topic to merge the current topic with.
 * is accessed with ?action=mergetopics;sa=index
 * default sub action for ?action=mergetopics.
 * uses 'merge' sub template of the MoveTopic template.
 * allows to set a different target board.
 */
function MergeIndex()
{
	global $txt, $board, $context, $smcFunc, $sourcedir;
	global $scripturl, $modSettings;

	if (!isset($_GET['from']))
		fatal_lang_error('no_access', false);
	$_GET['from'] = (int) $_GET['from'];

	$_REQUEST['targetboard'] = isset($_REQUEST['targetboard']) ? (int) $_REQUEST['targetboard'] : $board;
	$context['target_board'] = $_REQUEST['targetboard'];

	// Prepare a handy query bit for approval...
	if ($modSettings['postmod_active'])
	{
		$can_approve_boards = boardsAllowedTo('approve_posts');
		$onlyApproved = $can_approve_boards !== array(0) && !in_array($_REQUEST['targetboard'], $can_approve_boards);
	}
	else
		$onlyApproved = false;

	// How many topics are on this board?  (used for paging.)
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics AS t
		WHERE t.id_board = {int:id_board}' . ($onlyApproved ? '
			AND t.approved = {int:is_approved}' : ''),
		array(
			'id_board' => $_REQUEST['targetboard'],
			'is_approved' => 1,
		)
	);
	list ($topiccount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Make the page list.
	$context['page_index'] = constructPageIndex($scripturl . '?action=mergetopics;from=' . $_GET['from'] . ';targetboard=' . $_REQUEST['targetboard'] . ';board=' . $board . '.%1$d', $_REQUEST['start'], $topiccount, $modSettings['defaultMaxTopics'], true);

	// Get the topic's subject.
	$request = $smcFunc['db_query']('', '
		SELECT m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:id_topic}
			AND t.id_board = {int:current_board}' . ($onlyApproved ? '
			AND t.approved = {int:is_approved}' : '') . '
		LIMIT 1',
		array(
			'current_board' => $board,
			'id_topic' => $_GET['from'],
			'is_approved' => 1,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board');
	list ($subject) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Tell the template a few things..
	$context['origin_topic'] = $_GET['from'];
	$context['origin_subject'] = $subject;
	$context['origin_js_subject'] = addcslashes(addslashes($subject), '/');
	$context['page_title'] = $txt['merge'];

	// Check which boards you have merge permissions on.
	$merge_boards = boardsAllowedTo('merge_any');

	if (empty($merge_boards))
		fatal_lang_error('cannot_merge_any', 'user');

	// No sense in loading this if you can only merge on this board
	if (count($merge_boards) > 1 || in_array(0, $merge_boards))
	{
		require_once($sourcedir . '/Subs-MessageIndex.php');

		// Set up a couple of options for our board list
		$options = array(
			'not_redirection' => true,
			'selected_board' => $context['target_board'],
		);

		// Only include these boards in the list (0 means you're an admin')
		if (!in_array(0, $merge_boards))
			$options['included_boards'] = $merge_boards;

		$context['merge_categories'] = getBoardList($options);
	}

	// Get some topics to merge it with.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic, m.subject, m.id_member, COALESCE(mem.real_name, m.poster_name) AS poster_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_board = {int:id_board}
			AND t.id_topic != {int:id_topic}
			AND t.id_redirect_topic = {int:not_redirect}' . ($onlyApproved ? '
			AND t.approved = {int:is_approved}' : '') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'id_board' => $_REQUEST['targetboard'],
			'id_topic' => $_GET['from'],
			'sort' => 't.is_sticky DESC, t.id_last_msg DESC',
			'offset' => $_REQUEST['start'],
			'limit' => $modSettings['defaultMaxTopics'],
			'is_approved' => 1,
			'not_redirect' => 0,
		)
	);
	$context['topics'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($row['subject']);

		$context['topics'][] = array(
			'id' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank" rel="noopener">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'js_subject' => addcslashes(addslashes($row['subject']), '/')
		);
	}
	$smcFunc['db_free_result']($request);

	if (empty($context['topics']) && count($merge_boards) <= 1 && !in_array(0, $merge_boards))
		fatal_lang_error('merge_need_more_topics');

	$context['sub_template'] = 'merge';
}

/**
 * set merge options and do the actual merge of two or more topics.
 *
 * the merge options screen:
 * * shows topics to be merged and allows to set some merge options.
 * * is accessed by ?action=mergetopics;sa=options.and can also internally be called by QuickModeration() (Subs-Boards.php).
 * * uses 'merge_extra_options' sub template of the MoveTopic template.
 *
 * the actual merge:
 * * is accessed with ?action=mergetopics;sa=execute.
 * * updates the statistics to reflect the merge.
 * * logs the action in the moderation log.
 * * sends a notification is sent to all users monitoring this topic.
 * * redirects to ?action=mergetopics;sa=done.
 *
 * @param array $topics The IDs of the topics to merge
 */
function MergeExecute($topics = array())
{
	global $user_info, $txt, $context, $scripturl, $sourcedir;
	global $smcFunc, $language, $modSettings;

	// Check the session.
	checkSession('request');

	// Handle URLs from MergeIndex.
	if (!empty($_GET['from']) && !empty($_GET['to']))
		$topics = array((int) $_GET['from'], (int) $_GET['to']);

	// If we came from a form, the topic IDs came by post.
	if (!empty($_POST['topics']) && is_array($_POST['topics']))
		$topics = $_POST['topics'];

	// There's nothing to merge with just one topic...
	if (empty($topics) || !is_array($topics) || count($topics) == 1)
		fatal_lang_error('merge_need_more_topics');

	// Make sure every topic is numeric, or some nasty things could be done with the DB.
	foreach ($topics as $id => $topic)
		$topics[$id] = (int) $topic;

	// Joy of all joys, make sure they're not messing about with unapproved topics they can't see :P
	if ($modSettings['postmod_active'])
		$can_approve_boards = boardsAllowedTo('approve_posts');

	// Get info about the topics and polls that will be merged.
	$request = $smcFunc['db_query']('', '
		SELECT
			t.id_topic, t.id_board, t.id_poll, t.num_views, t.is_sticky, t.approved, t.num_replies, t.unapproved_posts, t.id_redirect_topic,
			m1.subject, m1.poster_time AS time_started, COALESCE(mem1.id_member, 0) AS id_member_started, COALESCE(mem1.real_name, m1.poster_name) AS name_started,
			m2.poster_time AS time_updated, COALESCE(mem2.id_member, 0) AS id_member_updated, COALESCE(mem2.real_name, m2.poster_name) AS name_updated
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m1 ON (m1.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem1 ON (mem1.id_member = m1.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
		WHERE t.id_topic IN ({array_int:topic_list})
		ORDER BY t.id_first_msg
		LIMIT {int:limit}',
		array(
			'topic_list' => $topics,
			'limit' => count($topics),
		)
	);
	if ($smcFunc['db_num_rows']($request) < 2)
		fatal_lang_error('no_topic_id');

	$num_views = 0;
	$is_sticky = 0;
	$boardTotals = array();
	$boards = array();
	$polls = array();
	$firstTopic = 0;
	$context['is_approved'] = 1;
	$lowestTopicId = 0;
	$lowestTopicBoard = 0;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Sorry, redirection topics can't be merged
		if (!empty($row['id_redirect_topic']))
			fatal_lang_error('cannot_merge_redirect', false);

		// Make a note for the board counts...
		if (!isset($boardTotals[$row['id_board']]))
			$boardTotals[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0
			);

		// We can't see unapproved topics here?
		if ($modSettings['postmod_active'] && !$row['approved'] && $can_approve_boards != array(0) && in_array($row['id_board'], $can_approve_boards))
		{
			unset($topics[$row['id_topic']]); // If we can't see it, we should not merge it and not adjust counts! Instead skip it.
			continue;
		}
		elseif (!$row['approved'])
			$boardTotals[$row['id_board']]['unapproved_topics']++;
		else
			$boardTotals[$row['id_board']]['topics']++;

		$boardTotals[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];
		$boardTotals[$row['id_board']]['posts'] += $row['num_replies'] + ($row['approved'] ? 1 : 0);

		// In the case of making a redirect, the topic count goes up by one due to the redirect topic.
		if (isset($_POST['postRedirect']))
			$boardTotals[$row['id_board']]['topics']--;

		$topic_data[$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'board' => $row['id_board'],
			'poll' => $row['id_poll'],
			'num_views' => $row['num_views'],
			'subject' => $row['subject'],
			'started' => array(
				'time' => timeformat($row['time_started']),
				'timestamp' => forum_time(true, $row['time_started']),
				'href' => empty($row['id_member_started']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_started'],
				'link' => empty($row['id_member_started']) ? $row['name_started'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_started'] . '">' . $row['name_started'] . '</a>'
			),
			'updated' => array(
				'time' => timeformat($row['time_updated']),
				'timestamp' => forum_time(true, $row['time_updated']),
				'href' => empty($row['id_member_updated']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_updated'],
				'link' => empty($row['id_member_updated']) ? $row['name_updated'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['name_updated'] . '</a>'
			),
			'approved' => $row['approved']
		);
		$num_views += $row['num_views'];
		$boards[] = $row['id_board'];

		// If there's no poll, id_poll == 0...
		if ($row['id_poll'] > 0)
			$polls[] = $row['id_poll'];
		// Store the id_topic with the lowest id_first_msg.
		if (empty($firstTopic))
			$firstTopic = $row['id_topic'];

		// Lowest topic id gets selected as surviving topic id. We need to store this board so we can adjust the topic count (This one will not have a redirect topic)
		if ($row['id_topic'] < $lowestTopicId || empty($lowestTopicId))
		{
			$lowestTopicId = $row['id_topic'];
			$lowestTopicBoard = $row['id_board'];
		}

		$is_sticky = max($is_sticky, $row['is_sticky']);
	}
	$smcFunc['db_free_result']($request);

	// If we didn't get any topics then they've been messing with unapproved stuff.
	if (empty($topic_data))
		fatal_lang_error('no_topic_id');

	if (isset($_POST['postRedirect']) && !empty($lowestTopicBoard))
		$boardTotals[$lowestTopicBoard]['topics']++;

	// Will this be approved?
	$context['is_approved'] = $topic_data[$firstTopic]['approved'];

	$boards = array_values(array_unique($boards));

	// The parameters of MergeExecute were set, so this must've been an internal call.
	if (!empty($topics))
	{
		isAllowedTo('merge_any', $boards);
		loadTemplate('MoveTopic');
	}

	// Get the boards a user is allowed to merge in.
	$merge_boards = boardsAllowedTo('merge_any');
	if (empty($merge_boards))
		fatal_lang_error('cannot_merge_any', 'user');

	// Make sure they can see all boards....
	$request = $smcFunc['db_query']('', '
		SELECT b.id_board
		FROM {db_prefix}boards AS b
		WHERE b.id_board IN ({array_int:boards})
			AND {query_see_board}' . (!in_array(0, $merge_boards) ? '
			AND b.id_board IN ({array_int:merge_boards})' : '') . '
		LIMIT {int:limit}',
		array(
			'boards' => $boards,
			'merge_boards' => $merge_boards,
			'limit' => count($boards),
		)
	);
	// If the number of boards that's in the output isn't exactly the same as we've put in there, you're in trouble.
	if ($smcFunc['db_num_rows']($request) != count($boards))
		fatal_lang_error('no_board');
	$smcFunc['db_free_result']($request);

	if (empty($_REQUEST['sa']) || $_REQUEST['sa'] == 'options')
	{
		if (count($polls) > 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic, t.id_poll, m.subject, p.question
				FROM {db_prefix}polls AS p
					INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE p.id_poll IN ({array_int:polls})
				LIMIT {int:limit}',
				array(
					'polls' => $polls,
					'limit' => count($polls),
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['polls'][] = array(
					'id' => $row['id_poll'],
					'topic' => array(
						'id' => $row['id_topic'],
						'subject' => $row['subject']
					),
					'question' => $row['question'],
					'selected' => $row['id_topic'] == $firstTopic
				);
			$smcFunc['db_free_result']($request);
		}
		if (count($boards) > 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_board, name
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:boards})
				ORDER BY name
				LIMIT {int:limit}',
				array(
					'boards' => $boards,
					'limit' => count($boards),
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['boards'][] = array(
					'id' => $row['id_board'],
					'name' => $row['name'],
					'selected' => $row['id_board'] == $topic_data[$firstTopic]['board']
				);
			$smcFunc['db_free_result']($request);
		}

		$context['topics'] = $topic_data;
		foreach ($topic_data as $id => $topic)
			$context['topics'][$id]['selected'] = $topic['id'] == $firstTopic;

		$context['page_title'] = $txt['merge'];
		$context['sub_template'] = 'merge_extra_options';
		return;
	}

	// Determine target board.
	$target_board = count($boards) > 1 ? (int) $_REQUEST['board'] : $boards[0];
	if (!in_array($target_board, $boards))
		fatal_lang_error('no_board');

	// Determine which poll will survive and which polls won't.
	$target_poll = count($polls) > 1 ? (int) $_POST['poll'] : (count($polls) == 1 ? $polls[0] : 0);
	if ($target_poll > 0 && !in_array($target_poll, $polls))
		fatal_lang_error('no_access', false);
	$deleted_polls = empty($target_poll) ? $polls : array_diff($polls, array($target_poll));

	// Determine the subject of the newly merged topic - was a custom subject specified?
	if (empty($_POST['subject']) && isset($_POST['custom_subject']) && $_POST['custom_subject'] != '')
	{
		$target_subject = strtr($smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));
		// Keep checking the length.
		if ($smcFunc['strlen']($target_subject) > 100)
			$target_subject = $smcFunc['substr']($target_subject, 0, 100);

		// Nothing left - odd but pick the first topics subject.
		if ($target_subject == '')
			$target_subject = $topic_data[$firstTopic]['subject'];
	}
	// A subject was selected from the list.
	elseif (!empty($topic_data[(int) $_POST['subject']]['subject']))
		$target_subject = $topic_data[(int) $_POST['subject']]['subject'];
	// Nothing worked? Just take the subject of the first message.
	else
		$target_subject = $topic_data[$firstTopic]['subject'];

	// Get the first and last message and the number of messages....
	$request = $smcFunc['db_query']('', '
		SELECT approved, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg, COUNT(*) AS message_count
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topics})
		GROUP BY approved
		ORDER BY approved DESC',
		array(
			'topics' => $topics,
		)
	);
	$topic_approved = 1;
	$first_msg = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If this is approved, or is fully unapproved.
		if ($row['approved'] || !empty($first_msg))
		{
			$first_msg = $row['first_msg'];
			$last_msg = $row['last_msg'];
			if ($row['approved'])
			{
				$num_replies = $row['message_count'] - 1;
				$num_unapproved = 0;
			}
			else
			{
				$topic_approved = 0;
				$num_replies = 0;
				$num_unapproved = $row['message_count'];
			}
		}
		else
		{
			// If this has a lower first_msg then the first post is not approved and hence the number of replies was wrong!
			if ($first_msg > $row['first_msg'])
			{
				$first_msg = $row['first_msg'];
				$num_replies++;
				$topic_approved = 0;
			}
			$num_unapproved = $row['message_count'];
		}
	}
	$smcFunc['db_free_result']($request);

	// Ensure we have a board stat for the target board.
	if (!isset($boardTotals[$target_board]))
	{
		$boardTotals[$target_board] = array(
			'posts' => 0,
			'topics' => 0,
			'unapproved_posts' => 0,
			'unapproved_topics' => 0
		);
	}

	// Fix the topic count stuff depending on what the new one counts as.
	$boardTotals[$target_board][(!$topic_approved) ? 'unapproved_topics' : 'topics']--;

	$boardTotals[$target_board]['unapproved_posts'] -= $num_unapproved;
	$boardTotals[$target_board]['posts'] -= $topic_approved ? $num_replies + 1 : $num_replies;

	// Get the member ID of the first and last message.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}messages
		WHERE id_msg IN ({int:first_msg}, {int:last_msg})
		ORDER BY id_msg
		LIMIT 2',
		array(
			'first_msg' => $first_msg,
			'last_msg' => $last_msg,
		)
	);
	list ($member_started) = $smcFunc['db_fetch_row']($request);
	list ($member_updated) = $smcFunc['db_fetch_row']($request);

	// First and last message are the same, so only row was returned.
	if ($member_updated === null)
		$member_updated = $member_started;

	$smcFunc['db_free_result']($request);

	// Obtain all the message ids we are going to affect.
	$affected_msgs = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topic_list})',
		array(
			'topic_list' => $topics,
		)
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$affected_msgs[] = $row[0];
	$smcFunc['db_free_result']($request);

	// Assign the first topic ID to be the merged topic.
	$id_topic = min($topics);

	$deleted_topics = array_diff($topics, array($id_topic));
	$updated_topics = array();

	// Create stub topics out of the remaining topics.
	// We don't want the search index data though (For non-redirect merges).
	if (!isset($_POST['postRedirect']))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
			)
		);
	}

	require_once($sourcedir . '/Subs-Post.php');
	$posterOptions = array(
		'id' => $user_info['id'],
		'update_post_count' => false,
	);

	// We only need to do this if we're posting redirection topics...
	if (isset($_POST['postRedirect']))
	{
		$_POST['reason'] = $smcFunc['htmlspecialchars']($_POST['reason'], ENT_QUOTES);
		preparsecode($_POST['reason']);

		// Add a URL onto the message.
		$reason = strtr($_POST['reason'], array(
			$txt['movetopic_auto_topic'] => '[iurl=' . $scripturl . '?topic=' . $id_topic . '.0]' . $target_subject . '[/iurl]'
		));

		// Automatically remove this MERGED redirection topic in the future?
		$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

		// Redirect to the MERGED topic from topic list?
		$redirect_topic = isset($_POST['redirect_topic']) ? $id_topic : 0;

		foreach ($deleted_topics as $this_old_topic)
		{
			$redirect_subject = sprintf($txt['merged_subject'], $topic_data[$this_old_topic]['subject']);

			$msgOptions = array(
				'icon' => 'moved',
				'subject' => $redirect_subject,
				'body' => $reason,
				'approved' => 1,
			);
			$topicOptions = array(
				'id' => $this_old_topic,
				'is_approved' => true,
				'lock_mode' => 1,
				'board' => $topic_data[$this_old_topic]['board'],
				'mark_as_read' => true,
			);

			// So we have to make the post. We need to do *this* here so we don't foul up indexes later
			// and we have to fix them up later once everything else has happened.
			if (createPost($msgOptions, $topicOptions, $posterOptions))
			{
				$updated_topics[$this_old_topic] = $msgOptions['id'];
			}

			// Update subject search index
			updateStats('subject', $this_old_topic, $redirect_subject);
		}
	}

	// Grab the response prefix (like 'Re: ') in the default forum language.
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

	// Change the topic IDs of all messages that will be merged.  Also adjust subjects if 'enforce subject' was checked.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET
			id_topic = {int:id_topic},
			id_board = {int:target_board}' . (empty($_POST['enforce_subject']) ? '' : ',
			subject = {string:subject}') . '
		WHERE id_topic IN ({array_int:topic_list})' . (!empty($updated_topics) ? '
			AND id_msg NOT IN ({array_int:merge_msg})' : ''),
		array(
			'topic_list' => $topics,
			'id_topic' => $id_topic,
			'merge_msg' => $updated_topics,
			'target_board' => $target_board,
			'subject' => $context['response_prefix'] . $target_subject,
		)
	);

	// Any reported posts should reflect the new board.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET
			id_topic = {int:id_topic},
			id_board = {int:target_board}
		WHERE id_topic IN ({array_int:topics_list})',
		array(
			'topics_list' => $topics,
			'id_topic' => $id_topic,
			'target_board' => $target_board,
		)
	);

	// Change the subject of the first message...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET subject = {string:target_subject}
		WHERE id_msg = {int:first_msg}',
		array(
			'first_msg' => $first_msg,
			'target_subject' => $target_subject,
		)
	);

	// Adjust all calendar events to point to the new topic.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}calendar
		SET
			id_topic = {int:id_topic},
			id_board = {int:target_board}
		WHERE id_topic IN ({array_int:deleted_topics})',
		array(
			'deleted_topics' => $deleted_topics,
			'id_topic' => $id_topic,
			'target_board' => $target_board,
		)
	);

	// Merge log topic entries.
	// The unwatch setting comes from the oldest topic
	$request = $smcFunc['db_query']('', '
		SELECT id_member, MIN(id_msg) AS new_id_msg, unwatched
		FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_member, unwatched',
		array(
			'topics' => $topics,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$replaceEntries = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$replaceEntries[] = array($row['id_member'], $id_topic, $row['new_id_msg'], $row['unwatched']);

		$smcFunc['db_insert']('replace',
			'{db_prefix}log_topics',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'),
			$replaceEntries,
			array('id_member', 'id_topic')
		);
		unset($replaceEntries);

		// Get rid of the old log entries.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
			)
		);
	}
	$smcFunc['db_free_result']($request);

	// Merge topic notifications.
	$notifications = isset($_POST['notifications']) && is_array($_POST['notifications']) ? array_intersect($topics, $_POST['notifications']) : array();
	if (!empty($notifications))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, MAX(sent) AS sent
			FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topics_list})
			GROUP BY id_member',
			array(
				'topics_list' => $notifications,
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			$replaceEntries = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$replaceEntries[] = array($row['id_member'], $id_topic, 0, $row['sent']);

			$smcFunc['db_insert']('replace',
				'{db_prefix}log_notify',
				array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'sent' => 'int'),
				$replaceEntries,
				array('id_member', 'id_topic', 'id_board')
			);
			unset($replaceEntries);

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_topics
				WHERE id_topic IN ({array_int:deleted_topics})',
				array(
					'deleted_topics' => $deleted_topics,
				)
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Get rid of the redundant polls.
	if (!empty($deleted_polls))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}polls
			WHERE id_poll IN ({array_int:deleted_polls})',
			array(
				'deleted_polls' => $deleted_polls,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}poll_choices
			WHERE id_poll IN ({array_int:deleted_polls})',
			array(
				'deleted_polls' => $deleted_polls,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll IN ({array_int:deleted_polls})',
			array(
				'deleted_polls' => $deleted_polls,
			)
		);
	}

	// Cycle through each board...
	foreach ($boardTotals as $id_board => $stats)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET
				num_topics = CASE WHEN {int:topics} > num_topics THEN 0 ELSE num_topics - {int:topics} END,
				unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END,
				num_posts = CASE WHEN {int:posts} > num_posts THEN 0 ELSE num_posts - {int:posts} END,
				unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $id_board,
				'topics' => $stats['topics'],
				'unapproved_topics' => $stats['unapproved_topics'],
				'posts' => $stats['posts'],
				'unapproved_posts' => $stats['unapproved_posts'],
			)
		);
	}

	// Determine the board the final topic resides in
	$request = $smcFunc['db_query']('', '
		SELECT id_board
		FROM {db_prefix}topics
		WHERE id_topic = {int:id_topic}
		LIMIT 1',
		array(
			'id_topic' => $id_topic,
		)
	);
	list($id_board) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Again, only do this if we're redirecting - otherwise delete
	if (isset($_POST['postRedirect']))
	{
		// Having done all that, now make sure we fix the merge/redirect topics upp before we
		// leave here. Specifically: that there are no replies, no unapproved stuff, that the first
		// and last posts are the same and so on and so forth.
		foreach ($updated_topics as $old_topic => $id_msg)
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}topics
				SET id_first_msg = id_last_msg,
					id_member_started = {int:current_user},
					id_member_updated = {int:current_user},
					id_poll = 0,
					approved = 1,
					num_replies = 0,
					unapproved_posts = 0,
					id_redirect_topic = {int:redirect_topic},
					redirect_expires = {int:redirect_expires}
				WHERE id_topic = {int:old_topic}',
				array(
					'current_user' => $user_info['id'],
					'old_topic' => $old_topic,
					'redirect_topic' => $redirect_topic,
					'redirect_expires' => $redirect_expires
				)
			);
		}
	}

	// Ensure we don't accidentally delete the poll we want to keep...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_poll = 0
		WHERE id_topic IN ({array_int:deleted_topics})',
		array(
			'deleted_topics' => $deleted_topics
		)
	);

	// Delete any remaining data regarding these topics, this is done before changing the properties of the merged topic (else we get duplicate keys)...
	if (!isset($_POST['postRedirect']))
	{
		// Remove any remaining info about these topics...
		include_once($sourcedir . '/RemoveTopic.php');
		// We do not need to remove the counts of the deleted topics, as we already removed these.
		removeTopics($deleted_topics, false, true, false);
	}

	// Asssign the properties of the newly merged topic.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			id_board = {int:id_board},
			id_member_started = {int:id_member_started},
			id_member_updated = {int:id_member_updated},
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			id_poll = {int:id_poll},
			num_replies = {int:num_replies},
			unapproved_posts = {int:unapproved_posts},
			num_views = {int:num_views},
			is_sticky = {int:is_sticky},
			approved = {int:approved}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_board' => $target_board,
			'is_sticky' => $is_sticky,
			'approved' => $topic_approved,
			'id_topic' => $id_topic,
			'id_member_started' => $member_started,
			'id_member_updated' => $member_updated,
			'id_first_msg' => $first_msg,
			'id_last_msg' => $last_msg,
			'id_poll' => $target_poll,
			'num_replies' => $num_replies,
			'unapproved_posts' => $num_unapproved,
			'num_views' => $num_views,
		)
	);

	// Update all the statistics.
	updateStats('topic');
	updateStats('subject', $id_topic, $target_subject);
	updateLastMessages($boards);

	logAction('merge', array('topic' => $id_topic, 'board' => $id_board));

	// Notify people that these topics have been merged?
	sendNotifications($id_topic, 'merge');

	// If there's a search index that needs updating, update it...
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'topicMerge')))
		$searchAPI->topicMerge($id_topic, $topics, $affected_msgs, empty($_POST['enforce_subject']) ? null : array($context['response_prefix'], $target_subject));

	// Merging is the sort of thing an external CMS might want to know about
	$merged_topic = array(
		'id_board' => $target_board,
		'is_sticky' => $is_sticky,
		'approved' => $topic_approved,
		'id_topic' => $id_topic,
		'id_member_started' => $member_started,
		'id_member_updated' => $member_updated,
		'id_first_msg' => $first_msg,
		'id_last_msg' => $last_msg,
		'id_poll' => $target_poll,
		'num_replies' => $num_replies,
		'unapproved_posts' => $num_unapproved,
		'num_views' => $num_views,
		'subject' => $target_subject,
	);
	call_integration_hook('integrate_merge_topic', array($merged_topic, $updated_topics, $deleted_topics, $deleted_polls));

	// Send them to the all done page.
	redirectexit('action=mergetopics;sa=done;to=' . $id_topic . ';targetboard=' . $target_board);
}

/**
 * Shows a 'merge completed' screen.
 * is accessed with ?action=mergetopics;sa=done.
 * uses 'merge_done' sub template of the SplitTopics template.
 */
function MergeDone()
{
	global $txt, $context;

	// Make sure the template knows everything...
	$context['target_board'] = (int) $_GET['targetboard'];
	$context['target_topic'] = (int) $_GET['to'];

	$context['page_title'] = $txt['merge'];
	$context['sub_template'] = 'merge_done';
}

?>