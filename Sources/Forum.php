<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
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
 *    'action-in-url' => ['Source-File.php', 'FunctionToCall'],
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
 *  2. Either use the integrate_actions hook or call SMF\Forum:::addAction()
 *     from the integrate_pre_load hook to add information about your action to
 *     SMF\Forum::$actions.
 *
 * Deprecations:
 *
 *  1. integrate_pre_log_stats (modifying SMF\Forum::$unlogged_actions)
 *     Implement SMF\ActionInterface::canBeLogged() to manage logging at the
 *     action level.
 *
 *  2. integrate_guest_actions (modifying SMF\Forum::$guest_access_actions)
 *     Implement SMF\ActionInterface::isRestrictedGuestAccessAllowed() for guest
 *     access control.
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
		'authext' => [
			'', Actions\AuthExternal::class,
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
		'edithistory' => [
			'', Actions\ShowMsgHistory::class,
		],
		'editpoll' => [
			'', Actions\PollEdit::class,
		],
		'editpoll2' => [
			'', Actions\PollEdit2::class,
		],
		'feed' => [
			'', Actions\Feed::class,
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
		'passkey' => [
			'', Actions\Passkey::class,
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
		'xmlhttp' => [
			'', Actions\XmlHttp::class,
		],
	];

	/**
	 * @var array
	 *
	 * Actions that had different names in old versions of SMF.
	 *
	 * Keys are the old action names. Values are the new action names.
	 */
	public static array $renamed_actions = [
		'.xml' => 'feed',
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
	 *    For example, `'pm' => ['sa' => ['popup']]` means that we won't log
	 *    visits to `index.php?action=pm;sa=popup`, but other sub-actions like
	 *    `index.php?action=pm;sa=send` will be logged.
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

	/****************************
	 * Internal static properties
	 ****************************/

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
		// If a Preflight is occurring, lets stop now.
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			Utils::sendHttpStatus(204);

			die();
		}

		// Ensure any renamed actions will still work using the old name.
		foreach (self::$renamed_actions as $old => $new) {
			self::$actions[$old] = self::$actions[$new];
		}

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

		// Check if compressed output is enabled, supported, and not already being done.
		if (!empty(Config::$modSettings['enableCompressedOutput']) && !headers_sent()) {
			// If zlib is being used, turn off output compression.
			if (\ini_get('zlib.output_compression') >= 1 || \ini_get('output_handler') == 'ob_gzhandler') {
				Config::$modSettings['enableCompressedOutput'] = '0';
			} else {
				ob_end_clean();
				ob_start('ob_gzhandler');
			}
		}

		// Register an error handler and an exception handler.
		set_error_handler([ErrorHandler::class, 'call']);
		set_exception_handler([ErrorHandler::class, 'catch']);

		// Start the session. (assuming it hasn't already been.)
		Session::load();

		// Why three different hooks? For historical reasons.
		// Allow modifying $actions easily.
		IntegrationHook::call('integrate_actions', [&self::$actions]);

		// Allow modifying $unlogged_actions easily.
		// Deprecated: Implement ActionInterface::isSimpleAction() instead of this hook.
		if (!empty(Config::$backward_compatibility)) {
			IntegrationHook::call('integrate_pre_log_stats', [&self::$unlogged_actions]);
		}

		// Allow modifying $guest_access_actions easily.
		// Deprecated: Implement ActionInterface::isRestrictedGuestAccessAllowed() instead of this hook.
		if (!empty(Config::$backward_compatibility)) {
			IntegrationHook::call('integrate_guest_actions', [&self::$guest_access_actions]);
		}
	}

	/**
	 * Executes the main forum action.
	 */
	public function execute(): void
	{
		$this->init();

		self::getCurrentAction();

		// Perform operations on the action object before its execute() is called.
		if (isset(self::$current_action)) {
			IntegrationHook::call('integrate_init_action', [self::$current_action]);
		}

		$this->preflight();

		if (isset(self::$current_action)) {
			self::$current_action->execute();
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
	 * @param string $action The action name as it appears in the URL.
	 *    For example, if the URL query string is `?action=newaction`, this must
	 *    be `newaction`.
	 * @param string $file The file that contains the action's handler class.
	 *    If using an autoloading class, this can be an empty string.
	 * @param string|callable $handler Either the name of a class that
	 *    implements ActionInterface or else a callable handler.
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
		if (!isset(self::$current_action)) {
			$current_action = self::findAction($_REQUEST['action'] ?? null);

			if (is_a($current_action, ActionInterface::class, true)) {
				self::$current_action = \call_user_func([$current_action, 'load']);
			} elseif (\is_callable($current_action)) {
				self::$current_action = Actions\GenericAction::load();
				self::$current_action->setCallable($current_action);
			}
		}

		return self::$current_action;
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
		User::loadMe();

		// Load the current board's information.
		Board::load();

		// Load the current user's permissions.
		User::$me->loadPermissions();

		// Analyze the user agent string.
		BrowserDetector::call();

		// Load the current theme.
		// Note that ?theme=1 will also work. This can used for guest theming.
		// Attachments don't require the entire theme to be loaded.
		if (($_REQUEST['action'] ?? '') !== 'dlattach' || !empty(Config::$maintenance)) {
			Theme::load();
		}

		// Check if the user should be disallowed access.
		User::$me->enforceBans();
	}

	/**
	 * Runs various checks that are required before calling the action.
	 *
	 * - Registration agreement or privacy policy has changed:
	 *   Calls $this->requireAgreement() in order to check whether we need to
	 *   redirect to a page where the user can see and accept the latest version
	 *   of these documents.
	 *
	 * - Trying to view an unapproved topic:
	 *   Unless user has permission to see the unapproved topic, die with error.
	 *
	 * - Logging and cookie refresh:
	 *   Logs the user's current action, unless the action is not loggable.
	 *   If the action is loggable, also refreshes the user's login cookie.
	 *
	 * - Cron job status:
	 *   Calls Config::checkCron() to check whether cron jobs are working.
	 *
	 * - Maintenance mode:
	 *   If the forum is in maintenance mode, only login and logout actions are
	 *   allowed for non-administrators. All other actions are redirected to a
	 *   maintenance page.
	 *
	 * - Guest access restrictions:
	 *   If guest access is disabled, guests are redirected to the login page
	 *   unless the requested action explicitly allows guest access.
	 */
	protected function preflight(): void
	{
		// If the user needs to accept the agreement or privacy policy, redirect now.
		$this->requireAgreement();

		// If we are in a topic and don't have permission to approve it then duck out now.
		if (
			!empty(Topic::$topic_id)
			&& empty(Board::$info->cur_topic_approved)
			&& !User::$me->allowedTo('approve_posts')
			&& (
				User::$me->id != Board::$info->cur_topic_starter
				|| User::$me->is_guest
			)
		) {
			ErrorHandler::fatalLang('not_a_topic', false);
		}

		// Make sure that our scheduled tasks have been running as intended.
		Config::checkCron();

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
				self::$current_action?->isRestrictedGuestAccessAllowed() !== true
				&& (
					!isset($_REQUEST['action'])
					|| !\in_array($_REQUEST['action'], self::$guest_access_actions)
				)
			)
		) {
			User::$me->kickIfGuest(null, false);
		}

		// Don't log if this is an attachment, avatar, toggle of editor buttons,
		// theme option, XML feed, popup, etc.
		if (
			self::$current_action?->canBeLogged() === true
			|| (
				self::$current_action === null
				&& !QueryString::isFilteredRequest(self::$unlogged_actions, 'action')
			)
		) {
			// Log this user as online.
			User::$me->logOnline();

			// Track forum statistics and hits...?
			if (!empty(Config::$modSettings['hitStats'])) {
				Logging::trackStats(['hits' => '+']);
			}

			// Login cookies should only expire after a period of inactivity.
			// Since doing something worthy of logging means this member is
			// actively engaged with the forum right now, refresh their login
			// cookie in order to reset the countdown to its expiry date.
			if (!User::$me->is_guest) {
				Cookie::updateLoginCookieExpiry();
			}
		}
	}

	/**
	 * If necessary, redirect to the agreement or privacy policy so that we can
	 * force the user to accept the current version.
	 */
	protected function requireAgreement(): void
	{
		// Perhaps we've changed the agreement or privacy policy?
		// Only redirect if all of the following conditions are met:
		if (
			// They're not a guest.
			!empty(User::$me->id)
			// They're not an admin.
			&& empty(User::$me->is_admin)
			// This isn't a called from SSI.
			&& SMF != 'SSI'
			// This isn't an XML request.
			&& !isset($_REQUEST['xml'])
			// They're trying to do an action that requires accepting the agreement and/or policy.
			&& self::$current_action?->isAgreementAction() !== true
			&& (
				// They haven't accepted the latest version of the agreement.
				Actions\Agreement::canRequireAgreement()
				// Or they haven't accepted the latest version of the privacy policy.
				|| Actions\Agreement::canRequirePrivacyPolicy()
			)
		) {
			Utils::redirectexit('action=agreement');
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Display a message about the forum being in maintenance mode.
	 *
	 * - Display a login screen with sub template 'maintenance'.
	 * - Sends a 503 header, so search engines don't bother indexing while we're
	 *   in maintenance mode.
	 */
	protected static function inMaintenance(): void
	{
		Theme::loadTemplate('Login');

		if (empty($_COOKIE)) {
			Cookie::setLoginCookie(Cookie::LENGTH_DEFAULT, 0, '');
		}
		SecurityToken::create('login');

		// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
		Utils::sendHttpStatus(503, 'Service Temporarily Unavailable');

		// Basic template stuff..
		Utils::$context['sub_template'] = 'maintenance';
		Utils::$context['title'] = Utils::htmlspecialchars(Config::$mtitle);
		Utils::$context['description'] = &Config::$mmessage;
		Utils::$context['page_title'] = Lang::getTxt('maintain_mode', file: 'Login');

		// And that is as far as this request goes. 2.1 made this the action, so
		// nothing else ever ran; here it is a step before one, and returning
		// hands the request straight back to the action we just decided must
		// not happen. Every side effect of it still happens, and whatever it
		// puts in 'sub_template' or 'sub_templates' takes the page back.
		Utils::obExit();

		// We should never reach this point, but just in case...
		die('No direct access...');
	}

	/**
	 * Resolves the appropriate action to execute based on the current request
	 * context.
	 *
	 * - Default Actions:
	 *   When no specific action is requested, a default or fallback action is
	 *   determined:
	 *   - If the topic is not empty, the Display action is executed.
	 *   - If the topic is empty but the board is not, the MessageIndex action
	 *     is executed.
	 *   - If both the topic and board are empty, the default action (usually
	 *     BoardIndex, unless a mod has changed it) is executed.
	 *
	 * - Requested Actions:
	 *   Resolves user-requested actions using the Forum::$actions array or
	 *   fallback logic, including support for theme-level catch actions or
	 *   configured fallback actions.
	 *
	 * @return string|callable|false Returns one of the following:
	 *  - The name of a class that implements ActionInterface.
	 *  - A callable that can be passed to SMF\GenericAction::setCallable().
	 *  - false if no valid class or callable was found.
	 */
	protected static function findAction(?string $action): string|callable|false
	{
		// If no action was supplied, is there an implied action?
		if (empty($action)) {
			// We check $_GET['topic'] and $_GET['board'] because the implied
			// action depends on what was in the URL, not on whatever values we
			// calculated for Board::$info->id and Topic::$info->id. This means
			// that if $_GET['topic'] or $_GET['board'] are set to something
			// invalid, an error will be shown. This is the correct and intended
			// behaviour.
			if (isset($_GET['topic'])) {
				$action = 'display';
			} elseif (isset($_GET['board'])) {
				$action = 'messageindex';
			} else {
				// Does a mod want to change the default action?
				if (!empty(Config::$modSettings['integrate_default_action'])) {
					$default_action = explode(',', Config::$modSettings['integrate_default_action'])[0];

					return is_a($default_action, ActionInterface::class, true) ? $default_action : Utils::getCallable($default_action);
				}

				$action = 'boardindex';
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

				return is_a($fallback_action, ActionInterface::class, true) ? $fallback_action : Utils::getCallable($fallback_action);
			}

			// If we get here, we have nothing.
			return false;
		}

		// Otherwise, it was set - so let's go to that action.
		if (!empty(self::$actions[$action][0])) {
			require_once Sapi::canonicalPath(Config::$sourcedir . '/' . self::$actions[$action][0]);
		}

		// Do the right thing.
		return self::$actions[$action][1];
	}
}
