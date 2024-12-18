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
 * Asia/Seoul
 */
class Seoul extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Seoul';

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
			'DTSTART' => '19080401T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+082752',
			'TZOFFSETTO' => '+0830',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19120101T000000',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+0830',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450908T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0900',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480601T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19480913T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19490403T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19490911T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SA;BYMONTHDAY=7,8,9,10,11,12,13;UNTIL=19510909T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19500401T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510506T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19540321T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0830',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19550505T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0830',
			'TZOFFSETTO' => '+0930',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19550909T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+0830',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19560520T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0830',
			'TZOFFSETTO' => '+0930',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19560930T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+0830',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19570505T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=1SU;UNTIL=19600501T000000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0830',
			'TZOFFSETTO' => '+0930',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19570922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SA;BYMONTHDAY=17,18,19,20,21,22,23;UNTIL=19600918T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+0930',
			'TZOFFSETTO' => '+0830',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19610810T000000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+0830',
			'TZOFFSETTO' => '+0900',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870510T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=2SU;UNTIL=19880508T020000',
			'TZNAME' => 'KDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19871011T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=2SU;UNTIL=19881009T030000',
			'TZNAME' => 'KST',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
	];
}

?>