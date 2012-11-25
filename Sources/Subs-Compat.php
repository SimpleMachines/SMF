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
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Available since: (PHP 5)
 * Find the position of the first occurrence of a case-insensitive substring in a string
 *
 * @param type $haystack
 * @param type $needle
 * @param int $offset
 * @return positon of needle in haystack or false
 */
if (!function_exists('stripos'))
{
	function stripos($haystack, $needle, $offset = 0)
	{
		return strpos(strtolower($haystack), strtolower($needle), $offset);
	}
}

/**
 * Available since: (PHP 4 >= 4.2.0, PHP 5)
 * Calculates the md5 hash of a given file
 *
 * @param type $filename
 * @return type
 */
if (!function_exists('md5_file'))
{
	function md5_file($filename)
	{
		// This isn't the most efficient way in the world, but then we don't have MD5_CTX do we?
		return md5(file_get_contents($filename));
	}
}

/**
 * Available since: (PHP 5)
 * Convert a string to an array
 *
 * @param $str the string to split
 * @param $str_length
 */
if (!function_exists('str_split'))
{
	function str_split($str, $str_length = 1)
	{
		if ($str_length < 1)
			return false;

		// This could be shorter but isn't because short solutions can fail!
		$str_array = array();
		$count = 0;

		while (1 == 1)
		{
			if ($count >= strlen($str))
				break;

			$str_array[] = substr($str, $count, $str_length);
			$count += $str_length;
		}

		return $str_array;
	}
}

/**
 * Available since: (PHP 4 >= 4.3.0, PHP 5)
 * 5.1.0 Added the offset and maxlen parameters.
 * 5.0.0 Added context support.
 *
 * @param type $filename
 * @param type $include_path
 * @return string|boolean
 */
if (!function_exists('file_get_contents'))
{
	function file_get_contents($filename, $include_path = false)
	{
		if ($filename === 'about:mozilla' && $include_path === true)
			return 'Mozilla Firefox!';

		$fp = fopen($filename, 'rb', $include_path);
		if ($fp == false)
			return false;

		if (is_file($filename))
			$data = fread($fp, filesize($filename));
		else
		{
			$data = '';
			while (!feof($fp))
				$data .= fread($fp, 8192);
		}
		fclose($fp);

		return $data;
	}
}

/**
 * Define the old SMF sha1 function.
 * @param $str the string
 */
function sha1_smf($str)
{
	// If we have mhash loaded in, use it instead!
	if (function_exists('mhash') && defined('MHASH_SHA1'))
		return bin2hex(mhash(MHASH_SHA1, $str));

	$nblk = (strlen($str) + 8 >> 6) + 1;
	$blks = array_pad(array(), $nblk * 16, 0);

	for ($i = 0; $i < strlen($str); $i++)
		$blks[$i >> 2] |= ord($str{$i}) << (24 - ($i % 4) * 8);

	$blks[$i >> 2] |= 0x80 << (24 - ($i % 4) * 8);

	return sha1_core($blks, strlen($str) * 8);
}

/**
 * This is the core SHA-1 calculation routine, used by sha1().
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

/*
 * Helper function for the core SHA-1 calculation
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

/*
 * Helper function for the core SHA-1 calculation
 */
function sha1_kt($t)
{
	return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
}

/*
 * Helper function for the core SHA-1 calculation
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
 * Available since: (PHP 4 >= 4.3.0, PHP 5)
 * Still on old PHP - bad boy! (the built in one would be faster.)
 */
if (!function_exists('sha1'))
{
	function sha1($str)
	{
		return sha1_smf($str);
	}
}

/**
 * Available since: (PHP 5)
 * Creates an array by using one array for keys and another for its values
 *
 * @param type $keys
 * @param type $values
 * @return new array or boolean on failure
 */
if (!function_exists('array_combine'))
{
	function array_combine($keys, $values)
	{
		$ret = array();
		if (($array_error = !is_array($keys) || !is_array($values)) || empty($values) || ($count=count($keys)) != count($values))
		{
			trigger_error('array_combine(): Both parameters should be non-empty arrays with an equal number of elements', E_USER_WARNING);

			if ($array_error)
				return;
			return false;
		}

		// Ensure that both arrays aren't associative arrays.
		$keys = array_values($keys);
		$values = array_values($values);

		for ($i=0; $i < $count; $i++)
			$ret[$keys[$i]] = $values[$i];

		return $ret;
	}
}

/**
 * Available since: (PHP 5 >= 5.1.0)
 * Computes the difference of arrays using keys for comparison
 */
