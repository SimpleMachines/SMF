<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class RedisImplementation extends CacheApi implements CacheApiInterface
{
	const PORT = 6379;

	/**
	 * @var \Redis The redis instance.
	 */
	private $redis;

	/**
	 * @var int.
	 */
	private $server;

	/**
	 * @var string.
	 */
	private $port = self::PORT;

	/**
	 * @var false|resource
	 */
	private $socket_connection;

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		global $cache_redis;

		$supported = !empty($cache_redis);

		if ($test)
			return $supported;

		return parent::isSupported() && $supported;
	}

	public function connect()
	{
		global $cache_redis;

		list($host, $port) = explode(':', $cache_redis);

		$socket_connection = fsockopen($host, $port);

		$this->socket_connection = !$socket_connection ? false : $socket_connection;
	}

	/**
	 * @inheritDoc
	 */
	public function getData($key, $ttl = null)
	{
		// TODO: Implement getData() method.
	}

	/**
	 * @inheritDoc
	 */
	public function putData($key, $value, $ttl = null)
	{
		// TODO: Implement putData() method.
	}

	/**
	 * @inheritDoc
	 */
	public function cleanCache($type = '')
	{
		// TODO: Implement cleanCache() method.
	}
}

?>