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

/*	This file has all the main functions in it that relate to the database.

	smf_db_initiate() maps the implementations in this file (smf_db_function_name)
	to the $smcFunc['db_function_name'] variable.

*/

// Initialize the database settings
function smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, &$db_prefix, $db_options = array())
{
	global $smcFunc, $mysql_set_mode;

	// Map some database specific functions, only do this once.
	if (!isset($smcFunc['db_fetch_assoc']) || $smcFunc['db_fetch_assoc'] != 'postg_fetch_assoc')
		$smcFunc += array(
			'db_query' => 'smf_db_query',
			'db_quote' => 'smf_db_quote',
			'db_insert' => 'smf_db_insert',
			'db_insert_id' => 'smf_db_insert_id',
			'db_fetch_assoc' => 'smf_db_fetch_assoc',
			'db_fetch_row' => 'smf_db_fetch_row',
			'db_free_result' => 'pg_free_result',
			'db_num_rows' => 'pg_num_rows',
			'db_data_seek' => 'smf_db_data_seek',
			'db_num_fields' => 'pg_num_fields',
			'db_escape_string' => 'pg_escape_string',
			'db_unescape_string' => 'smf_db_unescape_string',
			'db_server_info' => 'smf_db_version',
			'db_affected_rows' => 'smf_db_affected_rows',
			'db_transaction' => 'smf_db_transaction',
			'db_error' => 'pg_last_error',
			'db_select_db' => 'smf_db_select_db',
			'db_title' => 'PostgreSQL',
			'db_sybase' => true,
			'db_case_sensitive' => true,
			'db_escape_wildcard_string' => 'smf_db_escape_wildcard_string',
		);

	if (!empty($db_options['persist']))
		$connection = @pg_pconnect('host=' . $db_server . ' dbname=' . $db_name . ' user=\'' . $db_user . '\' password=\'' . $db_passwd . '\'');
	else
		$connection = @pg_connect( 'host=' . $db_server . ' dbname=' . $db_name . ' user=\'' . $db_user . '\' password=\'' . $db_passwd . '\'');

	// Something's wrong, show an error if its fatal (which we assume it is)
	if (!$connection)
	{
		if (!empty($db_options['non_fatal']))
		{
			return null;
		}
		else
		{
			db_fatal_error();
		}
	}

	return $connection;
}

// Extend the database functionality.
function db_extend ($type = 'extra')
{
	global $sourcedir, $db_type;

	require_once($sourcedir . '/Db' . strtoupper($type[0]) . substr($type, 1) . '-' . $db_type . '.php');
	$initFunc = 'db_' . $type . '_init';
	$initFunc();
}

// Do nothing on postgreSQL
function db_fix_prefix (&$db_prefix, $db_name)
{
	return;
}

