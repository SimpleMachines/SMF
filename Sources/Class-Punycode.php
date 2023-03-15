<?php
/**
 * A class for encoding/decoding Punycode.
 *
 * Derived from this library: https://github.com/true/php-punycode
 *
 * @author TrueServer B.V. <support@true.nl>
 * @package php-punycode
 * @license MIT
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
 * Punycode implementation as described in RFC 3492
 *
 * @link http://tools.ietf.org/html/rfc3492
 */
class Punycode
{
	/**
	 * Bootstring parameter values
	 *
	 */
	const BASE = 36;
	const TMIN = 1;
	const TMAX = 26;
	const SKEW = 38;
	const DAMP = 700;
	const INITIAL_BIAS = 72;
	const INITIAL_N = 128;
	const PREFIX = 'xn--';
	const DELIMITER = '-';

	/**
	 * IDNA Error constants
	 */
	const IDNA_ERROR_EMPTY_LABEL = 1;
	const IDNA_ERROR_LABEL_TOO_LONG = 2;
	const IDNA_ERROR_DOMAIN_NAME_TOO_LONG = 4;
	const IDNA_ERROR_LEADING_HYPHEN = 8;
	const IDNA_ERROR_TRAILING_HYPHEN = 16;
	const IDNA_ERROR_HYPHEN_3_4 = 32;
	const IDNA_ERROR_LEADING_COMBINING_MARK = 64;
	const IDNA_ERROR_DISALLOWED = 128;
	const IDNA_ERROR_PUNYCODE = 256;
	const IDNA_ERROR_LABEL_HAS_DOT = 512;
	const IDNA_ERROR_INVALID_ACE_LABEL = 1024;
	const IDNA_ERROR_BIDI = 2048;
	const IDNA_ERROR_CONTEXTJ = 4096;

	/**
	 * Encode table
	 *
	 * @param array
	 */
	protected static $encodeTable = array(
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l',
		'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
		'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
	);

	/**
	 * Decode table
	 *
	 * @param array
	 */
	protected static $decodeTable = array(
		'a' => 0, 'b' => 1, 'c' => 2, 'd' => 3, 'e' => 4, 'f' => 5,
		'g' => 6, 'h' => 7, 'i' => 8, 'j' => 9, 'k' => 10, 'l' => 11,
		'm' => 12, 'n' => 13, 'o' => 14, 'p' => 15, 'q' => 16, 'r' => 17,
		's' => 18, 't' => 19, 'u' => 20, 'v' => 21, 'w' => 22, 'x' => 23,
		'y' => 24, 'z' => 25, '0' => 26, '1' => 27, '2' => 28, '3' => 29,
		'4' => 30, '5' => 31, '6' => 32, '7' => 33, '8' => 34, '9' => 35
	);

	/**
	 * Character encoding
	 *
	 * @param string
	 */
	protected $encoding;

	/**
	 * Whether to use Non-Transitional Processing.
	 * Setting this to true breaks backward compatibility with IDNA2003.
	 *
	 * @param bool
	 */
	protected $nonTransitional = false;

	/**
	 * Whether to use STD3 ASCII rules.
	 *
	 * @param bool
	 */
	protected $std3 = false;

	/**
	 * Constructor
	 *
	 * @param string $encoding Character encoding
	 */
	public function __construct($encoding = 'UTF-8')
	{
		$this->encoding = $encoding;
	}

	/**
	 * Enable/disable Non-Transitional Processing
	 *
	 * @param bool $nonTransitional Whether to use Non-Transitional Processing
	 */
	public function useNonTransitional(bool $nonTransitional)
	{
		$this->nonTransitional = $nonTransitional;
	}

	/**
	 * Enable/disable STD3 ASCII rules
	 *
	 * @param bool $std3 Whether to use STD3 ASCII rules
	 */
	public function useStd3(bool $std3)
	{
		$this->std3 = $std3;
	}

