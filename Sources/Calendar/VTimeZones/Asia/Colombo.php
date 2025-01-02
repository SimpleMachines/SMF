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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Colombo
 */
class Colombo extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Colombo';

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
			'DTSTART' => '19060101T000000',
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+051932',
			'TZOFFSETTO' => '+0530',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420105T000000',
			'TZNAME' => 'UTC+06',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0600',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420901T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+0600',
			'TZOFFSETTO' => '+0630',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451016T020000',
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0530',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960525T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0630',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961026T003000',
			'TZNAME' => 'UTC+06',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0600',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060415T003000',
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+0600',
			'TZOFFSETTO' => '+0530',
		],
	];
}

?>