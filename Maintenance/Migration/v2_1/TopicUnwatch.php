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

class TopicUnwatch extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for topic unwatch';

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
		$table = new \SMF\Maintenance\Database\Schema\v3_0\LogTopics();

		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			// Add the unwatched column.
			if ($column->name === 'unwatched' && !isset($existing_structure['columns'][$column->name])) {
				$table->addColumn($column);
				continue;
			}

			// Remove the disregarded column
			if ($column->name === 'disregarded' && isset($existing_structure['columns'][$column->name])) {
				$table->dropColumn($column);
				continue;
			}
		}

		return true;
	}
}

?>