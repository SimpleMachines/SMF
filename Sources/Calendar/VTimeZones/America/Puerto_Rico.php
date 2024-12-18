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
 * America/Puerto_Rico
 */
class Puerto_Rico extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Puerto_Rico';

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
			'DTSTART' => '18990328T120000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-042425',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420503T000000',
			'TZNAME' => 'AWT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T200000',
			'TZNAME' => 'APT',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>