	/**
	 * Encode a domain to its Punycode version
	 *
	 * @param string $input Domain name in Unicode to be encoded
	 * @return string Punycode representation in ASCII
	 */
	public function encode($input)
	{
		// For compatibility with idn_to_* functions
		if ($this->decode($input) === false)
			return false;

		$errors = array();
		$preprocessed = $this->preprocess($input, $errors);

		if (!empty($errors))
		{
			return false;
		}

		$parts = explode('.', $preprocessed);
		foreach ($parts as $p => &$part) {
			$part = $this->encodePart($part);

			$validation_status = $this->validateLabel($part, true);

			switch ($validation_status) {
				case self::IDNA_ERROR_LABEL_TOO_LONG:
				case self::IDNA_ERROR_LEADING_HYPHEN:
				case self::IDNA_ERROR_TRAILING_HYPHEN:
				case self::IDNA_ERROR_LEADING_COMBINING_MARK:
				case self::IDNA_ERROR_DISALLOWED:
				case self::IDNA_ERROR_PUNYCODE:
				case self::IDNA_ERROR_LABEL_HAS_DOT:
				case self::IDNA_ERROR_INVALID_ACE_LABEL:
				case self::IDNA_ERROR_BIDI:
				case self::IDNA_ERROR_CONTEXTJ:
					return false;
					break;

				case self::IDNA_ERROR_HYPHEN_3_4:
					$part = $parts[$p];
					break;

				case self::IDNA_ERROR_EMPTY_LABEL:
					$parts_count = count($parts);
					if ($parts_count === 1 || $p !== $parts_count - 1)
						return false;
					break;

				default:
					break;
			}
		}
		$output = implode('.', $parts);

		// IDNA_ERROR_DOMAIN_NAME_TOO_LONG
		if (strlen(rtrim($output, '.')) > 253)
			return false;

		return $output;
	}

	/**
	 * Encode a part of a domain name, such as tld, to its Punycode version
	 *
	 * @param string $input Part of a domain name
	 * @return string Punycode representation of a domain part
	 */
	protected function encodePart($input)
	{
		$codePoints = $this->listCodePoints($input);

		$n = static::INITIAL_N;
		$bias = static::INITIAL_BIAS;
		$delta = 0;
		$h = $b = count($codePoints['basic']);

		$output = '';
		foreach ($codePoints['basic'] as $code) {
			$output .= $this->codePointToChar($code);
		}
		if ($input === $output) {
			return $output;
		}
		if ($b > 0) {
			$output .= static::DELIMITER;
		}

		$codePoints['nonBasic'] = array_unique($codePoints['nonBasic']);
		sort($codePoints['nonBasic']);

		$i = 0;
		$length = mb_strlen($input, $this->encoding);
		while ($h < $length) {
			$m = $codePoints['nonBasic'][$i++];
			$delta = $delta + ($m - $n) * ($h + 1);
			$n = $m;

			foreach ($codePoints['all'] as $c) {
				if ($c < $n || $c < static::INITIAL_N) {
					$delta++;
				}
				if ($c === $n) {
					$q = $delta;
					for ($k = static::BASE;; $k += static::BASE) {
						$t = $this->calculateThreshold($k, $bias);
						if ($q < $t) {
							break;
						}

						$code = $t + (((int) $q - $t) % (static::BASE - $t));
						$output .= static::$encodeTable[$code];

						$q = ($q - $t) / (static::BASE - $t);
					}

					$output .= static::$encodeTable[(int) $q];
					$bias = $this->adapt($delta, $h + 1, ($h === $b));
					$delta = 0;
					$h++;
				}
			}

			$delta++;
			$n++;
		}
		$out = static::PREFIX . $output;

		return $out;
	}

	/**
	 * Decode a Punycode domain name to its Unicode counterpart
	 *
	 * @param string $input Domain name in Punycode
	 * @return string Unicode domain name
	 */
	public function decode($input)
	{
		$errors = array();
		$preprocessed = $this->preprocess($input, $errors);

		if (!empty($errors))
		{
			return false;
		}

		$parts = explode('.', $preprocessed);
		foreach ($parts as $p => &$part)
		{
			if (strpos($part, static::PREFIX) === 0)
			{
				$part = substr($part, strlen(static::PREFIX));
				$part = $this->decodePart($part);

				if ($part === false)
					return false;
			}

			if ($this->validateLabel($part, false) !== 0)
			{
				if ($part === '')
				{
					$parts_count = count($parts);

					if ($parts_count === 1 || $p !== $parts_count - 1)
						return false;
				}
				else
					return false;
			}
		}
		$output = implode('.', $parts);

		return $output;
	}

