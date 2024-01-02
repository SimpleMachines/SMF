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
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class is exclusively for generating reports to help assist forum
 * administrators keep track of their forum configuration and state.
 */
class Reports implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ReportsMain',
			'boardReport' => 'BoardReport',
			'boardPermissionsReport' => 'BoardPermissionsReport',
			'memberGroupsReport' => 'MemberGroupsReport',
			'groupPermissionsReport' => 'GroupPermissionsReport',
			'staffReport' => 'StaffReport',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action (i.e. the report type).
	 * This should be set by the constructor.
	 */
	public string $subaction = '';

	/**
	 * @var string
	 *
	 * The sub-template to use.
	 */
	public string $sub_template = 'main';

	/**
	 * @var array
	 *
	 * Info about the different types of reports we can generate.
	 */
	public array $report_types = [];

	/**
	 * @var array
	 *
	 * What are valid templates for showing reports?
	 */
	public array $reportTemplates = [
		'main' => [
			'layers' => null,
		],
		'print' => [
			'layers' => ['print'],
		],
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'boards' => 'boards',
		'board_perms' => 'boardPerms',
		'member_groups' => 'memberGroups',
		'group_perms' => 'groupPerms',
		'staff' => 'staff',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * The tables that comprise this report.
	 */
	protected array $tables = [];

	/**
	 * @var int
	 *
	 * The number of tables that comprise this report.
	 */
	protected int $table_count = 0;

	/**
	 * @var int
	 *
	 * The table currently being worked upon.
	 */
	protected int $current_table = 0;

	/**
	 * @var array
	 *
	 * The keys of the array that represents the current table.
	 */
	protected array $keys = [];

	/**
	 * @var string
	 *
	 * Indicates what the keys of the current table array signify.
	 * Can be either 'rows' or 'cols'.
	 */
	protected string $key_method;

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
		if (empty($this->subaction)) {
			$this->sub_template = 'report_type';

			return;
		}

		// Specific template? Use that instead of main!
		if (isset($_REQUEST['st'], $this->reportTemplates[$_REQUEST['st']])) {
			$this->sub_template = $_REQUEST['st'];

			// Are we disabling the other layers - print friendly for example?
			if ($this->reportTemplates[$_REQUEST['st']]['layers'] !== null) {
				Utils::$context['template_layers'] = $this->reportTemplates[$_REQUEST['st']]['layers'];
			}
		}

		// Make the page title more descriptive.
		Utils::$context['page_title'] .= ' - ' . (Lang::$txt['gr_type_' . $this->subaction] ?? $this->subaction);

		// Build the reports button array.
		Utils::$context['report_buttons'] = [
			'generate_reports' => ['text' => 'generate_reports', 'image' => 'print.png', 'url' => Config::$scripturl . '?action=admin;area=reports', 'active' => true],
			'print' => ['text' => 'print', 'image' => 'print.png', 'url' => Config::$scripturl . '?action=admin;area=reports;rt=' . $this->subaction . ';st=print', 'custom' => 'target="_blank"'],
		];

		// Allow mods to add additional buttons here.
		IntegrationHook::call('integrate_report_buttons');

		// Now generate the data.
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}

		// Finish the tables before exiting - this is to help the templates a little more.
		$this->finishTables();
	}

	/**
	 * Standard report about what settings the boards have.
	 */
	public function boards(): void
	{
		// Load the permission profiles.
		Lang::load('ManagePermissions');
		Permissions::loadPermissionProfiles();

		// Get every moderator.
		$moderators = [];

		$request = Db::$db->query(
			'',
			'SELECT mods.id_board, mods.id_member, mem.real_name
			FROM {db_prefix}moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$moderators[$row['id_board']][] = $row['real_name'];
		}
		Db::$db->free_result($request);

		// Get every moderator gruop.
		$moderator_groups = [];

		$request = Db::$db->query(
			'',
			'SELECT modgs.id_board, modgs.id_group, memg.group_name
			FROM {db_prefix}moderator_groups AS modgs
				INNER JOIN {db_prefix}membergroups AS memg ON (memg.id_group = modgs.id_group)',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$moderator_groups[$row['id_board']][] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Get all the possible membergroups!
		$groups = [-1 => Lang::$txt['guest_title'], 0 => Lang::$txt['membergroups_members']];

		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name, online_color
			FROM {db_prefix}membergroups',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
		}
		Db::$db->free_result($request);

		// All the fields we'll show.
		$boardSettings = [
			'category' => Lang::$txt['board_category'],
			'parent' => Lang::$txt['board_parent'],
			'redirect' => Lang::$txt['board_redirect'],
			'num_topics' => Lang::$txt['board_num_topics'],
			'num_posts' => Lang::$txt['board_num_posts'],
			'count_posts' => Lang::$txt['board_count_posts'],
			'theme' => Lang::$txt['board_theme'],
			'override_theme' => Lang::$txt['board_override_theme'],
			'profile' => Lang::$txt['board_profile'],
			'moderators' => Lang::$txt['board_moderators'],
			'moderator_groups' => Lang::$txt['board_moderator_groups'],
			'groups' => Lang::$txt['board_groups'],
		];

		if (!empty(Config::$modSettings['deny_boards_access'])) {
			$boardSettings['disallowed_groups'] = Lang::$txt['board_disallowed_groups'];
		}

		// Do it in columns, it's just easier.
		$this->setKeys('cols');

		// Go through each board!
		$request = Db::$db->query(
			'order_by_board_order',
			'SELECT b.id_board, b.name, b.num_posts, b.num_topics, b.count_posts, b.member_groups, b.override_theme, b.id_profile, b.deny_member_groups,
				b.redirect, c.name AS cat_name, COALESCE(par.name, {string:text_none}) AS parent_name, COALESCE(th.value, {string:text_none}) AS theme_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}boards AS par ON (par.id_board = b.id_parent)
				LEFT JOIN {db_prefix}themes AS th ON (th.id_theme = b.id_theme AND th.variable = {string:name})
			ORDER BY b.board_order',
			[
				'name' => 'name',
				'text_none' => Lang::$txt['none'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Each board has it's own table.
			$this->newTable($row['name'], '', 'left', 'auto', 'left', 200, 'left');

			$this_boardSettings = $boardSettings;

			if (empty($row['redirect'])) {
				unset($this_boardSettings['redirect']);
			}

			// First off, add in the side key.
			$this->addData($this_boardSettings);

			// Create the main data array.
			$boardData = [
				'category' => $row['cat_name'],
				'parent' => $row['parent_name'],
				'redirect' => $row['redirect'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'count_posts' => empty($row['count_posts']) ? Lang::$txt['yes'] : Lang::$txt['no'],
				'theme' => $row['theme_name'],
				'profile' => Utils::$context['profiles'][$row['id_profile']]['name'],
				'override_theme' => $row['override_theme'] ? Lang::$txt['yes'] : Lang::$txt['no'],
				'moderators' => empty($moderators[$row['id_board']]) ? Lang::$txt['none'] : implode(', ', $moderators[$row['id_board']]),
				'moderator_groups' => empty($moderator_groups[$row['id_board']]) ? Lang::$txt['none'] : implode(', ', $moderator_groups[$row['id_board']]),
			];

			// Work out the membergroups who can and cannot access it (but only if enabled).
			$allowedGroups = explode(',', $row['member_groups']);

			foreach ($allowedGroups as $key => $group) {
				if (isset($groups[$group])) {
					$allowedGroups[$key] = $groups[$group];
				} else {
					unset($allowedGroups[$key]);
				}
			}

			$boardData['groups'] = implode(', ', $allowedGroups);

			if (!empty(Config::$modSettings['deny_boards_access'])) {
				$disallowedGroups = explode(',', $row['deny_member_groups']);

				foreach ($disallowedGroups as $key => $group) {
					if (isset($groups[$group])) {
						$disallowedGroups[$key] = $groups[$group];
					} else {
						unset($disallowedGroups[$key]);
					}
				}

				$boardData['disallowed_groups'] = implode(', ', $disallowedGroups);
			}

			if (empty($row['redirect'])) {
				unset($boardData['redirect']);
			}

			// Next add the main data.
			$this->addData($boardData);
		}
		Db::$db->free_result($request);
	}

	/**
	 * Generate a report on the current permissions by board and membergroup.
	 */
	public function boardPerms(): void
	{
		// Get as much memory as possible as this can be big.
		Config::setMemoryLimit('256M');

		if (isset($_REQUEST['boards'])) {
			if (!is_array($_REQUEST['boards'])) {
				$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			}

			foreach ($_REQUEST['boards'] as $k => $dummy) {
				$_REQUEST['boards'][$k] = (int) $dummy;
			}

			$board_clause = 'id_board IN ({array_int:boards})';
		} else {
			$board_clause = '1=1';
		}

		if (isset($_REQUEST['groups'])) {
			if (!is_array($_REQUEST['groups'])) {
				$_REQUEST['groups'] = explode(',', $_REQUEST['groups']);
			}

			foreach ($_REQUEST['groups'] as $k => $dummy) {
				$_REQUEST['groups'][$k] = (int) $dummy;
			}

			$group_clause = 'id_group IN ({array_int:groups})';
		} else {
			$group_clause = '1=1';
		}

		// Fetch all the board names and profiles.
		$boards = [];
		$profiles = [];

		$request = Db::$db->query(
			'',
			'SELECT id_board, name, id_profile
			FROM {db_prefix}boards
			WHERE ' . $board_clause . '
			ORDER BY id_board',
			[
				'boards' => $_REQUEST['boards'] ?? [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$boards[$row['id_board']] = [
				'name' => $row['name'],
				'profile' => $row['id_profile'],
				'mod_groups' => [],
			];

			$profiles[] = $row['id_profile'];
		}
		Db::$db->free_result($request);

		// Get the ids of any groups allowed to moderate this board
		// Limit it to any boards and/or groups we're looking at
		$request = Db::$db->query(
			'',
			'SELECT id_board, id_group
			FROM {db_prefix}moderator_groups
			WHERE ' . $board_clause . ' AND ' . $group_clause,
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$boards[$row['id_board']]['mod_groups'][] = $row['id_group'];
		}
		Db::$db->free_result($request);

		// Get all the possible membergroups, except admin!
		if (!isset($_REQUEST['groups']) || in_array(-1, $_REQUEST['groups']) || in_array(0, $_REQUEST['groups'])) {
			$member_groups = ['col' => '', -1 => Lang::$txt['membergroups_guests'], 0 => Lang::$txt['membergroups_members']];
		} else {
			$member_groups = ['col' => ''];
		}

		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE ' . $group_clause . '
				AND id_group != {int:admin_group}' . (empty(Config::$modSettings['permission_enable_postgroups']) ? '
				AND min_posts = {int:min_posts}' : '') . '
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			[
				'admin_group' => 1,
				'min_posts' => -1,
				'newbie_group' => 4,
				'groups' => $_REQUEST['groups'] ?? [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$member_groups[$row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Make sure that every group is represented - plus in rows!
		$this->setKeys('rows', $member_groups);

		// Certain permissions should not really be shown.
		$disabled_permissions = [];

		if (!Config::$modSettings['postmod_active']) {
			$disabled_permissions[] = 'approve_posts';
			$disabled_permissions[] = 'post_unapproved_topics';
			$disabled_permissions[] = 'post_unapproved_replies_own';
			$disabled_permissions[] = 'post_unapproved_replies_any';
			$disabled_permissions[] = 'post_unapproved_attachments';
		}

		IntegrationHook::call('integrate_reports_boardperm', [&$disabled_permissions]);

		// Cache every permission setting, to make sure we don't miss any allows.
		$permissions = [];
		$board_permissions = [];

		$request = Db::$db->query(
			'',
			'SELECT id_profile, id_group, add_deny, permission
			FROM {db_prefix}board_permissions
			WHERE id_profile IN ({array_int:profile_list})
				AND ' . $group_clause . (empty(Config::$modSettings['permission_enable_deny']) ? '
				AND add_deny = {int:not_deny}' : '') . '
			ORDER BY id_profile, permission',
			[
				'profile_list' => $profiles,
				'not_deny' => 1,
				'groups' => $_REQUEST['groups'] ?? [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (in_array($row['permission'], $disabled_permissions)) {
				continue;
			}

			foreach ($boards as $id => $board) {
				if ($board['profile'] == $row['id_profile']) {
					$board_permissions[$id][$row['id_group']][$row['permission']] = $row['add_deny'];
				}
			}

			// Make sure we get every permission.
			if (!isset($permissions[$row['permission']])) {
				// This will be reused on other boards.
				$permissions[$row['permission']] = [
					'title' => Lang::$txt['board_perms_name_' . $row['permission']] ?? $row['permission'],
				];
			}
		}
		Db::$db->free_result($request);

		// Now cycle through the board permissions array... lots to do ;)
		foreach ($board_permissions as $board => $groups) {
			// Create the table for this board first.
			$this->newTable($boards[$board]['name'], 'x', 'all', 100, 'center', 200, 'left');

			// Add the header row - shows all the membergroups.
			$this->addData($member_groups);

			// Add the separator.
			$this->addSeparator(Lang::$txt['board_perms_permission']);

			// Here cycle through all the detected permissions.
			foreach ($permissions as $ID_PERM => $perm_info) {
				// Default data for this row.
				$curData = ['col' => $perm_info['title']];

				// Now cycle each membergroup in this set of permissions.
				foreach ($member_groups as $id_group => $name) {
					// Don't overwrite the key column!
					if ($id_group === 'col') {
						continue;
					}

					$group_permissions = $groups[$id_group] ?? [];

					// Do we have any data for this group?
					if (isset($group_permissions[$ID_PERM])) {
						// Set the data for this group to be the local permission.
						$curData[$id_group] = $group_permissions[$ID_PERM];
					}
					// Is it inherited from Moderator?
					elseif (in_array($id_group, $boards[$board]['mod_groups']) && !empty($groups[3]) && isset($groups[3][$ID_PERM])) {
						$curData[$id_group] = $groups[3][$ID_PERM];
					}
					// Otherwise means it's set to disallow..
					else {
						$curData[$id_group] = 'x';
					}

					// Now actually make the data for the group look right.
					if (empty($curData[$id_group])) {
						$curData[$id_group] = '<span class="red">' . Lang::$txt['board_perms_deny'] . '</span>';
					} elseif ($curData[$id_group] == 1) {
						$curData[$id_group] = '<span style="color: darkgreen;">' . Lang::$txt['board_perms_allow'] . '</span>';
					} else {
						$curData[$id_group] = 'x';
					}

					// Embolden those permissions different from global (makes it a lot easier!)
					if (@$board_permissions[0][$id_group][$ID_PERM] != @$group_permissions[$ID_PERM]) {
						$curData[$id_group] = '<strong>' . $curData[$id_group] . '</strong>';
					}
				}

				// Now add the data for this permission.
				$this->addData($curData);
			}
		}
	}

	/**
	 * Show what the membergroups are made of.
	 */
	public function memberGroups(): void
	{
		// Fetch all the board names.
		$request = Db::$db->query(
			'',
			'SELECT id_board, name, member_groups, id_profile, deny_member_groups
			FROM {db_prefix}boards',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (trim($row['member_groups']) == '') {
				$groups = [1];
			} else {
				$groups = array_merge([1], explode(',', $row['member_groups']));
			}

			if (trim($row['deny_member_groups']) == '') {
				$denyGroups = [];
			} else {
				$denyGroups = explode(',', $row['deny_member_groups']);
			}

			$boards[$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'profile' => $row['id_profile'],
				'groups' => $groups,
				'deny_groups' => $denyGroups,
			];
		}
		Db::$db->free_result($request);

		// Standard settings.
		$mgSettings = [
			'name' => '',
			'#sep#1' => Lang::$txt['member_group_settings'],
			'color' => Lang::$txt['member_group_color'],
			'min_posts' => Lang::$txt['member_group_min_posts'],
			'max_messages' => Lang::$txt['member_group_max_messages'],
			'icons' => Lang::$txt['member_group_icons'],
			'#sep#2' => Lang::$txt['member_group_access'],
		];

		// Add on the boards!
		foreach ($boards as $board) {
			$mgSettings['board_' . $board['id']] = $board['name'];
		}

		// Add all the membergroup settings, plus we'll be adding in columns!
		$this->setKeys('cols', $mgSettings);

		// Only one table this time!
		$this->newTable(Lang::$txt['gr_type_member_groups'], '-', 'all', 100, 'center', 200, 'left');

		// Get the shaded column in.
		$this->addData($mgSettings);

		// Now start cycling through the membergroups!
		foreach (Group::loadSimple(Group::LOAD_BOTH, []) as $group) {
			$group_info = [
				'id' => $group->id,
				'name' => $group->name,
				'color' => empty($group->online_color) ? '-' : '<span style="color: ' . $group->online_color . ';">' . $group->online_color . '</span>',
				'min_posts' => $group->min_posts == -1 ? Lang::$txt['not_applicable'] : $group->min_posts,
				'max_messages' => $group->max_messages,
				'icons' => $group->icons,
			];

			// Board permissions.
			foreach ($boards as $board) {
				$group_info['board_' . $board['id']] = in_array($group->id, $board['groups']) ? '<span class="success">' . Lang::$txt['board_perms_allow'] . '</span>' : (!empty(Config::$modSettings['deny_boards_access']) && in_array($group->id, $board['deny_groups']) ? '<span class="alert">' . Lang::$txt['board_perms_deny'] . '</span>' : 'x');
			}

			$this->addData($group_info);
		}
	}

	/**
	 * Show the large variety of group permissions assigned to each membergroup.
	 */
	public function groupPerms(): void
	{
		if (isset($_REQUEST['groups'])) {
			if (!is_array($_REQUEST['groups'])) {
				$_REQUEST['groups'] = explode(',', $_REQUEST['groups']);
			}

			foreach ($_REQUEST['groups'] as $k => $dummy) {
				$_REQUEST['groups'][$k] = (int) $dummy;
			}

			$_REQUEST['groups'] = array_diff($_REQUEST['groups'], [3]);

			$clause = 'id_group IN ({array_int:groups})';
		} else {
			$clause = 'id_group != {int:moderator_group}';
		}

		// Get all the possible membergroups, except admin!
		if (!isset($_REQUEST['groups']) || in_array(-1, $_REQUEST['groups']) || in_array(0, $_REQUEST['groups'])) {
			$groups = ['col' => '', -1 => Lang::$txt['membergroups_guests'], 0 => Lang::$txt['membergroups_members']];
		} else {
			$groups = ['col' => ''];
		}

		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE ' . $clause . '
				AND id_group != {int:admin_group}' . (empty(Config::$modSettings['permission_enable_postgroups']) ? '
				AND min_posts = {int:min_posts}' : '') . '
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			[
				'admin_group' => 1,
				'min_posts' => -1,
				'newbie_group' => 4,
				'moderator_group' => 3,
				'groups' => $_REQUEST['groups'] ?? [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$groups[$row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Make sure that every group is represented!
		$this->setKeys('rows', $groups);

		// Create the table first.
		$this->newTable(Lang::$txt['gr_type_group_perms'], '-', 'all', 100, 'center', 200, 'left');

		// Show all the groups
		$this->addData($groups);

		// Add a separator
		$this->addSeparator(Lang::$txt['board_perms_permission']);

		// Certain permissions should not really be shown.
		$disabled_permissions = [];

		if (empty(Config::$modSettings['cal_enabled'])) {
			$disabled_permissions[] = 'calendar_view';
			$disabled_permissions[] = 'calendar_post';
			$disabled_permissions[] = 'calendar_edit_own';
			$disabled_permissions[] = 'calendar_edit_any';
		}

		if (empty(Config::$modSettings['warning_settings']) || Config::$modSettings['warning_settings'][0] == 0) {
			$disabled_permissions[] = 'issue_warning';
		}

		IntegrationHook::call('integrate_reports_groupperm', [&$disabled_permissions]);

		// Now the big permission fetch!
		$lastPermission = null;
		$curData = [];

		$request = Db::$db->query(
			'',
			'SELECT id_group, add_deny, permission
			FROM {db_prefix}permissions
			WHERE ' . $clause . (empty(Config::$modSettings['permission_enable_deny']) ? '
				AND add_deny = {int:not_denied}' : '') . '
			ORDER BY permission',
			[
				'not_denied' => 1,
				'moderator_group' => 3,
				'groups' => $_REQUEST['groups'] ?? [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (in_array($row['permission'], $disabled_permissions)) {
				continue;
			}

			if (strpos($row['permission'], 'bbc_') === 0) {
				Lang::$txt['group_perms_name_' . $row['permission']] = sprintf(Lang::$txt['group_perms_name_bbc'], substr($row['permission'], 4));
			}

			// If this is a new permission flush the last row.
			if ($row['permission'] != $lastPermission) {
				// Send the data!
				if ($lastPermission !== null) {
					$this->addData($curData);
				}

				// Add the permission name in the left column.
				$curData = ['col' => Lang::$txt['group_perms_name_' . $row['permission']] ?? $row['permission']];

				$lastPermission = $row['permission'];
			}

			// Good stuff - add the permission to the list!
			if ($row['add_deny']) {
				$curData[$row['id_group']] = '<span style="color: darkgreen;">' . Lang::$txt['board_perms_allow'] . '</span>';
			} else {
				$curData[$row['id_group']] = '<span class="red">' . Lang::$txt['board_perms_deny'] . '</span>';
			}
		}
		Db::$db->free_result($request);

		// Flush the last data!
		$this->addData($curData);
	}

	/**
	 * Report for showing all the forum staff members - quite a feat!
	 */
	public function staff(): void
	{
		// Fetch all the board names.
		$boards = [];

		$request = Db::$db->query(
			'',
			'SELECT id_board, name
			FROM {db_prefix}boards',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$boards[$row['id_board']] = $row['name'];
		}
		Db::$db->free_result($request);

		// Get every moderator.
		$moderators = [];
		$local_mods = [];

		$request = Db::$db->query(
			'',
			'SELECT mods.id_board, mods.id_member
			FROM {db_prefix}moderators AS mods',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$moderators[$row['id_member']][] = $row['id_board'];
			$local_mods[$row['id_member']] = $row['id_member'];
		}
		Db::$db->free_result($request);

		// Get any additional boards they can moderate through group-based board moderation
		$request = Db::$db->query(
			'',
			'SELECT mem.id_member, modgs.id_board
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_group = mem.id_group OR FIND_IN_SET(modgs.id_group, mem.additional_groups) != 0)',
			[
			],
		);

		// Add each board/member to the arrays, but only if they aren't already there
		while ($row = Db::$db->fetch_assoc($request)) {
			// Either we don't have them as a moderator at all or at least not as a moderator of this board
			if (!array_key_exists($row['id_member'], $moderators) || !in_array($row['id_board'], $moderators[$row['id_member']])) {
				$moderators[$row['id_member']][] = $row['id_board'];
			}

			// We don't have them listed as a moderator yet
			if (!array_key_exists($row['id_member'], $local_mods)) {
				$local_mods[$row['id_member']] = $row['id_member'];
			}
		}

		// Get a list of global moderators (i.e. members with moderation powers).
		$global_mods = array_intersect(User::membersAllowedTo('moderate_board', 0), User::membersAllowedTo('approve_posts', 0), User::membersAllowedTo('remove_any', 0), User::membersAllowedTo('modify_any', 0));

		// How about anyone else who is special?
		$allStaff = array_merge(User::membersAllowedTo('admin_forum'), User::membersAllowedTo('manage_membergroups'), User::membersAllowedTo('manage_permissions'), $local_mods, $global_mods);

		// Make sure everyone is there once - no admin less important than any other!
		$allStaff = array_unique($allStaff);

		// This is a bit of a cop out - but we're protecting their forum, really!
		if (count($allStaff) > 300) {
			ErrorHandler::fatalLang('report_error_too_many_staff');
		}

		// Get all the possible membergroups!
		$groups = [0 => Lang::$txt['membergroups_members']];

		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name, online_color
			FROM {db_prefix}membergroups',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
		}
		Db::$db->free_result($request);

		// All the fields we'll show.
		$staffSettings = [
			'position' => Lang::$txt['report_staff_position'],
			'moderates' => Lang::$txt['report_staff_moderates'],
			'posts' => Lang::$txt['report_staff_posts'],
			'last_login' => Lang::$txt['report_staff_last_login'],
		];

		// Do it in columns, it's just easier.
		$this->setKeys('cols');

		// Get each member!
		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name, id_group, posts, last_login
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:staff_list})
			ORDER BY real_name',
			[
				'staff_list' => $allStaff,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Each member gets their own table!.
			$this->newTable($row['real_name'], '', 'left', 'auto', 'left', 200, 'center');

			// First off, add in the side key.
			$this->addData($staffSettings);

			// Create the main data array.
			$staffData = [
				'position' => $groups[$row['id_group']] ?? $groups[0],
				'posts' => $row['posts'],
				'last_login' => Time::create('@' . $row['last_login'])->format(),
				'moderates' => [],
			];

			// What do they moderate?
			if (in_array($row['id_member'], $global_mods)) {
				$staffData['moderates'] = '<em>' . Lang::$txt['report_staff_all_boards'] . '</em>';
			} elseif (isset($moderators[$row['id_member']])) {
				// Get the names
				foreach ($moderators[$row['id_member']] as $board) {
					if (isset($boards[$board])) {
						$staffData['moderates'][] = $boards[$board];
					}
				}

				$staffData['moderates'] = implode(', ', $staffData['moderates']);
			} else {
				$staffData['moderates'] = '<em>' . Lang::$txt['report_staff_no_boards'] . '</em>';
			}

			// Next add the main data.
			$this->addData($staffData);
		}
		Db::$db->free_result($request);
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
	 * Backward compatibility wrapper for the boards sub-action.
	 */
	public static function boardReport(): void
	{
		self::load();
		self::$obj->subaction = 'boards';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the board_perms sub-action.
	 */
	public static function boardPermissionsReport(): void
	{
		self::load();
		self::$obj->subaction = 'board_perms';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the member_groups sub-action.
	 */
	public static function memberGroupsReport(): void
	{
		self::load();
		self::$obj->subaction = 'member_groups';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the group_perms sub-action.
	 */
	public static function groupPermissionsReport(): void
	{
		self::load();
		self::$obj->subaction = 'group_perms';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the staff sub-action.
	 */
	public static function staffReport(): void
	{
		self::load();
		self::$obj->subaction = 'staff';
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
		// Only admins, only EVER admins!
		User::$me->isAllowedTo('admin_forum');

		// Let's get our things running...
		Theme::loadTemplate('Reports');
		Lang::load('Reports');

		Utils::$context['page_title'] = Lang::$txt['generate_reports'];

		// For backward compatibility...
		Utils::$context['report_types'] = &self::$subactions;

		IntegrationHook::call('integrate_report_types', [&self::$subactions]);

		// Load up all the tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['generate_reports'],
			'help' => '',
			'description' => Lang::$txt['generate_reports_desc'],
		];

		$is_first = 0;

		foreach (self::$subactions as $k => $func) {
			if (!is_string($func)) {
				continue;
			}

			$this->report_types[$k] = [
				'id' => $k,
				'title' => Lang::$txt['gr_type_' . $k] ?? $k,
				'description' => Lang::$txt['gr_type_desc_' . $k] ?? null,
				'function' => $func,
				'is_first' => $is_first++ == 0,
			];
		}

		Utils::$context['report_types'] = &$this->report_types;
		Utils::$context['sub_template'] = &$this->sub_template;
		Utils::$context['tables'] = &$this->tables;

		if (!empty($_REQUEST['rt']) && isset(self::$subactions[$_REQUEST['rt']])) {
			$this->subaction = $_REQUEST['rt'];
		}
	}

	/**
	 * This function creates a new table of data, most functions will only use it once.
	 * The core of this file, it creates a new, but empty, table of data in
	 * context, ready for filling using $this->addData().
	 * Fills the context variable current_table with the ID of the table created.
	 * Keeps track of the current table count using context variable table_count.
	 *
	 * @param string $title Title to be displayed with this data table.
	 * @param string $default_value Value to be displayed if a key is missing from a row.
	 * @param string $shading Should the left, top or both (all) parts of the table beshaded?
	 * @param string $width_normal The width of an unshaded column (auto means not defined).
	 * @param string $align_normal The alignment of data in an unshaded column.
	 * @param string $width_shaded The width of a shaded column (auto means not defined).
	 * @param string $align_shaded The alignment of data in a shaded column.
	 */
	protected function newTable($title = '', $default_value = '', $shading = 'all', $width_normal = 'auto', $align_normal = 'center', $width_shaded = 'auto', $align_shaded = 'auto')
	{
		// Set the table count if needed.
		if (empty($this->table_count)) {
			$this->table_count = 0;
		}

		// Create the table!
		$this->tables[$this->table_count] = [
			'title' => $title,
			'default_value' => $default_value,
			'shading' => [
				'left' => $shading == 'all' || $shading == 'left',
				'top' => $shading == 'all' || $shading == 'top',
			],
			'width' => [
				'normal' => $width_normal,
				'shaded' => $width_shaded,
			],
			/* Align usage deprecated due to HTML5 */
			'align' => [
				'normal' => $align_normal,
				'shaded' => $align_shaded,
			],
			'data' => [],
		];

		$this->current_table = $this->table_count;

		// Increment the count...
		$this->table_count++;
	}

	/**
	 * Adds an array of data into an existing table.
	 * if there are no existing tables, will create one with default
	 * attributes.
	 * if custom_table isn't specified, it will use the last table created,
	 * if it is specified and doesn't exist the function will return false.
	 * if a set of keys have been specified, the function will check each
	 * required key is present in the incoming data. If this data is missing
	 * the current tables default value will be used.
	 * if any key in the incoming data begins with '#sep#', the function
	 * will add a separator across the table at this point.
	 * once the incoming data has been sanitized, it is added to the table.
	 *
	 * @param array $inc_data The data to include
	 * @param null|string $custom_table = null The ID of a custom table to put the data in
	 */
	protected function addData($inc_data, $custom_table = null): void
	{
		// No tables? Create one even though we are probably already in a bad state!
		if (empty($this->table_count)) {
			$this->newTable();
		}

		// Specific table?
		if ($custom_table !== null && !isset($this->tables[$custom_table])) {
			return;
		}

		$table = $custom_table ?? $this->current_table;

		// If we have keys, sanitise the data...
		if (!empty($this->keys)) {
			// Basically, check every key exists!
			foreach ($this->keys as $key => $dummy) {
				$data[$key] = [
					'v' => empty($inc_data[$key]) ? $this->tables[$table]['default_value'] : $inc_data[$key],
				];

				// Special "hack" the adding separators when doing data by column.
				if (substr($key, 0, 5) == '#sep#') {
					$data[$key]['separator'] = true;
				}
			}
		} else {
			$data = $inc_data;

			foreach ($data as $key => $value) {
				$data[$key] = [
					'v' => $value,
				];

				if (substr($key, 0, 5) == '#sep#') {
					$data[$key]['separator'] = true;
				}
			}
		}

		// Is it by row?
		if (empty($this->key_method) || $this->key_method == 'rows') {
			// Add the data!
			$this->tables[$table]['data'][] = $data;
		}
		// Otherwise, tricky!
		else {
			foreach ($data as $key => $item) {
				$this->tables[$table]['data'][$key][] = $item;
			}
		}
	}

	/**
	 * Add a separator row, only really used when adding data by rows.
	 *
	 * @param string $title The title of the separator
	 * @param null|string $custom_table The ID of the custom table
	 */
	protected function addSeparator($title = '', $custom_table = null)
	{
		// No tables - return?
		if (empty($this->table_count)) {
			return;
		}

		// Specific table?
		if ($custom_table !== null && !isset($this->tables[$table])) {
			return;
		}

		$table = $custom_table ?? $this->current_table;

		// Plumb in the separator
		$this->tables[$table]['data'][] = [0 => [
			'separator' => true,
			'v' => $title,
		]];
	}

	/**
	 * This does the necessary count of table data before displaying them.
	 * is (unfortunately) required to create some useful variables for templates.
	 * foreach data table created, it will count the number of rows and
	 * columns in the table.
	 * will also create a max_width variable for the table, to give an
	 * estimate width for the whole table * * if it can.
	 */
	protected function finishTables(): void
	{
		if (empty($this->tables)) {
			return;
		}

		// Loop through each table counting up some basic values, to help with the templating.
		foreach ($this->tables as $id => $table) {
			$this->tables[$id]['id'] = $id;
			$this->tables[$id]['row_count'] = count($table['data']);

			$curElement = current($table['data']);

			$this->tables[$id]['column_count'] = count($curElement);

			// Work out the rough width - for templates like the print template. Without this we might get funny tables.
			if ($table['shading']['left'] && $table['width']['shaded'] != 'auto' && $table['width']['normal'] != 'auto') {
				$this->tables[$id]['max_width'] = $table['width']['shaded'] + ($this->tables[$id]['column_count'] - 1) * $table['width']['normal'];
			} elseif ($table['width']['normal'] != 'auto') {
				$this->tables[$id]['max_width'] = $this->tables[$id]['column_count'] * $table['width']['normal'];
			} else {
				$this->tables[$id]['max_width'] = 'auto';
			}
		}
	}

	/**
	 * Set the keys in use by the tables - these ensure entries MUST exist if the data isn't sent.
	 *
	 * sets the current set of "keys" expected in each data array passed to
	 * $this->addData. It also sets the way we are adding data to the data table.
	 * method specifies whether the data passed to $this->addData represents a new
	 * column, or a new row.
	 * keys is an array whose keys are the keys for data being passed to
	 * $this->addData().
	 * if reverse is set to true, then the values of the variable "keys"
	 * are used as opposed to the keys(!
	 *
	 * @param string $method The method. Can be 'rows' or 'columns'
	 * @param array $keys The keys
	 * @param bool $reverse Whether we want to use the values as the keys
	 */
	protected function setKeys($method = 'rows', $keys = [], $reverse = false): void
	{
		// Do we want to use the keys of the keys as the keys? :P
		$this->keys = $reverse ? array_flip($keys) : $keys;

		// Rows or columns?
		$this->key_method = $method == 'rows' ? 'rows' : 'cols';
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Reports::exportStatic')) {
	Reports::exportStatic();
}

?>