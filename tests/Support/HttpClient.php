<?php

declare(strict_types=1);

namespace SMF\Tests\Support;

use SMF\Config;

/**
 * HTTP client used by integration tests.
 *
 * Maintains a persistent cURL handle and cookie jar so that multiple requests
 * can be made as the same HTTP session.
 *
 * The base URL can be configured with SMF_TESTS_BASE_URL. When HTTP logging is
 * enabled with SMF_TESTS_ENABLE_HTTP_LOGGING=1, request/response data is
 * written to the test HTTP log directory for debugging failed requests.
 */
final class HttpClient
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string Where requests go. Public so a test can report it on failure.
	 */
	public readonly string $base_url;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var \CurlHandle One handle for the life of the client.
	 *
	 * Reused rather than opened per request, and that is load bearing. curl only
	 * writes cookies that carry an expiry to the jar file; a session cookie has
	 * none, so closing the handle between requests threw the session away and
	 * every request arrived as a brand new visitor. The symptom is not an obvious
	 * one - pages still render, but any POST is rejected because the session
	 * check and the security token it carries were issued to a session that no
	 * longer exists.
	 */
	private \CurlHandle $handle;

	/**
	 * @var string Path to this client's cookie jar.
	 */
	private string $jar;

	/**
	 * @var HttpResponse|null The most recent response.
	 */
	private ?HttpResponse $last = null;

	/**
	 * Directory where HTTP request/response logs are written.
	 */
	private string $log_dir = '';

	/**
	 * Whether HTTP request/response logging is enabled.
	 */
	private bool $http_logging = false;

	/**
	 * Sequence number used to make log filenames unique.
	 */
	private int $log_sequence = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Creates an HTTP client.
	 *
	 * @param string|null $base_url Override the forum URL to talk to.
	 */
	public function __construct(?string $base_url = null)
	{
		$this->base_url = rtrim($base_url ?? self::detectBaseUrl(), '/');
		$this->http_logging = getenv('SMF_TESTS_ENABLE_HTTP_LOGGING') === '1';

		if ($this->http_logging) {
			$this->log_dir = \dirname(__DIR__, 2) . '/logs/http';

			if (!is_dir($this->log_dir) && !mkdir($this->log_dir, 0777, true) && !is_dir($this->log_dir)) {
				throw new \RuntimeException('Could not create HTTP log directory: ' . $this->log_dir);
			}
		}

		$this->jar = (string) tempnam(sys_get_temp_dir(), 'smf_tests_cookies_');

		$handle = curl_init();

		if (!$handle instanceof \CurlHandle) {
			throw new \RuntimeException('could not start curl');
		}

		$this->handle = $handle;
	}

	/**
	 * Removes the temporary cookie jar.
	 */
	public function __destruct()
	{
		if ($this->jar !== '' && is_file($this->jar)) {
			@unlink($this->jar);
		}
	}

	/**
	 * Fetches a page.
	 *
	 * @param string $path Either a full URL or something to hang off the board
	 *     URL, with or without a leading slash. '?action=login' is typical.
	 *
	 * @return HttpResponse The response.
	 */
	public function get(string $path = ''): HttpResponse
	{
		return $this->request($path, null);
	}

	/**
	 * Posts to a page.
	 *
	 * @param string $path Where to post, as for get().
	 * @param array $fields The form fields.
	 *
	 * @return HttpResponse The response.
	 */
	public function post(string $path, array $fields): HttpResponse
	{
		return $this->request($path, $fields);
	}

	/**
	 * Submits a form on a page the client has already fetched.
	 *
	 * This is the method to reach for. SMF forms carry a session check that
	 * User::checkSession() rejects the request without, and often a SecurityToken
	 * as well, both named unpredictably per session; resubmitting every field the
	 * page offered is what a browser does and saves the test knowing about any of
	 * it.
	 *
	 * @param HttpResponse $page The page holding the form.
	 * @param array $overrides Values to change or add.
	 * @param string $xpath Which form. Defaults to the first on the page.
	 *
	 * @return HttpResponse The response.
	 */
	public function submit(HttpResponse $page, array $overrides = [], string $xpath = '//form'): HttpResponse
	{
		return $this->request(
			$page->formAction($xpath),
			array_merge($page->formFields($xpath), $overrides),
		);
	}

	/**
	 * Returns the most recent response, for reporting on a failure.
	 *
	 * @return HttpResponse|null The response, or null if nothing has been sent.
	 */
	public function lastResponse(): ?HttpResponse
	{
		return $this->last;
	}

	/**
	 * Throws away this client's cookies, making it a fresh visitor.
	 */
	public function forgetCookies(): void
	{
		// In memory, not in the file: session cookies never reach the file.
		curl_setopt($this->handle, CURLOPT_COOKIELIST, 'ALL');

		if (is_file($this->jar)) {
			file_put_contents($this->jar, '');
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Turns whatever a caller passed into an absolute URL.
	 *
	 * @param string $path A full URL, a query string, or a path.
	 *
	 * @return string An absolute URL.
	 */
	private function url(string $path): string
	{
		if ($path === '') {
			return $this->base_url . '/';
		}

		// A form action is usually an absolute URL built from Config::$boardurl,
		// which is not necessarily the host we are talking to - inside the
		// container the forum answers on port 80 while boardurl names 8080. Keep
		// the path and query, drop the rest.
		if (preg_match('~^https?://~i', $path)) {
			$parts = parse_url($path);

			$path = (isset($parts['query']) ? '?' . $parts['query'] : '')
				. (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
		}

		if (str_starts_with($path, '?')) {
			return $this->base_url . '/index.php' . $path;
		}

		return $this->base_url . '/' . ltrim($path, '/');
	}

	/**
	 * Sends one request.
	 *
	 * @param string $path A full URL, a query string, or a path.
	 * @param array|null $fields POST fields, or null for a GET.
	 * @return HttpResponse The response.
	 */
	private function request(string $path, ?array $fields): HttpResponse
	{
		$handle = $this->handle;

		$url = $this->url($path);
		$options = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			// Off on purpose. A redirect is frequently the thing under test -
			// posting a reply is a success only if it sends you somewhere - and
			// following it silently would hide both the status and the location.
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_COOKIEJAR => $this->jar,
			CURLOPT_COOKIEFILE => $this->jar,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_USERAGENT => 'SMF test suite',
		];

		if ($fields !== null) {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = http_build_query($fields);
		} else {
			// The handle is reused, so a GET after a POST has to say so or it
			// would repeat the previous body.
			$options[CURLOPT_HTTPGET] = true;
		}

		curl_setopt_array($handle, $options);

		$raw = curl_exec($handle);

		if ($raw === false) {
			throw new \RuntimeException('request to ' . $url . ' failed: ' . curl_error($handle));
		}

		$status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
		$header_size = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);

		$raw = (string) $raw;

		[$headers, $set_cookies] = self::parseHeaders(substr($raw, 0, $header_size));

		if ($this->http_logging) {
			$this->logResponse($url, $fields, $raw);
		}

		return $this->last = new HttpResponse(
			$status,
			substr($raw, $header_size),
			$headers,
			$url,
			$set_cookies,
		);
	}

	/**
	 * Writes an HTTP request and response to the debug log.
	 *
	 * @param string $url The resolved request URL.
	 * @param array|null $fields POST fields, or null for a GET.
	 * @param string $raw The raw HTTP response, including headers.
	 */
	private function logResponse(string $url, ?array $fields, string $raw): void
	{
		++$this->log_sequence;

		$parts = parse_url($url);

		$name = isset($parts['query']) ? '_' . $parts['query'] : '';

		$name = trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_');
		$name = substr($name, 0, 100);

		$file = \sprintf(
			'%s/%03d_%s.html',
			$this->log_dir,
			$this->log_sequence,
			$name !== '' ? $name : 'index',
		);

		file_put_contents(
			$file,
			$url . "\n\n"
			. print_r($fields ?? [], true)
			. $raw,
		);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Works out which URL the forum answers on.
	 *
	 * SMF_TESTS_BASE_URL wins. Otherwise it is Config::$boardurl, unless nothing
	 * is listening there - which is the normal case when the tests run inside the
	 * web container, where the forum is on port 80 and boardurl names whatever
	 * port the host publishes.
	 *
	 * @return string The base URL.
	 */
	private static function detectBaseUrl(): string
	{
		$override = (string) getenv('SMF_TESTS_BASE_URL');

		if ($override !== '') {
			return $override;
		}

		$boardurl = (string) (Config::$boardurl ?? '');

		if ($boardurl !== '' && self::listening($boardurl)) {
			return $boardurl;
		}

		$parts = parse_url($boardurl) ?: [];

		return ($parts['scheme'] ?? 'http') . '://localhost' . ($parts['path'] ?? '');
	}

	/**
	 * Whether anything answers on the host and port of a URL.
	 *
	 * @param string $url The URL to try.
	 *
	 * @return bool Whether a connection could be opened.
	 */
	private static function listening(string $url): bool
	{
		$parts = parse_url($url);

		if (!isset($parts['host'])) {
			return false;
		}

		$port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);

		$socket = @fsockopen($parts['host'], (int) $port, $errno, $errstr, 2);

		if ($socket === false) {
			return false;
		}

		fclose($socket);

		return true;
	}

	/**
	 * Splits a raw header block into name => value.
	 *
	 * Only the last set is kept, which matters because CURLOPT_HEADER includes
	 * every hop when a proxy or a 100-continue is involved.
	 *
	 * @param string $raw The raw headers.
	 * @return array Two items: the headers as lowercased name => value, and
	 *     every Set-Cookie value in order.
	 */
	private static function parseHeaders(string $raw): array
	{
		$headers = [];
		$cookies = [];

		foreach (preg_split('~\R~', $raw) ?: [] as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if (stripos($line, 'HTTP/') === 0) {
				$headers = [];
				$cookies = [];

				continue;
			}

			$colon = strpos($line, ':');

			if ($colon === false) {
				continue;
			}

			$name = strtolower(substr($line, 0, $colon));
			$value = trim(substr($line, $colon + 1));

			$headers[$name] = $value;

			if ($name === 'set-cookie') {
				$cookies[] = $value;
			}
		}

		return [$headers, $cookies];
	}
}
