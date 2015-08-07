<?php

/**
 * This file has the very important job of ensuring forum security.
 * This task includes banning and permissions, namely.
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
 * Check if the user is who he/she says he is
 * Makes sure the user is who they claim to be by requiring a password to be typed in every hour.
 * Is turned on and off by the securityDisable setting.
 * Uses the adminLogin() function of Subs-Auth.php if they need to login, which saves all request (post and get) data.
 *
 * @param string $type = admin
 */
function validateSession($type = 'admin')
{
	global $modSettings, $sourcedir, $user_info, $user_settings;

	// We don't care if the option is off, because Guests should NEVER get past here.
	is_not_guest();

	// Validate what type of session check this is.
	$types = array();
	call_integration_hook('integrate_validateSession', array(&$types));
	$type = in_array($type, $types) || $type == 'moderate' ? $type : 'admin';

	// If we're using XML give an additional ten minutes grace as an admin can't log on in XML mode.
	$refreshTime = isset($_GET['xml']) ? 4200 : 3600;

	// Is the security option off?
	if (!empty($modSettings['securityDisable' . ($type != 'admin' ? '_' . $type : '')]))
		return;

	// Or are they already logged in?, Moderator or admin session is need for this area
	if ((!empty($_SESSION[$type . '_time']) && $_SESSION[$type . '_time'] + $refreshTime >= time()) || (!empty($_SESSION['admin_time']) && $_SESSION['admin_time'] + $refreshTime >= time()))
		return;

	require_once($sourcedir . '/Subs-Auth.php');

	// Posting the password... check it.
	if (isset($_POST[$type. '_pass']))
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty($modSettings['force_ssl']) && empty($maintenance) && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on'))
			fatal_lang_error('login_ssl_required');

		checkSession();

		$good_password = in_array(true, call_integration_hook('integrate_verify_password', array($user_info['username'], $_POST[$type . '_pass'], false)), true);

		// Password correct?
		if ($good_password || hash_verify_password($user_info['username'], $_POST[$type . '_pass'], $user_info['passwd']))
		{
			$_SESSION[$type . '_time'] = time();
			unset($_SESSION['request_referer']);
			return;
		}
	}

	// Better be sure to remember the real referer
	if (empty($_SESSION['request_referer']))
		$_SESSION['request_referer'] = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();
	elseif (empty($_POST))
		unset($_SESSION['request_referer']);

	// Need to type in a password for that, man.
	if (!isset($_GET['xml']))
		adminLogin($type);
	else
		return 'session_verify_fail';
}

/**
 * Require a user who is logged in. (not a guest.)
 * Checks if the user is currently a guest, and if so asks them to login with a message telling them why.
 * Message is what to tell them when asking them to login.
 *
 * @param string $message = ''
 */
