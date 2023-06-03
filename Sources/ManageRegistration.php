<?php

/**
 * This file helps the administrator setting registration settings and policy
 * as well as allow the administrator to register new members themselves.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Menu;
use SMF\User;
use SMF\Theme;
use SMF\Utils;
use SMF\Actions\Register2;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Entrance point for the registration center, it checks permissions and forwards
 * to the right function based on the subaction.
 * Accessed by ?action=admin;area=regcenter.
 * Requires either the moderate_forum or the admin_forum permission.
 *
 * Uses Login language file
 * Uses Register template.
 */
function RegCenter()
{
	// Old templates might still request this.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'browse')
		redirectexit('action=admin;area=viewmembers;sa=browse' . (isset($_REQUEST['type']) ? ';type=' . $_REQUEST['type'] : ''));

	$subActions = array(
		'register' => array('AdminRegister', 'moderate_forum'),
		'agreement' => array('EditAgreement', 'admin_forum'),
		'policy' => array('EditPrivacyPolicy', 'admin_forum'),
		'reservednames' => array('SetReserved', 'admin_forum'),
		'settings' => array('ModifyRegistrationSettings', 'admin_forum'),
	);

	// Work out which to call...
	Utils::$context['sub_action'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('moderate_forum') ? 'register' : 'settings');

	// Must have sufficient permissions.
	isAllowedTo($subActions[Utils::$context['sub_action']][1]);

	// Loading, always loading.
	Lang::load('Login');
	Theme::loadTemplate('Register');

	// Next create the tabs for the template.
	Menu::$loaded['admin']->tab_data = array(
		'title' => Lang::$txt['registration_center'],
		'help' => 'registrations',
		'description' => Lang::$txt['admin_settings_desc'],
		'tabs' => array(
			'register' => array(
				'description' => Lang::$txt['admin_register_desc'],
			),
			'agreement' => array(
				'description' => Lang::$txt['registration_agreement_desc'],
			),
			'policy' => array(
				'description' => Lang::$txt['privacy_policy_desc'],
			),
			'reservednames' => array(
				'description' => Lang::$txt['admin_reserved_desc'],
			),
			'settings' => array(
				'description' => Lang::$txt['admin_settings_desc'],
			)
		)
	);

	call_integration_hook('integrate_manage_registrations', array(&$subActions));

	// Finally, get around to calling the function...
	call_helper($subActions[Utils::$context['sub_action']][0]);
}

/**
 * This function allows the admin to register a new member by hand.
 * It also allows assigning a primary group to the member being registered.
 * Accessed by ?action=admin;area=regcenter;sa=register
 * Requires the moderate_forum permission.
 *
 * @uses template_admin_register()
 */
function AdminRegister()
{
	// Are there any custom profile fields required during registration?
	require_once(Config::$sourcedir . '/Profile.php');
	loadCustomFields(0, 'register');

	if (!empty($_POST['regSubmit']))
	{
		checkSession();
		validateToken('admin-regc');

		foreach ($_POST as $key => $value)
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', Utils::normalize($_POST[$key])));

		$regOptions = array(
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
			'memberGroup' => empty($_POST['group']) || !allowedTo('manage_membergroups') ? 0 : (int) $_POST['group'],
		);

		$memberID = Register2::registerMember($regOptions);
		if (!empty($memberID))
		{
			// We'll do custom fields after as then we get to use the helper function!
			if (!empty($_POST['customfield']))
			{
				require_once(Config::$sourcedir . '/Profile-Modify.php');
				makeCustomFieldChanges($memberID, 'register');
			}

			Utils::$context['new_member'] = array(
				'id' => $memberID,
				'name' => $_POST['user'],
				'href' => Config::$scripturl . '?action=profile;u=' . $memberID,
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $memberID . '">' . $_POST['user'] . '</a>',
			);
			Utils::$context['registration_done'] = sprintf(Lang::$txt['admin_register_done'], Utils::$context['new_member']['link']);
		}
	}

	// Load the assignable member groups.
	if (allowedTo('manage_membergroups'))
	{
		$request = Db::$db->query('', '
			SELECT group_name, id_group
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}
				AND min_posts = {int:min_posts}' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND group_type != {int:is_protected}') . '
				AND hidden != {int:hidden_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'min_posts' => -1,
				'admin_group' => 1,
				'is_protected' => 1,
				'hidden_group' => 2,
				'newbie_group' => 4,
			)
		);
		Utils::$context['member_groups'] = array(0 => Lang::$txt['admin_register_group_none']);
		while ($row = Db::$db->fetch_assoc($request))
			Utils::$context['member_groups'][$row['id_group']] = $row['group_name'];
		Db::$db->free_result($request);
	}
	else
		Utils::$context['member_groups'] = array();

	// Basic stuff.
	Utils::$context['sub_template'] = 'admin_register';
	Utils::$context['page_title'] = Lang::$txt['registration_center'];
	createToken('admin-regc');
	Theme::loadJavaScriptFile('register.js', array('defer' => false, 'minimize' => true), 'smf_register');
}

