<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
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

use SMF\BrowserDetector;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Forum;
use SMF\Group;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Security;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Url;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Fetchers\CurlFetcher;
use SMF\Unicode\Utf8String;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\Attachment');
class_exists('SMF\\BBCodeParser');
class_exists('SMF\\Image');
class_exists('SMF\\Logging');
class_exists('SMF\\PageIndex');
class_exists('SMF\\Theme');
class_exists('SMF\\Time');
class_exists('SMF\\TimeZone');
class_exists('SMF\\Topic');
class_exists('SMF\\Url');
class_exists('SMF\\User');
class_exists('SMF\\Utils');

/**
 * Shorten a subject + internationalization concerns.
 *
 * - shortens a subject so that it is either shorter than length, or that length plus an ellipsis.
 * - respects internationalization characters and entities as one character.
 * - avoids trailing entities.
 * - returns the shortened string.
 *
 * @param string $subject The subject
 * @param int $len How many characters to limit it to
 * @return string The shortened subject - either the entire subject (if it's <= $len) or the subject shortened to $len characters with "..." appended
 */
function shorten_subject($subject, $len)
{
	// It was already short enough!
	if (Utils::entityStrlen((string) $subject) <= (int) $len)
		return $subject;

	// Shorten it by the length it was too long, and strip off junk from the end.
	return Utils::entitySubstr((string) $subject, 0, (int) $len) . '...';
}

/**
 * Calculates all the possible permutations (orders) of array.
 * should not be called on huge arrays (bigger than like 10 elements.)
 * returns an array containing each permutation.
 *
 * @deprecated since 2.1
 * @param array $array An array
 * @return array An array containing each permutation
 */
function permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] == 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

/**
 * Make sure the browser doesn't come back and repost the form data.
 * Should be used whenever anything is posted.
 *
 * @param string $setLocation The URL to redirect them to
 * @param bool $refresh Whether to use a meta refresh instead
 * @param bool $permanent Whether to send a 301 Moved Permanently instead of a 302 Moved Temporarily
 */
function redirectexit($setLocation = '', $refresh = false, $permanent = false)
{
	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty(Utils::$context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in Mail::addToQueue itself... :\
		Mail::addToQueue(true);

	$add = preg_match('~^(ftp|http)[s]?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

	if ($add)
		$setLocation = Config::$scripturl . ($setLocation != '' ? '?' . $setLocation : '');

	// Put the session ID in.
	if (defined('SID') && SID != '')
		$setLocation = preg_replace('/^' . preg_quote(Config::$scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', Config::$scripturl . '?' . SID . ';', $setLocation);
	// Keep that debug in their for template debugging!
	elseif (isset($_GET['debug']))
		$setLocation = preg_replace('/^' . preg_quote(Config::$scripturl, '/') . '\\??/', Config::$scripturl . '?debug;', $setLocation);

	if (!empty(Config::$modSettings['queryless_urls']) && (empty(Utils::$context['server']['is_cgi']) || ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && (!empty(Utils::$context['server']['is_apache']) || !empty(Utils::$context['server']['is_lighttpd']) || !empty(Utils::$context['server']['is_litespeed'])))
	{
		if (defined('SID') && SID != '')
			$setLocation = preg_replace_callback(
				'~^' . preg_quote(Config::$scripturl, '~') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~',
				function($m)
				{
					return Config::$scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html?' . SID . (isset($m[2]) ? "$m[2]" : "");
				},
				$setLocation
			);
		else
			$setLocation = preg_replace_callback(
				'~^' . preg_quote(Config::$scripturl, '~') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~',
				function($m)
				{
					return Config::$scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html' . (isset($m[2]) ? "$m[2]" : "");
				},
				$setLocation
			);
	}

	// Maybe integrations want to change where we are heading?
	call_integration_hook('integrate_redirect', array(&$setLocation, &$refresh, &$permanent));

	// Set the header.
	header('location: ' . str_replace(' ', '%20', $setLocation), true, $permanent ? 301 : 302);

	// Debugging.
	if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
		$_SESSION['debug_redirect'] = Db::$cache;

	obExit(false);
}

/**
 * Ends execution.  Takes care of template loading and remembering the previous URL.
 *
 * @param bool $header Whether to do the header
 * @param bool $do_footer Whether to do the footer
 * @param bool $from_index Whether we're coming from the board index
 * @param bool $from_fatal_error Whether we're coming from a fatal error
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	if (function_exists('trackStats'))
		Logging::trackStats();

	// If we have mail to send, send it.
	if (class_exists('SMF\\Mail', false) && !empty(Utils::$context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in Mail::addToQueue itself... :\
		Mail::addToQueue(true);

	$do_header = $header === null ? !$header_done : $header;
	if ($do_footer === null)
		$do_footer = $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty(Utils::$context['page_title']) && empty(Utils::$context['page_title_html_safe']))
			Utils::$context['page_title_html_safe'] = Utils::htmlspecialchars(html_entity_decode(Utils::$context['page_title'])) . (!empty(Utils::$context['current_page']) ? ' - ' . Lang::$txt['page'] . ' ' . (Utils::$context['current_page'] + 1) : '');

		// Start up the session URL fixer.
		ob_start('SMF\\QueryString::ob_sessrewrite');

		if (!empty(Theme::$current->settings['output_buffers']) && is_string(Theme::$current->settings['output_buffers']))
			$buffers = explode(',', Theme::$current->settings['output_buffers']);
		elseif (!empty(Theme::$current->settings['output_buffers']))
			$buffers = Theme::$current->settings['output_buffers'];
		else
			$buffers = array();

		if (isset(Config::$modSettings['integrate_buffer']))
			$buffers = array_merge(explode(',', Config::$modSettings['integrate_buffer']), $buffers);

		if (!empty($buffers))
			foreach ($buffers as $function)
			{
				$call = call_helper($function, true);

				// Is it valid?
				if (!empty($call))
					ob_start($call);
			}

		// Display the screen in the logical order.
		Theme::template_header();
		$header_done = true;
	}
	if ($do_footer)
	{
		Theme::loadSubTemplate(isset(Utils::$context['sub_template']) ? Utils::$context['sub_template'] : 'main');

		// Anything special to put out?
		if (!empty(Utils::$context['insert_after_template']) && !isset($_REQUEST['xml']))
			echo Utils::$context['insert_after_template'];

		// Just so we don't get caught in an endless loop of errors from the footer...
		if (!$footer_done)
		{
			$footer_done = true;
			Theme::template_footer();

			// (since this is just debugging... it's okay that it's after </html>.)
			if (!isset($_REQUEST['xml']))
				Logging::displayDebug();
		}
	}

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (!is_filtered_request(Forum::$unlogged_actions, 'action'))
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... don't switch browsers...
	$_SESSION['USER_AGENT'] = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index)
		exit;
}

/**
 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
 *
 * @param string $text The text to split into words
 * @param int $max_chars The maximum number of characters per word
 * @param bool $encrypt Whether to encrypt the results
 * @return array An array of ints or words depending on $encrypt
 */
function text2words($text, $max_chars = 20, $encrypt = false)
{
	// Upgrader may be working on old DBs...
	if (!isset(Utils::$context['utf8']))
		Utils::$context['utf8'] = false;

	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0' . (Utils::$context['utf8'] ? '\x{A0}' : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', strtr($text, array('<br>' => ' ')));

	// Step 2: Entities we left to letters, where applicable, lowercase.
	$words = Utils::htmlspecialcharsDecode(Utils::strtolower($words));

	// Step 3: Ready to split apart and index!
	$words = explode(' ', $words);

	if ($encrypt)
	{
		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));
		$returned_ints = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				$encrypted = substr(crypt($word, 'uk'), 2, $max_chars);
				$total = 0;
				for ($i = 0; $i < $max_chars; $i++)
					$total += $possible_chars[ord($encrypted[$i])] * pow(63, $i);
				$returned_ints[] = $max_chars == 4 ? min($total, 16777215) : $total;
			}
		}
		return array_unique($returned_ints);
	}
	else
	{
		// Trim characters before and after and add slashes for database insertion.
		$returned_words = array();
		foreach ($words as $word)
			if (($word = trim($word, '-_\'')) !== '')
				$returned_words[] = $max_chars === null ? $word : substr($word, 0, $max_chars);

		// Filter out all words that occur more than once.
		return array_unique($returned_words);
	}
}

