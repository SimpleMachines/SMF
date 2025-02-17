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

namespace SMF\Calendar\VTimeZones\Atlantic;

/**
 * Atlantic/Canary
 */
class Canary extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Atlantic/Canary';

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
			'DTSTART' => '19220301T000000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-010136',
			'TZOFFSETTO' => '-0100',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460930T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '+0000',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800406T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
	];
}

?>