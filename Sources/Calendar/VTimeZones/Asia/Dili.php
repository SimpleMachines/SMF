<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Asia;

use SMF\Calendar\VTimeZone;

/**
 * Asia/Dili
 */
class Dili extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Dili';

	/**
	 * @var array
	 *
	 * Data about which metazone label to use for this time zone at any given
	 * date and time.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public array $metazones = [
		0 => [
			'ts' => '1970-01-01T00:00:00+0000',
			'metazone' => 'East_Timor',
		],
		1 => [
			'ts' => '1976-05-02T15:00:00+0000',
			'metazone' => 'Indonesia_Central',
		],
		2 => [
			'ts' => '2000-09-16T16:00:00+0000',
			'metazone' => 'East_Timor',
		],
	];

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
			'DTSTART' => '19120101T002220',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+082220',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420221T230000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760503T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000917T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
	];
}
