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

// Don't do anything if SMF is already loaded.
if (defined('SMF'))
	return true;

define('SMF', 'SSI');

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
global $boardurl, $boarddir, $sourcedir, $webmaster_email, $cookiename;
global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
global $db_connection, $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
global $smcFunc, $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd, $cachedir;

// Remember the current configuration so it can be set back.
$ssi_magic_quotes_runtime = function_exists('get_magic_quotes_gpc') && get_magic_quotes_runtime();
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime(0);
$time_start = microtime();

// Just being safe...
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable]);

// Get the forum's settings for database and file paths.
require_once(dirname(__FILE__) . '/Settings.php');

// Make absolutely sure the cache directory is defined.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

$ssi_error_reporting = error_reporting(defined('E_STRICT') ? E_ALL | E_STRICT : E_ALL);
/* Set this to one of three values depending on what you want to happen in the case of a fatal error.
	false:	Default, will just load the error sub template and die - not putting any theme layers around it.
	true:	Will load the error sub template AND put the SMF layers around it (Not useful if on total custom pages).
	string:	Name of a callback function to call in the event of an error to allow you to define your own methods. Will die after function returns.
*/
$ssi_on_error_method = false;

// Don't do john didley if the forum's been shut down competely.
if ($maintenance == 2 && (!isset($ssi_maintenance_off) || $ssi_maintenance_off !== true))
	die($mmessage);

// Fix for using the current directory as a path.
if (substr($sourcedir, 0, 1) == '.' && substr($sourcedir, 1, 1) != '.')
	$sourcedir = dirname(__FILE__) . substr($sourcedir, 1);

// Load the important includes.
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Security.php');

// Using an pre-PHP 5.1 version?
if (@version_compare(PHP_VERSION, '5.1') == -1)
	require_once($sourcedir . '/Subs-Compat.php');

// Create a variable to store some SMF specific functions in.
$smcFunc = array();

// Initate the database connection and define some database functions to use.
loadDatabase();

// Load installed 'Mods' settings.
reloadSettings();
// Clean the request variables.
cleanRequest();

// Seed the random generator?
if (empty($modSettings['rand_seed']) || mt_rand(1, 250) == 69)
	smf_seed_generator();

// Check on any hacking attempts.
if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
	die('Hacking attempt...');
elseif (isset($_REQUEST['ssi_theme']) && (int) $_REQUEST['ssi_theme'] == (int) $ssi_theme)
	die('Hacking attempt...');
elseif (isset($_COOKIE['ssi_theme']) && (int) $_COOKIE['ssi_theme'] == (int) $ssi_theme)
	die('Hacking attempt...');
elseif (isset($_REQUEST['ssi_layers'], $ssi_layers) && (@get_magic_quotes_gpc() ? stripslashes($_REQUEST['ssi_layers']) : $_REQUEST['ssi_layers']) == $ssi_layers)
	die('Hacking attempt...');
if (isset($_REQUEST['context']))
	die('Hacking attempt...');

// Make sure wireless is always off.
define('WIRELESS', false);

// Gzip output? (because it must be boolean and true, this can't be hacked.)
if (isset($ssi_gzip) && $ssi_gzip === true && @ini_get('zlib.output_compression') != '1' && @ini_get('output_handler') != 'ob_gzhandler' && @version_compare(PHP_VERSION, '4.2.0') != -1)
	ob_start('ob_gzhandler');
else
	$modSettings['enableCompressedOutput'] = '0';

// Primarily, this is to fix the URLs...
ob_start('ob_sessrewrite');

// Start the session... known to scramble SSI includes in cases...
if (!headers_sent())
	loadSession();
else
{
	if (isset($_COOKIE[session_name()]) || isset($_REQUEST[session_name()]))
	{
		// Make a stab at it, but ignore the E_WARNINGs generated because we can't send headers.
		$temp = error_reporting(error_reporting() & !E_WARNING);
		loadSession();
		error_reporting($temp);
	}

	if (!isset($_SESSION['session_value']))
	{
		$_SESSION['session_var'] = substr(md5(mt_rand() . session_id() . mt_rand()), 0, rand(7, 12));
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
	}
	$sc = $_SESSION['session_value'];
}

// Get rid of $board and $topic... do stuff loadBoard would do.
unset($board, $topic);
$user_info['is_mod'] = false;
$context['user']['is_mod'] = &$user_info['is_mod'];
$context['linktree'] = array();

// Load the user and their cookie, as well as their settings.
loadUserSettings();

// Load the current user's permissions....
loadPermissions();

// Load the current or SSI theme. (just use $ssi_theme = id_theme;)
loadTheme(isset($ssi_theme) ? (int) $ssi_theme : 0);

// Take care of any banning that needs to be done.
if (isset($_REQUEST['ssi_ban']) || (isset($ssi_ban) && $ssi_ban === true))
	is_not_banned();

// Do we allow guests in here?
if (empty($ssi_guest_access) && empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && basename($_SERVER['PHP_SELF']) != 'SSI.php')
{
	require_once($sourcedir . '/Subs-Auth.php');
	KickGuest();
	obExit(null, true);
}

// Load the stuff like the menu bar, etc.
if (isset($ssi_layers))
{
	$context['template_layers'] = $ssi_layers;
	template_header();
}
else
	setupThemeContext();

// Make sure they didn't muss around with the settings... but only if it's not cli.
if (isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['is_cli']) && session_id() == '')
	trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);

// Without visiting the forum this session variable might not be set on submit.
if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

// Call a function passed by GET.
if (isset($_GET['ssi_function']) && function_exists('ssi_' . $_GET['ssi_function']) && (!empty($modSettings['allow_guestAccess']) || !$user_info['is_guest']))
{
	call_user_func('ssi_' . $_GET['ssi_function']);
	exit;
}
if (isset($_GET['ssi_function']))
	exit;
// You shouldn't just access SSI.php directly by URL!!
elseif (basename($_SERVER['PHP_SELF']) == 'SSI.php')
	die(sprintf($txt['ssi_not_direct'], $user_info['is_admin'] ? '\'' . addslashes(__FILE__) . '\'' : '\'SSI.php\''));

error_reporting($ssi_error_reporting);
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime($ssi_magic_quotes_runtime);

return true;

// This shuts down the SSI and shows the footer.
function ssi_shutdown()
{
	if (!isset($_GET['ssi_function']) || $_GET['ssi_function'] != 'shutdown')
		template_footer();
}

