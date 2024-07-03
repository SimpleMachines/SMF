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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class MembersTimezone extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding timezone support';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Members();
		$existing_structure = $table->getCurrentStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] === 'timezone') {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Maintenance\Database\Schema\v2_1\Members();
		$new_col = $table->columns['timezone'];

		$table->addColumn($new_col);

		return true;
	}
}

?>