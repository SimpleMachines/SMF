<?php

/**
 * This file has all the main functions in it that relate to the database.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2013 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 *  Maps the implementations in this file (smf_db_function_name)
 *  to the $smcFunc['db_function_name'] variable.
 *
 * @param string $db_server
 * @param string $db_name
 * @param string $db_user
 * @param string $db_passwd
 * @param string $db_prefix
 * @param array $db_options
 */
function smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
{
	global $smcFunc, $mysql_set_mode, $db_in_transact, $sqlite_error;

	// Map some database specific functions, only do this once.
	// SQLite3 does not have any procedural functions, so we need to map them all...
	if (!isset($smcFunc['db_fetch_assoc']) || $smcFunc['db_fetch_assoc'] != 'smf_db_fetch_array')
		$smcFunc += array(
			'db_query' => 'smf_db_query',
			'db_quote' => 'smf_db_quote',
			'db_fetch_assoc' => 'smf_db_fetch_array',
			'db_fetch_row' => 'smf_db_fetch_row',
			'db_free_result' => 'smf_db_free_result',
			'db_insert' => 'smf_db_insert',
			'db_insert_id' => 'smf_db_insert_id',
			'db_num_rows' => 'smf_db_num_rows',
			'db_data_seek' => 'smf_db_seek',
			'db_num_fields' => 'smf_db_num_fields',
			'db_escape_string' => 'smf_db_escape_string',
			'db_unescape_string' => 'smf_db_unescape_string',
			'db_server_info' => 'smf_db_libversion',
			'db_affected_rows' => 'smf_db_affected_rows',
			'db_transaction' => 'smf_db_transaction',
			'db_error' => 'smf_db_last_error',
			'db_select_db' => '',
			'db_title' => 'SQLite3',
			'db_sybase' => true,
			'db_case_sensitive' => true,
			'db_escape_wildcard_string' => 'smf_db_escape_wildcard_string',
		);

	if (substr($db_name, -3) != '.db')
		$db_name .= '.db';

	// SQLite3 doesn't support persistent connections...
	$connection = new SQLite3($db_name);

	// Something's wrong, show an error if its fatal (which we assume it is)
	if (!$connection)
	{
		if (!empty($db_options['non_fatal']))
			return null;
		else
			display_db_error();
	}
	$db_in_transact = false;

	// This is frankly stupid - stop SQLite returning alias names!
	@$connection->exec('PRAGMA short_column_names = 1');

	// Make some user defined functions!
	smf_db_define_udfs($connection);

	return $connection;
}

/**
 * Extend the database functionality. It calls the respective file's init
 * to add the implementations in that file to $smcFunc array.
 *
 * @param string $type indicated which additional file to load. ('extra', 'packages')
 */
function db_extend($type = 'extra')
{
	global $sourcedir, $db_type;

	require_once($sourcedir . '/Db' . strtoupper($type[0]) . substr($type, 1) . '-' . $db_type . '.php');
	$initFunc = 'db_' . $type . '_init';
	$initFunc();
}

/**
 * Verify that we have a valid connection
 * 
 * @param resource $connection
 * Thanks to tinoest for this idea
 */
function smf_db_check_connection($connection)
{
	global $db_name;

 	if (!is_object($connection) || is_null($connection))
 	{
 		if (substr($db_name, -3) != '.db')
 			$db_name .= '.db';

		$connection = new Sqlite3($db_name);

		smf_db_define_udfs($connection);
	}

	return $connection;
}

/**
 * Define user-defined functions for SQLite3
 * @param resource $connection
 */
function smf_db_define_udfs(&$connection)
{
	$connection->createFunction('unix_timestamp', 'smf_udf_unix_timestamp', 0);
	$connection->createFunction('inet_aton', 'smf_udf_inet_aton', 1);
	$connection->createFunction('inet_ntoa', 'smf_udf_inet_ntoa', 1);
	$connection->createFunction('find_in_set', 'smf_udf_find_in_set', 2);
	$connection->createFunction('year', 'smf_udf_year', 1);
	$connection->createFunction('month', 'smf_udf_month', 1);
	$connection->createFunction('dayofmonth', 'smf_udf_dayofmonth', 1);
	$connection->createFunction('concat', 'smf_udf_concat');
	$connection->createFunction('locate', 'smf_udf_locate', 2);
	$connection->createFunction('regexp', 'smf_udf_regexp', 2);
}

