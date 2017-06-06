<?php

/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
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
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		// Zend's pricey stuff.
		if (function_exists('zend_shm_cache_fetch'))
			return zend_shm_cache_fetch('SMF::' . $key);
		elseif (function_exists('output_cache_get'))
			return output_cache_get($key, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		if (function_exists('zend_shm_cache_store'))
			return zend_shm_cache_store('SMF::' . $key, $value, $ttl);
		elseif (function_exists('output_cache_put'))
			return output_cache_put($key, $value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$this->invalidateCache();

		return zend_shm_cache_clear('SMF');
	}
}

?>