<?php

/**
 * This file contains database functionality specifically designed for packages (mods) to utilize.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Add the file functions to the $smcFunc array.
 */
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
	$reservedTables = array(
		'admin_info_files', 'approval_queue', 'attachments',
		'background_tasks', 'ban_groups', 'ban_items', 'board_permissions',
		'board_permissions_view', 'boards', 'calendar', 'calendar_holidays',
		'categories', 'custom_fields', 'group_moderators', 'log_actions',
		'log_activity', 'log_banned', 'log_boards', 'log_comments',
		'log_digest', 'log_errors', 'log_floodcontrol', 'log_group_requests',
		'log_mark_read', 'log_member_notices', 'log_notify', 'log_online',
		'log_packages', 'log_polls', 'log_reported', 'log_reported_comments',
		'log_scheduled_tasks', 'log_search_messages', 'log_search_results',
		'log_search_subjects', 'log_search_topics', 'log_spider_hits',
		'log_spider_stats', 'log_subscribed', 'log_topics', 'mail_queue',
		'member_logins', 'membergroups', 'members', 'mentions',
		'message_icons', 'messages', 'moderator_groups', 'moderators',
		'package_servers', 'permission_profiles', 'permissions',
		'personal_messages', 'pm_labeled_messages', 'pm_labels',
		'pm_recipients', 'pm_rules', 'poll_choices', 'polls', 'qanda',
		'scheduled_tasks', 'sessions', 'settings', 'smiley_files', 'smileys',
		'spiders', 'subscriptions', 'themes', 'topics', 'user_alerts',
		'user_alerts_prefs', 'user_drafts', 'user_likes',
	);
	foreach ($reservedTables as $k => $table_name)
		$reservedTables[$k] = strtolower($db_prefix . $table_name);

	// We in turn may need the extra stuff.
	db_extend('extra');
}

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
 *  	- 'null' => Can it be null (true or false) - if not set default will be false.
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
 * @return boolean Whether or not the operation was successful
 */
