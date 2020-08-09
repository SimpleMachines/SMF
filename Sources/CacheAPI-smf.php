<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Our Cache API class
 *
 * @package cacheAPI
 */
class smf_cache extends cache_api
{
	/**
	 * @var string The path to the current $cachedir directory.
	 */
	private $cachedir = null;

	/**
	 * {@inheritDoc}
	 */
	public function __construct()
	{
		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = is_writable($this->cachedir);

		if ($test)
			return $supported;
		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');
		$cachedir = $this->cachedir;

		// SMF Data returns $value and $expired.  $expired has a unix timestamp of when this expires.
		if (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
		{
			// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
			$cached = json_decode($this->readFile($cachedir . '/data_' . $key . '.php'), TRUE);
			if (!empty($cached['expired']) && isset($cached['value']))
			{
				if ($cached['expired'] < time())
				{
					@unlink($cachedir . '/data_' . $key . '.php');
					unset($value);
				}
				else
				{
					$value = $cached['value'];
				}
			}
		}

		return !empty($value) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function readFile($file)
	{
		// Turns out that file_get_contents does not honor locking, but we need it to.
		// This can be accomplished by placing a small wrapper around it:
		$tmp = fopen($file, 'rb');
		@flock($tmp, LOCK_SH);
		$cached = file_get_contents($file);
		@flock($tmp, LOCK_UN);
		fclose($tmp);

		return $cached;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');
		$cachedir = $this->cachedir;
		$ttl = $ttl !== null ? $ttl : $this->ttl;

		// Otherwise custom cache?
		if ($value === null)
			@unlink($cachedir . '/data_' . $key . '.php');
		else
		{
			$cache_data = json_encode(array('expired' => time() + $ttl, 'value' => $value));
			if ($cache_data === false)
				return false;

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			$fileSize = file_put_contents($cachedir . '/data_' . $key . '.php', $cache_data, LOCK_EX);
			if ($fileSize !== strlen($cache_data))
			{
				@unlink($cachedir . '/data_' . $key . '.php');
				return false;
			}
			else
				return true;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		$cachedir = $this->cachedir;

		// No directory = no game.
		if (!is_dir($cachedir))
			return;

		// Remove the files in SMF's own disk cache, if any
		$dh = opendir($cachedir);
		while ($file = readdir($dh))
		{
			if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
				@unlink($cachedir . '/' . $file);
		}
		closedir($dh);

		// Make this invalid.
		$this->invalidateCache();

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function invalidateCache()
	{
		// We don't worry about $cachedir here, since the key is based on the real $cachedir.
		parent::invalidateCache();

		// Since SMF is file based, be sure to clear the statcache.
		clearstatcache();

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$config_vars[] = $txt['cache_smf_settings'];
		$config_vars[] = array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir").prop("disabled", cache_type != "smf");
			});';
	}

	/**
	 * Sets the $cachedir or uses the SMF default $cachedir..
	 *
	 * @access public
	 * @param string $dir A valid path
	 * @return boolean If this was successful or not.
	 */
	public function setCachedir($dir = null)
	{
		global $cachedir;

		// If its invalid, use SMF's.
		if (is_null($dir) || !is_writable($dir))
			$this->cachedir = $cachedir;
		else
			$this->cachedir = $dir;
	}

	/**
	 * Gets the current $cachedir.
	 *
	 * @access public
	 * @return string the value of $ttl.
	 */
	public function getCachedir()
	{
		return $this->cachedir;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		return SMF_VERSION;
	}
}

?>