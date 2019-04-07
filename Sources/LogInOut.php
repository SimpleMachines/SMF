<?php

/**
 * This file is concerned pretty entirely, as you see from its name, with
 * logging in and out members, and the validation of that.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Ask them for their login information. (shows a page for the user to type
 *  in their username and password.)
 *  It caches the referring URL in $_SESSION['login_url'].
 *  It is accessed from ?action=login.
 *
 * @uses Login template and language file with the login sub-template.
 */
function Login()
{
	global $txt, $context, $scripturl, $user_info;

	// You are already logged in, go take a tour of the boards
	if (!empty($user_info['id']))
		redirectexit();

	// We need to load the Login template/language file.
	loadLanguage('Login');
	loadTemplate('Login');

	$context['sub_template'] = 'login';

	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
	{
		$context['from_ajax'] = true;
		$context['template_layers'] = array();
	}

	// Get the template ready.... not really much else to do.
	$context['page_title'] = $txt['login'];
	$context['default_username'] = &$_REQUEST['u'];
	$context['default_password'] = '';
	$context['never_expire'] = false;

	// Add the login chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=login',
		'name' => $txt['login'],
	);

	// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
	if (isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0)
		$_SESSION['login_url'] = $_SESSION['old_url'];
	elseif (isset($_SESSION['login_url']) && strpos($_SESSION['login_url'], 'dlattach') !== false)
		unset($_SESSION['login_url']);

	// Create a one time token.
	createToken('login');
}

/**
 * Actually logs you in.
 * What it does:
 * - checks credentials and checks that login was successful.
 * - it employs protection against a specific IP or user trying to brute force
 *  a login to an account.
 * - upgrades password encryption on login, if necessary.
 * - after successful login, redirects you to $_SESSION['login_url'].
 * - accessed from ?action=login2, by forms.
 * On error, uses the same templates Login() uses.
 */
