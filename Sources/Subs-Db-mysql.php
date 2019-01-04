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
 *  Maps the implementations in this file (smf_db_function_name)
 *  to the $smcFunc['db_function_name'] variable.
 *
 * @param string $db_server The database server
 * @param string $db_name The name of the database
 * @param string $db_user The database username
 * @param string $db_passwd The database password
 * @param string $db_prefix The table prefix
 * @param array $db_options An array of database options
 * @return null|resource Returns null on failure if $db_options['non_fatal'] is true or a MySQL connection resource handle if the connection was successful.
 */
function smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
{
	global $smcFunc;

	// Map some database specific functions, only do this once.
	if (!isset($smcFunc['db_fetch_assoc']))
		$smcFunc += array(
			'db_query'                  => 'smf_db_query',
			'db_quote'                  => 'smf_db_quote',
			'db_fetch_assoc'            => 'mysqli_fetch_assoc',
			'db_fetch_row'              => 'mysqli_fetch_row',
			'db_free_result'            => 'mysqli_free_result',
			'db_insert'                 => 'smf_db_insert',
			'db_insert_id'              => 'smf_db_insert_id',
			'db_num_rows'               => 'mysqli_num_rows',
			'db_data_seek'              => 'mysqli_data_seek',
			'db_num_fields'             => 'mysqli_num_fields',
			'db_escape_string'          => 'smf_db_escape_string',
			'db_unescape_string'        => 'stripslashes',
			'db_server_info'            => 'smf_db_get_server_info',
			'db_affected_rows'          => 'smf_db_affected_rows',
			'db_transaction'            => 'smf_db_transaction',
			'db_error'                  => 'mysqli_error',
			'db_select_db'              => 'smf_db_select',
			'db_title'                  => 'MySQLi',
			'db_sybase'                 => false,
			'db_case_sensitive'         => false,
			'db_escape_wildcard_string' => 'smf_db_escape_wildcard_string',
			'db_is_resource'            => 'smf_is_resource',
			'db_mb4'                    => false,
			'db_ping'                   => 'mysqli_ping',
			'db_fetch_all'              => 'smf_db_fetch_all',
			'db_error_insert'			=> 'smf_db_error_insert',
			'db_custom_order'			=> 'smf_db_custom_order',
			'db_native_replace'			=> 'smf_db_native_replace',
			'db_cte_support'			=> 'smf_db_cte_support',
		);

	if (!empty($db_options['persist']))
		$db_server = 'p:' . $db_server;

	// We are not going to make it very far without these.
	if (!function_exists('mysqli_init') || !function_exists('mysqli_real_connect'))
		display_db_error();

	$connection = mysqli_init();

	$flags = 2; // MYSQLI_CLIENT_FOUND_ROWS = 2

	$success = false;

	if ($connection)
	{
		if (!empty($db_options['port']))
			$success = mysqli_real_connect($connection, $db_server, $db_user, $db_passwd, null, $db_options['port'], null, $flags);
		else
			$success = mysqli_real_connect($connection, $db_server, $db_user, $db_passwd, null, 0, null, $flags);
	}

	// Something's wrong, show an error if its fatal (which we assume it is)
	if ($success === false)
	{
		if (!empty($db_options['non_fatal']))
			return null;
		else
			display_db_error();
	}

	// Select the database, unless told not to
	if (empty($db_options['dont_select_db']) && !@mysqli_select_db($connection, $db_name) && empty($db_options['non_fatal']))
		display_db_error();

	mysqli_query($connection, 'SET SESSION sql_mode = \'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\'');

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
	global $sourcedir;

	// we force the MySQL files as nothing syntactically changes with MySQLi
	require_once($sourcedir . '/Db' . strtoupper($type[0]) . substr($type, 1) . '-mysql.php');
	$initFunc = 'db_' . $type . '_init';
	$initFunc();
}

/**
 * Fix up the prefix so it doesn't require the database to be selected.
 *
 * @param string &$db_prefix The table prefix
 * @param string $db_name The database name
 */
function db_fix_prefix(&$db_prefix, $db_name)
{
	$db_prefix = is_numeric(substr($db_prefix, 0, 1)) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;
}

/**
 * Wrap mysqli_select_db so the connection does not need to be specified
 *
 * @param string &$database The database
 * @param object $connection The connection object (if null, $db_connection is used)
 * @return bool Whether the database was selected
 */
