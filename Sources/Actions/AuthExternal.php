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
use SMF\Authentication\OidcClient;
use SMF\Authentication\Provider;
use SMF\Authentication\StepUp;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Sapi;
use SMF\User;
use SMF\UserDataset;
use SMF\Utils;

/**
 * Signs members in using an external identity provider.
 */
class AuthExternal implements ActionInterface
{
	use ActionTrait;

	/*****************
	 * Class constants
	 *****************/

	/**
	 * How long an identity waits for the sign up form to be finished, in
	 * seconds.
	 *
	 * The provider has already said who this is, so the wait is only about how
	 * long the form itself may take, and the form can take a few goes.
	 */
	public const PENDING_LIFETIME = 3600;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'start';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'start' => 'start',
		'callback' => 'callback',
		'link' => 'link',
		'unlink' => 'unlink',
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
		// Everything here hands credentials around, so insist on SSL if the
		// forum does, exactly as the password form does.
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Sapi::httpsOn()) {
			ErrorHandler::fatalLang('login_ssl_required', false);
		}

		$call = \is_string(self::$subactions[$this->subaction]) && method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Sends the member off to the identity provider.
	 */
	public function start(): void
	{
		$provider = $this->loadProvider();

		$client = new OidcClient($provider);
		$begun = $client->beginAuthorization($_SESSION['login_url'] ?? '');

		if ($begun === null) {
			$this->fail($client->error, 'authext_provider_unavailable');
		}

		// Remember what we sent, so the callback can check what comes back.
		$_SESSION['authext'] = $begun['state'];

		Utils::redirectexit($begun['url']);
	}

	/**
	 * Sends a member who is already signed in back to their provider, to prove
	 * they are still the person who signed in.
	 */
	public function reauth(): void
	{
		User::$me->kickIfGuest();
		User::$me->checkSession('get');

		$provider = $this->loadProvider();

		// It has to be a provider they have actually linked. Signing in at one
		// they have not says somebody owns an account over there, and nothing
		// whatever about who is sitting at this browser.
		if (!Credential::has(Credential::TYPE_OIDC, $provider->id, User::$me->id)) {
			$this->fail('member ' . User::$me->id . ' has not linked provider ' . $provider->id, 'authext_reauth_not_linked');
		}

		$client = new OidcClient($provider);
		$begun = $client->beginAuthorization('', true);

		if ($begun === null) {
			$this->fail($client->error, 'authext_provider_unavailable');
		}

		// The callback reads these, and does something quite different with
		// what comes back than it would for an ordinary sign in.
		$begun['state']['purpose'] = StepUp::requestedPurpose();
		$begun['state']['reauth_for'] = User::$me->id;
		$begun['state']['reauth_return'] = StepUp::requestedReturn();

		$_SESSION['authext'] = $begun['state'];

		Utils::redirectexit($begun['url']);
	}

	/**
	 * Handles the member coming back from the identity provider.
	 */
	public function callback(): void
	{
		$provider = $this->loadProvider();
		$state = $_SESSION['authext'] ?? [];
		unset($_SESSION['authext']);

		// The provider says it went wrong, or the member said no.
		if (!empty($_REQUEST['error'])) {
			$this->fail(
				'provider returned ' . $_REQUEST['error'] . ': ' . ($_REQUEST['error_description'] ?? ''),
				'authext_declined',
			);
		}

		if (empty($_REQUEST['code']) || empty($_REQUEST['state'])) {
			$this->fail('callback without a code or state', 'authext_failed');
		}

		// Did this come from the request we started, in this session?
		if (
			empty($state['state'])
			|| !hash_equals($state['state'], (string) $_REQUEST['state'])
			|| (int) ($state['provider'] ?? 0) !== $provider->id
		) {
			$this->fail('state did not match the one we issued', 'authext_failed');
		}

		// Somebody could have left the tab open for a week.
		if (($state['created'] ?? 0) < time() - 900) {
			$this->fail('state expired', 'authext_failed');
		}

		$client = new OidcClient($provider);
		$claims = $client->completeAuthorization((string) $_REQUEST['code'], $state);

		if ($claims === null) {
			$this->fail($client->error, 'authext_failed');
		}

		$subject = (string) $claims['sub'];

		/*
		 * This was not a sign in at all: somebody already signed in was sent back
		 * to their provider to prove they are still there. It answers on its own
		 * and stops, because none of what follows should happen -- nothing is
		 * linked, nobody is logged in, and no account is created.
		 */
		if (!empty($state['reauth_for'])) {
			$this->finishReauth($provider, $subject, $state);
		}

		/*
		 * MOD AUTHORS: last chance to decide who this is. Set $id_member to
		 * take over the decision entirely; leave it alone to let SMF work it
		 * out from the rules below.
		 */
		$id_member = 0;
		IntegrationHook::call('integrate_external_identity', [&$claims, &$id_member, $provider]);

		if ($id_member === 0) {
			$id_member = Credential::findMember(Credential::TYPE_OIDC, $provider->id, $subject);
		}

		// Somebody we already know. Straight in.
		if ($id_member > 0) {
			Credential::touch(Credential::TYPE_OIDC, $provider->id, $subject);
			$this->logIn($id_member, $state['return_to'] ?? '');
		}

		// A member who is signed in already is attaching this to their account.
		if (!User::$me->is_guest) {
			Credential::add(
				User::$me->id,
				Credential::TYPE_OIDC,
				$provider->id,
				$subject,
				$provider->title . ' (' . ($claims['email'] ?? $subject) . ')',
			);

			Utils::redirectexit('action=profile;area=linkedaccounts;linked');
		}

		// Nobody has claimed this identity, and nobody is signed in.
		$this->claimOrRegister($provider, $claims, $subject, $state['return_to'] ?? '');
	}

	/**
	 * Starts linking a provider to the account that is already signed in.
	 */
	public function link(): void
	{
		User::$me->kickIfGuest();
		User::$me->checkSession('get');
		User::$me->validateSession(StepUp::FOR_CREDENTIALS);

		$this->start();
	}

	/**
	 * Detaches a provider from the account that is signed in.
	 */
	public function unlink(): void
	{
		User::$me->kickIfGuest();
		User::$me->checkSession('get');
		User::$me->validateSession(StepUp::FOR_CREDENTIALS);

		$removed = Credential::remove(
			(int) ($_REQUEST['cred'] ?? 0),
			User::$me->id,
			User::$me->hasUsablePassword(),
		);

		Utils::redirectexit('action=profile;area=linkedaccounts;' . ($removed ? 'unlinked' : 'lastone'));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The identity a provider vouched for while somebody was signing up.
	 *
	 * A provider saying who somebody is does not make them a member here: they
	 * still have to go through the sign up form, agreement and all. This is
	 * what is remembered in the meantime, so the form knows they have already
	 * been vouched for and does not ask them to invent a password as well.
	 *
	 * @return ?array The pending identity, or null if there is not one.
	 */
	public static function pendingIdentity(): ?array
	{
		$pending = $_SESSION['authext_pending'] ?? null;

		if (
			!\is_array($pending)
			|| empty($pending['subject'])
			|| ($pending['created'] ?? 0) < time() - self::PENDING_LIFETIME
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
	 * Loads the provider this request is about, or stops.
	 *
	 * @return \SMF\Authentication\Provider The provider.
	 */
	protected function loadProvider(): Provider
	{
		$provider = Provider::load((int) ($_REQUEST['provider'] ?? 0));

		if ($provider === null || !$provider->enabled || !$provider->isUsable()) {
			$this->fail('no usable provider ' . ($_REQUEST['provider'] ?? '(none)'), 'authext_provider_unavailable');
		}

		return $provider;
	}

	/**
	 * Decides what to do with an identity nobody has claimed yet.
	 *
	 * @param \SMF\Authentication\Provider $provider Who vouched for them.
	 * @param array $claims What the provider said about them.
	 * @param string $subject The provider's ID for this person.
	 * @param string $return_to Where they were headed.
	 */
	protected function claimOrRegister(Provider $provider, array $claims, string $subject, string $return_to): void
	{
		$email = (string) ($claims['email'] ?? '');

		/*
		 * Matching on email lets somebody who controls an address at the
		 * provider walk into the account that uses it here, so it happens only
		 * when the admin has asked for it and the provider states the address
		 * has been verified.
		 */
		if (
			$email !== ''
			&& !empty($provider->settings['link_by_verified_email'])
			&& !empty($claims['email_verified'])
		) {
			$loaded = User::load($email, User::LOAD_BY_EMAIL, UserDataset::Basic);

			if ($loaded !== []) {
				$member = reset($loaded);

				Credential::add(
					$member->id,
					Credential::TYPE_OIDC,
					$provider->id,
					$subject,
					$provider->title . ' (' . $email . ')',
				);

				$this->logIn($member->id, $return_to);
			}
		}

		if (empty($provider->settings['allow_registration'])) {
			$this->fail('no account for ' . $subject . ' and registration is off', 'authext_no_account');
		}

		// The forum is not taking new members at all, whatever the provider is
		// allowed to do. Say so here rather than sending them to a form that
		// will only tell them the same thing less helpfully.
		if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 3) {
			$this->fail('no account for ' . $subject . ' and registration is disabled', 'authext_no_account');
		}

		/*
		 * Hand over to the normal sign up form rather than creating an account
		 * behind the member's back: registration here still means the agreement,
		 * the privacy policy, COPPA and admin approval, and this is the one
		 * place that already gets all of that right.
		 */
		$_SESSION['authext_pending'] = [
			'provider' => $provider->id,
			'title' => $provider->title,
			'subject' => $subject,
			'email' => $email,
			'name' => (string) ($claims['preferred_username'] ?? $claims['name'] ?? ''),
			'created' => time(),
		];

		Utils::redirectexit('action=signup');
	}

	/**
	 * Finishes a member proving, at their provider, that they are still there.
	 *
	 * @param \SMF\Authentication\Provider $provider Who vouched for them.
	 * @param string $subject The provider's ID for whoever just signed in there.
	 * @param array $state What we remembered when we sent them off.
	 */
	protected function finishReauth(Provider $provider, string $subject, array $state): void
	{
		/*
		 * The member has to be the same one throughout. Being signed out and
		 * back in as somebody else while the provider's page was open would
		 * otherwise leave the second account holding the first one's proof.
		 */
		if (User::$me->is_guest || User::$me->id !== (int) $state['reauth_for']) {
			$this->fail('proof was begun by member ' . $state['reauth_for'] . ', who is no longer the one asking', 'authext_reauth_failed');
		}

		/*
		 * And the account at the provider has to be the one linked here. Any
		 * other account there proves somebody can sign in over there, which is
		 * not the question that was asked.
		 */
		if (Credential::findMember(Credential::TYPE_OIDC, $provider->id, $subject) !== User::$me->id) {
			$this->fail('member ' . User::$me->id . ' proved themselves as somebody else at provider ' . $provider->id, 'authext_reauth_failed');
		}

		Credential::touch(Credential::TYPE_OIDC, $provider->id, $subject);

		StepUp::stamp((string) ($state['purpose'] ?? StepUp::FOR_CREDENTIALS));

		Utils::redirectexit((string) ($state['reauth_return'] ?? Config::$scripturl));
	}

	/**
	 * Finishes a successful sign in.
	 *
	 * @param int $id_member Who to log in.
	 * @param string $return_to Where they were headed.
	 */
	protected function logIn(int $id_member, string $return_to): void
	{
		$loaded = User::load($id_member, User::LOAD_BY_ID, UserDataset::Normal);

		if ($loaded === []) {
			$this->fail('credential points at member ' . $id_member . ', who does not exist', 'authext_failed');
		}

		$member = reset($loaded);

		// Same activation rules a password login gets.
		if ($member->is_activated % User::BANNED !== User::ACTIVATED) {
			$this->fail('member ' . $id_member . ' is not activated', 'authext_not_activated');
		}

		if ($return_to !== '') {
			$_SESSION['login_url'] = $return_to;
		}

		Login2::completeLogin($member);
	}

	/**
	 * Logs why a sign in did not happen, and tells the member something useful.
	 *
	 * The member never sees the detail: it usually says more about the provider
	 * than they need to know, and some of it is worth keeping to ourselves.
	 *
	 * @param string $detail What actually went wrong.
	 * @param string $message The language string to show.
	 */
	protected function fail(string $detail, string $message): void
	{
		ErrorHandler::log('External authentication: ' . $detail, 'general');

		ErrorHandler::fatal(Lang::getTxt($message, file: 'Login'), false);
	}
}
