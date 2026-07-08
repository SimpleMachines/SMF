<?php

/**
 * This file provides compatibility functions and code for older versions of
 * PHP, such as the sha1() function, missing extensions, or 64-bit vs 32-bit
 * systems. It is only included for those older versions or when the respective
 * extension or function cannot be found.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Define the old SMF sha1 function. Uses mhash if available
 *
 * @param string $str The string
 * @return string The sha1 hashed version of $str
 */
function sha1_smf($str)
{
	// If we have mhash loaded in, use it instead!
	if (function_exists('mhash') && defined('MHASH_SHA1'))
		return bin2hex(mhash(MHASH_SHA1, $str));

	$nblk = (strlen($str) + 8 >> 6) + 1;
	$blks = array_pad(array(), $nblk * 16, 0);

	for ($i = 0; $i < strlen($str); $i++)
		$blks[$i >> 2] |= ord($str[$i]) << (24 - ($i % 4) * 8);

	$blks[$i >> 2] |= 0x80 << (24 - ($i % 4) * 8);

	return sha1_core($blks, strlen($str) * 8);
}

/**
 * This is the core SHA-1 calculation routine, used by sha1().
 *
 * @param string $x
 * @param int $len
 * @return string
 */
function sha1_core($x, $len)
{
	@$x[$len >> 5] |= 0x80 << (24 - $len % 32);
	$x[(($len + 64 >> 9) << 4) + 15] = $len;

	$w = array();
	$a = 1732584193;
	$b = -271733879;
	$c = -1732584194;
	$d = 271733878;
	$e = -1009589776;

	for ($i = 0, $n = count($x); $i < $n; $i += 16)
	{
		$olda = $a;
		$oldb = $b;
		$oldc = $c;
		$oldd = $d;
		$olde = $e;

		for ($j = 0; $j < 80; $j++)
		{
			if ($j < 16)
				$w[$j] = isset($x[$i + $j]) ? $x[$i + $j] : 0;
			else
				$w[$j] = sha1_rol($w[$j - 3] ^ $w[$j - 8] ^ $w[$j - 14] ^ $w[$j - 16], 1);

			$t = sha1_rol($a, 5) + sha1_ft($j, $b, $c, $d) + $e + $w[$j] + sha1_kt($j);
			$e = $d;
			$d = $c;
			$c = sha1_rol($b, 30);
			$b = $a;
			$a = $t;
		}

		$a += $olda;
		$b += $oldb;
		$c += $oldc;
		$d += $oldd;
		$e += $olde;
	}

	return sprintf('%08x%08x%08x%08x%08x', $a, $b, $c, $d, $e);
}

/**
 * Helper function for the core SHA-1 calculation
 *
 * @param int $t
 * @param int $b
 * @param int $c
 * @param int $d
 * @return int
 */
function sha1_ft($t, $b, $c, $d)
{
	if ($t < 20)
		return ($b & $c) | ((~$b) & $d);
	if ($t < 40)
		return $b ^ $c ^ $d;
	if ($t < 60)
		return ($b & $c) | ($b & $d) | ($c & $d);

	return $b ^ $c ^ $d;
}

/**
 * Helper function for the core SHA-1 calculation
 *
 * @param int $t
 * @return int 1518500249, 1859775393, -1894007588 or -899497514 depending on the value of $t
 */
function sha1_kt($t)
{
	return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
}

/**
 * Helper function for the core SHA-1 calculation
 *
 * @param int $num
 * @param int $cnt
 * @return int
 */
function sha1_rol($num, $cnt)
{
	// Unfortunately, PHP uses unsigned 32-bit longs only.  So we have to kludge it a bit.
	if ($num & 0x80000000)
		$a = ($num >> 1 & 0x7fffffff) >> (31 - $cnt);
	else
		$a = $num >> (32 - $cnt);

	return ($num << $cnt) | $a;
}

/**
 * Available since: (PHP 5)
 * If the optional raw_output is set to TRUE, then the sha1 digest is instead returned in raw binary format with a length of 20,
 * otherwise the returned value is a 40-character hexadecimal number.
 *
 * @param string $text The text to hash
 * @return string The sha1 hash of $text
 */
function sha1_raw($text)
{
	return sha1($text, true);
}

if (!function_exists('smf_crc32'))
{
	/**
	 * Compatibility function.
	 * crc32 doesn't work as expected on 64-bit functions - make our own.
	 * https://php.net/crc32#79567
	 *
	 * @param string $number
	 * @return string The crc32 polynomial of $number
	 */
	function smf_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000)
		{
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}

