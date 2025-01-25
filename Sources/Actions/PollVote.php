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
use SMF\Lang;
use SMF\Poll;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Allow the user to vote.
 *
 * It is called to record a vote in a poll.
 * Must be called with a topic and option specified.
 * Requires the poll_vote permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=vote.
 *
 * Uses Post language file.
 */
class PollVote implements ActionInterface
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
		// Make sure they can vote.
		User::$me->isAllowedTo('poll_vote');

		Lang::load('Post');

		$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);

		if (empty($poll->id)) {
			ErrorHandler::fatalLang('poll_error', false);
		}

		$poll->buildPermissions();

		// If they can't vote, bail out.
		if (!$poll->permissions['allow_vote'] && !$poll->permissions['allow_change_vote']) {
			// Guests trying to vote illegally get their own error message.
			if (User::$me->is_guest && !$poll->guest_vote) {
				ErrorHandler::fatalLang('guest_vote_disabled', false);
			}

			ErrorHandler::fatalLang('poll_error', false);
		}

		User::$me->checkSession('request');

		// Removing their vote(s)?
		if ($poll->permissions['allow_change_vote'] && !User::$me->is_guest && empty($_POST['options'])) {
			$changed = false;

			foreach ($poll->choices as $id => $choice) {
				if (!empty($choice->voted_this)) {
					$changed = true;
					$poll->choices[$id]->votes--;
					$poll->choices[$id]->voted_this = false;
				}
			}

			// Just skip it if they had voted for nothing before.
			if ($changed) {
				// Update the poll.
				$poll->save();

				// Delete off the log.
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_polls
					WHERE id_member = {int:current_member}
						AND id_poll = {int:id_poll}',
					[
						'current_member' => User::$me->id,
						'id_poll' => $poll->id,
					],
				);
			}

			// Redirect back to the topic so the user can vote again!
			Utils::redirectexit('topic=' . Topic::$topic_id . '.' . (int) ($_REQUEST['start'] ?? 0));
		}

		// Make sure the option(s) are valid.
		if (empty($_POST['options'])) {
			ErrorHandler::fatalLang('didnt_select_vote', false);
		}

		// Too many options checked!
		if (count($_REQUEST['options']) > $poll->max_votes) {
			ErrorHandler::fatalLang('poll_too_many_votes', false, [$poll->max_votes]);
		}

		$choices = array_map('intval', $_REQUEST['options']);

		$inserts = [];

		foreach ($choices as $id_choice) {
			$id_choice = (int) $id_choice;
			$poll->choices[$id_choice]->votes++;
			$inserts[] = [$poll->id, User::$me->id, $id_choice];
		}

		// If it's a guest don't let them vote again.
		if (User::$me->is_guest && count($choices) > 0) {
			// Time is stored in case the poll is reset later, plus what they voted for.
			$_COOKIE['guest_poll_vote'] = empty($_COOKIE['guest_poll_vote']) ? '' : $_COOKIE['guest_poll_vote'];

			// ;id,timestamp,[vote,vote...]; etc
			$_COOKIE['guest_poll_vote'] .= ';' . $poll->id . ',' . time() . ',' . implode(',', $choices);

			$cookie = new Cookie('guest_poll_vote', $_COOKIE['guest_poll_vote'], time() + 2500000);
			$cookie->set();

			// Increase num_guest_voters by 1
			$poll->num_guest_voters++;
		}

		$poll->save();

		// Add their vote to the tally.
		Db::$db->insert(
			'insert',
			'{db_prefix}log_polls',
			['id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'],
			$inserts,
			['id_poll', 'id_member', 'id_choice'],
		);

		// Let mods know about this vote.
		IntegrationHook::call('integrate_poll_vote', [$poll->id, $choices]);

		// Return to the post...
		Utils::redirectexit('topic=' . Topic::$topic_id . '.' . (int) ($_REQUEST['start'] ?? 0));
	}
}

?>