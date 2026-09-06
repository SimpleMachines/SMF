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

class EmailAddressCi extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding email_address_ci column';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v3_0\Members();
		$existing_structure = $table->getCurrentStructure();

		return (
			!isset($existing_structure['columns']['email_address_ci'])
			|| !isset($existing_structure['indexes']['idx_email_address_ci'])
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\Members();

		$table->addColumn($table->columns['email_address_ci']);
		$table->addIndex($table->indexes['idx_email_address_ci']);

		$this->handleTimeout();

		return true;
	}
}
