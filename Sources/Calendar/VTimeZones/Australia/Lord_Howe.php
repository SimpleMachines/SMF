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
 * Australia/Lord_Howe
 */
class Lord_Howe extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Australia/Lord_Howe';

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
			'TZOFFSETFROM' => '+103620',
			'TZOFFSETTO' => '+1000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19810301T000000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1030',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19811025T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19841028T020000',
			'TZNAME' => 'UTC+1130',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1130',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19820307T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19850303T020000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1130',
			'TZOFFSETTO' => '+1030',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19851027T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860316T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19890319T020000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1030',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861019T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1100',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871025T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19991031T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900304T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19950305T020000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1030',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960331T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20050327T020000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1030',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000827T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1100',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20011028T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20071028T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060402T020000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1030',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070325T020000',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1030',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080406T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU',
			'TZNAME' => 'UTC+1030',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1030',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20081005T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+1100',
		],
	];
}

?>