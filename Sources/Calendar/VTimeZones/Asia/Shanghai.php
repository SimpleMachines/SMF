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
 * Asia/Shanghai
 */
class Shanghai extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Shanghai';

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
			'DTSTART' => '19010101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+080543',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190413T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19191001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400601T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19401013T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410315T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19411102T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420131T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450902T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460515T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19470415T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19471101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480501T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19490501T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19481001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=30;UNTIL=19491001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19490528T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860504T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860914T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=11,12,13,14,15,16,17;UNTIL=19910915T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870412T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=11,12,13,14,15,16,17;UNTIL=19910414T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
	];
}

?>