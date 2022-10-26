<?php

/**
 * This is a lightweight proxy for serving images, generally meant to be used alongside SSL
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

if (!defined('SMF'))
	define('SMF', 'PROXY');

if (!defined('SMF_VERSION'))
	define('SMF_VERSION', '2.1.3');

if (!defined('SMF_FULL_VERSION'))
	define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);

if (!defined('SMF_SOFTWARE_YEAR'))
	define('SMF_SOFTWARE_YEAR', '2022');

if (!defined('JQUERY_VERSION'))
	define('JQUERY_VERSION', '3.6.0');

if (!defined('POSTGRE_TITLE'))
	define('POSTGRE_TITLE', 'PostgreSQL');

if (!defined('MYSQL_TITLE'))
	define('MYSQL_TITLE', 'MySQL');

if (!defined('SMF_USER_AGENT'))
	define('SMF_USER_AGENT', 'Mozilla/5.0 (' . php_uname('s') . ' ' . php_uname('m') . ') AppleWebKit/605.1.15 (KHTML, like Gecko)  SMF/' . strtr(SMF_VERSION, ' ', '.'));

/**
 * Class ProxyServer
 */
class ProxyServer
{
	/** @var bool $enabled Whether or not this is enabled */
	protected $enabled;

	/** @var int $maxSize The maximum size for files to cache */
	protected $maxSize;

	/** @var string $secret A secret code used for hashing */
	protected $secret;

	/** @var string The cache directory */
	protected $cache;

	/** @var int $maxDays until entries get deleted */
	protected $maxDays;

	/** @var int $cachedtime time object cached */
	protected $cachedtime;

	/** @var string $cachedtype type of object cached */
	protected $cachedtype;

	/** @var int $cachedsize size of object cached */
	protected $cachedsize;

	/** @var string $cachedbody body of object cached */
	protected $cachedbody;

	/**
	 * Constructor, loads up the Settings for the proxy
	 *
	 * @access public
	 */
	public function __construct()
	{
		global $image_proxy_enabled, $image_proxy_maxsize, $image_proxy_secret, $cachedir, $sourcedir;

		require_once(dirname(__FILE__) . '/Settings.php');
		require_once($sourcedir . '/Subs.php');

		// Ensure we don't trip over disabled internal functions
		if (version_compare(PHP_VERSION, '8.0.0', '>='))
			require_once($sourcedir . '/Subs-Compat.php');

		// Make absolutely sure the cache directory is defined and writable.
		if (empty($cachedir) || !is_dir($cachedir) || !is_writable($cachedir))
		{
			if (is_dir($boarddir . '/cache') && is_writable($boarddir . '/cache'))
				$cachedir = $boarddir . '/cache';
			else
			{
				$cachedir = sys_get_temp_dir() . '/smf_cache_' . md5($boarddir);
				@mkdir($cachedir, 0750);
			}
		}

		// Turn off all error reporting; any extra junk makes for an invalid image.
		error_reporting(0);

		$this->enabled = (bool) $image_proxy_enabled;
		$this->maxSize = (int) $image_proxy_maxsize;
		$this->secret = (string) $image_proxy_secret;
		$this->cache = $cachedir . '/images';
		$this->maxDays = 5;
	}

	/**
	 * Checks whether the request is valid or not
	 *
	 * @access public
	 * @return bool Whether the request is valid
	 */
	public function checkRequest()
	{
		if (!$this->enabled)
			return false;

		// Try to create the image cache directory if it doesn't exist
		if (!file_exists($this->cache))
			if (!mkdir($this->cache) || !copy(dirname($this->cache) . '/index.php', $this->cache . '/index.php'))
				return false;

		// Basic sanity check
		$_GET['request'] = validate_iri($_GET['request']);

		// We aren't going anywhere without these
		if (empty($_GET['hash']) || empty($_GET['request']))
			return false;

		$hash = $_GET['hash'];
		$request = $_GET['request'];

		if (hash_hmac('sha1', $request, $this->secret) != $hash)
			return false;

		// Ensure any non-ASCII characters in the URL are encoded correctly
		$request = iri_to_url($request);

		// Attempt to cache the request if it doesn't exist
		if (!$this->isCached($request))
			return $this->cacheImage($request);

		return true;
	}

