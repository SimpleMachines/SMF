<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file is concerned pretty entirely, as you see from its name, with
	logging in and out members, and the validation of that.  It contains:

	void Login()
		- shows a page for the user to type in their username and password.
		- caches the referring URL in $_SESSION['login_url'].
		- uses the Login template and language file with the login sub
		  template.
		- if you are using a wireless device, uses the protocol_login sub
		  template in the Wireless template.
		- accessed from ?action=login.

	void Login2()
		- actually logs you in and checks that login was successful.
		- employs protection against a specific IP or user trying to brute
		  force a login to an account.
		- on error, uses the same templates Login() uses.
		- upgrades password encryption on login, if necessary.
		- after successful login, redirects you to $_SESSION['login_url'].
		- accessed from ?action=login2, by forms.

	void Logout(bool internal = false)
		- logs the current user out of their account.
		- requires that the session hash is sent as well, to prevent automatic
		  logouts by images or javascript.
		- doesn't check the session if internal is true.
		- redirects back to $_SESSION['logout_url'], if it exists.
		- accessed via ?action=logout;session_var=...

	string md5_hmac(string data, string key)
		- old style SMF 1.0.x/YaBB SE 1.5.x hashing.
		- returns the HMAC MD5 of data with key.

	string phpBB3_password_check(string passwd, string passwd_hash)
		- custom encryption for phpBB3 based passwords.

	void validatePasswordFlood(id_member, password_flood_value = false, was_correct = false)
		- this function helps protect against brute force attacks on a member's password.
*/

