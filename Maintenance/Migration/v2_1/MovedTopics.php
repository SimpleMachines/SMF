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

use SMF\Maintenance\Migration\MigrationBase;

class MovedTopics extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for MOVED topics enhancements';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $newColumns = ['redirect_expires', 'id_redirect_topic'];

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
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Topics();
		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			// Column exists, don't need to do this.
			if (!in_array($column->name, $this->newColumns) || isset($existing_structure['columns'][$column->name])) {
				continue;
			}

			$table->addColumn($column);
		}

		return true;
	}
}

?>