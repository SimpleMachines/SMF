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

namespace SMF\Calendar\VTimeZones\Australia;

/**
 * Australia/Melbourne
 */
class Melbourne extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Australia/Melbourne';

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
			'DTSTART' => '18950201T000000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+093952',
			'TZOFFSETTO' => '+1000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170101T020000',
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
			'DTSTART' => '19420101T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420329T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420927T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430328T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19440326T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19431003T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19711031T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19851027T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19720227T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19730304T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19850303T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860316T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19900318T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861019T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=3SU;UNTIL=19871018T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19881030T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19991031T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910303T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19940306T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19950326T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20050327T020000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000827T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20011028T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20071028T020000',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060402T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070325T030000',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080406T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU',
			'TZNAME' => 'AEST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20081005T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU',
			'TZNAME' => 'AEDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
	];
}

?>