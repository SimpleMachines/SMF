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
		'disregard' => 'DisregardReport',
		'details' => 'ReportDetails', // Shows a single report and its comments.
		'handlecomment' => 'HandleComment', // CRUD actions for moderator comments.
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

	// Showing closed or open ones? regardless, turn this to an integer for better handling.
	$context['view_closed'] = (int) isset($_GET['closed']);

	// Call the right template.
	$context['sub_template'] = 'reported_posts';
	$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	$context['total_reports'] = countReports($context['view_closed']);

	// So, that means we can have pagination, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reports;sa=show' . ($context['view_closed'] ? ';closed' : ''), $context['start'], $context['total_reports'], 10);

	// Get the reposts at once!
	$context['reports'] = getReports($context['view_closed']);
}

function ReportDetails()
{
	global $user_info, $context, $sourcedir, $scripturl, $txt;
	global $smcFunc;

	$report = array();
	$reportComments = array();

	// Have to at least give us something to work with.
	if (empty($_REQUEST['report']))
		fatal_lang_error('mc_no_modreport_specified');

	// Integers only please
	$report_id = (int) $_REQUEST['report'];

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
		'report_href' => $scripturl . '?action=moderate;area=reports;report=' . $report['id_report'],
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
		'base_href' => $scripturl . '?action=moderate;area=reports;sa=details;report=' . $context['report']['id'],
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

	// Finally we are done :P
	$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
	$context['sub_template'] = 'viewmodreport';
}

function HandleComment()
{
	// If they are adding a comment then... add a comment.
	if (isset($_POST['add_comment']) && !empty($_POST['mod_comment']))
	{
		checkSession();

		$newComment = trim($smcFunc['htmlspecialchars']($_POST['mod_comment']));

		// In it goes.
		if (!empty($newComment))
		{
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

			// Redirect to prevent double submission.
			redirectexit($scripturl . '?action=moderate;area=reports;report=' . $_REQUEST['report']);
		}
	}
}
?>