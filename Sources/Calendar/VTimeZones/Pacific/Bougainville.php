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
 * Pacific/Bougainville
 */
class Bougainville extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Bougainville';

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
			'DTSTART' => '18950101T000000',
			'TZNAME' => 'UTC+10',
			'TZOFFSETFROM' => '+094832',
			'TZOFFSETTO' => '+1000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420701T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450821T000000',
			'TZNAME' => 'UTC+10',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20141228T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+1100',
		],
	];
}

?>