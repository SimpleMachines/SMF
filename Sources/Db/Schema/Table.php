<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema;

use SMF\Db\DatabaseApi as Db;

/**
 * Represents a database table.
 */
class Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the table.
	 */
	public string $name;

	/**
	 * @var array
	 *
	 * An array of SMF\Db\Schema\Column objects.
	 */
	public array $columns = [];

	/**
	 * @var array
	 *
	 * An array of SMF\Db\Schema\DbIndex objects.
	 */
	public array $indexes = [];

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

	/**
	 * @var string
	 *
	 * The default character set for the table.
	 */
	public ?string $default_charset;

	/**
	 * @var int
	 *
	 * The starting value for the table's automatically incrementing sequence.
	 *
	 * Only applicable when importing a table that already contains data and the
	 * table has an AUTO_INCREMENT column (MySQL) or a column with a sequence
	 * attached to it (PostgreSQL).
	 *
	 * This is not set by __construct(). It can be set afterward.
	 */
	public ?int $auto_start;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// As of SMF 3.0, all tables always use four-byte UTF-8.
		if (
			preg_match('/\\\\v(\d+_\d+)\\\\/', $this::class, $matches)
			&& version_compare(strtr($matches[1], '_', '.'), '3.0', '>=')
		) {
			$this->default_charset = Db::$db->title === MYSQL_TITLE ? 'utf8mb4' : 'utf8';
		}
	}

	/**
	 * Checks all the columns and indexes in this table to make sure they
	 * are defined the way they should be, and fixes any that aren't.
	 *
	 * @return bool Whether or not the operation was successful.
	 */
	public function normalize(): bool
	{
		if (count($this->columns) === 0) {
			return false;
		}

		if (empty(Db::$db->list_tables(false, Db::$db->prefix . $this->name))) {
			return $this->create();
		}

		$structure = $this->getCurrentStructure();

		$structure['columns'] = array_change_key_case($structure['columns'], CASE_LOWER);
		$structure['indexes'] = array_change_key_case($structure['indexes'], CASE_LOWER);

		// Adjust the values as needed.
		foreach ($structure['columns'] as $name => $column) {
			foreach ($column as $prop => $value) {
				// Easy way to cast numeric strings to int or float.
				if (is_numeric($value)) {
					$structure['columns'][$name][$prop] = $value + 0;
				}

				// Adjust the type if needed.
				if ($prop === 'type') {
					// Find the corresponding Column object.
					foreach ($this->columns as $col) {
						if ($col->name === $name) {
							break;
						}
					}

					// Just in case there was no matching Column object.
					if ($col->name !== $name) {
						continue;
					}

					$int_types = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'];
					$text_types = ['tinytext', 'text', 'mediumtext', 'longtext'];
					$blob_types = ['tinyblob', 'blob', 'mediumblob', 'longblob'];

					if (
						// If an existing integer column upgraded its type to a
						// larger one, we want to keep that larger type.
						(
							in_array($value, $int_types)
							&& in_array($col->type, $int_types)
							&& array_search($value, $int_types) > array_search($col->type, $int_types)
						)
						// If an existing string column upgraded its type to a
						// larger one, we want to keep that larger type.
						// This is only applicable to MySQL.
						|| (
							Db::$db->title === MYSQL_TITLE
							&& (
								in_array($value, $text_types)
								&& in_array($col->type, $text_types)
								&& array_search($value, $text_types) > array_search($col->type, $text_types)
							) || (
								in_array($value, $blob_types)
								&& in_array($col->type, $blob_types)
								&& array_search($value, $blob_types) > array_search($col->type, $blob_types)
							)
						)
					) {
						$col->type = $value;
					}
				}
			}
		}

		// Do we need to change the engine or row format?
		// This is only applicable to MySQL.
		if (Db::$db->title === MYSQL_TITLE) {
			if ($structure['engine'] !== 'InnoDB') {
				Db::$db->query(
					'ALTER TABLE {raw:table}
					ENGINE {literal:InnoDB}
					ROW_FORMAT=DYNAMIC',
					[
						'table' => Db::$db->prefix . $this->name,
					],
				);
			} elseif ($structure['row_format'] !== 'Dynamic') {
				Db::$db->query(
					'ALTER TABLE {raw:table}
					ROW_FORMAT=DYNAMIC',
					[
						'table' => Db::$db->prefix . $this->name,
					],
				);
			}
		}

		// Do we need to change any columns or indexes?
		$columns_to_change = [];
		$indexes_to_change = [];

		foreach ($this->columns as $col) {
			if (!isset($structure['columns'][$col->name])) {
				$columns_to_change[$col->name] = $col;
				continue;
			}

			list($expected_type) = Db::$db->calculate_type($col->type);
			list($existing_type) = Db::$db->calculate_type($structure['columns'][$col->name]['type']);

			if ($expected_type != $existing_type) {
				$columns_to_change[$col->name] = $col;
				continue;
			}

			foreach (['name', 'size', 'unsigned', 'not_null', 'default', 'auto'] as $prop) {
				if (($structure['columns'][$col->name][$prop] ?? null) != ($col->{$prop} ?? null)) {
					$columns_to_change[$col->name] = $col;
					continue 2;
				}
			}
		}

		foreach ($this->indexes as $index) {
			if (!isset($structure['indexes'][$index->name])) {
				$indexes_to_change[$index->name] = $index;
				continue;
			}

			if (($index->type ?? 'index') != $structure['indexes'][$index->name]['type']) {
				$columns_to_change[$col->name] = $col;
				continue;
			}

			// If we need to change any columns in this index, rebuild the index too.
			foreach ([$index->columns, $structure['indexes'][$index->name]['columns']] as $cols) {
				if (array_intersect($cols, array_keys($columns_to_change)) !== []) {
					$indexes_to_change[$index->name] = $index;
					continue 2;
				}
			}
		}

		// Change the columns.
		foreach ($columns_to_change as $col) {
			if (!isset($structure['columns'][$col->name])) {
				$this->addColumn($col);
			} else {
				$this->alterColumn($col, $structure['columns'][$col->name]['name']);
			}
		}

		// Special case if the table has a primary key index, but shouldn't.
		if (isset($structure['indexes']['primary']) && !isset($this->indexes['primary'])) {
			Db::$db->query(
				'ALTER TABLE {raw:table}
				DROP PRIMARY KEY',
				[
					'table' => Db::$db->prefix . $this->name,
				],
			);
		}

		// Rebuild the indexes.
		foreach ($indexes_to_change as $index) {
			$this->addIndex($index);
		}

		return true;
	}

	/**
	 * Creates the table in the database.
	 *
	 * @see SMF\Db\DatabaseApi::create_table
	 *
	 * @param array $parameters Extra parameters. Currently only 'engine', the
	 *    desired MySQL storage engine, is used.
	 * @param string $if_exists What to do if the table exists.
	 * @return bool Whether or not the operation was successful.
	 */
	public function create(array $parameters = [], string $if_exists = 'ignore'): bool
	{
		if (count($this->columns) === 0) {
			return false;
		}

		return Db::$db->create_table(
			'{db_prefix}' . $this->name,
			array_map('get_object_vars', array_values($this->columns)),
			array_map('get_object_vars', array_values($this->indexes)),
			$parameters,
			$if_exists,
		);
	}

	/**
	 * Drop the table from the database.
	 *
	 * @see SMF\Db\DatabaseApi::drop_table
	 *
	 * @return bool Whether or not the operation was successful.
	 */
	public function drop(): bool
	{
		return Db::$db->drop_table('{db_prefix}' . $this->name);
	}

	/**
	 * Get the table's current structure as it exists in the database.
	 *
	 * @see SMF\Db\DatabaseApi::table_structure
	 *
	 * @return array An array of table structure info: the name, the column
	 *    info from SMF\Db\DatabaseApi::list_columns() and index info from
	 *    SMF\Db\DatabaseApi::list_indexes().
	 */
	public function getCurrentStructure(): array
	{
		return Db::$db->table_structure('{db_prefix}' . $this->name);
	}

	/**
	 * Adds a column to this table in the database.
	 *
	 * @see SMF\Db\DatabaseApi::add_column
	 *
	 * @param Column $col The column to add to this table.
	 * @param string $if_exists What to do if the column exists.
	 *    If 'update', column is updated.
	 * @return bool Whether or not the operation was successful.
	 */
	public function addColumn(Column $col, string $if_exists = 'update'): bool
	{
		return Db::$db->add_column(
			'{db_prefix}' . $this->name,
			get_object_vars($col),
			[],
			$if_exists,
		);
	}

	/**
	 * Updates a column in the database to match the definition given by the
	 * supplied object's properties.
	 *
	 * @see SMF\Db\DatabaseApi::change_column
	 *
	 * @param Column $col The column to alter.
	 * @param ?string $old_name If passed, uses this as the old column name.
	 * @return bool Whether or not the operation was successful.
	 */
	public function alterColumn(Column $col, ?string $old_name = null): bool
	{
		return Db::$db->change_column(
			'{db_prefix}' . $this->name,
			$old_name ?? $col->name,
			get_object_vars($col),
		);
	}

	/**
	 * Drops a column from this table in the database.
	 *
	 * @see SMF\Db\DatabaseApi::remove_column
	 *
	 * @param Column $col The column to drop.
	 * @return bool Whether or not the operation was successful.
	 */
	public function dropColumn(Column $col): bool
	{
		return Db::$db->remove_column(
			'{db_prefix}' . $this->name,
			$col->name,
		);
	}

	/**
	 * Adds an index to this table in the database.
	 *
	 * If the index already exists in the table, it will be updated.
	 *
	 * @see SMF\Db\DatabaseApi::add_index
	 *
	 * @param DbIndex $index The index to add to this table.
	 * @param string $if_exists What to do if the index exists.
	 *    If 'update', index is updated.
	 * @return bool Whether or not the operation was successful.
	 */
	public function addIndex(DbIndex $index, string $if_exists = 'update'): bool
	{
		$index_info = get_object_vars($index);

		// The column size values are used by Db::$db->create_table,
		// but not by Db::$db->add_index.
		$index_info['columns'] = array_map(
			fn($col) => preg_replace('/\(\d+\)$/', '', $col),
			$index_info['columns'],
		);

		return Db::$db->add_index(
			'{db_prefix}' . $this->name,
			$index_info,
			[],
			$if_exists,
		);
	}

	/**
	 * Drops an index from this table in the database.
	 *
	 * @see SMF\Db\DatabaseApi::remove_index
	 *
	 * @param DbIndex $index The index to drop.
	 * @return bool Whether or not the operation was successful.
	 */
	public function dropIndex(DbIndex $index): bool
	{
		return Db::$db->remove_index(
			'{db_prefix}' . $this->name,
			$index->name,
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets all known table schemas.
	 *
	 * @return array All known table schemas.
	 */
	final public static function getAll(string $schema_version): array
	{
		$tables = [];

		$file_list = new \GlobIterator(__DIR__ . '/' . $schema_version . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($file_list as $file_path => $file_info) {
			$class_name = $file_info->getBasename('.php');
			$fully_qualified_class_name = __NAMESPACE__ . '\\' . $schema_version . '\\' . $class_name;

			if (!class_exists($fully_qualified_class_name)) {
				continue;
			}

			$table = new $fully_qualified_class_name();

			if ($table instanceof Table) {
				$tables[$table->name] = $table;
			}
		}

		return $tables;
	}
}
