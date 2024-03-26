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
		$LogOnlineTable = new \SMF\Db\Schema\v3_0\LogOnline();
		$LogErrorsTable = new \SMF\Db\Schema\v3_0\LogErrors();
		$SessionsTable = new \SMF\Db\Schema\v3_0\Sessions();

		foreach ($LogOnlineTable->columns as $column) {
			// Column exists, don't need to do this.
			if ($column->name == 'session') {
				continue;
			}

			$column->alter('{db_prefix}' . $this->name);
		}

		foreach ($LogErrorsTable->columns as $column) {
			// Column exists, don't need to do this.
			if ($column->name == 'session') {
				continue;
			}

			$column->alter('{db_prefix}' . $this->name);
		}

		foreach ($SessionsTable->columns as $column) {
			// Column exists, don't need to do this.
			if ($column->name == 'session_id') {
				continue;
			}

			$column->alter('{db_prefix}' . $this->name);
		}

		return true;
	}
}

?>