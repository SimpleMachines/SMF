<?php

/**
 * This file has functions in it to do with authentication, user handling, and the like.
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

if (!defined('SMF'))
	die('No direct access...');

/**
 * Sets the SMF-style login cookie and session based on the id_member and password passed.
 * - password should be already encrypted with the cookie salt.
 * - logs the user out if id_member is zero.
 * - sets the cookie and session to last the number of seconds specified by cookie_length, or
 *   ends them if cookie_length is less than 0.
 * - when logging out, if the globalCookies setting is enabled, attempts to clear the subdomain's
 *   cookie too.
 *
 * @param int $cookie_length How many seconds the cookie should last. If negative, forces logout.
 * @param int $id The ID of the member to set the cookie for
 * @param string $password The hashed password
 */
function setLoginCookie($cookie_length, $id, $password = '')
{
	$id = (int) $id;

	$expiry_time = ($cookie_length >= 0 ? time() + $cookie_length : 1);

	// If changing state force them to re-address some permission caching.
	$_SESSION['mc']['time'] = 0;

	// Extract our cookie domain and path from Config::$boardurl
	$cookie_url = url_parts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies']));

	// The cookie may already exist, and have been set with different options.
	if (isset($_COOKIE[Config::$cookiename]))
	{
		// First check for 2.1 json-format cookie
		if (preg_match('~^{"0":\d+,"1":"[0-9a-f]*","2":\d+,"3":"[^"]+","4":"[^"]+"~', $_COOKIE[Config::$cookiename]) === 1)
			list(,,, $old_domain, $old_path) = Utils::jsonDecode($_COOKIE[Config::$cookiename], true);

		// Legacy format (for recent 2.0 --> 2.1 upgrades)
		elseif (preg_match('~^a:[34]:\{i:0;i:\d+;i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d+;(i:3;i:\d;)?~', $_COOKIE[Config::$cookiename]) === 1)
		{
			list(,,, $old_state) = safe_unserialize($_COOKIE[Config::$cookiename]);

			$cookie_state = (empty(Config::$modSettings['localCookies']) ? 0 : 1) | (empty(Config::$modSettings['globalCookies']) ? 0 : 2);

			// Maybe we need to temporarily pretend to be using local cookies
			if ($cookie_state == 0 && $old_state == 1)
				list($old_domain, $old_path) = url_parts(true, false);
			else
				list($old_domain, $old_path) = url_parts($old_state & 1 > 0, $old_state & 2 > 0);
		}

		// Out with the old, in with the new!
		if (isset($old_domain) && $old_domain != $cookie_url[0] || isset($old_path) && $old_path != $cookie_url[1])
			smf_setcookie(Config::$cookiename, Utils::jsonEncode(array(0, '', 0, $old_domain, $old_path), JSON_FORCE_OBJECT), 1, $old_path, $old_domain);
	}

	// Get the data and path to set it on.
	$data = empty($id) ? array(0, '', 0, $cookie_url[0], $cookie_url[1]) : array($id, $password, $expiry_time, $cookie_url[0], $cookie_url[1]);

	// Allow mods to add custom info to the cookie
	$custom_data = array();
	call_integration_hook('integrate_cookie_data', array($data, &$custom_data));

	$data = Utils::jsonEncode(array_merge($data, $custom_data), JSON_FORCE_OBJECT);

	// Set the cookie, $_COOKIE, and session variable.
	smf_setcookie(Config::$cookiename, $data, $expiry_time, $cookie_url[1], $cookie_url[0]);

	// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
	if (empty($id) && !empty(Config::$modSettings['globalCookies']))
		smf_setcookie(Config::$cookiename, $data, $expiry_time, $cookie_url[1], '');

	// Any alias URLs?  This is mainly for use with frames, etc.
	if (!empty(Config::$modSettings['forum_alias_urls']))
	{
		$aliases = explode(',', Config::$modSettings['forum_alias_urls']);

		$temp = Config::$boardurl;
		foreach ($aliases as $alias)
		{
			// Fake the Config::$boardurl so we can set a different cookie.
			$alias = strtr(trim($alias), array('http://' => '', 'https://' => ''));
			Config::$boardurl = 'http://' . $alias;

			$cookie_url = url_parts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies']));

			if ($cookie_url[0] == '')
				$cookie_url[0] = strtok($alias, '/');

			$alias_data = Utils::jsonDecode($data, true);
			$alias_data[3] = $cookie_url[0];
			$alias_data[4] = $cookie_url[1];
			$alias_data = Utils::jsonEncode($alias_data, JSON_FORCE_OBJECT);

			smf_setcookie(Config::$cookiename, $alias_data, $expiry_time, $cookie_url[1], $cookie_url[0]);
		}

		Config::$boardurl = $temp;
	}

	$_COOKIE[Config::$cookiename] = $data;

	// Make sure the user logs in with a new session ID.
	if (!isset($_SESSION['login_' . Config::$cookiename]) || $_SESSION['login_' . Config::$cookiename] !== $data)
	{
		// We need to meddle with the session.
		require_once(Config::$sourcedir . '/Session.php');

		// Backup and remove the old session.
		$oldSessionData = $_SESSION;
		$_SESSION = array();
		session_destroy();

		// Recreate and restore the new session.
		loadSession();
		// @todo should we use session_regenerate_id(true); now that we are 5.1+
		session_regenerate_id();
		$_SESSION = $oldSessionData;

		$_SESSION['login_' . Config::$cookiename] = $data;
	}
}

