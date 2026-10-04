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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Sapi;

#[CoversClass(Sapi::class)]
class SapiTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testCanonicalPathResolvesDotSegments(): void
	{
		// A relative path has no root to differ over, so once the separator is
		// accounted for this says the same thing everywhere.
		$sep = DIRECTORY_SEPARATOR;

		$this->assertSame('a' . $sep . 'c', Sapi::canonicalPath('a/./b/../c', false, false));
		$this->assertSame('a', Sapi::canonicalPath('a/b/..', false, false));
	}

	public function testAnAbsolutePathIsRootedOnTheCurrentDrive(): void
	{
		// A path that starts at the root names no drive, and on Windows that
		// means the one the process is on, so the canonical form carries a
		// letter that depends on where the checkout sits. POSIX has no such
		// thing and the root is the separator on its own.
		$sep = DIRECTORY_SEPARATOR;
		$drive = $sep === '/' ? '' : substr((string) getcwd(), 0, 2);

		$this->assertSame($drive . $sep . 'a' . $sep . 'c', Sapi::canonicalPath('/a/./b/../c', false, false));
		$this->assertSame($drive . $sep . 'a', Sapi::canonicalPath('/a/b/..', false, false));
	}

	public function testTheSuiteRunsOnTheCommandLine(): void
	{
		$this->assertTrue(Sapi::isCLI());
	}

	public function testNoMemoryLimitIsReportedAsMoreThanAnythingWillNeed(): void
	{
		// A memory_limit of -1 means unlimited. Reporting it as 0 made
		// setMemoryLimit() decide the current limit was too small and impose
		// one, so asking for 128M on an unlimited server capped it at 128M.
		$this->assertSame(PHP_INT_MAX, Sapi::memoryReturnBytes('-1'));
	}

	/**
	 * A byte count with no unit designator is returned unchanged.
	 *
	 * Expected: memoryReturnBytes('50000000') returns 50000000.
	 * Guards:   the last character was always stripped as a K/M/G designator,
	 *           so Graphics\Image, which passes a plain byte count, got a tenth
	 *           of the memory it asked for.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/a4361e01b Introduced by "Introduce Sapi Class"
	 * @link https://github.com/SimpleMachines/SMF/pull/9324
	 */
	public function testAPlainByteCountKeepsItsLastDigit(): void
	{
		$this->assertSame(50000000, Sapi::memoryReturnBytes('50000000'));
	}

	public function testSurroundingWhitespaceIsIgnored(): void
	{
		$this->assertSame(67108864, Sapi::memoryReturnBytes(' 64M '));
	}

	#[DataProvider('memorySizeProvider')]
	public function testMemoryReturnBytes(string $val, int $expected): void
	{
		$this->assertSame($expected, Sapi::memoryReturnBytes($val));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, int}>
	 */
	public static function memorySizeProvider(): array
	{
		return [
			'kilobytes' => ['512K', 524288],
			'megabytes' => ['256M', 268435456],
			'gigabytes' => ['1G', 1073741824],
			'lowercase suffix' => ['256m', 268435456],
			'zero megabytes' => ['0M', 0],
			'plain byte count' => ['128', 128],
			'zero' => ['0', 0],
			'empty' => ['', 0],
		];
	}
}
