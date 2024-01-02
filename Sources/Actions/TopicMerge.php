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
use SMF\Board;
use SMF\Cache\CacheApi;
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
 * Handles merging of topics.
 */
class TopicMerge implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'MergeTopics',
			'mergeIndex' => 'MergeIndex',
			'mergeExecute' => 'MergeExecute',
			'mergeDone' => 'MergeDone',
		],
	];

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

	/**
	 * @var array
	 *
	 * IDs of the topics to merge.
	 */
	public array $topics = [];

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
		'done' => 'done',
		'merge' => 'merge',
		'options' => 'options',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 *
	 */
	protected array $topic_data = [];

	/**
	 * @var int
	 *
	 *
	 */
	protected int $num_views = 0;

	/**
	 * @var int
	 *
	 *
	 */
	protected int $is_sticky = 0;

	/**
	 * @var array
	 *
	 *
	 */
	protected array $boardTotals = [];

	/**
	 * @var array
	 *
	 *
	 */
	protected array $boards = [];

	/**
	 * @var array
	 *
	 *
	 */
	protected array $polls = [];

	/**
	 * @var int
	 *
	 *
	 */
	protected int $firstTopic = 0;

	/**
	 * @var int
	 *
	 *
	 */
	protected int $approved = 1;

	/**
	 * @var int
	 *
	 *
	 */
	protected int $lowestTopicId = 0;

	/**
	 * @var int
	 *
	 *
	 */
	protected int $lowestTopicBoard = 0;

	/**
	 * @var array
	 *
	 *
	 */
	protected array $can_approve_boards = [];

	/**
	 * @var array
	 *
	 *
	 */
	protected array $merge_boards = [];

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
	 * Merges two or more topics into one topic.
	 *
	 * - Delegates to the other functions (based on the URL parameter sa).
	 * - Loads the SplitTopics template.
	 * - Requires the merge_any permission.
	 * - Accessed via ?action=mergetopics.
	 */
	public function execute(): void
	{
		// Load the template....
		Theme::loadTemplate('MoveTopic');

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Screen shown before the actual merge.
	 *
	 * - Allows user to choose a topic to merge the current topic with.
	 * - Accessed via ?action=mergetopics;sa=index
	 * - Default sub action for ?action=mergetopics.
	 * - Uses 'merge' sub template of the MoveTopic template.
	 * - Allows setting a different target board.
	 */
	public function index()
	{
		if (!isset($_GET['from'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$_GET['from'] = (int) $_GET['from'];

		$_REQUEST['targetboard'] = isset($_REQUEST['targetboard']) ? (int) $_REQUEST['targetboard'] : Board::$info->id;
		Utils::$context['target_board'] = $_REQUEST['targetboard'];

		// Prepare a handy query bit for approval...
		if (Config::$modSettings['postmod_active']) {
			$can_approve_boards = User::$me->boardsAllowedTo('approve_posts');
			$onlyApproved = $can_approve_boards !== [0] && !in_array($_REQUEST['targetboard'], $can_approve_boards);
		} else {
			$onlyApproved = false;
		}

		// How many topics are on this board?  (used for paging.)
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}topics AS t
			WHERE t.id_board = {int:id_board}' . ($onlyApproved ? '
				AND t.approved = {int:is_approved}' : ''),
			[
				'id_board' => $_REQUEST['targetboard'],
				'is_approved' => 1,
			],
		);
		list($topiccount) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Make the page list.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=mergetopics;from=' . $_GET['from'] . ';targetboard=' . $_REQUEST['targetboard'] . ';board=' . Board::$info->id . '.%1$d', $_REQUEST['start'], $topiccount, Config::$modSettings['defaultMaxTopics'], true);

		// Get the topic's subject.
		$request = Db::$db->query(
			'',
			'SELECT m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:id_topic}
				AND t.id_board = {int:current_board}' . ($onlyApproved ? '
				AND t.approved = {int:is_approved}' : '') . '
			LIMIT 1',
			[
				'current_board' => Board::$info->id,
				'id_topic' => $_GET['from'],
				'is_approved' => 1,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_board');
		}
		list($subject) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Tell the template a few things..
		Utils::$context['origin_topic'] = $_GET['from'];
		Utils::$context['origin_subject'] = $subject;
		Utils::$context['origin_js_subject'] = addcslashes(addslashes($subject), '/');
		Utils::$context['page_title'] = Lang::$txt['merge'];

		// Check which boards you have merge permissions on.
		$this->merge_boards = User::$me->boardsAllowedTo('merge_any');

		if (empty($this->merge_boards)) {
			ErrorHandler::fatalLang('cannot_merge_any', 'user');
		}

		// No sense in loading this if you can only merge on this board
		if (count($this->merge_boards) > 1 || in_array(0, $this->merge_boards)) {
			// Set up a couple of options for our board list
			$options = [
				'not_redirection' => true,
				'selected_board' => Utils::$context['target_board'],
			];

			// Only include these boards in the list (0 means you're an admin')
			if (!in_array(0, $this->merge_boards)) {
				$options['included_boards'] = $this->merge_boards;
			}

			Utils::$context['merge_categories'] = MessageIndex::getBoardList($options);
		}

		// Get some topics to merge it with.
		Utils::$context['topics'] = [];

		$request = Db::$db->query(
			'',
			'SELECT t.id_topic, m.subject, m.id_member, COALESCE(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE t.id_board = {int:id_board}
				AND t.id_topic != {int:id_topic}
				AND t.id_redirect_topic = {int:not_redirect}' . ($onlyApproved ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			[
				'id_board' => $_REQUEST['targetboard'],
				'id_topic' => $_GET['from'],
				'sort' => 't.is_sticky DESC, t.id_last_msg DESC',
				'offset' => $_REQUEST['start'],
				'limit' => Config::$modSettings['defaultMaxTopics'],
				'is_approved' => 1,
				'not_redirect' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Lang::censorText($row['subject']);

			Utils::$context['topics'][] = [
				'id' => $row['id_topic'],
				'poster' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'href' => empty($row['id_member']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank" rel="noopener">' . $row['poster_name'] . '</a>',
				],
				'subject' => $row['subject'],
				'js_subject' => addcslashes(addslashes($row['subject']), '/'),
			];
		}
		Db::$db->free_result($request);

		if (empty(Utils::$context['topics']) && count($this->merge_boards) <= 1 && !in_array(0, $this->merge_boards)) {
			ErrorHandler::fatalLang('merge_need_more_topics');
		}

		Utils::$context['sub_template'] = 'merge';
	}

	/**
	 * The merge options screen.
	 *
	 * - Shows topics to be merged and allows to set some merge options.
	 * - Accessed via ?action=mergetopics;sa=options
	 * - Can also be called internally by SMF\MessageIndex::QuickModeration().
	 * - Uses 'merge_extra_options' sub template of the MoveTopic template.
	 */
	public function options(): void
	{
		$this->initOptionsAndMerge();

		if (count($this->polls) > 1) {
			$request = Db::$db->query(
				'',
				'SELECT t.id_topic, t.id_poll, m.subject, p.question
				FROM {db_prefix}polls AS p
					INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE p.id_poll IN ({array_int:polls})
				LIMIT {int:limit}',
				[
					'polls' => $this->polls,
					'limit' => count($this->polls),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['polls'][] = [
					'id' => $row['id_poll'],
					'topic' => [
						'id' => $row['id_topic'],
						'subject' => $row['subject'],
					],
					'question' => $row['question'],
					'selected' => $row['id_topic'] == $this->firstTopic,
				];
			}
			Db::$db->free_result($request);
		}

		if (count($this->boards) > 1) {
			$request = Db::$db->query(
				'',
				'SELECT id_board, name
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:boards})
				ORDER BY name
				LIMIT {int:limit}',
				[
					'boards' => $this->boards,
					'limit' => count($this->boards),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['boards'][] = [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'selected' => $row['id_board'] == $this->topic_data[$this->firstTopic]['board'],
				];
			}
			Db::$db->free_result($request);
		}

		Utils::$context['topics'] = $this->topic_data;

		foreach ($this->topic_data as $id => $topic) {
			Utils::$context['topics'][$id]['selected'] = $topic['id'] == $this->firstTopic;
		}

		Utils::$context['page_title'] = Lang::$txt['merge'];
		Utils::$context['sub_template'] = 'merge_extra_options';
	}

	/**
	 * Performs the merge
	 *
	 * - Accessed via ?action=mergetopics;sa=merge.
	 * - Updates the statistics to reflect the merge.
	 * - Logs the action in the moderation log.
	 * - Sends a notification to all users monitoring this topic.
	 * - Redirects to ?action=mergetopics;sa=done.
	 */
	public function merge(): void
	{
		$this->initOptionsAndMerge();

		// Determine target board.
		$target_board = count($this->boards) > 1 ? (int) $_REQUEST['board'] : $this->boards[0];

		if (!in_array($target_board, $this->boards)) {
			ErrorHandler::fatalLang('no_board');
		}

		// Determine which poll will survive and which polls won't.
		$target_poll = count($this->polls) > 1 ? (int) $_POST['poll'] : (count($this->polls) == 1 ? $this->polls[0] : 0);

		if ($target_poll > 0 && !in_array($target_poll, $this->polls)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$deleted_polls = empty($target_poll) ? $this->polls : array_diff($this->polls, [$target_poll]);

		// Determine the subject of the newly merged topic - was a custom subject specified?
		if (empty($_POST['subject']) && isset($_POST['custom_subject']) && $_POST['custom_subject'] != '') {
			$target_subject = strtr(Utils::htmlTrim(Utils::htmlspecialchars($_POST['custom_subject'])), ["\r" => '', "\n" => '', "\t" => '']);

			// Keep checking the length.
			if (Utils::entityStrlen($target_subject) > 100) {
				$target_subject = Utils::entitySubstr($target_subject, 0, 100);
			}

			// Nothing left - odd but pick the first topics subject.
			if ($target_subject == '') {
				$target_subject = $this->topic_data[$this->firstTopic]['subject'];
			}
		}
		// A subject was selected from the list.
		elseif (!empty($this->topic_data[(int) $_POST['subject']]['subject'])) {
			$target_subject = $this->topic_data[(int) $_POST['subject']]['subject'];
		}
		// Nothing worked? Just take the subject of the first message.
		else {
			$target_subject = $this->topic_data[$this->firstTopic]['subject'];
		}

		// Get the first and last message and the number of messages....
		$topic_approved = 1;
		$first_msg = 0;

		$request = Db::$db->query(
			'',
			'SELECT approved, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg, COUNT(*) AS message_count
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY approved
			ORDER BY approved DESC',
			[
				'topics' => $this->topics,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// If this is approved, or is fully unapproved.
			if ($row['approved'] || !empty($first_msg)) {
				$first_msg = $row['first_msg'];
				$last_msg = $row['last_msg'];

				if ($row['approved']) {
					$num_replies = $row['message_count'] - 1;
					$num_unapproved = 0;
				} else {
					$topic_approved = 0;
					$num_replies = 0;
					$num_unapproved = $row['message_count'];
				}
			} else {
				// If this has a lower first_msg then the first post is not approved and hence the number of replies was wrong!
				if ($first_msg > $row['first_msg']) {
					$first_msg = $row['first_msg'];
					$num_replies++;
					$topic_approved = 0;
				}

				$num_unapproved = $row['message_count'];
			}
		}
		Db::$db->free_result($request);

		// Ensure we have a board stat for the target board.
		if (!isset($this->boardTotals[$target_board])) {
			$this->boardTotals[$target_board] = [
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
			];
		}

		// Fix the topic count stuff depending on what the new one counts as.
		$this->boardTotals[$target_board][(!$topic_approved) ? 'unapproved_topics' : 'topics']--;

		$this->boardTotals[$target_board]['unapproved_posts'] -= $num_unapproved;

		$this->boardTotals[$target_board]['posts'] -= $topic_approved ? $num_replies + 1 : $num_replies;

		// Get the member ID of the first and last message.
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}messages
			WHERE id_msg IN ({int:first_msg}, {int:last_msg})
			ORDER BY id_msg
			LIMIT 2',
			[
				'first_msg' => $first_msg,
				'last_msg' => $last_msg,
			],
		);
		list($member_started) = Db::$db->fetch_row($request);
		list($member_updated) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// First and last message are the same, so only row was returned.
		if ($member_updated === null) {
			$member_updated = $member_started;
		}

		// Obtain all the message ids we are going to affect.
		$request = Db::$db->query(
			'',
			'SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})',
			[
				'topic_list' => $this->topics,
			],
		);
		$affected_msgs = Db::$db->fetch_all($request);
		Db::$db->free_result($request);

		// Assign the first topic ID to be the merged topic.
		$id_topic = min($this->topics);

		$deleted_topics = array_diff($this->topics, [$id_topic]);
		$updated_topics = [];

		// Create stub topics out of the remaining topics.
		// We don't want the search index data though (For non-redirect merges).
		if (!isset($_POST['postRedirect'])) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_search_subjects
				WHERE id_topic IN ({array_int:deleted_topics})',
				[
					'deleted_topics' => $deleted_topics,
				],
			);
		}

		$posterOptions = [
			'id' => User::$me->id,
			'update_post_count' => false,
		];

		// We only need to do this if we're posting redirection topics...
		if (isset($_POST['postRedirect'])) {
			// Replace tokens with links in the reason.
			$reason_replacements = [
				Lang::$txt['movetopic_auto_topic'] => '[iurl="' . Config::$scripturl . '?topic=' . $id_topic . '.0"]' . $target_subject . '[/iurl]',
			];

			// Should be in the boardwide language.
			if (User::$me->language != Lang::$default) {
				Lang::load('index', Lang::$default);

				// Make sure we catch both languages in the reason.
				$reason_replacements += [
					Lang::$txt['movetopic_auto_topic'] => '[iurl="' . Config::$scripturl . '?topic=' . $id_topic . '.0"]' . $target_subject . '[/iurl]',
				];
			}

			$_POST['reason'] = Utils::htmlspecialchars($_POST['reason'], ENT_QUOTES);
			Msg::preparsecode($_POST['reason']);

			// Add a URL onto the message.
			$reason = strtr($_POST['reason'], $reason_replacements);

			// Automatically remove this MERGED redirection topic in the future?
			$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

			// Redirect to the MERGED topic from topic list?
			$redirect_topic = isset($_POST['redirect_topic']) ? $id_topic : 0;

			foreach ($deleted_topics as $this_old_topic) {
				$redirect_subject = sprintf(Lang::$txt['merged_subject'], $this->topic_data[$this_old_topic]['subject']);

				$msgOptions = [
					'icon' => 'moved',
					'subject' => $redirect_subject,
					'body' => $reason,
					'approved' => 1,
				];

				$topicOptions = [
					'id' => $this_old_topic,
					'is_approved' => true,
					'lock_mode' => 1,
					'board' => $this->topic_data[$this_old_topic]['board'],
					'mark_as_read' => true,
				];

				// So we have to make the post. We need to do *this* here so we don't foul up indexes later
				// and we have to fix them up later once everything else has happened.
				if (Msg::create($msgOptions, $topicOptions, $posterOptions)) {
					$updated_topics[$this_old_topic] = $msgOptions['id'];
				}

				// Update subject search index
				Logging::updateStats('subject', $this_old_topic, $redirect_subject);
			}

			// Restore language strings to normal.
			if (User::$me->language != Lang::$default) {
				Lang::load('index');
			}
		}

		// Grab the response prefix (like 'Re: ') in the default forum language.
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

		// Change the topic IDs of all messages that will be merged.  Also adjust subjects if 'enforce subject' was checked.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET
				id_topic = {int:id_topic},
				id_board = {int:target_board}' . (empty($_POST['enforce_subject']) ? '' : ',
				subject = {string:subject}') . '
			WHERE id_topic IN ({array_int:topic_list})' . (!empty($updated_topics) ? '
				AND id_msg NOT IN ({array_int:merge_msg})' : ''),
			[
				'topic_list' => $this->topics,
				'id_topic' => $id_topic,
				'merge_msg' => $updated_topics,
				'target_board' => $target_board,
				'subject' => Utils::$context['response_prefix'] . $target_subject,
			],
		);

		// Any reported posts should reflect the new board.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_reported
			SET
				id_topic = {int:id_topic},
				id_board = {int:target_board}
			WHERE id_topic IN ({array_int:topics_list})',
			[
				'topics_list' => $this->topics,
				'id_topic' => $id_topic,
				'target_board' => $target_board,
			],
		);

		// Change the subject of the first message...
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET subject = {string:target_subject}
			WHERE id_msg = {int:first_msg}',
			[
				'first_msg' => $first_msg,
				'target_subject' => $target_subject,
			],
		);

		// Adjust all calendar events to point to the new topic.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}calendar
			SET
				id_topic = {int:id_topic},
				id_board = {int:target_board}
			WHERE id_topic IN ({array_int:deleted_topics})',
			[
				'deleted_topics' => $deleted_topics,
				'id_topic' => $id_topic,
				'target_board' => $target_board,
			],
		);

		// Merge log topic entries.
		// The unwatch setting comes from the oldest topic
		$request = Db::$db->query(
			'',
			'SELECT id_member, MIN(id_msg) AS new_id_msg, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_member, unwatched',
			[
				'topics' => $this->topics,
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			$replaceEntries = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$replaceEntries[] = [$row['id_member'], $id_topic, $row['new_id_msg'], $row['unwatched']];
			}

			Db::$db->insert(
				'replace',
				'{db_prefix}log_topics',
				['id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
				$replaceEntries,
				['id_member', 'id_topic'],
			);

			unset($replaceEntries);

			// Get rid of the old log entries.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_topics
				WHERE id_topic IN ({array_int:deleted_topics})',
				[
					'deleted_topics' => $deleted_topics,
				],
			);
		}
		Db::$db->free_result($request);

		// Merge topic notifications.
		$notifications = isset($_POST['notifications']) && is_array($_POST['notifications']) ? array_intersect($this->topics, $_POST['notifications']) : [];

		if (!empty($notifications)) {
			$request = Db::$db->query(
				'',
				'SELECT id_member, MAX(sent) AS sent
				FROM {db_prefix}log_notify
				WHERE id_topic IN ({array_int:topics_list})
				GROUP BY id_member',
				[
					'topics_list' => $notifications,
				],
			);

			if (Db::$db->num_rows($request) > 0) {
				$replaceEntries = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					$replaceEntries[] = [$row['id_member'], $id_topic, 0, $row['sent']];
				}

				Db::$db->insert(
					'replace',
					'{db_prefix}log_notify',
					['id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'sent' => 'int'],
					$replaceEntries,
					['id_member', 'id_topic', 'id_board'],
				);

				unset($replaceEntries);

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_topics
					WHERE id_topic IN ({array_int:deleted_topics})',
					[
						'deleted_topics' => $deleted_topics,
					],
				);
			}
			Db::$db->free_result($request);
		}

		// Get rid of the redundant polls.
		if (!empty($deleted_polls)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}polls
				WHERE id_poll IN ({array_int:deleted_polls})',
				[
					'deleted_polls' => $deleted_polls,
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}poll_choices
				WHERE id_poll IN ({array_int:deleted_polls})',
				[
					'deleted_polls' => $deleted_polls,
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_polls
				WHERE id_poll IN ({array_int:deleted_polls})',
				[
					'deleted_polls' => $deleted_polls,
				],
			);
		}

		// Cycle through each board...
		foreach ($this->boardTotals as $id_board => $stats) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET
					num_topics = CASE WHEN {int:topics} > num_topics THEN 0 ELSE num_topics - {int:topics} END,
					unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END,
					num_posts = CASE WHEN {int:posts} > num_posts THEN 0 ELSE num_posts - {int:posts} END,
					unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END
				WHERE id_board = {int:id_board}',
				[
					'id_board' => $id_board,
					'topics' => $stats['topics'],
					'unapproved_topics' => $stats['unapproved_topics'],
					'posts' => $stats['posts'],
					'unapproved_posts' => $stats['unapproved_posts'],
				],
			);
		}

		// Determine the board the final topic resides in
		$request = Db::$db->query(
			'',
			'SELECT id_board
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			[
				'id_topic' => $id_topic,
			],
		);
		list($id_board) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Again, only do this if we're redirecting - otherwise delete
		if (isset($_POST['postRedirect'])) {
			// Having done all that, now make sure we fix the merge/redirect topics upp before we
			// leave here. Specifically: that there are no replies, no unapproved stuff, that the first
			// and last posts are the same and so on and so forth.
			foreach ($updated_topics as $old_topic => $id_msg) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}topics
					SET id_first_msg = id_last_msg,
						id_member_started = {int:current_user},
						id_member_updated = {int:current_user},
						id_poll = 0,
						approved = 1,
						num_replies = 0,
						unapproved_posts = 0,
						id_redirect_topic = {int:redirect_topic},
						redirect_expires = {int:redirect_expires}
					WHERE id_topic = {int:old_topic}',
					[
						'current_user' => User::$me->id,
						'old_topic' => $old_topic,
						'redirect_topic' => $redirect_topic,
						'redirect_expires' => $redirect_expires,
					],
				);
			}
		}

		// Ensure we don't accidentally delete the poll we want to keep...
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_poll = 0
			WHERE id_topic IN ({array_int:deleted_topics})',
			[
				'deleted_topics' => $deleted_topics,
			],
		);

		// Delete any remaining data regarding these topics, this is done before changing the properties of the merged topic (else we get duplicate keys)...
		if (!isset($_POST['postRedirect'])) {
			// Remove any remaining info about these topics...
			// We do not need to remove the counts of the deleted topics, as we already removed these.
			Topic::remove($deleted_topics, false, true, false);
		}

		// Assign the properties of the newly merged topic.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET
				id_board = {int:id_board},
				id_member_started = {int:id_member_started},
				id_member_updated = {int:id_member_updated},
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				id_poll = {int:id_poll},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts},
				num_views = {int:num_views},
				is_sticky = {int:is_sticky},
				approved = {int:approved}
			WHERE id_topic = {int:id_topic}',
			[
				'id_board' => $target_board,
				'is_sticky' => $this->is_sticky,
				'approved' => $topic_approved,
				'id_topic' => $id_topic,
				'id_member_started' => $member_started,
				'id_member_updated' => $member_updated,
				'id_first_msg' => $first_msg,
				'id_last_msg' => $last_msg,
				'id_poll' => $target_poll,
				'num_replies' => $num_replies,
				'unapproved_posts' => $num_unapproved,
				'num_views' => $this->num_views,
			],
		);

		// Update all the statistics.
		Logging::updateStats('topic');
		Logging::updateStats('subject', $id_topic, $target_subject);
		Msg::updateLastMessages($this->boards);

		Logging::logAction('merge', ['topic' => $id_topic, 'board' => $id_board]);

		// Notify people that these topics have been merged?
		Mail::sendNotifications($id_topic, 'merge');

		// If there's a search index that needs updating, update it...
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'topicMerge'])) {
			$searchAPI->topicMerge($id_topic, $this->topics, $affected_msgs, empty($_POST['enforce_subject']) ? null : [Utils::$context['response_prefix'], $target_subject]);
		}

		// Merging is the sort of thing an external CMS might want to know about
		$merged_topic = [
			'id_board' => $target_board,
			'is_sticky' => $this->is_sticky,
			'approved' => $topic_approved,
			'id_topic' => $id_topic,
			'id_member_started' => $member_started,
			'id_member_updated' => $member_updated,
			'id_first_msg' => $first_msg,
			'id_last_msg' => $last_msg,
			'id_poll' => $target_poll,
			'num_replies' => $num_replies,
			'unapproved_posts' => $num_unapproved,
			'num_views' => $this->num_views,
			'subject' => $target_subject,
		];

		IntegrationHook::call('integrate_merge_topic', [$merged_topic, $updated_topics, $deleted_topics, $deleted_polls]);

		// Send them to the all done page.
		Utils::redirectexit('action=mergetopics;sa=done;to=' . $id_topic . ';targetboard=' . $target_board);
	}

	/**
	 * Shows a 'merge completed' screen.
	 *
	 * - Accessed via ?action=mergetopics;sa=done.
	 * - Uses 'merge_done' sub template of the SplitTopics template.
	 */
	public function done()
	{
		// Make sure the template knows everything...
		Utils::$context['target_board'] = (int) $_GET['targetboard'];
		Utils::$context['target_topic'] = (int) $_GET['to'];

		Utils::$context['page_title'] = Lang::$txt['merge'];
		Utils::$context['sub_template'] = 'merge_done';
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
	 * Initiates a merge of the specified topics.
	 *
	 * Called from SMF\MessageIndex::QuickModeration().
	 *
	 * @param array $topics The IDs of the topics to merge
	 */
	public static function initiate($topics = [])
	{
		self::load();
		self::$obj->subaction = 'options';
		self::$obj->topics = array_map('intval', $topics);
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the index sub-action.
	 */
	public static function mergeIndex(): void
	{
		self::load();
		self::$obj->subaction = 'index';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the options and/or merge sub-actions.
	 * (The old procedural function with this name did both.)
	 *
	 * @param array $topics The IDs of the topics to merge
	 */
	public static function mergeExecute($topics = [])
	{
		self::load();
		self::$obj->subaction = !empty($_GET['sa']) && $_GET['sa'] === 'merge' ? 'merge' : 'options';
		self::$obj->topics = array_map('intval', $topics);
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the split sub-action.
	 */
	public static function mergeDone(): void
	{
		self::load();
		self::$obj->subaction = 'done';
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
		// The 'merge' sub-action used to be called 'execute'.
		if (!empty($_GET['sa']) && $_GET['sa'] === 'execute') {
			$_GET['sa'] = 'merge';
			$_REQUEST['sa'] = 'merge';
		}

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}

	/**
	 * Sets up some stuff needed for both $this->options() and $this->merge().
	 */
	protected function initOptionsAndMerge()
	{
		// Check the session.
		User::$me->checkSession('request');

		$this->getTopics();

		// There's nothing to merge with just one topic...
		if (empty($this->topics) || !is_array($this->topics) || count($this->topics) == 1) {
			ErrorHandler::fatalLang('merge_need_more_topics');
		}

		// Make sure every topic is numeric, or some nasty things could be done with the DB.
		$this->topics = array_map('intval', $this->topics);

		// Joy of all joys, make sure they're not messing about with unapproved topics they can't see :P
		if (Config::$modSettings['postmod_active']) {
			$this->can_approve_boards = User::$me->boardsAllowedTo('approve_posts');
		}

		$this->getTopicData();

		Utils::$context['is_approved'] = &$this->approved;

		// If we didn't get any topics then they've been messing with unapproved stuff.
		if (empty($this->topic_data)) {
			ErrorHandler::fatalLang('no_topic_id');
		}

		if (isset($_POST['postRedirect']) && !empty($this->lowestTopicBoard)) {
			$this->boardTotals[$this->lowestTopicBoard]['topics']++;
		}

		// Will this be approved?
		$this->approved = $this->topic_data[$this->firstTopic]['approved'];

		$this->boards = array_values(array_unique($this->boards));

		if (!empty($this->topics)) {
			User::$me->isAllowedTo('merge_any', $this->boards);
			Theme::loadTemplate('MoveTopic');
		}

		$this->getMergeBoards();
	}

	/**
	 * Sets the value of $this->topics.
	 */
	protected function getTopics()
	{
		// Already set.
		if (count($this->topics) > 1) {
			return;
		}

		// Handle URLs from MergeIndex.
		if (!empty($_GET['from']) && !empty($_GET['to'])) {
			$this->topics = [(int) $_GET['from'], (int) $_GET['to']];
		}

		// If we came from a form, the topic IDs came by post.
		if (!empty($_REQUEST['topics']) && is_array($_REQUEST['topics'])) {
			$this->topics = (array) $_REQUEST['topics'];
		}
	}

	/**
	 * Gets info about the topics and polls that will be merged.
	 */
	protected function getTopicData()
	{
		$request = Db::$db->query(
			'',
			'SELECT
				t.id_topic, t.id_board, t.id_poll, t.num_views, t.is_sticky, t.approved, t.num_replies, t.unapproved_posts, t.id_redirect_topic,
				m1.subject, m1.poster_time AS time_started, COALESCE(mem1.id_member, 0) AS id_member_started, COALESCE(mem1.real_name, m1.poster_name) AS name_started,
				m2.poster_time AS time_updated, COALESCE(mem2.id_member, 0) AS id_member_updated, COALESCE(mem2.real_name, m2.poster_name) AS name_updated
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m1 ON (m1.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem1 ON (mem1.id_member = m1.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY t.id_first_msg
			LIMIT {int:limit}',
			[
				'topic_list' => $this->topics,
				'limit' => count($this->topics),
			],
		);

		if (Db::$db->num_rows($request) < 2) {
			ErrorHandler::fatalLang('no_topic_id');
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			// Sorry, redirection topics can't be merged
			if (!empty($row['id_redirect_topic'])) {
				ErrorHandler::fatalLang('cannot_merge_redirect', false);
			}

			// Make a note for the board counts...
			if (!isset($this->boardTotals[$row['id_board']])) {
				$this->boardTotals[$row['id_board']] = [
					'posts' => 0,
					'topics' => 0,
					'unapproved_posts' => 0,
					'unapproved_topics' => 0,
				];
			}

			// We can't see unapproved topics here?
			if (Config::$modSettings['postmod_active'] && !$row['approved'] && $this->can_approve_boards != [0] && in_array($row['id_board'], $this->can_approve_boards)) {
				// If we can't see it, we should not merge it and not adjust counts! Instead skip it.
				unset($this->topics[$row['id_topic']]);

				continue;
			}

			if (!$row['approved']) {
				$this->boardTotals[$row['id_board']]['unapproved_topics']++;
			} else {
				$this->boardTotals[$row['id_board']]['topics']++;
			}

			$this->boardTotals[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];
			$this->boardTotals[$row['id_board']]['posts'] += $row['num_replies'] + ($row['approved'] ? 1 : 0);

			// In the case of making a redirect, the topic count goes up by one due to the redirect topic.
			if (isset($_POST['postRedirect'])) {
				$this->boardTotals[$row['id_board']]['topics']--;
			}

			$this->topic_data[$row['id_topic']] = [
				'id' => $row['id_topic'],
				'board' => $row['id_board'],
				'poll' => $row['id_poll'],
				'num_views' => $row['num_views'],
				'subject' => $row['subject'],
				'started' => [
					'time' => Time::create('@' . $row['time_started'])->format(),
					'timestamp' => $row['time_started'],
					'href' => empty($row['id_member_started']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_member_started'],
					'link' => empty($row['id_member_started']) ? $row['name_started'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member_started'] . '">' . $row['name_started'] . '</a>',
				],
				'updated' => [
					'time' => Time::create('@' . $row['time_updated'])->format(),
					'timestamp' => $row['time_updated'],
					'href' => empty($row['id_member_updated']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_member_updated'],
					'link' => empty($row['id_member_updated']) ? $row['name_updated'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['name_updated'] . '</a>',
				],
				'approved' => $row['approved'],
			];

			$this->num_views += $row['num_views'];
			$this->boards[] = $row['id_board'];

			// If there's no poll, id_poll == 0...
			if ($row['id_poll'] > 0) {
				$this->polls[] = $row['id_poll'];
			}

			// Store the id_topic with the lowest id_first_msg.
			if (empty($this->firstTopic)) {
				$this->firstTopic = $row['id_topic'];
			}

			// Lowest topic id gets selected as surviving topic id. We need to store this board so we can adjust the topic count (This one will not have a redirect topic)
			if ($row['id_topic'] < $this->lowestTopicId || empty($this->lowestTopicId)) {
				$this->lowestTopicId = $row['id_topic'];
				$this->lowestTopicBoard = $row['id_board'];
			}

			$this->is_sticky = max($this->is_sticky, $row['is_sticky']);
		}
		Db::$db->free_result($request);
	}

	/**
	 * Gets the boards in which the user is allowed to merge topics.
	 */
	protected function getMergeBoards()
	{
		$this->merge_boards = User::$me->boardsAllowedTo('merge_any');

		if (empty($this->merge_boards)) {
			ErrorHandler::fatalLang('cannot_merge_any', 'user');
		}

		// Make sure they can see all boards....
		$request = Db::$db->query(
			'',
			'SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:boards})
				AND {query_see_board}' . (!in_array(0, $this->merge_boards) ? '
				AND b.id_board IN ({array_int:merge_boards})' : '') . '
			LIMIT {int:limit}',
			[
				'boards' => $this->boards,
				'merge_boards' => $this->merge_boards,
				'limit' => count($this->boards),
			],
		);

		// If the number of boards that's in the output isn't exactly the same as we've put in there, you're in trouble.
		if (Db::$db->num_rows($request) != count($this->boards)) {
			ErrorHandler::fatalLang('no_board');
		}

		Db::$db->free_result($request);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TopicMerge::exportStatic')) {
	TopicMerge::exportStatic();
}

?>