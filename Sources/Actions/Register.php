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
use SMF\BBCodeParser;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Profile;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * Shows the registration form.
 */
class Register implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'register' => 'Register',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The sub-action to call.
	 */
	public string $subaction = 'show';

	/**
	 * @var array
	 *
	 * Errors encountered while trying to register.
	 */
	public array $errors = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'show' => 'show',
		'usernamecheck' => 'checkUsername',
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
	 * Begin the registration process.
	 */
	public function show(): void
	{
		// Check if the administrator has it disabled.
		if (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == '3') {
			ErrorHandler::fatalLang('registration_disabled', false);
		}

		// If this user is an admin - redirect them to the admin registration page.
		if (User::$me->allowedTo('moderate_forum') && !User::$me->is_guest) {
			Utils::redirectexit('action=admin;area=regcenter;sa=register');
		}
		// You are not a guest, so you are a member - and members don't get to register twice!
		elseif (empty(User::$me->is_guest)) {
			Utils::redirectexit();
		}

		Lang::load('Login');
		Theme::loadTemplate('Register');

		// How many steps have we done so far today?
		$current_step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : (!empty(Config::$modSettings['requireAgreement']) || !empty(Config::$modSettings['requirePolicyAgreement']) ? 1 : 2);

		// Do we need them to agree to the registration agreement and/or privacy policy agreement, first?
		Utils::$context['registration_passed_agreement'] = !empty($_SESSION['registration_agreed']);
		Utils::$context['show_coppa'] = !empty(Config::$modSettings['coppaAge']);

		$agree_txt_key = '';

		if ($current_step == 1) {
			if (!empty(Config::$modSettings['requireAgreement']) && !empty(Config::$modSettings['requirePolicyAgreement'])) {
				$agree_txt_key = 'agreement_policy_';
			} elseif (!empty(Config::$modSettings['requireAgreement'])) {
				$agree_txt_key = 'agreement_';
			} elseif (!empty(Config::$modSettings['requirePolicyAgreement'])) {
				$agree_txt_key = 'policy_';
			}
		}

		// Under age restrictions?
		if (Utils::$context['show_coppa']) {
			Utils::$context['skip_coppa'] = false;
			Utils::$context['coppa_agree_above'] = sprintf(Lang::$txt[$agree_txt_key . 'agree_coppa_above'], Config::$modSettings['coppaAge']);
			Utils::$context['coppa_agree_below'] = sprintf(Lang::$txt[$agree_txt_key . 'agree_coppa_below'], Config::$modSettings['coppaAge']);
		} elseif ($agree_txt_key != '') {
			Utils::$context['agree'] = Lang::$txt[$agree_txt_key . 'agree'];
		}

		// Does this user agree to the registration agreement?
		if ($current_step == 1 && (isset($_POST['accept_agreement']) || isset($_POST['accept_agreement_coppa']))) {
			Utils::$context['registration_passed_agreement'] = $_SESSION['registration_agreed'] = true;
			$current_step = 2;

			// Skip the coppa procedure if the user says he's old enough.
			if (Utils::$context['show_coppa']) {
				$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);

				// Are they saying they're under age, while under age registration is disabled?
				if (empty(Config::$modSettings['coppaType']) && empty($_SESSION['skip_coppa'])) {
					Lang::load('Login');
					ErrorHandler::fatalLang('under_age_registration_prohibited', false, [Config::$modSettings['coppaAge']]);
				}
			}
		}
		// Make sure they don't squeeze through without agreeing.
		elseif ($current_step > 1 && (!empty(Config::$modSettings['requireAgreement']) || !empty(Config::$modSettings['requirePolicyAgreement'])) && !Utils::$context['registration_passed_agreement']) {
			$current_step = 1;
		}

		// Show the user the right form.
		Utils::$context['sub_template'] = $current_step == 1 ? 'registration_agreement' : 'registration_form';
		Utils::$context['page_title'] = $current_step == 1 ? Lang::$txt['registration_agreement'] : Lang::$txt['registration_form'];

		// Kinda need this.
		if (Utils::$context['sub_template'] == 'registration_form') {
			Theme::loadJavaScriptFile('register.js', ['defer' => false, 'minimize' => true], 'smf_register');
		}

		// Add the register chain to the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=signup',
			'name' => Lang::$txt['register'],
		];

		// Prepare the time gate! Do it like so, in case later steps want to reset the limit for any reason, but make sure the time is the current one.
		if (!isset($_SESSION['register'])) {
			$_SESSION['register'] = [
				'timenow' => time(),
				'limit' => 10, // minimum number of seconds required on this page for registration
			];
		} else {
			$_SESSION['register']['timenow'] = time();
		}

		// If you have to agree to the agreement, it needs to be fetched from the file.
		if (!empty(Config::$modSettings['requireAgreement'])) {
			// Have we got a localized one?
			if (file_exists(Config::$boarddir . '/agreement.' . User::$me->language . '.txt')) {
				Utils::$context['agreement'] = BBCodeParser::load()->parse(file_get_contents(Config::$boarddir . '/agreement.' . User::$me->language . '.txt'), true, 'agreement_' . User::$me->language);
			} elseif (file_exists(Config::$boarddir . '/agreement.txt')) {
				Utils::$context['agreement'] = BBCodeParser::load()->parse(file_get_contents(Config::$boarddir . '/agreement.txt'), true, 'agreement');
			} else {
				Utils::$context['agreement'] = '';
			}

			// Nothing to show, lets disable registration and inform the admin of this error
			if (empty(Utils::$context['agreement'])) {
				// No file found or a blank file, log the error so the admin knows there is a problem!
				ErrorHandler::log(Lang::$txt['registration_agreement_missing'], 'critical');
				ErrorHandler::fatalLang('registration_disabled', false);
			}
		}

		$prefs = Notify::getNotifyPrefs(0, 'announcements');
		Utils::$context['notify_announcements'] = !empty($prefs[0]['announcements']);

		if (!empty(Config::$modSettings['userLanguage'])) {
			$selectedLanguage = empty($_SESSION['language']) ? Lang::$default : $_SESSION['language'];

			// Do we have any languages?
			if (empty(Utils::$context['languages'])) {
				Lang::get();
			}

			// Try to find our selected language.
			foreach (Utils::$context['languages'] as $key => $lang) {
				Utils::$context['languages'][$key]['name'] = strtr($lang['name'], ['-utf8' => '']);

				// Found it!
				if ($selectedLanguage == $lang['filename']) {
					Utils::$context['languages'][$key]['selected'] = true;
				}
			}
		}

		// If you have to agree to the privacy policy, it needs to be loaded from the database.
		if (!empty(Config::$modSettings['requirePolicyAgreement'])) {
			// Have we got a localized one?
			if (!empty(Config::$modSettings['policy_' . User::$me->language])) {
				Utils::$context['privacy_policy'] = BBCodeParser::load()->parse(Config::$modSettings['policy_' . User::$me->language]);
			} elseif (!empty(Config::$modSettings['policy_' . Lang::$default])) {
				Utils::$context['privacy_policy'] = BBCodeParser::load()->parse(Config::$modSettings['policy_' . Lang::$default]);
			} else {
				// None was found; log the error so the admin knows there is a problem!
				ErrorHandler::log(Lang::$txt['registration_policy_missing'], 'critical');
				ErrorHandler::fatalLang('registration_disabled', false);
			}
		}

		// Any custom fields we want filled in?
		Profile::load(0);
		Profile::$member->loadCustomFields('register');

		// Or any standard ones?
		if (!empty(Config::$modSettings['registration_fields'])) {
			require_once Config::$sourcedir . '/Profile-Modify.php';

			// Setup some important context.
			Lang::load('Profile');
			Theme::loadTemplate('Profile');

			User::$me->is_owner = true;

			// Here, and here only, emulate the permissions the user would have to do this.
			User::$me->permissions = array_merge(User::$me->permissions, ['profile_account_own', 'profile_extra_own', 'profile_other_own', 'profile_password_own', 'profile_website_own', 'profile_blurb']);
			$reg_fields = explode(',', Config::$modSettings['registration_fields']);

			// Website is a little different
			if (in_array('website', $reg_fields)) {
				unset($reg_fields['website']);

				if (isset($_POST['website_title'])) {
					User::$profiles[User::$me->id]['website_title'] = Utils::htmlspecialchars($_POST['website_title']);
				}

				if (isset($_POST['website_url'])) {
					User::$profiles[User::$me->id]['website_url'] = Utils::htmlspecialchars($_POST['website_url']);
				}
			}

			// We might have had some submissions on this front - go check.
			foreach ($reg_fields as $field) {
				if (isset($_POST[$field])) {
					User::$profiles[User::$me->id][$field] = Utils::htmlspecialchars($_POST[$field]);
				}
			}

			// Load all the fields in question.
			Profile::$member->setupContext($reg_fields);
		}

		// Generate a visual verification code to make sure the user is no bot.
		if (!empty(Config::$modSettings['reg_verification'])) {
			$verifier = new Verifier(['id' => 'register']);
		}
		// Otherwise we have nothing to show.
		else {
			Utils::$context['visual_verification'] = false;
		}

		Utils::$context += [
			'username' => isset($_POST['user']) ? Utils::htmlspecialchars($_POST['user']) : '',
			'email' => isset($_POST['email']) ? Utils::htmlspecialchars($_POST['email']) : '',
			'notify_announcements' => !empty($_POST['notify_announcements']) ? 1 : 0,
		];

		// Were there any errors?
		Utils::$context['registration_errors'] = [];

		if (!empty($this->errors)) {
			Utils::$context['registration_errors'] = $this->errors;
		}

		SecurityToken::create('register');
	}

	/**
	 * See if a username already exists.
	 */
	public function checkUsername()
	{
		// This is XML!
		Theme::loadTemplate('Xml');
		Utils::$context['sub_template'] = 'check_username';
		Utils::$context['checked_username'] = isset($_GET['username']) ? Utils::htmlspecialcharsDecode($_GET['username']) : '';
		Utils::$context['valid_username'] = true;

		// Clean it up like mother would.
		Utils::$context['checked_username'] = trim(Utils::normalizeSpaces(Utils::sanitizeChars(Utils::$context['checked_username'], 1, ' '), true, true, ['no_breaks' => true, 'replace_tabs' => true, 'collapse_hspace' => true]));

		$errors = User::validateUsername(0, Utils::$context['checked_username'], true);

		Utils::$context['valid_username'] = empty($errors);
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
	 * Backward compatibility wrapper for show sub-action.
	 *
	 * @param array $reg_errors Holds information about any errors that occurred.
	 */
	public static function register($reg_errors = []): void
	{
		self::load();
		self::$obj->subaction = 'show';
		self::$obj->errors = (array) $reg_errors;
		self::$obj->execute();
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
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Register::exportStatic')) {
	Register::exportStatic();
}

?>