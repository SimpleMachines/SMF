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

/*	This file contains database functionality specifically designed for packages to utilize.

	bool smf_db_create_table(string table_name, array columns, array indexes = array(),
		array parameters = array(), string if_exists = 'ignore')
		- Can be used to create a table without worrying about schema compatabilities.
		- If the table exists will, by default, do nothing.
		- Builds table with columns as passed to it - at least one column must be sent.
		  The columns array should have one sub-array for each column - these sub arrays contain:
			+ 'name' = Column name
			+ 'type' = Type of column - values from (smallint,mediumint,int,text,varchar,char,tinytext,mediumtext,largetext)
			+ 'size' => Size of column (If applicable) - for example 255 for a large varchar, 10 for an int etc. If not
						set SMF will pick a size.
			+ 'default' = Default value - do not set if no default required.
			+ 'null' => Can it be null (true or false) - if not set default will be false.
			+ 'auto' => Set to true to make it an auto incrementing column. Set to a numerical value to set
						from what it should begin counting.
		- Adds indexes as specified within indexes parameter. Each index should be a member of $indexes. Values are:
			+ 'name' => Index name (If left empty SMF will generate).
			+ 'type' => Type of index. Choose from 'primary', 'unique' or 'index'. If not set will default to 'index'.
			+ 'columns' => Array containing columns that form part of key - in the order the index is to be created.
		- parameters: (None yet)
		- if_exists values:
			+ 'ignore' will do nothing if the table exists. (And will return true)
			+ 'overwrite' will drop any existing table of the same name.
			+ 'error' will return false if the table already exists.

*/

// Add the file functions to the $smcFunc array.
function db_packages_init()
{
	global $smcFunc, $reservedTables, $db_package_log, $db_prefix;

	if (!isset($smcFunc['db_create_table']) || $smcFunc['db_create_table'] != 'smf_db_create_table')
	{
		$smcFunc += array(
			'db_add_column' => 'smf_db_add_column',
			'db_add_index' => 'smf_db_add_index',
			'db_calculate_type' => 'smf_db_calculate_type',
			'db_change_column' => 'smf_db_change_column',
			'db_create_table' => 'smf_db_create_table',
			'db_drop_table' => 'smf_db_drop_table',
			'db_table_structure' => 'smf_db_table_structure',
			'db_list_columns' => 'smf_db_list_columns',
			'db_list_indexes' => 'smf_db_list_indexes',
			'db_remove_column' => 'smf_db_remove_column',
			'db_remove_index' => 'smf_db_remove_index',
		);
		$db_package_log = array();
	}

	// We setup an array of SMF tables we can't do auto-remove on - in case a mod writer cocks it up!
	$reservedTables = array('admin_info_files', 'approval_queue', 'attachments', 'ban_groups', 'ban_items',
		'board_permissions', 'boards', 'calendar', 'calendar_holidays', 'categories', 'collapsed_categories',
		'custom_fields', 'group_moderators', 'log_actions', 'log_activity', 'log_banned', 'log_boards',
		'log_digest', 'log_errors', 'log_floodcontrol', 'log_group_requests', 'log_karma', 'log_mark_read',
		'log_notify', 'log_online', 'log_packages', 'log_polls', 'log_reported', 'log_reported_comments',
		'log_scheduled_tasks', 'log_search_messages', 'log_search_results', 'log_search_subjects',
		'log_search_topics', 'log_topics', 'mail_queue', 'membergroups', 'members', 'message_icons',
		'messages', 'moderators', 'package_servers', 'permission_profiles', 'permissions', 'personal_messages',
		'pm_recipients', 'poll_choices', 'polls', 'scheduled_tasks', 'sessions', 'settings', 'smileys',
		'themes', 'topics');
	foreach ($reservedTables as $k => $table_name)
		$reservedTables[$k] = strtolower($db_prefix . $table_name);

	// We in turn may need the extra stuff.
	db_extend('extra');
}

