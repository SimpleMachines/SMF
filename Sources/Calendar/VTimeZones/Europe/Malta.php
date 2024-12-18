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
 * Europe/Malta
 */
class Malta extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Malta';

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
			'DTSTART' => '18931102T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+005804',
			'TZOFFSETTO' => '+0100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19160604T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19161001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=30;UNTIL=19171001T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170401T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180310T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181007T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190302T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19191005T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200321T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19200919T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400615T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440917T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450915T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460317T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461006T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19470316T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19471005T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480229T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19481003T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19660522T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=4SU;UNTIL=19680526T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19660925T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19670924T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=4SU;UNTIL=19690928T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19690601T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19700531T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700927T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19710523T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=4SU;UNTIL=19720528T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '19710926T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		32 => [
			'type' => 'STANDARD',
			'DTSTART' => '19721001T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19730331T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '19730929T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740421T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740916T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19750420T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=3SU;UNTIL=19790415T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750921T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=3SU;UNTIL=19800921T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800331T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810826T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		41 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800227T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970326T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
	];
}

?>