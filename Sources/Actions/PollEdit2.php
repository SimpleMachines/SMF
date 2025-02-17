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
use SMF\Security;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Update the settings for a poll, or add a new one.
 *
 * Must be called with a topic specified in the URL.
 * The user must have poll_edit_any/poll_add_any permission
 * for the relevant action. Otherwise they must be poll starter
 * with poll_edit_own permission for editing, or be topic starter
 * with poll_add_any permission for adding.
 * In the case of an error, this function will redirect back to
 * PollEdit and display the relevant error message.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=editpoll2.
 */
class PollEdit2 implements ActionInterface, Routable
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
		$errors = [];

		// Sneaking off, are we?
		if (empty($_POST)) {
			Utils::redirectexit('action=editpoll;topic=' . Topic::$topic_id . '.0');
		}

		if (User::$me->checkSession('post', '', false) != '') {
			$errors[] = 'session_timeout';
		}

		// Topic must exist.
		if (empty(Topic::load()->id)) {
			ErrorHandler::fatalLang('no_board', false);
		}

		// Is this a new poll, or editing an existing?
		$is_edit = isset($_REQUEST['add']) ? 0 : 1;

		// Get the poll attached to this topic, if there is one.
		$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);

		// Check their adding/editing is valid.
		if (!$is_edit && !empty($poll->id)) {
			ErrorHandler::fatalLang('poll_already_exists');
		}

		// Are we editing a poll that doesn't exist?
		if ($is_edit && empty($poll->id)) {
			ErrorHandler::fatalLang('poll_not_found');
		}

		// Does this poll belong to the current user?
		$is_own_topic = User::$me->id == Topic::$info->id_member_started;
		$is_own_poll = $is_own_topic || (!empty($poll->member) && User::$me->id == $poll->member);

		// Check if they have the power to add or edit the poll.
		if ($is_edit && !User::$me->allowedTo('poll_edit_any')) {
			User::$me->isAllowedTo('poll_edit_' . ($is_own_poll ? 'own' : 'any'));
		} elseif (!$is_edit && !User::$me->allowedTo('poll_add_any')) {
			User::$me->isAllowedTo('poll_add_' . ($is_own_topic ? 'own' : 'any'));
		}

		// Prevent double submission of this form.
		Security::checkSubmitOnce('check');

		// If adding a new poll to this topic, use the create method.
		if (!$is_edit && empty($poll->id)) {
			unset($poll);
			$poll = Poll::create($errors);
			$poll->topic = Topic::$topic_id;

			if (!empty($errors)) {
				PollEdit::call();

				return;
			}

			$poll->save();

			Logging::logAction('add_poll', ['topic' => Topic::$topic_id]);
			Utils::redirectexit('topic=' . Topic::$topic_id . '.' . (int) ($_REQUEST['start'] ?? 0));
		}

		// Clean up everything in $_POST.
		Poll::sanitizeInput($errors);

		if (!empty($errors)) {
			PollEdit::call();

			return;
		}

		// Set the properties.
		$props = [
			'question' => $_POST['question'],
			'max_votes' => $_POST['poll_max_votes'],
			'expire_time' => empty($_POST['poll_expire']) ? 0 : time() + $_POST['poll_expire'] * 86400,
			'hide_results' => $_POST['poll_hide'],
			'change_vote' => $_POST['poll_change_vote'],
			'guest_vote' => $_POST['poll_guest_vote'],
			'choices' => [],
		];

		$poll->set($props);

		foreach (array_values($_POST['options']) as $id => $label) {
			$id = (int) $id;

			if (isset($poll->choices[$id])) {
				$poll->choices[$id]->label = $label;
			} else {
				$poll->addChoice([
					'id' => $id,
					'poll' => $poll->id,
					'label' => $label,
					'votes' => 0,
				]);
			}
		}

		// Shall I reset the vote count, sir?
		if (isset($_POST['resetVoteCount'])) {
			$poll->resetVotes();
		}

		$poll->save();

		// Log this edit.
		$action = isset($_POST['resetVoteCount']) ? 'reset' : 'edit';
		Logging::logAction($action . '_poll', ['topic' => Topic::$topic_id]);

		// Off we go.
		Utils::redirectexit('topic=' . Topic::$topic_id . '.' . (int) ($_REQUEST['start'] ?? 0));
	}
}

?>