<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Localization;

use SMF\IntegrationHook;
use SMF\Lang;

/**
 * Transliterates Unicode to ASCII.
 *
 * If the intl extension's \Transliterator class exists, that will be used
 * to perform the transliteration. Otherwise, the transliteration data in
 * ./data/AsciiTransliteration_*.php will be used.
 *
 * Note that the data in the AsciiTransliteration_*.php files does not produce
 * the same output as the \Transliterator class.
 */
class AsciiTransliterator
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Transliterates Unicode to ASCII.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string An ASCII string.
	 */
	public static function toAscii(string $string): string
	{
		return class_exists('\Transliterator') ? self::intl($string) : self::manual($string);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Transliterates Unicode to ASCII using the \Transliterator class.
	 *
	 * MOD AUTHORS: If you want to adjust how the transliteration is performed,
	 * use the integrate_ascii_transliterator_id hook to change the identifiers
	 * that are used to construct the transliterator.
	 *
	 * @see https://unicode-org.github.io/icu/userguide/transforms/general
	 *
	 * @param string $string A UTF-8 string.
	 * @return string An ASCII string.
	 */
	protected static function intl(string $string): string
	{
		/*
		 * First use the specific transliterator method for converting the
		 * forum's default language to Latin characters, if there is one.
		 * Then use the generic Any-Latin to convert any other characters.
		 * Finally, convert Latin to ASCII to get rid of accents and such.
		 */
		$transliterator_id = Lang::$default . '-Latin; Any-Latin; Latin-ASCII';

		// Allow mods to adjust the transliterator identifiers.
		IntegrationHook::call('integrate_ascii_transliterator_id', [&$transliterator_id]);

		$transliterator = \Transliterator::create($transliterator_id);

		if (!($transliterator instanceof \Transliterator)) {
			$transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
		}

		return $transliterator->transliterate($string);
	}

	/**
	 * Transliterates Unicode to ASCII manually.
	 *
	 * MOD AUTHORS: If you want to adjust how the transliteration is performed,
	 * use the integrate_ascii_transliterator_chars hook to customize how the
	 * individual characters are transliterated.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string An ASCII string.
	 */
	protected static function manual(string $string): string
	{
		$chars = mb_str_split($string);

		$new_chars = [];

		foreach ($chars as $char_num => $char) {
			$bytes = str_split($char);

			$ord = ord($bytes[0]);

			if ($ord < 128) {
				continue;
			}

			if ($ord < 224) {
				$ord = (ord($bytes[0]) - 192) * 64 + (ord($bytes[1]) - 128);
			}

			if ($ord < 240) {
				$ord = (ord($bytes[0]) - 224) * 4096 + (ord($bytes[1]) - 128) * 64 + (ord($bytes[2]) - 128);
			}

			if ($ord < 248) {
				$ord = (ord($bytes[0]) - 240) * 262144 + (ord($bytes[1]) - 128) * 4096 + (ord($bytes[2]) - 128) * 64 + (ord($bytes[3]) - 128);
			}

			if ($ord < 252) {
				$ord = (ord($bytes[0]) - 248) * 16777216 + (ord($bytes[1]) - 128) * 262144 + (ord($bytes[2]) - 128) * 4096 + (ord($bytes[3]) - 128) * 64 + (ord($bytes[4]) - 128);
			}

			if ($ord < 254) {
				$ord = (ord($bytes[0]) - 252) * 1073741824 + (ord($bytes[1]) - 128) * 16777216 + (ord($bytes[2]) - 128) * 262144 + (ord($bytes[3]) - 128) * 4096 + (ord($bytes[4]) - 128) * 64 + (ord($bytes[5]) - 128);
			}

			require_once __DIR__ . '/data/AsciiTransliteration_' . sprintf('%03d', $ord >> 8) . '.php';

			if (array_key_exists($ord & 255, $ascii_transliteration[$ord >> 8])) {
				$new_chars[$char_num] = $ascii_transliteration[$ord >> 8][$ord & 255];
			}
		}

		// Allow mods to adjust the changed characters.
		IntegrationHook::call('integrate_ascii_transliterator_chars', [$chars, &$new_chars]);

		return implode('', $new_chars);
	}
}

?>