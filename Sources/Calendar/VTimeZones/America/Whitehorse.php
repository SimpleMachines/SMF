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
 * America/Whitehorse
 */
class Whitehorse extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Whitehorse';

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
			'DTSTART' => '19000820T000000',
			'TZNAME' => 'YST',
			'TZOFFSETFROM' => '-090012',
			'TZOFFSETTO' => '-0900',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180414T020000',
			'TZNAME' => 'YDT',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0800',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181027T020000',
			'TZNAME' => 'YST',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0900',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190525T020000',
			'TZNAME' => 'YDT',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0800',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19191101T000000',
			'TZNAME' => 'YST',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0900',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'YWT',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0800',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T150000',
			'TZNAME' => 'YPT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0800',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'YST',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0900',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650425T000000',
			'TZNAME' => 'YDDT',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0700',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19651031T020000',
			'TZNAME' => 'YST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0900',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19660227T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '20201101T000000',
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0700',
		],
	];
}

?>