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

namespace SMF\Calendar\VTimeZones\Pacific;

/**
 * Pacific/Kiritimati
 */
class Kiritimati extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Kiritimati';

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
			'DTSTART' => '19791001T000000',
			'TZNAME' => 'UTC-10',
			'TZOFFSETFROM' => '-1040',
			'TZOFFSETTO' => '-1000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19941231T000000',
			'TZNAME' => 'UTC+14',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '+1400',
		],
	];
}

?>