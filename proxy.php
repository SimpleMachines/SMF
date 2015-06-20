<?php

/**
 * This is a lightweight proxy for serving images, generally meant to be used alongside SSL
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

define('SMF', 'proxy');

class ProxyServer
{
	protected $enabled;
	protected $maxSize;
	protected $secret;
	protected $cache;

	/**
	 * Constructor, loads up the Settings for the proxy
	 *
	 * @access public
	 */
	public function __construct()
	{
		global $image_proxy_enabled, $image_proxy_maxsize, $image_proxy_secret, $cachedir, $sourcedir;

		require_once(dirname(__FILE__) . '/Settings.php');
		require_once($sourcedir . '/Class-CurlFetchWeb.php');

		$this->enabled = (bool) $image_proxy_enabled;
		$this->maxSize = (int) $image_proxy_maxsize;
		$this->secret = (string) $image_proxy_secret;
		$this->cache = $cachedir . '/images';
	}

	/**
	 * Checks whether the request is valid or not
	 *
	 * @access public
	 * @return bool
	 */
	public function checkRequest()
	{
		if (!$this->enabled)
			return false;

		// Try to create the image cache directory if it doesn't exist
		if (!file_exists($this->cache))
			if (!mkdir($this->cache) || !copy(dirname($this->cache) . '/index.php', $this->cache . '/index.php'))
				return false;

		if (empty($_GET['hash']) || empty($_GET['request']))
			return false;

		$hash = $_GET['hash'];
		$request = $_GET['request'];

		if (md5($request . $this->secret) != $hash)
			return false;

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

		// Is the cache expired?
		if (!$cached || time() - $cached['time'] > (5 * 86400))
		{
			@unlink($cached_file);
			if ($this->checkRequest())
				$this->serve();
			exit;
		}

		// Make sure we're serving an image
		$contentParts = explode('/', !empty($cached['content-type']) ? $cached['content-type'] : '');
		if ($contentParts[0] != 'image')
			exit;

		header('Content-type: ' . $cached['content_type']);
		header('Content-length: ' . $cached['size']);
		echo base64_decode($cached['body']);
	}

	/**
	 * Returns the request's hashed filepath
	 *
	 * @access public
	 * @param string $request
	 * @return string
	 */
	protected function getCachedPath($request)
	{
		return $this->cache . '/' . sha1($request . $this->secret);
	}

	/**
	 * Check whether the image exists in local cache or not
	 *
	 * @access protected
	 * @param string $request
	 * @return bool
	 */
	protected function isCached($request)
	{
		return file_exists($this->getCachedPath($request));
	}

	/**
	 * Attempts to cache the image while validating it
	 *
	 * @access protected
	 * @param string
	 * @return bool
	 */
	protected function cacheImage($request)
	{
		$dest = $this->getCachedPath($request);

		$curl = new curl_fetch_web_data(array(CURLOPT_BINARYTRANSFER => 1));
		$request = $curl->get_url_data($request);
		$response = $request->result();

		if (empty($response))
			return false;

		$headers = $response['headers'];

		// Make sure the url is returning an image
		$contentParts = explode('/', !empty($headers['content-type']) ? $headers['content-type'] : '');
		if ($contentParts[0] != 'image')
			return false;

		// Validate the filesize
		if ($response['size'] > ($this->maxSize * 1024))
			return false;

		return file_put_contents($dest, json_encode(array(
			'content_type' => $headers['content-type'],
			'size' => $response['size'],
			'time' => time(),
			'body' => base64_encode($response['body']),
		)));
	}
}

$proxy = new ProxyServer();
if ($proxy->checkRequest())
	$proxy->serve();

exit;
