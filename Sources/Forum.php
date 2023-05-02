<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * The root Forum class. Used when browsing the forum normally.
 *
 * This, as you have probably guessed, is the crux on which SMF functions.
 *
 * The most interesting part of this file for modification authors is the action
 * array. It is formatted as so:
 *
 *    'action-in-url' => array('Source-File.php', 'FunctionToCall'),
 *
 * Then, you can access the FunctionToCall() function from Source-File.php with
 * the URL index.php?action=action-in-url. Relatively simple, no?
 */
class Forum
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * This array defines what file to load and what function to call for each
	 * possible value of $_REQUEST['action'].
	 *
	 * When calling an autoloading class, the file can be left empty.
	 *
	 * Mod authors can add new actions to this via the integrate_actions hook.
	 */
	public static $actions = array(
		'agreement' => array('', 'SMF\\Actions\\Agreement::call'),
		'acceptagreement' => array('', 'SMF\\Actions\\AgreementAccept::call'),
		'activate' => array('', 'SMF\\Actions\\Activate::call'),
		'admin' => array('Admin.php', 'AdminMain'),
		'announce' => array('', 'SMF\\Actions\\Announce::call'),
		'attachapprove' => array('ManageAttachments.php', 'ApproveAttach'),
		'buddy' => array('', 'SMF\\Actions\\BuddyListToggle::call'),
		'calendar' => array('', 'SMF\\Actions\\Calendar::call'),
		'clock' => array('', 'SMF\\Actions\\Calendar::call'), // Deprecated; is now a sub-action
		'coppa' => array('', 'SMF\\Actions\\CoppaForm::call'),
		'credits' => array('', 'SMF\\Actions\\Credits::call'),
		'deletemsg' => array('', 'SMF\\Actions\\MsgDelete::call'),
		'dlattach' => array('', 'SMF\\Actions\\AttachmentDownload::call'),
		'editpoll' => array('Poll.php', 'EditPoll'),
		'editpoll2' => array('Poll.php', 'EditPoll2'),
		'findmember' => array('Subs-Auth.php', 'JSMembers'),
		'groups' => array('', 'SMF\\Actions\\Groups::call'),
		'help' => array('', 'SMF\\Actions\\Help::call'),
		'helpadmin' => array('', 'SMF\\Actions\\HelpAdmin::call'),
		'jsmodify' => array('', 'SMF\\Actions\\JavaScriptModify::call'),
		'jsoption' => array('', 'SMF\\Theme::setJavaScript'),
		'likes' => array('', 'SMF\\Likes::call'),
		'lock' => array('', 'SMF\\Topic::lock'),
		'lockvoting' => array('Poll.php', 'LockVoting'),
		'login' => array('LogInOut.php', 'Login'),
		'login2' => array('LogInOut.php', 'Login2'),
		'logintfa' => array('LogInOut.php', 'LoginTFA'),
		'logout' => array('LogInOut.php', 'Logout'),
		'markasread' => array('', 'SMF\\Board::MarkRead'),
		'mergetopics' => array('SplitTopics.php', 'MergeTopics'),
		'mlist' => array('', 'SMF\\Actions\\Memberlist::call'),
		'moderate' => array('ModerationCenter.php', 'ModerationMain'),
		'modifycat' => array('ManageBoards.php', 'ModifyCat'),
		'movetopic' => array('Actions/MoveTopic2.php', 'MoveTopic'),
		'movetopic2' => array('Actions/MoveTopic2.php', 'MoveTopic2'),
		'notifyannouncements' => array('Notify.php', 'AnnouncementsNotify'),
		'notifyboard' => array('Notify.php', 'BoardNotify'),
		'notifytopic' => array('Notify.php', 'TopicNotify'),
		'pm' => array('PersonalMessage.php', 'MessageMain'),
		'post' => array('', 'SMF\\Actions\\Post::call'),
		'post2' => array('', 'SMF\\Actions\\Post2::call'),
		'printpage' => array('Printpage.php', 'PrintTopic'),
		'profile' => array('Profile.php', 'ModifyProfile'),
		'quotefast' => array('', 'SMF\\Actions\\QuoteFast::call'),
		'quickmod' => array('MessageIndex.php', 'QuickModeration'),
		'quickmod2' => array('Display.php', 'QuickInTopicModeration'),
		'recent' => array('Recent.php', 'RecentPosts'),
		'reminder' => array('Reminder.php', 'RemindMe'),
		'removepoll' => array('Poll.php', 'RemovePoll'),
		'removetopic2' => array('', 'SMF\\Actions\\TopicRemove::call'),
		'reporttm' => array('ReportToMod.php', 'ReportToModerator'),
		'requestmembers' => array('Subs-Auth.php', 'RequestMembers'),
		'restoretopic' => array('', 'SMF\\Actions\\TopicRestore::call'),
		'search' => array('Search.php', 'PlushSearch1'),
		'search2' => array('Search.php', 'PlushSearch2'),
		'sendactivation' => array('', 'SMF\\Actions\\SendActivation::call'),
		'signup' => array('', 'SMF\\Actions\\Register::call'),
		'signup2' => array('', 'SMF\\Actions\\Register2::call'),
		'smstats' => array('Stats.php', 'SMStats'),
		'suggest' => array('Subs-Editor.php', 'AutoSuggestHandler'),
		'splittopics' => array('SplitTopics.php', 'SplitTopics'),
		'stats' => array('Stats.php', 'DisplayStats'),
		'sticky' => array('', 'SMF\\Topic::sticky'),
		'theme' => array('', 'SMF\\Theme::dispatch'),
		'trackip' => array('Profile-View.php', 'trackIP'),
		'about:unknown' => array('', 'SMF\\Likes::BookOfUnknown'),
		'unread' => array('Recent.php', 'UnreadTopics'),
		'unreadreplies' => array('Recent.php', 'UnreadTopics'),
		'uploadAttach' => array('', 'SMF\\Actions\\AttachmentUpload::call'),
		'verificationcode' => array('', 'SMF\\Actions\\VerificationCode::call'),
		'viewprofile' => array('Profile.php', 'ModifyProfile'),
		'vote' => array('Poll.php', 'Vote'),
		'viewquery' => array('ViewQuery.php', 'ViewQuery'),
		'viewsmfile' => array('Admin.php', 'DisplayAdminFile'),
		'who' => array('', 'SMF\\Actions\\Who::call'),
		'.xml' => array('', 'SMF\\Actions\\Feed::call'),
		'xmlhttp' => array('Xml.php', 'XMLhttpMain'),
	);

	/**
	 * @var array
	 *
	 * This array defines actions, sub-actions, and/or areas where user activity
	 * should not be logged. For example, if the user downloads an attachment
	 * via the dlattach action, that's not something we want to log.
	 *
	 * Array keys are actions. Array values are either:
	 *
	 *  - true, which means the action as a whole should not be logged.
	 *
	 *  - a multidimensional array indicating specific sub-actions or areas that
	 *    should not be logged.
	 *
	 *    For example, 'pm' => array('sa' => array('popup')) means that we won't
	 *    log visits to index.php?action=pm;sa=popup, but other sub-actions
	 *    like index.php?action=pm;sa=send will be logged.
	 */
	public static $unlogged_actions = array(
		'about:unknown' => true,
		'clock' => true,
		'dlattach' => true,
		'findmember' => true,
		'helpadmin' => true,
		'jsoption' => true,
		'likes' => true,
		'modifycat' => true,
		'pm' => array('sa' => array('popup')),
		'profile' => array('area' => array('popup', 'alerts_popup', 'download', 'dlattach')),
		'requestmembers' => true,
		'smstats' => true,
		'suggest' => true,
		'verificationcode' => true,
		'viewquery' => true,
		'viewsmfile' => true,
		'xmlhttp' => true,
		'.xml' => true,
	);

	/**
	 * @var array
	 *
	 * Actions that guests are always allowed to do.
	 * This allows users to log in when guest access is disabled.
	 */
	public static $guest_access_actions = array(
		'coppa',
		'login',
		'login2',
		'logintfa',
		'reminder',
		'activate',
		'help',
		'helpadmin',
		'smstats',
		'verificationcode',
		'signup',
		'signup2',
	);

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// If Config::$maintenance is set specifically to 2, then we're upgrading or something.
		if (!empty(Config::$maintenance) &&  2 === Config::$maintenance)
		{
			display_maintenance_message();
		}

		// Initiate the database connection and define some database functions to use.
		Db::load();

		// Load the settings from the settings table, and perform operations like optimizing.
		Config::reloadModSettings();

		// Clean the request variables, add slashes, etc.
		cleanRequest();

		// Seed the random generator.
		if (empty(Config::$modSettings['rand_seed']) || mt_rand(1, 250) == 69)
			smf_seed_generator();

		// If a Preflight is occurring, lets stop now.
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS')
		{
			send_http_status(204);
			die;
		}

		// Check if compressed output is enabled, supported, and not already being done.
		if (!empty(Config::$modSettings['enableCompressedOutput']) && !headers_sent())
		{
			// If zlib is being used, turn off output compression.
			if (ini_get('zlib.output_compression') >= 1 || ini_get('output_handler') == 'ob_gzhandler')
				Config::$modSettings['enableCompressedOutput'] = '0';

			else
			{
				ob_end_clean();
				ob_start('ob_gzhandler');
			}
		}

		// Register an error handler.
		set_error_handler('smf_error_handler');

		// Start the session. (assuming it hasn't already been.)
		loadSession();

		// Why three different hooks? For historical reasons.
		// Allow modifying $actions easily.
		call_integration_hook('integrate_actions', array(&self::$actions));

		// Allow modifying $unlogged_actions easily.
		call_integration_hook('integrate_pre_log_stats', array(&self::$unlogged_actions));

		// Allow modifying $guest_access_actions easily.
		call_integration_hook('integrate_guest_actions', array(&self::$guest_access_actions));
	}

	/**
	 * This is the one that gets stuff done.
	 *
	 * Internally, this calls $this->main() to find out what function to call,
	 * then calls that function, and then calls obExit() in order to send
	 * results to the browser.
	 */
	public function execute()
	{
		// What function shall we execute? (done like this for memory's sake.)
		call_user_func($this->main());

		// Call obExit specially; we're coming from the main area ;).
		obExit(null, null, true);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The main dispatcher.
	 * This delegates to each area.
	 *
	 * @return array|string|void An array containing the file to include and name of function to call, the name of a function to call or dies with a fatal_lang_error if we couldn't find anything to do.
	 */
	protected function main()
	{
		// Special case: session keep-alive, output a transparent pixel.
		if (isset($_GET['action']) && $_GET['action'] == 'keepalive')
		{
			header('content-type: image/gif');
			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}

		// We should set our security headers now.
		frameOptionsHeader();

		// Set our CORS policy.
		corsPolicyHeader();

		// Load the user's cookie (or set as guest) and load their settings.
		User::load();

		// Load the current board's information.
		Board::load();

		// Load the current user's permissions.
		User::$me->loadPermissions();

		// Attachments don't require the entire theme to be loaded.
		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'dlattach' && empty(Config::$maintenance))
		{
			BrowserDetector::call();
		}
		// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
		else
		{
			Theme::load();
		}

		// Check if the user should be disallowed access.
		is_not_banned();

		// If we are in a topic and don't have permission to approve it then duck out now.
		if (!empty(Topic::$topic_id) && empty(Board::$info->cur_topic_approved) && !allowedTo('approve_posts') && (User::$me->id != Board::$info->cur_topic_starter || User::$me->is_guest))
		{
			fatal_lang_error('not_a_topic', false);
		}

		// Don't log if this is an attachment, avatar, toggle of editor buttons, theme option, XML feed, popup, etc.
		if (!is_filtered_request(self::$unlogged_actions, 'action'))
		{
			// Log this user as online.
			writeLog();

			// Track forum statistics and hits...?
			if (!empty(Config::$modSettings['hitStats']))
				trackStats(array('hits' => '+'));
		}

		// Make sure that our scheduled tasks have been running as intended
		check_cron();

		// Is the forum in maintenance mode? (doesn't apply to administrators.)
		if (!empty(Config::$maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login.... otherwise, you're getting the "maintenance mode" display.
			if (isset($_REQUEST['action']) && (in_array($_REQUEST['action'], array('login2', 'logintfa', 'logout'))))
			{
				require_once(Config::$sourcedir . '/LogInOut.php');
				return ($_REQUEST['action'] == 'login2' ? 'Login2' : ($_REQUEST['action'] == 'logintfa' ? 'LoginTFA' : 'Logout'));
			}
			// Don't even try it, sonny.
			else
				return 'InMaintenance';
		}
		// If guest access is off, a guest can only do one of the very few following actions.
		elseif (empty(Config::$modSettings['allow_guestAccess']) && User::$me->is_guest && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], self::$guest_access_actions)))
		{
			return 'KickGuest';
		}
		elseif (empty($_REQUEST['action']))
		{
			// Action and board are both empty... BoardIndex! Unless someone else wants to do something different.
			if (empty(Board::$info->id) && empty(Topic::$topic_id))
			{
				if (!empty(Config::$modSettings['integrate_default_action']))
				{
					$defaultAction = explode(',', Config::$modSettings['integrate_default_action']);

					// Sorry, only one default action is needed.
					$defaultAction = $defaultAction[0];

					$call = call_helper($defaultAction, true);

					if (!empty($call))
						return $call;
				}

				// No default action huh? then go to our good old BoardIndex.
				else
				{
					return 'SMF\\BoardIndex::call';
				}
			}

			// Topic is empty, and action is empty.... MessageIndex!
			elseif (empty(Topic::$topic_id))
			{
				return 'SMF\\MessageIndex::call';
			}

			// Board is not empty... topic is not empty... action is empty.. Display!
			else
			{
				return 'SMF\\Display::call';
			}
		}

		// Get the function and file to include - if it's not there, do the board index.
		if (!isset($_REQUEST['action']) || !isset(self::$actions[$_REQUEST['action']]))
		{
			// Catch the action with the theme?
			if (!empty(Theme::$current->settings['catch_action']))
			{
				return 'SMF\\Theme::wrapAction';
			}

			if (!empty(Config::$modSettings['integrate_fallback_action']))
			{
				$fallbackAction = explode(',', Config::$modSettings['integrate_fallback_action']);

				// Sorry, only one fallback action is needed.
				$fallbackAction = $fallbackAction[0];

				$call = call_helper($fallbackAction, true);

				if (!empty($call))
					return $call;
			}

			// No fallback action, huh?
			else
			{
				fatal_lang_error('not_found', false, array(), 404);
			}
		}

		// Otherwise, it was set - so let's go to that action.
		if (!empty(self::$actions[$_REQUEST['action']][0]))
			require_once(Config::$sourcedir . '/' . self::$actions[$_REQUEST['action']][0]);

		// Do the right thing.
		return call_helper(self::$actions[$_REQUEST['action']][1], true);
	}
}

?>