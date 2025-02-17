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
 * America/Moncton
 */
class Moncton extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Moncton';

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
			'DTSTART' => '18831209T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-041908',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19020615T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180414T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181027T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330611T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=2SU;UNTIL=19350609T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19330910T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=2SU;UNTIL=19350908T010000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19360607T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=1SU;UNTIL=19380605T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19360906T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1SU;UNTIL=19380904T010000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390527T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19390923T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SA;BYMONTHDAY=21,22,23,24,25,26,27;UNTIL=19410927T010000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400519T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410504T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'AWT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T200000',
			'TZNAME' => 'APT',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19720430T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460929T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19560930T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19571027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19721029T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19930404T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T000100',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19931031T000100',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T000100',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>