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
 * Africa/Bissau
 */
class Bissau extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Bissau';

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
			'DTSTART' => '19111231T235740',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-010220',
			'TZOFFSETTO' => '-0100',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750101T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '+0000',
		],
	];
}

?>