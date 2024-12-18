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
 * Pacific/Kwajalein
 */
class Kwajalein extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Kwajalein';

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
			'TZOFFSETFROM' => '+110920',
			'TZOFFSETTO' => '+1100',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19370101T000000',
			'TZNAME' => 'UTC+10',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1000',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19410401T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440206T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19691001T000000',
			'TZNAME' => 'UTC-12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '-1200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930821T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '-1200',
			'TZOFFSETTO' => '+1200',
		],
	];
}

?>