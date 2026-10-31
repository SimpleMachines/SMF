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
 * The background task that tells members their group membership request was
 * approved or turned down, run the way the forum runs it: through cron.php.
 */
#[CoversNothing]
class GroupRequestNotifyTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Marks the task this test queues, so it can be found and removed.
	 */
	private const MARK = 'group request notify integration test';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The highest id_error before the test.
	 */
	private int $mark = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * A group request task with no requests in it finishes quietly.
	 *
	 * Expected: running a GroupAct_Notify task with an empty request_list
	 *           removes it from the queue and logs no database error.
	 * Guards:   answering requests that had already been answered - by another
	 *           moderator, or by the same form sent twice - queued the task with
	 *           an empty list anyway. Its query then failed with "given array of
	 *           integer values is empty", logged as a critical database error,
	 *           and the task stayed queued and failed again every five minutes.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/5f1b2be80 Introduced by "Restore a query"
	 * @link https://github.com/SimpleMachines/SMF/pull/9744
	 */
	public function testATaskWithNoRequestsFinishesWithoutAnError(): void
	{
		$this->mark = (int) ($this->queryRow('SELECT MAX(id_error) AS id FROM {db_prefix}log_errors')['id'] ?? 0);

		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			['task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
			[[
				'SMF\\Tasks\\GroupAct_Notify',
				json_encode([
					'member_id' => $this->adminId(),
					'member_ip' => '127.0.0.1',
					'request_list' => [],
					'status' => 'approve',
					'reason' => self::MARK,
					'time' => time(),
				]),
				0,
			]],
			['id_task'],
		);

		$this->http->get('cron.php?ts=' . (time() - time() % 15));

		$this->assertNull(
			$this->queryRow('SELECT id_task FROM {db_prefix}background_tasks WHERE task_data LIKE {string:mark}', ['mark' => '%' . self::MARK . '%']),
			'the task is still queued, so it will fail again',
		);

		$this->assertNull(
			$this->queryRow(
				'SELECT id_error FROM {db_prefix}log_errors WHERE id_error > {int:mark} AND message LIKE {string:message}',
				['mark' => $this->mark, 'message' => '%request_list%'],
			),
			'the task logged a database error',
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if (isset(Db::$db)) {
			Db::$db->query('DELETE FROM {db_prefix}background_tasks WHERE task_data LIKE {string:mark}', ['mark' => '%' . self::MARK . '%']);
			Db::$db->query('DELETE FROM {db_prefix}log_errors WHERE id_error > {int:mark} AND message LIKE {string:message}', ['mark' => $this->mark, 'message' => '%request_list%']);
		}

		parent::tearDown();
	}
}