// Display a welcome message, like:  Hey, User, you have 0 messages, 0 are new.
function ssi_welcome($output_method = 'echo')
{
	global $context, $txt, $scripturl;

	if ($output_method == 'echo')
	{
		if ($context['user']['is_guest'])
			echo sprintf($txt['welcome_guest'], $txt['guest_title']);
		else
			echo $txt['hello_member'], ' <strong>', $context['user']['name'], '</strong>', allowedTo('pm_read') ? ', ' . $txt['msg_alert_you_have'] . ' <a href="' . $scripturl . '?action=pm">' . $context['user']['messages'] . ' ' . ($context['user']['messages'] == '1' ? $txt['message_lowercase'] : $txt['msg_alert_messages']) . '</a>' . $txt['newmessages4'] . ' ' . $context['user']['unread_messages'] . ' ' . ($context['user']['unread_messages'] == '1' ? $txt['newmessages0'] : $txt['newmessages1']) : '', '.';
	}
	// Don't echo... then do what?!
	else
		return $context['user'];
}

// Display a menu bar, like is displayed at the top of the forum.
function ssi_menubar($output_method = 'echo')
{
	global $context;

	if ($output_method == 'echo')
		template_menu();
	// What else could this do?
	else
		return $context['menu_buttons'];
}

// Show a logout link.
function ssi_logout($redirect_to = '', $output_method = 'echo')
{
	global $context, $txt, $scripturl;

	if ($redirect_to != '')
		$_SESSION['logout_url'] = $redirect_to;

	// Guests can't log out.
	if ($context['user']['is_guest'])
		return false;

	$link = '<a href="' . $scripturl . '?action=logout;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['logout'] . '</a>';

	if ($output_method == 'echo')
		echo $link;
	else
		return $link;
}

// Recent post list:   [board] Subject by Poster	Date
function ssi_recentPosts($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo', $limit_body = true)
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;

	// Excluding certain boards...
	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

	// What about including certain boards - note we do some protection here as pre-2.0 didn't have this parameter.
	if (is_array($include_boards) || (int) $include_boards === $include_boards)
	{
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	}
	elseif ($include_boards != null)
	{
		$include_boards = array();
	}

	// Let's restrict the query boys (and girls)
	$query_where = '
		m.id_msg >= {int:min_message_id}
		' . (empty($exclude_boards) ? '' : '
		AND b.id_board NOT IN ({array_int:exclude_boards})') . '
		' . ($include_boards === null ? '' : '
		AND b.id_board IN ({array_int:include_boards})') . '
		AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
		AND m.approved = {int:is_approved}' : '');

	$query_where_params = array(
		'is_approved' => 1,
		'include_boards' => $include_boards === null ? '' : $include_boards,
		'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
		'min_message_id' => $modSettings['maxMsgID'] - 25 * min($num_recent, 5),
	);

	// Past to this simpleton of a function...
	return ssi_queryPosts($query_where, $query_where_params, $num_recent, 'm.id_msg DESC', $output_method, $limit_body);
}

// Fetch a post with a particular ID. By default will only show if you have permission to the see the board in question - this can be overriden.
function ssi_fetchPosts($post_ids, $override_permissions = false, $output_method = 'echo')
{
	global $user_info, $modSettings;

	// Allow the user to request more than one - why not?
	$post_ids = is_array($post_ids) ? $post_ids : array($post_ids);

	// Restrict the posts required...
	$query_where = '
		m.id_msg IN ({array_int:message_list})' . ($override_permissions ? '' : '
			AND {query_wanna_see_board}') . ($modSettings['postmod_active'] ? '
			AND m.approved = {int:is_approved}' : '');
	$query_where_params = array(
		'message_list' => $post_ids,
		'is_approved' => 1,
	);

	// Then make the query and dump the data.
	return ssi_queryPosts($query_where, $query_where_params, '', 'm.id_msg DESC', $output_method);
}

// This removes code duplication in other queries - don't call it direct unless you really know what you're up to.
function ssi_queryPosts($query_where = '', $query_where_params = array(), $query_limit = '', $query_order = 'm.id_msg DESC', $output_method = 'echo', $limit_body = false)
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;

	// Find all the posts. Newer ones will have higher IDs.
	$request = $smcFunc['db_query']('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg, m.id_board, b.name AS board_name,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', ' . ($limit_body ? 'SUBSTRING(m.body, 1, 384) AS body' : 'm.body') . ', m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})' : '') . '
		' . (empty($query_where) ? '' : 'WHERE ' . $query_where) . '
		ORDER BY ' . $query_order . '
		' . ($query_limit == '' ? '' : 'LIMIT ' . $query_limit),
		array_merge($query_where_params, array(
			'current_member' => $user_info['id'],
		))
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// Censor it!
		censorText($row['subject']);
		censorText($row['body']);

		$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));

		// Build the array.
		$posts[] = array(
			'id' => $row['id_msg'],
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 25),
			'preview' => $smcFunc['strlen']($preview) > 128 ? $smcFunc['substr']($preview, 0, 128) . '...' : $preview,
			'body' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'new' => !empty($row['is_read']),
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Just return it.
	if ($output_method != 'echo' || empty($posts))
		return $posts;

	echo '
		<table border="0" class="ssi_table">';
	foreach ($posts as $post)
		echo '
			<tr>
				<td align="right" valign="top" nowrap="nowrap">
					[', $post['board']['link'], ']
				</td>
				<td valign="top">
					<a href="', $post['href'], '">', $post['subject'], '</a>
					', $txt['by'], ' ', $post['poster']['link'], '
					', $post['is_new'] ? '<a href="' . $scripturl . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . ';topicseen#new" rel="nofollow"><img src="' . $settings['lang_images_url'] . '/new.gif" alt="' . $txt['new'] . '" /></a>' : '', '
				</td>
				<td align="right" nowrap="nowrap">
					', $post['time'], '
				</td>
			</tr>';
	echo '
		</table>';
}

