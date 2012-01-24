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
			'db_alter_table' => 'smf_db_alter_table',
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
	global $reservedTables, $smcFunc, $db_package_log, $db_prefix;

	// With or without the database name, the full name looks like this.
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;
	$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// First - no way do we touch SMF tables.
	// Commented out for now. We need to alter SMF tables in order to use this in the upgrade.
/*
	if (in_array(strtolower($table_name), $reservedTables))
		return false;
*/

	// Log that we'll want to remove this on uninstall.
	$db_package_log[] = array('remove_table', $table_name);

	// Does this table exist or not?
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
	$done_primary = false;
	foreach ($columns as $column)
	{
		// Auto increment is special
		if (!empty($column['auto']))
		{
			$table_query .= "\n" . $column['name'] . ' integer PRIMARY KEY,';
			$done_primary = true;
			continue;
		}
		elseif (isset($column['default']) && $column['default'] !== null)
			$default = 'default \'' . $smcFunc['db_escape_string']($column['default']) . '\'';
		else
			$default = '';

		// Sort out the size... and stuff...
		$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
		list ($type, $size) = $smcFunc['db_calculate_type']($column['type'], $column['size']);
		if ($size !== null)
			$type = $type . '(' . $size . ')';

		// Now just put it together!
		$table_query .= "\n\t" . $column['name'] . ' ' . $type . ' ' . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default . ',';
	}

	// Loop through the indexes next...
	$index_queries = array();
	foreach ($indexes as $index)
	{
		$columns = implode(',', $index['columns']);

		// Is it the primary?
		if (isset($index['type']) && $index['type'] == 'primary')
		{
			// If we've done the primary via auto_inc, don't do it again!
			if (!$done_primary)
				$table_query .= "\n\t" . 'PRIMARY KEY (' . implode(',', $index['columns']) . '),';
		}
		else
		{
			if (empty($index['name']))
				$index['name'] = implode('_', $index['columns']);
			$index_queries[] = 'CREATE ' . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : '') . ' INDEX ' . $table_name . '_' . $index['name'] . ' ON ' . $table_name . ' (' . $columns . ')';
		}
	}

	// No trailing commas!
	if (substr($table_query, -1) == ',')
		$table_query = substr($table_query, 0, -1);

	$table_query .= ')';

	if (empty($parameters['skip_transaction']))
		$smcFunc['db_transaction']('begin');

	// Do the table and indexes...
	$smcFunc['db_query']('', $table_query,
		array(
			'security_override' => true,
		)
	);
	foreach ($index_queries as $query)
		$smcFunc['db_query']('', $query,
		array(
			'security_override' => true,
		)
	);

	if (empty($parameters['skip_transaction']))
		$smcFunc['db_transaction']('commit');
}

