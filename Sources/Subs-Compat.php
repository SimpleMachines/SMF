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
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 4
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
		$blks[$i >> 2] |= ord($str{$i}) << (24 - ($i % 4) * 8);

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

/**
 * Compatibility function.
 * crc32 doesn't work as expected on 64-bit functions - make our own.
 * https://php.net/crc32#79567
 *
 * @param string $number
 * @return string The crc32 polynomial of $number
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
 * Random_* Compatibility Library
 * for using the new PHP 7 random_* API in PHP 5 projects
 *
 * @version 2.0.17
 * @released 2018-07-04
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 - 2018 Paragon Initiative Enterprises
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
if (!is_callable('random_bytes') || !is_callable('random_int'))
{
	if (!is_callable('RandomCompat_strlen'))
	{
		if (defined('MB_OVERLOAD_STRING') && ((int) ini_get('mbstring.func_overload')) & MB_OVERLOAD_STRING)
		{
			/**
			 * strlen() implementation that isn't brittle to mbstring.func_overload
			 *
			 * This version uses mb_strlen() in '8bit' mode to treat strings as raw
			 * binary rather than UTF-8, ISO-8859-1, etc
			 *
			 * @param string $binary_string
			 *
			 * @throws Exception
			 *
			 * @return int
			 */
			function RandomCompat_strlen($binary_string)
			{
				if (!is_string($binary_string))
				{
					throw new Exception(
						'RandomCompat_strlen() expects a string'
					);
				}

				return (int) mb_strlen($binary_string, '8bit');
			}
		}
		else
		{
			/**
			 * strlen() implementation that isn't brittle to mbstring.func_overload
			 *
			 * This version just used the default strlen()
			 *
			 * @param string $binary_string
			 *
			 * @throws Exception
			 *
			 * @return int
			 */
			function RandomCompat_strlen($binary_string)
			{
				if (!is_string($binary_string))
				{
					throw new Exception(
						'RandomCompat_strlen() expects a string'
					);
				}
				return (int) strlen($binary_string);
			}
		}
	}

	if (!is_callable('RandomCompat_substr'))
	{
		if (defined('MB_OVERLOAD_STRING') && ((int) ini_get('mbstring.func_overload')) & MB_OVERLOAD_STRING)
		{
			/**
			 * substr() implementation that isn't brittle to mbstring.func_overload
			 *
			 * This version uses mb_substr() in '8bit' mode to treat strings as raw
			 * binary rather than UTF-8, ISO-8859-1, etc
			 *
			 * @param string $binary_string
			 * @param int $start
			 * @param int|null $length (optional)
			 *
			 * @throws Exception
			 *
			 * @return string
			 */
			function RandomCompat_substr($binary_string, $start, $length = null)
			{
				if (!is_string($binary_string))
				{
					throw new Exception(
						'RandomCompat_substr(): First argument should be a string'
					);
				}

				if (!is_int($start))
				{
					throw new Exception(
						'RandomCompat_substr(): Second argument should be an integer'
					);
				}

				if ($length === null)
				{
					/**
					 * mb_substr($str, 0, NULL, '8bit') returns an empty string on
					 * PHP 5.3, so we have to find the length ourselves.
					 */
					/** @var int $length */
					$length = RandomCompat_strlen($binary_string) - $start;
				}
				elseif (!is_int($length))
				{
					throw new Exception(
						'RandomCompat_substr(): Third argument should be an integer, or omitted'
					);
				}

				// Consistency with PHP's behavior
				if ($start === RandomCompat_strlen($binary_string) && $length === 0)
				{
					return '';
				}

				if ($start > RandomCompat_strlen($binary_string))
				{
					return '';
				}

				return (string) mb_substr(
					(string) $binary_string,
					(int) $start,
					(int) $length,
					'8bit'
				);
			}
		}
		else
		{
			/**
			 * substr() implementation that isn't brittle to mbstring.func_overload
			 *
			 * This version just uses the default substr()
			 *
			 * @param string $binary_string
			 * @param int $start
			 * @param int|null $length (optional)
			 *
			 * @throws Exception
			 *
			 * @return string
			 */
			function RandomCompat_substr($binary_string, $start, $length = null)
			{
				if (!is_string($binary_string))
				{
					throw new Exception(
						'RandomCompat_substr(): First argument should be a string'
					);
				}

				if (!is_int($start))
				{
					throw new Exception(
						'RandomCompat_substr(): Second argument should be an integer'
					);
				}

				if ($length !== null)
				{
					if (!is_int($length))
					{
						throw new Exception(
							'RandomCompat_substr(): Third argument should be an integer, or omitted'
						);
					}

					return (string) substr(
						(string) $binary_string,
						(int) $start,
						(int) $length
					);
				}

				return (string) substr(
					(string) $binary_string,
					(int) $start
				);
			}
		}
	}

	if (!is_callable('RandomCompat_intval'))
	{
		/**
		 * Cast to an integer if we can, safely.
		 *
		 * If you pass it a float in the range (~PHP_INT_MAX, PHP_INT_MAX)
		 * (non-inclusive), it will sanely cast it to an int. If you it's equal to
		 * ~PHP_INT_MAX or PHP_INT_MAX, we let it fail as not an integer. Floats
		 * lose precision, so the <= and => operators might accidentally let a float
		 * through.
		 *
		 * @param int|float $number    The number we want to convert to an int
		 * @param bool      $fail_open Set to true to not throw an exception
		 *
		 * @return float|int
		 * @psalm-suppress InvalidReturnType
		 *
		 * @throws Exception
		 */
		function RandomCompat_intval($number, $fail_open = false)
		{
			if (is_int($number) || is_float($number))
			{
				$number += 0;
			}
			elseif (is_numeric($number))
			{
				/** @psalm-suppress InvalidOperand */
				$number += 0;
			}
			/** @var int|float $number */

			if (is_float($number) && $number > ~PHP_INT_MAX && $number < PHP_INT_MAX)
			{
				$number = (int) $number;
			}

			if (is_int($number))
			{
				return (int) $number;
			}
			elseif (!$fail_open)
			{
				throw new Exception(
					'Expected an integer.'
				);
			}

			return $number;
		}
	}
}

