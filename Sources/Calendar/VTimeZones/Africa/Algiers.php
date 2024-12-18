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

namespace SMF\Calendar\VTimeZones\Africa;

/**
 * Africa/Algiers
 */
class Algiers extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Algiers';

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
			'DTSTART' => '19110311T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+000921',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19160614T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19161002T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19191005T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170324T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180309T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190301T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200214T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19201024T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19210314T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19210622T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390911T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19391119T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19400225T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19441008T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450916T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461007T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19560129T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19630414T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19710425T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19710927T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770506T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19771021T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0100',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780324T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780922T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19791026T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800425T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '19801031T020000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19810501T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
	];
}

?>