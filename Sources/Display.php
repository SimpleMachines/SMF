<?php

/**
 * This is perhaps the most important and probably most accessed file in all
 * of SMF.  This file controls topic, message, and attachment display.
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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * The central part of the board - topic display.
 * This class loads the posts in a topic up so they can be displayed.
 * It uses the main sub template of the Display template.
 * It requires a topic, and can go to the previous or next topic from it.
 * It jumps to the correct post depending on a number/time/IS_MSG passed.
 * It depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
 * It is accessed by ?topic=id_topic.START.
 */
class Display
{
	use namespace\BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static array $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'Display',
		),
	);

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
	private $messages = array();

	/**
	 * @var array
	 *
	 * ID numbers of the authors of the $messages.
	 */
	private $posters = array();

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
		checkSubmitOnce('register');

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
		if ($counter === null)
		{
			$counter = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start'] : Topic::$info->total_visible_posts - Utils::$context['start'];
		}

		if (!(Msg::$getter instanceof \Generator))
			return false;

		$message = Msg::$getter->current();
		Msg::$getter->next();

		if (!$message)
			return false;

		$output = $message->format($counter);

		// Set up the quick buttons.
		$output['quickbuttons'] = array(
			'quote' => array(
				'label' => Lang::$txt['quote_action'],
				'href' => Config::$scripturl . '?action=post;quote=' . $output['id'] . ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';last_msg=' . Topic::$info->id_last_msg,
				'javascript' => 'onclick="return oQuickReply.quote(' . $output['id'] . ');"',
				'icon' => 'quote',
				'show' => Utils::$context['can_quote'],
			),
			'quote_selected' => array(
				'label' => Lang::$txt['quote_selected_action'],
				'id' => 'quoteSelected_' . $output['id'],
				'href' => 'javascript:void(0)',
				'custom' => 'style="display:none"',
				'icon' => 'quote_selected',
				'show' => Utils::$context['can_quote'],
			),
			'quick_edit' => array(
				'label' => Lang::$txt['quick_edit'],
				'class' => 'quick_edit',
				'id' => 'modify_button_' . $output['id'],
				'custom' => 'onclick="oQuickModify.modifyMsg(\'' . $output['id'] . '\', \'' . !empty(Config::$modSettings['toggle_subject']) . '\')"',
				'icon' => 'quick_edit_button',
				'show' => $output['can_modify'],
			),
			'more' => array(
				'modify' => array(
					'label' => Lang::$txt['modify'],
					'href' => Config::$scripturl . '?action=post;msg=' . $output['id'] . ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'],
					'icon' => 'modify_button',
					'show' => $output['can_modify'],
				),
				'remove_topic' => array(
					'label' => Lang::$txt['remove_topic'],
					'href' => Config::$scripturl . '?action=removetopic2;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'javascript' => 'data-confirm="' . Lang::$txt['are_sure_remove_topic'] . '"',
					'class' => 'you_sure',
					'icon' => 'remove_button',
					'show' => Utils::$context['can_delete'] && (Topic::$info->id_first_msg == $output['id']),
				),
				'remove' => array(
					'label' => Lang::$txt['remove'],
					'href' => Config::$scripturl . '?action=deletemsg;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';msg=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'javascript' => 'data-confirm="' . Lang::$txt['remove_message_question'] . '"',
					'class' => 'you_sure',
					'icon' => 'remove_button',
					'show' => $output['can_remove'] && (Topic::$info->id_first_msg != $output['id']),
				),
				'split' => array(
					'label' => Lang::$txt['split'],
					'href' => Config::$scripturl . '?action=splittopics;topic=' . Utils::$context['current_topic'] . '.0;at=' . $output['id'],
					'icon' => 'split_button',
					'show' => Utils::$context['can_split'] && !empty(Topic::$info->real_num_replies),
				),
				'report' => array(
					'label' => Lang::$txt['report_to_mod'],
					'href' => Config::$scripturl . '?action=reporttm;topic=' . Utils::$context['current_topic'] . '.' . $output['counter'] . ';msg=' . $output['id'],
					'icon' => 'error',
					'show' => Utils::$context['can_report_moderator'],
				),
				'warn' => array(
					'label' => Lang::$txt['issue_warning'],
					'href' => Config::$scripturl . '?action=profile;area=issuewarning;u=' . $output['member']['id'] . ';msg=' . $output['id'],
					'icon' => 'warn_button',
					'show' => Utils::$context['can_issue_warning'] && !$output['is_message_author'] && !$output['member']['is_guest'],
				),
				'restore' => array(
					'label' => Lang::$txt['restore_message'],
					'href' => Config::$scripturl . '?action=restoretopic;msgs=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'icon' => 'restore_button',
					'show' => Utils::$context['can_restore_msg'],
				),
				'approve' => array(
					'label' => Lang::$txt['approve'],
					'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';msg=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'icon' => 'approve_button',
					'show' => $output['can_approve'],
				),
				'unapprove' => array(
					'label' => Lang::$txt['unapprove'],
					'href' => Config::$scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';msg=' . $output['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'icon' => 'unapprove_button',
					'show' => $output['can_unapprove'],
				),
			),
			'quickmod' => array(
				'class' => 'inline_mod_check',
				'id' => 'in_topic_mod_check_' . $output['id'],
				'custom' => 'style="display: none;"',
				'content' => '',
				'show' => !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && $output['can_remove'],
			),
		);

		if (empty(Theme::$current->options['view_newest_first']))
			$counter++;
		else
			$counter--;

		call_integration_hook('integrate_prepare_display_context', array(&$output, $message, $counter));

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
	 * In-topic quick moderation.
	 */
	public static function QuickInTopicModeration(): void
	{
		// Check the session = get or post.
		checkSession('request');

		require_once(Config::$sourcedir . '/RemoveTopic.php');

		if (empty($_REQUEST['msgs']))
			redirectexit('topic=' . Topic::$topic_id . '.' . $_REQUEST['start']);

		$messages = array();
		foreach ($_REQUEST['msgs'] as $dummy)
			$messages[] = (int) $dummy;

		// We are restoring messages. We handle this in another place.
		if (isset($_REQUEST['restore_selected']))
			redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		if (isset($_REQUEST['split_selection']))
		{
			$request = Db::$db->query('', '
				SELECT subject
				FROM {db_prefix}messages
				WHERE id_msg = {int:message}
				LIMIT 1',
				array(
					'message' => min($messages),
				)
			);
			list($subname) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
			$_SESSION['split_selection'][Topic::$topic_id] = $messages;
			redirectexit('action=splittopics;sa=selectTopics;topic=' . Topic::$topic_id . '.0;subname_enc=' . urlencode($subname) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Allowed to delete any message?
		if (allowedTo('delete_any'))
			$allowed_all = true;
		// Allowed to delete replies to their messages?
		elseif (allowedTo('delete_replies'))
		{
			$request = Db::$db->query('', '
				SELECT id_member_started
				FROM {db_prefix}topics
				WHERE id_topic = {int:current_topic}
				LIMIT 1',
				array(
					'current_topic' => Topic::$topic_id,
				)
			);
			list ($starter) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$allowed_all = $starter == User::$me->id;
		}
		else
			$allowed_all = false;

		// Make sure they're allowed to delete their own messages, if not any.
		if (!$allowed_all)
			isAllowedTo('delete_own');

		// Allowed to remove which messages?
		$request = Db::$db->query('', '
			SELECT id_msg, subject, id_member, poster_time
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:message_list})
				AND id_topic = {int:current_topic}' . (!$allowed_all ? '
				AND id_member = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			array(
				'current_member' => User::$me->id,
				'current_topic' => Topic::$topic_id,
				'message_list' => $messages,
				'limit' => count($messages),
			)
		);
		$messages = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (!$allowed_all && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 < time())
				continue;

			$messages[$row['id_msg']] = array($row['subject'], $row['id_member']);
		}
		Db::$db->free_result($request);

		// Get the first message in the topic - because you can't delete that!
		$request = Db::$db->query('', '
			SELECT id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => Topic::$topic_id,
			)
		);
		list ($first_message, $last_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Delete all the messages we know they can delete. ($messages)
		foreach ($messages as $message => $info)
		{
			// Just skip the first message - if it's not the last.
			if ($message == $first_message && $message != $last_message)
				continue;
			// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
			elseif ($message == $first_message)
				$topicGone = true;

			removeMessage($message);

			// Log this moderation action ;).
			if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != User::$me->id))
				logAction('delete', array('topic' => Topic::$topic_id, 'subject' => $info[0], 'member' => $info[1], 'board' => Board::$info->id));
		}

		redirectexit(!empty($topicGone) ? 'board=' . Board::$info->id : 'topic=' . Topic::$topic_id . '.' . $_REQUEST['start']);
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
		if (empty(Topic::$topic_id))
			fatal_lang_error('no_board', false);

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
		foreach (Topic::$info->doPermissions() as $perm => $val)
			Utils::$context[$perm] = &Topic::$info->permissions[$perm];

		$this->setupTemplate();
	}

	/**
	 * Redirect to the previous or next topic, if requested in the URL params.
	 */
	protected function checkPrevNextRedirect(): void
	{
		// Find the previous or next topic. But don't bother if there's only one.
		if (isset($_REQUEST['prev_next']) && in_array($_REQUEST['prev_next'], array('prev', 'next')) && Board::$info->num_topics > 1)
		{
			$prev = $_REQUEST['prev_next'] === 'prev';

			// Just prepare some variables that are used in the query.
			$gt_lt = $prev ? '>' : '<';
			$order = $prev ? '' : ' DESC';

			$request = Db::$db->query('', '
				SELECT t2.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}topics AS t2 ON (
					(t2.id_last_msg ' . $gt_lt . ' t.id_last_msg AND t2.is_sticky ' . $gt_lt . '= t.is_sticky) OR t2.is_sticky ' . $gt_lt . ' t.is_sticky)
				WHERE t.id_topic = {int:current_topic}
					AND t2.id_board = {int:current_board}' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND (t2.approved = {int:is_approved} OR (t2.id_member_started != {int:id_member_started} AND t2.id_member_started = {int:current_member}))') . '
				ORDER BY t2.is_sticky' . $order . ', t2.id_last_msg' . $order . '
				LIMIT 1',
				array(
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
					'current_topic' => Topic::$topic_id,
					'is_approved' => 1,
					'id_member_started' => 0,
				)
			);

			// No more left.
			if (Db::$db->num_rows($request) == 0)
			{
				Db::$db->free_result($request);

				// Roll over - if we're going prev, get the last - otherwise the first.
				$request = Db::$db->query('', '
					SELECT id_topic
					FROM {db_prefix}topics
					WHERE id_board = {int:current_board}' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
						AND (approved = {int:is_approved} OR (id_member_started != {int:id_member_started} AND id_member_started = {int:current_member}))') . '
					ORDER BY is_sticky' . $order . ', id_last_msg' . $order . '
					LIMIT 1',
					array(
						'current_board' => Board::$info->id,
						'current_member' => User::$me->id,
						'is_approved' => 1,
						'id_member_started' => 0,
					)
				);
			}

			// Now you can be sure Topic::$topic_id is the id_topic to view.
			list (Topic::$topic_id) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Go to the newest message on the prev/next topic.
			redirectexit('topic=' . Topic::$topic_id . ';start=new#new');
		}
	}

	/**
	 * Is this a moved or merged topic that we are redirecting to?
	 */
	protected function checkMovedMergedRedirect(): void
	{
		if (!empty(Topic::$info->id_redirect_topic))
		{
			// Mark this as read.
			if (!User::$me->is_guest && Topic::$info->new_from != Topic::$info->id_first_msg)
			{
				Db::$db->insert(Topic::$info->new_from == 0 ? 'ignore' : 'replace',
					'{db_prefix}log_topics',
					array(
						'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
					),
					array(
						User::$me->id, Topic::$info->id, Topic::$info->id_first_msg, Topic::$info->unwatched,
					),
					array('id_member', 'id_topic')
				);
			}

			redirectexit('topic=' . Topic::$info->id_redirect_topic . '.0', false, true);
		}
	}

	/**
	 * Blocks browser attempts to prefetch the topic display.
	 */
	protected function preventPrefetch(): void
	{
		// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		{
			ob_end_clean();
			send_http_status(403, 'Prefetch Forbidden');
			die;
		}
	}

	/**
	 * Tells search engines not to index pages they shouldn't.
	 */
	protected function setRobotNoIndex(): void
	{
		// Let's do some work on what to search index.
		if (count($_GET) > 2)
		{
			foreach ($_GET as $k => $v)
			{
				if (!in_array($k, array('topic', 'board', 'start', session_name())))
					Utils::$context['robot_no_index'] = true;
			}
		}

		if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % Utils::$context['messages_per_page'] != 0))
			Utils::$context['robot_no_index'] = true;
	}

	/**
	 * Add 1 to the number of views of this topic (except for robots).
	 */
	protected function incrementNumViews(): void
	{
		if (!User::$me->possibly_robot && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != Topic::$info->id))
		{
			Db::$db->query('', '
				UPDATE {db_prefix}topics
				SET num_views = num_views + 1
				WHERE id_topic = {int:current_topic}',
				array(
					'current_topic' => Topic::$info->id,
				)
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
		if (!User::$me->is_guest && !empty($this->messages))
		{
			$mark_at_msg = max($this->messages);
			if ($mark_at_msg >= Topic::$info->id_last_msg)
				$mark_at_msg = Config::$modSettings['maxMsgID'];
			if ($mark_at_msg >= Topic::$info->new_from)
			{
				Db::$db->insert(Topic::$info->new_from == 0 ? 'ignore' : 'replace',
					'{db_prefix}log_topics',
					array(
						'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
					),
					array(
						User::$me->id, Topic::$info->id, $mark_at_msg, Topic::$info->unwatched,
					),
					array('id_member', 'id_topic')
				);
			}

			// Check for notifications on this topic OR board.
			$request = Db::$db->query('', '
				SELECT sent, id_topic
				FROM {db_prefix}log_notify
				WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
					AND id_member = {int:current_member}
				LIMIT 2',
				array(
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
					'current_topic' => Topic::$info->id,
				)
			);
			$do_once = true;
			while ($row = Db::$db->fetch_assoc($request))
			{
				// Find if this topic is marked for notification...
				if (!empty($row['id_topic']))
					Utils::$context['is_marked_notify'] = true;

				// Only do this once, but mark the notifications as "not sent yet" for next time.
				if (!empty($row['sent']) && $do_once)
				{
					Db::$db->query('', '
						UPDATE {db_prefix}log_notify
						SET sent = {int:is_not_sent}
						WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
							AND id_member = {int:current_member}',
						array(
							'current_board' => Board::$info->id,
							'current_member' => User::$me->id,
							'current_topic' => Topic::$info->id,
							'is_not_sent' => 0,
						)
					);
					$do_once = false;
				}
			}

			// Have we recently cached the number of new topics in this board, and it's still a lot?
			if (isset($_REQUEST['topicseen']) && isset($_SESSION['topicseen_cache'][Board::$info->id]) && $_SESSION['topicseen_cache'][Board::$info->id] > 5)
			{
				$_SESSION['topicseen_cache'][Board::$info->id]--;
			}
			// Mark board as seen if this is the only new topic.
			elseif (isset($_REQUEST['topicseen']))
			{
				// Use the mark read tables... and the last visit to figure out if this should be read or not.
				$request = Db::$db->query('', '
					SELECT COUNT(*)
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = {int:current_board} AND lb.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					WHERE t.id_board = {int:current_board}
						AND t.id_last_msg > COALESCE(lb.id_msg, 0)
						AND t.id_last_msg > COALESCE(lt.id_msg, 0)' . (empty($_SESSION['id_msg_last_visit']) ? '' : '
						AND t.id_last_msg > {int:id_msg_last_visit}'),
					array(
						'current_board' => Board::$info->id,
						'current_member' => User::$me->id,
						'id_msg_last_visit' => (int) $_SESSION['id_msg_last_visit'],
					)
				);
				list ($numNewTopics) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// If there're no real new topics in this board, mark the board as seen.
				if (empty($numNewTopics))
					$_REQUEST['boardseen'] = true;
				else
					$_SESSION['topicseen_cache'][Board::$info->id] = $numNewTopics;
			}
			// Probably one less topic - maybe not, but even if we decrease this too fast it will only make us look more often.
			elseif (isset($_SESSION['topicseen_cache'][Board::$info->id]))
				$_SESSION['topicseen_cache'][Board::$info->id]--;

			// Mark board as seen if we came using last post link from BoardIndex. (or other places...)
			if (isset($_REQUEST['boardseen']))
			{
				Db::$db->insert('replace',
					'{db_prefix}log_boards',
					array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
					array(Config::$modSettings['maxMsgID'], User::$me->id, Board::$info->id),
					array('id_member', 'id_board')
				);
			}

			// Mark any alerts about this topic or the posts on this page as read.
			if (!empty(User::$me->alerts))
			{
				Db::$db->query('', '
					UPDATE {db_prefix}user_alerts
					SET is_read = {int:now}
					WHERE is_read = 0 AND id_member = {int:current_member}
						AND
						(
							(content_id IN ({array_int:messages}) AND content_type = {string:msg})
							OR
							(content_id = {int:current_topic} AND (content_type = {string:topic} OR (content_type = {string:board} AND content_action = {string:topic})))
						)',
					array(
						'topic' => 'topic',
						'board' => 'board',
						'msg' => 'msg',
						'current_member' => User::$me->id,
						'current_topic' => Topic::$info->id,
						'messages' => $this->messages,
						'now' => time(),
					)
				);
				// If changes made, update the member record as well
				if (Db::$db->affected_rows() > 0)
				{
					require_once(Config::$sourcedir . '/Profile-Modify.php');
					User::$me->alerts = alert_count(User::$me->id, true);
					User::updateMemberData(User::$me->id, array('alerts' => User::$me->alerts));
				}
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
		if (!is_numeric($_REQUEST['start']))
		{
			// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
			if ($_REQUEST['start'] == 'new')
			{
				// Guests automatically go to the last post.
				if (User::$me->is_guest)
				{
					Utils::$context['start_from'] = Topic::$info->total_visible_posts - 1;
					$_REQUEST['start'] = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start_from'] : 0;
				}
				else
				{
					// Find the earliest unread message in the topic. (the use of topics here is just for both tables.)
					$request = Db::$db->query('', '
						SELECT COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from
						FROM {db_prefix}topics AS t
							LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
							LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})
						WHERE t.id_topic = {int:current_topic}
						LIMIT 1',
						array(
							'current_board' => Board::$info->id,
							'current_member' => User::$me->id,
							'current_topic' => Topic::$topic_id,
						)
					);
					list ($new_from) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					// Fall through to the next if statement.
					$_REQUEST['start'] = 'msg' . $new_from;
				}
			}

			// Start from a certain time index, not a message.
			if (substr($_REQUEST['start'], 0, 4) == 'from')
			{
				$timestamp = (int) substr($_REQUEST['start'], 4);
				if ($timestamp === 0)
					$_REQUEST['start'] = 0;
				else
				{
					// Find the number of messages posted before said time...
					$request = Db::$db->query('', '
						SELECT COUNT(*)
						FROM {db_prefix}messages
						WHERE poster_time < {int:timestamp}
							AND id_topic = {int:current_topic}' . (Config::$modSettings['postmod_active'] && Topic::$info->unapproved_posts && !allowedTo('approve_posts') ? '
							AND (approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
						array(
							'current_topic' => Topic::$topic_id,
							'current_member' => User::$me->id,
							'is_approved' => 1,
							'timestamp' => $timestamp,
						)
					);
					list (Utils::$context['start_from']) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					// Handle view_newest_first options, and get the correct start value.
					$_REQUEST['start'] = empty(Theme::$current->options['view_newest_first']) ? Utils::$context['start_from'] : Topic::$info->total_visible_posts - Utils::$context['start_from'] - 1;
				}
			}

			// Link to a message...
			elseif (substr($_REQUEST['start'], 0, 3) == 'msg')
			{
				$this->virtual_msg = (int) substr($_REQUEST['start'], 3);
				if (!Topic::$info->unapproved_posts && $this->virtual_msg >= Topic::$info->id_last_msg)
					Utils::$context['start_from'] = Topic::$info->total_visible_posts - 1;
				elseif (!Topic::$info->unapproved_posts && $this->virtual_msg <= Topic::$info->id_first_msg)
					Utils::$context['start_from'] = 0;
				else
				{
					// Find the start value for that message......
					$request = Db::$db->query('', '
						SELECT COUNT(*)
						FROM {db_prefix}messages
						WHERE id_msg < {int:virtual_msg}
							AND id_topic = {int:current_topic}' . (Config::$modSettings['postmod_active'] && Topic::$info->unapproved_posts && !allowedTo('approve_posts') ? '
							AND (approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
						array(
							'current_member' => User::$me->id,
							'current_topic' => Topic::$topic_id,
							'virtual_msg' => $this->virtual_msg,
							'is_approved' => 1,
							'no_member' => 0,
						)
					);
					list (Utils::$context['start_from']) = Db::$db->fetch_row($request);
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
		if (!empty(Theme::$current->settings['display_who_viewing']))
		{
			// Start out with no one at all viewing it.
			Utils::$context['view_members'] = array();
			Utils::$context['view_members_list'] = array();
			Utils::$context['view_num_hidden'] = 0;

			// Search for members who have this topic set in their GET data.
			$request = Db::$db->query('', '
				SELECT
					lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
					mg.online_color, mg.id_group, mg.group_name
				FROM {db_prefix}log_online AS lo
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_id_group} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
				array(
					'reg_id_group' => 0,
					'in_url_string' => '"topic":' . Topic::$info->id,
					'session' => User::$me->is_guest ? 'ip' . User::$me->ip : session_id(),
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (empty($row['id_member']))
					continue;

				if (!empty($row['online_color']))
					$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
				else
					$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';

				$is_buddy = in_array($row['id_member'], User::$me->buddies);

				if ($is_buddy)
					$link = '<strong>' . $link . '</strong>';

				// Add them both to the list and to the more detailed list.
				if (!empty($row['show_online']) || allowedTo('moderate_forum'))
				{
					Utils::$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;
				}

				Utils::$context['view_members'][$row['log_time'] . $row['member_name']] = array(
					'id' => $row['id_member'],
					'username' => $row['member_name'],
					'name' => $row['real_name'],
					'group' => $row['id_group'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => $link,
					'is_buddy' => $is_buddy,
					'hidden' => empty($row['show_online']),
				);

				if (empty($row['show_online']))
					Utils::$context['view_num_hidden']++;
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
		if (isset($_REQUEST['all']) && !$this->can_show_all)
			unset($_REQUEST['all']);
		// Otherwise, it must be allowed... so pretend start was -1.
		elseif (isset($_REQUEST['all']))
			$_REQUEST['start'] = -1;

		// Construct the page index, allowing for the .START method...
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?topic=' . Topic::$info->id . '.%1$d', $_REQUEST['start'], Topic::$info->total_visible_posts, Utils::$context['messages_per_page'], true);

		Utils::$context['start'] = $_REQUEST['start'];

		// This is information about which page is current, and which page we're on - in case you don't like the constructed page index. (again, wireless..)
		Utils::$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / Utils::$context['messages_per_page'] + 1,
			'num_pages' => floor((Topic::$info->total_visible_posts - 1) / Utils::$context['messages_per_page']) + 1,
		);

		// Figure out all the links to the next/prev/first/last/etc.
		if (!($this->can_show_all && isset($_REQUEST['all'])))
		{
			Utils::$context['links'] = array(
				'first' => $_REQUEST['start'] >= Utils::$context['messages_per_page'] ? Config::$scripturl . '?topic=' . Topic::$info->id . '.0' : '',
				'prev' => $_REQUEST['start'] >= Utils::$context['messages_per_page'] ? Config::$scripturl . '?topic=' . Topic::$info->id . '.' . ($_REQUEST['start'] - Utils::$context['messages_per_page']) : '',
				'next' => $_REQUEST['start'] + Utils::$context['messages_per_page'] < Topic::$info->total_visible_posts ? Config::$scripturl . '?topic=' . Topic::$info->id . '.' . ($_REQUEST['start'] + Utils::$context['messages_per_page']) : '',
				'last' => $_REQUEST['start'] + Utils::$context['messages_per_page'] < Topic::$info->total_visible_posts ? Config::$scripturl . '?topic=' . Topic::$info->id . '.' . (floor(Topic::$info->total_visible_posts / Utils::$context['messages_per_page']) * Utils::$context['messages_per_page']) : '',
				'up' => Config::$scripturl . '?board=' . Board::$info->id . '.0'
			);
		}

		// If they are viewing all the posts, show all the posts, otherwise limit the number.
		if ($this->can_show_all)
		{
			if (isset($_REQUEST['all']))
			{
				// No limit! (actually, there is a limit, but...)
				Utils::$context['messages_per_page'] = -1;
				Utils::$context['page_index'] .= sprintf(strtr(Theme::$current->settings['page_index']['current_page'], array('%1$d' => '%1$s')), Lang::$txt['all']);

				// Set start back to 0...
				$_REQUEST['start'] = 0;
			}
			// They aren't using it, but the *option* is there, at least.
			else
			{
				Utils::$context['page_index'] .= sprintf(strtr(Theme::$current->settings['page_index']['page'], array('{URL}' => Config::$scripturl . '?topic=' . Topic::$info->id . '.0;all')), '', Lang::$txt['all']);
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
		Utils::$context['link_moderators'] = array();
		if (!empty(Board::$info->moderators))
		{
			// Add a link for each moderator...
			foreach (Board::$info->moderators as $mod)
				Utils::$context['link_moderators'][] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . Lang::$txt['board_moderator'] . '">' . $mod['name'] . '</a>';
		}
		if (!empty(Board::$info->moderator_groups))
		{
			// Add a link for each moderator group as well...
			foreach (Board::$info->moderator_groups as $mod_group)
				Utils::$context['link_moderators'][] = '<a href="' . Config::$scripturl . '?action=groups;sa=viewmembers;group=' . $mod_group['id'] . '" title="' . Lang::$txt['board_moderator'] . '">' . $mod_group['name'] . '</a>';
		}

		if (!empty(Utils::$context['link_moderators']))
		{
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
		Theme::loadCSSFile('attachments.css', array('minimize' => true, 'order_pos' => 450), 'smf_attachments');

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
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?topic=' . Topic::$info->id . '.0',
			'name' => Topic::$info->subject,
		);

		Utils::$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars(Lang::$txt['jump_to'])),
			'board_name' => strtr(Utils::htmlspecialchars(strip_tags(Board::$info->name)), array('&amp;' => '&')),
			'child_level' => Board::$info->child_level,
		);

		// For quick reply we need a response prefix in the default forum language.
		if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix', 600)))
		{
			if (Lang::$default === User::$me->language)
				Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
			else
			{
				Lang::load('index', Lang::$default, false);
				Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
				Lang::load('index');
			}
			CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
		}

		// Are we showing signatures - or disabled fields?
		Utils::$context['signature_enabled'] = substr(Config::$modSettings['signature_settings'], 0, 1) == 1;
		Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : array();

		// Prevent signature images from going outside the box.
		if (Utils::$context['signature_enabled'])
		{
			list ($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);
			$sig_limits = explode(',', $sig_limits);

			if (!empty($sig_limits[5]) || !empty($sig_limits[6]))
				Theme::addInlineCss('.signature img { ' . (!empty($sig_limits[5]) ? 'max-width: ' . (int) $sig_limits[5] . 'px; ' : '') . (!empty($sig_limits[6]) ? 'max-height: ' . (int) $sig_limits[6] . 'px; ' : '') . '}');
		}

		// Load the drafts js file.
		if (!empty(Topic::$info->permissions['drafts_autosave']))
			Theme::loadJavaScriptFile('drafts.js', array('defer' => false, 'minimize' => true), 'smf_drafts');

		// And the drafts language file.
		if (!empty(Topic::$info->permissions['drafts_save']))
			Lang::load('Drafts');

		// Spellcheck
		if (Utils::$context['show_spellchecking'])
			Theme::loadJavaScriptFile('spellcheck.js', array('defer' => false, 'minimize' => true), 'smf_spellcheck');

		// topic.js
		Theme::loadJavaScriptFile('topic.js', array('defer' => false, 'minimize' => true), 'smf_topic');

		// quotedText.js
		Theme::loadJavaScriptFile('quotedText.js', array('defer' => true, 'minimize' => true), 'smf_quotedText');

		// Mentions
		if (!empty(Config::$modSettings['enable_mentions']) && allowedTo('mention'))
		{
			Theme::loadJavaScriptFile('jquery.atwho.min.js', array('defer' => true), 'smf_atwho');
			Theme::loadJavaScriptFile('jquery.caret.min.js', array('defer' => true), 'smf_caret');
			Theme::loadJavaScriptFile('mentions.js', array('defer' => true, 'minimize' => true), 'smf_mentions');
		}

		// Did we report a post to a moderator just now?
		Utils::$context['report_sent'] = isset($_GET['reportsent']);

		Utils::$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
		Utils::$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
	}

	/**
	 * Loads info about any calendar events that are linked to this topic.
	 */
	protected function loadEvents(): void
	{
		// If we want to show event information in the topic, prepare the data.
		if (allowedTo('calendar_view') && !empty(Config::$modSettings['cal_showInTopic']) && !empty(Config::$modSettings['cal_enabled']))
		{
			require_once(Config::$sourcedir . '/Subs-Calendar.php');

			// Any calendar information for this topic?
			$request = Db::$db->query('', '
				SELECT cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, mem.real_name, cal.start_time, cal.end_time, cal.timezone, cal.location
				FROM {db_prefix}calendar AS cal
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)
				WHERE cal.id_topic = {int:current_topic}
				ORDER BY start_date',
				array(
					'current_topic' => Topic::$info->id,
				)
			);
			Utils::$context['linked_calendar_events'] = array();
			while ($row = Db::$db->fetch_assoc($request))
			{
				// Get the various time and date properties for this event
				list($start, $end, $allday, $span, $tz, $tz_abbrev) = buildEventDatetimes($row);

				// Sanity check
				if (!empty($start['error_count']) || !empty($start['warning_count']) || !empty($end['error_count']) || !empty($end['warning_count']))
					continue;

				$linked_calendar_event = array(
					'id' => $row['id_event'],
					'title' => $row['title'],
					'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == User::$me->id && allowedTo('calendar_edit_own')),
					'modify_href' => Config::$scripturl . '?action=post;msg=' . Topic::$info->id_first_msg . ';topic=' . Topic::$info->id . '.0;calendar;eventid=' . $row['id_event'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'can_export' => allowedTo('calendar_edit_any') || ($row['id_member'] == User::$me->id && allowedTo('calendar_edit_own')),
					'export_href' => Config::$scripturl . '?action=calendar;sa=ical;eventid=' . $row['id_event'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'year' => $start['year'],
					'month' => $start['month'],
					'day' => $start['day'],
					'hour' => !$allday ? $start['hour'] : null,
					'minute' => !$allday ? $start['minute'] : null,
					'second' => !$allday ? $start['second'] : null,
					'start_date' => $row['start_date'],
					'start_date_local' => $start['date_local'],
					'start_date_orig' => $start['date_orig'],
					'start_time' => !$allday ? $row['start_time'] : null,
					'start_time_local' => !$allday ? $start['time_local'] : null,
					'start_time_orig' => !$allday ? $start['time_orig'] : null,
					'start_timestamp' => $start['timestamp'],
					'start_iso_gmdate' => $start['iso_gmdate'],
					'end_year' => $end['year'],
					'end_month' => $end['month'],
					'end_day' => $end['day'],
					'end_hour' => !$allday ? $end['hour'] : null,
					'end_minute' => !$allday ? $end['minute'] : null,
					'end_second' => !$allday ? $end['second'] : null,
					'end_date' => $row['end_date'],
					'end_date_local' => $end['date_local'],
					'end_date_orig' => $end['date_orig'],
					'end_time' => !$allday ? $row['end_time'] : null,
					'end_time_local' => !$allday ? $end['time_local'] : null,
					'end_time_orig' => !$allday ? $end['time_orig'] : null,
					'end_timestamp' => $end['timestamp'],
					'end_iso_gmdate' => $end['iso_gmdate'],
					'allday' => $allday,
					'tz' => !$allday ? $tz : null,
					'tz_abbrev' => !$allday ? $tz_abbrev : null,
					'span' => $span,
					'location' => $row['location'],
					'is_last' => false
				);

				Utils::$context['linked_calendar_events'][] = $linked_calendar_event;
			}
			Db::$db->free_result($request);

			if (!empty(Utils::$context['linked_calendar_events']))
				Utils::$context['linked_calendar_events'][count(Utils::$context['linked_calendar_events']) - 1]['is_last'] = true;
		}
	}

	/**
	 * Loads the poll linked to this topic, if applicable.
	 */
	protected function loadPoll(): void
	{
		// Create the poll info if it exists.
		if (Utils::$context['is_poll'])
		{
			// Get the question and if it's locked.
			$request = Db::$db->query('', '
				SELECT
					p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
					p.guest_vote, p.id_member, COALESCE(mem.real_name, p.poster_name) AS poster_name, p.num_guest_voters, p.reset_poll
				FROM {db_prefix}polls AS p
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)
				WHERE p.id_poll = {int:id_poll}
				LIMIT 1',
				array(
					'id_poll' => Topic::$info->id_poll,
				)
			);
			$pollinfo = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);
		}

		// Create the poll info if it exists and is valid.
		if (Utils::$context['is_poll'] && empty($pollinfo))
		{
			Utils::$context['is_poll'] = false;
		}
		elseif (Utils::$context['is_poll'])
		{
			$request = Db::$db->query('', '
				SELECT COUNT(DISTINCT id_member) AS total
				FROM {db_prefix}log_polls
				WHERE id_poll = {int:id_poll}
					AND id_member != {int:not_guest}',
				array(
					'id_poll' => Topic::$info->id_poll,
					'not_guest' => 0,
				)
			);
			list ($pollinfo['total']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Total voters needs to include guest voters
			$pollinfo['total'] += $pollinfo['num_guest_voters'];

			// Get all the options, and calculate the total votes.
			$request = Db::$db->query('', '
				SELECT pc.id_choice, pc.label, pc.votes, COALESCE(lp.id_choice, -1) AS voted_this
				FROM {db_prefix}poll_choices AS pc
					LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_choice = pc.id_choice AND lp.id_poll = {int:id_poll} AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
				WHERE pc.id_poll = {int:id_poll}
				ORDER BY pc.id_choice',
				array(
					'current_member' => User::$me->id,
					'id_poll' => Topic::$info->id_poll,
					'not_guest' => 0,
				)
			);
			$pollOptions = array();
			$realtotal = 0;
			$pollinfo['has_voted'] = false;
			while ($row = Db::$db->fetch_assoc($request))
			{
				Lang::censorText($row['label']);
				$pollOptions[$row['id_choice']] = $row;
				$realtotal += $row['votes'];
				$pollinfo['has_voted'] |= $row['voted_this'] != -1;
			}
			Db::$db->free_result($request);

			// Got we multi choice?
			if ($pollinfo['max_votes'] > 1)
				$realtotal = $pollinfo['total'];

			// If this is a guest we need to do our best to work out if they have voted, and what they voted for.
			if (User::$me->is_guest && $pollinfo['guest_vote'] && allowedTo('poll_vote'))
			{
				if (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . Topic::$info->id_poll . ',') !== false)
				{
					// ;id,timestamp,[vote,vote...]; etc
					$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);

					// Find the poll we're after.
					foreach ($guestinfo as $i => $guestvoted)
					{
						$guestvoted = explode(',', $guestvoted);

						if ($guestvoted[0] == Topic::$info->id_poll)
							break;
					}
					// Has the poll been reset since guest voted?
					if ($pollinfo['reset_poll'] > $guestvoted[1])
					{
						// Remove the poll info from the cookie to allow guest to vote again
						unset($guestinfo[$i]);
						if (!empty($guestinfo))
						{
							$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
						}
						else
						{
							unset($_COOKIE['guest_poll_vote']);
						}
					}
					else
					{
						// What did they vote for?
						unset($guestvoted[0], $guestvoted[1]);
						foreach ($pollOptions as $choice => $details)
						{
							$pollOptions[$choice]['voted_this'] = in_array($choice, $guestvoted) ? 1 : -1;
							$pollinfo['has_voted'] |= $pollOptions[$choice]['voted_this'] != -1;
						}
						unset($choice, $details, $guestvoted);
					}
					unset($guestinfo, $guestvoted, $i);
				}
			}

			// Set up the basic poll information.
			Utils::$context['poll'] = array(
				'id' => Topic::$info->id_poll,
				'image' => 'normal_' . (empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll'),
				'question' => BBCodeParser::load()->parse($pollinfo['question']),
				'total_votes' => $pollinfo['total'],
				'change_vote' => !empty($pollinfo['change_vote']),
				'is_locked' => !empty($pollinfo['voting_locked']),
				'options' => array(),
				'lock' => allowedTo('poll_lock_any') || (User::$me->started && allowedTo('poll_lock_own')),
				'edit' => allowedTo('poll_edit_any') || (User::$me->started && allowedTo('poll_edit_own')),
				'remove' => allowedTo('poll_remove_any') || (User::$me->started && allowedTo('poll_remove_own')),
				'allowed_warning' => $pollinfo['max_votes'] > 1 ? sprintf(Lang::$txt['poll_options_limit'], min(count($pollOptions), $pollinfo['max_votes'])) : '',
				'is_expired' => !empty($pollinfo['expire_time']) && $pollinfo['expire_time'] < time(),
				'expire_time' => !empty($pollinfo['expire_time']) ? timeformat($pollinfo['expire_time']) : 0,
				'has_voted' => !empty($pollinfo['has_voted']),
				'starter' => array(
					'id' => $pollinfo['id_member'],
					'name' => $pollinfo['poster_name'],
					'href' => $pollinfo['id_member'] == 0 ? '' : Config::$scripturl . '?action=profile;u=' . $pollinfo['id_member'],
					'link' => $pollinfo['id_member'] == 0 ? $pollinfo['poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $pollinfo['id_member'] . '">' . $pollinfo['poster_name'] . '</a>'
				)
			);

			// Make the lock, edit and remove permissions defined above more directly accessible.
			Utils::$context['allow_lock_poll'] = Utils::$context['poll']['lock'];
			Utils::$context['allow_edit_poll'] = Utils::$context['poll']['edit'];
			Utils::$context['can_remove_poll'] = Utils::$context['poll']['remove'];

			// You're allowed to vote if:
			// 1. the poll did not expire, and
			// 2. you're either not a guest OR guest voting is enabled... and
			// 3. you're not trying to view the results, and
			// 4. the poll is not locked, and
			// 5. you have the proper permissions, and
			// 6. you haven't already voted before.
			Utils::$context['allow_vote'] = !Utils::$context['poll']['is_expired'] && (!User::$me->is_guest || ($pollinfo['guest_vote'] && allowedTo('poll_vote'))) && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && !Utils::$context['poll']['has_voted'];

			// You're allowed to view the results if:
			// 1. you're just a super-nice-guy, or
			// 2. anyone can see them (hide_results == 0), or
			// 3. you can see them after you voted (hide_results == 1), or
			// 4. you've waited long enough for the poll to expire. (whether hide_results is 1 or 2.)
			Utils::$context['allow_results_view'] = allowedTo('moderate_board') || $pollinfo['hide_results'] == 0 || ($pollinfo['hide_results'] == 1 && Utils::$context['poll']['has_voted']) || Utils::$context['poll']['is_expired'];

			// Show the results if:
			// 1. You're allowed to see them (see above), and
			// 2. $_REQUEST['viewresults'] or $_REQUEST['viewResults'] is set
			Utils::$context['poll']['show_results'] = Utils::$context['allow_results_view'] && (isset($_REQUEST['viewresults']) || isset($_REQUEST['viewResults']));

			// Show the button if:
			// 1. You can vote in the poll (see above), and
			// 2. Results are visible to everyone (hidden = 0), and
			// 3. You aren't already viewing the results
			Utils::$context['show_view_results_button'] = Utils::$context['allow_vote'] && Utils::$context['allow_results_view'] && !Utils::$context['poll']['show_results'];

			// You're allowed to change your vote if:
			// 1. the poll did not expire, and
			// 2. you're not a guest... and
			// 3. the poll is not locked, and
			// 4. you have the proper permissions, and
			// 5. you have already voted, and
			// 6. the poll creator has said you can!
			Utils::$context['allow_change_vote'] = !Utils::$context['poll']['is_expired'] && !User::$me->is_guest && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && Utils::$context['poll']['has_voted'] && Utils::$context['poll']['change_vote'];

			// You're allowed to return to voting options if:
			// 1. you are (still) allowed to vote.
			// 2. you are currently seeing the results.
			Utils::$context['allow_return_vote'] = Utils::$context['allow_vote'] && Utils::$context['poll']['show_results'];

			// Calculate the percentages and bar lengths...
			$divisor = $realtotal == 0 ? 1 : $realtotal;

			// Determine if a decimal point is needed in order for the options to add to 100%.
			$precision = $realtotal == 100 ? 0 : 1;

			// Now look through each option, and...
			foreach ($pollOptions as $i => $option)
			{
				// First calculate the percentage, and then the width of the bar...
				$bar = round(($option['votes'] * 100) / $divisor, $precision);
				$barWide = $bar == 0 ? 1 : floor(($bar * 8) / 3);

				// Now add it to the poll's contextual theme data.
				Utils::$context['poll']['options'][$i] = array(
					'id' => 'options-' . $i,
					'percent' => $bar,
					'votes' => $option['votes'],
					'voted_this' => $option['voted_this'] != -1,
					'bar_ndt' => $bar > 0 ? '<div class="bar" style="width: ' . $bar . '%;"></div>' : '',
					'bar_width' => $barWide,
					'option' => BBCodeParser::load()->parse($option['label']),
					'vote_button' => '<input type="' . ($pollinfo['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . $i . '" value="' . $i . '">'
				);
			}

			// Build the poll moderation button array.
			Utils::$context['poll_buttons'] = array();

			if (Utils::$context['allow_return_vote'])
				Utils::$context['poll_buttons']['vote'] = array('text' => 'poll_return_vote', 'image' => 'poll_options.png', 'url' => Config::$scripturl . '?topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start']);

			if (Utils::$context['show_view_results_button'])
				Utils::$context['poll_buttons']['results'] = array('text' => 'poll_results', 'image' => 'poll_results.png', 'url' => Config::$scripturl . '?topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';viewresults');

			if (Utils::$context['allow_change_vote'])
				Utils::$context['poll_buttons']['change_vote'] = array('text' => 'poll_change_vote', 'image' => 'poll_change_vote.png', 'url' => Config::$scripturl . '?action=vote;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';poll=' . Utils::$context['poll']['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);

			if (Utils::$context['allow_lock_poll'])
				Utils::$context['poll_buttons']['lock'] = array('text' => (!Utils::$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'), 'image' => 'poll_lock.png', 'url' => Config::$scripturl . '?action=lockvoting;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);

			if (Utils::$context['allow_edit_poll'])
				Utils::$context['poll_buttons']['edit'] = array('text' => 'poll_edit', 'image' => 'poll_edit.png', 'url' => Config::$scripturl . '?action=editpoll;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start']);

			if (Utils::$context['can_remove_poll'])
				Utils::$context['poll_buttons']['remove_poll'] = array('text' => 'poll_remove', 'image' => 'admin_remove_poll.png', 'custom' => 'data-confirm="' . Lang::$txt['poll_remove_warn'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=removepoll;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_poll_buttons');
		}
	}

	/**
	 * Sets up anti-spam verification stuff, if needed.
	 */
	protected function setupVerification(): void
	{
		// Do we need to show the visual verification image?
		Utils::$context['require_verification'] = !User::$me->is_mod && !User::$me->is_admin && !empty(Config::$modSettings['posts_require_captcha']) && (User::$me->posts < Config::$modSettings['posts_require_captcha'] || (User::$me->is_guest && Config::$modSettings['posts_require_captcha'] == -1));

		if (Utils::$context['require_verification'])
		{
			require_once(Config::$sourcedir . '/Subs-Editor.php');

			$verificationOptions = array(
				'id' => 'post',
			);

			Utils::$context['require_verification'] = create_control_verification($verificationOptions);
			Utils::$context['visual_verification_id'] = $verificationOptions['id'];
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
		if ($start >= Topic::$info->total_visible_posts / 2 && Utils::$context['messages_per_page'] != -1)
		{
			$DBascending = !$ascending;
			$limit = Topic::$info->total_visible_posts <= $start + $limit ? Topic::$info->total_visible_posts - $start : $limit;
			$start = Topic::$info->total_visible_posts <= $start + $limit ? 0 : Topic::$info->total_visible_posts - $start - $limit;
			$this->firstIndex = empty(Theme::$current->options['view_newest_first']) ? $start - 1 : $limit - 1;
		}
		else
			$DBascending = $ascending;

		// Get each post and poster in this topic.
		$request = Db::$db->query('', '
			SELECT id_msg, id_member, approved
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}' . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND (approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR id_member = {int:current_member}') . ')') . '
			ORDER BY id_msg ' . ($DBascending ? '' : 'DESC') . (Utils::$context['messages_per_page'] == -1 ? '' : '
			LIMIT {int:start}, {int:max}'),
			array(
				'current_member' => User::$me->id,
				'current_topic' => Topic::$info->id,
				'is_approved' => 1,
				'blank_id_member' => 0,
				'start' => $start,
				'max' => $limit,
			)
		);

		$this->messages = array();
		$all_posters = array();

		while ($row = Db::$db->fetch_assoc($request))
		{
			if (!empty($row['id_member']))
				$all_posters[$row['id_msg']] = $row['id_member'];
			$this->messages[] = $row['id_msg'];
		}

		// Sort the messages into the correct display order
		if (!$DBascending)
			sort($this->messages);

		Db::$db->free_result($request);
		$this->posters = array_unique($all_posters);

		call_integration_hook('integrate_display_message_list', array(&$this->messages, &$this->posters));
	}

	/**
	 * Initializes Msg::get() and loads attachments and likes.
	 */
	protected function initDisplayContext(): void
	{
		Utils::$context['loaded_attachments'] = array();

		// If there _are_ messages here... (probably an error otherwise :!)
		if (!empty($this->messages))
		{
			Msg::$getter = Msg::get($this->messages);

			// Fetch attachments.
			if (!empty(Config::$modSettings['attachmentEnable']) && allowedTo('view_attachments'))
			{
				require_once(Config::$sourcedir . '/Attachment.php');
				prepareAttachsByMsg($this->messages);
			}

			// And the likes
			if (!empty(Config::$modSettings['enable_likes']))
				Utils::$context['my_likes'] = User::$me->is_guest ? array() : prepareLikesContext(Topic::$info->id);

			// Go to the last message if the given time is beyond the time of the last message.
			if (isset(Utils::$context['start_from']) && Utils::$context['start_from'] >= Topic::$info->num_replies)
				Utils::$context['start_from'] = Topic::$info->num_replies;

			// Since the anchor information is needed on the top of the page we load these variables beforehand.
			Utils::$context['first_message'] = isset($this->messages[$this->firstIndex]) ? $this->messages[$this->firstIndex] : $this->messages[0];

			if (empty(Theme::$current->options['view_newest_first']))
			{
				Utils::$context['first_new_message'] = isset(Utils::$context['start_from']) && $_REQUEST['start'] == Utils::$context['start_from'];
			}
			else
			{
				Utils::$context['first_new_message'] = isset(Utils::$context['start_from']) && $_REQUEST['start'] == Topic::$info->num_replies - Utils::$context['start_from'];
			}
		}
		else
		{
			Msg::$getter = array();
			Utils::$context['first_message'] = 0;
			Utils::$context['first_new_message'] = false;
			Utils::$context['likes'] = array();
		}

		// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
		// This will be called from the template.
		Utils::$context['get_message'] = array($this, 'prepareDisplayContext');
	}

	/**
	 * Warn the user against replying to old topics.
	 */
	protected function setOldTopicWarning(): void
	{
		// When was the last time this topic was replied to?  Should we warn them about it?
		if (!empty(Config::$modSettings['oldTopicDays']) && (Topic::$info->permissions['can_reply'] || Topic::$info->permissions['can_reply_unapproved']) && empty(Topic::$info->is_sticky))
		{
			$request = Db::$db->query('', '
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_last_msg}
				LIMIT 1',
				array(
					'id_last_msg' => Topic::$info->id_last_msg,
				)
			);

			list ($lastPostTime) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Utils::$context['oldTopicError'] = $lastPostTime + Config::$modSettings['oldTopicDays'] * 86400 < time();
		}
	}

	/**
	 * Loads the editor for the quick reply.
	 */
	protected function loadEditor(): void
	{
		// Needed for the editor and message icons.
		require_once(Config::$sourcedir . '/Subs-Editor.php');

		// Now create the editor.
		$editorOptions = array(
			'id' => 'quickReply',
			'value' => '',
			'labels' => array(
				'post_button' => Lang::$txt['post'],
			),
			// add height and width for the editor
			'height' => '150px',
			'width' => '100%',
			// We do HTML preview here.
			'preview_type' => 1,
			// This is required
			'required' => true,
		);
		create_control_richedit($editorOptions);

		// Store the ID.
		Utils::$context['post_box_name'] = $editorOptions['id'];

		Utils::$context['attached'] = '';
		Utils::$context['make_poll'] = isset($_REQUEST['poll']);

		// Message icons - customized icons are off?
		Utils::$context['icons'] = getMessageIcons(Board::$info->id);

		if (!empty(Utils::$context['icons']))
			Utils::$context['icons'][count(Utils::$context['icons']) - 1]['is_last'] = true;
	}

	/**
	 * Builds the user and moderator button arrays.
	 */
	protected function buildButtons(): void
	{
		// Build the normal button array.
		Utils::$context['normal_buttons'] = array();

		if (Topic::$info->permissions['can_reply'])
		{
			Utils::$context['normal_buttons']['reply'] = array('text' => 'reply', 'url' => Config::$scripturl . '?action=post;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';last_msg=' . Topic::$info->id_last_msg, 'active' => true);
		}

		if (Topic::$info->permissions['can_add_poll'])
		{
			Utils::$context['normal_buttons']['add_poll'] = array('text' => 'add_poll', 'url' => Config::$scripturl . '?action=editpoll;add;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start']);
		}

		if (Topic::$info->permissions['can_mark_unread'])
		{
			Utils::$context['normal_buttons']['mark_unread'] = array('text' => 'mark_unread', 'url' => Config::$scripturl . '?action=markasread;sa=topic;t=' . Utils::$context['mark_unread_time'] . ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		if (Topic::$info->permissions['can_print'])
		{
			Utils::$context['normal_buttons']['print'] = array('text' => 'print', 'custom' => 'rel="nofollow"', 'url' => Config::$scripturl . '?action=printpage;topic=' . Utils::$context['current_topic'] . '.0');
		}

		if (Topic::$info->permissions['can_set_notify'])
		{
			Utils::$context['normal_buttons']['notify'] = array(
				'text' => 'notify_topic_' . Utils::$context['topic_notification_mode'],
				'sub_buttons' => array(
					array(
						'test' => 'can_unwatch',
						'text' => 'notify_topic_0',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					),
					array(
						'text' => 'notify_topic_1',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=1;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					),
					array(
						'text' => 'notify_topic_2',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=2;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					),
					array(
						'text' => 'notify_topic_3',
						'url' => Config::$scripturl . '?action=notifytopic;topic=' . Utils::$context['current_topic'] . ';mode=3;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					),
				),
			);
		}

		// Build the mod button array
		Utils::$context['mod_buttons'] = array();

		if (Topic::$info->permissions['can_move'])
		{
			Utils::$context['mod_buttons']['move'] = array('text' => 'move_topic', 'url' => Config::$scripturl . '?action=movetopic;current_board=' . Utils::$context['current_board'] . ';topic=' . Utils::$context['current_topic'] . '.0');
		}

		if (Topic::$info->permissions['can_delete'])
		{
			Utils::$context['mod_buttons']['delete'] = array('text' => 'remove_topic', 'custom' => 'data-confirm="' . Lang::$txt['are_sure_remove_topic'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=removetopic2;topic=' . Utils::$context['current_topic'] . '.0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		if (Topic::$info->permissions['can_lock'])
		{
			Utils::$context['mod_buttons']['lock'] = array('text' => empty(Utils::$context['is_locked']) ? 'set_lock' : 'set_unlock', 'url' => Config::$scripturl . '?action=lock;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';sa=' . (Utils::$context['is_locked'] ? 'unlock' : 'lock') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		if (Topic::$info->permissions['can_sticky'])
		{
			Utils::$context['mod_buttons']['sticky'] = array('text' => empty(Utils::$context['is_sticky']) ? 'set_sticky' : 'set_nonsticky', 'url' => Config::$scripturl . '?action=sticky;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . ';sa=' . (Utils::$context['is_sticky'] ? 'nonsticky' : 'sticky') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		if (Topic::$info->permissions['can_merge'])
		{
			Utils::$context['mod_buttons']['merge'] = array('text' => 'merge', 'url' => Config::$scripturl . '?action=mergetopics;board=' . Utils::$context['current_board'] . '.0;from=' . Utils::$context['current_topic']);
		}

		if (Topic::$info->permissions['calendar_post'])
		{
			Utils::$context['mod_buttons']['calendar'] = array('text' => 'calendar_link', 'url' => Config::$scripturl . '?action=post;calendar;msg=' . Topic::$info->id_first_msg . ';topic=' . Utils::$context['current_topic'] . '.0');
		}

		// Restore topic. eh?  No monkey business.
		if (Topic::$info->permissions['can_restore_topic'])
		{
			Utils::$context['mod_buttons']['restore_topic'] = array('text' => 'restore_topic', 'url' => Config::$scripturl . '?action=restoretopic;topics=' . Utils::$context['current_topic'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Allow adding new buttons easily.
		// Note: Utils::$context['normal_buttons'] and Utils::$context['mod_buttons'] are added for backward compatibility with 2.0, but are deprecated and should not be used
		call_integration_hook('integrate_display_buttons', array(&Utils::$context['normal_buttons']));
		// Note: integrate_mod_buttons is no longer necessary and deprecated, but is kept for backward compatibility with 2.0
		call_integration_hook('integrate_mod_buttons', array(&Utils::$context['mod_buttons']));

		// If any buttons have a 'test' check, run those tests now to keep things clean.
		foreach (array('normal_buttons', 'mod_buttons') as $button_strip)
		{
			foreach (Utils::$context[$button_strip] as $key => $value)
			{
				if (isset($value['test']) && empty(Utils::$context[$value['test']]))
				{
					unset(Utils::$context[$button_strip][$key]);
				}
				elseif (isset($value['sub_buttons']))
				{
					foreach ($value['sub_buttons'] as $subkey => $subvalue)
					{
						if (isset($subvalue['test']) && empty(Utils::$context[$subvalue['test']]))
							unset(Utils::$context[$button_strip][$key]['sub_buttons'][$subkey]);
					}
				}
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Display::exportStatic'))
	Display::exportStatic();

?>