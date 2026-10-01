<?php

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
	 * The query had no ORDER BY, so the database chose the order, and with a
	 * limit it chose which members were listed at all. PostgreSQL returns a
	 * row it has just rewritten after the others, which MySQL does not, so on
	 * PostgreSQL the list changed as members' rows were updated.
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