function is_not_guest($message = '')
{
	global $user_info, $txt, $context, $scripturl, $modSettings;

	// Luckily, this person isn't a guest.
	if (!$user_info['is_guest'])
		return;

	// Log what they were trying to do didn't work)
	if (!empty($modSettings['who_enabled']))
		$_GET['error'] = 'guest_login';
	writeLog(true);

	// Just die.
	if (isset($_REQUEST['xml']))
		obExit(false);

	// Attempt to detect if they came from dlattach.
	if (!WIRELESS && SMF != 'SSI' && empty($context['theme_loaded']))
		loadTheme();

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	// Load the Login template and language file.
	loadLanguage('Login');

	// Are we in wireless mode?
	if (WIRELESS)
	{
		$context['login_error'] = $message ? $message : $txt['only_members_can_access'];
		$context['sub_template'] = WIRELESS_PROTOCOL . '_login';
	}
	// Apparently we're not in a position to handle this now. Let's go to a safer location for now.
	elseif (empty($context['template_layers']))
	{
		$_SESSION['login_url'] = $scripturl . '?' . $_SERVER['QUERY_STRING'];
		redirectexit('action=login');
	}
	else
	{
		loadTemplate('Login');
		$context['sub_template'] = 'kick_guest';
		$context['robot_no_index'] = true;
	}

	// Use the kick_guest sub template...
	$context['kick_message'] = $message;
	$context['page_title'] = $txt['login'];

	obExit();

	// We should never get to this point, but if we did we wouldn't know the user isn't a guest.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Do banning related stuff.  (ie. disallow access....)
 * Checks if the user is banned, and if so dies with an error.
 * Caches this information for optimization purposes.
 * Forces a recheck if force_check is true.
 *
 * @param bool $forceCheck = false
 */
function is_not_banned($forceCheck = false)
{
	global $txt, $modSettings, $context, $user_info;
	global $sourcedir, $cookiename, $user_settings, $smcFunc;

	// You cannot be banned if you are an admin - doesn't help if you log out.
	if ($user_info['is_admin'])
		return;

	// Only check the ban every so often. (to reduce load.)
	if ($forceCheck || !isset($_SESSION['ban']) || empty($modSettings['banLastUpdated']) || ($_SESSION['ban']['last_checked'] < $modSettings['banLastUpdated']) || $_SESSION['ban']['id_member'] != $user_info['id'] || $_SESSION['ban']['ip'] != $user_info['ip'] || $_SESSION['ban']['ip2'] != $user_info['ip2'] || (isset($user_info['email'], $_SESSION['ban']['email']) && $_SESSION['ban']['email'] != $user_info['email']))
	{
		// Innocent until proven guilty.  (but we know you are! :P)
		$_SESSION['ban'] = array(
			'last_checked' => time(),
			'id_member' => $user_info['id'],
			'ip' => $user_info['ip'],
			'ip2' => $user_info['ip2'],
			'email' => $user_info['email'],
		);

		$ban_query = array();
		$ban_query_vars = array('current_time' => time());
		$flag_is_activated = false;

		// Check both IP addresses.
		foreach (array('ip', 'ip2') as $ip_number)
		{
			if ($ip_number == 'ip2' && $user_info['ip2'] == $user_info['ip'])
				continue;
			$ban_query[] = constructBanQueryIP($user_info[$ip_number]);
			// IP was valid, maybe there's also a hostname...
			if (empty($modSettings['disableHostnameLookup']) && $user_info[$ip_number] != 'unknown')
			{
				$hostname = host_from_ip($user_info[$ip_number]);
				if (strlen($hostname) > 0)
				{
					$ban_query[] = '({string:hostname} LIKE bi.hostname)';
					$ban_query_vars['hostname'] = $hostname;
				}
			}
		}

		// Is their email address banned?
		if (strlen($user_info['email']) != 0)
		{
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = $user_info['email'];
		}

		// How about this user?
		if (!$user_info['is_guest'] && !empty($user_info['id']))
		{
			$ban_query[] = 'bi.id_member = {int:id_member}';
			$ban_query_vars['id_member'] = $user_info['id'];
		}

		// Check the ban, if there's information.
		if (!empty($ban_query))
		{
			$restrictions = array(
				'cannot_access',
				'cannot_login',
				'cannot_post',
				'cannot_register',
			);
			$request = $smcFunc['db_query']('', '
				SELECT bi.id_ban, bi.email_address, bi.id_member, bg.cannot_access, bg.cannot_register,
					bg.cannot_post, bg.cannot_login, bg.reason, IFNULL(bg.expire_time, 0) AS expire_time
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
				WHERE
					(' . implode(' OR ', $ban_query) . ')',
				$ban_query_vars
			);
			// Store every type of ban that applies to you in your session.
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				foreach ($restrictions as $restriction)
					if (!empty($row[$restriction]))
					{
						$_SESSION['ban'][$restriction]['reason'] = $row['reason'];
						$_SESSION['ban'][$restriction]['ids'][] = $row['id_ban'];
						if (!isset($_SESSION['ban']['expire_time']) || ($_SESSION['ban']['expire_time'] != 0 && ($row['expire_time'] == 0 || $row['expire_time'] > $_SESSION['ban']['expire_time'])))
							$_SESSION['ban']['expire_time'] = $row['expire_time'];

						if (!$user_info['is_guest'] && $restriction == 'cannot_access' && ($row['id_member'] == $user_info['id'] || $row['email_address'] == $user_info['email']))
							$flag_is_activated = true;
					}
			}
			$smcFunc['db_free_result']($request);
		}

		// Mark the cannot_access and cannot_post bans as being 'hit'.
		if (isset($_SESSION['ban']['cannot_access']) || isset($_SESSION['ban']['cannot_post']) || isset($_SESSION['ban']['cannot_login']))
			log_ban(array_merge(isset($_SESSION['ban']['cannot_access']) ? $_SESSION['ban']['cannot_access']['ids'] : array(), isset($_SESSION['ban']['cannot_post']) ? $_SESSION['ban']['cannot_post']['ids'] : array(), isset($_SESSION['ban']['cannot_login']) ? $_SESSION['ban']['cannot_login']['ids'] : array()));

		// If for whatever reason the is_activated flag seems wrong, do a little work to clear it up.
		if ($user_info['id'] && (($user_settings['is_activated'] >= 10 && !$flag_is_activated)
			|| ($user_settings['is_activated'] < 10 && $flag_is_activated)))
		{
			require_once($sourcedir . '/ManageBans.php');
			updateBanMembers();
		}
	}

	// Hey, I know you! You're ehm...
	if (!isset($_SESSION['ban']['cannot_access']) && !empty($_COOKIE[$cookiename . '_']))
	{
		$bans = explode(',', $_COOKIE[$cookiename . '_']);
		foreach ($bans as $key => $value)
			$bans[$key] = (int) $value;
		$request = $smcFunc['db_query']('', '
			SELECT bi.id_ban, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
			WHERE bi.id_ban IN ({array_int:ban_list})
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
				AND bg.cannot_access = {int:cannot_access}
			LIMIT ' . count($bans),
			array(
				'cannot_access' => 1,
				'ban_list' => $bans,
				'current_time' => time(),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
			$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
		}
		$smcFunc['db_free_result']($request);

		// My mistake. Next time better.
		if (!isset($_SESSION['ban']['cannot_access']))
		{
			require_once($sourcedir . '/Subs-Auth.php');
			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
			smf_setcookie($cookiename . '_', '', time() - 3600, $cookie_url[1], $cookie_url[0], false, false);
		}
	}

	// If you're fully banned, it's end of the story for you.
	if (isset($_SESSION['ban']['cannot_access']))
	{
		// We don't wanna see you!
		if (!$user_info['is_guest'])
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_online
				WHERE id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
				)
			);

		// 'Log' the user out.  Can't have any funny business... (save the name!)
		$old_name = isset($user_info['name']) && $user_info['name'] != '' ? $user_info['name'] : $txt['guest_title'];
		$user_info['name'] = '';
		$user_info['username'] = '';
		$user_info['is_guest'] = true;
		$user_info['is_admin'] = false;
		$user_info['permissions'] = array();
		$user_info['id'] = 0;
		$context['user'] = array(
			'id' => 0,
			'username' => '',
			'name' => $txt['guest_title'],
			'is_guest' => true,
			'is_logged' => false,
			'is_admin' => false,
			'is_mod' => false,
			'can_mod' => false,
			'language' => $user_info['language'],
		);

		// A goodbye present.
		require_once($sourcedir . '/Subs-Auth.php');
		require_once($sourcedir . '/LogInOut.php');
		$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
		smf_setcookie($cookiename . '_', implode(',', $_SESSION['ban']['cannot_access']['ids']), time() + 3153600, $cookie_url[1], $cookie_url[0], false, false);

		// Don't scare anyone, now.
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);
		Logout(true, false);

		// You banned, sucka!
		fatal_error(sprintf($txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_access']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_access']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never']), !empty($modSettings['log_ban_hits']) ? 'ban' : false);

		// If we get here, something's gone wrong.... but let's try anyway.
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}
	// You're not allowed to log in but yet you are. Let's fix that.
	elseif (isset($_SESSION['ban']['cannot_login']) && !$user_info['is_guest'])
	{
		// We don't wanna see you!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);

		// 'Log' the user out.  Can't have any funny business... (save the name!)
		$old_name = isset($user_info['name']) && $user_info['name'] != '' ? $user_info['name'] : $txt['guest_title'];
		$user_info['name'] = '';
		$user_info['username'] = '';
		$user_info['is_guest'] = true;
		$user_info['is_admin'] = false;
		$user_info['permissions'] = array();
		$user_info['id'] = 0;
		$context['user'] = array(
			'id' => 0,
			'username' => '',
			'name' => $txt['guest_title'],
			'is_guest' => true,
			'is_logged' => false,
			'is_admin' => false,
			'is_mod' => false,
			'can_mod' => false,
			'language' => $user_info['language'],
		);

		// SMF's Wipe 'n Clean(r) erases all traces.
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		require_once($sourcedir . '/LogInOut.php');
		Logout(true, false);

		fatal_error(sprintf($txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_login']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_login']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never']) . '<br>' . $txt['ban_continue_browse'], !empty($modSettings['log_ban_hits']) ? 'ban' : false);
	}

	// Fix up the banning permissions.
	if (isset($user_info['permissions']))
		banPermissions();
}

