<?php

/**
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
	die('Hacking attempt...');

/**
 * Interface cache_api_interface
 */
interface cache_api_interface
{
	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @access public
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false);

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @access public
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect();

	/**
	 * Overrides the default prefix. If left alone, this will use the default key defined in the class.
	 *
	 * @access public
	 * @param string $key The key to use
	 * @return boolean If this was successful or not.
	 */
	public function setPrefix($key = '');

	/**
	 * Gets the prefix as defined from set or the default.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getPrefix();

	/**
	 * Sets a default Time To Live, if this isn't specified we let the class define it.
	 *
	 * @access public
	 * @param int $ttl The default TTL
	 * @return boolean If this was successful or not.
	 */
	public function setDefaultTTL($ttl = 120);

	/**
	 * Gets the TTL as defined from set or the default.
	 *
	 * @access public
	 * @return string the value of $ttl.
	 */
	public function getDefaultTTL();

	/**
	 * Gets data from the cache.
	 *
	 * @access public
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param string $ttl Overrides the default TTL.
	 * @return mixed The result from the cache, if there is no data or it is invalid, we return null.
	 */
	public function getData($key, $ttl = null);

	/**
	 * Saves to data the cache.
	 *
	 * @access public
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param mixed $value The data we wish to save.
	 * @param string $ttl Overrides the default TTL.
	 * @return bool Whether or not we could save this to the cache.
	 */
	public function putData($key, $value, $ttl = null);

	/**
	 * Clean out the cache.
	 *
	 * @param string $type If supported, the type of cache to clear, blank/data or user.
	 * @return bool Whether or not we could clean the cache.
	 */
	public function cleanCache($type = '');

	/**
	 * Invalidate all cached data.
	 *
	 * @return bool Whether or not we could invalidate the cache.
	 */
	public function invalidateCache();

	/**
	 * Closes connections to the cache method.
	 *
	 * @access public
	 * @return bool Whether or not we could close connections.
	 */
	public function quit();

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @access public
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 * @return void No return is needed.
	 */
	public function cacheSettings(array &$config_vars);

	/**
	 * Gets the latest version of SMF this is compatible with.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getCompatibleVersion();

	/**
	 * Gets the min version that we support.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getMiniumnVersion();

	/**
	 * Gets the Version of the Caching API.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getVersion();

	/**
	 * Run housekeeping of this cache
	 * exp. clean up old data or do optimization
	 *
	 * @access public
	 * @return void
	 */
	public function housekeeping();
}

/**
 * Class cache_api
 */
abstract class cache_api implements cache_api_interface
{
	/**
	 * @var string The last version of SMF that this was tested on. Helps protect against API changes.
	 */
	protected $version_compatible = 'SMF 2.1 RC1';

	/**
	 * @var string The minimum SMF version that this will work with
	 */
	protected $min_smf_version = 'SMF 2.1 RC1';

	/**
	 * @var string The prefix for all keys.
	 */
	protected $prefix = '';

	/**
	 * @var int The default TTL.
	 */
	protected $ttl = 120;

	/**
	 * Does basic setup of a cache method when we create the object but before we call connect.
	 *
	 * @access public
	 */
	public function __construct()
	{
		$this->setPrefix('');
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $cache_enable;

		if ($test)
			return true;
		return !empty($cache_enable);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function setPrefix($prefix = '')
	{
		global $boardurl, $cachedir;

		// Find a valid good file to do mtime checks on.
		if (file_exists($cachedir . '/' . 'index.php'))
			$filemtime = $cachedir . '/' . 'index.php';
		elseif (is_dir($cachedir . '/'))
			$filemtime = $cachedir . '/';
		else
			$filemtime = $boardurl . '/index.php';

		// Set the default if no prefix was specified.
		if (empty($prefix))
			$this->prefix = md5($boardurl . filemtime($filemtime)) . '-SMF-';
		else
			$this->prefix = $prefix;

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setDefaultTTL($ttl = 120)
	{
		$this->ttl = $ttl;

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultTTL()
	{
		return $this->ttl;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
	}

	/**
	 * Invalidate all cached data.
	 *
	 * @return bool Whether or not we could invalidate the cache.
	 */
	public function invalidateCache()
	{
		global $cachedir;

		// Invalidate cache, to be sure!
		// ... as long as index.php can be modified, anyway.
		if (is_writable($cachedir . '/' . 'index.php'))
			@touch($cachedir . '/' . 'index.php');

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function quit()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCompatibleVersion()
	{
		return $this->version_compatible;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMiniumnVersion()
	{
		return $this->min_smf_version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		return $this->min_smf_version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function housekeeping()
	{
	}
}

?>