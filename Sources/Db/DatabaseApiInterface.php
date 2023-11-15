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
	 * @param resource $connection = null The connection to use (null to use $db_connection)
	 * @return resource|bool Returns a query result resource (for SELECT queries), true (for UPDATE queries) or false if the query failed.
	 */
	public function query($identifier, $db_string, $db_values = [], $connection = null);

	/**
	 * Prepares a query string for execution, but does not perform the query.
	 *
	 * @param string $db_string The database string.
	 * @param array $db_values An array of values to be injected into the string.
	 * @param resource $connection = null The connection to use (null to use $db_connection).
	 * @return string The string with the values inserted.
	 */
	public function quote($db_string, $db_values, $connection = null);

	/**
	 * Fetch the next row of a result set as an enumerated array.
	 *
	 * @param resource $request A query result resource.
	 * @return array One row of data, with numeric keys.
	 */
	public function fetch_row($result);

	/**
	 * Fetch the next row of a result set as an associative array.
	 *
	 * @param resource $request A query result resource.
	 * @return array One row of data, with string keys.
	 */
	public function fetch_assoc($result);

	/**
	 * Fetches all rows from a result as an array.
	 *
	 * @param resource $request A query result resource.
	 * @return array An array that contains all rows (records) in the result resource.
	 */
	public function fetch_all($request);

	/**
	 * Frees the memory and data associated with the query result.
	 */
	public function free_result($result);

	/**
	 * Gets the ID of the most recently inserted row.
	 *
	 * @param string $table The table (only used for Postgres).
	 * @param resource $connection = null The connection (if null, $db_connection is used).
	 * @param string $field = null The specific field (not used here).
	 * @return int The ID of the most recently inserted row.
	 */
	public function insert($method, $table, $columns, $data, $keys, $returnmode = 0, $connection = null);

	/**
	 * Gets the ID of the most recently inserted row.
	 *
	 * @param string $table The table (only used for Postgres)
	 * @param string $field = null The specific field (not used here)
	 * @param resource $connection = null The connection (if null, $db_connection is used)
	 * @return int The ID of the most recently inserted row
	 */
	public function insert_id($table, $field = null, $connection = null);

	/**
	 * Gets the number of rows in a result set.
	 *
	 * @param resource $request A query result resource.
	 * @return int The number of rows in the result.
	 */
	public function num_rows($result);

	/**
	 * Adjusts the result pointer to an arbitrary row in a query result.
	 *
	 * @param int $offset The row offset.
	 * @param resource $request A query result resource.
	 * @return bool True on success, or false on failuer.
	 */
	public function data_seek($result, int $offset);

	/**
	 * Gets the number of fields in a result set.
	 *
	 * @param resource $request A query result resource.
	 * @return int The number of fields (columns) in the result.
	 */
	public function num_fields($result);

	/**
	 * Escapes special characters in a string for use in an SQL statement,
	 * taking into account the current character set of the connection.
	 *
	 * @param resource $connection = null The connection to use (null to use $db_connection).
	 * @param string The unescaped string.
	 * @return string The escaped string.
	 */
	public function escape_string(string $string, $connection = null);

	/**
	 * Reverses the escape_string function.
	 *
	 * @param string The escaped string.
	 * @return string The unescaped string.
	 */
	public function unescape_string(string $string);

	/**
	 * Gets information, such as the version, about the database server.
	 *
	 * @param object $connection The connection to use (if null, $db_connection is used)
	 * @return string The server info.
	 */
	public function server_info($connection = null);

	/**
	 * Gets the number of rows affected by the last query.
	 *
	 * @todo PostgreSQL requires a $result param, not a $connection.
	 *
	 * @param resource $connection A connection to use (if null, $db_connection is used)
	 * @return int The number of affected rows.
	 */
	public function affected_rows($connection = null);

	/**
	 * Do a transaction.
	 *
	 * @param string $type The step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource $connection The connection to use (if null, $db_connection is used)
	 * @return bool True if successful, false otherwise
	 */
	public function transaction($type = 'commit', $connection = null);

	/**
	 * Get the last error message string.
	 *
	 * @param resource $connection The connection to use (if null, $db_connection is used)
	 * @return string The last error message.
	 */
	public function error($connection);

	/**
	 * Selects the default database for database queries.
	 *
	 * Does nothing on PostgreSQL.
	 *
	 * @param string &$database The database
	 * @param object $connection The connection object (if null, $db_connection is used)
	 * @return bool Whether the database was selected
	 */
	public function select($database, $connection = null);

	/**
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 *
	 * @param string $string The string to escape
	 * @param bool $translate_human_wildcards If true, turns human readable wildcards into SQL wildcards.
	 * @return string The escaped string
	 */
	public function escape_wildcard_string($string, $translate_human_wildcards = false);

	/**
	 * Validates whether the resource is a valid mysqli instance.
	 * Mysqli uses objects rather than resource. https://bugs.php.net/bug.php?id=42797
	 *
	 * @param mixed $result The string to test
	 * @return bool True if it is, false otherwise
	 */
	public function is_resource($result);

	/**
	 * Pings a server connection, and tries to reconnect if necessary.
	 *
	 * @param object $connection The connection object (if null, $db_connection is used)
	 * @return bool True on success, or false on failure.
	 */
	public function ping($connection = null);

	/**
	 * Save errors in the database safely.
	 *
	 * $error_array must have the following keys in order:
	 * id_member, log_time, ip, url, message, session, error_type, file, line, backtrace
	 *
	 * @param array Information about the error.
	 */
	public function error_insert($error_array);

	/**
	 * Function which constructs an optimize custom order string
	 * as an improved alternative to find_in_set()
	 *
	 * @param string $field name
	 * @param array $array_values Field values sequenced in array via order priority. Must cast to int.
	 * @param bool $desc default false
	 * @return string case field when ... then ... end
	 */
	public function custom_order($field, $array_values, $desc = false);

	/**
	 * Function which return the information if the database supports native replace inserts
	 *
	 * @return bool true or false
	 */
	public function native_replace();

	/**
	 * Function which return the information if the database supports cte with recursive
	 *
	 * @return bool true or false
	 */
	public function cte_support();

	/**
	 * Gets a description of the last connection error.
	 *
	 * @return string Error message from the last connection attempt.
	 */
	public function connect_error();

	/**
	 * Gets the error code of last connection error.
	 *
	 * @return int Error code from the last connection attempt.
	 */
	public function connect_errno();

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
	public function backup_table($table, $backup_table);

	/**
	 * This function optimizes a table.
	 *
	 * @param string $table The table to be optimized
	 * @return int How much space was gained
	 */
	public function optimize_table($table);

	/**
	 * Dumps the schema (CREATE) for a table.
	 *
	 * @todo why is this needed for?
	 * @param string $tableName The name of the table
	 * @return string The "CREATE TABLE" SQL string for this table
	 */
	public function table_sql($tableName);

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param string|bool $db string The database name or false to use the current DB
	 * @param string|bool $filter String to filter by or false to list all tables
	 * @return array An array of table names
	 */
	public function list_tables($db = false, $filter = false);

	/**
	 *  Get the version number.
	 *
	 * @return string The version
	 */
	public function get_version();

	/**
	 * Figures out if we are using MySQL, Percona or MariaDB
	 *
	 * @return string The database engine we are using
	 */
	public function get_vendor();

	/**
	 * Figures out if persistent connection is allowed
	 *
	 * @return bool
	 */
	public function allow_persistent();

	/*****************************************
	 * Methods that formerly lived in DbSearch
	 *****************************************/

	/**
	 * Returns the correct query for this search type.
	 *
	 * @param string $identifier A query identifier
	 * @param string $db_string The query text
	 * @param array $db_values An array of values to pass to $this->query()
	 * @param resource $connection The current DB connection resource
	 * @return resource The query result resource from $this->query()
	 */
	public function search_query($identifier, $db_string, $db_values = [], $connection = null);

	/**
	 * This function will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type The search type.
	 * @return bool Whether or not the specified search type is supported by this db system
	 */
	public function search_support($search_type);

	/**
	 * Highly specific function, to create the custom word index table.
	 *
	 * @param string $size The column size type (int, mediumint (8), etc.). Not used here.
	 */
	public function create_word_search($size);

	/**
	 * Return the language for the textsearch index
	 *
	 * @return string|null The PostgreSQL search language, or null for MySQL.
	 */
	public function search_language();

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
	public function add_column($table_name, $column_info, $parameters = [], $if_exists = 'update', $error = 'fatal');

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
	public function add_index($table_name, $index_info, $parameters = [], $if_exists = 'update', $error = 'fatal');

	/**
	 * Get the schema formatted name for a type.
	 *
	 * @param string $type_name The data type (int, varchar, smallint, etc.)
	 * @param int $type_size The size (8, 255, etc.)
	 * @param bool $reverse
	 * @return array An array containing the appropriate type and size for this DB type
	 */
	public function calculate_type($type_name, $type_size = null, $reverse = false);

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
	public function change_column($table_name, $old_column, $column_info);

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
	public function create_table($table_name, $columns, $indexes = [], $parameters = [], $if_exists = 'ignore', $error = 'fatal');

	/**
	 * Drop a table.
	 *
	 * @param string $table_name The name of the table to drop
	 * @param array $parameters Not used at the moment
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function drop_table($table_name, $parameters = [], $error = 'fatal');

	/**
	 * Get table structure.
	 *
	 * @param string $table_name The name of the table
	 * @return array An array of table structure - the name, the column info from {@link smf_db_list_columns()} and the index info from {@link smf_db_list_indexes()}
	 */
	public function table_structure($table_name);

	/**
	 * Return column information for a table.
	 *
	 * @param string $table_name The name of the table to get column info for
	 * @param bool $detail Whether or not to return detailed info. If true, returns the column info. If false, just returns the column names.
	 * @param array $parameters Not used?
	 * @return array An array of column names or detailed column info, depending on $detail
	 */
	public function list_columns($table_name, $detail = false, $parameters = []);

	/**
	 * Get index information.
	 *
	 * @param string $table_name The name of the table to get indexes for
	 * @param bool $detail Whether or not to return detailed info.
	 * @param array $parameters Not used?
	 * @return array An array of index names or a detailed array of index info, depending on $detail
	 */
	public function list_indexes($table_name, $detail = false, $parameters = []);

	/**
	 * Removes a column.
	 *
	 * @param string $table_name The name of the table to drop the column from
	 * @param string $column_name The name of the column to drop
	 * @param array $parameters Not used?
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function remove_column($table_name, $column_name, $parameters = [], $error = 'fatal');

	/**
	 * Remove an index.
	 *
	 * @param string $table_name The name of the table to remove the index from
	 * @param string $index_name The name of the index to remove
	 * @param array $parameters Not used?
	 * @param string $error
	 * @return bool Whether or not the operation was successful
	 */
	public function remove_index($table_name, $index_name, $parameters = [], $error = 'fatal');
}

?>