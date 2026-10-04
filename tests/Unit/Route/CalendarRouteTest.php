<?php

declare(strict_types=1);

namespace SMF\Tests\Unit\Route;

use SMF\Actions\Calendar;
use SMF\Tests\TestCase\AbstractRouteTestCase;

class CalendarRouteTest extends AbstractRouteTestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testParsesClockAlias(): void
	{
		$this->assertSame(
			[
				'action' => 'calendar',
				'sa' => 'clock',
			],
			Calendar::parseRoute(['clock']),
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function provideClassNameCases(): iterable
	{
		yield 'calendar' => [Calendar::class];
	}

	public static function provideRouteCases(): iterable
	{
		yield 'action only' => [
			'/calendar',
			['action' => 'calendar'],
		];

		yield 'clock subaction' => [
			'/calendar/clock',
			[
				'action' => 'calendar',
				'sa' => 'clock',
			],
		];

		yield 'clock with omfg' => [
			'/calendar/clock/omfg',
			[
				'action' => 'calendar',
				'sa' => 'clock',
				'omfg' => true,
			],
		];

		yield 'clock with rb' => [
			'/calendar/clock/rb',
			[
				'action' => 'calendar',
				'sa' => 'clock',
				'rb' => true,
			],
		];

		yield 'clock with bcd' => [
			'/calendar/clock/bcd',
			[
				'action' => 'calendar',
				'sa' => 'clock',
				'bcd' => true,
			],
		];

		yield 'year' => [
			'/calendar/2026/01/01',
			[
				'action' => 'calendar',
				'year' => '2026',
				'month' => '01',
				'day' => '01',
			],
		];

		yield 'year and month' => [
			'/calendar/2026/09/01',
			[
				'action' => 'calendar',
				'year' => '2026',
				'month' => '09',
				'day' => '01',
			],
		];

		yield 'full date' => [
			'/calendar/2026/09/24',
			[
				'action' => 'calendar',
				'year' => '2026',
				'month' => '09',
				'day' => '24',
			],
		];

		yield 'event' => [
			'/calendar/events/123',
			[
				'action' => 'calendar',
				'event' => '123',
			],
		];

		yield 'event post' => [
			'/calendar/events/123/post/?event=123',
			[
				'action' => 'calendar',
				'event' => '123',
				'sa' => 'post',
				'eventid' => '123',
			],
		];

		yield 'event post with recurrence' => [
			'/calendar/events/123/456/post/?event=123',
			[
				'action' => 'calendar',
				'event' => '123',
				'recurrenceid' => '456',
				'sa' => 'post',
				'eventid' => '123',
			],
		];
	}
}
