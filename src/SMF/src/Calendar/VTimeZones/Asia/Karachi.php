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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Karachi
 */
class Karachi extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Karachi';

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
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+042812',
			'TZOFFSETTO' => '+0530',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420901T000000',
			'TZNAME' => 'UTC+0630',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0630',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451015T000000',
			'TZNAME' => 'UTC+0530',
			'TZOFFSETFROM' => '+0630',
			'TZOFFSETTO' => '+0530',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T000000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0530',
			'TZOFFSETTO' => '+0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19710326T000000',
			'TZNAME' => 'PKT',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0500',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20020407T000000',
			'TZNAME' => 'PKST',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0600',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20021006T000000',
			'TZNAME' => 'PKT',
			'TZOFFSETFROM' => '+0600',
			'TZOFFSETTO' => '+0500',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20080601T000000',
			'TZNAME' => 'PKST',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0600',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20081101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=1;UNTIL=20091101T000000',
			'TZNAME' => 'PKT',
			'TZOFFSETFROM' => '+0600',
			'TZOFFSETTO' => '+0500',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090415T000000',
			'TZNAME' => 'PKST',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0600',
		],
	];
}

?>