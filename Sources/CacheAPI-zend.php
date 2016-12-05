<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2016 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Our Cache API class
 * @package cacheAPI
 */
class zend_cache extends cache_api
{
	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @access public
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		global $cache_memcached;

		$supported = function_exists('zend_shm_cache_fetch') || function_exists('output_cache_get');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported;
	}

	/**
	 * Connects to the cache method. If this fails, we return false, otherwise we return true.
	 *
	 * @access public
	 * @return boolean Whether or not the cache method was connected to.
	 */
	public function connect()
	{
		// No need to do anything here.
		return true;
	}

	/**
	 * Gets data from the cache.
	 *
	 * @access public
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param string $ttl Overrides the default TTL.
	 * @return mixed The result from the cache, if there is no data or it is invalid, we return null.
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->keyPrefix . strtr($key, ':/', '-_');

		// Zend's pricey stuff.
		if (function_exists('zend_shm_cache_fetch'))
			return zend_shm_cache_fetch('SMF::' . $key);
		elseif (function_exists('output_cache_get'))
			return output_cache_get($key, $ttl);
	}

	/**
	 * Saves to data the cache.
	 *
	 * @access public
	 * @param string $key The key to use, the prefix is applied to the key name.
	 * @param mixed $value The data we wish to save.
	 * @param string $ttl Overrides the default TTL.
	 * @return bool Whether or not we could save this to the cache.
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->keyPrefix . strtr($key, ':/', '-_');

		if (function_exists('zend_shm_cache_store'))
			return zend_shm_cache_store('SMF::' . $key, $value, $ttl);
		elseif (function_exists('output_cache_put'))
			return output_cache_put($key, $value);
	}

	/**
	 * Closes connections to the cache method.
	 *
	 * @access public
	 * @return bool Whether or not we could close connections.
	 */
	public function quit()
	{
		return true;
	}
}