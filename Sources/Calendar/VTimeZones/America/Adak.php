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
 * America/Adak
 */
class Adak extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Adak';

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
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-114638',
			'TZOFFSETTO' => '-1100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'NWT',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T130000',
			'TZNAME' => 'NPT',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-1000',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'NST',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-1100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19670401T000000',
			'TZNAME' => 'BST',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1100',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'BDT',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'BST',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-1100',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740106T020000',
			'TZNAME' => 'BDT',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19750223T020000',
			'TZNAME' => 'BDT',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760425T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'BDT',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19831130T000000',
			'TZNAME' => 'HST',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-1000',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'HDT',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-0900',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'HDT',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-0900',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'HST',
			'TZOFFSETFROM' => '-0900',
			'TZOFFSETTO' => '-1000',
		],
	];
}

?>