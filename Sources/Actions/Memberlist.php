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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class contains the methods for displaying and searching in the
 * members list.
 */
class Memberlist implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Memberlist',
			'MLAll' => 'MLAll',
			'MLSearch' => 'MLSearch',
			'printRows' => 'printMemberListRows',
			'getCustFields' => 'getCustFieldsMList',
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
	public string $subaction = 'all';

	/**
	 * @var array
	 *
	 * Initial definition of the sort_links array.
	 * Labels will be replaced with the indicated $txt strings.
	 * Selected will be changed to match the current sub-action.
	 */
	public array $sort_links = [
		'all' => [
			'label' => 'view_all_members',
			'action' => 'all',
			'selected' => true,
		],
		'search' => [
			'label' => 'mlist_search',
			'action' => 'search',
			'selected' => false,
		],
	];

	/**
	 * @var int
	 *
	 * The chunk size for the cached index.
	 */
	public int $cache_step_size = 500;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'all' => 'all',
		'search' => 'search',
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
	 * Shows a listing of registered members.
	 * - If a subaction is not specified, lists all registered members.
	 * - It allows searching for members with the 'search' sub action.
	 * - It calls all() or search() depending on the sub action.
	 * - Requires the view_mlist permission.
	 * - Accessed via ?action=mlist.
	 *
	 * Uses Memberlist template, main sub template.
	 */
	public function execute(): void
	{
		// Make sure they can view the memberlist.
		User::$me->isAllowedTo('view_mlist');

		Theme::loadTemplate('Memberlist');

		foreach ($this->sort_links as $sa => &$sort_link) {
			$sort_link['label'] = Lang::$txt[$sort_link['label']] ?? ($sort_link['label'] ?? ($sort_link['action'] ?? $sa));

			$sort_link['selected'] = $this->subaction === ($sort_link['action'] ?? $sa);
		}

		Utils::$context['listing_by'] = &$this->subaction;
		Utils::$context['sort_links'] = &$this->sort_links;

		Utils::$context['num_members'] = Config::$modSettings['totalMembers'];

		// Set up the columns...
		Utils::$context['columns'] = [
			'is_online' => [
				'label' => Lang::$txt['status'],
				'sort' => [
					'down' => User::$me->allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) ASC, real_name ASC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END ASC, real_name ASC',
					'up' => User::$me->allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) DESC, real_name DESC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END DESC, real_name DESC',
				],
			],
			'real_name' => [
				'label' => Lang::$txt['name'],
				'class' => 'lefttext',
				'sort' => [
					'down' => 'mem.real_name DESC',
					'up' => 'mem.real_name ASC',
				],
			],
			'website_url' => [
				'label' => Lang::$txt['website'],
				'link_with' => 'website',
				'sort' => [
					'down' => User::$me->is_guest ? '1=1' : 'mem.website_url = \'\', mem.website_url is null, mem.website_url DESC',
					'up' => User::$me->is_guest ? ' 1=1' : 'mem.website_url != \'\', mem.website_url is not null, mem.website_url ASC',
				],
			],
			'id_group' => [
				'label' => Lang::$txt['position'],
				'sort' => [
					'down' => 'mg.group_name is null, mg.group_name DESC',
					'up' => 'mg.group_name is not null, mg.group_name ASC',
				],
			],
			'registered' => [
				'label' => Lang::$txt['date_registered'],
				'sort' => [
					'down' => 'mem.date_registered DESC',
					'up' => 'mem.date_registered ASC',
				],
			],
			'post_count' => [
				'label' => Lang::$txt['posts'],
				'default_sort_rev' => true,
				'sort' => [
					'down' => 'mem.posts DESC',
					'up' => 'mem.posts ASC',
				],
			],
		];

		Utils::$context['custom_profile_fields'] = $this->getCustFields();

		if (!empty(Utils::$context['custom_profile_fields']['columns'])) {
			Utils::$context['columns'] += Utils::$context['custom_profile_fields']['columns'];
		}

		Utils::$context['colspan'] = 0;
		Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : [];

		foreach (Utils::$context['columns'] as $key => $column) {
			if (isset(Utils::$context['disabled_fields'][$key]) || (isset($column['link_with'], Utils::$context['disabled_fields'][$column['link_with']]))) {
				unset(Utils::$context['columns'][$key]);

				continue;
			}

			Utils::$context['colspan'] += $column['colspan'] ?? 1;
		}

		// Aesthetic stuff.
		end(Utils::$context['columns']);

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=mlist',
			'name' => Lang::$txt['members_list'],
		];

		Utils::$context['can_send_pm'] = User::$me->allowedTo('pm_send');
		Utils::$context['can_send_email'] = User::$me->allowedTo('moderate_forum');

		// Build the memberlist button array.
		Utils::$context['memberlist_buttons'] = [
			'view_all_members' => ['text' => 'view_all_members', 'image' => 'mlist.png', 'url' => Config::$scripturl . '?action=mlist' . ';sa=all', 'active' => true],
			'mlist_search' => ['text' => 'mlist_search', 'image' => 'mlist.png', 'url' => Config::$scripturl . '?action=mlist' . ';sa=search'],
		];

		// Allow mods to add additional buttons here
		IntegrationHook::call('integrate_memberlist_buttons');

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * List all members, page by page, with sorting.
	 * Called from MemberList().
	 * Can be passed a sort parameter, to order the display of members.
	 * Calls printRows to retrieve the results of the query.
	 */
	public function all()
	{
		// Only use caching if:
		// 1. there are at least 2k members,
		// 2. the default sorting method (real_name) is being used,
		// 3. the page shown is high enough to make a DB filesort unprofitable.
		$use_cache = Config::$modSettings['totalMembers'] > 2000 && (!isset($_REQUEST['sort']) || $_REQUEST['sort'] === 'real_name') && isset($_REQUEST['start']) && $_REQUEST['start'] > $this->cache_step_size;

		if ($use_cache) {
			// Maybe there's something cached already.
			if (!empty(Config::$modSettings['memberlist_cache'])) {
				$memberlist_cache = Utils::jsonDecode(Config::$modSettings['memberlist_cache'], true);
			}

			// Only update the cache if something changed or no cache existed yet.
			if (empty($memberlist_cache) || empty(Config::$modSettings['memberlist_updated']) || $memberlist_cache['last_update'] < Config::$modSettings['memberlist_updated']) {
				$request = Db::$db->query(
					'',
					'SELECT real_name
					FROM {db_prefix}members
					WHERE is_activated = {int:is_activated}
					ORDER BY real_name',
					[
						'is_activated' => 1,
					],
				);

				$memberlist_cache = [
					'last_update' => time(),
					'num_members' => Db::$db->num_rows($request),
					'index' => [],
				];

				for ($i = 0, $n = Db::$db->num_rows($request); $i < $n; $i += $this->cache_step_size) {
					Db::$db->data_seek($request, $i);
					list($memberlist_cache['index'][$i]) = Db::$db->fetch_row($request);
				}
				Db::$db->data_seek($request, $memberlist_cache['num_members'] - 1);
				list($memberlist_cache['index'][$memberlist_cache['num_members'] - 1]) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// Now we've got the cache...store it.
				Config::updateModSettings(['memberlist_cache' => Utils::jsonEncode($memberlist_cache)]);
			}

			Utils::$context['num_members'] = $memberlist_cache['num_members'];
		}
		// Without cache we need an extra query to get the amount of members.
		else {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}members
				WHERE is_activated = {int:is_activated}',
				[
					'is_activated' => 1,
				],
			);
			list(Utils::$context['num_members']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// Set defaults for sort (real_name) and start. (0)
		if (!isset($_REQUEST['sort']) || !isset(Utils::$context['columns'][$_REQUEST['sort']])) {
			$_REQUEST['sort'] = 'real_name';
		}

		if (!is_numeric($_REQUEST['start'])) {
			if (preg_match('~^[^\'\\\\/]~' . (Utils::$context['utf8'] ? 'u' : ''), Utils::strtolower($_REQUEST['start']), $match) === 0) {
				ErrorHandler::fatal('Are you a wannabe hacker?', false);
			}

			$_REQUEST['start'] = $match[0];

			$request = Db::$db->query(
				'substring',
				'SELECT COUNT(*)
				FROM {db_prefix}members
				WHERE LOWER(SUBSTRING(real_name, 1, 1)) < {string:first_letter}
					AND is_activated = {int:is_activated}',
				[
					'is_activated' => 1,
					'first_letter' => $_REQUEST['start'],
				],
			);
			list($_REQUEST['start']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		Utils::$context['letter_links'] = '';

		for ($i = 97; $i < 123; $i++) {
			Utils::$context['letter_links'] .= '<a href="' . Config::$scripturl . '?action=mlist;sa=all;start=' . chr($i) . '#letter' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';
		}

		// Sort out the column information.
		foreach (Utils::$context['columns'] as $col => $column_details) {
			Utils::$context['columns'][$col]['href'] = Config::$scripturl . '?action=mlist;sort=' . $col . ';start=' . $_REQUEST['start'];

			if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev']))) {
				Utils::$context['columns'][$col]['href'] .= ';desc';
			}

			Utils::$context['columns'][$col]['link'] = '<a href="' . Utils::$context['columns'][$col]['href'] . '" rel="nofollow">' . Utils::$context['columns'][$col]['label'] . '</a>';
			Utils::$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
		}

		// Don't offer website sort to guests
		if (User::$me->is_guest) {
			Utils::$context['columns']['website_url']['href'] = '';
			Utils::$context['columns']['website_url']['link'] = Utils::$context['columns']['website_url']['label'];
		}

		// Are we sorting the results
		Utils::$context['sort_by'] = $_REQUEST['sort'];
		Utils::$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

		// Construct the page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], Utils::$context['num_members'], Config::$modSettings['defaultMaxMembers']);

		// Send the data to the template.
		Utils::$context['start'] = $_REQUEST['start'] + 1;
		Utils::$context['end'] = min($_REQUEST['start'] + Config::$modSettings['defaultMaxMembers'], Utils::$context['num_members']);

		Utils::$context['can_moderate_forum'] = User::$me->allowedTo('moderate_forum');
		Utils::$context['page_title'] = sprintf(Lang::$txt['viewing_members'], Utils::$context['start'], Utils::$context['end']);
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . ';start=' . $_REQUEST['start'],
			'name' => &Utils::$context['page_title'],
			'extra_after' => '(' . sprintf(Lang::$txt['of_total_members'], Utils::$context['num_members']) . ')',
		];

		$limit = $_REQUEST['start'];
		$query_parameters = [
			'regular_id_group' => 0,
			'is_activated' => 1,
			'sort' => Utils::$context['columns'][$_REQUEST['sort']]['sort'][Utils::$context['sort_direction']],
			'blank_string' => '',
		];

		// Using cache allows to narrow down the list to be retrieved.
		if ($use_cache && $_REQUEST['sort'] === 'real_name' && !isset($_REQUEST['desc'])) {
			$first_offset = max(0, $_REQUEST['start'] - ($_REQUEST['start'] % $this->cache_step_size));

			$second_offset = min($memberlist_cache['num_members'] - 1, ceil(($_REQUEST['start'] + Config::$modSettings['defaultMaxMembers']) / $this->cache_step_size) * $this->cache_step_size);

			$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
			$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
			$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
			$limit -= $first_offset;
		}

		// Reverse sorting is a bit more complicated...
		elseif ($use_cache && $_REQUEST['sort'] === 'real_name') {
			$first_offset = max(0, floor(($memberlist_cache['num_members'] - Config::$modSettings['defaultMaxMembers'] - $_REQUEST['start']) / $this->cache_step_size) * $this->cache_step_size);

			$second_offset = min($memberlist_cache['num_members'] - 1, ceil(($memberlist_cache['num_members'] - $_REQUEST['start']) / $this->cache_step_size) * $this->cache_step_size);

			$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
			$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
			$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
			$limit = $second_offset - ($memberlist_cache['num_members'] - $_REQUEST['start']) - ($second_offset > $memberlist_cache['num_members'] ? $this->cache_step_size - ($memberlist_cache['num_members'] % $this->cache_step_size) : 0);
		}

		$custom_fields_qry = '';

		if (!empty(Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']])) {
			$custom_fields_qry = Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']];
		}

		// Select the members from the database.
		$request = Db::$db->query(
			'',
			'SELECT mem.id_member
			FROM {db_prefix}members AS mem' . ($_REQUEST['sort'] === 'is_online' ? '
				LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)' : '') . ($_REQUEST['sort'] === 'id_group' ? '
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' : '') . '
				' . $custom_fields_qry . '
			WHERE mem.is_activated = {int:is_activated}' . (empty($where) ? '' : '
				AND ' . $where) . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			array_merge($query_parameters, [
				'sort' => $query_parameters['sort'],
				'start' => $limit,
				'max' => Config::$modSettings['defaultMaxMembers'],
			]),
		);
		$this->printRows($request);
		Db::$db->free_result($request);

		// Add anchors at the start of each letter.
		if ($_REQUEST['sort'] == 'real_name') {
			$last_letter = '';

			foreach (Utils::$context['members'] as $i => $dummy) {
				$this_letter = Utils::strtolower(Utils::entitySubstr(Utils::$context['members'][$i]['name'], 0, 1));

				if ($this_letter != $last_letter && preg_match('~[a-z]~', $this_letter) === 1) {
					Utils::$context['members'][$i]['sort_letter'] = Utils::htmlspecialchars($this_letter);
					$last_letter = $this_letter;
				}
			}
		}
	}

	/**
	 * Search for members, or display search results.
	 * - Called by MemberList().
	 * - If variable 'search' is empty displays search dialog box, using the search sub template.
	 * - Calls printRows to retrieve the results of the query.
	 */
	public function search()
	{
		Utils::$context['page_title'] = Lang::$txt['mlist_search'];
		Utils::$context['can_moderate_forum'] = User::$me->allowedTo('moderate_forum');

		// Can they search custom fields?
		$request = Db::$db->query(
			'',
			'SELECT col_name, field_name, field_desc
			FROM {db_prefix}custom_fields
			WHERE active = {int:active}
				' . (User::$me->allowedTo('admin_forum') ? '' : ' AND private < {int:private_level}') . '
				AND can_search = {int:can_search}
				AND (field_type = {string:field_type_text} OR field_type = {string:field_type_textarea} OR field_type = {string:field_type_select})',
			[
				'active' => 1,
				'can_search' => 1,
				'private_level' => 2,
				'field_type_text' => 'text',
				'field_type_textarea' => 'textarea',
				'field_type_select' => 'select',
			],
		);
		Utils::$context['custom_search_fields'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['custom_search_fields'][$row['col_name']] = [
				'colname' => $row['col_name'],
				'name' => $row['field_name'],
				'desc' => $row['field_desc'],
			];
		}
		Db::$db->free_result($request);

		// They're searching..
		if (isset($_REQUEST['search'], $_REQUEST['fields'])) {
			$_POST['search'] = trim(isset($_GET['search']) ? html_entity_decode(htmlspecialchars_decode($_GET['search'], ENT_QUOTES), ENT_QUOTES) : $_POST['search']);
			$_POST['fields'] = isset($_GET['fields']) ? explode(',', $_GET['fields']) : $_POST['fields'];

			$_POST['search'] = $_REQUEST['search'] = Utils::htmlspecialchars($_POST['search'], ENT_QUOTES);

			Utils::$context['old_search'] = $_POST['search'];
			Utils::$context['old_search_value'] = urlencode($_POST['search']);

			// No fields?  Use default...
			if (empty($_POST['fields'])) {
				$_POST['fields'] = ['name'];
			}

			$_POST['fields'] = array_intersect($_POST['fields'], array_merge(['name', 'website', 'group', 'email'], array_keys(Utils::$context['custom_search_fields'])));

			// Set defaults for how the results are sorted
			if (!isset($_REQUEST['sort']) || !isset(Utils::$context['columns'][$_REQUEST['sort']])) {
				$_REQUEST['sort'] = 'real_name';
			}

			// Build the column link / sort information.
			foreach (Utils::$context['columns'] as $col => $column_details) {
				Utils::$context['columns'][$col]['href'] = Config::$scripturl . '?action=mlist;sa=search;start=' . (int) $_REQUEST['start'] . ';sort=' . $col;

				if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev']))) {
					Utils::$context['columns'][$col]['href'] .= ';desc';
				}

				if (isset($_POST['search'], $_POST['fields'])) {
					Utils::$context['columns'][$col]['href'] .= ';search=' . urlencode($_POST['search']) . ';fields=' . implode(',', $_POST['fields']);
				}

				Utils::$context['columns'][$col]['link'] = '<a href="' . Utils::$context['columns'][$col]['href'] . '" rel="nofollow">' . Utils::$context['columns'][$col]['label'] . '</a>';
				Utils::$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
			}

			// set up some things for use in the template
			Utils::$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';
			Utils::$context['sort_by'] = $_REQUEST['sort'];

			$query_parameters = [
				'regular_id_group' => 0,
				'is_activated' => 1,
				'blank_string' => '',
				'search' => '%' . strtr($_POST['search'], ['_' => '\\_', '%' => '\\%', '*' => '%']) . '%',
				'sort' => Utils::$context['columns'][$_REQUEST['sort']]['sort'][Utils::$context['sort_direction']],
			];

			// Search for a name
			if (in_array('name', $_POST['fields'])) {
				$fields = User::$me->allowedTo('moderate_forum') ? ['member_name', 'real_name'] : ['real_name'];
				$search_fields[] = 'name';
			} else {
				$fields = [];
				$search_fields = [];
			}

			// Search for websites.
			if (in_array('website', $_POST['fields'])) {
				$fields += [7 => 'website_title', 'website_url'];
				$search_fields[] = 'website';
			}

			// Search for groups.
			if (in_array('group', $_POST['fields'])) {
				$fields += [9 => 'COALESCE(group_name, {string:blank_string})'];
				$search_fields[] = 'group';
			}

			// Search for an email address?
			if (in_array('email', $_POST['fields']) && User::$me->allowedTo('moderate_forum')) {
				$fields += [2 => 'email_address'];
				$search_fields[] = 'email';
			}

			if (Db::$db->case_sensitive) {
				foreach ($fields as $key => $field) {
					$fields[$key] = 'LOWER(' . $field . ')';
				}
			}

			$customJoin = [];
			$customCount = 10;

			// Any custom fields to search for - these being tricky?
			foreach ($_POST['fields'] as $field) {
				if (substr($field, 0, 5) == 'cust_' && isset(Utils::$context['custom_search_fields'][$field])) {
					$customJoin[] = 'LEFT JOIN {db_prefix}themes AS t' . $field . ' ON (t' . $field . '.variable = {string:t' . $field . '} AND t' . $field . '.id_theme = 1 AND t' . $field . '.id_member = mem.id_member)';
					$query_parameters['t' . $field] = $field;
					$fields += [$customCount++ => 'COALESCE(t' . $field . '.value, {string:blank_string})'];
					$search_fields[] = $field;
				}
			}

			// No search fields? That means you're trying to hack things
			if (empty($search_fields)) {
				ErrorHandler::fatalLang('invalid_search_string', false);
			}

			$query = $_POST['search'] == '' ? '= {string:blank_string}' : (Db::$db->case_sensitive ? 'LIKE LOWER({string:search})' : 'LIKE {string:search}');

			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)
					' . (empty($customJoin) ? '' : implode('
					', $customJoin)) . '
				WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . ')
					AND mem.is_activated = {int:is_activated}',
				$query_parameters,
			);
			list($numResults) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=mlist;sa=search;search=' . urlencode($_POST['search']) . ';fields=' . implode(',', $_POST['fields']), $_REQUEST['start'], $numResults, Config::$modSettings['defaultMaxMembers']);

			$custom_fields_qry = '';

			if (array_search($_REQUEST['sort'], $_POST['fields']) === false && !empty(Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']])) {
				$custom_fields_qry = Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']];
			}

			// Find the members from the database.
			$request = Db::$db->query(
				'',
				'SELECT mem.id_member
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
					$custom_fields_qry .
					(empty($customJoin) ? '' : implode('
					', $customJoin)) . '
				WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . ')
					AND mem.is_activated = {int:is_activated}
				ORDER BY {raw:sort}
				LIMIT {int:start}, {int:max}',
				array_merge($query_parameters, [
					'start' => $_REQUEST['start'],
					'max' => Config::$modSettings['defaultMaxMembers'],
				]),
			);
			$this->printRows($request);
			Db::$db->free_result($request);
		} else {
			// These are all the possible fields.
			Utils::$context['search_fields'] = [
				'name' => Lang::$txt['mlist_search_name'],
				'email' => Lang::$txt['mlist_search_email'],
				'website' => Lang::$txt['mlist_search_website'],
				'group' => Lang::$txt['mlist_search_group'],
			];

			// Sorry, but you can't search by email unless you can view emails
			if (!User::$me->allowedTo('moderate_forum')) {
				unset(Utils::$context['search_fields']['email']);
				Utils::$context['search_defaults'] = ['name'];
			} else {
				Utils::$context['search_defaults'] = ['name', 'email'];
			}

			foreach (Utils::$context['custom_search_fields'] as $field) {
				Utils::$context['search_fields'][$field['colname']] = sprintf(Lang::$txt['mlist_search_by'], Lang::tokenTxtReplace($field['name']));
			}

			Utils::$context['sub_template'] = 'search';
			Utils::$context['old_search'] = $_GET['search'] ?? (isset($_POST['search']) ? Utils::htmlspecialchars($_POST['search']) : '');

			// Since we're nice we also want to default focus on to the search field.
			Theme::addInlineJavaScript("\n\t" . '$(\'input[name="search"]\').focus();', true);
		}

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=mlist;sa=search',
			'name' => &Utils::$context['page_title'],
		];

		// Highlight the correct button, too!
		unset(Utils::$context['memberlist_buttons']['view_all_members']['active']);
		Utils::$context['memberlist_buttons']['mlist_search']['active'] = true;
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
	 * Backward compatibility wrapper for the all sub-action.
	 */
	public static function MLAll(): void
	{
		self::load();
		self::$obj->subaction = 'all';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the search sub-action.
	 */
	public static function MLSearch(): void
	{
		self::load();
		self::$obj->subaction = 'search';
		self::$obj->execute();
	}

	/**
	 * Retrieves results of the request passed to it
	 * Puts results of request into the context for the sub template.
	 *
	 * @param resource $request An SQL result resource
	 */
	public static function printRows($request)
	{
		// Get the most posts.
		$result = Db::$db->query(
			'',
			'SELECT MAX(posts)
			FROM {db_prefix}members',
			[
			],
		);
		list($most_posts) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// Avoid division by zero...
		if ($most_posts == 0) {
			$most_posts = 1;
		}

		$members = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
		}

		// Load all the members for display.
		User::load($members);

		Utils::$context['members'] = [];

		foreach ($members as $member) {
			if (!isset(User::$loaded[$member])) {
				continue;
			}

			Utils::$context['members'][$member] = User::$loaded[$member]->format();
			Utils::$context['members'][$member]['post_percent'] = round((Utils::$context['members'][$member]['real_posts'] * 100) / $most_posts);
			Utils::$context['members'][$member]['registered_date'] = Time::strftime('%Y-%m-%d', Utils::$context['members'][$member]['registered_timestamp']);

			if (!empty(Utils::$context['custom_profile_fields']['columns'])) {
				foreach (Utils::$context['custom_profile_fields']['columns'] as $key => $column) {
					// Don't show anything if there isn't anything to show.
					if (!isset(Utils::$context['members'][$member]['options'][$key])) {
						Utils::$context['members'][$member]['options'][$key] = $column['default_value'] ?? '';

						continue;
					}

					Utils::$context['members'][$member]['options'][$key] = Lang::tokenTxtReplace(Utils::$context['members'][$member]['options'][$key]);
					$currentKey = 0;

					if (!empty($column['options'])) {
						$fieldOptions = explode(',', $column['options']);

						foreach ($fieldOptions as $k => $v) {
							if (empty($currentKey)) {
								$currentKey = $v === Utils::$context['members'][$member]['options'][$key] ? $k : 0;
							}
						}
					}

					if ($column['bbc'] && !empty(Utils::$context['members'][$member]['options'][$key])) {
						Utils::$context['members'][$member]['options'][$key] = strip_tags(BBCodeParser::load()->parse(Utils::$context['members'][$member]['options'][$key]));
					} elseif ($column['type'] == 'check') {
						Utils::$context['members'][$member]['options'][$key] = Utils::$context['members'][$member]['options'][$key] == 0 ? Lang::$txt['no'] : Lang::$txt['yes'];
					}

					// Enclosing the user input within some other text?
					if (!empty($column['enclose'])) {
						Utils::$context['members'][$member]['options'][$key] = strtr($column['enclose'], [
							'{SCRIPTURL}' => Config::$scripturl,
							'{IMAGES_URL}' => Theme::$current->settings['images_url'],
							'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
							'{INPUT}' => Lang::tokenTxtReplace(Utils::$context['members'][$member]['options'][$key]),
							'{KEY}' => $currentKey,
						]);
					}
				}
			}
		}
	}

	/**
	 * Sets the label, sort and join info for every custom field column.
	 *
	 * @return array An array of info about the custom fields for the member list
	 */
	public static function getCustFields()
	{
		$cpf = [];

		$request = Db::$db->query(
			'',
			'SELECT col_name, field_name, field_desc, field_type, field_options, bbc, enclose, default_value
			FROM {db_prefix}custom_fields
			WHERE active = {int:active}
				AND show_mlist = {int:show}
				AND private < {int:private_level}',
			[
				'active' => 1,
				'show' => 1,
				'private_level' => 2,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Get all the data we're gonna need.
			$cpf['columns'][$row['col_name']] = [
				'label' => Lang::tokenTxtReplace($row['field_name']),
				'type' => $row['field_type'],
				'options' => Lang::tokenTxtReplace($row['field_options']),
				'bbc' => !empty($row['bbc']),
				'enclose' => $row['enclose'],
				'default_value' => Lang::tokenTxtReplace($row['default_value']),
			];

			// Get the right sort method depending on the cust field type.
			if ($row['field_type'] != 'check') {
				$cpf['columns'][$row['col_name']]['sort'] = [
					'down' => 'LENGTH(t' . $row['col_name'] . '.value) > 0 ASC, COALESCE(t' . $row['col_name'] . '.value, \'\') DESC',
					'up' => 'LENGTH(t' . $row['col_name'] . '.value) > 0 DESC, COALESCE(t' . $row['col_name'] . '.value, \'\') ASC',
				];
			} else {
				$cpf['columns'][$row['col_name']]['sort'] = [
					'down' => 't' . $row['col_name'] . '.value DESC',
					'up' => 't' . $row['col_name'] . '.value ASC',
				];
			}

			$cpf['join'][$row['col_name']] = 'LEFT JOIN {db_prefix}themes AS t' . $row['col_name'] . ' ON (t' . $row['col_name'] . '.variable = {literal:' . $row['col_name'] . '} AND t' . $row['col_name'] . '.id_theme = 1 AND t' . $row['col_name'] . '.id_member = mem.id_member)';
		}
		Db::$db->free_result($request);

		return $cpf;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Allow mods to add sub-actions and sort_links.
		IntegrationHook::call('integrate_memberlist_subactions', [&self::$subactions, $this->sort_links]);

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Memberlist::exportStatic')) {
	Memberlist::exportStatic();
}

?>