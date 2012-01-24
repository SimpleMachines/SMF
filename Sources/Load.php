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

/*	This file has the hefty job of loading information for the forum.  It uses
	the following functions:

	void reloadSettings()
		- loads or reloads the $modSettings array.
		- loads any integration settings, SMF_INTEGRATION_SETTINGS, etc.

	void loadUserSettings()
		- sets up the $user_info array
		- assigns $user_info['query_wanna_see_board'] for what boards the user can see.
		- first checks for cookie or intergration validation.
		- uses the current session if no integration function or cookie is found.
		- checks password length, if member is activated and the login span isn't over.
		- if validation fails for the user, $id_member is set to 0.
		- updates the last visit time when needed.

	void loadBoard()
		- sets up the $board_info array for current board information.
		- if cache is enabled, the $board_info array is stored in cache.
		- redirects to appropriate post if only message id is requested.
		- is only used when inside a topic or board.
		- determines the local moderators for the board.
		- adds group id 3 if the user is a local moderator for the board they are in.
		- prevents access if user is not in proper group nor a local moderator of the board.

	void loadPermissions()
		// !!!

	array loadMemberData(array members, bool is_name = false, string set = 'normal')
		// !!!

	bool loadMemberContext(int id_member)
		// !!!

	void loadTheme(int id_theme = auto_detect)
		// !!!

	void loadTemplate(string template_name, array style_sheets = array(), bool fatal = true)
		- loads a template file with the name template_name from the current,
		  default, or base theme.
		- uses the template_include() function to include the file.
		- detects a wrong default theme directory and tries to work around it.
		- if fatal is true, dies with an error message if the template cannot
		  be found.

	void loadSubTemplate(string sub_template_name, bool fatal = false)
		- loads the sub template specified by sub_template_name, which must be
		  in an already-loaded template.
		- if ?debug is in the query string, shows administrators a marker after
		  every sub template for debugging purposes.

	string loadLanguage(string template_name, string language = default, bool fatal = true, bool force_reload = false)
		// !!!

	array getBoardParents(int id_parent)
		- finds all the parents of id_parent, and that board itself.
		- additionally detects the moderators of said boards.
		- returns an array of information about the boards found.

	string &censorText(string &text, bool force = false)
		- censors the passed string.
		- if the theme setting allow_no_censored is on, and the theme option
		  show_no_censored is enabled, does not censor - unless force is set.
		- caches the list of censored words to reduce parsing.

	void template_include(string filename, bool only_once = false)
		- loads the template or language file specified by filename.
		- if once is true, only includes the file once (like include_once.)
		- uses eval unless disableTemplateEval is enabled.
		- outputs a parse error if the file did not exist or contained errors.
		- attempts to detect the error and line, and show detailed information.

	void loadSession()
		// !!!

	void loadDatabase()
		- takes care of mysql_set_mode, if set.
		// !!!

	bool sessionOpen(string session_save_path, string session_name)
	bool sessionClose()
	bool sessionRead(string session_id)
	bool sessionWrite(string session_id, string data)
	bool sessionDestroy(string session_id)
	bool sessionGC(int max_lifetime)
		- implementations of PHP's session API.
		- handle the session data in the database (more scalable.)
		- use the databaseSession_lifetime setting for garbage collection.
		- set by loadSession().

	void cache_put_data(string key, mixed value, int ttl = 120)
		- puts value in the cache under key for ttl seconds.
		- may "miss" so shouldn't be depended on, and may go to any of many
		  various caching servers.
		- supports eAccelerator, Turck MMCache, ZPS, and memcached.

	mixed cache_get_data(string key, int ttl = 120)
		- gets the value from the cache specified by key, so long as it is not
		  older than ttl seconds.
		- may often "miss", so shouldn't be depended on.
		- supports the same as cache_put_data().

	void get_memcached_server(int recursion_level = 3)
		- used by cache_get_data() and cache_put_data().
		- attempts to connect to a random server in the cache_memcached
		  setting.
		- recursively calls itself up to recursion_level times.
*/

