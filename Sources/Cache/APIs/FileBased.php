<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

namespace SMF\Cache\APIs;

use GlobIterator;
use FilesystemIterator;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class FileBased extends CacheApi implements CacheApiInterface
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

	private function readFile($file)
	{
		if (file_exists($file) && ($fp = fopen($file, 'rb')) !== false)
		{
			if (!flock($fp, LOCK_SH))
			{
				fclose($fp);
				return false;
			}
			$string = '';
			while (!feof($fp))
				$string .= fread($fp, 8192);

			flock($fp, LOCK_UN);
			fclose($fp);

			return $string;
		}

		return false;
	}

	private function writeFile($file, $string)
	{
		if (($fp = fopen($file, 'cb')) !== false)
		{
			if (!flock($fp, LOCK_EX))
			{
				fclose($fp);
				return false;
			}
			ftruncate($fp, 0);
			$bytes = 0;
			$pieces = str_split($string, 8192);
			foreach ($pieces as $piece)
			{
				if (($val = fwrite($fp, $piece, 8192)) !== false)
					$bytes += $val;
				else
					return false;
			}
			fflush($fp);
			flock($fp, LOCK_UN);
			fclose($fp);

			return $bytes;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$file = sprintf('%s/data_%s.cache',
			$this->cachedir,
			$this->prefix . strtr($key, ':/', '-_')
		);

		// SMF Data returns $value and $expired.  $expired has a unix timestamp of when this expires.
		if (file_exists($file) && ($raw = $this->readFile($file)) !== false)
		{
			if (($value = smf_json_decode($raw, true, false)) !== array() && isset($value['expiration']) && $value['expiration'] >= time())
				return $value['value'];
			else
				@unlink($file);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$file = sprintf('%s/data_%s.cache',
			$this->cachedir,
			$this->prefix . strtr($key, ':/', '-_')
		);
		$ttl = $ttl !== null ? $ttl : $this->ttl;

		if ($value === null)
			@unlink($file);
		else
		{
			$cache_data = json_encode(
				array(
					'expiration' => time() + $ttl,
					'value' => $value
				),
				JSON_NUMERIC_CHECK
			);

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			if ($this->writeFile($file, $cache_data) !== strlen($cache_data))
			{
				@unlink($file);
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
		// No directory = no game.
		if (!is_dir($this->cachedir))
			return;

		// Remove the files in SMF's own disk cache, if any
		$files = new GlobIterator($this->cachedir . '/' . $type . '*.cache', FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($files as $file => $info)
			unlink($this->cachedir . '/' . $file);

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

		$class_name = $this->getImplementationClassKeyName();
		$class_name_txt_key = strtolower($class_name);

		$config_vars[] = $txt['cache_'. $class_name_txt_key .'_settings'];
		$config_vars[] = array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		if (empty($context['settings_not_writable']))
			$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir").prop("disabled", cache_type != "'. $class_name .'");
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