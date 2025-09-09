<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Manila
 */
class Manila extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Manila';

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
			'DTSTART' => '18990906T120352',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+080352',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19361101T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19370116T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19411216T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420212T000000',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0900',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450304T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0900',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451201T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19540412T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19540605T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770328T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770922T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900521T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900729T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}
