<?php

/**
 * This file has all the main functions in it that relate to the database.
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
 * Maps the implementations in this file (smf_db_function_name)
 * to the $smcFunc['db_function_name'] variable.
 *
 * @see Subs-Db-mysql.php#smf_db_initiate
 *
 * @param string $db_server The database server
 * @param string $db_name The name of the database
 * @param string $db_user The database username
 * @param string $db_passwd The database password
 * @param string $db_prefix The table prefix
 * @param array $db_options An array of database options
 * @return null|resource Returns null on failure if $db_options['non_fatal'] is true or a PostgreSQL connection resource handle if the connection was successful.
 */
function smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, &$db_prefix, $db_options = array())
{
	global $smcFunc;

	// Map some database specific functions, only do this once.
	if (!isset($smcFunc['db_fetch_assoc']))
		$smcFunc += array(
			'db_query'                  => 'smf_db_query',
			'db_quote'                  => 'smf_db_quote',
			'db_insert'                 => 'smf_db_insert',
			'db_insert_id'              => 'smf_db_insert_id',
			'db_fetch_assoc'            => 'smf_db_fetch_assoc',
			'db_fetch_row'              => 'smf_db_fetch_row',
			'db_free_result'            => 'pg_free_result',
			'db_num_rows'               => 'pg_num_rows',
			'db_data_seek'              => 'smf_db_data_seek',
			'db_num_fields'             => 'pg_num_fields',
			'db_escape_string'          => 'smf_db_escape_string',
			'db_unescape_string'        => 'stripslashes',
			'db_server_info'            => 'smf_db_version',
			'db_affected_rows'          => 'smf_db_affected_rows',
			'db_transaction'            => 'smf_db_transaction',
			'db_error'                  => 'pg_last_error',
			'db_select_db'              => 'smf_db_select_db',
			'db_title'                  => 'PostgreSQL',
			'db_sybase'                 => true,
			'db_case_sensitive'         => true,
			'db_escape_wildcard_string' => 'smf_db_escape_wildcard_string',
			'db_is_resource'            => 'is_resource',
			'db_mb4'                    => true,
			'db_ping'                   => 'pg_ping',
			'db_fetch_all'              => 'smf_db_fetch_all',
			'db_error_insert'           => 'smf_db_error_insert',
			'db_custom_order'           => 'smf_db_custom_order',
			'db_native_replace'         => 'smf_db_native_replace',
			'db_cte_support'            => 'smf_db_cte_support',
		);

	// We are not going to make it very far without these.
	if (!function_exists('pg_pconnect'))
		display_db_error();

	if (!empty($db_options['persist']))
		$connection = @pg_pconnect((empty($db_server) ? '' : 'host=' . $db_server . ' ') . 'dbname=' . $db_name . ' user=\'' . $db_user . '\' password=\'' . $db_passwd . '\'' . (empty($db_options['port']) ? '' : ' port=\'' . $db_options['port'] . '\''));
	else
		$connection = @pg_connect((empty($db_server) ? '' : 'host=' . $db_server . ' ') . 'dbname=' . $db_name . ' user=\'' . $db_user . '\' password=\'' . $db_passwd . '\'' . (empty($db_options['port']) ? '' : ' port=\'' . $db_options['port'] . '\''));

	// Something's wrong, show an error if its fatal (which we assume it is)
	if (!$connection)
	{
		if (!empty($db_options['non_fatal']))
		{
			return null;
		}
		else
		{
			display_db_error();
		}
	}

	if (!empty($db_options['db_mb4']))
		$smcFunc['db_mb4'] = (bool) $db_options['db_mb4'];

	return $connection;
}

/**
 * Extend the database functionality. It calls the respective file's init
 * to add the implementations in that file to $smcFunc array.
 *
 * @param string $type Indicates which additional file to load. ('extra', 'packages')
 */
