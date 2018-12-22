<?php

/**
 * Handles reported members and posts, as well as moderation comments.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Sets and call a function based on the given subaction. Acts as a dispatcher function.
 * It requires the moderate_forum permission.
 *
 * @uses ModerationCenter template.
 * @uses ModerationCenter language file.
 *
 */
function ReportedContent()
{
	global $txt, $context, $user_info, $smcFunc;
	global $sourcedir;

	// First order of business - what are these reports about?
	// area=reported{type}
	$context['report_type'] = substr($_GET['area'], 8);

	loadLanguage('ModerationCenter');
	loadTemplate('ReportedContent');

	// We need this little rough gem.
	require_once($sourcedir . '/Subs-ReportedContent.php');

	// Do we need to show a confirmation message?
	$context['report_post_action'] = !empty($_SESSION['rc_confirmation']) ? $_SESSION['rc_confirmation'] : array();
	unset($_SESSION['rc_confirmation']);

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_' . $context['report_type']];

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_reported_' . $context['report_type']],
		'help' => '',
		'description' => $txt['mc_reported_' . $context['report_type'] . '_desc'],
	);

	// This comes under the umbrella of moderating posts.
	if ($context['report_type'] == 'members' || $user_info['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	$subActions = array(
		'show' => 'ShowReports',
		'closed' => 'ShowClosedReports',
		'handle' => 'HandleReport', // Deals with closing/opening reports.
		'details' => 'ReportDetails', // Shows a single report and its comments.
		'handlecomment' => 'HandleComment', // CRUD actions for moderator comments.
		'editcomment' => 'EditComment',
	);

	// Go ahead and add your own sub-actions.
	call_integration_hook('integrate_reported_' . $context['report_type'], array(&$subActions));

	// By default we call the open sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_REQUEST['sa']), ENT_QUOTES);

	else
		$context['sub_action'] = 'show';

	// Hi Ho Silver Away!
	call_helper($subActions[$context['sub_action']]);
}

/**
 * Shows all currently open reported posts.
 * Handles closing multiple reports
 *
 */
function ShowReports()
{
	global $context, $scripturl;

	// Showing closed or open ones? regardless, turn this to an integer for better handling.
	$context['view_closed'] = 0;

	// Call the right template.
	$context['sub_template'] = 'reported_' . $context['report_type'];
	$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	$context['total_reports'] = countReports($context['view_closed']);

	// Just how many items are we showing per page?
	$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=show', $context['start'], $context['total_reports'], $context['reports_how_many']);

	// Get the reports at once!
	$context['reports'] = getReports($context['view_closed']);

	// Are we closing multiple reports?
	if (isset($_POST['close']) && isset($_POST['close_selected']))
	{
		checkSession('post');
		validateToken('mod-report-close-all');

		// All the ones to update...
		$toClose = array();
		foreach ($_POST['close'] as $rid)
			$toClose[] = (int) $rid;

		if (!empty($toClose))
			updateReport('closed', 1, $toClose);

		// Set the confirmation message.
		$_SESSION['rc_confirmation'] = 'close_all';

		// Force a page refresh.
		redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type']);
	}

	createToken('mod-report-close-all');
	createToken('mod-report-ignore', 'get');
	createToken('mod-report-closed', 'get');
}

/**
 * Shows all currently closed reported posts.
 *
 */
function ShowClosedReports()
{
	global $context, $scripturl;

	// Showing closed ones.
	$context['view_closed'] = 1;

	// Call the right template.
	$context['sub_template'] = 'reported_' . $context['report_type'];
	$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	$context['total_reports'] = countReports($context['view_closed']);

	// Just how many items are we showing per page?
	$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=closed', $context['start'], $context['total_reports'], $context['reports_how_many']);

	// Get the reports at once!
	$context['reports'] = getReports($context['view_closed']);

	createToken('mod-report-ignore', 'get');
	createToken('mod-report-closed', 'get');
}

/**
 * Shows detailed information about a report. such as report comments and moderator comments.
 * Shows a list of moderation actions for the specific report.
 *
 */
