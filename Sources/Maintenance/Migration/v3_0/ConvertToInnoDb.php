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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class ConvertToInnoDb extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting MySQL tables to the InnoDB engine and dynamic rows';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		if (Db::$db->title !== MYSQL_TITLE) {
			return true;
		}

		$tables = Db::$db->list_tables(false, Db::$db->prefix . '%');

		foreach ($tables as $table) {
			$structure = Db::$db->table_structure($table);

			if ($structure['engine'] !== 'InnoDB') {
				Db::$db->query(
					'ALTER TABLE {identifier:table}
					ENGINE {literal:InnoDB}
					ROW_FORMAT=DYNAMIC',
					[
						'table' => $table,
					],
				);
			} elseif ($structure['row_format'] !== 'Dynamic') {
				Db::$db->query(
					'ALTER TABLE {identifier:table}
					ROW_FORMAT=DYNAMIC',
					[
						'table' => $table,
					],
				);
			}
		}

		// Try to ensure all future tables use dynamic row format.
		Db::$db->query(
			'SET GLOBAL innodb_default_row_format=DYNAMIC',
			[
				'db_error_skip' => true,
			],
		);

		return true;
	}
}
