<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Time;
use SMF\TimeZone;
use SMF\User;

#[CoversClass(Time::class)]
class TimeTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool Whether Config::$modSettings had a default time zone already.
	 */
	private bool $had_default_timezone;

	/**
	 * @var string The forum's default time zone as it was before the test ran.
	 */
	private string $default_timezone;

	/****************
	 * Public methods
	 ****************/

	public function testItFallsBackToTheForumsDefaultTimeZoneBeforeTheUserIsLoaded(): void
	{
		// A request can build a date before User::$me exists: redirecting from
		// '?msg=1' to the topic that message is in happens in cleanRequest(),
		// which runs long before the user is loaded, and cron.php never loads a
		// user at all. Reading User::$me then is a fatal error rather than an
		// empty value, so the constructor has to manage without it.
		$this->assertFalse(isset(User::$me));

		// The constructor only consults User::$me while it has no time zone
		// worked out yet, so this has to be the first Time built in the run for
		// the assertion below to mean anything. A typed static cannot be put
		// back into its uninitialised state, so check the precondition instead
		// of resetting it: a later change that builds a Time earlier should
		// fail here rather than quietly stop testing anything.
		$this->assertFalse((new \ReflectionProperty(Time::class, 'user_tz'))->isInitialized());

		$time = new Time('@1785441064');

		$this->assertSame(1785441064, $time->getTimestamp());

		// The forum's default time zone stands in for the one we cannot ask for.
		$this->assertSame('Pacific/Auckland', $time->getTimezone()->getName());
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

		// Time remembers the first time zone it works out for the rest of the
		// process, so hand the suite back the one it would have had if this
		// test had never run.
		(new \ReflectionProperty(Time::class, 'user_tz'))
			->setValue(null, TimeZone::create(date_default_timezone_get()));
	}
}
