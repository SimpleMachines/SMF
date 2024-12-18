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
 * Asia/Jakarta
 */
class Jakarta extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Jakarta';

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
			'DTSTART' => '19321101T000000',
			'TZNAME' => 'UTC+0730',
			'TZOFFSETFROM' => '+0720',
			'TZOFFSETTO' => '+0730',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420323T000000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0730',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450923T000000',
			'TZNAME' => 'UTC+0730',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0730',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19480501T000000',
			'TZNAME' => 'UTC+08',
			'TZOFFSETFROM' => '+0730',
			'TZOFFSETTO' => '+0800',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500501T000000',
			'TZNAME' => 'UTC+0730',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0730',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640101T000000',
			'TZNAME' => 'WIB',
			'TZOFFSETFROM' => '+0730',
			'TZOFFSETTO' => '+0700',
		],
	];
}

?>