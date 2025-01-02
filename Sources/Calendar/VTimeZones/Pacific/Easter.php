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

namespace SMF\Calendar\VTimeZones\Pacific;

/**
 * Pacific/Easter
 */
class Easter extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Easter';

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
			'DTSTART' => '19320901T000000',
			'TZNAME' => 'UTC-07',
			'TZOFFSETFROM' => '-071728',
			'TZOFFSETTO' => '-0700',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19681102T210000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690329T210000',
			'TZNAME' => 'UTC-07',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19691122T210000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700328T210000',
			'TZNAME' => 'UTC-07',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19671126T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19721015T040000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19710313T210000',
			'TZNAME' => 'UTC-07',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690427T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19860309T030000',
			'TZNAME' => 'UTC-07',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19730929T210000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19711128T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19871011T040000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19720426T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19871011T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19690924T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19860309T030000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19870411T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19850925T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19900311T030000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860423T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19891015T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900915T220000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19880921T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19960310T030000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19890426T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=19971012T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970329T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19980314T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19980926T220000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19990403T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970423T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20101010T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970924T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20070311T030000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		24 => [
			'type' => 'STANDARD',
			'DTSTART' => '20080329T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '20090314T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		26 => [
			'type' => 'STANDARD',
			'DTSTART' => '20100403T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20110507T220000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20110820T220000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '20091111T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=23,24,25,26,27,28,29;UNTIL=20140427T030000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20100317T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=20140907T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131127T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20180513T030000',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20140226T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=8;BYDAY=SU;BYMONTHDAY=9,10,11,12,13,14,15;UNTIL=20180812T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '20161019T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8',
			'TZNAME' => 'UTC-06',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20170322T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8;UNTIL=20210905T040000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20220910T220000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20210317T040000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=SU;BYMONTHDAY=2,3,4,5,6,7,8',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
	];
}

?>