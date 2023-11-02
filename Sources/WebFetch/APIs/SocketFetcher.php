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

namespace SMF\WebFetch\APIs;

use SMF\Lang;
use SMF\Url;
use SMF\WebFetch\WebFetchApi;

/**
 * Fetches data from HTTP URLs via socket connections.
 */
class SocketFetcher extends WebFetchApi
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The host we are connecting to.
	 */
	public string $host;

	/**
	 * @var int
	 *
	 * The port we are connecting to.
	 */
	public int $port;

	/**
	 * @var int
	 *
	 * Error number that occurred in the system-level connect() call.
	 */
	public int $error_code = 0;

	/**
	 * @var string
	 *
	 * The error message as a string.
	 */
	public string $error_message = '';

	/**
	 * @var int
	 *
	 * The connection timeout, in seconds.
	 */
	public int $timeout = 5;

	/**
	 * @var int
	 *
	 * How many redirects have been followed.
	 */
	public $current_redirect = 0;

	/**
	 * @var int
	 *
	 * Maximum number of redirects.
	 */
	public $max_redirect = 3;

	/**
	 * @var array
	 *
	 * Stores responses (url, code, error, headers, body, size).
	 */
	public $response = array();

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Whether to keep the socket connection open after the initial request.
	 */
	private bool $keep_alive;

	/**
	 * @var resource
	 *
	 * File pointer for the open socket connection.
	 */
	private $fp;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param bool $keep_alive Whether to keep the socket connection open after
	 * the initial request.
	 * @param int $max_redirect Maximum number of redirects.
	 */
	public function __construct(bool $keep_alive = false, int $max_redirect = 3)
	{
		$this->keep_alive = $keep_alive;
		$this->max_redirect = $max_redirect;
	}

	/**
	 * Destructor.
	 *
	 * Ensures that the socket connection has been closed before the object is
	 * destroyed.
	 */
	public function __destruct()
	{
		@$this->closeConnection();
	}

	/**
	 * Main calling function.
	 *
	 *  - Will request the page data from a given $url.
	 *  - Optionally will post data to the page form if $post_data is supplied.
	 *    Passed arrays will be converted to a POST string joined with &'s.
	 *
	 * @param string $url the site we are going to fetch
	 * @param array|string $post_data any post data as form name => value
	 * @return object A reference to the object for method chaining.
	 */
	public function request(string $url, array|string $post_data = array()): object
	{
		$url = new Url($url, true);
		$url->toAscii();

		// Umm, this shouldn't happen?
		if (empty($url->scheme) || !in_array($url->scheme, array('http', 'https')))
		{
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['fetch_web_data_bad_url'], __METHOD__), E_USER_NOTICE);
			return $this;
		}

		$host = ($url->scheme === 'https' ? 'ssl://' : '') . $url->host;
		$port = !empty($url->port) ? $url->port : ($url->scheme === 'https' ? 443 : 80);

		$path_and_query = $url->path . (isset($url->query) && $url->query !== '' ? '?' . $url->query : '');
		$path_and_query = $path_and_query === '/' ? '' : str_replace(' ', '%20', $path_and_query);

		$post_data = $this->buildPostData($post_data);

		// We can start building our response data now.
		$this->response[$this->current_redirect] = array(
			'url' => (string) $url,
			'success' => false,
			'code' => null,
			'error' => null,
			'headers' => array(),
			'body' => null,
			'size' => 0,
		);

		// Do we already have an open connection to the socket? If not, open one.
		if (!is_resource($this->fp ?? null) || !$this->keep_alive || $this->host != $host || $this->port != $port)
		{
			$this->host = $host;
			$this->port = $port;

			$this->fp = @fsockopen($this->host, $this->port, $this->error_code, $this->error_message, $this->timeout);
		}

		// Uh-oh...
		if (!is_resource($this->fp))
		{
			$this->closeConnection();
			return $this;
		}

		// I want this, from there, and I may or may not bother you for more later.
		if (empty($post_data))
		{
			fwrite($this->fp, 'GET ' . $path_and_query . ' HTTP/1.1' . "\r\n");
			fwrite($this->fp, 'Host: ' . $url->host . "\r\n");
			fwrite($this->fp, 'User-Agent: ' . SMF_USER_AGENT . "\r\n");
			fwrite($this->fp, 'Connection: ' . ($this->keep_alive ? 'keep-alive' : 'close') . "\r\n");
			fwrite($this->fp, "\r\n");
		}
		else
		{
			fwrite($this->fp, 'POST ' . $path_and_query . ' HTTP/1.1' . "\r\n");
			fwrite($this->fp, 'Host: ' . $url->host . "\r\n");
			fwrite($this->fp, 'User-Agent: ' . SMF_USER_AGENT . "\r\n");
			fwrite($this->fp, 'Connection: ' . ($this->keep_alive ? 'keep-alive' : 'close') . "\r\n");
			fwrite($this->fp, 'Content-Type: application/x-www-form-urlencoded' . "\r\n");
			fwrite($this->fp, 'Content-Length: ' . strlen($post_data) . "\r\n");
			fwrite($this->fp, "\r\n");
			fwrite($this->fp, $post_data);
		}

		$response = fgets($this->fp, 768);

		// What response code was sent?
		if (preg_match('~^HTTP/\S+\s+(\d+)~i', $response, $matches))
		{
			$http_code = (int) $matches[1];
			$this->response[$this->current_redirect]['code'] = $http_code;
		}
		// No response code? Bail out.
		else
		{
			$this->closeConnection();
			return $this;
		}

		// Redirect if the resource has been permanently or temporarily moved.
		if ($this->current_redirect < $this->max_redirect && in_array($http_code, array(301, 302, 307)))
		{
			while (!feof($this->fp) && trim($header = fgets($this->fp, 4096)) != '')
			{
				$this->response[$this->current_redirect]['headers'][] = $header;

				if (stripos($header, 'location:') !== false)
					$location = trim(substr($header, strpos($header, ':') + 1));
			}

			$location = new Url($location ?? '');

			if (!$location->isValid())
			{
				$this->closeConnection();
				return $this;
			}

			// Close if it moved to a different host.
			if ($location->$host !== $url->host)
				$this->closeConnection();

			$this->current_redirect++;

			return $this->request($location, $post_data);
		}
		// Make sure we get a 200 OK.
		elseif (!in_array($http_code, array(200, 201)))
		{
			return $this;
		}

		// Skip the headers...
		while (!feof($this->fp) && trim($header = fgets($this->fp, 4096)) != '')
		{
			$this->response[$this->current_redirect]['headers'][] = $header;

			if (preg_match('~Content-Length:\s*(\d+)~i', $header, $match))
			{
				$content_length = $match[1];
			}
			elseif (preg_match('~Connection:\s*Close~i', $header))
			{
				$this->keep_alive = false;
			}

			continue;
		}

		$body = '';

		if (isset($content_length))
		{
			while (!feof($this->fp) && strlen($body) < $content_length)
				$body .= fread($this->fp, $content_length - strlen($body));
		}
		else
		{
			while (!feof($this->fp))
				$body .= fread($this->fp, 4096);
		}

		$this->response[$this->current_redirect]['success'] = true;
		$this->response[$this->current_redirect]['body'] = $body;
		$this->response[$this->current_redirect]['size'] = strlen($body);

		if (!$this->keep_alive)
			$this->closeConnection();

		return $this;
	}

	/**
	 * Used to return the results to the caller.
	 *
	 *  - Called as ->result() will return the full final array.
	 *  - Called as ->result('body') to return the page source of the result.
	 *
	 * @param string $area Used to return an area such as body, header, error.
	 * @return mixed The response.
	 */
	public function result(string $area = null): mixed
	{
		$max_result = count($this->response) - 1;

		// Just return a specifed area or the entire result?
		if (is_null($area))
			return $this->response[$max_result];

		return isset($this->response[$max_result][$area]) ? $this->response[$max_result][$area] : $this->response[$max_result];
	}

	/**
	 * Will return all results from all loops (redirects).
	 *
	 *  - Can be called as ->result_raw(x) where x is a specific loop's result.
	 *  - Call as ->result_raw() for everything.
	 *
	 * @param int $response_number Which response to get, or null for all.
	 * @return array The specified response or all the responses.
	 */
	public function resultRaw(int $response_number = null): array
	{
		if (!isset($response_number))
		{
			return $this->response;
		}
		else
		{
			$response_number = min($response_number, count($this->response) - 1);
			return $this->response[$response_number];
		}
	}

	/**
	 * Closes the socket connection.
	 */
	public function closeConnection(): void
	{
		if (!isset($this->fp))
			return;

		if (is_resource($this->fp))
			fclose($this->fp);

		unset($this->fp);
	}
}

?>