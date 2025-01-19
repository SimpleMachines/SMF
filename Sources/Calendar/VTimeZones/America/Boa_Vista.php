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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Boa_Vista
 */
class Boa_Vista extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Boa_Vista';

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
			'DTSTART' => '19140101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-040240',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19311003T110000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19320401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19330401T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19321003T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19491201T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=1;UNTIL=19521201T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500416T010000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19520401T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19530301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19631209T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650131T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650331T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19651201T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19660301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19680301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19661101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=1;UNTIL=19671101T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19851102T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860315T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861025T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870214T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871025T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19880207T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19991003T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000227T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20001008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=20011014T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001015T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>