function db_extend($type = 'extra')
{
	global $sourcedir, $db_type;

	require_once($sourcedir . '/Db' . strtoupper($type[0]) . substr($type, 1) . '-' . $db_type . '.php');
	$initFunc = 'db_' . $type . '_init';
	$initFunc();
}

/**
 * Fix the database prefix if necessary.
 * Does nothing on PostgreSQL
 *
 * @param string $db_prefix The database prefix
 * @param string $db_name The database name
 */
function db_fix_prefix(&$db_prefix, $db_name)
{
	return;
}

/**
 * Callback for preg_replace_callback on the query.
 * It allows to replace on the fly a few pre-defined strings, for convenience ('query_see_board', 'query_wanna_see_board', etc), with
 * their current values from $user_info.
 * In addition, it performs checks and sanitization on the values sent to the database.
 *
 * @param array $matches The matches from preg_replace_callback
 * @return string The appropriate string depending on $matches[1]
 */
function smf_db_replacement__callback($matches)
{
	global $db_callback, $user_info, $db_prefix, $smcFunc;

	list ($values, $connection) = $db_callback;

	if ($matches[1] === 'db_prefix')
		return $db_prefix;

	if (isset($user_info[$matches[1]]) && strpos($matches[1], 'query_') !== false)
		return $user_info[$matches[1]];

	if ($matches[1] === 'empty')
		return '\'\'';

	if (!isset($matches[2]))
		smf_db_error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

	if ($matches[1] === 'literal')
		return '\'' . pg_escape_string($matches[2]) . '\'';

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
			return sprintf('\'%1$s\'', pg_escape_string($replacement));
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
					$replacement[$key] = sprintf('\'%1$s\'', pg_escape_string($value));

				return implode(', ', $replacement);
			}
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		case 'date':
			if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1)
				return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]) . '::date';
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Date expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		case 'time':
			if (preg_match('~^([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $time_matches) === 1)
				return sprintf('\'%02d:%02d:%02d\'', $time_matches[1], $time_matches[2], $time_matches[3]) . '::time';
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Time expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		case 'datetime':
			if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d) ([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $datetime_matches) === 1)
				return 'to_timestamp(' .
					sprintf('\'%04d-%02d-%02d %02d:%02d:%02d\'', $datetime_matches[1], $datetime_matches[2], $datetime_matches[3], $datetime_matches[4], $datetime_matches[5], $datetime_matches[6]) .
					',\'YYYY-MM-DD HH24:MI:SS\')';
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Datetime expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		case 'float':
			if (!is_numeric($replacement))
				smf_db_error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			return (string) (float) $replacement;
			break;

		case 'identifier':
			return '"' . strtr($replacement, array('`' => '', '.' => '"."')) . '"';
			break;

		case 'raw':
			return $replacement;
			break;

		case 'inet':
			if ($replacement == 'null' || $replacement == '')
				return 'null';
			if (inet_pton($replacement) === false)
				smf_db_error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected.(' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			return sprintf('\'%1$s\'::inet', pg_escape_string($replacement));

		case 'array_inet':
			if (is_array($replacement))
			{
				if (empty($replacement))
					smf_db_error_backtrace('Database error, given array of IPv4 or IPv6 values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				foreach ($replacement as $key => $value)
				{
					if ($replacement == 'null' || $replacement == '')
						$replacement[$key] = 'null';
					if (!isValidIP($value))
						smf_db_error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected.(' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
					$replacement[$key] = sprintf('\'%1$s\'::inet', pg_escape_string($value));
				}

				return implode(', ', $replacement);
			}
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Array of IPv4 or IPv6 expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		default:
			smf_db_error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')', '', false, __FILE__, __LINE__);
			break;
	}
}

