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
 * Antarctica/Davis
 */
class Davis extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Antarctica/Davis';

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
			'DTSTART' => '19570113T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0700',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19641101T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0000',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690201T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0700',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20091018T020000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100311T010000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0700',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '20111028T020000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120222T010000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0700',
		],
	];
}

?>