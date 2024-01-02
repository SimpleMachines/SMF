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
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class shows the list of topics in a board.
 *
 * Although this class is not accessed using an ?action=... URL query, it
 * behaves like an action in every other way.
 */
class MessageIndex implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static array $backcompat = [
		'func_names' => [
			'call' => 'MessageIndex',
			'getBoardList' => 'getBoardList',
			'buildTopicContext' => 'buildTopicContext',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Default sort methods.
	 */
	public array $sort_methods = [
		'subject' => [
			'column' => 'mf.subject',
			'joins' => 'JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)',
			'asc_default' => true,
		],
		'starter' => [
			'column' => 'COALESCE(memf.real_name, mf.poster_name)',
			'joins' => 'JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)' . "\n\t\t\t" . 'LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)',
			'asc_default' => true,
		],
		'last_poster' => [
			'column' => 'COALESCE(meml.real_name, ml.poster_name)',
			'joins' => 'JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' . "\n\t\t\t" . 'LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)',
			'asc_default' => true,
		],
		'replies' => [
			'column' => 't.num_replies',
			'joins' => '',
			'asc_default' => true,
		],
		'views' => [
			'column' => 't.num_views',
			'joins' => '',
			'asc_default' => true,
		],
		'first_post' => [
			'column' => 't.id_topic',
			'joins' => '',
			'asc_default' => false,
		],
		'last_post' => [
			'column' => 't.id_last_msg',
			'joins' => '',
			'asc_default' => false,
		],
	];

	/**
	 * @var string
	 *
	 * Default sort method.
	 * Must be a key in $this->sort_methods.
	 */
	public string $sort_default = 'last_post';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Selected sort method.
	 */
	protected string $sort_by;

	/**
	 * @var string
	 *
	 * Selected sort column.
	 */
	protected string $sort_column;

	/**
	 * @var bool
	 *
	 * Whether to sort in ascending order or not.
	 */
	protected bool $ascending;

	/**
	 * @var bool
	 *
	 * Whether the $ascending value is the default for this sort method.
	 * Assume false until proven otherwise.
	 */
	protected bool $ascending_is_default = false;

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
	 * Shows the list of topics in this board, along with any child boards.
	 */
	public function execute(): void
	{
		$this->buildTopicList();
		$this->buildChildBoardIndex();

		// If there are children, but no topics and no ability to post topics...
		Utils::$context['no_topic_listing'] = !empty(Utils::$context['boards']) && empty(Utils::$context['topics']) && !Utils::$context['can_post_new'];

		$this->markViewed();
		$this->getWhoViewing();

		$this->buildQuickMod();
		$this->buildButtons();
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
	 * Buils and returns the list of available boards for a user.
	 *
	 * @param array $boardListOptions An array of options for the board list.
	 * @return array An array of board info.
	 */
	public static function getBoardList($boardListOptions = []): array
	{
		if (isset($boardListOptions['excluded_boards'], $boardListOptions['included_boards'])) {
			Lang::load('Errors');
			trigger_error(Lang::$txt['get_board_list_cannot_include_and_exclude'], E_USER_ERROR);
		}

		$where = [];
		$where_parameters = [];

		if (isset($boardListOptions['excluded_boards'])) {
			$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
			$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
		}

		if (isset($boardListOptions['included_boards'])) {
			$where[] = 'b.id_board IN ({array_int:included_boards})';
			$where_parameters['included_boards'] = $boardListOptions['included_boards'];
		}

		if (!empty($boardListOptions['ignore_boards'])) {
			$where[] = '{query_wanna_see_board}';
		} elseif (!empty($boardListOptions['use_permissions'])) {
			$where[] = '{query_see_board}';
		}

		if (!empty($boardListOptions['not_redirection'])) {
			$where[] = 'b.redirect = {string:blank_redirect}';
			$where_parameters['blank_redirect'] = '';
		}

		$request = Db::$db->query(
			'order_by_board_order',
			'SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level, b.redirect
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
			WHERE ' . implode('
				AND ', $where)),
			$where_parameters,
		);

		$return_value = [];

		if (Db::$db->num_rows($request) !== 0) {
			while ($row = Db::$db->fetch_assoc($request)) {
				if (!isset($return_value[$row['id_cat']])) {
					$return_value[$row['id_cat']] = [
						'id' => $row['id_cat'],
						'name' => $row['cat_name'],
						'boards' => [],
					];
				}

				$return_value[$row['id_cat']]['boards'][$row['id_board']] = [
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'child_level' => $row['child_level'],
					'redirect' => $row['redirect'],
					'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
				];
			}
		}
		Db::$db->free_result($request);

		Category::sort($return_value);

		return $return_value;
	}

	/**
	 * Processes information about topics.
	 * Populates Utils:$context['topics'] with the results.
	 *
	 * This is static so that it can be called by SMF\Actions\Unread, etc.
	 */
	public static function buildTopicContext(array $row)
	{
		// Reference the main color class.
		$colorClass = 'windowbg';

		// Does the theme support message previews?
		if (!empty(Config::$modSettings['preview_characters'])) {
			// Limit them to Config::$modSettings['preview_characters'] characters
			$row['first_body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['first_body'], $row['first_smileys'], $row['id_first_msg']), ['<br>' => '&#10;']));

			if (Utils::entityStrlen($row['first_body']) > Config::$modSettings['preview_characters']) {
				$row['first_body'] = Utils::entitySubstr($row['first_body'], 0, Config::$modSettings['preview_characters']) . '...';
			}

			// Censor the subject and message preview.
			Lang::censorText($row['first_subject']);
			Lang::censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg']) {
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			} else {
				$row['last_body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['last_body'], $row['last_smileys'], $row['id_last_msg']), ['<br>' => '&#10;']));

				if (Utils::entityStrlen($row['last_body']) > Config::$modSettings['preview_characters']) {
					$row['last_body'] = Utils::entitySubstr($row['last_body'], 0, Config::$modSettings['preview_characters']) . '...';
				}

				Lang::censorText($row['last_subject']);
				Lang::censorText($row['last_body']);
			}
		} else {
			$row['first_body'] = '';
			$row['last_body'] = '';
			Lang::censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg']) {
				$row['last_subject'] = $row['first_subject'];
			} else {
				Lang::censorText($row['last_subject']);
			}
		}

		// Decide how many pages the topic should have.
		if ($row['num_replies'] + 1 > Utils::$context['messages_per_page']) {
			// We can't pass start by reference.
			$start = -1;
			$pages = new PageIndex(Config::$scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $row['num_replies'] + 1, Utils::$context['messages_per_page'], true, false);

			// If we can use all, show all.
			if (!empty(Config::$modSettings['enableAllMessages']) && $row['num_replies'] + 1 < Config::$modSettings['enableAllMessages']) {
				$pages .= sprintf(strtr(Theme::$current->settings['page_index']['page'], ['{URL}' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0;all']), '', Lang::$txt['all']);
			}
		} else {
			$pages = '';
		}

		// We need to check the topic icons exist...
		if (!empty(Config::$modSettings['messageIconChecks_enable'])) {
			if (!isset(Utils::$context['icon_sources'][$row['first_icon']])) {
				Utils::$context['icon_sources'][$row['first_icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
			}

			if (!isset(Utils::$context['icon_sources'][$row['last_icon']])) {
				Utils::$context['icon_sources'][$row['last_icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
			}
		} else {
			if (!isset(Utils::$context['icon_sources'][$row['first_icon']])) {
				Utils::$context['icon_sources'][$row['first_icon']] = 'images_url';
			}

			if (!isset(Utils::$context['icon_sources'][$row['last_icon']])) {
				Utils::$context['icon_sources'][$row['last_icon']] = 'images_url';
			}
		}

		// Force the recycling icon if appropriate
		if (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $row['id_board']) {
			$row['first_icon'] = 'recycled';
			$row['last_icon'] = 'recycled';
		}

		// Is this topic pending approval, or does it have any posts pending approval?
		if (!empty($row['unapproved_posts']) && User::$me->allowedTo('approve_posts')) {
			$colorClass .= (!$row['approved'] ? ' approvetopic' : ' approvepost');
		}

		// Sticky topics should get a different color, too.
		if ($row['is_sticky']) {
			$colorClass .= ' sticky';
		}

		// Locked topics get special treatment as well.
		if ($row['locked']) {
			$colorClass .= ' locked';
		}

		// 'Print' the topic info.
		Utils::$context['topics'][$row['id_topic']] = array_merge($row, [
			'id' => $row['id_topic'],
			'first_post' => [
				'id' => $row['id_first_msg'],
				'member' => [
					'username' => $row['first_member_name'],
					'name' => $row['first_display_name'],
					'id' => $row['first_id_member'],
					'href' => !empty($row['first_id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
					'link' => !empty($row['first_id_member']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $row['first_display_name']) . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name'],
				],
				'time' => Time::create('@' . $row['first_poster_time'])->format(),
				'timestamp' => $row['first_poster_time'],
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'icon' => $row['first_icon'],
				'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>',
			],
			'last_post' => [
				'id' => $row['id_last_msg'],
				'member' => [
					'username' => $row['last_member_name'],
					'name' => $row['last_display_name'],
					'id' => $row['last_id_member'],
					'href' => !empty($row['last_id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
					'link' => !empty($row['last_id_member']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name'],
				],
				'time' => Time::create('@' . $row['last_poster_time'])->format(),
				'timestamp' => $row['last_poster_time'],
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'icon' => $row['last_icon'],
				'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . (User::$me->is_guest ? ('.' . (!empty(Theme::$current->options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / Utils::$context['messages_per_page'])) * Utils::$context['messages_per_page']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . (User::$me->is_guest ? ('.' . (!empty(Theme::$current->options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / Utils::$context['messages_per_page'])) * Utils::$context['messages_per_page']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>',
			],
			'is_sticky' => !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'is_redirect' => !empty($row['id_redirect_topic']),
			'is_poll' => Config::$modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
			'is_posted_in' => !empty($row['is_posted_in']),
			'is_watched' => false,
			'icon' => $row['first_icon'],
			'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
			'subject' => $row['first_subject'],
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'newtime' => $row['new_from'],
			'new_href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'pages' => $pages,
			'replies' => Lang::numberFormat($row['num_replies']),
			'views' => Lang::numberFormat($row['num_views']),
			'approved' => $row['approved'],
			'unapproved_posts' => $row['unapproved_posts'],
			'css_class' => $colorClass,
		]);

		if (!empty(Theme::$current->settings['avatars_on_indexes'])) {
			// Last post member avatar
			Utils::$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = User::setAvatarData([
				'avatar' => $row['avatar'],
				'email' => $row['email_address'],
				'filename' => !empty($row['last_member_filename']) ? $row['last_member_filename'] : '',
			]);

			// First post member avatar
			Utils::$context['topics'][$row['id_topic']]['first_post']['member']['avatar'] = User::setAvatarData([
				'avatar' => $row['first_member_avatar'],
				'email' => $row['first_member_mail'],
				'filename' => !empty($row['first_member_filename']) ? $row['first_member_filename'] : '',
			]);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Prepares to show the message index.
	 *
	 * Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (empty(Board::$info->id)) {
			ErrorHandler::fatalLang('no_board', false);
		}

		$this->checkRedirect();
		$this->preventPrefetch();

		$this->setSortMethod();
		$this->setPaginationAndLinks();

		$this->setModerators();
		$this->setUnapprovedPostsMessage();

		$this->setupTemplate();
		$this->setRobotNoIndex();
	}

	/**
	 * Redirects to the target URL for this board, if applicable.
	 */
	protected function checkRedirect(): void
	{
		if (Board::$info->redirect) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET num_posts = num_posts + 1
				WHERE id_board = {int:current_board}',
				[
					'current_board' => Board::$info->id,
				],
			);

			Utils::redirectexit(Board::$info->redirect);
		}
	}

	/**
	 * Blocks browser attempts to prefetch the message index.
	 */
	protected function preventPrefetch(): void
	{
		if (!User::$me->is_guest) {
			// We can't know they read it if we allow prefetches.
			// But we'll actually mark it read later after we've done everything else.
			if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
				ob_end_clean();
				Utils::sendHttpStatus(403, 'Prefetch Forbidden');

				die;
			}
		}
	}

	/**
	 * Determines how to sort the topics.
	 */
	protected function setSortMethod(): void
	{
		// This is a bit convoluted, but it's necessary to maintain backward
		// compatibility for the expected parameters of the integration hook.
		foreach ($this->sort_methods as $method => $sort_info) {
			$sort_columns[$method] = &$this->sort_methods[$method]['column'];
			$sort_joins[$method] = &$this->sort_methods[$method]['joins'];
			$sort_asc_defaults[$method] = &$this->sort_methods[$method]['asc_default'];
		}

		// Bring in any changes we want to make before the query.
		IntegrationHook::call('integrate_pre_messageindex', [&$sort_columns, &$sort_joins, &$sort_asc_defaults, &$this->sort_default]);

		// Default to sorting by last post descending.
		$this->sort_by = !isset($_REQUEST['sort']) || !isset($this->sort_methods[$_REQUEST['sort']]) ? $this->sort_default : $_REQUEST['sort'];

		$this->ascending = isset($_REQUEST['desc']) ? false : (isset($_REQUEST['asc']) ? true : $this->sort_methods[$this->sort_by]['asc_default']);

		$this->ascending_is_default = $this->ascending === $this->sort_methods[$this->sort_by]['asc_default'];

		$this->sort_column = $this->sort_methods[$this->sort_by]['column'];

		Utils::$context['sort_by'] = $this->sort_by;
		Lang::$txt['starter'] = Lang::$txt['started_by'];

		foreach ($this->sort_methods as $key => $val) {
			Utils::$context['topics_headers'][$key] = '<a href="' . Config::$scripturl . '?board=' . Utils::$context['current_board'] . '.' . $_REQUEST['start'] . ($this->sort_default == $key ? '' : ';sort=' . $key) . ($this->sort_by == $key && $this->ascending_is_default ? ($this->ascending ? ';desc' : ';asc') : '') . '">' . Lang::$txt[$key] . ($this->sort_by == $key ? ' <span class="main_icons sort_' . ($this->ascending ? 'up' : 'down') . '"></span>' : '') . '</a>';
		}
	}

	/**
	 * Constructs page index, sets next/prev/up links, etc.
	 */
	protected function setPaginationAndLinks(): void
	{
		// How many topics do we have in total?
		Board::$info->total_topics = User::$me->allowedTo('approve_posts') ? Board::$info->num_topics + Board::$info->unapproved_topics : Board::$info->num_topics + Board::$info->unapproved_user_topics;

		// View all the topics, or just a few?
		Utils::$context['topics_per_page'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['topics_per_page']) ? Theme::$current->options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'];

		Utils::$context['messages_per_page'] = $context['pageindex_multiplier'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		Utils::$context['maxindex'] = isset($_REQUEST['all']) && !empty(Config::$modSettings['enableAllMessages']) ? Board::$info->total_topics : Utils::$context['topics_per_page'];

		// Make sure the starting place makes sense and construct the page index.
		if ($this->sort_by !== $this->sort_default || !$this->ascending_is_default) {
			Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?board=' . Board::$info->id . '.%1$d' . ($this->sort_default == $this->sort_by ? '' : ';sort=' . $this->sort_by) . ($this->ascending_is_default ? '' : ($this->ascending ? ';asc' : ';desc')), $_REQUEST['start'], Board::$info->total_topics, Utils::$context['maxindex'], true);
		} else {
			Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?board=' . Board::$info->id . '.%1$d', $_REQUEST['start'], Board::$info->total_topics, Utils::$context['maxindex'], true);
		}

		Utils::$context['start'] = &$_REQUEST['start'];

		$can_show_all = !empty(Config::$modSettings['enableAllMessages']) && Utils::$context['maxindex'] > Config::$modSettings['enableAllMessages'];

		if (!($can_show_all && isset($_REQUEST['all']))) {
			Utils::$context['links'] = [
				'first' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?board=' . Board::$info->id . '.0' : '',
				'prev' => $_REQUEST['start'] >= Utils::$context['topics_per_page'] ? Config::$scripturl . '?board=' . Board::$info->id . '.' . ($_REQUEST['start'] - Utils::$context['topics_per_page']) : '',
				'next' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < Board::$info->total_topics ? Config::$scripturl . '?board=' . Board::$info->id . '.' . ($_REQUEST['start'] + Utils::$context['topics_per_page']) : '',
				'last' => $_REQUEST['start'] + Utils::$context['topics_per_page'] < Board::$info->total_topics ? Config::$scripturl . '?board=' . Board::$info->id . '.' . (floor((Board::$info->total_topics - 1) / Utils::$context['topics_per_page']) * Utils::$context['topics_per_page']) : '',
				'up' => Board::$info->parent == 0 ? Config::$scripturl . '?' : Config::$scripturl . '?board=' . Board::$info->parent . '.0',
			];
		}

		Utils::$context['page_info'] = [
			'current_page' => $_REQUEST['start'] / Utils::$context['topics_per_page'] + 1,
			'num_pages' => floor((Board::$info->total_topics - 1) / Utils::$context['topics_per_page']) + 1,
		];

		if (isset($_REQUEST['all']) && $can_show_all) {
			Utils::$context['maxindex'] = Config::$modSettings['enableAllMessages'];
			$_REQUEST['start'] = 0;
		}
	}

	/**
	 * The heart of the message index. Builds the list of topics to display.
	 */
	protected function buildTopicList(): void
	{
		// Set up the default topic icons. We'll need them below.
		Utils::$context['icon_sources'] = [];

		foreach (Utils::$context['stable_icons'] as $icon) {
			Utils::$context['icon_sources'][$icon] = 'images_url';
		}

		// Calculate the fastest way to get the topics.
		$start = (int) $_REQUEST['start'];

		if ($start > (Board::$info->total_topics - 1) / 2) {
			$this->ascending = !$this->ascending;
			$fake_ascending = true;
			Utils::$context['maxindex'] = Board::$info->total_topics < $start + Utils::$context['maxindex'] + 1 ? Board::$info->total_topics - $start : Utils::$context['maxindex'];
			$start = Board::$info->total_topics < $start + Utils::$context['maxindex'] + 1 ? 0 : Board::$info->total_topics - $start - Utils::$context['maxindex'];
		} else {
			$fake_ascending = false;
		}

		$topic_ids = [];
		Utils::$context['topics'] = [];

		// Grab the appropriate topic information...
		$params = [
			'current_board' => Board::$info->id,
			'current_member' => User::$me->id,
			'topic_list' => $topic_ids,
			'is_approved' => 1,
			'find_set_topics' => implode(',', $topic_ids),
			'start' => $start,
			'maxindex' => Utils::$context['maxindex'],
		];

		$selects = [];
		$joins = [];
		$main_where = [];
		$sort_where = [];

		IntegrationHook::call('integrate_message_index', [&$selects, &$joins, &$params, &$main_where, &$topic_ids, &$sort_where]);

		if (!empty(Config::$modSettings['enableParticipation']) && !User::$me->is_guest) {
			$enableParticipation = true;
		} else {
			$enableParticipation = false;
		}

		$sort_table = '
			SELECT t.id_topic, t.id_first_msg, t.id_last_msg' . (!empty($selects) ? (', ' . implode(', ', $selects)) : '') . '
			FROM {db_prefix}topics t
				' . $this->sort_methods[$this->sort_by]['joins'] . '
				' . (!empty($joins) ? implode("\n\t\t\t\t", $joins) : '') . '
			WHERE t.id_board = {int:current_board} '
				. (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
				AND (t.approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . (!empty($sort_where) ? '
				AND ' . implode("\n\t\t\t\tAND ", $sort_where) : '') . '
			ORDER BY is_sticky' . ($fake_ascending ? '' : ' DESC') . ', ' . $this->sort_column . ($this->ascending ? '' : ' DESC') . '
			LIMIT {int:maxindex}
				OFFSET {int:start} ';

		$result = Db::$db->query(
			'substring',
			'SELECT
				t.id_topic, t.num_replies, t.locked, t.num_views, t.is_sticky, t.id_poll, t.id_board, t.id_previous_board,
				' . (User::$me->is_guest ? '0' : 'COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1') . ' AS new_from,
				' . ($enableParticipation ? ' COALESCE(( SELECT 1 FROM {db_prefix}messages AS parti WHERE t.id_topic = parti.id_topic and parti.id_member = {int:current_member} LIMIT 1) , 0) as is_posted_in,
				' : '') . '
				t.id_last_msg, t.approved, t.unapproved_posts, ml.poster_time AS last_poster_time, t.id_redirect_topic,
				ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
				ml.poster_name AS last_member_name, ml.id_member AS last_id_member,' . (!empty(Theme::$current->settings['avatars_on_indexes']) ? ' meml.avatar, meml.email_address, memf.avatar AS first_member_avatar, memf.email_address AS first_member_mail, COALESCE(af.id_attach, 0) AS first_member_id_attach, af.filename AS first_member_filename, af.attachment_type AS first_member_attach_type, COALESCE(al.id_attach, 0) AS last_member_id_attach, al.filename AS last_member_filename, al.attachment_type AS last_member_attach_type,' : '') . '
				COALESCE(meml.real_name, ml.poster_name) AS last_display_name, t.id_first_msg,
				mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
				mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
				COALESCE(memf.real_name, mf.poster_name) AS first_display_name, ' . (!empty(Config::$modSettings['preview_characters']) ? '
				SUBSTRING(ml.body, 1, ' . (Config::$modSettings['preview_characters'] + 256) . ') AS last_body,
				SUBSTRING(mf.body, 1, ' . (Config::$modSettings['preview_characters'] + 256) . ') AS first_body,' : '') . 'ml.smileys_enabled AS last_smileys, mf.smileys_enabled AS first_smileys
				' . (!empty($selects) ? (', ' . implode(', ', $selects)) : '') . '
			FROM (' . $sort_table . ') as st
				JOIN {db_prefix}topics AS t ON (st.id_topic = t.id_topic)
				JOIN {db_prefix}messages AS ml ON (ml.id_msg = st.id_last_msg)
				JOIN {db_prefix}messages AS mf ON (mf.id_msg = st.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . (!empty(Theme::$current->settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = memf.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '' . (User::$me->is_guest ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') . '
				' . (!empty($joins) ? implode("\n\t\t\t\t", $joins) : '') . '
				' . (!empty($main_where) ? ' WHERE ' . implode("\n\t\t\t\tAND ", $main_where) : '') . '
			ORDER BY is_sticky' . ($fake_ascending ? '' : ' DESC') . ', ' . $this->sort_column . ($this->ascending ? '' : ' DESC'),
			$params,
		);

		// Begin 'printing' the message index for current board.
		while ($row = Db::$db->fetch_assoc($result)) {
			if ($row['id_poll'] > 0 && Config::$modSettings['pollMode'] == '0') {
				continue;
			}

			$topic_ids[] = $row['id_topic'];

			self::buildTopicContext($row);
		}
		Db::$db->free_result($result);

		// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
		if ($fake_ascending) {
			Utils::$context['topics'] = array_reverse(Utils::$context['topics'], true);
		}
	}

	/**
	 * Gets the child boards of this board, if applicable.
	 */
	protected function buildChildBoardIndex(): void
	{
		$boardIndexOptions = [
			'include_categories' => false,
			'base_level' => Board::$info->child_level + 1,
			'parent_id' => Board::$info->id,
			'set_latest_post' => false,
			'countChildPosts' => !empty(Config::$modSettings['countChildPosts']),
		];
		Utils::$context['boards'] = BoardIndex::get($boardIndexOptions);
	}

	/**
	 * Mark current and parent boards as seen.
	 */
	protected function markViewed(): void
	{
		if (!User::$me->is_guest) {
			Db::$db->insert(
				'replace',
				'{db_prefix}log_boards',
				['id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'],
				[Config::$modSettings['maxMsgID'], User::$me->id, Board::$info->id],
				['id_member', 'id_board'],
			);

			if (!empty(Board::$info->parent_boards)) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}log_boards
					SET id_msg = {int:id_msg}
					WHERE id_member = {int:current_member}
						AND id_board IN ({array_int:board_list})',
					[
						'current_member' => User::$me->id,
						'board_list' => array_keys(Board::$info->parent_boards),
						'id_msg' => Config::$modSettings['maxMsgID'],
					],
				);

				// We've seen all these boards now!
				foreach (Board::$info->parent_boards as $k => $dummy) {
					if (isset($_SESSION['topicseen_cache'][$k])) {
						unset($_SESSION['topicseen_cache'][$k]);
					}
				}
			}

			if (isset($_SESSION['topicseen_cache'][Board::$info->id])) {
				unset($_SESSION['topicseen_cache'][Board::$info->id]);
			}

			$request = Db::$db->query(
				'',
				'SELECT id_topic, id_board, sent
				FROM {db_prefix}log_notify
				WHERE id_member = {int:current_member}
					AND (' . (!empty(Utils::$context['topics']) ? 'id_topic IN ({array_int:topics}) OR ' : '') . 'id_board = {int:current_board})',
				[
					'current_board' => Board::$info->id,
					'topics' => !empty(Utils::$context['topics']) ? array_keys(Utils::$context['topics']) : [],
					'current_member' => User::$me->id,
				],
			);
			Utils::$context['is_marked_notify'] = false; // this is for the *board* only

			while ($row = Db::$db->fetch_assoc($request)) {
				if (!empty($row['id_board'])) {
					Utils::$context['is_marked_notify'] = true;
					$board_sent = $row['sent'];
				}

				if (!empty($row['id_topic'])) {
					Utils::$context['topics'][$row['id_topic']]['is_watched'] = true;
				}
			}
			Db::$db->free_result($request);

			if (Utils::$context['is_marked_notify'] && !empty($board_sent)) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}log_notify
					SET sent = {int:is_sent}
					WHERE id_member = {int:current_member}
						AND id_board = {int:current_board}',
					[
						'current_board' => Board::$info->id,
						'current_member' => User::$me->id,
						'is_sent' => 0,
					],
				);
			}

			$pref = Notify::getNotifyPrefs(User::$me->id, ['board_notify', 'board_notify_' . Board::$info->id], true);
			$pref = !empty($pref[User::$me->id]) ? $pref[User::$me->id] : [];
			$pref = $pref['board_notify_' . Board::$info->id] ?? (!empty($pref['board_notify']) ? $pref['board_notify'] : 0);
			Utils::$context['board_notification_mode'] = !Utils::$context['is_marked_notify'] ? 1 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));
		} else {
			Utils::$context['is_marked_notify'] = false;
			Utils::$context['board_notification_mode'] = 1;
		}
	}

	/**
	 * Nosey, nosey - who's viewing this board?
	 */
	protected function getWhoViewing(): void
	{
		if (!empty(Theme::$current->settings['display_who_viewing'])) {
			Utils::$context['view_members'] = [];
			Utils::$context['view_members_list'] = [];
			Utils::$context['view_num_hidden'] = 0;

			$request = Db::$db->query(
				'',
				'SELECT
					lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
					mg.online_color, mg.id_group, mg.group_name
				FROM {db_prefix}log_online AS lo
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_member_group} THEN mem.id_post_group ELSE mem.id_group END)
				WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
				[
					'reg_member_group' => 0,
					'in_url_string' => '"board":' . Board::$info->id,
					'session' => User::$me->is_guest ? 'ip' . User::$me->ip : session_id(),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (empty($row['id_member'])) {
					continue;
				}

				if (!empty($row['online_color'])) {
					$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
				} else {
					$link = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
				}

				$is_buddy = in_array($row['id_member'], User::$me->buddies);

				if ($is_buddy) {
					$link = '<strong>' . $link . '</strong>';
				}

				if (!empty($row['show_online']) || User::$me->allowedTo('moderate_forum')) {
					Utils::$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;
				}

				// @todo why are we filling this array of data that are just counted (twice) and discarded? ???
				Utils::$context['view_members'][$row['log_time'] . $row['member_name']] = [
					'id' => $row['id_member'],
					'username' => $row['member_name'],
					'name' => $row['real_name'],
					'group' => $row['id_group'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => $link,
					'is_buddy' => $is_buddy,
					'hidden' => empty($row['show_online']),
				];

				if (empty($row['show_online'])) {
					Utils::$context['view_num_hidden']++;
				}
			}
			Utils::$context['view_num_guests'] = Db::$db->num_rows($request) - count(Utils::$context['view_members']);
			Db::$db->free_result($request);

			// Put them in "last clicked" order.
			krsort(Utils::$context['view_members_list']);
			krsort(Utils::$context['view_members']);
		}
	}

	/**
	 * Prepares contextual info about the modertors of this board.
	 */
	protected function setModerators(): void
	{
		// Build a list of the board's moderators.
		Utils::$context['moderators'] = &Board::$info->moderators;
		Utils::$context['moderator_groups'] = &Board::$info->moderator_groups;
		Utils::$context['link_moderators'] = [];

		if (!empty(Board::$info->moderators)) {
			foreach (Board::$info->moderators as $mod) {
				Utils::$context['link_moderators'][] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . Lang::$txt['board_moderator'] . '">' . $mod['name'] . '</a>';
			}
		}

		if (!empty(Board::$info->moderator_groups)) {
			// By default just tack the moderator groups onto the end of the members
			foreach (Board::$info->moderator_groups as $mod_group) {
				Utils::$context['link_moderators'][] = '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $mod_group['id'] . '" title="' . Lang::$txt['board_moderator'] . '">' . $mod_group['name'] . '</a>';
			}
		}

		// Now we tack the info onto the end of the linktree
		if (!empty(Utils::$context['link_moderators'])) {
			Utils::$context['linktree'][count(Utils::$context['linktree']) - 1]['extra_after'] = '<span class="board_moderators">(' . (count(Utils::$context['link_moderators']) == 1 ? Lang::$txt['moderator'] : Lang::$txt['moderators']) . ': ' . implode(', ', Utils::$context['link_moderators']) . ')</span>';
		}
	}

	/**
	 * Sets the unapproved posts message.
	 */
	protected function setUnapprovedPostsMessage(): void
	{
		// If we can view unapproved messages and there are some build up a list.
		if (User::$me->allowedTo('approve_posts') && (Board::$info->unapproved_topics || Board::$info->unapproved_posts)) {
			$untopics = Board::$info->unapproved_topics ? '<a href="' . Config::$scripturl . '?action=moderate;area=postmod;sa=topics;brd=' . Board::$info->id . '">' . Board::$info->unapproved_topics . '</a>' : 0;

			$unposts = Board::$info->unapproved_posts ? '<a href="' . Config::$scripturl . '?action=moderate;area=postmod;sa=posts;brd=' . Board::$info->id . '">' . (Board::$info->unapproved_posts - Board::$info->unapproved_topics) . '</a>' : 0;

			Utils::$context['unapproved_posts_message'] = sprintf(
				Lang::$txt['there_are_unapproved_topics'],
				$untopics,
				$unposts,
				Config::$scripturl . '?action=moderate;area=postmod;sa=' . (Board::$info->unapproved_topics ? 'topics' : 'posts') . ';brd=' . Board::$info->id,
			);
		}

		// Show a message in case a recently posted message became unapproved.
		Utils::$context['becomesUnapproved'] = !empty($_SESSION['becomesUnapproved']);
		unset($_SESSION['becomesUnapproved']);
	}

	/**
	 * Loads the template and sets some related contextual info.
	 */
	protected function setupTemplate(): void
	{
		Theme::loadTemplate('MessageIndex');

		// Javascript for inline editing.
		Theme::loadJavaScriptFile('topic.js', ['defer' => false, 'minimize' => true], 'smf_topic');

		// 'Print' the header and board info.
		Utils::$context['page_title'] = strip_tags(Board::$info->name);

		Board::$info->parseDescription();

		Utils::$context['name'] = Board::$info->name;
		Utils::$context['description'] = Board::$info->description;

		if (!empty(Board::$info->description)) {
			Utils::$context['meta_description'] = strip_tags(Board::$info->description);
		}

		// Set a canonical URL for this page.
		Utils::$context['canonical_url'] = Config::$scripturl . '?board=' . Board::$info->id . '.' . Utils::$context['start'];

		Utils::$context['can_mark_notify'] = !User::$me->is_guest;
		Utils::$context['can_post_new'] = User::$me->allowedTo('post_new') || (Config::$modSettings['postmod_active'] && User::$me->allowedTo('post_unapproved_topics'));
		Utils::$context['can_post_poll'] = Config::$modSettings['pollMode'] == '1' && User::$me->allowedTo('poll_post') && Utils::$context['can_post_new'];
		Utils::$context['can_moderate_forum'] = User::$me->allowedTo('moderate_forum');
		Utils::$context['can_approve_posts'] = User::$me->allowedTo('approve_posts');

		Utils::$context['jump_to'] = [
			'label' => addslashes(Utils::htmlspecialcharsDecode(Lang::$txt['jump_to'])),
			'board_name' => strtr(Utils::htmlspecialchars(strip_tags(Board::$info->name)), ['&amp;' => '&']),
			'child_level' => Board::$info->child_level,
		];
	}

	/**
	 * Tells search engines not to index pages they shouldn't.
	 */
	protected function setRobotNoIndex(): void
	{
		// Right, let's only index normal stuff!
		if (count($_GET) > 1) {
			$session_name = session_name();

			foreach ($_GET as $k => $v) {
				if (!in_array($k, ['board', 'start', $session_name])) {
					Utils::$context['robot_no_index'] = true;
				}
			}
		}

		if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % Utils::$context['topics_per_page'] != 0)) {
			Utils::$context['robot_no_index'] = true;
		}
	}

	/**
	 * Figures out which quick moderation actions the current user can perform.
	 */
	protected function buildQuickMod(): void
	{
		// Is Quick Moderation active/needed?
		if (!empty(Theme::$current->options['display_quick_mod']) && !empty(Utils::$context['topics'])) {
			Utils::$context['can_markread'] = User::$me->is_logged;
			Utils::$context['can_lock'] = User::$me->allowedTo('lock_any');
			Utils::$context['can_sticky'] = User::$me->allowedTo('make_sticky');
			Utils::$context['can_move'] = User::$me->allowedTo('move_any');
			Utils::$context['can_remove'] = User::$me->allowedTo('remove_any');
			Utils::$context['can_merge'] = User::$me->allowedTo('merge_any');
			// Ignore approving own topics as it's unlikely to come up...
			Utils::$context['can_approve'] = Config::$modSettings['postmod_active'] && User::$me->allowedTo('approve_posts') && !empty(Board::$info->unapproved_topics);
			// Can we restore topics?
			Utils::$context['can_restore'] = User::$me->allowedTo('move_any') && !empty(Board::$info->recycle);

			if (User::$me->is_admin || Config::$modSettings['topic_move_any']) {
				Utils::$context['can_move_any'] = true;
			} else {
				// We'll use this in a minute
				$boards_allowed = User::$me->boardsAllowedTo('post_new');

				// How many boards can you do this on besides this one?
				Utils::$context['can_move_any'] = count($boards_allowed) > 1;
			}

			// Set permissions for all the topics.
			foreach (Utils::$context['topics'] as $t => $topic) {
				$started = $topic['first_post']['member']['id'] == User::$me->id;
				Utils::$context['topics'][$t]['quick_mod'] = [
					'lock' => User::$me->allowedTo('lock_any') || ($started && User::$me->allowedTo('lock_own')),
					'sticky' => User::$me->allowedTo('make_sticky'),
					'move' => (User::$me->allowedTo('move_any') || ($started && User::$me->allowedTo('move_own')) && Utils::$context['can_move_any']),
					'modify' => User::$me->allowedTo('modify_any') || ($started && User::$me->allowedTo('modify_own')),
					'remove' => User::$me->allowedTo('remove_any') || ($started && User::$me->allowedTo('remove_own')),
					'approve' => Utils::$context['can_approve'] && $topic['unapproved_posts'],
				];
				Utils::$context['can_lock'] |= ($started && User::$me->allowedTo('lock_own'));
				Utils::$context['can_move'] |= ($started && User::$me->allowedTo('move_own') && Utils::$context['can_move_any']);
				Utils::$context['can_remove'] |= ($started && User::$me->allowedTo('remove_own'));
			}

			// Can we use quick moderation checkboxes?
			if (Theme::$current->options['display_quick_mod'] == 1) {
				Utils::$context['can_quick_mod'] = User::$me->is_logged || Utils::$context['can_approve'] || Utils::$context['can_remove'] || Utils::$context['can_lock'] || Utils::$context['can_sticky'] || Utils::$context['can_move'] || Utils::$context['can_merge'] || Utils::$context['can_restore'];
			}
			// Or the icons?
			else {
				Utils::$context['can_quick_mod'] = Utils::$context['can_remove'] || Utils::$context['can_lock'] || Utils::$context['can_sticky'] || Utils::$context['can_move'];
			}
		}

		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1) {
			// Sets Utils::$context['qmod_actions']
			// This is also where the integrate_quick_mod_actions hook now lives.
			QuickModeration::getActions();
		}
	}

	/**
	 * Build the message index button array.
	 */
	protected function buildButtons(): void
	{
		Utils::$context['normal_buttons'] = [];

		if (Utils::$context['can_post_new']) {
			Utils::$context['normal_buttons']['new_topic'] = ['text' => 'new_topic', 'image' => 'new_topic.png', 'lang' => true, 'url' => Config::$scripturl . '?action=post;board=' . Utils::$context['current_board'] . '.0', 'active' => true];
		}

		if (Utils::$context['can_post_poll']) {
			Utils::$context['normal_buttons']['post_poll'] = ['text' => 'new_poll', 'image' => 'new_poll.png', 'lang' => true, 'url' => Config::$scripturl . '?action=post;board=' . Utils::$context['current_board'] . '.0;poll'];
		}

		if (User::$me->is_logged) {
			Utils::$context['normal_buttons']['markread'] = ['text' => 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => Config::$scripturl . '?action=markasread;sa=board;board=' . Utils::$context['current_board'] . '.0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		if (Utils::$context['can_mark_notify']) {
			Utils::$context['normal_buttons']['notify'] = [
				'lang' => true,
				'text' => 'notify_board_' . Utils::$context['board_notification_mode'],
				'sub_buttons' => [
					[
						'text' => 'notify_board_1',
						'url' => Config::$scripturl . '?action=notifyboard;board=' . Board::$info->id . ';mode=1;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
					[
						'text' => 'notify_board_2',
						'url' => Config::$scripturl . '?action=notifyboard;board=' . Board::$info->id . ';mode=2;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
					[
						'text' => 'notify_board_3',
						'url' => Config::$scripturl . '?action=notifyboard;board=' . Board::$info->id . ';mode=3;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
					],
				],
			];
		}

		// Allow adding new buttons easily.
		// Note: Utils::$context['normal_buttons'] is added for backward compatibility with 2.0, but is deprecated and should not be used
		IntegrationHook::call('integrate_messageindex_buttons', [&Utils::$context['normal_buttons']]);
	}
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\MessageIndex::exportStatic')) {
	MessageIndex::exportStatic();
}

?>