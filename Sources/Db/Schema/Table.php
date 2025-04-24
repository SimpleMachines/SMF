<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema;

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
	 * An array of SMF\Maintenance\Database\Schema\Column objects.
	 */
	public array $columns;

	/**
	 * @var array
	 *
	 * An array of SMF\Maintenance\Database\Schema\DbIndex objects.
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
		$this->default_charset = Db::$db->title === MYSQL_TITLE ? 'utf8mb4' : 'utf8';
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
		if (!isset($this->columns) || count($this->columns) === 0) {
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
	 * Updates an index in the database to match the definition given by the
	 * supplied object's properties.
	 *
	 * @param DbIndex $index The index to update.
	 * @return bool Whether or not the operation was successful.
	 */
	public function alterIndex(DbIndex $index): bool
	{
		// This method is really just a convenient way to replace an existing index.
		$this->dropIndex($index);

		return $this->addIndex($index);
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

		$file_list = new \GlobIterator(__DIR__ . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

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
