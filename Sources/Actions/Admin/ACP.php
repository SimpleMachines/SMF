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

use SMF\BBCodeParser;
use SMF\Config;
use SMF\Lang;
use SMF\Mail;
use SMF\Menu;
use SMF\MessageIndex;
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
					'function' => __NAMESPACE__ . '\\News::call',
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
					'function' => __NAMESPACE__ . '\\Features::call',
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
					'function' => __NAMESPACE__ . '\\AntiSpam::call',
					'icon' => 'security',
				),
				'languages' => array(
					'label' => 'language_configuration',
					'function' => __NAMESPACE__ . '\\Languages::call',
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
					'function' => __NAMESPACE__ . '\\Themes::call',
					'custom_url' => '{scripturl}?action=admin;area=theme;sa=list;th=%1$d',
					'icon' => 'current_theme',
				),
				'theme' => array(
					'label' => 'theme_admin',
					'function' => __NAMESPACE__ . '\\Themes::call',
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
					'function' => __NAMESPACE__ . '\\Mods::call',
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
					'function' => __NAMESPACE__ . '\\Boards::call',
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
					'function' => __NAMESPACE__ . '\\Posts::call',
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
					'function' => __NAMESPACE__ . '\\Calendar::call',
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
					'function' => __NAMESPACE__ . '\\Search::call',
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
					'function' => __NAMESPACE__ . '\\Permissions::call',
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
					'function' => __NAMESPACE__ . '\\Warnings::call',
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
					'function' => __NAMESPACE__ . '\\Server::call',
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
	 * Helper function, it sets up the context for database settings.
	 *
	 * @todo see rev. 10406 from 2.1-requests
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	public static function prepareDBSettingContext(&$config_vars): void
	{
		Lang::load('Help');

		if (isset($_SESSION['adm-save']))
		{
			if ($_SESSION['adm-save'] === true)
			{
				Utils::$context['saved_successful'] = true;
			}
			else
			{
				Utils::$context['saved_failed'] = $_SESSION['adm-save'];
			}

			unset($_SESSION['adm-save']);
		}

		Utils::$context['config_vars'] = array();
		$inlinePermissions = array();
		$bbcChoice = array();
		$board_list = false;

		foreach ($config_vars as $config_var)
		{
			// HR?
			if (!is_array($config_var))
			{
				Utils::$context['config_vars'][] = $config_var;
			}
			else
			{
				// If it has no name it doesn't have any purpose!
				if (empty($config_var[1]))
					continue;

				// Special case for inline permissions
				if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
				{
					$inlinePermissions[] = $config_var[1];
				}
				elseif ($config_var[0] == 'permissions')
				{
					continue;
				}

				if ($config_var[0] == 'boards')
					$board_list = true;

				// Are we showing the BBC selection box?
				if ($config_var[0] == 'bbc')
					$bbcChoice[] = $config_var[1];

				// We need to do some parsing of the value before we pass it in.
				if (isset(Config::$modSettings[$config_var[1]]))
				{
					switch ($config_var[0])
					{
						case 'select':
							$value = Config::$modSettings[$config_var[1]];
							break;

						case 'json':
							$value = Utils::htmlspecialchars(Utils::jsonEncode(Config::$modSettings[$config_var[1]]));
							break;

						case 'boards':
							$value = explode(',', Config::$modSettings[$config_var[1]]);
							break;

						default:
							$value = Utils::htmlspecialchars(Config::$modSettings[$config_var[1]]);
					}
				}
				else
				{
					// Darn, it's empty. What type is expected?
					switch ($config_var[0])
					{
						case 'int':
						case 'float':
							$value = 0;
							break;

						case 'select':
							$value = !empty($config_var['multiple']) ? Utils::jsonEncode(array()) : '';
							break;

						case 'boards':
							$value = array();
							break;

						default:
							$value = '';
					}
				}

				Utils::$context['config_vars'][$config_var[1]] = array(
					'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset(Lang::$txt[$config_var[1]]) ? Lang::$txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
					'help' => isset(Lang::$helptxt[$config_var[1]]) ? $config_var[1] : '',
					'type' => $config_var[0],
					'size' => !empty($config_var['size']) ? $config_var['size'] : (!empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0)),
					'data' => array(),
					'name' => $config_var[1],
					'value' => $value,
					'disabled' => false,
					'invalid' => !empty($config_var['invalid']),
					'javascript' => '',
					'var_message' => !empty($config_var['message']) && isset(Lang::$txt[$config_var['message']]) ? Lang::$txt[$config_var['message']] : '',
					'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
					'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
				);

				// Handle min/max/step if necessary
				if ($config_var[0] == 'int' || $config_var[0] == 'float')
				{
					// Default to a min of 0 if one isn't set
					if (isset($config_var['min']))
					{
						Utils::$context['config_vars'][$config_var[1]]['min'] = $config_var['min'];
					}
					else
					{
						Utils::$context['config_vars'][$config_var[1]]['min'] = 0;
					}

					if (isset($config_var['max']))
					{
						Utils::$context['config_vars'][$config_var[1]]['max'] = $config_var['max'];
					}

					if (isset($config_var['step']))
					{
						Utils::$context['config_vars'][$config_var[1]]['step'] = $config_var['step'];
					}
				}

				// If this is a select box handle any data.
				if (!empty($config_var[2]) && is_array($config_var[2]))
				{
					// If we allow multiple selections, we need to adjust a few things.
					if ($config_var[0] == 'select' && !empty($config_var['multiple']))
					{
						Utils::$context['config_vars'][$config_var[1]]['name'] .= '[]';

						Utils::$context['config_vars'][$config_var[1]]['value'] = !empty(Utils::$context['config_vars'][$config_var[1]]['value']) ? Utils::jsonDecode(Utils::$context['config_vars'][$config_var[1]]['value'], true) : array();
					}

					// If it's associative
					if (isset($config_var[2][0]) && is_array($config_var[2][0]))
					{
						Utils::$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
					}
					else
					{
						foreach ($config_var[2] as $key => $item)
							Utils::$context['config_vars'][$config_var[1]]['data'][] = array($key, $item);
					}

					if (empty($config_var['size']) && !empty($config_var['multiple']))
					{
						Utils::$context['config_vars'][$config_var[1]]['size'] = max(4, count($config_var[2]));
					}
				}

				// Finally allow overrides - and some final cleanups.
				foreach ($config_var as $k => $v)
				{
					if (!is_numeric($k))
					{
						if (substr($k, 0, 2) == 'on')
						{
							Utils::$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
						}
						else
						{
							Utils::$context['config_vars'][$config_var[1]][$k] = $v;
						}
					}

					// See if there are any other labels that might fit?
					if (isset(Lang::$txt['setting_' . $config_var[1]]))
					{
						Utils::$context['config_vars'][$config_var[1]]['label'] = Lang::$txt['setting_' . $config_var[1]];
					}
					elseif (isset(Lang::$txt['groups_' . $config_var[1]]))
					{
						Utils::$context['config_vars'][$config_var[1]]['label'] = Lang::$txt['groups_' . $config_var[1]];
					}
				}

				// Set the subtext in case it's part of the label.
				// @todo Temporary. Preventing divs inside label tags.
				$divPos = strpos(Utils::$context['config_vars'][$config_var[1]]['label'], '<div');

				if ($divPos !== false)
				{
					Utils::$context['config_vars'][$config_var[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr(Utils::$context['config_vars'][$config_var[1]]['label'], $divPos));

					Utils::$context['config_vars'][$config_var[1]]['label'] = substr(Utils::$context['config_vars'][$config_var[1]]['label'], 0, $divPos);
				}
			}
		}

		// If we have inline permissions we need to prep them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			Permissions::init_inline_permissions($inlinePermissions);
		}

		if ($board_list)
		{
			Utils::$context['board_list'] = MessageIndex::getBoardList();
		}

		// What about any BBC selection boxes?
		if (!empty($bbcChoice))
		{
			// What are the options, eh?
			$temp = BBCodeParser::getCodes();
			$bbcTags = array();

			foreach ($temp as $tag)
			{
				if (!isset($tag['require_parents']))
					$bbcTags[] = $tag['tag'];
			}

			$bbcTags = array_unique($bbcTags);

			// The number of columns we want to show the BBC tags in.
			$numColumns = isset(Utils::$context['num_bbc_columns']) ? Utils::$context['num_bbc_columns'] : 3;

			// Now put whatever BBC options we may have into context too!
			Utils::$context['bbc_sections'] = array();

			foreach ($bbcChoice as $bbcSection)
			{
				Utils::$context['bbc_sections'][$bbcSection] = array(
					'title' => isset(Lang::$txt['bbc_title_' . $bbcSection]) ? Lang::$txt['bbc_title_' . $bbcSection] : Lang::$txt['enabled_bbc_select'],
					'disabled' => empty(Config::$modSettings['bbc_disabled_' . $bbcSection]) ? array() : Config::$modSettings['bbc_disabled_' . $bbcSection],
					'all_selected' => empty(Config::$modSettings['bbc_disabled_' . $bbcSection]),
					'columns' => array(),
				);

				if ($bbcSection == 'legacyBBC')
				{
					$sectionTags = array_intersect(Utils::$context['legacy_bbc'], $bbcTags);
				}
				else
				{
					$sectionTags = array_diff($bbcTags, Utils::$context['legacy_bbc']);
				}

				$totalTags = count($sectionTags);
				$tagsPerColumn = ceil($totalTags / $numColumns);

				$col = 0;
				$i = 0;

				foreach ($sectionTags as $tag)
				{
					if ($i % $tagsPerColumn == 0 && $i != 0)
						$col++;

					Utils::$context['bbc_sections'][$bbcSection]['columns'][$col][] = array(
						'tag' => $tag,
						'show_help' => isset(Lang::$helptxt['tag_' . $tag]),
					);

					$i++;
				}
			}
		}

		call_integration_hook('integrate_prepare_db_settings', array(&$config_vars));
		createToken('admin-dbsc');
	}

	/**
	 * Helper function. Saves settings by putting them in Settings.php or saving them in the settings table.
	 *
	 * - Saves those settings set from ?action=admin;area=serversettings.
	 * - Requires the admin_forum permission.
	 * - Contains arrays of the types of data to save into Settings.php.
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	public static function saveSettings(&$config_vars): void
	{
		validateToken('admin-ssc');

		// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
		if (isset($_POST['cookiename']))
		{
			$_POST['cookiename'] = preg_replace('~[,;\s\.$]+~' . (Utils::$context['utf8'] ? 'u' : ''), '', $_POST['cookiename']);
		}

		// Fix the forum's URL if necessary.
		if (isset($_POST['boardurl']))
		{
			if (substr($_POST['boardurl'], -10) == '/index.php')
			{
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
			}
			elseif (substr($_POST['boardurl'], -1) == '/')
			{
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
			}

			if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			{
				$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
			}

			$_POST['boardurl'] = normalize_iri($_POST['boardurl']);
		}

		// Any passwords?
		$config_passwords = array();

		// All the numeric variables.
		$config_nums = array();

		// All the checkboxes
		$config_bools = array();

		// Ones that accept multiple types (should be rare)
		$config_multis = array();

		// Get all known setting definitions and assign them to our groups above.
		$settings_defs = Config::getSettingsDefs();
		foreach ($settings_defs as $var => $def)
		{
			if (!is_string($var))
				continue;

			if (!empty($def['is_password']))
			{
				$config_passwords[] = $var;
			}
			else
			{
				// Special handling if multiple types are allowed.
				if (is_array($def['type']))
				{
					// Obviously, we don't need null here.
					$def['type'] = array_filter(
						$def['type'],
						function ($type)
						{
							return $type !== 'NULL';
						}
					);

					$type = count($def['type']) == 1 ? reset($def['type']) : 'multiple';
				}
				else
				{
					$type = $def['type'];
				}

				switch ($type)
				{
					case 'multiple':
						$config_multis[$var] = $def['type'];

					case 'double':
						$config_nums[] = $var;
						break;

					case 'integer':
						// Some things saved as integers are presented as booleans
						foreach ($config_vars as $config_var)
						{
							if (is_array($config_var) && $config_var[0] == $var)
							{
								if ($config_var[3] == 'check')
								{
									$config_bools[] = $var;
									break 2;
								}
								else
								{
									break;
								}
							}
						}
						$config_nums[] = $var;
						break;

					case 'boolean':
						$config_bools[] = $var;
						break;

					default:
						break;
				}
			}
		}

		// Now sort everything into a big array, and figure out arrays and etc.
		$new_settings = array();
		// Figure out which config vars we're saving here...
		foreach ($config_vars as $config_var)
		{
			if (!is_array($config_var) || $config_var[2] != 'file')
				continue;

			$var_name = $config_var[0];

			// Unknown setting?
			if (!isset($settings_defs[$var_name]) && isset($config_var[3]))
			{
				switch ($config_var[3])
				{
					case 'int':
					case 'float':
						$config_nums[] = $var_name;
						break;

					case 'check':
						$config_bools[] = $var_name;
						break;

					default:
						break;
				}
			}

			if (!in_array($var_name, $config_bools) && !isset($_POST[$var_name]))
				continue;

			if (in_array($var_name, $config_passwords))
			{
				if (isset($_POST[$var_name][1]) && $_POST[$var_name][0] == $_POST[$var_name][1])
					$new_settings[$var_name] = $_POST[$var_name][0];
			}
			elseif (in_array($var_name, $config_nums))
			{
				$new_settings[$var_name] = (int) $_POST[$var_name];

				// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
				$min = isset($config_var['min']) ? $config_var['min'] : 0;
				$new_settings[$var_name] = max($min, $new_settings[$var_name]);

				// Is there a max value for this as well?
				if (isset($config_var['max']))
					$new_settings[$var_name] = min($config_var['max'], $new_settings[$var_name]);
			}
			elseif (in_array($var_name, $config_bools))
			{
				$new_settings[$var_name] = !empty($_POST[$var_name]);
			}
			elseif (isset($config_multis[$var_name]))
			{
				$is_acceptable_type = false;

				foreach ($config_multis[$var_name] as $type)
				{
					$temp = $_POST[$var_name];
					settype($temp, $type);

					if ($temp == $_POST[$var_name])
					{
						$new_settings[$var_name] = $temp;
						$is_acceptable_type = true;

						break;
					}
				}

				if (!$is_acceptable_type)
					fatal_error('Invalid config_var \'' . $var_name . '\'');
			}
			else
			{
				$new_settings[$var_name] = $_POST[$var_name];
			}
		}

		// Save the relevant settings in the Settings.php file.
		Config::updateSettingsFile($new_settings);

		// Now loop through the remaining (database-based) settings.
		$new_settings = array();
		foreach ($config_vars as $config_var)
		{
			// We just saved the file-based settings, so skip their definitions.
			if (!is_array($config_var) || $config_var[2] == 'file')
				continue;

			$new_setting = array($config_var[3], $config_var[0]);

			// Select options need carried over, too.
			if (isset($config_var[4]))
				$new_setting[] = $config_var[4];

			// Include min and max if necessary
			if (isset($config_var['min']))
				$new_setting['min'] = $config_var['min'];

			if (isset($config_var['max']))
				$new_setting['max'] = $config_var['max'];

			// Rewrite the definition a bit.
			$new_settings[] = $new_setting;
		}

		// Save the new database-based settings, if any.
		if (!empty($new_settings))
			ACP::saveDBSettings($new_settings);
	}

	/**
	 * Helper function for saving database settings.
	 *
	 * @todo see rev. 10406 from 2.1-requests
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	public static function saveDBSettings(&$config_vars)
	{
		static $board_list = null;

		validateToken('admin-dbsc');

		$inlinePermissions = array();

		foreach ($config_vars as $var)
		{
			if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && $var[0] != 'boards' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
			{
				continue;
			}

			// Checkboxes!
			if ($var[0] == 'check')
			{
				$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
			}
			// Select boxes!
			elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
			{
				$setArray[$var[1]] = $_POST[$var[1]];
			}
			elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != array())
			{
				// For security purposes we validate this line by line.
				$lOptions = array();

				foreach ($_POST[$var[1]] as $invar)
				{
					if (in_array($invar, array_keys($var[2])))
						$lOptions[] = $invar;
				}

				$setArray[$var[1]] = Utils::jsonEncode($lOptions);
			}
			// List of boards!
			elseif ($var[0] == 'boards')
			{
				// We just need a simple list of valid boards, nothing more.
				if ($board_list === null)
				{
					$board_list = array();
					$request = Db::$db->query('', '
						SELECT id_board
						FROM {db_prefix}boards'
					);
					while ($row = Db::$db->fetch_row($request))
					{
						$board_list[$row[0]] = true;
					}
					Db::$db->free_result($request);
				}

				$lOptions = array();

				if (!empty($_POST[$var[1]]))
				{
					foreach ($_POST[$var[1]] as $invar => $dummy)
					{
						if (isset($board_list[$invar]))
							$lOptions[] = $invar;
					}
				}

				$setArray[$var[1]] = !empty($lOptions) ? implode(',', $lOptions) : '';
			}
			// Integers!
			elseif ($var[0] == 'int')
			{
				$setArray[$var[1]] = (int) $_POST[$var[1]];

				// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
				$min = isset($var['min']) ? $var['min'] : 0;
				$setArray[$var[1]] = max($min, $setArray[$var[1]]);

				// Do we have a max value for this as well?
				if (isset($var['max']))
					$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
			}
			// Floating point!
			elseif ($var[0] == 'float')
			{
				$setArray[$var[1]] = (float) $_POST[$var[1]];

				// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
				$min = isset($var['min']) ? $var['min'] : 0;
				$setArray[$var[1]] = max($min, $setArray[$var[1]]);

				// Do we have a max value for this as well?
				if (isset($var['max']))
					$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
			}
			// Text!
			elseif (in_array($var[0], array('text', 'large_text', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time')))
			{
				$setArray[$var[1]] = $_POST[$var[1]];
			}
			// Passwords!
			elseif ($var[0] == 'password')
			{
				if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1])
					$setArray[$var[1]] = $_POST[$var[1]][0];
			}
			// BBC.
			elseif ($var[0] == 'bbc')
			{
				$bbcTags = array();
				foreach (BBCodeParser::getCodes() as $tag)
					$bbcTags[] = $tag['tag'];

				if (!isset($_POST[$var[1] . '_enabledTags']))
					$_POST[$var[1] . '_enabledTags'] = array();
				elseif (!is_array($_POST[$var[1] . '_enabledTags']))
					$_POST[$var[1] . '_enabledTags'] = array($_POST[$var[1] . '_enabledTags']);

				$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
			}
			// Permissions?
			elseif ($var[0] == 'permissions')
			{
				$inlinePermissions[] = $var[1];
			}
		}

		if (!empty($setArray))
			Config::updateModSettings($setArray);

		// If we have inline permissions we need to save them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			Permissions::save_inline_permissions($inlinePermissions);
		}
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