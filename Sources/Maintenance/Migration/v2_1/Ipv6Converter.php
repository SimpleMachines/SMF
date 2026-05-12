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

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Column;
use SMF\Db\Schema\Table;

trait Ipv6Converter
{
	/******************
	 * Internal methods
	 ******************/

	/**
	 * Converts string columns that stored IP addresses.
	 *
	 * @param Table $table The table containing the column to convert.
	 * @param Column $column The column to convert.
	 */
	protected function convertStringColumnToInet(Table $table, Column $column): void
	{
		if (Db::$db->title === POSTGRE_TITLE) {
			$this->query(
				'ALTER TABLE {db_prefix}{raw:table}
					ALTER {raw:column} DROP not null,
					ALTER {raw:column} DROP default,
					ALTER {raw:column} TYPE inet USING migrate_inet({raw:column})',
				[
					'table' => $table->name,
					'column' => $column->name,
				],
			);
		} else {
			$table->addColumn(new Column(
				name: 'temp',
				type: 'inet',
				size: 16,
			));

			$this->query(
				'UPDATE {db_prefix}{raw:table}
				SET {raw:temp} = INET6_ATON({raw:column})',
				[
					'table' => $table->name,
					'column' => $column->name,
					'temp' => 'temp',
				],
			);

			$table->alterColumn($column);

			$this->query(
				'UPDATE {db_prefix}{raw:table}
				SET {raw:column} = {raw:temp}',
				[
					'table' => $table->name,
					'column' => $column->name,
					'temp' => 'temp',
				],
			);

			$table->dropColumn('temp');
		}
	}

	/**
	 * Converts integer columns that stored IP addresses.
	 *
	 * @param Table $table The table containing the column to convert.
	 * @param Column $column The column to convert.
	 */
	protected function convertIntegerColumnToInet(Table $table, Column $column): void
	{
		if (Db::$db->title === POSTGRE_TITLE) {
			$this->query(
				'ALTER TABLE {db_prefix}{raw:table}
					ALTER {raw:column} DROP not null,
					ALTER {raw:column} DROP default,
					ALTER {raw:column} TYPE inet USING migrate_inet({raw:column})',
				[
					'table' => $table->name,
					'column' => $column->name,
				],
			);
		} else {
			$table->addColumn(new Column(
				name: 'temp',
				type: 'inet',
				size: 16,
			));

			$this->query(
				'UPDATE {db_prefix}{raw:table}
				SET {raw:temp} = UNHEX(HEX({raw:column}))',
				[
					'table' => $table->name,
					'column' => $column->name,
					'temp' => 'temp',
				],
			);

			$table->alterColumn($column);

			$this->query(
				'UPDATE {db_prefix}{raw:table}
				SET {raw:column} = {raw:temp}',
				[
					'table' => $table->name,
					'column' => $column->name,
					'temp' => 'temp',
				],
			);

			$table->dropColumn('temp');
		}
	}
}
