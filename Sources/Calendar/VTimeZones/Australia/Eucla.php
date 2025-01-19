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

namespace SMF\Calendar\VTimeZones\Australia;

/**
 * Australia/Eucla
 */
class Eucla extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Australia/Eucla';

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
			'DTSTART' => '18951201T000000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+083528',
			'TZOFFSETTO' => '+0845',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19170101T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19170325T030000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420101T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420329T030000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420927T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430328T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19440326T020000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19741027T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19750302T030000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19831030T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19840304T030000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19911117T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19920301T030000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20061203T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '20070325T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=20090329T020000',
			'TZNAME' => 'UTC+0845',
			'TZOFFSETFROM' => '+0945',
			'TZOFFSETTO' => '+0845',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20071028T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20081026T020000',
			'TZNAME' => 'UTC+0945',
			'TZOFFSETFROM' => '+0845',
			'TZOFFSETTO' => '+0945',
		],
	];
}

?>