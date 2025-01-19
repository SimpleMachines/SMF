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

namespace SMF\Calendar\VTimeZones\Pacific;

/**
 * Pacific/Fiji
 */
class Fiji extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Fiji';

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
			'DTSTART' => '19151026T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+115544',
			'TZOFFSETTO' => '+1200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19981101T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU;UNTIL=19991107T020000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990228T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=-1SU;UNTIL=20000227T030000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20091129T020000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100328T030000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20101024T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=21,22,23,24,25,26,27;UNTIL=20131027T020000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110306T030000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120122T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYDAY=SU;BYMONTHDAY=18,19,20,21,22,23,24;UNTIL=20130120T030000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20140119T020000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20141102T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU;UNTIL=20181104T020000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20150118T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYDAY=SU;BYMONTHDAY=12,13,14,15,16,17,18;UNTIL=20210117T030000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20191110T020000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20201220T020000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
	];
}

?>