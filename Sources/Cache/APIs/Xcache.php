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
class Xcache extends CacheApi implements CacheApiInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function __construct()
	{
		parent::__construct();

		$this->connect();
	}

	/**
	 * @inheritDoc
	 */
	public function connect()
	{
		global $modSettings;

		// Xcache requires a admin username and password in order to issue a clear.
		if (!empty($modSettings['xcache_adminuser']) && !empty($modSettings['xcache_adminpass']))
		{
			ini_set('xcache.admin.user', $modSettings['xcache_adminuser']);
			ini_set('xcache.admin.pass', md5($modSettings['xcache_adminpass']));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = function_exists('xcache_get') && function_exists('xcache_set') && ini_get('xcache.var_size') > 0;

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

		return xcache_get($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		if ($value === null)
			return xcache_unset($key);

		else
			return xcache_set($key, $value, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		global $modSettings;

		// Xcache requuires a admin username and password in order to issue a clear. Ideally this would log an error, but it seems like something that could fill up the error log quickly.
		if (empty($modSettings['xcache_adminuser']) || empty($modSettings['xcache_adminpass']))
		{
			// We are going to at least invalidate it.
			$this->invalidateCache();
			return false;
		}

		// if passed a type, clear that type out
		if ($type === '' || $type === 'user')
			xcache_clear_cache(XC_TYPE_VAR, 0);

		if ($type === '' || $type === 'data')
			xcache_clear_cache(XC_TYPE_PHP, 0);

		$this->invalidateCache();

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$class_key = $this->getImplementationClassKeyName();

		$config_vars[] = $txt['cache_'. $class_key .'_settings'];
		$config_vars[] = array($class_key .'_adminuser', $txt['cache_'. $class_key .'_adminuser'], 'db', 'text', 0, $class_key .'_adminuser');

		// While we could md5 this when saving, this could be tricky to be sure it doesn't get corrupted on additional saves.
		$config_vars[] = array($class_key .'_adminpass', $txt['cache_'. $class_key .'_adminpass'], 'db', 'text', 0);

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#'. $class_key .'_adminuser").prop("disabled", cache_type != "'. $class_key .'");
				$("#'. $class_key .'_adminpass").prop("disabled", cache_type != "'. $class_key .'");
			});';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		return XCACHE_VERSION;
	}
}

?>