	/**
	 * Serves the request
	 *
	 * @access public
	 */
	public function serve()
	{
		$request = $_GET['request'];
		// Did we get an error when trying to fetch the image
		$response = $this->checkRequest();
		if (!$response)
		{
			// Throw a 404
			send_http_status(404);
			exit;
		}

		// We should have a cached image at this point
		$cached_file = $this->getCachedPath($request);

		// Read from cache if you need to...
		if ($this->cachedbody === null)
		{
			$cached = json_decode(file_get_contents($cached_file), true);
			$this->cachedtime = $cached['time'];
			$this->cachedtype = $cached['content_type'];
			$this->cachedsize = $cached['size'];
			$this->cachedbody = $cached['body'];
		}

		$time = time();

		// Is the cache expired? Delete and reload.
		if ($time - $this->cachedtime > ($this->maxDays * 86400))
		{
			@unlink($cached_file);
			if ($this->checkRequest())
				$this->serve();
			$this->redirectexit($request);
		}

		$eTag = '"' . substr(sha1($request) . $this->cachedtime, 0, 64) . '"';
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
		{
			send_http_status(304);
			exit;
		}

		// Make sure we're serving an image
		$contentParts = explode('/', !empty($this->cachedtype) ? $this->cachedtype : '');
		if ($contentParts[0] != 'image')
			exit;

		$max_age = $time - $this->cachedtime + (5 * 86400);
		header('content-type: ' . $this->cachedtype);
		header('content-length: ' . $this->cachedsize);
		header('cache-control: public, max-age=' . $max_age);
		header('last-modified: ' . gmdate('D, d M Y H:i:s', $this->cachedtime) . ' GMT');
		header('etag: ' . $eTag);
		echo base64_decode($this->cachedbody);
	}

	/**
	 * Returns the request's hashed filepath
	 *
	 * @access public
	 * @param string $request The request to get the path for
	 * @return string The hashed filepath for the specified request
	 */
	protected function getCachedPath($request)
	{
		return $this->cache . '/' . sha1($request . $this->secret);
	}

	/**
	 * Check whether the image exists in local cache or not
	 *
	 * @access protected
	 * @param string $request The image to check for in the cache
	 * @return bool Whether or not the requested image is cached
	 */
	protected function isCached($request)
	{
		return file_exists($this->getCachedPath($request));
	}

	/**
	 * Attempts to cache the image while validating it
	 *
	 * Redirects to the origin if
	 *    - the image couldn't be fetched
	 *    - the MIME type doesn't indicate an image
	 *    - the image is too large
	 *
	 * @access protected
	 * @param string $request The image to cache/validate
	 * @return bool Whether the specified image was cached
	 */
	protected function cacheImage($request)
	{
		$dest = $this->getCachedPath($request);
		$ext = strtolower(pathinfo(parse_iri($request, PHP_URL_PATH), PATHINFO_EXTENSION));

		$image = fetch_web_data($request);

		// Looks like nobody was home
		if (empty($image))
			$this->redirectexit($request);

		// What kind of file did they give us?
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_buffer($finfo, $image);

		// SVG needs a little extra care
		if ($ext == 'svg' && in_array($mime_type, array('text/plain', 'text/xml')) && strpos($image, '<svg') !== false && strpos($image, '</svg>') !== false)
			$mime_type = 'image/svg+xml';

		// Make sure the url is returning an image
		if (strpos($mime_type, 'image/') !== 0)
			$this->redirectexit($request);

		// Validate the filesize
		$size = strlen($image);
		if ($size > ($this->maxSize * 1024))
			$this->redirectexit($request);

		// Populate object for current serve execution (so you don't have to read it again...)
		$this->cachedtime = time();
		$this->cachedtype = $mime_type;
		$this->cachedsize = $size;
		$this->cachedbody = base64_encode($image);

		// Cache it for later
		return file_put_contents($dest, json_encode(array(
			'content_type' => $this->cachedtype,
			'size' => $this->cachedsize,
			'time' => $this->cachedtime,
			'body' => $this->cachedbody,
		))) !== false;
	}

	/**
	 * A helper function to redirect a request
	 *
	 * @access private
	 * @param string $request
	 */
	private function redirectexit($request)
	{
		header('Location: ' . un_htmlspecialchars($request), false, 301);
		exit;
	}

	/**
	 * Delete all old entries
	 *
	 * @access public
	 */
	public function housekeeping()
	{
		$path = $this->cache . '/';
		if ($handle = opendir($path))
		{
			while (false !== ($file = readdir($handle)))
			{
				if (is_file($path . $file) && !in_array($file, array('index.php', '.htaccess')) && time() - filemtime($path . $file) > $this->maxDays * 86400)
					unlink($path . $file);
			}

			closedir($handle);
		}
	}
}

if (SMF == 'PROXY')
{
	$proxy = new ProxyServer();
	$proxy->serve();
}

?>