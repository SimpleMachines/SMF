<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Maintenance\Migration\MigrationBase;

class HolidayRecurrenceDates extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Clearing recurrence rules stored as recurrence dates';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Two of the holidays the installer creates were given their own
		// recurrence rule as a recurrence date as well. rdates holds a list of
		// dates, so anything starting with FREQ= is a rule that ended up in the
		// wrong column, and reading it back throws.
		$this->query(
			'UPDATE {db_prefix}calendar
			SET rdates = {empty}
			WHERE rdates LIKE {string:rrule}',
			[
				'rrule' => 'FREQ=%',
			],
		);

		return true;
	}
}
