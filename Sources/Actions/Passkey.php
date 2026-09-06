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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Authentication\Credential;
use SMF\Authentication\StepUp;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Sapi;
use SMF\User;
use SMF\UserDataset;
use SMF\Utils;
use SMF\WebAuthn\Server;
use SMF\WebAuthn\WebAuthnException;

/**
 * Registers passkeys, and signs members in with them.
 *
 * Everything here answers with JSON, because a passkey ceremony happens in the
 * browser: the page asks for the options, hands them to the authenticator, and
 * sends back whatever it produced. There is no version of this that works
 * without scripting, which is why the passkey button is only ever added to the
 * page by the script that knows how to use it.
 */
class Passkey implements ActionInterface
{
	use ActionTrait;

	/*****************
	 * Class constants
	 *****************/

	/**
	 * How long a passkey waits for the sign up form to be finished, in seconds.
	 *
	 * Generous, because the form it is waiting on may take several attempts to
	 * get past: a name that is taken, a verification code that was misread, a
	 * required custom field left blank. Making the member produce another
	 * passkey each time would leave a trail of dead ones on their device.
	 */
	public const SIGNUP_LIFETIME = 3600;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'loginoptions';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'registeroptions' => 'registerOptions',
		'register' => 'register',
		'loginoptions' => 'loginOptions',
		'login' => 'login',
		'signupoptions' => 'signupOptions',
		'signup' => 'signup',
		'reauthoptions' => 'reauthOptions',
		'reauth' => 'reauth',
	];

	/****************
	 * Public methods
	 ****************/

	public function isRestrictedGuestAccessAllowed(): bool
	{
		return true;
	}

	public function canShowInMaintenanceMode(): bool
	{
		return true;
	}

	public function isAgreementAction(): bool
	{
		return true;
	}

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		if (!Server::isEnabled()) {
			$this->fail('passkeys are not enabled on this forum', 'passkey_unavailable');
		}

		// A ceremony carries credentials, so insist on SSL if the forum does.
		// Browsers insist on it too, localhost aside, so this rarely bites.
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Sapi::httpsOn()) {
			$this->fail('passkey ceremony over plain HTTP', 'login_ssl_required');
		}

		User::$me->checkSession('post');

		$call = \is_string(self::$subactions[$this->subaction]) && method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Hands the browser what it needs to make a passkey.
	 */
	public function registerOptions(): void
	{
		if (User::$me->is_guest) {
			$this->fail('guest asked to register a passkey', 'passkey_not_logged_in');
		}

		$this->checkRecentlyProved();

		/*
		 * Anything already registered goes in the exclusion list, so that an
		 * authenticator the member is holding says "you already did this" rather
		 * than quietly making a second credential they now have to tell apart
		 * from the first.
		 */
		$exclude = [];
		$handle = null;

		foreach (Credential::listFor(User::$me->id, Credential::TYPE_WEBAUTHN) as $credential) {
			$stored = $this->secretData($credential);

			if ($stored['id'] !== '') {
				$exclude[] = $stored['id'];
			}

			/*
			 * A passkey made during sign up was given a random handle, because
			 * there was no member ID to derive one from yet. Keep using it, so
			 * that this key joins the account the browser already knows about
			 * instead of appearing as a second one with the same name.
			 */
			if ($stored['user_handle'] !== '') {
				$handle = Server::base64UrlDecode($stored['user_handle']);
			}
		}

		$this->respond([
			'options' => Server::creationOptions(
				User::$me->id,
				User::$me->username,
				User::$me->name,
				$exclude,
				$handle,
			),
		]);
	}

	/**
	 * Keeps a passkey the browser just made.
	 */
	public function register(): void
	{
		if (User::$me->is_guest) {
			$this->fail('guest tried to register a passkey', 'passkey_not_logged_in');
		}

		$this->checkRecentlyProved();

		try {
			$credential = Server::verifyCreation($this->credentialResponse());
		} catch (WebAuthnException $e) {
			$this->fail('registration refused: ' . $e->getMessage(), 'passkey_register_failed');
		}

		/*
		 * The challenge remembered who asked for it. If that is not who is
		 * asking now, someone has been signed out and back in as somebody else
		 * part way through, and this key would end up on the wrong account.
		 */
		if ($credential['member'] !== User::$me->id) {
			$this->fail('registration was begun by member ' . $credential['member'] . ', not ' . User::$me->id, 'passkey_register_failed');
		}

		$identifier = self::identifier($credential['id']);

		if (Credential::find(Credential::TYPE_WEBAUTHN, 0, $identifier) !== null) {
			$this->fail('credential is already registered', 'passkey_already_registered');
		}

		$title = (string) Utils::htmlTrim(Utils::htmlspecialchars((string) ($_POST['title'] ?? '')));

		if ($title === '') {
			$title = Lang::getTxt('passkey_default_title', file: 'Profile');
		}

		Credential::add(
			User::$me->id,
			Credential::TYPE_WEBAUTHN,
			0,
			$identifier,
			Utils::entitySubstr($title, 0, 80),
			$this->keepFrom($credential),
		);

		$this->respond([
			'redirect' => Config::$scripturl . '?action=profile;area=passkeys;added',
		]);
	}

	/**
	 * Hands the browser what it needs to sign in.
	 */
	public function loginOptions(): void
	{
		/*
		 * No list of credentials goes out: the member has not said who they are
		 * yet, and answering that question for them would let anybody ask this
		 * forum which passkeys a given account has. The browser knows which of
		 * its own passkeys belong to this site, so it is the one that chooses.
		 */
		$this->respond(['options' => Server::requestOptions()]);
	}

	/**
	 * Signs a member in with the passkey they just used.
	 */
	public function login(): void
	{
		if (!User::$me->is_guest) {
			$this->respond(['redirect' => Config::$scripturl]);
		}

		$response = $this->credentialResponse();

		$raw_id = Server::base64UrlDecode((string) ($_POST['rawId'] ?? ''));

		$credential = $raw_id === '' ? null : Credential::find(Credential::TYPE_WEBAUTHN, 0, self::identifier($raw_id));

		if ($credential === null) {
			$this->fail('no passkey registered with that ID', 'passkey_login_failed');
		}

		$stored = $this->secretData($credential);

		try {
			$result = Server::verifyAssertion($response, $stored['key'], $stored['sign_count']);
		} catch (WebAuthnException $e) {
			$this->fail('assertion refused: ' . $e->getMessage(), 'passkey_login_failed');
		}

		/*
		 * A counter that has not moved forward is the one hint the standard
		 * gives that a credential has been copied off its device. It is only a
		 * hint -- plenty of authenticators never count at all, and report zero
		 * every time -- so it is written down rather than acted on. Refusing
		 * here would lock members out over an authenticator quirk.
		 */
		if (Server::signCountWentBackwards($stored['sign_count'], $result['sign_count'])) {
			ErrorHandler::log(
				'Passkey sign count for member ' . $credential['id_member'] . ' went from ' . $stored['sign_count'] . ' to ' . $result['sign_count'] . ', which may mean the credential has been cloned.',
				'general',
			);
		}

		$stored['sign_count'] = $result['sign_count'];
		Credential::setSecretData((int) $credential['id_auth'], Utils::jsonEncode($stored));
		Credential::touch(Credential::TYPE_WEBAUTHN, 0, $credential['identifier']);

		$this->respond(['redirect' => $this->finishSignIn((int) $credential['id_member'])]);
	}

	/**
	 * Hands the browser what it needs to make a passkey for an account that
	 * does not exist yet.
	 */
	public function signupOptions(): void
	{
		$this->checkSignUpAllowed();

		/*
		 * An authenticator shows this name to whoever is holding it, and stores
		 * it forever, so it is worth having before the key is made rather than
		 * putting a placeholder on the member's phone for good. The sign up form
		 * has the field; the script sends whatever is in it.
		 */
		$username = Utils::htmlTrim(Utils::htmlspecialchars((string) ($_POST['user'] ?? '')));

		if ($username === '') {
			$this->fail('passkey sign up with no username', 'passkey_signup_needs_username');
		}

		/*
		 * There is no member to derive a handle from, so one is invented. It
		 * goes into the session alongside the challenge, which is where the
		 * answer to this ceremony picks it up again; from there it is stored
		 * with the credential, and every passkey the member adds later reuses
		 * it. See self::registerOptions().
		 */
		$this->respond([
			'options' => Server::creationOptions(
				0,
				$username,
				$username,
				[],
				random_bytes(32),
			),
		]);
	}

	/**
	 * Holds on to a passkey until the account it belongs to has been created.
	 *
	 * Nothing is written to the database here. The member has not agreed to
	 * anything yet, has not been approved, and may never finish the form; the
	 * credential waits in the session until Register2 says an account exists to
	 * attach it to, and is forgotten along with the session if it does not.
	 */
	public function signup(): void
	{
		$this->checkSignUpAllowed();

		try {
			$credential = Server::verifyCreation($this->credentialResponse());
		} catch (WebAuthnException $e) {
			$this->fail('sign up refused: ' . $e->getMessage(), 'passkey_register_failed');
		}

		// This challenge was issued to somebody registering a key on an account
		// they were already signed in to, and it is not that route's answer.
		if ($credential['member'] !== 0) {
			$this->fail('sign up answered a challenge issued to member ' . $credential['member'], 'passkey_register_failed');
		}

		$identifier = self::identifier($credential['id']);

		if (Credential::find(Credential::TYPE_WEBAUTHN, 0, $identifier) !== null) {
			$this->fail('credential is already registered', 'passkey_already_registered');
		}

		$_SESSION['webauthn_signup'] = [
			'identifier' => $identifier,
			'secret_data' => $this->keepFrom($credential),
			'created' => time(),
		];

		$this->respond(['ready' => true]);
	}

	/**
	 * Hands the browser what it needs to prove, again, that this is still them.
	 */
	public function reauthOptions(): void
	{
		if (User::$me->is_guest) {
			$this->fail('guest asked to prove who they are', 'passkey_not_logged_in');
		}

		/*
		 * Unlike signing in, this names the credentials that will do: we already
		 * know who is meant to be sitting here, and an assertion from somebody
		 * else's passkey proves nothing about them. The browser is told, so it
		 * offers the right key rather than one that will be refused.
		 */
		$allowed = [];

		foreach (Credential::listFor(User::$me->id, Credential::TYPE_WEBAUTHN) as $credential) {
			$stored = $this->secretData($credential);

			if ($stored['id'] !== '') {
				$allowed[] = $stored['id'];
			}
		}

		if ($allowed === []) {
			$this->fail('member ' . User::$me->id . ' has no passkey to prove anything with', 'passkey_reauth_none');
		}

		$this->respond(['options' => Server::requestOptions($allowed, StepUp::requestedPurpose())]);
	}

	/**
	 * Accepts the proof, and notes how recently it was given.
	 */
	public function reauth(): void
	{
		if (User::$me->is_guest) {
			$this->fail('guest tried to prove who they are', 'passkey_not_logged_in');
		}

		$purpose = StepUp::requestedPurpose();

		$raw_id = Server::base64UrlDecode((string) ($_POST['rawId'] ?? ''));

		$credential = $raw_id === '' ? null : Credential::find(Credential::TYPE_WEBAUTHN, 0, self::identifier($raw_id));

		/*
		 * The passkey has to be one of theirs. Without this, anybody holding a
		 * passkey for any account here could satisfy the check for whichever
		 * account they happened to be signed in to.
		 */
		if ($credential === null || (int) $credential['id_member'] !== User::$me->id) {
			$this->fail('passkey does not belong to member ' . User::$me->id, 'passkey_reauth_failed');
		}

		$stored = $this->secretData($credential);

		try {
			$result = Server::verifyAssertion($this->credentialResponse(), $stored['key'], $stored['sign_count'], $purpose);
		} catch (WebAuthnException $e) {
			$this->fail('proof refused: ' . $e->getMessage(), 'passkey_reauth_failed');
		}

		if (Server::signCountWentBackwards($stored['sign_count'], $result['sign_count'])) {
			ErrorHandler::log(
				'Passkey sign count for member ' . $credential['id_member'] . ' went from ' . $stored['sign_count'] . ' to ' . $result['sign_count'] . ', which may mean the credential has been cloned.',
				'general',
			);
		}

		$stored['sign_count'] = $result['sign_count'];
		Credential::setSecretData((int) $credential['id_auth'], Utils::jsonEncode($stored));
		Credential::touch(Credential::TYPE_WEBAUTHN, 0, $credential['identifier']);

		StepUp::stamp($purpose);

		$this->respond(['redirect' => StepUp::requestedReturn()]);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Turns a credential ID into something the database can index.
	 *
	 * Credential IDs run to a kilobyte, and MySQL indexes the first 191
	 * characters of the column they live in, so storing them as they come would
	 * let two different credentials collide on the part that gets indexed.
	 * Hashing gives a fixed length that is unique all the way along; the ID
	 * itself is kept beside it for the times a browser needs it back.
	 *
	 * @param string $raw_id The credential ID, as raw bytes.
	 * @return string What to store in the identifier column.
	 */
	public static function identifier(string $raw_id): string
	{
		return hash('sha256', $raw_id);
	}

	/**
	 * Whether somebody with no account here may make one with a passkey.
	 *
	 * This is a separate choice from letting members add a passkey to an
	 * account they already have. An account made this way has no password at
	 * all, so the member's only way back in is the device holding the key, and
	 * an admin should decide that rather than inherit it from turning passkeys
	 * on.
	 *
	 * @return bool Whether to offer it on the sign up form.
	 */
	public static function isSignUpAllowed(): bool
	{
		return Server::isEnabled()
			&& !empty(Config::$modSettings['webauthn_allow_signup'])
			&& (empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] != 3);
	}

	/**
	 * The passkey somebody made while filling in the sign up form, if any.
	 *
	 * @return ?array The pending credential, or null if there is not one.
	 */
	public static function pendingSignUp(): ?array
	{
		$pending = $_SESSION['webauthn_signup'] ?? null;

		if (
			!\is_array($pending)
			|| empty($pending['identifier'])
			|| ($pending['created'] ?? 0) < time() - self::SIGNUP_LIFETIME
		) {
			return null;
		}

		return $pending;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Stops unless the member proved who they are recently enough.
	 *
	 * The profile page asks before it renders, so a browser that got its
	 * settings from that page has already been through this. Checking again
	 * here is for the request that did not come from that page at all.
	 */
	protected function checkRecentlyProved(): void
	{
		if (!StepUp::isSatisfied(StepUp::FOR_CREDENTIALS)) {
			$this->fail('member ' . User::$me->id . ' has not proved who they are recently enough', 'stepup_required');
		}
	}

	/**
	 * Stops unless this request may create an account with a passkey.
	 */
	protected function checkSignUpAllowed(): void
	{
		if (!self::isSignUpAllowed()) {
			$this->fail('passkey sign up is not enabled on this forum', 'passkey_signup_unavailable');
		}

		// Somebody with an account uses the profile page to add a passkey. This
		// route makes an account, which they do not need a second of.
		if (!User::$me->is_guest) {
			$this->fail('member ' . User::$me->id . ' tried to sign up again', 'passkey_signup_unavailable');
		}
	}

	/**
	 * Reads the part of the request the authenticator produced.
	 *
	 * @return array The response fields, still base64url encoded.
	 */
	protected function credentialResponse(): array
	{
		$response = [];

		// Everything here is base64url, so anything else cannot be one of ours
		// and is dropped rather than passed along to be puzzled over later.
		foreach (['clientDataJSON', 'attestationObject', 'authenticatorData', 'signature', 'userHandle'] as $field) {
			if (isset($_POST[$field]) && \is_string($_POST[$field]) && preg_match('~^[A-Za-z0-9_-]*={0,2}$~', $_POST[$field]) === 1) {
				$response[$field] = $_POST[$field];
			}
		}

		return $response;
	}

	/**
	 * Reads what was kept alongside a stored passkey.
	 *
	 * @param array $credential The credential row.
	 * @return array The stored key, ID and sign count, whatever is in the row.
	 */
	protected function secretData(array $credential): array
	{
		$stored = Utils::jsonDecode($credential['secret_data'] ?? '', true);

		return [
			'id' => (string) ($stored['id'] ?? ''),
			'key' => (string) ($stored['key'] ?? ''),
			'algorithm' => (int) ($stored['algorithm'] ?? 0),
			'sign_count' => (int) ($stored['sign_count'] ?? 0),
			'aaguid' => (string) ($stored['aaguid'] ?? ''),
			'user_handle' => (string) ($stored['user_handle'] ?? ''),
		];
	}

	/**
	 * Picks out the parts of a new credential that are worth storing.
	 *
	 * @param array $credential What SMF\WebAuthn\Server made of the ceremony.
	 * @return string The secret_data column's new contents.
	 */
	protected function keepFrom(array $credential): string
	{
		return Utils::jsonEncode([
			'id' => Server::base64UrlEncode($credential['id']),
			'key' => $credential['key'],
			'algorithm' => $credential['algorithm'],
			'sign_count' => $credential['sign_count'],
			'aaguid' => $credential['aaguid'],
			'user_handle' => Server::base64UrlEncode($credential['user_handle']),
		]);
	}

	/**
	 * Finishes a successful sign in.
	 *
	 * @param int $id_member Who to log in.
	 * @return string Where the browser should go next.
	 */
	protected function finishSignIn(int $id_member): string
	{
		$loaded = User::load($id_member, User::LOAD_BY_ID, UserDataset::Normal);

		if ($loaded === []) {
			$this->fail('passkey belongs to member ' . $id_member . ', who does not exist', 'passkey_login_failed');
		}

		$member = reset($loaded);

		// Same activation rules a password login gets.
		if ($member->is_activated % User::BANNED !== User::ACTIVATED) {
			$this->fail('member ' . $id_member . ' is not activated', 'passkey_not_activated');
		}

		// Everything a password login does, minus the redirect: this request is
		// answering a script, so the script is told where to go instead.
		Login2::completeLogin($member, !empty($_POST['stay_logged_in']), false);

		if (!empty(Config::$maintenance) && !User::$me->allowedTo('admin_forum')) {
			return Config::$scripturl . '?action=logout;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		}

		// Through login2, which is what sends anyone with a second factor on to
		// prove it before they are really let in.
		return Config::$scripturl . '?action=login2;sa=check;member=' . User::$me->id;
	}

	/**
	 * Answers the script, and stops.
	 *
	 * @param array $data What to tell it.
	 */
	protected function respond(array $data): never
	{
		Utils::serverResponse(Utils::jsonEncode($data));

		exit;
	}

	/**
	 * Logs why a ceremony did not work, and tells the member something useful.
	 *
	 * The member never sees the detail. Saying which check failed tells whoever
	 * is trying exactly what to change, and the honest member cannot do
	 * anything with it either way.
	 *
	 * @param string $detail What actually went wrong.
	 * @param string $message The language string to show.
	 */
	protected function fail(string $detail, string $message): never
	{
		ErrorHandler::log('Passkey: ' . $detail, 'general');

		$this->respond(['error' => Lang::getTxt($message, file: 'Login')]);
	}
}
