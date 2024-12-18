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
 * Europe/Helsinki
 */
class Helsinki extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Helsinki';

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
			'DTSTART' => '19210501T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+013949',
			'TZOFFSETTO' => '+0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420403T000000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19421004T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19820328T020000',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19810927T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19820926T030000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19820123T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'EEST',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0300',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800726T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0200',
		],
		7 => [
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