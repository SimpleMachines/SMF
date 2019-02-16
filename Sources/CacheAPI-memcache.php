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
 * Our Cache API class
 *
 * @package cacheAPI
 */
class memcache_cache extends cache_api
{
	/**
	 * @var \Memcache The memcache instance.
	 */
	private $memcache = null;

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $cache_memcached;

		$supported = class_exists('memcache');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported && !empty($cache_memcached);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		global $db_persist, $cache_memcached;

		$servers = explode(',', $cache_memcached);
		$port = 0;

		// Don't try more times than we have servers!
		$connected = false;
		$level = 0;

		// We should keep trying if a server times out, but only for the amount of servers we have.
		while (!$connected && $level < count($servers))
		{
			++$level;
			$this->memcache = new Memcache();
			$server = trim($servers[array_rand($servers)]);

			// Normal host names do not contain slashes, while e.g. unix sockets do. Assume alternative transport pipe with port 0.
			if (strpos($server, '/') !== false)
				$host = $server;
			else
			{
				$server = explode(':', $server);
				$host = $server[0];
				$port = isset($server[1]) ? $server[1] : 11211;
			}

			// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
			if (empty($db_persist))
				$connected = $this->memcache->connect($host, $port);
			else
				$connected = $this->memcache->pconnect($host, $port);
		}

		return $connected;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		$value = $this->memcache->get($key);

		// $value should return either data or false (from failure, key not found or empty array).
		if ($value === false)
			return null;
		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		return $this->memcache->set($key, $value, 0, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function quit()
	{
		return $this->memcache->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$this->invalidateCache();
		return $this->memcache->flush();
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$config_vars[] = $txt['cache_memcache_settings'];
		$config_vars[] = array('cache_memcached', $txt['cache_memcache_servers'], 'file', 'text', 0, 'cache_memcached', 'postinput' => '<br><div class="smalltext"><em>' . $txt['cache_memcache_servers_subtext'] . '</em></div>');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cache_memcached").prop("disabled", cache_type != "memcache");
			});';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		return $this->memcache->getVersion();
	}
}

?>