/**
 * Process functions of an integration hook.
 * calls all functions of the given hook.
 * supports static class method calls.
 *
 * @param string $hook The hook name
 * @param array $parameters An array of parameters this hook implements
 * @return array The results of the functions
 */
function call_integration_hook($hook, $parameters = array())
{
	if (!class_exists('SMF\\Utils', false))
		return;

	if (Config::$db_show_debug === true)
		Utils::$context['debug']['hooks'][] = $hook;

	// Need to have some control.
	if (!isset(Utils::$context['instances']))
		Utils::$context['instances'] = array();

	$results = array();
	if (empty(Config::$modSettings[$hook]))
		return $results;

	$functions = explode(',', Config::$modSettings[$hook]);
	// Loop through each function.
	foreach ($functions as $function)
	{
		// Hook has been marked as "disabled". Skip it!
		if (strpos($function, '!') !== false)
			continue;

		$call = call_helper($function, true);

		// Is it valid?
		if (!empty($call))
			$results[$function] = call_user_func_array($call, $parameters);
		// This failed, but we want to do so silently.
		elseif (!empty($function) && !empty(Utils::$context['ignore_hook_errors']))
			return $results;
		// Whatever it was suppose to call, it failed :(
		elseif (!empty($function))
		{
			Lang::load('Errors');

			// Get a full path to show on error.
			if (strpos($function, '|') !== false)
			{
				list ($file, $string) = explode('|', $function);
				$absPath = empty(Theme::$current->settings['theme_dir']) ? (strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir))) : (strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => Theme::$current->settings['theme_dir'])));
				ErrorHandler::log(sprintf(Lang::$txt['hook_fail_call_to'], $string, $absPath), 'general');
			}
			// "Assume" the file resides on Config::$boarddir somewhere...
			else
				ErrorHandler::log(sprintf(Lang::$txt['hook_fail_call_to'], $function, Config::$boarddir), 'general');
		}
	}

	return $results;
}

/**
 * Add a function for integration hook.
 * Does nothing if the function is already added.
 * Cleans up enabled/disabled variants before taking requested action.
 *
 * @param string $hook The complete hook name.
 * @param string $function The function name. Can be a call to a method via Class::method.
 * @param bool $permanent If true, updates the value in settings table.
 * @param string $file The file. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param bool $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
 */
function add_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . (!empty($function) ? '|' . $function : '');

	// Get the correct string.
	$integration_call = $function;
	$enabled_call = rtrim($function, '!');
	$disabled_call = $enabled_call . '!';

	// Is it going to be permanent?
	if ($permanent)
	{
		$request = Db::$db->query('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			array(
				'variable' => $hook,
			)
		);
		list ($current_functions) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);

			// Cleanup enabled/disabled variants before taking action.
			$current_functions = array_diff($current_functions, array($enabled_call, $disabled_call));

			$permanent_functions = array_unique(array_merge($current_functions, array($integration_call)));
		}
		else
			$permanent_functions = array($integration_call);

		Config::updateModSettings(array($hook => implode(',', $permanent_functions)));
	}

	// Make current function list usable.
	$functions = empty(Config::$modSettings[$hook]) ? array() : explode(',', Config::$modSettings[$hook]);

	// Cleanup enabled/disabled variants before taking action.
	$functions = array_diff($functions, array($enabled_call, $disabled_call));

	$functions = array_unique(array_merge($functions, array($integration_call)));
	Config::$modSettings[$hook] = implode(',', $functions);

	// It is handy to be able to know which hooks are temporary...
	if ($permanent !== true)
	{
		if (!isset(Utils::$context['integration_hooks_temporary']))
			Utils::$context['integration_hooks_temporary'] = array();
		Utils::$context['integration_hooks_temporary'][$hook][$function] = true;
	}
}

