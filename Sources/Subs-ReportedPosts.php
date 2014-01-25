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
	global $smcFunc, $context, $user_info;

	$context['start'] = $_GET['start'];

	// How many entries are we viewing?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND ' . ($user_info['mod_cache']['bq'] == '1=1' || $user_info['mod_cache']['bq'] == '0=1' ? $user_info['mod_cache']['bq'] : 'lr.' . $user_info['mod_cache']['bq']),
		array(
			'view_closed' => $closed,
		)
	);
	list ($context['total_reports']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// So, that means we can page index, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reports' . ($closed ? ';sa=closed' : ''), $context['start'], $context['total_reports'], 10);
}

function getReports($closed = 0)
{
	global $smcFunc, $context, $scripturl;

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
			'view_closed' => $context['view_closed'],
		)
	);
	$context['reports'] = array();
	$report_ids = array();
	$report_boards_ids = array();
	for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i++)
	{
		$report_ids[] = $row['id_report'];
		$report_boards_ids[] = $row['id_board'];
		$context['reports'][$row['id_report']] = array(
			'id' => $row['id_report'],
			'alternate' => $i % 2,
			'topic' => array(
				'id' => $row['id_topic'],
				'id_msg' => $row['id_msg'],
				'id_board' => $row['id_board'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			),
			'report_href' => $scripturl . '?action=moderate;area=reports;report=' . $row['id_report'],
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

		foreach ($context['reports'] as $id_report => $report)
			if (!empty($board_names[$report['topic']['id_board']]))
				$context['reports'][$id_report]['topic']['board_name'] = $board_names[$report['topic']['id_board']];
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
			$context['reports'][$row['id_report']]['comments'][] = array(
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

}
?>