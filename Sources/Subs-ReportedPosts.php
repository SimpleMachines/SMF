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

?>