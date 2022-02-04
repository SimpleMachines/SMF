<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

namespace SMF\Cache;

if (!defined('SMF'))
	die('No direct access...');


interface CacheApiInterface
{
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
	 * Gets the class identifier of the current caching API implementation.
	 *
	 * @access public
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName();
}

?>