<?php

/**
 * Handle sending out reminders, and checking the secret answer and question.  It uses just a few functions to do this, which are:
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
 * This is the controlling delegator
 *
 * @uses Profile language files and Reminder template
 */
function RemindMe()
{
	global $txt, $context;

	loadLanguage('Profile');
	loadTemplate('Reminder');

	$context['page_title'] = $txt['authentication_reminder'];
	$context['robot_no_index'] = true;

	// Delegation can be useful sometimes.
	$subActions = array(
		'picktype' => 'RemindPick',
		'secret2' => 'SecretAnswer2',
		'setpassword' => 'setPassword',
		'setpassword2' => 'setPassword2'
	);

	// Any subaction?  If none, fall through to the main template, which will ask for one.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		call_helper($subActions[$_REQUEST['sa']]);

	// Creating a one time token.
	else
		createToken('remind');
}

/**
 * Allows the user to pick how they wish to be reminded
 */
function RemindPick()
{
	global $context, $txt, $scripturl, $sourcedir, $user_info, $webmaster_email, $smcFunc, $language, $modSettings;

	checkSession();
	validateToken('remind');
	createToken('remind');

	// Coming with a known ID?
	if (!empty($_REQUEST['uid']))
	{
		$where = 'id_member = {int:id_member}';
		$where_params['id_member'] = (int) $_REQUEST['uid'];
	}
	elseif (isset($_POST['user']) && $_POST['user'] != '')
	{
		$where = 'member_name = {string:member_name}';
		$where_params['member_name'] = $_POST['user'];
		$where_params['email_address'] = $_POST['user'];
	}

	// You must enter a username/email address.
	if (empty($where))
		fatal_lang_error('username_no_exist', false);

	// Make sure we are not being slammed
	// Don't call this if you're coming from the "Choose a reminder type" page - otherwise you'll likely get an error
	if (!isset($_POST['reminder_type']) || !in_array($_POST['reminder_type'], array('email', 'secret')))
	{
		spamProtection('remind');
	}

	// Find the user!
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, member_name, email_address, is_activated, validation_code, lngfile, secret_question
		FROM {db_prefix}members
		WHERE ' . $where . '
		LIMIT 1',
		$where_params
	);
	// Maybe email?
	if ($smcFunc['db_num_rows']($request) == 0 && empty($_REQUEST['uid']))
	{
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, member_name, email_address, is_activated, validation_code, lngfile, secret_question
			FROM {db_prefix}members
			WHERE email_address = {string:email_address}
			LIMIT 1',
			$where_params
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_user_with_email', false);
	}

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// If the user isn't activated/approved, give them some feedback on what to do next.
	if ($row['is_activated'] != 1)
	{
		// Awaiting approval...
		if (trim($row['validation_code']) == '')
			fatal_error(sprintf($txt['registration_not_approved'], $scripturl . '?action=activate;user=' . $_POST['user']), false);
		else
			fatal_error(sprintf($txt['registration_not_activated'], $scripturl . '?action=activate;user=' . $_POST['user']), false);
	}

	// You can't get emailed if you have no email address.
	$row['email_address'] = trim($row['email_address']);
	if ($row['email_address'] == '')
		fatal_error($txt['no_reminder_email'] . '<br>' . $txt['send_email'] . ' <a href="mailto:' . $webmaster_email . '">webmaster</a> ' . $txt['to_ask_password'] . '.');

	// If they have no secret question then they can only get emailed the item, or they are requesting the email, send them an email.
	if (empty($row['secret_question']) || (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'email'))
	{
		// Randomly generate a new password, with only alpha numeric characters that is a max length of 10 chars.
		require_once($sourcedir . '/Subs-Members.php');
		$password = generateValidationCode();

		require_once($sourcedir . '/Subs-Post.php');
		$replacements = array(
			'REALNAME' => $row['real_name'],
			'REMINDLINK' => $scripturl . '?action=reminder;sa=setpassword;u=' . $row['id_member'] . ';code=' . $password,
			'IP' => $user_info['ip'],
			'MEMBERNAME' => $row['member_name'],
		);

		$emaildata = loadEmailTemplate('forgot_password', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);
		$context['description'] = $txt['reminder_sent'];

		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'reminder', $emaildata['is_html'], 1);

		// Set the password in the database.
		updateMemberData($row['id_member'], array('validation_code' => substr(md5($password), 0, 10)));

		// Set up the template.
		$context['sub_template'] = 'sent';

		// Don't really.
		return;
	}
	// Otherwise are ready to answer the question?
	elseif (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'secret')
	{
		return SecretAnswerInput();
	}

	// No we're here setup the context for template number 2!
	$context['sub_template'] = 'reminder_pick';
	$context['current_member'] = array(
		'id' => $row['id_member'],
		'name' => $row['member_name'],
	);
}

/**
 * Allows the user to set their new password
 */
function setPassword()
{
	global $txt, $context;

	loadLanguage('Login');

	// You need a code!
	if (!isset($_REQUEST['code']))
		fatal_lang_error('no_access', false);

	// Fill the context array.
	$context += array(
		'page_title' => $txt['reminder_set_password'],
		'sub_template' => 'set_password',
		'code' => $_REQUEST['code'],
		'memID' => (int) $_REQUEST['u']
	);

	loadJavaScriptFile('register.js', array('defer' => false, 'minimize' => true), 'smf_register');

	// Tokens!
	createToken('remind-sp');
}

/**
 * Actually sets the new password
 */
