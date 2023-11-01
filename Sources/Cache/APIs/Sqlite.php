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

use SMF\Config;
use SMF\Lang;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;
use SQLite3;

if (!defined('SMF'))
	die('No direct access...');

/**
 * SQLite Cache API class
 *
 * @package CacheAPI
 */
class Sqlite extends CacheApi implements CacheApiInterface
{
	/**
	 * @var string The path to the current $cachedir directory.
	 */
	private $cachedir = null;

	/**
	 * @var SQLite3
	 */
	private $cacheDB = null;

	public function __construct()
	{
		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		$database = $this->cachedir . '/' . 'SQLite3Cache.db3';
		$this->cacheDB = new SQLite3($database);
		$this->cacheDB->busyTimeout(1000);
		if (filesize($database) == 0)
		{
			$this->cacheDB->exec('CREATE TABLE cache (key text unique, value blob, ttl int);');
			$this->cacheDB->exec('CREATE INDEX ttls ON cache(ttl);');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists("SQLite3") && is_writable($this->cachedir);

		if ($test)
			return $supported;

		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$query = 'SELECT value FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\' AND ttl >= ' . time() . ' LIMIT 1';
		$result = $this->cacheDB->query($query);

		$value = null;
		while ($res = $result->fetchArray(SQLITE3_ASSOC))
			$value = $res['value'];

		return !empty($value) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$ttl = time() + (int) ($ttl !== null ? $ttl : $this->ttl);
		if ($value === null)
			$query = 'DELETE FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\';';
		else
			$query = 'REPLACE INTO cache VALUES (\'' . $this->cacheDB->escapeString($key) . '\', \'' . $this->cacheDB->escapeString($value) . '\', ' . $ttl . ');';
		$result = $this->cacheDB->exec($query);

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		if ($type == 'expired')
			$query = 'DELETE FROM cache WHERE ttl < ' . time() . ';';
		else
			$query = 'DELETE FROM cache;';

		$result = $this->cacheDB->exec($query);

		$query = 'VACUUM;';
		$this->cacheDB->exec($query);

		$this->invalidateCache();

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		$class_name = $this->getImplementationClassKeyName();
		$class_name_txt_key = strtolower($class_name);

		$config_vars[] = Lang::$txt['cache_'. $class_name_txt_key .'_settings'];
		$config_vars[] = array(
			'cachedir_'. $class_name_txt_key,
			Lang::$txt['cachedir_'. $class_name_txt_key],
			'file',
			'text',
			36,
			'cache_'. $class_name_txt_key .'_cachedir',
		);

		if (!isset(Utils::$context['settings_post_javascript']))
			Utils::$context['settings_post_javascript'] = '';

		if (empty(Utils::$context['settings_not_writable']))
			Utils::$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir_'. $class_name_txt_key .'").prop("disabled", cache_type != "'. $class_name .'");
			});';
	}

	/**
	 * Sets the $cachedir or uses the SMF default $cachedir..
	 *
	 * @access public
	 *
	 * @param string $dir A valid path
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setCachedir($dir = null)
	{
		// If its invalid, use SMF's.
		if (!isset($dir) || !is_writable($dir))
		{
			if (!isset(Config::$cachedir_sqlite) || !is_writable(Config::$cachedir_sqlite))
			{
				Config::$cachedir_sqlite = Config::$cachedir;

				require_once(Config::$sourcedir . '/Subs-Admin.php');
				Config::updateSettingsFile(array('cachedir_sqlite' => Config::$cachedir_sqlite));
			}

			$this->cachedir = Config::$cachedir_sqlite;
		}
		else
			$this->cachedir = $dir;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		if (null == $this->cacheDB)
			$this->connect();

		return $this->cacheDB->version()['versionString'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function housekeeping()
	{
		$this->cleanCache('expired');
	}
}

?>