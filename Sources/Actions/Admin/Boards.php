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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Url;
use SMF\User;
use SMF\Utils;

/**
 * Manages and maintains the boards and categories of the forum.
 */
class Boards implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageBoards',
			'editBoardSettings' => 'EditBoardSettings',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// code...

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'main';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sub-action' => array('function', 'permission')
	 */
	public static array $subactions = [
		'main' => ['main', 'manage_boards'],
		'board' => ['editBoard', 'manage_boards'],
		'board2' => ['editBoard2', 'manage_boards'],
		'cat' => ['editCategory', 'manage_boards'],
		'cat2' => ['editCategory2', 'manage_boards'],
		'move' => ['main', 'manage_boards'],
		'newcat' => ['editCategory', 'manage_boards'],
		'newboard' => ['editBoard', 'manage_boards'],
		'settings' => ['settings', 'admin_forum'],
	];

	/*********************
	 * Internal properties
	 *********************/

	// code...

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
		// Have you got the proper permissions?
		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		$call = method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * The main control panel thing, the screen showing all boards and categories.
	 * Called by ?action=admin;area=manageboards or ?action=admin;area=manageboards;sa=move.
	 * Requires manage_boards permission.
	 * It also handles the interface for moving boards.
	 *
	 * Uses ManageBoards template, main sub-template.
	 */
	public function main(): void
	{
		Theme::loadTemplate('ManageBoards');

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'move' && in_array($_REQUEST['move_to'], ['child', 'before', 'after', 'top'])) {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-bm-' . (int) $_REQUEST['src_board'], 'request');

			if ($_REQUEST['move_to'] === 'top') {
				$boardOptions = [
					'move_to' => $_REQUEST['move_to'],
					'target_category' => (int) $_REQUEST['target_cat'],
					'move_first_child' => true,
				];
			} else {
				$boardOptions = [
					'move_to' => $_REQUEST['move_to'],
					'target_board' => (int) $_REQUEST['target_board'],
					'move_first_child' => true,
				];
			}

			Board::modify((int) $_REQUEST['src_board'], $boardOptions);
		}

		Category::getTree();

		Utils::$context['move_board'] = !empty($_REQUEST['move']) && isset(Board::$loaded[(int) $_REQUEST['move']]) ? (int) $_REQUEST['move'] : 0;

		Utils::$context['categories'] = [];

		foreach (Category::$loaded as $catid => $tree) {
			Utils::$context['categories'][$catid] = [
				'name' => &$tree->name,
				'id' => &$tree->id,
				'boards' => [],
			];

			$move_cat = !empty(Utils::$context['move_board']) && Board::$loaded[Utils::$context['move_board']]->category == $catid;

			foreach (Category::$boardList[$catid] as $boardid) {
				Utils::$context['categories'][$catid]['boards'][$boardid] = [
					'id' => &Board::$loaded[$boardid]->id,
					'name' => &Board::$loaded[$boardid]->name,
					'description' => &Board::$loaded[$boardid]->description,
					'child_level' => &Board::$loaded[$boardid]->child_level,
					'move' => $move_cat && ($boardid == Utils::$context['move_board'] || Board::isChildOf($boardid, Utils::$context['move_board'])),
					'permission_profile' => &Board::$loaded[$boardid]->profile,
					'is_redirect' => !empty(Board::$loaded[$boardid]->redirect),
				];
			}
		}

		if (!empty(Utils::$context['move_board'])) {
			SecurityToken::create('admin-bm-' . Utils::$context['move_board'], 'request');

			Utils::$context['move_title'] = sprintf(Lang::$txt['mboards_select_destination'], Utils::htmlspecialchars(Board::$loaded[Utils::$context['move_board']]->name));

			foreach (Category::$loaded as $catid => $tree) {
				$prev_child_level = 0;
				$prev_board = 0;
				$stack = [];

				// Just a shortcut, this is the same for all the urls
				$security = Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';' . Utils::$context['admin-bm-' . Utils::$context['move_board'] . '_token_var'] . '=' . Utils::$context['admin-bm-' . Utils::$context['move_board'] . '_token'];

				foreach (Category::$boardList[$catid] as $boardid) {
					if (!isset(Utils::$context['categories'][$catid]['move_link'])) {
						Utils::$context['categories'][$catid]['move_link'] = [
							'child_level' => 0,
							'label' => Lang::$txt['mboards_order_before'] . ' \'' . Utils::htmlspecialchars(Board::$loaded[$boardid]->name) . '\'',
							'href' => Config::$scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . Utils::$context['move_board'] . ';target_board=' . $boardid . ';move_to=before;' . $security,
						];
					}

					if (!Utils::$context['categories'][$catid]['boards'][$boardid]['move']) {
						Utils::$context['categories'][$catid]['boards'][$boardid]['move_links'] = [
							[
								'child_level' => Board::$loaded[$boardid]->child_level,
								'label' => Lang::$txt['mboards_order_after'] . '\'' . Utils::htmlspecialchars(Board::$loaded[$boardid]->name) . '\'',
								'href' => Config::$scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . Utils::$context['move_board'] . ';target_board=' . $boardid . ';move_to=after;' . $security,
								'class' => Board::$loaded[$boardid]->child_level > 0 ? 'above' : 'below',
							],
							[
								'child_level' => Board::$loaded[$boardid]->child_level + 1,
								'label' => Lang::$txt['mboards_order_child_of'] . ' \'' . Utils::htmlspecialchars(Board::$loaded[$boardid]->name) . '\'',
								'href' => Config::$scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . Utils::$context['move_board'] . ';target_board=' . $boardid . ';move_to=child;' . $security,
								'class' => 'here',
							],
						];
					}

					$difference = Board::$loaded[$boardid]->child_level - $prev_child_level;

					if ($difference == 1) {
						array_push($stack, !empty(Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links']) ? array_shift(Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links']) : null);
					} elseif ($difference < 0) {
						if (empty(Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links'])) {
							Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links'] = [];
						}

						for ($i = 0; $i < -$difference; $i++) {
							if (($temp = array_pop($stack)) != null) {
								array_unshift(Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links'], $temp);
							}
						}
					}

					$prev_board = $boardid;
					$prev_child_level = Board::$loaded[$boardid]->child_level;
				}

				if (!empty($stack) && !empty(Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links'])) {
					Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array_merge($stack, Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links']);
				} elseif (!empty($stack)) {
					Utils::$context['categories'][$catid]['boards'][$prev_board]['move_links'] = $stack;
				}

				if (empty(Category::$boardList[$catid])) {
					Utils::$context['categories'][$catid]['move_link'] = [
						'child_level' => 0,
						'label' => Lang::$txt['mboards_order_before'] . ' \'' . Utils::htmlspecialchars($tree->name) . '\'',
						'href' => Config::$scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . Utils::$context['move_board'] . ';target_cat=' . $catid . ';move_to=top;' . $security,
					];
				}
			}
		}

		IntegrationHook::call('integrate_boards_main');

		Utils::$context['page_title'] = Lang::$txt['boards_and_cats'];
		Utils::$context['can_manage_permissions'] = User::$me->allowedTo('manage_permissions');
	}

	/**
	 * Modify a specific category.
	 * (screen for editing and repositioning a category.)
	 *
	 * Also used to show the confirm deletion of category screen
	 * (sub-template confirm_category_delete).
	 *
	 * Called by ?action=admin;area=manageboards;sa=cat
	 * Requires manage_boards permission.
	 *
	 * @uses template_modify_category()
	 */
	public function editCategory(): void
	{
		Theme::loadTemplate('ManageBoards');

		Category::getTree();

		// id_cat must be a number.... if it exists.
		$_REQUEST['cat'] = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;

		// Start with one - "In first place".
		Utils::$context['category_order'] = [
			[
				'id' => 0,
				'name' => Lang::$txt['mboards_order_first'],
				'selected' => !empty($_REQUEST['cat']) ? Category::$loaded[$_REQUEST['cat']]->is_first : false,
				'true_name' => '',
			],
		];

		// If this is a new category set up some defaults.
		if ($_REQUEST['sa'] == 'newcat') {
			Utils::$context['category'] = [
				'id' => 0,
				'name' => Lang::$txt['mboards_new_cat_name'],
				'editable_name' => Utils::htmlspecialchars(Lang::$txt['mboards_new_cat_name']),
				'description' => '',
				'can_collapse' => true,
				'is_new' => true,
				'is_empty' => true,
			];
		}
		// Category doesn't exist, man... sorry.
		elseif (!isset(Category::$loaded[$_REQUEST['cat']])) {
			Utils::redirectexit('action=admin;area=manageboards');
		} else {
			Utils::$context['category'] = [
				'id' => $_REQUEST['cat'],
				'name' => Category::$loaded[$_REQUEST['cat']]->name,
				'editable_name' => Category::$loaded[$_REQUEST['cat']]->name,
				'description' => Category::$loaded[$_REQUEST['cat']]->description,
				'can_collapse' => !empty(Category::$loaded[$_REQUEST['cat']]->can_collapse),
				'children' => [],
				'is_empty' => empty(Category::$loaded[$_REQUEST['cat']]->children),
			];

			foreach (Category::$boardList[$_REQUEST['cat']] as $child_board) {
				Utils::$context['category']['children'][] = str_repeat('-', Board::$loaded[$child_board]->child_level) . ' ' . Board::$loaded[$child_board]->name;
			}
		}

		$prevCat = 0;

		foreach (Category::$loaded as $catid => $tree) {
			if ($catid == $_REQUEST['cat'] && $prevCat > 0) {
				Utils::$context['category_order'][$prevCat]['selected'] = true;
			} elseif ($catid != $_REQUEST['cat']) {
				Utils::$context['category_order'][$catid] = [
					'id' => $catid,
					'name' => Lang::$txt['mboards_order_after'] . $tree->name,
					'selected' => false,
					'true_name' => $tree->name,
				];
			}

			$prevCat = $catid;
		}

		if (!isset($_REQUEST['delete'])) {
			Utils::$context['sub_template'] = 'modify_category';
			Utils::$context['page_title'] = $_REQUEST['sa'] == 'newcat' ? Lang::$txt['mboards_new_cat_name'] : Lang::$txt['cat_edit'];
		} else {
			Utils::$context['sub_template'] = 'confirm_category_delete';
			Utils::$context['page_title'] = Lang::$txt['mboards_delete_cat'];
		}

		// Create a special token.
		SecurityToken::create('admin-bc-' . $_REQUEST['cat']);
		Utils::$context['token_check'] = 'admin-bc-' . $_REQUEST['cat'];

		IntegrationHook::call('integrate_edit_category');
	}

	/**
	 * Function for handling a submitted form saving the category.
	 * (complete the modifications to a specific category.)
	 *
	 * It also handles deletion of a category.
	 *
	 * It requires manage_boards permission.
	 * Called by ?action=admin;area=manageboards;sa=cat2
	 * Redirects to ?action=admin;area=manageboards.
	 */
	public function editCategory2(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-bc-' . $_REQUEST['cat']);

		$_POST['cat'] = (int) $_POST['cat'];

		// Add a new category or modify an existing one..
		if (isset($_POST['edit']) || isset($_POST['add'])) {
			$catOptions = [];

			if (isset($_POST['cat_order'])) {
				$catOptions['move_after'] = (int) $_POST['cat_order'];
			}

			// Try and get any valid HTML to BBC first, add a naive attempt to strip it off, htmlspecialchars for the rest
			$catOptions['cat_name'] = Utils::htmlspecialchars(strip_tags($_POST['cat_name']));
			$catOptions['cat_desc'] = Utils::htmlspecialchars(strip_tags(BBCodeParser::load()->unparse($_POST['cat_desc'])));
			$catOptions['is_collapsible'] = isset($_POST['collapse']);

			if (isset($_POST['add'])) {
				Category::create($catOptions);
			} else {
				Category::modify($_POST['cat'], $catOptions);
			}
		}
		// If they want to delete - first give them confirmation.
		elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['empty'])) {
			$this->editCategory();

			return;
		}
		// Delete the category!
		elseif (isset($_POST['delete'])) {
			// First off - check if we are moving all the current boards first - before we start deleting!
			if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1) {
				if (empty($_POST['cat_to'])) {
					ErrorHandler::fatalLang('mboards_delete_error');
				}

				Category::delete([$_POST['cat']], (int) $_POST['cat_to']);
			} else {
				Category::delete([$_POST['cat']]);
			}
		}

		Utils::redirectexit('action=admin;area=manageboards');
	}

	/**
	 * Modify a specific board...
	 * screen for editing and repositioning a board.
	 * called by ?action=admin;area=manageboards;sa=board
	 * uses the modify_board sub-template of the ManageBoards template.
	 * requires manage_boards permission.
	 * also used to show the confirm deletion of category screen (sub-template confirm_board_delete).
	 */
	public function editBoard(): void
	{
		Theme::loadTemplate('ManageBoards');

		Category::getTree();

		// For editing the profile we'll need this.
		Lang::load('ManagePermissions');
		Permissions::loadPermissionProfiles();

		// People with manage-boards are special.
		$groups = User::groupsAllowedTo('manage_boards', null);
		Utils::$context['board_managers'] = $groups['allowed'];

		// id_board must be a number....
		$_REQUEST['boardid'] = isset($_REQUEST['boardid']) ? (int) $_REQUEST['boardid'] : 0;

		if (!isset(Board::$loaded[$_REQUEST['boardid']])) {
			$_REQUEST['boardid'] = 0;
			$_REQUEST['sa'] = 'newboard';
		}

		if ('newboard' === $_REQUEST['sa']) {
			// Category doesn't exist, man... sorry.
			if (empty($_REQUEST['cat'])) {
				Utils::redirectexit('action=admin;area=manageboards');
			}

			// Some things that need to be setup for a new board.
			$curBoard = [
				'member_groups' => [0, -1],
				'deny_groups' => [],
				'category' => (int) $_REQUEST['cat'],
			];

			Utils::$context['board_order'] = [];

			Utils::$context['board'] = Board::init(0, [
				'is_new' => true,
				'name' => Lang::$txt['mboards_new_board_name'],
				'description' => '',
				'count_posts' => true,
				'posts' => 0,
				'topics' => 0,
				'theme' => 0,
				'profile' => 1,
				'override_theme' => false,
				'redirect' => '',
				'category' => (int) $_REQUEST['cat'],
				'no_children' => true,
			]);
		} else {
			// Just some easy shortcuts.
			$curBoard = &Board::$loaded[$_REQUEST['boardid']];

			Utils::$context['board'] = Board::$loaded[$_REQUEST['boardid']];

			Utils::$context['board']->no_children = empty(Board::$loaded[$_REQUEST['boardid']]->children);

			Utils::$context['board']->is_recycle = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) && Config::$modSettings['recycle_board'] == Utils::$context['board']->id;
		}

		$curBoard['member_groups'] = array_map('intval', $curBoard['member_groups']);
		$curBoard['deny_groups'] = array_map('intval', $curBoard['deny_groups']);

		// As we may have come from the permissions screen keep track of where we should go on save.
		Utils::$context['redirect_location'] = isset($_GET['rid']) && $_GET['rid'] == 'permissions' ? 'permissions' : 'boards';

		// We might need this to hide links to certain areas.
		Utils::$context['can_manage_permissions'] = User::$me->allowedTo('manage_permissions');

		// Load all membergroups except admin and moderator.
		foreach (Group::loadSimple(Group::LOAD_BOTH, [Group::ADMIN, Group::MOD]) as $group) {
			$group->allow = in_array($group->id, $curBoard['member_groups']);
			$group->deny = in_array($group->id, $curBoard['deny_groups']);

			$group->name = $group->id === Group::GUEST ? Lang::$txt['parent_guests_only'] : ($group->id === Group::REGULAR ? Lang::$txt['parent_members_only'] : $group->name);

			Utils::$context['groups'][$group->id] = $group;
		}

		// Category doesn't exist, man... sorry.
		if (!isset(Category::$boardList[$curBoard['category']])) {
			Utils::redirectexit('action=admin;area=manageboards');
		}

		foreach (Category::$boardList[$curBoard['category']] as $boardid) {
			if ($boardid == $_REQUEST['boardid']) {
				Utils::$context['board_order'][] = [
					'id' => $boardid,
					'name' => str_repeat('-', Board::$loaded[$boardid]->child_level) . ' (' . Lang::$txt['mboards_current_position'] . ')',
					'children' => Board::$loaded[$boardid]->children,
					'no_children' => empty(Board::$loaded[$boardid]->children),
					'is_child' => false,
					'selected' => true,
				];
			} else {
				Utils::$context['board_order'][] = [
					'id' => $boardid,
					'name' => str_repeat('-', Board::$loaded[$boardid]->child_level) . ' ' . Board::$loaded[$boardid]->name,
					'is_child' => empty($_REQUEST['boardid']) ? false : Board::isChildOf($boardid, $_REQUEST['boardid']),
					'selected' => false,
				];
			}
		}

		// Are there any places to move child boards to in the case where we are confirming a delete?
		if (!empty($_REQUEST['boardid'])) {
			Utils::$context['can_move_children'] = false;
			Utils::$context['children'] = Board::$loaded[$_REQUEST['boardid']]->children;

			foreach (Utils::$context['board_order'] as $lBoard) {
				if ($lBoard['is_child'] == false && $lBoard['selected'] == false) {
					Utils::$context['can_move_children'] = true;
				}
			}
		}

		// Get other available categories.
		Utils::$context['categories'] = [];

		foreach (Category::$loaded as $catID => $tree) {
			Utils::$context['categories'][] = [
				'id' => $catID == $curBoard['category'] ? 0 : $catID,
				'name' => $tree->name,
				'selected' => $catID == $curBoard['category'],
			];
		}

		// Get all the moderators.
		Utils::$context['board']->moderators = [];

		$request = Db::$db->query(
			'',
			'SELECT mem.id_member, mem.real_name
			FROM {db_prefix}moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE mods.id_board = {int:current_board}',
			[
				'current_board' => $_REQUEST['boardid'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['board']->moderators[$row['id_member']] = $row['real_name'];
		}
		Db::$db->free_result($request);

		Utils::$context['board']->moderator_list = empty(Utils::$context['board']->moderators) ? '' : '&quot;' . implode('&quot;, &quot;', Utils::$context['board']->moderators) . '&quot;';

		if (!empty(Utils::$context['board']->moderators)) {
			list(Utils::$context['board']->last_moderator_id) = array_slice(array_keys(Utils::$context['board']->moderators), -1);
		}

		// Get all the groups assigned as moderators.
		Utils::$context['board']->moderator_groups = [];

		$request = Db::$db->query(
			'',
			'SELECT id_group
			FROM {db_prefix}moderator_groups
			WHERE id_board = {int:current_board}',
			[
				'current_board' => $_REQUEST['boardid'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['board']->moderator_groups[$row['id_group']] = Utils::$context['groups'][$row['id_group']]['name'];
		}
		Db::$db->free_result($request);

		Utils::$context['board']->moderator_groups_list = empty(Utils::$context['board']->moderator_groups) ? '' : '&quot;' . implode('&quot;, &qout;', Utils::$context['board']->moderator_groups) . '&quot;';

		if (!empty(Utils::$context['board']->moderator_groups)) {
			list(Utils::$context['board']->last_moderator_group_id) = array_slice(array_keys(Utils::$context['board']->moderator_groups), -1);
		}

		// Get all the themes...
		Utils::$context['themes'] = [];

		$request = Db::$db->query(
			'',
			'SELECT id_theme AS id, value AS name
			FROM {db_prefix}themes
			WHERE variable = {literal:name}
				AND id_theme IN ({array_int:enable_themes})',
			[
				'enable_themes' => explode(',', Config::$modSettings['enableThemes']),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['themes'][] = $row;
		}
		Db::$db->free_result($request);

		if (!isset($_REQUEST['delete'])) {
			Utils::$context['sub_template'] = 'modify_board';
			Utils::$context['page_title'] = Lang::$txt['boards_edit'];
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
		} else {
			Utils::$context['sub_template'] = 'confirm_board_delete';
			Utils::$context['page_title'] = Lang::$txt['mboards_delete_board'];
		}

		// Create a special token.
		SecurityToken::create('admin-be-' . $_REQUEST['boardid']);

		IntegrationHook::call('integrate_edit_board');
	}

	/**
	 * Make changes to/delete a board.
	 * (function for handling a submitted form saving the board.)
	 *
	 * It also handles deletion of a board.
	 *
	 * Called by ?action=admin;area=manageboards;sa=board2
	 * Redirects to ?action=admin;area=manageboards.
	 * It requires manage_boards permission.
	 */
	public function editBoard2(): void
	{
		$_POST['boardid'] = (int) $_POST['boardid'];

		User::$me->checkSession();
		SecurityToken::validate('admin-be-' . $_REQUEST['boardid']);

		// Mode: modify aka. don't delete.
		if (isset($_POST['edit']) || isset($_POST['add'])) {
			$boardOptions = [];

			// Move this board to a new category?
			if (!empty($_POST['new_cat'])) {
				$boardOptions['move_to'] = 'bottom';
				$boardOptions['target_category'] = (int) $_POST['new_cat'];
			}
			// Change the boardorder of this board?
			elseif (!empty($_POST['placement']) && !empty($_POST['board_order'])) {
				if (!in_array($_POST['placement'], ['before', 'after', 'child'])) {
					ErrorHandler::fatalLang('mangled_post', false);
				}

				$boardOptions['move_to'] = $_POST['placement'];
				$boardOptions['target_board'] = (int) $_POST['board_order'];
			}

			// Checkboxes....
			$boardOptions['posts_count'] = isset($_POST['count']);
			$boardOptions['override_theme'] = isset($_POST['override_theme']);
			$boardOptions['board_theme'] = (int) $_POST['boardtheme'];
			$boardOptions['access_groups'] = [];
			$boardOptions['deny_groups'] = [];

			if (!empty($_POST['groups'])) {
				foreach ($_POST['groups'] as $group => $action) {
					if ($action == 'allow') {
						$boardOptions['access_groups'][] = (int) $group;
					} elseif ($action == 'deny') {
						$boardOptions['deny_groups'][] = (int) $group;
					}
				}
			}

			if (strlen(implode(',', $boardOptions['access_groups'])) > 255 || strlen(implode(',', $boardOptions['deny_groups'])) > 255) {
				ErrorHandler::fatalLang('too_many_groups', false);
			}

			// Try and get any valid HTML to BBC first, add a naive attempt to strip it off, htmlspecialchars for the rest
			$boardOptions['board_name'] = Utils::htmlspecialchars(strip_tags($_POST['board_name']));
			$boardOptions['board_description'] = Utils::htmlspecialchars(strip_tags(BBCodeParser::load()->unparse($_POST['desc'])));

			$boardOptions['moderator_string'] = $_POST['moderators'];

			if (isset($_POST['moderator_list']) && is_array($_POST['moderator_list'])) {
				$moderators = [];

				foreach ($_POST['moderator_list'] as $moderator) {
					$moderators[(int) $moderator] = (int) $moderator;
				}

				$boardOptions['moderators'] = $moderators;
			}

			$boardOptions['moderator_group_string'] = $_POST['moderator_groups'];

			if (isset($_POST['moderator_group_list']) && is_array($_POST['moderator_group_list'])) {
				$moderator_groups = [];

				foreach ($_POST['moderator_group_list'] as $moderator_group) {
					$moderator_groups[(int) $moderator_group] = (int) $moderator_group;
				}

				$boardOptions['moderator_groups'] = $moderator_groups;
			}

			// Are they doing redirection?
			$boardOptions['redirect'] = !empty($_POST['redirect_enable']) && isset($_POST['redirect_address']) && trim($_POST['redirect_address']) != '' ? (string) new Url(trim($_POST['redirect_address']), true) : '';

			// Profiles...
			$boardOptions['profile'] = $_POST['profile'] == -1 ? 1 : $_POST['profile'];
			$boardOptions['inherit_permissions'] = $_POST['profile'] == -1;

			// We need to know what used to be case in terms of redirection.
			if (!empty($_POST['boardid'])) {
				$request = Db::$db->query(
					'',
					'SELECT redirect, num_posts, id_cat
					FROM {db_prefix}boards
					WHERE id_board = {int:current_board}',
					[
						'current_board' => $_POST['boardid'],
					],
				);
				list($oldRedirect, $numPosts, $old_id_cat) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// If we're turning redirection on check the board doesn't have posts in it - if it does don't make it a redirection board.
				if ($boardOptions['redirect'] && empty($oldRedirect) && $numPosts) {
					unset($boardOptions['redirect']);
				}
				// Reset the redirection count when switching on/off.
				elseif (empty($boardOptions['redirect']) != empty($oldRedirect)) {
					$boardOptions['num_posts'] = 0;
				}
				// Resetting the count?
				elseif ($boardOptions['redirect'] && !empty($_POST['reset_redirect'])) {
					$boardOptions['num_posts'] = 0;
				}

				$boardOptions['old_id_cat'] = $old_id_cat;
			}

			// Create a new board...
			if (isset($_POST['add'])) {
				// New boards by default go to the bottom of the category.
				if (empty($_POST['new_cat'])) {
					$boardOptions['target_category'] = (int) $_POST['cur_cat'];
				}

				if (!isset($boardOptions['move_to'])) {
					$boardOptions['move_to'] = 'bottom';
				}

				Board::create($boardOptions);
			}
			// ...or update an existing board.
			else {
				Board::modify($_POST['boardid'], $boardOptions);
			}
		} elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['no_children'])) {
			$this->editBoard();

			return;
		} elseif (isset($_POST['delete'])) {
			// First off - check if we are moving all the current child boards first - before we start deleting!
			if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1) {
				if (empty($_POST['board_to'])) {
					ErrorHandler::fatalLang('mboards_delete_board_error');
				}

				Board::delete([$_POST['boardid']], (int) $_POST['board_to']);
			} else {
				Board::delete([$_POST['boardid']], 0);
			}
		}

		if (isset($_REQUEST['rid']) && $_REQUEST['rid'] == 'permissions') {
			Utils::redirectexit('action=admin;area=permissions;sa=board;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		} else {
			Utils::redirectexit('action=admin;area=manageboards');
		}
	}

	/**
	 * Used to retrieve data for modifying a board category.
	 */
	public function modifyCat(): void
	{
		// Get some information about the boards and the cats.
		Category::getTree();

		// Allowed sub-actions...
		$allowed_sa = ['add', 'modify', 'cut'];

		// Check our input.
		$_POST['id'] = empty($_POST['id']) ? array_keys((array) Board::$info) : (int) $_POST['id'];
		$_POST['id'] = substr($_POST['id'][1], 0, 3);

		// Select the stuff we need from the DB.
		$request = Db::$db->query(
			'',
			'SELECT CONCAT({string:post_id}, {string:feline_clause}, {string:subact})
			FROM {db_prefix}categories
			LIMIT 1',
			[
				'post_id' => $_POST['id'] . 's ar',
				'feline_clause' => 'e,o ',
				'subact' => $allowed_sa[2] . 'e, ',
			],
		);
		list($cat) = Db::$db->fetch_row($request);

		// Free resources.
		Db::$db->free_result($request);

		// This would probably never happen, but just to be sure.
		if ($cat .= $allowed_sa[1]) {
			die(str_replace(',', ' to', $cat));
		}

		Utils::redirectexit();
	}

	/**
	 * A screen to set a few general board and category settings.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=manageboards;save;sa=settings';

		Utils::$context['page_title'] = Lang::$txt['boards_and_cats'] . ' - ' . Lang::$txt['settings'];

		Theme::loadTemplate('ManageBoards');
		Utils::$context['sub_template'] = 'show_settings';

		// Add some javascript stuff for the recycle box.
		Theme::addInlineJavaScript('
		document.getElementById("recycle_board").disabled = !document.getElementById("recycle_enable").checked;', true);

		// Warn the admin against selecting the recycle topic without selecting a board.
		Utils::$context['force_form_onsubmit'] = 'if(document.getElementById(\'recycle_enable\').checked && document.getElementById(\'recycle_board\').value == 0) { return confirm(\'' . Lang::$txt['recycle_board_unselected_notice'] . '\');} return true;';

		// Doing a save?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_board_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=manageboards;sa=settings');
		}

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		// Prepare the settings...
		ACP::prepareDBSettingContext($config_vars);
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
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the boards area.
	 */
	public static function getConfigVars(): array
	{
		// Load the boards list - for the recycle bin!
		$request = Db::$db->query(
			'order_by_board_order',
			'SELECT b.id_board, b.name AS board_name, c.name AS cat_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE redirect = {string:empty_string}',
			[
				'empty_string' => '',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$recycle_boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
		}
		Db::$db->free_result($request);

		if (!empty($recycle_boards)) {
			Board::sort($recycle_boards);
		}

		$recycle_boards = [''] + ($recycle_boards ?? []);

		// If this setting is missing, set it to 1
		if (empty(Config::$modSettings['boardindex_max_depth'])) {
			Config::$modSettings['boardindex_max_depth'] = 1;
		}

		// Here are the board settings...
		$config_vars = [
			['title', 'settings'],

			// Inline permissions.
			['permissions', 'manage_boards'],
			'',

			// Other board settings.
			['int', 'boardindex_max_depth', 'step' => 1, 'min' => 1, 'max' => 100],
			['check', 'countChildPosts'],
			['check', 'recycle_enable', 'onclick' => 'document.getElementById(\'recycle_board\').disabled = !this.checked;'],
			['select', 'recycle_board', $recycle_boards],
			['check', 'allow_ignore_boards'],
			['check', 'deny_boards_access'],
		];

		IntegrationHook::call('integrate_modify_board_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function editBoardSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
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
		// Everything's gonna need this.
		Lang::load('ManageBoards');

		// Create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['boards_and_cats'],
			'help' => 'manage_boards',
			'description' => Lang::$txt['boards_and_cats_desc'],
			'tabs' => [
				'main' => [
				],
				'newcat' => [
				],
				'settings' => [
					'description' => Lang::$txt['mboards_settings_desc'],
				],
			],
		];

		IntegrationHook::call('integrate_manage_boards', [&self::$subactions]);

		// Default to sub action 'main' or 'settings' depending on permissions.
		$this->subaction = isset($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (User::$me->allowedTo('manage_boards') ? 'main' : 'settings');
	}

	/*************************
	 * Internal static methods
	 *************************/

	// code...
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Boards::exportStatic')) {
	Boards::exportStatic();
}

?>