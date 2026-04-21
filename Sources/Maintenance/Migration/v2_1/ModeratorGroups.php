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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class ModeratorGroups extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding support for group-based board moderation';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\ModeratorGroups();

		return !$table->exists();
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\ModeratorGroups();
		$table->create();

		return true;
	}
}
