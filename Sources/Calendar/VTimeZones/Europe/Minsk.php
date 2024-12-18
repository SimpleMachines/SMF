<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Europe;

/**
 * Europe/Minsk
 */
class Minsk extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Minsk';

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
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0150',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19300621T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410628T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440703T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840401T000000',
			'TZNAME' => 'MSD',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19811001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19831001T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850331T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20100328T020000',
			'TZNAME' => 'MSD',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20101031T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110327T020000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
	];
}

?>