/**
 * Fix db prefix if necessary.
 * SQLite doesn't actually need this!
 *
 * @param type $db_prefix
 * @param type $db_name
 */
function db_fix_prefix(&$db_prefix, $db_name)
{
	return false;
}

/**
 * Callback for preg_replace_calback on the query.
 * It allows to replace on the fly a few pre-defined strings, for
 * convenience ('query_see_board', 'query_wanna_see_board'), with
 * their current values from $user_info.
 * In addition, it performs checks and sanitization on the values
 * sent to the database.
 *
 * @param $matches
 */
function smf_db_replacement__callback($matches)
{
	global $db_callback, $user_info, $db_prefix, $smcFunc;

	list ($values, $connection) = $db_callback;

	if ($matches[1] === 'db_prefix')
		return $db_prefix;

	if ($matches[1] === 'query_see_board')
		return $user_info['query_see_board'];

	if ($matches[1] === 'query_wanna_see_board')
		return $user_info['query_wanna_see_board'];

	if ($matches[1] === 'empty')
		return '\'\'';

	if (!isset($matches[2]))
		smf_db_error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

	if ($matches[1] === 'literal')
		return '\'' . SQLite::escapeString($matches[2]) . '\'';

	if (!isset($values[$matches[2]]))
		smf_db_error_backtrace('The database value you\'re trying to insert does not exist: ' . (isset($smcFunc['htmlspecialchars']) ? $smcFunc['htmlspecialchars']($matches[2]) : htmlspecialchars($matches[2])), '', E_USER_ERROR, __FILE__, __LINE__);

	$replacement = $values[$matches[2]];

	switch ($matches[1])
	{
		case 'int':
			if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement)
				smf_db_error_backtrace('Wrong value type sent to the database. Integer expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			return (string) (int) $replacement;
		break;

		case 'string':
		case 'text':
			return sprintf('\'%1$s\'', SQLite3::escapeString($replacement));
		break;

		case 'array_int':
			if (is_array($replacement))
			{
				if (empty($replacement))
					smf_db_error_backtrace('Database error, given array of integer values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				foreach ($replacement as $key => $value)
				{
					if (!is_numeric($value) || (string) $value !== (string) (int) $value)
						smf_db_error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

					$replacement[$key] = (string) (int) $value;
				}

				return implode(', ', $replacement);
			}
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

		break;

		case 'array_string':
			if (is_array($replacement))
			{
				if (empty($replacement))
					smf_db_error_backtrace('Database error, given array of string values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				foreach ($replacement as $key => $value)
					$replacement[$key] = sprintf('\'%1$s\'', SQLite3::escapeString($value));

				return implode(', ', $replacement);
			}
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		break;

		case 'date':
			if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1)
				return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Date expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		break;

		case 'float':
			if (!is_numeric($replacement))
				smf_db_error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			return (string) (float) $replacement;
		break;

		case 'identifier':
			return '`' . strtr($replacement, array('`' => '', '.' => '')) . '`';
		break;

		case 'raw':
			return $replacement;
		break;

		default:
			smf_db_error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')', '', false, __FILE__, __LINE__);
		break;
	}
}

/**
 * Just like the db_query, escape and quote a string,
 * but not executing the query.
 *
 * @param string $db_string
 * @param string $db_values
 * @param resource $connection
 */
function smf_db_quote($db_string, $db_values, $connection = null)
{
	global $db_callback, $db_connection;

	// Only bother if there's something to replace.
	if (strpos($db_string, '{') !== false)
	{
		// This is needed by the callback function.
		$db_callback = array($db_values, $connection === null ? $db_connection : $connection);

		// Do the quoting and escaping
		$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'smf_db_replacement__callback', $db_string);

		// Clear this global variable.
		$db_callback = array();
	}

	return $db_string;
}

/**
 * 
 * @param resource $handle
 */
function smf_db_fetch_array($handle)
{
	return $handle->fetchArray();
}

/**
 * Do a query.  Takes care of errors too.
 *
 * @param string $identifier
 * @param string $db_string
 * @param string $db_values
 * @param resource $connection
 */
