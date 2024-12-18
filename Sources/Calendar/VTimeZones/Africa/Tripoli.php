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

namespace SMF\Calendar\VTimeZones\Africa;

/**
 * Africa/Tripoli
 */
class Tripoli extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Tripoli';

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
			'DTSTART' => '19200101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+005244',
			'TZOFFSETTO' => '+0100',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19511014T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19520101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19531009T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19540101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19550930T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19560101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19590101T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19820101T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19820401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19840401T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19821001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19851001T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850406T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		12 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860404T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19861003T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19890401T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19871001T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=1;UNTIL=19891001T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900504T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960930T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19970404T000000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19971004T000000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0200',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '20121110T020000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20130329T010000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '20131025T020000',
			'TZNAME' => 'EET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0200',
		],
	];
}

?>