/**
 * Sets Two Factor Auth cookie
 *
 * @param int $cookie_length How long the cookie should last, in seconds
 * @param int $id The ID of the member
 * @param string $secret Should be a salted secret using hash_salt
 */
function setTFACookie($cookie_length, $id, $secret)
{
	$expiry_time = ($cookie_length >= 0 ? time() + $cookie_length : 1);

	$identifier = Config::$cookiename . '_tfa';
	$cookie_url = url_parts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies']));

	// Get the data and path to set it on.
	$data = Utils::jsonEncode(empty($id) ? array(0, '', 0, $cookie_url[0], $cookie_url[1], false) : array($id, $secret, $expiry_time, $cookie_url[0], $cookie_url[1]), JSON_FORCE_OBJECT);

	// Set the cookie, $_COOKIE, and session variable.
	smf_setcookie($identifier, $data, $expiry_time, $cookie_url[1], $cookie_url[0]);

	// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
	if (empty($id) && !empty(Config::$modSettings['globalCookies']))
		smf_setcookie($identifier, $data, $expiry_time, $cookie_url[1], '');

	$_COOKIE[$identifier] = $data;
}

/**
 * Get the domain and path for the cookie
 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
 * - uses boardurl to determine these two things.
 *
 * @param bool $local Whether we want local cookies
 * @param bool $global Whether we want global cookies
 * @return array An array to set the cookie on with domain and path in it, in that order
 */
function url_parts($local, $global)
{
	// Parse the URL with PHP to make life easier.
	$parsed_url = parse_iri(Config::$boardurl);

	// Is local cookies off?
	if (empty($parsed_url['path']) || !$local)
		$parsed_url['path'] = '';

	if (!empty(Config::$modSettings['globalCookiesDomain']) && strpos(Config::$boardurl, Config::$modSettings['globalCookiesDomain']) !== false)
		$parsed_url['host'] = Config::$modSettings['globalCookiesDomain'];

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
	loadTheme();
	Lang::load('Login');
	loadTemplate('Login');
	createToken('login');

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	Utils::$context['sub_template'] = 'kick_guest';
	Utils::$context['page_title'] = Lang::$txt['login'];
}

/**
 * Display a message about the forum being in maintenance mode.
 * - display a login screen with sub template 'maintenance'.
 * - sends a 503 header, so search engines don't bother indexing while we're in maintenance mode.
 */
function InMaintenance()
{
	Lang::load('Login');
	loadTemplate('Login');
	createToken('login');

	// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
	send_http_status(503, 'Service Temporarily Unavailable');

	// Basic template stuff..
	Utils::$context['sub_template'] = 'maintenance';
	Utils::$context['title'] = Utils::htmlspecialchars(Config::$mtitle);
	Utils::$context['description'] = &Config::$mmessage;
	Utils::$context['page_title'] = Lang::$txt['maintain_mode'];
}

/**
 * Question the verity of the admin by asking for his or her password.
 * - loads Login.template.php and uses the admin_login sub template.
 * - sends data to template so the admin is sent on to the page they
 *   wanted if their password is correct, otherwise they can try again.
 *
 * @param string $type What login type is this - can be 'admin' or 'moderate'
 */
