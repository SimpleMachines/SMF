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

/*	This, as you have probably guessed, is the crux on which SMF functions.
	Everything should start here, so all the setup and security is done
	properly.  The most interesting part of this file is the action array in
	the smf_main() function.  It is formatted as so:

		'action-in-url' => array('Source-File.php', 'FunctionToCall'),

	Then, you can access the FunctionToCall() function from Source-File.php
	with the URL index.php?action=action-in-url.  Relatively simple, no?
*/

$forum_version = 'SMF 2.0';

// Get everything started up...
define('SMF', 1);
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime(0);
error_reporting(defined('E_STRICT') ? E_ALL | E_STRICT : E_ALL);
$time_start = microtime();

// This makes it so headers can be sent!
ob_start();

// Do some cleaning, just in case.
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable]);

// Load the settings...
require_once(dirname(__FILE__) . '/Settings.php');

// Make absolutely sure the cache directory is defined.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

// And important includes.
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Security.php');

// Using an pre-PHP 5.1 version?
if (@version_compare(PHP_VERSION, '5.1') == -1)
	require_once($sourcedir . '/Subs-Compat.php');

// If $maintenance is set specifically to 2, then we're upgrading or something.
if (!empty($maintenance) && $maintenance == 2)
	db_fatal_error();

// Create a variable to store some SMF specific functions in.
$smcFunc = array();

// Initate the database connection and define some database functions to use.
loadDatabase();

// Load the settings from the settings table, and perform operations like optimizing.
reloadSettings();
// Clean the request variables, add slashes, etc.
cleanRequest();
$context = array();

// Seed the random generator.
if (empty($modSettings['rand_seed']) || mt_rand(1, 250) == 69)
	smf_seed_generator();

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if (isset($_GET['scheduled']))
{
	require_once($sourcedir . '/ScheduledTasks.php');
	AutoTask();
}

// Check if compressed output is enabled, supported, and not already being done.
if (!empty($modSettings['enableCompressedOutput']) && !headers_sent())
{
	// If zlib is being used, turn off output compression.
	if (@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler' || @version_compare(PHP_VERSION, '4.2.0') == -1)
		$modSettings['enableCompressedOutput'] = '0';
	else
	{
		ob_end_clean();
		ob_start('ob_gzhandler');
	}
}

// Register an error handler.
set_error_handler('error_handler');

// Start the session. (assuming it hasn't already been.)
loadSession();

// Determine if this is using WAP, WAP2, or imode.  Technically, we should check that wap comes before application/xhtml or text/html, but this doesn't work in practice as much as it should.
if (isset($_REQUEST['wap']) || isset($_REQUEST['wap2']) || isset($_REQUEST['imode']))
	unset($_SESSION['nowap']);
elseif (isset($_REQUEST['nowap']))
	$_SESSION['nowap'] = true;
elseif (!isset($_SESSION['nowap']))
{
	if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/vnd.wap.xhtml+xml') !== false)
		$_REQUEST['wap2'] = 1;
	elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.wap.wml') !== false)
	{
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'DoCoMo/') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'portalmmm/') !== false)
			$_REQUEST['imode'] = 1;
		else
			$_REQUEST['wap'] = 1;
	}
}

if (!defined('WIRELESS'))
	define('WIRELESS', isset($_REQUEST['wap']) || isset($_REQUEST['wap2']) || isset($_REQUEST['imode']));

// Some settings and headers are different for wireless protocols.
if (WIRELESS)
{
	define('WIRELESS_PROTOCOL', isset($_REQUEST['wap']) ? 'wap' : (isset($_REQUEST['wap2']) ? 'wap2' : (isset($_REQUEST['imode']) ? 'imode' : '')));

	// Some cellphones can't handle output compression...
	$modSettings['enableCompressedOutput'] = '0';
	// !!! Do we want these hard coded?
	$modSettings['defaultMaxMessages'] = 5;
	$modSettings['defaultMaxTopics'] = 9;

	// Wireless protocol header.
	if (WIRELESS_PROTOCOL == 'wap')
		header('Content-Type: text/vnd.wap.wml');
}

