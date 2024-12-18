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
 * Asia/Jayapura
 */
class Jayapura extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Jayapura';

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
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+092248',
			'TZOFFSETTO' => '+0900',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440901T000000',
			'TZNAME' => 'UTC+0930',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0930',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640101T000000',
			'TZNAME' => 'WIT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+0900',
		],
	];
}

?>