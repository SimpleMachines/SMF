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

/*	The purpose of this file is... errors. (hard to guess, huh?)  It takes
	care of logging, error messages, error handling, database errors, and
	error log administration.  It does this with:

	bool db_fatal_error(bool loadavg = false)
		- calls show_db_error().
		- this is used for database connection error handling.
		- loadavg means this is a load average problem, not a database error.

	string log_error(string error_message, string error_type = general,
			string filename = none, int line = none)
		- logs an error, if error logging is enabled.
		- depends on the enableErrorLogging setting.
		- filename and line should be __FILE__ and __LINE__, respectively.
		- returns the error message. (ie. die(log_error($msg));)

	void fatal_error(string error_message, mixed (bool or string) log = general)
		- stops execution and displays an error message.
		- logs the error message if log is missing or true.

	void fatal_lang_error(string error_message_key, mixed (bool or string) log = general,
			array sprintf = array())
		- stops execution and displays an error message by key.
		- uses the string with the error_message_key key.
		- loads the Errors language file.
		- applies the sprintf information if specified.
		- the information is logged if log is true or missing.
		- logs the error in the forum's default language while displaying the error
		  message in the user's language

	void error_handler(int error_level, string error_string, string filename,
			int line)
		- this is a standard PHP error handler replacement.
		- dies with fatal_error() if the error_level matches with
		  error_reporting.

	void setup_fatal_error_context(string error_message)
		- uses the fatal_error sub template of the Errors template - or the
		  error sub template in the Wireless template.
		- used by fatal_error() and fatal_lang_error()

	void show_db_error(bool loadavg = false)
		- called by db_fatal_error() function
		- shows a complete page independent of language files or themes.
		- used only if there's no way to connect to the database or the
		  load averages are too high to do so.
		- loadavg means this is a load average problem, not a database error.
		- stops further execution of the script.
*/

// Handle fatal errors - like connection errors or load average problems
function db_fatal_error($loadavg = false)
{
	global $sourcedir;

	show_db_error($loadavg);

	// Since we use "or db_fatal_error();" this is needed...
	return false;
}

