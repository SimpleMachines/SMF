<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions\Moderation;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

/**
 * Handles reported members and posts, as well as moderation comments.
 */
class ReportedContent implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'ReportedContent',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'show';

	/**
	 * @var string
	 *
	 * The requested report type.
	 */
	public string $type = '';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = array(
		'show' => 'show',
		'closed' => 'showClosed',
		'details' => 'details',
		'handle' => 'setState',
		'handlecomment' => 'comment',
		'editcomment' => 'modifyComment',
	);

	/**
	 * @var array
	 *
	 * Supported report types
	 */
	public static array $types = array(
		'posts',
		'members',
	);

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Boards where the current user can remove any message.
	 */
	protected array $remove_any_boards = array();

	/**
	 * @var bool
	 *
	 * Whether the current user has the manage_bans permission.
	 */
	protected bool $manage_bans = false;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		call_helper(method_exists($this, self::$subactions[$this->subaction]) ? array($this, self::$subactions[$this->subaction]) : self::$subactions[$this->subaction]);
	}

	/**
	 * Shows all currently open reported posts.
	 *
	 * Handles closing multiple reports.
	 */
	public function show(): void
	{
		Utils::$context['view_closed'] = 0;

		// Call the right template.
		Utils::$context['sub_template'] = 'reported_' . $this->type;
		Utils::$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

		// Before anything, we need to know just how many reports do we have.
		$total_reports = $this->countReports(Utils::$context['view_closed']);

		// So, that means we can have pagination, yes?
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';sa=show', Utils::$context['start'], $total_reports, 10);

		// Get the reports at once!
		Utils::$context['reports'] = $this->getReports(Utils::$context['view_closed']);

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
				$this->updateReport('closed', 1, $toClose);

			// Set the confirmation message.
			$_SESSION['rc_confirmation'] = 'close_all';

			// Force a page refresh.
			redirectexit(Config::$scripturl . '?action=moderate;area=reported' . $this->type);
		}

		createToken('mod-report-close-all');
		createToken('mod-report-ignore', 'get');
		createToken('mod-report-closed', 'get');

		$this->buildQuickButtons();
	}

	/**
	 * Shows all currently closed reported posts.
	 */
	public function showClosed(): void
	{
		// Showing closed ones.
		Utils::$context['view_closed'] = 1;

		// Call the right template.
		Utils::$context['sub_template'] = 'reported_' . $this->type;
		Utils::$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

		// Before anything, we need to know just how many reports do we have.
		$total_reports = $this->countReports(Utils::$context['view_closed']);

		// So, that means we can have pagination, yes?
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';sa=closed', Utils::$context['start'], $total_reports, 10);

		// Get the reports at once!
		Utils::$context['reports'] = $this->getReports(Utils::$context['view_closed']);

		createToken('mod-report-ignore', 'get');
		createToken('mod-report-closed', 'get');

		$this->buildQuickButtons();
	}

	/**
	 * Shows detailed information about a report, such as report comments and
	 * moderator comments.
	 *
	 * Shows a list of moderation actions for the specific report.
	 */
	public function details(): void
	{
		// Have to at least give us something to work with.
		if (empty($_REQUEST['rid']))
			fatal_lang_error('mc_reportedp_none_found');

		// Integers only please
		$report_id = (int) $_REQUEST['rid'];

		// Get the report details.
		$report = $this->getReportDetails($report_id);

		if (empty($report))
			fatal_lang_error('mc_no_modreport_found', false);

		// Build the report data - basic details first, then extra stuff based on the type
		Utils::$context['report'] = array(
			'id' => $report['id_report'],
			'report_href' => Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';rid=' . $report['id_report'],
			'comments' => array(),
			'mod_comments' => array(),
			'time_started' => timeformat($report['time_started']),
			'last_updated' => timeformat($report['time_updated']),
			'num_reports' => $report['num_reports'],
			'closed' => $report['closed'],
			'ignore' => $report['ignore_all']
		);

		// Different reports have different "extra" data attached to them
		if ($this->type == 'members')
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

		$reportComments = $this->getReportComments($report_id);

		if (!empty($reportComments))
			Utils::$context['report'] = array_merge(Utils::$context['report'], $reportComments);

		// What have the other moderators done to this message?
		require_once(Config::$sourcedir . '/Actions/Moderation/Logs.php');
		Lang::load('Modlog');

		// Parameters are slightly different depending on what we're doing here...
		if ($this->type == 'members')
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
			'base_href' => Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';sa=details;rid=' . Utils::$context['report']['id'],
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
			Menu::$loaded['moderate']->current_subsection = 'closed';

		// Finally we are done :P
		if ($this->type == 'members')
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
	 * Performs closing and ignoring actions for a given report.
	 */
	public function setState(): void
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
		$this->updateReport($action, $value, $report_id);

		// So, time to show a confirmation message, lets do some trickery!
		$_SESSION['rc_confirmation'] = $message;

		// Done!
		redirectexit(Config::$scripturl . '?action=moderate;area=reported' . $this->type);
	}

	/**
	 * Creates and deletes moderator comments.
	 */
	public function comment(): void
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

			$this->saveModComment($report_id, array($report_id, $new_comment, time()));

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

			// We need to verify some data, so let's load the comment details once more!
			$comment = $this->getCommentModDetails($comment_id);

			// Perhaps somebody else already deleted this fine gem...
			if (empty($comment))
				fatal_lang_error('report_action_message_delete_issue');

			// Can you actually do this?
			if (!allowedTo('admin_forum') && User::$me->id != $comment['id_member'])
				fatal_lang_error('report_action_message_delete_cannot');

			// All good!
			$this->deleteModComment($comment_id);

			// Tell them the message was deleted.
			$_SESSION['rc_confirmation'] = 'message_deleted';
		}

		// Redirect to prevent double submission.
		redirectexit(Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';sa=details;rid=' . $report_id);
	}

	/**
	 * Shows a textarea for editing a moderator comment.
	 *
	 * Handles the edited comment and stores it in the DB.
	 */
	public function modifyComment(): void
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

		Utils::$context['comment'] = $this->getCommentModDetails(Utils::$context['comment_id']);

		if (empty(Utils::$context['comment']))
			fatal_lang_error('mc_reportedp_comment_none_found');

		// Set up the comforting bits...
		Utils::$context['page_title'] = Lang::$txt['mc_reported_posts'];
		Utils::$context['sub_template'] = 'edit_comment';

		if (isset($_REQUEST['save']) && isset($_POST['edit_comment']) && !empty($_POST['mod_comment']))
		{
			validateToken('mod-reportC-edit');

			// Make sure there is some data to edit in the DB.
			if (empty(Utils::$context['comment']))
				fatal_lang_error('report_action_message_edit_issue');

			// So, you aren't neither an admin or the comment owner huh? that's too bad.
			if (!allowedTo('admin_forum') && User::$me->id != Utils::$context['comment']['id_member'])
			{
				fatal_lang_error('report_action_message_edit_cannot');
			}

			// All good!
			$edited_comment = trim(Utils::htmlspecialchars($_POST['mod_comment']));

			$this->editModComment(Utils::$context['comment_id'], $edited_comment);

			$_SESSION['rc_confirmation'] = 'message_edited';

			redirectexit(Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';sa=details;rid=' . Utils::$context['report_id']);
		}

		createToken('mod-reportC-edit');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj))
			self::$obj = new self();

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Recount all open reports. Sets a SESSION var with the updated info.
	 *
	 * @param string $type the type of reports to count
	 * @return int the update open report count.
	 */
	public static function recountOpenReports(string $type): int
	{
		$bq = $type == 'members' ? '' : "\n\t\t\t\t" . 'AND ' . User::$me->mod_cache['bq'];

		$request = Db::$db->query('', '
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
		list($open_reports) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$arr = ($type == 'members' ? 'member_reports' : 'reports');
		$_SESSION['rc'] = array_merge(
			!empty($_SESSION['rc']) ? $_SESSION['rc'] : array(),
			array(
				'id' => User::$me->id,
				'time' => time(),
				$arr => $open_reports,
			)
		);

		return $open_reports;
	}

	/**
	 * Backward compatibility wrapper for the show sub-action.
	 */
	public static function showReports(): void
	{
		self::load();
		self::$obj->subaction = 'show';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the closed sub-action.
	 */
	public static function showClosedReports(): void
	{
		self::load();
		self::$obj->subaction = 'closed';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the details sub-action.
	 */
	public static function reportDetails(): void
	{
		self::load();
		self::$obj->subaction = 'details';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the handle sub-action.
	 */
	public static function handleReport(): void
	{
		self::load();
		self::$obj->subaction = 'handle';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the handlecomment sub-action.
	 */
	public static function handleComment(): void
	{
		self::load();
		self::$obj->subaction = 'handlecomment';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the editcomment sub-action.
	 */
	public static function editComment(): void
	{
		self::load();
		self::$obj->subaction = 'editcomment';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// First order of business - what are these reports about?
		// area=reported{type}
		$this->type = substr($_GET['area'], 8);

		if (!in_array($this->type, self::$types))
			fatal_lang_error('no_access', false);

		Utils::$context['report_type'] = $this->type;

		Lang::load('ModerationCenter');
		Theme::loadTemplate('ReportedContent');

		// Do we need to show a confirmation message?
		Utils::$context['report_post_action'] = !empty($_SESSION['rc_confirmation']) ? $_SESSION['rc_confirmation'] : array();
		unset($_SESSION['rc_confirmation']);

		// Set up the comforting bits...
		Utils::$context['page_title'] = Lang::$txt['mc_reported_' . $this->type];

		// Put the open and closed options into tabs, because we can...
		Menu::$loaded['moderate']->tab_data = array(
			'title' => Lang::$txt['mc_reported_' . $this->type],
			'help' => '',
			'description' => Lang::$txt['mc_reported_' . $this->type . '_desc'],
		);

		// This comes under the umbrella of moderating posts.
		if ($this->type == 'members' || User::$me->mod_cache['bq'] == '0=1')
			isAllowedTo('moderate_forum');

		// Go ahead and add your own sub-actions.
		call_integration_hook('integrate_reported_' . $this->type, array(&self::$subactions));

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]))
			$this->subaction = $_REQUEST['sa'];
	}

	/**
	 * Updates a report with the given parameters. Logs each action via logAction()
	 *
	 * @param string $action The action to perform. Accepts "closed" and "ignore".
	 * @param int $value The new value to update.
	 * @param int|array $report_id The affected report(s).
	 */
	protected function updateReport(string $action, int $value, int|array $report_id): void
	{
		// Don't bother.
		if (empty($action) || empty($report_id))
			return;

		// Add the "_all" thingy.
		if ($action == 'ignore')
			$action = 'ignore_all';

		// We don't need the board query for reported members
		if ($this->type == 'members')
		{
			$board_query = '';
		}
		else
		{
			$board_query = ' AND ' . User::$me->mod_cache['bq'];
		}

		// Update the report...
		Db::$db->query('', '
			UPDATE {db_prefix}log_reported
			SET  {raw:action} = {string:value}
			' . (is_array($report_id) ? 'WHERE id_report IN ({array_int:id_report})' : 'WHERE id_report = {int:id_report}') . '
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

		if ($this->type == 'posts')
		{
			// Get the board, topic and message for this report
			$request = Db::$db->query('', '
				SELECT id_board, id_topic, id_msg, id_report
				FROM {db_prefix}log_reported
				WHERE id_report IN ({array_int:id_report})',
				array(
					'id_report' => $report_id,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$extra[$row['id_report']] = array(
					'report' => $row['id_report'],
					'board' => $row['id_board'],
					'message' => $row['id_msg'],
					'topic' => $row['id_topic'],
				);
			}
			Db::$db->free_result($request);
		}
		else
		{
			$request = Db::$db->query('', '
				SELECT id_report, id_member, membername
				FROM {db_prefix}log_reported
				WHERE id_report IN ({array_int:id_report})',
				array(
					'id_report' => $report_id,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$extra[$row['id_report']] = array(
					'report' => $row['id_report'],
					'member' => $row['id_member'],
				);
			}
			Db::$db->free_result($request);
		}

		// Back to "ignore".
		if ($action == 'ignore_all')
			$action = 'ignore';

		$log_report = $action == 'ignore' ? (!empty($value) ? 'ignore' : 'unignore') : (!empty($value) ? 'close' : 'open');

		if ($this->type == 'members')
			$log_report .= '_user';

		// See if any report alerts need to be cleaned up upon close/ignore
		if (in_array($log_report, array('close', 'ignore', 'close_user', 'ignore_user')))
			$this->clearReportAlerts($log_report, $extra);

		// Log this action.
		if (!empty($extra))
		{
			foreach ($extra as $report)
				logAction($log_report . '_report', $report);
		}

		// Time to update.
		Config::updateModSettings(array('last_mod_report_action' => time()));
		self::recountOpenReports($this->type);
	}

	/**
	 * Upon close/ignore, mark unread alerts as read.
	 *
	 * @param string $log_report What action is being taken.
	 * @param array $extra Detailed info about the report.
	 */
	protected function clearReportAlerts(string $log_report, array $extra): void
	{
		// Setup the query, depending on if it's a member report or a msg report.
		// In theory, these should be unique (reports for the same things get combined), but since $extra is an array, treat as an array.
		if (strpos($log_report, '_user') !== false)
		{
			$content_ids = array_unique(array_column($extra, 'member'));
			$content_type = 'member';
		}
		else
		{
			$content_ids = array_unique(array_column($extra, 'message'));
			$content_type = 'msg';
		}

		// Check to see if there are unread alerts to flag as read...
		// Might be multiple alerts, for multiple moderators...
		$alerts = array();
		$moderators = array();
		$result = Db::$db->query('', '
			SELECT id_alert, id_member
			FROM {db_prefix}user_alerts
			WHERE content_id IN ({array_int:content_ids})
				AND content_type = {string:content_type}
				AND content_action = {string:content_action}
				AND is_read = {int:unread}',
			array(
				'content_ids' => $content_ids,
				'content_type' => $content_type,
				'content_action' => 'report',
				'unread' => 0,
			)
		);
		// Found any?
		while ($row = Db::$db->fetch_assoc($result))
		{
			$alerts[] = $row['id_alert'];
			$moderators[] = $row['id_member'];
		}
		if (!empty($alerts))
		{
			// Flag 'em as read
			Db::$db->query('', '
				UPDATE {db_prefix}user_alerts
				SET is_read = {int:time}
				WHERE id_alert IN ({array_int:alerts})',
				array(
					'time' => time(),
					'alerts' => $alerts,
				)
			);
			// Decrement counter for each moderator who had an unread alert
			User::updateMemberData($moderators, array('alerts' => '-'));
		}
	}

	/**
	 * Counts how many reports there are in total. Used for creating pagination.
	 *
	 * @param int $closed 1 for counting closed reports, 0 for open ones.
	 * @return int How many reports.
	 */
	protected function countReports(int $closed = 0): int
	{
		// Skip entries with id_board = 0 if we're viewing member reports
		if ($this->type == 'members')
		{
			$and = 'lr.id_board = 0';
		}
		else
		{
			if (User::$me->mod_cache['bq'] == '1=1' || User::$me->mod_cache['bq'] == '0=1')
			{
				$bq = User::$me->mod_cache['bq'];
			}
			else
			{
				$bq = 'lr.' . User::$me->mod_cache['bq'];
			}

			$and = $bq . ' AND lr.id_board != 0';
		}

		// How many entries are we viewing?
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_reported AS lr
			WHERE lr.closed = {int:view_closed}
				AND ' . $and,
			array(
				'view_closed' => $closed,
			)
		);
		list($total_reports) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $total_reports;
	}

	/**
	 * Get all possible reports the current user can see.
	 *
	 * @param int $closed 1 for closed reports, 0 for open ones.
	 * @return array The reports data, with the report ID as key.
	 */
	protected function getReports(int $closed = 0): array
	{
		// Lonely, standalone var.
		$reports = array();

		$report_ids = array();
		$report_boards_ids = array();

		// By George, that means we are in a position to get the reports, jolly good.
		if ($this->type == 'members')
		{
			$request = Db::$db->query('', '
				SELECT lr.id_report, lr.id_member,
					lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
					COALESCE(mem.real_name, lr.membername) AS user_name, COALESCE(mem.id_member, 0) AS id_user
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
				WHERE lr.closed = {int:view_closed}
					AND lr.id_board = 0
				ORDER BY lr.time_updated DESC
				LIMIT {int:start}, {int:max}',
				array(
					'view_closed' => $closed,
					'start' => Utils::$context['start'],
					'max' => 10,
				)
			);
		}
		else
		{
			$request = Db::$db->query('', '
				SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
					lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
					COALESCE(mem.real_name, lr.membername) AS author_name, COALESCE(mem.id_member, 0) AS id_author
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
				WHERE lr.closed = {int:view_closed}
					AND lr.id_board != 0
					AND ' . (User::$me->mod_cache['bq'] == '1=1' || User::$me->mod_cache['bq'] == '0=1' ? User::$me->mod_cache['bq'] : 'lr.' . User::$me->mod_cache['bq']) . '
				ORDER BY lr.time_updated DESC
				LIMIT {int:start}, {int:max}',
				array(
					'view_closed' => (int) $closed,
					'start' => Utils::$context['start'],
					'max' => 10,
				)
			);
		}
		while ($row = Db::$db->fetch_assoc($request))
		{
			$report_ids[] = $row['id_report'];
			$reports[$row['id_report']] = array(
				'id' => $row['id_report'],
				'report_href' => Config::$scripturl . '?action=moderate;area=reported' . $this->type . ';sa=details;rid=' . $row['id_report'],
				'comments' => array(),
				'time_started' => timeformat($row['time_started']),
				'last_updated' => timeformat($row['time_updated']),
				'num_reports' => $row['num_reports'],
				'closed' => $row['closed'],
				'ignore' => $row['ignore_all']
			);

			if ($this->type == 'members')
			{
				$extraDetails = array(
					'user' => array(
						'id' => $row['id_user'],
						'name' => $row['user_name'],
						'link' => $row['id_user'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_user'] . '">' . $row['user_name'] . '</a>' : $row['user_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_user'],
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
						'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					),
					'author' => array(
						'id' => $row['id_author'],
						'name' => $row['author_name'],
						'link' => $row['id_author'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_author'],
					),
					'subject' => $row['subject'],
					'body' => BBCodeParser::load()->parse($row['body']),
				);
			}

			$reports[$row['id_report']] = array_merge($reports[$row['id_report']], $extraDetails);
		}
		Db::$db->free_result($request);

		// Get the names of boards those topics are in. Slightly faster this way.
		if (!empty($report_boards_ids))
		{
			$report_boards_ids = array_unique($report_boards_ids);
			$board_names = array();
			$request = Db::$db->query('', '
				SELECT id_board, name
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:boards})',
				array(
					'boards' => $report_boards_ids,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$board_names[$row['id_board']] = $row['name'];
			}
			Db::$db->free_result($request);

			foreach ($reports as $id_report => $report)
			{
				if (!empty($board_names[$report['topic']['id_board']]))
				{
					$reports[$id_report]['topic']['board_name'] = $board_names[$report['topic']['id_board']];
				}
			}
		}

		// Now get all the people who reported it.
		if (!empty($report_ids))
		{
			$request = Db::$db->query('', '
				SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment,
					COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lrc.membername) AS reporter
				FROM {db_prefix}log_reported_comments AS lrc
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
				WHERE lrc.id_report IN ({array_int:report_list})',
				array(
					'report_list' => $report_ids,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$reports[$row['id_report']]['comments'][] = array(
					'id' => $row['id_comment'],
					'message' => $row['comment'],
					'time' => timeformat($row['time_sent']),
					'member' => array(
						'id' => $row['id_member'],
						'name' => empty($row['reporter']) ? Lang::$txt['guest'] : $row['reporter'],
						'link' => $row['id_member'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? Lang::$txt['guest'] : $row['reporter']),
						'href' => $row['id_member'] ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : '',
					),
				);
			}
			Db::$db->free_result($request);
		}

		// Get the boards where the current user can remove any message.
		$this->remove_any_boards = User::$me->is_admin ? $report_boards_ids : array_intersect($report_boards_ids, boardsAllowedTo('remove_any'));
		$this->manage_bans = allowedTo('manage_bans');

		return $reports;
	}

	/**
	 * Gets additional information for a specific report.
	 *
	 * @param int $report_id The report ID to get the info from.
	 * @return array The report data.
	 */
	protected function getReportDetails(int $report_id): array
	{
		if (empty($report_id))
			return array();

		// We don't need all this info if we're only getting user info
		if ($this->type == 'members')
		{
			$request = Db::$db->query('', '
				SELECT lr.id_report, lr.id_member,
					lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
					COALESCE(mem.real_name, lr.membername) AS user_name, COALESCE(mem.id_member, 0) AS id_user
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
			$request = Db::$db->query('', '
				SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
					lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
					COALESCE(mem.real_name, lr.membername) AS author_name, COALESCE(mem.id_member, 0) AS id_author
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
				WHERE lr.id_report = {int:id_report}
					AND ' . (User::$me->mod_cache['bq'] == '1=1' || User::$me->mod_cache['bq'] == '0=1' ? User::$me->mod_cache['bq'] : 'lr.' . User::$me->mod_cache['bq']) . '
				LIMIT 1',
				array(
					'id_report' => $report_id,
				)
			);
		}
		// So did we find anything?
		if (!Db::$db->num_rows($request))
		{
			$row = array();
		}
		else
		{
			// Woohoo we found a report and they can see it!
			$row = Db::$db->fetch_assoc($request);
		}
		Db::$db->free_result($request);

		return $row;
	}

	/**
	 * Gets both report comments as well as any moderator comment.
	 *
	 * @param int $report_id The report ID to get the info from.
	 * @return array An associative array with 2 keys: comments and mod_comments.
	 */
	protected function getReportComments(int $report_id): array
	{
		if (empty($report_id))
			return array();

		$report = array(
			'comments' => array(),
			'mod_comments' => array()
		);

		// So what bad things do the reporters have to say about it?
		$request = Db::$db->query('', '
			SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment, lrc.member_ip,
				COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lrc.membername) AS reporter
			FROM {db_prefix}log_reported_comments AS lrc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
			WHERE lrc.id_report = {int:id_report}',
			array(
				'id_report' => $report_id,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$report['comments'][] = array(
				'id' => $row['id_comment'],
				'message' => strtr($row['comment'], array("\n" => '<br>')),
				'time' => timeformat($row['time_sent']),
				'member' => array(
					'id' => $row['id_member'],
					'name' => empty($row['reporter']) ? Lang::$txt['guest'] : $row['reporter'],
					'link' => $row['id_member'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? Lang::$txt['guest'] : $row['reporter']),
					'href' => $row['id_member'] ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : '',
					'ip' => !empty($row['member_ip']) && allowedTo('moderate_forum') ? '<a href="' . Config::$scripturl . '?action=trackip;searchip=' . inet_dtop($row['member_ip']) . '">' . inet_dtop($row['member_ip']) . '</a>' : '',
				),
			);
		}
		Db::$db->free_result($request);

		// Hang about old chap, any comments from moderators on this one?
		$request = Db::$db->query('', '
			SELECT lc.id_comment, lc.id_notice, lc.log_time, lc.body,
				COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS moderator
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.id_notice = {int:id_report}
				AND lc.comment_type = {literal:reportc}',
			array(
				'id_report' => $report_id,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$report['mod_comments'][] = array(
				'id' => $row['id_comment'],
				'message' => BBCodeParser::load()->parse($row['body']),
				'time' => timeformat($row['log_time']),
				'can_edit' => allowedTo('admin_forum') || ((User::$me->id == $row['id_member'])),
				'member' => array(
					'id' => $row['id_member'],
					'name' => $row['moderator'],
					'link' => $row['id_member'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['moderator'] . '</a>' : $row['moderator'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				),
			);
		}
		Db::$db->free_result($request);

		return $report;
	}

	/**
	 * Gets specific details about a moderator comment.
	 *
	 * It also adds a permission for editing/deleting the comment.
	 * By default only admins and the author of the comment can edit/delete it.
	 *
	 * @param int $comment_id The moderator comment ID to get the info from.
	 * @return array An array with the fetched data.
	 */
	protected function getCommentModDetails(int $comment_id): array
	{
		if (empty($comment_id))
			return array();

		$request = Db::$db->query('', '
			SELECT id_comment, id_notice, log_time, body, id_member
			FROM {db_prefix}log_comments
			WHERE id_comment = {int:id_comment}
				AND comment_type = {literal:reportc}',
			array(
				'id_comment' => $comment_id,
			)
		);
		$comment = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// Add the permission
		if (!empty($comment))
		{
			$comment['can_edit'] = allowedTo('admin_forum') || ((User::$me->id == $comment['id_member']));
		}

		return $comment;
	}

	/**
	 * Inserts a new moderator comment to the DB.
	 *
	 * @param int $report_id The report ID is used to fire a notification about the event.
	 * @param array $data a formatted array of data to be inserted. Should be already properly sanitized.
	 */
	protected function saveModComment(int $report_id, array $data): void
	{
		if (empty($data))
			return;

		$data = array_merge(array(User::$me->id, User::$me->name, 'reportc', ''), $data);

		$last_comment = Db::$db->insert('',
			'{db_prefix}log_comments',
			array(
				'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
				'id_notice' => 'int', 'body' => 'string', 'log_time' => 'int',
			),
			$data,
			array('id_comment'),
			1
		);

		$report = $this->getReportDetails($report_id);

		if ($this->type == 'members')
		{
			$prefix = 'Member';
			$data = array(
				'report_id' => $report_id,
				'user_id' => $report['id_user'],
				'user_name' => $report['user_name'],
				'sender_id' => User::$me->id,
				'sender_name' => User::$me->name,
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
				'sender_id' => User::$me->id,
				'sender_name' => User::$me->name,
				'time' => time(),
			);
		}

		// And get ready to notify people.
		if (!empty($report))
		{
			Db::$db->insert('insert',
				'{db_prefix}background_tasks',
				array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/' . $prefix . 'ReportReply-Notify.php', 'SMF\Tasks\\' . $prefix . 'ReportReply_Notify', Utils::jsonEncode($data), 0),
				array('id_task')
			);
		}
	}

	/**
	 * Saves the new information whenever a moderator comment is edited.
	 *
	 * @param int $comment_id The edited moderator comment ID.
	 * @param string $edited_comment The edited moderator comment text.
	 */
	protected function editModComment(int $comment_id, string $edited_comment): void
	{
		if (empty($comment_id) || empty($edited_comment))
			return;

		Db::$db->query('', '
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
	 */
	protected function deleteModComment(int $comment_id): void
	{
		if (empty($comment_id))
			return;

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment = {int:comment_id}',
			array(
				'comment_id' => $comment_id,
			)
		);
	}

	/**
	 *
	 */
	protected function buildQuickButtons(): void
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
					'href' => Config::$scripturl.'?action=moderate;area=reported'.$this->type.';sa=handle;ignore='.(int)!$report['ignore'].';rid='.$report['id'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-ignore_token_var'].'='.Utils::$context['mod-report-ignore_token'],
					'javascript' => !$report['ignore'] ? ' data-confirm="' . Lang::$txt['mc_reportedp_ignore_confirm'] . '"' : '',
					'class' => 'you_sure',
					'icon' => 'ignore'
				),
				'close' => array(
					'label' => Utils::$context['view_closed'] ? Lang::$txt['mc_reportedp_open'] : Lang::$txt['mc_reportedp_close'],
					'href' => Config::$scripturl.'?action=moderate;area=reported'.$this->type.';sa=handle;closed='.(int)!$report['closed'].';rid='.$report['id'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'].';'.Utils::$context['mod-report-closed_token_var'].'='.Utils::$context['mod-report-closed_token'],
					'icon' => Utils::$context['view_closed'] ? 'folder' : 'close',
				),
			);

			// Only reported posts can be deleted
			if ($this->type == 'posts')
			{
				Utils::$context['reports'][$key]['quickbuttons']['delete'] = array(
					'label' => Lang::$txt['mc_reportedp_delete'],
					'href' => Config::$scripturl.'?action=deletemsg;topic='.$report['topic']['id'].'.0;msg='.$report['topic']['id_msg'].';modcenter;'.Utils::$context['session_var'].'='.Utils::$context['session_id'],
					'javascript' => 'data-confirm="'.Lang::$txt['mc_reportedp_delete_confirm'].'"',
					'class' => 'you_sure',
					'icon' => 'delete',
					'show' => !$report['closed'] && (is_array($this->remove_any_boards) && in_array($report['topic']['id_board'], $this->remove_any_boards))
				);
			}

			// Ban reported member/post author link
			if ($this->type == 'members')
			{
				$ban_link = Config::$scripturl.'?action=admin;area=ban;sa=add;u='.$report['user']['id'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'];
			}
			else
			{
				$ban_link = Config::$scripturl.'?action=admin;area=ban;sa=add'.(!empty($report['author']['id']) ? ';u='.$report['author']['id'] : ';msg='.$report['topic']['id_msg']).';'.Utils::$context['session_var'].'='.Utils::$context['session_id'];
			}

			Utils::$context['reports'][$key]['quickbuttons'] += array(
				'ban' => array(
					'label' => Lang::$txt['mc_reportedp_ban'],
					'href' => $ban_link,
					'icon' => 'error',
					'show' => !$report['closed'] && !empty($this->manage_bans) && ($this->type == 'posts' || $this->type == 'members' && !empty($report['user']['id']))
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

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\ReportedContent::exportStatic'))
	ReportedContent::exportStatic();

?>