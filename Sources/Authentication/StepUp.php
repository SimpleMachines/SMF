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

use SMF\Config;
use SMF\IntegrationHook;
use SMF\User;
use SMF\WebAuthn\Server;

/**
 * Proving, again, that you are still the person who signed in.
 *
 * SMF has always done this one way: before the admin or moderation areas it
 * asks for the password a second time, and remembers for an hour that you got
 * it right. That works right up until the account has no password to ask for,
 * which is now something a member can choose, so the same question has to be
 * answerable by whatever they did sign in with.
 *
 * The answer is kept where it always was, in $_SESSION['<purpose>_time'], so
 * everything downstream is unchanged: a passkey satisfies the admin gate by
 * writing the same stamp a typed password would have written.
 */
class StepUp
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Before the administration centre.
	 */
	public const FOR_ADMIN = 'admin';

	/**
	 * Before the moderation centre.
	 */
	public const FOR_MODERATE = 'moderate';

	/**
	 * Before changing what can sign in to the account.
	 */
	public const FOR_CREDENTIALS = 'credentials';

	/**
	 * How long an answer counts for, when the admin has not said.
	 */
	public const DEFAULT_LIFETIME = 3600;

	/**
	 * How long it counts for when what is being changed is the way in.
	 */
	public const DEFAULT_CREDENTIALS_LIFETIME = 300;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The things a member can be asked to prove themselves for.
	 *
	 * @return array The purposes, including any a mod has added.
	 */
	public static function purposes(): array
	{
		$purposes = [];

		/*
		 * MOD AUTHORS: this is the existing hook for adding your own kind of
		 * session check. Add the name here and SMF\User::validateSession() will
		 * accept it, rather than quietly treating it as an admin check.
		 */
		IntegrationHook::call('integrate_validateSession', [&$purposes]);

		return array_merge([self::FOR_ADMIN, self::FOR_MODERATE, self::FOR_CREDENTIALS], $purposes);
	}

	/**
	 * How long an answer for this purpose counts for.
	 *
	 * Changing the way into an account gets a shorter window than the admin
	 * areas do by default. An hour-old answer is a reasonable thing to accept
	 * from somebody working through the admin panel; it is a poor thing to
	 * accept from somebody attaching a new credential, since that outlives the
	 * session, the password and the answer itself.
	 *
	 * @param string $purpose One of this class's FOR_ constants.
	 * @return int How many seconds an answer lasts.
	 */
	public static function lifetime(string $purpose): int
	{
		if ($purpose === self::FOR_CREDENTIALS) {
			$lifetime = (int) (Config::$modSettings['auth_credentials_lifetime'] ?? self::DEFAULT_CREDENTIALS_LIFETIME);
		} else {
			$lifetime = (int) (Config::$modSettings['auth_stepup_lifetime'] ?? self::DEFAULT_LIFETIME);
		}

		/*
		 * An XML request cannot show anybody a login form, so it is given some
		 * extra grace rather than failing in a way the page cannot explain.
		 * This has been here since long before any of the rest of it.
		 */
		return max(60, $lifetime) + (isset($_GET['xml']) ? 600 : 0);
	}

	/**
	 * Whether this member has proved themselves recently enough.
	 *
	 * An admin answer counts for everything, which is how SMF has always
	 * treated it -- but it is measured against the window of whatever is being
	 * asked for, so a short-lived purpose cannot be satisfied by a stale admin
	 * answer that merely has not expired yet.
	 *
	 * @param string $purpose One of this class's FOR_ constants.
	 * @return bool Whether to let them straight through.
	 */
	public static function isFresh(string $purpose): bool
	{
		$lifetime = self::lifetime($purpose);

		foreach (array_unique([$purpose, self::FOR_ADMIN]) as $key) {
			if (!empty($_SESSION[$key . '_time']) && $_SESSION[$key . '_time'] + $lifetime >= time()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this purpose is answered for, one way or another.
	 *
	 * The admin may have turned the whole check off, which counts as answered
	 * and leaves no stamp behind. Anything that wants to know whether to let
	 * somebody through should ask this rather than self::isFresh(), or it will
	 * refuse on a forum where the check is switched off.
	 *
	 * @param string $purpose One of this class's FOR_ constants.
	 * @return bool Whether to let them through without asking.
	 */
	public static function isSatisfied(string $purpose): bool
	{
		// Named securityDisable for the admin check, and securityDisable_<name>
		// for everything else, which is how SMF has always spelled it.
		if (!empty(Config::$modSettings['securityDisable' . ($purpose === self::FOR_ADMIN ? '' : '_' . $purpose)])) {
			return true;
		}

		return self::isFresh($purpose);
	}

	/**
	 * Records that they just proved it.
	 *
	 * @param string $purpose One of this class's FOR_ constants.
	 */
	public static function stamp(string $purpose): void
	{
		$_SESSION[$purpose . '_time'] = time();

		// Whatever they were doing when they were interrupted, they are no
		// longer being sent back to it.
		unset($_SESSION['request_referer']);
	}

	/**
	 * Forgets that they proved anything.
	 *
	 * @param string $purpose One of this class's FOR_ constants, or null for
	 *    every one of them.
	 */
	public static function forget(?string $purpose = null): void
	{
		foreach ($purpose === null ? self::purposes() : [$purpose] as $key) {
			unset($_SESSION[$key . '_time']);
		}
	}

	/**
	 * The ways this member could prove themselves, right now.
	 *
	 * Only what they actually have is listed. Offering a member a passkey
	 * button when they have never registered one is a dead end, and offering a
	 * provider they have not linked is worse than a dead end: signing in there
	 * would prove somebody owns an account at that provider, and nothing at all
	 * about who is sitting here.
	 *
	 * @return array Whether they have a password, whether they have a passkey,
	 *    and the providers they have linked.
	 */
	public static function methods(): array
	{
		$methods = [
			'password' => User::$me->hasUsablePassword(),
			'passkey' => false,
			'providers' => [],
		];

		if (User::$me->is_guest) {
			return $methods;
		}

		$providers = Provider::loadAll();

		foreach (Credential::listFor(User::$me->id) as $credential) {
			if ($credential['type'] === Credential::TYPE_WEBAUTHN) {
				$methods['passkey'] = Server::isEnabled();

				continue;
			}

			$provider = $providers[(int) $credential['id_provider']] ?? null;

			if ($provider !== null && $provider->enabled && $provider->isUsable()) {
				$methods['providers'][$provider->id] = $provider;
			}
		}

		return $methods;
	}

	/**
	 * What the request being handled says it wants to prove.
	 *
	 * Anything unrecognised becomes the narrowest purpose there is, rather than
	 * the widest: a request that does not say what it is for must not turn out
	 * to have unlocked the administration centre.
	 *
	 * @return string One of this class's FOR_ constants.
	 */
	public static function requestedPurpose(): string
	{
		$purpose = (string) ($_REQUEST['purpose'] ?? '');

		return \in_array($purpose, self::purposes(), true) ? $purpose : self::FOR_CREDENTIALS;
	}

	/**
	 * Where the request being handled wants to go afterwards.
	 *
	 * @return string A URL on this forum, and only on this forum.
	 */
	public static function requestedReturn(): string
	{
		$return_to = (string) ($_REQUEST['return_to'] ?? '');

		// Anywhere else is somebody else's idea of where this should go.
		return str_starts_with($return_to, Config::$scripturl) ? $return_to : Config::$scripturl;
	}

	/**
	 * Whether there is any way at all for this member to prove themselves.
	 *
	 * When there is not, asking is pointless: the form would have nothing on it
	 * they could fill in, and they would be shut out of whatever is behind it
	 * for good. See SMF\User::validateSession(), which is where that matters.
	 *
	 * @return bool Whether to bother asking.
	 */
	public static function isPossible(): bool
	{
		$methods = self::methods();

		return $methods['password'] || $methods['passkey'] || $methods['providers'] !== [];
	}
}
