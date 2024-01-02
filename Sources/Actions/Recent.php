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
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Msg;
use SMF\PageIndex;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Finds and retrieves information about recently posted messages.
 */
class Recent implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'RecentPosts',
			'getLastPost' => 'getLastPost',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	public const PER_PAGE = 10;
	public const PAGES = 10;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Base URL for this action.
	 */
	protected string $action_url;

	/**
	 * @var string
	 *
	 * SQL statement indicating the boards to look in.
	 */
	protected string $query_this_board;

	/**
	 * @var array
	 *
	 * Parameters for the main query.
	 */
	protected array $query_parameters = [];

	/**
	 * @var string
	 *
	 * Name of the category we are in, if applicable.
	 */
	protected string $cat_name;

	/**
	 * @var int
	 *
	 * How many recent messages we found.
	 */
	protected int $total_posts = 0;

	/**
	 * @var array
	 *
	 * IDs of some recent messages.
	 */
	protected array $messages = [];

	/**
	 * @var array
	 *
	 * Boards that we need to check for some own/any permissions.
	 */
	protected array $permission_boards = [
		'own' => [],
		'any' => [],
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
	 * Find the ten most recent posts.
	 */
	public function execute(): void
	{
		$this->getBoards();
		$this->getCatName();

		$this->setPaginationAndLinks();

		$this->getMsgIds();

		// Nothing here... Or at least, nothing you can see...
		if (empty($this->messages)) {
			return;
		}

		$this->getMessages();
		$this->doPermissions();
		$this->buildQuickButtons();

		// Allow last minute changes.
		IntegrationHook::call('integrate_recent_RecentPosts');
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
	 * Get the latest post made on the forum.
	 *
	 * Respects approved, recycled, and board permissions.
	 * Not used by SMF itself, but kept around in case mods need it.
	 *
	 * @return array An array of information about the last post that you can see
	 */
	public static function getLastPost()
	{
		// Find it by the board - better to order by board than sort the entire messages table.
		$request = Db::$db->query(
			'substring',
			'SELECT m.poster_time, m.subject, m.id_topic, m.poster_name, SUBSTRING(m.body, 1, 385) AS body,
				m.smileys_enabled
			FROM {db_prefix}messages AS m' . (!empty(Config::$modSettings['postmod_active']) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
			WHERE {query_wanna_see_message_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND m.id_board != {int:recycle_board}' : '') . (!empty(Config::$modSettings['postmod_active']) ? '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT 1',
			[
				'recycle_board' => Config::$modSettings['recycle_board'],
				'is_approved' => 1,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			return [];
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// Censor the subject and post...
		Lang::censorText($row['subject']);
		Lang::censorText($row['body']);

		$row['body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['body'], $row['smileys_enabled']), ['<br>' => '&#10;']));

		if (Utils::entityStrlen($row['body']) > 128) {
			$row['body'] = Utils::entitySubstr($row['body'], 0, 128) . '...';
		}

		// Send the data.
		return [
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'short_subject' => Utils::shorten($row['subject'], 24),
			'preview' => $row['body'],
			'time' => Time::create('@' . $row['poster_time'])->format(),
			'timestamp' => $row['poster_time'],
			'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new">' . $row['subject'] . '</a>',
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->action_url = Config::$scripturl . '?action=recent';

		Utils::$context['posts'] = [];

		Theme::loadTemplate('Recent');
		Utils::$context['page_title'] = Lang::$txt['recent_posts'];
		Utils::$context['sub_template'] = 'recent';

		Utils::$context['is_redirect'] = false;

		// Limit the start value to 90 or less.
		Utils::$context['start'] = min(self::PER_PAGE * (self::PAGES - 1), (int) ($_REQUEST['start'] ?? 0));
		// Also make it an even multiple of our posts per page value.
		Utils::$context['start'] -= Utils::$context['start'] % self::PER_PAGE;

		// Convert $_REQUEST['boards'] to an array of integers.
		if (!empty($_REQUEST['boards'])) {
			$_REQUEST['boards'] = array_map('intval', explode(',', $_REQUEST['boards']));
		}

		// Board requests takes precedence over category requests.
		if (!empty($_REQUEST['boards']) || !empty(Board::$info->id)) {
			unset($_REQUEST['c']);
		}

		// Convert $_REQUEST['c'] to an array of integers.
		if (!empty($_REQUEST['c'])) {
			$_REQUEST['c'] = array_map('intval', explode(',', $_REQUEST['c']));
		}
	}

	/**
	 * Figures out what boards we want to get messages from.
	 *
	 * Sets $this->query_this_board and $this->query_parameters.
	 *
	 * @todo Break this up further.
	 */
	protected function getBoards()
	{
		// Requested one or more categories.
		if (!empty($_REQUEST['c'])) {
			$boards = [];
			$request = Db::$db->query(
				'',
				'SELECT b.id_board, b.num_posts
				FROM {db_prefix}boards AS b
				WHERE b.id_cat IN ({array_int:category_list})
					AND b.redirect = {string:empty}' . (!empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? '
					AND b.id_board != {int:recycle_board}' : '') . '
					AND {query_wanna_see_board}',
				[
					'category_list' => $_REQUEST['c'],
					'empty' => '',
					'recycle_board' => !empty(Config::$modSettings['recycle_board']) ? Config::$modSettings['recycle_board'] : 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards[] = $row['id_board'];
				$this->total_posts += $row['num_posts'];
			}
			Db::$db->free_result($request);

			if (empty($boards)) {
				ErrorHandler::fatalLang('error_no_boards_selected');
			}

			$this->query_this_board = 'm.id_board IN ({array_int:boards})';
			$this->query_parameters['boards'] = $boards;

			// If this category has a significant number of posts in it...
			if ($this->total_posts > 100 && $this->total_posts > Config::$modSettings['totalMessages'] / 15) {
				$this->query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
				$this->query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 400 - Utils::$context['start'] * 7);
			}

			$this->action_url .= ';c=' . implode(',', $_REQUEST['c']);
		}
		// Requested some boards.
		elseif (!empty($_REQUEST['boards'])) {
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);

			foreach ($_REQUEST['boards'] as $i => $b) {
				$_REQUEST['boards'][$i] = (int) $b;
			}

			$request = Db::$db->query(
				'',
				'SELECT b.id_board, b.num_posts
				FROM {db_prefix}boards AS b
				WHERE b.id_board IN ({array_int:board_list})
					AND b.redirect = {string:empty}
					AND {query_see_board}
				LIMIT {int:limit}',
				[
					'board_list' => $_REQUEST['boards'],
					'limit' => count($_REQUEST['boards']),
					'empty' => '',
				],
			);
			$boards = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards[] = $row['id_board'];
				$this->total_posts += $row['num_posts'];
			}
			Db::$db->free_result($request);

			if (empty($boards)) {
				ErrorHandler::fatalLang('error_no_boards_selected');
			}

			$this->query_this_board = 'm.id_board IN ({array_int:boards})';
			$this->query_parameters['boards'] = $boards;

			// If these boards have a significant number of posts in them...
			if ($this->total_posts > 100 && $this->total_posts > Config::$modSettings['totalMessages'] / 12) {
				$this->query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
				$this->query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 500 - Utils::$context['start'] * 9);
			}

			$this->action_url .= ';boards=' . implode(',', $_REQUEST['boards']);
		}
		// Requested a single board.
		elseif (!empty(Board::$info->id)) {
			$request = Db::$db->query(
				'',
				'SELECT num_posts, redirect
				FROM {db_prefix}boards
				WHERE id_board = {int:current_board}
				LIMIT 1',
				[
					'current_board' => Board::$info->id,
				],
			);
			list($this->total_posts, $redirect) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// If this is a redirection board, don't bother counting topics here...
			if ($redirect != '') {
				Utils::$context['is_redirect'] = true;
			}

			$this->query_this_board = 'm.id_board = {int:board}';
			$this->query_parameters['board'] = Board::$info->id;

			// If this board has a significant number of posts in it...
			if ($this->total_posts > 80 && $this->total_posts > Config::$modSettings['totalMessages'] / 10) {
				$this->query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
				$this->query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 600 - Utils::$context['start'] * 10);
			}

			$this->action_url .= ';board=' . Board::$info->id . '.%1$d';
		}
		// Requested recent posts from across the whole forum.
		else {
			$this->query_this_board = '{query_wanna_see_message_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
						AND m.id_board != {int:recycle_board}' : '') . '
						AND m.id_msg >= {int:max_id_msg}';
			$this->query_parameters['max_id_msg'] = max(0, Config::$modSettings['maxMsgID'] - 100 - Utils::$context['start'] * 6);
			$this->query_parameters['recycle_board'] = Config::$modSettings['recycle_board'];

			$query_these_boards = '{query_wanna_see_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
						AND b.id_board != {int:recycle_board}' : '');
			$query_these_boards_params = $this->query_parameters;
			unset($query_these_boards_params['max_id_msg']);

			$get_num_posts = Db::$db->query(
				'',
				'SELECT COALESCE(SUM(b.num_posts), 0)
				FROM {db_prefix}boards AS b
				WHERE ' . $query_these_boards . '
					AND b.redirect = {string:empty}',
				array_merge($query_these_boards_params, ['empty' => '']),
			);

			list($this->total_posts) = Db::$db->fetch_row($get_num_posts);

			Db::$db->free_result($get_num_posts);
		}
	}

	/**
	 * Gets the category name, if applicable.
	 */
	protected function getCatName()
	{
		if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1) {
			$request = Db::$db->query(
				'',
				'SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:id_cat}
				LIMIT 1',
				[
					'id_cat' => (int) $_REQUEST['c'][0],
				],
			);
			list($this->cat_name) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
	}

	/**
	 * Populates $this->messages with the IDs of some recent messages.
	 */
	protected function getMsgIds()
	{
		// If you selected a redirection board, don't try getting posts for it...
		if (Utils::$context['is_redirect']) {
			return;
		}

		$cache_key = 'recent-' . User::$me->id . '-' . md5(Utils::jsonEncode(array_diff_key($this->query_parameters, ['max_id_msg' => 0]))) . '-' . Utils::$context['start'];

		if (empty(CacheApi::$enable) || ($this->messages = CacheApi::get($cache_key, 120)) == null) {
			$done = false;

			while (!$done) {
				// Find the most recent messages they can *view*.
				// @todo SLOW This query is really slow still, probably?
				$request = Db::$db->query(
					'',
					'SELECT m.id_msg
					FROM {db_prefix}messages AS m ' . (!empty(Config::$modSettings['postmod_active']) ? '
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
					WHERE ' . $this->query_this_board . (!empty(Config::$modSettings['postmod_active']) ? '
						AND m.approved = {int:is_approved}
						AND t.approved = {int:is_approved}' : '') . '
					ORDER BY m.id_msg DESC
					LIMIT {int:offset}, {int:limit}',
					array_merge($this->query_parameters, [
						'is_approved' => 1,
						'offset' => Utils::$context['start'],
						'limit' => self::PER_PAGE,
					]),
				);

				// If we don't have enough results, try again with an unoptimized version covering all rows, and cache the result.
				if (isset($this->query_parameters['max_id_msg']) && Db::$db->num_rows($request) < self::PER_PAGE) {
					Db::$db->free_result($request);

					$this->query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $this->query_this_board);

					$cache_results = true;

					unset($this->query_parameters['max_id_msg']);
				} else {
					$done = true;
				}
			}

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->messages[] = $row['id_msg'];
			}
			Db::$db->free_result($request);

			if (!empty($cache_results)) {
				CacheApi::put($cache_key, $this->messages, 120);
			}
		}
	}

	/**
	 * Populates Utils::$context['posts'] with formatted messages.
	 */
	protected function getMessages()
	{
		$query_customizations = [
			'selects' => [
				'm.*',
				'COALESCE(mem.real_name, m.poster_name) AS poster_name',
				'b.name AS bname',
				't.id_member_started',
				't.id_first_msg',
				't.id_last_msg',
			],
			'joins' => [
				'INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)',
				'INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)',
				'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)',
			],
			'order' => ['m.id_msg DESC'],
			'limit' => count($this->messages),
			'params' => [],
		];

		$counter = Utils::$context['start'] + 1;

		foreach (Msg::get($this->messages, $query_customizations) as $msg) {
			Utils::$context['posts'][$msg->id] = $msg->format($counter++, [
				'do_permissions' => false,
				'do_icon' => false,
				'load_author' => false,
				'shorten_subject' => 30,
			]);

			Utils::$context['posts'][$msg->id]['board'] = [
				'id' => $msg->id_board,
				'name' => $msg->bname,
				'href' => Config::$scripturl . '?board=' . $msg->id_board . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $msg->id_board . '.0">' . $msg->bname . '</a>',
			];

			Utils::$context['posts'][$msg->id]['can_reply'] = false;
			Utils::$context['posts'][$msg->id]['can_delete'] = false;

			Utils::$context['posts'][$msg->id]['delete_possible'] = ($msg->id_first_msg != $msg->id || $msg->id_last_msg == $msg->id) && (empty(Config::$modSettings['edit_disable_time']) || $msg->poster_time + Config::$modSettings['edit_disable_time'] * 60 >= time());

			if (User::$me->id == $msg->id_member_started) {
				$this->permission_boards['own'][$msg->id_board][] = $msg->id;
			}

			$this->permission_boards['any'][$msg->id_board][] = $msg->id;
		}
	}

	/**
	 * Figure our what the current user can do with each message.
	 */
	protected function doPermissions(): void
	{
		// There might be - and are - different permissions between any and own.
		$permissions = [
			'own' => [
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			],
			'any' => [
				'post_reply_any' => 'can_reply',
				'delete_any' => 'can_delete',
			],
		];

		// Create an array for the permissions.
		$boards_can = User::$me->boardsAllowedTo(array_keys(
			iterator_to_array(
				new \RecursiveIteratorIterator(new \RecursiveArrayIterator($permissions)),
			),
		), true, false);

		// Now go through all the permissions, looking for boards they can do it on.
		foreach ($permissions as $type => $list) {
			foreach ($list as $permission => $allowed) {
				// They can do it on these boards...
				$boards = $boards_can[$permission];

				// If 0 is the only thing in the array, they can do it everywhere!
				if (!empty($boards) && $boards[0] == 0) {
					$boards = array_keys($this->permission_boards[$type]);
				}

				// Go through the boards, and look for posts they can do this on.
				foreach ($boards as $board_id) {
					// Hmm, they have permission, but there are no topics from that board on this page.
					if (!isset($this->permission_boards[$type][$board_id])) {
						continue;
					}

					// Okay, looks like they can do it for these posts.
					foreach ($this->permission_boards[$type][$board_id] as $counter) {
						if ($type == 'any' || Utils::$context['posts'][$counter]['poster']['id'] == User::$me->id) {
							Utils::$context['posts'][$counter][$allowed] = true;
						}
					}
				}
			}
		}

		$quote_enabled = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));

		foreach (Utils::$context['posts'] as $counter => $dummy) {
			// Some posts - the first posts - can't just be deleted.
			Utils::$context['posts'][$counter]['can_delete'] &= Utils::$context['posts'][$counter]['delete_possible'];

			// And some cannot be quoted...
			Utils::$context['posts'][$counter]['can_quote'] = Utils::$context['posts'][$counter]['can_reply'] && $quote_enabled;
		}
	}

	/**
	 * Constructs page index, sets the linktree, next/prev/up links, etc.
	 */
	protected function setPaginationAndLinks()
	{
		$total = min(self::PER_PAGE * self::PAGES, $this->total_posts);
		$not_first_page = Utils::$context['start'] >= self::PER_PAGE;
		$not_last_page = Utils::$context['start'] + self::PER_PAGE < $this->total_posts;

		if (isset($this->cat_name)) {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '#c' . (int) $_REQUEST['c'][0],
				'name' => $this->cat_name,
			];
		}

		Utils::$context['linktree'][] = [
			'url' => sprintf($this->action_url, 0),
			'name' => Utils::$context['page_title'],
		];

		Utils::$context['page_index'] = new PageIndex($this->action_url, Utils::$context['start'], $total, self::PER_PAGE, !empty(Board::$info->id));

		Utils::$context['current_page'] = floor(Utils::$context['start'] / self::PER_PAGE);

		Utils::$context['links'] = [
			'first' => $not_first_page ? sprintf($this->action_url, 0) : '',
			'prev' => $not_first_page ? sprintf($this->action_url, Utils::$context['start'] - self::PER_PAGE) : '',
			'next' => $not_last_page ? sprintf($this->action_url, Utils::$context['start'] + self::PER_PAGE) : '',
			'last' => $not_last_page ? sprintf($this->action_url, $total - ($total % self::PER_PAGE)) : '',
			'up' => Config::$scripturl,
		];

		Utils::$context['page_info'] = [
			'current_page' => Utils::$context['current_page'] + 1,
			'num_pages' => floor(($total - 1) / self::PER_PAGE) + 1,
		];
	}

	/**
	 * Last but not least, the quickbuttons.
	 */
	protected function buildQuickButtons()
	{
		foreach (Utils::$context['posts'] as $key => $post) {
			Utils::$context['posts'][$key]['quickbuttons'] = [
				'reply' => [
					'label' => Lang::$txt['reply'],
					'href' => Config::$scripturl . '?action=post;topic=' . $post['topic'] . '.0',
					'icon' => 'reply_button',
					'show' => $post['can_reply'],
				],
				'quote' => [
					'label' => Lang::$txt['quote_action'],
					'href' => Config::$scripturl . '?action=post;topic=' . $post['topic'] . '.0;quote=' . $post['id'],
					'icon' => 'quote',
					'show' => $post['can_quote'],
				],
				'delete' => [
					'label' => Lang::$txt['remove'],
					'href' => Config::$scripturl . '?action=deletemsg;msg=' . $post['id'] . ';topic=' . $post['topic'] . ';recent;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					'javascript' => 'data-confirm="' . Lang::$txt['remove_message'] . '"',
					'class' => 'you_sure',
					'icon' => 'remove_button',
					'show' => $post['can_delete'],
				],
			];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Recent::exportStatic')) {
	Recent::exportStatic();
}

?>