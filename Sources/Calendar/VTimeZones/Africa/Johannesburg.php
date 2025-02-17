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

namespace SMF\Calendar\VTimeZones\Africa;

/**
 * Africa/Johannesburg
 */
class Johannesburg extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Johannesburg';

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
			'DTSTART' => '18920208T000000',
			'TZNAME' => 'SAST',
			'TZOFFSETFROM' => '+0152',
			'TZOFFSETTO' => '+0130',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19030301T000000',
			'TZNAME' => 'SAST',
			'TZOFFSETFROM' => '+0130',
			'TZOFFSETTO' => '+0200',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420920T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=3SU;UNTIL=19430919T020000',
			'TZNAME' => 'SAST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430321T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19440319T020000',
			'TZNAME' => 'SAST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
	];
}

?>