// Recent topic list:   [board] Subject by Poster	Date
function ssi_recentTopics($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo')
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;

	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

	// Only some boards?.
	if (is_array($include_boards) || (int) $include_boards === $include_boards)
	{
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	}
	elseif ($include_boards != null)
	{
		$output_method = $include_boards;
		$include_boards = array();
	}

	$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless');
	$icon_sources = array();
	foreach ($stable_icons as $icon)
		$icon_sources[$icon] = 'images_url';

	// Find all the posts in distinct topics.  Newer ones will have higher IDs.
	$request = $smcFunc['db_query']('substring', '
		SELECT
			m.poster_time, ms.subject, m.id_topic, m.id_member, m.id_msg, b.id_board, b.name AS board_name, t.num_replies, t.num_views,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', SUBSTRING(m.body, 1, 384) AS body, m.smileys_enabled, m.icon
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})' : '') . '
		WHERE t.id_last_msg >= {int:min_message_id}
			' . (empty($exclude_boards) ? '' : '
			AND b.id_board NOT IN ({array_int:exclude_boards})') . '
			' . (empty($include_boards) ? '' : '
			AND b.id_board IN ({array_int:include_boards})') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}' : '') . '
		ORDER BY t.id_last_msg DESC
		LIMIT ' . $num_recent,
		array(
			'current_member' => $user_info['id'],
			'include_boards' => empty($include_boards) ? '' : $include_boards,
			'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
			'min_message_id' => $modSettings['maxMsgID'] - 35 * min($num_recent, 5),
			'is_approved' => 1,
		)
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		if ($smcFunc['strlen']($row['body']) > 128)
			$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

		// Censor the subject.
		censorText($row['subject']);
		censorText($row['body']);

		if (empty($modSettings['messageIconChecks_disable']) && !isset($icon_sources[$row['icon']]))
			$icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.gif') ? 'images_url' : 'default_images_url';

		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'replies' => $row['num_replies'],
			'views' => $row['num_views'],
			'short_subject' => shorten_subject($row['subject'], 25),
			'preview' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new" rel="nofollow">' . $row['subject'] . '</a>',
			// Retained for compatibility - is technically incorrect!
			'new' => !empty($row['is_read']),
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
			'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.gif" align="middle" alt="' . $row['icon'] . '" />',
		);
	}
	$smcFunc['db_free_result']($request);

	// Just return it.
	if ($output_method != 'echo' || empty($posts))
		return $posts;

	echo '
		<table border="0" class="ssi_table">';
	foreach ($posts as $post)
		echo '
			<tr>
				<td align="right" valign="top" nowrap="nowrap">
					[', $post['board']['link'], ']
				</td>
				<td valign="top">
					<a href="', $post['href'], '">', $post['subject'], '</a>
					', $txt['by'], ' ', $post['poster']['link'], '
					', !$post['is_new'] ? '' : '<a href="' . $scripturl . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . ';topicseen#new" rel="nofollow"><img src="' . $settings['lang_images_url'] . '/new.gif" alt="' . $txt['new'] . '" /></a>', '
				</td>
				<td align="right" nowrap="nowrap">
					', $post['time'], '
				</td>
			</tr>';
	echo '
		</table>';
}

// Show the top poster's name and profile link.
function ssi_topPoster($topNumber = 1, $output_method = 'echo')
{
	global $db_prefix, $scripturl, $smcFunc;

	// Find the latest poster.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		ORDER BY posts DESC
		LIMIT ' . $topNumber,
		array(
		)
	);
	$return = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$return[] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'posts' => $row['posts']
		);
	$smcFunc['db_free_result']($request);

	// Just return all the top posters.
	if ($output_method != 'echo')
		return $return;

	// Make a quick array to list the links in.
	$temp_array = array();
	foreach ($return as $member)
		$temp_array[] = $member['link'];

	echo implode(', ', $temp_array);
}

// Show boards by activity.
function ssi_topBoards($num_top = 10, $output_method = 'echo')
{
	global $context, $settings, $db_prefix, $txt, $scripturl, $user_info, $modSettings, $smcFunc;

	// Find boards with lots of posts.
	$request = $smcFunc['db_query']('', '
		SELECT
			b.name, b.num_topics, b.num_posts, b.id_board,' . (!$user_info['is_guest'] ? ' 1 AS is_read' : '
			(IFNULL(lb.id_msg, 0) >= b.id_last_msg) AS is_read') . '
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
		ORDER BY b.num_posts DESC
		LIMIT ' . $num_top,
		array(
			'current_member' => $user_info['id'],
			'recycle_board' => (int) $modSettings['recycle_board'],
		)
	);
	$boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[] = array(
			'id' => $row['id_board'],
			'num_posts' => $row['num_posts'],
			'num_topics' => $row['num_topics'],
			'name' => $row['name'],
			'new' => empty($row['is_read']),
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>'
		);
	$smcFunc['db_free_result']($request);

	// If we shouldn't output or have nothing to output, just jump out.
	if ($output_method != 'echo' || empty($boards))
		return $boards;

	echo '
		<table class="ssi_table">
			<tr>
				<th align="left">', $txt['board'], '</th>
				<th align="left">', $txt['board_topics'], '</th>
				<th align="left">', $txt['posts'], '</th>
			</tr>';
	foreach ($boards as $board)
		echo '
			<tr>
				<td>', $board['link'], $board['new'] ? ' <a href="' . $board['href'] . '"><img src="' . $settings['lang_images_url'] . '/new.gif" alt="' . $txt['new'] . '" /></a>' : '', '</td>
				<td align="right">', comma_format($board['num_topics']), '</td>
				<td align="right">', comma_format($board['num_posts']), '</td>
			</tr>';
	echo '
		</table>';
}

// Shows the top topics.
function ssi_topTopics($type = 'replies', $num_topics = 10, $output_method = 'echo')
{
	global $db_prefix, $txt, $scripturl, $user_info, $modSettings, $smcFunc, $context;

	if ($modSettings['totalMessages'] > 100000)
	{
		// !!! Why don't we use {query(_wanna)_see_board}?
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_' . ($type != 'replies' ? 'views' : 'replies') . ' != 0' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved}' : '') . '
			ORDER BY num_' . ($type != 'replies' ? 'views' : 'replies') . ' DESC
			LIMIT {int:limit}',
			array(
				'is_approved' => 1,
				'limit' => $num_topics > 100 ? ($num_topics + ($num_topics / 2)) : 100,
			)
		);
		$topic_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topic_ids[] = $row['id_topic'];
		$smcFunc['db_free_result']($request);
	}
	else
		$topic_ids = array();

	$request = $smcFunc['db_query']('', '
		SELECT m.subject, m.id_topic, t.num_views, t.num_replies
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : '') . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_enable}' : '') . '
		ORDER BY t.num_' . ($type != 'replies' ? 'views' : 'replies') . ' DESC
		LIMIT {int:limit}',
		array(
			'topic_list' => $topic_ids,
			'is_approved' => 1,
			'recycle_enable' => $modSettings['recycle_board'],
			'limit' => $num_topics,
		)
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($row['subject']);

		$topics[] = array(
			'id' => $row['id_topic'],
			'subject' => $row['subject'],
			'num_replies' => $row['num_replies'],
			'num_views' => $row['num_views'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
		);
	}
	$smcFunc['db_free_result']($request);

	if ($output_method != 'echo' || empty($topics))
		return $topics;

	echo '
		<table class="ssi_table">
			<tr>
				<th align="left"></th>
				<th align="left">', $txt['views'], '</th>
				<th align="left">', $txt['replies'], '</th>
			</tr>';
	foreach ($topics as $topic)
		echo '
			<tr>
				<td align="left">
					', $topic['link'], '
				</td>
				<td align="right">', comma_format($topic['num_views']), '</td>
				<td align="right">', comma_format($topic['num_replies']), '</td>
			</tr>';
	echo '
		</table>';
}

// Shows the top topics, by replies.
function ssi_topTopicsReplies($num_topics = 10, $output_method = 'echo')
{
	return ssi_topTopics('replies', $num_topics, $output_method);
}

// Shows the top topics, by views.
function ssi_topTopicsViews($num_topics = 10, $output_method = 'echo')
{
	return ssi_topTopics('views', $num_topics, $output_method);
}

