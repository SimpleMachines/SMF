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

use SMF\Lang;
use SMF\Url;
use SMF\WebFetch\WebFetchApi;

/**
 * Class CurlFetcher
 *
 * Simple curl class to fetch a web page.
 * Properly redirects even with safe mode and basedir restrictions.
 * Can provide simple post options to a page.
 *
 * ### Load class
 * Initiate as
 * ```
 * $fetch_data = new CurlFetcher();
 * ```
 * Optionally pass an array of curl options and redirect count
 * ```
 * $fetch_data = new CurlFetcher(array(CURLOPT_SSL_VERIFYPEER => 1), 5);
 * ```
 *
 * ### Make the call
 * Fetch a page
 * ```
 * $fetch_data->request('https://www.simplemachines.org');
 * ```
 * Post to a page providing an array
 * ```
 * $fetch_data->request('https://www.simplemachines.org', array('user' => 'name', 'password' => 'password'));
 * ```
 * Post to a page providing a string
 * ```
 * $fetch_data->fetch('https://www.simplemachines.org', parameter1&parameter2&parameter3);
 * ```
 *
 * ### Get the data
 * Just the page content
 * ```
 * $fetch_data->result('body');
 * ```
 * An array of results, body, header, http result codes
 * ```
 * $fetch_data->result();
 * ```
 * Show all results of all calls (in the event of a redirect)
 * ```
 * $fetch_data->result_raw();
 * ```
 * Show the results of a specific call (in the event of a redirect)
 * ```
 * $fetch_data->result_raw(0);
 * ```
 */