function Login2()
{
	global $txt, $scripturl, $user_info, $user_settings, $smcFunc;
	global $cookiename, $modSettings, $context, $sourcedir, $maintenance;

	// Check to ensure we're forcing SSL for authentication
	if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
		fatal_lang_error('login_ssl_required', false);

	// Load cookie authentication stuff.
	require_once($sourcedir . '/Subs-Auth.php');

	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
	{
		$context['from_ajax'] = true;
		$context['template_layers'] = array();
	}

	if (isset($_GET['sa']) && $_GET['sa'] == 'salt' && !$user_info['is_guest'])
	{
		// First check for 2.1 json-format cookie in $_COOKIE
		if (isset($_COOKIE[$cookiename]) && preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+~', $_COOKIE[$cookiename]) === 1)
			list (,, $timeout) = $smcFunc['json_decode']($_COOKIE[$cookiename], true);

		// Try checking for 2.1 json-format cookie in $_SESSION
		elseif (isset($_SESSION['login_' . $cookiename]) && preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+~', $_SESSION['login_' . $cookiename]) === 1)
			list (,, $timeout) = $smcFunc['json_decode']($_SESSION['login_' . $cookiename]);

		// Next, try checking for 2.0 serialized string cookie in $_COOKIE
		elseif (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;i:\d+;i:1;s:(0|128):"([a-fA-F0-9]{128})?";i:2;[id]:\d+;~', $_COOKIE[$cookiename]) === 1)
			list (,, $timeout) = safe_unserialize($_COOKIE[$cookiename]);

		// Last, see if you need to fall back on checking for 2.0 serialized string cookie in $_SESSION
		elseif (isset($_SESSION['login_' . $cookiename]) && preg_match('~^a:[34]:\{i:0;i:\d+;i:1;s:(0|128):"([a-fA-F0-9]{128})?";i:2;[id]:\d+;~', $_SESSION['login_' . $cookiename]) === 1)
			list (,, $timeout) = safe_unserialize($_SESSION['login_' . $cookiename]);

		else
			trigger_error('Login2(): Cannot be logged in without a session or cookie', E_USER_ERROR);

		$user_settings['password_salt'] = substr(md5($smcFunc['random_int']()), 0, 4);
		updateMemberData($user_info['id'], array('password_salt' => $user_settings['password_salt']));

		// Preserve the 2FA cookie?
		if (!empty($modSettings['tfa_mode']) && !empty($_COOKIE[$cookiename . '_tfa']))
		{
			list (,, $exp) = $smcFunc['json_decode']($_COOKIE[$cookiename . '_tfa'], true);
			setTFACookie((int) $exp - time(), $user_info['password_salt'], hash_salt($user_settings['tfa_backup'], $user_settings['password_salt']));
		}

		setLoginCookie((int) $timeout - time(), $user_info['id'], hash_salt($user_settings['passwd'], $user_settings['password_salt']));

		redirectexit('action=login2;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
	}
	// Double check the cookie...
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'check')
	{
		// Strike!  You're outta there!
		if ($_GET['member'] != $user_info['id'])
			fatal_lang_error('login_cookie_error', false);

		$user_info['can_mod'] = allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']))));

		// Some whitelisting for login_url...
		if (empty($_SESSION['login_url']))
			redirectexit(empty($user_settings['tfa_secret']) ? '' : 'action=logintfa');
		elseif (!empty($_SESSION['login_url']) && (strpos($_SESSION['login_url'], 'http://') === false && strpos($_SESSION['login_url'], 'https://') === false))
		{
			unset ($_SESSION['login_url']);
			redirectexit(empty($user_settings['tfa_secret']) ? '' : 'action=logintfa');
		}
		elseif (!empty($user_settings['tfa_secret']))
		{
			redirectexit('action=logintfa');
		}
		else
		{
			// Best not to clutter the session data too much...
			$temp = $_SESSION['login_url'];
			unset($_SESSION['login_url']);

			redirectexit($temp);
		}
	}

	// Beyond this point you are assumed to be a guest trying to login.
	if (!$user_info['is_guest'])
		redirectexit();

	// Are you guessing with a script?
	checkSession();
	validateToken('login');
	spamProtection('login');

	// Set the login_url if it's not already set (but careful not to send us to an attachment).
	if ((empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0) || (isset($_GET['quicklogin']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'login') === false))
		$_SESSION['login_url'] = $_SESSION['old_url'];

	// Been guessing a lot, haven't we?
	if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $modSettings['failed_login_threshold'] * 3)
		fatal_lang_error('login_threshold_fail', 'login');

	// Set up the cookie length.  (if it's invalid, just fall through and use the default.)
	if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
		$modSettings['cookieTime'] = 3153600;
	elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 && $_POST['cookielength'] <= 3153600))
		$modSettings['cookieTime'] = (int) $_POST['cookielength'];

	loadLanguage('Login');
	// Load the template stuff.
	loadTemplate('Login');
	$context['sub_template'] = 'login';

	// Set up the default/fallback stuff.
	$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($_POST['user'])) : '';
	$context['default_password'] = '';
	$context['never_expire'] = $modSettings['cookieTime'] <= 525600;
	$context['login_errors'] = array($txt['error_occured']);
	$context['page_title'] = $txt['login'];

	// Add the login chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=login',
		'name' => $txt['login'],
	);

	// You forgot to type your username, dummy!
	if (!isset($_POST['user']) || $_POST['user'] == '')
	{
		$context['login_errors'] = array($txt['need_username']);
		return;
	}

	// Hmm... maybe 'admin' will login with no password. Uhh... NO!
	if (!isset($_POST['passwrd']) || $_POST['passwrd'] == '')
	{
		$context['login_errors'] = array($txt['no_password']);
		return;
	}

	// No funky symbols either.
	if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_POST['user'])) != 0)
	{
		$context['login_errors'] = array($txt['error_invalid_characters_username']);
		return;
	}

	// And if it's too long, trim it back.
	if ($smcFunc['strlen']($_POST['user']) > 80)
	{
		$_POST['user'] = $smcFunc['substr']($_POST['user'], 0, 79);
		$context['default_username'] = preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($_POST['user']));
	}

	// Are we using any sort of integration to validate the login?
	if (in_array('retry', call_integration_hook('integrate_validate_login', array($_POST['user'], isset($_POST['passwrd']) ? $_POST['passwrd'] : null, $modSettings['cookieTime'])), true))
	{
		$context['login_errors'] = array($txt['incorrect_password']);
		return;
	}

	// Load the data up!
	$request = $smcFunc['db_query']('', '
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			passwd_flood, tfa_secret
		FROM {db_prefix}members
		WHERE ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(member_name) = LOWER({string:user_name})' : 'member_name = {string:user_name}') . '
		LIMIT 1',
		array(
			'user_name' => $smcFunc['db_case_sensitive'] ? strtolower($_POST['user']) : $_POST['user'],
		)
	);
	// Probably mistyped or their email, try it as an email address. (member_name first, though!)
	if ($smcFunc['db_num_rows']($request) == 0 && strpos($_POST['user'], '@') !== false)
	{
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
				passwd_flood, tfa_secret
			FROM {db_prefix}members
			WHERE email_address = {string:user_name}
			LIMIT 1',
			array(
				'user_name' => $_POST['user'],
			)
		);
	}

	// Let them try again, it didn't match anything...
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$context['login_errors'] = array($txt['username_no_exist']);
		return;
	}

	$user_settings = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Bad password!  Thought you could fool the database?!
	if (!hash_verify_password($user_settings['member_name'], un_htmlspecialchars($_POST['passwrd']), $user_settings['passwd']))
	{
		// Let's be cautious, no hacking please. thanx.
		validatePasswordFlood($user_settings['id_member'], $user_settings['member_name'], $user_settings['passwd_flood']);

		// Maybe we were too hasty... let's try some other authentication methods.
		$other_passwords = array();

		// None of the below cases will be used most of the time (because the salt is normally set.)
		if (!empty($modSettings['enable_password_conversion']) && $user_settings['password_salt'] == '')
		{
			// YaBB SE, Discus, MD5 (used a lot), SHA-1 (used some), SMF 1.0.x, IkonBoard, and none at all.
			$other_passwords[] = crypt($_POST['passwrd'], substr($_POST['passwrd'], 0, 2));
			$other_passwords[] = crypt($_POST['passwrd'], substr($user_settings['passwd'], 0, 2));
			$other_passwords[] = md5($_POST['passwrd']);
			$other_passwords[] = sha1($_POST['passwrd']);
			$other_passwords[] = md5_hmac($_POST['passwrd'], strtolower($user_settings['member_name']));
			$other_passwords[] = md5($_POST['passwrd'] . strtolower($user_settings['member_name']));
			$other_passwords[] = md5(md5($_POST['passwrd']));
			$other_passwords[] = $_POST['passwrd'];

			// This one is a strange one... MyPHP, crypt() on the MD5 hash.
			$other_passwords[] = crypt(md5($_POST['passwrd']), md5($_POST['passwrd']));

			// Snitz style - SHA-256.  Technically, this is a downgrade, but most PHP configurations don't support sha256 anyway.
			if (strlen($user_settings['passwd']) == 64 && function_exists('mhash') && defined('MHASH_SHA256'))
				$other_passwords[] = bin2hex(mhash(MHASH_SHA256, $_POST['passwrd']));

			// phpBB3 users new hashing.  We now support it as well ;).
			$other_passwords[] = phpBB3_password_check($_POST['passwrd'], $user_settings['passwd']);

			// APBoard 2 Login Method.
			$other_passwords[] = md5(crypt($_POST['passwrd'], 'CRYPT_MD5'));
		}
		// The hash should be 40 if it's SHA-1, so we're safe with more here too.
		elseif (!empty($modSettings['enable_password_conversion']) && strlen($user_settings['passwd']) == 32)
		{
			// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
			$other_passwords[] = md5(md5($_POST['passwrd']) . stripslashes($user_settings['password_salt']));

			// Hmm.. p'raps it's Invision 2 style?
			$other_passwords[] = md5(md5($user_settings['password_salt']) . md5($_POST['passwrd']));

			// Some common md5 ones.
			$other_passwords[] = md5($user_settings['password_salt'] . $_POST['passwrd']);
			$other_passwords[] = md5($_POST['passwrd'] . $user_settings['password_salt']);
		}
		elseif (strlen($user_settings['passwd']) == 40)
		{
			// Maybe they are using a hash from before the password fix.
			// This is also valid for SMF 1.1 to 2.0 style of hashing, changed to bcrypt in SMF 2.1
			$other_passwords[] = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

			// BurningBoard3 style of hashing.
			if (!empty($modSettings['enable_password_conversion']))
				$other_passwords[] = sha1($user_settings['password_salt'] . sha1($user_settings['password_salt'] . sha1($_POST['passwrd'])));

			// Perhaps we converted to UTF-8 and have a valid password being hashed differently.
			if ($context['character_set'] == 'UTF-8' && !empty($modSettings['previousCharacterSet']) && $modSettings['previousCharacterSet'] != 'utf8')
			{
				// Try iconv first, for no particular reason.
				if (function_exists('iconv'))
					$other_passwords['iconv'] = sha1(strtolower(iconv('UTF-8', $modSettings['previousCharacterSet'], $user_settings['member_name'])) . un_htmlspecialchars(iconv('UTF-8', $modSettings['previousCharacterSet'], $_POST['passwrd'])));

				// Say it aint so, iconv failed!
				if (empty($other_passwords['iconv']) && function_exists('mb_convert_encoding'))
					$other_passwords[] = sha1(strtolower(mb_convert_encoding($user_settings['member_name'], 'UTF-8', $modSettings['previousCharacterSet'])) . un_htmlspecialchars(mb_convert_encoding($_POST['passwrd'], 'UTF-8', $modSettings['previousCharacterSet'])));
			}
		}

		// SMF's sha1 function can give a funny result on Linux (Not our fault!). If we've now got the real one let the old one be valid!
		if (stripos(PHP_OS, 'win') !== 0 && strlen($user_settings['passwd']) < hash_length())
		{
			require_once($sourcedir . '/Subs-Compat.php');
			$other_passwords[] = sha1_smf(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));
		}

		// Allows mods to easily extend the $other_passwords array
		call_integration_hook('integrate_other_passwords', array(&$other_passwords));

		// Whichever encryption it was using, let's make it use SMF's now ;).
		if (in_array($user_settings['passwd'], $other_passwords))
		{
			$user_settings['passwd'] = hash_password($user_settings['member_name'], un_htmlspecialchars($_POST['passwrd']));
			$user_settings['password_salt'] = substr(md5($smcFunc['random_int']()), 0, 4);

			// Update the password and set up the hash.
			updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd'], 'password_salt' => $user_settings['password_salt'], 'passwd_flood' => ''));
		}
		// Okay, they for sure didn't enter the password!
		else
		{
			// They've messed up again - keep a count to see if they need a hand.
			$_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? ($_SESSION['failed_login'] + 1) : 1;

			// Hmm... don't remember it, do you?  Here, try the password reminder ;).
			if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
				redirectexit('action=reminder');
			// We'll give you another chance...
			else
			{
				// Log an error so we know that it didn't go well in the error log.
				log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

				$context['login_errors'] = array($txt['incorrect_password']);
				return;
			}
		}
	}
	elseif (!empty($user_settings['passwd_flood']))
	{
		// Let's be sure they weren't a little hacker.
		validatePasswordFlood($user_settings['id_member'], $user_settings['member_name'], $user_settings['passwd_flood'], true);

		// If we got here then we can reset the flood counter.
		updateMemberData($user_settings['id_member'], array('passwd_flood' => ''));
	}

	// Correct password, but they've got no salt; fix it!
	if ($user_settings['password_salt'] == '')
	{
		$user_settings['password_salt'] = substr(md5($smcFunc['random_int']()), 0, 4);
		updateMemberData($user_settings['id_member'], array('password_salt' => $user_settings['password_salt']));
	}

	// Check their activation status.
	if (!checkActivation())
		return;

	DoLogin();
}

