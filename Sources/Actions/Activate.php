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
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\EmailAddress;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Routable;
use SMF\Security;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Activates a user's account.
 */
class Activate implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The sub-action to call.
	 */
	public string $subaction = '';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'activate' => 'activate',
		'resend' => 'resend',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var User
	 *
	 * The member being activated.
	 */
	private User $member;

	/**
	 * @var bool
	 *
	 * Whether the member's email address was changed.
	 */
	private bool $email_change = false;

	/****************
	 * Public methods
	 ****************/

	public function isRestrictedGuestAccessAllowed(): bool
	{
		return true;
	}

	/**
	 * Dispatcher.
	 */
	public function execute(): void
	{
		Theme::loadTemplate('Login');

		if (!isset($this->member)) {
			if (empty($_REQUEST['u']) && empty($_POST['user'])) {
				$this->showResendRequest();
			} else {
				$this->showRetryInvalidUser();
			}

			return;
		}

		if (empty($this->subaction)) {
			$this->showResendRequest();

			return;
		}

		$call = \is_string(self::$subactions[$this->subaction]) && method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Resends the activation link.
	 */
	public function resend(): void
	{
		// If necessary, update their email address.
		$this->updateEmail();

		$replacements = [
			'REALNAME' => $this->member->name,
			'USERNAME' => $this->member->username,
			'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $this->member->id . ';code=' . $this->member->validation_code,
			'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $this->member->id,
			'ACTIVATIONCODE' => $this->member->validation_code,
			'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
		];

		$emaildata = Mail::loadEmailTemplate('resend_activate_message', $replacements, empty($this->member->language) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $this->member->language);

		Mail::send($this->member->email, $emaildata['subject'], $emaildata['body'], null, 'resendact', $emaildata['is_html'], 0);

		Utils::$context['page_title'] = Lang::getTxt('invalid_activation_resend', file: 'Login');
		Utils::$context['error_title'] = Lang::getTxt('invalid_activation_resend', file: 'Login');

		// Not actually an error, but we just want to show the message and end execution.
		ErrorHandler::fatalLang(!empty($this->email_change) ? 'change_email_success' : 'resend_email_success', false);
	}

	/**
	 * Actually activates the member.
	 */
	public function activate(): void
	{
		// Quit if this code is not right.
		if (empty($_REQUEST['code']) || $this->member->validation_code != $_REQUEST['code']) {
			$this->showRetryInvalidCode();

			return;
		}

		// Let the integration know that they've been activated!
		IntegrationHook::call('integrate_activate', [$this->member->username]);

		// Validation complete - update the database!
		$prev_is_activated = $this->member->is_activated;
		$this->member->is_activated = User::ACTIVATED;
		$this->member->save();

		// Also do a proper member stat re-evaluation.
		Logging::updateStats('member', false);

		// Notify the admin about new activations, but not re-activations.
		if ($prev_is_activated === User::NOT_ACTIVATED) {
			Mail::adminNotify('activation', $this->member->id, $this->member->username);
		}

		Utils::$context += [
			'page_title' => Lang::getTxt('registration_successful', file: 'Login'),
			'sub_template' => 'login',
			'default_username' => $this->member->username,
			'default_password' => '',
			'never_expire' => false,
			'description' => Lang::getTxt('activate_success', file: 'Login'),
		];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		$route = self::buildActionRoute($params);

		if (isset($params['u'])) {
			$route[] = $params['u'];
			unset($params['u']);
		}

		return ['route' => $route, 'params' => $params];
	}

	/**
	 * Parses a route to get URL query parameters.
	 *
	 * @param array $route Array of routing path components.
	 * @param array $params Any existing URL query parameters.
	 * @return array URL query parameters
	 */
	public static function parseRoute(array $route, array $params = []): array
	{
		$params = array_merge($params, self::parseActionRoute($route));

		if (!empty($route)) {
			$params['u'] = array_shift($route);
		}

		return $params;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Logged in users should not bother to activate their accounts
		if (!empty(User::$me->id)) {
			Utils::redirectexit('action=profile');
		}

		// We can't activate anyone without knowing whom to activate.
		if (empty($_REQUEST['u']) && empty($_POST['user'])) {
			return;
		}

		// Load the member.
		$this->loadMember();

		// Nobody by that name or id. execute() shows the form that asks for
		// one, but only if it gets that far: the check below reads $this->member
		// first, and loadMember() leaves it unassigned when it finds nothing.
		if (!isset($this->member)) {
			return;
		}

		// Already activated, so redirect to the login screen.
		if (!\in_array((int) $this->member->is_activated, [User::NOT_ACTIVATED, User::UNVALIDATED])) {
			Utils::redirectexit('action=login');
		}

		// If a validation code was provided, they are trying to activate.
		if (!empty($_REQUEST['code'])) {
			$_REQUEST['sa'] = 'activate';
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Loads the specified member.
	 */
	protected function loadMember(): void
	{
		if (!empty($_REQUEST['u'])) {
			$member = current(User::load((int) $_REQUEST['u'], User::LOAD_BY_ID));
		} elseif (!empty($_POST['user'])) {
			if (($member = current(User::load($_POST['user'], User::LOAD_BY_EMAIL))) === false) {
				$member = current(User::load($_POST['user'], User::LOAD_BY_NAME));
			}
		}

		if (!empty($member)) {
			$this->member = $member;
		}
	}

	/**
	 * Change their email address? (they probably tried a fake one first :P.)
	 */
	protected function updateEmail(): void
	{
		if (
			!empty($_POST['new_email'])
			&& !empty($_REQUEST['passwd'])
			&& Security::hashVerifyPassword($_REQUEST['passwd'], $this->member->passwd)
			&& (
				$this->member->is_activated == User::NOT_ACTIVATED
				|| $this->member->is_activated == User::UNVALIDATED
			)
		) {
			if (empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] == 3) {
				ErrorHandler::fatalLang('no_access', false);
			}

			$email = new EmailAddress($_POST['new_email'], true);

			if (!$email->isValid()) {
				ErrorHandler::fatal(Lang::getTxt('valid_email_needed', ['email' => Utils::htmlspecialchars($_POST['new_email'])], file: 'Login'), false);
			}

			// Ummm... don't even dare try to take someone else's email!!
			$request = Db::$db->query(
				'SELECT id_member
				FROM {db_prefix}members
				WHERE email_address_ci = {string:email_address}
				LIMIT 1',
				[
					'email_address' => $email->casefolded(),
				],
			);

			if (Db::$db->num_rows($request) != 0) {
				ErrorHandler::fatalLang('email_in_use', false, [Utils::htmlspecialchars($_POST['new_email'])]);
			}
			Db::$db->free_result($request);

			// Set the new email address.
			$this->member->email = (string) $email;

			// Make sure their email isn't banned.
			$bans = Security::checkBans($this->member, true);

			if (!empty($bans['cannot_register'])) {
				ErrorHandler::fatal(Lang::getTxt('ban_register_prohibited', file: 'Login'), false, 403);
			}

			// Save the changes.
			$this->member->save();
			$this->email_change = true;
		}
	}

	/**
	 * Shows the page where the user can ask to resend the activation email.
	 */
	protected function showResendRequest(): void
	{
		if (empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] == '3') {
			ErrorHandler::fatalLang('no_access', false);
		}

		Utils::$context['member_id'] = 0;
		Utils::$context['sub_template'] = 'resend';
		Utils::$context['page_title'] = Lang::getTxt('invalid_activation_resend', file: 'Login');
		Utils::$context['can_activate'] = empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] == '1';
		Utils::$context['default_username'] = $_GET['user'] ?? '';
	}

	/**
	 * Shows the page where we ask the user to supply a valid name or email.
	 */
	protected function showRetryInvalidUser(): void
	{
		Utils::$context['sub_template'] = 'retry_activate';
		Utils::$context['page_title'] = Lang::getTxt('invalid_userid', file: 'Login');
		Utils::$context['member_id'] = 0;
	}

	/**
	 * Shows the page where we ask the user to supply a valid code.
	 */
	protected function showRetryInvalidCode(): void
	{
		if ($this->member->is_activated !== User::NOT_ACTIVATED) {
			ErrorHandler::fatalLang('already_activated', false);
		} elseif ($this->member->validation_code == '') {
			ErrorHandler::fatal(Lang::getTxt('registration_not_approved', ['url' => Config::$scripturl . '?action=activate;user=' . $this->member->username], file: 'Profile'), false);
		}

		Utils::$context['sub_template'] = 'retry_activate';
		Utils::$context['page_title'] = Lang::getTxt('invalid_activation_code', file: 'Login');
		Utils::$context['member_id'] = $this->member->id;
	}
}
