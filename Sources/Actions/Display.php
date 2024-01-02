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

use SMF\Alert;
use SMF\Attachment;
use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Editor;
use SMF\ErrorHandler;
use SMF\Event;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Msg;
use SMF\PageIndex;
use SMF\Poll;
use SMF\Security;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * This class loads the posts in a topic so they can be displayed.
 *
 * It uses the main sub template of the Display template.
 * It requires a topic, and can go to the previous or next topic from it.
 * It jumps to the correct post depending on a number/time/msg passed.
 * It depends on the messages_per_page, defaultMaxMessages and enableAllMessages
 * settings.
 * It is accessed by ?topic=id_topic.START.
 *
 * Although this class is not accessed using an ?action=... URL query, it
 * behaves like an action in every other way.
 */
class Display implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static array $backcompat = [
		'func_names' => [
			'call' => 'Display',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * Whether all posts in the topic can be viewed on a single page.
	 */
	public $can_show_all = false;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * ID numbers of messages in this topic.
	 */
	private $messages = [];

	/**
	 * @var array
	 *
	 * ID numbers of the authors of the $messages.
	 */
	private $posters = [];

	/**
	 * @var int
	 *
	 * Index of the first message.
	 */
	private $firstIndex;

	/**
	 * @var int
	 *
	 * Requested message in $_REQUEST['start'].
	 * Might or might not be set.
	 */
	private $virtual_msg;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the heavy lifting to show the posts in this topic.
	 *
	 * - Sets up anti-spam verification and old topic warnings.
	 * - Gets the list of users viewing the topic.
	 * - Loads events and polls attached to the topic.
	 * - Loads IDs of the posts and posters.
	 * - Initializes the generator for displaying single posts.
	 * - Marks posts, topics, and boards as read (if they should be).
	 * - Loads the editor and builds the button arrays.
	 */
	public function execute(): void
	{
		$this->setupVerification();
		$this->setOldTopicWarning();
		$this->getWhoViewing();

		$this->loadEvents();
		$this->loadPoll();

		$this->getMessagesAndPosters();
		$this->initDisplayContext();

		$this->markRead();
		$this->getNotificationMode();

		// Load up the "double post" sequencing magic.
		Security::checkSubmitOnce('register');

		$this->loadEditor();
		$this->buildButtons();
	}

	/**
	 * Callback for the message display.
	 *
	 * This function internally relies on Msg::get(). Therefore, in order for this
	 * function to return any data, you must first initialize the Msg::$getter
	 * generator like this: `Msg::$getter = Msg::get($message_ids);`
	 *
	 * @return array|bool Contextual data for a post, or false on failure.
	 */
	public function prepareDisplayContext()
	{
		static $counter = null;

		// Remember which message this is.  (ie. reply #83)
		if ($counter === null) {
			$counter = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start'] : Topic::$info->total_visible_posts - Utils::$context['start'];
		}

		if (!(Msg::$getter instanceof \Generator)) {
			return false;
		}

		$message = Msg::$getter->current();
		Msg::$getter->next();

		if (!$message) {
			return false;
		}

		$output = $message->format($counter);

		// Set up the quick buttons.
		$output['quickbuttons'] = [
			'quote' => [
				'label' => Lang::$txt['quote_action'],
				'href' => Config::$scripturl . '?action=post;quote=' . $output['id'] . ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';last_msg=' . Topic::$info->id_last_msg,
				'javascript' => 'onclick="return oQuickReply.quote(' . $output['id'] . ');"',
				'icon' => 'quote',
				'show' => Utils::$context['can_quote'],
			],
			'quote_selected' => [
				'label' => Lang::$txt['quote_selected_action'],
				'id' => 'quoteSelected_' . $output['id'],
				'href' => 'javascript:void(0)',
				'custom' => 'style="display:none"',
				'icon' => 'quote_selected',
				'show' => Utils::$context['can_quote'],
			],
			'quick_edit' => [
				'label' => Lang::$txt['quick_edit'],
				'class' => 'quick_edit',
				'id' => 'modify_button_' . $output['id'],
				'custom' => 'onclick="oQuickModify.modifyMsg(\'' . $output['id'] . '\', \'' . !empty(Config::$modSettings['toggle_subject']) . '\')"',
				'icon' => 'quick_edit_button',
				'show' => $output['can_modify'],
			],
			'more' => [
				'modify' => [
					'label' => Lang::$txt['modify'],
					'href' => Config::$scripturl . '?action=post;msg=' . $output['id'] . ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'],
					'icon' => 'modify_button',
					'show' => $output['can_modify'],
				],
				'remove_topic' => [
					'label' => Lang::$txt['remove_topic'],
					'href' => Config::$scripturl . '?action=removetopic2;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'javascript' => 'data-confirm="' . Lang::$txt['are_sure_remove_topic'] . '"',
					'class' => 'you_sure',
					'icon' => 'remove_button',
					'show' => Utils::$context['can_delete'] && (Topic::$info->id_first_msg == $output['id']),
				],
				'remove' => [
					'label' => Lang::$txt['remove'],
					'href' => Config::$scripturl . '?action=deletemsg;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';msg=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'javascript' => 'data-confirm="' . Lang::$txt['remove_message_question'] . '"',
					'class' => 'you_sure',
					'icon' => 'remove_button',
					'show' => $output['can_remove'] && (Topic::$info->id_first_msg != $output['id']),
				],
				'split' => [
					'label' => Lang::$txt['split'],
					'href' => Config::$scripturl . '?action=splittopics;topic=' . Utils::$context['current_topic'] . '.0;at=' . $output['id'],
					'icon' => 'split_button',
					'show' => Utils::$context['can_split'] && !empty(Topic::$info->real_num_replies),
				],
				'report' => [
					'label' => Lang::$txt['report_to_mod'],
					'href' => Config::$scripturl . '?action=reporttm;topic=' . Utils::$context['current_topic'] . '.' . $output['counter'] . ';msg=' . $output['id'],
					'icon' => 'error',
					'show' => Utils::$context['can_report_moderator'],
				],
				'warn' => [
					'label' => Lang::$txt['issue_warning'],
					'href' => Config::$scripturl . '?action=profile;area=issuewarning;u=' . $output['member']['id'] . ';msg=' . $output['id'],
					'icon' => 'warn_button',
					'show' => Utils::$context['can_issue_warning'] && !$output['is_message_author'] && !$output['member']['is_guest'],
				],
				'restore' => [
					'label' => Lang::$txt['restore_message'],
					'href' => Config::$scripturl . '?action=restoretopic;msgs=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'icon' => 'restore_button',
					'show' => Utils::$context['can_restore_msg'],
				],
				'approve' => [
					'label' => Lang::$txt['approve'],
					'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';msg=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'icon' => 'approve_button',
					'show' => $output['can_approve'],
				],
				'unapprove' => [
					'label' => Lang::$txt['unapprove'],
					'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';msg=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'icon' => 'unapprove_button',
					'show' => $output['can_unapprove'],
				],
			],
			'quickmod' => [
				'class' => 'inline_mod_check',
				'id' => 'in_topic_mod_check_' . $output['id'],
				'custom' => 'style="display: none;"',
				'content' => '',
				'show' => !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && $output['can_remove'],
			],
		];

		if (empty(Theme::$current->options['view_newest_first'])) {
			$counter++;
		} else {
			$counter--;
		}

		IntegrationHook::call('integrate_prepare_display_context', [&$output, $message, $counter]);

		return $output;
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via load().
	 *
	 * - Handles any redirects we might need to do.
	 * - Loads topic info.
	 * - Loads permissions.
	 * - Prepares most of the stuff for the templates.
	 */
	protected function __construct()
	{
		// What are you gonna display if this is empty?!
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('no_board', false);
		}

		$this->checkPrevNextRedirect();
		$this->preventPrefetch();

		// Load the topic info.
		Topic::load();

		$this->incrementNumViews();
		$this->checkMovedMergedRedirect();

		$this->setStart();
		$this->setPaginationAndLinks();
		$this->setRobotNoIndex();

		$this->setModerators();
		$this->setUnapprovedPostsMessage();

		// Now set all the wonderful, wonderful permissions... like moderation ones...
		foreach (Topic::$info->doPermissions() as $perm => $val) {
			Utils::$context[$perm] = &Topic::$info->permissions[$perm];
		}

		$this->setupTemplate();
	}

	/**
	 * Redirect to the previous or next topic, if requested in the URL params.
	 */
	protected function checkPrevNextRedirect(): void
	{
		// Find the previous or next topic. But don't bother if there's only one.
		if (isset($_REQUEST['prev_next']) && in_array($_REQUEST['prev_next'], ['prev', 'next']) && Board::$info->num_topics > 1) {
			$prev = $_REQUEST['prev_next'] === 'prev';

			// Just prepare some variables that are used in the query.
			$gt_lt = $prev ? '>' : '<';
			$order = $prev ? '' : ' DESC';

			$request = Db::$db->query(
				'',
				'SELECT t2.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}topics AS t2 ON (
					(t2.id_last_msg ' . $gt_lt . ' t.id_last_msg AND t2.is_sticky ' . $gt_lt . '= t.is_sticky) OR t2.is_sticky ' . $gt_lt . ' t.is_sticky)
				WHERE t.id_topic = {int:current_topic}
					AND t2.id_board = {int:current_board}' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					AND (t2.approved = {int:is_approved} OR (t2.id_member_started != {int:id_member_started} AND t2.id_member_started = {int:current_member}))') . '
				ORDER BY t2.is_sticky' . $order . ', t2.id_last_msg' . $order . '
				LIMIT 1',
				[
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
					'current_topic' => Topic::$topic_id,
					'is_approved' => 1,
					'id_member_started' => 0,
				],
			);

			// No more left.
			if (Db::$db->num_rows($request) == 0) {
				Db::$db->free_result($request);

				// Roll over - if we're going prev, get the last - otherwise the first.
				$request = Db::$db->query(
					'',
					'SELECT id_topic
					FROM {db_prefix}topics
					WHERE id_board = {int:current_board}' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
						AND (approved = {int:is_approved} OR (id_member_started != {int:id_member_started} AND id_member_started = {int:current_member}))') . '
					ORDER BY is_sticky' . $order . ', id_last_msg' . $order . '
					LIMIT 1',
					[
						'current_board' => Board::$info->id,
						'current_member' => User::$me->id,
						'is_approved' => 1,
						'id_member_started' => 0,
					],
				);
			}

			// Now you can be sure Topic::$topic_id is the id_topic to view.
			list(Topic::$topic_id) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Go to the newest message on the prev/next topic.
			Utils::redirectexit('topic=' . Topic::$topic_id . ';start=new#new');
		}
	}

	/**
	 * Is this a moved or merged topic that we are redirecting to?
	 */
	protected function checkMovedMergedRedirect(): void
	{
		if (!empty(Topic::$info->id_redirect_topic)) {
			// Mark this as read.
			if (!User::$me->is_guest && Topic::$info->new_from != Topic::$info->id_first_msg) {
				Db::$db->insert(
					Topic::$info->new_from == 0 ? 'ignore' : 'replace',
					'{db_prefix}log_topics',
					[
						'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
					],
					[
						User::$me->id, Topic::$info->id, Topic::$info->id_first_msg, Topic::$info->unwatched,
					],
					['id_member', 'id_topic'],
				);
			}

			Utils::redirectexit('topic=' . Topic::$info->id_redirect_topic . '.0', false, true);
		}
	}

	/**
	 * Blocks browser attempts to prefetch the topic display.
	 */
	protected function preventPrefetch(): void
	{
		// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
			ob_end_clean();
			Utils::sendHttpStatus(403, 'Prefetch Forbidden');

			die;
		}
	}

	/**
	 * Tells search engines not to index pages they shouldn't.
	 */
	protected function setRobotNoIndex(): void
	{
		// Let's do some work on what to search index.
		if (count($_GET) > 2) {
			foreach ($_GET as $k => $v) {
				if (!in_array($k, ['topic', 'board', 'start', session_name()])) {
					Utils::$context['robot_no_index'] = true;
				}
			}
		}

		if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % Utils::$context['messages_per_page'] != 0)) {
			Utils::$context['robot_no_index'] = true;
		}
	}

	/**
	 * Add 1 to the number of views of this topic (except for robots).
	 */
	protected function incrementNumViews(): void
	{
		if (!User::$me->possibly_robot && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != Topic::$info->id)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET num_views = num_views + 1
				WHERE id_topic = {int:current_topic}',
				[
					'current_topic' => Topic::$info->id,
				],
			);

			$_SESSION['last_read_topic'] = Topic::$info->id;
		}
	}

	/**
	 *
	 */
	protected function markRead(): void
	{
		// Guests can't mark topics read or for notifications, just can't sorry.
		if (!User::$me->is_guest && !empty($this->messages)) {
			$mark_at_msg = max($this->messages);

			if ($mark_at_msg >= Topic::$info->id_last_msg) {
				$mark_at_msg = Config::$modSettings['maxMsgID'];
			}

			if ($mark_at_msg >= Topic::$info->new_from) {
				Db::$db->insert(
					Topic::$info->new_from == 0 ? 'ignore' : 'replace',
					'{db_prefix}log_topics',
					[
						'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
					],
					[
						User::$me->id, Topic::$info->id, $mark_at_msg, Topic::$info->unwatched,
					],
					['id_member', 'id_topic'],
				);
			}

			// Check for notifications on this topic OR board.
			$request = Db::$db->query(
				'',
				'SELECT sent, id_topic
				FROM {db_prefix}log_notify
				WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
					AND id_member = {int:current_member}
				LIMIT 2',
				[
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
					'current_topic' => Topic::$info->id,
				],
			);
			$do_once = true;

			while ($row = Db::$db->fetch_assoc($request)) {
				// Find if this topic is marked for notification...
				if (!empty($row['id_topic'])) {
					Utils::$context['is_marked_notify'] = true;
				}

				// Only do this once, but mark the notifications as "not sent yet" for next time.
				if (!empty($row['sent']) && $do_once) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}log_notify
						SET sent = {int:is_not_sent}
						WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
							AND id_member = {int:current_member}',
						[
							'current_board' => Board::$info->id,
							'current_member' => User::$me->id,
							'current_topic' => Topic::$info->id,
							'is_not_sent' => 0,
						],
					);
					$do_once = false;
				}
			}

			// Have we recently cached the number of new topics in this board, and it's still a lot?
			if (isset($_REQUEST['topicseen'], $_SESSION['topicseen_cache'][Board::$info->id])   && $_SESSION['topicseen_cache'][Board::$info->id] > 5) {
				$_SESSION['topicseen_cache'][Board::$info->id]--;
			}
			// Mark board as seen if this is the only new topic.
			elseif (isset($_REQUEST['topicseen'])) {
				// Use the mark read tables... and the last visit to figure out if this should be read or not.
				$request = Db::$db->query(
					'',
					'SELECT COUNT(*)
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = {int:current_board} AND lb.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					WHERE t.id_board = {int:current_board}
						AND t.id_last_msg > COALESCE(lb.id_msg, 0)
						AND t.id_last_msg > COALESCE(lt.id_msg, 0)' . (empty($_SESSION['id_msg_last_visit']) ? '' : '
						AND t.id_last_msg > {int:id_msg_last_visit}'),
					[
						'current_board' => Board::$info->id,
						'current_member' => User::$me->id,
						'id_msg_last_visit' => (int) $_SESSION['id_msg_last_visit'],
					],
				);
				list($numNewTopics) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// If there're no real new topics in this board, mark the board as seen.
				if (empty($numNewTopics)) {
					$_REQUEST['boardseen'] = true;
				} else {
					$_SESSION['topicseen_cache'][Board::$info->id] = $numNewTopics;
				}
			}
			// Probably one less topic - maybe not, but even if we decrease this too fast it will only make us look more often.
			elseif (isset($_SESSION['topicseen_cache'][Board::$info->id])) {
				$_SESSION['topicseen_cache'][Board::$info->id]--;
			}

			// Mark board as seen if we came using last post link from BoardIndex. (or other places...)
			if (isset($_REQUEST['boardseen'])) {
				Db::$db->insert(
					'replace',
					'{db_prefix}log_boards',
					['id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'],
					[Config::$modSettings['maxMsgID'], User::$me->id, Board::$info->id],
					['id_member', 'id_board'],
				);
			}

			// Mark any alerts about this topic or the posts on this page as read.
			if (!empty(User::$me->alerts)) {
				Alert::markWhere(
					[
						// Obviously, only for the current member.
						'id_member = {int:current_member}',

						// A compound condition to get all the relevant types.
						'(' .
							'content_id IN ({array_int:messages}) ' .
							'AND content_type = {literal:msg}' .
						') ' .
						'OR (' .
							'content_id = {int:current_topic} ' .
							'AND (' .
								'content_type = {literal:topic} ' .
								'OR (' .
									'content_type = {literal:board} ' .
									'AND content_action = {literal:topic}' .
								')' .
							')' .
						')',
					],
					[
						'current_member' => User::$me->id,
						'current_topic' => Topic::$info->id,
						'messages' => $this->messages,
					],
					true,
				);
			}
		}
	}

	/**
	 * Get notification preferences and mode.
	 */
	protected function getNotificationMode(): void
	{
		Topic::$info->getNotificationPrefs();

		Utils::$context['topic_notification'] = Topic::$info->notify_prefs;

		// 0 => unwatched, 1 => normal, 2 => receive alerts, 3 => receive emails
		Utils::$context['topic_notification_mode'] = !User::$me->is_guest ? (Topic::$info->unwatched ? 0 : (Topic::$info->notify_prefs['pref'] & 0x02 ? 3 : (Topic::$info->notify_prefs['pref'] & 0x01 ? 2 : 1))) : 0;
	}
	/**
	 * If $_REQUEST['start'] is not a number, figures out the correct numerical
	 * value and sets $_REQUEST['start'] to that value.
	 */
	protected function setStart(): void
	{
		// The start isn't a number; it's information about what to do, where to go.
		if (!is_numeric($_REQUEST['start'])) {
			// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
			if ($_REQUEST['start'] == 'new') {
				// Guests automatically go to the last post.
				if (User::$me->is_guest) {
					Utils::$context['start_from'] = Topic::$info->total_visible_posts - 1;
					$_REQUEST['start'] = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start_from'] : 0;
				} else {
					// Find the earliest unread message in the topic. (the use of topics here is just for both tables.)
					$request = Db::$db->query(
						'',
						'SELECT COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from
						FROM {db_prefix}topics AS t
							LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
							LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})
						WHERE t.id_topic = {int:current_topic}
						LIMIT 1',
						[
							'current_board' => Board::$info->id,
							'current_member' => User::$me->id,
							'current_topic' => Topic::$topic_id,
						],
					);
					list($new_from) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					// Fall through to the next if statement.
					$_REQUEST['start'] = 'msg' . $new_from;
				}
			}

			// Start from a certain time index, not a message.
			if (substr($_REQUEST['start'], 0, 4) == 'from') {
				$timestamp = (int) substr($_REQUEST['start'], 4);

				if ($timestamp === 0) {
					$_REQUEST['start'] = 0;
				} else {
					// Find the number of messages posted before said time...
					$request = Db::$db->query(
						'',
						'SELECT COUNT(*)
						FROM {db_prefix}messages
						WHERE poster_time < {int:timestamp}
							AND id_topic = {int:current_topic}' . (Config::$modSettings['postmod_active'] && Topic::$info->unapproved_posts && !User::$me->allowedTo('approve_posts') ? '
							AND (approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
						[
							'current_topic' => Topic::$topic_id,
							'current_member' => User::$me->id,
							'is_approved' => 1,
							'timestamp' => $timestamp,
						],
					);
					list(Utils::$context['start_from']) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					// Handle view_newest_first options, and get the correct start value.
					$_REQUEST['start'] = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start_from'] : Topic::$info->total_visible_posts - Utils::$context['start_from'] - 1;
				}
			}

			// Link to a message...
			elseif (substr($_REQUEST['start'], 0, 3) == 'msg') {
				$this->virtual_msg = (int) substr($_REQUEST['start'], 3);

				if (!Topic::$info->unapproved_posts && $this->virtual_msg >= Topic::$info->id_last_msg) {
					Utils::$context['start_from'] = Topic::$info->total_visible_posts - 1;
				} elseif (!Topic::$info->unapproved_posts && $this->virtual_msg <= Topic::$info->id_first_msg) {
					Utils::$context['start_from'] = 0;
				} else {
					// Find the start value for that message......
					$request = Db::$db->query(
						'',
						'SELECT COUNT(*)
						FROM {db_prefix}messages
						WHERE id_msg < {int:virtual_msg}
							AND id_topic = {int:current_topic}' . (Config::$modSettings['postmod_active'] && Topic::$info->unapproved_posts && !User::$me->allowedTo('approve_posts') ? '
							AND (approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
						[
							'current_member' => User::$me->id,
							'current_topic' => Topic::$topic_id,
							'virtual_msg' => $this->virtual_msg,
							'is_approved' => 1,
							'no_member' => 0,
						],
					);
					list(Utils::$context['start_from']) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);
				}

				// We need to reverse the start as well in this case.
				$_REQUEST['start'] = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start_from'] : Topic::$info->total_visible_posts - Utils::$context['start_from'] - 1;
			}
		}
	}

	/**
	 * Let's get nosey, who is viewing this topic?
	 */
	protected function getWhoViewing(): void
	{
		if (!empty(Theme::$current->settings['display_who_viewing'])) {
			// Start out with no one at all viewing it.
			Utils::$context['view_members'] = [];
			Utils::$context['view_members_list'] = [];
			Utils::$context['view_num_hidden'] = 0;

			// Search for members who have this topic set in their GET data.
			$request = Db::$db->query(
				'',
				'SELECT
					lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
					mg.online_color, mg.id_group, mg.group_name
				FROM {db_prefix}log_online AS lo
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_id_group} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
				[
					'reg_id_group' => 0,
					'in_url_string' => '"topic":' . Topic::$info->id,
					'session' => User::$me->is_guest ? 'ip' . User::$me->ip : session_id(),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (empty($row['id_member'])) {
					continue;
				}

				if (!empty($row['online_color'])) {
					$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
				} else {
					$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
				}

				$is_buddy = in_array($row['id_member'], User::$me->buddies);

				if ($is_buddy) {
					$link = '<strong>' . $link . '</strong>';
				}

				// Add them both to the list and to the more detailed list.
				if (!empty($row['show_online']) || User::$me->allowedTo('moderate_forum')) {
					Utils::$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;
				}

				Utils::$context['view_members'][$row['log_time'] . $row['member_name']] = [
					'id' => $row['id_member'],
					'username' => $row['member_name'],
					'name' => $row['real_name'],
					'group' => $row['id_group'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => $link,
					'is_buddy' => $is_buddy,
					'hidden' => empty($row['show_online']),
				];

				if (empty($row['show_online'])) {
					Utils::$context['view_num_hidden']++;
				}
			}

			// The number of guests is equal to the rows minus the ones we actually used ;).
			Utils::$context['view_num_guests'] = Db::$db->num_rows($request) - count(Utils::$context['view_members']);

			Db::$db->free_result($request);

			// Sort the list.
			krsort(Utils::$context['view_members']);
			krsort(Utils::$context['view_members_list']);
		}
	}

	/**
	 * Constructs page index, sets next/prev/up links, etc.
	 */
	protected function setPaginationAndLinks(): void
	{
		// How much are we sticking on each page?
		Utils::$context['messages_per_page'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		// Create a previous next string if the selected theme has it as a selected option.
		Utils::$context['previous_next'] = Config::$modSettings['enablePreviousNext'] ? '<a href="' . Config::$scripturl . '?topic=' . Topic::$info->id . '.0;prev_next=prev#new">' . Lang::$txt['previous_next_back'] . '</a> - <a href="' . Config::$scripturl . '?topic=' . Topic::$info->id . '.0;prev_next=next#new">' . Lang::$txt['previous_next_forward'] . '</a>' : '';

		// If all is set, but not allowed... just unset it.
		$this->can_show_all = !empty(Config::$modSettings['enableAllMessages']) && Topic::$info->total_visible_posts > Utils::$context['messages_per_page'] && Topic::$info->total_visible_posts < Config::$modSettings['enableAllMessages'];

		if (isset($_REQUEST['all']) && !$this->can_show_all) {
			unset($_REQUEST['all']);
		}
		// Otherwise, it must be allowed... so pretend start was -1.
		elseif (isset($_REQUEST['all'])) {
			$_REQUEST['start'] = -1;
		}

		// Construct the page index, allowing for the .START method...
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?topic=' . Topic::$info->id . '.%1$d', $_REQUEST['start'], Topic::$info->total_visible_posts, Utils::$context['messages_per_page'], true);

		Utils::$context['start'] = $_REQUEST['start'];

		// This is information about which page is current, and which page we're on - in case you don't like the constructed page index. (again, wireless..)
		Utils::$context['page_info'] = [
			'current_page' => $_REQUEST['start'] / Utils::$context['messages_per_page'] + 1,
			'num_pages' => floor((Topic::$info->total_visible_posts - 1) / Utils::$context['messages_per_page']) + 1,
		];

		// Figure out all the links to the next/prev/first/last/etc.
		if (!($this->can_show_all && isset($_REQUEST['all']))) {
			Utils::$context['links'] = [
				'first' => $_REQUEST['start'] >= Utils::$context['messages_per_page'] ? Config::$scripturl . '?topic=' . Topic::$info->id . '.0' : '',
				'prev' => $_REQUEST['start'] >= Utils::$context['messages_per_page'] ? Config::$scripturl . '?topic=' . Topic::$info->id . '.' . ($_REQUEST['start'] - Utils::$context['messages_per_page']) : '',
				'next' => $_REQUEST['start'] + Utils::$context['messages_per_page'] < Topic::$info->total_visible_posts ? Config::$scripturl . '?topic=' . Topic::$info->id . '.' . ($_REQUEST['start'] + Utils::$context['messages_per_page']) : '',
				'last' => $_REQUEST['start'] + Utils::$context['messages_per_page'] < Topic::$info->total_visible_posts ? Config::$scripturl . '?topic=' . Topic::$info->id . '.' . (floor(Topic::$info->total_visible_posts / Utils::$context['messages_per_page']) * Utils::$context['messages_per_page']) : '',
				'up' => Config::$scripturl . '?board=' . Board::$info->id . '.0',
			];
		}

		// If they are viewing all the posts, show all the posts, otherwise limit the number.
		if ($this->can_show_all) {
			if (isset($_REQUEST['all'])) {
				// No limit! (actually, there is a limit, but...)
				Utils::$context['messages_per_page'] = -1;
				Utils::$context['page_index'] .= sprintf(strtr(Theme::$current->settings['page_index']['current_page'], ['%1$d' => '%1$s']), Lang::$txt['all']);

				// Set start back to 0...
				$_REQUEST['start'] = 0;
			}
			// They aren't using it, but the *option* is there, at least.
			else {
				Utils::$context['page_index'] .= sprintf(strtr(Theme::$current->settings['page_index']['page'], ['{URL}' => Config::$scripturl . '?topic=' . Topic::$info->id . '.0;all']), '', Lang::$txt['all']);
			}
		}
	}

	/**
	 * Prepares contextual info about the modertors of this board.
	 */
	protected function setModerators(): void
	{
		// Build a list of this board's moderators.
		Utils::$context['moderators'] = &Board::$info->moderators;
		Utils::$context['moderator_groups'] = &Board::$info->moderator_groups;
		Utils::$context['link_moderators'] = [];

		if (!empty(Board::$info->moderators)) {
			// Add a link for each moderator...
			foreach (Board::$info->moderators as $mod) {
				Utils::$context['link_moderators'][] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . Lang::$txt['board_moderator'] . '">' . $mod['name'] . '</a>';
			}
		}

		if (!empty(Board::$info->moderator_groups)) {
			// Add a link for each moderator group as well...
			foreach (Board::$info->moderator_groups as $mod_group) {
				Utils::$context['link_moderators'][] = '<a href="' . Config::$scripturl . '?action=groups;sa=viewmembers;group=' . $mod_group['id'] . '" title="' . Lang::$txt['board_moderator'] . '">' . $mod_group['name'] . '</a>';
			}
		}

		if (!empty(Utils::$context['link_moderators'])) {
			// And show it after the board's name.
			Utils::$context['linktree'][count(Utils::$context['linktree']) - 1]['extra_after'] = '<span class="board_moderators">(' . (count(Utils::$context['link_moderators']) == 1 ? Lang::$txt['moderator'] : Lang::$txt['moderators']) . ': ' . implode(', ', Utils::$context['link_moderators']) . ')</span>';
		}
	}

	/**
	 * Sets the unapproved posts message.
	 */
	protected function setUnapprovedPostsMessage(): void
	{
		// Show a message in case a recently posted message became unapproved.
		Utils::$context['becomesUnapproved'] = !empty($_SESSION['becomesUnapproved']);
		unset($_SESSION['becomesUnapproved']);
	}

	/**
	 * Loads the template and sets some related contextual info.
	 */
	protected function setupTemplate(): void
	{
		// Load the proper template.
		Theme::loadTemplate('Display');
		Theme::loadCSSFile('attachments.css', ['minimize' => true, 'order_pos' => 450], 'smf_attachments');

		// Set a canonical URL for this page.
		Utils::$context['canonical_url'] = Config::$scripturl . '?topic=' . Topic::$info->id . '.' . ($this->can_show_all ? '0;all' : Utils::$context['start']);

		// Censor the title...
		Lang::censorText(Topic::$info->subject);
		Utils::$context['page_title'] = Topic::$info->subject;

		// For backward compatibility with any mods that might expect this.
		Utils::$context['topicinfo'] = Topic::$info;

		// Information about the current topic...
		Utils::$context['num_replies'] = Topic::$info->num_replies;
		Utils::$context['real_num_replies'] = Topic::$info->real_num_replies;
		Utils::$context['topic_started_time'] = Topic::$info->started_time;
		Utils::$context['topic_started_timestamp'] = Topic::$info->started_timestamp;
		Utils::$context['topic_poster_name'] = Topic::$info->started_name;
		Utils::$context['topic_first_message'] = Topic::$info->id_first_msg;
		Utils::$context['topic_last_message'] = Topic::$info->id_last_msg;
		Utils::$context['topic_unwatched'] = Topic::$info->unwatched;
		Utils::$context['total_visible_posts'] = Topic::$info->total_visible_posts;
		Utils::$context['is_locked'] = Topic::$info->is_locked;
		Utils::$context['is_sticky'] = Topic::$info->is_sticky;
		Utils::$context['is_approved'] = Topic::$info->is_approved;
		Utils::$context['is_poll'] = Topic::$info->is_poll;
		Utils::$context['topic_starter_id'] = Topic::$info->id_member_started;
		Utils::$context['subject'] = Topic::$info->subject;
		Utils::$context['num_views'] = Lang::numberFormat(Topic::$info->num_views);
		Utils::$context['num_views_text'] = Utils::$context['num_views'] == 1 ? Lang::$txt['read_one_time'] : sprintf(Lang::$txt['read_many_times'], Utils::$context['num_views']);
		Utils::$context['mark_unread_time'] = !empty($this->virtual_msg) ? $this->virtual_msg : Topic::$info->new_from;

		// Default this topic to not marked for notifications... of course...
		Utils::$context['is_marked_notify'] = false;

		// Build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?topic=' . Topic::$info->id . '.0',
			'name' => Topic::$info->subject,
		];

		Utils::$context['jump_to'] = [
			'label' => addslashes(Utils::htmlspecialcharsDecode(Lang::$txt['jump_to'])),
			'board_name' => strtr(Utils::htmlspecialchars(strip_tags(Board::$info->name)), ['&amp;' => '&']),
			'child_level' => Board::$info->child_level,
		];

		// For quick reply we need a response prefix in the default forum language.
		if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix', 600))) {
			if (Lang::$default === User::$me->language) {
				Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
			} else {
				Lang::load('index', Lang::$default, false);
				Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
				Lang::load('index');
			}
			CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
		}

		// Are we showing signatures - or disabled fields?
		Utils::$context['signature_enabled'] = substr(Config::$modSettings['signature_settings'], 0, 1) == 1;
		Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : [];

		// Prevent signature images from going outside the box.
		if (Utils::$context['signature_enabled']) {
			list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
			$sig_limits = explode(',', $sig_limits);

			if (!empty($sig_limits[5]) || !empty($sig_limits[6])) {
				Theme::addInlineCss('.signature img { ' . (!empty($sig_limits[5]) ? 'max-width: ' . (int) $sig_limits[5] . 'px; ' : '') . (!empty($sig_limits[6]) ? 'max-height: ' . (int) $sig_limits[6] . 'px; ' : '') . '}');
			}
		}

		// Load the drafts js file.
		if (!empty(Topic::$info->permissions['drafts_autosave'])) {
			Theme::loadJavaScriptFile('drafts.js', ['defer' => false, 'minimize' => true], 'smf_drafts');
		}

		// And the drafts language file.
		if (!empty(Topic::$info->permissions['drafts_save'])) {
			Lang::load('Drafts');
		}

		// Spellcheck
		if (Utils::$context['show_spellchecking']) {
			Theme::loadJavaScriptFile('spellcheck.js', ['defer' => false, 'minimize' => true], 'smf_spellcheck');
		}

		// topic.js
		Theme::loadJavaScriptFile('topic.js', ['defer' => false, 'minimize' => true], 'smf_topic');

		// quotedText.js
		Theme::loadJavaScriptFile('quotedText.js', ['defer' => true, 'minimize' => true], 'smf_quotedText');

		// Mentions
		if (!empty(Config::$modSettings['enable_mentions']) && User::$me->allowedTo('mention')) {
			Theme::loadJavaScriptFile('jquery.atwho.min.js', ['defer' => true], 'smf_atwho');
			Theme::loadJavaScriptFile('jquery.caret.min.js', ['defer' => true], 'smf_caret');
			Theme::loadJavaScriptFile('mentions.js', ['defer' => true, 'minimize' => true], 'smf_mentions');
		}

		// Did we report a post to a moderator just now?
		Utils::$context['report_sent'] = isset($_GET['reportsent']);

		Utils::$context['name'] = $_SESSION['guest_name'] ?? '';
		Utils::$context['email'] = $_SESSION['guest_email'] ?? '';
	}

	/**
	 * Loads info about any calendar events that are linked to this topic.
	 */
	protected function loadEvents(): void
	{
		// If we want to show event information in the topic, prepare the data.
		if (User::$me->allowedTo('calendar_view') && !empty(Config::$modSettings['cal_showInTopic']) && !empty(Config::$modSettings['cal_enabled'])) {
			Utils::$context['linked_calendar_events'] = Event::load(Topic::$info->id, true);

			if (!empty(Utils::$context['linked_calendar_events'])) {
				Utils::$context['linked_calendar_events'][count(Utils::$context['linked_calendar_events']) - 1]['is_last'] = true;
			}
		}
	}

	/**
	 * Loads the poll linked to this topic, if applicable.
	 */
	protected function loadPoll(): void
	{
		if (!Utils::$context['is_poll']) {
			return;
		}

		$poll = Poll::load(Topic::$info->id, Poll::LOAD_BY_TOPIC);

		// Create the poll info if it exists and is valid.
		if (empty($poll)) {
			Utils::$context['is_poll'] = false;
		} else {
			Utils::$context['poll'] = $poll->format();
		}
	}

	/**
	 * Sets up anti-spam verification stuff, if needed.
	 */
	protected function setupVerification(): void
	{
		// Do we need to show the visual verification image?
		Utils::$context['require_verification'] = !User::$me->is_mod && !User::$me->is_admin && !empty(Config::$modSettings['posts_require_captcha']) && (User::$me->posts < Config::$modSettings['posts_require_captcha'] || (User::$me->is_guest && Config::$modSettings['posts_require_captcha'] == -1));

		if (Utils::$context['require_verification']) {
			$verifier = new Verifier(['id' => 'post']);
		}
	}

	/**
	 * Populates $this->messages and $this->posters with IDs of each post and
	 * poster in this topic.
	 */
	protected function getMessagesAndPosters(): void
	{
		$limit = Utils::$context['messages_per_page'];
		$start = $_REQUEST['start'];
		$ascending = empty(Theme::$current->options['view_newest_first']);
		$this->firstIndex = 0;

		// Jump to page
		// Calculate the fastest way to get the messages!
		if ($start >= Topic::$info->total_visible_posts / 2 && Utils::$context['messages_per_page'] != -1) {
			$DBascending = !$ascending;
			$limit = Topic::$info->total_visible_posts <= $start + $limit ? Topic::$info->total_visible_posts - $start : $limit;
			$start = Topic::$info->total_visible_posts <= $start + $limit ? 0 : Topic::$info->total_visible_posts - $start - $limit;
			$this->firstIndex = empty(Theme::$current->options['view_newest_first']) ? $start - 1 : $limit - 1;
		} else {
			$DBascending = $ascending;
		}

		// Get each post and poster in this topic.
		$request = Db::$db->query(
			'',
			'SELECT id_msg, id_member, approved
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
				AND (approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR id_member = {int:current_member}') . ')') . '
			ORDER BY id_msg ' . ($DBascending ? '' : 'DESC') . (Utils::$context['messages_per_page'] == -1 ? '' : '
			LIMIT {int:start}, {int:max}'),
			[
				'current_member' => User::$me->id,
				'current_topic' => Topic::$info->id,
				'is_approved' => 1,
				'blank_id_member' => 0,
				'start' => $start,
				'max' => $limit,
			],
		);

		$this->messages = [];
		$all_posters = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!empty($row['id_member'])) {
				$all_posters[$row['id_msg']] = $row['id_member'];
			}
			$this->messages[] = $row['id_msg'];
		}

		// Sort the messages into the correct display order
		if (!$DBascending) {
			sort($this->messages);
		}

		Db::$db->free_result($request);
		$this->posters = array_unique($all_posters);

		IntegrationHook::call('integrate_display_message_list', [&$this->messages, &$this->posters]);
	}

	/**
	 * Initializes Msg::get() and loads attachments and likes.
	 */
	protected function initDisplayContext(): void
	{
		Utils::$context['loaded_attachments'] = [];

		// If there _are_ messages here... (probably an error otherwise :!)
		if (!empty($this->messages)) {
			Msg::$getter = Msg::get($this->messages);

			// Fetch attachments.
			if (!empty(Config::$modSettings['attachmentEnable']) && User::$me->allowedTo('view_attachments')) {
				Attachment::prepareByMsg($this->messages);
			}

			// And the likes
			if (!empty(Config::$modSettings['enable_likes'])) {
				Utils::$context['my_likes'] = Topic::$info->getLikedMsgs();
			}

			// Go to the last message if the given time is beyond the time of the last message.
			if (isset(Utils::$context['start_from']) && Utils::$context['start_from'] >= Topic::$info->num_replies) {
				Utils::$context['start_from'] = Topic::$info->num_replies;
			}

			// Since the anchor information is needed on the top of the page we load these variables beforehand.
			Utils::$context['first_message'] = $this->messages[$this->firstIndex] ?? $this->messages[0];

			if (empty(Theme::$current->options['view_newest_first'])) {
				Utils::$context['first_new_message'] = isset(Utils::$context['start_from']) && $_REQUEST['start'] == Utils::$context['start_from'];
			} else {
				Utils::$context['first_new_message'] = isset(Utils::$context['start_from']) && $_REQUEST['start'] == Topic::$info->num_replies - Utils::$context['start_from'];
			}
		} else {
			Msg::$getter = [];
			Utils::$context['first_message'] = 0;
			Utils::$context['first_new_message'] = false;
			Utils::$context['likes'] = [];
		}

		// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
		// This will be called from the template.
		Utils::$context['get_message'] = [$this, 'prepareDisplayContext'];
	}

	/**
	 * Warn the user against replying to old topics.
	 */
	protected function setOldTopicWarning(): void
	{
		// When was the last time this topic was replied to?  Should we warn them about it?
		if (!empty(Config::$modSettings['oldTopicDays']) && (Topic::$info->permissions['can_reply'] || Topic::$info->permissions['can_reply_unapproved']) && empty(Topic::$info->is_sticky)) {
			$request = Db::$db->query(
				'',
				'SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_last_msg}
				LIMIT 1',
				[
					'id_last_msg' => Topic::$info->id_last_msg,
				],
			);

			list($lastPostTime) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Utils::$context['oldTopicError'] = $lastPostTime + Config::$modSettings['oldTopicDays'] * 86400 < time();
		}
	}

	/**
	 * Loads the editor for the quick reply.
	 */
	protected function loadEditor(): void
	{
		// Now create the editor.
		new Editor([
			'id' => 'quickReply',
			'value' => '',
			'labels' => [
				'post_button' => Lang::$txt['post'],
			],
			// add height and width for the editor
			'height' => '150px',
			'width' => '100%',
			// We do HTML preview here.
			'preview_type' => Editor::PREVIEW_HTML,
			// This is required
			'required' => true,
		]);

		Utils::$context['attached'] = '';
		Utils::$context['make_poll'] = isset($_REQUEST['poll']);

		// Message icons - customized icons are off?
		Utils::$context['icons'] = Editor::getMessageIcons(Board::$info->id);

		if (!empty(Utils::$context['icons'])) {
			Utils::$context['icons'][count(Utils::$context['icons']) - 1]['is_last'] = true;
		}
	}

	/**
	 * Builds the user and moderator button arrays.
	 */
	protected function buildButtons(): void
	{
		// Build the normal button array.
		Utils::$context['normal_buttons'] = [];

		if (Topic::$info->permissions['can_reply']) {
			Utils::$context['normal_buttons']['reply'] = ['text' => 'reply', 'url' => Config::$scripturl . '?action=post;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';last_msg=' . Topic::$info->id_last_msg, 'active' => true];
		}

		if (Topic::$info->permissions['can_add_poll']) {
			Utils::$context['normal_buttons']['add_poll'] = ['text' => 'add_poll', 'url' => Config::$scripturl . '?action=editpoll;add;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start']];
		}

		if (Topic::$info->permissions['can_mark_unread']) {
			Utils::$context['normal_buttons']['mark_unread'] = ['text' => 'mark_unread', 'url' => Config::$scripturl . '?action=markasread;sa=topic;t=' . Utils::$context['mark_unread_time'] . ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		if (Topic::$info->permissions['can_print']) {
			Utils::$context['normal_buttons']['print'] = ['text' => 'print', 'custom' => 'rel="nofollow"', 'url' => Config::$scripturl . '?action=printpage;topic=' . Utils::$context['current_topic'] . '.0'];
		}

		if (Topic::$info->permissions['can_set_notify']) {
			Utils::$context['normal_buttons']['notify'] = [
				'text' => 'notify_topic_' . Utils::$context['topic_notification_mode'],
				'sub_buttons' => [
					[
						'test' => 'can_unwatch',
						'text' => 'notify_topic_0',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
					[
						'text' => 'notify_topic_1',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=1;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
					[
						'text' => 'notify_topic_2',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=2;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
					[
						'text' => 'notify_topic_3',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=3;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
				],
			];
		}

		// Build the mod button array
		Utils::$context['mod_buttons'] = [];

		if (Topic::$info->permissions['can_move']) {
			Utils::$context['mod_buttons']['move'] = ['text' => 'move_topic', 'url' => Config::$scripturl . '?action=movetopic;current_board=' . Utils::$context['current_board'] . ';topic=' . Utils::$context['current_topic'] . '.0'];
		}

		if (Topic::$info->permissions['can_delete']) {
			Utils::$context['mod_buttons']['delete'] = ['text' => 'remove_topic', 'custom' => 'data-confirm="' . Lang::$txt['are_sure_remove_topic'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=removetopic2;topic=' . Utils::$context['current_topic'] . '.0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		if (Topic::$info->permissions['can_lock']) {
			Utils::$context['mod_buttons']['lock'] = ['text' => empty(Utils::$context['is_locked']) ? 'set_lock' : 'set_unlock', 'url' => Config::$scripturl . '?action=lock;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';sa=' . (Utils::$context['is_locked'] ? 'unlock' : 'lock') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		if (Topic::$info->permissions['can_sticky']) {
			Utils::$context['mod_buttons']['sticky'] = ['text' => empty(Utils::$context['is_sticky']) ? 'set_sticky' : 'set_nonsticky', 'url' => Config::$scripturl . '?action=sticky;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';sa=' . (Utils::$context['is_sticky'] ? 'nonsticky' : 'sticky') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		if (Topic::$info->permissions['can_merge']) {
			Utils::$context['mod_buttons']['merge'] = ['text' => 'merge', 'url' => Config::$scripturl . '?action=mergetopics;board=' . Utils::$context['current_board'] . '.0;from=' . Utils::$context['current_topic']];
		}

		if (Topic::$info->permissions['calendar_post']) {
			Utils::$context['mod_buttons']['calendar'] = ['text' => 'calendar_link', 'url' => Config::$scripturl . '?action=post;calendar;msg=' . Topic::$info->id_first_msg . ';topic=' . Utils::$context['current_topic'] . '.0'];
		}

		// Restore topic. eh?  No monkey business.
		if (Topic::$info->permissions['can_restore_topic']) {
			Utils::$context['mod_buttons']['restore_topic'] = ['text' => 'restore_topic', 'url' => Config::$scripturl . '?action=restoretopic;topics=' . Utils::$context['current_topic'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		// Allow adding new buttons easily.
		// Note: Utils::$context['normal_buttons'] and Utils::$context['mod_buttons'] are added for backward compatibility with 2.0, but are deprecated and should not be used
		IntegrationHook::call('integrate_display_buttons', [&Utils::$context['normal_buttons']]);
		// Note: integrate_mod_buttons is no longer necessary and deprecated, but is kept for backward compatibility with 2.0
		IntegrationHook::call('integrate_mod_buttons', [&Utils::$context['mod_buttons']]);

		// If any buttons have a 'test' check, run those tests now to keep things clean.
		foreach (['normal_buttons', 'mod_buttons'] as $button_strip) {
			foreach (Utils::$context[$button_strip] as $key => $value) {
				if (isset($value['test']) && empty(Utils::$context[$value['test']])) {
					unset(Utils::$context[$button_strip][$key]);
				} elseif (isset($value['sub_buttons'])) {
					foreach ($value['sub_buttons'] as $subkey => $subvalue) {
						if (isset($subvalue['test']) && empty(Utils::$context[$subvalue['test']])) {
							unset(Utils::$context[$button_strip][$key]['sub_buttons'][$subkey]);
						}
					}
				}
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Display::exportStatic')) {
	Display::exportStatic();
}

?>