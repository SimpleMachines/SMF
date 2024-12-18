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
 * America/Rankin_Inlet
 */
class Rankin_Inlet extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Rankin_Inlet';

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
			'DTSTART' => '19570101T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '-0600',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19720430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19721029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001029T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0500',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20010401T030000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
	];
}

?>