/**
 * Allows the user to enter their Two-Factor Authentication code
 */
function LoginTFA()
{
	global $sourcedir, $txt, $context, $user_info, $modSettings, $scripturl;

	if (!$user_info['is_guest'] || empty($context['tfa_member']) || empty($modSettings['tfa_mode']))
		fatal_lang_error('no_access', false);

	loadLanguage('Profile');
	require_once($sourcedir . '/Class-TOTP.php');

	$member = $context['tfa_member'];

	// Prevent replay attacks by limiting at least 2 minutes before they can log in again via 2FA
	if (time() - $member['last_login'] < 120)
		fatal_lang_error('tfa_wait', false);

	$totp = new \TOTP\Auth($member['tfa_secret']);
	$totp->setRange(1);

	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
	{
		$context['from_ajax'] = true;
		$context['template_layers'] = array();
	}

	if (!empty($_POST['tfa_code']) && empty($_POST['tfa_backup']))
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
			fatal_lang_error('login_ssl_required', false);

		$code = $_POST['tfa_code'];

		if (strlen($code) == $totp->getCodeLength() && $totp->validateCode($code))
		{
			updateMemberData($member['id_member'], array('last_login' => time()));

			setTFACookie(3153600, $member['id_member'], hash_salt($member['tfa_backup'], $member['password_salt']));
			redirectexit();
		}
		else
		{
			validatePasswordFlood($member['id_member'], $member['member_name'], $member['passwd_flood'], false, true);

			$context['tfa_error'] = true;
			$context['tfa_value'] = $_POST['tfa_code'];
		}
	}
	elseif (!empty($_POST['tfa_backup']))
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
			fatal_lang_error('login_ssl_required', false);

		$backup = $_POST['tfa_backup'];

		if (hash_verify_password($member['member_name'], $backup, $member['tfa_backup']))
		{
			// Get rid of their current TFA settings
			updateMemberData($member['id_member'], array(
				'tfa_secret' => '',
				'tfa_backup' => '',
				'last_login' => time(),
			));
			setTFACookie(3153600, $member['id_member'], hash_salt($member['tfa_backup'], $member['password_salt']));
			redirectexit('action=profile;area=tfasetup;backup');
		}
		else
		{
			validatePasswordFlood($member['id_member'], $member['member_name'], $member['passwd_flood'], false, true);

			$context['tfa_backup_error'] = true;
			$context['tfa_value'] = $_POST['tfa_code'];
			$context['tfa_backup_value'] = $_POST['tfa_backup'];
		}
	}

	loadTemplate('Login');
	$context['sub_template'] = 'login_tfa';
	$context['page_title'] = $txt['login'];
	$context['tfa_url'] = $scripturl . '?action=logintfa';
}