function adminLogin($type = 'admin')
{
	Lang::load('Admin');
	loadTemplate('Login');

	// Validate what type of session check this is.
	$types = array();
	call_integration_hook('integrate_validateSession', array(&$types));
	$type = in_array($type, $types) || $type == 'moderate' ? $type : 'admin';

	// They used a wrong password, log it and unset that.
	if (isset($_POST[$type . '_hash_pass']) || isset($_POST[$type . '_pass']))
	{
		Lang::$txt['security_wrong'] = sprintf(Lang::$txt['security_wrong'], isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : Lang::$txt['unknown'], $_SERVER['HTTP_USER_AGENT'], User::$me->ip);
		log_error(Lang::$txt['security_wrong'], 'critical');

		if (isset($_POST[$type . '_hash_pass']))
			unset($_POST[$type . '_hash_pass']);
		if (isset($_POST[$type . '_pass']))
			unset($_POST[$type . '_pass']);

		Utils::$context['incorrect_password'] = true;
	}

	createToken('admin-login');

	// Figure out the get data and post data.
	Utils::$context['get_data'] = '?' . construct_query_string($_GET);
	Utils::$context['post_data'] = '';

	// Now go through $_POST.  Make sure the session hash is sent.
	$_POST[Utils::$context['session_var']] = Utils::$context['session_id'];
	foreach ($_POST as $k => $v)
		Utils::$context['post_data'] .= adminLogin_outputPostVars($k, $v);

	// Now we'll use the admin_login sub template of the Login template.
	Utils::$context['sub_template'] = 'admin_login';

	// And title the page something like "Login".
	if (!isset(Utils::$context['page_title']))
		Utils::$context['page_title'] = Lang::$txt['login'];

	// The type of action.
	Utils::$context['sessionCheckType'] = $type;

	obExit();

	// We MUST exit at this point, because otherwise we CANNOT KNOW that the user is privileged.
	trigger_error('No direct access...', E_USER_ERROR);
}

/**
 * Used by the adminLogin() function.
 * if 'value' is an array, the function is called recursively.
 *
 * @param string $k The keys
 * @param string $v The values
 * @return string 'hidden' HTML form fields, containing key-value-pairs
 */
