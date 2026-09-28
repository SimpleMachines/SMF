<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Goose_Bay
 */
class Goose_Bay extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Goose_Bay';

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
			'DTSTART' => '19350330T000000',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-033052',
			'TZOFFSETTO' => '-0330',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19360511T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=MO;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19410512T033000Z',
			'TZNAME' => 'NDT',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0230',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19361005T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=MO;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=19411006T023000Z',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0330',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420511T000000',
			'TZNAME' => 'NWT',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0230',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T203000',
			'TZNAME' => 'NPT',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0230',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0330',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460512T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=2SU;UNTIL=19500514T053000Z',
			'TZNAME' => 'NDT',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0230',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461006T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=19501008T043000Z',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0330',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T053000Z',
			'TZNAME' => 'NDT',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0230',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19590927T043000Z',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0330',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19601030T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19861026T043000Z',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-0230',
			'TZOFFSETTO' => '-0330',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19660315T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T060000Z',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19601030T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19861026T050000Z',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T000100',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19871025T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T030100Z',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19880403T000100',
			'TZNAME' => 'ADDT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0200',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19871025T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020100Z',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19890402T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T040100Z',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU;UNTIL=20110313T040100Z',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU;UNTIL=20101107T030100Z',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
	];
}
