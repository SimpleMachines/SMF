<?php

/**
 * The purpose of this file is... errors. (hard to guess, I guess?)  It takes
 * care of logging, error messages, error handling, database errors, and
 * error log administration.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

use SMF\Config;
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\ServerSideIncludes as SSI;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * Log an error, if the error logging is enabled.
 * filename and line should be __FILE__ and __LINE__, respectively.
 * Example use:
 *  die(log_error($msg));
 *
 * @param string $error_message The message to log
 * @param string|bool $error_type The type of error
 * @param string $file The name of the file where this error occurred
 * @param int $line The line where the error occurred
 * @return string The message that was logged
 */
function log_error($error_message, $error_type = 'general', $file = null, $line = null)
{
	static $last_error;
	static $tried_hook = false;
	static $error_call = 0;

	$error_call++;

	// Collect a backtrace
	if (!isset(Config::$db_show_debug) || Config::$db_show_debug === false)
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}
	else
	{
		// This is how to keep the args but skip the objects.
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS & DEBUG_BACKTRACE_PROVIDE_OBJECT);
	}

	// are we in a loop?
	if ($error_call > 2)
	{
		var_dump($backtrace);
		die('Error loop.');
	}

	// Check if error logging is actually on.
	if (empty(Config::$modSettings['enableErrorLogging']))
		return $error_message;

	// Basically, htmlspecialchars it minus &. (for entities!)
	$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
	$error_message = strtr($error_message, array('&lt;br /&gt;' => '<br>', '&lt;br&gt;' => '<br>', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br>'));

	// Add a file and line to the error message?
	// Don't use the actual txt entries for file and line but instead use %1$s for file and %2$s for line
	if ($file == null)
		$file = '';
	else
		// Windows style slashes don't play well, lets convert them to the unix style.
		$file = str_replace('\\', '/', $file);

	if ($line == null)
		$line = 0;
	else
		$line = (int) $line;

	// Find the best query string we can...
	$query_string = empty($_SERVER['QUERY_STRING']) ? (empty($_SERVER['REQUEST_URL']) ? '' : str_replace(Config::$scripturl, '', $_SERVER['REQUEST_URL'])) : $_SERVER['QUERY_STRING'];

	// Don't log the session hash in the url twice, it's a waste.
	$query_string = Utils::htmlspecialchars((SMF == 'SSI' || SMF == 'BACKGROUND' ? '' : '?') . preg_replace(array('~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'), array(';sesc', ''), $query_string));

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
		$known_error_types = array_merge($known_error_types, $other_error_types);
	}
	// Make sure the category that was specified is a valid one
	$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

	// leave out the call to log_error
	array_splice($backtrace, 0, 1);
	$backtrace = Utils::jsonEncode($backtrace);

	// Don't log the same error countless times, as we can get in a cycle of depression...
	$error_info = array(User::$me->id ?? User::$my_id ?? 0, time(), User::$me->ip ?? $_SERVER['REMOTE_ADDR'] ?? '', $query_string, $error_message, (string) (User::$sc ?? ''), $error_type, $file, $line, $backtrace);
	if (empty($last_error) || $last_error != $error_info)
	{
		// Insert the error into the database.
		Db::$db->error_insert($error_info);
		$last_error = $error_info;

		// Get an error count, if necessary
		if (!isset(Utils::$context['num_errors']))
		{
			$query = Db::$db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}log_errors',
				array()
			);

			list(Utils::$context['num_errors']) = Db::$db->fetch_row($query);
			Db::$db->free_result($query);
		}
		else
			Utils::$context['num_errors']++;
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
 * @param string|bool $log = 'general' What type of error to log this as (false to not log it))
 * @param int $status The HTTP status code associated with this error
 */
