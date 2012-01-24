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

/*	This file has functions in it to do with authentication, user handling,
	and the like.  It provides these functions:

	void setLoginCookie(int cookie_length, int id_member, string password = '')
		- sets the SMF-style login cookie and session based on the id_member
		  and password passed.
		- password should be already encrypted with the cookie salt.
		- logs the user out if id_member is zero.
		- sets the cookie and session to last the number of seconds specified
		  by cookie_length.
		- when logging out, if the globalCookies setting is enabled, attempts
		  to clear the subdomain's cookie too.

	array url_parts(bool local, bool global)
		- returns the path and domain to set the cookie on.
		- normally, local and global should be the localCookies and
		  globalCookies settings, respectively.
		- uses boardurl to determine these two things.
		- returns an array with domain and path in it, in that order.

	void KickGuest()
		- throws guests out to the login screen when guest access is off.
		- sets $_SESSION['login_url'] to $_SERVER['REQUEST_URL'].
		- uses the 'kick_guest' sub template found in Login.template.php.

	void InMaintenance()
		- display a message about being in maintenance mode.
		- display a login screen with sub template 'maintenance'.

	void adminLogin()
		- double check the verity of the admin by asking for his or her
		  password.
		- loads Login.template.php and uses the admin_login sub template.
		- sends data to template so the admin is sent on to the page they
		  wanted if their password is correct, otherwise they can try
		  again.

	string adminLogin_outputPostVars(string key, string value)
		- used by the adminLogin() function.
		- returns 'hidden' HTML form fields, containing key-value-pairs.
		- if 'value' is an array, the function is called recursively.

	array findMembers(array names, bool use_wildcards = false,
			bool buddies_only = false, int max = 500)
		- searches for members whose username, display name, or e-mail address
		  match the given pattern of array names.
		- accepts wildcards ? and * in the patern if use_wildcards is set.
		- retrieves a maximum of max members, if passed.
		- searches only buddies if buddies_only is set.
		- returns an array containing information about the matching members.

	void JSMembers()
		- called by index.php?action=findmember.
		- is used as a popup for searching members.
		- uses sub template find_members of the Help template.
		- also used to add members for PM's sent using wap2/imode protocol.

	void RequestMembers()
		- used by javascript to find members matching the request.
		- outputs each member name on its own line.

	void resetPassword(int id_member, string username = null)
		- called by Profile.php when changing someone's username.
		- checks the validity of the new username.
		- generates and sets a new password for the given user.
		- mails the new password to the email address of the user.
		- if username is not set, only a new password is generated and sent.

	string validateUsername(int memID, string username)
		- checks a username obeys a load of rules. Returns null if fine.

	string validatePassword(string password, string username,
			array restrict_in = none)
		- called when registering/choosing a password.
		- checks the password obeys the current forum settings for password
		  strength.
		- if password checking is enabled, will check that none of the words
		  in restrict_in appear in the password.
		- returns an error identifier if the password is invalid, or null.

	void rebuildModCache()
		- stores some useful information on the current users moderation powers in the session.

*/

// Actually set the login cookie...
function setLoginCookie($cookie_length, $id, $password = '')
{
	global $cookiename, $boardurl, $modSettings;

	// If changing state force them to re-address some permission caching.
	$_SESSION['mc']['time'] = 0;

	// The cookie may already exist, and have been set with different options.
	$cookie_state = (empty($modSettings['localCookies']) ? 0 : 1) | (empty($modSettings['globalCookies']) ? 0 : 2);
	if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
	{
		$array = @unserialize($_COOKIE[$cookiename]);

		// Out with the old, in with the new!
		if (isset($array[3]) && $array[3] != $cookie_state)
		{
			$cookie_url = url_parts($array[3] & 1 > 0, $array[3] & 2 > 0);
			setcookie($cookiename, serialize(array(0, '', 0)), time() - 3600, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
		}
	}

	// Get the data and path to set it on.
	$data = serialize(empty($id) ? array(0, '', 0) : array($id, $password, time() + $cookie_length, $cookie_state));
	$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

	// Set the cookie, $_COOKIE, and session variable.
	setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));

	// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
	if (empty($id) && !empty($modSettings['globalCookies']))
		setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], '', !empty($modSettings['secureCookies']));

	// Any alias URLs?  This is mainly for use with frames, etc.
	if (!empty($modSettings['forum_alias_urls']))
	{
		$aliases = explode(',', $modSettings['forum_alias_urls']);

		$temp = $boardurl;
		foreach ($aliases as $alias)
		{
			// Fake the $boardurl so we can set a different cookie.
			$alias = strtr(trim($alias), array('http://' => '', 'https://' => ''));
			$boardurl = 'http://' . $alias;

			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

			if ($cookie_url[0] == '')
				$cookie_url[0] = strtok($alias, '/');

			setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
		}

		$boardurl = $temp;
	}

	$_COOKIE[$cookiename] = $data;

	// Make sure the user logs in with a new session ID.
	if (!isset($_SESSION['login_' . $cookiename]) || $_SESSION['login_' . $cookiename] !== $data)
	{
		// Backup and remove the old session.
		$oldSessionData = $_SESSION;
		$_SESSION = array();
		session_destroy();

		// Recreate and restore the new session.
		loadSession();
		session_regenerate_id();
		$_SESSION = $oldSessionData;

		// Version 4.3.2 didn't store the cookie of the new session.
		if (version_compare(PHP_VERSION, '4.3.2') === 0)
		{
			$sessionCookieLifetime = @ini_get('session.cookie_lifetime');
			setcookie(session_name(), session_id(), time() + (empty($sessionCookieLifetime) ? $cookie_length : $sessionCookieLifetime), $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
		}

		$_SESSION['login_' . $cookiename] = $data;
	}
}

