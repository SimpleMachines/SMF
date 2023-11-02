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

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\Lang;
use SMF\Menu;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

/**
 * This class has the primary job of showing and editing people's profiles.
 * It also allows the user to change some of their or another's preferences,
 * and such things.
 */
class Main implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => false,
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 *
	 * Defines the menu structure for the profile area.
	 * See {@link Menu.php Menu.php} for details!
	 *
	 * The values of all 'title' and 'label' elements are Lang::$txt keys, and
	 * will be replaced at runtime with the values of those Lang::$txt strings.
	 *
	 * Occurrences of '{scripturl}' and '{boardurl}' in value strings will be
	 * replaced at runtime with the values of Config::$scripturl and
	 * Config::$boardurl.
	 *
	 * Occurrences of '{session_var}' and '{session_id}' in value strings will
	 * be replaced at runtime with the values of Utils::$context['session_var']
	 * and Utils::$context['session_id'].
	 *
	 * In this default definintion, all parts of the menu are set as enabled.
	 * At runtime, however, various parts may be turned on or off depending on
	 * the forum's saved settings.
	 *
	 * We start by defining the permission required. Then SMF takes this and
	 * turns it into the relevant context ;)
	 *
	 * Possible fields:
	 *
	 *   For Sections:
	 *
	 *     string $title:       Section title.
	 *
	 *     array $areas:        Array of areas within this section.
	 *
	 * 	For Areas:
	 *
	 *     string $label:       Text string that will be used to show the area
	 *                          in the menu.
	 *
	 *     string $file:        Optional text string that may contain a file
	 *                          name that's needed for inclusion in order to
	 *                          display the area properly.
	 *
	 *     string $custom_url:  Optional href for area.
	 *
	 *     string $function:    Function to execute for this section.
	 *
	 *     string $class        If your function is a method, set the class
	 *                          field with your class's name and SMF will create
	 *                          a new instance for it.
	 *
	 *     bool $enabled:       Should area be shown?
	 *
	 *     string $sc:          Session check validation to do on save. Without
	 *                          this save will get unset.
	 *
	 *     bool $hidden:        Does this not actually appear on the menu?
	 *
	 *     bool $password:      Whether to require the user's password in order
	 *                          to save the data in the area.
	 *
	 *     array $subsections:  Array of subsections, in order of appearance.
	 *
	 *     array $permission:   Array of permissions to determine who can access
	 *                          this area. Should contain arrays $own and $any.
	 */
	public array $profile_areas = array(
		'info' => array(
			'title' => 'profileInfo',
			'areas' => array(
				'summary' => array(
					'label' => 'summary',
					'function' => __NAMESPACE__ . '\\Summary::call',
					'sub_template' => 'summary',
					'icon' => 'administration',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					),
				),
				'popup' => array(
					'function' => __NAMESPACE__ . '\\Popup::call',
					'sub_template' => 'profile_popup',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
					'select' => 'summary',
				),
				'alerts_popup' => array(
					'function' => __NAMESPACE__ . '\\AlertsPopup::call',
					'sub_template' => 'alerts_popup',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
					'select' => 'summary',
				),
				'statistics' => array(
					'label' => 'statPanel',
					'function' => __NAMESPACE__ . '\\StatPanel::call',
					'sub_template' => 'statPanel',
					'icon' => 'stats',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					),
				),
				'showposts' => array(
					'label' => 'showPosts',
					'function' => __NAMESPACE__ . '\\ShowPosts::call',
					'sub_template' => 'showPosts',
					'icon' => 'posts',
					'subsections' => array(
						'messages' => array(
							'label' => 'showMessages',
							'permission' => array('is_not_guest', 'profile_view'),
						),
						'topics' => array(
							'label' => 'showTopics',
							'permission' => array('is_not_guest', 'profile_view'),
						),
						'unwatchedtopics' => array(
							'label' => 'showUnwatched',
							'permission' => array('is_not_guest', 'profile_view'),
							'enabled' => true,
						),
						'attach' => array(
							'label' => 'showAttachments',
							'permission' => array('is_not_guest', 'profile_view'),
						),
					),
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					),
				),
				'showdrafts' => array(
					'label' => 'drafts_show',
					'function' => 'SMF\\Draft::showInProfile',
					'icon' => 'drafts',
					'enabled' => true,
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
				),
				'showalerts' => array(
					'label' => 'alerts_show',
					'function' => __NAMESPACE__ . '\\ShowAlerts::call',
					'sub_template' => 'showAlerts',
					'icon' => 'alerts',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
				),
				'permissions' => array(
					'label' => 'showPermissions',
					'function' => __NAMESPACE__ . '\\ShowPermissions::call',
					'sub_template' => 'showPermissions',
					'icon' => 'permissions',
					'permission' => array(
						'own' => 'manage_permissions',
						'any' => 'manage_permissions',
					),
				),
				'tracking' => array(
					'label' => 'trackUser',
					'function' => __NAMESPACE__ . '\\Tracking::call',
					'sub_template' => 'tracking',
					'icon' => 'logs',
					'subsections' => array(
						'activity' => array(
							'label' => 'trackActivity',
							'permission' => 'moderate_forum',
						),
						'ip' => array(
							'label' => 'trackIP',
							'permission' => 'moderate_forum',
						),
						'edits' => array(
							'label' => 'trackEdits',
							'permission' => 'moderate_forum',
							'enabled' => true,
						),
						'groupreq' => array(
							'label' => 'trackGroupRequests',
							'permission' => 'approve_group_requests',
							'enabled' => true,
						),
						'logins' => array(
							'label' => 'trackLogins',
							'permission' => 'moderate_forum',
							'enabled' => true,
						),
					),
					'permission' => array(
						'own' => array('moderate_forum', 'approve_group_requests'),
						'any' => array('moderate_forum', 'approve_group_requests'),
					),
				),
				'viewwarning' => array(
					'label' => 'profile_view_warnings',
					'function' => __NAMESPACE__ . '\\ViewWarning::call',
					'sub_template' => 'viewWarning',
					'icon' => 'warning',
					'enabled' => true,
					'permission' => array(
						'own' => array('view_warning_own', 'view_warning_any', 'issue_warning', 'moderate_forum'),
						'any' => array('view_warning_any', 'issue_warning', 'moderate_forum'),
					),
				),
			),
		),
		'edit_profile' => array(
			'title' => 'forumprofile',
			'areas' => array(
				'account' => array(
					'label' => 'account',
					'function' => __NAMESPACE__ . '\\Account::call',
					'sub_template' => 'edit_options',
					'icon' => 'maintain',
					'enabled' => true,
					'sc' => 'post',
					'token' => 'profile-ac%u',
					'password' => true,
					'permission' => array(
						'own' => array('profile_identity_any', 'profile_identity_own', 'profile_password_any', 'profile_password_own', 'manage_membergroups'),
						'any' => array('profile_identity_any', 'profile_password_any', 'manage_membergroups'),
					),
				),
				'tfasetup' => array(
					'label' => 'account',
					'function' => __NAMESPACE__ . '\\TFASetup::call',
					'sub_template' => 'tfasetup',
					'token' => 'profile-tfa%u',
					'enabled' => true,
					'hidden' => true,
					'select' => 'account',
					'permission' => array(
						'own' => array('profile_password_own'),
						'any' => array('profile_password_any'),
					),
				),
				'tfadisable' => array(
					'label' => 'account',
					'function' => __NAMESPACE__ . '\\TFADisable::call',
					'sub_template' => 'tfadisable',
					'token' => 'profile-tfa%u',
					'sc' => 'post',
					'password' => true,
					'enabled' => true,
					'hidden' => true,
					'select' => 'account',
					'permission' => array(
						'own' => array('profile_password_own'),
						'any' => array('profile_password_any'),
					),
				),
				'forumprofile' => array(
					'label' => 'forumprofile',
					'function' => __NAMESPACE__ . '\\ForumProfile::call',
					'sub_template' => 'edit_options',
					'icon' => 'members',
					'sc' => 'post',
					'token' => 'profile-fp%u',
					'permission' => array(
						'own' => array('profile_forum_any', 'profile_forum_own'),
						'any' => array('profile_forum_any'),
					),
				),
				'theme' => array(
					'label' => 'theme',
					'function' => __NAMESPACE__ . '\\ThemeOptions::call',
					'sub_template' => 'edit_options',
					'icon' => 'features',
					'sc' => 'post',
					'token' => 'profile-th%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'notification' => array(
					'label' => 'notification',
					'file' => 'Profile-Modify.php',
					'function' => 'notification',
					'icon' => 'alerts',
					'sc' => 'post',
					//'token' => 'profile-nt%u', This is not checked here. We do it in the function itself - but if it was checked, this is what it'd be.
					'subsections' => array(
						'alerts' => array(
							'label' => 'alert_prefs',
							'permission' => array('is_not_guest', 'profile_extra_any'),
						),
						'topics' => array(
							'label' => 'watched_topics',
							'permission' => array('is_not_guest', 'profile_extra_any'),
						),
						'boards' => array(
							'label' => 'watched_boards',
							'permission' => array('is_not_guest', 'profile_extra_any'),
						),
					),
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array('profile_extra_any'), // If you change this, update it in the functions themselves; we delegate all saving checks there.
					),
				),
				'ignoreboards' => array(
					'label' => 'ignoreboards',
					'file' => 'Profile-Modify.php',
					'function' => 'ignoreboards',
					'icon' => 'boards',
					'enabled' => true,
					'sc' => 'post',
					'token' => 'profile-ib%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'lists' => array(
					'label' => 'editBuddyIgnoreLists',
					'file' => 'Profile-Modify.php',
					'function' => 'editBuddyIgnoreLists',
					'icon' => 'frenemy',
					'enabled' => true,
					'sc' => 'post',
					'subsections' => array(
						'buddies' => array(
							'label' => 'editBuddies',
						),
						'ignore' => array(
							'label' => 'editIgnoreList',
						),
					),
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array(),
					),
				),
				'groupmembership' => array(
					'label' => 'groupmembership',
					'file' => 'Profile-Modify.php',
					'function' => 'groupMembership',
					'icon' => 'people',
					'enabled' => true,
					'sc' => 'request',
					'token' => 'profile-gm%u',
					'token_type' => 'request',
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array('manage_membergroups'),
					),
				),
			),
		),
		'profile_action' => array(
			'title' => 'profileAction',
			'areas' => array(
				'sendpm' => array(
					'label' => 'profileSendIm',
					'custom_url' => '{scripturl}?action=pm;sa=send',
					'icon' => 'personal_message',
					'enabled' => true,
					'permission' => array(
						'own' => array(),
						'any' => array('pm_send'),
					),
				),
				'report' => array(
					'label' => 'report_profile',
					'custom_url' => '{scripturl}?action=reporttm;{session_var}={session_id}',
					'icon' => 'warning',
					'enabled' => true,
					'permission' => array(
						'own' => array(),
						'any' => array('report_user'),
					),
				),
				'issuewarning' => array(
					'label' => 'profile_issue_warning',
					'function' => __NAMESPACE__ . '\\IssueWarning::call',
					'sub_template' => 'issueWarning',
					'icon' => 'warning',
					'token' => 'profile-iw%u',
					'enabled' => true,
					'permission' => array(
						'own' => array(),
						'any' => array('issue_warning'),
					),
				),
				'banuser' => array(
					'label' => 'profileBanUser',
					'custom_url' => '{scripturl}?action=admin;area=ban;sa=add',
					'icon' => 'ban',
					'enabled' => true,
					'permission' => array(
						'own' => array(),
						'any' => array('manage_bans'),
					),
				),
				'subscriptions' => array(
					'label' => 'subscriptions',
					'file' => 'Profile-Actions.php',
					'function' => 'subscriptions',
					'icon' => 'paid',
					'enabled' => true,
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array('moderate_forum'),
					),
				),
				'getprofiledata' => array(
					'label' => 'export_profile_data',
					'function' => __NAMESPACE__ . '\\Export::call',
					'sub_template' => 'export_profile_data',
					'icon' => 'packages',
					// 'token' => 'profile-ex%u', // This is not checked here. We do it in the function itself - but if it was checked, this is what it'd be.
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('moderate_forum'),
					),
				),
				'download' => array(
					'label' => 'export_profile_data',
					'function' => __NAMESPACE__ . '\\ExportDownload::call',
					'sub_template' => 'download_export_file',
					'icon' => 'packages',
					'hidden' => true,
					'select' => 'getprofiledata',
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('moderate_forum'),
					),
				),
				'dlattach' => array(
					'label' => 'export_profile_data',
					'function' => __NAMESPACE__ . '\\ExportAttachment::call',
					'sub_template' => 'export_attachment',
					'icon' => 'packages',
					'hidden' => true,
					'select' => 'getprofiledata',
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array(),
					),
				),
				'deleteaccount' => array(
					'label' => 'deleteAccount',
					'file' => 'Profile-Actions.php',
					'function' => 'deleteAccount',
					'icon' => 'members_delete',
					'sc' => 'post',
					'token' => 'profile-da%u',
					'password' => true,
					'permission' => array(
						'own' => array('profile_remove_any', 'profile_remove_own'),
						'any' => array('profile_remove_any'),
					),
				),
				'activateaccount' => array(
					'file' => 'Profile-Actions.php',
					'function' => 'activateAccount',
					'icon' => 'regcenter',
					'sc' => 'get',
					'token' => 'profile-aa%u',
					'token_type' => 'get',
					'permission' => array(
						'own' => array(),
						'any' => array('moderate_forum'),
					),
				),
				// A logout link just for the popup menu.
				'logout' => array(
					'label' => 'logout',
					'custom_url' => '{scripturl}?action=logout;{session_var}={session_id}',
					'icon' => 'logout',
					'enabled' => true,
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array(),
					),
				),
			),
		),
	);

	/**
	 * @var bool
	 *
	 * Whether a password check is required to save changes to the current
	 * profile area.
	 */
	public bool $check_password;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// Is there an updated message to show?
		if (isset($_GET['updated']))
			Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];

		$menu = $this->createMenu();

		$this->securityChecks();

		// File to include?
		if (!empty($menu->include_data['file']))
			require_once(Config::$sourcedir . '/' . $menu->include_data['file']);

		// Build the link tree.
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=profile' . (Profile::$member->id != User::$me->id ? ';u=' . Profile::$member->id : ''),
			'name' => sprintf(Lang::$txt['profile_of_username'], Profile::$member->formatted['name']),
		);

		if (!empty($menu->include_data['label']))
		{
			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=profile' . (Profile::$member->id != User::$me->id ? ';u=' . Profile::$member->id : '') . ';area=' . $menu->current_area,
				'name' => $menu->include_data['label'],
			);
		}

		if (!empty($menu->current_subsection) && $menu->include_data['subsections'][$menu->current_subsection]['label'] != $menu->include_data['label'])
		{
			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=profile' . (Profile::$member->id != User::$me->id ? ';u=' . Profile::$member->id : '') . ';area=' . $menu->current_area . ';sa=' . $menu->current_subsection,
				'name' => $menu->include_data['subsections'][$menu->current_subsection]['label'],
			);
		}

		// Set the template for this area and add the profile layer.
		Utils::$context['sub_template'] = $menu->include_data['sub_template'] ?? $menu->include_data['function'];

		Utils::$context['template_layers'][] = 'profile';

		Theme::loadJavaScriptFile('profile.js', array('defer' => false, 'minimize' => true), 'smf_profile');

		// Right - are we saving - if so let's save the old data first.
		if (Utils::$context['completed_save'])
		{
			// Clean up the POST variables.
			$_POST = htmltrim__recursive($_POST);
			$_POST = htmlspecialchars__recursive($_POST);
			Profile::$member->post_sanitized = true;

			if ($this->check_password)
			{
				// Check to ensure we're forcing SSL for authentication
				if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !httpsOn())
				{
					fatal_lang_error('login_ssl_required', false);
				}

				$password = isset($_POST['oldpasswrd']) ? $_POST['oldpasswrd'] :  '';

				// You didn't even enter a password!
				if (trim($password) == '')
					Profile::$member->save_errors[] = 'no_password';

				// Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
				$password = un_htmlspecialchars($password);

				// Does the integration want to check passwords?
				$good_password = in_array(true, call_integration_hook('integrate_verify_password', array(Profile::$member->username, $password, false)), true);

				// Bad password!!!
				if (!$good_password && !hash_verify_password(Profile::$member->username, $password, Profile::$member->passwd))
				{
					Profile::$member->save_errors[] = 'bad_password';
				}

				// Warn other elements not to jump the gun and do custom changes!
				if (in_array('bad_password', Profile::$member->save_errors))
					Utils::$context['password_auth_failed'] = true;
			}

			// Change the IP address in the database.
			if (User::$me->is_owner && ($_REQUEST['area'] ?? null) != 'tfasetup')
				Profile::$member->new_data['member_ip'] = User::$me->ip;

			// Now call the sub-action function...
			if ($menu->current_area == 'activateaccount')
			{
				if (empty(Profile::$member->save_errors))
					activateAccount(Profile::$member->id);
			}
			elseif ($menu->current_area == 'deleteaccount')
			{
				if (empty(Profile::$member->save_errors))
				{
					deleteAccount2(Profile::$member->id);
					redirectexit();
				}
			}
			elseif (empty(Profile::$member->save_errors))
			{
				if (($_REQUEST['area'] ?? null) == 'tfadisable')
				{
					// Already checked the password, token, permissions, and session.
					Profile::$member->new_data += array(
						'tfa_secret' => '',
						'tfa_backup' => '',
					);
				}

				if ($menu->current_area == 'groupmembership')
				{
					$msg = groupMembership2(Profile::$member->new_data, Profile::$member->save_errors, Profile::$member->id);
				}

				$force_redirect = !in_array($menu->current_area, array('account', 'forumprofile', 'theme'));

				Profile::$member->save();
			}
		}

		// Have some errors for some reason?
		if (!empty(Profile::$member->save_errors))
		{
			// Set all the errors so the template knows what went wrong.
			foreach (Profile::$member->save_errors as $error_type)
				Utils::$context['modify_error'][$error_type] = true;
		}
		// If it's you or it's forced then we should redirect upon save.
		elseif ((!empty(Profile::$member->new_data) && User::$me->is_owner && !Utils::$context['do_preview']) || !empty($force_redirect))
		{
			redirectexit('action=profile' . (User::$me->is_owner ? '' : ';u=' . Profile::$member->id) . ';area=' . $menu->current_area . (!empty($msg) ? ';msg=' . $msg : ';updated'));
		}

		// Get the right callable.
		$call = call_helper($menu->include_data['function'], true);

		// Is it valid?
		if (!empty($call))
			call_user_func($call, Profile::$member->id);

		// Set the page title if it's not already set...
		if (!isset(Utils::$context['page_title']))
		{
			Utils::$context['page_title'] = Lang::$txt['profile'] . (isset(Lang::$txt[$menu->current_area]) ? ' - ' . Lang::$txt[$menu->current_area] : '');
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj))
			self::$obj = new self();

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Backward compatibility wrapper.
	 */
	public static function modifyProfile($post_errors = array()): void
	{
		self::load();
		Profile::$member->save_errors = $post_errors;
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Don't reload this as we may have processed error strings.
		if (empty(Profile::$member->save_errors))
			Lang::load('Profile+Drafts');

		Theme::loadTemplate('Profile');

		// Load the data of the member whose profile we are viewing.
		Profile::load();

		// Group management isn't actually a permission. But we need it to be for this, so we need a phantom permission.
		// And we care about what the current user can do, not what the user whose profile it is.
		if (User::$me->mod_cache['gq'] != '0=1')
			User::$me->permissions[] = 'approve_group_requests';

		// If paid subscriptions are enabled, make sure we actually have at least one subscription available...
		Utils::$context['subs_available'] = false;

		if (!empty(Config::$modSettings['paid_enabled']))
		{
			$get_active_subs = Db::$db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}subscriptions
				WHERE active = {int:active}', array(
					'active' => 1,
				)
			);
			list($num_subs) = Db::$db->fetch_row($get_active_subs);
			Db::$db->free_result($get_active_subs);

			Utils::$context['subs_available'] = !empty($num_subs);
		}

		$this->setProfileAreas();
	}

	/**
	 * Sets any dynamic values in $this->profile_areas.
	 */
	protected function setProfileAreas(): void
	{
		// Finalize various string values.
		array_walk_recursive(
			$this->profile_areas,
			function(&$value, $key)
			{
				if (in_array($key, array('title', 'label')))
					$value = Lang::$txt[$value] ?? $value;

				$value = strtr($value, array(
					'{scripturl}' => Config::$scripturl,
					'{boardurl}' => Config::$boardurl,
					'{session_var}' => Utils::$context['session_var'],
					'{session_id}' => Utils::$context['session_id'],
				));
			}
		);

		$this->profile_areas['info']['areas']['showposts']['subsections']['unwatchedtopics']['enabled'] = User::$me->is_owner;

		$this->profile_areas['info']['areas']['showdrafts']['enabled'] = !empty(Config::$modSettings['drafts_post_enabled']) && User::$me->is_owner;

		$this->profile_areas['info']['areas']['tracking']['subsections']['edits']['enabled'] = !empty(Config::$modSettings['userlog_enabled']);

		$this->profile_areas['info']['areas']['tracking']['subsections']['groupreq']['enabled'] = !empty(Config::$modSettings['show_group_membership']);

		$this->profile_areas['info']['areas']['tracking']['subsections']['logins']['enabled'] = !empty(Config::$modSettings['loginHistoryDays']);

		$this->profile_areas['info']['areas']['viewwarning']['enabled'] = Config::$modSettings['warning_settings'][0] == 1 && Profile::$member->warning;


		$this->profile_areas['edit_profile']['areas']['account']['enabled'] = User::$me->is_admin || (Profile::$member->group_id != 1 && !in_array(1, Profile::$member->additional_groups));

		$this->profile_areas['edit_profile']['areas']['tfasetup']['enabled'] = !empty(Config::$modSettings['tfa_mode']);

		$this->profile_areas['edit_profile']['areas']['tfadisable']['enabled'] = !empty(Config::$modSettings['tfa_mode']);

		$this->profile_areas['edit_profile']['areas']['ignoreboards']['enabled'] = !empty(Config::$modSettings['allow_ignore_boards']);

		$this->profile_areas['edit_profile']['areas']['lists']['enabled'] = !empty(Config::$modSettings['enable_buddylist']) && User::$me->is_owner;

		$this->profile_areas['edit_profile']['areas']['groupmembership']['enabled'] = !empty(Config::$modSettings['show_group_membership']) && User::$me->is_owner;


		$this->profile_areas['profile_action']['areas']['sendpm']['enabled'] = allowedTo('profile_view');

		$this->profile_areas['profile_action']['areas']['report']['enabled'] = allowedTo('profile_view');

		$this->profile_areas['profile_action']['areas']['issuewarning']['enabled'] = Config::$modSettings['warning_settings'][0] == 1;

		$this->profile_areas['profile_action']['areas']['banuser']['enabled'] = Profile::$member->group_id != 1 && !in_array(1, Profile::$member->additional_groups);

		$this->profile_areas['profile_action']['areas']['subscriptions']['enabled'] = !empty(Config::$modSettings['paid_enabled']) && Utils::$context['subs_available'];

		$this->profile_areas['profile_action']['areas']['logout']['enabled'] = !empty($_REQUEST['area']) && $_REQUEST['area'] === 'popup';

		// Give mods access to the menu.
		call_integration_hook('integrate_profile_areas', array(&$this->profile_areas));

		// Do some cleaning ready for the menu function.
		Utils::$context['password_areas'] = array();

		foreach ($this->profile_areas as $section_id => $section)
		{
			// Do a bit of spring cleaning so to speak.
			foreach ($section['areas'] as $area_id => $area)
			{
				// If it said no permissions that meant it wasn't valid!
				if (empty($area['permission'][User::$me->is_owner ? 'own' : 'any']))
				{
					$this->profile_areas[$section_id]['areas'][$area_id]['enabled'] = false;
				}
				// Otherwise pick the right set.
				else
				{
					$this->profile_areas[$section_id]['areas'][$area_id]['permission'] = $area['permission'][User::$me->is_owner ? 'own' : 'any'];
				}

				// Password required in most cases
				if (!empty($area['password']))
					Utils::$context['password_areas'][] = $area_id;
			}
		}
	}

	/**
	 * Creates the profile menu.
	 *
	 * The menu is always available as Menu::$loaded['profile'], but for
	 * convenience, this method also returns it.
	 *
	 * @return object The profile menu object.
	 */
	protected function createMenu(): object
	{
		// Set a few options for the menu.
		$menuOptions = array(
			'disable_hook_call' => true,
			'disable_url_session_check' => true,
			'current_area' => isset($_REQUEST['area']) ? $_REQUEST['area'] : '',
			'extra_url_parameters' => array(
				'u' => Profile::$member->id,
			),
		);

		// Actually create the menu!
		$menu = new Menu($this->profile_areas, $menuOptions);

		// No menu means no access.
		if (empty($menu->include_data) && (!User::$me->is_guest || validateSession()))
			fatal_lang_error('no_access', false);

		// Backward compatibility.
		Utils::$context['profile_menu_id'] = $menu->id;
		Utils::$context['profile_menu_name'] = $menu->name;
		Utils::$context['menu_item_selected'] = $menu->current_area;

		return $menu;
	}

	/**
	 * Checks that the viewer can see what they are asking to see.
	 */
	protected function securityChecks(): void
	{
		// Before we go any further, let's work on the area we've said is valid. Note this is done here just in case we ever compromise the menu function in error!
		Utils::$context['completed_save'] = false;
		Utils::$context['do_preview'] = isset($_REQUEST['preview_signature']);

		$security_checks = array();
		$found_area = false;

		foreach ($this->profile_areas as $section_id => $section)
		{
			// Do a bit of spring cleaning so to speak.
			foreach ($section['areas'] as $area_id => $area)
			{
				// Is this our area?
				if (Menu::$loaded['profile']->current_area == $area_id)
				{
					// This can't happen - but is a security check.
					if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($area['enabled']) && $area['enabled'] == false))
					{
						fatal_lang_error('no_access', false);
					}

					// Are we saving data in a valid area?
					if (isset($area['sc']) && (isset($_REQUEST['save']) || Utils::$context['do_preview']))
					{
						$security_checks['session'] = $area['sc'];
						Utils::$context['completed_save'] = true;
					}

					// Do we need to perform a token check?
					if (!empty($area['token']))
					{
						$security_checks[isset($_REQUEST['save']) ? 'validateToken' : 'needsToken'] = $area['token'];

						$token_name = $area['token'] !== true ? str_replace('%u', Profile::$member->id, $area['token']) : 'profile-u' . Profile::$member->id;

						$token_type = isset($area['token_type']) && in_array($area['token_type'], array('request', 'post', 'get')) ? $area['token_type'] : 'post';
					}

					// Does this require session validating?
					if (!empty($area['validate']) || (isset($_REQUEST['save']) && !User::$me->is_owner && ($area_id != 'issuewarning' || empty(Config::$modSettings['securityDisable_moderate']))))
					{
						$security_checks['validate'] = true;
					}

					// Permissions for good measure.
					if (!empty(Menu::$loaded['profile']->include_data['permission']))
					{
						$security_checks['permission'] = Menu::$loaded['profile']->include_data['permission'];
					}

					// Either way got something.
					$found_area = true;
				}
			}
		}

		// Oh dear, some serious security lapse is going on here... we'll put a stop to that!
		if (!$found_area)
			fatal_lang_error('no_access', false);

		// Now the context is setup have we got any security checks to carry out additional to that above?
		if (isset($security_checks['session']))
			checkSession($security_checks['session']);

		if (isset($security_checks['validate']))
			validateSession();

		if (isset($security_checks['validateToken']))
			validateToken($token_name, $token_type);

		if (isset($security_checks['permission']))
			isAllowedTo($security_checks['permission']);

		// Create a token if needed.
		if (isset($security_checks['needsToken']) || isset($security_checks['validateToken']))
		{
			createToken($token_name, $token_type);
			Utils::$context['token_check'] = $token_name;
		}

		// All the subactions that require a user password in order to validate.
		$this->check_password = User::$me->is_owner && in_array(Menu::$loaded['profile']->current_area, Utils::$context['password_areas']);

		Utils::$context['require_password'] = $this->check_password;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Main::exportStatic'))
	Main::exportStatic();

?>