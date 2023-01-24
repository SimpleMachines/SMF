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

namespace SMF;

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
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'entityDecode' => false,
			'entityFix' => false,
			'sanitizeEntities' => false,
			'sanitizeChars' => 'sanitize_chars',
			'normalizeSpaces' => 'normalize_spaces',
			'htmlspecialchars' => false,
			'htmlTrim' => false,
			'entityStrlen' => false,
			'entityStrpos' => false,
			'entitySubstr' => false,
			'truncate' => false,
			'normalize' => false,
			'convertCase' => false,
			'strtoupper' => false,
			'strtolower' => false,
			'casefold' => false,
			'strtotitle' => false,
			'ucfirst' => false,
			'ucwords' => false,
			'jsonDecode' => 'smf_json_decode',
			'jsonEncode' => false,
			'randomInt' => false,
			'randomBytes' => false,
		),
		'prop_names' => array(
			'context' => 'context',
			'smcFunc' => 'smcFunc',
		),
	);

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Regular expression to match named entities for HTML special characters
	 * and any numeric entities.
	 */
	const ENT_LIST = '&(?' . '>nbsp|quot|gt|lt|a(?' . '>pos|mp)|#(?' . '>\d+|x[0-9a-fA-F]+));';

	/**
	 * Regular expression to match all forms of the non-breaking space entity.
	 */
	const ENT_NBSP = '&(?' . '>nbsp|#(?' . '>x0*A0|0*160));';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * SMF's venerable $context variable, now available as Utils::$context.
	 */
	public static $context = array(
		// Assume UTF-8 until proven otherwise.
		'utf8' => true,
		'character_set' => 'UTF-8',
		// Define a list of icons used across multiple places.
		'stable_icons' => array(
			'xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp',
			'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved',
			'recycled', 'clip',
		),
		// Define an array for custom profile fields placements.
		'cust_profile_fields_placement' => array(
			'standard',
			'icons',
			'above_signature',
			'below_signature',
			'below_avatar',
			'above_member',
			'bottom_poster',
			'before_member',
			'after_member',
		),
		// Define an array for content-related <meta> elements (e.g. description,
		// keywords, Open Graph) for the HTML head.
		'meta_tags' => array(),
		// Define an array of allowed HTML tags.
		'allowed_html_tags' => array(
			'<img>',
			'<div>',
		),
		// These are the only valid image types for SMF attachments, by default
		// anyway. Note: The values are for mime types, not file extensions.
		'valid_image_types' => array(
			IMAGETYPE_GIF => 'gif',
			IMAGETYPE_JPEG => 'jpeg',
			IMAGETYPE_PNG => 'png',
			IMAGETYPE_PSD => 'psd',
			IMAGETYPE_BMP => 'bmp',
			IMAGETYPE_TIFF_II => 'tiff',
			IMAGETYPE_TIFF_MM => 'tiff',
			IMAGETYPE_IFF => 'iff'
		),
		// Define a list of allowed tags for descriptions.
		'description_allowed_tags' => array(
			'abbr', 'anchor', 'b', 'br', 'center', 'color', 'font', 'hr', 'i',
			'img', 'iurl', 'left', 'li', 'list', 'ltr', 'pre', 'right', 's',
			'sub', 'sup', 'table', 'td', 'tr', 'u', 'url',
		),
		// Define a list of deprecated BBC tags.
		// Even when enabled, they'll only work in old posts and not new ones.
		'legacy_bbc' => array(
			'acronym', 'bdo', 'black', 'blue', 'flash', 'ftp', 'glow',
			'green', 'move', 'red', 'shadow', 'tt', 'white',
		),
		// Define a list of BBC tags that require permissions to use.
		'restricted_bbc' => array(
			'html',
		),
		// Login Cookie times. Format: time => txt
		'login_cookie_times' => array(
			3153600 => 'always_logged_in',
			60 => 'one_hour',
			1440 => 'one_day',
			10080 => 'one_week',
			43200 => 'one_month',
		),
		'show_spellchecking' => false,
	);

	/**
	 * @var array
	 *
	 * Backward compatibility aliases of various utility functions.
	 */
	public static $smcFunc = array(
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
	);

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * (Re)initializes some $context values that need to be set dynamically.
	 */
	public static function load(): void
	{
		// Used to force browsers to download fresh CSS and JavaScript when necessary
		if (isset(Config::$modSettings['browser_cache']))
		{
			self::$context['browser_cache'] = '?' . preg_replace('~\W~', '', strtolower(SMF_FULL_VERSION)) . '_' . Config::$modSettings['browser_cache'];
		}

		// UTF-8?
		if (isset(Config::$modSettings['global_character_set']))
		{
			self::$context['character_set'] = Config::$modSettings['global_character_set'];
			self::$context['utf8'] = self::$context['character_set'] === 'UTF-8';
		}

		// This determines the server... not used in many places, except for login fixing.
		self::$context['server'] = array(
			'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
			'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
			'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
			'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
			'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
			'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
			'is_windows' => DIRECTORY_SEPARATOR === '\\',
			'is_mac' => PHP_OS === 'Darwin',
			'iso_case_folding' => ord(strtolower(chr(138))) === 154,
		);

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
	 * @param integer $flags Flags to pass to html_entity_decode.
	 * 		Default: ENT_QUOTES | ENT_HTML5.
	 * @param bool $nbsp_to_space If true, decode '&nbsp;' to space character.
	 * 		Default: false.
	 * @return string The string with the entities decoded.
	 */
	public static function entityDecode(string $string, bool $mb4 = false, int $flags = ENT_QUOTES | ENT_HTML5, bool $nbsp_to_space = false): string
	{
		// Don't waste time on empty strings.
		if (trim($string) === '')
			return $string;

		// In theory this is always UTF-8, but...
		if (empty(self::$context['character_set']))
		{
			$charset = is_callable('mb_detect_encoding') ? mb_detect_encoding($string) : 'UTF-8';
		}
		elseif (strpos(self::$context['character_set'], 'ISO-8859-') !== false && !in_array(self::$context['character_set'], array('ISO-8859-5', 'ISO-8859-15')))
		{
			$charset = 'ISO-8859-1';
		}
		else
		{
			$charset = self::$context['character_set'];
		}

		// Enables consistency with the behaviour of un_htmlspecialchars.
		if ($nbsp_to_space)
			$string = preg_replace('~' . self::ENT_NBSP . '~u', ' ', $string);

		// Do the deed.
		$string = html_entity_decode($string, $flags, $charset);

		// Remove any illegal character entities.
		$string = self::sanitizeEntities($string);

		// Finally, make sure we don't break the database.
		if (!$mb4)
			$string = self::fixUtf8mb4($string);

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
		if (strpos($string, '&#') === false)
			return $string;

		// Disallow entities for control characters, non-characters, etc.
		return preg_replace_callback(
			'~(&#(0*\d{1,7}|x0*[0-9a-fA-F]{1,6});)~',
			function($matches) use ($substitute)
			{
				$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

				if (
					// Control characters (except \t, \n, and \r).
					($num < 0x20 && $num !== 0x9 && $num !== 0xA && $num !== 0xD)
					|| ($num >= 0x74 && $num < 0xA0)

					// UTF-16 surrogate pairs.
					|| ($num >= 0xD800 && $num <= 0xDFFF)

					// Code points that are guaranteed never to be characters.
					|| ($num >= 0xFDD0 && $num <= 0xFDEF)
					|| (in_array($num % 0x10000, array(0xFFFE, 0xFFFF)))

					// Out of range.
					|| $num > 0x10FFFF
				)
				{
					return $substitute;
				}
				else
				{
					return '&#' . $num . ';';
				}
			},
			(string) $string
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
		if (isset($substitute))
		{
			$substitute = strval($substitute);
		}
		elseif (!empty(Utils::$context['utf8']))
		{
			// Raw UTF-8 bytes for U+FFFD.
			$substitute = "\xEF\xBF\xBD";
		}
		elseif (!empty(Utils::$context['character_set']) && is_callable('mb_decode_numericentity'))
		{
			// Get whatever the default replacement character is for this encoding.
			$substitute = mb_decode_numericentity('&#xFFFD;', array(0xFFFD,0xFFFD,0,0xFFFF), Utils::$context['character_set']);
		}
		else
			$substitute = '?';

		// Fix any invalid byte sequences.
		if (!empty(Utils::$context['character_set']))
		{
			// For UTF-8, this preg_match test is much faster than mb_check_encoding.
			$malformed = !empty(Utils::$context['utf8']) ? @preg_match('//u', $string) === false && preg_last_error() === PREG_BAD_UTF8_ERROR : (!is_callable('mb_check_encoding') || !mb_check_encoding($string, Utils::$context['character_set']));

			if ($malformed)
			{
				// mb_convert_encoding will replace invalid byte sequences with our substitute.
				if (is_callable('mb_convert_encoding'))
				{
					if (!is_callable('mb_ord'))
						require_once(Config::$sourcedir . '/Subs-Compat.php');

					$substitute_ord = $substitute === '' ? 'none' : mb_ord($substitute, Utils::$context['character_set']);

					$mb_substitute_character = mb_substitute_character();
					mb_substitute_character($substitute_ord);

					$string = mb_convert_encoding($string, Utils::$context['character_set'], Utils::$context['character_set']);

					mb_substitute_character($mb_substitute_character);
				}
				else
					return false;
			}
		}

		// Fix any weird vertical space characters.
		$string = Utils::normalizeSpaces($string, true);

		// Deal with unwanted control characters, invisible formatting characters, and other creepy-crawlies.
		if (!empty(Utils::$context['utf8']))
		{
			require_once(Config::$sourcedir . '/Subs-Charset.php');
			$string = utf8_sanitize_invisibles($string, $level, $substitute);
		}
		else
			$string = preg_replace('/[^\P{Cc}\t\r\n]/', $substitute, $string);

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
	public static function normalizeSpaces($string, $vspace = true, $hspace = false, $options = array())
	{
		$string = (string) $string;
		$vspace = !empty($vspace);
		$hspace = !empty($hspace);

		if (!$vspace && !$hspace)
			return $string;

		$options['no_breaks'] = !empty($options['no_breaks']);
		$options['collapse_hspace'] = !empty($options['collapse_hspace']);
		$options['replace_tabs'] = !empty($options['replace_tabs']);

		$patterns = array();
		$replacements = array();

		if ($vspace)
		{
			// \R is like \v, except it handles "\r\n" as a single unit.
			$patterns[] = '/\R/' . (Utils::$context['utf8'] ? 'u' : '');
			$replacements[] = $options['no_breaks'] ? ' ' : "\n";
		}

		if ($hspace)
		{
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
	 * Like standard trim(), except that it also trims &nbsp; entities.
	 *
	 * @param string $string The string.
	 * @return string The fixed string.
	 */
	public static function htmlTrim(string $string): string
	{
		return preg_replace('~^(?'.'>[\p{Z}\p{C}]|' . self::ENT_NBSP . ')+|(?'.'>[\p{Z}\p{C}]|' . self::ENT_NBSP . ')+$~u', '', self::sanitizeEntities($string));
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

		if (strlen($needle) === 1)
		{
			$result = array_search($needle, array_slice($haystack_arr, $offset));

			return is_int($result) ? $result + $offset : false;
		}
		else
		{
			$needle_arr = preg_split('~(' . self::ENT_LIST . '|\X)~u', self::sanitizeEntities($needle), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

			$needle_size = count($needle_arr);

			$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));

			while ((int) $result === $result)
			{
				$offset += $result;

				if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
					return $offset;

				$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
			}

			return false;
		}
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
	public static function entitySubstr(string $string, int $offset, int $length = null): string
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

		while (strlen($string) > $length)
		{
			$string = preg_replace('~(?:' . self::ENT_LIST . '|\X)$~u', '', $string);
		}

		return $string;
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
		require_once(Config::$sourcedir . '/Subs-Charset.php');

		$normalize_func = 'utf8_normalize_' . strtolower($form);

		if (!function_exists($normalize_func))
			return false;

		return $normalize_func($string);
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
		if (strpos($string, '&#') !== false)
		{
			$string = strtr(self::sanitizeEntities($string), array(
				'&#34;' => '&quot;',
				'&#38;' => '&amp;',
				'&#39;' => '&apos;',
				'&#60;' => '&lt;',
				'&#62;' => '&gt;',
				'&#160;' => '&nbsp;',
			));

			$string = mb_decode_numericentity($string, array(0, 0x10FFFF, 0, 0xFFFFFF), 'UTF-8');
		}

		// Use optimized function for compatibility casefolding.
		if ($form === 'kc_casefold')
		{
			$string = self::normalize($string, 'kc_casefold');
		}
		// Everything else.
		else
		{
			require_once(Config::$sourcedir . '/Subs-Charset.php');
			$string = self::normalize(utf8_convert_case($string, $case, $simple), $form);
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
		global $txt;

		// Come on...
		if (empty($json) || !is_string($json))
			return array();

		$return_value = @json_decode($json, $associative);

		// Use this instead of json_last_error_msg() so that we can translate
		// the error messages for the admin.
		switch (json_last_error())
		{
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
		if (!empty($json_error) && $should_log)
		{
			// Being a wrapper means we lost our smf_error_handler() privileges :(
			$json_debug = debug_backtrace();
			$json_debug = $json_debug[0];
			loadLanguage('Errors');

			if (!empty($json_debug))
			{
				log_error($txt['json_' . $json_error], 'critical', $json_debug['file'], $json_debug['line']);
			}
			else
			{
				log_error($txt['json_' . $json_error], 'critical');
			}

			// Everyone expects an array.
			return array();
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
	 * Wrapper for random_int() that transparently loads a compatibility library
	 * if necessary.
	 *
	 * @todo Compatibility library is no longer necessary since we don't support
	 * PHP 5 any more.
	 *
	 * @param int $min Minumum value. Default: 0.
	 * @param int $max Maximum value. Default: PHP_INT_MAX.
	 * @return int A random integer.
	 */
	public static function randomInt(int $min = 0, int $max = PHP_INT_MAX): int
	{
		// Oh, wouldn't it be great if I *was* crazy? Then the world would be okay.
		if (!is_callable('random_int'))
			require_once(Config::$sourcedir . '/random_compat/random.php');

		return random_int($min, $max);
	}

	/**
	 * Wrapper for random_bytes() that transparently loads a compatibility
	 * library if necessary.
	 *
	 * @todo Compatibility library is no longer necessary since we don't support
	 * PHP 5 any more.
	 *
	 * @param int $length Number of bytes to return. Default: 64.
	 * @return string A string of random bytes.
	 */
	public static function randomBytes(int $length = 64): string
	{
		if (!is_callable('random_bytes'))
			require_once(Config::$sourcedir . '/random_compat/random.php');

		// Make sure length is valid
		$length = max(1, $length);

		return random_bytes($length);
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
		if (class_exists('SMF\\Db\\DatabaseApi', false) && isset(Db\DatabaseApi::$db) && Db\DatabaseApi::$db->mb4)
		{
			return $string;
		}

		return mb_encode_numericentity($string, array(0x010000, 0x10FFFF, 0, 0xFFFFFF), 'UTF-8');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Utils::exportStatic'))
	Utils::exportStatic();

?>