// Restore post data if we are revalidating OpenID.
if (isset($_GET['openid_restore_post']) && !empty($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post']) && empty($_POST))
{
	$_POST = $_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post'];
	unset($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]);
}

// What function shall we execute? (done like this for memory's sake.)
call_user_func(smf_main());

// Call obExit specially; we're coming from the main area ;).
obExit(null, null, true);

// The main controlling function.
function smf_main()
{
	global $modSettings, $settings, $user_info, $board, $topic, $board_info, $maintenance, $sourcedir;

	// Special case: session keep-alive, output a transparent pixel.
	if (isset($_GET['action']) && $_GET['action'] == 'keepalive')
	{
		header('Content-Type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	// Load the user's cookie (or set as guest) and load their settings.
	loadUserSettings();

	// Load the current board's information.
	loadBoard();

	// Load the current user's permissions.
	loadPermissions();

	// Attachments don't require the entire theme to be loaded.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'dlattach' && (!empty($modSettings['allow_guestAccess']) && $user_info['is_guest']))
		detectBrowser();
	// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
	else
		loadTheme();

	// Check if the user should be disallowed access.
	is_not_banned();

	// If we are in a topic and don't have permission to approve it then duck out now.
	if (!empty($topic) && empty($board_info['cur_topic_approved']) && !allowedTo('approve_posts') && ($user_info['id'] != $board_info['cur_topic_starter'] || $user_info['is_guest']))
		fatal_lang_error('not_a_topic', false);

	// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
	if (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], array('dlattach', 'findmember', 'jseditor', 'jsoption', 'requestmembers', 'smstats', '.xml', 'xmlhttp', 'verificationcode', 'viewquery', 'viewsmfile')))
	{
		// Log this user as online.
		writeLog();

		// Track forum statistics and hits...?
		if (!empty($modSettings['hitStats']))
			trackStats(array('hits' => '+'));
	}

	// Is the forum in maintenance mode? (doesn't apply to administrators.)
	if (!empty($maintenance) && !allowedTo('admin_forum'))
	{
		// You can only login.... otherwise, you're getting the "maintenance mode" display.
		if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'login2' || $_REQUEST['action'] == 'logout'))
		{
			require_once($sourcedir . '/LogInOut.php');
			return $_REQUEST['action'] == 'login2' ? 'Login2' : 'Logout';
		}
		// Don't even try it, sonny.
		else
		{
			require_once($sourcedir . '/Subs-Auth.php');
			return 'InMaintenance';
		}
	}
	// If guest access is off, a guest can only do one of the very few following actions.
	elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], array('coppa', 'login', 'login2', 'register', 'register2', 'reminder', 'activate', 'help', 'smstats', 'mailq', 'verificationcode', 'openidreturn'))))
	{
		require_once($sourcedir . '/Subs-Auth.php');
		return 'KickGuest';
	}
	elseif (empty($_REQUEST['action']))
	{
		// Action and board are both empty... BoardIndex!
		if (empty($board) && empty($topic))
		{
			require_once($sourcedir . '/BoardIndex.php');
			return 'BoardIndex';
		}
		// Topic is empty, and action is empty.... MessageIndex!
		elseif (empty($topic))
		{
			require_once($sourcedir . '/MessageIndex.php');
			return 'MessageIndex';
		}
		// Board is not empty... topic is not empty... action is empty.. Display!
		else
		{
			require_once($sourcedir . '/Display.php');
			return 'Display';
		}
	}

	// Here's the monstrous $_REQUEST['action'] array - $_REQUEST['action'] => array($file, $function).
	$actionArray = array(
		'activate' => array('Register.php', 'Activate'),
		'admin' => array('Admin.php', 'AdminMain'),
		'announce' => array('Post.php', 'AnnounceTopic'),
		'attachapprove' => array('ManageAttachments.php', 'ApproveAttach'),
		'buddy' => array('Subs-Members.php', 'BuddyListToggle'),
		'calendar' => array('Calendar.php', 'CalendarMain'),
		'clock' => array('Calendar.php', 'clock'),
		'collapse' => array('BoardIndex.php', 'CollapseCategory'),
		'coppa' => array('Register.php', 'CoppaForm'),
		'credits' => array('Who.php', 'Credits'),
		'deletemsg' => array('RemoveTopic.php', 'DeleteMessage'),
		'display' => array('Display.php', 'Display'),
		'dlattach' => array('Display.php', 'Download'),
		'editpoll' => array('Poll.php', 'EditPoll'),
		'editpoll2' => array('Poll.php', 'EditPoll2'),
		'emailuser' => array('SendTopic.php', 'EmailUser'),
		'findmember' => array('Subs-Auth.php', 'JSMembers'),
		'groups' => array('Groups.php', 'Groups'),
		'help' => array('Help.php', 'ShowHelp'),
		'helpadmin' => array('Help.php', 'ShowAdminHelp'),
		'im' => array('PersonalMessage.php', 'MessageMain'),
		'jseditor' => array('Subs-Editor.php', 'EditorMain'),
		'jsmodify' => array('Post.php', 'JavaScriptModify'),
		'jsoption' => array('Themes.php', 'SetJavaScript'),
		'lock' => array('LockTopic.php', 'LockTopic'),
		'lockvoting' => array('Poll.php', 'LockVoting'),
		'login' => array('LogInOut.php', 'Login'),
		'login2' => array('LogInOut.php', 'Login2'),
		'logout' => array('LogInOut.php', 'Logout'),
		'markasread' => array('Subs-Boards.php', 'MarkRead'),
		'mergetopics' => array('SplitTopics.php', 'MergeTopics'),
		'mlist' => array('Memberlist.php', 'Memberlist'),
		'moderate' => array('ModerationCenter.php', 'ModerationMain'),
		'modifycat' => array('ManageBoards.php', 'ModifyCat'),
		'modifykarma' => array('Karma.php', 'ModifyKarma'),
		'movetopic' => array('MoveTopic.php', 'MoveTopic'),
		'movetopic2' => array('MoveTopic.php', 'MoveTopic2'),
		'notify' => array('Notify.php', 'Notify'),
		'notifyboard' => array('Notify.php', 'BoardNotify'),
		'openidreturn' => array('Subs-OpenID.php', 'smf_openID_return'),
		'pm' => array('PersonalMessage.php', 'MessageMain'),
		'post' => array('Post.php', 'Post'),
		'post2' => array('Post.php', 'Post2'),
		'printpage' => array('Printpage.php', 'PrintTopic'),
		'profile' => array('Profile.php', 'ModifyProfile'),
		'quotefast' => array('Post.php', 'QuoteFast'),
		'quickmod' => array('MessageIndex.php', 'QuickModeration'),
		'quickmod2' => array('Display.php', 'QuickInTopicModeration'),
		'recent' => array('Recent.php', 'RecentPosts'),
		'register' => array('Register.php', 'Register'),
		'register2' => array('Register.php', 'Register2'),
		'reminder' => array('Reminder.php', 'RemindMe'),
		'removepoll' => array('Poll.php', 'RemovePoll'),
		'removetopic2' => array('RemoveTopic.php', 'RemoveTopic2'),
		'reporttm' => array('SendTopic.php', 'ReportToModerator'),
		'requestmembers' => array('Subs-Auth.php', 'RequestMembers'),
		'restoretopic' => array('RemoveTopic.php', 'RestoreTopic'),
		'search' => array('Search.php', 'PlushSearch1'),
		'search2' => array('Search.php', 'PlushSearch2'),
		'sendtopic' => array('SendTopic.php', 'EmailUser'),
		'smstats' => array('Stats.php', 'SMStats'),
		'suggest' => array('Subs-Editor.php', 'AutoSuggestHandler'),
		'spellcheck' => array('Subs-Post.php', 'SpellCheck'),
		'splittopics' => array('SplitTopics.php', 'SplitTopics'),
		'stats' => array('Stats.php', 'DisplayStats'),
		'sticky' => array('LockTopic.php', 'Sticky'),
		'theme' => array('Themes.php', 'ThemesMain'),
		'trackip' => array('Profile-View.php', 'trackIP'),
		'about:mozilla' => array('Karma.php', 'BookOfUnknown'),
		'about:unknown' => array('Karma.php', 'BookOfUnknown'),
		'unread' => array('Recent.php', 'UnreadTopics'),
		'unreadreplies' => array('Recent.php', 'UnreadTopics'),
		'verificationcode' => array('Register.php', 'VerificationCode'),
		'viewprofile' => array('Profile.php', 'ModifyProfile'),
		'vote' => array('Poll.php', 'Vote'),
		'viewquery' => array('ViewQuery.php', 'ViewQuery'),
		'viewsmfile' => array('Admin.php', 'DisplayAdminFile'),
		'who' => array('Who.php', 'Who'),
		'.xml' => array('News.php', 'ShowXmlFeed'),
		'xmlhttp' => array('Xml.php', 'XMLhttpMain'),
	);

	// Allow modifying $actionArray easily.
	call_integration_hook('integrate_actions', array(&$actionArray));

	// Get the function and file to include - if it's not there, do the board index.
	if (!isset($_REQUEST['action']) || !isset($actionArray[$_REQUEST['action']]))
	{
		// Catch the action with the theme?
		if (!empty($settings['catch_action']))
		{
			require_once($sourcedir . '/Themes.php');
			return 'WrapAction';
		}

		// Fall through to the board index then...
		require_once($sourcedir . '/BoardIndex.php');
		return 'BoardIndex';
	}

	// Otherwise, it was set - so let's go to that action.
	require_once($sourcedir . '/' . $actionArray[$_REQUEST['action']][0]);
	return $actionArray[$_REQUEST['action']][1];
}

?>