/**
 * Fix permissions according to ban status.
 * Applies any states of banning by removing permissions the user cannot have.
 */
function banPermissions()
{
	global $user_info, $sourcedir, $modSettings, $context;

	// Somehow they got here, at least take away all permissions...
	if (isset($_SESSION['ban']['cannot_access']))
		$user_info['permissions'] = array();
	// Okay, well, you can watch, but don't touch a thing.
	elseif (isset($_SESSION['ban']['cannot_post']) || (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $user_info['warning']))
	{
		$denied_permissions = array(
			'pm_send',
			'calendar_post', 'calendar_edit_own', 'calendar_edit_any',
			'poll_post',
			'poll_add_own', 'poll_add_any',
			'poll_edit_own', 'poll_edit_any',
			'poll_lock_own', 'poll_lock_any',
			'poll_remove_own', 'poll_remove_any',
			'manage_attachments', 'manage_smileys', 'manage_boards', 'admin_forum', 'manage_permissions',
			'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news',
			'profile_identity_any', 'profile_extra_any', 'profile_title_any',
			'profile_forum_any', 'profile_other_any', 'profile_signature_any',
			'post_new', 'post_reply_own', 'post_reply_any',
			'delete_own', 'delete_any', 'delete_replies',
			'make_sticky',
			'merge_any', 'split_any',
			'modify_own', 'modify_any', 'modify_replies',
			'move_any',
			'lock_own', 'lock_any',
			'remove_own', 'remove_any',
			'post_unapproved_topics', 'post_unapproved_replies_own', 'post_unapproved_replies_any',
		);
		call_integration_hook('integrate_post_ban_permissions', array(&$denied_permissions));
		$user_info['permissions'] = array_diff($user_info['permissions'], $denied_permissions);
	}
	// Are they absolutely under moderation?
	elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $user_info['warning'])
	{
		// Work out what permissions should change...
		$permission_change = array(
			'post_new' => 'post_unapproved_topics',
			'post_reply_own' => 'post_unapproved_replies_own',
			'post_reply_any' => 'post_unapproved_replies_any',
			'post_attachment' => 'post_unapproved_attachments',
		);
		call_integration_hook('integrate_warn_permissions', array(&$permission_change));
		foreach ($permission_change as $old => $new)
		{
			if (!in_array($old, $user_info['permissions']))
				unset($permission_change[$old]);
			else
				$user_info['permissions'][] = $new;
		}
		$user_info['permissions'] = array_diff($user_info['permissions'], array_keys($permission_change));
	}

	// @todo Find a better place to call this? Needs to be after permissions loaded!
	// Finally, some bits we cache in the session because it saves queries.
	if (isset($_SESSION['mc']) && $_SESSION['mc']['time'] > $modSettings['settings_updated'] && $_SESSION['mc']['id'] == $user_info['id'])
		$user_info['mod_cache'] = $_SESSION['mc'];
	else
	{
		require_once($sourcedir . '/Subs-Auth.php');
		rebuildModCache();
	}

	// Now that we have the mod cache taken care of lets setup a cache for the number of mod reports still open
	if (!empty($_SESSION['rc']) && $_SESSION['rc']['time'] > $modSettings['last_mod_report_action'] && $_SESSION['rc']['id'] == $user_info['id'])
		$context['open_mod_reports'] = $_SESSION['rc']['reports'];
	elseif ($_SESSION['mc']['bq'] != '0=1')
	{
		require_once($sourcedir . '/Subs-ReportedContent.php');
		recountOpenReports('posts');
	}
	else
		$context['open_mod_reports'] = 0;

	if (!empty($_SESSION['rc']) && $_SESSION['rc']['time'] > $modSettings['last_mod_report_action'] && $_SESSION['rc']['id'] == $user_info['id'])
		$context['open_member_reports'] = !empty($_SESSION['rc']['member_reports']) ? $_SESSION['rc']['member_reports'] : 0;
	elseif (allowedTo('moderate_forum'))
	{
		require_once($sourcedir . '/Subs-ReportedContent.php');
		recountOpenReports('members');
	}
	else
		$context['open_member_reports'] = 0;

}

