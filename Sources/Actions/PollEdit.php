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
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Poll;
use SMF\Routable;
use SMF\Security;
use SMF\Theme;
use SMF\Topic;
use SMF\Utils;

/**
 * Display screen for editing or adding a poll.
 *
 * Must be called with a topic specified in the URL.
 * If the user is adding a poll to a topic, must contain the variable
 * 'add' in the url.
 * User must have poll_edit_any/poll_add_any permission for the
 * relevant action, otherwise must be poll starter with poll_edit_own
 * permission for editing, or be topic starter with poll_add_any permission for adding.
 * Accessed via ?action=editpoll.
 *
 * Uses Post language file.
 * Uses Poll template, main sub-template.
 */
class PollEdit implements ActionInterface, Routable
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
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		Lang::load('Post');
		Theme::loadTemplate('Poll');

		Utils::$context['start'] = (int) $_REQUEST['start'];
		Utils::$context['is_edit'] = isset($_REQUEST['add']) ? 0 : 1;

		// Topic must exist.
		if (empty(Topic::load()->id)) {
			ErrorHandler::fatalLang('no_board', false);
		}

		// Get the poll attached to this topic, if there is one.
		$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);

		// If we are adding a new poll, make sure that there isn't already a poll there.
		if (!Utils::$context['is_edit'] && !empty($poll->id)) {
			ErrorHandler::fatalLang('poll_already_exists', false);
		}

		// Otherwise, if we're editing it, it obviously needs to exist.
		if (Utils::$context['is_edit'] && empty($poll->id)) {
			ErrorHandler::fatalLang('poll_not_found', false);
		}

		// Can you do this?
		Utils::$context['can_moderate_poll'] = Utils::$context['is_edit'] ? Poll::checkEditPermission($poll) : Poll::checkCreatePermission();

		// Do we enable guest voting?
		Poll::canGuestsVote();

		// Always show one extra box...
		if (Utils::$context['is_edit']) {
			do {
				$poll->addChoice([
					'id' => empty($poll->choices) ? 0 : max(array_keys($poll->choices)) + 1,
					'number' => count($poll->choices),
					'label' => '',
					'votes' => -1,
				], true);
			} while (count($poll->choices) < 2);
		}

		// Basic theme info...
		Utils::$context['poll'] = $poll->format();
		Utils::$context['choices'] = &Utils::$context['poll']['choices'];

		Utils::$context['last_choice_id'] = array_key_last(Utils::$context['poll']['choices']);
		Utils::$context['poll']['choices'][Utils::$context['last_choice_id']]['is_last'] = true;

		Utils::$context['page_title'] = Utils::$context['is_edit'] ? Lang::$txt['poll_edit'] : Lang::$txt['add_poll'];

		// Build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?topic=' . Topic::$info->id . '.0',
			'name' => Topic::$info->subject,
		];
		Utils::$context['linktree'][] = [
			'name' => Utils::$context['page_title'],
		];

		// Register this form in the session variables.
		Security::checkSubmitOnce('register');
	}
}

?>