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
 * Pacific/Efate
 */
class Efate extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Efate';

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
			'metazone' => 'Vanuatu',
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
			'DTSTART' => '19120113T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+111316',
			'TZOFFSETTO' => '+1100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731222T230000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740331T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19830925T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=4SA;UNTIL=19910928T130000Z',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840325T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=4SA;UNTIL=19910323T120000Z',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920126T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYDAY=4SA;UNTIL=19930123T120000Z',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19921025T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
	];
}
