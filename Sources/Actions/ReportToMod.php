<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Msg;
use SMF\Security;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Deals with reporting posts or profiles to mods and admins.
 */
class ReportToMod implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ReportToModerator',
			'ReportToModerator2' => 'ReportToModerator2',
			'reportPost' => 'reportPost',
			'reportUser' => 'reportUser',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// code...

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

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'show' => 'show',
		'submit' => 'submit',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Whether they want to see a preview of the report.
	 */
	protected bool $previewing = false;

	/**
	 * @var bool
	 *
	 * Whether they want to submit the report.
	 */
	protected bool $submitting = false;

	/**
	 * @var bool
	 *
	 * Whether they can submit the report.
	 */
	protected bool $can_submit = false;

	/**
	 * @var string
	 *
	 * The text content of the report.
	 */
	protected string $comment = '';

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
		// No guests!
		User::$me->kickIfGuest();

		// You can't use this if it's off or you are not allowed to do it.
		// If we don't have the ID of something to report, we'll die with a no_access error below
		if (isset($_REQUEST['msg'])) {
			User::$me->isAllowedTo('report_any');
		} elseif (isset($_REQUEST['u'])) {
			User::$me->isAllowedTo('report_user');
		}

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Report a post or profile to the moderator... ask for a comment.
	 * Gathers data from the user to report abuse to the moderator(s).
	 * Uses the ReportToModerator template, main sub template.
	 * Requires the report_any or report_user permission.
	 * Accessed through ?action=reporttm.
	 */
	public function show(): void
	{
		// Previewing or modifying?
		if ($this->previewing) {
			$this->setPreview();
		}

		// We need a message ID or user ID to check!
		if (empty($_REQUEST['msg']) && empty($_REQUEST['mid']) && empty($_REQUEST['u'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// For compatibility, accept mid, but we should be using msg. (not the flavor kind!)
		if (!empty($_REQUEST['msg']) || !empty($_REQUEST['mid'])) {
			$_REQUEST['msg'] = empty($_REQUEST['msg']) ? (int) $_REQUEST['mid'] : (int) $_REQUEST['msg'];
		}
		// msg and mid empty - assume we're reporting a user
		elseif (!empty($_REQUEST['u'])) {
			$_REQUEST['u'] = (int) $_REQUEST['u'];
		}

		// Set up some form values
		Utils::$context['report_type'] = isset($_REQUEST['msg']) ? 'msg' : 'u';
		Utils::$context['reported_item'] = $_REQUEST['msg'] ?? $_REQUEST['u'];

		if (isset($_REQUEST['msg'])) {
			// Check the message's ID - don't want anyone reporting a post they can't even see!
			$result = Db::$db->query(
				'',
				'SELECT m.id_msg, m.id_member, t.id_member_started
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				WHERE m.id_msg = {int:id_msg}
					AND m.id_topic = {int:current_topic}
				LIMIT 1',
				[
					'current_topic' => Topic::$topic_id,
					'id_msg' => $_REQUEST['msg'],
				],
			);

			if (Db::$db->num_rows($result) == 0) {
				ErrorHandler::fatalLang('no_board', false);
			}

			list($_REQUEST['msg'], $member, $starter) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			// This is here so that the user could, in theory, be redirected back to the topic.
			Utils::$context['start'] = $_REQUEST['start'];
			Utils::$context['message_id'] = $_REQUEST['msg'];

			// The submit URL is different for users than it is for posts
			Utils::$context['submit_url'] = Config::$scripturl . '?action=reporttm;msg=' . $_REQUEST['msg'] . ';topic=' . Topic::$topic_id;
		} else {
			// Check the user's ID
			$result = Db::$db->query(
				'',
				'SELECT id_member, real_name, member_name
				FROM {db_prefix}members
				WHERE id_member = {int:current_user}',
				[
					'current_user' => $_REQUEST['u'],
				],
			);

			if (Db::$db->num_rows($result) == 0) {
				ErrorHandler::fatalLang('no_user', false);
			}

			list($_REQUEST['u'], $display_name, $username) = Db::$db->fetch_row($result);

			Utils::$context['current_user'] = $_REQUEST['u'];
			Utils::$context['submit_url'] = Config::$scripturl . '?action=reporttm;u=' . $_REQUEST['u'];
		}

		Utils::$context['comment_body'] = Utils::htmlspecialchars($this->comment, ENT_QUOTES);

		Utils::$context['page_title'] = Utils::$context['report_type'] == 'msg' ? Lang::$txt['report_to_mod'] : sprintf(Lang::$txt['report_profile'], $display_name);
		Utils::$context['notice'] = Utils::$context['report_type'] == 'msg' ? Lang::$txt['report_to_mod_func'] : Lang::$txt['report_profile_func'];

		// Show the inputs for the comment, etc.
		Lang::load('Post');
		Theme::loadTemplate('ReportToMod');

		Theme::addInlineJavaScript('
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

					$("#error_list").append("<li id=\'error_post_too_long\' class=\'error\'>" + ' . Utils::JavaScriptEscape(Lang::$txt['post_too_long']) . ' + "</li>");
				}
			}
			else
			{
				post_too_long.remove();
				if ($("#error_list li").length == 0)
					error_box.hide();
			}
		});', true);
	}

	/**
	 *
	 */
	public function submit(): void
	{
		// Make sure they aren't spamming.
		Security::spamProtection('reporttm');

		// Prevent double submission of this form.
		Security::checkSubmitOnce('check');

		// No errors, yet.
		$post_errors = [];

		// Check their session.
		if (User::$me->checkSession('post', '', false) != '') {
			$post_errors[] = 'session_timeout';
		}

		// Make sure we have a comment and it's clean.
		if ($this->comment === '') {
			$post_errors[] = 'no_comment';
		}

		if (Utils::entityStrlen(Utils::htmlspecialchars($this->comment)) > 254) {
			$post_errors[] = 'post_too_long';
		}

		// Any errors?
		if (!empty($post_errors)) {
			Lang::load('Errors');

			Utils::$context['post_errors'] = [];

			foreach ($post_errors as $post_error) {
				Utils::$context['post_errors'][$post_error] = Lang::$txt['error_' . $post_error];
			}

			$this->previewing = false;
			$this->show();

			return;
		}

		if (isset($_POST['msg'])) {
			$this->reportMsg($_POST['msg']);
		} else {
			$this->reportMember($_POST['u']);
		}
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
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

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
	 * Backward compatibility wrapper for the submit sub-action.
	 * In theory, no modifications should ever have called this, but...
	 */
	public static function ReportToModerator2(): void
	{
		self::load();
		self::$obj->subaction = 'submit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the reportMsg() method.
	 * In theory, no modifications should ever have called this, but...
	 */
	public static function reportPost($msg, $reason): void
	{
		$_POST['msg'] = (int) $msg;
		$_POST['comment'] = Utils::htmlspecialcharsDecode((string) $reason);

		self::load();
		self::$obj->subaction = 'submit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the reportMember() method.
	 * In theory, no modifications should ever have called this, but...
	 */
	public static function reportUser($id_member, $reason): void
	{
		$_POST['u'] = (int) $id_member;
		$_POST['comment'] = Utils::htmlspecialcharsDecode((string) $reason);

		self::load();
		self::$obj->subaction = 'submit';
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
		Utils::$context['robot_no_index'] = true;
		Utils::$context['comment_body'] = '';

		if (isset($_POST['comment'])) {
			$this->comment = trim(Utils::normalizeSpaces(Utils::sanitizeChars(Utils::normalize($_POST['comment']), 0), true, true));
		}

		$this->previewing = isset($_POST['preview']) && $this->comment !== '';
		$this->submitting = isset($_POST['save']);
		$this->can_submit = isset($_POST[Utils::$context['session_var']]);

		if ($this->submitting && $this->can_submit && !$this->previewing) {
			$this->subaction = 'submit';
		}
	}

	/**
	 * Sets Utils::$context['preview_message'] based on $this->comment.
	 */
	protected function setPreview()
	{
		// Set up the preview message.
		Utils::$context['preview_message'] = Utils::htmlspecialchars($this->comment, ENT_QUOTES);
		Msg::preparsecode(Utils::$context['preview_message']);

		// We censor for your protection...
		Lang::censorText(Utils::$context['preview_message']);
	}

	/**
	 * Actually reports a post using information specified from a form
	 *
	 * @param int $msg The ID of the post being reported
	 */
	protected function reportMsg(int $msg)
	{
		// Get the basic topic information, and make sure they can see it.
		$request = Db::$db->query(
			'',
			'SELECT m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
				'id_msg' => $msg,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_board', false);
		}
		$message = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		$request = Db::$db->query(
			'',
			'SELECT id_report, ignore_all
			FROM {db_prefix}log_reported
			WHERE id_msg = {int:id_msg}
				AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
			ORDER BY ignore_all DESC',
			[
				'id_msg' => $msg,
				'not_closed' => 0,
				'ignored' => 1,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			list($id_report, $ignore) = Db::$db->fetch_row($request);
		}

		Db::$db->free_result($request);

		// If we're just going to ignore these, then who gives a monkeys...
		if (!empty($ignore)) {
			Utils::redirectexit('topic=' . Topic::$topic_id . '.msg' . $msg . '#msg' . $msg);
		}

		// Already reported? My god, we could be dealing with a real rogue here...
		if (!empty($id_report)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_reported
				SET num_reports = num_reports + 1, time_updated = {int:current_time}
				WHERE id_report = {int:id_report}',
				[
					'current_time' => time(),
					'id_report' => $id_report,
				],
			);
		}
		// Otherwise, we shall make one!
		else {
			if (empty($message['real_name'])) {
				$message['real_name'] = $message['poster_name'];
			}

			$id_report = Db::$db->insert(
				'',
				'{db_prefix}log_reported',
				[
					'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
					'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
					'num_reports' => 'int', 'closed' => 'int',
				],
				[
					$msg, $message['id_topic'], $message['id_board'], $message['id_poster'], $message['real_name'],
					$message['subject'], $message['body'], time(), time(), 1, 0,
				],
				['id_report'],
				1,
			);
		}

		// Now just add our report...
		if ($id_report) {
			$id_comment = Db::$db->insert(
				'',
				'{db_prefix}log_reported_comments',
				[
					'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string',
					'member_ip' => 'inet', 'comment' => 'string', 'time_sent' => 'int',
				],
				[
					$id_report, User::$me->id, User::$me->name,
					User::$me->ip, Utils::htmlspecialchars($this->comment), time(),
				],
				['id_comment'],
				1,
			);

			// And get ready to notify people.
			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\MsgReport_Notify',
					Utils::jsonEncode([
						'report_id' => $id_report,
						'msg_id' => $msg,
						'topic_id' => $message['id_topic'],
						'board_id' => $message['id_board'],
						'sender_id' => User::$me->id,
						'sender_name' => User::$me->name,
						'time' => time(),
						'comment_id' => $id_comment,
					]),
					0,
				],
				['id_task'],
			);
		}

		// Keep track of when the mod reports get updated, that way we know when we need to look again.
		Config::updateModSettings(['last_mod_report_action' => time()]);

		// Back to the post we reported!
		Utils::redirectexit('reportsent;topic=' . Topic::$topic_id . '.msg' . $msg . '#msg' . $msg);
	}

	/**
	 * Actually reports a user's profile using information specified from a form
	 *
	 * @param int $id_member The ID of the member whose profile is being reported
	 */
	protected function reportMember($id_member)
	{
		// Get the basic topic information, and make sure they can see it.
		$_POST['u'] = (int) $id_member;

		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name, member_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			[
				'id_member' => $_POST['u'],
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_user', false);
		}
		$user = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		$user_name = Utils::htmlspecialcharsDecode($user['real_name']) . ($user['real_name'] != $user['member_name'] ? ' (' . $user['member_name'] . ')' : '');

		$request = Db::$db->query(
			'',
			'SELECT id_report, ignore_all
			FROM {db_prefix}log_reported
			WHERE id_member = {int:id_member}
				AND id_msg = {int:not_a_reported_post}
				AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
			ORDER BY ignore_all DESC',
			[
				'id_member' => $_POST['u'],
				'not_a_reported_post' => 0,
				'not_closed' => 0,
				'ignored' => 1,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			list($id_report, $ignore) = Db::$db->fetch_row($request);
		}

		Db::$db->free_result($request);

		// If we're just going to ignore these, then who gives a monkeys...
		if (!empty($ignore)) {
			Utils::redirectexit('action=profile;u=' . $_POST['u']);
		}

		// Already reported? My god, we could be dealing with a real rogue here...
		if (!empty($id_report)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_reported
				SET num_reports = num_reports + 1, time_updated = {int:current_time}
				WHERE id_report = {int:id_report}',
				[
					'current_time' => time(),
					'id_report' => $id_report,
				],
			);
		}
		// Otherwise, we shall make one!
		else {
			$id_report = Db::$db->insert(
				'',
				'{db_prefix}log_reported',
				[
					'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
					'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
					'num_reports' => 'int', 'closed' => 'int',
				],
				[
					0, 0, 0, $user['id_member'], $user_name,
					'', '', time(), time(), 1, 0,
				],
				['id_report'],
				1,
			);
		}

		// Now just add our report...
		if ($id_report) {
			Db::$db->insert(
				'',
				'{db_prefix}log_reported_comments',
				[
					'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string',
					'member_ip' => 'inet', 'comment' => 'string', 'time_sent' => 'int',
				],
				[
					$id_report, User::$me->id, User::$me->name,
					User::$me->ip, Utils::htmlspecialchars($this->comment), time(),
				],
				['id_comment'],
			);

			// And get ready to notify people.
			Db::$db->insert(
				'insert',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\MemberReport_Notify',
					Utils::jsonEncode([
						'report_id' => $id_report,
						'user_id' => $user['id_member'],
						'user_name' => $user_name,
						'sender_id' => User::$me->id,
						'sender_name' => User::$me->name,
						'comment' => Utils::htmlspecialchars($this->comment),
						'time' => time(),
					]),
					0,
				],
				['id_task'],
			);
		}

		// Keep track of when the mod reports get updated, that way we know when we need to look again.
		Config::updateModSettings(['last_mod_report_action' => time()]);

		// Back to the profile we reported!
		Utils::redirectexit('reportsent;action=profile;u=' . $id_member);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ReportToMod::exportStatic')) {
	ReportToMod::exportStatic();
}

?>