/**
 * Remove an integration hook function.
 * Removes the given function from the given hook.
 * Does nothing if the function is not available.
 * Cleans up enabled/disabled variants before taking requested action.
 *
 * @param string $hook The complete hook name.
 * @param string $function The function name. Can be a call to a method via Class::method.
 * @param boolean $permanent Irrelevant for the function itself but need to declare it to match
 * @param string $file The filename. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param boolean $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
 * @see add_integration_function
 */
function remove_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . '|' . $function;

	// Get the correct string.
	$integration_call = $function;
	$enabled_call = rtrim($function, '!');
	$disabled_call = $enabled_call . '!';

	// Get the permanent functions.
	$request = Db::$db->query('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:variable}',
		array(
			'variable' => $hook,
		)
	);
	list ($current_functions) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if (!empty($current_functions))
	{
		$current_functions = explode(',', $current_functions);

		// Cleanup enabled and disabled variants.
		$current_functions = array_unique(array_diff($current_functions, array($enabled_call, $disabled_call)));

		Config::updateModSettings(array($hook => implode(',', $current_functions)));
	}

	// Turn the function list into something usable.
	$functions = empty(Config::$modSettings[$hook]) ? array() : explode(',', Config::$modSettings[$hook]);

	// Cleanup enabled and disabled variants.
	$functions = array_unique(array_diff($functions, array($enabled_call, $disabled_call)));

	Config::$modSettings[$hook] = implode(',', $functions);
}

/**
 * Receives a string and tries to figure it out if it's a method or a function.
 * If a method is found, it looks for a "#" which indicates SMF should create a new instance of the given class.
 * Checks the string/array for is_callable() and return false/fatal_lang_error is the given value results in a non callable string/array.
 * Prepare and returns a callable depending on the type of method/function found.
 *
 * @param mixed $string The string containing a function name or a static call. The function can also accept a closure, object or a callable array (object/class, valid_callable)
 * @param boolean $return If true, the function will not call the function/method but instead will return the formatted string.
 * @return string|array|boolean Either a string or an array that contains a callable function name or an array with a class and method to call. Boolean false if the given string cannot produce a callable var.
 */
function call_helper($string, $return = false)
{
	// Really?
	if (empty($string))
		return false;

	// An array? should be a "callable" array IE array(object/class, valid_callable).
	// A closure? should be a callable one.
	if (is_array($string) || $string instanceof Closure)
		return $return ? $string : (is_callable($string) ? call_user_func($string) : false);

	// No full objects, sorry! pass a method or a property instead!
	if (is_object($string))
		return false;

	// Stay vitaminized my friends...
	$string = Utils::htmlspecialchars(Utils::htmlTrim($string));

	// Is there a file to load?
	$string = load_file($string);

	// Loaded file failed
	if (empty($string))
		return false;

	// Found a method.
	if (strpos($string, '::') !== false)
	{
		list ($class, $method) = explode('::', $string);

		// Check if a new object will be created.
		if (strpos($method, '#') !== false)
		{
			// Need to remove the # thing.
			$method = str_replace('#', '', $method);

			// Don't need to create a new instance for every method.
			if (empty(Utils::$context['instances'][$class]) || !(Utils::$context['instances'][$class] instanceof $class))
			{
				Utils::$context['instances'][$class] = new $class;

				// Add another one to the list.
				if (Config::$db_show_debug === true)
				{
					if (!isset(Utils::$context['debug']['instances']))
						Utils::$context['debug']['instances'] = array();

					Utils::$context['debug']['instances'][$class] = $class;
				}
			}

			$func = array(Utils::$context['instances'][$class], $method);
		}

		// Right then. This is a call to a static method.
		else
			$func = array($class, $method);
	}

	// Nope! just a plain regular function.
	else
		$func = $string;

	// We can't call this helper, but we want to silently ignore this.
	if (!is_callable($func, false, $callable_name) && !empty(Utils::$context['ignore_hook_errors']))
		return false;

	// Right, we got what we need, time to do some checks.
	elseif (!is_callable($func, false, $callable_name))
	{
		Lang::load('Errors');
		ErrorHandler::log(sprintf(Lang::$txt['sub_action_fail'], $callable_name), 'general');

		// Gotta tell everybody.
		return false;
	}

	// Everything went better than expected.
	else
	{
		// What are we gonna do about it?
		if ($return)
			return $func;

		// If this is a plain function, avoid the heat of calling call_user_func().
		else
		{
			if (is_array($func))
				call_user_func($func);

			else
				$func();
		}
	}
}

/**
 * Receives a string and tries to figure it out if it contains info to load a file.
 * Checks for a | (pipe) symbol and tries to load a file with the info given.
 * The string should be format as follows File.php|. You can use the following wildcards: $boarddir, $sourcedir and if available at the moment of execution, $themedir.
 *
 * @param string $string The string containing a valid format.
 * @return string|boolean The given string with the pipe and file info removed. Boolean false if the file couldn't be loaded.
 */
function load_file($string)
{
	if (empty($string))
		return false;

	if (strpos($string, '|') !== false)
	{
		list ($file, $string) = explode('|', $string);

		// Match the wildcards to their regular vars.
		if (empty(Theme::$current->settings['theme_dir']))
			$absPath = strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir));

		else
			$absPath = strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => Theme::$current->settings['theme_dir']));

		// Load the file if it can be loaded.
		if (file_exists($absPath))
			require_once($absPath);

		// No? try a fallback to Config::$sourcedir
		else
		{
			$absPath = Config::$sourcedir . '/' . $file;

			if (file_exists($absPath))
				require_once($absPath);

			// Sorry, can't do much for you at this point.
			elseif (empty(Utils::$context['uninstalling']))
			{
				Lang::load('Errors');
				ErrorHandler::log(sprintf(Lang::$txt['hook_fail_loading_file'], $absPath), 'general');

				// File couldn't be loaded.
				return false;
			}
		}
	}

	return $string;
}