/**
 * Log a ban in the database.
 * Log the current user in the ban logs.
 * Increment the hit counters for the specified ban ID's (if any.)
 *
 * @param array $ban_ids = array()
 * @param string $email = null
 */
function log_ban($ban_ids = array(), $email = null)
{
	global $user_info, $smcFunc;

	// Don't log web accelerators, it's very confusing...
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		return;

	$smcFunc['db_insert']('',
		'{db_prefix}log_banned',
		array('id_member' => 'int', 'ip' => 'string-16', 'email' => 'string', 'log_time' => 'int'),
		array($user_info['id'], $user_info['ip'], ($email === null ? ($user_info['is_guest'] ? '' : $user_info['email']) : $email), time()),
		array('id_ban_log')
	);

	// One extra point for these bans.
	if (!empty($ban_ids))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}ban_items
			SET hits = hits + 1
			WHERE id_ban IN ({array_int:ban_ids})',
			array(
				'ban_ids' => $ban_ids,
			)
		);
}

/**
 * Checks if a given email address might be banned.
 * Check if a given email is banned.
 * Performs an immediate ban if the turns turns out positive.
 *
 * @param string $email
 * @param string $restriction
 * @param string $error
 */
function isBannedEmail($email, $restriction, $error)
{
	global $txt, $smcFunc;

	// Can't ban an empty email
	if (empty($email) || trim($email) == '')
		return;

	// Let's start with the bans based on your IP/hostname/memberID...
	$ban_ids = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['ids'] : array();
	$ban_reason = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['reason'] : '';

	// ...and add to that the email address you're trying to register.
	$request = $smcFunc['db_query']('', '
		SELECT bi.id_ban, bg.' . $restriction . ', bg.cannot_access, bg.reason
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE {string:email} LIKE bi.email_address
			AND (bg.' . $restriction . ' = {int:cannot_access} OR bg.cannot_access = {int:cannot_access})
			AND (bg.expire_time IS NULL OR bg.expire_time >= {int:now})',
		array(
			'email' => $email,
			'cannot_access' => 1,
			'now' => time(),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!empty($row['cannot_access']))
		{
			$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
			$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
		}
		if (!empty($row[$restriction]))
		{
			$ban_ids[] = $row['id_ban'];
			$ban_reason = $row['reason'];
		}
	}
	$smcFunc['db_free_result']($request);

	// You're in biiig trouble.  Banned for the rest of this session!
	if (isset($_SESSION['ban']['cannot_access']))
	{
		log_ban($_SESSION['ban']['cannot_access']['ids']);
		$_SESSION['ban']['last_checked'] = time();

		fatal_error(sprintf($txt['your_ban'], $txt['guest_title']) . $_SESSION['ban']['cannot_access']['reason'], false);
	}

	if (!empty($ban_ids))
	{
		// Log this ban for future reference.
		log_ban($ban_ids, $email);
		fatal_error($error . $ban_reason, false);
	}
}

/**
 * Make sure the user's correct session was passed, and they came from here.
 * Checks the current session, verifying that the person is who he or she should be.
 * Also checks the referrer to make sure they didn't get sent here.
 * Depends on the disableCheckUA setting, which is usually missing.
 * Will check GET, POST, or REQUEST depending on the passed type.
 * Also optionally checks the referring action if passed. (note that the referring action must be by GET.)
 *
 * @param string $type = 'post' (post, get, request)
 * @param string $from_action = ''
 * @param bool $is_fatal = true
 * @return string the error message if is_fatal is false.
 */