	/**
	 * Decode a part of domain name, such as tld
	 *
	 * @param string $input Part of a domain name
	 * @return string Unicode domain part
	 */
	protected function decodePart($input)
	{
		$n = static::INITIAL_N;
		$i = 0;
		$bias = static::INITIAL_BIAS;
		$output = '';

		$pos = strrpos($input, static::DELIMITER);
		if ($pos !== false)
		{
			$output = substr($input, 0, $pos++);
		}
		else
		{
			$pos = 0;
		}

		$outputLength = strlen($output);
		$inputLength = strlen($input);
		while ($pos < $inputLength)
		{
			$oldi = $i;
			$w = 1;

			for ($k = static::BASE;; $k += static::BASE)
			{
				if (!isset($input[$pos]) || !isset(static::$decodeTable[$input[$pos]]))
					return false;

				$digit = static::$decodeTable[$input[$pos++]];
				$i = $i + ($digit * $w);
				$t = $this->calculateThreshold($k, $bias);

				if ($digit < $t)
				{
					break;
				}

				$w = $w * (static::BASE - $t);
			}

			$bias = $this->adapt($i - $oldi, ++$outputLength, ($oldi === 0));
			$n = $n + (int) ($i / $outputLength);
			$i = $i % ($outputLength);
			$output = mb_substr($output, 0, $i, $this->encoding) . $this->codePointToChar($n) . mb_substr($output, $i, $outputLength - 1, $this->encoding);

			$i++;
		}

		return $output;
	}

	/**
	 * Calculate the bias threshold to fall between TMIN and TMAX
	 *
	 * @param integer $k
	 * @param integer $bias
	 * @return integer
	 */
	protected function calculateThreshold($k, $bias)
	{
		if ($k <= $bias + static::TMIN)
		{
			return static::TMIN;
		}
		elseif ($k >= $bias + static::TMAX)
		{
			return static::TMAX;
		}
		return $k - $bias;
	}

	/**
	 * Bias adaptation
	 *
	 * @param integer $delta
	 * @param integer $numPoints
	 * @param boolean $firstTime
	 * @return integer
	 */
	protected function adapt($delta, $numPoints, $firstTime)
	{
		$delta = (int) (
			($firstTime)
			? $delta / static::DAMP
			: $delta / 2
		);
		$delta += (int) ($delta / $numPoints);

		$k = 0;
		while ($delta > ((static::BASE - static::TMIN) * static::TMAX) / 2)
		{
			$delta = (int) ($delta / (static::BASE - static::TMIN));
			$k = $k + static::BASE;
		}
		$k = $k + (int) (((static::BASE - static::TMIN + 1) * $delta) / ($delta + static::SKEW));

		return $k;
	}

	/**
	 * List code points for a given input
	 *
	 * @param string $input
	 * @return array Multi-dimension array with basic, non-basic and aggregated code points
	 */
	protected function listCodePoints($input)
	{
		$codePoints = array(
			'all' => array(),
			'basic' => array(),
			'nonBasic' => array(),
		);

		$length = mb_strlen($input, $this->encoding);
		for ($i = 0; $i < $length; $i++)
		{
			$char = mb_substr($input, $i, 1, $this->encoding);
			$code = $this->charToCodePoint($char);
			if ($code < 128)
			{
				$codePoints['all'][] = $codePoints['basic'][] = $code;
			}
			else
			{
				$codePoints['all'][] = $codePoints['nonBasic'][] = $code;
			}
		}

		return $codePoints;
	}

