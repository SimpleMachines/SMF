<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\America;

use SMF\Calendar\VTimeZone;

/**
 * America/Santo_Domingo
 */
class Santo_Domingo extends VTimeZone
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
	 * Data about which metazone label to use for this time zone at any given
	 * date and time.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public array $metazones = [
		0 => [
			'ts' => '1970-01-01T00:00:00+0000',
			'metazone' => 'Dominican',
		],
		1 => [
			'ts' => '1974-10-27T05:00:00+0000',
			'metazone' => 'Atlantic',
		],
		2 => [
			'ts' => '2000-10-29T06:00:00+0000',
			'metazone' => 'America_Eastern',
		],
		3 => [
			'ts' => '2000-12-03T06:00:00+0000',
			'metazone' => 'Atlantic',
		],
	];

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
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19731028T050000Z',
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
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYMONTHDAY=21;UNTIL=19740121T043000Z',
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
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T060000Z',
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
