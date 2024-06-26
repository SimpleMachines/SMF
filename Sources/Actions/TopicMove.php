<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Security;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * This action provides UI to allow topics to be moved from one board to another.
 */
class TopicMove implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * This method allows to move a topic, making sure to ask the moderator
	 * to give reason for topic move.
	 * It must be called with a topic specified. (that is, Topic::$topic_id must
	 * be set... @todo fix this thing.)
	 * If the member is the topic starter requires the move_own permission,
	 * otherwise the move_any permission.
	 * Accessed via ?action=movetopic.
	 *
	 * Uses the MoveTopic template, main sub-template.
	 */
	public function execute(): void
	{
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$request = Db::$db->query(
			'',
			'SELECT t.id_member_started, ms.subject, t.approved
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
			],
		);
		list($id_member_started, Utils::$context['subject'], Utils::$context['is_approved']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Can they see it - if not approved?
		if (Config::$modSettings['postmod_active'] && !Utils::$context['is_approved']) {
			User::$me->isAllowedTo('approve_posts');
		}

		// Permission check!
		// @todo
		if (!User::$me->allowedTo('move_any')) {
			if ($id_member_started == User::$me->id) {
				User::$me->isAllowedTo('move_own');
			} else {
				User::$me->isAllowedTo('move_any');
			}
		}

		Utils::$context['move_any'] = User::$me->is_admin || Config::$modSettings['topic_move_any'];
		$boards = [];

		if (!Utils::$context['move_any']) {
			$boards = array_diff(User::$me->boardsAllowedTo('post_new', true), [Board::$info->id]);

			if (empty($boards)) {
				// No boards? Too bad...
				ErrorHandler::fatalLang('moveto_no_boards');
			}
		}

		Theme::loadTemplate('MoveTopic');

		$options = [
			'not_redirection' => true,
			'use_permissions' => Utils::$context['move_any'],
		];

		if (!empty($_SESSION['move_to_topic']) && $_SESSION['move_to_topic'] != Board::$info->id) {
			$options['selected_board'] = $_SESSION['move_to_topic'];
		}

		if (!Utils::$context['move_any']) {
			$options['included_boards'] = $boards;
		}

		Utils::$context['categories'] = MessageIndex::getBoardList($options);

		Utils::$context['page_title'] = Lang::$txt['move_topic'];

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?topic=' . Topic::$topic_id . '.0',
			'name' => Utils::$context['subject'],
		];

		Utils::$context['linktree'][] = [
			'name' => Lang::$txt['move_topic'],
		];

		Utils::$context['back_to_topic'] = isset($_REQUEST['goback']);

		if (User::$me->language != Lang::$default) {
			Lang::load('General', Lang::$default);
			$temp = Lang::$txt['movetopic_default'];
			Lang::load('General');

			Lang::$txt['movetopic_default'] = $temp;
		}

		Utils::$context['sub_template'] = 'move';

		TopicMove2::moveTopicConcurrence();

		// Register this form and get a sequence number in Utils::$context.
		Security::checkSubmitOnce('register');
	}
}

?>