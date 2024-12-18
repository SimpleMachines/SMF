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

namespace SMF\Calendar\VTimeZones\Australia;

/**
 * Australia/Adelaide
 */
class Adelaide extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Australia/Adelaide';

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
			'DTSTART' => '18950201T000000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+091420',
			'TZOFFSETTO' => '+0900',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '18990501T000000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0930',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170101T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19170325T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420101T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420329T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420927T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430328T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19440326T020000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19431003T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19711031T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19851027T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19720227T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19730304T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19850303T020000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860316T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=19900318T020000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19861019T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19871025T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20071028T020000',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910303T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920322T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930307T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940320T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19950326T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20050327T020000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060402T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070325T030000',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080406T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU',
			'TZNAME' => 'ACST',
			'TZOFFSETFROM' => '+1030',
			'TZOFFSETTO' => '+0930',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20081005T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU',
			'TZNAME' => 'ACDT',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+1030',
		],
	];
}

?>