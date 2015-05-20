<?php

/**
 * This file has functions in it to do with authentication, user handling, and the like.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Sets the SMF-style login cookie and session based on the id_member and password passed.
 * - password should be already encrypted with the cookie salt.
 * - logs the user out if id_member is zero.
 * - sets the cookie and session to last the number of seconds specified by cookie_length.
 * - when logging out, if the globalCookies setting is enabled, attempts to clear the subdomain's cookie too.
 *
 * @param int $cookie_length
 * @param int $id The id of the member
 * @param string $password = ''
 */
function setLoginCookie($cookie_length, $id, $password = '')
{
	global $cookiename, $boardurl, $modSettings, $sourcedir;

	$id = (int) $id;

	// If changing state force them to re-address some permission caching.
	$_SESSION['mc']['time'] = 0;

	// The cookie may already exist, and have been set with different options.
	$cookie_state = (empty($modSettings['localCookies']) ? 0 : 1) | (empty($modSettings['globalCookies']) ? 0 : 2);
	if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;i:\d{1,7};i:1;s:(0|128):"([a-fA-F0-9]{128})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
	{
		$array = @unserialize($_COOKIE[$cookiename]);

		// Out with the old, in with the new!
		if (isset($array[3]) && $array[3] != $cookie_state)
		{
			$cookie_url = url_parts($array[3] & 1 > 0, $array[3] & 2 > 0);
			smf_setcookie($cookiename, serialize(array(0, '', 0)), time() - 3600, $cookie_url[1], $cookie_url[0]);
		}
	}

	// Get the data and path to set it on.
	$data = serialize(empty($id) ? array(0, '', 0) : array($id, $password, time() + $cookie_length, $cookie_state));
	$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

	// Set the cookie, $_COOKIE, and session variable.
	smf_setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0]);

	// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
	if (empty($id) && !empty($modSettings['globalCookies']))
		smf_setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], '');

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

			smf_setcookie($cookiename, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0]);
		}

		$boardurl = $temp;
	}

	$_COOKIE[$cookiename] = $data;

	// Make sure the user logs in with a new session ID.
	if (!isset($_SESSION['login_' . $cookiename]) || $_SESSION['login_' . $cookiename] !== $data)
	{
		// We need to meddle with the session.
		require_once($sourcedir . '/Session.php');

		// Backup and remove the old session.
		$oldSessionData = $_SESSION;
		$_SESSION = array();
		session_destroy();

		// Recreate and restore the new session.
		loadSession();
		// @todo should we use session_regenerate_id(true); now that we are 5.1+
		session_regenerate_id();
		$_SESSION = $oldSessionData;

		$_SESSION['login_' . $cookiename] = $data;
	}
}

/**
 * Sets Two Factor Auth cookie
 *
 * @param int $cookie_length
 * @param int $id
 * @param string $secret Should be a salted secret using hash_salt
 */
function setTFACookie($cookie_length, $id, $secret)
{
	global $modSettings, $cookiename, $boardurl;

	$identifier = $cookiename . '_tfa';
	$cookie_state = (empty($modSettings['localCookies']) ? 0 : 1) | (empty($modSettings['globalCookies']) ? 0 : 2);

	// Get the data and path to set it on.
	$data = serialize(empty($id) ? array(0, '', 0) : array($id, $secret, time() + $cookie_length, $cookie_state));
	$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

	// Set the cookie, $_COOKIE, and session variable.
	smf_setcookie($identifier, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0]);

	// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
	if (empty($id) && !empty($modSettings['globalCookies']))
		smf_setcookie($identifier, $data, time() + $cookie_length, $cookie_url[1], '');

	$_COOKIE[$identifier] = $data;
}

/**
 * Get the domain and path for the cookie
 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
 * - uses boardurl to determine these two things.
 *
 * @param bool $local
 * @param bool $global
 * @return array an array to set the cookie on with domain and path in it, in that order
 */
