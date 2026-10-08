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

	/**
	 * A Time built before the user is loaded uses the forum's default zone.
	 *
	 * Expected: new Time('@1785441064') while User::$me is not set has that
	 *           timestamp and the default time zone, 'Pacific/Auckland'.
	 * Guards:   a request can build a date before User::$me exists: redirecting
	 *           from '?msg=1' to the topic that message is in happens in
	 *           cleanRequest(), which runs long before the user is loaded, and
	 *           cron.php never loads a user at all. User::$me is a typed
	 *           static, so reading it then was a fatal error rather than an
	 *           empty value.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/7bc3a536c Introduced by "Removes unnecessary SMF\User::getTimezone() method"
	 * @link https://github.com/SimpleMachines/SMF/pull/9635
	 */
	public function testItFallsBackToTheForumsDefaultTimeZoneBeforeTheUserIsLoaded(): void
	{
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

	/**
	 * An explicitly given time zone is used as it was given.
	 *
	 * Expected: new Time('@1785441064', 'Asia/Tokyo') has the time zone
	 *           'Asia/Tokyo'.
	 */
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
