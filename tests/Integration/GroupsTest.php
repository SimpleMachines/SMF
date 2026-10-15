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

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversMethod;
use SMF\Actions\Groups;
use SMF\Db\DatabaseApi as Db;

/**
 * Covers the list of a membergroup's members that the admin centre shows for
 * the administrators.
 */
#[CoversMethod(Groups::class, 'listMembergroupMembers_Href')]
class GroupsTest extends IntegrationTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Members of a membergroup are listed in the order they joined.
	 *
	 * Expected: listMembergroupMembers_Href($members, 1) lists two new
	 *           administrators in id order, even after the earlier one's row has
	 *           been rewritten.
	 * Guards:   the query had no ORDER BY, so the database chose the order. A
	 *           row that PostgreSQL has just rewritten is returned after the
	 *           others, so there the list changed as members' rows were
	 *           updated.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9721
	 */
	public function testMembersAreListedInTheOrderTheyJoined(): void
	{
		$first = $this->makeAdministrator();
		$second = $this->makeAdministrator();

		// Rewriting the earlier row sends it to the end of the table on PostgreSQL.
		Db::$db->query(
			'UPDATE {db_prefix}members
			SET real_name = real_name
			WHERE id_member = {int:member}',
			['member' => $first],
		);

		Groups::listMembergroupMembers_Href($members, 1);

		$listed = array_values(array_intersect(array_keys($members), [$first, $second]));

		$this->assertSame([$first, $second], $listed);
	}

	/**
	 * With a limit, the members left out are the ones who joined last.
	 *
	 * Expected: listMembergroupMembers_Href($members, 1, $count - 1) returns true
	 *           for "more" and lists every administrator but the last, in id
	 *           order.
	 * Guards:   with no ORDER BY and a limit, the database chose which members
	 *           were listed at all.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9721
	 */
	public function testALimitKeepsTheMembersWhoJoinedFirst(): void
	{
		$this->makeAdministrator();
		$this->makeAdministrator();

		$all = $this->administratorIds();

		$more = Groups::listMembergroupMembers_Href($members, 1, \count($all) - 1);

		$this->assertTrue($more, 'there should be more administrators than the limit');
		$this->assertSame(\array_slice($all, 0, -1), array_keys($members));
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Makes an administrator, inside the test's transaction.
	 *
	 * @return int The member's id.
	 */
	private function makeAdministrator(): int
	{
		$name = 'groups_test_' . bin2hex(random_bytes(4));

		return (int) Db::$db->insert(
			'insert',
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'id_group' => 'int',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$name, $name, $name . '@example.com', 1, '', '', '']],
			['id_member'],
			1,
		);
	}

	/**
	 * Every administrator's id, in the order they joined.
	 *
	 * @return array The ids.
	 */
	private function administratorIds(): array
	{
		$ids = [];

		$request = Db::$db->query(
			'SELECT id_member
			FROM {db_prefix}members
			WHERE id_group = {int:admin} OR FIND_IN_SET({int:admin}, additional_groups) != 0
			ORDER BY id_member',
			['admin' => 1],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$ids[] = (int) $row['id_member'];
		}

		Db::$db->free_result($request);

		return $ids;
	}
}
