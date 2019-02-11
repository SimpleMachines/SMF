<?php

/**
 * This file has two main jobs, but they really are one.  It registers new
 * members, and it helps the administrator moderate member registrations.
 * Similarly, it handles account activation as well.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Begin the registration process.
 *
 * @param array $reg_errors Holds information about any errors that occurred
 */
function Register($reg_errors = array())
{
	global $txt, $boarddir, $context, $modSettings, $user_info;
	global $language, $scripturl, $smcFunc, $sourcedir, $cur_profile;

	// Is this an incoming AJAX check?
	if (isset($_GET['sa']) && $_GET['sa'] == 'usernamecheck')
		return RegisterCheckUsername();

	// Check if the administrator has it disabled.
	if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == '3')
		fatal_lang_error('registration_disabled', false);

	// If this user is an admin - redirect them to the admin registration page.
	if (allowedTo('moderate_forum') && !$user_info['is_guest'])
		redirectexit('action=admin;area=regcenter;sa=register');
	// You are not a guest, so you are a member - and members don't get to register twice!
	elseif (empty($user_info['is_guest']))
		redirectexit();

	loadLanguage('Login');
	loadTemplate('Register');

	// Do we need them to agree to the registration agreement, first?
	$context['require_agreement'] = !empty($modSettings['requireAgreement']);
	$context['registration_passed_agreement'] = !empty($_SESSION['registration_agreed']);
	$context['show_coppa'] = !empty($modSettings['coppaAge']);

	// Under age restrictions?
	if ($context['show_coppa'])
	{
		$context['skip_coppa'] = false;
		$context['coppa_agree_above'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_above'], $modSettings['coppaAge']);
		$context['coppa_agree_below'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_below'], $modSettings['coppaAge']);
	}

	// What step are we at?
	$current_step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : ($context['require_agreement'] ? 1 : 2);

	// Does this user agree to the registation agreement?
	if ($current_step == 1 && (isset($_POST['accept_agreement']) || isset($_POST['accept_agreement_coppa'])))
	{
		$context['registration_passed_agreement'] = $_SESSION['registration_agreed'] = true;
		$current_step = 2;

		// Skip the coppa procedure if the user says he's old enough.
		if ($context['show_coppa'])
		{
			$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);

			// Are they saying they're under age, while under age registration is disabled?
			if (empty($modSettings['coppaType']) && empty($_SESSION['skip_coppa']))
			{
				loadLanguage('Login');
				fatal_lang_error('under_age_registration_prohibited', false, array($modSettings['coppaAge']));
			}
		}
	}
	// Make sure they don't squeeze through without agreeing.
	elseif ($current_step > 1 && $context['require_agreement'] && !$context['registration_passed_agreement'])
		$current_step = 1;

	// Show the user the right form.
	$context['sub_template'] = $current_step == 1 ? 'registration_agreement' : 'registration_form';
	$context['page_title'] = $current_step == 1 ? $txt['registration_agreement'] : $txt['registration_form'];

	// Kinda need this.
	if ($context['sub_template'] == 'registration_form')
		loadJavaScriptFile('register.js', array('defer' => false, 'minimize' => true), 'smf_register');

	// Add the register chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=signup',
		'name' => $txt['register'],
	);

	// Prepare the time gate! Do it like so, in case later steps want to reset the limit for any reason, but make sure the time is the current one.
	if (!isset($_SESSION['register']))
		$_SESSION['register'] = array(
			'timenow' => time(),
			'limit' => 10, // minimum number of seconds required on this page for registration
		);
	else
		$_SESSION['register']['timenow'] = time();

	// If you have to agree to the agreement, it needs to be fetched from the file.
	if ($context['require_agreement'])
	{
		// Have we got a localized one?
		if (file_exists($boarddir . '/agreement.' . $user_info['language'] . '.txt'))
			$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.' . $user_info['language'] . '.txt'), true, 'agreement_' . $user_info['language']);
		elseif (file_exists($boarddir . '/agreement.txt'))
			$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.txt'), true, 'agreement');
		else
			$context['agreement'] = '';

		// Nothing to show, lets disable registration and inform the admin of this error
		if (empty($context['agreement']))
		{
			// No file found or a blank file, log the error so the admin knows there is a problem!
			log_error($txt['registration_agreement_missing'], 'critical');
			fatal_lang_error('registration_disabled', false);
		}
	}

	if (!empty($modSettings['userLanguage']))
	{
		$selectedLanguage = empty($_SESSION['language']) ? $language : $_SESSION['language'];

		// Do we have any languages?
		if (empty($context['languages']))
			getLanguages();

		// Try to find our selected language.
		foreach ($context['languages'] as $key => $lang)
		{
			$context['languages'][$key]['name'] = strtr($lang['name'], array('-utf8' => ''));

			// Found it!
			if ($selectedLanguage == $lang['filename'])
				$context['languages'][$key]['selected'] = true;
		}
	}

	// Any custom fields we want filled in?
	require_once($sourcedir . '/Profile.php');
	loadCustomFields(0, 'register');

	// Or any standard ones?
	if (!empty($modSettings['registration_fields']))
	{
		require_once($sourcedir . '/Profile-Modify.php');

		// Setup some important context.
		loadLanguage('Profile');
		loadTemplate('Profile');

		$context['user']['is_owner'] = true;

		// Here, and here only, emulate the permissions the user would have to do this.
		$user_info['permissions'] = array_merge($user_info['permissions'], array('profile_account_own', 'profile_extra_own', 'profile_other_own', 'profile_password_own', 'profile_website_own', 'profile_blurb'));
		$reg_fields = explode(',', $modSettings['registration_fields']);

		// Website is a little different
		if (in_array('website', $reg_fields))
		{
			unset($reg_fields['website']);
			if (isset($_POST['website_title']))
				$cur_profile['website_title'] = $smcFunc['htmlspecialchars']($_POST['website_title']);
			if (isset($_POST['website_url']))
				$cur_profile['website_url'] = $smcFunc['htmlspecialchars']($_POST['website_url']);
		}

		// We might have had some submissions on this front - go check.
		foreach ($reg_fields as $field)
			if (isset($_POST[$field]))
				$cur_profile[$field] = $smcFunc['htmlspecialchars']($_POST[$field]);

		// Load all the fields in question.
		setupProfileContext($reg_fields);
	}

	// Generate a visual verification code to make sure the user is no bot.
	if (!empty($modSettings['reg_verification']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
	// Otherwise we have nothing to show.
	else
		$context['visual_verification'] = false;

	$context += array(
		'username' => isset($_POST['user']) ? $smcFunc['htmlspecialchars']($_POST['user']) : '',
		'email' => isset($_POST['email']) ? $smcFunc['htmlspecialchars']($_POST['email']) : '',
		'notify_announcements' => !empty($_POST['notify_announcements']) ? 1 : 0,
	);

	// Were there any errors?
	$context['registration_errors'] = array();
	if (!empty($reg_errors))
		$context['registration_errors'] = $reg_errors;

	createToken('register');
}

/**
 * Actually register the member.
 */
function Register2()
{
	global $txt, $modSettings, $context, $sourcedir;
	global $smcFunc, $maintenance;

	checkSession();
	validateToken('register');

	// Check to ensure we're forcing SSL for authentication
	if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
		fatal_lang_error('register_ssl_required');

	// Start collecting together any errors.
	$reg_errors = array();

	// You can't register if it's disabled.
	if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 3)
		fatal_lang_error('registration_disabled', false);

	// Well, if you don't agree, you can't register.
	if (!empty($modSettings['requireAgreement']) && empty($_SESSION['registration_agreed']))
		redirectexit();

	// Make sure they came from *somewhere*, have a session.
	if (!isset($_SESSION['old_url']))
		redirectexit('action=signup');

	// If we don't require an agreement, we need a extra check for coppa.
	if (empty($modSettings['requireAgreement']) && !empty($modSettings['coppaAge']))
		$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);
	// Are they under age, and under age users are banned?
	if (!empty($modSettings['coppaAge']) && empty($modSettings['coppaType']) && empty($_SESSION['skip_coppa']))
	{
		loadLanguage('Errors');
		fatal_lang_error('under_age_registration_prohibited', false, array($modSettings['coppaAge']));
	}

	// Check the time gate for miscreants. First make sure they came from somewhere that actually set it up.
	if (empty($_SESSION['register']['timenow']) || empty($_SESSION['register']['limit']))
		redirectexit('action=signup');
	// Failing that, check the time on it.
	if (time() - $_SESSION['register']['timenow'] < $_SESSION['register']['limit'])
	{
		loadLanguage('Errors');
		$reg_errors[] = $txt['error_too_quickly'];
	}

	// Check whether the visual verification code was entered correctly.
	if (!empty($modSettings['reg_verification']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['visual_verification']))
		{
			loadLanguage('Errors');
			foreach ($context['visual_verification'] as $error)
				$reg_errors[] = $txt['error_' . $error];
		}
	}

	foreach ($_POST as $key => $value)
	{
		if (!is_array($_POST[$key]))
		{
			// For UTF-8, replace any kind of space with a normal space, and remove any kind of control character (incl. "\n" and "\r"), then trim.
			if ($context['utf8'])
				$_POST[$key] = $smcFunc['htmltrim'](preg_replace(array('~\p{Z}+~u', '~\p{C}+~u'), array(' ', ''), $_POST[$key]));
			// Otherwise, just remove "\n" and "\r", then trim.
			else
				$_POST[$key] = $smcFunc['htmltrim'](str_replace(array("\n", "\r"), '', $_POST[$key]));
		}
		else
			$_POST[$key] = htmltrim__recursive($_POST[$key]);
	}

	// Collect all extra registration fields someone might have filled in.
	$possible_strings = array(
		'birthdate',
		'time_format',
		'buddy_list',
		'pm_ignore_list',
		'smiley_set',
		'personal_text', 'avatar',
		'lngfile',
		'secret_question', 'secret_answer',
	);
	$possible_ints = array(
		'id_theme',
	);
	$possible_floats = array(
		'time_offset',
	);
	$possible_bools = array(
		'show_online',
	);

	// We may want to add certain things to these if selected in the admin panel.
	if (!empty($modSettings['registration_fields']))
	{
		$reg_fields = explode(',', $modSettings['registration_fields']);

		// Website is a little different
		if (in_array('website', $reg_fields))
		{
			$possible_strings = array_merge(array('website_url', 'website_title'), $possible_strings);

			// Make sure their website URL is squeaky clean
			if (isset($_POST['website_url']))
				$_POST['website_url'] = (string) validate_iri(sanitize_iri($_POST['website_url']));
		}
	}

	if (isset($_POST['secret_answer']) && $_POST['secret_answer'] != '')
		$_POST['secret_answer'] = md5($_POST['secret_answer']);

	// Needed for isReservedName() and registerMember().
	require_once($sourcedir . '/Subs-Members.php');

	// Maybe you want set the displayed name during registration
	if (isset($_POST['real_name']))
	{
		// Are you already allowed to edit the displayed name?
		if (allowedTo('profile_displayed_name') || allowedTo('moderate_forum'))
			$canEditDisplayName = true;

		// If you are a guest, will you be allowed to once you register?
		else
		{
			$request = $smcFunc['db_query']('', '
				SELECT add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:id_group} AND permission = {string:permission}',
				array(
					'id_group' => 0,
					'permission' => 'profile_displayed_name_own',
				)
			);
			list($canEditDisplayName) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Only set it if you can and if we are sure it is good
		if ($canEditDisplayName && $smcFunc['htmltrim']($_POST['real_name']) != '' && !isReservedName($_POST['real_name']) && $smcFunc['strlen']($_POST['real_name']) < 60)
			$possible_strings[] = 'real_name';
	}

	// Handle a string as a birthdate...
	if (isset($_POST['birthdate']) && $_POST['birthdate'] != '')
		$_POST['birthdate'] = strftime('%Y-%m-%d', strtotime($_POST['birthdate']));
	// Or birthdate parts...
	elseif (!empty($_POST['bday1']) && !empty($_POST['bday2']))
		$_POST['birthdate'] = sprintf('%04d-%02d-%02d', empty($_POST['bday3']) ? 0 : (int) $_POST['bday3'], (int) $_POST['bday1'], (int) $_POST['bday2']);

	// Validate the passed language file.
	if (isset($_POST['lngfile']) && !empty($modSettings['userLanguage']))
	{
		// Do we have any languages?
		if (empty($context['languages']))
			getLanguages();

		// Did we find it?
		if (isset($context['languages'][$_POST['lngfile']]))
			$_SESSION['language'] = $_POST['lngfile'];
		else
			unset($_POST['lngfile']);
	}
	else
		unset($_POST['lngfile']);

	// Set the options needed for registration.
	$regOptions = array(
		'interface' => 'guest',
		'username' => !empty($_POST['user']) ? $_POST['user'] : '',
		'email' => !empty($_POST['email']) ? $_POST['email'] : '',
		'password' => !empty($_POST['passwrd1']) ? $_POST['passwrd1'] : '',
		'password_check' => !empty($_POST['passwrd2']) ? $_POST['passwrd2'] : '',
		'check_reserved_name' => true,
		'check_password_strength' => true,
		'check_email_ban' => true,
		'send_welcome_email' => !empty($modSettings['send_welcomeEmail']),
		'require' => !empty($modSettings['coppaAge']) && empty($_SESSION['skip_coppa']) ? 'coppa' : (empty($modSettings['registration_method']) ? 'nothing' : ($modSettings['registration_method'] == 1 ? 'activation' : 'approval')),
		'extra_register_vars' => array(),
		'theme_vars' => array(),
		'timezone' => !empty($modSettings['default_timezone']) ? $modSettings['default_timezone'] : '',
	);

	// Include the additional options that might have been filled in.
	foreach ($possible_strings as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = $smcFunc['htmlspecialchars']($_POST[$var], ENT_QUOTES);
	foreach ($possible_ints as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = (int) $_POST[$var];
	foreach ($possible_floats as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = (float) $_POST[$var];
	foreach ($possible_bools as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = empty($_POST[$var]) ? 0 : 1;

	// Registration options are always default options...
	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];
	$regOptions['theme_vars'] = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : array();

	// Make sure they are clean, dammit!
	$regOptions['theme_vars'] = htmlspecialchars__recursive($regOptions['theme_vars']);

	// Check whether we have fields that simply MUST be displayed?
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_type, field_length, mask, show_reg
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}
		ORDER BY field_order',
		array(
			'is_active' => 1,
		)
	);
	$custom_field_errors = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't allow overriding of the theme variables.
		if (isset($regOptions['theme_vars'][$row['col_name']]))
			unset($regOptions['theme_vars'][$row['col_name']]);

		// Not actually showing it then?
		if (!$row['show_reg'])
			continue;

		// Prepare the value!
		$value = isset($_POST['customfield'][$row['col_name']]) ? trim($_POST['customfield'][$row['col_name']]) : '';

		// We only care for text fields as the others are valid to be empty.
		if (!in_array($row['field_type'], array('check', 'select', 'radio')))
		{
			// Is it too long?
			if ($row['field_length'] && $row['field_length'] < $smcFunc['strlen']($value))
				$custom_field_errors[] = array('custom_field_too_long', array($row['field_name'], $row['field_length']));

			// Any masks to apply?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				if ($row['mask'] == 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 255))
					$custom_field_errors[] = array('custom_field_invalid_email', array($row['field_name']));
				elseif ($row['mask'] == 'number' && preg_match('~[^\d]~', $value))
					$custom_field_errors[] = array('custom_field_not_number', array($row['field_name']));
				elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($row['mask'], 5), $value) === 0)
					$custom_field_errors[] = array('custom_field_inproper_format', array($row['field_name']));
			}
		}

		// Is this required but not there?
		if (trim($value) == '' && $row['show_reg'] > 1)
			$custom_field_errors[] = array('custom_field_empty', array($row['field_name']));
	}
	$smcFunc['db_free_result']($request);

	// Process any errors.
	if (!empty($custom_field_errors))
	{
		loadLanguage('Errors');
		foreach ($custom_field_errors as $error)
			$reg_errors[] = vsprintf($txt['error_' . $error[0]], $error[1]);
	}

	// Lets check for other errors before trying to register the member.
	if (!empty($reg_errors))
	{
		$_REQUEST['step'] = 2;
		$_SESSION['register']['limit'] = 5; // If they've filled in some details, they won't need the full 10 seconds of the limit.
		return Register($reg_errors);
	}

	$memberID = registerMember($regOptions, true);

	// What there actually an error of some kind dear boy?
	if (is_array($memberID))
	{
		$reg_errors = array_merge($reg_errors, $memberID);
		$_REQUEST['step'] = 2;
		return Register($reg_errors);
	}

	// Do our spam protection now.
	spamProtection('register');

	// Do they want to recieve announcements?
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memberID, 'announcements', true);
	$var = !empty($_POST['notify_announcements']);
	$pref = !empty($prefs[$memberID]['announcements']);

	// Don't update if the default is the same.
	if ($var != $pref)
	{
		setNotifyPrefs($memberID, array('announcements' => (int) !empty($_POST['notify_announcements'])));
	}

	// We'll do custom fields after as then we get to use the helper function!
	if (!empty($_POST['customfield']))
	{
		require_once($sourcedir . '/Profile.php');
		require_once($sourcedir . '/Profile-Modify.php');
		makeCustomFieldChanges($memberID, 'register');
	}

	// If COPPA has been selected then things get complicated, setup the template.
	if (!empty($modSettings['coppaAge']) && empty($_SESSION['skip_coppa']))
		redirectexit('action=coppa;member=' . $memberID);
	// Basic template variable setup.
	elseif (!empty($modSettings['registration_method']))
	{
		loadTemplate('Register');

		$context += array(
			'page_title' => $txt['register'],
			'title' => $txt['registration_successful'],
			'sub_template' => 'after',
			'description' => $modSettings['registration_method'] == 2 ? $txt['approval_after_registration'] : $txt['activate_after_registration']
		);
	}
	else
	{
		call_integration_hook('integrate_activate', array($regOptions['username']));

		setLoginCookie(60 * $modSettings['cookieTime'], $memberID, hash_salt($regOptions['register_vars']['passwd'], $regOptions['register_vars']['password_salt']));

		redirectexit('action=login2;sa=check;member=' . $memberID, $context['server']['needs_login_fix']);
	}
}

