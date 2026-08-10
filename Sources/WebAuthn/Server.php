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

use SMF\Config;
use SMF\Url;
use SMF\Utils;

/**
 * The forum's half of a WebAuthn ceremony.
 *
 * A passkey is a key pair the member's device made and kept. Registering one
 * means being handed the public half; signing in with one means being handed a
 * signature and checking it against the half we kept. Both start here, with a
 * challenge we invent, and finish here, where everything that came back is
 * checked before it is believed.
 *
 * Attestation is deliberately neither asked for nor examined. It says what make
 * and model of authenticator produced a credential, which only matters to a
 * site that wants to allow some and refuse others; checking it means shipping a
 * list of trusted manufacturers and keeping it current, and getting it wrong
 * locks out honest members. None of the security of signing in rests on it -- a
 * credential is bound to this forum by the browser whether we look or not.
 */
class Server
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * How many bytes of randomness go into a challenge.
	 */
	public const CHALLENGE_LENGTH = 32;

	/**
	 * How long a member has to answer one, in seconds.
	 */
	public const CHALLENGE_LIFETIME = 300;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Whether this forum can do any of this at all.
	 *
	 * @return bool Whether passkeys are available.
	 */
	public static function isAvailable(): bool
	{
		/*
		 * openssl is not a hard requirement of SMF, and passkeys cannot be
		 * verified without it. Rather than raising what SMF needs everywhere for
		 * the sake of one feature, the feature is simply not offered when the
		 * extension is missing.
		 */
		if (!\extension_loaded('openssl')) {
			return false;
		}

		return self::relyingPartyId() !== '';
	}

	/**
	 * Whether the admin has turned passkeys on.
	 *
	 * @return bool Whether members may use them.
	 */
	public static function isEnabled(): bool
	{
		return !empty(Config::$modSettings['webauthn_enabled']) && self::isAvailable();
	}

	/**
	 * The domain credentials are tied to.
	 *
	 * Every credential is bound to this, permanently: change the forum's domain
	 * and every passkey registered under the old one stops working, with nothing
	 * that can be done about it from this end.
	 *
	 * @return string The relying party ID, or an empty string if there is none.
	 */
	public static function relyingPartyId(): string
	{
		/*
		 * Url::parse() unsets the components the URL does not have, rather than
		 * leaving them null, so every one of these has to be reached for with
		 * ?? or it throws on a typed property that was never initialised.
		 */
		return (string) ((new Url(Config::$boardurl))->host ?? '');
	}

	/**
	 * The origin a ceremony has to have come from.
	 *
	 * @return string The forum's origin, as a browser reports it.
	 */
	public static function origin(): string
	{
		$url = new Url(Config::$boardurl);

		$scheme = (string) ($url->scheme ?? '');
		$port = $url->port ?? null;

		$origin = $scheme . '://' . ($url->host ?? '');

		// A browser leaves the port out when it is the scheme's usual one.
		if (
			$port !== null
			&& !($scheme === 'http' && $port === 80)
			&& !($scheme === 'https' && $port === 443)
		) {
			$origin .= ':' . $port;
		}

		return $origin;
	}

	/**
	 * Builds what the browser needs to create a credential.
	 *
	 * The challenge is remembered in the session, since the answer has to be
	 * matched against the question we actually asked and nothing else.
	 *
	 * @param int $id_member Who is registering, or 0 if they have no account yet.
	 * @param string $username What to call them on their authenticator.
	 * @param string $display_name What to show them there.
	 * @param array $exclude Credential IDs they have already registered.
	 * @param ?string $handle How to identify them to their authenticator, for
	 *    when self::userHandle() cannot say: either because there is no member
	 *    to derive it from yet, or because they already have a handle that this
	 *    credential should join rather than sit beside.
	 * @return array The options, ready to be sent as JSON.
	 */
	public static function creationOptions(int $id_member, string $username, string $display_name, array $exclude = [], ?string $handle = null): array
	{
		$challenge = random_bytes(self::CHALLENGE_LENGTH);
		$handle ??= self::userHandle($id_member);

		$_SESSION['webauthn_register'] = [
			'challenge' => $challenge,
			'member' => $id_member,
			'handle' => $handle,
			'created' => time(),
		];

		return [
			'challenge' => self::base64UrlEncode($challenge),
			'rp' => [
				'id' => self::relyingPartyId(),
				'name' => Config::$mbname,
			],
			'user' => [
				'id' => self::base64UrlEncode($handle),
				'name' => $username,
				'displayName' => $display_name,
			],
			// Both of the algorithms CoseKey understands, best first. Offering
			// anything else would mean accepting a key we cannot check.
			'pubKeyCredParams' => [
				['type' => 'public-key', 'alg' => CoseKey::ALG_ES256],
				['type' => 'public-key', 'alg' => CoseKey::ALG_RS256],
			],
			'timeout' => self::CHALLENGE_LIFETIME * 1000,
			'attestation' => 'none',
			'authenticatorSelection' => [
				/*
				 * A discoverable credential is the whole point: it is what lets
				 * the browser offer the passkey before anybody has said who they
				 * are, which is what makes signing in without a password work.
				 * An authenticator too small to store one will refuse here, and
				 * that is a clearer answer than a passkey that silently cannot
				 * be used to log in.
				 */
				'residentKey' => 'required',
				'requireResidentKey' => true,
				'userVerification' => self::requiresUserVerification() ? 'required' : 'preferred',
			],
			// So the same authenticator cannot be enrolled twice over.
			'excludeCredentials' => array_map(
				fn($id) => ['type' => 'public-key', 'id' => $id],
				$exclude,
			),
		];
	}

	/**
	 * Checks a newly created credential and pulls out what is worth keeping.
	 *
	 * @param array $response What the browser sent back.
	 * @throws \SMF\WebAuthn\WebAuthnException If anything is not as it should be.
	 * @return array The credential: its ID, key, sign count, authenticator, and
	 *    who we were talking to when we asked for it.
	 */
	public static function verifyCreation(array $response): array
	{
		$expected = self::takeChallenge('webauthn_register');

		$attestation = Cbor::decodeAll(self::decodeField($response, 'attestationObject'));

		if (!\is_array($attestation) || !isset($attestation['authData'])) {
			throw new WebAuthnException('attestation object has no authenticator data');
		}

		$data = new AuthenticatorData((string) $attestation['authData']);

		self::checkClientData($response, 'webauthn.create', $expected['challenge']);
		self::checkAuthenticatorData($data);

		if (!$data->hasAttestedCredentialData()) {
			throw new WebAuthnException('registration returned no credential');
		}

		// Throws unless it is a key we can verify signatures with later.
		$key = new CoseKey($data->credential_public_key);

		return [
			'id' => $data->credential_id,
			'key' => $key->pem,
			'algorithm' => $key->algorithm,
			'sign_count' => $data->sign_count,
			'aaguid' => bin2hex($data->aaguid),
			'user_verified' => $data->userVerified(),
			'member' => (int) $expected['member'],
			'user_handle' => (string) ($expected['handle'] ?? ''),
		];
	}

	/**
	 * Builds what the browser needs to sign in.
	 *
	 * @param array $allowed Credential IDs to accept, or none for any of them.
	 * @return array The options, ready to be sent as JSON.
	 */
	public static function requestOptions(array $allowed = []): array
	{
		$challenge = random_bytes(self::CHALLENGE_LENGTH);

		$_SESSION['webauthn_login'] = [
			'challenge' => $challenge,
			'created' => time(),
		];

		return [
			'challenge' => self::base64UrlEncode($challenge),
			'rpId' => self::relyingPartyId(),
			'timeout' => self::CHALLENGE_LIFETIME * 1000,
			'userVerification' => self::requiresUserVerification() ? 'required' : 'preferred',
			'allowCredentials' => array_map(
				fn($id) => ['type' => 'public-key', 'id' => $id],
				$allowed,
			),
		];
	}

	/**
	 * Checks an assertion against the key we kept when it was registered.
	 *
	 * @param array $response What the browser sent back.
	 * @param string $pem The credential's public key.
	 * @param int $sign_count What the sign count was last time.
	 * @throws \SMF\WebAuthn\WebAuthnException If anything is not as it should be.
	 * @return array What was learned: the new sign count, and whether the
	 *    authenticator verified who was using it.
	 */
	public static function verifyAssertion(array $response, string $pem, int $sign_count): array
	{
		$expected = self::takeChallenge('webauthn_login');

		$data = new AuthenticatorData(self::decodeField($response, 'authenticatorData'));

		$client_data = self::decodeField($response, 'clientDataJSON');

		self::checkClientData($response, 'webauthn.get', $expected['challenge']);
		self::checkAuthenticatorData($data);

		// The signature covers the authenticator data with the hash of the
		// client data stuck on the end, and nothing else.
		CoseKey::checkSignature(
			$pem,
			$data->raw . hash('sha256', $client_data, true),
			self::decodeField($response, 'signature'),
		);

		return [
			'sign_count' => $data->sign_count,
			'user_verified' => $data->userVerified(),
		];
	}

	/**
	 * Whether a sign count says the credential has been copied.
	 *
	 * An authenticator that counts its signatures is meant to count up. One that
	 * does not count at all reports zero every time, which is allowed and says
	 * nothing either way.
	 *
	 * @param int $stored What it was last time.
	 * @param int $received What it is now.
	 * @return bool Whether this looks like a cloned credential.
	 */
	public static function signCountWentBackwards(int $stored, int $received): bool
	{
		return ($stored !== 0 || $received !== 0) && $received <= $stored;
	}

	/**
	 * How the member is identified to their own authenticator.
	 *
	 * Nothing looks a credential up by this: an assertion is matched on the
	 * credential's own ID, which is what the signature is bound to. It exists
	 * because browsers group passkeys by it, so that a member with two of them
	 * sees one account rather than two.
	 *
	 * It is derived rather than stored so that nothing about the member leaks
	 * into it. The specification is explicit that this must not be something
	 * like a username or an email address, since authenticators may show it and
	 * it is readable by anyone holding the device.
	 *
	 * A credential made before the account existed cannot use this, because
	 * there was no ID to derive it from; that one carries a random handle
	 * instead, which every later credential for the same member then reuses so
	 * that the browser still sees one account rather than several.
	 *
	 * @param int $id_member The member.
	 * @return string 32 opaque bytes.
	 */
	public static function userHandle(int $id_member): string
	{
		return hash_hmac('sha256', 'webauthn-user:' . $id_member, Config::getAuthSecret(), true);
	}

	/**
	 * Encodes bytes the way WebAuthn passes them around.
	 *
	 * @param string $data The bytes.
	 * @return string The base64url encoding, unpadded.
	 */
	public static function base64UrlEncode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Decodes what the browser sent.
	 *
	 * @param string $data The base64url encoding, padded or not.
	 * @return string The bytes, or an empty string if it was not base64url.
	 */
	public static function base64UrlDecode(string $data): string
	{
		return (string) base64_decode(strtr($data, '-_', '+/'), true);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Whether the authenticator has to check who is using it.
	 *
	 * The setting is the way round it is so that the safe answer is the one an
	 * install gets without anybody choosing it: a passkey stands in for a
	 * password here, and a passkey that proves only that somebody was holding
	 * the device is a weaker thing than the password it replaced.
	 *
	 * @return bool Whether user verification is required.
	 */
	protected static function requiresUserVerification(): bool
	{
		return empty(Config::$modSettings['webauthn_allow_unverified']);
	}

	/**
	 * Takes the challenge we set, and makes sure it cannot be used twice.
	 *
	 * @param string $key Which session entry holds it.
	 * @throws \SMF\WebAuthn\WebAuthnException If there is no usable challenge.
	 * @return array The stored entry.
	 */
	protected static function takeChallenge(string $key): array
	{
		$stored = $_SESSION[$key] ?? null;

		// Whatever happens next, this challenge is spent. Clearing it first
		// means an answer that fails half way through cannot be tried again.
		unset($_SESSION[$key]);

		if (!\is_array($stored) || empty($stored['challenge'])) {
			throw new WebAuthnException('no challenge was issued for this ' . $key);
		}

		if ($stored['created'] < time() - self::CHALLENGE_LIFETIME) {
			throw new WebAuthnException('challenge expired ' . (time() - $stored['created']) . ' seconds ago');
		}

		return $stored;
	}

	/**
	 * Checks the part of the answer the browser wrote.
	 *
	 * @param array $response What the browser sent back.
	 * @param string $type Which ceremony this is meant to be.
	 * @param string $challenge What we asked.
	 * @throws \SMF\WebAuthn\WebAuthnException If anything is not as it should be.
	 */
	protected static function checkClientData(array $response, string $type, string $challenge): void
	{
		$client_data = Utils::jsonDecode(self::decodeField($response, 'clientDataJSON'), true);

		if (!\is_array($client_data)) {
			throw new WebAuthnException('client data is not JSON');
		}

		if (($client_data['type'] ?? '') !== $type) {
			// A registration cannot be passed off as a sign in, or the reverse.
			throw new WebAuthnException('client data is for ' . ($client_data['type'] ?? 'nothing') . ', not ' . $type);
		}

		if (!hash_equals($challenge, self::base64UrlDecode((string) ($client_data['challenge'] ?? '')))) {
			throw new WebAuthnException('client data answers a different challenge');
		}

		/*
		 * This is the check that makes a passkey unphishable: the browser writes
		 * the origin itself, and a site pretending to be this one cannot make it
		 * write ours.
		 */
		if (($client_data['origin'] ?? '') !== self::origin()) {
			throw new WebAuthnException('ceremony came from ' . ($client_data['origin'] ?? 'nowhere') . ', not ' . self::origin());
		}

		if (!empty($client_data['crossOrigin'])) {
			throw new WebAuthnException('ceremony was performed in a frame on another site');
		}
	}

	/**
	 * Checks the part of the answer the authenticator signed.
	 *
	 * @param \SMF\WebAuthn\AuthenticatorData $data The authenticator data.
	 * @throws \SMF\WebAuthn\WebAuthnException If anything is not as it should be.
	 */
	protected static function checkAuthenticatorData(AuthenticatorData $data): void
	{
		if (!hash_equals(hash('sha256', self::relyingPartyId(), true), $data->rp_id_hash)) {
			throw new WebAuthnException('credential belongs to another domain');
		}

		if (!$data->userPresent()) {
			throw new WebAuthnException('nobody was present at the authenticator');
		}

		if (self::requiresUserVerification() && !$data->userVerified()) {
			throw new WebAuthnException('authenticator did not verify who was using it');
		}
	}

	/**
	 * Pulls one base64url field out of what the browser sent.
	 *
	 * @param array $response What the browser sent back.
	 * @param string $field Which field to read.
	 * @throws \SMF\WebAuthn\WebAuthnException If it is missing or not base64url.
	 * @return string The decoded bytes.
	 */
	protected static function decodeField(array $response, string $field): string
	{
		$encoded = $response[$field] ?? '';

		if (!\is_string($encoded) || $encoded === '') {
			throw new WebAuthnException('response has no ' . $field);
		}

		$decoded = self::base64UrlDecode($encoded);

		if ($decoded === '') {
			throw new WebAuthnException($field . ' is not base64url');
		}

		return $decoded;
	}
}
