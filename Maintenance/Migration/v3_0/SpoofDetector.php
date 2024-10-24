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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;

class SpoofDetector extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding SpoofDetector support';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v3_0\Members();
		$existing_structure = $table->getCurrentStructure();

		// Add the spoofdetector_name column.
		foreach ($table->columns as $column) {
			if (!isset($existing_structure['columns'][$column->name])) {
				$table->addColumn($column);

				$this->handleTimeout();
			}
		}

		// Add indexes for the spoofdetector_name column.
		foreach ($table->indexes as $index) {
			if (!isset($existing_structure['indexes'][$index->name])) {
				$table->addIndex($index);

				$this->handleTimeout();
			}
		}

		// Add the new "spoofdetector_censor" setting
		Config::updateModSettings(['spoofdetector_censor' => 1]);

		return true;
	}
}

?>