function ReportDetails()
{
	global $context, $sourcedir, $scripturl, $txt;

	// Have to at least give us something to work with.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please
	$report_id = (int) $_REQUEST['rid'];

	// Get the report details.
	$report = getReportDetails($report_id);

	if (!$report)
		fatal_lang_error('mc_no_modreport_found');

	// Build the report data - basic details first, then extra stuff based on the type
	$context['report'] = array(
		'id' => $report['id_report'],
		'report_href' => $scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';rid=' . $report['id_report'],
		'comments' => array(),
		'mod_comments' => array(),
		'time_started' => timeformat($report['time_started']),
		'last_updated' => timeformat($report['time_updated']),
		'num_reports' => $report['num_reports'],
		'closed' => $report['closed'],
		'ignore' => $report['ignore_all']
	);

	// Different reports have different "extra" data attached to them
	if ($context['report_type'] == 'members')
	{
		$extraDetails = array(
			'user' => array(
				'id' => $report['id_user'],
				'name' => $report['user_name'],
				'link' => $report['id_user'] ? '<a href="' . $scripturl . '?action=profile;u=' . $report['id_user'] . '">' . $report['user_name'] . '</a>' : $report['user_name'],
				'href' => $scripturl . '?action=profile;u=' . $report['id_user'],
			),
		);
	}
	else
	{
		$extraDetails = array(
			'topic_id' => $report['id_topic'],
			'board_id' => $report['id_board'],
			'message_id' => $report['id_msg'],
			'message_href' => $scripturl . '?msg=' . $report['id_msg'],
			'message_link' => '<a href="' . $scripturl . '?msg=' . $report['id_msg'] . '">' . $report['subject'] . '</a>',
			'author' => array(
				'id' => $report['id_author'],
				'name' => $report['author_name'],
				'link' => $report['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $report['id_author'] . '">' . $report['author_name'] . '</a>' : $report['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $report['id_author'],
			),
			'subject' => $report['subject'],
			'body' => parse_bbc($report['body']),
		);
	}

	$context['report'] = array_merge($context['report'], $extraDetails);

	$reportComments = getReportComments($report_id);

	if (!empty($reportComments))
		$context['report'] = array_merge($context['report'], $reportComments);

	// What have the other moderators done to this message?
	require_once($sourcedir . '/Modlog.php');
	require_once($sourcedir . '/Subs-List.php');
	loadLanguage('Modlog');

	// Parameters are slightly different depending on what we're doing here...
	if ($context['report_type'] == 'members')
	{
		// Find their ID in the serialized action string...
		$user_id_length = strlen((string) $context['report']['user']['id']);
		$member = 's:6:"member";s:' . $user_id_length . ':"' . $context['report']['user']['id'] . '";}';

		$params = array(
			'lm.extra LIKE {raw:member}
				AND lm.action LIKE {raw:report}',
			array('member' => '\'%' . $member . '\'', 'report' => '\'%_user_report\''),
			1,
			true,
		);
	}
	else
	{
		$params = array(
			'lm.id_topic = {int:id_topic}
				AND lm.id_board != {int:not_a_reported_post}',
			array('id_topic' => $context['report']['topic_id'], 'not_a_reported_post' => 0),
			1,
		);
	}

	// This is all the information from the moderation log.
	$listOptions = array(
		'id' => 'moderation_actions_list',
		'title' => $txt['mc_modreport_modactions'],
		'items_per_page' => 15,
		'no_items_label' => $txt['modlog_no_entries_found'],
		'base_href' => $scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $context['report']['id'],
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getModLogEntries',
			'params' => $params,
		),
		'get_count' => array(
			'function' => 'list_getModLogEntryCount',
			'params' => $params,
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['modlog_action'],
				),
				'data' => array(
					'db' => 'action_text',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.action',
					'reverse' => 'lm.action DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['modlog_date'],
				),
				'data' => array(
					'db' => 'time',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.log_time',
					'reverse' => 'lm.log_time DESC',
				),
			),
			'moderator' => array(
				'header' => array(
					'value' => $txt['modlog_member'],
				),
				'data' => array(
					'db' => 'moderator_link',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'position' => array(
				'header' => array(
					'value' => $txt['modlog_position'],
				),
				'data' => array(
					'db' => 'position',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['modlog_ip'],
				),
				'data' => array(
					'db' => 'ip',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.ip',
					'reverse' => 'lm.ip DESC',
				),
			),
		),
	);

	// Create the watched user list.
	createList($listOptions);

	// Make sure to get the correct tab selected.
	if ($context['report']['closed'])
		$context[$context['moderation_menu_name']]['current_subsection'] = 'closed';

	// Finally we are done :P
	if ($context['report_type'] == 'members')
	{
		$context['page_title'] = sprintf($txt['mc_viewmemberreport'], $context['report']['user']['name']);
		$context['sub_template'] = 'viewmemberreport';
	}
	else
	{
		$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
		$context['sub_template'] = 'viewmodreport';
	}

	createToken('mod-reportC-add');
	createToken('mod-reportC-delete', 'get');

	// We can "un-ignore" and close a report from here so add their respective tokens.
	createToken('mod-report-ignore', 'get');
	createToken('mod-report-closed', 'get');
}

