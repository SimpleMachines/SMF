<?php

/**
 * This file helps the administrator setting registration settings and policy
 * as well as allow the administrator to register new members themselves.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Entrance point for the registration center, it checks permissions and forwards
 * to the right function based on the subaction.
 * Accessed by ?action=admin;area=regcenter.
 * Requires either the moderate_forum or the admin_forum permission.
 *
 * @uses Login language file
 * @uses Register template.
 */
function RegCenter()
{
	global $context, $txt;

	// Old templates might still request this.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'browse')
		redirectexit('action=admin;area=viewmembers;sa=browse' . (isset($_REQUEST['type']) ? ';type=' . $_REQUEST['type'] : ''));

	$subActions = array(
		'register' => array('AdminRegister', 'moderate_forum'),
		'agreement' => array('EditAgreement', 'admin_forum'),
		'reservednames' => array('SetReserved', 'admin_forum'),
		'settings' => array('ModifyRegistrationSettings', 'admin_forum'),
	);

	// Work out which to call...
	$context['sub_action'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('moderate_forum') ? 'register' : 'settings');

	// Must have sufficient permissions.
	isAllowedTo($subActions[$context['sub_action']][1]);

	// Loading, always loading.
	loadLanguage('Login');
	loadTemplate('Register');

	// Next create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['registration_center'],
		'help' => 'registrations',
		'description' => $txt['admin_settings_desc'],
		'tabs' => array(
			'register' => array(
				'description' => $txt['admin_register_desc'],
			),
			'agreement' => array(
				'description' => $txt['registration_agreement_desc'],
			),
			'reservednames' => array(
				'description' => $txt['admin_reserved_desc'],
			),
			'settings' => array(
				'description' => $txt['admin_settings_desc'],
			)
		)
	);

	call_integration_hook('integrate_manage_registrations', array(&$subActions));

	// Finally, get around to calling the function...
	call_helper($subActions[$context['sub_action']][0]);
}

/**
 * This function allows the admin to register a new member by hand.
 * It also allows assigning a primary group to the member being registered.
 * Accessed by ?action=admin;area=regcenter;sa=register
 * Requires the moderate_forum permission.
 *
 * @uses Register template, admin_register sub-template.
 */
function AdminRegister()
{
	global $txt, $context, $sourcedir, $scripturl, $smcFunc;

	// Are there any custom profile fields required during registration?
	require_once($sourcedir . '/Profile.php');
	loadCustomFields(0, 'register');

	if (!empty($_POST['regSubmit']))
	{
		checkSession();
		validateToken('admin-regc');

		foreach ($_POST as $key => $value)
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));

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

		require_once($sourcedir . '/Subs-Members.php');
		$memberID = registerMember($regOptions);
		if (!empty($memberID))
		{
			// We'll do custom fields after as then we get to use the helper function!
			if (!empty($_POST['customfield']))
			{
				require_once($sourcedir . '/Profile-Modify.php');
				makeCustomFieldChanges($memberID, 'register');
			}

			$context['new_member'] = array(
				'id' => $memberID,
				'name' => $_POST['user'],
				'href' => $scripturl . '?action=profile;u=' . $memberID,
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $memberID . '">' . $_POST['user'] . '</a>',
			);
			$context['registration_done'] = sprintf($txt['admin_register_done'], $context['new_member']['link']);
		}
	}

	// Load the assignable member groups.
	if (allowedTo('manage_membergroups'))
	{
		$request = $smcFunc['db_query']('', '
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
		$context['member_groups'] = array(0 => $txt['admin_register_group_none']);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['member_groups'][$row['id_group']] = $row['group_name'];
		$smcFunc['db_free_result']($request);
	}
	else
		$context['member_groups'] = array();

	// Basic stuff.
	$context['sub_template'] = 'admin_register';
	$context['page_title'] = $txt['registration_center'];
	createToken('admin-regc');
	loadJavaScriptFile('register.js', array('defer' => false, 'minimize' => true), 'smf_register');
}

/**
 * Allows the administrator to edit the registration agreement, and choose whether
 * it should be shown or not. It writes and saves the agreement to the agreement.txt
 * file.
 * Accessed by ?action=admin;area=regcenter;sa=agreement.
 * Requires the admin_forum permission.
 *
 * @uses Admin template and the edit_agreement sub template.
 */
