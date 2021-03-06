<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2021 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

namespace SMF\Cache;

if (!defined('SMF'))
	die('No direct access...');

abstract class CacheApi
{
	const APIS_FOLDER = 'APIs';
	const APIS_NAMESPACE = 'SMF\Cache\APIs\\';
	const APIS_DEFAULT = 'FileBased';

	/**
	 * @var string The maximum SMF version that this will work with.
	 */
	protected $version_compatible = '2.1.999';

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
		global $cache_enable;

		if ($test)
			return true;

		return !empty($cache_enable);
	}

	/**
	 * Overrides the default prefix. If left alone, this will use the default key defined in the class.
	 *
	 * @access public
	 * @param string $key The key to use
	 * @return bool If this was successful or not.
	 */
	public function setPrefix($key = '')
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
		global $cachedir;

		// Invalidate cache, to be sure!
		// ... as long as index.php can be modified, anyway.
		if (is_writable($cachedir . '/' . 'index.php'))
			@touch($cachedir . '/' . 'index.php');

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
}

?>