function checkSession($type = 'post', $from_action = '', $is_fatal = true)
{
	global $sc, $modSettings, $boardurl;

	// Is it in as $_POST['sc']?
	if ($type == 'post')
	{
		$check = isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null);
		if ($check !== $sc)
			$error = 'session_timeout';
	}

	// How about $_GET['sesc']?
	elseif ($type == 'get')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : null);
		if ($check !== $sc)
			$error = 'session_verify_fail';
	}

	// Or can it be in either?
	elseif ($type == 'request')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : (isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null)));

		if ($check !== $sc)
			$error = 'session_verify_fail';
	}

	// Verify that they aren't changing user agents on us - that could be bad.
	if ((!isset($_SESSION['USER_AGENT']) || $_SESSION['USER_AGENT'] != $_SERVER['HTTP_USER_AGENT']) && empty($modSettings['disableCheckUA']))
		$error = 'session_verify_fail';

	// Make sure a page with session check requirement is not being prefetched.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Forbidden');
		die;
	}

	// Check the referring site - it should be the same server at least!
	if (isset($_SESSION['request_referer']))
		$referrer = $_SESSION['request_referer'];
	else
		$referrer = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();
	if (!empty($referrer['host']))
	{
		if (strpos($_SERVER['HTTP_HOST'], ':') !== false)
			$real_host = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
		else
			$real_host = $_SERVER['HTTP_HOST'];

		$parsed_url = parse_url($boardurl);

		// Are global cookies on?  If so, let's check them ;).
		if (!empty($modSettings['globalCookies']))
		{
			if (preg_match('~(?:[^\.]+\.)?([^\.]{3,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
				$parsed_url['host'] = $parts[1];

			if (preg_match('~(?:[^\.]+\.)?([^\.]{3,}\..+)\z~i', $referrer['host'], $parts) == 1)
				$referrer['host'] = $parts[1];

			if (preg_match('~(?:[^\.]+\.)?([^\.]{3,}\..+)\z~i', $real_host, $parts) == 1)
				$real_host = $parts[1];
		}

		// Okay: referrer must either match parsed_url or real_host.
		if (isset($parsed_url['host']) && strtolower($referrer['host']) != strtolower($parsed_url['host']) && strtolower($referrer['host']) != strtolower($real_host))
		{
			$error = 'verify_url_fail';
			$log_error = true;
		}
	}

	// Well, first of all, if a from_action is specified you'd better have an old_url.
	if (!empty($from_action) && (!isset($_SESSION['old_url']) || preg_match('~[?;&]action=' . $from_action . '([;&]|$)~', $_SESSION['old_url']) == 0))
	{
		$error = 'verify_url_fail';
		$log_error = true;
	}

	if (strtolower($_SERVER['HTTP_USER_AGENT']) == 'hacker')
		fatal_error('Sound the alarm!  It\'s a hacker!  Close the castle gates!!', false);

	// Everything is ok, return an empty string.
	if (!isset($error))
		return '';
	// A session error occurred, show the error.
	elseif ($is_fatal)
	{
		if (isset($_GET['xml']))
		{
			ob_end_clean();
			header('HTTP/1.1 403 Forbidden - Session timeout');
			die;
		}
		else
			fatal_lang_error($error, isset($log_error) ? 'user' : false);
	}
	// A session error occurred, return the error to the calling function.
	else
		return $error;

	// We really should never fall through here, for very important reasons.  Let's make sure.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Check if a specific confirm parameter was given.
 *
 * @param string $action
 */
function checkConfirm($action)
{
	global $modSettings;

	if (isset($_GET['confirm']) && isset($_SESSION['confirm_' . $action]) && md5($_GET['confirm'] . $_SERVER['HTTP_USER_AGENT']) == $_SESSION['confirm_' . $action])
		return true;

	else
	{
		$token = md5(mt_rand() . session_id() . (string) microtime() . $modSettings['rand_seed']);
		$_SESSION['confirm_' . $action] = md5($token . $_SERVER['HTTP_USER_AGENT']);

		return $token;
	}
}

/**
 * Lets give you a token of our appreciation.
 *
 * @param string $action
 * @param string $type = 'post'
 * @return array
 */
function createToken($action, $type = 'post')
{
	global $modSettings, $context;

	$token = md5(mt_rand() . session_id() . (string) microtime() . $modSettings['rand_seed'] . $type);
	$token_var = substr(preg_replace('~^\d+~', '', md5(mt_rand() . (string) microtime() . mt_rand())), 0, rand(7, 12));

	$_SESSION['token'][$type . '-' . $action] = array($token_var, md5($token . $_SERVER['HTTP_USER_AGENT']), time(), $token);

	$context[$action . '_token'] = $token;
	$context[$action . '_token_var'] = $token_var;

	return array($action . '_token_var' => $token_var, $action . '_token' => $token);
}

/**
 * Only patrons with valid tokens can ride this ride.
 *
 * @param string $action
 * @param string $type = 'post' (get, request, or post)
 * @param bool $reset = true
 * @return boolean
 */
function validateToken($action, $type = 'post', $reset = true)
{
	$type = $type == 'get' || $type == 'request' ? $type : 'post';

	// Logins are special: the token is used to has the password with javascript before POST it
	if ($action == 'login')
	{
		if (isset($_SESSION['token'][$type . '-' . $action]))
		{
			$return = $_SESSION['token'][$type . '-' . $action][3];
			unset($_SESSION['token'][$type . '-' . $action]);
			return $return;
		}
		else
			return '';
	}

	// This nasty piece of code validates a token.
	/*
		1. The token exists in session.
		2. The {$type} variable should exist.
		3. We concat the variable we received with the user agent
		4. Match that result against what is in the session.
		5. If it matches, success, otherwise we fallout.
	*/
	if (isset($_SESSION['token'][$type . '-' . $action], $GLOBALS['_' . strtoupper($type)][$_SESSION['token'][$type . '-' . $action][0]]) && md5($GLOBALS['_' . strtoupper($type)][$_SESSION['token'][$type . '-' . $action][0]] . $_SERVER['HTTP_USER_AGENT']) == $_SESSION['token'][$type . '-' . $action][1])
	{
		// Invalidate this token now.
		unset($_SESSION['token'][$type . '-' . $action]);

		return true;
	}

	// Patrons with invalid tokens get the boot.
	if ($reset)
	{
		// Might as well do some cleanup on this.
		cleanTokens();

		// I'm back baby.
		createToken($action, $type);

		fatal_lang_error('token_verify_fail', false);
	}
	// Remove this token as its useless
	else
		unset($_SESSION['token'][$type . '-' . $action]);

	// Randomly check if we should remove some older tokens.
	if (mt_rand(0, 138) == 23)
		cleanTokens();

	return false;
}

/**
 * Removes old unused tokens from session
 * defaults to 3 hours before a token is considered expired
 * if $complete = true will remove all tokens
 *
 * @param bool $complete = false
 */
function cleanTokens($complete = false)
{
	// We appreciate cleaning up after yourselves.
	if (!isset($_SESSION['token']))
		return;

	// Clean up tokens, trying to give enough time still.
	foreach ($_SESSION['token'] as $key => $data)
		if ($data[2] + 10800 < time() || $complete)
			unset($_SESSION['token'][$key]);
}

/**
 * Check whether a form has been submitted twice.
 * Registers a sequence number for a form.
 * Checks whether a submitted sequence number is registered in the current session.
 * Depending on the value of is_fatal shows an error or returns true or false.
 * Frees a sequence number from the stack after it's been checked.
 * Frees a sequence number without checking if action == 'free'.
 *
 * @param string $action
 * @param bool $is_fatal = true
 * @return boolean
 */
function checkSubmitOnce($action, $is_fatal = true)
{
	global $context;

	if (!isset($_SESSION['forms']))
		$_SESSION['forms'] = array();

	// Register a form number and store it in the session stack. (use this on the page that has the form.)
	if ($action == 'register')
	{
		$context['form_sequence_number'] = 0;
		while (empty($context['form_sequence_number']) || in_array($context['form_sequence_number'], $_SESSION['forms']))
			$context['form_sequence_number'] = mt_rand(1, 16000000);
	}
	// Check whether the submitted number can be found in the session.
	elseif ($action == 'check')
	{
		if (!isset($_REQUEST['seqnum']))
			return true;
		elseif (!in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		{
			$_SESSION['forms'][] = (int) $_REQUEST['seqnum'];
			return true;
		}
		elseif ($is_fatal)
			fatal_lang_error('error_form_already_submitted', false);
		else
			return false;
	}
	// Don't check, just free the stack number.
	elseif ($action == 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		$_SESSION['forms'] = array_diff($_SESSION['forms'], array($_REQUEST['seqnum']));
	elseif ($action != 'free')
		trigger_error('checkSubmitOnce(): Invalid action \'' . $action . '\'', E_USER_WARNING);
}

/**
 * Check the user's permissions.
 * checks whether the user is allowed to do permission. (ie. post_new.)
 * If boards is specified, checks those boards instead of the current one.
 * Always returns true if the user is an administrator.
 *
 * @param string $permission
 * @param array $boards = null
 * @return boolean if the user can do the permission
 */
function allowedTo($permission, $boards = null)
{
	global $user_info, $smcFunc;

	// You're always allowed to do nothing. (unless you're a working man, MR. LAZY :P!)
	if (empty($permission))
		return true;

	// You're never allowed to do something if your data hasn't been loaded yet!
	if (empty($user_info))
		return false;

	// Administrators are supermen :P.
	if ($user_info['is_admin'])
		return true;

	if (!is_array($permission))
		$permission = array($permission);

	// Are we checking the _current_ board, or some other boards?
	if ($boards === null)
	{
		if (count(array_intersect($permission, $user_info['permissions'])) != 0)
			return true;
		// You aren't allowed, by default.
		else
			return false;
	}
	elseif (!is_array($boards))
		$boards = array($boards);

	$request = $smcFunc['db_query']('', '
		SELECT MIN(bp.add_deny) AS add_deny
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}board_permissions AS bp ON (bp.id_profile = b.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))
		WHERE b.id_board IN ({array_int:board_list})
			AND bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission IN ({array_string:permission_list})
			AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})
		GROUP BY b.id_board',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $boards,
			'group_list' => $user_info['groups'],
			'moderator_group' => 3,
			'permission_list' => $permission,
		)
	);

	// Make sure they can do it on all of the boards.
	if ($smcFunc['db_num_rows']($request) != count($boards))
		return false;

	$result = true;
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$result &= !empty($row['add_deny']);
	$smcFunc['db_free_result']($request);

	// If the query returned 1, they can do it... otherwise, they can't.
	return $result;
}