if (!is_callable('random_bytes'))
{
	/**
	 * Reading directly from /dev/urandom:
	 */
	if (DIRECTORY_SEPARATOR === '/')
	{
		// DIRECTORY_SEPARATOR === '/' on Unix-like OSes -- this is a fast
		// way to exclude Windows.
		/**
		 * Unless open_basedir is enabled, use /dev/urandom for
		 * random numbers in accordance with best practices
		 *
		 * Why we use /dev/urandom and not /dev/random
		 * @ref http://sockpuppet.org/blog/2014/02/25/safely-generate-random-numbers
		 *
		 * @param int $bytes
		 *
		 * @return string
		 */
		function random_bytes($bytes)
		{
			/** @var resource $fp */
			static $fp = null;

			/**
			 * This block should only be run once
			 */
			if (empty($fp))
			{
				/**
				 * We use /dev/urandom if it is a char device.
				 * We never fall back to /dev/random
				 */
				/** @var resource|bool $fp */
				$fp = fopen('/dev/urandom', 'rb');
				if (is_resource($fp))
				{
					/** @var array<string, int> $st */
					$st = fstat($fp);
					if (($st['mode'] & 0170000) !== 020000)
					{
						fclose($fp);
						$fp = false;
					}
				}

				if (is_resource($fp))
				{
					/**
					 * stream_set_read_buffer() does not exist in HHVM
					 *
					 * If we don't set the stream's read buffer to 0, PHP will
					 * internally buffer 8192 bytes, which can waste entropy
					 *
					 * stream_set_read_buffer returns 0 on success
					 */
					if (!defined('RANDOM_COMPAT_READ_BUFFER'))
						define('RANDOM_COMPAT_READ_BUFFER', 8);

					if (is_callable('stream_set_read_buffer'))
						stream_set_read_buffer($fp, RANDOM_COMPAT_READ_BUFFER);

					if (is_callable('stream_set_chunk_size'))
						stream_set_chunk_size($fp, RANDOM_COMPAT_READ_BUFFER);
				}
			}

			try
			{
				/** @var int $bytes */
				$bytes = RandomCompat_intval($bytes);
			}
			catch (Exception $ex)
			{
				log_error('random_bytes(): $bytes must be an integer');
			}

			if ($bytes < 1)
			{
				log_error('Length must be greater than 0');
			}

			/**
			 * This if() block only runs if we managed to open a file handle
			 *
			 * It does not belong in an else {} block, because the above
			 * if (empty($fp)) line is logic that should only be run once per
			 * page load.
			 */
			if (is_resource($fp))
			{
				/**
				 * @var int
				 */
				$remaining = $bytes;

				/**
				 * @var string|bool
				 */
				$buf = '';

				/**
				 * We use fread() in a loop to protect against partial reads
				 */
				do
				{
					/**
					 * @var string|bool
					 */
					$read = fread($fp, $remaining);
					if (!is_string($read))
					{
						if ($read === false)
						{
							/**
							 * We cannot safely read from the file. Exit the
							 * do-while loop and trigger the exception condition
							 *
							 * @var string|bool
							 */
							$buf = false;
							break;
						}
					}
					/**
					 * Decrease the number of bytes returned from remaining
					 */
					$remaining -= RandomCompat_strlen($read);

					/**
					 * @var string|bool
					 */
					$buf = $buf . $read;

				}
				while ($remaining > 0);

				/**
				 * Is our result valid?
				 */
				if (is_string($buf))
				{
					if (RandomCompat_strlen($buf) === $bytes)
					{
						/**
						 * Return our random entropy buffer here:
						 */
						return $buf;
					}
				}
			}

			/**
			 * If we reach here, PHP has failed us.
			 */
			log_error('Error reading from source device');
		}
	}

	/**
	 * mcrypt_create_iv()
	 *
	 * We only want to use mcypt_create_iv() if:
	 *
	 * - random_bytes() hasn't already been defined
	 * - the mcrypt extensions is loaded
	 * - One of these two conditions is true:
	 *   - We're on Windows (DIRECTORY_SEPARATOR !== '/')
	 *   - We're not on Windows and /dev/urandom is readabale
	 *     (i.e. we're not in a chroot jail)
	 * - Special case:
	 *   - If we're not on Windows, but the PHP version is between
	 *     5.6.10 and 5.6.12, we don't want to use mcrypt. It will
	 *     hang indefinitely. This is bad.
	 *   - If we're on Windows, we want to use PHP >= 5.3.7 or else
	 *     we get insufficient entropy errors.
	 */
	if (!is_callable('random_bytes') &&
		// Windows on PHP < 5.3.7 is broken, but non-Windows is not known to be.
		(DIRECTORY_SEPARATOR === '/' || PHP_VERSION_ID >= 50307)
		&&
		// Prevent this code from hanging indefinitely on non-Windows;
		// see https://bugs.php.net/bug.php?id=69833
		(
			DIRECTORY_SEPARATOR !== '/' ||
			(PHP_VERSION_ID <= 50609 || PHP_VERSION_ID >= 50613)
		)
		&& extension_loaded('mcrypt'))
	{
		/**
		 * Powered by ext/mcrypt (and thankfully NOT libmcrypt)
		 *
		 * @ref https://bugs.php.net/bug.php?id=55169
		 * @ref https://github.com/php/php-src/blob/c568ffe5171d942161fc8dda066bce844bdef676/ext/mcrypt/mcrypt.c#L1321-L1386
		 *
		 * @param int $bytes
		 *
		 * @throws Exception
		 *
		 * @return string
		 */
		function random_bytes($bytes)
		{
			try
			{
				/** @var int $bytes */
				$bytes = RandomCompat_intval($bytes);
			}
			catch (Exception $ex)
			{
				log_error('random_bytes(): $bytes must be an integer');
			}

			if ($bytes < 1)
				log_error('Length must be greater than 0');

			/** @var string|bool $buf */
			$buf = @mcrypt_create_iv((int) $bytes, (int) MCRYPT_DEV_URANDOM);

			if (is_string($buf) && RandomCompat_strlen($buf) === $bytes)
			{
				/**
				 * Return our random entropy buffer here:
				 */
				return $buf;
			}

			/**
			 * If we reach here, PHP has failed us.
			 */
			log_error('Could not gather sufficient random data');
		}
	}

	/**
	 * This is a Windows-specific fallback, for when the mcrypt extension
	 * isn't loaded.
	 */
	if (!is_callable('random_bytes') && extension_loaded('com_dotnet') && class_exists('COM'))
	{
		/**
		 * Class COM
		 *
		 * This is just a stub class.
		 */
		class com_exception extends Exception
		{

		}

		$RandomCompat_disabled_classes = preg_split(
			'#\s*,\s*#',
			strtolower(ini_get('disable_classes'))
		);

		if (!in_array('com', $RandomCompat_disabled_classes))
		{
			try
			{
				$RandomCompatCOMtest = new COM('CAPICOM.Utilities.1');
				if (method_exists($RandomCompatCOMtest, 'GetRandom'))
				{
					/**
					 * Windows with PHP < 5.3.0 will not have the function
					 * openssl_random_pseudo_bytes() available, so let's use
					 * CAPICOM to work around this deficiency.
					 *
					 * @param int $bytes
					 *
					 * @return string
					 */
					function random_bytes($bytes)
					{
						try
						{
							/** @var int $bytes */
							$bytes = RandomCompat_intval($bytes);
						}
						catch (Exception $ex)
						{
							log_error('random_bytes(): $bytes must be an integer');
						}

						if ($bytes < 1)
							log_error('Length must be greater than 0');

						/** @var string $buf */
						$buf = '';
						if (!class_exists('COM'))
							log_error('COM does not exist');

						/** @var COM $util */
						$util = new COM('CAPICOM.Utilities.1');
						$execCount = 0;

						/**
						 * Let's not let it loop forever. If we run N times and fail to
						 * get N bytes of random data, then CAPICOM has failed us.
						 */
						do
						{
							$buf .= base64_decode((string) $util->GetRandom($bytes, 0));
							if (RandomCompat_strlen($buf) >= $bytes)
							{
								/**
								 * Return our random entropy buffer here:
								 */
								return (string) RandomCompat_substr($buf, 0, $bytes);
							}
							++$execCount;

						}
						while ($execCount < $bytes);

						/**
						 * If we reach here, PHP has failed us.
						 */
						log_error('Could not gather sufficient random data');
					}
				}
			}
			catch (com_exception $e)
			{
				// Don't try to use it.
			}
		}
		$RandomCompat_disabled_classes = null;
		$RandomCompatCOMtest = null;
	}

	/**
	 * throw new Exception
	 */
	if (!is_callable('random_bytes'))
	{
		/**
		 * We don't have any more options, so let's throw an exception right now
		 * and hope the developer won't let it fail silently.
		 *
		 * @param mixed $length
		 * @psalm-suppress InvalidReturnType
		 * @throws Exception
		 * @return string
		 */
		function random_bytes($length)
		{
			unset($length); // Suppress "variable not used" warnings.
			log_error('There is no suitable CSPRNG installed on your system');
			return '';
		}
	}
}

