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

namespace SMF\Actions\Moderation;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Msg;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class WatchedUsers implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ViewWatchedUsers',
			'list_getWatchedUserCount' => 'list_getWatchedUserCount',
			'list_getWatchedUsers' => 'list_getWatchedUsers',
			'list_getWatchedUserPostsCount' => 'list_getWatchedUserPostsCount',
			'list_getWatchedUserPosts' => 'list_getWatchedUserPosts',
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// First off - are we deleting?
		if (!empty($_REQUEST['delete'])) {
			User::$me->checkSession(!is_array($_REQUEST['delete']) ? 'get' : 'post');

			$toDelete = [];

			if (!is_array($_REQUEST['delete'])) {
				$toDelete[] = (int) $_REQUEST['delete'];
			} else {
				foreach ($_REQUEST['delete'] as $did) {
					$toDelete[] = (int) $did;
				}
			}

			if (!empty($toDelete)) {
				// If they don't have permission we'll let it error - either way no chance of a security slip here!
				foreach ($toDelete as $did) {
					Msg::remove($did);
				}
			}
		}

		// Start preparing the list by grabbing relevant permissions.
		if (!Utils::$context['view_posts']) {
			$approve_query = '';
			$delete_boards = [];
		} else {
			// Still obey permissions!
			$approve_boards = User::$me->boardsAllowedTo('approve_posts');
			$delete_boards = User::$me->boardsAllowedTo('delete_any');

			if ($approve_boards == [0]) {
				$approve_query = '';
			} elseif (!empty($approve_boards)) {
				$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
			}
			// Nada, zip, etc...
			else {
				$approve_query = ' AND 1=0';
			}
		}

		// This is all the information required for a watched user listing.
		$listOptions = [
			'id' => 'watch_user_list',
			'title' => Lang::$txt['mc_watched_users_title'] . ' - ' . (Utils::$context['view_posts'] ? Lang::$txt['mc_watched_users_post'] : Lang::$txt['mc_watched_users_member']),
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Utils::$context['view_posts'] ? Lang::$txt['mc_watched_users_no_posts'] : Lang::$txt['mc_watched_users_none'],
			'base_href' => Config::$scripturl . '?action=moderate;area=userwatch;sa=' . (Utils::$context['view_posts'] ? 'post' : 'member'),
			'default_sort_col' => Utils::$context['view_posts'] ? '' : 'member',
			'get_items' => [
				'function' => Utils::$context['view_posts'] ? __CLASS__ . '::list_getWatchedUserPosts' : __CLASS__ . '::list_getWatchedUsers',
				'params' => [
					$approve_query,
					$delete_boards,
				],
			],
			'get_count' => [
				'function' => Utils::$context['view_posts'] ? 'list_getWatchedUserPostsCount' : __CLASS__ . '::list_getWatchedUserCount',
				'params' => [
					$approve_query,
				],
			],
			// This assumes we are viewing by user.
			'columns' => [
				'member' => [
					'header' => [
						'value' => Lang::$txt['mc_watched_users_member'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=profile;u=%1$d">%2$s</a>',
							'params' => [
								'id' => false,
								'name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'real_name',
						'reverse' => 'real_name DESC',
					],
				],
				'warning' => [
					'header' => [
						'value' => Lang::$txt['mc_watched_users_warning'],
					],
					'data' => [
						'function' => function ($member) {
							return User::$me->allowedTo('issue_warning') ? '<a href="' . Config::$scripturl . '?action=profile;area=issuewarning;u=' . $member['id'] . '">' . $member['warning'] . '%</a>' : $member['warning'] . '%';
						},
					],
					'sort' => [
						'default' => 'warning',
						'reverse' => 'warning DESC',
					],
				],
				'posts' => [
					'header' => [
						'value' => Lang::$txt['posts'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=profile;u=%1$d;area=showposts;sa=messages">%2$s</a>',
							'params' => [
								'id' => false,
								'posts' => false,
							],
						],
					],
					'sort' => [
						'default' => 'posts',
						'reverse' => 'posts DESC',
					],
				],
				'last_login' => [
					'header' => [
						'value' => Lang::$txt['mc_watched_users_last_login'],
					],
					'data' => [
						'db' => 'last_login',
					],
					'sort' => [
						'default' => 'last_login',
						'reverse' => 'last_login DESC',
					],
				],
				'last_post' => [
					'header' => [
						'value' => Lang::$txt['mc_watched_users_last_post'],
					],
					'data' => [
						'function' => function ($member) {
							if ($member['last_post_id']) {
								return '<a href="' . Config::$scripturl . '?msg=' . $member['last_post_id'] . '">' . $member['last_post'] . '</a>';
							}

							return $member['last_post'];
						},
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=moderate;area=userwatch;sa=post',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
				],
			],
			'additional_rows' => [
				Utils::$context['view_posts'] ?
					[
						'position' => 'bottom_of_list',
						'value' => '
						<input type="submit" name="delete_selected" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button">',
						'class' => 'floatright',
					] : [],
			],
		];

		// If this is being viewed by posts we actually change the columns to call a template each time.
		if (Utils::$context['view_posts']) {
			$listOptions['columns'] = [
				'posts' => [
					'data' => [
						'function' => function ($post) {
							return template_user_watch_post_callback($post);
						},
						'class' => 'unique',
					],
				],
			];
		}

		// Create the watched user list.
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'watch_user_list';
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
	 * Callback for SMF\ItemList().
	 *
	 * @param string $approve_query Not used here
	 * @return int The number of users on the watch list
	 */
	public static function list_getWatchedUserCount($approve_query): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE warning >= {int:warning_watch}',
			[
				'warning_watch' => Config::$modSettings['warning_watch'],
			],
		);
		list($totalMembers) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $totalMembers;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort things
	 * @param string $approve_query A query for approving things. Not used here.
	 * @param string $dummy Not used here.
	 * @return array An array of info about watched users
	 */
	public static function list_getWatchedUsers($start, $items_per_page, $sort, $approve_query, $dummy): array
	{
		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name, last_login, posts, warning
			FROM {db_prefix}members
			WHERE warning >= {int:warning_watch}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			[
				'warning_watch' => Config::$modSettings['warning_watch'],
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			],
		);
		$watched_users = [];
		$members = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$watched_users[$row['id_member']] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'last_login' => $row['last_login'] ? Time::create('@' . $row['last_login'])->format() : Lang::$txt['never'],
				'last_post' => Lang::$txt['not_applicable'],
				'last_post_id' => 0,
				'warning' => $row['warning'],
				'posts' => $row['posts'],
			];
			$members[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		if (!empty($members)) {
			// First get the latest messages from these users.
			$request = Db::$db->query(
				'',
				'SELECT m.id_member, MAX(m.id_msg) AS last_post_id
				FROM {db_prefix}messages AS m' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
				WHERE {query_see_message_board}
					AND m.id_member IN ({array_int:member_list})' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}') . '
				GROUP BY m.id_member',
				[
					'member_list' => $members,
					'is_approved' => 1,
				],
			);
			$latest_posts = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$latest_posts[$row['id_member']] = $row['last_post_id'];
			}

			if (!empty($latest_posts)) {
				// Now get the time those messages were posted.
				$request = Db::$db->query(
					'',
					'SELECT id_member, poster_time
					FROM {db_prefix}messages
					WHERE id_msg IN ({array_int:message_list})',
					[
						'message_list' => $latest_posts,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$watched_users[$row['id_member']]['last_post'] = Time::create('@' . $row['poster_time'])->format();
					$watched_users[$row['id_member']]['last_post_id'] = $latest_posts[$row['id_member']];
				}

				Db::$db->free_result($request);
			}

			$request = Db::$db->query(
				'',
				'SELECT MAX(m.poster_time) AS last_post, MAX(m.id_msg) AS last_post_id, m.id_member
				FROM {db_prefix}messages AS m
				WHERE {query_see_message_board}
					AND m.id_member IN ({array_int:member_list})' . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}') . '
				GROUP BY m.id_member',
				[
					'member_list' => $members,
					'is_approved' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$watched_users[$row['id_member']]['last_post'] = Time::create('@' . $row['last_post'])->format();
				$watched_users[$row['id_member']]['last_post_id'] = $row['last_post_id'];
			}
			Db::$db->free_result($request);
		}

		return $watched_users;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param string $approve_query A query to pull only approved items
	 * @return int The total number of posts by watched users
	 */
	public static function list_getWatchedUserPostsCount($approve_query): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE mem.warning >= {int:warning_watch}
				AND {query_see_board}
				' . $approve_query,
			[
				'warning_watch' => Config::$modSettings['warning_watch'],
			],
		);
		list($totalMemberPosts) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $totalMemberPosts;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results (not used here)
	 * @param string $approve_query A query to only pull approved items
	 * @param int[] $delete_boards An array containing the IDs of boards we can delete posts in
	 * @return array An array of info about posts by watched users
	 */
	public static function list_getWatchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards): array
	{
		$request = Db::$db->query(
			'',
			'SELECT m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
				m.approved, mem.real_name, m.smileys_enabled
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE mem.warning >= {int:warning_watch}
				AND {query_see_board}
				' . $approve_query . '
			ORDER BY m.id_msg DESC
			LIMIT {int:start}, {int:max}',
			[
				'warning_watch' => Config::$modSettings['warning_watch'],
				'start' => $start,
				'max' => $items_per_page,
			],
		);
		$member_posts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['subject'] = Lang::censorText($row['subject']);
			$row['body'] = Lang::censorText($row['body']);

			$member_posts[$row['id_msg']] = [
				'id' => $row['id_msg'],
				'id_topic' => $row['id_topic'],
				'author_link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'subject' => $row['subject'],
				'body' => BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']),
				'poster_time' => Time::create('@' . $row['poster_time'])->format(),
				'approved' => $row['approved'],
				'can_delete' => $delete_boards == [0] || in_array($row['id_board'], $delete_boards),
			];
		}
		Db::$db->free_result($request);

		return $member_posts;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Some important context!
		Utils::$context['page_title'] = Lang::$txt['mc_watched_users_title'];
		Utils::$context['view_posts'] = isset($_GET['sa']) && $_GET['sa'] == 'post';
		Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		Theme::loadTemplate('ModerationCenter');

		// Get some key settings!
		Config::$modSettings['warning_watch'] = empty(Config::$modSettings['warning_watch']) ? 1 : Config::$modSettings['warning_watch'];

		// Put some pretty tabs on cause we're gonna be doing hot stuff here...
		Menu::$loaded['moderate']->tab_data = [
			'title' => Lang::$txt['mc_watched_users_title'],
			'help' => '',
			'description' => Lang::$txt['mc_watched_users_desc'],
		];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\WatchedUsers::exportStatic')) {
	WatchedUsers::exportStatic();
}

?>