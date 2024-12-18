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
 * America/Sitka
 */
class Sitka extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Sitka';

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
			'DTSTART' => '19000820T120000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-090113',
			'TZOFFSETTO' => '-0800',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'PWT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T160000',
			'TZNAME' => 'PPT',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0700',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740106T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19750223T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760425T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19831130T000000',
			'TZNAME' => 'AKST',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0900',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'AKDT',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'AKDT',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-0800',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'AKST',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0900',
		],
	];
}

?>