<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
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
	public static $actions = [
		'agreement' => ['', 'SMF\\Actions\\Agreement::call'],
		'acceptagreement' => ['', 'SMF\\Actions\\AgreementAccept::call'],
		'activate' => ['', 'SMF\\Actions\\Activate::call'],
		'admin' => ['', 'SMF\\Actions\\Admin\\ACP::call'],
		'announce' => ['', 'SMF\\Actions\\Announce::call'],
		'attachapprove' => ['', 'SMF\\Actions\\AttachmentApprove::call'],
		'buddy' => ['', 'SMF\\Actions\\BuddyListToggle::call'],
		'calendar' => ['', 'SMF\\Actions\\Calendar::call'],
		'clock' => ['', 'SMF\\Actions\\Calendar::call'], // Deprecated; is now a sub-action
		'coppa' => ['', 'SMF\\Actions\\CoppaForm::call'],
		'credits' => ['', 'SMF\\Actions\\Credits::call'],
		'deletemsg' => ['', 'SMF\\Actions\\MsgDelete::call'],
		'dlattach' => ['', 'SMF\\Actions\\AttachmentDownload::call'],
		'editpoll' => ['', 'SMF\\Poll::edit'],
		'editpoll2' => ['', 'SMF\\Poll::edit2'],
		'findmember' => ['', 'SMF\\Actions\\FindMember::call'],
		'groups' => ['', 'SMF\\Actions\\Groups::call'],
		'help' => ['', 'SMF\\Actions\\Help::call'],
		'helpadmin' => ['', 'SMF\\Actions\\HelpAdmin::call'],
		'jsmodify' => ['', 'SMF\\Actions\\JavaScriptModify::call'],
		'jsoption' => ['', 'SMF\\Theme::setJavaScript'],
		'likes' => ['', 'SMF\\Actions\\Like::call'],
		'lock' => ['', 'SMF\\Topic::lock'],
		'lockvoting' => ['', 'SMF\\Poll::lock'],
		'login' => ['', 'SMF\\Actions\\Login::call'],
		'login2' => ['', 'SMF\\Actions\\Login2::call'],
		'logintfa' => ['', 'SMF\\Actions\\LoginTFA::call'],
		'logout' => ['', 'SMF\\Actions\\Logout::call'],
		'markasread' => ['', 'SMF\\Board::MarkRead'],
		'mergetopics' => ['', 'SMF\\Actions\\TopicMerge::call'],
		'mlist' => ['', 'SMF\\Actions\\Memberlist::call'],
		'moderate' => ['', 'SMF\\Actions\\Moderation\\Main::call'],
		'modifycat' => ['', 'SMF\\Actions\\Admin\\Boards::modifyCat'],
		'movetopic' => ['', 'SMF\\Actions\\TopicMove::call'],
		'movetopic2' => ['', 'SMF\\Actions\\TopicMove2::call'],
		'notifyannouncements' => ['', 'SMF\\Actions\\NotifyAnnouncements::call'],
		'notifyboard' => ['', 'SMF\\Actions\\NotifyBoard::call'],
		'notifytopic' => ['', 'SMF\\Actions\\NotifyTopic::call'],
		'pm' => ['', 'SMF\\Actions\\PersonalMessage::call'],
		'post' => ['', 'SMF\\Actions\\Post::call'],
		'post2' => ['', 'SMF\\Actions\\Post2::call'],
		'printpage' => ['', 'SMF\\Actions\\TopicPrint::call'],
		'profile' => ['', 'SMF\\Actions\\Profile\\Main::call'],
		'quotefast' => ['', 'SMF\\Actions\\QuoteFast::call'],
		'quickmod' => ['', 'SMF\\Actions\\QuickModeration::call'],
		'quickmod2' => ['', 'SMF\\Actions\\QuickModerationInTopic::call'],
		'recent' => ['', 'SMF\\Actions\\Recent::call'],
		'reminder' => ['', 'SMF\\Actions\\Reminder::call'],
		'removepoll' => ['', 'SMF\\Poll::remove'],
		'removetopic2' => ['', 'SMF\\Actions\\TopicRemove::call'],
		'reporttm' => ['', 'SMF\\Actions\\ReportToMod::call'],
		'requestmembers' => ['', 'SMF\\Actions\\RequestMembers::call'],
		'restoretopic' => ['', 'SMF\\Actions\\TopicRestore::call'],
		'search' => ['', 'SMF\\Actions\\Search::call'],
		'search2' => ['', 'SMF\\Actions\\Search2::call'],
		'sendactivation' => ['', 'SMF\\Actions\\SendActivation::call'],
		'signup' => ['', 'SMF\\Actions\\Register::call'],
		'signup2' => ['', 'SMF\\Actions\\Register2::call'],
		'smstats' => ['', 'SMF\\Actions\\SmStats::call'],
		'suggest' => ['', 'SMF\\Actions\\AutoSuggest::call'],
		'splittopics' => ['', 'SMF\\Actions\\TopicSplit::call'],
		'stats' => ['', 'SMF\\Actions\\Stats::call'],
		'sticky' => ['', 'SMF\\Topic::sticky'],
		'theme' => ['', 'SMF\\Theme::dispatch'],
		'trackip' => ['', 'SMF\\Actions\\TrackIP::call'],
		'about:unknown' => ['', 'SMF\\Actions\\Like::BookOfUnknown'],
		'unread' => ['', 'SMF\\Actions\\Unread::call'],
		'unreadreplies' => ['', 'SMF\\Actions\\UnreadReplies::call'],
		'uploadAttach' => ['', 'SMF\\Actions\\AttachmentUpload::call'],
		'verificationcode' => ['', 'SMF\\Actions\\VerificationCode::call'],
		'viewprofile' => ['', 'SMF\\Actions\\Profile\\Main::call'],
		'vote' => ['', 'SMF\\Poll::vote'],
		'viewquery' => ['', 'SMF\\Actions\\ViewQuery::call'],
		'viewsmfile' => ['', 'SMF\\Actions\\DisplayAdminFile::call'],
		'who' => ['', 'SMF\\Actions\\Who::call'],
		'.xml' => ['', 'SMF\\Actions\\Feed::call'],
		'xmlhttp' => ['', 'SMF\\Actions\\XmlHttp::call'],
	];

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
	public static $unlogged_actions = [
		'about:unknown' => true,
		'clock' => true,
		'dlattach' => true,
		'findmember' => true,
		'helpadmin' => true,
		'jsoption' => true,
		'likes' => true,
		'modifycat' => true,
		'pm' => ['sa' => ['popup']],
		'profile' => ['area' => ['popup', 'alerts_popup', 'download', 'dlattach']],
		'requestmembers' => true,
		'smstats' => true,
		'suggest' => true,
		'verificationcode' => true,
		'viewquery' => true,
		'viewsmfile' => true,
		'xmlhttp' => true,
		'.xml' => true,
	];

	/**
	 * @var array
	 *
	 * Actions that guests are always allowed to do.
	 * This allows users to log in when guest access is disabled.
	 */
	public static $guest_access_actions = [
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
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// If Config::$maintenance is set specifically to 2, then we're upgrading or something.
		if (!empty(Config::$maintenance) &&  2 === Config::$maintenance) {
			ErrorHandler::displayMaintenanceMessage();
		}

		// Initiate the database connection and define some database functions to use.
		Db::load();

		// Load the settings from the settings table, and perform operations like optimizing.
		Config::reloadModSettings();

		// Clean the request variables, add slashes, etc.
		QueryString::cleanRequest();

		// Seed the random generator.
		if (empty(Config::$modSettings['rand_seed']) || mt_rand(1, 250) == 69) {
			Config::generateSeed();
		}

		// If a Preflight is occurring, lets stop now.
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			Utils::sendHttpStatus(204);

			die;
		}

		// Check if compressed output is enabled, supported, and not already being done.
		if (!empty(Config::$modSettings['enableCompressedOutput']) && !headers_sent()) {
			// If zlib is being used, turn off output compression.
			if (ini_get('zlib.output_compression') >= 1 || ini_get('output_handler') == 'ob_gzhandler') {
				Config::$modSettings['enableCompressedOutput'] = '0';
			} else {
				ob_end_clean();
				ob_start('ob_gzhandler');
			}
		}

		// Register an error handler.
		set_error_handler(__NAMESPACE__ . '\\ErrorHandler::call');

		// Start the session. (assuming it hasn't already been.)
		Session::load();

		// Why three different hooks? For historical reasons.
		// Allow modifying $actions easily.
		IntegrationHook::call('integrate_actions', [&self::$actions]);

		// Allow modifying $unlogged_actions easily.
		IntegrationHook::call('integrate_pre_log_stats', [&self::$unlogged_actions]);

		// Allow modifying $guest_access_actions easily.
		IntegrationHook::call('integrate_guest_actions', [&self::$guest_access_actions]);
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
		Utils::obExit(null, null, true);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Display a message about the forum being in maintenance mode.
	 * - display a login screen with sub template 'maintenance'.
	 * - sends a 503 header, so search engines don't bother indexing while we're in maintenance mode.
	 */
	public static function inMaintenance()
	{
		Lang::load('Login');
		Theme::loadTemplate('Login');
		SecurityToken::create('login');

		// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
		Utils::sendHttpStatus(503, 'Service Temporarily Unavailable');

		// Basic template stuff..
		Utils::$context['sub_template'] = 'maintenance';
		Utils::$context['title'] = Utils::htmlspecialchars(Config::$mtitle);
		Utils::$context['description'] = &Config::$mmessage;
		Utils::$context['page_title'] = Lang::$txt['maintain_mode'];
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
		if (isset($_GET['action']) && $_GET['action'] == 'keepalive') {
			header('content-type: image/gif');

			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}

		// We should set our security headers now.
		Security::frameOptionsHeader();

		// Set our CORS policy.
		Security::corsPolicyHeader();

		// Load the user's cookie (or set as guest) and load their settings.
		User::load();

		// Load the current board's information.
		Board::load();

		// Load the current user's permissions.
		User::$me->loadPermissions();

		// Attachments don't require the entire theme to be loaded.
		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'dlattach' && empty(Config::$maintenance)) {
			BrowserDetector::call();
		}
		// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
		else {
			Theme::load();
		}

		// Check if the user should be disallowed access.
		User::$me->kickIfBanned();

		// If we are in a topic and don't have permission to approve it then duck out now.
		if (!empty(Topic::$topic_id) && empty(Board::$info->cur_topic_approved) && !User::$me->allowedTo('approve_posts') && (User::$me->id != Board::$info->cur_topic_starter || User::$me->is_guest)) {
			ErrorHandler::fatalLang('not_a_topic', false);
		}

		// Don't log if this is an attachment, avatar, toggle of editor buttons, theme option, XML feed, popup, etc.
		if (!QueryString::isFilteredRequest(self::$unlogged_actions, 'action')) {
			// Log this user as online.
			User::$me->logOnline();

			// Track forum statistics and hits...?
			if (!empty(Config::$modSettings['hitStats'])) {
				Logging::trackStats(['hits' => '+']);
			}
		}

		// Make sure that our scheduled tasks have been running as intended
		Config::checkCron();

		// Is the forum in maintenance mode? (doesn't apply to administrators.)
		if (!empty(Config::$maintenance) && !User::$me->allowedTo('admin_forum')) {
			// You can only login.... otherwise, you're getting the "maintenance mode" display.
			if (isset($_REQUEST['action']) && (in_array($_REQUEST['action'], ['login2', 'logintfa', 'logout']))) {
				return self::$actions[$_REQUEST['action']][1];
			}

			// Don't even try it, sonny.
			return __CLASS__ . '::inMaintenance';
		}

		// If guest access is off, a guest can only do one of the very few following actions.
		if (empty(Config::$modSettings['allow_guestAccess']) && User::$me->is_guest && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], self::$guest_access_actions))) {
			User::$me->kickIfGuest(null, false);
		} elseif (empty($_REQUEST['action'])) {
			// Action and board are both empty... BoardIndex! Unless someone else wants to do something different.
			if (empty(Board::$info->id) && empty(Topic::$topic_id)) {
				if (!empty(Config::$modSettings['integrate_default_action'])) {
					$defaultAction = explode(',', Config::$modSettings['integrate_default_action']);

					// Sorry, only one default action is needed.
					$defaultAction = $defaultAction[0];

					$call = Utils::getCallable($defaultAction);

					if (!empty($call)) {
						return $call;
					}
				}

				// No default action huh? then go to our good old BoardIndex.
				else {
					return 'SMF\\Actions\\BoardIndex::call';
				}
			}

			// Topic is empty, and action is empty.... MessageIndex!
			elseif (empty(Topic::$topic_id)) {
				return 'SMF\\Actions\\MessageIndex::call';
			}

			// Board is not empty... topic is not empty... action is empty.. Display!
			else {
				return 'SMF\\Actions\\Display::call';
			}
		}

		// Get the function and file to include - if it's not there, do the board index.
		if (!isset($_REQUEST['action']) || !isset(self::$actions[$_REQUEST['action']])) {
			// Catch the action with the theme?
			if (!empty(Theme::$current->settings['catch_action'])) {
				return 'SMF\\Theme::wrapAction';
			}

			if (!empty(Config::$modSettings['integrate_fallback_action'])) {
				$fallbackAction = explode(',', Config::$modSettings['integrate_fallback_action']);

				// Sorry, only one fallback action is needed.
				$fallbackAction = $fallbackAction[0];

				$call = Utils::getCallable($fallbackAction);

				if (!empty($call)) {
					return $call;
				}
			}

			// No fallback action, huh?
			else {
				ErrorHandler::fatalLang('not_found', false, [], 404);
			}
		}

		// Otherwise, it was set - so let's go to that action.
		if (!empty(self::$actions[$_REQUEST['action']][0])) {
			require_once Config::$sourcedir . '/' . self::$actions[$_REQUEST['action']][0];
		}

		// Do the right thing.
		return Utils::getCallable(self::$actions[$_REQUEST['action']][1]);
	}
}

?>