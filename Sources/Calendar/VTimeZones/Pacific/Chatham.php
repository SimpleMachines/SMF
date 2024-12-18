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
 * Pacific/Chatham
 */
class Chatham extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Chatham';

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
			'DTSTART' => '18681102T000000',
			'TZNAME' => 'UTC+1215',
			'TZOFFSETFROM' => '+121348',
			'TZOFFSETTO' => '+1215',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460101T000000',
			'TZNAME' => 'UTC+1245',
			'TZOFFSETFROM' => '+1215',
			'TZOFFSETTO' => '+1245',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19741103T024500',
			'TZNAME' => 'UTC+1345',
			'TZOFFSETFROM' => '+1245',
			'TZOFFSETTO' => '+1345',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750223T034500',
			'TZNAME' => 'UTC+1245',
			'TZOFFSETFROM' => '+1345',
			'TZOFFSETTO' => '+1245',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19751026T024500',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19881030T024500',
			'TZNAME' => 'UTC+1345',
			'TZOFFSETFROM' => '+1245',
			'TZOFFSETTO' => '+1345',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760307T034500',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19890305T024500',
			'TZNAME' => 'UTC+1245',
			'TZOFFSETFROM' => '+1345',
			'TZOFFSETTO' => '+1245',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891008T024500',
			'TZNAME' => 'UTC+1345',
			'TZOFFSETFROM' => '+1245',
			'TZOFFSETTO' => '+1345',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900318T034500',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=20070318T024500',
			'TZNAME' => 'UTC+1245',
			'TZOFFSETFROM' => '+1345',
			'TZOFFSETTO' => '+1245',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19901007T024500',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=20061001T024500',
			'TZNAME' => 'UTC+1345',
			'TZOFFSETFROM' => '+1245',
			'TZOFFSETTO' => '+1345',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070930T024500',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU',
			'TZNAME' => 'UTC+1345',
			'TZOFFSETFROM' => '+1245',
			'TZOFFSETTO' => '+1345',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080406T034500',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU',
			'TZNAME' => 'UTC+1245',
			'TZOFFSETFROM' => '+1345',
			'TZOFFSETTO' => '+1245',
		],
	];
}

?>