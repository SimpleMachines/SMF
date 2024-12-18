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
 * America/Eirunepe
 */
class Eirunepe extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Eirunepe';

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
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-043928',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19311003T110000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19320401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19330401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19321003T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19491201T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=1;UNTIL=19521201T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500416T010000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19520401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19530301T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19631209T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640301T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650131T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650331T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19651201T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19660301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19680301T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19661101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=1;UNTIL=19671101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19851102T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860315T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861025T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870214T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871025T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19880207T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19931017T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=11,12,13,14,15,16,17;UNTIL=19951015T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940220T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=19950219T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080624T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131110T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
	];
}

?>