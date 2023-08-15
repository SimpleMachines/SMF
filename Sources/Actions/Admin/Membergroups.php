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
use SMF\ErrorHandler;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

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
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'ModifyMembergroups',
			'getConfigVars' => false,
		),
	);

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
	public static array $subactions = array(
		'index' => array('index', 'manage_membergroups'),
		'add' => array('add', 'manage_membergroups'),
		'edit' => array('edit', 'manage_membergroups'),
		'settings' => array('settings', 'admin_forum'),

		// This subaction is handled by the Groups action.
		'members' => array('SMF\\Actions\\Groups::call', 'manage_membergroups'),
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// Do the permission check, you might not be allowed here.
		isAllowedTo(self::$subactions[$this->subaction][1]);

		call_helper(method_exists($this, self::$subactions[$this->subaction][0]) ? array($this, self::$subactions[$this->subaction][0]) : self::$subactions[$this->subaction][0]);
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
		$listOptions = array(
			'id' => 'regular_membergroups_list',
			'title' => Lang::$txt['membergroups_regular'],
			'base_href' => Config::$scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : ''),
			'default_sort_col' => 'name',
			'get_items' => array(
				'function' => '\\SMF\\Actions\\Groups::list_getMembergroups',
				'params' => array(
					'regular',
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_name'],
					),
					'data' => array(
						'function' => function($rowData)
						{
							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData['id_group'] == 3)
							{
								$group_name = $rowData['group_name'];
							}
							else
							{
								$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

								$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData['id_group'] == 1)
							{
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							}
							elseif ($rowData['id_group'] == 3)
							{
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							}

							return $group_name;
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_icons'],
					),
					'data' => array(
						'db' => 'icons',
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'members' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_members_top'],
						'class' => 'centercol',
					),
					'data' => array(
						'function' => function($rowData)
						{
							// No explicit members for the moderator group.
							return $rowData['id_group'] == 3 ? Lang::$txt['membergroups_guests_na'] : Lang::numberFormat($rowData['num_members']);
						},
						'class' => 'centercol',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => Lang::$txt['modify'],
						'class' => 'centercol',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . Lang::$txt['membergroups_modify'] . '</a>',
							'params' => array(
								'id_group' => false,
							),
						),
						'class' => 'centercol',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'above_column_headers',
					'value' => '<a class="button" href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . Lang::$txt['membergroups_add_group'] . '</a>',
				),
				array(
					'position' => 'below_table_data',
					'value' => '<a class="button" href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . Lang::$txt['membergroups_add_group'] . '</a>',
				),
			),
		);

		new ItemList($listOptions);

		// The second list shows the post count based groups.
		$listOptions = array(
			'id' => 'post_count_membergroups_list',
			'title' => Lang::$txt['membergroups_post'],
			'base_href' => Config::$scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort']) ? ';sort=' . urlencode($_REQUEST['sort']) : ''),
			'default_sort_col' => 'required_posts',
			'request_vars' => array(
				'sort' => 'sort2',
				'desc' => 'desc2',
			),
			'get_items' => array(
				'function' => '\\SMF\\Actions\\Groups::list_getMembergroups',
				'params' => array(
					'post_count',
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_name'],
					),
					'data' => array(
						'function' => function($rowData)
						{
							$colorStyle = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

							return sprintf('<a href="%1$s?action=moderate;area=viewgroups;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $colorStyle, $rowData['group_name']);
						},
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_icons'],
					),
					'data' => array(
						'db' => 'icons',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons DESC',
					)
				),
				'members' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_members_top'],
						'class' => 'centercol',
					),
					'data' => array(
						'db' => 'num_members',
						'class' => 'centercol',
					),
					'sort' => array(
						'default' => '1 DESC',
						'reverse' => '1',
					),
				),
				'required_posts' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_min_posts'],
						'class' => 'centercol',
					),
					'data' => array(
						'db' => 'min_posts',
						'class' => 'centercol',
					),
					'sort' => array(
						'default' => 'mg.min_posts',
						'reverse' => 'mg.min_posts DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => Lang::$txt['modify'],
						'class' => 'centercol',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . Lang::$txt['membergroups_modify'] . '</a>',
							'params' => array(
								'id_group' => false,
							),
						),
						'class' => 'centercol',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<a class="button" href="' . Config::$scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . Lang::$txt['membergroups_add_group'] . '</a>',
				),
			),
		);

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
		if (isset($_POST['group_name']) && trim($_POST['group_name']) != '')
		{
			checkSession();
			validateToken('admin-mmg');

			$postCountBasedGroup = isset($_POST['min_posts']) && (!isset($_POST['postgroup_based']) || !empty($_POST['postgroup_based']));

			$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

			call_integration_hook('integrate_pre_add_membergroup', array());

			$id_group = Db::$db->insert('',
				'{db_prefix}membergroups',
				array(
					'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
					'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int',
				),
				array(
					'', Utils::htmlspecialchars($_POST['group_name'], ENT_QUOTES), ($postCountBasedGroup ? (int) $_POST['min_posts'] : '-1'),
					'1#icon.png', '', $_POST['group_type'],
				),
				array('id_group'),
				1
			);

			call_integration_hook('integrate_add_membergroup', array($id_group, $postCountBasedGroup));

			// Update the post groups now, if this is a post group!
			if (isset($_POST['min_posts']))
				updateStats('postgroups');

			// You cannot set permissions for post groups if they are disabled.
			if ($postCountBasedGroup && empty(Config::$modSettings['permission_enable_postgroups']))
			{
				$_POST['perm_type'] = '';
			}

			if ($_POST['perm_type'] == 'predefined')
			{
				// Set default permission level.
				Permissions::setPermissionLevel($_POST['level'], $id_group, 'null');
			}
			// Copy or inherit the permissions!
			elseif ($_POST['perm_type'] == 'copy' || $_POST['perm_type'] == 'inherit')
			{
				$copy_id = $_POST['perm_type'] == 'copy' ? (int) $_POST['copyperm'] : (int) $_POST['inheritperm'];

				// Are you a powerful admin?
				if (!allowedTo('admin_forum'))
				{
					$request = Db::$db->query('', '
						SELECT group_type
						FROM {db_prefix}membergroups
						WHERE id_group = {int:copy_from}
						LIMIT {int:limit}',
						array(
							'copy_from' => $copy_id,
							'limit' => 1,
						)
					);
					list($copy_type) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					// Protected groups are... well, protected!
					if ($copy_type == 1)
						ErrorHandler::fatalLang('membergroup_does_not_exist');
				}

				// Don't allow copying of a real priviledged person!
				$illegal_permissions = Permissions::loadIllegalPermissions();

				$inserts = array();
				$request = Db::$db->query('', '
					SELECT permission, add_deny
					FROM {db_prefix}permissions
					WHERE id_group = {int:copy_from}',
					array(
						'copy_from' => $copy_id,
					)
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					if (empty($illegal_permissions) || !in_array($row['permission'], $illegal_permissions))
					{
						$inserts[] = array($id_group, $row['permission'], $row['add_deny']);
					}
				}
				Db::$db->free_result($request);

				if (!empty($inserts))
				{
					Db::$db->insert('insert',
						'{db_prefix}permissions',
						array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
						$inserts,
						array('id_group', 'permission')
					);
				}

				$inserts = array();
				$request = Db::$db->query('', '
					SELECT id_profile, permission, add_deny
					FROM {db_prefix}board_permissions
					WHERE id_group = {int:copy_from}',
					array(
						'copy_from' => $copy_id,
					)
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					$inserts[] = array($id_group, $row['id_profile'], $row['permission'], $row['add_deny']);
				}
				Db::$db->free_result($request);

				if (!empty($inserts))
				{
					Db::$db->insert('insert',
						'{db_prefix}board_permissions',
						array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
						$inserts,
						array('id_group', 'id_profile', 'permission')
					);
				}

				// Also get some membergroup information if we're copying and not copying from guests...
				if ($copy_id > 0 && $_POST['perm_type'] == 'copy')
				{
					$request = Db::$db->query('', '
						SELECT online_color, max_messages, icons
						FROM {db_prefix}membergroups
						WHERE id_group = {int:copy_from}
						LIMIT 1',
						array(
							'copy_from' => $copy_id,
						)
					);
					$group_info = Db::$db->fetch_assoc($request);
					Db::$db->free_result($request);

					// ...and update the new membergroup with it.
					Db::$db->query('', '
						UPDATE {db_prefix}membergroups
						SET
							online_color = {string:online_color},
							max_messages = {int:max_messages},
							icons = {string:icons}
						WHERE id_group = {int:current_group}',
						array(
							'max_messages' => $group_info['max_messages'],
							'current_group' => $id_group,
							'online_color' => $group_info['online_color'],
							'icons' => $group_info['icons'],
						)
					);
				}
				// If inheriting say so...
				elseif ($_POST['perm_type'] == 'inherit')
				{
					Db::$db->query('', '
						UPDATE {db_prefix}membergroups
						SET id_parent = {int:copy_from}
						WHERE id_group = {int:current_group}',
						array(
							'copy_from' => $copy_id,
							'current_group' => $id_group,
						)
					);
				}
			}

			// Make sure all boards selected are stored in a proper array.
			$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];

			$changed_boards['allow'] = array();
			$changed_boards['deny'] = array();
			$changed_boards['ignore'] = array();

			foreach ($accesses as $group_id => $action)
				$changed_boards[$action][] = (int) $group_id;

			foreach (array('allow', 'deny') as $board_action)
			{
				// Only do this if they have special access requirements.
				if (!empty($changed_boards[$board_action]))
				{
					Db::$db->query('', '
						UPDATE {db_prefix}boards
						SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
						WHERE id_board IN ({array_int:board_list})',
						array(
							'board_list' => $changed_boards[$board_action],
							'blank_string' => '',
							'group_id_string' => (string) $id_group,
							'comma_group' => ',' . $id_group,
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						)
					);

					Db::$db->query('', '
						DELETE FROM {db_prefix}board_permissions_view
						WHERE id_board IN ({array_int:board_list})
							AND id_group = {int:group_id}
							AND deny = {int:deny}',
						array(
							'board_list' => $changed_boards[$board_action],
							'group_id' => $id_group,
							'deny' => $board_action == 'allow' ? 0 : 1,
						)
					);

					$insert = array();

					foreach ($changed_boards[$board_action] as $board_id)
						$insert[] = array($id_group, $board_id, $board_action == 'allow' ? 0 : 1);

					Db::$db->insert('insert',
						'{db_prefix}board_permissions_view',
						array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
						$insert,
						array('id_group', 'id_board', 'deny')
					);
				}

			}

			// If this is joinable then set it to show group membership in people's profiles.
			if (empty(Config::$modSettings['show_group_membership']) && $_POST['group_type'] > 1)
				Config::updateModSettings(array('show_group_membership' => 1));

			// Rebuild the group cache.
			Config::updateModSettings(array(
				'settings_updated' => time(),
			));

			// We did it.
			logAction('add_group', array('group' => Utils::htmlspecialchars($_POST['group_name'])), 'admin');

			// Go change some more settings.
			redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
		}

		// Just show the 'add membergroup' screen.
		Utils::$context['page_title'] = Lang::$txt['membergroups_new_group'];
		Utils::$context['sub_template'] = 'new_group';
		Utils::$context['post_group'] = isset($_REQUEST['postgroup']);
		Utils::$context['undefined_group'] = !isset($_REQUEST['postgroup']) && !isset($_REQUEST['generalgroup']);
		Utils::$context['allow_protected'] = allowedTo('admin_forum');

		if (!empty(Config::$modSettings['deny_boards_access']))
			Lang::load('ManagePermissions');

		Utils::$context['groups'] = array();
		$result = Db::$db->query('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE (id_group > {int:moderator_group} OR id_group = {int:global_mod_group})' . (empty(Config::$modSettings['permission_enable_postgroups']) ? '
				AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
				AND group_type != {int:is_protected}') . '
			ORDER BY min_posts, id_group != {int:global_mod_group}, group_name',
			array(
				'moderator_group' => 3,
				'global_mod_group' => 2,
				'min_posts' => -1,
				'is_protected' => 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($result))
		{
			Utils::$context['groups'][] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name']
			);
		}
		Db::$db->free_result($result);

		Utils::$context['num_boards'] = 0;
		Utils::$context['categories'] = array();

		$request = Db::$db->query('', '
			SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY board_order',
			array(
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['num_boards']++;

			// This category hasn't been set up yet..
			if (!isset(Utils::$context['categories'][$row['id_cat']]))
			{
				Utils::$context['categories'][$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => array()
				);
			}

			// Set this board up, and let the template know when it's a child.  (indent them..)
			Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'allow' => false,
				'deny' => false
			);
		}
		Db::$db->free_result($request);

		// Now, let's sort the list of categories into the boards for templates that like that.
		$temp_boards = array();
		foreach (Utils::$context['categories'] as $category)
		{
			$temp_boards[] = array(
				'name' => $category['name'],
				'child_ids' => array_keys($category['boards'])
			);

			$temp_boards = array_merge($temp_boards, array_values($category['boards']));

			// Include a list of boards per category for easy toggling.
			Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}

		createToken('admin-mmg');
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

		if (!empty(Config::$modSettings['deny_boards_access']))
			Lang::load('ManagePermissions');

		// Make sure this group is editable.
		if (!empty($_REQUEST['group']))
		{
			$request = Db::$db->query('', '
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE id_group = {int:current_group}' . (allowedTo('admin_forum') ? '' : '
					AND group_type != {int:is_protected}') . '
				LIMIT {int:limit}',
				array(
					'current_group' => $_REQUEST['group'],
					'is_protected' => 1,
					'limit' => 1,
				)
			);
			list($_REQUEST['group']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// Now, do we have a valid id?
		if (empty($_REQUEST['group']))
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);

		// People who can manage boards are a bit special.
		require_once(Config::$sourcedir . '/Subs-Members.php');

		$board_managers = groupsAllowedTo('manage_boards', null);

		Utils::$context['can_manage_boards'] = in_array($_REQUEST['group'], $board_managers['allowed']);

		// Can this group moderate any boards?
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}moderator_groups
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_REQUEST['group'],
			)
		);
		$row = Db::$db->fetch_row($request);
		Utils::$context['is_moderator_group'] = ($row[0] > 0);
		Db::$db->free_result($request);

		// Get a list of all the image formats we can select for icons.
		$imageExts = array('png', 'jpg', 'jpeg', 'bmp', 'gif', 'svg');

		// Scan the icons directory.
		Utils::$context['possible_icons'] = array();

		if ($files = scandir(Theme::$current->settings['default_theme_dir'] . '/images/membericons'))
		{
			// Loop through every file in the directory.
			foreach ($files as $value)
			{
				// Grab the image extension.
				$ext = pathinfo(Theme::$current->settings['default_theme_dir'] . '/images/membericons/' . $value, PATHINFO_EXTENSION);

				// If the extension is not empty, and it is valid.
				if (!empty($ext) && in_array($ext, $imageExts))
					Utils::$context['possible_icons'][] = $value;
			}
		}

		// The delete this membergroup button was pressed.
		if (isset($_POST['delete']))
		{
			checkSession();
			validateToken('admin-mmg');

			$result = $this->deleteMembergroups($_REQUEST['group']);

			// Need to throw a warning if it went wrong, but this is the only one we have a message for...
			if ($result === 'group_cannot_delete_sub')
				ErrorHandler::fatalLang('membergroups_cannot_delete_paid', false);

			redirectexit('action=admin;area=membergroups;');
		}
		// A form was submitted with the new membergroup settings.
		elseif (isset($_POST['save']))
		{
			// Validate the session.
			checkSession();
			validateToken('admin-mmg');

			// Can they really inherit from this group?
			if ($_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
			{
				$request = Db::$db->query('', '
					SELECT group_type
					FROM {db_prefix}membergroups
					WHERE id_group = {int:inherit_from}
					LIMIT {int:limit}',
					array(
						'inherit_from' => $_POST['group_inherit'],
						'limit' => 1,
					)
				);
				list($inherit_type) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			}

			// Set variables to their proper value.
			$_POST['max_messages'] = isset($_POST['max_messages']) ? (int) $_POST['max_messages'] : 0;

			$_POST['min_posts'] = isset($_POST['min_posts']) && isset($_POST['group_type']) && $_POST['group_type'] == -1 && $_REQUEST['group'] > 3 ? abs($_POST['min_posts']) : ($_REQUEST['group'] == 4 ? 0 : -1);

			$_POST['icons'] = (empty($_POST['icon_count']) || $_POST['icon_count'] < 0 || !in_array($_POST['icon_image'], Utils::$context['possible_icons'])) ? '' : min((int) $_POST['icon_count'], 99) . '#' . $_POST['icon_image'];

			$_POST['group_name'] = Utils::htmlspecialchars($_POST['group_name']);

			$_POST['group_desc'] = isset($_POST['group_desc']) && ($_REQUEST['group'] == 1 || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? Utils::htmlTrim(Utils::sanitizeChars(Utils::normalize($_POST['group_desc']))) : '';

			$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

			$_POST['group_hidden'] = empty($_POST['group_hidden']) || $_POST['min_posts'] != -1 || $_REQUEST['group'] == 3 ? 0 : (int) $_POST['group_hidden'];

			$_POST['group_inherit'] = $_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && (empty($inherit_type) || $inherit_type != 1) ? (int) $_POST['group_inherit'] : -2;

			$_POST['group_tfa_force'] = (empty(Config::$modSettings['tfa_mode']) || Config::$modSettings['tfa_mode'] != 2 || empty($_POST['group_tfa_force'])) ? 0 : 1;

			$_POST['online_color'] = (int) $_REQUEST['group'] != 3 && preg_match('/^#?\w+$/', Utils::htmlTrim($_POST['online_color'])) ? Utils::htmlTrim($_POST['online_color']) : '';

			// Do the update of the membergroup settings.
			Db::$db->query('', '
				UPDATE {db_prefix}membergroups
				SET group_name = {string:group_name}, online_color = {string:online_color},
					max_messages = {int:max_messages}, min_posts = {int:min_posts}, icons = {string:icons},
					description = {string:group_desc}, group_type = {int:group_type}, hidden = {int:group_hidden},
					id_parent = {int:group_inherit}, tfa_required = {int:tfa_required}
				WHERE id_group = {int:current_group}',
				array(
					'max_messages' => $_POST['max_messages'],
					'min_posts' => $_POST['min_posts'],
					'group_type' => $_POST['group_type'],
					'group_hidden' => $_POST['group_hidden'],
					'group_inherit' => $_POST['group_inherit'],
					'current_group' => (int) $_REQUEST['group'],
					'group_name' => $_POST['group_name'],
					'online_color' => $_POST['online_color'],
					'icons' => $_POST['icons'],
					'group_desc' => $_POST['group_desc'],
					'tfa_required' => $_POST['group_tfa_force'],
				)
			);

			call_integration_hook('integrate_save_membergroup', array((int) $_REQUEST['group']));

			// Time to update the boards this membergroup has access to.
			if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
			{
				$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];

				$changed_boards['allow'] = array();
				$changed_boards['deny'] = array();
				$changed_boards['ignore'] = array();

				foreach ($accesses as $board_id => $action)
					$changed_boards[$action][] = (int) $board_id;

				Db::$db->query('', '
					DELETE FROM {db_prefix}board_permissions_view
					WHERE id_group = {int:group_id}',
					array(
						'group_id' => (int) $_REQUEST['group'],
					)
				);

				foreach (array('allow', 'deny') as $board_action)
				{
					// Find all board this group is in, but shouldn't be in.
					$request = Db::$db->query('', '
						SELECT id_board, {raw:column}
						FROM {db_prefix}boards
						WHERE FIND_IN_SET({string:current_group}, {raw:column}) != 0' . (empty($changed_boards[$board_action]) ? '' : '
							AND id_board NOT IN ({array_int:board_access_list})'),
						array(
							'current_group' => (int) $_REQUEST['group'],
							'board_access_list' => $changed_boards[$board_action],
							'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
						)
					);
					while ($row = Db::$db->fetch_assoc($request))
					{
						Db::$db->query('', '
							UPDATE {db_prefix}boards
							SET {raw:column} = {string:member_group_access}
							WHERE id_board = {int:current_board}',
							array(
								'current_board' => $row['id_board'],
								'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($_REQUEST['group']))),
								'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
							)
						);
					}
					Db::$db->free_result($request);

					// Add the membergroup to all boards that hadn't been set yet.
					if (!empty($changed_boards[$board_action]))
					{
						Db::$db->query('', '
							UPDATE {db_prefix}boards
							SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
							WHERE id_board IN ({array_int:board_list})
								AND FIND_IN_SET({int:current_group}, {raw:column}) = 0',
							array(
								'board_list' => $changed_boards[$board_action],
								'blank_string' => '',
								'current_group' => (int) $_REQUEST['group'],
								'group_id_string' => (string) (int) $_REQUEST['group'],
								'comma_group' => ',' . $_REQUEST['group'],
								'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
							)
						);

						$insert = array();

						foreach ($changed_boards[$board_action] as $board_id)
						{
							$insert[] = array((int) $_REQUEST['group'], $board_id, $board_action == 'allow' ? 0 : 1);
						}

						Db::$db->insert('insert',
							'{db_prefix}board_permissions_view',
							array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
							$insert,
							array('id_group', 'id_board', 'deny')
						);
					}
				}
			}

			// Remove everyone from this group!
			if ($_POST['min_posts'] != -1)
			{
				Db::$db->query('', '
					UPDATE {db_prefix}members
					SET id_group = {int:regular_member}
					WHERE id_group = {int:current_group}',
					array(
						'regular_member' => 0,
						'current_group' => (int) $_REQUEST['group'],
					)
				);

				$updates = array();

				$request = Db::$db->query('', '
					SELECT id_member, additional_groups
					FROM {db_prefix}members
					WHERE FIND_IN_SET({string:current_group}, additional_groups) != 0',
					array(
						'current_group' => (int) $_REQUEST['group'],
					)
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					$updates[$row['additional_groups']][] = $row['id_member'];
				}
				Db::$db->free_result($request);

				foreach ($updates as $additional_groups => $memberArray)
				{
					User::updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), array((int) $_REQUEST['group'])))));
				}

				// Sorry, but post groups can't moderate boards
				Db::$db->query('', '
					DELETE FROM {db_prefix}moderator_groups
					WHERE id_group = {int:current_group}',
					array(
						'current_group' => (int) $_REQUEST['group'],
					)
				);
			}
			elseif ($_REQUEST['group'] != 3)
			{
				// Making it a hidden group? If so remove everyone with it as primary group (Actually, just make them additional).
				if ($_POST['group_hidden'] == 2)
				{
					$updates = array();

					$request = Db::$db->query('', '
						SELECT id_member, additional_groups
						FROM {db_prefix}members
						WHERE id_group = {int:current_group}
							AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
						array(
							'current_group' => (int) $_REQUEST['group'],
						)
					);
					while ($row = Db::$db->fetch_assoc($request))
					{
						$updates[$row['additional_groups']][] = $row['id_member'];
					}
					Db::$db->free_result($request);

					foreach ($updates as $additional_groups => $memberArray)
					{
						// We already validated $_REQUEST['group'] a while ago.
						$new_groups = (!empty($additional_groups) ? $additional_groups . ',' : '') . $_REQUEST['group'];

						User::updateMemberData($memberArray, array('additional_groups' => $new_groups));
					}

					Db::$db->query('', '
						UPDATE {db_prefix}members
						SET id_group = {int:regular_member}
						WHERE id_group = {int:current_group}',
						array(
							'regular_member' => 0,
							'current_group' => $_REQUEST['group'],
						)
					);

					// Hidden groups can't moderate boards
					Db::$db->query('', '
						DELETE FROM {db_prefix}moderator_groups
						WHERE id_group = {int:current_group}',
						array(
							'current_group' => $_REQUEST['group'],
						)
					);
				}

				// Either way, let's check our "show group membership" setting is correct.
				$request = Db::$db->query('', '
					SELECT COUNT(*)
					FROM {db_prefix}membergroups
					WHERE group_type > {int:non_joinable}',
					array(
						'non_joinable' => 1,
					)
				);
				list($have_joinable) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// Do we need to update the setting?
				if ((empty(Config::$modSettings['show_group_membership']) && $have_joinable) || (!empty(Config::$modSettings['show_group_membership']) && !$have_joinable))
				{
					Config::updateModSettings(array('show_group_membership' => $have_joinable ? 1 : 0));
				}
			}

			// Do we need to set inherited permissions?
			if ($_POST['group_inherit'] != -2 && $_POST['group_inherit'] != $_POST['old_inherit'])
			{
				Permissions::updateChildPermissions($_POST['group_inherit']);
			}

			// Finally, moderators!
			$moderator_string = isset($_POST['group_moderators']) ? trim($_POST['group_moderators']) : '';

			Db::$db->query('', '
				DELETE FROM {db_prefix}group_moderators
				WHERE id_group = {int:current_group}',
				array(
					'current_group' => $_REQUEST['group'],
				)
			);

			if ((!empty($moderator_string) || !empty($_POST['moderator_list'])) && $_POST['min_posts'] == -1 && $_REQUEST['group'] != 3)
			{
				$group_moderators = array();

				// Get all the usernames from the string
				if (!empty($moderator_string))
				{
					$moderator_string = strtr(preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', Utils::htmlspecialchars($moderator_string, ENT_QUOTES)), array('&quot;' => '"'));

					preg_match_all('~"([^"]+)"~', $moderator_string, $matches);

					$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));

					for ($k = 0, $n = count($moderators); $k < $n; $k++)
					{
						$moderators[$k] = trim($moderators[$k]);

						if (strlen($moderators[$k]) == 0)
							unset($moderators[$k]);
					}

					// Find all the id_member's for the member_name's in the list.
					if (!empty($moderators))
					{
						$request = Db::$db->query('', '
							SELECT id_member
							FROM {db_prefix}members
							WHERE member_name IN ({array_string:moderators}) OR real_name IN ({array_string:moderators})
							LIMIT {int:count}',
							array(
								'moderators' => $moderators,
								'count' => count($moderators),
							)
						);
						while ($row = Db::$db->fetch_assoc($request))
						{
							$group_moderators[] = $row['id_member'];
						}
						Db::$db->free_result($request);
					}
				}

				if (!empty($_POST['moderator_list']))
				{
					$moderators = array();

					foreach ($_POST['moderator_list'] as $moderator)
						$moderators[] = (int) $moderator;

					if (!empty($moderators))
					{
						$request = Db::$db->query('', '
							SELECT id_member
							FROM {db_prefix}members
							WHERE id_member IN ({array_int:moderators})
							LIMIT {int:num_moderators}',
							array(
								'moderators' => $moderators,
								'num_moderators' => count($moderators),
							)
						);
						while ($row = Db::$db->fetch_assoc($request))
						{
							$group_moderators[] = $row['id_member'];
						}
						Db::$db->free_result($request);
					}
				}

				// Make sure we don't have any duplicates first...
				$group_moderators = array_unique($group_moderators);

				// Found some?
				if (!empty($group_moderators))
				{
					$mod_insert = array();

					foreach ($group_moderators as $moderator)
						$mod_insert[] = array($_REQUEST['group'], $moderator);

					Db::$db->insert('insert',
						'{db_prefix}group_moderators',
						array('id_group' => 'int', 'id_member' => 'int'),
						$mod_insert,
						array('id_group', 'id_member')
					);
				}
			}

			// There might have been some post group changes.
			updateStats('postgroups');

			// We've definitely changed some group stuff.
			Config::updateModSettings(array(
				'settings_updated' => time(),
			));

			// Log the edit.
			logAction('edited_group', array('group' => $_POST['group_name']), 'admin');

			redirectexit('action=admin;area=membergroups');
		}

		// Fetch the current group information.
		$request = Db::$db->query('', '
			SELECT group_name, description, min_posts, online_color, max_messages, icons, group_type, hidden, id_parent, tfa_required
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => (int) $_REQUEST['group'],
			)
		);
		if (Db::$db->num_rows($request) == 0)
		{
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		$row['icons'] = explode('#', $row['icons']);

		Utils::$context['group'] = array(
			'id' => $_REQUEST['group'],
			'name' => $row['group_name'],
			'description' => Utils::htmlspecialchars($row['description'], ENT_QUOTES),
			'editable_name' => $row['group_name'],
			'color' => $row['online_color'],
			'min_posts' => $row['min_posts'],
			'max_messages' => $row['max_messages'],
			'icon_count' => (int) $row['icons'][0],
			'icon_image' => isset($row['icons'][1]) ? $row['icons'][1] : '',
			'is_post_group' => $row['min_posts'] != -1,
			'type' => $row['min_posts'] != -1 ? 0 : $row['group_type'],
			'hidden' => $row['min_posts'] == -1 ? $row['hidden'] : 0,
			'inherited_from' => $row['id_parent'],
			'allow_post_group' => $_REQUEST['group'] == 2 || $_REQUEST['group'] > 4,
			'allow_delete' => $_REQUEST['group'] == 2 || $_REQUEST['group'] > 4,
			'allow_protected' => allowedTo('admin_forum'),
			'tfa_required' => $row['tfa_required'],
		);

		// Get any moderators for this group
		Utils::$context['group']['moderators'] = array();
		$request = Db::$db->query('', '
			SELECT mem.id_member, mem.real_name
			FROM {db_prefix}group_moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE mods.id_group = {int:current_group}',
			array(
				'current_group' => $_REQUEST['group'],
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['group']['moderators'][$row['id_member']] = $row['real_name'];
		}
		Db::$db->free_result($request);

		Utils::$context['group']['moderator_list'] = empty(Utils::$context['group']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', Utils::$context['group']['moderators']) . '&quot;';

		if (!empty(Utils::$context['group']['moderators']))
		{
			list(Utils::$context['group']['last_moderator_id']) = array_slice(array_keys(Utils::$context['group']['moderators']), -1);
		}

		// Get a list of boards this membergroup is allowed to see.
		Utils::$context['boards'] = array();
		if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
		{
			Utils::$context['categories'] = array();
			$request = Db::$db->query('', '
				SELECT b.id_cat, c.name as cat_name, b.id_board, b.name, b.child_level,
				FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access, FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				ORDER BY board_order',
				array(
					'current_group' => (int) $_REQUEST['group'],
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				// This category hasn't been set up yet..
				if (!isset(Utils::$context['categories'][$row['id_cat']]))
				{
					Utils::$context['categories'][$row['id_cat']] = array(
						'id' => $row['id_cat'],
						'name' => $row['cat_name'],
						'boards' => array()
					);
				}

				// Set this board up, and let the template know when it's a child.  (indent them..)
				Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
					'id' => $row['id_board'],
					'name' => $row['name'],
					'child_level' => $row['child_level'],
					'allow' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
					'deny' => !(empty($row['cannot_access']) || $row['cannot_access'] == 'f'),
				);
			}
			Db::$db->free_result($request);

			// Now, let's sort the list of categories into the boards for templates that like that.
			$temp_boards = array();
			foreach (Utils::$context['categories'] as $category)
			{
				$temp_boards[] = array(
					'name' => $category['name'],
					'child_ids' => array_keys($category['boards'])
				);
				$temp_boards = array_merge($temp_boards, array_values($category['boards']));

				// Include a list of boards per category for easy toggling.
				Utils::$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
			}
		}

		// Insert our JS, if we have possible icons.
		if (!empty(Utils::$context['possible_icons']))
		{
			Theme::loadJavaScriptFile('icondropdown.js', array('validate' => true, 'minimize' => true), 'smf_icondropdown');
		}

		Theme::loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');

		// Finally, get all the groups this could inherit from.
		Utils::$context['inheritable_groups'] = array();
		$request = Db::$db->query('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group != {int:current_group}' .
				(empty(Config::$modSettings['permission_enable_postgroups']) ? '
				AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
				AND group_type != {int:is_protected}') . '
				AND id_group NOT IN (1, 3)
				AND id_parent = {int:not_inherited}',
			array(
				'current_group' => (int) $_REQUEST['group'],
				'min_posts' => -1,
				'not_inherited' => -2,
				'is_protected' => 1,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['inheritable_groups'][$row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		call_integration_hook('integrate_view_membergroup');

		Utils::$context['sub_template'] = 'edit_group';
		Utils::$context['page_title'] = Lang::$txt['membergroups_edit_group'];

		createToken('admin-mmg');
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

		if (isset($_REQUEST['save']))
		{
			checkSession();
			call_integration_hook('integrate_save_membergroup_settings');

			// Yeppers, saving this...
			ACP::saveDBSettings($config_vars);

			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=membergroups;sa=settings');
		}

		// Some simple context.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=membergroups;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['membergroups_settings'];

		// We need this for the in-line permissions
		createToken('admin-mp');

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
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the membergroups area.
	 */
	public static function getConfigVars(): array
	{
		// Only one thing here!
		$config_vars = array(
			array('permissions', 'manage_membergroups'),
		);

		call_integration_hook('integrate_modify_membergroup_settings', array(&$config_vars));

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
		if (!empty($return_config))
			return self::getConfigVars();

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
		Menu::$loaded['admin']->tab_data = array(
			'title' => Lang::$txt['membergroups_title'],
			'help' => 'membergroups',
			'description' => Lang::$txt['membergroups_description'],
		);

		call_integration_hook('integrate_manage_membergroups', array(&self::$subactions));

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]))
		{
			$this->subaction = $_REQUEST['sa'];
		}
		elseif (!allowedTo('manage_membergroups'))
		{
			$this->subaction = 'settings';
		}
	}

	/**
	 * Delete one of more membergroups.
	 *
	 * Requires the manage_membergroups permission.
	 * Returns true on success or false on failure.
	 * Has protection against deletion of protected membergroups.
	 * Deletes the permissions linked to the membergroup.
	 * Takes members out of the deleted membergroups.
	 *
	 * @param int|array $groups The ID of the group to delete or an array of IDs of groups to delete
	 * @return bool|string True for success, otherwise an identifier as to reason for failure
	 */
	protected function deleteMembergroups($groups): bool|string
	{
		// Make sure it's an array of integers.
		$groups = array_unique(array_map('intval', (array) $groups));

		// Some groups are protected (guests, administrators, moderators, newbies).
		$protected_groups = array(-1, 0, 1, 3, 4);

		// There maybe some others as well.
		if (!allowedTo('admin_forum'))
		{
			$request = Db::$db->query('', '
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE group_type = {int:is_protected}',
				array(
					'is_protected' => 1,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$protected_groups[] = $row['id_group'];
			}
			Db::$db->free_result($request);
		}

		// Make sure they don't delete protected groups!
		$groups = array_diff($groups, array_unique($protected_groups));

		if (empty($groups))
			return 'no_group_found';

		// Make sure they don't try to delete a group attached to a paid subscription.
		$subscriptions = array();

		$request = Db::$db->query('', '
			SELECT id_subscribe, name, id_group, add_groups
			FROM {db_prefix}subscriptions
			ORDER BY name',
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (in_array($row['id_group'], $groups))
			{
				$subscriptions[] = $row['name'];
			}
			else
			{
				$add_groups = explode(',', $row['add_groups']);

				if (count(array_intersect($add_groups, $groups)) != 0)
					$subscriptions[] = $row['name'];
			}
		}
		Db::$db->free_result($request);

		if (!empty($subscriptions))
		{
			// Uh oh. But before we return, we need to update a language string because we want the names of the groups.
			Lang::load('ManageMembers');
			Lang::$txt['membergroups_cannot_delete_paid'] = sprintf(Lang::$txt['membergroups_cannot_delete_paid'], implode(', ', $subscriptions));

			return 'group_cannot_delete_sub';
		}

		// Log the deletion.
		$request = Db::$db->query('', '
			SELECT group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			logAction('delete_group', array('group' => $row['group_name']), 'admin');
		}
		Db::$db->free_result($request);

		call_integration_hook('integrate_delete_membergroups', array($groups));

		// Remove the membergroups themselves.
		Db::$db->query('', '
			DELETE FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);

		// Remove the permissions of the membergroups.
		Db::$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}group_moderators
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}moderator_groups
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);

		// Delete any outstanding requests.
		Db::$db->query('', '
			DELETE FROM {db_prefix}log_group_requests
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);

		// Update the primary groups of members.
		Db::$db->query('', '
			UPDATE {db_prefix}members
			SET id_group = {int:regular_group}
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
				'regular_group' => 0,
			)
		);

		// Update any inherited groups (Lose inheritance).
		Db::$db->query('', '
			UPDATE {db_prefix}membergroups
			SET id_parent = {int:uninherited}
			WHERE id_parent IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
				'uninherited' => -2,
			)
		);

		// Update the additional groups of members.
		$updates = array();

		$request = Db::$db->query('', '
			SELECT id_member, additional_groups
			FROM {db_prefix}members
			WHERE FIND_IN_SET({raw:additional_groups_explode}, additional_groups) != 0',
			array(
				'additional_groups_explode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$updates[$row['additional_groups']][] = $row['id_member'];
		}
		Db::$db->free_result($request);

		foreach ($updates as $additional_groups => $memberArray)
		{
			User::updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups))));
		}

		// No boards can provide access to these membergroups anymore.
		$updates = array();

		$request = Db::$db->query('', '
			SELECT id_board, member_groups
			FROM {db_prefix}boards
			WHERE FIND_IN_SET({raw:member_groups_explode}, member_groups) != 0',
			array(
				'member_groups_explode' => implode(', member_groups) != 0 OR FIND_IN_SET(', $groups),
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$updates[$row['member_groups']][] = $row['id_board'];
		}
		Db::$db->free_result($request);

		foreach ($updates as $member_groups => $boardArray)
		{
			Db::$db->query('', '
				UPDATE {db_prefix}boards
				SET member_groups = {string:member_groups}
				WHERE id_board IN ({array_int:board_lists})',
				array(
					'board_lists' => $boardArray,
					'member_groups' => implode(',', array_diff(explode(',', $member_groups), $groups)),
				)
			);
		}

		// Recalculate the post groups, as they likely changed.
		updateStats('postgroups');

		// Make a note of the fact that the cache may be wrong.
		$settings_update = array('settings_updated' => time());

		// Have we deleted the spider group?
		if (isset(Config::$modSettings['spider_group']) && in_array(Config::$modSettings['spider_group'], $groups))
		{
			$settings_update['spider_group'] = 0;
		}

		Config::updateModSettings($settings_update);

		// It was a success.
		return true;
	}

}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Membergroups::exportStatic'))
	Membergroups::exportStatic();

?>