// Load the $modSettings array.
function reloadSettings()
{
	global $modSettings, $boarddir, $smcFunc, $txt, $db_character_set, $context, $sourcedir;

	// Most database systems have not set UTF-8 as their default input charset.
	if (!empty($db_character_set))
		$smcFunc['db_query']('set_character_set', '
			SET NAMES ' . $db_character_set,
			array(
			)
		);

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
			db_fatal_error();
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

		if (!empty($modSettings['cache_enable']))
			cache_put_data('modSettings', $modSettings, 90);
	}

	// UTF-8 in regular expressions is unsupported on PHP(win) versions < 4.2.3.
	$utf8 = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8' && (strpos(strtolower(PHP_OS), 'win') === false || @version_compare(PHP_VERSION, '4.2.3') != -1);

	// Set a list of common functions.
	$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';
	$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e\', \'$smcFunc[\\\'entity_fix\\\'](\\\'\\2\\\')\', ', ')') : array('', '');

	// Preg_replace can handle complex characters only for higher PHP versions.
	$space_chars = $utf8 ? (@version_compare(PHP_VERSION, '4.3.3') != -1 ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : "\xC2\xA0\xC2\xAD\xE2\x80\x80-\xE2\x80\x8F\xE2\x80\x9F\xE2\x80\xAF\xE2\x80\x9F\xE3\x80\x80\xEF\xBB\xBF") : '\x00-\x08\x0B\x0C\x0E-\x19\xA0';

	$smcFunc += array(
		'entity_fix' => create_function('$string', '
			$num = substr($string, 0, 1) === \'x\' ? hexdec(substr($string, 1)) : (int) $string;
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202E ? \'\' : \'&#\' . $num . \';\';'),
		'htmlspecialchars' => create_function('$string, $quote_style = ENT_COMPAT, $charset = \'ISO-8859-1\'', '
			global $smcFunc;
			return ' . strtr($ent_check[0], array('&' => '&amp;')) . 'htmlspecialchars($string, $quote_style, ' . ($utf8 ? '\'UTF-8\'' : '$charset') . ')' . $ent_check[1] . ';'),
		'htmltrim' => create_function('$string', '
			global $smcFunc;
			return preg_replace(\'~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+$~' . ($utf8 ? 'u' : '') . '\', \'\', ' . implode('$string', $ent_check) . ');'),
		'strlen' => create_function('$string', '
			global $smcFunc;
			return strlen(preg_replace(\'~' . $ent_list . ($utf8 ? '|.~u' : '~') . '\', \'_\', ' . implode('$string', $ent_check) . '));'),
		'strpos' => create_function('$haystack, $needle, $offset = 0', '
			global $smcFunc;
			$haystack_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$haystack', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$haystack_size = count($haystack_arr);
			if (strlen($needle) === 1)
			{
				$result = array_search($needle, array_slice($haystack_arr, $offset));
				return is_int($result) ? $result + $offset : false;
			}
			else
			{
				$needle_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\',  ' . implode('$needle', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$needle_size = count($needle_arr);

				$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
				while (is_int($result))
				{
					$offset += $result;
					if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
						return $offset;
					$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
				}
				return false;
			}'),
		'substr' => create_function('$string, $start, $length = null', '
			global $smcFunc;
			$ent_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$string', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			return $length === null ? implode(\'\', array_slice($ent_arr, $start)) : implode(\'\', array_slice($ent_arr, $start, $length));'),
		'strtolower' => $utf8 ? (function_exists('mb_strtolower') ? create_function('$string', '
			return mb_strtolower($string, \'UTF-8\');') : create_function('$string', '
			global $sourcedir;
			require_once($sourcedir . \'/Subs-Charset.php\');
			return utf8_strtolower($string);')) : 'strtolower',
		'strtoupper' => $utf8 ? (function_exists('mb_strtoupper') ? create_function('$string', '
			return mb_strtoupper($string, \'UTF-8\');') : create_function('$string', '
			global $sourcedir;
			require_once($sourcedir . \'/Subs-Charset.php\');
			return utf8_strtoupper($string);')) : 'strtoupper',
		'truncate' => create_function('$string, $length', (empty($modSettings['disableEntityCheck']) ? '
			global $smcFunc;
			$string = ' . implode('$string', $ent_check) . ';' : '') . '
			preg_match(\'~^(' . $ent_list . '|.){\' . $smcFunc[\'strlen\'](substr($string, 0, $length)) . \'}~'.  ($utf8 ? 'u' : '') . '\', $string, $matches);
			$string = $matches[0];
			while (strlen($string) > $length)
				$string = preg_replace(\'~(?:' . $ent_list . '|.)$~'.  ($utf8 ? 'u' : '') . '\', \'\', $string);
			return $string;'),
		'ucfirst' => $utf8 ? create_function('$string', '
			global $smcFunc;
			return $smcFunc[\'strtoupper\']($smcFunc[\'substr\']($string, 0, 1)) . $smcFunc[\'substr\']($string, 1);') : 'ucfirst',
		'ucwords' => $utf8 ? create_function('$string', '
			global $smcFunc;
			$words = preg_split(\'~([\s\r\n\t]+)~\', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			for ($i = 0, $n = count($words); $i < $n; $i += 2)
				$words[$i] = $smcFunc[\'ucfirst\']($words[$i]);
			return implode(\'\', $words);') : 'ucwords',
	);

	// Setting the timezone is a requirement for some functions in PHP >= 5.1.
	if (isset($modSettings['default_timezone']) && function_exists('date_default_timezone_set'))
		date_default_timezone_set($modSettings['default_timezone']);

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

			if (!empty($modSettings['load_average']))
				cache_put_data('loadavg', $modSettings['load_average'], 90);
		}

		if (!empty($modSettings['loadavg_forum']) && !empty($modSettings['load_average']) && $modSettings['load_average'] >= $modSettings['loadavg_forum'])
			db_fatal_error(true);
	}

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = isset($modSettings['admin_features']) ? in_array('pm', explode(',', $modSettings['admin_features'])) : true;

	// Integration is cool.
	if (defined('SMF_INTEGRATION_SETTINGS'))
	{
		$integration_settings = unserialize(SMF_INTEGRATION_SETTINGS);
		foreach ($integration_settings as $hook => $function)
			add_integration_function($hook, $function, false);
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

	// Call pre load integration functions.
	call_integration_hook('integrate_pre_load');
}

// Load all the important user information...
function loadUserSettings()
{
	global $modSettings, $user_settings, $sourcedir, $smcFunc;
	global $cookiename, $user_info, $language;

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
		// Fix a security hole in PHP 4.3.9 and below...
		if (preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~i', $_COOKIE[$cookiename]) == 1)
		{
			list ($id_member, $password) = @unserialize($_COOKIE[$cookiename]);
			$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
		}
		else
			$id_member = 0;
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty($modSettings['disableCheckUA'])))
	{
		// !!! Perhaps we can do some more checking on this, such as on the first octet of the IP?
		list ($id_member, $password, $login_span) = @unserialize($_SESSION['login_' . $cookiename]);
		$id_member = !empty($id_member) && strlen($password) == 40 && $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT mem.*, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
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

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-1 passwords should be 40 characters long.
			elseif (strlen($password) == 40)
				$check = sha1($user_settings['passwd'] . $user_settings['password_salt']) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
		{
			require_once($sourcedir . '/LogInOut.php');
			validatePasswordFlood(!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member, !empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false, $id_member != 0);
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
		if (SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml') && empty($_SESSION['id_msg_last_visit']) && (empty($modSettings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
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
		foreach ($user_info['groups'] as $k => $v)
			$user_info['groups'][$k] = (int) $v;

		// This is a logged in user, so definitely not a spider.
		$user_info['possibly_robot'] = false;
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[$cookiename]))
			$_COOKIE[$cookiename] = '';

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
			$user_info['possibly_robot'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || strpos($ci_user_agent, 'googlebot') !== false || strpos($ci_user_agent, 'slurp') !== false || strpos($ci_user_agent, 'crawl') !== false;
		}
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
		'time_offset' => empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset'],
		'avatar' => array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0
		),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($modSettings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);
	$user_info['groups'] = array_unique($user_info['groups']);
	// Make sure that the last item in the ignore boards array is valid.  If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Do we have any languages to validate this?
	if (!empty($modSettings['userLanguage']) && (!empty($_GET['language']) || !empty($_SESSION['language'])))
		$languages = getLanguages();

	// Allow the user to change their language if its valid.
	if (!empty($modSettings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
	{
		$user_info['language'] = strtr($_GET['language'], './\\:', '____');
		$_SESSION['language'] = $user_info['language'];
	}
	elseif (!empty($modSettings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
		$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');

	// Just build this here, it makes it easier to change/use - administrators can see all boards.
	if ($user_info['is_admin'])
		$user_info['query_see_board'] = '1=1';
	// Otherwise just the groups in $user_info['groups'].
	else
		$user_info['query_see_board'] = '(FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $user_info['groups']) . ', b.member_groups) != 0' . (isset($user_info['mod_cache']) ? ' OR ' . $user_info['mod_cache']['mq'] : '') . ')';

	// Build the list of boards they WANT to see.
	// This will take the place of query_see_boards in certain spots, so it better include the boards they can see also

	// If they aren't ignoring any boards then they want to see all the boards they can see
	if (empty($user_info['ignoreboards']))
		$user_info['query_wanna_see_board'] = $user_info['query_see_board'];
	// Ok I guess they don't want to see all the boards
	else
		$user_info['query_wanna_see_board'] = '(' . $user_info['query_see_board'] . ' AND b.id_board NOT IN (' . implode(',', $user_info['ignoreboards']) . '))';
}

// Check for moderators and see if they have access to the board.
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
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === NULL)
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
		$board_info = array('moderators' => array());
		return;
	}

	if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
	{
		// !!! SLOW?
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
		$request = $smcFunc['db_query']('', '
			SELECT
				c.id_cat, b.name AS bname, b.description, b.num_topics, b.member_groups,
				b.id_parent, c.name AS cname, IFNULL(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect,
				b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
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
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
			);

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] == '' ? array() : explode(',', $row['member_groups']);

			do
			{
				if (!empty($row['id_moderator']))
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);
			}
			while ($row = $smcFunc['db_fetch_assoc']($request));

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				$smcFunc['db_free_result']($request); // Free the previous result

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
				// !!! SLOW?
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
		// Now check if the user is a moderator.
		$user_info['is_mod'] = isset($board_info['moderators'][$user_info['id']]);

		if (count(array_intersect($user_info['groups'], $board_info['groups'])) == 0 && !$user_info['is_admin'])
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

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && ($board_info['error'] != 'access' || !$user_info['is_mod']))
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
			header('HTTP/1.1 403 Forbidden');
			die;
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

// Load this user's permissions.
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
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} && add_deny = 0)' : '';

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
	}
}

// Loads an array of users' data by ID or member_name.
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $modSettings, $board_info, $smcFunc;

	// Can't just look for no users :P.
	if (empty($users))
		return false;

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

	if ($set == 'normal')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.icq, mem.aim, mem.yim, mem.msn, mem.posts, mem.last_login,
			mem.karma_good, mem.id_post_group, mem.karma_bad, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mem.buddy_list, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group, mem.is_activated, mem.warning,
			CASE WHEN mem.id_group = 0 OR mg.stars = {string:blank_string} THEN pg.stars ELSE mg.stars END AS stars' . (!empty($modSettings['titlesEnable']) ? ',
			mem.usertitle' : '');
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'profile')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.openid_uri, mem.birthdate, mem.icq, mem.aim, mem.yim, mem.msn, mem.posts, mem.last_login, mem.karma_good,
			mem.karma_bad, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group, mem.id_theme, mem.buddy_list,
			mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from, mem.time_offset' . (!empty($modSettings['titlesEnable']) ? ', mem.usertitle' : '') . ',
			mem.time_format, mem.secret_question, mem.is_activated, mem.additional_groups, mem.smiley_set, mem.show_online,
			mem.total_time_logged_in, mem.id_post_group, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group, mem.ignore_boards, mem.warning,
			CASE WHEN mem.id_group = 0 OR mg.stars = {string:blank_string} THEN pg.stars ELSE mg.stars END AS stars, mem.password_salt, mem.pm_prefs';
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'minimal')
	{
		$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
		$select_tables = '';
	}
	else
		trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);

	if (!empty($users))
	{
		// Load the member's data.
		$request = $smcFunc['db_query']('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank_string' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
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
			SELECT *
			FROM {db_prefix}themes
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);
	}

	if (!empty($new_loaded_ids) && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators?  If so, fix their group data...
	if (!empty($loaded_ids) && !empty($board_info['moderators']) && $set === 'normal' && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) !== 0)
	{
		if (($row = cache_get_data('moderator_group_info', 480)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT group_name AS member_group, online_color AS member_group_color, stars
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

			// If the Moderator group has no color or stars, but their group does... don't overwrite.
			if (!empty($row['stars']))
				$user_profile[$id]['stars'] = $row['stars'];
			if (!empty($row['member_group_color']))
				$user_profile[$id]['member_group_color'] = $row['member_group_color'];
		}
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}

// Loads the user's basic values... meant for template/theme usage.
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $txt, $scripturl, $user_info;
	global $context, $modSettings, $board_info, $settings;
	global $smcFunc;
	static $dataLoaded = array();

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
	censorText($profile['location']);

	// Set things up to be used before hand.
	$gendertxt = $profile['gender'] == 2 ? $txt['female'] : ($profile['gender'] == 1 ? $txt['male'] : '');
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br />', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);

	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
	$profile['stars'] = empty($profile['stars']) ? array('', '') : explode('#', $profile['stars']);
	// Setup the buddy status here (One whole in_array call saved :P)
	$profile['buddy'] = in_array($profile['id_member'], $user_info['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

	// If we're always html resizing, assume it's too large.
	if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
	{
		$avatar_width = !empty($modSettings['avatar_max_width_external']) ? ' width="' . $modSettings['avatar_max_width_external'] . '"' : '';
		$avatar_height = !empty($modSettings['avatar_max_height_external']) ? ' height="' . $modSettings['avatar_max_height_external'] . '"' : '';
	}
	else
	{
		$avatar_width = '';
		$avatar_height = '';
	}

	// What a monstrous array...
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'is_buddy' => $profile['buddy'],
		'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
		'buddies' => $buddy_list,
		'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
		'blurb' => $profile['personal_text'],
		'gender' => array(
			'name' => $gendertxt,
			'image' => !empty($profile['gender']) ? '<img class="gender" src="' . $settings['images_url'] . '/' . ($profile['gender'] == 1 ? 'Male' : 'Female') . '.gif" alt="' . $gendertxt . '" />' : ''
		),
		'website' => array(
			'title' => $profile['website_title'],
			'url' => $profile['website_url'],
		),
		'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
		'signature' => $profile['signature'],
		'location' => $profile['location'],
		'icq' => $profile['icq'] != '' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['icq'],
			'href' => 'http://www.icq.com/whitepages/about_me.php?uin=' . $profile['icq'],
			'link' => '<a class="icq new_win" href="http://www.icq.com/whitepages/about_me.php?uin=' . $profile['icq'] . '" target="_blank" title="' . $txt['icq_title'] . ' - ' . $profile['icq'] . '"><img src="http://status.icq.com/online.gif?img=5&amp;icq=' . $profile['icq'] . '" alt="' . $txt['icq_title'] . ' - ' . $profile['icq'] . '" width="18" height="18" /></a>',
			'link_text' => '<a class="icq extern" href="http://www.icq.com/whitepages/about_me.php?uin=' . $profile['icq'] . '" title="' . $txt['icq_title'] . ' - ' . $profile['icq'] . '">' . $profile['icq'] . '</a>',
		) : array('name' => '', 'add' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'aim' => $profile['aim'] != '' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['aim'],
			'href' => 'aim:goim?screenname=' . urlencode(strtr($profile['aim'], array(' ' => '%20'))) . '&amp;message=' . $txt['aim_default_message'],
			'link' => '<a class="aim" href="aim:goim?screenname=' . urlencode(strtr($profile['aim'], array(' ' => '%20'))) . '&amp;message=' . $txt['aim_default_message'] . '" title="' . $txt['aim_title'] . ' - ' . $profile['aim'] . '"><img src="' . $settings['images_url'] . '/aim.gif" alt="' . $txt['aim_title'] . ' - ' . $profile['aim'] . '" /></a>',
			'link_text' => '<a class="aim" href="aim:goim?screenname=' . urlencode(strtr($profile['aim'], array(' ' => '%20'))) . '&amp;message=' . $txt['aim_default_message'] . '" title="' . $txt['aim_title'] . ' - ' . $profile['aim'] . '">' . $profile['aim'] . '</a>'
		) : array('name' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'yim' => $profile['yim'] != '' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['yim'],
			'href' => 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($profile['yim']),
			'link' => '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($profile['yim']) . '" title="' . $txt['yim_title'] . ' - ' . $profile['yim'] . '"><img src="http://opi.yahoo.com/online?u=' . urlencode($profile['yim']) . '&amp;m=g&amp;t=0" alt="' . $txt['yim_title'] . ' - ' . $profile['yim'] . '" /></a>',
			'link_text' => '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($profile['yim']) . '" title="' . $txt['yim_title'] . ' - ' . $profile['yim'] . '">' . $profile['yim'] . '</a>'
		) : array('name' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'msn' => $profile['msn'] !='' && (empty($modSettings['guest_hideContacts']) || !$user_info['is_guest']) ? array(
			'name' => $profile['msn'],
			'href' => 'http://members.msn.com/' . $profile['msn'],
			'link' => '<a class="msn new_win" href="http://members.msn.com/' . $profile['msn'] . '" title="' . $txt['msn_title'] . ' - ' . $profile['msn'] . '"><img src="' . $settings['images_url'] . '/msntalk.gif" alt="' . $txt['msn_title'] . ' - ' . $profile['msn'] . '" /></a>',
			'link_text' => '<a class="msn new_win" href="http://members.msn.com/' . $profile['msn'] . '" title="' . $txt['msn_title'] . ' - ' . $profile['msn'] . '">' . $profile['msn'] . '</a>'
		) : array('name' => '', 'href' => '', 'link' => '', 'link_text' => ''),
		'real_posts' => $profile['posts'],
		'posts' => $profile['posts'] > 500000 ? $txt['geek'] : comma_format($profile['posts']),
		'avatar' => array(
			'name' => $profile['avatar'],
			'image' => $profile['avatar'] == '' ? ($profile['id_attach'] > 0 ? '<img class="avatar" src="' . (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) . '" alt="" />' : '') : (stristr($profile['avatar'], 'http://') ? '<img class="avatar" src="' . $profile['avatar'] . '"' . $avatar_width . $avatar_height . ' alt="" />' : '<img class="avatar" src="' . $modSettings['avatar_url'] . '/' . htmlspecialchars($profile['avatar']) . '" alt="" />'),
			'href' => $profile['avatar'] == '' ? ($profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) : '') : (stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar']),
			'url' => $profile['avatar'] == '' ? '' : (stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar'])
		),
		'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
		'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
		'karma' => array(
			'good' => $profile['karma_good'],
			'bad' => $profile['karma_bad'],
			'allow' => !$user_info['is_guest'] && !empty($modSettings['karmaMode']) && $user_info['id'] != $user && allowedTo('karma_edit') &&
			($user_info['posts'] >= $modSettings['karmaMinPosts'] || $user_info['is_admin']),
		),
		'ip' => htmlspecialchars($profile['member_ip']),
		'ip2' => htmlspecialchars($profile['member_ip2']),
		'online' => array(
			'is_online' => $profile['is_online'],
			'text' => $txt[$profile['is_online'] ? 'online' : 'offline'],
			'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
			'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
			'image_href' => $settings['images_url'] . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.gif',
			'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
		),
		'language' => $smcFunc['ucwords'](strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))),
		'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
		'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
		'options' => $profile['options'],
		'is_guest' => false,
		'group' => $profile['member_group'],
		'group_color' => $profile['member_group_color'],
		'group_id' => $profile['id_group'],
		'post_group' => $profile['post_group'],
		'post_group_color' => $profile['post_group_color'],
		'group_stars' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['stars'][1]) ? $settings['images_url'] . '/' . $profile['stars'][1] : '') . '" alt="*" />', empty($profile['stars'][0]) || empty($profile['stars'][1]) ? 0 : $profile['stars'][0]),
		'warning' => $profile['warning'],
		'warning_status' => !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : (''))),
		'local_time' => timeformat(time() + ($profile['time_offset'] - $user_info['time_offset']) * 3600, false),
	);

	// First do a quick run through to make sure there is something to be shown.
	$memberContext[$user]['has_messenger'] = false;
	foreach (array('icq', 'msn', 'aim', 'yim') as $messenger)
	{
		if (!isset($context['disabled_fields'][$messenger]) && !empty($memberContext[$user][$messenger]['link']))
		{
			$memberContext[$user]['has_messenger'] = true;
			break;
		}
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($modSettings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($modSettings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (empty($custom['title']) || empty($profile['options'][$custom['colname']]))
				continue;

			$value = $profile['options'][$custom['colname']];

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
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	return true;
}

function detectBrowser()
{
	global $context, $user_info;

	// The following determines the user agent (browser) as best it can.
	$context['browser'] = array(
		'is_opera' => strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false,
		'is_opera6' => strpos($_SERVER['HTTP_USER_AGENT'], 'Opera 6') !== false,
		'is_opera7' => strpos($_SERVER['HTTP_USER_AGENT'], 'Opera 7') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'Opera/7') !== false,
		'is_opera8' => strpos($_SERVER['HTTP_USER_AGENT'], 'Opera 8') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'Opera/8') !== false,
		'is_opera9' => preg_match('~Opera[ /]9(?!\\.[89])~', $_SERVER['HTTP_USER_AGENT']) === 1,
		'is_opera10' => preg_match('~Opera[ /]10\\.~', $_SERVER['HTTP_USER_AGENT']) === 1 || (preg_match('~Opera[ /]9\\.[89]~', $_SERVER['HTTP_USER_AGENT']) === 1 && preg_match('~Version/1[0-9]\\.~', $_SERVER['HTTP_USER_AGENT']) === 1),
		'is_ie4' => strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 4') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'WebTV') === false,
		'is_webkit' => strpos($_SERVER['HTTP_USER_AGENT'], 'AppleWebKit') !== false,
		'is_mac_ie' => strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 5.') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') !== false,
		'is_web_tv' => strpos($_SERVER['HTTP_USER_AGENT'], 'WebTV') !== false,
		'is_konqueror' => strpos($_SERVER['HTTP_USER_AGENT'], 'Konqueror') !== false,
		'is_firefox' => preg_match('~(?:Firefox|Ice[wW]easel|IceCat)/~', $_SERVER['HTTP_USER_AGENT']) === 1,
		'is_firefox1' => preg_match('~(?:Firefox|Ice[wW]easel|IceCat)/1\\.~', $_SERVER['HTTP_USER_AGENT']) === 1,
		'is_firefox2' => preg_match('~(?:Firefox|Ice[wW]easel|IceCat)/2\\.~', $_SERVER['HTTP_USER_AGENT']) === 1,
		'is_firefox3' => preg_match('~(?:Firefox|Ice[wW]easel|IceCat|Shiretoko|Minefield)/3\\.~', $_SERVER['HTTP_USER_AGENT']) === 1,
		'is_iphone' => strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'iPod') !== false,
		'is_android' => strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false,
	);

	$context['browser']['is_chrome'] = $context['browser']['is_webkit'] && strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false;
	$context['browser']['is_safari'] = !$context['browser']['is_chrome'] && strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false;
	$context['browser']['is_gecko'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Gecko') !== false && !$context['browser']['is_webkit'] && !$context['browser']['is_konqueror'];

	// Internet Explorer 5 and 6 are often "emulated".
	$context['browser']['is_ie8'] = !$context['browser']['is_opera'] && !$context['browser']['is_gecko'] && !$context['browser']['is_web_tv'] && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8') !== false;
	$context['browser']['is_ie7'] = !$context['browser']['is_opera'] && !$context['browser']['is_gecko'] && !$context['browser']['is_web_tv'] && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 7') !== false && !$context['browser']['is_ie8'];
	$context['browser']['is_ie6'] = !$context['browser']['is_opera'] && !$context['browser']['is_gecko'] && !$context['browser']['is_web_tv'] && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 6') !== false && !$context['browser']['is_ie8'] && !$context['browser']['is_ie7'];
	$context['browser']['is_ie5.5'] = !$context['browser']['is_opera'] && !$context['browser']['is_gecko'] && !$context['browser']['is_web_tv'] && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 5.5') !== false;
	$context['browser']['is_ie5'] = !$context['browser']['is_opera'] && !$context['browser']['is_gecko'] && !$context['browser']['is_web_tv'] && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 5.0') !== false;

	$context['browser']['is_ie'] = $context['browser']['is_ie4'] || $context['browser']['is_ie5'] || $context['browser']['is_ie5.5'] || $context['browser']['is_ie6'] || $context['browser']['is_ie7'] || $context['browser']['is_ie8'];
	// Before IE8 we need to fix IE... lots!
	$context['browser']['ie_standards_fix'] = !$context['browser']['is_ie8'];

	$context['browser']['needs_size_fix'] = ($context['browser']['is_ie5'] || $context['browser']['is_ie5.5'] || $context['browser']['is_ie4'] || $context['browser']['is_opera6']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') === false;

	// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
	$context['browser']['possibly_robot'] = !empty($user_info['possibly_robot']);

	// Robots shouldn't be logging in or registering.  So, they aren't a bot.  Better to be wrong than sorry (or people won't be able to log in!), anyway.
	if ((isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('login', 'login2', 'register'))) || !$user_info['is_guest'])
		$context['browser']['possibly_robot'] = false;
}

// Load a theme, by ID.
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info, $sc, $boarddir;
	global $txt, $boardurl, $scripturl, $mbname, $modSettings, $language;
	global $context, $settings, $options, $sourcedir, $ssi_theme, $smcFunc;

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
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
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
	elseif (!empty($modSettings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $modSettings['knownThemes']);
		if (!in_array($id_theme, $themes))
			$id_theme = $modSettings['theme_guests'];
		else
			$id_theme = (int) $id_theme;
	}
	else
		$id_theme = (int) $id_theme;

	$member = empty($user_info['id']) ? -1 : $user_info['id'];

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
				AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
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

	if (!$initialize)
		return;

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
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
				list ($k, $v) = each($_GET);

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
	$context['user'] = array(
		'id' => $user_info['id'],
		'is_logged' => !$user_info['is_guest'],
		'is_guest' => &$user_info['is_guest'],
		'is_admin' => &$user_info['is_admin'],
		'is_mod' => &$user_info['is_mod'],
		// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
		'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
		'username' => $user_info['username'],
		'language' => $user_info['language'],
		'email' => $user_info['email'],
		'ignoreusers' => $user_info['ignoreusers'],
	);
	if (!$context['user']['is_guest'])
		$context['user']['name'] = $user_info['name'];
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
		$context['user']['name'] = $txt['guest_title'];

	// Determine the current smiley set.
	$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], explode(',', $modSettings['smiley_sets_known'])) && $user_info['smiley_set'] != 'none') || empty($modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) : $user_info['smiley_set'];
	$context['user']['smiley_set'] = $user_info['smiley_set'];

	// Some basic information...
	if (!isset($context['html_headers']))
		$context['html_headers'] = '';

	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $smcFunc['htmlspecialchars']($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? '' : $smcFunc['htmlspecialchars']($settings['header_logo_url']);
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	if (isset($modSettings['load_average']))
		$context['load_average'] = $modSettings['load_average'];

	// Set some permission related settings.
	$context['show_login_bar'] = $user_info['is_guest'] && !empty($modSettings['enableVBStyleLogin']);

	// This determines the server... not used in many places, except for login fixing.
	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
		'complex_preg_chars' => @version_compare(PHP_VERSION, '4.3.3') != -1,
	);
	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];

	// Detect the browser. This is separated out because it's also used in attachment downloads
	detectBrowser();

	// Set the top level linktree up.
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
		'quotefast',
		'spellcheck',
	);

	// Wireless mode?  Load up the wireless stuff.
	if (WIRELESS)
	{
		$context['template_layers'] = array(WIRELESS_PROTOCOL);
		loadTemplate('Wireless');
		loadLanguage('Wireless+index+Modifications');
	}
	// Output is fully XML, so no need for the index template.
	elseif (isset($_REQUEST['xml']))
	{
		loadLanguage('index+Modifications');
		loadTemplate('Xml');
		$context['template_layers'] = array();
	}
	// These actions don't require the index template at all.
	elseif (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], $simpleActions))
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

	// Load the compatibility stylesheet if the theme hasn't been updated for 2.0 RC2 (yet).
	if (isset($settings['theme_version']) && (version_compare($settings['theme_version'], '2.0 RC2', '<') || strpos($settings['theme_version'], '2.0 Beta') !== false))
		loadTemplate(false, 'compat');

	// Guests may still need a name.
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

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
	}

	// Let's be compatible with old themes!
	if (!function_exists('template_html_above') && in_array('html', $context['template_layers']))
		$context['template_layers'] = array('main');

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];
	$txt['number_format'] = empty($txt['number_format']) ? empty($modSettings['number_format']) ? '' : $modSettings['number_format'] : $txt['number_format'];

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
	// Make a special URL for the language.
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

	// Set the character set from the template.
	$context['character_set'] = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8' && (strpos(strtolower(PHP_OS), 'win') === false || @version_compare(PHP_VERSION, '4.2.3') != -1);
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	$context['tabindex'] = 1;

	// Fix font size with HTML 4.01, etc.
	if (isset($settings['doctype']))
		$context['browser']['needs_size_fix'] |= $settings['doctype'] == 'html' && $context['browser']['is_ie6'];

	// Compatibility.
	if (!isset($settings['theme_version']))
		$modSettings['memberCount'] = $modSettings['totalMembers'];

	// This allows us to change the way things look for the admin.
	$context['admin_features'] = isset($modSettings['admin_features']) ? explode(',', $modSettings['admin_features']) : array('cd,cp,k,w,rg,ml,pm');

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
	{
		if ($context['browser']['possibly_robot'])
		{
			//!!! Maybe move this somewhere better?!
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

			$context['html_headers'] .= '
	<script type="text/javascript">
		function smfAutoTask()
		{
			var tempImage = new Image();
			tempImage.src = "' . $scripturl . '?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("smfAutoTask();", 1);
	</script>';
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

// Load a template - if the theme doesn't include it, use the default.
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	global $context, $settings, $txt, $scripturl, $boarddir, $db_show_debug;

	// Do any style sheets first, cause we're easy with those.
	if (!empty($style_sheets))
	{
		if (!is_array($style_sheets))
			$style_sheets = array($style_sheets);

		foreach ($style_sheets as $sheet)
		{
			// Prevent the style sheet from being included twice.
			if (strpos($context['html_headers'], 'id="' . $sheet . '_css"') !== false)
				continue;

			$sheet_path = file_exists($settings['theme_dir']. '/css/' . $sheet . '.css') ? 'theme_url' : (file_exists($settings['default_theme_dir']. '/css/' . $sheet . '.css') ? 'default_theme_url' : '');
			if ($sheet_path)
			{
				$context['html_headers'] .= "\n\t" . '<link rel="stylesheet" type="text/css" id="' . $sheet . '_css" href="' . $settings[$sheet_path] . '/css/' . $sheet . '.css" />';
				if ($db_show_debug === true)
					$context['debug']['sheets'][] = $sheet . ' (' . basename($settings[$sheet_path]) . ')';
			}
		}
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
		// For compatibility reasons, if this is the index template without new functions, include compatible stuff.
		if (substr($template_name, 0, 5) == 'index' && !function_exists('template_button_strip'))
			loadTemplate('Compat');

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
	<a href="', $scripturl . '?action=admin;area=theme;sa=settings;th=1;' . $context['session_var'] . '=' . $context['session_id'], '" class="alert">', $txt['theme_dir_wrong'], '</a>
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

// Load a sub template... fatal is for templates that shouldn't get a 'pretty' error screen.
function loadSubTemplate($sub_template_name, $fatal = false)
{
	global $context, $settings, $options, $txt, $db_show_debug;

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
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold;">---- ', $sub_template_name, ' ends ----</div>';
	}
}

// Load a language file.  Tries the current and default themes as well as the user and global languages.
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $user_info, $language, $settings, $context, $modSettings;
	global $cachedir, $db_show_debug, $sourcedir, $txt;
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

				break;
			}
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
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

// Get all parent boards (requires first parent as parameter)
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
					b.id_parent, b.name, {int:board_parent} AS id_board, IFNULL(mem.id_member, 0) AS id_moderator,
					mem.real_name, b.child_level
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
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
						'moderators' => array()
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
			}
			$smcFunc['db_free_result']($result);
		}

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