/**
 * Get the contents of a URL, irrespective of allow_url_fopen.
 *
 * - reads the contents of an http or ftp address and returns the page in a string
 * - will accept up to 3 page redirections (redirectio_level in the function call is private)
 * - if post_data is supplied, the value and length is posted to the given url as form data
 * - URL must be supplied in lowercase
 *
 * @param string $url The URL
 * @param string $post_data The data to post to the given URL
 * @param bool $keep_alive Whether to send keepalive info
 * @param int $redirection_level How many levels of redirection
 * @return string|false The fetched data or false on failure
 */
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 0)
{
	static $keep_alive_dom = null, $keep_alive_fp = null;

	$url = Url::create($url, true)->validate()->toAscii();

	// No scheme? No data for you!
	if (empty($url->scheme) || !in_array($url->scheme, array('http', 'https', 'ftp', 'ftps')))
		return false;

	$path_and_query = $url->path . (isset($url->query) && $url->query !== '' ? '?' . $url->query : '');

	// An FTP url. We should try connecting and RETRieving it...
	// @todo Move this to a SMF\Fetchers\FtpFetcher class.
	if (in_array($url->scheme, array('ftp', 'ftps')))
	{
		// Establish a connection and attempt to enable passive mode.
		$ftp = new SMF\PackageManager\FtpConnection(($url->scheme === 'ftps' ? 'ssl://' : '') . $url->host, empty($url->port) ? 21 : $url->port, 'anonymous', Config::$webmaster_email);

		if ($ftp->error !== false || !$ftp->passive())
			return false;

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $path_and_query . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
			return false;

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}
	// This is more likely; a standard HTTP URL.
	elseif (in_array($url->scheme, array('http', 'https')))
	{
		// First try to use fsockopen, because it is fastest.
		// @todo Move this to a SMF\Fetchers\SocketFetcher class.
		if ($keep_alive && $url->host == $keep_alive_dom)
			$fp = $keep_alive_fp;

		if (empty($fp))
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($url->scheme === 'https' ? 'ssl://' : '') . $url->host, empty($url->port) ? ($url->scheme === 'https' ? 443 : 80) : $url->port, $err, $err, 5);
		}
		if (!empty($fp))
		{
			if ($keep_alive)
			{
				$keep_alive_dom = $url->host;
				$keep_alive_fp = $fp;
			}

			// I want this, from there, and I'm not going to be bothering you for more (probably.)
			if (empty($post_data))
			{
				fwrite($fp, 'GET ' . ($path_and_query !== '/' ? str_replace(' ', '%20', $path_and_query) : '') . ' HTTP/1.0' . "\r\n");
				fwrite($fp, 'Host: ' . $url->host . (empty($url->port) ? ($url->scheme === 'https' ? ':443' : '') : ':' . $url->port) . "\r\n");
				fwrite($fp, 'user-agent: '. SMF_USER_AGENT . "\r\n");
				if ($keep_alive)
					fwrite($fp, 'connection: Keep-Alive' . "\r\n\r\n");
				else
					fwrite($fp, 'connection: close' . "\r\n\r\n");
			}
			else
			{
				fwrite($fp, 'POST ' . ($path_and_query !== '/' ? $path_and_query : '') . ' HTTP/1.0' . "\r\n");
				fwrite($fp, 'Host: ' . $url->host . (empty($url->port) ? ($url->scheme === 'https' ? ':443' : '') : ':' . $url->port) . "\r\n");
				fwrite($fp, 'user-agent: '. SMF_USER_AGENT . "\r\n");
				if ($keep_alive)
					fwrite($fp, 'connection: Keep-Alive' . "\r\n");
				else
					fwrite($fp, 'connection: close' . "\r\n");
				fwrite($fp, 'content-type: application/x-www-form-urlencoded' . "\r\n");
				fwrite($fp, 'content-length: ' . strlen($post_data) . "\r\n\r\n");
				fwrite($fp, $post_data);
			}

			$response = fgets($fp, 768);

			// Redirect in case this location is permanently or temporarily moved.
			if ($redirection_level < 3 && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
			{
				$header = '';
				$location = '';
				while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
					if (stripos($header, 'location:') !== false)
						$location = trim(substr($header, strpos($header, ':') + 1));

				if (empty($location))
					return false;
				else
				{
					if (!$keep_alive)
						fclose($fp);
					return fetch_web_data($location, $post_data, $keep_alive, $redirection_level + 1);
				}
			}

			// Make sure we get a 200 OK.
			elseif (preg_match('~^HTTP/\S+\s+20[01]~i', $response) === 0)
				return false;

			// Skip the headers...
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
			{
				if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
					$content_length = $match[1];
				elseif (preg_match('~connection:\s*close~i', $header) != 0)
				{
					$keep_alive_dom = null;
					$keep_alive = false;
				}

				continue;
			}

			$data = '';
			if (isset($content_length))
			{
				while (!feof($fp) && strlen($data) < $content_length)
					$data .= fread($fp, $content_length - strlen($data));
			}
			else
			{
				while (!feof($fp))
					$data .= fread($fp, 4096);
			}

			if (!$keep_alive)
				fclose($fp);
		}

		// If using fsockopen didn't work, try to use cURL if available.
		elseif (function_exists('curl_init'))
		{
			$fetch_data = new CurlFetcher();
			$fetch_data->get_url_data($url, $post_data);

			// no errors and a 200 result, then we have a good dataset, well we at least have data. ;)
			if ($fetch_data->result('code') == 200 && !$fetch_data->result('error'))
				$data = $fetch_data->result('body');
			else
				return false;
		}

		// Neither fsockopen nor curl are available. Well, phooey.
		else
			return false;
	}
	else
	{
		// Umm, this shouldn't happen?
		Lang::load('Errors');
		trigger_error(Lang::$txt['fetch_web_data_bad_url'], E_USER_NOTICE);
		$data = false;
	}

	return $data;
}

