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
 * Europe/Lisbon
 */
class Lisbon extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Lisbon';

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
			'DTSTART' => '19111231T232315',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+003645',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19160617T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19161101T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19210301T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19171015T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=14;UNTIL=19211015T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19240416T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19241005T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19260417T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19261003T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19291005T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19270409T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280414T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19290420T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19310418T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19311004T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19321001T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19320402T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19340407T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19341007T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19381001T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19350330T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19360418T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19370403T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380326T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390415T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19391119T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400224T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19401008T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410405T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19411006T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420314T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SA;UNTIL=19450310T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420425T230000',
			'TZNAME' => 'WEMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420816T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421025T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SA;BYMONTHDAY=24,25,26,27,28,29,30;UNTIL=19451027T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430417T230000',
			'TZNAME' => 'WEMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430829T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=8;BYDAY=SA;BYMONTHDAY=25,26,27,28,29,30,31;UNTIL=19450825T220000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440422T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SA;BYMONTHDAY=21,22,23,24,25,26,27;UNTIL=19450421T220000',
			'TZNAME' => 'WEMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460406T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		35 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461006T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19470406T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19660403T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		37 => [
			'type' => 'STANDARD',
			'DTSTART' => '19471005T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19651003T020000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '19661002T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0100',
		],
		39 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760926T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770327T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		41 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770925T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		42 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780402T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19800406T010000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		43 => [
			'type' => 'STANDARD',
			'DTSTART' => '19781001T020000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		44 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19800928T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19860330T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		46 => [
			'type' => 'STANDARD',
			'DTSTART' => '19810927T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19850929T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		47 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		48 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810826T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		49 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800227T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		50 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
	];
}

?>