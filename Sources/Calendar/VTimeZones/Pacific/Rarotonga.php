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
 * Pacific/Rarotonga
 */
class Rarotonga extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Pacific/Rarotonga';

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
			'DTSTART' => '19521016T000000',
			'TZNAME' => 'UTC-1030',
			'TZOFFSETFROM' => '-103904',
			'TZOFFSETTO' => '-1030',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19781112T000000',
			'TZNAME' => 'UTC-0930',
			'TZOFFSETFROM' => '-1030',
			'TZOFFSETTO' => '-0930',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790304T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU;UNTIL=19910303T000000',
			'TZNAME' => 'UTC-10',
			'TZOFFSETFROM' => '-0930',
			'TZOFFSETTO' => '-1000',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19791028T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19901028T000000',
			'TZNAME' => 'UTC-0930',
			'TZOFFSETFROM' => '-1000',
			'TZOFFSETTO' => '-0930',
		],
	];
}

?>