function smf_db_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $db_cache, $db_count, $db_connection, $db_show_debug, $time_start;
	global $db_unbuffered, $db_callback, $modSettings;

	// Decide which connection to use.
	$connection = smf_db_check_connection($connection === null ? $db_connection : $connection);

	// Special queries that need processing.
	$replacements = array(
		'birthday_array' => array(
			'~DATE_FORMAT\(([^,]+),\s*([^\)]+)\s*\)~' => 'strftime($2, $1)'
		),
		'substring' => array(
			'~SUBSTRING~' => 'SUBSTR',
		),
		'truncate_table' => array(
			'~TRUNCATE~i' => 'DELETE FROM',
		),
		'user_activity_by_time' => array(
			'~HOUR\(FROM_UNIXTIME\((poster_time\s+\+\s+\{int:.+\})\)\)~' => 'strftime(\'%H\', datetime($1, \'unixepoch\'))',
		),
		'unread_fetch_topic_count' => array(
			'~\s*SELECT\sCOUNT\(DISTINCT\st\.id_topic\),\sMIN\(t\.id_last_msg\)(.+)$~is' => 'SELECT COUNT(id_topic), MIN(id_last_msg) FROM (SELECT DISTINCT t.id_topic, t.id_last_msg $1)',
		),
		'alter_table_boards' => array(
			'~(.+)~' => '',
		),
		'get_random_number' => array(
			'~RAND~' => 'RANDOM',
		),
		'set_character_set' => array(
			'~(.+)~' => '',
		),
		'themes_count' => array(
			'~\s*SELECT\sCOUNT\(DISTINCT\sid_member\)\sAS\svalue,\sid_theme.+FROM\s(.+themes)(.+)~is' => 'SELECT COUNT(id_member) AS value, id_theme FROM (SELECT DISTINCT id_member, id_theme, variable FROM $1) $2',
		),
		'attach_download_increase' => array(
			'~LOW_PRIORITY~' => '',
		),
		'pm_conversation_list' => array(
			'~ORDER BY id_pm~' => 'ORDER BY MAX(pm.id_pm)',
		),
		'boardindex_fetch_boards' => array(
			'~(.)$~' => '$1 ORDER BY b.board_order',
		),
		'messageindex_fetch_boards' => array(
			'~(.)$~' => '$1 ORDER BY b.board_order',
		),
		'order_by_board_order' => array(
			'~(.)$~' => '$1 ORDER BY b.board_order',
		),
		'spider_check' => array(
			'~(.)$~' => '$1 ORDER BY LENGTH(user_agent) DESC',
		),
	);

	if (isset($replacements[$identifier]))
		$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);

	// SQLite doesn't support count(distinct).
	$db_string = trim($db_string);
	$db_string = preg_replace('~^\s*SELECT\s+?COUNT\(DISTINCT\s+?(.+?)\)(\s*AS\s*(.+?))*\s*(FROM.+)~is', 'SELECT COUNT(*) $2 FROM (SELECT DISTINCT $1 $4)', $db_string);

	// Or RLIKE.
	$db_string = preg_replace('~AND\s*(.+?)\s*RLIKE\s*(\{string:.+?\})~', 'AND REGEXP(\1, \2)', $db_string);

	// INSTR?  No support for that buddy :(
	if (preg_match('~INSTR\((.+?),\s(.+?)\)~', $db_string, $matches) === 1)
	{
		$db_string = preg_replace('~INSTR\((.+?),\s(.+?)\)~', '$1 LIKE $2', $db_string);
		list(, $search) = explode(':', substr($matches[2], 1, -1));
		$db_values[$search] = '%' . $db_values[$search] . '%';
	}

	// Lets remove ASC and DESC from GROUP BY clause.
	if (preg_match('~GROUP BY .*? (?:ASC|DESC)~is', $db_string, $matches))
	{
		$replace = str_replace(array('ASC', 'DESC'), '', $matches[0]);
		$db_string = str_replace($matches[0], $replace, $db_string);
	}

	// We need to replace the SUBSTRING in the sort identifier.
	if ($identifier == 'substring_membergroups' && isset($db_values['sort']))
		$db_values['sort'] = preg_replace('~SUBSTRING~', 'SUBSTR', $db_values['sort']);

	// SQLite doesn't support TO_DAYS but has the julianday function which can be used in the same manner.  But make sure it is being used to calculate a span.
	$db_string = preg_replace('~\(TO_DAYS\(([^)]+)\) - TO_DAYS\(([^)]+)\)\) AS span~', '(julianday($1) - julianday($2)) AS span', $db_string);

	// One more query....
	$db_count = !isset($db_count) ? 1 : $db_count + 1;

	if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
		smf_db_error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);

	if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false))
	{
		// Pass some values to the global space for use in the callback function.
		$db_callback = array($db_values, $connection);

		// Inject the values passed to this function.
		$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'smf_db_replacement__callback', $db_string);

		// This shouldn't be residing in global space any longer.
		$db_callback = array();
	}

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		// Get the file and line number this function was called.
		list ($file, $line) = smf_db_error_backtrace('', '', 'return', __FILE__, __LINE__);

		// Initialize $db_cache if not already initialized.
		if (!isset($db_cache))
			$db_cache = array();

		if (!empty($_SESSION['debug_redirect']))
		{
			$db_cache = array_merge($_SESSION['debug_redirect'], $db_cache);
			$db_count = count($db_cache) + 1;
			$_SESSION['debug_redirect'] = array();
		}

		$st = microtime();
		// Don't overload it.
		$db_cache[$db_count]['q'] = $db_count < 50 ? $db_string : '...';
		$db_cache[$db_count]['f'] = $file;
		$db_cache[$db_count]['l'] = $line;
		$db_cache[$db_count]['s'] = array_sum(explode(' ', $st)) - array_sum(explode(' ', $time_start));
	}

	$ret = @$connection->query($db_string);
	if ($ret === false && empty($db_values['db_error_skip']))
	{
		$err_msg = $connection->lastErrorMsg();
		$ret = smf_db_error($db_string . '#!#' . $err_msg, $connection);
	}

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$db_cache[$db_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));

	return $ret;
}

