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

namespace SMF\Calendar\VTimeZones\Antarctica;

/**
 * Antarctica/Casey
 */
class Casey extends \SMF\Calendar\VTimeZone
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
	 * Data for the VTIMEZONE components.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public array $components = [
		0 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690101T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '20091018T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100305T020000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20111028T020000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120222T040000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '20161022T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20180311T040000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '20181007T040000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20190317T030000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '20191004T030000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20200308T030000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '20201004T000100',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20210314T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '20211003T000100',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '20220313T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '20221002T000100',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+1100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '20230309T030000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>