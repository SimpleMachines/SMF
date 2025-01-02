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
 * America/Barbados
 */
class Barbados extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Barbados';

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
			'DTSTART' => '19110828T000000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-035829',
			'TZOFFSETTO' => '-0400',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19420419T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19420831T030000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19430502T010000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		4 => [
			'type' => 'STANDARD',
			'DTSTART' => '19430905T030000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		5 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19440410T010000',
			'TZNAME' => 'UTC-0330',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0330',
		],
		6 => [
			'type' => 'STANDARD',
			'DTSTART' => '19440910T023000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0400',
		],
		7 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19770612T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		8 => [
			'type' => 'STANDARD',
			'DTSTART' => '19771002T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=10;BYDAY=1SU;UNTIL=19781001T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		9 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19780416T020000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=4;BYDAY=3SU;UNTIL=19800420T020000',
			'TZNAME' => 'ADT',
			'TZOFFSETFROM' => '-0400',
			'TZOFFSETTO' => '-0300',
		],
		10 => [
			'type' => 'STANDARD',
			'DTSTART' => '19790930T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
		11 => [
			'type' => 'STANDARD',
			'DTSTART' => '19800925T020000',
			'TZNAME' => 'AST',
			'TZOFFSETFROM' => '-0300',
			'TZOFFSETTO' => '-0400',
		],
	];
}

?>