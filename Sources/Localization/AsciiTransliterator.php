<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Localization;

use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Sapi;
use SMF\Unicode\Utf8String;
use SMF\Utils;

/**
 * Transliterates Unicode to ASCII.
 */
class AsciiTransliterator
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var string
	 *
	 * The version of the ICU library that was used to build the data files.
	 */
	public const BUILT_FROM_ICU_VERSION = '78.2';

	/**
	 * @var string
	 *
	 * Default set of transliterator ID rules for \Transliterator::create().
	 *
	 * @see https://unicode-org.github.io/icu/userguide/transforms/general
	 */
	public const DEFAULT_ID =
		// Generic transliterator that does the bulk of the work.
		'Any-Latin;' .

		// Various language-specific transliterators to catch some stuff that
		// the generic one doesn't.
		'Han-Latin;' .
		'[\p{Block=Arabic}] Arabic-Latin;' .
		'[\p{Block=Devanagari}] Devanagari-Latin;' .
		'[\p{Block=Greek And Coptic}|\p{Block=Latin 1 Supplement}] Greek-Latin;' .
		'[\p{Block=Ethiopic}|\p{Block=Ethiopic Supplement}|\p{Block=Ethiopic Extended}] Ethiopic-Latin/ES3842;' .
		'[\p{Block=Ethiopic Supplement}] Ethiopic-Latin/SERA;' .
		'[\p{Block=Sinhala}] si-si_Latn;' .
		'[\p{Block=Thaana}] Maldivian-Latin/BGN;' .
		'[\u30FC\uFF70\uFF9E\uFF9F] Katakana-Latin;' .
		'[\u2135-\u2138] Hebrew-Latin;' .
		'[\u04B2-\u04B3] Uzbek-Latin/BGN;' .
		'[\u06B0] Pashto-Latin/BGN;' .
		'[\u0589] Armenian-Latin/BGN;' .

		// Normalize to NFKC in certain code blocks and then re-run Any-Latin
		// in order to catch some more stuff.
		'[' .
			'[\u00aa\u00b2\u00b3\u00b9\u00ba\u202f\u2032-\u2037\u2057]' .
			'|\p{Block=Arabic}' .
			'|\p{Block=Arabic Presentation Forms-A}' .
			'|\p{Block=Arabic Presentation Forms-B}' .
			'|\p{Block=Ethiopic}' .
			'|\p{Block=Ethiopic Supplement}' .
			'|\p{Block=Ethiopic Extended}' .
			'|\p{Block=Spacing Modifier Letters}' .
			'|\p{Block=Superscripts and Subscripts}' .
			'|\p{Block=Currency Symbols}' .
			'|\p{Block=Letterlike Symbols}' .
			'|\p{Block=Number Forms}' .
			'|\p{Block=Latin Extended-C}' .
			'|\p{Block=Latin Extended-D}' .
			'|\p{Block=Enclosed Alphanumerics}' .
			'|\p{Block=Enclosed CJK Letters and Months}' .
			'|\p{Block=CJK Compatibility}' .
		'] Any-NFKC;' .
		'Any-Latin;' .

		// Remove accents, etc.
		'[\p{M}] Any-Remove;' .

		// Finally, transliterate non-ASCII Latin characters to ASCII.
		'Latin-ASCII';

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var \Transliterator
	 *
	 * An instance of \Transliterator to be used by self::intl().
	 */
	private static \Transliterator $transliterator;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Transliterates Unicode to ASCII and performs additional cleanup.
	 *
	 * If the intl extension's \Transliterator class exists, calls self::intl()
	 * to perform the transliteration. Otherwise, calls self::manual() in order
	 * to use the transliteration data in ./data/AsciiTransliteration_*.php.
	 *
	 * Once the main transliteration is complete, performs extra steps to ensure
	 * that the returned string contains nothing but ASCII.
	 *
	 * @param string $string A UTF-8 string.
	 * @param string $substitute Substitute to use for characters that have no
	 *    ASCII approximation. Default: '[?]'
	 * @return string An ASCII string.
	 */
	public static function toAscii(string $string, string $substitute = '[?]'): string
	{
		$string = class_exists('\Transliterator') ? self::intl($string) : self::manual($string);

		// Remove invisible formatting characters.
		$string = Utils::sanitizeChars($string, 2, '');

		// Remove emoji and symbols.
		$string = preg_replace(
			[
				Utf8String::emojiRegex(),
				'/[\p{Sk}\p{So}]/u',
			],
			'',
			$string,
		);

		// Replace any remaining non-ASCII graphemes with $substitute.
		// Explanation: \X in the regex matches a grapheme cluster, which can
		// contain one base character and any number of combining marks. Then
		// in mb_ord($m[0]) we pass the entire grapheme cluster to mb_ord(),
		// but mb_ord() only ever returns the ord for the base character. If
		// the base character is not ASCII, we replace the grapheme with our
		// substitute. If the base character is ASCII, then we replace the
		// grapheme with the first byte of the grapheme (i.e. $m[0][0]), which
		// will be just the ASCII character itself without any combining marks.
		$string = preg_replace_callback(
			'/\X/u',
			fn($m) => mb_ord($m[0]) > 0x7f ? $substitute : $m[0][0],
			$string,
		);

		return $string;
	}

	/**
	 * Transliterates Unicode to ASCII (as much as possible) using the intl
	 * extension's Transliterator class.
	 *
	 * Note that the returned value is NOT guaranteed to be an ASCII string.
	 *
	 * Transliteration steps:
	 *
	 *  1. If applicable, a specific transliterator rule to convert the forum's
	 *     default language to Latin characters is applied to the string.
	 *     For example, if the forum's default language is Urdu, specific
	 *     transliterations for Urdu to Latin are applied before any other
	 *     transliterations. However, if the forum's default language is Dutch,
	 *     this step is skipped because Dutch already uses Latin characters.
	 *
	 *  2. The default set of transliterators (AsciiTransliterator::DEFAULT_ID)
	 *     is applied to the string in order to transliterate as many characters
	 *     as possible to Latin equivalents, and thence to the ASCII subset of
	 *     the Latin script.
	 *
	 * MOD AUTHORS: If you want to adjust how the transliteration is performed,
	 * use the integrate_ascii_transliterator_id hook to change the rules
	 * that are used to construct the transliterator.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string The transliterated string.
	 */
	public static function intl(string $string): string
	{
		if (!class_exists('\Transliterator')) {
			throw new \Exception('\Transliterator class does not exist');
		}

		if (!isset(self::$transliterator)) {
			// Maybe there is a specific transliterator for the default language?
			switch (substr(Lang::$default, 0, 2)) {
				// There's a specific transliterator for German to ASCII.
				case 'de':
					$locale_id = 'de-ASCII;' . self::DEFAULT_ID;
					break;

				// These languages are already covered by the default set.
				case 'ar':
				case 'el':
				case 'hi':
				case 'zh':
				case 'si':
				// These languages already use Latin script.
				// Note: if SMF adds new language packs in the future that use
				// Latin characters, they should be added to this list in order
				// to improve efficiency.
				case 'ca':
				case 'cs':
				case 'da':
				case 'en':
				case 'eo':
				case 'es':
				case 'fi':
				case 'fr':
				case 'gl':
				case 'hr':
				case 'hu':
				case 'id':
				case 'it':
				case 'lt':
				case 'ms':
				case 'nb':
				case 'nl':
				case 'pl':
				case 'pt':
				case 'ro':
				case 'sk':
				case 'sl':
				case 'sq':
				case 'sv':
				case 'tr':
					$locale_id = self::DEFAULT_ID;
					break;

				// For anything else, guess at a specific transliterator.
				// It might or might not work, but we'll handle that below.
				default:
					$locale_id = substr(Lang::$default, 0, 2) . '-Latin;' . self::DEFAULT_ID;
					break;
			}

			// Make the transliterator instance.
			foreach ([$locale_id, self::DEFAULT_ID, 'Any-Latin;Latin-ASCII'] as $id) {
				if (($temp = \Transliterator::create($id)) instanceof \Transliterator) {
					self::$transliterator = $temp;
					break;
				}
			}

			// Allow mods to adjust the transliterator identifier string.
			$id = self::$transliterator->id;

			IntegrationHook::call('integrate_ascii_transliterator_id', [&$id]);

			if ($id !== self::$transliterator->id) {
				self::$transliterator = ($temp = \Transliterator::create($id)) instanceof \Transliterator ? $temp : self::$transliterator;
			}
		}

		return self::$transliterator->transliterate($string);
	}

	/**
	 * Transliterates Unicode to ASCII (as much as possible) using saved data
	 * files.
	 *
	 * Note that the returned value is NOT guaranteed to be an ASCII string.
	 *
	 * Unlike self::intl(), this method acts on each character in isolation and
	 * is therefore unable to take context clues into consideration.
	 *
	 * MOD AUTHORS: If you want to adjust how the transliteration is performed,
	 * use the integrate_ascii_transliterator_chars hook to customize how the
	 * individual characters are transliterated.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string The transliterated string.
	 */
	public static function manual(string $string): string
	{
		$chars = mb_str_split($string);

		$new_chars = [];

		foreach ($chars as $char_num => $char) {
			$ord = mb_ord($char);

			if (file_exists(__DIR__ . '/data/AsciiTransliteration_' . \sprintf('%04d', $ord >> 8) . '.php')) {
				include_once Sapi::canonicalPath(__DIR__ . '/data/AsciiTransliteration_' . \sprintf('%04d', $ord >> 8) . '.php');

				$new_chars[$char_num] = $ascii_transliteration[$ord >> 8][$ord & 255] ?? $char;
			}
		}

		// Allow mods to adjust the changed characters.
		IntegrationHook::call('integrate_ascii_transliterator_chars', [$chars, &$new_chars]);

		return implode('', $new_chars);
	}
}
