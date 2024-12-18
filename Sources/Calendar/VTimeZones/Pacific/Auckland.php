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
 * Pacific/Auckland
 */
class Auckland extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Auckland';

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
			'TZNAME' => 'NZMT',
			'TZOFFSETFROM' => '+113904',
			'TZOFFSETTO' => '+1130',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19271106T020000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1130',
			'TZOFFSETTO' => '+1230',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19280304T020000',
			'TZNAME' => 'NZMT',
			'TZOFFSETFROM' => '+1230',
			'TZOFFSETTO' => '+1130',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19281014T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=19331008T020000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1130',
			'TZOFFSETTO' => '+1200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19290317T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19330319T020000',
			'TZNAME' => 'NZMT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1130',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19340429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19400428T020000',
			'TZNAME' => 'NZMT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1130',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19340930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19400929T020000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1130',
			'TZOFFSETTO' => '+1200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460101T000000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1200',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19741103T020000',
			'TZNAME' => 'NZDT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750223T030000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19751026T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19881030T020000',
			'TZNAME' => 'NZDT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760307T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19890305T020000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891008T020000',
			'TZNAME' => 'NZDT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900318T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=20070318T020000',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19901007T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=20061001T020000',
			'TZNAME' => 'NZDT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU',
			'TZNAME' => 'NZDT',
			'TZOFFSETFROM' => '+1200',
			'TZOFFSETTO' => '+1300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080406T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU',
			'TZNAME' => 'NZST',
			'TZOFFSETFROM' => '+1300',
			'TZOFFSETTO' => '+1200',
		],
	];
}

?>