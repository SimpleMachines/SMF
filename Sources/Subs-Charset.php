<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2021 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC4
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Converts the given UTF-8 string into lowercase.
 * Equivalent to mb_strtolower($string, 'UTF-8'), except that we can keep the
 * output consistent across PHP versions and up to date with the latest version
 * of Unicode.
 *
 * @param string $string The string
 * @return string The lowercase version of $string
 */
function utf8_strtolower($string)
{
	global $sourcedir;

	require_once($sourcedir . '/Unicode/CaseLower.php');

	$substitutions = utf8_strtolower_maps();

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	foreach ($chars as &$char)
		$char = isset($substitutions[$char]) ? $substitutions[$char] : $char;

	if ($chars === false)
		return false;

	return implode('', $chars);
}

/**
 * Convert the given UTF-8 string to uppercase.
 * Equivalent to mb_strtoupper($string, 'UTF-8'), except that we can keep the
 * output consistent across PHP versions and up to date with the latest version
 * of Unicode.
 *
 * @param string $string The string
 * @return string The uppercase version of $string
 */
function utf8_strtoupper($string)
{
	global $sourcedir;

	require_once($sourcedir . '/Unicode/CaseUpper.php');

	$substitutions = utf8_strtoupper_maps();

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	foreach ($chars as &$char)
		$char = isset($substitutions[$char]) ? $substitutions[$char] : $char;

	return implode('', $chars);
}

/**
 * Casefolds the given UTF-8 string.
 * Equivalent to mb_convert_case($string, MB_CASE_FOLD, 'UTF-8'), except that
 * we can keep the output consistent across PHP versions and up to date with
 * the latest version of Unicode.
 *
 * @param string $string The string
 * @return string The uppercase version of $string
 */
function utf8_casefold($string)
{
	global $sourcedir;

	require_once($sourcedir . '/Unicode/CaseFold.php');

	$substitutions = utf8_casefold_maps();

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	foreach ($chars as &$char)
		$char = isset($substitutions[$char]) ? $substitutions[$char] : $char;

	return implode('', $chars);
}

/**
 * Normalizes UTF-8 via Canonical Decomposition.
 *
 * @param string $string A UTF-8 string
 * @return string The decomposed version of $string
 */
function utf8_normalize_d($string)
{
	if (is_callable('normalizer_is_normalized') && normalizer_is_normalized($string, Normalizer::FORM_D))
		return $string;

	if (is_callable('normalizer_normalize'))
		return normalizer_normalize($string, Normalizer::FORM_D);

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	return implode('', utf8_decompose($chars, false));
}

/**
 * Normalizes UTF-8 via Compatibility Decomposition.
 *
 * @param string $string A UTF-8 string.
 * @return string The decomposed version of $string.
 */
function utf8_normalize_kd($string)
{
	if (is_callable('normalizer_is_normalized') && normalizer_is_normalized($string, Normalizer::FORM_KD))
		return $string;

	if (is_callable('normalizer_normalize'))
		return normalizer_normalize($string, Normalizer::FORM_KD);

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	return implode('', utf8_decompose($chars, true));
}

/**
 * Normalizes UTF-8 via Canonical Decomposition then Canonical Composition.
 *
 * @param string $string A UTF-8 string
 * @return string The composed version of $string
 */
function utf8_normalize_c($string)
{
	if (is_callable('normalizer_is_normalized') && normalizer_is_normalized($string, Normalizer::FORM_C))
		return $string;

	if (is_callable('normalizer_normalize'))
		return normalizer_normalize($string, Normalizer::FORM_C);

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	return implode('', utf8_compose(utf8_decompose($chars, false)));
}

/**
 * Normalizes UTF-8 via Compatibility Decomposition then Canonical Composition.
 *
 * @param string $string The string
 * @return string The composed version of $string
 */
function utf8_normalize_kc($string)
{
	if (is_callable('normalizer_is_normalized') && normalizer_is_normalized($string, Normalizer::FORM_KC))
		return $string;

	if (is_callable('normalizer_normalize'))
		return normalizer_normalize($string, Normalizer::FORM_KC);

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	return implode('', utf8_compose(utf8_decompose($chars, true)));
}

/**
 * Casefolds UTF-8 via Compatibility Composition Casefolding.
 * Used by idn_to_ascii polyfill in Subs-Compat.php
 *
 * @param string $string The string
 * @return string The casefolded version of $string
 */
function utf8_normalize_kc_casefold($string)
{
	global $sourcedir;

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	$chars = utf8_decompose($chars, true);

	require_once($sourcedir . '/Unicode/CaseFold.php');
	require_once($sourcedir . '/Unicode/DefaultIgnorables.php');

	$substitutions = utf8_casefold_maps();
	$ignorables = array_flip(utf8_default_ignorables());

	foreach ($chars as &$char)
	{
		if (isset($substitutions[$char]))
			$char = $substitutions[$char];

		elseif (isset($ignorables[$char]))
			$char = '';
	}

	return implode('', utf8_compose($chars));
}

