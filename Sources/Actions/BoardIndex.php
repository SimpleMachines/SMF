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
use SMF\Category;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Msg;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class shows the board index.
 *
 * It uses the BoardIndex template, and main sub template.
 * It updates most of the online statistics.
 *
 * Although this class is not accessed using an ?action=... URL query, it
 * behaves like an action in every other way.
 */
class BoardIndex implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static array $backcompat = [
		'func_names' => [
			'load' => 'BoardIndex',
			'call' => 'call',
			'get' => 'getBoardIndex',
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Shows the list of categories and boards, along with the info center.
	 */
	public function execute(): void
	{
		// Retrieve the categories and boards.
		$boardIndexOptions = [
			'include_categories' => true,
			'base_level' => 0,
			'parent_id' => 0,
			'set_latest_post' => true,
			'countChildPosts' => !empty(Config::$modSettings['countChildPosts']),
		];
		Utils::$context['categories'] = self::get($boardIndexOptions);

		// Now set up for the info center.
		Utils::$context['info_center'] = [];

		// Retrieve the latest posts if the theme settings require it.
		if (!empty(Theme::$current->settings['number_recent_posts'])) {
			if (Theme::$current->settings['number_recent_posts'] > 1) {
				Utils::$context['latest_posts'] = CacheApi::quickGet('boardindex-latest_posts:' . md5(User::$me->query_wanna_see_board . User::$me->language), '', [$this, 'cache_getLastPosts'], [Theme::$current->settings['number_recent_posts']]);
			}

			if (!empty(Utils::$context['latest_posts']) || !empty(Utils::$context['latest_post'])) {
				Utils::$context['info_center'][] = [
					'tpl' => 'recent',
					'txt' => 'recent_posts',
				];
			}
		}

		// Load the calendar?
		if (!empty(Config::$modSettings['cal_enabled']) && User::$me->allowedTo('calendar_view')) {
			// Retrieve the calendar data (events, birthdays, holidays).
			$eventOptions = [
				'include_holidays' => Config::$modSettings['cal_showholidays'] > 1,
				'include_birthdays' => Config::$modSettings['cal_showbdays'] > 1,
				'include_events' => Config::$modSettings['cal_showevents'] > 1,
				'num_days_shown' => empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'],
			];

			Utils::$context += CacheApi::quickGet('calendar_index_offset_' . User::$me->time_offset, 'Actions/Calendar.php', 'SMF\\Actions\\Calendar::cache_getRecentEvents', [$eventOptions]);

			// Whether one or multiple days are shown on the board index.
			Utils::$context['calendar_only_today'] = Config::$modSettings['cal_days_for_index'] == 1;

			// This is used to show the "how-do-I-edit" help.
			Utils::$context['calendar_can_edit'] = User::$me->allowedTo('calendar_edit_any');

			if (!empty(Utils::$context['show_calendar'])) {
				Utils::$context['info_center'][] = [
					'tpl' => 'calendar',
					'txt' => Utils::$context['calendar_only_today'] ? 'calendar_today' : 'calendar_upcoming',
				];
			}
		}

		// And stats.
		if (Theme::$current->settings['show_stats_index']) {
			Utils::$context['info_center'][] = [
				'tpl' => 'stats',
				'txt' => 'forum_stats',
			];
		}

		// Now the online stuff
		Utils::$context += Logging::getMembersOnlineStats([
			'show_hidden' => User::$me->allowedTo('moderate_forum'),
			'sort' => 'log_time',
			'reverse_sort' => true,
		]);

		Utils::$context['info_center'][] = [
			'tpl' => 'online',
			'txt' => 'online_users',
		];

		// Track most online statistics?
		if (!empty(Config::$modSettings['trackStats'])) {
			Logging::trackStatsUsersOnline(Utils::$context['num_guests'] + Utils::$context['num_users_online']);
		}

		// Are we showing all membergroups on the board index?
		if (!empty(Theme::$current->settings['show_group_key'])) {
			Utils::$context['membergroups'] = CacheApi::quickGet('membergroup_list', 'Group.php', 'SMF\\Group::getCachedList', []);
		}

		// Mark read button
		Utils::$context['mark_read_button'] = [
			'markread' => ['text' => 'mark_as_read', 'image' => 'markread.png', 'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=markasread;sa=all;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']],
		];

		// Allow mods to add additional buttons here
		IntegrationHook::call('integrate_mark_read_button');
	}

	/**
	 * Get the latest posts of a forum.
	 *
	 * @param int $number_posts How many posts to get.
	 * @return array Info about the posts.
	 */
	public function getLastPosts(int $number_posts = 5)
	{
		$msg_load_options = [
			'selects' => [
				'm.id_msg',
				'm.id_topic',
				'm.id_board',
				'm.id_member',
				'COALESCE(mem.real_name, m.poster_name) AS poster_name',
				'm.poster_time',
				'm.subject',
				'm.body',
				'm.smileys_enabled',
			],
			'joins' => [
				'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)',
			],
			'where' => [
				'm.id_msg >= {int:likely_max_msg}',
				'{query_wanna_see_message_board}',
			],
			'order' => ['m.id_msg DESC'],
			'limit' => $number_posts,
			'params' => [
				'likely_max_msg' => max(0, Config::$modSettings['maxMsgID'] - 50 * $number_posts),
			],
		];

		if (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0) {
			$msg_load_options['where'][] = 'm.id_board != {int:recycle_board}';
			$msg_load_options['params']['recycle_board'] = Config::$modSettings['recycle_board'];
		}

		if (Config::$modSettings['postmod_active']) {
			$msg_load_options['joins'][] = 'INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)';
			$msg_load_options['where'][] = 't.approved = {int:is_approved}';
			$msg_load_options['where'][] = 'm.approved = {int:is_approved}';
			$msg_load_options['params']['is_approved'] = 1;
		}

		foreach (Msg::get(0, $msg_load_options) as $msg) {
			$posts[$msg->id] = $msg->format(0, [
				'do_permissions' => false,
				'do_icon' => false,
				'load_author' => false,
				'load_board' => true,
				'make_preview' => true,
				'shorten_subject' => 24,
				// Going to the last post counts as viewing the whole topic.
				'url_params' => ['topicseen'],
			]);

			// Backward compatibility.
			$posts[$msg->id]['poster'] = &$posts[$msg->id]['member'];
		}

		return $posts;
	}

	/**
	 * Callback-function for the cache for getLastPosts().
	 *
	 * @param int $number_posts
	 */
	public function cache_getLastPosts(int $number_posts = 5)
	{
		return [
			'data' => $this->getLastPosts($number_posts),
			'expires' => time() + 60,
			'post_retri_eval' => '
				foreach ($cache_block[\'data\'] as $k => $post)
				{
					$cache_block[\'data\'][$k][\'time\'] = \\SMF\\Time::create(\'@\' . $post[\'raw_timestamp\'])->format();
					$cache_block[\'data\'][$k][\'timestamp\'] = $post[\'raw_timestamp\'];
				}',
		];
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
	 * Fetches a list of boards and (optional) categories including
	 * statistical information, child boards and moderators.
	 *
	 * Used by both the board index (main data) and the message index (child
	 * boards).
	 *
	 * Depending on the include_categories setting returns an associative
	 * array with categories->boards->child_boards or an associative array
	 * with boards->child_boards.
	 *
	 * @param array $board_index_options An array of boardindex options.
	 * @return array An array of information for displaying the boardindex.
	 */
	public static function get($board_index_options): array
	{
		// These should always be set.
		$board_index_options['include_categories'] = $board_index_options['include_categories'] ?? false;
		$board_index_options['base_level'] = $board_index_options['base_level'] ?? 0;
		$board_index_options['parent_id'] = $board_index_options['parent_id'] ?? 0;

		// For performance, track the latest post while going through the boards.
		if (!empty($board_index_options['set_latest_post'])) {
			$latest_post = [
				'timestamp' => 0,
				'ref' => 0,
			];
		}

		// This setting is not allowed to be empty
		if (empty(Config::$modSettings['boardindex_max_depth'])) {
			Config::$modSettings['boardindex_max_depth'] = 1;
		}

		// Stuff we always want to have in the query.
		$selects = [
			'b.id_board',
			'b.name AS board_name',
			'b.description',
			'CASE WHEN b.redirect != {string:blank_string} THEN 1 ELSE 0 END AS is_redirect',
			'b.num_posts',
			'b.num_topics',
			'b.unapproved_posts',
			'b.unapproved_topics',
			'b.child_level',
			'b.id_parent',
			'b.id_cat',
			'm.id_msg',
			'm.id_topic',
			'm.subject',
			'COALESCE(m.poster_time, 0) AS poster_time',
			'COALESCE(mem.id_member, 0) AS id_member',
			'COALESCE(mem.member_name, m.poster_name) AS poster_name',
			'COALESCE(mem.real_name, m.poster_name) AS real_name',
		];

		$params = [
			'current_member' => User::$me->id,
			'child_level' => $board_index_options['base_level'],
			'max_child_level' => $board_index_options['base_level'] + Config::$modSettings['boardindex_max_depth'],
			'blank_string' => '',
		];

		$joins = [
			'LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)',
			'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)',
		];

		$where = [
			'{query_see_board}',
			'b.child_level BETWEEN {int:child_level} AND {int:max_child_level}',
		];

		$order = [
			'b.child_level',
			'b.board_order',
		];

		// Extra stuff based on the passed options.
		if (!empty($board_index_options['parent_id'])) {
			$where[] = 'b.id_parent != 0';
			$params['id_parent'] = (int) $board_index_options['parent_id'];
		}

		if (!empty($board_index_options['include_categories'])) {
			$selects[] = 'c.name AS cat_name';
			$selects[] = 'c.description AS cat_desc';
			$selects[] = 'c.cat_order';
			array_unshift($joins, 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)');
			array_unshift($order, 'c.cat_order');
		}

		if (User::$me->is_guest) {
			$selects[] = '0 AS new_from';
			$selects[] = '1 AS is_read';
		} else {
			$selects[] = 'COALESCE(lb.id_msg, -1) + 1 AS new_from';
			$selects[] = '(CASE WHEN COALESCE(lb.id_msg, 0) >= b.id_last_msg THEN 1 ELSE 0 END) AS is_read';

			if (!empty($board_index_options['include_categories'])) {
				$selects[] = 'c.can_collapse';
			}

			$joins[] = 'LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})';
		}

		if (!empty(Theme::$current->settings['avatars_on_boardIndex'])) {
			$selects[] = 'mem.email_address';
			$selects[] = 'mem.avatar';
			$selects[] = 'COALESCE(am.id_attach, 0) AS member_id_attach';
			$selects[] = 'am.filename AS member_filename';
			$selects[] = 'am.attachment_type AS member_attach_type';

			$joins[] = 'LEFT JOIN {db_prefix}attachments AS am ON (am.id_member = m.id_member)';
		}

		// Give mods access to the query.
		IntegrationHook::call('integrate_pre_boardindex', [&$selects, &$params, &$joins, &$where, &$order]);

		// Start with empty arrays.
		$boards = [];
		$cat_boards = [];

		// Find all boards and categories, as well as related information.
		foreach (Board::queryData($selects, $params, $joins, $where, $order) as $row_board) {
			$row_board = array_filter($row_board, fn ($prop) => !is_null($prop));

			$parent = Board::$loaded[$row_board['id_parent']] ?? null;

			// Perhaps we are ignoring this board?
			$ignoreThisBoard = in_array($row_board['id_board'], User::$me->ignoreboards);
			$row_board['is_read'] = !empty($row_board['is_read']) || $ignoreThisBoard ? '1' : '0';

			if ($board_index_options['include_categories']) {
				// Haven't set this category yet.
				if (!isset(Category::$loaded[$row_board['id_cat']])) {
					$category = Category::init($row_board['id_cat'], [
						'id' => $row_board['id_cat'],
						'name' => $row_board['cat_name'],
						'description' => $row_board['cat_desc'],
						'order' => $row_board['cat_order'],
						'can_collapse' => !empty($row_board['can_collapse']),
						'is_collapsed' => !empty($row_board['can_collapse']) && !empty(Theme::$current->options['collapse_category_' . $row_board['id_cat']]),
						'href' => Config::$scripturl . '#c' . $row_board['id_cat'],
						'new' => false,
						'css_class' => '',
						'link' => '<a id="c' . $row_board['id_cat'] . '"></a>' . (!User::$me->is_guest ?
							'<a href="' . Config::$scripturl . '?action=unread;c=' . $row_board['id_cat'] . '" title="' . sprintf(Lang::$txt['new_posts_in_category'], $row_board['cat_name']) . '">' . $row_board['cat_name'] . '</a>' : $row_board['cat_name']),
					]);

					$category->parseDescription();
				} else {
					$category = Category::$loaded[$row_board['id_cat']];
				}

				// If this board has new posts in it (and isn't the recycle bin!) then the category is new.
				if (empty(Config::$modSettings['recycle_enable']) || Config::$modSettings['recycle_board'] != $row_board['id_board']) {
					$category->new |= empty($row_board['is_read']);
				}

				// Avoid showing category unread link where it only has redirection boards.
				$category->show_unread = !empty($category->show_unread) ? 1 : !$row_board['is_redirect'];

				$cat_boards = &$category->children;
			}

			// Is this a new board, or just another moderator?
			if (!isset(Board::$loaded[$row_board['id_board']]->type)) {
				$board = Board::init($row_board['id_board'], [
					'cat' => Category::init($row_board['id_cat']),
					'new' => empty($row_board['is_read']),
					'type' => $row_board['is_redirect'] ? 'redirect' : 'board',
					'name' => $row_board['board_name'],
					'description' => $row_board['description'],
					'short_description' => Utils::shorten($row_board['description'], 128),
					'link_moderators' => [],
					'link_moderator_groups' => [],
					'parent' => $row_board['id_parent'],
					'child_level' => $row_board['child_level'],
					'link_children' => [],
					'children_new' => false,
					'topics' => $row_board['num_topics'],
					'posts' => $row_board['num_posts'],
					'is_redirect' => $row_board['is_redirect'],
					'unapproved_topics' => $row_board['unapproved_topics'],
					'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
					'can_approve_posts' => !empty(User::$me->mod_cache['ap']) && (User::$me->mod_cache['ap'] == [0] || in_array($row_board['id_board'], User::$me->mod_cache['ap'])),
					'href' => Config::$scripturl . '?board=' . $row_board['id_board'] . '.0',
					'link' => '<a href="' . Config::$scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['board_name'] . '</a>',
					'board_class' => 'off',
					'css_class' => '',
					'last_post' => self::prepareLastPost($row_board),
				]);

				$board->parseDescription();

				// This is a parent board.
				if (!isset($parent) || $parent->id == $board_index_options['parent_id']) {
					// @todo Should this be called for every board, not just parent boards?
					IntegrationHook::call('integrate_boardindex_board', [&$cat_boards, $row_board]);

					// Add parent boards to the $boards list later used to fetch moderators.
					$boards[] = $row_board['id_board'];

					// We can do some of the figuring-out-what-icon now.
					// For certain types of thing we also set up what the tooltip is.
					if ($board->is_redirect) {
						$board->board_class = 'redirect';
						$board->board_tooltip = Lang::$txt['redirect_board'];
					} elseif ($board->new || User::$me->is_guest) {
						// If we're showing to guests, we want to give them the idea that something interesting is going on!
						$board->board_class = 'on';
						$board->board_tooltip = Lang::$txt['new_posts'];
					} else {
						$board->board_tooltip = Lang::$txt['old_posts'];
					}
				}
				// This is a child board.
				elseif (isset($parent) && $parent->parent == $board_index_options['parent_id']) {
					// Counting child board posts in the parent's totals?
					self::propagateStatsToParents($board, $board_index_options);

					// Update the icon if appropriate
					if ($parent->children_new && $parent->board_class == 'off') {
						$parent->board_class = 'on2';
						$parent->board_tooltip = Lang::$txt['new_posts'];
					}

					// This is easier to use in many cases for the theme....
					$parent->link_children[] = $board->link;
				}
				// A further descendent (grandchild, great-grandchild, etc.)
				else {
					self::propagateStatsToParents($board, $board_index_options);
				}
			}

			// Determine a global most recent topic.
			if (
				!empty($board_index_options['set_latest_post'])
				&& !empty($row_board['poster_time'])
				&& $row_board['poster_time'] > $latest_post['timestamp']
				&& !$ignoreThisBoard
			) {
				$latest_post = [
					'timestamp' => $row_board['poster_time'],
					'ref' => isset($parent) ? $parent->last_post : $board->last_post,
				];
			}
		}

		// Fetch the board's moderators and moderator groups
		$boards = array_unique($boards);
		$moderators = Board::getModerators($boards);
		$groups = Board::getModeratorGroups($boards);

		if ($board_index_options['include_categories']) {
			foreach (Category::$loaded as &$category) {
				foreach ($category->children as $board) {
					if (!empty($moderators[$board->id])) {
						$board->moderators = $moderators[$board->id];

						foreach ($moderators[$board->id] as $moderator) {
							$board->link_moderators[] = $moderator['link'];
						}
					}

					if (!empty($groups[$board->id])) {
						$board->moderator_groups = $groups[$board->id];

						foreach ($groups[$board->id] as $group) {
							$board->link_moderators[] = $group['link'];
							$board->link_moderator_groups[] = $group['link'];
						}
					}

					if (!empty($board->last_post)) {
						$board->last_post['last_post_message'] = sprintf(Lang::$txt['last_post_message'], $board->last_post['member']['link'], $board->last_post['link'], $board->last_post['timestamp'] > 0 ? $board->last_post['time'] : Lang::$txt['not_applicable']);
					}
				}
			}
		} else {
			$cat_boards = &Board::$loaded[$board_index_options['parent_id']]->children;

			foreach ($cat_boards as &$board) {
				if (!empty($moderators[$board->id])) {
					$board->moderators = $moderators[$board->id];

					foreach ($moderators[$board->id] as $moderator) {
						$board->link_moderators[] = $moderator['link'];
					}
				}

				if (!empty($groups[$board->id])) {
					$board->moderator_groups = $groups[$board->id];

					foreach ($groups[$board->id] as $group) {
						$board->link_moderators[] = $group['link'];
						$board->link_moderator_groups[] = $group['link'];
					}
				}

				if (!empty($board->last_post)) {
					$board->last_post['last_post_message'] = sprintf(Lang::$txt['last_post_message'], $board->last_post['member']['link'], $board->last_post['link'], $board->last_post['timestamp'] > 0 ? $board->last_post['time'] : Lang::$txt['not_applicable']);
				}
			}
		}

		if ($board_index_options['include_categories']) {
			Category::sort(Category::$loaded);
		} else {
			Board::sort($cat_boards);
		}

		// By now we should know the most recent post...if we wanna know it that is.
		if (!empty($board_index_options['set_latest_post']) && !empty($latest_post['ref'])) {
			$latest_post['ref']['time'] = Time::create('@' . $latest_post['ref']['timestamp'])->format();
			Utils::$context['latest_post'] = $latest_post['ref'];
		}

		// I can't remember why but trying to make a ternary to get this all in one line is actually a Very Bad Idea.
		if ($board_index_options['include_categories']) {
			IntegrationHook::call('integrate_getboardtree', [$board_index_options, &Category::$loaded]);
		} else {
			IntegrationHook::call('integrate_getboardtree', [$board_index_options, &$cat_boards]);
		}

		return $board_index_options['include_categories'] ? Category::$loaded : $cat_boards;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Prepares to show the board index.
	 *
	 * Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		Theme::loadTemplate('BoardIndex');
		Utils::$context['template_layers'][] = 'boardindex_outer';

		Utils::$context['page_title'] = sprintf(Lang::$txt['forum_index'], Utils::$context['forum_name']);

		// Set a canonical URL for this page.
		Utils::$context['canonical_url'] = Config::$scripturl;

		// Do not let search engines index anything if there is a random thing in $_GET.
		if (!empty($_GET)) {
			Utils::$context['robot_no_index'] = true;
		}

		// Replace the collapse and expand default alts.
		Theme::addJavaScriptVar('smf_expandAlt', Lang::$txt['show_category'], true);
		Theme::addJavaScriptVar('smf_collapseAlt', Lang::$txt['hide_category'], true);

		if (!empty(Theme::$current->settings['show_newsfader'])) {
			Theme::loadJavaScriptFile('slippry.min.js', [], 'smf_jquery_slippry');
			Theme::loadCSSFile('slider.min.css', [], 'smf_jquery_slider');
		}

		// Set a few minor things.
		Utils::$context['show_stats'] = User::$me->allowedTo('view_stats') && !empty(Config::$modSettings['trackStats']);
		Utils::$context['show_buddies'] = !empty(User::$me->buddies);
		Utils::$context['show_who'] = User::$me->allowedTo('who_view') && !empty(Config::$modSettings['who_enabled']);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Propagates statistics (e.g. post and topic counts) to parent boards.
	 *
	 * @param object $board An instance of SMF\Board.
	 * @param array $board_index_options The options passed to BoardIndex:get().
	 */
	protected static function propagateStatsToParents($board, $board_index_options): void
	{
		if ($board->is_redirect || empty($board->parent)) {
			return;
		}

		// If the parent is already loaded, this will just return it.
		// In the unlikely event that it isn't loaded, this will fix that.
		$parent = Board::init($board->parent);

		if (!empty($board_index_options['countChildPosts'])) {
			$parent->own_topics = $parent->own_topics ?? $parent->topics;
			$parent->own_posts = $parent->own_posts ?? $parent->posts;

			$parent->topics = $parent->own_topics + $board->topics;
			$parent->posts = $parent->own_posts + $board->posts;
		}

		if (($board->last_post['timestamp'] ?? 0) > ($parent->last_post['timestamp'] ?? 0)) {
			$parent->last_post = $board->last_post;
		}

		if (!empty($parent->parent)) {
			// Does this board contain new boards?
			$parent->children_new |= $board->new;

			if ($parent->parent != $board_index_options['parent_id']) {
				$parent->new |= $board->new;
			}

			// Continue propagating up the tree.
			if ($parent->id != $board_index_options['parent_id']) {
				self::propagateStatsToParents($parent, $board_index_options);
			}
		}
	}

	/**
	 * Prepares formatted data about the latest post in a board.
	 *
	 * Returned array contains at least the following keys:
	 *  - id (of the post)
	 *  - timestamp (a number that represents the time)
	 *  - time (formatted according to the user's preferences)
	 *  - topic (topic id)
	 *  - link
	 *  - href
	 *  - subject
	 *  - start (where they should go for the first unread post)
	 *  - member (which contains id, name, link, href, username)
	 *
	 * @param array $row_board Raw board data.
	 * @return array Formatted post data.
	 */
	protected static function prepareLastPost($row_board): array
	{
		if (empty($row_board['id_msg'])) {
			return [
				'timestamp' => 0,
				'href' => '',
				'link' => Lang::$txt['not_applicable'],
				'member' => [
					'id' => 0,
					'name' => Lang::$txt['not_applicable'],
					'username' => Lang::$txt['not_applicable'],
					'href' => '',
					'link' => Lang::$txt['not_applicable'],
				],
			];
		}

		Lang::censorText($row_board['subject']);
		$short_subject = Utils::shorten($row_board['subject'], 24);

		$msg = new Msg($row_board['id_msg'], [
			'id_topic' => $row_board['id_topic'],
			'id_board' => $row_board['id_board'],
			'poster_time' => (int) $row_board['poster_time'],
			'id_member' => $row_board['id_member'],
			'poster_name' => $row_board['real_name'],
			'subject' => $short_subject,
		]);

		$last_post = array_merge(
			$msg->format(0, [
				'do_permissions' => false,
				'do_icon' => false,
				'load_author' => false,
			]),
			[
				'start' => 'msg' . $row_board['new_from'],
				'topic' => $row_board['id_topic'],
				'href' => Config::$scripturl . '?topic=' . $row_board['id_topic'] . '.msg' . $row_board['id_msg'] . (empty($row_board['is_read']) ? ';boardseen' : '') . '#new',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row_board['id_topic'] . '.msg' . $row_board['id_msg'] . (empty($row_board['is_read']) ? ';boardseen' : '') . '#new' . '" title="' . $row_board['subject'] . '">' . $short_subject . '</a>',
			],
		);

		unset($msg, Msg::$loaded[$row_board['id_msg']]);

		if (!empty(Theme::$current->settings['avatars_on_boardIndex'])) {
			$last_post['member']['avatar'] = User::setAvatarData([
				'avatar' => $row_board['avatar'],
				'email' => $row_board['email_address'],
				'filename' => !empty($row_board['member_filename']) ? $row_board['member_filename'] : '',
			]);
		}

		IntegrationHook::call('integrate_boardindex_last_post', [&$last_post, $row_board]);

		return $last_post;
	}
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\BoardIndex::exportStatic')) {
	BoardIndex::exportStatic();
}

?>