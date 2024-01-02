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

namespace SMF\WebFetch;

use SMF\BackwardCompatibility;
use SMF\Lang;
use SMF\Url;

/**
 * Class SearchApi
 */
abstract class WebFetchApi implements WebFetchApiInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'fetch' => 'fetch_web_data',
		],
	];

	/*******************
	 * Public properties
	 *******************/

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

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Specifies the fetcher class or classes to try for any given URL scheme.
	 *
	 * Keys are URL schemes. Values are the names of one or more classes to try.
	 *
	 * Class names will be prepended with __NAMESPACE__ . '\APIs\'.
	 */
	public static $scheme_handlers = [
		'ftp' => ['FtpFetcher'],
		'ftps' => ['FtpFetcher'],
		'http' => ['SocketFetcher', 'CurlFetcher'],
		'https' => ['SocketFetcher', 'CurlFetcher'],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function request(string $url, array|string $post_data = []): ?object
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function result(?string $area = null): mixed
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function resultRaw(?int $response_number = null): array
	{
		return [];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Get the contents of a URL, irrespective of allow_url_fopen.
	 *
	 * - Reads the contents of an HTTP or FTP address and returns those contents
	 *   in a string.
	 * - If $post_data is supplied, the value and length is posted to the given
	 *   URL as form data.
	 *
	 * @param string $url An HTTP or FTP URL.
	 * @param string|array $post_data The data to post to the given URL.
	 *    Not applicable to FTP requests.
	 * @param bool $keep_alive Whether to keep the connection alive for further
	 *    requests. Not applicable to FTP requests.
	 * @return string|false The fetched data or false on failure.
	 */
	public static function fetch(string $url, string|array $post_data = [], bool $keep_alive = false): string|false
	{
		$url = Url::create($url, true)->validate()->toAscii();

		// No scheme? No data for you!
		if (empty($url->scheme) || !isset(self::$scheme_handlers[$url->scheme])) {
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['fetch_web_data_bad_url'], __METHOD__), E_USER_NOTICE);
			$data = false;
		}

		foreach (self::$scheme_handlers[$url->scheme] as $class) {
			$class = __NAMESPACE__ . '\\APIs\\' . $class;

			$fetcher = new $class();
			$fetcher->request($url);

			if ($fetcher->result('success')) {
				break;
			}
		}

		if (!$fetcher->result('success')) {
			return false;
		}

		return $fetcher->result('body');
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Takes supplied POST data and urlencodes it.
	 *
	 *  - Forms the data (for POST) into a string like var=xyz&var2=abc&var3=123
	 *  - Drops vars with @ since we don't support sending files (uploading)
	 *
	 * @param array|string $post_data The raw POST data.
	 * @return string A string of POST data.
	 */
	protected function buildPostData(array|string $post_data): string
	{
		if (is_array($post_data)) {
			// Drop ones with leading @'s since those can be used to send files
			// and we don't support that.
			foreach ($post_data as $name => $value) {
				if (strpos($value, '@') === 0) {
					$post_data[$name] = null;
				}
			}

			return http_build_query($post_data, '', '&');
		}

		return trim($post_data);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\WebFetchApi::exportStatic')) {
	WebFetchApi::exportStatic();
}

?>