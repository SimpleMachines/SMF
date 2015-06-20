<?php

/**
 * This file contains rarely used extended database functionality.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Add the functions implemented in this file to the $smcFunc array.
 */
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

/**
 * Backup $table to $backup_table.
 * @param string $table The name of the table to backup
 * @param string $backup_table The name of the backup table for this table
 * @return resource -the request handle to the table creation query
 */
function smf_db_backup_table($table, $backup_table)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	// Do we need to drop it first?
	$tables = smf_db_list_tables(false, $backup_table);
	if (!empty($tables))
		$smcFunc['db_query']('', '
			DROP TABLE {raw:backup_table}',
			array(
				'backup_table' => $backup_table,
			)
		);

	/**
	 * @todo Should we create backups of sequences as well?
	 */
	$smcFunc['db_query']('', '
		CREATE TABLE {raw:backup_table}
		(
			LIKE {raw:table}
			INCLUDING DEFAULTS
		)',
		array(
			'backup_table' => $backup_table,
			'table' => $table,
		)
	);
	$smcFunc['db_query']('', '
		INSERT INTO {raw:backup_table}
		SELECT * FROM {raw:table}',
		array(
			'backup_table' => $backup_table,
			'table' => $table,
		)
	);
}

/**
 * This function optimizes a table.
 * @param string $table The table to be optimized
 * @return int How much space was gained
 */
function smf_db_optimize_table($table)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$request = $smcFunc['db_query']('', '
			VACUUM ANALYZE {raw:table}',
			array(
				'table' => $table,
			)
		);
	if (!$request)
		return -1;

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (isset($row['Data_free']))
			return $row['Data_free'] / 1024;
	else
		return 0;
}

/**
 * This function lists all tables in the database.
 * The listing could be filtered according to $filter.
 *
 * @param string|boolean $db string The database name or false to use the current DB
 * @param string|boolean $filter String to filter by or false to list all tables
 * @return array An array of table names
 */
function smf_db_list_tables($db = false, $filter = false)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT tablename
		FROM pg_tables
		WHERE schemaname = {string:schema_public}' . ($filter == false ? '' : '
			AND tablename LIKE {string:filter}') . '
		ORDER BY tablename',
		array(
			'schema_public' => 'public',
			'filter' => $filter,
		)
	);

	$tables = array();
	while ($row = $smcFunc['db_fetch_row']($request))
		$tables[] = $row[0];
	$smcFunc['db_free_result']($request);

	return $tables;
}

/**
 * Gets all the necessary INSERTs for the table named table_name.
 * It goes in 250 row segments.
 *
 * @param string $tableName The table to create the inserts for.
 * @param boolean $new_table Whether or not this a new table (resets $start and $limit)
 * @return string The query to insert the data back in, or an empty string if the table was empty.
 */
function smf_db_insert_sql($tableName, $new_table = false)
{
	global $smcFunc, $db_prefix;
	static $start = 0, $num_rows, $fields, $limit;

	if ($new_table)
	{
		$limit = strstr($tableName, 'log_') !== false ? 500 : 250;
		$start = 0;
	}

	$data = '';
	$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

	// This will be handy...
	$crlf = "\r\n";

	$result = $smcFunc['db_query']('', '
		SELECT *
		FROM ' . $tableName . '
		LIMIT ' . $start . ', ' . $limit,
		array(
			'security_override' => true,
		)
	);

	// The number of rows, just for record keeping and breaking INSERTs up.
	$num_rows = $smcFunc['db_num_rows']($result);

	if ($num_rows == 0)
		return '';

	if ($new_table)
	{
		$fields = array_keys($smcFunc['db_fetch_assoc']($result));
		$smcFunc['db_data_seek']($result, 0);
	}

	// Start it off with the basic INSERT INTO.
	$data = '';
	$insert_msg = $crlf . 'INSERT INTO ' . $tableName . $crlf . "\t" . '(' . implode(', ', $fields) . ')' . $crlf . 'VALUES ' . $crlf . "\t";

	// Loop through each row.
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Get the fields in this row...
		$field_list = array();

		foreach ($row as $key => $item)
		{
			// Try to figure out the type of each field. (NULL, number, or 'string'.)
			if (!isset($item))
				$field_list[] = 'NULL';
			elseif (is_numeric($item) && (int) $item == $item)
				$field_list[] = $item;
			else
				$field_list[] = '\'' . $smcFunc['db_escape_string']($item) . '\'';
		}

		// 'Insert' the data.
		$data .= $insert_msg . '(' . implode(', ', $field_list) . ');' . $crlf;
	}
	$smcFunc['db_free_result']($result);

	$data .= $crlf;

	$start += $limit;

	return $data;
}

