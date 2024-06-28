<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Sources\Cache\APIs;

use SMF\Sources\Cache\CacheApi;
use SMF\Sources\Cache\CacheApiInterface;

if (!defined('SMF')) {
	die('No direct access...');
}

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class Apcu extends CacheApi implements CacheApiInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function isSupported(bool $test = false): bool
	{
		$supported = function_exists('apcu_fetch') && function_exists('apcu_store');

		if ($test) {
			return $supported;
		}

		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
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

		$value = apcu_fetch($key . 'smf');

		return !empty($value) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData(string $key, mixed $value, ?int $ttl = null): mixed
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		// An extended key is needed to counteract a bug in APC.
		if ($value === null) {
			return apcu_delete($key . 'smf');
		}

		return apcu_store($key . 'smf', $value, $ttl !== null ? $ttl : $this->ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = ''): bool
	{
		$this->invalidateCache();

		return apcu_clear_cache();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion(): string|bool
	{
		return phpversion('apcu');
	}
}

?>