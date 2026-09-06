<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Cache\APIs;

use FilesystemIterator;
use GlobIterator;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!\defined('SMF')) {
	die('No direct access...');
}

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class FileBased extends CacheApi implements CacheApiInterface
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string The path to the current directory.
	 */
	private $cachedir = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();
	}

	/**
	 *
	 */
	public function isSupported(bool $test = false): bool
	{
		$supported = is_writable($this->cachedir);

		if ($test) {
			return $supported;
		}

		return parent::isSupported() && $supported;
	}

	/**
	 *
	 */
	public function connect(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function getData(string $key, ?int $ttl = null): mixed
	{
		$file = \sprintf(
			'%s/data_%s.cache',
			$this->cachedir,
			$this->prefix . strtr($key, ':/', '-_'),
		);

		// SMF Data returns $value and $expired.  $expired has a unix timestamp of when this expires.
		if (file_exists($file) && ($raw = $this->readFile($file)) !== false) {
			if (($value = Utils::jsonDecode($raw, false, 512, 0, false)) !== null && isset($value->expiration) && $value->expiration >= time()) {
				return $value->value;
			}

			@unlink($file);
		}

		return null;
	}

	/**
	 *
	 */
	public function putData(string $key, mixed $value, ?int $ttl = null): mixed
	{
		$file = \sprintf(
			'%s/data_%s.cache',
			$this->cachedir,
			$this->prefix . strtr($key, ':/', '-_'),
		);
		$ttl = $ttl !== null ? $ttl : $this->ttl;

		if ($value === null) {
			@unlink($file);

			return true;
		}
		$cache_data = json_encode(
			[
				'expiration' => time() + $ttl,
				'value' => $value,
			],
			JSON_NUMERIC_CHECK,
		);

		// Anything json_encode cannot represent, such as a string that is not
		// valid UTF-8, simply does not get cached.
		if ($cache_data === false) {
			@unlink($file);

			return false;
		}

		// Write out the cache file, check that the cache write was successful; all the data must be written
		// If it fails due to low diskspace, or other, remove the cache file
		if ($this->writeFile($file, $cache_data) !== \strlen($cache_data)) {
			@unlink($file);

			return false;
		}

		return true;

	}

	/**
	 *
	 */
	public function cleanCache($type = ''): bool
	{
		// No directory = no game.
		if (!is_dir($this->cachedir)) {
			return false;
		}

		// Remove the files in SMF's own disk cache, if any
		$files = new GlobIterator($this->cachedir . '/' . $type . '*.cache', FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($files as $file => $info) {
			unlink($this->cachedir . '/' . $file);
		}

		// Make this invalid.
		$this->invalidateCache();

		return true;
	}

	/**
	 *
	 */
	public function invalidateCache(): bool
	{
		// We don't worry about $cachedir here, since the key is based on the real $cachedir.
		parent::invalidateCache();

		// Since SMF is file based, be sure to clear the statcache.
		clearstatcache();

		return true;
	}

	/**
	 *
	 */
	public function cacheSettings(array &$config_vars): void
	{
		$class_name = $this->getImplementationClassKeyName();
		$class_name_txt_key = strtolower($class_name);

		$config_vars[] = Lang::getTxt('cache_' . $class_name_txt_key . '_settings', file: 'ManageSettings');
		$config_vars[] = [
			'cachedir',
			Lang::getTxt('cachedir', file: 'Admin'),
			'file',
			'text',
			36,
			'cache_cachedir',
		];

		if (!isset(Utils::$context['settings_post_javascript'])) {
			Utils::$context['settings_post_javascript'] = '';
		}

		if (empty(Utils::$context['settings_not_writable'])) {
			Utils::$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir").prop("disabled", cache_type != "' . $class_name . '");
			});';
		}
	}

	/**
	 * Sets the $cachedir or uses the SMF default $cachedir..
	 *
	 * @param null|string $dir A valid path
	 */
	public function setCachedir(?string $dir = null): void
	{
		// If it's invalid, use SMF's.
		if (\is_null($dir) || !is_writable($dir)) {
			$this->cachedir = Config::$cachedir;
		} else {
			$this->cachedir = $dir;
		}
	}

	/**
	 * Gets the current $cachedir.
	 *
	 * @return string the value of $ttl.
	 */
	public function getCachedir(): string
	{
		return $this->cachedir;
	}

	/**
	 *
	 */
	public function getVersion(): string|bool
	{
		return SMF_VERSION;
	}

	/******************
	 * Internal methods
	 ******************/

	private function readFile(string $file): mixed
	{
		try {
			$fp = new \SplFileObject($file, 'rb');

			if (!$fp->flock(LOCK_SH)) {
				$fp = null;

				return false;
			}

			$string = '';

			while (!$fp->eof()) {
				$string .= $fp->fgets();
			}

			$fp->flock(LOCK_UN);
			$fp = null;

			return $string;
		} catch (\Exception $ex) {
			if ($fp !== null) {
				$fp->flock(LOCK_UN);
				$fp = null;
			}
		}

		return false;
	}

	private function writeFile(string $file, mixed $string): mixed
	{
		try {
			$fp = new \SplFileObject($file, 'cb');

			if (!$fp->flock(LOCK_EX)) {
				$fp = null;

				return false;
			}

			$fp->ftruncate(0);
			$bytes = 0;
			$pieces = str_split($string, 8192);

			foreach ($pieces as $piece) {
				if (($val = $fp->fwrite($piece, 8192)) !== false) {
					$bytes += $val;
				} else {
					return false;
				}
			}
			$fp->fflush();
			$fp->flock(LOCK_UN);
			$fp = null;

			return $bytes;
		} catch (\Exception $ex) {
			if ($fp !== null) {
				$fp->flock(LOCK_UN);
				$fp = null;
			}
		}

		return false;
	}
}