if (!function_exists('mb_ord'))
{
	/**
	 * Compatibility function.
	 *
	 * This is a complete polyfill.
	 *
	 * @param string $string A character.
	 * @param string|null $encoding The character encoding.
	 *     If null, the current SMF encoding will be used, falling back to UTF-8.
	 * @return int|bool The Unicode code point of the character, or false on failure.
	 */
	function mb_ord($string, $encoding = null)
	{
		// Must have a supported encoding.
		if (($encoding = mb_ord_chr_encoding($encoding)) === false)
			return false;

		/* Alternative approach for certain encodings.
		 *
		 * This is required because there are some invalid byte sequences in
		 * these encodings for which native mb_ord() will return false, yet
		 * mb_convert_encoding() and iconv() will nevertheless convert into
		 * technically valid but semantically unrelated UTF-8 byte sequences.
		 *
		 * For these encodings, mb_encode_numericentity() always produces
		 * either an entity with the same number that mb_ord() would produce,
		 * or else malformed output for byte sequences where mb_ord() would
		 * return false. This allows us to use mb_encode_numericentity() as a
		 * (slow) alternative method for these encodings.
		 *
		 * Note: we cannot use mb_check_encoding() here, because it returns
		 * false for ALL invalid byte sequences, but mb_ord() only returns false
		 * for SOME invalid byte sequences.
		 */
		if (in_array($encoding, array('EUC-CN', 'EUC-KR', 'ISO-2022-KR')))
		{
			if (!function_exists('mb_encode_numericentity'))
				return false;

			$entity = mb_encode_numericentity($string, array(0x0,0x10FFFF,0x0,0xFFFFFF), $encoding);

			if (strpos($entity, '&#') !== 0)
				return false;

			return (int) trim($entity, '&#;');
		}

		// Convert to UTF-8. Return false on failure.
		if ($encoding !== 'UTF-8')
		{
			$temp = false;

			if (function_exists('mb_convert_encoding'))
			{
				$mb_substitute_character = mb_substitute_character();
				mb_substitute_character('none');

				$temp = mb_convert_encoding($string, 'UTF-8', $encoding);

				mb_substitute_character($mb_substitute_character);
			}

			if ($temp === false && function_exists('iconv'))
				$temp = iconv($encoding, 'UTF-8', $string);

			if ($temp === false)
				return false;

			$string = $temp;
		}

		if (strlen($string) === 1)
			return ord($string);

		// Get the values of the individual bytes.
		$unpacked = unpack('C*', substr($string, 0, 4));

		if ($unpacked === false)
		{
			$ord = 0;
		}
		elseif ($unpacked[1] >= 0xF0)
		{
			$ord = ($unpacked[1] - 0xF0) << 18;
			$ord += ($unpacked[2] - 0x80) << 12;
			$ord += ($unpacked[3] - 0x80) << 6;
			$ord += $unpacked[4] - 0x80;
		}
		elseif ($unpacked[1] >= 0xE0)
		{
			$ord = ($unpacked[1] - 0xE0) << 12;
			$ord += ($unpacked[2] - 0x80) << 6;
			$ord += $unpacked[3] - 0x80;
		}
		elseif ($unpacked[1] >= 0xC0)
		{
			$ord = ($unpacked[1] - 0xC0) << 6;
			$ord += $unpacked[2] - 0x80;
		}
		else
		{
			$ord = $unpacked[1];
		}

		// Surrogate pairs are invalid in UTF-8.
		if ($ord >= 0xD800 && $ord <= 0xDFFF)
			$ord = 0;

		return $ord;
	}
}

