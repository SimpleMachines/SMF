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

namespace SMF\Calendar\VTimeZones\America\Argentina;

/**
 * America/Argentina/Rio_Gallegos
 */
class Rio_Gallegos extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Argentina/Rio_Gallegos';

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
			'DTSTART' => '19200501T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-041648',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19301201T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19310401T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19311015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19320301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19400301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19321101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYMONTHDAY=1;UNTIL=19391101T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400701T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19410615T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19411015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430801T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19431015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19461001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19631001T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19631215T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19640301T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;UNTIL=19660301T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19641015T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=15;UNTIL=19661015T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19670402T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19671001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19681006T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19680407T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19690406T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19691005T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740123T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740501T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19881201T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '19890305T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19930307T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19891015T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=3SU;UNTIL=19921018T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19991003T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000303T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '20040601T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20040620T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20071230T000000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080316T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=3SU;UNTIL=20090315T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
	];
}

?>