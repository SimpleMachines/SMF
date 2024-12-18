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
 * Asia/Amman
 */
class Amman extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Amman';

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
			'DTSTART' => '19310101T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+022344',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19730606T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19731001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19751001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740501T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19770501T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19761101T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19771001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780430T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780930T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850401T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19851001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860404T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1FR;UNTIL=19880401T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19861003T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1FR;UNTIL=19901005T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19890508T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900427T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19910417T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910927T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19920410T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19921002T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1FR;UNTIL=19931001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19930402T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1FR;UNTIL=19980403T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940916T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19950915T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=3FR;UNTIL=19980918T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19990701T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990924T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1FR;UNTIL=20020927T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000330T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1TH;UNTIL=20010329T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20020329T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1TH;UNTIL=20120330T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '20031024T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '20041015T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20050930T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '20061027T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1FR;UNTIL=20111028T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131220T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20140328T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1TH;UNTIL=20210326T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '20141031T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1FR;UNTIL=20221028T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20220225T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '20221028T010000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0300',
		],
	];
}

?>