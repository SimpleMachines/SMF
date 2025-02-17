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
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Routable;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Mark boards and topics as read (or unread in some cases).
 */
class MarkRead implements ActionInterface, Routable
{
	use ActionSuffixRouter;
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'board';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'all' => 'all',
		'unreadreplies' => 'replies',
		'topic' => 'topic',
		'board' => 'board',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Mark all visible boards as read.
	 */
	public function all(): void
	{
		// Find all the boards this user can see.
		$boards = [];

		$result = Db::$db->query(
			'',
			'SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$boards[] = $row['id_board'];
		}
		Db::$db->free_result($result);

		if (!empty($boards)) {
			Board::markBoardsRead($boards, isset($_REQUEST['unread']));
		}

		$_SESSION['id_msg_last_visit'] = Config::$modSettings['maxMsgID'];

		if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false) {
			Utils::redirectexit('action=unread');
		}

		if (isset($_SESSION['topicseen_cache'])) {
			$_SESSION['topicseen_cache'] = [];
		}

		Utils::redirectexit();
	}

	/**
	 * Marks topics with unread replies as read.
	 */
	public function replies(): void
	{
		// Make sure all the topics are integers!
		$topics = array_map('intval', explode('-', $_REQUEST['topics']));

		$logged_topics = [];

		$request = Db::$db->query(
			'',
			'SELECT id_topic, unwatched
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:selected_topics})
				AND id_member = {int:current_user}',
			[
				'selected_topics' => $topics,
				'current_user' => User::$me->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$logged_topics[$row['id_topic']] = $row['unwatched'];
		}

		Db::$db->free_result($request);

		$markRead = [];

		foreach ($topics as $id_topic) {
			$markRead[] = [
				Config::$modSettings['maxMsgID'],
				User::$me->id,
				$id_topic,
				($logged_topics[Topic::$topic_id] ?? 0),
			];
		}

		Db::$db->insert(
			'replace',
			'{db_prefix}log_topics',
			[
				'id_msg' => 'int',
				'id_member' => 'int',
				'id_topic' => 'int',
				'unwatched' => 'int',
			],
			$markRead,
			[
				'id_member',
				'id_topic',
			],
		);

		if (isset($_SESSION['topicseen_cache'])) {
			$_SESSION['topicseen_cache'] = [];
		}

		Utils::redirectexit('action=unreadreplies');
	}

	/**
	 * Mark a topic as unread.
	 *
	 * @todo Should this be a separate action?
	 */
	public function topic(): void
	{
		// First, let's figure out what the latest message is.
		$result = Db::$db->query(
			'',
			'SELECT t.id_first_msg, t.id_last_msg, COALESCE(lt.unwatched, 0) as unwatched
			FROM {db_prefix}topics as t
				LEFT JOIN {db_prefix}log_topics as lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			WHERE t.id_topic = {int:current_topic}',
			[
				'current_topic' => Topic::$topic_id,
				'current_member' => User::$me->id,
			],
		);
		$topicinfo = Db::$db->fetch_assoc($result);
		Db::$db->free_result($result);

		if (!empty($_GET['t'])) {
			// If they read the whole topic, go back to the beginning.
			if ($_GET['t'] >= $topicinfo['id_last_msg']) {
				$earlyMsg = 0;
			}
			// If they want to mark the whole thing read, same.
			elseif ($_GET['t'] <= $topicinfo['id_first_msg']) {
				$earlyMsg = 0;
			}
			// Otherwise, get the latest message before the named one.
			else {
				$result = Db::$db->query(
					'',
					'SELECT MAX(id_msg)
					FROM {db_prefix}messages
					WHERE id_topic = {int:current_topic}
						AND id_msg >= {int:id_first_msg}
						AND id_msg < {int:topic_msg_id}',
					[
						'current_topic' => Topic::$topic_id,
						'topic_msg_id' => (int) $_GET['t'],
						'id_first_msg' => $topicinfo['id_first_msg'],
					],
				);
				list($earlyMsg) = Db::$db->fetch_row($result);
				Db::$db->free_result($result);
			}
		}
		// Marking unread from first page?  That's the whole topic.
		elseif ($_REQUEST['start'] == 0) {
			$earlyMsg = 0;
		} else {
			$result = Db::$db->query(
				'',
				'SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
				ORDER BY id_msg
				LIMIT {int:start}, 1',
				[
					'current_topic' => Topic::$topic_id,
					'start' => (int) $_REQUEST['start'],
				],
			);
			list($earlyMsg) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			$earlyMsg--;
		}

		// Blam, unread!
		Db::$db->insert(
			'replace',
			'{db_prefix}log_topics',
			[
				'id_msg' => 'int',
				'id_member' => 'int',
				'id_topic' => 'int',
				'unwatched' => 'int',
			],
			[
				[
					$earlyMsg,
					User::$me->id,
					Topic::$topic_id,
					$topicinfo['unwatched'],
				],
			],
			[
				'id_member',
				'id_topic',
			],
		);

		Utils::redirectexit('board=' . Board::$info->id . '.0');
	}

	/**
	 * Mark one or more boards as read.
	 */
	public function board(): void
	{
		$categories = [];
		$boards = [];

		if (isset($_REQUEST['c'])) {
			$_REQUEST['c'] = explode(',', $_REQUEST['c']);

			foreach ($_REQUEST['c'] as $c) {
				$categories[] = (int) $c;
			}
		}

		if (isset($_REQUEST['boards'])) {
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);

			foreach ($_REQUEST['boards'] as $b) {
				$boards[] = (int) $b;
			}
		}

		if (!empty(Board::$info->id)) {
			$boards[] = (int) Board::$info->id;
		}

		if (isset($_REQUEST['children']) && !empty($boards)) {
			// They want to mark the entire tree starting with the boards specified
			// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
			$request = Db::$db->query(
				'',
				'SELECT b.id_board, b.id_parent
				FROM {db_prefix}boards AS b
				WHERE {query_see_board}
					AND b.child_level > {int:no_parents}
					AND b.id_board NOT IN ({array_int:board_list})
				ORDER BY child_level ASC',
				[
					'no_parents' => 0,
					'board_list' => $boards,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (in_array($row['id_parent'], $boards)) {
					$boards[] = $row['id_board'];
				}
			}
			Db::$db->free_result($request);
		}

		$clauses = [];
		$clauseParameters = [];

		if (!empty($categories)) {
			$clauses[] = 'id_cat IN ({array_int:category_list})';
			$clauseParameters['category_list'] = $categories;
		}

		if (!empty($boards)) {
			$clauses[] = 'id_board IN ({array_int:board_list})';
			$clauseParameters['board_list'] = $boards;
		}

		if (empty($clauses)) {
			Utils::redirectexit();
		}

		$boards = [];

		$request = Db::$db->query(
			'',
			'SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.' . implode(' OR b.', $clauses),
			array_merge($clauseParameters, [
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$boards[] = $row['id_board'];
		}
		Db::$db->free_result($request);

		if (empty($boards)) {
			Utils::redirectexit();
		}

		Board::markBoardsRead($boards, isset($_REQUEST['unread']));

		foreach ($boards as $b) {
			if (isset($_SESSION['topicseen_cache'][$b])) {
				$_SESSION['topicseen_cache'][$b] = [];
			}
		}

		if (!isset($_REQUEST['unread'])) {
			// Find all the boards this user can see.
			$result = Db::$db->query(
				'',
				'SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE b.id_parent IN ({array_int:parent_list})
					AND {query_see_board}',
				[
					'parent_list' => $boards,
				],
			);

			if (Db::$db->num_rows($result) > 0) {
				$logBoardInserts = [];

				while ($row = Db::$db->fetch_assoc($result)) {
					$logBoardInserts[] = [Config::$modSettings['maxMsgID'], User::$me->id, $row['id_board']];
				}

				Db::$db->insert(
					'replace',
					'{db_prefix}log_boards',
					[
						'id_msg' => 'int',
						'id_member' => 'int',
						'id_board' => 'int',
					],
					$logBoardInserts,
					[
						'id_member',
						'id_board',
					],
				);
			}
			Db::$db->free_result($result);

			if (empty(Board::$info->id)) {
				Utils::redirectexit();
			} else {
				Utils::redirectexit('board=' . Board::$info->id . '.0');
			}
		} else {
			if (empty(Board::$info->parent)) {
				Utils::redirectexit();
			} else {
				Utils::redirectexit('board=' . Board::$info->parent . '.0');
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		if (isset($params['sa'], $params['topic']) && $params['sa'] === 'topic') {
			unset($params['sa']);
		} elseif (isset($params['sa'], $params['board']) && $params['sa'] === 'board') {
			unset($params['sa']);
		}

		if (isset($params['topic'])) {
			extract(Topic::buildRoute($params));
		} elseif (isset($params['board'])) {
			extract(Board::buildRoute($params));
		}

		$route = array_merge($route ?? [], self::buildActionRoute($params));

		return ['route' => $route, 'params' => $params];
	}

	/**
	 * Parses a route to get URL query parameters.
	 *
	 * @param array $route Array of routing path components.
	 * @param array $params Any existing URL query parameters.
	 * @return array URL query parameters
	 */
	public static function parseRoute(array $route, array $params = []): array
	{
		$params = array_merge($params, self::parseActionRoute($route));

		$params['sa'] = $params['sa'] ?? isset($params['topic']) ? 'topic' : (isset($params['board']) ? 'board' : null);

		return $params;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// No Guests allowed!
		User::$me->kickIfGuest();

		User::$me->checkSession('get');

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}

?>