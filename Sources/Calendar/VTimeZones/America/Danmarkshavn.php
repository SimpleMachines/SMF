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
 * America/Danmarkshavn
 */
class Danmarkshavn extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Danmarkshavn';

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
			'metazone' => 'Greenland_Western',
		],
		1 => [
			'ts' => '1996-01-01T03:00:00+0000',
			'metazone' => 'GMT',
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
			'DTSTART' => '19160728T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-011440',
			'TZOFFSETTO' => '-0300',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800406T020000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780707T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T030000Z',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800104T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19950327T010000Z',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960101T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '+0000',
		],
	];
}
