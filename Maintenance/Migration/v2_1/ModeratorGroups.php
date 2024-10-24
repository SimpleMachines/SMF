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
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class ModeratorGroups extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for group-based board moderation';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$tables = Db::$db->list_tables();

		return !in_array(Config::$db_prefix . 'moderator_groups', $tables);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$ModeratorGroupsTable = new \SMF\Maintenance\Database\Schema\v2_1\ModeratorGroups();

		$tables = Db::$db->list_tables();

		// Creating draft table.
		if (!in_array(Config::$db_prefix . 'moderator_groups', $tables)) {
			$ModeratorGroupsTable->create();
		}

		return true;
	}
}

?>