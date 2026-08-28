<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\TimeInterval;

#[CoversClass(TimeInterval::class)]
class TimeIntervalTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testItRoundTripsAnIso8601Duration(): void
	{
		$this->assertSame('P1Y2M3DT4H5M6S', (string) new TimeInterval('P1Y2M3DT4H5M6S'));
	}

	public function testTimeOnlyDurationsKeepTheirTimeDesignator(): void
	{
		// The point here is the 'T': without it, the 'M' would read as months
		// rather than minutes.
		$this->assertSame('PT30M', (string) new TimeInterval('PT30M'));
	}

	public function testAZeroUnitIsDroppedOnTheWayBackOut(): void
	{
		// This used to be the canonical form: the class populated days for a
		// duration naming no years or months, and stringifying wrote the days
		// out whether there were any or not. Both ends of that are gone, so a
		// duration written the old way now comes back in the short form.
		$this->assertSame('PT30M', (string) new TimeInterval('P0DT30M'));
	}

	public function testItCanBeBuiltFromAPlainDateInterval(): void
	{
		$this->assertSame(
			'P1D',
			(string) TimeInterval::createFromDateInterval(new \DateInterval('P1D')),
		);
	}

	public function testToSecondsIsMeasuredFromAGivenMoment(): void
	{
		$this->assertSame(3600, (new TimeInterval('PT1H'))->toSeconds(new \DateTimeImmutable('@0')));
	}

	public function testToSecondsDependsOnTheMomentForCalendarUnits(): void
	{
		// A month is not a fixed number of seconds. January is longer than
		// February, and asking from a different starting point proves the
		// interval is resolved against a real calendar rather than an average.
		$january = (new TimeInterval('P1M'))->toSeconds(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
		$february = (new TimeInterval('P1M'))->toSeconds(new \DateTimeImmutable('2026-02-01T00:00:00Z'));

		$this->assertSame(31 * 86400, $january);
		$this->assertSame(28 * 86400, $february);
	}

	public function testToParsableSpellsTheDurationOut(): void
	{
		// Note the singular 'year' against the plural everything else; the
		// units are pluralised one at a time based on their own value.
		$this->assertSame(
			'1 year 2 months 3 days 4 hours 5 minutes 6 seconds',
			(new TimeInterval('P1Y2M3DT4H5M6S'))->toParsable(),
		);
	}

	public function testItCarriesItsOwnValuesRatherThanHoldingThem(): void
	{
		// The class used to keep a \DateInterval of its own and answer for it
		// through property hooks, which left the object it actually is empty.
		// Everything below reads the object itself, as \DateInterval's own
		// documented interface and every caller passing one to date arithmetic
		// does.
		$interval = new TimeInterval('P1Y2M3DT4H5M6S');

		$this->assertInstanceOf(\DateInterval::class, $interval);
		$this->assertSame(1, $interval->y);
		$this->assertSame(2, $interval->m);
		$this->assertSame(3, $interval->d);
		$this->assertSame(4, $interval->h);
		$this->assertSame(5, $interval->i);
		$this->assertSame(6, $interval->s);
	}

	public function testDateArithmeticMovesTheDateByTheWholeInterval(): void
	{
		// This is what an empty object costs: \DateTime reads the interval's
		// own properties and cannot see a stored one, so adding an interval
		// that said it was a month and change moved the date by nothing at all.
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

	public function testItMovesTheDateByTheSameAmountAPlainDateIntervalDoes(): void
	{
		$start = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));

		$this->assertSame(
			$start->add(new \DateInterval('P1M2DT3H'))->format('c'),
			$start->add(new TimeInterval('P1M2DT3H'))->format('c'),
		);
	}

	public function testFractionalSecondsSurviveConstruction(): void
	{
		// The whole reason this class exists: \DateInterval accepts only the
		// integer subset of the ISO 8601 duration spec.
		$this->assertSame('PT1.5S', (string) new TimeInterval('PT1.5S'));
		$this->assertSame(1.5, (new TimeInterval('PT1.5S'))->toSeconds(new \DateTimeImmutable('@0')));
	}

	public function testFormatFallsBackToDaysWhenTheTotalIsUnknown(): void
	{
		// %a is the total number of days, which only an interval produced by
		// diff() knows; \DateInterval writes '(unknown)' for any other. When
		// there are no years or months in the way, the days field is that
		// total, so it is written instead.
		$this->assertSame('1', (new TimeInterval('P1DT2H'))->format('%a'));

		// With a year in it, the days field is not the total and nothing can be
		// substituted, so the honest answer is still the one \DateInterval gives.
		$this->assertSame('(unknown)', (new TimeInterval('P1Y'))->format('%a'));
	}

	/*
	 * localize() is not covered here. It is the other half of what #9499 put
	 * right - the unit order stopped depending on how the caller wrote the
	 * argument, and asking for 'a' when the total number of days is unknown now
	 * falls back to years, months and days instead of producing nothing - but
	 * every branch of it goes through Lang::getTxt(), which loads a language
	 * file, which wants Theme::$current and therefore Db::$db. It belongs to an
	 * integration suite. toParsable() above covers the same walk over the units
	 * with the strings hard coded, so the ordering is not entirely unwatched.
	 */
}