// Log an error, if the option is on.
function log_error($error_message, $error_type = 'general', $file = null, $line = null)
{
	global $txt, $modSettings, $sc, $user_info, $smcFunc, $scripturl, $last_error;

	// Check if error logging is actually on.
	if (empty($modSettings['enableErrorLogging']))
		return $error_message;

	// Basically, htmlspecialchars it minus &. (for entities!)
	$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
	$error_message = strtr($error_message, array('&lt;br /&gt;' => '<br />', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br />'));

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
	$query_string = htmlspecialchars((SMF == 'SSI' ? '' : '?') . preg_replace(array('~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'), array(';sesc', ''), $query_string));

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
		'template',
		'debug',
	);

	// Make sure the category that was specified is a valid one
	$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

	// Don't log the same error countless times, as we can get in a cycle of depression...
	$error_info = array($user_info['id'], time(), $user_info['ip'], $query_string, $error_message, (string) $sc, $error_type, $file, $line);
	if (empty($last_error) || $last_error != $error_info)
	{
		// Insert the error into the database.
		$smcFunc['db_insert']('',
			'{db_prefix}log_errors',
			array('id_member' => 'int', 'log_time' => 'int', 'ip' => 'string-16', 'url' => 'string-65534', 'message' => 'string-65534', 'session' => 'string', 'error_type' => 'string', 'file' => 'string-255', 'line' => 'int'),
			$error_info,
			array('id_error')
		);
		$last_error = $error_info;
	}

	// Return the message to make things simpler.
	return $error_message;
}

// An irrecoverable error.
function fatal_error($error, $log = 'general')
{
	global $txt, $context, $modSettings;

	// We don't have $txt yet, but that's okay...
	if (empty($txt))
		die($error);

	setup_fatal_error_context($log || (!empty($modSettings['enableErrorLogging']) && $modSettings['enableErrorLogging'] == 2) ? log_error($error, $log) : $error);
}

// A fatal error with a message stored in the language file.
function fatal_lang_error($error, $log = 'general', $sprintf = array())
{
	global $txt, $language, $modSettings, $user_info, $context;
	static $fatal_error_called = false;

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
	if ($log || (!empty($modSettings['enableErrorLogging']) && $modSettings['enableErrorLogging'] == 2))
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

	setup_fatal_error_context($error_message);
}

// Handler for standard error messages.
function error_handler($error_level, $error_string, $file, $line)
{
	global $settings, $modSettings, $db_show_debug;

	// Ignore errors if we're ignoring them or they are strict notices from PHP 5 (which cannot be solved without breaking PHP 4.)
	if (error_reporting() == 0 || (defined('E_STRICT') && $error_level == E_STRICT && (empty($modSettings['enableErrorLogging']) || $modSettings['enableErrorLogging'] != 2)))
		return;

	if (strpos($file, 'eval()') !== false && !empty($settings['current_include_filename']))
	{
		if (function_exists('debug_backtrace'))
		{
			$array = debug_backtrace();
			for ($i = 0; $i < count($array); $i++)
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
		echo '<br />
<strong>', $error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice'), '</strong>: ', $error_string, ' in <strong>', $file, '</strong> on line <strong>', $line, '</strong><br />';
	}

	$error_type = strpos(strtolower($error_string), 'undefined') !== false ? 'undefined_vars' : 'general';

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
		die('Hacking attempt...');
}

function setup_fatal_error_context($error_message)
{
	global $context, $txt, $ssi_on_error_method;
	static $level = 0;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1)
		return false;

	// Maybe they came from dlattach or similar?
	if (SMF != 'SSI' && empty($context['theme_loaded']))
		loadTheme();

	// Don't bother indexing errors mate...
	$context['robot_no_index'] = true;

	if (!isset($context['error_title']))
		$context['error_title'] = $txt['error_occured'];
	$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;

	if (empty($context['page_title']))
		$context['page_title'] = $context['error_title'];

	// Display the error message - wireless?
	if (defined('WIRELESS') && WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_error';
	// Load the template and set the sub template.
	else
	{
		loadTemplate('Errors');
		$context['sub_template'] = 'fatal_error';
	}

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

// Show an error message for the connection problems.
function show_db_error($loadavg = false)
{
	global $sourcedir, $mbname, $maintenance, $mtitle, $mmessage, $modSettings;
	global $db_connection, $webmaster_email, $db_last_error, $db_error_send, $smcFunc;

	// Don't cache this page!
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache');

	// Send the right error codes.
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	header('Retry-After: 3600');

	if ($loadavg == false)
	{
		// For our purposes, we're gonna want this on if at all possible.
		$modSettings['cache_enable'] = '1';

		if (($temp = cache_get_data('db_last_error', 600)) !== null)
			$db_last_error = max($db_last_error, $temp);

		if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
		{
			require_once($sourcedir . '/Subs-Admin.php');

			// Avoid writing to the Settings.php file if at all possible; use shared memory instead.
			cache_put_data('db_last_error', time(), 600);
			if (($temp = cache_get_data('db_last_error', 600)) == null)
				updateLastDatabaseError();

			// Language files aren't loaded yet :(.
			$db_error = @$smcFunc['db_error']($db_connection);
			@mail($webmaster_email, $mbname . ': SMF Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\n" . $smcFunc['db_title'] . ' reported:' . "\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that SMF could not connect to the database, contact your host if this continues.');
		}
	}

	if (!empty($maintenance))
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="robots" content="noindex" />
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
	// If this is a load average problem, display an appropriate message (but we still don't have language files!)
	elseif ($loadavg)
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="robots" content="noindex" />
		<title>Temporarily Unavailable</title>
	</head>
	<body>
		<h3>Temporarily Unavailable</h3>
		Due to high stress on the server the forum is temporarily unavailable.  Please try again later.
	</body>
</html>';
	// What to do?  Language files haven't and can't be loaded yet...
	else
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="robots" content="noindex" />
		<title>Connection Problems</title>
	</head>
	<body>
		<h3>Connection Problems</h3>
		Sorry, SMF was unable to connect to the database.  This may be caused by the server being busy.  Please try again later.
	</body>
</html>';

	die;
}

?>