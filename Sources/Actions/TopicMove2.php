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
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Msg;
use SMF\Security;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * This action handles moving topics from one board to another board.
 */
class TopicMove2 implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'MoveTopic2',
			'moveTopicConcurrence' => 'moveTopicConcurrence',
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
	 * Execute the move of a topic.
	 * It is called on the submit of TopicMove.
	 * This function logs that topics have been moved in the moderation log.
	 * If the member is the topic starter requires the move_own permission,
	 * otherwise requires the move_any permission.
	 * Upon successful completion redirects to message index.
	 * Accessed via ?action=movetopic2.
	 */
	public function execute(): void
	{
		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// You can't choose to have a redirection topic and use an empty reason.
		if (isset($_POST['postRedirect']) && (!isset($_POST['reason']) || trim($_POST['reason']) == '')) {
			ErrorHandler::fatalLang('movetopic_no_reason', false);
		}

		self::moveTopicConcurrence();

		// Make sure this form hasn't been submitted before.
		Security::checkSubmitOnce('check');

		$request = Db::$db->query(
			'',
			'SELECT id_member_started, id_first_msg, approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
			],
		);
		list($id_member_started, $id_first_msg, Utils::$context['is_approved']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Can they see it?
		if (!Utils::$context['is_approved']) {
			User::$me->isAllowedTo('approve_posts');
		}

		// Can they move topics on this board?
		if (!User::$me->allowedTo('move_any')) {
			if ($id_member_started == User::$me->id) {
				User::$me->isAllowedTo('move_own');
			} else {
				User::$me->isAllowedTo('move_any');
			}
		}

		User::$me->checkSession();

		// The destination board must be numeric.
		$_POST['toboard'] = (int) $_POST['toboard'];

		// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
		$request = Db::$db->query(
			'',
			'SELECT b.count_posts, b.name, m.subject
			FROM {db_prefix}boards AS b
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE {query_see_board}
				AND b.id_board = {int:to_board}
				AND b.redirect = {string:blank_redirect}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
				'to_board' => $_POST['toboard'],
				'blank_redirect' => '',
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_board');
		}
		list($pcounter, $board_name, $subject) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Remember this for later.
		$_SESSION['move_to_topic'] = $_POST['toboard'];

		// Rename the topic...
		if (isset($_POST['reset_subject'], $_POST['custom_subject']) && $_POST['custom_subject'] != '') {
			$_POST['custom_subject'] = strtr(Utils::htmlTrim(Utils::htmlspecialchars($_POST['custom_subject'])), ["\r" => '', "\n" => '', "\t" => '']);

			// Keep checking the length.
			if (Utils::entityStrlen($_POST['custom_subject']) > 100) {
				$_POST['custom_subject'] = Utils::entitySubstr($_POST['custom_subject'], 0, 100);
			}

			// If it's still valid move onwards and upwards.
			if ($_POST['custom_subject'] != '') {
				if (isset($_POST['enforce_subject'])) {
					// Get a response prefix, but in the forum's default language.
					if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix'))) {
						if (Lang::$default === User::$me->language) {
							Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
						} else {
							Lang::load('index', Lang::$default, false);
							Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
							Lang::load('index');
						}
						CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
					}

					Db::$db->query(
						'',
						'UPDATE {db_prefix}messages
						SET subject = {string:subject}
						WHERE id_topic = {int:current_topic}',
						[
							'current_topic' => Topic::$topic_id,
							'subject' => Utils::$context['response_prefix'] . $_POST['custom_subject'],
						],
					);
				}

				Db::$db->query(
					'',
					'UPDATE {db_prefix}messages
					SET subject = {string:custom_subject}
					WHERE id_msg = {int:id_first_msg}',
					[
						'id_first_msg' => $id_first_msg,
						'custom_subject' => $_POST['custom_subject'],
					],
				);

				// Fix the subject cache.
				Logging::updateStats('subject', Topic::$topic_id, $_POST['custom_subject']);
			}
		}

		// Create a link to this in the old board.
		// @todo Does this make sense if the topic was unapproved before? I'd just about say so.
		if (isset($_POST['postRedirect'])) {
			// Replace tokens with links in the reason.
			$reason_replacements = [
				Lang::$txt['movetopic_auto_board'] => '[url="' . Config::$scripturl . '?board=' . $_POST['toboard'] . '.0"]' . $board_name . '[/url]',
				Lang::$txt['movetopic_auto_topic'] => '[iurl]' . Config::$scripturl . '?topic=' . Topic::$topic_id . '.0[/iurl]',
			];

			// Should be in the boardwide language.
			if (User::$me->language != Lang::$default) {
				Lang::load('index', Lang::$default);

				// Make sure we catch both languages in the reason.
				$reason_replacements += [
					Lang::$txt['movetopic_auto_board'] => '[url="' . Config::$scripturl . '?board=' . $_POST['toboard'] . '.0"]' . $board_name . '[/url]',
					Lang::$txt['movetopic_auto_topic'] => '[iurl]' . Config::$scripturl . '?topic=' . Topic::$topic_id . '.0[/iurl]',
				];
			}

			$_POST['reason'] = Utils::htmlspecialchars($_POST['reason'], ENT_QUOTES);
			Msg::preparsecode($_POST['reason']);

			// Insert real links into the reason.
			$_POST['reason'] = strtr($_POST['reason'], $reason_replacements);

			// auto remove this MOVED redirection topic in the future?
			$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

			// redirect to the MOVED topic from topic list?
			$redirect_topic = isset($_POST['redirect_topic']) ? Topic::$topic_id : 0;

			$msgOptions = [
				'subject' => Lang::$txt['moved'] . ': ' . $subject,
				'body' => $_POST['reason'],
				'icon' => 'moved',
				'smileys_enabled' => 1,
			];

			$topicOptions = [
				'board' => Board::$info->id,
				'lock_mode' => 1,
				'mark_as_read' => true,
				'redirect_expires' => $redirect_expires,
				'redirect_topic' => $redirect_topic,
			];

			$posterOptions = [
				'id' => User::$me->id,
				'update_post_count' => empty($pcounter),
			];

			Msg::create($msgOptions, $topicOptions, $posterOptions);
		}

		$request = Db::$db->query(
			'',
			'SELECT count_posts
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			[
				'current_board' => Board::$info->id,
			],
		);
		list($pcounter_from) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($pcounter_from != $pcounter) {
			$posters = [];

			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND approved = {int:is_approved}',
				[
					'current_topic' => Topic::$topic_id,
					'is_approved' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (!isset($posters[$row['id_member']])) {
					$posters[$row['id_member']] = 0;
				}

				$posters[$row['id_member']]++;
			}
			Db::$db->free_result($request);

			foreach ($posters as $id_member => $posts) {
				// The board we're moving from counted posts, but not to.
				if (empty($pcounter_from)) {
					User::updateMemberData($id_member, ['posts' => 'posts - ' . $posts]);
				}
				// The reverse: from didn't, to did.
				else {
					User::updateMemberData($id_member, ['posts' => 'posts + ' . $posts]);
				}
			}
		}

		// Do the move (includes statistics update needed for the redirect topic).
		Topic::move(Topic::$topic_id, $_POST['toboard']);

		// Log that they moved this topic.
		if (!User::$me->allowedTo('move_own') || $id_member_started != User::$me->id) {
			Logging::logAction('move', ['topic' => Topic::$topic_id, 'board_from' => Board::$info->id, 'board_to' => $_POST['toboard']]);
		}

		// Notify people that this topic has been moved?
		Mail::sendNotifications(Topic::$topic_id, 'move');

		IntegrationHook::call('integrate_movetopic2_end');

		// Why not go back to the original board in case they want to keep moving?
		if (!isset($_REQUEST['goback'])) {
			Utils::redirectexit('board=' . Board::$info->id . '.0');
		} else {
			Utils::redirectexit('topic=' . Topic::$topic_id . '.0');
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

	/**
	 * Called after a topic is moved to update $board_link and $topic_link to point to new location
	 */
	public static function moveTopicConcurrence()
	{
		if (isset($_GET['current_board'])) {
			$move_from = (int) $_GET['current_board'];
		}

		if (empty($move_from) || empty(Board::$info->id) || empty(Topic::$topic_id)) {
			return true;
		}

		if ($move_from == Board::$info->id) {
			return true;
		}

		$request = Db::$db->query(
			'',
			'SELECT m.subject, b.name
				FROM {db_prefix}topics as t
					LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
					LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
				WHERE t.id_topic = {int:topic_id}
				LIMIT 1',
			[
				'topic_id' => Topic::$topic_id,
			],
		);
		list($topic_subject, $board_name) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$board_link = '<a href="' . Config::$scripturl . '?board=' . Board::$info->id . '.0">' . $board_name . '</a>';

		$topic_link = '<a href="' . Config::$scripturl . '?topic=' . Topic::$topic_id . '.0">' . $topic_subject . '</a>';

		ErrorHandler::fatalLang('topic_already_moved', false, [$topic_link, $board_link]);
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
if (is_callable(__NAMESPACE__ . '\\TopicMove2::exportStatic')) {
	TopicMove2::exportStatic();
}

?>