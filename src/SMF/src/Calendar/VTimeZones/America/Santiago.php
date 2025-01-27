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
 * America/Santiago
 */
class Santiago extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Santiago';

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
			'DTSTART' => '19100110T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-044245',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19160701T000000',
			'TZNAME' => 'SMT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-044245',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19180910T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-044245',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19190701T000000',
			'TZNAME' => 'SMT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-044245',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19270901T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=1;UNTIL=19310901T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-044245',
			'TZOFFSETTO' => '-0400',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19280401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19320401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19320901T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420601T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420801T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460715T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460829T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19470401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19470521T230000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19681103T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690330T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19691123T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700329T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19690218T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19721015T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19710314T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700721T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19860309T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19730930T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19730220T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19871011T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870412T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860722T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19900311T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870217T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19891015T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900916T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890718T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19960310T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900220T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19971012T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970330T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980315T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980927T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990404T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980217T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20101010T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980721T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20070311T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080330T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		35 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090315T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100404T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		37 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110508T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		38 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110821T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		39 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100907T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=23,24,25,26,27,28,29;UNTIL=20140427T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110111T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=20140907T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		41 => [
			'type' => 'STANDARD',
			'DTSTART' => '20140923T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20180513T030000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		42 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20141223T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=8;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20180812T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		43 => [
			'type' => 'STANDARD',
			'DTSTART' => '20170815T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		44 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20180116T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=20210905T040000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20220911T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		46 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20220111T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
	];
}

?>