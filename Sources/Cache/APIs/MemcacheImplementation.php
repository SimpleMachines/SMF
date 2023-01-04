<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Cache\APIs;

use Memcache;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class MemcacheImplementation extends CacheApi implements CacheApiInterface
{
	const CLASS_KEY = 'cache_memcached';

	/**
	 * @var object
	 *
	 * The Memcache instance.
	 */
	private $memcache = null;

	/**
	 * @var array
	 *
	 * Known Memcache servers.
	 */
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
				// Normal host names do not contain slashes, while e.g. unix sockets do. Assume alternative transport pipe with port 0.
				if (strpos($server, '/') !== false)
					return array($server, 0);

				else
				{
					$server = explode(':', $server);
					return array($server[0], isset($server[1]) ? (int) $server[1] : 11211);
				}
			},
			array_map('trim', explode(',', $cache_memcached))
		);

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists('Memcache');

		if ($test)
			return $supported;

		return parent::isSupported() && $supported && !empty($this->servers);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		global $db_persist;

		$this->memcache = new Memcache();

		// Don't try more times than we have servers!
		$connected = false;
		$level = 0;

		// We should keep trying if a server times out, but only for the amount of servers we have.
		while (!$connected && $level < count($this->servers))
		{
			++$level;

			$server = $this->servers[array_rand($this->servers)];

			// No server, can't connect to this.
			if (empty($server[0]))
				continue;

			$host = $server[0];
			$port = $server[1];

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

		return $this->memcache->set($key, $value, 0, $ttl !== null ? $ttl : $this->ttl);
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
		if (!is_object($this->memcache))
			return false;

		// This gets called in Subs-Admin getServerVersions when loading up support information.  If we can't get a connection, return nothing.
		$result = $this->memcache->getVersion();

		if (!empty($result))
			return $result;

		return false;
	}
}

?>