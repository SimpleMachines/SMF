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
use SMF\Unicode\Utf8String;
use SMF\WebFetch\WebFetchApi;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\Attachment');
class_exists('SMF\\BBCodeParser');
class_exists('SMF\\Image');
class_exists('SMF\\Logging');
class_exists('SMF\\PageIndex');
class_exists('SMF\\QueryString');
class_exists('SMF\\Theme');
class_exists('SMF\\Time');
class_exists('SMF\\TimeZone');
class_exists('SMF\\Topic');
class_exists('SMF\\Url');
class_exists('SMF\\User');
class_exists('SMF\\Utils');
class_exists('SMF\\WebFetch\\WebFetchApi');

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

?>