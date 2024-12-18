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

namespace SMF\Calendar\VTimeZones\Pacific;

/**
 * Pacific/Guam
 */
class Guam extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Guam';

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
			'DTSTART' => '19010101T000000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+0939',
			'TZOFFSETTO' => '+1000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19411210T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440731T000000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19590627T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19610129T020000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670901T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690126T000100',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19690622T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690831T020000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19700426T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19710425T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700906T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1SU;UNTIL=19710905T020000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731216T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740224T020000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760526T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760822T020100',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770424T020000',
			'TZNAME' => 'GDT',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770828T020000',
			'TZNAME' => 'GST',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001223T000000',
			'TZNAME' => 'ChST',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1000',
		],
	];
}

?>