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

/**
 * Detects URLs in strings.
 */
class Autolinker
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var string
	 *
	 * Characters to exclude from a detected URL if they appear at the end.
	 */
	public static string $excluded_trailing_chars = '!;:.,?';

	/**
	 * @var array
	 *
	 * Brackets and quotation marks are problematic at the end of an IRI.
	 * E.g.: `http://foo.com/baz(qux)` vs. `(http://foo.com/baz_qux)`
	 * In the first case, the user probably intended the `)` as part of the
	 * IRI, but not in the second case. To account for this, we test for
	 * balanced pairs within the IRI.
	 * */
	public static array $balanced_pairs = [
		// Brackets and parentheses
		'(' => ')', // '&#x28;' => '&#x29;',
		'[' => ']', // '&#x5B;' => '&#x5D;',
		'{' => '}', // '&#x7B;' => '&#x7D;',

		// Double quotation marks
		'"' => '"', // '&#x22;' => '&#x22;',
		'“' => '”', // '&#x201C;' => '&#x201D;',
		'„' => '”', // '&#x201E;' => '&#x201D;',
		'‟' => '”', // '&#x201F;' => '&#x201D;',
		'«' => '»', // '&#xAB;' => '&#xBB;',

		// Single quotation marks
		"'" => "'", // '&#x27;' => '&#x27;',
		'‘' => '’', // '&#x2018;' => '&#x2019;',
		'‚' => '’', // '&#x201A;' => '&#x2019;',
		'‛' => '’', // '&#x201B;' => '&#x2019;',
		'‹' => '›', // '&#x2039;' => '&#x203A;',
	];

	/**
	 * @var string
	 *
	 * Regular expression character class to match all characters allowed to
	 * appear in a domain name.
	 */
	public static string $domain_label_chars = '0-9A-Za-z\-' . '\x{A0}-\x{D7FF}' .
		'\x{F900}-\x{FDCF}' . '\x{FDF0}-\x{FFEF}' . '\x{10000}-\x{1FFFD}' .
		'\x{20000}-\x{2FFFD}' . '\x{30000}-\x{3FFFD}' . '\x{40000}-\x{4FFFD}' .
		'\x{50000}-\x{5FFFD}' . '\x{60000}-\x{6FFFD}' . '\x{70000}-\x{7FFFD}' .
		'\x{80000}-\x{8FFFD}' . '\x{90000}-\x{9FFFD}' . '\x{A0000}-\x{AFFFD}' .
		'\x{B0000}-\x{BFFFD}' . '\x{C0000}-\x{CFFFD}' . '\x{D0000}-\x{DFFFD}' .
		'\x{E1000}-\x{EFFFD}';

	/**
	 * @var array
	 *
	 * URI schemes that require some sort of special handling.
	 *
	 * Mods can add to this list using the integrate_autolinker_schemes hook.
	 */
	public static array $schemes = [
		// Schemes whose URI definitions require a domain name in the
		// authority (or whatever the next part of the URI is).
		'need_domain' => [
			'aaa', 'aaas', 'acap', 'acct', 'afp', 'cap', 'cid', 'coap',
			'coap+tcp', 'coap+ws', 'coaps', 'coaps+tcp', 'coaps+ws', 'crid',
			'cvs', 'dict', 'dns', 'feed', 'fish', 'ftp', 'git', 'go', 'gopher',
			'h323', 'http', 'https', 'iax', 'icap', 'im', 'imap', 'ipp', 'ipps',
			'irc', 'irc6', 'ircs', 'ldap', 'ldaps', 'mailto', 'mid', 'mupdate',
			'nfs', 'nntp', 'pop', 'pres', 'reload', 'rsync', 'rtsp', 'sftp',
			'sieve', 'sip', 'sips', 'smb', 'snmp', 'soap.beep', 'soap.beeps',
			'ssh', 'svn', 'stun', 'stuns', 'telnet', 'tftp', 'tip', 'tn3270',
			'turn', 'turns', 'tv', 'udp', 'vemmi', 'vnc', 'webcal', 'ws', 'wss',
			'xmlrpc.beep', 'xmlrpc.beeps', 'xmpp', 'z39.50', 'z39.50r',
			'z39.50s',
		],

		// Schemes that allow an empty authority ("://" followed by "/")
		'empty_authority' => [
			'file', 'ni', 'nih',
		],

		// Schemes that do not use an authority but still have a reasonable
		// chance of working as clickable links.
		'no_authority' => [
			'about', 'callto', 'geo', 'gg', 'leaptofrogans', 'magnet', 'mailto',
			'maps', 'news', 'ni', 'nih', 'service', 'skype', 'sms', 'tel', 'tv',
		],

		// Schemes that should never be autolinked.
		'forbidden' => [
			'javascript', 'data',
		],
	];

	/**
	 * @var array
	 *
	 * BBCodes whose content should be skipped when autolinking URLs.
	 *
	 * Mods can add to this list using the integrate_bbc_codes hook.
	 */
	public static array $no_autolink_tags = [
		'url',
		'iurl',
		'email',
		'img',
		'html',
		'attach',
		'ftp',
		'flash',
		'member',
		'code',
		'php',
		'nobbc',
		'nolink',
	];

	/**
	 * @var array
	 *
	 * BBCodes in which to fix URL strings.
	 *
	 * Mods can add to this list using the integrate_autolinker_fix_tags hook.
	 */
	public static array $tags_to_fix = [
		'url',
		'iurl',
		'img',
		'ftp',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The character encoding being used.
	 */
	protected string $encoding = 'UTF-8';

	/**
	 * @var bool
	 *
	 * If true, will only link URLs with basic TLDs.
	 */
	protected bool $only_basic = false;

	/**
	 * @var string
	 *
	 * PCRE regular expression to match top level domains.
	 */
	protected string $tld_regex;

	/**
	 * @var string
	 *
	 * PCRE regular expression to match URLs.
	 */
	protected string $url_regex;

	/**
	 * @var string
	 *
	 * PCRE regular expression to match e-mail addresses.
	 */
	protected string $email_regex;

	/**
	 * @var string
	 *
	 * JavaScript regular expression to match top level domains.
	 */
	protected string $js_tld_regex;

	/**
	 * @var array
	 *
	 * JavaScript regular expressions to match URLs.
	 *
	 * Due to limitations of JavaScript's regex engine, this has to be a series
	 * of different regexes rather than one regex like the PCRE version.
	 */
	protected array $js_url_regexes;

	/**
	 * @var string
	 *
	 * JavaScript regular expression to match e-mail addresses.
	 */
	protected string $js_email_regex;

	/**
	 * @var string
	 *
	 * Regular expression to match the named entities in HTML5.
	 */
	protected string $entities_regex;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var bool
	 *
	 * Ensures we only call integrate_autolinker_schemes once.
	 */
	protected static bool $integrate_autolinker_schemes_done = false;

	/**
	 * @var bool
	 *
	 * Ensures we only call integrate_autolinker_fix_tags once.
	 */
	protected static bool $integrate_autolinker_fix_tags_done = false;

	/**
	 * @var self
	 *
	 * A reference to an existing, reusable instance of this class.
	 */
	private static self $instance;

	/*****************
	 * Public methods.
	 *****************/

	/**
	 * Constructor.
	 *
	 * @param bool $only_basic If true, will only link URLs with basic TLDs.
	 */
	public function __construct(bool $only_basic = false)
	{
		$this->only_basic = $only_basic;

		if (!empty(Utils::$context['utf8'])) {
			$this->encoding = 'UTF-8';
		} else {
			$this->encoding = !empty(Config::$modSettings['global_character_set']) ? Config::$modSettings['global_character_set'] : (!empty(Lang::$txt['lang_character_set']) ? Lang::$txt['lang_character_set'] : $this->encoding);

			if (in_array($this->encoding, mb_encoding_aliases('UTF-8'))) {
				$this->encoding = 'UTF-8';
			}
		}

		if ($this->encoding !== 'UTF-8') {
			self::$domain_label_chars = '0-9A-Za-z\-';
		}

		// In case a mod wants to control behaviour for a special URI scheme.
		if (!self::$integrate_autolinker_schemes_done) {
			IntegrationHook::call('integrate_autolinker_schemes', [&self::$schemes]);
			self::$integrate_autolinker_schemes_done = true;
		}

		// For historical reasons, integrate_bbc_hook is used to give mods access to $no_autolink_tags.
		BBCodeParser::integrateBBC();
	}

	/**
	 * Gets a PCRE regular expression to match all known TLDs.
	 *
	 * @return string Regular expression to match all known TLDs.
	 */
	public function getTldRegex(): string
	{
		if (!isset($this->tld_regex)) {
			$this->setTldRegex();
		}

		return $this->tld_regex;
	}

	/**
	 * Gets a PCRE regular expression to match URLs.
	 *
	 * @return string Regular expression to match URLs.
	 */
	public function getUrlRegex(): string
	{
		if (!isset($this->url_regex)) {
			$this->setUrlRegex();
		}

		return $this->url_regex;
	}

	/**
	 * Gets a PCRE regular expression to match email addresses.
	 *
	 * @return string Regular expression to match email addresses.
	 */
	public function getEmailRegex(): string
	{
		if (!isset($this->email_regex)) {
			$this->setEmailRegex();
		}

		return $this->email_regex;
	}

	/**
	 * Gets a JavaScript regular expression to match all known TLDs.
	 *
	 * @return string Regular expression to match all known TLDs.
	 */
	public function getJavaScriptTldRegex(): string
	{
		if (!isset($this->js_tld_regex)) {
			$this->setJavaScriptTldRegex();
		}

		return $this->js_tld_regex;
	}

	/**
	 * Gets a series of JavaScript regular expressions to match URLs.
	 *
	 * @return array Regular expressions to match URLs.
	 */
	public function getJavaScriptUrlRegexes(): array
	{
		if (!isset($this->js_url_regexes)) {
			$this->setJavaScriptUrlRegexes();
		}

		return $this->js_url_regexes;
	}

	/**
	 * Gets a JavaScript regular expression to match email addresses.
	 *
	 * @return string Regular expression to match email addresses.
	 */
	public function getJavaScriptEmailRegex(): string
	{
		if (!isset($this->js_email_regex)) {
			$this->setJavaScriptEmailRegex();
		}

		return $this->js_email_regex;
	}

	/**
	 * Detects URLs in a string.
	 *
	 * Returns an array in which the keys are the start positions of any
	 * detected URLs in the string, and the values are the URLs themselves.
	 *
	 * @param string $string The string to examine.
	 * @param bool bool $plaintext_only If true, only look for plain text URLs.
	 * @return array Positional info about any detected URLs.
	 */
	public function detectUrls(string $string, bool $plaintext_only = false): array
	{
		static $no_autolink_regex;

		// An entity right after the URL can break the autolinker.
		$this->setEntitiesRegex();
		$string = preg_replace('~(' . $this->entities_regex . ')*(?=\s|$)~u', ' ', $string);

		$this->setUrlRegex();

		if ($plaintext_only) {
			$no_autolink_regex = $no_autolink_regex ?? Utils::buildRegex(self::$no_autolink_tags);

			// Overwrite the contents of all BBC markup elements that should not be autolinked.
			$string = preg_replace_callback(
				'~' .
					// 1 = Opening BBC markup element.
					'(\[' .
						// 2 = BBC tag.
						'(' . $no_autolink_regex . ')' .
						// BBC parameters, if any.
						'\b[^\]]*' .
					'\])' .
					// 3 = Recursive construct.
					'((?' . '>' . '[^\[]|\[/?(?!' . $no_autolink_regex . ')' . '|(?1))*)' .
					// 4 = Closing BBC markup element.
					'(\[/\2\])' .
				'~i' . ($this->encoding === 'UTF-8' ? 'u' : ''),
				fn ($matches) => $matches[1] . str_repeat('x', strlen($matches[3])) . $matches[4],
				$string,
			);

			// Overwrite all BBC markup elements.
			$string = preg_replace_callback(
				'/\[[^\]]*\]/i' . ($this->encoding === 'UTF-8' ? 'u' : ''),
				fn ($matches) => str_repeat(' ', strlen($matches[0])),
				$string,
			);

			// Overwrite the contents of all HTML anchor elements.
			$string = preg_replace_callback(
				'~' .
					// 1 = Opening 'a' markup element.
					'(<a\b[^\>]*>)' .
					// 2 = Recursive construct.
					'((?' . '>' . '[^<]|</?(?!a)' . '|(?1))*)' .
					// 3 = Closing 'a' markup element.
					'(</a>)' .
				'~i' . ($this->encoding === 'UTF-8' ? 'u' : ''),
				fn ($matches) => $matches[1] . str_repeat('x', strlen($matches[2])) . $matches[3],
				$string,
			);

			// Overwrite all HTML elements.
			$string = preg_replace_callback(
				'~</?(\w+)\b([^>]*)>~i' . ($this->encoding === 'UTF-8' ? 'u' : ''),
				fn ($matches) => str_repeat(' ', strlen($matches[0])),
				$string,
			);
		}

		preg_match_all(
			'~' . $this->url_regex . '~i' . ($this->encoding === 'UTF-8' ? 'u' : ''),
			$string,
			$matches,
			PREG_OFFSET_CAPTURE,
		);

		$detected = [];

		if (!empty($matches[0])) {
			foreach ($matches[0] as $key => $match) {
				$detected[$match[1]] = $match[0];
			}
		}

		return $detected;
	}

	/**
	 * Detects email addresses in a string.
	 *
	 * Returns an array in which the keys are the start positions of any
	 * detected email addresses in the string, and the values are the email
	 * addresses themselves.
	 *
	 * @param string $string The string to examine.
	 * @param bool bool $plaintext_only If true, only look for plain text email
	 *    addresses.
	 * @return array Positional info about any detected email addresses.
	 */
	public function detectEmails(string $string, bool $plaintext_only = false): array
	{
		// An entity right after the email address can break the autolinker.
		$this->setEntitiesRegex();
		$string = preg_replace('~(' . $this->entities_regex . ')*(?=\s|$)~u', ' ', $string);

		$this->setEmailRegex();

		preg_match_all(
			'~' . ($plaintext_only ? '(?:^|\s|<br>)\K' : '') . $this->email_regex . '~i' . ($this->encoding === 'UTF-8' ? 'u' : ''),
			$string,
			$matches,
			PREG_OFFSET_CAPTURE,
		);

		$detected = [];

		foreach ($matches[0] as $match) {
			$detected[$match[1]] = $match[0];
		}

		return $detected;
	}

	/**
	 * Detects plain text URLs and email addresses and formats them as BBCode
	 * links.
	 *
	 * @param string $string The string to autolink.
	 * @param bool $link_emails Whether to autolink email addresses.
	 *    Default: true.
	 * @param bool $link_urls Whether to autolink URLs.
	 *    Default: true.
	 * @return string The string with linked URLs.
	 */
	public function makeLinks(string $string, bool $link_emails = true, bool $link_urls = true): string
	{
		$placeholders = [];

		foreach (self::$no_autolink_tags as $tag) {
			$parts = preg_split('~(\[/' . $tag . '\]|\[' . $tag . '\b(?:[^\]]*)\])~i', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

			for ($i = 0, $n = count($parts); $i < $n; $i++) {
				if ($i % 4 == 2) {
					$placeholder = md5($parts[$i]);
					$placeholders[$placeholder] = $parts[$i];
					$parts[$i] = $placeholder;
				}
			}

			$string = implode('', $parts);
		}

		if ($link_urls) {
			$detected_urls = $this->detectUrls($string, true);

			if (empty($detected_urls)) {
				$new_string = $string;
			} else {
				$new_string = '';
				$prev_pos = 0;
				$prev_len = 0;

				foreach ($detected_urls as $pos => $url) {
					$new_string .= substr($string, $prev_pos + $prev_len, $pos - ($prev_pos + $prev_len));
					$prev_pos = $pos;
					$prev_len = strlen($url);

					// If this isn't a clean URL, leave it alone.
					if ($url !== (string) Url::create($url)->sanitize()) {
						$new_string .= $url;
						continue;
					}

					// Ensure the host name is in its canonical form.
					$url = new Url($url, true);

					if (!isset($url->scheme)) {
						$url->scheme = '';
					}

					if ($url->scheme == 'mailto') {
						if (!$link_emails) {
							$new_string .= $url;
							continue;
						}

						// Is this version of PHP capable of validating this email address?
						$can_validate = defined('FILTER_FLAG_EMAIL_UNICODE') || strlen($url->path) == strspn(strtolower($url->path), 'abcdefghijklmnopqrstuvwxyz0123456789!#$%&\'*+-/=?^_`{|}~.@');

						$flags = defined('FILTER_FLAG_EMAIL_UNICODE') ? FILTER_FLAG_EMAIL_UNICODE : null;

						if (!$can_validate || filter_var($url->path, FILTER_VALIDATE_EMAIL, $flags) !== false) {
							$placeholders[md5($url->path)] = $url->path;
							$placeholders[md5((string) $url)] = (string) $url;

							$new_string .= '[email=' . md5($url->path) . ']' . md5((string) $url) . '[/email]';
						} else {
							$placeholders[md5((string) $url)] = (string) $url;

							$new_string .= $url;
						}

						continue;
					}

					// Are we linking a schemeless URL or naked domain name (e.g. "example.com")?
					if (empty($url->scheme)) {
						$full_url = new Url('//' . ltrim((string) $url, ':/'));
					} else {
						$full_url = clone $url;
					}

					// Make sure that $full_url really is valid
					if (
						in_array($url->scheme, self::$schemes['forbidden'])
						|| (
							!in_array($url->scheme, self::$schemes['no_authority'])
							&& !$full_url->isValid()
						)
					) {
						$new_string .= $url;
					} elseif ((string) $full_url->toAscii() === (string) $url) {
						$new_string .= '[url]' . $url . '[/url]';
					} else {
						$new_string .= '[url=&quot;' . str_replace(['[', ']'], ['&#91;', '&#93;'], (string) $full_url->toAscii()) . '&quot;]' . $url . '[/url]';
					}
				}

				$new_string .= substr($string, $prev_pos + $prev_len);
			}
		} else {
			$new_string = $string;
		}

		if ($link_emails) {
			$string = $new_string;

			$detected_emails = $this->detectEmails($string, true);

			if (!empty($detected_emails)) {
				$new_string = '';
				$prev_pos = 0;
				$prev_len = 0;

				foreach ($detected_emails as $pos => $email) {
					$new_string .= substr($string, $prev_pos + $prev_len, $pos - ($prev_pos + $prev_len));
					$prev_pos = $pos;
					$prev_len = strlen($email);

					$new_string .= '[email]' . $email . '[/email]';
				}

				$new_string .= substr($string, $prev_pos + $prev_len);
			}
		}

		if (!empty($placeholders)) {
			$new_string = strtr($new_string, $placeholders);
		}

		return $new_string;
	}

	/**
	 * Checks URLs inside BBCodes and fixes them if invalid.
	 *
	 * @param string $string The string containing the BBCodes.
	 * @return string The fixed string.
	 */
	public function fixUrlsInBBC(string $string): string
	{
		static $tags_to_fix_regex;

		// In case a mod wants to add tags to the list of BBC to fix URLs in.
		if (!self::$integrate_autolinker_fix_tags_done) {
			IntegrationHook::call('integrate_autolinker_fix_tags', [&self::$tags_to_fix]);

			self::$tags_to_fix = array_unique(self::$tags_to_fix);

			self::$integrate_autolinker_fix_tags_done = true;
		}

		$tags_to_fix_regex = $tags_to_fix_regex ?? Utils::buildRegex((array) self::$tags_to_fix, '~');

		$parts = preg_split('~(\[/?' . $tags_to_fix_regex . '\b[^\]]*\])~u', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $n = count($parts); $i < $n; $i++) {
			if ($i % 4 == 1) {
				unset($href, $bbc);

				$bbc = substr(ltrim($parts[$i], '['), 0, strcspn(ltrim($parts[$i], '['), ' =]'));

				if (str_contains($parts[$i], '=')) {
					$href = substr($parts[$i], strpos($parts[$i], '=') + 1, -1);

					if (str_starts_with($href, '&quot;')) {
						$href = substr($href, 6, -6);
					}

					if (str_starts_with($href, '"')) {
						$href = substr($href, 1, -1);
					}

					$detected_urls = $this->detectUrls($href);

					if (empty($detected_urls)) {
						$parts[$i] = '';
						$parts[$i + 2] = '';
						continue;
					}

					$url = reset($detected_urls);

					$parts[$i] = str_replace($href, $url, $parts[$i]);
				}
			} elseif ($i % 4 == 2) {
				$detected_urls = $this->detectUrls($parts[$i], true);

				// Not a valid URL.
				if (empty($detected_urls) && empty($href)) {
					$parts[$i - 1] = '';
					$parts[$i + 1] = '';
					continue;
				}

				$first_url = reset($detected_urls);

				// Valid URL.
				if (count($detected_urls) === 1 && $parts[$i] === $first_url) {
					// BBC param is unnecessary if it is identical to the content.
					if (!empty($href) && $href === $first_url) {
						$parts[$i - 1] = '[' . $bbc . ']';
					}

					// Nothing else needs to change.
					continue;
				}

				// One URL, plus some unexpected cruft...
				if (count($detected_urls) === 1) {
					foreach ($detected_urls as $url) {
						if (!str_starts_with($parts[$i], $url)) {
							$parts[$i - 1] = substr($parts[$i], 0, strpos($parts[$i], $url)) . $parts[$i - 1];
							$parts[$i] = substr($parts[$i], strpos($parts[$i], $url));
						}

						if (!str_ends_with($parts[$i], $url)) {
							$parts[$i + 1] .= substr($parts[$i], strlen($url));
							$parts[$i] = substr($parts[$i], 0, strlen($url));
						}
					}
				}

				// Multiple URLs inside one BBCode? Weird. Fix them.
				if (count($detected_urls) > 1) {
					$parts[$i - 1] = '';
					$parts[$i + 1] = '';

					$parts[$i] = strtr(
						$parts[$i],
						array_combine(
							$detected_urls,
							array_map(fn ($url) => '[' . $bbc . ']' . $url . '[/' . $bbc . ']', $detected_urls),
						),
					);
				}
			}
		}

		return implode('', $parts);
	}

	/************************
	 * Public static methods.
	 ************************/

	/**
	 * Returns a reusable instance of this class.
	 *
	 * @param bool $only_basic If true, will only link URLs with basic TLDs.
	 * @return object An instance of this class.
	 */
	public static function load(bool $only_basic = false): object
	{
		if (!isset(self::$instance) || self::$instance->only_basic !== $only_basic) {
			self::$instance = new self($only_basic);
		}

		return self::$instance;
	}

	/**
	 * Creates the JavaScript file used for autolinking in the editor.
	 *
	 * @param bool $force Whether to overwrite an existing file. Default: false.
	 */
	public static function createJavaScriptFile(bool $force = false): void
	{
		if (empty(Config::$modSettings['autoLinkUrls'])) {
			return;
		}

		if (!isset(Theme::$current)) {
			Theme::loadEssential();
		}

		if (!$force && file_exists(Theme::$current->settings['default_theme_dir'] . '/scripts/autolinker.js')) {
			return;
		}

		$js[] = 'const autolinker_regexes = new Map();';

		$regexes = self::load()->getJavaScriptUrlRegexes();
		$regexes['email'] = self::load()->getJavaScriptEmailRegex();

		foreach ($regexes as $key => $value) {
			$js[] = 'autolinker_regexes.set(' . Utils::escapeJavaScript($key) . ', new RegExp(' . Utils::escapeJavaScript($value) . ', "giu"));';

			$js[] = 'autolinker_regexes.set(' . Utils::escapeJavaScript('paste_' . $key) . ', new RegExp(' . Utils::escapeJavaScript('(?<=^|\s|<br>)' . $value . '(?=$|\s|<br>|[' . self::$excluded_trailing_chars . '])') . ', "giu"));';

			$js[] = 'autolinker_regexes.set(' . Utils::escapeJavaScript('keypress_' . $key) . ', new RegExp(' . Utils::escapeJavaScript($value . '(?=[' . self::$excluded_trailing_chars . preg_quote(implode('', array_merge(array_keys(self::$balanced_pairs), self::$balanced_pairs)), '/') . ']*\s$)') . ', "giu"));';
		}

		$js[] = 'const autolinker_balanced_pairs = new Map();';

		foreach (self::$balanced_pairs as $opener => $closer) {
			$js[] = 'autolinker_balanced_pairs.set(' . Utils::escapeJavaScript($opener) . ', ' . Utils::escapeJavaScript($closer) . ');';
		}

		file_put_contents(Theme::$current->settings['default_theme_dir'] . '/scripts/autolinker.js', implode("\n", $js));
	}

	/*******************
	 * Internal methods.
	 *******************/

	/**
	 * Sets $this->entities_regex.
	 */
	protected function setEntitiesRegex(): void
	{
		if (isset($this->entities_regex)) {
			return;
		}

		$this->entities_regex = '(?' . '>&(?' . '>' . Utils::buildRegex(array_map(fn ($ent) => ltrim($ent, '&'), get_html_translation_table(HTML_ENTITIES, ENT_HTML5 | ENT_QUOTES)), '~') . '|(?' . '>#(?' . '>x[0-9a-fA-F]{1,6}|\d{1,7});)))';
	}

	/**
	 * Sets $this->tld_regex.
	 */
	protected function setTldRegex(): void
	{
		if (isset($this->tld_regex)) {
			return;
		}

		if (!$this->only_basic && $this->encoding === 'UTF-8') {
			Url::setTldRegex();
			$this->tld_regex = Config::$modSettings['tld_regex'];
		} else {
			$this->tld_regex = Utils::buildRegex(array_merge(Url::$basic_tlds, Url::$cc_tlds, Url::$special_use_tlds));
		}
	}

	/**
	 * Sets $this->js_tld_regex.
	 */
	protected function setJavaScriptTldRegex(): void
	{
		$this->setTldRegex();

		// The JavaScript version of this one is simple to make.
		$this->js_tld_regex = strtr($this->tld_regex, ['(?' . '>' => '(?:']);
	}

	/**
	 * Sets $this->email_regex.
	 */
	protected function setEmailRegex(): void
	{
		if (!empty($this->email_regex)) {
			return;
		}

		$this->setTldRegex();

		// Preceded by a space or start of line
		$this->email_regex = '(?<=^|\s|<br>)' .

		// An email address
		'[' . self::$domain_label_chars . '_.]{1,80}' .
		'@' .
		'[' . self::$domain_label_chars . '.]+' .
		'\.' . $this->tld_regex .

		// Followed by a non-domain character or end of line
		'(?=[^' . self::$domain_label_chars . ']|$)';
	}

	/**
	 * Sets $this->js_email_regex.
	 */
	protected function setJavaScriptEmailRegex(): void
	{
		if (!empty($this->js_email_regex)) {
			return;
		}

		$this->setTldRegex();

		// Preceded by a space or start of line
		$this->js_email_regex = '(?<=^|\s|<br>)' .

		// An email address
		'[' . strtr(self::$domain_label_chars, ['\\x{' => '\\u{']) . '_.]{1,80}' .
		'@' .
		'[' . strtr(self::$domain_label_chars, ['\\x{' => '\\u{']) . '.]+' .
		'\.' . strtr($this->tld_regex, ['(?' . '>' => '(?:']) .

		// For JavaScript we need to use a simpler ending than the PCRE version.
		'\b';
	}

	/**
	 * Sets $this->url_regex.
	 */
	protected function setUrlRegex(): void
	{
		// Don't repeat this unnecessarily.
		if (!empty($this->url_regex)) {
			return;
		}

		$this->setTldRegex();

		// PCRE subroutines for efficiency.
		$pcre_subroutines = [
			'tlds' => $this->tld_regex,
			'pct' => '%[0-9A-Fa-f]{2}',
			'space_lookahead' => '(?=$|\s|<br>)',
			'space_lookbehind' => '(?<=^|\s|<br>)',
			'domain_label_char' => '[' . self::$domain_label_chars . ']',
			'not_domain_label_char' => '[^' . self::$domain_label_chars . ']',
			'domain' => '(?:(?P>domain_label_char)+\.)+(?P>tlds)(?!\.(?P>domain_label_char))',
			'no_domain' => '(?:(?P>domain_label_char)|[._\\~!$&\'()*+,;=:@]|(?P>pct))+',
			'scheme_need_domain' => Utils::buildRegex(self::$schemes['need_domain'], '~'),
			'scheme_empty_authority' => Utils::buildRegex(self::$schemes['empty_authority'], '~'),
			'scheme_no_authority' => Utils::buildRegex(self::$schemes['no_authority'], '~'),
			'scheme_any' => '[A-Za-z][0-9A-Za-z+\-.]*',
			'user_info' => '(?:(?P>domain_label_char)|[._\\~!$&\'()*+,;=:]|(?P>pct))+',
			'dec_octet' => '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)',
			'h16' => '[0-9A-Fa-f]{1,4}',
			'ipv4' => '(?:\b(?:(?P>dec_octet)\.){3}(?P>dec_octet)\b)',
			'ipv6' => '\[(?:' . implode('|', [
				'(?:(?P>h16):){7}(?P>h16)',
				'(?:(?P>h16):){1,7}:',
				'(?:(?P>h16):){1,6}(?::(?P>h16))',
				'(?:(?P>h16):){1,5}(?::(?P>h16)){1,2}',
				'(?:(?P>h16):){1,4}(?::(?P>h16)){1,3}',
				'(?:(?P>h16):){1,3}(?::(?P>h16)){1,4}',
				'(?:(?P>h16):){1,2}(?::(?P>h16)){1,5}',
				'(?P>h16):(?::(?P>h16)){1,6}',
				':(?:(?::(?P>h16)){1,7}|:)',
				'fe80:(?::(?P>h16)){0,4}%[0-9A-Za-z]+',
				'::(ffff(:0{1,4})?:)?(?P>ipv4)',
				'(?:(?P>h16):){1,4}:(?P>ipv4)',
			]) . ')\]',
			'host' => '(?:' . implode('|', [
				'localhost',
				'(?P>domain)',
				'(?P>ipv4)',
				'(?P>ipv6)',
			]) . ')',
			'authority' => '(?:(?P>user_info)@)?(?P>host)(?::\d+)?',
		];

		// Work with a fresh copy each time, in case multiple objects were instantiated.
		$balanced_pairs = self::$balanced_pairs;

		foreach ($balanced_pairs as $pair_opener => $pair_closer) {
			$balanced_pairs[htmlspecialchars($pair_opener)] = htmlspecialchars($pair_closer);
		}

		$bracket_quote_chars = '';
		$bracket_quote_entities = [];

		foreach ($balanced_pairs as $pair_opener => $pair_closer) {
			if ($pair_opener == $pair_closer) {
				$pair_closer = '';
			}

			foreach ([$pair_opener, $pair_closer] as $bracket_quote) {
				if (!str_contains($bracket_quote, '&')) {
					$bracket_quote_chars .= $bracket_quote;
				} else {
					$bracket_quote_entities[] = substr($bracket_quote, 1);
				}
			}
		}
		$bracket_quote_chars = str_replace(['[', ']'], ['\[', '\]'], $bracket_quote_chars);

		$pcre_subroutines['bracket_quote'] = '[' . $bracket_quote_chars . ']|&' . Utils::buildRegex($bracket_quote_entities, '~');
		$pcre_subroutines['allowed_entities'] = '&(?!' . Utils::buildRegex(array_merge($bracket_quote_entities, ['lt;', 'gt;']), '~') . ')';
		$pcre_subroutines['excluded_lookahead'] = '(?![' . self::$excluded_trailing_chars . ']*(?P>space_lookahead))';

		foreach (['path', 'query', 'fragment'] as $part) {
			switch ($part) {
				case 'path':
					$part_disallowed_chars = '\s<>' . $bracket_quote_chars . self::$excluded_trailing_chars . '/#&';
					$part_excluded_trailing_chars = str_replace('?', '', self::$excluded_trailing_chars);
					break;

				case 'query':
					$part_disallowed_chars = '\s<>' . $bracket_quote_chars . self::$excluded_trailing_chars . '#&';
					$part_excluded_trailing_chars = self::$excluded_trailing_chars;
					break;

				default:
					$part_disallowed_chars = '\s<>' . $bracket_quote_chars . self::$excluded_trailing_chars . '&';
					$part_excluded_trailing_chars = self::$excluded_trailing_chars;
					break;
			}
			$pcre_subroutines[$part . '_allowed'] = '[^' . $part_disallowed_chars . ']|(?P>allowed_entities)|[' . $part_excluded_trailing_chars . '](?P>excluded_lookahead)';

			$balanced_construct_regex = [];

			foreach ($balanced_pairs as $pair_opener => $pair_closer) {
				$balanced_construct_regex[] = preg_quote($pair_opener) . '(?P>' . $part . '_recursive)*+' . preg_quote($pair_closer);
			}

			$pcre_subroutines[$part . '_balanced'] = '(?:' . implode('|', $balanced_construct_regex) . ')(?P>' . $part . '_allowed)*+';
			$pcre_subroutines[$part . '_recursive'] = '(?' . '>(?P>' . $part . '_allowed)|(?P>' . $part . '_balanced))';

			$pcre_subroutines[$part . '_segment'] =
				// Allowed characters besides brackets and quotation marks
				'(?P>' . $part . '_allowed)*+' .
				// Brackets and quotation marks that are either...
				'(?:' .
					// part of a balanced construct
					'(?P>' . $part . '_balanced)' .
					// or
					'|' .
					// unpaired but not at the end
					'(?P>bracket_quote)(?=(?P>' . $part . '_allowed))' .
				')*+';
		}

		// Time to build this monster!
		$this->url_regex =
		// 1. IRI scheme and domain components
		'(?:' .
			// 1a. IRIs with a scheme, or at least an opening "//"
			'(?:' .

				// URI scheme (or lack thereof for schemeless URLs)
				'(?' . '>' .
					// URI scheme and colon
					'\b' .
					'(?:' .
						// Either a scheme that need a domain in the authority
						// (Remember for later that we need a domain)
						'(?P<need_domain>(?P>scheme_need_domain)):' .
						// or
						'|' .
						// a scheme that allows an empty authority
						// (Remember for later that the authority can be empty)
						'(?P<empty_authority>(?P>scheme_empty_authority)):' .
						// or
						'|' .
						// a scheme that uses no authority
						'(?P>scheme_no_authority):(?!//)' .
						// or
						'|' .
						// another scheme, but only if it is followed by "://"
						'(?P>scheme_any):(?=//)' .
					')' .

					// or
					'|' .

					// An empty string followed by "//" for schemeless URLs
					'(?P<schemeless>(?=//))' .
				')' .

				// IRI authority chunk (maybe)
				'(?:' .
					// (Keep track of whether we find a valid authority or not)
					'(?P<has_authority>' .
						// 2 slashes before the authority itself
						'//' .
						'(?:' .
							// If there was no scheme...
							'(?(<schemeless>)' .
								// require an authority that contains a domain.
								'(?P>authority)' .

								// Else if a domain is needed...
								'|(?(<need_domain>)' .
									// require an authority with a domain.
									'(?P>authority)' .

									// Else if an empty authority is allowed...
									'|(?(<empty_authority>)' .
										// then require either
										'(?:' .
											// empty string, followed by a "/"
											'(?=/)' .
											// or
											'|' .
											// an authority with a domain.
											'(?P>authority)' .
										')' .

										// Else just a run of IRI characters.
										'|(?P>no_domain)' .
									')' .
								')' .
							')' .
						')' .
						// Followed by a non-domain character or end of line
						'(?=(?P>not_domain_label_char)|$)' .
					')' .

					// or, if there is a scheme but no authority
					// (e.g. "mailto:" URLs)...
					'|' .

					// A run of IRI characters
					'(?P>no_domain)' .
					// If scheme needs a domain, require a dot and a TLD
					'(?(<need_domain>)\.(?P>tlds))' .
					// Followed by a non-domain character or end of line
					'(?=(?P>not_domain_label_char)|$)' .
				')' .
			')' .

			// Or, if there is neither a scheme nor an authority...
			'|' .

			// 1b. Naked domains
			// (e.g. "example.com" in "Go to example.com for an example.")
			'(?P<naked_domain>' .
				// Preceded by start of line or a space
				'(?P>space_lookbehind)' .
				// A domain name
				'(?P>domain)' .
				// Followed by a non-domain character or end of line
				'(?=(?P>not_domain_label_char)|$)' .
			')' .
		')' .

		// 2. IRI path, query, and fragment components (if present)
		'(?:' .
			// If the IRI has an authority or is a naked domain and any of these
			// components exist, the path must start with a single "/".
			// Note: technically, it is valid to append a query or fragment
			// directly to the authority chunk without a "/", but supporting
			// that in the autolinker would produce a lot of false positives,
			// so we don't.
			'(?=' .
				// If we found an authority above...
				'(?(<has_authority>)' .
					// require a "/"
					'/' .
					// Else if we found a naked domain above...
					'|(?(<naked_domain>)' .
						// require a "/"
						'/' .
					')' .
				')' .
			')' .

			// 2.a. Path component, if any.
			'(?:' .
				// Can have one or more segments
				'(?:' .
					// Not preceded by a "/", except in the special case of an
					// empty authority immediately before the path.
					'(?(<empty_authority>)' .
						'(?:(?<=://)|(?<!/))' .
						'|' .
						'(?<!/)' .
					')' .
					// Initial "/"
					'/' .
					// Then a run of allowed path segment characters
					'(?P>path_segment)*+' .
				')*+' .
			')' .

			// 2.b. Query component, if any.
			'(?:' .
				// Initial "?" that is not last character.
				'\?' . '(?=(?P>bracket_quote)*(?P>query_allowed))' .
				// Then a run of allowed query characters
				'(?P>query_segment)*+' .
			')?' .

			// 2.c. Fragment component, if any.
			'(?:' .
				// Initial "#" that is not last character.
				'#' . '(?=(?P>bracket_quote)*(?P>fragment_allowed))' .
				// Then a run of allowed fragment characters
				'(?P>fragment_segment)*+' .
			')?' .
		')?+';

		// Finally, define the PCRE subroutines in the regex.
		$this->url_regex .= '(?(DEFINE)';

		foreach ($pcre_subroutines as $name => $subroutine) {
			$this->url_regex .= '(?<' . $name . '>' . $subroutine . ')';
		}

		$this->url_regex .= ')';
	}

	/**
	 * Sets $this->js_url_regexes.
	 */
	protected function setJavaScriptUrlRegexes(): void
	{
		// Don't repeat this unnecessarily.
		if (!empty($this->js_url_regexes)) {
			return;
		}

		$this->setJavaScriptTldRegex();

		$pct = '%[0-9A-Fa-f]{2}';
		$space_lookahead = '(?=$|\s|<br>)';
		$space_lookbehind = '(?<=^|\s|<br>)';
		$domain_label_char = '[' . strtr(self::$domain_label_chars, ['\\x{' => '\\u{']) . ']';
		$not_domain_label_char = '[^' . strtr(self::$domain_label_chars, ['\\x{' => '\\u{']) . ']';
		$domain = '(?:(?:' . $domain_label_char . ')+\.)+(?:' . $this->js_tld_regex . ')(?!\.(?:' . $domain_label_char . '))';
		$no_domain = '(?:(?:' . $domain_label_char . ')|[._~!$&\'()*+,;=:@]|(?:' . $pct . '))+';
		$scheme_need_domain = strtr(Utils::buildRegex(self::$schemes['need_domain'], '/'), ['(?' . '>' => '(?:']);
		$scheme_empty_authority = strtr(Utils::buildRegex(self::$schemes['empty_authority'], '/'), ['(?' . '>' => '(?:']);
		$scheme_no_authority = strtr(Utils::buildRegex(self::$schemes['no_authority'], '/'), ['(?' . '>' => '(?:']);
		$scheme_any = '[A-Za-z][0-9A-Za-z+\-.]*';
		$user_info = '(?:(?:' . $domain_label_char . ')|[._~!$&\'()*+,;=:]|(?:' . $pct . '))+';
		$dec_octet = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)';
		$h16 = '[0-9A-Fa-f]{1,4}';
		$ipv4 = '(?:\b(?:(?:' . $dec_octet . ')\.){3}(?:' . $dec_octet . ')\b)';
		$ipv6 = '\[(?:' . implode('|', [
			'(?:(?:' . $h16 . '):){7}(?:' . $h16 . ')',
			'(?:(?:' . $h16 . '):){1,7}:',
			'(?:(?:' . $h16 . '):){1,6}(?::(?:' . $h16 . '))',
			'(?:(?:' . $h16 . '):){1,5}(?::(?:' . $h16 . ')){1,2}',
			'(?:(?:' . $h16 . '):){1,4}(?::(?:' . $h16 . ')){1,3}',
			'(?:(?:' . $h16 . '):){1,3}(?::(?:' . $h16 . ')){1,4}',
			'(?:(?:' . $h16 . '):){1,2}(?::(?:' . $h16 . ')){1,5}',
			'(?:' . $h16 . '):(?::(?:' . $h16 . ')){1,6}',
			':(?:(?::(?:' . $h16 . ')){1,7}|:)',
			'fe80:(?::(?:' . $h16 . ')){0,4}%[0-9A-Za-z]+',
			'::(ffff(:0{1,4})?:)?(?:' . $ipv4 . ')',
			'(?:(?:' . $h16 . '):){1,4}:(?:' . $ipv4 . ')',
		]) . ')\]';
		$host = '(?:' . implode('|', [
			'localhost',
			'(?:' . $domain . ')',
			'(?:' . $ipv4 . ')',
			'(?:' . $ipv6 . ')',
		]) . ')';
		$authority = '(?:' . $user_info . '@)?' . $host . '(?::\d+)?';
		$excluded_lookahead = '(?![' . self::$excluded_trailing_chars . ']*' . $space_lookahead . ')';
		$end = '(?=' . $not_domain_label_char . '|[' . self::$excluded_trailing_chars . ']*' . $space_lookahead . '|$)';

		$allowed_entities = '&(?![lg]t;)';

		$path_allowed = '[^\s<>' . self::$excluded_trailing_chars . '\/#&]|(?:' . $allowed_entities . ')|[' . str_replace('?', '', self::$excluded_trailing_chars) . ']' . $excluded_lookahead;

		$path_component =
			'(?:' .
				'(?<!\/)' .
				'\/' .
				'(?:' .
					$path_allowed .
				')*' .
			')*';

		$empty_authority_path_component =
			'(?:' .
				'(?:' .
					'(?<=:\/\/)' .
					'|' .
					'(?<!\/)' .
				')' .
				'\/' .
				'(?:' .
					$path_allowed .
				')*' .
			')*';

		$query_component =
			'(?:' .
				'\?' .
				'(?:' .
					'[^\s<>' . self::$excluded_trailing_chars . '#&]' .
					'|' .
					'(?:' . $allowed_entities . ')' .
					'|' .
					'[' . self::$excluded_trailing_chars . ']' . $excluded_lookahead .
				')+' .
			')?';

		$fragment_component =
			'(?:' .
				'#' .
				'(?:' .
					'[^\s<>' . self::$excluded_trailing_chars . '&]' .
					'|' .
					'(?:' . $allowed_entities . ')' .
					'|' .
					'[' . self::$excluded_trailing_chars . ']' . $excluded_lookahead . '' .
				')+' .
			')?';

		// Schemeless URLs with an authority, e.g. '//www.example.com/index.php'
		$this->js_url_regexes['schemeless'] = '(?<!:)\/\/' . $authority . '(?:(?=\/)' . $path_component . $query_component . $fragment_component . ')?' . $end;

		// URLs with an authority, e.g. 'https://www.example.com/index.php'
		$this->js_url_regexes['normal'] = '\b' . $scheme_need_domain . ':\/\/' . $authority . '(?:(?=\/)' . $path_component . $query_component . $fragment_component . ')?' . $end;

		// URLs that allow an empty authority, e.g. 'file:///index.php'
		$this->js_url_regexes['empty_authority'] = '\b' . $scheme_empty_authority . ':\/\/(?:' . $authority . ')?(?:' . $empty_authority_path_component . $query_component . $fragment_component . ')?' . $end;

		// URLs that use no authority, e.g. 'mailto:foo@example.com'
		$this->js_url_regexes['no_authority'] = '\b' . $scheme_no_authority . ':(?!\/\/)(?:' . $no_domain . ')(?:\.(?:' . $this->js_tld_regex . '))?(?:' . $path_component . $query_component . $fragment_component . ')?' . $end;

		// URLs that use an authority that doesn't include a domain, e.g. 'tel://+19995551234'
		$this->js_url_regexes['no_domain'] = '\b(?!' . $scheme_need_domain . ')' . $scheme_any . ':\/\/' . $no_domain . '(?:(?=\/)' . $path_component . $query_component . $fragment_component . ')?' . $end;

		// Naked domain names, e.g. 'www.example.com/index.php'
		$this->js_url_regexes['naked_domain'] = $space_lookbehind . '(?:' . $domain . ')(?:(?=\/)' . $path_component . $query_component . $fragment_component . ')?' . $end;
	}
}

?>