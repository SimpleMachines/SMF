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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * This class loads information about the current board, as well as other boards
 * when needed. It also handles low-level tasks for managing boards, such as
 * creating, deleting, and modifying them, as well as minor tasks relating to
 * boards, such as marking them read.
 *
 * Implements the \ArrayAccess interface to ease backward compatibility with the
 * deprecated global $board_info variable.
 *
 * @todo Refactor MessageIndex.php into an extension of this class.
 */
class Board implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'loadBoard',
			'markRead' => 'MarkRead',
			'markBoardsRead' => 'markBoardsRead',
			'getMsgMemberID' => 'getMsgMemberID',
			'modify' => 'modifyBoard',
			'create' => 'createBoard',
			'delete' => 'deleteBoards',
			'reorder' => 'reorderBoards',
			'fixChildren' => 'fixChildren',
			'sort' => 'sortBoards',
			'getModerators' => 'getBoardModerators',
			'getModeratorGroups' => 'getBoardModeratorGroups',
			'isChildOf' => 'isChildOf',
			'getParents' => 'getBoardParents',
		],
		'prop_names' => [
			'board_id' => 'board',
			'info' => 'board_info',
			'loaded' => 'boards',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This board's ID number.
	 */
	public int $id;

	/**
	 * @var object
	 *
	 * This board's category.
	 * An instance of SMF\Category.
	 */
	public object $cat;

	/**
	 * @var string
	 *
	 * This board's name.
	 */
	public string $name = '';

	/**
	 * @var string
	 *
	 * This board's description.
	 */
	public string $description = '';

	/**
	 * @var array
	 *
	 * Info about individual members allowed to moderate this board.
	 */
	public array $moderators = [];

	/**
	 * @var array
	 *
	 * Info about member groups allowed to moderate this board.
	 */
	public array $moderator_groups = [];

	/**
	 * @var array
	 *
	 * Info about member groups allowed to access this board.
	 */
	public array $member_groups = [];

	/**
	 * @var array
	 *
	 * Info about member groups forbidden from accessing this board.
	 */
	public array $deny_groups = [];

	/**
	 * @var int
	 *
	 * Number of posts in this board.
	 */
	public int $num_posts = 0;

	/**
	 * @var int
	 *
	 * Number of topics in this board.
	 */
	public int $num_topics = 0;

	/**
	 * @var int
	 *
	 * Number of unapproved topics in this board.
	 */
	public int $unapproved_topics = 0;

	/**
	 * @var int
	 *
	 * Number of unapproved posts in this board.
	 */
	public int $unapproved_posts = 0;

	/**
	 * @var int
	 *
	 * Number of unapproved topics in this board that were started by the
	 * current user.
	 */
	public int $unapproved_user_topics = 0;

	/**
	 * @var array
	 *
	 * All of this board's ancestor boards.
	 */
	public array $parent_boards = [];

	/**
	 * @var int
	 *
	 * ID of this board's immediate ancestor.
	 */
	public int $parent = 0;

	/**
	 * @var int
	 *
	 * The hierarchy level of this board.
	 */
	public int $child_level = 0;

	/**
	 * @var int
	 *
	 * The positional order of this board.
	 */
	public int $order = 0;

	/**
	 * @var int
	 *
	 * ID of the board previous to this one in positional order.
	 */
	public int $prev_board = 0;

	/**
	 * @var array
	 *
	 * Boards that are children of this board.
	 */
	public array $children = [];

	/**
	 * @var int
	 *
	 * This board's theme.
	 */
	public int $theme = 0;

	/**
	 * @var bool
	 *
	 * Whether this board's theme overrides the default.
	 */
	public bool $override_theme = false;

	/**
	 * @var int
	 *
	 * The permission profile of this board.
	 */
	public int $profile = 1;

	/**
	 * @var string
	 *
	 * The redirection URL (if any) for this board.
	 */
	public string $redirect = '';

	/**
	 * @var bool
	 *
	 * Whether this board is the recycle bin board.
	 */
	public bool $recycle = false;

	/**
	 * @var bool
	 *
	 * Whether posts in this board count toward a user's total post count.
	 */
	public bool $count_posts = true;

	/**
	 * @var bool
	 *
	 * Whether the current topic (if any) is approved.
	 */
	public bool $cur_topic_approved = false;

	/**
	 * @var int
	 *
	 * User who started the current topic (if any).
	 */
	public int $cur_topic_starter = 0;

	/**
	 * @var string
	 *
	 * URL for this board.
	 */
	public string $url = '';

	/**
	 * @var string
	 *
	 * HTML anchor link for this board.
	 */
	public string $link = '';

	/**
	 * @var array
	 *
	 * HTML anchor links for this board's children.
	 */
	public array $link_children = [];

	/**
	 * @var array
	 *
	 * HTML anchor links for this board's moderators.
	 */
	public array $link_moderators = [];

	/**
	 * @var array
	 *
	 * HTML anchor links for this board's moderator groups.
	 */
	public array $link_moderator_groups = [];

	/**
	 * @var int
	 *
	 * ID number of the latest message posted in this board.
	 */
	public int $last_msg = 0;

	/**
	 * @var int
	 *
	 * ID number of the latest message in the forum at the time when this board
	 * was last updated.
	 */
	public int $msg_updated = 0;

	/**
	 * @var array
	 *
	 * Info about the latest post in this board.
	 */
	public array $last_post = [];

	/**
	 * @var bool
	 *
	 * Whether the board contains posts that the current user has not read.
	 */
	public bool $new = false;

	/**
	 * @var string
	 *
	 * What error (if any) was encountered while loading this board.
	 */
	public string $error;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var int
	 *
	 * ID number of the board being viewed.
	 *
	 * As a general rule, code outside this class should use Board::$info->id
	 * rather than Board::$board_id. The only exception to this rule is in code
	 * executed before Board::load() has been called.
	 */
	public static $board_id;

	/**
	 * @var object
	 *
	 * Instance of this class for board we are currently in.
	 */
	public static $info;

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * IDs of groups that can access this board even though they are not
	 * explicitly granted access according to the database. Basically, this
	 * means any groups that have the manage_boards permission that aren't
	 * in the board's list of allowed groups.
	 */
	protected array $overridden_access_groups = [];

	/**
	 * @var array
	 *
	 * IDs of groups that can access this board even though they are supposedly
	 * denied access according to the database.  Basically, this means any
	 * groups that have the manage_boards permission that are in the board's
	 * list of denied groups.
	 */
	protected array $overridden_deny_groups = [];

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_board' => 'id',
		'board_name' => 'name',
		'board_description' => 'description',
		'groups' => 'member_groups',
		'access_groups' => 'member_groups',
		'deny_member_groups' => 'deny_groups',
		'posts' => 'num_posts',
		'topics' => 'num_topics',
		'id_parent' => 'parent',
		'level' => 'child_level',
		'board_order' => 'order',
		'id_theme' => 'theme',
		'board_theme' => 'theme',
		'id_profile' => 'profile',
		'posts_count' => 'count_posts',
		'href' => 'url',
		'id_last_msg' => 'last_msg',
		'id_msg_updated' => 'msg_updated',

		// Square brackets are parsed to find array elements.
		'category' => 'cat[id]',
		'id_cat' => 'cat[id]',

		// Initial exclamation mark means inverse of the property.
		'is_read' => '!new',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Properties that should be cached in different situations.
	 */
	protected static array $cache_props = [
		// When caching Board::$info
		'info' => [
			'id',
			'cat',
			'name',
			'description',
			'moderators',
			'moderator_groups',
			'member_groups',
			'deny_groups',
			'num_posts',
			'num_topics',
			'unapproved_topics',
			'unapproved_posts',
			'parent_boards',
			'parent',
			'child_level',
			'order',
			'prev_board',
			'theme',
			'override_theme',
			'profile',
			'redirect',
			'recycle',
			'count_posts',
			'cur_topic_approved',
			'cur_topic_starter',
		],
	];

	/**
	 * @var array
	 *
	 * Holds parsed versions of board descriptions.
	 */
	protected static array $parsed_descriptions = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		if (in_array($this->prop_aliases[$prop] ?? $prop, ['member_groups', 'deny_groups'])) {
			if (!is_array($value)) {
				$value = explode(',', $value);
			}

			$value = array_map('intval', array_filter($value, 'strlen'));

			// Special handling for access for board manager groups.
			if (!empty(Config::$modSettings['board_manager_groups']) && in_array($this->prop_aliases[$prop] ?? $prop, ['member_groups', 'deny_groups']) && is_array($value)) {
				$board_manager_groups = array_map('intval', array_filter(explode(',', Config::$modSettings['board_manager_groups']), 'strlen'));

				if (($this->prop_aliases[$prop] ?? $prop) === 'deny_groups') {
					$this->overridden_deny_groups = array_intersect($board_manager_groups, $value);
					$value = array_diff($value, $board_manager_groups);
				} else {
					$this->overridden_access_groups = array_diff($board_manager_groups, $value);
					$value = array_merge($board_manager_groups, $value);
				}
			}

			$value = array_unique(array_diff($value, [1]));

			sort($value);
		}

		// Special handling for the category.
		if (($this->prop_aliases[$prop] ?? null) === 'cat[id]') {
			$this->cat = Category::init($value);

			return;
		}

		$this->customPropertySet($prop, $value);
	}

	/**
	 * Saves this board to the database.
	 *
	 * @param array $boardOptions An array of options related to the board.
	 */
	public function save(array $boardOptions = []): void
	{
		User::$me->isAllowedTo('manage_boards');

		// Undo any overrides of the group access values.
		$access_groups = array_unique(array_diff($this->member_groups, $this->overridden_access_groups, [1]));
		$deny_groups = array_unique(array_diff(array_merge($this->deny_groups, $this->overridden_deny_groups), [1]));

		sort($access_groups);
		sort($deny_groups);

		// Saving a new board.
		if (empty($this->id)) {
			$columns = [
				'id_cat' => 'int',
				'child_level' => 'int',
				'id_parent' => 'int',
				'board_order' => 'int',
				'id_last_msg' => 'int',
				'id_msg_updated' => 'int',
				'member_groups' => 'string-255',
				'id_profile' => 'int',
				'name' => 'string-255',
				'description' => 'string',
				'num_topics' => 'int',
				'num_posts' => 'int',
				'count_posts' => 'int',
				'id_theme' => 'int',
				'override_theme' => 'int',
				'unapproved_posts' => 'int',
				'unapproved_topics' => 'int',
				'redirect' => 'string-255',
				'deny_member_groups' => 'string-255',
			];

			$params = [
				$this->cat['id'],
				$this->child_level,
				$this->parent,
				$this->order,
				$this->last_msg,
				$this->msg_updated,
				implode(',', $access_groups),
				$this->profile,
				$this->name,
				$this->description,
				$this->num_topics,
				$this->num_posts,
				(int) $this->count_posts,
				$this->theme,
				(int) $this->override_theme,
				$this->unapproved_posts,
				$this->unapproved_topics,
				$this->redirect,
				implode(',', $deny_groups),
			];

			$this->id = Db::$db->insert(
				'',
				'{db_prefix}boards',
				$columns,
				$params,
				['id_board'],
				1,
			);

			self::$loaded[$this->id] = $this;
		}
		// Updating an existing board.
		else {
			$set = [
				'id_cat = {int:id_cat}',
				'child_level = {int:child_level}',
				'id_parent = {int:id_parent}',
				'board_order = {int:board_order}',
				'id_last_msg = {int:last_msg}',
				'id_msg_updated = {int:msg_updated}',
				'member_groups = {string:member_groups}',
				'id_profile = {int:profile}',
				'name = {string:board_name}',
				'description = {string:board_description}',
				'num_topics = {int:num_topics}',
				'num_posts = {int:num_posts}',
				'count_posts = {int:count_posts}',
				'id_theme = {int:board_theme}',
				'override_theme = {int:override_theme}',
				'unapproved_posts = {int:unapproved_posts}',
				'unapproved_topics = {int:unapproved_topics}',
				'redirect = {string:redirect}',
				'deny_member_groups = {string:deny_groups}',
			];

			$params = [
				'id' => $this->id,
				'id_cat' => $this->cat['id'],
				'child_level' => $this->child_level,
				'id_parent' => $this->parent,
				'board_order' => $this->order,
				'last_msg' => $this->last_msg,
				'msg_updated' => $this->msg_updated,
				'member_groups' => implode(',', $access_groups),
				'profile' => $this->profile,
				'board_name' => $this->name,
				'board_description' => $this->description,
				'num_topics' => $this->num_topics,
				'num_posts' => $this->num_posts,
				'count_posts' => (int) $this->count_posts,
				'board_theme' => $this->theme,
				'override_theme' => (int) $this->override_theme,
				'unapproved_posts' => $this->unapproved_posts,
				'unapproved_topics' => $this->unapproved_topics,
				'redirect' => $this->redirect,
				'deny_groups' => implode(',', $deny_groups),
			];

			// Do any hooks want to add or adjust anything?
			IntegrationHook::call('integrate_modify_board', [$this->id, $boardOptions, &$set, &$params]);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET ' . (implode(', ', $set)) . '
				WHERE id_board = {int:id}',
				$params,
			);
		}

		// Before we add new access_groups or deny_groups, remove all of the old entries.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}board_permissions_view
			WHERE id_board = {int:this_board}',
			[
				'this_board' => $this->id,
			],
		);

		$inserts = [];

		foreach ($access_groups as $id_group) {
			$inserts[] = [$id_group, $this->id, 0];
		}

		foreach ($deny_groups as $id_group) {
			$inserts[] = [$id_group, $this->id, 1];
		}

		if ($inserts != []) {
			Db::$db->insert(
				'insert',
				'{db_prefix}board_permissions_view',
				['id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'],
				$inserts,
				['id_group', 'id_board', 'deny'],
			);
		}

		// Reset current moderators for this board - if there are any!
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}moderators
			WHERE id_board = {int:this_board}',
			[
				'this_board' => $this->id,
			],
		);

		if (!empty($this->moderators)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}moderators',
				['id_board' => 'int', 'id_member' => 'int'],
				array_map(fn ($mod) => [$this->id, $mod['id']], $this->moderators),
				['id_board', 'id_member'],
			);
		}

		// Reset current moderator groups for this board - if there are any!
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}moderator_groups
			WHERE id_board = {int:this_board}',
			[
				'this_board' => $this->id,
			],
		);

		if (!empty($this->moderator_groups)) {
			Db::$db->insert(
				'insert',
				'{db_prefix}moderator_groups',
				['id_board' => 'int', 'id_group' => 'int'],
				array_map(fn ($mod) => [$this->id, $mod['id']], $this->moderator_groups),
				['id_board', 'id_group'],
			);
		}

		// If we were moving boards, ensure that the order is correct.
		if (isset($boardOptions['move_to'])) {
			self::reorder();
		}

		// The caches might now be wrong.
		Config::updateModSettings(['settings_updated' => time()]);
		CacheApi::clean('data');
	}

	/**
	 * Changes this board's position in the overall board tree.
	 *
	 * @param string $move_to Where to move the board. Value can be one of
	 *    'top', 'bottom', 'child', 'before', or 'after'.
	 * @param ?int $target_category ID of the board's new category.
	 *    Only applicable when $move_to is 'top' or 'bottom'.
	 * @param ?int $target_board ID of another board that this board is being
	 *    moved next to or is becoming a child of. Only applicable when $move_to
	 *    is 'child', 'before,' or 'after'.
	 * @param bool $first_child Whether this should be the first or last child
	 *    of its new parent. Only applicable when $move_to is 'child'.
	 * @param bool $save Whether to call the save() method for affected boards.
	 *    If set to false, the caller is responsible for calling the save()
	 *    method for each of the affected boards.
	 * @return array IDs of all boards that were affected by this move.
	 */
	public function move(
		string $move_to,
		?int $target_category = null,
		?int $target_board = null,
		bool $first_child = false,
		bool $save = true,
	): array {
		// Do we have what we need?
		switch ($move_to) {
			case 'top':
			case 'bottom':
				if (!isset($target_category)) {
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['modify_board_incorrect_move_to'], $move_to), E_USER_ERROR);
				}
				break;

			default:
				if (!isset($target_board)) {
					Lang::load('Errors');
					trigger_error(sprintf(Lang::$txt['modify_board_incorrect_move_to'], $move_to), E_USER_ERROR);
				}
				break;
		}

		// IDs of all boards that were affected by this move.
		$affected_boards = [$this->id];

		// Ensure everything is loaded.
		if ((isset($target_category) && !isset(Category::$loaded[$target_category])) || (isset($target_board) && !isset(self::$loaded[$target_board]))) {
			Category::getTree();
		}

		// Where are we moving this board to?
		switch ($move_to) {
			case 'top':
				$id_cat = $target_category;
				$child_level = 0;
				$id_parent = 0;
				$after = Category::$loaded[$id_cat]->last_board_order;
				break;

			case 'bottom':
				$id_cat = $target_category;
				$child_level = 0;
				$id_parent = 0;
				$after = 0;

				foreach (Category::$loaded[$id_cat]->children as $id_board => $dummy) {
					$after = max($after, self::$loaded[$id_board]->order);
				}

				break;

			case 'child':
				$id_cat = self::$loaded[$target_board]->category;
				$child_level = self::$loaded[$target_board]->child_level + 1;
				$id_parent = $target_board;

				// People can be creative, in many ways...
				if (self::isChildOf($id_parent, $this->id)) {
					ErrorHandler::fatalLang('mboards_parent_own_child_error', false);
				} elseif ($id_parent == $this->id) {
					ErrorHandler::fatalLang('mboards_board_own_child_error', false);
				}

				$after = self::$loaded[$target_board]->order;

				// Check if there are already children and (if so) get the max board order.
				if (!empty(self::$loaded[$id_parent]->children) && empty($first_child)) {
					foreach (self::$loaded[$id_parent]->children as $childBoard_id => $dummy) {
						$after = max($after, self::$loaded[$childBoard_id]->order);
					}
				}

				break;

			case 'before':
			case 'after':
				$id_cat = self::$loaded[$target_board]->category;
				$child_level = self::$loaded[$target_board]->child_level;
				$id_parent = self::$loaded[$target_board]->parent;
				$after = self::$loaded[$target_board]->order - ($move_to == 'before' ? 1 : 0);
				break;

			default:
				Lang::load('Errors');
				trigger_error(sprintf(Lang::$txt['modify_board_incorrect_move_to'], $move_to), E_USER_ERROR);
				break;
		}

		// Get a list of children of this board.
		$child_list = [];
		Category::recursiveBoards($child_list, $this);

		// See if there are changes that affect children.
		foreach ($child_list as $child_id) {
			if ($child_level != $this->child_level) {
				self::$loaded[$child_id]->child_level += ($child_level - $this->child_level);
				$affected_boards[] = $child_id;
			}

			if ($id_cat != self::$loaded[$child_id]->category) {
				self::$loaded[$child_id]->category = $id_cat;
				$affected_boards[] = $child_id;
			}
		}

		foreach (self::$loaded as $board) {
			if ($board->order <= $after) {
				continue;
			}

			if ($board->id === $this->id) {
				continue;
			}

			$board->order += (1 + count($child_list));
			$affected_boards[] = $board->id;
		}

		// Now change the properties of this board itself.
		$this->category = $id_cat;
		$this->parent = $id_parent;
		$this->child_level = $child_level;
		$this->order = $after + 1;

		// Are we saving the changes?
		if ($save) {
			foreach ($affected_boards as $board_id) {
				self::$loaded[$board_id]->save();
			}
		}

		return $affected_boards;
	}

	/**
	 * Parses BBCode in $this->description and updates it with the result.
	 */
	public function parseDescription(): void
	{
		if (empty($this->description)) {
			return;
		}

		// Save the unparsed description in case we need it later.
		if (!isset($this->custom['unparsed_description'])) {
			$this->custom['unparsed_description'] = $this->description;
		}

		if (!empty(CacheApi::$enable)) {
			if (empty(self::$parsed_descriptions)) {
				self::$parsed_descriptions = CacheApi::get('parsed_boards_descriptions', 864000) ?? [];
			}

			if (!isset(self::$parsed_descriptions[$this->id])) {
				self::$parsed_descriptions[$this->id] = BBCodeParser::load()->parse($this->description, false, '', Utils::$context['description_allowed_tags']);

				CacheApi::put('parsed_boards_descriptions', self::$parsed_descriptions, 864000);
			}

			$this->description = self::$parsed_descriptions[$this->id];
		} else {
			$this->description = BBCodeParser::load()->parse($this->description, false, '', Utils::$context['description_allowed_tags']);
		}
	}

	/**
	 * Restores $this->description to its unparsed value.
	 */
	public function unparseDescription(): void
	{
		if (isset($this->custom['unparsed_description'])) {
			$this->description = $this->custom['unparsed_description'];
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads boards by ID number and/or by custom query.
	 *
	 * If both arguments are empty, loads the board in self::$board_id.
	 *
	 * @param array|int $ids The ID numbers of zero or more boards.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return array Instances of this class for the loaded boards.
	 */
	public static function load(array|int $ids = [], array $query_customizations = []): array
	{
		$loaded = [];

		$ids = array_unique(array_map('intval', (array) $ids));

		if (empty($query_customizations)) {
			if (empty($ids)) {
				$loaded[] = self::init();
			} else {
				foreach ($ids as $id) {
					$loaded[] = self::init($id);
				}
			}

			return $loaded;
		}

		$selects = $query_customizations['selects'] ?? ['b.*'];
		$joins = $query_customizations['joins'] ?? [];
		$where = $query_customizations['where'] ?? [];
		$order = $query_customizations['order'] ?? [];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [];

		if ($ids !== []) {
			$where[] = 'b.id_board IN ({array_int:ids})';
			$params['ids'] = $ids;
		}

		foreach (self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$row['id_board'] = (int) $row['id_board'];

			if (isset(self::$loaded[$row['id_board']])) {
				self::$loaded[$row['id_board']]->set($row);
				$loaded[] = self::$loaded[$row['id_board']];
			} else {
				$loaded[] = self::init($row['id_board'], $row);
			}
		}

		// Return the instances we just loaded.
		return $loaded;
	}

	/**
	 * Creates a new instance of this class if necessary, or updates an existing
	 * instance if one already exists for the given ID number. In either case,
	 * the instance will be returned.
	 *
	 * If $id is empty but self::$board_id isn't, $id is set to self::$board_id.
	 * If $id and self::$board_id are both empty, returns null.
	 *
	 * If an instance already exists for the given ID number, then $props will
	 * simply be passed to the existing instance's set() method, and then the
	 * existing instance will be returned.
	 *
	 * If an instance does not exist for the given ID number and $props is
	 * empty, a query will be performed to populate the properties with data
	 * from the boards table.
	 *
	 * @param ?int $id The ID number of a board, or null for current board.
	 *    Default: null.
	 * @param array $props Properties to set for this board. Only used when $id
	 *    is not null.
	 * @return object|null An instance of this class, or null on error.
	 */
	public static function init(?int $id = null, array $props = []): ?object
	{
		// This should already have been set, but just in case...
		if (!isset(self::$board_id)) {
			self::$board_id = (int) ($_REQUEST['board'] ?? 0);
		}

		if (!isset(self::$loaded[$id])) {
			new self($id, $props);
		} else {
			self::$loaded[$id]->set($props);
		}

		return self::$loaded[$id] ?? null;
	}

	/**
	 * Mark one or more boards as read.
	 */
	public static function markRead(): void
	{
		// No Guests allowed!
		User::$me->kickIfGuest();

		User::$me->checkSession('get');

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all') {
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
				self::markBoardsRead($boards, isset($_REQUEST['unread']));
			}

			$_SESSION['id_msg_last_visit'] = Config::$modSettings['maxMsgID'];

			if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false) {
				Utils::redirectexit('action=unread');
			}

			if (isset($_SESSION['topicseen_cache'])) {
				$_SESSION['topicseen_cache'] = [];
			}

			Utils::redirectexit();
		} elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'unreadreplies') {
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
				$markRead[] = [Config::$modSettings['maxMsgID'], User::$me->id, $id_topic, ($logged_topics[Topic::$topic_id] ?? 0)];
			}

			Db::$db->insert(
				'replace',
				'{db_prefix}log_topics',
				['id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'],
				$markRead,
				['id_member', 'id_topic'],
			);

			if (isset($_SESSION['topicseen_cache'])) {
				$_SESSION['topicseen_cache'] = [];
			}

			Utils::redirectexit('action=unreadreplies');
		}
		// Special case: mark a topic unread!
		elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'topic') {
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
			// Marking read from first page?  That's the whole topic.
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
				['id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int', 'unwatched' => 'int'],
				[$earlyMsg, User::$me->id, Topic::$topic_id, $topicinfo['unwatched']],
				['id_member', 'id_topic'],
			);

			Utils::redirectexit('board=' . self::$info->id . '.0');
		} else {
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

			if (!empty(self::$info->id)) {
				$boards[] = (int) self::$info->id;
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

			self::markBoardsRead($boards, isset($_REQUEST['unread']));

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
						['id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'],
						$logBoardInserts,
						['id_member', 'id_board'],
					);
				}
				Db::$db->free_result($result);

				if (empty(self::$info->id)) {
					Utils::redirectexit();
				} else {
					Utils::redirectexit('board=' . self::$info->id . '.0');
				}
			} else {
				if (empty(self::$info->parent)) {
					Utils::redirectexit();
				} else {
					Utils::redirectexit('board=' . self::$info->parent . '.0');
				}
			}
		}
	}

	/**
	 * Mark a board or multiple boards read.
	 *
	 * @param int|array $boards The ID of a single board or an array of boards
	 * @param bool $unread Whether we're marking them as unread
	 */
	public static function markBoardsRead(int|array $boards, bool $unread = false): void
	{
		// Force $boards to be an array.
		if (!is_array($boards)) {
			$boards = [$boards];
		} else {
			$boards = array_unique($boards);
		}

		// No boards, nothing to mark as read.
		if (empty($boards)) {
			return;
		}

		// Allow the user to mark a board as unread.
		if ($unread) {
			// Clear out all the places where this lovely info is stored.
			// @todo Maybe not log_mark_read?
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_mark_read
				WHERE id_board IN ({array_int:board_list})
					AND id_member = {int:current_member}',
				[
					'current_member' => User::$me->id,
					'board_list' => $boards,
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_boards
				WHERE id_board IN ({array_int:board_list})
					AND id_member = {int:current_member}',
				[
					'current_member' => User::$me->id,
					'board_list' => $boards,
				],
			);
		}
		// Otherwise mark the board as read.
		else {
			$markRead = [];

			foreach ($boards as $board) {
				$markRead[] = [Config::$modSettings['maxMsgID'], User::$me->id, $board];
			}

			// Update log_mark_read and log_boards.
			Db::$db->insert(
				'replace',
				'{db_prefix}log_mark_read',
				['id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'],
				$markRead,
				['id_board', 'id_member'],
			);

			Db::$db->insert(
				'replace',
				'{db_prefix}log_boards',
				['id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'],
				$markRead,
				['id_board', 'id_member'],
			);
		}

		// Get rid of useless log_topics data, because log_mark_read is better for it - even if marking unread - I think so...
		// @todo look at this...
		// The call to markBoardsRead() in Display() used to be simply
		// marking log_boards (the previous query only)
		$result = Db::$db->query(
			'',
			'SELECT MIN(id_topic)
			FROM {db_prefix}log_topics
			WHERE id_member = {int:current_member}',
			[
				'current_member' => User::$me->id,
			],
		);
		list($lowest_topic) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		if (empty($lowest_topic)) {
			return;
		}

		// @todo SLOW This query seems to eat it sometimes.
		$topics = [];
		$result = Db::$db->query(
			'',
			'SELECT lt.id_topic
			FROM {db_prefix}log_topics AS lt
				INNER JOIN {db_prefix}topics AS t /*!40000 USE INDEX (PRIMARY) */ ON (t.id_topic = lt.id_topic
					AND t.id_board IN ({array_int:board_list}))
			WHERE lt.id_member = {int:current_member}
				AND lt.id_topic >= {int:lowest_topic}
				AND lt.unwatched != 1',
			[
				'current_member' => User::$me->id,
				'board_list' => $boards,
				'lowest_topic' => $lowest_topic,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$topics[] = $row['id_topic'];
		}
		Db::$db->free_result($result);

		if (!empty($topics)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_topics
				WHERE id_member = {int:current_member}
					AND id_topic IN ({array_int:topic_list})',
				[
					'current_member' => User::$me->id,
					'topic_list' => $topics,
				],
			);
		}
	}

	/**
	 * Get the id_member associated with the specified message.
	 *
	 * @todo Move this? It's not really related to boards.
	 *
	 * @param int $messageID The ID of the message
	 * @return int The ID of the member associated with that post
	 */
	public static function getMsgMemberID(int $messageID): int
	{
		// Find the topic and make sure the member still exists.
		$result = Db::$db->query(
			'',
			'SELECT COALESCE(mem.id_member, 0)
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg = {int:selected_message}
			LIMIT 1',
			[
				'selected_message' => (int) $messageID,
			],
		);

		if (Db::$db->num_rows($result) > 0) {
			list($memberID) = Db::$db->fetch_row($result);
		}
		// The message doesn't even exist.
		else {
			$memberID = 0;
		}
		Db::$db->free_result($result);

		return (int) $memberID;
	}

	/**
	 * Modify the settings and position of a board.
	 * Used by ManageBoards.php to change the settings of a board.
	 *
	 * @param int $board_id The ID of the board
	 * @param array &$boardOptions An array of options related to the board
	 */
	public static function modify(int $board_id, array &$boardOptions): void
	{
		// Load and organize all boards and categories.
		Category::getTree();

		// Make sure given boards and categories exist.
		if (
			!isset(self::$loaded[$board_id])
			|| (
				isset($boardOptions['target_board'])
				&& !isset(self::$loaded[$boardOptions['target_board']])
			)
			|| (
				isset($boardOptions['target_category'])
				&& !isset(Category::$loaded[$boardOptions['target_category']])
			)
		) {
			ErrorHandler::fatalLang('no_board');
		}

		IntegrationHook::call('integrate_pre_modify_board', [$board_id, &$boardOptions]);

		$board = self::$loaded[$board_id];

		// In case the board has to be moved.
		if (isset($boardOptions['move_to'])) {
			$moved_boards = $board->move($boardOptions['move_to'], $boardOptions['target_category'] ?? null, $boardOptions['target_board'] ?? null, !empty($boardOptions['move_first_child']), false);
		}

		// Set moderators of this board.
		if (isset($boardOptions['moderators']) || isset($boardOptions['moderator_string']) || isset($boardOptions['moderator_groups']) || isset($boardOptions['moderator_group_string'])) {
			// Validate and get the IDs of the new moderators.
			// $boardOptions['moderator_string'] is only set if the admin has JavaScript disabled.
			if (isset($boardOptions['moderator_string']) && trim($boardOptions['moderator_string']) != '') {
				if (empty($boardOptions['moderators'])) {
					$boardOptions['moderators'] = [];
				}

				// Divvy out the usernames, remove extra space.
				$moderator_string = strtr(Utils::htmlspecialchars($boardOptions['moderator_string'], ENT_QUOTES), ['&quot;' => '"']);

				preg_match_all('~"([^"]+)"~', $moderator_string, $matches);

				$moderators = array_filter(array_map('trim', array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)))), 'strlen');

				// Find all the id_member's for the member_name's in the list.
				if (!empty($moderators)) {
					foreach (User::load($moderators, User::LOAD_BY_NAME, 'minimal') as $moderator) {
						$boardOptions['moderators'][] = $moderator->id;
					}
				}
			}

			// Validate and get the IDs of the new moderator groups.
			// $boardOptions['moderator_group_string'] is only set if the admin has JavaScript disabled.
			if (isset($boardOptions['moderator_group_string']) && trim($boardOptions['moderator_group_string']) != '') {
				if (empty($boardOptions['moderator_groups'])) {
					$boardOptions['moderator_groups'] = [];
				}

				// Divvy out the group names, remove extra space.
				$moderator_group_string = strtr(Utils::htmlspecialchars($boardOptions['moderator_group_string'], ENT_QUOTES), ['&quot;' => '"']);

				preg_match_all('~"([^"]+)"~', $moderator_group_string, $matches);

				$moderator_groups = array_filter(array_map('trim', array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_group_string)))), 'strlen');

				// Find all the id_group's for all the group names in the list
				// But skip any invalid ones (invisible/post groups/Administrator/Moderator)
				if (!empty($moderator_groups)) {
					$query_customizations = [
						'where' => [
							'group_name IN ({array_string:moderator_group_list})',
							'hidden = {int:visible}',
							'min_posts = {int:negative_one}',
							'id_group NOT IN ({array_int:invalid_groups})',
						],
						'params' => [
							'visible' => Group::VISIBLE,
							'negative_one' => -1,
							'invalid_groups' => [Group::ADMIN, Group::MOD],
							'moderator_group_list' => $moderator_groups,
						],
						'limit' => count($moderator_groups),
					];

					foreach (Group::load([], $query_customizations) as $group) {
						$boardOptions['moderator_groups'][] = $group->id;
					}
				}
			}

			if (isset($boardOptions['moderators'])) {
				if (!is_array($boardOptions['moderators'])) {
					$boardOptions['moderators'] = array_filter(explode(',', $boardOptions['moderators']), 'strlen');
				}

				$boardOptions['moderators'] = array_unique(array_map('intval', $boardOptions['moderators']));
			}

			if (isset($boardOptions['moderator_groups'])) {
				if (!is_array($boardOptions['moderator_groups'])) {
					$boardOptions['moderator_groups'] = array_filter(explode(',', $boardOptions['moderator_groups']), 'strlen');
				}

				$boardOptions['moderator_groups'] = array_unique(array_map('intval', $boardOptions['moderator_groups']));
			}
		}

		// String properties.
		$board->name = (string) ($boardOptions['board_name'] ?? $board->name ?? '');
		$board->description = (string) ($boardOptions['board_description'] ?? $board->description ?? '');
		$board->redirect = (string) ($boardOptions['redirect'] ?? $board->redirect ?? '');

		// Integer properties.
		$board->num_posts = (int) ($boardOptions['num_posts'] ?? $board->num_posts ?? 0);
		$board->theme = (int) ($boardOptions['board_theme'] ?? $board->theme ?? 0);
		$board->profile = (int) ($boardOptions['profile'] ?? $board->profile ?? 1);

		// Boolean properties.
		$board->count_posts = !empty($boardOptions['posts_count'] ?? $board->count_posts ?? true);
		$board->override_theme = !empty($boardOptions['override_theme'] ?? $board->override_theme ?? false);

		// Array properties.
		$board->moderators = $boardOptions['moderators'] ?? $board->moderators;
		$board->moderator_groups = $boardOptions['moderator_groups'] ?? $board->moderator_groups;
		$board->member_groups = $boardOptions['access_groups'] ?? $board->member_groups;
		$board->deny_groups = $boardOptions['deny_groups'] ?? $board->deny_groups;

		// We're ready to save the changes now.
		$board->save($boardOptions);

		// If we moved any boards, save their changes too.
		if (!empty($moved_boards)) {
			foreach (array_diff($moved_boards, [$board->id]) as $moved) {
				self::$loaded[$moved]->save();
			}
		}

		// Log the changes unless told otherwise.
		if (empty($boardOptions['dont_log'])) {
			Logging::logAction('edit_board', ['board' => $board->id], 'admin');
		}
	}

	/**
	 * Create a new board and set its properties and position.
	 *
	 * Allows (almost) the same options as the modifyBoard() function.
	 * With the option inherit_permissions set, the parent board permissions
	 * will be inherited.
	 *
	 * @param array $boardOptions An array of information for the new board
	 * @return int The ID of the new board
	 */
	public static function create(array $boardOptions): int
	{
		// Trigger an error if one of the required values is not set.
		if (!isset($boardOptions['board_name']) || trim($boardOptions['board_name']) == '' || !isset($boardOptions['move_to']) || !isset($boardOptions['target_category'])) {
			Lang::load('Errors');
			trigger_error(Lang::$txt['create_board_missing_options'], E_USER_ERROR);
		}

		if (in_array($boardOptions['move_to'], ['child', 'before', 'after']) && !isset($boardOptions['target_board'])) {
			Lang::load('Errors');
			trigger_error(Lang::$txt['move_board_no_target'], E_USER_ERROR);
		}

		// Set every optional value to its default value.
		$boardOptions += [
			'posts_count' => true,
			'override_theme' => false,
			'board_theme' => 0,
			'access_groups' => [],
			'board_description' => '',
			'profile' => 1,
			'moderators' => '',
			'inherit_permissions' => true,
			'dont_log' => true,
		];

		// This used to be done via a direct query, which is why these look like
		// arrays that would be passed to our database API. We keep them this
		// way now merely in order to maintain the signature of the hook.
		$board_columns = [
			'id_cat' => 'int',
			'name' => 'string-255',
			'description' => 'string',
			'board_order' => 'int',
			'member_groups' => 'string',
			'redirect' => 'string',
		];

		$board_parameters = [
			$boardOptions['target_category'],
			$boardOptions['board_name'],
			'',
			0,
			'',
			'',
		];

		IntegrationHook::call('integrate_create_board', [&$boardOptions, &$board_columns, &$board_parameters]);

		// Make a new instance and save it.
		$board = new self(0, array_combine(array_keys($board_columns), $board_parameters));
		$board->save();

		// Uh-oh...
		if (empty($board->id)) {
			return 0;
		}

		// Do we want the parent permissions to be inherited?
		if ($boardOptions['inherit_permissions'] && !empty($board->parent)) {
			self::load($board->parent);
			$boardOptions['profile'] = self::$loaded[$board->parent]->profile;
			unset($boardOptions['inherit_permissions']);
		}

		// Change the board according to the given specifications.
		self::modify($board->id, $boardOptions);

		// Created it.
		Logging::logAction('add_board', ['board' => $board->id], 'admin');

		// Here you are, a new board, ready to be spammed.
		return $board->id;
	}

	/**
	 * Remove one or more boards.
	 * Allows to move the children of the board before deleting it
	 * if moveChildrenTo is set to null, the child boards will be deleted.
	 * Deletes:
	 *   - all topics that are on the given boards;
	 *   - all information that's associated with the given boards;
	 * updates the statistics to reflect the new situation.
	 *
	 * @param array $boards_to_remove The boards to remove
	 * @param int $moveChildrenTo The ID of the board to move the child boards to (null to remove the child boards, 0 to make them a top-level board)
	 */
	public static function delete(array $boards_to_remove, ?int $moveChildrenTo = null): void
	{
		// No boards to delete? Return!
		if (empty($boards_to_remove)) {
			return;
		}

		Category::getTree();

		IntegrationHook::call('integrate_delete_board', [$boards_to_remove, &$moveChildrenTo]);

		// If $moveChildrenTo is set to null, include the children in the removal.
		if ($moveChildrenTo === null) {
			// Get a list of the child boards that will also be removed.
			$child_boards_to_remove = [];

			foreach ($boards_to_remove as $board_to_remove) {
				Category::recursiveBoards($child_boards_to_remove, self::$loaded[$board_to_remove]);
			}

			// Merge the children with their parents.
			if (!empty($child_boards_to_remove)) {
				$boards_to_remove = array_unique(array_merge($boards_to_remove, $child_boards_to_remove));
			}
		}
		// Move the children to a safe home.
		else {
			foreach ($boards_to_remove as $id_board) {
				// @todo Separate category?
				if ($moveChildrenTo === 0) {
					self::fixChildren($id_board, 0, 0);
				} else {
					self::fixChildren($id_board, self::$loaded[$moveChildrenTo]->child_level + 1, $moveChildrenTo);
				}
			}
		}

		// Delete ALL topics in the selected boards (done first so topics can't be marooned.)
		$topics = [];

		$request = Db::$db->query(
			'',
			'SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$topics[] = $row['id_topic'];
		}
		Db::$db->free_result($request);

		Topic::remove($topics, false);

		// Delete the board's logs.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_mark_read
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_boards
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Delete this board's moderators.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}moderators
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Delete this board's moderator groups.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}moderator_groups
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Delete any extra events in the calendar.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}calendar
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Delete any message icons that only appear on these boards.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}message_icons
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Delete the boards.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}boards
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Delete permissions
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}board_permissions_view
			WHERE id_board IN ({array_int:boards_to_remove})',
			[
				'boards_to_remove' => $boards_to_remove,
			],
		);

		// Latest message/topic might not be there anymore.
		Logging::updateStats('message');
		Logging::updateStats('topic');
		Config::updateModSettings([
			'calendar_updated' => time(),
		]);

		// Plus reset the cache to stop people getting odd results.
		Config::updateModSettings(['settings_updated' => time()]);

		// Clean the cache as well.
		CacheApi::clean('data');

		// Let's do some serious logging.
		foreach ($boards_to_remove as $id_board) {
			Logging::logAction('delete_board', ['boardname' => self::$loaded[$id_board]->name], 'admin');
		}

		self::reorder();
	}

	/**
	 * Put all boards in the right order and sorts the records of the boards table.
	 * Used by Board::modify(), Board::delete(), Category::modify(), and Category::delete()
	 */
	public static function reorder(): void
	{
		Category::getTree();

		// Set the board order for each category.
		$board_order = 0;

		foreach (Category::$loaded as $cat_id => $dummy) {
			foreach (Category::$boardList[$cat_id] as $board_id) {
				if (self::$loaded[$board_id]->order != ++$board_order) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET board_order = {int:new_order}
						WHERE id_board = {int:selected_board}',
						[
							'new_order' => $board_order,
							'selected_board' => $board_id,
						],
					);
				}
			}
		}

		// Empty the board order cache
		CacheApi::put('board_order', null, -3600);
	}

	/**
	 * Fixes the children of a board by setting their child_levels to new values.
	 * Used when a board is deleted or moved, to affect its children.
	 *
	 * @param int $parent The ID of the parent board
	 * @param int $newLevel The new child level for each of the child boards
	 * @param int $newParent The ID of the new parent board
	 */
	public static function fixChildren(int $parent, int $newLevel, int $newParent): void
	{
		// Grab all children of $parent...
		$children = [];

		$result = Db::$db->query(
			'',
			'SELECT id_board
			FROM {db_prefix}boards
			WHERE id_parent = {int:parent_board}',
			[
				'parent_board' => $parent,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			$children[] = $row['id_board'];
		}
		Db::$db->free_result($result);

		// ...and set it to a new parent and child_level.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET id_parent = {int:new_parent}, child_level = {int:new_child_level}
			WHERE id_parent = {int:parent_board}',
			[
				'new_parent' => $newParent,
				'new_child_level' => $newLevel,
				'parent_board' => $parent,
			],
		);

		// Recursively fix the children of the children.
		foreach ($children as $child) {
			self::fixChildren($child, $newLevel + 1, $child);
		}
	}

	/**
	 * Takes a board array and sorts it
	 *
	 * @param array &$boards The boards
	 */
	public static function sort(array &$boards): void
	{
		$tree = Category::getTreeOrder();

		$ordered = [];

		foreach ($tree['boards'] as $board) {
			if (!empty($boards[$board])) {
				$ordered[$board] = $boards[$board];

				if (is_array($ordered[$board]) && !empty($ordered[$board]['children'])) {
					self::sort($ordered[$board]['children']);
				} elseif (is_object($ordered[$board]) && !empty($ordered[$board]->children)) {
					Board::sort($ordered[$board]->children);
				}
			}
		}

		$boards = $ordered;
	}

	/**
	 * Returns the given board's moderators, with their names and links
	 *
	 * @param array $boards The boards to get moderators of
	 * @return array An array containing information about the moderators of each board
	 */
	public static function getModerators(array $boards): array
	{
		if (empty($boards)) {
			return [];
		}

		$moderators = [];

		$request = Db::$db->query(
			'',
			'SELECT mem.id_member, mem.real_name, mo.id_board
			FROM {db_prefix}moderators AS mo
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mo.id_member)
			WHERE mo.id_board IN ({array_int:boards})',
			[
				'boards' => $boards,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['id_board'] = (int) $row['id_board'];
			$row['id_member'] = (int) $row['id_member'];

			if (empty($moderators[$row['id_board']])) {
				$moderators[$row['id_board']] = [];
			}

			$moderators[$row['id_board']][$row['id_member']] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			];
		}
		Db::$db->free_result($request);

		// We might as well update the data in any loaded boards.
		foreach (self::$loaded as $board) {
			if (isset($moderators[$board->id])) {
				$board->moderators = $moderators[$board->id];
			}
		}

		return $moderators;
	}

	/**
	 * Returns board's moderator groups with their names and link
	 *
	 * @param array $boards The boards to get moderator groups of
	 * @return array An array containing information about the groups assigned to moderate each board
	 */
	public static function getModeratorGroups(array $boards): array
	{
		if (empty($boards)) {
			return [];
		}

		$groups = [];

		$request = Db::$db->query(
			'',
			'SELECT mg.id_group, mg.group_name, bg.id_board
			FROM {db_prefix}moderator_groups AS bg
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bg.id_group)
			WHERE bg.id_board IN ({array_int:boards})',
			[
				'boards' => $boards,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['id_board'] = (int) $row['id_board'];
			$row['id_group'] = (int) $row['id_group'];

			if (empty($groups[$row['id_board']])) {
				$groups[$row['id_board']] = [];
			}

			$groups[$row['id_board']][$row['id_group']] = [
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'href' => Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_group'],
				'link' => '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '">' . $row['group_name'] . '</a>',
			];
		}

		// We might as well update the data in any loaded boards.
		foreach (self::$loaded as $board) {
			if (isset($groups[$board->id])) {
				$board->moderator_groups = $groups[$board->id];
			}
		}

		return $groups;
	}

	/**
	 * Returns whether the child board id is a child of the parent (recursive).
	 *
	 * @param int $child The ID of the child board.
	 * @param int $parent The ID of a parent board.
	 * @return bool Whether the specified child board is a child of the
	 *    specified parent board.
	 */
	public static function isChildOf($child, $parent): bool
	{
		if (empty(self::$loaded[$child]->parent)) {
			return false;
		}

		if (self::$loaded[$child]->parent == $parent) {
			return true;
		}

		return self::isChildOf(self::$loaded[$child]->parent, $parent);
	}

	/**
	 * Get all parent boards (requires first parent as parameter)
	 * It finds all the parents of id_parent, and that board itself.
	 * Additionally, it detects the moderators of said boards.
	 *
	 * @param int $id_parent The ID of the parent board
	 * @return array An array of information about the boards found.
	 */
	public static function getParents(int $id_parent): array
	{
		// First check if we have this cached already.
		if (($boards = CacheApi::get('board_parents-' . $id_parent, 480)) === null) {
			$boards = [];
			$original_parent = $id_parent;

			// Loop while the parent is non-zero.
			while ($id_parent != 0) {
				$selects = [
					'b.id_parent', 'b.name', 'b.id_board', 'b.child_level',
					'b.member_groups', 'b.deny_member_groups',
				];
				$params = ['board_parent' => $id_parent];
				$joins = [];
				$where = ['b.id_board = {int:board_parent}'];
				$order = [];

				foreach (self::queryData($selects, $params, $joins, $where, $order) as $row) {
					if (!isset($boards[$row['id_board']])) {
						$id_parent = $row['id_parent'];
						$boards[$row['id_board']] = [
							'url' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
							'name' => $row['name'],
							'child_level' => $row['child_level'],
							'parent' => $row['id_parent'],
							'groups' => explode(',', $row['member_groups']),
							'deny_groups' => explode(',', $row['deny_member_groups']),
						];
					}
				}
			}

			CacheApi::put('board_parents-' . $original_parent, $boards, 480);
		}

		$loaded_boards = [];

		foreach ($boards as $id => $props) {
			$loaded_boards[] = self::init($id, $props);
		}

		return $loaded_boards;
	}

	/**
	 * Generator that runs queries about board data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)'
	 *    Note that 'FROM {db_prefix}boards AS b' is always part of the query.
	 * @param array $where Zero or more conditions for the WHERE clause.
	 *    Conditions will be placed in parentheses and concatenated with AND.
	 *    If this is left empty, no WHERE clause will be used.
	 * @param array $order Zero or more conditions for the ORDER BY clause.
	 *    If this is left empty, no ORDER BY clause will be used.
	 * @param array $group Zero or more conditions for the GROUP BY clause.
	 *    If this is left empty, no GROUP BY clause will be used.
	 * @param int $limit Maximum number of results to retrieve.
	 *    If this is left empty, all results will be retrieved.
	 *
	 * @return Generator<array> Iterating over the result gives database rows.
	 */
	public static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int $limit = 0)
	{
		// If we only want some child boards, use a CTE query for improved performance.
		if (!empty($params['id_parent']) && in_array('b.id_parent != 0', $where) && Db::$db->cte_support()) {
			// Ensure we include all the necessary fields for the CTE query.
			preg_match_all('/\bb\.(\w+)/', implode(', ', $selects), $matches);

			$cte_fields = array_unique(array_merge(
				$matches[1],
				[
					'child_level',
					'id_board',
					'name',
					'description',
					'redirect',
					'num_posts',
					'num_topics',
					'unapproved_posts',
					'unapproved_topics',
					'id_parent',
					'id_msg_updated',
					'id_cat',
					'id_last_msg',
					'board_order',
				],
			));

			$cte_selects = array_map(
				function ($field) {
					return 'b.' . $field;
				},
				$cte_fields,
			);

			$cte_where1 = ['b.id_board = {int:id_parent}'];
			$cte_where2 = [];

			if (in_array('{query_see_board}', $where)) {
				array_unshift($cte_where1, '{query_see_board}');
				$cte_where2[] = '{query_see_board}';
				$where = array_diff($where, ['{query_see_board}']);
			}

			if (in_array('b.child_level BETWEEN {int:child_level} AND {int:max_child_level}', $where)) {
				$cte_where2[] = 'b.child_level BETWEEN {int:child_level} AND {int:max_child_level}';
				$where = array_diff($where, ['b.child_level BETWEEN {int:child_level} AND {int:max_child_level}']);
			}

			$request = Db::$db->query(
				'',
				'WITH RECURSIVE
					boards_cte (' . implode(', ', $cte_fields) . ')
				AS
				(
					SELECT ' . implode(', ', $cte_selects) . '
					FROM {db_prefix}boards AS b
					WHERE ' . implode(' AND ', $cte_where1) . '
						UNION ALL
					SELECT ' . implode(', ', $cte_selects) . '
					FROM {db_prefix}boards AS b
						JOIN boards_cte AS bc ON (b.id_parent = bc.id_board)
					WHERE ' . implode(' AND ', $cte_where2) . '
				)
				SELECT
					' . (!empty($selects) ? implode(', ', $selects) : '') . '
				FROM boards_cte AS b' . (empty($joins) ? '' : '
					' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
				WHERE (' . implode(') AND (', $where) . ')') . (empty($order) ? '' : '
				ORDER BY ' . implode(', ', $order)) . ($limit > 0 ? '
				LIMIT ' . $limit : ''),
				$params,
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT
					' . implode(', ', $selects) . '
				FROM {db_prefix}boards AS b' . (empty($joins) ? '' : '
					' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
				WHERE (' . implode(') AND (', $where) . ')') . (empty($order) ? '' : '
				ORDER BY ' . implode(', ', $order)) . ($limit > 0 ? '
				LIMIT ' . $limit : ''),
				$params,
			);
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			yield $row;
		}
		Db::$db->free_result($request);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via Board::load().
	 *
	 * If $id is null, loads the current board:
	 *  - Sets up the Board::$info object for current board information.
	 *  - If cache is enabled, Board::$info is stored in cache.
	 *  - Redirects to appropriate post if only a message ID was requested.
	 *  - Is only used when inside a topic or board.
	 *  - Determines the local moderators for the board and calls
	 *    User::setModerators.
	 *  - Prevents access if user is not in proper group nor a local moderator
	 *    of the board.
	 *
	 * If $id is an integer, creates an instance for that ID, sets any supplied
	 * properties in $props, and adds the instance to the Board::$loaded array.
	 *
	 * If $id is an integer and $props is empty, a query will be performed to
	 * populate the properties with data from the boards table.
	 *
	 * @param ?int $id The ID number of a board, or null for current board.
	 *    Default: null.
	 * @param array $props Properties to set for this board. Only used when $id
	 *    is not null.
	 */
	protected function __construct(?int $id = null, array $props = [])
	{
		// This should already have been set, but just in case...
		if (!isset(self::$board_id)) {
			self::$board_id = (int) ($_REQUEST['board'] ?? 0);
		}

		// No ID given, so load current board.
		if (!isset($id) || (!empty($id) && $id === self::$board_id)) {
			// Only do this once.
			if (!isset(self::$info)) {
				// Assume they are not a moderator.
				if (isset(User::$me)) {
					User::$me->is_mod = false;
				}

				// Start the linktree off empty..
				Utils::$context['linktree'] = [];

				// Have they by chance specified a message id but nothing else?
				if (empty($_REQUEST['action']) && empty(Topic::$topic_id) && empty(self::$board_id) && !empty($_REQUEST['msg'])) {
					$this->redirectFromMsg();
				}

				// Load this board only if it is specified.
				if (empty(self::$board_id) && empty(Topic::$topic_id)) {
					return;
				}

				// Load this board's info into the object properties.
				$this->loadBoardInfo();

				if (empty($this->id)) {
					return;
				}

				// At this point, we know that self::$board_id won't change.
				self::$loaded[self::$board_id] = $this;
				self::$info = $this;

				if (!empty(Topic::$topic_id)) {
					$_GET['board'] = (int) self::$board_id;
				}

				if (!empty(self::$board_id)) {
					User::setModerators();
					$this->checkAccess();
					$this->buildLinkTree();
				}

				// Set the template contextual information.
				Utils::$context['current_topic'] = Topic::$topic_id;
				Utils::$context['current_board'] = self::$board_id;

				// No posting in redirection boards!
				if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'post' && !empty($this->redirect)) {
					$this->error = 'post_in_redirect';
				}

				$this->blockOnError();
			}
		} else {
			// No props provided, so get the standard ones.
			if ($id > 0 && empty($props)) {
				$request = Db::$db->query(
					'',
					'SELECT *
					FROM {db_prefix}boards
					WHERE id_board = {int:id}
					LIMIT 1',
					[
						'id' => $id,
					],
				);
				$props = Db::$db->fetch_all($request);
				Db::$db->free_result($request);
			}

			$this->id = $id;
			$this->set($props);
			self::$loaded[$this->id] = $this;
		}

		// Add this board as a child of its parent.
		if (!empty($this->parent)) {
			self::init($this->parent)->children[$this->id] = $this;
		}

		// Plug this board into its category.
		if (!empty($this->cat) && $this->child_level == 0) {
			$this->cat->children[$this->id] = $this;
		}
	}

	/**
	 * Handles redirecting 'index.php?msg=123' links to the canonical URL.
	 *
	 * @todo Should this be moved somewhere else? It's not really board-related.
	 */
	protected function redirectFromMsg(): void
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if ((Topic::$topic_id = CacheApi::get('msg_topic-' . $_REQUEST['msg'], 120)) === null) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				[
					'id_msg' => $_REQUEST['msg'],
				],
			);

			// So did it find anything?
			if (Db::$db->num_rows($request)) {
				list(Topic::$topic_id) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// Save save save.
				CacheApi::put('msg_topic-' . $_REQUEST['msg'], Topic::$topic_id, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty(Topic::$topic_id)) {
			$redirect_url = 'topic=' . Topic::$topic_id . '.msg' . $_REQUEST['msg'];

			if (($other_get_params = array_diff(array_keys($_GET), ['msg'])) !== []) {
				$redirect_url .= ';' . implode(';', $other_get_params);
			}

			$redirect_url .= '#msg' . $_REQUEST['msg'];

			Utils::redirectexit($redirect_url);
		} else {
			User::$me->loadPermissions();
			Theme::load();
			ErrorHandler::fatalLang('topic_gone', false);
		}
	}

	/**
	 * Loads information about the current board.
	 *
	 * The loaded info is stored in the Board::$info instance of this class.
	 */
	protected function loadBoardInfo(): void
	{
		// First, try the cache.
		if (!empty(CacheApi::$enable) && (empty(Topic::$topic_id) || CacheApi::$enable >= 3)) {
			if (!empty(Topic::$topic_id)) {
				$temp = CacheApi::get('topic_board-' . Topic::$topic_id, 120);
			} else {
				$temp = CacheApi::get('board-' . self::$board_id, 120);
			}

			if (!empty($temp)) {
				foreach ($temp as $key => $value) {
					if ($key === 'cat') {
						$this->{$key} = Category::init($value['id'], $value);
					} else {
						$this->{$key} = $value;
					}
				}

				self::$board_id = $this->id;
			}
		}

		// Cache gave us nothing, so query the database.
		if (empty($this->id)) {
			// Set up all the query components.
			$selects = [
				'b.id_board', 'b.id_cat', 'b.name', 'b.description',
				'b.child_level', 'b.id_parent', 'b.board_order', 'b.redirect',
				'b.member_groups', 'b.deny_member_groups', 'b.id_profile',
				'b.num_topics', 'b.num_posts', 'b.count_posts', 'b.id_last_msg',
				'b.id_msg_updated', 'b.id_theme', 'b.override_theme',
				'b.unapproved_posts', 'b.unapproved_topics', 'c.name AS cat_name',
				'COALESCE(mg.id_group, 0) AS id_moderator_group', 'mg.group_name',
				'COALESCE(mem.id_member, 0) AS id_moderator', 'mem.real_name',
			];

			$params = [
				'board_link' => self::$board_id,
			];

			$joins = [
				'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)',
				'LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = {raw:board_link})',
				'LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = modgs.id_group)',
				'LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})',
				'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)',
			];

			$where = ['b.id_board = {raw:board_link}'];
			$order = [];

			if (!empty(Topic::$topic_id)) {
				$selects[] = 't.approved';
				$selects[] = 't.id_member_started';

				$params['current_topic'] = Topic::$topic_id;
				$params['board_link'] = 't.id_board';

				array_unshift($joins, 'INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})');
			}

			// Do any mods want to add some custom stuff to the query?
			IntegrationHook::call('integrate_load_board', [&$selects, &$params, &$joins, &$where, &$order]);

			$selects = array_unique($selects);
			$params = array_unique($params);
			$joins = array_unique($joins);
			$where = array_unique($where);
			$order = array_unique($order);

			// Run the query and iterate over the returned rows.
			foreach (self::queryData($selects, $params, $joins, $where, $order) as $row) {
				$row['id_board'] = (int) $row['id_board'];

				// The query as currently constructed will return multiple rows if
				// there are multiple moderators and/or moderator groups. To avoid
				// redundancy, we only set the rest of the data the first time.
				if (!isset($this->id)) {
					// Set the current board.
					if (!empty($row['id_board'])) {
						self::$board_id = $row['id_board'];
					}

					$props = [
						'id' => $row['id_board'],
						'moderators' => [],
						'moderator_groups' => [],
						'cat' => Category::init($row['id_cat'], ['name' => $row['cat_name']]),
						'name' => $row['name'],
						'description' => $row['description'],
						'num_topics' => (int) $row['num_topics'],
						'unapproved_topics' => (int) $row['unapproved_topics'],
						'unapproved_posts' => (int) $row['unapproved_posts'],
						'unapproved_user_topics' => 0,
						'parent_boards' => self::getParents($row['id_parent']),
						'parent' => (int) $row['id_parent'],
						'child_level' => (int) $row['child_level'],
						'theme' => (int) $row['id_theme'],
						'override_theme' => !empty($row['override_theme']),
						'profile' => (int) $row['id_profile'],
						'redirect' => $row['redirect'],
						'recycle' => !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) && Config::$modSettings['recycle_board'] == self::$board_id,
						'count_posts' => empty($row['count_posts']),
						'cur_topic_approved' => empty(Topic::$topic_id) || $row['approved'],
						'cur_topic_starter' => empty(Topic::$topic_id) ? 0 : $row['id_member_started'],

						// Load the membergroups allowed, and check permissions.
						'member_groups' => $row['member_groups'] == '' ? [] : array_filter(explode(',', $row['member_groups']), 'strlen'),
						'deny_groups' => $row['deny_member_groups'] == '' ? [] : array_filter(explode(',', $row['deny_member_groups']), 'strlen'),
					];

					IntegrationHook::call('integrate_board_info', [&$props, $row]);
				}

				// This row included an individual moderator.
				if (!empty($row['id_moderator'])) {
					$row['id_moderator'] = (int) $row['id_moderator'];

					$props['moderators'][$row['id_moderator']] = [
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>',
					];
				}

				// This row included a moderator group.
				if (!empty($row['id_moderator_group'])) {
					$row['id_moderator_group'] = (int) $row['id_moderator_group'];

					$props['moderator_groups'][$row['id_moderator_group']] = [
						'id' => $row['id_moderator_group'],
						'name' => $row['group_name'],
						'href' => Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'],
						'link' => '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'] . '">' . $row['group_name'] . '</a>',
					];
				}

				// Set the properties.
				$this->set($props);
			}

			if (!empty($this->id) && !empty(CacheApi::$enable) && (empty(Topic::$topic_id) || CacheApi::$enable >= 3)) {
				$to_cache = array_intersect_key((array) $this, array_flip(self::$cache_props['info']));

				if (!empty(Topic::$topic_id)) {
					CacheApi::put('topic_board-' . Topic::$topic_id, $to_cache, 120);
				}

				CacheApi::put('board-' . self::$board_id, $to_cache, 120);
			}
		}

		// No board? Then the topic is invalid, there are no moderators, etc.
		if (empty($this->id)) {
			$this->moderators = [];
			$this->moderator_groups = [];
			$this->error = 'exist';
			Topic::$topic_id = null;
			self::$board_id = 0;
		}
		// If the board only contains unapproved posts and the user isn't an
		// approver then they can't see any topics. If that is the case, do
		// an additional check to see if they have any topics waiting to be
		// approved. Note: this cannot be cached with the rest of the board
		// info since it is user-specific.
		elseif (
			!User::$me->is_guest
			&& $this->num_topics === 0
			&& $this->unapproved_topics > 0
			&& Config::$modSettings['postmod_active']
			&& !User::$me->allowedTo('approve_posts')
		) {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}topics
				WHERE id_member_started = {int:id_member}
					AND approved = {int:unapproved}
					AND id_board = {int:board}',
				[
					'id_member' => User::$me->id,
					'unapproved' => 0,
					'board' => self::$board_id,
				],
			);

			list($this->unapproved_user_topics) = Db::$db->fetch_row($request);
		}
	}

	/**
	 * Checks whether the current user can access the current board.
	 */
	protected function checkAccess(): void
	{
		if (User::$me->is_admin) {
			return;
		}

		if (count(array_intersect(User::$me->groups, $this->member_groups)) == 0) {
			$this->error = 'access';
		} elseif (!empty(Config::$modSettings['deny_boards_access']) && count(array_intersect(User::$me->groups, $this->deny_groups)) != 0) {
			$this->error = 'access';
		}
	}

	/**
	 * Builds the link tree path to the current board.
	 */
	protected function buildLinkTree(): void
	{
		// Build up the linktree.
		Utils::$context['linktree'] = array_merge(
			Utils::$context['linktree'],
			[[
				'url' => Config::$scripturl . '#c' . $this->cat['id'],
				'name' => $this->cat['name'],
			]],
			array_reverse($this->parent_boards),
			[[
				'url' => Config::$scripturl . '?board=' . $this->id . '.0',
				'name' => $this->name,
			]],
		);
	}

	/**
	 * Blocks access if an error occurred while loading the current board.
	 */
	protected function blockOnError(): void
	{
		// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
		if (!empty($this->error) && (!empty(Config::$modSettings['deny_boards_access']) || $this->error != 'access' || !User::$me->is_mod)) {
			// The permissions and theme need loading, just to make sure everything goes smoothly.
			User::$me->loadPermissions();
			Theme::load();

			$_GET['board'] = '';
			$_GET['topic'] = '';

			// The linktree should not give the game away mate!
			Utils::$context['linktree'] = [
				[
					'url' => Config::$scripturl,
					'name' => Utils::$context['forum_name_html_safe'],
				],
			];

			// If it's a prefetching agent or we're requesting an attachment.
			if ((isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') || (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'dlattach')) {
				ob_end_clean();
				Utils::sendHttpStatus(403);

				die;
			}

			if ($this->error == 'post_in_redirect') {
				// Slightly different error message here...
				ErrorHandler::fatalLang('cannot_post_redirect', false);
			} elseif (User::$me->is_guest) {
				Lang::load('Errors');
				User::$me->kickIfGuest(Lang::$txt['topic_gone']);
			} else {
				ErrorHandler::fatalLang('topic_gone', false);
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Board::exportStatic')) {
	Board::exportStatic();
}

?>