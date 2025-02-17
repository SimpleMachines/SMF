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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Caracas
 */
class Caracas extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Caracas';

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
			'DTSTART' => '19120212T000000',
			'TZNAME' => 'UTC-0430',
			'TZOFFSETFROM' => '-042740',
			'TZOFFSETTO' => '-0430',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0430',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071209T030000',
			'TZNAME' => 'UTC-0430',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0430',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20160501T023000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0430',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>