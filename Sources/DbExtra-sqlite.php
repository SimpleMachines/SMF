<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file contains rarely used extended database functionality.

	void db_extra_init()
		- add this file's functions to the $smcFunc array.

	resource smf_db_backup_table($table, $backup_table)
		- backup $table to $backup_table.
		- returns the request handle to the table creation query

	string function smf_db_get_version()
		- get the version number.

	string db_insert_sql(string table_name)
		- gets all the necessary INSERTs for the table named table_name.
		- goes in 250 row segments.
		- returns the query to insert the data back in.
		- returns an empty string if the table was empty.

	array smf_db_list_tables($db = false, $filter = false)
		- lists all tables in the database
		- could be filtered according to $filter
		- returns an array of table names. (strings)

	float smf_db_optimize_table($table)
		- optimize a table
		- $table - the table to be optimized
		- returns how much it was gained

	string db_table_sql(string table_name)
		- dumps the CREATE for the specified table. (by table_name.)
		- returns the CREATE statement.

*/

// Add the file functions to the $smcFunc array.
function db_extra_init()
{
	global $smcFunc;

	if (!isset($smcFunc['db_backup_table']) || $smcFunc['db_backup_table'] != 'smf_db_backup_table')
		$smcFunc += array(
			'db_backup_table' => 'smf_db_backup_table',
			'db_optimize_table' => 'smf_db_optimize_table',
			'db_insert_sql' => 'smf_db_insert_sql',
			'db_table_sql' => 'smf_db_table_sql',
			'db_list_tables' => 'smf_db_list_tables',
			'db_get_backup' => 'smf_db_get_backup',
			'db_get_version' => 'smf_db_get_version',
		);
}

// Backup $table to $backup_table.
function smf_db_backup_table($table, $backup_table)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$result = $smcFunc['db_query']('', '
		SELECT sql
		FROM sqlite_master
		WHERE type = {string:txttable}
			AND name = {string:table}',
		array(
			'table' => $table,
			'txttable' => 'table'
		)
	);
	list ($create) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	$create = preg_split('/[\n\r]/', $create);
	$auto_inc = '';

	// Remove the first line and check to see if the second one contain useless info.
	unset($create[0]);
	if (trim($create[1]) == '(')
		unset($create[1]);
	if (trim($create[count($create)]) == ')')
		unset($create[count($create)]);

	foreach ($create as $k => $l)
	{
		// Get the name of the auto_increment column.
		if (strpos($l, 'primary') || strpos($l, 'PRIMARY'))
			$auto_inc = trim($l);

		// Skip everything but keys...
		if ((strpos($l, 'KEY') !== false && strpos($l, 'PRIMARY KEY') === false) || strpos($l, $table) !== false || strpos(trim($l), 'PRIMARY KEY') === 0)
			unset($create[$k]);
	}

	if (!empty($create))
		$create = '(
			' . implode('
			', $create) . ')';
	else
		$create = '';

	// Is there an extra junk at the end?
	if (substr($create, -2, 1) == ',')
		$create = substr($create, 0, -2) . ')';
	if (substr($create, -2) == '))')
		$create = substr($create, 0, -1);

	$smcFunc['db_query']('', '
		DROP TABLE {raw:backup_table}',
		array(
			'backup_table' => $backup_table,
			'db_error_skip' => true,
		)
	);

	$request = $smcFunc['db_quote']('
		CREATE TABLE {raw:backup_table} {raw:create}',
		array(
			'backup_table' => $backup_table,
			'create' => $create,
	));

	$smcFunc['db_query']('', '
		CREATE TABLE {raw:backup_table} {raw:create}',
		array(
			'backup_table' => $backup_table,
			'create' => $create,
	));

	$request = $smcFunc['db_query']('', '
		INSERT INTO {raw:backup_table}
		SELECT *
		FROM {raw:table}',
		array(
			'backup_table' => $backup_table,
			'table' => $table,
	));

	return $request;
}

// Optimize a table - return data freed!
function smf_db_optimize_table($table)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$request = $smcFunc['db_query']('', '
		VACUUM {raw:table}',
		array(
			'table' => $table,
		)
	);
	if (!$request)
		return -1;

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// The function returns nothing.
	return 0;
}

