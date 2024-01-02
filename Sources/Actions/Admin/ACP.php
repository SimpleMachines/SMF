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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\MessageIndex;
use SMF\Actions\Notify;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Mail;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Url;
use SMF\User;
use SMF\Utils;

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
	private static $backcompat = [
		'func_names' => [
			'call' => 'AdminMain',
			'prepareDBSettingContext' => 'prepareDBSettingContext',
			'saveSettings' => 'saveSettings',
			'saveDBSettings' => 'saveDBSettings',
			'getServerVersions' => 'getServerVersions',
			'getFileVersions' => 'getFileVersions',
			'updateAdminPreferences' => 'updateAdminPreferences',
			'emailAdmins' => 'emailAdmins',
			'adminLogin' => 'adminLogin',
		],
	];

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
	public array $admin_areas = [
		'forum' => [
			'title' => 'admin_main',
			'permission' => [
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
			],
			'areas' => [
				'index' => [
					'label' => 'admin_center',
					'function' => __NAMESPACE__ . '\\Home::call',
					'icon' => 'administration',
				],
				'credits' => [
					'label' => 'support_credits_title',
					'function' => __NAMESPACE__ . '\\Home::call',
					'icon' => 'support',
				],
				'news' => [
					'label' => 'news_title',
					'function' => __NAMESPACE__ . '\\News::call',
					'icon' => 'news',
					'permission' => [
						'edit_news',
						'send_mail',
						'admin_forum',
					],
					'subsections' => [
						'editnews' => [
							'label' => 'admin_edit_news',
							'permission' => 'edit_news',
						],
						'mailingmembers' => [
							'label' => 'admin_newsletters',
							'permission' => 'send_mail',
						],
						'settings' => [
							'label' => 'settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'packages' => [
					'label' => 'package',
					'function' => 'SMF\\PackageManager\\PackageManager::call',
					'permission' => ['admin_forum'],
					'icon' => 'packages',
					'subsections' => [
						'browse' => [
							'label' => 'browse_packages',
						],
						'packageget' => [
							'label' => 'download_packages',
							'url' => '{scripturl}?action=admin;area=packages;sa=packageget;get',
						],
						'perms' => [
							'label' => 'package_file_perms',
						],
						'options' => [
							'label' => 'package_settings',
						],
					],
				],
				'search' => [
					'function' => __NAMESPACE__ . '\\Find::call',
					'permission' => ['admin_forum'],
					'select' => 'index',
				],
				'adminlogoff' => [
					'label' => 'admin_logoff',
					'function' => __NAMESPACE__ . '\\EndSession::call',
					'enabled' => true,
					'icon' => 'exit',
				],
			],
		],
		'config' => [
			'title' => 'admin_config',
			'permission' => ['admin_forum'],
			'areas' => [
				'featuresettings' => [
					'label' => 'modSettings_title',
					'function' => __NAMESPACE__ . '\\Features::call',
					'icon' => 'features',
					'subsections' => [
						'basic' => [
							'label' => 'mods_cat_features',
						],
						'bbc' => [
							'label' => 'manageposts_bbc_settings',
						],
						'layout' => [
							'label' => 'mods_cat_layout',
						],
						'sig' => [
							'label' => 'signature_settings_short',
						],
						'profile' => [
							'label' => 'custom_profile_shorttitle',
						],
						'likes' => [
							'label' => 'likes',
						],
						'mentions' => [
							'label' => 'mentions',
						],
						'alerts' => [
							'label' => 'notifications',
						],
					],
				],
				'antispam' => [
					'label' => 'antispam_title',
					'function' => __NAMESPACE__ . '\\AntiSpam::call',
					'icon' => 'security',
				],
				'languages' => [
					'label' => 'language_configuration',
					'function' => __NAMESPACE__ . '\\Languages::call',
					'icon' => 'languages',
					'subsections' => [
						'edit' => [
							'label' => 'language_edit',
						],
						'add' => [
							'label' => 'language_add',
						],
						'settings' => [
							'label' => 'language_settings',
						],
					],
				],
				'current_theme' => [
					'label' => 'theme_current_settings',
					'function' => __NAMESPACE__ . '\\Themes::call',
					'custom_url' => '{scripturl}?action=admin;area=theme;sa=list;th=%1$d',
					'icon' => 'current_theme',
				],
				'theme' => [
					'label' => 'theme_admin',
					'function' => __NAMESPACE__ . '\\Themes::call',
					'custom_url' => '{scripturl}?action=admin;area=theme',
					'icon' => 'themes',
					'subsections' => [
						'admin' => [
							'label' => 'themeadmin_admin_title',
						],
						'list' => [
							'label' => 'themeadmin_list_title',
						],
						'reset' => [
							'label' => 'themeadmin_reset_title',
						],
						'edit' => [
							'label' => 'themeadmin_edit_title',
						],
					],
				],
				'modsettings' => [
					'label' => 'admin_modifications',
					'function' => __NAMESPACE__ . '\\Mods::call',
					'icon' => 'modifications',
					'subsections' => [
						// MOD AUTHORS: If your mod has just a few simple
						// settings and doesn't need its own settings page, you
						// can use the integrate_general_mod_settings hook to
						// add them to the 'general' page.
						'general' => [
							'label' => 'mods_cat_modifications_misc',
						],
						// MOD AUTHORS: If your mod has a custom settings page,
						// use the integrate_admin_areas hook to insert it here.
					],
				],
			],
		],
		'layout' => [
			'title' => 'layout_controls',
			'permission' => ['manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum'],
			'areas' => [
				'manageboards' => [
					'label' => 'admin_boards',
					'function' => __NAMESPACE__ . '\\Boards::call',
					'icon' => 'boards',
					'permission' => ['manage_boards'],
					'subsections' => [
						'main' => [
							'label' => 'boards_edit',
						],
						'newcat' => [
							'label' => 'mboards_new_cat',
						],
						'settings' => [
							'label' => 'settings',
							'admin_forum',
						],
					],
				],
				'postsettings' => [
					'label' => 'manageposts',
					'function' => __NAMESPACE__ . '\\Posts::call',
					'permission' => ['admin_forum'],
					'icon' => 'posts',
					'subsections' => [
						'posts' => [
							'label' => 'manageposts_settings',
						],
						'censor' => [
							'label' => 'admin_censored_words',
						],
						'topics' => [
							'label' => 'manageposts_topic_settings',
						],
						'drafts' => [
							'label' => 'manage_drafts',
						],
					],
				],
				'managecalendar' => [
					'label' => 'manage_calendar',
					'function' => __NAMESPACE__ . '\\Calendar::call',
					'icon' => 'calendar',
					'permission' => ['admin_forum'],
					'inactive' => false,
					'subsections' => [
						'holidays' => [
							'label' => 'manage_holidays',
							'permission' => 'admin_forum',
						],
						'settings' => [
							'label' => 'calendar_settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'managesearch' => [
					'label' => 'manage_search',
					'function' => __NAMESPACE__ . '\\Search::call',
					'icon' => 'search',
					'permission' => ['admin_forum'],
					'subsections' => [
						'weights' => [
							'label' => 'search_weights',
						],
						'method' => [
							'label' => 'search_method',
						],
						'settings' => [
							'label' => 'settings',
						],
					],
				],
				'smileys' => [
					'label' => 'smileys_manage',
					'function' => __NAMESPACE__ . '\\Smileys::call',
					'icon' => 'smiley',
					'permission' => ['manage_smileys'],
					'subsections' => [
						'editsets' => [
							'label' => 'smiley_sets',
						],
						'addsmiley' => [
							'label' => 'smileys_add',
							'enabled' => true,
						],
						'editsmileys' => [
							'label' => 'smileys_edit',
							'enabled' => true,
						],
						'setorder' => [
							'label' => 'smileys_set_order',
							'enabled' => true,
						],
						'editicons' => [
							'label' => 'icons_edit_message_icons',
							'enabled' => true,
						],
						'settings' => [
							'label' => 'settings',
						],
					],
				],
				'manageattachments' => [
					'label' => 'attachments_avatars',
					'function' => __NAMESPACE__ . '\\Attachments::call',
					'icon' => 'attachment',
					'permission' => ['manage_attachments'],
					'subsections' => [
						'browse' => [
							'label' => 'attachment_manager_browse',
						],
						'attachments' => [
							'label' => 'attachment_manager_settings',
						],
						'avatars' => [
							'label' => 'attachment_manager_avatar_settings',
						],
						'attachpaths' => [
							'label' => 'attach_directories',
						],
						'maintenance' => [
							'label' => 'attachment_manager_maintenance',
						],
					],
				],
				'sengines' => [
					'label' => 'search_engines',
					'inactive' => false,
					'function' => __NAMESPACE__ . '\\SearchEngines::call',
					'icon' => 'engines',
					'permission' => 'admin_forum',
					'subsections' => [
						'stats' => [
							'label' => 'spider_stats',
						],
						'logs' => [
							'label' => 'spider_logs',
						],
						'spiders' => [
							'label' => 'spiders',
						],
						'settings' => [
							'label' => 'settings',
						],
					],
				],
			],
		],
		'members' => [
			'title' => 'admin_manage_members',
			'permission' => [
				'moderate_forum',
				'manage_membergroups',
				'manage_bans',
				'manage_permissions',
				'admin_forum',
			],
			'areas' => [
				'viewmembers' => [
					'label' => 'admin_users',
					'function' => __NAMESPACE__ . '\\Members::call',
					'icon' => 'members',
					'permission' => ['moderate_forum'],
					'subsections' => [
						'all' => [
							'label' => 'view_all_members',
						],
						'search' => [
							'label' => 'mlist_search',
						],
					],
				],
				'membergroups' => [
					'label' => 'admin_groups',
					'function' => __NAMESPACE__ . '\\Membergroups::call',
					'icon' => 'membergroups',
					'permission' => ['manage_membergroups'],
					'subsections' => [
						'index' => [
							'label' => 'membergroups_edit_groups',
							'permission' => 'manage_membergroups',
						],
						'add' => [
							'label' => 'membergroups_new_group',
							'permission' => 'manage_membergroups',
						],
						'settings' => [
							'label' => 'settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'permissions' => [
					'label' => 'edit_permissions',
					'function' => __NAMESPACE__ . '\\Permissions::call',
					'icon' => 'permissions',
					'permission' => ['manage_permissions'],
					'subsections' => [
						'index' => [
							'label' => 'permissions_groups',
							'permission' => 'manage_permissions',
						],
						'board' => [
							'label' => 'permissions_boards',
							'permission' => 'manage_permissions',
						],
						'profiles' => [
							'label' => 'permissions_profiles',
							'permission' => 'manage_permissions',
						],
						'postmod' => [
							'label' => 'permissions_post_moderation',
							'permission' => 'manage_permissions',
						],
						'settings' => [
							'label' => 'settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'regcenter' => [
					'label' => 'registration_center',
					'function' => __NAMESPACE__ . '\\Registration::call',
					'icon' => 'regcenter',
					'permission' => [
						'admin_forum',
						'moderate_forum',
					],
					'subsections' => [
						'register' => [
							'label' => 'admin_browse_register_new',
							'permission' => 'moderate_forum',
						],
						'agreement' => [
							'label' => 'registration_agreement',
							'permission' => 'admin_forum',
						],
						'policy' => [
							'label' => 'privacy_policy',
							'permission' => 'admin_forum',
						],
						'reservednames' => [
							'label' => 'admin_reserved_set',
							'permission' => 'admin_forum',
						],
						'settings' => [
							'label' => 'settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'warnings' => [
					'label' => 'warnings',
					'function' => __NAMESPACE__ . '\\Warnings::call',
					'icon' => 'warning',
					'inactive' => false,
					'permission' => ['admin_forum'],
				],
				'ban' => [
					'label' => 'ban_title',
					'function' => __NAMESPACE__ . '\\Bans::call',
					'icon' => 'ban',
					'permission' => 'manage_bans',
					'subsections' => [
						'list' => [
							'label' => 'ban_edit_list',
						],
						'add' => [
							'label' => 'ban_add_new',
						],
						'browse' => [
							'label' => 'ban_trigger_browse',
						],
						'log' => [
							'label' => 'ban_log',
						],
					],
				],
				'paidsubscribe' => [
					'label' => 'paid_subscriptions',
					'inactive' => false,
					'function' => __NAMESPACE__ . '\\Subscriptions::call',
					'icon' => 'paid',
					'permission' => 'admin_forum',
					'subsections' => [
						'view' => [
							'label' => 'paid_subs_view',
						],
						'settings' => [
							'label' => 'settings',
						],
					],
				],
			],
		],
		'maintenance' => [
			'title' => 'admin_maintenance',
			'permission' => ['admin_forum'],
			'areas' => [
				'serversettings' => [
					'label' => 'admin_server_settings',
					'function' => __NAMESPACE__ . '\\Server::call',
					'icon' => 'server',
					'subsections' => [
						'general' => [
							'label' => 'general_settings',
						],
						'database' => [
							'label' => 'database_settings',
						],
						'cookie' => [
							'label' => 'cookies_sessions_settings',
						],
						'security' => [
							'label' => 'security_settings',
						],
						'cache' => [
							'label' => 'caching_settings',
						],
						'export' => [
							'label' => 'export_settings',
						],
						'loads' => [
							'label' => 'load_balancing_settings',
						],
						'phpinfo' => [
							'label' => 'phpinfo_settings',
						],
					],
				],
				'maintain' => [
					'label' => 'maintain_title',
					'function' => __NAMESPACE__ . '\\Maintenance::call',
					'icon' => 'maintain',
					'subsections' => [
						'routine' => [
							'label' => 'maintain_sub_routine',
							'permission' => 'admin_forum',
						],
						'database' => [
							'label' => 'maintain_sub_database',
							'permission' => 'admin_forum',
						],
						'members' => [
							'label' => 'maintain_sub_members',
							'permission' => 'admin_forum',
						],
						'topics' => [
							'label' => 'maintain_sub_topics',
							'permission' => 'admin_forum',
						],
						'hooks' => [
							'label' => 'hooks_title_list',
							'permission' => 'admin_forum',
						],
					],
				],
				'scheduledtasks' => [
					'label' => 'maintain_tasks',
					'function' => __NAMESPACE__ . '\\Tasks::call',
					'icon' => 'scheduled',
					'subsections' => [
						'tasks' => [
							'label' => 'maintain_tasks',
							'permission' => 'admin_forum',
						],
						'tasklog' => [
							'label' => 'scheduled_log',
							'permission' => 'admin_forum',
						],
						'settings' => [
							'label' => 'scheduled_tasks_settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'mailqueue' => [
					'label' => 'mailqueue_title',
					'function' => __NAMESPACE__ . '\\Mail::call',
					'icon' => 'mail',
					'subsections' => [
						'browse' => [
							'label' => 'mailqueue_browse',
							'permission' => 'admin_forum',
						],
						'settings' => [
							'label' => 'mailqueue_settings',
							'permission' => 'admin_forum',
						],
						'test' => [
							'label' => 'mailqueue_test',
							'permission' => 'admin_forum',
						],
					],
				],
				'reports' => [
					'label' => 'generate_reports',
					'function' => __NAMESPACE__ . '\\Reports::call',
					'icon' => 'reports',
				],
				'logs' => [
					'label' => 'logs',
					'function' => __NAMESPACE__ . '\\Logs::call',
					'icon' => 'logs',
					'subsections' => [
						'errorlog' => [
							'label' => 'errorlog',
							'permission' => 'admin_forum',
							'enabled' => true,
							'url' => '{scripturl}?action=admin;area=logs;sa=errorlog;desc',
						],
						'adminlog' => [
							'label' => 'admin_log',
							'permission' => 'admin_forum',
							'enabled' => true,
						],
						'modlog' => [
							'label' => 'moderation_log',
							'permission' => 'admin_forum',
							'enabled' => true,
						],
						'banlog' => [
							'label' => 'ban_log',
							'permission' => 'manage_bans',
						],
						'spiderlog' => [
							'label' => 'spider_logs',
							'permission' => 'admin_forum',
							'enabled' => true,
						],
						'tasklog' => [
							'label' => 'scheduled_log',
							'permission' => 'admin_forum',
						],
						'settings' => [
							'label' => 'log_settings',
							'permission' => 'admin_forum',
						],
					],
				],
				'repairboards' => [
					'label' => 'admin_repair',
					'function' => __NAMESPACE__ . '\\RepairBoards::call',
					'select' => 'maintain',
					'hidden' => true,
				],
			],
		],
	];

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
		User::$me->validateSession();

		// Actually create the menu!
		// Hook call disabled because we already called it in setAdminAreas()
		$menu = new Menu($this->admin_areas, [
			'do_big_icons' => true,
			'disable_hook_call' => true,
		]);

		// Nothing valid?
		if (empty($menu->include_data)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Build the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=admin',
			'name' => Lang::$txt['admin_center'],
		];

		if (isset($menu->current_area) && $menu->current_area != 'index') {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '?action=admin;area=' . $menu->current_area . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'name' => $menu->include_data['label'],
			];
		}

		if (!empty($menu->current_subsection) && $menu->include_data['subsections'][$menu->current_subsection]['label'] != $menu->include_data['label']) {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '?action=admin;area=' . $menu->current_area . ';sa=' . $menu->current_subsection . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'name' => $menu->include_data['subsections'][$menu->current_subsection]['label'],
			];
		}

		// Make a note of the Unique ID for this menu.
		Utils::$context['admin_menu_id'] = $menu->id;
		Utils::$context['admin_menu_name'] = $menu->name;

		// Where in the admin are we?
		Utils::$context['admin_area'] = $menu->current_area;

		// Now - finally - call the right place!
		if (isset($menu->include_data['file'])) {
			require_once Config::$sourcedir . '/' . $menu->include_data['file'];
		}

		// Get the right callable.
		$call = Utils::getCallable($menu->include_data['function']);

		// Is it valid?
		if (!empty($call)) {
			call_user_func($call);
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
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

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

		if (isset($_SESSION['adm-save'])) {
			if ($_SESSION['adm-save'] === true) {
				Utils::$context['saved_successful'] = true;
			} else {
				Utils::$context['saved_failed'] = $_SESSION['adm-save'];
			}

			unset($_SESSION['adm-save']);
		}

		Utils::$context['config_vars'] = [];
		$inlinePermissions = [];
		$bbcChoice = [];
		$board_list = false;

		foreach ($config_vars as $config_var) {
			// HR?
			if (!is_array($config_var)) {
				Utils::$context['config_vars'][] = $config_var;
			} else {
				// If it has no name it doesn't have any purpose!
				if (empty($config_var[1])) {
					continue;
				}

				// Special case for inline permissions
				if ($config_var[0] == 'permissions' && User::$me->allowedTo('manage_permissions')) {
					$inlinePermissions[] = $config_var[1];
				} elseif ($config_var[0] == 'permissions') {
					continue;
				}

				if ($config_var[0] == 'boards') {
					$board_list = true;
				}

				// Are we showing the BBC selection box?
				if ($config_var[0] == 'bbc') {
					$bbcChoice[] = $config_var[1];
				}

				// We need to do some parsing of the value before we pass it in.
				if (isset(Config::$modSettings[$config_var[1]])) {
					switch ($config_var[0]) {
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
				} else {
					// Darn, it's empty. What type is expected?
					switch ($config_var[0]) {
						case 'int':
						case 'float':
							$value = 0;
							break;

						case 'select':
							$value = !empty($config_var['multiple']) ? Utils::jsonEncode([]) : '';
							break;

						case 'boards':
							$value = [];
							break;

						default:
							$value = '';
					}
				}

				Utils::$context['config_vars'][$config_var[1]] = [
					'label' => $config_var['text_label'] ?? (Lang::$txt[$config_var[1]] ?? (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
					'help' => isset(Lang::$helptxt[$config_var[1]]) ? $config_var[1] : '',
					'type' => $config_var[0],
					'size' => !empty($config_var['size']) ? $config_var['size'] : (!empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], ['int', 'float']) ? 6 : 0)),
					'data' => [],
					'name' => $config_var[1],
					'value' => $value,
					'disabled' => false,
					'invalid' => !empty($config_var['invalid']),
					'javascript' => '',
					'var_message' => !empty($config_var['message']) && isset(Lang::$txt[$config_var['message']]) ? Lang::$txt[$config_var['message']] : '',
					'preinput' => $config_var['preinput'] ?? '',
					'postinput' => $config_var['postinput'] ?? '',
				];

				// Handle min/max/step if necessary
				if ($config_var[0] == 'int' || $config_var[0] == 'float') {
					// Default to a min of 0 if one isn't set
					if (isset($config_var['min'])) {
						Utils::$context['config_vars'][$config_var[1]]['min'] = $config_var['min'];
					} else {
						Utils::$context['config_vars'][$config_var[1]]['min'] = 0;
					}

					if (isset($config_var['max'])) {
						Utils::$context['config_vars'][$config_var[1]]['max'] = $config_var['max'];
					}

					if (isset($config_var['step'])) {
						Utils::$context['config_vars'][$config_var[1]]['step'] = $config_var['step'];
					}
				}

				// If this is a select box handle any data.
				if (!empty($config_var[2]) && is_array($config_var[2])) {
					// If we allow multiple selections, we need to adjust a few things.
					if ($config_var[0] == 'select' && !empty($config_var['multiple'])) {
						Utils::$context['config_vars'][$config_var[1]]['name'] .= '[]';

						Utils::$context['config_vars'][$config_var[1]]['value'] = !empty(Utils::$context['config_vars'][$config_var[1]]['value']) ? Utils::jsonDecode(Utils::$context['config_vars'][$config_var[1]]['value'], true) : [];
					}

					// If it's associative
					if (isset($config_var[2][0]) && is_array($config_var[2][0])) {
						Utils::$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
					} else {
						foreach ($config_var[2] as $key => $item) {
							Utils::$context['config_vars'][$config_var[1]]['data'][] = [$key, $item];
						}
					}

					if (empty($config_var['size']) && !empty($config_var['multiple'])) {
						Utils::$context['config_vars'][$config_var[1]]['size'] = max(4, count($config_var[2]));
					}
				}

				// Finally allow overrides - and some final cleanups.
				foreach ($config_var as $k => $v) {
					if (!is_numeric($k)) {
						if (substr($k, 0, 2) == 'on') {
							Utils::$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
						} else {
							Utils::$context['config_vars'][$config_var[1]][$k] = $v;
						}
					}

					// See if there are any other labels that might fit?
					if (isset(Lang::$txt['setting_' . $config_var[1]])) {
						Utils::$context['config_vars'][$config_var[1]]['label'] = Lang::$txt['setting_' . $config_var[1]];
					} elseif (isset(Lang::$txt['groups_' . $config_var[1]])) {
						Utils::$context['config_vars'][$config_var[1]]['label'] = Lang::$txt['groups_' . $config_var[1]];
					}
				}

				// Set the subtext in case it's part of the label.
				// @todo Temporary. Preventing divs inside label tags.
				$divPos = strpos(Utils::$context['config_vars'][$config_var[1]]['label'], '<div');

				if ($divPos !== false) {
					Utils::$context['config_vars'][$config_var[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr(Utils::$context['config_vars'][$config_var[1]]['label'], $divPos));

					Utils::$context['config_vars'][$config_var[1]]['label'] = substr(Utils::$context['config_vars'][$config_var[1]]['label'], 0, $divPos);
				}
			}
		}

		// If we have inline permissions we need to prep them.
		if (!empty($inlinePermissions) && User::$me->allowedTo('manage_permissions')) {
			Permissions::init_inline_permissions($inlinePermissions);
		}

		if ($board_list) {
			Utils::$context['board_list'] = MessageIndex::getBoardList();
		}

		// What about any BBC selection boxes?
		if (!empty($bbcChoice)) {
			// What are the options, eh?
			$temp = BBCodeParser::getCodes();
			$bbcTags = [];

			foreach ($temp as $tag) {
				if (!isset($tag['require_parents'])) {
					$bbcTags[] = $tag['tag'];
				}
			}

			$bbcTags = array_unique($bbcTags);

			// The number of columns we want to show the BBC tags in.
			$numColumns = Utils::$context['num_bbc_columns'] ?? 3;

			// Now put whatever BBC options we may have into context too!
			Utils::$context['bbc_sections'] = [];

			foreach ($bbcChoice as $bbcSection) {
				Utils::$context['bbc_sections'][$bbcSection] = [
					'title' => Lang::$txt['bbc_title_' . $bbcSection] ?? Lang::$txt['enabled_bbc_select'],
					'disabled' => empty(Config::$modSettings['bbc_disabled_' . $bbcSection]) ? [] : Config::$modSettings['bbc_disabled_' . $bbcSection],
					'all_selected' => empty(Config::$modSettings['bbc_disabled_' . $bbcSection]),
					'columns' => [],
				];

				if ($bbcSection == 'legacyBBC') {
					$sectionTags = array_intersect(Utils::$context['legacy_bbc'], $bbcTags);
				} else {
					$sectionTags = array_diff($bbcTags, Utils::$context['legacy_bbc']);
				}

				$totalTags = count($sectionTags);
				$tagsPerColumn = ceil($totalTags / $numColumns);

				$col = 0;
				$i = 0;

				foreach ($sectionTags as $tag) {
					if ($i % $tagsPerColumn == 0 && $i != 0) {
						$col++;
					}

					Utils::$context['bbc_sections'][$bbcSection]['columns'][$col][] = [
						'tag' => $tag,
						'show_help' => isset(Lang::$helptxt['tag_' . $tag]),
					];

					$i++;
				}
			}
		}

		IntegrationHook::call('integrate_prepare_db_settings', [&$config_vars]);
		SecurityToken::create('admin-dbsc');
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
		SecurityToken::validate('admin-ssc');

		// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
		if (isset($_POST['cookiename'])) {
			$_POST['cookiename'] = preg_replace('~[,;\s\.$]+~' . (Utils::$context['utf8'] ? 'u' : ''), '', $_POST['cookiename']);
		}

		// Fix the forum's URL if necessary.
		if (isset($_POST['boardurl'])) {
			if (substr($_POST['boardurl'], -10) == '/index.php') {
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
			} elseif (substr($_POST['boardurl'], -1) == '/') {
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
			}

			if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://') {
				$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
			}

			$_POST['boardurl'] = (string) new Url($_POST['boardurl'], true);
		}

		// Any passwords?
		$config_passwords = [];

		// All the numeric variables.
		$config_nums = [];

		// All the checkboxes
		$config_bools = [];

		// Ones that accept multiple types (should be rare)
		$config_multis = [];

		// Get all known setting definitions and assign them to our groups above.
		$settings_defs = Config::getSettingsDefs();

		foreach ($settings_defs as $var => $def) {
			if (!is_string($var)) {
				continue;
			}

			if (!empty($def['is_password'])) {
				$config_passwords[] = $var;
			} else {
				// Special handling if multiple types are allowed.
				if (is_array($def['type'])) {
					// Obviously, we don't need null here.
					$def['type'] = array_filter(
						$def['type'],
						function ($type) {
							return $type !== 'NULL';
						},
					);

					$type = count($def['type']) == 1 ? reset($def['type']) : 'multiple';
				} else {
					$type = $def['type'];
				}

				switch ($type) {
					case 'multiple':
						$config_multis[$var] = $def['type'];
						// no break

					case 'double':
						$config_nums[] = $var;
						break;

					case 'integer':
						// Some things saved as integers are presented as booleans
						foreach ($config_vars as $config_var) {
							if (is_array($config_var) && $config_var[0] == $var) {
								if ($config_var[3] == 'check') {
									$config_bools[] = $var;
									break 2;
								}
								break;
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
		$new_settings = [];

		// Figure out which config vars we're saving here...
		foreach ($config_vars as $config_var) {
			if (!is_array($config_var) || $config_var[2] != 'file') {
				continue;
			}

			$var_name = $config_var[0];

			// Unknown setting?
			if (!isset($settings_defs[$var_name]) && isset($config_var[3])) {
				switch ($config_var[3]) {
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

			if (!in_array($var_name, $config_bools) && !isset($_POST[$var_name])) {
				continue;
			}

			if (in_array($var_name, $config_passwords)) {
				if (isset($_POST[$var_name][1]) && $_POST[$var_name][0] == $_POST[$var_name][1]) {
					$new_settings[$var_name] = $_POST[$var_name][0];
				}
			} elseif (in_array($var_name, $config_nums)) {
				$new_settings[$var_name] = (int) $_POST[$var_name];

				// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
				$min = $config_var['min'] ?? 0;
				$new_settings[$var_name] = max($min, $new_settings[$var_name]);

				// Is there a max value for this as well?
				if (isset($config_var['max'])) {
					$new_settings[$var_name] = min($config_var['max'], $new_settings[$var_name]);
				}
			} elseif (in_array($var_name, $config_bools)) {
				$new_settings[$var_name] = !empty($_POST[$var_name]);
			} elseif (isset($config_multis[$var_name])) {
				$is_acceptable_type = false;

				foreach ($config_multis[$var_name] as $type) {
					$temp = $_POST[$var_name];
					settype($temp, $type);

					if ($temp == $_POST[$var_name]) {
						$new_settings[$var_name] = $temp;
						$is_acceptable_type = true;
						break;
					}
				}

				if (!$is_acceptable_type) {
					ErrorHandler::fatal('Invalid config_var \'' . $var_name . '\'');
				}
			} else {
				$new_settings[$var_name] = $_POST[$var_name];
			}
		}

		// Save the relevant settings in the Settings.php file.
		Config::updateSettingsFile($new_settings);

		// Now loop through the remaining (database-based) settings.
		$new_settings = [];

		foreach ($config_vars as $config_var) {
			// We just saved the file-based settings, so skip their definitions.
			if (!is_array($config_var) || $config_var[2] == 'file') {
				continue;
			}

			$new_setting = [$config_var[3], $config_var[0]];

			// Select options need carried over, too.
			if (isset($config_var[4])) {
				$new_setting[] = $config_var[4];
			}

			// Include min and max if necessary
			if (isset($config_var['min'])) {
				$new_setting['min'] = $config_var['min'];
			}

			if (isset($config_var['max'])) {
				$new_setting['max'] = $config_var['max'];
			}

			// Rewrite the definition a bit.
			$new_settings[] = $new_setting;
		}

		// Save the new database-based settings, if any.
		if (!empty($new_settings)) {
			ACP::saveDBSettings($new_settings);
		}
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

		SecurityToken::validate('admin-dbsc');

		$inlinePermissions = [];

		foreach ($config_vars as $var) {
			if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && $var[0] != 'boards' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags'])))) {
				continue;
			}

			// Checkboxes!
			if ($var[0] == 'check') {
				$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
			}
			// Select boxes!
			elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2]))) {
				$setArray[$var[1]] = $_POST[$var[1]];
			} elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != []) {
				// For security purposes we validate this line by line.
				$lOptions = [];

				foreach ($_POST[$var[1]] as $invar) {
					if (in_array($invar, array_keys($var[2]))) {
						$lOptions[] = $invar;
					}
				}

				$setArray[$var[1]] = Utils::jsonEncode($lOptions);
			}
			// List of boards!
			elseif ($var[0] == 'boards') {
				// We just need a simple list of valid boards, nothing more.
				if ($board_list === null) {
					$board_list = [];
					$request = Db::$db->query(
						'',
						'SELECT id_board
						FROM {db_prefix}boards',
					);

					while ($row = Db::$db->fetch_row($request)) {
						$board_list[$row[0]] = true;
					}
					Db::$db->free_result($request);
				}

				$lOptions = [];

				if (!empty($_POST[$var[1]])) {
					foreach ($_POST[$var[1]] as $invar => $dummy) {
						if (isset($board_list[$invar])) {
							$lOptions[] = $invar;
						}
					}
				}

				$setArray[$var[1]] = !empty($lOptions) ? implode(',', $lOptions) : '';
			}
			// Integers!
			elseif ($var[0] == 'int') {
				$setArray[$var[1]] = (int) $_POST[$var[1]];

				// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
				$min = $var['min'] ?? 0;
				$setArray[$var[1]] = max($min, $setArray[$var[1]]);

				// Do we have a max value for this as well?
				if (isset($var['max'])) {
					$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
				}
			}
			// Floating point!
			elseif ($var[0] == 'float') {
				$setArray[$var[1]] = (float) $_POST[$var[1]];

				// If no min is specified, assume 0. This is done to avoid having to specify 'min => 0' for all settings where 0 is the min...
				$min = $var['min'] ?? 0;
				$setArray[$var[1]] = max($min, $setArray[$var[1]]);

				// Do we have a max value for this as well?
				if (isset($var['max'])) {
					$setArray[$var[1]] = min($var['max'], $setArray[$var[1]]);
				}
			}
			// Text!
			elseif (in_array($var[0], ['text', 'large_text', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time'])) {
				$setArray[$var[1]] = $_POST[$var[1]];
			}
			// Passwords!
			elseif ($var[0] == 'password') {
				if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1]) {
					$setArray[$var[1]] = $_POST[$var[1]][0];
				}
			}
			// BBC.
			elseif ($var[0] == 'bbc') {
				$bbcTags = [];

				foreach (BBCodeParser::getCodes() as $tag) {
					$bbcTags[] = $tag['tag'];
				}

				if (!isset($_POST[$var[1] . '_enabledTags'])) {
					$_POST[$var[1] . '_enabledTags'] = [];
				} elseif (!is_array($_POST[$var[1] . '_enabledTags'])) {
					$_POST[$var[1] . '_enabledTags'] = [$_POST[$var[1] . '_enabledTags']];
				}

				$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
			}
			// Permissions?
			elseif ($var[0] == 'permissions') {
				$inlinePermissions[] = $var[1];
			}
		}

		if (!empty($setArray)) {
			Config::updateModSettings($setArray);
		}

		// If we have inline permissions we need to save them.
		if (!empty($inlinePermissions) && User::$me->allowedTo('manage_permissions')) {
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

		$versions = [];

		// Is GD available?  If it is, we should show version information for it too.
		if (in_array('gd', $checkFor) && function_exists('gd_info')) {
			$temp = gd_info();
			$versions['gd'] = ['title' => Lang::$txt['support_versions_gd'], 'version' => $temp['GD Version']];
		}

		// Why not have a look at ImageMagick? If it's installed, we should show version information for it too.
		if (in_array('imagemagick', $checkFor) && class_exists('Imagick')) {
			$temp = new \Imagick();
			$temp2 = $temp->getVersion();
			$im_version = $temp2['versionString'];
			$extension_version = 'Imagick ' . phpversion('Imagick');

			// We already know it's ImageMagick and the website isn't needed...
			$im_version = str_replace(['ImageMagick ', ' https://www.imagemagick.org'], '', $im_version);

			$versions['imagemagick'] = ['title' => Lang::$txt['support_versions_imagemagick'], 'version' => $im_version . ' (' . $extension_version . ')'];
		}

		// Now lets check for the Database.
		if (in_array('db_server', $checkFor)) {
			if (!isset(Db::$db_connection) || Db::$db_connection === false) {
				Lang::load('Errors');
				trigger_error(Lang::$txt['get_server_versions_no_database'], E_USER_NOTICE);
			} else {
				$versions['db_engine'] = [
					'title' => sprintf(Lang::$txt['support_versions_db_engine'], Db::$db->title),
					'version' => Db::$db->get_vendor(),
				];

				$versions['db_server'] = [
					'title' => sprintf(Lang::$txt['support_versions_db'], Db::$db->title),
					'version' => Db::$db->get_version(),
				];
			}
		}

		// Check to see if we have any accelerators installed.
		foreach (CacheApi::detect() as $class_name => $cache_api) {
			$class_name_txt_key = strtolower($cache_api->getImplementationClassKeyName());

			if (in_array($class_name_txt_key, $checkFor)) {
				$versions[$class_name_txt_key] = [
					'title' => Lang::$txt[$class_name_txt_key . '_cache'] ?? $class_name,
					'version' => $cache_api->getVersion(),
				];
			}
		}

		if (in_array('php', $checkFor)) {
			$versions['php'] = [
				'title' => 'PHP',
				'version' => PHP_VERSION,
				'more' => '?action=admin;area=serversettings;sa=phpinfo',
			];
		}

		if (in_array('server', $checkFor)) {
			$versions['server'] = [
				'title' => Lang::$txt['support_versions_server'],
				'version' => $_SERVER['SERVER_SOFTWARE'],
			];
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
	 * @param array &$versionOptions An array of options. Can contain one or more of 'include_root', 'include_tasks' and 'sort_results'
	 * @return array An array of file version info.
	 */
	public static function getFileVersions(&$versionOptions)
	{
		// Default place to find the languages would be the default theme dir.
		$lang_dir = Theme::$current->settings['default_theme_dir'] . '/languages';

		$version_info = [
			'root_versions' => [],
			'file_versions' => [],
			'default_template_versions' => [],
			'template_versions' => [],
			'default_language_versions' => [],
			'tasks_versions' => [],
		];

		$root_files = [
			'cron.php',
			'proxy.php',
			'SSI.php',
			'subscriptions.php',
		];

		// Find the version in root files header.
		if (!empty($versionOptions['include_root'])) {
			foreach ($root_files as $file) {
				if (!file_exists(Config::$boarddir . '/' . $file)) {
					continue;
				}

				$fp = fopen(Config::$boarddir . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				// The comment looks rougly like... that.
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1) {
					$version_info['root_versions'][$file] = $match[1];
				}
				// Not found!  This is bad.
				else {
					$version_info['root_versions'][$file] = '??';
				}
			}
		}

		// Load all the files in the Sources directory, except some vendor libraires, index place holderes and non php files.
		$sources_dir = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				Config::$sourcedir,
				\RecursiveDirectoryIterator::SKIP_DOTS,
			),
		);

		$ignore_sources = [
			Config::$sourcedir . '/minify/*',
			Config::$sourcedir . '/ReCaptcha/*',
			Config::$sourcedir . '/Tasks/*',
		];

		foreach ($sources_dir as $filename => $file) {
			if (!$file->isFile() || $file->getFilename() === 'index.php' || $file->getExtension() !== 'php') {
				continue;
			}

			foreach ($ignore_sources as $if) {
				if (preg_match('~' . $if . '~i', $filename)) {
					continue 2;
				}
			}

			$shortname = str_replace(Config::$sourcedir . '/', '', $filename);

			// Read the first 4k from the file.... enough for the header.
			$fp = $file->openFile('rb');
			$header = $fp->fread(4096);
			$fp = null;

			// Look for the version comment in the file header.
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1) {
				$version_info['file_versions'][$shortname] = $match[1];
			}
			// It wasn't found, but the file was... show a '??'.
			else {
				$version_info['file_versions'][$shortname] = '??';
			}
		}
		$sources_dir = null;

		// Load all the files in the tasks directory.
		if (!empty($versionOptions['include_tasks'])) {
			$tasks_dir = dir(Config::$tasksdir);

			while ($entry = $tasks_dir->read()) {
				if (substr($entry, -4) === '.php' && !is_dir(Config::$tasksdir . '/' . $entry) && $entry !== 'index.php') {
					// Read the first 4k from the file.... enough for the header.
					$fp = fopen(Config::$tasksdir . '/' . $entry, 'rb');
					$header = fread($fp, 4096);
					fclose($fp);

					// Look for the version comment in the file header.
					if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1) {
						$version_info['tasks_versions'][$entry] = $match[1];
					}
					// It wasn't found, but the file was... show a '??'.
					else {
						$version_info['tasks_versions'][$entry] = '??';
					}
				}
			}
			$tasks_dir->close();
		}

		// Load all the files in the default template directory - and the current theme if applicable.
		$directories = ['default_template_versions' => Theme::$current->settings['default_theme_dir']];

		if (Theme::$current->settings['theme_id'] != 1) {
			$directories += ['template_versions' => Theme::$current->settings['theme_dir']];
		}

		foreach ($directories as $type => $dirname) {
			$this_dir = dir($dirname);

			while ($entry = $this_dir->read()) {
				if (substr($entry, -12) == 'template.php' && !is_dir($dirname . '/' . $entry)) {
					// Read the first 768 bytes from the file.... enough for the header.
					$fp = fopen($dirname . '/' . $entry, 'rb');
					$header = fread($fp, 768);
					fclose($fp);

					// Look for the version comment in the file header.
					if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1) {
						$version_info[$type][$entry] = $match[1];
					}
					// It wasn't found, but the file was... show a '??'.
					else {
						$version_info[$type][$entry] = '??';
					}
				}
			}
			$this_dir->close();
		}

		// Load up all the files in the default language directory and sort by language.
		$this_dir = dir($lang_dir);

		while ($entry = $this_dir->read()) {
			if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($lang_dir . '/' . $entry)) {
				// Read the first 768 bytes from the file.... enough for the header.
				$fp = fopen($lang_dir . '/' . $entry, 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Split the file name off into useful bits.
				list($name, $language) = explode('.', $entry);

				// Look for the version comment in the file header.
				if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1) {
					$version_info['default_language_versions'][$language][$name] = $match[1];
				}
				// It wasn't found, but the file was... show a '??'.
				else {
					$version_info['default_language_versions'][$language][$name] = '??';
				}
			}
		}

		$this_dir->close();

		// Sort the file versions by filename.
		if (!empty($versionOptions['sort_results'])) {
			ksort($version_info['file_versions']);
			ksort($version_info['default_template_versions']);
			ksort($version_info['template_versions']);
			ksort($version_info['default_language_versions']);
			ksort($version_info['tasks_versions']);

			// For languages sort each language too.
			foreach ($version_info['default_language_versions'] as $language => $dummy) {
				ksort($version_info['default_language_versions'][$language]);
			}
		}

		return $version_info;
	}

	/**
	 * Saves the admin's current preferences to the database.
	 */
	public static function updateAdminPreferences()
	{
		// This must exist!
		if (!isset(Utils::$context['admin_preferences'])) {
			return false;
		}

		// This is what we'll be saving.
		Theme::$current->options['admin_preferences'] = Utils::jsonEncode(Utils::$context['admin_preferences']);

		// Just check we haven't ended up with something theme exclusive somehow.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}themes
			WHERE id_theme != {int:default_theme}
				AND variable = {string:admin_preferences}',
			[
				'default_theme' => 1,
				'admin_preferences' => 'admin_preferences',
			],
		);

		// Update the themes table.
		Db::$db->insert(
			'replace',
			'{db_prefix}themes',
			['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'],
			[User::$me->id, 1, 'admin_preferences', Theme::$current->options['admin_preferences']],
			['id_member', 'id_theme', 'variable'],
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
	public static function emailAdmins($template, $replacements = [], $additional_recipients = [])
	{
		// Load all members which are effectively admins.
		$members = User::membersAllowedTo('admin_forum');

		// Load their alert preferences
		$prefs = Notify::getNotifyPrefs($members, 'announcements', true);

		$emails_sent = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, member_name, real_name, lngfile, email_address
			FROM {db_prefix}members
			WHERE id_member IN({array_int:members})',
			[
				'members' => $members,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (empty($prefs[$row['id_member']]['announcements'])) {
				continue;
			}

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
		if (!empty($additional_recipients)) {
			foreach ($additional_recipients as $recipient) {
				if (in_array($recipient['email'], $emails_sent)) {
					continue;
				}

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

	/**
	 * Question the verity of the admin by asking for his or her password.
	 * - loads Login.template.php and uses the admin_login sub template.
	 * - sends data to template so the admin is sent on to the page they
	 *   wanted if their password is correct, otherwise they can try again.
	 *
	 * @param string $type What login type is this - can be 'admin' or 'moderate'
	 */
	public static function adminLogin($type = 'admin')
	{
		Lang::load('Admin');
		Theme::loadTemplate('Login');

		// Validate what type of session check this is.
		$types = [];
		IntegrationHook::call('integrate_validateSession', [&$types]);
		$type = in_array($type, $types) || $type == 'moderate' ? $type : 'admin';

		// They used a wrong password, log it and unset that.
		if (isset($_POST[$type . '_hash_pass']) || isset($_POST[$type . '_pass'])) {
			Lang::$txt['security_wrong'] = sprintf(Lang::$txt['security_wrong'], $_SERVER['HTTP_REFERER'] ?? Lang::$txt['unknown'], $_SERVER['HTTP_USER_AGENT'], User::$me->ip);
			ErrorHandler::log(Lang::$txt['security_wrong'], 'critical');

			if (isset($_POST[$type . '_hash_pass'])) {
				unset($_POST[$type . '_hash_pass']);
			}

			if (isset($_POST[$type . '_pass'])) {
				unset($_POST[$type . '_pass']);
			}

			Utils::$context['incorrect_password'] = true;
		}

		SecurityToken::create('admin-login');

		// Figure out the get data and post data.
		Utils::$context['get_data'] = '?' . self::construct_query_string($_GET);
		Utils::$context['post_data'] = '';

		// Now go through $_POST.  Make sure the session hash is sent.
		$_POST[Utils::$context['session_var']] = Utils::$context['session_id'];

		foreach ($_POST as $k => $v) {
			Utils::$context['post_data'] .= self::adminLogin_outputPostVars($k, $v);
		}

		// Now we'll use the admin_login sub template of the Login template.
		Utils::$context['sub_template'] = 'admin_login';

		// And title the page something like "Login".
		if (!isset(Utils::$context['page_title'])) {
			Utils::$context['page_title'] = Lang::$txt['login'];
		}

		// The type of action.
		Utils::$context['sessionCheckType'] = $type;

		Utils::obExit();

		// We MUST exit at this point, because otherwise we CANNOT KNOW that the user is privileged.
		trigger_error('No direct access...', E_USER_ERROR);
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
		Theme::loadJavaScriptFile('admin.js', ['minimize' => true], 'smf_admin');
		Theme::loadCSSFile('admin.css', [], 'smf_admin');

		// Set any dynamic values in $this->admin_areas.
		$this->setAdminAreas();

		// No indexing evil stuff.
		Utils::$context['robot_no_index'] = true;

		// Some preferences.
		Utils::$context['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : [];

		// Any files to include for administration?
		if (!empty(Config::$modSettings['integrate_admin_include'])) {
			$admin_includes = explode(',', Config::$modSettings['integrate_admin_include']);

			foreach ($admin_includes as $include) {
				$include = strtr(trim($include), [
					'$boarddir' => Config::$boarddir,
					'$sourcedir' => Config::$sourcedir,
					'$themedir' => Theme::$current->settings['theme_dir'],
				]);

				if (file_exists($include)) {
					require_once $include;
				}
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
			function (&$value, $key) {
				if (in_array($key, ['title', 'label'])) {
					$value = Lang::$txt[$value] ?? $value;
				}

				$value = strtr($value, [
					'{scripturl}' => Config::$scripturl,
					'{boardurl}' => Config::$boardurl,
				]);
			},
		);

		// Fill in the ID number for the current theme URL.
		$this->admin_areas['config']['areas']['current_theme']['custom_url'] = sprintf($this->admin_areas['config']['areas']['current_theme']['custom_url'], Theme::$current->settings['theme_id']);

		// Figure out what is enabled or not.
		$this->admin_areas['forum']['areas']['adminlogoff']['enabled'] = empty(Config::$modSettings['securityDisable']);

		if (empty(Config::$modSettings['cal_enabled'])) {
			$this->admin_areas['layout']['areas']['managecalendar']['inactive'] = true;
			$this->admin_areas['layout']['areas']['managecalendar']['subsections'] = [];
		}

		$this->admin_areas['layout']['areas']['smileys']['subsections']['addsmiley']['enabled'] = !empty(Config::$modSettings['smiley_enable']);
		$this->admin_areas['layout']['areas']['smileys']['subsections']['editsmileys']['enabled'] = !empty(Config::$modSettings['smiley_enable']);
		$this->admin_areas['layout']['areas']['smileys']['subsections']['setorder']['enabled'] = !empty(Config::$modSettings['smiley_enable']);
		$this->admin_areas['layout']['areas']['smileys']['subsections']['editicons']['enabled'] = !empty(Config::$modSettings['messageIcons_enable']);

		if (empty(Config::$modSettings['spider_mode'])) {
			$this->admin_areas['layout']['areas']['sengines']['inactive'] = true;
			$this->admin_areas['layout']['areas']['sengines']['subsections'] = [];
		}

		$this->admin_areas['members']['areas']['warnings']['inactive'] = Config::$modSettings['warning_settings'][0] == 0;

		if (empty(Config::$modSettings['paid_enabled'])) {
			$this->admin_areas['members']['areas']['paidsubscribe']['inactive'] = true;
			$this->admin_areas['members']['areas']['paidsubscribe']['subsections'] = [];
		}

		$this->admin_areas['maintenance']['areas']['logs']['subsections']['errorlog']['enabled'] = !empty(Config::$modSettings['enableErrorLogging']);
		$this->admin_areas['maintenance']['areas']['logs']['subsections']['adminlog']['enabled'] = !empty(Config::$modSettings['adminlog_enabled']);
		$this->admin_areas['maintenance']['areas']['logs']['subsections']['modlog']['enabled'] = !empty(Config::$modSettings['modlog_enabled']);
		$this->admin_areas['maintenance']['areas']['logs']['subsections']['spiderlog']['enabled'] = !empty(Config::$modSettings['spider_mode']);

		// Give mods access to the menu.
		IntegrationHook::call('integrate_admin_areas', [&$this->admin_areas]);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Used by the adminLogin() method.
	 *
	 * If 'value' is an array, calls itself recursively.
	 *
	 * @param string $k The keys
	 * @param string $v The values
	 * @return string 'hidden' HTML form fields, containing key-value pairs
	 */
	protected static function adminLogin_outputPostVars($k, $v)
	{
		if (!is_array($v)) {
			return "\n" . '<input type="hidden" name="' . Utils::htmlspecialchars($k) . '" value="' . strtr($v, ['"' => '&quot;', '<' => '&lt;', '>' => '&gt;']) . '">';
		}

		$ret = '';

		foreach ($v as $k2 => $v2) {
			$ret .= self::adminLogin_outputPostVars($k . '[' . $k2 . ']', $v2);
		}

		return $ret;
	}

	/**
	 * Properly urlencodes a string to be used in a query.
	 *
	 * @param string $get A copy of $_GET.
	 * @return string Our query string.
	 */
	protected static function construct_query_string($get)
	{
		$query_string = '';

		// Awww, darn. The Config::$scripturl contains GET stuff!
		$q = strpos(Config::$scripturl, '?');

		if ($q !== false) {
			parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(substr(Config::$scripturl, $q + 1), ';', '&')), $temp);

			foreach ($get as $k => $v) {
				// Only if it's not already in the Config::$scripturl!
				if (!isset($temp[$k])) {
					$query_string .= urlencode($k) . '=' . urlencode($v) . ';';
				}
				// If it changed, put it out there, but with an ampersand.
				elseif ($temp[$k] != $get[$k]) {
					$query_string .= urlencode($k) . '=' . urlencode($v) . '&amp;';
				}
			}
		} else {
			// Add up all the data from $_GET into get_data.
			foreach ($get as $k => $v) {
				$query_string .= urlencode($k) . '=' . urlencode($v) . ';';
			}
		}

		$query_string = substr($query_string, 0, -1);

		return $query_string;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ACP::exportStatic')) {
	ACP::exportStatic();
}

?>