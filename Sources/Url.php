<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF;

use SMF\Db\DatabaseApi as Db;
use SMF\Tasks\UpdateTldRegex;
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

	/*****************
	 * Class constants
	 *****************/

	 public const SCHEME_HTTPS = 'https';
	 public const SCHEME_HTTP = 'http';
	 public const SCHEME_GRAVATAR = 'gravatar';

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var ?string
	 *
	 * The scheme component of the URL.
	 */
	public ?string $scheme = null;

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

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * The 2012 list of top level domains, excluding ccTLDs.
	 */
	public static $basic_tlds = [
		'com', 'net', 'org', 'edu', 'gov', 'mil', 'aero', 'asia', 'biz', 'cat',
		'coop', 'info', 'int', 'jobs', 'mobi', 'museum', 'name', 'post', 'pro',
		'tel', 'travel', 'xxx',
	];

	/**
	 * @var array
	 *
	 * Country code top level domains.
	 */
	public static $cc_tlds = [
		'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'ao', 'aq', 'ar', 'as',
		'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd', 'be', 'bf', 'bg', 'bh',
		'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv', 'bw', 'by', 'bz',
		'ca', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co',
		'cr', 'cu', 'cv', 'cx', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do', 'dz',
		'ec', 'ee', 'eg', 'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo',
		'fr', 'ga', 'gb', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn',
		'gp', 'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hm', 'hn', 'hr',
		'ht', 'hu', 'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it',
		'je', 'jm', 'jo', 'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr',
		'kw', 'ky', 'kz', 'la', 'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu',
		'lv', 'ly', 'ma', 'mc', 'md', 'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn',
		'mo', 'mp', 'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'my', 'mz',
		'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz',
		'om', 'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl', 'pm', 'pn', 'pr', 'ps',
		'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru', 'rw', 'sa', 'sb', 'sc',
		'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn', 'so', 'sr',
		'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz', 'tc', 'td', 'tf', 'tg', 'th',
		'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz', 'ua',
		'ug', 'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu',
		'wf', 'ws', 'ye', 'yt', 'za', 'zm', 'zw',
	];

	/**
	 * @var array
	 *
	 * "Special use domain names" that aren't in DNS but may possibly resolve.
	 *
	 * See https://www.iana.org/assignments/special-use-domain-names.
	 */
	public static $special_use_tlds = ['local', 'onion', 'test'];

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
		return (string) $this->url;
	}

	/**
	 * Converts an IRI (a URL with international characters) into an ASCII URL.
	 *
	 * Uses Punycode to encode any non-ASCII characters in the domain name, and
	 * uses standard URL encoding on the rest.
	 *
	 * @return self A reference to this object for method chaining.
	 */
	public function toAscii(): self
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
	 * @return self A reference to this object for method chaining.
	 */
	public function toUtf8(): self
	{
		// Bail out if we can be sure that it contains no international characters, encoded or otherwise.
		if ($this->is_ascii && !str_contains($this->host ?? '', 'xn--') && !str_contains($this->url, '%')) {
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
	 *
	 * @return self A reference to this object for method chaining.
	 */
	public function normalize(): self
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
	 * @return self A reference to this object for method chaining.
	 */
	public function sanitize(): self
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

		if (str_starts_with($ascii_url, '//')) {
			$ascii_url = 'http:' . $ascii_url;
		}

		return filter_var($ascii_url, FILTER_VALIDATE_URL, $flags) !== false;
	}

	/**
	 * Checks whether this is a valid IRI, and sets $this->url to '' if not.
	 *
	 * @param int $flags Optional flags for filter_var's third parameter.
	 * @return self A reference to this object for method chaining.
	 */
	public function validate(int $flags = 0): self
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
	 * @return string|int|array|null|bool Same as parse_url(), but with unmangled Unicode.
	 */
	public function parse(int $component = -1): string|int|array|null|bool
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
				$this->{$prop} = $parsed[$prop] = is_string($parsed[$prop]) ? rawurldecode($parsed[$prop]) : $parsed[$prop];
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
	 * @return self A new instance of this class for the proxied URL.
	 */
	public function proxied(): self
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
			$proxied->parse();

			return $proxied;
		}

		// By default, use SMF's own image proxy script.
		$proxied->url = strtr(Config::$boardurl, ['http://' => 'https://']) . '/proxy.php?request=' . urlencode($proxied->url) . '&hash=' . hash_hmac('sha1', $proxied->url, Config::$image_proxy_secret);

		// Allow mods to easily implement an alternative proxy.
		// MOD AUTHORS: To add settings UI for your proxy, use the integrate_general_settings hook.
		IntegrationHook::call('integrate_proxy', [$this->url, &$proxied->url]);

		$proxied->parse();

		return $proxied;
	}

	/**
	 * Checks if this URL has an SSL certificate.
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
	 * Checks if this URL has a redirect to https:// by querying headers.
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

	/**
	 * Checks if this URL points to a website.
	 *
	 * @return bool Whether the URL matches the https or http schemes.
	 */
	public function isWebsite(): bool
	{
		return $this->isScheme([self::SCHEME_HTTP, self::SCHEME_HTTPS]);
	}

	/**
	 * Check if this URL uses one of the specified schemes.
	 *
	 * @param string|string[] $scheme Schemes to check.
	 * @return bool Whether the URL matches a scheme.
	 */
	public function isScheme(string|array $scheme): bool
	{
		return !empty($this->scheme) && in_array($this->scheme, array_map('strval', (array) $scheme));
	}

	/**
	 * Checks if this is a Gravatar URL.
	 *
	 * @return bool Whether this is a Gravatar URL.
	 */
	public function isGravatar(): bool
	{
		return
			$this->isScheme(self::SCHEME_GRAVATAR)
			|| $this->url === 'gravatar://'
			|| (
				!empty($this->host)
				&& (
					$this->host === 'gravatar.com'
					||  $this->host === 'secure.gravatar.com'
				)
			);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience wrapper for constructor.
	 *
	 * This is just syntactical sugar to ease method chaining.
	 *
	 * @param string $url The URL or IRI.
	 * @param bool $normalize Whether to normalize the URL during construction.
	 *    Default: false.
	 * @return self The created object.
	 */
	public static function create(string $url, bool $normalize = false): self
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
			if (md5((string) $tlds) != substr((string) $tlds_md5, 0, 32)) {
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
			$tlds = array_merge(self::$basic_tlds, self::$cc_tlds);

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
						UpdateTldRegex::class,
						'',
						0,
					],
					[],
				);
			}
		}

		// Tack on some "special use domain names" that aren't in DNS but may possibly resolve.
		$tlds = array_merge($tlds, self::$special_use_tlds);

		// Get an optimized regex to match all the TLDs
		$tld_regex = Utils::buildRegex($tlds);

		// Remember the new regex in Config::$modSettings
		Config::updateModSettings(['tld_regex' => $tld_regex]);

		// Update the editor's autolinker JavaScript.
		if ($update) {
			Autolinker::createJavaScriptFile(true);
		}

		// Redundant repetition is redundant
		$done = true;
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

?>