/**
 * Fatal error if they cannot.
 * Uses allowedTo() to check if the user is allowed to do permission.
 * Checks the passed boards or current board for the permission.
 * If they are not, it loads the Errors language file and shows an error using $txt['cannot_' . $permission].
 * If they are a guest and cannot do it, this calls is_not_guest().
 *
 * @param string $permission
 * @param array $boards = null
 */
function isAllowedTo($permission, $boards = null)
{
	global $user_info, $txt;

	static $heavy_permissions = array(
		'admin_forum',
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'edit_news',
		'moderate_forum',
		'manage_bans',
		'manage_membergroups',
		'manage_permissions',
	);

	// Make it an array, even if a string was passed.
	$permission = is_array($permission) ? $permission : array($permission);

	// Check the permission and return an error...
	if (!allowedTo($permission, $boards))
	{
		// Pick the last array entry as the permission shown as the error.
		$error_permission = array_shift($permission);

		// If they are a guest, show a login. (because the error might be gone if they do!)
		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			is_not_guest($txt['cannot_' . $error_permission]);
		}

		// Clear the action because they aren't really doing that!
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		fatal_lang_error('cannot_' . $error_permission, false);

		// Getting this far is a really big problem, but let's try our best to prevent any cases...
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}

	// If you're doing something on behalf of some "heavy" permissions, validate your session.
	// (take out the heavy permissions, and if you can't do anything but those, you need a validated session.)
	if (!allowedTo(array_diff($permission, $heavy_permissions), $boards))
		validateSession();
}