/**
 * Allows the administrator to edit the registration agreement, and choose whether
 * it should be shown or not. It writes and saves the agreement to the agreement.txt
 * file.
 * Accessed by ?action=admin;area=regcenter;sa=agreement.
 * Requires the admin_forum permission.
 *
 * @uses template_edit_agreement()
 */
function EditAgreement()
{
	// I hereby agree not to be a lazy bum.
	// By default we look at agreement.txt.
	Utils::$context['current_agreement'] = '';

	// Is there more than one to edit?
	Utils::$context['editable_agreements'] = array(
		'' => Lang::$txt['admin_agreement_default'],
	);

	// Get our languages.
	Lang::get();

	// Try to figure out if we have more agreements.
	foreach (Utils::$context['languages'] as $lang)
	{
		if (file_exists(Config::$boarddir . '/agreement.' . $lang['filename'] . '.txt'))
		{
			Utils::$context['editable_agreements']['.' . $lang['filename']] = $lang['name'];
			// Are we editing this?
			if (isset($_POST['agree_lang']) && $_POST['agree_lang'] == '.' . $lang['filename'])
				Utils::$context['current_agreement'] = '.' . $lang['filename'];
		}
	}

	$agreement_lang = empty(Utils::$context['current_agreement']) ? 'default' : substr(Utils::$context['current_agreement'], 1);

	Utils::$context['agreement'] = file_exists(Config::$boarddir . '/agreement' . Utils::$context['current_agreement'] . '.txt') ? str_replace("\r", '', file_get_contents(Config::$boarddir . '/agreement' . Utils::$context['current_agreement'] . '.txt')) : '';

	if (isset($_POST['agreement']) && str_replace("\r", '', $_POST['agreement']) != Utils::$context['agreement'])
	{
		checkSession();
		validateToken('admin-rega');

		$_POST['agreement'] = Utils::normalize($_POST['agreement']);

		// Off it goes to the agreement file.
		$to_write = str_replace("\r", '', $_POST['agreement']);
		$bytes = file_put_contents(Config::$boarddir . '/agreement' . Utils::$context['current_agreement'] . '.txt', $to_write, LOCK_EX);

		$agreement_settings['agreement_updated_' . $agreement_lang] = time();

		if ($bytes == strlen($to_write))
			Utils::$context['saved_successful'] = true;
		else
			Utils::$context['could_not_save'] = true;

		// Writing it counts as agreeing to it, right?
		Db::$db->insert('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			array(User::$me->id, 1, 'agreement_accepted', time()),
			array('id_member', 'id_theme', 'variable')
		);
		logAction('agreement_updated', array('language' => Utils::$context['editable_agreements'][Utils::$context['current_agreement']]), 'admin');
		logAction('agreement_accepted', array('applicator' => User::$me->id), 'user');

		Config::updateModSettings($agreement_settings);

		Utils::$context['agreement'] = str_replace("\r", '', $_POST['agreement']);
	}

	Utils::$context['agreement_info'] = sprintf(Lang::$txt['admin_agreement_info'], empty(Config::$modSettings['agreement_updated_' . $agreement_lang]) ? Lang::$txt['never'] : timeformat(Config::$modSettings['agreement_updated_' . $agreement_lang]));

	Utils::$context['agreement'] = Utils::htmlspecialchars(Utils::$context['agreement']);
	Utils::$context['warning'] = is_writable(Config::$boarddir . '/agreement' . Utils::$context['current_agreement'] . '.txt') ? '' : Lang::$txt['agreement_not_writable'];

	Utils::$context['sub_template'] = 'edit_agreement';
	Utils::$context['page_title'] = Lang::$txt['registration_agreement'];

	createToken('admin-rega');
}

