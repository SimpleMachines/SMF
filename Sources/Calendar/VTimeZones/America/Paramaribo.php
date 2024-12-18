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
 * America/Paramaribo
 */
class Paramaribo extends \SMF\Calendar\VTimeZone
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'America/Paramaribo';

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
			'DTSTART' => '19451001T000000',
			'TZNAME' => 'UTC-0330',
			'TZOFFSETFROM' => '-034036',
			'TZOFFSETTO' => '-0330',
		],
		1 => [
			'type' => 'STANDARD',
			'DTSTART' => '19841001T000000',
			'TZNAME' => 'UTC-03',
			'TZOFFSETFROM' => '-0330',
			'TZOFFSETTO' => '-0300',
		],
	];
}

?>