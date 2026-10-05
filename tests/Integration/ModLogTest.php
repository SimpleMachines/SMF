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
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\Actions\Moderation\Logs;
use SMF\Db\DatabaseApi as Db;

/**
 * Covers the order of the entries in the moderation and administration logs.
 */
#[CoversMethod(Logs::class, 'list_getModLogEntries')]
class ModLogTest extends IntegrationTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * A log time no other entry has, so that the test sees only its own.
	 */
	private int $time;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Entries logged in the same second are listed newest first.
	 *
	 * Expected: list_getModLogEntries() with the sort lm.log_time DESC lists two
	 *           entries logged at the same time with the newer one first.
	 * Guards:   tied entries had only the time column to go on, so the database
	 *           chose their order. MySQL listed them oldest first, the opposite
	 *           of the rest of the log. PostgreSQL listed them newest first until
	 *           one of the rows was rewritten, after which it did not, so at the
	 *           edge of a page which of them were shown could change between
	 *           page loads.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9726
	 */
	public function testEntriesLoggedInTheSameSecondAreNewestFirst(): void
	{
		[$older, $newer] = $this->logTwice();

		$this->assertSame([$newer, $older], $this->listed('lm.log_time DESC'));
	}

	/**
	 * Entries that tie on the sorted column follow the direction of the sort.
	 *
	 * Every column can tie, so every column needs the same tiebreaker. The sort
	 * a list passes is the column's default or its reverse, and the ties turn
	 * around along with it.
	 *
	 * Expected: each case in sorts() lists two tied entries oldest first, or
	 *           newest first when the sort is descending.
	 * Guards:   only the time column had a tiebreaker, so ties on any other
	 *           column were left to the database.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9726
	 */
	#[DataProvider('sorts')]
	public function testTiesFollowTheDirectionOfTheSort(string $sort, bool $newest_first): void
	{
		[$older, $newer] = $this->logTwice();

		$this->assertSame($newest_first ? [$newer, $older] : [$older, $newer], $this->listed($sort));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The sorts both log lists offer, each of which ties for two entries made
	 * by the same moderator at the same time.
	 *
	 * @return array The cases.
	 */
	public static function sorts(): array
	{
		return [
			'time, oldest first' => ['lm.log_time', false],
			'action' => ['lm.action', false],
			'action, reversed' => ['lm.action DESC', true],
			'moderator' => ['mem.real_name', false],
			'moderator, reversed' => ['mem.real_name DESC', true],
			'position, reversed' => ['mg.group_name DESC', true],
			'ip' => ['lm.ip', false],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Acts as the administrator, who sees the whole log.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->actingAs($this->adminId());
		$this->time = random_int(1_000_000_000, 1_100_000_000);
	}

	/**
	 * Logs two identical entries in the administration log at the same time.
	 *
	 * @return array The older entry's id and the newer one's.
	 */
	private function logTwice(): array
	{
		$ids = [];

		for ($i = 0; $i < 2; $i++) {
			$ids[] = (int) Db::$db->insert(
				'insert',
				'{db_prefix}log_actions',
				[
					'id_log' => 'int',
					'log_time' => 'int',
					'id_member' => 'int',
					'action' => 'string',
					'extra' => 'string',
				],
				[[3, $this->time, $this->adminId(), 'modlog_test', '{}']],
				['id_action'],
				1,
			);
		}

		return $ids;
	}

	/**
	 * Lists the test's entries from the administration log.
	 *
	 * @param string $sort The ORDER BY clause, as the list passes it.
	 * @return array The ids of the entries, in the order they were listed.
	 */
	private function listed(string $sort): array
	{
		return array_keys(Logs::list_getModLogEntries(
			0,
			10,
			$sort,
			'lm.log_time = {int:tied_time}',
			['tied_time' => $this->time],
			3,
		));
	}
}