/**
 * Return the boards a user has a certain (board) permission on. (array(0) if all.)
 *  - returns a list of boards on which the user is allowed to do the specified permission.
 *  - returns an array with only a 0 in it if the user has permission to do this on every board.
 *  - returns an empty array if he or she cannot do this on any board.
 * If check_access is true will also make sure the group has proper access to that board.
 *
 * @param array $permissions
 * @param bool $check_access = true
 * @param bool $simple = true
 */
function boardsAllowedTo($permissions, $check_access = true, $simple = true)
{
	global $user_info, $smcFunc;

	// Arrays are nice, most of the time.
	if (!is_array($permissions))
		$permissions = array($permissions);

	/*
	 * Set $simple to true to use this function as it were in SMF 2.0.x.
	 * Otherwise, the resultant array becomes split into the multiple
	 * permissions that were passed. Other than that, it's just the normal
	 * state of play that you're used to.
	 */

	// Administrators are all powerful, sorry.
	if ($user_info['is_admin'])
	{
		if ($simple)
			return array(0);
		else
		{
			$boards = array();
			foreach ($permissions as $permission)
				$boards[$permission] = array(0);

			return $boards;
		}
	}

	// All groups the user is in except 'moderator'.
	$groups = array_diff($user_info['groups'], array(3));

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board, bp.add_deny' . ($simple ? '' : ', bp.permission') . '
		FROM {db_prefix}board_permissions AS bp
			INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))
		WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission IN ({array_string:permissions})
			AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})' .
			($check_access ? ' AND {query_see_board}' : ''),
		array(
			'current_member' => $user_info['id'],
			'group_list' => $groups,
			'moderator_group' => 3,
			'permissions' => $permissions,
		)
	);
	$boards = array();
	$deny_boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($simple)
		{
			if (empty($row['add_deny']))
				$deny_boards[] = $row['id_board'];
			else
				$boards[] = $row['id_board'];
		}
		else
		{
			if (empty($row['add_deny']))
				$deny_boards[$row['permission']][] = $row['id_board'];
			else
				$boards[$row['permission']][] = $row['id_board'];
		}
	}
	$smcFunc['db_free_result']($request);

	if ($simple)
		$boards = array_unique(array_values(array_diff($boards, $deny_boards)));
	else
	{
		foreach ($permissions as $permission)
		{
			// never had it to start with
			if (empty($boards[$permission]))
				$boards[$permission] = array();
			else
			{
				// Or it may have been removed
				$deny_boards[$permission] = isset($deny_boards[$permission]) ? $deny_boards[$permission] : array();
				$boards[$permission] = array_unique(array_values(array_diff($boards[$permission], $deny_boards[$permission])));
			}
		}
	}

	return $boards;
}

/**
 * This function attempts to protect from spammed messages and the like.
 * The time taken depends on error_type - generally uses the modSetting.
 *
 * @param string $error_type used also as a $txt index. (not an actual string.)
 * @param boolean $only_return_result True if you don't want the function to die with a fatal_lang_error.
 * @return boolean
 */
function spamProtection($error_type, $only_return_result = false)
{
	global $modSettings, $user_info, $smcFunc;

	// Certain types take less/more time.
	$timeOverrides = array(
		'login' => 2,
		'register' => 2,
		'remind' => 30,
		'sendmail' => $modSettings['spamWaitTime'] * 5,
		'reporttm' => $modSettings['spamWaitTime'] * 4,
		'search' => !empty($modSettings['search_floodcontrol_time']) ? $modSettings['search_floodcontrol_time'] : 1,
	);


	// Moderators are free...
	if (!allowedTo('moderate_board'))
		$timeLimit = isset($timeOverrides[$error_type]) ? $timeOverrides[$error_type] : $modSettings['spamWaitTime'];
	else
		$timeLimit = 2;

	call_integration_hook('integrate_spam_protection', array(&$timeOverrides, &$timeLimit));

	// Delete old entries...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol
		WHERE log_time < {int:log_time}
			AND log_type = {string:log_type}',
		array(
			'log_time' => time() - $timeLimit,
			'log_type' => $error_type,
		)
	);

	// Add a new entry, deleting the old if necessary.
	$smcFunc['db_insert']('replace',
		'{db_prefix}log_floodcontrol',
		array('ip' => 'string-16', 'log_time' => 'int', 'log_type' => 'string'),
		array($user_info['ip'], time(), $error_type),
		array('ip', 'log_type')
	);

	// If affected is 0 or 2, it was there already.
	if ($smcFunc['db_affected_rows']() != 1)
	{
		// Spammer!  You only have to wait a *few* seconds!
		if (!$only_return_result)
			fatal_lang_error($error_type . '_WaitTime_broken', false, array($timeLimit));

		return true;
	}

	// They haven't posted within the limit.
	return false;
}