// Ask them for their login information.
function Login()
{
	global $txt, $context, $scripturl;

	// In wireless?  If so, use the correct sub template.
	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_login';
	// Otherwise, we need to load the Login template/language file.
	else
	{
		loadLanguage('Login');
		loadTemplate('Login');
		$context['sub_template'] = 'login';
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
	else
		unset($_SESSION['login_url']);
}

// Perform the actual logging-in.
function Login2()
{
	global $txt, $scripturl, $user_info, $user_settings, $smcFunc;
	global $cookiename, $maintenance, $modSettings, $context, $sc, $sourcedir;

	// Load cookie authentication stuff.
	require_once($sourcedir . '/Subs-Auth.php');

	if (isset($_GET['sa']) && $_GET['sa'] == 'salt' && !$user_info['is_guest'])
	{
		if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
			list (, , $timeout) = @unserialize($_COOKIE[$cookiename]);
		elseif (isset($_SESSION['login_' . $cookiename]))
			list (, , $timeout) = @unserialize($_SESSION['login_' . $cookiename]);
		else
			trigger_error('Login2(): Cannot be logged in without a session or cookie', E_USER_ERROR);

		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
		updateMemberData($user_info['id'], array('password_salt' => $user_settings['password_salt']));

		setLoginCookie($timeout - time(), $user_info['id'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

		redirectexit('action=login2;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
	}
	// Double check the cookie...
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'check')
	{
		// Strike!  You're outta there!
		if ($_GET['member'] != $user_info['id'])
			fatal_lang_error('login_cookie_error', false);

		// Some whitelisting for login_url...
		if (empty($_SESSION['login_url']))
			redirectexit();
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
	spamProtection('login');

	// Set the login_url if it's not already set (but careful not to send us to an attachment).
	if (empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0)
		$_SESSION['login_url'] = $_SESSION['old_url'];

	// Been guessing a lot, haven't we?
	if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $modSettings['failed_login_threshold'] * 3)
		fatal_lang_error('login_threshold_fail', 'critical');

	// Set up the cookie length.  (if it's invalid, just fall through and use the default.)
	if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
		$modSettings['cookieTime'] = 3153600;
	elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 || $_POST['cookielength'] <= 525600))
		$modSettings['cookieTime'] = (int) $_POST['cookielength'];

	loadLanguage('Login');
	// Load the template stuff - wireless or normal.
	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_login';
	else
	{
		loadTemplate('Login');
		$context['sub_template'] = 'login';
	}

	// Set up the default/fallback stuff.
	$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($_POST['user'])) : '';
	$context['default_password'] = '';
	$context['never_expire'] = $modSettings['cookieTime'] == 525600 || $modSettings['cookieTime'] == 3153600;
	$context['login_errors'] = array($txt['error_occured']);
	$context['page_title'] = $txt['login'];

	// Add the login chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=login',
		'name' => $txt['login'],
	);

	if (!empty($_POST['openid_identifier']) && !empty($modSettings['enableOpenID']))
	{
		require_once($sourcedir . '/Subs-OpenID.php');
		if (($open_id = smf_openID_validate($_POST['openid_identifier'])) !== 'no_data')
			return $open_id;
	}

	// You forgot to type your username, dummy!
	if (!isset($_POST['user']) || $_POST['user'] == '')
	{
		$context['login_errors'] = array($txt['need_username']);
		return;
	}

	// Hmm... maybe 'admin' will login with no password. Uhh... NO!
	if ((!isset($_POST['passwrd']) || $_POST['passwrd'] == '') && (!isset($_POST['hash_passwrd']) || strlen($_POST['hash_passwrd']) != 40))
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

	// Are we using any sort of integration to validate the login?
	if (in_array('retry', call_integration_hook('integrate_validate_login', array($_POST['user'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $modSettings['cookieTime'])), true))
	{
		$context['login_errors'] = array($txt['login_hash_error']);
		$context['disable_login_hashing'] = true;
		return;
	}

	// Load the data up!
	$request = $smcFunc['db_query']('', '
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			openid_uri, passwd_flood
		FROM {db_prefix}members
		WHERE ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(member_name) = LOWER({string:user_name})' : 'member_name = {string:user_name}') . '
		LIMIT 1',
		array(
			'user_name' => $smcFunc['db_case_sensitive'] ? strtolower($_POST['user']) : $_POST['user'],
		)
	);
	// Probably mistyped or their email, try it as an email address. (member_name first, though!)
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt, openid_uri,
			passwd_flood
			FROM {db_prefix}members
			WHERE email_address = {string:user_name}
			LIMIT 1',
			array(
				'user_name' => $_POST['user'],
			)
		);
		// Let them try again, it didn't match anything...
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			$context['login_errors'] = array($txt['username_no_exist']);
			return;
		}
	}

	$user_settings = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Figure out the password using SMF's encryption - if what they typed is right.
	if (isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40)
	{
		// Needs upgrading?
		if (strlen($user_settings['passwd']) != 40)
		{
			$context['login_errors'] = array($txt['login_hash_error']);
			$context['disable_login_hashing'] = true;
			unset($user_settings);
			return;
		}
		// Challenge passed.
		elseif ($_POST['hash_passwrd'] == sha1($user_settings['passwd'] . $sc))
			$sha_passwd = $user_settings['passwd'];
		else
		{
			// Don't allow this!
			validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

			$_SESSION['failed_login'] = @$_SESSION['failed_login'] + 1;

			if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
				redirectexit('action=reminder');
			else
			{
				log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

				$context['disable_login_hashing'] = true;
				$context['login_errors'] = array($txt['incorrect_password']);
				unset($user_settings);
				return;
			}
		}
	}
	else
		$sha_passwd = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

	// Bad password!  Thought you could fool the database?!
	if ($user_settings['passwd'] != $sha_passwd)
	{
		// Let's be cautious, no hacking please. thanx.
		validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

		// Maybe we were too hasty... let's try some other authentication methods.
		$other_passwords = array();

		// None of the below cases will be used most of the time (because the salt is normally set.)
		if ($user_settings['password_salt'] == '')
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
		elseif (strlen($user_settings['passwd']) == 32)
		{
			// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
			$other_passwords[] = md5(md5($_POST['passwrd']) . $user_settings['password_salt']);

			// Hmm.. p'raps it's Invision 2 style?
			$other_passwords[] = md5(md5($user_settings['password_salt']) . md5($_POST['passwrd']));

			// Some common md5 ones.
			$other_passwords[] = md5($user_settings['password_salt'] . $_POST['passwrd']);
			$other_passwords[] = md5($_POST['passwrd'] . $user_settings['password_salt']);
		}
		elseif (strlen($user_settings['passwd']) == 40)
		{
			// Maybe they are using a hash from before the password fix.
			$other_passwords[] = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

			// BurningBoard3 style of hashing.
			$other_passwords[] = sha1($user_settings['password_salt'] . sha1($user_settings['password_salt'] . sha1($_POST['passwrd'])));

			// Perhaps we converted to UTF-8 and have a valid password being hashed differently.
			if ($context['character_set'] == 'utf8' && !empty($modSettings['previousCharacterSet']) && $modSettings['previousCharacterSet'] != 'utf8')
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
		if (strpos(strtolower(PHP_OS), 'win') !== 0)
		{
			require_once($sourcedir . '/Subs-Compat.php');
			$other_passwords[] = sha1_smf(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));
		}

		// Whichever encryption it was using, let's make it use SMF's now ;).
		if (in_array($user_settings['passwd'], $other_passwords))
		{
			$user_settings['passwd'] = $sha_passwd;
			$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);

			// Update the password and set up the hash.
			updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd'], 'password_salt' => $user_settings['password_salt'], 'passwd_flood' => ''));
		}
		// Okay, they for sure didn't enter the password!
		else
		{
			// They've messed up again - keep a count to see if they need a hand.
			$_SESSION['failed_login'] = @$_SESSION['failed_login'] + 1;

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
		validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood'], true);

		// If we got here then we can reset the flood counter.
		updateMemberData($user_settings['id_member'], array('passwd_flood' => ''));
	}

	// Correct password, but they've got no salt; fix it!
	if ($user_settings['password_salt'] == '')
	{
		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
		updateMemberData($user_settings['id_member'], array('password_salt' => $user_settings['password_salt']));
	}

	// Check their activation status.
	if (!checkActivation())
		return;

	DoLogin();
}

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

