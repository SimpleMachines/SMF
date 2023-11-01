<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Cache;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Utils;

abstract class CacheApi
{
	use BackwardCompatibility;

	const APIS_FOLDER = __DIR__ . '/APIs';
	const APIS_NAMESPACE = __NAMESPACE__ . '\\APIs\\';
	const APIS_DEFAULT = 'FileBased';

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => 'loadCacheAccelerator',
			'detect' => 'loadCacheAPIs',
			'clean' => 'clean_cache',
			'quickGet' => 'cache_quick_get',
			'put' => 'cache_put_data',
			'get' => 'cache_get_data',
		),
		'prop_names' => array(
			'loadedApi' => 'cacheAPI',
			'hits' => 'cache_hits',
			'count_hits' => 'cache_count',
			'misses' => 'cache_misses',
			'count_misses' => 'cache_count_misses',
		),
	);

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var int
	 *
	 * Caching level. Values range from 0 to 3.
	 *
	 * This is an copy of the $cache_enable setting in Settings.php.
	 */
	public static int $enable;

	/**
	 * @var string
	 *
	 * Name of the selected cache engine.
	 *
	 * This is an copy of the $cache_accelerator setting in Settings.php.
	 */
	public static string $accelerator;

	/**
	 * @var object|bool
	 *
	 * The loaded cache API, or false on failure.
	 *
	 * For backward compatibilty, also referenced as global $cacheAPI.
	 */
	public static $loadedApi;

	/**
	 * @var array
	 *
	 * Records debugging info.
	 *
	 * For backward compatibilty, also referenced as global $cache_hits.
	 */
	public static array $hits = array();

	/**
	 * @var int
	 *
	 * The number of times the cache has been acceesed.
	 *
	 * For backward compatibilty, also referenced as global $cache_count.
	 */
	public static int $count_hits = 0;

	/**
	 * @var array
	 *
	 * Records debugging info.
	 *
	 * For backward compatibilty, also referenced as global $cache_misses.
	 */
	public static array $misses = array();

	/**
	 * @var int
	 *
	 * The number of times the cache has missed.
	 *
	 * For backward compatibilty, also referenced as global $cache_count_misses.
	 */
	public static int $count_misses = 0;

	/**********************
	 * Protected properties
	 **********************/

	/**
	 * @var string The maximum SMF version that this will work with.
	 */
	protected $version_compatible = '3.0.999';

	/**
	 * @var string The minimum SMF version that this will work with.
	 */
	protected $min_smf_version = '2.1 RC1';

	/**
	 * @var string The prefix for all keys.
	 */
	protected $prefix = '';

	/**
	 * @var int The default TTL.
	 */
	protected $ttl = 120;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does basic setup of a cache method when we create the object but before we call connect.
	 *
	 * @access public
	 */
	public function __construct()
	{
		$this->setPrefix();
	}

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @access public
	 * @param bool $test Test if this is supported or enabled.
	 * @return bool Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		if ($test)
			return true;

		return !empty(self::$enable);
	}

	/**
	 * Sets the cache prefix.
	 *
	 * @access public
	 * @param string $prefix The prefix to use.
	 *     If empty, the prefix will be generated automatically.
	 * @return bool If this was successful or not.
	 */
	public function setPrefix($prefix = '')
	{
		if (!is_string($prefix))
			$prefix = '';

		// Use the supplied prefix, if there is one.
		if (!empty($prefix))
		{
			$this->prefix = $prefix;

			return true;
		}

		// Ideally the prefix should reflect the last time the cache was reset.
		if (!empty(Config::$cachedir) && file_exists(Config::$cachedir . '/index.php'))
		{
			$mtime = filemtime(Config::$cachedir . '/index.php');
		}
		// Fall back to the last time that Settings.php was updated.
		elseif (!empty(Config::$boarddir) && file_exists(SMF_SETTINGS_FILE))
		{
			$mtime = filemtime(SMF_SETTINGS_FILE);
		}
		// This should never happen, but just in case...
		else
		{
			$mtime = filemtime(realpath($_SERVER['SCRIPT_FILENAME']));
		}

		$this->prefix = md5(Config::$boardurl . $mtime) . '-SMF-';

		return true;
	}

	/**
	 * Gets the prefix as defined from set or the default.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * Sets a default Time To Live, if this isn't specified we let the class define it.
	 *
	 * @access public
	 * @param int $ttl The default TTL
	 * @return bool If this was successful or not.
	 */
	public function setDefaultTTL($ttl = 120)
	{
		$this->ttl = $ttl;

		return true;
	}

	/**
	 * Gets the TTL as defined from set or the default.
	 *
	 * @access public
	 * @return int the value of $ttl.
	 */
	public function getDefaultTTL()
	{
		return $this->ttl;
	}

	/**
	 * Invalidate all cached data.
	 *
	 * @return bool Whether or not we could invalidate the cache.
	 */
	public function invalidateCache()
	{
		// Invalidate cache, to be sure!
		// ... as long as index.php can be modified, anyway.
		if (is_writable(Config::$cachedir . '/' . 'index.php'))
			@touch(Config::$cachedir . '/' . 'index.php');

		return true;
	}

	/**
	 * Closes connections to the cache method.
	 *
	 * @access public
	 * @return bool Whether the connections were closed.
	 */
	public function quit()
	{
		return true;
	}

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @access public
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 */
	public function cacheSettings(array &$config_vars)
	{
	}

	/**
	 * Gets the latest version of SMF this is compatible with.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getCompatibleVersion()
	{
		return $this->version_compatible;
	}

	/**
	 * Gets the min version that we support.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getMinimumVersion()
	{
		return $this->min_smf_version;
	}

	/**
	 * Gets the Version of the Caching API.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getVersion()
	{
		return $this->min_smf_version;
	}

	/**
	 * Run housekeeping of this cache
	 * exp. clean up old data or do optimization
	 *
	 * @access public
	 * @return void
	 */
	public function housekeeping()
	{
	}

	/**
	 * Gets the class identifier of the current caching API implementation.
	 *
	 * @access public
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName()
	{
		$class_name = get_class($this);

		if ($position = strrpos($class_name, '\\'))
			return substr($class_name, $position + 1);

		else
			return get_class($this);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Try to load up a supported caching method.
	 * This is saved in $loadedApi if we are not overriding it.
	 *
	 * @todo Add a reference to Utils::$context['instances'] as well?
	 *
	 * @param string $overrideCache Allows manually specifying a cache accelerator engine.
	 * @param bool $fallbackSMF Use the default SMF method if the accelerator fails.
	 * @return object|false An instance of a child class of this class, or false on failure.
	 */
	final public static function load($overrideCache = '', $fallbackSMF = true)
	{
		if (!isset(self::$enable))
			self::$enable = min(max((int) Config::$cache_enable, 0), 3);

		if (!isset(self::$accelerator))
			self::$accelerator = Config::$cache_accelerator;

		// Is caching enabled?
		if (empty(self::$enable) && empty($overrideCache))
			return false;

		// Not overriding this and we have a cacheAPI, send it back.
		if (empty($overrideCache) && is_object(self::$loadedApi))
			return self::$loadedApi;

		elseif (is_null(self::$loadedApi))
			self::$loadedApi = false;

		// What accelerator we are going to try.
		$cache_class_name = !empty(self::$accelerator) ? self::$accelerator : self::APIS_DEFAULT;
		$fully_qualified_class_name = !empty($overrideCache) ? $overrideCache :
			self::APIS_NAMESPACE . $cache_class_name;

		// Do some basic tests.
		$cache_api = false;
		if (class_exists($fully_qualified_class_name))
		{
			$cache_api = new $fully_qualified_class_name();

			// There are rules you know...
			if (!($cache_api instanceof CacheApiInterface) || !($cache_api instanceof CacheApi))
				$cache_api = false;

			// No Support?  NEXT!
			if ($cache_api && !$cache_api->isSupported())
			{
				// Can we save ourselves?
				if (!empty($fallbackSMF) && $overrideCache == '' && $cache_class_name !== self::APIS_DEFAULT)
					return self::load(self::APIS_NAMESPACE . self::APIS_DEFAULT, false);

				$cache_api = false;
			}

			// Connect up to the accelerator.
			if ($cache_api && $cache_api->connect() === false)
				$cache_api = false;

			// Don't set this if we are overriding the cache.
			if ($cache_api && empty($overrideCache))
				self::$loadedApi = $cache_api;
		}

		if (!$cache_api && !empty($fallbackSMF) && $overrideCache == '' && $cache_class_name !== self::APIS_DEFAULT)
			$cache_api = self::load(self::APIS_NAMESPACE . self::APIS_DEFAULT, false);

		return $cache_api;
	}

	/**
	 * Get the installed Cache API implementations.
	 */
	final public static function detect()
	{
		$loadedApis = array();

		$api_classes = new \GlobIterator(self::APIS_FOLDER . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_path => $file_info)
		{
			$class_name = $file_info->getBasename('.php');
			$fully_qualified_class_name = self::APIS_NAMESPACE . $class_name;

			if (!class_exists($fully_qualified_class_name))
				continue;

			/* @var CacheApiInterface $cache_api */
			$cache_api = new $fully_qualified_class_name();

			// Deal with it!
			if (!($cache_api instanceof CacheApiInterface) || !($cache_api instanceof CacheApi))
				continue;

			// No Support?  NEXT!
			if (!$cache_api->isSupported(true))
				continue;

			$loadedApis[$class_name] = $cache_api;
		}

		call_integration_hook('integrate_load_cache_apis', array(&$loadedApis));

		return $loadedApis;
	}

	/**
	 * Empty out the cache in use as best it can
	 *
	 * It may only remove the files of a certain type (if the $type parameter is given)
	 * Type can be user, data or left blank
	 * 	- user clears out user data
	 *  - data clears out system / opcode data
	 *  - If no type is specified will perform a complete cache clearing
	 * For cache engines that do not distinguish on types, a full cache flush will be done
	 *
	 * @param string $type The cache type ('memcached', 'zend' or something else for SMF's file cache)
	 */
	final public static function clean($type = '')
	{
		// If we can't get to the API, can't do this.
		if (empty(self::$loadedApi))
			return;

		// Ask the API to do the heavy lifting. cleanCache also calls invalidateCache to be sure.
		self::$loadedApi->cleanCache($type);

		call_integration_hook('integrate_clean_cache');

		clearstatcache();
	}

	/**
	 * Try to retrieve a cache entry. On failure, call the appropriate function.
	 *
	 * @param string $key The key for this entry
	 * @param string $file The file associated with this entry
	 * @param string $function The function to call
	 * @param array $params Parameters to be passed to the specified function
	 * @param int $level The cache level
	 * @return string The cached data
	 */
	final public static function quickGet($key, $file, $function, $params, $level = 1)
	{
		if (function_exists('call_integration_hook'))
			call_integration_hook('pre_cache_quick_get', array(&$key, &$file, &$function, &$params, &$level));

		/* Refresh the cache if either:
			1. Caching is disabled.
			2. The cache level isn't high enough.
			3. The item has not been cached or the cached item expired.
			4. The cached item has a custom expiration condition evaluating to true.
			5. The expire time set in the cache item has passed (needed for Zend).
		*/
		if (empty(self::$enable) || self::$enable < $level || !is_array($cache_block = self::get($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
		{
			if (!empty($file) && is_file(Config::$sourcedir . '/' . $file))
				require_once(Config::$sourcedir . '/' . $file);

			$cache_block = call_user_func_array($function, $params);

			if (!empty(self::$enable) && self::$enable >= $level)
				self::put($key, $cache_block, $cache_block['expires'] - time());
		}

		// Some cached data may need a freshening up after retrieval.
		if (!empty($cache_block['post_retri_eval']))
			eval($cache_block['post_retri_eval']);

		if (function_exists('call_integration_hook'))
			call_integration_hook('post_cache_quick_get', array(&$cache_block));

		return $cache_block['data'];
	}

	/**
	 * Puts value in the cache under key for ttl seconds.
	 *
	 * - It may "miss" so shouldn't be depended on
	 * - Uses the cache engine chosen in the ACP and saved in Settings.php
	 * - It supports:
	 *	 memcache: https://php.net/memcache
	 *   APCu: https://php.net/book.apcu
	 *	 Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
	 *	 Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
	 *
	 * @param string $key A key for this value
	 * @param mixed $value The data to cache
	 * @param int $ttl How long (in seconds) the data should be cached for
	 */
	final public static function put($key, $value, $ttl = 120)
	{
		if (empty(self::$enable) || empty(self::$loadedApi))
			return;

		self::$count_hits++;
		if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
		{
			self::$hits[self::$count_hits] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(Utils::jsonEncode($value)));
			$st = microtime(true);
		}

		// The API will handle the rest.
		$value = $value === null ? null : Utils::jsonEncode($value);
		self::$loadedApi->putData($key, $value, $ttl);

		if (function_exists('call_integration_hook'))
			call_integration_hook('cache_put_data', array(&$key, &$value, &$ttl));

		if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
			self::$hits[self::$count_hits]['t'] = microtime(true) - $st;
	}

	/**
	 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
	 * - It may often "miss", so shouldn't be depended on.
	 * - It supports the same as self::put().
	 *
	 * @param string $key The key for the value to retrieve
	 * @param int $ttl The maximum age of the cached data
	 * @return array|null The cached data or null if nothing was loaded
	 */
	final public static function get($key, $ttl = 120)
	{
		if (empty(self::$enable) || empty(self::$loadedApi))
			return null;

		self::$count_hits++;
		if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
		{
			self::$hits[self::$count_hits] = array('k' => $key, 'd' => 'get');
			$st = microtime(true);
			$original_key = $key;
		}

		// Ask the API to get the data.
		$value = self::$loadedApi->getData($key, $ttl);

		if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
		{
			self::$hits[self::$count_hits]['t'] = microtime(true) - $st;
			self::$hits[self::$count_hits]['s'] = isset($value) ? strlen($value) : 0;

			if (empty($value))
			{
				self::$count_misses++;
				self::$misses[self::$count_misses] = array('k' => $original_key, 'd' => 'get');
			}
		}

		if (function_exists('call_integration_hook') && isset($value))
			call_integration_hook('cache_get_data', array(&$key, &$ttl, &$value));

		return empty($value) ? null : Utils::jsonDecode($value, true);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\CacheApi::exportStatic'))
	CacheApi::exportStatic();

?>