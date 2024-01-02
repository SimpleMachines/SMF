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
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Finds and retrieves information about new posts and topics.
 */
class Unread implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'UnreadTopics',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Ways to sort the posts.
	 * Keys are requestable methods, values are SQL ORDER BY statements
	 */
	public array $sort_methods = [
		'subject' => 'ms.subject',
		'starter' => 'COALESCE(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Either 'query_see_board' or 'query_wanna_see_board'.
	 */
	protected string $see_board = 'query_wanna_see_board';

	/**
	 * @var string
	 *
	 * Name of the sub-template to use.
	 */
	protected string $sub_template = 'unread';

	/**
	 * @var bool
	 *
	 * Whether we are getting topics or replies.
	 */
	protected bool $is_topics = true;

	/**
	 * @var string
	 *
	 * Name of this action for the linktree
	 */
	protected string $linktree_name;

	/**
	 * @var string
	 *
	 * Base URL for this action.
	 */
	protected string $action_url;

	/**
	 * @var bool
	 *
	 * Whether to sort in ascending or descending order.
	 */
	protected bool $ascending;

	/**
	 * @var array
	 *
	 * The boards to look in.
	 */
	protected array $boards = [];

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
	 * Earliest unread message.
	 */
	protected int $earliest_msg = 0;

	/**
	 * @var array
	 *
	 * Columns for the SQL SELECT clause. This part is the same for each query.
	 * More columns will be added at runtime if avatars are shown on indices.
	 */
	protected array $selects = [
		'ms.subject AS first_subject',
		'ms.poster_time AS first_poster_time',
		'ms.id_topic',
		't.id_board',
		'b.name AS bname',
		't.num_replies',
		't.num_views',
		'ms.id_member AS first_id_member',
		'ml.id_member AS last_id_member',
		'ml.poster_time AS last_poster_time',
		'ms.poster_name as first_member_name',
		'ml.poster_name as last_member_name',
		'COALESCE(mems.real_name, ms.poster_name) AS first_display_name',
		'COALESCE(meml.real_name, ml.poster_name) AS last_display_name',
		'ml.subject AS last_subject',
		'ml.icon AS last_icon',
		'ms.icon AS first_icon',
		't.id_poll',
		't.is_sticky',
		't.locked',
		'ml.modified_time AS last_modified_time',
		'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from',
		'SUBSTRING(ml.body, 1, 385) AS last_body',
		'SUBSTRING(ms.body, 1, 385) AS first_body',
		'ml.smileys_enabled AS last_smileys',
		'ms.smileys_enabled AS first_smileys',
		't.id_first_msg',
		't.id_last_msg',
		'ml.id_msg_modified',
		't.approved',
		't.unapproved_posts',
	];

	/**
	 * @var int
	 *
	 * Which temporary table we using, if any.
	 */
	protected int $have_temp_table = 0;

	/**
	 * @var object
	 *
	 * Database request to get the topics.
	 */
	protected object $topic_request;

	/**
	 * @var int
	 *
	 * How many unread topics we found.
	 */
	protected int $num_topics = 0;

	/**
	 * @var int
	 *
	 * Lowest unread message ID.
	 */
	protected int $min_message = 0;

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
	 * Find unread topics and replies.
	 */
	public function execute(): void
	{
		$this->getBoards();
		$this->setSortMethod();
		$this->getCatName();

		$this->finalizeSelects();
		$this->getEarliestMsg();
		$this->setTopicRequest();

		$this->setPaginationAndLinks();

		// If they've read everything, we're done here.
		if (empty($this->num_topics)) {
			return;
		}

		$this->getTopics();
		$this->buildButtons();

		Utils::$context['no_topic_listing'] = empty(Utils::$context['topics']);

		// Allow helpdesks and bug trackers and what not to add their own unread data (just add a template_layer to show custom stuff in the template!)
		IntegrationHook::call('integrate_unread_list');
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
		// Guests can't have unread things, we don't know anything about them.
		User::$me->kickIfGuest();

		// Prefetching + lots of MySQL work = bad mojo.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
			ob_end_clean();
			Utils::sendHttpStatus(403);

			die;
		}

		Utils::$context['topics'] = [];

		Utils::$context['showCheckboxes'] = !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1;

		Utils::$context['showing_all_topics'] = isset($_GET['all']);

		Utils::$context['start'] = (int) $_REQUEST['start'];

		Utils::$context['topics_per_page'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['topics_per_page']) ? Theme::$current->options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'];

		Utils::$context['messages_per_page'] = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		Utils::$context['page_title'] = Utils::$context['showing_all_topics'] ? Lang::$txt['unread_topics_all'] : Lang::$txt['unread_topics_visit'];

		$this->linktree_name = Lang::$txt['unread_topics_visit'];
		$this->action_url = Config::$scripturl . '?action=unread';

		if (Utils::$context['showing_all_topics']) {
			$this->checkLoadAverageAll();
		} else {
			$this->checkLoadAverage();
		}

		Theme::loadTemplate('Recent');
		Theme::loadTemplate('MessageIndex');
		Utils::$context['sub_template'] = $this->sub_template;

		// Setup the default topic icons... for checking they exist and the like ;)
		Utils::$context['icon_sources'] = [];

		foreach (Utils::$context['stable_icons'] as $icon) {
			Utils::$context['icon_sources'][$icon] = 'images_url';
		}
	}

	/**
	 * Checks that the load averages aren't too high to show unread posts.
	 */
	protected function checkLoadAverage()
	{
		if (empty(Utils::$context['load_average'])) {
			return;
		}

		if (empty(Config::$modSettings['loadavg_unread'])) {
			return;
		}

		if (Utils::$context['load_average'] >= Config::$modSettings['loadavg_unread']) {
			ErrorHandler::fatalLang('loadavg_unread_disabled', false);
		}
	}

	/**
	 * Checks that the load averages aren't too high to show all unread posts.
	 */
	protected function checkLoadAverageAll()
	{
		if (empty(Utils::$context['load_average'])) {
			return;
		}

		if (empty(Config::$modSettings['loadavg_allunread'])) {
			return;
		}

		if (Utils::$context['load_average'] >= Config::$modSettings['loadavg_allunread']) {
			ErrorHandler::fatalLang('loadavg_allunread_disabled', false);
		}
	}

	/**
	 * Figures out what boards we want to get messages from.
	 *
	 * Sets $this->boards, $this->query_this_board, and $this->query_parameters.
	 *
	 * @todo Break this up further.
	 */
	protected function getBoards()
	{
		// Are we specifying any specific board?
		if (isset($_REQUEST['children']) && (!empty(Board::$info->id) || !empty($_REQUEST['boards']))) {
			if (!empty($_REQUEST['boards'])) {
				$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);

				foreach ($_REQUEST['boards'] as $b) {
					$this->boards[] = (int) $b;
				}
			}

			if (!empty(Board::$info->id)) {
				$this->boards[] = (int) Board::$info->id;
			}

			// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
			$request = Db::$db->query(
				'',
				'SELECT b.id_board, b.id_parent
				FROM {db_prefix}boards AS b
				WHERE {query_wanna_see_board}
					AND b.child_level > {int:no_child}
					AND b.id_board NOT IN ({array_int:boards})
				ORDER BY child_level ASC',
				[
					'no_child' => 0,
					'boards' => $this->boards,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (in_array($row['id_parent'], $this->boards)) {
					$this->boards[] = $row['id_board'];
				}
			}
			Db::$db->free_result($request);

			if (empty($this->boards)) {
				ErrorHandler::fatalLang('error_no_boards_selected');
			}

			$this->query_this_board = 'id_board IN ({array_int:boards})';
			$this->query_parameters['boards'] = $this->boards;
			Utils::$context['querystring_board_limits'] = ';boards=' . implode(',', $this->boards) . ';start=%1$d';
		} elseif (!empty(Board::$info->id)) {
			$this->query_this_board = 'id_board = {int:board}';
			$this->query_parameters['board'] = Board::$info->id;
			Utils::$context['querystring_board_limits'] = ';board=' . Board::$info->id . '.%1$d';
		} elseif (!empty($_REQUEST['boards'])) {
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);

			foreach ($_REQUEST['boards'] as $i => $b) {
				$_REQUEST['boards'][$i] = (int) $b;
			}

			$request = Db::$db->query(
				'',
				'SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE {query_see_board}
					AND b.id_board IN ({array_int:board_list})',
				[
					'board_list' => $_REQUEST['boards'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->boards[] = $row['id_board'];
			}
			Db::$db->free_result($request);

			if (empty($this->boards)) {
				ErrorHandler::fatalLang('error_no_boards_selected');
			}

			$this->query_this_board = 'id_board IN ({array_int:boards})';
			$this->query_parameters['boards'] = $this->boards;
			Utils::$context['querystring_board_limits'] = ';boards=' . implode(',', $this->boards) . ';start=%1$d';
		} elseif (!empty($_REQUEST['c'])) {
			$_REQUEST['c'] = explode(',', $_REQUEST['c']);

			foreach ($_REQUEST['c'] as $i => $c) {
				$_REQUEST['c'][$i] = (int) $c;
			}

			$request = Db::$db->query(
				'',
				'SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE ' . User::$me->{$this->see_board} . '
					AND b.id_cat IN ({array_int:id_cat})',
				[
					'id_cat' => $_REQUEST['c'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->boards[] = $row['id_board'];
			}
			Db::$db->free_result($request);

			if (empty($this->boards)) {
				ErrorHandler::fatalLang('error_no_boards_selected');
			}

			$this->query_this_board = 'id_board IN ({array_int:boards})';
			$this->query_parameters['boards'] = $this->boards;
			Utils::$context['querystring_board_limits'] = ';c=' . implode(',', $_REQUEST['c']) . ';start=%1$d';
		} else {
			// Don't bother to show deleted posts!
			$request = Db::$db->query(
				'',
				'SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE ' . User::$me->{$this->see_board} . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
					AND b.id_board != {int:recycle_board}' : ''),
				[
					'recycle_board' => (int) Config::$modSettings['recycle_board'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->boards[] = $row['id_board'];
			}
			Db::$db->free_result($request);

			if (empty($this->boards)) {
				ErrorHandler::fatalLang('error_no_boards_available', false);
			}

			$this->query_this_board = 'id_board IN ({array_int:boards})';
			$this->query_parameters['boards'] = $this->boards;
			Utils::$context['querystring_board_limits'] = ';start=%1$d';
			Utils::$context['no_board_limits'] = true;
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
	 * Figures out how to sort the results.
	 */
	protected function setSortMethod()
	{
		// We only know these.
		if (isset($_REQUEST['sort']) && !in_array($_REQUEST['sort'], array_keys($this->sort_methods))) {
			$_REQUEST['sort'] = 'last_post';
		}

		// The default is the most logical: newest first.
		if (!isset($_REQUEST['sort']) || !isset($this->sort_methods[$_REQUEST['sort']])) {
			Utils::$context['sort_by'] = 'last_post';
			$_REQUEST['sort'] = 't.id_last_msg';
			$this->ascending = isset($_REQUEST['asc']);

			Utils::$context['querystring_sort_limits'] = $this->ascending ? ';asc' : '';
		}
		// But, for other methods the default sort is ascending.
		else {
			Utils::$context['sort_by'] = $_REQUEST['sort'];
			$_REQUEST['sort'] = $this->sort_methods[$_REQUEST['sort']];
			$this->ascending = !isset($_REQUEST['desc']);

			Utils::$context['querystring_sort_limits'] = ';sort=' . Utils::$context['sort_by'] . ($this->ascending ? '' : ';desc');
		}

		Utils::$context['sort_direction'] = $this->ascending ? 'up' : 'down';

		Lang::$txt['starter'] = Lang::$txt['started_by'];

		foreach ($this->sort_methods as $key => $val) {
			Utils::$context['topics_headers'][$key] = '<a href="' . $this->action_url . (Utils::$context['showing_all_topics'] ? ';all' : '') . Utils::$context['querystring_board_limits'] . ';sort=' . $key . (Utils::$context['sort_by'] == $key && Utils::$context['sort_direction'] == 'up' ? ';desc' : '') . '">' . Lang::$txt[$key] . (Utils::$context['sort_by'] == $key ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '') . '</a>';
		}
	}

	/**
	 * Constructs page index, sets the linktree, next/prev/up links, etc.
	 */
	protected function setPaginationAndLinks()
	{
		$not_first_page = Utils::$context['start'] >= Utils::$context['topics_per_page'];
		$not_last_page = Utils::$context['start'] + Utils::$context['topics_per_page'] < $this->num_topics;

		$url_limits = [
			'first' => sprintf(Utils::$context['querystring_board_limits'], 0) . Utils::$context['querystring_sort_limits'],
			'prev' => sprintf(Utils::$context['querystring_board_limits'], Utils::$context['start'] - Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'],
			'next' => sprintf(Utils::$context['querystring_board_limits'], Utils::$context['start'] + Utils::$context['topics_per_page']) . Utils::$context['querystring_sort_limits'],
			'last' => sprintf(Utils::$context['querystring_board_limits'], $this->num_topics - ($this->num_topics % Utils::$context['topics_per_page'])) . Utils::$context['querystring_sort_limits'],
		];

		if (isset($this->cat_name)) {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '#c' . (int) $_REQUEST['c'][0],
				'name' => $this->cat_name,
			];
		}

		Utils::$context['linktree'][] = [
			'url' => $this->action_url . $url_limits['first'],
			'name' => $this->linktree_name,
		];

		if (Utils::$context['showing_all_topics']) {
			Utils::$context['linktree'][] = [
				'url' => $this->action_url . ';all' . $url_limits['first'],
				'name' => Lang::$txt['unread_topics_all'],
			];
		} else {
			Lang::$txt['unread_topics_visit_none'] = strtr(sprintf(Lang::$txt['unread_topics_visit_none'], Config::$scripturl), ['?action=unread;all' => '?action=unread;all' . $url_limits['first']]);
		}

		// Make sure the starting place makes sense and construct the page index.
		Utils::$context['page_index'] = new PageIndex($this->action_url . (Utils::$context['showing_all_topics'] ? ';all' : '') . Utils::$context['querystring_board_limits'] . Utils::$context['querystring_sort_limits'], Utils::$context['start'], $this->num_topics, Utils::$context['topics_per_page'], true);

		Utils::$context['current_page'] = floor(Utils::$context['start'] / Utils::$context['topics_per_page']);

		Utils::$context['links'] = [
			'first' => $not_first_page ? $this->action_url . (Utils::$context['showing_all_topics'] ? ';all' : '') . $url_limits['first'] : '',
			'prev' => $not_first_page ? $this->action_url . (Utils::$context['showing_all_topics'] ? ';all' : '') . $url_limits['prev'] : '',
			'next' => $not_last_page ? $this->action_url . (Utils::$context['showing_all_topics'] ? ';all' : '') . $url_limits['next'] : '',
			'last' => $not_last_page ? $this->action_url . (Utils::$context['showing_all_topics'] ? ';all' : '') . $url_limits['last'] : '',
			'up' => Config::$scripturl,
		];

		Utils::$context['page_info'] = [
			'current_page' => Utils::$context['current_page'] + 1,
			'num_pages' => floor(($this->num_topics - 1) / Utils::$context['topics_per_page']) + 1,
		];
	}

	/**
	 *
	 */
	protected function setNoTopics()
	{
		// Is this an all topics query?
		if (Utils::$context['showing_all_topics']) {
			// Since there are no unread topics, mark the boards as read!
			Board::markBoardsRead(empty($this->boards) ? Board::$info->id : $this->boards);
		}

		Utils::$context['topics'] = [];
		Utils::$context['no_topic_listing'] = true;

		if (Utils::$context['querystring_board_limits'] == ';start=%1$d') {
			Utils::$context['querystring_board_limits'] = '';
		} else {
			Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], Utils::$context['start']);
		}
	}

	/**
	 * Makes any needed adjustments to $this->selects.
	 */
	protected function finalizeSelects()
	{
		if (!empty(Theme::$current->settings['avatars_on_indexes'])) {
			$this->selects = array_merge($this->selects, [
				'meml.avatar',
				'meml.email_address',
				'mems.avatar AS first_member_avatar',
				'mems.email_address AS first_poster_email',
				'COALESCE(af.id_attach, 0) AS first_poster_id_attach',
				'af.filename AS first_member_filename',
				'af.attachment_type AS first_poster_attach_type',
				'COALESCE(al.id_attach, 0) AS last_poster_id_attach',
				'al.filename AS last_member_filename',
				'al.attachment_type AS last_poster_attach_type',
			]);
		}
	}

	/**
	 * Gets the ID of the earliest message that the current user has not read.
	 */
	protected function getEarliestMsg()
	{
		if (!Utils::$context['showing_all_topics']) {
			return;
		}

		if (!empty(Board::$info->id)) {
			$request = Db::$db->query(
				'',
				'SELECT MIN(id_msg)
				FROM {db_prefix}log_mark_read
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				[
					'current_board' => Board::$info->id,
					'current_member' => User::$me->id,
				],
			);
			list($this->earliest_msg) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT MIN(lmr.id_msg)
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
				WHERE {query_see_board}',
				[
					'current_member' => User::$me->id,
				],
			);
			list($this->earliest_msg) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// This is needed in case of topics marked unread.
		if (empty($this->earliest_msg)) {
			$this->earliest_msg = 0;
		} else {
			// Using caching, when possible, to ignore the below slow query.
			if (isset($_SESSION['cached_log_time']) && $_SESSION['cached_log_time'][0] + 45 > time()) {
				$earliest_msg2 = $_SESSION['cached_log_time'][1];
			} else {
				// This query is pretty slow, but it's needed to ensure nothing crucial is ignored.
				$request = Db::$db->query(
					'',
					'SELECT MIN(id_msg)
					FROM {db_prefix}log_topics
					WHERE id_member = {int:current_member}',
					[
						'current_member' => User::$me->id,
					],
				);
				list($earliest_msg2) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// In theory this could be zero, if the first ever post is unread, so fudge it ;)
				if ($earliest_msg2 == 0) {
					$earliest_msg2 = -1;
				}

				$_SESSION['cached_log_time'] = [time(), $earliest_msg2];
			}

			$this->earliest_msg = min($earliest_msg2, $this->earliest_msg);
		}
	}

	/**
	 * Sets $this->topic_request to the appropriate query.
	 */
	protected function setTopicRequest()
	{
		if (Config::$modSettings['totalMessages'] > 100000 && Utils::$context['showing_all_topics']) {
			$this->makeTempTable();
		}

		if ($this->have_temp_table) {
			$this->getTopicRequestWithTempTable();
		} else {
			$this->getTopicRequestWithoutTempTable();
		}
	}

	/**
	 * For large forums, creates a temporary table to use when showing all unread topics.
	 */
	protected function makeTempTable()
	{
		Db::$db->query(
			'',
			'DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
			[
			],
		);

		// Let's copy things out of the log_topics table, to reduce searching.
		$this->have_temp_table = Db::$db->query(
			'',
			'CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg, lt.unwatched
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}
				AND t.' . $this->query_this_board . (empty($this->earliest_msg) ? '' : '
				AND t.id_last_msg > {int:earliest_msg}') . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : ''),
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'earliest_msg' => !empty($this->earliest_msg) ? $this->earliest_msg : 0,
				'is_approved' => 1,
				'db_error_skip' => true,
			]),
		) !== false;
	}

	/**
	 * For large forums, sets $this->topic_request with the help of a temporary table.
	 */
	protected function getTopicRequestWithTempTable()
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $this->query_this_board . (!empty($this->earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : '') . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1',
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'earliest_msg' => !empty($this->earliest_msg) ? $this->earliest_msg : 0,
				'is_approved' => 1,
			]),
		);
		list($num_topics, $min_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$this->num_topics = $num_topics ?? 0;
		$this->min_message = $min_message ?? 0;

		if ($this->num_topics == 0) {
			$this->setNoTopics();

			return;
		}

		$this->topic_request = Db::$db->query(
			'substring',
			'SELECT ' . implode(', ', $this->selects) . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty(Theme::$current->settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE b.' . $this->query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'min_message' => $this->min_message,
				'is_approved' => 1,
				'sort' => $_REQUEST['sort'] . ($this->ascending ? '' : ' DESC'),
				'offset' => Utils::$context['start'],
				'limit' => Utils::$context['topics_per_page'],
			]),
		);
	}

	/**
	 * Sets $this->topic_request without the help of a temporary table.
	 */
	protected function getTopicRequestWithoutTempTable()
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t' . (!empty($this->have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $this->query_this_board . (Utils::$context['showing_all_topics'] && !empty($this->earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : (!Utils::$context['showing_all_topics'] && empty($_SESSION['first_login']) ? '
				AND t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1',
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'earliest_msg' => !empty($this->earliest_msg) ? $this->earliest_msg : 0,
				'id_msg_last_visit' => $_SESSION['id_msg_last_visit'],
				'is_approved' => 1,
			]),
		);
		list($num_topics, $min_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$this->num_topics = $num_topics ?? 0;
		$this->min_message = $min_message ?? 0;

		if ($this->num_topics == 0) {
			$this->setNoTopics();

			return;
		}

		$this->topic_request = Db::$db->query(
			'substring',
			'SELECT ' . implode(', ', $this->selects) . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty(Theme::$current->settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '' . (!empty($this->have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $this->query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < ml.id_msg' . (Config::$modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'min_message' => $this->min_message,
				'is_approved' => 1,
				'order' => $_REQUEST['sort'] . ($this->ascending ? '' : ' DESC'),
				'offset' => Utils::$context['start'],
				'limit' => Utils::$context['topics_per_page'],
			]),
		);
	}

	/**
	 *
	 */
	protected function getTopics()
	{
		$topic_ids = [];

		while ($row = Db::$db->fetch_assoc($this->topic_request)) {
			if ($row['id_poll'] > 0 && Config::$modSettings['pollMode'] == '0') {
				continue;
			}

			$topic_ids[] = $row['id_topic'];

			// Handle the rows like we do on the message index.
			MessageIndex::buildTopicContext($row);

			// ... except with a few tweaks.

			// Show a link to the board.
			Utils::$context['topics'][$row['id_topic']]['board'] = [
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>',
			];

			// Adjust the "new" link.
			Utils::$context['topics'][$row['id_topic']]['new_href'] = Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new';

			// Adjust the link to the first post.
			Utils::$context['topics'][$row['id_topic']]['first_post']['href'] = Config::$scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen';

			Utils::$context['topics'][$row['id_topic']]['first_post']['link'] = '<a href="' . Utils::$context['topics'][$row['id_topic']]['first_post']['href'] . '" rel="nofollow">' . $row['first_subject'] . '</a>';

			// Adjust the link to the last post.
			Utils::$context['topics'][$row['id_topic']]['last_post']['href'] = Config::$scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'];

			Utils::$context['topics'][$row['id_topic']]['last_post']['link'] = '<a href="' . Utils::$context['topics'][$row['id_topic']]['last_post']['href'] . '" rel="nofollow">' . $row['last_subject'] . '</a>';

			// Add "started by" string to first post.
			Utils::$context['topics'][$row['id_topic']]['first_post']['started_by'] = sprintf(Lang::$txt['topic_started_by'], Utils::$context['topics'][$row['id_topic']]['first_post']['member']['link'], Utils::$context['topics'][$row['id_topic']]['board']['link']);

			// This isn't really necessary, but for the sake of consistency
			// ensure the topic is marked as new.
			Utils::$context['topics'][$row['id_topic']]['new'] = true;
		}
		Db::$db->free_result($this->topic_request);

		if (!empty(Config::$modSettings['enableParticipation']) && !empty($topic_ids)) {
			$result = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT {int:limit}',
				[
					'current_member' => User::$me->id,
					'topic_list' => $topic_ids,
					'limit' => count($topic_ids),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				if (empty(Utils::$context['topics'][$row['id_topic']]['is_posted_in'])) {
					Utils::$context['topics'][$row['id_topic']]['is_posted_in'] = true;
				}
			}
			Db::$db->free_result($result);
		}

		Utils::$context['querystring_board_limits'] = sprintf(Utils::$context['querystring_board_limits'], Utils::$context['start']);

		Utils::$context['topics_to_mark'] = implode('-', $topic_ids);
	}

	/**
	 *
	 */
	protected function buildButtons()
	{
		// Build the recent button array.
		if ($this->is_topics) {
			Utils::$context['recent_buttons'] = [
				'markread' => [
					'text' => !empty(Utils::$context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short',
					'image' => 'markread.png',
					'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"',
					'class' => 'you_sure',
					'url' => Config::$scripturl . '?action=markasread;sa=' . (!empty(Utils::$context['no_board_limits']) ? 'all' : 'board' . Utils::$context['querystring_board_limits']) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				],
			];

			if (Utils::$context['showCheckboxes']) {
				Utils::$context['recent_buttons']['markselectread'] = [
					'text' => 'quick_mod_markread',
					'image' => 'markselectedread.png',
					'url' => 'javascript:document.quickModForm.submit();',
				];
			}

			if (!empty(Utils::$context['topics']) && !Utils::$context['showing_all_topics']) {
				Utils::$context['recent_buttons']['readall'] = ['text' => 'unread_topics_all', 'image' => 'markreadall.png', 'url' => Config::$scripturl . '?action=unread;all' . Utils::$context['querystring_board_limits'], 'active' => true];
			}
		} elseif (!$this->is_topics && isset(Utils::$context['topics_to_mark'])) {
			Utils::$context['recent_buttons'] = [
				'markread' => [
					'text' => 'mark_as_read',
					'image' => 'markread.png',
					'custom' => 'data-confirm="' . Lang::$txt['are_sure_mark_read'] . '"',
					'class' => 'you_sure',
					'url' => Config::$scripturl . '?action=markasread;sa=unreadreplies;topics=' . Utils::$context['topics_to_mark'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				],
			];

			if (Utils::$context['showCheckboxes']) {
				Utils::$context['recent_buttons']['markselectread'] = [
					'text' => 'quick_mod_markread',
					'image' => 'markselectedread.png',
					'url' => 'javascript:document.quickModForm.submit();',
				];
			}
		}

		// Allow mods to add additional buttons here
		IntegrationHook::call('integrate_recent_buttons');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Unread::exportStatic')) {
	Unread::exportStatic();
}

?>