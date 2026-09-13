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

		/*
		 * Anything already registered goes in the exclusion list, so that an
		 * authenticator the member is holding says "you already did this" rather
		 * than quietly making a second credential they now have to tell apart
		 * from the first.
		 */
		$exclude = [];

		foreach (Credential::listFor(User::$me->id, Credential::TYPE_WEBAUTHN) as $credential) {
			$stored = $this->secretData($credential);

			if ($stored['id'] !== '') {
				$exclude[] = $stored['id'];
			}
		}

		$this->respond([
			'options' => Server::creationOptions(
				User::$me->id,
				User::$me->username,
				User::$me->name,
				$exclude,
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
			Utils::jsonEncode([
				'id' => Server::base64UrlEncode($credential['id']),
				'key' => $credential['key'],
				'algorithm' => $credential['algorithm'],
				'sign_count' => $credential['sign_count'],
				'aaguid' => $credential['aaguid'],
			]),
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
		];
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
