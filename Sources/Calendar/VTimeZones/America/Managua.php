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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Managua
 */
class Managua extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Managua';

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
			'DTSTART' => '19340623T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-054512',
			'TZOFFSETTO' => '-0600',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19730501T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750216T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790318T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19800316T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790625T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=6;BYDAY=MO;BYMONTHDAY=23,24,25,26,27,28,29;UNTIL=19800623T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920101T040000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920924T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930101T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20050410T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20051002T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20060430T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20061001T010000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
	];
}

?>