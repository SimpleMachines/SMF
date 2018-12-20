<?php

/**
 * This is a lightweight proxy for serving images, generally meant to be used alongside SSL
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	define('SMF', 'proxy');

global $proxyhousekeeping;

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

		if (md5($request . $this->secret) != $hash)
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
	 * @return void
	 */
	public function serve()
	{
		$request = $_GET['request'];
		$cached_file = $this->getCachedPath($request);
		$cached = json_decode(file_get_contents($cached_file), true);

		// Did we get an error when trying to fetch the image
		$response = $this->checkRequest();
		if ($response === -1)
		{
			// Throw a 404
			send_http_status(404);
			exit;
		}
		// Right, image not cached? Simply redirect, then.
		if ($response === 0)
		{
			$this::redirectexit($request);
		}

		$time = time();

		// Is the cache expired?
		if (!$cached || $time - $cached['time'] > ($this->maxDays * 86400))
		{
			@unlink($cached_file);
			if ($this->checkRequest())
				$this->serve();
			$this::redirectexit($request);
		}

		$eTag = '"' . substr(sha1($request) . $cached['time'], 0, 64) . '"';
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
		{
			send_http_status(304);
			exit;
		}

		// Make sure we're serving an image
		$contentParts = explode('/', !empty($cached['content_type']) ? $cached['content_type'] : '');
		if ($contentParts[0] != 'image')
			exit;

		$max_age = $time - $cached['time'] + (5 * 86400);
		header('content-type: ' . $cached['content_type']);
		header('content-length: ' . $cached['size']);
		header('cache-control: public, max-age=' . $max_age);
		header('last-modified: ' . gmdate('D, d M Y H:i:s', $cached['time']) . ' GMT');
		header('etag: ' . $eTag);
		echo base64_decode($cached['body']);
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
	 * @access protected
	 * @param string $request The image to cache/validate
	 * @return int -1 error, 0 too big, 1 valid image
	 */
	protected function cacheImage($request)
	{
		$dest = $this->getCachedPath($request);
		$ext = strtolower(pathinfo(parse_url($request, PHP_URL_PATH), PATHINFO_EXTENSION));

		$image = fetch_web_data($request);

		// Looks like nobody was home
		if (empty($image))
			return -1;

		// What kind of file did they give us?
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_buffer($finfo, $image);

		// SVG needs a little extra care
		if ($ext == 'svg' && $mime_type == 'text/plain')
			$mime_type = 'image/svg+xml';

		// Make sure the url is returning an image
		if (strpos($mime_type, 'image/') !== 0)
			return -1;

		// Validate the filesize
		$size = strlen($image);
		if ($size > ($this->maxSize * 1024))
			return 0;

		// Cache it for later
		return file_put_contents($dest, json_encode(array(
			'content_type' => $mime_type,
			'size' => $size,
			'time' => time(),
			'body' => base64_encode($image),
		))) === false ? -1 : 1;
	}

	/**
	 * Static helper function to redirect a request
	 *
	 * @access public
	 * @param type $request
	 * @return void
	 */
	static public function redirectexit($request)
	{
		header('Location: ' . $request, false, 301);
		exit;
	}

	/**
	 * Delete all old entries
	 *
	 * @access public
	 * @return void
	 */
	public function housekeeping()
	{
		$path = $this->cache . '/';
		if ($handle = opendir($path))
		{
			while (false !== ($file = readdir($handle)))
			{
				$filelastmodified = filemtime($path . $file);

				if ((time() - $filelastmodified) > ($this->maxDays * 86400))
				{
					unlink($path . $file);
				}
			}

			closedir($handle);
		}
	}
}

if (empty($proxyhousekeeping))
{
	$proxy = new ProxyServer();
	$proxy->serve();
}

?>