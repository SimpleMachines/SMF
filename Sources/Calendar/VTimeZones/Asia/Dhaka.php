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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Dhaka
 */
class Dhaka extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Dhaka';

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
			'DTSTART' => '19411001T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+055320',
			'TZOFFSETTO' => '+0630',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420515T000000',
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0530',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420901T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0630',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T000000',
			'TZNAME' => 'UTC+06',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0600',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090619T230000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0600',
			'TZOFFSETTO' => '+0700',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100101T000000',
			'TZNAME' => 'UTC+06',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0600',
		],
	];
}

?>