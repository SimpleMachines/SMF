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
 * America/Toronto
 */
class Toronto extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Toronto';

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
			'DTSTART' => '18950101T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-051732',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180414T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181027T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190330T233000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19191026T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200502T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19200926T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19210515T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19210915T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19220514T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=2SU;UNTIL=19230513T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19220917T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=3SU;UNTIL=19260919T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19240504T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=1SU;UNTIL=19270501T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19270925T020000',
			'RRULE' => 'FREQ=YEARLY;BYDAY=SU;BYYEARDAY=-98,-97,-96,-95,-94,-93,-92;BYSETPOS=1;UNTIL=19370926T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280429T020000',
			'RRULE' => 'FREQ=YEARLY;BYDAY=SU;BYYEARDAY=-250,-249,-248,-247,-246,-245,-244;BYSETPOS=1;UNTIL=19370425T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380424T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19400428T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19380925T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19390924T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T030000',
			'TZNAME' => 'EWT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0400',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T190000',
			'TZNAME' => 'EPT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19480926T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19491127T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=-1SU;UNTIL=19501126T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19560930T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19571027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19731028T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
	];
}

?>