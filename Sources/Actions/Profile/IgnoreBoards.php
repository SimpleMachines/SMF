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
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Handles the "ignored boards" section of the profile (if enabled)
 */
class IgnoreBoards implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ignoreboards',
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
	 * Does the job.
	 */
	public function execute(): void
	{
		// Have the admins enabled this option?
		if (empty(Config::$modSettings['allow_ignore_boards'])) {
			ErrorHandler::fatalLang('ignoreboards_disallowed', 'user');
		}

		// Find all the boards this user is allowed to see.
		Utils::$context['num_boards'] = 0;
		Utils::$context['categories'] = [];

		$request = Db::$db->query(
			'order_by_board_order',
			'SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
				' . (!empty(Profile::$member->data['ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE {query_see_board}
				AND redirect = {string:empty_string}',
			[
				'ignore_boards' => !empty(Profile::$member->data['ignore_boards']) ? explode(',', Profile::$member->data['ignore_boards']) : [],
				'empty_string' => '',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['num_boards']++;

			// This category hasn't been set up yet..
			if (!isset(Utils::$context['categories'][$row['id_cat']])) {
				Utils::$context['categories'][$row['id_cat']] = [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => [],
				];
			}

			// Set this board up, and let the template know when it's a child.  (indent them..)
			Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'selected' => $row['is_ignored'],
			];
		}
		Db::$db->free_result($request);

		Category::sort(Utils::$context['categories']);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = [];

		foreach (Utils::$context['categories'] as $category) {
			// Include a list of boards per category for easy toggling.
			Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

			$temp_boards[] = [
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards']),
			];

			$temp_boards = array_merge($temp_boards, array_values($category['boards']));
		}

		$max_boards = max(2, ceil(count($temp_boards) / 2));

		// Now, alternate them so they can be shown left and right ;).
		Utils::$context['board_columns'] = [];

		for ($i = 0; $i < $max_boards; $i++) {
			Utils::$context['board_columns'][] = $temp_boards[$i];

			if (isset($temp_boards[$i + $max_boards])) {
				Utils::$context['board_columns'][] = $temp_boards[$i + $max_boards];
			} else {
				Utils::$context['board_columns'][] = [];
			}
		}

		Profile::$member->loadThemeOptions();
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
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\IgnoreBoards::exportStatic')) {
	IgnoreBoards::exportStatic();
}

?>