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
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\ServerSideIncludes as SSI;

/**
 * SMF's error handler.
 *
 * Also provides methods for logging and/or dying when errors occur.
 */
class ErrorHandler
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'smf_error_handler',
			'log' => 'log_error',
			'fatal' => 'fatal_error',
			'fatalLang' => 'fatal_lang_error',
			'displayMaintenanceMessage' => 'display_maintenance_message',
			'displayDbError' => 'display_db_error',
			'displayLoadAvgError' => 'display_loadavg_error',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $error_level A pre-defined error-handling constant (see {@link https://php.net/errorfunc.constants})
	 * @param string $error_string The error message
	 * @param string $file The file where the error occurred
	 * @param int $line The line where the error occurred
	 */
	public function __construct(int $error_level, string $error_string, string $file, int $line)
	{
		// Error was suppressed with the @-operator.
		if (error_reporting() == 0 || error_reporting() == (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
			return true;
		}

		// Ignore errors that should should not be logged.
		$error_match = error_reporting() & $error_level;

		if (empty($error_match) || empty(Config::$modSettings['enableErrorLogging'])) {
			return false;
		}

		if (strpos($file, 'eval()') !== false && !empty(Theme::$current->settings['current_include_filename'])) {
			$array = debug_backtrace();
			$count = count($array);

			for ($i = 0; $i < $count; $i++) {
				if ($array[$i]['function'] != 'SMF\\Theme::loadSubTemplate') {
					continue;
				}

				// This is a bug in PHP, with eval, it seems!
				if (empty($array[$i]['args'])) {
					$i++;
				}

				break;
			}

			if (isset($array[$i]) && !empty($array[$i]['args'])) {
				$file = realpath(Theme::$current->settings['current_include_filename']) . ' (' . $array[$i]['args'][0] . ' sub template - eval?)';
			} else {
				$file = realpath(Theme::$current->settings['current_include_filename']) . ' (eval?)';
			}
		}

		if (isset(Config::$db_show_debug) && Config::$db_show_debug === true) {
			// Commonly, undefined indexes will occur inside attributes; try to show them anyway!
			if ($error_level % 255 != E_ERROR) {
				$temporary = ob_get_contents();

				if (substr($temporary, -2) == '="') {
					echo '"';
				}
			}

			// Debugging!  This should look like a PHP error message.
			echo "<br>\n<strong>", $error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice'), '</strong>: ', $error_string, ' in <strong>', $file, '</strong> on line <strong>', $line, '</strong><br>';
		}

		$error_type = stripos($error_string, 'undefined') !== false ? 'undefined_vars' : 'general';

		$message = self::log($error_level . ': ' . $error_string, $error_type, $file, $line);

		// Let's give integrations a chance to output a bit differently
		IntegrationHook::call('integrate_output_error', [$message, $error_type, $error_level, $file, $line]);

		// Dying on these errors only causes MORE problems (blank pages!)
		if ($file == 'Unknown') {
			return;
		}

		// If this is an E_ERROR or E_USER_ERROR.... die. Violently so.
		if ($error_level % 255 == E_ERROR) {
			Utils::obExit(false);
		} else {
			return;
		}

		// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die. Violently so.
		if ($error_level % 255 == E_ERROR || $error_level % 255 == E_WARNING) {
			self::fatal(User::$me->allowedTo('admin_forum') ? $message : $error_string, false);
		}

		// We should NEVER get to this point.  Any fatal error MUST quit, or very bad things can happen.
		if ($error_level % 255 == E_ERROR) {
			die('No direct access...');
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience method to create an instance of this class.
	 *
	 * @param int $error_level A pre-defined error-handling constant (see {@link https://php.net/errorfunc.constants})
	 * @param string $error_string The error message
	 * @param string $file The file where the error occurred
	 * @param int $line The line where the error occurred
	 */
	public static function call(int $error_level, string $error_string, string $file, int $line): void
	{
		new self($error_level, $error_string, $file, $line);
	}

	/**
	 * Log an error, if the error logging is enabled.
	 *
	 * $file and $line should be __FILE__ and __LINE__, respectively.
	 *
	 * Example use:
	 *  die(ErrorHandler::log($msg));
	 *
	 * @param string $error_message The message to log.
	 * @param string|bool $error_type The type of error.
	 * @param string $file The name of the file where this error occurred.
	 * @param int $line The line where the error occurred.
	 * @return string The message that was logged.
	 */
	public static function log(string $error_message, string|bool $error_type = 'general', string $file = '', int $line = 0): string
	{
		static $last_error;
		static $tried_hook = false;
		static $error_call = 0;

		$error_call++;

		// Collect a backtrace
		if (!isset(Config::$db_show_debug) || Config::$db_show_debug === false) {
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		} else {
			// This is how to keep the args but skip the objects.
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS & DEBUG_BACKTRACE_PROVIDE_OBJECT);
		}

		// Are we in a loop?
		if ($error_call > 2) {
			var_dump($backtrace);

			die('Error loop.');
		}

		// Check if error logging is actually on.
		if (empty(Config::$modSettings['enableErrorLogging'])) {
			return $error_message;
		}

		// Basically, htmlspecialchars it minus &. (for entities!)
		$error_message = strtr($error_message, ['<' => '&lt;', '>' => '&gt;', '"' => '&quot;']);

		$error_message = strtr($error_message, ['&lt;br /&gt;' => '<br>', '&lt;br&gt;' => '<br>', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br>']);

		// Add a file and line to the error message?
		// Don't use the actual txt entries for file and line.
		// Instead use %1$s for file and %2$s for line.
		// Windows style slashes don't play well, lets convert them to the UNIX style.
		$file = str_replace('\\', '/', $file);

		// Find the best query string we can...
		$query_string = empty($_SERVER['QUERY_STRING']) ? (empty($_SERVER['REQUEST_URL']) ? '' : str_replace(Config::$scripturl, '', $_SERVER['REQUEST_URL'])) : $_SERVER['QUERY_STRING'];

		// Don't log the session hash in the url twice, it's a waste.
		$query_string = Utils::htmlspecialchars((SMF == 'SSI' || SMF == 'BACKGROUND' ? '' : '?') . preg_replace(['~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'], [';sesc', ''], $query_string));

		// Just so we know what board error messages are from.
		if (isset($_POST['board']) && !isset($_GET['board'])) {
			$query_string .= ($query_string == '' ? 'board=' : ';board=') . $_POST['board'];
		}

		// What types of categories do we have?
		$known_error_types = [
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
		];

		// This prevents us from infinite looping if the hook or call produces an error.
		$other_error_types = [];

		if (empty($tried_hook)) {
			$tried_hook = true;

			// Allow the hook to change the error_type and know about the error.
			IntegrationHook::call('integrate_error_types', [&$other_error_types, &$error_type, $error_message, $file, $line]);

			$known_error_types = array_merge($known_error_types, $other_error_types);
		}

		// Make sure the category that was specified is a valid one
		$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

		// Leave out the call to this method.
		array_splice($backtrace, 0, 1);
		$backtrace = Utils::jsonEncode($backtrace);

		// Don't log the same error countless times, as we can get in a cycle of depression...
		$error_info = [User::$me->id ?? User::$my_id ?? 0, time(), User::$me->ip ?? $_SERVER['REMOTE_ADDR'] ?? '', $query_string, $error_message, (string) (User::$sc ?? ''), $error_type, $file, $line, $backtrace];

		if (empty($last_error) || $last_error != $error_info) {
			// Insert the error into the database.
			Db::$db->error_insert($error_info);
			$last_error = $error_info;

			// Get an error count, if necessary
			if (!isset(Utils::$context['num_errors'])) {
				$query = Db::$db->query(
					'',
					'SELECT COUNT(*)
					FROM {db_prefix}log_errors',
					[],
				);
				list(Utils::$context['num_errors']) = Db::$db->fetch_row($query);
				Db::$db->free_result($query);
			} else {
				Utils::$context['num_errors']++;
			}
		}

		// Reset error call
		$error_call = 0;

		// Return the message to make things simpler.
		return $error_message;
	}

	/**
	 * An irrecoverable error.
	 *
	 * This function stops execution and displays an error message.
	 * It logs the error message if $log is specified.
	 *
	 * @param string $error The error message
	 * @param string|bool $log = 'general' What type of error to log this as (false to not log it))
	 * @param int $status The HTTP status code associated with this error
	 */
	public static function fatal(string $error, string|bool $log = 'general', int $status = 500): void
	{
		// Send the appropriate HTTP status header - set this to 0 or false if you don't want to send one at all
		if (!empty($status)) {
			Utils::sendHttpStatus($status);
		}

		// We don't have Lang::$txt yet, but that's okay...
		if (empty(Lang::$txt)) {
			die($error);
		}

		self::logOnline($error);
		self::setupFatalContext($log ? self::log($error, $log) : $error);
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
	 * @param string $error The error message.
	 * @param string|false $log The type of error, or false to not log it.
	 * @param array $sprintf An array of data to be sprintf()'d into the specified message.
	 * @param int $status The HTTP status code associated with this error. Default: 403.
	 */
	public static function fatalLang(string $error, string|bool $log = 'general', array $sprintf = [], int $status = 403)
	{
		static $fatal_error_called = false;

		// Send the status header - set this to 0 or false if you don't want to send one at all.
		if (!empty($status)) {
			Utils::sendHttpStatus($status);
		}

		// Try to load a theme if we don't have one.
		if (empty(Utils::$context['theme_loaded']) && empty($fatal_error_called)) {
			$fatal_error_called = true;
			Theme::load();
		}

		// Attempt to load the text string.
		Lang::load('Errors');

		if (empty(Lang::$txt[$error])) {
			$error_message = $error;
		} else {
			$error_message = empty($sprintf) ? Lang::$txt[$error] : vsprintf(Lang::$txt[$error], $sprintf);
		}

		// Send a custom header if we have a custom message.
		if (isset($_REQUEST['js']) || isset($_REQUEST['xml']) || isset($_RQEUEST['ajax'])) {
			header('X-SMF-errormsg: ' . $error_message);
		}

		// If we have no theme stuff we can't have the language file...
		if (empty(Utils::$context['theme_loaded'])) {
			die($error);
		}

		$reload_lang_file = true;

		// Log the error in the forum's language, but don't waste the time if we aren't logging
		if ($log) {
			Lang::load('Errors', Lang::$default);

			$reload_lang_file = Lang::$default != User::$me->language;

			if (empty(Lang::$txt[$error])) {
				$error_message = $error;
			} else {
				$error_message = empty($sprintf) ? Lang::$txt[$error] : vsprintf(Lang::$txt[$error], $sprintf);
			}

			self::log($error_message, $log);
		}

		// Load the language file, only if it needs to be reloaded
		if ($reload_lang_file && !empty($txt[$error])) {
			Lang::load('Errors');

			$error_message = empty($sprintf) ? Lang::$txt[$error] : vsprintf(Lang::$txt[$error], $sprintf);
		}

		self::logOnline($error, $sprintf);
		self::setupFatalContext($error_message, $error);
	}

	/**
	 * Show a message for the (full block) maintenance mode.
	 *
	 * It shows a complete page independent of language files or themes.
	 * It is used only if $maintenance = 2 in Settings.php.
	 * It stops further execution of the script.
	 */
	public static function displayMaintenanceMessage(): void
	{
		self::setFatalHeaders();

		if (!empty(Config::$maintenance)) {
			$mtitle = Config::$mtitle;
			$mmessage = Config::$mmessage;

			echo <<<END
				<!DOCTYPE html>
				<html>
					<head>
						<meta name="robots" content="noindex">
						<title>{$mtitle}</title>
					</head>
					<body>
						<h3>{$mtitle}</h3>
						{$mmessage}
					</body>
				</html>
				END;
		}

		die();
	}

	/**
	 * Show an error message for the connection problems.
	 *
	 * It shows a complete page independent of language files or themes.
	 * It is used only if there's no way to connect to the database.
	 * It stops further execution of the script.
	 */
	public static function displayDbError(): void
	{
		self::setFatalHeaders();

		// For our purposes, we're gonna want this on if at all possible.
		CacheApi::$enable = 1;

		if (($temp = CacheApi::get('db_last_error', 600)) !== null) {
			Config::$db_last_error = max(Config::$db_last_error, $temp);
		}

		if (Config::$db_last_error < time() - 3600 * 24 * 3 && empty(Config::$maintenance) && !empty(Config::$db_error_send)) {
			// Avoid writing to the Settings.php file if at all possible; use shared memory instead.
			CacheApi::put('db_last_error', time(), 600);

			if (($temp = CacheApi::get('db_last_error', 600)) === null) {
				self::logLastDatabaseError();
			}

			$db_error = isset(Db::$db) ? @Db::$db->error() : '';

			// Language files aren't loaded yet :(.
			@mail(Config::$webmaster_email, Config::$mbname . ': SMF Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\n" . Db::$db->title . ' reported:' . "\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that SMF could not connect to the database, contact your host if this continues.');
		}

		// What to do?  Language files haven't and can't be loaded yet...
		echo <<<END
			<!DOCTYPE html>
			<html>
				<head>
					<meta name="robots" content="noindex">
					<title>Connection Problems</title>
				</head>
				<body>
					<h3>Connection Problems</h3>
					Sorry, SMF was unable to connect to the database. This may be caused by the server being busy. Please try again later.
				</body>
			</html>
			END;

		die();
	}

	/**
	 * Show an error message for load average blocking problems.
	 *
	 * It shows a complete page independent of language files or themes.
	 * It is used only if the load averages are too high to continue execution.
	 * It stops further execution of the script.
	 */
	public static function displayLoadAvgError(): void
	{
		// If this is a load average problem, display an appropriate message (but we still don't have language files!)

		self::setFatalHeaders();

		echo <<<END
			<!DOCTYPE html>
			<html>
				<head>
					<meta name="robots" content="noindex">
					<title>Temporarily Unavailable</title>
				</head>
				<body>
					<h3>Temporarily Unavailable</h3>
					Due to high stress on the server the forum is temporarily unavailable.  Please try again later.
				</body>
			</html>
			END;

		die();
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Small utility function for fatal error pages.
	 * Used by self::fatal() and self::fatalLang().
	 *
	 * @param string $error The error
	 * @param array $sprintf An array of data to be sprintf()'d into the specified message
	 */
	protected static function logOnline(string $error, array $sprintf = [])
	{
		// Don't bother if Who's Online is disabled.
		if (empty(Config::$modSettings['who_enabled'])) {
			return;
		}

		// Maybe they came from SSI or similar where sessions are not recorded?
		if (SMF == 'SSI' || SMF == 'BACKGROUND') {
			return;
		}

		$session_id = !empty(User::$me->is_guest) ? 'ip' . User::$me->ip : session_id();

		// First, we have to get the online log, because we need to break apart the serialized string.
		$request = Db::$db->query(
			'',
			'SELECT url
			FROM {db_prefix}log_online
			WHERE session = {string:session}',
			[
				'session' => $session_id,
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			list($url) = Db::$db->fetch_row($request);

			$url = Utils::jsonDecode($url, true);
			$url['error'] = $error;

			// Url field got a max length of 1024 in db
			if (strlen($url['error']) > 500) {
				$url['error'] = substr($url['error'], 0, 500);
			}

			if (!empty($sprintf)) {
				$url['error_params'] = $sprintf;
			}

			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_online
				SET url = {string:url}
				WHERE session = {string:session}',
				[
					'url' => Utils::jsonEncode($url),
					'session' => $session_id,
				],
			);
		}
		Db::$db->free_result($request);
	}

	/**
	 * Small utility function for fatal error pages.
	 *
	 * Used by self::displayMaintenanceMessage(), self::displayDbError(), and
	 * self::displayLoadAvgError().
	 */
	protected static function setFatalHeaders(): void
	{
		if (headers_sent()) {
			return;
		}

		// Don't cache this page!
		header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('cache-control: no-cache');

		// Send the right error codes.
		Utils::sendHttpStatus(503, 'Service Temporarily Unavailable');
		header('status: 503 Service Temporarily Unavailable');
		header('retry-after: 3600');
	}

	/**
	 * It is called by self::fatal() and self::fatalLang().
	 *
	 * @uses template_fatal_error()
	 *
	 * @param string $error_message The error message
	 * @param string $error_code An error code
	 */
	protected static function setupFatalContext(string $error_message, ?string $error_code = null): void
	{
		static $level = 0;

		// Attempt to prevent a recursive loop.
		if (++$level > 1) {
			die($error_message);
		}

		// Maybe they came from dlattach or similar?
		if (SMF != 'SSI' && SMF != 'BACKGROUND' && empty(Utils::$context['theme_loaded'])) {
			Theme::load();
		}

		// Don't bother indexing errors mate...
		Utils::$context['robot_no_index'] = true;

		if (!isset(Utils::$context['error_title'])) {
			Utils::$context['error_title'] = Lang::$txt['error_occured'];
		}

		Utils::$context['error_message'] = Utils::$context['error_message'] ?? $error_message;

		Utils::$context['error_code'] = isset($error_code) ? 'id="' . $error_code . '" ' : '';

		Utils::$context['error_link'] = Utils::$context['error_link'] ?? 'javascript:document.location=document.referrer';

		if (empty(Utils::$context['page_title'])) {
			Utils::$context['page_title'] = Utils::$context['error_title'];
		}

		Theme::loadTemplate('Errors');
		Utils::$context['sub_template'] = 'fatal_error';

		// If this is SSI, what do they want us to do?
		if (SMF == 'SSI') {
			if (!empty(SSI::$on_error_method) && SSI::$on_error_method !== true && is_callable(SSI::$on_error_method)) {
				call_user_func(SSI::$on_error_method);
			} elseif (empty(SSI::$on_error_method) || SSI::$on_error_method !== true) {
				Theme::loadSubTemplate('fatal_error');
			}

			// No layers?
			if (empty(SSI::$on_error_method) || SSI::$on_error_method !== true) {
				exit;
			}
		}
		// Alternatively from the cron call?
		elseif (SMF == 'BACKGROUND') {
			// We can't rely on even having language files available.
			if (defined('FROM_CLI') && FROM_CLI) {
				echo 'cron error: ', Utils::$context['error_message'];
			} else {
				echo 'An error occurred. More information may be available in your logs.';
			}

			exit;
		}

		// We want whatever for the header, and a footer. (footer includes sub template!)
		Utils::obExit(null, true, false, true);

		/* DO NOT IGNORE:
			If you are creating a bridge to SMF or modifying this function, you MUST
			make ABSOLUTELY SURE that this function quits and DOES NOT RETURN TO NORMAL
			PROGRAM FLOW.  Otherwise, security error messages will not be shown, and
			your forum will be in a very easily hackable state.
		*/
		trigger_error('No direct access...', E_USER_ERROR);
	}

	/**
	 * Logs the last database error into a file.
	 * Attempts to use the backup file first, to store the last database error
	 * and only update db_last_error.php if the first was successful.
	 */
	protected static function logLastDatabaseError()
	{
		// Make a note of the last modified time in case someone does this before us
		$last_db_error_change = @filemtime(Config::$cachedir . '/db_last_error.php');

		// save the old file before we do anything
		$file = Config::$cachedir . '/db_last_error.php';
		$dberror_backup_fail = !@is_writable(Config::$cachedir . '/db_last_error_bak.php') || !@copy($file, Config::$cachedir . '/db_last_error_bak.php');
		$dberror_backup_fail = !$dberror_backup_fail ? (!file_exists(Config::$cachedir . '/db_last_error_bak.php') || filesize(Config::$cachedir . '/db_last_error_bak.php') === 0) : $dberror_backup_fail;

		clearstatcache();

		if (filemtime(Config::$cachedir . '/db_last_error.php') === $last_db_error_change) {
			// Write the change
			$write_db_change = '<' . '?' . "php\n" . '$db_last_error = ' . time() . ';' . "\n" . '?' . '>';
			$written_bytes = file_put_contents(Config::$cachedir . '/db_last_error.php', $write_db_change, LOCK_EX);

			// survey says ...
			if ($written_bytes !== strlen($write_db_change) && !$dberror_backup_fail) {
				// Oops. maybe we have no more disk space left, or some other troubles, troubles...
				// Copy the file back and run for your life!
				@copy(Config::$cachedir . '/db_last_error_bak.php', Config::$cachedir . '/db_last_error.php');
			} else {
				@touch(SMF_SETTINGS_FILE);

				return true;
			}
		}

		return false;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ErrorHandler::exportStatic')) {
	ErrorHandler::exportStatic();
}

?>