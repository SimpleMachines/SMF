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
class smf_cache extends cache_api
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
		global $cachedir;

		$supported = is_writable($cachedir);

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
//		$this->setPrefix('');

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
		global $cachedir;

		$key = $this->keyPrefix . strtr($key, ':/', '-_');

		// SMF Data returns $value and $expired.  $expired has a unix timestamp of when this expires.
		if (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
		{
			// Work around Zend's opcode caching (PHP 5.5+), they would cache older files for a couple of seconds
			// causing newer files to take effect a while later.
			if (function_exists('opcache_invalidate'))
				opcache_invalidate($cachedir . '/data_' . $key . '.php', true);

			if (function_exists('apc_delete_file'))
				@apc_delete_file($cachedir . '/data_' . $key . '.php');

			// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
			@include($cachedir . '/data_' . $key . '.php');
			if (!empty($expired) && isset($value))
			{
				@unlink($cachedir . '/data_' . $key . '.php');
				unset($value);
			}
		}

		return !empty($value) ? $value : null;
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
		global $cachedir;

		$key = $this->keyPrefix . strtr($key, ':/', '-_');

		// Work around Zend's opcode caching (PHP 5.5+), they would cache older files for a couple of seconds
		// causing newer files to take effect a while later.
		if (function_exists('opcache_invalidate'))
			opcache_invalidate($cachedir . '/data_' . $key . '.php', true);

		if (function_exists('apc_delete_file'))
			@apc_delete_file($cachedir . '/data_' . $key . '.php');

		// Otherwise custom cache?
		if ($value === null)
			@unlink($cachedir . '/data_' . $key . '.php');
		else
		{
			$cache_data = '<' . '?' . 'php if (!defined(\'SMF\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, '\\\'') . '\';}' . '?' . '>';

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			$fileSize = file_put_contents($cachedir . '/data_' . $key . '.php', $cache_data, LOCK_EX);
			if ($fileSize !== strlen($cache_data))
			{
				@unlink($cachedir . '/data_' . $key . '.php');
				return false;
			}
			else
				return true;
		}
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

	/**
	 * Specify custom settings that the cache API supports.
	 *
	 * @access public
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 * @return void No return is needed.
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$config_vars[] = $txt['cache_smf'];
		$config_vars[] = array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir").prop("disabled", cache_type != "smf");
			});';
	}
}