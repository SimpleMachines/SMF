<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class TopicUnwatch extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding support for topic unwatch';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\LogTopics();

		$existing_structure = $table->getCurrentStructure();

		foreach ($table->columns as $column) {
			// Add the unwatched column.
			if ($column->name === 'unwatched' && !isset($existing_structure['columns'][$column->name])) {
				$table->addColumn($column);
				continue;
			}

			// Remove the disregarded column
			$table->dropColumn('disregarded');
		}

		return true;
	}
}
