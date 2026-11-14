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
use SMF\Db\APIs\MySQL;
use SMF\Db\APIs\PostgreSQL;
use SMF\Db\DatabaseApi as Db;

/**
 * Covers writing to the error log through its prepared statement.
 */
#[CoversMethod(MySQL::class, 'error_insert')]
#[CoversMethod(PostgreSQL::class, 'error_insert')]
class ErrorInsertTest extends IntegrationTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Session value the test logs its errors under, so it finds and removes
	 * only its own rows.
	 */
	private string $session;

	/****************
	 * Public methods
	 ****************/

	/**
	 * An ordinary message is stored exactly as given.
	 *
	 * Expected: error_insert() stores the message, the file and the line
	 *           unchanged.
	 */
	public function testAnOrdinaryMessageIsStoredUnchanged(): void
	{
		$message = 'It\'s "quoted", 100% {db_prefix}x {int:foo} %s $1 ?, Ünïcödé 日本語';

		$row = $this->logAndRead($message, '2001:db8::1');

		$this->assertSame($message, $row['message']);
		$this->assertSame('ErrorInsertTest.php', $row['file']);
		$this->assertSame(42, (int) $row['line']);
	}

	/**
	 * A message the database could not take as given is still logged.
	 *
	 * Expected: error_insert() stores a row for each case in awkwardMessages(),
	 *           holding valid UTF-8 that starts with the readable part of the
	 *           message and fits its column.
	 * Guards:   the message went to the database as it was, so invalid UTF-8,
	 *           or a message longer than its column, made the insert fail and
	 *           the error was lost without a trace.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/fed29b87bd Introduced by "Use perpared query for error db logging"
	 * @link https://github.com/SimpleMachines/SMF/pull/9882
	 */
	#[DataProvider('awkwardMessages')]
	public function testAnAwkwardMessageIsStillLogged(string $message, string $expected_start): void
	{
		$row = $this->logAndRead($message, '');

		$this->assertTrue(mb_check_encoding($row['message'], 'UTF-8'), 'the stored message is not valid UTF-8');
		$this->assertStringStartsWith($expected_start, $row['message']);
		$this->assertLessThanOrEqual(65534, \strlen($row['message']));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Messages that cannot be stored byte for byte.
	 *
	 * @return array Label => [message, how the stored message starts].
	 */
	public static function awkwardMessages(): array
	{
		return [
			'invalid UTF-8' => ["bad \xC3\x28 \xFF\xFE bytes", 'bad '],
			'NUL byte' => ["before\0after", 'beforeafter'],
			'too long, cut inside a 3-byte character' => ['a' . str_repeat('日', 30000), 'a日日'],
			'too long, cut inside a 4-byte character' => ['aa' . str_repeat('😀', 20000), 'aa😀'],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Runs outside a transaction, because error_insert() rolls back any open
	 * transaction on PostgreSQL so that the error is not lost with it.
	 *
	 * @return bool Always false.
	 */
	protected function usesTransaction(): bool
	{
		return false;
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->session = 'error_insert_test_' . bin2hex(random_bytes(6));
	}

	protected function tearDown(): void
	{
		if (isset(Db::$db, $this->session)) {
			Db::$db->query(
				'DELETE FROM {db_prefix}log_errors
				WHERE session = {string:session}',
				[
					'session' => $this->session,
				],
			);
		}

		parent::tearDown();
	}

	/**
	 * Logs a message through error_insert() and reads back the row it stored.
	 *
	 * @param string $message The message to log.
	 * @param string $ip The IP address to log it under, or '' for none.
	 * @return array The stored row.
	 */
	private function logAndRead(string $message, string $ip): array
	{
		Db::$db->error_insert([0, time(), $ip, '?action=error_insert_test', $message, $this->session, 'general', 'ErrorInsertTest.php', 42, '']);

		$row = $this->queryRow(
			'SELECT message, file, line
			FROM {db_prefix}log_errors
			WHERE session = {string:session}',
			[
				'session' => $this->session,
			],
		);

		$this->assertNotNull($row, 'nothing was logged');

		return $row;
	}
}