if (!function_exists('mb_chr'))
{
	/**
	 * Compatibility function.
	 *
	 * This is a complete polyfill.
	 *
	 * @param int $codepoint A Unicode codepoint value.
	 * @param string|null $encoding The character encoding.
	 *     If null, the current SMF encoding will be used, falling back to UTF-8.
	 * @return string|bool The requested character, or false on failure.
	 */
	function mb_chr($codepoint, $encoding = null)
	{
		// Must have a supported encoding.
		if (($encoding = mb_ord_chr_encoding($encoding)) === false)
			return false;

		// 0x10FFFF is the highest defined code point as of Unicode 13.0.0
		$codepoint %= 0x110000;

		if ($codepoint < 0x80)
		{
			$string = chr($codepoint);
		}
		elseif ($codepoint < 0x800)
		{
			$string = chr(0xC0 | $codepoint >> 6) . chr(0x80 | $codepoint & 0x3F);
		}
		elseif ($codepoint < 0x10000)
		{
			$string = chr(0xE0 | $codepoint >> 12) . chr(0x80 | $codepoint >> 6 & 0x3F) . chr(0x80 | $codepoint & 0x3F);
		}
		else
		{
			$string = chr(0xF0 | $codepoint >> 18) . chr(0x80 | $codepoint >> 12 & 0x3F) . chr(0x80 | $codepoint >> 6 & 0x3F) . chr(0x80 | $codepoint & 0x3F);
		}

		// Return in the requested encoding, or false on failure.
		// Note: native mb_chr() always returns a character in regular UTF-8
		// when the encoding is set to one of the UTF-8-Mobile* encodings. If
		// that behaviour changes in the future, add version checks here.
		if (strpos($encoding, 'UTF-8') !== 0)
		{
			$temp = false;

			if (function_exists('mb_convert_encoding'))
			{
				$mb_substitute_character = mb_substitute_character();
				mb_substitute_character('none');

				$temp = mb_convert_encoding($string, $encoding, 'UTF-8');

				mb_substitute_character($mb_substitute_character);
			}

			if ($temp === false && function_exists('iconv'))
				$temp = iconv('UTF-8', $encoding, $string);

			if ($temp === false)
				return false;

			$string = $temp;
		}

		return $string;
	}
}

/**
 * Helper function for the mb_ord and mb_chr polyfills.
 *
 * Checks whether $encoding is a supported character encoding for the mb_ord
 * and mb_chr functions. If $encoding is null, the current default character
 * encoding is used. If the encoding is supported, it is returned as a string.
 * If not, false is returned.
 *
 * @param string $encoding A character encoding to check, or null for default.
 * @return string|bool The character encoding, or false if unsupported.
 */
function mb_ord_chr_encoding($encoding = null)
{
	global $modSettings, $txt;

	if (is_null($encoding))
	{
		if (isset($modSettings['global_character_set']))
			$encoding = $modSettings['global_character_set'];

		elseif (isset($txt['lang_character_set']))
			$encoding = $txt['lang_character_set'];

		elseif (function_exists('mb_internal_encoding'))
			$encoding = mb_internal_encoding();

		elseif (ini_get('default_charset') != false)
			$encoding = ini_get('default_charset');

		else
			$encoding = 'UTF-8';
	}

	// Only some mb_string encodings are supported by mb_chr() and mb_ord().
	$supported_encodings = array(
		'8bit', 'UCS-4', 'UCS-4BE', 'UCS-4LE', 'UCS-2', 'UCS-2BE', 'UCS-2LE',
		'UTF-32', 'UTF-32BE', 'UTF-32LE', 'UTF-16', 'UTF-16BE', 'UTF-16LE',
		'UTF-8', 'ASCII', 'EUC-JP', 'SJIS', 'eucJP-win', 'EUC-JP-2004',
		'SJIS-win', 'SJIS-Mobile#DOCOMO', 'SJIS-Mobile#KDDI',
		'SJIS-Mobile#SOFTBANK', 'SJIS-mac', 'SJIS-2004', 'UTF-8-Mobile#DOCOMO',
		'UTF-8-Mobile#KDDI-A', 'UTF-8-Mobile#KDDI-B', 'UTF-8-Mobile#SOFTBANK',
		'CP932', 'CP51932', 'GB18030', 'Windows-1252', 'Windows-1254',
		'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
		'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10',
		'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'EUC-CN',
		'CP936', 'HZ', 'EUC-TW', 'BIG-5', 'CP950', 'EUC-KR', 'UHC',
		'ISO-2022-KR', 'Windows-1251', 'CP866', 'KOI8-R', 'KOI8-U', 'ArmSCII-8',
		'CP850', 'JIS-ms',
	);

	// Found it.
	if (in_array($encoding, $supported_encodings))
		return $encoding;

	// Gracefully handle aliases and incorrect lettercase.
	$encoding_l = strtolower($encoding);
	foreach ($supported_encodings as $possible_encoding)
	{
		$aliases = array_merge(array($possible_encoding), mb_encoding_aliases($possible_encoding));

		foreach ($aliases as $alias)
		{
			if (strtolower($alias) === $encoding_l)
				return $possible_encoding;
		}
	}

	return false;
}

