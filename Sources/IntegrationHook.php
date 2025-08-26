<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

use Exception;
use SMF\Db\DatabaseApi as Db;

/**
 * Handles adding, removing, and calling hooked integration functions.
 */
class IntegrationHook
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The name of this integration hook.
	 */
	public string $name;

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

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * Big array of hooks.
	 * Key holds the hook name.
	 * Value holds an array of Hook data.
	 *
	 * @var array<string, array[]>
	 */
	private static array $hooks = [];

	/**
	 * These hooks did not use the intergate_ prefix.
	 * @var array
	 */
	private static array $no_integrate_names = [
		'pre_cache_quick_get',
		'post_cache_quick_get',
		'cache_put_data',
		'cache_get_data',
		'mention_insert_quote',
		'mention_insert_msg',
		'before_profile_save_avatar',
		'after_profile_save_avatar',
		'who_allowed',
		'whos_online_after',
	];

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

		$this->name = $name;

		$this->ignore_errors = $ignore_errors ?? !empty(Utils::$context['ignore_hook_errors']);

		if (!empty(Config::$db_show_debug)) {
			Utils::$context['debug']['hooks'][] = $this->name;
		}

		if (empty(self::$hooks) || empty(self::$hooks[$name])) {
			return;
		}

		// Loop through each one to get the callable for it.
		foreach (self::$hooks[$name] as $hook) {
			// Hook has been marked as disabled. Skip it!
			if (!$hook['is_enabled']) {
				continue;
			}

			// Old "include" hooks would dump the content into the function list.
			if (str_ends_with($name, '_include') && empty($hook['file']) && !empty($hook['function'])) {
				$hook['file'] = $hook['function'];
				$hook['function'] = '';
			}

			// Attempt to load the file, only if succesful do we attempt to prepare the callable.
			if (!empty($hook['file']) && !self::loadFile($hook['file'], $name === 'pre_include')) {
				continue;
			}

			// Special include hooks don't generate callables.
			if (!str_ends_with($name, '_include')) {
				$this->callables[$hook['id_hook']] = self::getCallable($hook['function'], $hook['class'], $hook['is_object']);
			}
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
		foreach ($this->callables as $id_hook => $callable) {
			// Is it valid?
			if (\is_callable($callable)) {
				$this->results[$id_hook] = \call_user_func_array($callable, $parameters);
			}
			// This failed, but we want to do so silently.
			elseif ($this->ignore_errors) {
				// return $this->results;
				continue;
			}
			// Whatever it was supposed to call, it failed :(
			else {
				$hook = self::$hooks[$id_hook];
				$hook_call = (!empty($hook['is_object']) ? '#' : '') . (!empty($hook['class']) ? $hook['class'] . ':' : '') . $hook['function'];

				// Assume the file resides on Config::$boarddir somewhere...
				$file = Config::$boarddir;

				// Get a full path to show on error.
				if (!empty($hook['file'])) {
					$file = strtr($hook['file'], [
						'$boarddir' => Config::$boarddir,
						'$sourcedir' => Config::$sourcedir,
					]);

					if (str_contains($file, '$themedir') && class_exists('SMF\\Theme', false) && !empty(Theme::$current->settings['theme_dir'])) {
						$file = strtr($file, [
							'$themedir' => Theme::$current->settings['theme_dir'],
						]);
					}
				}

				ErrorHandler::log(Lang::getTxt('hook_fail_call_to', [$hook_call, $file], file: 'Errors'), 'general');
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
	public static function call(string $name, array $parameters = []): array
	{
		$name = self::cleanHookName($name);
		$hook = new self($name);

		return $hook->execute($parameters);
	}

	/**
	 * Convenience method to fetch all hooks loaded.
	 *
	 * @param ?string $name If provided, only returns hooks from a single hook
	 * @return array The results returned.
	 */
	public static function get(?string $name = null): array
	{
		if (!empty($name)) {
			return self::$hooks[$name];
		}

		return self::$hooks;
	}

	/**
	 * Load up all our hook data from the database.
	 * Sends off for the compatiblity layer with SMF 2.1 calls.
	 *
	 */
	final public static function load()
	{
		try {
			$request = Db::$db->query(
				'SELECT id_hook, is_enabled, hook_name, func, file, class, is_object, package_id
				FROM {db_prefix}hooks',
				[
				],
			);

			foreach (Db::$db->fetch_all($request) as $row) {
				self::$hooks[$row['hook_name']] ??= [];

				self::$hooks[$row['hook_name']][] = [
					'id_hook' => (int) $row['id_hook'],
					'is_enabled' => $row['is_enabled'] === '1' ? true : false,
					'hook_name' => trim($row['hook_name'] ?? ''),
					'function' => trim($row['func'] ?? ''),
					'file' => trim($row['file'] ?? ''),
					'class' => trim($row['class'] ?? ''),
					'is_object' => $row['is_object'] == '1' ? true : false,
					'package_id' => $row['package_id'] ?? null,
					'is_temp' => false,
				];

				if (Config::$backward_compatibility) {
					self::updateModSettings($row['hook_name'], self::buildBcString($row));
				}
			}
			Db::$db->free_result($request);
		} catch (Exception $e) {
			return;
		}
	}

	/**
	 * Parses the given input to determine if is a callable entity or can be
	 * turned into one, and then returns either a callable or false.
	 *
	 * If $input is already a callable entity, it will simply be returned.
	 * Otherwise, this method will attempt to turn it into one.
	 *
	 * @param string|callable $function Function to parse as a callable.
	 * @param string|null $class (Optional) Class we would be loading.
	 * @param ?bool $is_object (Optional) Initialize the object.
	 * @param ?bool $ignore_errors Optional. Whether to suppress errors if the
	 *    callable is invalid. If null, falls back to the current value of
	 *    Utils::$context['ignore_hook_errors']. Default: null.
	 * @return callable|false Either a valid callable or false on failure.
	 */
	final public static function getCallable(string|callable $function, ?string $class = null, bool $is_object = false, ?bool $ignore_errors = null): callable|false
	{
		if (!\is_string($function)) {
			return \is_callable($function) ? $function : false;
		}

		// Abort if file loading fails.
		if (empty($function)) {
			return false;
		}

		$callable = false;
		$callable_name = (!empty($class) ? $class . '::' : '') . $function;

		// Process the instances.
		if ($is_object && \is_string($class)) {
			Utils::$context['instances'] ??= [];

			if (!isset(Utils::$context['instances'][$class]) || !(Utils::$context['instances'][$class] instanceof $class)) {
				Utils::$context['instances'][$class] = new $class();

				// Optionally track instance creation for debugging.
				if (!empty(Config::$db_show_debug)) {
					Utils::$context['debug']['instances'][$class] = $class;
				}
			}

			$callable = [Utils::$context['instances'][$class], $function];
		} elseif (!empty($class)) {
			// Static method reference.
			$callable = [$class, $function];
		} else {
			// Treat as a plain function.
			$callable = $function;
		}

		// Validate the callable.
		if (!\is_callable($callable, false, $callable_name)) {
			$ignore_errors ??= !empty(Utils::$context['ignore_hook_errors']);

			if ($ignore_errors) {
				return false;
			}

			// Log error for invalid callables.
			ErrorHandler::log((string) Lang::getTxt('sub_action_fail', [$callable_name], file: 'Errors'), 'general');

			return false;
		}

		return $callable;
	}

	/**
	 * Register a hook.
	 *
	 * @param string $name Calls self::cleanHookName($name) to remote integrate_
	 * @param string $function Name of the function to call
	 * @param string $file (Optional) Load a file prior to calling the function
	 * @param string $class (Optional) Class of the function, supports both static and object
	 * @param bool $is_object (Optional) If class should be intialized as an object
	 * @param bool $is_enabled (Optional) if False, the hook is added, but not called
	 * @param ?string $package_id (Optional) If defined, add the package_id that added this hook
	 * @param bool $permanent (Optional) if true, we will add to the database, otherwise just held in memory.
	 * @return int id of the hook.
	 */
	public static function register(string $name, string $function, string $file = '', string $class = '', bool $is_object = false, bool $is_enabled = false, ?string $package_id = null, bool $permanent = true): int
	{
		$name = self::cleanHookName($name);

		if ($permanent) {
			$id_hook = Db::$db->insert(
				'',
				'{db_prefix}hooks',
				[
					'is_enabled' => 'int',
					'hook_name' => 'string-255',
					'func' => 'string-255',
					'file' => 'string-255',
					'class' => 'string-255',
					'is_object' => 'int',
					'package_id' => 'string-255',
				],
				[
					[
						$is_enabled ? 1 : 0,
						$name,
						$function,
						$file,
						$class,
						$is_object ? 1 : 0,
						$package_id,
					],
				],
				[
					'id_hook',
				],
				1,
			);
		} else {
			$id_hook = rand(10000, 90000);
		}

		$hook = [
			'id_hook' => $id_hook,
			'is_enabled' => $is_enabled,
			'hook_name' => $name,
			'function' => $function,
			'file' => $file,
			'class' => $class,
			'is_object' => $is_object,
			'package_id' => $package_id,
			'is_temp' => !$permanent,
		];

		self::$hooks[$name] ??= [];
		self::$hooks[$name][] = $hook;

		if (Config::$backward_compatibility) {
			self::updateModSettings($name, self::buildBcString($hook));
		}

		return $id_hook;
	}

	/**
	 * Remove a hook from the system.
	 *
	 * @param string $name Calls self::cleanHookName($name) to remote integrate_
	 * @param int $id_hook Id of hook.
	 * @param string $function (Optional) Name of the function to call
	 * @param string $file (Optional) Load a file prior to calling the function
	 * @param string $class (Optional) Class of the function, supports both static and object
	 * @param bool $is_object (Optional) If class should be intialized as an object
	 * @param bool $is_enabled (Optional) if False, the hook is added, but not called
	 * @param bool $permanent If true, removes from database.
	 */
	public static function unregister(string $name, int $id_hook, string $function = '', string $file = '', string $class = '', bool $is_object = false, bool $is_enabled = false, bool $permanent = true): void
	{
		$name = self::cleanHookName($name);
		self::$hooks[$name] ??= [];

		if (empty(self::$hooks[$name])) {
			return;
		}

		// If we don't have a hook id, we have to search.
		$key = null;

		if (empty($id_hook)) {
			$key = array_find_key(self::$hooks[$name], function ($val) use ($function, $file, $class, $is_object) {
				return
					$val['function'] === $function
					&& $val['class'] === $class
					&& $val['file'] === $file
					&& $val['is_object'] === $is_object;
			});
		} else {
			$key = array_find_key(self::$hooks[$name], fn($val) => $val['id_hook'] === $id_hook);
		}

		if ($key !== null) {
			if (Config::$backward_compatibility) {
				self::updateModSettings($name, self::buildBcString(self::$hooks[$name][$key]));
			}

			unset(self::$hooks[$name][$key]);
		}

		if ($permanent) {
			Db::$db->query(
				'DELETE FROM {db_prefix}hooks
				WHERE id_hook = {int:hook}',
				[
					'hook' => $id_hook,
				],
			);
		}
	}

	/**
	 * Find all instances of hooks related to the package id and remove them.
	 *
	 * @param string $package_id
	 */
	public static function uninstallPackage(string $package_id): void
	{
		foreach (self::$hooks as $name => &$hooks) {
			foreach ($hooks as $key => $calls) {
				if ($calls['package_id'] !== $package_id) {
					continue;
				}

				unset($hooks[$key]);
			}
		}

		Db::$db->query(
			'DELETE FROM {db_prefix}hooks
			WHERE package_id = {string:package_id}',
			[
				'package_id' => $package_id,
			],
		);
	}

	/**
	 * Adds a function or method to an integration hook.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * Does nothing if the function is already added.
	 * Cleans up enabled/disabled variants before taking requested action.
	 *
	 * @param string $name The complete hook name. Calls self::cleanHookName($name) to remote integrate_
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
		$name = self::cleanHookName($name);
		$hook = self::parseBcString($name, $function, $permanent);

		if ($permanent) {
			self::register($name, $hook['function'], $hook['file'], $hook['class'], $hook['is_object'], $hook['is_enabled']);
		} else {
			self::$hooks[$hook['hook_name']] ??= [];
			self::$hooks[$hook['hook_name']][] = $hook;

			// It is handy to be able to know which hooks are temporary...
			Utils::$context['integration_hooks_temporary'] ??= [];
			Utils::$context['integration_hooks_temporary'][$name][$function] = true;

			if (Config::$backward_compatibility) {
				self::updateModSettings($name, $function);
			}
		}
	}

	/**
	 * Removes an integration hook function.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * Removes the given function from the given hook.
	 * Does nothing if the function is not available.
	 * Cleans up enabled/disabled variants before taking requested action.
	 *
	 * @see IntegrationHook::add
	 *
	 * @param string $name The complete hook name. Calls self::cleanHookName($name) to remote integrate_
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
		$name = self::cleanHookName($name);
		$tmpHook = self::parseBcString($name, $function, $permanent);

		$key = array_find(self::$hooks[$name], function ($val) use ($tmpHook) {
			return
				$val['function'] === $tmpHook['function']
				&& $val['class'] === $tmpHook['class']
				&& $val['file'] === $tmpHook['file']
				&& $val['is_object'] === $val['is_object'];
		});

		if ($permanent) {
			self::unregister($name, self::$hooks[$name][$key]['id_hook']);
		} else {
			self::$hooks[$name] ??= [];
			unset(self::$hooks[$name][$key]);

			if (Config::$backward_compatibility) {
				self::updateModSettings($name, $function, true);
			}
		}
	}

	/**
	 * Calls to Config::updateModSettings get interupted and redirected here.
	 * Process the incoming string of data, break out existing matching hooks
	 * and then determine if we added or removed a hook.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * Note, A danger exists (since hooks where added in 2.x), that we assume
	 * modSettings has the hook data we want, but we also added temporary hooks
	 * and if a call to a permanent hook is made after, it could result in the
	 * being saved to the database.
	 *
	 * @param mixed $name Calls self::cleanHookName($name) to remote integrate_
	 * @param mixed $hooks
	 */
	final public static function processUpdateModSettings($name, $hooks): void
	{
		$name = self::cleanHookName($name);

		// This is easy.
		if (empty($hooks)) {
			Config::$modSettings[$name] = '';

			return;
		}

		$tmp_data = self::$hooks[$name];
		$tmp_hooks = explode(',', $hooks);

		foreach ($tmp_data as $key1 => $tmp) {
			$hook_string = self::buildBcString($tmp);

			$key2 = array_search($hook_string, $tmp_hooks);

			// We found the key, nothing to do.
			if ($key2 !== false) {
				unset($tmp_hooks[$key2], $tmp_data[$key1]);

			}
			// Hook needs deleted.
			else {
				self::remove($name, $hook_string);
			}
		}

		// If the hook still exists, it needs added.
		foreach ($tmp_hooks as $tmp) {
			self::add($name, $tmp);
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Receives a filename and tries to loads the file.
	 *
	 * You can use the following wildcards in the path:
	 *  - $boarddir
	 *  - $sourcedir
	 *  - $themedir (only works if SMF\Theme has already been initialized)
	 *
	 * @param string $file The string containing a valid format.
	 * @param bool $silent Should we be silent about the failure?
	 * @return bool False if we failed, true if we loaded.
	 */
	final protected static function loadFile(string $file, bool $silent = false): bool
	{
		if (empty($file)) {
			return false;
		}

		$path = strtr($file, [
			'$boarddir' => Config::$boarddir,
			'$sourcedir' => Config::$sourcedir,
		]);

		if (str_contains($path, '$themedir') && class_exists(Theme::class, false) && !empty(Theme::$current->settings['theme_dir'])) {
			$path = strtr($path, [
				'$themedir' => Theme::$current->settings['theme_dir'],
			]);
		}

		// Load the file if it can be loaded.
		if (is_file($path)) {
			require_once $path;
		}
		// No? Try a fallback to Config::$sourcedir.
		else {
			$path = Config::$sourcedir . '/' . $file;

			if (is_file($path)) {
				require_once $path;
			}
			// Sorry, can't do much for you at this point.
			elseif (empty(Utils::$context['uninstalling'])) {
				if (!$silent) {
					ErrorHandler::log(Lang::getTxt('hook_fail_loading_file', [$path], file: 'Errors'), 'general');
				}

				// File couldn't be loaded.
				return false;
			}
		}

		return true;
	}

	/**
	 * Parses the given input to determine and returns a compatible hook array.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * Two special syntaxes can be used with string input, as follows:
	 *
	 *  - Instructions to load a specific file can be given by prepending a file
	 *    path followed by a `|` character to the $input string. This amounts to
	 *    a form of autoloading for callables that are not class-based. These
	 *    file paths support the wildcards $boarddir, $sourcedir, and $themedir.
	 *
	 *    Example: '$sourcedir/foo.php|func_name' will load ./Sources/foo.php
	 *    and then return 'func_name'.
	 *
	 *  - If a class method is specified with a "#" character appended to it, an
	 *    instance of that class will be automatically created and added to
	 *    Utils::$context['instances'], and the returned value from this method
	 *    will be a callable array that will call the specified method on that
	 *    instance. Note, however, that there is no way to pass arguments to the
	 *    class's constructor when using this syntax. For that reason, it is
	 *    usually better to construct the object directly rather than using this
	 *    syntax to do the job for you.
	 *
	 *    Example: 'SMF\Foo::methodName#' will create an instance of SMF\Foo and
	 *    then return an array containing the instantiated object and the string
	 *    'methodName'.
	 *
	 * @param string $hook_name The name of the hook
	 * @param string|callable $function Function to parse as a callable.
	 * @param bool $permanent If true, updates the value in settings table.
	 * @return array Either a valid callable or false on failure.
	 */
	private static function parseBcString(string $hook_name, string $function, bool $permanent = true): array
	{
		$file = '';
		$class = '';

		if (str_contains($function, '|')) {
			[$file, $function] = explode('|', $function);
		}

		if (str_contains($function, '::')) {
			[$class, $function] = explode('::', $function);
		}

		$hook = [
			'id_hook' => rand(100000, 900000),
			'is_enabled' => !str_starts_with('!', $function) ? true : false,
			'hook_name' => self::cleanHookName($hook_name),
			'function' => $function,
			'file' => $file,
			'class' => $class,
			'is_object' => false,
			'package_id' => null,
			'is_temp' => !$permanent,
		];

		return $hook;
	}

	/**
	 * Builds a string compatible with SMF 2.1 hook system.
	 *
	 * @param array $hook Data from our hook system.
	 * @return string
	 */
	private static function buildBcString(array $hook): string
	{
		return
			(!empty($hook['is_enabled']) ? '!' : '')
			. (!empty($hook['file']) ? $hook['file'] . '|' : '')
			. (!empty($hook['class']) ? $hook['class'] . ':' : '')
			. ($hook['function'] ?? '')
			. (!empty($hook['is_object']) ? '#' : '');
	}

	/**
	 * Registers with modSettings our hook data.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * Note, A danger exists (since hooks where added in 2.x), that we assume
	 * modSettings has the hook data we want, but we also added temporary hooks
	 * and if a call to a permanent hook is made after, it could result in the
	 * being saved to the database.
	 *
	 * @param mixed $hook
	 */
	private static function updateModSettings(string $name, string $function, bool $remove = false)
	{
		$name = self::prepareLegacyName($name);
		Config::$modSettings[$name] ??= '';

		if ($remove) {
			$tmps = explode(',', Config::$modSettings[$name]);

			$key = array_search($function, $tmps);
			unset($tmps[$key]);
			Config::$modSettings[$name] = implode(',', $tmps);
		} else {
			Config::$modSettings[$name] .= (!empty(Config::$modSettings[$name]) ? ',' : '') . $function;
		}
	}

	/**
	 * Wrapper to remove integrate_ from the hook name.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * @param string $name
	 * @return string Name cleansed of integrate_
	 */
	private static function cleanHookName(string $name): string
	{
		return str_starts_with($name, 'integrate_') ? substr($name, 10) : $name;
	}

	/**
	 * Wrapper to prepare the right prefix of the legacy hook name.
	 *
	 * For use with SMF 2.1 compatbility layer.
	 *
	 * @param string $name
	 * @return string
	 */
	private static function prepareLegacyName(string $name): string
	{
		return \in_array($name, self::$no_integrate_names) ? $name : 'integrate_' . $name;
	}
}
