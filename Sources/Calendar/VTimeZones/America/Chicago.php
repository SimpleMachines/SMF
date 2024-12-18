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
 * America/Chicago
 */
class Chicago extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Chicago';

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
			'DTSTART' => '18831118T120924',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-055036',
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
			'DTSTART' => '19200613T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19201031T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19211030T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19210327T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19220430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19660424T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		7 => [
			'type' => 'STANDARD',
			'DTSTART' => '19220924T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19540926T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19360301T020000',
			'TZNAME' => 'EST',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		9 => [
			'type' => 'STANDARD',
			'DTSTART' => '19361115T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'CWT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T180000',
			'TZNAME' => 'CPT',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0500',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19551030T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19661030T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19670430T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740106T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19750223T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760425T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		20 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'CDT',
			'TZOFFSETFROM' => '-0600',
			'TZOFFSETTO' => '-0500',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'CST',
			'TZOFFSETFROM' => '-0500',
			'TZOFFSETTO' => '-0600',
		],
	];
}

?>