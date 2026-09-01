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

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;

class Warnings extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding user warnings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Note: there are some tables that need to be created, and some columns
		// and indexes that need to be added to the members table, but the table
		// normalization substeps will take care of that part.

		// Ensure warning settings are present
		if (empty(Config::$modSettings['warning_settings'])) {
			Config::updateModSettings([
				'warning_settings' => '1,20,0',
				'warning_watch' => '10',
				'warning_moderate' => '35',
				'warning_mute' => '60',
			]);
		}

		$this->handleTimeout();

		return true;
	}
}