if (!is_callable('random_int'))
{
	/**
	 * Fetch a random integer between $min and $max inclusive
	 *
	 * @param int $min
	 * @param int $max
	 *
	 * @throws Exception
	 *
	 * @return int
	 */
	function random_int($min, $max)
	{
		/**
		 * Type and input logic checks
		 *
		 * If you pass it a float in the range (~PHP_INT_MAX, PHP_INT_MAX)
		 * (non-inclusive), it will sanely cast it to an int. If you it's equal to
		 * ~PHP_INT_MAX or PHP_INT_MAX, we let it fail as not an integer. Floats
		 * lose precision, so the <= and => operators might accidentally let a float
		 * through.
		 */

		try
		{
			/** @var int $min */
			$min = RandomCompat_intval($min);
		}
		catch (Exception $ex)
		{
			log_error('random_int(): $min must be an integer');
		}

		try
		{
			/** @var int $max */
			$max = RandomCompat_intval($max);
		}
		catch (Exception $ex)
		{
			log_error('random_int(): $max must be an integer');
		}

		/**
		 * Now that we've verified our weak typing system has given us an integer,
		 * let's validate the logic then we can move forward with generating random
		 * integers along a given range.
		 */
		if ($min > $max)
			log_error('Minimum value must be less than or equal to the maximum value');

		if ($max === $min)
			return (int) $min;

		/**
		 * Initialize variables to 0
		 *
		 * We want to store:
		 * $bytes => the number of random bytes we need
		 * $mask => an integer bitmask (for use with the &) operator
		 *          so we can minimize the number of discards
		 */
		$attempts = $bits = $bytes = $mask = $valueShift = 0;
		/** @var int $attempts */
		/** @var int $bits */
		/** @var int $bytes */
		/** @var int $mask */
		/** @var int $valueShift */

		/**
		 * At this point, $range is a positive number greater than 0. It might
		 * overflow, however, if $max - $min > PHP_INT_MAX. PHP will cast it to
		 * a float and we will lose some precision.
		 *
		 * @var int|float $range
		 */
		$range = $max - $min;

		/**
		 * Test for integer overflow:
		 */
		if (!is_int($range))
		{

			/**
			 * Still safely calculate wider ranges.
			 * Provided by @CodesInChaos, @oittaa
			 *
			 * @ref https://gist.github.com/CodesInChaos/03f9ea0b58e8b2b8d435
			 *
			 * We use ~0 as a mask in this case because it generates all 1s
			 *
			 * @ref https://eval.in/400356 (32-bit)
			 * @ref http://3v4l.org/XX9r5  (64-bit)
			 */
			$bytes = PHP_INT_SIZE;
			/** @var int $mask */
			$mask = ~0;

		}
		else
		{

			/**
			 * $bits is effectively ceil(log($range, 2)) without dealing with
			 * type juggling
			 */
			while ($range > 0)
			{
				if ($bits % 8 === 0)
				{
					++$bytes;
				}
				++$bits;
				$range >>= 1;
				/** @var int $mask */
				$mask = $mask << 1 | 1;
			}
			$valueShift = $min;
		}

		/** @var int $val */
		$val = 0;
		/**
		 * Now that we have our parameters set up, let's begin generating
		 * random integers until one falls between $min and $max
		 */
		/** @psalm-suppress RedundantCondition */
		do
		{
			/**
			 * The rejection probability is at most 0.5, so this corresponds
			 * to a failure probability of 2^-128 for a working RNG
			 */
			if ($attempts > 128)
				log_error('random_int: RNG is broken - too many rejections');

			/**
			 * Let's grab the necessary number of random bytes
			 */
			$randomByteString = random_bytes($bytes);

			/**
			 * Let's turn $randomByteString into an integer
			 *
			 * This uses bitwise operators (<< and |) to build an integer
			 * out of the values extracted from ord()
			 *
			 * Example: [9F] | [6D] | [32] | [0C] =>
			 *   159 + 27904 + 3276800 + 201326592 =>
			 *   204631455
			 */
			$val &= 0;
			for ($i = 0; $i < $bytes; ++$i)
				$val |= ord($randomByteString[$i]) << ($i * 8);

			/** @var int $val */

			/**
			 * Apply mask
			 */
			$val &= $mask;
			$val += $valueShift;

			++$attempts;
			/**
			 * If $val overflows to a floating point number,
			 * ... or is larger than $max,
			 * ... or smaller than $min,
			 * then try again.
			 */

		}
		while (!is_int($val) || $val > $max || $val < $min);

		return (int) $val;
	}
}

?>