// Attempt to reload our languages.
function getLanguages($use_cache = true, $favor_utf8 = true)
{
	global $context, $smcFunc, $settings, $modSettings;

	// Either we don't use the cache, or its expired.
	if (!$use_cache || ($context['languages'] = cache_get_data('known_languages' . ($favor_utf8 ? '' : '_all'), !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600)) == null)
	{
		// If we don't have our theme information yet, lets get it.
		if (empty($settings['default_theme_dir']))
			loadTheme(0, false);

		// Default language directories to try.
		$language_directories = array(
			$settings['default_theme_dir'] . '/languages',
			$settings['actual_theme_dir'] . '/languages',
		);

		// We possibly have a base theme directory.
		if (!empty($settings['base_theme_dir']))
			$language_directories[] = $settings['base_theme_dir'] . '/languages';

		// Remove any duplicates.
		$language_directories = array_unique($language_directories);

		foreach ($language_directories as $language_dir)
		{
			// Can't look in here... doesn't exist!
			if (!file_exists($language_dir))
				continue;

			$dir = dir($language_dir);
			while ($entry = $dir->read())
			{
				// Look for the index language file....
				if (!preg_match('~^index\.(.+)\.php$~', $entry, $matches))
					continue;

				$context['languages'][$matches[1]] = array(
					'name' => $smcFunc['ucwords'](strtr($matches[1], array('_' => ' '))),
					'selected' => false,
					'filename' => $matches[1],
					'location' => $language_dir . '/index.' . $matches[1] . '.php',
				);

			}
			$dir->close();
		}

		// Favoring UTF8? Then prevent us from selecting non-UTF8 versions.
		if ($favor_utf8)
		{
			foreach ($context['languages'] as $lang)
				if (substr($lang['filename'], strlen($lang['filename']) - 5, 5) != '-utf8' && isset($context['languages'][$lang['filename'] . '-utf8']))
					unset($context['languages'][$lang['filename']]);
		}

		// Lets cash in on this deal.
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages' . ($favor_utf8 ? '' : '_all'), $context['languages'], !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	return $context['languages'];
}

// Replace all vulgar words with respective proper words. (substring or whole words..)
function &censorText(&$text, $force = false)
{
	global $modSettings, $options, $settings, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($modSettings['censor_vulgar']))
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
		{
			$censor_vulgar[$i] = strtr(preg_quote($censor_vulgar[$i], '/'), array('\\\\\\*' => '[*]', '\\*' => '[^\s]*?', '&' => '&amp;'));
			$censor_vulgar[$i] = (empty($modSettings['censorWholeWord']) ? '/' . $censor_vulgar[$i] . '/' : '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/') . (empty($modSettings['censorIgnoreCase']) ? '' : 'i') . ((empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8' ? 'u' : '');

			if (strpos($censor_vulgar[$i], '\'') !== false)
			{
				$censor_proper[count($censor_vulgar)] = $censor_proper[$i];
				$censor_vulgar[count($censor_vulgar)] = strtr($censor_vulgar[$i], array('\'' => '&#039;'));
			}
		}
	}

	// Censoring isn't so very complicated :P.
	$text = preg_replace($censor_vulgar, $censor_proper, $text);
	return $text;
}

// Load the template/language file using eval or require? (with eval we can show an error message!)
function template_include($filename, $once = false)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	global $user_info, $boardurl, $boarddir, $sourcedir;
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

	// Are we going to use eval?
	if (empty($modSettings['disableTemplateEval']))
	{
		$file_found = file_exists($filename) && eval('?' . '>' . rtrim(file_get_contents($filename))) !== false;
		$settings['current_include_filename'] = $filename;
	}
	else
	{
		$file_found = file_exists($filename);

		if ($once && $file_found)
			require_once($filename);
		elseif ($file_found)
			require($filename);
	}

	if ($file_found !== true)
	{
		ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		if (isset($_GET['debug']) && !WIRELESS)
			header('Content-Type: application/xhtml+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file.  Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>).  To see more specific error information from PHP, try <a href="' . $boardurl . '%1$s" class="extern">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="' . $scripturl . '?theme=1">use the default theme</a>.';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', !empty($context['right_to_left']) ? ' dir="rtl"' : '', '>
	<head>';
		if (isset($context['character_set']))
			echo '
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />';

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
			require_once($sourcedir . '/Subs-Package.php');

			$error = fetch_web_data($boardurl . strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));
			if (empty($error))
				$error = $php_errormsg;

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));

			if (!empty($error))
				echo '
		<hr />

		<div style="margin: 0 20px;"><tt>', strtr(strtr($error, array('<strong>' . $boarddir => '<strong>...', '<strong>' . strtr($boarddir, '\\', '/') => '<strong>...')), '\\', '/'), '</tt></div>';

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if ($context['browser']['is_ie4'] || $context['browser']['is_ie5'] || $context['browser']['is_ie5.5'])
					$data2 = str_replace("\t", '<pre style="display: inline;">' . "\t" . '</pre>', $data2);
				elseif (!$context['browser']['is_gecko'])
					$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
				else
					$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);

				// Now we get to work around a bug in PHP where it doesn't escape <br />s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br />') == 0)
						continue;

					$n = substr_count($line, '<br />');
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