function smf_db_select($database, $connection = null)
{
	global $db_connection;
	return mysqli_select_db($connection === null ? $db_connection : $connection, $database);
}

/**
 * Wrap mysqli_get_server_info so the connection does not need to be specified
 *
 * @param object $connection The connection to use (if null, $db_connection is used)
 * @return string The server info
 */
function smf_db_get_server_info($connection = null)
{
	global $db_connection;
	return mysqli_get_server_info($connection === null ? $db_connection : $connection);
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
	if (!is_object($connection))
		display_db_error();

	if ($matches[1] === 'db_prefix')
		return $db_prefix;

	if (isset($user_info[$matches[1]]) && strpos($matches[1], 'query_') !== false)
		return $user_info[$matches[1]];

	if ($matches[1] === 'empty')
		return '\'\'';

	if (!isset($matches[2]))
		smf_db_error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

	if ($matches[1] === 'literal')
		return '\'' . mysqli_real_escape_string($connection, $matches[2]) . '\'';

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
			return sprintf('\'%1$s\'', mysqli_real_escape_string($connection, $replacement));
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
					$replacement[$key] = sprintf('\'%1$s\'', mysqli_real_escape_string($connection, $value));

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

		case 'time':
			if (preg_match('~^([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $time_matches) === 1)
				return sprintf('\'%02d:%02d:%02d\'', $time_matches[1], $time_matches[2], $time_matches[3]);
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Time expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		case 'datetime':
			if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d) ([0-1]?\d|2[0-3]):([0-5]\d):([0-5]\d)$~', $replacement, $datetime_matches) === 1)
				return 'str_to_date(' .
					sprintf('\'%04d-%02d-%02d %02d:%02d:%02d\'', $datetime_matches[1], $datetime_matches[2], $datetime_matches[3], $datetime_matches[4], $datetime_matches[5], $datetime_matches[6]) .
					',\'%Y-%m-%d %h:%i:%s\')';
			else
				smf_db_error_backtrace('Wrong value type sent to the database. Datetime expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

		case 'float':
			if (!is_numeric($replacement))
				smf_db_error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			return (string) (float) $replacement;
			break;

		case 'identifier':
			// Backticks inside identifiers are supported as of MySQL 4.1. We don't need them for SMF.
			return '`' . strtr($replacement, array('`' => '', '.' => '`.`')) . '`';
			break;

		case 'raw':
			return $replacement;
			break;

		case 'inet':
			if ($replacement == 'null' || $replacement == '')
				return 'null';
			if (!isValidIP($replacement))
				smf_db_error_backtrace('Wrong value type sent to the database. IPv4 or IPv6 expected.(' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			//we don't use the native support of mysql > 5.6.2
			return sprintf('unhex(\'%1$s\')', bin2hex(inet_pton($replacement)));

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
					$replacement[$key] = sprintf('unhex(\'%1$s\')', bin2hex(inet_pton($value)));
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
	global $db_unbuffered, $db_callback, $modSettings;

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

	// Decide which connection to use.
	$connection = $connection === null ? $db_connection : $connection;

	// One more query....
	$db_count = !isset($db_count) ? 1 : $db_count + 1;

	if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
		smf_db_error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);

	// Use "ORDER BY null" to prevent Mysql doing filesorts for Group By clauses without an Order By
	if (strpos($db_string, 'GROUP BY') !== false && strpos($db_string, 'ORDER BY') === false && preg_match('~^\s+SELECT~i', $db_string))
	{
		// Add before LIMIT
		if ($pos = strpos($db_string, 'LIMIT '))
			$db_string = substr($db_string, 0, $pos) . "\t\t\tORDER BY null\n" . substr($db_string, $pos, strlen($db_string));
		else
			// Append it.
			$db_string .= "\n\t\t\tORDER BY null";
	}

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
		$db_string_1 = str_replace('\\\'', '', $db_string);
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

	if (empty($db_unbuffered))
		$ret = @mysqli_query($connection, $db_string);
	else
		$ret = @mysqli_query($connection, $db_string, MYSQLI_USE_RESULT);

	if ($ret === false && empty($db_values['db_error_skip']))
		$ret = smf_db_error($db_string, $connection);

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$db_cache[$db_count]['t'] = microtime(true) - $st;

	return $ret;
}

/**
 * affected_rows
 *
 * @param resource $connection A connection to use (if null, $db_connection is used)
 * @return int The number of rows affected by the last query
 */
function smf_db_affected_rows($connection = null)
{
	global $db_connection;

	return mysqli_affected_rows($connection === null ? $db_connection : $connection);
}

/**
 * Gets the ID of the most recently inserted row.
 *
 * @param string $table The table (only used for Postgres)
 * @param string $field = null The specific field (not used here)
 * @param resource $connection = null The connection (if null, $db_connection is used)
 * @return int The ID of the most recently inserted row
 */
function smf_db_insert_id($table, $field = null, $connection = null)
{
	global $db_connection;

	// MySQL doesn't need the table or field information.
	return mysqli_insert_id($connection === null ? $db_connection : $connection);
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
		return @mysqli_query($connection, 'BEGIN');
	elseif ($type == 'rollback')
		return @mysqli_query($connection, 'ROLLBACK');
	elseif ($type == 'commit')
		return @mysqli_query($connection, 'COMMIT');

	return false;
}

/**
 * Database error!
 * Backtrace, log, try to fix.
 *
 * @param string $db_string The DB string
 * @param object $connection The connection to use (if null, $db_connection is used)
 */
function smf_db_error($db_string, $connection = null)
{
	global $txt, $context, $sourcedir, $webmaster_email, $modSettings;
	global $db_connection, $db_last_error, $db_persist;
	global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;
	global $smcFunc;

	// Get the file and line numbers.
	list ($file, $line) = smf_db_error_backtrace('', '', 'return', __FILE__, __LINE__);

	// Decide which connection to use
	$connection = $connection === null ? $db_connection : $connection;

	// This is the error message...
	$query_error = mysqli_error($connection);
	$query_errno = mysqli_errno($connection);

	// Error numbers:
	//    1016: Can't open file '....MYI'
	//    1030: Got error ??? from table handler.
	//    1034: Incorrect key file for table.
	//    1035: Old key file for table.
	//    1205: Lock wait timeout exceeded.
	//    1213: Deadlock found.

	// Log the error.
	if ($query_errno != 1213 && $query_errno != 1205 && function_exists('log_error'))
		log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n$db_string" : ''), 'database', $file, $line);

	// Database error auto fixing ;).
	if (function_exists('cache_get_data') && (!isset($modSettings['autoFixDatabase']) || $modSettings['autoFixDatabase'] == '1'))
	{
		// Force caching on, just for the error checking.
		$old_cache = @$modSettings['cache_enable'];
		$modSettings['cache_enable'] = '1';

		if (($temp = cache_get_data('db_last_error', 600)) !== null)
			$db_last_error = max(@$db_last_error, $temp);

		if (@$db_last_error < time() - 3600 * 24 * 3)
		{
			// We know there's a problem... but what?  Try to auto detect.
			if ($query_errno == 1030 && strpos($query_error, ' 127 ') !== false)
			{
				preg_match_all('~(?:[\n\r]|^)[^\']+?(?:FROM|JOIN|UPDATE|TABLE) ((?:[^\n\r(]+?(?:, )?)*)~s', $db_string, $matches);

				$fix_tables = array();
				foreach ($matches[1] as $tables)
				{
					$tables = array_unique(explode(',', $tables));
					foreach ($tables as $table)
					{
						// Now, it's still theoretically possible this could be an injection.  So backtick it!
						if (trim($table) != '')
							$fix_tables[] = '`' . strtr(trim($table), array('`' => '')) . '`';
					}
				}

				$fix_tables = array_unique($fix_tables);
			}
			// Table crashed.  Let's try to fix it.
			elseif ($query_errno == 1016)
			{
				if (preg_match('~\'([^\.\']+)~', $query_error, $match) != 0)
					$fix_tables = array('`' . $match[1] . '`');
			}
			// Indexes crashed.  Should be easy to fix!
			elseif ($query_errno == 1034 || $query_errno == 1035)
			{
				preg_match('~\'([^\']+?)\'~', $query_error, $match);
				$fix_tables = array('`' . $match[1] . '`');
			}
		}

		// Check for errors like 145... only fix it once every three days, and send an email. (can't use empty because it might not be set yet...)
		if (!empty($fix_tables))
		{
			// Subs-Admin.php for updateSettingsFile(), Subs-Post.php for sendmail().
			require_once($sourcedir . '/Subs-Admin.php');
			require_once($sourcedir . '/Subs-Post.php');

			// Make a note of the REPAIR...
			cache_put_data('db_last_error', time(), 600);
			if (($temp = cache_get_data('db_last_error', 600)) === null)
				updateSettingsFile(array('db_last_error' => time()));

			// Attempt to find and repair the broken table.
			foreach ($fix_tables as $table)
				$smcFunc['db_query']('', "
					REPAIR TABLE $table", false, false);

			// And send off an email!
			sendmail($webmaster_email, $txt['database_error'], $txt['tried_to_repair'], null, 'dberror');

			$modSettings['cache_enable'] = $old_cache;

			// Try the query again...?
			$ret = $smcFunc['db_query']('', $db_string, false, false);
			if ($ret !== false)
				return $ret;
		}
		else
			$modSettings['cache_enable'] = $old_cache;

		// Check for the "lost connection" or "deadlock found" errors - and try it just one more time.
		if (in_array($query_errno, array(1205, 1213)))
		{
			if ($db_connection)
			{
				// Try a deadlock more than once more.
				for ($n = 0; $n < 4; $n++)
				{
					$ret = $smcFunc['db_query']('', $db_string, false, false);

					$new_errno = mysqli_errno($db_connection);
					if ($ret !== false || in_array($new_errno, array(1205, 1213)))
						break;
				}

				// If it failed again, shucks to be you... we're not trying it over and over.
				if ($ret !== false)
					return $ret;
			}
		}
		// Are they out of space, perhaps?
		elseif ($query_errno == 1030 && (strpos($query_error, ' -1 ') !== false || strpos($query_error, ' 28 ') !== false || strpos($query_error, ' 12 ') !== false))
		{
			if (!isset($txt))
				$query_error .= ' - check database storage space.';
			else
			{
				if (!isset($txt['mysql_error_space']))
					loadLanguage('Errors');

				$query_error .= !isset($txt['mysql_error_space']) ? ' - check database storage space.' : $txt['mysql_error_space'];
			}
		}
	}

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
 * Inserts data into a table
 *
 * @param string $method The insert method - can be 'replace', 'ignore' or 'insert'
 * @param string $table The table we're inserting the data into
 * @param array $columns An array of the columns we're inserting the data into. Should contain 'column' => 'datatype' pairs
 * @param array $data The data to insert
 * @param array $keys The keys for the table
 * @param int returnmode 0 = nothing(default), 1 = last row id, 2 = all rows id as array
 * @param object $connection The connection to use (if null, $db_connection is used)
 * @return mixed value of the first key, behavior based on returnmode. null if no data.
 */
function smf_db_insert($method = 'replace', $table, $columns, $data, $keys, $returnmode = 0, $connection = null)
{
	global $smcFunc, $db_connection, $db_prefix;

	$connection = $connection === null ? $db_connection : $connection;

	$return_var = null;

	// With nothing to insert, simply return.
	if (empty($data))
		return;

	// Replace the prefix holder with the actual prefix.
	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$with_returning = false;

	if (!empty($keys) && (count($keys) > 0) && $returnmode > 0)
	{
		$with_returning = true;
		if ($returnmode == 2)
			$return_var = array();
	}

	// Inserting data as a single row can be done as a single array.
	if (!is_array($data[array_rand($data)]))
		$data = array($data);

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

	// Determine the method of insertion.
	$queryTitle = $method == 'replace' ? 'REPLACE' : ($method == 'ignore' ? 'INSERT IGNORE' : 'INSERT');

	// Sanity check for replace is key part of the columns array
	if ($method == 'replace' && count(array_intersect_key($columns, array_flip($keys))) !== count($keys))
		smf_db_error_backtrace('Primary Key field missing in insert call',
			'Change the method of db insert to insert or add the pk field to the columns array', E_USER_ERROR, __FILE__, __LINE__);

	if (!$with_returning || $method != 'ignore')
	{
		// Do the insert.
		$smcFunc['db_query']('', '
			' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
			VALUES
				' . implode(',
				', $insertRows),
			array(
				'security_override' => true,
				'db_error_skip' => $table === $db_prefix . 'log_errors',
			),
			$connection
		);
	}
	// Special way for ignore method with returning
	else
	{
		$count = count($insertRows);
		$ai = 0;
		for ($i = 0; $i < $count; $i++)
		{
			$old_id = $smcFunc['db_insert_id']();

			$smcFunc['db_query']('', '
				' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
				VALUES
					' . $insertRows[$i],
				array(
					'security_override' => true,
					'db_error_skip' => $table === $db_prefix . 'log_errors',
				),
				$connection
			);
			$new_id = $smcFunc['db_insert_id']();

			// the inserted value was new
			if ($old_id != $new_id)
			{
				$ai = $new_id;
			}
			// the inserted value already exists we need to find the pk
			else
			{
				$where_string = '';
				$count2 = count($indexed_columns);
				for ($x = 0; $x < $count2; $x++)
				{
					$where_string .= key($indexed_columns[$x]) . ' = ' . $insertRows[$i][$x];
					if (($x + 1) < $count2)
						$where_string .= ' AND ';
				}

				$request = $smcFunc['db_query']('', '
					SELECT `' . $keys[0] . '` FROM ' . $table . '
					WHERE ' . $where_string . ' LIMIT 1',
					array()
				);

				if ($request !== false && $smcFunc['db_num_rows']($request) == 1)
				{
					$row = $smcFunc['db_fetch_assoc']($request);
					$ai = $row[$keys[0]];
				}
			}

			if ($returnmode == 1)
				$return_var = $ai;
			elseif ($returnmode == 2)
				$return_var[] = $ai;
		}
	}

	if ($with_returning)
	{
		if ($returnmode == 1 && empty($return_var))
			$return_var = smf_db_insert_id($table, $keys[0]) + count($insertRows) - 1;
		elseif ($returnmode == 2 && empty($return_var))
		{
			$return_var = array();
			$count = count($insertRows);
			$start = smf_db_insert_id($table, $keys[0]);
			for ($i = 0; $i < $count; $i++)
				$return_var[] = $start + $i;
		}
		return $return_var;
	}
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
		fatal_error($error_message, false);

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
 * Validates whether the resource is a valid mysqli instance.
 * Mysqli uses objects rather than resource. https://bugs.php.net/bug.php?id=42797
 *
 * @param mixed $result The string to test
 * @return bool True if it is, false otherwise
 */
function smf_is_resource($result)
{
	if ($result instanceof mysqli_result)
		return true;

	return false;
}

/**
 * Fetches all rows from a result as an array
 *
 * @param resource $request A MySQL result resource
 * @return array An array that contains all rows (records) in the result resource
 */
function smf_db_fetch_all($request)
{
	// Return the right row.
	return mysqli_fetch_all($request);
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
	static $mysql_error_data_prep;

	// without database we can't do anything
	if (empty($db_connection))
		return;

	if (empty($mysql_error_data_prep))
		$mysql_error_data_prep = mysqli_prepare($db_connection,
			'INSERT INTO ' . $db_prefix . 'log_errors
				(id_member, log_time, ip, url, message, session, error_type, file, line, backtrace)
			VALUES( ?, ?, unhex(?), ?, ?, ?, ?, ?, ?, ?)'
		);

	if (filter_var($error_array[2], FILTER_VALIDATE_IP) !== false)
		$error_array[2] = bin2hex(inet_pton($error_array[2]));
	else
		$error_array[2] = null;
	mysqli_stmt_bind_param($mysql_error_data_prep, 'iissssssis',
		$error_array[0], $error_array[1], $error_array[2], $error_array[3], $error_array[4], $error_array[5], $error_array[6],
		$error_array[7], $error_array[8], $error_array[9]);
	mysqli_stmt_execute($mysql_error_data_prep);
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
	return true;
}

/**
 * Function which return the information if the database supports cte with recursive
 *
 * @return boolean true or false
 */
function smf_db_cte_support()
{
	global $smcFunc;
	static $return;

	if (isset($return))
		return $return;

	db_extend('extra');

	$version = $smcFunc['db_get_version']();

	if (strpos(strtolower($version), 'mariadb') !== false)
		$return = version_compare($version, '10.2.2', '>=');
	else //mysql
		$return = version_compare($version, '8.0.1', '>=');

	return $return;
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

	return mysqli_real_escape_string($connection === null ? $db_connection : $connection, $string);
}

?>