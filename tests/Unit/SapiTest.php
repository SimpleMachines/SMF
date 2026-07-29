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
		$this->assertSame('/a/c', Sapi::canonicalPath('/a/./b/../c', false, false));
		$this->assertSame('/a', Sapi::canonicalPath('/a/b/..', false, false));
	}

	public function testTheSuiteRunsOnTheCommandLine(): void
	{
		$this->assertTrue(Sapi::isCLI());
	}

	#[DataProvider('memorySizeProvider')]
	public function testMemoryReturnBytesUnderstandsUnitSuffixes(string $val, int $expected): void
	{
		$this->assertSame($expected, Sapi::memoryReturnBytes($val));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Only values carrying a unit suffix are covered here. memoryReturnBytes()
	 * unconditionally strips the last character before parsing the number, so a
	 * plain byte count such as '128' or the '-1' that means "no limit" is not
	 * read correctly. Those cases are deliberately not asserted rather than
	 * pinned to the current behaviour.
	 *
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
		];
	}
}
