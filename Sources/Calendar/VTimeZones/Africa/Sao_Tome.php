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

namespace SMF\Calendar\VTimeZones\Africa;

use SMF\Calendar\VTimeZone;

/**
 * Africa/Sao_Tome
 */
class Sao_Tome extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Sao_Tome';

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
			'metazone' => 'GMT',
		],
		1 => [
			'ts' => '2018-01-01T01:00:00+0000',
			'metazone' => 'Africa_Western',
		],
		2 => [
			'ts' => '2019-01-01T01:00:00+0000',
			'metazone' => 'GMT',
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
			'DTSTART' => '19111231T232315',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+003645',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '20180101T010000',
			'TZNAME' => 'WAT',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '20190101T020000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
	];
}