function adminLogin_outputPostVars($k, $v)
{
	if (!is_array($v))
		return '
<input type="hidden" name="' . Utils::htmlspecialchars($k) . '" value="' . strtr($v, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;')) . '">';
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
 * @param string $get
 * @return string Our query string
 */
function construct_query_string($get)
{
	$query_string = '';

	// Awww, darn. The Config::$scripturl contains GET stuff!
	$q = strpos(Config::$scripturl, '?');
	if ($q !== false)
	{
		parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(substr(Config::$scripturl, $q + 1), ';', '&')), $temp);

		foreach ($get as $k => $v)
		{
			// Only if it's not already in the Config::$scripturl!
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
 * @param array $names The names of members to search for
 * @param bool $use_wildcards Whether to use wildcards. Accepts wildcards ? and * in the pattern if true
 * @param bool $buddies_only Whether to only search for the user's buddies
 * @param int $max The maximum number of results
 * @return array An array containing information about the matching members
 */
function findMembers($names, $use_wildcards = false, $buddies_only = false, $max = 500)
{

	// If it's not already an array, make it one.
	if (!is_array($names))
		$names = explode(',', $names);

	$maybe_email = false;
	$names_list = array();
	foreach (array_values($names) as $i => $name)
	{
		// Trim, and fix wildcards for each name.
		$names[$i] = trim(Utils::strtolower($name));

		$maybe_email |= strpos($name, '@') !== false;

		// Make it so standard wildcards will work. (* and ?)
		if ($use_wildcards)
			$names[$i] = strtr($names[$i], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '\'' => '&#039;'));
		else
			$names[$i] = strtr($names[$i], array('\'' => '&#039;'));

		$names_list[] = '{string:lookup_name_' . $i . '}';
		$where_params['lookup_name_' . $i] = $names[$i];
	}

	// What are we using to compare?
	$comparison = $use_wildcards ? 'LIKE' : '=';

	// Nothing found yet.
	$results = array();

	// This ensures you can't search someones email address if you can't see it.
	if (($use_wildcards || $maybe_email) && allowedTo('moderate_forum'))
		$email_condition = '
			OR (email_address ' . $comparison . ' \'' . implode('\') OR (email_address ' . $comparison . ' \'', $names) . '\')';
	else
		$email_condition = '';

	// Get the case of the columns right - but only if we need to as things like MySQL will go slow needlessly otherwise.
	$member_name = Db::$db->case_sensitive ? 'LOWER(member_name)' : 'member_name';
	$real_name = Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name';

	// Searches.
	$member_name_search = $member_name . ' ' . $comparison . ' ' . implode(' OR ' . $member_name . ' ' . $comparison . ' ', $names_list);
	$real_name_search = $real_name . ' ' . $comparison . ' ' . implode(' OR ' . $real_name . ' ' . $comparison . ' ', $names_list);

	// Search by username, display name, and email address.
	$request = Db::$db->query('', '
		SELECT id_member, member_name, real_name, email_address
		FROM {db_prefix}members
		WHERE (' . $member_name_search . '
			OR ' . $real_name_search . ' ' . $email_condition . ')
			' . ($buddies_only ? 'AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT {int:limit}',
		array_merge($where_params, array(
			'buddy_list' => User::$me->buddies,
			'limit' => $max,
		))
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		$results[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'username' => $row['member_name'],
			'email' => allowedTo('moderate_forum') ? $row['email_address'] : '',
			'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
		);
	}
	Db::$db->free_result($request);

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

	checkSession('get');

	// Why is this in the Help template, you ask?  Well, erm... it helps you.  Does that work?
	loadTemplate('Help');

	Utils::$context['template_layers'] = array();
	Utils::$context['sub_template'] = 'find_members';

	if (isset($_REQUEST['search']))
		Utils::$context['last_search'] = Utils::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);
	else
		$_REQUEST['start'] = 0;

	// Allow the user to pass the input to be added to to the box.
	Utils::$context['input_box_name'] = isset($_REQUEST['input']) && preg_match('~^[\w-]+$~', $_REQUEST['input']) === 1 ? $_REQUEST['input'] : 'to';

	// Take the delimiter over GET in case it's \n or something.
	Utils::$context['delimiter'] = isset($_REQUEST['delim']) ? ($_REQUEST['delim'] == 'LB' ? "\n" : $_REQUEST['delim']) : ', ';
	Utils::$context['quote_results'] = !empty($_REQUEST['quote']);

	// List all the results.
	Utils::$context['results'] = array();

	// Some buddy related settings ;)
	Utils::$context['show_buddies'] = !empty(User::$me->buddies);
	Utils::$context['buddy_search'] = isset($_REQUEST['buddies']);

	// If the user has done a search, well - search.
	if (isset($_REQUEST['search']))
	{
		$_REQUEST['search'] = Utils::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);

		Utils::$context['results'] = findMembers(array($_REQUEST['search']), true, Utils::$context['buddy_search']);
		$total_results = count(Utils::$context['results']);

		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=findmember;search=' . Utils::$context['last_search'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';input=' . Utils::$context['input_box_name'] . (Utils::$context['quote_results'] ? ';quote=1' : '') . (Utils::$context['buddy_search'] ? ';buddies' : ''), $_REQUEST['start'], $total_results, 7);

		// Determine the navigation context.
		$base_url = Config::$scripturl . '?action=findmember;search=' . urlencode(Utils::$context['last_search']) . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		Utils::$context['links'] = array(
			'first' => $_REQUEST['start'] >= 7 ? $base_url . ';start=0' : '',
			'prev' => $_REQUEST['start'] >= 7 ? $base_url . ';start=' . ($_REQUEST['start'] - 7) : '',
			'next' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . ($_REQUEST['start'] + 7) : '',
			'last' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . (floor(($total_results - 1) / 7) * 7) : '',
			'up' => Config::$scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']),
		);
		Utils::$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / 7 + 1,
			'num_pages' => floor(($total_results - 1) / 7) + 1
		);

		Utils::$context['results'] = array_slice(Utils::$context['results'], $_REQUEST['start'], 7);
	}
	else
		Utils::$context['links']['up'] = Config::$scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']);
}

/**
 * Outputs each member name on its own line.
 * - used by javascript to find members matching the request.
 */