/**
 * Check activation status of the current user.
 */
function checkActivation()
{
	global $context, $txt, $scripturl, $user_settings, $modSettings;

	if (!isset($context['login_errors']))
		$context['login_errors'] = array();

	// What is the true activation status of this account?
	$activation_status = $user_settings['is_activated'] > 10 ? $user_settings['is_activated'] - 10 : $user_settings['is_activated'];

	// Check if the account is activated - COPPA first...
	if ($activation_status == 5)
	{
		$context['login_errors'][] = $txt['coppa_no_concent'] . ' <a href="' . $scripturl . '?action=coppa;member=' . $user_settings['id_member'] . '">' . $txt['coppa_need_more_details'] . '</a>';
		return false;
	}
	// Awaiting approval still?
	elseif ($activation_status == 3)
		fatal_lang_error('still_awaiting_approval', 'user');
	// Awaiting deletion, changed their mind?
	elseif ($activation_status == 4)
	{
		if (isset($_REQUEST['undelete']))
		{
			updateMemberData($user_settings['id_member'], array('is_activated' => 1));
			updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > 0 ? $modSettings['unapprovedMembers'] - 1 : 0)));
		}
		else
		{
			$context['disable_login_hashing'] = true;
			$context['login_errors'][] = $txt['awaiting_delete_account'];
			$context['login_show_undelete'] = true;
			return false;
		}
	}
	// Standard activation?
	elseif ($activation_status != 1)
	{
		log_error($txt['activate_not_completed1'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', false);

		$context['login_errors'][] = $txt['activate_not_completed1'] . ' <a href="' . $scripturl . '?action=activate;sa=resend;u=' . $user_settings['id_member'] . '">' . $txt['activate_not_completed2'] . '</a>';
		return false;
	}
	return true;
}

/**
 * Perform the logging in. (set cookie, call hooks, etc)
 */
function DoLogin()
{
	global $user_info, $user_settings, $smcFunc;
	global $maintenance, $modSettings, $context, $sourcedir;

	// Load cookie authentication stuff.
	require_once($sourcedir . '/Subs-Auth.php');

	// Call login integration functions.
	call_integration_hook('integrate_login', array($user_settings['member_name'], null, $modSettings['cookieTime']));

	// Get ready to set the cookie...
	$user_info['id'] = $user_settings['id_member'];

	// Bam!  Cookie set.  A session too, just in case.
	setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], hash_salt($user_settings['passwd'], $user_settings['password_salt']));

	// Reset the login threshold.
	if (isset($_SESSION['failed_login']))
		unset($_SESSION['failed_login']);

	$user_info['is_guest'] = false;
	$user_settings['additional_groups'] = explode(',', $user_settings['additional_groups']);
	$user_info['is_admin'] = $user_settings['id_group'] == 1 || in_array(1, $user_settings['additional_groups']);

	// Are you banned?
	is_not_banned(true);

	// Don't stick the language or theme after this point.
	unset($_SESSION['language'], $_SESSION['id_theme']);

	// First login?
	$request = $smcFunc['db_query']('', '
		SELECT last_login
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
			AND last_login = 0',
		array(
			'id_member' => $user_info['id'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 1)
		$_SESSION['first_login'] = true;
	else
		unset($_SESSION['first_login']);
	$smcFunc['db_free_result']($request);

	// You've logged in, haven't you?
	$update = array('member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']);
	if (empty($user_settings['tfa_secret']))
		$update['last_login'] = time();
	updateMemberData($user_info['id'], $update);

	// Get rid of the online entry for that old guest....
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => 'ip' . $user_info['ip'],
		)
	);
	$_SESSION['log_time'] = 0;

	// Log this entry, only if we have it enabled.
	if (!empty($modSettings['loginHistoryDays']))
		$smcFunc['db_insert']('insert',
			'{db_prefix}member_logins',
			array(
				'id_member' => 'int', 'time' => 'int', 'ip' => 'inet', 'ip2' => 'inet',
			),
			array(
				$user_info['id'], time(), $user_info['ip'], $user_info['ip2']
			),
			array(
				'id_member', 'time'
			)
		);

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty($maintenance) || allowedTo('admin_forum'))
		redirectexit('action=login2;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
	else
		redirectexit('action=logout;' . $context['session_var'] . '=' . $context['session_id'], $context['server']['needs_login_fix']);
}

