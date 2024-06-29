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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Column;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class MessagesModifiedReason extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for edit reasons';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Db\Schema\v3_0\Messages();
		$existing_structure = $table->getCurrentStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] === 'modified_reason') {
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

		$table = new \SMF\Db\Schema\v3_0\Messages();
		$new_col = $table->columns['modified_reason'];

		$table->addColumn($new_col);

		return true;
	}
}

?>