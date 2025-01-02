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
 * Asia/Singapore
 */
class Singapore extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Singapore';

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
			'DTSTART' => '19050601T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+065525',
			'TZOFFSETTO' => '+0700',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330101T000000',
			'TZNAME' => 'UTC+0720',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0720',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19360101T000000',
			'TZNAME' => 'UTC+0720',
			'TZOFFSETFROM' => '+0720',
			'TZOFFSETTO' => '+0720',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19410901T000000',
			'TZNAME' => 'UTC+0730',
			'TZOFFSETFROM' => '+0720',
			'TZOFFSETTO' => '+0730',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420216T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0730',
			'TZOFFSETTO' => '+0900',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450912T000000',
			'TZNAME' => 'UTC+0730',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0730',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19811231T233000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0730',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>