/**
 * Logs the current user out of their account.
 * It requires that the session hash is sent as well, to prevent automatic logouts by images or javascript.
 * It redirects back to $_SESSION['logout_url'], if it exists.
 * It is accessed via ?action=logout;session_var=...
 *
 * @param bool $internal If true, it doesn't check the session
 * @param bool $redirect Whether or not to redirect the user after they log out
 */
function Logout($internal = false, $redirect = true)
{
	global $sourcedir, $user_info, $user_settings, $context, $smcFunc, $cookiename, $modSettings;

	// Make sure they aren't being auto-logged out.
	if (!$internal)
		checkSession('get');

	require_once($sourcedir . '/Subs-Auth.php');

	if (isset($_SESSION['pack_ftp']))
		$_SESSION['pack_ftp'] = null;

	// It won't be first login anymore.
	unset($_SESSION['first_login']);

	// Just ensure they aren't a guest!
	if (!$user_info['is_guest'])
	{
		// Pass the logout information to integrations.
		call_integration_hook('integrate_logout', array($user_settings['member_name']));

		// If you log out, you aren't online anymore :P.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);
	}

	$_SESSION['log_time'] = 0;

	// Empty the cookie! (set it in the past, and for id_member = 0)
	setLoginCookie(-3600, 0);

	// And some other housekeeping while we're at it.
	$salt = substr(md5($smcFunc['random_int']()), 0, 4);
	if (!empty($user_info['id']))
		updateMemberData($user_info['id'], array('password_salt' => $salt));

	if (!empty($modSettings['tfa_mode']) && !empty($user_info['id']) && !empty($_COOKIE[$cookiename . '_tfa']))
	{
		list (,, $exp) = $smcFunc['json_decode']($_COOKIE[$cookiename . '_tfa'], true);
		setTFACookie((int) $exp - time(), $salt, hash_salt($user_settings['tfa_backup'], $salt));
	}

	session_destroy();

	// Off to the merry board index we go!
	if ($redirect)
	{
		if (empty($_SESSION['logout_url']))
			redirectexit('', $context['server']['needs_login_fix']);
		elseif (!empty($_SESSION['logout_url']) && (strpos($_SESSION['logout_url'], 'http://') === false && strpos($_SESSION['logout_url'], 'https://') === false))
		{
			unset ($_SESSION['logout_url']);
			redirectexit();
		}
		else
		{
			$temp = $_SESSION['logout_url'];
			unset($_SESSION['logout_url']);

			redirectexit($temp, $context['server']['needs_login_fix']);
		}
	}
}

