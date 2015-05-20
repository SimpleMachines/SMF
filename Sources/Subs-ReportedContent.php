<?php

/**
 * Perform CRUD actions for reported posts and moderation comments.
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
 * Updates a report with the given parameters. Logs each action via logAction()
 *
 * @param string $action The action to perform. Accepts "closed" and "ignore".
 * @param integer $value The new value to update.
 * @params integer|array $report_id The affected report(s).
 */
function updateReport($action, $value, $report_id)
{
	global $smcFunc, $user_info, $context;

	// Don't bother.
	if (empty($action) || empty($report_id))
		return false;

	// Add the "_all" thingy.
	if ($action == 'ignore')
		$action = 'ignore_all';

	// We don't need the board query for reported members
	if ($context['report_type'] == 'members')
	{
		$board_query = '';
	}
	else
	{
		$board_query = ' AND ' . $user_info['mod_cache']['bq'];
	}

	// Update the report...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET  {raw:action} = {string:value}
		'. (is_array($report_id) ? 'WHERE id_report IN ({array_int:id_report})' : 'WHERE id_report = {int:id_report}') .'
			' . $board_query,
		array(
			'action' => $action,
			'value' => $value,
			'id_report' => $report_id,
		)
	);

	// From now on, lets work with arrays, makes life easier.
	$report_id = (array) $report_id;

	// Set up the data for the log...
	$extra = array();

	if ($context['report_type'] == 'posts')
	{
		// Get the board, topic and message for this report
		$request = $smcFunc['db_query']('', '
			SELECT id_board, id_topic, id_msg, id_report
			FROM {db_prefix}log_reported
			WHERE id_report IN ({array_int:id_report})',
			array(
				'id_report' => $report_id,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			$extra[$row['id_report']] = array(
				'report' => $row['id_report'],
				'board' => $row['id_board'],
				'message' => $row['id_msg'],
				'topic' => $row['id_topic'],
			);

		$smcFunc['db_free_result']($request);
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_report, id_member, membername
			FROM {db_prefix}log_reported
			WHERE id_report IN ({array_int:id_report})',
			array(
				'id_report' => $report_id,
			)
		);

		while($row = $smcFunc['db_fetch_assoc']($request))
			$extra[$row['id_report']] = array(
				'report' => $row['id_report'],
				'member' => $row['id_member'],
			);

		$smcFunc['db_free_result']($request);
	}

	// Back to "ignore".
	if ($action == 'ignore_all')
		$action = 'ignore';

	$log_report = $action == 'ignore' ? (!empty($value) ? 'ignore' : 'unignore') : (!empty($value) ? 'close' : 'open');

	if ($context['report_type'] == 'members')
		$log_report .= '_user';

	// Log this action.
	if (!empty($extra))
		foreach ($extra as $report)
			logAction($log_report . '_report', $report);

	// Time to update.
	updateSettings(array('last_mod_report_action' => time()));
	recountOpenReports($context['report_type']);
}

/**
 * Counts how many reports are in total. Used for creating pagination.
 *
 * @param int $closed 1 for counting closed reports, 0 for open ones.
 * @return integer How many reports.

 */
function countReports($closed = 0)
{
	global $smcFunc, $user_info, $context;

	$total_reports = 0;

	// Skip entries with id_board = 0 if we're viewing member reports
	if ($context['report_type'] == 'members')
	{
		$and = 'lr.id_board = 0';
	}
	else
	{
		if ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1')
		{
			$bq = $user_info['mod_cache']['bq'];
		}
		else
		{
			$bq = 'lr.' . $user_info['mod_cache']['bq'];
		}

		$and = $bq . ' AND lr.id_board != 0';
	}

	// How many entries are we viewing?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND ' . $and,
		array(
			'view_closed' => (int) $closed,
		)
	);
	list ($total_reports) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total_reports;
}

/**
 * Get all possible reports the current user can see.
 *
 * @param int $closed 1 for closed reports, 0 for open ones.
 * @return array the reports data with the report ID as key.
 */