// Attempt to start the session, unless it already has been.
function loadSession()
{
	global $HTTP_SESSION_VARS, $modSettings, $boardurl, $sc;

	// Attempt to change a few PHP settings.
	@ini_set('session.use_cookies', true);
	@ini_set('session.use_only_cookies', false);
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', false);
	@ini_set('arg_separator.output', '&amp;');

	if (!empty($modSettings['globalCookies']))
	{
		$parsed_url = parse_url($boardurl);

		if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			@ini_set('session.cookie_domain', '.' . $parts[1]);
	}
	// !!! Set the session cookie path?

	// If it's already been started... probably best to skip this.
	if ((@ini_get('session.auto_start') == 1 && !empty($modSettings['databaseSession_enable'])) || session_id() == '')
	{
		// Attempt to end the already-started session.
		if (@ini_get('session.auto_start') == 1)
			@session_write_close();

		// This is here to stop people from using bad junky PHPSESSIDs.
		if (isset($_REQUEST[session_name()]) && preg_match('~^[A-Za-z0-9]{16,32}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()]))
		{
			$session_id = md5(md5('smf_sess_' . time()) . mt_rand());
			$_REQUEST[session_name()] = $session_id;
			$_GET[session_name()] = $session_id;
			$_POST[session_name()] = $session_id;
		}

		// Use database sessions? (they don't work in 4.1.x!)
		if (!empty($modSettings['databaseSession_enable']) && @version_compare(PHP_VERSION, '4.2.0') != -1)
		{
			session_set_save_handler('sessionOpen', 'sessionClose', 'sessionRead', 'sessionWrite', 'sessionDestroy', 'sessionGC');
			@ini_set('session.gc_probability', '1');
		}
		elseif (@ini_get('session.gc_maxlifetime') <= 1440 && !empty($modSettings['databaseSession_lifetime']))
			@ini_set('session.gc_maxlifetime', max($modSettings['databaseSession_lifetime'], 60));

		// Use cache setting sessions?
		if (empty($modSettings['databaseSession_enable']) && !empty($modSettings['cache_enable']) && php_sapi_name() != 'cli')
		{
			if (function_exists('mmcache_set_session_handlers'))
				mmcache_set_session_handlers();
			elseif (function_exists('eaccelerator_set_session_handlers'))
				eaccelerator_set_session_handlers();
		}

		session_start();

		// Change it so the cache settings are a little looser than default.
		if (!empty($modSettings['databaseSession_loose']))
			header('Cache-Control: private');
	}

	// While PHP 4.1.x should use $_SESSION, it seems to need this to do it right.
	if (@version_compare(PHP_VERSION, '4.2.0') == -1)
		$HTTP_SESSION_VARS['php_412_bugfix'] = true;

	// Set the randomly generated code.
	if (!isset($_SESSION['session_var']))
	{
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', sha1(mt_rand() . session_id() . mt_rand())), 0, rand(7, 12));
	}
	$sc = $_SESSION['session_value'];
}

function sessionOpen($save_path, $session_name)
{
	return true;
}

function sessionClose()
{
	return true;
}

function sessionRead($session_id)
{
	global $smcFunc;

	if (preg_match('~^[A-Za-z0-9]{16,32}$~', $session_id) == 0)
		return false;

	// Look for it in the database.
	$result = $smcFunc['db_query']('', '
		SELECT data
		FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}
		LIMIT 1',
		array(
			'session_id' => $session_id,
		)
	);
	list ($sess_data) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return $sess_data;
}

function sessionWrite($session_id, $data)
{
	global $smcFunc;

	if (preg_match('~^[A-Za-z0-9]{16,32}$~', $session_id) == 0)
		return false;

	// First try to update an existing row...
	$result = $smcFunc['db_query']('', '
		UPDATE {db_prefix}sessions
		SET data = {string:data}, last_update = {int:last_update}
		WHERE session_id = {string:session_id}',
		array(
			'last_update' => time(),
			'data' => $data,
			'session_id' => $session_id,
		)
	);

	// If that didn't work, try inserting a new one.
	if ($smcFunc['db_affected_rows']() == 0)
		$result = $smcFunc['db_insert']('ignore',
			'{db_prefix}sessions',
			array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
			array($session_id, $data, time()),
			array('session_id')
		);

	return $result;
}

function sessionDestroy($session_id)
{
	global $smcFunc;

	if (preg_match('~^[A-Za-z0-9]{16,32}$~', $session_id) == 0)
		return false;

	// Just delete the row...
	return $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}',
		array(
			'session_id' => $session_id,
		)
	);
}

