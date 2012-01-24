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

/*
	This file has a single job - database backup.

	void DumpDatabase2()
		- writes all of the database to standard output.
		- uses gzip compression if compress is set in the URL/post data.
		- may possibly time out in some cases.
		- the data dumped depends on whether "struct" and "data" are passed.
		- requires an administrator and the session hash by post.
		- is called from ManageMaintenance.php.

*/

// Dumps the database to a file.
function DumpDatabase2()
{
	global $db_name, $scripturl, $context, $modSettings, $crlf, $smcFunc, $db_prefix;

	// Administrators only!
	if (!allowedTo('admin_forum'))
		fatal_lang_error('no_dump_database', 'critical');

	// You can't dump nothing!
	if (!isset($_REQUEST['struct']) && !isset($_REQUEST['data']))
		$_REQUEST['data'] = true;

	checkSession('post');

	// We will need this, badly!
	db_extend();

	// Attempt to stop from dying...
	@set_time_limit(600);
	if (@ini_get('memory_limit') < 256)
		@ini_set('memory_limit', '256M');

	// Start saving the output... (don't do it otherwise for memory reasons.)
	if (isset($_REQUEST['compress']) && function_exists('gzencode'))
	{
		// Make sure we're gzipping output, but then say we're not in the header ^_^.
		if (empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		// Try to clean any data already outputted.
		elseif (ob_get_length() != 0)
		{
			ob_end_clean();
			@ob_start('ob_gzhandler');
		}

		// Send faked headers so it will just save the compressed output as a gzip.
		header('Content-Type: application/x-gzip');
		header('Accept-Ranges: bytes');
		header('Content-Encoding: none');

		// Gecko browsers... don't like this. (Mozilla, Firefox, etc.)
		if (!$context['browser']['is_gecko'])
			header('Content-Transfer-Encoding: binary');

		// The file extension will include .gz...
		$extension = '.sql.gz';
	}
	else
	{
		// Get rid of the gzipping alreading being done.
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_end_clean();
		// If we can, clean anything already sent from the output buffer...
		elseif (function_exists('ob_clean') && ob_get_length() != 0)
			ob_clean();

		// Tell the client to save this file, even though it's text.
		header('Content-Type: ' . ($context['browser']['is_ie'] || $context['browser']['is_opera'] ? 'application/octetstream' : 'application/octet-stream'));
		header('Content-Encoding: none');

		// This time the extension should just be .sql.
		$extension = '.sql';
	}

	// This should turn off the session URL parser.
	$scripturl = '';

	// If this database is flat file and has a handler function pass it to that.
	if (!empty($smcFunc['db_get_backup']))
	{
		$smcFunc['db_get_backup']();
		exit;
	}

	// Send the proper headers to let them download this file.
	header('Content-Disposition: filename="' . $db_name . '-' . (empty($_REQUEST['struct']) ? 'data' : (empty($_REQUEST['data']) ? 'structure' : 'complete')) . '_' . strftime('%Y-%m-%d') . $extension . '"');
	header('Cache-Control: private');
	header('Connection: close');

	// This makes things simpler when using it so very very often.
	$crlf = "\r\n";

	// SQL Dump Header.
	echo
		'-- ==========================================================', $crlf,
		'--', $crlf,
		'-- Database dump of tables in `', $db_name, '`', $crlf,
		'-- ', timeformat(time(), false), $crlf,
		'--', $crlf,
		'-- ==========================================================', $crlf,
		$crlf;

	// Get all tables in the database....
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) != 0)
	{
		$db = strtr($match[1], array('`' => ''));
		$dbp = str_replace('_', '\_', $match[2]);
	}
	else
	{
		$db = false;
		$dbp = $db_prefix;
	}

	// Dump each table.
	$tables = $smcFunc['db_list_tables'](false, $db_prefix . '%');
	foreach ($tables as $tableName)
	{
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Are we dumping the structures?
		if (isset($_REQUEST['struct']))
		{
			echo
				$crlf,
				'--', $crlf,
				'-- Table structure for table `', $tableName, '`', $crlf,
				'--', $crlf,
				$crlf,
				$smcFunc['db_table_sql']($tableName), ';', $crlf;
		}

		// How about the data?
		if (!isset($_REQUEST['data']) || substr($tableName, -10) == 'log_errors')
			continue;

		// Are there any rows in this table?
		$get_rows = $smcFunc['db_insert_sql']($tableName);

		// No rows to get - skip it.
		if (empty($get_rows))
			continue;

		echo
			$crlf,
			'--', $crlf,
			'-- Dumping data in `', $tableName, '`', $crlf,
			'--', $crlf,
			$crlf,
			$get_rows,
			'-- --------------------------------------------------------', $crlf;
	}

	echo
		$crlf,
		'-- Done', $crlf;

	exit;
}

?>