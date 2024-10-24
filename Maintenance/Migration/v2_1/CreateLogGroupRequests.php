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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class CreateLogGroupRequests extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for logging who fulfils a group request';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $newColumns = ['status', 'id_member_acted', 'member_name_acted', 'time_acted', 'act_reason'];

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
		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogGroupRequests();
		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			// Column exists, don't need to do this.
			if (!in_array($column->name, $this->newColumns) || isset($existing_structure['columns'][$column->name])) {
				continue;
			}

			$table->addColumn($column);
		}

		Db::$db->remove_index('{db_prefix}log_group_requests', 'id_member');

		foreach ($table->indexes as $idx) {
			// Column exists, don't need to do this.
			if ($idx->name !== 'idx_id_member' || isset($existing_structure['indexes'][$idx->name])) {
				continue;
			}

			$table->addIndex($idx);
		}

		return true;
	}
}

?>