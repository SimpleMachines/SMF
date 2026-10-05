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
	 * Entries logged in the same second tie on the time column, and with only
	 * that column to go on the database chooses their order. MySQL lists them
	 * oldest first, the opposite of the rest of the log. PostgreSQL lists them
	 * newest first until one of the rows is rewritten, after which it does not,
	 * so at the edge of a page which of them are shown can change between page
	 * loads.
	 */
	public function testEntriesLoggedInTheSameSecondAreNewestFirst(): void
	{
		[$older, $newer] = $this->logTwice();

		$this->assertSame([$newer, $older], $this->listed('lm.log_time DESC'));
	}

	/**
	 * Every column can tie, so every column needs the same tiebreaker. The
	 * sort a list passes is the column's default or its reverse, and the ties
	 * turn around along with it.
	 *
	 * @param string $sort The ORDER BY clause the list passes.
	 * @param bool $newest_first Whether the tied entries should come out newest first.
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