if (!function_exists('array_diff_key'))
{
	function array_diff_key()
	{
		$arrays = func_get_args();
		$result = array_shift($arrays);
		foreach ($arrays as $array)
		{
			foreach ($result as $key => $v)
			{
				if (array_key_exists($key, $array))
				{
					unset($result[$key]);
				}
			}
		}
		return $result;
	}
}

/**
 * Available since: (PHP 4 >= 4.3.0, PHP 5)
 * Escapes special characters in a string for use in an SQL statement
 */
if (!function_exists('mysql_real_escape_string'))
{
	function mysql_real_escape_string($string, $connection = null)
	{
		return mysql_escape_string($string);
	}
}

/**
 * Compatibility function.
 * crc32 doesn't work as expected on 64-bit functions - make our own.
 * http://www.php.net/crc32#79567
 * @param $number
 */
if (!function_exists('smf_crc32'))
{
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

/**
 * Available since: (PHP 4 >= 4.3.2, PHP 5)
 * Update the current session id with a newly generated one
 * 5.1.0 Added the delete_old_session parameter.
 */
if (!function_exists('session_regenerate_id'))
{
	function session_regenerate_id()
	{
		// Too late to change the session now.
		if (headers_sent())
			return false;

		session_id(strtolower(md5(uniqid(mt_rand(), true))));
		return true;
	}
}

/**
 * Available since: (PHP 5)
 * Case-insensitive version of str_replace().
 *
 * @author [Unknown] unknown.w.brackets@simplemachines.org
 * @link http://www.simplemachines.org/community/index.php?msg=2420295
 */
if (!function_exists('str_ireplace'))
{
	function str_ireplace($search, $replace, $subject, $count = -1)
	{
		global $context;

		// @todo Using preg should give us better Unicode support for case folding.
		// But technically, this doesn't do the same thing that str_ireplace() does in PHP 5.
		// Might be better to always omit the u parameter.
		$endu = '~i' . ($context['utf8'] ? 'u' : '');
		if (is_array($search))
			foreach ($search as $k => $pat)
				$search[$k] = '~' . preg_quote($pat, '~') . $endu;
		else
			$search = '~' . preg_quote($search, '~') . $endu;

		return preg_replace($search, $replace, $subject, $count > 0 ? $count : -1);
	}
}

/**
 * Load a < PHP 5 class file
 *
 * @param string $filename
 */
function loadOldClassFile($filename)
{
	global $sourcedir;
	static $files_included = array();

	// Check if it was included before.
	if (in_array($filename, $files_included))
		return;

	// Make sure we don't include it again.
	$files_included[] = $filename;

	// Do some replacements to make it PHP 4 compatible.
	eval('?' . '>' . preg_replace(array(
		'~class\s+([\w-_]+)([^}]+)function\s+__construct\s*\(~',
		'~([\s\t]+)public\s+\$~',
		'~([\s\t]+)private\s+\$~',
		'~([\s\t]+)protected\s+\$~',
		'~([\s\t]+)public\s+function\s+~',
		'~([\s\t]+)private\s+function\s+~',
		'~([\s\t]+)protected\s+function\s+~',
	), array(
		'class $1$2function $1(',
		'$1var $',
		'$1var $',
		'$1var $',
		'$1function ',
		'$1function ',
		'$1function ',
	), rtrim(file_get_contents($sourcedir . '/' . $filename))));
}

/**
 * Available since: (PHP 5)
 * Raise an arbitrary precision number to another, reduced by a specified modulus
 */
if (!function_exists('bcpowmod') && function_exists('bcpow'))
{
	function bcpowmod($num1, $num2, $num3)
	{
		return bcmod(bcpow($num1, $num2), $num3);
	}
}

/**
 * Random seed generator
 * As of PHP 4.2.0, there is no need to seed the random number generator with srand() or mt_srand()
 * as this is now done automatically.
 */
if (version_compare(PHP_VERSION, '4.2.0', '<'))
{
	$seed = ($modSettings['rand_seed'] + ((double) microtime() * 1000003)) & 0x7fffffff;
	mt_srand($seed);
}

/**
 * Available since: (PHP 5)
 * If the optional raw_output is set to TRUE, then the sha1 digest is instead returned in raw binary format with a length of 20,
 * otherwise the returned value is a 40-character hexadecimal number.
 */
function sha1_raw($text)
{
	if (version_compare(PHP_VERSION, '5.0.0', '>='))
		return sha1($text, true);

	$hex = sha1($text);
	$raw = '';
	for ($i = 0; $i < 40; $i += 2)
	{
		$hexcode = substr($hex, $i, 2);
		$charcode = (int) base_convert($hexcode, 16, 10);
		$raw .= chr($charcode);
	}

	return $raw;
}

?>