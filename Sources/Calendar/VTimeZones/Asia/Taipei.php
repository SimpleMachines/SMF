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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Taipei
 */
class Taipei extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Taipei';

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
			'DTSTART' => '18960101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0806',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19371001T000000',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450921T010000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460515T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19470415T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19471101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480501T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19510501T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19481001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19511001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19520301T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19521101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=1;UNTIL=19541101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19530401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19590401T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19551001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19611001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19600601T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYMONTHDAY=1;UNTIL=19610601T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19750401T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19751001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790701T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19791001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>