function EditAgreement()
{
	// I hereby agree not to be a lazy bum.
	global $txt, $boarddir, $context, $modSettings, $smcFunc;

	// By default we look at agreement.txt.
	$context['current_agreement'] = '';

	// Is there more than one to edit?
	$context['editable_agreements'] = array(
		'' => $txt['admin_agreement_default'],
	);

	// Get our languages.
	getLanguages();

	// Try to figure out if we have more agreements.
	foreach ($context['languages'] as $lang)
	{
		if (file_exists($boarddir . '/agreement.' . $lang['filename'] . '.txt'))
		{
			$context['editable_agreements']['.' . $lang['filename']] = $lang['name'];
			// Are we editing this?
			if (isset($_POST['agree_lang']) && $_POST['agree_lang'] == '.' . $lang['filename'])
				$context['current_agreement'] = '.' . $lang['filename'];
		}
	}

	if (isset($_POST['agreement']))
	{
		checkSession();
		validateToken('admin-rega');

		// Off it goes to the agreement file.
		$to_write = str_replace("\r", '', $_POST['agreement']);
		$bytes = file_put_contents($boarddir . '/agreement' . $context['current_agreement'] . '.txt', $to_write, LOCK_EX);

		updateSettings(array('requireAgreement' => !empty($_POST['requireAgreement'])));

		if ($bytes == strlen($to_write))
			$context['saved_successful'] = true;
		else
			$context['could_not_save'] = true;
	}

	$context['agreement'] = file_exists($boarddir . '/agreement' . $context['current_agreement'] . '.txt') ? $smcFunc['htmlspecialchars'](file_get_contents($boarddir . '/agreement' . $context['current_agreement'] . '.txt')) : '';
	$context['warning'] = is_writable($boarddir . '/agreement' . $context['current_agreement'] . '.txt') ? '' : $txt['agreement_not_writable'];
	$context['require_agreement'] = !empty($modSettings['requireAgreement']);

	$context['sub_template'] = 'edit_agreement';
	$context['page_title'] = $txt['registration_agreement'];
	createToken('admin-rega');
}

/**
 * Set the names under which users are not allowed to register.
 * Accessed by ?action=admin;area=regcenter;sa=reservednames.
 * Requires the admin_forum permission.
 *
 * @uses Register template, reserved_words sub-template.
 */
function SetReserved()
{
	global $txt, $context, $modSettings;

	// Submitting new reserved words.
	if (!empty($_POST['save_reserved_names']))
	{
		checkSession();
		validateToken('admin-regr');

		// Set all the options....
		updateSettings(array(
			'reserveWord' => (isset($_POST['matchword']) ? '1' : '0'),
			'reserveCase' => (isset($_POST['matchcase']) ? '1' : '0'),
			'reserveUser' => (isset($_POST['matchuser']) ? '1' : '0'),
			'reserveName' => (isset($_POST['matchname']) ? '1' : '0'),
			'reserveNames' => str_replace("\r", '', $_POST['reserved'])
		));
		$context['saved_successful'] = true;
	}

	// Get the reserved word options and words.
	$modSettings['reserveNames'] = str_replace('\n', "\n", $modSettings['reserveNames']);
	$context['reserved_words'] = explode("\n", $modSettings['reserveNames']);
	$context['reserved_word_options'] = array();
	$context['reserved_word_options']['match_word'] = $modSettings['reserveWord'] == '1';
	$context['reserved_word_options']['match_case'] = $modSettings['reserveCase'] == '1';
	$context['reserved_word_options']['match_user'] = $modSettings['reserveUser'] == '1';
	$context['reserved_word_options']['match_name'] = $modSettings['reserveName'] == '1';

	// Ready the template......
	$context['sub_template'] = 'edit_reserved_words';
	$context['page_title'] = $txt['admin_reserved_set'];
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
	global $txt, $context, $scripturl, $modSettings, $sourcedir;

	// This is really quite wanting.
	require_once($sourcedir . '/ManageServer.php');

	$config_vars = array(
		array('select', 'registration_method', array($txt['setting_registration_standard'], $txt['setting_registration_activate'], $txt['setting_registration_approval'], $txt['setting_registration_disabled'])),
		array('check', 'send_welcomeEmail'),
		'',

		array('int', 'coppaAge', 'subtext' => $txt['zero_to_disable'], 'onchange' => 'checkCoppa();', 'onkeyup' => 'checkCoppa();'),
		array('select', 'coppaType', array($txt['setting_coppaType_reject'], $txt['setting_coppaType_approval']), 'onchange' => 'checkCoppa();'),
		array('large_text', 'coppaPost', 'subtext' => $txt['setting_coppaPost_desc']),
		array('text', 'coppaFax'),
		array('text', 'coppaPhone'),
	);

	call_integration_hook('integrate_modify_registration_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Setup the template
	$context['sub_template'] = 'show_settings';
	$context['page_title'] = $txt['registration_center'];

	if (isset($_GET['save']))
	{
		checkSession();

		// Are there some contacts missing?
		if (!empty($_POST['coppaAge']) && !empty($_POST['coppaType']) && empty($_POST['coppaPost']) && empty($_POST['coppaFax']))
			fatal_lang_error('admin_setting_coppa_require_contact');

		// Post needs to take into account line breaks.
		$_POST['coppaPost'] = str_replace("\n", '<br>', empty($_POST['coppaPost']) ? '' : $_POST['coppaPost']);

		call_integration_hook('integrate_save_registration_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=regcenter;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=regcenter;save;sa=settings';
	$context['settings_title'] = $txt['settings'];

	// Define some javascript for COPPA.
	$context['settings_post_javascript'] = '
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
	$modSettings['coppaPost'] = !empty($modSettings['coppaPost']) ? preg_replace('~<br ?/?' . '>~', "\n", $modSettings['coppaPost']) : '';

	prepareDBSettingContext($config_vars);
}

?>