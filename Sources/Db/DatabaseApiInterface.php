<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Db;

/**
 * Interface DatabaseApiInterface
 */
interface DatabaseApiInterface
{
	/**
	 * Performs a query. Takes care of errors too.
	 *
	 * @param string $identifier An identifier. Only used in PostgreSQL.
	 * @param string $db_string The database string
	 * @param array $db_values = array() The values to be inserted into the string
	 * @param object $connection = null The connection to use (null to use $db_connection)
	 * @return object|bool Returns a query result resource (for SELECT queries), true (for UPDATE queries) or false if the query failed.
	 */
	public function query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null): object|bool;

	/**
	 * Prepares a query string for execution, but does not perform the query.
	 *
	 * @param string $db_string The database string.
	 * @param array $db_values An array of values to be injected into the string.
	 * @param object $connection = null The connection to use (null to use $db_connection).
	 * @return string The string with the values inserted.
	 */
	public function quote(string $db_string, array $db_values, ?object $connection = null): string;

	/**
	 * Fetch the next row of a result set as an enumerated array.
	 *
	 * @param object $request A query result resource.
	 * @return array|false One row of data, with numeric keys.
	 */
	public function fetch_row(object $result): array|false|null;

	/**
	 * Fetch the next row of a result set as an associative array.
	 *
	 * @param object $request A query result resource.
	 * @return array One row of data, with string keys.
	 */
	public function fetch_assoc(object $result): array|false|null;

	/**
	 * Fetches all rows from a result as an array.
	 *
	 * @param object $request A query result resource.
	 * @return array An array that contains all rows (records) in the result resource.
	 */
	public function fetch_all(object $request): array;

	/**
	 * Frees the memory and data associated with the query result.
	 */
	public function free_result(object $result): bool;

	/**
	 * Gets the ID of the most recently inserted row.
	 *
	 * @param string $method INSERT or REPLACE.
	 * @param string $table The table (only used for Postgres).
	 * @param array $columns An array of the columns we're inserting the data into. Should contain 'column' => 'datatype' pairs.
	 * @param array $data The data to insert.
	 * @param array $keys The keys for the table, needs to be not empty on replace mode.
	 * @param object $connection = null The connection (if null, $db_connection is used).
	 * @param int returnmode 0 = nothing(default), 1 = last row id, 2 = all rows id as array.
	 * @return int The ID of the most recently inserted row.
	 */
	public function insert(string $method, string $table, array $columns, array $data, array $keys, int $returnmode = 0, ?object $connection = null): int|array|null;

	/**
	 * Gets the ID of the most recently inserted row.
	 *
	 * @param string $table The table (only used for Postgres)
	 * @param string $field = null The specific field (not used here)
	 * @param object $connection = null The connection (if null, $db_connection is used)
	 * @return int The ID of the most recently inserted row
	 */
	public function insert_id(string $table, ?string $field = null, ?object $connection = null): int;

	/**
	 * Gets the number of rows in a result set.
	 *
	 * @param object $request A query result resource.
	 * @return int The number of rows in the result.
	 */
	public function num_rows(object $result): int;

	/**
	 * Adjusts the result pointer to an arbitrary row in a query result.
	 *
	 * @param int $offset The row offset.
	 * @param object $request A query result resource.
	 * @return bool True on success, or false on failuer.
	 */
	public function data_seek(object $result, int $offset): bool;

	/**
	 * Gets the number of fields in a result set.
	 *
	 * @param object $request A query result resource.
	 * @return int The number of fields (columns) in the result.
	 */
	public function num_fields(object $result): int;

	/**
	 * Escapes special characters in a string for use in an SQL statement,
	 * taking into account the current character set of the connection.
	 *
	 * @param object $connection = null The connection to use (null to use $db_connection).
	 * @param string The unescaped string.
	 * @return string The escaped string.
	 */
	public function escape_string(string $string, ?object $connection = null): string;

	/**
	 * Reverses the escape_string function.
	 *
	 * @param string The escaped string.
	 * @return string The unescaped string.
	 */
	public function unescape_string(string $string): string;

	/**
	 * Gets information, such as the version, about the database server.
	 *
	 * @param object $connection The connection to use (if null, $db_connection is used)
	 * @return string The server info.
	 */
	public function server_info(?object $connection = null): string;

	/**
	 * Gets the number of rows affected by the last query.
	 *
	 * @todo PostgreSQL requires a $result param, not a $connection.
	 *
	 * @param object $connection A connection to use (if null, $db_connection is used)
	 * @return int The number of affected rows.
	 */
	public function affected_rows(?object $connection = null): int;

	/**
	 * Do a transaction.
	 *
	 * @param string $type The step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param object $connection The connection to use (if null, $db_connection is used)
	 * @return bool True if successful, false otherwise
	 */
	public function transaction(string $type = 'commit', ?object $connection = null): bool;

	/**
	 * Get the last error message string.
	 *
	 * @param object $connection The connection to use (if null, $db_connection is used)
	 * @return string The last error message.
	 */
	public function error(object $connection): string;

	/**
	 * Selects the default database for database queries.
	 *
	 * Does nothing on PostgreSQL.
	 *
	 * @param string &$database The database
	 * @param object $connection The connection object (if null, $db_connection is used)
	 * @return bool Whether the database was selected
	 */
	public function select(string $database, ?object $connection = null): bool;

	/**
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 *
	 * @param string $string The string to escape
	 * @param bool $translate_human_wildcards If true, turns human readable wildcards into SQL wildcards.
	 * @return string The escaped string
	 */
	public function escape_wildcard_string(string $string, bool $translate_human_wildcards = false): string;

	/**
	 * Validates whether the resource is a valid mysqli instance.
	 * Mysqli uses objects rather than resource. https://bugs.php.net/bug.php?id=42797
	 *
	 * @param mixed $result The string to test
	 * @return bool True if it is, false otherwise
	 */
	public function is_resource(mixed $result): bool;

	/**
	 * Pings a server connection, and tries to reconnect if necessary.
	 *
	 * @param object $connection The connection object (if null, $db_connection is used)
	 * @return bool True on success, or false on failure.
	 */
	public function ping(?object $connection = null): bool;

	/**
	 * Save errors in the database safely.
	 *
	 * $error_array must have the following keys in order:
	 * id_member, log_time, ip, url, message, session, error_type, file, line, backtrace
	 *
	 * @param array Information about the error.
	 */
	public function error_insert(array $error_array): void;

	/**
	 * Function which constructs an optimize custom order string
	 * as an improved alternative to find_in_set()
	 *
	 * @param string $field name
	 * @param array $array_values Field values sequenced in array via order priority. Must cast to int.
	 * @param bool $desc default false
	 * @return string case field when ... then ... end
	 */
	public function custom_order(string $field, array $array_values, bool $desc = false): string;

	/**
	 * Function which return the information if the database supports native replace inserts
	 *
	 * @return bool true or false
	 */
	public function native_replace(): bool;

	/**
	 * Function which return the information if the database supports cte with recursive
	 *
	 * @return bool true or false
	 */
	public function cte_support(): bool;

	/**
	 * Gets a description of the last connection error.
	 *
	 * @return string Error message from the last connection attempt.
	 */
	public function connect_error(): string;

	/**
	 * Gets the error code of last connection error.
	 *
	 * @return int Error code from the last connection attempt.
	 */
	public function connect_errno(): int;

	/****************************************
	 * Methods that formerly lived in DbExtra
	 ****************************************/

	/**
	 * Backup $table to $backup_table.
	 *
	 * @param string $table The name of the table to backup
	 * @param string $backup_table The name of the backup table for this table
	 * @return resource -the request handle to the table creation query
	 */
	public function backup_table(string $table, string $backup_table): object;

	/**
	 * This function optimizes a table.
	 *
	 * @param string $table The table to be optimized
	 * @return int How much space was gained
	 */
	public function optimize_table(string $table): int|float;

	/**
	 * Dumps the schema (CREATE) for a table.
	 *
	 * @todo why is this needed for?
	 * @param string $tableName The name of the table
	 * @return string The "CREATE TABLE" SQL string for this table
	 */
	public function table_sql(string $tableName): string;

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param string|bool $db string The database name or false to use the current DB
	 * @param string|bool $filter String to filter by or false to list all tables
	 * @return array An array of table names
	 */
	public function list_tables(string|bool $db = false, string|bool $filter = false): array;

	/**
	 *  Get the version number.
	 *
	 * @return string The version
	 */
	public function get_version(): string;

	/**
	 * Figures out if we are using MySQL, Percona or MariaDB
	 *
	 * @return string The database engine we are using
	 */
	public function get_vendor(): string;

	/**
	 * Figures out if persistent connection is allowed
	 *
	 * @return bool
	 */
	public function allow_persistent(): bool;

	/*****************************************
	 * Methods that formerly lived in DbSearch
	 *****************************************/

	/**
	 * Returns the correct query for this search type.
	 *
	 * @param string $identifier A query identifier
	 * @param string $db_string The query text
	 * @param array $db_values An array of values to pass to $this->query()
	 * @param object $connection The current DB connection resource
	 * @return resource The query result resource from $this->query()
	 */
	public function search_query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null): object|bool;

	/**
	 * This function will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type The search type.
	 * @return bool Whether or not the specified search type is supported by this db system
	 */
	public function search_support(string $search_type): bool;

	/**
	 * Highly specific function, to create the custom word index table.
	 *
	 * @param string $size The column size type (int, mediumint (8), etc.). Not used here.
	 */
	public function create_word_search(string $size): void;

	/**
	 * Return the language for the textsearch index
	 *
	 * @return string|null The PostgreSQL search language, or null for MySQL.
	 */
	public function search_language(): ?string;

	/*******************************************
	 * Methods that formerly lived in DbPackages
	 *******************************************/

	/**
	 * This function adds a column.
	 *
	 * @param string $table_name The name of the table to add the column to
	 * @param array $column_info An array of column info ({@see smf_db_create_table})
	 * @param array $parameters Not used?
	 * @param string $if_exists What to do if the column exists. If 'update', column is updated.
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function add_column(string $table_name, array $column_info, array $parameters = [], string $if_exists = 'update', string $error = 'fatal'): bool;

	/**
	 * Add an index.
	 *
	 * @param string $table_name The name of the table to add the index to
	 * @param array $index_info An array of index info (see {@link smf_db_create_table()})
	 * @param array $parameters Not used?
	 * @param string $if_exists What to do if the index exists. If 'update', the definition will be updated.
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function add_index(string $table_name, array $index_info, array $parameters = [], string $if_exists = 'update', string $error = 'fatal'): bool;

	/**
	 * Get the schema formatted name for a type.
	 *
	 * @param string $type_name The data type (int, varchar, smallint, etc.)
	 * @param int $type_size The size (8, 255, etc.)
	 * @param bool $reverse
	 * @return array An array containing the appropriate type and size for this DB type
	 */
	public function calculate_type(string $type_name, ?int $type_size = null, bool $reverse = false): array;

	/**
	 * Change a column.  You only need to specify the column attributes that are changing.
	 *
	 * @param string $table_name The name of the table this column is in
	 * @param string $old_column The name of the column we want to change
	 * @param array $column_info An array of info about the "new" column definition (see {@link smf_db_create_table()})
	 * Note that $column_info also supports two additional parameters that only make sense when changing columns:
	 * - drop_default - to drop a default that was previously specified
	 * @return bool
	 */
	public function change_column(string $table_name, string $old_column, array $column_info): bool;

	/**
	 * This function can be used to create a table without worrying about schema
	 *  compatibilities across supported database systems.
	 *  - If the table exists will, by default, do nothing.
	 *  - Builds table with columns as passed to it - at least one column must be sent.
	 *  The columns array should have one sub-array for each column - these sub arrays contain:
	 *  	'name' = Column name
	 *  	'type' = Type of column - values from (smallint, mediumint, int, text, varchar, char, tinytext, mediumtext, largetext)
	 *  	'size' => Size of column (If applicable) - for example 255 for a large varchar, 10 for an int etc.
	 *  		If not set SMF will pick a size.
	 *  	- 'default' = Default value - do not set if no default required.
	 *  	- 'not_null' => Can it be null (true or false) - if not set default will be false.
	 *  	- 'auto' => Set to true to make it an auto incrementing column. Set to a numerical value to set from what
	 *  		 it should begin counting.
	 *  - Adds indexes as specified within indexes parameter. Each index should be a member of $indexes. Values are:
	 *  	- 'name' => Index name (If left empty SMF will generate).
	 *  	- 'type' => Type of index. Choose from 'primary', 'unique' or 'index'. If not set will default to 'index'.
	 *  	- 'columns' => Array containing columns that form part of key - in the order the index is to be created.
	 *  - parameters: (None yet)
	 *  - if_exists values:
	 *  	- 'ignore' will do nothing if the table exists. (And will return true)
	 *  	- 'overwrite' will drop any existing table of the same name.
	 *  	- 'error' will return false if the table already exists.
	 *  	- 'update' will update the table if the table already exists (no change of ai field and only colums with the same name keep the data)
	 *
	 * @param string $table_name The name of the table to create
	 * @param array $columns An array of column info in the specified format
	 * @param array $indexes An array of index info in the specified format
	 * @param array $parameters Extra parameters. Currently only 'engine', the desired MySQL storage engine, is used.
	 * @param string $if_exists What to do if the table exists.
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function create_table(string $table_name, array $columns, array $indexes = [], array $parameters = [], string $if_exists = 'ignore', string $error = 'fatal'): bool;

	/**
	 * Drop a table.
	 *
	 * @param string $table_name The name of the table to drop
	 * @param array $parameters Not used at the moment
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function drop_table(string $table_name, array $parameters = [], string $error = 'fatal'): bool;

	/**
	 * Get table structure.
	 *
	 * @param string $table_name The name of the table
	 * @return array An array of table structure - the name, the column info from {@link smf_db_list_columns()} and the index info from {@link smf_db_list_indexes()}
	 */
	public function table_structure(string $table_name): array;

	/**
	 * Return column information for a table.
	 *
	 * @param string $table_name The name of the table to get column info for
	 * @param bool $detail Whether or not to return detailed info. If true, returns the column info. If false, just returns the column names.
	 * @param array $parameters Not used?
	 * @return array An array of column names or detailed column info, depending on $detail
	 */
	public function list_columns(string $table_name, bool $detail = false, array $parameters = []): array;

	/**
	 * Get index information.
	 *
	 * @param string $table_name The name of the table to get indexes for
	 * @param bool $detail Whether or not to return detailed info.
	 * @param array $parameters Not used?
	 * @return array An array of index names or a detailed array of index info, depending on $detail
	 */
	public function list_indexes(string $table_name, bool $detail = false, array $parameters = []): array;

	/**
	 * Removes a column.
	 *
	 * @param string $table_name The name of the table to drop the column from
	 * @param string $column_name The name of the column to drop
	 * @param array $parameters Not used?
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function remove_column(string $table_name, string $column_name, array $parameters = [], string $error = 'fatal'): bool;

	/**
	 * Remove an index.
	 *
	 * @param string $table_name The name of the table to remove the index from
	 * @param string $index_name The name of the index to remove
	 * @param array $parameters Not used?
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function remove_index(string $table_name, string $index_name, array $parameters = [], string $error = 'fatal'): bool;
}

?>