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

namespace SMF\Calendar\VTimeZones\Indian;

/**
 * Indian/Chagos
 */
class Chagos extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Indian/Chagos';

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
			'DTSTART' => '19070101T000000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+044940',
			'TZOFFSETTO' => '+0500',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960101T000000',
			'TZNAME' => 'UTC+06',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0600',
		],
	];
}

?>