<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Ho_Chi_Minh
 */
class Ho_Chi_Minh extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Ho_Chi_Minh';

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
			'DTSTART' => '19110501T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+070630',
			'TZOFFSETTO' => '+0700',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421231T230000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0800',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450314T230000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450902T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0700',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19470401T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19550701T010000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0700',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19591231T230000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0700',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750613T000000',
			'TZNAME' => 'UTC+07',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0700',
		],
	];
}

?>