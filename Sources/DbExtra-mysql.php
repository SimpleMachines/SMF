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
			'db_get_version' => 'smf_db_get_version',
		);
}

// Backup $table to $backup_table.
function smf_db_backup_table($table, $backup_table)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	// First, get rid of the old table.
	$smcFunc['db_query']('', '
		DROP TABLE IF EXISTS {raw:backup_table}',
		array(
			'backup_table' => $backup_table,
		)
	);

	// Can we do this the quick way?
	$result = $smcFunc['db_query']('', '
		CREATE TABLE {raw:backup_table} LIKE {raw:table}',
		array(
			'backup_table' => $backup_table,
			'table' => $table
	));
	// If this failed, we go old school.
	if ($result)
	{
		$request = $smcFunc['db_query']('', '
			INSERT INTO {raw:backup_table}
			SELECT *
			FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table
			));

		// Old school or no school?
		if ($request)
			return $request;
	}

	// At this point, the quick method failed.
	$result = $smcFunc['db_query']('', '
		SHOW CREATE TABLE {raw:table}',
		array(
			'table' => $table,
		)
	);
	list (, $create) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	$create = preg_split('/[\n\r]/', $create);

	$auto_inc = '';
	// Default engine type.
	$engine = 'MyISAM';
	$charset = '';
	$collate = '';

	foreach ($create as $k => $l)
	{
		// Get the name of the auto_increment column.
		if (strpos($l, 'auto_increment'))
			$auto_inc = trim($l);

		// For the engine type, see if we can work out what it is.
		if (strpos($l, 'ENGINE') !== false || strpos($l, 'TYPE') !== false)
		{
			// Extract the engine type.
			preg_match('~(ENGINE|TYPE)=(\w+)(\sDEFAULT)?(\sCHARSET=(\w+))?(\sCOLLATE=(\w+))?~', $l, $match);

			if (!empty($match[1]))
				$engine = $match[1];

			if (!empty($match[2]))
				$engine = $match[2];

			if (!empty($match[5]))
				$charset = $match[5];

			if (!empty($match[7]))
				$collate = $match[7];
		}

		// Skip everything but keys...
		if (strpos($l, 'KEY') === false)
			unset($create[$k]);
	}

	if (!empty($create))
		$create = '(
			' . implode('
			', $create) . ')';
	else
		$create = '';

	$request = $smcFunc['db_query']('', '
		CREATE TABLE {raw:backup_table} {raw:create}
		ENGINE={raw:engine}' . (empty($charset) ? '' : ' CHARACTER SET {raw:charset}' . (empty($collate) ? '' : ' COLLATE {raw:collate}')) . '
		SELECT *
		FROM {raw:table}',
		array(
			'backup_table' => $backup_table,
			'table' => $table,
			'create' => $create,
			'engine' => $engine,
			'charset' => empty($charset) ? '' : $charset,
			'collate' => empty($collate) ? '' : $collate,
		)
	);

	if ($auto_inc != '')
	{
		if (preg_match('~\`(.+?)\`\s~', $auto_inc, $match) != 0 && substr($auto_inc, -1, 1) == ',')
			$auto_inc = substr($auto_inc, 0, -1);

		$smcFunc['db_query']('', '
			ALTER TABLE {raw:backup_table}
			CHANGE COLUMN {raw:column_detail} {raw:auto_inc}',
			array(
				'backup_table' => $backup_table,
				'column_detail' => $match[1],
				'auto_inc' => $auto_inc,
			)
		);
	}

	return $request;
}

// Optimize a table - return data freed!
function smf_db_optimize_table($table)
{
	global $smcFunc, $db_name, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	// Get how much overhead there is.
	$request = $smcFunc['db_query']('', '
			SHOW TABLE STATUS LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $table),
			)
		);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$data_before = isset($row['Data_free']) ? $row['Data_free'] : 0;
	$request = $smcFunc['db_query']('', '
			OPTIMIZE TABLE `{raw:table}`',
			array(
				'table' => $table,
			)
		);
	if (!$request)
		return -1;

	// How much left?
	$request = $smcFunc['db_query']('', '
			SHOW TABLE STATUS LIKE {string:table}',
			array(
				'table' => str_replace('_', '\_', $table),
			)
		);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$total_change = isset($row['Data_free']) && $data_before > $row['Data_free'] ? $data_before / 1024 : 0;

	return $total_change;
}

