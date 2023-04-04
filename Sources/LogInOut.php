<?php

/**
 * This file is concerned pretty entirely, as you see from its name, with
 * logging in and out members, and the validation of that.
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
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;
use SMF\TOTP\Auth as Tfa;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Ask them for their login information. (shows a page for the user to type
 *  in their username and password.)
 *  It caches the referring URL in $_SESSION['login_url'].
 *  It is accessed from ?action=login.
 *
 * Uses Login template and language file with the login sub-template.
 */
function Login()
{
	// You are already logged in, go take a tour of the boards
	if (!empty(User::$me->id))
	{
 		// This came from a valid hashed return url.  Or something that knows our secrets...
 		if (!empty($_REQUEST['return_hash']) && !empty($_REQUEST['return_to']) && hash_hmac('sha1', un_htmlspecialchars($_REQUEST['return_to']), Config::getAuthSecret()) == $_REQUEST['return_hash'])
			redirectexit(un_htmlspecialchars($_REQUEST['return_to']));
		else
			redirectexit();
	}

	// We need to load the Login template/language file.
	Lang::load('Login');
	loadTemplate('Login');

	Utils::$context['sub_template'] = 'login';

	/* This is true when:
	 * We have a valid header indicating a JQXHR request.  This is not sent during a cross domain request.
	 * OR we have found:
	 *		1. valid cors host
	 *  	2. A header indicating a SMF request
	 *  	3. The url has a ajax in either the GET or POST
	 *  These are not intended for security, but ensuring the request is intended for a JQXHR response.
	*/
	if (
		(
			!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
		)
		||
		(
			!empty(Utils::$context['valid_cors_found'])
			&& !empty($_SERVER['HTTP_X_SMF_AJAX'])
			&& isset($_REQUEST['ajax'])
		)
	)
	{
		Utils::$context['from_ajax'] = true;
		Utils::$context['template_layers'] = array();
	}

	// Get the template ready.... not really much else to do.
	Utils::$context['page_title'] = Lang::$txt['login'];
	Utils::$context['default_username'] = &$_REQUEST['u'];
	Utils::$context['default_password'] = '';
	Utils::$context['never_expire'] = false;

	// Add the login chain to the link tree.
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=login',
		'name' => Lang::$txt['login'],
	);

	// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
	if (isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0)
		$_SESSION['login_url'] = $_SESSION['old_url'];
	// This came from a valid hashed return url.  Or something that knows our secrets...
	elseif (!empty($_REQUEST['return_hash']) && !empty($_REQUEST['return_to']) && hash_hmac('sha1', un_htmlspecialchars($_REQUEST['return_to']), Config::getAuthSecret()) == $_REQUEST['return_hash'])
		$_SESSION['login_url'] = un_htmlspecialchars($_REQUEST['return_to']);
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
	// Check to ensure we're forcing SSL for authentication
	if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !httpsOn())
		fatal_lang_error('login_ssl_required', false);

	// Load cookie authentication stuff.
	require_once(Config::$sourcedir . '/Subs-Auth.php');

	/* This is true when:
	 * We have a valid header indicating a JQXHR request.  This is not sent during a cross domain request.
	 * OR we have found:
	 *		1. valid cors host
	 *  	2. A header indicating a SMF request
	 *  	3. The url has a ajax in either the GET or POST
	 *  These are not intended for security, but ensuring the request is intended for a JQXHR response.
	*/
	if (
		(
			!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
		)
		||
		(
			!empty(Utils::$context['valid_cors_found'])
			&& !empty($_SERVER['HTTP_X_SMF_AJAX'])
			&& isset($_REQUEST['ajax'])
		)
	)
	{
		Utils::$context['from_ajax'] = true;
		Utils::$context['template_layers'] = array();
	}

	if (isset($_GET['sa']) && $_GET['sa'] == 'salt' && !User::$me->is_guest)
	{
		// First check for 2.1 json-format cookie in $_COOKIE
		if (isset($_COOKIE[Config::$cookiename]) && preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+~', $_COOKIE[Config::$cookiename]) === 1)
			list (,, $timeout) = Utils::jsonDecode($_COOKIE[Config::$cookiename], true);

		// Try checking for 2.1 json-format cookie in $_SESSION
		elseif (isset($_SESSION['login_' . Config::$cookiename]) && preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+~', $_SESSION['login_' . Config::$cookiename]) === 1)
			list (,, $timeout) = Utils::jsonDecode($_SESSION['login_' . Config::$cookiename]);

		// Next, try checking for 2.0 serialized string cookie in $_COOKIE
		elseif (isset($_COOKIE[Config::$cookiename]) && preg_match('~^a:[34]:\{i:0;i:\d+;i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d+;~', $_COOKIE[Config::$cookiename]) === 1)
			list (,, $timeout) = safe_unserialize($_COOKIE[Config::$cookiename]);

		// Last, see if you need to fall back on checking for 2.0 serialized string cookie in $_SESSION
		elseif (isset($_SESSION['login_' . Config::$cookiename]) && preg_match('~^a:[34]:\{i:0;i:\d+;i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d+;~', $_SESSION['login_' . Config::$cookiename]) === 1)
			list (,, $timeout) = safe_unserialize($_SESSION['login_' . Config::$cookiename]);

		else
		{
			Lang::load('Errors');
			trigger_error(Lang::$txt['login_no_session_cookie'], E_USER_ERROR);
		}

		User::$me->password_salt = bin2hex(Utils::randomBytes(16));
		User::updateMemberData(User::$me->id, array('password_salt' => User::$me->password_salt));

		// Preserve the 2FA cookie?
		if (!empty(Config::$modSettings['tfa_mode']) && !empty($_COOKIE[Config::$cookiename . '_tfa']))
		{
			list (,, $exp) = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);
			setTFACookie((int) $exp - time(), User::$me->password_salt, hash_salt(User::$me->tfa_backup, User::$me->password_salt));
		}

		setLoginCookie((int) $timeout - time(), User::$me->id, hash_salt(User::$me->passwd, User::$me->password_salt));

		redirectexit('action=login2;sa=check;member=' . User::$me->id, Utils::$context['server']['needs_login_fix']);
	}
	// Double check the cookie...
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'check')
	{
		// Strike!  You're outta there!
		if ($_GET['member'] != User::$me->id)
			fatal_lang_error('login_cookie_error', false);

		User::$me->can_mod = allowedTo('access_mod_center') || (!User::$me->is_guest && (User::$me->mod_cache['gq'] != '0=1' || User::$me->mod_cache['bq'] != '0=1' || (Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']))));

		// Some whitelisting for login_url...
		if (empty($_SESSION['login_url']))
			redirectexit(empty(User::$me->tfa_secret) ? '' : 'action=logintfa');
		elseif (!empty($_SESSION['login_url']) && (strpos($_SESSION['login_url'], 'http://') === false && strpos($_SESSION['login_url'], 'https://') === false))
		{
			unset($_SESSION['login_url']);
			redirectexit(empty(User::$me->tfa_secret) ? '' : 'action=logintfa');
		}
		elseif (!empty(User::$me->tfa_secret))
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
	if (!User::$me->is_guest)
		redirectexit();

	// Are you guessing with a script?
	checkSession();
	validateToken('login');
	spamProtection('login');

	// Set the login_url if it's not already set (but careful not to send us to an attachment).
	if ((empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0) || (isset($_GET['quicklogin']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'login') === false))
		$_SESSION['login_url'] = $_SESSION['old_url'];

	// Been guessing a lot, haven't we?
	if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= Config::$modSettings['failed_login_threshold'] * 3)
		fatal_lang_error('login_threshold_fail', 'login');

	// Set up the cookie length.  (if it's invalid, just fall through and use the default.)
	if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
		Config::$modSettings['cookieTime'] = 3153600;
	elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 && $_POST['cookielength'] <= 3153600))
		Config::$modSettings['cookieTime'] = (int) $_POST['cookielength'];

	Lang::load('Login');
	// Load the template stuff.
	loadTemplate('Login');
	Utils::$context['sub_template'] = 'login';

	// Create a one time token.
	createToken('login');

	// Set up the default/fallback stuff.
	Utils::$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Utils::htmlspecialchars($_POST['user'])) : '';
	Utils::$context['default_password'] = '';
	Utils::$context['never_expire'] = Config::$modSettings['cookieTime'] <= 525600;
	Utils::$context['login_errors'] = array(Lang::$txt['error_occured']);
	Utils::$context['page_title'] = Lang::$txt['login'];

	// Add the login chain to the link tree.
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=login',
		'name' => Lang::$txt['login'],
	);

	// You forgot to type your username, dummy!
	if (!isset($_POST['user']) || $_POST['user'] == '')
	{
		Utils::$context['login_errors'] = array(Lang::$txt['need_username']);
		return;
	}

	// Hmm... maybe 'admin' will login with no password. Uhh... NO!
	if (!isset($_POST['passwrd']) || $_POST['passwrd'] == '')
	{
		Utils::$context['login_errors'] = array(Lang::$txt['no_password']);
		return;
	}

	// No funky symbols either.
	if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_POST['user'])) != 0)
	{
		Utils::$context['login_errors'] = array(Lang::$txt['error_invalid_characters_username']);
		return;
	}

	// And if it's too long, trim it back.
	if (Utils::entityStrlen($_POST['user']) > 80)
	{
		$_POST['user'] = Utils::entitySubstr($_POST['user'], 0, 79);
		Utils::$context['default_username'] = preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Utils::htmlspecialchars($_POST['user']));
	}

	// Are we using any sort of integration to validate the login?
	if (in_array('retry', call_integration_hook('integrate_validate_login', array($_POST['user'], isset($_POST['passwrd']) ? $_POST['passwrd'] : null, Config::$modSettings['cookieTime'])), true))
	{
		Utils::$context['login_errors'] = array(Lang::$txt['incorrect_password']);
		return;
	}

	// Load the data up!
	$loaded = User::load($_POST['user'], User::LOAD_BY_NAME, 'minimal');

	// Probably mistyped or their email, try it as an email address. (member_name first, though!)
	if (empty($loaded))
		$loaded = User::load($_POST['user'], User::LOAD_BY_EMAIL, 'minimal');

	// Let them try again, it didn't match anything...
	if (empty($loaded))
	{
		Utils::$context['login_errors'] = array(Lang::$txt['username_no_exist']);
		return;
	}

	User::$my_id = (reset($loaded))->id;

	// Bad password!  Thought you could fool the database?!
	if (!hash_verify_password(User::$profiles[User::$my_id]['member_name'], un_htmlspecialchars($_POST['passwrd']), User::$profiles[User::$my_id]['passwd']))
	{
		// Let's be cautious, no hacking please. thanx.
		validatePasswordFlood(User::$profiles[User::$my_id]['id_member'], User::$profiles[User::$my_id]['member_name'], User::$profiles[User::$my_id]['passwd_flood']);

		// Maybe we were too hasty... let's try some other authentication methods.
		$other_passwords = array();

		// None of the below cases will be used most of the time (because the salt is normally set.)
		if (!empty(Config::$modSettings['enable_password_conversion']) && User::$profiles[User::$my_id]['password_salt'] == '')
		{
			// YaBB SE, Discus, MD5 (used a lot), SHA-1 (used some), SMF 1.0.x, IkonBoard, and none at all.
			$other_passwords[] = crypt($_POST['passwrd'], substr($_POST['passwrd'], 0, 2));
			$other_passwords[] = crypt($_POST['passwrd'], substr(User::$profiles[User::$my_id]['passwd'], 0, 2));
			$other_passwords[] = md5($_POST['passwrd']);
			$other_passwords[] = sha1($_POST['passwrd']);
			$other_passwords[] = md5_hmac($_POST['passwrd'], strtolower(User::$profiles[User::$my_id]['member_name']));
			$other_passwords[] = md5($_POST['passwrd'] . strtolower(User::$profiles[User::$my_id]['member_name']));
			$other_passwords[] = md5(md5($_POST['passwrd']));
			$other_passwords[] = $_POST['passwrd'];
			$other_passwords[] = crypt($_POST['passwrd'], User::$profiles[User::$my_id]['passwd']);

			// This one is a strange one... MyPHP, crypt() on the MD5 hash.
			$other_passwords[] = crypt(md5($_POST['passwrd']), md5($_POST['passwrd']));

			// Snitz style - SHA-256.  Technically, this is a downgrade, but most PHP configurations don't support sha256 anyway.
			if (strlen(User::$profiles[User::$my_id]['passwd']) == 64 && function_exists('mhash') && defined('MHASH_SHA256'))
				$other_passwords[] = bin2hex(mhash(MHASH_SHA256, $_POST['passwrd']));

			// phpBB3 users new hashing.  We now support it as well ;).
			$other_passwords[] = phpBB3_password_check($_POST['passwrd'], User::$profiles[User::$my_id]['passwd']);

			// APBoard 2 Login Method.
			$other_passwords[] = md5(crypt($_POST['passwrd'], 'CRYPT_MD5'));
		}
		// If the salt is set let's try some other options
		elseif (!empty(Config::$modSettings['enable_password_conversion']) && User::$profiles[User::$my_id]['password_salt'] != '')
		{
			// PHPBB 3 check this function exists in PHP 5.5 or higher
			if (function_exists('password_verify'))
				$other_passwords[] = password_verify($_POST['passwrd'],User::$profiles[User::$my_id]['password_salt']);

			// PHP-Fusion
			$other_passwords[] = hash_hmac('sha256', $_POST['passwrd'], User::$profiles[User::$my_id]['password_salt']);

			// MyBB
			$other_passwords[] = md5(md5(User::$profiles[User::$my_id]['password_salt']) . md5($_POST['passwrd']));
		}
		// The hash should be 40 if it's SHA-1, so we're safe with more here too.
		elseif (!empty(Config::$modSettings['enable_password_conversion']) && strlen(User::$profiles[User::$my_id]['passwd']) == 32)
		{
			// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
			$other_passwords[] = md5(md5($_POST['passwrd']) . stripslashes(User::$profiles[User::$my_id]['password_salt']));

			// Hmm.. p'raps it's Invision 2 style?
			$other_passwords[] = md5(md5(User::$profiles[User::$my_id]['password_salt']) . md5($_POST['passwrd']));

			// Some common md5 ones.
			$other_passwords[] = md5(User::$profiles[User::$my_id]['password_salt'] . $_POST['passwrd']);
			$other_passwords[] = md5($_POST['passwrd'] . User::$profiles[User::$my_id]['password_salt']);
		}
		elseif (strlen(User::$profiles[User::$my_id]['passwd']) == 40)
		{
			// Maybe they are using a hash from before the password fix.
			// This is also valid for SMF 1.1 to 2.0 style of hashing, changed to bcrypt in SMF 2.1
			$other_passwords[] = sha1(strtolower(User::$profiles[User::$my_id]['member_name']) . un_htmlspecialchars($_POST['passwrd']));

			// BurningBoard3 style of hashing.
			if (!empty(Config::$modSettings['enable_password_conversion']))
				$other_passwords[] = sha1(User::$profiles[User::$my_id]['password_salt'] . sha1(User::$profiles[User::$my_id]['password_salt'] . sha1($_POST['passwrd'])));

			// PunBB
			$other_passwords[] = sha1(User::$profiles[User::$my_id]['password_salt'] . sha1($_POST['passwrd']));

			// Perhaps we converted to UTF-8 and have a valid password being hashed differently.
			if (Utils::$context['character_set'] == 'UTF-8' && !empty(Config::$modSettings['previousCharacterSet']) && Config::$modSettings['previousCharacterSet'] != 'utf8')
			{
				// Try iconv first, for no particular reason.
				if (function_exists('iconv'))
					$other_passwords['iconv'] = sha1(strtolower(iconv('UTF-8', Config::$modSettings['previousCharacterSet'], User::$profiles[User::$my_id]['member_name'])) . un_htmlspecialchars(iconv('UTF-8', Config::$modSettings['previousCharacterSet'], $_POST['passwrd'])));

				// Say it aint so, iconv failed!
				if (empty($other_passwords['iconv']) && function_exists('mb_convert_encoding'))
					$other_passwords[] = sha1(strtolower(mb_convert_encoding(User::$profiles[User::$my_id]['member_name'], 'UTF-8', Config::$modSettings['previousCharacterSet'])) . un_htmlspecialchars(mb_convert_encoding($_POST['passwrd'], 'UTF-8', Config::$modSettings['previousCharacterSet'])));
			}
		}

		// SMF's sha1 function can give a funny result on Linux (Not our fault!). If we've now got the real one let the old one be valid!
		if (stripos(PHP_OS, 'win') !== 0 && strlen(User::$profiles[User::$my_id]['passwd']) < hash_length())
		{
			require_once(Config::$sourcedir . '/Subs-Compat.php');
			$other_passwords[] = sha1_smf(strtolower(User::$profiles[User::$my_id]['member_name']) . un_htmlspecialchars($_POST['passwrd']));
		}

		// Allows mods to easily extend the $other_passwords array
		call_integration_hook('integrate_other_passwords', array(&$other_passwords));

		// Whichever encryption it was using, let's make it use SMF's now ;).
		if (in_array(User::$profiles[User::$my_id]['passwd'], $other_passwords))
		{
			User::$profiles[User::$my_id]['passwd'] = hash_password(User::$profiles[User::$my_id]['member_name'], un_htmlspecialchars($_POST['passwrd']));
			User::$profiles[User::$my_id]['password_salt'] = bin2hex(Utils::randomBytes(16));

			// Update the password and set up the hash.
			User::updateMemberData(User::$profiles[User::$my_id]['id_member'], array('passwd' => User::$profiles[User::$my_id]['passwd'], 'password_salt' => User::$profiles[User::$my_id]['password_salt'], 'passwd_flood' => ''));
		}
		// Okay, they for sure didn't enter the password!
		else
		{
			// They've messed up again - keep a count to see if they need a hand.
			$_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? ($_SESSION['failed_login'] + 1) : 1;

			// Hmm... don't remember it, do you?  Here, try the password reminder ;).
			if ($_SESSION['failed_login'] >= Config::$modSettings['failed_login_threshold'])
				redirectexit('action=reminder');
			// We'll give you another chance...
			else
			{
				// Log an error so we know that it didn't go well in the error log.
				log_error(Lang::$txt['incorrect_password'] . ' - <span class="remove">' . User::$profiles[User::$my_id]['member_name'] . '</span>', 'user');

				Utils::$context['login_errors'] = array(Lang::$txt['incorrect_password']);
				return;
			}
		}
	}
	elseif (!empty(User::$profiles[User::$my_id]['passwd_flood']))
	{
		// Let's be sure they weren't a little hacker.
		validatePasswordFlood(User::$profiles[User::$my_id]['id_member'], User::$profiles[User::$my_id]['member_name'], User::$profiles[User::$my_id]['passwd_flood'], true);

		// If we got here then we can reset the flood counter.
		User::updateMemberData(User::$profiles[User::$my_id]['id_member'], array('passwd_flood' => ''));
	}

	// Correct password, but they've got no salt; fix it!
	if (strlen(User::$profiles[User::$my_id]['password_salt']) < 32)
	{
		User::$profiles[User::$my_id]['password_salt'] = bin2hex(Utils::randomBytes(16));
		User::updateMemberData(User::$profiles[User::$my_id]['id_member'], array('password_salt' => User::$profiles[User::$my_id]['password_salt']));
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
	if (!User::$me->is_guest || empty(Utils::$context['tfa_member']) || empty(Config::$modSettings['tfa_mode']))
		fatal_lang_error('no_access', false);

	Lang::load('Profile');

	$member = Utils::$context['tfa_member'];

	// Prevent replay attacks by limiting at least 2 minutes before they can log in again via 2FA
	if (time() - $member['last_login'] < 120)
		fatal_lang_error('tfa_wait', false);

	$totp = new Tfa($member['tfa_secret']);
	$totp->setRange(1);

	/* This is true when:
	 * We have a valid header indicating a JQXHR request.  This is not sent during a cross domain request.
	 * OR we have found:
	 *		1. valid cors host
	 *  	2. A header indicating a SMF request
	 *  	3. The url has a ajax in either the GET or POST
	 *  These are not intended for security, but ensuring the request is intended for a JQXHR response.
	*/
	if (
		(
			!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
		)
		||
		(
			!empty(Utils::$context['valid_cors_found'])
			&& !empty($_SERVER['HTTP_X_SMF_AJAX'])
			&& isset($_REQUEST['ajax'])
		)
	)
	{
		Utils::$context['from_ajax'] = true;
		Utils::$context['template_layers'] = array();
	}

	if (!empty($_POST['tfa_code']) && empty($_POST['tfa_backup']))
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !httpsOn())
			fatal_lang_error('login_ssl_required', false);

		$code = $_POST['tfa_code'];

		if (strlen($code) == $totp->getCodeLength() && $totp->validateCode($code))
		{
			User::updateMemberData($member['id_member'], array('last_login' => time()));

			setTFACookie(3153600, $member['id_member'], hash_salt($member['tfa_backup'], $member['password_salt']));
			redirectexit();
		}
		else
		{
			validatePasswordFlood($member['id_member'], $member['member_name'], $member['passwd_flood'], false, true);

			Utils::$context['tfa_error'] = true;
			Utils::$context['tfa_value'] = $_POST['tfa_code'];
		}
	}
	elseif (!empty($_POST['tfa_backup']))
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !httpsOn())
			fatal_lang_error('login_ssl_required', false);

		$backup = $_POST['tfa_backup'];

		if (hash_verify_password($member['member_name'], $backup, $member['tfa_backup']))
		{
			// Get rid of their current TFA settings
			User::updateMemberData($member['id_member'], array(
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

			Utils::$context['tfa_backup_error'] = true;
			Utils::$context['tfa_value'] = $_POST['tfa_code'];
			Utils::$context['tfa_backup_value'] = $_POST['tfa_backup'];
		}
	}

	loadTemplate('Login');
	Utils::$context['sub_template'] = 'login_tfa';
	Utils::$context['page_title'] = Lang::$txt['login'];
	Utils::$context['tfa_url'] = Config::$scripturl . '?action=logintfa';
}

