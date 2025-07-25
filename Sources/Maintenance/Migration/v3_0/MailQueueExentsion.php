<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class MailQueueExentsion extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update mail queue support';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $newColumns = ['next_try', 'tries', 'extra'];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v3_0\MailQueue();
		$existing_structure = $table->getCurrentStructure();

		$found = 0;

		foreach ($existing_structure['columns'] as $column) {
			if (in_array($column['name'], $this->newColumns)) {
				$found++;
			}
		}

		return $found != count($this->newColumns);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\MailQueue();
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