function url_parts($local, $global)
{
	global $boardurl, $modSettings;

	// Parse the URL with PHP to make life easier.
	$parsed_url = parse_url($boardurl);

	// Is local cookies off?
	if (empty($parsed_url['path']) || !$local)
		$parsed_url['path'] = '';

	if (!empty($modSettings['globalCookiesDomain']) && strpos($boardurl, $modSettings['globalCookiesDomain']) !== false)
		$parsed_url['host'] = $modSettings['globalCookiesDomain'];

	// Globalize cookies across domains (filter out IP-addresses)?
	elseif ($global && preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
		$parsed_url['host'] = '.' . $parts[1];

	// We shouldn't use a host at all if both options are off.
	elseif (!$local && !$global)
		$parsed_url['host'] = '';

	// The host also shouldn't be set if there aren't any dots in it.
	elseif (!isset($parsed_url['host']) || strpos($parsed_url['host'], '.') === false)
		$parsed_url['host'] = '';

	return array($parsed_url['host'], $parsed_url['path'] . '/');
}

/**
 * Throws guests out to the login screen when guest access is off.
 * - sets $_SESSION['login_url'] to $_SERVER['REQUEST_URL'].
 * - uses the 'kick_guest' sub template found in Login.template.php.
 */
function KickGuest()
{
	global $txt, $context;

	loadLanguage('Login');
	loadTemplate('Login');
	createToken('login');

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	$context['sub_template'] = 'kick_guest';
	$context['page_title'] = $txt['login'];
}

/**
 * Display a message about the forum being in maintenance mode.
 * - display a login screen with sub template 'maintenance'.
 * - sends a 503 header, so search engines don't bother indexing while we're in maintenance mode.
 */
function InMaintenance()
{
	global $txt, $mtitle, $mmessage, $context, $smcFunc;

	loadLanguage('Login');
	loadTemplate('Login');
	createToken('login');

	// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
	header('HTTP/1.1 503 Service Temporarily Unavailable');

	// Basic template stuff..
	$context['sub_template'] = 'maintenance';
	$context['title'] = $smcFunc['htmlspecialchars']($mtitle);
	$context['description'] = &$mmessage;
	$context['page_title'] = $txt['maintain_mode'];
}

/**
 * Question the verity of the admin by asking for his or her password.
 * - loads Login.template.php and uses the admin_login sub template.
 * - sends data to template so the admin is sent on to the page they
 *   wanted if their password is correct, otherwise they can try again.
 *
 * @param string $type = 'admin'
 */
function adminLogin($type = 'admin')
{
	global $context, $txt, $user_settings, $user_info;

	loadLanguage('Admin');
	loadTemplate('Login');

	// Validate what type of session check this is.
	$types = array();
	call_integration_hook('integrate_validateSession', array(&$types));
	$type = in_array($type, $types) || $type == 'moderate' ? $type : 'admin';

	// They used a wrong password, log it and unset that.
	if (isset($_POST[$type . '_hash_pass']) || isset($_POST[$type . '_pass']))
	{
		$txt['security_wrong'] = sprintf($txt['security_wrong'], isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $txt['unknown'], $_SERVER['HTTP_USER_AGENT'], $user_info['ip']);
		log_error($txt['security_wrong'], 'critical');

		if (isset($_POST[$type . '_hash_pass']))
			unset($_POST[$type . '_hash_pass']);
		if (isset($_POST[$type . '_pass']))
			unset($_POST[$type . '_pass']);

		$context['incorrect_password'] = true;
	}

	createToken('admin-login');

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

	// The type of action.
	$context['sessionCheckType'] = $type;

	obExit();

	// We MUST exit at this point, because otherwise we CANNOT KNOW that the user is privileged.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Used by the adminLogin() function.
 * if 'value' is an array, the function is called recursively.
 *
 * @param string $k key
 * @param string $v value
 * @return string 'hidden' HTML form fields, containing key-value-pairs
 */
function adminLogin_outputPostVars($k, $v)
{
	global $smcFunc;

	if (!is_array($v))
		return '
<input type="hidden" name="' . $smcFunc['htmlspecialchars']($k) . '" value="' . strtr($v, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;')) . '">';
	else
	{
		$ret = '';
		foreach ($v as $k2 => $v2)
			$ret .= adminLogin_outputPostVars($k . '[' . $k2 . ']', $v2);

		return $ret;
	}
}

/**
 * Properly urlencodes a string to be used in a query
 *
 * @global type $scripturl
 * @param type $get
 * @return our query string
 */
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

/**
 * Finds members by email address, username, or real name.
 * - searches for members whose username, display name, or e-mail address match the given pattern of array names.
 * - searches only buddies if buddies_only is set.
 *
 * @param array $names
 * @param bool $use_wildcards = false, accepts wildcards ? and * in the pattern if true
 * @param bool $buddies_only = false,
 * @param int $max = 500 retrieves a maximum of max members, if passed
 * @return array containing information about the matching members
 */
function findMembers($names, $use_wildcards = false, $buddies_only = false, $max = 500)
{
	global $scripturl, $user_info, $smcFunc;

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
	if (($use_wildcards || $maybe_email) && allowedTo('moderate_forum'))
		$email_condition = '
			OR (email_address ' . $comparison . ' \'' . implode( '\') OR (email_address ' . $comparison . ' \'', $names) . '\')';
	else
		$email_condition = '';

	// Get the case of the columns right - but only if we need to as things like MySQL will go slow needlessly otherwise.
	$member_name = $smcFunc['db_case_sensitive'] ? 'LOWER(member_name)' : 'member_name';
	$real_name = $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name';

	// Search by username, display name, and email address.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, email_address
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
			'email' => allowedTo('moderate_forum') ? $row['email_address'] : '',
			'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
		);
	}
	$smcFunc['db_free_result']($request);

	// Return all the results.
	return $results;
}

/**
 * Called by index.php?action=findmember.
 * - is used as a popup for searching members.
 * - uses sub template find_members of the Help template.
 * - also used to add members for PM's sent using wap2/imode protocol.
 */
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

/**
 * Outputs each member name on its own line.
 * - used by javascript to find members matching the request.
 */
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
		WHERE {raw:real_name} LIKE {string:search}' . (isset($_REQUEST['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name',
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
			$row['real_name'] = preg_replace_callback('~&#(\d+);~', 'fixchar__callback', $row['real_name']);

		echo $row['real_name'], "\n";
	}
	$smcFunc['db_free_result']($request);

	obExit(false);
}

