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

namespace SMF\Authentication;

use SMF\IP;
use SMF\Url;
use SMF\Utils;

/**
 * Speaks OpenID Connect to one identity provider.
 *
 * Authorization code flow with PKCE, which is what every provider supports and
 * the only one appropriate for a server that can keep a secret.
 *
 * On not checking the ID token signature: the token is read from the response
 * to our own back channel POST to the token endpoint, made over TLS with the
 * certificate verified and the client authenticated. OpenID Connect Core
 * section 3.1.3.7 item 6 allows skipping signature validation in exactly that
 * case, which is why the certificate check in fetch() is not optional and why
 * the token is never read from the redirect. Take either of those away and the
 * signature
 * would have to be checked, which would mean JWKS handling and a hard
 * dependency on openssl that SMF does not currently have.
 */
class OidcClient
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Why the last call failed, for the error log.
	 */
	public string $error = '';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var \SMF\Authentication\Provider
	 *
	 * The provider we are talking to.
	 */
	protected Provider $provider;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param \SMF\Authentication\Provider $provider The provider to talk to.
	 */
	public function __construct(Provider $provider)
	{
		$this->provider = $provider;
	}

	/**
	 * Fetches, and caches, the provider's discovery document.
	 *
	 * @param bool $force Whether to refetch even if we have one.
	 * @return array The document, or an empty array if it could not be had.
	 */
	public function discover(bool $force = false): array
	{
		$cached = $this->provider->settings['discovery'] ?? [];

		// A day is long enough to notice a provider moving an endpoint, and
		// short enough not to hammer them on every login.
		if (
			!$force
			&& $cached !== []
			&& ($this->provider->settings['discovered_at'] ?? 0) > time() - 86400
		) {
			return $cached;
		}

		$url = rtrim($this->provider->issuer, '/') . '/.well-known/openid-configuration';
		$body = $this->fetch($url);

		if ($body === null) {
			// Stale endpoints beat no endpoints if the provider is briefly down.
			return $cached;
		}

		$document = Utils::jsonDecode($body, true);

		if (!\is_array($document) || empty($document['authorization_endpoint']) || empty($document['token_endpoint'])) {
			$this->error = 'discovery document from ' . $url . ' is missing its endpoints';

			return $cached;
		}

		// The issuer has to agree with where we looked, or we are being told
		// about somebody else's endpoints.
		if (rtrim($document['issuer'] ?? '', '/') !== rtrim($this->provider->issuer, '/')) {
			$this->error = 'discovery issuer ' . ($document['issuer'] ?? '(none)') . ' does not match ' . $this->provider->issuer;

			return $cached;
		}

		$this->provider->settings['discovery'] = $document;
		$this->provider->settings['discovered_at'] = time();
		$this->provider->save();

		return $document;
	}

	/**
	 * Builds the URL to send the member to, and the state to remember.
	 *
	 * @param string $return_to Where to put them once they are back.
	 * @param bool $force_login Whether the provider must challenge them again
	 *    rather than answering out of a session it already has.
	 * @return ?array The 'url' to send them to and the 'state' to stash in the
	 *    session, or null if we could not work out where to send them.
	 */
	public function beginAuthorization(string $return_to = '', bool $force_login = false): ?array
	{
		$document = $this->discover();

		if (empty($document['authorization_endpoint'])) {
			return null;
		}

		// The verifier never leaves this server; only its hash goes out, so an
		// intercepted authorization code cannot be redeemed by anyone else.
		$verifier = self::base64UrlEncode(random_bytes(32));

		$state = [
			'provider' => $this->provider->id,
			'state' => bin2hex(random_bytes(16)),
			'nonce' => bin2hex(random_bytes(16)),
			'verifier' => $verifier,
			'return_to' => $return_to,
			'created' => time(),
		];

		$query = [
			'response_type' => 'code',
			'client_id' => $this->provider->client_id,
			'redirect_uri' => $this->provider->redirectUri(),
			'scope' => $this->provider->scopes,
			'state' => $state['state'],
			'nonce' => $state['nonce'],
			'code_challenge' => self::base64UrlEncode(hash('sha256', $verifier, true)),
			'code_challenge_method' => 'S256',
		];

		/*
		 * Asking somebody to prove who they are is worthless if the provider
		 * answers out of the session it already has: they would prove only that
		 * they signed in there at some point, which we knew. This is the request
		 * that says "challenge them again, now" (OIDC Core 3.1.2.1).
		 */
		if ($force_login) {
			$query['prompt'] = 'login';
			$query['max_age'] = 0;
		}

		return [
			'url' => $document['authorization_endpoint']
				. (str_contains($document['authorization_endpoint'], '?') ? '&' : '?')
				. http_build_query($query, '', '&'),
			'state' => $state,
		];
	}

	/**
	 * Trades the authorization code for tokens, and returns the claims.
	 *
	 * @param string $code The code the provider sent back.
	 * @param array $state What beginAuthorization() stashed in the session.
	 * @return ?array The claims about the member, or null if anything is off.
	 */
	public function completeAuthorization(string $code, array $state): ?array
	{
		$document = $this->discover();

		if (empty($document['token_endpoint'])) {
			$this->error = 'no token endpoint';

			return null;
		}

		$body = $this->fetch(
			$document['token_endpoint'],
			[
				'grant_type' => 'authorization_code',
				'code' => $code,
				'redirect_uri' => $this->provider->redirectUri(),
				'code_verifier' => $state['verifier'] ?? '',
				// Sent as well as the Basic header, because providers differ on
				// which they accept and sending both is harmless.
				'client_id' => $this->provider->client_id,
				'client_secret' => $this->provider->client_secret,
			],
			[
				'Authorization: Basic ' . base64_encode(
					rawurlencode($this->provider->client_id) . ':' . rawurlencode($this->provider->client_secret),
				),
			],
		);

		if ($body === null) {
			return null;
		}

		$token = Utils::jsonDecode($body, true);

		if (!\is_array($token) || empty($token['id_token'])) {
			$this->error = 'token endpoint returned no id_token';

			return null;
		}

		$claims = self::decodeIdToken($token['id_token']);

		if ($claims === null) {
			$this->error = 'could not read the id_token';

			return null;
		}

		if (!$this->claimsAreAcceptable($claims, $state)) {
			return null;
		}

		// Ask for the rest only if the token did not carry it. Some providers
		// keep the ID token small and put the profile behind userinfo.
		if (empty($claims['email']) && !empty($document['userinfo_endpoint']) && !empty($token['access_token'])) {
			$body = $this->fetch(
				$document['userinfo_endpoint'],
				null,
				['Authorization: Bearer ' . $token['access_token']],
			);

			$userinfo = $body === null ? null : Utils::jsonDecode($body, true);

			// The sub has to be the same person we just authenticated.
			if (\is_array($userinfo) && ($userinfo['sub'] ?? '') === $claims['sub']) {
				$claims += $userinfo;
			}
		}

		return $claims;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Base64url, as the JOSE specifications use it.
	 *
	 * @param string $data Raw bytes.
	 * @return string The encoded form.
	 */
	public static function base64UrlEncode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * The reverse of self::base64UrlEncode().
	 *
	 * @param string $data The encoded form.
	 * @return string Raw bytes.
	 */
	public static function base64UrlDecode(string $data): string
	{
		return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - \strlen($data) % 4) % 4), true);
	}

	/**
	 * Reads the claims out of an ID token without checking its signature.
	 *
	 * Only safe because of where the caller got the token; see the note on this
	 * class. Do not call this with a token that arrived any other way.
	 *
	 * @param string $id_token The JWT.
	 * @return ?array The payload, or null if it is not a readable JWT.
	 */
	public static function decodeIdToken(string $id_token): ?array
	{
		$parts = explode('.', $id_token);

		if (\count($parts) !== 3) {
			return null;
		}

		$claims = Utils::jsonDecode(self::base64UrlDecode($parts[1]), true);

		return \is_array($claims) && !empty($claims['sub']) ? $claims : null;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks the claims are about us, from who we asked, and still current.
	 *
	 * @param array $claims The decoded ID token payload.
	 * @param array $state What we stashed before sending the member away.
	 * @return bool Whether the claims can be trusted.
	 */
	protected function claimsAreAcceptable(array $claims, array $state): bool
	{
		if (rtrim($claims['iss'] ?? '', '/') !== rtrim($this->provider->issuer, '/')) {
			$this->error = 'id_token issuer ' . ($claims['iss'] ?? '(none)') . ' is not ' . $this->provider->issuer;

			return false;
		}

		// aud is either our client ID or a list containing it.
		$audience = (array) ($claims['aud'] ?? []);

		if (!\in_array($this->provider->client_id, $audience, true)) {
			$this->error = 'id_token was not issued for this client';

			return false;
		}

		// When more than one audience is named the provider must say which one
		// it was really for, and it has to be us.
		if (\count($audience) > 1 && ($claims['azp'] ?? $this->provider->client_id) !== $this->provider->client_id) {
			$this->error = 'id_token authorized party is somebody else';

			return false;
		}

		if (!isset($claims['exp']) || (int) $claims['exp'] < time() - 60) {
			$this->error = 'id_token has expired';

			return false;
		}

		// Ties this token to the request we started, so one obtained elsewhere
		// cannot be replayed into this session.
		if (($claims['nonce'] ?? '') !== ($state['nonce'] ?? '')) {
			$this->error = 'id_token nonce does not match the one we sent';

			return false;
		}

		return true;
	}

	/**
	 * Makes one back channel request to the provider.
	 *
	 * Deliberately not WebFetchApi::fetch(): that cannot set request headers,
	 * and it rewrites the host to a literal IP, which defeats the certificate
	 * check we need here. CurlFetcher would work but defaults to
	 * CURLOPT_SSL_VERIFYPEER false, which is not acceptable for a token
	 * exchange, so the options that matter are set explicitly instead.
	 *
	 * @param string $url Where to send it.
	 * @param ?array $post_data Form fields to post, or null for a GET.
	 * @param array $headers Extra request headers.
	 * @return ?string The response body, or null if the call failed.
	 */
	protected function fetch(string $url, ?array $post_data = null, array $headers = []): ?string
	{
		if (!\function_exists('curl_init')) {
			$this->error = 'curl is not available';

			return null;
		}

		$parsed = Url::create($url, true);

		if (($parsed->scheme ?? '') !== 'https' && !$this->allowsInsecure($parsed)) {
			$this->error = 'refusing to talk to ' . $url . ' without https';

			return null;
		}

		if (!$this->hostIsAllowed($parsed)) {
			$this->error = $url . ' resolves to a private address and this provider does not allow that';

			return null;
		}

		$ch = curl_init();

		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_USERAGENT => SMF_USER_AGENT,
			// Not negotiable. See the note on this class.
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
		]);

		if ($post_data !== null) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data, '', '&'));
		}

		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$curl_error = curl_error($ch);

		curl_close($ch);

		if ($body === false) {
			$this->error = 'request to ' . $url . ' failed: ' . $curl_error;

			return null;
		}

		if ($code < 200 || $code > 299) {
			$this->error = $url . ' answered ' . $code . ': ' . substr((string) $body, 0, 200);

			return null;
		}

		return (string) $body;
	}

	/**
	 * Whether this provider may be reached over plain http.
	 *
	 * Only ever true for a host that is already allowed to be private, which in
	 * practice means a provider on the same machine or network as the forum.
	 *
	 * @param \SMF\Url $url The URL in question.
	 * @return bool Whether to allow it.
	 */
	protected function allowsInsecure(Url $url): bool
	{
		return !empty($this->provider->settings['allow_private_host']) && !$this->resolvesGlobally($url);
	}

	/**
	 * Whether we are willing to send this provider's traffic to this host.
	 *
	 * @param \SMF\Url $url The URL in question.
	 * @return bool Whether to allow it.
	 */
	protected function hostIsAllowed(Url $url): bool
	{
		return $this->resolvesGlobally($url) || !empty($this->provider->settings['allow_private_host']);
	}

	/**
	 * Whether every address this host resolves to is a public one.
	 *
	 * @param \SMF\Url $url The URL in question.
	 * @return bool Whether it is out on the internet.
	 */
	protected function resolvesGlobally(Url $url): bool
	{
		if (empty($url->host)) {
			return false;
		}

		$ips = $url->getIPs();

		if ($ips === []) {
			return false;
		}

		foreach ($ips as $ip) {
			if (!$ip->isValid(FILTER_FLAG_GLOBAL_RANGE)) {
				return false;
			}
		}

		return true;
	}
}
