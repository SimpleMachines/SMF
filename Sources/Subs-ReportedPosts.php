<?php

/**
 * Perform CRUD actions for reported posts and moderation comments.
 *
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
 * Updates a report with the given parameters.
 *
 * @param string $action The action to perform. Accepts "closed" and "ignore".
 * @param integer $value The new value to update.
 * @params integer $report_id The affected report.
 */
function updateReport($action, $value, $report_id)
{
	global $smcFunc, $user_info, $context;

	// Don't bother.
	if (empty($action)|| empty($report_id) || $action != 'ignore' || $action != 'closed')
		return false;

	// Add the "_all" thingy.
	if ($action == 'ignore')
		$action = 'ignore_all';

	// Update the report...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET  {text:action} = {int:value}
		WHERE id_report = {int:id_report}
			AND ' . $user_info['mod_cache']['bq'],
		array(
			'action' => $action,
			'value' => $value,
			'id_report' => $report_id,
		)
	);

	// Get the board, topic and message for this report
	$request = $smcFunc['db_query']('', '
		SELECT id_board, id_topic, id_msg
		FROM {db_prefix}log_reported
		WHERE id_report = {int:id_report}',
		array(
			'id_report' => $report_id,
		)
	);

	// Set up the data for the log...
	$extra = array('report' => $report_id);
	list ($extra['board'], $extra['topic'], $extra['message']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Tell the user about it.
	$context['report_post_action'] = isset($_GET['ignore']) ? (!empty($_GET['ignore']) ? 'ignore' : 'unignore') : (!empty($_GET['close']) ? 'close' : 'open');

	// Log this action
	logAction($context['report_post_action'] . '_report', $extra);

	// Time to update.
	updateSettings(array('last_mod_report_action' => time()));
	recountOpenReports();
}

function countReports($closed = 0)
{
	global $smcFunc, $user_info;

	$total_reports = 0;

	// How many entries are we viewing?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']),
		array(
			'view_closed' => (int) $closed,
		)
	);
	list ($total_reports) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total_reports;
}