function sessionGC($max_lifetime)
{
	global $modSettings, $smcFunc;

	// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
	if (!empty($modSettings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $modSettings['databaseSession_lifetime'] > $max_lifetime))
		$max_lifetime = max($modSettings['databaseSession_lifetime'], 60);

	// Clean up ;).
	return $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}sessions
		WHERE last_update < {int:last_update}',
		array(
			'last_update' => time() - $max_lifetime,
		)
	);
}

// Load up a database connection.
function loadDatabase()
{
	global $db_persist, $db_connection, $db_server, $db_user, $db_passwd;
	global $db_type, $db_name, $ssi_db_user, $ssi_db_passwd, $sourcedir, $db_prefix;

	// Figure out what type of database we are using.
	if (empty($db_type) || !file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
		$db_type = 'mysql';

	// Load the file for the database.
	require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (SMF == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$db_connection = smf_db_initiate($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));

	// Either we aren't in SSI mode, or it failed.
	if (empty($db_connection))
		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => SMF == 'SSI'));

	// Safe guard here, if there isn't a valid connection lets put a stop to it.
	if (!$db_connection)
		db_fatal_error();

	// If in SSI mode fix up the prefix.
	if (SMF == 'SSI')
		db_fix_prefix($db_prefix, $db_name);
}

