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

declare(strict_types=1);

namespace SMF\WebFetch\APIs;

use SMF\Config;
use SMF\Lang;
use SMF\PackageManager\FtpConnection;
use SMF\Url;
use SMF\WebFetch\WebFetchApi;

/**
 * Fetches data from FTP URLs.
 */
class FtpFetcher extends WebFetchApi
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
	 * @var string
	 *
	 * The user name to connect with.
	 */
	public string $user;

	/**
	 * @var string
	 *
	 * The email address to connect with.
	 */
	public string $email;

	/**
	 * @var array
	 *
	 * Stores responses (url, code, error, headers, body, size).
	 */
	public $response = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $user User name to connect with. If null, uses 'anonymous'.
	 * @param string $email Email address to connect with. Defaults to
	 *    Config::$webmaster_email, or 'nobody@example.com' if that is not set.
	 */
	public function __construct(?string $user = null, ?string $email = null)
	{
		$this->user = is_string($user) ? $user : 'anonymous';
		$this->email = is_string($email) ? $email : (Config::$webmaster_email ?? 'nobody@example.com');
	}

	/**
	 * Main calling function.
	 *
	 *  - Will request the page data from a given $url.
	 *  - Optionally will post data to the page form if $post_data is supplied.
	 *    Passed arrays will be converted to a POST string joined with &'s.
	 *
	 * @param string|Url $url the site we are going to fetch
	 * @return self A reference to the object for method chaining.
	 */
	public function request(string|Url $url, array|string $post_data = []): self
	{
		if (!$url instanceof Url) {
			$url = new Url($url, true);
			$url->toAscii();
		}

		// Umm, this shouldn't happen?
		if (empty($url->scheme) || !in_array($url->scheme, ['ftp', 'ftps'])) {
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['fetch_web_data_bad_url'], __METHOD__), E_USER_NOTICE);

			return $this;
		}

		$this->host = ($url->scheme === 'ftps' ? 'ssl://' : '') . $url->host;
		$this->port = !empty($url->port) ? $url->port : 21;

		// Establish a connection and attempt to enable passive mode.
		$ftp = new FtpConnection($this->host, $this->port, $this->user, $this->email);

		$this->error_message = !empty($ftp->error) ? (string) ($ftp->last_message ?? $ftp->error) : '';

		if ($ftp->error !== false) {
			return $this;
		}

		if (!$ftp->passive()) {
			$this->error_message = (string) ($ftp->last_message ?? $ftp->error);

			return $this;
		}

		// I want that one! *points*
		fwrite($ftp->connection, 'RETR ' . $url->path . (isset($url->query) && $url->query !== '' ? '?' . $url->query : '') . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $this->error_code, $this->error_message, 5);

		// We can start building our response data now.
		$this->response[0] = [
			'url' => (string) $url,
			'success' => false,
			'code' => null,
			'error' => $this->error_message ?? null,
			'headers' => [],
			'body' => null,
			'size' => 0,
		];

		if (!$fp) {
			return $this;
		}

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);
		$this->response[0]['code'] = substr($ftp->last_message, 0, 3);

		$body = '';

		while (!feof($fp)) {
			$body .= fread($fp, 4096);
		}

		fclose($fp);

		// All done, right?  Good.
		$this->response[0]['success'] = $ftp->check_response(226);
		$this->response[0]['code'] = substr($ftp->last_message, 0, 3);
		$ftp->close();

		$this->response[0]['body'] = $body;
		$this->response[0]['size'] = strlen($body);

		return $this;
	}

	/**
	 * Used to return the results to the caller.
	 *
	 *  - Called as ->result() will return the full final array.
	 *  - Called as ->result('body') to return the page source of the result.
	 *
	 * @param string $area Used to return an area such as body, header, error.
	 * @return mixed The response
	 */
	public function result(?string $area = null): mixed
	{
		// Just return a specifed area or the entire result?
		if (empty($area)) {
			return $this->response[0];
		}

		return $this->response[0][$area] ?? $this->response[0];
	}

	/**
	 * Since this class doesn't support redirects, this method is practically
	 * useless, but it's required to comply with FetcherApiInterface.
	 *
	 * @param int $response_number Which response to get, or null for all.
	 * @return array The specified response or all the responses.
	 */
	public function resultRaw(?int $response_number = null): array
	{
		if (!isset($response_number)) {
			return $this->response;
		}

		return $this->response[0];
	}
}

?>