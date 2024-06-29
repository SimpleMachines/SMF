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

class MessageVersion extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding version information to posts and personal messages';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Db\Schema\v3_0\Messages();
		$existing_structure = $table->getCurrentStructure();

		if (!isset($existing_structure['columns']['version'])) {
			foreach ($table->columns as $column) {
				if ($column->name === 'version') {
					$table->addColumn($column);
					break;
				}
			}
		}

		$table = new \SMF\Db\Schema\v3_0\PersonalMessages();
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

?>