/**
 * Helper function for utf8_normalize_d and utf8_normalize_kd.
 *
 * @param array $chars Array of Unicode characters
 * @return array Array of decomposed Unicode characters.
 */
function utf8_decompose($chars, $compatibility = false)
{
	global $sourcedir;

	if (!empty($compatibility))
	{
		require_once($sourcedir . '/Unicode/DecompositionCompatibility.php');

		$substitutions = utf8_normalize_kd_maps();

		foreach ($chars as &$char)
			$char = isset($substitutions[$char]) ? $substitutions[$char] : $char;
	}

	require_once($sourcedir . '/Unicode/DecompositionCanonical.php');
	require_once($sourcedir . '/Unicode/CombiningClasses.php');

	$substitutions = utf8_normalize_d_maps();
	$combining_classes = utf8_combining_classes();

	// Replace characters with decomposed forms.
	for ($i=0; $i < count($chars); $i++)
	{
		// Hangul characters.
		if ($chars[$i] >= "\xEA\xB0\x80" && $chars[$i] <= "\xED\x9E\xA3")
		{
			if (!function_exists('mb_ord'))
				require_once($sourcedir . '/Subs-Compat.php');

			$s = mb_ord($chars[$i]);
			$sindex = $s - 0xAC00;
			$l = 0x1100 + $sindex / (21 * 28);
			$v = 0x1161 + ($sindex % (21 * 28)) / 28;
			$t = $sindex % 28;

			$chars[$i] = implode('', array(mb_chr($l), mb_chr($v), $t ? mb_chr(0x11A7 + $t) : ''));
		}
		// Everything else.
		elseif (isset($substitutions[$chars[$i]]))
			$chars[$i] = $substitutions[$chars[$i]];
	}

	// Must re-split the string before sorting.
	$chars = preg_split('/(.)/su', implode('', $chars), 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	// Sort characters into canonical order.
	for ($i = 1; $i < count($chars); $i++)
	{
		if (empty($combining_classes[$chars[$i]]) || empty($combining_classes[$chars[$i - 1]]))
			continue;

		if ($combining_classes[$chars[$i - 1]] > $combining_classes[$chars[$i]])
		{
			$temp = $chars[$i];
			$chars[$i] = $chars[$i - 1];
			$chars[$i -1] = $temp;

			// Backtrack and check again.
			if ($i > 1)
				$i -= 2;
		}
	}

	return $chars;
}

/**
 * Helper function for utf8_normalize_c and utf8_normalize_kc.
 *
 * @param array $chars Array of decomposed Unicode characters
 * @return array Array of composed Unicode characters.
 */
function utf8_compose($chars)
{
	global $sourcedir;

	require_once($sourcedir . '/Unicode/Composition.php');
	require_once($sourcedir . '/Unicode/CombiningClasses.php');

	$substitutions = utf8_compose_maps();
	$combining_classes = utf8_combining_classes();

	for ($c = 0; $c < count($chars); $c++)
	{
		// Singleton replacements.
		if (isset($substitutions[$chars[$c]]))
			$chars[$c] = $substitutions[$chars[$c]];

		// Hangul characters.
		// See "Hangul Syllable Composition" in the Unicode standard, ch. 3.12.
		if ($chars[$c] >= "\xE1\x84\x80" && $chars[$c] <= "\xE1\x84\x92" && $chars[$c + 1] >= "\xE1\x85\xA1" && $chars[$c + 1] <= "\xE1\x85\xB5")
		{
			if (!function_exists('mb_ord'))
				require_once($sourcedir . '/Subs-Compat.php');

			$l_part = $chars[$c];
			$v_part = $chars[$c + 1];
			$t_part = null;

			$l_index = mb_ord($l_part) - 0x1100;
			$v_index = mb_ord($v_part) - 0x1161;

			$lv_index = $l_index * 588 + $v_index * 28;
			$s = 0xAC00 + $lv_index;

			if ($chars[$c + 2] >= "\xE1\x86\xA8" && $chars[$c + 2] <= "\xE1\x87\x82")
			{
				$t_part = $chars[$c + 2];
				$t_index = mb_ord($t_part) - 0x11A7;
				$s += $t_index;
			}

			$chars[$c] = mb_chr($s);
			$chars[++$c] = null;

			if (isset($t_part))
				$chars[++$c] = null;

			continue;
		}

		if ($c > 0)
		{
			$ccc = isset($combining_classes[$chars[$c]]) ? $combining_classes[$chars[$c]] : 0;

			// Find the preceding starter character.
			$l = $c - 1;
			while ($l > 0 && (!isset($chars[$l]) || (!empty($combining_classes[$chars[$l]]) && $combining_classes[$chars[$l]] < $ccc)))
				$l--;

			// Is there a composed form for this combination?
			if (isset($substitutions[$chars[$l] . $chars[$c]]))
			{
				// Replace the starter character with the composed character.
				$chars[$l] = $substitutions[$chars[$l] . $chars[$c]];

				// Unset the current combining character.
				$chars[$c] = null;
			}
		}
	}

	return $chars;
}

/**
 * Helper function for sanitize_chars() that deals with invisible characters.
 *
 * This function deals with control characters, private use characters,
 * non-characters, and characters that are invisible by definition in the
 * Unicode standard. It does not deal with characters that are supposed to be
 * visible according to the Unicode standard, and makes no attempt to compensate
 * for possibly incomplete Unicode support in text rendering engines on client
 * devices.
 *
 * @param string $string The string to sanitize.
 * @param int $level Controls how invisible formatting characters are handled.
 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
 *      2: Disallow all formatting characters. Use for internal comparisions
 *         only, such as in the word censor, search contexts, etc.
 * @param string $substitute Replacement string for the invalid characters.
 * @return string The sanitized string.
 */
function utf8_sanitize_invisibles($string, $level, $substitute)
{
	global $sourcedir;

	$string = (string) $string;
	$level = min(max((int) $level, 0), 2);
	$substitute = (string) $substitute;

	require_once($sourcedir . '/Unicode/RegularExpressions.php');
	$prop_classes = utf8_regex_properties();

	// We never want non-whitespace control characters
	$disallowed[] = '[^\P{Cc}\t\r\n]';

	// We never want private use characters or non-characters.
	// Use our own version of \p{Cn} in order to avoid possible inconsistencies
	// between our data and whichever version of PCRE happens to be installed
	// on this server. Unlike \p{Cc} and \p{Co}, which never change, the value
	// of \p{Cn} changes with every new version of Unicode.
	$disallowed[] = '[\p{Co}' . $prop_classes['Cn'] . ']';

	// Several more things we never want:
	$disallowed[] = '[' . implode('', array(
		// Soft Hyphen.
		'\x{AD}',
		// Khmer Vowel Inherent AQ and Khmer Vowel Inherent AA.
		// Unicode Standard ch. 16 says: "they are insufficient for [their]
		// purpose and should be considered errors in the encoding."
		'\x{17B4}-\x{17B5}',
		// Invisible math characters.
		'\x{2061}-\x{2064}',
		// Deprecated formatting characters.
		'\x{206A}-\x{206F}',
		// Zero Width No-Break Space, a.k.a. Byte Order Mark.
		'\x{FEFF}',
		// Annotation characters and Object Replacement Character.
		'\x{FFF9}-\x{FFFC}',
	)) . ']';

	switch ($level)
	{
		case 2:
			$disallowed[] = '[' . implode('', array(
				// Combining Grapheme Character.
				'\x{34F}',
				// Zero Width Non-Joiner.
				'\x{200C}',
				// Zero Width Joiner.
				'\x{200D}',
				// All variation selectors.
				$prop_classes['Variation_Selector'],
				// Tag characters.
				'\x{E0000}-\x{E007F}',
			)) . ']';

			// no break

		case 1:
			$disallowed[] = '[' . implode('', array(
				// Zero Width Space.
				'\x{200B}',
				// Word Joiner.
				'\x{2060}',
				// "Bidi_Control" characters.
				// Disallowing means that all characters will behave according
				// to their default bidirectional text properties.
				$prop_classes['Bidi_Control'],
				// Hangul filler characters.
				// Used as placeholders in incomplete ideographs.
				'\x{115F}\x{1160}\x{3164}\x{FFA0}',
				// Shorthand formatting characters.
				'\x{1BCA0}-\x{1BCA3}',
				// Musical formatting characters.
				'\x{1D173}-\x{1D17A}',
			)) . ']';

			break;

		default:
			// Zero Width Space only allowed in certain scripts.
			$disallowed[] = '(?<![\p{Thai}\p{Myanmar}\p{Khmer}\p{Hiragana}\p{Katakana}])\x{200B}';

			// Word Joiner disallowed inside words. (Yes, \w is Unicode safe.)
			$disallowed[] = '(?<=\w)\x{2060}(?=\w)';

			// Hangul Choseong Filler and Hangul Jungseong Filler must followed
			// by more Hangul Jamo characters.
			$disallowed[] = '[\x{115F}\x{1160}](?![\x{1100}-\x{11FF}\x{A960}-\x{A97F}\x{D7B0}-\x{D7FF}])';

			// Hangul Filler for Hangul compatibility chars.
			$disallowed[] = '\x{3164}(?![\x{3130}-\x{318F}])';

			// Halfwidth Hangul Filler for halfwidth Hangul compatibility chars.
			$disallowed[] = '\x{FFA0}(?![\x{FFA1}-\x{FFDC}])';

			// Shorthand formatting characters only with other shorthand chars.
			$disallowed[] = '[\x{1BCA0}-\x{1BCA3}](?![\x{1BC00}-\x{1BC9F}])';
			$disallowed[] = '(?<![\x{1BC00}-\x{1BC9F}])[\x{1BCA0}-\x{1BCA3}]';

			// Musical formatting characters only with other musical chars.
			$disallowed[] = '[\x{1D173}\x{1D175}\x{1D177}\x{1D179}](?![\x{1D100}-\x{1D1FF}])';
			$disallowed[] = '(?<![\x{1D100}-\x{1D1FF}])[\x{1D174}\x{1D176}\x{1D178}\x{1D17A}]';

			break;
	}

	if ($level < 2)
	{
		/*
			Combining Grapheme Character has two uses: to override standard
			search and collation behaviours, which we never want to allow, and
			to ensure correct behaviour of combining marks in a few exceptional
			cases, which is legitimate and should be allowed. This means we can
			simply test whether it is followed by a combining mark in order to
			determine whether to allow it.
		*/
		$disallowed[] = '\x{34F}(?!\p{M})';

		// Tag characters not allowed inside words.
		$disallowed[] = '(?<=\w)[\x{E0000}-\x{E007F}](?=\w)';

		// Mongolian Free Variation Selectors.
		$disallowed[] = '(?<!\p{Mongolian})[\x{180B}-\x{180D}\x{180F}]';
	}

	$string = preg_replace('/' . implode('|', $disallowed) . '/u', $substitute, $string);

	/*
		Past this point, we need to use mb_ereg* functions because they support
		character class intersection and more Unicode properties than the preg*
		functions do.
	*/
	if (!function_exists('mb_ereg_replace_callback') || !preg_match('/[' . $prop_classes['Join_Control'] . $prop_classes['Regional_Indicator'] . $prop_classes['Emoji'] . $prop_classes['Variation_Selector'] . ']/u', $string))
		return $string;

	mb_regex_encoding('UTF-8');

	// String must be in Normalization Form C for the following checks to work.
	$string = utf8_normalize_c($string);

	$placeholders = array();

	// Use placeholders to preserve known emoji from further processing.
	// Regex source is https://unicode.org/reports/tr51/#EBNF_and_Regex
	$string  = mb_ereg_replace_callback(
		'[' . $prop_classes['Regional_Indicator'] . ']{2}' .
		'|' .
		'[' . $prop_classes['Emoji'] . ']' .
		'(' .
			'[' . $prop_classes['Emoji_Modifier'] . ']' .
			'|' .
			'\x{FE0F}\x{20E3}?' .
			'|' .
			'[\x{E0020}-\x{E007E}]+\x{E007F}' .
		')?' .
		'(' .
			'\x{200D}[' . $prop_classes['Emoji'] . ']' .
			'(' .
				'[' . $prop_classes['Emoji_Modifier'] . ']' .
				'|' .
				'\x{FE0F}\x{20E3}?' .
				'|' .
				'[\x{E0020}-\x{E007E}]+\x{E007F}' .
			')?' .
		')*',
		function ($matches) use (&$placeholders)
		{
			// Skip lone ASCII characters that are not actully part of an emoji sequence.
			// This can happen because the digits 0-9 and the '*' and '#' characters are
			// the base characters for the "Emoji_Keycap_Sequence" emojis.
			if (strlen($matches[0]) === 1)
				return $matches[0];

			$placeholders[$matches[0]] = "\xEE\xB3\x9B" . md5($matches[0]) . "\xEE\xB3\x9C";
			return $placeholders[$matches[0]];
		},
		$string
	);

	// Get rid of any unsanctioned variation selectors.
	if (mb_ereg('[' . $prop_classes['Variation_Selector'] . ']', $string))
	{
		/*
			Unicode gives pre-defined lists of sanctioned variation sequences
			and says any use of variation selectors outside those sequences is
			unsanctioned. However, those lists will continue to grow over time.
			Therefore, the regex patterns below are more permissive than
			Unicode itself, making reasonable guesses about the types of
			characters that are likely to be used as base characters for new
			variation sequences in the future.
		*/

		// Base characters that take variation selectors 1 - 16
		$variation_base_chars_low = implode('', array(
			// Symbols.
			'\p{S}',
			// CJK Symbols and Punctuation.
			'\x{3000}-\x{303F}',
			// CJK Unified Ideographs.
			'\x{3400}-\x{4DBF}',
			'\x{4E00}-\x{9FFF}',
			'\x{20000}-\x{2A6DF}',
			// Halfwidth and Fullwidth Forms.
			'\x{FF01}-\x{FFEE}',
			// Multiple characters in these scripts can have variations.
			'\p{Myanmar}',
			'\p{Phags_Pa}',
			'\p{Manichaean}',
		));
		$string = mb_ereg_replace('[^' . $variation_base_chars_low . ']\K[\x{FE00}-\x{FE0F}]', $substitute, $string);

		// For variation selectors 17 - 256, things are simpler.
		$string = mb_ereg_replace('[^' . $prop_classes['Ideographic'] . ']\K[\x{E0100}-\x{E01EF}]', $substitute, $string);
	}

	// Join controls are only allowed inside words in special circumstances.
	// See https://unicode.org/reports/tr31/#Layout_and_Format_Control_Characters
	if (mb_ereg('[' . $prop_classes['Join_Control'] . ']', $string))
	{
		// Zero Width Non-Joiner (U+200C)
		$zwnj = "\xE2\x80\x8C";
		// Zero Width Joiner (U+200D)
		$zwj = "\xE2\x80\x8D";

		$placeholders[$zwnj] = "\xEE\x80\x8C";
		$placeholders[$zwj] = "\xEE\x80\x8C";

		// When not in strict mode, allow ZWJ at word boundaries.
		if ($level === 0)
			$string = mb_ereg_replace('\b\x{200D}|\x{200D}\b', $placeholders[$zwj], $string);

		// Tests for Zero Width Joiner and Zero Width Non-Joiner.
		$script_tests = array(
			// For these scripts, use test A1 for allowing ZWNJ
			// https://unicode.org/reports/tr31/#A1
			// Character class lists compiled from:
			// https://unicode.org/Public/UNIDATA/extracted/DerivedJoiningType.txt
			'Arabic' => array(
				'dual_joining' => '\x{0620}\x{0626}\x{0628}\x{062A}-\x{062E}\x{0633}-\x{063F}\x{0641}-\x{0647}\x{0649}-\x{064A}\x{066E}-\x{066F}\x{0678}-\x{0687}\x{069A}-\x{06BF}\x{06C1}-\x{06C2}\x{06CC}\x{06CE}\x{06D0}-\x{06D1}\x{06FA}-\x{06FC}\x{06FF}\x{074E}-\x{0758}\x{075C}-\x{076A}\x{076D}-\x{0770}\x{0772}\x{0775}-\x{0777}\x{077A}-\x{077F}\x{08A0}-\x{08A9}\x{08AF}-\x{08B0}\x{08B3}-\x{08B8}\x{08BA}-\x{08C8}',
				'right_joining' => '\x{0622}-\x{0625}\x{0627}\x{0629}\x{062F}-\x{0632}\x{0648}\x{0671}-\x{0673}\x{0675}-\x{0677}\x{0688}-\x{0699}\x{06C0}\x{06C3}-\x{06CB}\x{06CD}\x{06CF}\x{06D2}-\x{06D3}\x{06D5}\x{06EE}-\x{06EF}\x{0759}-\x{075B}\x{076B}-\x{076C}\x{0771}\x{0773}-\x{0774}\x{0778}-\x{0779}\x{08AA}-\x{08AC}\x{08AE}\x{08B1}-\x{08B2}\x{08B9}',
				'transparent_joining' => '\x{0610}-\x{061A}\x{061C}\x{064B}-\x{065F}\x{06D6}-\x{06DC}\x{06DF}-\x{06E4}\x{06E7}-\x{06E8}\x{06EA}-\x{06ED}\x{08CA}-\x{08E1}\x{08E3}-\x{0902}',
			),
			'Syriac' => array(
				'dual_joining' => '\x{0712}-\x{0714}\x{071A}-\x{071D}\x{071F}-\x{0727}\x{0729}\x{072B}\x{072D}-\x{072E}\x{0860}\x{0862}-\x{0865}\x{0868}',
				'right_joining' => '\x{0710}\x{0715}-\x{0719}\x{071E}\x{0728}\x{072A}\x{072C}\x{072F}\x{074D}\x{0867}\x{0869}-\x{086A}',
				'transparent_joining' => '\x{070F}\x{0711}\x{0730}-\x{074A}',
			),
			'Mongolian' => array(
				'dual_joining' => '\x{1807}\x{1820}-\x{1878}\x{1887}-\x{18A8}\x{18AA}',
				'transparent_joining' => '\x{180B}-\x{180D}\x{1885}-\x{1886}\x{18A9}',
			),
			'Nko' => array(
				'dual_joining' => '\x{07CA}-\x{07EA}',
				'transparent_joining' => '\x{07EB}-\x{07F3}\x{07FD}',
			),
			'Mandaic' => array(
				'dual_joining' => '\x{0841}-\x{0845}\x{0848}\x{084A}-\x{0853}\x{0855}',
				'right_joining' => '\x{0840}\x{0846}-\x{0847}\x{0849}\x{0854}\x{0856}-\x{0858}',
				'transparent_joining' => '\x{0859}-\x{085B}',
			),
			'Manichaean' => array(
				'dual_joining' => '\x{10AC0}-\x{10AC4}\x{10AD3}-\x{10AD6}\x{10AD8}-\x{10ADC}\x{10ADE}-\x{10AE0}\x{10AEB}-\x{10AEE}',
				'right_joining' => '\x{10AC5}\x{10AC7}\x{10AC9}-\x{10ACA}\x{10ACE}-\x{10AD2}\x{10ADD}\x{10AE1}\x{10AE4}\x{10AEF}',
				'left_joining' => '\x{10ACD}\x{10AD7}',
				'transparent_joining' => '\x{10AE5}-\x{10AE6}',
			),
			'Psalter_Pahlavi' => array(
				'dual_joining' => '\x{10B80}\x{10B82}\x{10B86}-\x{10B88}\x{10B8A}-\x{10B8B}\x{10B8D}\x{10B90}\x{10BAD}-\x{10BAE}',
				'right_joining' => '\x{10B81}\x{10B83}-\x{10B85}\x{10B89}\x{10B8C}\x{10B8E}-\x{10B8F}\x{10B91}\x{10BA9}-\x{10BAC}',
			),
			'Hanifi_Rohingya' => array(
				'dual_joining' => '\x{10D01}-\x{10D21}\x{10D23}',
				'right_joining' => '\x{10D22}',
				'left_joining' => '\x{10D00}',
				'transparent_joining' => '\x{10D24}-\x{10D27}',
			),
			'Sogdian' => array(
				'dual_joining' => '\x{10F30}-\x{10F32}\x{10F34}-\x{10F44}\x{10F51}-\x{10F53}',
				'right_joining' => '\x{10F33}\x{10F54}',
				'transparent_joining' => '\x{10F46}-\x{10F50}',
			),
			'Chorasmian' => array(
				'dual_joining' => '\x{0FB0}\x{0FB2)-\x{10FB3}\x{0FB8}\x{0FBB)-\x{10FBC}\x{0FBE)-\x{10FBF}\x{0FC1}\x{0FC4}\x{0FCA}',
				'right_joining' => '\x{0FB4)-\x{10FB6}\x{0FB9)-\x{10FBA}\x{0FBD}\x{0FC2)-\x{10FC3}\x{0FC9}',
				'left_joining' => '\x{0FCB}',
			),
			'Adlam' => array(
				'dual_joining' => '\x{1E900}-\x{1E943}',
				'transparent_joining' => '\x{1E944}-\x{1E94B}',
			),

			// For these scripts, use tests A2 and B for allowing ZWNJ and ZWJ
			// https://unicode.org/reports/tr31/#A2
			// https://unicode.org/reports/tr31/#B
			// Character class lists compiled from:
			// https://unicode.org/Public/UNIDATA/extracted/DerivedCombiningClass.txt
			// https://unicode.org/Public/UNIDATA/IndicSyllabicCategory.txt
			'Devanagari' => array(
				'viramas' => '\x{094D}',
				'vowel_dependents' => '\x{093A}-\x{093B}\x{093E}-\x{094C}\x{094E}-\x{094F}\x{0955}-\x{0957}\x{0962}-\x{0963}\x{A8FF}',
			),
			'Bengali' => array(
				'viramas' => '\x{09CD}',
				'vowel_dependents' => '\x{09BE}-\x{09C4}\x{09C7}-\x{09C8}\x{09CB}-\x{09CC}\x{09D7}\x{09E2}-\x{09E3}',
			),
			'Gurmukhi' => array(
				'viramas' => '\x{0A4D}',
				'vowel_dependents' => '\x{0A3E}-\x{0A42}\x{0A47}-\x{0A48}\x{0A4B}-\x{0A4C}',
			),
			'Gujarati' => array(
				'viramas' => '\x{0ACD}',
				'vowel_dependents' => '\x{0ABE}-\x{0AC5}\x{0AC7}-\x{0AC9}\x{0ACB}-\x{0ACC}\x{0AE2}-\x{0AE3}',
			),
			'Oriya' => array(
				'viramas' => '\x{0B4D}',
				'vowel_dependents' => '\x{0B3E}-\x{0B44}\x{0B47}-\x{0B48}\x{0B4B}-\x{0B4C}\x{0B55}-\x{0B57}\x{0B62}-\x{0B63}',
			),
			'Tamil' => array(
				'viramas' => '\x{0BCD}',
				'vowel_dependents' => '\x{0BBE}-\x{0BC2}\x{0BC6}-\x{0BC8}\x{0BCA}-\x{0BCC}\x{0BD7}',
			),
			'Telugu' => array(
				'viramas' => '\x{0C4D}',
				'vowel_dependents' => '\x{0C3E}-\x{0C44}\x{0C46}-\x{0C48}\x{0C4A}-\x{0C4C}\x{0C55}-\x{0C56}\x{0C62}-\x{0C63}',
			),
			'Kannada' => array(
				'viramas' => '\x{0CCD}',
				'vowel_dependents' => '\x{0CBE}-\x{0CC4}\x{0CC6}-\x{0CC8}\x{0CCA}-\x{0CCC}\x{0CD5}-\x{0CD6}\x{0CE2}-\x{0CE3}',
			),
			'Malayalam' => array(
				'viramas' => '\x{0D4D}',
				'vowel_dependents' => '\x{0D3E}-\x{0D44}\x{0D46}-\x{0D48}\x{0D4A}-\x{0D4C}\x{0D57}\x{0D62}-\x{0D63}',
			),
			'Sinhala' => array(
				'viramas' => '\x{0DCA}',
				'vowel_dependents' => '\x{0DCF}-\x{0DD4}\x{0DD6}\x{0DD8}-\x{0DDF}\x{0DF2}-\x{0DF3}',
			),
			'Thai' => array(
				'viramas' => '\x{0E3A}',
				'vowel_dependents' => '\x{0E30}\x{0E40}\x{0E47}',
			),
			'Lao' => array(
				'viramas' => '\x{0EBA}',
				'vowel_dependents' => '\x{0EB0}-\x{0EB9}\x{0EBB}\x{0EC0}-\x{0EC4}',
			),
			'Tibetan' => array(
				'viramas' => '\x{0F84}',
				'vowel_dependents' => '\x{0F71}-\x{0F7D}\x{0F80}-\x{0F81}',
			),
			'Myanmar' => array(
				'viramas' => '\x{1039}-\x{103A}',
				'vowel_dependents' => '\x{102B}-\x{1035}\x{1056}-\x{1059}\x{1062}\x{1067}-\x{1068}\x{1071}-\x{1074}\x{1083}-\x{1086}\x{109C}-\x{109D}\x{A9E5}',
			),
			'Tagalog' => array(
				'viramas' => '\x{1714}-\x{1715}',
				'vowel_dependents' => '\x{1712}-\x{1713}',
			),
			'Hanunoo' => array(
				'viramas' => '\x{1734}',
				'vowel_dependents' => '\x{1732}-\x{1733}',
			),
			'Khmer' => array(
				'viramas' => '\x{17D2}',
				'vowel_dependents' => '\x{17B6}-\x{17C5}\x{17C8}',
			),
			'Tai_Tham' => array(
				'viramas' => '\x{1A60}',
				'vowel_dependents' => '\x{1A61}-\x{1A73}',
			),
			'Balinese' => array(
				'viramas' => '\x{1B44}',
				'vowel_dependents' => '\x{1B35}-\x{1B43}',
			),
			'Sundanese' => array(
				'viramas' => '\x{1BAA}-\x{1BAB}',
				'vowel_dependents' => '\x{1BA4}-\x{1BA9}',
			),
			'Batak' => array(
				'viramas' => '\x{1BF2}-\x{1BF3}',
				'vowel_dependents' => '\x{1BE7}-\x{1BEF}',
			),
			'Tifinagh' => array(
				'viramas' => '\x{2D7F}',
				'vowel_dependents' => '',
			),
			'Syloti_Nagri' => array(
				'viramas' => '\x{A806}-\x{A82C}',
				'vowel_dependents' => '\x{A802}\x{A823}-\x{A827}',
			),
			'Saurashtra' => array(
				'viramas' => '\x{A8C4}',
				'vowel_dependents' => '\x{A8B5}-\x{A8C3}',
			),
			'Rejang' => array(
				'viramas' => '\x{A953}',
				'vowel_dependents' => '\x{A947}-\x{A94E}',
			),
			'Javanese' => array(
				'viramas' => '\x{A9C0}',
				'vowel_dependents' => '\x{A9B4}-\x{A9BC}',
			),
			'Meetei_Mayek' => array(
				'viramas' => '\x{AAF6}-\x{ABED}',
				'vowel_dependents' => '\x{AAEB}-\x{AAEF}\x{ABE3}-\x{ABEA}',
			),
			'Kharoshthi' => array(
				'viramas' => '\x{10A3F}',
				'vowel_dependents' => '\x{10A01}-\x{10A03}\x{10A05}-\x{10A06}\x{10A0C}-\x{10A0D}',
			),
			'Brahmi' => array(
				'viramas' => '\x{11046}\x{11070}\x{1107F}',
				'vowel_dependents' => '\x{11038}-\x{11045}',
			),
			'Kaithi' => array(
				'viramas' => '\x{110B9}',
				'vowel_dependents' => '\x{110B0}-\x{110B8}',
			),
			'Chakma' => array(
				'viramas' => '\x{11133}-\x{11134}',
				'vowel_dependents' => '\x{11127}-\x{11132}\x{11145}-\x{11146}',
			),
			'Sharada' => array(
				'viramas' => '\x{111C0}',
				'vowel_dependents' => '\x{111B3}-\x{111BF}\x{111CB}-\x{111CC}',
			),
			'Khojki' => array(
				'viramas' => '\x{11235}',
				'vowel_dependents' => '\x{1122C}-\x{11233}',
			),
			'Khudawadi' => array(
				'viramas' => '\x{112EA}',
				'vowel_dependents' => '\x{112E0}-\x{112E8}',
			),
			'Grantha' => array(
				'viramas' => '\x{1134D}',
				'vowel_dependents' => '\x{1133E}-\x{11344}\x{11347}-\x{11348}\x{1134B}-\x{1134C}\x{11357}\x{11362}-\x{11363}',
			),
			'Newa' => array(
				'viramas' => '\x{11442}',
				'vowel_dependents' => '\x{11435}-\x{11441}',
			),
			'Tirhuta' => array(
				'viramas' => '\x{114C2}',
				'vowel_dependents' => '\x{114B0}-\x{114BE}',
			),
			'Siddham' => array(
				'viramas' => '\x{115BF}',
				'vowel_dependents' => '\x{115AF}-\x{115B5}\x{115B8}-\x{115BB}\x{115DC}-\x{115DD}',
			),
			'Modi' => array(
				'viramas' => '\x{1163F}',
				'vowel_dependents' => '\x{11630}-\x{1163C}\x{11640}',
			),
			'Takri' => array(
				'viramas' => '\x{116B6}',
				'vowel_dependents' => '\x{116AD}-\x{116B5}',
			),
			'Ahom' => array(
				'viramas' => '\x{1172B}',
				'vowel_dependents' => '\x{11720}-\x{1172A}',
			),
			'Dogra' => array(
				'viramas' => '\x{11839}',
				'vowel_dependents' => '\x{1182C}-\x{11836}',
			),
			'Nandinagari' => array(
				'viramas' => '\x{119E0}',
				'vowel_dependents' => '\x{119D1}-\x{119D7}\x{119DA}-\x{119DD}\x{119E4}',
			),
			'Zanabazar_Square' => array(
				'viramas' => '\x{11A34}\x{11A47}',
				'vowel_dependents' => '\x{11A01}-\x{11A0A}',
			),
			'Soyombo' => array(
				'viramas' => '\x{11A99}',
				'vowel_dependents' => '\x{11A51}-\x{11A5B}',
			),
			'Bhaiksuki' => array(
				'viramas' => '\x{11C3F}',
				'vowel_dependents' => '\x{11C2F}-\x{11C36}\x{11C38}-\x{11C3B}',
			),
			'Masaram_Gondi' => array(
				'viramas' => '\x{11D44}-\x{11D45}',
				'vowel_dependents' => '\x{11D31}-\x{11D36}\x{11D3A}\x{11D3C}-\x{11D3D}\x{11D3F}\x{11D43}',
			),
			'Gunjala_Gondi' => array(
				'viramas' => '\x{11D97}',
				'vowel_dependents' => '\x{11D8A}-\x{11D8E}\x{11D90}-\x{11D91}\x{11D93}-\x{11D94}',
			),
		);

		$all_combining_marks = '[' . implode('', array_keys(utf8_combining_classes())) . ']';

		foreach ($script_tests as $script => $chars)
		{
			// https://unicode.org/reports/tr31/#A1
			if (empty($chars['viramas']))
			{
				$lj = !empty($chars['left_joining']) ? $chars['left_joining'] : '';
				$rj = !empty($chars['right_joining']) ? $chars['right_joining'] : '';
				$t = !empty($chars['transparent_joining']) ? '[' . $chars['transparent_joining'] . ']*' : '';

				if (!empty($chars['dual_joining']))
				{
					$lj .= $chars['dual_joining'];
					$rj .= $chars['dual_joining'];
				}

				$pattern = '[' . $lj . ']' . $t . $zwnj . $t . '[' . $rj . ']';
			}
			// https://unicode.org/reports/tr31/#A2
			// https://unicode.org/reports/tr31/#B
			else
			{
				// Characters used in this script.
				$used_in_script = '[\p{' . $script . '}\p{Common}\p{Inherited}]';

				// A letter that is part of this particular script.
				$letter = '[\p{L}&&\p{' . $script . '}]';

				// Zero or more non-spacing marks used in this script.
				$nonspacing_marks = '[\p{Mn}&&' . $used_in_script . ']*';

				// Zero or more non-spacing combining marks used in this script.
				$nonspacing_combining_marks = '[\p{Mn}&&' . $used_in_script . '&&' . $all_combining_marks . ']*';

				// ZWNJ must be followed by another letter in the same script.
				$zwnj_pattern = '\x{200C}(?=' . $nonspacing_combining_marks . $letter . ')';

				// ZWJ must NOT be followed by a vowel dependent character in this
				// script or by any character from a different script.
				$zwj_pattern = '\x{200D}(?!' . (!empty($chars['vowel_dependents']) ? '[' . $chars['vowel_dependents'] . ']|' : '') . '\P{' . $script . '}})';

				// Now build the pattern for this script.
				$pattern = $letter . $nonspacing_marks . '[' . $chars['viramas'] . ']' . $nonspacing_combining_marks . '\K' . (!empty($zwj_pattern) ? '(?:' . $zwj_pattern . '|' . $zwnj_pattern . ')' : $zwnj_pattern);
			}

			// Do the thing.
			$temp = @mb_ereg_replace_callback(
				$pattern,
				function ($matches) use ($placeholders)
				{
					return strtr($matches[0], $placeholders);
				},
				$string
			);

			// False means the installed version of mbstring lacks support for this script.
			if ($temp !== false)
				$string = $temp;

			// Did we catch 'em all?
			if (strpos($string, $zwnj) === false && strpos($string, $zwj) === false)
				break;
		}

		// Apart from the exceptions above, ZWNJ and ZWJ are not allowed.
		$string = str_replace(array($zwj, $zwnj), $substitute, $string);
	}

	// Revert placeholders back to original characters.
	$string = strtr($string, array_flip($placeholders));


	return $string;
}

?>