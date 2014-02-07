<?php

/**
 * Handles reported posts and moderation comments.
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
 * Sets and call a function based on the given subaction.
 * It requires the moderate_forum permission.
 *
 * @uses ModerationCenter template.
 * @uses ModerationCenter language file.
 *
 */
function ReportedPosts()
{
	global $txt, $context, $scripturl, $user_info, $smcFunc;
	global $sourcedir;

	loadLanguage('ModerationCenter');
	loadTemplate('ReportedPosts');

	// We need this little rough gem.
	require_once($sourcedir . '/Subs-ReportedPosts.php');

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_posts'];
	$context['sub_template'] = 'reported_posts';

	// This comes under the umbrella of moderating posts.
	if ($user_info['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	$sub_actions = array(
		'show' => 'ShowReports', // Both open and closed reports
		'handle' => 'HandleReport', // Deals with closing/opening reports.
		'details' => 'ReportDetails', // Shows a single report and its comments.
		'handlecomment' => 'HandleComment', // CRUD actions for moderator comments.
		'editcomment' => 'EditComment',
	);

	// Go ahead and add your own sub-actions.
	call_integration_hook('integrate_reported_posts', array(&$sub_actions));

	// By default we call the open sub-action.
	if (isset($_REQUEST['sa']) && isset($sub_actions[$_REQUEST['sa']]))
		$context['sub_action'] = $smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_REQUEST['sa']), ENT_QUOTES);

	else
		$context['sub_action'] = 'show';

	// Call the function!
	$sub_actions[$context['sub_action']]();
}

/**
 * Shows all open or closed reported posts.
 * It requires the moderate_forum permission.
 *
 * @uses ModerationCenter template.
 * @uses ModerationCenter language file.
 *
 */
function ShowReports()
{
	global $context, $txt, $scripturl;

	// Showing closed or open ones? regardless, turn this to an integer for better handling.
	$context['view_closed'] = (int) isset($_GET['closed']);

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_reported_posts'],
		'help' => '',
		'description' => $txt['mc_reported_posts_desc'],
		'tabs' => array(
			'show' => array($txt['mc_reportedp_active']),
			'show;closed' => array($txt['mc_reportedp_closed']),
		),
	);

	// Call the right template.
	$context['sub_template'] = 'reported_posts';
	$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	$context['total_reports'] = countReports($context['view_closed']);

	// Just how many items are we showing per page?
	$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reports;sa=show' . ($context['view_closed'] ? ';closed' : ''), $context['start'], $context['total_reports'], $context['reports_how_many']);

	// Get the reports at once!
	$context['reports'] = getReports($context['view_closed']);

	// Are we closing multiple reports?
	if (isset($_POST['close']) && isset($_POST['close_selected']))
	{
		checkSession('post');

		// All the ones to update...
		$toClose = array();
		foreach ($_POST['close'] as $rid)
			$toClose[] = (int) $rid;

		if (!empty($toClose))
			updateReport('closed', 1, $toClose);
	}

	// Show a confirmation if the user wants to disregard a report.
	if (!$context['view_closed'])
		addInlineJavascript('
	$(\'.report_ignore\').on(\'click\', function(){
		// Need to make sure to only show this when ignoring.
		if ($(this).data(\'ignore\') == \'1\'){
			return confirm('. JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) .');
		}
	});', true);
}

