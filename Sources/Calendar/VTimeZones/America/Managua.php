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

namespace SMF\Calendar\VTimeZones\America;

use SMF\Calendar\VTimeZone;

/**
 * America/Managua
 */
class Managua extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Managua';

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
			'metazone' => 'America_Central',
		],
		1 => [
			'ts' => '1973-05-01T06:00:00+0000',
			'metazone' => 'America_Eastern',
		],
		2 => [
			'ts' => '1975-02-16T05:00:00+0000',
			'metazone' => 'America_Central',
		],
		3 => [
			'ts' => '1992-01-01T10:00:00+0000',
			'metazone' => 'America_Eastern',
		],
		4 => [
			'ts' => '1992-09-24T05:00:00+0000',
			'metazone' => 'America_Central',
		],
		5 => [
			'ts' => '1993-01-01T06:00:00+0000',
			'metazone' => 'America_Eastern',
		],
		6 => [
			'ts' => '1997-01-01T05:00:00+0000',
			'metazone' => 'America_Central',
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
			'DTSTART' => '19340623T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-054512',
			'TZOFFSETTO' => '-0600',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19730501T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750216T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790318T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19800316T060000Z',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790625T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=MO;BYMONTHDAY=23,24,25,26,27,28,29;UNTIL=19800623T050000Z',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920101T040000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920924T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930101T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20050410T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20051002T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20060430T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20061001T010000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
	];
}