/**
 * Just like the db_query, escape and quote a string, but not executing the query.
 *
 * @param string $db_string The database string
 * @param array $db_values An array of values to be injected into the string
 * @param resource $connection = null The connection to use (null to use $db_connection)
 * @return string The string with the values inserted
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
 * Do a query.  Takes care of errors too.
 * Special queries may need additional replacements to be appropriate
 * for PostgreSQL.
 *
 * @param string $identifier An identifier. Only used in Postgres when we need to do things differently...
 * @param string $db_string The database string
 * @param array $db_values = array() The values to be inserted into the string
 * @param resource $connection = null The connection to use (null to use $db_connection)
 * @return resource|bool Returns a MySQL result resource (for SELECT queries), true (for UPDATE queries) or false if the query failed
 */
function smf_db_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $db_cache, $db_count, $db_connection, $db_show_debug, $time_start;
	global $db_callback, $db_last_result, $db_replace_result, $modSettings;

	// Decide which connection to use.
	$connection = $connection === null ? $db_connection : $connection;

	// Special queries that need processing.
	$replacements = array(
		'consolidate_spider_stats' => array(
			'~MONTH\(log_time\), DAYOFMONTH\(log_time\)~' => 'MONTH(CAST(CAST(log_time AS abstime) AS timestamp)), DAYOFMONTH(CAST(CAST(log_time AS abstime) AS timestamp))',
		),
		'get_random_number' => array(
			'~RAND~' => 'RANDOM',
		),
		'insert_log_search_topics' => array(
			'~NOT RLIKE~' => '!~',
		),
		'insert_log_search_results_no_index' => array(
			'~NOT RLIKE~' => '!~',
		),
		'insert_log_search_results_subject' => array(
			'~NOT RLIKE~' => '!~',
		),
		'profile_board_stats' => array(
			'~COUNT\(\*\) \/ MAX\(b.num_posts\)~' => 'CAST(COUNT(*) AS DECIMAL) / CAST(b.num_posts AS DECIMAL)',
		),
	);

	// Special optimizer Hints
	$query_opt = array(
		'load_board_info' => array(
			'join_collapse_limit' => 1,
		),
		'calendar_get_events' => array(
			'enable_seqscan' => 'off',
		),
	);

	if (isset($replacements[$identifier]))
		$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);

	// Limits need to be a little different.
	$db_string = preg_replace('~\sLIMIT\s(\d+|{int:.+}),\s*(\d+|{int:.+})\s*$~i', 'LIMIT $2 OFFSET $1', $db_string);

	if (trim($db_string) == '')
		return false;

	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments_from = array(
		'~\s+~s',
		'~/\*!40001 SQL_NO_CACHE \*/~',
		'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
		'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
	);
	static $allowed_comments_to = array(
		' ',
		'',
		'',
		'',
	);

	// One more query....
	$db_count = !isset($db_count) ? 1 : $db_count + 1;
	$db_replace_result = 0;

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

	// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
	if (empty($modSettings['disableQueryCheck']))
	{
		$clean = '';
		$old_pos = 0;
		$pos = -1;
		// Remove the string escape for better runtime
		$db_string_1 = str_replace('\'\'', '', $db_string);
		while (true)
		{
			$pos = strpos($db_string_1, '\'', $pos + 1);
			if ($pos === false)
				break;
			$clean .= substr($db_string_1, $old_pos, $pos - $old_pos);

			while (true)
			{
				$pos1 = strpos($db_string_1, '\'', $pos + 1);
				$pos2 = strpos($db_string_1, '\\', $pos + 1);
				if ($pos1 === false)
					break;
				elseif ($pos2 === false || $pos2 > $pos1)
				{
					$pos = $pos1;
					break;
				}

				$pos = $pos2 + 1;
			}
			$clean .= ' %s ';

			$old_pos = $pos + 1;
		}
		$clean .= substr($db_string_1, $old_pos);
		$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

		// Comments?  We don't use comments in our queries, we leave 'em outside!
		if (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
			$fail = true;
		// Trying to change passwords, slow us down, or something?
		elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
			$fail = true;
		elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
			$fail = true;

		if (!empty($fail) && function_exists('log_error'))
			smf_db_error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
	}

	// Set optimize stuff
	if (isset($query_opt[$identifier]))
	{
		$query_hints = $query_opt[$identifier];
		$query_hints_set = '';
		if (isset($query_hints['join_collapse_limit']))
		{
			$query_hints_set .= 'SET LOCAL join_collapse_limit = ' . $query_hints['join_collapse_limit'] . ';';
		}
		if (isset($query_hints['enable_seqscan']))
		{
			$query_hints_set .= 'SET LOCAL enable_seqscan = ' . $query_hints['enable_seqscan'] . ';';
		}

		$db_string = $query_hints_set . $db_string;
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

		// Don't overload it.
		$db_cache[$db_count]['q'] = $db_count < 50 ? $db_string : '...';
		$db_cache[$db_count]['f'] = $file;
		$db_cache[$db_count]['l'] = $line;
		$db_cache[$db_count]['s'] = ($st = microtime(true)) - $time_start;
	}

	$db_last_result = @pg_query($connection, $db_string);

	if ($db_last_result === false && empty($db_values['db_error_skip']))
		$db_last_result = smf_db_error($db_string, $connection);

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$db_cache[$db_count]['t'] = microtime(true) - $st;

	return $db_last_result;
}