/**
 * Set the names under which users are not allowed to register.
 * Accessed by ?action=admin;area=regcenter;sa=reservednames.
 * Requires the admin_forum permission.
 *
 * @uses template_edit_reserved_words()
 */
function SetReserved()
{
	// Submitting new reserved words.
	if (!empty($_POST['save_reserved_names']))
	{
		checkSession();
		validateToken('admin-regr');

		$_POST['reserved'] = Utils::normalize($_POST['reserved']);

		// Set all the options....
		Config::updateModSettings(array(
			'reserveWord' => (isset($_POST['matchword']) ? '1' : '0'),
			'reserveCase' => (isset($_POST['matchcase']) ? '1' : '0'),
			'reserveUser' => (isset($_POST['matchuser']) ? '1' : '0'),
			'reserveName' => (isset($_POST['matchname']) ? '1' : '0'),
			'reserveNames' => str_replace("\r", '', $_POST['reserved'])
		));
		Utils::$context['saved_successful'] = true;
	}

	// Get the reserved word options and words.
	Config::$modSettings['reserveNames'] = str_replace('\n', "\n", Config::$modSettings['reserveNames']);
	Utils::$context['reserved_words'] = explode("\n", Config::$modSettings['reserveNames']);
	Utils::$context['reserved_word_options'] = array();
	Utils::$context['reserved_word_options']['match_word'] = Config::$modSettings['reserveWord'] == '1';
	Utils::$context['reserved_word_options']['match_case'] = Config::$modSettings['reserveCase'] == '1';
	Utils::$context['reserved_word_options']['match_user'] = Config::$modSettings['reserveUser'] == '1';
	Utils::$context['reserved_word_options']['match_name'] = Config::$modSettings['reserveName'] == '1';

	// Ready the template......
	Utils::$context['sub_template'] = 'edit_reserved_words';
	Utils::$context['page_title'] = Lang::$txt['admin_reserved_set'];
	createToken('admin-regr');
}

