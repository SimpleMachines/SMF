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

namespace SMF\Calendar\VTimeZones\Antarctica;

/**
 * Antarctica/Mawson
 */
class Mawson extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Antarctica/Mawson';

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
			'DTSTART' => '19540213T000000',
			'TZNAME' => 'UTC+06',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0600',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '20091018T020000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0600',
			'TZOFFSETTO' => '+0500',
		],
	];
}

?>