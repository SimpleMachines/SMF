<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\Actions\Moderation\Posts as PostMod;
use SMF\ActionTrait;
use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Permissions\GroupPermissionSet;
use SMF\Permissions\Permission;
use SMF\Permissions\PermissionProfile;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Permissions handles all possible permission stuff.
 */
class Permissions implements ActionInterface
{
	use ActionTrait;
	use BackwardCompatibility;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'index';

	/**
	 * @var array
	 *
	 * Maps the permission groups used in the post moderation permissions UI
	 * to real permissions.
	 *
	 * Format: permission_group => array(can_do_moderated, can_do_all)
	 */
	public array $postmod_maps = [
		'new_topic' => ['post_new', 'post_unapproved_topics'],
		'replies_own' => ['post_reply_own', 'post_unapproved_replies_own'],
		'replies_any' => ['post_reply_any', 'post_unapproved_replies_any'],
		'attachment' => ['post_attachment', 'post_unapproved_attachments'],
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sub-action' => array('method_to_call', 'permission_needed')
	 */
	public static array $subactions = [
		'index' => ['index', 'manage_permissions'],
		'board' => ['board', 'manage_permissions'],
		'modify' => ['modify', 'manage_permissions'],
		'modify2' => ['modify2', 'manage_permissions'],
		'quick' => ['quick', 'manage_permissions'],
		'postmod' => ['postmod', 'manage_permissions'],
		'profiles' => ['profiles', 'manage_permissions'],
		'settings' => ['settings', 'admin_forum'],
	];

	/**
	 * @var array
	 *
	 * Organized list of permission view_groups.
	 * This ensures that permissions are presented in a stable order in the UI.
	 *
	 * Keys are permission scopes, values are lists of view_groups.
	 */
	public static array $permission_groups = [
		'global' => [
			'general',
			'pm',
			'calendar',
			'maintenance',
			'member_admin',
			'profile',
			'profile_account',
			'likes',
			'mentions',
			'bbc',
		],
		'board' => [
			'general_board',
			'topic',
			'post',
			'poll',
			'attachment',
		],
	];

	/**
	 * @var array
	 *
	 * Permission view_groups that should be shown in the left column of the UI.
	 */
	public static array $left_permission_groups = [
		'general',
		'calendar',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Permissions that are allowed or denied for the relevant membergroup.
	 * Used by the modify() method.
	 */
	protected array $allowed_denied = [
		'global' => [
			'allowed' => [],
			'denied' => [],
		],
		'board' => [
			'allowed' => [],
			'denied' => [],
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Convenience array listing permissions that certain groups may not have.
	 */
	protected static array $excluded = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatches to the right method based on the given sub-action.
	 *
	 * Checks the permissions, based on the sub-action.
	 * Called by ?action=admin;area=permissions.
	 *
	 * Uses ManagePermissions language file.
	 */
	public function execute(): void
	{
		Theme::loadTemplate('ManagePermissions');

		// Create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::getTxt('permissions_title', file: 'ManagePermissions'),
			'help' => 'permissions',
			'description' => '',
			'tabs' => [
				'index' => [
					'description' => Lang::getTxt('permissions_groups', file: 'Admin'),
				],
				'board' => [
					'description' => Lang::getTxt('permission_by_board_desc', file: 'ManagePermissions'),
				],
				'profiles' => [
					'description' => Lang::getTxt('permissions_profiles_desc', file: 'ManagePermissions'),
				],
				'postmod' => [
					'description' => Lang::getTxt('permissions_post_moderation_desc', file: 'ManagePermissions'),
				],
				'settings' => [
					'description' => Lang::getTxt('permission_settings_desc', file: 'ManagePermissions'),
				],
			],
		];

		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		$call = is_string(self::$subactions[$this->subaction][0]) && method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Sets up the permissions by membergroup index page.
	 *
	 * Called by ?action=admin;area=permissions;sa=index
	 * Creates an array of all the groups with the number of members and permissions.
	 *
	 * Uses ManagePermissions language file.
	 * Uses ManagePermissions template file.
	 * @uses template_permission_index()
	 */
	public function index(): void
	{
		Utils::$context['page_title'] = Lang::getTxt('permissions_title', file: 'ManagePermissions');

		// Load all the permissions. We'll need them for the advanced options.
		self::loadPermissionsContext();

		// Also load profiles, we may want to reset.
		PermissionProfile::loadContext();

		// Expand or collapse the advanced options?
		Utils::$context['show_advanced_options'] = empty(Utils::$context['admin_preferences']['app']);

		$this->setGroupsContext();

		// We can modify any permission set, except for the ones we can't.
		Utils::$context['can_modify'] = PermissionProfile::load((int) ($_REQUEST['pid'] ?? PermissionProfile::DEFAULT)) !== null && PermissionProfile::load((int) ($_REQUEST['pid'] ?? PermissionProfile::DEFAULT))->canModify();

		// Load the proper template.
		Utils::$context['sub_template'] = 'permission_index';
		SecurityToken::create('admin-mpq');
	}

	/**
	 * Handle permissions by board... more or less. :P
	 */
	public function board(): void
	{
		Utils::$context['page_title'] = Lang::getTxt('permissions_boards', file: 'Admin');
		Utils::$context['edit_all'] = isset($_GET['edit']);

		// Saving?
		if (!empty($_POST['save_changes']) && !empty($_POST['boardprofile'])) {
			User::$me->checkSession('request');
			SecurityToken::validate('admin-mpb');

			$changes = [];

			foreach ($_POST['boardprofile'] as $p_board => $profile) {
				if (PermissionProfile::load((int) $profile) !== null) {
					$changes[(int) $profile][] = (int) $p_board;
				}
			}

			if (!empty($changes)) {
				foreach ($changes as $profile => $boards) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET id_profile = {int:current_profile}
						WHERE id_board IN ({array_int:board_list})',
						[
							'board_list' => $boards,
							'current_profile' => $profile,
						],
					);
				}
			}

			Utils::$context['edit_all'] = false;
		}

		// Load all permission profiles.
		PermissionProfile::loadContext();

		// Get the board tree.
		Category::getTree();

		// Build the list of the boards.
		Utils::$context['categories'] = [];

		foreach (Category::$loaded as $catid => $tree) {
			Utils::$context['categories'][$catid] = [
				'name' => &$tree->name,
				'id' => &$tree->id,
				'boards' => [],
			];

			foreach (Category::$boardList[$catid] as $boardid) {
				if (!isset(Utils::$context['profiles'][Board::$loaded[$boardid]->profile])) {
					Board::$loaded[$boardid]->profile = PermissionProfile::DEFAULT;
				}

				Utils::$context['categories'][$catid]['boards'][$boardid] = [
					'id' => &Board::$loaded[$boardid]->id,
					'name' => &Board::$loaded[$boardid]->name,
					'description' => &Board::$loaded[$boardid]->description,
					'child_level' => &Board::$loaded[$boardid]->child_level,
					'profile' => &Board::$loaded[$boardid]->profile,
					'profile_name' => Utils::$context['profiles'][Board::$loaded[$boardid]->profile]['name'],
				];
			}
		}

		Utils::$context['sub_template'] = 'by_board';
		SecurityToken::create('admin-mpb');
	}

	/**
	 * Handles permission modification actions from the "advanced options"
	 * section of the permission manager index.
	 */
	public function quick(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpq', 'quick');

		if ($_POST['copy_from'] === 'empty') {
			$_POST['copy_from'] = 0;
		}

		// Make sure only one of the quick options was selected.
		if (!empty($_POST['predefined']) + !empty($_POST['permissions']) + !empty($_POST['copy_from']) > 1) {
			ErrorHandler::fatalLang('permissions_only_one_option', false);
		}

		// Only accept numeric values for selected membergroups.
		$_POST['group'] = array_unique(array_map('intval', (array) ($_POST['group'] ?? [])));

		// No groups were selected.
		if (empty($_POST['group'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		// Profile ID must be an integer.
		$_REQUEST['pid'] = (int) ($_REQUEST['pid'] ?? 0);

		// Sorry, but that one can't be modified.
		if (
			PermissionProfile::load($_REQUEST['pid']) === null
			|| !PermissionProfile::load($_REQUEST['pid'])->canModify()
		) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Clear out any cached authority.
		Config::updateModSettings(['settings_updated' => time()]);

		// Set a predefined permission profile.
		if (!empty($_POST['predefined'])) {
			$this->quickSetPredefined();
		}
		// Set a permission profile based on the permissions of a selected group.
		elseif (!empty($_POST['copy_from'])) {
			$this->quickCopyFrom();
		}
		// Set or unset a certain permission for the selected groups.
		elseif (!empty($_POST['permissions'])) {
			$this->quickSetPermission();
		}

		self::updateBoardManagers();

		Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
	}

	/**
	 * Initializes the necessary stuff to modify a membergroup's permissions.
	 */
	public function modify(): void
	{
		// First, which membergroup are we working on?
		$this->setGroupContext();

		// Next, which permission profile are we working with?
		$this->setProfileContext();

		$this->setAllowedDenied(Utils::$context['group']['id'], Utils::$context['permission_type'], Utils::$context['profile']['id']);

		$this->setOnOff();

		Utils::$context['sub_template'] = 'modify_group';
		Utils::$context['page_title'] = Lang::getTxt('permissions_modify_group', file: 'ManagePermissions');

		SecurityToken::create('admin-mp');
	}

	/**
	 * This method actually saves modifications to a membergroup's board permissions.
	 */
	public function modify2(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mp');

		// Can't do anything without these.
		if (!isset($_GET['group'], $_GET['pid'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		$_GET['group'] = (int) $_GET['group'];
		$_GET['pid'] = (int) $_GET['pid'];

		// Group needs to be valid.
		if ($_GET['group'] < Group::GUEST) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Verify this isn't inherited.
		if (current(Group::load($_GET['group']))->parent != Group::NONE) {
			ErrorHandler::fatalLang('cannot_edit_permissions_inherited');
		}

		$permission_profile = PermissionProfile::load($_GET['pid'] === 0 ? PermissionProfile::DEFAULT : $_GET['pid']);

		// Permission profile needs to be valid.
		if (!($permission_profile instanceof PermissionProfile)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// No, you can't modify this permission profile.
		if (!$permission_profile->canModify()) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Update the permissions.
		if (is_array($_POST['perm'] ?? null)) {
			// Load the relevant permission set.
			$set = current(GroupPermissionSet::load($permission_profile->id, $_GET['group']));

			// Update the permission set.
			foreach ($set->permissions as $name => $old_value) {
				$permission = Permission::get($name);

				// Only change global permissions when $_GET['pid'] is 0.
				if ($permission->scope !== ($_GET['pid'] === 0 ? 'global' : 'board')) {
					continue;
				}

				switch ($_POST['perm'][$permission->scope][$name] ?? null) {
					case 'on':
						$set->permissions[$name] = 1;
						break;

					case 'deny':
						$set->permissions[$name] = 0;
						break;

					default:
						$set->permissions[$name] = null;
						break;
				}
			}

			// Save the permission set.
			$set->save();
		}

		// Ensure Config::$modSettings['board_manager_groups'] is up to date.
		self::updateBoardManagers();

		// Clear cached permissions.
		Config::updateModSettings(['settings_updated' => time()]);

		Utils::redirectexit('action=admin;area=permissions' . (!empty($_GET['pid']) ? ';pid=' . $_GET['pid'] : ''));
	}

	/**
	 * A screen to set some general settings for permissions.
	 */
	public function settings(): void
	{
		// All the setting variables
		$config_vars = self::getConfigVars();

		Utils::$context['page_title'] = Lang::getTxt('permission_settings_title', file: 'ManagePermissions');
		Utils::$context['sub_template'] = 'show_settings';

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=permissions;save;sa=settings';

		// Saving the settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_permission_settings');

			ACP::saveDBSettings($config_vars);

			// Clear all deny permissions... if we want that.
			if (empty(Config::$modSettings['permission_enable_deny'])) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}permissions
					WHERE add_deny = {int:denied}',
					[
						'denied' => 0,
					],
				);
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE add_deny = {int:denied}',
					[
						'denied' => 0,
					],
				);
			}

			// Make sure there are no postgroup based permissions left.
			if (empty(Config::$modSettings['permission_enable_postgroups'])) {
				// Get a list of postgroups.
				$post_groups = [];

				$request = Db::$db->query(
					'',
					'SELECT id_group
					FROM {db_prefix}membergroups
					WHERE min_posts != {int:min_posts}',
					[
						'min_posts' => -1,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$post_groups[] = $row['id_group'];
				}
				Db::$db->free_result($request);

				// Remove'em.
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:post_group_list})',
					[
						'post_group_list' => $post_groups,
					],
				);

				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:post_group_list})',
					[
						'post_group_list' => $post_groups,
					],
				);

				Db::$db->query(
					'',
					'UPDATE {db_prefix}membergroups
					SET id_parent = {int:not_inherited}
					WHERE id_parent IN ({array_int:post_group_list})',
					[
						'post_group_list' => $post_groups,
						'not_inherited' => -2,
					],
				);
			}

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=permissions;sa=settings');
		}

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Add/Edit/Delete profiles.
	 */
	public function profiles(): void
	{
		// Setup the template.
		Utils::$context['page_title'] = Lang::getTxt('permissions_profile_edit', file: 'ManagePermissions');
		Utils::$context['sub_template'] = 'edit_profiles';

		// If we're creating a new one do it first.
		if (isset($_POST['create']) && trim($_POST['profile_name']) != '') {
			$this->createProfile();
		}
		// Renaming?
		elseif (isset($_POST['rename'])) {
			$this->renameProfile();
		}
		// Deleting?
		elseif (isset($_POST['delete']) && !empty($_POST['delete_profile'])) {
			$this->deleteProfile();
		}

		// Clearly, we'll need this!
		PermissionProfile::loadContext();

		// Work out what ones are in use.
		foreach (PermissionProfile::loadAll() as $profile) {
			Utils::$context['profiles'][$profile->id]['in_use'] = !empty($profile->boards());
			Utils::$context['profiles'][$profile->id]['boards'] = count($profile->boards());
			Utils::$context['profiles'][$profile->id]['boards_text'] = Lang::getTxt(
				'permissions_profile_used_by_count',
				[count($profile->boards())],
				file: 'ManagePermissions',
			);
		}

		// What can we do with these?
		Utils::$context['can_rename_something'] = false;

		foreach (PermissionProfile::loadAll() as $profile) {
			// Can't rename the special ones.
			Utils::$context['profiles'][$profile->id]['can_rename'] = !$profile->isPredefined();

			if (Utils::$context['profiles'][$profile->id]['can_rename']) {
				Utils::$context['can_rename_something'] = true;
			}

			// You can only delete it if you can rename it AND it's not in use.
			Utils::$context['profiles'][$profile->id]['can_delete'] = !$profile->isPredefined() && empty($profile->boards());
		}

		SecurityToken::create('admin-mpp');
	}

