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

use SMF\Config;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class IdxLogPackages extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Clean up indexes (Log Packages)';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type === POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogActions();
		$existing_structure = $table->getCurrentStructure();

		// Change index for table log_packages
		if ($start <= 0) {
			foreach ($table->indexes as $idx) {
				if ($idx->name === 'log_packages_filename' && isset($existing_structure['indexes']['log_packages_filename'])) {
					$table->addIndex($idx);
				}
			}

			$this->handleTimeout(++$start);
		}

		// Change index for table log_packages
		if ($start <= 1) {
			foreach ($table->indexes as $idx) {
				if ($idx->name === 'log_packages_filename' && !isset($existing_structure['indexes']['log_packages_filename'])) {
					$idx->columns[0] .= ' varchar_pattern_ops';
					$table->addIndex($idx);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>