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
 * What the authenticator says about the thing it just did.
 *
 * This is the structure both halves of WebAuthn are built around: the
 * authenticator signs it, so every claim in it is covered by the signature.
 * The layout is fixed and documented in the WebAuthn specification, section
 * 6.1.
 */
class AuthenticatorData
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Somebody was there and did something.
	 */
	public const FLAG_USER_PRESENT = 0x01;

	/**
	 * The authenticator checked who they were, with a PIN or biometrics.
	 */
	public const FLAG_USER_VERIFIED = 0x04;

	/**
	 * A new credential is described below.
	 */
	public const FLAG_ATTESTED_CREDENTIAL_DATA = 0x40;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The bytes as they arrived, which are what the signature covers.
	 */
	public string $raw;

	/**
	 * @var string
	 *
	 * SHA-256 of the relying party ID the credential belongs to.
	 */
	public string $rp_id_hash;

	/**
	 * @var int
	 *
	 * What the authenticator did, as a set of the FLAG_ constants.
	 */
	public int $flags;

	/**
	 * @var int
	 *
	 * How many times this credential has been used, or 0 if not counted.
	 */
	public int $sign_count;

	/**
	 * @var string
	 *
	 * Which model of authenticator this is. Empty unless a credential is
	 * described, and all zeroes when the authenticator would rather not say.
	 */
	public string $aaguid = '';

	/**
	 * @var string
	 *
	 * The new credential's ID. Empty unless a credential is described.
	 */
	public string $credential_id = '';

	/**
	 * @var string
	 *
	 * The new credential's public key, as a CBOR encoded COSE_Key. Empty unless
	 * a credential is described.
	 */
	public string $credential_public_key = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $data The authenticator data.
	 * @throws \SMF\WebAuthn\WebAuthnException If it cannot be read.
	 */
	public function __construct(string $data)
	{
		if (\strlen($data) < 37) {
			throw new WebAuthnException('authenticator data is only ' . \strlen($data) . ' bytes');
		}

		$this->raw = $data;
		$this->rp_id_hash = substr($data, 0, 32);
		$this->flags = \ord($data[32]);
		$this->sign_count = (int) unpack('N', substr($data, 33, 4))[1];

		if (!$this->hasAttestedCredentialData()) {
			return;
		}

		if (\strlen($data) < 55) {
			throw new WebAuthnException('authenticator data promises a credential but is too short to hold one');
		}

		$this->aaguid = substr($data, 37, 16);

		$id_length = (int) unpack('n', substr($data, 53, 2))[1];

		// The specification caps this at 1023, so anything longer is either a
		// misread or a length we have no business trusting.
		if ($id_length < 1 || $id_length > 1023 || \strlen($data) < 55 + $id_length) {
			throw new WebAuthnException('credential ID length of ' . $id_length . ' does not fit the data');
		}

		$this->credential_id = substr($data, 55, $id_length);

		/*
		 * The key runs to the end of the data unless extensions follow it, and
		 * there is no length in front of either. Decoding the key is the only
		 * way to find out where it stops, so read it and keep the bytes it used.
		 */
		$offset = 55 + $id_length;
		$start = $offset;

		Cbor::decode($data, $offset);

		$this->credential_public_key = substr($data, $start, $offset - $start);
	}

	/**
	 * Whether somebody was in front of the authenticator.
	 *
	 * @return bool Whether the user present flag is set.
	 */
	public function userPresent(): bool
	{
		return ($this->flags & self::FLAG_USER_PRESENT) !== 0;
	}

	/**
	 * Whether the authenticator checked who that somebody was.
	 *
	 * @return bool Whether the user verified flag is set.
	 */
	public function userVerified(): bool
	{
		return ($this->flags & self::FLAG_USER_VERIFIED) !== 0;
	}

	/**
	 * Whether a new credential is described.
	 *
	 * @return bool Whether the attested credential data flag is set.
	 */
	public function hasAttestedCredentialData(): bool
	{
		return ($this->flags & self::FLAG_ATTESTED_CREDENTIAL_DATA) !== 0;
	}
}
