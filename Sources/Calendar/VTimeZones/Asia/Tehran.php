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
 * Asia/Tehran
 */
class Tehran extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Tehran';

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
			'DTSTART' => '19350613T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+032544',
			'TZOFFSETTO' => '+0330',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770321T230000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19771021T000000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780325T000000',
			'TZNAME' => 'UTC+05',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780805T010000',
			'TZNAME' => 'UTC+04',
			'TZOFFSETFROM' => '+0500',
			'TZOFFSETTO' => '+0400',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790101T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0400',
			'TZOFFSETTO' => '+0330',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19790527T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790919T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800923T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19910503T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19910922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=19950922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19920322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=19950322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19960321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=19990322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=19990922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20000321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '20000921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20010322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=20030322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '20010922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=20030922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20040321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '20040921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20050322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20050922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		25 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20080321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		27 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20090322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=20110322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		28 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=20110922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		29 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20120321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		30 => [
			'type' => 'STANDARD',
			'DTSTART' => '20120921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		31 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=20150322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		32 => [
			'type' => 'STANDARD',
			'DTSTART' => '20130922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=20150922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		33 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20160321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		34 => [
			'type' => 'STANDARD',
			'DTSTART' => '20160921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20170322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=20190322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		36 => [
			'type' => 'STANDARD',
			'DTSTART' => '20170922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=20190922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20200321T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '20200921T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20210322T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=21;UNTIL=20220322T000000',
			'TZNAME' => 'UTC+0430',
			'TZOFFSETFROM' => '+0330',
			'TZOFFSETTO' => '+0430',
		],
		40 => [
			'type' => 'STANDARD',
			'DTSTART' => '20210922T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=21;UNTIL=20220922T000000',
			'TZNAME' => 'UTC+0330',
			'TZOFFSETFROM' => '+0430',
			'TZOFFSETTO' => '+0330',
		],
	];
}

?>