<?php

namespace TOTP;

/**
 * A class for generating the codes compatible with the Google Authenticator and similar TOTP
 * clients.
 *
 * NOTE: A lot of the logic from this class has been borrowed from this class:
 * https://www.idontplaydarts.com/wp-content/uploads/2011/07/ga.php_.txt
 *
 * @author Chris Cornutt <ccornutt@phpdeveloper.org>
 * @package GAuth
 * @license MIT
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * Class Auth
 *
 * @package TOTP
 */
class Auth
{
	/**
	 * @var array Internal lookup table
	 */
	private $lookup = array();

	/**
	 * @var string Initialization key
	 */
	private $initKey = null;

	/**
	 * @var integer Seconds between key refreshes
	 */
	private $refreshSeconds = 30;

	/**
	 * @var integer The length of codes to generate
	 */
	private $codeLength = 6;

	/**
	 * @var integer Range plus/minus for "window of opportunity" on allowed codes
	 */
	private $range = 2;

	/**
	 * Initialize the object and set up the lookup table
	 *     Optionally the Initialization key
	 *
	 * @param string $initKey Initialization key
	 */
	public function __construct($initKey = null)
	{
		$this->buildLookup();

		if ($initKey !== null)
		{
			$this->setInitKey($initKey);
		}
	}

	/**
	 * Build the base32 lookup table
	 */
	public function buildLookup()
	{
		$lookup = array_combine(
			array_merge(range('A', 'Z'), range(2, 7)),
			range(0, 31)
		);
		$this->setLookup($lookup);
	}

	/**
	 * Get the current "range" value
	 *
	 * @return integer Range value
	 */
	public function getRange()
	{
		return $this->range;
	}

	/**
	 * Set the "range" value
	 *
	 * @param integer $range Range value
	 * @return \TOTP\Auth instance
	 */
	public function setRange($range)
	{
		if (!is_numeric($range))
		{
			throw new \InvalidArgumentException('Invalid window range');
		}
		$this->range = $range;
		return $this;
	}

	/**
	 * Set the initialization key for the object
	 *
	 * @param string $key Initialization key
	 * @throws \InvalidArgumentException If hash is not valid base32
	 * @return \TOTP\Auth instance
	 */
	public function setInitKey($key)
	{
		if (preg_match('/^[' . implode('', array_keys($this->getLookup())) . ']+$/', $key) == false)
		{
			throw new \InvalidArgumentException('Invalid base32 hash!');
		}
		$this->initKey = $key;
		return $this;
	}

	/**
	 * Get the current Initialization key
	 *
	 * @return string Initialization key
	 */
	public function getInitKey()
	{
		return $this->initKey;
	}

	/**
	 * Set the contents of the internal lookup table
	 *
	 * @param array $lookup Lookup data set
	 * @throws \InvalidArgumentException If lookup given is not an array
	 * @return \TOTP\Auth instance
	 */
	public function setLookup($lookup)
	{
		if (!is_array($lookup))
		{
			throw new \InvalidArgumentException('Lookup value must be an array');
		}
		$this->lookup = $lookup;
		return $this;
	}

	/**
	 * Get the current lookup data set
	 *
	 * @return array Lookup data
	 */
	public function getLookup()
	{
		return $this->lookup;
	}

	/**
	 * Get the number of seconds for code refresh currently set
	 *
	 * @return integer Refresh in seconds
	 */
	public function getRefresh()
	{
		return $this->refreshSeconds;
	}

	/**
	 * Set the number of seconds to refresh codes
	 *
	 * @param integer $seconds Seconds to refresh
	 * @throws \InvalidArgumentException If seconds value is not numeric
	 * @return \TOTP\Auth instance
	 */
	public function setRefresh($seconds)
	{
		if (!is_numeric($seconds))
		{
			throw new \InvalidArgumentException('Seconds must be numeric');
		}
		$this->refreshSeconds = $seconds;
		return $this;
	}

	/**
	 * Get the current length for generated codes
	 *
	 * @return integer Code length
	 */
	public function getCodeLength()
	{
		return $this->codeLength;
	}

	/**
	 * Set the length of the generated codes
	 *
	 * @param integer $length Code length
	 * @return \TOTP\Auth instance
	 */
	public function setCodeLength($length)
	{
		$this->codeLength = $length;
		return $this;
	}

