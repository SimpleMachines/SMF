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

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Db\APIs\MySQL;
use SMF\Db\DatabaseApi;

/**
 * Covers how a row for the error log is made safe to store.
 */
#[CoversMethod(DatabaseApi::class, 'sanitizeErrorRow')]
class DatabaseApiTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Values that are not strings to clean are left alone.
	 *
	 * Expected: the numbers and the IP address come back exactly as given.
	 */
	public function testOtherValuesAreLeftAlone(): void
	{
		$row = [
			'id_member' => 5,
			'log_time' => 1700000000,
			'ip' => null,
			'line' => 42,
			'message' => 'plain',
		];

		$this->assertSame($row, $this->sanitize($row));
	}

	/**
	 * A string longer than its column is cut on a character boundary.
	 *
	 * Expected: each case in overlongStrings() comes back no longer than the
	 *           column and still valid UTF-8.
	 * Guards:   nothing cut the string to fit its column, so MySQL rejected a
	 *           long message and the error was never logged. A cut by bytes
	 *           can end inside a multibyte character, which the database
	 *           rejects just the same.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/fed29b87bd Introduced by "Use perpared query for error db logging"
	 * @link https://github.com/SimpleMachines/SMF/pull/9882
	 */
	#[DataProvider('overlongStrings')]
	public function testAnOverlongStringIsCutOnACharacterBoundary(string $key, string $value, int $limit): void
	{
		$row = $this->sanitize([$key => $value]);

		$this->assertLessThanOrEqual($limit, \strlen($row[$key]));
		$this->assertTrue(mb_check_encoding($row[$key], 'UTF-8'), 'the cut left invalid UTF-8');
	}

	/**
	 * Invalid UTF-8 and NUL bytes are removed from the strings.
	 *
	 * Expected: sanitizeErrorRow() returns valid UTF-8 without NUL bytes and
	 *           keeps the readable text around them.
	 * Guards:   PostgreSQL rejected the whole row over one invalid byte, and
	 *           silently cut the string at a NUL byte.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/fed29b87bd Introduced by "Use perpared query for error db logging"
	 * @link https://github.com/SimpleMachines/SMF/pull/9882
	 */
	#[DataProvider('unsafeMessages')]
	public function testUnsafeBytesAreRemoved(string $message, string $expected_start): void
	{
		$row = $this->sanitize(['message' => $message]);

		$this->assertTrue(mb_check_encoding($row['message'], 'UTF-8'));
		$this->assertStringNotContainsString("\0", $row['message']);
		$this->assertStringStartsWith($expected_start, $row['message']);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Strings longer than their column, ending in the middle of a character.
	 *
	 * @return array Label => [column, value, column size in bytes].
	 */
	public static function overlongStrings(): array
	{
		return [
			'3-byte message' => ['message', 'a' . str_repeat('日', 30000), 65534],
			'4-byte backtrace' => ['backtrace', 'aa' . str_repeat('😀', 20000), 65534],
			'2-byte file' => ['file', 'a' . str_repeat('é', 200), 255],
			'3-byte session' => ['session', 'a' . str_repeat('日', 100), 128],
		];
	}

	/**
	 * Messages holding bytes the database cannot store.
	 *
	 * @return array Label => [message, how the cleaned message starts].
	 */
	public static function unsafeMessages(): array
	{
		return [
			'invalid UTF-8' => ["bad \xC3\x28 \xFF\xFE bytes", 'bad '],
			'NUL byte' => ["before\0after", 'beforeafter'],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Calls DatabaseApi::sanitizeErrorRow() on an API that has no connection.
	 *
	 * @param array $row The row to clean.
	 * @return array The cleaned row.
	 */
	private function sanitize(array $row): array
	{
		$api = new \ReflectionClass(MySQL::class)->newInstanceWithoutConstructor();

		return new \ReflectionMethod(DatabaseApi::class, 'sanitizeErrorRow')->invoke($api, $row);
	}
}
