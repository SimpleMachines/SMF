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

namespace SMF\Calendar\VTimeZones\Africa;

/**
 * Africa/Nairobi
 */
class Nairobi extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Nairobi';

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
			'DTSTART' => '19080501T000000',
			'TZNAME' => 'UTC+0230',
			'TZOFFSETFROM' => '+022716',
			'TZOFFSETTO' => '+0230',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19280701T000000',
			'TZNAME' => 'EAT',
			'TZOFFSETFROM' => '+0230',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19300105T000000',
			'TZNAME' => 'UTC+0230',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0230',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19370101T000000',
			'TZNAME' => 'UTC+0245',
			'TZOFFSETFROM' => '+0230',
			'TZOFFSETTO' => '+0245',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420801T000000',
			'TZNAME' => 'EAT',
			'TZOFFSETFROM' => '+0245',
			'TZOFFSETTO' => '+0300',
		],
	];
}

?>