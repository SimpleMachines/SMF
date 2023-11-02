<?php

/**
 * Moderation Center.
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

namespace SMF\Actions\Moderation;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\Config;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This is the Moderation Center.
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
	 * Defines the menu structure for the moderation center.
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
	 * MOD AUTHORS: You can use the integration_moderation_areas hook to add
	 * things to this menu. The hook can be found in SMF\Menu::_construct().
	 */
	public array $moderation_areas = array(
		'main' => array(
			'title' => 'mc_main',
			'areas' => array(
				'index' => array(
					'label' => 'moderation_center',
					'function' => __NAMESPACE__ . '\\Home::call',
					'icon' => 'administration',
				),
				'modlogoff' => array(
					'label' => 'mc_logoff',
					'function' => __NAMESPACE__ . '\\EndSession::call',
					'enabled' => true,
					'icon' => 'exit',
				),
				'notice' => array(
					'function' => __NAMESPACE__ . '\\ShowNotice::call',
					'select' => 'index'
				),
			),
		),
		'logs' => array(
			'title' => 'mc_logs',
			'areas' => array(
				'modlog' => array(
					'label' => 'modlog_view',
					'enabled' => true,
					'file' => 'Modlog.php',
					'function' => 'ViewModlog',
					'icon' => 'logs',
				),
				'warnings' => array(
					'label' => 'mc_warnings',
					'enabled' => true,
					'function' => __NAMESPACE__ . '\\Warnings::call',
					'icon' => 'warning',
					'subsections' => array(
						'log' => array(
							'label' => 'mc_warning_log',
							'permission' => array('view_warning_any', 'moderate_forum'),
						),
						'templates' => array(
							'label' => 'mc_warning_templates',
							'permission' => 'issue_warning',
						),
					),
				),
			),
		),
		'posts' => array(
			'title' => 'mc_posts',
			'enabled' => true,
			'areas' => array(
				'postmod' => array(
					'label' => 'mc_unapproved_posts',
					'enabled' => true,
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'posts',
					'custom_url' => '{scripturl}?action=moderate;area=postmod',
					'subsections' => array(
						'posts' => array(
							'label' => 'mc_unapproved_replies',
						),
						'topics' => array(
							'label' => 'mc_unapproved_topics',
						),
					),
				),
				'attachmod' => array(
					'label' => 'mc_unapproved_attachments',
					'enabled' => true,
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'post_moderation_attach',
					'custom_url' => '{scripturl}?action=moderate;area=attachmod;sa=attachments',
				),
				'reportedposts' => array(
					'label' => 'mc_reported_posts',
					'enabled' => true,
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'reports',
					'subsections' => array(
						'show' => array(
							'label' => 'mc_reportedp_active',
						),
						'closed' => array(
							'label' => 'mc_reportedp_closed',
						),
					),
				),
			),
		),
		'groups' => array(
			'title' => 'mc_groups',
			'enabled' => true,
			'areas' => array(
				'groups' => array(
					'label' => 'mc_group_requests',
					'function' => '\\SMF\\Actions\\Groups::call',
					'icon' => 'members_request',
					'custom_url' => '{scripturl}?action=moderate;area=groups;sa=requests',
				),
				'viewgroups' => array(
					'label' => 'mc_view_groups',
					'function' => '\\SMF\\Actions\\Groups::call',
					'icon' => 'membergroups',
				),
			),
		),
		'members' => array(
			'title' => 'mc_members',
			'enabled' => true,
			'areas' => array(
				'userwatch' => array(
					'label' => 'mc_watched_users_title',
					'enabled' => true,
					'function' => __NAMESPACE__ . '\\WatchedUsers::call',
					'icon' => 'members_watched',
					'subsections' => array(
						'member' => array(
							'label' => 'mc_watched_users_member',
						),
						'post' => array(
							'label' => 'mc_watched_users_post',
						),
					),
				),
				'reportedmembers' => array(
					'label' => 'mc_reported_members_title',
					'enabled' => true,
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'members_watched',
					'subsections' => array(
						'open' => array(
							'label' => 'mc_reportedp_active',
						),
						'closed' => array(
							'label' => 'mc_reportedp_closed',
						),
					),
				),
			),
		)
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

	/**
	 * @var bool
	 *
	 * Whether self::checkAccessPermissions() has been run yet.
	 */
	protected static bool $access_checked = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher.
	 */
	public function execute(): void
	{
		$this->createMenu();

		if (isset(Menu::$loaded['moderate']->include_data['file']))
		{
			require_once(Config::$sourcedir . '/' . Menu::$loaded['moderate']->include_data['file']);
		}

		call_helper(method_exists($this, Menu::$loaded['moderate']->include_data['function']) ? array($this, Menu::$loaded['moderate']->include_data['function']) : Menu::$loaded['moderate']->include_data['function']);
	}

	/**
	 * Creates the moderation center menu.
	 *
	 * This is separated from execute() for the sake of SMF\Actions\Groups,
	 * which wants to be able to create the menu without calling the methods.
	 */
	public function createMenu(): void
	{
		// Make sure the administrator has a valid session...
		validateSession('moderate');

		// I don't know where we're going - I don't know where we've been...
		$menuOptions = array(
			'action' => 'moderate',
			'disable_url_session_check' => true,
		);

		$menu = new Menu($this->moderation_areas, $menuOptions);

		// We got something - didn't we? DIDN'T WE!
		if (empty($menu->include_data))
			fatal_lang_error('no_access', false);

		// Retain the ID information in case required by a subaction.
		Utils::$context['moderation_menu_id'] = $menu->id;
		Utils::$context['moderation_menu_name'] = $menu->name;

		// @todo: html in here is not good
		$menu->tab_data = array(
			'title' => Lang::$txt['moderation_center'],
			'help' => '',
			'description' => '
				<strong>' . Lang::$txt['hello_guest'] . ' ' . User::$me->name . '!</strong>
				<br><br>
				' . Lang::$txt['mc_description']
		);

		// What a pleasant shortcut - even tho we're not *really* on the admin screen who cares...
		Utils::$context['admin_area'] = $menu->current_area;

		// Build the link tree.
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=moderate',
			'name' => Lang::$txt['moderation_center'],
		);

		if (isset($menu->current_area) && $menu->current_area != 'index')
		{
			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=moderate;area=' . $menu->current_area,
				'name' => $menu->include_data['label'],
			);
		}

		if (!empty($menu->current_subsection) && $menu->include_data['subsections'][$menu->current_subsection]['label'] != $menu->include_data['label'])
		{
			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=moderate;area=' . $menu->current_area . ';sa=' . $menu->current_subsection,
				'name' => $menu->include_data['subsections'][$menu->current_subsection]['label'],
			);
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
	 * Figures out which parts of the moderation center can be accessed by the
	 * current user.
	 *
	 * Populates the following context variables:
	 *  - can_moderate_boards
	 *  - can_moderate_groups
	 *  - can_moderate_approvals
	 *  - can_moderate_users
	 */
	public static function checkAccessPermissions(): void
	{
		// No need to repeat these checks.
		if (self::$access_checked)
			return;

		Utils::$context['can_moderate_boards'] = User::$me->mod_cache['bq'] != '0=1';
		Utils::$context['can_moderate_groups'] = User::$me->mod_cache['gq'] != '0=1';
		Utils::$context['can_moderate_approvals'] = Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']);
		Utils::$context['can_moderate_users'] = allowedTo('moderate_forum');

		// Everyone using this area must be allowed here!
		if (!Utils::$context['can_moderate_boards'] && !Utils::$context['can_moderate_groups'] && !Utils::$context['can_moderate_approvals'] && !Utils::$context['can_moderate_users'])
		{
			isAllowedTo('access_mod_center');
		}

		self::$access_checked = true;
	}

	/**
	 * Backward compatibility wrapper that either calls self::call() or calls
	 * self::load()->createMenu(), depending on the value of $dont_call.
	 *
	 * @param bool $dont_call If true, just creates the menu and doesn't call
	 *    the function for the appropriate mod area.
	 */
	public static function ModerationMain(bool $dont_call = false): void
	{
		if ($dont_call)
		{
			self::load()->createMenu();
		}
		else
		{
			self::call();
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
		// Don't run this twice... and don't conflict with the admin bar.
		if (isset(Utils::$context['admin_area']))
			return;

		self::checkAccessPermissions();

		// Load the language, and the template.
		Lang::load('ModerationCenter');
		Theme::loadTemplate(false, 'admin');

		Utils::$context['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : array();
		Utils::$context['robot_no_index'] = true;

		$this->setModerationAreas();
	}

	/**
	 * Sets any dynamic values in $this->moderation_areas.
	 */
	protected function setModerationAreas(): void
	{
		// Finalize various string values.
		array_walk_recursive(
			$this->moderation_areas,
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

		// Is moderation security enabled?
		$this->moderation_areas['main']['areas']['modlogoff']['enabled'] = empty(Config::$modSettings['securityDisable_moderate']);

		// Which logs should be enabled?
		$this->moderation_areas['logs']['areas']['modlog']['enabled'] = !empty(Config::$modSettings['modlog_enabled']) && Utils::$context['can_moderate_boards'];

		$this->moderation_areas['logs']['areas']['warnings']['enabled'] = Config::$modSettings['warning_settings'][0] == 1 && allowedTo(array('issue_warning', 'view_warning_any'));

		// Which parts of post moderation should be enabled?
		$this->moderation_areas['posts']['enabled'] = Utils::$context['can_moderate_boards'] || Utils::$context['can_moderate_approvals'];

		$this->moderation_areas['posts']['areas']['postmod']['enabled'] = Utils::$context['can_moderate_approvals'];

		$this->moderation_areas['posts']['areas']['attachmod']['enabled'] = Utils::$context['can_moderate_approvals'];

		$this->moderation_areas['posts']['areas']['reportedposts']['enabled'] = Utils::$context['can_moderate_boards'];

		// Should group moderation be enabled?
		$this->moderation_areas['groups']['enabled'] = Utils::$context['can_moderate_groups'];

		// Which parts of member moderation should be enabled?
		$this->moderation_areas['members']['enabled'] = Utils::$context['can_moderate_users'] || (Config::$modSettings['warning_settings'][0] == 1 && Utils::$context['can_moderate_boards']);

		$this->moderation_areas['members']['areas']['userwatch']['enabled'] = Config::$modSettings['warning_settings'][0] == 1 && Utils::$context['can_moderate_boards'];

		$this->moderation_areas['members']['areas']['reportedmembers']['enabled'] = Utils::$context['can_moderate_users'];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Main::exportStatic'))
	Main::exportStatic();

?>