	/**
	 * Present a nice way of applying post moderation.
	 */
	public function postmod(): void
	{
		// Just in case.
		User::$me->checkSession('get');

		Utils::$context['page_title'] = Lang::getTxt('permissions_post_moderation', file: 'Admin');
		Utils::$context['sub_template'] = 'postmod_permissions';
		Utils::$context['current_profile'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 1;

		// Load all the permission profiles.
		PermissionProfile::loadContext();

		IntegrationHook::call('integrate_post_moderation_mapping', [&$this->postmod_maps]);

		// Start this with the guests/members.
		Utils::$context['profile_groups'] = [
			Group::GUEST => new Group(Group::GUEST, [
				'name' => Lang::getTxt('membergroups_guests', file: 'Admin'),
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'attachment' => 'disallow',
			]),
			Group::REGULAR => new Group(Group::REGULAR, [
				'name' => Lang::getTxt('membergroups_members', file: 'Admin'),
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'attachment' => 'disallow',
			]),
		];

		// Load the groups.
		$query_customizations = [
			'where' => [
				'id_group != {int:admin_group}',
				'id_parent = {int:no_parent}',
			],
			'order' => ['id_parent ASC'],
			'params' => [
				'admin_group' => Group::ADMIN,
				'no_parent' => Group::NONE,
			],
		];

		if (empty(Config::$modSettings['permission_enable_postgroups'])) {
			$query_customizations['where'][] = 'min_posts = {int:min_posts}';
			$query_customizations['params']['min_posts'] = -1;
		}

		foreach (Group::load([], $query_customizations) as $group) {
			// Get a list of the child groups as well.
			$group->getChildren();

			// Add some custom properties.
			foreach ($this->postmod_maps as $permission_group => $data) {
				$group->{$permission_group} = 'disallow';
			}

			Utils::$context['profile_groups'][$group->id] = $group;
		}

		// Load all the permission sets.
		$sets = GroupPermissionSet::load(
			Utils::$context['current_profile'],
			array_keys(Utils::$context['profile_groups']),
			true,
		);

		// If we're saving the changes then do just that - save them.
		if (
			!empty($_POST['save_changes'])
			&& PermissionProfile::load(Utils::$context['current_profile'])->canModify()
		) {
			SecurityToken::validate('admin-mppm');

			// First, are we saving a new value for enabled post moderation?
			$new_setting = !empty($_POST['postmod_active']);

			if ($new_setting != Config::$modSettings['postmod_active']) {
				if ($new_setting) {
					// Turning it on. This seems easy enough.
					Config::updateModSettings(['postmod_active' => 1]);
				} else {
					// Turning it off. Not so straightforward. We have to turn off warnings to moderation level, and make everything approved.
					Config::updateModSettings([
						'postmod_active' => 0,
						'warning_moderate' => 0,
					]);

					PostMod::approveAllData();
				}
			} elseif (Config::$modSettings['postmod_active']) {
				// We're not saving a new setting - and if it's still enabled we have more work to do.
				foreach ($sets as $set) {
					foreach ($this->postmod_maps as $permission_group => $data) {
						if (!isset($_POST[$permission_group][$set->group])) {
							continue;
						}

						foreach ($set->permissions as $permission_name => $value) {
							if (!in_array($permission_name, $data)) {
								continue;
							}

							if (
								$_POST[$permission_group][$set->group] == 'allow'
								|| (
									$_POST[$permission_group][$set->group] == 'moderate'
									&& $permission_name === $data[1]
								)
							) {
								$set->permissions[$permission_name] = 1;
							}
						}
					}

					$set->save();
				}
			}
		}

		// Add the status for each permission to our context array.
		foreach ($sets as $set) {
			foreach ($this->postmod_maps as $permission_group => $data) {
				foreach ($data as $key => $permission_name) {
					if (!empty($set->permissions[$permission_name])) {
						// Full allowance?
						if ($key == 0) {
							Utils::$context['profile_groups'][$set->group][$permission_group] = 'allow';
						}
						// Otherwise only bother with moderate if not on allow.
						elseif (Utils::$context['profile_groups'][$set->group][$permission_group] != 'allow') {
							Utils::$context['profile_groups'][$set->group][$permission_group] = 'moderate';
						}
					}
				}
			}
		}

		SecurityToken::create('admin-mppm');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the permissions area.
	 */
	public static function getConfigVars(): array
	{
		// All the setting variables
		$config_vars = [
			['title', 'settings'],
			// Inline permissions.
			['permissions', 'manage_permissions'],
			'',

			// A few useful settings
			['check', 'permission_enable_deny', 0, Lang::getTxt('permission_settings_enable_deny', file: 'ManagePermissions'), 'help' => 'permissions_deny'],
			['check', 'permission_enable_postgroups', 0, Lang::getTxt('permission_settings_enable_postgroups', file: 'ManagePermissions'), 'help' => 'permissions_postgroups'],
		];

		IntegrationHook::call('integrate_modify_permission_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Set the permission level for a specific profile, group, or group for a profile.
	 *
	 * @param string $level The level ('restrict', 'standard', etc.)
	 * @param int $group The group to set the permission for
	 * @param string|int $profile The ID of the permissions profile or 'null' if we're setting it for a group
	 */
	public static function setPermissionLevel(string $level, int $group, string|int $profile = 'null'): void
	{
		// Levels by group... restrict, standard, moderator, maintenance.
		$group_levels = [
			'board' => ['inherit' => []],
			'group' => ['inherit' => []],
		];
		// Levels by board... standard, publish, free.
		$board_levels = ['inherit' => []];

		foreach (Permission::getAll() as $perm) {
			if (isset($perm->group_level)) {
				switch ($perm->group_level) {
					case Permission::GROUP_LEVEL_RESTRICT:
						$group_levels[$perm->scope]['restrict'][] = $perm->name;
						// no break

					case Permission::GROUP_LEVEL_STANDARD:
						$group_levels[$perm->scope]['standard'][] = $perm->name;
						// no break

					case Permission::GROUP_LEVEL_MODERATOR:
						$group_levels[$perm->scope]['moderator'][] = $perm->name;
						// no break

					case Permission::GROUP_LEVEL_MAINTENANCE:
						$group_levels[$perm->scope]['maintenance'][] = $perm->name;
						break;
				}
			}

			if (isset($perm->board_level)) {
				switch ($perm->board_level) {
					case Permission::BOARD_LEVEL_STANDARD:
						$board_levels['standard'][] = $perm->name;
						// no break

					case Permission::BOARD_LEVEL_LOCKED:
						$board_levels['locked'][] = $perm->name;
						// no break

					case Permission::BOARD_LEVEL_PUBLISH:
						$board_levels['publish'][] = $perm->name;
						// no break

					case Permission::BOARD_LEVEL_FREE:
						$board_levels['free'][] = $perm->name;
						break;
				}
			}
		}

		IntegrationHook::call('integrate_load_permission_levels', [&$group_levels, &$board_levels]);

		// Make sure we're not granting someone too many permissions!
		foreach (['global', 'board'] as $scope) {
			foreach ($group_levels[$scope][$level] as $k => $permission) {
				if (
					!Permission::get($permission)->canAssign()
					|| !Permission::get($permission)->canBeGrantedTo($group)
				) {
					unset($group_levels[$scope][$level][$k]);
				}
			}
		}

		// Reset all cached permissions.
		Config::updateModSettings(['settings_updated' => time()]);

		// Setting group permissions.
		if ($profile === 'null' && $group !== 'null') {
			if (empty($group_levels['global'][$level])) {
				return;
			}

			$set = current(GroupPermissionSet::load(PermissionProfile::DEFAULT, (int) $group));

			foreach ($set->permissions as $permission_name => $value) {
				// Make sure we're not granting someone too many permissions!
				if (
					!Permission::get($permission_name)->canAssign()
					|| !Permission::get($permission_name)->canBeGrantedTo($set->group)
				) {
					continue;
				}

				$set->permissions[$permission_name] = in_array($permission_name, $group_levels['global'][$level]) || in_array($permission_name, $group_levels['board'][$level]) ? 1 : null;
			}

			$set->save();
		}
		// Setting profile permissions for a specific group.
		elseif (
			$profile !== 'null'
			&& $group !== 'null'
			&& PermissionProfile::load((int) $profile) !== null
			&& PermissionProfile::load((int) $profile)->canModify()
		) {
			$set = current(GroupPermissionSet::load((int) $profile, (int) $group));

			foreach ($set->permissions as $permission_name => $value) {
				// Make sure we're not granting someone too many permissions!
				if (
					!Permission::get($permission_name)->canAssign()
					|| !Permission::get($permission_name)->canBeGrantedTo($set->group)
				) {
					continue;
				}

				$set->permissions[$permission_name] = in_array($permission_name, $group_levels['board'][$level]) ? 1 : null;
			}

			$set->save();
		}
		// Setting profile permissions for all groups.
		elseif (
			$profile !== 'null'
			&& $group === 'null'
			&& PermissionProfile::load((int) $profile) !== null
			&& PermissionProfile::load((int) $profile)->canModify()
		) {
			$groups = array_filter(
				Group::getAll(),
				fn($group) => $group === Group::REGULAR || $group > Group::MOD,
			);

			foreach (GroupPermissionSet::load((int) $profile, $groups) as $set) {
				foreach ($set->permissions as $permission_name => $value) {
					// Make sure we're not granting someone too many permissions!
					if (
						!Permission::get($permission_name)->canAssign()
						|| !Permission::get($permission_name)->canBeGrantedTo($set->group)
					) {
						continue;
					}

					$set->permissions[$permission_name] = in_array($permission_name, $board_levels[$level] ?? []) ? 1 : null;
				}

				$set->save();
			}
		}
		// $profile and $group are both null!
		else {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Make sure Config::$modSettings['board_manager_groups'] is up to date.
		if (Permission::get('manage_boards')->canAssign()) {
			self::updateBoardManagers();
		}
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variable for each permission.
	 * This method is used by several settings screens to set specific permissions.
	 *
	 * To exclude groups from the form for a given permission, add the group IDs as
	 * an array to Utils::$context['excluded_permissions'][$permission]. For backwards
	 * compatibility, it is also possible to pass group IDs in via the
	 * $excluded_groups parameter, which will exclude the groups from the forms for
	 * all of the permissions passed in via $permissions.
	 *
	 * @param array $permissions The permissions to display inline
	 * @param array $excluded_groups The IDs of one or more groups to exclude
	 *
	 * Uses ManagePermissions language
	 * Uses ManagePermissions template
	 */
	public static function init_inline_permissions(array $permissions, array $excluded_groups = []): void
	{
		Theme::loadTemplate('ManagePermissions');
		Utils::$context['can_change_permissions'] = User::$me->allowedTo('manage_permissions');

		// Nothing to initialize here.
		if (!Utils::$context['can_change_permissions']) {
			return;
		}

		// Make sure this is an array of integers.
		// Can't blindly cast to int because we don't want invalid ones to become 0.
		$excluded_groups = array_unique(array_merge(
			[Group::ADMIN, Group::MOD],
			array_map(
				'intval',
				array_filter(
					(array) $excluded_groups,
					fn($v) => is_int($v) || is_string($v) && intval($v) == $v,
				),
			),
		));

		$query_customizations = [
			'where' => [
				'mg.id_group NOT IN ({array_int:excluded_groups})',
				'mg.id_parent = {int:not_inherited}',
				empty(Config::$modSettings['permission_enable_postgroups']) ? 'mg.min_posts = {int:min_posts}' : '1=1',
			],
			'order' => [
				'mg.min_posts',
				'CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE {int:newbie_group} END',
				'mg.group_name',
			],
			'params' => [
				'min_posts' => -1,
				'excluded_groups' => [Group::ADMIN, Group::MOD],
				'not_inherited' => Group::NONE,
				'newbie_group' => Group::NEWBIE,
			],
		];

		$groups = array_merge(
			Group::load([Group::GUEST, Group::REGULAR]),
			Group::load([], $query_customizations),
		);

		foreach (
			GroupPermissionSet::load(
				PermissionProfile::DEFAULT,
				array_map(fn($group) => $group->id, $groups),
			) as $set
		) {
			$group = Group::$loaded[$set->group];

			foreach ($permissions as $permission) {
				if (
					Permission::get($permission)->scope !== 'global'
					|| !Permission::get($permission)->canAssign()
					|| !Permission::get($permission)->canBeGrantedTo($group->id)
				) {
					continue;
				}

				Utils::$context[$permission][$group->id] = [
					'id' => $group->id,
					'name' => $group->name,
					'is_postgroup' => $group->min_posts > -1,
					'status' => !isset($set->permissions[$permission]) ? 'off' : ($set->permissions[$permission] === 1 ? 'on' : 'deny'),
				];
			}
		}

		// There's no point showing a form with nobody in it.
		foreach ($permissions as $permission) {
			if (empty(Utils::$context[$permission])) {
				unset(Utils::$context['config_vars'][$permission], Utils::$context[$permission]);
			}
		}

		// Create the token for the separate inline permission verification.
		SecurityToken::create('admin-mp');
	}

	/**
	 * Show a collapsible box to set a specific permission.
	 * The method is called by templates to show a list of permissions settings.
	 * Calls the template function template_inline_permissions().
	 *
	 * @param string $permission The permission to display inline
	 */
	public static function theme_inline_permissions(string $permission): void
	{
		Utils::$context['current_permission'] = $permission;
		Utils::$context['member_groups'] = Utils::$context[$permission];

		template_inline_permissions();
	}

	/**
	 * Save the permissions of a form containing inline permissions.
	 *
	 * @param array $permissions The permissions to save
	 */
	public static function save_inline_permissions(array $permissions): void
	{
		// No permissions? Not a great deal to do here.
		if (!User::$me->allowedTo('manage_permissions')) {
			return;
		}

		// Almighty session check, verify our ways.
		User::$me->checkSession();
		SecurityToken::validate('admin-mp');

		$groups = [];

		foreach ($permissions as $permission_name) {
			if (isset($_POST[$permission_name])) {
				$groups = array_unique(array_merge($groups, array_map('intval', array_keys($_POST[$permission_name]))));
			}
		}

		foreach (GroupPermissionSet::load(PermissionProfile::DEFAULT, $groups) as $set) {
			foreach ($permissions as $permission_name) {
				$permission = Permission::get($permission_name);

				if ($permission->scope !== 'global' || !$permission->canAssign()) {
					continue;
				}

				$new_value = !isset($_POST[$permission->name][$set->group]) ? null : ($_POST[$permission->name][$set->group] == 'on' ? 1 : 0);

				if (
					$new_value === 1
					&& (
						!$permission->canBeGrantedTo($set->group)
						|| in_array(
							$set->group,
							Utils::$context['excluded_permissions'][$permission->name] ?? [],
						)
					)
				) {
					$new_value === null;
				}

				$set->permissions[$permission->name] = $new_value;
			}

			$set->save();
		}

		// Make sure Config::$modSettings['board_manager_groups'] is up to date.
		if (Permission::get('manage_boards')->canAssign()) {
			self::updateBoardManagers();
		}

		Config::updateModSettings(['settings_updated' => time()]);
	}

	/**
	 * Makes sure Config::$modSettings['board_manager_groups'] is up to date.
	 */
	public static function updateBoardManagers(): void
	{
		Config::updateModSettings(['board_manager_groups' => implode(',', Group::getAllowedTo('manage_boards'))], true);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		IntegrationHook::call('integrate_manage_permissions', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Populates Utils::$context['groups'].
	 *
	 * Helper method called from index().
	 */
	protected function setGroupsContext(): void
	{
		Utils::$context['groups'] = [];

		foreach (Group::loadSimple(Group::LOAD_BOTH, []) as $group) {
			if (
				// Skip child groups.
				$group->parent !== Group::NONE
				// Skip post groups if post group permissions are disabled.
				|| (
					empty(Config::$modSettings['permission_enable_postgroups'])
					&& $group->min_posts > -1
				)
			) {
				continue;
			}

			Utils::$context['groups'][$group->id] = $group;
		}

		// Count the members that each group has (except moderators).
		Group::countMembersBatch(array_diff(array_keys(Utils::$context['groups']), [Group::MOD]));

		// Count the permissions that each group has.
		if (!empty($_REQUEST['pid'])) {
			$_REQUEST['pid'] = (int) $_REQUEST['pid'];

			if (!isset(Utils::$context['profiles'][$_REQUEST['pid']])) {
				ErrorHandler::fatalLang('no_access', false);
			}

			// Change the selected tab to better reflect that this really is a board profile.
			Menu::$loaded['admin']['current_subsection'] = 'profiles';

			Utils::$context['profile'] = [
				'id' => $_REQUEST['pid'],
				'name' => Utils::$context['profiles'][$_REQUEST['pid']]['name'],
			];
		}

		foreach (Utils::$context['groups'] as $group) {
			$group->countPermissions(isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 0);

			// A few overrides.
			if ($group->id === Group::GUEST) {
				$group->num_permissions['denied'] = '(' . Lang::getTxt('permissions_none', file: 'ManagePermissions') . ')';
			}

			if ($group->id === Group::ADMIN) {
				$group->num_permissions['allowed'] = '(' . Lang::getTxt('permissions_all', file: 'ManagePermissions') . ')';
				$group->num_permissions['denied'] = '(' . Lang::getTxt('permissions_none', file: 'ManagePermissions') . ')';
			}
		}
	}

	/**
	 * Sets a predefined permission profile.
	 *
	 * Helper method called from quick().
	 */
	protected function quickSetPredefined(): void
	{
		// Make sure it's a predefined permission set we expect.
		if (!in_array($_POST['predefined'], ['restrict', 'standard', 'moderator', 'maintenance'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		$level = constant(Permission::class . '::GROUP_LEVEL_' . strtoupper($_POST['predefined']));

		foreach (Group::load(array_map('intval', $_POST['group'])) as $group) {
			$group->setPermissionsByLevel($level, (int) ($_REQUEST['pid'] ?? PermissionProfile::DEFAULT));
		}
	}

	/**
	 * Sets a permission profile based on the permissions of a selected group.
	 *
	 * Helper method called from quick().
	 */
	protected function quickCopyFrom(): void
	{
		$pid = max(PermissionProfile::DEFAULT, (int) $_REQUEST['pid']);

		// Just checking the input.
		if (!is_numeric($_POST['copy_from'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		$_POST['copy_from'] = (int) $_POST['copy_from'];

		// Make sure the group we're copying to is never included.
		$_POST['group'] = array_diff(array_map('intval', $_POST['group']), [$_POST['copy_from']]);

		// No groups left? Too bad.
		if (empty($_POST['group'])) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		$from_set = current(GroupPermissionSet::load($pid, $_POST['copy_from']));

		foreach (GroupPermissionSet::load($pid, $_POST['group']) as $to_set) {
			foreach ($from_set->permissions as $permission_name => $value) {
				// Only do global permissions if $_REQUEST['pid'] was empty.
				if (Permission::get($permission_name)->scope === 'global' && !empty($_REQUEST['pid'])) {
					continue;
				}

				// No dodgy permissions please!
				if (
					!Permission::get($permission_name)->canAssign()
					|| !Permission::get($permission_name)->canBeGrantedTo($to_set->group)
				) {
					continue;
				}

				$to_set->permissions[$permission_name] = $value;
			}

			$to_set->save();
		}
	}

	/**
	 * Sets or unsets a certain permission for the selected groups.
	 *
	 * Helper method called from quick().
	 */
	protected function quickSetPermission(): void
	{
		$pid = max(PermissionProfile::DEFAULT, (int) $_REQUEST['pid']);

		$groups = array_map('intval', (array) $_POST['group']);

		// Unpack two variables that were transported.
		list($scope, $permission) = explode('/', $_POST['permissions']);

		// Check whether our input is within expected range.
		if (
			!in_array($_POST['add_remove'], ['add', 'clear', 'deny'])
			|| !in_array($scope, ['global', 'board'])
			|| !Permission::get($permission)->canAssign()
			|| Permission::get($permission)->scope !== $scope
		) {
			Utils::redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
		}

		foreach (GroupPermissionSet::load($pid, $groups) as $set) {
			if ($_POST['add_remove'] === 'add' && !Permission::get($permission)->canBeGrantedTo($set->group)) {
				continue;
			}

			$set->permissions[$permission] = $_POST['add_remove'] === 'add' ? 1 : ($_POST['add_remove'] === 'deny' ? 0 : null);

			$set->save();
		}
	}

	/**
	 * Populates Utils::$context['group'].
	 *
	 * Helper method called from modify().
	 */
	protected function setGroupContext(): void
	{
		if (!isset($_GET['group']) || (int) $_GET['group'] < -1) {
			ErrorHandler::fatalLang('no_access', false);
		}

		Utils::$context['group']['id'] = (int) $_GET['group'];

		switch (Utils::$context['group']['id']) {
			case -1:
				Utils::$context['group']['name'] = Lang::getTxt('membergroups_guests', file: 'Admin');
				break;

			case 0:
				Utils::$context['group']['name'] = Lang::getTxt('membergroups_members', file: 'Admin');
				break;

			// Can't set permissions for admins.
			case 1:
				Utils::redirectexit('action=admin;area=permissions');
				break;

			default:
				$result = Db::$db->query(
					'',
					'SELECT group_name, id_parent
					FROM {db_prefix}membergroups
					WHERE id_group = {int:current_group}
					LIMIT 1',
					[
						'current_group' => Utils::$context['group']['id'],
					],
				);
				list(Utils::$context['group']['name'], $parent) = Db::$db->fetch_row($result);
				Db::$db->free_result($result);

				// Cannot edit an inherited group!
				if ($parent != -2) {
					ErrorHandler::fatalLang('cannot_edit_permissions_inherited');
				}
				break;
		}
	}

	/**
	 * Sets Utils::$context['profile'] and Utils::$context['permission_type'].
	 *
	 * Helper method called from modify().
	 */
	protected function setProfileContext(): void
	{
		PermissionProfile::loadContext();

		Utils::$context['profile']['id'] = (int) ($_GET['pid'] ?? 0);

		// If this is a moderator and they are editing "no profile" then we only do boards.
		if (Utils::$context['group']['id'] == Group::MOD && empty(Utils::$context['profile']['id'])) {
			// For sanity just check they have no general permissions.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}permissions
				WHERE id_group = {int:moderator_group}',
				[
					'moderator_group' => Group::MOD,
				],
			);

			Utils::$context['profile']['id'] = PermissionProfile::DEFAULT;
		}

		Utils::$context['permission_type'] = empty(Utils::$context['profile']['id']) ? 'global' : 'board';

		Utils::$context['profile']['can_modify'] = !Utils::$context['profile']['id'] || Utils::$context['profiles'][Utils::$context['profile']['id']]['can_modify'];

		// Set up things a little nicer for board related stuff...
		if (Utils::$context['permission_type'] == 'board') {
			Utils::$context['profile']['name'] = Utils::$context['profiles'][Utils::$context['profile']['id']]['name'];

			Menu::$loaded['admin']['current_subsection'] = 'profiles';
		}
	}

	/**
	 * Fetches the current allowed or denied values stored in the database for
	 * each permission, and populates $this->allowed_denied with those values.
	 *
	 * Helper method called from modify().
	 *
	 * @param int $group ID number of a membergroup.
	 * @param string $scope Either 'global' or 'board'. If this is 'global', the
	 *    $profile param will always be treated as PermissionProfile::DEFAULT.
	 * @param int $profile Permission profile to use. Only applicable when the
	 *    $scope param is set to 'board'.
	 */
	protected function setAllowedDenied(int $group, string $scope = 'global', int $profile = PermissionProfile::DEFAULT): void
	{
		$profile = $scope == 'global' ? PermissionProfile::DEFAULT : $profile;

		// General permissions?
		if ($scope == 'global') {
			foreach (current(GroupPermissionSet::load($profile, $group))->permissions as $perm => $add_deny) {
				if (is_null($add_deny)) {
					continue;
				}

				$this->allowed_denied['global'][empty($add_deny) ? 'denied' : 'allowed'][] = $perm;
			}
		}

		// Fetch current board permissions...
		foreach (current(GroupPermissionSet::load($profile, $group))->permissions as $perm => $add_deny) {
			if (is_null($add_deny)) {
				continue;
			}

			$this->allowed_denied['board'][empty($add_deny) ? 'denied' : 'allowed'][] = $perm;
		}
	}

	/**
	 * Sets 'select' for each permission in Utils::$context['permissions'].
	 * Also populates Utils::$context['hidden_perms'].
	 *
	 * Helper method called from modify().
	 */
	protected function setOnOff(): void
	{
		self::loadPermissionsContext();
		Utils::$context['hidden_perms'] = [];

		// Loop through each permission and set whether it's on, off, or denied.
		foreach (Utils::$context['permissions'] as $scope => $tmp) {
			foreach ($tmp['columns'] as $position => $permission_groups) {
				foreach ($permission_groups as $group_name => $group) {
					foreach ($group['permissions'] as $perm) {
						// Create a shortcut for the current permission.
						$cur_perm = &Utils::$context['permissions'][$scope]['columns'][$position][$group_name]['permissions'][$perm['id']];

						if ($perm['has_own_any']) {
							$cur_perm['any']['select'] = in_array($perm['id'] . '_any', $this->allowed_denied[$scope]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $this->allowed_denied[$scope]['denied']) ? 'deny' : 'off');

							$cur_perm['own']['select'] = in_array($perm['id'] . '_own', $this->allowed_denied[$scope]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $this->allowed_denied[$scope]['denied']) ? 'deny' : 'off');
						} else {
							$cur_perm['select'] = in_array($perm['id'], $this->allowed_denied[$scope]['denied']) ? 'deny' : (in_array($perm['id'], $this->allowed_denied[$scope]['allowed']) ? 'on' : 'off');
						}

						// Keep the last value if it's hidden.
						if (!empty($perm['hidden']) || !empty($group['hidden'])) {
							if ($perm['has_own_any']) {
								Utils::$context['hidden_perms'][] = [
									$scope,
									$perm['own']['id'],
									$cur_perm['own']['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $cur_perm['own']['select'],
								];

								Utils::$context['hidden_perms'][] = [
									$scope,
									$perm['any']['id'],
									$cur_perm['any']['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $cur_perm['any']['select'],
								];
							} else {
								Utils::$context['hidden_perms'][] = [
									$scope,
									$perm['id'],
									$cur_perm['select'] == 'deny' && !empty(Config::$modSettings['permission_enable_deny']) ? 'deny' : $cur_perm['select'],
								];
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Creates a new permission profile by copying an existing one.
	 *
	 * The ID of the profile to copy must be given in $_POST['copy_from'].
	 * The name for the new profile must be given in $_POST['profile_name'].
	 */
	protected function createProfile(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpp');

		PermissionProfile::copy((int) $_POST['copy_from'], Utils::htmlspecialchars($_POST['profile_name']));
	}

	/**
	 * Renames one or more permission profiles.
	 *
	 * Acts on the profiles listed in $_POST['rename_profile'], where keys are
	 * ID numbers of existing profiles, and values are the new names.
	 *
	 * If $_POST['rename_profile'] is not set, this method will instead instruct
	 * the UI to show input fields to allow the admin to rename the profiles.
	 */
	protected function renameProfile(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpp');

		// Just showing the input fields?
		if (!isset($_POST['rename_profile'])) {
			Utils::$context['show_rename_boxes'] = true;

			return;
		}

		foreach ($_POST['rename_profile'] as $id => $value) {
			if (($id = (int) $id) <= 0) {
				continue;
			}

			if (
				Utils::htmlTrim($value) != ''
				&& ($profile = PermissionProfile::load($id)) !== null
				&& !$profile->isPredefined()
			) {
				$profile->name = Utils::htmlspecialchars($value);
				$profile->save();
			}
		}
	}

	/**
	 * Deletes one or more permission profiles.
	 *
	 * Acts on profiles listed in $_POST['delete_profile'], which must be an
	 * array of profile ID numbers.
	 *
	 * Attempts to delete predefined profiles will be silently rejected.
	 *
	 * Attempts to delete profiles that are in use will abort with an error.
	 */
	protected function deleteProfile(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-mpp');

		$profiles = [];

		foreach (array_map('intval', $_POST['delete_profile']) as $id) {
			if (
				$id <= 0
				|| ($profile = PermissionProfile::load($id)) === null
				|| $profile->isPredefined()
				|| !empty($profile->boards())
			) {
				ErrorHandler::fatalLang('no_access', false);
			}

			$profiles[] = $profile;
		}

		foreach ($profiles as $profile) {
			$profile->delete();
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Load permissions into Utils::$context['permissions'].
	 */
	protected static function loadPermissionsContext(): void
	{
		// Call the deprecated integrate_load_permissions hook.
		self::integrateLoadPermissions();

		Utils::$context['permissions'] = [];

		foreach (self::$permission_groups as $scope => $groups) {
			if (!isset(Utils::$context['permissions'][$scope])) {
				Utils::$context['permissions'][$scope] = [
					'id' => $scope,
					'columns' => [
						[],
						[],
					],
				];
			}

			foreach ($groups as $group) {
				$position = (int) (!in_array($group, self::$left_permission_groups));

				Utils::$context['permissions'][$scope]['columns'][$position][$group] = [
					'type' => $scope,
					'id' => $group,
					'name' => Lang::getTxt('permissiongroup_' . $group, file: 'ManagePermissions'),
					'icon' => Lang::txtExists('permissionicon_' . $group, file: 'ManagePermissions') ? Lang::getTxt('permissionicon_' . $group, file: 'ManagePermissions') : Lang::getTxt('permissionicon', file: 'ManagePermissions'),
					'help' => Lang::txtExists('permissionhelp_' . $group, file: 'ManagePermissions') ? Lang::getTxt('permissionhelp_' . $group, file: 'ManagePermissions') : '',
					'hidden' => false,
					'permissions' => [],
				];
			}
		}

		foreach (Permission::getAll() as $permission) {
			// If this permission shouldn't be given to certain groups (e.g. guests), don't.
			if (isset(Utils::$context['group']['id']) && !$permission->canBeGrantedTo(Utils::$context['group']['id'])) {
				continue;
			}

			// What column should this be located in?
			$position = (int) (!in_array($permission->view_group, self::$left_permission_groups));

			// For legibility reasons...
			$view_group_perms = &Utils::$context['permissions'][$permission->scope]['columns'][$position][$permission->view_group]['permissions'];

			if (!isset($view_group_perms[$permission->generic_name])) {
				$view_group_perms[$permission->generic_name] = [
					'id' => $permission->generic_name,
					'name' => Lang::getTxt($permission->label, file: 'ManagePermissions+ManageMembers'),
					'show_help' => Lang::txtExists('permissionhelp_' . $permission->generic_name, file: 'ManagePermissions'),
					'note' => Lang::txtExists('permissionnote_' . $permission->generic_name, file: 'ManagePermissions') ? Lang::getTxt('permissionnote_' . $permission->generic_name, file: 'ManagePermissions') : '',
					'hidden' => !empty($permission->hidden),
				];
			}

			$view_group_perms[$permission->generic_name]['has_own_any'] = isset($permission->own_any);

			if (isset($permission->own_any)) {
				$view_group_perms[$permission->generic_name][$permission->own_any] = [
					'id' => $permission->name,
					'name' => Lang::getTxt('permissionname_' . $permission->name, file: 'ManagePermissions'),
				];
			}

			// For backward compatibility purposes only.
			if ($permission->hidden) {
				Utils::$context['hidden_permissions'][] = $permission->name;
			}
		}

		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach (Utils::$context['permissions'] as $scope => $section) {
			foreach ($section['columns'] as $column => $groups) {
				foreach ($groups as $id => $group) {
					if (empty($group['permissions'])) {
						unset(Utils::$context['permissions'][$scope]['columns'][$column][$id]);
					} else {
						$show_this_group = false;

						foreach ($group['permissions'] as $permission) {
							if (empty($permission['hidden'])) {
								$show_this_group = true;
							}
						}

						if (!$show_this_group) {
							Utils::$context['permissions'][$scope]['columns'][$column][$id]['hidden'] = true;
						}
					}
				}
			}
		}
	}

	/**
	 * Calls the deprecated integrate_load_permissions hook.
	 *
	 * MOD AUTHORS: Please update your code to use integrate_permissions_list,
	 * which can be found in SMF\Permissions\Permission::getAll()
	 *
	 * @deprecated 3.0
	 */
	protected static function integrateLoadPermissions(): void
	{
		// Don't bother if nothing is using this hook.
		if (empty(Config::$backward_compatibility) || empty(Config::$modSettings['integrate_load_permissions'])) {
			return;
		}

		$permissions_by_scope = [
			'global' => [],
			'board' => [],
		];
		$hidden_permissions = [];
		$relabel_permissions = [];

		foreach (Permission::getAll() as $perm) {
			$permissions_by_scope[$perm->scope][$perm->generic_name] = [
				!empty($perm->own_any),
				$perm->view_group,
			];

			if (!empty($perm->hidden)) {
				$hidden_permissions[] = $perm->generic_name;
			}
		}

		// Provide a practical way to modify permissions.
		self::$permission_groups['membergroup'] = &self::$permission_groups['global'];
		$permissions_by_scope['membergroup'] = &$permissions_by_scope['global'];

		IntegrationHook::call('integrate_load_permissions', [&self::$permission_groups, &$permissions_by_scope, &self::$left_permission_groups, &$hidden_permissions, &$relabel_permissions]);

		// If the hook made changes, sync them back to our master list.
		foreach ($permissions_by_scope as $scope => $permissions) {
			foreach ($permissions as $generic_name => $perm_info) {
				$is_new = true;

				foreach (['', '_own', '_any'] as $suffix) {
					try {
						$permission = Permission::get($generic_name . $suffix);
					} catch (\Throwable $e) {
						continue;
					}

					$is_new = false;
					$permission->view_group = $perm_info[1];
					unset($permission);
				}

				if ($is_new) {
					$new_ids = $perm_info[0] ? [$generic_name . '_own', $generic_name . '_any'] : [$generic_name];

					foreach ($new_ids as $id) {
						(new Permission($id, [
							'generic_name' => $generic_name,
							'own_any' => $perm_info[0] ? substr($id, -3) : null,
							'view_group' => $perm_info[1],
							'scope' => $scope === 'board' ? 'board' : 'global',
							'hidden' => in_array($generic_name, $hidden_permissions),
							'label' => 'permissionname_' . $generic_name,
							'never_guests' => in_array($generic_name, Permission::getNonGuestPermissions()),
						]))->addToKnownPermissions();
					}
				}
			}
		}

		foreach ($hidden_permissions as $permission) {
			foreach (['', '_own', '_any'] as $suffix) {
				try {
					$permission = Permission::get($permission . $suffix);
				} catch (\Throwable $e) {
					continue;
				}

				$permission->hidden = true;
				unset($permission);
			}
		}

		foreach ($relabel_permissions as $permission => $label) {
			foreach (['', '_own', '_any'] as $suffix) {
				try {
					$permission = Permission::get($permission . $suffix);
				} catch (\Throwable $e) {
					continue;
				}

				$permission->label = $label;
				unset($permission);
			}
		}
	}
}