function smf_db_create_table($table_name, $columns, $indexes = array(), $parameters = array(), $if_exists = 'ignore', $error = 'fatal')
{
	global $reservedTables, $smcFunc, $db_package_log, $db_prefix, $db_character_set, $db_name;

	static $engines = array();

	$old_table_exists = false;

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
		elseif ($if_exists == 'update')
		{
			$smcFunc['db_transaction']('begin');
			$db_trans = true;
			$smcFunc['db_drop_table']($table_name . '_old');
			$smcFunc['db_query']('', '
				RENAME TABLE ' . $table_name . ' TO ' . $table_name . '_old',
				array(
					'security_override' => true,
				)
			);
			$old_table_exists = true;
		}
		else
			return $if_exists == 'ignore';
	}

	// Righty - let's do the damn thing!
	$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
	foreach ($columns as $column)
		$table_query .= "\n\t" . smf_db_create_query_column($column) . ',';

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

	// Which engine do we want here?
	if (empty($engines))
	{
		// Figure out which engines we have
		$get_engines = $smcFunc['db_query']('', 'SHOW ENGINES', array());

		while ($row = $smcFunc['db_fetch_assoc']($get_engines))
		{
			if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT')
				$engines[] = $row['Engine'];
		}

		$smcFunc['db_free_result']($get_engines);
	}

	// If we don't have this engine, or didn't specify one, default to InnoDB or MyISAM
	// depending on which one is available
	if (!isset($parameters['engine']) || !in_array($parameters['engine'], $engines))
	{
		$parameters['engine'] = in_array('InnoDB', $engines) ? 'InnoDB' : 'MyISAM';
	}

	$table_query .= ') ENGINE=' . $parameters['engine'];
	if (!empty($db_character_set) && $db_character_set == 'utf8')
		$table_query .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

	// Create the table!
	$smcFunc['db_query']('', $table_query,
		array(
			'security_override' => true,
		)
	);

	// Fill the old data
	if ($old_table_exists)
	{
		$same_col = array();

		$request = $smcFunc['db_query']('', '
			SELECT count(*), column_name
			FROM information_schema.columns
			WHERE table_name in ({string:table1},{string:table2}) AND table_schema = {string:schema}
			GROUP BY column_name
			HAVING count(*) > 1',
			array(
				'table1' => $table_name,
				'table2' => $table_name . '_old',
				'schema' => $db_name,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$same_col[] = $row['column_name'];
		}

		$smcFunc['db_query']('', '
			INSERT INTO ' . $table_name . '('
			. implode($same_col, ',') .
			')
			SELECT ' . implode($same_col, ',') . '
			FROM ' . $table_name . '_old',
			array()
		);

		$smcFunc['db_drop_table']($table_name . '_old');
	}

	return true;
}

/**
 * Drop a table.
 *
 * @param string $table_name The name of the table to drop
 * @param array $parameters Not used at the moment
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
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

/**
 * This function adds a column.
 *
 * @param string $table_name The name of the table to add the column to
 * @param array $column_info An array of column info ({@see smf_db_create_table})
 * @param array $parameters Not used?
 * @param string $if_exists What to do if the column exists. If 'update', column is updated.
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
function smf_db_add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
{
	global $smcFunc, $db_package_log, $db_prefix;

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

	// Now add the thing!
	$query = '
		ALTER TABLE ' . $table_name . '
		ADD ' . smf_db_create_query_column($column_info) . (empty($column_info['auto']) ? '' : ' primary key'
	);
	$smcFunc['db_query']('', $query,
		array(
			'security_override' => true,
		)
	);

	return true;
}

/**
 * Removes a column.
 *
 * @param string $table_name The name of the table to drop the column from
 * @param string $column_name The name of the column to drop
 * @param array $parameters Not used?
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
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

/**
 * Change a column.
 *
 * @param string $table_name The name of the table this column is in
 * @param string $old_column The name of the column we want to change
 * @param array $column_info An array of info about the "new" column definition (see {@link smf_db_create_table()})
 * @return bool
 */
function smf_db_change_column($table_name, $old_column, $column_info)
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

/**
 * Add an index.
 *
 * @param string $table_name The name of the table to add the index to
 * @param array $index_info An array of index info (see {@link smf_db_create_table()})
 * @param array $parameters Not used?
 * @param string $if_exists What to do if the index exists. If 'update', the definition will be updated.
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
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

/**
 * Remove an index.
 *
 * @param string $table_name The name of the table to remove the index from
 * @param string $index_name The name of the index to remove
 * @param array $parameters Not used?
 * @param string $error
 * @return boolean Whether or not the operation was successful
 */
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

/**
 * Get the schema formatted name for a type.
 *
 * @param string $type_name The data type (int, varchar, smallint, etc.)
 * @param int $type_size The size (8, 255, etc.)
 * @param boolean $reverse
 * @return array An array containing the appropriate type and size for this DB type
 */
function smf_db_calculate_type($type_name, $type_size = null, $reverse = false)
{
	// MySQL is actually the generic baseline.

	$type_name = strtolower($type_name);
	// Generic => Specific.
	if (!$reverse)
	{
		$types = array(
			'inet' => 'varbinary',
		);
	}
	else
	{
		$types = array(
			'varbinary' => 'inet',
		);
	}

	// Got it? Change it!
	if (isset($types[$type_name]))
	{
		if ($type_name == 'inet' && !$reverse)
		{
			$type_size = 16;
			$type_name = 'varbinary';
		}
		elseif ($type_name == 'varbinary' && $reverse && $type_size == 16)
		{
			$type_name = 'inet';
			$type_size = null;
		}
		elseif ($type_name == 'varbinary')
			$type_name = 'varbinary';
		else
			$type_name = $types[$type_name];
	}

	return array($type_name, $type_size);
}

/**
 * Get table structure.
 *
 * @param string $table_name The name of the table
 * @return array An array of table structure - the name, the column info from {@link smf_db_list_columns()} and the index info from {@link smf_db_list_indexes()}
 */
function smf_db_table_structure($table_name)
{
	global $smcFunc, $db_prefix;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	// Find the table engine and add that to the info as well
	$table_status = $smcFunc['db_query']('', '
		SHOW TABLE STATUS
		LIKE {string:table}',
		array(
			'table' => strtr($table_name, array('_' => '\\_', '%' => '\\%'))
		)
	);

	// Only one row, so no need for a loop...
	$row = $smcFunc['db_fetch_assoc']($table_status);

	$smcFunc['db_free_result']($table_status);

	return array(
		'name' => $table_name,
		'columns' => $smcFunc['db_list_columns']($table_name, true),
		'indexes' => $smcFunc['db_list_indexes']($table_name, true),
		'engine' => $row['Engine'],
	);
}

/**
 * Return column information for a table.
 *
 * @param string $table_name The name of the table to get column info for
 * @param bool $detail Whether or not to return detailed info. If true, returns the column info. If false, just returns the column names.
 * @param array $parameters Not used?
 * @return array An array of column names or detailed column info, depending on $detail
 */
function smf_db_list_columns($table_name, $detail = false, $parameters = array())
{
	global $smcFunc, $db_prefix, $db_name;

	$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

	$result = $smcFunc['db_query']('', '
		SELECT column_name "Field", COLUMN_TYPE "Type", is_nullable "Null", COLUMN_KEY "Key" , column_default "Default", extra "Extra"
		FROM information_schema.columns
		WHERE table_name = {string:table_name}
			AND table_schema = {string:db_name}
		ORDER BY ordinal_position',
		array(
			'table_name' => $table_name,
			'db_name' => $db_name,
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

/**
 * Get index information.
 *
 * @param string $table_name The name of the table to get indexes for
 * @param bool $detail Whether or not to return detailed info.
 * @param array $parameters Not used?
 * @return array An array of index names or a detailed array of index info, depending on $detail
 */
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

/**
 * Creates a query for a column
 *
 * @param array $column An array of column info
 * @return string The column definition
 */
function smf_db_create_query_column($column)
{
	global $smcFunc;

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
	return '`' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default;
}

?>