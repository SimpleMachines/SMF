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
use SMF\Maintenance\Database\Schema\Table;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Ipv6Base extends MigrationBase
{
	public function getTotalItems(string $table, string $col): int
	{
		$request = $this->query('', '
			SELECT COUNT(DISTINCT member_ip)
			FROM {db_prefix}members
		');

		list($items) = Db::$db->fetch_row($request);

		return (int) $items;
	}

	public function convertData(string $targetTable, string $oldCol, string $newCol, int $limit = 50000, int $setSize = 100): bool
	{
		// mysql default max length is 1mb https://dev.mysql.com/doc/refman/5.1/en/packet-too-large.html
		$arIp = [];

		$request = $this->query(
			'',
			'
			SELECT DISTINCT {raw:old_col}
			FROM {db_prefix}{raw:table_name}
			WHERE {raw:new_col} = {string:empty}
			LIMIT {int:limit}',
			[
				'old_col' => $oldCol,
				'new_col' => $newCol,
				'table_name' => $targetTable,
				'empty' => '',
				'limit' => $limit,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$arIp[] = $row[$oldCol];
		}

		Db::$db->free_result($request);

		if (empty($arIp)) {
			return true;
		}

		$updates = [];
		$new_ips = [];
		$cases = [];
		$count = count($arIp);

		for ($i = 0; $i < $count; $i++) {
			$new_ip = trim($arIp[$i]);

			$new_ip = filter_var($new_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);

			if ($new_ip === false) {
				$new_ip = '';
			}

			$updates['ip' . $i] = $arIp[$i];
			$new_ips['newip' . $i] = $new_ip;
			$cases[$arIp[$i]] = 'WHEN ' . $oldCol . ' = {string:ip' . $i . '} THEN {inet:newip' . $i . '}';

			// Execute updates every $setSize & also when done with contents of $arIp
			if ((($i + 1) == $count) || (($i + 1) % $setSize === 0)) {
				$updates['whereSet'] = array_values($updates);
				Db::$db->query(
					'',
					'UPDATE {db_prefix}' . $targetTable . '
					SET ' . $newCol . ' = CASE ' .
					implode('
						', $cases) . '
						ELSE NULL
					END
					WHERE ' . $oldCol . ' IN ({array_string:whereSet})',
					array_merge($updates, $new_ips),
				);

				$updates = [];
				$new_ips = [];
				$cases = [];
			}
		}

		return false;
	}


	public function migrateData(Table $table, string $col): bool
	{
		$start = Maintenance::getCurrentStart();

		// Get our total items.
		Maintenance::$total_items = $this->getTotalItems('members', 'member_ip2');

		$existing_structure = $table->getCurrentStructure();

		// PostgreSQL we use a migration function.
		if (Config::$db_type === POSTGRE_TITLE) {
			$this->query('', '
			ALTER TABLE {db_prefix}{raw:table}
				ALTER {raw:col} DROP not null,
				ALTER {raw:col} DROP default,
				ALTER {raw:col} TYPE inet USING migrate_inet({raw:col});
			', [
				'table' => $table->name,
				'col' => $col,
			]);

			return true;
		}

		// Add columns to ban_items
		if ($start <= 0) {
			// Does the old IP exist?
			foreach ($table->columns as $column) {
				if ($column->name === $col && !isset($existing_structure['columns'][$col . '_old'])
				) {
					$this->query('', '
						ALTER TABLE {db_prefix}{raw:table}
							CHANGE {raw:col} {raw:col}_old varchar(200)
					', [
						'table' => $table->name,
						'col' => $col,
					]);
				}
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			if ($column->name === $col && isset($existing_structure['columns'][$col . '_old']) && !isset($existing_structure['columns'][$col])) {
				$table->addColumn($column);
			}

			$this->handleTimeout(++$start);
		}

		// Make sure our temp index exists.
		if ($start <= 2) {
			if (!isset($existing_structure['indexes']['temp_old_' . $col])) {
				$this->query('', '
					CREATE INDEX {db_prefix}temp_old_{raw:col} ON {db_prefix}{raw:table} ({raw:col_old})
				', [
					'table' => $table->name,
					'col' => $col,
				]);
			}

			$this->handleTimeout(++$start);
		}

		// Initialize new ip column.
		if ($start <= 3) {
				$this->query('', '
					UPDATE {db_prefix}{raw:table}
					SET {raw:col_old) = {empty}
				', [
					'table' => $table->name,
					'col' => $col,
				]);

			$this->handleTimeout(++$start);
		}

		if ($start <= 4) {
			$is_done = false;

			while (!$is_done) {
				$this->handleTimeout();
				$is_done = $this->convertData($table->name, $col . '_old', $col);
			}

			$this->handleTimeout(++$start);
		}

		// Remove the temporary ip indexes.
		if ($start <= 5) {
			if (isset($existing_structure['indexes']['temp_old_' . $col])) {
				$this->query('', '
					DROP INDEX {db_prefix}temp_old_{raw:col} ON {db_prefix}{raw:table}
				', [
					'table' => $table->name,
					'col' => $col,
				]);
			}

			$this->handleTimeout(++$start);
		}

		// Remove the old member columns.
		if ($start <= 6) {
			if (isset($existing_structure['columns'][$col . '_old'])) {
				$this->query('', '
					ALTER TABLE {db_prefix}{raw:table}
						DROP COLUMN {raw:col}_old
				', [
					'table' => $table->name,
					'col' => $col,
				]);
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}

	public function truncateAndConvert(Table $table, string|array $columns, bool $force = false): bool
	{
		$start = Maintenance::getCurrentStart();

		// PostgreSQL we use a migration function.
		if (Config::$db_type !== POSTGRE_TITLE && !$force) {
			return $this->postgreSQLmigrate($table, $columns);
		}

		if ($start <= 0) {
			$this->query('', 'TRUNCATE TABLE {db_prefix}{raw:table}', ['table' => $table->name]);

			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			// Modify ip size
			foreach ($table->columns as $col) {
				if (in_array($col->name, (array) $columns)) {
					$table->alterColumn($col);
				}
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}


	public function convertWithNoDataPreservation(Table $table, string|array $columns, bool $force = false): bool
	{
		$start = Maintenance::getCurrentStart();

		// PostgreSQL we use a migration function.
		if (Config::$db_type !== POSTGRE_TITLE && !$force) {
			return $this->postgreSQLmigrate($table, $columns);
		}

		$existing_structure = $table->getCurrentStructure();

		foreach ($columns as $column) {
			foreach ($table->columns as $col) {
				if ($col->name == $column && $existing_structure['columns'][$col->name]['type'] !== (Config::$db_type === POSTGRE_TITLE ? 'inet' : 'varbinary')) {
					$table->dropColumn($col);
					$table->addColumn($col);

					$this->handleTimeout(++$start);
				}
			}
		}

		return true;
	}

	private function postgreSQLmigrate(Table $table, string|array $columns)
	{
		foreach ($columns as $column) {
			$this->query('', '
			ALTER TABLE {db_prefix}{raw:table}
				ALTER {raw:col} DROP not null,
				ALTER {raw:col} DROP default,
				ALTER {raw:col} TYPE inet USING migrate_inet({raw:col});
			', [
				'table' => $table->name,
				'col' => $column,
			]);
		}

		return true;
	}
}

?>