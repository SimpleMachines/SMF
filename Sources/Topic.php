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

namespace SMF;

use SMF\Actions\Moderation\ReportedContent;
use SMF\Actions\Notify;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;

/**
 * Represents a topic.
 *
 * This class's static methods also takes care of certain actions on topics:
 * lock/unlock a topic, sticky/unsticky it, etc.
 */
class Topic implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'lock' => 'LockTopic',
			'sticky' => 'Sticky',
			'approve' => 'approveTopics',
			'move' => 'moveTopics',
			'remove' => 'removeTopics',
			'prepareLikesContext' => 'prepareLikesContext',
		],
		'prop_names' => [
			'topic_id' => 'topic',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This topic's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * The board that contains this topic.
	 */
	public int $id_board;

	/**
	 * @var string
	 *
	 * Subject line of this topic's first message.
	 */
	public string $subject;

	/**
	 * @var bool
	 *
	 * Whether this topic is locked.
	 */
	public bool $is_locked;

	/**
	 * @var bool
	 *
	 * Whether this topic is stickied.
	 */
	public bool $is_sticky;

	/**
	 * @var bool
	 *
	 * Whether this topic has been approved by a moderator.
	 */
	public bool $is_approved;

	/**
	 * @var int
	 *
	 * ID number of the user who started this topic.
	 * This will be 0 for topics started by guests.
	 */
	public int $id_member_started;

	/**
	 * @var string
	 *
	 * Name of the user who started this topic.
	 *
	 * For topics started by guests, this will be the value of the poster_name
	 * field of the first post in the topic.
	 *
	 * For topics started by members, this will the real_name of the member who
	 * started the topic.
	 */
	public string $started_name;

	/**
	 * @var int
	 *
	 * Unix timestamp when the first message in this topic was submitted.
	 */
	public int $started_timestamp;

	/**
	 * @var string
	 *
	 * Formatted time string corresponding to $started_timestamp.
	 */
	public string $started_time;

	/**
	 * @var int
	 *
	 * The number of visible replies to this topic.
	 *
	 * This will be different that $real_num_replies if some replies have not
	 * yet been approved by a moderator.
	 */
	public int $num_replies;

	/**
	 * @var int
	 *
	 * The true number of replies to this topic, including both approved and
	 * unapproved ones.
	 */
	public int $real_num_replies;

	/**
	 * @var int
	 *
	 * The number of unapproved messages in this topic.
	 */
	public int $unapproved_posts;

	/**
	 * @var int
	 *
	 * The number of visible messages in this topic.
	 */
	public int $total_visible_posts;

	/**
	 * @var int
	 *
	 * The number of times this topic has been viewed.
	 */
	public int $num_views;

	/**
	 * @var int
	 *
	 * ID number of the first message in this topic.
	 */
	public int $id_first_msg;

	/**
	 * @var int
	 *
	 * ID number of the latest message in this topic.
	 */
	public int $id_last_msg;

	/**
	 * @var string
	 *
	 * Name of the user who most recently replied to this topic.
	 *
	 * For replies by guests, this will be the value of the poster_name
	 * field of the last post in the topic.
	 *
	 * For replies by members, this will the real_name of the member who
	 * most recently replied to the topic.
	 */
	public string $updated_name;

	/**
	 * @var int
	 *
	 * Unix timestamp when the latest message in this topic was submitted
	 * or modified.
	 */
	public int $updated_timestamp;

	/**
	 * @var int
	 *
	 * ID number of a topic that this topic redirects to (if any).
	 */
	public int $id_redirect_topic;

	/**
	 * @var int
	 *
	 * For topics in the recycle board, the ID number of the board that this
	 * topic used to be in.
	 */
	public int $id_previous_board;

	/**
	 * @var int
	 *
	 * For topics in the recycle board, the ID number of the topic that the
	 * messages in this topic used to be in.
	 */
	public int $id_previous_topic;

	/**
	 * @var int
	 *
	 * ID number of a poll associated with this topic (if any).
	 */
	public int $id_poll;

	/**
	 * @var bool
	 *
	 * Whether there is a visible poll associated with this topic.
	 *
	 * If polls are disabled, this will be false even if $id_poll is not empty.
	 */
	public bool $is_poll;

	/**
	 * @var int
	 *
	 * ID number of the first message in this topic that the current user has
	 * not previously read.
	 */
	public int $new_from;

	/**
	 * @var int
	 *
	 * True if the current user does not want notifications about replies to
	 * this topic.
	 */
	public int $unwatched;

	/**
	 * @var array
	 *
	 * The current user's notification preferences regarding this topic.
	 */
	public array $notify_prefs = [];

	/**
	 * @var array
	 *
	 * Contextual permissions that the current user has in this topic.
	 *
	 * "Contextual" here means "suitable for use in Utils::$context."
	 * Examples include can_move, can_lock, etc.
	 */
	public array $permissions = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var int
	 *
	 * ID number of the requested topic.
	 */
	public static $topic_id;

	/**
	 * @var SMF\Topic
	 *
	 * Instance of this class for the requested topic.
	 */
	public static $info;

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_topic' => 'id',
		'locked' => 'is_locked',
		'approved' => 'is_approved',
		'topic_started_name' => 'started_name',
		'topic_started_time' => 'started_time',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Common permissions to check for this topic.
	 * Used by Topic::doPermissions();
	 */
	protected static array $common_permissions = [
		'can_approve' => 'approve_posts',
		'can_ban' => 'manage_bans',
		'can_sticky' => 'make_sticky',
		'can_merge' => 'merge_any',
		'can_split' => 'split_any',
		'calendar_post' => 'calendar_post',
		'can_send_pm' => 'pm_send',
		'can_report_moderator' => 'report_any',
		'can_moderate_forum' => 'moderate_forum',
		'can_issue_warning' => 'issue_warning',
		'can_restore_topic' => 'move_any',
		'can_restore_msg' => 'move_any',
		'can_like' => 'likes_like',
	];

	/**
	 * @var array
	 *
	 * Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
	 * Used by Topic::doPermissions();
	 */
	protected static array $anyown_permissions = [
		'can_move' => 'move',
		'can_lock' => 'lock',
		'can_delete' => 'remove',
		'can_add_poll' => 'poll_add',
		'can_remove_poll' => 'poll_remove',
		'can_reply' => 'post_reply',
		'can_reply_unapproved' => 'post_unapproved_replies',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id The ID number of the topic.
	 * @param array $props Properties to set for this topic.
	 * @return object An instance of this class.
	 */
	public function __construct(int $id, array $props = [])
	{
		self::$loaded[$id] = $this;
		$this->id = $id;
		$this->set($props);
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		// Special case for num_replies and real_num_replies.
		if ($prop === 'num_replies' && !isset($this->real_num_replies)) {
			$this->real_num_replies = $value;
		} elseif ($prop === 'real_num_replies' && !isset($this->num_replies)) {
			$this->num_replies = $value;
		}

		$this->customPropertySet($prop, $value);
	}

	/**
	 * Determines the current user's permissions in this topic.
	 *
	 * Permission values are stored in $this->permissions and also returned.
	 *
	 * @return array Contextual permissions info.
	 */
	public function doPermissions(): array
	{
		if (!empty($this->permissions)) {
			return $this->permissions;
		}

		foreach (self::$common_permissions as $contextual => $perm) {
			$this->permissions[$contextual] = User::$me->allowedTo($perm);
		}

		foreach (self::$anyown_permissions as $contextual => $perm) {
			$this->permissions[$contextual] = User::$me->allowedTo($perm . '_any') || (User::$me->started && User::$me->allowedTo($perm . '_own'));
		}

		if (!User::$me->is_admin && $this->permissions['can_move'] && !Config::$modSettings['topic_move_any']) {
			// We'll use this in a minute
			$boards_allowed = array_diff(User::$me->boardsAllowedTo('post_new'), [Board::$info->id]);

			// You can't move this unless you have permission to start new topics on at least one other board.
			$this->permissions['can_move'] = count($boards_allowed) > 1;
		}

		// If a topic is locked, you can't remove it unless it's yours and you locked it or you can lock_any
		if ($this->is_locked) {
			$this->permissions['can_delete'] &= (($this->is_locked == 1 && User::$me->started) || User::$me->allowedTo('lock_any'));
		}

		// Cleanup all the permissions with extra stuff...
		$this->permissions['can_mark_notify'] = !User::$me->is_guest;
		$this->permissions['calendar_post'] &= !empty(Config::$modSettings['cal_enabled']) && (User::$me->allowedTo('modify_any') || (User::$me->allowedTo('modify_own') && User::$me->started));
		$this->permissions['can_add_poll'] &= Config::$modSettings['pollMode'] == '1' && $this->id_poll <= 0;
		$this->permissions['can_remove_poll'] &= Config::$modSettings['pollMode'] == '1' && $this->id_poll > 0;
		$this->permissions['can_reply'] &= empty($this->is_locked) || User::$me->allowedTo('moderate_board');
		$this->permissions['can_reply_unapproved'] &= Config::$modSettings['postmod_active'] && (empty($this->is_locked) || User::$me->allowedTo('moderate_board'));
		$this->permissions['can_issue_warning'] &= Config::$modSettings['warning_settings'][0] == 1;

		// Handle approval flags...
		$this->permissions['can_reply_approved'] = $this->permissions['can_reply'];
		$this->permissions['can_reply'] |= $this->permissions['can_reply_unapproved'];
		$this->permissions['can_quote'] = $this->permissions['can_reply'] && (empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC'])));
		$this->permissions['can_mark_unread'] = !User::$me->is_guest;
		$this->permissions['can_unwatch'] = !User::$me->is_guest;
		$this->permissions['can_set_notify'] = !User::$me->is_guest;

		$this->permissions['can_print'] = empty(Config::$modSettings['disable_print_topic']);

		// Start this off for quick moderation - it will be or'd for each post.
		$this->permissions['can_remove_post'] = User::$me->allowedTo('delete_any') || (User::$me->allowedTo('delete_replies') && User::$me->started);

		// Can restore topic?  That's if the topic is in the recycle board and has a previous restore state.
		$this->permissions['can_restore_topic'] &= !empty(Board::$info->recycle) && !empty($this->id_previous_board);
		$this->permissions['can_restore_msg'] &= !empty(Board::$info->recycle) && !empty($this->id_previous_topic);

		// Check whether the draft functions are enabled and that they have permission to use them (for quick reply.)
		$this->permissions['drafts_save'] = !empty(Config::$modSettings['drafts_post_enabled']) && User::$me->allowedTo('post_draft') && $this->permissions['can_reply'];
		$this->permissions['drafts_autosave'] = !empty($this->permissions['drafts_save']) && !empty(Config::$modSettings['drafts_autosave_enabled']) && !empty(Theme::$current->options['drafts_autosave_enabled']);

		// They can't link an existing topic to the calendar unless they can modify the first post...
		$this->permissions['calendar_post'] &= User::$me->allowedTo('modify_any') || (User::$me->allowedTo('modify_own') && User::$me->started);

		// For convenience, return the permissions array.
		return $this->permissions;
	}

	/**
	 * Gets the current user's notification preferences for this topic.
	 *
	 * Values are stored in $this->notify_prefs and also returned.
	 *
	 * @return array Notification preferences.
	 */
	public function getNotificationPrefs(): array
	{
		if (!empty(User::$me->id)) {
			$prefs = Notify::getNotifyPrefs(User::$me->id, ['topic_notify', 'topic_notify_' . $this->id, 'msg_auto_notify'], true);

			// Only pay attention to Utils::$context['is_marked_notify'] if it is set.
			$pref = !empty($prefs[User::$me->id]) && (!isset(Utils::$context['is_marked_notify']) || Utils::$context['is_marked_notify'] == true) ? $prefs[User::$me->id] : [];

			$this->notify_prefs = [
				'is_custom' => isset($pref['topic_notify_' . $this->id]),
				'pref' => $pref['topic_notify_' . $this->id] ?? ($pref['topic_notify'] ?? 0),
				'msg_auto_notify' => $pref['msg_auto_notify'] ?? false,
			];
		}

		return $this->notify_prefs;
	}

	/**
	 * Gets the IDs of messages in this topic that the current user likes.
	 *
	 * @param int $topic The topic ID to fetch the info from.
	 * @return array IDs of messages in this topic that the current user likes.
	 */
	public function getLikedMsgs(): array
	{
		if (User::$me->is_guest) {
			return [];
		}

		$cache_key = 'likes_topic_' . $this->id . '_' . User::$me->id;
		$ttl = 180;

		if (($liked_messages = CacheApi::get($cache_key, $ttl)) === null) {
			$liked_messages = [];

			$request = Db::$db->query(
				'',
				'SELECT content_id
				FROM {db_prefix}user_likes AS l
					INNER JOIN {db_prefix}messages AS m ON (l.content_id = m.id_msg)
				WHERE l.id_member = {int:current_user}
					AND l.content_type = {literal:msg}
					AND m.id_topic = {int:topic}',
				[
					'current_user' => User::$me->id,
					'topic' => $this->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$liked_messages[] = (int) $row['content_id'];
			}
			Db::$db->free_result($request);

			CacheApi::put($cache_key, $liked_messages, $ttl);
		}

		return $liked_messages;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads information about a topic.
	 *
	 * @param ?int $id The ID number of a topic, or null for the current topic.
	 * @return object An instance of this class.
	 */
	public static function load(?int $id = null)
	{
		if (!isset($id)) {
			if (empty(self::$topic_id)) {
				ErrorHandler::fatalLang('not_a_topic', false, [], 404);
			}

			$id = self::$topic_id;
		}

		if (!isset(self::$loaded[$id])) {
			new self($id);

			self::$loaded[$id]->loadTopicInfo();

			if (!empty(self::$topic_id) && $id === self::$topic_id) {
				self::$info = self::$loaded[$id];
			}
		}

		return self::$loaded[$id];
	}

	/**
	 * Locks a topic... either by way of a moderator or the topic starter.
	 * What this does:
	 *  - locks a topic, toggles between locked/unlocked/admin locked.
	 *  - only admins can unlock topics locked by other admins.
	 *  - requires the lock_own or lock_any permission.
	 *  - logs the action to the moderator log.
	 *  - returns to the topic after it is done.
	 *  - it is accessed via ?action=lock.
	 */
	public static function lock(): void
	{
		// Just quit if there's no topic to lock.
		if (empty(self::$topic_id)) {
			ErrorHandler::fatalLang('not_a_topic', false);
		}

		User::$me->checkSession('get');

		// Find out who started the topic - in case User Topic Locking is enabled.
		$request = Db::$db->query(
			'',
			'SELECT id_member_started, locked
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => self::$topic_id,
			],
		);
		list($starter, $locked) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Can you lock topics here, mister?
		$user_lock = !User::$me->allowedTo('lock_any');

		if ($user_lock && $starter == User::$me->id) {
			User::$me->isAllowedTo('lock_own');
		} else {
			User::$me->isAllowedTo('lock_any');
		}

		// Another moderator got the job done first?
		if (isset($_GET['sa']) && $_GET['sa'] == 'unlock' && $locked == '0') {
			ErrorHandler::fatalLang('error_topic_locked_already', false);
		} elseif (isset($_GET['sa']) && $_GET['sa'] == 'lock' && ($locked == '1' || $locked == '2')) {
			ErrorHandler::fatalLang('error_topic_unlocked_already', false);
		}

		// Locking with high privileges.
		if ($locked == '0' && !$user_lock) {
			$locked = '1';
		}
		// Locking with low privileges.
		elseif ($locked == '0') {
			$locked = '2';
		}
		// Unlocking - make sure you don't unlock what you can't.
		elseif ($locked == '2' || ($locked == '1' && !$user_lock)) {
			$locked = '0';
		}
		// You cannot unlock this!
		else {
			ErrorHandler::fatalLang('locked_by_admin', 'user');
		}

		// Actually lock the topic in the database with the new value.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET locked = {int:locked}
			WHERE id_topic = {int:current_topic}',
			[
				'current_topic' => self::$topic_id,
				'locked' => $locked,
			],
		);

		// If they are allowed a "moderator" permission, log it in the moderator log.
		if (!$user_lock) {
			Logging::logAction($locked ? 'lock' : 'unlock', ['topic' => self::$topic_id, 'board' => Board::$info->id]);
		}

		// Notify people that this topic has been locked?
		Mail::sendNotifications(self::$topic_id, empty($locked) ? 'unlock' : 'lock');

		// Back to the topic!
		Utils::redirectexit('topic=' . self::$topic_id . '.' . $_REQUEST['start'] . ';moderate');
	}

	/**
	 * Sticky a topic.
	 * Can't be done by topic starters - that would be annoying!
	 * What this does:
	 *  - stickies a topic - toggles between sticky and normal.
	 *  - requires the make_sticky permission.
	 *  - adds an entry to the moderator log.
	 *  - when done, sends the user back to the topic.
	 *  - accessed via ?action=sticky.
	 */
	public static function sticky(): void
	{
		// Make sure the user can sticky it, and they are stickying *something*.
		User::$me->isAllowedTo('make_sticky');

		// You can't sticky a board or something!
		if (empty(self::$topic_id)) {
			ErrorHandler::fatalLang('not_a_topic', false);
		}

		User::$me->checkSession('get');

		// Is this topic already stickied, or no?
		$request = Db::$db->query(
			'',
			'SELECT is_sticky
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => self::$topic_id,
			],
		);
		list($is_sticky) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Another moderator got the job done first?
		if (isset($_GET['sa']) && $_GET['sa'] == 'nonsticky' && $is_sticky == '0') {
			ErrorHandler::fatalLang('error_topic_nonsticky_already', false);
		} elseif (isset($_GET['sa']) && $_GET['sa'] == 'sticky' && $is_sticky == '1') {
			ErrorHandler::fatalLang('error_topic_sticky_already', false);
		}

		// Toggle the sticky value.... pretty simple ;).
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET is_sticky = {int:is_sticky}
			WHERE id_topic = {int:current_topic}',
			[
				'current_topic' => self::$topic_id,
				'is_sticky' => empty($is_sticky) ? 1 : 0,
			],
		);

		// Log this sticky action - always a moderator thing.
		Logging::logAction(empty($is_sticky) ? 'sticky' : 'unsticky', ['topic' => self::$topic_id, 'board' => Board::$info->id]);

		// Notify people that this topic has been stickied?
		if (empty($is_sticky)) {
			Mail::sendNotifications(self::$topic_id, 'sticky');
		}

		// Take them back to the now stickied topic.
		Utils::redirectexit('topic=' . self::$topic_id . '.' . $_REQUEST['start'] . ';moderate');
	}

	/**
	 * Approves or unapproves topics.
	 *
	 * @param array $topics Array of topic ids.
	 * @param bool $approve Whether to approve the topics. If false, unapproves them instead.
	 * @return bool Whether the operation was successful.
	 */
	public static function approve($topics, $approve = true)
	{
		if (!is_array($topics)) {
			$topics = [$topics];
		}

		if (empty($topics)) {
			return false;
		}

		$approve_type = $approve ? 0 : 1;

		// Just get the messages to be approved and pass through...
		$request = Db::$db->query(
			'',
			'SELECT id_first_msg
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topic_list})
				AND approved = {int:approve_type}',
			[
				'topic_list' => $topics,
				'approve_type' => $approve_type,
			],
		);
		$msgs = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$msgs[] = $row['id_first_msg'];
		}
		Db::$db->free_result($request);

		return Msg::approve($msgs, $approve);
	}

	/**
	 * Moves one or more topics to a specific board. (doesn't check permissions.)
	 * Determines the source boards for the supplied topics
	 * Handles the moving of mark_read data
	 * Updates the posts count of the affected boards
	 *
	 * @param array|int $topics The ID of a single topic to move or an array containing the IDs of multiple topics to move
	 * @param int $toBoard The ID of the board to move the topics to
	 */
	public static function move($topics, $toBoard)
	{
		// Empty array?
		if (empty($topics)) {
			return;
		}

		// Only a single topic.
		if (is_numeric($topics)) {
			$topics = [$topics];
		}

		$fromBoards = [];

		// Destination board empty or equal to 0?
		if (empty($toBoard)) {
			return;
		}

		// Are we moving to the recycle board?
		$isRecycleDest = !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $toBoard;

		// Callback for search APIs to do their thing
		$searchAPI = SearchApi::load();

		if ($searchAPI->supportsMethod('topicsMoved')) {
			$searchAPI->topicsMoved($topics, $toBoard);
		}

		// Determine the source boards...
		$request = Db::$db->query(
			'',
			'SELECT id_board, approved, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
				SUM(num_replies) AS num_replies
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_board, approved',
			[
				'topics' => $topics,
			],
		);

		// Num of rows = 0 -> no topics found. Num of rows > 1 -> topics are on multiple boards.
		if (Db::$db->num_rows($request) == 0) {
			return;
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset($fromBoards[$row['id_board']]['num_posts'])) {
				$fromBoards[$row['id_board']] = [
					'num_posts' => 0,
					'num_topics' => 0,
					'unapproved_posts' => 0,
					'unapproved_topics' => 0,
					'id_board' => $row['id_board'],
				];
			}
			// Posts = (num_replies + 1) for each approved topic.
			$fromBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
			$fromBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

			// Add the topics to the right type.
			if ($row['approved']) {
				$fromBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
			} else {
				$fromBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
			}
		}
		Db::$db->free_result($request);

		// Move over the mark_read data. (because it may be read and now not by some!)
		$SaveAServer = max(0, Config::$modSettings['maxMsgID'] - 50000);
		$request = Db::$db->query(
			'',
			'SELECT lmr.id_member, lmr.id_msg, t.id_topic, COALESCE(lt.unwatched, 0) AS unwatched
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board
					AND lmr.id_msg > t.id_first_msg AND lmr.id_msg > {int:protect_lmr_msg})
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = lmr.id_member)
			WHERE t.id_topic IN ({array_int:topics})
				AND lmr.id_msg > COALESCE(lt.id_msg, 0)',
			[
				'protect_lmr_msg' => $SaveAServer,
				'topics' => $topics,
			],
		);
		$log_topics = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$log_topics[] = [$row['id_topic'], $row['id_member'], $row['id_msg'], (is_null($row['unwatched']) ? 0 : $row['unwatched'])];

			// Prevent queries from getting too big. Taking some steam off.
			if (count($log_topics) > 500) {
				Db::$db->insert(
					'replace',
					'{db_prefix}log_topics',
					['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
					$log_topics,
					['id_topic', 'id_member'],
				);

				$log_topics = [];
			}
		}
		Db::$db->free_result($request);

		// Now that we have all the topics that *should* be marked read, and by which members...
		if (!empty($log_topics)) {
			// Insert that information into the database!
			Db::$db->insert(
				'replace',
				'{db_prefix}log_topics',
				['id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'],
				$log_topics,
				['id_topic', 'id_member'],
			);
		}

		// Update the number of posts on each board.
		$totalTopics = 0;
		$totalPosts = 0;
		$totalUnapprovedTopics = 0;
		$totalUnapprovedPosts = 0;

		foreach ($fromBoards as $stats) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET
					num_posts = CASE WHEN {int:num_posts} > num_posts THEN 0 ELSE num_posts - {int:num_posts} END,
					num_topics = CASE WHEN {int:num_topics} > num_topics THEN 0 ELSE num_topics - {int:num_topics} END,
					unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END,
					unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END
				WHERE id_board = {int:id_board}',
				[
					'id_board' => $stats['id_board'],
					'num_posts' => $stats['num_posts'],
					'num_topics' => $stats['num_topics'],
					'unapproved_posts' => $stats['unapproved_posts'],
					'unapproved_topics' => $stats['unapproved_topics'],
				],
			);
			$totalTopics += $stats['num_topics'];
			$totalPosts += $stats['num_posts'];
			$totalUnapprovedTopics += $stats['unapproved_topics'];
			$totalUnapprovedPosts += $stats['unapproved_posts'];
		}
		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET
				num_topics = num_topics + {int:total_topics},
				num_posts = num_posts + {int:total_posts},' . ($isRecycleDest ? '
				unapproved_posts = {int:no_unapproved}, unapproved_topics = {int:no_unapproved}' : '
				unapproved_posts = unapproved_posts + {int:total_unapproved_posts},
				unapproved_topics = unapproved_topics + {int:total_unapproved_topics}') . '
			WHERE id_board = {int:id_board}',
			[
				'id_board' => $toBoard,
				'total_topics' => $totalTopics,
				'total_posts' => $totalPosts,
				'total_unapproved_topics' => $totalUnapprovedTopics,
				'total_unapproved_posts' => $totalUnapprovedPosts,
				'no_unapproved' => 0,
			],
		);

		// Move the topic.  Done.  :P
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_board = {int:id_board}' . ($isRecycleDest ? ',
				unapproved_posts = {int:no_unapproved}, approved = {int:is_approved}' : '') . '
			WHERE id_topic IN ({array_int:topics})',
			[
				'id_board' => $toBoard,
				'topics' => $topics,
				'is_approved' => 1,
				'no_unapproved' => 0,
			],
		);

		// If this was going to the recycle bin, check what messages are being recycled, and remove them from the queue.
		if ($isRecycleDest && ($totalUnapprovedTopics || $totalUnapprovedPosts)) {
			$request = Db::$db->query(
				'',
				'SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topics})
					AND approved = {int:not_approved}',
				[
					'topics' => $topics,
					'not_approved' => 0,
				],
			);
			$approval_msgs = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$approval_msgs[] = $row['id_msg'];
			}

			Db::$db->free_result($request);

			// Empty the approval queue for these, as we're going to approve them next.
			if (!empty($approval_msgs)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}approval_queue
					WHERE id_msg IN ({array_int:message_list})
						AND id_attach = {int:id_attach}',
					[
						'message_list' => $approval_msgs,
						'id_attach' => 0,
					],
				);
			}

			// Get all the current max and mins.
			$request = Db::$db->query(
				'',
				'SELECT id_topic, id_first_msg, id_last_msg
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:topics})',
				[
					'topics' => $topics,
				],
			);
			$topicMaxMin = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$topicMaxMin[$row['id_topic']] = [
					'min' => $row['id_first_msg'],
					'max' => $row['id_last_msg'],
				];
			}
			Db::$db->free_result($request);

			// Check the MAX and MIN are correct.
			$request = Db::$db->query(
				'',
				'SELECT id_topic, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topics})
				GROUP BY id_topic',
				[
					'topics' => $topics,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				// If not, update.
				if ($row['first_msg'] != $topicMaxMin[$row['id_topic']]['min'] || $row['last_msg'] != $topicMaxMin[$row['id_topic']]['max']) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}topics
						SET id_first_msg = {int:first_msg}, id_last_msg = {int:last_msg}
						WHERE id_topic = {int:selected_topic}',
						[
							'first_msg' => $row['first_msg'],
							'last_msg' => $row['last_msg'],
							'selected_topic' => $row['id_topic'],
						],
					);
				}
			}
			Db::$db->free_result($request);
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_board = {int:id_board}' . ($isRecycleDest ? ',approved = {int:is_approved}' : '') . '
			WHERE id_topic IN ({array_int:topics})',
			[
				'id_board' => $toBoard,
				'topics' => $topics,
				'is_approved' => 1,
			],
		);
		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_reported
			SET id_board = {int:id_board}
			WHERE id_topic IN ({array_int:topics})',
			[
				'id_board' => $toBoard,
				'topics' => $topics,
			],
		);
		Db::$db->query(
			'',
			'UPDATE {db_prefix}calendar
			SET id_board = {int:id_board}
			WHERE id_topic IN ({array_int:topics})',
			[
				'id_board' => $toBoard,
				'topics' => $topics,
			],
		);

		// Mark target board as seen, if it was already marked as seen before.
		$request = Db::$db->query(
			'',
			'SELECT (COALESCE(lb.id_msg, 0) >= b.id_msg_updated) AS isSeen
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
			WHERE b.id_board = {int:id_board}',
			[
				'current_member' => User::$me->id,
				'id_board' => $toBoard,
			],
		);
		list($isSeen) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (!empty($isSeen) && !User::$me->is_guest) {
			Db::$db->insert(
				'replace',
				'{db_prefix}log_boards',
				['id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'],
				[$toBoard, User::$me->id, Config::$modSettings['maxMsgID']],
				['id_board', 'id_member'],
			);
		}

		// Update the cache?
		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 3) {
			foreach ($topics as $topic_id) {
				CacheApi::put('topic_board-' . $topic_id, null, 120);
			}
		}

		$updates = array_keys($fromBoards);
		$updates[] = $toBoard;

		Msg::updateLastMessages(array_unique($updates));

		// Update 'em pesky stats.
		Logging::updateStats('topic');
		Logging::updateStats('message');
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);
	}

	/**
	 * Removes the passed id_topic's. (permissions are NOT checked here!).
	 *
	 * @param array|int $topics The topics to remove (can be an id or an array of ids).
	 * @param bool $decreasePostCount Whether to decrease the users' post counts
	 * @param bool $ignoreRecycling Whether to ignore recycling board settings
	 * @param bool $updateBoardCount Whether to adjust topic counts for the boards
	 */
	public static function remove($topics, $decreasePostCount = true, $ignoreRecycling = false, $updateBoardCount = true)
	{
		// Nothing to do?
		if (empty($topics)) {
			return;
		}

		// Only a single topic.
		if (is_numeric($topics)) {
			$topics = [$topics];
		}

		$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;

		// Do something before?
		IntegrationHook::call('integrate_remove_topics_before', [$topics, $recycle_board]);

		// Decrease the post counts.
		if ($decreasePostCount) {
			$requestMembers = Db::$db->query(
				'',
				'SELECT m.id_member, COUNT(*) AS posts
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				WHERE m.id_topic IN ({array_int:topics})' . (!empty($recycle_board) ? '
					AND m.id_board != {int:recycled_board}' : '') . '
					AND b.count_posts = {int:do_count_posts}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_member',
				[
					'do_count_posts' => 0,
					'recycled_board' => $recycle_board,
					'topics' => $topics,
					'is_approved' => 1,
				],
			);

			if (Db::$db->num_rows($requestMembers) > 0) {
				while ($rowMembers = Db::$db->fetch_assoc($requestMembers)) {
					User::updateMemberData($rowMembers['id_member'], ['posts' => 'posts - ' . $rowMembers['posts']]);
				}
			}
			Db::$db->free_result($requestMembers);
		}

		// Recycle topics that aren't in the recycle board...
		if (!empty($recycle_board) && !$ignoreRecycling) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic, id_board, unapproved_posts, approved
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:topics})
					AND id_board != {int:recycle_board}
				LIMIT {int:limit}',
				[
					'recycle_board' => $recycle_board,
					'topics' => $topics,
					'limit' => count($topics),
				],
			);

			if (Db::$db->num_rows($request) > 0) {
				// Get topics that will be recycled.
				$recycleTopics = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					if (function_exists('apache_reset_timeout')) {
						@apache_reset_timeout();
					}

					$recycleTopics[] = $row['id_topic'];

					// Set the id_previous_board for this topic - and make it not sticky.
					Db::$db->query(
						'',
						'UPDATE {db_prefix}topics
						SET id_previous_board = {int:id_previous_board}, is_sticky = {int:not_sticky}
						WHERE id_topic = {int:id_topic}',
						[
							'id_previous_board' => $row['id_board'],
							'id_topic' => $row['id_topic'],
							'not_sticky' => 0,
						],
					);
				}
				Db::$db->free_result($request);

				// Move the topics to the recycle board.
				self::move($recycleTopics, Config::$modSettings['recycle_board']);

				// Close reports that are being recycled.
				require_once Config::$sourcedir . '/Actions/Moderation/Main.php';

				Db::$db->query(
					'',
					'UPDATE {db_prefix}log_reported
					SET closed = {int:is_closed}
					WHERE id_topic IN ({array_int:recycle_topics})',
					[
						'recycle_topics' => $recycleTopics,
						'is_closed' => 1,
					],
				);

				Config::updateModSettings(['last_mod_report_action' => time()]);

				ReportedContent::recountOpenReports('posts');

				// Topics that were recycled don't need to be deleted, so subtract them.
				$topics = array_diff($topics, $recycleTopics);
			} else {
				Db::$db->free_result($request);
			}
		}

		// Still topics left to delete?
		if (empty($topics)) {
			return;
		}

		// Callback for search APIs to do their thing
		$searchAPI = SearchApi::load();

		if ($searchAPI->supportsMethod('topicsRemoved')) {
			$searchAPI->topicsRemoved($topics);
		}

		$adjustBoards = [];

		// Find out how many posts we are deleting.
		$request = Db::$db->query(
			'',
			'SELECT id_board, approved, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
				SUM(num_replies) AS num_replies
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_board, approved',
			[
				'topics' => $topics,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset($adjustBoards[$row['id_board']]['num_posts'])) {
				$adjustBoards[$row['id_board']] = [
					'num_posts' => 0,
					'num_topics' => 0,
					'unapproved_posts' => 0,
					'unapproved_topics' => 0,
					'id_board' => $row['id_board'],
				];
			}
			// Posts = (num_replies + 1) for each approved topic.
			$adjustBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
			$adjustBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

			// Add the topics to the right type.
			if ($row['approved']) {
				$adjustBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
			} else {
				$adjustBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
			}
		}
		Db::$db->free_result($request);

		if ($updateBoardCount) {
			// Decrease the posts/topics...
			foreach ($adjustBoards as $stats) {
				if (function_exists('apache_reset_timeout')) {
					@apache_reset_timeout();
				}

				Db::$db->query(
					'',
					'UPDATE {db_prefix}boards
					SET
						num_posts = CASE WHEN {int:num_posts} > num_posts THEN 0 ELSE num_posts - {int:num_posts} END,
						num_topics = CASE WHEN {int:num_topics} > num_topics THEN 0 ELSE num_topics - {int:num_topics} END,
						unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END,
						unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END
					WHERE id_board = {int:id_board}',
					[
						'id_board' => $stats['id_board'],
						'num_posts' => $stats['num_posts'],
						'num_topics' => $stats['num_topics'],
						'unapproved_posts' => $stats['unapproved_posts'],
						'unapproved_topics' => $stats['unapproved_topics'],
					],
				);
			}
		}
		// Remove Polls.
		$request = Db::$db->query(
			'',
			'SELECT id_poll
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})
				AND id_poll > {int:no_poll}
			LIMIT {int:limit}',
			[
				'no_poll' => 0,
				'topics' => $topics,
				'limit' => count($topics),
			],
		);
		$polls = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$polls[] = $row['id_poll'];
		}
		Db::$db->free_result($request);

		if (!empty($polls)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}polls
				WHERE id_poll IN ({array_int:polls})',
				[
					'polls' => $polls,
				],
			);
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}poll_choices
				WHERE id_poll IN ({array_int:polls})',
				[
					'polls' => $polls,
				],
			);
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_polls
				WHERE id_poll IN ({array_int:polls})',
				[
					'polls' => $polls,
				],
			);
		}

		// Get rid of the attachment, if it exists.
		$attachmentQuery = [
			'attachment_type' => 0,
			'id_topic' => $topics,
		];
		Attachment::remove($attachmentQuery, 'messages');

		// Delete possible search index entries.
		if (!empty(Config::$modSettings['search_custom_index_config'])) {
			$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

			$words = [];
			$messages = [];
			$request = Db::$db->query(
				'',
				'SELECT id_msg, body
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topics})',
				[
					'topics' => $topics,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (function_exists('apache_reset_timeout')) {
					@apache_reset_timeout();
				}

				$words = array_merge($words, Utils::text2words($row['body'], $customIndexSettings['bytes_per_word'], true));
				$messages[] = $row['id_msg'];
			}
			Db::$db->free_result($request);
			$words = array_unique($words);

			if (!empty($words) && !empty($messages)) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_search_words
					WHERE id_word IN ({array_int:word_list})
						AND id_msg IN ({array_int:message_list})',
					[
						'word_list' => $words,
						'message_list' => $messages,
					],
				);
			}
		}

		// Delete anything related to the topic.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}calendar
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);

		// Maybe there's a mod that wants to delete topic related data of its own
		IntegrationHook::call('integrate_remove_topics', [$topics]);

		// Update the totals...
		Logging::updateStats('message');
		Logging::updateStats('topic');
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		$updates = [];

		foreach ($adjustBoards as $stats) {
			$updates[] = $stats['id_board'];
		}
		Msg::updateLastMessages($updates);
	}

	/**
	 * Backward compatibility wrapper for the getLikedMsgs method.
	 *
	 * @param int $topic The topic ID to fetch the info from.
	 * @return array An array of IDs of messages in the specified topic that the current user likes
	 */
	public static function prepareLikesContext(int $topic): array
	{
		return self::load($topic)->getLikedMsgs();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Loads primary information about this topic.
	 */
	protected function loadTopicInfo(): void
	{
		if (empty($this->id)) {
			ErrorHandler::fatalLang('not_a_topic', false, [], 404);
		}

		// Basic stuff we always want.
		$topic_selects = [
			't.*',
			'ms.subject',
			'ms.poster_time AS started_timestamp',
			'COALESCE(mem.real_name, ms.poster_name) AS started_name',
			'ml.poster_time AS updated_timestamp',
			'COALESCE(meml.real_name, ml.poster_name) AS updated_name',
		];
		$topic_joins = [
			'INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
			'INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)',
			'LEFT JOIN {db_prefix}members AS mem on (mem.id_member = t.id_member_started)',
			'LEFT JOIN {db_prefix}members AS meml on (meml.id_member = t.id_member_updated)',
		];
		$topic_parameters = [
			'current_topic' => $this->id,
			'current_member' => User::$me->id ?? 0,
		];

		// What's new to this user?
		if (User::$me->is_guest) {
			$topic_selects[] = 't.id_last_msg + 1 AS new_from';
		} else {
			$topic_selects[] = 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from';
			$topic_selects[] = 'COALESCE(lt.unwatched, 0) as unwatched';

			$topic_joins[] = 'LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})';
			$topic_joins[] = 'LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})';
		}

		IntegrationHook::call('integrate_display_topic', [&$topic_selects, &$topic_joins, &$topic_parameters]);

		// @todo Why isn't this cached?
		// @todo if we get id_board in this query and cache it, we can save a query on posting
		// Get all the important topic info.
		$request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $topic_selects) . '
			FROM {db_prefix}topics AS t
				' . implode("\n\t\t\t\t", $topic_joins) . '
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			$topic_parameters,
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('not_a_topic', false, [], 404);
		}
		$this->set(Db::$db->fetch_assoc($request));
		Db::$db->free_result($request);

		// Censor the title...
		Lang::censorText($this->subject);

		// A few tweaks and extras.
		$this->started_time = Time::create('@' . $this->started_timestamp)->format();
		$this->unwatched = $this->unwatched ?? 0;
		$this->is_poll = (int) ($this->id_poll > 0 && Config::$modSettings['pollMode'] == '1' && User::$me->allowedTo('poll_view'));

		$this->real_num_replies = $this->num_replies + (Config::$modSettings['postmod_active'] && User::$me->allowedTo('approve_posts') ? $this->unapproved_posts - ($this->is_approved ? 0 : 1) : 0);

		// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
		if (Config::$modSettings['postmod_active'] && $this->unapproved_posts && !User::$me->is_guest && !User::$me->allowedTo('approve_posts')) {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(id_member) AS my_unapproved_posts
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_member = {int:current_member}
					AND approved = 0',
				[
					'current_topic' => Topic::$topic_id,
					'current_member' => User::$me->id,
				],
			);
			list($myUnapprovedPosts) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$this->total_visible_posts = $this->num_replies + $myUnapprovedPosts + ($this->is_approved ? 1 : 0);
		} elseif (User::$me->is_guest) {
			$this->total_visible_posts = $this->num_replies + ($this->is_approved ? 1 : 0);
		} else {
			$this->total_visible_posts = $this->num_replies + $this->unapproved_posts + ($this->is_approved ? 1 : 0);
		}

		// Did this user start the topic or not?
		User::$me->started = User::$me->id == $this->id_member_started && !User::$me->is_guest;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Topic::exportStatic')) {
	Topic::exportStatic();
}

?>