// Drop a table.
function smf_db_drop_table($table_name, $parameters = array(), $error = 'fatal')
{
	global $reservedTables, $smcFunc, $db_prefix;

	// Strip out the table name, we might not need it in some cases
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;
	$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// God no - dropping one of these = bad.
	if (in_array(strtolower($table_name), $reservedTables))
		return false;

	// Does it exist?
	if (in_array($full_table_name, $smcFunc['db_list_tables']()))
	{
		$query = 'DROP TABLE ' . $table_name;
		$smcFunc['db_query']('', $query,
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

	// Alter the table to add the column.
	if ($smcFunc['db_alter_table']($table_name, array('add' => array($column_info))) === false)
		return false;

	return true;
}

// We can't reliably do this on SQLite - damn!
function smf_db_remove_column($table_name, $column_name, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	if ($smcFunc['db_alter_table']($table_name, array('remove' => array(array('name' => $column_name)))))
		return true;
	else
		return false;
}

// Change a column.
function smf_db_change_column($table_name, $old_column, $column_info, $parameters = array(), $error = 'fatal')
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	if ($smcFunc['db_alter_table']($table_name, array('change' => array(array('name' => $old_column) + $column_info))))
		return true;
	else
		return false;
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
		//!!! Doesn't work with PRIMARY KEY yet.
	}
	else
	{
		$smcFunc['db_query']('', '
			CREATE ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : '') . ' INDEX ' . $index_info['name'] . ' ON ' . $table_name . ' (' . $columns . ')',
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
		//!!! Doesn't do primary key at the moment!
		if ($index['type'] != 'primary' && $index['name'] == $index_name)
		{
			// Drop the bugger...
			$smcFunc['db_query']('', '
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
	// Generic => Specific.
	if (!$reverse)
	{
		$types = array(
			'mediumint' => 'int',
			'tinyint' => 'smallint',
			'mediumtext' => 'text',
			'largetext' => 'text',
		);
	}
	else
	{
		$types = array(
			'integer' => 'int',
		);
	}

	// Got it? Change it!
	if (isset($types[$type_name]))
	{
		if ($type_name == 'tinytext')
			$type_size = 255;
		$type_name = $types[$type_name];
	}
	// Numbers don't have a size.
	if (strpos($type_name, 'int') !== false)
		$type_size = null;

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

// Harder than it should be on sqlite!
function smf_db_list_columns($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		PRAGMA table_info(' . $table_name . ')',
		array(
			'security_override' => true,
		)
	);
	$columns = array();

	$primaries = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!$detail)
		{
			$columns[] = $row['name'];
		}
		else
		{
			// Auto increment is hard to tell really... if there's only one primary it probably is.
			if ($row['pk'])
				$primaries[] = $row['name'];

			// Can we split out the size?
			if (preg_match('~(.+?)\s*\((\d+)\)~i', $row['type'], $matches))
			{
				$type = $matches[1];
				$size = $matches[2];
			}
			else
			{
				$type = $row['type'];
				$size = null;
			}

			$columns[$row['name']] = array(
				'name' => $row['name'],
				'null' => $row['notnull'] ? false : true,
				'default' => $row['dflt_value'],
				'type' => $type,
				'size' => $size,
				'auto' => false,
			);
		}
	}
	$smcFunc['db_free_result']($result);

	// Put in our guess at auto_inc.
	if (count($primaries) == 1)
		$columns[$primaries[0]]['auto'] = true;

	return $columns;
}

// What about some index information?
function smf_db_list_indexes($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		PRAGMA index_list(' . $table_name . ')',
		array(
			'security_override' => true,
		)
	);
	$indexes = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!$detail)
			$indexes[] = $row['name'];
		else
		{
			$result2 = $smcFunc['db_query']('', '
				PRAGMA index_info(' . $row['name'] . ')',
				array(
					'security_override' => true,
				)
			);
			while ($row2 = $smcFunc['db_fetch_assoc']($result2))
			{
				// What is the type?
				if ($row['unique'])
					$type = 'unique';
				else
					$type = 'index';

				// This is the first column we've seen?
				if (empty($indexes[$row['name']]))
				{
					$indexes[$row['name']] = array(
						'name' => $row['name'],
						'type' => $type,
						'columns' => array(),
					);
				}

				// Add the column...
				$indexes[$row['name']]['columns'][] = $row2['name'];
			}
			$smcFunc['db_free_result']($result2);
		}
	}
	$smcFunc['db_free_result']($result);

	return $indexes;
}

