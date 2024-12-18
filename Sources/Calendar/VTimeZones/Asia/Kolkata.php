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
 * Asia/Kolkata
 */
class Kolkata extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Kolkata';

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
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+052110',
			'TZOFFSETTO' => '+0530',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19411001T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0630',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420515T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0530',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420901T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0630',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451015T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0530',
		],
	];
}

?>