/**
 * IDNA_* constants used as flags for the idn_to_* functions.
 */
foreach (
	array(
		'IDNA_DEFAULT' => 0,
		'IDNA_ALLOW_UNASSIGNED' => 1,
		'IDNA_USE_STD3_RULES' => 2,
		'IDNA_CHECK_BIDI' => 4,
		'IDNA_CHECK_CONTEXTJ' => 8,
		'IDNA_NONTRANSITIONAL_TO_ASCII' => 16,
		'IDNA_NONTRANSITIONAL_TO_UNICODE' => 32,
		'INTL_IDNA_VARIANT_2003' => 0,
		'INTL_IDNA_VARIANT_UTS46' => 1,
	)
	as $name => $value
)
{
	if (!defined($name))
		define($name, $value);
};

if (!function_exists('idn_to_ascii'))
{
	/**
	 * Compatibility function.
	 *
	 * This is not a complete polyfill:
	 *
	 *  - $flags only supports IDNA_DEFAULT, IDNA_NONTRANSITIONAL_TO_ASCII,
	 *    and IDNA_USE_STD3_RULES.
	 *  - $variant is ignored, because INTL_IDNA_VARIANT_UTS46 is always used.
	 *  - $idna_info is ignored.
	 *
	 * @param string $domain The domain to convert, which must be UTF-8 encoded.
	 * @param int $flags A subset of possible IDNA_* flags.
	 * @param int $variant Ignored in this compatibility function.
	 * @param array|null $idna_info Ignored in this compatibility function.
	 * @return string|bool The domain name encoded in ASCII-compatible form, or false on failure.
	 */
	function idn_to_ascii($domain, $flags = 0, $variant = 1, &$idna_info = null)
	{
		global $sourcedir;

		static $Punycode;

		require_once($sourcedir . '/Class-Punycode.php');

		if (!is_object($Punycode))
			$Punycode = new Punycode();

		if (method_exists($Punycode, 'useStd3'))
			$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		if (method_exists($Punycode, 'useNonTransitional'))
			$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_ASCII));

		return $Punycode->encode($domain);
	}
}

if (!function_exists('idn_to_utf8'))
{
	/**
	 * Compatibility function.
	 *
	 * This is not a complete polyfill:
	 *
	 *  - $flags only supports IDNA_DEFAULT, IDNA_NONTRANSITIONAL_TO_UNICODE,
	 *    and IDNA_USE_STD3_RULES.
	 *  - $variant is ignored, because INTL_IDNA_VARIANT_UTS46 is always used.
	 *  - $idna_info is ignored.
	 *
	 * @param string $domain Domain to convert, in an IDNA ASCII-compatible format.
	 * @param int $flags Ignored in this compatibility function.
	 * @param int $variant Ignored in this compatibility function.
	 * @param array|null $idna_info Ignored in this compatibility function.
	 * @return string|bool The domain name in Unicode, encoded in UTF-8, or false on failure.
	 */
	function idn_to_utf8($domain, $flags = 0, $variant = 1, &$idna_info = null)
	{
		global $sourcedir;

		static $Punycode;

		require_once($sourcedir . '/Class-Punycode.php');

		if (!is_object($Punycode))
			$Punycode = new Punycode();

		$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_UNICODE));

		return $Punycode->decode($domain);
	}
}

/**
 * Prevent fatal errors under PHP 8 when a disabled internal function is called.
 *
 * Before PHP 8, calling a disabled internal function merely generated a
 * warning that could be easily suppressed by the @ operator. But as of PHP 8
 * a disabled internal function is treated like it is undefined, which means
 * a fatal error will be thrown and execution will halt. SMF expects the old
 * behaviour, so these no-op polyfills make sure that is what happens.
 */
if (version_compare(PHP_VERSION, '8.0.0', '>='))
{
	/*
	 * This array contains function names that meet the following conditions:
	 *
	 * 1. SMF assumes they are defined, even if disabled. Note that prior to
	 *    PHP 8, this was always true for internal functions.
	 *
	 * 2. Some hosts are known to disable them.
	 *
	 * 3. SMF can get by without them (as opposed to missing functions that
	 *    really SHOULD cause execution to halt).
	 */
	foreach (array('set_time_limit') as $func)
	{
		if (!function_exists($func))
			eval('function ' . $func . '() { trigger_error("' . $func . '() has been disabled for security reasons", E_USER_WARNING); }');
	}
	unset($func);
}

?>