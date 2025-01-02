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
 * America/Montevideo
 */
class Montevideo extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Montevideo';

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
			'DTSTART' => '19200501T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-034451',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19231001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19251001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19240401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19260401T000000',
			'TZNAME' => 'UTC-0330',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0330',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19331029T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19381030T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19340401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SA;UNTIL=19410330T000000',
			'TZNAME' => 'UTC-0330',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0330',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19391001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0300',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19401027T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0300',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410801T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0300',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19421214T000000',
			'TZNAME' => 'UTC-0230',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0230',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430314T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0300',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19590524T000000',
			'TZNAME' => 'UTC-0230',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0230',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19591115T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0300',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19600117T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19600306T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650404T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650926T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19680527T000000',
			'TZNAME' => 'UTC-0230',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0230',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19681201T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0300',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19700425T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700614T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19720423T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19720716T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740113T000000',
			'TZNAME' => 'UTC-0130',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0130',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740310T000000',
			'TZNAME' => 'UTC-0230',
			'TZOFFSETFROM' => '-0130',
			'TZOFFSETTO' => '-0230',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740901T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0300',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19741222T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750330T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19761219T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770306T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19771204T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780305T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19790304T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19781217T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790429T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800316T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871214T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		35 => [
			'type' => 'STANDARD',
			'DTSTART' => '19880228T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19881211T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		37 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890305T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		38 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891029T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		39 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900225T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19901021T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=21,22,23,24,25,26,27;UNTIL=19911027T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		41 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910303T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19920301T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		42 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19921018T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		43 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930228T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		44 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20040919T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		45 => [
			'type' => 'STANDARD',
			'DTSTART' => '20050327T020000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		46 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20051009T020000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		47 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060312T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU;UNTIL=20150308T020000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		48 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20061001T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=20141005T020000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
	];
}

?>