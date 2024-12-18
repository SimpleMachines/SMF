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

namespace SMF\Calendar\VTimeZones\Australia;

/**
 * Australia/Darwin
 */
class Darwin extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Australia/Darwin';

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
			'DTSTART' => '18950201T000000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+084320',
			'TZOFFSETTO' => '+0900',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '18990501T000000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0930',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170101T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19170325T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420101T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420329T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420927T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430328T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19440326T020000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19431003T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
	];
}

?>