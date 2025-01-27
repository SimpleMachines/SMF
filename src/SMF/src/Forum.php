<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

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
 *
 * MOD AUTHORS:
 *
 * To add a new action, do the following:
 *
 *  1. Create a class that implements SMF\ActionInterface and uses SMF\ActionTrait.
 *     Put your code in its execute() method.
 *  2. Either use the integrate_actions hook or call SMF\Forum:::addAction() from
 *     the integrate_pre_load hook to add information about your
 *     action to SMF\Forum::$actions.
 *
 * Deprecations:
 *
 *  1. integrate_pre_log_stats (modifying SMF\Forum::$unlogged_actions)
 *     Implement SMF\ActionInterface::canBeLogged() to manage logging at the action level.
 *
 *  2. integrate_guest_actions (modifying SMF\Forum::$guest_access_actions)
 *     Implement SMF\ActionInterface::isRestrictedGuestAccessAllowed() for guest access control.
 */
class Forum
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * This array defines what file to load and what to call for each possible
	 * value of $_REQUEST['action'].
	 *
	 * Keys are action names as found in $_REQUEST['action'].
	 *
	 * Values are arrays containing two elements:
	 *  - The relative path of a file to load. When calling an autoloading
	 *    class, the file can be left empty.
	 *  - A callable or a class that implements SMF\ActionInterface.
	 *
	 * Mod authors can add new actions to this via the integrate_actions hook.
	 */
	public static array $actions = [
		'agreement' => [
			'', Actions\Agreement::class,
		],
		'acceptagreement' => [
			'', Actions\AgreementAccept::class,
		],
		'activate' => [
			'', Actions\Activate::class,
		],
		'admin' => [
			'', Actions\Admin\ACP::class,
		],
		'announce' => [
			'', Actions\Announce::class,
		],
		'attachapprove' => [
			'', Actions\AttachmentApprove::class,
		],
		'boardindex' => [
			'', Actions\BoardIndex::class,
		],
		'buddy' => [
			'', Actions\BuddyListToggle::class,
		],
		'calendar' => [
			'', Actions\Calendar::class,
		],
		// Deprecated; is now a sub-action
		'clock' => [
			'', Actions\Calendar::class,
		],
		'coppa' => [
			'', Actions\CoppaForm::class,
		],
		'credits' => [
			'', Actions\Credits::class,
		],
		'deletemsg' => [
			'', Actions\MsgDelete::class,
		],
		'display' => [
			'', Actions\Display::class,
		],
		'dlattach' => [
			'', Actions\AttachmentDownload::class,
		],
		'editpoll' => [
			'', Actions\PollEdit::class,
		],
		'editpoll2' => [
			'', Actions\PollEdit2::class,
		],
		'groups' => [
			'', Actions\Groups::class,
		],
		'help' => [
			'', Actions\Help::class,
		],
		'helpadmin' => [
			'', Actions\HelpAdmin::class,
		],
		'jsmodify' => [
			'', Actions\JavaScriptModify::class,
		],
		'jsoption' => [
			'', Actions\ThemeSetOption::class,
		],
		'likes' => [
			'', Actions\Like::class,
		],
		'lock' => [
			'', Actions\TopicLock::class,
		],
		'lockvoting' => [
			'', Actions\PollLock::class,
		],
		'login' => [
			'', Actions\Login::class,
		],
		'login2' => [
			'', Actions\Login2::class,
		],
		'logintfa' => [
			'', Actions\LoginTFA::class,
		],
		'logout' => [
			'', Actions\Logout::class,
		],
		'markasread' => [
			'', Actions\MarkRead::class,
		],
		'mergetopics' => [
			'', Actions\TopicMerge::class,
		],
		'messageindex' => [
			'', Actions\MessageIndex::class,
		],
		'mlist' => [
			'', Actions\Memberlist::class,
		],
		'moderate' => [
			'', Actions\Moderation\Main::class,
		],
		// Deprecated; is now a sub-action
		'modifycat' => [
			'', Actions\Admin\Boards::class,
		],
		'movetopic' => [
			'', Actions\TopicMove::class,
		],
		'movetopic2' => [
			'', Actions\TopicMove2::class,
		],
		'notifyannouncements' => [
			'', Actions\NotifyAnnouncements::class,
		],
		'notifyboard' => [
			'', Actions\NotifyBoard::class,
		],
		'notifytopic' => [
			'', Actions\NotifyTopic::class,
		],
		'pm' => [
			'', Actions\PersonalMessage::class,
		],
		'post' => [
			'', Actions\Post::class,
		],
		'post2' => [
			'', Actions\Post2::class,
		],
		'printpage' => [
			'', Actions\TopicPrint::class,
		],
		'profile' => [
			'', Actions\Profile\Main::class,
		],
		'quotefast' => [
			'', Actions\QuoteFast::class,
		],
		'quickmod' => [
			'', Actions\QuickModeration::class,
		],
		'quickmod2' => [
			'', Actions\QuickModerationInTopic::class,
		],
		'recent' => [
			'', Actions\Recent::class,
		],
		'reminder' => [
			'', Actions\Reminder::class,
		],
		'removepoll' => [
			'', Actions\PollRemove::class,
		],
		'removetopic2' => [
			'', Actions\TopicRemove::class,
		],
		'reporttm' => [
			'', Actions\ReportToMod::class,
		],
		'requestmembers' => [
			'', Actions\RequestMembers::class,
		],
		'restoretopic' => [
			'', Actions\TopicRestore::class,
		],
		'search' => [
			'', Actions\Search::class,
		],
		'search2' => [
			'', Actions\Search2::class,
		],
		'sendactivation' => [
			'', Actions\SendActivation::class,
		],
		'signup' => [
			'', Actions\Register::class,
		],
		'signup2' => [
			'', Actions\Register2::class,
		],
		'smstats' => [
			'', Actions\SmStats::class,
		],
		'suggest' => [
			'', Actions\AutoSuggest::class,
		],
		'splittopics' => [
			'', Actions\TopicSplit::class,
		],
		'stats' => [
			'', Actions\Stats::class,
		],
		'sticky' => [
			'', Actions\TopicSticky::class,
		],
		// Deprecated; will be redirected to the correct location.
		'theme' => [
			'', Actions\Admin\Themes::class,
		],
		'themechooser' => [
			'', Actions\ThemeChooser::class,
		],
		'trackip' => [
			'', Actions\TrackIP::class,
		],
		'about:unknown' => [
			'', Actions\Unknown::class,
		],
		'unread' => [
			'', Actions\Unread::class,
		],
		'unreadreplies' => [
			'', Actions\UnreadReplies::class,
		],
		'uploadAttach' => [
			'', Actions\AttachmentUpload::class,
		],
		'verificationcode' => [
			'', Actions\VerificationCode::class,
		],
		'viewprofile' => [
			'', Actions\Profile\Main::class,
		],
		'vote' => [
			'', Actions\PollVote::class,
		],
		'viewquery' => [
			'', Actions\ViewQuery::class,
		],
		'viewsmfile' => [
			'', Actions\DisplayAdminFile::class,
		],
		'who' => [
			'', Actions\Who::class,
		],
		'.xml' => [
			'', Actions\Feed::class,
		],
		'xmlhttp' => [
			'', Actions\XmlHttp::class,
		],
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
	public static array $unlogged_actions = [
		'calendar' => ['sa' => ['clock']],
		'clock' => true,
		'modifycat' => true,
	];

	/**
	 * @var array
	 *
	 * Actions that guests are always allowed to do.
	 * This allows users to log in when guest access is disabled.
	 */
	public static array $guest_access_actions = [];

	/**
	 * @var ActionInterface|null
	 *
	 * Stores the current action.
	 */
	protected static ?ActionInterface $current_action = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor
	 *
	 * Initializes the forum by setting up database connections, loading settings,
	 * and handling maintenance mode.
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
			// @TODO: Calls a deprecated function.
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
		set_error_handler([ErrorHandler::class, 'call']);

		// Start the session. (assuming it hasn't already been.)
		Session::load();

		// Why three different hooks? For historical reasons.
		// Allow modifying $actions easily.
		IntegrationHook::call('integrate_actions', [&self::$actions]);

		// Allow modifying $unlogged_actions easily.
		// Deprecated: Implement ActionInterface::isSimpleAction() instead of this hook.
		IntegrationHook::call('integrate_pre_log_stats', [&self::$unlogged_actions]);

		// Allow modifying $guest_access_actions easily.
		// Deprecated: Implement ActionInterface::isRestrictedGuestAccessAllowed() instead of this hook.
		IntegrationHook::call('integrate_guest_actions', [&self::$guest_access_actions]);
	}

	/**
	 * Executes the main forum action.
	 *
	 * This method serves as the main dispatcher for determining the appropriate action
	 * in various scenarios, ensuring proper handling of the following cases:
	 *
	 * - **Maintenance Mode**: If the forum is in maintenance mode, only login and logout actions
	 *   are allowed for non-administrators. All other actions are redirected to a maintenance page.
	 *
	 * - **Guest Access Restrictions**: If guest access is disabled, guests are redirected to the login page
	 *   unless the requested action explicitly allows guest access.
	 *
	 * - **Default Actions**: When no specific action is requested, a default or fallback action is determined:
	 *   - If both the board and topic are empty, the default action (e.g., BoardIndex) is executed.
	 *   - If only the topic is empty, the MessageIndex action is executed.
	 *   - Otherwise, the Display action is executed.
	 *
	 * - **Custom Actions**: Resolves user-requested actions using the defined `$actions` array or
	 *   fallback logic, including support for theme-level catch actions or configured fallback actions.
	 */
	public function execute(): void
	{
		$this->init();
		$requested_action = $_REQUEST['action'] ?? null;
		$current_action = $this->findAction($requested_action);

		if (is_a($current_action, ActionInterface::class, true)) {
			$action = call_user_func([$current_action, 'load']);
			self::$current_action = $action;

			// Perform operations on the action object before execute() is called.
			IntegrationHook::call('integrate_init_action', [$action]);
		}

		$this->main();

		// Is the forum in maintenance mode? (doesn't apply to administrators.)
		if (
			!empty(Config::$maintenance)
			&& !User::$me->allowedTo('admin_forum')
			&& self::$current_action?->canShowInMaintenanceMode() === false
		) {
			// Don't even try it, sonny.
			self::inMaintenance();
		}

		// If guest access is off, a guest can only do one of a few actions.
		if (
			empty(Config::$modSettings['allow_guestAccess'])
			&& User::$me->is_guest
			&& (
				self::$current_action?->isRestrictedGuestAccessAllowed() === false
				|| (
					!isset($_REQUEST['action'])
					|| !in_array($_REQUEST['action'] ?? '', self::$guest_access_actions)
					&& self::$current_action?->isRestrictedGuestAccessAllowed() === null
				)
			)
		) {
			User::$me->kickIfGuest(null, false);
		}

		if (isset($action)) {
			$action->execute();
		} elseif (is_callable($current_action)) {
			call_user_func($current_action);
		} else {
			ErrorHandler::fatalLang('not_found', false, [], 404);
		}

		// Call obExit specially; we're coming from the main area ;).
		Utils::obExit(null, null, true);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Adds a new action to the $actions array.
	 *
	 * This method allows you to add a new action to the forum's action
	 * array, which maps URL actions to their corresponding handlers.
	 *
	 * @param string $action The action name as it appears in the URL (e.g., 'newaction').
	 * @param string $file The file that contains the action's handler class. If using an autoloading class, this can be an empty string.
	 * @param string|callable $handler The class name that implements ActionInterface or a callable handler.
	 */
	public static function addAction(string $action, string $file, string|callable $handler): void
	{
		self::$actions[$action] = [$file, $handler];
	}

	/**
	 * Removes an action from the $actions array.
	 *
	 * This method allows you to remove an action from the forum's
	 * action array, effectively disabling that action.
	 *
	 * @param string $action The action name to remove (e.g., 'oldaction').
	 */
	public static function removeAction(string $action): void
	{
		unset(self::$actions[$action]);
	}

	/**
	 * Get the current action.
	 *
	 * @return ActionInterface|null The current action or null if not set.
	 */
	public static function getCurrentAction(): ?ActionInterface
	{
		return self::$current_action;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Display a message about the forum being in maintenance mode.
	 * - Display a login screen with sub template 'maintenance'.
	 * - Sends a 503 header, so search engines don't bother indexing while we're in maintenance mode.
	 */
	protected static function inMaintenance(): void
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
	 * The main forum loader.
	 *
	 * This method initializes various components and settings required
	 * for the forum to operate, such as security headers, user permissions,
	 * and theme loading.
	 */
	protected function init(): void
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
	}

	/**
	 * The main forum loader.
	 *
	 * This method handles the main forum logic, such as checking permissions,
	 * logging user activity, and tracking forum statistics.
	 */
	protected function main(): void
	{
		// If we are in a topic and don't have permission to approve it then duck out now.
		if (!empty(Topic::$topic_id) && empty(Board::$info->cur_topic_approved) && !User::$me->allowedTo('approve_posts') && (User::$me->id != Board::$info->cur_topic_starter || User::$me->is_guest)) {
			ErrorHandler::fatalLang('not_a_topic', false);
		}

		// Don't log if this is an attachment, avatar, toggle of editor buttons, theme option, XML feed, popup, etc.
		if (self::$current_action?->canBeLogged() === true || (self::$current_action === null && !QueryString::isFilteredRequest(self::$unlogged_actions, 'action'))) {
			// Log this user as online.
			User::$me->logOnline();

			// Track forum statistics and hits...?
			if (!empty(Config::$modSettings['hitStats'])) {
				Logging::trackStats(['hits' => '+']);
			}
		}

		// Make sure that our scheduled tasks have been running as intended.
		Config::checkCron();
	}

	/**
	 * Resolves the appropriate action to execute based on the current request context.
	 *
	 * @return string|callable|false Returns one of the following:
	 *  - A string representing a class implementing ActionInterface.
	 *  - A callable string representing a static method (e.g., `'Class::method'`).
	 */
	protected function findAction(?string $action): string|callable|false
	{
		// If no action was supplied, is there an implied action?
		if (empty($action)) {
			// Action and board are both empty... BoardIndex!
			if (empty(Board::$info->id) && empty(Topic::$topic_id)) {
				// ... unless someone else wants to do something different.
				if (!empty(Config::$modSettings['integrate_default_action'])) {
					$default_action = explode(',', Config::$modSettings['integrate_default_action'])[0];

					return is_a($default_action, ActionInterface::class, true) ? $default_action : Utils::getCallable($default_action);
				}

				$action = 'boardindex';
			}
			// Topic is empty, and action is empty.... MessageIndex!
			elseif (empty(Topic::$topic_id)) {
				$action = 'messageindex';
			}
			// Board is not empty... topic is not empty... action is empty... Display!
			else {
				$action = 'display';
			}
		}

		// Still no valid action?
		if (!isset(self::$actions[$action])) {
			// Catch the action with the theme?
			if (!empty(Theme::$current->settings['catch_action'])) {
				return [Theme::class, 'wrapAction'];
			}

			// Do we have a last-ditch fallback action?
			if (!empty(Config::$modSettings['integrate_fallback_action'])) {
				$fallback_action = explode(',', Config::$modSettings['integrate_fallback_action'])[0];

				if (is_a($fallback_action, ActionInterface::class, true)) {
					return $fallback_action;
				}

				if (($fallback_action = Utils::getCallable($fallback_action)) !== false) {
					return $fallback_action;
				}

				ErrorHandler::fatalLang('not_found', false, [], 404);
			}
		}

		// Otherwise, it was set - so let's go to that action.
		if (!empty(self::$actions[$action][0])) {
			require_once Config::$sourcedir . '/' . self::$actions[$action][0];
		}

		// Do the right thing.
		return self::$actions[$action][1];
	}
}

?>