// Create a table.
function smf_db_create_table($table_name, $columns, $indexes = array(), $parameters = array(), $if_exists = 'ignore', $error = 'fatal')
{
	global $reservedTables, $smcFunc, $db_package_log, $db_prefix, $db_character_set;

	// Strip out the table name, we might not need it in some cases
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// With or without the database name, the fullname looks like this.
	$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// First - no way do we touch SMF tables.
	if (in_array(strtolower($table_name), $reservedTables))
		return false;

	// Log that we'll want to remove this on uninstall.
	$db_package_log[] = array('remove_table', $table_name);

	// Slightly easier on MySQL than the others...
	$tables = $smcFunc['db_list_tables']();
	if (in_array($full_table_name, $tables))
	{
		// This is a sad day... drop the table? If not, return false (error) by default.
		if ($if_exists == 'overwrite')
			$smcFunc['db_drop_table']($table_name);
		else
			return $if_exists == 'ignore';
	}

	// Righty - let's do the damn thing!
	$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
	foreach ($columns as $column)
	{
		// Auto increment is easy here!
		if (!empty($column['auto']))
		{
			$default = 'auto_increment';
		}
		elseif (isset($column['default']) && $column['default'] !== null)
			$default = 'default \'' . $smcFunc['db_escape_string']($column['default']) . '\'';
		else
			$default = '';

		// Sort out the size... and stuff...
		$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
		list ($type, $size) = $smcFunc['db_calculate_type']($column['type'], $column['size']);

		// Allow unsigned integers (mysql only)
		$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column['unsigned']) ? 'unsigned ' : '';

		if ($size !== null)
			$type = $type . '(' . $size . ')';

		// Now just put it together!
		$table_query .= "\n\t`" .$column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default . ',';
	}

	// Loop through the indexes next...
	foreach ($indexes as $index)
	{
		$columns = implode(',', $index['columns']);

		// Is it the primary?
		if (isset($index['type']) && $index['type'] == 'primary')
			$table_query .= "\n\t" . 'PRIMARY KEY (' . implode(',', $index['columns']) . '),';
		else
		{
			if (empty($index['name']))
				$index['name'] = implode('_', $index['columns']);
			$table_query .= "\n\t" . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : 'KEY') . ' ' . $index['name'] . ' (' . $columns . '),';
		}
	}

	// No trailing commas!
	if (substr($table_query, -1) == ',')
		$table_query = substr($table_query, 0, -1);

	$table_query .= ') ENGINE=MyISAM';
	if (!empty($db_character_set) && $db_character_set == 'utf8')
		$table_query .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

	// Create the table!
	$smcFunc['db_query']('', $table_query,
		array(
			'security_override' => true,
		)
	);
}

// Drop a table.
function smf_db_drop_table($table_name, $parameters = array(), $error = 'fatal')
{
	global $reservedTables, $smcFunc, $db_prefix;

	// After stripping away the database name, this is what's left.
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get some aliases.
	$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// God no - dropping one of these = bad.
	if (in_array(strtolower($table_name), $reservedTables))
		return false;

	// Does it exist?
	if (in_array($full_table_name, $smcFunc['db_list_tables']()))
	{
		$query = 'DROP TABLE ' . $table_name;
		$smcFunc['db_query']('',
			$query,
			array(
				'security_override' => true,
			)
		);

		return true;
	}

	// Otherwise do 'nout.
	return false;
}

