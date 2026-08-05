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
		// rather than minutes. The leading 'P0D' comes along for the ride
		// because this class, unlike \DateInterval, populates days for a
		// duration that names no years or months.
		$this->assertSame('P0DT30M', (string) new TimeInterval('PT30M'));
	}

	public function testStringifyingIsStableAcrossARoundTrip(): void
	{
		$this->assertSame('P0DT30M', (string) new TimeInterval('P0DT30M'));
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
}