	/**
	 * Convert a single or multi-byte character to its code point
	 *
	 * @param string $char
	 * @return integer
	 */
	protected function charToCodePoint($char)
	{
		$code = ord($char[0]);
		if ($code < 128)
		{
			return $code;
		}
		elseif ($code < 224)
		{
			return (($code - 192) * 64) + (ord($char[1]) - 128);
		}
		elseif ($code < 240)
		{
			return (($code - 224) * 4096) + ((ord($char[1]) - 128) * 64) + (ord($char[2]) - 128);
		}
		else
		{
			return (($code - 240) * 262144) + ((ord($char[1]) - 128) * 4096) + ((ord($char[2]) - 128) * 64) + (ord($char[3]) - 128);
		}
	}

	/**
	 * Convert a code point to its single or multi-byte character
	 *
	 * @param integer $code
	 * @return string
	 */
	protected function codePointToChar($code)
	{
		if ($code <= 0x7F)
		{
			return chr($code);
		}
		elseif ($code <= 0x7FF)
		{
			return chr(($code >> 6) + 192) . chr(($code & 63) + 128);
		}
		elseif ($code <= 0xFFFF)
		{
			return chr(($code >> 12) + 224) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
		}
		else
		{
			return chr(($code >> 18) + 240) . chr((($code >> 12) & 63) + 128) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
		}
	}

	/**
	 * Prepare domain name string for Punycode processing.
	 * See https://www.unicode.org/reports/tr46/#Processing
	 *
	 * @param string $domain A domain name
	 * @param array $errors Will record any errors encountered during preprocessing
	 */
	protected function preprocess(string $domain, array &$errors = array())
	{
		global $sourcedir;

		require_once($sourcedir . '/Unicode/Idna.php');
		require_once($sourcedir . '/Subs-Charset.php');

		$regexes = idna_regex();

		if (preg_match('/[' . $regexes['disallowed'] . ($this->std3 ? $regexes['disallowed_std3'] : '') . ']/u', $domain))
			$errors[] = 'disallowed';

		$domain = preg_replace('/[' . $regexes['ignored'] . ']/u', '', $domain);

		unset($regexes);

		$maps = idna_maps();

		if (!$this->nonTransitional)
			$maps = array_merge($maps, idna_maps_deviation());

		if (!$this->std3)
			$maps = array_merge($maps, idna_maps_not_std3());

		return utf8_normalize_c(strtr($domain, $maps));
	}

	/**
	 * Validates an individual part of a domain name.
	 *
	 * @param string $label Individual part of a domain name.
	 * @param bool $toPunycode True for encoding to Punycode, false for decoding.
	 */
	protected function validateLabel(string $label, bool $toPunycode = true)
	{
		global $sourcedir;

		$length = strlen($label);

		if ($length === 0)
		{
			return self::IDNA_ERROR_EMPTY_LABEL;
		}

		if ($toPunycode)
		{
			if ($length > 63)
			{
				return self::IDNA_ERROR_LABEL_TOO_LONG;
			}

			if ($this->std3 && $length !== strspn($label, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-'))
			{
				return self::IDNA_ERROR_PUNYCODE;
			}
		}

		if (strpos($label, '-') === 0)
		{
			return self::IDNA_ERROR_LEADING_HYPHEN;
		}

		if (strrpos($label, '-') === $length - 1)
		{
			return self::IDNA_ERROR_TRAILING_HYPHEN;
		}

		if (substr($label, 2, 2) === '--')
		{
			return self::IDNA_ERROR_HYPHEN_3_4;
		}

		if (preg_match('/^\p{M}/u', $label))
		{
			return self::IDNA_ERROR_LEADING_COMBINING_MARK;
		}

		require_once($sourcedir . '/Unicode/Idna.php');
		require_once($sourcedir . '/Subs-Charset.php');

		$regexes = idna_regex();

		if (preg_match('/[' . $regexes['disallowed'] . ($this->std3 ? $regexes['disallowed_std3'] : '') . ']/u', $label))
		{
			return self::IDNA_ERROR_INVALID_ACE_LABEL;
		}

		if (!$toPunycode && $label !== utf8_normalize_kc($label))
		{
			return self::IDNA_ERROR_INVALID_ACE_LABEL;
		}

		return 0;
	}
}

?>