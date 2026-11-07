<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\Db\DatabaseApi as Db;

/**
 * The recent posts page, narrowed to some boards.
 */
#[CoversNothing]
class RecentTest extends HttpTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Recent posts can be narrowed to a list of boards.
	 *
	 * The boards list is how the board index's "recent posts" links for a set
	 * of boards reach the page.
	 *
	 * Expected: each case in boardLists() requests ?action=recent;boards= with
	 *           that many visible boards, and gets a forum page with nothing
	 *           logged.
	 * Guards:   ?action=recent;boards= was a 500 for any value at all.
	 *           Recent::execute() turns the comma separated list into an array
	 *           of integers, and getBoards() then ran explode() over it a
	 *           second time, which is a TypeError on an array.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/633b3fa97 Introduced by "Implements SMF\Actions\Recent and related classes"
	 * @link https://github.com/SimpleMachines/SMF/pull/9713
	 */
	#[DataProvider('boardLists')]
	public function testRecentPostsCanBeNarrowedToBoards(int $count): void
	{
		$boards = $this->visibleBoards($count);

		$response = $this->fetch('?action=recent;boards=' . implode(',', $boards));

		$this->assertLooksLikeAForumPage($response, 'recent posts for boards ' . implode(',', $boards));
		$this->assertNoErrorsLogged('recent posts for some boards logged something.' . "\n");
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * One board, and a list of them.
	 *
	 * @return array The cases: how many boards to ask for.
	 */
	public static function boardLists(): array
	{
		return [
			'one board' => [1],
			'two boards' => [2],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Boards a guest can see, repeating the last when the forum has fewer than
	 * asked for, which the page takes as it would any other list. A new forum
	 * has only the one.
	 *
	 * @param int $count How many.
	 * @return array Their ids.
	 */
	private function visibleBoards(int $count): array
	{
		$request = Db::$db->query(
			'SELECT id_board, member_groups
			FROM {db_prefix}boards
			WHERE redirect = {string:empty}
			ORDER BY id_board',
			[
				'empty' => '',
			],
		);

		$boards = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (\in_array('-1', explode(',', (string) $row['member_groups']), true)) {
				$boards[] = (int) $row['id_board'];
			}
		}

		Db::$db->free_result($request);

		$this->assertNotEmpty($boards, 'the forum has no board a guest can see');

		$boards = \array_slice($boards, 0, $count);

		return array_pad($boards, $count, end($boards));
	}
}