/**
 * MD5 Encryption used for older passwords. (SMF 1.0.x/YaBB SE 1.5.x hashing)
 *
 * @param string $data The data
 * @param string $key The key
 * @return string The HMAC MD5 of data with key
 */
function md5_hmac($data, $key)
{
	$key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
	return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)) . $data)));
}

/**
 * Custom encryption for phpBB3 based passwords.
 *
 * @param string $passwd The raw (unhashed) password
 * @param string $passwd_hash The hashed password
 * @return string The hashed version of $passwd
 */
function phpBB3_password_check($passwd, $passwd_hash)
{
	// Too long or too short?
	if (strlen($passwd_hash) != 34)
		return;

	// Range of characters allowed.
	$range = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	// Tests
	$strpos = strpos($range, $passwd_hash[3]);
	$count = 1 << $strpos;
	$salt = substr($passwd_hash, 4, 8);

	$hash = md5($salt . $passwd, true);
	for (; $count != 0; --$count)
		$hash = md5($hash . $passwd, true);

	$output = substr($passwd_hash, 0, 12);
	$i = 0;
	while ($i < 16)
	{
		$value = ord($hash[$i++]);
		$output .= $range[$value & 0x3f];

		if ($i < 16)
			$value |= ord($hash[$i]) << 8;

		$output .= $range[($value >> 6) & 0x3f];

		if ($i++ >= 16)
			break;

		if ($i < 16)
			$value |= ord($hash[$i]) << 16;

		$output .= $range[($value >> 12) & 0x3f];

		if ($i++ >= 16)
			break;

		$output .= $range[($value >> 18) & 0x3f];
	}

	// Return now.
	return $output;
}

