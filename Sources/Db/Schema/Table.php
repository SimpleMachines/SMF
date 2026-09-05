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

namespace SMF\Db\Schema;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;

/**
 * Represents a database table.
 */
abstract class Table
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
	public ?string $default_charset {
		get {
			// As of SMF 3.0, all tables always use four-byte UTF-8.
			if (
				!isset($this->default_charset)
				&& preg_match('/\\\\v(\d+_\d+)\\\\/', $this::class, $matches)
				&& version_compare(strtr($matches[1], '_', '.'), '3.0', '>=')
			) {
				$this->default_charset = Db::$db->title === MYSQL_TITLE ? 'utf8mb4' : 'utf8';
			}

			return $this->default_charset ?? null;
		}
	}

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

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Cached output of Db::$db->list_tables
	 */
	protected static array $existing_tables = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks whether a table with this name exists in the database.
	 *
	 * @param bool $force_refresh If true, force a refresh of the tables list.
	 * @return bool Whether this table exists.
	 */
	public function exists(bool $force_refresh = false)
	{
		if ($force_refresh || empty(self::$existing_tables)) {
			self::$existing_tables = Db::$db->list_tables();
		}

		return \in_array(Db::$db->prefix . $this->name, self::$existing_tables);
	}

	/**
	 * Checks all the columns and indexes in this table to make sure they
	 * are defined the way they should be, and fixes any that aren't.
	 *
	 * @return bool Whether or not the operation was successful.
	 */
	public function normalize(): bool
	{
		if (\count($this->columns) === 0) {
			return false;
		}

		if (!$this->exists()) {
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
							\in_array($value, $int_types)
							&& \in_array($col->type, $int_types)
							&& array_search($value, $int_types) > array_search($col->type, $int_types)
						)
						// If an existing string column upgraded its type to a
						// larger one, we want to keep that larger type.
						// This is only applicable to MySQL.
						|| (
							Db::$db->title === MYSQL_TITLE
							&& (
								\in_array($value, $text_types)
								&& \in_array($col->type, $text_types)
								&& array_search($value, $text_types) > array_search($col->type, $text_types)
							) || (
								\in_array($value, $blob_types)
								&& \in_array($col->type, $blob_types)
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

			foreach (['name', 'size', 'unsigned', 'generation_expression', 'stored', 'not_null', 'default', 'auto'] as $prop) {
				if (($structure['columns'][$col->name][$prop] ?? null) != ($col->{$prop} ?? null)) {
					$columns_to_change[$col->name] = $col;
					continue 2;
				}
			}
		}

		foreach ($this->indexes as $index) {
			if (
				!isset($structure['indexes'][$index->name])
				&& !$this->fixIndexName($index)
			) {
				$indexes_to_change[$index->name] = $index;
				continue;
			}

			if (!isset($structure['indexes'][$index->name]) || ($index->type ?? 'index') != $structure['indexes'][$index->name]['type']) {
				$indexes_to_change[$index->name] = $index;
				continue;
			}

			// If we need to change any columns in this index, rebuild the index too.
			//
			// Both lists hold their column names as values. The definition
			// holds ['name' => ...] arrays, and the database reports plain
			// strings, to which MySQL adds a "(15)" prefix length. Reduce each
			// to bare names, since $columns_to_change is keyed by name.
			foreach (
				[
					array_column($index->columns, 'name'),
					array_map(
						fn($col) => preg_replace('~\s*\(\d+\)$~', '', $col),
						$structure['indexes'][$index->name]['columns'],
					),
				] as $cols
			) {
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
		if (\count($this->columns) === 0) {
			return false;
		}

		$success = Db::$db->create_table(
			'{db_prefix}' . $this->name,
			array_map('get_object_vars', array_values($this->columns)),
			array_map('get_object_vars', array_values($this->indexes)),
			$parameters,
			$if_exists,
		);

		if ($success) {
			// Force a refresh of the list of tables.
			self::$existing_tables = [];
		}

		return $success;
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
		$success = Db::$db->drop_table('{db_prefix}' . $this->name);

		if ($success) {
			// Force a refresh of the list of tables.
			self::$existing_tables = [];
		}

		return $success;
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
	 * @param string|Column $col The column to drop. May be either an instance
	 *    of the Column class, or just the name of the column.
	 * @return bool Whether or not the operation was successful.
	 */
	public function dropColumn(string|Column $col): bool
	{
		return Db::$db->remove_column(
			'{db_prefix}' . $this->name,
			$col instanceof Column ? $col->name : $col,
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
		return Db::$db->add_index(
			'{db_prefix}' . $this->name,
			get_object_vars($index),
			[],
			$if_exists,
		);
	}

	/**
	 * Ensures an existing index in the database is using the correct name.
	 *
	 * Searches all the existing indexes for this table for the one that has the
	 * same type and columns as the passed DbIndex object. If no matching index
	 * is found, returns false. Otherwise, if the matching index is not using
	 * the name defined in the DbIndex object, renames the matching index.
	 *
	 * @param DbIndex $index The index to set the name of.
	 * @return bool Whether the existing index now has the correct name.
	 */
	public function fixIndexName(DbIndex $index): bool
	{
		foreach (Db::$db->list_indexes('{db_prefix}' . $this->name, true) as $existing_index) {
			// Must be the same type. A normal index carries no type of its own,
			// which the database reports as 'index'.
			if (($index->type ?? 'index') !== $existing_index['type']) {
				continue;
			}

			// Must index the same columns.
			if (array_map(fn($col) => $col['name'], $index->columns) !== $existing_index['columns']) {
				continue;
			}

			// There's no need to rename the primary key.
			if ($index->type === 'primary' && $existing_index['type'] === 'primary') {
				return true;
			}

			// If the name is already the same, there's nothing to do.
			if ($index->name === $existing_index['name']) {
				return true;
			}

			// Do the rename.
			return Db::$db->rename_index(
				table_name: '{db_prefix}' . $this->name,
				old_name: $existing_index['name'],
				new_name: $index->name,
			);
		}

		// No matching index was found.
		return false;
	}

	/**
	 * Drops an index from this table in the database.
	 *
	 * @see SMF\Db\DatabaseApi::remove_index
	 *
	 * @param string|DbIndex $index The index to drop. May be either an instance
	 *    of the DbIndex class, or just the name of the index.
	 * @return bool Whether or not the operation was successful.
	 */
	public function dropIndex(string|DbIndex $index): bool
	{
		return Db::$db->remove_index(
			'{db_prefix}' . $this->name,
			$index instanceof DbIndex ? $index->name : $index,
		);
	}

	/**
	 * Inserts initial data into the table.
	 *
	 * @param bool $replace Whether to replace rows that have ID conflicts.
	 *    Default: false.
	 * @return int Number of inserted rows.
	 */
	public function populate(bool $replace = false): int
	{
		if (empty($this->initial_data)) {
			return 0;
		}

		// Does this table auto-increment?
		$auto_col = null;

		foreach ($this->columns as $column) {
			if (!empty($column->auto)) {
				$auto_col = $column->name;
				break;
			}
		}

		$method = $replace ? 'replace' : 'ignore';
		$returnmode = isset($auto_col) ? Db::INSERT_RETURN_MODE_MULTI : Db::INSERT_RETURN_MODE_OFF;

		// Only do this if we are replacing data or the table is empty.
		if ($method !== 'replace') {
			$request = Db::$db->query(
				'SELECT COUNT(*)
				FROM {db_prefix}{raw:table}',
				[
					'table' => $this->name,
				],
			);
			list($num_rows) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if ($num_rows > 0) {
				return 0;
			}
		}

		// Get the correct values for any placeholders.
		Lang::load('General+Maintenance', Config::$language);

		$replacements = [
			'{$db_prefix}' => Db::$db->prefix,
			'{$attachdir}' => Config::$modSettings['attachmentUploadDir'] ?? json_encode([1 => Db::$db->escape_string(Config::$boarddir . '/attachments')]),
			'{$boarddir}' => Db::$db->escape_string(Config::$boarddir),
			'{$boardurl}' => Config::$boardurl,
			'{$enableCompressedOutput}' => \defined('SMF_INSTALLING') ? ((int) !empty($_POST['compress'])) : (Config::$modSettings['enableCompressedOutput'] ?? 0),
			'{$databaseSession_enable}' => \defined('SMF_INSTALLING') ? ((int) !empty($_POST['dbsession'])) : (Config::$modSettings['databaseSession_enable'] ?? 0),
			'{$smf_version}' => SMF_VERSION,
			'{$current_time}' => time(),
			'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
			'{$registration_method}' => \defined('SMF_INSTALLING') ? ((int) !empty($_POST['reg_mode'])) : (Config::$modSettings['registration_method'] ?? 0),
		];

		// Sometimes its a string, sometimes its an array, sometimes its json.
		if (\is_array($replacements['{$attachdir}'])) {
			$replacements['{$attachdir}'] = json_encode($replacements['{$attachdir}']);
		}

		foreach (Lang::$txt as $key => $value) {
			if (substr($key, 0, 8) == 'default_') {
				$replacements['{$' . $key . '}'] = Db::$db->escape_string($value);
			}
		}

		$replacements['{$default_reserved_names}'] = strtr($replacements['{$default_reserved_names}'], ['\\\\n' => "\n", '\\n' => "\n"]);

		// Replace any placeholders in the initial data.
		foreach ($this->initial_data as &$row) {
			foreach ($row as &$val) {
				if ($val === null) {
					continue;
				}

				if (\is_int($val)) {
					$val = (int) strtr((string) $val, $replacements);
				} else {
					$val = strtr($val, $replacements);
				}
			}
		}

		// Insert the initial data.
		$ids = Db::$db->insert(
			method: $method,
			table: '{db_prefix}' . $this->name,
			columns: Db::$db->getTypeIndicators('{db_prefix}' . $this->name, reset($this->initial_data)),
			data: array_map(fn($row) => array_values($row), $this->initial_data),
			keys: isset($auto_col) ? [$auto_col] : array_column($this->indexes['primary']->columns, 'name'),
			returnmode: $returnmode,
		);

		$num_inserts = \count($ids ?? $this->initial_data);

		if (isset($auto_col)) {
			$this->resyncAutoIncrement($auto_col);
		}

		return $num_inserts;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets all known table schemas.
	 *
	 * @param string $schema_version E.g. 'v3_0'.
	 * @return array All known table schemas for the given schema version.
	 */
	final public static function getAll(string $schema_version): array
	{
		$tables = [];

		$file_list = new \GlobIterator(__DIR__ . '/' . $schema_version . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($file_list as $file_path => $file_info) {
			if ($file_info->getBasename() === 'index.php') {
				continue;
			}

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

	/**
	 * Finds and returns the table schema class for the specified table name.
	 *
	 * @param string $table_name The name of the table.
	 * @param string $schema_version E.g. 'v3_0'.
	 * @return ?self An instance of this class, or null if nothing was found.
	 */
	final public static function find(string $table_name, string $schema_version): ?self
	{
		// Strip off the prefix.
		foreach (['{db_prefix}', Config::$db_prefix, Db::$db?->prefix ?? ''] as $prefix) {
			if (str_starts_with($table_name, $prefix)) {
				$table_name = substr($table_name, \strlen($prefix));
				break;
			}
		}

		// Try to reverse-engineer the class name from the table name.
		$fully_qualified_class_name = __NAMESPACE__ . '\\' . $schema_version . '\\' . ucfirst(preg_replace_callback(
			'/_(\w)/',
			fn($matches) => strtoupper($matches[1]),
			$table_name,
		));

		// Does it exist?
		if (class_exists($fully_qualified_class_name)) {
			return new $fully_qualified_class_name();
		}

		// Failed to reverse-engineer the class name, so loop through them all
		// to see if we can find a match that way.
		foreach (self::getAll($schema_version) as $table) {
			if ($table->name === $table_name) {
				return $table;
			}
		}

		// Couldn't find it.
		return null;
	}

	/**
	 * Gets database initializer queries for the indicated SMF version.
	 *
	 * @param string $schema_version E.g. 'v3_0'.
	 * @return array All known table schemas.
	 */
	final public static function getInitializers(string $schema_version): array
	{
		if (file_exists(__DIR__ . '/' . $schema_version . '/Initialize/' . Db::$db->title . '.php')) {

			$fully_qualified_class_name = __NAMESPACE__ . '\\' . $schema_version . '\\Initialize\\' . Db::$db->title;

			if (!class_exists($fully_qualified_class_name)) {
				return [];
			}

			$intializer = new $fully_qualified_class_name(Db::$db->get_version());

			return $intializer->getAll();
		}

		return [];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Points the table's ID generator past the rows that were just inserted.
	 *
	 * The initial data carries its own IDs — the default board is board 1, and
	 * plenty of other rows are referred to by number elsewhere in it — so they
	 * are supplied rather than generated.
	 *
	 * MySQL notices that and moves AUTO_INCREMENT along by itself. PostgreSQL
	 * does not: a sequence only advances when something calls nextval() on it,
	 * and nothing has, so it still sits at 1 and hands 1 to the next insert.
	 * That collides with the row already there, and the insert fails on the
	 * primary key. The first topic anybody starts on a new forum is the usual
	 * way to meet this, and it fails once and then works, because the failed
	 * attempt consumed the 1 and the retry gets 2.
	 *
	 * The 2.1 upgrade path has had this fix for years, in the PostgreSqlSequences
	 * migration. This is the same thing for a fresh install.
	 *
	 * @param string $auto_col Name of the auto-incrementing column.
	 */
	private function resyncAutoIncrement(string $auto_col): void
	{
		if (Db::$db->title !== POSTGRE_TITLE) {
			return;
		}

		// COALESCE for the table that ended up empty after an 'ignore' insert:
		// setval() will not accept NULL, and 1 is where the sequence began.
		Db::$db->query(
			'SELECT setval(\'{raw:sequence}\', COALESCE((SELECT MAX({raw:column}) FROM {db_prefix}{raw:table}), 1))',
			[
				'sequence' => Db::$db->prefix . $this->name . '_seq',
				'column' => $auto_col,
				'table' => $this->name,
			],
		);
	}
}
