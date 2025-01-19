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

namespace SMF\Calendar\VTimeZones\Pacific;

/**
 * Pacific/Efate
 */
class Efate extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Efate';

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
			'TZOFFSETFROM' => '+111316',
			'TZOFFSETTO' => '+1100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731222T230000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740331T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19830925T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=4SA;UNTIL=19910929T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840325T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=4SA;UNTIL=19910324T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920126T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYDAY=4SA;UNTIL=19930124T000000',
			'TZNAME' => 'UTC+11',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1100',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19921025T000000',
			'TZNAME' => 'UTC+12',
			'TZOFFSETFROM' => '+1100',
			'TZOFFSETTO' => '+1200',
		],
	];
}

?>