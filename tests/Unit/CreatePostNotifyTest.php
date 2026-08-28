<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Tasks\CreatePost_Notify;

#[CoversClass(CreatePost_Notify::class)]
class CreatePostNotifyTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testTheOffsetIsRelativeToTheForumTimezone(): void
	{
		Config::$modSettings['default_timezone'] = 'Etc/GMT-2';

		$this->assertSame(3.0, $this->getTimeOffset('Etc/GMT-5'));
	}

	// Note: the section banner above must not be the first thing in this group when
	// the first member carries an attribute. The SMF/section_comments fixer inserts
	// the banner between the attribute and its method, which is why the data provider
	// case is second rather than first.
	#[DataProvider('timezoneProvider')]
	public function testGetTimeOffset(string $timezone, float $expected): void
	{
		$this->assertSame($expected, $this->getTimeOffset($timezone));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, float}>
	 */
	public static function timezoneProvider(): array
	{
		return [
			'UTC is no offset' => ['UTC', 0.0],
			'whole hour' => ['Etc/GMT-5', 5.0],
			'negative whole hour' => ['Etc/GMT+5', -5.0],
			'half hour is not truncated' => ['Asia/Kolkata', 5.5],
			'quarter hour is not truncated' => ['Asia/Kathmandu', 5.75],
			'empty time zone falls back to zero' => ['', 0.0],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		// The offset is relative to the forum's own time zone, so pin it.
		Config::$modSettings['default_timezone'] = 'UTC';
	}

	protected function tearDown(): void
	{
		unset(Config::$modSettings['default_timezone']);
	}

	/**
	 * Calls the protected helper under test.
	 */
	private function getTimeOffset(string $timezone): float
	{
		$method = new \ReflectionMethod(CreatePost_Notify::class, 'getTimeOffset');

		return $method->invoke(null, $timezone);
	}
}
