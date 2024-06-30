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

class LogOnlineURL extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Changing url column in log_online from TEXT to VARCHAR(2048)';

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
		$table = new \SMF\Maintenance\Database\Schema\v3_0\LogOnline();
		$existing_structure = $table->getCurrentStructure();

		if ($existing_structure['columns']['url'] !== 'varchar' || (int) $existing_structure['columns']['url']['size'] !== 2048) {
			$table->alterColumn(
				$table->columns['url'],
				'url',
			);
		}

		return true;
	}
}

?>