// PHP < 4.3.2 doesn't have this function
if (!function_exists('session_regenerate_id'))
{
	function session_regenerate_id()
	{
		// Too late to change the session now.
		if (headers_sent())
			return false;

		session_id(strtolower(md5(uniqid(mt_rand(), true))));
		return true;
	}

}

// Get the domain and path for the cookie...
function url_parts($local, $global)
{
	global $boardurl;

	// Parse the URL with PHP to make life easier.
	$parsed_url = parse_url($boardurl);

	// Is local cookies off?
	if (empty($parsed_url['path']) || !$local)
		$parsed_url['path'] = '';

	// Globalize cookies across domains (filter out IP-addresses)?
	if ($global && preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			$parsed_url['host'] = '.' . $parts[1];

	// We shouldn't use a host at all if both options are off.
	elseif (!$local && !$global)
		$parsed_url['host'] = '';

	// The host also shouldn't be set if there aren't any dots in it.
	elseif (!isset($parsed_url['host']) || strpos($parsed_url['host'], '.') === false)
		$parsed_url['host'] = '';

	return array($parsed_url['host'], $parsed_url['path'] . '/');
}

// Kick out a guest when guest access is off...
function KickGuest()
{
	global $txt, $context;

	loadLanguage('Login');
	loadTemplate('Login');

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	$context['sub_template'] = 'kick_guest';
	$context['page_title'] = $txt['login'];
}

// Display a message about the forum being in maintenance mode, etc.
function InMaintenance()
{
	global $txt, $mtitle, $mmessage, $context;

	loadLanguage('Login');
	loadTemplate('Login');

	// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
	header('HTTP/1.1 503 Service Temporarily Unavailable');

	// Basic template stuff..
	$context['sub_template'] = 'maintenance';
	$context['title'] = &$mtitle;
	$context['description'] = &$mmessage;
	$context['page_title'] = $txt['maintain_mode'];
}

function adminLogin()
{
	global $context, $scripturl, $txt, $user_info, $user_settings;

	loadLanguage('Admin');
	loadTemplate('Login');

	// They used a wrong password, log it and unset that.
	if (isset($_POST['admin_hash_pass']) || isset($_POST['admin_pass']))
	{
		$txt['security_wrong'] = sprintf($txt['security_wrong'], isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $txt['unknown'], $_SERVER['HTTP_USER_AGENT'], $user_info['ip']);
		log_error($txt['security_wrong'], 'critical');

		if (isset($_POST['admin_hash_pass']))
			unset($_POST['admin_hash_pass']);
		if (isset($_POST['admin_pass']))
			unset($_POST['admin_pass']);

		$context['incorrect_password'] = true;
	}

	// Figure out the get data and post data.
	$context['get_data'] = '?' . construct_query_string($_GET);
	$context['post_data'] = '';

	// Now go through $_POST.  Make sure the session hash is sent.
	$_POST[$context['session_var']] = $context['session_id'];
	foreach ($_POST as $k => $v)
		$context['post_data'] .= adminLogin_outputPostVars($k, $v);

	// Now we'll use the admin_login sub template of the Login template.
	$context['sub_template'] = 'admin_login';

	// And title the page something like "Login".
	if (!isset($context['page_title']))
		$context['page_title'] = $txt['login'];

	obExit();

	// We MUST exit at this point, because otherwise we CANNOT KNOW that the user is privileged.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

function adminLogin_outputPostVars($k, $v)
{
	global $smcFunc;

	if (!is_array($v))
		return '
<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . strtr($v, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;')) . '" />';
	else
	{
		$ret = '';
		foreach ($v as $k2 => $v2)
			$ret .= adminLogin_outputPostVars($k . '[' . $k2 . ']', $v2);

		return $ret;
	}
}

function construct_query_string($get)
{
	global $scripturl;

	$query_string = '';

	// Awww, darn.  The $scripturl contains GET stuff!
	$q = strpos($scripturl, '?');
	if ($q !== false)
	{
		parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(substr($scripturl, $q + 1), ';', '&')), $temp);

		foreach ($get as $k => $v)
		{
			// Only if it's not already in the $scripturl!
			if (!isset($temp[$k]))
				$query_string .= urlencode($k) . '=' . urlencode($v) . ';';
			// If it changed, put it out there, but with an ampersand.
			elseif ($temp[$k] != $get[$k])
				$query_string .= urlencode($k) . '=' . urlencode($v) . '&amp;';
		}
	}
	else
	{
		// Add up all the data from $_GET into get_data.
		foreach ($get as $k => $v)
			$query_string .= urlencode($k) . '=' . urlencode($v) . ';';
	}

	$query_string = substr($query_string, 0, -1);
	return $query_string;
}

// Find members by email address, username, or real name.
function findMembers($names, $use_wildcards = false, $buddies_only = false, $max = 500)
{
	global $scripturl, $user_info, $modSettings, $smcFunc;

	// If it's not already an array, make it one.
	if (!is_array($names))
		$names = explode(',', $names);

	$maybe_email = false;
	foreach ($names as $i => $name)
	{
		// Trim, and fix wildcards for each name.
		$names[$i] = trim($smcFunc['strtolower']($name));

		$maybe_email |= strpos($name, '@') !== false;

		// Make it so standard wildcards will work. (* and ?)
		if ($use_wildcards)
			$names[$i] = strtr($names[$i], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '\'' => '&#039;'));
		else
			$names[$i] = strtr($names[$i], array('\'' => '&#039;'));
	}

	// What are we using to compare?
	$comparison = $use_wildcards ? 'LIKE' : '=';

	// Nothing found yet.
	$results = array();

	// This ensures you can't search someones email address if you can't see it.
	$email_condition = allowedTo('moderate_forum') ? '' : 'hide_email = 0 AND ';

	if ($use_wildcards || $maybe_email)
		$email_condition = '
			OR (' . $email_condition . 'email_address ' . $comparison . ' \'' . implode( '\') OR (' . $email_condition . ' email_address ' . $comparison . ' \'', $names) . '\')';
	else
		$email_condition = '';

	// Get the case of the columns right - but only if we need to as things like MySQL will go slow needlessly otherwise.
	$member_name = $smcFunc['db_case_sensitive'] ? 'LOWER(member_name)' : 'member_name';
	$real_name = $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name';

	// Search by username, display name, and email address.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, email_address, hide_email
		FROM {db_prefix}members
		WHERE ({raw:member_name_search}
			OR {raw:real_name_search} {raw:email_condition})
			' . ($buddies_only ? 'AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT {int:limit}',
		array(
			'buddy_list' => $user_info['buddies'],
			'member_name_search' => $member_name . ' ' . $comparison . ' \'' . implode( '\' OR ' . $member_name . ' ' . $comparison . ' \'', $names) . '\'',
			'real_name_search' => $real_name . ' ' . $comparison . ' \'' . implode( '\' OR ' . $real_name . ' ' . $comparison . ' \'', $names) . '\'',
			'email_condition' => $email_condition,
			'limit' => $max,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$results[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'username' => $row['member_name'],
			'email' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['email_address'] : '',
			'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
		);
	}
	$smcFunc['db_free_result']($request);

	// Return all the results.
	return $results;
}

function JSMembers()
{
	global $context, $scripturl, $user_info, $smcFunc;

	checkSession('get');

	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_pm';
	else
	{
		// Why is this in the Help template, you ask?  Well, erm... it helps you.  Does that work?
		loadTemplate('Help');

		$context['template_layers'] = array();
		$context['sub_template'] = 'find_members';
	}

	if (isset($_REQUEST['search']))
		$context['last_search'] = $smcFunc['htmlspecialchars']($_REQUEST['search'], ENT_QUOTES);
	else
		$_REQUEST['start'] = 0;

	// Allow the user to pass the input to be added to to the box.
	$context['input_box_name'] = isset($_REQUEST['input']) && preg_match('~^[\w-]+$~', $_REQUEST['input']) === 1 ? $_REQUEST['input'] : 'to';

	// Take the delimiter over GET in case it's \n or something.
	$context['delimiter'] = isset($_REQUEST['delim']) ? ($_REQUEST['delim'] == 'LB' ? "\n" : $_REQUEST['delim']) : ', ';
	$context['quote_results'] = !empty($_REQUEST['quote']);

	// List all the results.
	$context['results'] = array();

	// Some buddy related settings ;)
	$context['show_buddies'] = !empty($user_info['buddies']);
	$context['buddy_search'] = isset($_REQUEST['buddies']);

	// If the user has done a search, well - search.
	if (isset($_REQUEST['search']))
	{
		$_REQUEST['search'] = $smcFunc['htmlspecialchars']($_REQUEST['search'], ENT_QUOTES);

		$context['results'] = findMembers(array($_REQUEST['search']), true, $context['buddy_search']);
		$total_results = count($context['results']);

		$context['page_index'] = constructPageIndex($scripturl . '?action=findmember;search=' . $context['last_search'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';input=' . $context['input_box_name'] . ($context['quote_results'] ? ';quote=1' : '') . ($context['buddy_search'] ? ';buddies' : ''), $_REQUEST['start'], $total_results, 7);

		// Determine the navigation context (especially useful for the wireless template).
		$base_url = $scripturl . '?action=findmember;search=' . urlencode($context['last_search']) . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']) . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['links'] = array(
			'first' => $_REQUEST['start'] >= 7 ? $base_url . ';start=0' : '',
			'prev' => $_REQUEST['start'] >= 7 ? $base_url . ';start=' . ($_REQUEST['start'] - 7) : '',
			'next' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . ($_REQUEST['start'] + 7) : '',
			'last' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . (floor(($total_results - 1) / 7) * 7) : '',
			'up' => $scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']),
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / 7 + 1,
			'num_pages' => floor(($total_results - 1) / 7) + 1
		);

		$context['results'] = array_slice($context['results'], $_REQUEST['start'], 7);
	}
	else
		$context['links']['up'] = $scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']);
}

function RequestMembers()
{
	global $user_info, $txt, $smcFunc;

	checkSession('get');

	$_REQUEST['search'] = $smcFunc['htmlspecialchars']($_REQUEST['search']) . '*';
	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search']));
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	if (function_exists('iconv'))
		header('Content-Type: text/plain; charset=UTF-8');

	$request = $smcFunc['db_query']('', '
		SELECT real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:search}' . (isset($_REQUEST['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'buddy_list' => $user_info['buddies'],
			'search' => $_REQUEST['search'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (function_exists('iconv'))
		{
			$utf8 = iconv($txt['lang_character_set'], 'UTF-8', $row['real_name']);
			if ($utf8)
				$row['real_name'] = $utf8;
		}

		$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		if (preg_match('~&#\d+;~', $row['real_name']) != 0)
		{
			$fixchar = create_function('$n', '
				if ($n < 128)
					return chr($n);
				elseif ($n < 2048)
					return chr(192 | $n >> 6) . chr(128 | $n & 63);
				elseif ($n < 65536)
					return chr(224 | $n >> 12) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);
				else
					return chr(240 | $n >> 18) . chr(128 | $n >> 12 & 63) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);');

			$row['real_name'] = preg_replace('~&#(\d+);~e', '$fixchar(\'$1\')', $row['real_name']);
		}

		echo $row['real_name'], "\n";
	}
	$smcFunc['db_free_result']($request);

	obExit(false);
}

