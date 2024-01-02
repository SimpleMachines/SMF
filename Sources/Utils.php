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

/**
 * Holds some widely used stuff, like $context and $smcFunc.
 */
class Utils
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'sanitizeChars' => 'sanitize_chars',
			'normalizeSpaces' => 'normalize_spaces',
			'htmlspecialcharsRecursive' => 'htmlspecialchars__recursive',
			'htmlspecialcharsDecode' => 'un_htmlspecialchars',
			'htmlTrimRecursive' => 'htmltrim__recursive',
			'shorten' => 'shorten_subject',
			'text2words' => 'text2words',
			'buildRegex' => 'build_regex',
			'cleanXml' => 'cleanXml',
			'JavaScriptEscape' => 'JavaScriptEscape',
			'stripslashesRecursive' => 'stripslashes__recursive',
			'urldecodeRecursive' => 'urldecode__recursive',
			'escapestringRecursive' => 'escapestring__recursive',
			'unescapestringRecursive' => 'unescapestring__recursive',
			'truncateArray' => 'truncate_array',
			'arrayLength' => 'array_length',
			'jsonDecode' => 'smf_json_decode',
			'safeSerialize' => 'safe_serialize',
			'safeUnserialize' => 'safe_unserialize',
			'getMimeType' => 'get_mime_type',
			'checkMimeType' => 'check_mime_type',
			'makeWritable' => 'smf_chmod',
			'sendHttpStatus' => 'send_http_status',
			'serverResponse' => 'smf_serverResponse',
			'redirectexit' => 'redirectexit',
			'obExit' => 'obExit',
			'getCallable' => 'getCallable',
			'call_helper' => 'call_helper',
			'replaceEntities__callback' => 'replaceEntities__callback',
			'fixchar__callback' => 'fixchar__callback',
			'entity_fix__callback' => 'entity_fix__callback',
		],
		'prop_names' => [
			'context' => 'context',
			'smcFunc' => 'smcFunc',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Regular expression to match named entities for HTML special characters
	 * and any numeric entities.
	 */
	public const ENT_LIST = '&(?' . '>nbsp|quot|gt|lt|a(?' . '>pos|mp)|#(?' . '>\d+|x[0-9a-fA-F]+));';

	/**
	 * Regular expression to match all forms of the non-breaking space entity.
	 */
	public const ENT_NBSP = '&(?' . '>nbsp|#(?' . '>x0*A0|0*160));';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * SMF's venerable $context variable, now available as Utils::$context.
	 */
	public static $context = [
		// Assume UTF-8 until proven otherwise.
		'utf8' => true,
		'character_set' => 'UTF-8',
		// Define a list of icons used across multiple places.
		'stable_icons' => [
			'xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp',
			'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved',
			'recycled', 'clip',
		],
		// Define an array for custom profile fields placements.
		'cust_profile_fields_placement' => [
			'standard',
			'icons',
			'above_signature',
			'below_signature',
			'below_avatar',
			'above_member',
			'bottom_poster',
			'before_member',
			'after_member',
		],
		// Define an array for content-related <meta> elements (e.g. description,
		// keywords, Open Graph) for the HTML head.
		'meta_tags' => [],
		// Define an array of allowed HTML tags.
		'allowed_html_tags' => [
			'<img>',
			'<div>',
		],
		// Define a list of allowed tags for descriptions.
		'description_allowed_tags' => [
			'abbr', 'anchor', 'b', 'br', 'center', 'color', 'font', 'hr', 'i',
			'img', 'iurl', 'left', 'li', 'list', 'ltr', 'pre', 'right', 's',
			'sub', 'sup', 'table', 'td', 'tr', 'u', 'url',
		],
		// Define a list of deprecated BBC tags.
		// Even when enabled, they'll only work in old posts and not new ones.
		'legacy_bbc' => [
			'acronym', 'bdo', 'black', 'blue', 'flash', 'ftp', 'glow',
			'green', 'move', 'red', 'shadow', 'tt', 'white',
		],
		// Define a list of BBC tags that require permissions to use.
		'restricted_bbc' => [
			'html',
		],
		// Login Cookie times. Format: time => txt
		'login_cookie_times' => [
			3153600 => 'always_logged_in',
			60 => 'one_hour',
			1440 => 'one_day',
			10080 => 'one_week',
			43200 => 'one_month',
		],
		'show_spellchecking' => false,
	];

	/**
	 * @var array
	 *
	 * Backward compatibility aliases of various utility functions.
	 */
	public static $smcFunc = [
		'entity_decode' => __CLASS__ . '::entityDecode',
		'sanitize_entities' => __CLASS__ . '::sanitizeEntities',
		'entity_fix' => __CLASS__ . '::entityFix',
		'htmlspecialchars' => __CLASS__ . '::htmlspecialchars',
		'htmltrim' => __CLASS__ . '::htmlTrim',
		'strlen' => __CLASS__ . '::entityStrlen',
		'strpos' => __CLASS__ . '::entityStrpos',
		'substr' => __CLASS__ . '::entitySubstr',
		'strtolower' => __CLASS__ . '::strtolower',
		'strtoupper' => __CLASS__ . '::strtoupper',
		'ucfirst' => __CLASS__ . '::ucfirst',
		'ucwords' => __CLASS__ . '::ucwords',
		'convert_case' => __CLASS__ . '::convertCase',
		'normalize' => __CLASS__ . '::normalize',
		'truncate' => __CLASS__ . '::truncate',
		'json_encode' => __CLASS__ . '::jsonEncode',
		'json_decode' => __CLASS__ . '::jsonDecode',
		'random_int' => __CLASS__ . '::randomInt',
		'random_bytes' => __CLASS__ . '::randomBytes',
	];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * (Re)initializes some $context values that need to be set dynamically.
	 */
	public static function load(): void
	{
		// Used to force browsers to download fresh CSS and JavaScript when necessary
		if (isset(Config::$modSettings['browser_cache'])) {
			self::$context['browser_cache'] = '?' . preg_replace('~\W~', '', strtolower(SMF_FULL_VERSION)) . '_' . Config::$modSettings['browser_cache'];
		}

		// UTF-8?
		if (isset(Config::$modSettings['global_character_set'])) {
			self::$context['character_set'] = Config::$modSettings['global_character_set'];
			self::$context['utf8'] = self::$context['character_set'] === 'UTF-8';
		}

		// This determines the server... not used in many places, except for login fixing.
		self::$context['server'] = [
			'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
			'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
			'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
			'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
			'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
			'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
			'is_windows' => DIRECTORY_SEPARATOR === '\\',
			'is_mac' => PHP_OS === 'Darwin',
			'iso_case_folding' => ord(strtolower(chr(138))) === 154,
		];

		// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
		self::$context['server']['needs_login_fix'] = self::$context['server']['is_cgi'] && self::$context['server']['is_iis'];
	}

	/**
	 * Decodes and sanitizes HTML entities.
	 *
	 * If database does not support 4-byte UTF-8 characters, entities for 4-byte
	 * characters are left in place, unless the $mb4 argument is set to true.
	 *
	 * @param string $string The string in which to decode entities.
	 * @param bool $mb4 If true, always decode 4-byte UTF-8 characters.
	 *      Default: false.
	 * @param int $flags Flags to pass to html_entity_decode.
	 * 		Default: ENT_QUOTES | ENT_HTML5.
	 * @param bool $nbsp_to_space If true, decode '&nbsp;' to space character.
	 * 		Default: false.
	 * @return string The string with the entities decoded.
	 */
	public static function entityDecode(string $string, bool $mb4 = false, int $flags = ENT_QUOTES | ENT_HTML5, bool $nbsp_to_space = false): string
	{
		// Don't waste time on empty strings.
		if (trim($string) === '') {
			return $string;
		}

		// In theory this is always UTF-8, but...
		if (empty(self::$context['character_set'])) {
			$charset = is_callable('mb_detect_encoding') ? mb_detect_encoding($string) : 'UTF-8';
		} elseif (strpos(self::$context['character_set'], 'ISO-8859-') !== false && !in_array(self::$context['character_set'], ['ISO-8859-5', 'ISO-8859-15'])) {
			$charset = 'ISO-8859-1';
		} else {
			$charset = self::$context['character_set'];
		}

		// Enables consistency with the behaviour of un_htmlspecialchars.
		if ($nbsp_to_space) {
			$string = preg_replace('~' . self::ENT_NBSP . '~u', ' ', $string);
		}

		// Do the deed.
		$string = html_entity_decode($string, $flags, $charset);

		// Remove any illegal character entities.
		$string = self::sanitizeEntities($string);

		// Finally, make sure we don't break the database.
		if (!$mb4) {
			$string = self::fixUtf8mb4($string);
		}

		return $string;
	}

	/**
	 * Fixes double-encoded entities in a string.
	 *
	 * @param string $string The string.
	 * @return string The fixed string.
	 */
	public static function entityFix(string $string): string
	{
		return preg_replace('~&amp;(' . substr(self::ENT_LIST, 1, -1) . ');~', '&$1;', $string);
	}

	/**
	 * Replaces HTML entities for invalid characters with a substitute.
	 *
	 * The default substitute is the entity for the replacement character U+FFFD
	 * (a.k.a. the question-mark-in-a-box).
	 *
	 * !!! Warning !!! Setting $substitute to '' in order to delete invalid
	 * entities from the string can create unexpected security problems. See
	 * https://www.unicode.org/reports/tr36/#Deletion_of_Noncharacters for an
	 * explanation.
	 *
	 * @param string $string The string to sanitize.
	 * @param string $substitute Replacement for the invalid entities.
	 *      Default: '&#65533;'
	 * @return string The sanitized string.
	 */
	public static function sanitizeEntities(string $string, string $substitute = '&#65533;'): string
	{
		if (strpos($string, '&#') === false) {
			return $string;
		}

		// Disallow entities for control characters, non-characters, etc.
		return preg_replace_callback(
			'~(&#(0*\d{1,7}|x0*[0-9a-fA-F]{1,6});)~',
			function ($matches) use ($substitute) {
				$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

				if (
					// Control characters (except \t, \n, and \r).
					($num < 0x20 && $num !== 0x9 && $num !== 0xA && $num !== 0xD)
					|| ($num >= 0x74 && $num < 0xA0)

					// UTF-16 surrogate pairs.
					|| ($num >= 0xD800 && $num <= 0xDFFF)

					// Code points that are guaranteed never to be characters.
					|| ($num >= 0xFDD0 && $num <= 0xFDEF)
					|| (in_array($num % 0x10000, [0xFFFE, 0xFFFF]))

					// Out of range.
					|| $num > 0x10FFFF
				) {
					return $substitute;
				}

				return '&#' . $num . ';';
			},
			(string) $string,
		);
	}

	/**
	 * Replaces invalid characters with a substitute.
	 *
	 * !!! Warning !!! Setting $substitute to '' in order to delete invalid
	 * characters from the string can create unexpected security problems. See
	 * https://www.unicode.org/reports/tr36/#Deletion_of_Noncharacters for an
	 * explanation.
	 *
	 * @param string $string The string to sanitize.
	 * @param int $level Controls filtering of invisible formatting characters.
	 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
	 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
	 *      2: Disallow all formatting characters. Use for internal comparisons
	 *         only, such as in the word censor, search contexts, etc.
	 *      Default: 0.
	 * @param string|null $substitute Replacement string for the invalid characters.
	 *      If not set, the Unicode replacement character (U+FFFD) will be used
	 *      (or a fallback like "?" if necessary).
	 * @return string The sanitized string.
	 */
	public static function sanitizeChars($string, $level = 0, $substitute = null)
	{
		$string = (string) $string;
		$level = min(max((int) $level, 0), 2);

		// What substitute character should we use?
		if (isset($substitute)) {
			$substitute = strval($substitute);
		} elseif (!empty(Utils::$context['utf8'])) {
			// Raw UTF-8 bytes for U+FFFD.
			$substitute = "\xEF\xBF\xBD";
		} elseif (!empty(Utils::$context['character_set']) && is_callable('mb_decode_numericentity')) {
			// Get whatever the default replacement character is for this encoding.
			$substitute = mb_decode_numericentity('&#xFFFD;', [0xFFFD, 0xFFFD, 0, 0xFFFF], Utils::$context['character_set']);
		} else {
			$substitute = '?';
		}

		// Fix any invalid byte sequences.
		if (!empty(Utils::$context['character_set'])) {
			// For UTF-8, this preg_match test is much faster than mb_check_encoding.
			$malformed = !empty(Utils::$context['utf8']) ? @preg_match('//u', $string) === false && preg_last_error() === PREG_BAD_UTF8_ERROR : (!is_callable('mb_check_encoding') || !mb_check_encoding($string, Utils::$context['character_set']));

			if ($malformed) {
				// mb_convert_encoding will replace invalid byte sequences with our substitute.
				if (is_callable('mb_convert_encoding')) {
					if (!is_callable('mb_ord')) {
						require_once Config::$sourcedir . '/Subs-Compat.php';
					}

					$substitute_ord = $substitute === '' ? 'none' : mb_ord($substitute, Utils::$context['character_set']);

					$mb_substitute_character = mb_substitute_character();
					mb_substitute_character($substitute_ord);

					$string = mb_convert_encoding($string, Utils::$context['character_set'], Utils::$context['character_set']);

					mb_substitute_character($mb_substitute_character);
				} else {
					return false;
				}
			}
		}

		// Fix any weird vertical space characters.
		$string = Utils::normalizeSpaces($string, true);

		// Deal with unwanted control characters, invisible formatting characters, and other creepy-crawlies.
		if (!empty(Utils::$context['utf8'])) {
			$string = (string) Unicode\Utf8String::create($string)->sanitizeInvisibles($level, $substitute);
		} else {
			$string = preg_replace('/[^\P{Cc}\t\r\n]/', $substitute, $string);
		}

		return $string;
	}

	/**
	 * Normalizes space characters and line breaks.
	 *
	 * @param string $string The string to sanitize.
	 * @param bool $vspace If true, replaces all line breaks and vertical space
	 *      characters with "\n". Default: true.
	 * @param bool $hspace If true, replaces horizontal space characters with a
	 *      plain " " character. (Note: tabs are not replaced unless the
	 *      'replace_tabs' option is supplied.) Default: false.
	 * @param array $options An array of boolean options. Possible values are:
	 *      - no_breaks: Vertical spaces are replaced by " " instead of "\n".
	 *      - replace_tabs: If true, tabs are are replaced by " " chars.
	 *      - collapse_hspace: If true, removes extra horizontal spaces.
	 * @return string The sanitized string.
	 */
	public static function normalizeSpaces($string, $vspace = true, $hspace = false, $options = [])
	{
		$string = (string) $string;
		$vspace = !empty($vspace);
		$hspace = !empty($hspace);

		if (!$vspace && !$hspace) {
			return $string;
		}

		$options['no_breaks'] = !empty($options['no_breaks']);
		$options['collapse_hspace'] = !empty($options['collapse_hspace']);
		$options['replace_tabs'] = !empty($options['replace_tabs']);

		$patterns = [];
		$replacements = [];

		if ($vspace) {
			// \R is like \v, except it handles "\r\n" as a single unit.
			$patterns[] = '/\R/' . (Utils::$context['utf8'] ? 'u' : '');
			$replacements[] = $options['no_breaks'] ? ' ' : "\n";
		}

		if ($hspace) {
			// Interesting fact: Unicode properties like \p{Zs} work even when not in UTF-8 mode.
			$patterns[] = '/' . ($options['replace_tabs'] ? '\h' : '\p{Zs}') . ($options['collapse_hspace'] ? '+' : '') . '/' . (Utils::$context['utf8'] ? 'u' : '');
			$replacements[] = ' ';
		}

		return preg_replace($patterns, $replacements, $string);
	}

	/**
	 * Wrapper for standard htmlspecialchars() that ensures the output respects
	 * the database's support (or lack thereof) for four-byte UTF-8 characters.
	 *
	 * @param string $string The string being converted.
	 * @param int $flags Bitmask of flags to pass to standard htmlspecialchars().
	 *    Default is ENT_COMPAT.
	 * @param string $encoding Character encoding. Default is UTF-8.
	 * @return string The converted string.
	 */
	public static function htmlspecialchars(string $string, int $flags = ENT_COMPAT, $encoding = 'UTF-8'): string
	{
		$string = self::normalize($string);

		return self::fixUtf8mb4(self::sanitizeEntities(\htmlspecialchars($string, $flags, $encoding)));
	}

	/**
	 * Recursively applies self::htmlspecialchars() to all elements of an array.
	 *
	 * Only affects values.
	 *
	 * @param array|string $var The string or array of strings to add entities to
	 * @param int $flags Bitmask of flags to pass to standard htmlspecialchars().
	 *    Default is ENT_COMPAT.
	 * @param string $encoding Character encoding. Default is UTF-8.
	 * @return array|string The string or array of strings with entities added
	 */
	public static function htmlspecialcharsRecursive(array|string $var, int $flags = ENT_COMPAT, $encoding = 'UTF-8'): array|string
	{
		static $level = 0;

		if (!is_array($var)) {
			return self::htmlspecialchars($var, $flags, $encoding);
		}

		// Add the htmlspecialchars to every element.
		foreach ($var as $k => $v) {
			if ($level > 25) {
				$var[$k] = null;
			} else {
				$level++;
				$var[$k] = self::htmlspecialcharsRecursive($v, $flags, $encoding);
				$level--;
			}
		}

		return $var;
	}

	/**
	 * Replaces special entities in strings with the real characters.
	 *
	 * Functionally equivalent to htmlspecialchars_decode(), except that this also
	 * replaces '&nbsp;' with a simple space character.
	 *
	 * @param string $string A string.
	 * @param int $flags Bitmask of flags to pass to standard htmlspecialchars().
	 *    Default is ENT_QUOTES.
	 * @param string $encoding Character encoding. Default is UTF-8.
	 * @return string The string without entities.
	 */
	public static function htmlspecialcharsDecode(string $string, int $flags = ENT_QUOTES, $encoding = 'UTF-8'): string
	{
		return preg_replace('/' . self::ENT_NBSP . '/u', ' ', htmlspecialchars_decode($string, $flags));
	}

	/**
	 * Like standard trim(), except that it also trims &nbsp; entities, control
	 * characters, and Unicode whitespace characters beyond the ASCII range.
	 *
	 * @param string $string The string.
	 * @return string The trimmed string.
	 */
	public static function htmlTrim(string $string): string
	{
		return preg_replace('~^(?' . '>[\p{Z}\p{C}]|' . self::ENT_NBSP . ')+|(?' . '>[\p{Z}\p{C}]|' . self::ENT_NBSP . ')+$~u', '', self::sanitizeEntities($string));
	}

	/**
	 * Recursively applies self::htmlTrim to all elements of an array.
	 *
	 * Only affects values.
	 *
	 * @param array|string $var The string or array of strings to trim.
	 * @return array|string The trimmed string or array of trimmed strings.
	 */
	public static function htmlTrimRecursive(array|string $var): array|string
	{
		static $level = 0;

		// Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
		if (!is_array($var)) {
			return self::htmlTrim($var);
		}

		// Go through all the elements and remove the whitespace.
		foreach ($var as $k => $v) {
			if ($level > 25) {
				$var[$k] = null;
			} else {
				$level++;
				$var[$k] = self::htmlTrimRecursive($v);
				$level--;
			}
		}

		return $var;
	}

	/**
	 * Like standard mb_strlen(), except that it counts HTML entities as
	 * single characters. This essentially amounts to getting the length of
	 * the string as it would appear to a human reader.
	 *
	 * @param string $string The string.
	 * @return int The length of the string.
	 */
	public static function entityStrlen(string $string): int
	{
		return strlen(preg_replace('~' . self::ENT_LIST . '|\X~u', '_', self::sanitizeEntities($string)));
	}

	/**
	 * Like standard mb_strpos(), except that it counts HTML entities as
	 * single characters.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for.
	 * @param int $offset Search offset within $haystack.
	 * @return int|false Position of $needle in $haystack, or false on failure.
	 */
	public static function entityStrpos(string $haystack, string $needle, int $offset = 0): int|false
	{
		$haystack_arr = preg_split('~(' . self::ENT_LIST . '|\X)~u', self::sanitizeEntities($haystack), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if (strlen($needle) === 1) {
			$result = array_search($needle, array_slice($haystack_arr, $offset));

			return is_int($result) ? $result + $offset : false;
		}

		$needle_arr = preg_split('~(' . self::ENT_LIST . '|\X)~u', self::sanitizeEntities($needle), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		$needle_size = count($needle_arr);

		$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));

		while ((int) $result === $result) {
			$offset += $result;

			if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr) {
				return $offset;
			}

			$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
		}

		return false;
	}

	/**
	 * Like standard mb_substr(), except that it counts HTML entities as
	 * single characters.
	 *
	 * @param string $string The input string.
	 * @param int $offset Offset where substring will start.
	 * @param int $length Maximum length, in characters, of the substring.
	 * @return string The substring.
	 */
	public static function entitySubstr(string $string, int $offset, ?int $length = null): string
	{
		$ent_arr = preg_split('~(' . self::ENT_LIST . '|\X)~u', self::sanitizeEntities($string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		return $length === null ? implode('', array_slice($ent_arr, $offset)) : implode('', array_slice($ent_arr, $offset, $length));
	}

	/**
	 * Truncates a string to fit within the specified byte length, while making
	 * sure not to cut in the middle of an HTML entity or multi-byte character.
	 *
	 * The difference between this and the entitySubstr method is that this
	 * truncates the string to fit into a certain byte length, whereas
	 * entitySubstr truncates to fit into a certain visual character length.
	 * This difference is important when dealing with Unicode.
	 *
	 * @param string $string The input string.
	 * @param int $length The maximum length, in bytes, of the returned string.
	 * @return string The truncated string.
	 */
	public static function truncate(string $string, int $length): string
	{
		$string = self::sanitizeEntities($string);

		while (strlen($string) > $length) {
			$string = preg_replace('~(?:' . self::ENT_LIST . '|\X)$~u', '', $string);
		}

		return $string;
	}

	/**
	 * Like Utils::entitySubstr(), except that this also appends an ellipsis
	 * to the returned string to indicate that it was truncated (unless it
	 * wasn't truncated because it was already short enough).
	 *
	 * @param string $subject The string.
	 * @param int $len How many characters to limit it to.
	 * @return string The shortened string.
	 */
	public static function shorten(string $subject, int $len): string
	{
		// It was already short enough!
		if (self::entityStrlen($subject) <= $len) {
			return $subject;
		}

		// Truncate it and append an ellipsis.
		return self::entitySubstr($subject, 0, $len) . '...';
	}

	/**
	 * Performs Unicode normalization on a UTF-8 string.
	 *
	 * Note that setting $form to 'kc_casefold' will cause the string's case to
	 * be folded and will also remove all "default ignorable code points" from
	 * the string. It should be used (1) when validating identifier strings that
	 * must be unambigously unique, such as domain names, file names, or even
	 * SMF user names, or (2) when performing caseless matching of strings, such
	 * as when performing a search or checking for censored words in a post.
	 *
	 * @param string $string The input string.
	 * @param string $form A Unicode normalization form: 'c', 'd', 'kc', 'kd',
	 *    or 'kc_casefold'.
	 * @return string The normalized string.
	 */
	public static function normalize(string $string, string $form = 'c'): string
	{
		return (string) Unicode\Utf8String::create($string)->normalize($form);
	}

	/**
	 * Performs case conversion on UTF-8 strings.
	 *
	 * Similar to, but with more capabilities than, mb_convert_case().
	 *
	 * Note that setting $form to 'kc_casefold' will override any value of $case
	 * and will always fold the case of the string. Also note that setting $form
	 * to 'kc_casefold' is not the same as setting $case to 'fold' and $form to
	 * 'kc'; specifically, setting $case to 'fold' and $form to 'kc' does not
	 * remove "default ignorable code points" from the string, whereas setting
	 * $form to 'kc_casefold' does. See notes on the normalize() method for more
	 * information about when 'kc_casefold' should be used.
	 *
	 * @param string $string The input string.
	 * @param string $case One of 'upper', 'lower', 'fold', 'title', 'ucfirst',
	 *    or 'ucwords'.
	 * @param bool $simple If true, use simple maps instead of full maps.
	 *    Default: false.
	 * @param string $form A Unicode normalization form: 'c', 'd', 'kc', 'kd',
	 *    or 'kc_casefold'.
	 * @return string The normalized string.
	 */
	public static function convertCase(string $string, string $case, bool $simple = false, string $form = 'c'): string
	{
		// Convert numeric entities to characters, except special ones.
		if (strpos($string, '&#') !== false) {
			$string = strtr(self::sanitizeEntities($string), [
				'&#34;' => '&quot;',
				'&#38;' => '&amp;',
				'&#39;' => '&apos;',
				'&#60;' => '&lt;',
				'&#62;' => '&gt;',
				'&#160;' => '&nbsp;',
			]);

			$string = mb_decode_numericentity($string, [0, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');
		}

		// Use optimized function for compatibility casefolding.
		if ($form === 'kc_casefold') {
			$string = (string) Unicode\Utf8String::create($string)->normalize('kc_casefold');
		}
		// Everything else.
		else {
			$string = (string) Unicode\Utf8String::create($string)->convertCase($case, $simple)->normalize($form);
		}

		return self::fixUtf8mb4($string);
	}

	/**
	 * Convenience alias of Utils::convertCase($string, 'upper')
	 *
	 * @param string $string The input string.
	 * @return string The uppercase version of the input string.
	 */
	public static function strtoupper(string $string): string
	{
		return self::convertCase($string, 'upper');
	}

	/**
	 * Convenience alias of Utils::convertCase($string, 'lower')
	 *
	 * @param string $string The input string.
	 * @return string The lowercase version of the input string.
	 */
	public static function strtolower(string $string): string
	{
		return self::convertCase($string, 'lower');
	}

	/**
	 * Convenience alias of Utils::convertCase($string, 'fold')
	 *
	 * @param string $string The input string.
	 * @return string The casefolded version of the input string.
	 */
	public static function casefold(string $string): string
	{
		return self::convertCase($string, 'fold');
	}

	/**
	 * Convenience alias of Utils::convertCase($string, 'title')
	 *
	 * @param string $string The input string.
	 * @return string The titlecase version of the input string.
	 */
	public static function strtotitle(string $string): string
	{
		return self::convertCase($string, 'upper');
	}

	/**
	 * Convenience alias of Utils::convertCase($string, 'ucfirst')
	 *
	 * @param string $string The input string.
	 * @return string The string, but with the first character in titlecase.
	 */
	public static function ucfirst(string $string): string
	{
		return self::convertCase($string, 'ucfirst');
	}

	/**
	 * Convenience alias of Utils::convertCase($string, 'ucwords')
	 *
	 * @param string $string The input string.
	 * @return string The string, but with the first character of each word in titlecase.
	 */
	public static function ucwords(string $string): string
	{
		return self::convertCase($string, 'ucwords');
	}

	/**
	 * Chops a string into words and prepares them to be inserted into (or
	 * searched from) the database.
	 *
	 * @param string $string The text to split into words.
	 * @param ?int $max_length The maximum byte length for each word.
	 * @param bool $encrypt Whether to encrypt the results.
	 * @return array An array of strings or integers, depending on $encrypt.
	 */
	public static function text2words(string $string, ?int $max_length = 20, bool $encrypt = false): array
	{
		if (empty($max_length)) {
			$max_length = PHP_INT_MAX;
		}

		$words = Unicode\Utf8String::create($string)->extractWords(2);

		if (!$encrypt) {
			foreach ($words as &$word) {
				$word = self::truncate($word, $max_length);
			}

			return array_unique($words);
		}

		// We want to "encrypt" the words, which basically just means getting a
		// unique number for each one...
		$returned_ints = [];

		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));

		foreach ($words as $word) {
			if (($word = trim($word, '-_\'')) !== '') {
				$encrypted = substr(crypt($word, 'uk'), 2, $max_length);

				$total = 0;

				for ($i = 0; $i < $max_length; $i++) {
					$total += $possible_chars[ord($encrypted[$i])] * pow(63, $i);
				}

				$returned_ints[] = $max_length == 4 ? min($total, 16777215) : $total;
			}
		}

		return array_unique($returned_ints);
	}

	/**
	 * Creates optimized regular expressions from an array of strings.
	 *
	 * An optimized regex built using this function will be much faster than a
	 * simple regex built using `implode('|', $strings)` --- anywhere from
	 * several times to several orders of magnitude faster.
	 *
	 * However, the time required to build and execute the optimized regex is
	 * approximately equal to the time it takes to execute the simple regex.
	 * Therefore, it is usually only worth calling this method if the resulting
	 * regex will be used more than once.
	 *
	 * Because PHP places an upper limit on the allowed length of a regex, very
	 * large arrays of $strings may not fit in a single regex. Normally, the
	 * excess strings will simply be dropped. However, if the $return_array
	 * parameter is set to true, this function will build as many regexes as
	 * necessary to accommodate everything in $strings and return them in an
	 * array. You will need to iterate through all elements of the returned
	 * array in order to test all possible matches.
	 *
	 * @param array $strings An array of strings to make a regex for.
	 * @param string $delim Optional delimiter character to pass to preg_quote().
	 * @param bool $return_array If true, returns an array of regexes.
	 * @return string|array One or more regular expressions to match any of the
	 *    input strings.
	 */
	public static function buildRegex(array $strings, ?string $delim = null, bool $return_array = false): string|array
	{
		static $regexes = [];

		// If it's not an array, there's not much to do. ;)
		if (!is_array($strings)) {
			return preg_quote(@strval($strings), $delim);
		}

		$regex_key = md5(json_encode([$strings, $delim, $return_array]));

		if (isset($regexes[$regex_key])) {
			return $regexes[$regex_key];
		}

		if (($string_encoding = mb_detect_encoding(implode(' ', $strings))) !== false) {
			$current_encoding = mb_internal_encoding();
			mb_internal_encoding($string_encoding);
		}

		// This recursive closure creates the trie from the strings.
		$add_string_to_trie = function ($string, $trie) use (&$add_string_to_trie) {
			static $depth = 0;
			$depth++;

			$first = (string) @mb_substr($string, 0, 1);

			// No first character? That's no good.
			if ($first === '') {
				// A nested array? Really? Ugh. Fine.
				if (is_array($string) && $depth < 20) {
					foreach ($string as $str) {
						$trie = $add_string_to_trie($str, $trie);
					}
				}

				$depth--;

				return $trie;
			}

			if (empty($trie[$first])) {
				$trie[$first] = [];
			}

			if (mb_strlen($string) > 1) {
				// Sanity check on recursion
				if ($depth > 99) {
					$trie[$first][mb_substr($string, 1)] = '';
				} else {
					$trie[$first] = $add_string_to_trie(mb_substr($string, 1), $trie[$first]);
				}
			} else {
				$trie[$first][''] = '';
			}

			$depth--;

			return $trie;
		};

		// This recursive closure turns the trie into a regular expression.
		$trie_to_regex = function (&$trie, $delim) use (&$trie_to_regex) {
			static $depth = 0;
			$depth++;

			// Absolute max length for a regex is 32768, but we might need wiggle room
			$max_length = 30000;

			$regex = [];
			$length = 0;

			foreach ($trie as $key => $value) {
				$key_regex = preg_quote($key, $delim);
				$new_key = $key;

				if (empty($value)) {
					$sub_regex = '';
				} else {
					$sub_regex = $trie_to_regex($value, $delim);

					if (count(array_keys($value)) == 1) {
						$new_key_array = explode('(?' . '>', $sub_regex);
						$new_key .= $new_key_array[0];
					} else {
						$sub_regex = '(?' . '>' . $sub_regex . ')';
					}
				}

				if ($depth > 1) {
					$regex[$new_key] = $key_regex . $sub_regex;
				} else {
					if (($length += strlen($key_regex . $sub_regex) + 1) < $max_length || empty($regex)) {
						$regex[$new_key] = $key_regex . $sub_regex;
						unset($trie[$key]);
					} else {
						break;
					}
				}
			}

			// Sort by key length and then alphabetically
			uksort(
				$regex,
				function ($k1, $k2) {
					$l1 = mb_strlen($k1);
					$l2 = mb_strlen($k2);

					if ($l1 == $l2) {
						return strcmp($k1, $k2) > 0 ? 1 : -1;
					}

					return $l1 > $l2 ? -1 : 1;
				},
			);

			$depth--;

			return implode('|', $regex);
		};

		// Now that the closures are defined, let's do this thing.
		$trie = [];
		$regex = '';

		foreach ($strings as $string) {
			$trie = $add_string_to_trie($string, $trie);
		}

		if ($return_array === true) {
			$regex = [];

			while (!empty($trie)) {
				$regex[] = '(?' . '>' . $trie_to_regex($trie, $delim) . ')';
			}
		} else {
			$regex = '(?' . '>' . $trie_to_regex($trie, $delim) . ')';
		}

		// Restore PHP's internal character encoding to whatever it was originally.
		if (!empty($current_encoding)) {
			mb_internal_encoding($current_encoding);
		}

		// Save for later.
		$regexes[$regex_key] = $regex;

		return $regex;
	}

	/**
	 * Clean up the XML to make sure it doesn't contain invalid characters.
	 *
	 * See https://www.w3.org/TR/xml/#charsets
	 *
	 * @param string $string The string to clean
	 * @return string The cleaned string
	 */
	public static function cleanXml(string $string): string
	{
		$illegal_chars = [
			// Remove all ASCII control characters except \t, \n, and \r.
			"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08",
			"\x0B", "\x0C", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13", "\x14",
			"\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
			"\x1E", "\x1F",
			// Remove \xFFFE and \xFFFF
			"\xEF\xBF\xBE", "\xEF\xBF\xBF",
		];

		$string = str_replace($illegal_chars, '', $string);

		// The Unicode surrogate pair code points should never be present in our
		// strings to begin with, but if any snuck in, they need to be removed.
		if (!empty(Utils::$context['utf8']) && strpos($string, "\xED") !== false) {
			$string = preg_replace('/\xED[\xA0-\xBF][\x80-\xBF]/', '', $string);
		}

		return $string;
	}

	/**
	 * Escapes (replaces) characters in strings to make them safe for use in JavaScript
	 *
	 * @param string $string The string to escape
	 * @param bool $as_json If true, escape as double-quoted string. Default false.
	 * @return string The escaped string
	 */
	public static function JavaScriptEscape(string $string, bool $as_json = false): string
	{
		$q = !empty($as_json) ? '"' : '\'';

		return $q . strtr($string, [
			"\r" => '',
			"\n" => '\\n',
			"\t" => '\\t',
			'\\' => '\\\\',
			$q => addslashes($q),
			'</' => '<' . $q . ' + ' . $q . '/',
			'<script' => '<scri' . $q . '+' . $q . 'pt',
			'<body>' => '<bo' . $q . '+' . $q . 'dy>',
			'<a href' => '<a hr' . $q . '+' . $q . 'ef',
			Config::$scripturl => $q . ' + smf_scripturl + ' . $q,
		]) . $q;
	}

	/**
	 * Recursively applies stripslashes() to all elements of an array.
	 *
	 * Affects both keys and values of arrays.
	 *
	 * @param array|string $var The string or array of strings to strip slashes from
	 * @param int $level = 0 What level we're at within the array (if called recursively)
	 * @return array|string The string or array of strings with slashes stripped
	 */
	public static function stripslashesRecursive($var, $level = 0)
	{
		if (!is_array($var)) {
			return stripslashes($var);
		}

		// Reindex the array without slashes, this time.
		$new_var = [];

		// Strip the slashes from every element.
		foreach ($var as $k => $v) {
			$new_var[stripslashes($k)] = $level > 25 ? null : self::stripslashesRecursive($v, $level + 1);
		}

		return $new_var;
	}

	/**
	 * Recursively applies urldecode() to all elements of an array.
	 *
	 * Affects both keys and values of arrays.
	 *
	 * @param array|string $var The string or array of strings to decode
	 * @param int $level Which level we're at within the array (if called recursively)
	 * @return array|string The decoded string or array of decoded strings
	 */
	public static function urldecodeRecursive($var, $level = 0)
	{
		if (!is_array($var)) {
			return urldecode($var);
		}

		// Reindex the array...
		$new_var = [];

		// urldecode() every element.
		foreach ($var as $k => $v) {
			$new_var[urldecode($k)] = $level > 25 ? null : self::urldecodeRecursive($v, $level + 1);
		}

		return $new_var;
	}

	/**
	 * Recursively applies database string escaping to all elements of an array.
	 *
	 * Affects both keys and values of arrays.
	 *
	 * @param array|string $var A string or array of strings to escape
	 * @return array|string The escaped string or array of escaped strings
	 */
	public static function escapestringRecursive(array|string $var): array|string
	{
		if (!is_array($var)) {
			return Db::$db->escape_string($var);
		}

		// Reindex the array with slashes.
		$new_var = [];

		// Escape every element, even the keys!
		foreach ($var as $k => $v) {
			$new_var[Db::$db->escape_string($k)] = self::escapestringRecursive($v);
		}

		return $new_var;
	}

	/**
	 * Recursively removes database string escaping in all elements of an array.
	 *
	 * Affects both keys and values of arrays.
	 *
	 * @param array|string $var The string or array of strings to unescape
	 * @return array|string The unescaped string or array of unescaped strings
	 */
	public static function unescapestringRecursive(array|string $var): array|string
	{
		if (!is_array($var)) {
			return Db::$db->unescape_string($var);
		}

		// Reindex the array without slashes, this time.
		$new_var = [];

		// Unescape every element, even the keys!
		foreach ($var as $k => $v) {
			$new_var[Db::$db->unescape_string($k)] = self::unescapestringRecursive($v);
		}

		return $new_var;
	}

	/**
	 * Truncates the leaf nodes of an array so that their cumulative byte length
	 * fits within a specified maximum.
	 *
	 * The array's keys are not considered when calculating the total length.
	 *
	 * @param array $array The array to truncate.
	 * @param int $max_length The upperbound on the length.
	 * @return array The truncated array.
	 */
	public static function truncateArray(array $array, int $max_length = 1900): array
	{
		$num_elements = 0;

		array_walk_recursive(
			$array,
			function ($value) use (&$num_elements) {
				$num_elements++;
			},
		);

		$temp = $array;
		$current_length = self::arrayLength($array);
		$param_length = floor($current_length / $num_elements);

		while ($current_length > $max_length) {
			$temp = $array;

			array_walk_recursive(
				$temp,
				function (&$value) use ($param_length) {
					$value = self::truncate(strval($value), $param_length);
				},
			);

			$current_length = self::arrayLength($temp);
			$param_length--;
		}

		$array = $temp;

		return $array;
	}

	/**
	 * Gets the total byte length of all leaf nodes in an array.
	 *
	 * @param array $array The array.
	 * @return int Total byte length of all leaf nodes in an array.
	 */
	public static function arrayLength(array $array): int
	{
		$length = 0;

		array_walk_recursive(
			$array,
			function ($value, $key) use (&$length) {
				$length += strlen($value);
			},
		);

		return $length;
	}

	/**
	 * Wrapper function for json_decode() with error handling.
	 *
	 * @param string $json The string to decode.
	 * @param bool $associative Whether to force JSON objects to be returned as
	 *    associative arrays. SMF nearly always wants this to be true, but for
	 *    the sake of consistency with json_decode(), the default is false.
	 * @param bool $should_log Whether to log errors. Default: true.
	 * @return mixed The decoded data.
	 */
	public static function jsonDecode(string $json, bool $associative = false, bool $should_log = true)
	{
		// Come on...
		if (empty($json) || !is_string($json)) {
			return [];
		}

		$return_value = @json_decode($json, $associative);

		// Use this instead of json_last_error_msg() so that we can translate
		// the error messages for the admin.
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				$json_error = false;
				break;

			case JSON_ERROR_DEPTH:
				$json_error = 'JSON_ERROR_DEPTH';
				break;

			case JSON_ERROR_STATE_MISMATCH:
				$json_error = 'JSON_ERROR_STATE_MISMATCH';
				break;

			case JSON_ERROR_CTRL_CHAR:
				$json_error = 'JSON_ERROR_CTRL_CHAR';
				break;

			case JSON_ERROR_SYNTAX:
				$json_error = 'JSON_ERROR_SYNTAX';
				break;

			case JSON_ERROR_UTF8:
				$json_error = 'JSON_ERROR_UTF8';
				break;

			default:
				$json_error = 'unknown';
				break;
		}

		// Something went wrong!
		if (!empty($json_error) && $should_log) {
			// Being a wrapper means we lost our smf_error_handler() privileges :(
			$json_debug = debug_backtrace();
			$json_debug = $json_debug[0];

			Lang::load('Errors');

			if (!empty($json_debug)) {
				ErrorHandler::log(Lang::$txt['json_' . $json_error], 'critical', $json_debug['file'], $json_debug['line']);
			} else {
				ErrorHandler::log(Lang::$txt['json_' . $json_error], 'critical');
			}

			// Everyone expects an array.
			return [];
		}

		return $return_value;
	}

	/**
	 * Wrapper function for json_encode().
	 *
	 * This method exists merely to complement Utils::jsonDecode() for the sake
	 * of completeness. Calling this method is functionally identical to calling
	 * json_encode() directly.
	 *
	 * @param mixed $value The value to encode.
	 * @param int $flags Bitmask of flags for json_encode(). Default: 0.
	 * @param int $depth Maximum depth. Default: 512.
	 * @return mixed The decoded data.
	 */
	public static function jsonEncode($value, int $flags = 0, int $depth = 512)
	{
		return json_encode($value, $flags, $depth);
	}

	/**
	 * Safe serialize() replacement.
	 *
	 * - Recursive.
	 * - Outputs a strict subset of PHP's native serialized representation.
	 * - Does not serialize objects.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function safeSerialize(mixed $value): string
	{
		// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
		if (function_exists('mb_internal_encoding')
			&& (((int) ini_get('mbstring.func_overload')) & 2)) {
			$mb_int_enc = mb_internal_encoding();
			mb_internal_encoding('ASCII');
		}

		switch (gettype($value)) {
			case 'NULL':
				$out = 'N;';
				break;

			case 'boolean':
				$out = 'b:' . (int) $value . ';';
				break;

			case 'integer':
				$out = 'i:' . $value . ';';
				break;

			case 'double':
				$out = 'd:' . str_replace(',', '.', $value) . ';';
				break;

			case 'string':
				$out = 's:' . strlen($value) . ':"' . $value . '";';
				break;

			case 'array':
				// Check for nested objects or resources.
				$contains_invalid = false;

				array_walk_recursive(
					$value,
					function ($v) use (&$contains_invalid) {
						if (is_object($v) || is_resource($v)) {
							$contains_invalid = true;
						}
					},
				);

				if ($contains_invalid) {
					$out = false;
				} else {
					$out = '';

					foreach ($value as $k => $v) {
						$out .= self::safeSerialize($k) . self::safeSerialize($v);
					}

					$out = 'a:' . count($value) . ':{' . $out . '}';
				}
				break;

			default:
				$out = false;
				break;
		}

		if (isset($mb_int_enc)) {
			mb_internal_encoding($mb_int_enc);
		}

		return $out;
	}

	/**
	 * Safe unserialize() replacement
	 *
	 * - Accepts a strict subset of PHP's native serialized representation.
	 * - Does not unserialize objects.
	 *
	 * @param string $str
	 * @return mixed
	 */
	public static function safeUnserialize(string $str): mixed
	{
		// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
		if (function_exists('mb_internal_encoding')
			&& (((int) ini_get('mbstring.func_overload')) & 0x02)) {
			$mb_int_enc = mb_internal_encoding();
			mb_internal_encoding('ASCII');
		}

		// Input is not a string.
		if (empty($str) || !is_string($str)) {
			$out = false;
		}
		// The substrings 'O:' and 'C:' are used to serialize objects.
		// If they are not present, then there are none in the serialized data.
		elseif (strpos($str, 'O:') === false && strpos($str, 'C:') === false) {
			$out = unserialize($str);
		}
		// It looks like there might be an object in the serialized data,
		// but we won't know for sure until we check more closely.
		else {
			$stack = [];
			$expected = [];

			/*
			 * states:
			 *   0 - initial state, expecting a single value or array
			 *   1 - terminal state
			 *   2 - in array, expecting end of array or a key
			 *   3 - in array, expecting value or another array
			 */
			$state = 0;

			while ($state != 1) {
				$type = $str[0] ?? '';

				if ($type == '}') {
					$str = substr($str, 1);
				} elseif ($type == 'N' && $str[1] == ';') {
					$value = null;
					$str = substr($str, 2);
				} elseif ($type == 'b' && preg_match('/^b:([01]);/', $str, $matches)) {
					$value = $matches[1] == '1' ? true : false;
					$str = substr($str, 4);
				} elseif ($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches)) {
					$value = (int) $matches[1];
					$str = $matches[2];
				} elseif ($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches)) {
					$value = (float) $matches[1];
					$str = $matches[3];
				} elseif ($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int) $matches[1], 2) == '";') {
					$value = substr($matches[2], 0, (int) $matches[1]);
					$str = substr($matches[2], (int) $matches[1] + 2);
				} elseif ($type == 'a' && preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches)) {
					$expectedLength = (int) $matches[1];
					$str = $matches[2];
				}
				// Object or unknown/malformed type.
				else {
					$out = false;
					break;
				}

				switch ($state) {
					// In array, expecting value or another array.
					case 3:
						if ($type == 'a') {
							$stack[] = &$list;
							$list[$key] = [];
							$list = &$list[$key];
							$expected[] = $expectedLength;
							$state = 2;
							break;
						}

						if ($type != '}') {
							$list[$key] = $value;
							$state = 2;
							break;
						}

						// Missing array value.
						$out = false;
						break 2;

					// In array, expecting end of array or a key.
					case 2:
						if ($type == '}') {
							// Array size is less than expected.
							if (count($list) < end($expected)) {
								return false;
							}

							unset($list);
							$list = &$stack[count($stack) - 1];
							array_pop($stack);

							// Go to terminal state if we're at the end of the root array.
							array_pop($expected);

							if (count($expected) == 0) {
								$state = 1;
							}

							break;
						}

						if ($type == 'i' || $type == 's') {
							// Array size exceeds expected length.
							if (count($list) >= end($expected)) {
								return false;
							}

							$key = $value;
							$state = 3;

							break;
						}

						// Illegal array index type.
						$out = false;
						break 2;

					// Expecting array or value.
					case 0:
						if ($type == 'a') {
							$data = [];
							$list = &$data;
							$expected[] = $expectedLength;
							$state = 2;
							break;
						}

						if ($type != '}') {
							$data = $value;
							$state = 1;
							break;
						}

						// Not in array.
						$out = false;
						break 2;
				}
			}

			// If there's no trailing data in input, we're good to go.
			$out = !empty($str) ? false : $data;
		}

		if (isset($mb_int_enc)) {
			mb_internal_encoding($mb_int_enc);
		}

		return $out;
	}

	/**
	 * Wrapper for random_int() that sets default values.
	 *
	 * Only exists for backward compatibility purposes.
	 *
	 * @param int $min Minumum value. Default: 0.
	 * @param int $max Maximum value. Default: PHP_INT_MAX.
	 * @return int A random integer.
	 */
	public static function randomInt(int $min = 0, int $max = PHP_INT_MAX): int
	{
		return random_int($min, $max);
	}

	/**
	 * Wrapper for random_bytes() that sets a default length.
	 *
	 * Only exists for backward compatibility purposes.
	 *
	 * @param int $length Number of bytes to return. Default: 64.
	 * @return string A string of random bytes.
	 */
	public static function randomBytes(int $length = 64): string
	{
		// Make sure length is valid
		$length = max(1, $length);

		return random_bytes($length);
	}

	/**
	 * Attempts to determine the MIME type of some data or a file.
	 *
	 * @param string $data The data to check, or the path or URL of a file to check.
	 * @param string $is_path If true, $data is a path or URL to a file.
	 * @return string|bool A MIME type, or false if we cannot determine it.
	 */
	public static function getMimeType(string $data, bool $is_path = false): string|bool
	{
		$finfo_loaded = extension_loaded('fileinfo');
		$exif_loaded = extension_loaded('exif') && function_exists('image_type_to_mime_type');

		// Oh well. We tried.
		if (!$finfo_loaded && !$exif_loaded) {
			return false;
		}

		// Start with the 'empty' MIME type.
		$mime_type = 'application/x-empty';

		if ($finfo_loaded) {
			// Just some nice, simple data to analyze.
			if (empty($is_path)) {
				$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);
			}
			// A file, or maybe a URL?
			else {
				$path = $data;

				// Local file.
				if (file_exists($path)) {
					$mime_type = mime_content_type($path);
				}
				// URL.
				elseif ($data = WebFetch\WebFetchApi::fetch($path)) {
					$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);

					// SVG sometimes needs a little extra help.
					if ($mime_type === 'text/html' && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
						// Detection sometimes fails if the <svg> element isn't first.
						$data = preg_replace('/^([^<]|<(?!svg\b))*/i', '', $data);

						if (finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data) == 'image/svg+xml') {
							$mime_type = 'image/svg+xml';
						}
					}
				}
			}
		}
		// Workaround using Exif requires a local file.
		else {
			// If $data is a URL to fetch, do so.
			if (!empty($is_path) && !file_exists($data)) {
				$data = WebFetch\WebFetchApi::fetch($data);

				if ($data === false) {
					return false;
				}

				$is_path = false;
			}

			// If we don't have a local file, create one and use it.
			if (empty($is_path)) {
				$temp_file = tempnam(Config::$cachedir, md5($data));
				file_put_contents($temp_file, $data);
				$is_path = true;
				$data = $temp_file;
			}

			$imagetype = @exif_imagetype($data);

			if (isset($temp_file)) {
				unlink($temp_file);
			}

			// Unfortunately, this workaround only works for image files.
			if ($imagetype !== false) {
				$mime_type = image_type_to_mime_type($imagetype);
			}
		}

		return $mime_type;
	}

	/**
	 * Checks whether a file or data has the expected MIME type.
	 *
	 * @param string $data The data to check, or the path or URL of a file to check.
	 * @param string $type_pattern A regex pattern to match the acceptable MIME types.
	 * @param bool $is_path If true, $data is a path or URL to a file.
	 * @return int 1 if the detected MIME type matches the pattern, 0 if it doesn't, or 2 if we can't check.
	 */
	public static function checkMimeType(string $data, string $type_pattern, bool $is_path = false): int
	{
		// Get the MIME type.
		$mime_type = self::getMimeType($data, $is_path);

		// Couldn't determine it.
		if ($mime_type === false) {
			return 2;
		}

		// Check whether the MIME type matches expectations.
		return (int) @preg_match('~' . $type_pattern . '~', $mime_type);
	}

	/**
	 * Tries different modes to make files or directories writable.
	 *
	 * Wrapper function for PHP's chmod().
	 *
	 * @param string $path The full path of the file or directory.
	 * @return bool Whether the file/dir exists and is now writable.
	 */
	public static function makeWritable(string $path): bool
	{
		// No file? no checks!
		if (empty($path)) {
			return false;
		}

		// Already writable?
		if (is_writable($path)) {
			return true;
		}

		// Set different modes.
		$chmod_values = is_dir($path) ? [0750, 0755, 0775, 0777] : [0644, 0664, 0666];

		foreach ($chmod_values as $val) {
			// If it's writable now, we're done.
			if (is_writable($path)) {
				return true;
			}

			@chmod($path, $val);
		}

		// Didn't work.
		return false;
	}

	/**
	 * Emits a file for download. Mostly used for attachments.
	 *
	 * @param array|object $file Information about the file. Must be either an
	 *    array or an object that implements the \ArrayAccess interface.
	 * @param bool $show_thumb Whether to send the image's embedded thumbnail,
	 *    if it has one.
	 */
	public static function emitFile(\ArrayAccess|array $file, bool $show_thumb = false): void
	{
		// If headers were already sent, anything we send now will be corrupted.
		if (headers_sent()) {
			exit;
		}

		// Do we want to send an embedded thumbnail image?
		if ($show_thumb && $file instanceof Attachment && $file->embedded_thumb && function_exists('exif_thumbnail')) {
			$thumb = [
				'content' => exif_thumbnail($file->path, $width, $height, $type),
				'filename' => $file->filename ?? null,
				'mtime' => $file->mtime ?? null,
				'disposition' => $file->disposition ?? 'attachment',
			];
			$thumb['size'] = strlen($thumb['content']);
			$thumb['width'] = $width;
			$thumb['height'] = $height;
			$thumb['etag'] = sha1($thumb['content']);
			$thumb['mime_type'] = image_type_to_mime_type($type);
			$thumb['fileext'] = ltrim(image_type_to_extension($type), '.');

			$file = $thumb;
		}

		// We always need a file size.
		if (!isset($file['size'])) {
			if (isset($file['content'])) {
				$file['size'] = strlen($file['content']);
			} elseif (isset($file['path']) && file_exists($file['path'])) {
				$file['size'] = filesize($file['path']);
			} else {
				exit;
			}
		}

		// The file needs some sort of name.
		if (!isset($file['filename'])) {
			$file['filename'] = hash_hmac('md5', var_export($file, true), Config::$image_proxy_secret) . '.' . ltrim($file['fileext'] ?? 'dat', '.');
		}

		// Convert the filename to UTF-8, cuz most browsers dig that.
		$file['filename'] = !self::$context['utf8'] ? mb_convert_encoding($file['filename'], 'UTF-8', self::$context['character_set']) : $file['filename'];

		// Also provide a plain ASCII name for the sake of old browsers.
		$file['asciiname'] = preg_replace('/[\x{80}-\x{10FFFF}]+/u', '?', Utils::entityDecode($file['filename'], true));

		// Replace ASCII names like ??????.jpg with something more unique.
		if (strspn($file['asciiname'], '?') === strpos($file['asciiname'], '.')) {
			$file['asciiname'] = md5($file['filename']) . substr($file['asciiname'], strpos($file['asciiname'], '.'));
		}

		// Clear any output that was made before now.
		header_remove();

		while (@ob_get_level() > 0) {
			@ob_end_clean();
		}

		// Start a new output buffer.
		$output_already_compressed = @ini_get('zlib.output_compression') > 0 || @ini_get('output_handler') == 'ob_gzhandler';
		$ob_handler = !$output_already_compressed && !empty(Config::$modSettings['enableCompressedOutput']) ? 'ob_gzhandler' : null;
		ob_start($ob_handler);

		// Send the attachment headers.
		header('Pragma: ');
		header('Expires: ' . gmdate('D, d M Y H:i:s', ($file['expires'] ?? time() + 525600 * 60)) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', ($file['mtime'] ?? time())) . ' GMT');
		header('Accept-Ranges: bytes');
		header('Connection: close');
		header('Content-Type: ' . ($file['content_type'] ?? ($file['mime_type'] ?? 'application/octet-stream')));
		header('Content-Disposition: ' . ($file['disposition'] ?? 'attachment') . '; filename*=UTF-8\'\'' . rawurlencode($file['filename']) . '; filename="' . $file['asciiname'] . '"');

		if (isset($file['etag'])) {
			header('ETag: "' . $file['etag'] . '"');
		}

		// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
		if (isset($file['mime_type'], $file['fileext']) && strpos($file['mime_type'], 'image/') !== 0 && in_array($file['fileext'], ['gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff'])) {
			header('Cache-Control: no-cache');
		} else {
			header('Cache-Control: max-age=' . (525600 * 60) . ', private');
		}

		// Multipart and resuming support
		if (isset($_SERVER['HTTP_RANGE'])) {
			list($a, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			list($range) = explode(',', $range, 2);
			list($range, $range_end) = explode('-', $range);
			$range = intval($range);
			$range_end = !$range_end ? $file['size'] - 1 : intval($range_end);
			$length = $range_end - $range + 1;

			self::sendHttpStatus(206);
			header('Content-Length: ' . $length);
			header('Content-Range: bytes ' . $range . '-' . $range_end . '/' . $file['size']);
		} else {
			header('Content-Length: ' . $file['size']);
		}

		// Allow customizations to hook in here before we send anything to
		// modify any headers needed or to change the process of how we output.
		IntegrationHook::call('integrate_download_headers');

		// Try to buy some time...
		@set_time_limit(600);

		// For multipart/resumable downloads, send the requested chunk(s) of the file
		if (isset($_SERVER['HTTP_RANGE'])) {
			// 40 kilobytes is a good-ish amount
			$chunksize = 40 * 1024;
			$bytes_sent = 0;

			if (isset($file['content'])) {
				$offset = $range;

				while ($offset < $file['size'] && !connection_aborted() && $bytes_sent < $length) {
					$buffer = substr($file['content'], $offset, $chunksize);
					echo $buffer;
					flush();
					$bytes_sent += strlen($buffer);
					$offset += strlen($buffer);
				}
			} else {
				$fp = fopen($file['path'], 'rb');

				fseek($fp, $range);

				while (!feof($fp) && !connection_aborted() && $bytes_sent < $length) {
					$buffer = fread($fp, $chunksize);
					echo $buffer;
					flush();
					$bytes_sent += strlen($buffer);
				}
				fclose($fp);
			}
		} else {
			// Since we don't do output compression for files this large...
			if (!is_null($ob_handler) && $file['size'] > 4194304) {
				header_remove('Content-Encoding');

				while (@ob_get_level() > 0) {
					@ob_end_clean();
				}
			}

			// We were given the file content directly.
			if (isset($file['content'])) {
				echo $file['content'];
			} else {
				$fp = fopen($file['path'], 'rb');
				fpassthru($fp);
				fclose($fp);
			}
		}

		die();
	}

	/**
	 * Sends an appropriate HTTP status header based on a given status code.
	 *
	 * @param int $code The status code.
	 * @param string $status The status message. Set automatically if empty.
	 */
	public static function sendHttpStatus(int $code, string $status = ''): void
	{
		// This will fail anyways if headers have been sent.
		if (headers_sent()) {
			return;
		}

		$statuses = [
			204 => 'No Content',
			206 => 'Partial Content',
			304 => 'Not Modified',
			400 => 'Bad Request',
			403 => 'Forbidden',
			404 => 'Not Found',
			410 => 'Gone',
			500 => 'Internal Server Error',
			503 => 'Service Unavailable',
		];

		$protocol = !empty($_SERVER['SERVER_PROTOCOL']) && preg_match('~^\s*(HTTP/[12]\.\d)\s*$~i', $_SERVER['SERVER_PROTOCOL'], $matches) ? $matches[1] : 'HTTP/1.0';

		// Typically during these requests, we have cleaned the response (ob_*clean), ensure these headers exist.
		Security::frameOptionsHeader();
		Security::corsPolicyHeader();

		if (!isset($statuses[$code])) {
			header($protocol . ' 500 Internal Server Error');
		} else {
			header($protocol . ' ' . $code . ' ' . (!empty($status) ? $status : $statuses[$code]));
		}
	}

	/**
	 * Outputs a response.
	 * It assumes the data is already a string.
	 *
	 * @param string $data The data to print
	 * @param string $type The content type. Defaults to JSON.
	 */
	public static function serverResponse($data = '', $type = 'Content-Type: application/json')
	{
		// Defensive programming anyone?
		if (empty($data)) {
			return false;
		}

		// Don't need extra stuff...
		Config::$db_show_debug = false;

		// Kill anything else.
		ob_end_clean();

		if (!empty(Config::$modSettings['enableCompressedOutput'])) {
			@ob_start('ob_gzhandler');
		} else {
			ob_start();
		}

		// Set the header.
		header($type);

		// Echo!
		echo $data;

		// Done.
		self::obExit(false);
	}

	/**
	 * Make sure the browser doesn't come back and repost the form data.
	 * Should be used whenever anything is posted.
	 *
	 * @param string $setLocation The URL to redirect them to
	 * @param bool $refresh Whether to use a meta refresh instead
	 * @param bool $permanent Whether to send a 301 Moved Permanently instead of a 302 Moved Temporarily
	 */
	public static function redirectexit(string $setLocation = '', bool $refresh = false, bool $permanent = false): void
	{
		// In case we have mail to send, better do that - as obExit doesn't always quite make it...
		// @todo this relies on 'flush_mail' being only set in Mail::addToQueue itself... :\
		if (!empty(Utils::$context['flush_mail'])) {
			Mail::addToQueue(true);
		}

		$add = preg_match('~^(ftp|http)s?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

		if ($add) {
			$setLocation = Config::$scripturl . ($setLocation != '' ? '?' . $setLocation : '');
		}

		// Put the session ID in.
		if (defined('SID') && SID != '') {
			$setLocation = preg_replace('/^' . preg_quote(Config::$scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\??/', Config::$scripturl . '?' . SID . ';', $setLocation);
		}
		// Keep that debug in their for template debugging!
		elseif (isset($_GET['debug'])) {
			$setLocation = preg_replace('/^' . preg_quote(Config::$scripturl, '/') . '\??/', Config::$scripturl . '?debug;', $setLocation);
		}

		if (
			!empty(Config::$modSettings['queryless_urls'])
			&& (
				empty(Utils::$context['server']['is_cgi'])
				|| ini_get('cgi.fix_pathinfo') == 1
				|| @get_cfg_var('cgi.fix_pathinfo') == 1
			)
			&& (
				!empty(Utils::$context['server']['is_apache'])
				|| !empty(Utils::$context['server']['is_lighttpd'])
				|| !empty(Utils::$context['server']['is_litespeed'])
			)
		) {
			if (defined('SID') && SID != '') {
				$setLocation = preg_replace_callback(
					'~^' . preg_quote(Config::$scripturl, '~') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~',
					function ($m) {
						return Config::$scripturl . '/' . strtr("{$m[1]}", '&;=', '//,') . '.html?' . SID . (isset($m[2]) ? "{$m[2]}" : '');
					},
					$setLocation,
				);
			} else {
				$setLocation = preg_replace_callback(
					'~^' . preg_quote(Config::$scripturl, '~') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~',
					function ($m) {
						return Config::$scripturl . '/' . strtr("{$m[1]}", '&;=', '//,') . '.html' . (isset($m[2]) ? "{$m[2]}" : '');
					},
					$setLocation,
				);
			}
		}

		// Maybe integrations want to change where we are heading?
		IntegrationHook::call('integrate_redirect', [&$setLocation, &$refresh, &$permanent]);

		// Set the header.
		header('location: ' . str_replace(' ', '%20', $setLocation), true, $permanent ? 301 : 302);

		// Debugging.
		if (isset(Config::$db_show_debug) && Config::$db_show_debug === true) {
			$_SESSION['debug_redirect'] = Db::$cache;
		}

		self::obExit(false);
	}

	/**
	 * Ends execution.  Takes care of template loading and remembering the previous URL.
	 *
	 * @param bool $header Whether to do the header
	 * @param bool $do_footer Whether to do the footer
	 * @param bool $from_index Whether we're coming from the board index
	 * @param bool $from_fatal_error Whether we're coming from a fatal error
	 */
	public static function obExit(?bool $header = null, ?bool $do_footer = null, bool $from_index = false, bool $from_fatal_error = false): void
	{
		static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

		// Attempt to prevent a recursive loop.
		++$level;

		if ($level > 1 && !$from_fatal_error && !$has_fatal_error) {
			exit;
		}

		if ($from_fatal_error) {
			$has_fatal_error = true;
		}

		// Clear out the stat cache.
		Logging::trackStats();

		// If we have mail to send, send it.
		// @todo this relies on 'flush_mail' being only set in Mail::addToQueue itself... :\
		if (!empty(Utils::$context['flush_mail'])) {
			Mail::addToQueue(true);
		}

		$do_header = $header === null ? !$header_done : $header;

		if ($do_footer === null) {
			$do_footer = $do_header;
		}

		// Has the template/header been done yet?
		if ($do_header) {
			// Was the page title set last minute? Also update the HTML safe one.
			if (!empty(Utils::$context['page_title']) && empty(Utils::$context['page_title_html_safe'])) {
				Utils::$context['page_title_html_safe'] = Utils::htmlspecialchars(html_entity_decode(Utils::$context['page_title'])) . (!empty(Utils::$context['current_page']) ? ' - ' . Lang::$txt['page'] . ' ' . (Utils::$context['current_page'] + 1) : '');
			}

			// Start up the session URL fixer.
			ob_start('SMF\\QueryString::ob_sessrewrite');

			if (!empty(Theme::$current->settings['output_buffers']) && is_string(Theme::$current->settings['output_buffers'])) {
				$buffers = explode(',', Theme::$current->settings['output_buffers']);
			} elseif (!empty(Theme::$current->settings['output_buffers'])) {
				$buffers = Theme::$current->settings['output_buffers'];
			} else {
				$buffers = [];
			}

			if (isset(Config::$modSettings['integrate_buffer'])) {
				$buffers = array_merge(explode(',', Config::$modSettings['integrate_buffer']), $buffers);
			}

			if (!empty($buffers)) {
				foreach ($buffers as $function) {
					$call = Utils::getCallable($function);

					// Is it valid?
					if (!empty($call)) {
						ob_start($call);
					}
				}
			}

			// Display the screen in the logical order.
			Theme::template_header();
			$header_done = true;
		}

		if ($do_footer) {
			Theme::loadSubTemplate(Utils::$context['sub_template'] ?? 'main');

			// Anything special to put out?
			if (!empty(Utils::$context['insert_after_template']) && !isset($_REQUEST['xml'])) {
				echo Utils::$context['insert_after_template'];
			}

			// Just so we don't get caught in an endless loop of errors from the footer...
			if (!$footer_done) {
				$footer_done = true;
				Theme::template_footer();

				// (since this is just debugging... it's okay that it's after </html>.)
				if (!isset($_REQUEST['xml'])) {
					Logging::displayDebug();
				}
			}
		}

		// Remember this URL in case someone doesn't like sending HTTP_REFERER.
		if (!QueryString::isFilteredRequest(Forum::$unlogged_actions, 'action')) {
			$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];
		}

		// For session check verification.... don't switch browsers...
		$_SESSION['USER_AGENT'] = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];

		// Hand off the output to the portal, etc. we're integrated with.
		IntegrationHook::call('integrate_exit', [$do_footer]);

		// Don't exit if we're coming from index.php; that will pass through normally.
		if (!$from_index) {
			exit;
		}
	}

	/**
	 * Parses $input to find some sort of callable.
	 *
	 * If a method is found, it looks for a "#" which indicates SMF should
	 * create a new instance of the given class.
	 *
	 * ADD MORE HERE.
	 *
	 * @param mixed $input Input to parse to find a callable.
	 * @return string|array|bool Either a callable, or false on failure.
	 */
	public static function getCallable(mixed $input, ?bool $ignore_errors = null): mixed
	{
		$ignore_errors = $ignore_errors ?? !empty(Utils::$context['ignore_hook_errors']);

		// Really?
		if (empty($input)) {
			return false;
		}

		// An array? should be a "callable" array IE array(object/class, valid_callable).
		// A closure? should be a callable one.
		if (is_array($input) || $input instanceof \Closure) {
			return is_callable($input) ? $input : false;
		}

		// No full objects, sorry! pass a method or a property instead!
		if (is_object($input)) {
			return false;
		}

		// Stay vitaminized my friends...
		$input = Utils::htmlspecialchars(Utils::htmlTrim($input));

		// Is there a file to load?
		$input = self::loadFile($input);

		// Loaded file failed
		if (empty($input)) {
			return false;
		}

		// Found a method.
		if (strpos($input, '::') !== false) {
			list($class, $method) = explode('::', $input);

			// Check if a new object will be created.
			if (strpos($method, '#') !== false) {
				if (!isset(Utils::$context['instances'])) {
					Utils::$context['instances'] = [];
				}

				// Need to remove the # thing.
				$method = str_replace('#', '', $method);

				// Don't need to create a new instance for every method.
				if (empty(Utils::$context['instances'][$class]) || !(Utils::$context['instances'][$class] instanceof $class)) {
					Utils::$context['instances'][$class] = new $class();

					// Add another one to the list.
					if (Config::$db_show_debug === true) {
						if (!isset(Utils::$context['debug']['instances'])) {
							Utils::$context['debug']['instances'] = [];
						}

						Utils::$context['debug']['instances'][$class] = $class;
					}
				}

				$callable = [Utils::$context['instances'][$class], $method];
			}
			// Right then. This is a call to a static method.
			else {
				$callable = [$class, $method];
			}
		}
		// Nope! just a plain regular function.
		else {
			$callable = $input;
		}

		// Right, we got what we need, time to do some checks.
		if (!is_callable($callable, false, $callable_name) && $ignore_errors) {
			// We can't call this helper, but we want to silently ignore this.
			if ($ignore_errors) {
				return false;
			}

			// Gotta tell everybody.
			Lang::load('Errors');
			ErrorHandler::log(sprintf(Lang::$txt['sub_action_fail'], $callable_name), 'general');

			return false;
		}

		return $callable;
	}

	/**
	 * Backward compatibility method.
	 *
	 * Basically just a wrapper for Utils::getCallable(), except that if this
	 * method's $return parameter is false, the callable will be called inside
	 * this method..
	 *
	 * @param mixed $input Input to parse to find a callable.
	 * @param bool $return If true, just return the callable instead of
	 *    calling it. Default: false.
	 * @return mixed If $return is false, nothing. Otherwise, either a callable
	 *    or false if no callable was found.
	 */
	public static function call_helper(mixed $input, bool $return = false): mixed
	{
		$callable = self::getCallable($input);

		// Just return the callable if that's all we were asked to do.
		if ($return) {
			return $callable;
		}

		call_user_func($callable);
	}

	/**
	 * Decode HTML entities to their UTF-8 equivalent character, except for
	 * HTML special characters, which are always converted to numeric entities.
	 *
	 * Callback function for preg_replace_callback.
	 * Uses capture group 2 in the supplied array.
	 * Does basic scan to ensure characters are inside a valid range.
	 *
	 * Unused by SMF. Only retained for backward compatibility purposes.
	 *
	 * @deprecated since 3.0
	 *
	 * @param array $matches An array of matches (relevant info should be the 3rd item)
	 * @return string A fixed string
	 */
	public static function replaceEntities__callback($matches)
	{
		return strtr(
			htmlspecialchars(Utils::entityDecode($matches[1], true), ENT_QUOTES),
			[
				'&amp;' => '&#038;',
				'&quot;' => '&#034;',
				'&lt;' => '&#060;',
				'&gt;' => '&#062;',
			],
		);
	}

	/**
	 * Converts HTML entities to UTF-8 equivalents.
	 *
	 * Callback function for preg_replace_callback.
	 * Uses capture group 1 in the supplied array.
	 * Does basic checks to keep characters inside a viewable range.
	 *
	 * Unused by SMF. Only retained for backward compatibility purposes.
	 *
	 * @deprecated since 3.0
	 *
	 * @param array $matches An array of matches (relevant info should be the 2nd item in the array)
	 * @return string The fixed string
	 */
	public static function fixchar__callback($matches)
	{
		return Utils::entityDecode($matches[0], true);
	}

	/**
	 * Strips out invalid HTML entities and fixes double-encoded entities.
	 *
	 * Callback function for preg_replace_callback.
	 *
	 * Unused by SMF. Only retained for backward compatibility purposes.
	 *
	 * @deprecated since 3.0
	 *
	 * @param array $matches An array of matches (relevant info should be the 3rd
	 *    item in the array)
	 * @return string The fixed string
	 */
	public static function entity_fix__callback($matches)
	{
		return Utils::sanitizeEntities(Utils::entityFix($matches[1]));
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Converts four-byte Unicode characters to entities, but only if the
	 * database can't handle four-byte characters natively.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string The string, with four-byte chars encoded as entities.
	 */
	final protected static function fixUtf8mb4(string $string): string
	{
		if (class_exists('SMF\\Db\\DatabaseApi', false) && isset(Db::$db) && Db::$db->mb4) {
			return $string;
		}

		return mb_encode_numericentity($string, [0x010000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');
	}

	/**
	 * Helper method for Utils::call_helper.
	 *
	 * Receives a string and tries to figure it out if it contains info to load
	 * a file.
	 *
	 * Checks for a '|' symbol and tries to load a file with the info given.
	 *
	 * The string should be format as follows: 'path/to/file.php|whatever'.
	 *
	 * You can use the following wildcards in the path:
	 *  - $boarddir
	 *  - $sourcedir
	 *  - $themedir (only works if SMF\Theme has already been initialized)
	 *
	 * @param string $string The string containing a valid format.
	 * @return string|bool The given string with the pipe and file info removed
	 *    or false if the file couldn't be loaded.
	 */
	final protected static function loadFile(string $string): string|bool
	{
		if (empty($string)) {
			return false;
		}

		if (strpos($string, '|') !== false) {
			list($file, $string) = explode('|', $string);

			$path = strtr($file, [
				'$boarddir' => Config::$boarddir,
				'$sourcedir' => Config::$sourcedir,
			]);

			if (strpos($path, '$themedir') !== false && class_exists('SMF\\Theme', false) && !empty(Theme::$current->settings['theme_dir'])) {
				$path = strtr($path, [
					'$themedir' => Theme::$current->settings['theme_dir'],
				]);
			}

			// Load the file if it can be loaded.
			if (is_file($path)) {
				require_once $path;
			}
			// No? Try a fallback to Config::$sourcedir.
			else {
				$path = Config::$sourcedir . '/' . $file;

				if (is_file($path)) {
					require_once $path;
				}
				// Sorry, can't do much for you at this point.
				elseif (empty(Utils::$context['uninstalling'])) {
					Lang::load('Errors');
					ErrorHandler::log(sprintf(Lang::$txt['hook_fail_loading_file'], $path), 'general');

					// File couldn't be loaded.
					return false;
				}
			}
		}

		return $string;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Utils::exportStatic')) {
	Utils::exportStatic();
}

?>