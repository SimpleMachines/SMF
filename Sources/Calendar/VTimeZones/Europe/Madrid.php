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
 * Europe/Madrid
 */
class Madrid extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Madrid';

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
			'DTSTART' => '19001231T234516',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+001444',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180415T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181007T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=6;UNTIL=19191007T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19190406T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19240416T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19241005T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19260417T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19261003T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19291006T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19270409T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280415T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19290420T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19370616T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19371003T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380402T230000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380430T230000',
			'TZNAME' => 'WEMT',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19381003T000000',
			'TZNAME' => 'WEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19391008T010000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0000',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19400316T230000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420502T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420901T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430417T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SA;BYMONTHDAY=13,14,15,16,17,18,19;UNTIL=19460413T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431003T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19441001T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19460929T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19490430T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19491002T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740413T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SA;BYMONTHDAY=12,13,14,15,16,17,18;UNTIL=19750412T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741006T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19751005T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760327T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '19760926T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19770925T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770402T230000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '19781001T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770831T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19800406T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800227T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810826T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		35 => [
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