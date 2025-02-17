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
 * Europe/Brussels
 */
class Brussels extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Brussels';

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
			'DTSTART' => '18920501T001730',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+001730',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19141108T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19160501T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19161001T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170416T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=3MO;UNTIL=19180415T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19170917T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=3MO;UNTIL=19180916T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181111T120000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190301T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181006T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19191004T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200214T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19201024T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19210314T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19211026T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19220325T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19221008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19271001T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19230421T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19240329T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19250404T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19260417T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19270409T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280414T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19281007T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=19381002T020000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19290421T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19300413T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19310419T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19320403T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330326T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19340408T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19350331T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19360419T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19370404T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380327T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390416T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '19391119T030000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400225T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400520T030000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		40 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440917T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		41 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450916T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		43 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460519T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		44 => [
			'type' => 'STANDARD',
			'DTSTART' => '19461007T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770831T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19800406T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		46 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770925T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		47 => [
			'type' => 'STANDARD',
			'DTSTART' => '19781001T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		48 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800227T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		49 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810826T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		50 => [
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