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
 * Represents an index in a database table.
 */
class Indices
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Columns to include in the index.
	 *
	 * Values should be the names of columns, optionally with an index length
	 * appended in parentheses.
	 *
	 * Example: ['id_msg', 'member_groups(48)']
	 */
	public array $columns = [];

	/**
	 * @var bool
	 *
	 * Allowed values: 'primary', 'unique', or null for a normal index.
	 */
	public ?string $type;

	/**
	 * @var ?string
	 *
	 * The name of the index.
	 */
	public ?string $name;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $columns Columns to include in the index.
	 * @param ?string $type The type of index. Either 'primary' for the PRIMARY
	 *    KEY index, 'unique' for a UNIQUE index, or null for a normal index.
	 * @param ?string $name The name of the index. If this is left null, a name
	 *    will be generated automatically.
	 */
	public function __construct(
		array $columns,
		?string $type = null,
		?string $name = null,
	) {
		$this->columns = array_map('strtolower', $columns);

		$this->type = isset($type) ? strtolower((string) $type) : null;

		if (($this->type ?? null) !== 'primary') {
			$this->name = $name ?? 'idx_' . trim(implode('_', preg_replace(['/\s*/', '/\(\d+\)/'], ['', ''], $this->columns)));
		}
	}

	/**
	 * Adds this index to the specified table.
	 *
	 * @see SMF\Db\DatabaseApi::add_index
	 *
	 * @param string $table_name The name of the table to add the index to.
	 * @param string $if_exists What to do if the index exists.
	 *    If 'update', index is updated.
	 * @return bool Whether or not the operation was successful.
	 */
	public function add(string $table_name, string $if_exists = 'update'): bool
	{
		return Db::$db->add_index(
			$table_name,
			get_object_vars($this),
			[],
			$if_exists,
		);
	}

	/**
	 * Updates the index in the database to match the definition given by this
	 * object's properties.
	 *
	 * @param string $table_name Name of the table that contains this index.
	 * @return bool Whether or not the operation was successful.
	 */
	public function alter(string $table_name): bool
	{
		// This method is really just a convenient way to replace an existing index.
		$this->drop($table_name);

		return $this->add($table_name);
	}

	/**
	 * Drops this column from the specified table.
	 *
	 * @see SMF\Db\DatabaseApi::remove_column
	 *
	 * @param string $table_name The name of the table to drop the column from.
	 * @return bool Whether or not the operation was successful.
	 */
	public function drop(string $table_name): bool
	{
		return Db::$db->remove_index(
			$table_name,
			$this->name,
		);
	}
}

?>