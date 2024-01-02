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

use Memcache;
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
class MemcacheImplementation extends CacheApi implements CacheApiInterface
{
	public const CLASS_KEY = 'cache_memcached';

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
		$this->servers = array_map(
			function ($server) {
				// Normal host names do not contain slashes, while e.g. unix sockets do. Assume alternative transport pipe with port 0.
				if (strpos($server, '/') !== false) {
					return [$server, 0];
				}

				$server = explode(':', $server);

				return [$server[0], isset($server[1]) ? (int) $server[1] : 11211];
			},
			array_map('trim', explode(',', Config::$cache_memcached)),
		);

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported(bool $test = false): bool
	{
		$supported = class_exists('Memcache');

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
		$this->memcache = new Memcache();

		// Don't try more times than we have servers!
		$connected = false;
		$level = 0;

		// We should keep trying if a server times out, but only for the amount of servers we have.
		while (!$connected && $level < count($this->servers)) {
			++$level;

			$server = $this->servers[array_rand($this->servers)];

			// No server, can't connect to this.
			if (empty($server[0])) {
				continue;
			}

			$host = $server[0];
			$port = $server[1];

			// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
			if (empty(Config::$db_persist)) {
				$connected = $this->memcache->connect($host, $port);
			} else {
				$connected = $this->memcache->pconnect($host, $port);
			}
		}

		return $connected;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData(string $key, ?int $ttl = null): mixed
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		$value = $this->memcache->get($key);

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

		return $this->memcache->set($key, $value, 0, $ttl !== null ? $ttl : $this->ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function quit(): bool
	{
		return $this->memcache->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = ''): bool
	{
		$this->invalidateCache();

		return $this->memcache->flush();
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
		if (!is_object($this->memcache)) {
			return false;
		}

		// This gets called in Subs-Admin getServerVersions when loading up support information.  If we can't get a connection, return nothing.
		$result = $this->memcache->getVersion();

		if (!empty($result)) {
			return $result;
		}

		return false;
	}
}

?>