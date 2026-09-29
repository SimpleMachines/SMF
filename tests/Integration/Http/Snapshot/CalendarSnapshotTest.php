<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http\Snapshot;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\Db\DatabaseApi as Db;

/**
 * The calendar, as the fixture member sees it, as the templates render it today.
 *
 * The calendar is off on a new forum, so this class turns it on for as long as
 * it runs and puts the setting back afterwards. Every page looks at January
 * 2020, where the fixture events are, so that nothing on it depends on today's
 * date.
 */
#[CoversNothing]
class CalendarSnapshotTest extends SnapshotTestCase
{
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string|null What cal_enabled was before this class changed it, or
	 *     null when it has not.
	 */
	private static ?string $previous = null;

	/****************
	 * Public methods
	 ****************/

	#[DataProvider('pages')]
	public function testThePageRendersAsItDid(string $name, string $path, array $roots = self::PAGE, array $ignore = [], array $only = [], array $unordered = []): void
	{
		$this->assertPageMatchesSnapshot($name, $path, $roots, $ignore, $only, $unordered);
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function audience(): string
	{
		return 'member';
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$previous !== null && isset(Db::$db)) {
			self::setCalendar(self::$previous);
			self::$previous = null;
		}

		parent::tearDownAfterClass();
	}

	/**
	 * The pages, as name, path, and optionally what to compare and what to
	 * leave out of it.
	 *
	 * @return array The cases.
	 */
	public static function pages(): array
	{
		return self::cases([
			['calendar_month', '?action=calendar;year=2020;month=1;day=15'],
			['calendar_week', '?action=calendar;viewweek;year=2020;month=1;day=20'],
			['calendar_list', '?action=calendar;viewlist;year=2020;month=1;day=1'],
			['calendar_post', '?action=calendar;sa=post'],
		]);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		if (self::$previous === null) {
			self::$previous = $this->rawSetting('cal_enabled') ?? '0';
			self::setCalendar('1');
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Turns the calendar on or off.
	 *
	 * Written to the table rather than through Config::updateModSettings(),
	 * which skips a value it believes is already set. IntegrationTestCase puts
	 * Config::$modSettings back after every test, so by the time this class
	 * finishes, the copy in memory says the calendar is off while the table
	 * still says it is on, and the calendar would never be turned off again.
	 *
	 * @param string $value '1' or '0'.
	 */
	private static function setCalendar(string $value): void
	{
		Db::$db->insert(
			'replace',
			'{db_prefix}settings',
			['variable' => 'string-255', 'value' => 'string-65534'],
			[['cal_enabled', $value]],
			['variable'],
		);
	}
}
