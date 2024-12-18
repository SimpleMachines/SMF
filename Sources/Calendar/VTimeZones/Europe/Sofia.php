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
 * Europe/Sofia
 */
class Sofia extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Sofia';

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
			'DTSTART' => '18941130T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+015656',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421102T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430329T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19431004T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1MO;UNTIL=19450402T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19441002T030000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450402T030000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790331T230000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19791001T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800405T230000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SA;UNTIL=19820403T230000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800929T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19810927T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19820926T030000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19820123T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970823T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
	];
}

?>