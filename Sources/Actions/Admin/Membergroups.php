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
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class is concerned with anything in the Manage Membergroups admin screen.
 */
class Membergroups implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ModifyMembergroups',
			'AddMembergroup' => 'AddMembergroup',
			'DeleteMembergroup' => 'DeleteMembergroup',
			'EditMembergroup' => 'EditMembergroup',
			'MembergroupIndex' => 'MembergroupIndex',
			'ModifyMembergroupsettings' => 'ModifyMembergroupsettings',
		],
	];

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

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sa' => array('method', 'required_permission')
	 */
	public static array $subactions = [
		'index' => ['index', 'manage_membergroups'],
		'add' => ['add', 'manage_membergroups'],
		'edit' => ['edit', 'manage_membergroups'],
		'settings' => ['settings', 'admin_forum'],

		// This subaction is handled by the Groups action.
		'members' => ['SMF\\Actions\\Groups::call', 'manage_membergroups'],
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// Do the permission check, you might not be allowed here.
		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		$call = method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Shows an overview of the current membergroups.
	 *
	 * Called by ?action=admin;area=membergroups.
	 * Requires the manage_membergroups permission.
	 * Splits the membergroups in regular ones and post count based groups.
	 * It also counts the number of members part of each membergroup.
	 */
	public function index(): void
	{
		Utils::$context['page_title'] = Lang::$txt['membergroups_title'];

		// The first list shows the regular membergroups.
		$listOptions = [
			'id' => 'regular_membergroups_list',
			'title' => Lang::$txt['membergroups_regular'],
			'base_href' => Config::$scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : ''),
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => '\\SMF\\Actions\\Groups::list_getMembergroups',
				'params' => [
					'regular',
				],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['membergroups_name'],
					],
					'data' => [
						'function' => function ($rowData) {
							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData['id_group'] == 3) {
								$group_name = $rowData['group_name'];
							} else {
								$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

								$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData['id_group'] == 1) {
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							} elseif ($rowData['id_group'] == 3) {
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							}

							return $group_name;
						},
					],
					'sort' => [
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					],
				],
				'icons' => [
					'header' => [
						'value' => Lang::$txt['membergroups_icons'],
					],
					'data' => [
						'db' => 'icons',
					],
					'sort' => [
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					],
				],
				'members' => [
					'header' => [
						'value' => Lang::$txt['membergroups_members_top'],
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							// No explicit members for the moderator group.
							return $rowData['id_group'] == 3 ? Lang::$txt['membergroups_guests_na'] : Lang::numberFormat($rowData['num_members']);
						},
						'class' => 'centercol',
					],
					'sort' => [
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					],
				],
				'modify' => [
					'header' => [
						'value' => Lang::$txt['modify'],
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . Lang::$txt['membergroups_modify'] . '</a>',
							'params' => [
								'id_group' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'above_column_headers',
					'value' => '<a class="button" href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . Lang::$txt['membergroups_add_group'] . '</a>',
				],
				[
					'position' => 'below_table_data',
					'value' => '<a class="button" href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . Lang::$txt['membergroups_add_group'] . '</a>',
				],
			],
		];

		new ItemList($listOptions);

		// The second list shows the post count based groups.
		$listOptions = [
			'id' => 'post_count_membergroups_list',
			'title' => Lang::$txt['membergroups_post'],
			'base_href' => Config::$scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort']) ? ';sort=' . urlencode($_REQUEST['sort']) : ''),
			'default_sort_col' => 'required_posts',
			'request_vars' => [
				'sort' => 'sort2',
				'desc' => 'desc2',
			],
			'get_items' => [
				'function' => '\\SMF\\Actions\\Groups::list_getMembergroups',
				'params' => [
					'post_count',
				],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['membergroups_name'],
					],
					'data' => [
						'function' => function ($rowData) {
							$colorStyle = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

							return sprintf('<a href="%1$s?action=moderate;area=viewgroups;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $colorStyle, $rowData['group_name']);
						},
					],
					'sort' => [
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					],
				],
				'icons' => [
					'header' => [
						'value' => Lang::$txt['membergroups_icons'],
					],
					'data' => [
						'db' => 'icons',
					],
					'sort' => [
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons DESC',
					],
				],
				'members' => [
					'header' => [
						'value' => Lang::$txt['membergroups_members_top'],
						'class' => 'centercol',
					],
					'data' => [
						'db' => 'num_members',
						'class' => 'centercol',
					],
					'sort' => [
						'default' => '1 DESC',
						'reverse' => '1',
					],
				],
				'required_posts' => [
					'header' => [
						'value' => Lang::$txt['membergroups_min_posts'],
						'class' => 'centercol',
					],
					'data' => [
						'db' => 'min_posts',
						'class' => 'centercol',
					],
					'sort' => [
						'default' => 'mg.min_posts',
						'reverse' => 'mg.min_posts DESC',
					],
				],
				'modify' => [
					'header' => [
						'value' => Lang::$txt['modify'],
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . Lang::$txt['membergroups_modify'] . '</a>',
							'params' => [
								'id_group' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<a class="button" href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . Lang::$txt['membergroups_add_group'] . '</a>',
				],
			],
		];

		new ItemList($listOptions);
	}

	/**
	 * Handles adding a membergroup and setting some initial properties.
	 *
	 * Called by ?action=admin;area=membergroups;sa=add.
	 * It requires the manage_membergroups permission.
	 * Allows to use a predefined permission profile or copy one from another group.
	 * Redirects to action=admin;area=membergroups;sa=edit;group=x.
	 */
	public function add(): void
	{
		// A form was submitted, we can start adding.
		if (isset($_POST['group_name']) && trim($_POST['group_name']) != '') {
			User::$me->checkSession();
			SecurityToken::validate('admin-mmg');

			$postCountBasedGroup = isset($_POST['min_posts']) && (!isset($_POST['postgroup_based']) || !empty($_POST['postgroup_based']));

			$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !User::$me->allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

			IntegrationHook::call('integrate_pre_add_membergroup', []);

			$id_group = Db::$db->insert(
				'',
				'{db_prefix}membergroups',
				[
					'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
					'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int',
				],
				[
					'', Utils::htmlspecialchars($_POST['group_name'], ENT_QUOTES), ($postCountBasedGroup ? (int) $_POST['min_posts'] : '-1'),
					'1#icon.png', '', $_POST['group_type'],
				],
				['id_group'],
				1,
			);

			IntegrationHook::call('integrate_add_membergroup', [$id_group, $postCountBasedGroup]);

			// Update the post groups now, if this is a post group!
			if (isset($_POST['min_posts'])) {
				Logging::updateStats('postgroups');
			}

			// You cannot set permissions for post groups if they are disabled.
			if ($postCountBasedGroup && empty(Config::$modSettings['permission_enable_postgroups'])) {
				$_POST['perm_type'] = '';
			}

			if ($_POST['perm_type'] == 'predefined') {
				// Set default permission level.
				Permissions::setPermissionLevel($_POST['level'], $id_group, 'null');
			}
			// Copy or inherit the permissions!
			elseif ($_POST['perm_type'] == 'copy' || $_POST['perm_type'] == 'inherit') {
				$copy_id = $_POST['perm_type'] == 'copy' ? (int) $_POST['copyperm'] : (int) $_POST['inheritperm'];

				@list($copy_from) = Group::load($copy_id);

				if (!isset($copy_from)) {
					ErrorHandler::fatalLang('membergroup_does_not_exist');
				}

				// Protected groups are... well, protected!
				if (!User::$me->allowedTo('admin_forum') && $copy_from->type == Group::TYPE_PROTECTED) {
					ErrorHandler::fatalLang('membergroup_does_not_exist');
				}

				// Don't allow copying of a real privileged person!
				$illegal_permissions = Permissions::loadIllegalPermissions();

				$inserts = [];
				$request = Db::$db->query(
					'',
					'SELECT permission, add_deny
					FROM {db_prefix}permissions
					WHERE id_group = {int:copy_from}',
					[
						'copy_from' => $copy_id,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					if (empty($illegal_permissions) || !in_array($row['permission'], $illegal_permissions)) {
						$inserts[] = [$id_group, $row['permission'], $row['add_deny']];
					}
				}
				Db::$db->free_result($request);

				if (!empty($inserts)) {
					Db::$db->insert(
						'insert',
						'{db_prefix}permissions',
						['id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
						$inserts,
						['id_group', 'permission'],
					);
				}

				$inserts = [];
				$request = Db::$db->query(
					'',
					'SELECT id_profile, permission, add_deny
					FROM {db_prefix}board_permissions
					WHERE id_group = {int:copy_from}',
					[
						'copy_from' => $copy_id,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$inserts[] = [$id_group, $row['id_profile'], $row['permission'], $row['add_deny']];
				}
				Db::$db->free_result($request);

				if (!empty($inserts)) {
					Db::$db->insert(
						'insert',
						'{db_prefix}board_permissions',
						['id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
						$inserts,
						['id_group', 'id_profile', 'permission'],
					);
				}

				// Also get some membergroup information if we're copying and not copying from guests...
				if ($copy_id > 0 && $_POST['perm_type'] == 'copy') {
					// ...and update the new membergroup with it.
					Db::$db->query(
						'',
						'UPDATE {db_prefix}membergroups
						SET
							online_color = {string:online_color},
							max_messages = {int:max_messages},
							icons = {string:icons}
						WHERE id_group = {int:current_group}',
						[
							'max_messages' => $copy_from->max_messages,
							'current_group' => $id_group,
							'online_color' => $copy_from->online_color,
							'icons' => $copy_from->icons,
						],
					);
				}
				// If inheriting say so...
				elseif ($_POST['perm_type'] == 'inherit') {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}membergroups
						SET id_parent = {int:copy_from}
						WHERE id_group = {int:current_group}',
						[
							'copy_from' => $copy_id,
							'current_group' => $id_group,
						],
					);
				}
			}

			// Make sure all boards selected are stored in a proper array.
			$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? [] : $_POST['boardaccess'];

			$changed_boards['allow'] = [];
			$changed_boards['deny'] = [];
			$changed_boards['ignore'] = [];

			foreach ($accesses as $group_id => $action) {
				$changed_boards[$action][] = (int) $group_id;
			}

			foreach (['allow', 'deny'] as $board_action) {
				// Only do this if they have special access requirements.
				if (!empty($changed_boards[$board_action])) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
						WHERE id_board IN ({array_int:board_list})',
						[
							'board_list' => $changed_boards[$board_action],
							'blank_string' => '',
							'group_id_string' => (string) $id_group,
							'comma_group' => ',' . $id_group,
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						],
					);

					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}board_permissions_view
						WHERE id_board IN ({array_int:board_list})
							AND id_group = {int:group_id}
							AND deny = {int:deny}',
						[
							'board_list' => $changed_boards[$board_action],
							'group_id' => $id_group,
							'deny' => $board_action == 'allow' ? 0 : 1,
						],
					);

					$insert = [];

					foreach ($changed_boards[$board_action] as $board_id) {
						$insert[] = [$id_group, $board_id, $board_action == 'allow' ? 0 : 1];
					}

					Db::$db->insert(
						'insert',
						'{db_prefix}board_permissions_view',
						['id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'],
						$insert,
						['id_group', 'id_board', 'deny'],
					);
				}
			}

			// If this is joinable then set it to show group membership in people's profiles.
			if (empty(Config::$modSettings['show_group_membership']) && $_POST['group_type'] > 1) {
				Config::updateModSettings(['show_group_membership' => 1]);
			}

			// Rebuild the group cache.
			Config::updateModSettings([
				'settings_updated' => time(),
			]);

			// We did it.
			Logging::logAction('add_group', ['group' => Utils::htmlspecialchars($_POST['group_name'])], 'admin');

			// Go change some more settings.
			Utils::redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
		}

		// Just show the 'add membergroup' screen.
		Utils::$context['page_title'] = Lang::$txt['membergroups_new_group'];
		Utils::$context['sub_template'] = 'new_group';
		Utils::$context['post_group'] = isset($_REQUEST['postgroup']);
		Utils::$context['undefined_group'] = !isset($_REQUEST['postgroup']) && !isset($_REQUEST['generalgroup']);
		Utils::$context['allow_protected'] = User::$me->allowedTo('admin_forum');

		if (!empty(Config::$modSettings['deny_boards_access'])) {
			Lang::load('ManagePermissions');
		}

		// Load all the relevant member groups. Start with a clean slate.
		Group::$loaded = [];

		Utils::$context['groups'] = Group::loadSimple(
			Group::LOAD_NORMAL | (int) !empty(Config::$modSettings['permission_enable_postgroups']),
			[Group::GUEST, Group::REGULAR, Group::ADMIN, Group::MOD],
		);

		Utils::$context['num_boards'] = 0;
		Utils::$context['categories'] = [];

		$request = Db::$db->query(
			'',
			'SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY board_order',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['num_boards']++;

			// This category hasn't been set up yet..
			if (!isset(Utils::$context['categories'][$row['id_cat']])) {
				Utils::$context['categories'][$row['id_cat']] = [
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => [],
				];
			}

			// Set this board up, and let the template know when it's a child.  (indent them..)
			Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'allow' => false,
				'deny' => false,
			];
		}
		Db::$db->free_result($request);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = [];

		foreach (Utils::$context['categories'] as $category) {
			$temp_boards[] = [
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards']),
			];

			$temp_boards = array_merge($temp_boards, array_values($category['boards']));

			// Include a list of boards per category for easy toggling.
			Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}

		SecurityToken::create('admin-mmg');
	}

	/**
	 * Editing a membergroup.
	 *
	 * Screen to edit a specific membergroup.
	 * Called by ?action=admin;area=membergroups;sa=edit;group=x.
	 * It requires the manage_membergroups permission.
	 * Also handles the delete button of the edit form.
	 * Redirects to ?action=admin;area=membergroups.
	 */
	public function edit(): void
	{
		$_REQUEST['group'] = isset($_REQUEST['group']) && $_REQUEST['group'] > 0 ? (int) $_REQUEST['group'] : 0;

		if (!empty(Config::$modSettings['deny_boards_access'])) {
			Lang::load('ManagePermissions');
		}

		if (empty($_REQUEST['group'])) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		@list($group) = Group::load($_REQUEST['group']);

		if (!isset($group) || !($group instanceof Group)) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		if (!User::$me->allowedTo('admin_forum') && $group->type === Group::TYPE_PROTECTED) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		// People who can manage boards are a bit special.
		$board_managers = User::groupsAllowedTo('manage_boards', null);

		Utils::$context['can_manage_boards'] = in_array($group->id, $board_managers['allowed']);

		// Can this group moderate any boards?
		Utils::$context['is_moderator_group'] = $group->is_moderator_group;

		// Get a list of all the image formats we can select for icons.
		$imageExts = ['png', 'jpg', 'jpeg', 'bmp', 'gif', 'svg'];

		// Scan the icons directory.
		Utils::$context['possible_icons'] = [];

		if ($files = scandir(Theme::$current->settings['default_theme_dir'] . '/images/membericons')) {
			// Loop through every file in the directory.
			foreach ($files as $value) {
				// Grab the image extension.
				$ext = pathinfo(Theme::$current->settings['default_theme_dir'] . '/images/membericons/' . $value, PATHINFO_EXTENSION);

				// If the extension is not empty, and it is valid.
				if (!empty($ext) && in_array($ext, $imageExts)) {
					Utils::$context['possible_icons'][] = $value;
				}
			}
		}

		// The delete this membergroup button was pressed.
		if (isset($_POST['delete'])) {
			$result = $group->delete();

			// Need to throw a warning if it went wrong, but this is the only one we have a message for...
			if ($result === 'group_cannot_delete_sub') {
				ErrorHandler::fatalLang('membergroups_cannot_delete_paid', false);
			}

			Utils::redirectexit('action=admin;area=membergroups;');
		}
		// A form was submitted with the new membergroup settings.
		elseif (isset($_POST['save'])) {
			// Can they really inherit from this group?
			if ($group->id > Group::ADMIN && $group->id != Group::MOD && isset($_POST['group_inherit']) && $_POST['group_inherit'] != Group::NONE && !User::$me->allowedTo('admin_forum')) {
				$_POST['group_inherit'] = (int) $_POST['group_inherit'];

				if ($_POST['group_inherit'] > Group::ADMIN) {
					@list($inherit_from) = Group::load($_POST['group_inherit']);
				}

				if (isset($inherit_from) && ($inherit_from instanceof Group)) {
					$inherit_type = $inherit_from->type;
				}
			}

			// Set variables to their proper value.
			$group->set([
				'max_messages' => isset($_POST['max_messages']) ? (int) $_POST['max_messages'] : 0,
				'min_posts' => isset($_POST['min_posts']) && isset($_POST['group_type']) && $_POST['group_type'] == -1 && $group->id > Group::MOD ? abs($_POST['min_posts']) : ($group->id == Group::NEWBIE ? 0 : -1),
				'icons' => (empty($_POST['icon_count']) || $_POST['icon_count'] < 0 || !in_array($_POST['icon_image'], Utils::$context['possible_icons'])) ? '' : min((int) $_POST['icon_count'], 99) . '#' . $_POST['icon_image'],
				'name' => Utils::htmlspecialchars($_POST['group_name']),
				'description' => isset($_POST['group_desc']) && ($group->id == Group::ADMIN || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? Utils::htmlTrim(Utils::sanitizeChars(Utils::normalize($_POST['group_desc']))) : '',
				'type' => !isset($_POST['group_type']) || $_POST['group_type'] < Group::TYPE_PRIVATE || $_POST['group_type'] > Group::TYPE_FREE || ($_POST['group_type'] == Group::TYPE_PROTECTED && !User::$me->allowedTo('admin_forum')) ? Group::TYPE_PRIVATE : (int) $_POST['group_type'],
				'hidden' => empty($_POST['group_hidden']) || $_POST['min_posts'] != -1 || $group->id == Group::MOD ? Group::VISIBLE : (int) $_POST['group_hidden'],
				'parent' => $group->id > Group::ADMIN && $group->id != Group::MOD && (empty($inherit_type) || $inherit_type != Group::TYPE_PROTECTED) ? (int) $_POST['group_inherit'] : Group::NONE,
				'tfa_required' => (empty(Config::$modSettings['tfa_mode']) || Config::$modSettings['tfa_mode'] != 2 || empty($_POST['group_tfa_force'])) ? false : true,
				'online_color' => (int) $group->id != Group::MOD && preg_match('/^#?\w+$/', Utils::htmlTrim($_POST['online_color'])) ? Utils::htmlTrim($_POST['online_color']) : '',
			]);

			// Does the group have any moderators?
			$moderator_string = isset($_POST['group_moderators']) ? trim($_POST['group_moderators']) : '';

			if ((!empty($moderator_string) || !empty($_POST['moderator_list'])) && ($_POST['min_posts'] ?? -1) == -1 && $group->id != Group::MOD) {
				$group->moderator_ids = [];

				// Get all the usernames from the string.
				if (!empty($moderator_string)) {
					$moderator_string = strtr(Utils::entityFix(Utils::htmlspecialchars($moderator_string, ENT_QUOTES)), ['&quot;' => '"']);

					preg_match_all('~"([^"]+)"~', $moderator_string, $matches);

					$moderators = array_filter(array_map('trim', array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)))), 'strlen');

					// Get the IDs of the named members.
					if (!empty($moderators)) {
						foreach (User::load($moderators, User::LOAD_BY_NAME, 'minimal') as $moderator) {
							$group->moderator_ids[] = $moderator->id;
						}
					}
				}

				if (!empty($_POST['moderator_list'])) {
					$moderators = array_filter(array_map('intval', $_POST['moderator_list']));

					if (!empty($moderators)) {
						foreach (User::load($moderators, User::LOAD_BY_ID, 'minimal') as $moderator) {
							$group->moderator_ids[] = $moderator->id;
						}
					}
				}

				// Make sure we don't have any duplicates.
				$group->moderator_ids = array_unique($group->moderator_ids);
			}

			// Update the membergroup.
			$group->save();

			// Time to update the boards this membergroup has access to.
			$group->updateBoardAccess(empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? [] : $_POST['boardaccess']);

			// Let's check whether our "show group membership" setting is correct.
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}membergroups
				WHERE group_type > {int:non_joinable}',
				[
					'non_joinable' => Group::TYPE_PROTECTED,
				],
			);
			list($have_joinable) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Do we need to update the setting?
			if ((empty(Config::$modSettings['show_group_membership']) && $have_joinable) || (!empty(Config::$modSettings['show_group_membership']) && !$have_joinable)) {
				Config::updateModSettings(['show_group_membership' => $have_joinable ? 1 : 0]);
			}

			Utils::redirectexit('action=admin;area=membergroups');
		}

		// Fetch the current group information.
		unset(Group::$loaded[$group->id]);

		if (Group::load($group->id) === []) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		$group = Group::$loaded[$group->id];

		$group->description = Utils::htmlspecialchars($group->description, ENT_QUOTES);

		// Get any moderators for this group.
		$group->loadModerators();

		foreach ($group->moderator_ids as $mod_id) {
			$group->moderators[$mod_id] = User::$loaded[$mod_id]->name;
		}

		$group->moderator_list = empty($group->moderators) ? '' : '&quot;' . implode('&quot;, &quot;', $group->moderators) . '&quot;';

		if (!empty($group->moderators)) {
			list($group->last_moderator_id) = array_slice(array_keys($group->moderators), -1);
		}

		Utils::$context['group'] = $group;

		// Get a list of boards this membergroup is allowed to see.
		Utils::$context['boards'] = [];

		if ($group->id == Group::GLOBAL_MOD || $group->id >= Group::NEWBIE) {
			Utils::$context['categories'] = [];
			$request = Db::$db->query(
				'',
				'SELECT b.id_cat, c.name as cat_name, b.id_board, b.name, b.child_level,
				FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access, FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				ORDER BY board_order',
				[
					'current_group' => $group->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				// This category hasn't been set up yet..
				if (!isset(Utils::$context['categories'][$row['id_cat']])) {
					Utils::$context['categories'][$row['id_cat']] = [
						'id' => $row['id_cat'],
						'name' => $row['cat_name'],
						'boards' => [],
					];
				}

				// Set this board up, and let the template know when it's a child.  (indent them..)
				Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'child_level' => $row['child_level'],
					'allow' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
					'deny' => !(empty($row['cannot_access']) || $row['cannot_access'] == 'f'),
				];
			}
			Db::$db->free_result($request);

			// Now, let's sort the list of categories into the boards for templates that like that.
			$temp_boards = [];

			foreach (Utils::$context['categories'] as $category) {
				$temp_boards[] = [
					'name' => $category['name'],
					'child_ids' => array_keys($category['boards']),
				];
				$temp_boards = array_merge($temp_boards, array_values($category['boards']));

				// Include a list of boards per category for easy toggling.
				Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
			}
		}

		// Insert our JS, if we have possible icons.
		if (!empty(Utils::$context['possible_icons'])) {
			Theme::loadJavaScriptFile('icondropdown.js', ['validate' => true, 'minimize' => true], 'smf_icondropdown');
		}

		Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');

		// Finally, get all the groups this could inherit from.
		Utils::$context['inheritable_groups'] = [];

		$query_customizations = [
			'where' => [
				'id_group != {int:current_group}',
				'id_group NOT IN ({array_int:disallowed})',
				'id_parent = {int:not_inherited}',
				empty(Config::$modSettings['permission_enable_postgroups']) ? 'min_posts = {int:min_posts}' : '1=1',
				User::$me->allowedTo('admin_forum') ? '1=1' : 'group_type != {int:is_protected}',
			],
			'params' => [
				'current_group' => $group->id,
				'min_posts' => -1,
				'disallowed' => [Group::ADMIN, Group::MOD],
				'not_inherited' => Group::NONE,
				'is_protected' => Group::TYPE_PROTECTED,
			],
		];

		foreach (Group::load([], $query_customizations) as $inheritable_group) {
			Utils::$context['inheritable_groups'][$inheritable_group->id] = $inheritable_group->name;
		}

		IntegrationHook::call('integrate_view_membergroup');

		Utils::$context['sub_template'] = 'edit_group';
		Utils::$context['page_title'] = Lang::$txt['membergroups_edit_group'];

		SecurityToken::create('admin-mmg');
	}

	/**
	 * Set some general membergroup settings and permissions.
	 *
	 * Called by ?action=admin;area=membergroups;sa=settings
	 * Requires the admin_forum permission (and manage_permissions for changing permissions)
	 * Redirects to itself.
	 */
	public function settings(): void
	{
		Utils::$context['sub_template'] = 'show_settings';
		Utils::$context['page_title'] = Lang::$txt['membergroups_settings'];

		$config_vars = self::getConfigVars();

		if (isset($_REQUEST['save'])) {
			User::$me->checkSession();
			IntegrationHook::call('integrate_save_membergroup_settings');

			// Yeppers, saving this...
			ACP::saveDBSettings($config_vars);

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=membergroups;sa=settings');
		}

		// Some simple context.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=membergroups;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['membergroups_settings'];

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		ACP::prepareDBSettingContext($config_vars);
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
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the membergroups area.
	 */
	public static function getConfigVars(): array
	{
		// Only one thing here!
		$config_vars = [
			['permissions', 'manage_membergroups'],
		];

		IntegrationHook::call('integrate_modify_membergroup_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper for the add sub-action.
	 */
	public static function AddMembergroup(): void
	{
		self::load();
		self::$obj->subaction = 'add';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the delete sub-action.
	 */
	public static function DeleteMembergroup(): void
	{
		self::load();
		self::$obj->subaction = 'delete';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the edit sub-action.
	 */
	public static function EditMembergroup(): void
	{
		self::load();
		self::$obj->subaction = 'edit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the index sub-action.
	 */
	public static function MembergroupIndex(): void
	{
		self::load();
		self::$obj->subaction = 'index';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function ModifyMembergroupsettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
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
		// Language and template stuff, the usual.
		Lang::load('ManageMembers');
		Theme::loadTemplate('ManageMembergroups');

		// Setup the admin tabs.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['membergroups_title'],
			'help' => 'membergroups',
			'description' => Lang::$txt['membergroups_description'],
		];

		IntegrationHook::call('integrate_manage_membergroups', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		} elseif (!User::$me->allowedTo('manage_membergroups')) {
			$this->subaction = 'settings';
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Membergroups::exportStatic')) {
	Membergroups::exportStatic();
}

?>