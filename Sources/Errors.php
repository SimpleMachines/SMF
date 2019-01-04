<?php

/**
 * The purpose of this file is... errors. (hard to guess, I guess?)  It takes
 * care of logging, error messages, error handling, database errors, and
 * error log administration.
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
 * Log an error, if the error logging is enabled.
 * filename and line should be __FILE__ and __LINE__, respectively.
 * Example use:
 *  die(log_error($msg));
 *
 * @param string $error_message The message to log
 * @param string $error_type The type of error
 * @param string $file The name of the file where this error occurred
 * @param int $line The line where the error occurred
 * @return string The message that was logged
 */
function log_error($error_message, $error_type = 'general', $file = null, $line = null)
{
	global $modSettings, $sc, $user_info, $smcFunc, $scripturl, $last_error, $context, $db_show_debug;
	static $tried_hook = false;
	static $error_call = 0;

	$error_call++;

	// Collect a backtrace
	if (!isset($db_show_debug) || $db_show_debug === false)
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	else
		$backtrace = debug_backtrace();

	// are we in a loop?
	if ($error_call > 2)
	{
		var_dump($backtrace);
		die('Error loop.');
	}

	// Check if error logging is actually on.
	if (empty($modSettings['enableErrorLogging']))
		return $error_message;

	// Basically, htmlspecialchars it minus &. (for entities!)
	$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
	$error_message = strtr($error_message, array('&lt;br /&gt;' => '<br>', '&lt;br&gt;' => '<br>', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br>'));

	// Add a file and line to the error message?
	// Don't use the actual txt entries for file and line but instead use %1$s for file and %2$s for line
	if ($file == null)
		$file = '';
	else
		// Window style slashes don't play well, lets convert them to the unix style.
		$file = str_replace('\\', '/', $file);

	if ($line == null)
		$line = 0;
	else
		$line = (int) $line;

	// Just in case there's no id_member or IP set yet.
	if (empty($user_info['id']))
		$user_info['id'] = 0;
	if (empty($user_info['ip']))
		$user_info['ip'] = '';

	// Find the best query string we can...
	$query_string = empty($_SERVER['QUERY_STRING']) ? (empty($_SERVER['REQUEST_URL']) ? '' : str_replace($scripturl, '', $_SERVER['REQUEST_URL'])) : $_SERVER['QUERY_STRING'];

	// Don't log the session hash in the url twice, it's a waste.
	if (!empty($smcFunc['htmlspecialchars']))
		$query_string = $smcFunc['htmlspecialchars']((SMF == 'SSI' || SMF == 'BACKGROUND' ? '' : '?') . preg_replace(array('~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'), array(';sesc', ''), $query_string));

	// Just so we know what board error messages are from.
	if (isset($_POST['board']) && !isset($_GET['board']))
		$query_string .= ($query_string == '' ? 'board=' : ';board=') . $_POST['board'];

	// What types of categories do we have?
	$known_error_types = array(
		'general',
		'critical',
		'database',
		'undefined_vars',
		'user',
		'ban',
		'template',
		'debug',
		'cron',
		'paidsubs',
		'backup',
		'login',
	);

	// This prevents us from infinite looping if the hook or call produces an error.
	$other_error_types = array();
	if (empty($tried_hook))
	{
		$tried_hook = true;
		// Allow the hook to change the error_type and know about the error.
		call_integration_hook('integrate_error_types', array(&$other_error_types, &$error_type, $error_message, $file, $line));
		$known_error_types += $other_error_types;
	}
	// Make sure the category that was specified is a valid one
	$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

	// leave out the call to log_error
	array_splice($backtrace, 0, 1);
	$backtrace = !empty($smcFunc['json_encode']) ? $smcFunc['json_encode']($backtrace) : json_encode($backtrace);

	// Don't log the same error countless times, as we can get in a cycle of depression...
	$error_info = array($user_info['id'], time(), $user_info['ip'], $query_string, $error_message, (string) $sc, $error_type, $file, $line, $backtrace);
	if (empty($last_error) || $last_error != $error_info)
	{
		// Insert the error into the database.
		$smcFunc['db_error_insert']($error_info);
		$last_error = $error_info;

		// Get an error count, if necessary
		if (!isset($context['num_errors']))
		{
			$query = $smcFunc['db_query']('', '
				SELECT COUNT(id_error)
				FROM {db_prefix}log_errors',
				array()
			);

			list($context['num_errors']) = $smcFunc['db_fetch_row']($query);
			$smcFunc['db_free_result']($query);
		}
		else
			$context['num_errors']++;
	}

	// reset error call
	$error_call = 0;

	// Return the message to make things simpler.
	return $error_message;
}

/**
 * An irrecoverable error. This function stops execution and displays an error message.
 * It logs the error message if $log is specified.
 *
 * @param string $error The error message
 * @param string $log = 'general' What type of error to log this as (false to not log it))
 * @param int $status The HTTP status code associated with this error
 */
function fatal_error($error, $log = 'general', $status = 500)
{
	global $txt;

	// Send the appropriate HTTP status header - set this to 0 or false if you don't want to send one at all
	if (!empty($status))
		send_http_status($status);

	// We don't have $txt yet, but that's okay...
	if (empty($txt))
		die($error);

	log_error_online($error, false);
	setup_fatal_error_context($log ? log_error($error, $log) : $error);
}

/**
 * Shows a fatal error with a message stored in the language file.
 *
 * This function stops execution and displays an error message by key.
 *  - uses the string with the error_message_key key.
 *  - logs the error in the forum's default language while displaying the error
 *    message in the user's language.
 *  - uses Errors language file and applies the $sprintf information if specified.
 *  - the information is logged if log is specified.
 *
 * @param string $error The error message
 * @param string|false $log The type of error, or false to not log it
 * @param array $sprintf An array of data to be sprintf()'d into the specified message
 * @param int $status = false The HTTP status code associated with this error
 */
function fatal_lang_error($error, $log = 'general', $sprintf = array(), $status = 403)
{
	global $txt, $language, $user_info, $context;
	static $fatal_error_called = false;

	// Send the status header - set this to 0 or false if you don't want to send one at all
	if (!empty($status))
		send_http_status($status);

	// Try to load a theme if we don't have one.
	if (empty($context['theme_loaded']) && empty($fatal_error_called))
	{
		$fatal_error_called = true;
		loadTheme();
	}

	// If we have no theme stuff we can't have the language file...
	if (empty($context['theme_loaded']))
		die($error);

	$reload_lang_file = true;
	// Log the error in the forum's language, but don't waste the time if we aren't logging
	if ($log)
	{
		loadLanguage('Errors', $language);
		$reload_lang_file = $language != $user_info['language'];
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
		log_error($error_message, $log);
	}

	// Load the language file, only if it needs to be reloaded
	if ($reload_lang_file)
	{
		loadLanguage('Errors');
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
	}

	log_error_online($error, true, $sprintf);
	setup_fatal_error_context($error_message, $error);
}

/**
 * Handler for standard error messages, standard PHP error handler replacement.
 * It dies with fatal_error() if the error_level matches with error_reporting.
 *
 * @param int $error_level A pre-defined error-handling constant (see {@link https://php.net/errorfunc.constants})
 * @param string $error_string The error message
 * @param string $file The file where the error occurred
 * @param int $line The line where the error occurred
 */
function smf_error_handler($error_level, $error_string, $file, $line)
{
	global $settings, $modSettings, $db_show_debug;

	// Ignore errors if we're ignoring them or they are strict notices from PHP 5
	if (error_reporting() == 0)
		return;

	if (strpos($file, 'eval()') !== false && !empty($settings['current_include_filename']))
	{
		$array = debug_backtrace();
		$count = count($array);
		for ($i = 0; $i < $count; $i++)
		{
			if ($array[$i]['function'] != 'loadSubTemplate')
				continue;

			// This is a bug in PHP, with eval, it seems!
			if (empty($array[$i]['args']))
				$i++;
			break;
		}

		if (isset($array[$i]) && !empty($array[$i]['args']))
			$file = realpath($settings['current_include_filename']) . ' (' . $array[$i]['args'][0] . ' sub template - eval?)';
		else
			$file = realpath($settings['current_include_filename']) . ' (eval?)';
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		// Commonly, undefined indexes will occur inside attributes; try to show them anyway!
		if ($error_level % 255 != E_ERROR)
		{
			$temporary = ob_get_contents();
			if (substr($temporary, -2) == '="')
				echo '"';
		}

		// Debugging!  This should look like a PHP error message.
		echo '<br>
<strong>', $error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice'), '</strong>: ', $error_string, ' in <strong>', $file, '</strong> on line <strong>', $line, '</strong><br>';
	}

	$error_type = stripos($error_string, 'undefined') !== false ? 'undefined_vars' : 'general';

	$message = log_error($error_level . ': ' . $error_string, $error_type, $file, $line);

	// Let's give integrations a chance to ouput a bit differently
	call_integration_hook('integrate_output_error', array($message, $error_type, $error_level, $file, $line));

	// Dying on these errors only causes MORE problems (blank pages!)
	if ($file == 'Unknown')
		return;

	// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
	if ($error_level % 255 == E_ERROR)
		obExit(false);
	else
		return;

	// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die.  Violently so.
	if ($error_level % 255 == E_ERROR || $error_level % 255 == E_WARNING)
		fatal_error(allowedTo('admin_forum') ? $message : $error_string, false);

	// We should NEVER get to this point.  Any fatal error MUST quit, or very bad things can happen.
	if ($error_level % 255 == E_ERROR)
		die('No direct access...');
}

/**
 * It is called by {@link fatal_error()} and {@link fatal_lang_error()}.
 *
 * @uses Errors template, fatal_error sub template.
 *
 * @param string $error_message The error message
 * @param string $error_code An error code
 */
function setup_fatal_error_context($error_message, $error_code = null)
{
	global $context, $txt, $ssi_on_error_method;
	static $level = 0;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1)
		return false;

	// Maybe they came from dlattach or similar?
	if (SMF != 'SSI' && SMF != 'BACKGROUND' && empty($context['theme_loaded']))
		loadTheme();

	// Don't bother indexing errors mate...
	$context['robot_no_index'] = true;

	if (!isset($context['error_title']))
		$context['error_title'] = $txt['error_occured'];
	$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;

	$context['error_code'] = isset($error_code) ? 'id="' . $error_code . '" ' : '';

	if (empty($context['page_title']))
		$context['page_title'] = $context['error_title'];

	loadTemplate('Errors');
	$context['sub_template'] = 'fatal_error';

	// If this is SSI, what do they want us to do?
	if (SMF == 'SSI')
	{
		if (!empty($ssi_on_error_method) && $ssi_on_error_method !== true && is_callable($ssi_on_error_method))
			$ssi_on_error_method();
		elseif (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			loadSubTemplate('fatal_error');

		// No layers?
		if (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			exit;
	}
	// Alternatively from the cron call?
	elseif (SMF == 'BACKGROUND')
	{
		// We can't rely on even having language files available.
		if (defined('FROM_CLI') && FROM_CLI)
			echo 'cron error: ', $context['error_message'];
		else
			echo 'An error occurred. More information may be available in your logs.';
		exit;
	}

	// We want whatever for the header, and a footer. (footer includes sub template!)
	obExit(null, true, false, true);

	/* DO NOT IGNORE:
		If you are creating a bridge to SMF or modifying this function, you MUST
		make ABSOLUTELY SURE that this function quits and DOES NOT RETURN TO NORMAL
		PROGRAM FLOW.  Otherwise, security error messages will not be shown, and
		your forum will be in a very easily hackable state.
	*/
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Show a message for the (full block) maintenance mode.
 * It shows a complete page independent of language files or themes.
 * It is used only if $maintenance = 2 in Settings.php.
 * It stops further execution of the script.
 */
function display_maintenance_message()
{
	global $maintenance, $mtitle, $mmessage;

	set_fatal_error_headers();

	if (!empty($maintenance))
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';

	die();
}

/**
 * Show an error message for the connection problems.
 * It shows a complete page independent of language files or themes.
 * It is used only if there's no way to connect to the database.
 * It stops further execution of the script.
 */
function display_db_error()
{
	global $mbname, $modSettings, $maintenance;
	global $db_connection, $webmaster_email, $db_last_error, $db_error_send, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Logging.php');
	set_fatal_error_headers();

	// For our purposes, we're gonna want this on if at all possible.
	$modSettings['cache_enable'] = '1';

	if (($temp = cache_get_data('db_last_error', 600)) !== null)
		$db_last_error = max($db_last_error, $temp);

	if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
	{
		// Avoid writing to the Settings.php file if at all possible; use shared memory instead.
		cache_put_data('db_last_error', time(), 600);
		if (($temp = cache_get_data('db_last_error', 600)) === null)
			logLastDatabaseError();

		// Language files aren't loaded yet :(.
		$db_error = @$smcFunc['db_error']($db_connection);
		@mail($webmaster_email, $mbname . ': SMF Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\n" . $smcFunc['db_title'] . ' reported:' . "\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that SMF could not connect to the database, contact your host if this continues.');
	}

	// What to do?  Language files haven't and can't be loaded yet...
	echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>Connection Problems</title>
	</head>
	<body>
		<h3>Connection Problems</h3>
		Sorry, SMF was unable to connect to the database.  This may be caused by the server being busy.  Please try again later.
	</body>
</html>';

	die();
}

/**
 * Show an error message for load average blocking problems.
 * It shows a complete page independent of language files or themes.
 * It is used only if the load averages are too high to continue execution.
 * It stops further execution of the script.
 */
function display_loadavg_error()
{
	// If this is a load average problem, display an appropriate message (but we still don't have language files!)

	set_fatal_error_headers();

	echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>Temporarily Unavailable</title>
	</head>
	<body>
		<h3>Temporarily Unavailable</h3>
		Due to high stress on the server the forum is temporarily unavailable.  Please try again later.
	</body>
</html>';

	die();
}

/**
 * Small utility function for fatal error pages.
 * Used by {@link display_db_error()}, {@link display_loadavg_error()},
 * {@link display_maintenance_message()}
 */
function set_fatal_error_headers()
{
	if (headers_sent())
		return;

	// Don't cache this page!
	header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('cache-control: no-cache');

	// Send the right error codes.
	send_http_status(503, 'Service Temporarily Unavailable');
	header('status: 503 Service Temporarily Unavailable');
	header('retry-after: 3600');
}

/**
 * Small utility function for fatal error pages.
 * Used by fatal_error(), fatal_lang_error()
 *
 * @param string $error The error
 * @param array $sprintf An array of data to be sprintf()'d into the specified message
 */
function log_error_online($error, $sprintf = array())
{
	global $smcFunc, $user_info, $modSettings;

	// Don't bother if Who's Online is disabled.
	if (empty($modSettings['who_enabled']))
		return;

	// Maybe they came from SSI or similar where sessions are not recorded?
	if (SMF == 'SSI' || SMF == 'BACKGROUND')
		return;

	$session_id = !empty($user_info['is_guest']) ? 'ip' . $user_info['ip'] : session_id();

	// First, we have to get the online log, because we need to break apart the serialized string.
	$request = $smcFunc['db_query']('', '
		SELECT url
		FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => $session_id,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		// If this happened very early on in SMF startup, $smcFunc may not fully be defined.
		if (!isset($smcFunc['json_decode']))
		{
			$smcFunc['json_decode'] = 'smf_json_decode';
			$smcFunc['json_encode'] = 'json_encode';
		}

		list ($url) = $smcFunc['db_fetch_row']($request);
		$url = $smcFunc['json_decode']($url, true);
		$url['error'] = $error;
		// Url field got a max length of 1024 in db
		if (strlen($url['error']) > 500)
			$url['error'] = substr($url['error'], 0, 500);

		if (!empty($sprintf))
			$url['error_params'] = $sprintf;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_online
			SET url = {string:url}
			WHERE session = {string:session}',
			array(
				'url' => $smcFunc['json_encode']($url),
				'session' => $session_id,
			)
		);
	}
	$smcFunc['db_free_result']($request);
}

?>