function fatal_error($error, $log = 'general', $status = 500)
{
	// Send the appropriate HTTP status header - set this to 0 or false if you don't want to send one at all
	if (!empty($status))
		send_http_status($status);

	// We don't have Lang::$txt yet, but that's okay...
	if (empty(Lang::$txt))
		die($error);

	log_error_online($error);
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
	static $fatal_error_called = false;

	// Ensure this is an array.
	$sprintf = (array) $sprintf;

	// Send the status header - set this to 0 or false if you don't want to send one at all
	if (!empty($status))
		send_http_status($status);

	// Try to load a theme if we don't have one.
	if (empty(Utils::$context['theme_loaded']) && empty($fatal_error_called))
	{
		$fatal_error_called = true;
		loadTheme();
	}

	// Attempt to load the text string.
	Lang::load('Errors');
	if (empty(Lang::$txt[$error]))
		$error_message = $error;
	else
		$error_message = empty($sprintf) ? Lang::$txt[$error] : vsprintf(Lang::$txt[$error], $sprintf);

	// Send a custom header if we have a custom message.
	if (isset($_REQUEST['js']) || isset($_REQUEST['xml']) || isset($_RQEUEST['ajax']))
		header('X-SMF-errormsg: ' .  $error_message);

	// If we have no theme stuff we can't have the language file...
	if (empty(Utils::$context['theme_loaded']))
		die($error);

	$reload_lang_file = true;
	// Log the error in the forum's language, but don't waste the time if we aren't logging
	if ($log)
	{
		Lang::load('Errors', Lang::$default);
		$reload_lang_file = Lang::$default != User::$me->language;
		if (empty(Lang::$txt[$error]))
			$error_message = $error;
		else
			$error_message = empty($sprintf) ? Lang::$txt[$error] : vsprintf(Lang::$txt[$error], $sprintf);
		log_error($error_message, $log);
	}

	// Load the language file, only if it needs to be reloaded
	if ($reload_lang_file && !empty($txt[$error]))
	{
		Lang::load('Errors');
		$error_message = empty($sprintf) ? Lang::$txt[$error] : vsprintf(Lang::$txt[$error], $sprintf);
	}

	log_error_online($error, $sprintf);
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
	global $settings;

	// Error was suppressed with the @-operator.
	if (error_reporting() == 0 || error_reporting() == (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR))
		return true;

	// Ignore errors that should should not be logged.
	$error_match = error_reporting() & $error_level;
	if (empty($error_match) || empty(Config::$modSettings['enableErrorLogging']))
		return false;

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

	if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
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

	// Let's give integrations a chance to output a bit differently
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
 * @uses template_fatal_error()
 *
 * @param string $error_message The error message
 * @param string $error_code An error code
 * @return void|false Normally doesn't return anything, but returns false if a recursive loop is detected
 */
function setup_fatal_error_context($error_message, $error_code = null)
{
	static $level = 0;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1)
		return false;

	// Maybe they came from dlattach or similar?
	if (SMF != 'SSI' && SMF != 'BACKGROUND' && empty(Utils::$context['theme_loaded']))
		loadTheme();

	// Don't bother indexing errors mate...
	Utils::$context['robot_no_index'] = true;

	if (!isset(Utils::$context['error_title']))
		Utils::$context['error_title'] = Lang::$txt['error_occured'];
	Utils::$context['error_message'] = isset(Utils::$context['error_message']) ? Utils::$context['error_message'] : $error_message;

	Utils::$context['error_code'] = isset($error_code) ? 'id="' . $error_code . '" ' : '';

	Utils::$context['error_link'] = isset(Utils::$context['error_link']) ? Utils::$context['error_link'] : 'javascript:document.location=document.referrer';

	if (empty(Utils::$context['page_title']))
		Utils::$context['page_title'] = Utils::$context['error_title'];

	loadTemplate('Errors');
	Utils::$context['sub_template'] = 'fatal_error';

	// If this is SSI, what do they want us to do?
	if (SMF == 'SSI')
	{
		if (!empty(SSI::$on_error_method) && SSI::$on_error_method !== true && is_callable(SSI::$on_error_method))
			call_user_func(SSI::$on_error_method);
		elseif (empty(SSI::$on_error_method) || SSI::$on_error_method !== true)
			loadSubTemplate('fatal_error');

		// No layers?
		if (empty(SSI::$on_error_method) || SSI::$on_error_method !== true)
			exit;
	}
	// Alternatively from the cron call?
	elseif (SMF == 'BACKGROUND')
	{
		// We can't rely on even having language files available.
		if (defined('FROM_CLI') && FROM_CLI)
			echo 'cron error: ', Utils::$context['error_message'];
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
	trigger_error('No direct access...', E_USER_ERROR);
}

/**
 * Show a message for the (full block) maintenance mode.
 * It shows a complete page independent of language files or themes.
 * It is used only if $maintenance = 2 in Settings.php.
 * It stops further execution of the script.
 */
function display_maintenance_message()
{
	set_fatal_error_headers();

	if (!empty(Config::$maintenance))
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>', Config::$mtitle, '</title>
	</head>
	<body>
		<h3>', Config::$mtitle, '</h3>
		', Config::$mmessage, '
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
	require_once(Config::$sourcedir . '/Logging.php');
	set_fatal_error_headers();

	// For our purposes, we're gonna want this on if at all possible.
	CacheApi::$enable = 1;

	if (($temp = CacheApi::get('db_last_error', 600)) !== null)
		Config::$db_last_error = max(Config::$db_last_error, $temp);

	if (Config::$db_last_error < time() - 3600 * 24 * 3 && empty(Config::$maintenance) && !empty(Config::$db_error_send))
	{
		// Avoid writing to the Settings.php file if at all possible; use shared memory instead.
		CacheApi::put('db_last_error', time(), 600);
		if (($temp = CacheApi::get('db_last_error', 600)) === null)
			logLastDatabaseError();

		// Language files aren't loaded yet :(.
		$db_error = isset(Db::$db) ? @Db::$db->error() : '';
		@mail(Config::$webmaster_email, Config::$mbname . ': SMF Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\n" . Db::$db->title . ' reported:' . "\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that SMF could not connect to the database, contact your host if this continues.');
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
	// Don't bother if Who's Online is disabled.
	if (empty(Config::$modSettings['who_enabled']))
		return;

	// Maybe they came from SSI or similar where sessions are not recorded?
	if (SMF == 'SSI' || SMF == 'BACKGROUND')
		return;

	$session_id = !empty(User::$me->is_guest) ? 'ip' . User::$me->ip : session_id();

	// First, we have to get the online log, because we need to break apart the serialized string.
	$request = Db::$db->query('', '
		SELECT url
		FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => $session_id,
		)
	);
	if (Db::$db->num_rows($request) != 0)
	{
		list ($url) = Db::$db->fetch_row($request);
		$url = Utils::jsonDecode($url, true);
		$url['error'] = $error;
		// Url field got a max length of 1024 in db
		if (strlen($url['error']) > 500)
			$url['error'] = substr($url['error'], 0, 500);

		if (!empty($sprintf))
			$url['error_params'] = $sprintf;

		Db::$db->query('', '
			UPDATE {db_prefix}log_online
			SET url = {string:url}
			WHERE session = {string:session}',
			array(
				'url' => Utils::jsonEncode($url),
				'session' => $session_id,
			)
		);
	}
	Db::$db->free_result($request);
}

?>