function getReports($closed = 0)
{
	global $smcFunc, $context, $user_info, $scripturl;

	// Lonely, standalone var.
	$reports = array();

	// By George, that means we in a position to get the reports, golly good.
	if ($context['report_type'] == 'members')
	{
		$request = $smcFunc['db_query']('', '
			SELECT lr.id_report, lr.id_member,
				lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
				IFNULL(mem.real_name, lr.membername) AS user_name, IFNULL(mem.id_member, 0) AS id_user
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE lr.closed = {int:view_closed}
				AND lr.id_board = 0
			ORDER BY lr.time_updated DESC
			LIMIT ' . $context['start'] . ', 10',
			array(
				'view_closed' => (int) $closed,
			)
		);
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
				lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
				IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE lr.closed = {int:view_closed}
				AND lr.id_board != 0
				AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']) . '
			ORDER BY lr.time_updated DESC
			LIMIT ' . $context['start'] . ', 10',
			array(
				'view_closed' => (int) $closed,
			)
		);
	}

	$report_ids = array();
	$report_boards_ids = array();
	$i = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$report_ids[] = $row['id_report'];
		$reports[$row['id_report']] = array(
			'id' => $row['id_report'],
			'report_href' => $scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $row['id_report'],
			'comments' => array(),
			'time_started' => timeformat($row['time_started']),
			'last_updated' => timeformat($row['time_updated']),
			'num_reports' => $row['num_reports'],
			'closed' => $row['closed'],
			'ignore' => $row['ignore_all']
		);

		if ($context['report_type'] == 'members')
		{
			$extraDetails = array(
				'user' => array(
					'id' => $row['id_user'],
					'name' => $row['user_name'],
					'link' => $row['id_user'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_user'] . '">' . $row['user_name'] . '</a>' : $row['user_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_user'],
				),
			);
		}
		else
		{
			$report_boards_ids[] = $row['id_board'];
			$extraDetails = array(
				'topic' => array(
					'id' => $row['id_topic'],
					'id_msg' => $row['id_msg'],
					'id_board' => $row['id_board'],
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				),
				'author' => array(
					'id' => $row['id_author'],
					'name' => $row['author_name'],
					'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
				),
				'subject' => $row['subject'],
				'body' => parse_bbc($row['body']),
			);
		}

		$reports[$row['id_report']] = array_merge($reports[$row['id_report']], $extraDetails);
		$i++;
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
 * Recount all open reports. Sets a SESSION var with the updated info.
 *
 * @param string the type of reports to count
 * @return int the update open report count.
 */
function recountOpenReports($type)
{
	global $user_info, $smcFunc;

	if ($type == 'members')
		$bq = '';
	else
		$bq = '	AND ' . $user_info['mod_cache']['bq'];

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported
		WHERE closed = {int:not_closed}
			AND ignore_all = {int:not_ignored}
			AND id_board' . ($type == 'members' ? '' : '!') . '= {int:not_a_reported_post}'
		 	. $bq,
		array(
			'not_closed' => 0,
			'not_ignored' => 0,
			'not_a_reported_post' => 0,
		)
	);
	list ($open_reports) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$arr = ($type == 'members' ? 'member_reports' : 'reports');
	$_SESSION['rc'] = array_merge(!empty($_SESSION['rc']) ? $_SESSION['rc'] : array(),
		array(
			'id' => $user_info['id'],
			'time' => time(),
			$arr => $open_reports,
		));

	return $open_reports;
}

/**
 * Gets additional information for a specific report.
 *
 * @param int $report_id The report ID to get the info from.
 * @return array|bool the report data. Boolean false if no report_id was provided.
 */
function getReportDetails($report_id)
{
	global $smcFunc, $user_info, $context;

	if (empty($report_id))
		return false;

	// We don't need all this info if we're only getting user info
	if ($context['report_type'] == 'members')
	{
		$request = $smcFunc['db_query']('', '
			SELECT lr.id_report, lr.id_member,
					lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
					IFNULL(mem.real_name, lr.membername) AS user_name, IFNULL(mem.id_member, 0) AS id_user
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE lr.id_report = {int:id_report}
				AND lr.id_board = 0
			LIMIT 1',
			array(
				'id_report' => $report_id,
			)
		);
	}
	else
	{
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
	}

	// So did we find anything?
	if (!$smcFunc['db_num_rows']($request))
		return false;

	// Woohoo we found a report and they can see it!
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $row;
}