/**
 * affected_rows
 *
 * @param resource $connection
 */
function smf_db_affected_rows($connection = null)
{
	global $db_connection;
	
	$connection = smf_db_check_connection($connection == null ? $db_connection : $connection);

	return $connection->changes();
}

/**
 * insert_id
 *
 * @param string $table
 * @param string $field = null
 * @param resource $connection = null
 */
function smf_db_insert_id($table, $field = null, $connection = null)
{
	global $db_connection, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);
	$connection = smf_db_check_connection($connection == null ? $db_connection : $connection);

	// SQLite doesn't need the table or field information.
	return $connection->lastInsertRowid();
}

/**
 * Number of rows returned by a query.
 * 
 * @param resource $handle
 */
function smf_db_num_rows($handle)
{
	$num_rows = 0;
	
	// For some stupid reason, the sqlite3 extension doesn't have a numRows function
	while ($handle->fetchArray())
		$num_rows++;

	return $num_rows;
}

/**
 * Seek to a specific row in the result set
 * 
 * @param resource $handle
 * @param int $row
 */
function smf_db_seek(&$handle, $row)
{
	if ($row === 0)
	{
		$handle->Reset();
	}
	else
	{
		$i = 0;
		do
		{
			$handle->fetchArray();
			$i++;
		} while ($i < $row);
	}
	
	return $handle;
}

/**
 * 
 * @param resource $handle
 */
function smf_db_num_fields($handle)
{
	return $handle->numColumns();
}

/**
*
* @param string $string
*/
function smf_db_escape_string($string)
{
	return SQLite3::escapeString($string);
}

/**
 * Last error on SQLite
 */
function smf_db_last_error()
{
	global $db_connection, $sqlite_error;

	$query_errno = $db_connection->lastErrorCode();
	return $query_errno || empty($sqlite_error) ? $db_connection->lastErrorMsg() : $sqlite_error;
}

/**
 * Do a transaction.
 *
 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
 * @param resource $connection = null
 */
function smf_db_transaction($type = 'commit', $connection = null)
{
	global $db_connection, $db_in_transact;

	// Decide which connection to use
	$connection = smf_db_check_connection($connection === null ? $db_connection : $connection);

	if ($type == 'begin')
	{
		$db_in_transact = true;
		return @$connection->exec('BEGIN');
	}
	elseif ($type == 'rollback')
	{
		$db_in_transact = false;
		return @$connection->exec('ROLLBACK');
	}
	elseif ($type == 'commit')
	{
		$db_in_transact = false;
		return @$connection->exec('COMMIT');
	}

	return false;
}

/**
 * Database error!
 * Backtrace, log, try to fix.
 *
 * @param string $db_string
 * @param resource $connection = null
 */
