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
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'MoveTopic',
		],
	];

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
			Lang::load('index', Lang::$default);
			$temp = Lang::$txt['movetopic_default'];
			Lang::load('index');

			Lang::$txt['movetopic_default'] = $temp;
		}

		Utils::$context['sub_template'] = 'move';

		TopicMove2::moveTopicConcurrence();

		// Register this form and get a sequence number in Utils::$context.
		Security::checkSubmitOnce('register');
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TopicMove::exportStatic')) {
	TopicMove::exportStatic();
}

?>