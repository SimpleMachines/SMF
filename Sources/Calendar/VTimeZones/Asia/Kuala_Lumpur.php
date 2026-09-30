<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Calendar\VTimeZones\Asia;

/**
 * Asia/Kuala_Lumpur
 */
class Kuala_Lumpur extends Singapore
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Time zone identifier.
	 */
	public string $tzid = 'Asia/Kuala_Lumpur';

	/**
	 * @var array
	 *
	 * Data about which metazone label to use for this time zone at any given
	 * date and time.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public array $metazones = [
		0 => [
			'ts' => '1970-01-01T00:00:00+0000',
			'metazone' => 'Malaya',
		],
		1 => [
			'ts' => '1981-12-31T16:00:00+0000',
			'metazone' => 'Malaysia',
		],
	];
}
