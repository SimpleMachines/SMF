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
use SMF\Attachment;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Msg;
use SMF\PageIndex;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Handles things related to post moderation.
 */
class Posts implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'PostModerationMain',
			'approveAllData' => 'approveAllData',
			'list_getUnapprovedAttachments' => 'list_getUnapprovedAttachments',
			'list_getNumUnapprovedAttachments' => 'list_getNumUnapprovedAttachments',
			'unapprovedPosts' => 'UnapprovedPosts',
			'unapprovedAttachments' => 'UnapprovedAttachments',
			'approveMessage' => 'ApproveMessage',
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
	public string $subaction = 'replies';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'replies' => 'posts',
		'topics' => 'posts',
		'attachments' => 'attachments',
		'approve' => 'approve',
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
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * View all unapproved posts.
	 */
	public function posts(): void
	{
		Utils::$context['current_view'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? 'topics' : 'replies';
		Utils::$context['page_title'] = Lang::$txt['mc_unapproved_posts'];

		// Work out what boards we can work in!
		$approve_boards = User::$me->boardsAllowedTo('approve_posts');

		// If we filtered by board remove ones outside of this board.
		// @todo Put a message saying we're filtered?
		if (isset($_REQUEST['brd'])) {
			$filter_board = [(int) $_REQUEST['brd']];

			$approve_boards = $approve_boards == [0] ? $filter_board : array_intersect($approve_boards, $filter_board);
		}

		if ($approve_boards == [0]) {
			$approve_query = '';
		} elseif (!empty($approve_boards)) {
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		}
		// Nada, zip, etc...
		else {
			$approve_query = ' AND 1=0';
		}

		// We also need to know where we can delete topics and/or replies to.
		$boards_can = User::$me->boardsAllowedTo(['remove_any', 'remove_own', 'delete_own', 'delete_any', 'delete_own_replies'], true, false);

		if (Utils::$context['current_view'] == 'topics') {
			$delete_own_boards = $boards_can['remove_own'];
			$delete_any_boards = $boards_can['remove_any'];
			$delete_own_replies = [];
		} else {
			$delete_own_boards = $boards_can['delete_own'];
			$delete_any_boards = $boards_can['delete_any'];
			$delete_own_replies = $boards_can['delete_own_replies'];
		}

		$toAction = [];

		// Check if we have something to do?
		if (isset($_GET['approve'])) {
			$toAction[] = (int) $_GET['approve'];
		}
		// Just a deletion?
		elseif (isset($_GET['delete'])) {
			$toAction[] = (int) $_GET['delete'];
		}
		// Lots of approvals?
		elseif (isset($_POST['item'])) {
			foreach ($_POST['item'] as $item) {
				$toAction[] = (int) $item;
			}
		}

		// What are we actually doing.
		if (isset($_GET['approve']) || (isset($_POST['do']) && $_POST['do'] == 'approve')) {
			$curAction = 'approve';
		} elseif (isset($_GET['delete']) || (isset($_POST['do']) && $_POST['do'] == 'delete')) {
			$curAction = 'delete';
		}

		// Right, so we have something to do?
		if (!empty($toAction) && isset($curAction)) {
			User::$me->checkSession('request');

			// Handy shortcut.
			$any_array = $curAction == 'approve' ? $approve_boards : $delete_any_boards;

			// Now for each message work out whether it's actually a topic, and what board it's on.
			$request = Db::$db->query(
				'',
				'SELECT m.id_msg, m.id_member, m.id_board, m.subject, t.id_topic, t.id_first_msg, t.id_member_started
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				WHERE m.id_msg IN ({array_int:message_list})
					AND m.approved = {int:not_approved}
					AND {query_see_message_board}',
				[
					'message_list' => $toAction,
					'not_approved' => 0,
				],
			);
			$toAction = [];
			$details = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				// If it's not within what our view is ignore it...
				if (
					(
						$row['id_msg'] == $row['id_first_msg']
						&& Utils::$context['current_view'] != 'topics'
					)
					|| (
						$row['id_msg'] != $row['id_first_msg']
						&& Utils::$context['current_view'] != 'replies'
					)
				) {
					continue;
				}

				$can_add = false;

				// If we're approving this is simple.
				if ($curAction == 'approve' && ($any_array == [0] || in_array($row['id_board'], $any_array))) {
					$can_add = true;
				}
				// Delete requires more permission checks...
				elseif ($curAction == 'delete') {
					// Own post is easy!
					if (
						$row['id_member'] == User::$me->id
						&& (
							$delete_own_boards == [0]
							|| in_array($row['id_board'], $delete_own_boards)
						)
					) {
						$can_add = true;
					}
					// Is it a reply to their own topic?
					elseif (
						$row['id_member'] == $row['id_member_started']
						&& $row['id_msg'] != $row['id_first_msg']
						&& (
							$delete_own_replies == [0]
							|| in_array($row['id_board'], $delete_own_replies)
						)
					) {
						$can_add = true;
					}
					// Someone elses?
					elseif (
						$row['id_member'] != User::$me->id
						&& (
							$delete_any_boards == [0]
							|| in_array($row['id_board'], $delete_any_boards)
						)
					) {
						$can_add = true;
					}
				}

				if ($can_add) {
					$anItem = Utils::$context['current_view'] == 'topics' ? $row['id_topic'] : $row['id_msg'];

					$toAction[] = $anItem;

					// All clear. What have we got now, what, what?
					$details[$anItem] = [];
					$details[$anItem]['subject'] = $row['subject'];
					$details[$anItem]['topic'] = $row['id_topic'];
					$details[$anItem]['member'] = (Utils::$context['current_view'] == 'topics') ? $row['id_member_started'] : $row['id_member'];
					$details[$anItem]['board'] = $row['id_board'];
				}
			}
			Db::$db->free_result($request);

			// If we have anything left we can actually do the approving (etc).
			if (!empty($toAction)) {
				if ($curAction == 'approve') {
					approveMessages($toAction, $details, Utils::$context['current_view']);
				} else {
					removeMessages($toAction, $details, Utils::$context['current_view']);
				}
			}
		}

		// How many unapproved posts are there?
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_first_msg != m.id_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE m.approved = {int:not_approved}
				AND {query_see_board}
				' . $approve_query,
			[
				'not_approved' => 0,
			],
		);
		list(Utils::$context['total_unapproved_posts']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// What about topics?  Normally we'd use the table alias t for topics but lets use m so we don't have to redo our approve query.
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}topics AS m
			WHERE m.approved = {int:not_approved}
				AND {query_see_message_board}
				' . $approve_query,
			[
				'not_approved' => 0,
			],
		);
		list(Utils::$context['total_unapproved_topics']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Limit to how many? (obey the user setting)
		$limit = !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

		// Must have a start value.
		Utils::$context['start'] = $_GET['start'] ?? 0;

		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=moderate;area=postmod;sa=' . Utils::$context['current_view'] . (isset($_REQUEST['brd']) ? ';brd=' . (int) $_REQUEST['brd'] : ''), Utils::$context['start'], Utils::$context['current_view'] == 'topics' ? Utils::$context['total_unapproved_topics'] : Utils::$context['total_unapproved_posts'], $limit);

		// We have enough to make some pretty tabs!
		$menu = Menu::$loaded['moderate'];
		$menu->tab_data = [
			'title' => Lang::$txt['mc_unapproved_posts'],
			'help' => 'postmod',
			'description' => Lang::$txt['mc_unapproved_posts_desc'],
		];

		// Update the tabs with the correct number of posts.
		$menu->sections['posts']['areas']['postmod']['subsections']['posts']['label'] .= ' (' . Utils::$context['total_unapproved_posts'] . ')';
		$menu->sections['posts']['areas']['postmod']['subsections']['topics']['label'] .= ' (' . Utils::$context['total_unapproved_topics'] . ')';

		// If we are filtering some boards out then make sure to send that along with the links.
		if (isset($_REQUEST['brd'])) {
			$menu->sections['posts']['areas']['postmod']['subsections']['posts']['add_params'] = ';brd=' . (int) $_REQUEST['brd'];
			$menu->sections['posts']['areas']['postmod']['subsections']['topics']['add_params'] = ';brd=' . (int) $_REQUEST['brd'];
		}

		// Get all unapproved posts.
		Utils::$context['unapproved_items'] = [];

		$request = Db::$db->query(
			'',
			'SELECT m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.smileys_enabled,
				t.id_member_started, t.id_first_msg, b.name AS board_name, c.id_cat, c.name AS cat_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE m.approved = {int:not_approved}
				AND t.id_first_msg ' . (Utils::$context['current_view'] == 'topics' ? '=' : '!=') . ' m.id_msg
				AND {query_see_board}
				' . $approve_query . '
			LIMIT {int:start}, {int:limit}',
			[
				'not_approved' => 0,
				'start' => Utils::$context['start'],
				'limit' => $limit,
			],
		);

		for ($i = 1; $row = Db::$db->fetch_assoc($request); $i++) {
			// Can delete is complicated, let's solve it first... is it their own post?
			if (
				$row['id_member'] == User::$me->id
				&& (
					$delete_own_boards == [0]
					|| in_array($row['id_board'], $delete_own_boards)
				)
			) {
				$can_delete = true;
			}
			// Is it a reply to their own topic?
			elseif (
				$row['id_member'] == $row['id_member_started']
				&& $row['id_msg'] != $row['id_first_msg']
				&& (
					$delete_own_replies == [0]
					|| in_array($row['id_board'], $delete_own_replies)
				)
			) {
				$can_delete = true;
			}
			// Someone elses?
			elseif (
				$row['id_member'] != User::$me->id
				&& (
					$delete_any_boards == [0]
					|| in_array($row['id_board'], $delete_any_boards)
				)
			) {
				$can_delete = true;
			} else {
				$can_delete = false;
			}

			Utils::$context['unapproved_items'][] = [
				'id' => $row['id_msg'],
				'counter' => Utils::$context['start'] + $i,
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'subject' => $row['subject'],
				'body' => BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']),
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'poster' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'link' => $row['id_member'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				],
				'topic' => [
					'id' => $row['id_topic'],
				],
				'board' => [
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>',
				],
				'category' => [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'link' => '<a href="' . Config::$scripturl . '#c' . $row['id_cat'] . '">' . $row['cat_name'] . '</a>',
				],
				'can_delete' => $can_delete,
			];
		}
		Db::$db->free_result($request);

		Utils::$context['sub_template'] = 'unapproved_posts';
	}

	/**
	 * View all unapproved attachments.
	 */
	public function attachments(): void
	{
		Utils::$context['page_title'] = Lang::$txt['mc_unapproved_attachments'];

		// Once again, permissions are king!
		$approve_boards = User::$me->boardsAllowedTo('approve_posts');

		if ($approve_boards == [0]) {
			$approve_query = '';
		} elseif (!empty($approve_boards)) {
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		} else {
			$approve_query = ' AND 1=0';
		}

		// Get together the array of things to act on, if any.
		$attachments = [];

		if (isset($_GET['approve'])) {
			$attachments[] = (int) $_GET['approve'];
		} elseif (isset($_GET['delete'])) {
			$attachments[] = (int) $_GET['delete'];
		} elseif (isset($_POST['item'])) {
			foreach ($_POST['item'] as $item) {
				$attachments[] = (int) $item;
			}
		}

		// Are we approving or deleting?
		if (isset($_GET['approve']) || (isset($_POST['do']) && $_POST['do'] == 'approve')) {
			$curAction = 'approve';
		} elseif (isset($_GET['delete']) || (isset($_POST['do']) && $_POST['do'] == 'delete')) {
			$curAction = 'delete';
		}

		// Something to do, let's do it!
		if (!empty($attachments) && isset($curAction)) {
			User::$me->checkSession('request');

			// Confirm the attachments are eligible for changing!
			$request = Db::$db->query(
				'',
				'SELECT a.id_attach
				FROM {db_prefix}attachments AS a
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.id_attach IN ({array_int:attachments})
					AND a.approved = {int:not_approved}
					AND a.attachment_type = {int:attachment_type}
					AND {query_see_message_board}
					' . $approve_query,
				[
					'attachments' => $attachments,
					'not_approved' => 0,
					'attachment_type' => 0,
				],
			);
			$attachments = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$attachments[] = $row['id_attach'];
			}
			Db::$db->free_result($request);

			// Assuming it wasn't all like, proper illegal, we can do the approving.
			if (!empty($attachments)) {
				if ($curAction == 'approve') {
					Attachment::approve($attachments);
				} else {
					Attachment::remove(['id_attach' => $attachments, 'do_logging' => true]);
				}
			}
		}

		$listOptions = [
			'id' => 'mc_unapproved_attach',
			'width' => '100%',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['mc_unapproved_attachments_none_found'],
			'base_href' => Config::$scripturl . '?action=moderate;area=attachmod;sa=attachments',
			'default_sort_col' => 'attach_name',
			'get_items' => [
				'function' => __CLASS__ . '::list_getUnapprovedAttachments',
				'params' => [
					$approve_query,
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumUnapprovedAttachments',
				'params' => [
					$approve_query,
				],
			],
			'columns' => [
				'attach_name' => [
					'header' => [
						'value' => Lang::$txt['mc_unapproved_attach_name'],
					],
					'data' => [
						'db' => 'filename',
					],
					'sort' => [
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					],
				],
				'attach_size' => [
					'header' => [
						'value' => Lang::$txt['mc_unapproved_attach_size'],
					],
					'data' => [
						'db' => 'size',
					],
					'sort' => [
						'default' => 'a.size',
						'reverse' => 'a.size DESC',
					],
				],
				'attach_poster' => [
					'header' => [
						'value' => Lang::$txt['mc_unapproved_attach_poster'],
					],
					'data' => [
						'function' => function ($data) {
							return $data['poster']['link'];
						},
					],
					'sort' => [
						'default' => 'm.id_member',
						'reverse' => 'm.id_member DESC',
					],
				],
				'date' => [
					'header' => [
						'value' => Lang::$txt['date'],
						'style' => 'width: 18%;',
					],
					'data' => [
						'db' => 'time',
						'class' => 'smalltext',
						'style' => 'white-space:nowrap;',
					],
					'sort' => [
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					],
				],
				'message' => [
					'header' => [
						'value' => Lang::$txt['post'],
					],
					'data' => [
						'function' => function ($data) {
							return '<a href="' . $data['message']['href'] . '">' . Utils::shorten($data['message']['subject'], 20) . '</a>';
						},
						'class' => 'smalltext',
						'style' => 'width:15em;',
					],
					'sort' => [
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					],
				],
				'action' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" checked>',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="item[]" value="%1$d" checked>',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=moderate;area=attachmod;sa=attachments',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
				],
				'token' => 'mod-ap',
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '
						<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm(\'' . Lang::$txt['mc_unapproved_sure'] . '\')) submit();">
							<option value="0">' . Lang::$txt['with_selected'] . ':</option>
							<option value="0" disabled>-------------------</option>
							<option value="approve">&nbsp;--&nbsp;' . Lang::$txt['approve'] . '</option>
							<option value="delete">&nbsp;--&nbsp;' . Lang::$txt['delete'] . '</option>
						</select>
						<noscript><input type="submit" name="ml_go" value="' . Lang::$txt['go'] . '" class="button"></noscript>',
					'class' => 'floatright',
				],
			],
		];

		// Create the request list.
		SecurityToken::create('mod-ap');
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'mc_unapproved_attach';

		Menu::$loaded['moderate']->tab_data = [
			'title' => Lang::$txt['mc_unapproved_attachments'],
			'help' => '',
			'description' => Lang::$txt['mc_unapproved_attachments_desc'],
		];
	}

	/**
	 * Approve a post, just the one.
	 */
	public function approve(): void
	{
		User::$me->checkSession('get');

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		User::$me->isAllowedTo('approve_posts');

		$request = Db::$db->query(
			'',
			'SELECT t.id_member_started, t.id_first_msg, m.id_member, m.subject, m.approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
				'id_msg' => $_REQUEST['msg'],
			],
		);
		list($starter, $first_msg, $poster, $subject, $approved) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// If it's the first in a topic then the whole topic gets approved!
		if ($first_msg == $_REQUEST['msg']) {
			Topic::approve(Topic::$topic_id, !$approved);

			if ($starter != User::$me->id) {
				Logging::logAction(($approved ? 'un' : '') . 'approve_topic', ['topic' => Topic::$topic_id, 'subject' => $subject, 'member' => $starter, 'board' => Board::$info->id]);
			}
		} else {
			Msg::approve($_REQUEST['msg'], !$approved);

			if ($poster != User::$me->id) {
				Logging::logAction(($approved ? 'un' : '') . 'approve', ['topic' => Topic::$topic_id, 'subject' => $subject, 'member' => $poster, 'board' => Board::$info->id]);
			}
		}

		Utils::redirectexit('topic=' . Topic::$topic_id . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
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
	 * This is a helper function - basically approve everything!
	 */
	public static function approveAllData(): void
	{
		// Start with messages and topics.
		$msgs = [];

		$request = Db::$db->query(
			'',
			'SELECT id_msg
			FROM {db_prefix}messages
			WHERE approved = {int:not_approved}',
			[
				'not_approved' => 0,
			],
		);

		while ($row = Db::$db->fetch_row($request)) {
			$msgs[] = $row[0];
		}
		Db::$db->free_result($request);

		if (!empty($msgs)) {
			Msg::approve($msgs);
		}

		// Now do attachments
		$attachments = [];

		$request = Db::$db->query(
			'',
			'SELECT id_attach
			FROM {db_prefix}attachments
			WHERE approved = {int:not_approved}',
			[
				'not_approved' => 0,
			],
		);

		while ($row = Db::$db->fetch_row($request)) {
			$attachments[] = $row[0];
		}
		Db::$db->free_result($request);

		if (!empty($attachments)) {
			Attachment::approve($attachments);
		}
	}

	/**
	 * Callback function for UnapprovedAttachments
	 * retrieve all the attachments waiting for approval the approver can approve
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $approve_query Additional restrictions based on the boards the approver can see
	 * @return array An array of information about the unapproved attachments
	 */
	public static function list_getUnapprovedAttachments($start, $items_per_page, $sort, $approve_query): array
	{
		// Get all unapproved attachments.
		$unapproved_items = [];

		$request = Db::$db->query(
			'',
			'SELECT a.id_attach, a.filename, a.size, m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
				t.id_member_started, t.id_first_msg, b.name AS board_name, c.id_cat, c.name AS cat_name
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE a.approved = {int:not_approved}
				AND a.attachment_type = {int:attachment_type}
				AND {query_see_board}
				{raw:approve_query}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items_per_page}',
			[
				'not_approved' => 0,
				'attachment_type' => 0,
				'start' => $start,
				'sort' => $sort,
				'items_per_page' => $items_per_page,
				'approve_query' => $approve_query,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$unapproved_items[] = [
				'id' => $row['id_attach'],
				'filename' => $row['filename'],
				'size' => round($row['size'] / 1024, 2),
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'poster' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'link' => $row['id_member'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				],
				'message' => [
					'id' => $row['id_msg'],
					'subject' => $row['subject'],
					'body' => BBCodeParser::load()->parse($row['body']),
					'time' => Time::create('@' . $row['poster_time'])->format(),
					'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				],
				'topic' => [
					'id' => $row['id_topic'],
				],
				'board' => [
					'id' => $row['id_board'],
					'name' => $row['board_name'],
				],
				'category' => [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
				],
			];
		}
		Db::$db->free_result($request);

		return $unapproved_items;
	}

	/**
	 * Callback function for UnapprovedAttachments
	 * count all the attachments waiting for approval that this approver can approve
	 *
	 * @param string $approve_query Additional restrictions based on the boards the approver can see
	 * @return int The number of unapproved attachments
	 */
	public static function list_getNumUnapprovedAttachments($approve_query): int
	{
		// How many unapproved attachments in total?
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			WHERE a.approved = {int:not_approved}
				AND a.attachment_type = {int:attachment_type}
				AND {query_see_message_board}
				' . $approve_query,
			[
				'not_approved' => 0,
				'attachment_type' => 0,
			],
		);
		list($total_unapproved_attachments) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $total_unapproved_attachments;
	}

	/**
	 * Backward compatibility wrapper for the replies sub-action.
	 */
	public static function unapprovedPosts(): void
	{
		self::load();
		self::$obj->subaction = 'replies';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the attachments sub-action.
	 */
	public static function unapprovedAttachments(): void
	{
		self::load();
		self::$obj->subaction = 'attachments';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the approve sub-action.
	 */
	public static function approveMessage(): void
	{
		self::load();
		self::$obj->subaction = 'approve';
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
		Lang::load('ModerationCenter');
		Theme::loadTemplate('ModerationCenter');

		IntegrationHook::call('integrate_post_moderation', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Approve a batch of posts (or topics in their own right)
	 *
	 * @param array $messages The IDs of the messages to approve
	 * @param array $messageDetails An array of information about each message, for the log
	 * @param string $current_view What type of unapproved items we're approving - can be 'topics' or 'replies'
	 */
	protected function approveMessages($messages, $messageDetails, $current_view = 'replies'): void
	{
		if ($current_view == 'topics') {
			Topic::approve($messages);

			// and tell the world about it
			foreach ($messages as $topic) {
				Logging::logAction('approve_topic', ['topic' => $topic, 'subject' => $messageDetails[$topic]['subject'], 'member' => $messageDetails[$topic]['member'], 'board' => $messageDetails[$topic]['board']]);
			}
		} else {
			Msg::approve($messages);

			// and tell the world about it again
			foreach ($messages as $post) {
				Logging::logAction('approve', ['topic' => $messageDetails[$post]['topic'], 'subject' => $messageDetails[$post]['subject'], 'member' => $messageDetails[$post]['member'], 'board' => $messageDetails[$post]['board']]);
			}
		}
	}

	/**
	 * Remove a batch of messages (or topics)
	 *
	 * @param array $messages The IDs of the messages to remove
	 * @param array $messageDetails An array of information about the messages for the log
	 * @param string $current_view What type of item we're removing - can be 'topics' or 'replies'
	 */
	protected function removeMessages($messages, $messageDetails, $current_view = 'replies'): void
	{
		// @todo something's not right, Msg::remove() checks permissions, Topic::remove() doesn't
		if ($current_view == 'topics') {
			Topic::remove($messages);

			// and tell the world about it
			foreach ($messages as $topic) {
				// Note, only log topic ID in native form if it's not gone forever.
				$topic_key = empty(Config::$modSettings['recycle_enable']) || Config::$modSettings['recycle_board'] != $messageDetails[$topic]['board'] ? 'topic' : 'old_topic_id';

				Logging::logAction('remove', [
					$topic_key => $topic,
					'subject' => $messageDetails[$topic]['subject'],
					'member' => $messageDetails[$topic]['member'],
					'board' => $messageDetails[$topic]['board'],
				]);
			}
		} else {
			foreach ($messages as $post) {
				Msg::remove($post);

				$topic_key = empty(Config::$modSettings['recycle_enable']) || Config::$modSettings['recycle_board'] != $messageDetails[$post]['board'] ? 'topic' : 'old_topic_id';

				Logging::logAction('delete', [
					$topic_key => $messageDetails[$post]['topic'],
					'subject' => $messageDetails[$post]['subject'],
					'member' => $messageDetails[$post]['member'],
					'board' => $messageDetails[$post]['board'],
				]);
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Posts::exportStatic')) {
	Posts::exportStatic();
}

?>