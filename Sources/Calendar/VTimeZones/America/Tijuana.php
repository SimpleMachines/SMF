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
 * America/Tijuana
 */
class Tijuana extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Tijuana';

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
			'DTSTART' => '19211231T231156',
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '-074804',
			'TZOFFSETTO' => '-0700',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19240101T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19270610T000000',
			'TZNAME' => 'MST',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19301115T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19310401T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		5 => [
			'type' => 'STANDARD',
			'DTSTART' => '19310930T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		6 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420424T000000',
			'TZNAME' => 'PWT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T160000',
			'TZNAME' => 'PPT',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0700',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19451115T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480405T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19490114T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		11 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19500501T000000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500924T000000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		13 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510429T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		14 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		15 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19520427T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		16 => [
			'type' => 'STANDARD',
			'DTSTART' => '19520928T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		17 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19500430T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19660424T010000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		18 => [
			'type' => 'STANDARD',
			'DTSTART' => '19500924T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19610924T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		19 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19760425T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19671029T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		21 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		22 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19960407T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20000402T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		23 => [
			'type' => 'STANDARD',
			'DTSTART' => '19961027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20001029T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20020407T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20220403T020000',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '20021027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20221030T020000',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'PDT',
			'TZOFFSETFROM' => '-0800',
			'TZOFFSETTO' => '-0700',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'PST',
			'TZOFFSETFROM' => '-0700',
			'TZOFFSETTO' => '-0800',
		],
	];
}

?>