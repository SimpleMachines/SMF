<?php

/**
 * Handles reported members and posts, as well as moderation comments.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Sets and call a function based on the given subaction. Acts as a dispatcher function.
 * It requires the moderate_forum permission.
 *
 * Uses ModerationCenter template.
 * Uses ModerationCenter language file.
 *
 */
function ReportedContent()
{
	// First order of business - what are these reports about?
	// area=reported{type}
	Utils::$context['report_type'] = substr($_GET['area'], 8);

	Lang::load('ModerationCenter');
	Theme::loadTemplate('ReportedContent');

	// We need this little rough gem.
	require_once(Config::$sourcedir . '/Subs-ReportedContent.php');

	// Do we need to show a confirmation message?
	Utils::$context['report_post_action'] = !empty($_SESSION['rc_confirmation']) ? $_SESSION['rc_confirmation'] : array();
	unset($_SESSION['rc_confirmation']);

	// Set up the comforting bits...
	Utils::$context['page_title'] = Lang::$txt['mc_reported_' . Utils::$context['report_type']];

	// Put the open and closed options into tabs, because we can...
	Menu::$loaded['moderate']->tab_data = array(
		'title' => Lang::$txt['mc_reported_' . Utils::$context['report_type']],
		'help' => '',
		'description' => Lang::$txt['mc_reported_' . Utils::$context['report_type'] . '_desc'],
	);

	// This comes under the umbrella of moderating posts.
	if (Utils::$context['report_type'] == 'members' || User::$me->mod_cache['bq'] == '0=1')
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
	call_integration_hook('integrate_reported_' . Utils::$context['report_type'], array(&$subActions));

	// By default we call the open sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		Utils::$context['sub_action'] = Utils::htmlTrim(Utils::htmlspecialchars($_REQUEST['sa']), ENT_QUOTES);

	else
		Utils::$context['sub_action'] = 'show';

	// Hi Ho Silver Away!
	call_helper($subActions[Utils::$context['sub_action']]);

	// If we're showing a list of reports
	if (Utils::$context['sub_action'] == 'show' || Utils::$context['sub_action'] == 'closed')
	{
		// Quickbuttons for each report
		foreach (Utils::$context['reports'] as $key => $report)
		{
			Utils::$context['reports'][$key]['quickbuttons'] = array(
				'details' => array(
					'label' => Lang::$txt['mc_reportedp_details'],
					'href' => $report['report_href'],
					'icon' => 'details',
				),
				'ignore' => array(
					'label' => $report['ignore'] ? Lang::$txt['mc_reportedp_unignore'] : Lang::$txt['mc_reportedp_ignore'],
					'href' => Config::$scripturl.'?action=moderate;area=reported'.Utils::$context['report_type'].';sa=handle;ignore='.(int)!$report['ignore'].';rid='.$report['id'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-ignore_token_var'].'='.Utils::$context['mod-report-ignore_token'],
					'javascript' => !$report['ignore'] ? ' data-confirm="' . Lang::$txt['mc_reportedp_ignore_confirm'] . '"' : '',
					'class' => 'you_sure',
					'icon' => 'ignore'
				),
				'close' => array(
					'label' => Utils::$context['view_closed'] ? Lang::$txt['mc_reportedp_open'] : Lang::$txt['mc_reportedp_close'],
					'href' => Config::$scripturl.'?action=moderate;area=reported'.Utils::$context['report_type'].';sa=handle;closed='.(int)!$report['closed'].';rid='.$report['id'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-closed_token_var'].'='.Utils::$context['mod-report-closed_token'],
					'icon' => Utils::$context['view_closed'] ? 'folder' : 'close',
				),
			);

			// Only reported posts can be deleted
			if (Utils::$context['report_type'] == 'posts')
				Utils::$context['reports'][$key]['quickbuttons']['delete'] = array(
					'label' => Lang::$txt['mc_reportedp_delete'],
					'href' => Config::$scripturl.'?action=deletemsg;topic='.$report['topic']['id'].'.0;msg='.$report['topic']['id_msg'].';modcenter;'.Utils::$context['session_var'].'='.Utils::$context['session_id'],
					'javascript' => 'data-confirm="'.Lang::$txt['mc_reportedp_delete_confirm'].'"',
					'class' => 'you_sure',
					'icon' => 'delete',
					'show' => !$report['closed'] && (is_array(Utils::$context['report_remove_any_boards']) && in_array($report['topic']['id_board'], Utils::$context['report_remove_any_boards']))
				);

			// Ban reported member/post author link
			if (Utils::$context['report_type'] == 'members')
				$ban_link = Config::$scripturl.'?action=admin;area=ban;sa=add;u='.$report['user']['id'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'];
			else
				$ban_link = Config::$scripturl.'?action=admin;area=ban;sa=add'.(!empty($report['author']['id']) ? ';u='.$report['author']['id'] : ';msg='.$report['topic']['id_msg']).';'.Utils::$context['session_var'].'='.Utils::$context['session_id'];

			Utils::$context['reports'][$key]['quickbuttons'] += array(
				'ban' => array(
					'label' => Lang::$txt['mc_reportedp_ban'],
					'href' => $ban_link,
					'icon' => 'error',
					'show' => !$report['closed'] && !empty(Utils::$context['report_manage_bans']) && (Utils::$context['report_type'] == 'posts' || Utils::$context['report_type'] == 'members' && !empty($report['user']['id']))
				),
				'quickmod' => array(
					'class' => 'inline_mod_check',
					'content' => '<input type="checkbox" name="close[]" value="'.$report['id'].'">',
					'show' => !Utils::$context['view_closed'] && !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1
				)
			);
		}
	}
}

/**
 * Shows all currently open reported posts.
 * Handles closing multiple reports
 *
 */
function ShowReports()
{
	// Showing closed or open ones? regardless, turn this to an integer for better handling.
	Utils::$context['view_closed'] = 0;

	// Call the right template.
	Utils::$context['sub_template'] = 'reported_' . Utils::$context['report_type'];
	Utils::$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	Utils::$context['total_reports'] = countReports(Utils::$context['view_closed']);

	// Just how many items are we showing per page?
	Utils::$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type'] . ';sa=show', Utils::$context['start'], Utils::$context['total_reports'], Utils::$context['reports_how_many']);

	// Get the reports at once!
	Utils::$context['reports'] = getReports(Utils::$context['view_closed']);

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
		redirectexit(Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type']);
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
	// Showing closed ones.
	Utils::$context['view_closed'] = 1;

	// Call the right template.
	Utils::$context['sub_template'] = 'reported_' . Utils::$context['report_type'];
	Utils::$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	Utils::$context['total_reports'] = countReports(Utils::$context['view_closed']);

	// Just how many items are we showing per page?
	Utils::$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type'] . ';sa=closed', Utils::$context['start'], Utils::$context['total_reports'], Utils::$context['reports_how_many']);

	// Get the reports at once!
	Utils::$context['reports'] = getReports(Utils::$context['view_closed']);

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
	// Have to at least give us something to work with.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please
	$report_id = (int) $_REQUEST['rid'];

	// Get the report details.
	$report = getReportDetails($report_id);

	if (!$report)
		fatal_lang_error('mc_no_modreport_found', false);

	// Build the report data - basic details first, then extra stuff based on the type
	Utils::$context['report'] = array(
		'id' => $report['id_report'],
		'report_href' => Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type'] . ';rid=' . $report['id_report'],
		'comments' => array(),
		'mod_comments' => array(),
		'time_started' => timeformat($report['time_started']),
		'last_updated' => timeformat($report['time_updated']),
		'num_reports' => $report['num_reports'],
		'closed' => $report['closed'],
		'ignore' => $report['ignore_all']
	);

	// Different reports have different "extra" data attached to them
	if (Utils::$context['report_type'] == 'members')
	{
		$extraDetails = array(
			'user' => array(
				'id' => $report['id_user'],
				'name' => $report['user_name'],
				'link' => $report['id_user'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $report['id_user'] . '">' . $report['user_name'] . '</a>' : $report['user_name'],
				'href' => Config::$scripturl . '?action=profile;u=' . $report['id_user'],
			),
		);
	}
	else
	{
		$extraDetails = array(
			'topic_id' => $report['id_topic'],
			'board_id' => $report['id_board'],
			'message_id' => $report['id_msg'],
			'message_href' => Config::$scripturl . '?msg=' . $report['id_msg'],
			'message_link' => '<a href="' . Config::$scripturl . '?msg=' . $report['id_msg'] . '">' . $report['subject'] . '</a>',
			'author' => array(
				'id' => $report['id_author'],
				'name' => $report['author_name'],
				'link' => $report['id_author'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $report['id_author'] . '">' . $report['author_name'] . '</a>' : $report['author_name'],
				'href' => Config::$scripturl . '?action=profile;u=' . $report['id_author'],
			),
			'subject' => $report['subject'],
			'body' => BBCodeParser::load()->parse($report['body']),
		);
	}

	Utils::$context['report'] = array_merge(Utils::$context['report'], $extraDetails);

	$reportComments = getReportComments($report_id);

	if (!empty($reportComments))
		Utils::$context['report'] = array_merge(Utils::$context['report'], $reportComments);

	// What have the other moderators done to this message?
	require_once(Config::$sourcedir . '/Modlog.php');
	Lang::load('Modlog');

	// Parameters are slightly different depending on what we're doing here...
	if (Utils::$context['report_type'] == 'members')
	{
		// Find their ID in the serialized action string...
		$user_id_length = strlen((string) Utils::$context['report']['user']['id']);
		$member = 's:6:"member";s:' . $user_id_length . ':"' . Utils::$context['report']['user']['id'] . '";}';

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
			array('id_topic' => Utils::$context['report']['topic_id'], 'not_a_reported_post' => 0),
			1,
		);
	}

	// This is all the information from the moderation log.
	$listOptions = array(
		'id' => 'moderation_actions_list',
		'title' => Lang::$txt['mc_modreport_modactions'],
		'items_per_page' => 15,
		'no_items_label' => Lang::$txt['modlog_no_entries_found'],
		'base_href' => Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type'] . ';sa=details;rid=' . Utils::$context['report']['id'],
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
					'value' => Lang::$txt['modlog_action'],
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
					'value' => Lang::$txt['modlog_date'],
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
					'value' => Lang::$txt['modlog_member'],
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
					'value' => Lang::$txt['modlog_position'],
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
					'value' => Lang::$txt['modlog_ip'],
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
	new ItemList($listOptions);

	// Make sure to get the correct tab selected.
	if (Utils::$context['report']['closed'])
		Menu::$loaded['moderate']['current_subsection'] = 'closed';

	// Finally we are done :P
	if (Utils::$context['report_type'] == 'members')
	{
		Utils::$context['page_title'] = sprintf(Lang::$txt['mc_viewmemberreport'], Utils::$context['report']['user']['name']);
		Utils::$context['sub_template'] = 'viewmemberreport';
	}
	else
	{
		Utils::$context['page_title'] = sprintf(Lang::$txt['mc_viewmodreport'], Utils::$context['report']['subject'], Utils::$context['report']['author']['name']);
		Utils::$context['sub_template'] = 'viewmodreport';
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

		$new_comment = trim(Utils::htmlspecialchars($_POST['mod_comment']));

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
		$comment_owner = User::$me->id == $comment['id_member'];

		// Nope! sorry.
		if (!allowedTo('admin_forum') && !$comment_owner)
			fatal_lang_error('report_action_message_delete_cannot');

		// All good!
		deleteModComment($comment_id);

		// Tell them the message was deleted.
		$_SESSION['rc_confirmation'] = 'message_deleted';
	}

	//Redirect to prevent double submission.
	redirectexit(Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type'] . ';sa=details;rid=' . $report_id);
}

/**
 * Shows a textarea for editing a moderator comment.
 * Handles the edited comment and stores it on the DB.
 *
 */
function EditComment()
{
	checkSession(isset($_REQUEST['save']) ? 'post' : 'get');

	// The report ID is a must.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	if (empty($_REQUEST['mid']))
		fatal_lang_error('mc_reportedp_comment_none_found');

	// Integers only please.
	Utils::$context['report_id'] = (int) $_REQUEST['rid'];
	Utils::$context['comment_id'] = (int) $_REQUEST['mid'];

	Utils::$context['comment'] = getCommentModDetails(Utils::$context['comment_id']);

	if (empty(Utils::$context['comment']))
		fatal_lang_error('mc_reportedp_comment_none_found');

	// Set up the comforting bits...
	Utils::$context['page_title'] = Lang::$txt['mc_reported_posts'];
	Utils::$context['sub_template'] = 'edit_comment';

	if (isset($_REQUEST['save']) && isset($_POST['edit_comment']) && !empty($_POST['mod_comment']))
	{
		validateToken('mod-reportC-edit');

		// Make sure there is some data to edit on the DB.
		if (empty(Utils::$context['comment']))
			fatal_lang_error('report_action_message_edit_issue');

		// Still there, good, now lets see if you can actually edit it...
		$comment_owner = User::$me->id == Utils::$context['comment']['id_member'];

		// So, you aren't neither an admin or the comment owner huh? that's too bad.
		if (!allowedTo('admin_forum') && !$comment_owner)
			fatal_lang_error('report_action_message_edit_cannot');

		// All good!
		$edited_comment = trim(Utils::htmlspecialchars($_POST['mod_comment']));

		editModComment(Utils::$context['comment_id'], $edited_comment);

		$_SESSION['rc_confirmation'] = 'message_edited';

		redirectexit(Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type'] . ';sa=details;rid=' . Utils::$context['report_id']);
	}

	createToken('mod-reportC-edit');
}

/**
 * Performs closing/ignoring actions for a given report.
 *
 */
function HandleReport()
{
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
	redirectexit(Config::$scripturl . '?action=moderate;area=reported' . Utils::$context['report_type']);
}

?>