/**
 * Activate an users account.
 *
 * Checks for mail changes, resends password if needed.
 */
function Activate()
{
	global $context, $txt, $modSettings, $scripturl, $sourcedir, $smcFunc, $language, $user_info;

	// Logged in users should not bother to activate their accounts
	if (!empty($user_info['id']))
		redirectexit();

	loadLanguage('Login');
	loadTemplate('Login');

	if (empty($_REQUEST['u']) && empty($_POST['user']))
	{
		if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == '3')
			fatal_lang_error('no_access', false);

		$context['member_id'] = 0;
		$context['sub_template'] = 'resend';
		$context['page_title'] = $txt['invalid_activation_resend'];
		$context['can_activate'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] == '1';
		$context['default_username'] = isset($_GET['user']) ? $_GET['user'] : '';

		return;
	}

	// Get the code from the database...
	$request = $smcFunc['db_query']('', '
		SELECT id_member, validation_code, member_name, real_name, email_address, is_activated, passwd, lngfile
		FROM {db_prefix}members' . (empty($_REQUEST['u']) ? '
		WHERE member_name = {string:email_address} OR email_address = {string:email_address}' : '
		WHERE id_member = {int:id_member}') . '
		LIMIT 1',
		array(
			'id_member' => isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0,
			'email_address' => isset($_POST['user']) ? $_POST['user'] : '',
		)
	);

	// Does this user exist at all?
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$context['sub_template'] = 'retry_activate';
		$context['page_title'] = $txt['invalid_userid'];
		$context['member_id'] = 0;

		return;
	}

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Change their email address? (they probably tried a fake one first :P.)
	if (isset($_POST['new_email'], $_REQUEST['passwd']) && hash_password($row['member_name'], $_REQUEST['passwd']) == $row['passwd'] && ($row['is_activated'] == 0 || $row['is_activated'] == 2))
	{
		if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == 3)
			fatal_lang_error('no_access', false);

		if (!filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL))
			fatal_error(sprintf($txt['valid_email_needed'], $smcFunc['htmlspecialchars']($_POST['new_email'])), false);

		// Make sure their email isn't banned.
		isBannedEmail($_POST['new_email'], 'cannot_register', $txt['ban_register_prohibited']);

		// Ummm... don't even dare try to take someone else's email!!
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address = {string:email_address}
			LIMIT 1',
			array(
				'email_address' => $_POST['new_email'],
			)
		);

		if ($smcFunc['db_num_rows']($request) != 0)
			fatal_lang_error('email_in_use', false, array($smcFunc['htmlspecialchars']($_POST['new_email'])));
		$smcFunc['db_free_result']($request);

		updateMemberData($row['id_member'], array('email_address' => $_POST['new_email']));
		$row['email_address'] = $_POST['new_email'];

		$email_change = true;
	}

	// Resend the password, but only if the account wasn't activated yet.
	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'resend' && ($row['is_activated'] == 0 || $row['is_activated'] == 2) && (!isset($_REQUEST['code']) || $_REQUEST['code'] == ''))
	{
		require_once($sourcedir . '/Subs-Post.php');

		$replacements = array(
			'REALNAME' => $row['real_name'],
			'USERNAME' => $row['member_name'],
			'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $row['id_member'] . ';code=' . $row['validation_code'],
			'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $row['id_member'],
			'ACTIVATIONCODE' => $row['validation_code'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
		);

		$emaildata = loadEmailTemplate('resend_activate_message', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'resendact', $emaildata['is_html'], 0);

		$context['page_title'] = $txt['invalid_activation_resend'];

		// This will ensure we don't actually get an error message if it works!
		$context['error_title'] = '';

		fatal_lang_error(!empty($email_change) ? 'change_email_success' : 'resend_email_success', false);
	}

	// Quit if this code is not right.
	if (empty($_REQUEST['code']) || $row['validation_code'] != $_REQUEST['code'])
	{
		if (!empty($row['is_activated']))
			fatal_lang_error('already_activated', false);
		elseif ($row['validation_code'] == '')
		{
			loadLanguage('Profile');
			fatal_error(sprintf($txt['registration_not_approved'], $scripturl . '?action=activate;user=' . $row['member_name']), false);
		}

		$context['sub_template'] = 'retry_activate';
		$context['page_title'] = $txt['invalid_activation_code'];
		$context['member_id'] = $row['id_member'];

		return;
	}

	// Let the integration know that they've been activated!
	call_integration_hook('integrate_activate', array($row['member_name']));

	// Validation complete - update the database!
	updateMemberData($row['id_member'], array('is_activated' => 1, 'validation_code' => ''));

	// Also do a proper member stat re-evaluation.
	updateStats('member', false);

	if (!isset($_POST['new_email']))
	{
		require_once($sourcedir . '/Subs-Post.php');

		adminNotify('activation', $row['id_member'], $row['member_name']);
	}

	$context += array(
		'page_title' => $txt['registration_successful'],
		'sub_template' => 'login',
		'default_username' => $row['member_name'],
		'default_password' => '',
		'never_expire' => false,
		'description' => $txt['activate_success']
	);
}