/**
 * Dumps the schema (CREATE) for a table.
 * @todo why is this needed for?
 * @param string $tableName The name of the table
 * @return string The "CREATE TABLE" SQL string for this table
 */
function smf_db_table_sql($tableName)
{
	global $smcFunc, $db_prefix;

	$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

	// This will be needed...
	$crlf = "\r\n";

	// Start the create table...
	$schema_create = 'CREATE TABLE ' . $tableName . ' (' . $crlf;
	$index_create = '';
	$seq_create = '';

	// Find all the fields.
	$result = $smcFunc['db_query']('', '
		SELECT column_name, column_default, is_nullable, data_type, character_maximum_length
		FROM information_schema.columns
		WHERE table_name = {string:table}
		ORDER BY ordinal_position',
		array(
			'table' => $tableName,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if ($row['data_type'] == 'character varying')
			$row['data_type'] = 'varchar';
		elseif ($row['data_type'] == 'character')
			$row['data_type'] = 'char';
		if ($row['character_maximum_length'])
			$row['data_type'] .= '(' . $row['character_maximum_length'] . ')';

		// Make the CREATE for this column.
		$schema_create .= ' "' . $row['column_name'] . '" ' . $row['data_type'] . ($row['is_nullable'] != 'YES' ? ' NOT NULL' : '');

		// Add a default...?
		if (trim($row['column_default']) != '')
		{
			$schema_create .= ' default ' . $row['column_default'] . '';

			// Auto increment?
			if (preg_match('~nextval\(\'(.+?)\'(.+?)*\)~i', $row['column_default'], $matches) != 0)
			{
				// Get to find the next variable first!
				$count_req = $smcFunc['db_query']('', '
					SELECT MAX("{raw:column}")
					FROM {raw:table}',
					array(
						'column' => $row['column_name'],
						'table' => $tableName,
					)
				);
				list ($max_ind) = $smcFunc['db_fetch_row']($count_req);
				$smcFunc['db_free_result']($count_req);
				// Get the right bloody start!
				$seq_create .= 'CREATE SEQUENCE ' . $matches[1] . ' START WITH ' . ($max_ind + 1) . ';' . $crlf . $crlf;
			}
		}

		$schema_create .= ',' . $crlf;
	}
	$smcFunc['db_free_result']($result);

	// Take off the last comma.
	$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

	$result = $smcFunc['db_query']('', '
		SELECT CASE WHEN i.indisprimary THEN 1 ELSE 0 END AS is_primary, pg_get_indexdef(i.indexrelid) AS inddef
		FROM pg_class AS c
			INNER JOIN pg_index AS i ON (i.indrelid = c.oid)
			INNER JOIN pg_class AS c2 ON (c2.oid = i.indexrelid)
		WHERE c.relname = {string:table}',
		array(
			'table' => $tableName,
		)
	);
	$indexes = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if ($row['is_primary'])
		{
			if (preg_match('~\(([^\)]+?)\)~i', $row['inddef'], $matches) == 0)
				continue;

			$index_create .= $crlf . 'ALTER TABLE ' . $tableName . ' ADD PRIMARY KEY ("' . $matches[1] . '");';
		}
		else
			$index_create .= $crlf . $row['inddef'] . ';';
	}
	$smcFunc['db_free_result']($result);

	// Finish it off!
	$schema_create .= $crlf . ');';

	return $seq_create . $schema_create . $index_create;
}

/**
 *  Get the version number.
 *  @return string The version
 */
function smf_db_get_version()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SHOW server_version',
		array(
		)
	);
	list ($ver) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $ver;
}

?>