<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\Actions\BackwardCompatibility;
use SMF\ActionTrait;
use SMF\Board;
use SMF\Category;
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
	use ActionTrait;

	use BackwardCompatibility;

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
		'board_access' => 'boardAccess',
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
		Group::loadSimple(
			Group::LOAD_NORMAL | (int) !empty(Config::$modSettings['permission_enable_postgroups']),
			[Group::ADMIN, Group::MOD],
		);
		Lang::load('ManagePermissions');
		Permissions::loadPermissionProfiles();

		// Get all the themes...
		Utils::$context['themes'] = [];

		$request = Db::$db->query(
			'',
			'
			SELECT id_theme, value
			FROM {db_prefix}themes
			WHERE variable = {literal:name}
				AND id_theme IN ({array_int:enable_themes})',
			[
				'enable_themes' => explode(',', Config::$modSettings['enableThemes']),
			],
		);

		while ([$id, $name] = Db::$db->fetch_row($request)) {
			Utils::$context['themes'][$id] = $name;
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

		Category::getTree();
		$loaded_ids = array_keys(Board::$loaded);
		Board::getModerators($loaded_ids);
		Board::getModeratorGroups($loaded_ids);

		foreach (Board::$loaded as $board) {
			// Each board has its own table.
			$this->newTable($board->name, '', 'left', 'auto', 'left', '200', 'left');

			$this_boardSettings = $boardSettings;

			if (empty($board->redirect)) {
				unset($this_boardSettings['redirect']);
			}

			// First off, add in the side key.
			$this->addData($this_boardSettings);

			// Create the main data array.
			$boardData = [
				'category' => $board->cat->name,
				'parent' => $board->parent == 0 ? Lang::$txt['none'] : Board::$loaded[$board->parent]->name,
				'redirect' => $board->redirect,
				'num_posts' => $board->posts,
				'num_topics' => $board->topics,
				'count_posts' => empty($board->count_posts) ? Lang::$txt['yes'] : Lang::$txt['no'],
				'theme' => Utils::$context['themes'][$board->theme] ?? Lang::$txt['none'],
				'profile' => Utils::$context['profiles'][$board->profile]['name'],
				'override_theme' => $board->override_theme ? Lang::$txt['yes'] : Lang::$txt['no'],
				'moderators' => implode(', ', array_column($board->moderators, 'name')) ?: Lang::$txt['none'],
				'moderator_groups' => implode(', ', array_column($board->moderator_groups, 'name')) ?: Lang::$txt['none'],
			];

			// Work out the membergroups who can and cannot access it (but only if enabled).
			$allowedGroups = $board->member_groups;

			foreach ($allowedGroups as $key => $group) {
				if (isset(Group::$loaded[$group])) {
					$allowedGroups[$key] = Group::$loaded[$group]->name;
				} else {
					unset($allowedGroups[$key]);
				}
			}

			$boardData['groups'] = implode(', ', $allowedGroups);

			if (!empty(Config::$modSettings['deny_boards_access'])) {
				$disallowedGroups = $board->deny_member_groups;

				foreach ($disallowedGroups as $key => $group) {
					if (isset(Group::$loaded[$group])) {
						$disallowedGroups[$key] = Group::$loaded[$group]->name;
					} else {
						unset($disallowedGroups[$key]);
					}
				}

				$boardData['disallowed_groups'] = implode(', ', $disallowedGroups);
			}

			if (empty($board->redirect)) {
				unset($boardData['redirect']);
			}

			// Next add the main data.
			$this->addData($boardData);
		}
	}

	/**
	 * Standard report about who can access which board.
	 */
	public function boardAccess(): void
	{
		$inc = [];

		if (isset($_REQUEST['groups'])) {
			if (!is_array($_REQUEST['groups'])) {
				$inc = explode(',', $_REQUEST['groups']);
			}

			foreach ($inc as $k => $dummy) {
				$inc[$k] = (int) $dummy;
			}
		}

		$data = [];
		$groups = ['col' => '#sep#'];
		$group_data = Group::loadSimple(
			Group::LOAD_NORMAL | (int) !empty(Config::$modSettings['permission_enable_postgroups']),
			[Group::ADMIN, Group::MOD],
		);
		Board::load([], ['selects' => ['b.id_board', 'b.name', 'member_groups', 'deny_member_groups']]);
		$loaded_ids = array_keys(Board::$loaded);
		Board::getModerators($loaded_ids);
		Board::getModeratorGroups($loaded_ids);

		foreach ($group_data as $group) {
			if ($group->parent === Group::NONE && ($inc == [] || in_array($group->id, $inc))) {
				$groups[$group->id] = $group->name;

				foreach (Board::$loaded as $board) {
					if (!isset($data[$board->id])) {
						$data[$board->id] = ['col' => $board->name];
					} elseif (in_array($group->id, $board->member_groups)) {
						$data[$board->id][$group->id] = '&#x2705;';
					} elseif (in_array($group->id, $board->deny_groups)) {
						$data[$board->id][$group->id] = '&#x1F6AB;';
					}
				}
			}
		}

		$this->setKeys('rows', $groups);
		$this->newTable(Lang::$txt['gr_type_board_access'], '&mdash;', 'all', '100', 'center', '200', 'left');
		$this->addData($groups);
		uasort($data, fn ($a, $b) => $a['col'] <=> $b['col']);

		foreach ($data as $d) {
			$this->addData($d);
		}
	}

	/**
	 * Generate a report on the current permissions by board and membergroup.
	 */
	public function boardPerms(): void
	{
		Lang::load('ManagePermissions');
		Permissions::loadPermissionProfiles();

		$inc = [];

		if (isset($_REQUEST['groups'])) {
			if (!is_array($_REQUEST['groups'])) {
				$inc = explode(',', $_REQUEST['groups']);
			}

			foreach ($inc as $k => $dummy) {
				$inc[$k] = (int) $dummy;
			}
		}

		$groups = ['col' => '#sep#'];
		$group_data = Group::loadSimple(
			Group::LOAD_NORMAL | (int) !empty(Config::$modSettings['permission_enable_postgroups']),
			[Group::ADMIN],
		);
		Group::loadPermissionsBatch(array_map(fn ($group) => $group->id, $group_data), null, true);

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

		$data = [];

		foreach ($group_data as $group) {
			if ($group->parent === Group::NONE && ($inc == [] || in_array($group->id, $inc))) {
				$groups[$group->id] = $group->name;

				foreach ($group->permissions['board_profiles'] as $id_profile => $board_profile) {
					foreach ($board_profile as $permission => $add_deny) {
						if (in_array($permission, $disabled_permissions)) {
							continue;
						}

						if (!isset($data[$id_profile][$permission])) {
							$data[$id_profile][$permission] = ['col' => Lang::$txt['board_perms_name_' . $permission] ?? $permission];
						}

						$data[$id_profile][$permission][$group->id] = $add_deny ? '&#x2705;' : '&#x1F6AB;';
					}
				}
			}
		}

		$this->setKeys('rows', $groups);

		foreach ($data as $id_profile => $board_profile_data) {
			$this->newTable(Utils::$context['profiles'][$id_profile]['name'], '&mdash;', 'all', '100', 'center', '200', 'left');

			$this->addData($groups);
			uasort($board_profile_data, fn ($a, $b) => $a['col'] <=> $b['col']);

			foreach ($board_profile_data as $d) {
				$this->addData($d);
			}
		}
	}

	/**
	 * Show what the membergroups are made of.
	 */
	public function memberGroups(): void
	{
		$mgSettings = [
			'name' => '#sep#',
			'color' => Lang::$txt['member_group_color'],
			'min_posts' => Lang::$txt['member_group_min_posts'],
			'max_messages' => Lang::$txt['member_group_max_messages'],
			'icons' => Lang::$txt['member_group_icons'],
		];

		$this->newTable(Lang::$txt['gr_type_member_groups'], '&mdash;', 'all', 'auto', 'left', 'auto', 'left');
		$this->addData($mgSettings);

		foreach (Group::loadSimple(Group::LOAD_BOTH, []) as $group) {
			$group_info = [
				'name' => $group->name,
				'color' => empty($group->online_color) ? '&mdash;' : '<span style="color: ' . $group->online_color . ';">' . $group->online_color . '</span>',
				'min_posts' => $group->min_posts == -1 ? Lang::$txt['not_applicable'] : (string) $group->min_posts,
				'max_messages' => (string) $group->max_messages,
				'icons' => $group->icons,
			];

			$this->addData($group_info);
		}
	}

	/**
	 * Show the large variety of group permissions assigned to each membergroup.
	 */
	public function groupPerms(): void
	{
		$inc = [];

		if (isset($_REQUEST['groups'])) {
			if (!is_array($_REQUEST['groups'])) {
				$inc = explode(',', $_REQUEST['groups']);
			}

			foreach ($inc as $k => $dummy) {
				$inc[$k] = (int) $dummy;
			}
		}

		$data = [];
		$groups = ['col' => '#sep#'];
		$group_data = Group::loadSimple(
			Group::LOAD_NORMAL | (int) !empty(Config::$modSettings['permission_enable_postgroups']),
			[Group::ADMIN, Group::MOD],
		);
		Group::loadPermissionsBatch(array_map(fn ($group) => $group->id, $group_data), 0);

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

		foreach ($group_data as $group) {
			if ($group->parent === Group::NONE && ($inc == [] || in_array($group->id, $inc))) {
				$groups[$group->id] = $group->name;

				foreach ($group->permissions['general'] as $permission => $add_deny) {
					if (in_array($permission, $disabled_permissions)) {
						continue;
					}

					if (!isset($data[$permission])) {
						if (str_starts_with($permission, 'bbc_')) {
							$data[$permission] = ['col' => Lang::getTxt('group_perms_name_bbc', ['bbc' => substr($permission, 4)])];
						} else {
							$data[$permission] = ['col' => Lang::$txt['group_perms_name_' . $permission] ?? $permission];
						}
					}

					$data[$permission][$group->id] = $add_deny ? '&#x2705;' : '&#x1F6AB;';
				}
			}
		}

		$this->setKeys('rows', $groups);
		$this->newTable(Lang::$txt['gr_type_group_perms'], '&mdash;', 'all', '100', 'center', '200', 'left');
		$this->addData($groups);
		uasort($data, fn ($a, $b) => $a['col'] <=> $b['col']);

		foreach ($data as $d) {
			$this->addData($d);
		}
	}

	/**
	 * Report for showing all the forum staff members - quite a feat!
	 */
	public function staff(): void
	{
		// Get a list of global moderators (i.e. members with moderation powers).
		$global_mods = array_intersect(User::membersAllowedTo('moderate_board', 0), User::membersAllowedTo('approve_posts', 0), User::membersAllowedTo('remove_any', 0), User::membersAllowedTo('modify_any', 0));

		// How about anyone else who is special?
		$allStaff = array_merge(User::membersAllowedTo('admin_forum'), User::membersAllowedTo('manage_membergroups'), User::membersAllowedTo('manage_permissions'), $global_mods);

		// Make sure everyone is there once - no admin less important than any other!
		$allStaff = array_unique($allStaff);

		// This is a bit of a cop out - but we're protecting their forum, really!
		if (count($allStaff) > 300) {
			ErrorHandler::fatalLang('report_error_too_many_staff');
		}

		Group::loadSimple(Group::LOAD_NORMAL, [Group::MOD]);
		Board::load([], ['selects' => ['b.id_board', 'b.name']]);
		$loaded_ids = array_keys(Board::$loaded);
		Board::getModerators($loaded_ids);
		Board::getModeratorGroups($loaded_ids);

		$staffSettings = [
			'name' => '#sep#',
			'position' => Lang::$txt['report_staff_position'],
			'posts' => Lang::$txt['report_staff_posts'],
			'last_login' => Lang::$txt['report_staff_last_login'],
			'moderates' => Lang::$txt['report_staff_moderates'],
		];

		$this->newTable(Lang::$txt['gr_type_staff'], '', 'left', 'auto', 'left', '200', 'left');
		$this->addData($staffSettings);
		User::load($allStaff);

		foreach (User::$loaded as $member) {
			$board_names = [];

			foreach (Board::$loaded as $board) {
				if (isset($board->moderators[$member->id]) && !isset($board_names[$board->id])) {
					$board_names[$board->id] = $board->name;
				}

				if (isset($board->moderator_groups[$member->group_id]) && !isset($board_names[$board->id])) {
					$board_names[$board->id] = $board->name;
				}
			}

			$staffData = [
				'name' => $member->real_name,
				'position' => Group::$loaded[$member->group_id]->name,
				'posts' => $member->posts,
				'last_login' => Time::create('@' . $member->last_login)->format(),
				'moderates' => implode(', ', $board_names) ?: '<i>' . Lang::$txt['report_staff_all_boards'] . '</i>',
			];

			$this->addData($staffData);
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return self An instance of this class.
	 */
	public static function load(): static
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
		Theme::loadJavaScriptFile('reports.js', ['defer' => true, 'minimize' => true], 'smf_reports');

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
	protected function newTable(string $title = '', string $default_value = '', string $shading = 'all', string $width_normal = 'auto', string $align_normal = 'center', string $width_shaded = 'auto', string $align_shaded = 'auto'): void
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
	protected function addData(array $inc_data, ?string $custom_table = null): void
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
					'v' => $inc_data[$key] ?? $this->tables[$table]['default_value'],
				];

				// Special "hack" the adding separators when doing data by column.
				if (str_starts_with((string) $key, '#sep#')) {
					$data[$key]['separator'] = true;
				} elseif (substr((string) $data[$key]['v'], 0, 5) == '#sep#') {
					$data[$key]['header'] = true;
					$data[$key]['v'] = substr((string) $data[$key]['v'], 5);
				}
			}
		} else {
			$data = $inc_data;

			foreach ($data as $key => $value) {
				$data[$key] = [
					'v' => $value,
				];

				if (str_starts_with((string) $key, '#sep#')) {
					$data[$key]['separator'] = true;
				}
				// Hack in a "separator" to display a row differently.
				elseif (str_starts_with((string) $value, '#sep#')) {
					$data[$key]['header'] = true;
					$data[$key]['v'] = substr((string) $value, 5);
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
	protected function addSeparator(string $title = '', ?string $custom_table = null): void
	{
		// No tables - return?
		if (empty($this->table_count)) {
			return;
		}

		// Specific table?
		if ($custom_table !== null && !isset($this->tables[$custom_table])) {
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

			$this->tables[$id]['column_count'] = array_reduce(
				$table['data'],
				fn (int $accumulator, $data): int => max($accumulator, count($data)),
				0,
			);

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
	protected function setKeys(string $method = 'rows', array $keys = [], bool $reverse = false): void
	{
		// Do we want to use the keys of the keys as the keys? :P
		$this->keys = $reverse ? array_flip($keys) : $keys;

		// Rows or columns?
		$this->key_method = $method == 'rows' ? 'rows' : 'cols';
	}
}

?>