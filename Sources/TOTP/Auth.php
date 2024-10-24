<?php

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
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\TOTP;

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
	private array $lookup = [];

	/**
	 * @var ?string Initialization key
	 */
	private ?string $initKey = null;

	/**
	 * @var int Seconds between key refreshes
	 */
	private int $refreshSeconds = 30;

	/**
	 * @var int The length of codes to generate
	 */
	private int $codeLength = 6;

	/**
	 * @var int Range plus/minus for "window of opportunity" on allowed codes
	 */
	private int $range = 2;

	/**
	 * Initialize the object and set up the lookup table
	 *     Optionally the Initialization key
	 *
	 * @param string $initKey Initialization key
	 */
	public function __construct(?string $initKey = null)
	{
		$this->buildLookup();

		if ($initKey !== null) {
			$this->setInitKey($initKey);
		}
	}

	/**
	 * Build the base32 lookup table
	 */
	public function buildLookup(): void
	{
		$lookup = array_combine(
			array_merge(range('A', 'Z'), range(2, 7)),
			range(0, 31),
		);
		$this->setLookup($lookup);
	}

	/**
	 * Get the current "range" value
	 *
	 * @return int Range value
	 */
	public function getRange(): int
	{
		return $this->range;
	}

	/**
	 * Set the "range" value
	 *
	 * @param int $range Range value
	 * @return \SMF\TOTP\Auth instance
	 */
	public function setRange(int $range): self
	{
		if (!is_numeric($range)) {
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
	 * @return \SMF\TOTP\Auth instance
	 */
	public function setInitKey(string $key): self
	{
		if (preg_match('/^[' . implode('', array_keys($this->getLookup())) . ']+$/', $key) == false) {
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
	public function getInitKey(): string
	{
		return $this->initKey;
	}

	/**
	 * Set the contents of the internal lookup table
	 *
	 * @param array $lookup Lookup data set
	 * @throws \InvalidArgumentException If lookup given is not an array
	 * @return \SMF\TOTP\Auth instance
	 */
	public function setLookup(array $lookup): self
	{
		if (!\is_array($lookup)) {
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
	public function getLookup(): array
	{
		return $this->lookup;
	}

	/**
	 * Get the number of seconds for code refresh currently set
	 *
	 * @return int Refresh in seconds
	 */
	public function getRefresh(): int
	{
		return $this->refreshSeconds;
	}

	/**
	 * Set the number of seconds to refresh codes
	 *
	 * @param int $seconds Seconds to refresh
	 * @throws \InvalidArgumentException If seconds value is not numeric
	 * @return \SMF\TOTP\Auth instance
	 */
	public function setRefresh(int $seconds): self
	{
		if (!is_numeric($seconds)) {
			throw new \InvalidArgumentException('Seconds must be numeric');
		}
		$this->refreshSeconds = $seconds;

		return $this;
	}

	/**
	 * Get the current length for generated codes
	 *
	 * @return int Code length
	 */
	public function getCodeLength(): int
	{
		return $this->codeLength;
	}

	/**
	 * Set the length of the generated codes
	 *
	 * @param int $length Code length
	 * @return \SMF\TOTP\Auth instance
	 */
	public function setCodeLength(int $length): self
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
	 * @param int $range Seconds before/after to validate hash against
	 * @throws \InvalidArgumentException If incorrect code length
	 * @return bool Pass/fail of validation
	 */
	public function validateCode(string $code, ?string $initKey = null, ?string $timestamp = null, ?string $range = null): bool
	{
		if (\strlen($code) !== $this->getCodeLength()) {
			throw new \InvalidArgumentException('Incorrect code length');
		}

		$range = ($range == null) ? $this->getRange() : $range;
		$timestamp = ($timestamp == null) ? $this->generateTimestamp() : $timestamp;
		$initKey = ($initKey == null) ? $this->getInitKey() : $initKey;

		$binary = $this->base32_decode($initKey);

		for ($time = ($timestamp - $range); $time <= ($timestamp + $range); $time++) {
			if ($this->generateOneTime($binary, (string) $time) == $code) {
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
	public function generateOneTime(?string $initKey = null, ?string $timestamp = null): string
	{
		$initKey = ($initKey == null) ? $this->getInitKey() : $initKey;
		$timestamp = ($timestamp == null) ? $this->generateTimestamp() : $timestamp;

		$hash = hash_hmac(
			'sha1',
			pack('N*', 0) . pack('N*', $timestamp),
			$initKey,
			true,
		);

		return str_pad($this->truncateHash($hash), $this->getCodeLength(), '0', STR_PAD_LEFT);
	}

	/**
	 * Generate a code/hash
	 *     Useful for making Initialization codes
	 *
	 * @param int $length Length for the generated code
	 * @return string Generated code
	 */
	public function generateCode(int $length = 16): string
	{
		$lookup = implode('', array_keys($this->getLookup()));
		$code = '';

		for ($i = 0; $i < $length; $i++) {
			$code .= $lookup[random_int(0, \strlen($lookup) - 1)];
		}

		return $code;
	}

	/**
	 * Generate the timestamp for the calculation
	 *
	 * @return int Timestamp
	 */
	public function generateTimestamp(): int
	{
		return (int) floor(microtime(true) / $this->getRefresh());
	}

	/**
	 * Truncate the given hash down to just what we need
	 *
	 * @param string $hash Hash to truncate
	 * @return string Truncated hash value
	 */
	public function truncateHash(string $hash): string
	{
		$offset = \ord($hash[19]) & 0xf;

		return (string) ((
			((\ord($hash[$offset + 0]) & 0x7f) << 24) |
			((\ord($hash[$offset + 1]) & 0xff) << 16) |
			((\ord($hash[$offset + 2]) & 0xff) << 8) |
			(\ord($hash[$offset + 3]) & 0xff)
		) % pow(10, $this->getCodeLength()));
	}

	/**
	 * Base32 decoding function
	 *
	 * @param string $hash The base32-encoded hash
	 * @throws \InvalidArgumentException When hash is not valid
	 * @return string Binary value of hash
	 */
	public function base32_decode(string $hash): string
	{
		$lookup = $this->getLookup();

		if (preg_match('/^[' . implode('', array_keys($lookup)) . ']+$/', $hash) == false) {
			throw new \InvalidArgumentException('Invalid base32 hash!');
		}

		$hash = strtoupper($hash);
		$buffer = 0;
		$length = 0;
		$binary = '';

		for ($i = 0; $i < \strlen($hash); $i++) {
			$buffer = $buffer << 5;
			$buffer += $lookup[$hash[$i]];
			$length += 5;

			if ($length >= 8) {
				$length -= 8;
				$binary .= \chr(($buffer & (0xFF << $length)) >> $length);
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
	public function getQrCodeUrl(string $name, string $code): string
	{
		$url = 'otpauth://totp/' . urlencode($name) . '?secret=' . $code;

		return $url;
	}
}

?>