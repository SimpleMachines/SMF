<?php

/**
 * The functions in this file deal with sending topics to a friend or moderator
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Report a post to the moderator... ask for a comment.
 * Gathers data from the user to report abuse to the moderator(s).
 * Uses the ReportToModerator template, main sub template.
 * Requires the report_any permission.
 * Uses ReportToModerator2() if post data was sent.
 * Accessed through ?action=reporttm.
 */
function ReportToModerator()
{
	global $txt, $topic, $context, $smcFunc;

	$context['robot_no_index'] = true;

	// No guests!
	is_not_guest();

	// You can't use this if it's off or you are not allowed to do it.
	isAllowedTo('report_any');

	// If they're posting, it should be processed by ReportToModerator2.
	if ((isset($_POST[$context['session_var']]) || isset($_POST['save'])) && empty($context['post_errors']))
		ReportToModerator2();

	// We need a message ID to check!
	if (empty($_REQUEST['msg']) && empty($_REQUEST['mid']))
		fatal_lang_error('no_access', false);

	// For compatibility, accept mid, but we should be using msg. (not the flavor kind!)
	$_REQUEST['msg'] = empty($_REQUEST['msg']) ? (int) $_REQUEST['mid'] : (int) $_REQUEST['msg'];

	// Check the message's ID - don't want anyone reporting a post they can't even see!
	$result = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.id_member, t.id_member_started
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		)
	);
	if ($smcFunc['db_num_rows']($result) == 0)
		fatal_lang_error('no_board', false);
	list ($_REQUEST['msg'], $member, $starter) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Show the inputs for the comment, etc.
	loadLanguage('Post');
	loadTemplate('SendTopic');

	addInlineJavascript('
	var error_box = $("#error_box");
	$("#report_comment").keyup(function() {
		var post_too_long = $("#error_post_too_long");
		if ($(this).val().length > 254)
		{
			if (post_too_long.length == 0)
			{
				error_box.show();
				if ($.trim(error_box.html()) == \'\')
					error_box.append("<ul id=\'error_list\'></ul>");

				$("#error_list").append("<li id=\'error_post_too_long\' class=\'error\'>" + ' . JavaScriptEscape($txt['post_too_long']) . ' + "</li>");
			}
		}
		else
		{
			post_too_long.remove();
			if ($("#error_list li").length == 0)
				error_box.hide();
		}
	});', true);

	$context['comment_body'] = !isset($_POST['comment']) ? '' : trim($_POST['comment']);

	// This is here so that the user could, in theory, be redirected back to the topic.
	$context['start'] = $_REQUEST['start'];
	$context['message_id'] = $_REQUEST['msg'];

	$context['page_title'] = $txt['report_to_mod'];
	$context['sub_template'] = 'report';
}

/**
 * Send the emails.
 * Sends off emails to all the moderators.
 * Sends to administrators and global moderators. (1 and 2)
 * Called by ReportToModerator(), and thus has the same permission and setting requirements as it does.
 * Accessed through ?action=reporttm when posting.
 */
function ReportToModerator2()
{
	global $txt, $topic, $user_info, $modSettings, $sourcedir, $context, $smcFunc;

	// Sorry, no guests allowed... Probably just trying to spam us anyway
	is_not_guest();

	// You must have the proper permissions!
	isAllowedTo('report_any');

	// Make sure they aren't spamming.
	spamProtection('reporttm');

	require_once($sourcedir . '/Subs-Post.php');

	// No errors, yet.
	$post_errors = array();

	// Check their session.
	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	// Make sure we have a comment and it's clean.
	if (!isset($_POST['comment']) || $smcFunc['htmltrim']($_POST['comment']) === '')
		$post_errors[] = 'no_comment';
	$poster_comment = strtr($smcFunc['htmlspecialchars']($_POST['comment']), array("\r" => '', "\t" => ''));

	if ($smcFunc['strlen']($poster_comment) > 254)
		$post_errors[] = 'post_too_long';

	// Guests need to provide their address!
	if ($user_info['is_guest'])
	{
		$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);
		if ($_POST['email'] === '')
			$post_errors[] = 'no_email';
		elseif (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_POST['email']) == 0)
			$post_errors[] = 'bad_email';

		isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));

		$user_info['email'] = $smcFunc['htmlspecialchars']($_POST['email']);
	}

	// Could they get the right verification code?
	if ($user_info['is_guest'] && !empty($modSettings['guests_report_require_captcha']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'report',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);
		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}

	// Any errors?
	if (!empty($post_errors))
	{
		loadLanguage('Errors');

		$context['post_errors'] = array();
		foreach ($post_errors as $post_error)
			$context['post_errors'][$post_error] = $txt['error_' . $post_error];

		return ReportToModerator();
	}

	// Get the basic topic information, and make sure they can see it.
	$_POST['msg'] = (int) $_POST['msg'];

	$request = $smcFunc['db_query']('', '
		SELECT m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_POST['msg'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board', false);
	$message = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$poster_name = un_htmlspecialchars($message['real_name']) . ($message['real_name'] != $message['poster_name'] ? ' (' . $message['poster_name'] . ')' : '');
	$reporterName = un_htmlspecialchars($user_info['name']) . ($user_info['name'] != $user_info['username'] && $user_info['username'] != '' ? ' (' . $user_info['username'] . ')' : '');
	$subject = un_htmlspecialchars($message['subject']);

	$request = $smcFunc['db_query']('', '
		SELECT id_report, ignore_all
		FROM {db_prefix}log_reported
		WHERE id_msg = {int:id_msg}
			AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
		ORDER BY ignore_all DESC',
		array(
			'id_msg' => $_POST['msg'],
			'not_closed' => 0,
			'ignored' => 1,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 0)
		list ($id_report, $ignore) = $smcFunc['db_fetch_row']($request);

	$smcFunc['db_free_result']($request);

	// If we're just going to ignore these, then who gives a monkeys...
	if (!empty($ignore))
		redirectexit('topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);

	// Already reported? My god, we could be dealing with a real rogue here...
	if (!empty($id_report))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_reported
			SET num_reports = num_reports + 1, time_updated = {int:current_time}
			WHERE id_report = {int:id_report}',
			array(
				'current_time' => time(),
				'id_report' => $id_report,
			)
		);
	// Otherwise, we shall make one!
	else
	{
		if (empty($message['real_name']))
			$message['real_name'] = $message['poster_name'];

		$smcFunc['db_insert']('',
			'{db_prefix}log_reported',
			array(
				'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
				'num_reports' => 'int', 'closed' => 'int',
			),
			array(
				$_POST['msg'], $message['id_topic'], $message['id_board'], $message['id_poster'], $message['real_name'],
				$message['subject'], $message['body'] , time(), time(), 1, 0,
			),
			array('id_report')
		);
		$id_report = $smcFunc['db_insert_id']('{db_prefix}log_reported', 'id_report');
	}

	// Now just add our report...
	if ($id_report)
	{
		$smcFunc['db_insert']('',
			'{db_prefix}log_reported_comments',
			array(
				'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'member_ip' => 'string', 'comment' => 'string', 'time_sent' => 'int',
			),
			array(
				$id_report, $user_info['id'], $user_info['name'],
				$user_info['ip'], $poster_comment, time(),
			),
			array('id_comment')
		);

		// And get ready to notify people.
		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/MsgReport-Notify.php', 'MsgReport_Notify_Background', serialize(array(
				'report_id' => $id_report,
				'msg_id' => $_POST['msg'],
				'topic_id' => $message['id_topic'],
				'board_id' => $message['id_board'],
				'sender_id' => $context['user']['id'],
				'sender_name' => $context['user']['name'],
				'time' => time(),
			)), 0),
			array('id_task')
		);
	}

	// Keep track of when the mod reports get updated, that way we know when we need to look again.
	updateSettings(array('last_mod_report_action' => time()));

	// Back to the post we reported!
	redirectexit('reportsent;topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);
}

?>