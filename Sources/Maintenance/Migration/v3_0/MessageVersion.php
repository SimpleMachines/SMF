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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class MessageVersion extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding version information to posts and personal messages';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\Messages();
		$existing_structure = $table->getCurrentStructure();

		if (!isset($existing_structure['columns']['version'])) {
			foreach ($table->columns as $column) {
				if ($column->name === 'version') {
					$table->addColumn($column);
					break;
				}
			}
		}

		$this->handleTimeout();

		$table = new Schema\v3_0\PersonalMessages();
		$existing_structure = $table->getCurrentStructure();

		if (!isset($existing_structure['columns']['version'])) {
			foreach ($table->columns as $column) {
				if ($column->name === 'version') {
					$table->addColumn($column);
					break;
				}
			}
		}

		return true;
	}
}