function smf_db_error($db_string, $connection = null)
{
	global $txt, $context, $sourcedir, $webmaster_email, $modSettings;
	global $forum_version, $db_connection, $db_last_error, $db_persist;
	global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;
	global $smcFunc;

	// We'll try recovering the file and line number the original db query was called from.
	list ($file, $line) = smf_db_error_backtrace('', '', 'return', __FILE__, __LINE__);

	// Decide which connection to use
	$connection = smf_db_check_connection($connection === null ? $db_connection : $connection);

	// This is the error message...
	$query_errno = $connection->lastErrorCode();
	$query_error = $connection->lastErrorMsg();

	// Get the extra error message.
	$errStart = strrpos($db_string, '#!#');
	$query_error .= '<br />' . substr($db_string, $errStart + 3);
	$db_string = substr($db_string, 0, $errStart);

	// Log the error.
	if (function_exists('log_error'))
		log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" .$db_string : ''), 'database', $file, $line);

	// Sqlite optimizing - the actual error message isn't helpful or user friendly.
	if (strpos($query_error, 'no_access') !== false || strpos($query_error, 'database schema has changed') !== false)
	{
		if (!empty($context) && !empty($txt) && !empty($txt['error_sqlite_optimizing']))
			fatal_error($txt['error_sqlite_optimizing'], false);
		else
		{
			// Don't cache this page!
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
			header('Cache-Control: no-cache');

			// Send the right error codes.
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 3600');

			die('Sqlite is optimizing the database, the forum can not be accessed until it has finished.  Please try refreshing this page momentarily.');
		}
	}

	// Nothing's defined yet... just die with it.
	if (empty($context) || empty($txt))
		die($query_error);

	// Show an error message, if possible.
	$context['error_title'] = $txt['database_error'];
	if (allowedTo('admin_forum'))
		$context['error_message'] = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line;
	else
		$context['error_message'] = $txt['try_again'];

	if (allowedTo('admin_forum') && isset($db_show_debug) && $db_show_debug === true)
	{
		$context['error_message'] .= '<br /><br />' . nl2br($db_string);
	}

	// It's already been logged... don't log it again.
	fatal_error($context['error_message'], false);
}

/**
 * insert
 *
 * @param string $method, options 'replace', 'ignore', 'insert'
 * @param $table
 * @param $columns
 * @param $data
 * @param $keys
 * @param bool $disable_trans = false
 * @param resource $connection = null
 */
function smf_db_insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
{
	global $db_in_transact, $db_connection, $smcFunc, $db_prefix;

	$connection = $connection === null ? $db_connection : $connection;

	if (empty($data))
		return;

	if (!is_array($data[array_rand($data)]))
		$data = array($data);

	// Replace the prefix holder with the actual prefix.
	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$priv_trans = false;
	if (count($data) > 1 && !$db_in_transact && !$disable_trans)
	{
		$smcFunc['db_transaction']('begin', $connection);
		$priv_trans = true;
	}

	if (!empty($data))
	{
		// Create the mold for a single row insert.
		$insertData = '(';
		foreach ($columns as $columnName => $type)
		{
			// Are we restricting the length?
			if (strpos($type, 'string-') !== false)
				$insertData .= sprintf('SUBSTR({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
			else
				$insertData .= sprintf('{%1$s:%2$s}, ', $type, $columnName);
		}
		$insertData = substr($insertData, 0, -2) . ')';

		// Create an array consisting of only the columns.
		$indexed_columns = array_keys($columns);

		// Here's where the variables are injected to the query.
		$insertRows = array();
		foreach ($data as $dataRow)
			$insertRows[] = smf_db_quote($insertData, array_combine($indexed_columns, $dataRow), $connection);

		foreach ($insertRows as $entry)
			// Do the insert.
			$smcFunc['db_query']('',
				(($method === 'replace') ? 'REPLACE' : (' INSERT' . ($method === 'ignore' ? ' OR IGNORE' : ''))) . ' INTO ' . $table . '(' . implode(', ', $indexed_columns) . ')
				VALUES
					' . $entry,
				array(
					'security_override' => true,
					'db_error_skip' => $table === $db_prefix . 'log_errors',
				),
				$connection
			);
	}

	if ($priv_trans)
		$smcFunc['db_transaction']('commit', $connection);
}

/**
 * free_result
 *
 * @param resource $handle = false
 */
function smf_db_free_result($handle = false)
{
	return $handle->finalize();
}

/**
 * fetch_row
 * Make sure we return no string indexes!
 *
 * @param $handle
 */
function smf_db_fetch_row($handle)
{
	return $handle->fetchArray(SQLITE3_NUM);
}

/**
 * Unescape an escaped string!
 *
 * @param $string
 */
function smf_db_unescape_string($string)
{
	return strtr($string, array('\'\'' => '\''));
}

/**
 * This function tries to work out additional error information from a back trace.
 *
 * @param $error_message
 * @param $log_message
 * @param $error_type
 * @param $file
 * @param $line
 */
function smf_db_error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
{
	if (empty($log_message))
		$log_message = $error_message;

	foreach (debug_backtrace() as $step)
	{
		// Found it?
		if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), array('smf_db_', 'preg_re', 'db_erro', 'call_us')) && strpos($step['function'], '__') !== 0)
		{
			$log_message .= '<br />Function: ' . $step['function'];
			break;
		}

		if (isset($step['line']))
		{
			$file = $step['file'];
			$line = $step['line'];
		}
	}

	// A special case - we want the file and line numbers for debugging.
	if ($error_type == 'return')
		return array($file, $line);

	// Is always a critical error.
	if (function_exists('log_error'))
		log_error($log_message, 'critical', $file, $line);

	if (function_exists('fatal_error'))
	{
		fatal_error($error_message, $error_type);

		// Cannot continue...
		exit;
	}
	elseif ($error_type)
		trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
	else
		trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
}

