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
 * @param int $t
 * @return int 1518500249, 1859775393, -1894007588 or -899497514 depending on the value of $t
 */
function sha1_kt($t)
{
	return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
}

/**
 * Helper function for the core SHA-1 calculation
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

if (!is_callable('random_int')) {
    /**
     * Random_* Compatibility Library
     * for using the new PHP 7 random_* API in PHP 5 projects
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

        try {
            /** @var int $min */
            $min = RandomCompat_intval($min);
        } catch (TypeError $ex) {
            throw new TypeError(
                'random_int(): $min must be an integer'
            );
        }

        try {
            /** @var int $max */
            $max = RandomCompat_intval($max);
        } catch (TypeError $ex) {
            throw new TypeError(
                'random_int(): $max must be an integer'
            );
        }

        /**
         * Now that we've verified our weak typing system has given us an integer,
         * let's validate the logic then we can move forward with generating random
         * integers along a given range.
         */
        if ($min > $max) {
            throw new Error(
                'Minimum value must be less than or equal to the maximum value'
            );
        }

        if ($max === $min) {
            return (int) $min;
        }

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
        if (!is_int($range)) {

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

        } else {

            /**
             * $bits is effectively ceil(log($range, 2)) without dealing with
             * type juggling
             */
            while ($range > 0) {
                if ($bits % 8 === 0) {
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
        do {
            /**
             * The rejection probability is at most 0.5, so this corresponds
             * to a failure probability of 2^-128 for a working RNG
             */
            if ($attempts > 128) {
                throw new Exception(
                    'random_int: RNG is broken - too many rejections'
                );
            }

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
            for ($i = 0; $i < $bytes; ++$i) {
                $val |= ord($randomByteString[$i]) << ($i * 8);
            }
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
        } while (!is_int($val) || $val > $max || $val < $min);

        return (int) $val;
    }
}


?>