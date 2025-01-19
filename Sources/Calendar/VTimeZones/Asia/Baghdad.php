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
 * Asia/Baghdad
 */
class Baghdad extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Baghdad';

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
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+025736',
			'TZOFFSETTO' => '+0300',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19820501T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19821001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19841001T000000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19830331T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19840401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19850401T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19850929T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19900930T010000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860330T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19900325T010000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19910401T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=20070401T030000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0300',
			'TZOFFSETTO' => '+0400',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19911001T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=20071001T030000',
			'TZNAME' => 'UTC+03',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0300',
		],
	];
}

?>