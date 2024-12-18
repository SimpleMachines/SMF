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
 * Pacific/Nauru
 */
class Nauru extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Nauru';

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
			'DTSTART' => '19210115T000000',
			'TZNAME' => 'UTC+1130',
			'TZOFFSETFROM' => '+110740',
			'TZOFFSETTO' => '+1130',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420829T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1130',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450908T000000',
			'TZNAME' => 'UTC+1130',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1130',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790210T020000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1130',
			'TZOFFSETTO' => '+1200',
		],
	];
}

?>