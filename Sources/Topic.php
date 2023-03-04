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

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * Represents a topic.
 *
 * This class's static methods also takes care of certain actions on topics:
 * lock/unlock a topic, sticky/unsticky it, etc.
 */
class Topic implements \ArrayAccess
{
	use BackwardCompatibility, ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'lock' => 'LockTopic',
		),
		'prop_names' => array(
			'topic_id' => 'topic',
		),
	);

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
	public array $notify_prefs = array();

	/**
	 * @var array
	 *
	 * Contextual permissions that the current user has in this topic.
	 *
	 * "Contextual" here means "suitable for use in Utils::$context."
	 * Examples include can_move, can_lock, etc.
	 */
	public array $permissions = array();

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
	 * @var int
	 *
	 * Instance of this class for the requested topic.
	 */
	public static $info;

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static $loaded = array();

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = array(
		'id_topic' => 'id',
		'locked' => 'is_locked',
		'approved' => 'is_approved',
		'topic_started_name' => 'started_name',
		'topic_started_time' => 'started_time',
	);

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Common permissions to check for this topic.
	 * Used by Topic::doPermissions();
	 */
	protected static array $common_permissions = array(
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
	);

	/**
	 * @var array
	 *
	 * Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
	 * Used by Topic::doPermissions();
	 */
	protected static array $anyown_permissions = array(
		'can_move' => 'move',
		'can_lock' => 'lock',
		'can_delete' => 'remove',
		'can_add_poll' => 'poll_add',
		'can_remove_poll' => 'poll_remove',
		'can_reply' => 'post_reply',
		'can_reply_unapproved' => 'post_unapproved_replies',
	);

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
	public function __construct(int $id, array $props = array())
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
		if ($prop === 'num_replies' && !isset($this->real_num_replies))
		{
			$this->real_num_replies = $value;
		}
		elseif ($prop === 'real_num_replies' && !isset($this->num_replies))
		{
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
		global $options;

		if (!empty($this->permissions))
			return $this->permissions;

		foreach (self::$common_permissions as $contextual => $perm)
			$this->permissions[$contextual] = allowedTo($perm);

		foreach (self::$anyown_permissions as $contextual => $perm)
		{
			$this->permissions[$contextual] = allowedTo($perm . '_any') || (User::$me->started && allowedTo($perm . '_own'));
		}

		if (!User::$me->is_admin && $this->permissions['can_move'] && !Config::$modSettings['topic_move_any'])
		{
			// We'll use this in a minute
			$boards_allowed = array_diff(boardsAllowedTo('post_new'), array(Board::$info->id));

			// You can't move this unless you have permission to start new topics on at least one other board.
			$this->permissions['can_move'] = count($boards_allowed) > 1;
		}

		// If a topic is locked, you can't remove it unless it's yours and you locked it or you can lock_any
		if ($this->is_locked)
		{
			$this->permissions['can_delete'] &= (($this->is_locked == 1 && User::$me->started) || allowedTo('lock_any'));
		}

		// Cleanup all the permissions with extra stuff...
		$this->permissions['can_mark_notify'] = !User::$me->is_guest;
		$this->permissions['calendar_post'] &= !empty(Config::$modSettings['cal_enabled']) && (allowedTo('modify_any') || (allowedTo('modify_own') && User::$me->started));
		$this->permissions['can_add_poll'] &= Config::$modSettings['pollMode'] == '1' && $this->id_poll <= 0;
		$this->permissions['can_remove_poll'] &= Config::$modSettings['pollMode'] == '1' && $this->id_poll > 0;
		$this->permissions['can_reply'] &= empty($this->is_locked) || allowedTo('moderate_board');
		$this->permissions['can_reply_unapproved'] &= Config::$modSettings['postmod_active'] && (empty($this->is_locked) || allowedTo('moderate_board'));
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
		$this->permissions['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && User::$me->started);

		// Can restore topic?  That's if the topic is in the recycle board and has a previous restore state.
		$this->permissions['can_restore_topic'] &= !empty(Board::$info->recycle) && !empty($this->id_previous_board);
		$this->permissions['can_restore_msg'] &= !empty(Board::$info->recycle) && !empty($this->id_previous_topic);

		// Check whether the draft functions are enabled and that they have permission to use them (for quick reply.)
		$this->permissions['drafts_save'] = !empty(Config::$modSettings['drafts_post_enabled']) && allowedTo('post_draft') && $this->permissions['can_reply'];
		$this->permissions['drafts_autosave'] = !empty($this->permissions['drafts_save']) && !empty(Config::$modSettings['drafts_autosave_enabled']) && !empty($options['drafts_autosave_enabled']);

		// They can't link an existing topic to the calendar unless they can modify the first post...
		$this->permissions['calendar_post'] &= allowedTo('modify_any') || (allowedTo('modify_own') && User::$me->started);

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
		if (!empty(User::$me->id))
		{
			require_once(Config::$sourcedir . '/Subs-Notify.php');

			$prefs = getNotifyPrefs(User::$me->id, array('topic_notify', 'topic_notify_' . $this->id, 'msg_auto_notify'), true);

			// Only pay attention to Utils::$context['is_marked_notify'] if it is set.
			$pref = !empty($prefs[User::$me->id]) && (!isset(Utils::$context['is_marked_notify']) || Utils::$context['is_marked_notify'] == true) ? $prefs[User::$me->id] : array();

			$this->notify_prefs = array(
				'is_custom' => isset($pref['topic_notify_' . $this->id]),
				'pref' => $pref['topic_notify_' . $this->id] ?? ($pref['topic_notify'] ?? 0),
				'msg_auto_notify' => $pref['msg_auto_notify'] ?? false,
			);
		}

		return $this->notify_prefs;
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
		if (!isset($id))
		{
			if (empty(self::$topic_id))
				fatal_lang_error('not_a_topic', false, 404);

			$id = self::$topic_id;
		}

		if (!isset(self::$loaded[$id]))
		{
			new self($id);

			self::$loaded[$id]->loadTopicInfo();

			if (!empty(self::$topic_id) && $id === self::$topic_id)
				self::$info = self::$loaded[$id];
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
		if (empty(self::$topic_id))
			fatal_lang_error('not_a_topic', false);

		checkSession('get');

		// Get Msg.php for sendNotifications.
		require_once(Config::$sourcedir . '/Msg.php');

		// Find out who started the topic - in case User Topic Locking is enabled.
		$request = Db::$db->query('', '
			SELECT id_member_started, locked
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => self::$topic_id,
			)
		);
		list($starter, $locked) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Can you lock topics here, mister?
		$user_lock = !allowedTo('lock_any');

		if ($user_lock && $starter == User::$me->id)
		{
			isAllowedTo('lock_own');
		}
		else
		{
			isAllowedTo('lock_any');
		}

		// Another moderator got the job done first?
		if (isset($_GET['sa']) && $_GET['sa'] == 'unlock' && $locked == '0')
		{
			fatal_lang_error('error_topic_locked_already', false);
		}
		elseif (isset($_GET['sa']) && $_GET['sa'] == 'lock' && ($locked == '1' || $locked == '2'))
		{
			fatal_lang_error('error_topic_unlocked_already', false);
		}

		// Locking with high privileges.
		if ($locked == '0' && !$user_lock)
		{
			$locked = '1';
		}
		// Locking with low privileges.
		elseif ($locked == '0')
		{
			$locked = '2';
		}
		// Unlocking - make sure you don't unlock what you can't.
		elseif ($locked == '2' || ($locked == '1' && !$user_lock))
		{
			$locked = '0';
		}
		// You cannot unlock this!
		else
		{
			fatal_lang_error('locked_by_admin', 'user');
		}

		// Actually lock the topic in the database with the new value.
		Db::$db->query('', '
			UPDATE {db_prefix}topics
			SET locked = {int:locked}
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => self::$topic_id,
				'locked' => $locked,
			)
		);

		// If they are allowed a "moderator" permission, log it in the moderator log.
		if (!$user_lock)
		{
			logAction($locked ? 'lock' : 'unlock', array('topic' => self::$topic_id, 'board' => Board::$info->id));
		}

		// Notify people that this topic has been locked?
		sendNotifications(self::$topic_id, empty($locked) ? 'unlock' : 'lock');

		// Back to the topic!
		redirectexit('topic=' . self::$topic_id . '.' . $_REQUEST['start'] . ';moderate');
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
		isAllowedTo('make_sticky');

		// You can't sticky a board or something!
		if (empty(self::$topic_id))
			fatal_lang_error('not_a_topic', false);

		checkSession('get');

		// We need Msg.php for the sendNotifications() function.
		require_once(Config::$sourcedir . '/Msg.php');

		// Is this topic already stickied, or no?
		$request = Db::$db->query('', '
			SELECT is_sticky
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => self::$topic_id,
			)
		);
		list($is_sticky) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Another moderator got the job done first?
		if (isset($_GET['sa']) && $_GET['sa'] == 'nonsticky' && $is_sticky == '0')
		{
			fatal_lang_error('error_topic_nonsticky_already', false);
		}
		elseif (isset($_GET['sa']) && $_GET['sa'] == 'sticky' && $is_sticky == '1')
		{
			fatal_lang_error('error_topic_sticky_already', false);
		}

		// Toggle the sticky value.... pretty simple ;).
		Db::$db->query('', '
			UPDATE {db_prefix}topics
			SET is_sticky = {int:is_sticky}
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => self::$topic_id,
				'is_sticky' => empty($is_sticky) ? 1 : 0,
			)
		);

		// Log this sticky action - always a moderator thing.
		logAction(empty($is_sticky) ? 'sticky' : 'unsticky', array('topic' => self::$topic_id, 'board' => Board::$info->id));

		// Notify people that this topic has been stickied?
		if (empty($is_sticky))
			sendNotifications(self::$topic_id, 'sticky');

		// Take them back to the now stickied topic.
		redirectexit('topic=' . self::$topic_id . '.' . $_REQUEST['start'] . ';moderate');
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Loads primary information about this topic.
	 */
	protected function loadTopicInfo(): void
	{
		if (empty($this->id))
			fatal_lang_error('not_a_topic', false, 404);

		// Basic stuff we always want.
		$topic_selects = array(
			't.*',
			'ms.subject',
			'ms.poster_time AS started_timestamp',
			'COALESCE(mem.real_name, ms.poster_name) AS started_name',
			'ml.poster_time AS updated_timestamp',
			'COALESCE(meml.real_name, ml.poster_name) AS updated_name',
		);
		$topic_joins = array(
			'INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
			'INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)',
			'LEFT JOIN {db_prefix}members AS mem on (mem.id_member = t.id_member_started)',
			'LEFT JOIN {db_prefix}members AS meml on (meml.id_member = t.id_member_updated)',
		);
		$topic_parameters = array(
			'current_topic' => $this->id,
			'current_member' => User::$me->id ?? 0,
		);

		// What's new to this user?
		if (User::$me->is_guest)
		{
			$topic_selects[] = 't.id_last_msg + 1 AS new_from';
		}
		else
		{
			$topic_selects[] = 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from';
			$topic_selects[] = 'COALESCE(lt.unwatched, 0) as unwatched';

			$topic_joins[] = 'LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})';
			$topic_joins[] = 'LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})';
		}

		call_integration_hook('integrate_display_topic', array(&$topic_selects, &$topic_joins, &$topic_parameters));

		// @todo Why isn't this cached?
		// @todo if we get id_board in this query and cache it, we can save a query on posting
		// Get all the important topic info.
		$request = Db::$db->query('', '
			SELECT
				' . implode(', ', $topic_selects) . '
			FROM {db_prefix}topics AS t
				' . implode("\n\t\t\t\t", $topic_joins) . '
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			$topic_parameters
		);
		if (Db::$db->num_rows($request) == 0)
		{
			fatal_lang_error('not_a_topic', false, 404);
		}
		$this->set(Db::$db->fetch_assoc($request));
		Db::$db->free_result($request);

		// Censor the title...
		Lang::censorText($this->subject);

		// A few tweaks and extras.
		$this->started_time = timeformat($this->started_timestamp);
		$this->unwatched = $this->unwatched ?? 0;
		$this->is_poll = (int) ($this->id_poll > 0 && Config::$modSettings['pollMode'] == '1' && allowedTo('poll_view'));

		$this->real_num_replies = $this->num_replies + (Config::$modSettings['postmod_active'] && allowedTo('approve_posts') ? $this->unapproved_posts - ($this->is_approved ? 0 : 1) : 0);

		// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
		if (Config::$modSettings['postmod_active'] && $this->unapproved_posts && !User::$me->is_guest && !allowedTo('approve_posts'))
		{
			$request = Db::$db->query('', '
				SELECT COUNT(id_member) AS my_unapproved_posts
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_member = {int:current_member}
					AND approved = 0',
				array(
					'current_topic' => Topic::$topic_id,
					'current_member' => User::$me->id,
				)
			);
			list($myUnapprovedPosts) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$this->total_visible_posts = $this->num_replies + $myUnapprovedPosts + ($this->is_approved ? 1 : 0);
		}
		elseif (User::$me->is_guest)
		{
			$this->total_visible_posts = $this->num_replies + ($this->is_approved ? 1 : 0);
		}
		else
		{
			$this->total_visible_posts = $this->num_replies + $this->unapproved_posts + ($this->is_approved ? 1 : 0);
		}

		// Did this user start the topic or not?
		User::$me->started = User::$me->id == $this->id_member_started && !User::$me->is_guest;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Topic::exportStatic'))
	Topic::exportStatic();

?>