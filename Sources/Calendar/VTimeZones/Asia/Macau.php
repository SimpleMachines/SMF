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
 * Asia/Macau
 */
class Macau extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Macau';

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
			'DTSTART' => '19041030T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+073410',
			'TZOFFSETTO' => '+0800',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19411221T230000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420430T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=30;UNTIL=19430430T230000',
			'TZNAME' => 'UTC+10',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421117T230000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430930T230000',
			'TZNAME' => 'UTC+09',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460430T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461001T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19470419T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19471201T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480502T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19481101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19490402T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SA;UNTIL=19500401T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19491030T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SA;UNTIL=19501028T230000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510331T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19511029T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19520405T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SA;UNTIL=19530404T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19521102T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19531101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SA;UNTIL=19541030T230000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19540320T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SA;BYMONTHDAY=17,18,19,20,21,22,23;UNTIL=19560317T230000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19551106T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19561104T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU;UNTIL=19641101T033000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19570324T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=18,19,20,21,22,23,24;UNTIL=19640322T033000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19650418T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19730422T033000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19651017T023000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19661016T023000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671022T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19761017T033000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731230T033000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19750420T033000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=16,17,18,19,20,21,22;UNTIL=19760418T033000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790513T033000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '+0800',
			'TZOFFSETTO' => '+0900',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '19791021T033000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+0800',
		],
	];
}

?>