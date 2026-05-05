<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Actions\MessageIndex;
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Profile;
use SMF\Utils;

/**
 * Handles the "ignored boards" section of the profile (if enabled)
 */
class IgnoreBoards implements ActionInterface
{
	use ActionTrait;

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

		// Find all the boards this user is allowed to see.
		$ignored_boards = !empty(Profile::$member->data['ignore_boards'])
			? explode(',', Profile::$member->data['ignore_boards'])
			: [];

		Utils::$context['num_boards'] = 0;
		Utils::$context['categories'] = MessageIndex::getBoardList([
			'use_permissions' => true,
			'not_redirection' => true,
		]);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = [];

		foreach (Utils::$context['categories'] as $cat_id => $category) {
			// Include a list of boards per category for easy toggling.
			Utils::$context['categories'][$cat_id]['child_ids'] = array_keys($category['boards']);

			$temp_boards[] = [
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards']),
			];

			$temp_boards = array_merge($temp_boards, array_values($category['boards']));

			foreach ($category['boards'] as $board_id => $board) {
				Utils::$context['num_boards']++;

				Utils::$context['categories'][$cat_id]['boards'][$board_id]['selected'] => in_array($board_id, $ignored_boards);
			}
		}

		$max_boards = max(2, ceil(\count($temp_boards) / 2));

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
