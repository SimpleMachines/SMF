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
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\Lang;
use SMF\Routable;
use SMF\Sapi;
use SMF\Security;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Validates the submitted credentials and logs the user in if they pass.
 */
class Login2 implements ActionInterface, Routable
{
	use ActionRouter;
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
	public string $subaction = 'main';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'main' => 'main',
		'salt' => 'updateSalt',
		'check' => 'checkCookie',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var User
	 *
	 * The member they are trying to login as.
	 */
	private User $member;

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

	public function isSimpleAction(): bool
	{
		return isset($_REQUEST['ajax']);
	}

	public function isAgreementAction(): bool
	{
		return true;
	}

	/**
	 * Actually logs you in.
	 *
	 * What it does:
	 * - checks credentials and checks that login was successful.
	 * - it employs protection against a specific IP or user trying to brute force
	 *   a login to an account.
	 * - upgrades password encryption on login, if necessary.
	 * - after successful login, redirects you to $_SESSION['login_url'].
	 * - accessed from ?action=login2, by forms.
	 *
	 * On error, uses the same templates Login() uses.
	 */
	public function execute(): void
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Sapi::httpsOn()) {
			ErrorHandler::fatalLang('login_ssl_required', false);
		}

		self::checkAjax();

		$call = \is_string(self::$subactions[$this->subaction]) && method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Update the user's password salt.
	 */
	public function updateSalt(): void
	{
		if (User::$me->is_guest) {
			return;
		}

		// First check for 2.1 json-format cookie in $_COOKIE
		if (isset($_COOKIE[Config::$cookiename]) && preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+~', $_COOKIE[Config::$cookiename]) === 1) {
			list(, , $timeout) = Utils::jsonDecode($_COOKIE[Config::$cookiename], true);
		}
		// Try checking for 2.1 json-format cookie in $_SESSION
		elseif (isset($_SESSION['login_' . Config::$cookiename]) && preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+~', $_SESSION['login_' . Config::$cookiename]) === 1) {
			list(, , $timeout) = Utils::jsonDecode($_SESSION['login_' . Config::$cookiename]);
		} else {
			throw new \Exception('login_no_session_cookie');
		}

		User::$me->password_salt = bin2hex(random_bytes(16));
		User::$me->save();

		// Preserve the 2FA cookie?
		if (!empty(Config::$modSettings['tfa_mode']) && !empty($_COOKIE[Config::$cookiename . '_tfa'])) {
			list(, , $exp) = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);
			Cookie::setTFACookie((int) $exp - time(), User::$me->id, Cookie::encrypt(User::$me->tfa_backup, User::$me->password_salt));
		}

		Cookie::setLoginCookie((int) $timeout - time(), User::$me->id, Cookie::encrypt(User::$me->passwd, User::$me->password_salt));

		Utils::redirectexit('action=login2;sa=check;member=' . User::$me->id, Sapi::needsLoginFix());
	}

	/**
	 * Double check the cookie...
	 */
	public function checkCookie(): void
	{
		// Strike!  You're outta there!
		if ($_GET['member'] != User::$me->id) {
			ErrorHandler::fatalLang('login_cookie_error', false);
		}

		User::$me->can_mod = User::$me->allowedTo('access_mod_center') || (!User::$me->is_guest && (User::$me->mod_cache['gq'] != '0=1' || User::$me->mod_cache['bq'] != '0=1' || (Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']))));

		// Some whitelisting for login_url...
		if (empty($_SESSION['login_url'])) {
			Utils::redirectexit(empty(User::$me->tfa_secret) ? '' : 'action=logintfa');
		} elseif (!empty($_SESSION['login_url']) && (!str_contains($_SESSION['login_url'], 'http://') && !str_contains($_SESSION['login_url'], 'https://'))) {
			unset($_SESSION['login_url']);
			Utils::redirectexit(empty(User::$me->tfa_secret) ? '' : 'action=logintfa');
		} elseif (!empty(User::$me->tfa_secret)) {
			Utils::redirectexit('action=logintfa');
		} else {
			// Best not to clutter the session data too much...
			$temp = $_SESSION['login_url'];
			unset($_SESSION['login_url']);

			Utils::redirectexit($temp);
		}
	}

	/**
	 * Performs checks and then logs the guest in (if they pass the tests).
	 */
	public function main(): void
	{
		// Beyond this point you are assumed to be a guest trying to login.
		if (!User::$me->is_guest) {
			Utils::redirectexit();
		}

		// Are you guessing with a script?
		// If cookies are disallowed, session & token checks will fail
		if (!empty($_COOKIE)) {
			User::$me->checkSession();
			SecurityToken::validate('login');
		}
		Security::spamProtection('login');

		// Set the login_url if it's not already set (but careful not to send us to an attachment).
		if (
			(
				empty($_SESSION['login_url'])
				&& isset($_SESSION['old_url'])
				&& strpos($_SESSION['old_url'], 'dlattach') === false
				&& preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0
			)
			|| (
				isset($_GET['quicklogin'], $_SESSION['old_url'])

				&& strpos($_SESSION['old_url'], 'login') === false
			)
		) {
			$_SESSION['login_url'] = $_SESSION['old_url'];
		}

		// Been guessing a lot, haven't we?
		if (
			isset($_SESSION['failed_login'])
			&& $_SESSION['failed_login'] >= Config::$modSettings['failed_login_threshold'] * 3
		) {
			ErrorHandler::fatalLang('login_threshold_fail', 'login');
		}

		// Load the template stuff.
		Theme::loadTemplate('Login');
		Utils::$context['sub_template'] = 'login';

		// Create a one time token.
		SecurityToken::create('login');

		// Set up the default/fallback stuff.
		Utils::$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#$1;', Utils::htmlspecialchars($_POST['user'])) : '';
		Utils::$context['default_password'] = '';
		Utils::$context['never_expire'] = !empty($_POST['cookieneverexp']);
		Utils::$context['login_errors'] = [Lang::getTxt('error_occured', file: 'General')];
		Utils::$context['page_title'] = Lang::getTxt('login', file: 'General');

		// Add the login chain to the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=login',
			'name' => Lang::getTxt('login', file: 'General'),
		];

		// Cookies are required...
		if (empty($_COOKIE)) {
			Cookie::setLoginCookie(Cookie::LENGTH_DEFAULT, 0, '');
			Utils::$context['login_errors'] = [Lang::getTxt('login_cookie_error', file: 'Errors')];

			return;
		}

		// Bail out if the username and/or password are obviously invalid.
		if (!$this->validateInput()) {
			return;
		}

		// Are we using any sort of integration to validate the login?
		if (
			\in_array(
				'retry',
				IntegrationHook::call(
					'integrate_validate_login',
					[
						$_POST['user'],
						$_POST['passwrd'] ?? null,
						// This is divided by 60 for compatibility with old mods that
						// expected a number of minutes rather than a number of seconds.
						(!empty($_POST['cookieneverexp']) ? Cookie::LENGTH_ONE_YEAR : Cookie::LENGTH_DEFAULT) / 60,
					],
				),
				true,
			)
		) {
			Utils::$context['login_errors'] = [Lang::getTxt('invalid_credentials', file: 'General')];

			return;
		}

		// Load the data up!
		$loaded = User::load($_POST['user'], User::LOAD_BY_NAME, User::$me->dataset);

		// Probably mistyped or their email, try it as an email address. (member_name first, though!)
		if (empty($loaded)) {
			$loaded = User::load($_POST['user'], User::LOAD_BY_EMAIL, User::$me->dataset);
		}

		// Let them try again, it didn't match anything...
		if (empty($loaded)) {
			Utils::$context['login_errors'] = [Lang::getTxt('invalid_credentials', file: 'General')];

			return;
		}

		$this->member = reset($loaded);

		// Bad password! Thought you could fool the database?!
		if (
			!Security::checkPassword(
				Utils::htmlspecialcharsDecode($_POST['passwrd']),
				$this->member,
				Security::PASSWORD_FALLBACK_ALL,
			)
		) {
			// They've messed up again - keep a count to see if they need a hand.
			$_SESSION['failed_login'] ??= 0;
			$_SESSION['failed_login']++;

			// Hmm... don't remember it, do you?  Here, try the password reminder ;).
			if ($_SESSION['failed_login'] >= Config::$modSettings['failed_login_threshold']) {
				Utils::redirectexit('action=reminder');
			}
			// We'll give you another chance...
			else {
				// Log an error so we know that it didn't go well in the error log.
				ErrorHandler::log(Lang::getTxt('incorrect_password', file: 'Login') . ' - <span class="remove">' . $this->member->username . '</span>', 'user');

				Utils::$context['login_errors'] = [Lang::getTxt('invalid_credentials', file: 'General')];
			}

			return;
		}

		// Correct password, but it took multiple tries...
		if (!empty($this->member->passwd_flood)) {
			// Let's be sure they weren't a little hacker.
			Security::validatePasswordFlood($this->member->id, $this->member->username, $this->member->passwd_flood, true);

			// If we got here then we can reset the flood counter.
			$this->member->passwd_flood = '';
			$this->member->save();
		}

		// Correct password, but they've got no salt. Fix it!
		if (\strlen($this->member->password_salt) < 32) {
			$this->member->password_salt = bin2hex(random_bytes(16));
			$this->member->save();
		}

		// Check their activation status.
		if (!$this->checkActivation()) {
			return;
		}

		$this->DoLogin();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Checks whether this is an AJAX request.
	 *
	 * If so, does the following:
	 *  1. Sets Utils::$context['from_ajax'] to true.
	 *  2. Sets Utils::$context['template_layers'] to an empty array.
	 */
	public static function checkAjax(): void
	{
		/*
		 * This is true when:
		 *
		 * 1. We have a valid header indicating a JQXHR request.
		 *    This is not sent during a cross domain request.
		 *
		 * OR
		 *
		 * 2. We have found:
		 *		a. valid CORS host
		 *  	b. a header indicating a SMF request
		 *  	c. The url has an 'ajax' in either the GET or POST
		 *
		 * These are not intended for security, but ensuring the request
		 * is intended for a JQXHR response.
		 */
		if (
			(
				!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
				&& $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
			)
			|| (
				!empty(Utils::$context['valid_cors_found'])
				&& !empty($_SERVER['HTTP_X_SMF_AJAX'])
				&& isset($_REQUEST['ajax'])
			)
		) {
			Utils::$context['from_ajax'] = true;
			Utils::$context['template_layers'] = [];
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}

	/**
	 * Checks that $_POST['user'] and $_POST['passwrd'] aren't obviously valid.
	 *
	 * @return bool False if either URL param is obviously invalid.
	 */
	protected function validateInput(): bool
	{
		// You forgot to type your username, dummy!
		if (!isset($_POST['user']) || $_POST['user'] == '') {
			Utils::$context['login_errors'] = [Lang::getTxt('need_username', file: 'Login')];

			return false;
		}

		// Hmm... maybe 'admin' will login with no password. Uhh... NO!
		if (!isset($_POST['passwrd']) || $_POST['passwrd'] == '') {
			Utils::$context['login_errors'] = [Lang::getTxt('no_password', file: 'Login')];

			return false;
		}

		// No funky symbols either.
		if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_POST['user'])) != 0) {
			Utils::$context['login_errors'] = [Lang::getTxt('error_invalid_characters_username', file: 'General')];

			return false;
		}

		// And if it's too long, trim it back.
		if (Utils::entityStrlen($_POST['user']) > 80) {
			$_POST['user'] = Utils::entitySubstr($_POST['user'], 0, 79);
			Utils::$context['default_username'] = preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#$1;', Utils::htmlspecialchars($_POST['user']));
		}

		return true;
	}

	/**
	 * Check activation status of the current user.
	 *
	 * @return bool True if they are activated, false otherwise.
	 */
	protected function checkActivation(): bool
	{
		if (!isset(Utils::$context['login_errors'])) {
			Utils::$context['login_errors'] = [];
		}

		// What is the true activation status of this account?
		$activation_status = $this->member->is_activated % User::BANNED;

		// Check if the account is activated - COPPA first...
		if ($activation_status == User::NEED_COPPA) {
			Utils::$context['login_errors'][] = Lang::getTxt('coppa_no_consent', file: 'Login') . ' <a href="' . Config::$scripturl . '?action=coppa;member=' . $this->member->id . '">' . Lang::getTxt('coppa_need_more_details', file: 'Login') . '</a>';

			return false;
		}

		// Awaiting approval still?
		if ($activation_status == User::UNAPPROVED) {
			ErrorHandler::fatalLang('still_awaiting_approval', 'user');
		}
		// Awaiting deletion, changed their mind?
		elseif (\in_array($activation_status, [User::REQUESTED_DELETE, User::REQUESTED_DELETE_ANONYMIZE])) {
			if (isset($_REQUEST['undelete'])) {
				$this->member->is_activated = $this->member->is_activated >= User::BANNED ? User::ACTIVATED_BANNED : User::ACTIVATED;
				$this->member->save();

				Config::updateModSettings(['unapprovedMembers' => (Config::$modSettings['unapprovedMembers'] > 0 ? Config::$modSettings['unapprovedMembers'] - 1 : 0)]);
			} else {
				Utils::$context['disable_login_hashing'] = true;
				Utils::$context['login_errors'][] = Lang::getTxt('awaiting_delete_account', file: 'Login');
				Utils::$context['login_show_undelete'] = true;

				return false;
			}
		}
		// Standard activation?
		elseif ($activation_status != User::ACTIVATED) {
			ErrorHandler::log(Lang::getTxt('activate_not_completed1', file: 'Login') . ' - <span class="remove">' . $this->member->username . '</span>', 'user');

			Utils::$context['login_errors'][] = Lang::getTxt('activate_not_completed1', file: 'Login') . ' <a href="' . Config::$scripturl . '?action=activate;sa=resend;u=' . $this->member->id . '">' . Lang::getTxt('activate_not_completed2', file: 'Login') . '</a>';

			return false;
		}

		return true;
	}

	/**
	 * Perform the logging in. (set cookie, call hooks, etc)
	 */
	protected function DoLogin(): void
	{
		// Call login integration functions.
		IntegrationHook::call(
			'integrate_login',
			[
				$this->member->username,
				null,
				// This is divided by 60 for compatibility with old mods that
				// expected a number of minutes rather than a number of seconds.
				(!empty(Utils::$context['never_expire']) ? Cookie::LENGTH_ONE_YEAR : Cookie::LENGTH_DEFAULT) / 60,
			],
		);

		// Get ready to set the cookie...
		User::setMe($this->member->id);
		User::$me->stay_logged_in = !empty(Utils::$context['never_expire']);

		// Bam!  Cookie set.  A session too, just in case.
		Cookie::setLoginCookie(User::$me->stay_logged_in ? Cookie::LENGTH_ONE_YEAR : Cookie::LENGTH_DEFAULT, User::$me->id, Cookie::encrypt(User::$me->passwd, User::$me->password_salt));

		// Reset the login threshold.
		if (isset($_SESSION['failed_login'])) {
			unset($_SESSION['failed_login']);
		}

		// Are you banned?
		User::$me->enforceBans(true);

		// Don't stick the language or theme after this point.
		unset($_SESSION['language'], $_SESSION['id_theme']);

		// First login?
		if (User::$me->last_login === 0) {
			$_SESSION['first_login'] = true;
		} else {
			unset($_SESSION['first_login']);
		}

		// You've logged in, haven't you?
		User::$me->ip = IP::getUserIP();
		User::$me->ip2 = IP::getUserIPAlternative();
		User::$me->validation_code = '';

		if (empty(User::$me->tfa_secret)) {
			User::$me->last_login = time();
		}

		User::$me->save();

		// Get rid of the online entry for that old guest....
		Db::$db->query(
			'DELETE FROM {db_prefix}log_online
			WHERE session = {string:session}',
			[
				'session' => 'ip' . User::$me->ip,
			],
		);
		$_SESSION['log_time'] = 0;

		// Log this entry, only if we have it enabled.
		if (!empty(Config::$modSettings['loginHistoryDays'])) {
			Db::$db->insert(
				'insert',
				'{db_prefix}member_logins',
				[
					'id_member' => 'int',
					'time' => 'int',
					'ip' => 'inet',
					'ip2' => 'inet',
				],
				[
					[
						User::$me->id,
						time(),
						User::$me->ip,
						User::$me->ip2,
					],
				],
				[
					'id_member', 'time',
				],
			);
		}

		// Just log you back out if it's in maintenance mode and you AREN'T an admin.
		if (empty(Config::$maintenance) || User::$me->allowedTo('admin_forum')) {
			Utils::redirectexit('action=login2;sa=check;member=' . User::$me->id, Sapi::needsLoginFix());
		} else {
			Utils::redirectexit('action=logout;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], Sapi::needsLoginFix());
		}
	}
}
