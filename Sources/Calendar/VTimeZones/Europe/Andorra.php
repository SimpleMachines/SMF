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

namespace SMF\Calendar\VTimeZones\Europe;

/**
 * Europe/Andorra
 */
class Andorra extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Europe/Andorra';

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
			'DTSTART' => '19010101T000000',
			'TZNAME' => 'WET',
			'TZOFFSETFROM' => '+000604',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460930T000000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+0100',
		],
		2 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19850331T020000',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800227T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810826T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'CEST',
			'TZOFFSETFROM' => '+0100',
			'TZOFFSETTO' => '+0200',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19970326T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'CET',
			'TZOFFSETFROM' => '+0200',
			'TZOFFSETTO' => '+0100',
		],
	];
}

?>