/**
 * Creates/Deletes moderator comments.
 *
 */
function HandleComment()
{
	global $smcFunc, $scripturl, $user_info, $context;

	// The report ID is a must.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please.
	$report_id = (int) $_REQUEST['rid'];

	// If they are adding a comment then... add a comment.
	if (isset($_POST['add_comment']) && !empty($_POST['mod_comment']))
	{
		checkSession();
		validateToken('mod-reportC-add');

		$new_comment = trim($smcFunc['htmlspecialchars']($_POST['mod_comment']));

		saveModComment($report_id, array($report_id, $new_comment, time()));

		// Everything went better than expected!
		$_SESSION['rc_confirmation'] = 'message_saved';
	}

	// Deleting a comment?
	if (isset($_REQUEST['delete']) && isset($_REQUEST['mid']))
	{
		checkSession('get');
		validateToken('mod-reportC-delete', 'get');

		if (empty($_REQUEST['mid']))
			fatal_lang_error('mc_reportedp_comment_none_found');

		$comment_id = (int) $_REQUEST['mid'];

		// We need to verify some data, so lets load the comment details once more!
		$comment = getCommentModDetails($comment_id);

		// Perhaps somebody else already deleted this fine gem...
		if (empty($comment))
			fatal_lang_error('report_action_message_delete_issue');

		// Can you actually do this?
		$comment_owner = $user_info['id'] == $comment['id_member'];

		// Nope! sorry.
		if (!allowedTo('admin_forum') && !$comment_owner)
			fatal_lang_error('report_action_message_delete_cannot');

		// All good!
		deleteModComment($comment_id);

		// Tell them the message was deleted.
		$_SESSION['rc_confirmation'] = 'message_deleted';
	}

	//Redirect to prevent double submission.
	redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $report_id);
}

/**
 * Shows a textarea for editing a moderator comment.
 * Handles the edited comment and stores it on the DB.
 *
 */
function EditComment()
{
	global $smcFunc, $context, $txt, $scripturl, $user_info;

	checkSession(isset($_REQUEST['save']) ? 'post' : 'get');

	// The report ID is a must.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	if (empty($_REQUEST['mid']))
		fatal_lang_error('mc_reportedp_comment_none_found');

	// Integers only please.
	$context['report_id'] = (int) $_REQUEST['rid'];
	$context['comment_id'] = (int) $_REQUEST['mid'];

	$context['comment'] = getCommentModDetails($context['comment_id']);

	if (empty($context['comment']))
		fatal_lang_error('mc_reportedp_comment_none_found');

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_posts'];
	$context['sub_template'] = 'edit_comment';

	if (isset($_REQUEST['save']) && isset($_POST['edit_comment']) && !empty($_POST['mod_comment']))
	{
		validateToken('mod-reportC-edit');

		// Make sure there is some data to edit on the DB.
		if (empty($context['comment']))
			fatal_lang_error('report_action_message_edit_issue');

		// Still there, good, now lets see if you can actually edit it...
		$comment_owner = $user_info['id'] == $context['comment']['id_member'];

		// So, you aren't neither an admin or the comment owner huh? that's too bad.
		if (!allowedTo('admin_forum') && !$comment_owner)
			fatal_lang_error('report_action_message_edit_cannot');

		// All good!
		$edited_comment = trim($smcFunc['htmlspecialchars']($_POST['mod_comment']));

		editModComment($context['comment_id'], $edited_comment);

		$_SESSION['rc_confirmation'] = 'message_edited';

		redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $context['report_id']);
	}

	createToken('mod-reportC-edit');
}

/**
 * Performs closing/ignoring actions for a given report.
 *
 */
function HandleReport()
{
	global $scripturl, $context;

	checkSession('get');

	// We need to do something!
	if (empty($_GET['rid']) && (!isset($_GET['ignore']) || !isset($_GET['closed'])))
		fatal_lang_error('mc_reportedp_none_found');

	// What are we gonna do?
	$action = isset($_GET['ignore']) ? 'ignore' : 'closed';

	validateToken('mod-report-' . $action, 'get');

	// Are we ignore or "un-ignore"? "un-ignore" that's a funny word!
	$value = (int) $_GET[$action];

	// Figuring out.
	$message = $action == 'ignore' ? ($value ? 'ignore' : 'unignore') : ($value ? 'close' : 'open');

	// Integers only please.
	$report_id = (int) $_REQUEST['rid'];

	// Update the DB entry
	updateReport($action, $value, $report_id);

	// So, time to show a confirmation message, lets do some trickery!
	$_SESSION['rc_confirmation'] = $message;

	// Done!
	redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type']);
}

?>