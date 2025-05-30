<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Tasks;

use SMF\Db\DatabaseApi as Db;
use SMF\Sapi;
use SMF\Taskrunner;

/**
 *
 */
class Utf8EntityDecode extends BackgroundTask
{
	/*****************
	 * Class constants
	 *****************/

	public const LIMIT = 500;

	/****************
	 * Public methods
	 ****************/

	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		// Avoid leaving data in an inconsistent state.
		ignore_user_abort(true);

		$time_limit = (int) ((Sapi::setTimeLimit(Taskrunner::MAX_CLAIM_THRESHOLD) !== false ? Taskrunner::MAX_CLAIM_THRESHOLD : (int) ini_get('max_execution_time')) / 2);

		// Check that the table actually exists.
		if (
			!in_array(
				$this->_details['table'],
				Db::$db->list_tables(false, Db::$db->prefix . '%'),
			)
		) {
			return true;
		}

		// Get the structure of this table.
		$structure = Db::$db->table_structure($this->_details['table']);

		// Which columns contain string data?
		$string_columns = array_map(
			fn($col) => $col['name'],
			array_filter(
				$structure['columns'],
				fn($col) => (
					!str_ends_with($col['name'], '_utf8entitydecode')
					&& in_array($col['type'], ['varchar', 'char', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'])
				),
			),
		);

		// We need to fetch rows in a consistent order. There are several options:
		// First and best option is to use the primary key, if there is one.
		if (array_filter($structure['indexes'], fn($idx) => $idx['type'] === 'primary') !== []) {
			$idx = current(array_filter($structure['indexes'], fn($idx) => $idx['type'] === 'primary'));
			$order_by = array_map(
				fn($col) => preg_replace('/\(\d+\)$/', '', $col),
				$idx['columns'],
			);
		}
		// Next best is some other unique index, if there is one.
		elseif (array_filter($structure['indexes'], fn($idx) => $idx['type'] === 'unique') !== []) {
			$idx = current(array_filter($structure['indexes'], fn($idx) => $idx['type'] === 'unique'));
			$order_by = array_map(
				fn($col) => preg_replace('/\(\d+\)$/', '', $col),
				$idx['columns'],
			);
		}
		// If there are no indexes, try the sequentially increasing column, if there is one.
		elseif (array_filter($structure['columns'], fn($col) => !empty($col['auto'])) !== []) {
			$col = current(array_filter($structure['columns'], fn($col) => !empty($col['auto'])));
			$order_by = [$col['name']];
		}
		// This is inefficient, but we have no better option remaining.
		else {
			$order_by = array_map(
				fn($col) => $col['name'],
				$structure['columns'],
			);
		}

		// Can we update the table directly, or do we need to use a temp table?
		$update_directly = array_intersect($string_columns, $order_by) === [];

		if ($update_directly) {
			// Use the ORDER BY columns for the WHERE clause that updates data.
			foreach ($order_by as $col) {
				if (str_contains($structure['columns'][$col]['type'], 'int')) {
					$type = 'int';
				} elseif (in_array($structure['columns'][$col]['type'], ['decimal', 'numeric', 'float', 'double'])) {
					$type = 'float';
				} else {
					$type = 'string';
				}

				$where[$col] = $col . ' = {' . $type . ':' . $col . '}';
			}
		} else {
			// When the ORDER BY clause contains one or more of the columns that
			// need to be updated, we must use a multi-step process.
			$this->createTempTable($string_columns);
		}

		// Work in batches until we run close to the time limit.
		while (microtime(true) < TIME_START + $time_limit) {
			// Fetch the rows.
			$request = Db::$db->query(
				'',
				'SELECT {raw:columns}
				FROM {identifier:table}
				ORDER BY {raw:order_by}
				LIMIT {int:limit}
				OFFSET {int:offset}',
				[
					'table' => $this->_details['table'],
					'columns' => implode(', ', array_unique(array_merge($order_by, $string_columns))),
					'order_by' => implode(', ', $order_by),
					'limit' => self::LIMIT,
					'offset' => $this->_details['offset'],
				],
			);

			$num_rows = Db::$db->num_rows($request);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->_details['offset']++;

				if ($update_directly) {
					$this->updateDirectly($row, $where, $string_columns);
				} else {
					$this->recordInTempTable($row, $string_columns);
				}
			}

			Db::$db->free_result($request);

			if ($num_rows < self::LIMIT) {
				break;
			}
		}

		// If we have more rows to process, respawn this task.
		if ($num_rows >= self::LIMIT) {
			$this->respawn();

			return true;
		}

		// If we used a temp table and all rows have been processed,
		// then we're now ready to update the main table.
		if (!$update_directly) {
			$this->updateFromTempTable($string_columns);
			$this->dropTempTable($string_columns);
		}

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Decodes numeric entities for four-byte UTF-8 characters in a string.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string A UTF-8 string.
	 */
	protected function decode(string $string): string
	{
		return str_contains($string, '&') ? mb_decode_numericentity($string, [0x010000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8') : $string;
	}

	/**
	 * Decodes numeric entities for four-byte UTF-8 characters in the data of
	 * each string column in a row and then updates the table with the new data.
	 *
	 * @param array $row A row of data that was retrieved from the table.
	 * @param array $where Conditions used to find the correct row to update.
	 * @param array $string_columns The columns whose data needs to be updated.
	 */
	private function updateDirectly(array $row, array $where, array $string_columns): void
	{
		$params = [
			'table' => $this->_details['table'],
		];

		$set = [];

		foreach ($row as $col => $value) {
			if (isset($where[$col])) {
				$params[$col] = $value;
			}

			if (in_array($col, $string_columns) && is_string($value)) {
				$set[] = $col . ' = {string:decoded_' . $col . '}';
				$params['decoded_' . $col] = $this->decode($value);
			}
		}

		if (empty($set)) {
			return;
		}

		Db::$db->query(
			'',
			'UPDATE {identifier:table}
			SET ' . implode(', ', $set) . '
			WHERE (' . implode(') AND (', $where) . ')',
			$params,
		);
	}

	/**
	 * Creates a temporary table to store updated data and adds some temporary
	 * columns to the permanent table to link one to the other.
	 *
	 * @param array $string_columns The columns whose data needs to be updated.
	 */
	private function createTempTable(array $string_columns): void
	{
		Db::$db->create_table(
			table_name: $this->_details['table'] . '_utf8entitydecode',
			columns: [
				[
					'name' => 'hash',
					'type' => 'char',
					'size' => 40,
				],
				[
					'name' => 'string',
					'type' => 'longtext',
				],
			],
			if_exists: 'ignore',
		);

		foreach ($string_columns as $string_column) {
			$added = Db::$db->add_column(
				table_name: $this->_details['table'],
				column_info: [
					'name' => $string_column . '_utf8entitydecode',
					'type' => 'char',
					'size' => 40,
					'default' => '',
				],
				if_exists: 'ignore',
			);

			if ($added) {
				Db::$db->query(
					'',
					'UPDATE {identifier:table}
					SET {identifier:hash_col} = SHA1({identifier:col})',
					[
						'table' => $this->_details['table'],
						'col' => $string_column,
						'hash_col' => $string_column . '_utf8entitydecode',
					],
				);
			}
		}
	}

	/**
	 * Decodes numeric entities in the data of each string column in the row and
	 * then writes the updated data to a temporary table.
	 *
	 * @param array $row A row of data that was retrieved from the table.
	 * @param array $string_columns The columns whose data needs to be updated.
	 */
	private function recordInTempTable(array $row, array $string_columns): void
	{
		$data = [];

		foreach ($string_columns as $col) {
			if (!is_string($row[$col])) {
				continue;
			}

			$data[] = [
				sha1($row[$col]),
				$this->decode($row[$col]),
			];
		}

		if (!empty($data)) {
			Db::$db->insert(
				method: 'ignore',
				table: $this->_details['table'] . '_utf8entitydecode',
				columns: [
					'hash' => 'string',
					'string' => 'string',
				],
				data: $data,
				keys: [],
			);
		}
	}

	/**
	 * Updates the permanent table with the data from the temp table.
	 *
	 * @param array $string_columns The columns whose data needs to be updated.
	 */
	private function updateFromTempTable(array $string_columns): void
	{
		foreach ($string_columns as $string_column) {
			Db::$db->update_from(
				table: [
					'name' => $this->_details['table'],
					'alias' => 't',
				],
				from_tables: [
					[
						'name' => $this->_details['table'] . '_utf8entitydecode',
						'alias' => 'u',
						'condition' => 't.' . $string_column . '_utf8entitydecode = u.hash',
					],
				],
				set: 't.' . $string_column . ' = u.string',
				where: '',
				db_values: [],
			);
		}
	}

	/**
	 * Drops the temporary table and removes the temporary columns from the
	 * permanent table.
	 */
	private function dropTempTable(array $string_columns): void
	{
		foreach ($string_columns as $string_column) {
			Db::$db->remove_column(
				table_name: $this->_details['table'],
				column_name: $string_column . '_utf8entitydecode',
			);
		}

		Db::$db->drop_table(
			table_name: $this->_details['table'] . '_utf8entitydecode',
		);
	}

	/**
	 * Adds a new instance of this task to the task list.
	 */
	private function respawn(): void
	{
		Db::$db->insert(
			method: 'insert',
			table: '{db_prefix}background_tasks',
			columns: [
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			data: [
				[
					get_class($this),
					json_encode($this->_details),
					0,
				],
			],
			keys: [],
		);
	}
}
