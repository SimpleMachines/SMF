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
			// Work around Zend's opcode caching (PHP 5.5+), they would cache older files for a couple of seconds
			// causing newer files to take effect a while later.
			if (function_exists('opcache_invalidate'))
				opcache_invalidate($cachedir . '/data_' . $key . '.php', true);

			if (function_exists('apc_delete_file'))
				@apc_delete_file($cachedir . '/data_' . $key . '.php');

			// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
			@include($cachedir . '/data_' . $key . '.php');
			if (!empty($expired) && isset($value))
			{
				@unlink($cachedir . '/data_' . $key . '.php');
				unset($value);
			}
		}

		return !empty($value) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');
		$file_name = '/data_' . $key . '.php';
		$file_path = $this->cachedir . $file_name;

		// Always assume the worst
		$file_created = false;

		// Work around Zend's opcode caching (PHP 5.5+), they would cache older files for a couple of seconds
		// causing newer files to take effect a while later.
		if (function_exists('opcache_invalidate'))
			opcache_invalidate($file_path, true);

		if (function_exists('apc_delete_file'))
			@apc_delete_file($file_path);

		// Otherwise custom cache?
		if ($value === null)
			@unlink($file_path);

		else
		{
			$cache_data = '<' . '?' . 'php if (!defined(\'SMF\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, "\0" . '\\\'') . '\';}' . '?' . '>';

			// Write out the cache file, check that the cache write was successful; all the data must be written
			$fileSize = $this->filePutWithLock($file_name, $cache_data);

			// Check the file againts what its suppose to have in it.
			if ($fileSize === strlen($cache_data))
				$file_created = true;

		}

		return $file_created;
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

	/**
	 * @param string $file_name
	 * @param string $cache_data
	 * @return int
	 */
	protected function filePutWithLock($file_name, $cache_data)
	{
		$saved_file = 0;
		$tmp_lock_file = $this->cachedir . '/tmp'. str_replace('.php', '.lock', $file_name);

		if (mkdir($tmp_lock_file, 0700, true)) {

			$saved_file = file_put_contents( $this->cachedir . $file_name, $cache_data, LOCK_EX);

			rmdir($tmp_lock_file);
		}

		return $saved_file;
	}
}

?>