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

namespace SMF\Cache\APIs;

use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
    die('No direct access...');

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class Apc extends CacheApi implements CacheApiInterface
{
    public function connect(){}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
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

		$value = apc_fetch($key . 'smf');

		return !empty($value) ? $value : null;
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
			return apc_store($key . 'smf', $value, $ttl !== null ? $ttl : $this->ttl);
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

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		return phpversion('apc');
	}
}

?>