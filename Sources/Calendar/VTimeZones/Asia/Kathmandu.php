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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Kathmandu
 */
class Kathmandu extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Kathmandu';

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
			'DTSTART' => '19200101T000000',
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+054116',
			'TZOFFSETTO' => '+0530',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860101T000000',
			'TZNAME' => 'UTC+0545',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0545',
		],
	];
}

?>