	/**
	 * Validate the given code
	 *
	 * @param string $code Code entered by user
	 * @param string $initKey Initialization key
	 * @param string $timestamp Timestamp for calculation
	 * @param integer $range Seconds before/after to validate hash against
	 * @throws \InvalidArgumentException If incorrect code length
	 * @return boolean Pass/fail of validation
	 */
	public function validateCode($code, $initKey = null, $timestamp = null, $range = null)
	{
		if (strlen($code) !== $this->getCodeLength())
		{
			throw new \InvalidArgumentException('Incorrect code length');
		}

		$range = ($range == null) ? $this->getRange() : $range;
		$timestamp = ($timestamp == null) ? $this->generateTimestamp() : $timestamp;
		$initKey = ($initKey == null) ? $this->getInitKey() : $initKey;

		$binary = $this->base32_decode($initKey);

		for ($time = ($timestamp - $range); $time <= ($timestamp + $range); $time++)
		{
			if ($this->generateOneTime($binary, $time) == $code)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Generate a one-time code
	 *
	 * @param string $initKey Initialization key [optional]
	 * @param string $timestamp Timestamp for calculation [optional]
	 * @return string Generated code/hash
	 */
	public function generateOneTime($initKey = null, $timestamp = null)
	{
		$initKey = ($initKey == null) ? $this->getInitKey() : $initKey;
		$timestamp = ($timestamp == null) ? $this->generateTimestamp() : $timestamp;

		$hash = hash_hmac(
			'sha1',
			pack('N*', 0) . pack('N*', $timestamp),
			$initKey,
			true
		);

		return str_pad($this->truncateHash($hash), $this->getCodeLength(), '0', STR_PAD_LEFT);
	}

	/**
	 * Generate a code/hash
	 *     Useful for making Initialization codes
	 *
	 * @param integer $length Length for the generated code
	 * @return string Generated code
	 */
	public function generateCode($length = 16)
	{
		global $smcFunc;

		$lookup = implode('', array_keys($this->getLookup()));
		$code = '';

		for ($i = 0; $i < $length; $i++)
		{
			$code .= $lookup[$smcFunc['random_int'](0, strlen($lookup) - 1)];
		}

		return $code;
	}

	/**
	 * Generate the timestamp for the calculation
	 *
	 * @return integer Timestamp
	 */
	public function generateTimestamp()
	{
		return floor(microtime(true) / $this->getRefresh());
	}

	/**
	 * Truncate the given hash down to just what we need
	 *
	 * @param string $hash Hash to truncate
	 * @return string Truncated hash value
	 */
	public function truncateHash($hash)
	{
		$offset = ord($hash[19]) & 0xf;

		return (
			((ord($hash[$offset + 0]) & 0x7f) << 24) |
			((ord($hash[$offset + 1]) & 0xff) << 16) |
			((ord($hash[$offset + 2]) & 0xff) << 8) |
			(ord($hash[$offset + 3]) & 0xff)
		) % pow(10, $this->getCodeLength());
	}

	/**
	 * Base32 decoding function
	 *
	 * @param string $hash The base32-encoded hash
	 * @throws \InvalidArgumentException When hash is not valid
	 * @return string Binary value of hash
	 */
	public function base32_decode($hash)
	{
		$lookup = $this->getLookup();

		if (preg_match('/^[' . implode('', array_keys($lookup)) . ']+$/', $hash) == false)
		{
			throw new \InvalidArgumentException('Invalid base32 hash!');
		}

		$hash = strtoupper($hash);
		$buffer = 0;
		$length = 0;
		$binary = '';

		for ($i = 0; $i < strlen($hash); $i++)
		{
			$buffer = $buffer << 5;
			$buffer += $lookup[$hash[$i]];
			$length += 5;

			if ($length >= 8)
			{
				$length -= 8;
				$binary .= chr(($buffer & (0xFF << $length)) >> $length);
			}
		}

		return $binary;
	}

	/**
	 * Returns a URL to QR code for embedding the QR code
	 *
	 * @param string $name The name
	 * @param string $code The generated code
	 * @return string The URL to the QR code
	 */
	public function getQrCodeUrl($name, $code)
	{
		$url = 'otpauth://totp/' . urlencode($name) . '?secret=' . $code;
		return $url;
	}
}

?>