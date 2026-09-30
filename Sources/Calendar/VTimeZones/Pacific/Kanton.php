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

namespace SMF\Calendar\VTimeZones\Pacific;

use SMF\Calendar\VTimeZone;

/**
 * Pacific/Kanton
 */
class Kanton extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Kanton';

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
			'metazone' => 'Phoenix_Islands',
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
			'DTSTART' => '15821015T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19370831T000000',
			'TZNAME' => 'UTC-12',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '-1200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19791001T000000',
			'TZNAME' => 'UTC-11',
			'TZOFFSETFROM' => '-1200',
			'TZOFFSETTO' => '-1100',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19941231T000000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '+1300',
		],
	];
}
