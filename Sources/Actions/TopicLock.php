<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionSuffixRouter;
use SMF\ActionTrait;
use SMF\Board;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Routable;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Locks a topic... either by way of a moderator or the topic starter.
 *
 * What this does:
 *  - locks a topic, toggles between locked/unlocked/moderator locked.
 *  - only moderators can unlock topics locked by other moderators.
 *  - requires the lock_own or lock_any permission.
 *  - logs the action to the moderator log.
 *  - returns to the topic after it is done.
 *  - it is accessed via ?action=lock.
 */
class TopicLock implements ActionInterface, Routable
{
	use ActionSuffixRouter;
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		// Just quit if there's no topic to lock.
		if (empty(Topic::$topic_id)) {
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
				'current_topic' => Topic::$topic_id,
			],
		);
		list($starter, $locked) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Only moderators can unlock moderator locks.
		if (User::$me->allowedTo('lock_any', Board::$info->id)) {
			$max_unlock_level = 2;
		} elseif ($starter === User::$me->id && User::$me->allowedTo('lock_own', Board::$info->id)) {
			$max_unlock_level = 1;
		} else {
			// User cannot do this. Boot them out with the appropriate message.
			User::$me->isAllowedTo($starter !== User::$me->id ? 'lock_any' : 'lock_own', Board::$info->id);
		}

		// Level 2 locks should only be used when locking some else's topic.
		$max_lock_level = $starter === User::$me->id ? 1 : min($max_unlock_level, 2);

		// What is the new lock level that we want?
		switch ($locked) {
			case 2:
				if ($max_unlock_level < 2) {
					ErrorHandler::fatalLang('locked_by_admin', 'user');
				}
				$level = 0;
				break;

			case 1:
				$level = 0;
				break;

			default:
				$level = $max_lock_level;
				break;
		}

		// Do it.
		$ids = Topic::lock(Topic::$topic_id, $level);

		// Another moderator got the job done first?
		if (empty($ids)) {
			ErrorHandler::fatalLang(empty($level) ? 'error_topic_unlocked_already' : 'error_topic_locked_already', false);
		}

		// Back to the topic!
		Utils::redirectexit('topic=' . Topic::$topic_id . '.' . $_REQUEST['start'] . ';moderate');
	}
}

?>