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
 * cron.php, which runs the background tasks: every notification email, among
 * other things.
 *
 * It is reached the way browsers reach it on every page load, over HTTP with a
 * ts parameter, and it runs outside Forum entirely.
 */
#[CoversNothing]
class CronTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * A task class that does not exist.
	 */
	private const MISSING_CLASS = 'SMF\\Tasks\\NoSuchTaskForTheIntegrationSuite';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The highest id_error before the test, so its own can be removed.
	 */
	private int $mark = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * An error in a task is logged by cron.php, and the task is cleared.
	 *
	 * Expected: requesting cron.php with a queued task whose class does not
	 *           exist returns no fatal error, removes the task from
	 *           background_tasks and logs a message naming the missing class.
	 * Guards:   only Forum gave ErrorHandler its service, so anything cron.php
	 *           tried to log threw a LogicException instead. TaskRunner's
	 *           exception handler then tried to log that, and threw again. The
	 *           request died with the task still claimed, so it ran again five
	 *           minutes later: a notification email whose task logged so much
	 *           as a warning was sent again every five minutes.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/058a81c27 Introduced by "wire the error handler service up to the new service implementation"
	 * @link https://github.com/SimpleMachines/SMF/pull/9737
	 */
	public function testAnErrorInATaskIsLoggedAndTheTaskIsCleared(): void
	{
		$this->mark = (int) ($this->queryRow('SELECT MAX(id_error) AS id FROM {db_prefix}log_errors')['id'] ?? 0);

		$task = (int) Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			['task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
			[[self::MISSING_CLASS, '', 0]],
			['id_task'],
			1,
		);

		$response = $this->http->get('cron.php?ts=' . (time() - time() % 15));

		$this->assertStringNotContainsString(
			'Fatal error',
			$response->body,
			'cron.php died: ' . trim(strip_tags($response->body)),
		);

		$this->assertNull(
			$this->queryRow('SELECT id_task FROM {db_prefix}background_tasks WHERE id_task = {int:task}', ['task' => $task]),
			'the task is still queued, so it will run again',
		);

		$this->assertNotNull(
			$this->queryRow(
				'SELECT id_error FROM {db_prefix}log_errors WHERE id_error > {int:mark} AND message LIKE {string:message}',
				['mark' => $this->mark, 'message' => '%NoSuchTaskForTheIntegrationSuite%'],
			),
			'cron.php did not log the missing task class',
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if (isset(Db::$db)) {
			Db::$db->query(
				'DELETE FROM {db_prefix}background_tasks WHERE task_class = {string:class}',
				['class' => self::MISSING_CLASS],
			);

			Db::$db->query(
				'DELETE FROM {db_prefix}log_errors WHERE id_error > {int:mark} AND message LIKE {string:message}',
				['mark' => $this->mark, 'message' => '%NoSuchTaskForTheIntegrationSuite%'],
			);
		}

		parent::tearDown();
	}
}
