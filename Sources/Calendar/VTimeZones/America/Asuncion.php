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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Asuncion
 */
class Asuncion extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Asuncion';

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
			'DTSTART' => '19311010T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-035040',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19721001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740401T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19751001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19881001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19780301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19910401T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891022T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19901001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19911006T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19921005T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930331T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19931001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19951001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940227T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=-1SU;UNTIL=19950226T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19961006T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=20011007T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970223T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=20010304T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '20020407T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20040404T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20020901T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1SU;UNTIL=20030907T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20041017T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=3SU;UNTIL=20091018T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '20050313T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU;UNTIL=20090308T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100411T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=2SU;UNTIL=20120408T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20101003T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20130324T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=4SU',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>