function ReportDetails()
{
	global $user_info, $context, $sourcedir, $scripturl, $txt;
	global $smcFunc;

	$report = array();
	$reportComments = array();

	// Have to at least give us something to work with.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please
	$report_id = (int) $_REQUEST['rid'];

	// Get the report details.
	$report = getReportDetails($report_id);

	if(!$report)
		fatal_lang_error('mc_no_modreport_found');

	// Build the report data.
	$context['report'] = array(
		'id' => $report['id_report'],
		'topic_id' => $report['id_topic'],
		'board_id' => $report['id_board'],
		'message_id' => $report['id_msg'],
		'message_href' => $scripturl . '?msg=' . $report['id_msg'],
		'message_link' => '<a href="' . $scripturl . '?msg=' . $report['id_msg'] . '">' . $report['subject'] . '</a>',
		'report_href' => $scripturl . '?action=moderate;area=reports;rid=' . $report['id_report'],
		'author' => array(
			'id' => $report['id_author'],
			'name' => $report['author_name'],
			'link' => $report['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $report['id_author'] . '">' . $report['author_name'] . '</a>' : $report['author_name'],
			'href' => $scripturl . '?action=profile;u=' . $report['id_author'],
		),
		'comments' => array(),
		'mod_comments' => array(),
		'time_started' => timeformat($report['time_started']),
		'last_updated' => timeformat($report['time_updated']),
		'subject' => $report['subject'],
		'body' => parse_bbc($report['body']),
		'num_reports' => $report['num_reports'],
		'closed' => $report['closed'],
		'ignore' => $report['ignore_all']
	);

	$reportComments = getReportComments($report_id);

	if (!empty($reportComments))
		$context['report'] = array_merge($context['report'], $reportComments);

	// What have the other moderators done to this message?
	require_once($sourcedir . '/Modlog.php');
	require_once($sourcedir . '/Subs-List.php');
	loadLanguage('Modlog');

	// This is all the information from the moderation log.
	$listOptions = array(
		'id' => 'moderation_actions_list',
		'title' => $txt['mc_modreport_modactions'],
		'items_per_page' => 15,
		'no_items_label' => $txt['modlog_no_entries_found'],
		'base_href' => $scripturl . '?action=moderate;area=reports;sa=details;rid=' . $context['report']['id'],
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getModLogEntries',
			'params' => array(
				'lm.id_topic = {int:id_topic}',
				array('id_topic' => $context['report']['topic_id']),
				1,
			),
		),
		'get_count' => array(
			'function' => 'list_getModLogEntryCount',
			'params' => array(
				'lm.id_topic = {int:id_topic}',
				array('id_topic' => $context['report']['topic_id']),
				1,
			),
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

	addInlineJavascript('
	$(\'.deleteModComment\').on(\'click\', function() {
		return confirm('. (JavaScriptEscape($txt['mc_reportedp_delete_confirm'])) .');
});', true);

	// Finally we are done :P
	$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
	$context['sub_template'] = 'viewmodreport';
}

function HandleComment()
{
	global $smcFunc, $scripturl;

	// The report ID is a must.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please.
	$report_id = (int) $_REQUEST['rid'];

	// If they are adding a comment then... add a comment.
	if (isset($_POST['add_comment']) && !empty($_POST['mod_comment']))
	{
		checkSession();

		$new_comment = trim($smcFunc['htmlspecialchars']($_POST['mod_comment']));

		saveModComment($report_id, array($report_id, $new_comment, time()));
	}

	// Deleting a comment?
	if (isset($_REQUEST['delete']) && isset($_REQUEST['mid']))
	{
		if (empty($_REQUEST['mid']))
			fatal_lang_error('mc_reportedp_comment_none_found');

		$comment_id = (int) $_REQUEST['mid'];

		deleteModComment($comment_id);
	}

	//Redirect to prevent double submission.
	redirectexit($scripturl . '?action=moderate;area=reports;sa=details;rid=' . $report_id);
}

function EditComment()
{
	global $smcFunc, $context, $txt, $scripturl;

	$comment = array();

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
		$edited_comment = trim($smcFunc['htmlspecialchars']($_POST['mod_comment']));

		editModComment($context['comment_id'], $edited_comment);

		redirectexit($scripturl . '?action=moderate;area=reports;sa=details;rid=' . $context['report_id']);
	}
}

function HandleReport()
{
	global $scripturl;

	checkSession('get');

	// We need to do something!
	if (empty($_GET['rid']) && (!isset($_GET['ignore']) || !isset($_GET['closed'])))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please.
	$report_id = (int) $_REQUEST['rid'];

	// What are we gonna do?
	$action = isset($_GET['ignore']) ? 'ignore' : 'closed';

	// Are we disregarding or "un-disregarding"? "un-disregarding" thats a funny word!
	$value = (int) $_GET[$action];

	// Update the DB entry
	updateReport($action, $value, $report_id);

	// Done!
	redirectexit($scripturl . '?action=moderate;area=reports');
}
?>