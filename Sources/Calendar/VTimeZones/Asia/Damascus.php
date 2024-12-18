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
 * Asia/Damascus
 */
class Damascus extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Damascus';

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
			'DTSTART' => '19200101T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+022512',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200418T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=3SU;UNTIL=19230415T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19201003T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19231007T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19620429T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19621001T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19630501T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19650501T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19630930T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19641001T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19650930T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19660424T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19661001T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19761001T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670501T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYMONTHDAY=1;UNTIL=19780501T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770901T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=1;UNTIL=19780901T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19830409T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=9;UNTIL=19840409T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19831001T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19841001T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860216T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19861009T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870301T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19871031T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=31;UNTIL=19881031T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19880315T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19890331T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19891001T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900401T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900930T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19910401T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19911001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19921001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19920408T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19930326T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930925T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19940401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19960401T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '19941001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=20051001T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970331T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1MO;UNTIL=19980330T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19990401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=20060401T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '20060922T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070330T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		35 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071102T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20080404T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		37 => [
			'type' => 'STANDARD',
			'DTSTART' => '20081101T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		38 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090327T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		39 => [
			'type' => 'STANDARD',
			'DTSTART' => '20091030T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1FR;UNTIL=20221028T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20100402T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1FR;UNTIL=20110401T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		41 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120330T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1FR;UNTIL=20220325T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '20221028T000000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0300',
		],
	];
}

?>