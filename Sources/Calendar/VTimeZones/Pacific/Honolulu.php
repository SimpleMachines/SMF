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
 * Pacific/Honolulu
 */
class Honolulu extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Honolulu';

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
			'DTSTART' => '18960113T120000',
			'TZNAME' => 'HST',
			'TZOFFSETFROM' => '-103126',
			'TZOFFSETTO' => '-1030',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330430T020000',
			'TZNAME' => 'HDT',
			'TZOFFSETFROM' => '-1030',
			'TZOFFSETTO' => '-0930',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19330521T120000',
			'TZNAME' => 'HST',
			'TZOFFSETFROM' => '-0930',
			'TZOFFSETTO' => '-1030',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'HWT',
			'TZOFFSETFROM' => '-1030',
			'TZOFFSETTO' => '-0930',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T133000',
			'TZNAME' => 'HPT',
			'TZOFFSETFROM' => '-0930',
			'TZOFFSETTO' => '-0930',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'HST',
			'TZOFFSETFROM' => '-0930',
			'TZOFFSETTO' => '-1030',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19470608T020000',
			'TZNAME' => 'HST',
			'TZOFFSETFROM' => '-1030',
			'TZOFFSETTO' => '-1000',
		],
	];
}

?>