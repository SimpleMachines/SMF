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

namespace SMF\Calendar\VTimeZones\Pacific;

/**
 * Pacific/Noumea
 */
class Noumea extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Noumea';

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
			'DTSTART' => '19120113T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+110548',
			'TZOFFSETTO' => '+1100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19771204T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=12;BYDAY=1SU;UNTIL=19781203T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780227T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=27;UNTIL=19790227T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19961201T020000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970302T030000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
	];
}

?>