// List all the tables in the database.
function smf_db_list_tables($db = false, $filter = false)
{
	global $smcFunc;

	$filter = $filter == false ? '' : ' AND name LIKE \'' . str_replace("\_", "_", $filter) . '\'';

	$request = $smcFunc['db_query']('', '
		SELECT name
		FROM sqlite_master
		WHERE type = {string:type}
		{raw:filter}
		ORDER BY name',
		array(
			'type' => 'table',
			'filter' => $filter,
		)
	);
	$tables = array();
	while ($row = $smcFunc['db_fetch_row']($request))
		$tables[] = $row[0];
	$smcFunc['db_free_result']($request);

	return $tables;
}

// Get the content (INSERTs) for a table.
function smf_db_insert_sql($tableName)
{
	global $smcFunc, $db_prefix;

	$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

	// This will be handy...
	$crlf = "\r\n";

	// Get everything from the table.
	$result = $smcFunc['db_query']('', '
		SELECT *
		FROM {raw:table}',
		array(
			'table' => $tableName,
		)
	);

	// The number of rows, just for record keeping and breaking INSERTs up.
	$num_rows = $smcFunc['db_num_rows']($result);

	if ($num_rows == 0)
		return '';

	$fields = array_keys($smcFunc['db_fetch_assoc']($result));

	// SQLite fetches an array so we need to filter out the numberic index for the columns.
	foreach ($fields as $key => $name)
		if (is_numeric($name))
			unset($fields[$key]);

	$smcFunc['db_data_seek']($result, 0);

	// Start it off with the basic INSERT INTO.
	$data = 'BEGIN TRANSACTION;' . $crlf;

	// Loop through each row.
	while ($row = $smcFunc['db_fetch_row']($result))
	{
		// Get the fields in this row...
		$field_list = array();
		for ($j = 0; $j < $smcFunc['db_num_fields']($result); $j++)
		{
			// Try to figure out the type of each field. (NULL, number, or 'string'.)
			if (!isset($row[$j]))
				$field_list[] = 'NULL';
			elseif (is_numeric($row[$j]) && (int) $row[$j] == $row[$j])
				$field_list[] = $row[$j];
			else
				$field_list[] = '\'' . $smcFunc['db_escape_string']($row[$j]) . '\'';
		}
		$data .= 'INSERT INTO ' . $tableName . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $field_list) . ');' . $crlf;
	}
	$smcFunc['db_free_result']($result);

	// Return an empty string if there were no rows.
	return $num_rows == 0 ? '' : $data . 'COMMIT;' . $crlf;
}

// Get the schema (CREATE) for a table.
function smf_db_table_sql($tableName)
{
	global $smcFunc, $db_prefix;

	$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

	// This will be needed...
	$crlf = "\r\n";

	// Start the create table...
	$schema_create = '';
	$index_create = '';

	// Let's get the create statement directly from SQLite.
	$result = $smcFunc['db_query']('', '
		SELECT sql
		FROM sqlite_master
		WHERE type = {string:type}
			AND name = {string:table_name}',
		array(
			'type' => 'table',
			'table_name' => $tableName,
		)
	);
	list ($schema_create) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Now the indexes.
	$result = $smcFunc['db_query']('', '
		SELECT sql
		FROM sqlite_master
		WHERE type = {string:type}
			AND tbl_name = {string:table_name}',
		array(
			'type' => 'index',
			'table_name' => $tableName,
		)
	);
	$indexes = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
		if (trim($row['sql']) != '')
			$indexes[] = $row['sql'];
	$smcFunc['db_free_result']($result);

	$index_create .= implode(';' . $crlf, $indexes);
	$schema_create = empty($indexes) ? rtrim($schema_create) : $schema_create . ';' . $crlf . $crlf;

	return $schema_create . $index_create;
}

// Get the version number.
function smf_db_get_version()
{
	return sqlite_libversion();
}

// Simple return the database - and die!
function smf_db_get_backup()
{
	global $db_name;

	$db_file = substr($db_name, -3) === '.db' ? $db_name : $db_name . '.db';

	// Add more info if zipped...
	$ext = '';
	if (isset($_REQUEST['compress']) && function_exists('gzencode'))
		$ext = '.gz';

	// Do the remaining headers.
	header('Content-Disposition: attachment; filename="' . $db_file . $ext . '"');
	header('Cache-Control: private');
	header('Connection: close');

	// Literally dump the contents.  Try reading the file first.
	if (@readfile($db_file) == null)
		echo file_get_contents($db_file);

	obExit(false);
}

?>