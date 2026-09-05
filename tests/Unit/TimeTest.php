<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Time;
use SMF\User;

#[CoversClass(Time::class)]
class TimeTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool Whether Config::$modSettings already had a default time zone.
	 */
	private bool $had_default_timezone;

	/**
	 * @var string The forum's default time zone as it was before the test ran.
	 */
	private string $default_timezone;

	/****************
	 * Public methods
	 ****************/

	public function testItCanBeConstructedBeforeTheUserIsLoaded(): void
	{
		// A request can build a date before User::$me exists: redirecting from
		// '?msg=1' to the topic that message is in happens in cleanRequest(),
		// which runs long before the user is loaded. Reading User::$me there is
		// a fatal error rather than an empty value, so the constructor has to
		// manage without it.
		$this->assertFalse(isset(User::$me));

		$this->assertSame(1785441064, (new Time('@1785441064'))->getTimestamp());
	}

	public function testItFallsBackToTheForumsDefaultTimeZone(): void
	{
		$this->assertSame(
			'Pacific/Auckland',
			(new Time('@1785441064'))->getTimezone()->getName(),
		);
	}

	public function testTheFallbackTimeZoneIsNotRemembered(): void
	{
		// Caching the forum's default as if it were the user's own would give
		// everyone the wrong times for the rest of the request.
		new Time('@1785441064');

		$user_tz = new \ReflectionProperty(Time::class, 'user_tz');

		$this->assertFalse($user_tz->isInitialized());
	}

	public function testAnExplicitTimeZoneIsUsedAsGiven(): void
	{
		$this->assertSame(
			'Asia/Tokyo',
			(new Time('@1785441064', 'Asia/Tokyo'))->getTimezone()->getName(),
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		$this->had_default_timezone = isset(Config::$modSettings['default_timezone']);
		$this->default_timezone = (string) (Config::$modSettings['default_timezone'] ?? '');

		Config::$modSettings['default_timezone'] = 'Pacific/Auckland';
	}

	protected function tearDown(): void
	{
		if ($this->had_default_timezone) {
			Config::$modSettings['default_timezone'] = $this->default_timezone;
		} else {
			unset(Config::$modSettings['default_timezone']);
		}
	}
}
