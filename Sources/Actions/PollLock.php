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
use SMF\ErrorHandler;
use SMF\Logging;
use SMF\Poll;
use SMF\Routable;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Lock the voting for a poll.
 *
 * Must be called with a topic specified in the URL.
 * An admin always has overriding permission to lock a poll.
 * If not an admin must have poll_lock_any permission, otherwise must
 * be poll starter with poll_lock_own permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=lockvoting.
 */
class PollLock implements ActionInterface, Routable
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
		User::$me->checkSession('get');

		$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);

		if (empty($poll->id)) {
			ErrorHandler::fatalLang('poll_error', false);
		}

		$poll->buildPermissions();

		// Not allowed, so log and show fatal error.
		if (!$poll->permissions['allow_lock_poll']) {
			User::$me->isAllowedTo('poll_lock_' . (User::$me->id == $poll->member ? 'own' : 'any'));
		}

		switch ($poll->voting_locked) {
			// Was locked by a moderator.
			case 2:
				// If current user is not a moderator, they can't unlock it.
				if (!User::$me->allowedTo('moderate_board')) {
					ErrorHandler::fatalLang('locked_by_admin', 'user');
				}

				// Otherwise, unlock it.
				$poll->voting_locked = 0;

				break;

			// Was locked by a regular user, so unlock it.
			case 1:
				$poll->voting_locked = 0;
				break;

			// Not locked, so lock it.
			default:
				// Remember whether this was locked by moderator or a regular user.
				$poll->voting_locked = User::$me->allowedTo('moderate_board') ? 2 : 1;
				break;
		}

		$poll->save();

		Logging::logAction(($poll->voting_locked ? '' : 'un') . 'lock_poll', ['topic' => Topic::$topic_id]);

		Utils::redirectexit('topic=' . Topic::$topic_id . '.' . (int) ($_REQUEST['start'] ?? 0));
	}
}

?>