// This function generates a random password for a user and emails it to them.
function resetPassword($memID, $username = null)
{
	global $scripturl, $context, $txt, $sourcedir, $modSettings, $smcFunc, $language;

	// Language... and a required file.
	loadLanguage('Login');
	require_once($sourcedir . '/Subs-Post.php');

	// Get some important details.
	$request = $smcFunc['db_query']('', '
		SELECT member_name, email_address, lngfile
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $memID,
		)
	);
	list ($user, $email, $lngfile) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($username !== null)
	{
		$old_user = $user;
		$user = trim($username);
	}

	// Generate a random password.
	$newPassword = substr(preg_replace('/\W/', '', md5(mt_rand())), 0, 10);
	$newPassword_sha1 = sha1(strtolower($user) . $newPassword);

	// Do some checks on the username if needed.
	if ($username !== null)
	{
		validateUsername($memID, $user);

		// Update the database...
		updateMemberData($memID, array('member_name' => $user, 'passwd' => $newPassword_sha1));
	}
	else
		updateMemberData($memID, array('passwd' => $newPassword_sha1));

	call_integration_hook('integrate_reset_pass', array($old_user, $user, $newPassword));

	$replacements = array(
		'USERNAME' => $user,
		'PASSWORD' => $newPassword,
	);

	$emaildata = loadEmailTemplate('change_password', $replacements, empty($lngfile) || empty($modSettings['userLanguage']) ? $language : $lngfile);

	// Send them the email informing them of the change - then we're done!
	sendmail($email, $emaildata['subject'], $emaildata['body'], null, null, false, 0);
}