/**
 * A generic function to create a pair of index.php and .htaccess files in a directory
 *
 * @param string $path the (absolute) directory path
 * @param boolean $attachments if the directory is an attachments directory or not
 * @return true on success error string if anything fails
 */
function secureDirectory($path, $attachments = false)
{
	if (empty($path))
		return 'empty_path';

	if (!is_writable($path))
		return 'path_not_writable';

	$directoryname = basename($path);

	$errors = array();
	$close = empty($attachments) ? '
</Files>' : '
	Allow from localhost
</Files>

RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml';

	if (file_exists($path . '/.htaccess'))
		$errors[] = 'htaccess_exists';
	else
	{
		$fh = @fopen($path . '/.htaccess', 'w');
		if ($fh) {
			fwrite($fh, '<Files *>
	Order Deny,Allow
	Deny from all' . $close);
			fclose($fh);
		}
		$errors[] = 'htaccess_cannot_create_file';
	}

	if (file_exists($path . '/index.php'))
		$errors[] = 'index-php_exists';
	else
	{
		$fh = @fopen($path . '/index.php', 'w');
		if ($fh) {
			fwrite($fh, '<' . '?php

/**
 * This file is here solely to protect your ' . $directoryname . ' directory.
 */

// Look for Settings.php....
if (file_exists(dirname(dirname(__FILE__)) . \'/Settings.php\'))
{
	// Found it!
	require(dirname(dirname(__FILE__)) . \'/Settings.php\');
	header(\'Location: \' . $boardurl);
}
// Can\'t find it... just forget it.
else
	exit;

?'. '>');
			fclose($fh);
		}
		$errors[] = 'index-php_cannot_create_file';
	}

	if (!empty($errors))
		return $errors;
	else
		return true;
}

/**
 * Helper function that puts together a ban query for a given ip
 * builds the query for ipv6, ipv4 or 255.255.255.255 depending on whats supplied
 *
 * @param string $fullip An IP address either IPv6 or not
 * @return string A SQL condition
 */
function constructBanQueryIP($fullip)
{
	// First attempt a IPv6 address.
	if (isValidIPv6($fullip))
	{
		$ip_parts = convertIPv6toInts($fullip);

		$ban_query = '((' . $ip_parts[0] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
			AND (' . $ip_parts[1] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
			AND (' . $ip_parts[2] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
			AND (' . $ip_parts[3] . ' BETWEEN bi.ip_low4 AND bi.ip_high4)
			AND (' . $ip_parts[4] . ' BETWEEN bi.ip_low5 AND bi.ip_high5)
			AND (' . $ip_parts[5] . ' BETWEEN bi.ip_low6 AND bi.ip_high6)
			AND (' . $ip_parts[6] . ' BETWEEN bi.ip_low7 AND bi.ip_high7)
			AND (' . $ip_parts[7] . ' BETWEEN bi.ip_low8 AND bi.ip_high8))';
	}
	// Check if we have a valid IPv4 address.
	elseif (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $fullip, $ip_parts) == 1)
		$ban_query = '((' . $ip_parts[1] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
			AND (' . $ip_parts[2] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
			AND (' . $ip_parts[3] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
			AND (' . $ip_parts[4] . ' BETWEEN bi.ip_low4 AND bi.ip_high4))';
	// We use '255.255.255.255' for 'unknown' since it's not valid anyway.
	else
		$ban_query = '(bi.ip_low1 = 255 AND bi.ip_high1 = 255
			AND bi.ip_low2 = 255 AND bi.ip_high2 = 255
			AND bi.ip_low3 = 255 AND bi.ip_high3 = 255
			AND bi.ip_low4 = 255 AND bi.ip_high4 = 255)';

	return $ban_query;
}

/**
* This sets the X-Frame-Options header.
*
* @param string $option the frame option, defaults to deny.
* @return void.
* @since 2.1
*/
function frameOptionsHeader($override = null)
{
	global $modSettings;

	$option = 'SAMEORIGIN';
	if (is_null($override) && !empty($modSettings['frame_security']))
		$option = $modSettings['frame_security'];
	elseif (in_array($override, array('SAMEORIGIN', 'DENY')))
		$option = $override;

	// Don't bother setting the header if we have disabled it.
	if ($option == 'DISABLE')
		return;

	// Finally set it.
	header('X-Frame-Options: ' . $option);

	// And some other useful ones.
	header('X-XSS-Protection: 1');
	header('X-Content-Type-Options: nosniff');
}

?>