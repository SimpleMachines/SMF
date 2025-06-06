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

class PackageManager extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Making changes to the package manager';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Note: there is also a table that needs to be created, but the table
		// normalization substeps will take care of that part.

		// Change the URL of the SMF package server.
		$this->query(
			'UPDATE {db_prefix}package_servers
			SET url = {string:new}
			WHERE url = {string:old}',
			[
				'new' => 'http://custom.simplemachines.org/packages/mods',
				'old' => 'http://mods.simplemachines.org',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
