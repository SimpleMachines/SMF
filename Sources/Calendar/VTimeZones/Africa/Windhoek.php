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
 * Africa/Windhoek
 */
class Windhoek extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Windhoek';

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
			'TZNAME' => 'UTC+0130',
			'TZOFFSETFROM' => '+010824',
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
			'TZNAME' => 'SAST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430321T020000',
			'TZNAME' => 'SAST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900321T000000',
			'TZNAME' => 'CAT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19940321T000000',
			'TZNAME' => 'WAT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940904T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=1SU;UNTIL=20170903T020000',
			'TZNAME' => 'CAT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19950402T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20170402T020000',
			'TZNAME' => 'WAT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
	];
}

?>