/**
 * Returns the amount of affected rows for a query.
 *
 * @param mixed $result
 *
 * @return int
 *
 */
function smf_db_affected_rows($result = null)
{
	global $db_last_result, $db_replace_result;

	if ($db_replace_result)
		return $db_replace_result;
	elseif ($result === null && !$db_last_result)
		return 0;

	return pg_affected_rows($result === null ? $db_last_result : $result);
}

/**
 * Gets the ID of the most recently inserted row.
 *
 * @param string $table The table (only used for Postgres)
 * @param string $field = null The specific field (not used here)
 * @param resource $connection = null The connection (if null, $db_connection is used) (not used here)
 * @return int The ID of the most recently inserted row
 */
function smf_db_insert_id($table, $field = null, $connection = null)
{
	global $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	// Try get the last ID for the auto increment field.
	$request = $smcFunc['db_query']('', 'SELECT CURRVAL(\'' . $table . '_seq\') AS insertID',
		array(
		)
	);
	if (!$request)
		return false;
	list ($lastID) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $lastID;
}

/**
 * Do a transaction.
 *
 * @param string $type The step to perform (i.e. 'begin', 'commit', 'rollback')
 * @param resource $connection The connection to use (if null, $db_connection is used)
 * @return bool True if successful, false otherwise
 */
function smf_db_transaction($type = 'commit', $connection = null)
{
	global $db_connection;

	// Decide which connection to use
	$connection = $connection === null ? $db_connection : $connection;

	if ($type == 'begin')
		return @pg_query($connection, 'BEGIN');
	elseif ($type == 'rollback')
		return @pg_query($connection, 'ROLLBACK');
	elseif ($type == 'commit')
		return @pg_query($connection, 'COMMIT');

	return false;
}

/**
 * Database error!
 * Backtrace, log, try to fix.
 *
 * @param string $db_string The DB string
 * @param resource $connection The connection to use (if null, $db_connection is used)
 */
