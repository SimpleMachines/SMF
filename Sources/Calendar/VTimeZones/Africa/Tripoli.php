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

namespace SMF\Calendar\VTimeZones\Africa;

use SMF\Calendar\VTimeZone;

/**
 * Africa/Tripoli
 */
class Tripoli extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Tripoli';

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
			'metazone' => 'Europe_Eastern',
		],
		1 => [
			'ts' => '1981-12-31T22:00:00+0000',
			'metazone' => 'Europe_Central',
		],
		2 => [
			'ts' => '1990-05-03T23:00:00+0000',
			'metazone' => 'Europe_Eastern',
		],
		3 => [
			'ts' => '1996-09-29T22:00:00+0000',
			'metazone' => 'Europe_Central',
		],
		4 => [
			'ts' => '1997-10-03T22:00:00+0000',
			'metazone' => 'Europe_Eastern',
		],
		5 => [
			'ts' => '2012-11-10T00:00:00+0000',
			'metazone' => 'Europe_Central',
		],
		6 => [
			'ts' => '2013-10-25T00:00:00+0000',
			'metazone' => 'Europe_Eastern',
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
			'DTSTART' => '19200101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+005244',
			'TZOFFSETTO' => '+0100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19511014T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19520101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19531009T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19540101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19550930T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19560101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19590101T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19820101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19820401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840331T230000Z',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19821001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19850930T220000Z',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850406T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860404T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19861003T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19890331T230000Z',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19871001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19890930T220000Z',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900504T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960930T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970404T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19971004T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0200',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '20121110T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130329T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131025T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0200',
		],
	];
}
