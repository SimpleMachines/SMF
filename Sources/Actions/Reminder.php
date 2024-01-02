<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Mail;
use SMF\Security;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Handle sending out reminders, and checking the secret answer and question.
 */
class Reminder implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'RemindMe',
		],
	];

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

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var object
	 *
	 * SMF\User object for the member.
	 */
	public object $member;

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
		'picktype' => 'pickType',
		'secret2' => 'secretAnswer2',
		'setpassword' => 'setPassword',
		'setpassword2' => 'setPassword2',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Just shows the main template to ask for a more specific sub-action.
	 */
	public function main()
	{
		SecurityToken::create('remind');
	}

	/**
	 * Allows the user to pick how they wish to be reminded.
	 */
	public function pickType()
	{
		User::$me->checkSession();
		SecurityToken::validate('remind');
		SecurityToken::create('remind');

		// Make sure we are not being slammed
		// Don't call this if you're coming from the "Choose a reminder type" page - otherwise you'll likely get an error
		if (!in_array($_POST['reminder_type'] ?? null, ['email', 'secret'])) {
			Security::spamProtection('remind');
		}

		$this->loadMember();

		// If the user isn't activated/approved, give them some feedback on what to do next.
		if ($this->member->is_activated != 1) {
			// Awaiting approval...
			if (trim($this->member->validation_code) == '') {
				ErrorHandler::fatal(sprintf(Lang::$txt['registration_not_approved'], Config::$scripturl . '?action=activate;user=' . $_POST['user']), false);
			} else {
				ErrorHandler::fatal(sprintf(Lang::$txt['registration_not_activated'], Config::$scripturl . '?action=activate;user=' . $_POST['user']), false);
			}
		}

		// You can't get emailed if you have no email address.
		$this->member->email = trim($this->member->email);

		if ($this->member->email == '') {
			ErrorHandler::fatal(Lang::$txt['no_reminder_email'] . '<br>' . Lang::$txt['send_email_to'] . ' <a href="mailto:' . Config::$webmaster_email . '">' . Lang::$txt['webmaster'] . '</a> ' . Lang::$txt['to_ask_password']);
		}

		// If they have no secret question then they can only get emailed the item, or they are requesting the email, send them an email.
		if (empty($this->member->secret_question) || (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'email')) {
			// Randomly generate a validation code with a max length of 10 chars.
			$code = User::generateValidationCode();

			$replacements = [
				'REALNAME' => $this->member->name,
				'REMINDLINK' => Config::$scripturl . '?action=reminder;sa=setpassword;u=' . $this->member->id . ';code=' . $code,
				'IP' => User::$me->ip,
				'MEMBERNAME' => $this->member->username,
			];

			$emaildata = Mail::loadEmailTemplate('forgot_password', $replacements, empty($this->member->lngfile) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $this->member->lngfile);

			Mail::send($this->member->email, $emaildata['subject'], $emaildata['body'], null, 'reminder', $emaildata['is_html'], 1);

			// Set the validation code in the database.
			User::updateMemberData($this->member->id, ['validation_code' => $code]);

			// Set up the template.
			Utils::$context['description'] = Lang::$txt['reminder_sent'];
			Utils::$context['sub_template'] = 'sent';

			// Don't really.
			return;
		}

		// Otherwise are ready to answer the question?
		if (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'secret') {
			return self::secretAnswerInput();
		}

		// Now we're here setup the context for template number 2!
		Utils::$context['sub_template'] = 'reminder_pick';
		Utils::$context['current_member'] = [
			'id' => $this->member->id,
			'name' => $this->member->username,
		];
	}

	/**
	 * Allows the user to set their new password.
	 */
	public function setPassword()
	{
		Lang::load('Login');

		// You need a code!
		if (!isset($_REQUEST['code'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Fill the context array.
		Utils::$context += [
			'page_title' => Lang::$txt['reminder_set_password'],
			'sub_template' => 'set_password',
			'code' => $_REQUEST['code'],
			'memID' => (int) $_REQUEST['u'],
		];

		Theme::loadJavaScriptFile('register.js', ['defer' => false, 'minimize' => true], 'smf_register');

		// Tokens!
		SecurityToken::create('remind-sp');
	}

	/**
	 * Actually sets the new password.
	 */
	public function setPassword2()
	{
		User::$me->checkSession();
		SecurityToken::validate('remind-sp');

		if (empty($_POST['u']) || !isset($_POST['passwrd1']) || !isset($_POST['passwrd2'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$_POST['u'] = (int) $_POST['u'];

		if ($_POST['passwrd1'] != $_POST['passwrd2']) {
			ErrorHandler::fatalLang('passwords_dont_match', false);
		}

		if ($_POST['passwrd1'] == '') {
			ErrorHandler::fatalLang('no_password', false);
		}

		Lang::load('Login');

		$this->loadMember();

		// Is the password actually valid?
		$passwordError = User::validatePassword($_POST['passwrd1'], $this->member->username, [$this->member->email]);

		// What - it's not?
		if ($passwordError != null) {
			if ($passwordError == 'short') {
				ErrorHandler::fatalLang('profile_error_password_' . $passwordError, false, [empty(Config::$modSettings['password_strength']) ? 4 : 8]);
			} else {
				ErrorHandler::fatalLang('profile_error_password_' . $passwordError, false);
			}
		}

		// Quit if this code is not right.
		if (empty($_POST['code']) || $this->member->validation_code !== $_POST['code']) {
			// Stop brute force attacks like this.
			Login2::validatePasswordFlood($this->member->id, $this->member->username, $this->member->passwd_flood, false);

			ErrorHandler::fatal(Lang::$txt['invalid_activation_code'], false);
		}

		// Just in case, flood control.
		Login2::validatePasswordFlood($this->member->id, $this->member->username, $this->member->passwd_flood, true);

		// User validated.  Update the database!
		User::updateMemberData($this->member->id, ['validation_code' => '', 'passwd' => Security::hashPassword($this->member->username, $_POST['passwrd1'])]);

		IntegrationHook::call('integrate_reset_pass', [$this->member->username, $this->member->username, $_POST['passwrd1']]);

		Theme::loadTemplate('Login');
		Utils::$context += [
			'page_title' => Lang::$txt['reminder_password_set'],
			'sub_template' => 'login',
			'default_username' => $this->member->username,
			'default_password' => $_POST['passwrd1'],
			'never_expire' => false,
			'description' => Lang::$txt['reminder_password_set'],
		];

		SecurityToken::create('login');
	}

	/**
	 * Allows the user to enter their secret answer.
	 */
	public function secretAnswerInput()
	{
		User::$me->checkSession();

		// Strings for the register auto javascript clever stuffy wuffy.
		Lang::load('Login');

		// This should never happen, but just in case...
		if (!isset($this->member)) {
			$this->loadMember();
		}

		// If there is NO secret question - then throw an error.
		if (trim($this->member->secret_question) == '') {
			ErrorHandler::fatalLang('registration_no_secret_question', false);
		}

		// Ask for the answer...
		Utils::$context['remind_user'] = $this->member->id_member;
		Utils::$context['remind_type'] = '';
		Utils::$context['secret_question'] = $this->member->secret_question;

		Utils::$context['sub_template'] = 'ask';
		SecurityToken::create('remind-sai');
		Theme::loadJavaScriptFile('register.js', ['defer' => false, 'minimize' => true], 'smf_register');
	}

	/**
	 * Validates the secret answer input by the user.
	 */
	public function secretAnswer2()
	{
		User::$me->checkSession();
		SecurityToken::validate('remind-sai');

		// Hacker?  How did you get this far without an email or username?
		if (empty($_REQUEST['uid'])) {
			ErrorHandler::fatalLang('username_no_exist', false);
		}

		Lang::load('Login');

		$this->loadMember();

		/*
		 * Check if the secret answer is correct.
		 *
		 * In 2.1 this was changed to use hash_(verify_)passsword, same as the
		 * password. The length of the hash is 60 characters.
		 *
		 * Prior to 2.1 this was a simple md5. The length of the hash is 32
		 * characters.
		 *
		 * For compatibility with older answers, we still check if a match
		 * occurs on md5. If it does, we automatically upgrade the stored
		 * version from md5 to hash_password.
		 */
		if (
			$this->member->secret_question == ''
			|| $this->member->secret_answer == ''
			|| (
				!Security::hashVerifyPassword($this->member->username, $_POST['secret_answer'], $this->member->secret_answer)
				&& md5($_POST['secret_answer']) != $this->member->secret_answer
			)
		) {
			ErrorHandler::log(sprintf(Lang::$txt['reminder_error'], $this->member->username), 'user');
			ErrorHandler::fatalLang('incorrect_answer', false);
		}

		// If the secret answer was right, but stored using md5, upgrade it now.
		if (md5($_POST['secret_answer']) === $this->member->secret_answer) {
			User::updateMemberData($this->member->id_member, ['secret_answer' => Security::hashPassword($this->member->username, $_POST['secret_answer'])]);
		}

		// You can't use a blank one!
		if (strlen(trim($_POST['passwrd1'])) === 0) {
			ErrorHandler::fatalLang('no_password', false);
		}

		// They have to be the same too.
		if ($_POST['passwrd1'] != $_POST['passwrd2']) {
			ErrorHandler::fatalLang('passwords_dont_match', false);
		}

		// Make sure they have a strong enough password.
		$passwordError = User::validatePassword($_POST['passwrd1'], $this->member->username, [$this->member->email]);

		// Invalid?
		if ($passwordError != null) {
			if ($passwordError == 'short') {
				ErrorHandler::fatalLang('profile_error_password_' . $passwordError, false, [empty(Config::$modSettings['password_strength']) ? 4 : 8]);
			} else {
				ErrorHandler::fatalLang('profile_error_password_' . $passwordError, false);
			}
		}

		// Alright, so long as 'yer sure.
		User::updateMemberData($this->member->id_member, ['passwd' => Security::hashPassword($this->member->username, $_POST['passwrd1'])]);

		IntegrationHook::call('integrate_reset_pass', [$this->member->username, $this->member->username, $_POST['passwrd1']]);

		// Tell them it went fine.
		Theme::loadTemplate('Login');
		Utils::$context += [
			'page_title' => Lang::$txt['reminder_password_set'],
			'sub_template' => 'login',
			'default_username' => $this->member->username,
			'default_password' => $_POST['passwrd1'],
			'never_expire' => false,
			'description' => Lang::$txt['reminder_password_set'],
		];

		SecurityToken::create('login');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		Lang::load('Profile');
		Theme::loadTemplate('Reminder');

		Utils::$context['page_title'] = Lang::$txt['authentication_reminder'];
		Utils::$context['robot_no_index'] = true;

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}

	/**
	 * Loads the requested member.
	 */
	protected function loadMember()
	{
		$loaded = [];
		$err_msg = 'username_no_exist';

		// Coming with a known ID?
		$uid = intval(!empty($_REQUEST['uid']) ? $_REQUEST['uid'] : (!empty($_POST['u']) ? $_POST['u'] : 0));

		// If given a name or email, clean it up like we do during registration.
		if (isset($_POST['user'])) {
			$_POST['user'] = Utils::htmlTrim(Utils::normalizeSpaces(Utils::sanitizeChars($_POST['user'], 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));
		}

		// Load by ID.
		if (!empty($uid)) {
			$err_msg = 'invalid_userid';
			$loaded = User::load($uid, User::LOAD_BY_ID, 'minimal');
		}
		// Load by name or email.
		elseif (isset($_POST['user']) && $_POST['user'] != '') {
			$loaded = User::load($_POST['user'], User::LOAD_BY_NAME, 'minimal');

			if (empty($loaded)) {
				$loaded = User::load($_POST['user'], User::LOAD_BY_EMAIL, 'minimal');
			}
		}

		// Nothing found.
		if (empty($loaded)) {
			ErrorHandler::fatalLang($err_msg, false);
		}

		$this->member = reset($loaded);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Reminder::exportStatic')) {
	Reminder::exportStatic();
}

?>