<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
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
class apc_cache extends cache_api
{
	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $cache_memcached;

		$supported = function_exists('apc_fetch') && function_exists('apc_store');

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

		return apc_fetch($key . 'smf');
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			return apc_delete($key . 'smf');
		else
			return apc_store($key . 'smf', $value, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		// if passed a type, clear that type out
		if ($type === '' || $type === 'data')
		{
			// Always returns true.
			apc_clear_cache('user');
			apc_clear_cache('system');
		}
		elseif ($type === 'user')
			apc_clear_cache('user');

		$this->invalidateCache();
		return true;
	}
}