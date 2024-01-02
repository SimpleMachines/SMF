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
 *
 * Original module by Mach8 - We'll never forget you.
 */

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Msg;
use SMF\PageIndex;
use SMF\Search\SearchApi;
use SMF\Theme;
use SMF\Time;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Handles splitting of topics.
 */
class TopicSplit implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'SplitTopics',
			'splitTopic' => 'splitTopic',
			'splitIndex' => 'SplitIndex',
			'splitExecute' => 'SplitExecute',
			'splitSelectTopics' => 'SplitSelectTopics',
			'SplitSelectionExecute' => 'SplitSelectionExecute',
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
	public string $subaction = 'index';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'index' => 'index',
		'split' => 'split',
		'selectTopics' => 'select',
		'splitSelection' => 'splitSelection',
	];

	/*********************
	 * Internal properties
	 *********************/

	// code...

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
	 * Splits a topic into two topics.
	 *
	 * - Delegates to the other functions (based on the URL parameter 'sa').
	 * - Loads the SplitTopics template.
	 * - Requires the split_any permission.
	 * - Accessed via ?action=splittopics.
	 */
	public function execute(): void
	{
		// And... which topic were you splitting, again?
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('numbers_one_to_nine', false);
		}

		// Are you allowed to split topics?
		User::$me->isAllowedTo('split_any');

		// Load up the "dependencies" - the template and getMsgMemberID().
		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('SplitTopics');
		}

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Screen shown before the actual split.
	 *
	 * - Default sub action for ?action=splittopics.
	 * - Accessed via ?action=splittopics;sa=index.
	 * - Uses 'ask' sub template of the SplitTopics template.
	 * - Redirects to select if the message given turns out to be
	 *   the first message of a topic.
	 * - Shows the user three ways to split the current topic.
	 */
	public function index()
	{
		// Validate "at".
		if (empty($_GET['at'])) {
			ErrorHandler::fatalLang('numbers_one_to_nine', false);
		}

		$_GET['at'] = (int) $_GET['at'];

		// Retrieve the subject and stuff of the specific topic/message.
		$request = Db::$db->query(
			'',
			'SELECT m.subject, t.num_replies, t.unapproved_posts, t.id_first_msg, t.approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			WHERE m.id_msg = {int:split_at}' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
				AND m.approved = 1') . '
				AND m.id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
				'split_at' => $_GET['at'],
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('cant_find_messages');
		}
		list($_REQUEST['subname'], $num_replies, $unapproved_posts, $id_first_msg, $approved) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// If not approved validate they can see it.
		if (Config::$modSettings['postmod_active'] && !$approved) {
			User::$me->isAllowedTo('approve_posts');
		}

		// If this topic has unapproved posts, we need to count them too...
		if (Config::$modSettings['postmod_active'] && User::$me->allowedTo('approve_posts')) {
			$num_replies += $unapproved_posts - ($approved ? 0 : 1);
		}

		// Check if there is more than one message in the topic.  (there should be.)
		if ($num_replies < 1) {
			ErrorHandler::fatalLang('topic_one_post', false);
		}

		// Check if this is the first message in the topic (if so, the first and second option won't be available)
		if ($id_first_msg == $_GET['at']) {
			return $this->select();
		}

		// Basic template information....
		Utils::$context['message'] = [
			'id' => $_GET['at'],
			'subject' => $_REQUEST['subname'],
		];
		Utils::$context['sub_template'] = 'ask';
		Utils::$context['page_title'] = Lang::$txt['split'];
	}

	/**
	 * Do the actual split.
	 *
	 * - Accessed via ?action=splittopics;sa=merge.
	 * - Uses the main SplitTopics template.
	 * - Supports three ways of splitting:
	 *   (1) only one message is split off.
	 *   (2) all messages after and including a given message are split off.
	 *   (3) select topics to split (redirects to select()).
	 * - Uses splitTopic function to do the actual splitting.
	 */
	public function split()
	{
		// Check the session to make sure they meant to do this.
		User::$me->checkSession();

		// Clean up the subject.
		if (!isset($_POST['subname']) || $_POST['subname'] == '') {
			$_POST['subname'] = Lang::$txt['new_topic'];
		}

		// Redirect to the selector if they chose selective.
		if ($_POST['step2'] == 'selective') {
			Utils::redirectexit('action=splittopics;sa=selectTopics;subname=' . $_POST['subname'] . ';topic=' . Topic::$topic_id . '.0;start2=0');
		}

		$_POST['at'] = (int) $_POST['at'];
		$messagesToBeSplit = [];

		if ($_POST['step2'] == 'afterthis') {
			// Fetch the message IDs of the topic that are at or after the message.
			$request = Db::$db->query(
				'',
				'SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg >= {int:split_at}',
				[
					'current_topic' => Topic::$topic_id,
					'split_at' => $_POST['at'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$messagesToBeSplit[] = $row['id_msg'];
			}
			Db::$db->free_result($request);
		}
		// Only the selected message has to be split. That should be easy.
		elseif ($_POST['step2'] == 'onlythis') {
			$messagesToBeSplit[] = $_POST['at'];
		}
		// There's another action?!
		else {
			ErrorHandler::fatalLang('no_access', false);
		}

		Utils::$context['old_topic'] = Topic::$topic_id;
		Utils::$context['new_topic'] = $this->splitTopic(Topic::$topic_id, $messagesToBeSplit, $_POST['subname']);
		Utils::$context['page_title'] = Lang::$txt['split'];
	}

	/**
	 * Allows the user to select the messages to be split.
	 *
	 * - Accessed via ?action=splittopics;sa=selectTopics.
	 * - Uses 'select' sub template of the SplitTopics template or (for
	 *   XMLhttp) the 'split' sub template of the Xml template.
	 * - Supports XMLhttp for adding/removing a message to the selection.
	 * - Uses a session variable to store the selected topics.
	 * - Shows two independent page indexes for both the selected and
	 *   not-selected messages (;topic=1.x;start2=y).
	 */
	public function select()
	{
		Utils::$context['page_title'] = Lang::$txt['split'] . ' - ' . Lang::$txt['select_split_posts'];

		// Haven't selected anything have we?
		$_SESSION['split_selection'][Topic::$topic_id] = empty($_SESSION['split_selection'][Topic::$topic_id]) ? [] : $_SESSION['split_selection'][Topic::$topic_id];

		// This is a special case for split topics from quick-moderation checkboxes
		if (isset($_REQUEST['subname_enc'])) {
			$_REQUEST['subname'] = urldecode($_REQUEST['subname_enc']);
		}

		Utils::$context['not_selected'] = [
			'num_messages' => 0,
			'start' => empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'],
			'messages' => [],
		];

		Utils::$context['selected'] = [
			'num_messages' => 0,
			'start' => empty($_REQUEST['start2']) ? 0 : (int) $_REQUEST['start2'],
			'messages' => [],
		];

		Utils::$context['topic'] = [
			'id' => Topic::$topic_id,
			'subject' => urlencode($_REQUEST['subname']),
		];

		// Some stuff for our favorite template.
		Utils::$context['new_subject'] = $_REQUEST['subname'];

		// Using the "select" sub template.
		Utils::$context['sub_template'] = isset($_REQUEST['xml']) ? 'split' : 'select';

		// Are we using a custom messages per page?
		Utils::$context['messages_per_page'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		// Get the message ID's from before the move.
		if (isset($_REQUEST['xml'])) {
			$original_msgs = [
				'not_selected' => [],
				'selected' => [],
			];

			$request = Db::$db->query(
				'',
				'SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][Topic::$topic_id]) ? '' : '
					AND id_msg NOT IN ({array_int:no_split_msgs})') . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}') . '
					' . (empty(Theme::$current->options['view_newest_first']) ? '' : 'ORDER BY id_msg DESC') . '
					LIMIT {int:start}, {int:messages_per_page}',
				[
					'current_topic' => Topic::$topic_id,
					'no_split_msgs' => empty($_SESSION['split_selection'][Topic::$topic_id]) ? [] : $_SESSION['split_selection'][Topic::$topic_id],
					'is_approved' => 1,
					'start' => Utils::$context['not_selected']['start'],
					'messages_per_page' => Utils::$context['messages_per_page'],
				],
			);

			// You can't split the last message off.
			if (empty(Utils::$context['not_selected']['start']) && Db::$db->num_rows($request) <= 1 && $_REQUEST['move'] == 'down') {
				$_REQUEST['move'] = '';
			}

			while ($row = Db::$db->fetch_assoc($request)) {
				$original_msgs['not_selected'][] = $row['id_msg'];
			}
			Db::$db->free_result($request);

			if (!empty($_SESSION['split_selection'][Topic::$topic_id])) {
				$request = Db::$db->query(
					'',
					'SELECT id_msg
					FROM {db_prefix}messages
					WHERE id_topic = {int:current_topic}
						AND id_msg IN ({array_int:split_msgs})' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
						AND approved = {int:is_approved}') . '
					' . (empty(Theme::$current->options['view_newest_first']) ? '' : 'ORDER BY id_msg DESC') . '
					LIMIT {int:start}, {int:messages_per_page}',
					[
						'current_topic' => Topic::$topic_id,
						'split_msgs' => $_SESSION['split_selection'][Topic::$topic_id],
						'is_approved' => 1,
						'start' => Utils::$context['selected']['start'],
						'messages_per_page' => Utils::$context['messages_per_page'],
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$original_msgs['selected'][] = $row['id_msg'];
				}
				Db::$db->free_result($request);
			}
		}

		// (De)select a message..
		if (!empty($_REQUEST['move'])) {
			$_REQUEST['msg'] = (int) $_REQUEST['msg'];

			if ($_REQUEST['move'] == 'reset') {
				$_SESSION['split_selection'][Topic::$topic_id] = [];
			} elseif ($_REQUEST['move'] == 'up') {
				$_SESSION['split_selection'][Topic::$topic_id] = array_diff($_SESSION['split_selection'][Topic::$topic_id], [$_REQUEST['msg']]);
			} else {
				$_SESSION['split_selection'][Topic::$topic_id][] = $_REQUEST['msg'];
			}
		}

		// Make sure the selection is still accurate.
		if (!empty($_SESSION['split_selection'][Topic::$topic_id])) {
			$split_msgs = $_SESSION['split_selection'][Topic::$topic_id];
			$_SESSION['split_selection'][Topic::$topic_id] = [];

			$request = Db::$db->query(
				'',
				'SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg IN ({array_int:split_msgs})' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}'),
				[
					'current_topic' => Topic::$topic_id,
					'split_msgs' => $split_msgs,
					'is_approved' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$_SESSION['split_selection'][Topic::$topic_id][] = $row['id_msg'];
			}
			Db::$db->free_result($request);
		}

		// Get the number of messages (not) selected to be split.
		$request = Db::$db->query(
			'',
			'SELECT ' . (empty($_SESSION['split_selection'][Topic::$topic_id]) ? '0' : 'm.id_msg IN ({array_int:split_msgs})') . ' AS is_selected, COUNT(*) AS num_messages
			FROM {db_prefix}messages AS m
			WHERE m.id_topic = {int:current_topic}' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . (empty($_SESSION['split_selection'][Topic::$topic_id]) ? '' : '
			GROUP BY is_selected'),
			[
				'current_topic' => Topic::$topic_id,
				'split_msgs' => !empty($_SESSION['split_selection'][Topic::$topic_id]) ? $_SESSION['split_selection'][Topic::$topic_id] : [],
				'is_approved' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context[empty($row['is_selected']) || $row['is_selected'] == 'f' ? 'not_selected' : 'selected']['num_messages'] = $row['num_messages'];
		}
		Db::$db->free_result($request);

		// Fix an oversized starting page (to make sure both pageindexes are properly set).
		if (Utils::$context['selected']['start'] >= Utils::$context['selected']['num_messages']) {
			Utils::$context['selected']['start'] = Utils::$context['selected']['num_messages'] <= Utils::$context['messages_per_page'] ? 0 : (Utils::$context['selected']['num_messages'] - ((Utils::$context['selected']['num_messages'] % Utils::$context['messages_per_page']) == 0 ? Utils::$context['messages_per_page'] : (Utils::$context['selected']['num_messages'] % Utils::$context['messages_per_page'])));
		}

		// Build a page list of the not-selected topics...
		Utils::$context['not_selected']['page_index'] = new PageIndex(Config::$scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), ['%' => '%%']) . ';topic=' . Topic::$topic_id . '.%1$d;start2=' . Utils::$context['selected']['start'], Utils::$context['not_selected']['start'], Utils::$context['not_selected']['num_messages'], Utils::$context['messages_per_page'], true);

		// ...and one of the selected topics.
		Utils::$context['selected']['page_index'] = new PageIndex(Config::$scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), ['%' => '%%']) . ';topic=' . Topic::$topic_id . '.' . Utils::$context['not_selected']['start'] . ';start2=%1$d', Utils::$context['selected']['start'], Utils::$context['selected']['num_messages'], Utils::$context['messages_per_page'], true);

		// Get the messages and stick them into an array.
		$request = Db::$db->query(
			'',
			'SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS real_name, m.poster_time, m.body, m.id_msg, m.smileys_enabled
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][Topic::$topic_id]) ? '' : '
				AND id_msg NOT IN ({array_int:no_split_msgs})') . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . '
				' . (empty(Theme::$current->options['view_newest_first']) ? '' : 'ORDER BY m.id_msg DESC') . '
				LIMIT {int:start}, {int:messages_per_page}',
			[
				'current_topic' => Topic::$topic_id,
				'no_split_msgs' => !empty($_SESSION['split_selection'][Topic::$topic_id]) ? $_SESSION['split_selection'][Topic::$topic_id] : [],
				'is_approved' => 1,
				'start' => Utils::$context['not_selected']['start'],
				'messages_per_page' => Utils::$context['messages_per_page'],
			],
		);

		for ($counter = 0; $row = Db::$db->fetch_assoc($request); $counter++) {
			Lang::censorText($row['subject']);
			Lang::censorText($row['body']);

			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			Utils::$context['not_selected']['messages'][$row['id_msg']] = [
				'id' => $row['id_msg'],
				'subject' => $row['subject'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'body' => $row['body'],
				'poster' => $row['real_name'],
			];
		}
		Db::$db->free_result($request);

		// Now get the selected messages.
		if (!empty($_SESSION['split_selection'][Topic::$topic_id])) {
			// Get the messages and stick them into an array.
			$request = Db::$db->query(
				'',
				'SELECT m.subject, COALESCE(mem.real_name, m.poster_name) AS real_name,  m.poster_time, m.body, m.id_msg, m.smileys_enabled
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.id_topic = {int:current_topic}
					AND m.id_msg IN ({array_int:split_msgs})' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}') . '
				' . (empty(Theme::$current->options['view_newest_first']) ? '' : 'ORDER BY m.id_msg DESC') . '
				LIMIT {int:start}, {int:messages_per_page}',
				[
					'current_topic' => Topic::$topic_id,
					'split_msgs' => $_SESSION['split_selection'][Topic::$topic_id],
					'is_approved' => 1,
					'start' => Utils::$context['selected']['start'],
					'messages_per_page' => Utils::$context['messages_per_page'],
				],
			);

			for ($counter = 0; $row = Db::$db->fetch_assoc($request); $counter++) {
				Lang::censorText($row['subject']);
				Lang::censorText($row['body']);

				$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

				Utils::$context['selected']['messages'][$row['id_msg']] = [
					'id' => $row['id_msg'],
					'subject' => $row['subject'],
					'time' => Time::create('@' . $row['poster_time'])->format(),
					'timestamp' => $row['poster_time'],
					'body' => $row['body'],
					'poster' => $row['real_name'],
				];
			}
			Db::$db->free_result($request);
		}

		// The XMLhttp method only needs the stuff that changed, so let's compare.
		if (isset($_REQUEST['xml'])) {
			$changes = [
				'remove' => [
					'not_selected' => array_diff($original_msgs['not_selected'], array_keys(Utils::$context['not_selected']['messages'])),
					'selected' => array_diff($original_msgs['selected'], array_keys(Utils::$context['selected']['messages'])),
				],
				'insert' => [
					'not_selected' => array_diff(array_keys(Utils::$context['not_selected']['messages']), $original_msgs['not_selected']),
					'selected' => array_diff(array_keys(Utils::$context['selected']['messages']), $original_msgs['selected']),
				],
			];

			Utils::$context['changes'] = [];

			foreach ($changes as $change_type => $change_array) {
				foreach ($change_array as $section => $msg_array) {
					if (empty($msg_array)) {
						continue;
					}

					foreach ($msg_array as $id_msg) {
						Utils::$context['changes'][$change_type . $id_msg] = [
							'id' => $id_msg,
							'type' => $change_type,
							'section' => $section,
						];

						if ($change_type == 'insert') {
							Utils::$context['changes']['insert' . $id_msg]['insert_value'] = Utils::$context[$section]['messages'][$id_msg];
						}
					}
				}
			}
		}
	}

	/**
	 * Do the actual split of a selection of topics.
	 *
	 * - Accessed via ?action=splittopics;sa=splitSelection.
	 * - Uses the main SplitTopics template.
	 * - Uses splitTopic function to do the actual splitting.
	 */
	public function splitSelection()
	{
		// Make sure the session id was passed with post.
		User::$me->checkSession();

		// Default the subject in case it's blank.
		if (!isset($_POST['subname']) || $_POST['subname'] == '') {
			$_POST['subname'] = Lang::$txt['new_topic'];
		}

		// You must've selected some messages!  Can't split out none!
		if (empty($_SESSION['split_selection'][Topic::$topic_id])) {
			ErrorHandler::fatalLang('no_posts_selected', false);
		}

		Utils::$context['old_topic'] = Topic::$topic_id;
		Utils::$context['new_topic'] = $this->splitTopic(Topic::$topic_id, $_SESSION['split_selection'][Topic::$topic_id], $_POST['subname']);
		Utils::$context['page_title'] = Lang::$txt['split'];
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
	 * General function to split off a topic.
	 *
	 * - Creates a new topic and moves the messages with the IDs in
	 *   array messagesToBeSplit to the new topic.
	 * - The subject of the newly created topic is set to 'newSubject'.
	 * - Marks the newly created message as read for the user splitting it.
	 * - Updates the statistics to reflect a newly created topic.
	 * - Logs the action in the moderation log.
	 * - A notification is sent to all users monitoring this topic.
	 *
	 * @param int $split1_ID_TOPIC The ID of the topic we're splitting
	 * @param array $splitMessages The IDs of the messages being split
	 * @param string $new_subject The subject of the new topic
	 * @return int The ID of the new split topic.
	 */
	public static function splitTopic($split1_ID_TOPIC, $splitMessages, $new_subject)
	{
		// Nothing to split?
		if (empty($splitMessages)) {
			ErrorHandler::fatalLang('no_posts_selected', false);
		}

		// Get some board info.
		$request = Db::$db->query(
			'',
			'SELECT id_board, approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			[
				'id_topic' => $split1_ID_TOPIC,
			],
		);
		list($id_board, $split1_approved) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Find the new first and last not in the list. (old topic)
		$request = Db::$db->query(
			'',
			'SELECT
				MIN(m.id_msg) AS myid_first_msg, MAX(m.id_msg) AS myid_last_msg, COUNT(*) AS message_count, m.approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:id_topic})
			WHERE m.id_msg NOT IN ({array_int:no_msg_list})
				AND m.id_topic = {int:id_topic}
			GROUP BY m.approved
			ORDER BY m.approved DESC
			LIMIT 2',
			[
				'id_topic' => $split1_ID_TOPIC,
				'no_msg_list' => $splitMessages,
			],
		);

		// You can't select ALL the messages!
		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('selected_all_posts', false);
		}

		$split1_first_msg = null;
		$split1_last_msg = null;

		while ($row = Db::$db->fetch_assoc($request)) {
			// Get the right first and last message dependent on approved state...
			if (empty($split1_first_msg) || $row['myid_first_msg'] < $split1_first_msg) {
				$split1_first_msg = $row['myid_first_msg'];
			}

			if (empty($split1_last_msg) || $row['approved']) {
				$split1_last_msg = $row['myid_last_msg'];
			}

			// Get the counts correct...
			if ($row['approved']) {
				$split1_replies = $row['message_count'] - 1;
				$split1_unapprovedposts = 0;
			} else {
				if (!isset($split1_replies)) {
					$split1_replies = 0;
				}
				// If the topic isn't approved then num replies must go up by one... as first post wouldn't be counted.
				elseif (!$split1_approved) {
					$split1_replies++;
				}

				$split1_unapprovedposts = $row['message_count'];
			}
		}
		Db::$db->free_result($request);

		$split1_firstMem = Board::getMsgMemberID($split1_first_msg);
		$split1_lastMem = Board::getMsgMemberID($split1_last_msg);

		// Find the first and last in the list. (new topic)
		$request = Db::$db->query(
			'',
			'SELECT MIN(id_msg) AS myid_first_msg, MAX(id_msg) AS myid_last_msg, COUNT(*) AS message_count, approved
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:msg_list})
				AND id_topic = {int:id_topic}
			GROUP BY id_topic, approved
			ORDER BY approved DESC
			LIMIT 2',
			[
				'msg_list' => $splitMessages,
				'id_topic' => $split1_ID_TOPIC,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// As before get the right first and last message dependent on approved state...
			if (empty($split2_first_msg) || $row['myid_first_msg'] < $split2_first_msg) {
				$split2_first_msg = $row['myid_first_msg'];
			}

			if (empty($split2_last_msg) || $row['approved']) {
				$split2_last_msg = $row['myid_last_msg'];
			}

			// Then do the counts again...
			if ($row['approved']) {
				$split2_approved = true;
				$split2_replies = $row['message_count'] - 1;
				$split2_unapprovedposts = 0;
			} else {
				// Should this one be approved??
				if ($split2_first_msg == $row['myid_first_msg']) {
					$split2_approved = false;
				}

				if (!isset($split2_replies)) {
					$split2_replies = 0;
				}
				// As before, fix number of replies.
				elseif (!$split2_approved) {
					$split2_replies++;
				}

				$split2_unapprovedposts = $row['message_count'];
			}
		}
		Db::$db->free_result($request);

		$split2_firstMem = Board::getMsgMemberID($split2_first_msg);
		$split2_lastMem = Board::getMsgMemberID($split2_last_msg);

		// No database changes yet, so let's double check to see if everything makes at least a little sense.
		if ($split1_first_msg <= 0 || $split1_last_msg <= 0 || $split2_first_msg <= 0 || $split2_last_msg <= 0 || $split1_replies < 0 || $split2_replies < 0 || $split1_unapprovedposts < 0 || $split2_unapprovedposts < 0 || !isset($split1_approved) || !isset($split2_approved)) {
			ErrorHandler::fatalLang('cant_find_messages');
		}

		// You cannot split off the first message of a topic.
		if ($split1_first_msg > $split2_first_msg) {
			ErrorHandler::fatalLang('split_first_post', false);
		}

		// We're off to insert the new topic!  Use 0 for now to avoid UNIQUE errors.
		$split2_ID_TOPIC = Db::$db->insert(
			'',
			'{db_prefix}topics',
			[
				'id_board' => 'int',
				'id_member_started' => 'int',
				'id_member_updated' => 'int',
				'id_first_msg' => 'int',
				'id_last_msg' => 'int',
				'num_replies' => 'int',
				'unapproved_posts' => 'int',
				'approved' => 'int',
				'is_sticky' => 'int',
			],
			[
				(int) $id_board,
				$split2_firstMem,
				$split2_lastMem,
				0,
				0,
				$split2_replies,
				$split2_unapprovedposts,
				(int) $split2_approved,
				0,
			],
			['id_topic'],
			1,
		);

		if ($split2_ID_TOPIC <= 0) {
			ErrorHandler::fatalLang('cant_insert_topic');
		}

		// Move the messages over to the other topic.
		$new_subject = strtr(Utils::htmlTrim(Utils::htmlspecialchars($new_subject)), ["\r" => '', "\n" => '', "\t" => '']);

		// Check the subject length.
		if (Utils::entityStrlen($new_subject) > 100) {
			$new_subject = Utils::entitySubstr($new_subject, 0, 100);
		}

		// Valid subject?
		if ($new_subject != '') {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET
					id_topic = {int:id_topic},
					subject = CASE WHEN id_msg = {int:split_first_msg} THEN {string:new_subject} ELSE {string:new_subject_replies} END
				WHERE id_msg IN ({array_int:split_msgs})',
				[
					'split_msgs' => $splitMessages,
					'id_topic' => $split2_ID_TOPIC,
					'new_subject' => $new_subject,
					'split_first_msg' => $split2_first_msg,
					'new_subject_replies' => Lang::$txt['response_prefix'] . $new_subject,
				],
			);

			// Cache the new topics subject... we can do it now as all the subjects are the same!
			Logging::updateStats('subject', $split2_ID_TOPIC, $new_subject);
		}

		// Any associated reported posts better follow...
		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_reported
			SET id_topic = {int:id_topic}
			WHERE id_msg IN ({array_int:split_msgs})',
			[
				'split_msgs' => $splitMessages,
				'id_topic' => $split2_ID_TOPIC,
			],
		);

		// Mess with the old topic's first, last, and number of messages.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET
				num_replies = {int:num_replies},
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				id_member_started = {int:id_member_started},
				id_member_updated = {int:id_member_updated},
				unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:id_topic}',
			[
				'num_replies' => $split1_replies,
				'id_first_msg' => $split1_first_msg,
				'id_last_msg' => $split1_last_msg,
				'id_member_started' => $split1_firstMem,
				'id_member_updated' => $split1_lastMem,
				'unapproved_posts' => $split1_unapprovedposts,
				'id_topic' => $split1_ID_TOPIC,
			],
		);

		// Now, put the first/last message back to what they should be.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg}
			WHERE id_topic = {int:id_topic}',
			[
				'id_first_msg' => $split2_first_msg,
				'id_last_msg' => $split2_last_msg,
				'id_topic' => $split2_ID_TOPIC,
			],
		);

		// If the new topic isn't approved ensure the first message flags this just in case.
		if (!$split2_approved) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET approved = {int:approved}
				WHERE id_msg = {int:id_msg}
					AND id_topic = {int:id_topic}',
				[
					'approved' => 0,
					'id_msg' => $split2_first_msg,
					'id_topic' => $split2_ID_TOPIC,
				],
			);
		}

		// The board has more topics now (Or more unapproved ones!).
		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET ' . ($split2_approved ? '
				num_topics = num_topics + 1' : '
				unapproved_topics = unapproved_topics + 1') . '
			WHERE id_board = {int:id_board}',
			[
				'id_board' => $id_board,
			],
		);

		// Copy log topic entries.
		// @todo This should really be chunked.
		$request = Db::$db->query(
			'',
			'SELECT id_member, id_msg, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic = {int:id_topic}',
			[
				'id_topic' => (int) $split1_ID_TOPIC,
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			$replaceEntries = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$replaceEntries[] = [$row['id_member'], $split2_ID_TOPIC, $row['id_msg'], $row['unwatched']];
			}

			Db::$db->insert(
				'ignore',
				'{db_prefix}log_topics',
				['id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
				$replaceEntries,
				['id_member', 'id_topic'],
			);

			unset($replaceEntries);
		}
		Db::$db->free_result($request);

		// Housekeeping.
		Logging::updateStats('topic');
		Msg::updateLastMessages($id_board);

		Logging::logAction('split', ['topic' => $split1_ID_TOPIC, 'new_topic' => $split2_ID_TOPIC, 'board' => $id_board]);

		// Notify people that this topic has been split?
		Mail::sendNotifications($split1_ID_TOPIC, 'split');

		// If there's a search index that needs updating, update it...
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'topicSplit'])) {
			$searchAPI->topicSplit($split2_ID_TOPIC, $splitMessages);
		}

		// Maybe we want to let an external CMS know about this split
		$split1 = [
			'num_replies' => $split1_replies,
			'id_first_msg' => $split1_first_msg,
			'id_last_msg' => $split1_last_msg,
			'id_member_started' => $split1_firstMem,
			'id_member_updated' => $split1_lastMem,
			'unapproved_posts' => $split1_unapprovedposts,
			'id_topic' => $split1_ID_TOPIC,
		];

		$split2 = [
			'num_replies' => $split2_replies,
			'id_first_msg' => $split2_first_msg,
			'id_last_msg' => $split2_last_msg,
			'id_member_started' => $split2_firstMem,
			'id_member_updated' => $split2_lastMem,
			'unapproved_posts' => $split2_unapprovedposts,
			'id_topic' => $split2_ID_TOPIC,
		];

		IntegrationHook::call('integrate_split_topic', [$split1, $split2, $new_subject, $id_board]);

		// Return the ID of the newly created topic.
		return $split2_ID_TOPIC;
	}

	/**
	 * Backward compatibility wrapper for the index sub-action.
	 */
	public static function splitIndex(): void
	{
		self::load();
		self::$obj->subaction = 'index';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the split sub-action.
	 */
	public static function splitExecute(): void
	{
		self::load();
		self::$obj->subaction = 'split';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the selectTopics sub-action.
	 */
	public static function splitSelectTopics(): void
	{
		self::load();
		self::$obj->subaction = 'selectTopics';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the splitSelection sub-action.
	 */
	public static function SplitSelectionExecute(): void
	{
		self::load();
		self::$obj->subaction = 'splitSelection';
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
		// The 'split' sub-action used to be called 'execute'.
		if (!empty($_GET['sa']) && $_GET['sa'] === 'execute') {
			$_GET['sa'] = 'split';
			$_REQUEST['sa'] = 'split';
		}

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TopicSplit::exportStatic')) {
	TopicSplit::exportStatic();
}

?>