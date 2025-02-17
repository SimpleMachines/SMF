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
 * Europe/Paris
 */
class Paris extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Paris';

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
			'DTSTART' => '19110311T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+000921',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19160614T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19161002T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19191005T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170324T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180309T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190301T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200214T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19201024T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19210314T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19211026T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19220325T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19221008T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19381001T230000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19230526T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19240329T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19250404T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19260417T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19270409T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280414T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19290420T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19300412T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19310418T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19320402T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330325T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19340407T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19350330T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19360418T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19370403T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380326T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390415T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '19391119T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400225T020000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400614T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		32 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440825T000000',
			'TZNAME' => 'WEMT',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0200',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19441008T010000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		38 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450402T020000',
			'TZNAME' => 'WEMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		39 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450916T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		40 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760328T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		41 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760926T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		42 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770831T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19800406T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		43 => [
			'type' => 'STANDARD',
			'DTSTART' => '19770925T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		44 => [
			'type' => 'STANDARD',
			'DTSTART' => '19781001T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		45 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800227T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		46 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810826T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		47 => [
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