// Add a column.
function smf_db_add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
{
	global $smcFunc, $db_package_log, $txt, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Log that we will want to uninstall this!
	$db_package_log[] = array('remove_column', $table_name, $column_info['name']);

	// Does it exist - if so don't add it again!
	$columns = $smcFunc['db_list_columns']($table_name, false);
	foreach ($columns as $column)
		if ($column == $column_info['name'])
		{
			// If we're going to overwrite then use change column.
			if ($if_exists == 'update')
				return $smcFunc['db_change_column']($table_name, $column_info['name'], $column_info);
			else
				return false;
		}

	// Get the specifics...
	$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;
	list ($type, $size) = $smcFunc['db_calculate_type']($column_info['type'], $column_info['size']);

	// Allow unsigned integers (mysql only)
	$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column_info['unsigned']) ? 'unsigned ' : '';

	if ($size !== null)
		$type = $type . '(' . $size . ')';

	// Now add the thing!
	$query = '
		ALTER TABLE ' . $table_name . '
		ADD `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column_info['null']) ? 'NOT NULL' : '') . ' ' .
			(!isset($column_info['default']) ? '' : 'default \'' . $smcFunc['db_escape_string']($column_info['default']) . '\'') . ' ' .
			(empty($column_info['auto']) ? '' : 'auto_increment primary key') . ' ';
	$smcFunc['db_query']('', $query,
		array(
			'security_override' => true,
		)
	);

	return true;
}

// Remove a column.
function smf_db_remove_column($table_name, $column_name, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Does it exist?
	$columns = $smcFunc['db_list_columns']($table_name, true);
	foreach ($columns as $column)
		if ($column['name'] == $column_name)
		{
			$smcFunc['db_query']('', '
				ALTER TABLE ' . $table_name . '
				DROP COLUMN ' . $column_name,
				array(
					'security_override' => true,
				)
			);

			return true;
		}

	// If here we didn't have to work - joy!
	return false;
}

// Change a column.
function smf_db_change_column($table_name, $old_column, $column_info, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Check it does exist!
	$columns = $smcFunc['db_list_columns']($table_name, true);
	$old_info = null;
	foreach ($columns as $column)
		if ($column['name'] == $old_column)
			$old_info = $column;

	// Nothing?
	if ($old_info == null)
		return false;

	// Get the right bits.
	if (!isset($column_info['name']))
		$column_info['name'] = $old_column;
	if (!isset($column_info['default']))
		$column_info['default'] = $old_info['default'];
	if (!isset($column_info['null']))
		$column_info['null'] = $old_info['null'];
	if (!isset($column_info['auto']))
		$column_info['auto'] = $old_info['auto'];
	if (!isset($column_info['type']))
		$column_info['type'] = $old_info['type'];
	if (!isset($column_info['size']) || !is_numeric($column_info['size']))
		$column_info['size'] = $old_info['size'];
	if (!isset($column_info['unsigned']) || !in_array($column_info['type'], array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')))
		$column_info['unsigned'] = '';

	list ($type, $size) = $smcFunc['db_calculate_type']($column_info['type'], $column_info['size']);

	// Allow for unsigned integers (mysql only)
	$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column_info['unsigned']) ? 'unsigned ' : '';

	if ($size !== null)
		$type = $type . '(' . $size . ')';

	$smcFunc['db_query']('', '
		ALTER TABLE ' . $table_name . '
		CHANGE COLUMN `' . $old_column . '` `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column_info['null']) ? 'NOT NULL' : '') . ' ' .
			(!isset($column_info['default']) ? '' : 'default \'' . $smcFunc['db_escape_string']($column_info['default']) . '\'') . ' ' .
			(empty($column_info['auto']) ? '' : 'auto_increment') . ' ',
		array(
			'security_override' => true,
		)
	);
}

// Add an index.
function smf_db_add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
{
	global $smcFunc, $db_package_log, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// No columns = no index.
	if (empty($index_info['columns']))
		return false;
	$columns = implode(',', $index_info['columns']);

	// No name - make it up!
	if (empty($index_info['name']))
	{
		// No need for primary.
		if (isset($index_info['type']) && $index_info['type'] == 'primary')
			$index_info['name'] = '';
		else
			$index_info['name'] = implode('_', $index_info['columns']);
	}
	else
		$index_info['name'] = $index_info['name'];

	// Log that we are going to want to remove this!
	$db_package_log[] = array('remove_index', $table_name, $index_info['name']);

	// Let's get all our indexes.
	$indexes = $smcFunc['db_list_indexes']($table_name, true);
	// Do we already have it?
	foreach ($indexes as $index)
	{
		if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
		{
			// If we want to overwrite simply remove the current one then continue.
			if ($if_exists != 'update' || $index['type'] == 'primary')
				return false;
			else
				$smcFunc['db_remove_index']($table_name, $index_info['name']);
		}
	}

	// If we're here we know we don't have the index - so just add it.
	if (!empty($index_info['type']) && $index_info['type'] == 'primary')
	{
		$smcFunc['db_query']('', '
			ALTER TABLE ' . $table_name . '
			ADD PRIMARY KEY (' . $columns . ')',
			array(
				'security_override' => true,
			)
		);
	}
	else
	{
		$smcFunc['db_query']('', '
			ALTER TABLE ' . $table_name . '
			ADD ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : 'INDEX') . ' ' . $index_info['name'] . ' (' . $columns . ')',
			array(
				'security_override' => true,
			)
		);
	}
}

