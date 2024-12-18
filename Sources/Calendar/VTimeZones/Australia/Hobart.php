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

namespace SMF\Calendar\VTimeZones\Australia;

/**
 * Australia/Hobart
 */
class Hobart extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Australia/Hobart';

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
			'DTSTART' => '18950901T000000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+094916',
			'TZOFFSETTO' => '+1000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19161001T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19170325T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19171028T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=4SU;UNTIL=19181027T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19180303T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19190302T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420101T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420329T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420927T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430328T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19440326T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19431003T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19671001T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19680331T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19681027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19851027T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690309T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU;UNTIL=19710314T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19720227T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19730304T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19810301T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19820328T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19830327T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840304T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19860302T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861019T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870315T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19900318T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871025T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19881030T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19901028T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910331T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20050327T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19911006T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19991003T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000827T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20011007T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060402T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070325T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080406T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
	];
}

?>