function getReports($closed = 0)
{
	global $smcFunc, $context, $user_info, $scripturl;

	// Lonely, standalone var.
	$reports = array();

	// By George, that means we in a position to get the reports, golly good.
	$request = $smcFunc['db_query']('', '
		SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.closed = {int:view_closed}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
		ORDER BY lr.time_updated DESC
		LIMIT ' . $context['start'] . ', 10',
		array(
			'view_closed' => (int) $closed,
		)
	);

	$report_ids = array();
	$report_boards_ids = array();
	for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i++)
	{
		$report_ids[] = $row['id_report'];
		$report_boards_ids[] = $row['id_board'];
		$reports[$row['id_report']] = array(
			'id' => $row['id_report'],
			'alternate' => $i % 2,
			'topic' => array(
				'id' => $row['id_topic'],
				'id_msg' => $row['id_msg'],
				'id_board' => $row['id_board'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			),
			'report_href' => $scripturl . '?action=moderate;area=reports;sa=details;report=' . $row['id_report'],
			'author' => array(
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
			),
			'comments' => array(),
			'time_started' => timeformat($row['time_started']),
			'last_updated' => timeformat($row['time_updated']),
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body']),
			'num_reports' => $row['num_reports'],
			'closed' => $row['closed'],
			'ignore' => $row['ignore_all']
		);
	}
	$smcFunc['db_free_result']($request);

	// Get the names of boards those topics are in. Slightly faster this way.
	if (!empty($report_boards_ids))
	{
		$report_boards_ids = array_unique($report_boards_ids);
		$board_names = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_board, name
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:boards})',
			array(
				'boards' => $report_boards_ids,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$board_names[$row['id_board']] = $row['name'];

		$smcFunc['db_free_result']($request);

		foreach ($reports as $id_report => $report)
			if (!empty($board_names[$report['topic']['id_board']]))
				$reports[$id_report]['topic']['board_name'] = $board_names[$report['topic']['id_board']];
	}

	// Now get all the people who reported it.
	if (!empty($report_ids))
	{
		$request = $smcFunc['db_query']('', '
			SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment,
				IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lrc.membername) AS reporter
			FROM {db_prefix}log_reported_comments AS lrc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
			WHERE lrc.id_report IN ({array_int:report_list})',
			array(
				'report_list' => $report_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$reports[$row['id_report']]['comments'][] = array(
				'id' => $row['id_comment'],
				'message' => $row['comment'],
				'time' => timeformat($row['time_sent']),
				'member' => array(
					'id' => $row['id_member'],
					'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
					'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				),
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Get the boards where the current user can remove any message.
	$context['report_remove_any_boards'] = $user_info['is_admin'] ? $report_boards_ids : array_intersect($report_boards_ids, boardsAllowedTo('remove_any'));
	$context['report_manage_bans'] = allowedTo('manage_bans');

	return $reports;
}

/**
 * How many open reports do we have?
 */
function recountOpenReports()
{
	global $user_info, $context, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported
		WHERE ' . $user_info['mod_cache']['bq'] . '
			AND closed = {int:not_closed}
			AND ignore_all = {int:not_ignored}',
		array(
			'not_closed' => 0,
			'not_ignored' => 0,
		)
	);
	list ($open_reports) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$_SESSION['rc'] = array(
		'id' => $user_info['id'],
		'time' => time(),
		'reports' => $open_reports,
	);

	return $open_reports;
}

function getReportDetails($report_id)
{
	global $smcFunc, $user_info;

	if (empty($report_id))
		return false;

	// Get the report details, need this so we can limit access to a particular board.
	$request = $smcFunc['db_query']('', '
		SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.id_report = {int:id_report}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
		LIMIT 1',
		array(
			'id_report' => $report_id,
		)
	);

	// So did we find anything?
	if (!$smcFunc['db_num_rows']($request))
		return false;

	// Woohoo we found a report and they can see it!
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $row;
}

function getReportComments($report_id)
{
	global $smcFunc, $scripturl;

	if (empty($report_id))
		return false;

	$report = array(
		'comments' => array(),
		'mod_comments' => array()
	);

	// So what bad things do the reporters have to say about it?
	$request = $smcFunc['db_query']('', '
		SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment, lrc.member_ip,
			IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lrc.membername) AS reporter
		FROM {db_prefix}log_reported_comments AS lrc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
		WHERE lrc.id_report = {int:id_report}',
		array(
			'id_report' => $report_id,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$report['comments'][] = array(
			'id' => $row['id_comment'],
			'message' => strtr($row['comment'], array("\n" => '<br>')),
			'time' => timeformat($row['time_sent']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
				'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'ip' => !empty($row['member_ip']) && allowedTo('moderate_forum') ? '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>' : '',
			),
		);
	}
	$smcFunc['db_free_result']($request);

	// Hang about old chap, any comments from moderators on this one?
	$request = $smcFunc['db_query']('', '
		SELECT lc.id_comment, lc.id_notice, lc.log_time, lc.body,
			IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lc.member_name) AS moderator
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_notice = {int:id_report}
			AND lc.comment_type = {literal:reportc}',
		array(
			'id_report' => $report_id,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$report['mod_comments'][] = array(
			'id' => $row['id_comment'],
			'message' => parse_bbc($row['body']),
			'time' => timeformat($row['log_time']),
			'can_edit' => allowedTo('admin_forum') || (($user_info['id'] == $row['id_member']) && allowedTo('moderate_forum')),
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['moderator'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['moderator'] . '</a>' : $row['moderator'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			),
		);
	}

	$smcFunc['db_free_result']($request);

	return $report;
}

function saveModComment($data)
{
	global $smcFunc, $user_info;

	if (empty($data))
		return false;

	$smcFunc['db_insert']('',
		'{db_prefix}log_comments',
		array(
			'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
			'id_notice' => 'int', 'body' => 'string', 'log_time' => 'int',
		),
		array(
			$user_info['id'], $user_info['name'], 'reportc', '',
			$_REQUEST['report'], $newComment, time(),
		),
		array('id_comment')
	);
	$last_comment = $smcFunc['db_insert_id']('{db_prefix}log_comments', 'id_comment');

	// And get ready to notify people.
	$smcFunc['db_insert']('insert',
		'{db_prefix}background_tasks',
		array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/MsgReportReply-Notify.php', 'MsgReportReply_Notify_Background', serialize(array(
			'report_id' => $_REQUEST['report'],
			'comment_id' => $last_comment,
			'msg_id' => $row['id_msg'],
			'topic_id' => $row['id_topic'],
			'board_id' => $row['id_board'],
			'sender_id' => $user_info['id'],
			'sender_name' => $user_info['name'],
			'time' => time(),
		)), 0),
		array('id_task')
	);
}
?>