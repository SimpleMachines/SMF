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
 * America/Noronha
 */
class Noronha extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Noronha';

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
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-020940',
			'TZOFFSETTO' => '-0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19311003T110000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19320401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19330401T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19321003T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19491201T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=1;UNTIL=19521201T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500416T010000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19520401T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19530301T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19631209T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640301T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650131T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650331T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19651201T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19660301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19680301T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19661101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=1;UNTIL=19671101T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19851102T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860315T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861025T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870214T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871025T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19880207T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19881016T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890129T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891015T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900211T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19991003T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000227T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20001008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=20011014T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001015T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20010218T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=3SU;UNTIL=20060219T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
	];
}

?>