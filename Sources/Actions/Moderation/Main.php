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

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Msg;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Entry point for the moderation center.
 *
 * @param bool $dont_call If true, doesn't call the function for the appropriate mod area
 */
function ModerationMain($dont_call = false)
{
	// Don't run this twice... and don't conflict with the admin bar.
	if (isset(Utils::$context['admin_area']))
		return;

	Utils::$context['can_moderate_boards'] = User::$me->mod_cache['bq'] != '0=1';
	Utils::$context['can_moderate_groups'] = User::$me->mod_cache['gq'] != '0=1';
	Utils::$context['can_moderate_approvals'] = Config::$modSettings['postmod_active'] && !empty(User::$me->mod_cache['ap']);
	Utils::$context['can_moderate_users'] = allowedTo('moderate_forum');

	// Everyone using this area must be allowed here!
	if (!Utils::$context['can_moderate_boards'] && !Utils::$context['can_moderate_groups'] && !Utils::$context['can_moderate_approvals'] && !Utils::$context['can_moderate_users'])
		isAllowedTo('access_mod_center');

	// Load the language, and the template.
	Lang::load('ModerationCenter');
	Theme::loadTemplate(false, 'admin');

	Utils::$context['admin_preferences'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : array();
	Utils::$context['robot_no_index'] = true;

	// This is the menu structure - refer to Menu.php for the details.
	$moderation_areas = array(
		'main' => array(
			'title' => Lang::$txt['mc_main'],
			'areas' => array(
				'index' => array(
					'label' => Lang::$txt['moderation_center'],
					'function' => '\\SMF\\Actions\\Moderation\\Home::call',
					'icon' => 'administration',
				),
				'settings' => array(
					'label' => Lang::$txt['mc_settings'],
					'function' => 'ModerationSettings',
					'icon' => 'features',
				),
				'modlogoff' => array(
					'label' => Lang::$txt['mc_logoff'],
					'function' => '\\SMF\\Actions\\Moderation\\EndSession::call',
					'enabled' => empty(Config::$modSettings['securityDisable_moderate']),
					'icon' => 'exit',
				),
				'notice' => array(
					'function' => '\\SMF\\Actions\\Moderation\\ShowNotice::call',
					'select' => 'index'
				),
			),
		),
		'logs' => array(
			'title' => Lang::$txt['mc_logs'],
			'areas' => array(
				'modlog' => array(
					'label' => Lang::$txt['modlog_view'],
					'enabled' => !empty(Config::$modSettings['modlog_enabled']) && Utils::$context['can_moderate_boards'],
					'file' => 'Modlog.php',
					'function' => 'ViewModlog',
					'icon' => 'logs',
				),
				'warnings' => array(
					'label' => Lang::$txt['mc_warnings'],
					'enabled' => Config::$modSettings['warning_settings'][0] == 1 && allowedTo(array('issue_warning', 'view_warning_any')),
					'function' => '\\SMF\\Actions\\Moderation\\Warnings::call',
					'icon' => 'warning',
					'subsections' => array(
						'log' => array(
							'label' => Lang::$txt['mc_warning_log'],
							'permission' => array('view_warning_any', 'moderate_forum'),
						),
						'templates' => array(
							'label' => Lang::$txt['mc_warning_templates'],
							'permission' => 'issue_warning',
						),
					),
				),
			),
		),
		'posts' => array(
			'title' => Lang::$txt['mc_posts'],
			'enabled' => Utils::$context['can_moderate_boards'] || Utils::$context['can_moderate_approvals'],
			'areas' => array(
				'postmod' => array(
					'label' => Lang::$txt['mc_unapproved_posts'],
					'enabled' => Utils::$context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'posts',
					'custom_url' => Config::$scripturl . '?action=moderate;area=postmod',
					'subsections' => array(
						'posts' => array(
							'label' => Lang::$txt['mc_unapproved_replies'],
						),
						'topics' => array(
							'label' => Lang::$txt['mc_unapproved_topics'],
						),
					),
				),
				'attachmod' => array(
					'label' => Lang::$txt['mc_unapproved_attachments'],
					'enabled' => Utils::$context['can_moderate_approvals'],
					'file' => 'PostModeration.php',
					'function' => 'PostModerationMain',
					'icon' => 'post_moderation_attach',
					'custom_url' => Config::$scripturl . '?action=moderate;area=attachmod;sa=attachments',
				),
				'reportedposts' => array(
					'label' => Lang::$txt['mc_reported_posts'],
					'enabled' => Utils::$context['can_moderate_boards'],
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'reports',
					'subsections' => array(
						'show' => array(
							'label' => Lang::$txt['mc_reportedp_active'],
						),
						'closed' => array(
							'label' => Lang::$txt['mc_reportedp_closed'],
						),
					),
				),
			),
		),
		'groups' => array(
			'title' => Lang::$txt['mc_groups'],
			'enabled' => Utils::$context['can_moderate_groups'],
			'areas' => array(
				'groups' => array(
					'label' => Lang::$txt['mc_group_requests'],
					'file' => 'Actions/Groups.php',
					'function' => 'Groups',
					'icon' => 'members_request',
					'custom_url' => Config::$scripturl . '?action=moderate;area=groups;sa=requests',
				),
				'viewgroups' => array(
					'label' => Lang::$txt['mc_view_groups'],
					'file' => 'Actions/Groups.php',
					'function' => 'Groups',
					'icon' => 'membergroups',
				),
			),
		),
		'members' => array(
			'title' => Lang::$txt['mc_members'],
			'enabled' => Utils::$context['can_moderate_users'] || (Config::$modSettings['warning_settings'][0] == 1 && Utils::$context['can_moderate_boards']),
			'areas' => array(
				'userwatch' => array(
					'label' => Lang::$txt['mc_watched_users_title'],
					'enabled' => Config::$modSettings['warning_settings'][0] == 1 && Utils::$context['can_moderate_boards'],
					'function' => '\\SMF\\Actions\\Moderation\\WatchedUsers::call',
					'icon' => 'members_watched',
					'subsections' => array(
						'member' => array(
							'label' => Lang::$txt['mc_watched_users_member'],
						),
						'post' => array(
							'label' => Lang::$txt['mc_watched_users_post'],
						),
					),
				),
				'reportedmembers' => array(
					'label' => Lang::$txt['mc_reported_members_title'],
					'enabled' => Utils::$context['can_moderate_users'],
					'file' => 'ReportedContent.php',
					'function' => 'ReportedContent',
					'icon' => 'members_watched',
					'subsections' => array(
						'open' => array(
							'label' => Lang::$txt['mc_reportedp_active'],
						),
						'closed' => array(
							'label' => Lang::$txt['mc_reportedp_closed'],
						),
					),
				),
			),
		)
	);

	// Make sure the administrator has a valid session...
	validateSession('moderate');

	// I don't know where we're going - I don't know where we've been...
	$menuOptions = array(
		'action' => 'moderate',
		'disable_url_session_check' => true,
	);

	$menu = new Menu($moderation_areas, $menuOptions);

	unset($moderation_areas);

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

	// Now - finally - the bit before the encore - the main performance of course!
	if (!$dont_call)
	{
		if (isset($menu->include_data['file']))
			require_once(Config::$sourcedir . '/' . $menu->include_data['file']);

		call_helper($menu->include_data['function']);
	}
}

/**
 * Act as an entrace for all group related activity.
 *
 * @todo As for most things in this file, this needs to be moved somewhere appropriate?
 */
function ModerateGroups()
{
	// You need to be allowed to moderate groups...
	if (User::$me->mod_cache['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Load the group templates.
	Theme::loadTemplate('ModerationCenter');

	// Setup the subactions...
	$subActions = array(
		'requests' => 'GroupRequests',
		'view' => 'ViewGroups',
	);

	if (!isset($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'view';
	Utils::$context['sub_action'] = $_GET['sa'];

	// Call the relevant function.
	call_helper($subActions[Utils::$context['sub_action']]);
}

/**
 * Change moderation preferences.
 */
function ModerationSettings()
{
	// Some useful context stuff.
	Theme::loadTemplate('ModerationCenter');
	Utils::$context['page_title'] = Lang::$txt['mc_settings'];
	Utils::$context['sub_template'] = 'moderation_settings';
	Menu::$loaded['moderate']->tab_data = array(
		'title' => Lang::$txt['mc_prefs_title'],
		'help' => '',
		'description' => Lang::$txt['mc_prefs_desc']
	);

	$pref_binary = 5;

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('mod-set');

		/* Current format of mod_prefs is:
			x|ABCD|yyy

			WHERE:
				x = Show report count on forum header.
				ABCD = Block indexes to show on moderation main page.
				yyy = Integer with the following bit status:
					- yyy & 4 = Notify about posts awaiting approval.
		*/

		// Now check other options!
		$pref_binary = 0;

		// Put it all together.
		$mod_prefs = '0||' . $pref_binary;
		User::updateMemberData(User::$me->id, array('mod_prefs' => $mod_prefs));
	}

	// What blocks does the user currently have selected?
	Utils::$context['mod_settings'] = array(
	);

	createToken('mod-set');
}

?>