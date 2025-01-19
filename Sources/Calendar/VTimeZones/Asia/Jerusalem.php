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
 * Asia/Jerusalem
 */
class Jerusalem extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Jerusalem';

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
			'DTSTART' => '19180101T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+022040',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400601T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19401001T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19401117T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430828T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=31;UNTIL=19461101T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440126T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=31;UNTIL=19440401T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460210T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=15;UNTIL=19460416T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480523T020000',
			'TZNAME' => 'IDDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0400',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480901T040000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19490828T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=31;UNTIL=19491101T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19490501T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19500416T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500915T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510401T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19511111T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19520420T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19521019T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19530412T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19530913T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19540613T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19540912T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19550612T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19550911T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19560603T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19560930T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19570428T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19570922T030000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740707T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741013T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19750420T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750831T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800803T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		32 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800914T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19840506T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840826T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850414T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '19850901T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860518T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860907T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870415T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		40 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870913T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		41 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19880410T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '19880904T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		43 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19890430T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		44 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890903T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900325T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		46 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900826T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		47 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19910324T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		48 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910901T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		49 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19920329T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		50 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920906T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		51 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19930402T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		52 => [
			'type' => 'STANDARD',
			'DTSTART' => '19930905T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		53 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19940401T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		54 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940828T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		55 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19950331T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		56 => [
			'type' => 'STANDARD',
			'DTSTART' => '19950903T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		57 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19960315T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		58 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960916T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		59 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970321T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		60 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970914T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		61 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980320T000000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		62 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980906T000000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		63 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19990402T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		64 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990903T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		65 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000414T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		66 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001006T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		67 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20010409T010000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		68 => [
			'type' => 'STANDARD',
			'DTSTART' => '20010924T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		69 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20020329T010000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		70 => [
			'type' => 'STANDARD',
			'DTSTART' => '20021007T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		71 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20030328T010000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		72 => [
			'type' => 'STANDARD',
			'DTSTART' => '20031003T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		73 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20040407T010000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		74 => [
			'type' => 'STANDARD',
			'DTSTART' => '20040922T010000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		75 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20050401T020000',
			'RRULE' => 'FREQ=YEARLY;BYDAY=FR;BYYEARDAY=-281,-280,-279,-278,-277,-276,-275;BYSETPOS=1;UNTIL=20120330T020000',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		76 => [
			'type' => 'STANDARD',
			'DTSTART' => '20051009T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		77 => [
			'type' => 'STANDARD',
			'DTSTART' => '20061001T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		78 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070916T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		79 => [
			'type' => 'STANDARD',
			'DTSTART' => '20081005T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		80 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090927T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		81 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100912T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		82 => [
			'type' => 'STANDARD',
			'DTSTART' => '20111002T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		83 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120923T020000',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		84 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130329T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=FR;BYMONTHDAY=23,24,25,26,27,28,29',
			'TZNAME' => 'IDT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		85 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'IST',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
	];
}

?>