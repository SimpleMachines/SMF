<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversMethod;
use SMF\Config;

/**
 * Config::updateModSettings() against a real database, on whichever engine is
 * configured.
 *
 * The counter case is a regression test. Passing true or false as a value means
 * "add one" or "take one away", and that was emitted as SET value = value + 1
 * against settings.value, which is a text column. MySQL coerces that silently;
 * PostgreSQL rejects it outright, and because the PostgreSQL API discarded
 * failed queries without a word, the statement simply did nothing. Every counter
 * that goes through this path - totalMessages, totalTopics, totalMembers,
 * unapprovedMembers - stayed frozen at whatever the last full recount produced.
 *
 * Nothing about that is reachable without a database, and it only shows up on
 * one of the two engines, which is exactly the gap this suite exists to close.
 */
#[CoversMethod(Config::class, 'updateModSettings')]
class ModSettingsTest extends IntegrationTestCase
{
	/*****************
	 * Class constants
	 *****************/

	private const COUNTER = 'smf_tests_counter';

	/****************
	 * Public methods
	 ****************/

	public function testWritesAValue(): void
	{
		Config::updateModSettings([self::COUNTER => '41']);

		$this->assertSame('41', $this->rawSetting(self::COUNTER));
		$this->assertSame('41', Config::$modSettings[self::COUNTER]);
	}

	public function testIncrementsACounter(): void
	{
		Config::updateModSettings([self::COUNTER => '41']);
		Config::updateModSettings([self::COUNTER => true], true);

		$this->assertSame(
			42,
			(int) $this->rawSetting(self::COUNTER),
			'the counter did not increment - on PostgreSQL this means the '
			. 'arithmetic was rejected and the failure swallowed',
		);
	}

	public function testDecrementsACounter(): void
	{
		Config::updateModSettings([self::COUNTER => '41']);
		Config::updateModSettings([self::COUNTER => false], true);

		$this->assertSame(40, (int) $this->rawSetting(self::COUNTER));
	}

	/**
	 * The value has to stay something the next increment can read back, so a
	 * cast that leaves '42.0000' behind is not good enough.
	 */
	public function testAnIncrementedCounterStaysAPlainInteger(): void
	{
		Config::updateModSettings([self::COUNTER => '41']);
		Config::updateModSettings([self::COUNTER => true], true);

		$this->assertMatchesRegularExpression(
			'~^\d+$~',
			(string) $this->rawSetting(self::COUNTER),
			'the incremented value is not a plain integer, so it will not survive a round trip',
		);
	}

	public function testCountersCanBeIncrementedRepeatedly(): void
	{
		// Starts at 10 rather than 0 on purpose: see the test below for why a
		// brand new setting cannot be created holding a falsy value.
		Config::updateModSettings([self::COUNTER => '10']);

		for ($i = 0; $i < 3; $i++) {
			Config::updateModSettings([self::COUNTER => true], true);
		}

		$this->assertSame(13, (int) $this->rawSetting(self::COUNTER));
	}

	/**
	 * A setting that does not exist yet and would only be set to nothingness is
	 * skipped rather than written. That is deliberate, and it is a sharp edge:
	 * seeding a counter at zero looks like it worked and leaves no row, so the
	 * first increment then has nothing to increment.
	 */
	public function testDoesNotCreateANewSettingHoldingAFalsyValue(): void
	{
		Config::updateModSettings([self::COUNTER => '0']);

		$this->assertNull($this->rawSetting(self::COUNTER));

		// An existing one can be set to zero perfectly well.
		Config::updateModSettings([self::COUNTER => '7']);
		Config::updateModSettings([self::COUNTER => '0']);

		$this->assertSame('0', $this->rawSetting(self::COUNTER));
	}

	public function testUpdatingSettingsLogsNoErrors(): void
	{
		Config::updateModSettings([self::COUNTER => '1']);
		Config::updateModSettings([self::COUNTER => true], true);
		Config::updateModSettings([self::COUNTER => false], true);

		$this->assertNoErrorsLogged('updating a counter should be silent');
	}
}
