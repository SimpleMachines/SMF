<?php

/**
 * This file, unpredictable as this might be, handles basic administration.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Credits;
use SMF\Db\DatabaseApi as Db;
use SMF\PackageManager\XmlArray;

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main admin handling function.<br>
 * It initialises all the basic context required for the admin center.<br>
 * It passes execution onto the relevant admin section.<br>
 * If the passed section is not found it shows the admin home page.
 */
function AdminMain()
{
	// Load the language and templates....
	Lang::load('Admin');
	Theme::loadTemplate('Admin');
	Theme::loadJavaScriptFile('admin.js', array('minimize' => true), 'smf_admin');
	Theme::loadCSSFile('admin.css', array(), 'smf_admin');

	// No indexing evil stuff.
	Utils::$context['robot_no_index'] = true;

	// Some preferences.
	Utils::$context['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : array();

	/** @var array $admin_areas Defines the menu structure for the admin center. See {@link Menu.php Menu.php} for details! */
	$admin_areas = array(
		'forum' => array(
			'title' => Lang::$txt['admin_main'],
			'permission' => array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'),
			'areas' => array(
				'index' => array(
					'label' => Lang::$txt['admin_center'],
					'function' => '\\SMF\\Actions\\Admin\\Home::call',
					'icon' => 'administration',
				),
				'credits' => array(
					'label' => Lang::$txt['support_credits_title'],
					'function' => '\\SMF\\Actions\\Admin\\Home::call',
					'icon' => 'support',
				),
				'news' => array(
					'label' => Lang::$txt['news_title'],
					'file' => 'ManageNews.php',
					'function' => 'ManageNews',
					'icon' => 'news',
					'permission' => array('edit_news', 'send_mail', 'admin_forum'),
					'subsections' => array(
						'editnews' => array(
							'label' => Lang::$txt['admin_edit_news'],
							'permission' => 'edit_news',
						),
						'mailingmembers' => array(
							'label' => Lang::$txt['admin_newsletters'],
							'permission' => 'send_mail',
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'packages' => array(
					'label' => Lang::$txt['package'],
					'function' => 'SMF\\PackageManager\\PackageManager::call',
					'permission' => array('admin_forum'),
					'icon' => 'packages',
					'subsections' => array(
						'browse' => array(
							'label' => Lang::$txt['browse_packages'],
						),
						'packageget' => array(
							'label' => Lang::$txt['download_packages'],
							'url' => Config::$scripturl . '?action=admin;area=packages;sa=packageget;get',
						),
						'perms' => array(
							'label' => Lang::$txt['package_file_perms'],
						),
						'options' => array(
							'label' => Lang::$txt['package_settings'],
						),
					),
				),
				'search' => array(
					'function' => 'AdminSearch',
					'permission' => array('admin_forum'),
					'select' => 'index'
				),
				'adminlogoff' => array(
					'label' => Lang::$txt['admin_logoff'],
					'function' => 'AdminEndSession',
					'enabled' => empty(Config::$modSettings['securityDisable']),
					'icon' => 'exit',
				),

			),
		),
		'config' => array(
			'title' => Lang::$txt['admin_config'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'featuresettings' => array(
					'label' => Lang::$txt['modSettings_title'],
					'file' => 'ManageSettings.php',
					'function' => 'ModifyFeatureSettings',
					'icon' => 'features',
					'subsections' => array(
						'basic' => array(
							'label' => Lang::$txt['mods_cat_features'],
						),
						'bbc' => array(
							'label' => Lang::$txt['manageposts_bbc_settings'],
						),
						'layout' => array(
							'label' => Lang::$txt['mods_cat_layout'],
						),
						'sig' => array(
							'label' => Lang::$txt['signature_settings_short'],
						),
						'profile' => array(
							'label' => Lang::$txt['custom_profile_shorttitle'],
						),
						'likes' => array(
							'label' => Lang::$txt['likes'],
						),
						'mentions' => array(
							'label' => Lang::$txt['mentions'],
						),
						'alerts' => array(
							'label' => Lang::$txt['notifications'],
						),
					),
				),
				'antispam' => array(
					'label' => Lang::$txt['antispam_title'],
					'file' => 'ManageSettings.php',
					'function' => 'ModifyAntispamSettings',
					'icon' => 'security',
				),
				'languages' => array(
					'label' => Lang::$txt['language_configuration'],
					'file' => 'ManageLanguages.php',
					'function' => 'ManageLanguages',
					'icon' => 'languages',
					'subsections' => array(
						'edit' => array(
							'label' => Lang::$txt['language_edit'],
						),
						'add' => array(
							'label' => Lang::$txt['language_add'],
						),
						'settings' => array(
							'label' => Lang::$txt['language_settings'],
						),
					),
				),
				'current_theme' => array(
					'label' => Lang::$txt['theme_current_settings'],
					'file' => 'ManageThemes.php',
					'function' => 'ThemesMain',
					'custom_url' => Config::$scripturl . '?action=admin;area=theme;sa=list;th=' . Theme::$current->settings['theme_id'],
					'icon' => 'current_theme',
				),
				'theme' => array(
					'label' => Lang::$txt['theme_admin'],
					'file' => 'ManageThemes.php',
					'function' => 'ThemesMain',
					'custom_url' => Config::$scripturl . '?action=admin;area=theme',
					'icon' => 'themes',
					'subsections' => array(
						'admin' => array(
							'label' => Lang::$txt['themeadmin_admin_title'],
						),
						'list' => array(
							'label' => Lang::$txt['themeadmin_list_title'],
						),
						'reset' => array(
							'label' => Lang::$txt['themeadmin_reset_title'],
						),
						'edit' => array(
							'label' => Lang::$txt['themeadmin_edit_title'],
						),
					),
				),
				'modsettings' => array(
					'label' => Lang::$txt['admin_modifications'],
					'file' => 'ManageSettings.php',
					'function' => 'ModifyModSettings',
					'icon' => 'modifications',
					'subsections' => array(
						'general' => array(
							'label' => Lang::$txt['mods_cat_modifications_misc'],
						),
						// Mod Authors for a "ADD AFTER" on this line. Ensure you end your change with a comma. For example:
						// 'shout' => array(Lang::$txt['shout']),
						// Note the comma!! The setting with automatically appear with the first mod to be added.
					),
				),
			),
		),
		'layout' => array(
			'title' => Lang::$txt['layout_controls'],
			'permission' => array('manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum'),
			'areas' => array(
				'manageboards' => array(
					'label' => Lang::$txt['admin_boards'],
					'file' => 'ManageBoards.php',
					'function' => 'ManageBoards',
					'icon' => 'boards',
					'permission' => array('manage_boards'),
					'subsections' => array(
						'main' => array(
							'label' => Lang::$txt['boards_edit'],
						),
						'newcat' => array(
							'label' => Lang::$txt['mboards_new_cat'],
						),
						'settings' => array(
							'label' => Lang::$txt['settings'], 'admin_forum',
						),
					),
				),
				'postsettings' => array(
					'label' => Lang::$txt['manageposts'],
					'file' => 'ManagePosts.php',
					'function' => 'ManagePostSettings',
					'permission' => array('admin_forum'),
					'icon' => 'posts',
					'subsections' => array(
						'posts' => array(
							'label' => Lang::$txt['manageposts_settings'],
						),
						'censor' => array(
							'label' => Lang::$txt['admin_censored_words'],
						),
						'topics' => array(
							'label' => Lang::$txt['manageposts_topic_settings'],
						),
						'drafts' => array(
							'label' => Lang::$txt['manage_drafts'],
						),
					),
				),
				'managecalendar' => array(
					'label' => Lang::$txt['manage_calendar'],
					'file' => 'ManageCalendar.php',
					'function' => 'ManageCalendar',
					'icon' => 'calendar',
					'permission' => array('admin_forum'),
					'inactive' => empty(Config::$modSettings['cal_enabled']),
					'subsections' => empty(Config::$modSettings['cal_enabled']) ? array() : array(
						'holidays' => array(
							'label' => Lang::$txt['manage_holidays'],
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => Lang::$txt['calendar_settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'managesearch' => array(
					'label' => Lang::$txt['manage_search'],
					'file' => 'ManageSearch.php',
					'function' => 'ManageSearch',
					'icon' => 'search',
					'permission' => array('admin_forum'),
					'subsections' => array(
						'weights' => array(
							'label' => Lang::$txt['search_weights'],
						),
						'method' => array(
							'label' => Lang::$txt['search_method'],
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
						),
					),
				),
				'smileys' => array(
					'label' => Lang::$txt['smileys_manage'],
					'file' => 'ManageSmileys.php',
					'function' => 'ManageSmileys',
					'icon' => 'smiley',
					'permission' => array('manage_smileys'),
					'subsections' => array(
						'editsets' => array(
							'label' => Lang::$txt['smiley_sets'],
						),
						'addsmiley' => array(
							'label' => Lang::$txt['smileys_add'],
							'enabled' => !empty(Config::$modSettings['smiley_enable']),
						),
						'editsmileys' => array(
							'label' => Lang::$txt['smileys_edit'],
							'enabled' => !empty(Config::$modSettings['smiley_enable']),
						),
						'setorder' => array(
							'label' => Lang::$txt['smileys_set_order'],
							'enabled' => !empty(Config::$modSettings['smiley_enable']),
						),
						'editicons' => array(
							'label' => Lang::$txt['icons_edit_message_icons'],
							'enabled' => !empty(Config::$modSettings['messageIcons_enable']),
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
						),
					),
				),
				'manageattachments' => array(
					'label' => Lang::$txt['attachments_avatars'],
					'file' => 'ManageAttachments.php',
					'function' => 'ManageAttachments',
					'icon' => 'attachment',
					'permission' => array('manage_attachments'),
					'subsections' => array(
						'browse' => array(
							'label' => Lang::$txt['attachment_manager_browse'],
						),
						'attachments' => array(
							'label' => Lang::$txt['attachment_manager_settings'],
						),
						'avatars' => array(
							'label' => Lang::$txt['attachment_manager_avatar_settings'],
						),
						'attachpaths' => array(
							'label' => Lang::$txt['attach_directories'],
						),
						'maintenance' => array(
							'label' => Lang::$txt['attachment_manager_maintenance'],
						),
					),
				),
				'sengines' => array(
					'label' => Lang::$txt['search_engines'],
					'inactive' => empty(Config::$modSettings['spider_mode']),
					'file' => 'ManageSearchEngines.php',
					'icon' => 'engines',
					'function' => 'SearchEngines',
					'permission' => 'admin_forum',
					'subsections' => empty(Config::$modSettings['spider_mode']) ? array() : array(
						'stats' => array(
							'label' => Lang::$txt['spider_stats'],
						),
						'logs' => array(
							'label' => Lang::$txt['spider_logs'],
						),
						'spiders' => array(
							'label' => Lang::$txt['spiders'],
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
						),
					),
				),
			),
		),
		'members' => array(
			'title' => Lang::$txt['admin_manage_members'],
			'permission' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'manage_permissions', 'admin_forum'),
			'areas' => array(
				'viewmembers' => array(
					'label' => Lang::$txt['admin_users'],
					'file' => 'ManageMembers.php',
					'function' => 'ViewMembers',
					'icon' => 'members',
					'permission' => array('moderate_forum'),
					'subsections' => array(
						'all' => array(
							'label' => Lang::$txt['view_all_members'],
						),
						'search' => array(
							'label' => Lang::$txt['mlist_search'],
						),
					),
				),
				'membergroups' => array(
					'label' => Lang::$txt['admin_groups'],
					'file' => 'ManageMembergroups.php',
					'function' => 'ModifyMembergroups',
					'icon' => 'membergroups',
					'permission' => array('manage_membergroups'),
					'subsections' => array(
						'index' => array(
							'label' => Lang::$txt['membergroups_edit_groups'],
							'permission' => 'manage_membergroups',
						),
						'add' => array(
							'label' => Lang::$txt['membergroups_new_group'],
							'permission' => 'manage_membergroups',
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'permissions' => array(
					'label' => Lang::$txt['edit_permissions'],
					'file' => 'ManagePermissions.php',
					'function' => 'ModifyPermissions',
					'icon' => 'permissions',
					'permission' => array('manage_permissions'),
					'subsections' => array(
						'index' => array(
							'label' => Lang::$txt['permissions_groups'],
							'permission' => 'manage_permissions',
						),
						'board' => array(
							'label' => Lang::$txt['permissions_boards'],
							'permission' => 'manage_permissions',
						),
						'profiles' => array(
							'label' => Lang::$txt['permissions_profiles'],
							'permission' => 'manage_permissions',
						),
						'postmod' => array(
							'label' => Lang::$txt['permissions_post_moderation'],
							'permission' => 'manage_permissions',
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'regcenter' => array(
					'label' => Lang::$txt['registration_center'],
					'file' => 'ManageRegistration.php',
					'function' => 'RegCenter',
					'icon' => 'regcenter',
					'permission' => array('admin_forum', 'moderate_forum'),
					'subsections' => array(
						'register' => array(
							'label' => Lang::$txt['admin_browse_register_new'],
							'permission' => 'moderate_forum',
						),
						'agreement' => array(
							'label' => Lang::$txt['registration_agreement'],
							'permission' => 'admin_forum',
						),
						'policy' => array(
							'label' => Lang::$txt['privacy_policy'],
							'permission' => 'admin_forum',
						),
						'reservednames' => array(
							'label' => Lang::$txt['admin_reserved_set'],
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'warnings' => array(
					'label' => Lang::$txt['warnings'],
					'file' => 'ManageSettings.php',
					'function' => 'ModifyWarningSettings',
					'icon' => 'warning',
					'inactive' => Config::$modSettings['warning_settings'][0] == 0,
					'permission' => array('admin_forum'),
				),
				'ban' => array(
					'label' => Lang::$txt['ban_title'],
					'file' => 'ManageBans.php',
					'function' => 'Ban',
					'icon' => 'ban',
					'permission' => 'manage_bans',
					'subsections' => array(
						'list' => array(
							'label' => Lang::$txt['ban_edit_list'],
						),
						'add' => array(
							'label' => Lang::$txt['ban_add_new'],
						),
						'browse' => array(
							'label' => Lang::$txt['ban_trigger_browse'],
						),
						'log' => array(
							'label' => Lang::$txt['ban_log'],
						),
					),
				),
				'paidsubscribe' => array(
					'label' => Lang::$txt['paid_subscriptions'],
					'inactive' => empty(Config::$modSettings['paid_enabled']),
					'file' => 'ManagePaid.php',
					'icon' => 'paid',
					'function' => 'ManagePaidSubscriptions',
					'permission' => 'admin_forum',
					'subsections' => empty(Config::$modSettings['paid_enabled']) ? array() : array(
						'view' => array(
							'label' => Lang::$txt['paid_subs_view'],
						),
						'settings' => array(
							'label' => Lang::$txt['settings'],
						),
					),
				),
			),
		),
		'maintenance' => array(
			'title' => Lang::$txt['admin_maintenance'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'serversettings' => array(
					'label' => Lang::$txt['admin_server_settings'],
					'file' => 'ManageServer.php',
					'function' => 'ModifySettings',
					'icon' => 'server',
					'subsections' => array(
						'general' => array(
							'label' => Lang::$txt['general_settings'],
						),
						'database' => array(
							'label' => Lang::$txt['database_settings'],
						),
						'cookie' => array(
							'label' => Lang::$txt['cookies_sessions_settings'],
						),
						'security' => array(
							'label' => Lang::$txt['security_settings'],
						),
						'cache' => array(
							'label' => Lang::$txt['caching_settings'],
						),
						'export' => array(
							'label' => Lang::$txt['export_settings'],
						),
						'loads' => array(
							'label' => Lang::$txt['load_balancing_settings'],
						),
						'phpinfo' => array(
							'label' => Lang::$txt['phpinfo_settings'],
						),
					),
				),
				'maintain' => array(
					'label' => Lang::$txt['maintain_title'],
					'file' => 'ManageMaintenance.php',
					'icon' => 'maintain',
					'function' => 'ManageMaintenance',
					'subsections' => array(
						'routine' => array(
							'label' => Lang::$txt['maintain_sub_routine'],
							'permission' => 'admin_forum',
						),
						'database' => array(
							'label' => Lang::$txt['maintain_sub_database'],
							'permission' => 'admin_forum',
						),
						'members' => array(
							'label' => Lang::$txt['maintain_sub_members'],
							'permission' => 'admin_forum',
						),
						'topics' => array(
							'label' => Lang::$txt['maintain_sub_topics'],
							'permission' => 'admin_forum',
						),
						'hooks' => array(
							'label' => Lang::$txt['hooks_title_list'],
							'permission' => 'admin_forum',
						),
					),
				),
				'scheduledtasks' => array(
					'label' => Lang::$txt['maintain_tasks'],
					'file' => 'ManageScheduledTasks.php',
					'icon' => 'scheduled',
					'function' => 'ManageScheduledTasks',
					'subsections' => array(
						'tasks' => array(
							'label' => Lang::$txt['maintain_tasks'],
							'permission' => 'admin_forum',
						),
						'tasklog' => array(
							'label' => Lang::$txt['scheduled_log'],
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => Lang::$txt['scheduled_tasks_settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'mailqueue' => array(
					'label' => Lang::$txt['mailqueue_title'],
					'file' => 'ManageMail.php',
					'function' => 'ManageMail',
					'icon' => 'mail',
					'subsections' => array(
						'browse' => array(
							'label' => Lang::$txt['mailqueue_browse'],
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => Lang::$txt['mailqueue_settings'],
							'permission' => 'admin_forum',
						),
						'test' => array(
							'label' => Lang::$txt['mailqueue_test'],
							'permission' => 'admin_forum',
						),
					),
				),
				'reports' => array(
					'label' => Lang::$txt['generate_reports'],
					'file' => 'Reports.php',
					'function' => 'ReportsMain',
					'icon' => 'reports',
				),
				'logs' => array(
					'label' => Lang::$txt['logs'],
					'function' => 'AdminLogs',
					'icon' => 'logs',
					'subsections' => array(
						'errorlog' => array(
							'label' => Lang::$txt['errorlog'],
							'permission' => 'admin_forum',
							'enabled' => !empty(Config::$modSettings['enableErrorLogging']),
							'url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc',
						),
						'adminlog' => array(
							'label' => Lang::$txt['admin_log'],
							'permission' => 'admin_forum',
							'enabled' => !empty(Config::$modSettings['adminlog_enabled']),
						),
						'modlog' => array(
							'label' => Lang::$txt['moderation_log'],
							'permission' => 'admin_forum',
							'enabled' => !empty(Config::$modSettings['modlog_enabled']),
						),
						'banlog' => array(
							'label' => Lang::$txt['ban_log'],
							'permission' => 'manage_bans',
						),
						'spiderlog' => array(
							'label' => Lang::$txt['spider_logs'],
							'permission' => 'admin_forum',
							'enabled' => !empty(Config::$modSettings['spider_mode']),
						),
						'tasklog' => array(
							'label' => Lang::$txt['scheduled_log'],
							'permission' => 'admin_forum',
						),
						'settings' => array(
							'label' => Lang::$txt['log_settings'],
							'permission' => 'admin_forum',
						),
					),
				),
				'repairboards' => array(
					'label' => Lang::$txt['admin_repair'],
					'file' => 'RepairBoards.php',
					'function' => 'RepairBoards',
					'select' => 'maintain',
					'hidden' => true,
				),
			),
		),
	);

	// Any files to include for administration?
	if (!empty(Config::$modSettings['integrate_admin_include']))
	{
		$admin_includes = explode(',', Config::$modSettings['integrate_admin_include']);
		foreach ($admin_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => Theme::$current->settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Make sure the administrator has a valid session...
	validateSession();

	// Actually create the menu!
	$menu = new Menu($admin_areas, array('do_big_icons' => true));
	unset($admin_areas);

	// Nothing valid?
	if (empty($menu->include_data))
		fatal_lang_error('no_access', false);

	// Build the link tree.
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=admin',
		'name' => Lang::$txt['admin_center'],
	);
	if (isset($menu->current_area) && $menu->current_area != 'index')
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=admin;area=' . $menu->current_area . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'name' => $menu->include_data['label'],
		);
	if (!empty($menu->current_subsection) && $menu->include_data['subsections'][$menu->current_subsection]['label'] != $menu->include_data['label'])
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=admin;area=' . $menu->current_area . ';sa=' . $menu->current_subsection . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'name' => $menu->include_data['subsections'][$menu->current_subsection]['label'],
		);

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

/**
 * This function allocates out all the search stuff.
 */
function AdminSearch()
{
	isAllowedTo('admin_forum');

	// What can we search for?
	$subActions = array(
		'internal' => 'AdminSearchInternal',
		'online' => 'AdminSearchOM',
		'member' => 'AdminSearchMember',
	);

	Utils::$context['search_type'] = !isset($_REQUEST['search_type']) || !isset($subActions[$_REQUEST['search_type']]) ? 'internal' : $_REQUEST['search_type'];
	Utils::$context['search_term'] = isset($_REQUEST['search_term']) ? Utils::htmlspecialchars($_REQUEST['search_term'], ENT_QUOTES) : '';

	Utils::$context['sub_template'] = 'admin_search_results';
	Utils::$context['page_title'] = Lang::$txt['admin_search_results'];

	// Keep track of what the admin wants.
	if (empty(Utils::$context['admin_preferences']['sb']) || Utils::$context['admin_preferences']['sb'] != Utils::$context['search_type'])
	{
		Utils::$context['admin_preferences']['sb'] = Utils::$context['search_type'];

		// Update the preferences.
		require_once(Config::$sourcedir . '/Subs-Admin.php');
		updateAdminPreferences();
	}

	if (trim(Utils::$context['search_term']) == '')
		Utils::$context['search_results'] = array();
	else
		call_helper($subActions[Utils::$context['search_type']]);
}

/**
 * A complicated but relatively quick internal search.
 */
function AdminSearchInternal()
{
	// Try to get some more memory.
	setMemoryLimit('128M');

	// Load a lot of language files.
	$language_files = array(
		'Help', 'ManageMail', 'ManageSettings', 'ManageCalendar', 'ManageBoards', 'ManagePaid', 'ManagePermissions', 'Search',
		'Login', 'ManageSmileys', 'Drafts',
	);

	// All the files we need to include.
	$include_files = array(
		'ManageSettings', 'ManageBoards', 'ManageNews', 'ManageAttachments', 'ManageCalendar', 'ManageMail', 'ManagePaid', 'ManagePermissions',
		'ManagePosts', 'ManageRegistration', 'ManageSearch', 'ManageSearchEngines', 'ManageServer', 'ManageSmileys', 'ManageLanguages',
	);

	// This is a special array of functions that contain setting data - we query all these to simply pull all setting bits!
	$settings_search = array(
		array('ModifyBasicSettings', 'area=featuresettings;sa=basic'),
		array('ModifyBBCSettings', 'area=featuresettings;sa=bbc'),
		array('ModifyLayoutSettings', 'area=featuresettings;sa=layout'),
		array('ModifyLikesSettings', 'area=featuresettings;sa=likes'),
		array('ModifyMentionsSettings', 'area=featuresettings;sa=mentions'),
		array('ModifySignatureSettings', 'area=featuresettings;sa=sig'),
		array('ModifyAntispamSettings', 'area=antispam'),
		array('ModifyWarningSettings', 'area=warnings'),
		array('ModifyGeneralModSettings', 'area=modsettings;sa=general'),
		// Mod authors if you want to be "real freaking good" then add any setting pages for your mod BELOW this line!
		array('ManageAttachmentSettings', 'area=manageattachments;sa=attachments'),
		array('ManageAvatarSettings', 'area=manageattachments;sa=avatars'),
		array('ModifyCalendarSettings', 'area=managecalendar;sa=settings'),
		array('EditBoardSettings', 'area=manageboards;sa=settings'),
		array('ModifyMailSettings', 'area=mailqueue;sa=settings'),
		array('ModifyNewsSettings', 'area=news;sa=settings'),
		array('GeneralPermissionSettings', 'area=permissions;sa=settings'),
		array('ModifyPostSettings', 'area=postsettings;sa=posts'),
		array('ModifyTopicSettings', 'area=postsettings;sa=topics'),
		array('ModifyDraftSettings', 'area=postsettings;sa=drafts'),
		array('EditSearchSettings', 'area=managesearch;sa=settings'),
		array('EditSmileySettings', 'area=smileys;sa=settings'),
		array('ModifyGeneralSettings', 'area=serversettings;sa=general'),
		array('ModifyDatabaseSettings', 'area=serversettings;sa=database'),
		array('ModifyCookieSettings', 'area=serversettings;sa=cookie'),
		array('ModifyGeneralSecuritySettings', 'area=serversettings;sa=security'),
		array('ModifyCacheSettings', 'area=serversettings;sa=cache'),
		array('ModifyLanguageSettings', 'area=languages;sa=settings'),
		array('ModifyRegistrationSettings', 'area=regcenter;sa=settings'),
		array('ManageSearchEngineSettings', 'area=sengines;sa=settings'),
		array('ModifySubscriptionSettings', 'area=paidsubscribe;sa=settings'),
		array('ModifyLogSettings', 'area=logs;sa=settings'),
	);

	call_integration_hook('integrate_admin_search', array(&$language_files, &$include_files, &$settings_search));

	Lang::load(implode('+', $language_files));

	foreach ($include_files as $file)
		require_once(Config::$sourcedir . '/' . $file . '.php');

	/* This is the huge array that defines everything... it's a huge array of items formatted as follows:
		0 = Language index (Can be array of indexes) to search through for this setting.
		1 = URL for this indexes page.
		2 = Help index for help associated with this item (If different from 0)
	*/

	$search_data = array(
		// All the major sections of the forum.
		'sections' => array(
		),
		'settings' => array(
			array('COPPA', 'area=regcenter;sa=settings'),
			array('CAPTCHA', 'area=antispam'),
		),
	);

	// Go through the admin menu structure trying to find suitably named areas!
	foreach (Menu::$loaded['admin']['sections'] as $section)
	{
		foreach ($section['areas'] as $menu_key => $menu_item)
		{
			$search_data['sections'][] = array($menu_item['label'], 'area=' . $menu_key);
			if (!empty($menu_item['subsections']))
				foreach ($menu_item['subsections'] as $key => $sublabel)
				{
					if (isset($sublabel['label']))
						$search_data['sections'][] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
				}
		}
	}

	foreach ($settings_search as $setting_area)
	{
		// Get a list of their variables.
		$config_vars = call_user_func($setting_area[0], true);

		foreach ($config_vars as $var)
			if (!empty($var[1]) && !in_array($var[0], array('permissions', 'switch', 'desc')))
				$search_data['settings'][] = array($var[(isset($var[2]) && in_array($var[2], array('file', 'db'))) ? 0 : 1], $setting_area[1], 'alttxt' => (isset($var[2]) && in_array($var[2], array('file', 'db'))) || isset($var[3]) ? (in_array($var[2], array('file', 'db')) ? $var[1] : $var[3]) : '');
	}

	Utils::$context['page_title'] = Lang::$txt['admin_search_results'];
	Utils::$context['search_results'] = array();

	$search_term = strtolower(un_htmlspecialchars(Utils::$context['search_term']));
	// Go through all the search data trying to find this text!
	foreach ($search_data as $section => $data)
	{
		foreach ($data as $item)
		{
			$found = false;
			if (!is_array($item[0]))
				$item[0] = array($item[0]);
			foreach ($item[0] as $term)
			{
				if (stripos($term, $search_term) !== false || (isset(Lang::$txt[$term]) && stripos(Lang::$txt[$term], $search_term) !== false) || (isset(Lang::$txt['setting_' . $term]) && stripos(Lang::$txt['setting_' . $term], $search_term) !== false))
				{
					$found = $term;
					break;
				}
			}

			if ($found)
			{
				// Format the name - and remove any descriptions the entry may have.
				$name = isset(Lang::$txt[$found]) ? Lang::$txt[$found] : (isset(Lang::$txt['setting_' . $found]) ? Lang::$txt['setting_' . $found] : (!empty($item['alttxt']) ? $item['alttxt'] : $found));
				$name = preg_replace('~<(?:div|span)\sclass="smalltext">.+?</(?:div|span)>~', '', $name);

				Utils::$context['search_results'][] = array(
					'url' => (substr($item[1], 0, 4) == 'area' ? Config::$scripturl . '?action=admin;' . $item[1] : $item[1]) . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ((substr($item[1], 0, 4) == 'area' && $section == 'settings' ? '#' . $item[0][0] : '')),
					'name' => $name,
					'type' => $section,
					'help' => shorten_subject(isset($item[2]) ? strip_tags(Lang::$helptxt[$item[2]]) : (isset(Lang::$helptxt[$found]) ? strip_tags(Lang::$helptxt[$found]) : ''), 255),
				);
			}
		}
	}
}

/**
 * All this does is pass through to manage members.
 * {@see ViewMembers()}
 */
function AdminSearchMember()
{
	require_once(Config::$sourcedir . '/ManageMembers.php');
	$_REQUEST['sa'] = 'query';

	$_POST['membername'] = un_htmlspecialchars(Utils::$context['search_term']);
	$_POST['types'] = '';

	ViewMembers();
}

/**
 * This file allows the user to search the SM online manual for a little of help.
 */
function AdminSearchOM()
{
	Utils::$context['doc_apiurl'] = 'https://wiki.simplemachines.org/api.php';
	Utils::$context['doc_scripturl'] = 'https://wiki.simplemachines.org/smf/';

	// Set all the parameters search might expect.
	$postVars = explode(' ', Utils::$context['search_term']);

	// Encode the search data.
	foreach ($postVars as $k => $v)
		$postVars[$k] = urlencode($v);

	// This is what we will send.
	$postVars = implode('+', $postVars);

	// Get the results from the doc site.
	// Demo URL:
	// https://wiki.simplemachines.org/api.php?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=template+eval
	$search_results = fetch_web_data(Utils::$context['doc_apiurl'] . '?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=' . $postVars);

	// If we didn't get any xml back we are in trouble - perhaps the doc site is overloaded?
	if (!$search_results || preg_match('~<' . '\?xml\sversion="\d+\.\d+"\?' . '>\s*(<api\b[^>]*>.+?</api>)~is', $search_results, $matches) != true)
		fatal_lang_error('cannot_connect_doc_site');

	$search_results = $matches[1];

	// Otherwise we simply walk through the XML and stick it in context for display.
	Utils::$context['search_results'] = array();

	// Get the results loaded into an array for processing!
	$results = new XmlArray($search_results, false);

	// Move through the api layer.
	if (!$results->exists('api'))
		fatal_lang_error('cannot_connect_doc_site');

	// Are there actually some results?
	if ($results->exists('api/query/search/p'))
	{
		$relevance = 0;
		foreach ($results->set('api/query/search/p') as $result)
		{
			Utils::$context['search_results'][$result->fetch('@title')] = array(
				'title' => $result->fetch('@title'),
				'relevance' => $relevance++,
				'snippet' => str_replace('class=\'searchmatch\'', 'class="highlight"', un_htmlspecialchars($result->fetch('@snippet'))),
			);
		}
	}
}

/**
 * This function decides which log to load.
 */
function AdminLogs()
{
	// These are the logs they can load.
	$log_functions = array(
		'errorlog' => array('ManageErrors.php', 'ViewErrorLog'),
		'adminlog' => array('Modlog.php', 'ViewModlog', 'disabled' => empty(Config::$modSettings['adminlog_enabled'])),
		'modlog' => array('Modlog.php', 'ViewModlog', 'disabled' => empty(Config::$modSettings['modlog_enabled'])),
		'banlog' => array('ManageBans.php', 'BanLog'),
		'spiderlog' => array('ManageSearchEngines.php', 'SpiderLogs'),
		'tasklog' => array('ManageScheduledTasks.php', 'TaskLog'),
		'settings' => array('ManageSettings.php', 'ModifyLogSettings'),
	);

	// If it's not got a sa set it must have come here for first time, pretend error log should be reversed.
	if (!isset($_REQUEST['sa']))
		$_REQUEST['desc'] = true;

	// Setup some tab stuff.
	Menu::$loaded['admin']->tab_data = array(
		'title' => Lang::$txt['logs'],
		'help' => '',
		'description' => Lang::$txt['maintain_info'],
		'tabs' => array(
			'errorlog' => array(
				'url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc',
				'description' => sprintf(Lang::$txt['errorlog_desc'], Lang::$txt['remove']),
			),
			'adminlog' => array(
				'description' => Lang::$txt['admin_log_desc'],
			),
			'modlog' => array(
				'description' => Lang::$txt['moderation_log_desc'],
			),
			'banlog' => array(
				'description' => Lang::$txt['ban_log_description'],
			),
			'spiderlog' => array(
				'description' => Lang::$txt['spider_log_desc'],
			),
			'tasklog' => array(
				'description' => Lang::$txt['scheduled_log_desc'],
			),
			'settings' => array(
				'description' => Lang::$txt['log_settings_desc'],
			),
		),
	);

	call_integration_hook('integrate_manage_logs', array(&$log_functions));

	$subAction = isset($_REQUEST['sa']) && isset($log_functions[$_REQUEST['sa']]) && empty($log_functions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : 'errorlog';

	require_once(Config::$sourcedir . '/' . $log_functions[$subAction][0]);
	call_helper($log_functions[$subAction][1]);
}

/**
 * This ends a admin session, requiring authentication to access the ACP again.
 */
function AdminEndSession()
{
	// This is so easy!
	unset($_SESSION['admin_time']);

	// Clean any admin tokens as well.
	foreach ($_SESSION['token'] as $key => $token)
		if (strpos($key, '-admin') !== false)
			unset($_SESSION['token'][$key]);

	redirectexit();
}

?>