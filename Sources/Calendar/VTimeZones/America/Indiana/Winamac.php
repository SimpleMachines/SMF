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

namespace SMF\Calendar\VTimeZones\America\Indiana;

/**
 * America/Indiana/Winamac
 */
class Winamac extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Indiana/Winamac';

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
			'DTSTART' => '18831118T121335',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-054625',
			'TZOFFSETTO' => '-0600',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180331T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU;UNTIL=19190330T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19191026T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
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
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19600424T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460929T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19540926T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19551030T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19561028T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19570929T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19600925T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19610430T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0500',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'EDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0400',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0500',
		],
	];
}

?>