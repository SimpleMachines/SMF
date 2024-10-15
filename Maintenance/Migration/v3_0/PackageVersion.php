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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Maintenance\Migration\MigrationBase;

class PackageVersion extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding SMF version information to log_packages';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v3_0\LogPackages();
		$existing_structure = $table->getCurrentStructure();

		if (!isset($existing_structure['columns']['smf_version'])) {
			foreach ($table->columns as $column) {
				if ($column->name === 'smf_version') {
					$table->addColumn($column);
					break;
				}
			}
		}

		return true;
	}
}

?>