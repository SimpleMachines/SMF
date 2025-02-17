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
 * Pacific/Apia
 */
class Apia extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Apia';

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
			'DTSTART' => '19110101T000000',
			'TZNAME' => 'UTC-1130',
			'TZOFFSETFROM' => '-112656',
			'TZOFFSETTO' => '-1130',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500101T000000',
			'TZNAME' => 'UTC-11',
			'TZOFFSETFROM' => '-1130',
			'TZOFFSETTO' => '-1100',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20100926T000000',
			'TZNAME' => 'UTC-10',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110402T040000',
			'TZNAME' => 'UTC-11',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-1100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110924T030000',
			'TZNAME' => 'UTC-10',
			'TZOFFSETFROM' => '-1100',
			'TZOFFSETTO' => '-1000',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20111230T000000',
			'TZNAME' => 'UTC+14',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '+1400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120401T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20210404T040000',
			'TZNAME' => 'UTC+13',
			'TZOFFSETFROM' => '+1400',
			'TZOFFSETTO' => '+1300',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=20200927T030000',
			'TZNAME' => 'UTC+14',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1400',
		],
	];
}

?>