/**
 * Gets both report comments as well as any moderator comment.
 *
 * @param int $report_id The report ID to get the info from.
 * @return array|bool an associative array with 2 keys comments and mod_comments. Boolean false if no report_id was provided.
 */
function getReportComments($report_id)
{
	global $smcFunc, $scripturl, $user_info;

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
			'can_edit' => allowedTo('admin_forum') || (($user_info['id'] == $row['id_member'])),
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

/**
 * Gets specific details about a moderator comment. It also adds a permission for editing/deleting the comment,
 * by default only admins and the author of the comment can edit/delete it.
 *
 * @param int $comment_id The moderator comment ID to get the info from.
 * @return array|bool an array with the fetched data. Boolean false if no report_id was provided.
 */
function getCommentModDetails($comment_id)
{
	global $smcFunc, $user_info;

	$comment = array();

	if (empty($comment_id))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT id_comment, id_notice, log_time, body, id_member
		FROM {db_prefix}log_comments
		WHERE id_comment = {int:id_comment}
			AND comment_type = {literal:reportc}',
		array(
			'id_comment' => $comment_id,
		)
	);

	$comment = $smcFunc['db_fetch_assoc']($request);

	$smcFunc['db_free_result']($request);

	// Add the permission
	if (!empty($comment))
		$comment['can_edit'] = allowedTo('admin_forum') || (($user_info['id'] == $comment['id_member']));

	return $comment;
}

/**
 * Inserts a new moderator comment to the DB.
 *
 * @param int $report_id The report ID is used to fire a notification about the event.
 * @param array $data a formatted array of data to be inserted. Should be already properly sanitized.
 * @return bool  Boolean false if no data was provided.
 */
function saveModComment($report_id, $data)
{
	global $smcFunc, $user_info, $context;

	if (empty($data))
		return false;

	$data = array_merge(array($user_info['id'], $user_info['name'], 'reportc', ''), $data);

	$smcFunc['db_insert']('',
		'{db_prefix}log_comments',
		array(
			'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
			'id_notice' => 'int', 'body' => 'string', 'log_time' => 'int',
		),
		$data,
		array('id_comment')
	);
	$last_comment = $smcFunc['db_insert_id']('{db_prefix}log_comments', 'id_comment');

	$report = getReportDetails($report_id);

	if ($context['report_type'] == 'members')
	{
		$prefix = 'Member';
		$data = array(
		 	'report_id' => $report_id,
			'user_id' => $report['id_user'],
			'user_name' => $report['user_name'],
			'sender_id' => $context['user']['id'],
			'sender_name' => $context['user']['name'],
			'comment_id' => $last_comment,
			'time' => time(),
		);
	}
	else
	{
		$prefix = 'Msg';
		$data = array(
			'report_id' => $report_id,
			'comment_id' => $last_comment,
			'msg_id' => $report['id_msg'],
			'topic_id' => $report['id_topic'],
			'board_id' => $report['id_board'],
			'sender_id' => $user_info['id'],
			'sender_name' => $user_info['name'],
			'time' => time(),
		);
	}

	// And get ready to notify people.
	if (!empty($report))
		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/' . $prefix . 'ReportReply-Notify.php', $prefix . 'ReportReply_Notify_Background', serialize($data), 0),
			array('id_task')
		);
}

/**
 * Saves the new information whenever a moderator comment is edited.
 *
 * @param int $comment_id The edited moderator comment ID.
 * @param array $data The new data to de inserted. Should be already properly sanitized.
 * @return bool  Boolean false if no data or no comment ID was provided.
 */
function editModComment($comment_id, $edited_comment)
{
	global $smcFunc;

	if (empty($comment_id) || empty($edited_comment))
		return false;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_comments
		SET  body = {string:body}
		WHERE id_comment = {int:id_comment}',
		array(
			'body' => $edited_comment,
			'id_comment' => $comment_id,
		)
	);
}

/**
 * Deletes a moderator comment from the DB.
 *
 * @param int $comment_id The moderator comment ID used to identify which report will be deleted.
 * @return bool  Boolean false if no data was provided.
 */
function deleteModComment($comment_id)
{
	global $smcFunc;

	if (empty($comment_id))
		return false;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_comments
		WHERE id_comment = {int:comment_id}',
		array(
			'comment_id' => $comment_id,
		)
	);

}

?>