// Remove an index.
function smf_db_remove_index($table_name, $index_name, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Better exist!
	$indexes = $smcFunc['db_list_indexes']($table_name, true);

	foreach ($indexes as $index)
	{
		// If the name is primary we want the primary key!
		if ($index['type'] == 'primary' && $index_name == 'primary')
		{
			// Dropping primary key?
			$smcFunc['db_query']('', '
				ALTER TABLE ' . $table_name . '
				DROP PRIMARY KEY',
				array(
					'security_override' => true,
				)
			);

			return true;
		}
		if ($index['name'] == $index_name)
		{
			// Drop the bugger...
			$smcFunc['db_query']('', '
				ALTER TABLE ' . $table_name . '
				DROP INDEX ' . $index_name,
				array(
					'security_override' => true,
				)
			);

			return true;
		}
	}

	// Not to be found ;(
	return false;
}

// Get the schema formatted name for a type.
function smf_db_calculate_type($type_name, $type_size = null, $reverse = false)
{
	// MySQL is actually the generic baseline.
	return array($type_name, $type_size);
}

// Get table structure.
function smf_db_table_structure($table_name, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	return array(
		'name' => $table_name,
		'columns' => $smcFunc['db_list_columns']($table_name, true),
		'indexes' => $smcFunc['db_list_indexes']($table_name, true),
	);
}

// Return column information for a table.
function smf_db_list_columns($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		SHOW FIELDS
		FROM {raw:table_name}',
		array(
			'table_name' => substr($table_name, 0, 1) == '`' ? $table_name : '`' . $table_name . '`',
		)
	);
	$columns = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!$detail)
		{
			$columns[] = $row['Field'];
		}
		else
		{
			// Is there an auto_increment?
			$auto = strpos($row['Extra'], 'auto_increment') !== false ? true : false;

			// Can we split out the size?
			if (preg_match('~(.+?)\s*\((\d+)\)(?:(?:\s*)?(unsigned))?~i', $row['Type'], $matches) === 1)
			{
				$type = $matches[1];
				$size = $matches[2];
				if (!empty($matches[3]) && $matches[3] == 'unsigned')
					$unsigned = true;
			}
			else
			{
				$type = $row['Type'];
				$size = null;
			}

			$columns[$row['Field']] = array(
				'name' => $row['Field'],
				'null' => $row['Null'] != 'YES' ? false : true,
				'default' => isset($row['Default']) ? $row['Default'] : null,
				'type' => $type,
				'size' => $size,
				'auto' => $auto,
			);

			if (isset($unsigned))
			{
				$columns[$row['Field']]['unsigned'] = $unsigned;
				unset($unsigned);
			}
		}
	}
	$smcFunc['db_free_result']($result);

	return $columns;
}

// What about some index information?
function smf_db_list_indexes($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		SHOW KEYS
		FROM {raw:table_name}',
		array(
			'table_name' => substr($table_name, 0, 1) == '`' ? $table_name : '`' . $table_name . '`',
		)
	);
	$indexes = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!$detail)
			$indexes[] = $row['Key_name'];
		else
		{
			// What is the type?
			if ($row['Key_name'] == 'PRIMARY')
				$type = 'primary';
			elseif (empty($row['Non_unique']))
				$type = 'unique';
			elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT')
				$type = 'fulltext';
			else
				$type = 'index';

			// This is the first column we've seen?
			if (empty($indexes[$row['Key_name']]))
			{
				$indexes[$row['Key_name']] = array(
					'name' => $row['Key_name'],
					'type' => $type,
					'columns' => array(),
				);
			}

			// Is it a partial index?
			if (!empty($row['Sub_part']))
				$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
			else
				$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
		}
	}
	$smcFunc['db_free_result']($result);

	return $indexes;
}

?>