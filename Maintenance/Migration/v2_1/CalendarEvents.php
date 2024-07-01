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

class CalendarEvents extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for calendar events';

	private array $newColumns = [
		'start_time',
		'end_time',
		'timezone',
		'location',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Calendar();
		$existing_structure = $table->getCurrentStructure();

		foreach ($this->newColumns as $column) {
			if (!isset($existing_structure['columns'][$column])) {
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
		$table = new \SMF\Maintenance\Database\Schema\v2_1\Calendar();
		$existing_structure = $table->getCurrentStructure();

		foreach ($this->newColumns as $column) {
			if (isset($existing_structure['columns'][$column])) {
				continue;
			}

			foreach ($table->columns as $col) {
				if ($col->name === $column) {
					$table->addColumn($col);

					$this->handleTimeout();
				}
			}
		}

		return true;
	}
}

?>