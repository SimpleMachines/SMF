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
			'DTSTART' => '18990511T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0804',
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
			'DTSTART' => '19370201T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420501T000000',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19441101T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19540412T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19540701T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780322T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780921T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>