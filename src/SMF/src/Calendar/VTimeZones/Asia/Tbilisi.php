<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Tbilisi
 */
class Tbilisi extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Tbilisi';

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
			'DTSTART' => '19240502T000000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+025911',
			'TZOFFSETTO' => '+0300',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19570301T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840401T000000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0500',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19811001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19831001T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0400',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0400',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850331T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20100328T020000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0500',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T000000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0400',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20040627T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0400',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20101031T020000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '20050327T020000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
	];
}

?>