/**
 * Generates a random password for a user and emails it to them.
 * - called by Profile.php when changing someone's username.
 * - checks the validity of the new username.
 * - generates and sets a new password for the given user.
 * - mails the new password to the email address of the user.
 * - if username is not set, only a new password is generated and sent.
 *
 * @param int $memID
 * @param string $username = null
 */
function resetPassword($memID, $username = null)
{
	global $sourcedir, $modSettings, $smcFunc, $language;

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
	$newPassword_sha1 = hash_password($user, $newPassword);

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
	sendmail($email, $emaildata['subject'], $emaildata['body'], null, 'chgpass' . $memID, false, 0);
}

/**
 * Checks a username obeys a load of rules
 *
 * @param int $memID
 * @param string $username
 * @param boolean $return_error
 * @param boolean $check_reserved_name
 * @return string Returns null if fine
 */
function validateUsername($memID, $username, $return_error = false, $check_reserved_name = true)
{
	global $sourcedir, $txt, $smcFunc, $user_info;

	$errors = array();

	// Don't use too long a name.
	if ($smcFunc['strlen']($username) > 25)
		$errors[] = array('lang', 'error_long_name');

	// No name?!  How can you register with no name?
	if ($username == '')
		$errors[] = array('lang', 'need_username');

	// Only these characters are permitted.
	if (in_array($username, array('_', '|')) || preg_match('~[<>&"\'=\\\\]~', preg_replace('~&#(?:\\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $username)) != 0 || strpos($username, '[code') !== false || strpos($username, '[/code') !== false)
		$errors[] = array('lang', 'error_invalid_characters_username');

	if (stristr($username, $txt['guest_title']) !== false)
		$errors[] = array('lang', 'username_reserved', 'general', array($txt['guest_title']));

	if ($check_reserved_name)
	{
		require_once($sourcedir . '/Subs-Members.php');
		if (isReservedName($username, $memID, false))
			$errors[] = array('done', '(' . $smcFunc['htmlspecialchars']($username) . ') ' . $txt['name_in_use']);
	}

	if ($return_error)
		return $errors;
	elseif (empty($errors))
		return null;

	loadLanguage('Errors');
	$error = $errors[0];

	$message = $error[0] == 'lang' ? (empty($error[3]) ? $txt[$error[1]] : vsprintf($txt[$error[1]], $error[3])) : $error[1];
	fatal_error($message, empty($error[2]) || $user_info['is_admin'] ? false : $error[2]);
}

/**
 * Checks whether a password meets the current forum rules
 * - called when registering/choosing a password.
 * - checks the password obeys the current forum settings for password strength.
 * - if password checking is enabled, will check that none of the words in restrict_in appear in the password.
 * - returns an error identifier if the password is invalid, or null.
 *
 * @param string $password
 * @param string $username
 * @param array $restrict_in = array()
 * @return string an error identifier if the password is invalid
 */
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

	// If just medium, we're done.
	if ($modSettings['password_strength'] == 1)
		return null;

	// Otherwise, hard test next, check for numbers and letters, uppercase too.
	$good = preg_match('~(\D\d|\d\D)~', $password) != 0;
	$good &= $smcFunc['strtolower']($password) != $password;

	return $good ? null : 'chars';
}

/**
 * Quickly find out what moderation authority this user has
 * - builds the moderator, group and board level querys for the user
 * - stores the information on the current users moderation powers in $user_info['mod_cache'] and $_SESSION['mc']
 */
function rebuildModCache()
{
	global $user_info, $smcFunc;

	// What groups can they moderate?
	$group_query = allowedTo('manage_membergroups') ? '1=1' : '0=1';

	if ($group_query == '0=1' && !$user_info['is_guest'])
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

	if ($board_query == '0=1' && !$user_info['is_guest'])
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

		// Can any of the groups they're in moderate any of the boards?
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}moderator_groups
			WHERE id_group IN({array_int:groups})',
			array(
				'groups' => $user_info['groups'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards_mod[] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		// Just in case we've got duplicates here...
		$boards_mod = array_unique($boards_mod);
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
	call_integration_hook('integrate_mod_cache');

	$user_info['mod_cache'] = $_SESSION['mc'];

	// Might as well clean up some tokens while we are at it.
	cleanTokens();
}

/**
 * The same thing as setcookie but gives support for HTTP-Only cookies in PHP < 5.2
 *
 * @param string $name
 * @param string $value = ''
 * @param int $expire = 0
 * @param string $path = ''
 * @param string $domain = ''
 * @param bool $secure = false
 * @param bool $httponly = true
 */
function smf_setcookie($name, $value = '', $expire = 0, $path = '', $domain = '', $secure = null, $httponly = true)
{
	global $modSettings;

	// In case a customization wants to override the default settings
	if ($httponly === null)
		$httponly = !empty($modSettings['httponlyCookies']);
	if ($secure === null)
		$secure = !empty($modSettings['secureCookies']);

	// Intercept cookie?
	call_integration_hook('integrate_cookie', array($name, $value, $expire, $path, $domain, $secure, $httponly));

	// This function is pointless if we have PHP >= 5.2.
	return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
}

/**
 * Hashes username with password
 *
 * @param string $username
 * @param string $password
 * @param int $cost
 * @return string
 */
function hash_password($username, $password, $cost = null)
{
	global $sourcedir, $smcFunc, $modSettings;
	if (!function_exists('password_hash'))
		require_once($sourcedir . '/Subs-Password.php');

	$cost = empty($cost) ? (empty($modSettings['bcrypt_hash_cost']) ? 10 : $modSettings['bcrypt_hash_cost']) : $cost;

	return password_hash($smcFunc['strtolower']($username) . $password, PASSWORD_BCRYPT, array(
		'cost' => $cost,
	));
}

/**
 * Hashes password with salt, this is solely used for cookies.
 *
 * @param string $password
 * @param string $salt
 * @return string
 */
function hash_salt($password, $salt)
{
	return hash('sha512', $password . $salt);
}

/**
 * Verifies a raw SMF password against the bcrypt'd string
 *
 * @param string $username
 * @param string $password
 * @param string $hash
 * @return bool
 */
function hash_verify_password($username, $password, $hash)
{
	global $sourcedir, $smcFunc;
	if (!function_exists('password_verify'))
		require_once($sourcedir . '/Subs-Password.php');

	return password_verify($smcFunc['strtolower']($username) . $password, $hash);
}

/**
 * Returns the length for current hash
 *
 * @return int
 */
function hash_length()
{
	return 60;
}

/**
 * Benchmarks the server to figure out an appropriate cost factor (minimum 9)
 *
 * @param int $hashTime Time to target, in seconds
 * @return int
 */
function hash_benchmark($hashTime = 0.2)
{
	$cost = 9;
	do {
		$timeStart = microtime(true);
		hash_password('test', 'thisisatestpassword', $cost);
		$timeTaken = microtime(true) - $timeStart;
		$cost++;
	} while ($timeTaken < $hashTime);

	return $cost;
}

?>