// Try to retrieve a cache entry. On failure, call the appropriate function.
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $modSettings, $sourcedir;

	// Refresh the cache if either:
	// 1. Caching is disabled.
	// 2. The cache level isn't high enough.
	// 3. The item has not been cached or the cached item expired.
	// 4. The cached item has a custom expiration condition evaluating to true.
	// 5. The expire time set in the cache item has passed (needed for Zend).
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

	return $cache_block['data'];
}

function cache_put_data($key, $value, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($modSettings['cache_enable']) && !empty($modSettings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(serialize($value)));
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':', '-');
	$value = $value === null ? null : serialize($value);

	// The simple yet efficient memcached.
	if (function_exists('memcache_set') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
	{
		// Not connected yet?
		if (empty($memcached))
			get_memcached_server();
		if (!$memcached)
			return;

		memcache_set($memcached, $key, $value, 0, $ttl);
	}
	// eAccelerator...
	elseif (function_exists('eaccelerator_put'))
	{
		if (mt_rand(0, 10) == 1)
			eaccelerator_gc();

		if ($value === null)
			@eaccelerator_rm($key);
		else
			eaccelerator_put($key, $value, $ttl);
	}
	// Turck MMCache?
	elseif (function_exists('mmcache_put'))
	{
		if (mt_rand(0, 10) == 1)
			mmcache_gc();

		if ($value === null)
			@mmcache_rm($key);
		else
			mmcache_put($key, $value, $ttl);
	}
	// Alternative PHP Cache, ahoy!
	elseif (function_exists('apc_store'))
	{
		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			apc_delete($key . 'smf');
		else
			apc_store($key . 'smf', $value, $ttl);
	}
	// Zend Platform/ZPS/etc.
	elseif (function_exists('output_cache_put'))
		output_cache_put($key, $value);
	elseif (function_exists('xcache_set') && ini_get('xcache.var_size') > 0)
	{
		if ($value === null)
			xcache_unset($key);
		else
			xcache_set($key, $value, $ttl);
	}
	// Otherwise custom cache?
	else
	{
		if ($value === null)
			@unlink($cachedir . '/data_' . $key . '.php');
		else
		{
			$cache_data = '<' . '?' . 'php if (!defined(\'SMF\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, '\\\'') . '\';}' . '?' . '>';
			$fh = @fopen($cachedir . '/data_' . $key . '.php', 'w');
			if ($fh)
			{
				// Write the file.
				set_file_buffer($fh, 0);
				flock($fh, LOCK_EX);
				$cache_bytes = fwrite($fh, $cache_data);
				flock($fh, LOCK_UN);
				fclose($fh);

				// Check that the cache write was successful; all the data should be written
				// If it fails due to low diskspace, remove the cache file
				if ($cache_bytes != strlen($cache_data))
					@unlink($cachedir . '/data_' . $key . '.php');
			}
		}
	}

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
}

