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
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Profile;
use SMF\Security;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\TimeZone;
use SMF\Url;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * Actually registers the new member.
 */
class Register2 extends Register
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Register2',
			'registerMember' => 'registerMember',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Registration fields that take strings.
	 */
	public array $possible_strings = [
		'birthdate',
		'timezone',
		'buddy_list',
		'pm_ignore_list',
		'smiley_set',
		'personal_text',
		'avatar',
		'lngfile',
		'secret_question',
		'secret_answer',
	];

	/**
	 * @var array
	 *
	 * Registration fields that take integers.
	 */
	public array $possible_ints = [
		'id_theme',
	];

	/**
	 * @var array
	 *
	 * Registration fields that take floats.
	 */
	public array $possible_floats = [
		'time_offset',
	];

	/**
	 * @var array
	 *
	 * Registration fields that take booleans.
	 */
	public array $possible_bools = [
		'show_online',
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
	 * Actually register the member.
	 */
	public function execute(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('register');

		// Check to ensure we're forcing SSL for authentication
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Config::httpsOn()) {
			ErrorHandler::fatalLang('register_ssl_required');
		}

		// You can't register if it's disabled.
		if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 3) {
			ErrorHandler::fatalLang('registration_disabled', false);
		}

		// Well, if you don't agree, you can't register.
		if ((!empty(Config::$modSettings['requireAgreement']) || !empty(Config::$modSettings['requirePolicyAgreement'])) && empty($_SESSION['registration_agreed'])) {
			Utils::redirectexit();
		}

		// Make sure they came from *somewhere*, have a session.
		if (!isset($_SESSION['old_url'])) {
			Utils::redirectexit('action=signup');
		}

		// If we require neither an agreement nor a privacy policy, we need a extra check for coppa.
		if (empty(Config::$modSettings['requireAgreement']) && empty(Config::$modSettings['requirePolicyAgreement']) && !empty(Config::$modSettings['coppaAge'])) {
			$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);
		}

		// Are they under age, and under age users are banned?
		if (!empty(Config::$modSettings['coppaAge']) && empty(Config::$modSettings['coppaType']) && empty($_SESSION['skip_coppa'])) {
			Lang::load('Errors');
			ErrorHandler::fatalLang('under_age_registration_prohibited', false, [Config::$modSettings['coppaAge']]);
		}

		// Check the time gate for miscreants. First make sure they came from somewhere that actually set it up.
		if (empty($_SESSION['register']['timenow']) || empty($_SESSION['register']['limit'])) {
			Utils::redirectexit('action=signup');
		}

		// Failing that, check the time on it.
		if (time() - $_SESSION['register']['timenow'] < $_SESSION['register']['limit']) {
			Lang::load('Errors');
			$this->errors[] = Lang::$txt['error_too_quickly'];
		}

		// Check whether the visual verification code was entered correctly.
		if (!empty(Config::$modSettings['reg_verification'])) {
			$verifier = new Verifier(['id' => 'register']);

			if (!empty($verifier->errors)) {
				Lang::load('Errors');

				foreach ($verifier->errors as $error) {
					$this->errors[] = Lang::$txt['error_' . $error];
				}
			}
		}

		array_walk_recursive(
			$_POST,
			function (&$value, $key) {
				// Normalize Unicode characters. (Does nothing if not in UTF-8 mode.)
				$value = Utils::normalize($value);

				// Replace any kind of space or illegal character with a normal space, and then trim.
				$value = Utils::htmlTrim(Utils::normalizeSpaces(Utils::sanitizeChars($value, 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));
			},
		);

		// We may want to add certain things to these if selected in the admin panel.
		if (!empty(Config::$modSettings['registration_fields'])) {
			$reg_fields = explode(',', Config::$modSettings['registration_fields']);

			// Website is a little different
			if (in_array('website', $reg_fields)) {
				$this->possible_strings = array_merge(['website_url', 'website_title'], $this->possible_strings);

				// Make sure their website URL is squeaky clean
				if (isset($_POST['website_url'])) {
					$_POST['website_url'] = (string) Url::create($_POST['website_url'], true)->validate();
				}
			}
		}

		if (isset($_POST['secret_answer']) && $_POST['secret_answer'] != '') {
			$_POST['secret_answer'] = Security::hashPassword($_POST['user'], $_POST['secret_answer']);
		}

		// Maybe you want set the displayed name during registration
		if (isset($_POST['real_name'])) {
			// Are you already allowed to edit the displayed name?
			if (User::$me->allowedTo('profile_displayed_name') || User::$me->allowedTo('moderate_forum')) {
				$can_edit_display_name = true;
			}
			// If you are a guest, will you be allowed to once you register?
			else {
				$request = Db::$db->query(
					'',
					'SELECT add_deny
					FROM {db_prefix}permissions
					WHERE id_group = {int:id_group} AND permission = {string:permission}',
					[
						'id_group' => 0,
						'permission' => 'profile_displayed_name_own',
					],
				);
				list($can_edit_display_name) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			}

			// Only set it if you can and if we are sure it is good
			if ($can_edit_display_name && Utils::htmlTrim($_POST['real_name']) != '' && !User::isReservedName($_POST['real_name']) && Utils::entityStrlen($_POST['real_name']) < 60) {
				$this->possible_strings[] = 'real_name';
			}
		}

		// Handle a string as a birthdate...
		if (isset($_POST['birthdate']) && $_POST['birthdate'] != '') {
			$_POST['birthdate'] = Time::strftime('%Y-%m-%d', strtotime($_POST['birthdate']));
		}
		// Or birthdate parts...
		elseif (!empty($_POST['bday1']) && !empty($_POST['bday2'])) {
			$_POST['birthdate'] = sprintf('%04d-%02d-%02d', empty($_POST['bday3']) ? 0 : (int) $_POST['bday3'], (int) $_POST['bday1'], (int) $_POST['bday2']);
		}

		// Validate the passed language file.
		if (isset($_POST['lngfile']) && !empty(Config::$modSettings['userLanguage'])) {
			// Do we have any languages?
			if (empty(Utils::$context['languages'])) {
				Lang::get();
			}

			// Did we find it?
			if (isset(Utils::$context['languages'][$_POST['lngfile']])) {
				$_SESSION['language'] = $_POST['lngfile'];
			} else {
				unset($_POST['lngfile']);
			}
		} else {
			unset($_POST['lngfile']);
		}

		// Set the options needed for registration.
		$reg_options = [
			'interface' => 'guest',
			'username' => !empty($_POST['user']) ? $_POST['user'] : '',
			'email' => !empty($_POST['email']) ? $_POST['email'] : '',
			'password' => !empty($_POST['passwrd1']) ? $_POST['passwrd1'] : '',
			'password_check' => !empty($_POST['passwrd2']) ? $_POST['passwrd2'] : '',
			'check_reserved_name' => true,
			'check_password_strength' => true,
			'check_email_ban' => true,
			'send_welcome_email' => !empty(Config::$modSettings['send_welcomeEmail']),
			'require' => !empty(Config::$modSettings['coppaAge']) && empty($_SESSION['skip_coppa']) ? 'coppa' : (empty(Config::$modSettings['registration_method']) ? 'nothing' : (Config::$modSettings['registration_method'] == 1 ? 'activation' : 'approval')),
			'extra_register_vars' => [],
			'theme_vars' => [],
		];

		// Include the additional options that might have been filled in.
		foreach ($this->possible_strings as $var) {
			if (isset($_POST[$var])) {
				$reg_options['extra_register_vars'][$var] = Utils::htmlspecialchars($_POST[$var], ENT_QUOTES);
			}
		}

		foreach ($this->possible_ints as $var) {
			if (isset($_POST[$var])) {
				$reg_options['extra_register_vars'][$var] = (int) $_POST[$var];
			}
		}

		foreach ($this->possible_floats as $var) {
			if (isset($_POST[$var])) {
				$reg_options['extra_register_vars'][$var] = (float) $_POST[$var];
			}
		}

		foreach ($this->possible_bools as $var) {
			if (isset($_POST[$var])) {
				$reg_options['extra_register_vars'][$var] = empty($_POST[$var]) ? 0 : 1;
			}
		}

		// Registration options are always default options...
		if (isset($_POST['default_options'])) {
			$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];
		}

		$reg_options['theme_vars'] = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : [];

		// Note when they accepted the agreement and privacy policy
		if (!empty(Config::$modSettings['requireAgreement'])) {
			$reg_options['theme_vars']['agreement_accepted'] = time();
		}

		if (!empty(Config::$modSettings['requirePolicyAgreement'])) {
			$reg_options['theme_vars']['policy_accepted'] = time();
		}

		// Make sure they are clean, dammit!
		$reg_options['theme_vars'] = Utils::htmlspecialcharsRecursive($reg_options['theme_vars']);

		// Check whether we have fields that simply MUST be displayed?
		$request = Db::$db->query(
			'',
			'SELECT col_name, field_name, field_type, field_length, mask, show_reg
			FROM {db_prefix}custom_fields
			WHERE active = {int:is_active}
			ORDER BY field_order',
			[
				'is_active' => 1,
			],
		);
		$custom_field_errors = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Don't allow overriding of the theme variables.
			if (isset($reg_options['theme_vars'][$row['col_name']])) {
				unset($reg_options['theme_vars'][$row['col_name']]);
			}

			// Not actually showing it then?
			if (!$row['show_reg']) {
				continue;
			}

			// Prepare the value!
			$value = isset($_POST['customfield'][$row['col_name']]) ? trim($_POST['customfield'][$row['col_name']]) : '';

			// We only care for text fields as the others are valid to be empty.
			if (!in_array($row['field_type'], ['check', 'select', 'radio'])) {
				// Is it too long?
				if ($row['field_length'] && $row['field_length'] < Utils::entityStrlen($value)) {
					$custom_field_errors[] = ['custom_field_too_long', [$row['field_name'], $row['field_length']]];
				}

				// Any masks to apply?
				if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none') {
					if ($row['mask'] == 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 255)) {
						$custom_field_errors[] = ['custom_field_invalid_email', [$row['field_name']]];
					} elseif ($row['mask'] == 'number' && preg_match('~[^\d]~', $value)) {
						$custom_field_errors[] = ['custom_field_not_number', [$row['field_name']]];
					} elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($row['mask'], 5), $value) === 0) {
						$custom_field_errors[] = ['custom_field_inproper_format', [$row['field_name']]];
					}
				}
			}

			// Is this required but not there?
			if (trim($value) == '' && $row['show_reg'] > 1) {
				$custom_field_errors[] = ['custom_field_empty', [$row['field_name']]];
			}
		}
		Db::$db->free_result($request);

		// Process any errors.
		if (!empty($custom_field_errors)) {
			Lang::load('Errors');

			foreach ($custom_field_errors as $error) {
				$this->errors[] = vsprintf(Lang::$txt['error_' . $error[0]], (array) $error[1]);
			}
		}

		// Lets check for other errors before trying to register the member.
		if (!empty($this->errors)) {
			$_REQUEST['step'] = 2;
			$_SESSION['register']['limit'] = 5; // If they've filled in some details, they won't need the full 10 seconds of the limit.
			$this->show();

			return;
		}

		$member_id = self::registerMember($reg_options, true);

		// What there actually an error of some kind dear boy?
		if (is_array($member_id)) {
			$this->errors = array_merge($this->errors, $member_id);
			$_REQUEST['step'] = 2;
			$this->show();

			return;
		}

		// Do our spam protection now.
		Security::spamProtection('register');

		// Do they want to receive announcements?
		$prefs = Notify::getNotifyPrefs($member_id, 'announcements', true);
		$var = !empty($_POST['notify_announcements']);
		$pref = !empty($prefs[$member_id]['announcements']);

		// Don't update if the default is the same.
		if ($var != $pref) {
			Notify::setNotifyPrefs($member_id, ['announcements' => (int) !empty($_POST['notify_announcements'])]);
		}

		// We'll do custom fields after as then we get to use the helper function!
		if (!empty($_POST['customfield'])) {
			Profile::load($member_id);
			Profile::$member->loadCustomFields('register');
			Profile::$member->save();
		}

		// If COPPA has been selected then things get complicated, setup the template.
		if (!empty(Config::$modSettings['coppaAge']) && empty($_SESSION['skip_coppa'])) {
			Utils::redirectexit('action=coppa;member=' . $member_id);
		}
		// Basic template variable setup.
		elseif (!empty(Config::$modSettings['registration_method'])) {
			Theme::loadTemplate('Register');

			Utils::$context += [
				'page_title' => Lang::$txt['register'],
				'title' => Lang::$txt['registration_successful'],
				'sub_template' => 'after',
				'description' => Config::$modSettings['registration_method'] == 2 ? Lang::$txt['approval_after_registration'] : Lang::$txt['activate_after_registration'],
			];
		} else {
			IntegrationHook::call('integrate_activate', [$reg_options['username']]);

			Cookie::setLoginCookie(60 * Config::$modSettings['cookieTime'], $member_id, Cookie::encrypt($reg_options['register_vars']['passwd'], $reg_options['register_vars']['password_salt']));

			Utils::redirectexit('action=login2;sa=check;member=' . $member_id, Utils::$context['server']['needs_login_fix']);
		}
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

	/**
	 * Registers a member to the forum.
	 *
	 * Allows two types of interface: 'guest' and 'admin'. The first
	 * includes hammering protection, the latter can perform the
	 * registration silently.
	 * The strings used in the options array are assumed to be escaped.
	 * Allows to perform several checks on the input, e.g. reserved names.
	 * The function will adjust member statistics.
	 * If an error is detected will fatal error on all errors unless return_errors is true.
	 *
	 * @param array $reg_options An array of registration options
	 * @param bool $return_errors Whether to return the errors
	 * @return int|array The ID of the newly registered user or an array of error info if $return_errors is true
	 */
	public static function registerMember(&$reg_options, $return_errors = false)
	{
		Lang::load('Login');

		// Put any errors in here.
		$reg_errors = [];

		// Registration from the admin center, let them sweat a little more.
		if ($reg_options['interface'] == 'admin') {
			User::$me->kickIfGuest();
			User::$me->isAllowedTo('moderate_forum');
		}
		// If you're an admin, you're special ;).
		elseif ($reg_options['interface'] == 'guest') {
			// You cannot register twice...
			if (empty(User::$me->is_guest)) {
				Utils::redirectexit();
			}

			// Make sure they didn't just register with this session.
			if (!empty($_SESSION['just_registered']) && empty(Config::$modSettings['disableRegisterCheck'])) {
				ErrorHandler::fatalLang('register_only_once', false);
			}
		}

		// Spaces and other odd characters are evil...
		$reg_options['username'] = trim(Utils::normalizeSpaces(Utils::sanitizeChars($reg_options['username'], 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));

		// Convert character encoding for non-utf8mb4 database
		$reg_options['username'] = Utils::htmlspecialchars($reg_options['username']);

		// @todo Separate the sprintf?
		if (empty($reg_options['email']) || !filter_var($reg_options['email'], FILTER_VALIDATE_EMAIL) || strlen($reg_options['email']) > 255) {
			$reg_errors[] = ['lang', 'profile_error_bad_email'];
		}

		$username_validation_errors = User::validateUsername(0, $reg_options['username'], true, !empty($reg_options['check_reserved_name']));

		if (!empty($username_validation_errors)) {
			$reg_errors = array_merge($reg_errors, $username_validation_errors);
		}

		// Generate a validation code if it's supposed to be emailed.
		$validation_code = '';

		if ($reg_options['require'] == 'activation') {
			$validation_code = User::generateValidationCode();
		}

		// If you haven't put in a password generate one.
		if ($reg_options['interface'] == 'admin' && $reg_options['password'] == '') {
			$reg_options['password'] = User::generateValidationCode();
			$reg_options['password_check'] = $reg_options['password'];
		}
		// Does the first password match the second?
		elseif ($reg_options['password'] != $reg_options['password_check']) {
			$reg_errors[] = ['lang', 'passwords_dont_match'];
		}

		// That's kind of easy to guess...
		if ($reg_options['password'] == '') {
			$reg_errors[] = ['lang', 'no_password'];
		}

		// Now perform hard password validation as required.
		if (!empty($reg_options['check_password_strength']) && $reg_options['password'] != '') {
			$password_error = User::validatePassword($reg_options['password'], $reg_options['username'], [$reg_options['email']]);

			// Password isn't legal?
			if ($password_error != null) {
				$error_code = ['lang', 'profile_error_password_' . $password_error, false];

				if ($password_error == 'short') {
					$error_code[] = [empty(Config::$modSettings['password_strength']) ? 4 : 8];
				}

				$reg_errors[] = $error_code;
			}
		}

		// You may not be allowed to register this email.
		if (!empty($reg_options['check_email_ban'])) {
			User::isBannedEmail($reg_options['email'], 'cannot_register', Lang::$txt['ban_register_prohibited']);
		}

		// Check if the email address is in use.
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}members
			WHERE email_address = {string:email_address}
				OR email_address = {string:username}
			LIMIT 1',
			[
				'email_address' => $reg_options['email'],
				'username' => $reg_options['username'],
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			$reg_errors[] = ['lang', 'email_in_use', false, [Utils::htmlspecialchars($reg_options['email'])]];
		}
		Db::$db->free_result($request);

		// Perhaps someone else wants to check this user.
		IntegrationHook::call('integrate_register_check', [&$reg_options, &$reg_errors]);

		// If we found any errors we need to do something about it right away!
		foreach ($reg_errors as $key => $error) {
			/* Note for each error:
				0 = 'lang' if it's an index, 'done' if it's clear text.
				1 = The text/index.
				2 = Whether to log.
				3 = sprintf data if necessary. */
			if ($error[0] == 'lang') {
				Lang::load('Errors');
			}

			$message = $error[0] == 'lang' ? (empty($error[3]) ? Lang::$txt[$error[1]] : vsprintf(Lang::$txt[$error[1]], (array) $error[3])) : $error[1];

			// What to do, what to do, what to do.
			if ($return_errors) {
				if (!empty($error[2])) {
					ErrorHandler::log($message, $error[2]);
				}

				$reg_errors[$key] = $message;
			} else {
				ErrorHandler::fatal($message, empty($error[2]) ? false : $error[2]);
			}
		}

		// If there's any errors left return them at once!
		if (!empty($reg_errors)) {
			return $reg_errors;
		}

		$reserved_vars = [
			'actual_theme_url',
			'actual_images_url',
			'base_theme_dir',
			'base_theme_url',
			'default_images_url',
			'default_theme_dir',
			'default_theme_url',
			'default_template',
			'images_url',
			'number_recent_posts',
			'smiley_sets_default',
			'theme_dir',
			'theme_id',
			'theme_layers',
			'theme_templates',
			'theme_url',
		];

		// Can't change reserved vars.
		if (isset($reg_options['theme_vars']) && count(array_intersect(array_keys($reg_options['theme_vars']), $reserved_vars)) != 0) {
			ErrorHandler::fatalLang('no_theme');
		}

		// Some of these might be overwritten. (the lower ones that are in the arrays below.)
		$reg_options['register_vars'] = [
			'member_name' => $reg_options['username'],
			'email_address' => $reg_options['email'],
			'passwd' => Security::hashPassword($reg_options['username'], $reg_options['password']),
			'password_salt' => bin2hex(random_bytes(16)),
			'posts' => 0,
			'date_registered' => time(),
			'member_ip' => $reg_options['interface'] == 'admin' ? '127.0.0.1' : User::$me->ip,
			'member_ip2' => $reg_options['interface'] == 'admin' ? '127.0.0.1' : $_SERVER['BAN_CHECK_IP'],
			'validation_code' => $validation_code,
			'real_name' => $reg_options['username'],
			'personal_text' => Config::$modSettings['default_personal_text'],
			'id_theme' => 0,
			'id_post_group' => 4,
			'lngfile' => '',
			'buddy_list' => '',
			'pm_ignore_list' => '',
			'website_title' => '',
			'website_url' => '',
			'time_format' => '',
			'signature' => '',
			'avatar' => '',
			'usertitle' => '',
			'secret_question' => '',
			'secret_answer' => '',
			'additional_groups' => '',
			'ignore_boards' => '',
			'smiley_set' => '',
			'timezone' => empty(Config::$modSettings['default_timezone']) || !array_key_exists(Config::$modSettings['default_timezone'], TimeZone::list()) ? 'UTC' : Config::$modSettings['default_timezone'],
		];

		// Setup the activation status on this new account so it is correct - firstly is it an under age account?
		if ($reg_options['require'] == 'coppa') {
			$reg_options['register_vars']['is_activated'] = 5;

			// @todo This should be changed.  To what should be it be changed??
			$reg_options['register_vars']['validation_code'] = '';
		}
		// Maybe it can be activated right away?
		elseif ($reg_options['require'] == 'nothing') {
			$reg_options['register_vars']['is_activated'] = 1;
		}
		// Maybe it must be activated by email?
		elseif ($reg_options['require'] == 'activation') {
			$reg_options['register_vars']['is_activated'] = 0;
		}
		// Otherwise it must be awaiting approval!
		else {
			$reg_options['register_vars']['is_activated'] = 3;
		}

		// Check if this group is assignable.
		if (isset($reg_options['memberGroup'])) {
			$reg_options['register_vars']['id_group'] = in_array($reg_options['memberGroup'], Group::getUnassignable()) ? Group::REGULAR : $reg_options['memberGroup'];
		}

		// Verify that timezone is correct, if provided.
		if (
			!empty($reg_options['extra_register_vars'])
			&& !empty($reg_options['extra_register_vars']['timezone'])
			&& !array_key_exists($reg_options['extra_register_vars']['timezone'], TimeZone::list())
		) {
			unset($reg_options['extra_register_vars']['timezone']);
		}

		// Integrate optional member settings to be set.
		if (!empty($reg_options['extra_register_vars'])) {
			foreach ($reg_options['extra_register_vars'] as $var => $value) {
				$reg_options['register_vars'][$var] = $value;
			}
		}

		// Integrate optional user theme options to be set.
		$theme_vars = [];

		if (!empty($reg_options['theme_vars'])) {
			foreach ($reg_options['theme_vars'] as $var => $value) {
				$theme_vars[$var] = $value;
			}
		}

		// Right, now let's prepare for insertion.
		$known_ints = [
			'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
			'new_pm', 'pm_prefs', 'show_online',
			'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
		];
		$known_floats = [
			'time_offset',
		];
		$known_inets = [
			'member_ip', 'member_ip2',
		];

		// Call an optional function to validate the users' input.
		IntegrationHook::call('integrate_register', [&$reg_options, &$theme_vars, &$known_ints, &$known_floats]);

		$column_names = [];
		$values = [];

		foreach ($reg_options['register_vars'] as $var => $val) {
			$type = 'string';

			if (in_array($var, $known_ints)) {
				$type = 'int';
			} elseif (in_array($var, $known_floats)) {
				$type = 'float';
			} elseif (in_array($var, $known_inets)) {
				$type = 'inet';
			} elseif ($var == 'birthdate') {
				$type = 'date';
			}

			$column_names[$var] = $type;
			$values[$var] = $val;
		}

		// Register them into the database.
		$member_id = Db::$db->insert(
			'',
			'{db_prefix}members',
			$column_names,
			$values,
			['id_member'],
			1,
		);

		// Call an optional function as notification of registration.
		IntegrationHook::call('integrate_post_register', [&$reg_options, &$theme_vars, &$member_id]);

		// Update the number of members and latest member's info - and pass the name, but remove the 's.
		if ($reg_options['register_vars']['is_activated'] == 1) {
			Logging::updateStats('member', $member_id, $reg_options['register_vars']['real_name']);
		} else {
			Logging::updateStats('member');
		}

		// Theme variables too?
		if (!empty($theme_vars)) {
			$inserts = [];

			foreach ($theme_vars as $var => $val) {
				$inserts[] = [$member_id, $var, $val];
			}

			Db::$db->insert(
				'insert',
				'{db_prefix}themes',
				['id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
				$inserts,
				['id_member', 'variable'],
			);
		}

		// Log their acceptance of the agreement and privacy policy, for future reference.
		foreach (['agreement_accepted', 'policy_accepted'] as $key) {
			if (!empty($theme_vars[$key])) {
				Logging::logAction($key, ['member_affected' => $member_id, 'applicator' => $member_id], 'user');
			}
		}

		// If it's enabled, increase the registrations for today.
		Logging::trackStats(['registers' => '+']);

		// Administrative registrations are a bit different...
		if ($reg_options['interface'] == 'admin') {
			if ($reg_options['require'] == 'activation') {
				$email_message = 'admin_register_activate';
			} elseif (!empty($reg_options['send_welcome_email'])) {
				$email_message = 'admin_register_immediate';
			}

			if (isset($email_message)) {
				$replacements = [
					'REALNAME' => $reg_options['register_vars']['real_name'],
					'USERNAME' => $reg_options['username'],
					'PASSWORD' => $reg_options['password'],
					'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
					'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $member_id . ';code=' . $validation_code,
					'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $member_id,
					'ACTIVATIONCODE' => $validation_code,
				];

				$emaildata = Mail::loadEmailTemplate($email_message, $replacements);

				Mail::send($reg_options['email'], $emaildata['subject'], $emaildata['body'], null, $email_message . $member_id, $emaildata['is_html'], 0);
			}

			// All admins are finished here.
			return $member_id;
		}

		// Can post straight away - welcome them to your fantastic community...
		if ($reg_options['require'] == 'nothing') {
			if (!empty($reg_options['send_welcome_email'])) {
				$replacements = [
					'REALNAME' => $reg_options['register_vars']['real_name'],
					'USERNAME' => $reg_options['username'],
					'PASSWORD' => $reg_options['password'],
					'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
				];

				$emaildata = Mail::loadEmailTemplate('register_immediate', $replacements);

				Mail::send($reg_options['email'], $emaildata['subject'], $emaildata['body'], null, 'register', $emaildata['is_html'], 0);
			}

			// Send admin their notification.
			Mail::adminNotify('standard', $member_id, $reg_options['username']);
		}
		// Need to activate their account - or fall under COPPA.
		elseif ($reg_options['require'] == 'activation' || $reg_options['require'] == 'coppa') {
			$replacements = [
				'REALNAME' => $reg_options['register_vars']['real_name'],
				'USERNAME' => $reg_options['username'],
				'PASSWORD' => $reg_options['password'],
				'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
			];

			if ($reg_options['require'] == 'activation') {
				$replacements += [
					'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $member_id . ';code=' . $validation_code,
					'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $member_id,
					'ACTIVATIONCODE' => $validation_code,
				];
			} else {
				$replacements += [
					'COPPALINK' => Config::$scripturl . '?action=coppa;member=' . $member_id,
				];
			}

			$emaildata = Mail::loadEmailTemplate('register_' . ($reg_options['require'] == 'activation' ? 'activate' : 'coppa'), $replacements);

			Mail::send($reg_options['email'], $emaildata['subject'], $emaildata['body'], null, 'reg_' . $reg_options['require'] . $member_id, $emaildata['is_html'], 0);
		}
		// Must be awaiting approval.
		else {
			$replacements = [
				'REALNAME' => $reg_options['register_vars']['real_name'],
				'USERNAME' => $reg_options['username'],
				'PASSWORD' => $reg_options['password'],
				'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
			];

			$emaildata = Mail::loadEmailTemplate('register_pending', $replacements);

			Mail::send($reg_options['email'], $emaildata['subject'], $emaildata['body'], null, 'reg_pending', $emaildata['is_html'], 0);

			// Admin gets informed here...
			Mail::adminNotify('approval', $member_id, $reg_options['username']);
		}

		// Okay, they're for sure registered... make sure the session is aware of this for security. (Just married :P!)
		$_SESSION['just_registered'] = 1;

		// If they are for sure registered, let other people to know about it
		IntegrationHook::call('integrate_register_after', [$reg_options, $member_id]);

		return $member_id;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Register2::exportStatic')) {
	Register2::exportStatic();
}

?>