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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\Register2;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Profile;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class helps the administrator setting registration settings and policy
 * as well as allow the administrator to register new members themselves.
 */
class Registration implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'RegCenter',
			'adminRegister' => 'AdminRegister',
			'editAgreement' => 'EditAgreement',
			'editPrivacyPolicy' => 'EditPrivacyPolicy',
			'setReserved' => 'SetReserved',
			'modifyRegistrationSettings' => 'ModifyRegistrationSettings',
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
	public string $subaction = 'register';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sa' => array('method', 'required_permission')
	 */
	public static array $subactions = [
		'register' => ['register', 'moderate_forum'],
		'agreement' => ['agreement', 'admin_forum'],
		'policy' => ['privacyPolicy', 'admin_forum'],
		'reservednames' => ['reservedNames', 'admin_forum'],
		'settings' => ['settings', 'admin_forum'],
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
		// Must have sufficient permissions.
		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		$call = method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * This method allows the admin to register a new member by hand.
	 *
	 * It also allows assigning a primary group to the member being registered.
	 * Accessed by ?action=admin;area=regcenter;sa=register
	 * Requires the moderate_forum permission.
	 */
	public function register(): void
	{
		// Are there any custom profile fields required during registration?
		Profile::load(0);
		Profile::$member->loadCustomFields('register');

		if (!empty($_POST['regSubmit'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-regc');

			foreach ($_POST as $key => $value) {
				if (!is_array($_POST[$key])) {
					$_POST[$key] = Utils::htmlTrimRecursive(str_replace(["\n", "\r"], '', Utils::normalize($_POST[$key])));
				}
			}

			$regOptions = [
				'interface' => 'admin',
				'username' => $_POST['user'],
				'email' => $_POST['email'],
				'password' => $_POST['password'],
				'password_check' => $_POST['password'],
				'check_reserved_name' => true,
				'check_password_strength' => false,
				'check_email_ban' => false,
				'send_welcome_email' => isset($_POST['emailPassword']) || empty($_POST['password']),
				'require' => isset($_POST['emailActivate']) ? 'activation' : 'nothing',
				'memberGroup' => empty($_POST['group']) || !User::$me->allowedTo('manage_membergroups') ? 0 : (int) $_POST['group'],
			];

			$memberID = Register2::registerMember($regOptions);

			if (!empty($memberID)) {
				// We'll do custom fields after as then we get to use the helper function!
				if (!empty($_POST['customfield'])) {
					Profile::load($memberID);
					Profile::$member->loadCustomFields('register');
					Profile::$member->save();
				}

				Utils::$context['new_member'] = [
					'id' => $memberID,
					'name' => $_POST['user'],
					'href' => Config::$scripturl . '?action=profile;u=' . $memberID,
					'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $memberID . '">' . $_POST['user'] . '</a>',
				];

				Utils::$context['registration_done'] = sprintf(Lang::$txt['admin_register_done'], Utils::$context['new_member']['link']);
			}
		}

		Utils::$context['member_groups'] = [];

		// Load the assignable member groups.
		if (User::$me->allowedTo('manage_membergroups')) {
			Utils::$context['member_groups'][] = Lang::$txt['admin_register_group_none'];

			$request = Db::$db->query(
				'',
				'SELECT group_name, id_group
				FROM {db_prefix}membergroups
				WHERE id_group != {int:moderator_group}
					AND min_posts = {int:min_posts}' . (User::$me->allowedTo('admin_forum') ? '' : '
					AND id_group != {int:admin_group}
					AND group_type != {int:is_protected}') . '
					AND hidden != {int:hidden_group}
				ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
				[
					'moderator_group' => 3,
					'min_posts' => -1,
					'admin_group' => 1,
					'is_protected' => 1,
					'hidden_group' => 2,
					'newbie_group' => 4,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['member_groups'][$row['id_group']] = $row['group_name'];
			}
			Db::$db->free_result($request);
		}

		// Basic stuff.
		Utils::$context['sub_template'] = 'admin_register';
		Utils::$context['page_title'] = Lang::$txt['registration_center'];

		SecurityToken::create('admin-regc');

		Theme::loadJavaScriptFile('register.js', ['defer' => false, 'minimize' => true], 'smf_register');
	}

	/**
	 * Allows the administrator to edit the registration agreement and to choose
	 * whether it should be shown or not.
	 *
	 * It saves the agreement to the agreement.txt file.
	 * Accessed by ?action=admin;area=regcenter;sa=agreement.
	 * Requires the admin_forum permission.
	 */
	public function agreement(): void
	{
		// I hereby agree not to be a lazy bum.
		// By default we look at agreement.txt.
		Utils::$context['current_agreement'] = '';

		// Is there more than one to edit?
		Utils::$context['editable_agreements'] = [
			'' => Lang::$txt['admin_agreement_default'],
		];

		// Get our languages.
		Lang::get();

		// Try to figure out if we have more agreements.
		foreach (Utils::$context['languages'] as $lang) {
			if (file_exists(Config::$boarddir . '/agreement.' . $lang['filename'] . '.txt')) {
				Utils::$context['editable_agreements']['.' . $lang['filename']] = $lang['name'];

				// Are we editing this?
				if (isset($_POST['agree_lang']) && $_POST['agree_lang'] == '.' . $lang['filename']) {
					Utils::$context['current_agreement'] = '.' . $lang['filename'];
				}
			}
		}

		$agreement_lang = empty(Utils::$context['current_agreement']) ? 'default' : substr(Utils::$context['current_agreement'], 1);

		$agreement_file = Config::$boarddir . '/agreement' . Utils::$context['current_agreement'] . '.txt';

		Utils::$context['agreement'] = file_exists($agreement_file) ? str_replace("\r", '', file_get_contents($agreement_file)) : '';

		if (isset($_POST['agreement'])) {
			$_POST['agreement'] = Utils::normalizeSpaces(Utils::normalize($_POST['agreement']));
		}

		if (isset($_POST['agreement']) && $_POST['agreement'] != Utils::$context['agreement']) {
			User::$me->checkSession();
			SecurityToken::validate('admin-rega');

			$backup_file = (date_create('@' . filemtime($agreement_file))->format('Y-m-d\\TH_i_sp')) . '_' . $agreement_file;

			// Off it goes to the agreement file.
			if (Config::safeFileWrite($agreement_file, $_POST['agreement'], $backup_file)) {
				Utils::$context['saved_successful'] = true;

				$agreement_settings['agreement_updated_' . $agreement_lang] = time();

				// Writing it counts as agreeing to it, right?
				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
					[User::$me->id, 1, 'agreement_accepted', time()],
					['id_member', 'id_theme', 'variable'],
				);

				Logging::logAction('agreement_updated', ['language' => Utils::$context['editable_agreements'][Utils::$context['current_agreement']]], 'admin');

				Logging::logAction('agreement_accepted', ['applicator' => User::$me->id], 'user');

				Config::updateModSettings($agreement_settings);

				Utils::$context['agreement'] = $_POST['agreement'];
			} else {
				Utils::$context['could_not_save'] = true;
			}
		}

		Utils::$context['agreement_info'] = sprintf(Lang::$txt['admin_agreement_info'], empty(Config::$modSettings['agreement_updated_' . $agreement_lang]) ? Lang::$txt['never'] : Time::create('@' . Config::$modSettings['agreement_updated_' . $agreement_lang])->format());

		Utils::$context['agreement'] = Utils::htmlspecialchars(Utils::$context['agreement']);

		Utils::$context['warning'] = is_writable($agreement_file) ? '' : Lang::$txt['agreement_not_writable'];

		Utils::$context['sub_template'] = 'edit_agreement';
		Utils::$context['page_title'] = Lang::$txt['registration_agreement'];

		SecurityToken::create('admin-rega');
	}

	/**
	 * Allows the administrator to edit the privacy policy and to choose
	 * whether it should be shown or not.
	 *
	 * It saves the privacy policy to the database.
	 * Accessed by ?action=admin;area=regcenter;sa=policy.
	 * Requires the admin_forum permission.
	 */
	public function privacyPolicy(): void
	{
		// By default, edit the current language's policy.
		Utils::$context['current_policy_lang'] = User::$me->language;

		// We need a policy for every language.
		Lang::get();

		foreach (Utils::$context['languages'] as $lang) {
			Utils::$context['editable_policies'][$lang['filename']] = $lang['name'];

			// Are we editing this one?
			if (isset($_POST['policy_lang']) && $_POST['policy_lang'] == $lang['filename']) {
				Utils::$context['current_policy_lang'] = $lang['filename'];
			}
		}

		Utils::$context['privacy_policy'] = empty(Config::$modSettings['policy_' . Utils::$context['current_policy_lang']]) ? '' : Config::$modSettings['policy_' . Utils::$context['current_policy_lang']];

		if (isset($_POST['policy'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-regp');

			// Make sure there are no creepy-crawlies in it.
			$policy_text = Utils::normalizeSpaces(Utils::htmlspecialchars($_POST['policy']));

			$policy_settings = [
				'policy_' . Utils::$context['current_policy_lang'] => $policy_text,
				'policy_' . Utils::$context['current_policy_lang'] . '_' . Config::$modSettings['policy_updated_' . Utils::$context['current_policy_lang']] => Utils::$context['privacy_policy'],
			];

			$policy_settings['policy_updated_' . Utils::$context['current_policy_lang']] = time();

			// Writing it counts as agreeing to it, right?
			Db::$db->insert(
				'replace',
				'{db_prefix}themes',
				['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
				[User::$me->id, 1, 'policy_accepted', time()],
				['id_member', 'id_theme', 'variable'],
			);

			Logging::logAction('policy_updated', ['language' => Utils::$context['editable_policies'][Utils::$context['current_policy_lang']]], 'admin');

			Logging::logAction('policy_accepted', ['applicator' => User::$me->id], 'user');

			if (Utils::$context['privacy_policy'] !== $policy_text) {
				Utils::$context['saved_successful'] = true;
			}

			Config::updateModSettings($policy_settings);

			Utils::$context['privacy_policy'] = $policy_text;
		}

		Utils::$context['privacy_policy_info'] = sprintf(Lang::$txt['admin_agreement_info'], empty(Config::$modSettings['policy_updated_' . Utils::$context['current_policy_lang']]) ? Lang::$txt['never'] : Time::create('@' . Config::$modSettings['policy_updated_' . Utils::$context['current_policy_lang']])->format());

		Utils::$context['sub_template'] = 'edit_privacy_policy';
		Utils::$context['page_title'] = Lang::$txt['privacy_policy'];

		SecurityToken::create('admin-regp');
	}

	/**
	 * Set the names under which users are not allowed to register.
	 *
	 * Accessed by ?action=admin;area=regcenter;sa=reservednames.
	 * Requires the admin_forum permission.
	 */
	public function reservedNames(): void
	{
		// Submitting new reserved words.
		if (!empty($_POST['save_reserved_names'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-regr');

			$_POST['reserved'] = Utils::normalize($_POST['reserved']);

			// Set all the options....
			Config::updateModSettings([
				'reserveWord' => (int) !empty($_POST['matchword']),
				'reserveCase' => (int) !empty($_POST['matchcase']),
				'reserveUser' => (int) !empty($_POST['matchuser']),
				'reserveName' => (int) !empty($_POST['matchname']),
				'reserveNames' => Utils::normalizeSpaces(Utils::normalize($_POST['reserved'])),
			]);

			Utils::$context['saved_successful'] = true;
		}

		// Get the reserved word options and words.
		Config::$modSettings['reserveNames'] = str_replace('\\n', "\n", Config::$modSettings['reserveNames']);

		Utils::$context['reserved_words'] = explode("\n", Config::$modSettings['reserveNames']);

		Utils::$context['reserved_word_options'] = [
			'match_word' => !empty(Config::$modSettings['reserveWord']),
			'match_case' => !empty(Config::$modSettings['reserveCase']),
			'match_user' => !empty(Config::$modSettings['reserveUser']),
			'match_name' => !empty(Config::$modSettings['reserveName']),
		];

		// Ready the template......
		Utils::$context['sub_template'] = 'edit_reserved_words';
		Utils::$context['page_title'] = Lang::$txt['admin_reserved_set'];

		SecurityToken::create('admin-regr');
	}

	/**
	 * This function handles registration settings and provides a few pretty
	 * stats too while it's at it.
	 *
	 * General registration settings and Coppa compliance settings.
	 * Accessed by ?action=admin;area=regcenter;sa=settings.
	 * Requires the admin_forum permission.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Setup the template
		Utils::$context['sub_template'] = 'show_settings';
		Utils::$context['page_title'] = Lang::$txt['registration_center'];

		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// Are there some contacts missing?
			if (!empty($_POST['coppaAge']) && !empty($_POST['coppaType']) && empty($_POST['coppaPost']) && empty($_POST['coppaFax'])) {
				ErrorHandler::fatalLang('admin_setting_coppa_require_contact');
			}

			// Post needs to take into account line breaks.
			$_POST['coppaPost'] = str_replace("\n", '<br>', empty($_POST['coppaPost']) ? '' : Utils::normalize($_POST['coppaPost']));

			IntegrationHook::call('integrate_save_registration_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=regcenter;sa=settings');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=regcenter;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['settings'];

		// Define some javascript for COPPA.
		Utils::$context['settings_post_javascript'] = '
			function checkCoppa()
			{
				var coppaDisabled = document.getElementById(\'coppaAge\').value == 0;
				document.getElementById(\'coppaType\').disabled = coppaDisabled;

				var disableContacts = coppaDisabled || document.getElementById(\'coppaType\').options[document.getElementById(\'coppaType\').selectedIndex].value != 1;
				document.getElementById(\'coppaPost\').disabled = disableContacts;
				document.getElementById(\'coppaFax\').disabled = disableContacts;
				document.getElementById(\'coppaPhone\').disabled = disableContacts;
			}
			checkCoppa();';

		// Turn the postal address into something suitable for a textbox.
		Config::$modSettings['coppaPost'] = !empty(Config::$modSettings['coppaPost']) ? preg_replace('~<br ?/?' . '>~', "\n", Config::$modSettings['coppaPost']) : '';

		ACP::prepareDBSettingContext($config_vars);
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
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the registration area.
	 */
	public static function getConfigVars(): array
	{
		// Do we have at least default versions of the agreement and privacy policy?
		$agreement = file_exists(Config::$boarddir . '/agreement.' . Lang::$default . '.txt') || file_exists(Config::$boarddir . '/agreement.txt');

		$policy = !empty(Config::$modSettings['policy_' . Lang::$default]);

		$config_vars = [
			['select', 'registration_method', [Lang::$txt['setting_registration_standard'], Lang::$txt['setting_registration_activate'], Lang::$txt['setting_registration_approval'], Lang::$txt['setting_registration_disabled']]],
			['check', 'send_welcomeEmail'],

			'',

			['check', 'requireAgreement', 'text_label' => Lang::$txt['admin_agreement'], 'value' => !empty(Config::$modSettings['requireAgreement'])],
			['warning', empty($agreement) ? 'error_no_agreement' : ''],
			['check', 'requirePolicyAgreement', 'text_label' => Lang::$txt['admin_privacy_policy'], 'value' => !empty(Config::$modSettings['requirePolicyAgreement'])],
			['warning', empty($policy) ? 'error_no_privacy_policy' : ''],

			'',

			['int', 'coppaAge', 'subtext' => Lang::$txt['zero_to_disable'], 'onchange' => 'checkCoppa();'],
			['select', 'coppaType', [Lang::$txt['setting_coppaType_reject'], Lang::$txt['setting_coppaType_approval']], 'onchange' => 'checkCoppa();'],
			['large_text', 'coppaPost', 'subtext' => Lang::$txt['setting_coppaPost_desc']],
			['text', 'coppaFax'],
			['text', 'coppaPhone'],
		];

		IntegrationHook::call('integrate_modify_registration_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper for the register sub-action.
	 */
	public static function adminRegister(): void
	{
		self::load();
		self::$obj->subaction = 'register';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the agreement sub-action.
	 */
	public static function editAgreement(): void
	{
		self::load();
		self::$obj->subaction = 'agreement';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the policy sub-action.
	 */
	public static function editPrivacyPolicy(): void
	{
		self::load();
		self::$obj->subaction = 'policy';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the reservednames sub-action.
	 */
	public static function setReserved(): void
	{
		self::load();
		self::$obj->subaction = 'reservednames';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyRegistrationSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
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
		// Loading, always loading.
		Lang::load('Login');
		Theme::loadTemplate('Register');

		// Next create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['registration_center'],
			'help' => 'registrations',
			'description' => Lang::$txt['admin_settings_desc'],
			'tabs' => [
				'register' => [
					'description' => Lang::$txt['admin_register_desc'],
				],
				'agreement' => [
					'description' => Lang::$txt['registration_agreement_desc'],
				],
				'policy' => [
					'description' => Lang::$txt['privacy_policy_desc'],
				],
				'reservednames' => [
					'description' => Lang::$txt['admin_reserved_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['admin_settings_desc'],
				],
			],
		];

		IntegrationHook::call('integrate_manage_registrations', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		} elseif (!User::$me->allowedTo('moderate_forum')) {
			$this->subaction = 'settings';
		}

		// @todo Is this context variable necessary?
		Utils::$context['sub_action'] = $this->subaction;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Registration::exportStatic')) {
	Registration::exportStatic();
}

?>