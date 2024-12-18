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
 * America/Scoresbysund
 */
class Scoresbysund extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Scoresbysund';

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
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-012752',
			'TZOFFSETTO' => '-0200',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770403T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=19800406T020000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '-0100',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T030000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T020000',
			'TZNAME' => 'UTC-02',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0200',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19810329T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '-0200',
			'TZOFFSETTO' => '+0000',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790503T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19950924T010000',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '-0100',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19801030T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '+0000',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19960530T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '-0100',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19800602T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'TZNAME' => 'UTC-01',
			'TZOFFSETFROM' => '-0100',
			'TZOFFSETTO' => '-0100',
		],
		8 => [
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