/**
 * Check activation status of the current user.
 */
function checkActivation()
{
	if (!isset(Utils::$context['login_errors']))
		Utils::$context['login_errors'] = array();

	// What is the true activation status of this account?
	$activation_status = User::$profiles[User::$my_id]['is_activated'] > 10 ? User::$profiles[User::$my_id]['is_activated'] - 10 : User::$profiles[User::$my_id]['is_activated'];

	// Check if the account is activated - COPPA first...
	if ($activation_status == 5)
	{
		Utils::$context['login_errors'][] = Lang::$txt['coppa_no_consent'] . ' <a href="' . Config::$scripturl . '?action=coppa;member=' . User::$profiles[User::$my_id]['id_member'] . '">' . Lang::$txt['coppa_need_more_details'] . '</a>';
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
			User::updateMemberData(User::$profiles[User::$my_id]['id_member'], array('is_activated' => 1));
			Config::updateModSettings(array('unapprovedMembers' => (Config::$modSettings['unapprovedMembers'] > 0 ? Config::$modSettings['unapprovedMembers'] - 1 : 0)));
		}
		else
		{
			Utils::$context['disable_login_hashing'] = true;
			Utils::$context['login_errors'][] = Lang::$txt['awaiting_delete_account'];
			Utils::$context['login_show_undelete'] = true;
			return false;
		}
	}
	// Standard activation?
	elseif ($activation_status != 1)
	{
		log_error(Lang::$txt['activate_not_completed1'] . ' - <span class="remove">' . User::$profiles[User::$my_id]['member_name'] . '</span>', 'user');

		Utils::$context['login_errors'][] = Lang::$txt['activate_not_completed1'] . ' <a href="' . Config::$scripturl . '?action=activate;sa=resend;u=' . User::$profiles[User::$my_id]['id_member'] . '">' . Lang::$txt['activate_not_completed2'] . '</a>';
		return false;
	}
	return true;
}

