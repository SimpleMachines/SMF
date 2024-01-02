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

/**
 * Represents a cookie.
 */
class Cookie
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'setLoginCookie' => 'setLoginCookie',
			'setTFACookie' => 'setTFACookie',
			'urlParts' => 'url_parts',
			'encrypt' => 'hash_salt',
			'setcookie' => 'smf_setcookie',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The name of the cookie.
	 */
	public string $name;

	/**
	 * @var mixed
	 *
	 * Arbitrary data to include in the cookie.
	 *
	 * MOD AUTHORS: if you want to add data to the cookie, this is the place.
	 */
	public $custom_data;

	/**
	 * @var int
	 *
	 * The member this cookie is for.
	 *
	 * Only normally used for login and TFA cookies.
	 * Either User::$me->id, or 0 when forcing a logout.
	 */
	public int $member;

	/**
	 * @var string
	 *
	 * Hashed password or TFA secret, or '' when forcing a logout.
	 *
	 * Only normally used for login and TFA cookies.
	 * This is an HMAC hash of an already hashed value.
	 */
	public string $hash;

	/**
	 * @var int
	 *
	 * UNIX timestamp of the expiry date of the cookie.
	 */
	public int $expires;

	/**
	 * @var string
	 *
	 * The domain of the site where the cookie is used.
	 * This is normally the domain name of the forum's site.
	 */
	public string $domain;

	/**
	 * @var string
	 *
	 * The path to the part of the site where the cookie is used.
	 * This is normally the URL path to the forum.
	 */
	public string $path;

	/**
	 * @var bool
	 *
	 * Whether the cookie must be secure.
	 */
	public bool $secure;

	/**
	 * @var bool
	 *
	 * Whether the cookie can only be used for HTTP requests.
	 */
	public bool $httponly;

	/**
	 * @var string
	 *
	 * Value for the cookie's 'SameSite' attribute.
	 */
	public string $samesite;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var string
	 *
	 * The domain of the site where the cookie is used.
	 * This is normally the domain name of the forum's site.
	 */
	public static string $default_domain;

	/**
	 * @var string
	 *
	 * The path to the part of the site where the cookie is used.
	 * This is normally the URL path to the forum.
	 */
	public static string $default_path;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $name Name of the cookie.
	 * @param mixed $custom_data Data to include in the cookie.
	 * @param int $expires When the cookie expires.
	 *    If not set, determined automatically.
	 * @param string $domain The domain of the site where the cookie is used.
	 *    If not set, determined automatically.
	 * @param string $path The part of the site where the cookie is used.
	 *    If not set, determined automatically.
	 * @param bool $secure Whether cookie must be secure.
	 *    If not set, determined by Config::$modSettings['secureCookies'].
	 * @param bool $httponly Whether cookie can only be used for HTTP requests.
	 *    If not set, determined by Config::$modSettings['httponlyCookies'].
	 * @param string $samesite Value for the cookie's 'SameSite' attribute.
	 *    If not set, determined by Config::$modSettings['samesiteCookies'].
	 */
	public function __construct(
		?string $name = null,
		mixed $custom_data = null,
		?int $expires = null,
		?string $domain = null,
		?string $path = null,
		?bool $secure = null,
		?bool $httponly = null,
		?string $samesite = null,
	) {
		self::setDefaults();

		$this->name = $name ?? Config::$cookiename;

		$this->custom_data = $custom_data ?? [];

		// Special case for the login and TFA cookies.
		if (in_array($this->name, [Config::$cookiename, Config::$cookiename . '_tfa'])) {
			$this->member = (int) ($this->custom_data[0] ?? User::$me->id);
			$this->hash = $this->custom_data[1] ?? self::encrypt(User::$me->passwd, User::$me->password_salt);

			$expires = $expires ?? $this->custom_data[2] ?? null;
			$domain = $domain ?? $this->custom_data[3] ?? null;
			$path = $path ?? $this->custom_data[4] ?? null;

			for ($i = 0; $i <= 4; $i++) {
				unset($this->custom_data[$i]);
			}
		}

		$this->expires = $expires ?? time() + 60 * Config::$modSettings['cookieTime'];
		$this->domain = $domain ?? self::$default_domain;
		$this->path = $path ?? self::$default_path;
		$this->secure = $secure ?? !empty(Config::$modSettings['secureCookies']);
		$this->httponly = $httponly ?? !empty(Config::$modSettings['httponlyCookies']);
		$this->samesite = $samesite ?? !empty(Config::$modSettings['samesiteCookies']) ? Config::$modSettings['samesiteCookies'] : 'lax';

		// Allow mods to add custom info to the cookie
		$data = $this->name !== Config::$cookiename ? [] : [
			$this->member,
			$this->hash,
			$this->expires,
			$this->domain,
			$this->path,
		];

		IntegrationHook::call('integrate_cookie_data', [$data, &$this->custom_data]);
	}

	/**
	 * A wrapper for setcookie that gives integration hooks access to it.
	 */
	public function set()
	{
		if (in_array($this->name, [Config::$cookiename, Config::$cookiename . '_tfa'])) {
			$data = [
				$this->member,
				$this->hash,
				$this->expires,
				$this->domain,
				$this->path,
			];

			$data = array_merge($data, (array) $this->custom_data);

			$value = Utils::jsonEncode($data, JSON_FORCE_OBJECT);
		} elseif (!is_scalar($this->custom_data)) {
			$value = Utils::jsonEncode($this->custom_data, JSON_FORCE_OBJECT);
		} else {
			$value = $this->custom_data;
		}

		// MOD AUTHORS: This hook just informs you about the cookie. If you want
		// to change the cookie data, use integrate_cookie_data instead.
		IntegrationHook::call('integrate_cookie', [$this->name, $value, $this->expires, $this->path, $this->domain, $this->secure, $this->httponly, $this->samesite]);

		return setcookie($this->name, $value, [
			'expires' 	=> $this->expires,
			'path'		=> $this->path,
			'domain' 	=> $this->domain,
			'secure'	=> $this->secure,
			'httponly'	=> $this->httponly,
			'samesite'	=> $this->samesite,
		]);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Constructs an instance of this class for a cookie that was sent to the
	 * server by the client.
	 *
	 * @param string $name The name of the cookie.
	 * @return object|null An instance of this class for the cookie, or null
	 *    if no cookie with that name was sent by the client.
	 */
	public static function getCookie(string $name): ?object
	{
		if (!isset($_COOKIE[$name])) {
			return null;
		}

		// Special case for the login cookie.
		if ($name === Config::$cookiename) {
			// First check for JSON-format cookie
			if (preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+,"3":"[^"]+","4":"[^"]+"~', $_COOKIE[$name])) {
				$data = Utils::jsonDecode($_COOKIE[$name], true);
			}
			// Legacy format (for recent upgrades from SMF 2.0.x)
			elseif (preg_match('~^a:[34]:\{i:0;i:\d+;i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d+;(i:3;i:\d;)?~', $_COOKIE[$name])) {
				$data = Utils::safeUnserialize($_COOKIE[$name]);

				list(, , , $state) = $data;

				$cookie_state = (empty(Config::$modSettings['localCookies']) ? 0 : 1) | (empty(Config::$modSettings['globalCookies']) ? 0 : 2);

				// Maybe we need to temporarily pretend to be using local cookies
				if ($cookie_state == 0 && $state == 1) {
					list($data[3], $data[4]) = self::urlParts(true, false);
				} else {
					list($data[3], $data[4]) = self::urlParts($state & 1 > 0, $state & 2 > 0);
				}
			}

			if (!isset($data)) {
				return null;
			}

			$member = (int) ($data[0] ?? 0);
			$hash = (string) ($data[1] ?? '');
			$expires = $data[2] ?? null;
			$domain = $data[3] ?? null;
			$path = $data[4] ?? null;

			return new self($name, [$member, $hash], $expires, $domain, $path);
		}

		// Special case for the TFA cookie.
		if ($name === Config::$cookiename . '_tfa') {
			$data = Utils::jsonDecode($_COOKIE[$name], true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}

			$member = (int) ($data[0] ?? 0);
			$hash = (string) ($data[1] ?? '');
			$expires = (int) ($data[2] ?? 0);
			$domain = (string) ($data[3] ?? '');
			$path = (string) ($data[4] ?? '');

			return new self($name, [$member, $hash], $expires, $domain, $path);
		}

		// Other cookies.
		$data = Utils::jsonDecode($_COOKIE[$name], true, false);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$data = $_COOKIE[$name];
		}

		return new self($name, $data);
	}

	/**
	 * Sets the SMF-style login cookie and session based on the id_member and password passed.
	 *
	 * - password should be already encrypted with the cookie salt.
	 * - logs the user out if id_member is zero.
	 * - sets the cookie and session to last the number of seconds specified by cookie_length, or
	 *   ends them if cookie_length is less than 0.
	 * - when logging out, if the globalCookies setting is enabled, attempts to clear the subdomain's
	 *   cookie too.
	 *
	 * @param int $cookie_length How many seconds the cookie should last. If negative, forces logout.
	 * @param int $id The ID of the member to set the cookie for
	 * @param string $password The hashed password
	 */
	public static function setLoginCookie($cookie_length, $id, $password = '')
	{
		self::setDefaults();

		$id = (int) $id;

		$expires = ($cookie_length >= 0 ? time() + $cookie_length : 1);

		// If changing state force them to re-address some permission caching.
		$_SESSION['mc']['time'] = 0;

		// The cookie may already exist and have been set with different options.
		$old_cookie = self::getCookie(Config::$cookiename);

		// Out with the old, in with the new!
		if (($old_cookie->domain ?? self::$default_domain) != self::$default_domain || ($old_cookie->path ?? self::$default_path) != self::$default_path) {
			$old_domain = $old_cookie->domain;
			$old_path = $old_cookie->path;

			$cookie = new self(Config::$cookiename, [0, ''], 0, $old_domain, $old_path);
			$cookie->set();
		}

		// Set the cookie, $_COOKIE, and session variable.
		$cookie = new self(Config::$cookiename, [$id, $password], $expires);
		$cookie->set();

		// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
		if (empty($id) && !empty(Config::$modSettings['globalCookies'])) {
			$cookie->domain = '';
			$cookie->set();
			$cookie->domain = self::$default_domain;
		}

		// Any alias URLs?  This is mainly for use with frames, etc.
		if (!empty(Config::$modSettings['forum_alias_urls'])) {
			$aliases = explode(',', Config::$modSettings['forum_alias_urls']);

			$temp = Config::$boardurl;

			foreach ($aliases as $alias) {
				// Fake the Config::$boardurl so we can set a different cookie.
				$alias = strtr(trim($alias), ['http://' => '', 'https://' => '']);
				Config::$boardurl = 'http://' . $alias;

				list($domain, $path) = self::urlParts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies']));

				if ($domain == '') {
					$domain = strtok($alias, '/');
				}

				$alias_cookie = clone $cookie;
				$alias_cookie->custom_data[3] = $alias_cookie->domain = $domain;
				$alias_cookie->custom_data[4] = $alias_cookie->path = $path;
				$alias_cookie->set();
			}

			Config::$boardurl = $temp;
		}

		$_COOKIE[Config::$cookiename] = Utils::jsonEncode(
			[
				$cookie->member,
				$cookie->hash,
				$cookie->expires,
				$cookie->domain,
				$cookie->path,
			],
			JSON_FORCE_OBJECT,
		);

		// Make sure the user logs in with a new session ID.
		if (($_SESSION['login_' . Config::$cookiename] ?? null) !== $_COOKIE[Config::$cookiename]) {
			// Backup and remove the old session.
			$oldSessionData = $_SESSION;
			$_SESSION = [];
			session_destroy();

			// Recreate and restore the new session.
			Session::load();

			// @todo should we use session_regenerate_id(true); now that we are 5.1+
			session_regenerate_id();

			$_SESSION = $oldSessionData;

			$_SESSION['login_' . Config::$cookiename] = $_COOKIE[Config::$cookiename];
		}
	}

	/**
	 * Sets the Two Factor Authentication cookie.
	 *
	 * @param int $cookie_length How long the cookie should last, in seconds.
	 * @param int $id The ID of the member.
	 * @param string $secret Should be a salted secret using self::encrypt().
	 */
	public static function setTFACookie($cookie_length, $id, $secret)
	{
		self::setDefaults();

		$expires = ($cookie_length >= 0 ? time() + $cookie_length : 1);

		// Set the cookie, $_COOKIE, and session variable.
		$cookie = new self(Config::$cookiename . '_tfa', [$id, $secret], $expires);
		$cookie->set();

		// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
		if (empty($id) && !empty(Config::$modSettings['globalCookies'])) {
			$cookie->domain = '';
			$cookie->set();
			$cookie->domain = self::$default_domain;
		}

		$_COOKIE[Config::$cookiename . '_tfa'] = Utils::jsonEncode(
			[
				$cookie->member,
				$cookie->hash,
				$cookie->expires,
				$cookie->domain,
				$cookie->path,
			],
			JSON_FORCE_OBJECT,
		);
	}

	/**
	 * Get the domain and path for the cookie.
	 *
	 * Normally, local and global should be the localCookies and globalCookies
	 * settings, respectively.
	 *
	 * Uses $boardurl to determine these two things.
	 *
	 * @param bool $local Whether we want local cookies.
	 * @param bool $global Whether we want global cookies.
	 * @return array The domain and path for the cookie, in that order.
	 */
	public static function urlParts($local, $global)
	{
		// Use the Url class to make life easier.
		$url = new Url(Config::$boardurl);

		// Are local cookies off?
		$path = empty($url->path) || !$local ? '' : $url->path;

		$host = $url->host;

		// Manually specified the global domain.
		// @todo Why doesn't this check whether $global is true?
		if (!empty(Config::$modSettings['globalCookiesDomain']) && strpos(Config::$boardurl, Config::$modSettings['globalCookiesDomain']) !== false) {
			$host = Config::$modSettings['globalCookiesDomain'];
		}
		// Globalize cookies across domains? (filter out IP-addresses)
		elseif ($global && preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $host) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $host, $parts) == 1) {
			$host = '.' . $parts[1];
		}
		// We shouldn't use a host at all if both options are off.
		elseif (!$local && !$global) {
			$host = '';
		}
		// The host also shouldn't be set if there aren't any dots in it.
		elseif (!isset($host) || strpos($host, '.') === false) {
			$host = '';
		}

		return [$host, $path . '/'];
	}

	/**
	 * Hashes password with salt and authentication secret.
	 *
	 * This is solely used for cookies.
	 *
	 * @param string $password The password.
	 * @param string $salt The salt.
	 * @return string The hashed password.
	 */
	public static function encrypt($password, $salt)
	{
		// Append the salt to get a user-specific authentication secret.
		$secret_key = Config::getAuthSecret() . $salt;

		// Now use that to generate an HMAC of the password.
		return hash_hmac('sha512', $password, $secret_key);
	}

	/**
	 * Sets the values of self::$default_domain and self::$default_path.
	 */
	public static function setDefaults(): void
	{
		if (isset(self::$default_domain, self::$default_path)) {
			return;
		}

		list(self::$default_domain, self::$default_path) = self::urlParts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies']));
	}

	/**
	 * Backward compatibility wrapper for the set() method.
	 */
	public static function setcookie(string $name, string $value = '', int $expires = 0, string $path = '', string $domain = '', ?bool $secure = null, bool $httponly = true, ?string $samesite = null): void
	{
		$data = Utils::jsonDecode($value);

		$cookie = new self($name, $data, $expires, $domain, $path, $secure, $httponly, $samesite);

		$cookie->set();
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Cookie::exportStatic')) {
	Cookie::exportStatic();
}

?>