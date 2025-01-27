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
use SMF\ActionTrait;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Logging;
use SMF\Poll;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Remove a poll from a topic without removing the topic.
 *
 * Must be called with a topic specified in the URL.
 * Requires poll_remove_any permission, unless it's the poll starter
 * with poll_remove_own permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=removepoll.
 */
class PollRemove implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		// Make sure the topic is not empty.
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Verify the session.
		User::$me->checkSession('get');

		$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);

		if (empty($poll->id)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		Poll::checkRemovePermission($poll);

		// Remove all user logs for this poll.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}',
			[
				'id_poll' => $poll->id,
			],
		);

		// Remove all poll choices.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}poll_choices
			WHERE id_poll = {int:id_poll}',
			[
				'id_poll' => $poll->id,
			],
		);

		// Remove the poll itself.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}polls
			WHERE id_poll = {int:id_poll}',
			[
				'id_poll' => $poll->id,
			],
		);

		// Finally set the topic's poll ID back to 0.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_poll = {int:no_poll}
			WHERE id_topic = {int:current_topic}',
			[
				'current_topic' => Topic::$topic_id,
				'no_poll' => 0,
			],
		);

		// Let mods know that this poll has been removed.
		IntegrationHook::call('integrate_poll_remove', [$poll->id]);

		// Log this!
		Logging::logAction('remove_poll', ['topic' => Topic::$topic_id]);

		// Take the moderator back to the topic.
		Utils::redirectexit('topic=' . Topic::$topic_id . '.' . (int) ($_REQUEST['start'] ?? 0));
	}
}

?>