/**
 * Emulate UNIX_TIMESTAMP.
 */
function smf_udf_unix_timestamp()
{
	return strftime('%s', 'now');
}

/**
 * Emulate INET_ATON.
 *
 * @param $ip
 */
function smf_udf_inet_aton($ip)
{
	$chunks = explode('.', $ip);
	return @$chunks[0] * pow(256, 3) + @$chunks[1] * pow(256, 2) + @$chunks[2] * 256 + @$chunks[3];
}

/**
 * Emulate INET_NTOA.
 *
 * @param $n
 */
function smf_udf_inet_ntoa($n)
{
	$t = array(0, 0, 0, 0);
	$msk = 16777216.0;
	$n += 0.0;
		if ($n < 1)
			return '0.0.0.0';

	for ($i = 0; $i < 4; $i++)
	{
		$k = (int) ($n / $msk);
		$n -= $msk * $k;
		$t[$i] = $k;
		$msk /= 256.0;
	};

	$a = join('.', $t);
	return $a;
}

/**
 * Emulate FIND_IN_SET.
 *
 * @param $find
 * @param $groups
 */
function smf_udf_find_in_set($find, $groups)
{
	foreach (explode(',', $groups) as $key => $group)
	{
		if ($group == $find)
			return $key + 1;
	}

	return 0;
}

/**
 * Emulate YEAR.
 *
 * @param $date
 */
function smf_udf_year($date)
{
	return substr($date, 0, 4);
}

/**
 * Emulate MONTH.
 *
 * @param $date
 */
function smf_udf_month($date)
{
	return substr($date, 5, 2);
}

/**
 * Emulate DAYOFMONTH.
 *
 * @param $date
 */
function smf_udf_dayofmonth($date)
{
	return substr($date, 8, 2);
}

/**
 * We need this since sqlite_libversion() doesn't take any parameters.
 *
 * @param $void
 */
function smf_db_libversion($void = null)
{
	$version = SQLite3::version();
	return $version['versionString'];
}

/**
 * This function uses variable argument lists so that it can handle more then two parameters.
 * Emulates the CONCAT function.
 */
function smf_udf_concat()
{
	// Since we didn't specify any arguments we must get them from PHP.
	$args = func_get_args();

	// It really doesn't matter if there were 0 to 100 arguments, just slap them all together.
	return implode('', $args);
}

/**
 * We need to use PHP to locate the position in the string.
 *
 * @param string $find
 * @param string $string
 */
function smf_udf_locate($find, $string)
{
	return strpos($string, $find);
}

/**
 * This is used to replace RLIKE.
 *
 * @param string $exp
 * @param string $search
 */
function smf_udf_regexp($exp, $search)
{
	if (preg_match($exp, $match))
		return 1;
	return 0;
}

/**
 * Escape the LIKE wildcards so that they match the character and not the wildcard.
 * The optional second parameter turns human readable wildcards into SQL wildcards.
 */
function smf_db_escape_wildcard_string($string, $translate_human_wildcards=false)
{
	$replacements = array(
		'%' => '\%',
		'\\' => '\\\\',
	);

	if ($translate_human_wildcards)
		$replacements += array(
			'*' => '%',
		);

	return strtr($string, $replacements);
}
?>