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
 * Asia/Kuching
 */
class Kuching extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Kuching';

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
			'DTSTART' => '19260301T000000',
			'TZNAME' => 'UTC+0730',
			'TZOFFSETFROM' => '+072120',
			'TZOFFSETTO' => '+0730',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19330101T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0730',
			'TZOFFSETTO' => '+0800',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19350914T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=14;UNTIL=19410914T000000',
			'TZNAME' => 'UTC+0820',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0820',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19351214T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=14;UNTIL=19411214T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0820',
			'TZOFFSETTO' => '+0800',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420216T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450912T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>