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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Hong_Kong
 */
class Hong_Kong extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Hong_Kong';

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
			'DTSTART' => '19041030T003642',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+073642',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410615T030000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19411001T040000',
			'TZNAME' => 'HKWT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0830',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19411225T000000',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+0830',
			'TZOFFSETTO' => '+0900',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451118T020000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460421T000000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461201T043000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19470413T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19471130T043000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480502T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19481031T043000',
			'RRULE' => 'FREQ=YEARLY;BYDAY=SU;BYYEARDAY=-64,-63,-62,-61,-60,-59,-58;BYSETPOS=1;UNTIL=19521102T033000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19490403T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19530405T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19531101T033000',
			'RRULE' => 'FREQ=YEARLY;BYDAY=SU;BYYEARDAY=-62,-61,-60,-59,-58,-57,-56;BYSETPOS=1;UNTIL=19641101T033000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19540321T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=18,19,20,21,22,23,24;UNTIL=19640322T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650418T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19760418T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19651017T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19761017T033000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731230T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790513T033000',
			'TZNAME' => 'HKST',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19791021T033000',
			'TZNAME' => 'HKT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>