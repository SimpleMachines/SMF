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
use SMF\Logging;
use SMF\Msg;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Handles quick moderation actions from within a topic.
 *
 * Only deals with actions that work on individual messages, such as deleting,
 * restoring, and spliting into a new topic.
 */
class QuickModerationInTopic implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'QuickInTopicModeration',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * IDs of the messages to act on.
	 */
	public array $messages = [];

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
	 * Do the job.
	 */
	public function execute(): void
	{
		// Check the session = get or post.
		User::$me->checkSession('request');

		if (isset($_REQUEST['restore_selected'])) {
			$this->restore();
		} elseif (isset($_REQUEST['split_selection'])) {
			$this->split();
		} else {
			$this->delete();
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->messages = array_map('intval', $_REQUEST['msgs']);
	}

	/**
	 * Redirects to restortopic action,
	 */
	protected function restore()
	{
		Utils::redirectexit('action=restoretopic;msgs=' . implode(',', $this->messages) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
	}

	/**
	 * Looks up some info, then redirects to the splittopics action.
	 */
	protected function split()
	{
		$request = Db::$db->query(
			'',
			'SELECT subject
			FROM {db_prefix}messages
			WHERE id_msg = {int:message}
			LIMIT 1',
			[
				'message' => min($this->messages),
			],
		);
		list($subname) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$_SESSION['split_selection'][Topic::$topic_id] = $this->messages;

		Utils::redirectexit('action=splittopics;sa=selectTopics;topic=' . Topic::$topic_id . '.0;subname_enc=' . urlencode($subname) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
	}

	/**
	 * Deletes messages.
	 *
	 * Performs permissions checks before doing anything.
	 */
	protected function delete()
	{
		// Allowed to delete any message?
		if (User::$me->allowedTo('delete_any')) {
			$allowed_all = true;
		}
		// Allowed to delete replies to their messages?
		elseif (User::$me->allowedTo('delete_replies')) {
			$request = Db::$db->query(
				'',
				'SELECT id_member_started
				FROM {db_prefix}topics
				WHERE id_topic = {int:current_topic}
				LIMIT 1',
				[
					'current_topic' => Topic::$topic_id,
				],
			);
			list($starter) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$allowed_all = $starter == User::$me->id;
		} else {
			$allowed_all = false;
		}

		// Make sure they're allowed to delete their own messages, if not any.
		if (!$allowed_all) {
			User::$me->isAllowedTo('delete_own');
		}

		// Allowed to remove which messages?
		$request = Db::$db->query(
			'',
			'SELECT id_msg, subject, id_member, poster_time
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:message_list})
				AND id_topic = {int:current_topic}' . (!$allowed_all ? '
				AND id_member = {int:current_member}' : '') . '
			LIMIT {int:limit}',
			[
				'current_member' => User::$me->id,
				'current_topic' => Topic::$topic_id,
				'message_list' => $this->messages,
				'limit' => count($this->messages),
			],
		);
		$message_info = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!$allowed_all && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 < time()) {
				continue;
			}

			$message_info[$row['id_msg']] = [$row['subject'], $row['id_member']];
		}
		Db::$db->free_result($request);

		// Get the first message in the topic - because you can't delete that!
		$request = Db::$db->query(
			'',
			'SELECT id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
			],
		);
		list($first_message, $last_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Delete all the messages we know they can delete. ($message_info)
		foreach ($message_info as $message => $info) {
			// Just skip the first message - if it's not the last.
			if ($message == $first_message && $message != $last_message) {
				continue;
			}

			// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
			if ($message == $first_message) {
				$topicGone = true;
			}

			Msg::remove($message);

			// Log this moderation action ;).
			if (User::$me->allowedTo('delete_any') && (!User::$me->allowedTo('delete_own') || $info[1] != User::$me->id)) {
				Logging::logAction('delete', ['topic' => Topic::$topic_id, 'subject' => $info[0], 'member' => $info[1], 'board' => Board::$info->id]);
			}
		}

		Utils::redirectexit(!empty($topicGone) ? 'board=' . Board::$info->id : 'topic=' . Topic::$topic_id . '.' . $_REQUEST['start']);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\QuickModerationInTopic::exportStatic')) {
	QuickModerationInTopic::exportStatic();
}

?>