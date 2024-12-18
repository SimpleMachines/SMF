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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Cambridge_Bay
 */
class Cambridge_Bay extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Cambridge_Bay';

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
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '-0700',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'MWT',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T170000',
			'TZNAME' => 'MPT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19720430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'MDT',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19721029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'MDT',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0600',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001029T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001105T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20010401T030000',
			'TZNAME' => 'MDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0600',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'MDT',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0600',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0700',
		],
	];
}

?>