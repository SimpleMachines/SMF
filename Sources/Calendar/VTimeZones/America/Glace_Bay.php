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
 * America/Glace_Bay
 */
class Glace_Bay extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Glace_Bay';

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
			'DTSTART' => '19020615T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-035948',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180414T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181027T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'AWT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T200000',
			'TZNAME' => 'APT',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19540425T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19540926T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19620429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19621028T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19731028T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		14 => [
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