/**
 * Perform the logging in. (set cookie, call hooks, etc)
 */
function DoLogin()
{
	// Load cookie authentication stuff.
	require_once(Config::$sourcedir . '/Subs-Auth.php');

	// Call login integration functions.
	call_integration_hook('integrate_login', array(User::$profiles[User::$my_id]['member_name'], null, Config::$modSettings['cookieTime']));

	// Get ready to set the cookie...
	User::setMe(User::$my_id);

	// Bam!  Cookie set.  A session too, just in case.
	setLoginCookie(60 * Config::$modSettings['cookieTime'], User::$me->id, hash_salt(User::$me->passwd, User::$me->password_salt));

	// Reset the login threshold.
	if (isset($_SESSION['failed_login']))
		unset($_SESSION['failed_login']);

	// Are you banned?
	is_not_banned(true);

	// Don't stick the language or theme after this point.
	unset($_SESSION['language'], $_SESSION['id_theme']);

	// First login?
	if (User::$me->last_login === 0)
		$_SESSION['first_login'] = true;
	else
		unset($_SESSION['first_login']);

	// You've logged in, haven't you?
	$update = array('member_ip' => User::$me->ip, 'member_ip2' => $_SERVER['BAN_CHECK_IP']);

	if (empty(User::$me->tfa_secret))
		$update['last_login'] = time();

	User::updateMemberData(User::$me->id, $update);

	// Get rid of the online entry for that old guest....
	Db::$db->query('', '
		DELETE FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => 'ip' . User::$me->ip,
		)
	);
	$_SESSION['log_time'] = 0;

	// Log this entry, only if we have it enabled.
	if (!empty(Config::$modSettings['loginHistoryDays']))
		Db::$db->insert('insert',
			'{db_prefix}member_logins',
			array(
				'id_member' => 'int', 'time' => 'int', 'ip' => 'inet', 'ip2' => 'inet',
			),
			array(
				User::$me->id, time(), User::$me->ip, User::$me->ip2
			),
			array(
				'id_member', 'time'
			)
		);

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty(Config::$maintenance) || allowedTo('admin_forum'))
		redirectexit('action=login2;sa=check;member=' . User::$me->id, Utils::$context['server']['needs_login_fix']);
	else
		redirectexit('action=logout;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], Utils::$context['server']['needs_login_fix']);
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
	// They decided to cancel a logout?
	if (!$internal && isset($_POST['cancel']) && isset($_GET[Utils::$context['session_var']]))
		redirectexit(!empty($_SESSION['logout_return']) ? $_SESSION['logout_return'] : '');
	// Prompt to logout?
	elseif (!$internal && !isset($_GET[Utils::$context['session_var']]))
	{
		Lang::load('Login');
		loadTemplate('Login');
		Utils::$context['sub_template'] = 'logout';

		// This came from a valid hashed return url.  Or something that knows our secrets...
		if (!empty($_REQUEST['return_hash']) && !empty($_REQUEST['return_to']) && hash_hmac('sha1', un_htmlspecialchars($_REQUEST['return_to']), Config::getAuthSecret()) == $_REQUEST['return_hash'])
		{
			$_SESSION['logout_url'] = un_htmlspecialchars($_REQUEST['return_to']);
			$_SESSION['logout_return'] = $_SESSION['logout_url'];
		}
		// Setup the return address.
		elseif (isset($_SESSION['old_url']))
			$_SESSION['logout_return'] = $_SESSION['old_url'];

		// Don't go any further.
		return;
	}
	// Make sure they aren't being auto-logged out.
	elseif (!$internal && isset($_GET[Utils::$context['session_var']]))
		checkSession('get');

	require_once(Config::$sourcedir . '/Subs-Auth.php');

	if (isset($_SESSION['pack_ftp']))
		$_SESSION['pack_ftp'] = null;

	// It won't be first login anymore.
	unset($_SESSION['first_login']);

	// Just ensure they aren't a guest!
	if (!User::$me->is_guest)
	{
		// Pass the logout information to integrations.
		call_integration_hook('integrate_logout', array(User::$me->username));

		// If you log out, you aren't online anymore :P.
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => User::$me->id,
			)
		);
	}

	$_SESSION['log_time'] = 0;

	// Empty the cookie! (set it in the past, and for id_member = 0)
	setLoginCookie(-3600, 0);

	// And some other housekeeping while we're at it.
	$salt = bin2hex(Utils::randomBytes(16));
	if (!empty(User::$me->id))
		User::updateMemberData(User::$me->id, array('password_salt' => $salt));

	if (!empty(Config::$modSettings['tfa_mode']) && !empty(User::$me->id) && !empty($_COOKIE[Config::$cookiename . '_tfa']))
	{
		list (,, $exp) = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);
		setTFACookie((int) $exp - time(), $salt, hash_salt(User::$me->tfa_backup, $salt));
	}

	session_destroy();

	// Off to the merry board index we go!
	if ($redirect)
	{
		if (empty($_SESSION['logout_url']))
			redirectexit('', Utils::$context['server']['needs_login_fix']);
		elseif (!empty($_SESSION['logout_url']) && (strpos($_SESSION['logout_url'], 'http://') === false && strpos($_SESSION['logout_url'], 'https://') === false))
		{
			unset ($_SESSION['logout_url']);
			redirectexit();
		}
		else
		{
			$temp = $_SESSION['logout_url'];
			unset($_SESSION['logout_url']);

			redirectexit($temp, Utils::$context['server']['needs_login_fix']);
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
	// As this is only brute protection, we allow 5 attempts every 10 seconds.

	// Destroy any session or cookie data about this member, as they validated wrong.
	// Only if they're not validating for 2FA
	if (!$tfa)
	{
		require_once(Config::$sourcedir . '/Subs-Auth.php');
		setLoginCookie(-3600, 0);

		if (isset($_SESSION['login_' . Config::$cookiename]))
			unset($_SESSION['login_' . Config::$cookiename]);
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
	User::updateMemberData($id_member, array('passwd_flood' => $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries));

}

?>