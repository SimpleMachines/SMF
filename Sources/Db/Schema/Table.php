<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Db\Schema;

use SMF\Db\DatabaseApi as Db;

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
	 * @var \SMF\Db\Schema\Column[]
	 *
	 * An array of SMF\Db\Schema\Column objects.
	 */
	public array $columns;

	/**
	 * @var \SMF\Db\Schema\Index[]
	 *
	 * An array of SMF\Db\Schema\Index objects.
	 */
	public array $indices = [];

	/**
	 * @var array
	 *
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [];

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
	 *
	 * Only needed if the table should override the database's default charset.
	 *
	 * This is not set by __construct(). It can be set afterward.
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
			$this->name,
			array_map('get_object_vars', $this->columns),
			array_map('get_object_vars', $this->indices),
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
		return Db::$db->drop_table($this->name);
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
	public function getStructure(): array
	{
		return Db::$db->table_structure($this->name);
	}
}

?>