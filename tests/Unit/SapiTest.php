<?php

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
		// The root of an absolute path is a drive letter on Windows and nothing
		// at all on POSIX, so name it here rather than let the current drive
		// decide. Everything after it is the same on both.
		$root = DIRECTORY_SEPARATOR === '/' ? '' : 'C:';
		$sep = DIRECTORY_SEPARATOR;

		$this->assertSame($root . $sep . 'a' . $sep . 'c', Sapi::canonicalPath($root . '/a/./b/../c', false, false));
		$this->assertSame($root . $sep . 'a', Sapi::canonicalPath($root . '/a/b/..', false, false));
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

	public function testAPlainByteCountKeepsItsLastDigit(): void
	{
		// The designator is optional, and Graphics\Image passes a computed byte
		// count without one. Stripping the last character regardless turned this
		// into a tenth of the memory that was actually asked for.
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
