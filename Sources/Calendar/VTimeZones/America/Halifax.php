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
 * America/Halifax
 */
class Halifax extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Halifax';

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
			'DTSTART' => '19020615T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-041424',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19160401T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19161001T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19180414T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19181027T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19200509T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19200829T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19210506T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19210905T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=5;UNTIL=19220905T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19220430T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		10 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19230506T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=1SU;UNTIL=19250503T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19230904T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		12 => [
			'type' => 'STANDARD',
			'DTSTART' => '19240915T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		13 => [
			'type' => 'STANDARD',
			'DTSTART' => '19250928T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		14 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19260516T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		15 => [
			'type' => 'STANDARD',
			'DTSTART' => '19260913T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		16 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19270501T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		17 => [
			'type' => 'STANDARD',
			'DTSTART' => '19270926T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		18 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19280513T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=2SU;UNTIL=19310510T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		19 => [
			'type' => 'STANDARD',
			'DTSTART' => '19280909T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		20 => [
			'type' => 'STANDARD',
			'DTSTART' => '19290903T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		21 => [
			'type' => 'STANDARD',
			'DTSTART' => '19300915T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		22 => [
			'type' => 'STANDARD',
			'DTSTART' => '19310928T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=MO;BYMONTHDAY=24,25,26,27,28,29,30;UNTIL=19320926T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		23 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19320501T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		24 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19330430T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		25 => [
			'type' => 'STANDARD',
			'DTSTART' => '19331002T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		26 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19340520T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		27 => [
			'type' => 'STANDARD',
			'DTSTART' => '19340916T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		28 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19350602T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		29 => [
			'type' => 'STANDARD',
			'DTSTART' => '19350930T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		30 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19360601T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		31 => [
			'type' => 'STANDARD',
			'DTSTART' => '19360914T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		32 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19370502T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=1SU;UNTIL=19380501T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		33 => [
			'type' => 'STANDARD',
			'DTSTART' => '19370927T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=MO;BYMONTHDAY=24,25,26,27,28,29,30;UNTIL=19410929T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		34 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19390528T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		35 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19400505T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=1SU;UNTIL=19410504T000000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		36 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420209T020000',
			'TZNAME' => 'AWT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		37 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19450814T200000',
			'TZNAME' => 'APT',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0300',
		],
		38 => [
			'type' => 'STANDARD',
			'DTSTART' => '19450930T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		39 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19460428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19490424T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		40 => [
			'type' => 'STANDARD',
			'DTSTART' => '19460929T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19490925T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		41 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19510429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19540425T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		42 => [
			'type' => 'STANDARD',
			'DTSTART' => '19510930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19540926T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		43 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19560429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19590426T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		44 => [
			'type' => 'STANDARD',
			'DTSTART' => '19560930T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU;UNTIL=19590927T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		45 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19620429T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19730429T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		46 => [
			'type' => 'STANDARD',
			'DTSTART' => '19621028T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=19731028T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		47 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19740428T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=-1SU;UNTIL=19860427T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		48 => [
			'type' => 'STANDARD',
			'DTSTART' => '19741027T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU;UNTIL=20061029T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		49 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19870405T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=1SU;UNTIL=20060402T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		50 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '20070311T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		51 => [
			'type' => 'STANDARD',
			'DTSTART' => '20071104T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>