function cache_get_data($key, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($modSettings['cache_enable']) && !empty($modSettings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':', '-');

	// Okay, let's go for it memcached!
	if (function_exists('memcache_get') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
	{
		// Not connected yet?
		if (empty($memcached))
			get_memcached_server();
		if (!$memcached)
			return;

		$value = memcache_get($memcached, $key);
	}
	// Again, eAccelerator.
	elseif (function_exists('eaccelerator_get'))
		$value = eaccelerator_get($key);
	// The older, but ever-stable, Turck MMCache...
	elseif (function_exists('mmcache_get'))
		$value = mmcache_get($key);
	// This is the free APC from PECL.
	elseif (function_exists('apc_fetch'))
		$value = apc_fetch($key . 'smf');
	// Zend's pricey stuff.
	elseif (function_exists('output_cache_get'))
		$value = output_cache_get($key, $ttl);
	elseif (function_exists('xcache_get') && ini_get('xcache.var_size') > 0)
		$value = xcache_get($key);
	// Otherwise it's SMF data!
	elseif (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
	{
		require($cachedir . '/data_' . $key . '.php');
		if (!empty($expired) && isset($value))
		{
			@unlink($cachedir . '/data_' . $key . '.php');
			unset($value);
		}
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
		$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;
	}

	if (empty($value))
		return null;
	// If it's broke, it's broke... so give up on it.
	else
		return @unserialize($value);
}

function get_memcached_server($level = 3)
{
	global $modSettings, $memcached, $db_persist;

	$servers = explode(',', $modSettings['cache_memcached']);
	$server = explode(':', trim($servers[array_rand($servers)]));

	// Don't try more times than we have servers!
	$level = min(count($servers), $level);

	// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
	if (empty($db_persist))
		$memcached = memcache_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
	else
		$memcached = memcache_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);

	if (!$memcached && $level > 0)
		get_memcached_server($level - 1);
}

?>