/**
 * This function will display the contact information for the forum, as well a form to fill in.
 */
function CoppaForm()
{
	global $context, $modSettings, $txt, $smcFunc;

	loadLanguage('Login');
	loadTemplate('Register');

	// No User ID??
	if (!isset($_GET['member']))
		fatal_lang_error('no_access', false);

	// Get the user details...
	$request = $smcFunc['db_query']('', '
		SELECT member_name
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
			AND is_activated = {int:is_coppa}',
		array(
			'id_member' => (int) $_GET['member'],
			'is_coppa' => 5,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);
	list ($username) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (isset($_GET['form']))
	{
		// Some simple contact stuff for the forum.
		$context['forum_contacts'] = (!empty($modSettings['coppaPost']) ? $modSettings['coppaPost'] . '<br><br>' : '') . (!empty($modSettings['coppaFax']) ? $modSettings['coppaFax'] . '<br>' : '');
		$context['forum_contacts'] = !empty($context['forum_contacts']) ? $context['forum_name_html_safe'] . '<br>' . $context['forum_contacts'] : '';

		// Showing template?
		if (!isset($_GET['dl']))
		{
			// Shortcut for producing underlines.
			$context['ul'] = '<u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>';
			$context['template_layers'] = array();
			$context['sub_template'] = 'coppa_form';
			$context['page_title'] = $txt['coppa_form_title'];
			$context['coppa_body'] = str_replace(array('{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}'), array($context['ul'], $context['ul'], $username), $txt['coppa_form_body']);
		}
		// Downloading.
		else
		{
			// The data.
			$ul = '                ';
			$crlf = "\r\n";
			$data = $context['forum_contacts'] . $crlf . $txt['coppa_form_address'] . ':' . $crlf . $txt['coppa_form_date'] . ':' . $crlf . $crlf . $crlf . $txt['coppa_form_body'];
			$data = str_replace(array('{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}', '<br>', '<br>'), array($ul, $ul, $username, $crlf, $crlf), $data);

			// Send the headers.
			header('connection: close');
			header('content-disposition: attachment; filename="approval.txt"');
			header('content-type: ' . (isBrowser('ie') || isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
			header('content-length: ' . count($data));

			echo $data;
			obExit(false);
		}
	}
	else
	{
		$context += array(
			'page_title' => $txt['coppa_title'],
			'sub_template' => 'coppa',
		);

		$context['coppa'] = array(
			'body' => str_replace('{MINIMUM_AGE}', $modSettings['coppaAge'], $txt['coppa_after_registration']),
			'many_options' => !empty($modSettings['coppaPost']) && !empty($modSettings['coppaFax']),
			'post' => empty($modSettings['coppaPost']) ? '' : $modSettings['coppaPost'],
			'fax' => empty($modSettings['coppaFax']) ? '' : $modSettings['coppaFax'],
			'phone' => empty($modSettings['coppaPhone']) ? '' : str_replace('{PHONE_NUMBER}', $modSettings['coppaPhone'], $txt['coppa_send_by_phone']),
			'id' => $_GET['member'],
		);
	}
}

/**
 * Show the verification code or let it be heard.
 */
function VerificationCode()
{
	global $sourcedir, $context, $scripturl;

	$verification_id = isset($_GET['vid']) ? $_GET['vid'] : '';
	$code = $verification_id && isset($_SESSION[$verification_id . '_vv']) ? $_SESSION[$verification_id . '_vv']['code'] : (isset($_SESSION['visual_verification_code']) ? $_SESSION['visual_verification_code'] : '');

	// Somehow no code was generated or the session was lost.
	if (empty($code))
	{
		header('content-type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	// Show a window that will play the verification code.
	elseif (isset($_REQUEST['sound']))
	{
		loadLanguage('Login');
		loadTemplate('Register');

		$context['verification_sound_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand()) . ($verification_id ? ';vid=' . $verification_id : '') . ';format=.wav';
		$context['sub_template'] = 'verification_sound';
		$context['template_layers'] = array();

		obExit();
	}

	// If we have GD, try the nice code.
	elseif (empty($_REQUEST['format']))
	{
		require_once($sourcedir . '/Subs-Graphics.php');

		if (in_array('gd', get_loaded_extensions()) && !showCodeImage($code))
			send_http_status(400);

		// Otherwise just show a pre-defined letter.
		elseif (isset($_REQUEST['letter']))
		{
			$_REQUEST['letter'] = (int) $_REQUEST['letter'];
			if ($_REQUEST['letter'] > 0 && $_REQUEST['letter'] <= strlen($code) && !showLetterImage(strtolower($code{$_REQUEST['letter'] - 1})))
			{
				header('content-type: image/gif');
				die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
			}
		}
		// You must be up to no good.
		else
		{
			header('content-type: image/gif');
			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}
	}

	elseif ($_REQUEST['format'] === '.wav')
	{
		require_once($sourcedir . '/Subs-Sound.php');

		if (!createWaveFile($code))
			send_http_status(400);
	}

	// We all die one day...
	die();
}

/**
 * See if a username already exists.
 */
function RegisterCheckUsername()
{
	global $sourcedir, $context;

	// This is XML!
	loadTemplate('Xml');
	$context['sub_template'] = 'check_username';
	$context['checked_username'] = isset($_GET['username']) ? un_htmlspecialchars($_GET['username']) : '';
	$context['valid_username'] = true;

	// Clean it up like mother would.
	$context['checked_username'] = preg_replace('~[\t\n\r \x0B\0' . ($context['utf8'] ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : '\x00-\x08\x0B\x0C\x0E-\x19\xA0') . ']+~' . ($context['utf8'] ? 'u' : ''), ' ', $context['checked_username']);

	require_once($sourcedir . '/Subs-Auth.php');
	$errors = validateUsername(0, $context['checked_username'], true);

	$context['valid_username'] = empty($errors);
}

/**
 * It doesn't actually send anything, this action just shows a message for a guest.
 *
 */
function SendActivation()
{
	global $context, $txt;

	$context['user']['is_logged'] = false;
	$context['user']['is_guest'] = true;

	// Send them to the done-with-registration-login screen.
	loadTemplate('Register');

	$context['page_title'] = $txt['profile'];
	$context['sub_template'] = 'after';
	$context['title'] = $txt['activate_changed_email_title'];
	$context['description'] = $txt['activate_changed_email_desc'];

	// We're gone!
	obExit();
}

?>