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
 * America/Havana
 */
class Havana extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Havana';

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
			'DTSTART' => '19250719T120000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-052936',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280610T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19281010T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400602T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=1SU;UNTIL=19420607T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19400901T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1SU;UNTIL=19420906T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450603T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=1SU;UNTIL=19460602T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450902T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1SU;UNTIL=19460901T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650601T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650930T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19660529T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19661002T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670408T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19670910T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=2SU;UNTIL=19680908T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19680414T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19690427T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19770424T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19691026T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19711031T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19721008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=8;UNTIL=19741008T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19751026T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19771030T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780507T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19781008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=19901014T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790318T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19800316T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810510T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=SU;BYMONTHDAY=5,6,7,8,9,10,11;UNTIL=19850505T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860316T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=14,15,16,17,18,19,20;UNTIL=19890319T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19970406T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19911013T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=19951008T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961006T010000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19971012T010000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980329T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19990328T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19981025T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20031026T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000402T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20030406T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20040328T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '20061029T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20101031T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20080316T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090308T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU;UNTIL=20100314T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110320T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '20111113T010000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120401T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '20121104T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130310T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>