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

use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Utils;
use SQLite3;

if (!\defined('SMF')) {
	die('No direct access...');
}

/**
 * SQLite Cache API class
 *
 * @package CacheAPI
 */
class Sqlite extends CacheApi implements CacheApiInterface
{
	/*****************
	 * Class constants
	 *****************/

	public const CLASS_KEY = 'cache_sqlite';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string The path to the current directory.
	 */
	private $cachedir = null;

	/**
	 * @var SQLite3
	 */
	private $cacheDB = null;

	/**
	 * Indicates we have logged a error.
	 * @var bool
	 */
	private bool $logOnce = false;

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();
	}

	/**
	 *
	 */
	public function connect(): bool
	{
		$database = $this->cachedir . '/' . 'SQLite3Cache.db3';

		if ((file_exists($database) && !is_writable($database)) || !is_writeable($this->cachedir)) {
			return false;
		}

		try {
			// Did we disable WAL?  That triggers a read only mode, dump the cache.
			if (file_exists($this->cachedir . '/' . 'SQLite3Cache.db3-wal') && empty(Config::$cache_sqlite_wal)) {
				unlink($this->cachedir . '/' . 'SQLite3Cache.db3');
			}

			$this->cacheDB = new SQLite3($database);
			$this->cacheDB->busyTimeout(1000);
			$this->cacheDB->enableExceptions(true);

			// Its a WALuigi!
			if (!empty(Config::$cache_sqlite_wal)) {
				$this->cacheDB->exec('PRAGMA journal_mode = wal;');
			}

			$this->cacheDB->exec('CREATE TABLE IF NOT EXISTS cache (key text unique, value blob, ttl int);');
			$this->cacheDB->exec('CREATE INDEX IF NOT EXISTS ttls ON cache(ttl);');

			return true;
		} catch (\Exception $ex) {
			if (!$this->logOnce) {
				$this->logOnce = true;
				ErrorHandler::logException($ex, null);
			}

			return false;
		}
	}

	/**
	 *
	 */
	public function isSupported(bool $test = false): bool
	{
		$supported = class_exists('SQLite3') && is_writable($this->cachedir);

		if ($test) {
			return $supported;
		}

		return parent::isSupported() && $supported;
	}

	/**
	 *
	 */
	public function getData(string $key, ?int $ttl = null): mixed
	{
		$query = 'SELECT value FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\' AND ttl >= ' . time() . ' LIMIT 1';

		try {
			$result = $this->cacheDB->query($query);
		} catch (\Exception $ex) {
			if (!$this->logOnce) {
				$this->logOnce = true;
				ErrorHandler::logException($ex, null);
			}

			return null;
		}

		$value = null;

		while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
			$value = $res['value'];
		}

		return !empty($value) ? $value : null;
	}

	/**
	 *
	 */
	public function putData(string $key, mixed $value, ?int $ttl = null): mixed
	{
		$ttl = time() + (int) ($ttl !== null ? $ttl : $this->ttl);

		if ($value === null) {
			$query = 'DELETE FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\';';
		} else {
			$query = 'REPLACE INTO cache VALUES (\'' . $this->cacheDB->escapeString($key) . '\', \'' . $this->cacheDB->escapeString(\is_bool($value) ? \strval(\intval($value)) : $value) . '\', ' . $ttl . ');';
		}

		try {
			$result = $this->cacheDB->exec($query);
		} catch (\Exception $ex) {
			if (!$this->logOnce) {
				$this->logOnce = true;
				ErrorHandler::logException($ex);
			}

			return false;
		}

		return $result;
	}

	/**
	 *
	 */
	public function cleanCache(string $type = ''): bool
	{
		if ($type == 'expired') {
			$query = 'DELETE FROM cache WHERE ttl < ' . time() . ';';
		} else {
			$query = 'DELETE FROM cache;';
		}

		try {
			$result = $this->cacheDB->exec($query);

			$query = 'VACUUM;';
			$this->cacheDB->exec($query);
		} catch (\Exception $ex) {
			if (!$this->logOnce) {
				$this->logOnce = true;
				ErrorHandler::logException($ex, null);
			}

			$result = false;
		}

		$this->invalidateCache();

		return $result;
	}

	/**
	 *
	 */
	public function cacheSettings(array &$config_vars): void
	{
		$class_name = $this->getImplementationClassKeyName();
		$class_name_txt_key = strtolower($class_name);

		$config_vars[] = Lang::getTxt(self::CLASS_KEY . '_settings', file: 'ManageSettings');
		$config_vars[] = [
			'cachedir_' . $class_name_txt_key,
			Lang::getTxt('cachedir_' . $class_name_txt_key, file: 'ManageSettings'),
			'file',
			'text',
			36,
			self::CLASS_KEY . '_cachedir',
		];
		$config_vars[] = [
			self::CLASS_KEY . '_wal',
			Lang::getTxt('cache_sqlite_wal', file: 'ManageSettings'),
			'file',
			'check',
			self::CLASS_KEY . '_cachedir',
			'subtext' => Lang::getTxt(self::CLASS_KEY . '_wal_subtext', file: 'ManageSettings'),
		];

		if (!isset(Utils::$context['settings_post_javascript'])) {
			Utils::$context['settings_post_javascript'] = '';
		}

		if (empty(Utils::$context['settings_not_writable'])) {
			Utils::$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir_' . $class_name_txt_key . '").prop("disabled", cache_type != "' . $class_name . '");
			});';
		}
	}

	/**
	 * Sets the $cachedir or uses the SMF default $cachedir..
	 *
	 * @param string|null $dir A valid path
	 */
	public function setCachedir(?string $dir = null): void
	{
		// If it's invalid, use SMF's.
		if (!isset($dir) || !is_writable($dir)) {
			if (!isset(Config::$cachedir_sqlite) || !is_writable(Config::$cachedir_sqlite)) {
				Config::$cachedir_sqlite = Config::$cachedir;

				Config::updateSettingsFile(['cachedir_sqlite' => Config::$cachedir_sqlite]);
			}

			$this->cachedir = Config::$cachedir_sqlite;
		} else {
			$this->cachedir = $dir;
		}
	}

	/**
	 *
	 */
	public function getVersion(): string|bool
	{
		if (null == $this->cacheDB) {
			$this->connect();
		}

		return $this->cacheDB !== null ? $this->cacheDB->version()['versionString'] : false;
	}

	/**
	 *
	 */
	public function housekeeping(): void
	{
		$this->cleanCache('expired');
	}
}
