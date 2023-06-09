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

namespace SMF\Actions\Admin;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\Lang;
use SMF\Mail;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Notify;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * This class, unpredictable as this might be, handles basic administration.
 */
class ACP implements ActionInterface
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
			'call' => 'AdminMain',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Defines the menu structure for the admin center.
	 * See {@link Menu.php Menu.php} for details!
	 *
	 * The values of all 'title' and 'label' elements are Lang::$txt keys, and
	 * will be replaced at runtime with the values of those Lang::$txt strings.
	 *
	 * All occurrences of '{scripturl}' and '{boardurl}' in value strings will
	 * be replaced at runtime with the real values of Config::$scripturl and
	 * Config::$boardurl.
	 *
	 * In this default definintion, all parts of the menu are set as enabled.
	 * At runtime, however, various parts may be turned on or off depending on
	 * the forum's saved settings.
	 *
	 * MOD AUTHORS: If your mod has just a few simple settings and doesn't need
	 * its own settings page, you don't need to bother adding anything to this
	 * menu. Instead, just use the integrate_general_mod_settings hook to add
	 * your settings to the general mod settings page.
	 *
	 * Alternatively, if you want to add a custom settings page for your mod,
	 * please use the integrate_admin_areas hook to add your settings page to
	 * $admin_areas['config']['areas']['modsettings']['subsections'].
	 */
	public array $admin_areas = array(
		'forum' => array(
			'title' => 'admin_main',
			'permission' => array(
				'admin_forum',
				'manage_permissions',
				'moderate_forum',
				'manage_membergroups',
				'manage_bans',
				'send_mail',
				'edit_news',
				'manage_boards',
				'manage_smileys',
				'manage_attachments',
			),
			'areas' => array(
				'index' => array(
					'label' => 'admin_center',
					'function' => __NAMESPACE__ . '\\Home::call',
					'icon' => 'administration',
				),
				'credits' => array(
					'label' => 'support_credits_title',
					'function' => __NAMESPACE__ . '\\Home::call',
					'icon' => 'support',
				),
				'news' => array(
					'label' => 'news_title',
					'file' => 'ManageNews.php',
					'function' => 'ManageNews',
					'icon' => 'news',
					'permission' => array(
						'edit_news',
						'send_mail',
						'admin_forum',
					),
					'subsections' => array(
						'editnews' => array(
							'label' => 'admin_edit_news',
							'permission' => 'edit_news',
						),
						'mailingmembers' => array(
							'label' => 'admin_newsletters',
							'permission' => 'send_mail',
						),
						'settings' => array(
							'label' => 'settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'packages' => array(
					'label' => 'package',
					'function' => 'SMF\\PackageManager\\PackageManager::call',
					'permission' => array('admin_forum'),
					'icon' => 'packages',
					'subsections' => array(
						'browse' => array(
							'label' => 'browse_packages',
						),
						'packageget' => array(
							'label' => 'download_packages',
							'url' => '{scripturl}?action=admin;area=packages;sa=packageget;get',
						),
						'perms' => array(
							'label' => 'package_file_perms',
						),
						'options' => array(
							'label' => 'package_settings',
						),
					),
				),
				'search' => array(
					'function' => __NAMESPACE__ . '\\Find::call',
					'permission' => array('admin_forum'),
					'select' => 'index'
				),
				'adminlogoff' => array(
					'label' => 'admin_logoff',
					'function' => __NAMESPACE__ . '\\EndSession::call',
					'enabled' => true,
					'icon' => 'exit',
				),
			),
		),
		'config' => array(
			'title' => 'admin_config',
			'permission' => array('admin_forum'),
			'areas' => array(
				'featuresettings' => array(
					'label' => 'modSettings_title',
					'file' => 'ManageSettings.php',
					'function' => 'ModifyFeatureSettings',
					'icon' => 'features',
					'subsections' => array(
						'basic' => array(
							'label' => 'mods_cat_features',
						),
						'bbc' => array(
							'label' => 'manageposts_bbc_settings',
						),
						'layout' => array(
							'label' => 'mods_cat_layout',
						),
						'sig' => array(
							'label' => 'signature_settings_short',
						),
						'profile' => array(
							'label' => 'custom_profile_shorttitle',
						),
						'likes' => array(
							'label' => 'likes',
						),
						'mentions' => array(
							'label' => 'mentions',
						),
						'alerts' => array(
							'label' => 'notifications',
						),
					),
				),
				'antispam' => array(
					'label' => 'antispam_title',
					'file' => 'ManageSettings.php',
					'function' => 'ModifyAntispamSettings',
					'icon' => 'security',
				),
				'languages' => array(
					'label' => 'language_configuration',
					'file' => 'ManageLanguages.php',
					'function' => 'ManageLanguages',
					'icon' => 'languages',
					'subsections' => array(
						'edit' => array(
							'label' => 'language_edit',
						),
						'add' => array(
							'label' => 'language_add',
						),
						'settings' => array(
							'label' => 'language_settings',
						),
					),
				),
				'current_theme' => array(
					'label' => 'theme_current_settings',
					'file' => 'ManageThemes.php',
					'function' => 'ThemesMain',
					'custom_url' => '{scripturl}?action=admin;area=theme;sa=list;th=%1$d',
					'icon' => 'current_theme',
				),
				'theme' => array(
					'label' => 'theme_admin',
					'file' => 'ManageThemes.php',
					'function' => 'ThemesMain',
					'custom_url' => '{scripturl}?action=admin;area=theme',
					'icon' => 'themes',
					'subsections' => array(
						'admin' => array(
							'label' => 'themeadmin_admin_title',
						),
						'list' => array(
							'label' => 'themeadmin_list_title',
						),
						'reset' => array(
							'label' => 'themeadmin_reset_title',
						),
						'edit' => array(
							'label' => 'themeadmin_edit_title',
						),
					),
				),
				'modsettings' => array(
					'label' => 'admin_modifications',
					'file' => 'ManageSettings.php',
					'function' => 'ModifyModSettings',
					'icon' => 'modifications',
					'subsections' => array(
						// MOD AUTHORS: If your mod has just a few simple
						// settings and doesn't need its own settings page, you
						// can use the integrate_general_mod_settings hook to
						// add them to the 'general' page.
						'general' => array(
							'label' => 'mods_cat_modifications_misc',
						),
						// MOD AUTHORS: If your mod has a custom settings page,
						// use the integrate_admin_areas hook to insert it here.
					),
				),
			),
		),
		'layout' => array(
			'title' => 'layout_controls',
			'permission' => array('manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum'),
			'areas' => array(
				'manageboards' => array(
					'label' => 'admin_boards',
					'file' => 'ManageBoards.php',
					'function' => 'ManageBoards',
					'icon' => 'boards',
					'permission' => array('manage_boards'),
					'subsections' => array(
						'main' => array(
							'label' => 'boards_edit',
						),
						'newcat' => array(
							'label' => 'mboards_new_cat',
						),
						'settings' => array(
							'label' => 'settings',
							'admin_forum',
						),
					),
				),
				'postsettings' => array(
					'label' => 'manageposts',
					'file' => 'ManagePosts.php',
					'function' => 'ManagePostSettings',
					'permission' => array('admin_forum'),
					'icon' => 'posts',
					'subsections' => array(
						'posts' => array(
							'label' => 'manageposts_settings',
						),
						'censor' => array(
							'label' => 'admin_censored_words',
						),
						'topics' => array(
							'label' => 'manageposts_topic_settings',
						),
						'drafts' => array(
							'label' => 'manage_drafts',
						),
					),
				),
				'managecalendar' => array(
					'label' => 'manage_calendar',
					'file' => 'ManageCalendar.php',
					'function' => 'ManageCalendar',
					'icon' => 'calendar',
					'permission' => array('admin_forum'),
					'inactive' => false,
					'subsections' => array(
						'holidays' => array(
							'label' => 'manage_holidays',
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => 'calendar_settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'managesearch' => array(
					'label' => 'manage_search',
					'file' => 'ManageSearch.php',
					'function' => 'ManageSearch',
					'icon' => 'search',
					'permission' => array('admin_forum'),
					'subsections' => array(
						'weights' => array(
							'label' => 'search_weights',
						),
						'method' => array(
							'label' => 'search_method',
						),
						'settings' => array(
							'label' => 'settings',
						),
					),
				),
				'smileys' => array(
					'label' => 'smileys_manage',
					'file' => 'ManageSmileys.php',
					'function' => 'ManageSmileys',
					'icon' => 'smiley',
					'permission' => array('manage_smileys'),
					'subsections' => array(
						'editsets' => array(
							'label' => 'smiley_sets',
						),
						'addsmiley' => array(
							'label' => 'smileys_add',
							'enabled' => true,
						),
						'editsmileys' => array(
							'label' => 'smileys_edit',
							'enabled' => true,
						),
						'setorder' => array(
							'label' => 'smileys_set_order',
							'enabled' => true,
						),
						'editicons' => array(
							'label' => 'icons_edit_message_icons',
							'enabled' => true,
						),
						'settings' => array(
							'label' => 'settings',
						),
					),
				),
				'manageattachments' => array(
					'label' => 'attachments_avatars',
					'file' => 'ManageAttachments.php',
					'function' => 'ManageAttachments',
					'icon' => 'attachment',
					'permission' => array('manage_attachments'),
					'subsections' => array(
						'browse' => array(
							'label' => 'attachment_manager_browse',
						),
						'attachments' => array(
							'label' => 'attachment_manager_settings',
						),
						'avatars' => array(
							'label' => 'attachment_manager_avatar_settings',
						),
						'attachpaths' => array(
							'label' => 'attach_directories',
						),
						'maintenance' => array(
							'label' => 'attachment_manager_maintenance',
						),
					),
				),
				'sengines' => array(
					'label' => 'search_engines',
					'inactive' => false,
					'file' => 'ManageSearchEngines.php',
					'icon' => 'engines',
					'function' => 'SearchEngines',
					'permission' => 'admin_forum',
					'subsections' => array(
						'stats' => array(
							'label' => 'spider_stats',
						),
						'logs' => array(
							'label' => 'spider_logs',
						),
						'spiders' => array(
							'label' => 'spiders',
						),
						'settings' => array(
							'label' => 'settings',
						),
					),
				),
			),
		),
		'members' => array(
			'title' => 'admin_manage_members',
			'permission' => array(
				'moderate_forum',
				'manage_membergroups',
				'manage_bans',
				'manage_permissions',
				'admin_forum',
			),
			'areas' => array(
				'viewmembers' => array(
					'label' => 'admin_users',
					'file' => 'ManageMembers.php',
					'function' => 'ViewMembers',
					'icon' => 'members',
					'permission' => array('moderate_forum'),
					'subsections' => array(
						'all' => array(
							'label' => 'view_all_members',
						),
						'search' => array(
							'label' => 'mlist_search',
						),
					),
				),
				'membergroups' => array(
					'label' => 'admin_groups',
					'file' => 'ManageMembergroups.php',
					'function' => 'ModifyMembergroups',
					'icon' => 'membergroups',
					'permission' => array('manage_membergroups'),
					'subsections' => array(
						'index' => array(
							'label' => 'membergroups_edit_groups',
							'permission' => 'manage_membergroups',
						),
						'add' => array(
							'label' => 'membergroups_new_group',
							'permission' => 'manage_membergroups',
						),
						'settings' => array(
							'label' => 'settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'permissions' => array(
					'label' => 'edit_permissions',
					'file' => 'ManagePermissions.php',
					'function' => 'ModifyPermissions',
					'icon' => 'permissions',
					'permission' => array('manage_permissions'),
					'subsections' => array(
						'index' => array(
							'label' => 'permissions_groups',
							'permission' => 'manage_permissions',
						),
						'board' => array(
							'label' => 'permissions_boards',
							'permission' => 'manage_permissions',
						),
						'profiles' => array(
							'label' => 'permissions_profiles',
							'permission' => 'manage_permissions',
						),
						'postmod' => array(
							'label' => 'permissions_post_moderation',
							'permission' => 'manage_permissions',
						),
						'settings' => array(
							'label' => 'settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'regcenter' => array(
					'label' => 'registration_center',
					'file' => 'ManageRegistration.php',
					'function' => 'RegCenter',
					'icon' => 'regcenter',
					'permission' => array(
						'admin_forum',
						'moderate_forum',
					),
					'subsections' => array(
						'register' => array(
							'label' => 'admin_browse_register_new',
							'permission' => 'moderate_forum',
						),
						'agreement' => array(
							'label' => 'registration_agreement',
							'permission' => 'admin_forum',
						),
						'policy' => array(
							'label' => 'privacy_policy',
							'permission' => 'admin_forum',
						),
						'reservednames' => array(
							'label' => 'admin_reserved_set',
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => 'settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'warnings' => array(
					'label' => 'warnings',
					'file' => 'ManageSettings.php',
					'function' => 'ModifyWarningSettings',
					'icon' => 'warning',
					'inactive' => false,
					'permission' => array('admin_forum'),
				),
				'ban' => array(
					'label' => 'ban_title',
					'file' => 'ManageBans.php',
					'function' => 'Ban',
					'icon' => 'ban',
					'permission' => 'manage_bans',
					'subsections' => array(
						'list' => array(
							'label' => 'ban_edit_list',
						),
						'add' => array(
							'label' => 'ban_add_new',
						),
						'browse' => array(
							'label' => 'ban_trigger_browse',
						),
						'log' => array(
							'label' => 'ban_log',
						),
					),
				),
				'paidsubscribe' => array(
					'label' => 'paid_subscriptions',
					'inactive' => false,
					'file' => 'ManagePaid.php',
					'icon' => 'paid',
					'function' => 'ManagePaidSubscriptions',
					'permission' => 'admin_forum',
					'subsections' => array(
						'view' => array(
							'label' => 'paid_subs_view',
						),
						'settings' => array(
							'label' => 'settings',
						),
					),
				),
			),
		),
		'maintenance' => array(
			'title' => 'admin_maintenance',
			'permission' => array('admin_forum'),
			'areas' => array(
				'serversettings' => array(
					'label' => 'admin_server_settings',
					'file' => 'Actions/Admin/Server.php',
					'function' => 'ModifySettings',
					'icon' => 'server',
					'subsections' => array(
						'general' => array(
							'label' => 'general_settings',
						),
						'database' => array(
							'label' => 'database_settings',
						),
						'cookie' => array(
							'label' => 'cookies_sessions_settings',
						),
						'security' => array(
							'label' => 'security_settings',
						),
						'cache' => array(
							'label' => 'caching_settings',
						),
						'export' => array(
							'label' => 'export_settings',
						),
						'loads' => array(
							'label' => 'load_balancing_settings',
						),
						'phpinfo' => array(
							'label' => 'phpinfo_settings',
						),
					),
				),
				'maintain' => array(
					'label' => 'maintain_title',
					'file' => 'ManageMaintenance.php',
					'icon' => 'maintain',
					'function' => 'ManageMaintenance',
					'subsections' => array(
						'routine' => array(
							'label' => 'maintain_sub_routine',
							'permission' => 'admin_forum',
						),
						'database' => array(
							'label' => 'maintain_sub_database',
							'permission' => 'admin_forum',
						),
						'members' => array(
							'label' => 'maintain_sub_members',
							'permission' => 'admin_forum',
						),
						'topics' => array(
							'label' => 'maintain_sub_topics',
							'permission' => 'admin_forum',
						),
						'hooks' => array(
							'label' => 'hooks_title_list',
							'permission' => 'admin_forum',
						),
					),
				),
				'scheduledtasks' => array(
					'label' => 'maintain_tasks',
					'file' => 'ManageScheduledTasks.php',
					'icon' => 'scheduled',
					'function' => 'ManageScheduledTasks',
					'subsections' => array(
						'tasks' => array(
							'label' => 'maintain_tasks',
							'permission' => 'admin_forum',
						),
						'tasklog' => array(
							'label' => 'scheduled_log',
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => 'scheduled_tasks_settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'mailqueue' => array(
					'label' => 'mailqueue_title',
					'file' => 'ManageMail.php',
					'function' => 'ManageMail',
					'icon' => 'mail',
					'subsections' => array(
						'browse' => array(
							'label' => 'mailqueue_browse',
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => 'mailqueue_settings',
							'permission' => 'admin_forum',
						),
						'test' => array(
							'label' => 'mailqueue_test',
							'permission' => 'admin_forum',
						),
					),
				),
				'reports' => array(
					'label' => 'generate_reports',
					'file' => 'Reports.php',
					'function' => 'ReportsMain',
					'icon' => 'reports',
				),
				'logs' => array(
					'label' => 'logs',
					'function' => __NAMESPACE__ . '\\Logs::call',
					'icon' => 'logs',
					'subsections' => array(
						'errorlog' => array(
							'label' => 'errorlog',
							'permission' => 'admin_forum',
							'enabled' => true,
							'url' => '{scripturl}?action=admin;area=logs;sa=errorlog;desc',
						),
						'adminlog' => array(
							'label' => 'admin_log',
							'permission' => 'admin_forum',
							'enabled' => true,
						),
						'modlog' => array(
							'label' => 'moderation_log',
							'permission' => 'admin_forum',
							'enabled' => true,
						),
						'banlog' => array(
							'label' => 'ban_log',
							'permission' => 'manage_bans',
						),
						'spiderlog' => array(
							'label' => 'spider_logs',
							'permission' => 'admin_forum',
							'enabled' => true,
						),
						'tasklog' => array(
							'label' => 'scheduled_log',
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => 'log_settings',
							'permission' => 'admin_forum',
						),
					),
				),
				'repairboards' => array(
					'label' => 'admin_repair',
					'file' => 'RepairBoards.php',
					'function' => 'RepairBoards',
					'select' => 'maintain',
					'hidden' => true,
				),
			),
		),
	);


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
	 * The main admin handling function.
	 *
	 * It initialises all the basic context required for the admin center.
	 * It passes execution onto the relevant admin section.
	 * If the passed section is not found it shows the admin home page.
	 */
	public function execute(): void
	{
		// Make sure the administrator has a valid session...
		validateSession();

		// Actually create the menu!
		// Hook call disabled because we already called it in setAdminAreas()
		$menu = new Menu($this->admin_areas, array(
			'do_big_icons' => true,
			'disable_hook_call' => true,
		));

		// Nothing valid?
		if (empty($menu->include_data))
			fatal_lang_error('no_access', false);

		// Build the link tree.
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=admin',
			'name' => Lang::$txt['admin_center'],
		);

		if (isset($menu->current_area) && $menu->current_area != 'index')
		{
			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=admin;area=' . $menu->current_area . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'name' => $menu->include_data['label'],
			);
		}

		if (!empty($menu->current_subsection) && $menu->include_data['subsections'][$menu->current_subsection]['label'] != $menu->include_data['label'])
		{
			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=admin;area=' . $menu->current_area . ';sa=' . $menu->current_subsection . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'name' => $menu->include_data['subsections'][$menu->current_subsection]['label'],
			);
		}

		// Make a note of the Unique ID for this menu.
		Utils::$context['admin_menu_id'] = $menu->id;
		Utils::$context['admin_menu_name'] = $menu->name;

		// Where in the admin are we?
		Utils::$context['admin_area'] = $menu->current_area;

		// Now - finally - call the right place!
		if (isset($menu->include_data['file']))
			require_once(Config::$sourcedir . '/' . $menu->include_data['file']);

		// Get the right callable.
		$call = call_helper($menu->include_data['function'], true);

		// Is it valid?
		if (!empty($call))
			call_user_func($call);
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
	 * Get a list of versions that are currently installed on the server.
	 *
	 * @param array $checkFor An array of what to check versions for - can contain one or more of 'gd', 'imagemagick', 'db_server', 'phpa', 'memcache', 'php' or 'server'
	 * @return array An array of versions (keys are same as what was in $checkFor, values are the versions)
	 */
	public static function getServerVersions($checkFor)
	{
		Lang::load('Admin');
		Lang::load('ManageSettings');

		$versions = array();

		// Is GD available?  If it is, we should show version information for it too.
		if (in_array('gd', $checkFor) && function_exists('gd_info'))
		{
			$temp = gd_info();
			$versions['gd'] = array('title' => Lang::$txt['support_versions_gd'], 'version' => $temp['GD Version']);
		}

		// Why not have a look at ImageMagick? If it's installed, we should show version information for it too.
		if (in_array('imagemagick', $checkFor) && (class_exists('Imagick') || function_exists('MagickGetVersionString')))
		{
			if (class_exists('Imagick'))
			{
				$temp = new \Imagick();
				$temp2 = $temp->getVersion();
				$im_version = $temp2['versionString'];
				$extension_version = 'Imagick ' . phpversion('Imagick');
			}
			else
			{
				$im_version = MagickGetVersionString();
				$extension_version = 'MagickWand ' . phpversion('MagickWand');
			}

			// We already know it's ImageMagick and the website isn't needed...
			$im_version = str_replace(array('ImageMagick ', ' https://www.imagemagick.org'), '', $im_version);

			$versions['imagemagick'] = array('title' => Lang::$txt['support_versions_imagemagick'], 'version' => $im_version . ' (' . $extension_version . ')');
		}

		// Now lets check for the Database.
		if (in_array('db_server', $checkFor))
		{
			if (!isset(Db::$db_connection) || Db::$db_connection === false)
			{
				Lang::load('Errors');
				trigger_error(Lang::$txt['get_server_versions_no_database'], E_USER_NOTICE);
			}
			else
			{
				$versions['db_engine'] = array(
					'title' => sprintf(Lang::$txt['support_versions_db_engine'], Db::$db->title),
					'version' => Db::$db->get_vendor(),
				);

				$versions['db_server'] = array(
					'title' => sprintf(Lang::$txt['support_versions_db'], Db::$db->title),
					'version' => Db::$db->get_version(),
				);
			}
		}

		// Check to see if we have any accelerators installed.
		foreach (CacheApi::detect() as $class_name => $cache_api)
		{
			$class_name_txt_key = strtolower($cache_api->getImplementationClassKeyName());

			if (in_array($class_name_txt_key, $checkFor))
			{
				$versions[$class_name_txt_key] = array(
					'title' => isset(Lang::$txt[$class_name_txt_key . '_cache']) ?
						Lang::$txt[$class_name_txt_key . '_cache'] : $class_name,
					'version' => $cache_api->getVersion(),
				);
			}
		}

		if (in_array('php', $checkFor))
		{
			$versions['php'] = array(
				'title' => 'PHP',
				'version' => PHP_VERSION,
				'more' => '?action=admin;area=serversettings;sa=phpinfo',
			);
		}

		if (in_array('server', $checkFor))
		{
			$versions['server'] = array(
				'title' => Lang::$txt['support_versions_server'],
				'version' => $_SERVER['SERVER_SOFTWARE'],
			);
		}

		return $versions;
	}

	/**
	 * Search through source, theme, and language files to determine their version.
	 * Get detailed version information about the physical SMF files on the server.
	 *
	 * - the input parameter allows to set whether to include SSI.php and whether
	 *   the results should be sorted.
	 * - returns an array containing information on source files, templates, and
	 *   language files found in the default theme directory (grouped by language).
	 *
	 * @param array &$versionOptions An array of options. Can contain one or more of 'include_ssi', 'include_subscriptions', 'include_tasks' and 'sort_results'
	 * @return array An array of file version info.
	 */
	public static function getFileVersions(&$versionOptions)
	{
		// Default place to find the languages would be the default theme dir.
		$lang_dir = Theme::$current->settings['default_theme_dir'] . '/languages';

		$version_info = array(
			'file_versions' => array(),
			'default_template_versions' => array(),
			'template_versions' => array(),
			'default_language_versions' => array(),
			'tasks_versions' => array(),
		);

		// Find the version in SSI.php's file header.
		if (!empty($versionOptions['include_ssi']) && file_exists(Config::$boarddir . '/SSI.php'))
		{
			$fp = fopen(Config::$boarddir . '/SSI.php', 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			// The comment looks rougly like... that.
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$version_info['file_versions']['SSI.php'] = $match[1];
			// Not found!  This is bad.
			else
				$version_info['file_versions']['SSI.php'] = '??';
		}

		// Do the paid subscriptions handler?
		if (!empty($versionOptions['include_subscriptions']) && file_exists(Config::$boarddir . '/subscriptions.php'))
		{
			$fp = fopen(Config::$boarddir . '/subscriptions.php', 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			// Found it?
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			{
				$version_info['file_versions']['subscriptions.php'] = $match[1];
			}
			// If we haven't how do we all get paid?
			else
			{
				$version_info['file_versions']['subscriptions.php'] = '??';
			}
		}

		// Load all the files in the Sources directory, except this file and the redirect.
		$sources_dir = dir(Config::$sourcedir);
		while ($entry = $sources_dir->read())
		{
			if (substr($entry, -4) === '.php' && !is_dir(Config::$sourcedir . '/' . $entry) && $entry !== 'index.php')
			{
				// Read the first 4k from the file.... enough for the header.
				$fp = fopen(Config::$sourcedir . '/' . $entry, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				// Look for the version comment in the file header.
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				{
					$version_info['file_versions'][$entry] = $match[1];
				}
				// It wasn't found, but the file was... show a '??'.
				else
				{
					$version_info['file_versions'][$entry] = '??';
				}
			}
		}
		$sources_dir->close();

		// Load all the files in the tasks directory.
		if (!empty($versionOptions['include_tasks']))
		{
			$tasks_dir = dir(Config::$tasksdir);

			while ($entry = $tasks_dir->read())
			{
				if (substr($entry, -4) === '.php' && !is_dir(Config::$tasksdir . '/' . $entry) && $entry !== 'index.php')
				{
					// Read the first 4k from the file.... enough for the header.
					$fp = fopen(Config::$tasksdir . '/' . $entry, 'rb');
					$header = fread($fp, 4096);
					fclose($fp);

					// Look for the version comment in the file header.
					if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					{
						$version_info['tasks_versions'][$entry] = $match[1];
					}
					// It wasn't found, but the file was... show a '??'.
					else
					{
						$version_info['tasks_versions'][$entry] = '??';
					}
				}
			}
			$tasks_dir->close();
		}

		// Load all the files in the default template directory - and the current theme if applicable.
		$directories = array('default_template_versions' => Theme::$current->settings['default_theme_dir']);

		if (Theme::$current->settings['theme_id'] != 1)
		{
			$directories += array('template_versions' => Theme::$current->settings['theme_dir']);
		}

		foreach ($directories as $type => $dirname)
		{
			$this_dir = dir($dirname);

			while ($entry = $this_dir->read())
			{
				if (substr($entry, -12) == 'template.php' && !is_dir($dirname . '/' . $entry))
				{
					// Read the first 768 bytes from the file.... enough for the header.
					$fp = fopen($dirname . '/' . $entry, 'rb');
					$header = fread($fp, 768);
					fclose($fp);

					// Look for the version comment in the file header.
					if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					{
						$version_info[$type][$entry] = $match[1];
					}
					// It wasn't found, but the file was... show a '??'.
					else
					{
						$version_info[$type][$entry] = '??';
					}
				}
			}
			$this_dir->close();
		}

		// Load up all the files in the default language directory and sort by language.
		$this_dir = dir($lang_dir);

		while ($entry = $this_dir->read())
		{
			if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($lang_dir . '/' . $entry))
			{
				// Read the first 768 bytes from the file.... enough for the header.
				$fp = fopen($lang_dir . '/' . $entry, 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Split the file name off into useful bits.
				list ($name, $language) = explode('.', $entry);

				// Look for the version comment in the file header.
				if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				{
					$version_info['default_language_versions'][$language][$name] = $match[1];
				}
				// It wasn't found, but the file was... show a '??'.
				else
				{
					$version_info['default_language_versions'][$language][$name] = '??';
				}
			}
		}

		$this_dir->close();

		// Sort the file versions by filename.
		if (!empty($versionOptions['sort_results']))
		{
			ksort($version_info['file_versions']);
			ksort($version_info['default_template_versions']);
			ksort($version_info['template_versions']);
			ksort($version_info['default_language_versions']);
			ksort($version_info['tasks_versions']);

			// For languages sort each language too.
			foreach ($version_info['default_language_versions'] as $language => $dummy)
				ksort($version_info['default_language_versions'][$language]);
		}

		return $version_info;
	}

	/**
	 * Saves the admin's current preferences to the database.
	 */
	public static function updateAdminPreferences()
	{
		// This must exist!
		if (!isset(Utils::$context['admin_preferences']))
			return false;

		// This is what we'll be saving.
		Theme::$current->options['admin_preferences'] = Utils::jsonEncode(Utils::$context['admin_preferences']);

		// Just check we haven't ended up with something theme exclusive somehow.
		Db::$db->query('', '
			DELETE FROM {db_prefix}themes
			WHERE id_theme != {int:default_theme}
				AND variable = {string:admin_preferences}',
			array(
				'default_theme' => 1,
				'admin_preferences' => 'admin_preferences',
			)
		);

		// Update the themes table.
		Db::$db->insert('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			array(User::$me->id, 1, 'admin_preferences', Theme::$current->options['admin_preferences']),
			array('id_member', 'id_theme', 'variable')
		);

		// Make sure we invalidate any cache.
		CacheApi::put('theme_settings-' . Theme::$current->settings['theme_id'] . ':' . User::$me->id, null, 0);
	}

	/**
	 * Send all the administrators a lovely email.
	 * - loads all users who are admins or have the admin forum permission.
	 * - uses the email template and replacements passed in the parameters.
	 * - sends them an email.
	 *
	 * @param string $template Which email template to use
	 * @param array $replacements An array of items to replace the variables in the template
	 * @param array $additional_recipients An array of arrays of info for additional recipients. Should have 'id', 'email' and 'name' for each.
	 */
	public static function emailAdmins($template, $replacements = array(), $additional_recipients = array())
	{
		// Load all members which are effectively admins.
		require_once(Config::$sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('admin_forum');

		// Load their alert preferences
		$prefs = Notify::getNotifyPrefs($members, 'announcements', true);

		$emails_sent = array();

		$request = Db::$db->query('', '
			SELECT id_member, member_name, real_name, lngfile, email_address
			FROM {db_prefix}members
			WHERE id_member IN({array_int:members})',
			array(
				'members' => $members,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (empty($prefs[$row['id_member']]['announcements']))
				continue;

			// Stick their particulars in the replacement data.
			$replacements['IDMEMBER'] = $row['id_member'];
			$replacements['REALNAME'] = $row['member_name'];
			$replacements['USERNAME'] = $row['real_name'];

			// Load the data from the template.
			$emaildata = Mail::loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile']);

			// Then send the actual email.
			Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);

			// Track who we emailed so we don't do it twice.
			$emails_sent[] = $row['email_address'];
		}
		Db::$db->free_result($request);

		// Any additional users we must email this to?
		if (!empty($additional_recipients))
		{
			foreach ($additional_recipients as $recipient)
			{
				if (in_array($recipient['email'], $emails_sent))
					continue;

				$replacements['IDMEMBER'] = $recipient['id'];
				$replacements['REALNAME'] = $recipient['name'];
				$replacements['USERNAME'] = $recipient['name'];

				// Load the template again.
				$emaildata = Mail::loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $recipient['lang']);

				// Send off the email.
				Mail::send($recipient['email'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);
			}
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Load the language and templates....
		Lang::load('Admin');
		Theme::loadTemplate('Admin');
		Theme::loadJavaScriptFile('admin.js', array('minimize' => true), 'smf_admin');
		Theme::loadCSSFile('admin.css', array(), 'smf_admin');

		// Set any dynamic values in $this->admin_areas.
		$this->setAdminAreas();

		// No indexing evil stuff.
		Utils::$context['robot_no_index'] = true;

		// Some preferences.
		Utils::$context['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : array();

		// Any files to include for administration?
		if (!empty(Config::$modSettings['integrate_admin_include']))
		{
			$admin_includes = explode(',', Config::$modSettings['integrate_admin_include']);

			foreach ($admin_includes as $include)
			{
				$include = strtr(trim($include), array(
					'$boarddir' => Config::$boarddir,
					'$sourcedir' => Config::$sourcedir,
					'$themedir' => Theme::$current->settings['theme_dir'],
				));

				if (file_exists($include))
					require_once($include);
			}
		}
	}

	/**
	 * Sets any dynamic values in $this->admin_areas.
	 */
	protected function setAdminAreas(): void
	{
		// Finalize various string values.
		array_walk_recursive(
			$this->admin_areas,
			function(&$value, $key)
			{
				if (in_array($key, array('title', 'label')))
					$value = Lang::$txt[$value] ?? $value;

				$value = strtr($value, array(
					'{scripturl}' => Config::$scripturl,
					'{boardurl}' => Config::$boardurl,
				));
			}
		);

		// Fill in the ID number for the current theme URL.
		$this->admin_areas['config']['areas']['current_theme']['custom_url'] = sprintf($this->admin_areas['config']['areas']['current_theme']['custom_url'], Theme::$current->settings['theme_id']);

		// Figure out what is enabled or not.
		$this->admin_areas['forum']['areas']['adminlogoff']['enabled'] = empty(Config::$modSettings['securityDisable']);

		if (empty(Config::$modSettings['cal_enabled']))
		{
			$this->admin_areas['layout']['areas']['managecalendar']['inactive'] = true;
			$this->admin_areas['layout']['areas']['managecalendar']['subsections'] = array();
		}

		$this->admin_areas['layout']['areas']['smileys']['subsections']['addsmiley']['enabled'] = !empty(Config::$modSettings['smiley_enable']);
		$this->admin_areas['layout']['areas']['smileys']['subsections']['editsmileys']['enabled'] = !empty(Config::$modSettings['smiley_enable']);
		$this->admin_areas['layout']['areas']['smileys']['subsections']['setorder']['enabled'] = !empty(Config::$modSettings['smiley_enable']);
		$this->admin_areas['layout']['areas']['smileys']['subsections']['editicons']['enabled'] = !empty(Config::$modSettings['messageIcons_enable']);

		if (empty(Config::$modSettings['spider_mode']))
		{
			$this->admin_areas['layout']['areas']['sengines']['inactive'] = true;
			$this->admin_areas['layout']['areas']['sengines']['subsections'] = array();
		}

		$this->admin_areas['members']['areas']['warnings']['inactive'] = Config::$modSettings['warning_settings'][0] == 0;

		if (empty(Config::$modSettings['paid_enabled']))
		{
			$this->admin_areas['members']['areas']['paidsubscribe']['inactive'] = true;
			$this->admin_areas['members']['areas']['paidsubscribe']['subsections'] = array();
		}

		$this->admin_areas['maintenance']['areas']['logs']['subsections']['errorlog']['enabled'] = !empty(Config::$modSettings['enableErrorLogging']);
		$this->admin_areas['maintenance']['areas']['logs']['subsections']['adminlog']['enabled'] = !empty(Config::$modSettings['adminlog_enabled']);
		$this->admin_areas['maintenance']['areas']['logs']['subsections']['modlog']['enabled'] = !empty(Config::$modSettings['modlog_enabled']);
		$this->admin_areas['maintenance']['areas']['logs']['subsections']['spiderlog']['enabled'] = !empty(Config::$modSettings['spider_mode']);

		// Give mods access to the menu.
		call_integration_hook('integrate_admin_areas', array(&$this->admin_areas));
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\ACP::exportStatic'))
	ACP::exportStatic();

?>