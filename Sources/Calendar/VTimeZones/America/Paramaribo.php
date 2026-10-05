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

namespace SMF\Calendar\VTimeZones\America;

use SMF\Calendar\VTimeZone;

/**
 * America/Paramaribo
 */
class Paramaribo extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Paramaribo';

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
			'metazone' => 'Dutch_Guiana',
		],
		1 => [
			'ts' => '1975-11-20T03:30:00+0000',
			'metazone' => 'Suriname',
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
			'DTSTART' => '19451001T000000',
			'TZNAME' => 'UTC-0330',
			'TZOFFSETFROM' => '-034036',
			'TZOFFSETTO' => '-0330',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19841001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0300',
		],
	];
}
