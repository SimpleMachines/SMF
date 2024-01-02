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

use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF')) {
	die('No direct access...');
}

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class Zend extends CacheApi implements CacheApiInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function isSupported(bool $test = false): bool
	{
		$supported = function_exists('zend_shm_cache_fetch') || function_exists('output_cache_get');

		if ($test) {
			return $supported;
		}

		return parent::isSupported() && $supported;
	}

	public function connect(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData(string $key, ?int $ttl = null): mixed
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		// Zend's pricey stuff.
		if (function_exists('zend_shm_cache_fetch')) {
			return zend_shm_cache_fetch('SMF::' . $key);
		}

		if (function_exists('output_cache_get')) {
			return output_cache_get($key, $ttl);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData(string $key, mixed $value, ?int $ttl = null): mixed
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		if (function_exists('zend_shm_cache_store')) {
			return zend_shm_cache_store('SMF::' . $key, $value, $ttl);
		}

		if (function_exists('output_cache_put')) {
			return output_cache_put($key, $value);
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = ''): bool
	{
		$this->invalidateCache();

		return zend_shm_cache_clear('SMF');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion(): string|bool
	{
		return zend_version();
	}
}

?>