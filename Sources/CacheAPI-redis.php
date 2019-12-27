<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Our Cache API class
 *
 * @package cacheAPI
 */
class redis_cache extends cache_api
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
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists('Redis');

		if ($test)
			return $supported;

		return parent::isSupported() && $supported;
	}

	public function connect()
	{

	}

	/**
	 * @param Redis $redis
	 * @return redis_cache
	 */
	public function setRedis($redis)
	{
		$this->redis = $redis;

		return $this;
	}

	/**
	 * @param string $server
	 * @return redis_cache
	 */
	public function setServer($server)
	{
		$this->server = $server;

		return $this;
	}

	/**
	 * @param int $port
	 * @return redis_cache
	 */
	public function setPort($port)
	{
		$this->port = $port;

		return $this;
	}
}

?>