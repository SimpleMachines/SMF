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

class DropModPrefs extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Removing mod_prefs column from members table';

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

		return isset($existing_structure['columns']['mod_prefs']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\Members();
		$table->dropColumn('mod_prefs');

		return true;
	}
}