/**
 * This function handles registration settings, and provides a few pretty stats too while it's at it.
 * General registration settings and Coppa compliance settings.
 * Accessed by ?action=admin;area=regcenter;sa=settings.
 * Requires the admin_forum permission.
 *
 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyRegistrationSettings($return_config = false)
{
	// This is really quite wanting.
	require_once(Config::$sourcedir . '/ManageServer.php');

	// Do we have at least default versions of the agreement and privacy policy?
	$agreement = file_exists(Config::$boarddir . '/agreement.' . Lang::$default . '.txt') || file_exists(Config::$boarddir . '/agreement.txt');
	$policy = !empty(Config::$modSettings['policy_' . Lang::$default]);

	$config_vars = array(
		array('select', 'registration_method', array(Lang::$txt['setting_registration_standard'], Lang::$txt['setting_registration_activate'], Lang::$txt['setting_registration_approval'], Lang::$txt['setting_registration_disabled'])),
		array('check', 'send_welcomeEmail'),
	'',
		array('check', 'requireAgreement', 'text_label' => Lang::$txt['admin_agreement'], 'value' => !empty(Config::$modSettings['requireAgreement'])),
		array('warning', empty($agreement) ? 'error_no_agreement' : ''),
		array('check', 'requirePolicyAgreement', 'text_label' => Lang::$txt['admin_privacy_policy'], 'value' => !empty(Config::$modSettings['requirePolicyAgreement'])),
		array('warning', empty($policy) ? 'error_no_privacy_policy' : ''),
	'',
		array('int', 'coppaAge', 'subtext' => Lang::$txt['zero_to_disable'], 'onchange' => 'checkCoppa();'),
		array('select', 'coppaType', array(Lang::$txt['setting_coppaType_reject'], Lang::$txt['setting_coppaType_approval']), 'onchange' => 'checkCoppa();'),
		array('large_text', 'coppaPost', 'subtext' => Lang::$txt['setting_coppaPost_desc']),
		array('text', 'coppaFax'),
		array('text', 'coppaPhone'),
	);

	call_integration_hook('integrate_modify_registration_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template
	Utils::$context['sub_template'] = 'show_settings';
	Utils::$context['page_title'] = Lang::$txt['registration_center'];

	if (isset($_GET['save']))
	{
		checkSession();

		// Are there some contacts missing?
		if (!empty($_POST['coppaAge']) && !empty($_POST['coppaType']) && empty($_POST['coppaPost']) && empty($_POST['coppaFax']))
			fatal_lang_error('admin_setting_coppa_require_contact');

		// Post needs to take into account line breaks.
		$_POST['coppaPost'] = str_replace("\n", '<br>', empty($_POST['coppaPost']) ? '' : Utils::normalize($_POST['coppaPost']));

		call_integration_hook('integrate_save_registration_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=regcenter;sa=settings');
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

	prepareDBSettingContext($config_vars);
}

// Sure, you can sell my personal info for profit (...or not)
function EditPrivacyPolicy()
{
	// By default, edit the current language's policy
	Utils::$context['current_policy_lang'] = User::$me->language;

	// We need a policy for every language
	Lang::get();

	foreach (Utils::$context['languages'] as $lang)
	{
		Utils::$context['editable_policies'][$lang['filename']] = $lang['name'];

		// Are we editing this one?
		if (isset($_POST['policy_lang']) && $_POST['policy_lang'] == $lang['filename'])
			Utils::$context['current_policy_lang'] = $lang['filename'];
	}

	Utils::$context['privacy_policy'] = empty(Config::$modSettings['policy_' . Utils::$context['current_policy_lang']]) ? '' : Config::$modSettings['policy_' . Utils::$context['current_policy_lang']];

	if (isset($_POST['policy']))
	{
		checkSession();
		validateToken('admin-regp');

		// Make sure there are no creepy-crawlies in it
		$policy_text = Utils::htmlspecialchars(str_replace("\r", '', $_POST['policy']));

		$policy_settings = array(
			'policy_' . Utils::$context['current_policy_lang'] => $policy_text,
		);

		$policy_settings['policy_updated_' . Utils::$context['current_policy_lang']] = time();

		// Writing it counts as agreeing to it, right?
		Db::$db->insert('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			array(User::$me->id, 1, 'policy_accepted', time()),
			array('id_member', 'id_theme', 'variable')
		);
		logAction('policy_updated', array('language' => Utils::$context['editable_policies'][Utils::$context['current_policy_lang']]), 'admin');
		logAction('policy_accepted', array('applicator' => User::$me->id), 'user');

		if (Utils::$context['privacy_policy'] !== $policy_text)
			Utils::$context['saved_successful'] = true;

		Config::updateModSettings($policy_settings);

		Utils::$context['privacy_policy'] = $policy_text;
	}

	Utils::$context['privacy_policy_info'] = sprintf(Lang::$txt['admin_agreement_info'], empty(Config::$modSettings['policy_updated_' . Utils::$context['current_policy_lang']]) ? Lang::$txt['never'] : timeformat(Config::$modSettings['policy_updated_' . Utils::$context['current_policy_lang']]));

	Utils::$context['sub_template'] = 'edit_privacy_policy';
	Utils::$context['page_title'] = Lang::$txt['privacy_policy'];

	createToken('admin-regp');
}

?>