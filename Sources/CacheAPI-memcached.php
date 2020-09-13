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

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Our Cache API class
 *
 * @package cacheAPI
 */
class memcached_cache extends cache_api
{
	/** @var Memcached The memcache instance. */
	private $memcached = null;

	/** @var string[] */
	private $servers;

	/**
	 * {@inheritDoc}
	 */
	public function __construct()
	{
		global $cache_memcached;

		$this->servers = array_map(
			function($server)
			{
				if (strpos($server, '/') !== false)
					return array($server, 0);
				else
					return array($server, isset($server[1]) ? $server[1] : 11211);
			},
			explode(',', $cache_memcached)
		);

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $cache_memcached;

		$supported = class_exists('Memcached');

		if ($test)
			return $supported;
		return parent::isSupported() && $supported && !empty($cache_memcached);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		$this->memcached = new Memcached;

		return $this->addServers();
	}

	/**
	 * Add memcached servers.
	 *
	 * Don't add servers if they already exist. Ideal for persistent connections.
	 *
	 * @return bool True if there are servers in the daemon, false if not.
	 */
	protected function addServers()
	{
		// memcached does not remove servers from the list upon completing the
		// script under modes like FastCGI. So check to see if servers exist or not.
		$currentServers = $this->memcached->getServerList();
		$retVal = !empty($currentServers);
		foreach ($this->servers as $server)
		{
			// Figure out if we have this server or not
			$foundServer = false;
			foreach ($currentServers as $currentServer)
			{
				if ($server[0] == $currentServer['host'] && $server[1] == $currentServer['port'])
				{
					$foundServer = true;
					break;
				}
			}

			// Found it?
			if (empty($foundServer))
				$retVal |= $this->memcached->addServer($server[0], $server[1]);
		}

		return $retVal;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		$value = $this->memcached->get($key);

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

		return $this->memcached->set($key, $value, $ttl !== null ? $ttl : $this->ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$this->invalidateCache();

		// Memcached accepts a delay parameter, always use 0 (instant).
		return $this->memcached->flush(0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function quit()
	{
		return $this->memcached->quit();
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
				$("#cache_memcached").prop("disabled", cache_type != "memcached");
			});';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		return current($this->memcached->getVersion());
	}
}

?>