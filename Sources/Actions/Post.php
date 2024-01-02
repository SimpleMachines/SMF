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

use SMF\Attachment;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Draft;
use SMF\Editor;
use SMF\ErrorHandler;
use SMF\Event;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Msg;
use SMF\Poll;
use SMF\Security;
use SMF\Theme;
use SMF\Time;
use SMF\TimeZone;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * This class handles posting and modifying replies and new topics.
 */
class Post implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'post' => 'Post',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Constants to indicate what the user intends to do with this post.
	 */
	public const INTENT_NEW_TOPIC = 0;
	public const INTENT_NEW_REPLY = 1;
	public const INTENT_EDIT_POST = 2;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The sub-action to call.
	 */
	public string $subaction = 'show';

	/**
	 * @var array
	 *
	 * Errors encountered while trying to post.
	 */
	public array $errors = [];

	/**
	 * @var array
	 *
	 * There are two error types: serious and minor. Serious errors
	 * actually tell the user that a real error has occurred, while minor
	 * errors are like warnings that let them know that something with
	 * their post isn't right.
	 */
	public array $minor_errors = [
		'not_approved',
		'new_replies',
		'old_topic',
		'need_qr_verification',
		'no_subject',
		'topic_locked',
		'topic_unlocked',
		'topic_stickied',
		'topic_unstickied',
		'cannot_post_attachment',
	];

	/**
	 * @var string
	 *
	 * The subject string to show in the editor form.
	 */
	public string $form_subject = '';

	/**
	 * @var string
	 *
	 * The message body string to show in the editor form.
	 */
	public string $form_message = '';

	/**
	 * @var bool
	 *
	 * Whether the current user can approve posts.
	 */
	public bool $can_approve = false;

	/**
	 * @var bool
	 *
	 * Whether this post will become approved upon submission.
	 */
	public bool $becomes_approved = true;

	/**
	 * @var int
	 *
	 * What is the user trying to do with this post?
	 */
	public int $intent = self::INTENT_NEW_TOPIC;

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
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * The boards that this post can be submitted to.
	 *
	 * If a board was specified in the URL parameters, this will contain the ID
	 * of that board. Otherwise, it will contain a list of boards that the user
	 * is allowed to post in.
	 */
	protected array $boards = [];

	/**
	 * @var array
	 *
	 * A list of categories and boards produced by MessageIndex::getBoardList().
	 * Not used if a board was specified in the URL parameters.
	 */
	protected array $board_list = [];

	/**
	 * @var bool
	 *
	 * Whether the current topic is locked.
	 */
	protected bool $locked = false;

	/**
	 * @var int
	 *
	 * Used by getTopicSummary() method to count previous posts.
	 */
	protected int $counter = 0;

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
	 * Dispatcher.
	 *
	 * Does a little bit more setup, then calls $this->show().
	 */
	public function execute(): void
	{
		$this->can_approve = User::$me->allowedTo('approve_posts');

		// If there is an existing topic, load it.
		if ($this->intent !== self::INTENT_NEW_TOPIC) {
			$this->loadTopic();
		}

		// Allow mods to add new sub-actions.
		IntegrationHook::call('integrate_post_subactions', [&self::$subactions]);

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Handles showing the post screen, loading the post to be modified, and
	 * loading any post quoted.
	 *
	 * Additionally handles previews of posts.
	 * Uses the Post template and language file, main sub template.
	 * Requires different permissions depending on the actions, but most notably
	 *     post_new, post_reply_own, and post_reply_any.
	 * Shows options for the editing and posting of calendar events and
	 *     attachments, as well as the posting of polls.
	 * Accessed from ?action=post.
	 */
	public function show(): void
	{
		Lang::load('Post');

		if (!empty(Config::$modSettings['drafts_post_enabled'])) {
			Lang::load('Drafts');
		}

		// You can't reply with a poll... hacker.
		if (isset($_REQUEST['poll']) && !empty(Topic::$topic_id) && !isset($_REQUEST['msg'])) {
			unset($_REQUEST['poll']);
		}

		// Posting an event?
		Utils::$context['make_event'] = isset($_REQUEST['calendar']);
		Utils::$context['robot_no_index'] = true;

		IntegrationHook::call('integrate_post_start');

		// Where are we posting this (or where might we)?
		$this->setBoards();

		if (isset($_REQUEST['xml'])) {
			Utils::$context['sub_template'] = 'post';

			// Just in case of an earlier error...
			Utils::$context['preview_message'] = '';
			Utils::$context['preview_subject'] = '';
		}

		// Get notification preferences for later.
		$this->loadNotifyPrefs();

		// Replying to an existing topic.
		if ($this->intent !== self::INTENT_NEW_TOPIC) {
			$this->initiateReply();
		}
		// Starting a new topic.
		else {
			$this->initiateNewTopic();
		}

		// Don't allow a post if it's locked and you aren't all powerful.
		if ($this->locked && !User::$me->allowedTo('moderate_board')) {
			ErrorHandler::fatalLang('topic_locked', false);
		}

		Utils::$context['notify'] = !empty(Utils::$context['notify']);

		Utils::$context['can_notify'] = !User::$me->is_guest;
		Utils::$context['move'] = !empty($_REQUEST['move']);
		Utils::$context['announce'] = !empty($_REQUEST['announce']);
		Utils::$context['locked'] = !empty($this->locked) || !empty($_REQUEST['lock']);
		Utils::$context['can_quote'] = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));

		// An array to hold all the attachments for this topic.
		Utils::$context['current_attachments'] = [];

		// Does the user want to create a poll?
		$this->initiatePoll();

		// Does the user want to create an event?
		$this->initiateEvent();

		// See if any new replies have come along.
		$this->checkForNewReplies();

		// Get a response prefix (like 'Re:') in the default forum language.
		$this->setResponsePrefix();

		// Previewing, modifying, or posting?
		if (isset($_REQUEST['message']) || isset($_REQUEST['quickReply']) || !empty(Utils::$context['post_error'])) {
			$this->showPreview();
		}
		// Editing a message...
		elseif ($this->intent === self::INTENT_EDIT_POST) {
			$this->showEdit();
		}
		// Posting...
		else {
			$this->showNew();
		}

		// Prepare any existing attachments for display.
		$this->showAttachments();

		// Do we need to show the visual verification image?
		$this->showVerification();

		// Did any errors occur?
		$this->checkForErrors();

		// What are you doing? Posting a poll, modifying, previewing, new post, or reply...
		$this->setPageTitle();

		// Build the link tree.
		$this->setLinktree();

		Utils::$context['subject'] = addcslashes($this->form_subject, '"');
		Utils::$context['message'] = str_replace(['"', '<', '>', '&nbsp;'], ['&quot;', '&lt;', '&gt;', ' '], $this->form_message);

		// Are there any drafts to show in the editor?
		$this->loadDrafts();

		// Load the editor.
		$this->loadEditor();

		$this->setMessageIcons();

		$this->getTopicSummary();

		Utils::$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
		Utils::$context['show_additional_options'] = !empty($_POST['additional_options']) || isset($_GET['additionalOptions']);

		Utils::$context['is_new_topic'] = empty(Topic::$info->id);
		Utils::$context['is_new_post'] = !isset($_REQUEST['msg']);
		Utils::$context['is_first_post'] = Utils::$context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == Topic::$info->id_first_msg);

		// Register this form in the session variables.
		Security::checkSubmitOnce('register');

		// Mentions
		if (!empty(Config::$modSettings['enable_mentions']) && User::$me->allowedTo('mention')) {
			Theme::loadJavaScriptFile('jquery.caret.min.js', ['defer' => true], 'smf_caret');
			Theme::loadJavaScriptFile('jquery.atwho.min.js', ['defer' => true], 'smf_atwho');
			Theme::loadJavaScriptFile('mentions.js', ['defer' => true, 'minimize' => true], 'smf_mentions');
		}

		// Load the drafts.js file
		if (Utils::$context['drafts_autosave']) {
			Theme::loadJavaScriptFile('drafts.js', ['defer' => false, 'minimize' => true], 'smf_drafts');
		}

		// quotedText.js
		Theme::loadJavaScriptFile('quotedText.js', ['defer' => true, 'minimize' => true], 'smf_quotedText');

		// Knowing the current board ID might be handy.
		Theme::addInlineJavaScript('
		var current_board = ' . (empty(Utils::$context['current_board']) ? 'null' : Utils::$context['current_board']) . ';', false);

		// Set up the fields for the posting form header.
		$this->setupPostingFields();

		// Finally, load the template.
		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('Post');
		}

		IntegrationHook::call('integrate_post_end');
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
	 * Backward compatibility wrapper.
	 *
	 * Needed to allow old mods to pass $post_errors as a function parameter.
	 */
	public static function post($post_errors = []): void
	{
		self::load();
		self::$obj->errors = (array) $post_errors;
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
		// Add references to some properties to Utils::$context.
		Utils::$context['becomes_approved'] = &$this->becomes_approved;

		// What exactly is the user trying to do?
		$this->intent = !empty($_REQUEST['msg']) ? self::INTENT_EDIT_POST : (!empty(Topic::$topic_id) ? self::INTENT_NEW_REPLY : self::INTENT_NEW_TOPIC);
	}

	/**
	 * Get the topic for display purposes.
	 *
	 * Gets a summary of the most recent posts in a topic.
	 * Depends on the topicSummaryPosts setting.
	 * If you are editing a post, only shows posts previous to that post.
	 * Puts results into Utils::$context['previous_posts'].
	 */
	protected function getTopicSummary(): void
	{
		if (empty(Topic::$info->id) || empty(Config::$modSettings['topicSummaryPosts'])) {
			return;
		}

		if (isset($_REQUEST['xml'])) {
			$limit = '
			LIMIT ' . (empty(Utils::$context['new_replies']) ? '0' : Utils::$context['new_replies']);
		} else {
			$limit = empty(Config::$modSettings['topicSummaryPosts']) ? '' : '
			LIMIT ' . (int) Config::$modSettings['topicSummaryPosts'];
		}

		// If you're modifying, get only those posts before the current one. (otherwise get all.)
		Utils::$context['previous_posts'] = [];
		$request = Db::$db->query(
			'',
			'SELECT
				COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
				m.body, m.smileys_enabled, m.id_msg, m.id_member
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_topic = {int:current_topic}' . (isset($_REQUEST['msg']) ? '
				AND m.id_msg < {int:id_msg}' : '') . (!Config::$modSettings['postmod_active'] || $this->can_approve ? '' : '
				AND m.approved = {int:approved}') . '
			ORDER BY m.id_msg DESC' . $limit,
			[
				'current_topic' => Topic::$topic_id,
				'id_msg' => isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0,
				'approved' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Censor, BBC, ...
			Lang::censorText($row['body']);

			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			IntegrationHook::call('integrate_getTopic_previous_post', [&$row]);

			// ...and store.
			Utils::$context['previous_posts'][] = [
				'counter' => $this->counter++,
				'poster' => $row['poster_name'],
				'message' => $row['body'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'id' => $row['id_msg'],
				'is_new' => !empty(Utils::$context['new_replies']),
				'is_ignored' => !empty(Config::$modSettings['enable_buddylist']) && !empty(Theme::$current->options['posts_apply_ignore_list']) && in_array($row['id_member'], User::$me->ignoreusers),
			];

			if (!empty(Utils::$context['new_replies'])) {
				Utils::$context['new_replies']--;
			}
		}
		Db::$db->free_result($request);
	}

	/**
	 * Get notification preferences for later use.
	 *
	 * Puts results in Utils::$context['notify'], Utils::$context['auto_notify'],
	 * and Utils::$context['notify_prefs'].
	 */
	protected function loadNotifyPrefs(): void
	{
		if (!empty(Topic::$info->id)) {
			Utils::$context['notify_prefs'] = Topic::$info->getNotificationPrefs();
			Utils::$context['auto_notify'] = !empty(Topic::$info->notify_prefs['msg_auto_notify']);
		} else {
			Utils::$context['notify_prefs'] = Notify::getNotifyPrefs(User::$me->id, ['topic_notify', 'msg_auto_notify'], true);
			Utils::$context['auto_notify'] = !empty(Utils::$context['notify_prefs']['msg_auto_notify']);
		}

		Utils::$context['notify'] = !empty(Utils::$context['notify_prefs']['pref']);
	}

	/**
	 * Sets the values of $this->boards and, if applicable, $this->board_list.
	 */
	protected function setBoards(): void
	{
		// Not in a board? Fine, but we'll make them pick one eventually.
		if (empty(Board::$info->id) || Utils::$context['make_event']) {
			// Get ids of all the boards they can post in.
			$post_permissions = ['post_new'];

			if (Config::$modSettings['postmod_active']) {
				$post_permissions[] = 'post_unapproved_topics';
			}

			$this->boards = User::$me->boardsAllowedTo($post_permissions);

			if (empty($this->boards)) {
				ErrorHandler::fatalLang('cannot_post_new', false);
			}

			// Get a list of boards for the select menu
			$boardListOptions = [
				'included_boards' => in_array(0, $this->boards) ? null : $this->boards,
				'not_redirection' => true,
				'use_permissions' => true,
				'selected_board' => !empty(Board::$info->id) ? Board::$info->id : (Utils::$context['make_event'] && !empty(Config::$modSettings['cal_defaultboard']) ? Config::$modSettings['cal_defaultboard'] : $this->boards[0]),
			];

			$this->board_list = MessageIndex::getBoardList($boardListOptions);
		}
		// Let's keep things simple for ourselves.
		else {
			$this->boards = [Board::$info->id];
		}
	}

	/**
	 * Loads the topic that is being replied to.
	 *
	 * If necessary, finds the topic ID based on $_REQUEST['msg'].
	 */
	protected function loadTopic(): void
	{
		if (empty(Topic::$topic_id) && !empty($_REQUEST['msg'])) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:msg}
				LIMIT 1',
				[
					'msg' => (int) $_REQUEST['msg'],
				],
			);

			if (Db::$db->num_rows($request) != 1) {
				unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);
			} else {
				list(Topic::$topic_id) = Db::$db->fetch_row($request);
			}
			Db::$db->free_result($request);
		}

		// We expected a topic, but we don't have one.
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('not_a_topic');
		}

		Topic::load(Topic::$topic_id);

		// Though the topic should be there, it might have vanished.
		if (empty(Topic::$info->id)) {
			ErrorHandler::fatalLang('topic_doesnt_exist', 404);
		}

		// Did this topic suddenly move? Just checking...
		if (isset(Board::$info->id) && Topic::$info->id_board != Board::$info->id) {
			ErrorHandler::fatalLang('not_a_topic');
		}
	}

	/**
	 *
	 */
	protected function initiateReply(): void
	{
		$this->locked = Topic::$info->is_locked;
		Utils::$context['topic_last_message'] = Topic::$info->id_last_msg;

		// If this topic already has a poll, they sure can't add another.
		if (isset($_REQUEST['poll']) && Topic::$info->id_poll > 0) {
			unset($_REQUEST['poll']);
		}

		// New reply.
		if ($this->intent === self::INTENT_NEW_REPLY) {
			// If guests can't post, kick them out.
			if (User::$me->is_guest && !User::$me->allowedTo('post_reply_any') && (!Config::$modSettings['postmod_active'] || !User::$me->allowedTo('post_unapproved_replies_any'))) {
				User::$me->kickIfGuest();
			}

			// By default the reply will be approved...
			$this->becomes_approved = true;

			if (Topic::$info->id_member_started != User::$me->id || User::$me->is_guest) {
				if (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_replies_any') && !User::$me->allowedTo('post_reply_any')) {
					$this->becomes_approved = false;
				} else {
					User::$me->isAllowedTo('post_reply_any');
				}
			} elseif (!User::$me->allowedTo('post_reply_any')) {
				if (Config::$modSettings['postmod_active'] && ((User::$me->allowedTo('post_unapproved_replies_own') && !User::$me->allowedTo('post_reply_own')) || User::$me->allowedTo('post_unapproved_replies_any'))) {
					$this->becomes_approved = false;
				} else {
					User::$me->isAllowedTo('post_reply_own');
				}
			}
		}
		// Modifying an existing reply.
		else {
			$this->becomes_approved = true;
		}

		// What options should we show?
		Utils::$context['can_lock'] = User::$me->allowedTo('lock_any') || (User::$me->id == Topic::$info->id_member_started && User::$me->allowedTo('lock_own'));
		Utils::$context['can_sticky'] = User::$me->allowedTo('make_sticky');
		Utils::$context['can_move'] = User::$me->allowedTo('move_any');
		// You can only announce topics that will get approved...
		Utils::$context['can_announce'] = User::$me->allowedTo('announce_topic') && $this->becomes_approved;
		Utils::$context['show_approval'] = !$this->can_approve ? 0 : ($this->becomes_approved ? 2 : 1);

		// We don't always want the request vars to override what's in the db...
		Utils::$context['already_locked'] = $this->locked;
		Utils::$context['already_sticky'] = Topic::$info->is_sticky;
		Utils::$context['sticky'] = isset($_REQUEST['sticky']) ? !empty($_REQUEST['sticky']) : Topic::$info->is_sticky;

		// Check whether this is a really old post being bumped...
		if (!empty(Config::$modSettings['oldTopicDays']) && Topic::$info->updated_timestamp + Config::$modSettings['oldTopicDays'] * 86400 < time() && empty(Topic::$info->is_sticky) && !isset($_REQUEST['subject'])) {
			$this->errors[] = ['old_topic', [Config::$modSettings['oldTopicDays']]];
		}
	}

	/**
	 *
	 */
	protected function initiateNewTopic(): void
	{
		// @todo Should use JavaScript to hide and show the warning based on the selection in the board select menu
		$this->becomes_approved = true;

		if (Config::$modSettings['postmod_active'] && !User::$me->allowedTo('post_new', $this->boards, true) && User::$me->allowedTo('post_unapproved_topics', $this->boards, true)) {
			$this->becomes_approved = false;
		} else {
			User::$me->isAllowedTo('post_new', $this->boards, true);
		}

		$this->locked = 0;
		Utils::$context['already_locked'] = 0;
		Utils::$context['already_sticky'] = 0;
		Utils::$context['sticky'] = !empty($_REQUEST['sticky']);

		// What options should we show?
		Utils::$context['can_lock'] = User::$me->allowedTo(['lock_any', 'lock_own'], $this->boards, true);
		Utils::$context['can_sticky'] = User::$me->allowedTo('make_sticky', $this->boards, true);
		Utils::$context['can_move'] = User::$me->allowedTo('move_any', $this->boards, true);
		Utils::$context['can_announce'] = User::$me->allowedTo('announce_topic', $this->boards, true) && $this->becomes_approved;
		Utils::$context['show_approval'] = !User::$me->allowedTo('approve_posts', $this->boards, true) ? 0 : ($this->becomes_approved ? 2 : 1);
	}

	/**
	 *
	 */
	protected function initiatePoll(): void
	{
		// Check the users permissions - is the user allowed to add or post a poll?
		if (!isset($_REQUEST['poll']) || Config::$modSettings['pollMode'] != '1') {
			unset($_REQUEST['poll'], $_POST['poll'], $_GET['poll']);
			Utils::$context['make_poll'] = false;

			return;
		}

		// Make a new empty poll.
		$poll = Poll::create();

		Utils::$context['poll_options'] = $poll->format();
		Utils::$context['choices'] = &$poll->formatted['choices'];

		Utils::$context['make_poll'] = true;
	}

	/**
	 *
	 */
	protected function initiateEvent(): void
	{
		if (empty(Utils::$context['make_event'])) {
			return;
		}

		// They might want to pick a board.
		if (!isset(Utils::$context['current_board'])) {
			Utils::$context['current_board'] = 0;
		}

		// Start loading up the event info.
		if (isset($_REQUEST['eventid'])) {
			list(Utils::$context['event']) = Event::load((int) $_REQUEST['eventid']);
		}

		if (!(Utils::$context['event'] instanceof Event)) {
			Utils::$context['event'] = new Event(-1);
		}

		// Permissions check!
		User::$me->isAllowedTo('calendar_post');

		// We want a fairly compact version of the time, but as close as possible to the user's settings.
		$time_string = Time::getShortTimeFormat();

		// Editing an event?  (but NOT previewing!?)
		if (empty(Utils::$context['event']->new) && !isset($_REQUEST['subject'])) {
			// If the user doesn't have permission to edit the post in this topic, redirect them.
			if ((empty(Topic::$info->id_member_started) || Topic::$info->id_member_started != User::$me->id || !User::$me->allowedTo('modify_own')) && !User::$me->allowedTo('modify_any')) {
				$calendar_action = Calendar::load();
				$calendar_action->subaction = 'post';
				$calendar_action->execute();

				return;
			}
		} else {
			// Make sure the year and month are in the valid range.
			if (Utils::$context['event']->month < 1 || Utils::$context['event']->month > 12) {
				ErrorHandler::fatalLang('invalid_month', false);
			}

			if (Utils::$context['event']->year < Config::$modSettings['cal_minyear'] || Utils::$context['event']->year > Config::$modSettings['cal_maxyear']) {
				ErrorHandler::fatalLang('invalid_year', false);
			}

			Utils::$context['event']->categories = $this->board_list;
		}

		// An all day event? Set up some nice defaults in case the user wants to change that
		if (Utils::$context['event']->allday == true) {
			Utils::$context['event']->tz = User::getTimezone();
			Utils::$context['event']->start->modify(Time::create('now')->format('%H:%M:%S'));
			Utils::$context['event']->end->modify(Time::create('now + 1 hour')->format('%H:%M:%S'));
		}

		// Need this so the user can select a timezone for the event.
		Utils::$context['all_timezones'] = TimeZone::list(Utils::$context['event']->start_date);

		// If the event's timezone is not in SMF's standard list of time zones, try to fix it.
		Utils::$context['event']->fixTimezone();

		Calendar::loadDatePicker('#event_time_input .date_input');
		Calendar::loadTimePicker('#event_time_input .time_input', $time_string);
		Calendar::loadDatePair('#event_time_input', 'date_input', 'time_input');
		Theme::addInlineJavaScript('
		$("#allday").click(function(){
			$("#start_time").attr("disabled", this.checked);
			$("#end_time").attr("disabled", this.checked);
			$("#tz").attr("disabled", this.checked);
		});	', true);

		Utils::$context['event']->board = !empty(Board::$info->id) ? Board::$info->id : Config::$modSettings['cal_defaultboard'];
		Utils::$context['event']->topic = !empty(Topic::$info->id) ? Topic::$info->id : 0;
	}

	/**
	 *
	 */
	protected function checkForNewReplies(): void
	{
		// Only check if the user is trying to submit a new reply to a topic.
		if (!empty($_REQUEST['msg']) || empty(Topic::$info->id)) {
			return;
		}

		// Only check if the user wants to be warned about new replies.
		if (!empty(Theme::$current->options['no_new_reply_warning'])) {
			return;
		}

		// Is the last post when they started writing still the last post now?
		if (!isset($_REQUEST['last_msg']) || Topic::$info->id_last_msg <= $_REQUEST['last_msg']) {
			return;
		}

		// Figure out how many new replies were made while the user was writing.
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg > {int:last_msg}' . (!Config::$modSettings['postmod_active'] || $this->can_approve ? '' : '
				AND approved = {int:approved}') . '
			LIMIT 1',
			[
				'current_topic' => Topic::$info->id,
				'last_msg' => (int) $_REQUEST['last_msg'],
				'approved' => 1,
			],
		);
		list(Utils::$context['new_replies']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (!empty(Utils::$context['new_replies'])) {
			if (Utils::$context['new_replies'] == 1) {
				Lang::$txt['error_new_replies'] = isset($_GET['last_msg']) ? Lang::$txt['error_new_reply_reading'] : Lang::$txt['error_new_reply'];
			} else {
				Lang::$txt['error_new_replies'] = sprintf(isset($_GET['last_msg']) ? Lang::$txt['error_new_replies_reading'] : Lang::$txt['error_new_replies'], Utils::$context['new_replies']);
			}

			$this->errors[] = 'new_replies';

			Config::$modSettings['topicSummaryPosts'] = Utils::$context['new_replies'] > Config::$modSettings['topicSummaryPosts'] ? max(Config::$modSettings['topicSummaryPosts'], 5) : Config::$modSettings['topicSummaryPosts'];
		}
	}

	/**
	 *
	 */
	protected function setResponsePrefix(): void
	{
		if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix'))) {
			if (Lang::$default === User::$me->language) {
				Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
			} else {
				Lang::load('index', Lang::$default, false);
				Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
				Lang::load('index');
			}
			CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
		}
	}

	/**
	 *
	 */
	protected function showPreview(): void
	{
		if (isset($_REQUEST['quickReply'])) {
			$_REQUEST['message'] = $_REQUEST['quickReply'];
		}

		// Validate inputs.
		if (empty(Utils::$context['post_error'])) {
			// This means they didn't click Post and get an error.
			$really_previewing = true;
		} else {
			if (!isset($_REQUEST['subject'])) {
				$_REQUEST['subject'] = '';
			}

			if (!isset($_REQUEST['message'])) {
				$_REQUEST['message'] = '';
			}

			if (!isset($_REQUEST['icon'])) {
				$_REQUEST['icon'] = 'xx';
			}

			// They are previewing if they asked to preview (i.e. came from quick reply).
			$really_previewing = !empty($_POST['preview']);
		}

		// In order to keep the approval status flowing through, we have to pass it through the form...
		$this->becomes_approved = empty($_REQUEST['not_approved']);
		Utils::$context['show_approval'] = isset($_REQUEST['approve']) ? ($_REQUEST['approve'] ? 2 : 1) : ($this->can_approve ? 2 : 0);
		Utils::$context['can_announce'] &= $this->becomes_approved;

		// Set up the inputs for the form.
		$this->form_subject = strtr(Utils::htmlspecialchars($_REQUEST['subject']), ["\r" => '', "\n" => '', "\t" => '']);
		$this->form_message = Utils::htmlspecialchars($_REQUEST['message'], ENT_QUOTES);

		// Make sure the subject isn't too long - taking into account special characters.
		if (Utils::entityStrlen($this->form_subject) > 100) {
			$this->form_subject = Utils::entitySubstr($this->form_subject, 0, 100);
		}

		if (isset($_REQUEST['poll'])) {
			Utils::$context['question'] = isset($_REQUEST['question']) ? Utils::htmlspecialchars(trim($_REQUEST['question'])) : '';

			Utils::$context['choices'] = [];
			$choice_id = 0;

			$_POST['options'] = empty($_POST['options']) ? [] : Utils::htmlspecialcharsRecursive($_POST['options']);

			foreach ($_POST['options'] as $option) {
				if (trim($option) == '') {
					continue;
				}

				Utils::$context['choices'][] = [
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => $option,
					'is_last' => false,
				];
			}

			// One empty option for those with js disabled...I know are few... :P
			Utils::$context['choices'][] = [
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => '',
				'is_last' => false,
			];

			if (count(Utils::$context['choices']) < 2) {
				Utils::$context['choices'][] = [
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => '',
					'is_last' => false,
				];
			}
			Utils::$context['last_choice_id'] = $choice_id;
			Utils::$context['choices'][count(Utils::$context['choices']) - 1]['is_last'] = true;
		}

		// Are you... a guest?
		if (User::$me->is_guest) {
			$_REQUEST['guestname'] = !isset($_REQUEST['guestname']) ? '' : trim($_REQUEST['guestname']);
			$_REQUEST['email'] = !isset($_REQUEST['email']) ? '' : trim($_REQUEST['email']);

			$_REQUEST['guestname'] = Utils::htmlspecialchars($_REQUEST['guestname']);
			Utils::$context['name'] = $_REQUEST['guestname'];
			$_REQUEST['email'] = Utils::htmlspecialchars($_REQUEST['email']);
			Utils::$context['email'] = $_REQUEST['email'];

			User::$me->name = $_REQUEST['guestname'];
		}

		// Only show the preview stuff if they hit Preview.
		if (($really_previewing == true || isset($_REQUEST['xml'])) && !isset($_REQUEST['save_draft'])) {
			// Set up the preview message and subject and censor them...
			Utils::$context['preview_message'] = $this->form_message;
			Msg::preparsecode($this->form_message, true);
			Msg::preparsecode(Utils::$context['preview_message']);

			// Do all bulletin board code tags, with or without smileys.
			Utils::$context['preview_message'] = BBCodeParser::load()->parse(Utils::$context['preview_message'], !isset($_REQUEST['ns']));
			Lang::censorText(Utils::$context['preview_message']);

			if ($this->form_subject != '') {
				Utils::$context['preview_subject'] = $this->form_subject;

				Lang::censorText(Utils::$context['preview_subject']);
			} else {
				Utils::$context['preview_subject'] = '<em>' . Lang::$txt['no_subject'] . '</em>';
			}

			IntegrationHook::call('integrate_preview_post', [&$this->form_message, &$this->form_subject]);

			// Protect any CDATA blocks.
			if (isset($_REQUEST['xml'])) {
				Utils::$context['preview_message'] = strtr(Utils::$context['preview_message'], [']]>' => ']]]]><![CDATA[>']);
			}
		}

		// Set up the checkboxes.
		Utils::$context['notify'] = !empty($_REQUEST['notify']);
		Utils::$context['use_smileys'] = !isset($_REQUEST['ns']);

		Utils::$context['icon'] = isset($_REQUEST['icon']) ? preg_replace('~[./\\\\*\':"<>]~', '', $_REQUEST['icon']) : 'xx';

		// Set the destination action for submission.
		Utils::$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . $_REQUEST['msg'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] : '') . (isset($_REQUEST['poll']) ? ';poll' : '');

		Utils::$context['submit_label'] = isset($_REQUEST['msg']) ? Lang::$txt['save'] : Lang::$txt['post'];

		// Previewing an edit?
		if (isset($_REQUEST['msg']) && !empty(Topic::$info->id)) {
			// Get the existing message. Previewing.
			$request = Db::$db->query(
				'',
				'SELECT
					m.id_member, m.modified_time, m.smileys_enabled, m.body,
					m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
					t.id_member_started AS id_member_poster,
					m.poster_time, log.id_action, t.id_first_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
					LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
				WHERE m.id_msg = {int:id_msg}
					AND m.id_topic = {int:current_topic}',
				[
					'current_topic' => Topic::$info->id,
					'id_msg' => $_REQUEST['msg'],
					'announce_action' => 'announce_topic',
				],
			);

			// The message they were trying to edit was most likely deleted.
			// @todo Change this error message?
			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('no_board', false);
			}
			$row = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			if ($row['id_member'] == User::$me->id && !User::$me->allowedTo('modify_any')) {
				// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
				if ($row['approved'] && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time()) {
					ErrorHandler::fatalLang('modify_post_time_passed', false);
				} elseif ($row['id_member_poster'] == User::$me->id && !User::$me->allowedTo('modify_own')) {
					User::$me->isAllowedTo('modify_replies');
				} else {
					User::$me->isAllowedTo('modify_own');
				}
			} elseif ($row['id_member_poster'] == User::$me->id && !User::$me->allowedTo('modify_any')) {
				User::$me->isAllowedTo('modify_replies');
			} else {
				User::$me->isAllowedTo('modify_any');
			}

			if (Utils::$context['can_announce'] && !empty($row['id_action']) && $row['id_first_msg'] == $_REQUEST['msg']) {
				Lang::load('Errors');
				Utils::$context['post_error']['already_announced'] = Lang::$txt['error_topic_already_announced'];
			}

			if (!empty(Config::$modSettings['attachmentEnable'])) {
				Utils::$context['current_attachments'] = Attachment::loadByMsg($_REQUEST['msg'], Attachment::APPROVED_ANY);
			}

			// Allow moderators to change names....
			if (User::$me->allowedTo('moderate_forum') && !empty(Topic::$info->id)) {
				$request = Db::$db->query(
					'',
					'SELECT id_member, poster_name, poster_email
					FROM {db_prefix}messages
					WHERE id_msg = {int:id_msg}
						AND id_topic = {int:current_topic}
					LIMIT 1',
					[
						'current_topic' => Topic::$info->id,
						'id_msg' => (int) $_REQUEST['msg'],
					],
				);
				$row = Db::$db->fetch_assoc($request);
				Db::$db->free_result($request);

				if (empty($row['id_member'])) {
					Utils::$context['name'] = Utils::htmlspecialchars($row['poster_name']);
					Utils::$context['email'] = Utils::htmlspecialchars($row['poster_email']);
				}
			}
		}

		// No check is needed, since nothing is really posted.
		Security::checkSubmitOnce('free');
	}

	/**
	 *
	 */
	protected function showEdit(): void
	{
		Utils::$context['editing'] = true;

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Get the existing message. Editing.
		$request = Db::$db->query(
			'',
			'SELECT
				m.id_member, m.modified_time, m.modified_name, m.modified_reason, m.smileys_enabled, m.body,
				m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
				t.id_member_started AS id_member_poster,
				m.poster_time, log.id_action, t.id_first_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}',
			[
				'current_topic' => Topic::$info->id,
				'id_msg' => $_REQUEST['msg'],
				'announce_action' => 'announce_topic',
			],
		);

		// The message they were trying to edit was most likely deleted.
		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_message', false);
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		if ($row['id_member'] == User::$me->id && !User::$me->allowedTo('modify_any')) {
			// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
			if ($row['approved'] && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time()) {
				ErrorHandler::fatalLang('modify_post_time_passed', false);
			} elseif ($row['id_member_poster'] == User::$me->id && !User::$me->allowedTo('modify_own')) {
				User::$me->isAllowedTo('modify_replies');
			} else {
				User::$me->isAllowedTo('modify_own');
			}
		} elseif ($row['id_member_poster'] == User::$me->id && !User::$me->allowedTo('modify_any')) {
			User::$me->isAllowedTo('modify_replies');
		} else {
			User::$me->isAllowedTo('modify_any');
		}

		if (Utils::$context['can_announce'] && !empty($row['id_action']) && $row['id_first_msg'] == $_REQUEST['msg']) {
			Lang::load('Errors');
			Utils::$context['post_error']['already_announced'] = Lang::$txt['error_topic_already_announced'];
		}

		// When was it last modified?
		if (!empty($row['modified_time'])) {
			Utils::$context['last_modified'] = Time::create('@' . $row['modified_time'])->format();
			Utils::$context['last_modified_reason'] = Lang::censorText($row['modified_reason']);
			Utils::$context['last_modified_text'] = sprintf(Lang::$txt['last_edit_by'], Utils::$context['last_modified'], $row['modified_name']) . empty($row['modified_reason']) ? '' : '&nbsp;' . Lang::$txt['last_edit_reason'] . ':&nbsp;' . $row['modified_reason'];
		}

		// Get the stuff ready for the form.
		$this->form_subject = $row['subject'];
		$this->form_message = Msg::un_preparsecode($row['body']);
		Lang::censorText($this->form_message);
		Lang::censorText($this->form_subject);

		// Check the boxes that should be checked.
		Utils::$context['use_smileys'] = !empty($row['smileys_enabled']);
		Utils::$context['icon'] = $row['icon'];

		// Leave the approval checkbox unchecked by default for unapproved messages.
		if (!$row['approved'] && !empty(Utils::$context['show_approval'])) {
			Utils::$context['show_approval'] = 1;
		}

		// Load up 'em attachments!
		if (!empty(Config::$modSettings['attachmentEnable'])) {
			Utils::$context['current_attachments'] = Attachment::loadByMsg($_REQUEST['msg'], Attachment::APPROVED_ANY);
		}

		// Allow moderators to change names....
		if (User::$me->allowedTo('moderate_forum') && empty($row['id_member'])) {
			Utils::$context['name'] = Utils::htmlspecialchars($row['poster_name']);
			Utils::$context['email'] = Utils::htmlspecialchars($row['poster_email']);
		}

		// Set the destination.
		Utils::$context['destination'] = 'post2;start=' . $_REQUEST['start'] . ';msg=' . $_REQUEST['msg'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . (isset($_REQUEST['poll']) ? ';poll' : '');

		Utils::$context['submit_label'] = Lang::$txt['save'];
	}

	/**
	 *
	 */
	protected function showNew(): void
	{
		// By default....
		Utils::$context['use_smileys'] = true;
		Utils::$context['icon'] = 'xx';

		if (User::$me->is_guest) {
			Utils::$context['name'] = $_SESSION['guest_name'] ?? '';
			Utils::$context['email'] = $_SESSION['guest_email'] ?? '';
		}
		Utils::$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['poll']) ? ';poll' : '');

		Utils::$context['submit_label'] = Lang::$txt['post'];

		// Posting a quoted reply?
		if (!empty(Topic::$info->id) && !empty($_REQUEST['quote'])) {
			// Make sure they _can_ quote this post, and if so get it.
			$request = Db::$db->query(
				'',
				'SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!Config::$modSettings['postmod_active'] || $this->can_approve ? '' : '
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
				WHERE {query_see_message_board}
					AND m.id_msg = {int:id_msg}' . (!Config::$modSettings['postmod_active'] || $this->can_approve ? '' : '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}') . '
				LIMIT 1',
				[
					'id_msg' => (int) $_REQUEST['quote'],
					'is_approved' => 1,
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('quoted_post_deleted', false);
			}
			list($this->form_subject, $mname, $mdate, $this->form_message) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Add 'Re: ' to the front of the quoted subject.
			if (trim(Utils::$context['response_prefix']) != '' && Utils::entityStrpos($this->form_subject, trim(Utils::$context['response_prefix'])) !== 0) {
				$this->form_subject = Utils::$context['response_prefix'] . $this->form_subject;
			}

			// Censor the message and subject.
			Lang::censorText($this->form_message);
			Lang::censorText($this->form_subject);

			// But if it's in HTML world, turn them into htmlspecialchar's so they can be edited!
			if (strpos($this->form_message, '[html]') !== false) {
				$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $this->form_message, -1, PREG_SPLIT_DELIM_CAPTURE);

				for ($i = 0, $n = count($parts); $i < $n; $i++) {
					// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
					if ($i % 4 == 0) {
						$parts[$i] = preg_replace_callback(
							'~\[html\](.+?)\[/html\]~is',
							function ($m) {
								return '[html]' . preg_replace('~<br\s?/?' . '>~i', '&lt;br /&gt;<br>', "{$m[1]}") . '[/html]';
							},
							$parts[$i],
						);
					}
				}

				$this->form_message = implode('', $parts);
			}

			$this->form_message = preg_replace('~<br ?/?' . '>~i', "\n", $this->form_message);

			// Remove any nested quotes, if necessary.
			if (!empty(Config::$modSettings['removeNestedQuotes'])) {
				$this->form_message = preg_replace(['~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'], '', $this->form_message);
			}

			// Add a quote string on the front and end.
			$this->form_message = '[quote author=' . $mname . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $mdate . ']' . "\n" . rtrim($this->form_message) . "\n" . '[/quote]';
		}
		// Posting a reply without a quote?
		elseif (!empty(Topic::$info->id) && empty($_REQUEST['quote'])) {
			// Get the first message's subject.
			$this->form_subject = Topic::$info->subject;

			// Add 'Re: ' to the front of the subject.
			if (trim(Utils::$context['response_prefix']) != '' && $this->form_subject != '' && Utils::entityStrpos($this->form_subject, trim(Utils::$context['response_prefix'])) !== 0) {
				$this->form_subject = Utils::$context['response_prefix'] . $this->form_subject;
			}

			// Censor the subject.
			Lang::censorText($this->form_subject);

			$this->form_message = '';
		} else {
			$this->form_subject = $_GET['subject'] ?? '';
			$this->form_message = '';
		}
	}

	/**
	 *
	 */
	protected function showAttachments(): void
	{
		// Clear out prior attachment activity when starting afresh
		if (empty($_REQUEST['message']) && empty($_REQUEST['preview']) && !empty($_SESSION['already_attached'])) {
			foreach ($_SESSION['already_attached'] as $attachID => $attachment) {
				Attachment::remove(['id_attach' => $attachID]);
			}

			unset($_SESSION['already_attached']);
		}

		Utils::$context['can_post_attachment'] = !empty(Config::$modSettings['attachmentEnable']) && Config::$modSettings['attachmentEnable'] == 1 && (User::$me->allowedTo('post_attachment', $this->boards, true) || (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_attachments', $this->boards, true)));

		if (Utils::$context['can_post_attachment']) {
			// If there are attachments, calculate the total size and how many.
			Utils::$context['attachments']['total_size'] = 0;
			Utils::$context['attachments']['quantity'] = 0;

			// If this isn't a new post, check the current attachments.
			if (isset($_REQUEST['msg'])) {
				Utils::$context['attachments']['quantity'] = count(Utils::$context['current_attachments']);

				foreach (Utils::$context['current_attachments'] as $attachment) {
					Utils::$context['attachments']['total_size'] += $attachment['size'];
				}
			}

			// A bit of house keeping first.
			if (!empty($_SESSION['temp_attachments']) && count($_SESSION['temp_attachments']) == 1) {
				unset($_SESSION['temp_attachments']);
			}

			if (!empty($_SESSION['temp_attachments'])) {
				// Is this a request to delete them?
				if (isset($_GET['delete_temp'])) {
					foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
						if (strpos($attachID, 'post_tmp_' . User::$me->id) !== false) {
							if (file_exists($attachment['tmp_name'])) {
								unlink($attachment['tmp_name']);
							}
						}
					}

					$this->errors[] = 'temp_attachments_gone';
					$_SESSION['temp_attachments'] = [];
				}
				// Hmm, coming in fresh and there are files in session.
				elseif ($this->subaction != 'submit' || !empty($_POST['from_qr'])) {
					// Let's be nice and see if they belong here first.
					if ((empty($_REQUEST['msg']) && empty($_SESSION['temp_attachments']['post']['msg']) && $_SESSION['temp_attachments']['post']['board'] == (!empty(Board::$info->id) ? Board::$info->id : 0)) || (!empty($_REQUEST['msg']) && $_SESSION['temp_attachments']['post']['msg'] == $_REQUEST['msg'])) {
						// See if any files still exist before showing the warning message and the files attached.
						foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
							if (strpos($attachID, 'post_tmp_' . User::$me->id) === false) {
								continue;
							}

							if (file_exists($attachment['tmp_name'])) {
								$this->errors[] = 'temp_attachments_new';
								Utils::$context['files_in_session_warning'] = Lang::$txt['attached_files_in_session'];
								unset($_SESSION['temp_attachments']['post']['files']);
								break;
							}
						}
					} else {
						// Since, they don't belong here. Let's inform the user that they exist..
						if (!empty(Topic::$info->id)) {
							$delete_url = Config::$scripturl . '?action=post' . (!empty($_REQUEST['msg']) ? (';msg=' . $_REQUEST['msg']) : '') . (!empty($_REQUEST['last_msg']) ? (';last_msg=' . $_REQUEST['last_msg']) : '') . ';topic=' . Topic::$info->id . ';delete_temp';
						} else {
							$delete_url = Config::$scripturl . '?action=post' . (!empty(Board::$info->id) ? ';board=' . Board::$info->id : '') . ';delete_temp';
						}

						// Compile a list of the files to show the user.
						$file_list = [];

						foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
							if (strpos($attachID, 'post_tmp_' . User::$me->id) !== false) {
								$file_list[] = $attachment['name'];
							}
						}

						$_SESSION['temp_attachments']['post']['files'] = $file_list;
						$file_list = '<div class="attachments">' . implode('<br>', $file_list) . '</div>';

						if (!empty($_SESSION['temp_attachments']['post']['msg'])) {
							// We have a message id, so we can link back to the old topic they were trying to edit..
							$goback_url = Config::$scripturl . '?action=post' . (!empty($_SESSION['temp_attachments']['post']['msg']) ? (';msg=' . $_SESSION['temp_attachments']['post']['msg']) : '') . (!empty($_SESSION['temp_attachments']['post']['last_msg']) ? (';last_msg=' . $_SESSION['temp_attachments']['post']['last_msg']) : '') . ';topic=' . $_SESSION['temp_attachments']['post']['topic'] . ';additionalOptions';

							$this->errors[] = ['temp_attachments_found', [$delete_url, $goback_url, $file_list]];

							Utils::$context['ignore_temp_attachments'] = true;
						} else {
							$this->errors[] = ['temp_attachments_lost', [$delete_url, $file_list]];

							Utils::$context['ignore_temp_attachments'] = true;
						}
					}
				}

				if (!empty(Utils::$context['we_are_history'])) {
					$this->errors[] = Utils::$context['we_are_history'];
				}

				foreach ($_SESSION['temp_attachments'] as $attachID => $attachment) {
					if (isset(Utils::$context['ignore_temp_attachments']) || isset($_SESSION['temp_attachments']['post']['files'])) {
						break;
					}

					if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . User::$me->id) === false) {
						continue;
					}

					if ($attachID == 'initial_error') {
						Lang::$txt['error_attach_initial_error'] = Lang::$txt['attach_no_upload'] . '<div style="padding: 0 1em;">' . (is_array($attachment) ? vsprintf(Lang::$txt[$attachment[0]], (array) $attachment[1]) : Lang::$txt[$attachment]) . '</div>';

						$this->errors[] = 'attach_initial_error';

						unset($_SESSION['temp_attachments']);

						break;
					}

					// Show any errors which might have occurred.
					if (!empty($attachment['errors'])) {
						Lang::$txt['error_attach_errors'] = empty(Lang::$txt['error_attach_errors']) ? '<br>' : '';

						Lang::$txt['error_attach_errors'] .= sprintf(Lang::$txt['attach_warning'], $attachment['name']) . '<div style="padding: 0 1em;">';

						foreach ($attachment['errors'] as $error) {
							Lang::$txt['error_attach_errors'] .= (is_array($error) ? vsprintf(Lang::$txt[$error[0]], (array) $error[1]) : Lang::$txt[$error]) . '<br >';
						}

						Lang::$txt['error_attach_errors'] .= '</div>';

						$this->errors[] = 'attach_errors';

						// Take out the trash.
						unset($_SESSION['temp_attachments'][$attachID]);

						if (file_exists($attachment['tmp_name'])) {
							unlink($attachment['tmp_name']);
						}

						continue;
					}

					// More house keeping.
					if (!file_exists($attachment['tmp_name'])) {
						unset($_SESSION['temp_attachments'][$attachID]);

						continue;
					}

					Utils::$context['attachments']['quantity']++;
					Utils::$context['attachments']['total_size'] += $attachment['size'];

					if (!isset(Utils::$context['files_in_session_warning'])) {
						Utils::$context['files_in_session_warning'] = Lang::$txt['attached_files_in_session'];
					}

					Utils::$context['current_attachments'][$attachID] = [
						'name' => $attachment['name'],
						'size' => $attachment['size'],
						'attachID' => $attachID,
						'href' => Config::$scripturl . '?action=dlattach;attach=' . $attachID,
						'unchecked' => false,
						'approved' => 1,
						'mime_type' => '',
						'thumb' => 0,
					];
				}
			}
		}

		// Allow user to see previews for all of this post's attachments, even if the post hasn't been submitted yet.
		if (!isset($_SESSION['attachments_can_preview'])) {
			$_SESSION['attachments_can_preview'] = [];
		}

		if (!empty($_SESSION['already_attached'])) {
			$_SESSION['attachments_can_preview'] += array_fill_keys(array_keys($_SESSION['already_attached']), true);
		}

		foreach (Utils::$context['current_attachments'] as $attachID => $attachment) {
			$_SESSION['attachments_can_preview'][$attachID] = true;

			if (!empty($attachment['thumb'])) {
				$_SESSION['attachments_can_preview'][$attachment['thumb']] = true;
			}
		}

		// Previously uploaded attachments have 2 flavors:
		// - Existing post - at this point, now in Utils::$context['current_attachments']
		// - Just added, current session only - at this point, now in $_SESSION['already_attached']
		// We need to make sure *all* of these are in Utils::$context['current_attachments'], otherwise they won't show in dropzone during edits.
		if (!empty($_SESSION['already_attached'])) {
			Utils::$context['current_attachments'] = Attachment::load($_SESSION['already_attached'], Attachment::APPROVED_ANY, Attachment::TYPE_STANDARD);
		}

		// If the user can post attachments prepare the warning labels.
		if (Utils::$context['can_post_attachment']) {
			// If they've unchecked an attachment, they may still want to attach that many more files, but don't allow more than num_allowed_attachments.
			Utils::$context['num_allowed_attachments'] = empty(Config::$modSettings['attachmentNumPerPostLimit']) ? PHP_INT_MAX : Config::$modSettings['attachmentNumPerPostLimit'];

			Utils::$context['can_post_attachment_unapproved'] = User::$me->allowedTo('post_attachment');

			Utils::$context['attachment_restrictions'] = [];

			Utils::$context['allowed_extensions'] = !empty(Config::$modSettings['attachmentCheckExtensions']) ? (strtr(strtolower(Config::$modSettings['attachmentExtensions']), [',' => ', '])) : '';

			$attachmentRestrictionTypes = ['attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit'];

			foreach ($attachmentRestrictionTypes as $type) {
				if (!empty(Config::$modSettings[$type])) {
					Utils::$context['attachment_restrictions'][$type] = sprintf(Lang::$txt['attach_restrict_' . $type . (Config::$modSettings[$type] >= 1024 ? '_MB' : '')], Lang::numberFormat(Config::$modSettings[$type] >= 1024 ? Config::$modSettings[$type] / 1024 : Config::$modSettings[$type], 2));

					// Show the max number of attachments if not 0.
					if ($type == 'attachmentNumPerPostLimit') {
						Utils::$context['attachment_restrictions'][$type] .= ' (' . sprintf(Lang::$txt['attach_remaining'], max(Config::$modSettings['attachmentNumPerPostLimit'] - Utils::$context['attachments']['quantity'], 0)) . ')';
					} elseif ($type == 'attachmentPostLimit' && Utils::$context['attachments']['total_size'] > 0) {
						Utils::$context['attachment_restrictions'][$type] .= '<span class="attach_available"> (' . sprintf(Lang::$txt['attach_available'], round(max(Config::$modSettings['attachmentPostLimit'] - (Utils::$context['attachments']['total_size'] / 1024), 0), 2)) . ')</span>';
					}
				}
			}
		}

		Theme::addInlineJavaScript('
		var current_attachments = [];');

		if (!empty(Utils::$context['current_attachments'])) {
			// Mock files to show already attached files.
			foreach (Utils::$context['current_attachments'] as $key => $mock) {
				Theme::addInlineJavaScript('
		current_attachments.push({
			name: ' . Utils::JavaScriptEscape($mock['name']) . ',
			size: ' . $mock['size'] . ',
			attachID: ' . $mock['attachID'] . ',
			approved: ' . $mock['approved'] . ',
			type: ' . Utils::JavaScriptEscape(!empty($mock['mime_type']) ? $mock['mime_type'] : '') . ',
			thumbID: ' . (!empty($mock['thumb']) ? $mock['thumb'] : 0) . '
		});');
			}
		}

		// File Upload.
		if (Utils::$context['can_post_attachment']) {
			$acceptedFiles = empty(Utils::$context['allowed_extensions']) ? '' : implode(',', array_map(
				function ($val) {
					return !empty($val) ? ('.' . Utils::htmlTrim($val)) : '';
				},
				explode(',', Utils::$context['allowed_extensions']),
			));

			Theme::loadJavaScriptFile('dropzone.min.js', ['defer' => true], 'smf_dropzone');
			Theme::loadJavaScriptFile('smf_fileUpload.js', ['defer' => true, 'minimize' => true], 'smf_fileUpload');
			Theme::addInlineJavaScript('
		$(function() {
			smf_fileUpload({
				dictDefaultMessage : ' . Utils::JavaScriptEscape(Lang::$txt['attach_drop_zone']) . ',
				dictFallbackMessage : ' . Utils::JavaScriptEscape(Lang::$txt['attach_drop_zone_no']) . ',
				dictCancelUpload : ' . Utils::JavaScriptEscape(Lang::$txt['modify_cancel']) . ',
				genericError: ' . Utils::JavaScriptEscape(Lang::$txt['attach_php_error']) . ',
				text_attachDropzoneLabel: ' . Utils::JavaScriptEscape(Lang::$txt['attach_drop_zone']) . ',
				text_attachLimitNag: ' . Utils::JavaScriptEscape(Lang::$txt['attach_limit_nag']) . ',
				text_attachLeft: ' . Utils::JavaScriptEscape(Lang::$txt['attachments_left']) . ',
				text_deleteAttach: ' . Utils::JavaScriptEscape(Lang::$txt['attached_file_delete']) . ',
				text_attachDeleted: ' . Utils::JavaScriptEscape(Lang::$txt['attached_file_deleted']) . ',
				text_insertBBC: ' . Utils::JavaScriptEscape(Lang::$txt['attached_insert_bbc']) . ',
				text_attachUploaded: ' . Utils::JavaScriptEscape(Lang::$txt['attached_file_uploaded']) . ',
				text_attach_unlimited: ' . Utils::JavaScriptEscape(Lang::$txt['attach_drop_unlimited']) . ',
				text_totalMaxSize: ' . Utils::JavaScriptEscape(Lang::$txt['attach_max_total_file_size_current']) . ',
				text_max_size_progress: ' . Utils::JavaScriptEscape('{currentRemain} ' . (Config::$modSettings['attachmentPostLimit'] >= 1024 ? Lang::$txt['megabyte'] : Lang::$txt['kilobyte']) . ' / {currentTotal} ' . (Config::$modSettings['attachmentPostLimit'] >= 1024 ? Lang::$txt['megabyte'] : Lang::$txt['kilobyte'])) . ',
				dictMaxFilesExceeded: ' . Utils::JavaScriptEscape(Lang::$txt['more_attachments_error']) . ',
				dictInvalidFileType: ' . Utils::JavaScriptEscape(sprintf(Lang::$txt['cant_upload_type'], Utils::$context['allowed_extensions'])) . ',
				dictFileTooBig: ' . Utils::JavaScriptEscape(sprintf(Lang::$txt['file_too_big'], Lang::numberFormat(Config::$modSettings['attachmentSizeLimit'], 0))) . ',
				acceptedFiles: ' . Utils::JavaScriptEscape($acceptedFiles) . ',
				thumbnailWidth: ' . (!empty(Config::$modSettings['attachmentThumbWidth']) ? Config::$modSettings['attachmentThumbWidth'] : 'null') . ',
				thumbnailHeight: ' . (!empty(Config::$modSettings['attachmentThumbHeight']) ? Config::$modSettings['attachmentThumbHeight'] : 'null') . ',
				limitMultiFileUploadSize:' . round(max(Config::$modSettings['attachmentPostLimit'] - (Utils::$context['attachments']['total_size'] / 1024), 0)) * 1024 . ',
				maxFileAmount: ' . (!empty(Utils::$context['num_allowed_attachments']) ? Utils::$context['num_allowed_attachments'] : 'null') . ',
				maxTotalSize: ' . (!empty(Config::$modSettings['attachmentPostLimit']) ? Config::$modSettings['attachmentPostLimit'] : '0') . ',
				maxFilesize: ' . (!empty(Config::$modSettings['attachmentSizeLimit']) ? Config::$modSettings['attachmentSizeLimit'] : '0') . ',
			});
		});', true);
		}

		Theme::loadCSSFile('attachments.css', ['minimize' => true, 'order_pos' => 450], 'smf_attachments');
	}

	/**
	 *
	 */
	protected function showVerification(): void
	{
		Utils::$context['require_verification'] = !User::$me->is_mod && !User::$me->is_admin && !empty(Config::$modSettings['posts_require_captcha']) && (User::$me->posts < Config::$modSettings['posts_require_captcha'] || (User::$me->is_guest && Config::$modSettings['posts_require_captcha'] == -1));

		if (Utils::$context['require_verification']) {
			$verifier = new Verifier(['id' => 'post']);
		}

		// If they came from quick reply, and have to enter verification details, give them some notice.
		if (!empty($_REQUEST['from_qr']) && !empty(Utils::$context['require_verification'])) {
			$this->errors[] = 'need_qr_verification';
		}
	}

	/**
	 *
	 */
	protected function checkForErrors(): void
	{
		IntegrationHook::call('integrate_post_errors', [&$this->errors, &$this->minor_errors, $this->form_message, $this->form_subject]);

		if (empty($this->errors)) {
			return;
		}

		Lang::load('Errors');
		Utils::$context['error_type'] = 'minor';

		foreach ($this->errors as $post_error) {
			if (is_array($post_error)) {
				$post_error_id = $post_error[0];

				Utils::$context['post_error'][$post_error_id] = vsprintf(Lang::$txt['error_' . $post_error_id], (array) $post_error[1]);

				// If it's not a minor error flag it as such.
				if (!in_array($post_error_id, $this->minor_errors)) {
					Utils::$context['error_type'] = 'serious';
				}
			} else {
				Utils::$context['post_error'][$post_error] = Lang::$txt['error_' . $post_error];

				// If it's not a minor error flag it as such.
				if (!in_array($post_error, $this->minor_errors)) {
					Utils::$context['error_type'] = 'serious';
				}
			}
		}
	}

	/**
	 *
	 */
	protected function setPageTitle(): void
	{
		if (isset($_REQUEST['poll'])) {
			Utils::$context['page_title'] = Lang::$txt['new_poll'];
		} elseif (Utils::$context['make_event']) {
			Utils::$context['page_title'] = Utils::$context['event']->id == -1 ? Lang::$txt['calendar_post_event'] : Lang::$txt['calendar_edit'];
		} elseif (isset($_REQUEST['msg'])) {
			Utils::$context['page_title'] = Lang::$txt['modify_msg'];
		} elseif (isset($_REQUEST['subject'], Utils::$context['preview_subject'])) {
			Utils::$context['page_title'] = Lang::$txt['preview'] . ' - ' . strip_tags(Utils::$context['preview_subject']);
		} elseif (empty(Topic::$info->id)) {
			Utils::$context['page_title'] = Lang::$txt['start_new_topic'];
		} else {
			Utils::$context['page_title'] = Lang::$txt['post_reply'];
		}
	}

	/**
	 *
	 */
	protected function setLinktree(): void
	{
		if (empty(Topic::$info->id)) {
			Utils::$context['linktree'][] = [
				'name' => '<em>' . Lang::$txt['start_new_topic'] . '</em>',
			];
		} else {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '?topic=' . Topic::$info->id . '.' . $_REQUEST['start'],
				'name' => $this->form_subject,
				'extra_before' => '<span><strong class="nav">' . Utils::$context['page_title'] . ' (</strong></span>',
				'extra_after' => '<span><strong class="nav">)</strong></span>',
			];
		}
	}

	/**
	 *
	 */
	protected function loadDrafts(): void
	{
		// Are post drafts enabled?
		Utils::$context['drafts_type'] = 'post';
		Utils::$context['drafts_save'] = !empty(Config::$modSettings['drafts_post_enabled']) && User::$me->allowedTo('post_draft');
		Utils::$context['drafts_autosave'] = !empty(Utils::$context['drafts_save']) && !empty(Config::$modSettings['drafts_autosave_enabled']) && User::$me->allowedTo('post_autosave_draft') && !empty(Theme::$current->options['drafts_autosave_enabled']);

		// Build a list of drafts that they can load in to the editor
		if (!empty(Utils::$context['drafts_save'])) {
			Draft::showInEditor(User::$me->id, Topic::$topic_id);

			// Has a specific draft has been selected?
			// Load its data if there is not a message already in the editor.
			if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message'])) {
				$draft = new Draft((int) $_REQUEST['id_draft'], true);
				$draft->prepare();
			}
		}
	}

	/**
	 * Load a new editor instance.
	 */
	protected function loadEditor(): void
	{
		new Editor([
			'id' => 'message',
			'value' => Utils::$context['message'],
			'labels' => [
				'post_button' => Utils::$context['submit_label'],
			],
			// add height and width for the editor
			'height' => '175px',
			'width' => '100%',
			// We do XML preview here.
			'preview_type' => Editor::PREVIEW_XML,
			'required' => true,
		]);
	}

	/**
	 *
	 */
	protected function setMessageIcons(): void
	{
		// Message icons - customized icons are off?
		Utils::$context['icons'] = Editor::getMessageIcons(!empty(Board::$info->id) ? Board::$info->id : 0);

		if (!empty(Utils::$context['icons'])) {
			Utils::$context['icons'][count(Utils::$context['icons']) - 1]['is_last'] = true;
		}

		// Are we starting a poll? if set the poll icon as selected if its available
		if (isset($_REQUEST['poll'])) {
			foreach (Utils::$context['icons'] as $icons) {
				if (isset($icons['value']) && $icons['value'] == 'poll') {
					// if found we are done
					Utils::$context['icon'] = 'poll';
					break;
				}
			}
		}

		Utils::$context['icon_url'] = '';

		for ($i = 0, $n = count(Utils::$context['icons']); $i < $n; $i++) {
			Utils::$context['icons'][$i]['selected'] = Utils::$context['icon'] == Utils::$context['icons'][$i]['value'];

			if (Utils::$context['icons'][$i]['selected']) {
				Utils::$context['icon_url'] = Utils::$context['icons'][$i]['url'];
			}
		}

		if (empty(Utils::$context['icon_url'])) {
			Utils::$context['icon_url'] = Theme::$current->settings[file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . Utils::$context['icon'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . Utils::$context['icon'] . '.png';

			array_unshift(Utils::$context['icons'], [
				'value' => Utils::$context['icon'],
				'name' => Lang::$txt['current_icon'],
				'url' => Utils::$context['icon_url'],
				'is_last' => empty(Utils::$context['icons']),
				'selected' => true,
			]);
		}
	}

	/**
	 *
	 */
	protected function setupPostingFields(): void
	{
		/*
			Each item in Utils::$context['posting_fields'] is an array similar to one of
			the following:

			Utils::$context['posting_fields']['foo'] = array(
				'label' => array(
					'text' => Lang::$txt['foo'], // required
					'class' => 'foo', // optional
				),
				'input' => array(
					'type' => 'text', // required
					'attributes' => array(
						'name' => 'foo', // optional, defaults to posting field's key
						'value' => $foo,
						'size' => 80,
					),
				),
			);

			Utils::$context['posting_fields']['bar'] = array(
				'label' => array(
					'text' => Lang::$txt['bar'], // required
					'class' => 'bar', // optional
				),
				'input' => array(
					'type' => 'select', // required
					'attributes' => array(
						'name' => 'bar', // optional, defaults to posting field's key
					),
					'options' => array(
						'option_1' => array(
							'label' => Lang::$txt['option_1'],
							'value' => '1',
							'selected' => true,
						),
						'option_2' => array(
							'label' => Lang::$txt['option_2'],
							'value' => '2',
							'selected' => false,
						),
						'opt_group_1' => array(
							'label' => Lang::$txt['opt_group_1'],
							'options' => array(
								'option_3' => array(
									'label' => Lang::$txt['option_3'],
									'value' => '3',
									'selected' => false,
								),
								'option_4' => array(
									'label' => Lang::$txt['option_4'],
									'value' => '4',
									'selected' => false,
								),
							),
						),
					),
				),
			);

			Utils::$context['posting_fields']['baz'] = array(
				'label' => array(
					'text' => Lang::$txt['baz'], // required
					'class' => 'baz', // optional
				),
				'input' => array(
					'type' => 'radio_select', // required
					'attributes' => array(
						'name' => 'baz', // optional, defaults to posting field's key
					),
					'options' => array(
						'option_1' => array(
							'label' => Lang::$txt['option_1'],
							'value' => '1',
							'selected' => true,
						),
						'option_2' => array(
							'label' => Lang::$txt['option_2'],
							'value' => '2',
							'selected' => false,
						),
					),
				),
			);

			The label and input elements are required. The label text and input
			type are also required. Other elements may be required or optional
			depending on the situation.

			The input type can be one of the following:

			- text, password, color, date, datetime-local, email, month, number,
			  range, tel, time, url, or week
			- textarea
			- checkbox
			- select
			- radio_select

			When the input type is text (etc.), textarea, or checkbox, the
			'attributes' element is used to specify the initial value and any
			other HTML attributes that might be necessary for the input field.

			When the input type is select or radio_select, the options element
			is required in order to list the options that the user can select.
			For the select type, these will be used to generate a typical select
			menu. For the radio_select type, they will be used to make a div with
			some radio buttons in it.

			Each option in the options array is itself an array of attributes. If
			an option contains a sub-array of more options, then it will be
			turned into an optgroup in the generated select menu. Note that the
			radio_select type only supports simple options, not grouped ones.

			Both the label and the input can have a 'before' and/or 'after'
			element. If used, these define literal HTML strings to be inserted
			before or after the rest of the content of the label or input.

			Finally, it is possible to define an 'html' element for the label
			and/or the input. If used, this will override the HTML that would
			normally be generated in the template file using the other
			information in the array. This should be avoided if at all possible.
		*/
		Utils::$context['posting_fields'] = [];

		// Guests must supply their name and email.
		if (isset(Utils::$context['name'], Utils::$context['email'])) {
			Utils::$context['posting_fields']['guestname'] = [
				'label' => [
					'text' => Lang::$txt['name'],
					'class' => isset(Utils::$context['post_error']['long_name']) || isset(Utils::$context['post_error']['no_name']) || isset(Utils::$context['post_error']['bad_name']) ? 'error' : '',
				],
				'input' => [
					'type' => 'text',
					'attributes' => [
						'size' => 25,
						'maxlength' => 25,
						'value' => Utils::$context['name'],
						'required' => true,
					],
				],
			];

			if (empty(Config::$modSettings['guest_post_no_email'])) {
				Utils::$context['posting_fields']['email'] = [
					'label' => [
						'text' => Lang::$txt['email'],
						'class' => isset(Utils::$context['post_error']['no_email']) || isset(Utils::$context['post_error']['bad_email']) ? 'error' : '',
					],
					'input' => [
						'type' => 'email',
						'attributes' => [
							'size' => 25,
							'value' => Utils::$context['email'],
							'required' => true,
						],
					],
				];
			}
		}

		// Gotta post it somewhere.
		if (empty(Board::$info->id)) {
			Utils::$context['posting_fields']['board'] = [
				'label' => [
					'text' => Lang::$txt['calendar_post_in'],
				],
				'input' => [
					'type' => 'select',
					'options' => [],
				],
			];

			foreach ($this->board_list as $category) {
				Utils::$context['posting_fields']['board']['input']['options'][$category['name']] = ['options' => []];

				foreach ($category['boards'] as $brd) {
					Utils::$context['posting_fields']['board']['input']['options'][$category['name']]['options'][$brd['name']] = [
						'value' => $brd['id'],
						'selected' => (bool) $brd['selected'],
						'label' => ($brd['child_level'] > 0 ? str_repeat('==', $brd['child_level'] - 1) . '=&gt;' : '') . ' ' . $brd['name'],
					];
				}
			}
		}

		// Gotta have a subject.
		Utils::$context['posting_fields']['subject'] = [
			'label' => [
				'text' => Lang::$txt['subject'],
				'class' => isset(Utils::$context['post_error']['no_subject']) ? 'error' : '',
			],
			'input' => [
				'type' => 'text',
				'attributes' => [
					'size' => 80,
					'maxlength' => 80 + (!empty(Topic::$info->id) ? Utils::entityStrlen(Utils::$context['response_prefix']) : 0),
					'value' => Utils::$context['subject'],
					'required' => true,
				],
			],
		];

		// Icons are fun.
		Utils::$context['posting_fields']['icon'] = [
			'label' => [
				'text' => Lang::$txt['message_icon'],
			],
			'input' => [
				'type' => 'select',
				'attributes' => [
					'id' => 'icon',
					'onchange' => 'showimage();',
				],
				'options' => [],
				'after' => ' <img id="icons" src="' . Utils::$context['icon_url'] . '">',
			],
		];

		foreach (Utils::$context['icons'] as $icon) {
			Utils::$context['posting_fields']['icon']['input']['options'][$icon['name']] = [
				'value' => $icon['value'],
				'selected' => $icon['value'] == Utils::$context['icon'],
			];
		}

		// If we're editing and displaying edit details, show a box where they can say why.
		if (isset(Utils::$context['editing']) && Config::$modSettings['show_modify']) {
			Utils::$context['posting_fields']['modify_reason'] = [
				'label' => [
					'text' => Lang::$txt['reason_for_edit'],
				],
				'input' => [
					'type' => 'text',
					'attributes' => [
						'size' => 80,
						'maxlength' => 80,
						// If same user is editing again, keep the previous edit reason by default.
						'value' => isset($modified_reason) && isset(Utils::$context['last_modified_name']) && Utils::$context['last_modified_name'] === User::$me->name ? $modified_reason : '',
					],
					// If message has been edited before, show info about that.
					'after' => empty(Utils::$context['last_modified_text']) ? '' : '<div class="smalltext em">' . Utils::$context['last_modified_text'] . '</div>',
				],
			];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Post::exportStatic')) {
	Post::exportStatic();
}

?>