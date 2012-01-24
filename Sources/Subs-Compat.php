<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file provides compatibility functions and code for older versions of
	PHP, such as the sha1() function.  It is only included for those older
	versions.
*/

if (!function_exists('stripos'))
{
	function stripos($haystack, $needle, $offset = 0)
	{
		return strpos(strtolower($haystack), strtolower($needle), $offset);
	}
}

if (!function_exists('md5_file'))
{
	function md5_file($filename)
	{
		// This isn't the most efficient way in the world, but then we don't have MD5_CTX do we?
		return md5(file_get_contents($filename));
	}
}

// Split a string into an array.
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

// Define the old SMF sha1 function.
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

// This is the core SHA-1 calculation routine, used by sha1().
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

function sha1_kt($t)
{
	return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
}

function sha1_rol($num, $cnt)
{
	// Unfortunately, PHP uses unsigned 32-bit longs only.  So we have to kludge it a bit.
	if ($num & 0x80000000)
		$a = ($num >> 1 & 0x7fffffff) >> (31 - $cnt);
	else
		$a = $num >> (32 - $cnt);

	return ($num << $cnt) | $a;
}

// Still on old PHP - bad boy! (the built in one would be faster.)
if (!function_exists('sha1'))
{
	function sha1($str)
	{
		return sha1_smf($str);
	}
}

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

if (!function_exists('mysql_real_escape_string'))
{
	function mysql_real_escape_string($string, $connection = null)
	{
		return mysql_escape_string($string);
	}
}

?>