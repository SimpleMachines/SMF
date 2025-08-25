<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class Ipv6LogBanned extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update log_banned ip with ipv6 support';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function __construct()
	{
		if (Db::$db->title !== POSTGRE_TITLE) {
			$this->name .= ' without converting';
		}
	}

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Db\Schema\v2_1\LogBanned();
		$existing_structure = $table->getCurrentStructure();

		if (Db::$db->title === POSTGRE_TITLE) {
			return $existing_structure['columns']['ip']['type'] !== 'inet';
		}

		return $existing_structure['columns']['ip']['type'] !== 'varbinary';
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new \SMF\Db\Schema\v2_1\LogBanned();
		$existing_structure = $table->getCurrentStructure();

		if (Db::$db->title === POSTGRE_TITLE) {
			$this->query(
				'ALTER TABLE {db_prefix}log_banned
					ALTER ip DROP not null,
					ALTER ip DROP default,
					ALTER ip TYPE inet USING migrate_inet(ip)',
			);
		} else {
			foreach ($table->columns as $column) {
				if ($column->name === 'ip' && $existing_structure['columns'][$column->name]['type'] !== 'varbinary') {
					$table->dropColumn($column);
					$table->addColumn($column);
					continue;
				}
			}
		}

		return true;
	}
}
