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
 * Pacific/Kosrae
 */
class Kosrae extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Kosrae';

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
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+105156',
			'TZOFFSETTO' => '+1100',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19141001T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19190201T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1100',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19370101T000000',
			'TZNAME' => 'UTC+10',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19410401T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450801T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19691001T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990101T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
	];
}

?>