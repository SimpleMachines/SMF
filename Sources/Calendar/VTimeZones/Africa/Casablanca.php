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
 * Africa/Casablanca
 */
class Casablanca extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Casablanca';

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
			'DTSTART' => '19131026T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+003020',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390912T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19391119T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400225T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451118T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19500611T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19501029T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670603T120000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671001T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740624T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740901T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760501T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19770501T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760801T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770928T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780601T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780804T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840316T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860101T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20080601T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080901T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090601T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090821T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20100502T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100808T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110403T000000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110731T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=20130428T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120720T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120820T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120930T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '20130707T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130810T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		32 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131027T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20181028T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20140330T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20180325T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '20140628T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20140802T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '20150614T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20150719T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '20160605T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20160710T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		40 => [
			'type' => 'STANDARD',
			'DTSTART' => '20170521T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		41 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20170702T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '20180513T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		43 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20180617T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		44 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20190505T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		45 => [
			'type' => 'STANDARD',
			'DTSTART' => '20190609T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		46 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20200419T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		47 => [
			'type' => 'STANDARD',
			'DTSTART' => '20200531T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		48 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20210411T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		49 => [
			'type' => 'STANDARD',
			'DTSTART' => '20210516T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		50 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20220327T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		51 => [
			'type' => 'STANDARD',
			'DTSTART' => '20220508T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		52 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20230319T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		53 => [
			'type' => 'STANDARD',
			'DTSTART' => '20230423T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		54 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20240310T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		55 => [
			'type' => 'STANDARD',
			'DTSTART' => '20240414T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		56 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20250223T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		57 => [
			'type' => 'STANDARD',
			'DTSTART' => '20250406T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		58 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20260215T030000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		59 => [
			'type' => 'STANDARD',
			'DTSTART' => '20260322T020000',
			'TZNAME' => 'UTC+01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		60 => [
			'type' => 'STANDARD',
			'DTSTART' => '20260920T020000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
	];
}
