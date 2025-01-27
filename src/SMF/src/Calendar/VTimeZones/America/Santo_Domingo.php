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
 * America/Santo_Domingo
 */
class Santo_Domingo extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Santo_Domingo';

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
			'DTSTART' => '19330401T120000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0440',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19661030T000000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19670228T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19691026T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19731028T000000',
			'TZNAME' => 'UTC-0430',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0430',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19700221T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0430',
			'TZOFFSETTO' => '-0500',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19710120T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0430',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19720121T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYMONTHDAY=21;UNTIL=19740121T000000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0430',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '20001203T010000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>