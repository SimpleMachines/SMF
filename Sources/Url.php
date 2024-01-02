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

use SMF\Db\DatabaseApi as Db;
use SMF\WebFetch\WebFetchApi;

/**
 * Represents a URL string and allows performing various operations on the URL.
 *
 * Most importantly, this class allows transparent handling of URLs that contain
 * international characters (a.k.a. IRIs), so that they can easily be sanitized,
 * normalized, validated, etc. This class also makes it easy to convert IRIs to
 * raw ASCII URLs and back.
 */
class Url implements \Stringable
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'setTldRegex' => 'set_tld_regex',
			'parseIri' => 'parse_iri',
			'validateIri' => 'validate_iri',
			'sanitizeIri' => 'sanitize_iri',
			'normalizeIri' => 'normalize_iri',
			'iriToUrl' => 'iri_to_url',
			'urlToIri' => 'url_to_iri',
			'getProxiedUrl' => 'get_proxied_url',
			'sslCertFound' => 'ssl_cert_found',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The scheme component of the URL.
	 */
	public string $scheme;

	/**
	 * @var string
	 *
	 * The host component of the URL.
	 */
	public string $host;

	/**
	 * @var int
	 *
	 * The port component of the URL.
	 */
	public int $port;

	/**
	 * @var string
	 *
	 * The user component of the URL.
	 */
	public string $user;

	/**
	 * @var string
	 *
	 * The password component of the URL.
	 */
	public string $pass;

	/**
	 * @var string
	 *
	 * The path component of the URL.
	 */
	public string $path;

	/**
	 * @var string
	 *
	 * The query component of the URL.
	 */
	public string $query;

	/**
	 * @var string
	 *
	 * The fragment component of the URL.
	 */
	public string $fragment;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string|false
	 *
	 * The URL string, or false if invalid.
	 */
	protected $url;

	/**
	 * @var bool
	 *
	 * Whether this contains only ASCII characters.
	 *
	 * If not set, unknown.
	 */
	protected $is_ascii;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $url The URL or IRI.
	 * @param bool $normalize Whether to normalize the URL during construction.
	 *    Default: false.
	 */
	public function __construct(string $url, bool $normalize = false)
	{
		$this->url = $url;

		// Clean it up?
		if ($normalize) {
			// normalize() will call parse() for us.
			$this->normalize();
		} else {
			$this->parse();
		}

		$this->checkIfAscii();
	}

	/**
	 * Return the string.
	 */
	public function __toString(): string
	{
		return $this->url;
	}

	/**
	 * Converts an IRI (a URL with international characters) into an ASCII URL.
	 *
	 * Uses Punycode to encode any non-ASCII characters in the domain name, and
	 * uses standard URL encoding on the rest.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	public function toAscii(): object
	{
		// Nothing to do if it is already ASCII.
		if ($this->is_ascii) {
			return $this;
		}

		if (!empty($this->host)) {
			if (!function_exists('idn_to_ascii')) {
				require_once Config::$sourcedir . '/Subs-Compat.php';
			}

			// Convert the host using the Punycode algorithm
			$encoded_host = idn_to_ascii($this->host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

			$pos = strpos($this->url, $this->host);
		} else {
			$encoded_host = '';
			$pos = 0;
		}

		$before_host = substr($this->url, 0, $pos);
		$after_host = substr($this->url, $pos + strlen($this->host ?? ''));

		// Encode any disallowed characters in the rest of the URL
		$unescaped = [
			'%21' => '!', '%23' => '#', '%24' => '$', '%26' => '&',
			'%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*',
			'%2B' => '+', '%2C' => ',', '%2F' => '/', '%3A' => ':',
			'%3B' => ';', '%3D' => '=', '%3F' => '?', '%40' => '@',
			'%25' => '%',
		];

		$before_host = strtr(rawurlencode($before_host), $unescaped);
		$after_host = strtr(rawurlencode($after_host), $unescaped);

		$this->url = $before_host . $encoded_host . $after_host;
		$this->is_ascii = true;

		$this->parse();

		return $this;
	}

	/**
	 * Decodes a URL containing encoded international characters to UTF-8.
	 *
	 * Decodes any Punycode encoded characters in the domain name, then uses
	 * standard URL decoding on the rest.
	 *
	 * @return object A reference to this object for method chaining.
	 */
	public function toUtf8(): object
	{
		// Bail out if we can be sure that it contains no international characters, encoded or otherwise.
		if ($this->is_ascii && strpos($this->host ?? '', 'xn--') === false && strpos($this->url, '%') === false) {
			return $this;
		}

		if (!empty($this->host)) {
			if (!function_exists('idn_to_utf8')) {
				require_once Config::$sourcedir . '/Subs-Compat.php';
			}

			// Decode the domain from Punycode.
			$decoded_host = idn_to_utf8($this->host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
			$pos = strpos($this->url, $this->host);
		} else {
			$decoded_host = '';
			$pos = 0;
		}

		$before_host = substr($this->url, 0, $pos);
		$after_host = substr($this->url, $pos + strlen($this->host ?? ''));

		// Decode the rest of the URL, but preserve escaped URL syntax characters.
		$double_escaped = [
			'%21' => '%2521', '%23' => '%2523', '%24' => '%2524', '%26' => '%2526',
			'%27' => '%2527', '%28' => '%2528', '%29' => '%2529', '%2A' => '%252A',
			'%2B' => '%252B', '%2C' => '%252C', '%2F' => '%252F', '%3A' => '%253A',
			'%3B' => '%253B', '%3D' => '%253D', '%3F' => '%253F', '%40' => '%2540',
			'%25' => '%2525',
		];

		$before_host = rawurldecode(strtr($before_host, $double_escaped));
		$after_host = rawurldecode(strtr($after_host, $double_escaped));

		$this->url = $before_host . $decoded_host . $after_host;
		$this->checkIfAscii();

		$this->parse();

		return $this;
	}

	/**
	 * Performs Unicode normalization on the URL.
	 *
	 * Internally calls $this->sanitize(), then performs Unicode normalization on the
	 * URL as a whole, using NFKC normalization for the domain name (see RFC 3491)
	 * and NFC normalization for the rest.
	 */
	public function normalize(): object
	{
		// Make sure it is in Unicode normalization form C.
		$this->url = Utils::normalize($this->url);

		$this->sanitize();

		if (!empty($this->host)) {
			$normalized_host = Utils::normalize($this->host, 'kc_casefold');
			$pos = strpos($this->url, $this->host);
		} else {
			$normalized_host = '';
			$pos = 0;
		}

		$before_host = substr($this->url, 0, $pos);
		$after_host = substr($this->url, $pos + strlen($this->host ?? ''));

		$this->url = $before_host . $normalized_host . $after_host;

		$this->parse();

		return $this;
	}

	/**
	 * Removes illegal characters from the URL.
	 *
	 * Unlike `filter_var($url, FILTER_SANITIZE_URL)`, this correctly handles
	 * URLs with international characters (a.k.a. IRIs).
	 *
	 * @return object A reference to this object for method chaining.
	 */
	public function sanitize(): object
	{
		// Encode any non-ASCII characters (but not space or control characters of any sort)
		// Also encode '%' in order to preserve anything that is already percent-encoded.
		$url = preg_replace_callback(
			'~[^\x00-\x7F\pZ\pC]|%~u',
			function ($matches) {
				return rawurlencode($matches[0]);
			},
			$this->url,
		);

		// Perform normal sanitization
		$url = filter_var($url, FILTER_SANITIZE_URL);

		// Decode the non-ASCII characters
		$this->url = rawurldecode($url);

		$this->parse();

		return $this;
	}

	/**
	 * Checks whether this is a valid IRI. Makes no changes.
	 *
	 * Similar to `filter_var($url, FILTER_SANITIZE_URL, $flags)`, except that
	 * it correctly handles URLs with international characters (a.k.a. IRIs), it
	 * recognizes schemeless URLs like '//www.example.com', and it only returns
	 * a boolean rather than a mixed value.
	 *
	 * @param int $flags Optional flags for filter_var's third parameter.
	 * @return bool Whether this is a valid IRI.
	 */
	public function isValid(int $flags = 0): bool
	{
		$ascii_url = $this->is_ascii ? $this->url : (string) (clone $this)->toAscii();

		if (strpos($ascii_url, '//') === 0) {
			$ascii_url = 'http:' . $ascii_url;
		}

		return filter_var($ascii_url, FILTER_VALIDATE_URL, $flags) !== false;
	}

	/**
	 * Checks whether this is a valid IRI, and sets $this->url to '' if not.
	 *
	 * @param int $flags Optional flags for filter_var's third parameter.
	 * @return object A reference to this object for method chaining.
	 */
	public function validate(int $flags = 0): object
	{
		if (!$this->isValid($flags)) {
			$this->url = '';
			$this->parse();
		}

		return $this;
	}

	/**
	 * A wrapper for `parse_url()` that can handle URLs with international
	 * characters (a.k.a. IRIs)
	 *
	 * @param int $component Optional flag for parse_url's second parameter.
	 * @return mixed Same as parse_url(), but with unmangled Unicode.
	 */
	public function parse(int $component = -1): mixed
	{
		$url = preg_replace_callback(
			'~[^\x00-\x7F\pZ\pC]|%~u',
			function ($matches) {
				return rawurlencode($matches[0]);
			},
			$this->url,
		);

		$parsed = parse_url($url);

		foreach (['scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment'] as $prop) {
			// Clear out any old value.
			unset($this->{$prop});

			// Set the new value, if any.
			if (isset($parsed[$prop])) {
				$this->{$prop} = rawurldecode($parsed[$prop]);
			}
		}

		switch ($component) {
			case PHP_URL_SCHEME:
				return $this->scheme ?? null;

			case PHP_URL_HOST:
				return $this->host ?? null;

			case PHP_URL_PORT:
				return $this->port ?? null;

			case PHP_URL_USER:
				return $this->user ?? null;

			case PHP_URL_PASS:
				return $this->pass ?? null;

			case PHP_URL_PATH:
				return $this->path ?? null;

			case PHP_URL_QUERY:
				return $this->query ?? null;

			case PHP_URL_FRAGMENT:
				return $this->fragment ?? null;

			default:
				return $parsed;
		}
	}

	/**
	 * Gets the appropriate URL to use for images (or whatever) when using SSL.
	 *
	 * The returned URL may or may not be a proxied URL, depending on the
	 * situation.
	 *
	 * Mods can implement alternative proxies using the 'integrate_proxy' hook.
	 *
	 * @return object A new instance of this class for the proxied URL.
	 */
	public function proxied(): object
	{
		$proxied = clone $this;

		// Only use the proxy if enabled, and never for robots.
		if (empty(Config::$image_proxy_enabled) || !empty(User::$me->possibly_robot)) {
			return $proxied;
		}

		// Don't bother with HTTPS URLs, schemeless URLs, or obviously invalid URLs.
		if (empty($proxied->scheme) || empty($proxied->host) || empty($proxied->path) || $proxied->scheme === 'https') {
			return $proxied;
		}

		// We don't need to proxy our own resources.
		if ($proxied->host === Url::create(Config::$boardurl)->host) {
			$proxied->url = strtr($this->url, ['http://' => 'https://']);

			return $proxied;
		}

		// By default, use SMF's own image proxy script.
		$proxied->url = strtr(Config::$boardurl, ['http://' => 'https://']) . '/proxy.php?request=' . urlencode($proxied->url) . '&hash=' . hash_hmac('sha1', $proxied->url, Config::$image_proxy_secret);

		// Allow mods to easily implement an alternative proxy.
		// MOD AUTHORS: To add settings UI for your proxy, use the integrate_general_settings hook.
		IntegrationHook::call('integrate_proxy', [$this->url, &$proxied->url]);

		return $proxied;
	}

	/**
	 * Check if this URL has an SSL certificate.
	 *
	 * @return bool Whether the URL has an SSL certificate.
	 */
	public function hasSSL(): bool
	{
		// This check won't work without OpenSSL
		if (!extension_loaded('openssl')) {
			return true;
		}

		// First, strip the subfolder from the passed url, if any
		$ssl_url = 'ssl://' . $this->host . ':443';

		// Next, check the ssl stream context for certificate info
		$ssloptions = [
			'capture_peer_cert' => true,
			'verify_peer' => true,
			'allow_self_signed' => true,
		];

		$result = false;

		$stream_context = stream_context_create(['ssl' => $ssloptions]);

		$stream = @stream_socket_client($ssl_url, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $stream_context);

		if ($stream !== false) {
			$params = stream_context_get_params($stream);
			$result = isset($params['options']['ssl']['peer_certificate']) ? true : false;
		}

		return $result;
	}

	/**
	 * Check if this URL has a redirect to https:// by querying headers.
	 *
	 * @return bool Whether a redirect to HTTPS was found.
	 */
	public function redirectsToHttps(): bool
	{
		// Ask for the headers for the passed URL, but via HTTP.
		// Need to add the trailing slash for empty paths, or it puts it there and
		// thinks there's a redirect when there isn't.
		$http_url = 'http://' . $this->host . (empty($this->path) ? '/' : $this->path);

		$headers = @get_headers($http_url);

		if ($headers === false) {
			return false;
		}

		// Now to see if it came back HTTPS.
		// First check for a redirect status code in first row (301, 302, 307).
		if (strstr($headers[0], '301') === false && strstr($headers[0], '302') === false && strstr($headers[0], '307') === false) {
			return false;
		}

		// Search for the location entry to confirm HTTPS.
		foreach ($headers as $header) {
			if (stristr($header, 'Location: https://') !== false) {
				return true;
			}
		}

		return false;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constuctor.
	 *
	 * This is just syntactical sugar to ease method chaining.
	 *
	 * @param string $url The URL or IRI.
	 * @param bool $normalize Whether to normalize the URL during construction.
	 *    Default: false.
	 * @return object The created object.
	 */
	public static function create(string $url, bool $normalize = false): object
	{
		return new self($url, $normalize);
	}

	/**
	 * Creates an optimized regex to match all known top level domains.
	 *
	 * The optimized regex is stored in Config::$modSettings['tld_regex'].
	 *
	 * To update the stored version of the regex to use the latest list of valid
	 * TLDs from iana.org, set the $update parameter to true. Updating can take some
	 * time, based on network connectivity, so it should normally only be done by
	 * calling this function from a background or scheduled task.
	 *
	 * If $update is not true, but the regex is missing or invalid, the regex will
	 * be regenerated from a hard-coded list of TLDs. This regenerated regex will be
	 * overwritten on the next scheduled update.
	 *
	 * @param bool $update If true, fetch and process the latest official list of TLDs from iana.org.
	 */
	public static function setTldRegex(bool $update = false): void
	{
		static $done = false;

		// If we don't need to do anything, don't
		if (!$update && $done) {
			return;
		}

		// Should we get a new copy of the official list of TLDs?
		if ($update) {
			$tlds = WebFetchApi::fetch('https://data.iana.org/TLD/tlds-alpha-by-domain.txt');
			$tlds_md5 = WebFetchApi::fetch('https://data.iana.org/TLD/tlds-alpha-by-domain.txt.md5');

			/*
			 * If the Internet Assigned Numbers Authority can't be reached,
			 * the Internet is GONE! We're probably running on a server hidden
			 * in a bunker deep underground to protect it from marauding bandits
			 * roaming on the surface. We don't want to waste precious
			 * electricity on pointlessly repeating background tasks, so we'll
			 * wait until the next regularly scheduled update to see if
			 * civilization has been restored.
			 */
			if ($tlds === false || $tlds_md5 === false) {
				$postapocalypticNightmare = true;
			}

			// Make sure nothing went horribly wrong along the way.
			if (md5($tlds) != substr($tlds_md5, 0, 32)) {
				$tlds = [];
			}
		}
		// If we aren't updating and the regex is valid, we're done
		elseif (!empty(Config::$modSettings['tld_regex']) && @preg_match('~' . Config::$modSettings['tld_regex'] . '~', '') !== false) {
			$done = true;

			return;
		}

		// If we successfully got an update, process the list into an array
		if (!empty($tlds)) {
			// Clean $tlds and convert it to an array
			$tlds = array_filter(
				explode("\n", strtolower($tlds)),
				function ($line) {
					$line = trim($line);

					return !(empty($line) || strlen($line) != strspn($line, 'abcdefghijklmnopqrstuvwxyz0123456789-'));
				},
			);

			// Convert Punycode to Unicode
			if (!function_exists('idn_to_utf8')) {
				require_once Config::$sourcedir . '/Subs-Compat.php';
			}

			foreach ($tlds as &$tld) {
				$tld = idn_to_utf8($tld, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
			}
		}
		// Otherwise, use the 2012 list of gTLDs and ccTLDs for now and schedule a background update
		else {
			$tlds = ['com', 'net', 'org', 'edu', 'gov', 'mil', 'aero', 'asia', 'biz',
				'cat', 'coop', 'info', 'int', 'jobs', 'mobi', 'museum', 'name', 'post',
				'pro', 'tel', 'travel', 'xxx', 'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al',
				'am', 'ao', 'aq', 'ar', 'as', 'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd',
				'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv',
				'bw', 'by', 'bz', 'ca', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm',
				'cn', 'co', 'cr', 'cu', 'cv', 'cx', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do',
				'dz', 'ec', 'ee', 'eg', 'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo',
				'fr', 'ga', 'gb', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp',
				'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hm', 'hn', 'hr', 'ht', 'hu',
				'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it', 'je', 'jm', 'jo',
				'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr', 'kw', 'ky', 'kz', 'la',
				'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'md',
				'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt',
				'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl',
				'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl',
				'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru', 'rw',
				'sa', 'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn',
				'so', 'sr', 'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz', 'tc', 'td', 'tf', 'tg',
				'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz', 'ua',
				'ug', 'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf',
				'ws', 'ye', 'yt', 'za', 'zm', 'zw',
			];

			// Schedule a background update, unless civilization has collapsed and/or we are having connectivity issues.
			if (empty($postapocalypticNightmare)) {
				Db::$db->insert(
					'insert',
					'{db_prefix}background_tasks',
					[
						'task_class' => 'string-255',
						'task_data' => 'string',
						'claimed_time' => 'int',
					],
					[
						'SMF\\Tasks\\UpdateTldRegex',
						'',
						0,
					],
					[],
				);
			}
		}

		// Tack on some "special use domain names" that aren't in DNS but may possibly resolve.
		// See https://www.iana.org/assignments/special-use-domain-names/ for more info.
		$tlds = array_merge($tlds, ['local', 'onion', 'test']);

		// Get an optimized regex to match all the TLDs
		$tld_regex = Utils::buildRegex($tlds);

		// Remember the new regex in Config::$modSettings
		Config::updateModSettings(['tld_regex' => $tld_regex]);

		// Redundant repetition is redundant
		$done = true;
	}

	/**
	 * Backward compatibility wrapper for the parse method.
	 *
	 * @param string $iri The IRI to parse.
	 * @param int $component Optional flag for parse_url's second parameter.
	 * @return mixed Same as parse_url(), but with unmangled Unicode.
	 */
	public static function parseIri(string $iri, int $component = -1): mixed
	{
		$iri = new self($iri);

		return $iri->parse($component);
	}

	/**
	 * Backward compatibility wrapper for the validate method.
	 *
	 * @param string $iri The IRI to parse.
	 * @param int $flags Optional flags for filter_var's third parameter.
	 * @return object|false A reference to an object for the IRI if it is valid,
	 *    or false if the IRI is invalid.
	 */
	public static function validateIri(string $iri, int $flags = 0): object|false
	{
		$iri = new self($iri);

		$iri->validate($flags);

		return $iri->url === '' ? false : $iri;
	}

	/**
	 * Backward compatibility method.
	 *
	 * @param string $iri The IRI to sanitize.
	 * @return object A reference to an object for the IRI.
	 */
	public static function sanitizeIri(string $iri): object
	{
		$iri = new self($iri);

		return $iri->sanitize();
	}

	/**
	 * Backward compatibility method.
	 *
	 * @param string $iri The IRI to normalize.
	 * @return object A reference to an object for the IRI.
	 */
	public static function normalizeIri(string $iri): object
	{
		$iri = new self($iri);

		return $iri->normalize();
	}

	/**
	 * Backward compatibility wrapper for the toAscii method.
	 *
	 * @param string $iri The IRI to convert to an ASCII URL.
	 * @return object A reference to an object for the URL.
	 */
	public static function iriToUrl(string $iri): object
	{
		$iri = new self($iri);

		return $iri->toAscii();
	}

	/**
	 * Backward compatibility wrapper for the toUtf8 method.
	 *
	 * @param string $url The URL to convert to an IRI.
	 * @return object A reference to an object for the IRI.
	 */
	public static function urlToIri(string $url): object
	{
		$url = new self($url);

		return $url->toUtf8();
	}

	/**
	 * Backward compatibility wrapper for the proxied method.
	 *
	 * @param string $url The original URL of the requested resource.
	 * @return Url A new instance of this class for the proxied URL.
	 */
	public static function getProxiedUrl(string $url): Url
	{
		$url = new self($url);

		return $url->proxied();
	}

	/**
	 * Backward compatibility wrapper for the hasSSL method.
	 *
	 * @param string $url The URL to check.
	 * @return bool Whether the URL has an SSL certificate.
	 */
	public static function sslCertFound(string $url): bool
	{
		$url = new self($url);

		return $url->hasSSL();
	}

	/**
	 * Backward compatibility wrapper for the redirectsToHttps method.
	 *
	 * @param string $url The URL to check.
	 * @return bool Whether a redirect to HTTPS was found.
	 */
	public function httpsRedirectActive(string $url): bool
	{
		$url = new self($url);

		return $url->redirectsToHttps();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks whether $this->url contains only ASCII characters.
	 * Sets the value of $this->is_ascii to the result.
	 */
	protected function checkIfAscii(): void
	{
		$this->is_ascii = mb_check_encoding($this->url, 'ASCII');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Url::exportStatic')) {
	Url::exportStatic();
}

?>