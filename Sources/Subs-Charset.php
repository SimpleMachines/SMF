<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
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

	$string = (string) $string;

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	require_once($sourcedir . '/Unicode/CaseLower.php');

	$substitutions = utf8_strtolower_maps();

	foreach ($chars as &$char)
		$char = isset($substitutions[$char]) ? $substitutions[$char] : $char;

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

	$string = (string) $string;

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	require_once($sourcedir . '/Unicode/CaseUpper.php');

	$substitutions = utf8_strtoupper_maps();

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

	$string = (string) $string;

	$chars = preg_split('/(.)/su', $string, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	if ($chars === false)
		return false;

	require_once($sourcedir . '/Unicode/CaseFold.php');

	$substitutions = utf8_casefold_maps();

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
	$string = (string) $string;

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
	$string = (string) $string;

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
	$string = (string) $string;

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
	$string = (string) $string;

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

	$string = (string) $string;

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

			if (isset($chars[$c + 2]) && $chars[$c + 2] >= "\xE1\x86\xA8" && $chars[$c + 2] <= "\xE1\x87\x82")
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
	}

	$string = preg_replace('/' . implode('|', $disallowed) . '/u', $substitute, $string);

	// Are we done yet?
	if (!preg_match('/[' . $prop_classes['Join_Control'] . $prop_classes['Regional_Indicator'] . $prop_classes['Emoji'] . $prop_classes['Variation_Selector'] . ']/u', $string))
		return $string;

	// String must be in Normalization Form C for the following checks to work.
	$string = utf8_normalize_c($string);

	$placeholders = array();

	// Use placeholders to preserve known emoji from further processing.
	// Regex source is https://unicode.org/reports/tr51/#EBNF_and_Regex
	$string  = preg_replace_callback(
		'/' .
		// Flag emojis
		'[' . $prop_classes['Regional_Indicator'] . ']{2}' .
		// Or
		'|' .
		// Emoji characters
		'[' . $prop_classes['Emoji'] . ']' .
		// Possibly followed by modifiers of various sorts
		'(' .
			'[' . $prop_classes['Emoji_Modifier'] . ']' .
			'|' .
			'\x{FE0F}\x{20E3}?' .
			'|' .
			'[\x{E0020}-\x{E007E}]+\x{E007F}' .
		')?' .
		// Possibly concatenated with Zero Width Joiner and more emojis
		// (e.g. the "family" emoji sequences)
		'(' .
			'\x{200D}[' . $prop_classes['Emoji'] . ']' .
			'(' .
				'[' . $prop_classes['Emoji_Modifier'] . ']' .
				'|' .
				'\x{FE0F}\x{20E3}?' .
				'|' .
				'[\x{E0020}-\x{E007E}]+\x{E007F}' .
			')?' .
		')*' .
		'/u',
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
	if (preg_match('/[' . $prop_classes['Variation_Selector'] . ']/u', $string))
	{
		/*
			Unicode gives pre-defined lists of sanctioned variation sequences
			and says any use of variation selectors outside those sequences is
			unsanctioned.
		*/

		$patterns = array('/[' . $prop_classes['Ideographic'] . ']\K[\x{E0100}-\x{E01EF}]/u');

		foreach (utf8_regex_variation_selectors() as $variation_selector => $allowed_base_chars)
			$patterns[] = '/[' . $allowed_base_chars . ']\K[' . $variation_selector . ']/u';

		// Use placeholders for sanctioned variation selectors.
		$string = preg_replace_callback(
			$patterns,
			function ($matches) use (&$placeholders)
			{
				$placeholders[$matches[0]] = "\xEE\xB3\x9B" . md5($matches[0]) . "\xEE\xB3\x9C";
				return $placeholders[$matches[0]];
			},
			$string
		);

		// Remove any unsanctioned variation selectors.
		$string = preg_replace('/[' . $prop_classes['Variation_Selector'] . ']/u', $substitute, $string);
	}

	// Join controls are only allowed inside words in special circumstances.
	// See https://unicode.org/reports/tr31/#Layout_and_Format_Control_Characters
	if (preg_match('/[' . $prop_classes['Join_Control'] . ']/u', $string))
	{
		// Zero Width Non-Joiner (U+200C)
		$zwnj = "\xE2\x80\x8C";
		// Zero Width Joiner (U+200D)
		$zwj = "\xE2\x80\x8D";

		$placeholders[$zwnj] = "\xEE\x80\x8C";
		$placeholders[$zwj] = "\xEE\x80\x8D";

		// When not in strict mode, allow ZWJ at word boundaries.
		if ($level === 0)
			$string = preg_replace('/\b\x{200D}|\x{200D}\b/u', $placeholders[$zwj], $string);

		// Tests for Zero Width Joiner and Zero Width Non-Joiner.
		$joining_type_classes = utf8_regex_joining_type();
		$indic_classes = utf8_regex_indic();

		foreach (array_merge($joining_type_classes, $indic_classes) as $script => $classes)
		{
			// Cursive scripts like Arabic use ZWNJ in certain contexts.
			// For these scripts, use test A1 for allowing ZWNJ.
			// https://unicode.org/reports/tr31/#A1
			if (isset($joining_type_classes[$script]))
			{
				$lj = !empty($classes['Left_Joining']) ? $classes['Left_Joining'] : '';
				$rj = !empty($classes['Right_Joining']) ? $classes['Right_Joining'] : '';
				$t = !empty($classes['Transparent']) ? '[' . $classes['Transparent'] . ']*' : '';

				if (!empty($classes['Dual_Joining']))
				{
					$lj .= $classes['Dual_Joining'];
					$rj .= $classes['Dual_Joining'];
				}

				$pattern = '[' . $lj . ']' . $t . $zwnj . $t . '[' . $rj . ']';
			}
			// Indic scripts with viramas use ZWNJ and ZWJ in certain contexts.
			// For these scripts, use tests A2 and B for allowing ZWNJ and ZWJ.
			// https://unicode.org/reports/tr31/#A2
			// https://unicode.org/reports/tr31/#B
			else
			{
				// A letter that is part of this particular script.
				$letter = '[' . $classes['Letter'] . ']';

				// Zero or more non-spacing marks used in this script.
				$nonspacing_marks = '[' . $classes['Nonspacing_Mark'] . ']*';

				// Zero or more non-spacing combining marks used in this script.
				$nonspacing_combining_marks = '[' . $classes['Nonspacing_Combining_Mark'] . ']*';

				// ZWNJ must be followed by another letter in the same script.
				$zwnj_pattern = '\x{200C}(?=' . $nonspacing_combining_marks . $letter . ')';

				// ZWJ must NOT be followed by a vowel dependent character in this
				// script or by any character from a different script.
				$zwj_pattern = '\x{200D}(?!' . (!empty($classes['Vowel_Dependent']) ? '[' . $classes['Vowel_Dependent'] . ']|' : '') . '[^' . $classes['All'] . '])';

				// Now build the pattern for this script.
				$pattern = $letter . $nonspacing_marks . '[' . $classes['viramas'] . ']' . $nonspacing_combining_marks . '\K' . (!empty($zwj_pattern) ? '(?:' . $zwj_pattern . '|' . $zwnj_pattern . ')' : $zwnj_pattern);
			}

			// Do the thing.
			$string = preg_replace_callback(
				'/' . $pattern . '/u',
				function ($matches) use ($placeholders)
				{
					return strtr($matches[0], $placeholders);
				},
				$string
			);

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