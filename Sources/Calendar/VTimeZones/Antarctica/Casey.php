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

namespace SMF\Calendar\VTimeZones\Antarctica;

use SMF\Calendar\VTimeZone;

/**
 * Antarctica/Casey
 */
class Casey extends VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Antarctica/Casey';

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
			'metazone' => 'Australia_Western',
		],
		1 => [
			'ts' => '2009-10-17T18:00:00+0000',
			'metazone' => 'Casey',
		],
		2 => [
			'ts' => '2010-03-04T15:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		3 => [
			'ts' => '2011-10-27T18:00:00+0000',
			'metazone' => 'Casey',
		],
		4 => [
			'ts' => '2012-02-21T17:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		5 => [
			'ts' => '2016-10-21T16:00:00+0000',
			'metazone' => 'Casey',
		],
		6 => [
			'ts' => '2018-03-10T17:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		7 => [
			'ts' => '2018-10-06T20:00:00+0000',
			'metazone' => 'Casey',
		],
		8 => [
			'ts' => '2019-03-16T16:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		9 => [
			'ts' => '2019-10-03T19:00:00+0000',
			'metazone' => 'Casey',
		],
		10 => [
			'ts' => '2020-03-07T16:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		11 => [
			'ts' => '2020-10-03T16:01:00+0000',
			'metazone' => 'Casey',
		],
		12 => [
			'ts' => '2021-03-13T13:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		13 => [
			'ts' => '2021-10-02T16:01:00+0000',
			'metazone' => 'Casey',
		],
		14 => [
			'ts' => '2022-03-12T13:00:00+0000',
			'metazone' => 'Australia_Western',
		],
		15 => [
			'ts' => '2022-10-01T16:01:00+0000',
			'metazone' => 'Casey',
		],
		16 => [
			'ts' => '2023-03-08T16:00:00+0000',
			'metazone' => 'Australia_Western',
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
			'DTSTART' => '15821015T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690101T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0800',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '20091018T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100305T020000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '20111028T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120222T040000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20161022T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '20180311T040000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20181007T040000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '20190317T030000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20191004T030000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '20200308T030000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20201004T000100',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '20210314T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '20211003T000100',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '20220313T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '20221002T000100',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '20230309T030000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
	];
}