function RequestMembers()
{
	checkSession('get');

	$_REQUEST['search'] = Utils::htmlspecialchars($_REQUEST['search']) . '*';
	$_REQUEST['search'] = trim(Utils::strtolower($_REQUEST['search']));
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	if (function_exists('iconv'))
		header('content-type: text/plain; charset=UTF-8');

	$request = Db::$db->query('', '
		SELECT real_name
		FROM {db_prefix}members
		WHERE {raw:real_name} LIKE {string:search}' . (isset($_REQUEST['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . (Utils::entityStrlen($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'real_name' => Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name',
			'buddy_list' => User::$me->buddies,
			'search' => $_REQUEST['search'],
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		if (function_exists('iconv'))
		{
			$utf8 = iconv(Lang::$txt['lang_character_set'], 'UTF-8', $row['real_name']);
			if ($utf8)
				$row['real_name'] = $utf8;
		}

		$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		$row['real_name'] = Utils::entityDecode($row['real_name'], true);

		echo $row['real_name'], "\n";
	}
	Db::$db->free_result($request);

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
 * @param int $memID The ID of the member
 * @param string $username The new username. If set, also checks the validity of the username
 */
function resetPassword($memID, $username = null)
{
	// Language... and a required file.
	Lang::load('Login');
	require_once(Config::$sourcedir . '/Subs-Post.php');

	// Get some important details.
	$request = Db::$db->query('', '
		SELECT member_name, email_address, lngfile
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $memID,
		)
	);
	list ($user, $email, $lngfile) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if ($username !== null)
	{
		$old_user = $user;
		$user = trim($username);
	}

	// Generate a random password.
	$newPassword = substr(preg_replace('/\W/', '', md5(Utils::randomInt())), 0, 10);
	$newPassword_sha1 = hash_password($user, $newPassword);

	// Do some checks on the username if needed.
	if ($username !== null)
	{
		validateUsername($memID, $user);

		// Update the database...
		User::updateMemberData($memID, array('member_name' => $user, 'passwd' => $newPassword_sha1));
	}
	else
		User::updateMemberData($memID, array('passwd' => $newPassword_sha1));

	call_integration_hook('integrate_reset_pass', array($old_user, $user, $newPassword));

	$replacements = array(
		'USERNAME' => $user,
		'PASSWORD' => $newPassword,
	);

	$emaildata = loadEmailTemplate('change_password', $replacements, empty($lngfile) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $lngfile);

	// Send them the email informing them of the change - then we're done!
	sendmail($email, $emaildata['subject'], $emaildata['body'], null, 'chgpass' . $memID, $emaildata['is_html'], 0);
}

/**
 * Checks a username obeys a load of rules
 *
 * @param int $memID The ID of the member
 * @param string $username The username to validate
 * @param boolean $return_error Whether to return errors
 * @param boolean $check_reserved_name Whether to check this against the list of reserved names
 * @return array|null Null if there are no errors, otherwise an array of errors if return_error is true
 */
function validateUsername($memID, $username, $return_error = false, $check_reserved_name = true)
{
	$errors = array();

	// Don't use too long a name.
	if (Utils::entityStrlen($username) > 25)
		$errors[] = array('lang', 'error_long_name');

	// No name?!  How can you register with no name?
	if ($username == '')
		$errors[] = array('lang', 'need_username');

	// Only these characters are permitted.
	if (in_array($username, array('_', '|')) || preg_match('~[<>&"\'=\\\\]~', preg_replace('~&#(?:\\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $username)) != 0 || strpos($username, '[code') !== false || strpos($username, '[/code') !== false)
		$errors[] = array('lang', 'error_invalid_characters_username');

	if (stristr($username, Lang::$txt['guest_title']) !== false)
		$errors[] = array('lang', 'username_reserved', 'general', array(Lang::$txt['guest_title']));

	if ($check_reserved_name)
	{
		if (User::isReservedName($username, $memID, false))
			$errors[] = array('done', '(' . Utils::htmlspecialchars($username) . ') ' . Lang::$txt['name_in_use']);
	}

	// Maybe a mod wants to perform more checks?
	call_integration_hook('integrate_validate_username', array($username, &$errors));

	if ($return_error)
		return $errors;
	elseif (empty($errors))
		return null;

	Lang::load('Errors');
	$error = $errors[0];

	$message = $error[0] == 'lang' ? (empty($error[3]) ? Lang::$txt[$error[1]] : vsprintf(Lang::$txt[$error[1]], (array) $error[3])) : $error[1];
	fatal_error($message, empty($error[2]) || User::$me->is_admin ? false : $error[2]);
}

/**
 * Checks whether a password meets the current forum rules
 * - called when registering/choosing a password.
 * - checks the password obeys the current forum settings for password strength.
 * - if password checking is enabled, will check that none of the words in restrict_in appear in the password.
 * - returns an error identifier if the password is invalid, or null.
 *
 * @param string $password The desired password
 * @param string $username The username
 * @param array $restrict_in An array of restricted strings that cannot be part of the password (email address, username, etc.)
 * @return null|string Null if valid or a string indicating what the problem was
 */
function validatePassword($password, $username, $restrict_in = array())
{
	// Perform basic requirements first.
	if (Utils::entityStrlen($password) < (empty(Config::$modSettings['password_strength']) ? 4 : 8))
		return 'short';

	// Maybe we need some more fancy password checks.
	$pass_error = '';
	call_integration_hook('integrate_validatePassword', array($password, $username, $restrict_in, &$pass_error));
	if (!empty($pass_error))
		return $pass_error;

	// Is this enough?
	if (empty(Config::$modSettings['password_strength']))
		return null;

	// Otherwise, perform the medium strength test - checking if password appears in the restricted string.
	if (preg_match('~\b' . preg_quote($password, '~') . '\b~', implode(' ', $restrict_in)) != 0)
		return 'restricted_words';
	elseif (Utils::entityStrpos($password, $username) !== false)
		return 'restricted_words';

	// If just medium, we're done.
	if (Config::$modSettings['password_strength'] == 1)
		return null;

	// Otherwise, hard test next, check for numbers and letters, uppercase too.
	$good = preg_match('~(\D\d|\d\D)~', $password) != 0;
	$good &= Utils::strtolower($password) != $password;

	return $good ? null : 'chars';
}

/**
 * Quickly find out what moderation authority this user has
 * - builds the moderator, group and board level querys for the user
 * - stores the information on the current users moderation powers in User::$me->mod_cache and $_SESSION['mc']
 */
function rebuildModCache()
{
	// What groups can they moderate?
	$group_query = allowedTo('manage_membergroups') ? '1=1' : '0=1';

	if ($group_query == '0=1' && !User::$me->is_guest)
	{
		$request = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}group_moderators
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => User::$me->id,
			)
		);
		$groups = array();
		while ($row = Db::$db->fetch_assoc($request))
			$groups[] = $row['id_group'];
		Db::$db->free_result($request);

		if (empty($groups))
			$group_query = '0=1';
		else
			$group_query = 'id_group IN (' . implode(',', $groups) . ')';
	}

	// Then, same again, just the boards this time!
	$board_query = allowedTo('moderate_forum') ? '1=1' : '0=1';

	if ($board_query == '0=1' && !User::$me->is_guest)
	{
		$boards = boardsAllowedTo('moderate_board', true);

		if (empty($boards))
			$board_query = '0=1';
		else
			$board_query = 'id_board IN (' . implode(',', $boards) . ')';
	}

	// What boards are they the moderator of?
	$boards_mod = array();
	if (!User::$me->is_guest)
	{
		$request = Db::$db->query('', '
			SELECT id_board
			FROM {db_prefix}moderators
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => User::$me->id,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$boards_mod[] = $row['id_board'];
		Db::$db->free_result($request);

		// Can any of the groups they're in moderate any of the boards?
		$request = Db::$db->query('', '
			SELECT id_board
			FROM {db_prefix}moderator_groups
			WHERE id_group IN({array_int:groups})',
			array(
				'groups' => User::$me->groups,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$boards_mod[] = $row['id_board'];
		Db::$db->free_result($request);

		// Just in case we've got duplicates here...
		$boards_mod = array_unique($boards_mod);
	}

	$mod_query = empty($boards_mod) ? '0=1' : 'b.id_board IN (' . implode(',', $boards_mod) . ')';

	$_SESSION['mc'] = array(
		'time' => time(),
		// This looks a bit funny but protects against the login redirect.
		'id' => User::$me->id && User::$me->name ? User::$me->id : 0,
		// If you change the format of 'gq' and/or 'bq' make sure to adjust 'can_mod' in SMF\User.
		'gq' => $group_query,
		'bq' => $board_query,
		'ap' => boardsAllowedTo('approve_posts'),
		'mb' => $boards_mod,
		'mq' => $mod_query,
	);
	call_integration_hook('integrate_mod_cache');

	User::$me->mod_cache = $_SESSION['mc'];

	// Might as well clean up some tokens while we are at it.
	cleanTokens();
}

/**
 * A wrapper for setcookie that gives integration hook access to it
 *
 * @param string $name
 * @param string $value = ''
 * @param int $expire = 0
 * @param string $path = ''
 * @param string $domain = ''
 * @param bool $secure = false
 * @param bool $httponly = true
 * @param string $samesite = lax
 */
function smf_setcookie($name, $value = '', $expire = 0, $path = '', $domain = '', $secure = null, $httponly = true, $samesite = null)
{
	// In case a customization wants to override the default settings
	if ($httponly === null)
		$httponly = !empty(Config::$modSettings['httponlyCookies']);
	if ($secure === null)
		$secure = !empty(Config::$modSettings['secureCookies']);
	if ($samesite === null)
		$samesite = !empty(Config::$modSettings['samesiteCookies']) ? Config::$modSettings['samesiteCookies'] : 'lax';

	// Intercept cookie?
	call_integration_hook('integrate_cookie', array($name, $value, $expire, $path, $domain, $secure, $httponly, $samesite));

	if(PHP_VERSION_ID < 70300)
		return setcookie($name, $value, $expire, $path . ';samesite=' . $samesite, $domain, $secure, $httponly);
	else
		return setcookie($name, $value, array(
			'expires' 	=> $expire,
			'path'		=> $path,
			'domain' 	=> $domain,
			'secure'	=> $secure,
			'httponly'	=> $httponly,    
			'samesite'	=> $samesite
		));
}

/**
 * Hashes username with password
 *
 * @param string $username The username
 * @param string $password The unhashed password
 * @param int $cost The cost
 * @return string The hashed password
 */
function hash_password($username, $password, $cost = null)
{
	$cost = empty($cost) ? (empty(Config::$modSettings['bcrypt_hash_cost']) ? 10 : Config::$modSettings['bcrypt_hash_cost']) : $cost;

	return password_hash(Utils::strtolower($username) . $password, PASSWORD_BCRYPT, array(
		'cost' => $cost,
	));
}

/**
 * Hashes password with salt and authentication secret. This is solely used for cookies.
 *
 * @param string $password The password
 * @param string $salt The salt
 * @return string The hashed password
 */
function hash_salt($password, $salt)
{
	// Append the salt to get a user-specific authentication secret.
	$secret_key = Config::getAuthSecret() . $salt;

	// Now use that to generate an HMAC of the password.
	return hash_hmac('sha512', $password, $secret_key);
}

/**
 * Verifies a raw SMF password against the bcrypt'd string
 *
 * @param string $username The username
 * @param string $password The password
 * @param string $hash The hashed string
 * @return bool Whether the hashed password matches the string
 */
function hash_verify_password($username, $password, $hash)
{
	return password_verify(Utils::strtolower($username) . $password, $hash);
}

/**
 * Returns the length for current hash
 *
 * @return int The length for the current hash
 */
function hash_length()
{
	return 60;
}

/**
 * Benchmarks the server to figure out an appropriate cost factor (minimum 9)
 *
 * @param float $hashTime Time to target, in seconds
 * @return int The cost
 */
function hash_benchmark($hashTime = 0.2)
{
	$cost = 9;
	do
	{
		$timeStart = microtime(true);
		hash_password('test', 'thisisatestpassword', $cost);
		$timeTaken = microtime(true) - $timeStart;
		$cost++;
	}
	while ($timeTaken < $hashTime);

	return $cost;
}

// Based on code by "examplehash at user dot com".
// https://www.php.net/manual/en/function.hash-equals.php#125034
if (!function_exists('hash_equals'))
{
	/**
	 * A compatibility function for when PHP's "hash_equals" function isn't available
	 * @param string $known_string A known hash
	 * @param string $user_string The hash of the user string
	 * @return bool Whether or not the two are equal
	 */
	function hash_equals($known_string, $user_string)
	{
		$known_string = (string) $known_string;
		$user_string = (string) $user_string;

		$sx = 0;
		$sy = strlen($known_string);
		$uy = strlen($user_string);
		$result = $sy - $uy;
		for ($ux = 0; $ux < $uy; $ux++)
		{
			$result |= ord($user_string[$ux]) ^ ord($known_string[$sx]);
			$sx = ($sx + 1) % $sy;
		}

		return !$result;
	}
}

?>