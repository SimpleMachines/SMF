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

namespace SMF\Calendar\VTimeZones\Africa;

/**
 * Africa/Lagos
 */
class Lagos extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Africa/Lagos';

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
			'DTSTART' => '19050701T000000',
			'TZNAME' => 'GMT',
			'TZOFFSETFROM' => '+001335',
			'TZOFFSETTO' => '+0000',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19080701T000000',
			'TZNAME' => 'LMT',
			'TZOFFSETFROM' => '+0000',
			'TZOFFSETTO' => '+001335',
		],
		2 => [
			'type' => 'STANDARD',
			'DTSTART' => '19140101T000000',
			'TZNAME' => 'UTC+0030',
			'TZOFFSETFROM' => '+001335',
			'TZOFFSETTO' => '+0030',
		],
		3 => [
			'type' => 'STANDARD',
			'DTSTART' => '19190901T000000',
			'TZNAME' => 'WAT',
			'TZOFFSETFROM' => '+0030',
			'TZOFFSETTO' => '+0100',
		],
	];
}

?>