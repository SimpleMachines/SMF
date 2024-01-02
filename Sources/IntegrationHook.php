<?php

/**
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

use SMF\Db\DatabaseApi as Db;

/**
 * Handles adding, removing, and calling hooked integration functions.
 */
class IntegrationHook
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'call_integration_hook',
			'add' => 'add_integration_function',
			'remove' => 'remove_integration_function',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * The name of this integration hook.
	 */
	public array $name;

	/**
	 * @var bool
	 *
	 * If true, silently skip hooked functions that are not callable.
	 */
	public bool $ignore_errors = false;

	/**
	 * @var array
	 *
	 * The results from executing this hook.
	 */
	public array $results = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * The callables to execute for this hook.
	 */
	private array $callables = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $name The name of the integration hook.
	 * @param bool $ignore_errors If true, silently skip hooked functions that
	 *    are not callable. Defaults to Utils::$context['ignore_hook_errors'].
	 */
	public function __construct(string $name, ?bool $ignore_errors = null)
	{
		if (!class_exists('SMF\\Config', false) || !class_exists('SMF\\Utils', false)) {
			return;
		}

		$this->ignore_errors = $ignore_errors ?? !empty(Utils::$context['ignore_hook_errors']);

		if (Config::$db_show_debug === true) {
			Utils::$context['debug']['hooks'][] = $name;
		}

		if (empty(Config::$modSettings[$name])) {
			return;
		}

		$func_strings = explode(',', Config::$modSettings[$name]);

		// Loop through each one to get the callable for it.
		foreach ($func_strings as $func_string) {
			// Hook has been marked as disabled. Skip it!
			if (strpos($func_string, '!') !== false) {
				continue;
			}

			$this->callables[$func_string] = Utils::getCallable($func_string);
		}
	}

	/**
	 * Executes all the callables in $this->callables, passing the $parameters
	 * to each one.
	 *
	 * @param array $parameters Parameters to pass to the hooked callables.
	 * @return array The results returned by all the hooked callables.
	 */
	public function execute(array $parameters = []): array
	{
		if (empty($this->callables)) {
			return $this->results;
		}

		// Loop through each callable.
		foreach ($this->callables as $func_string => $callable) {
			// Is it valid?
			if (is_callable($callable)) {
				$this->results[$func_string] = call_user_func_array($callable, $parameters);
			}
			// This failed, but we want to do so silently.
			elseif ($this->ignore_errors) {
				// return $this->results;
				continue;
			}
			// Whatever it was supposed to call, it failed :(
			else {
				Lang::load('Errors');

				// Get a full path to show on error.
				if (strpos($func_string, '|') !== false) {
					list($file, $func) = explode('|', $func_string);

					$path = strtr($file, [
						'$boarddir' => Config::$boarddir,
						'$sourcedir' => Config::$sourcedir,
					]);

					if (strpos($path, '$themedir') !== false && class_exists('SMF\\Theme', false) && !empty(Theme::$current->settings['theme_dir'])) {
						$path = strtr($path, [
							'$themedir' => Theme::$current->settings['theme_dir'],
						]);
					}

					ErrorHandler::log(sprintf(Lang::$txt['hook_fail_call_to'], $func, $path), 'general');
				}
				// Assume the file resides on Config::$boarddir somewhere...
				else {
					ErrorHandler::log(sprintf(Lang::$txt['hook_fail_call_to'], $func_string, Config::$boarddir), 'general');
				}
			}
		}

		return $this->results;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience method to create and execute an instance of this class.
	 *
	 * @param string $name The name of the integration hook.
	 * @param array $parameters Parameters to pass to the hooked callables.
	 * @return array The results returned by all the hooked callables.
	 */
	public static function call($name, $parameters = []): array
	{
		$hook = new self($name);

		return $hook->execute($parameters);
	}

	/**
	 * Adds a function or method to an integration hook.
	 *
	 * Does nothing if the function is already added.
	 * Cleans up enabled/disabled variants before taking requested action.
	 *
	 * @param string $name The complete hook name.
	 * @param string $function The function name. Can be a call to a method via
	 *    Class::method.
	 * @param bool $permanent If true, updates the value in settings table.
	 * @param string $file The filename. Must include one of the following
	 *    wildcards: $boarddir, $sourcedir, $themedir.
	 *    Example: $sourcedir/Test.php
	 * @param bool $object Indicates if your class will be instantiated when its
	 *    respective hook is called. If true, your function must be a method.
	 */
	public static function add(string $name, string $function, bool $permanent = true, string $file = '', bool $object = false): void
	{
		// Any objects?
		if ($object) {
			$function = $function . '#';
		}

		// Any files  to load?
		if (!empty($file) && is_string($file)) {
			$function = $file . (!empty($function) ? '|' . $function : '');
		}

		// Get the correct string.
		$integration_call = $function;
		$enabled_call = rtrim($function, '!');
		$disabled_call = $enabled_call . '!';

		// Is it going to be permanent?
		if ($permanent) {
			$request = Db::$db->query(
				'',
				'SELECT value
				FROM {db_prefix}settings
				WHERE variable = {string:variable}',
				[
					'variable' => $name,
				],
			);
			list($current_functions) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (!empty($current_functions)) {
				$current_functions = explode(',', $current_functions);

				// Cleanup enabled/disabled variants before taking action.
				$current_functions = array_diff($current_functions, [$enabled_call, $disabled_call]);

				$permanent_functions = array_unique(array_merge($current_functions, [$integration_call]));
			} else {
				$permanent_functions = [$integration_call];
			}

			Config::updateModSettings([$name => implode(',', $permanent_functions)]);
		}

		// Make current function list usable.
		$functions = empty(Config::$modSettings[$name]) ? [] : explode(',', Config::$modSettings[$name]);

		// Cleanup enabled/disabled variants before taking action.
		$functions = array_diff($functions, [$enabled_call, $disabled_call]);
		$functions = array_unique(array_merge($functions, [$integration_call]));

		Config::$modSettings[$name] = implode(',', $functions);

		// It is handy to be able to know which hooks are temporary...
		if ($permanent !== true) {
			if (!isset(Utils::$context['integration_hooks_temporary'])) {
				Utils::$context['integration_hooks_temporary'] = [];
			}

			Utils::$context['integration_hooks_temporary'][$name][$function] = true;
		}
	}

	/**
	 * Removes an integration hook function.
	 *
	 * Removes the given function from the given hook.
	 * Does nothing if the function is not available.
	 * Cleans up enabled/disabled variants before taking requested action.
	 *
	 * @see IntegrationHook::add
	 *
	 * @param string $name The complete hook name.
	 * @param string $function The function name. Can be a call to a method via
	 *    Class::method.
	 * @param bool $permanent Irrelevant for the function itself but need to
	 *    declare it to match.
	 * @param string $file The filename. Must include one of the following
	 *    wildcards: $boarddir, $sourcedir, $themedir.
	 *    Example: $sourcedir/Test.php
	 * @param bool $object Indicates if your class will be instantiated when its
	 *    respective hook is called. If true, your function must be a method.
	 */
	public static function remove(string $name, string $function, bool $permanent = true, string $file = '', bool $object = false): void
	{
		// Any objects?
		if ($object) {
			$function = $function . '#';
		}

		// Any files  to load?
		if (!empty($file) && is_string($file)) {
			$function = $file . '|' . $function;
		}

		// Get the correct string.
		$integration_call = $function;
		$enabled_call = rtrim($function, '!');
		$disabled_call = $enabled_call . '!';

		// Get the permanent functions.
		$request = Db::$db->query(
			'',
			'SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			[
				'variable' => $name,
			],
		);
		list($current_functions) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (!empty($current_functions)) {
			$current_functions = explode(',', $current_functions);

			// Cleanup enabled and disabled variants.
			$current_functions = array_unique(array_diff($current_functions, [$enabled_call, $disabled_call]));

			Config::updateModSettings([$name => implode(',', $current_functions)]);
		}

		// Turn the function list into something usable.
		$functions = empty(Config::$modSettings[$name]) ? [] : explode(',', Config::$modSettings[$name]);

		// Cleanup enabled and disabled variants.
		$functions = array_unique(array_diff($functions, [$enabled_call, $disabled_call]));

		Config::$modSettings[$name] = implode(',', $functions);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\IntegrationHook::exportStatic')) {
	IntegrationHook::exportStatic();
}

?>