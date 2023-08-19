<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.2
 */

namespace SMF\Cache\APIs;

use Memcached;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class MemcachedImplementation extends CacheApi implements CacheApiInterface
{
	const CLASS_KEY = 'cache_memcached';

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
				{
					$server = explode(':', $server);
					return array($server[0], isset($server[1]) ? (int) $server[1] : 11211);
				}
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

		if (!in_array($txt[self::CLASS_KEY .'_settings'], $config_vars))
		{
			$config_vars[] = $txt[self::CLASS_KEY .'_settings'];
			$config_vars[] = array(
				self::CLASS_KEY,
				$txt[self::CLASS_KEY .'_servers'],
				'file',
				'text',
				0,
				'subtext' => $txt[self::CLASS_KEY .'_servers_subtext']);
		}

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		if (empty($context['settings_not_writable']))
			$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#'. self::CLASS_KEY .'").prop("disabled", cache_type != "MemcacheImplementation" && cache_type != "MemcachedImplementation");
			});';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		if (!is_object($this->memcached))
			return false;

		// This gets called in Subs-Admin getServerVersions when loading up support information.  If we can't get a connection, return nothing.
		$result = $this->memcached->getVersion();

		if (!empty($result))
			return current($result);

		return false;
	}
}

?>