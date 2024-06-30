<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Maintenance\Migration\MigrationBase;

class SessionIDs extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding more space for session ids';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$LogOnlineTable = new \SMF\Maintenance\Database\Schema\v2_1\LogOnline();
		$LogErrorsTable = new \SMF\Maintenance\Database\Schema\v2_1\LogErrors();
		$SessionsTable = new \SMF\Maintenance\Database\Schema\v2_1\Sessions();

		foreach ($LogOnlineTable->columns as $column) {
			if ($column->name !== 'session') {
				continue;
			}

			$LogOnlineTable->alterColumn($column);
		}

		foreach ($LogErrorsTable->columns as $column) {
			if ($column->name !== 'session') {
				continue;
			}

			$LogErrorsTable->alterColumn($column);
		}

		foreach ($SessionsTable->columns as $column) {
			if ($column->name !== 'session_id') {
				continue;
			}

			$SessionsTable->alterColumn($column);
		}

		return true;
	}
}

?>