function smf_db_replacement__callback($matches)
{
	global $db_callback, $user_info, $db_prefix;

	list ($values, $connection) = $db_callback;

	if ($matches[1] === 'db_prefix')
		return $db_prefix;

	if ($matches[1] === 'query_see_board')
		return $user_info['query_see_board'];

	if ($matches[1] === 'query_wanna_see_board')
		return $user_info['query_wanna_see_board'];

	if (!isset($matches[2]))
		smf_db_error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

	if (!isset($values[$matches[2]]))
		smf_db_error_backtrace('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2]), '', E_USER_ERROR, __FILE__, __LINE__);

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

// Just like the db_query, escape and quote a string, but not executing the query.
function smf_db_quote($db_string, $db_values, $connection = null)
{
	global $db_callback, $db_connection;

	// Only bother if there's something to replace.
	if (strpos($db_string, '{') !== false)
	{
		// This is needed by the callback function.
		$db_callback = array($db_values, $connection == null ? $db_connection : $connection);

		// Do the quoting and escaping
		$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'smf_db_replacement__callback', $db_string);

		// Clear this global variable.
		$db_callback = array();
	}

	return $db_string;
}

// Do a query.  Takes care of errors too.
function smf_db_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $db_cache, $db_count, $db_connection, $db_show_debug, $time_start;
	global $db_unbuffered, $db_callback, $db_last_result, $db_replace_result, $modSettings;

	// Decide which connection to use.
	$connection = $connection == null ? $db_connection : $connection;

	// Special queries that need processing.
	$replacements = array(
		'alter_table_boards' => array(
			'~(.+)~' => '',
		),
		'alter_table_icons' => array(
			'~(.+)~' => '',
		),
		'alter_table_smileys' => array(
			'~(.+)~' => '',
		),
		'alter_table_spiders' => array(
			'~(.+)~' => '',
		),
		'ban_suggest_error_ips' => array(
			'~RLIKE~' => '~',
			'~\\.~' => '\.',
		),
		'ban_suggest_message_ips' => array(
			'~RLIKE~' => '~',
			'~\\.~' => '\.',
		),
		'consolidate_spider_stats' => array(
			'~MONTH\(log_time\), DAYOFMONTH\(log_time\)~' => 'MONTH(CAST(CAST(log_time AS abstime) AS timestamp)), DAYOFMONTH(CAST(CAST(log_time AS abstime) AS timestamp))',
		),
		'delete_subscription' => array(
			'~LIMIT 1~' => '',
		),
		'display_get_post_poster' => array(
			'~GROUP BY id_msg\s+HAVING~' => 'AND',
		),
		'attach_download_increase' => array(
			'~LOW_PRIORITY~' => '',
		),
		'boardindex_fetch_boards' => array(
			'~IFNULL\(lb.id_msg, 0\) >= b.id_msg_updated~' => 'CASE WHEN IFNULL(lb.id_msg, 0) >= b.id_msg_updated THEN 1 ELSE 0 END',
			'~(.)$~' => '$1 ORDER BY b.board_order',
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
		'messageindex_fetch_boards' => array(
			'~(.)$~' => '$1 ORDER BY b.board_order',
		),
		'select_message_icons' => array(
			'~(.)$~' => '$1 ORDER BY icon_order',
		),
		'set_character_set' => array(
			'~SET\\s+NAMES\\s([a-zA-Z0-9\\-_]+)~' => 'SET NAMES \'$1\'',
		),
		'pm_conversation_list' => array(
			'~ORDER\\s+BY\\s+\\{raw:sort\\}~' => 'ORDER BY ' . (isset($db_values['sort']) ? ($db_values['sort'] === 'pm.id_pm' ? 'MAX(pm.id_pm)' : $db_values['sort']) : ''),
		),
		'top_topic_starters' => array(
			'~ORDER BY FIND_IN_SET\(id_member,(.+?)\)~' => 'ORDER BY STRPOS(\',\' || $1 || \',\', \',\' || id_member|| \',\')',
		),
		'order_by_board_order' => array(
			'~(.)$~' => '$1 ORDER BY b.board_order',
		),
		'spider_check' => array(
			'~(.)$~' => '$1 ORDER BY LENGTH(user_agent) DESC',
		),
		'unread_replies' => array(
			'~SELECT\\s+DISTINCT\\s+t.id_topic~' => 'SELECT t.id_topic, {raw:sort}',
		),
		'profile_board_stats' => array(
			'~COUNT\(\*\) \/ MAX\(b.num_posts\)~' => 'CAST(COUNT(*) AS DECIMAL) / CAST(b.num_posts AS DECIMAL)',
		),
		'set_smiley_order' => array(
			'~(.+)~' => '',
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

	// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
	if (empty($modSettings['disableQueryCheck']))
	{
		$clean = '';
		$old_pos = 0;
		$pos = -1;
		while (true)
		{
			$pos = strpos($db_string, '\'', $pos + 1);
			if ($pos === false)
				break;
			$clean .= substr($db_string, $old_pos, $pos - $old_pos);

			while (true)
			{
				$pos1 = strpos($db_string, '\'', $pos + 1);
				$pos2 = strpos($db_string, '\\', $pos + 1);
				if ($pos1 === false)
					break;
				elseif ($pos2 == false || $pos2 > $pos1)
				{
					$pos = $pos1;
					break;
				}

				$pos = $pos2 + 1;
			}
			$clean .= ' %s ';

			$old_pos = $pos + 1;
		}
		$clean .= substr($db_string, $old_pos);
		$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

		// We don't use UNION in SMF, at least so far.  But it's useful for injections.
		if (strpos($clean, 'union') !== false && preg_match('~(^|[^a-z])union($|[^[a-z])~s', $clean) != 0)
			$fail = true;
		// Comments?  We don't use comments in our queries, we leave 'em outside!
		elseif (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
			$fail = true;
		// Trying to change passwords, slow us down, or something?
		elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
			$fail = true;
		elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
			$fail = true;
		// Sub selects?  We don't use those either.
		elseif (preg_match('~\([^)]*?select~s', $clean) != 0)
			$fail = true;

		if (!empty($fail) && function_exists('log_error'))
			smf_db_error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
	}

	$db_last_result = @pg_query($connection, $db_string);

	if ($db_last_result === false && empty($db_values['db_error_skip']))
		$db_last_result = smf_db_error($db_string, $connection);

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$db_cache[$db_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));

	return $db_last_result;
}

function smf_db_affected_rows($result = null)
{
	global $db_last_result, $db_replace_result;

	if ($db_replace_result)
		return $db_replace_result;
	elseif ($result == null && !$db_last_result)
		return 0;

	return pg_affected_rows($result == null ? $db_last_result : $result);
}

function smf_db_insert_id($table, $field = null, $connection = null)
{
	global $db_connection, $smcFunc, $db_prefix;

	$table = str_replace('{db_prefix}', $db_prefix, $table);

	if ($connection === false)
		$connection = $db_connection;

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

// Do a transaction.
function smf_db_transaction($type = 'commit', $connection = null)
{
	global $db_connection;

	// Decide which connection to use
	$connection = $connection == null ? $db_connection : $connection;

	if ($type == 'begin')
		return @pg_query($connection, 'BEGIN');
	elseif ($type == 'rollback')
		return @pg_query($connection, 'ROLLBACK');
	elseif ($type == 'commit')
		return @pg_query($connection, 'COMMIT');

	return false;
}

// Database error!
function smf_db_error($db_string, $connection = null)
{
	global $txt, $context, $sourcedir, $webmaster_email, $modSettings;
	global $forum_version, $db_connection, $db_last_error, $db_persist;
	global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;
	global $smcFunc;

	// We'll try recovering the file and line number the original db query was called from.
	list ($file, $line) = smf_db_error_backtrace('', '', 'return', __FILE__, __LINE__);

	// Decide which connection to use
	$connection = $connection == null ? $db_connection : $connection;

	// This is the error message...
	$query_error = @pg_last_error($connection);

	// Log the error.
	if (function_exists('log_error'))
		log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" .$db_string : ''), 'database', $file, $line);

	// Nothing's defined yet... just die with it.
	if (empty($context) || empty($txt))
		die($query_error);

	// Show an error message, if possible.
	$context['error_title'] = $txt['database_error'];
	if (allowedTo('admin_forum'))
		$context['error_message'] = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line;
	else
		$context['error_message'] = $txt['try_again'];

	// A database error is often the sign of a database in need of updgrade.  Check forum versions, and if not identical suggest an upgrade... (not for Demo/CVS versions!)
	if (allowedTo('admin_forum') && !empty($forum_version) && $forum_version != 'SMF ' . @$modSettings['smfVersion'] && strpos($forum_version, 'Demo') === false && strpos($forum_version, 'CVS') === false)
		$context['error_message'] .= '<br /><br />' . sprintf($txt['database_error_versions'], $forum_version, $modSettings['smfVersion']);

	if (allowedTo('admin_forum') && isset($db_show_debug) && $db_show_debug === true)
	{
		$context['error_message'] .= '<br /><br />' . nl2br($db_string);
	}

	// It's already been logged... don't log it again.
	fatal_error($context['error_message'], false);
}

// A PostgreSQL specific function for tracking the current row...
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

// Get an associative array
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

// Reset the pointer...
function smf_db_data_seek($request, $counter)
{
	global $db_row_count;

	$db_row_count[(int) $request] = $counter;

	return true;
}

// Unescape an escaped string!
function smf_db_unescape_string($string)
{
	return strtr($string, array('\'\'' => '\''));
}

// For inserting data in a special way...
function smf_db_insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
{
	global $db_replace_result, $db_in_transact, $smcFunc, $db_connection, $db_prefix;

	$connection = $connection === null ? $db_connection : $connection;

	if (empty($data))
		return;

	if (!is_array($data[array_rand($data)]))
		$data = array($data);

	// Replace the prefix holder with the actual prefix.
	$table = str_replace('{db_prefix}', $db_prefix, $table);

	$priv_trans = false;
	if ((count($data) > 1 || $method == 'replace') && !$db_in_transact && !$disable_trans)
	{
		$smcFunc['db_transaction']('begin', $connection);
		$priv_trans = true;
	}

	// PostgreSQL doesn't support replace: we implement a MySQL-compatible behavior instead
	if ($method == 'replace')
	{
		$count = 0;
		$where = '';
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

		foreach ($insertRows as $entry)
			// Do the insert.
			$smcFunc['db_query']('', '
				INSERT INTO ' . $table . '("' . implode('", "', $indexed_columns) . '")
				VALUES
					' . $entry,
				array(
					'security_override' => true,
					'db_error_skip' => $method == 'ignore' || $table === $db_prefix . 'log_errors',
				),
				$connection
			);
	}

	if ($priv_trans)
		$smcFunc['db_transaction']('commit', $connection);
}

// Dummy function really.
function smf_db_select_db($db_name, $db_connection)
{
	return true;
}

// Get the current version.
function smf_db_version()
{
	$version = pg_version();

	return $version['client'];
}

// This function tries to work out additional error information from a back trace.
function smf_db_error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
{
	if (empty($log_message))
		$log_message = $error_message;

	if (function_exists('debug_backtrace'))
	{
		foreach (debug_backtrace() as $step)
		{
			// Found it?
			if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), array('smf_db_', 'preg_re', 'db_erro', 'call_us')) && substr($step['function'], 0, 2) != '__')
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

// Escape the LIKE wildcards so that they match the character and not the wildcard.
// The optional second parameter turns human readable wildcards into SQL wildcards.
function smf_db_escape_wildcard_string($string, $translate_human_wildcards=false)
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

?>