function DoLogin()
{
	global $txt, $scripturl, $user_info, $user_settings, $smcFunc;
	global $cookiename, $maintenance, $modSettings, $context, $sourcedir;

	// Load cookie authentication stuff.
	require_once($sourcedir . '/Subs-Auth.php');

	// Call login integration functions.
	call_integration_hook('integrate_login', array($user_settings['member_name'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $modSettings['cookieTime']));

	// Get ready to set the cookie...
	$username = $user_settings['member_name'];
	$user_info['id'] = $user_settings['id_member'];

	// Bam!  Cookie set.  A session too, just in case.
	setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

	// Reset the login threshold.
	if (isset($_SESSION['failed_login']))
		unset($_SESSION['failed_login']);

	$user_info['is_guest'] = false;
	$user_settings['additional_groups'] = explode(',', $user_settings['additional_groups']);
	$user_info['is_admin'] = $user_settings['id_group'] == 1 || in_array(1, $user_settings['additional_groups']);

	// Are you banned?
	is_not_banned(true);

	// An administrator, set up the login so they don't have to type it again.
	if ($user_info['is_admin'] && isset($user_settings['openid_uri']) && empty($user_settings['openid_uri']))
	{
		$_SESSION['admin_time'] = time();
		unset($_SESSION['just_registered']);
	}

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
	updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));

	// Get rid of the online entry for that old guest....
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => 'ip' . $user_info['ip'],
		)
	);
	$_SESSION['log_time'] = 0;

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty($maintenance) || allowedTo('admin_forum'))
		redirectexit('action=login2;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
	else
		redirectexit('action=logout;' . $context['session_var'] . '=' . $context['session_id'], $context['server']['needs_login_fix']);
}

// Log the user out.
function Logout($internal = false, $redirect = true)
{
	global $sourcedir, $user_info, $user_settings, $context, $modSettings, $smcFunc;

	// Make sure they aren't being auto-logged out.
	if (!$internal)
		checkSession('get');

	require_once($sourcedir . '/Subs-Auth.php');

	if (isset($_SESSION['pack_ftp']))
		$_SESSION['pack_ftp'] = null;

	// They cannot be open ID verified any longer.
	if (isset($_SESSION['openid']))
		unset($_SESSION['openid']);

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

	// Off to the merry board index we go!
	if ($redirect)
	{
		if (empty($_SESSION['logout_url']))
			redirectexit('', $context['server']['needs_login_fix']);
		else
		{
			$temp = $_SESSION['logout_url'];
			unset($_SESSION['logout_url']);

			redirectexit($temp, $context['server']['needs_login_fix']);
		}
	}
}

// MD5 Encryption used for older passwords.
function md5_hmac($data, $key)
{
	$key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
	return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)) . $data)));
}

// Special encryption used by phpBB3.
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
	$count2 = $count;
	$salt = substr($passwd_hash, 4, 8);

	// Things are done differently for PHP 5.
	if (@version_compare(PHP_VERSION, '5') >= 0)
	{
		$hash = md5($salt . $passwd, true);
		for (; $count != 0; --$count)
			$hash = md5($hash . $passwd, true);
	}
	else
	{
		$hash = pack('H*', md5($salt . $passwd));
		for (; $count != 0; --$count)
			$hash = pack('H*', md5($hash . $passwd));
	}

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

// This protects against brute force attacks on a member's password. Importantly even if the password was right we DON'T TELL THEM!
function validatePasswordFlood($id_member, $password_flood_value = false, $was_correct = false)
{
	global $smcFunc, $cookiename, $sourcedir;

	// As this is only brute protection, we allow 5 attempts every 10 seconds.

	// Destroy any session or cookie data about this member, as they validated wrong.
	require_once($sourcedir . '/Subs-Auth.php');
	setLoginCookie(-3600, 0);

	if (isset($_SESSION['login_' . $cookiename]))
		unset($_SESSION['login_' . $cookiename]);

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

	// Timestamp invalid or non-existent?
	if (empty($number_tries) || $time_stamp < (time() - 10))
	{
		// If it wasn't *that* long ago, don't give them another five goes.
		$number_tries = !empty($number_tries) && $time_stamp < (time() - 20) ? 2 : 0;
		$time_stamp = time();
	}

	$number_tries++;

	// Broken the law?
	if ($number_tries > 5)
		fatal_lang_error('login_threshold_brute_fail', 'critical');

	// Otherwise set the members data. If they correct on their first attempt then we actually clear it, otherwise we set it!
	updateMemberData($id_member, array('passwd_flood' => $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries));

}

?>