/**
 * Attempts to determine the MIME type of some data or a file.
 *
 * @param string $data The data to check, or the path or URL of a file to check.
 * @param string $is_path If true, $data is a path or URL to a file.
 * @return string|bool A MIME type, or false if we cannot determine it.
 */
function get_mime_type($data, $is_path = false)
{
	$finfo_loaded = extension_loaded('fileinfo');
	$exif_loaded = extension_loaded('exif') && function_exists('image_type_to_mime_type');

	// Oh well. We tried.
	if (!$finfo_loaded && !$exif_loaded)
		return false;

	// Start with the 'empty' MIME type.
	$mime_type = 'application/x-empty';

	if ($finfo_loaded)
	{
		// Just some nice, simple data to analyze.
		if (empty($is_path))
			$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);

		// A file, or maybe a URL?
		else
		{
			// Local file.
			if (file_exists($data))
				$mime_type = mime_content_type($data);

			// URL.
			elseif ($data = fetch_web_data($data))
				$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);
		}
	}
	// Workaround using Exif requires a local file.
	else
	{
		// If $data is a URL to fetch, do so.
		if (!empty($is_path) && !file_exists($data) && url_exists($data))
		{
			$data = fetch_web_data($data);
			$is_path = false;
		}

		// If we don't have a local file, create one and use it.
		if (empty($is_path))
		{
			$temp_file = tempnam(Config::$cachedir, md5($data));
			file_put_contents($temp_file, $data);
			$is_path = true;
			$data = $temp_file;
		}

		$imagetype = @exif_imagetype($data);

		if (isset($temp_file))
			unlink($temp_file);

		// Unfortunately, this workaround only works for image files.
		if ($imagetype !== false)
			$mime_type = image_type_to_mime_type($imagetype);
	}

	return $mime_type;
}

/**
 * Checks whether a file or data has the expected MIME type.
 *
 * @param string $data The data to check, or the path or URL of a file to check.
 * @param string $type_pattern A regex pattern to match the acceptable MIME types.
 * @param string $is_path If true, $data is a path or URL to a file.
 * @return int 1 if the detected MIME type matches the pattern, 0 if it doesn't, or 2 if we can't check.
 */
function check_mime_type($data, $type_pattern, $is_path = false)
{
	// Get the MIME type.
	$mime_type = get_mime_type($data, $is_path);

	// Couldn't determine it.
	if ($mime_type === false)
		return 2;

	// Check whether the MIME type matches expectations.
	return (int) @preg_match('~' . $type_pattern . '~', $mime_type);
}

/**
 * Decode HTML entities to their UTF-8 equivalent character, except for
 * HTML special characters, which are always converted to numeric entities.
 *
 * Callback function for preg_replace_callback in subs-members
 * Uses capture group 2 in the supplied array
 * Does basic scan to ensure characters are inside a valid range
 *
 * @deprecated since 3.0
 *
 * @param array $matches An array of matches (relevant info should be the 3rd item)
 * @return string A fixed string
 */
function replaceEntities__callback($matches)
{
	return strtr(
		htmlspecialchars(Utils::entityDecode($matches[1], true), ENT_QUOTES),
		array(
			'&amp;' => '&#038;',
			'&quot;' => '&#034;',
			'&lt;' => '&#060;',
			'&gt;' => '&#062;',
		)
	);
}

/**
 * Converts HTML entities to UTF-8 equivalents.
 *
 * Callback function for preg_replace_callback
 * Uses capture group 1 in the supplied array
 * Does basic checks to keep characters inside a viewable range.
 *
 * @deprecated since 3.0
 *
 * @param array $matches An array of matches (relevant info should be the 2nd item in the array)
 * @return string The fixed string
 */
function fixchar__callback($matches)
{
	return Utils::entityDecode($matches[0], true);
}

/**
 * Strips out invalid HTML entities and fixes double-encoded entities.
 *
 * Callback function for preg_replace_callback.
 *
 * @deprecated since 3.0
 *
 * @param array $matches An array of matches (relevant info should be the 3rd
 *    item in the array)
 * @return string The fixed string
 */
function entity_fix__callback($matches)
{
	return Utils::sanitizeEntities(Utils::entityFix($matches[1]));
}

/**
 * Return a Gravatar URL based on
 * - the supplied email address,
 * - the global maximum rating,
 * - the global default fallback,
 * - maximum sizes as set in the admin panel.
 *
 * It is SSL aware, and caches most of the parameters.
 *
 * @param string $email_address The user's email address
 * @return string The gravatar URL
 */
function get_gravatar_url($email_address)
{
	static $url_params = null;

	if ($url_params === null)
	{
		$ratings = array('G', 'PG', 'R', 'X');
		$defaults = array('mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank');
		$url_params = array();
		if (!empty(Config::$modSettings['gravatarMaxRating']) && in_array(Config::$modSettings['gravatarMaxRating'], $ratings))
			$url_params[] = 'rating=' . Config::$modSettings['gravatarMaxRating'];
		if (!empty(Config::$modSettings['gravatarDefault']) && in_array(Config::$modSettings['gravatarDefault'], $defaults))
			$url_params[] = 'default=' . Config::$modSettings['gravatarDefault'];
		if (!empty(Config::$modSettings['avatar_max_width_external']))
			$size_string = (int) Config::$modSettings['avatar_max_width_external'];
		if (!empty(Config::$modSettings['avatar_max_height_external']) && !empty($size_string))
			if ((int) Config::$modSettings['avatar_max_height_external'] < $size_string)
				$size_string = Config::$modSettings['avatar_max_height_external'];

		if (!empty($size_string))
			$url_params[] = 's=' . $size_string;
	}
	$http_method = !empty(Config::$modSettings['force_ssl']) ? 'https://secure' : 'http://www';

	return $http_method . '.gravatar.com/avatar/' . md5(Utils::strtolower($email_address)) . '?' . implode('&', $url_params);
}

