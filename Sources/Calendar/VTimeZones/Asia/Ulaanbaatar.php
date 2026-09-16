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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Ulaanbaatar
 */
class Ulaanbaatar extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Ulaanbaatar';

	/**
	 * @var array
	 *
	 * Data for the VTIMEZONE components.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public array $components = [
		0 => [
			'type' => 'STANDARD',
			'DTSTART' => '19050801T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+070732',
			'TZOFFSETTO' => '+0700',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780101T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0800',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19830401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840331T160000Z',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19831001T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840930T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19980926T150000Z',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850331T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19980328T160000Z',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20010428T020000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '20010929T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SA;UNTIL=20060929T170000Z',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20020330T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SA;UNTIL=20060324T180000Z',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20150328T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SA;UNTIL=20160325T180000Z',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20150926T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SA;UNTIL=20160923T150000Z',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}
