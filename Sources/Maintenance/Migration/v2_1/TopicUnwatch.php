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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class TopicUnwatch extends MigrationBase
{
  	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for topic unwatch';
  
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
		$LogTopicsTable = new \SMF\Db\Schema\v3_0\LogTopics();

		$existing_columns = Db::$db->list_columns('{db_prefix}' . $LogTopicsTable->name);

		foreach ($LogTopicsTable->columns as $column) {
			// Add the unwatched column.
			if ($column->name === 'unwatched' && !in_array($column->name, $existing_columns)) {
                $column->add('{db_prefix}' . $LogTopicsTable->name);
                continue;
			}

			// Remove the disregarded column
			if ($column->name === 'disregarded' && in_array($column->name, $existing_columns)) {
                $column->drop('{db_prefix}' . $LogTopicsTable->name);
                continue;
			}
        }

        return true;
    }
}

?>