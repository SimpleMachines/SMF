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

namespace SMF\Calendar\VTimeZones\Europe;

/**
 * Europe/Chisinau
 */
class Chisinau extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Chisinau';

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
			'DTSTART' => '19310724T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+014424',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19320521T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19321002T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19391001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330402T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=19390402T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400815T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410717T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440824T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840401T000000',
			'TZNAME' => 'MSD',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19811001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19831001T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850331T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20100328T020000',
			'TZNAME' => 'MSD',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900506T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970330T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19971026T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
	];
}

?>