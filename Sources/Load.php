<?php

/**
 * This file has the hefty job of loading information for the forum.
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

use SMF\BBCodeParser;
use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Load all the important user information.
 * What it does:
 * 	- sets up the $user_info array
 * 	- assigns $user_info['query_wanna_see_board'] for what boards the user can see.
 * 	- first checks for cookie or integration validation.
 * 	- uses the current session if no integration function or cookie is found.
 * 	- checks password length, if member is activated and the login span isn't over.
 * 		- if validation fails for the user, $id_member is set to 0.
 * 		- updates the last visit time when needed.
 */
function loadUserSettings()
{
	global $user_settings;
	global $user_info;

	require_once(Config::$sourcedir . '/Subs-Auth.php');

	// Check first the integration, then the cookie, and last the session.
	if (count($integration_ids = call_integration_hook('integrate_verify_user')) > 0)
	{
		$id_member = 0;
		foreach ($integration_ids as $integration_id)
		{
			$integration_id = (int) $integration_id;
			if ($integration_id > 0)
			{
				$id_member = $integration_id;
				$already_verified = true;
				break;
			}
		}
	}
	else
		$id_member = 0;

	if (empty($id_member) && isset($_COOKIE[Config::$cookiename]))
	{
		// First try 2.1 json-format cookie
		$cookie_data = Utils::jsonDecode($_COOKIE[Config::$cookiename], true, false);

		// Legacy format (for recent 2.0 --> 2.1 upgrades)
		if (empty($cookie_data))
			$cookie_data = safe_unserialize($_COOKIE[Config::$cookiename]);

		list($id_member, $password, $login_span, $cookie_domain, $cookie_path) = array_pad((array) $cookie_data, 5, '');

		$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;

		// Make sure the cookie is set to the correct domain and path
		if (array($cookie_domain, $cookie_path) !== url_parts(!empty(Config::$modSettings['localCookies']), !empty(Config::$modSettings['globalCookies'])))
			setLoginCookie((int) $login_span - time(), $id_member);
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . Config::$cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty(Config::$modSettings['disableCheckUA'])))
	{
		// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
		$cookie_data = Utils::jsonDecode($_SESSION['login_' . Config::$cookiename], true);

		if (empty($cookie_data))
			$cookie_data = safe_unserialize($_SESSION['login_' . Config::$cookiename]);

		list($id_member, $password, $login_span) = array_pad((array) $cookie_data, 3, '');
		$id_member = !empty($id_member) && strlen($password) == 40 && (int) $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty(CacheApi::$enable) || CacheApi::$enable < 2 || ($user_settings = CacheApi::get('user_settings-' . $id_member, 60)) == null)
		{
			$request = Db::$db->query('', '
				SELECT mem.*, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.width AS "attachment_width", a.height AS "attachment_height"
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			if (!empty($user_settings['avatar']))
				$user_settings['avatar'] = get_proxied_url($user_settings['avatar']);

			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
				CacheApi::put('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-512 hash should be 128 characters long.
			elseif (strlen($password) == 128)
				$check = hash_equals(hash_salt($user_settings['passwd'], $user_settings['password_salt']), $password);
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? (int) $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// Check if we are forcing TFA
		$force_tfasetup = !empty(Config::$modSettings['tfa_mode']) && Config::$modSettings['tfa_mode'] >= 2 && $id_member && empty($user_settings['tfa_secret']) && SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml');

		// Don't force TFA on popups
		if ($force_tfasetup)
		{
			if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'profile' && isset($_REQUEST['area']) && in_array($_REQUEST['area'], array('popup', 'alerts_popup')))
				$force_tfasetup = false;
			elseif (isset($_REQUEST['action']) && $_REQUEST['action'] == 'pm' && (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'popup'))
				$force_tfasetup = false;

			call_integration_hook('integrate_force_tfasetup', array(&$force_tfasetup));
		}

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
		{
			require_once(Config::$sourcedir . '/LogInOut.php');
			validatePasswordFlood(
				!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member,
				!empty($user_settings['member_name']) ? $user_settings['member_name'] : '',
				!empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false,
				$id_member != 0
			);
		}
		// Validate for Two Factor Authentication
		elseif (!empty(Config::$modSettings['tfa_mode']) && $id_member && !empty($user_settings['tfa_secret']) && (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], array('login2', 'logintfa'))))
		{
			$tfacookie = Config::$cookiename . '_tfa';
			$tfasecret = null;

			$verified = call_integration_hook('integrate_verify_tfa', array($id_member, $user_settings));

			if (empty($verified) || !in_array(true, $verified))
			{
				if (!empty($_COOKIE[$tfacookie]))
				{
					$tfa_data = Utils::jsonDecode($_COOKIE[$tfacookie], true);

					list ($tfamember, $tfasecret) = array_pad((array) $tfa_data, 2, '');

					if (!isset($tfamember, $tfasecret) || (int) $tfamember != $id_member)
						$tfasecret = null;
				}

				// They didn't finish logging in before coming here? Then they're no one to us.
				if (empty($tfasecret) || !hash_equals(hash_salt($user_settings['tfa_backup'], $user_settings['password_salt']), $tfasecret))
				{
					setLoginCookie(-3600, $id_member);
					$id_member = 0;
					$user_settings = array();
				}
			}
		}
		// When authenticating their two factor code, make sure to reset their ID for security
		elseif (!empty(Config::$modSettings['tfa_mode']) && $id_member && !empty($user_settings['tfa_secret']) && $_REQUEST['action'] == 'logintfa')
		{
			$id_member = 0;
			Utils::$context['tfa_member'] = $user_settings;
			$user_settings = array();
		}
		// Are we forcing 2FA? Need to check if the user groups actually require 2FA
		elseif ($force_tfasetup)
		{
			if (Config::$modSettings['tfa_mode'] == 2) //only do this if we are just forcing SOME membergroups
			{
				//Build an array of ALL user membergroups.
				$full_groups = array($user_settings['id_group']);
				if (!empty($user_settings['additional_groups']))
				{
					$full_groups = array_merge($full_groups, explode(',', $user_settings['additional_groups']));
					$full_groups = array_unique($full_groups); //duplicates, maybe?
				}

				//Find out if any group requires 2FA
				$request = Db::$db->query('', '
					SELECT COUNT(id_group) AS total
					FROM {db_prefix}membergroups
					WHERE tfa_required = {int:tfa_required}
						AND id_group IN ({array_int:full_groups})',
					array(
						'tfa_required' => 1,
						'full_groups' => $full_groups,
					)
				);
				$row = Db::$db->fetch_assoc($request);
				Db::$db->free_result($request);
			}
			else
				$row['total'] = 1; //simplifies logics in the next "if"

			$area = !empty($_REQUEST['area']) ? $_REQUEST['area'] : '';
			$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';

			if ($row['total'] > 0 && !in_array($action, array('profile', 'logout')) || ($action == 'profile' && $area != 'tfasetup'))
				redirectexit('action=profile;area=tfasetup;forced');
		}
	}

	// Found 'im, let's set up the variables.
	if ($id_member != 0)
	{
		// Let's not update the last visit time in these cases...
		// 1. SSI doesn't count as visiting the forum.
		// 2. RSS feeds and XMLHTTP requests don't count either.
		// 3. If it was set within this session, no need to set it again.
		// 4. New session, yet updated < five hours ago? Maybe cache can help.
		// 5. We're still logging in or authenticating
		if (SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], array('.xml', 'login2', 'logintfa'))) && empty($_SESSION['id_msg_last_visit']) && (empty(CacheApi::$enable) || ($_SESSION['id_msg_last_visit'] = CacheApi::get('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			$result = Db::$db->query('', '
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $user_settings['id_msg_last_visit'],
				)
			);
			list ($visitTime) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600)
			{
				updateMemberData($id_member, array('id_msg_last_visit' => (int) Config::$modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
				$user_settings['last_login'] = time();

				if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
					CacheApi::put('user_settings-' . $id_member, $user_settings, 60);

				if (!empty(CacheApi::$enable))
					CacheApi::put('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
			}
		}
		elseif (empty($_SESSION['id_msg_last_visit']))
			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

		$username = $user_settings['member_name'];

		if (empty($user_settings['additional_groups']))
			$user_info = array(
				'groups' => array($user_settings['id_group'], $user_settings['id_post_group'])
			);

		else
			$user_info = array(
				'groups' => array_merge(
					array($user_settings['id_group'], $user_settings['id_post_group']),
					explode(',', $user_settings['additional_groups'])
				)
			);

		// Because history has proven that it is possible for groups to go bad - clean up in case.
		$user_info['groups'] = array_map('intval', $user_info['groups']);

		// This is a logged in user, so definitely not a spider.
		$user_info['possibly_robot'] = false;

		// Figure out the new time offset.
		if (!empty($user_settings['timezone']))
		{
			// Get the offsets from UTC for the server, then for the user.
			$tz_system = new DateTimeZone(Config::$modSettings['default_timezone']);
			$tz_user = new DateTimeZone($user_settings['timezone']);
			$time_system = new DateTime('now', $tz_system);
			$time_user = new DateTime('now', $tz_user);
			$user_settings['time_offset'] = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600;
		}
		// We need a time zone.
		else
		{
			if (!empty($user_settings['time_offset']))
			{
				$tz_system = new DateTimeZone(Config::$modSettings['default_timezone']);
				$time_system = new DateTime('now', $tz_system);

				$user_settings['timezone'] = @timezone_name_from_abbr('', $tz_system->getOffset($time_system) + $user_settings['time_offset'] * 3600, (int) $time_system->format('I'));
			}

			if (empty($user_settings['timezone']))
			{
				$user_settings['timezone'] = Config::$modSettings['default_timezone'];
				$user_settings['time_offset'] = 0;
			}
		}
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[Config::$cookiename]) && empty(Utils::$context['tfa_member']))
			$_COOKIE[Config::$cookiename] = '';

		// Expire the 2FA cookie
		if (isset($_COOKIE[Config::$cookiename . '_tfa']) && empty(Utils::$context['tfa_member']))
		{
			$tfa_data = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);

			list (,, $exp) = array_pad((array) $tfa_data, 3, 0);

			if (time() > $exp)
			{
				$_COOKIE[Config::$cookiename . '_tfa'] = '';
				setTFACookie(-3600, 0, '');
			}
		}

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login']))
			createToken('login');
		else
			list (Utils::$context['login_token_var'],,, Utils::$context['login_token']) = $_SESSION['token']['post-login'];

		// Do we perhaps think this is a search robot? Check every five minutes just in case...
		if ((!empty(Config::$modSettings['spider_mode']) || !empty(Config::$modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			require_once(Config::$sourcedir . '/ManageSearchEngines.php');
			$user_info['possibly_robot'] = SpiderCheck();
		}
		elseif (!empty(Config::$modSettings['spider_mode']))
			$user_info['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
		// If we haven't turned on proper spider hunts then have a guess!
		else
		{
			$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			$user_info['possibly_robot'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || strpos($ci_user_agent, 'googlebot') !== false || strpos($ci_user_agent, 'slurp') !== false || strpos($ci_user_agent, 'crawl') !== false || strpos($ci_user_agent, 'bingbot') !== false || strpos($ci_user_agent, 'bingpreview') !== false || strpos($ci_user_agent, 'adidxbot') !== false || strpos($ci_user_agent, 'msnbot') !== false;
		}

		$user_settings['timezone'] = Config::$modSettings['default_timezone'];
		$user_settings['time_offset'] = 0;
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ip2' => $_SERVER['BAN_CHECK_IP'],
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? Config::$modSettings['time_format'] : $user_settings['time_format'],
		'timezone' => $user_settings['timezone'],
		'time_offset' => $user_settings['time_offset'],
		'avatar' => array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0,
			'width' => isset($user_settings['attachment_width']) > 0 ? $user_settings['attachment_width']: 0,
			'height' => isset($user_settings['attachment_height']) > 0 ? $user_settings['attachment_height'] : 0,
		),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'alerts' => empty($user_settings['alerts']) ? 0 : $user_settings['alerts'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty(Config::$modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty(Config::$modSettings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);
	$user_info['groups'] = array_unique($user_info['groups']);
	$user_info['can_manage_boards'] = !empty($user_info['is_admin']) || (!empty(Config::$modSettings['board_manager_groups']) && count(array_intersect($user_info['groups'], explode(',', Config::$modSettings['board_manager_groups']))) > 0);

	// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Allow the user to change their language.
	if (!empty(Config::$modSettings['userLanguage']))
	{
		$languages = Lang::get();

		// Is it valid?
		if (!empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
		{
			$user_info['language'] = strtr($_GET['language'], './\\:', '____');

			// Make it permanent for members.
			if (!empty($user_info['id']))
				updateMemberData($user_info['id'], array('lngfile' => $user_info['language']));
			else
				$_SESSION['language'] = $user_info['language'];
			// Reload same url with new language, if it exist
			if (isset($_SESSION['old_url']))
				redirectexit($_SESSION['old_url']);
		}
		elseif (!empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
			$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');
	}

	$temp = build_query_board($user_info['id']);
	$user_info['query_see_board'] = $temp['query_see_board'];
	$user_info['query_see_message_board'] = $temp['query_see_message_board'];
	$user_info['query_see_topic_board'] = $temp['query_see_topic_board'];
	$user_info['query_wanna_see_board'] = $temp['query_wanna_see_board'];
	$user_info['query_wanna_see_message_board'] = $temp['query_wanna_see_message_board'];
	$user_info['query_wanna_see_topic_board'] = $temp['query_wanna_see_topic_board'];

	call_integration_hook('integrate_user_info');
}

/**
 * Load minimal user info from members table.
 * Intended for use by background tasks that need to populate $user_info.
 *
 * @param int|array $user_ids The users IDs to get the data for.
 * @return array
 * @throws Exception
 */
function loadMinUserInfo($user_ids = array())
{
	static $user_info_min = array();

	$user_ids = (array) $user_ids;

	// Already loaded?
	if (!empty($user_ids))
		$user_ids = array_diff($user_ids, array_keys($user_info_min));

	if (empty($user_ids))
		return $user_info_min;

	$columns_to_load = array(
		'id_member',
		'member_name',
		'real_name',
		'time_offset',
		'additional_groups',
		'id_group',
		'id_post_group',
		'lngfile',
		'smiley_set',
		'timezone',
	);

	call_integration_hook('integrate_load_min_user_settings_columns', array(&$columns_to_load));

	$request = Db::$db->query('', '
		SELECT {raw:columns}
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:user_ids})',
		array(
			'user_ids' => array_map('intval', array_unique($user_ids)),
			'columns' => implode(', ', $columns_to_load)
		)
	);

	while ($row = Db::$db->fetch_assoc($request))
	{
		$user_info_min[$row['id_member']] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => isset($row['real_name']) ? $row['real_name'] : '',
			'language' => (empty($row['lngfile']) || empty(Config::$modSettings['userLanguage'])) ? Lang::$default : $row['lngfile'],
			'is_guest' => false,
			'time_format' => empty($row['time_format']) ? Config::$modSettings['time_format'] : $row['time_format'],
			'smiley_set' => empty($row['smiley_set']) ? Config::$modSettings['smiley_sets_default'] : $row['smiley_set'],
		);

		if (empty($row['additional_groups']))
			$user_info_min[$row['id_member']]['groups'] = array($row['id_group'], $row['id_post_group']);

		else
			$user_info_min[$row['id_member']]['groups'] = array_merge(
				array($row['id_group'], $row['id_post_group']),
				explode(',', $row['additional_groups'])
			);

		$user_info_min[$row['id_member']]['is_admin'] = in_array(1, $user_info_min[$row['id_member']]['groups']);

		if (!empty($row['timezone']))
		{
			$tz_system = new \DateTimeZone(Config::$modSettings['default_timezone']);
			$tz_user = new \DateTimeZone($row['timezone']);
			$time_system = new \DateTime('now', $tz_system);
			$time_user = new \DateTime('now', $tz_user);
			$row['time_offset'] = ($tz_user->getOffset($time_user) -
					$tz_system->getOffset($time_system)) / 3600;
		}
		else
		{
			if (!empty($row['time_offset']))
			{
				$tz_system = new \DateTimeZone(Config::$modSettings['default_timezone']);
				$time_system = new \DateTime('now', $tz_system);

				$row['timezone'] = @timezone_name_from_abbr('', $tz_system->getOffset($time_system) + $row['time_offset'] * 3600, (int) $time_system->format('I'));
			}

			if (empty($row['timezone']))
			{
				$row['timezone'] = Config::$modSettings['default_timezone'];
				$row['time_offset'] = 0;
			}
		}

		$user_info_min[$row['id_member']]['timezone'] = $row['timezone'];
		$user_info_min[$row['id_member']]['time_offset'] = $row['time_offset'];
	}

	Db::$db->free_result($request);

	call_integration_hook('integrate_load_min_user_settings', array(&$user_info_min));

	return $user_info_min;
}

/**
 * Check for moderators and see if they have access to the board.
 * What it does:
 * - sets up the $board_info array for current board information.
 * - if cache is enabled, the $board_info array is stored in cache.
 * - redirects to appropriate post if only message id is requested.
 * - is only used when inside a topic or board.
 * - determines the local moderators for the board.
 * - adds group id 3 if the user is a local moderator for the board they are in.
 * - prevents access if user is not in proper group nor a local moderator of the board.
 */
function loadBoard()
{
	global $board_info, $board, $topic, $user_info;

	// Assume they are not a moderator.
	$user_info['is_mod'] = false;
	Utils::$context['user']['is_mod'] = &$user_info['is_mod'];

	// Start the linktree off empty..
	Utils::$context['linktree'] = array();

	// Have they by chance specified a message id but nothing else?
	if (empty($_REQUEST['action']) && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = CacheApi::get('msg_topic-' . $_REQUEST['msg'], 120)) === null)
		{
			$request = Db::$db->query('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);

			// So did it find anything?
			if (Db::$db->num_rows($request))
			{
				list ($topic) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
				// Save save save.
				CacheApi::put('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			fatal_lang_error('topic_gone', false);
		}
	}

	// Load this board only if it is specified.
	if (empty($board) && empty($topic))
	{
		$board_info = array('moderators' => array(), 'moderator_groups' => array());
		return;
	}

	if (!empty(CacheApi::$enable) && (empty($topic) || CacheApi::$enable >= 3))
	{
		// @todo SLOW?
		if (!empty($topic))
			$temp = CacheApi::get('topic_board-' . $topic, 120);
		else
			$temp = CacheApi::get('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$custom_column_selects = array();
		$custom_column_parameters = [
			'current_topic' => $topic,
			'board_link' => empty($topic) ? Db::$db->quote('{int:current_board}', array('current_board' => $board)) : 't.id_board',
		];

		call_integration_hook('integrate_load_board', array(&$custom_column_selects, &$custom_column_parameters));

		$request = Db::$db->query('load_board_info', '
			SELECT
				c.id_cat, b.name AS bname, b.description, b.num_topics, b.member_groups, b.deny_member_groups,
				b.id_parent, c.name AS cname, COALESCE(mg.id_group, 0) AS id_moderator_group, mg.group_name,
				COALESCE(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect,
				b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . '
				' . (!empty($custom_column_selects) ? (', ' . implode(', ', $custom_column_selects)) : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = modgs.id_group)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE b.id_board = {raw:board_link}',
			$custom_column_parameters
		);

		// If there aren't any, skip.
		if (Db::$db->num_rows($request) > 0)
		{
			$row = Db::$db->fetch_assoc($request);

			// Set the current board.
			if (!empty($row['id_board']))
				$board = $row['id_board'];

			// Basic operating information. (globals... :/)
			$board_info = array(
				'id' => $board,
				'moderators' => array(),
				'moderator_groups' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
				'description' => $row['description'],
				'num_topics' => $row['num_topics'],
				'unapproved_topics' => $row['unapproved_topics'],
				'unapproved_posts' => $row['unapproved_posts'],
				'unapproved_user_topics' => 0,
				'parent_boards' => getBoardParents($row['id_parent']),
				'parent' => $row['id_parent'],
				'child_level' => $row['child_level'],
				'theme' => $row['id_theme'],
				'override_theme' => !empty($row['override_theme']),
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'recycle' => !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) && Config::$modSettings['recycle_board'] == $board,
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
			);

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] == '' ? array() : explode(',', $row['member_groups']);
			$board_info['deny_groups'] = $row['deny_member_groups'] == '' ? array() : explode(',', $row['deny_member_groups']);

			call_integration_hook('integrate_board_info', array(&$board_info, $row));

			if (!empty(Config::$modSettings['board_manager_groups']))
			{
				$board_info['groups'] = array_unique(array_merge($board_info['groups'], explode(',', Config::$modSettings['board_manager_groups'])));
				$board_info['deny_groups'] = array_diff($board_info['deny_groups'], explode(',', Config::$modSettings['board_manager_groups']));
			}

			do
			{
				if (!empty($row['id_moderator']))
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);

				if (!empty($row['id_moderator_group']))
					$board_info['moderator_groups'][$row['id_moderator_group']] = array(
						'id' => $row['id_moderator_group'],
						'name' => $row['group_name'],
						'href' => Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'],
						'link' => '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'] . '">' . $row['group_name'] . '</a>'
					);
			}
			while ($row = Db::$db->fetch_assoc($request));

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && Config::$modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				// Free the previous result
				Db::$db->free_result($request);

				// @todo why is this using id_topic?
				// @todo Can this get cached?
				$request = Db::$db->query('', '
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started={int:id_member}
						AND approved = {int:unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => $user_info['id'],
						'unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = Db::$db->fetch_row($request);
			}

			if (!empty(CacheApi::$enable) && (empty($topic) || CacheApi::$enable >= 3))
			{
				// @todo SLOW?
				if (!empty($topic))
					CacheApi::put('topic_board-' . $topic, $board_info, 120);
				CacheApi::put('board-' . $board, $board_info, 120);
			}
		}
		else
		{
			// Otherwise the topic is invalid, there are no moderators, etc.
			$board_info = array(
				'moderators' => array(),
				'moderator_groups' => array(),
				'error' => 'exist'
			);
			$topic = null;
			$board = 0;
		}
		Db::$db->free_result($request);
	}

	if (!empty($topic))
		$_GET['board'] = (int) $board;

	if (!empty($board))
	{
		// Get this into an array of keys for array_intersect
		$moderator_groups = array_keys($board_info['moderator_groups']);

		// Now check if the user is a moderator.
		$user_info['is_mod'] = isset($board_info['moderators'][$user_info['id']]) || count(array_intersect($user_info['groups'], $moderator_groups)) != 0;

		if (count(array_intersect($user_info['groups'], $board_info['groups'])) == 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';
		if (!empty(Config::$modSettings['deny_boards_access']) && count(array_intersect($user_info['groups'], $board_info['deny_groups'])) != 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';

		// Build up the linktree.
		Utils::$context['linktree'] = array_merge(
			Utils::$context['linktree'],
			array(array(
				'url' => Config::$scripturl . '#c' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => Config::$scripturl . '?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);
	}

	// Set the template contextual information.
	Utils::$context['user']['is_mod'] = &$user_info['is_mod'];
	Utils::$context['current_topic'] = $topic;
	Utils::$context['current_board'] = $board;

	// No posting in redirection boards!
	if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'post' && !empty($board_info['redirect']))
		$board_info['error'] = 'post_in_redirect';

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && (!empty(Config::$modSettings['deny_boards_access']) || $board_info['error'] != 'access' || !$user_info['is_mod']))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		loadTheme();

		$_GET['board'] = '';
		$_GET['topic'] = '';

		// The linktree should not give the game away mate!
		Utils::$context['linktree'] = array(
			array(
				'url' => Config::$scripturl,
				'name' => Utils::$context['forum_name_html_safe']
			)
		);

		// If it's a prefetching agent or we're requesting an attachment.
		if ((isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') || (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'dlattach'))
		{
			ob_end_clean();
			send_http_status(403);
			die;
		}
		elseif ($board_info['error'] == 'post_in_redirect')
		{
			// Slightly different error message here...
			fatal_lang_error('cannot_post_redirect', false);
		}
		elseif ($user_info['is_guest'])
		{
			Lang::load('Errors');
			is_not_guest(Lang::$txt['topic_gone']);
		}
		else
			fatal_lang_error('topic_gone', false);
	}

	if ($user_info['is_mod'])
		$user_info['groups'][] = 3;
}

/**
 * Load this user's permissions.
 */
function loadPermissions()
{
	global $user_info, $board, $board_info;

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	if (!empty(CacheApi::$enable))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if (CacheApi::$enable >= 2 && !empty($board) && ($temp = CacheApi::get('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > Config::$modSettings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = CacheApi::get('permissions:' . $cache_groups, 240)) != null && time() - 240 > Config::$modSettings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty(Config::$modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = Db::$db->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty(Config::$modSettings['spider_group']) ? Config::$modSettings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		Db::$db->free_result($request);

		if (isset($cache_groups))
			CacheApi::put('permissions:' . $cache_groups, array($user_info['permissions'], $removals), 240);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = Db::$db->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => $user_info['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty(Config::$modSettings['spider_group']) ? Config::$modSettings['spider_group'] : 0,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		Db::$db->free_result($request);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty(Config::$modSettings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && CacheApi::$enable >= 2)
		CacheApi::put('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

	// Banned?  Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= Config::$modSettings['settings_updated'])
		{
			require_once(Config::$sourcedir . '/Subs-Auth.php');
			rebuildModCache();
		}
		else
			$user_info['mod_cache'] = $_SESSION['mc'];

		// This is a useful phantom permission added to the current user, and only the current user while they are logged in.
		// For example this drastically simplifies certain changes to the profile area.
		$user_info['permissions'][] = 'is_not_guest';
		// And now some backwards compatibility stuff for mods and whatnot that aren't expecting the new permissions.
		$user_info['permissions'][] = 'profile_view_own';
		if (in_array('profile_view', $user_info['permissions']))
			$user_info['permissions'][] = 'profile_view_any';
	}
}

/**
 * Loads an array of users' data by ID or member_name.
 *
 * @param array|string $users An array of users by id or name or a single username/id
 * @param bool $is_name Whether $users contains names
 * @param string $set What kind of data to load (normal, profile, minimal)
 * @return array The ids of the members loaded
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $board_info;
	global $user_info;

	// Can't just look for no users :P.
	if (empty($users))
		return array();

	// Pass the set value
	Utils::$context['loadMemberContext_set'] = $set;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty(CacheApi::$enable) && CacheApi::$enable >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = CacheApi::get('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	// Used by default
	$select_columns = '
			COALESCE(lo.log_time, 0) AS is_online, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.width "attachment_width", a.height "attachment_height",
			mem.signature, mem.personal_text, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login, mem.id_post_group, mem.lngfile, mem.id_group, mem.time_offset, mem.timezone, mem.show_online,
			mg.online_color AS member_group_color, COALESCE(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, COALESCE(pg.group_name, {string:blank_string}) AS post_group,
			mem.is_activated, mem.warning, ' . (!empty(Config::$modSettings['titlesEnable']) ? 'mem.usertitle, ' : '') . '
			CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons';
	$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';

	// We add or replace according the the set
	switch ($set)
	{
		case 'normal':
			$select_columns .= ', mem.buddy_list,  mem.additional_groups';
			break;
		case 'profile':
			$select_columns .= ', mem.additional_groups, mem.id_theme, mem.pm_ignore_list, mem.pm_receive_from,
			mem.time_format, mem.timezone, mem.secret_question, mem.smiley_set, mem.tfa_secret,
			mem.total_time_logged_in, lo.url, mem.ignore_boards, mem.password_salt, mem.pm_prefs, mem.buddy_list, mem.alerts';
			break;
		case 'minimal':
			$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
			$select_tables = '';
			break;
		default:
		{
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['invalid_member_data_set'], $set), E_USER_WARNING);
		}
	}

	// Allow mods to easily add to the selected member data
	call_integration_hook('integrate_load_member_data', array(&$select_columns, &$select_tables, &$set));

	if (!empty($users))
	{
		// Load the member's data.
		$request = Db::$db->query('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})',
			array(
				'blank_string' => '',
				'users' => $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			// If the image proxy is enabled, we still want the original URL when they're editing the profile...
			$row['avatar_original'] = !empty($row['avatar']) ? $row['avatar'] : '';

			// Take care of proxying avatar if required, do this here for maximum reach
			if (!empty($row['avatar']))
				$row['avatar'] = get_proxied_url($row['avatar']);

			// Keep track of the member's normal member group
			$row['primary_group'] = !empty($row['member_group']) ? $row['member_group'] : '';

			if (isset($row['member_ip']))
				$row['member_ip'] = inet_dtop($row['member_ip']);
			if (isset($row['member_ip2']))
				$row['member_ip2'] = inet_dtop($row['member_ip2']);
			$row['id_member'] = (int) $row['id_member'];
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$user_profile[$row['id_member']] = $row;
		}
		Db::$db->free_result($request);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal')
	{
		$request = Db::$db->query('', '
			SELECT id_member, variable, value
			FROM {db_prefix}themes
			WHERE id_member IN ({array_int:loaded_ids})',
			array(
				'loaded_ids' => $new_loaded_ids,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		Db::$db->free_result($request);
	}

	$additional_mods = array();

	// Are any of these users in groups assigned to moderate this board?
	if (!empty($loaded_ids) && !empty($board_info['moderator_groups']) && $set === 'normal')
	{
		foreach ($loaded_ids as $a_member)
		{
			if (!empty($user_profile[$a_member]['additional_groups']))
				$groups = array_merge(array($user_profile[$a_member]['id_group']), explode(',', $user_profile[$a_member]['additional_groups']));
			else
				$groups = array($user_profile[$a_member]['id_group']);

			$temp = array_intersect($groups, array_keys($board_info['moderator_groups']));

			if (!empty($temp))
			{
				$additional_mods[] = $a_member;
			}
		}
	}

	if (!empty($new_loaded_ids) && !empty(CacheApi::$enable) && CacheApi::$enable >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			CacheApi::put('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators?  If so, fix their group data...
	if (!empty($loaded_ids) && (!empty($board_info['moderators']) || !empty($board_info['moderator_groups'])) && $set === 'normal' && count($temp_mods = array_merge(array_intersect($loaded_ids, array_keys($board_info['moderators'])), $additional_mods)) !== 0)
	{
		if (($row = CacheApi::get('moderator_group_info', 480)) == null)
		{
			$request = Db::$db->query('', '
				SELECT group_name AS member_group, online_color AS member_group_color, icons
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$row = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			CacheApi::put('moderator_group_info', $row, 480);
		}

		foreach ($temp_mods as $id)
		{
			// By popular demand, don't show admins or global moderators as moderators.
			if ($user_profile[$id]['id_group'] != 1 && $user_profile[$id]['id_group'] != 2)
				$user_profile[$id]['member_group'] = $row['member_group'];

			// If the Moderator group has no color or icons, but their group does... don't overwrite.
			if (!empty($row['icons']))
				$user_profile[$id]['icons'] = $row['icons'];
			if (!empty($row['member_group_color']))
				$user_profile[$id]['member_group_color'] = $row['member_group_color'];
		}
	}

	return $loaded_ids;
}

/**
 * Loads the user's basic values... meant for template/theme usage.
 *
 * @param int $user The ID of a user previously loaded by {@link loadMemberData()}
 * @param bool $display_custom_fields Whether or not to display custom profile fields
 * @return boolean|array  False if the data wasn't loaded or the loaded data.
 * @throws Exception
 */
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $user_info;
	global $settings;
	static $already_loaded_custom_fields = array();
	static $loadedLanguages = array();

	// If this person's data is already loaded, skip it.
	if (!empty($memberContext[$user]) && !empty($already_loaded_custom_fields[$user]) >= $display_custom_fields)
		return $memberContext[$user];

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		Lang::load('Errors');
		trigger_error(sprintf(Lang::$txt['user_not_loaded'], $user), E_USER_WARNING);
		return false;
	}

	// Well, it's loaded now anyhow.
	$profile = $user_profile[$user];

	// These minimal values are always loaded
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'href' => Config::$scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $profile['real_name']) . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => !$user_info['is_guest'] && ($user_info['id'] == $profile['id_member'] || allowedTo('moderate_forum')),
		'registered' => empty($profile['date_registered']) ? Lang::$txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : $profile['date_registered'],
	);

	// If the set isn't minimal then load the monstrous array.
	if (Utils::$context['loadMemberContext_set'] != 'minimal')
	{
		// Censor everything.
		Lang::censorText($profile['signature']);
		Lang::censorText($profile['personal_text']);

		// Set things up to be used before hand.
		$profile['signature'] = str_replace(array("\n", "\r"), array('<br>', ''), $profile['signature']);
		$profile['signature'] = BBCodeParser::load()->parse($profile['signature'], true, 'sig' . $profile['id_member'], get_signature_allowed_bbc_tags());

		$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
		$profile['icons'] = empty($profile['icons']) ? array('', '') : explode('#', $profile['icons']);
		// Setup the buddy status here (One whole in_array call saved :P)
		$profile['buddy'] = in_array($profile['id_member'], $user_info['buddies']);
		$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

		//We need a little fallback for the membergroup icons. If it doesn't exist in the current theme, fallback to default theme
		if (isset($profile['icons'][1]) && file_exists($settings['actual_theme_dir'] . '/images/membericons/' . $profile['icons'][1])) //icon is set and exists
			$group_icon_url = $settings['images_url'] . '/membericons/' . $profile['icons'][1];
		elseif (isset($profile['icons'][1])) //icon is set and doesn't exist, fallback to default
			$group_icon_url = $settings['default_images_url'] . '/membericons/' . $profile['icons'][1];
		else //not set, bye bye
			$group_icon_url = '';

		// Go the extra mile and load the user's native language name.
		if (empty($loadedLanguages))
			$loadedLanguages = Lang::get();

		// Figure out the new time offset.
		if (!empty($profile['timezone']))
		{
			// Get the offsets from UTC for the server, then for the user.
			$tz_system = new DateTimeZone(Config::$modSettings['default_timezone']);
			$tz_user = new DateTimeZone($profile['timezone']);
			$time_system = new DateTime('now', $tz_system);
			$time_user = new DateTime('now', $tz_user);
			$profile['time_offset'] = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600;
		}
		// We need a time zone.
		else
		{
			if (!empty($profile['time_offset']))
			{
				$tz_system = new DateTimeZone(Config::$modSettings['default_timezone']);
				$time_system = new DateTime('now', $tz_system);

				$profile['timezone'] = @timezone_name_from_abbr('', $tz_system->getOffset($time_system) + $profile['time_offset'] * 3600, (int) $time_system->format('I'));
			}

			if (empty($profile['timezone']))
			{
				$profile['timezone'] = Config::$modSettings['default_timezone'];
				$profile['time_offset'] = 0;
			}
		}

		$memberContext[$user] += array(
			'username_color' => '<span ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['member_name'] . '</span>',
			'name_color' => '<span ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['real_name'] . '</span>',
			'link_color' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $profile['real_name']) . '" ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['real_name'] . '</a>',
			'is_buddy' => $profile['buddy'],
			'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
			'buddies' => $buddy_list,
			'title' => !empty(Config::$modSettings['titlesEnable']) ? $profile['usertitle'] : '',
			'blurb' => $profile['personal_text'],
			'website' => array(
				'title' => $profile['website_title'],
				'url' => $profile['website_url'],
			),
			'birth_date' => empty($profile['birthdate']) ? '1004-01-01' : (substr($profile['birthdate'], 0, 4) === '0004' ? '1004' . substr($profile['birthdate'], 4) : $profile['birthdate']),
			'signature' => $profile['signature'],
			'real_posts' => $profile['posts'],
			'posts' => $profile['posts'] > 500000 ? Lang::$txt['geek'] : Lang::numberFormat($profile['posts']),
			'last_login' => empty($profile['last_login']) ? Lang::$txt['never'] : timeformat($profile['last_login']),
			'last_login_timestamp' => empty($profile['last_login']) ? 0 : $profile['last_login'],
			'ip' => Utils::htmlspecialchars($profile['member_ip']),
			'ip2' => Utils::htmlspecialchars($profile['member_ip2']),
			'online' => array(
				'is_online' => $profile['is_online'],
				'text' => Utils::htmlspecialchars(Lang::$txt[$profile['is_online'] ? 'online' : 'offline']),
				'member_online_text' => sprintf(Lang::$txt[$profile['is_online'] ? 'member_is_online' : 'member_is_offline'], Utils::htmlspecialchars($profile['real_name'])),
				'href' => Config::$scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . Lang::$txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
				'label' => Lang::$txt[$profile['is_online'] ? 'online' : 'offline']
			),
			'language' => !empty($loadedLanguages[$profile['lngfile']]) && !empty($loadedLanguages[$profile['lngfile']]['name']) ? $loadedLanguages[$profile['lngfile']]['name'] : Utils::ucwords(strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))),
			'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
			'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
			'options' => $profile['options'],
			'is_guest' => false,
			'primary_group' => $profile['primary_group'],
			'group' => $profile['member_group'],
			'group_color' => $profile['member_group_color'],
			'group_id' => $profile['id_group'],
			'post_group' => $profile['post_group'],
			'post_group_color' => $profile['post_group_color'],
			'group_icons' => str_repeat('<img src="' . str_replace('$language', Utils::$context['user']['language'], isset($profile['icons'][1]) ? $group_icon_url : '') . '" alt="*">', empty($profile['icons'][0]) || empty($profile['icons'][1]) ? 0 : $profile['icons'][0]),
			'warning' => $profile['warning'],
			'warning_status' => !empty(Config::$modSettings['warning_mute']) && Config::$modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty(Config::$modSettings['warning_moderate']) && Config::$modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty(Config::$modSettings['warning_watch']) && Config::$modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : (''))),
			'local_time' => timeformat(time(), false, $profile['timezone']),
			'custom_fields' => array(),
		);
	}

	// If the set isn't minimal then load their avatar as well.
	if (Utils::$context['loadMemberContext_set'] != 'minimal')
	{
		$avatarData = set_avatar_data(array(
			'filename' => $profile['filename'],
			'avatar' => $profile['avatar'],
			'email' => $profile['email_address'],
		));

		if (!empty($avatarData['image']))
			$memberContext[$user]['avatar'] = $avatarData;
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty(Config::$modSettings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();

		if (!isset(Utils::$context['display_fields']))
			Utils::$context['display_fields'] = Utils::jsonDecode(Config::$modSettings['displayFields'], true);

		foreach (Utils::$context['display_fields'] as $custom)
		{
			if (!isset($custom['col_name']) || trim($custom['col_name']) == '' || empty($profile['options'][$custom['col_name']]))
				continue;

			$value = $profile['options'][$custom['col_name']];

			$fieldOptions = array();
			$currentKey = 0;

			// Create a key => value array for multiple options fields
			if (!empty($custom['options']))
				foreach ($custom['options'] as $k => $v)
				{
					$fieldOptions[] = $v;
					if (empty($currentKey))
						$currentKey = $v == $value ? $k : 0;
				}

			// BBC?
			if ($custom['bbc'])
				$value = BBCodeParser::load()->parse($value);

			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? Lang::$txt['yes'] : Lang::$txt['no'];

			// Enclosing the user input within some other text?
			$simple_value = $value;
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => Config::$scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => Lang::tokenTxtReplace($value),
					'{KEY}' => $currentKey,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => Lang::tokenTxtReplace(!empty($custom['title']) ? $custom['title'] : $custom['col_name']),
				'col_name' => Lang::tokenTxtReplace($custom['col_name']),
				'value' => un_htmlspecialchars(Lang::tokenTxtReplace($value)),
				'simple' => Lang::tokenTxtReplace($simple_value),
				'raw' => $profile['options'][$custom['col_name']],
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	call_integration_hook('integrate_member_context', array(&$memberContext[$user], $user, $display_custom_fields));

	$already_loaded_custom_fields[$user] = !empty($already_loaded_custom_fields[$user]) | $display_custom_fields;

	return $memberContext[$user];
}

/**
 * Loads the user's custom profile fields
 *
 * @param integer|array $users A single user ID or an array of user IDs
 * @param string|array $params Either a string or an array of strings with profile field names
 * @return array|boolean An array of data about the fields and their values or false if nothing was loaded
 */
function loadMemberCustomFields($users, $params)
{
	global $settings;

	// Do not waste my time...
	if (empty($users) || empty($params))
		return false;

	// Make sure it's an array.
	$users = (array) array_unique($users);
	$params = (array) array_unique($params);
	$return = array();

	$request = Db::$db->query('', '
		SELECT c.id_field, c.col_name, c.field_name, c.field_desc, c.field_type, c.field_order, c.field_length, c.field_options, c.mask, show_reg,
		c.show_display, c.show_profile, c.private, c.active, c.bbc, c.can_search, c.default_value, c.enclose, c.placement, t.variable, t.value, t.id_member
		FROM {db_prefix}themes AS t
			LEFT JOIN {db_prefix}custom_fields AS c ON (c.col_name = t.variable)
		WHERE id_member IN ({array_int:loaded_ids})
			AND variable IN ({array_string:params})
		ORDER BY field_order',
		array(
			'loaded_ids' => $users,
			'params' => $params,
		)
	);

	while ($row = Db::$db->fetch_assoc($request))
	{
		$fieldOptions = array();
		$currentKey = 0;
		$row['field_name'] = Lang::tokenTxtReplace($row['field_name']);
		$row['field_desc'] = Lang::tokenTxtReplace($row['field_desc']);

		// Create a key => value array for multiple options fields
		if (!empty($row['field_options']))
			foreach (explode(',', $row['field_options']) as $k => $v)
			{
				$fieldOptions[] = $v;
				if (empty($currentKey))
					$currentKey = $v == $row['value'] ? $k : 0;
			}

		// BBC?
		if (!empty($row['bbc']))
			$row['value'] = BBCodeParser::load()->parse($row['value']);

		// ... or checkbox?
		elseif (isset($row['type']) && $row['type'] == 'check')
			$row['value'] = !empty($row['value']) ? Lang::$txt['yes'] : Lang::$txt['no'];

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']))
			$row['value'] = strtr($row['enclose'], array(
				'{SCRIPTURL}' => Config::$scripturl,
				'{IMAGES_URL}' => $settings['images_url'],
				'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
				'{INPUT}' => un_htmlspecialchars($row['value']),
				'{KEY}' => $currentKey,
			));

		// Send a simple array if there is just 1 param
		if (count($params) == 1)
			$return[$row['id_member']] = $row;

		// More than 1? knock yourself out...
		else
		{
			if (!isset($return[$row['id_member']]))
				$return[$row['id_member']] = array();

			$return[$row['id_member']][$row['variable']] = $row;
		}
	}

	Db::$db->free_result($request);

	return !empty($return) ? $return : false;
}

/**
 * Load a theme, by ID.
 *
 * @param int $id_theme The ID of the theme to load
 * @param bool $initialize Whether or not to initialize a bunch of theme-related variables/settings
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info;
	global $settings, $options, $board;

	if (empty($id_theme))
	{
		// The theme was specified by the board.
		if (!empty($board_info['theme']))
			$id_theme = $board_info['theme'];
		// The theme is the forum's default.
		else
			$id_theme = Config::$modSettings['theme_guests'];

		// Sometimes the user can choose their own theme.
		if (!empty(Config::$modSettings['theme_allow']) || allowedTo('admin_forum'))
		{
			// The theme was specified by REQUEST.
			if (!empty($_REQUEST['theme']) && (allowedTo('admin_forum') || in_array($_REQUEST['theme'], explode(',', Config::$modSettings['knownThemes']))))
			{
				$id_theme = (int) $_REQUEST['theme'];
				$_SESSION['id_theme'] = $id_theme;
			}
			// The theme was specified by REQUEST... previously.
			elseif (!empty($_SESSION['id_theme']))
				$id_theme = (int) $_SESSION['id_theme'];
			// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
			elseif (!empty($user_info['theme']))
				$id_theme = $user_info['theme'];
		}

		// Verify the id_theme... no foul play.
		// Always allow the board specific theme, if they are overriding.
		if (!empty($board_info['theme']) && $board_info['override_theme'])
			$id_theme = $board_info['theme'];
		elseif (!empty(Config::$modSettings['enableThemes']))
		{
			$themes = explode(',', Config::$modSettings['enableThemes']);
			if (!in_array($id_theme, $themes))
				$id_theme = Config::$modSettings['theme_guests'];
			else
				$id_theme = (int) $id_theme;
		}
	}

	// Allow mod authors the option to override the theme id for custom page themes
	call_integration_hook('integrate_pre_load_theme', array(&$id_theme));

	// We already load the basic stuff?
	if (empty($settings['theme_id']) || $settings['theme_id'] != $id_theme)
	{
		$member = empty($user_info['id']) ? -1 : $user_info['id'];

		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2 && ($temp = CacheApi::get('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > Config::$modSettings['settings_updated'])
		{
			$themeData = $temp;
			$flag = true;
		}
		elseif (($temp = CacheApi::get('theme_settings-' . $id_theme, 90)) != null && time() - 60 > Config::$modSettings['settings_updated'])
			$themeData = $temp + array($member => array());
		else
			$themeData = array(-1 => array(), 0 => array(), $member => array());

		if (empty($flag))
		{
			// Load variables from the current or default theme, global or this user's.
			$result = Db::$db->query('', '
				SELECT variable, value, id_member, id_theme
				FROM {db_prefix}themes
				WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
					AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)') . '
				ORDER BY id_theme asc',
				array(
					'id_theme' => $id_theme,
					'id_member' => $member,
				)
			);
			// Pick between $settings and $options depending on whose data it is.
			foreach (Db::$db->fetch_all($result) as $row)
			{
				// There are just things we shouldn't be able to change as members.
				if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'number_recent_posts', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_layers', 'theme_templates', 'theme_url')))
					continue;

				// If this is the theme_dir of the default theme, store it.
				if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
					$themeData[0]['default_' . $row['variable']] = $row['value'];

				// If this isn't set yet, is a theme option, or is not the default theme..
				if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
					$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
			}
			Db::$db->free_result($result);

			if (!empty($themeData[-1]))
				foreach ($themeData[-1] as $k => $v)
				{
					if (!isset($themeData[$member][$k]))
						$themeData[$member][$k] = $v;
				}

			if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
				CacheApi::put('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
			// Only if we didn't already load that part of the cache...
			elseif (!isset($temp))
				CacheApi::put('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
		}

		$settings = $themeData[0];
		$options = $themeData[$member];

		$settings['theme_id'] = $id_theme;

		$settings['actual_theme_url'] = $settings['theme_url'];
		$settings['actual_images_url'] = $settings['images_url'];
		$settings['actual_theme_dir'] = $settings['theme_dir'];

		$settings['template_dirs'] = array();
		// This theme first.
		$settings['template_dirs'][] = $settings['theme_dir'];

		// Based on theme (if there is one).
		if (!empty($settings['base_theme_dir']))
			$settings['template_dirs'][] = $settings['base_theme_dir'];

		// Lastly the default theme.
		if ($settings['theme_dir'] != $settings['default_theme_dir'])
			$settings['template_dirs'][] = $settings['default_theme_dir'];
	}

	if (!$initialize)
		return;

	// Perhaps we've changed the agreement or privacy policy? Only redirect if:
	// 1. They're not a guest or admin
	// 2. This isn't called from SSI
	// 3. This isn't an XML request
	// 4. They're not trying to do any of the following actions:
	// 4a. View or accept the agreement and/or policy
	// 4b. Login or logout
	// 4c. Get a feed (RSS, ATOM, etc.)
	$agreement_actions = array(
		'agreement' => true,
		'acceptagreement' => true,
		'login2' => true,
		'logintfa' => true,
		'logout' => true,
		'pm' => array('sa' => array('popup')),
		'profile' => array('area' => array('popup', 'alerts_popup')),
		'xmlhttp' => true,
		'.xml' => true,
	);
	if (empty($user_info['is_guest']) && empty($user_info['is_admin']) && SMF != 'SSI' && !isset($_REQUEST['xml']) && !is_filtered_request($agreement_actions, 'action'))
	{
		require_once(Config::$sourcedir . '/Agreement.php');
		$can_accept_agreement = !empty(Config::$modSettings['requireAgreement']) && canRequireAgreement();
		$can_accept_privacy_policy = !empty(Config::$modSettings['requirePolicyAgreement']) && canRequirePrivacyPolicy();

		if ($can_accept_agreement || $can_accept_privacy_policy)
			redirectexit('action=agreement');
	}

	// Check to see if we're forcing SSL
	if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) &&
		!httpsOn() && SMF != 'SSI')
	{
		if (isset($_GET['sslRedirect']))
		{
			Lang::load('Errors');
			fatal_lang_error('login_ssl_required', false);
		}

		redirectexit(strtr($_SERVER['REQUEST_URL'], array('http://' => 'https://')) . (strpos($_SERVER['REQUEST_URL'], '?') > 0 ? ';' : '?') . 'sslRedirect');
	}

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = httpsOn() ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename(Config::$scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}
	if (isset($detected_url) && $detected_url != Config::$boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses.
		if (!empty(Config::$modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', Config::$modSettings['forum_alias_urls']);

			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == Config::$boardurl && (empty($_GET) || count($_GET) == 1) && SMF != 'SSI')
		{
			// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
			if (empty($_GET))
				redirectexit('wwwRedirect');
			else
			{
				$k = key($_GET);
				$v = current($_GET);

				if ($k != 'wwwRedirect')
					redirectexit('wwwRedirect;' . $k . '=' . $v);
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == Config::$boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			// Caching is good ;).
			$oldurl = Config::$boardurl;

			// Fix Config::$boardurl and Config::$scripturl.
			Config::$boardurl = $detected_url;
			Config::$scripturl = strtr(Config::$scripturl, array($oldurl => Config::$boardurl));
			$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => Config::$boardurl));

			// Fix the theme urls...
			$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => Config::$boardurl));
			$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => Config::$boardurl));
			$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => Config::$boardurl));
			$settings['images_url'] = strtr($settings['images_url'], array($oldurl => Config::$boardurl));
			$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => Config::$boardurl));
			$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => Config::$boardurl));

			// And just a few mod settings :).
			Config::$modSettings['smileys_url'] = strtr(Config::$modSettings['smileys_url'], array($oldurl => Config::$boardurl));
			Config::$modSettings['avatar_url'] = strtr(Config::$modSettings['avatar_url'], array($oldurl => Config::$boardurl));
			Config::$modSettings['custom_avatar_url'] = strtr(Config::$modSettings['custom_avatar_url'], array($oldurl => Config::$boardurl));

			// Clean up after loadBoard().
			if (isset($board_info['moderators']))
			{
				foreach ($board_info['moderators'] as $k => $dummy)
				{
					$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => Config::$boardurl));
					$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . Config::$boardurl));
				}
			}
			foreach (Utils::$context['linktree'] as $k => $dummy)
				Utils::$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => Config::$boardurl));
		}
	}
	// Set up the contextual user array.
	if (!empty($user_info))
	{
		Utils::$context['user'] = array(
			'id' => $user_info['id'],
			'is_logged' => !$user_info['is_guest'],
			'is_guest' => &$user_info['is_guest'],
			'is_admin' => &$user_info['is_admin'],
			'is_mod' => &$user_info['is_mod'],
			// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
			'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || (Config::$modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
			'name' => $user_info['username'],
			'language' => $user_info['language'],
			'email' => $user_info['email'],
			'ignoreusers' => $user_info['ignoreusers'],
		);
		if (!Utils::$context['user']['is_guest'])
			Utils::$context['user']['name'] = $user_info['name'];
		elseif (Utils::$context['user']['is_guest'] && !empty(Lang::$txt['guest_title']))
			Utils::$context['user']['name'] = Lang::$txt['guest_title'];

		// Determine the current smiley set.
		$smiley_sets_known = explode(',', Config::$modSettings['smiley_sets_known']);
		$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], $smiley_sets_known) && $user_info['smiley_set'] != 'none') || empty(Config::$modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : Config::$modSettings['smiley_sets_default']) : $user_info['smiley_set'];
		Utils::$context['user']['smiley_set'] = $user_info['smiley_set'];
	}
	else
	{
		// What to do when there is no $user_info (e.g., an error very early in the login process)
		Utils::$context['user'] = array(
			'id' => -1,
			'is_logged' => false,
			'is_guest' => true,
			'is_mod' => false,
			'can_mod' => false,
			'name' => Lang::$txt['guest_title'],
			'language' => Lang::$default,
			'email' => '',
			'ignoreusers' => array(),
		);
		// Note we should stuff $user_info with some guest values also...
		$user_info = array(
			'id' => 0,
			'is_guest' => true,
			'is_admin' => false,
			'is_mod' => false,
			'username' => Lang::$txt['guest_title'],
			'language' => Lang::$default,
			'email' => '',
			'smiley_set' => '',
			'permissions' => array(),
			'groups' => array(),
			'ignoreusers' => array(),
			'possibly_robot' => true,
			'time_offset' => 0,
			'timezone' => Config::$modSettings['default_timezone'],
			'time_format' => Config::$modSettings['time_format'],
		);
	}

	// Some basic information...
	if (!isset(Utils::$context['html_headers']))
		Utils::$context['html_headers'] = '';
	if (!isset(Utils::$context['javascript_files']))
		Utils::$context['javascript_files'] = array();
	if (!isset(Utils::$context['css_files']))
		Utils::$context['css_files'] = array();
	if (!isset(Utils::$context['css_header']))
		Utils::$context['css_header'] = array();
	if (!isset(Utils::$context['javascript_inline']))
		Utils::$context['javascript_inline'] = array('standard' => array(), 'defer' => array());
	if (!isset(Utils::$context['javascript_vars']))
		Utils::$context['javascript_vars'] = array();

	Utils::$context['login_url'] = Config::$scripturl . '?action=login2';
	Utils::$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	Utils::$context['session_var'] = $_SESSION['session_var'];
	Utils::$context['session_id'] = $_SESSION['session_value'];
	Utils::$context['forum_name'] = Config::$mbname;
	Utils::$context['forum_name_html_safe'] = Utils::htmlspecialchars(Utils::$context['forum_name']);
	Utils::$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? '' : Utils::htmlspecialchars($settings['header_logo_url']);
	Utils::$context['current_action'] = isset($_REQUEST['action']) ? Utils::htmlspecialchars($_REQUEST['action']) : null;
	Utils::$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	Utils::$context['can_register'] = empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] != 3;
	if (isset(Config::$modSettings['load_average']))
		Utils::$context['load_average'] = Config::$modSettings['load_average'];

	// Detect the browser. This is separated out because it's also used in attachment downloads
	BrowserDetector::call();

	// Set the top level linktree up.
	// Note that if we're dealing with certain very early errors (e.g., login) the linktree might not be set yet...
	if (empty(Utils::$context['linktree']))
		Utils::$context['linktree'] = array();
	array_unshift(Utils::$context['linktree'], array(
		'url' => Config::$scripturl,
		'name' => Utils::$context['forum_name_html_safe']
	));

	// This allows sticking some HTML on the page output - useful for controls.
	Utils::$context['insert_after_template'] = '';

	$simpleActions = array(
		'findmember',
		'helpadmin',
		'printpage',
	);

	// Parent action => array of areas
	$simpleAreas = array(
		'profile' => array('popup', 'alerts_popup',),
	);

	// Parent action => array of subactions
	$simpleSubActions = array(
		'pm' => array('popup',),
		'signup' => array('usernamecheck'),
	);

	// Extra params like ;preview ;js, etc.
	$extraParams = array(
		'preview',
		'splitjs',
	);

	// Actions that specifically uses XML output.
	$xmlActions = array(
		'quotefast',
		'jsmodify',
		'xmlhttp',
		'post2',
		'suggest',
		'stats',
		'notifytopic',
		'notifyboard',
	);

	call_integration_hook('integrate_simple_actions', array(&$simpleActions, &$simpleAreas, &$simpleSubActions, &$extraParams, &$xmlActions));

	Utils::$context['simple_action'] = in_array(Utils::$context['current_action'], $simpleActions) ||
		(isset($simpleAreas[Utils::$context['current_action']]) && isset($_REQUEST['area']) && in_array($_REQUEST['area'], $simpleAreas[Utils::$context['current_action']])) ||
		(isset($simpleSubActions[Utils::$context['current_action']]) && in_array(Utils::$context['current_subaction'], $simpleSubActions[Utils::$context['current_action']]));

	// See if theres any extra param to check.
	$requiresXML = false;
	foreach ($extraParams as $key => $extra)
		if (isset($_REQUEST[$extra]))
			$requiresXML = true;

	// Output is fully XML, so no need for the index template.
	if (isset($_REQUEST['xml']) && (in_array(Utils::$context['current_action'], $xmlActions) || $requiresXML))
	{
		Lang::load('index+Modifications');
		loadTemplate('Xml');
		Utils::$context['template_layers'] = array();
	}

	// These actions don't require the index template at all.
	elseif (!empty(Utils::$context['simple_action']))
	{
		Lang::load('index+Modifications');
		Utils::$context['template_layers'] = array();
	}

	else
	{
		// Custom templates to load, or just default?
		if (isset($settings['theme_templates']))
			$templates = explode(',', $settings['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', array_merge($templates, array('Modifications')));
		Lang::load($required_files, '', false);

		// Custom template layers?
		if (isset($settings['theme_layers']))
			Utils::$context['template_layers'] = explode(',', $settings['theme_layers']);
		else
			Utils::$context['template_layers'] = array('html', 'body');
	}

	// Initialize the theme.
	loadSubTemplate('init', 'ignore');

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty(Lang::$txt['time_format']))
		$user_info['time_format'] = Lang::$txt['time_format'];

	// Set the character set from the template.
	Utils::$context['character_set'] = empty(Config::$modSettings['global_character_set']) ? Lang::$txt['lang_character_set'] : Config::$modSettings['global_character_set'];
	Utils::$context['right_to_left'] = !empty(Lang::$txt['lang_rtl']);

	// Guests may still need a name.
	if (Utils::$context['user']['is_guest'] && empty(Utils::$context['user']['name']))
		Utils::$context['user']['name'] = Lang::$txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		Lang::load('ThemeStrings', '', false);

	// Make a special URL for the language.
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty(Lang::$txt['image_lang']) ? Lang::$txt['image_lang'] : $user_info['language']);

	// And of course, let's load the default CSS file.
	loadCSSFile('index.css', array('minimize' => true, 'order_pos' => 1), 'smf_index');

	// Here is my luvly Responsive CSS
	loadCSSFile('responsive.css', array('force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9000), 'smf_responsive');

	if (Utils::$context['right_to_left'])
		loadCSSFile('rtl.css', array('order_pos' => 4000), 'smf_rtl');

	// We allow theme variants, because we're cool.
	Utils::$context['theme_variant'] = '';
	Utils::$context['theme_variant_url'] = '';
	if (!empty($settings['theme_variants']))
	{
		// Overriding - for previews and that ilk.
		if (!empty($_REQUEST['variant']))
			$_SESSION['id_variant'] = $_REQUEST['variant'];
		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
			Utils::$context['theme_variant'] = !empty($_SESSION['id_variant']) && in_array($_SESSION['id_variant'], $settings['theme_variants']) ? $_SESSION['id_variant'] : (!empty($options['theme_variant']) && in_array($options['theme_variant'], $settings['theme_variants']) ? $options['theme_variant'] : '');
		// If not a user variant, select the default.
		if (Utils::$context['theme_variant'] == '' || !in_array(Utils::$context['theme_variant'], $settings['theme_variants']))
			Utils::$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants']) ? $settings['default_variant'] : $settings['theme_variants'][0];

		// Do this to keep things easier in the templates.
		Utils::$context['theme_variant'] = '_' . Utils::$context['theme_variant'];
		Utils::$context['theme_variant_url'] = Utils::$context['theme_variant'] . '/';

		if (!empty(Utils::$context['theme_variant']))
		{
			loadCSSFile('index' . Utils::$context['theme_variant'] . '.css', array('order_pos' => 300), 'smf_index' . Utils::$context['theme_variant']);
			if (Utils::$context['right_to_left'])
				loadCSSFile('rtl' . Utils::$context['theme_variant'] . '.css', array('order_pos' => 4200), 'smf_rtl' . Utils::$context['theme_variant']);
		}
	}

	// Let's be compatible with old themes!
	if (!function_exists('template_html_above') && in_array('html', Utils::$context['template_layers']))
		Utils::$context['template_layers'] = array('main');

	Utils::$context['tabindex'] = 1;

	// Compatibility.
	if (!isset($settings['theme_version']))
		Config::$modSettings['memberCount'] = Config::$modSettings['totalMembers'];

	// Default JS variables for use in every theme
	Utils::$context['javascript_vars'] = array(
		'smf_theme_url' => '"' . $settings['theme_url'] . '"',
		'smf_default_theme_url' => '"' . $settings['default_theme_url'] . '"',
		'smf_images_url' => '"' . $settings['images_url'] . '"',
		'smf_smileys_url' => '"' . Config::$modSettings['smileys_url'] . '"',
		'smf_smiley_sets' => '"' . Config::$modSettings['smiley_sets_known'] . '"',
		'smf_smiley_sets_default' => '"' . Config::$modSettings['smiley_sets_default'] . '"',
		'smf_avatars_url' => '"' . Config::$modSettings['avatar_url'] . '"',
		'smf_scripturl' => '"' . Config::$scripturl . '"',
		'smf_iso_case_folding' => Utils::$context['server']['iso_case_folding'] ? 'true' : 'false',
		'smf_charset' => '"' . Utils::$context['character_set'] . '"',
		'smf_session_id' => '"' . Utils::$context['session_id'] . '"',
		'smf_session_var' => '"' . Utils::$context['session_var'] . '"',
		'smf_member_id' => Utils::$context['user']['id'],
		'ajax_notification_text' => JavaScriptEscape(Lang::$txt['ajax_in_progress']),
		'help_popup_heading_text' => JavaScriptEscape(Lang::$txt['help_popup']),
		'banned_text' => JavaScriptEscape(sprintf(Lang::$txt['your_ban'], Utils::$context['user']['name'])),
		'smf_txt_expand' => JavaScriptEscape(Lang::$txt['code_expand']),
		'smf_txt_shrink' => JavaScriptEscape(Lang::$txt['code_shrink']),
		'smf_collapseAlt' => JavaScriptEscape(Lang::$txt['hide']),
		'smf_expandAlt' => JavaScriptEscape(Lang::$txt['show']),
		'smf_quote_expand' => !empty(Config::$modSettings['quote_expand']) ? Config::$modSettings['quote_expand'] : 'false',
		'allow_xhjr_credentials' => !empty(Config::$modSettings['allow_cors_credentials']) ? 'true' : 'false',
	);

	// Add the JQuery library to the list of files to load.
	$jQueryUrls = array ('cdn' => 'https://ajax.googleapis.com/ajax/libs/jquery/'. JQUERY_VERSION . '/jquery.min.js', 'jquery_cdn' => 'https://code.jquery.com/jquery-'. JQUERY_VERSION . '.min.js', 'microsoft_cdn' => 'https://ajax.aspnetcdn.com/ajax/jQuery/jquery-'. JQUERY_VERSION . '.min.js');

	if (isset(Config::$modSettings['jquery_source']) && array_key_exists(Config::$modSettings['jquery_source'], $jQueryUrls))
		loadJavaScriptFile($jQueryUrls[Config::$modSettings['jquery_source']], array('external' => true, 'seed' => false), 'smf_jquery');

	elseif (isset(Config::$modSettings['jquery_source']) && Config::$modSettings['jquery_source'] == 'local')
		loadJavaScriptFile('jquery-' . JQUERY_VERSION . '.min.js', array('seed' => false), 'smf_jquery');

	elseif (isset(Config::$modSettings['jquery_source'], Config::$modSettings['jquery_custom']) && Config::$modSettings['jquery_source'] == 'custom')
		loadJavaScriptFile(Config::$modSettings['jquery_custom'], array('external' => true, 'seed' => false), 'smf_jquery');

	// Fall back to the forum default
	else
		loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js', array('external' => true, 'seed' => false), 'smf_jquery');

	// Queue our JQuery plugins!
	loadJavaScriptFile('smf_jquery_plugins.js', array('minimize' => true), 'smf_jquery_plugins');
	if (!$user_info['is_guest'])
	{
		loadJavaScriptFile('jquery.custom-scrollbar.js', array('minimize' => true), 'smf_jquery_scrollbar');
		loadCSSFile('jquery.custom-scrollbar.css', array('force_current' => false, 'validate' => true), 'smf_scrollbar');
	}

	// script.js and theme.js, always required, so always add them! Makes index.template.php cleaner and all.
	loadJavaScriptFile('script.js', array('defer' => false, 'minimize' => true), 'smf_script');
	loadJavaScriptFile('theme.js', array('minimize' => true), 'smf_theme');

	// And we should probably trigger the cron too.
	if (empty(Config::$modSettings['cron_is_real_cron']))
	{
		$ts = time();
		$ts -= $ts % 15;
		addInlineJavaScript('
	function triggerCron()
	{
		$.get(' . JavaScriptEscape(Config::$boardurl) . ' + "/cron.php?ts=' . $ts . '");
	}
	window.setTimeout(triggerCron, 1);', true);

		// Robots won't normally trigger cron.php, so for them run the scheduled tasks directly.
		if (BrowserDetector::isBrowser('possibly_robot') && (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() || (!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron']))))
		{
			require_once($sourcedir . '/ScheduledTasks.php');

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
				AutoTask();
			else
				ReduceMailQueue();
		}
	}

	// Filter out the restricted boards from the linktree
	if (!$user_info['is_admin'] && !empty($board))
	{
		foreach (Utils::$context['linktree'] as $k => $element)
		{
			if (!empty($element['groups']) &&
				(count(array_intersect($user_info['groups'], $element['groups'])) == 0 ||
					(!empty(Config::$modSettings['deny_boards_access']) && count(array_intersect($user_info['groups'], $element['deny_groups'])) != 0)))
			{
				Utils::$context['linktree'][$k]['name'] = Lang::$txt['restricted_board'];
				Utils::$context['linktree'][$k]['extra_before'] = '<i>';
				Utils::$context['linktree'][$k]['extra_after'] = '</i>';
				unset(Utils::$context['linktree'][$k]['url']);
			}
		}
	}

	// Any files to include at this point?
	if (!empty(Config::$modSettings['integrate_theme_include']))
	{
		$theme_includes = explode(',', Config::$modSettings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call load theme integration functions.
	call_integration_hook('integrate_load_theme');

	// We are ready to go.
	Utils::$context['theme_loaded'] = true;
}

/**
 * Load a template - if the theme doesn't include it, use the default.
 * What this function does:
 *  - loads a template file with the name template_name from the current, default, or base theme.
 *  - detects a wrong default theme directory and tries to work around it.
 *
 * @uses template_include() to include the file.
 * @param string $template_name The name of the template to load
 * @param array|string $style_sheets The name of a single stylesheet or an array of names of stylesheets to load
 * @param bool $fatal If true, dies with an error message if the template cannot be found
 * @return boolean Whether or not the template was loaded
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	global $settings;

	// Do any style sheets first, cause we're easy with those.
	if (!empty($style_sheets))
	{
		if (!is_array($style_sheets))
			$style_sheets = array($style_sheets);

		foreach ($style_sheets as $sheet)
			loadCSSFile($sheet . '.css', array(), $sheet);
	}

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($settings['template_dirs'] as $template_dir)
	{
		if (file_exists($template_dir . '/' . $template_name . '.template.php'))
		{
			$loaded = true;
			template_include($template_dir . '/' . $template_name . '.template.php', true);
			break;
		}
	}

	if ($loaded)
	{
		if (Config::$db_show_debug === true)
			Utils::$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
	elseif (!file_exists($settings['default_theme_dir']) && file_exists(Config::$boarddir . '/Themes/default'))
	{
		$settings['default_theme_dir'] = Config::$boarddir . '/Themes/default';
		$settings['template_dirs'][] = $settings['default_theme_dir'];

		if (!empty(Utils::$context['user']['is_admin']) && !isset($_GET['th']))
		{
			Lang::load('Errors');
			echo '
<div class="alert errorbox">
	<a href="', Config::$scripturl . '?action=admin;area=theme;sa=list;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], '" class="alert">', Lang::$txt['theme_dir_wrong'], '</a>
</div>';
		}

		loadTemplate($template_name);
	}
	// Cause an error otherwise.
	elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal)
		fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
	elseif ($fatal)
		die(log_error(sprintf(isset(Lang::$txt['theme_template_error']) ? Lang::$txt['theme_template_error'] : 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
	else
		return false;
}

/**
 * Load a sub-template.
 * What it does:
 * 	- loads the sub template specified by sub_template_name, which must be in an already-loaded template.
 *  - if ?debug is in the query string, shows administrators a marker after every sub template
 *	for debugging purposes.
 *
 * @todo get rid of reading $_REQUEST directly
 *
 * @param string $sub_template_name The name of the sub-template to load
 * @param bool $fatal Whether to die with an error if the sub-template can't be loaded
 */
function loadSubTemplate($sub_template_name, $fatal = false)
{
	if (Config::$db_show_debug === true)
		Utils::$context['debug']['sub_templates'][] = $sub_template_name;

	// Figure out what the template function is named.
	$theme_function = 'template_' . $sub_template_name;
	if (function_exists($theme_function))
		$theme_function();
	elseif ($fatal === false)
		fatal_lang_error('theme_template_error', 'template', array((string) $sub_template_name));
	elseif ($fatal !== 'ignore')
		die(log_error(sprintf(isset(Lang::$txt['theme_template_error']) ? Lang::$txt['theme_template_error'] : 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));

	// Are we showing debugging for templates?  Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && !in_array($sub_template_name, array('init', 'main_below')) && ob_get_length() > 0 && !isset($_REQUEST['xml']))
	{
		echo '
<div class="noticebox">---- ', $sub_template_name, ' ends ----</div>';
	}
}

/**
 * Add a CSS file for output later
 *
 * @param string $fileName The name of the file to load
 * @param array $params An array of parameters
 * Keys are the following:
 * 	- ['external'] (true/false): define if the file is a externally located file. Needs to be set to true if you are loading an external file
 * 	- ['default_theme'] (true/false): force use of default theme url
 * 	- ['force_current'] (true/false): if this is false, we will attempt to load the file from the default theme if not found in the current theme
 *  - ['validate'] (true/false): if true script will validate the local file exists
 *  - ['rtl'] (string): additional file to load in RTL mode
 *  - ['seed'] (true/false/string): if true or null, use cache stale, false do not, or used a supplied string
 *  - ['minimize'] boolean to add your file to the main minimized file. Useful when you have a file thats loaded everywhere and for everyone.
 *  - ['order_pos'] int define the load order, when not define it's loaded in the middle, before index.css = -500, after index.css = 500, middle = 3000, end (i.e. after responsive.css) = 10000
 *  - ['attributes'] array extra attributes to add to the element
 * @param string $id An ID to stick on the end of the filename for caching purposes
 */
function loadCSSFile($fileName, $params = array(), $id = '')
{
	global $settings;

	if (empty(Utils::$context['css_files_order']))
		Utils::$context['css_files_order'] = array();

	$params['seed'] = (!array_key_exists('seed', $params) || (array_key_exists('seed', $params) && $params['seed'] === true)) ?
		(array_key_exists('browser_cache', Utils::$context) ? Utils::$context['browser_cache'] : '') :
		(is_string($params['seed']) ? '?' . ltrim($params['seed'], '?') : '');
	$params['force_current'] = isset($params['force_current']) ? $params['force_current'] : false;
	$themeRef = !empty($params['default_theme']) ? 'default_theme' : 'theme';
	$params['minimize'] = isset($params['minimize']) ? $params['minimize'] : true;
	$params['external'] = isset($params['external']) ? $params['external'] : false;
	$params['validate'] = isset($params['validate']) ? $params['validate'] : true;
	$params['order_pos'] = isset($params['order_pos']) ? (int) $params['order_pos'] : 3000;
	$params['attributes'] = isset($params['attributes']) ? $params['attributes'] : array();

	// Account for shorthand like admin.css?alp21 filenames
	$id = (empty($id) ? strtr(str_replace('.css', '', basename($fileName)), '?', '_') : $id) . '_css';

	$fileName = str_replace(pathinfo($fileName, PATHINFO_EXTENSION), strtok(pathinfo($fileName, PATHINFO_EXTENSION), '?'), $fileName);

	// Is this a local file?
	if (empty($params['external']))
	{
		// Are we validating the the file exists?
		if (!empty($params['validate']) && ($mtime = @filemtime($settings[$themeRef . '_dir'] . '/css/' . $fileName)) === false)
		{
			// Maybe the default theme has it?
			if ($themeRef === 'theme' && !$params['force_current'] && ($mtime = @filemtime($settings['default_theme_dir'] . '/css/' . $fileName) !== false))
			{
				$fileUrl = $settings['default_theme_url'] . '/css/' . $fileName;
				$filePath = $settings['default_theme_dir'] . '/css/' . $fileName;
			}
			else
			{
				$fileUrl = false;
				$filePath = false;
			}
		}
		else
		{
			$fileUrl = $settings[$themeRef . '_url'] . '/css/' . $fileName;
			$filePath = $settings[$themeRef . '_dir'] . '/css/' . $fileName;
			$mtime = @filemtime($filePath);
		}
	}
	// An external file doesn't have a filepath. Mock one for simplicity.
	else
	{
		$fileUrl = $fileName;
		$filePath = $fileName;

		// Always turn these off for external files.
		$params['minimize'] = false;
		$params['seed'] = false;
	}

	$mtime = empty($mtime) ? 0 : $mtime;

	// Add it to the array for use in the template
	if (!empty($fileName) && !empty($fileUrl))
	{
		// find a free number/position
		while (isset(Utils::$context['css_files_order'][$params['order_pos']]))
			$params['order_pos']++;
		Utils::$context['css_files_order'][$params['order_pos']] = $id;

		Utils::$context['css_files'][$id] = array('fileUrl' => $fileUrl, 'filePath' => $filePath, 'fileName' => $fileName, 'options' => $params, 'mtime' => $mtime);
	}

	if (!empty(Utils::$context['right_to_left']) && !empty($params['rtl']))
		loadCSSFile($params['rtl'], array_diff_key($params, array('rtl' => 0)));

	if ($mtime > Config::$modSettings['browser_cache'])
		Config::updateModSettings(array('browser_cache' => $mtime));
}

/**
 * Add a block of inline css code to be executed later
 *
 * - only use this if you have to, generally external css files are better, but for very small changes
 *   or for scripts that require help from PHP/whatever, this can be useful.
 * - all code added with this function is added to the same <style> tag so do make sure your css is valid!
 *
 * @param string $css Some css code
 * @return void|bool Adds the CSS to the Utils::$context['css_header'] array or returns if no CSS is specified
 */
function addInlineCss($css)
{
	// Gotta add something...
	if (empty($css))
		return false;

	Utils::$context['css_header'][] = $css;
}

/**
 * Add a Javascript file for output later
 *
 * @param string $fileName The name of the file to load
 * @param array $params An array of parameter info
 * Keys are the following:
 * 	- ['external'] (true/false): define if the file is a externally located file. Needs to be set to true if you are loading an external file
 * 	- ['default_theme'] (true/false): force use of default theme url
 * 	- ['defer'] (true/false): define if the file should load in <head> or before the closing <html> tag
 * 	- ['force_current'] (true/false): if this is false, we will attempt to load the file from the
 *	default theme if not found in the current theme
 *	- ['async'] (true/false): if the script should be loaded asynchronously (HTML5)
 *  - ['validate'] (true/false): if true script will validate the local file exists
 *  - ['seed'] (true/false/string): if true or null, use cache stale, false do not, or used a supplied string
 *  - ['minimize'] boolean to add your file to the main minimized file. Useful when you have a file thats loaded everywhere and for everyone.
 *  - ['attributes'] array extra attributes to add to the element
 *
 * @param string $id An ID to stick on the end of the filename
 */
function loadJavaScriptFile($fileName, $params = array(), $id = '')
{
	global $settings;

	$params['seed'] = (!array_key_exists('seed', $params) || (array_key_exists('seed', $params) && $params['seed'] === true)) ?
		(array_key_exists('browser_cache', Utils::$context) ? Utils::$context['browser_cache'] : '') :
		(is_string($params['seed']) ? '?' . ltrim($params['seed'], '?') : '');
	$params['force_current'] = isset($params['force_current']) ? $params['force_current'] : false;
	$themeRef = !empty($params['default_theme']) ? 'default_theme' : 'theme';
	$params['async'] = isset($params['async']) ? $params['async'] : false;
	$params['defer'] = isset($params['defer']) ? $params['defer'] : false;
	$params['minimize'] = isset($params['minimize']) ? $params['minimize'] : false;
	$params['external'] = isset($params['external']) ? $params['external'] : false;
	$params['validate'] = isset($params['validate']) ? $params['validate'] : true;
	$params['attributes'] = isset($params['attributes']) ? $params['attributes'] : array();

	// Account for shorthand like admin.js?alp21 filenames
	$id = (empty($id) ? strtr(str_replace('.js', '', basename($fileName)), '?', '_') : $id) . '_js';
	$fileName = str_replace(pathinfo($fileName, PATHINFO_EXTENSION), strtok(pathinfo($fileName, PATHINFO_EXTENSION), '?'), $fileName);

	// Is this a local file?
	if (empty($params['external']))
	{
		// Are we validating it exists on disk?
		if (!empty($params['validate']) && ($mtime = @filemtime($settings[$themeRef . '_dir'] . '/scripts/' . $fileName)) === false)
		{
			// Can't find it in this theme, how about the default?
			if ($themeRef === 'theme' && !$params['force_current'] && ($mtime = @filemtime($settings['default_theme_dir'] . '/scripts/' . $fileName)) !== false)
			{
				$fileUrl = $settings['default_theme_url'] . '/scripts/' . $fileName;
				$filePath = $settings['default_theme_dir'] . '/scripts/' . $fileName;
			}
			else
			{
				$fileUrl = false;
				$filePath = false;
			}
		}
		else
		{
			$fileUrl = $settings[$themeRef . '_url'] . '/scripts/' . $fileName;
			$filePath = $settings[$themeRef . '_dir'] . '/scripts/' . $fileName;
			$mtime = @filemtime($filePath);
		}
	}
	// An external file doesn't have a filepath. Mock one for simplicity.
	else
	{
		$fileUrl = $fileName;
		$filePath = $fileName;

		// Always turn these off for external files.
		$params['minimize'] = false;
		$params['seed'] = false;
	}

	$mtime = empty($mtime) ? 0 : $mtime;

	// Add it to the array for use in the template
	if (!empty($fileName) && !empty($fileUrl))
		Utils::$context['javascript_files'][$id] = array('fileUrl' => $fileUrl, 'filePath' => $filePath, 'fileName' => $fileName, 'options' => $params, 'mtime' => $mtime);

	if ($mtime > Config::$modSettings['browser_cache'])
		Config::updateModSettings(array('browser_cache' => $mtime));
}

/**
 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
 * Cleaner and easier (for modders) than to use the function below.
 *
 * @param string $key The key for this variable
 * @param string $value The value
 * @param bool $escape Whether or not to escape the value
 */
function addJavaScriptVar($key, $value, $escape = false)
{
	// Variable name must be a valid string.
	if (!is_string($key) || $key === '' || is_numeric($key))
		return;

	// Take care of escaping the value for JavaScript?
	if (!empty($escape))
	{
		switch (gettype($value)) {
			// Illegal.
			case 'resource':
				break;

			// Convert PHP objects to arrays before processing.
			case 'object':
				$value = (array) $value;
				// no break

			// Apply JavaScriptEscape() to any strings in the array.
			case 'array':
				$replacements = array();
				array_walk_recursive(
					$value,
					function($v, $k) use (&$replacements)
					{
						if (is_string($v))
							$replacements[json_encode($v)] = JavaScriptEscape($v, true);
					}
				);
				$value = strtr(json_encode($value), $replacements);
				break;

			case 'string':
				$value = JavaScriptEscape($value);
				break;

			default:
				$value = json_encode($value);
				break;
		}
	}

	// At this point, value should contain suitably escaped JavaScript code.
	// If it obviously doesn't, declare the var with an undefined value.
	if (!is_string($value) && !is_numeric($value))
		$value = null;

	Utils::$context['javascript_vars'][$key] = $value;
}

/**
 * Add a block of inline Javascript code to be executed later
 *
 * - only use this if you have to, generally external JS files are better, but for very small scripts
 *   or for scripts that require help from PHP/whatever, this can be useful.
 * - all code added with this function is added to the same <script> tag so do make sure your JS is clean!
 *
 * @param string $javascript Some JS code
 * @param bool $defer Whether the script should load in <head> or before the closing <html> tag
 * @return void|bool Adds the code to one of the Utils::$context['javascript_inline'] arrays or returns if no JS was specified
 */
function addInlineJavaScript($javascript, $defer = false)
{
	if (empty($javascript))
		return false;

	Utils::$context['javascript_inline'][($defer === true ? 'defer' : 'standard')][] = $javascript;
}

/**
 * Get all parent boards (requires first parent as parameter)
 * It finds all the parents of id_parent, and that board itself.
 * Additionally, it detects the moderators of said boards.
 *
 * @param int $id_parent The ID of the parent board
 * @return array An array of information about the boards found.
 */
function getBoardParents($id_parent)
{
	// First check if we have this cached already.
	if (($boards = CacheApi::get('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = Db::$db->query('', '
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, b.member_groups, b.deny_member_groups,
					b.child_level, COALESCE(mem.id_member, 0) AS id_moderator, mem.real_name,
					COALESCE(mg.id_group, 0) AS id_moderator_group, mg.group_name
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
					LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board)
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = modgs.id_group)
				WHERE b.id_board = {int:board_parent}',
				array(
					'board_parent' => $id_parent,
				)
			);
			// In the EXTREMELY unlikely event this happens, give an error message.
			if (Db::$db->num_rows($result) == 0)
				fatal_lang_error('parent_not_found', 'critical');
			while ($row = Db::$db->fetch_assoc($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
						'name' => $row['name'],
						'level' => $row['child_level'],
						'groups' => explode(',', $row['member_groups']),
						'deny_groups' => explode(',', $row['deny_member_groups']),
						'moderators' => array(),
						'moderator_groups' => array()
					);
				}
				// If a moderator exists for this board, add that moderator for all children too.
				if (!empty($row['id_moderator']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => Config::$scripturl . '?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}

				// If a moderator group exists for this board, add that moderator group for all children too
				if (!empty($row['id_moderator_group']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderator_groups'][$row['id_moderator_group']] = array(
							'id' => $row['id_moderator_group'],
							'name' => $row['group_name'],
							'href' => Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'],
							'link' => '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'] . '">' . $row['group_name'] . '</a>'
						);
					}
			}
			Db::$db->free_result($result);
		}

		CacheApi::put('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Load the template/language file using require
 * 	- loads the template or language file specified by filename.
 * 	- uses eval unless disableTemplateEval is enabled.
 * 	- outputs a parse error if the file did not exist or contained errors.
 * 	- attempts to detect the error and line, and show detailed information.
 *
 * @param string $filename The name of the file to include
 * @param bool $once If true only includes the file once (like include_once)
 */
function template_include($filename, $once = false)
{
	static $templates = array();

	// We want to be able to figure out any errors...
	@ini_set('track_errors', '1');

	// Don't include the file more than once, if $once is true.
	if ($once && in_array($filename, $templates))
		return;
	// Add this file to the include list, whether $once is true or not.
	else
		$templates[] = $filename;

	$file_found = file_exists($filename);

	if ($once && $file_found)
		require_once($filename);
	elseif ($file_found)
		require($filename);

	if ($file_found !== true)
	{
		ob_end_clean();
		if (!empty(Config::$modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		if (isset($_GET['debug']))
			header('content-type: application/xhtml+xml; charset=' . (empty(Utils::$context['character_set']) ? 'ISO-8859-1' : Utils::$context['character_set']));

		// Don't cache error pages!!
		header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('cache-control: no-cache');

		if (!isset(Lang::$txt['template_parse_error']))
		{
			Lang::$txt['template_parse_error'] = 'Template Parse Error!';
			Lang::$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			Lang::$txt['template_parse_error_details'] = 'There was a problem loading the <pre><strong>%1$s</strong></pre> template or language file.  Please check the syntax and try again - remember, single quotes (<pre>\'</pre>) often have to be escaped with a slash (<pre>\\</pre>).  To see more specific error information from PHP, try <a href="%2$s%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="%3$s?theme=1">use the default theme</a>.';
			Lang::$txt['template_parse_errmsg'] = 'Unfortunately more information is not available at this time as to exactly what is wrong.';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html>
<html', !empty(Utils::$context['right_to_left']) ? ' dir="rtl"' : '', '>
	<head>';
		if (isset(Utils::$context['character_set']))
			echo '
		<meta charset="', Utils::$context['character_set'], '">';

		if (!empty(Config::$maintenance) && !allowedTo('admin_forum'))
			echo '
		<title>', Config::$mtitle, '</title>
	</head>
	<body>
		<h3>', Config::$mtitle, '</h3>
		', Config::$mmessage, '
	</body>
</html>';
		elseif (!allowedTo('admin_forum'))
			echo '
		<title>', Lang::$txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', Lang::$txt['template_parse_error'], '</h3>
		', Lang::$txt['template_parse_error_message'], '
	</body>
</html>';
		else
		{
			$error = fetch_web_data(Config::$boardurl . strtr($filename, array(Config::$boarddir => '', strtr(Config::$boarddir, '\\', '/') => '')));
			$error_array = error_get_last();
			if (empty($error) && ini_get('track_errors') && !empty($error_array))
				$error = $error_array['message'];
			if (empty($error))
				$error = Lang::$txt['template_parse_errmsg'];

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', Lang::$txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', Lang::$txt['template_parse_error'], '</h3>
		', sprintf(Lang::$txt['template_parse_error_details'], strtr($filename, array(Config::$boarddir => '', strtr(Config::$boarddir, '\\', '/') => '')), Config::$boardurl, Config::$scripturl);

			if (!empty($error))
				echo '
		<hr>

		<div style="margin: 0 20px;"><pre>', strtr(strtr($error, array('<strong>' . Config::$boarddir => '<strong>...', '<strong>' . strtr(Config::$boarddir, '\\', '/') => '<strong>...')), '\\', '/'), '</pre></div>';

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = BBCodeParser::highlightPhpCode(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if (!BrowserDetector::isBrowser('gecko'))
					$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
				else
					$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);

				// Now we get to work around a bug in PHP where it doesn't escape <br>s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br>') == 0)
						continue;

					$n = substr_count($line, '<br>');
					for ($i = 0; $i < $n; $i++)
					{
						$data2[$j] .= '&lt;br /&gt;' . $data2[$j + $i + 1];
						unset($data2[$j + $i + 1]);
					}
					$j += $n;
				}
				$data2 = array_values($data2);
				array_unshift($data2, '');

				echo '
		<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

				// Figure out what the color coding was before...
				$line = max($match[1] - 9, 1);
				$last_line = '';
				for ($line2 = $line - 1; $line2 > 1; $line2--)
					if (strpos($data2[$line2], '<') !== false)
					{
						if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
							$last_line = $color_match[1];
						break;
					}

				// Show the relevant lines...
				for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
				{
					if ($line == $match[1])
						echo '</pre><div style="background-color: #ffb0b5;"><pre style="margin: 0;">';

					echo '<span style="color: black;">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
					if (isset($data2[$line]) && $data2[$line] != '')
						echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];

					if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
					{
						$last_line = $color_match[1];
						echo '</', substr($last_line, 1, 4), '>';
					}
					elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
						$last_line = '';
					elseif ($last_line != '' && $data2[$line] != '')
						echo '</', substr($last_line, 1, 4), '>';

					if ($line == $match[1])
						echo '</pre></div><pre style="margin: 0;">';
					else
						echo "\n";
				}

				echo '</pre></div>';
			}

			echo '
	</body>
</html>';
		}

		die;
	}
}

/**
 * Helper function to set an array of data for an user's avatar.
 *
 * Makes assumptions based on the data provided, the following keys are required:
 * - avatar The raw "avatar" column in members table
 * - email The user's email. Used to get the gravatar info
 * - filename The attachment filename
 *
 * @param array $data An array of raw info
 * @return array An array of avatar data
 */
function set_avatar_data($data = array())
{
	global $user_info;

	// Come on!
	if (empty($data))
		return array();

	// Set a nice default var.
	$image = '';

	// Gravatar has been set as mandatory!
	if (!empty(Config::$modSettings['gravatarEnabled']) && !empty(Config::$modSettings['gravatarOverride']))
	{
		if (!empty(Config::$modSettings['gravatarAllowExtraEmail']) && !empty($data['avatar']) && stristr($data['avatar'], 'gravatar://'))
			$image = get_gravatar_url(Utils::entitySubstr($data['avatar'], 11));

		elseif (!empty($data['email']))
			$image = get_gravatar_url($data['email']);
	}

	// Look if the user has a gravatar field or has set an external url as avatar.
	else
	{
		// So it's stored in the member table?
		if (!empty($data['avatar']))
		{
			// Gravatar.
			if (stristr($data['avatar'], 'gravatar://'))
			{
				if ($data['avatar'] == 'gravatar://')
					$image = get_gravatar_url($data['email']);

				elseif (!empty(Config::$modSettings['gravatarAllowExtraEmail']))
					$image = get_gravatar_url(Utils::entitySubstr($data['avatar'], 11));
			}

			// External url.
			else
				$image = parse_iri($data['avatar'], PHP_URL_SCHEME) !== null ? get_proxied_url($data['avatar']) : Config::$modSettings['avatar_url'] . '/' . $data['avatar'];
		}

		// Perhaps this user has an attachment as avatar...
		elseif (!empty($data['filename']))
			$image = Config::$modSettings['custom_avatar_url'] . '/' . $data['filename'];

		// Right... no avatar... use our default image.
		else
			$image = Config::$modSettings['avatar_url'] . '/default.png';
	}

	call_integration_hook('integrate_set_avatar_data', array(&$image, &$data));

	// At this point in time $image has to be filled unless you chose to force gravatar and the user doesn't have the needed data to retrieve it... thus a check for !empty() is still needed.
	if (!empty($image))
		return array(
			'name' => !empty($data['avatar']) ? $data['avatar'] : '',
			'image' => '<img class="avatar" src="' . $image . '" alt="">',
			'href' => $image,
			'url' => $image,
		);

	// Fallback to make life easier for everyone...
	else
		return array(
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => '',
		);
}

?>