// Show a link to the latest member:  Please welcome, Someone, out latest member.
function ssi_latestMember($output_method = 'echo')
{
	global $db_prefix, $txt, $scripturl, $context;

	if ($output_method == 'echo')
		echo '
	', $txt['welcome_member'], ' ', $context['common_stats']['latest_member']['link'], '', $txt['newest_member'], '<br />';
	else
		return $context['common_stats']['latest_member'];
}

// Fetch a random member - if type set to 'day' will only change once a day!
function ssi_randomMember($random_type = '', $output_method = 'echo')
{
	global $modSettings;

	// If we're looking for something to stay the same each day then seed the generator.
	if ($random_type == 'day')
	{
		// Set the seed to change only once per day.
		mt_srand(floor(time() / 86400));
	}

	// Get the lowest ID we're interested in.
	$member_id = mt_rand(1, $modSettings['latestMember']);

	$where_query = '
		id_member >= {int:selected_member}
		AND is_activated = {int:is_activated}';

	$query_where_params = array(
		'selected_member' => $member_id,
		'is_activated' => 1,
	);

	$result = ssi_queryMembers($where_query, $query_where_params, 1, 'id_member ASC', $output_method);

	// If we got nothing do the reverse - in case of unactivated members.
	if (empty($result))
	{
		$where_query = '
			id_member <= {int:selected_member}
			AND is_activated = {int:is_activated}';

		$query_where_params = array(
			'selected_member' => $member_id,
			'is_activated' => 1,
		);

		$result = ssi_queryMembers($where_query, $query_where_params, 1, 'id_member DESC', $output_method);
	}

	// Just to be sure put the random generator back to something... random.
	if ($random_type != '')
		mt_srand(time());

	return $result;
}

// Fetch a specific member.
function ssi_fetchMember($member_ids, $output_method = 'echo')
{
	// Can have more than one member if you really want...
	$member_ids = is_array($member_ids) ? $member_ids : array($member_ids);

	// Restrict it right!
	$query_where = '
		id_member IN ({array_int:member_list})';

	$query_where_params = array(
		'member_list' => $member_ids,
	);

	// Then make the query and dump the data.
	return ssi_queryMembers($query_where, $query_where_params, '', 'id_member', $output_method);
}

// Get all members of a group.
function ssi_fetchGroupMembers($group_id, $output_method = 'echo')
{
	$query_where = '
		id_group = {int:id_group}
		OR id_post_group = {int:id_group}
		OR FIND_IN_SET({int:id_group}, additional_groups)';

	$query_where_params = array(
		'id_group' => $group_id,
	);

	return ssi_queryMembers($query_where, $query_where_params, '', 'real_name', $output_method);
}

// Fetch some member data!
function ssi_queryMembers($query_where, $query_where_params = array(), $query_limit = '', $query_order = 'id_member DESC', $output_method = 'echo')
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc, $memberContext;

	// Fetch the members in question.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . $query_where . '
		ORDER BY ' . $query_order . '
		' . ($query_limit == '' ? '' : 'LIMIT ' . $query_limit),
		array_merge($query_where_params, array(
		))
	);
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	if (empty($members))
		return array();

	// Load the members.
	loadMemberData($members);

	// Draw the table!
	if ($output_method == 'echo')
		echo '
		<table border="0" class="ssi_table">';

	$query_members = array();
	foreach ($members as $member)
	{
		// Load their context data.
		if (!loadMemberContext($member))
			continue;

		// Store this member's information.
		$query_members[$member] = $memberContext[$member];

		// Only do something if we're echo'ing.
		if ($output_method == 'echo')
			echo '
			<tr>
				<td align="right" valign="top" nowrap="nowrap">
					', $query_members[$member]['link'], '
					<br />', $query_members[$member]['blurb'], '
					<br />', $query_members[$member]['avatar']['image'], '
				</td>
			</tr>';
	}

	// End the table if appropriate.
	if ($output_method == 'echo')
		echo '
		</table>';

	// Send back the data.
	return $query_members;
}

// Show some basic stats:  Total This: XXXX, etc.
function ssi_boardStats($output_method = 'echo')
{
	global $db_prefix, $txt, $scripturl, $modSettings, $smcFunc;

	$totals = array(
		'members' => $modSettings['totalMembers'],
		'posts' => $modSettings['totalMessages'],
		'topics' => $modSettings['totalTopics']
	);

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}boards',
		array(
		)
	);
	list ($totals['boards']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}categories',
		array(
		)
	);
	list ($totals['categories']) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if ($output_method != 'echo')
		return $totals;

	echo '
		', $txt['total_members'], ': <a href="', $scripturl . '?action=mlist">', comma_format($totals['members']), '</a><br />
		', $txt['total_posts'], ': ', comma_format($totals['posts']), '<br />
		', $txt['total_topics'], ': ', comma_format($totals['topics']), ' <br />
		', $txt['total_cats'], ': ', comma_format($totals['categories']), '<br />
		', $txt['total_boards'], ': ', comma_format($totals['boards']);
}