// List all the tables in the database.
function smf_db_list_tables($db = false, $filter = false)
{
	global $db_name, $smcFunc;

	$db = $db == false ? $db_name : $db;
	$db = trim($db);
	$filter = $filter == false ? '' : ' LIKE \'' . $filter . '\'';

	$request = $smcFunc['db_query']('', '
		SHOW TABLES
		FROM `{raw:db}`
		{raw:filter}',
		array(
			'db' => $db[0] == '`' ? strtr($db, array('`' => '')) : $db,
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
		SELECT /*!40001 SQL_NO_CACHE */ *
		FROM `{raw:table}`',
		array(
			'table' => $tableName,
		)
	);

	// The number of rows, just for record keeping and breaking INSERTs up.
	$num_rows = $smcFunc['db_num_rows']($result);
	$current_row = 0;

	if ($num_rows == 0)
		return '';

	$fields = array_keys($smcFunc['db_fetch_assoc']($result));
	$smcFunc['db_data_seek']($result, 0);

	// Start it off with the basic INSERT INTO.
	$data = 'INSERT INTO `' . $tableName . '`' . $crlf . "\t" . '(`' . implode('`, `', $fields) . '`)' . $crlf . 'VALUES ';

	// Loop through each row.
	while ($row = $smcFunc['db_fetch_row']($result))
	{
		$current_row++;

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

		// 'Insert' the data.
		$data .= '(' . implode(', ', $field_list) . ')';

		// All done!
		if ($current_row == $num_rows)
			$data .= ';' . $crlf;
		// Start a new INSERT statement after every 250....
		elseif ($current_row > 249 && $current_row % 250 == 0)
			$data .= ';' . $crlf . 'INSERT INTO `' . $tableName . '`' . $crlf . "\t" . '(`' . implode('`, `', $fields) . '`)' . $crlf . 'VALUES ';
		// Otherwise, go to the next line.
		else
			$data .= ',' . $crlf . "\t";
	}
	$smcFunc['db_free_result']($result);

	// Return an empty string if there were no rows.
	return $num_rows == 0 ? '' : $data;
}

// Get the schema (CREATE) for a table.
function smf_db_table_sql($tableName)
{
	global $smcFunc, $db_prefix;

	$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

	// This will be needed...
	$crlf = "\r\n";

	// Drop it if it exists.
	$schema_create = 'DROP TABLE IF EXISTS `' . $tableName . '`;' . $crlf . $crlf;

	// Start the create table...
	$schema_create .= 'CREATE TABLE `' . $tableName . '` (' . $crlf;

	// Find all the fields.
	$result = $smcFunc['db_query']('', '
		SHOW FIELDS
		FROM `{raw:table}`',
		array(
			'table' => $tableName,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Make the CREATE for this column.
		$schema_create .= ' `' . $row['Field'] . '` ' . $row['Type'] . ($row['Null'] != 'YES' ? ' NOT NULL' : '');

		// Add a default...?
		if (!empty($row['Default']) || $row['Null'] !== 'YES')
		{
			// Make a special case of auto-timestamp.
			if ($row['Default'] == 'CURRENT_TIMESTAMP')
				$schema_create .= ' /*!40102 NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */';
			// Text shouldn't have a default.
			elseif ($row['Default'] !== null)
			{
				// If this field is numeric the default needs no escaping.
				$type = strtolower($row['Type']);
				$isNumericColumn = strpos($type, 'int') !== false || strpos($type, 'bool') !== false || strpos($type, 'bit') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false;

				$schema_create .= ' default ' . ($isNumericColumn ? $row['Default'] : '\'' . $smcFunc['db_escape_string']($row['Default']) . '\'');
			}
		}

		// And now any extra information. (such as auto_increment.)
		$schema_create .= ($row['Extra'] != '' ? ' ' . $row['Extra'] : '') . ',' . $crlf;
	}
	$smcFunc['db_free_result']($result);

	// Take off the last comma.
	$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

	// Find the keys.
	$result = $smcFunc['db_query']('', '
		SHOW KEYS
		FROM `{raw:table}`',
		array(
			'table' => $tableName,
		)
	);
	$indexes = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// IS this a primary key, unique index, or regular index?
		$row['Key_name'] = $row['Key_name'] == 'PRIMARY' ? 'PRIMARY KEY' : (empty($row['Non_unique']) ? 'UNIQUE ' : ($row['Comment'] == 'FULLTEXT' || (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT') ? 'FULLTEXT ' : 'KEY ')) . '`' . $row['Key_name'] . '`';

		// Is this the first column in the index?
		if (empty($indexes[$row['Key_name']]))
			$indexes[$row['Key_name']] = array();

		// A sub part, like only indexing 15 characters of a varchar.
		if (!empty($row['Sub_part']))
			$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`(' . $row['Sub_part'] . ')';
		else
			$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`';
	}
	$smcFunc['db_free_result']($result);

	// Build the CREATEs for the keys.
	foreach ($indexes as $keyname => $columns)
	{
		// Ensure the columns are in proper order.
		ksort($columns);

		$schema_create .= ',' . $crlf . ' ' . $keyname . ' (' . implode($columns, ', ') . ')';
	}

	// Now just get the comment and type... (MyISAM, etc.)
	$result = $smcFunc['db_query']('', '
		SHOW TABLE STATUS
		LIKE {string:table}',
		array(
			'table' => strtr($tableName, array('_' => '\\_', '%' => '\\%')),
		)
	);
	$row = $smcFunc['db_fetch_assoc']($result);
	$smcFunc['db_free_result']($result);

	// Probably MyISAM.... and it might have a comment.
	$schema_create .= $crlf . ') ENGINE=' . (isset($row['Type']) ? $row['Type'] : $row['Engine']) . ($row['Comment'] != '' ? ' COMMENT="' . $row['Comment'] . '"' : '');

	return $schema_create;
}

// Get the version number.
function smf_db_get_version()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT VERSION()',
		array(
		)
	);
	list ($ver) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $ver;
}

?>