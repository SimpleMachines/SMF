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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Msg;
use SMF\PageIndex;
use SMF\Profile;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class ShowPosts implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'list_getUnwatched' => 'list_getUnwatched',
			'list_getNumUnwatched' => 'list_getNumUnwatched',
			'list_getAttachments' => 'list_getAttachments',
			'list_getNumAttachments' => 'list_getNumAttachments',
			'showPosts' => 'showPosts',
			'showUnwatched' => 'showUnwatched',
			'showAttachments' => 'showAttachments',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'messages';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'messages' => 'messages',
		'topics' => 'topics',
		'unwatchedtopics' => 'unwatched',
		'attach' => 'attachments',
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
		// Some initial context.
		Utils::$context['start'] = (int) ($_REQUEST['start'] ?? 0);
		Utils::$context['current_member'] = Profile::$member->id;

		// Create the tabs for the template.
		Menu::$loaded['profile']->tab_data = [
			'title' => Lang::$txt['showPosts'],
			'description' => Lang::$txt['showPosts_help'],
			'icon_class' => 'main_icons profile_hd',
			'tabs' => [
				'messages' => [
				],
				'topics' => [
				],
				'unwatchedtopics' => [
				],
				'attach' => [
				],
			],
		];

		$this->setPageTitle();

		// Is the load average too high to allow searching just now?
		if (
			!empty(Utils::$context['load_average'])
			&& !empty(Config::$modSettings['loadavg_show_posts'])
			&& Utils::$context['load_average'] >= Config::$modSettings['loadavg_show_posts']
		) {
			ErrorHandler::fatalLang('loadavg_show_posts_disabled', false);
		}

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Show all the posts by this member.
	 */
	public function messages(): void
	{
		Utils::$context['is_topics'] = false;

		// If just deleting a message, do it and then redirect back.
		if (isset($_GET['delete'])) {
			$this->deletePost();
		}

		$this->loadPosts();
	}

	/**
	 * Show all the topics started by this member.
	 */
	public function topics(): void
	{
		Utils::$context['is_topics'] = true;

		$this->loadPosts(true);
	}

	/**
	 * Show all the unwatched topics for this member.
	 */
	public function unwatched(): void
	{
		// Only the owner can see the list (if the function is enabled of course)
		if (!User::$me->is_owner) {
			Utils::redirectexit('action=profile;u=' . Profile::$member->id . ';area=showposts');
		}

		// And here they are: the topics you don't like
		$list_options = [
			'id' => 'unwatched_topics',
			'width' => '100%',
			'items_per_page' => (empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['topics_per_page'])) ? Theme::$current->options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'],
			'no_items_label' => Lang::$txt['unwatched_topics_none'],
			'base_href' => Config::$scripturl . '?action=profile;area=showposts;sa=unwatchedtopics;u=' . Profile::$member->id,
			'default_sort_col' => 'started_on',
			'get_items' => [
				'function' => __CLASS__ . '::list_getUnwatched',
				'params' => [],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumUnwatched',
				'params' => [],
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => Lang::$txt['subject'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?topic=%1$d.0">%2$s</a>',
							'params' => [
								'id_topic' => false,
								'subject' => false,
							],
						],
					],
					'sort' => [
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					],
				],
				'started_by' => [
					'header' => [
						'value' => Lang::$txt['started_by'],
						'style' => 'width: 15%;',
					],
					'data' => [
						'db' => 'started_by',
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'started_on' => [
					'header' => [
						'value' => Lang::$txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					],
					'data' => [
						'db' => 'started_on',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					],
				],
				'last_post_by' => [
					'header' => [
						'value' => Lang::$txt['last_post'],
						'style' => 'width: 15%;',
					],
					'data' => [
						'db' => 'last_post_by',
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'last_post_on' => [
					'header' => [
						'value' => Lang::$txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					],
					'data' => [
						'db' => 'last_post_on',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					],
				],
			],
		];

		// Create the request list.
		new ItemList($list_options);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'unwatched_topics';
	}

	/**
	 * Show all the attachments belonging to this member.
	 */
	public function attachments(): void
	{
		// OBEY permissions!
		$boards_allowed = User::$me->boardsAllowedTo('view_attachments');

		// Make sure we can't actually see anything...
		if (empty($boards_allowed)) {
			$boards_allowed = [-1];
		}

		// This is all the information required to list attachments.
		$list_options = [
			'id' => 'attachments',
			'width' => '100%',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['show_attachments_none'],
			'base_href' => Config::$scripturl . '?action=profile;area=showposts;sa=attach;u=' . Profile::$member->id,
			'default_sort_col' => 'filename',
			'get_items' => [
				'function' => __CLASS__ . '::list_getAttachments',
				'params' => [
					$boards_allowed,
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumAttachments',
				'params' => [
					$boards_allowed,
				],
			],
			'data_check' => [
				'class' => function ($data) {
					return $data['approved'] ? '' : 'approvebg';
				},
			],
			'columns' => [
				'filename' => [
					'header' => [
						'value' => Lang::$txt['show_attach_filename'],
						'class' => 'lefttext',
						'style' => 'width: 25%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=dlattach;topic=%1$d.0;attach=%2$d">%3$s</a>%4$s',
							'params' => [
								'topic' => true,
								'id' => true,
								'filename' => false,
								'awaiting_approval' => false,
							],
						],
					],
					'sort' => [
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					],
				],
				'downloads' => [
					'header' => [
						'value' => Lang::$txt['show_attach_downloads'],
						'style' => 'width: 12%;',
					],
					'data' => [
						'db' => 'downloads',
						'comma_format' => true,
					],
					'sort' => [
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					],
				],
				'subject' => [
					'header' => [
						'value' => Lang::$txt['message'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?msg=%1$d">%2$s</a>',
							'params' => [
								'msg' => true,
								'subject' => false,
							],
						],
					],
					'sort' => [
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					],
				],
				'posted' => [
					'header' => [
						'value' => Lang::$txt['show_attach_posted'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'posted',
						'timeformat' => true,
					],
					'sort' => [
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					],
				],
			],
		];

		// Create the request list.
		new ItemList($list_options);
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
	 * Gets information about unwatched (disregarded) topics. Callback for the list in show_unwatched
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of information about the unwatched topics
	 */
	public static function list_getUnwatched(int $start, int $items_per_page, string $sort): array
	{
		// Get the list of topics we can see
		$topics = [];

		$request = Db::$db->query(
			'',
			'SELECT lt.id_topic
			FROM {db_prefix}log_topics as lt
				LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}messages as m ON (t.id_first_msg = m.id_msg)' . (in_array($sort, ['mem.real_name', 'mem.real_name DESC', 'mem.poster_time', 'mem.poster_time DESC']) ? '
				LEFT JOIN {db_prefix}members as mem ON (m.id_member = mem.id_member)' : '') . '
			WHERE lt.id_member = {int:current_member}
				AND unwatched = 1
				AND {query_see_message_board}
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			[
				'current_member' => Profile::$member->id,
				'sort' => $sort,
				'offset' => $start,
				'limit' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$topics[] = $row['id_topic'];
		}
		Db::$db->free_result($request);

		// Any topics found?
		$topics_info = [];

		if (!empty($topics)) {
			$request = Db::$db->query(
				'',
				'SELECT mf.subject, mf.poster_time as started_on, COALESCE(memf.real_name, mf.poster_name) as started_by, ml.poster_time as last_post_on, COALESCE(meml.real_name, ml.poster_name) as last_post_by, t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
					LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
				WHERE t.id_topic IN ({array_int:topics})',
				[
					'topics' => $topics,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$topics_info[] = $row;
			}
			Db::$db->free_result($request);
		}

		return $topics_info;
	}

	/**
	 * Count the number of topics in the unwatched list
	 *
	 * @return int The number of unwatched topics
	 */
	public static function list_getNumUnwatched(): int
	{
		// Get the total number of attachments they have posted.
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_topics as lt
			LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}
				AND lt.unwatched = 1
				AND {query_see_topic_board}',
			[
				'current_member' => Profile::$member->id,
			],
		);
		list($unwatched_count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $unwatched_count;
	}

	/**
	 * Get a list of attachments for a member. Callback for the list in showAttachments()
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @param array $boards_allowed An array containing the IDs of the boards they can see
	 * @return array An array of information about the attachments
	 */
	public static function list_getAttachments(int $start, int $items_per_page, string $sort, array $boards_allowed): array
	{
		// Retrieve some attachments.
		$attachments = [];

		$request = Db::$db->query(
			'',
			'SELECT a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, m.id_msg, m.id_topic,
				m.id_board, m.poster_time, m.subject, b.name
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
			WHERE a.attachment_type = {int:attachment_type}
				AND a.id_msg != {int:no_message}
				AND m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
				AND b.id_board = {int:board}' : '') . (!in_array(0, $boards_allowed) ? '
				AND b.id_board IN ({array_int:boards_list})' : '') . (!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') || User::$me->is_owner ? '' : '
				AND a.approved = {int:is_approved}') . '
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			[
				'boards_list' => $boards_allowed,
				'attachment_type' => 0,
				'no_message' => 0,
				'current_member' => Profile::$member->id,
				'is_approved' => 1,
				'board' => Board::$info->id ?? 0,
				'sort' => $sort,
				'offset' => $start,
				'limit' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$attachments[] = [
				'id' => $row['id_attach'],
				'filename' => $row['filename'],
				'downloads' => $row['downloads'],
				'subject' => Lang::censorText($row['subject']),
				'posted' => $row['poster_time'],
				'msg' => $row['id_msg'],
				'topic' => $row['id_topic'],
				'board' => $row['id_board'],
				'board_name' => $row['name'],
				'approved' => $row['approved'],
				'awaiting_approval' => (empty($row['approved']) ? ' <em>(' . Lang::$txt['awaiting_approval'] . ')</em>' : ''),
			];
		}
		Db::$db->free_result($request);

		return $attachments;
	}

	/**
	 * Gets the total number of attachments for a member
	 *
	 * @param array $boards_allowed An array of the IDs of the boards they can see
	 * @return int The number of attachments
	 */
	public static function list_getNumAttachments(array $boards_allowed): int
	{
		// Get the total number of attachments they have posted.
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner || User::$me->allowedTo('approve_posts') ? '' : '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
			WHERE a.attachment_type = {int:attachment_type}
				AND a.id_msg != {int:no_message}
				AND m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
				AND b.id_board = {int:board}' : '') . (!in_array(0, $boards_allowed) ? '
				AND b.id_board IN ({array_int:boards_list})' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner || User::$me->allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}'),
			[
				'boards_list' => $boards_allowed,
				'attachment_type' => 0,
				'no_message' => 0,
				'current_member' => Profile::$member->id,
				'is_approved' => 1,
				'board' => Board::$info->id ?? 0,
			],
		);
		list($attach_count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $attach_count;
	}

	/**
	 * Backward compatibility wrapper.
	 */
	public static function showPosts(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the unwatchedtopics sub-action.
	 */
	public static function showUnwatched(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->subaction = 'unwatchedtopics';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the attach sub-action.
	 */
	public static function showAttachments(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->subaction = 'attach';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function setPageTitle(): void
	{
		// Shortcut used to determine which Lang::$txt['show*'] string to use for the title, based on the SA
		$title = [
			'messages' => 'showPosts',
			'topics' => 'showTopics',
			'unwatchedtopics' => User::$me->is_owner ? 'showUnwatched' : 'showPosts',
			'attach' => 'showAttachments',
		];

		// Set the page title
		Utils::$context['page_title'] = Lang::$txt[$title[$_REQUEST['sa'] ?? 'messages'] ?? $title['messages']] . ' - ' . Profile::$member->name;
	}

	/**
	 * Deletes a message and then redirects back to the list.
	 */
	protected function deletePost(): void
	{
		// Double check, just in case...
		if (!isset($_GET['delete'])) {
			return;
		}

		User::$me->checkSession('get');

		// We need msg info for logging.
		$request = Db::$db->query(
			'',
			'SELECT subject, id_member, id_topic, id_board
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			[
				'id_msg' => (int) $_GET['delete'],
			],
		);
		$info = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Trying to remove a message that doesn't exist.
		if (empty($info)) {
			Utils::redirectexit('action=profile;u=' . Profile::$member->id . ';area=showposts;start=' . $_GET['start']);
		}

		// We can be lazy, since Msg::remove() will check the permissions for us.
		Msg::remove((int) $_GET['delete']);

		// Add it to the mod log.
		if (User::$me->allowedTo('delete_any') && (!User::$me->allowedTo('delete_own') || $info[1] != User::$me->id)) {
			Logging::logAction('delete', [
				'topic' => $info[2],
				'subject' => $info[0],
				'member' => $info[1],
				'board' => $info[3],
			]);
		}

		// Back to... where we are now ;).
		Utils::redirectexit('action=profile;u=' . Profile::$member->id . ';area=showposts;start=' . $_GET['start']);
	}

	/**
	 *
	 */
	protected function loadPosts($is_topics = false): void
	{
		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount'])) {
			$_REQUEST['viewscount'] = '10';
		}

		if ($is_topics) {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}topics AS t' . '
				WHERE {query_see_topic_board}
					AND t.id_member_started = {int:current_member}' . (!empty(Board::$info->id) ? '
					AND t.id_board = {int:board}' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
					AND t.approved = {int:is_approved}'),
				[
					'current_member' => Profile::$member->id,
					'is_approved' => 1,
					'board' => Board::$info->id ?? 0,
				],
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}messages AS m' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
				WHERE {query_see_message_board} AND m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
					AND m.id_board = {int:board}' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}'),
				[
					'current_member' => Profile::$member->id,
					'is_approved' => 1,
					'board' => Board::$info->id ?? 0,
				],
			);
		}
		list($msg_count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$request = Db::$db->query(
			'',
			'SELECT MIN(id_msg), MAX(id_msg)
			FROM {db_prefix}messages AS m' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
			WHERE m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
				AND m.id_board = {int:board}' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}'),
			[
				'current_member' => Profile::$member->id,
				'is_approved' => 1,
				'board' => Board::$info->id ?? 0,
			],
		);
		list($min_msg_member, $max_msg_member) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$range_limit = '';

		if ($is_topics) {
			$max_per_page = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['topics_per_page']) ? Theme::$current->options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'];
		} else {
			$max_per_page = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];
		}

		$max_index = $max_per_page;

		// Make sure the starting place makes sense and construct our friend the page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=profile;u=' . Profile::$member->id . ';area=showposts' . ($is_topics ? ';sa=topics' : '') . (!empty(Board::$info->id) ? ';board=' . Board::$info->id : ''), Utils::$context['start'], $msg_count, $max_index);

		Utils::$context['current_page'] = Utils::$context['start'] / $max_index;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = Utils::$context['start'];
		$reverse = $_REQUEST['start'] > $msg_count / 2;

		if ($reverse) {
			$max_index = $msg_count < Utils::$context['start'] + $max_per_page + 1 && $msg_count > Utils::$context['start'] ? $msg_count - Utils::$context['start'] : $max_per_page;
			$start = $msg_count < Utils::$context['start'] + $max_per_page + 1 || $msg_count < Utils::$context['start'] + $max_per_page ? 0 : $msg_count - Utils::$context['start'] - $max_per_page;
		}

		// Guess the range of messages to be shown.
		if ($msg_count > 1000) {
			$margin = floor(($max_msg_member - $min_msg_member) * (($start + $max_per_page) / $msg_count) + .1 * ($max_msg_member - $min_msg_member));

			// Make a bigger margin for topics only.
			if ($is_topics) {
				$margin *= 5;
				$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
			} else {
				$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
			}
		}

		// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
		$counter = $reverse ? Utils::$context['start'] + $max_index + 1 : Utils::$context['start'];
		Utils::$context['posts'] = [];
		$board_ids = ['own' => [], 'any' => []];

		$looped = false;

		while (true) {
			if ($is_topics) {
				$request = Db::$db->query(
					'',
					'SELECT
						b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
						t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
						LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
						INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					WHERE t.id_member_started = {int:current_member}' . (!empty(Board::$info->id) ? '
						AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
						AND ' . $range_limit) . '
						AND {query_see_board}' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
						AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
					ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
					LIMIT {int:start}, {int:max}',
					[
						'current_member' => Profile::$member->id,
						'is_approved' => 1,
						'board' => Board::$info->id ?? 0,
						'start' => $start,
						'max' => $max_index,
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SELECT
						b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
						t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
						m.subject, m.poster_time, m.approved
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
						LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					WHERE m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
						AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
						AND ' . $range_limit) . '
						AND {query_see_board}' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
						AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
					ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
					LIMIT {int:start}, {int:max}',
					[
						'current_member' => Profile::$member->id,
						'is_approved' => 1,
						'board' => Board::$info->id ?? 0,
						'start' => $start,
						'max' => $max_index,
					],
				);
			}

			// Make sure we quit this loop.
			if (Db::$db->num_rows($request) === $max_index || $looped || $range_limit == '') {
				break;
			}

			$looped = true;
			$range_limit = '';
		}

		// Start counting at the number of the first message displayed.
		while ($row = Db::$db->fetch_assoc($request)) {
			// Censor....
			Lang::censorText($row['body']);
			Lang::censorText($row['subject']);

			// Do the code.
			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			// And the array...
			Utils::$context['posts'][$counter += $reverse ? -1 : 1] = [
				'body' => $row['body'],
				'counter' => $counter,
				'category' => [
					'name' => $row['cname'],
					'id' => $row['id_cat'],
				],
				'board' => [
					'name' => $row['bname'],
					'id' => $row['id_board'],
				],
				'topic' => $row['id_topic'],
				'subject' => $row['subject'],
				'start' => 'msg' . $row['id_msg'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'id' => $row['id_msg'],
				'can_reply' => false,
				'can_mark_notify' => !User::$me->is_guest,
				'can_delete' => false,
				'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty(Config::$modSettings['edit_disable_time']) || $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 >= time()),
				'approved' => $row['approved'],
				'css_class' => $row['approved'] ? 'windowbg' : 'approvebg',
			];

			if (User::$me->id == $row['id_member_started']) {
				$board_ids['own'][$row['id_board']][] = $counter;
			}

			$board_ids['any'][$row['id_board']][] = $counter;
		}
		Db::$db->free_result($request);

		// All posts were retrieved in reverse order, get them right again.
		if ($reverse) {
			Utils::$context['posts'] = array_reverse(Utils::$context['posts'], true);
		}

		// These are all the permissions that are different from board to board..
		if ($is_topics) {
			$permissions = [
				'own' => [
					'post_reply_own' => 'can_reply',
				],
				'any' => [
					'post_reply_any' => 'can_reply',
				],
			];
		} else {
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
		}

		// Create an array for the permissions.
		$boards_can = User::$me->boardsAllowedTo(
			array_keys(
				iterator_to_array(
					new \RecursiveIteratorIterator(
						new \RecursiveArrayIterator($permissions),
					),
				),
			),
			true,
			false,
		);

		// For every permission in the own/any lists...
		foreach ($permissions as $type => $list) {
			foreach ($list as $permission => $allowed) {
				// Get the boards they can do this on...
				$boards = $boards_can[$permission];

				// Hmm, they can do it on all boards, can they?
				if (!empty($boards) && $boards[0] == 0) {
					$boards = array_keys($board_ids[$type]);
				}

				// Now go through each board they can do the permission on.
				foreach ($boards as $board_id) {
					// There aren't any posts displayed from this board.
					if (!isset($board_ids[$type][$board_id])) {
						continue;
					}

					// Set the permission to true ;).
					foreach ($board_ids[$type][$board_id] as $counter) {
						Utils::$context['posts'][$counter][$allowed] = true;
					}
				}
			}
		}

		// Clean up after posts that cannot be deleted and quoted.
		$quote_enabled = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));

		foreach (Utils::$context['posts'] as $counter => $dummy) {
			Utils::$context['posts'][$counter]['can_delete'] &= Utils::$context['posts'][$counter]['delete_possible'];

			Utils::$context['posts'][$counter]['can_quote'] = Utils::$context['posts'][$counter]['can_reply'] && $quote_enabled;
		}

		// Allow last minute changes.
		IntegrationHook::call('integrate_profile_showPosts');

		foreach (Utils::$context['posts'] as $key => $post) {
			Utils::$context['posts'][$key]['quickbuttons'] = [
				'reply' => [
					'label' => Lang::$txt['reply'],
					'href' => Config::$scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'],
					'icon' => 'reply_button',
					'show' => $post['can_reply'],
				],
				'quote' => [
					'label' => Lang::$txt['quote_action'],
					'href' => Config::$scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'] . ';quote=' . $post['id'],
					'icon' => 'quote',
					'show' => $post['can_quote'],
				],
				'remove' => [
					'label' => Lang::$txt['remove'],
					'href' => Config::$scripturl . '?action=deletemsg;msg=' . $post['id'] . ';topic=' . $post['topic'] . ';profile;u=' . Utils::$context['member']['id'] . ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
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
if (is_callable(__NAMESPACE__ . '\\ShowPosts::exportStatic')) {
	ShowPosts::exportStatic();
}

?>