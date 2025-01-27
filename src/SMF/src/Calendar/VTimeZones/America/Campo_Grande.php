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
 * America/Campo_Grande
 */
class Campo_Grande extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Campo_Grande';

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
			'TZOFFSETFROM' => '-033828',
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
			'DTSTART' => '19881016T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890129T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900211T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19901021T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910217T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19911020T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920209T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19921025T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930131T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19931017T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=11,12,13,14,15,16,17;UNTIL=19951015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		32 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940220T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=19950219T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960211T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19961006T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		35 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970216T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19971006T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		37 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		38 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19981011T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		39 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990221T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19991003T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		41 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000227T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		42 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20001008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=20011014T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		43 => [
			'type' => 'STANDARD',
			'DTSTART' => '20010218T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=20060219T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		44 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20021103T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20031019T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		46 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20041102T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		47 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20051016T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		48 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20061105T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		49 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070225T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		50 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20071014T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		51 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080217T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=20110220T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		52 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20081019T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=3SU;UNTIL=20171015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		53 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120226T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		54 => [
			'type' => 'STANDARD',
			'DTSTART' => '20130217T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=20140216T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		55 => [
			'type' => 'STANDARD',
			'DTSTART' => '20150222T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		56 => [
			'type' => 'STANDARD',
			'DTSTART' => '20160221T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=20190217T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		57 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20181104T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
	];
}

?>