function smf_db_error($db_string, $connection = null)
{
	global $txt, $context, $modSettings;
	global $db_connection;
	global $db_show_debug;

	// We'll try recovering the file and line number the original db query was called from.
	list ($file, $line) = smf_db_error_backtrace('', '', 'return', __FILE__, __LINE__);

	// Decide which connection to use
	$connection = $connection === null ? $db_connection : $connection;

	// This is the error message...
	$query_error = @pg_last_error($connection);

	// Log the error.
	if (function_exists('log_error'))
		log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" . $db_string : ''), 'database', $file, $line);

	// Nothing's defined yet... just die with it.
	if (empty($context) || empty($txt))
		die($query_error);

	// Show an error message, if possible.
	$context['error_title'] = $txt['database_error'];
	if (allowedTo('admin_forum'))
		$context['error_message'] = nl2br($query_error) . '<br>' . $txt['file'] . ': ' . $file . '<br>' . $txt['line'] . ': ' . $line;
	else
		$context['error_message'] = $txt['try_again'];

	if (allowedTo('admin_forum') && isset($db_show_debug) && $db_show_debug === true)
	{
		$context['error_message'] .= '<br><br>' . nl2br($db_string);
	}

	// It's already been logged... don't log it again.
	fatal_error($context['error_message'], false);
}

/**
 * A PostgreSQL specific function for tracking the current row...
 *
 * @param resource $request A PostgreSQL result resource
 * @param bool|int $counter The row number in the result to fetch (false to fetch the next one)
 * @return array The contents of the row that was fetched
 */
function smf_db_fetch_row($request, $counter = false)
{
	global $db_row_count;

	if ($counter !== false)
		return pg_fetch_row($request, $counter);

	// Reset the row counter...
	if (!isset($db_row_count[(int) $request]))
		$db_row_count[(int) $request] = 0;

	// Return the right row.
	return @pg_fetch_row($request, $db_row_count[(int) $request]++);
}

/**
 * Get an associative array
 *
 * @param resource $request A PostgreSQL result resource
 * @param int|bool $counter The row to get. If false, returns the next row.
 * @return array An associative array of row contents
 */
function smf_db_fetch_assoc($request, $counter = false)
{
	global $db_row_count;

	if ($counter !== false)
		return pg_fetch_assoc($request, $counter);

	// Reset the row counter...
	if (!isset($db_row_count[(int) $request]))
		$db_row_count[(int) $request] = 0;

	// Return the right row.
	return @pg_fetch_assoc($request, $db_row_count[(int) $request]++);
}

/**
 * Reset the pointer...
 *
 * @param resource $request A PostgreSQL result resource
 * @param int $counter The counter
 * @return bool Always returns true
 */
function smf_db_data_seek($request, $counter)
{
	global $db_row_count;

	$db_row_count[(int) $request] = $counter;

	return true;
}

/**
 * Inserts data into a table
 *
 * @param string $method The insert method - can be 'replace', 'ignore' or 'insert'
 * @param string $table The table we're inserting the data into
 * @param array $columns An array of the columns we're inserting the data into. Should contain 'column' => 'datatype' pairs
 * @param array $data The data to insert
 * @param array $keys The keys for the table
 * @param int returnmode 0 = nothing(default), 1 = last row id, 2 = all rows id as array; every mode runs only with method != 'ignore'
 * @param resource $connection The connection to use (if null, $db_connection is used)
 * @return mixed value of the first key, behavior based on returnmode. null if no data.
 */
