<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

namespace SMF\Cache;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Interface cache_api_interface
 */
interface cache_api_interface
{
    const APIS_FOLDER = 'APIs';
	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @access public
	 * @param bool $test Test if this is supported or enabled.
	 * @return bool Whether or not the cache is supported
	 */
	public function isSupported($test = false);

	/**
	 * Connects to the cache method. This defines our $key. If this fails, we return false, otherwise we return true.
	 *
	 * @access public
	 * @return bool Whether or not the cache method was connected to.
	 */
	public function connect();

	/**
	 * Overrides the default prefix. If left alone, this will use the default key defined in the class.
	 *
	 * @access public
	 * @param string $key The key to use
	 * @return bool If this was successful or not.
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
	 * @return bool If this was successful or not.
	 */
	public function setDefaultTTL($ttl = 120);

	/**
	 * Gets the TTL as defined from set or the default.
	 *
	 * @access public
	 * @return int the value of $ttl.
	 */
	public function getDefaultTTL();

	/**
	 * Retrieves an item from the cache.
	 *
	 * @access public
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param int    $ttl Overrides the default TTL. Not really used anymore,
	 *                    but is kept for backwards compatibility.
	 * @return mixed The result from the cache, if there is no data or it is invalid, we return null.
	 * @todo Seperate existence checking into its own method
	 */
	public function getData($key, $ttl = null);

	/**
	 * Stores a value, regardless of whether or not the key already exists (in
	 * which case it will overwrite the existing value for that key).
	 *
	 * @access public
	 * @param string $key   The key to use, the prefix is applied to the key name.
	 * @param mixed  $value The data we wish to save. Use null to delete.
	 * @param int    $ttl   How long (in seconds) the data should be cached for.
	 *                      The default TTL will be used if this is null.
	 * @return bool Whether or not we could save this to the cache.
	 * @todo Seperate deletion into its own method
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
	 * @return bool Whether the connections were closed.
	 */
	public function quit();

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @access public
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
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
	public function getMinimumVersion();

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

?>