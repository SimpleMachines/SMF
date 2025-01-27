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

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Tokyo
 */
class Tokyo extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Tokyo';

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
			'DTSTART' => '18880101T001859',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+091859',
			'TZOFFSETTO' => '+0900',
		],
		1 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19480502T000000',
			'TZNAME' => 'JDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19480912T010000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=9;BYDAY=2SA;UNTIL=19510909T010000',
			'TZNAME' => 'JST',
			'TZOFFSETFROM' => '+1000',
			'TZOFFSETTO' => '+0900',
		],
		3 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19490403T000000',
			'TZNAME' => 'JDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
		4 => [
			'type' => 'DAYLIGHT',
			'DTSTART' => '19500507T000000',
			'RRULE' => 'FREQ=YEARLY;BYMONTH=5;BYDAY=1SA;UNTIL=19510506T000000',
			'TZNAME' => 'JDT',
			'TZOFFSETFROM' => '+0900',
			'TZOFFSETTO' => '+1000',
		],
	];
}

?>