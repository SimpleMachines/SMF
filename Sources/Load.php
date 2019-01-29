<?php

/**
 * This file has the hefty job of loading information for the forum.
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
 * Load the $modSettings array.
 */
function reloadSettings()
{
	global $modSettings, $boarddir, $smcFunc, $txt, $db_character_set;
	global $cache_enable, $sourcedir, $context, $forum_version, $boardurl;

	// Most database systems have not set UTF-8 as their default input charset.
	if (!empty($db_character_set))
		$smcFunc['db_query']('', '
			SET NAMES {string:db_character_set}',
			array(
				'db_character_set' => $db_character_set,
			)
		);

	// We need some caching support, maybe.
	loadCacheAccelerator();

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($modSettings = cache_get_data('modSettings', 90)) == null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$modSettings = array();
		if (!$request)
			display_db_error();
		while ($row = $smcFunc['db_fetch_row']($request))
			$modSettings[$row[0]] = $row[1];
		$smcFunc['db_free_result']($request);

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($modSettings['defaultMaxTopics']) || $modSettings['defaultMaxTopics'] <= 0 || $modSettings['defaultMaxTopics'] > 999)
			$modSettings['defaultMaxTopics'] = 20;
		if (empty($modSettings['defaultMaxMessages']) || $modSettings['defaultMaxMessages'] <= 0 || $modSettings['defaultMaxMessages'] > 999)
			$modSettings['defaultMaxMessages'] = 15;
		if (empty($modSettings['defaultMaxMembers']) || $modSettings['defaultMaxMembers'] <= 0 || $modSettings['defaultMaxMembers'] > 999)
			$modSettings['defaultMaxMembers'] = 30;
		if (empty($modSettings['defaultMaxListItems']) || $modSettings['defaultMaxListItems'] <= 0 || $modSettings['defaultMaxListItems'] > 999)
			$modSettings['defaultMaxListItems'] = 15;

		// We explicitly do not use $smcFunc['json_decode'] here yet, as $smcFunc is not fully loaded.
		if (!is_array($modSettings['attachmentUploadDir']))
		{
			$attachmentUploadDir = smf_json_decode($modSettings['attachmentUploadDir'], true);
			$modSettings['attachmentUploadDir'] = !empty($attachmentUploadDir) ? $attachmentUploadDir : $modSettings['attachmentUploadDir'];
		}

		if (!empty($cache_enable))
			cache_put_data('modSettings', $modSettings, 90);
	}

	// Going anything further when the files don't match the database can make nasty messes (unless we're actively installing or upgrading)
	if (!defined('SMF_INSTALLING') && (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'admin' || !isset($_REQUEST['area']) || $_REQUEST['area'] !== 'packages') && !empty($modSettings['smfVersion']) && version_compare(strtolower(strtr($modSettings['smfVersion'], array(' ' => '.'))), strtolower(strtr(SMF_VERSION, array(' ' => '.'))), '!='))
	{
		// Wipe the cached $modSettings values so they don't interfere with anything later
		cache_put_data('modSettings', null);

		// Redirect to the upgrader if we can
		if (file_exists($boarddir . '/upgrade.php'))
			header('location: ' . $boardurl . '/upgrade.php');

		die('SMF file version (' . SMF_VERSION . ') does not match SMF database version (' . $modSettings['smfVersion'] . ').<br>Run the SMF upgrader to fix this.<br><a href="https://wiki.simplemachines.org/smf/Upgrading">More information</a>.');
	}

	$modSettings['cache_enable'] = $cache_enable;

	// Used to force browsers to download fresh CSS and JavaScript when necessary
	$modSettings['browser_cache'] = !empty($modSettings['browser_cache']) ? (int) $modSettings['browser_cache'] : 0;
	$context['browser_cache'] = '?' . preg_replace('~\W~', '', strtolower(SMF_FULL_VERSION)) . '_' . $modSettings['browser_cache'];

	// UTF-8 ?
	$utf8 = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8';

	// Set a list of common functions.
	$ent_list = '&(?:#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . '|quot|amp|lt|gt|nbsp);';
	$ent_check = empty($modSettings['disableEntityCheck']) ? function($string)
		{
			$string = preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string);
			return $string;
		} : function($string)
		{
			return $string;
		};
	$fix_utf8mb4 = function($string) use ($utf8, $smcFunc)
	{
		if (!$utf8 || $smcFunc['db_mb4'])
			return $string;

		$i = 0;
		$len = strlen($string);
		$new_string = '';
		while ($i < $len)
		{
			$ord = ord($string[$i]);
			if ($ord < 128)
			{
				$new_string .= $string[$i];
				$i++;
			}
			elseif ($ord < 224)
			{
				$new_string .= $string[$i] . $string[$i + 1];
				$i += 2;
			}
			elseif ($ord < 240)
			{
				$new_string .= $string[$i] . $string[$i + 1] . $string[$i + 2];
				$i += 3;
			}
			elseif ($ord < 248)
			{
				// Magic happens.
				$val = (ord($string[$i]) & 0x07) << 18;
				$val += (ord($string[$i + 1]) & 0x3F) << 12;
				$val += (ord($string[$i + 2]) & 0x3F) << 6;
				$val += (ord($string[$i + 3]) & 0x3F);
				$new_string .= '&#' . $val . ';';
				$i += 4;
			}
		}
		return $new_string;
	};

	// global array of anonymous helper functions, used mostly to properly handle multi byte strings
	$smcFunc += array(
		'entity_fix' => function($string)
		{
			$num = $string[0] === 'x' ? hexdec(substr($string, 1)) : (int) $string;
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202E || $num === 0x202D ? '' : '&#' . $num . ';';
		},
		'htmlspecialchars' => function($string, $quote_style = ENT_COMPAT, $charset = 'ISO-8859-1') use ($ent_check, $utf8, $fix_utf8mb4)
		{
			return $fix_utf8mb4($ent_check(htmlspecialchars($string, $quote_style, $utf8 ? 'UTF-8' : $charset)));
		},
		'htmltrim' => function($string) use ($utf8, $ent_check)
		{
			// Preg_replace space characters depend on the character set in use
			$space_chars = $utf8 ? '\p{Z}\p{C}' : '\x00-\x20\x80-\xA0';

			return preg_replace('~^(?:[' . $space_chars . ']|&nbsp;)+|(?:[' . $space_chars . ']|&nbsp;)+$~' . ($utf8 ? 'u' : ''), '', $ent_check($string));
		},
		'strlen' => function($string) use ($ent_list, $utf8, $ent_check)
		{
			return strlen(preg_replace('~' . $ent_list . ($utf8 ? '|.~u' : '~'), '_', $ent_check($string)));
		},
		'strpos' => function($haystack, $needle, $offset = 0) use ($utf8, $ent_check, $ent_list, $modSettings)
		{
			$haystack_arr = preg_split('~(' . $ent_list . '|.)~' . ($utf8 ? 'u' : ''), $ent_check($haystack), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

			if (strlen($needle) === 1)
			{
				$result = array_search($needle, array_slice($haystack_arr, $offset));
				return is_int($result) ? $result + $offset : false;
			}
			else
			{
				$needle_arr = preg_split('~(' . $ent_list . '|.)~' . ($utf8 ? 'u' : '') . '', $ent_check($needle), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$needle_size = count($needle_arr);

				$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
				while ((int) $result === $result)
				{
					$offset += $result;
					if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
						return $offset;
					$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
				}
				return false;
			}
		},
		'substr' => function($string, $start, $length = null) use ($utf8, $ent_check, $ent_list, $modSettings)
		{
			$ent_arr = preg_split('~(' . $ent_list . '|.)~' . ($utf8 ? 'u' : '') . '', $ent_check($string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
		},
		'strtolower' => $utf8 ? function($string) use ($sourcedir)
		{
			if (!function_exists('mb_strtolower'))
			{
				require_once($sourcedir . '/Subs-Charset.php');
				return utf8_strtolower($string);
			}

			return mb_strtolower($string, 'UTF-8');
		} : 'strtolower',
		'strtoupper' => $utf8 ? function($string)
		{
			global $sourcedir;

			if (!function_exists('mb_strtolower'))
			{
				require_once($sourcedir . '/Subs-Charset.php');
				return utf8_strtoupper($string);
			}

			return mb_strtoupper($string, 'UTF-8');
		} : 'strtoupper',
		'truncate' => function($string, $length) use ($utf8, $ent_check, $ent_list, &$smcFunc)
		{
			$string = $ent_check($string);
			preg_match('~^(' . $ent_list . '|.){' . $smcFunc['strlen'](substr($string, 0, $length)) . '}~' . ($utf8 ? 'u' : ''), $string, $matches);
			$string = $matches[0];
			while (strlen($string) > $length)
				$string = preg_replace('~(?:' . $ent_list . '|.)$~' . ($utf8 ? 'u' : ''), '', $string);
			return $string;
		},
		'ucfirst' => $utf8 ? function($string) use (&$smcFunc)
		{
			return $smcFunc['strtoupper']($smcFunc['substr']($string, 0, 1)) . $smcFunc['substr']($string, 1);
		} : 'ucfirst',
		'ucwords' => $utf8 ? function($string) use (&$smcFunc)
		{
			$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			for ($i = 0, $n = count($words); $i < $n; $i += 2)
				$words[$i] = $smcFunc['ucfirst']($words[$i]);
			return implode('', $words);
		} : 'ucwords',
		'json_decode' => 'smf_json_decode',
		'json_encode' => 'json_encode',
		'random_int' => function($min = 0, $max = PHP_INT_MAX)
		{
			global $sourcedir;

			// Oh, wouldn't it be great if I *was* crazy? Then the world would be okay.
			if (!is_callable('random_int'))
				require_once($sourcedir . '/random_compat/random.php');

			return random_int($min, $max);
		},
	);

	// Setting the timezone is a requirement for some functions.
	if (isset($modSettings['default_timezone']) && in_array($modSettings['default_timezone'], timezone_identifiers_list()))
		date_default_timezone_set($modSettings['default_timezone']);
	else
	{
		// Get PHP's default timezone, if set
		$ini_tz = ini_get('date.timezone');
		if (!empty($ini_tz))
			$modSettings['default_timezone'] = $ini_tz;
		else
			$modSettings['default_timezone'] = '';

		// If date.timezone is unset, invalid, or just plain weird, make a best guess
		if (!in_array($modSettings['default_timezone'], timezone_identifiers_list()))
		{
			$server_offset = @mktime(0, 0, 0, 1, 1, 1970);
			$modSettings['default_timezone'] = timezone_name_from_abbr('', $server_offset, 0);
		}

		date_default_timezone_set($modSettings['default_timezone']);
	}

	// Check the load averages?
	if (!empty($modSettings['loadavg_enable']))
	{
		if (($modSettings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$modSettings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			elseif (($modSettings['load_average'] = @`uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			else
				unset($modSettings['load_average']);

			if (!empty($modSettings['load_average']) || $modSettings['load_average'] === 0.0)
				cache_put_data('loadavg', $modSettings['load_average'], 90);
		}

		if (!empty($modSettings['load_average']) || $modSettings['load_average'] === 0.0)
			call_integration_hook('integrate_load_average', array($modSettings['load_average']));

		if (!empty($modSettings['loadavg_forum']) && !empty($modSettings['load_average']) && $modSettings['load_average'] >= $modSettings['loadavg_forum'])
			display_loadavg_error();
	}

	// Is post moderation alive and well? Everywhere else assumes this has been defined, so let's make sure it is.
	$modSettings['postmod_active'] = !empty($modSettings['postmod_active']);

	// Here to justify the name of this function. :P
	// It should be added to the install and upgrade scripts.
	// But since the converters need to be updated also. This is easier.
	if (empty($modSettings['currentAttachmentUploadDir']))
	{
		updateSettings(array(
			'attachmentUploadDir' => $smcFunc['json_encode'](array(1 => $modSettings['attachmentUploadDir'])),
			'currentAttachmentUploadDir' => 1,
		));
	}

	// Integration is cool.
	if (defined('SMF_INTEGRATION_SETTINGS'))
	{
		$integration_settings = $smcFunc['json_decode'](SMF_INTEGRATION_SETTINGS, true);
		foreach ($integration_settings as $hook => $function)
			add_integration_function($hook, $function, '', false);
	}

	// Any files to pre include?
	if (!empty($modSettings['integrate_pre_include']))
	{
		$pre_includes = explode(',', $modSettings['integrate_pre_include']);
		foreach ($pre_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir));
			if (file_exists($include))
				require_once($include);
		}
	}

	// This determines the server... not used in many places, except for login fixing.
	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => DIRECTORY_SEPARATOR === '\\',
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);
	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];

	// Define a list of icons used across multiple places.
	$context['stable_icons'] = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved', 'recycled', 'clip');

	// Define an array for custom profile fields placements.
	$context['cust_profile_fields_placement'] = array(
		'standard',
		'icons',
		'above_signature',
		'below_signature',
		'below_avatar',
		'above_member',
		'bottom_poster',
		'before_member',
		'after_member',
	);

	// Define an array for content-related <meta> elements (e.g. description, keywords, Open Graph) for the HTML head.
	$context['meta_tags'] = array();

	// Define an array of allowed HTML tags.
	$context['allowed_html_tags'] = array(
		'<img>',
		'<div>',
	);

	// These are the only valid image types for SMF attachments, by default anyway.
	// Note: The values are for image mime types, not file extensions.
	$context['valid_image_types'] = array(
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_PSD => 'psd',
		IMAGETYPE_BMP => 'bmp',
		IMAGETYPE_TIFF_II => 'tiff',
		IMAGETYPE_TIFF_MM => 'tiff',
		IMAGETYPE_IFF => 'iff'
	);

	// Define a list of allowed tags for descriptions.
	$context['description_allowed_tags'] = array(
		'abbr', 'anchor', 'b', 'center', 'color', 'font', 'hr', 'i', 'img',
		'iurl', 'left', 'li', 'list', 'ltr', 'pre', 'right', 's', 'sub',
		'sup', 'table', 'td', 'tr', 'u', 'url',
	);

	// Define a list of deprecated BBC tags
	// Even when enabled, they'll only work in old posts and not new ones
	$context['legacy_bbc'] = array(
		'acronym', 'bdo', 'black', 'blue', 'flash', 'ftp', 'glow',
		'green', 'move', 'red', 'shadow', 'tt', 'white',

	);

	// Call pre load integration functions.
	call_integration_hook('integrate_pre_load');
}

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
	global $modSettings, $user_settings, $sourcedir, $smcFunc;
	global $cookiename, $user_info, $language, $context, $image_proxy_enabled;

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

	if (empty($id_member) && isset($_COOKIE[$cookiename]))
	{
		// First try 2.1 json-format cookie
		$cookie_data = $smcFunc['json_decode']($_COOKIE[$cookiename], true, false);

		// Legacy format (for recent 2.0 --> 2.1 upgrades)
		if (empty($cookie_data))
			$cookie_data = safe_unserialize($_COOKIE[$cookiename]);

		list($id_member, $password, $login_span, $cookie_domain, $cookie_path) = array_pad((array) $cookie_data, 5, '');

		$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;

		// Make sure the cookie is set to the correct domain and path
		require_once($sourcedir . '/Subs-Auth.php');
		if (array($cookie_domain, $cookie_path) !== url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies'])))
			setLoginCookie((int) $login_span - time(), $id_member);
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty($modSettings['disableCheckUA'])))
	{
		// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
		$cookie_data = $smcFunc['json_decode']($_SESSION['login_' . $cookiename], true);

		if (empty($cookie_data))
			$cookie_data = safe_unserialize($_SESSION['login_' . $cookiename]);

		list($id_member, $password, $login_span) = array_pad((array) $cookie_data, 3, '');
		$id_member = !empty($id_member) && strlen($password) == 128 && (int) $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT mem.*, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($modSettings['force_ssl']) && $image_proxy_enabled && stripos($user_settings['avatar'], 'http://') !== false && empty($user_info['possibly_robot']))
				$user_settings['avatar'] = get_proxied_url($user_settings['avatar']);

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-512 hash should be 128 characters long.
			elseif (strlen($password) == 128)
				$check = hash_salt($user_settings['passwd'], $user_settings['password_salt']) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? (int) $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
		{
			require_once($sourcedir . '/LogInOut.php');
			validatePasswordFlood(
				!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member,
				!empty($user_settings['member_name']) ? $user_settings['member_name'] : '',
				!empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false,
				$id_member != 0
			);
		}
		// Validate for Two Factor Authentication
		elseif (!empty($modSettings['tfa_mode']) && $id_member && !empty($user_settings['tfa_secret']) && (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], array('login2', 'logintfa'))))
		{
			$tfacookie = $cookiename . '_tfa';
			$tfasecret = null;

			$verified = call_integration_hook('integrate_verify_tfa', array($id_member, $user_settings));

			if (empty($verified) || !in_array(true, $verified))
			{
				if (!empty($_COOKIE[$tfacookie]))
				{
					$tfa_data = $smcFunc['json_decode']($_COOKIE[$tfacookie], true);

					list ($tfamember, $tfasecret) = array_pad((array) $tfa_data, 2, '');

					if (!isset($tfamember, $tfasecret) || (int) $tfamember != $id_member)
						$tfasecret = null;
				}

				// They didn't finish logging in before coming here? Then they're no one to us.
				if (empty($tfasecret) || hash_salt($user_settings['tfa_backup'], $user_settings['password_salt']) != $tfasecret)
				{
					setLoginCookie(-3600, $id_member);
					$id_member = 0;
					$user_settings = array();
				}
			}
		}
		// When authenticating their two factor code, make sure to reset their ID for security
		elseif (!empty($modSettings['tfa_mode']) && $id_member && !empty($user_settings['tfa_secret']) && $_REQUEST['action'] == 'logintfa')
		{
			$id_member = 0;
			$context['tfa_member'] = $user_settings;
			$user_settings = array();
		}
		// Are we forcing 2FA? Need to check if the user groups actually require 2FA
		elseif (!empty($modSettings['tfa_mode']) && $modSettings['tfa_mode'] >= 2 && $id_member && empty($user_settings['tfa_secret']))
		{
			if ($modSettings['tfa_mode'] == 2) //only do this if we are just forcing SOME membergroups
			{
				//Build an array of ALL user membergroups.
				$full_groups = array($user_settings['id_group']);
				if (!empty($user_settings['additional_groups']))
				{
					$full_groups = array_merge($full_groups, explode(',', $user_settings['additional_groups']));
					$full_groups = array_unique($full_groups); //duplicates, maybe?
				}

				//Find out if any group requires 2FA
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(id_group) AS total
					FROM {db_prefix}membergroups
					WHERE tfa_required = {int:tfa_required}
						AND id_group IN ({array_int:full_groups})',
					array(
						'tfa_required' => 1,
						'full_groups' => $full_groups,
					)
				);
				$row = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);
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
		if (SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], array('.xml', 'login2', 'logintfa'))) && empty($_SESSION['id_msg_last_visit']) && (empty($modSettings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			$result = $smcFunc['db_query']('', '
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $user_settings['id_msg_last_visit'],
				)
			);
			list ($visitTime) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600)
			{
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
				$user_settings['last_login'] = time();

				if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
					cache_put_data('user_settings-' . $id_member, $user_settings, 60);

				if (!empty($modSettings['cache_enable']))
					cache_put_data('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
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
			$tz_system = new DateTimeZone(@date_default_timezone_get());
			$tz_user = new DateTimeZone($user_settings['timezone']);
			$time_system = new DateTime('now', $tz_system);
			$time_user = new DateTime('now', $tz_user);
			$user_info['time_offset'] = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600;
		}
		else
		{
			// !!! Compatibility.
			$user_info['time_offset'] = empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset'];
		}
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[$cookiename]) && empty($context['tfa_member']))
			$_COOKIE[$cookiename] = '';

		// Expire the 2FA cookie
		if (isset($_COOKIE[$cookiename . '_tfa']) && empty($context['tfa_member']))
		{
			$tfa_data = $smcFunc['json_decode']($_COOKIE[$cookiename . '_tfa'], true);

			list (,, $exp) = array_pad((array) $tfa_data, 3, 0);

			if (time() > $exp)
			{
				$_COOKIE[$cookiename . '_tfa'] = '';
				setTFACookie(-3600, 0, '');
			}
		}

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login']))
			createToken('login');
		else
			list ($context['login_token_var'],,, $context['login_token']) = $_SESSION['token']['post-login'];

		// Do we perhaps think this is a search robot? Check every five minutes just in case...
		if ((!empty($modSettings['spider_mode']) || !empty($modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			require_once($sourcedir . '/ManageSearchEngines.php');
			$user_info['possibly_robot'] = SpiderCheck();
		}
		elseif (!empty($modSettings['spider_mode']))
			$user_info['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
		// If we haven't turned on proper spider hunts then have a guess!
		else
		{
			$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			$user_info['possibly_robot'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || strpos($ci_user_agent, 'googlebot') !== false || strpos($ci_user_agent, 'slurp') !== false || strpos($ci_user_agent, 'crawl') !== false || strpos($ci_user_agent, 'bingbot') !== false || strpos($ci_user_agent, 'bingpreview') !== false || strpos($ci_user_agent, 'adidxbot') !== false || strpos($ci_user_agent, 'msnbot') !== false;
		}

		// We don't know the offset...
		$user_info['time_offset'] = 0;

		// Login Cookie times. Format: time => txt
		$context['login_cookie_times'] = array(
			60 => 'one_hour',
			1440 => 'one_day',
			10080 => 'one_week',
			43200 => 'one_month',
			3153600 => 'always_logged_in',
		);
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ip2' => $_SERVER['BAN_CHECK_IP'],
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? $modSettings['time_format'] : $user_settings['time_format'],
		'avatar' => array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0
		),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'alerts' => empty($user_settings['alerts']) ? 0 : $user_settings['alerts'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($modSettings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);
	$user_info['groups'] = array_unique($user_info['groups']);

	// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Allow the user to change their language.
	if (!empty($modSettings['userLanguage']))
	{
		$languages = getLanguages();

		// Is it valid?
		if (!empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
		{
			$user_info['language'] = strtr($_GET['language'], './\\:', '____');

			// Make it permanent for members.
			if (!empty($user_info['id']))
				updateMemberData($user_info['id'], array('lngfile' => $user_info['language']));
			else
				$_SESSION['language'] = $user_info['language'];
		}
		elseif (!empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
			$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');
	}

	$temp = build_query_board($user_info['id']);
	$user_info['query_see_board'] = $temp['query_see_board'];
	$user_info['query_wanna_see_board'] = $temp['query_wanna_see_board'];

	call_integration_hook('integrate_user_info');
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
	global $txt, $scripturl, $context, $modSettings;
	global $board_info, $board, $topic, $user_info, $smcFunc;

	// Assume they are not a moderator.
	$user_info['is_mod'] = false;
	$context['user']['is_mod'] = &$user_info['is_mod'];

	// Start the linktree off empty..
	$context['linktree'] = array();

	// Have they by chance specified a message id but nothing else?
	if (empty($_REQUEST['action']) && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);

			// So did it find anything?
			if ($smcFunc['db_num_rows']($request))
			{
				list ($topic) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				// Save save save.
				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
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

	if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
	{
		// @todo SLOW?
		if (!empty($topic))
			$temp = cache_get_data('topic_board-' . $topic, 120);
		else
			$temp = cache_get_data('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$request = $smcFunc['db_query']('load_board_info', '
			SELECT
				c.id_cat, b.name AS bname, b.description, b.num_topics, b.member_groups, b.deny_member_groups,
				b.id_parent, c.name AS cname, COALESCE(mg.id_group, 0) AS id_moderator_group, mg.group_name,
				COALESCE(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect,
				b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = modgs.id_group)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE b.id_board = {raw:board_link}',
			array(
				'current_topic' => $topic,
				'board_link' => empty($topic) ? $smcFunc['db_quote']('{int:current_board}', array('current_board' => $board)) : 't.id_board',
			)
		);
		// If there aren't any, skip.
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			$row = $smcFunc['db_fetch_assoc']($request);

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
				'recycle' => !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) && $modSettings['recycle_board'] == $board,
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
			);

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] == '' ? array() : explode(',', $row['member_groups']);
			$board_info['deny_groups'] = $row['deny_member_groups'] == '' ? array() : explode(',', $row['deny_member_groups']);

			do
			{
				if (!empty($row['id_moderator']))
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);

				if (!empty($row['id_moderator_group']))
					$board_info['moderator_groups'][$row['id_moderator_group']] = array(
						'id' => $row['id_moderator_group'],
						'name' => $row['group_name'],
						'href' => $scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'],
						'link' => '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'] . '">' . $row['group_name'] . '</a>'
					);
			}
			while ($row = $smcFunc['db_fetch_assoc']($request));

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				// Free the previous result
				$smcFunc['db_free_result']($request);

				// @todo why is this using id_topic?
				// @todo Can this get cached?
				$request = $smcFunc['db_query']('', '
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

				list ($board_info['unapproved_user_topics']) = $smcFunc['db_fetch_row']($request);
			}

			if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
			{
				// @todo SLOW?
				if (!empty($topic))
					cache_put_data('topic_board-' . $topic, $board_info, 120);
				cache_put_data('board-' . $board, $board_info, 120);
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
		$smcFunc['db_free_result']($request);
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
		if (!empty($modSettings['deny_boards_access']) && count(array_intersect($user_info['groups'], $board_info['deny_groups'])) != 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => $scripturl . '#c' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => $scripturl . '?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);
	}

	// Set the template contextual information.
	$context['user']['is_mod'] = &$user_info['is_mod'];
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// No posting in redirection boards!
	if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'post' && !empty($board_info['redirect']))
		$board_info['error'] == 'post_in_redirect';

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && (!empty($modSettings['deny_boards_access']) || $board_info['error'] != 'access' || !$user_info['is_mod']))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		loadTheme();

		$_GET['board'] = '';
		$_GET['topic'] = '';

		// The linktree should not give the game away mate!
		$context['linktree'] = array(
			array(
				'url' => $scripturl,
				'name' => $context['forum_name_html_safe']
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
			loadLanguage('Errors');
			is_not_guest($txt['topic_gone']);
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
	global $user_info, $board, $board_info, $modSettings, $smcFunc, $sourcedir;

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	if (!empty($modSettings['cache_enable']))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if ($modSettings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > $modSettings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $modSettings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$smcFunc['db_free_result']($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array($user_info['permissions'], $removals), 240);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => $user_info['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$smcFunc['db_free_result']($request);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty($modSettings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && $modSettings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

	// Banned?  Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $modSettings['settings_updated'])
		{
			require_once($sourcedir . '/Subs-Auth.php');
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
	global $user_profile, $modSettings, $board_info, $smcFunc, $context;
	global $image_proxy_enabled, $user_info;

	// Can't just look for no users :P.
	if (empty($users))
		return array();

	// Pass the set value
	$context['loadMemberContext_set'] = $set;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	// Used by default
	$select_columns = '
			COALESCE(lo.log_time, 0) AS is_online, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login, mem.id_post_group, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mg.online_color AS member_group_color, COALESCE(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, COALESCE(pg.group_name, {string:blank_string}) AS post_group,
			mem.is_activated, mem.warning, ' . (!empty($modSettings['titlesEnable']) ? 'mem.usertitle, ' : '') . '
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
			trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);
	}

	// Allow mods to easily add to the selected member data
	call_integration_hook('integrate_load_member_data', array(&$select_columns, &$select_tables, &$set));

	if (!empty($users))
	{
		// Load the member's data.
		$request = $smcFunc['db_query']('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})',
			array(
				'blank_string' => '',
				'users' => $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If the image proxy is enabled, we still want the original URL when they're editing the profile...
			$row['avatar_original'] = !empty($row['avatar']) ? $row['avatar'] : '';

			// Take care of proxying avatar if required, do this here for maximum reach
			if ($image_proxy_enabled && !empty($row['avatar']) && stripos($row['avatar'], 'http://') !== false && empty($user_info['possibly_robot']))
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
		$smcFunc['db_free_result']($request);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal')
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, variable, value
			FROM {db_prefix}themes
			WHERE id_member IN ({array_int:loaded_ids})',
			array(
				'loaded_ids' => $new_loaded_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);
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

	if (!empty($new_loaded_ids) && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators?  If so, fix their group data...
	if (!empty($loaded_ids) && (!empty($board_info['moderators']) || !empty($board_info['moderator_groups'])) && $set === 'normal' && count($temp_mods = array_merge(array_intersect($loaded_ids, array_keys($board_info['moderators'])), $additional_mods)) !== 0)
	{
		if (($row = cache_get_data('moderator_group_info', 480)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT group_name AS member_group, online_color AS member_group_color, icons
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			cache_put_data('moderator_group_info', $row, 480);
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
 * @return boolean Whether or not the data was loaded successfully
 */
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $txt, $scripturl, $user_info;
	global $context, $modSettings, $settings, $smcFunc;
	static $dataLoaded = array();
	static $loadedLanguages = array();

	// If this person's data is already loaded, skip it.
	if (isset($dataLoaded[$user]))
		return true;

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	censorText($profile['signature']);
	censorText($profile['personal_text']);

	// Set things up to be used before hand.
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br>', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);

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

	// These minimal values are always loaded
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '" ' . (!empty($modSettings['onlineEnable']) ? 'class="pm_icon"' : '') . '>' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => !$user_info['is_guest'] && ($user_info['id'] == $profile['id_member'] || allowedTo('moderate_forum')),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
	);

	// If the set isn't minimal then load the monstrous array.
	if ($context['loadMemberContext_set'] != 'minimal')
	{
		// Go the extra mile and load the user's native language name.
		if (empty($loadedLanguages))
			$loadedLanguages = getLanguages();

		$memberContext[$user] += array(
			'username_color' => '<span ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['member_name'] . '</span>',
			'name_color' => '<span ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['real_name'] . '</span>',
			'link_color' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '" ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['real_name'] . '</a>',
			'is_buddy' => $profile['buddy'],
			'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
			'buddies' => $buddy_list,
			'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
			'blurb' => $profile['personal_text'],
			'website' => array(
				'title' => $profile['website_title'],
				'url' => $profile['website_url'],
			),
			'birth_date' => empty($profile['birthdate']) ? '1004-01-01' : (substr($profile['birthdate'], 0, 4) === '0004' ? '1004' . substr($profile['birthdate'], 4) : $profile['birthdate']),
			'signature' => $profile['signature'],
			'real_posts' => $profile['posts'],
			'posts' => $profile['posts'] > 500000 ? $txt['geek'] : comma_format($profile['posts']),
			'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
			'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
			'ip' => $smcFunc['htmlspecialchars']($profile['member_ip']),
			'ip2' => $smcFunc['htmlspecialchars']($profile['member_ip2']),
			'online' => array(
				'is_online' => $profile['is_online'],
				'text' => $smcFunc['htmlspecialchars']($txt[$profile['is_online'] ? 'online' : 'offline']),
				'member_online_text' => sprintf($txt[$profile['is_online'] ? 'member_is_online' : 'member_is_offline'], $smcFunc['htmlspecialchars']($profile['real_name'])),
				'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
				'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
			),
			'language' => !empty($loadedLanguages[$profile['lngfile']]) && !empty($loadedLanguages[$profile['lngfile']]['name']) ? $loadedLanguages[$profile['lngfile']]['name'] : $smcFunc['ucwords'](strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))),
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
			'group_icons' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['icons'][1]) ? $group_icon_url : '') . '" alt="*">', empty($profile['icons'][0]) || empty($profile['icons'][1]) ? 0 : $profile['icons'][0]),
			'warning' => $profile['warning'],
			'warning_status' => !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : (''))),
			'local_time' => timeformat(time() + ($profile['time_offset'] - $user_info['time_offset']) * 3600, false),
			'custom_fields' => array(),
		);
	}

	// If the set isn't minimal then load their avatar as well.
	if ($context['loadMemberContext_set'] != 'minimal')
	{
		if (!empty($modSettings['gravatarOverride']) || (!empty($modSettings['gravatarEnabled']) && stristr($profile['avatar'], 'gravatar://')))
		{
			if (!empty($modSettings['gravatarAllowExtraEmail']) && stristr($profile['avatar'], 'gravatar://') && strlen($profile['avatar']) > 11)
				$image = get_gravatar_url($smcFunc['substr']($profile['avatar'], 11));
			else
				$image = get_gravatar_url($profile['email_address']);
		}
		else
		{
			// So it's stored in the member table?
			if (!empty($profile['avatar']))
			{
				$image = (stristr($profile['avatar'], 'http://') || stristr($profile['avatar'], 'https://')) ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar'];
			}
			elseif (!empty($profile['filename']))
				$image = $modSettings['custom_avatar_url'] . '/' . $profile['filename'];
			// Right... no avatar...use the default one
			else
				$image = $modSettings['avatar_url'] . '/default.png';
		}
		if (!empty($image))
			$memberContext[$user]['avatar'] = array(
				'name' => $profile['avatar'],
				'image' => '<img class="avatar" src="' . $image . '" alt="avatar_' . $profile['member_name'] . '">',
				'href' => $image,
				'url' => $image,
			);
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($modSettings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();

		if (!isset($context['display_fields']))
			$context['display_fields'] = $smcFunc['json_decode']($modSettings['displayFields'], true);

		foreach ($context['display_fields'] as $custom)
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
				$value = parse_bbc($value);

			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
					'{KEY}' => $currentKey,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => !empty($custom['title']) ? $custom['title'] : $custom['col_name'],
				'col_name' => $custom['col_name'],
				'value' => un_htmlspecialchars($value),
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	call_integration_hook('integrate_member_context', array(&$memberContext[$user], $user, $display_custom_fields));
	return true;
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
	global $smcFunc, $txt, $scripturl, $settings;

	// Do not waste my time...
	if (empty($users) || empty($params))
		return false;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$params = !is_array($params) ? array($params) : array_unique($params);
	$return = array();

	$request = $smcFunc['db_query']('', '
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

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$fieldOptions = array();
		$currentKey = 0;

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
			$row['value'] = parse_bbc($row['value']);

		// ... or checkbox?
		elseif (isset($row['type']) && $row['type'] == 'check')
			$row['value'] = !empty($row['value']) ? $txt['yes'] : $txt['no'];

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']))
			$row['value'] = strtr($row['enclose'], array(
				'{SCRIPTURL}' => $scripturl,
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

	$smcFunc['db_free_result']($request);

	return !empty($return) ? $return : false;
}

/**
 * Loads information about what browser the user is viewing with and places it in $context
 *  - uses the class from {@link Class-BrowserDetect.php}
 */
function detectBrowser()
{
	// Load the current user's browser of choice
	$detector = new browser_detector;
	$detector->detectBrowser();
}

/**
 * Are we using this browser?
 *
 * Wrapper function for detectBrowser
 *
 * @param string $browser The browser we are checking for.
 * @return bool Whether or not the current browser is what we're looking for
 */
function isBrowser($browser)
{
	global $context;

	// Don't know any browser!
	if (empty($context['browser']))
		detectBrowser();

	return !empty($context['browser'][$browser]) || !empty($context['browser']['is_' . $browser]) ? true : false;
}

/**
 * Load a theme, by ID.
 *
 * @param int $id_theme The ID of the theme to load
 * @param bool $initialize Whether or not to initialize a bunch of theme-related variables/settings
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info, $boarddir, $maintenance;
	global $txt, $boardurl, $scripturl, $mbname, $modSettings;
	global $context, $settings, $options, $sourcedir, $ssi_theme, $smcFunc, $language, $board, $image_proxy_enabled;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_REQUEST['theme'];
		$_SESSION['id_theme'] = $id_theme;
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = (int) $_SESSION['id_theme'];
	// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']))
		$id_theme = $user_info['theme'];
	// The theme was specified by the board.
	elseif (!empty($board_info['theme']))
		$id_theme = $board_info['theme'];
	// The theme is the forum's default.
	else
		$id_theme = $modSettings['theme_guests'];

	// Verify the id_theme... no foul play.
	// Always allow the board specific theme, if they are overriding.
	if (!empty($board_info['theme']) && $board_info['override_theme'])
		$id_theme = $board_info['theme'];
	// If they have specified a particular theme to use with SSI allow it to be used.
	elseif (!empty($ssi_theme) && $id_theme == $ssi_theme)
		$id_theme = (int) $id_theme;
	elseif (!empty($modSettings['enableThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $modSettings['enableThemes']);
		if (!in_array($id_theme, $themes))
			$id_theme = $modSettings['theme_guests'];
		else
			$id_theme = (int) $id_theme;
	}

	// Allow mod authors the option to override the theme id for custom page themes
	call_integration_hook('integrate_pre_load_theme', array(&$id_theme));

	// We already load the basic stuff?
	if (empty($settings['theme_id']) || $settings['theme_id'] != $id_theme)
	{
		$member = empty($user_info['id']) ? -1 : $user_info['id'];

		// Disable image proxy if we don't have SSL enabled
		if (empty($modSettings['force_ssl']))
			$image_proxy_enabled = false;

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $modSettings['settings_updated'])
		{
			$themeData = $temp;
			$flag = true;
		}
		elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $modSettings['settings_updated'])
			$themeData = $temp + array($member => array());
		else
			$themeData = array(-1 => array(), 0 => array(), $member => array());

		if (empty($flag))
		{
			// Load variables from the current or default theme, global or this user's.
			$result = $smcFunc['db_query']('', '
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
			while ($row = $smcFunc['db_fetch_assoc']($result))
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
			$smcFunc['db_free_result']($result);

			if (!empty($themeData[-1]))
				foreach ($themeData[-1] as $k => $v)
				{
					if (!isset($themeData[$member][$k]))
						$themeData[$member][$k] = $v;
				}

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
			// Only if we didn't already load that part of the cache...
			elseif (!isset($temp))
				cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
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

	// Check to see if we're forcing SSL
	if (!empty($modSettings['force_ssl']) && empty($maintenance) &&
		!httpsOn() && SMF != 'SSI')
	{
		if (isset($_GET['sslRedirect']))
		{
			loadLanguage('Errors');
			fatal_lang_error($txt['login_ssl_required']);
		}

		redirectexit(strtr($_SERVER['REQUEST_URL'], array('http://' => 'https://')) . (strpos($_SERVER['REQUEST_URL'], '?') > 0 ? ';' : '?') . 'sslRedirect');
	}

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = httpsOn() ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}
	if (isset($detected_url) && $detected_url != $boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses.
		if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);

			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && SMF != 'SSI')
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
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			// Caching is good ;).
			$oldurl = $boardurl;

			// Fix $boardurl and $scripturl.
			$boardurl = $detected_url;
			$scripturl = strtr($scripturl, array($oldurl => $boardurl));
			$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

			// Fix the theme urls...
			$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => $boardurl));
			$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => $boardurl));
			$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => $boardurl));
			$settings['images_url'] = strtr($settings['images_url'], array($oldurl => $boardurl));
			$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => $boardurl));
			$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => $boardurl));

			// And just a few mod settings :).
			$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], array($oldurl => $boardurl));
			$modSettings['avatar_url'] = strtr($modSettings['avatar_url'], array($oldurl => $boardurl));
			$modSettings['custom_avatar_url'] = strtr($modSettings['custom_avatar_url'], array($oldurl => $boardurl));

			// Clean up after loadBoard().
			if (isset($board_info['moderators']))
			{
				foreach ($board_info['moderators'] as $k => $dummy)
				{
					$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => $boardurl));
					$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . $boardurl));
				}
			}
			foreach ($context['linktree'] as $k => $dummy)
				$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
		}
	}
	// Set up the contextual user array.
	if (!empty($user_info))
	{
		$context['user'] = array(
			'id' => $user_info['id'],
			'is_logged' => !$user_info['is_guest'],
			'is_guest' => &$user_info['is_guest'],
			'is_admin' => &$user_info['is_admin'],
			'is_mod' => &$user_info['is_mod'],
			// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
			'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
			'name' => $user_info['username'],
			'language' => $user_info['language'],
			'email' => $user_info['email'],
			'ignoreusers' => $user_info['ignoreusers'],
		);
		if (!$context['user']['is_guest'])
			$context['user']['name'] = $user_info['name'];
		elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
			$context['user']['name'] = $txt['guest_title'];

		// Determine the current smiley set.
		$smiley_sets_known = explode(',', $modSettings['smiley_sets_known']);
		$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], $smiley_sets_known) && $user_info['smiley_set'] != 'none') || empty($modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) : $user_info['smiley_set'];
		$context['user']['smiley_set'] = $user_info['smiley_set'];
	}
	else
	{
		// What to do when there is no $user_info (e.g., an error very early in the login process)
		$context['user'] = array(
			'id' => -1,
			'is_logged' => false,
			'is_guest' => true,
			'is_mod' => false,
			'can_mod' => false,
			'name' => $txt['guest_title'],
			'language' => $language,
			'email' => '',
			'ignoreusers' => array(),
		);
		// Note we should stuff $user_info with some guest values also...
		$user_info = array(
			'id' => 0,
			'is_guest' => true,
			'is_admin' => false,
			'is_mod' => false,
			'username' => $txt['guest_title'],
			'language' => $language,
			'email' => '',
			'smiley_set' => '',
			'permissions' => array(),
			'groups' => array(),
			'ignoreusers' => array(),
			'possibly_robot' => true,
			'time_offset' => 0,
			'time_format' => $modSettings['time_format'],
		);
	}

	// Some basic information...
	if (!isset($context['html_headers']))
		$context['html_headers'] = '';
	if (!isset($context['javascript_files']))
		$context['javascript_files'] = array();
	if (!isset($context['css_files']))
		$context['css_files'] = array();
	if (!isset($context['css_header']))
		$context['css_header'] = array();
	if (!isset($context['javascript_inline']))
		$context['javascript_inline'] = array('standard' => array(), 'defer' => array());
	if (!isset($context['javascript_vars']))
		$context['javascript_vars'] = array();

	$context['login_url'] = $scripturl . '?action=login2';
	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $smcFunc['htmlspecialchars']($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? '' : $smcFunc['htmlspecialchars']($settings['header_logo_url']);
	$context['current_action'] = isset($_REQUEST['action']) ? $smcFunc['htmlspecialchars']($_REQUEST['action']) : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	$context['can_register'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] != 3;
	if (isset($modSettings['load_average']))
		$context['load_average'] = $modSettings['load_average'];

	// Detect the browser. This is separated out because it's also used in attachment downloads
	detectBrowser();

	// Set the top level linktree up.
	// Note that if we're dealing with certain very early errors (e.g., login) the linktree might not be set yet...
	if (empty($context['linktree']))
		$context['linktree'] = array();
	array_unshift($context['linktree'], array(
		'url' => $scripturl,
		'name' => $context['forum_name_html_safe']
	));

	// This allows sticking some HTML on the page output - useful for controls.
	$context['insert_after_template'] = '';

	if (!isset($txt))
		$txt = array();

	$simpleActions = array(
		'findmember',
		'helpadmin',
		'printpage',
		'spellcheck',
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

	$context['simple_action'] = in_array($context['current_action'], $simpleActions) ||
		(isset($simpleAreas[$context['current_action']]) && isset($_REQUEST['area']) && in_array($_REQUEST['area'], $simpleAreas[$context['current_action']])) ||
		(isset($simpleSubActions[$context['current_action']]) && in_array($context['current_subaction'], $simpleSubActions[$context['current_action']]));

	// See if theres any extra param to check.
	$requiresXML = false;
	foreach ($extraParams as $key => $extra)
		if (isset($_REQUEST[$extra]))
			$requiresXML = true;

	// Output is fully XML, so no need for the index template.
	if (isset($_REQUEST['xml']) && (in_array($context['current_action'], $xmlActions) || $requiresXML))
	{
		loadLanguage('index+Modifications');
		loadTemplate('Xml');
		$context['template_layers'] = array();
	}

	// These actions don't require the index template at all.
	elseif (!empty($context['simple_action']))
	{
		loadLanguage('index+Modifications');
		$context['template_layers'] = array();
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
		loadLanguage($required_files, '', false);

		// Custom template layers?
		if (isset($settings['theme_layers']))
			$context['template_layers'] = explode(',', $settings['theme_layers']);
		else
			$context['template_layers'] = array('html', 'body');
	}

	// Initialize the theme.
	loadSubTemplate('init', 'ignore');

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];

	// Set the character set from the template.
	$context['character_set'] = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8';
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	// Guests may still need a name.
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	// Make a special URL for the language.
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

	// And of course, let's load the default CSS file.
	loadCSSFile('index.css', array('minimize' => true, 'order_pos' => 1), 'smf_index');

	// Here is my luvly Responsive CSS
	loadCSSFile('responsive.css', array('force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9000), 'smf_responsive');

	if ($context['right_to_left'])
		loadCSSFile('rtl.css', array('order_pos' => 200), 'smf_rtl');

	// We allow theme variants, because we're cool.
	$context['theme_variant'] = '';
	$context['theme_variant_url'] = '';
	if (!empty($settings['theme_variants']))
	{
		// Overriding - for previews and that ilk.
		if (!empty($_REQUEST['variant']))
			$_SESSION['id_variant'] = $_REQUEST['variant'];
		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
			$context['theme_variant'] = !empty($_SESSION['id_variant']) ? $_SESSION['id_variant'] : (!empty($options['theme_variant']) ? $options['theme_variant'] : '');
		// If not a user variant, select the default.
		if ($context['theme_variant'] == '' || !in_array($context['theme_variant'], $settings['theme_variants']))
			$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants']) ? $settings['default_variant'] : $settings['theme_variants'][0];

		// Do this to keep things easier in the templates.
		$context['theme_variant'] = '_' . $context['theme_variant'];
		$context['theme_variant_url'] = $context['theme_variant'] . '/';

		if (!empty($context['theme_variant']))
		{
			loadCSSFile('index' . $context['theme_variant'] . '.css', array('order_pos' => 300), 'smf_index' . $context['theme_variant']);
			if ($context['right_to_left'])
				loadCSSFile('rtl' . $context['theme_variant'] . '.css', array('order_pos' => 400), 'smf_rtl' . $context['theme_variant']);
		}
	}

	// Let's be compatible with old themes!
	if (!function_exists('template_html_above') && in_array('html', $context['template_layers']))
		$context['template_layers'] = array('main');

	$context['tabindex'] = 1;

	// Compatibility.
	if (!isset($settings['theme_version']))
		$modSettings['memberCount'] = $modSettings['totalMembers'];

	// Default JS variables for use in every theme
	$context['javascript_vars'] = array(
		'smf_theme_url' => '"' . $settings['theme_url'] . '"',
		'smf_default_theme_url' => '"' . $settings['default_theme_url'] . '"',
		'smf_images_url' => '"' . $settings['images_url'] . '"',
		'smf_smileys_url' => '"' . $modSettings['smileys_url'] . '"',
		'smf_smiley_sets' => '"' . $modSettings['smiley_sets_known'] . '"',
		'smf_smiley_sets_default' => '"' . $modSettings['smiley_sets_default'] . '"',
		'smf_scripturl' => '"' . $scripturl . '"',
		'smf_iso_case_folding' => $context['server']['iso_case_folding'] ? 'true' : 'false',
		'smf_charset' => '"' . $context['character_set'] . '"',
		'smf_session_id' => '"' . $context['session_id'] . '"',
		'smf_session_var' => '"' . $context['session_var'] . '"',
		'smf_member_id' => $context['user']['id'],
		'ajax_notification_text' => JavaScriptEscape($txt['ajax_in_progress']),
		'help_popup_heading_text' => JavaScriptEscape($txt['help_popup']),
		'banned_text' => JavaScriptEscape(sprintf($txt['your_ban'], $context['user']['name'])),
	);

	// Add the JQuery library to the list of files to load.
	if (isset($modSettings['jquery_source']) && $modSettings['jquery_source'] == 'cdn')
		loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array('external' => true), 'smf_jquery');

	elseif (isset($modSettings['jquery_source']) && $modSettings['jquery_source'] == 'local')
		loadJavaScriptFile('jquery-3.2.1.min.js', array('seed' => false), 'smf_jquery');

	elseif (isset($modSettings['jquery_source'], $modSettings['jquery_custom']) && $modSettings['jquery_source'] == 'custom')
		loadJavaScriptFile($modSettings['jquery_custom'], array('external' => true), 'smf_jquery');

	// Auto loading? template_javascript() will take care of the local half of this.
	else
		loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array('external' => true), 'smf_jquery');

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

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
	{
		if (isBrowser('possibly_robot'))
		{
			// @todo Maybe move this somewhere better?!
			require_once($sourcedir . '/ScheduledTasks.php');

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			addInlineJavaScript('
		function smfAutoTask()
		{
			$.get(smf_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '");
		}
		window.setTimeout("smfAutoTask();", 1);');
		}
	}

	// And we should probably trigger the cron too.
	if (empty($modSettings['cron_is_real_cron']))
	{
		$ts = time();
		$ts -= $ts % 15;
		addInlineJavaScript('
	function triggerCron()
	{
		$.get(' . JavaScriptEscape($boardurl) . ' + "/cron.php?ts=' . $ts . '");
	}
	window.setTimeout(triggerCron, 1);', true);
	}

	// Filter out the restricted boards from the linktree
	if (!$user_info['is_admin'] && !empty($board))
	{
		foreach ($context['linktree'] as $k => $element)
		{
			if (!empty($element['groups']) &&
				(count(array_intersect($user_info['groups'], $element['groups'])) == 0 ||
					(!empty($modSettings['deny_boards_access']) && count(array_intersect($user_info['groups'], $element['deny_groups'])) != 0)))
			{
				$context['linktree'][$k]['name'] = $txt['restricted_board'];
				$context['linktree'][$k]['extra_before'] = '<i>';
				$context['linktree'][$k]['extra_after'] = '</i>';
				unset($context['linktree'][$k]['url']);
			}
		}
	}

	// Any files to include at this point?
	if (!empty($modSettings['integrate_theme_include']))
	{
		$theme_includes = explode(',', $modSettings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call load theme integration functions.
	call_integration_hook('integrate_load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
}

/**
 * Load a template - if the theme doesn't include it, use the default.
 * What this function does:
 *  - loads a template file with the name template_name from the current, default, or base theme.
 *  - detects a wrong default theme directory and tries to work around it.
 *
 * @uses the template_include() function to include the file.
 * @param string $template_name The name of the template to load
 * @param array|string $style_sheets The name of a single stylesheet or an array of names of stylesheets to load
 * @param bool $fatal If true, dies with an error message if the template cannot be found
 * @return boolean Whether or not the template was loaded
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	global $context, $settings, $txt, $scripturl, $boarddir, $db_show_debug;

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
		if ($db_show_debug === true)
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
	elseif (!file_exists($settings['default_theme_dir']) && file_exists($boarddir . '/Themes/default'))
	{
		$settings['default_theme_dir'] = $boarddir . '/Themes/default';
		$settings['template_dirs'][] = $settings['default_theme_dir'];

		if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
		{
			loadLanguage('Errors');
			echo '
<div class="alert errorbox">
	<a href="', $scripturl . '?action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id'], '" class="alert">', $txt['theme_dir_wrong'], '</a>
</div>';
		}

		loadTemplate($template_name);
	}
	// Cause an error otherwise.
	elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal)
		fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
	elseif ($fatal)
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
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
	global $context, $txt, $db_show_debug;

	if ($db_show_debug === true)
		$context['debug']['sub_templates'][] = $sub_template_name;

	// Figure out what the template function is named.
	$theme_function = 'template_' . $sub_template_name;
	if (function_exists($theme_function))
		$theme_function();
	elseif ($fatal === false)
		fatal_lang_error('theme_template_error', 'template', array((string) $sub_template_name));
	elseif ($fatal !== 'ignore')
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));

	// Are we showing debugging for templates?  Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && !in_array($sub_template_name, array('init', 'main_below')) && ob_get_length() > 0 && !isset($_REQUEST['xml']))
	{
		echo '
<div class="warningbox">---- ', $sub_template_name, ' ends ----</div>';
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
 * @param string $id An ID to stick on the end of the filename for caching purposes
 */
function loadCSSFile($fileName, $params = array(), $id = '')
{
	global $settings, $context, $modSettings;

	if (empty($context['css_files_order']))
		$context['css_files_order'] = array();

	$params['seed'] = (!array_key_exists('seed', $params) || (array_key_exists('seed', $params) && $params['seed'] === true)) ? (array_key_exists('browser_cache', $context) ? $context['browser_cache'] : '') : (is_string($params['seed']) ? '?' . ltrim($params['seed'], '?') : '');
	$params['force_current'] = isset($params['force_current']) ? $params['force_current'] : false;
	$themeRef = !empty($params['default_theme']) ? 'default_theme' : 'theme';
	$params['minimize'] = isset($params['minimize']) ? $params['minimize'] : true;
	$params['external'] = isset($params['external']) ? $params['external'] : false;
	$params['validate'] = isset($params['validate']) ? $params['validate'] : true;
	$params['order_pos'] = isset($params['order_pos']) ? (int) $params['order_pos'] : 3000;

	// If this is an external file, automatically set this to false.
	if (!empty($params['external']))
		$params['minimize'] = false;

	// Account for shorthand like admin.css?alp21 filenames
	$id = empty($id) ? strtr(str_replace('.css', '', basename($fileName)), '?', '_') : $id;
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
	}

	$mtime = empty($mtime) ? 0 : $mtime;

	// Add it to the array for use in the template
	if (!empty($fileName))
	{
		// find a free number/position
		while (isset($context['css_files_order'][$params['order_pos']]))
			$params['order_pos']++;
		$context['css_files_order'][$params['order_pos']] = $id;

		$context['css_files'][$id] = array('fileUrl' => $fileUrl, 'filePath' => $filePath, 'fileName' => $fileName, 'options' => $params, 'mtime' => $mtime);
	}

	if (!empty($context['right_to_left']) && !empty($params['rtl']))
		loadCSSFile($params['rtl'], array_diff_key($params, array('rtl' => 0)));

	if ($mtime > $modSettings['browser_cache'])
		updateSettings(array('browser_cache' => $mtime));
}

/**
 * Add a block of inline css code to be executed later
 *
 * - only use this if you have to, generally external css files are better, but for very small changes
 *   or for scripts that require help from PHP/whatever, this can be useful.
 * - all code added with this function is added to the same <style> tag so do make sure your css is valid!
 *
 * @param string $css Some css code
 * @return void|bool Adds the CSS to the $context['css_header'] array or returns if no CSS is specified
 */
function addInlineCss($css)
{
	global $context;

	// Gotta add something...
	if (empty($css))
		return false;

	$context['css_header'][] = $css;
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
 *
 * @param string $id An ID to stick on the end of the filename
 */
function loadJavaScriptFile($fileName, $params = array(), $id = '')
{
	global $settings, $context, $modSettings;

	$params['seed'] = (!array_key_exists('seed', $params) || (array_key_exists('seed', $params) && $params['seed'] === true)) ? (array_key_exists('browser_cache', $context) ? $context['browser_cache'] : '') : (is_string($params['seed']) ? '?' . ltrim($params['seed'], '?') : '');
	$params['force_current'] = isset($params['force_current']) ? $params['force_current'] : false;
	$themeRef = !empty($params['default_theme']) ? 'default_theme' : 'theme';
	$params['async'] = isset($params['async']) ? $params['async'] : false;
	$params['minimize'] = isset($params['minimize']) ? $params['minimize'] : false;
	$params['external'] = isset($params['external']) ? $params['external'] : false;
	$params['validate'] = isset($params['validate']) ? $params['validate'] : true;

	// If this is an external file, automatically set this to false.
	if (!empty($params['external']))
		$params['minimize'] = false;

	// Account for shorthand like admin.js?alp21 filenames
	$id = empty($id) ? strtr(str_replace('.js', '', basename($fileName)), '?', '_') : $id;
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
	}

	$mtime = empty($mtime) ? 0 : $mtime;

	// Add it to the array for use in the template
	if (!empty($fileName))
		$context['javascript_files'][$id] = array('fileUrl' => $fileUrl, 'filePath' => $filePath, 'fileName' => $fileName, 'options' => $params, 'mtime' => $mtime);

	if ($mtime > $modSettings['browser_cache'])
		updateSettings(array('browser_cache' => $mtime));
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
	global $context;

	if (!empty($key) && (!empty($value) || $value === '0'))
		$context['javascript_vars'][$key] = !empty($escape) ? JavaScriptEscape($value) : $value;
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
 * @return void|bool Adds the code to one of the $context['javascript_inline'] arrays or returns if no JS was specified
 */
function addInlineJavaScript($javascript, $defer = false)
{
	global $context;

	if (empty($javascript))
		return false;

	$context['javascript_inline'][($defer === true ? 'defer' : 'standard')][] = $javascript;
}

/**
 * Load a language file.  Tries the current and default themes as well as the user and global languages.
 *
 * @param string $template_name The name of a template file
 * @param string $lang A specific language to load this file from
 * @param bool $fatal Whether to die with an error if it can't be loaded
 * @param bool $force_reload Whether to load the file again if it's already loaded
 * @return string The language actually loaded.
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $user_info, $language, $settings, $context, $modSettings;
	global $db_show_debug, $sourcedir, $txt, $birthdayEmails, $txtBirthdayEmails;
	static $already_loaded = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	// Do we want the English version of language file as fallback?
	if (empty($modSettings['disable_language_fallback']) && $lang != 'english')
		loadLanguage($template_name, 'english', false);

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	// Make sure we have $settings - if not we're in trouble and need to find it!
	if (empty($settings['default_theme_dir']))
	{
		require_once($sourcedir . '/ScheduledTasks.php');
		loadEssentialThemeData();
	}

	// What theme are we in?
	$theme_name = basename($settings['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	// For each file open it up and write it out!
	foreach (explode('+', $template_name) as $template)
	{
		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($settings['theme_dir'], $template, $lang, $settings['theme_url']),
			array($settings['theme_dir'], $template, $language, $settings['theme_url']),
		);

		// Do we have a base theme to worry about?
		if (isset($settings['base_theme_dir']))
		{
			$attempts[] = array($settings['base_theme_dir'], $template, $lang, $settings['base_theme_url']);
			$attempts[] = array($settings['base_theme_dir'], $template, $language, $settings['base_theme_url']);
		}

		// Fall back on the default theme if necessary.
		$attempts[] = array($settings['default_theme_dir'], $template, $lang, $settings['default_theme_url']);
		$attempts[] = array($settings['default_theme_dir'], $template, $language, $settings['default_theme_url']);

		// Fall back on the English language if none of the preferred languages can be found.
		if (!in_array('english', array($lang, $language)))
		{
			$attempts[] = array($settings['theme_dir'], $template, 'english', $settings['theme_url']);
			$attempts[] = array($settings['default_theme_dir'], $template, 'english', $settings['default_theme_url']);
		}

		// Try to find the language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				// setlocale is required for basename() & pathinfo() to work properly on the selected language
				if (!empty($txt['lang_locale']) && !empty($modSettings['global_character_set']))
					setlocale(LC_CTYPE, $txt['lang_locale'] . '.' . $modSettings['global_character_set']);

				break;
			}
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
		}

		// For the sake of backward compatibility
		if (!empty($txt['emails']))
		{
			foreach ($txt['emails'] as $key => $value)
			{
				$txt[$key . '_subject'] = $value['subject'];
				$txt[$key . '_body'] = $value['body'];
			}
			$txt['emails'] = array();
		}
		// For sake of backward compatibility: $birthdayEmails is supposed to be
		// empty in a normal install. If it isn't it means the forum is using
		// something "old" (it may be the translation, it may be a mod) and this
		// code (like the piece above) takes care of converting it to the new format
		if (!empty($birthdayEmails))
		{
			foreach ($birthdayEmails as $key => $value)
			{
				$txtBirthdayEmails[$key . '_subject'] = $value['subject'];
				$txtBirthdayEmails[$key . '_body'] = $value['body'];
				$txtBirthdayEmails[$key . '_author'] = $value['author'];
			}
			$birthdayEmails = array();
		}
	}

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $theme_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
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
	global $scripturl, $smcFunc;

	// First check if we have this cached already.
	if (($boards = cache_get_data('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = $smcFunc['db_query']('', '
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
			if ($smcFunc['db_num_rows']($result) == 0)
				fatal_lang_error('parent_not_found', 'critical');
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => $scripturl . '?board=' . $row['id_board'] . '.0',
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
							'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}

				// If a moderator group exists for this board, add that moderator group for all children too
				if (!empty($row['id_moderator_group']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderator_groups'][$row['id_moderator_group']] = array(
							'id' => $row['id_moderator_group'],
							'name' => $row['group_name'],
							'href' => $scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'],
							'link' => '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $row['id_moderator_group'] . '">' . $row['group_name'] . '</a>'
						);
					}
			}
			$smcFunc['db_free_result']($result);
		}

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Attempt to reload our known languages.
 * It will try to choose only utf8 or non-utf8 languages.
 *
 * @param bool $use_cache Whether or not to use the cache
 * @return array An array of information about available languages
 */
function getLanguages($use_cache = true)
{
	global $context, $smcFunc, $settings, $modSettings;

	// Either we don't use the cache, or its expired.
	if (!$use_cache || ($context['languages'] = cache_get_data('known_languages', !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600)) == null)
	{
		// If we don't have our ucwords function defined yet, let's load the settings data.
		if (empty($smcFunc['ucwords']))
			reloadSettings();

		// If we don't have our theme information yet, let's get it.
		if (empty($settings['default_theme_dir']))
			loadTheme(0, false);

		// Default language directories to try.
		$language_directories = array(
			$settings['default_theme_dir'] . '/languages',
		);
		if (!empty($settings['actual_theme_dir']) && $settings['actual_theme_dir'] != $settings['default_theme_dir'])
			$language_directories[] = $settings['actual_theme_dir'] . '/languages';

		// We possibly have a base theme directory.
		if (!empty($settings['base_theme_dir']))
			$language_directories[] = $settings['base_theme_dir'] . '/languages';

		// Remove any duplicates.
		$language_directories = array_unique($language_directories);

		// Get a list of languages.
		$langList = !empty($modSettings['langList']) ? $smcFunc['json_decode']($modSettings['langList'], true) : array();
		$langList = is_array($langList) ? $langList : false;

		$catchLang = array();

		foreach ($language_directories as $language_dir)
		{
			// Can't look in here... doesn't exist!
			if (!file_exists($language_dir))
				continue;

			$dir = dir($language_dir);
			while ($entry = $dir->read())
			{
				// Look for the index language file... For good measure skip any "index.language-utf8.php" files
				if (!preg_match('~^index\.(.+[^-utf8])\.php$~', $entry, $matches))
					continue;

				if (!empty($langList) && !empty($langList[$matches[1]]))
					$langName = $langList[$matches[1]];

				else
				{
					$langName = $smcFunc['ucwords'](strtr($matches[1], array('_' => ' ')));

					// Get the line we need.
					$fp = @fopen($language_dir . '/' . $entry);

					// Yay!
					if ($fp)
					{
						while (($line = fgets($fp)) !== false)
						{
							preg_match('~\$txt\[\'native_name\'\] = \'(.+)\'\;~', $line, $matchNative);

							// Set the language's name.
							if (!empty($matchNative) && !empty($matchNative[1]))
							{
								$langName = un_htmlspecialchars($matchNative[1]);
								break;
							}
						}

						fclose($fp);
					}

					// Catch the language name.
					$catchLang[$matches[1]] = $langName;
				}

				// Build this language entry.
				$context['languages'][$matches[1]] = array(
					'name' => $langName,
					'selected' => false,
					'filename' => $matches[1],
					'location' => $language_dir . '/index.' . $matches[1] . '.php',
				);
			}
			$dir->close();
		}

		// Do we need to store the lang list?
		if (empty($langList))
			updateSettings(array('langList' => $smcFunc['json_encode']($catchLang)));

		// Let's cash in on this deal.
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages', $context['languages'], !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	return $context['languages'];
}

/**
 * Replace all vulgar words with respective proper words. (substring or whole words..)
 * What this function does:
 *  - it censors the passed string.
 *  - if the theme setting allow_no_censored is on, and the theme option
 *	show_no_censored is enabled, does not censor, unless force is also set.
 *  - it caches the list of censored words to reduce parsing.
 *
 * @param string &$text The text to censor
 * @param bool $force Whether to censor the text regardless of settings
 * @return string The censored text
 */
function censorText(&$text, $force = false)
{
	global $modSettings, $options, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && !empty($modSettings['allow_no_censored']) && !$force) || empty($modSettings['censor_vulgar']) || trim($text) === '')
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		if (!empty($modSettings['censorWholeWord']))
		{
			$charset = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];

			for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
			{
				$censor_vulgar[$i] = str_replace(array('\\\\\\*', '\\*', '&', '\''), array('[*]', '[^\s]*?', '&amp;', '&#039;'), preg_quote($censor_vulgar[$i], '/'));

				// Use the faster \b if we can, or something more complex if we can't
				$boundary_before = preg_match('/^\w/', $censor_vulgar[$i]) ? '\b' : ($charset === 'UTF-8' ? '(?<![\p{L}\p{M}\p{N}_])' : '(?<!\w)');
				$boundary_after = preg_match('/\w$/', $censor_vulgar[$i]) ? '\b' : ($charset === 'UTF-8' ? '(?![\p{L}\p{M}\p{N}_])' : '(?!\w)');

				$censor_vulgar[$i] = '/' . $boundary_before . $censor_vulgar[$i] . $boundary_after . '/' . (empty($modSettings['censorIgnoreCase']) ? '' : 'i') . ($charset === 'UTF-8' ? 'u' : '');
			}
		}
	}

	// Censoring isn't so very complicated :P.
	if (empty($modSettings['censorWholeWord']))
	{
		$func = !empty($modSettings['censorIgnoreCase']) ? 'str_ireplace' : 'str_replace';
		$text = $func($censor_vulgar, $censor_proper, $text);
	}
	else
		$text = preg_replace($censor_vulgar, $censor_proper, $text);

	return $text;
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
	global $context, $txt, $scripturl, $modSettings;
	global $boardurl, $boarddir;
	global $maintenance, $mtitle, $mmessage;
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
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		if (isset($_GET['debug']))
			header('content-type: application/xhtml+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

		// Don't cache error pages!!
		header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('cache-control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <pre><strong>%1$s</strong></pre> template or language file.  Please check the syntax and try again - remember, single quotes (<pre>\'</pre>) often have to be escaped with a slash (<pre>\\</pre>).  To see more specific error information from PHP, try <a href="' . $boardurl . '%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="' . $scripturl . '?theme=1">use the default theme</a>.';
			$txt['template_parse_errmsg'] = 'Unfortunately more information is not available at this time as to exactly what is wrong.';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html>
<html', !empty($context['right_to_left']) ? ' dir="rtl"' : '', '>
	<head>';
		if (isset($context['character_set']))
			echo '
		<meta charset="', $context['character_set'], '">';

		if (!empty($maintenance) && !allowedTo('admin_forum'))
			echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
		elseif (!allowedTo('admin_forum'))
			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
		else
		{
			$error = fetch_web_data($boardurl . strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));
			$error_array = error_get_last();
			if (empty($error) && ini_get('track_errors') && !empty($error_array))
				$error = $error_array['message'];
			if (empty($error))
				$error = $txt['template_parse_errmsg'];

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));

			if (!empty($error))
				echo '
		<hr>

		<div style="margin: 0 20px;"><pre>', strtr(strtr($error, array('<strong>' . $boarddir => '<strong>...', '<strong>' . strtr($boarddir, '\\', '/') => '<strong>...')), '\\', '/'), '</pre></div>';

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if (!isBrowser('gecko'))
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
 * Initialize a database connection.
 */
function loadDatabase()
{
	global $db_persist, $db_connection, $db_server, $db_user, $db_passwd;
	global $db_type, $db_name, $ssi_db_user, $ssi_db_passwd, $sourcedir, $db_prefix, $db_port, $db_mb4;

	// Figure out what type of database we are using.
	if (empty($db_type) || !file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
		$db_type = 'mysql';

	// Load the file for the database.
	require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

	$db_options = array();

	// Add in the port if needed
	if (!empty($db_port))
		$db_options['port'] = $db_port;

	if (!empty($db_mb4))
		$db_options['db_mb4'] = $db_mb4;

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (SMF == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
	{
		$options = array_merge($db_options, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));

		$db_connection = smf_db_initiate($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, $options);
	}

	// Either we aren't in SSI mode, or it failed.
	if (empty($db_connection))
	{
		$options = array_merge($db_options, array('persist' => $db_persist, 'dont_select_db' => SMF == 'SSI'));

		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $options);
	}

	// Safe guard here, if there isn't a valid connection lets put a stop to it.
	if (!$db_connection)
		display_db_error();

	// If in SSI mode fix up the prefix.
	if (SMF == 'SSI')
		db_fix_prefix($db_prefix, $db_name);
}

/**
 * Try to load up a supported caching method. This is saved in $cacheAPI if we are not overriding it.
 *
 * @param string $overrideCache Try to use a different cache method other than that defined in $cache_accelerator.
 * @param bool $fallbackSMF Use the default SMF method if the accelerator fails.
 * @return object|false A object of $cacheAPI, or False on failure.
 */
function loadCacheAccelerator($overrideCache = null, $fallbackSMF = true)
{
	global $sourcedir, $cacheAPI, $cache_accelerator, $cache_enable;

	// is caching enabled?
	if (empty($cache_enable) && empty($overrideCache))
		return false;

	// Not overriding this and we have a cacheAPI, send it back.
	if (empty($overrideCache) && is_object($cacheAPI))
		return $cacheAPI;
	elseif (is_null($cacheAPI))
		$cacheAPI = false;

	// Make sure our class is in session.
	require_once($sourcedir . '/Class-CacheAPI.php');

	// What accelerator we are going to try.
	$tryAccelerator = !empty($overrideCache) ? $overrideCache : !empty($cache_accelerator) ? $cache_accelerator : 'smf';
	$tryAccelerator = strtolower($tryAccelerator);

	// Do some basic tests.
	if (file_exists($sourcedir . '/CacheAPI-' . $tryAccelerator . '.php'))
	{
		require_once($sourcedir . '/CacheAPI-' . $tryAccelerator . '.php');

		$cache_class_name = $tryAccelerator . '_cache';
		$testAPI = new $cache_class_name();

		// No Support?  NEXT!
		if (!$testAPI->isSupported())
		{
			// Can we save ourselves?
			if (!empty($fallbackSMF) && is_null($overrideCache) && $tryAccelerator != 'smf')
				return loadCacheAccelerator(null, false);
			return false;
		}

		// Connect up to the accelerator.
		$testAPI->connect();

		// Don't set this if we are overriding the cache.
		if (is_null($overrideCache))
		{
			$cacheAPI = $testAPI;
			return $cacheAPI;
		}
		else
			return $testAPI;
	}
}

/**
 * Try to retrieve a cache entry. On failure, call the appropriate function.
 *
 * @param string $key The key for this entry
 * @param string $file The file associated with this entry
 * @param string $function The function to call
 * @param array $params Parameters to be passed to the specified function
 * @param int $level The cache level
 * @return string The cached data
 */
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $modSettings, $sourcedir;

	// @todo Why are we doing this if caching is disabled?

	if (function_exists('call_integration_hook'))
		call_integration_hook('pre_cache_quick_get', array(&$key, &$file, &$function, &$params, &$level));

	/* Refresh the cache if either:
		1. Caching is disabled.
		2. The cache level isn't high enough.
		3. The item has not been cached or the cached item expired.
		4. The cached item has a custom expiration condition evaluating to true.
		5. The expire time set in the cache item has passed (needed for Zend).
	*/
	if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
	{
		require_once($sourcedir . '/' . $file);
		$cache_block = call_user_func_array($function, $params);

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}

	// Some cached data may need a freshening up after retrieval.
	if (!empty($cache_block['post_retri_eval']))
		eval($cache_block['post_retri_eval']);

	if (function_exists('call_integration_hook'))
		call_integration_hook('post_cache_quick_get', array(&$cache_block));

	return $cache_block['data'];
}

/**
 * Puts value in the cache under key for ttl seconds.
 *
 * - It may "miss" so shouldn't be depended on
 * - Uses the cache engine chosen in the ACP and saved in settings.php
 * - It supports:
 *	 Xcache: https://xcache.lighttpd.net/wiki/XcacheApi
 *	 memcache: https://php.net/memcache
 *	 APC: https://php.net/apc
 *   APCu: https://php.net/book.apcu
 *	 Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
 *	 Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
 *
 * @param string $key A key for this value
 * @param mixed $value The data to cache
 * @param int $ttl How long (in seconds) the data should be cached for
 */
function cache_put_data($key, $value, $ttl = 120)
{
	global $smcFunc, $cache_enable, $cacheAPI;
	global $cache_hits, $cache_count, $db_show_debug;

	if (empty($cache_enable) || empty($cacheAPI))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(isset($smcFunc['json_encode']) ? $smcFunc['json_encode']($value) : json_encode($value)));
		$st = microtime(true);
	}

	// The API will handle the rest.
	$value = $value === null ? null : (isset($smcFunc['json_encode']) ? $smcFunc['json_encode']($value) : json_encode($value));
	$cacheAPI->putData($key, $value, $ttl);

	if (function_exists('call_integration_hook'))
		call_integration_hook('cache_put_data', array(&$key, &$value, &$ttl));

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
}

/**
 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
 * - It may often "miss", so shouldn't be depended on.
 * - It supports the same as cache_put_data().
 *
 * @param string $key The key for the value to retrieve
 * @param int $ttl The maximum age of the cached data
 * @return string The cached data or null if nothing was loaded
 */
function cache_get_data($key, $ttl = 120)
{
	global $smcFunc, $cache_enable, $cacheAPI;
	global $cache_hits, $cache_count, $cache_misses, $cache_count_misses, $db_show_debug;

	if (empty($cache_enable) || empty($cacheAPI))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime(true);
		$original_key = $key;
	}

	// Ask the API to get the data.
	$value = $cacheAPI->getData($key, $ttl);

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
		$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;

		if (empty($value))
		{
			if (!is_array($cache_misses))
				$cache_misses = array();

			$cache_count_misses = isset($cache_count_misses) ? $cache_count_misses + 1 : 1;
			$cache_misses[$cache_count_misses] = array('k' => $original_key, 'd' => 'get');
		}
	}

	if (function_exists('call_integration_hook') && isset($value))
		call_integration_hook('cache_get_data', array(&$key, &$ttl, &$value));

	return empty($value) ? null : (isset($smcFunc['json_decode']) ? $smcFunc['json_decode']($value, true) : smf_json_decode($value, true));
}

/**
 * Empty out the cache in use as best it can
 *
 * It may only remove the files of a certain type (if the $type parameter is given)
 * Type can be user, data or left blank
 * 	- user clears out user data
 *  - data clears out system / opcode data
 *  - If no type is specified will perform a complete cache clearing
 * For cache engines that do not distinguish on types, a full cache flush will be done
 *
 * @param string $type The cache type ('memcached', 'apc', 'xcache', 'zend' or something else for SMF's file cache)
 */
function clean_cache($type = '')
{
	global $cacheAPI;

	// If we can't get to the API, can't do this.
	if (empty($cacheAPI))
		return;

	// Ask the API to do the heavy lifting. cleanCache also calls invalidateCache to be sure.
	$cacheAPI->cleanCache($type);

	call_integration_hook('integrate_clean_cache');
	clearstatcache();
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
	global $modSettings, $smcFunc, $image_proxy_enabled, $user_info;

	// Come on!
	if (empty($data))
		return array();

	// Set a nice default var.
	$image = '';

	// Gravatar has been set as mandatory!
	if (!empty($modSettings['gravatarOverride']))
	{
		if (!empty($modSettings['gravatarAllowExtraEmail']) && !empty($data['avatar']) && stristr($data['avatar'], 'gravatar://'))
			$image = get_gravatar_url($smcFunc['substr']($data['avatar'], 11));

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

				elseif (!empty($modSettings['gravatarAllowExtraEmail']))
					$image = get_gravatar_url($smcFunc['substr']($data['avatar'], 11));
			}

			// External url.
			else
			{
				// Using ssl?
				if (!empty($modSettings['force_ssl']) && $image_proxy_enabled && stripos($data['avatar'], 'http://') !== false && empty($user_info['possibly_robot']))
					$image = get_proxied_url($data['avatar']);

				// Just a plain external url.
				else
					$image = (stristr($data['avatar'], 'http://') || stristr($data['avatar'], 'https://')) ? $data['avatar'] : $modSettings['avatar_url'] . '/' . $data['avatar'];
			}
		}

		// Perhaps this user has an attachment as avatar...
		elseif (!empty($data['filename']))
			$image = $modSettings['custom_avatar_url'] . '/' . $data['filename'];

		// Right... no avatar... use our default image.
		else
			$image = $modSettings['avatar_url'] . '/default.png';
	}

	call_integration_hook('integrate_set_avatar_data', array(&$image, &$data));

	// At this point in time $image has to be filled unless you chose to force gravatar and the user doesn't have the needed data to retrieve it... thus a check for !empty() is still needed.
	if (!empty($image))
		return array(
			'name' => !empty($data['avatar']) ? $data['avatar'] : '',
			'image' => '<img class="avatar" src="' . $image . '" />',
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