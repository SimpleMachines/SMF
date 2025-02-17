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
 * America/Lima
 */
class Lima extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Lima';

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
			'DTSTART' => '19080728T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-050836',
			'TZOFFSETTO' => '-0500',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19380401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19380925T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19390924T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19390326T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=SU;BYMONTHDAY=24,25,26,27,28,29,30;UNTIL=19400324T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19860101T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=1;BYMONTHDAY=1;UNTIL=19870101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19860401T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1;UNTIL=19870401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19900101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19900401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19940101T000000',
			'TZNAME' => 'UTC-04',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19940401T000000',
			'TZNAME' => 'UTC-05',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
	];
}

?>