/**
 * Safe serialize() and unserialize() replacements
 *
 * @license Public Domain
 *
 * @author anthon (dot) pang (at) gmail (dot) com
 */

/**
 * Safe serialize() replacement. Recursive
 * - output a strict subset of PHP's native serialized representation
 * - does not serialize objects
 *
 * @param mixed $value
 * @return string
 */
function _safe_serialize($value)
{
	if (is_null($value))
		return 'N;';

	if (is_bool($value))
		return 'b:' . (int) $value . ';';

	if (is_int($value))
		return 'i:' . $value . ';';

	if (is_float($value))
		return 'd:' . str_replace(',', '.', $value) . ';';

	if (is_string($value))
		return 's:' . strlen($value) . ':"' . $value . '";';

	if (is_array($value))
	{
		// Check for nested objects or resources.
		$contains_invalid = false;
		array_walk_recursive(
			$value,
			function($v) use (&$contains_invalid)
			{
				if (is_object($v) || is_resource($v))
					$contains_invalid = true;
			}
		);
		if ($contains_invalid)
			return false;

		$out = '';
		foreach ($value as $k => $v)
			$out .= _safe_serialize($k) . _safe_serialize($v);

		return 'a:' . count($value) . ':{' . $out . '}';
	}

	// safe_serialize cannot serialize resources or objects.
	return false;
}

/**
 * Wrapper for _safe_serialize() that handles exceptions and multibyte encoding issues.
 *
 * @param mixed $value
 * @return string
 */
function safe_serialize($value)
{
	// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_serialize($value);

	if (isset($mbIntEnc))
		mb_internal_encoding($mbIntEnc);

	return $out;
}

/**
 * Safe unserialize() replacement
 * - accepts a strict subset of PHP's native serialized representation
 * - does not unserialize objects
 *
 * @param string $str
 * @return mixed
 * @throw Exception if $str is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_unserialize($str)
{
	// Input  is not a string.
	if (empty($str) || !is_string($str))
		return false;

	// The substring 'O:' is used to serialize objects.
	// If it is not present, then there are none in the serialized data.
	if (strpos($str, 'O:') === false)
		return unserialize($str);

	$stack = array();
	$expected = array();

	/*
	 * states:
	 *   0 - initial state, expecting a single value or array
	 *   1 - terminal state
	 *   2 - in array, expecting end of array or a key
	 *   3 - in array, expecting value or another array
	 */
	$state = 0;
	while ($state != 1)
	{
		$type = isset($str[0]) ? $str[0] : '';
		if ($type == '}')
			$str = substr($str, 1);

		elseif ($type == 'N' && $str[1] == ';')
		{
			$value = null;
			$str = substr($str, 2);
		}
		elseif ($type == 'b' && preg_match('/^b:([01]);/', $str, $matches))
		{
			$value = $matches[1] == '1' ? true : false;
			$str = substr($str, 4);
		}
		elseif ($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches))
		{
			$value = (int) $matches[1];
			$str = $matches[2];
		}
		elseif ($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches))
		{
			$value = (float) $matches[1];
			$str = $matches[3];
		}
		elseif ($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int) $matches[1], 2) == '";')
		{
			$value = substr($matches[2], 0, (int) $matches[1]);
			$str = substr($matches[2], (int) $matches[1] + 2);
		}
		elseif ($type == 'a' && preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches))
		{
			$expectedLength = (int) $matches[1];
			$str = $matches[2];
		}

		// Object or unknown/malformed type.
		else
			return false;

		switch ($state)
		{
			case 3: // In array, expecting value or another array.
				if ($type == 'a')
				{
					$stack[] = &$list;
					$list[$key] = array();
					$list = &$list[$key];
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if ($type != '}')
				{
					$list[$key] = $value;
					$state = 2;
					break;
				}

				// Missing array value.
				return false;

			case 2: // in array, expecting end of array or a key
				if ($type == '}')
				{
					// Array size is less than expected.
					if (count($list) < end($expected))
						return false;

					unset($list);
					$list = &$stack[count($stack) - 1];
					array_pop($stack);

					// Go to terminal state if we're at the end of the root array.
					array_pop($expected);

					if (count($expected) == 0)
						$state = 1;

					break;
				}

				if ($type == 'i' || $type == 's')
				{
					// Array size exceeds expected length.
					if (count($list) >= end($expected))
						return false;

					$key = $value;
					$state = 3;
					break;
				}

				// Illegal array index type.
				return false;

			// Expecting array or value.
			case 0:
				if ($type == 'a')
				{
					$data = array();
					$list = &$data;
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}

				if ($type != '}')
				{
					$data = $value;
					$state = 1;
					break;
				}

				// Not in array.
				return false;
		}
	}

	// Trailing data in input.
	if (!empty($str))
		return false;

	return $data;
}

/**
 * Wrapper for _safe_unserialize() that handles exceptions and multibyte encoding issue
 *
 * @param string $str
 * @return mixed
 */
function safe_unserialize($str)
{
	// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 0x02))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_unserialize($str);

	if (isset($mbIntEnc))
		mb_internal_encoding($mbIntEnc);

	return $out;
}

/**
 * Tries different modes to make file/dirs writable. Wrapper function for chmod()
 *
 * @param string $file The file/dir full path.
 * @param int $value Not needed, added for legacy reasons.
 * @return boolean  true if the file/dir is already writable or the function was able to make it writable, false if the function couldn't make the file/dir writable.
 */
