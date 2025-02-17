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

namespace SMF\Calendar\VTimeZones\Europe;

/**
 * Europe/Tallinn
 */
class Tallinn extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Tallinn';

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
			'DTSTART' => '19180201T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0139',
			'TZOFFSETTO' => '+0100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170416T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=3MO;UNTIL=19180415T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19170917T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=3MO;UNTIL=19180916T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19190701T000000',
			'TZNAME' => 'TMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0139',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19210501T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0139',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19400806T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19410915T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440922T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840401T000000',
			'TZNAME' => 'MSD',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19811001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19831001T000000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'MSK',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850331T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20100328T020000',
			'TZNAME' => 'MSD',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19890326T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0300',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970823T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19820123T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19991031T040000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
	];
}

?>