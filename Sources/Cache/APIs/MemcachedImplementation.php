<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Cache\APIs;

use Memcached;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class MemcachedImplementation extends CacheApi implements CacheApiInterface
{
	public const CLASS_KEY = 'cache_memcached';

	/**
	 * @var object
	 *
	 * The Memcache instance.
	 */
	private $memcached = null;

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
		$this->servers = array_map(
			function ($server) {
				// Normal host names do not contain slashes, while e.g. unix sockets do. Assume alternative transport pipe with port 0.
				if (strpos($server, '/') !== false) {
					return [$server, 0];
				}

				$server = explode(':', $server);

				return [$server[0], isset($server[1]) ? (int) $server[1] : 11211];
			},
			explode(',', Config::$cache_memcached),
		);

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported(bool $test = false): bool
	{
		$supported = class_exists('Memcached');

		if ($test) {
			return $supported;
		}

		return parent::isSupported() && $supported && !empty($this->servers);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): bool
	{
		$this->memcached = new Memcached();

		return $this->addServers();
	}

	/**
	 * Add memcached servers.
	 *
	 * Don't add servers if they already exist. Ideal for persistent connections.
	 *
	 * @return bool True if there are servers in the daemon, false if not.
	 */
	protected function addServers(): bool
	{
		$currentServers = $this->memcached->getServerList();
		$retVal = !empty($currentServers);

		foreach ($this->servers as $server) {
			// Figure out if we have this server or not
			$foundServer = false;

			foreach ($currentServers as $currentServer) {
				if ($server[0] == $currentServer['host'] && $server[1] == $currentServer['port']) {
					$foundServer = true;
					break;
				}
			}

			// Found it?
			if (empty($foundServer)) {
				$retVal |= $this->memcached->addServer($server[0], $server[1]);
			}
		}

		return $retVal;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData(string $key, ?int $ttl = null): mixed
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		$value = $this->memcached->get($key);

		// $value should return either data or false (from failure, key not found or empty array).
		if ($value === false) {
			return null;
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData(string $key, mixed $value, ?int $ttl = null): mixed
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		return $this->memcached->set($key, $value, $ttl !== null ? $ttl : $this->ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = ''): bool
	{
		$this->invalidateCache();

		// Memcached accepts a delay parameter, always use 0 (instant).
		return $this->memcached->flush(0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function quit(): bool
	{
		return $this->memcached->quit();
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars): void
	{
		if (!in_array(Lang::$txt[self::CLASS_KEY . '_settings'], $config_vars)) {
			$config_vars[] = Lang::$txt[self::CLASS_KEY . '_settings'];
			$config_vars[] = [
				self::CLASS_KEY,
				Lang::$txt[self::CLASS_KEY . '_servers'],
				'file',
				'text',
				0,
				'subtext' => Lang::$txt[self::CLASS_KEY . '_servers_subtext']];
		}

		if (!isset(Utils::$context['settings_post_javascript'])) {
			Utils::$context['settings_post_javascript'] = '';
		}

		if (empty(Utils::$context['settings_not_writable'])) {
			Utils::$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#' . self::CLASS_KEY . '").prop("disabled", cache_type != "MemcacheImplementation" && cache_type != "MemcachedImplementation");
			});';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion(): string|bool
	{
		if (!is_object($this->memcached)) {
			return false;
		}

		// This gets called in Subs-Admin getServerVersions when loading up support information.  If we can't get a connection, return nothing.
		$result = $this->memcached->getVersion();

		if (!empty($result)) {
			return current($result);
		}

		return false;
	}
}

?>