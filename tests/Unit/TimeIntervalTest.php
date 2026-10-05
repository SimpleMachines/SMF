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
use SMF\Lang;
use SMF\TimeInterval;

#[CoversClass(TimeInterval::class)]
class TimeIntervalTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Lang's statics as they were before the test ran.
	 */
	private array $lang_backup = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * An ISO 8601 duration is written back out as it was given.
	 *
	 * Expected: (string) new TimeInterval('P1Y2M3DT4H5M6S') returns
	 *           'P1Y2M3DT4H5M6S'.
	 */
	public function testItRoundTripsAnIso8601Duration(): void
	{
		$this->assertSame('P1Y2M3DT4H5M6S', (string) new TimeInterval('P1Y2M3DT4H5M6S'));
	}

	/**
	 * A time-only duration keeps its time designator.
	 *
	 * The point here is the 'T': without it, the 'M' would read as months
	 * rather than minutes.
	 *
	 * Expected: (string) new TimeInterval('PT30M') returns 'PT30M'.
	 */
	public function testTimeOnlyDurationsKeepTheirTimeDesignator(): void
	{
		$this->assertSame('PT30M', (string) new TimeInterval('PT30M'));
	}

	/**
	 * A zero unit is dropped when the duration is written back out.
	 *
	 * An interval built from a string has no total number of days, and
	 * stringifying writes only the units that have a value, so a redundant zero
	 * day count handed in does not come back out.
	 *
	 * Expected: (string) new TimeInterval('P0DT30M') returns 'PT30M'.
	 */
	public function testAZeroUnitIsDroppedOnTheWayBackOut(): void
	{
		$this->assertSame('PT30M', (string) new TimeInterval('P0DT30M'));
	}

	/**
	 * A TimeInterval can be built from a plain DateInterval.
	 *
	 * Expected: createFromDateInterval(new \DateInterval('P1D')) stringifies
	 *           to 'P1D'.
	 */
	public function testItCanBeBuiltFromAPlainDateInterval(): void
	{
		$this->assertSame(
			'P1D',
			(string) TimeInterval::createFromDateInterval(new \DateInterval('P1D')),
		);
	}

	/**
	 * The length of an interval in seconds is measured from a given moment.
	 *
	 * Expected: new TimeInterval('PT1H') asked toSeconds() from the epoch
	 *           returns 3600.
	 */
	public function testToSecondsIsMeasuredFromAGivenMoment(): void
	{
		$this->assertSame(3600, (new TimeInterval('PT1H'))->toSeconds(new \DateTimeImmutable('@0')));
	}

	/**
	 * The seconds in a calendar unit depend on where they are measured from.
	 *
	 * A month is not a fixed number of seconds. January is longer than
	 * February, and asking from a different starting point proves the
	 * interval is resolved against a real calendar rather than an average.
	 *
	 * Expected: new TimeInterval('P1M') asked toSeconds() from 2026-01-01
	 *           returns 31 * 86400, and from 2026-02-01 returns 28 * 86400.
	 */
	public function testToSecondsDependsOnTheMomentForCalendarUnits(): void
	{
		$january = (new TimeInterval('P1M'))->toSeconds(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
		$february = (new TimeInterval('P1M'))->toSeconds(new \DateTimeImmutable('2026-02-01T00:00:00Z'));

		$this->assertSame(31 * 86400, $january);
		$this->assertSame(28 * 86400, $february);
	}

	/**
	 * toParsable() spells the duration out in words.
	 *
	 * Note the singular 'year' against the plural everything else; the
	 * units are pluralised one at a time based on their own value.
	 *
	 * Expected: new TimeInterval('P1Y2M3DT4H5M6S')->toParsable() returns
	 *           '1 year 2 months 3 days 4 hours 5 minutes 6 seconds'.
	 */
	public function testToParsableSpellsTheDurationOut(): void
	{
		$this->assertSame(
			'1 year 2 months 3 days 4 hours 5 minutes 6 seconds',
			(new TimeInterval('P1Y2M3DT4H5M6S'))->toParsable(),
		);
	}

	/**
	 * The interval carries its own values rather than holding another object.
	 *
	 * Everything is read from the object itself, as \DateInterval's own
	 * documented interface does and as every caller passing one to date
	 * arithmetic does.
	 *
	 * Expected: new TimeInterval('P1Y2M3DT4H5M6S') is a \DateInterval whose
	 *           y, m, d, h, i and s properties are 1, 2, 3, 4, 5 and 6.
	 * Guards:   the class kept a \DateInterval of its own and answered for it
	 *           through property hooks, which left the object it actually is
	 *           empty.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/7b31585f8 Introduced by "Improves SMF\TimeInterval thanks to property hooks"
	 * @link https://github.com/SimpleMachines/SMF/pull/9499
	 */
	public function testItCarriesItsOwnValuesRatherThanHoldingThem(): void
	{
		$interval = new TimeInterval('P1Y2M3DT4H5M6S');

		$this->assertInstanceOf(\DateInterval::class, $interval);
		$this->assertSame(1, $interval->y);
		$this->assertSame(2, $interval->m);
		$this->assertSame(3, $interval->d);
		$this->assertSame(4, $interval->h);
		$this->assertSame(5, $interval->i);
		$this->assertSame(6, $interval->s);
	}

	/**
	 * Date arithmetic moves the date by the whole interval.
	 *
	 * Expected: adding new TimeInterval('P1M2DT3H') to 2026-01-01 00:00 UTC
	 *           gives 2026-02-03T03:00:00+00:00, and subtracting it gives
	 *           2025-11-28T21:00:00+00:00.
	 * Guards:   \DateTime reads an interval's own properties and cannot see a
	 *           stored one, so adding an interval that said it was a month and
	 *           change moved the date by nothing at all.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/7b31585f8 Introduced by "Improves SMF\TimeInterval thanks to property hooks"
	 * @link https://github.com/SimpleMachines/SMF/pull/9499
	 */
	public function testDateArithmeticMovesTheDateByTheWholeInterval(): void
	{
		$start = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));

		$this->assertSame(
			'2026-02-03T03:00:00+00:00',
			$start->add(new TimeInterval('P1M2DT3H'))->format('c'),
		);

		$this->assertSame(
			'2025-11-28T21:00:00+00:00',
			$start->sub(new TimeInterval('P1M2DT3H'))->format('c'),
		);
	}

	/**
	 * A TimeInterval moves a date by the same amount a DateInterval does.
	 *
	 * Expected: adding new TimeInterval('P1M2DT3H') to 2026-01-01 00:00 UTC
	 *           formats the same as adding the equivalent \DateInterval.
	 */
	public function testItMovesTheDateByTheSameAmountAPlainDateIntervalDoes(): void
	{
		$start = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));

		$this->assertSame(
			$start->add(new \DateInterval('P1M2DT3H'))->format('c'),
			$start->add(new TimeInterval('P1M2DT3H'))->format('c'),
		);
	}

	/**
	 * A duration with fractional seconds survives construction.
	 *
	 * This is the whole reason the class exists: \DateInterval accepts only the
	 * integer subset of the ISO 8601 duration spec.
	 *
	 * Expected: new TimeInterval('PT1.5S') stringifies to 'PT1.5S' and
	 *           toSeconds() from the epoch returns 1.5.
	 * Guards:   when the fractional unit was the only one given, taking it out
	 *           left 'PT' behind, which \DateInterval rejects, so every
	 *           such duration threw.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/9627ad8b6 Introduced by "Implements SMF\TimeInterval"
	 * @link https://github.com/SimpleMachines/SMF/pull/9383
	 */
	public function testFractionalSecondsSurviveConstruction(): void
	{
		$this->assertSame('PT1.5S', (string) new TimeInterval('PT1.5S'));
		$this->assertSame(1.5, (new TimeInterval('PT1.5S'))->toSeconds(new \DateTimeImmutable('@0')));
	}

	/**
	 * format('%a') falls back to the days field when the total is unknown.
	 *
	 * %a is the total number of days, which only an interval produced by diff()
	 * knows; \DateInterval writes '(unknown)' for any other. When there are no
	 * years or months in the way, the days field is that total, so it is written
	 * instead. With a year in it, the days field is not the total and nothing
	 * can be substituted, so the answer is still the one \DateInterval gives.
	 *
	 * Expected: new TimeInterval('P1DT2H')->format('%a') returns '1', and
	 *           new TimeInterval('P1Y')->format('%a') returns '(unknown)'.
	 */
	public function testFormatFallsBackToDaysWhenTheTotalIsUnknown(): void
	{
		$this->assertSame('1', (new TimeInterval('P1DT2H'))->format('%a'));

		// With a year in it, the days field is not the total and nothing can be
		// substituted, so the honest answer is still the one \DateInterval gives.
		$this->assertSame('(unknown)', (new TimeInterval('P1Y'))->format('%a'));
	}

	/*
	 * Everything below covers localize(), which this file used to say belonged
	 * to an integration suite: every branch of it goes through Lang::getTxt(),
	 * which loads a language file, which wanted Theme::$current and therefore
	 * Db::$db. #9581 removed that, so the other half of what #9499 put right is
	 * now watched here rather than only implied by toParsable() above.
	 */

	/**
	 * Each unit is localised with its own plural.
	 *
	 * Same shape as toParsable(), but through the language file: the units are
	 * pluralised one at a time, and the result is a sentence rather than a list
	 * of fields.
	 *
	 * Expected: new TimeInterval('P1Y2M3D')->localize() returns
	 *           '1 year, 2 months, and 3 days'.
	 */
	public function testItLocalisesEachUnitWithItsOwnPlural(): void
	{
		$this->assertSame(
			'1 year, 2 months, and 3 days',
			(new TimeInterval('P1Y2M3D'))->localize(),
		);
	}

	/**
	 * The order of the units does not depend on how the caller wrote it.
	 *
	 * localize() walks its own table of units rather than the array it was
	 * handed, so the same three units asked for backwards come back in the
	 * order a reader expects.
	 *
	 * Expected: new TimeInterval('P1Y2M3D')->localize(['d', 'y', 'm']) returns
	 *           '1 year, 2 months, and 3 days'.
	 */
	public function testTheUnitOrderDoesNotDependOnHowTheCallerWroteIt(): void
	{
		$this->assertSame(
			'1 year, 2 months, and 3 days',
			(new TimeInterval('P1Y2M3D'))->localize(['d', 'y', 'm']),
		);
	}

	/**
	 * Asking for the total days falls back when there is no total.
	 *
	 * 'a' is the total number of days, which only an interval produced by
	 * diff() has. On any other one it is substituted with years, months and
	 * days; without that, nothing would match and the answer would be a flat
	 * '0 days'.
	 *
	 * Expected: new TimeInterval('P1Y')->localize(['a']) returns '1 year', and
	 *           new TimeInterval('P1DT2H')->localize(['a']) returns '1 day'.
	 */
	public function testAskingForTheTotalDaysFallsBackWhenThereIsNoTotal(): void
	{
		$this->assertSame('1 year', (new TimeInterval('P1Y'))->localize(['a']));
		$this->assertSame('1 day', (new TimeInterval('P1DT2H'))->localize(['a']));
	}

	/**
	 * An empty result says zero of the smallest unit asked for.
	 *
	 * Empty units are dropped so the output is not padded with "0 hours, 0
	 * minutes", but dropping all of them would leave nothing to say.
	 *
	 * Expected: new TimeInterval('PT0S')->localize(['h', 'i', 's']) returns
	 *           '0 seconds', and localize(['d']) returns '0 days'.
	 */
	public function testItSaysZeroOfTheSmallestUnitItWasAskedFor(): void
	{
		$this->assertSame('0 seconds', (new TimeInterval('PT0S'))->localize(['h', 'i', 's']));
		$this->assertSame('0 days', (new TimeInterval('PT0S'))->localize(['d']));
	}

	/**
	 * Fractional seconds are folded into the seconds.
	 *
	 * 's' and 'f' are one number to a reader, not two.
	 *
	 * Expected: new TimeInterval('PT1.5S')->localize(['s', 'f']) returns
	 *           '1.5 seconds'.
	 */
	public function testFractionalSecondsAreFoldedIntoTheSeconds(): void
	{
		$this->assertSame('1.5 seconds', (new TimeInterval('PT1.5S'))->localize(['s', 'f']));
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		$this->lang_backup = self::langState();
	}

	protected function tearDown(): void
	{
		// localize() loads the General language file, and PHPUnit does not
		// reset SMF's statics between tests. Leaving 700-odd strings and a
		// record in Lang::$already_loaded behind would change what every test
		// after this one starts from.
		foreach ($this->lang_backup as $name => $value) {
			(new \ReflectionProperty(Lang::class, $name))->setValue(null, $value);
		}

		parent::tearDown();
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * The statics that loading a language file writes to.
	 *
	 * @return array Property name => current value.
	 */
	private static function langState(): array
	{
		$state = [];

		foreach (['txt', 'dirs', 'already_loaded', 'loaded_keys'] as $name) {
			$state[$name] = (new \ReflectionProperty(Lang::class, $name))->getValue();
		}

		return $state;
	}
}
