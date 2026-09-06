<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Africa;

/**
 * Africa/El_Aaiun
 */
class El_Aaiun extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/El_Aaiun';

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
			'DTSTART' => '19340101T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '+005248',
			'TZOFFSETTO' => '-0100',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760414T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '+0000',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760501T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19770501T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760801T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770928T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780601T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780804T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20080601T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080901T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090601T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090821T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20100502T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100808T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110403T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110731T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=20130428T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120720T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120820T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120930T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '20130707T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130810T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131027T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20181028T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20140330T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20180325T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '20140628T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20140802T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '20150614T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20150719T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20160605T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20160710T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20170521T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20170702T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '20180513T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20180617T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20190505T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '20190609T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20200419T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '20200531T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20210411T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '20210516T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20220327T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		40 => [
			'type' => 'STANDARD',
			'DTSTART' => '20220508T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		41 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20230319T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '20230423T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		43 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20240310T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		44 => [
			'type' => 'STANDARD',
			'DTSTART' => '20240414T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20250223T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		46 => [
			'type' => 'STANDARD',
			'DTSTART' => '20250406T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		47 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20260215T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		48 => [
			'type' => 'STANDARD',
			'DTSTART' => '20260322T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		49 => [
			'type' => 'STANDARD',
			'DTSTART' => '20260920T020000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
	];
}
