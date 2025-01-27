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
 * America/Nuuk
 */
class Nuuk extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Nuuk';

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
			'DTSTART' => '19160728T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-032656',
			'TZOFFSETTO' => '-0300',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800406T020000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19780707T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800104T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19950804T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0300',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '20230325T220000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0200',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800602T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960101T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
	];
}

?>