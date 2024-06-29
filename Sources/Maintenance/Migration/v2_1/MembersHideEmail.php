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

class MembersHideEmail extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Dropping the "hide_email" column from the members table';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Db\Schema\v3_0\Members();
		$existing_structure = $table->getStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] === 'hide_email') {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Db\Schema\v3_0\Members();
		$existing_structure = $table->getStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] == 'hide_email') {
				$col = new Column(
					name: $column['name'],
					type: 'varchar',
				);

				$table->dropColumn($col);
			}
		}

		return true;
	}
}

?>