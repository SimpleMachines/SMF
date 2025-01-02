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

namespace SMF\Calendar\VTimeZones\America;

/**
 * America/Belize
 */
class Belize extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Belize';

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
			'DTSTART' => '19120401T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-055248',
			'TZOFFSETTO' => '-0600',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19181006T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19411005T000000',
			'TZNAME' => 'UTC-0530',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0530',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19190209T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=2SA;UNTIL=19420215T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0530',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420628T000000',
			'TZNAME' => 'CWT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T180000',
			'TZNAME' => 'CPT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0500',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451216T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19471005T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SA;UNTIL=19671008T000000',
			'TZNAME' => 'UTC-0530',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0530',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19480215T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=2;BYDAY=2SA;UNTIL=19680211T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0530',
			'TZOFFSETTO' => '-0600',
		],
		8 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19731205T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19740209T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19821218T000000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19830212T000000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
	];
}

?>