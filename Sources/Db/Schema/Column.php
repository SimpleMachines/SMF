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
 * Represents a column in a database table.
 */
class Column
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the column.
	 */
	public string $name;

	/**
	 * @var string
	 *
	 * Data type of the column.
	 */
	public string $type;

	/**
	 * @var ?int
	 *
	 * Size of the column.
	 * Only applicable to some data types.
	 */
	public ?int $size;

	/**
	 * @var ?bool
	 *
	 * Whether the column uses unsigned numerical values.
	 * Only applicable in MySQL.
	 */
	public ?bool $unsigned;

	/**
	 * @var ?bool
	 *
	 * Whether the column disallows null values.
	 */
	public ?bool $not_null;

	/**
	 * @var mixed
	 *
	 * Default value of the column.
	 */
	public mixed $default;

	/**
	 * @var ?bool
	 *
	 * Whether this is an automatically incrementing column.
	 * Only applicable to integer columns.
	 */
	public ?bool $auto;

	/**
	 * @var ?string
	 *
	 * The character set for string data.
	 * Only applicable to string types.
	 */
	public ?string $charset;

	/**
	 * @var ?bool
	 *
	 * Set this to true to drop the default during an ALTER TABLE operation.
	 *
	 * This is not set by __construct(). It can be set afterward.
	 */
	public bool $drop_default = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $name Name of the column.
	 * @param string $type Data type of the column.
	 * @param ?int $size Size of the column.
	 *    Only applicable to some data types.
	 * @param ?bool $unsigned Whether the column uses unsigned numerical values.
	 *    Only used by MySQL.
	 * @param ?bool $not_null Whether the column disallows null values.
	 * @param mixed $default Default value of the column. If null, no default
	 *    will be set. To set an explicit null default, use the string 'NULL'.
	 * @param ?bool $auto Whether this is an automatically incrementing column.
	 *    Only applicable to integer columns.
	 * @param ?string $charset The character set for string data.
	 *    Only applicable to string types. If null, will be set automatically.
	 */
	public function __construct(
		string $name,
		string $type,
		?int $size = null,
		?bool $unsigned = null,
		?bool $not_null = null,
		mixed $default = null,
		?bool $auto = null,
		?string $charset = null,
	) {
		$this->name = strtolower($name);
		$this->type = strtolower($type);

		if (isset($default)) {
			$this->default = $default === 'NULL' ? null : $default;
		}

		foreach (['auto', 'size', 'unsigned', 'not_null'] as $var) {
			if (isset($var)) {
				$this->{$var} = ${$var};
			}
		}

		if (isset($charset)) {
			$this->charset = strtolower($charset);
		}
	}

	/**
	 * Adds this column to the specified table.
	 *
	 * @see SMF\Db\DatabaseApi::add_column
	 *
	 * @param string $table_name The name of the table to add the column to.
	 * @param string $if_exists What to do if the column exists.
	 *    If 'update', column is updated.
	 * @return bool Whether or not the operation was successful.
	 */
	public function add(string $table_name, string $if_exists = 'update'): bool
	{
		return Db::$db->add_column(
			$table_name,
			get_object_vars($this),
			[],
			$if_exists,
		);
	}

	/**
	 * Updates the column in the database to match the definition given by this
	 * object's properties.
	 *
	 * @see SMF\Db\DatabaseApi::change_column
	 *
	 * @param string $table_name Name of the table that contains this column.
	 * @return bool Whether or not the operation was successful.
	 */
	public function alter(string $table_name): bool
	{
		return Db::$db->change_column(
			$table_name,
			$this->name,
			get_object_vars($this),
		);
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
		return Db::$db->remove_column(
			$table_name,
			$this->name,
		);
	}
}

?>