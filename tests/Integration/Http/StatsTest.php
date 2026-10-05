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
use SMF\Db\DatabaseApi as Db;

/**
 * The statistics centre, as a guest sees it.
 *
 * The members it ranks are made for the test and removed again afterwards: an
 * HTTP test runs on another connection than the forum, so it cannot be rolled
 * back.
 */
#[CoversNothing]
class StatsTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array The members made for the test.
	 */
	private array $member_ids = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * The top tens were ordered by their count alone, so members with the same
	 * count came in whatever order the database returned them: here, on both
	 * engines, the later member first. PostgreSQL also returns a row it has
	 * just rewritten after the others, so there the order changes whenever a
	 * tied member's row is updated.
	 */
	public function testMembersWithTheSamePostCountAreRankedInTheOrderTheyJoined(): void
	{
		$first = $this->makeMember('Stats Tied First', 1000000);
		$second = $this->makeMember('Stats Tied Second', 1000000);

		// Rewriting the earlier row sends it to the end of the table on PostgreSQL.
		Db::$db->query(
			'UPDATE {db_prefix}members
			SET posts = posts
			WHERE id_member = {int:member}',
			['member' => $first],
		);

		$page = $this->fetch('?action=stats');
		$text = $page->text();

		$this->assertStringContainsString('Stats Tied First', $text, 'the tied members are not in the top ten. The forum said: ' . $page->errorText());
		$this->assertLessThan(
			strpos($text, 'Stats Tied Second'),
			strpos($text, 'Stats Tied First'),
			'the member who joined later is ranked first',
		);

		$this->assertNoErrorsLogged('the stats page logged something.' . "\n");
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->member_ids !== []) {
			Db::$db->query(
				'DELETE FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				['members' => $this->member_ids],
			);

			$this->member_ids = [];
		}

		parent::tearDown();
	}

	/**
	 * Makes a member with a given number of posts.
	 *
	 * @param string $name Their name.
	 * @param int $posts How many posts they have made.
	 * @return int The member's id.
	 */
	private function makeMember(string $name, int $posts): int
	{
		$login = 'stats_' . bin2hex(random_bytes(4));

		$id = (int) Db::$db->insert(
			'insert',
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'posts' => 'int',
				'is_activated' => 'int',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$login, $name, $login . '@example.com', $posts, 1, '', '', '']],
			['id_member'],
			1,
		);

		$this->member_ids[] = $id;

		return $id;
	}
}