function smf_db_alter_table($table_name, $columns)
{
	global $smcFunc, $db_prefix, $db_name, $boarddir;

	$db_file = substr($db_name, -3) === '.db' ? $db_name : $db_name . '.db';

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Let's get the current columns for the table.
	$current_columns = $smcFunc['db_list_columns']($table_name, true);

	// Let's get a list of columns for the temp table.
	$temp_table_columns = array();

	// Let's see if we have columns to remove or columns that are being added that already exist.
	foreach ($current_columns as $key => $column)
	{
		$exists = false;
		if (isset($columns['remove']))
			foreach ($columns['remove'] as $drop)
				if ($drop['name'] == $column['name'])
				{
					$exists = true;
					break;
				}

		if (isset($columns['add']))
			foreach ($columns['add'] as $key2 => $add)
				if ($add['name'] == $column['name'])
				{
					unset($columns['add'][$key2]);
					break;
				}

		// Doesn't exist then we 'remove'.
		if (!$exists)
			$temp_table_columns[] = $column['name'];
	}

	// If they are equal then that means that the column that we are adding exists or it doesn't exist and we are not looking to change any one of them.
	if (count($temp_table_columns) == count($current_columns) && empty($columns['change']) && empty($columns['add']))
		return true;

	// Drop the temp table.
	$smcFunc['db_query']('', '
		DROP TABLE {raw:temp_table_name}',
		array(
			'temp_table_name' => $table_name . '_tmp',
			'db_error_skip' => true,
		)
	);

	// Let's make a backup of the current database.
	// We only want the first backup of a table modification.  So if there is a backup file and older than an hour just delete and back up again
	$db_backup_file = $boarddir . '/Packages/backups/backup_' . $table_name . '_' . basename($db_file) . md5($table_name . $db_file);
	if (file_exists($db_backup_file) && time() - filemtime($db_backup_file) > 3600)
	{
		@unlink($db_backup_file);
		@copy($db_file, $db_backup_file);
	}
	elseif (!file_exists($db_backup_file))
		@copy($db_file, $db_backup_file);

	// If we don't have temp tables then everything crapped out.  Just exit.
	if (empty($temp_table_columns))
		return false;

	// Start
	$smcFunc['db_transaction']('begin');

	// Let's create the temporary table.
	$createTempTable = $smcFunc['db_query']('', '
		CREATE TEMPORARY TABLE {raw:temp_table_name}
		(
			{raw:columns}
		);',
		array(
			'temp_table_name' => $table_name . '_tmp',
			'columns' => implode(', ', $temp_table_columns),
			'db_error_skip' => true,
		)
	) !== false;

	if (!$createTempTable)
		return false;

	// Insert into temp table.
	$smcFunc['db_query']('', '
		INSERT INTO {raw:temp_table_name}
			({raw:columns})
		SELECT {raw:columns}
		FROM {raw:table_name}',
		array(
			'table_name' => $table_name,
			'columns' => implode(', ', $temp_table_columns),
			'temp_table_name' => $table_name . '_tmp',
		)
	);

	// Drop the current table.
	$dropTable = $smcFunc['db_query']('', '
		DROP TABLE {raw:table_name}',
		array(
			'table_name' => $table_name,
			'db_error_skip' => true,
		)
	) !== false;

	// If you can't drop the main table then there is no where to go from here. Just return.
	if (!$dropTable)
		return false;

	// We need to keep track of the structure for the current columns and the new columns.
	$new_columns = array();
	$column_names = array();

	// Let's get the ones that we already have first.
	foreach ($current_columns as $name => $column)
	{
		if (in_array($name, $temp_table_columns))
		{
			$new_columns[$name] = array(
				'name' => $name,
				'type' => $column['type'],
				'size' => isset($column['size']) ? (int) $column['size'] : null,
				'null' => !empty($column['null']),
				'auto' => isset($column['auto']) ? $column['auto'] : false,
				'default' => isset($column['default']) ? $column['default'] : '',
			);

			// Lets keep track of the name for the column.
			$column_names[$name] = $name;
		}
	}

	// Now the new.
	if (!empty($columns['add']))
		foreach ($columns['add'] as $add)
		{
			$new_columns[$add['name']] = array(
				'name' => $add['name'],
				'type' => $add['type'],
				'size' => isset($add['size']) ? (int) $add['size'] : null,
				'null' => !empty($add['null']),
				'auto' => isset($add['auto']) ? $add['auto'] : false,
				'default' => isset($add['default']) ? $add['default'] : '',
			);

			// Let's keep track of the name for the column.
			$column_names[$add['name']] = strstr('int', $add['type']) ? ' 0 AS ' . $add['name'] : ' {string:empty_string} AS ' . $add['name'];
		}

	// Now to change a column.  Not drop but change it.
	if (isset($columns['change']))
		foreach ($columns['change'] as $change)
			if (isset($new_columns[$change['name']]))
				$new_columns[$change['name']] = array(
					'name' => $change['name'],
					'type' => $change['type'],
					'size' => isset($change['size']) ? (int) $change['size'] : null,
					'null' => !empty($change['null']),
					'auto' => isset($change['auto']) ? $change['auto'] : false,
					'default' => isset($change['default']) ? $change['default'] : '',
				);

	// Now let's create the table.
	$createTable = $smcFunc['db_create_table']($table_name, $new_columns, array(), array('skip_transaction' => true));

	// Did it create correctly?
	if ($createTable === false)
		return false;

	// Back to it's original table.
	$insertData = $smcFunc['db_query']('', '
		INSERT INTO {raw:table_name}
			({raw:columns})
		SELECT ' . implode(', ', $column_names) . '
		FROM {raw:temp_table_name}',
		array(
			'table_name' => $table_name,
			'columns' => implode(', ', array_keys($new_columns)),
			'columns_select' => implode(', ', $column_names),
			'temp_table_name' => $table_name . '_tmp',
			'empty_string' => '',
		)
	);

	// Did everything insert correctly?
	if (!$insertData)
		return false;

	// Drop the temp table.
	$smcFunc['db_query']('', '
		DROP TABLE {raw:temp_table_name}',
		array(
			'temp_table_name' => $table_name . '_tmp',
			'db_error_skip' => true,
		)
	);

	// Commit or else there is no point in doing the previous steps.
	$smcFunc['db_transaction']('commit');

	// We got here so we're good.  The temp table should be deleted, if not it will be gone later on >:D.
	return true;
}

?>