function smf_chmod($file, $value = 0)
{
	// No file? no checks!
	if (empty($file))
		return false;

	// Already writable?
	if (is_writable($file))
		return true;

	// Do we have a file or a dir?
	$isDir = is_dir($file);
	$isWritable = false;

	// Set different modes.
	$chmodValues = $isDir ? array(0750, 0755, 0775, 0777) : array(0644, 0664, 0666);

	foreach ($chmodValues as $val)
	{
		// If it's writable, break out of the loop.
		if (is_writable($file))
		{
			$isWritable = true;
			break;
		}

		else
			@chmod($file, $val);
	}

	return $isWritable;
}

/**
 * Outputs a response.
 * It assumes the data is already a string.
 *
 * @param string $data The data to print
 * @param string $type The content type. Defaults to Json.
 * @return void
 */
function smf_serverResponse($data = '', $type = 'content-type: application/json')
{
	// Defensive programming anyone?
	if (empty($data))
		return false;

	// Don't need extra stuff...
	Config::$db_show_debug = false;

	// Kill anything else.
	ob_end_clean();

	if (!empty(Config::$modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// Set the header.
	header($type);

	// Echo!
	echo $data;

	// Done.
	obExit(false);
}

/**
 * Creates optimized regular expressions from an array of strings.
 *
 * An optimized regex built using this function will be much faster than a
 * simple regex built using `implode('|', $strings)` --- anywhere from several
 * times to several orders of magnitude faster.
 *
 * However, the time required to build the optimized regex is approximately
 * equal to the time it takes to execute the simple regex. Therefore, it is only
 * worth calling this function if the resulting regex will be used more than
 * once.
 *
 * Because PHP places an upper limit on the allowed length of a regex, very
 * large arrays of $strings may not fit in a single regex. Normally, the excess
 * strings will simply be dropped. However, if the $returnArray parameter is set
 * to true, this function will build as many regexes as necessary to accommodate
 * everything in $strings and return them in an array. You will need to iterate
 * through all elements of the returned array in order to test all possible
 * matches.
 *
 * @param array $strings An array of strings to make a regex for.
 * @param string $delim An optional delimiter character to pass to preg_quote().
 * @param bool $returnArray If true, returns an array of regexes.
 * @return string|array One or more regular expressions to match any of the input strings.
 */
function build_regex($strings, $delim = null, $returnArray = false)
{
	static $regexes = array();

	// If it's not an array, there's not much to do. ;)
	if (!is_array($strings))
		return preg_quote(@strval($strings), $delim);

	$regex_key = md5(json_encode(array($strings, $delim, $returnArray)));

	if (isset($regexes[$regex_key]))
		return $regexes[$regex_key];

	// The mb_* functions are faster than the SMF\Utils ones, but may not be available
	if (function_exists('mb_internal_encoding') && function_exists('mb_detect_encoding') && function_exists('mb_strlen') && function_exists('mb_substr'))
	{
		if (($string_encoding = mb_detect_encoding(implode(' ', $strings))) !== false)
		{
			$current_encoding = mb_internal_encoding();
			mb_internal_encoding($string_encoding);
		}

		$strlen = 'mb_strlen';
		$substr = 'mb_substr';
	}
	else
	{
		$strlen = 'SMF\\Utils::entityStrlen';
		$substr = 'SMF\\Utils::entitySubstr';
	}

	// This recursive function creates the index array from the strings
	$add_string_to_index = function($string, $index) use (&$strlen, &$substr, &$add_string_to_index)
	{
		static $depth = 0;
		$depth++;

		$first = (string) @$substr($string, 0, 1);

		// No first character? That's no good.
		if ($first === '')
		{
			// A nested array? Really? Ugh. Fine.
			if (is_array($string) && $depth < 20)
			{
				foreach ($string as $str)
					$index = $add_string_to_index($str, $index);
			}

			$depth--;
			return $index;
		}

		if (empty($index[$first]))
			$index[$first] = array();

		if ($strlen($string) > 1)
		{
			// Sanity check on recursion
			if ($depth > 99)
				$index[$first][$substr($string, 1)] = '';

			else
				$index[$first] = $add_string_to_index($substr($string, 1), $index[$first]);
		}
		else
			$index[$first][''] = '';

		$depth--;
		return $index;
	};

	// This recursive function turns the index array into a regular expression
	$index_to_regex = function(&$index, $delim) use (&$strlen, &$index_to_regex)
	{
		static $depth = 0;
		$depth++;

		// Absolute max length for a regex is 32768, but we might need wiggle room
		$max_length = 30000;

		$regex = array();
		$length = 0;

		foreach ($index as $key => $value)
		{
			$key_regex = preg_quote($key, $delim);
			$new_key = $key;

			if (empty($value))
				$sub_regex = '';
			else
			{
				$sub_regex = $index_to_regex($value, $delim);

				if (count(array_keys($value)) == 1)
				{
					$new_key_array = explode('(?' . '>', $sub_regex);
					$new_key .= $new_key_array[0];
				}
				else
					$sub_regex = '(?' . '>' . $sub_regex . ')';
			}

			if ($depth > 1)
				$regex[$new_key] = $key_regex . $sub_regex;
			else
			{
				if (($length += strlen($key_regex . $sub_regex) + 1) < $max_length || empty($regex))
				{
					$regex[$new_key] = $key_regex . $sub_regex;
					unset($index[$key]);
				}
				else
					break;
			}
		}

		// Sort by key length and then alphabetically
		uksort(
			$regex,
			function($k1, $k2) use (&$strlen)
			{
				$l1 = $strlen($k1);
				$l2 = $strlen($k2);

				if ($l1 == $l2)
					return strcmp($k1, $k2) > 0 ? 1 : -1;
				else
					return $l1 > $l2 ? -1 : 1;
			}
		);

		$depth--;
		return implode('|', $regex);
	};

	// Now that the functions are defined, let's do this thing
	$index = array();
	$regex = '';

	foreach ($strings as $string)
		$index = $add_string_to_index($string, $index);

	if ($returnArray === true)
	{
		$regex = array();
		while (!empty($index))
			$regex[] = '(?' . '>' . $index_to_regex($index, $delim) . ')';
	}
	else
		$regex = '(?' . '>' . $index_to_regex($index, $delim) . ')';

	// Restore PHP's internal character encoding to whatever it was originally
	if (!empty($current_encoding))
		mb_internal_encoding($current_encoding);

	$regexes[$regex_key] = $regex;
	return $regex;
}

/**
 * Sends an appropriate HTTP status header based on a given status code
 *
 * @param int $code The status code
 * @param string $status The string for the status. Set automatically if not provided.
 */
function send_http_status($code, $status = '')
{
	// This will fail anyways if headers have been sent.
	if (headers_sent())
		return;

	$statuses = array(
		204 => 'No Content',
		206 => 'Partial Content',
		304 => 'Not Modified',
		400 => 'Bad Request',
		403 => 'Forbidden',
		404 => 'Not Found',
		410 => 'Gone',
		500 => 'Internal Server Error',
		503 => 'Service Unavailable',
	);

	$protocol = !empty($_SERVER['SERVER_PROTOCOL']) && preg_match('~^\s*(HTTP/[12]\.\d)\s*$~i', $_SERVER['SERVER_PROTOCOL'], $matches) ? $matches[1] : 'HTTP/1.0';

	// Typically during these requests, we have cleaned the response (ob_*clean), ensure these headers exist.
	Security::frameOptionsHeader();
	Security::corsPolicyHeader();

	if (!isset($statuses[$code]) && empty($status))
		header($protocol . ' 500 Internal Server Error');
	else
		header($protocol . ' ' . $code . ' ' . (!empty($status) ? $status : $statuses[$code]));
}

/**
 * Truncate an array to a specified length
 *
 * @param array $array The array to truncate
 * @param int $max_length The upperbound on the length
 * @param int $deep How levels in an multidimensional array should the function take into account.
 * @return array The truncated array
 */
function truncate_array($array, $max_length = 1900, $deep = 3)
{
	$array = (array) $array;

	$curr_length = array_length($array, $deep);

	if ($curr_length <= $max_length)
		return $array;

	else
	{
		// Truncate each element's value to a reasonable length
		$param_max = floor($max_length / count($array));

		$current_deep = $deep - 1;

		foreach ($array as $key => &$value)
		{
			if (is_array($value))
				if ($current_deep > 0)
					$value = truncate_array($value, $current_deep);

			else
				$value = substr($value, 0, $param_max - strlen($key) - 5);
		}

		return $array;
	}
}

/**
 * array_length Recursive
 * @param array $array
 * @param int $deep How many levels should the function
 * @return int
 */
function array_length($array, $deep = 3)
{
	// Work with arrays
	$array = (array) $array;
	$length = 0;

	$deep_count = $deep - 1;

	foreach ($array as $value)
	{
		// Recursive?
		if (is_array($value))
		{
			// No can't do
			if ($deep_count <= 0)
				continue;

			$length += array_length($value, $deep_count);
		}
		else
			$length += strlen($value);
	}

	return $length;
}

/**
 * Compares existance request variables against an array.
 *
 * The input array is associative, where keys denote accepted values
 * in a request variable denoted by `$req_val`. Values can be:
 *
 * - another associative array where at least one key must be found
 *   in the request and their values are accepted request values.
 * - A scalar value, in which case no furthur checks are done.
 *
 * @param array $array
 * @param string $req_var request variable
 *
 * @return bool whether any of the criteria was satisfied
 */
function is_filtered_request(array $array, $req_var)
{
	$matched = false;
	if (isset($_REQUEST[$req_var], $array[$_REQUEST[$req_var]]))
	{
		if (is_array($array[$_REQUEST[$req_var]]))
		{
			foreach ($array[$_REQUEST[$req_var]] as $subtype => $subnames)
				$matched |= isset($_REQUEST[$subtype]) && in_array($_REQUEST[$subtype], $subnames);
		}
		else
			$matched = true;
	}

	return (bool) $matched;
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 *
 * See https://www.w3.org/TR/xml/#charsets
 *
 * @param string $string The string to clean
 * @return string The cleaned string
 */
function cleanXml($string)
{
	$illegal_chars = array(
		// Remove all ASCII control characters except \t, \n, and \r.
		"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08",
		"\x0B", "\x0C", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13", "\x14",
		"\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
		"\x1E", "\x1F",
		// Remove \xFFFE and \xFFFF
		"\xEF\xBF\xBE", "\xEF\xBF\xBF",
	);

	$string = str_replace($illegal_chars, '', $string);

	// The Unicode surrogate pair code points should never be present in our
	// strings to begin with, but if any snuck in, they need to be removed.
	if (!empty(Utils::$context['utf8']) && strpos($string, "\xED") !== false)
		$string = preg_replace('/\xED[\xA0-\xBF][\x80-\xBF]/', '', $string);

	return $string;
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in JavaScript
 *
 * @param string $string The string to escape
 * @param bool $as_json If true, escape as double-quoted string. Default false.
 * @return string The escaped string
 */
function JavaScriptEscape($string, $as_json = false)
{
	$q = !empty($as_json) ? '"' : '\'';

	return $q . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		$q => addslashes($q),
		'</' => '<' . $q . ' + ' . $q . '/',
		'<script' => '<scri' . $q . '+' . $q . 'pt',
		'<body>' => '<bo' . $q . '+' . $q . 'dy>',
		'<a href' => '<a hr' . $q . '+' . $q . 'ef',
		Config::$scripturl => $q . ' + smf_scripturl + ' . $q,
	)) . $q;
}

?>