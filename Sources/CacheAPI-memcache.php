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
class memcache_cache extends cache_api
{
	/**
	 * @var string The memcache instance.
	 */
	private $memcache = null;

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

		$supported = class_exists('memcache');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported && !empty($cache_memcached);
	}

	/**
	 * Connects to the cache method. If this fails, we return false, otherwise we return true.
	 *
	 * @access public
	 * @return boolean Whether or not the cache method was connected to.
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
			if (strpos($server,'/') !== false)
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

		$value = $this->memcache->get($key);

		// $value should return either data or false (from failure, key not found or empty array).
		if ($value === false)
			return null;
		return $value;
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

		return $this->memcache->set($key, $value, 0, $ttl);
	}

	/**
	 * Closes connections to the cache method.
	 *
	 * @access public
	 * @return bool Whether or not we could close connections.
	 */
	public function quit()
	{
		return $this->memcache->close();
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

		$config_vars[] = $txt['cache_memcache'];
		$config_vars[] = array('cache_memcached', $txt['cache_memcache_servers'], 'file', 'text', 0, 'cache_memcached', 'postinput' => '<br /><div class="smalltext"><em>' . $txt['cache_memcache_servers_subtext'] . '</em></div>');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cache_memcached").prop("disabled", cache_type != "memcache");
			});';
	}
}