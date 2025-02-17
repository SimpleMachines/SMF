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

namespace SMF\Calendar\VTimeZones\Antarctica;

/**
 * Antarctica/Palmer
 */
class Palmer extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Antarctica/Palmer';

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
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650101T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '-0300',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19660301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19641015T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=15;UNTIL=19661015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19670402T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19671001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19681006T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19680407T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19690406T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19691005T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740123T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740501T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19820501T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19730220T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19871011T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700721T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19860309T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870412T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860722T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19900311T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870217T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19891015T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900916T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890718T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19960310T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900220T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19971012T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970330T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980315T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980927T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990404T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980217T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20101010T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980721T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20070311T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080330T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090315T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100404T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110508T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110821T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100907T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=23,24,25,26,27,28,29;UNTIL=20140427T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110111T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=20140907T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '20140923T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20180513T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20141223T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=8;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20180812T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '20161204T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
	];
}

?>