/**
 * This protects against brute force attacks on a member's password.
 * Importantly, even if the password was right we DON'T TELL THEM!
 *
 * @param int $id_member The ID of the member
 * @param string $member_name The name of the member.
 * @param bool|string $password_flood_value False if we don't have a flood value, otherwise a string with a timestamp and number of tries separated by a |
 * @param bool $was_correct Whether or not the password was correct
 * @param bool $tfa Whether we're validating for two-factor authentication
 */
function validatePasswordFlood($id_member, $member_name, $password_flood_value = false, $was_correct = false, $tfa = false)
{
	global $cookiename, $sourcedir;

	// As this is only brute protection, we allow 5 attempts every 10 seconds.

	// Destroy any session or cookie data about this member, as they validated wrong.
	// Only if they're not validating for 2FA
	if (!$tfa)
	{
		require_once($sourcedir . '/Subs-Auth.php');
		setLoginCookie(-3600, 0);

		if (isset($_SESSION['login_' . $cookiename]))
			unset($_SESSION['login_' . $cookiename]);
	}

	// We need a member!
	if (!$id_member)
	{
		// Redirect back!
		redirectexit();

		// Probably not needed, but still make sure...
		fatal_lang_error('no_access', false);
	}

	// Right, have we got a flood value?
	if ($password_flood_value !== false)
		@list ($time_stamp, $number_tries) = explode('|', $password_flood_value);

	// Timestamp or number of tries invalid?
	if (empty($number_tries) || empty($time_stamp))
	{
		$number_tries = 0;
		$time_stamp = time();
	}

	// They've failed logging in already
	if (!empty($number_tries))
	{
		// Give them less chances if they failed before
		$number_tries = $time_stamp < time() - 20 ? 2 : $number_tries;

		// They are trying too fast, make them wait longer
		if ($time_stamp < time() - 10)
			$time_stamp = time();
	}

	$number_tries++;

	// Broken the law?
	if ($number_tries > 5)
		fatal_lang_error('login_threshold_brute_fail', 'login', [$member_name]);

	// Otherwise set the members data. If they correct on their first attempt then we actually clear it, otherwise we set it!
	updateMemberData($id_member, array('passwd_flood' => $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries));

}

?>