function setPassword2()
{
	global $context, $txt, $smcFunc, $sourcedir;

	checkSession();
	validateToken('remind-sp');

	if (empty($_POST['u']) || !isset($_POST['passwrd1']) || !isset($_POST['passwrd2']))
		fatal_lang_error('no_access', false);

	$_POST['u'] = (int) $_POST['u'];

	if ($_POST['passwrd1'] != $_POST['passwrd2'])
		fatal_lang_error('passwords_dont_match', false);

	if ($_POST['passwrd1'] == '')
		fatal_lang_error('no_password', false);

	loadLanguage('Login');

	// Get the code as it should be from the database.
	$request = $smcFunc['db_query']('', '
		SELECT validation_code, member_name, email_address, passwd_flood
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
			AND is_activated = {int:is_activated}
			AND validation_code != {string:blank_string}
		LIMIT 1',
		array(
			'id_member' => $_POST['u'],
			'is_activated' => 1,
			'blank_string' => '',
		)
	);

	// Does this user exist at all?
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('invalid_userid', false);

	list ($realCode, $username, $email, $flood_value) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Is the password actually valid?
	require_once($sourcedir . '/Subs-Auth.php');
	$passwordError = validatePassword($_POST['passwrd1'], $username, array($email));

	// What - it's not?
	if ($passwordError != null)
		fatal_lang_error('profile_error_password_' . $passwordError, false);

	require_once($sourcedir . '/LogInOut.php');

	// Quit if this code is not right.
	if (empty($_POST['code']) || substr($realCode, 0, 10) !== substr(md5($_POST['code']), 0, 10))
	{
		// Stop brute force attacks like this.
		validatePasswordFlood($_POST['u'], $flood_value, false);

		fatal_error($txt['invalid_activation_code'], false);
	}

	// Just in case, flood control.
	validatePasswordFlood($_POST['u'], $flood_value, true);

	// User validated.  Update the database!
	updateMemberData($_POST['u'], array('validation_code' => '', 'passwd' => hash_password($username, $_POST['passwrd1'])));

	call_integration_hook('integrate_reset_pass', array($username, $username, $_POST['passwrd1']));

	loadTemplate('Login');
	$context += array(
		'page_title' => $txt['reminder_password_set'],
		'sub_template' => 'login',
		'default_username' => $username,
		'default_password' => $_POST['passwrd1'],
		'never_expire' => false,
		'description' => $txt['reminder_password_set']
	);

	createToken('login');
}

/**
 * Allows the user to enter their secret answer
 */
function SecretAnswerInput()
{
	global $context, $smcFunc;

	checkSession();

	// Strings for the register auto javascript clever stuffy wuffy.
	loadLanguage('Login');

	// Check they entered something...
	if (empty($_REQUEST['uid']))
		fatal_lang_error('username_no_exist', false);

	// Get the stuff....
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, member_name, secret_question
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_member' => (int) $_REQUEST['uid'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('username_no_exist', false);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// If there is NO secret question - then throw an error.
	if (trim($row['secret_question']) == '')
		fatal_lang_error('registration_no_secret_question', false);

	// Ask for the answer...
	$context['remind_user'] = $row['id_member'];
	$context['remind_type'] = '';
	$context['secret_question'] = $row['secret_question'];

	$context['sub_template'] = 'ask';
	createToken('remind-sai');
	loadJavaScriptFile('register.js', array('defer' => false, 'minimize' => true), 'smf_register');
}

/**
 * Validates the secret answer input by the user
 */
function SecretAnswer2()
{
	global $txt, $context, $smcFunc, $sourcedir;

	checkSession();
	validateToken('remind-sai');

	// Hacker?  How did you get this far without an email or username?
	if (empty($_REQUEST['uid']))
		fatal_lang_error('username_no_exist', false);

	loadLanguage('Login');

	// Get the information from the database.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, member_name, secret_answer, secret_question, email_address
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_member' => $_REQUEST['uid'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('username_no_exist', false);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	/*
	 * Check if the secret answer is correct.
	 * In 2.1 this was changed to use hash_(verify_)passsword, same as the password. The length of the hash is 60 characters.
	 * Prior to 2.1 this was a simple md5.  The length of the hash is 32 characters.
	 * For compatibility with older answers, we still check if a match occurs on md5.  As there is a difference in the hash lengths, there isn't a possiblity of a cross match between the hashes.  This will ensure in future answer updates will prevent md5 methods from working.
	*/
	if ($row['secret_question'] == '' || $row['secret_answer'] == '' || (!hash_verify_password($row['member_name'], $_POST['secret_answer'], $row['secret_answer']) && md5($_POST['secret_answer']) != $row['secret_answer']))
	{
		log_error(sprintf($txt['reminder_error'], $row['member_name']), 'user');
		fatal_lang_error('incorrect_answer', false);
	}

	// You can't use a blank one!
	if (strlen(trim($_POST['passwrd1'])) === 0)
		fatal_lang_error('no_password', false);

	// They have to be the same too.
	if ($_POST['passwrd1'] != $_POST['passwrd2'])
		fatal_lang_error('passwords_dont_match', false);

	// Make sure they have a strong enough password.
	require_once($sourcedir . '/Subs-Auth.php');
	$passwordError = validatePassword($_POST['passwrd1'], $row['member_name'], array($row['email_address']));

	// Invalid?
	if ($passwordError != null)
		fatal_lang_error('profile_error_password_' . $passwordError, false);

	// Alright, so long as 'yer sure.
	updateMemberData($row['id_member'], array('passwd' => hash_password($row['member_name'], $_POST['passwrd1'])));

	call_integration_hook('integrate_reset_pass', array($row['member_name'], $row['member_name'], $_POST['passwrd1']));

	// Tell them it went fine.
	loadTemplate('Login');
	$context += array(
		'page_title' => $txt['reminder_password_set'],
		'sub_template' => 'login',
		'default_username' => $row['member_name'],
		'default_password' => $_POST['passwrd1'],
		'never_expire' => false,
		'description' => $txt['reminder_password_set']
	);

	createToken('login');
}

?>