function smf_db_insert($method = 'replace', $table, $columns, $data, $keys, $returnmode = 0, $connection = null)
{
	global $smcFunc, $db_connection, $db_prefix;

	$connection = $connection === null ? $db_connection : $connection;

	$replace = '';

	if (empty($data))
		return;

	if (!is_array($data[array_rand($data)]))
		$data = array($data);

	// Replace the prefix holder with the actual prefix.
	$table = str_replace('{db_prefix}', $db_prefix, $table);

	// Sanity check for replace is key part of the columns array
	if ($method == 'replace' && count(array_intersect_key($columns, array_flip($keys))) !== count($keys))
		smf_db_error_backtrace('Primary Key field missing in insert call',
			'Change the method of db insert to insert or add the pk field to the columns array', E_USER_ERROR, __FILE__, __LINE__);

	// PostgreSQL doesn't support replace: we implement a MySQL-compatible behavior instead
	if ($method == 'replace' || $method == 'ignore')
	{
		$key_str = '';
		$col_str = '';
		$replace_support = $smcFunc['db_native_replace']();

		$count = 0;
		$where = '';
		$count_pk = 0;

		If ($replace_support)
		{
			foreach ($columns as $columnName => $type)
			{
				//check pk fiel
				IF (in_array($columnName, $keys))
				{
					$key_str .= ($count_pk > 0 ? ',' : '');
					$key_str .= $columnName;
					$count_pk++;
				}
				elseif ($method == 'replace') //normal field
				{
					$col_str .= ($count > 0 ? ',' : '');
					$col_str .= $columnName . ' = EXCLUDED.' . $columnName;
					$count++;
				}
			}
			if ($method == 'replace')
				$replace = ' ON CONFLICT (' . $key_str . ') DO UPDATE SET ' . $col_str;
			else
				$replace = ' ON CONFLICT (' . $key_str . ') DO NOTHING';
		}
		elseif ($method == 'replace')
		{
			foreach ($columns as $columnName => $type)
			{
				// Are we restricting the length?
				if (strpos($type, 'string-') !== false)
					$actualType = sprintf($columnName . ' = SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $count);
				else
					$actualType = sprintf($columnName . ' = {%1$s:%2$s}, ', $type, $count);

				// A key? That's what we were looking for.
				if (in_array($columnName, $keys))
					$where .= (empty($where) ? '' : ' AND ') . substr($actualType, 0, -2);
				$count++;
			}

			// Make it so.
			if (!empty($where) && !empty($data))
			{
				foreach ($data as $k => $entry)
				{
					$smcFunc['db_query']('', '
						DELETE FROM ' . $table .
						' WHERE ' . $where,
						$entry, $connection
					);
				}
			}
		}
	}

	$returning = '';
	$with_returning = false;
	// lets build the returning string, mysql allow only in normal mode
	if (!empty($keys) && (count($keys) > 0) && $returnmode > 0)
	{
		// we only take the first key
		$returning = ' RETURNING ' . $keys[0];
		$with_returning = true;
	}

	if (!empty($data))
	{
		// Create the mold for a single row insert.
		$insertData = '(';
		foreach ($columns as $columnName => $type)
		{
			// Are we restricting the length?
			if (strpos($type, 'string-') !== false)
				$insertData .= sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
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

		// Do the insert.
		$request = $smcFunc['db_query']('', '
			INSERT INTO ' . $table . '("' . implode('", "', $indexed_columns) . '")
			VALUES
				' . implode(',
				', $insertRows) . $replace . $returning,
			array(
				'security_override' => true,
				'db_error_skip' => $method == 'ignore' || $table === $db_prefix . 'log_errors',
			),
			$connection
		);

		if ($with_returning && $request !== false)
		{
			if ($returnmode === 2)
				$return_var = array();

			while (($row = $smcFunc['db_fetch_row']($request)) && $with_returning)
			{
				if (is_numeric($row[0])) // try to emulate mysql limitation
				{
					if ($returnmode === 1)
						$return_var = $row[0];
					elseif ($returnmode === 2)
						$return_var[] = $row[0];
				}
				else
				{
					$with_returning = false;
					trigger_error('trying to returning ID Field which is not a Int field', E_USER_ERROR);
				}
			}
		}
	}

	if ($with_returning && !empty($return_var))
		return $return_var;
}

/**
 * Dummy function really. Doesn't do anything on PostgreSQL.
 *
 * @param string $db_name The database name
 * @param resource $db_connection The database connection
 * @return true Always returns true
 */
function smf_db_select_db($db_name, $db_connection)
{
	return true;
}

/**
 * Get the current version.
 *
 * @return string The client version
 */
function smf_db_version()
{
	$version = pg_version();

	return $version['client'];
}

/**
 * This function tries to work out additional error information from a back trace.
 *
 * @param string $error_message The error message
 * @param string $log_message The message to log
 * @param string|bool $error_type What type of error this is
 * @param string $file The file the error occurred in
 * @param int $line What line of $file the code which generated the error is on
 * @return void|array Returns an array with the file and line if $error_type is 'return'
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
			$log_message .= '<br>Function: ' . $step['function'];
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
 * Escape the LIKE wildcards so that they match the character and not the wildcard.
 *
 * @param string $string The string to escape
 * @param bool $translate_human_wildcards If true, turns human readable wildcards into SQL wildcards.
 * @return string The escaped string
 */
function smf_db_escape_wildcard_string($string, $translate_human_wildcards = false)
{
	$replacements = array(
		'%' => '\%',
		'_' => '\_',
		'\\' => '\\\\',
	);

	if ($translate_human_wildcards)
		$replacements += array(
			'*' => '%',
		);

	return strtr($string, $replacements);
}

/**
 * Fetches all rows from a result as an array
 *
 * @param resource $request A PostgreSQL result resource
 * @return array An array that contains all rows (records) in the result resource
 */
function smf_db_fetch_all($request)
{
	// Return the right row.
	return @pg_fetch_all($request);
}

/**
 * Function to save errors in database in a safe way
 *
 * @param array with keys in this order id_member, log_time, ip, url, message, session, error_type, file, line
 * @return void
 */
function smf_db_error_insert($error_array)
{
	global $db_prefix, $db_connection;
	static $pg_error_data_prep;

	// without database we can't do anything
	if (empty($db_connection))
		return;

	if (empty($pg_error_data_prep))
		$pg_error_data_prep = pg_prepare($db_connection, 'smf_log_errors',
			'INSERT INTO ' . $db_prefix . 'log_errors
				(id_member, log_time, ip, url, message, session, error_type, file, line, backtrace)
			VALUES( $1, $2, $3, $4, $5, $6, $7, $8,	$9, $10)'
		);

	pg_execute($db_connection, 'smf_log_errors', $error_array);
}

/**
 * Function which constructs an optimize custom order string
 * as an improved alternative to find_in_set()
 *
 * @param string $field name
 * @param array $array_values Field values sequenced in array via order priority. Must cast to int.
 * @param boolean $desc default false
 * @return string case field when ... then ... end
 */
function smf_db_custom_order($field, $array_values, $desc = false)
{
	$return = 'CASE ' . $field . ' ';
	$count = count($array_values);
	$then = ($desc ? ' THEN -' : ' THEN ');

	for ($i = 0; $i < $count; $i++)
		$return .= 'WHEN ' . (int) $array_values[$i] . $then . $i . ' ';

	$return .= 'END';
	return $return;
}

/**
 * Function which return the information if the database supports native replace inserts
 *
 * @return boolean true or false
 */
function smf_db_native_replace()
{
	global $smcFunc;
	static $pg_version;
	static $replace_support;

	if (empty($pg_version))
	{
		db_extend();
		//pg 9.5 got replace support
		$pg_version = $smcFunc['db_get_version']();
		// if we got a Beta Version
		if (stripos($pg_version, 'beta') !== false)
			$pg_version = substr($pg_version, 0, stripos($pg_version, 'beta')) . '.0';
		// or RC
		if (stripos($pg_version, 'rc') !== false)
			$pg_version = substr($pg_version, 0, stripos($pg_version, 'rc')) . '.0';

		$replace_support = (version_compare($pg_version, '9.5.0', '>=') ? true : false);
	}

	return $replace_support;
}

/**
 * Function which return the information if the database supports cte with recursive
 *
 * @return boolean true or false
 */
function smf_db_cte_support()
{
	return true;
}

/**
 * Function which return the escaped string
 *
 * @param string the unescaped text
 * @param resource $connection = null The connection to use (null to use $db_connection)
 * @return string escaped string
 */
function smf_db_escape_string($string, $connection = null)
{
	global $db_connection;

	return pg_escape_string($connection === null ? $db_connection : $connection, $string);
}

?>