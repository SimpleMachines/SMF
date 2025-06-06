<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Maintenance\Migration\MigrationBase;

class PurgeFloodcontrol extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Purging the log_floodcontrol table';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Purge flood control.
		$this->query(
			'DELETE FROM {db_prefix}log_floodcontrol',
			[],
		);

		$this->handleTimeout();

		return true;
	}
}
