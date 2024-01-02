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
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Logging;
use SMF\Mail;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Handles moderation from the message index.
 */
class QuickModeration implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'QuickModeration',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	// code...

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Actions that a user might perform on topics.
	 *
	 * Note: it is better to access this via self::getActions() in case any mods
	 * have something to add to the list.
	 */
	public static array $known_actions = [
		'approve',
		'remove',
		'lock',
		'sticky',
		'move',
		'merge',
		'restore',
		'markread',
	];

	/**
	 * @var array
	 *
	 * Permissions need to carry out different actions.
	 */
	public static array $action_permissions = [
		'markread' => [
			'any' => 'is_not_guest',
		],
		'sticky' => [
			'any' => 'make_sticky',
		],
		'lock' => [
			'any' => 'lock_any',
			'own' => 'lock_own',
		],
		'approve' => [
			'any' => 'approve_posts',
		],
		'remove' => [
			'any' => 'remove_any',
			'own' => 'remove_own',
		],
		'move' => [
			'any' => 'move_any',
			'own' => 'move_own',
		],
		'merge' => [
			'any' => 'merge_any',
		],
		'restore' => [
			'any' => 'move_any',
			'where' => 'recycle_board',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Actions that this user is allowed perform on topics.
	 */
	protected array $possible_actions = [];

	/**
	 * @var array
	 *
	 * Boards where this user can do different actions.
	 */
	protected array $boards_can = [];

	/**
	 * @var string
	 *
	 * Where to send the user after the action is complete.
	 */
	protected string $redirect_url = '';

	/**
	 * @var bool
	 *
	 * If false, do not redirect when done.
	 * Used for actions that require showing a UI.
	 */
	protected bool $should_redirect = true;

	/**
	 * @var array
	 *
	 * The actions to perform on the given topics.
	 *
	 * Keys in this array are action names.
	 *
	 * In most cases, the values are arrays of topic IDs. The exception is
	 * 'move', where the 'topics' sub-array contains topic IDs and the 'to'
	 * sub-array contains info about the board that each topic should be
	 * moved to.
	 */
	protected array $topic_actions = [
		'markread' => [],
		'sticky' => [],
		'lock' => [],
		'approve' => [],
		'remove' => [],
		'move' => [
			'topics' => [],
			'to' => [],
		],
		'merge' => [],
		'restore' => [],
	];

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
	 * Does the job.
	 */
	public function execute(): void
	{
		// Check the session = get or post.
		User::$me->checkSession('request');

		// This won't be valid anymore.
		if (isset($_SESSION['topicseen_cache'])) {
			$_SESSION['topicseen_cache'] = [];
		}

		// Which actions can this user perform?
		$this->setPossibleActions();

		// Figure out what to do.
		$this->setTopicActions();

		// Do the jobs...
		$this->doSticky();
		$this->doMove();
		$this->doRemove();
		$this->doApprove();
		$this->doLock();
		$this->doMarkRead();
		$this->doMerge();
		$this->doRestore();

		// Update stats and such.
		Logging::updateStats('topic');
		Logging::updateStats('message');
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		if ($this->should_redirect) {
			Utils::redirectexit($this->redirect_url);
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
	 * Gets the list of known quick moderation actions.
	 *
	 * Calls the integrate_quick_mod_actions hook.
	 * Sets Utils::$context['qmod_actions'].
	 *
	 * @param bool $search Whether this is being called from the search results
	 *     display page.
	 * @return array The list of known quick moderation actions.
	 */
	public static function getActions(bool $search = false): array
	{
		Utils::$context['qmod_actions'] = &self::$known_actions;

		if ($search) {
			// Approve isn't applicable when viewing search results.
			self::$known_actions = array_diff(self::$known_actions, ['approve']);

			IntegrationHook::call('integrate_quick_mod_actions_search', [self::$known_actions]);
		} else {
			IntegrationHook::call('integrate_quick_mod_actions', [self::$known_actions]);
		}

		return self::$known_actions;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Let modifications add custom actions.
		self::getActions();

		// Unset any topic_actions that aren't in known_actions.
		$this->topic_actions = array_intersect_key($this->topic_actions, array_flip(self::$known_actions));

		// Add any custom actions to topic_actions.
		foreach (self::$known_actions as $action) {
			$this->topic_actions[$action] = [];
		}

		$this->setRequestActions();
	}

	/**
	 * Standardizes $_REQUEST input regarding action and topics.
	 *
	 * The requested moderation actions can be specified in one of two ways:
	 *
	 * - $_REQUEST['qaction'] and $_REQUEST['topics'] is used when quick
	 *   moderation uses checkboxes. In this approach, there is only one action
	 *   specified, but any number of topics could be specified.
	 *
	 * - $_REQUEST['actions'] is used when quick moderation uses icons. The keys
	 *   in the $_REQUEST['actions'] array are topic IDs, and the values are the
	 *   actions to perform on those topics. In theory this array could contain
	 *   multiple topics to act upon, although in practice the templates only
	 *   offer the ability to act on one topic per request.
	 *
	 * When this method returns, the input will have been standardized to use
	 * the $_REQUEST['actions'] format.
	 */
	protected function setRequestActions(): void
	{
		if (!empty($_REQUEST['topics'])) {
			// If the action isn't valid, just quit now.
			if (empty($_REQUEST['qaction']) || !isset($this->topic_actions[$_REQUEST['qaction']])) {
				Utils::redirectexit($this->redirect_url);
			}

			// Just convert to the other method, to make it easier.
			foreach ($_REQUEST['topics'] as $topic) {
				$_REQUEST['actions'][(int) $topic] = $_REQUEST['qaction'];
			}
		}

		// Weird... how'd you get here?
		if (empty($_REQUEST['actions'])) {
			Utils::redirectexit($this->redirect_url);
		}
	}

	/**
	 * Figures out which actions the user is allowed to perform.
	 * Only checks the permissions for the actions that were requested.
	 *
	 * Sets $this->possible_actions and $this->boards_can.
	 */
	protected function setPossibleActions(): void
	{
		// Which permissions do we need to check?
		$permissions_to_check = [];

		foreach (self::$action_permissions as $action => $permissions) {
			// Skip permissions for actions that weren't requested.
			// Exception: always check the approval permission.
			if (!in_array($action, $_REQUEST['actions']) && $action !== 'approve') {
				continue;
			}

			// If the permission specifies a particular board, only proceed
			// if we are in that board.
			if (!empty($permissions['where'])) {
				// The 'where' can be a $modSettings key or a raw board ID.
				if (!is_numeric($permissions['where'])) {
					$permissions['where'] = Config::$modSettings[$permissions['where']] ?? null;
				}

				// Can't do it if not in the required board.
				if (!empty($permissions['where']) && !empty(Board::$info->id) && Board::$info->id != $permissions['where']) {
					continue;
				}
			}

			$permissions_to_check = array_unique(array_merge($permissions_to_check, array_values($permissions)));
		}

		if (!empty(Board::$info->id)) {
			foreach ($permissions_to_check as $permission) {
				$this->boards_can[$permission] = User::$me->allowedTo($permission) ? [Board::$info->id] : [];
			}

			$this->redirect_url = 'board=' . Board::$info->id . '.' . $_REQUEST['start'];
		} else {
			$this->boards_can = User::$me->boardsAllowedTo($permissions_to_check, true, false);

			$this->redirect_url = $_POST['redirect_url'] ?? ($_SESSION['old_url'] ?? '');
		}

		// Are we enforcing the "no moving topics to boards where you can't post new ones" rule?
		if (!User::$me->is_admin && !Config::$modSettings['topic_move_any']) {
			// Don't count this board, if it's specified
			if (!empty(Board::$info->id)) {
				$this->boards_can['post_new'] = array_diff(User::$me->boardsAllowedTo('post_new'), [Board::$info->id]);
			} else {
				$this->boards_can['post_new'] = User::$me->boardsAllowedTo('post_new');
			}

			if (empty($this->boards_can['post_new'])) {
				$this->boards_can['move_any'] = $this->boards_can['move_own'] = [];
			}
		}

		foreach (self::$action_permissions as $action => $permissions) {
			foreach (['any', 'own'] as $scope) {
				if (isset($permissions[$scope]) && !empty($this->boards_can[$permissions[$scope]])) {
					$this->possible_actions[] = $action;
					break;
				}
			}
		}
	}

	/**
	 * Sets $this->topic_actions based on $_REQUEST['actions'].
	 */
	protected function setTopicActions(): void
	{
		// Validate each action.
		$temp = [];

		foreach ($_REQUEST['actions'] as $topic => $action) {
			if (in_array($action, $this->possible_actions)) {
				$temp[(int) $topic] = $action;
			}
		}
		$_REQUEST['actions'] = $temp;

		if (!empty($_REQUEST['actions'])) {
			// Find all topics...
			$request = Db::$db->query(
				'',
				'SELECT id_topic, id_member_started, id_board, locked, approved, unapproved_posts
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:action_topic_ids})
				LIMIT {int:limit}',
				[
					'action_topic_ids' => array_keys($_REQUEST['actions']),
					'limit' => count($_REQUEST['actions']),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				// Can't act on topics that aren't in this board.
				if (!empty(Board::$info->id) && $row['id_board'] != Board::$info->id) {
					unset($_REQUEST['actions'][$row['id_topic']]);

					continue;
				}

				// Can't act on topics that they can't see...
				if (Config::$modSettings['postmod_active'] && !$row['approved'] && array_intersect([0, $row['id_board']], $this->boards_can['approve_posts']) === []) {
					unset($_REQUEST['actions'][$row['id_topic']]);

					continue;
				}

				foreach (self::$action_permissions as $action => $permissions) {
					if ($action !== $_REQUEST['actions'][$row['id_topic']]) {
						continue;
					}

					$boards_can_any = isset($permissions['any']) ? array_intersect([0, $row['id_board']], $this->boards_can[$permissions['any']]) : [];

					$boards_can_own = isset($permissions['own']) ? array_intersect([0, $row['id_board']], $this->boards_can[$permissions['own']]) : [];

					$own_topic = $row['id_member_started'] == User::$me->id;

					// If locked by a moderator, non-moderators cannot unlock.
					$mod_locked = $action === 'lock' && $row['locked'] == 1;

					if (empty($boards_can_any) && (!$own_topic || empty($boards_can_own) || $mod_locked)) {
						unset($_REQUEST['actions'][$row['id_topic']]);
					}

					// Don't bother approving if there are no unapproved posts.
					if ($action === 'approve' && empty($row['unapproved_posts'])) {
						unset($_REQUEST['actions'][$row['id_topic']]);
					}
				}
			}
			Db::$db->free_result($request);
		}

		// Separate the actions.
		foreach ($_REQUEST['actions'] as $topic => $action) {
			$topic = (int) $topic;

			switch ($action) {
				case 'move':
					TopicMove2::moveTopicConcurrence();

					// If action is 'move', $_REQUEST['move_to'] or $_REQUEST['move_tos'][$topic] is used.
					$this->topic_actions['move']['to'][$topic] = (int) ($_REQUEST['move_tos'][$topic] ?? $_REQUEST['move_to']);

					if (empty($this->topic_actions['move']['to'][$topic])) {
						break;
					}

					// Never move topics to redirect boards
					$redirect_boards = [];
					$request = Db::$db->query(
						'',
						'SELECT id_board
						FROM {db_prefix}boards
						WHERE redirect != {string:blank_redirect}',
						[
							'blank_redirect' => '',
						],
					);

					while ($row = Db::$db->fetch_row($request)) {
						$redirect_boards[] = $row[0];
					}
					Db::$db->free_result($request);

					if (in_array($this->topic_actions['move']['to'][$topic], $redirect_boards)) {
						break;
					}

					$this->topic_actions['move']['topics'][] = $topic;
					break;

				default:
					$this->topic_actions[$action][] = $topic;
					break;
			}
		}
	}

	/**
	 * Marks the topics as read for the current user.
	 */
	protected function doMarkRead(): void
	{
		if (empty($this->topic_actions['markread'])) {
			return;
		}

		$logged_topics = [];

		$request = Db::$db->query(
			'',
			'SELECT id_topic, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:selected_topics})
				AND id_member = {int:current_user}',
			[
				'selected_topics' => $this->topic_actions['markread'],
				'current_user' => User::$me->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$logged_topics[$row['id_topic']] = $row['unwatched'];
		}
		Db::$db->free_result($request);

		$markreadArray = [];

		foreach ($this->topic_actions['markread'] as $topic) {
			$markreadArray[] = [Config::$modSettings['maxMsgID'], User::$me->id, $topic, ($logged_topics[$topic] ?? 0)];
		}

		Db::$db->insert(
			'replace',
			'{db_prefix}log_topics',
			['id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'],
			$markreadArray,
			['id_member', 'id_topic'],
		);
	}

	/**
	 * Stickies or unstickies the topics, and does some logging and notifying.
	 */
	protected function doSticky(): void
	{
		if (empty($this->topic_actions['sticky'])) {
			return;
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET is_sticky = CASE WHEN is_sticky = {int:is_sticky} THEN 0 ELSE 1 END
			WHERE id_topic IN ({array_int:sticky_topic_ids})',
			[
				'sticky_topic_ids' => $this->topic_actions['sticky'],
				'is_sticky' => 1,
			],
		);

		// Get the board IDs and Sticky status
		$sticky_cache_boards = [];
		$sticky_cache_status = [];

		$request = Db::$db->query(
			'',
			'SELECT id_topic, id_board, is_sticky
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:sticky_topic_ids})
			LIMIT {int:limit}',
			[
				'sticky_topic_ids' => $this->topic_actions['sticky'],
				'limit' => count($this->topic_actions['sticky']),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$sticky_cache_boards[$row['id_topic']] = $row['id_board'];
			$sticky_cache_status[$row['id_topic']] = empty($row['is_sticky']);
		}
		Db::$db->free_result($request);

		foreach ($this->topic_actions['sticky'] as $topic) {
			Logging::logAction($sticky_cache_status[$topic] ? 'unsticky' : 'sticky', ['topic' => $topic, 'board' => $sticky_cache_boards[$topic]]);

			Mail::sendNotifications($topic, 'sticky');
		}
	}

	/**
	 * Locks or unlocks the topics, and does some logging and notifying.
	 */
	protected function doLock(): void
	{
		if (empty($this->topic_actions['lock'])) {
			return;
		}

		$lock_status = [];

		// Gotta make sure they CAN lock/unlock these topics...
		if (!empty(Board::$info->id) && !User::$me->allowedTo('lock_any')) {
			// Make sure they started the topic AND it isn't already locked by someone with higher priv's.
			$locked_topic_ids = [];
			$lock_cache_boards = [];

			$result = Db::$db->query(
				'',
				'SELECT id_topic, locked, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:locked_topic_ids})
					AND id_member_started = {int:current_member}
					AND locked IN (2, 0)
				LIMIT {int:limit}',
				[
					'current_member' => User::$me->id,
					'locked_topic_ids' => $this->topic_actions['lock'],
					'limit' => count($locked_topic_ids),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$locked_topic_ids[] = $row['id_topic'];
				$lock_cache_boards[$row['id_topic']] = $row['id_board'];
				$lock_status[$row['id_topic']] = empty($row['locked']);
			}
			Db::$db->free_result($result);

			$this->topic_actions['lock'] = $locked_topic_ids;
		} else {
			$lock_cache_boards = [];

			$result = Db::$db->query(
				'',
				'SELECT id_topic, locked, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:locked_topic_ids})
				LIMIT {int:limit}',
				[
					'locked_topic_ids' => $this->topic_actions['lock'],
					'limit' => count($this->topic_actions['lock']),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$lock_status[$row['id_topic']] = empty($row['locked']);
				$lock_cache_boards[$row['id_topic']] = $row['id_board'];
			}
			Db::$db->free_result($result);
		}

		// It could just be that *none* were their own topics...
		if (!empty($this->topic_actions['lock'])) {
			// Alternate the locked value.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET locked = CASE WHEN locked = {int:is_locked} THEN ' . (User::$me->allowedTo('lock_any') ? '1' : '2') . ' ELSE 0 END
				WHERE id_topic IN ({array_int:locked_topic_ids})',
				[
					'locked_topic_ids' => $this->topic_actions['lock'],
					'is_locked' => 0,
				],
			);
		}

		foreach ($this->topic_actions['lock'] as $topic) {
			Logging::logAction($lock_status[$topic] ? 'lock' : 'unlock', ['topic' => $topic, 'board' => $lock_cache_boards[$topic]]);

			Mail::sendNotifications($topic, $lock_status[$topic] ? 'lock' : 'unlock');
		}
	}

	/**
	 * Moves topics from one board to another.
	 *
	 * Performs some checks, passes the topic IDs to SMF\Topic::remove(),
	 * updates some stats, and then does some logging and notifying.
	 */
	protected function doMove(): void
	{
		// Remember the last board they moved things to.
		if (isset($_REQUEST['move_to'])) {
			$_SESSION['move_to_topic'] = $_REQUEST['move_to'];
		}

		if (empty($this->topic_actions['move']['topics'])) {
			return;
		}

		// I know - I just KNOW you're trying to beat the system.  Too bad for you... we CHECK :P.
		$moveTos = [];
		$moveCache2 = [];
		$countPosts = [];

		$request = Db::$db->query(
			'',
			'SELECT t.id_topic, t.id_board, b.count_posts
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			WHERE t.id_topic IN ({array_int:move_topic_ids})' . (!empty(Board::$info->id) && !User::$me->allowedTo('move_any') ? '
				AND t.id_member_started = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			[
				'current_member' => User::$me->id,
				'move_topic_ids' => $this->topic_actions['move']['topics'],
				'limit' => count($this->topic_actions['move']['topics']),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$to = $this->topic_actions['move']['to'][$row['id_topic']];

			if (empty($to)) {
				continue;
			}

			// Does this topic's board count the posts or not?
			$countPosts[$row['id_topic']] = empty($row['count_posts']);

			if (!isset($moveTos[$to])) {
				$moveTos[$to] = [];
			}

			$moveTos[$to][] = $row['id_topic'];

			// For reporting...
			$moveCache2[] = [$row['id_topic'], $row['id_board'], $to];
		}
		Db::$db->free_result($request);

		$this->topic_actions['move'] = $moveCache2;

		// Do the actual moves...
		foreach ($moveTos as $to => $topics) {
			Topic::move($topics, $to);
		}

		// Does the post counts need to be updated?
		if (!empty($moveTos)) {
			$topicRecounts = [];
			$request = Db::$db->query(
				'',
				'SELECT id_board, count_posts
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:move_boards})',
				[
					'move_boards' => array_keys($moveTos),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$cp = empty($row['count_posts']);

				// Go through all the topics that are being moved to this board.
				foreach ($moveTos[$row['id_board']] as $topic) {
					// If both boards have the same value for post counting then no adjustment needs to be made.
					if ($countPosts[$topic] != $cp) {
						// If the board being moved to does count the posts then the other one doesn't so add to their post count.
						$topicRecounts[$topic] = $cp ? '+' : '-';
					}
				}
			}

			Db::$db->free_result($request);

			if (!empty($topicRecounts)) {
				$members = [];

				// Get all the members who have posted in the moved topics.
				$request = Db::$db->query(
					'',
					'SELECT id_member, id_topic
					FROM {db_prefix}messages
					WHERE id_topic IN ({array_int:moved_topic_ids})',
					[
						'moved_topic_ids' => array_keys($topicRecounts),
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					if (!isset($members[$row['id_member']])) {
						$members[$row['id_member']] = 0;
					}

					if ($topicRecounts[$row['id_topic']] === '+') {
						$members[$row['id_member']]++;
					} else {
						$members[$row['id_member']]--;
					}

					$members[$row['id_member']] = max(0, $members[$row['id_member']]);
				}
				Db::$db->free_result($request);

				// And now update them member's post counts
				foreach ($members as $id_member => $post_adj) {
					User::updateMemberData($id_member, ['posts' => 'posts + ' . $post_adj]);
				}
			}
		}

		foreach ($this->topic_actions['move'] as $topic) {
			// Didn't actually move anything!
			if (!isset($topic[0])) {
				break;
			}

			Logging::logAction('move', ['topic' => $topic[0], 'board_from' => $topic[1], 'board_to' => $topic[2]]);

			Mail::sendNotifications($topic[0], 'move');
		}
	}

	/**
	 * Removes topics, by moving them to the recycle board or deleting them.
	 *
	 * Performs some checks, passes the topic IDs to SMF\Topic::remove(), and
	 * does some logging and notifying.
	 */
	protected function doRemove(): void
	{
		if (empty($this->topic_actions['remove'])) {
			return;
		}

		// They can only delete their own topics. (we wouldn't be here if they couldn't do that..)
		$removed_topic_ids = [];
		$remove_cache_boards = [];

		$result = Db::$db->query(
			'',
			'SELECT id_topic, id_board
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:removed_topic_ids})' . (!empty(Board::$info->id) && !User::$me->allowedTo('remove_any') ? '
				AND id_member_started = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			[
				'current_member' => User::$me->id,
				'removed_topic_ids' => $this->topic_actions['remove'],
				'limit' => count($this->topic_actions['remove']),
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$removed_topic_ids[] = $row['id_topic'];
			$remove_cache_boards[$row['id_topic']] = $row['id_board'];
		}
		Db::$db->free_result($result);

		$this->topic_actions['remove'] = $removed_topic_ids;

		// Maybe *none* were their own topics.
		if (!empty($this->topic_actions['remove'])) {
			// Gotta send the notifications *first*!
			foreach ($this->topic_actions['remove'] as $topic) {
				// Only log the topic ID if it's not in the recycle board.
				Logging::logAction('remove', [(empty(Config::$modSettings['recycle_enable']) || Config::$modSettings['recycle_board'] != $remove_cache_boards[$topic] ? 'topic' : 'old_topic_id') => $topic, 'board' => $remove_cache_boards[$topic]]);

				Mail::sendNotifications($topic, 'remove');
			}

			Topic::remove($this->topic_actions['remove']);
		}
	}

	/**
	 * Approves a topic.
	 *
	 * Performs some checks, passes the topic IDs to SMF\Topic::approve(), and
	 * does some logging.
	 */
	protected function doApprove(): void
	{
		if (empty($this->topic_actions['approve'])) {
			return;
		}

		// We need unapproved topic ids and their authors!
		$approve_topic_ids = [];
		$approve_cache_members = [];

		$request = Db::$db->query(
			'',
			'SELECT id_topic, id_member_started
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:approve_topic_ids})
				AND approved = {int:not_approved}
			LIMIT {int:limit}',
			[
				'approve_topic_ids' => $this->topic_actions['approve'],
				'not_approved' => 0,
				'limit' => count($this->topic_actions['approve']),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$approve_topic_ids[] = $row['id_topic'];
			$approve_cache_members[$row['id_topic']] = $row['id_member_started'];
		}
		Db::$db->free_result($request);

		$this->topic_actions['approve'] = $approve_topic_ids;

		// Any topics to approve?
		if (!empty($this->topic_actions['approve'])) {
			// Handle the approval part...
			Topic::approve($this->topic_actions['approve']);

			// Time for some logging!
			foreach ($this->topic_actions['approve'] as $topic) {
				Logging::logAction('approve_topic', ['topic' => $topic, 'member' => $approve_cache_members[$topic]]);
			}
		}
	}

	/**
	 * Merges topics.
	 *
	 * Passes the topic IDs to SMF\TopicMerge::initiate().
	 * Sets $this->should_redirect to false.
	 */
	protected function doMerge(): void
	{
		// Merge requires at least two topics.
		if (count($this->topic_actions['merge']) < 2) {
			return;
		}

		TopicMerge::initiate($this->topic_actions['merge']);

		// Don't redirect, because we need to show the merge UI.
		$this->should_redirect = false;
	}

	/**
	 * Restores topics.
	 *
	 * Simply redirects to ?action=restoretopic.
	 */
	protected function doRestore(): void
	{
		// Merge requires at least two topics.
		if (empty($this->topic_actions['restore'])) {
			return;
		}

		Utils::redirectexit('action=restoretopic;topics=' . implode(',', $this->topic_actions['restore']) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\QuickModeration::exportStatic')) {
	QuickModeration::exportStatic();
}

?>