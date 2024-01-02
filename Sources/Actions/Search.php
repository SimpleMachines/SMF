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
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Search\SearchApi;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * Shows the search form.
 */
class Search implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'PlushSearch1',
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
	 * Ask the user what they want to search for.
	 *
	 * What it does:
	 * - shows the screen to search forum posts (action=search)
	 * - uses the main sub template of the Search template.
	 * - uses the Search language file.
	 * - requires the search_posts permission.
	 * - decodes and loads search parameters given in the URL (if any).
	 * - the form submits to index.php?action=search2.
	 */
	public function execute(): void
	{
		// Is the load average too high to allow searching just now?
		if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_search']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_search']) {
			ErrorHandler::fatalLang('loadavg_search_disabled', false);
		}

		Lang::load('Search');

		// Don't load this in XML mode.
		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('Search');
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
		}

		// Check the user's permissions.
		User::$me->isAllowedTo('search_posts');

		// Link tree....
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=search',
			'name' => Lang::$txt['search'],
		];

		Utils::$context['search_string_limit'] = SearchApi::MAX_LENGTH;

		Utils::$context['require_verification'] = User::$me->is_guest && !empty(Config::$modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);

		if (Utils::$context['require_verification']) {
			$verifier = new Verifier(['id' => 'search']);
		}

		// If you got back from search2 by using the linktree, you get your original search parameters back.
		if (isset($_REQUEST['params'])) {
			// Due to IE's 2083 character limit, we have to compress long search strings
			$temp_params = base64_decode(str_replace(['-', '_', '.'], ['+', '/', '='], $_REQUEST['params']));
			// Test for gzuncompress failing
			$temp_params2 = @gzuncompress($temp_params);
			$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

			Utils::$context['search_params'] = [];

			foreach ($temp_params as $i => $data) {
				@list($k, $v) = explode('|\'|', $data);
				Utils::$context['search_params'][$k] = $v;
			}

			if (isset(Utils::$context['search_params']['brd'])) {
				Utils::$context['search_params']['brd'] = Utils::$context['search_params']['brd'] == '' ? [] : explode(',', Utils::$context['search_params']['brd']);
			}
		}

		if (isset($_REQUEST['search'])) {
			Utils::$context['search_params']['search'] = Utils::htmlspecialcharsDecode($_REQUEST['search']);
		}

		if (isset(Utils::$context['search_params']['search'])) {
			Utils::$context['search_params']['search'] = Utils::htmlspecialchars(Utils::$context['search_params']['search']);
		}

		if (isset(Utils::$context['search_params']['userspec'])) {
			Utils::$context['search_params']['userspec'] = Utils::htmlspecialchars(Utils::$context['search_params']['userspec']);
		}

		if (!empty(Utils::$context['search_params']['searchtype'])) {
			Utils::$context['search_params']['searchtype'] = 2;
		}

		if (!empty(Utils::$context['search_params']['minage'])) {
			Utils::$context['search_params']['minage'] = (int) Utils::$context['search_params']['minage'];
		}

		if (!empty(Utils::$context['search_params']['maxage'])) {
			Utils::$context['search_params']['maxage'] = (int) Utils::$context['search_params']['maxage'];
		}

		Utils::$context['search_params']['show_complete'] = !empty(Utils::$context['search_params']['show_complete']);

		Utils::$context['search_params']['subject_only'] = !empty(Utils::$context['search_params']['subject_only']);

		// Load the error text strings if there were errors in the search.
		if (!empty(Utils::$context['search_errors'])) {
			Lang::load('Errors');
			Utils::$context['search_errors']['messages'] = [];

			foreach (Utils::$context['search_errors'] as $search_error => $dummy) {
				if ($search_error === 'messages') {
					continue;
				}

				if ($search_error == 'string_too_long') {
					Lang::$txt['error_string_too_long'] = sprintf(Lang::$txt['error_string_too_long'], SearchApi::MAX_LENGTH);
				}

				Utils::$context['search_errors']['messages'][] = Lang::$txt['error_' . $search_error];
			}
		}

		// Find all the boards this user is allowed to see.
		$request = Db::$db->query(
			'order_by_board_order',
			'SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE {query_see_board}
				AND redirect = {string:empty_string}',
			[
				'empty_string' => '',
			],
		);
		Utils::$context['num_boards'] = Db::$db->num_rows($request);
		Utils::$context['boards_check_all'] = true;
		Utils::$context['categories'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// This category hasn't been set up yet..
			if (!isset(Utils::$context['categories'][$row['id_cat']])) {
				Utils::$context['categories'][$row['id_cat']] = [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => [],
				];
			}

			$is_recycle_board = !empty(Config::$modSettings['recycle_enable']) && $row['id_board'] == Config::$modSettings['recycle_board'];

			// Set this board up, and let the template know when it's a child.  (indent them..)
			Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
			];

			// If user selected some particular boards, is this one of them?
			if (!empty(Utils::$context['search_params']['brd'])) {
				Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] = in_array($row['id_board'], Utils::$context['search_params']['brd']);
			}
			// User didn't select any boards, so select all except ignored and recycle boards.
			else {
				Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] = !$is_recycle_board && !in_array($row['id_board'], User::$me->ignoreboards);
			}

			// If a board wasn't checked that probably should have been ensure the board selection is selected, yo!
			if (!Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] && !$is_recycle_board) {
				Utils::$context['boards_check_all'] = false;
			}
		}
		Db::$db->free_result($request);

		Category::sort(Utils::$context['categories']);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = [];

		foreach (Utils::$context['categories'] as $category) {
			$temp_boards[] = [
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards']),
			];
			$temp_boards = array_merge($temp_boards, array_values($category['boards']));

			// Include a list of boards per category for easy toggling.
			Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}

		$max_boards = ceil(count($temp_boards) / 2);

		if ($max_boards == 1) {
			$max_boards = 2;
		}

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

		if (!empty($_REQUEST['topic'])) {
			Utils::$context['search_params']['topic'] = (int) $_REQUEST['topic'];
			Utils::$context['search_params']['show_complete'] = true;
		}

		if (!empty(Utils::$context['search_params']['topic'])) {
			Utils::$context['search_params']['topic'] = (int) Utils::$context['search_params']['topic'];

			Utils::$context['search_topic'] = [
				'id' => Utils::$context['search_params']['topic'],
				'href' => Config::$scripturl . '?topic=' . Utils::$context['search_params']['topic'] . '.0',
			];

			$request = Db::$db->query(
				'',
				'SELECT subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_topic = {int:search_topic_id}
					AND {query_see_message_board} ' . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved_true}' : '') . '
				LIMIT 1',
				[
					'is_approved_true' => 1,
					'search_topic_id' => Utils::$context['search_params']['topic'],
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('topic_gone', false);
			}

			list(Utils::$context['search_topic']['subject']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Utils::$context['search_topic']['link'] = '<a href="' . Utils::$context['search_topic']['href'] . '">' . Utils::$context['search_topic']['subject'] . '</a>';
		}

		Utils::$context['page_title'] = Lang::$txt['set_parameters'];

		IntegrationHook::call('integrate_search');
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Search::exportStatic')) {
	Search::exportStatic();
}

?>