// Is this a valid username?
function validateUsername($memID, $username)
{
	global $sourcedir, $txt;

	// No name?!  How can you register with no name?
	if ($username == '')
		fatal_lang_error('need_username', false);

	// Only these characters are permitted.
	if (in_array($username, array('_', '|')) || preg_match('~[<>&"\'=\\\\]~', preg_replace('~&#(?:\\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $username)) != 0 || strpos($username, '[code') !== false || strpos($username, '[/code') !== false)
		fatal_lang_error('error_invalid_characters_username', false);

	if (stristr($username, $txt['guest_title']) !== false)
		fatal_lang_error('username_reserved', true, array($txt['guest_title']));

	require_once($sourcedir . '/Subs-Members.php');
	if (isReservedName($username, $memID, false))
		fatal_error('(' . htmlspecialchars($username) . ') ' . $txt['name_in_use'], false);

	return null;
}

// This function simply checks whether a password meets the current forum rules.
function validatePassword($password, $username, $restrict_in = array())
{
	global $modSettings, $smcFunc;

	// Perform basic requirements first.
	if ($smcFunc['strlen']($password) < (empty($modSettings['password_strength']) ? 4 : 8))
		return 'short';

	// Is this enough?
	if (empty($modSettings['password_strength']))
		return null;

	// Otherwise, perform the medium strength test - checking if password appears in the restricted string.
	if (preg_match('~\b' . preg_quote($password, '~') . '\b~', implode(' ', $restrict_in)) != 0)
		return 'restricted_words';
	elseif ($smcFunc['strpos']($password, $username) !== false)
		return 'restricted_words';

	// !!! If pspell is available, use it on the word, and return restricted_words if it doesn't give "bad spelling"?

	// If just medium, we're done.
	if ($modSettings['password_strength'] == 1)
		return null;

	// Otherwise, hard test next, check for numbers and letters, uppercase too.
	$good = preg_match('~(\D\d|\d\D)~', $password) != 0;
	$good &= $smcFunc['strtolower']($password) != $password;

	return $good ? null : 'chars';
}

// Quickly find out what this user can and cannot do.
function rebuildModCache()
{
	global $user_info, $smcFunc;

	// What groups can they moderate?
	$group_query = allowedTo('manage_membergroups') ? '1=1' : '0=1';

	if ($group_query == '0=1')
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}group_moderators
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);
		$groups = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$groups[] = $row['id_group'];
		$smcFunc['db_free_result']($request);

		if (empty($groups))
			$group_query = '0=1';
		else
			$group_query = 'id_group IN (' . implode(',', $groups) . ')';
	}

	// Then, same again, just the boards this time!
	$board_query = allowedTo('moderate_forum') ? '1=1' : '0=1';

	if ($board_query == '0=1')
	{
		$boards = boardsAllowedTo('moderate_board', true);

		if (empty($boards))
			$board_query = '0=1';
		else
			$board_query = 'id_board IN (' . implode(',', $boards) . ')';
	}

	// What boards are they the moderator of?
	$boards_mod = array();
	if (!$user_info['is_guest'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}moderators
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards_mod[] = $row['id_board'];
		$smcFunc['db_free_result']($request);
	}

	$mod_query = empty($boards_mod) ? '0=1' : 'b.id_board IN (' . implode(',', $boards_mod) . ')';

	$_SESSION['mc'] = array(
		'time' => time(),
		// This looks a bit funny but protects against the login redirect.
		'id' => $user_info['id'] && $user_info['name'] ? $user_info['id'] : 0,
		// If you change the format of 'gq' and/or 'bq' make sure to adjust 'can_mod' in Load.php.
		'gq' => $group_query,
		'bq' => $board_query,
		'ap' => boardsAllowedTo('approve_posts'),
		'mb' => $boards_mod,
		'mq' => $mod_query,
	);

	$user_info['mod_cache'] = $_SESSION['mc'];
}

?>