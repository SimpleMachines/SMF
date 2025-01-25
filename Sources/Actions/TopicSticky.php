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
use SMF\Board;
use SMF\ErrorHandler;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

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
class TopicSticky implements ActionInterface
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
		if (isset($_GET['sa']) && in_array($_GET['sa'], ['sticky', 'nonsticky'])) {
			// Make sure the user can sticky it, and they are stickying *something*.
			User::$me->isAllowedTo('make_sticky', Board::$info->id);

			// You can't sticky a board or something!
			if (empty(Topic::$topic_id)) {
				ErrorHandler::fatalLang('not_a_topic', false);
			}

			User::$me->checkSession('get');

			$ids = Topic::sticky(Topic::$topic_id, $_GET['sa'] === 'sticky');

			// Another moderator got the job done first?
			if (empty($ids)) {
				ErrorHandler::fatalLang($_GET['sa'] === 'nonsticky' ? 'error_topic_nonsticky_already' : 'error_topic_sticky_already', false);
			}
		}

		// Take them back to the topic.
		Utils::redirectexit('topic=' . Topic::$topic_id . '.' . $_REQUEST['start'] . ';moderate');
	}
}

?>