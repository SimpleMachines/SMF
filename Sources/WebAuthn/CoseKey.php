<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\WebAuthn;

/**
 * A public key as an authenticator hands it over, described by RFC 8152.
 *
 * Authenticators send their public key as a COSE_Key map, and openssl wants a
 * PEM encoded SubjectPublicKeyInfo, so this converts one into the other. Only
 * the two algorithms SMF asks for are handled: ES256, which is what every
 * platform authenticator and security key produces, and RS256, which is what
 * Windows Hello produces on older machines.
 */
class CoseKey
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * ECDSA over the NIST P-256 curve with SHA-256.
	 */
	public const ALG_ES256 = -7;

	/**
	 * RSASSA-PKCS1-v1_5 with SHA-256.
	 */
	public const ALG_RS256 = -257;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * Which algorithm this key signs with, as a COSE identifier.
	 */
	public int $algorithm;

	/**
	 * @var string
	 *
	 * The key, PEM encoded, ready for openssl_verify().
	 */
	public string $pem;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $cose The COSE_Key map, still encoded as CBOR.
	 * @throws \SMF\WebAuthn\WebAuthnException If the key cannot be used.
	 */
	public function __construct(string $cose)
	{
		$key = Cbor::decodeAll($cose);

		if (!\is_array($key)) {
			throw new WebAuthnException('public key is not a COSE_Key map');
		}

		$this->algorithm = (int) ($key[3] ?? 0);

		$this->pem = match ($this->algorithm) {
			self::ALG_ES256 => self::pemFromEc2($key),
			self::ALG_RS256 => self::pemFromRsa($key),
			default => throw new WebAuthnException('unsupported key algorithm ' . $this->algorithm),
		};
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Checks a signature against a key kept when a credential was registered.
	 *
	 * Both algorithms SMF accepts sign with SHA-256 and are in the encoding
	 * openssl expects, so there is nothing to choose between here: an ECDSA
	 * signature arrives ASN.1 encoded and an RSA one as PKCS#1 v1.5, which is
	 * what openssl_verify() reads in either case.
	 *
	 * @param string $pem The PEM encoded public key.
	 * @param string $data What was signed.
	 * @param string $signature The signature over it.
	 * @throws \SMF\WebAuthn\WebAuthnException If the signature is not good.
	 */
	public static function checkSignature(string $pem, string $data, string $signature): void
	{
		if (openssl_verify($data, $signature, $pem, \OPENSSL_ALGO_SHA256) !== 1) {
			throw new WebAuthnException('signature does not match the stored key');
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Builds a PEM public key from a COSE elliptic curve key.
	 *
	 * @param array $key The decoded COSE_Key map.
	 * @throws \SMF\WebAuthn\WebAuthnException If the key cannot be used.
	 * @return string The PEM encoded key.
	 */
	protected static function pemFromEc2(array $key): string
	{
		// Curve 1 is P-256. ES256 is only ever defined over that one, so a key
		// claiming anything else is not the key it says it is.
		if ((int) ($key[-1] ?? 0) !== 1) {
			throw new WebAuthnException('ES256 key is not on the P-256 curve');
		}

		$x = (string) ($key[-2] ?? '');
		$y = (string) ($key[-3] ?? '');

		if (\strlen($x) !== 32 || \strlen($y) !== 32) {
			throw new WebAuthnException('ES256 key coordinates are the wrong length');
		}

		/*
		 * The algorithm identifier for an uncompressed P-256 point never varies,
		 * so it goes in as the constant it is rather than being assembled from
		 * object identifiers we would only ever build one way.
		 */
		$algorithm = "\x30\x13\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";

		return self::pem(
			self::der(0x30, $algorithm . self::der(0x03, "\x00\x04" . $x . $y)),
		);
	}

	/**
	 * Builds a PEM public key from a COSE RSA key.
	 *
	 * @param array $key The decoded COSE_Key map.
	 * @throws \SMF\WebAuthn\WebAuthnException If the key cannot be used.
	 * @return string The PEM encoded key.
	 */
	protected static function pemFromRsa(array $key): string
	{
		$modulus = (string) ($key[-1] ?? '');
		$exponent = (string) ($key[-2] ?? '');

		if ($modulus === '' || $exponent === '') {
			throw new WebAuthnException('RS256 key is missing its modulus or exponent');
		}

		// rsaEncryption, with the NULL parameters PKCS#1 asks for.
		$algorithm = "\x30\x0D\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00";

		$public_key = self::der(
			0x30,
			self::integer($modulus) . self::integer($exponent),
		);

		return self::pem(
			self::der(0x30, $algorithm . self::der(0x03, "\x00" . $public_key)),
		);
	}

	/**
	 * Wraps content in a DER tag and length.
	 *
	 * @param int $tag Which tag to use.
	 * @param string $content What to wrap.
	 * @return string The tagged content.
	 */
	protected static function der(int $tag, string $content): string
	{
		$length = \strlen($content);

		if ($length < 128) {
			$header = \chr($length);
		} else {
			$bytes = ltrim(pack('N', $length), "\x00");
			$header = \chr(0x80 | \strlen($bytes)) . $bytes;
		}

		return \chr($tag) . $header . $content;
	}

	/**
	 * Encodes a big number as a DER integer.
	 *
	 * @param string $bytes The number, most significant byte first.
	 * @return string The DER integer.
	 */
	protected static function integer(string $bytes): string
	{
		$bytes = ltrim($bytes, "\x00");

		if ($bytes === '') {
			$bytes = "\x00";
		}

		// DER integers are signed, so a leading byte of 0x80 or above would read
		// as a negative number without a zero in front of it.
		if ((\ord($bytes[0]) & 0x80) !== 0) {
			$bytes = "\x00" . $bytes;
		}

		return self::der(0x02, $bytes);
	}

	/**
	 * Wraps DER in the armour openssl expects.
	 *
	 * @param string $der The encoded key.
	 * @return string The PEM encoded key.
	 */
	protected static function pem(string $der): string
	{
		return "-----BEGIN PUBLIC KEY-----\n"
			. chunk_split(base64_encode($der), 64, "\n")
			. "-----END PUBLIC KEY-----\n";
	}
}
