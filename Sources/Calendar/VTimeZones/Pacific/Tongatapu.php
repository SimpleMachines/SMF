<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Pacific;

use SMF\Calendar\VTimeZone;

/**
 * Pacific/Tongatapu
 */
class Tongatapu extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Tongatapu';

	/**
	 * @var array
	 *
	 * Data about which metazone label to use for this time zone at any given
	 * date and time.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public array $metazones = [
		0 => [
			'ts' => '1970-01-01T00:00:00+0000',
			'metazone' => 'Tonga',
		],
	];

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
			'DTSTART' => '19610101T000000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1220',
			'TZOFFSETTO' => '+1300',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19991007T020000',
			'TZNAME' => 'UTC+14',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000319T030000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1400',
			'TZOFFSETTO' => '+1300',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20001105T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU;UNTIL=20011103T130000Z',
			'TZNAME' => 'UTC+14',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1400',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '20010128T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYDAY=-1SU;UNTIL=20020126T120000Z',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1400',
			'TZOFFSETTO' => '+1300',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20161106T020000',
			'TZNAME' => 'UTC+14',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20170115T030000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1400',
			'TZOFFSETTO' => '+1300',
		],
	];
}