// Shows a list of online users:  YY Guests, ZZ Users and then a list...
function ssi_whosOnline($output_method = 'echo')
{
	global $user_info, $txt, $sourcedir, $settings, $modSettings;

	require_once($sourcedir . '/Subs-MembersOnline.php');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	$return = getMembersOnlineStats($membersOnlineOptions);

	// Add some redundancy for backwards compatibility reasons.
	if ($output_method != 'echo')
		return $return + array(
			'users' => $return['users_online'],
			'guests' => $return['num_guests'],
			'hidden' => $return['num_users_hidden'],
			'buddies' => $return['num_buddies'],
			'num_users' => $return['num_users_online'],
			'total_users' => $return['num_users_online'] + $return['num_guests'] + $return['num_spiders'],
		);

	echo '
		', comma_format($return['num_guests']), ' ', $return['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($return['num_users_online']), ' ', $return['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	$bracketList = array();
	if (!empty($user_info['buddies']))
		$bracketList[] = comma_format($return['num_buddies']) . ' ' . ($return['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($return['num_spiders']))
		$bracketList[] = comma_format($return['num_spiders']) . ' ' . ($return['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($return['num_users_hidden']))
		$bracketList[] = comma_format($return['num_users_hidden']) . ' ' . $txt['hidden'];

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo '<br />
			', implode(', ', $return['list_users_online']);

	// Showing membergroups?
	if (!empty($settings['show_group_key']) && !empty($return['membergroups']))
		echo '<br />
			[' . implode(']&nbsp;&nbsp;[', $return['membergroups']) . ']';
}

// Just like whosOnline except it also logs the online presence.
function ssi_logOnline($output_method = 'echo')
{
	writeLog();

	if ($output_method != 'echo')
		return ssi_whosOnline($output_method);
	else
		ssi_whosOnline($output_method);
}

// Shows a login box.
function ssi_login($redirect_to = '', $output_method = 'echo')
{
	global $scripturl, $txt, $user_info, $context, $modSettings;

	if ($redirect_to != '')
		$_SESSION['login_url'] = $redirect_to;

	if ($output_method != 'echo' || !$user_info['is_guest'])
		return $user_info['is_guest'];

	echo '
		<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '">
			<table border="0" cellspacing="1" cellpadding="0" class="ssi_table">
				<tr>
					<td align="right"><label for="user">', $txt['username'], ':</label>&nbsp;</td>
					<td><input type="text" id="user" name="user" size="9" value="', $user_info['username'], '" class="input_text" /></td>
				</tr><tr>
					<td align="right"><label for="passwrd">', $txt['password'], ':</label>&nbsp;</td>
					<td><input type="password" name="passwrd" id="passwrd" size="9" class="input_password" /></td>
				</tr>';

	// Open ID?
	if (!empty($modSettings['enableOpenID']))
		echo '<tr>
					<td colspan="2" align="center"><strong>&mdash;', $txt['or'], '&mdash;</strong></td>
				</tr><tr>
					<td align="right"><label for="openid_url">', $txt['openid'], ':</label>&nbsp;</td>
					<td><input type="text" name="openid_identifier" id="openid_url" class="input_text openid_login" size="17" /></td>
				</tr>';

	echo '<tr>
					<td><input type="hidden" name="cookielength" value="-1" /></td>
					<td><input type="submit" value="', $txt['login'], '" class="button_submit" /></td>
				</tr>
			</table>
		</form>';

}

// Show the most-voted-in poll.
function ssi_topPoll($output_method = 'echo')
{
	// Just use recentPoll, no need to duplicate code...
	return ssi_recentPoll(true, $output_method);
}

// Show the most recently posted poll.
function ssi_recentPoll($topPollInstead = false, $output_method = 'echo')
{
	global $db_prefix, $txt, $settings, $boardurl, $user_info, $context, $smcFunc, $modSettings;

	$boardsAllowed = array_intersect(boardsAllowedTo('poll_view'), boardsAllowedTo('poll_vote'));

	if (empty($boardsAllowed))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT p.id_poll, p.question, t.id_topic, p.max_votes, p.guest_vote, p.hide_results, p.expire_time
		FROM {db_prefix}polls AS p
			INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' . ($topPollInstead ? '
			INNER JOIN {db_prefix}poll_choices AS pc ON (pc.id_poll = p.id_poll)' : '') . '
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member > {int:no_member} AND lp.id_member = {int:current_member})
		WHERE p.voting_locked = {int:voting_opened}
			AND (p.expire_time = {int:no_expiration} OR {int:current_time} < p.expire_time)
			AND ' . ($user_info['is_guest'] ? 'p.guest_vote = {int:guest_vote_allowed}' : 'lp.id_choice IS NULL') . '
			AND {query_wanna_see_board}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_list})' : '') . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_enable}' : '') . '
		ORDER BY ' . ($topPollInstead ? 'pc.votes' : 'p.id_poll') . ' DESC
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'boards_allowed_list' => $boardsAllowed,
			'is_approved' => 1,
			'guest_vote_allowed' => 1,
			'no_member' => 0,
			'voting_opened' => 0,
			'no_expiration' => 0,
			'current_time' => time(),
			'recycle_enable' => $modSettings['recycle_board'],
		)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// This user has voted on all the polls.
	if ($row === false)
		return array();

	// If this is a guest who's voted we'll through ourselves to show poll to show the results.
	if ($user_info['is_guest'] && (!$row['guest_vote'] || (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote'])))))
		return ssi_showPoll($row['id_topic'], $output_method);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$options = array();
	while ($rowChoice = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($rowChoice['label']);

		$options[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
	}
	$smcFunc['db_free_result']($request);

	// Can they view it?
	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || $is_expired;

	$return = array(
		'id' => $row['id_poll'],
		'image' => 'poll',
		'question' => $row['question'],
		'total_votes' => $total,
		'is_locked' => false,
		'topic' => $row['id_topic'],
		'allow_view_results' => $allow_view_results,
		'options' => array()
	);

	// Calculate the percentages and bar lengths...
	$divisor = $return['total_votes'] == 0 ? 1 : $return['total_votes'];
	foreach ($options as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$barWide = $bar == 0 ? 1 : floor(($bar * 5) / 3);
		$return['options'][$i] = array(
			'id' => 'options-' . ($topPollInstead ? 'top-' : 'recent-') . $i,
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<span style="white-space: nowrap;"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.gif" alt="" /><img src="' . $settings['images_url'] . '/poll_middle.gif" width="' . $barWide . '" height="12" alt="-" /><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.gif" alt="" /></span>',
			'option' => parse_bbc($option[0]),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . ($topPollInstead ? 'top-' : 'recent-') . $i . '" value="' . $i . '" class="input_' . ($row['max_votes'] > 1 ? 'check' : 'radio') . '" />'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($options), $row['max_votes'])) : '';

	if ($output_method != 'echo')
		return $return;

	if ($allow_view_results)
	{
		echo '
		<form class="ssi_poll" action="', $boardurl, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="', $context['character_set'], '">
			<strong>', $return['question'], '</strong><br />
			', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br />' : '';

		foreach ($return['options'] as $option)
			echo '
			<label for="', $option['id'], '">', $option['vote_button'], ' ', $option['option'], '</label><br />';

		echo '
			<input type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
			<input type="hidden" name="poll" value="', $return['id'], '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
	}
	else
		echo $txt['poll_cannot_see'];
}

function ssi_showPoll($topic = null, $output_method = 'echo')
{
	global $db_prefix, $txt, $settings, $boardurl, $user_info, $context, $smcFunc, $modSettings;

	$boardsAllowed = boardsAllowedTo('poll_view');

	if (empty($boardsAllowed))
		return array();

	if ($topic === null && isset($_REQUEST['ssi_topic']))
		$topic = (int) $_REQUEST['ssi_topic'];
	else
		$topic = (int) $topic;

	$request = $smcFunc['db_query']('', '
		SELECT
			p.id_poll, p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.guest_vote, b.id_board
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_topic = {int:current_topic}
			AND {query_see_board}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_see})' : '') . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . '
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'boards_allowed_see' => $boardsAllowed,
			'is_approved' => 1,
		)
	);

	// Either this topic has no poll, or the user cannot view it.
	if ($smcFunc['db_num_rows']($request) == 0)
		return array();

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Check if they can vote.
	if (!empty($row['expire_time']) && $row['expire_time'] < time())
		$allow_vote = false;
	elseif ($user_info['is_guest'] && $row['guest_vote'] && (!isset($_COOKIE['guest_poll_vote']) || !in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote']))))
		$allow_vote = true;
	elseif ($user_info['is_guest'])
		$allow_vote = false;
	elseif (!empty($row['voting_locked']) || !allowedTo('poll_vote', $row['id_board']))
		$allow_vote = false;
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_polls
			WHERE id_poll = {int:current_poll}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'current_poll' => $row['id_poll'],
			)
		);
		$allow_vote = $smcFunc['db_num_rows']($request) == 0;
		$smcFunc['db_free_result']($request);
	}

	// Can they view?
	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || ($row['hide_results'] == 1 && !$allow_vote) || $is_expired;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$options = array();
	$total_votes = 0;
	while ($rowChoice = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($rowChoice['label']);

		$options[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
		$total_votes += $rowChoice['votes'];
	}
	$smcFunc['db_free_result']($request);

	$return = array(
		'id' => $row['id_poll'],
		'image' => empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll',
		'question' => $row['question'],
		'total_votes' => $total,
		'is_locked' => !empty($pollinfo['voting_locked']),
		'allow_vote' => $allow_vote,
		'allow_view_results' => $allow_view_results,
		'topic' => $topic
	);

	// Calculate the percentages and bar lengths...
	$divisor = $total_votes == 0 ? 1 : $total_votes;
	foreach ($options as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$barWide = $bar == 0 ? 1 : floor(($bar * 5) / 3);
		$return['options'][$i] = array(
			'id' => 'options-' . $i,
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<span style="white-space: nowrap;"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.gif" alt="" /><img src="' . $settings['images_url'] . '/poll_middle.gif" width="' . $barWide . '" height="12" alt="-" /><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.gif" alt="" /></span>',
			'option' => parse_bbc($option[0]),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . $i . '" value="' . $i . '" class="input_' . ($row['max_votes'] > 1 ? 'check' : 'radio') . '" />'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($options), $row['max_votes'])) : '';

	if ($output_method != 'echo')
		return $return;

	if ($return['allow_vote'])
	{
		echo '
			<form class="ssi_poll" action="', $boardurl, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="', $context['character_set'], '">
				<strong>', $return['question'], '</strong><br />
				', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br />' : '';

		foreach ($return['options'] as $option)
			echo '
				<label for="', $option['id'], '">', $option['vote_button'], ' ', $option['option'], '</label><br />';

		echo '
				<input type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
				<input type="hidden" name="poll" value="', $return['id'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>';
	}
	elseif ($return['allow_view_results'])
	{
		echo '
			<div class="ssi_poll">
				<strong>', $return['question'], '</strong>
				<dl>';

		foreach ($return['options'] as $option)
			echo '
					<dt>', $option['option'], '</dt>
					<dd>
						<div class="ssi_poll_bar" style="border: 1px solid #666; height: 1em">
							<div class="ssi_poll_bar_fill" style="background: #ccf; height: 1em; width: ', $option['percent'], '%;">
							</div>
						</div>
						', $option['votes'], ' (', $option['percent'], '%)
					</dd>';
		echo '
				</dl>
				<strong>', $txt['poll_total_voters'], ': ', $return['total_votes'], '</strong>
			</div>';
	}
	// Cannot see it I'm afraid!
	else
		echo $txt['poll_cannot_see'];
}

// Takes care of voting - don't worry, this is done automatically.
function ssi_pollVote()
{
	global $context, $db_prefix, $user_info, $sc, $smcFunc, $sourcedir, $modSettings;

	if (!isset($_POST[$context['session_var']]) || $_POST[$context['session_var']] != $sc || empty($_POST['options']) || !isset($_POST['poll']))
	{
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<script type="text/javascript"><!-- // --><![CDATA[
		history.go(-1);
	// ]]></script>
</head>
<body>&laquo;</body>
</html>';
		return;
	}

	// This can cause weird errors! (ie. copyright missing.)
	checkSession();

	$_POST['poll'] = (int) $_POST['poll'];

	// Check if they have already voted, or voting is locked.
	$request = $smcFunc['db_query']('', '
		SELECT
			p.id_poll, p.voting_locked, p.expire_time, p.max_votes, p.guest_vote,
			t.id_topic,
			IFNULL(lp.id_choice, -1) AS selected
		FROM {db_prefix}polls AS p
			INNER JOIN {db_prefix}topics AS t ON (t.id_poll = {int:current_poll})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member = {int:current_member})
		WHERE p.id_poll = {int:current_poll}
			AND {query_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . '
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'current_poll' => $_POST['poll'],
			'is_approved' => 1,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		die;
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($row['voting_locked']) || ($row['selected'] != -1 && !$user_info['is_guest']) || (!empty($row['expire_time']) && time() > $row['expire_time']))
		redirectexit('topic=' . $row['id_topic'] . '.0');

	// Too many options checked?
	if (count($_REQUEST['options']) > $row['max_votes'])
		redirectexit('topic=' . $row['id_topic'] . '.0');

	// It's a guest who has already voted?
	if ($user_info['is_guest'])
	{
		// Guest voting disabled?
		if (!$row['guest_vote'])
			redirectexit('topic=' . $row['id_topic'] . '.0');
		// Already voted?
		elseif (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote'])))
			redirectexit('topic=' . $row['id_topic'] . '.0');
	}

	$options = array();
	$inserts = array();
	foreach ($_REQUEST['options'] as $id)
	{
		$id = (int) $id;

		$options[] = $id;
		$inserts[] = array($_POST['poll'], $user_info['id'], $id);
	}

	// Add their vote in to the tally.
	$smcFunc['db_insert']('insert',
		$db_prefix . 'log_polls',
		array('id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'),
		$inserts,
		array('id_poll', 'id_member', 'id_choice')
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}poll_choices
		SET votes = votes + 1
		WHERE id_poll = {int:current_poll}
			AND id_choice IN ({array_int:option_list})',
		array(
			'option_list' => $options,
			'current_poll' => $_POST['poll'],
		)
	);

	// Track the vote if a guest.
	if ($user_info['is_guest'])
	{
		$_COOKIE['guest_poll_vote'] = !empty($_COOKIE['guest_poll_vote']) ? ($_COOKIE['guest_poll_vote'] . ',' . $row['id_poll']) : $row['id_poll'];

		require_once($sourcedir . '/Subs-Auth.php');
		$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
		setcookie('guest_poll_vote', $_COOKIE['guest_poll_vote'], time() + 2500000, $cookie_url[1], $cookie_url[0], 0);
	}

	redirectexit('topic=' . $row['id_topic'] . '.0');
}

// Show a search box.
function ssi_quickSearch($output_method = 'echo')
{
	global $scripturl, $txt, $context;

	if ($output_method != 'echo')
		return $scripturl . '?action=search';

	echo '
		<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="advanced" value="0" /><input type="text" name="search" size="30" class="input_text" /> <input type="submit" name="submit" value="', $txt['search'], '" class="button_submit" />
		</form>';
}

// Show what would be the forum news.
function ssi_news($output_method = 'echo')
{
	global $context;

	if ($output_method != 'echo')
		return $context['random_news_line'];

	echo $context['random_news_line'];
}

// Show today's birthdays.
function ssi_todaysBirthdays($output_method = 'echo')
{
	global $scripturl, $modSettings, $user_info;

	$eventOptions = array(
		'include_birthdays' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'Subs-Calendar.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return['calendar_birthdays'];

	foreach ($return['calendar_birthdays'] as $member)
		echo '
			<a href="', $scripturl, '?action=profile;u=', $member['id'], '">' . $member['name'] . (isset($member['age']) ? ' (' . $member['age'] . ')' : '') . '</a>' . (!$member['is_last'] ? ', ' : '');
}

// Show today's holidays.
function ssi_todaysHolidays($output_method = 'echo')
{
	global $modSettings, $user_info;

	$eventOptions = array(
		'include_holidays' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'Subs-Calendar.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return['calendar_holidays'];

	echo '
		', implode(', ', $return['calendar_holidays']);
}

// Show today's events.
function ssi_todaysEvents($output_method = 'echo')
{
	global $modSettings, $user_info;

	$eventOptions = array(
		'include_events' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'Subs-Calendar.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return['calendar_events'];

	foreach ($return['calendar_events'] as $event)
	{
		if ($event['can_edit'])
			echo '
	<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
		echo '
	' . $event['link'] . (!$event['is_last'] ? ', ' : '');
	}
}

// Show all calendar entires for today. (birthdays, holodays, and events.)
function ssi_todaysCalendar($output_method = 'echo')
{
	global $modSettings, $txt, $scripturl, $user_info;

	$eventOptions = array(
		'include_birthdays' => true,
		'include_holidays' => true,
		'include_events' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'Subs-Calendar.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return;

	if (!empty($return['calendar_holidays']))
		echo '
			<span class="holiday">' . $txt['calendar_prompt'] . ' ' . implode(', ', $return['calendar_holidays']) . '<br /></span>';
	if (!empty($return['calendar_birthdays']))
	{
		echo '
			<span class="birthday">' . $txt['birthdays_upcoming'] . '</span> ';
		foreach ($return['calendar_birthdays'] as $member)
			echo '
			<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', !$member['is_last'] ? ', ' : '';
		echo '
			<br />';
	}
	if (!empty($return['calendar_events']))
	{
		echo '
			<span class="event">' . $txt['events_upcoming'] . '</span> ';
		foreach ($return['calendar_events'] as $event)
		{
			if ($event['can_edit'])
				echo '
			<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
			echo '
			' . $event['link'] . (!$event['is_last'] ? ', ' : '');
		}
	}
}

// Show the latest news, with a template... by board.
function ssi_boardNews($board = null, $limit = null, $start = null, $length = null, $output_method = 'echo')
{
	global $scripturl, $db_prefix, $txt, $settings, $modSettings, $context;
	global $smcFunc;

	loadLanguage('Stats');

	// Must be integers....
	if ($limit === null)
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
	else
		$limit = (int) $limit;

	if ($start === null)
		$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
	else
		$start = (int) $start;

	if ($board !== null)
		$board = (int) $board;
	elseif (isset($_GET['board']))
		$board = (int) $_GET['board'];

	if ($length === null)
		$length = isset($_GET['length']) ? (int) $_GET['length'] : 0;
	else
		$length = (int) $length;

	$limit = max(0, $limit);
	$start = max(0, $start);

	// Make sure guests can see this board.
	$request = $smcFunc['db_query']('', '
		SELECT id_board
		FROM {db_prefix}boards
		WHERE ' . ($board === null ? '' : 'id_board = {int:current_board}
			AND ') . 'FIND_IN_SET(-1, member_groups)
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		if ($output_method == 'echo')
			die($txt['ssi_no_guests']);
		else
			return array();
	}
	list ($board) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Load the message icons - the usual suspects.
	$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless');
	$icon_sources = array();
	foreach ($stable_icons as $icon)
		$icon_sources[$icon] = 'images_url';

	// Find the post ids.
	$request = $smcFunc['db_query']('', '
		SELECT id_first_msg
		FROM {db_prefix}topics
		WHERE id_board = {int:current_board}' . ($modSettings['postmod_active'] ? '
			AND approved = {int:is_approved}' : '') . '
		ORDER BY id_first_msg DESC
		LIMIT ' . $start . ', ' . $limit,
		array(
			'current_board' => $board,
			'is_approved' => 1,
		)
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$posts[] = $row['id_first_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($posts))
		return array();

	// Find the posts.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.icon, m.subject, m.body, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			t.num_replies, t.id_topic, m.id_member, m.smileys_enabled, m.id_msg, t.locked, t.id_last_msg
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_first_msg IN ({array_int:post_list})
		ORDER BY t.id_first_msg DESC
		LIMIT ' . count($posts),
		array(
			'post_list' => $posts,
		)
	);
	$return = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If we want to limit the length of the post.
		if (!empty($length) && $smcFunc['strlen']($row['body']) > $length)
		{
			$row['body'] = $smcFunc['substr']($row['body'], 0, $length);

			// The first space or line break. (<br />, etc.)
			$cutoff = max(strrpos($row['body'], ' '), strrpos($row['body'], '<'));

			if ($cutoff !== false)
				$row['body'] = $smcFunc['substr']($row['body'], 0, $cutoff);
			$row['body'] .= '...';
		}

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// Check that this message icon is there...
		if (empty($modSettings['messageIconChecks_disable']) && !isset($icon_sources[$row['icon']]))
			$icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.gif') ? 'images_url' : 'default_images_url';

		censorText($row['subject']);
		censorText($row['body']);

		$return[] = array(
			'id' => $row['id_topic'],
			'message_id' => $row['id_msg'],
			'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.gif" alt="' . $row['icon'] . '" />',
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => $row['body'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['num_replies'] . ' ' . ($row['num_replies'] == 1 ? $txt['ssi_comment'] : $txt['ssi_comments']) . '</a>',
			'replies' => $row['num_replies'],
			'comment_href' => !empty($row['locked']) ? '' : $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'],
			'comment_link' => !empty($row['locked']) ? '' : '<a href="' . $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'new_comment' => !empty($row['locked']) ? '' : '<a href="' . $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'link' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name']
			),
			'locked' => !empty($row['locked']),
			'is_last' => false
		);
	}
	$smcFunc['db_free_result']($request);

	if (empty($return))
		return $return;

	$return[count($return) - 1]['is_last'] = true;

	if ($output_method != 'echo')
		return $return;

	foreach ($return as $news)
	{
		echo '
			<div class="news_item">
				<h3 class="news_header">
					', $news['icon'], '
					<a href="', $news['href'], '">', $news['subject'], '</a>
				</h3>
				<div class="news_timestamp">', $news['time'], ' ', $txt['by'], ' ', $news['poster']['link'], '</div>
				<div class="news_body" style="padding: 2ex 0;">', $news['body'], '</div>
				', $news['link'], $news['locked'] ? '' : ' | ' . $news['comment_link'], '
			</div>';

		if (!$news['is_last'])
			echo '
			<hr />';
	}
}

// Show the most recent events.
function ssi_recentEvents($max_events = 7, $output_method = 'echo')
{
	global $db_prefix, $user_info, $scripturl, $modSettings, $txt, $context, $smcFunc;

	// Find all events which are happening in the near future that the member can see.
	$request = $smcFunc['db_query']('', '
		SELECT
			cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, cal.id_topic,
			cal.id_board, t.id_first_msg, t.approved
		FROM {db_prefix}calendar AS cal
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)
		WHERE cal.start_date <= {date:current_date}
			AND cal.end_date >= {date:current_date}
			AND (cal.id_board = {int:no_board} OR {query_wanna_see_board})
		ORDER BY cal.start_date DESC
		LIMIT ' . $max_events,
		array(
			'current_date' => strftime('%Y-%m-%d', forum_time(false)),
			'no_board' => 0,
		)
	);
	$return = array();
	$duplicates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Check if we've already come by an event linked to this same topic with the same title... and don't display it if we have.
		if (!empty($duplicates[$row['title'] . $row['id_topic']]))
			continue;

		// Censor the title.
		censorText($row['title']);

		if ($row['start_date'] < strftime('%Y-%m-%d', forum_time(false)))
			$date = strftime('%Y-%m-%d', forum_time(false));
		else
			$date = $row['start_date'];

		// If the topic it is attached to is not approved then don't link it.
		if (!empty($row['id_first_msg']) && !$row['approved'])
			$row['id_board'] = $row['id_topic'] = $row['id_first_msg'] = 0;

		$return[$date][] = array(
			'id' => $row['id_event'],
			'title' => $row['title'],
			'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
			'modify_href' => $scripturl . '?action=' . ($row['id_board'] == 0 ? 'calendar;sa=post;' : 'post;msg=' . $row['id_first_msg'] . ';topic=' . $row['id_topic'] . '.0;calendar;') . 'eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'href' => $row['id_board'] == 0 ? '' : $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => $row['id_board'] == 0 ? $row['title'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
			'start_date' => $row['start_date'],
			'end_date' => $row['end_date'],
			'is_last' => false
		);

		// Let's not show this one again, huh?
		$duplicates[$row['title'] . $row['id_topic']] = true;
	}
	$smcFunc['db_free_result']($request);

	foreach ($return as $mday => $array)
		$return[$mday][count($array) - 1]['is_last'] = true;

	if ($output_method != 'echo' || empty($return))
		return $return;

	// Well the output method is echo.
	echo '
			<span class="event">' . $txt['events'] . '</span> ';
	foreach ($return as $mday => $array)
		foreach ($array as $event)
		{
			if ($event['can_edit'])
				echo '
				<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';

			echo '
				' . $event['link'] . (!$event['is_last'] ? ', ' : '');
		}
}

// Check the passed id_member/password.  If $is_username is true, treats $id as a username.
function ssi_checkPassword($id = null, $password = null, $is_username = false)
{
	global $db_prefix, $sourcedir, $smcFunc;

	// If $id is null, this was most likely called from a query string and should do nothing.
	if ($id === null)
		return;

	$request = $smcFunc['db_query']('', '
		SELECT passwd, member_name, is_activated
		FROM {db_prefix}members
		WHERE ' . ($is_username ? 'member_name' : 'id_member') . ' = {string:id}
		LIMIT 1',
		array(
			'id' => $id,
		)
	);
	list ($pass, $user, $active) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return sha1(strtolower($user) . $password) == $pass && $active == 1;
}

// We want to show the recent attachments outside of the forum.
function ssi_recentAttachments($num_attachments = 10, $attachment_ext = array(), $output_method = 'echo')
{
	global $smcFunc, $context, $modSettings, $scripturl, $txt, $settings;

	// We want to make sure that we only get attachments for boards that we can see *if* any.
	$attachments_boards = boardsAllowedTo('view_attachments');

	// No boards?  Adios amigo.
	if (empty($attachments_boards))
		return array();

	// Is it an array?
	if (!is_array($attachment_ext))
		$attachment_ext = array($attachment_ext);

	// Lets build the query.
	$request = $smcFunc['db_query']('', '
		SELECT
			att.id_attach, att.id_msg, att.filename, IFNULL(att.size, 0) AS filesize, att.downloads, mem.id_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.id_topic, m.subject, t.id_board, m.poster_time,
			att.width, att.height' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ', IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
		FROM {db_prefix}attachments AS att
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = att.id_msg)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = att.id_thumb)') . '
		WHERE att.attachment_type = 0' . ($attachments_boards === array(0) ? '' : '
			AND m.id_board IN ({array_int:boards_can_see})') . (!empty($attachment_ext) ? '
			AND att.fileext IN ({array_string:attachment_ext})' : '') .
			(!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}
			AND att.approved = {int:is_approved}') . '
		ORDER BY att.id_attach DESC
		LIMIT {int:num_attachments}',
		array(
			'boards_can_see' => $attachments_boards,
			'attachment_ext' => $attachment_ext,
			'num_attachments' => $num_attachments,
			'is_approved' => 1,
		)
	);

	// We have something.
	$attachments = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$filename = preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($row['filename']));

		// Is it an image?
		$attachments[$row['id_attach']] = array(
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
			),
			'file' => array(
				'filename' => $filename,
				'filesize' => round($row['filesize'] /1024, 2) . $txt['kilobyte'],
				'downloads' => $row['downloads'],
				'href' => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'],
				'link' => '<img src="' . $settings['images_url'] . '/icons/clip.gif" alt="" /> <a href="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . '">' . $filename . '</a>',
				'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($modSettings['attachmentShowImages']),
			),
			'topic' => array(
				'id' => $row['id_topic'],
				'subject' => $row['subject'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'time' => timeformat($row['poster_time']),
			),
		);

		// Images.
		if ($attachments[$row['id_attach']]['file']['is_image'])
		{
			$id_thumb = empty($row['id_thumb']) ? $row['id_attach'] : $row['id_thumb'];
			$attachments[$row['id_attach']]['file']['image'] = array(
				'id' => $id_thumb,
				'width' => $row['width'],
				'height' => $row['height'],
				'img' => '<img src="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image" alt="' . $filename . '" />',
				'thumb' => '<img src="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '" />',
				'href' => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image',
				'link' => '<a href="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image"><img src="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '" /></a>',
			);
		}
	}
	$smcFunc['db_free_result']($request);

	// So you just want an array?  Here you can have it.
	if ($output_method == 'array' || empty($attachments))
		return $attachments;

	// Give them the default.
	echo '
		<table class="ssi_downloads" cellpadding="2">
			<tr>
				<th align="left">', $txt['file'], '</th>
				<th align="left">', $txt['posted_by'], '</th>
				<th align="left">', $txt['downloads'], '</th>
				<th align="left">', $txt['filesize'], '</th>
			</tr>';
	foreach ($attachments as $attach)
		echo '
			<tr>
				<td>', $attach['file']['link'], '</td>
				<td>', $attach['member']['link'], '</td>
				<td align="center">', $attach['file']['downloads'], '</td>
				<td>', $attach['file']['filesize'], '</td>
			</tr>';
	echo '
		</table>';
}

?>