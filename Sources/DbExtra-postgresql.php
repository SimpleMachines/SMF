<?php

/**
 * This file contains rarely used extended database functionality.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
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
			'db_table_sql' => 'smf_db_table_sql',
			'db_list_tables' => 'smf_db_list_tables',
			'db_get_version' => 'smf_db_get_version',
			'db_get_vendor' => 'smf_db_get_vendor',
			'db_allow_persistent' => 'smf_db_allow_persistent',
		);
}

/**
 * Backup $table to $backup_table.
 *
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
 *
 * @param string $table The table to be optimized
 * @return int How much space was gained
 */
function smf_db_optimize_table($table)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$pg_tables = array('pg_catalog', 'information_schema');

	$request = $smcFunc['db_query']('', '
		SELECT pg_relation_size(C.oid) AS "size"
		FROM pg_class C
			LEFT JOIN pg_namespace N ON (N.oid = C.relnamespace)
		WHERE nspname NOT IN ({array_string:pg_tables})
			AND relname = {string:table}',
		array(
			'table' => $table,
			'pg_tables' => $pg_tables,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$old_size = $row['size'];

	$request = $smcFunc['db_query']('', '
		VACUUM FULL ANALYZE {raw:table}',
		array(
			'table' => $table,
		)
	);

	if (!$request)
		return -1;

	$request = $smcFunc['db_query']('', '
		SELECT pg_relation_size(C.oid) AS "size"
		FROM pg_class C
			LEFT JOIN pg_namespace N ON (N.oid = C.relnamespace)
		WHERE nspname NOT IN ({array_string:pg_tables})
			AND relname = {string:table}',
		array(
			'table' => $table,
			'pg_tables' => $pg_tables,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (isset($row['size']))
		return ($old_size - $row['size']) / 1024;
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
 * Dumps the schema (CREATE) for a table.
 *
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

	// Drop it if it exists.
	$schema_create = 'DROP TABLE IF EXISTS ' . $tableName . ';' . $crlf . $crlf;

	// Start the create table...
	$schema_create .= 'CREATE TABLE ' . $tableName . ' (' . $crlf;
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
 *
 * @return string The version
 */
function smf_db_get_version()
{
	global $db_connection;
	static $ver;

	if (!empty($ver))
		return $ver;

	$ver = pg_version($db_connection)['server'];

	return $ver;
}

/**
 * Return PostgreSQL
 *
 * @return string The database engine we are using
 */
function smf_db_get_vendor()
{
	return 'PostgreSQL';
}

/**
 * Figures out if persistent connection is allowed
 *
 * @return boolean
 */
function smf_db_allow_persistent()
{
	$value = ini_get('pgsql.allow_persistent');
	if (strtolower($value) == 'on' || strtolower($value) == 'true' || $value == '1')
		return true;
	else
		return false;
}

?>