class CurlFetcher extends WebFetchApi
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * Maximum number of redirects.
	 */
	public $max_redirect;

	/**
	 * @var array
	 *
	 * An array of curl options.
	 */
	public $user_options = [];

	/**
	 * @var string
	 *
	 * Any post data as form name => value.
	 */
	public $post_data;

	/**
	 * @var array
	 *
	 * An array of curl options.
	 */
	public $options;

	/**
	 * @var int
	 *
	 * How many redirects have been followed.
	 */
	public $current_redirect = 0;

	/**
	 * @var array
	 *
	 * Stores responses (url, code, error, headers, body, size).
	 */
	public $response = [];

	/**
	 * @var string
	 *
	 * The header.
	 */
	public $headers;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * The default curl options to use.
	 */
	private $default_options = [
		// Get returned value as a string (don't output it).
		CURLOPT_RETURNTRANSFER  => 1,

		// We need the headers to do our own redirect.
		CURLOPT_HEADER          => 1,

		// Don't follow. We will do it ourselves so safe mode and open_basedir
		// will dig it.
		CURLOPT_FOLLOWLOCATION  => 0,

		// Set a normal looking user agent.
		CURLOPT_USERAGENT       => SMF_USER_AGENT,

		// Don't wait forever on a connection.
		CURLOPT_CONNECTTIMEOUT  => 15,

		// A page should load in this amount of time.
		CURLOPT_TIMEOUT         => 90,

		// Stop after this many redirects.
		CURLOPT_MAXREDIRS       => 5,

		// Accept gzip and decode it.
		CURLOPT_ENCODING        => 'gzip,deflate',

		// Stop curl from verifying the peer's certificate.
		CURLOPT_SSL_VERIFYPEER  => 0,

		// Stop curl from verifying the peer's host.
		CURLOPT_SSL_VERIFYHOST  => 0,

		// No post data. This will change if some is passed to request().
		CURLOPT_POST            => 0,
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Start the curl object.
	 *
	 * - allow for user override values.
	 *
	 * @param array $options An array of curl options.
	 * @param int $max_redirect Maximum number of redirects.
	 */
	public function __construct(array $options = [], int $max_redirect = 3)
	{
		// Initialize class variables
		$this->max_redirect = intval($max_redirect);
		$this->user_options = $options;
	}

	/**
	 * Main calling function.
	 *
	 *  - Will request the page data from a given $url.
	 *  - Optionally will post data to the page form if $post_data is supplied.
	 *    Passed arrays will be converted to a POST string joined with &'s.
	 *  - Calls setOptions() to set the curl opts array values based on the
	 *    defaults and user input.
	 *
	 * @param string|Url $url the site we are going to fetch
	 * @param array|string $post_data any post data as form name => value
	 * @return object A reference to the object for method chaining.
	 */
	public function request(string|Url $url, array|string $post_data = []): object
	{
		if (!$url instanceof Url) {
			$url = new Url($url, true);
			$url->toAscii();
		}

		// If we can't do it, bail out.
		if (!function_exists('curl_init')) {
			$this->response[] = [
				'url' => (string) $url,
				'success' => false,
				'code' => null,
				'error' => null,
				'headers' => [],
				'body' => null,
				'size' => 0,
			];

			return $this;
		}

		// Umm, this shouldn't happen?
		if (empty($url->scheme) || !in_array($url->scheme, ['http', 'https'])) {
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['fetch_web_data_bad_url'], __METHOD__), E_USER_NOTICE);

			return $this;
		}

		// POSTing some data perhaps?
		if (!empty($post_data)) {
			$this->post_data = $this->buildPostData($post_data);
		}

		// Set the options and get it.
		$this->setOptions();
		$this->sendRequest(str_replace(' ', '%20', strval($url)));

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
		$max_result = count($this->response) - 1;

		// Just return a specifed area or the entire result?
		if (empty($area)) {
			return $this->response[$max_result];
		}

		return $this->response[$max_result][$area] ?? $this->response[$max_result];
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
	public function resultRaw(?int $response_number = null): array
	{
		if (!isset($response_number)) {
			return $this->response;
		}

		$response_number = min($response_number, count($this->response) - 1);

		return $this->response[$response_number];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Makes the actual curl call.
	 *
	 *  - Stores responses (url, code, error, headers, body) in the response
	 *    array.
	 *  - Detects 301, 302, 307 codes and will redirect to the given response
	 *    header location.
	 *
	 * @param string $url The site to fetch.
	 * @param bool $redirect Whether or not this was a redirect request.
	 */
	private function sendRequest(string $url, bool $redirect = false): void
	{
		// We do have a url, I hope.
		if ($url == '') {
			return;
		}

		$this->options[CURLOPT_URL] = $url;

		// If we have not already been redirected, set it up so we can if needed.
		if (!$redirect) {
			$this->current_redirect = 1;
			$this->response = [];
		}

		// Initialize the curl object and make the call.
		$cr = curl_init();
		curl_setopt_array($cr, $this->options);
		curl_exec($cr);

		// Get what was returned.
		$curl_info = curl_getinfo($cr);
		$curl_content = curl_multi_getcontent($cr);
		$url = $curl_info['url']; // Last effective URL
		$http_code = (string) $curl_info['http_code']; // Last HTTP code
		$body = (!curl_error($cr)) ? substr($curl_content, $curl_info['header_size']) : false;
		$error = (curl_error($cr)) ? curl_error($cr) : false;

		// Close this request.
		curl_close($cr);

		// Store this loop's data, someone may want all of these. :O
		$this->response[] = [
			'url' => $url,
			'success' => $error === false && $body !== false,
			'code' => $http_code,
			'error' => $error,
			'headers' => $this->headers ?? [],
			'body' => $body,
			'size' => $curl_info['download_content_length'],
		];

		// If this a redirect with a location header and we have not given up, then do it again.
		if (preg_match('~30[127]~i', $http_code) === 1 && $this->headers['location'] != '' && $this->current_redirect <= $this->max_redirect) {
			$this->current_redirect++;
			$header_location = $this->getRedirectUrl($url, $this->headers['location']);
			$this->redirect($header_location, $url);
		}
	}

	/**
	 * Used if being redirected to ensure we have a fully qualified address.
	 *
	 * @param string $last_url The URL we went to.
	 * @param string $new_url The URL we were redirected to.
	 * @return string The new URL that was in the HTTP header.
	 */
	private function getRedirectUrl(string $last_url = '', string $new_url = ''): string
	{
		// Get the elements for these urls.
		$last_url_parse = parse_url($last_url);
		$new_url_parse = parse_url($new_url);

		// Redirect headers are often incomplete or relative so we need to make sure they are fully qualified.
		$new_url_parse['scheme'] = $new_url_parse['scheme'] ?? $last_url_parse['scheme'];
		$new_url_parse['host'] = $new_url_parse['host'] ?? $last_url_parse['host'];
		$new_url_parse['path'] = $new_url_parse['path'] ?? $last_url_parse['path'];
		$new_url_parse['query'] = $new_url_parse['query'] ?? '';

		// Build the new URL that was in the http header.
		return $new_url_parse['scheme'] . '://' . $new_url_parse['host'] . $new_url_parse['path'] . (!empty($new_url_parse['query']) ? '?' . $new_url_parse['query'] : '');
	}

	/**
	 * Sets the final curl options for the current call.
	 *
	 *  - Overwrites our default values with user supplied ones or appends new
	 *    user ones to what we have.
	 *  - Sets the callback function now that $this is existing.
	 */
	private function setOptions(): void
	{
		// Callback to parse the returned headers, if any.
		$this->default_options[CURLOPT_HEADERFUNCTION] = [$this, 'headerCallback'];

		// Any user options to account for.
		if (is_array($this->user_options)) {
			$keys = array_merge(array_keys($this->default_options), array_keys($this->user_options));
			$vals = array_merge($this->default_options, $this->user_options);
			$this->options = array_combine($keys, $vals);
		} else {
			$this->options = $this->default_options;
		}

		// POST data options, here we don't allow any override.
		if (isset($this->post_data)) {
			$this->options[CURLOPT_POST] = 1;
			$this->options[CURLOPT_POSTFIELDS] = $this->post_data;
		}
	}

	/**
	 * Called to initiate a redirect from a 301, 302 or 307 header.
	 *
	 *  - Resets the curl options for the loop and sets the referrer flag.
	 *
	 * @param string $target_url The URL we want to redirect to.
	 * @param string $referrer_url The URL that we're redirecting from.
	 */
	private function redirect(string $target_url, string $referrer_url): void
	{
		// No, no, I last saw that over there... really, 301, 302, 307
		$this->setOptions();
		$this->options[CURLOPT_REFERER] = $referrer_url;
		$this->sendRequest($target_url, true);
	}

	/**
	 * Callback function to parse returned headers.
	 *
	 *  - Lowercases everything to make it consistent.
	 *
	 * @param \CurlHandle $cr The curl request.
	 * @param string $header The header.
	 * @return int The length of the header.
	 */
	private function headerCallback(\CurlHandle $cr, string $header): int
	{
		$temp = explode(': ', trim($header), 2);

		// Set proper headers only.
		if (isset($temp[0], $temp[1])) {
			$this->headers[strtolower($temp[0])] = strtolower(trim($temp[1]));
		}

		// Return the length of what was passed unless you want a Failed writing header error ;)
		return strlen($header);
	}
}

?>