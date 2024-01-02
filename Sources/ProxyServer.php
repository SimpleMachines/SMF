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

namespace SMF;

use SMF\WebFetch\WebFetchApi;

/**
 * This is a lightweight proxy for serving images, generally meant to be used
 * alongside SSL.
 */
class ProxyServer
{
	/**
	 * @var bool
	 *
	 * Whether or not this is enabled.
	 */
	protected $enabled;

	/**
	 * @var int
	 *
	 * The maximum size for files to cache.
	 */
	protected $maxSize;

	/**
	 * @var string
	 *
	 * A secret code used for hashing.
	 */
	protected $secret;

	/**
	 * @var string
	 *
	 * The cache directory.
	 */
	protected $cache;

	/**
	 * @var int
	 *
	 * Days until entries get deleted
	 */
	protected $maxDays;

	/**
	 * @var int
	 *
	 * Time object cached.
	 */
	protected $cachedtime;

	/**
	 * @var string
	 *
	 * Type of object cached.
	 */
	protected $cachedtype;

	/**
	 * @var int
	 *
	 * Size of object cached.
	 */
	protected $cachedsize;

	/**
	 * @var string
	 *
	 * Body of object cached.
	 */
	protected $cachedbody;

	/**
	 * Constructor. Loads up the settings for the proxy.
	 */
	public function __construct()
	{
		// Turn off all error reporting; any extra junk makes for an invalid image.
		error_reporting(0);

		$this->enabled = (bool) Config::$image_proxy_enabled;
		$this->maxSize = (int) Config::$image_proxy_maxsize;
		$this->secret = (string) Config::$image_proxy_secret;
		$this->cache = Config::$cachedir . '/images';
		$this->maxDays = 5;
	}

	/**
	 * Checks whether the request is valid or not.
	 *
	 * @return bool Whether the request is valid.
	 */
	public function checkRequest(): bool
	{
		if (!$this->enabled) {
			return false;
		}

		// Try to create the image cache directory if it doesn't exist
		if (!file_exists($this->cache)) {
			if (!mkdir($this->cache) || !copy(dirname($this->cache) . '/index.php', $this->cache . '/index.php')) {
				return false;
			}
		}

		// We aren't going anywhere without these
		if (empty($_GET['hash']) || empty($_GET['request'])) {
			return false;
		}

		$request = new Url($_GET['request']);

		// Basic sanity check.
		if (!$request->isValid()) {
			return false;
		}

		// Ensure any non-ASCII characters in the URL are encoded correctly
		$request = strval($request->toAscii());

		if (hash_hmac('sha1', $request, $this->secret) != $_GET['hash']) {
			return false;
		}

		// Attempt to cache the request if it doesn't exist
		if (!$this->isCached($request)) {
			return $this->cacheImage($request);
		}

		return true;
	}

	/**
	 * Serves the request.
	 */
	public function serve(): void
	{
		$request = $_GET['request'];

		// Did we get an error when trying to fetch the image
		$response = $this->checkRequest();

		if (!$response) {
			// Throw a 404
			Utils::sendHttpStatus(404);

			exit;
		}

		// We should have a cached image at this point
		$cached_file = $this->getCachedPath($request);

		// Read from cache if you need to...
		if ($this->cachedbody === null) {
			$cached = json_decode(file_get_contents($cached_file), true);
			$this->cachedtime = $cached['time'];
			$this->cachedtype = $cached['content_type'];
			$this->cachedsize = $cached['size'];
			$this->cachedbody = $cached['body'];
		}

		$time = time();

		// Is the cache expired? Delete and reload.
		if ($time - $this->cachedtime > ($this->maxDays * 86400)) {
			@unlink($cached_file);

			if ($this->checkRequest()) {
				$this->serve();
			}

			$this->redirectexit($request);
		}

		$eTag = '"' . substr(sha1($request) . $this->cachedtime, 0, 64) . '"';

		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false) {
			Utils::sendHttpStatus(304);

			exit;
		}

		// Make sure we're serving an image
		$contentParts = explode('/', !empty($this->cachedtype) ? $this->cachedtype : '');

		if ($contentParts[0] != 'image') {
			exit;
		}

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
	 * @param string $request The request to get the path for
	 * @return string The hashed filepath for the specified request
	 */
	protected function getCachedPath(string $request): string
	{
		return $this->cache . '/' . sha1($request . $this->secret);
	}

	/**
	 * Check whether the image exists in local cache or not
	 *
	 * @param string $request The image to check for in the cache
	 * @return bool Whether or not the requested image is cached
	 */
	protected function isCached(string $request): string
	{
		return file_exists($this->getCachedPath($request));
	}

	/**
	 * Attempts to cache the image while validating it.
	 *
	 * Redirects to the origin if
	 *    - the image couldn't be fetched
	 *    - the MIME type doesn't indicate an image
	 *    - the image is too large
	 *
	 * @param string $request The image to cache/validate.
	 * @return bool Whether the specified image was cached.
	 */
	protected function cacheImage(string $request): bool
	{
		$request = new Url($request);

		$dest = $this->getCachedPath($request);
		$ext = strtolower(pathinfo($request->path, PATHINFO_EXTENSION));

		$image = WebFetchApi::fetch($request);

		// Looks like nobody was home
		if (empty($image)) {
			$this->redirectexit($request);
		}

		// What kind of file did they give us?
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_buffer($finfo, $image);

		// SVG needs a little extra care
		if ($ext == 'svg' && in_array($mime_type, ['text/plain', 'text/xml']) && strpos($image, '<svg') !== false && strpos($image, '</svg>') !== false) {
			$mime_type = 'image/svg+xml';
		}

		// Make sure the url is returning an image
		if (strpos($mime_type, 'image/') !== 0) {
			$this->redirectexit($request);
		}

		// Validate the filesize
		$size = strlen($image);

		if ($size > ($this->maxSize * 1024)) {
			$this->redirectexit($request);
		}

		// Populate object for current serve execution (so you don't have to read it again...)
		$this->cachedtime = time();
		$this->cachedtype = $mime_type;
		$this->cachedsize = $size;
		$this->cachedbody = base64_encode($image);

		// Cache it for later
		return file_put_contents($dest, json_encode([
			'content_type' => $this->cachedtype,
			'size' => $this->cachedsize,
			'time' => $this->cachedtime,
			'body' => $this->cachedbody,
		])) !== false;
	}

	/**
	 * A helper function to redirect a request
	 *
	 * @param string $request
	 */
	private function redirectexit(string $request): void
	{
		header('Location: ' . Utils::htmlspecialcharsDecode($request), false, 301);

		exit;
	}

	/**
	 * Delete all old entries
	 */
	public function housekeeping(): void
	{
		$path = $this->cache . '/';

		if ($handle = opendir($path)) {
			while (false !== ($file = readdir($handle))) {
				if (is_file($path . $file) && !in_array($file, ['index.php', '.htaccess']) && time() - filemtime($path . $file) > $this->maxDays * 86400) {
					unlink($path . $file);
				}
			}

			closedir($handle);
		}
	}
}

?>