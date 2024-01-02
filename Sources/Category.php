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
 * This class loads information about categories. It also handles low-level
 * tasks for managing categories, such as creating, deleting, and modifying
 * them.
 *
 * Implements the \ArrayAccess interface to ease backward compatibility with the
 * deprecated global $cat_tree variable.
 *
 * @todo Make better use of this class in BoardIndex.php.
 */
class Category implements \ArrayAccess
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
			'modify' => 'modifyCategory',
			'create' => 'createCategory',
			'delete' => 'deleteCategories',
			'sort' => 'sortCategories',
			'getTreeOrder' => 'getTreeOrder',
			'getTree' => 'getBoardTree',
			'recursiveBoards' => 'recursiveBoards',
		],
		'prop_names' => [
			'loaded' => 'cat_tree',
			'boardList' => 'boardList',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This category's ID number.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * This category's name.
	 */
	public string $name;

	/**
	 * @var string
	 *
	 * This category's description.
	 */
	public string $description;

	/**
	 * @var bool
	 *
	 * Whether this category is collapsible.
	 */
	public bool $can_collapse;

	/**
	 * @var bool
	 *
	 * Whether the current user has collapsed this category.
	 */
	public bool $is_collapsed;

	/**
	 * @var int
	 *
	 * The positional order of this category.
	 */
	public int $order;

	/**
	 * @var int
	 *
	 * The positional order of the board immediately before this category.
	 */
	public int $last_board_order;

	/**
	 * @var array
	 *
	 * Boards that are children of this category.
	 */
	public array $children = [];

	/**
	 * @var string
	 *
	 * URL for this category.
	 */
	public string $url;

	/**
	 * @var string
	 *
	 * HTML anchor link for this category.
	 */
	public string $link;

	/**
	 * @var string
	 *
	 * HTML anchor link for this category.
	 * Used in board index table headers.
	 */
	public string $header_link;

	/**
	 * @var string
	 *
	 * A space-separated list of CSS classes.
	 */
	public string $css_class;

	/**
	 * @var bool
	 *
	 * Whether this category contains posts that the current user hasn't read.
	 */
	public bool $new;

	/**
	 * @var bool
	 *
	 * Whether to show an unread link for this category.
	 */
	public bool $show_unread;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/**
	 * @var array
	 *
	 * A list of boards grouped by category ID.
	 */
	public static array $boardList = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_cat' => 'id',
		'cat_order' => 'order',
		'href' => 'url',
		'boards' => 'children',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Holds results of Category::getTreeOrder().
	 */
	protected static array $tree_order = [
		'cats' => [],
		'boards' => [],
	];

	/**
	 * @var array
	 *
	 * Holds parsed versions of category descriptions.
	 */
	protected static array $parsed_descriptions = [];

	/****************
	 * Public methods
	 ****************/

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
				self::$parsed_descriptions = CacheApi::get('parsed_category_descriptions', 864000) ?? [];
			}

			if (!isset(self::$parsed_descriptions[$this->id])) {
				self::$parsed_descriptions[$this->id] = BBCodeParser::load()->parse($this->description, false, '', Utils::$context['description_allowed_tags']);

				CacheApi::put('parsed_category_descriptions', self::$parsed_descriptions, 864000);
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
	 * Loads categories by ID number and/or by custom query.
	 *
	 * If both arguments are empty, loads all categories.
	 *
	 * @param array|int $ids The ID numbers of zero or more categories.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return array Instances of this class for the loaded categories.
	 */
	public static function load(array|int $ids = [], array $query_customizations = []): array
	{
		$loaded = [];

		$ids = array_unique(array_map('intval', (array) $ids));

		$selects = $query_customizations['selects'] ?? ['c.*'];
		$joins = $query_customizations['joins'] ?? [];
		$where = $query_customizations['where'] ?? [];
		$order = $query_customizations['order'] ?? ['c.cat_order'];
		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? 0;
		$params = $query_customizations['params'] ?? [];

		if ($ids !== []) {
			$where[] = 'c.id_cat IN ({array_int:ids})';
			$params['ids'] = $ids;
		}

		foreach (self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$row['id_cat'] = (int) $row['id_cat'];

			if (isset(self::$loaded[$row['id_cat']])) {
				self::$loaded[$row['id_cat']]->set($row);
				$loaded[] = self::$loaded[$row['id_cat']];
			} else {
				$loaded[] = self::init($row['id_cat'], $row);
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
	 * If an instance already exists for the given ID number, then $props will
	 * simply be passed to the existing instance's set() method, and then the
	 * existing instance will be returned.
	 *
	 * If an instance does not exist for the given ID number and $props is
	 * empty, a query will be performed to populate the properties with data
	 * from the categories table.
	 *
	 * @param int $id The ID number of the category.
	 * @param array $props Array of properties to set.
	 * @return object An instance of this class.
	 */
	public static function init(int $id, array $props = []): object
	{
		if (!isset(self::$loaded[$id])) {
			new self($id, $props);
		} else {
			self::$loaded[$id]->set($props);
		}

		return self::$loaded[$id] ?? null;
	}

	/**
	 * Edit the position and properties of a category.
	 * general function to modify the settings and position of a category.
	 * used by ManageBoards.php to change the settings of a category.
	 *
	 * @param int $category_id The ID of the category
	 * @param array $catOptions An array containing data and options related to the category
	 */
	public static function modify(int $category_id, array $catOptions): void
	{
		$catUpdates = [];
		$catParameters = [];

		$cat_id = $category_id;
		IntegrationHook::call('integrate_pre_modify_category', [$cat_id, &$catOptions]);

		// Wanna change the categories position?
		if (isset($catOptions['move_after'])) {
			// Store all categories in the proper order.
			$cats = [];
			$cat_order = [];

			// Setting 'move_after' to '0' moves the category to the top.
			if ($catOptions['move_after'] == 0) {
				$cats[] = $category_id;
			}

			// Grab the categories sorted by cat_order.
			$request = Db::$db->query(
				'',
				'SELECT id_cat, cat_order
				FROM {db_prefix}categories
				ORDER BY cat_order',
				[
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['id_cat'] != $category_id) {
					$cats[] = $row['id_cat'];
				}

				if ($row['id_cat'] == $catOptions['move_after']) {
					$cats[] = $category_id;
				}

				$cat_order[$row['id_cat']] = $row['cat_order'];
			}
			Db::$db->free_result($request);

			// Set the new order for the categories.
			foreach ($cats as $index => $cat) {
				if ($index != $cat_order[$cat]) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}categories
						SET cat_order = {int:new_order}
						WHERE id_cat = {int:current_category}',
						[
							'new_order' => $index,
							'current_category' => $cat,
						],
					);
				}
			}

			// If the category order changed, so did the board order.
			Board::reorder();
		}

		if (isset($catOptions['cat_name'])) {
			$catUpdates[] = 'name = {string:cat_name}';
			$catParameters['cat_name'] = $catOptions['cat_name'];
		}

		if (isset($catOptions['cat_desc'])) {
			$catUpdates[] = 'description = {string:cat_desc}';
			$catParameters['cat_desc'] = $catOptions['cat_desc'];

			if (!empty(CacheApi::$enable)) {
				CacheApi::put('parsed_category_descriptions', null);
			}
		}

		// Can a user collapse this category or is it too important?
		if (isset($catOptions['is_collapsible'])) {
			$catUpdates[] = 'can_collapse = {int:is_collapsible}';
			$catParameters['is_collapsible'] = $catOptions['is_collapsible'] ? 1 : 0;
		}

		$cat_id = $category_id;
		IntegrationHook::call('integrate_modify_category', [$cat_id, &$catUpdates, &$catParameters]);

		// Do the updates (if any).
		if (!empty($catUpdates)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}categories
				SET
					' . implode(',
					', $catUpdates) . '
				WHERE id_cat = {int:current_category}',
				array_merge($catParameters, [
					'current_category' => $category_id,
				]),
			);

			if (empty($catOptions['dont_log'])) {
				Logging::logAction('edit_cat', ['catname' => $catOptions['cat_name'] ?? $category_id], 'admin');
			}
		}
	}

	/**
	 * Create a new category.
	 *
	 * General function to create a new category and set its position.
	 * Allows (almost) the same options as the modifyCat() function.
	 *
	 * @param array $catOptions An array of data and settings related to the new
	 *    category. Should contain 'cat_name' and can also have 'cat_desc',
	 *    'move_after' and 'is_collapsable'.
	 * @return int ID of the newly created category.
	 */
	public static function create(array $catOptions): int
	{
		// Check required values.
		if (!isset($catOptions['cat_name']) || trim($catOptions['cat_name']) == '') {
			Lang::load('Errors');
			trigger_error(Lang::$txt['create_category_no_name'], E_USER_ERROR);
		}

		// Set default values.
		if (!isset($catOptions['cat_desc'])) {
			$catOptions['cat_desc'] = '';
		}

		if (!isset($catOptions['move_after'])) {
			$catOptions['move_after'] = 0;
		}

		if (!isset($catOptions['is_collapsible'])) {
			$catOptions['is_collapsible'] = true;
		}

		// Don't log an edit right after.
		$catOptions['dont_log'] = true;

		$cat_columns = [
			'name' => 'string-48',
			'description' => 'string',
		];
		$cat_parameters = [
			$catOptions['cat_name'],
			$catOptions['cat_desc'],
		];

		IntegrationHook::call('integrate_create_category', [&$catOptions, &$cat_columns, &$cat_parameters]);

		// Add the category to the database.
		$category_id = Db::$db->insert(
			'',
			'{db_prefix}categories',
			$cat_columns,
			$cat_parameters,
			['id_cat'],
			1,
		);

		// Set the given properties to the newly created category.
		self::modify($category_id, $catOptions);

		Logging::logAction('add_cat', ['catname' => $catOptions['cat_name']], 'admin');

		// Return the database ID of the category.
		return $category_id;
	}

	/**
	 * Remove one or more categories.
	 * general function to delete one or more categories.
	 * allows to move all boards in the categories to a different category before deleting them.
	 * if moveChildrenTo is set to null, all boards inside the given categories will be deleted.
	 * deletes all information that's associated with the given categories.
	 * updates the statistics to reflect the new situation.
	 *
	 * @param array $categories The IDs of the categories to delete
	 * @param int $moveBoardsTo The ID of the category to move any boards to or null to delete the boards
	 */
	public static function delete(array $categories, ?int $moveBoardsTo = null): void
	{
		self::getTree();

		IntegrationHook::call('integrate_delete_category', [$categories, &$moveBoardsTo]);

		// With no category set to move the boards to, delete them all.
		if ($moveBoardsTo === null) {
			$boards_inside = [];

			$request = Db::$db->query(
				'',
				'SELECT id_board
				FROM {db_prefix}boards
				WHERE id_cat IN ({array_int:category_list})',
				[
					'category_list' => $categories,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards_inside[] = $row['id_board'];
			}
			Db::$db->free_result($request);

			if (!empty($boards_inside)) {
				Board::delete($boards_inside, null);
			}
		}
		// Make sure the safe category is really safe.
		elseif (in_array($moveBoardsTo, $categories)) {
			Lang::load('Errors');
			trigger_error(Lang::$txt['cannot_move_to_deleted_category'], E_USER_ERROR);
		}
		// Move the boards inside the categories to a safe category.
		else {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET id_cat = {int:new_parent_cat}
				WHERE id_cat IN ({array_int:category_list})',
				[
					'category_list' => $categories,
					'new_parent_cat' => $moveBoardsTo,
				],
			);
		}

		// Do the deletion of the category itself
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}categories
			WHERE id_cat IN ({array_int:category_list})',
			[
				'category_list' => $categories,
			],
		);

		// Log what we've done.
		foreach ($categories as $category) {
			Logging::logAction('delete_cat', ['catname' => self::$loaded[$category]['node']['name']], 'admin');
		}

		// Get all boards back into the right order.
		Board::reorder();
	}

	/**
	 * Takes a category array and sorts it
	 *
	 * @param array &$categories The categories
	 */
	public static function sort(array &$categories): void
	{
		$tree = self::getTreeOrder();

		$ordered = [];

		foreach ($tree['cats'] as $cat) {
			if (!empty($categories[$cat])) {
				$ordered[$cat] = $categories[$cat];

				if (is_array($ordered[$cat]) && !empty($ordered[$cat]['boards'])) {
					Board::sort($ordered[$cat]['boards']);
				} elseif (is_object($ordered[$cat]) && !empty($ordered[$cat]->children)) {
					Board::sort($ordered[$cat]->children);
				}
			}
		}

		$categories = $ordered;
	}

	/**
	 * Tries to load up the entire board order and category very very quickly
	 * Returns an array with two elements, cats and boards
	 *
	 * @return array An array of categories and boards
	 */
	public static function getTreeOrder(): array
	{
		if (!empty(self::$tree_order['boards'])) {
			return self::$tree_order;
		}

		if (($cached = CacheApi::get('board_order', 86400)) !== null) {
			self::$tree_order = $cached;

			return $cached;
		}

		$request = Db::$db->query(
			'',
			'SELECT b.id_board, b.id_cat
			FROM {db_prefix}categories AS c
				JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)
			ORDER BY c.cat_order, b.board_order',
		);

		foreach (Db::$db->fetch_all($request) as $row) {
			if (!in_array($row['id_cat'], self::$tree_order['cats'])) {
				self::$tree_order['cats'][] = $row['id_cat'];
			}

			self::$tree_order['boards'][] = $row['id_board'];
		}
		Db::$db->free_result($request);

		CacheApi::put('board_order', self::$tree_order, 86400);

		return self::$tree_order;
	}

	/**
	 * Load a lot of useful information regarding the boards and categories.
	 * The information retrieved is stored in static properties:
	 *  Board::$loaded        Instances of SMF\Board for each board.
	 *  Category::$boardList  A list of board IDs grouped by category ID.
	 *  Category::$loaded	  Complete hierarchy of all categories and boards.
	 */
	public static function getTree(): void
	{
		$selects = [
			'COALESCE(b.id_board, 0) AS id_board', 'b.name', 'b.description',
			'b.id_parent', 'b.child_level', 'b.board_order', 'b.redirect',
			'b.member_groups', 'b.deny_member_groups', 'b.id_profile',
			'b.id_theme', 'b.override_theme', 'b.count_posts', 'b.num_posts',
			'b.num_topics', 'c.id_cat', 'c.cat_order', 'c.can_collapse',
			'c.name AS cat_name', 'c.description AS cat_desc',
		];
		$params = [];
		$joins = ['LEFT JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)'];
		$where = ['{query_see_board}'];
		$order = ['c.cat_order', 'b.child_level', 'b.board_order'];

		// Let mods add extra columns, parameters, etc., to the SELECT query
		IntegrationHook::call('integrate_pre_boardtree', [&$selects, &$params, &$joins, &$where, &$order]);

		$selects = array_unique($selects);
		$params = array_unique($params);
		$joins = array_unique($joins);
		$where = array_unique($where);
		$order = array_unique($order);

		// Getting all the board and category information you'd ever wanted.
		self::$loaded = [];
		$last_board_order = 0;
		$prevBoard = 0;
		$curLevel = 0;

		foreach (self::queryData($selects, $params, $joins, $where, $order) as $row) {
			if (!isset(self::$loaded[$row['id_cat']])) {
				self::init($row['id_cat'], [
					'name' => $row['cat_name'],
					'description' => $row['cat_desc'],
					'order' => $row['cat_order'],
					'can_collapse' => $row['can_collapse'],
					'last_board_order' => $last_board_order,
				]);
				$prevBoard = 0;
				$curLevel = 0;
			}

			$row['cat'] = self::$loaded[$row['id_cat']];

			unset($row['id_cat'], $row['cat_name'], $row['cat_desc'], $row['cat_order'], $row['can_collapse']);

			if (!empty($row['id_board'])) {
				if ($row['child_level'] != $curLevel) {
					$prevBoard = 0;
				}

				$row['member_groups'] = explode(',', $row['member_groups']);
				$row['deny_member_groups'] = explode(',', $row['deny_member_groups']);
				$row['prev_board'] = $prevBoard;

				Board::init($row['id_board'], $row);

				$prevBoard = $row['id_board'];
				$last_board_order = $row['board_order'];

				if (empty($row['child_level'])) {
					Board::$loaded[$row['id_board']]->is_first = empty(self::$loaded[$row['cat']->id]['children']);

					self::$loaded[$row['cat']->id]->children[$row['id_board']] = Board::$loaded[$row['id_board']];
				} else {
					// Parent doesn't exist!
					if (!isset(Board::$loaded[$row['id_parent']])) {
						ErrorHandler::fatalLang('no_valid_parent', false, [$row['name']]);
					}

					// Wrong childlevel...we can silently fix this...
					if (Board::$loaded[$row['id_parent']]->child_level != $row['child_level'] - 1) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}boards
							SET child_level = {int:new_child_level}
							WHERE id_board = {int:selected_board}',
							[
								'new_child_level' => Board::$loaded[$row['id_parent']]->child_level + 1,
								'selected_board' => $row['id_board'],
							],
						);

						Board::$loaded[$row['id_board']]->child_level = Board::$loaded[$row['id_parent']]->child_level + 1;
					}

					Board::$loaded[$row['id_board']]->is_first = empty(Board::$loaded[$row['id_parent']]->children);
					Board::$loaded[$row['id_parent']]->children[$row['id_board']] = Board::$loaded[$row['id_board']];
				}
			}

			// If mods want to do anything with this board before we move on, now's the time
			IntegrationHook::call('integrate_boardtree_board', [$row]);
		}

		// Get a list of all the boards in each category (using recursion).
		self::$boardList = [];

		foreach (self::$loaded as $id => $node) {
			self::$boardList[$id] = [];
			self::recursiveBoards(self::$boardList[$id], $node);
		}
	}

	/**
	 * Recursively get a list of boards.
	 *
	 * Used by self::getTree().
	 *
	 * @param array &$list The board list
	 * @param SMF\Category &$tree The board tree
	 */
	public static function recursiveBoards(&$list, &$tree): void
	{
		if (empty($tree->children)) {
			return;
		}

		foreach ($tree->children as $child) {
			$list[] = $child->id;
			self::recursiveBoards($list, $child);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via Category::init() or
	 * Category::load().
	 *
	 * Creates an instance for the give ID number, sets any properties supplied
	 * in $props, and adds the instance to the Category::$loaded array.
	 *
	 * If $props is empty, a query will be performed to populate the properties
	 * with data from the categories table.
	 *
	 * @param int $id The ID number of the category.
	 * @param array $props Array of properties to set.
	 */
	protected function __construct(int $id, array $props = [])
	{
		// No props provided, so get the standard ones.
		if (empty($props)) {
			$request = Db::$db->query(
				'',
				'SELECT *
				FROM {db_prefix}categories
				WHERE id_cat = {int:id}
				LIMIT 1',
				[
					'id' => $id,
				],
			);

			if (Db::$db->num_rows($request) > 0) {
				$props = Db::$db->fetch_all($request);
			}
			Db::$db->free_result($request);
		}

		$this->id = $id;
		$this->set($props);
		self::$loaded[$this->id] = $this;

		if (count(self::$loaded) > 1) {
			uasort(
				self::$loaded,
				function ($a, $b) {
					return ($a->order ?? 0) <=> ($b->order ?? 0);
				},
			);

			foreach (self::$loaded as $id => $info) {
				self::$loaded[$id]->is_first = false;
			}

			self::$loaded[array_key_first(self::$loaded)]->is_first = true;
		} else {
			$this->is_first = true;
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Generator that runs queries about category data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}boards AS b ON (c.id_cat = b.id_cat)'
	 *    Note: 'FROM {db_prefix}categories AS c' is always part of the query.
	 * @param array $where Zero or more conditions for the WHERE clause.
	 *    Conditions will be placed in parentheses and concatenated with AND.
	 *    If this is left empty, no WHERE clause will be used.
	 * @param array $order Zero or more conditions for the ORDER BY clause.
	 *    If this is left empty, no ORDER BY clause will be used.
	 * @param array $group Zero or more conditions for the GROUP BY clause.
	 *    If this is left empty, no GROUP BY clause will be used.
	 * @param int|string $limit Maximum number of results to retrieve.
	 *    If this is left empty, all results will be retrieved.
	 *
	 * @return Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0)
	{
		$request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}categories AS c' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($group) ? '' : '
			GROUP BY ' . implode(', ', $group)) . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (!empty($limit) ? '
			LIMIT ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			yield $row;
		}

		Db::$db->free_result($request);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Category::exportStatic')) {
	Category::exportStatic();
}

?>