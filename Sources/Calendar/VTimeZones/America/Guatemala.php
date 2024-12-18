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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Guatemala
 */
class Guatemala extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Guatemala';

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
			'DTSTART' => '19181005T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-060204',
			'TZOFFSETTO' => '-0600',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731125T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740224T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19830521T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19830922T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19910323T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910907T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20060430T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20061001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
	];
}

?>