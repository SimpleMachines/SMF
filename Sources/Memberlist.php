<?php

/**
 * This file contains the functions for displaying and searching in the
 * members list.
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
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Shows a listing of registered members.
 * - If a subaction is not specified, lists all registered members.
 * - It allows searching for members with the 'search' sub action.
 * - It calls MLAll or MLSearch depending on the sub action.
 * - Requires the view_mlist permission.
 * - Accessed via ?action=mlist.
 *
 * Uses Memberlist template, main sub template.
 */
function Memberlist()
{
	// Make sure they can view the memberlist.
	isAllowedTo('view_mlist');

	Theme::loadTemplate('Memberlist');

	Utils::$context['listing_by'] = !empty($_GET['sa']) ? $_GET['sa'] : 'all';

	// $subActions array format:
	// 'subaction' => array('label', 'function', 'is_selected')
	$subActions = array(
		'all' => array(Lang::$txt['view_all_members'], 'MLAll', Utils::$context['listing_by'] == 'all'),
		'search' => array(Lang::$txt['mlist_search'], 'MLSearch', Utils::$context['listing_by'] == 'search'),
	);

	// Set up the sort links.
	Utils::$context['sort_links'] = array();
	foreach ($subActions as $act => $text)
	{
		Utils::$context['sort_links'][] = array(
			'label' => $text[0],
			'action' => $act,
			'selected' => $text[2],
		);
	}

	Utils::$context['num_members'] = Config::$modSettings['totalMembers'];

	// Set up the columns...
	Utils::$context['columns'] = array(
		'is_online' => array(
			'label' => Lang::$txt['status'],
			'sort' => array(
				'down' => allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) ASC, real_name ASC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END ASC, real_name ASC',
				'up' => allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) DESC, real_name DESC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END DESC, real_name DESC'
			),
		),
		'real_name' => array(
			'label' => Lang::$txt['name'],
			'class' => 'lefttext',
			'sort' => array(
				'down' => 'mem.real_name DESC',
				'up' => 'mem.real_name ASC'
			),
		),
		'website_url' => array(
			'label' => Lang::$txt['website'],
			'link_with' => 'website',
			'sort' => array(
				'down' => User::$me->is_guest ? '1=1' : 'mem.website_url = \'\', mem.website_url is null, mem.website_url DESC',
				'up' => User::$me->is_guest ? ' 1=1' : 'mem.website_url != \'\', mem.website_url is not null, mem.website_url ASC'
			),
		),
		'id_group' => array(
			'label' => Lang::$txt['position'],
			'sort' => array(
				'down' => 'mg.group_name is null, mg.group_name DESC',
				'up' => 'mg.group_name is not null, mg.group_name ASC'
			),
		),
		'registered' => array(
			'label' => Lang::$txt['date_registered'],
			'sort' => array(
				'down' => 'mem.date_registered DESC',
				'up' => 'mem.date_registered ASC'
			),
		),
		'post_count' => array(
			'label' => Lang::$txt['posts'],
			'default_sort_rev' => true,
			'sort' => array(
				'down' => 'mem.posts DESC',
				'up' => 'mem.posts ASC'
			),
		)
	);

	Utils::$context['custom_profile_fields'] = getCustFieldsMList();

	if (!empty(Utils::$context['custom_profile_fields']['columns']))
		Utils::$context['columns'] += Utils::$context['custom_profile_fields']['columns'];

	Utils::$context['colspan'] = 0;
	Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : array();
	foreach (Utils::$context['columns'] as $key => $column)
	{
		if (isset(Utils::$context['disabled_fields'][$key]) || (isset($column['link_with']) && isset(Utils::$context['disabled_fields'][$column['link_with']])))
		{
			unset(Utils::$context['columns'][$key]);
			continue;
		}

		Utils::$context['colspan'] += isset($column['colspan']) ? $column['colspan'] : 1;
	}

	// Aesthetic stuff.
	end(Utils::$context['columns']);

	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=mlist',
		'name' => Lang::$txt['members_list']
	);

	Utils::$context['can_send_pm'] = allowedTo('pm_send');
	Utils::$context['can_send_email'] = allowedTo('moderate_forum');

	// Build the memberlist button array.
	Utils::$context['memberlist_buttons'] = array(
		'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.png', 'url' => Config::$scripturl . '?action=mlist' . ';sa=all', 'active' => true),
		'mlist_search' => array('text' => 'mlist_search', 'image' => 'mlist.png', 'url' => Config::$scripturl . '?action=mlist' . ';sa=search'),
	);

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_memberlist_buttons');

	// Jump to the sub action.
	if (isset($subActions[Utils::$context['listing_by']]))
		call_helper($subActions[Utils::$context['listing_by']][1]);

	else
		call_helper($subActions['all'][1]);
}

/**
 * List all members, page by page, with sorting.
 * Called from MemberList().
 * Can be passed a sort parameter, to order the display of members.
 * Calls printMemberListRows to retrieve the results of the query.
 */
function MLAll()
{
	// The chunk size for the cached index.
	$cache_step_size = 500;

	// Only use caching if:
	// 1. there are at least 2k members,
	// 2. the default sorting method (real_name) is being used,
	// 3. the page shown is high enough to make a DB filesort unprofitable.
	$use_cache = Config::$modSettings['totalMembers'] > 2000 && (!isset($_REQUEST['sort']) || $_REQUEST['sort'] === 'real_name') && isset($_REQUEST['start']) && $_REQUEST['start'] > $cache_step_size;

	if ($use_cache)
	{
		// Maybe there's something cached already.
		if (!empty(Config::$modSettings['memberlist_cache']))
			$memberlist_cache = Utils::jsonDecode(Config::$modSettings['memberlist_cache'], true);

		// The chunk size for the cached index.
		$cache_step_size = 500;

		// Only update the cache if something changed or no cache existed yet.
		if (empty($memberlist_cache) || empty(Config::$modSettings['memberlist_updated']) || $memberlist_cache['last_update'] < Config::$modSettings['memberlist_updated'])
		{
			$request = Db::$db->query('', '
				SELECT real_name
				FROM {db_prefix}members
				WHERE is_activated = {int:is_activated}
				ORDER BY real_name',
				array(
					'is_activated' => 1,
				)
			);

			$memberlist_cache = array(
				'last_update' => time(),
				'num_members' => Db::$db->num_rows($request),
				'index' => array(),
			);

			for ($i = 0, $n = Db::$db->num_rows($request); $i < $n; $i += $cache_step_size)
			{
				Db::$db->data_seek($request, $i);
				list($memberlist_cache['index'][$i]) = Db::$db->fetch_row($request);
			}
			Db::$db->data_seek($request, $memberlist_cache['num_members'] - 1);
			list ($memberlist_cache['index'][$memberlist_cache['num_members'] - 1]) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Now we've got the cache...store it.
			Config::updateModSettings(array('memberlist_cache' => Utils::jsonEncode($memberlist_cache)));
		}

		Utils::$context['num_members'] = $memberlist_cache['num_members'];
	}

	// Without cache we need an extra query to get the amount of members.
	else
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
			)
		);
		list (Utils::$context['num_members']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
	}

	// Set defaults for sort (real_name) and start. (0)
	if (!isset($_REQUEST['sort']) || !isset(Utils::$context['columns'][$_REQUEST['sort']]))
		$_REQUEST['sort'] = 'real_name';

	if (!is_numeric($_REQUEST['start']))
	{
		if (preg_match('~^[^\'\\\\/]~' . (Utils::$context['utf8'] ? 'u' : ''), Utils::strtolower($_REQUEST['start']), $match) === 0)
			fatal_error('Hacker?', false);

		$_REQUEST['start'] = $match[0];

		$request = Db::$db->query('substring', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE LOWER(SUBSTRING(real_name, 1, 1)) < {string:first_letter}
				AND is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
				'first_letter' => $_REQUEST['start'],
			)
		);
		list ($_REQUEST['start']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
	}

	Utils::$context['letter_links'] = '';
	for ($i = 97; $i < 123; $i++)
		Utils::$context['letter_links'] .= '<a href="' . Config::$scripturl . '?action=mlist;sa=all;start=' . chr($i) . '#letter' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';

	// Sort out the column information.
	foreach (Utils::$context['columns'] as $col => $column_details)
	{
		Utils::$context['columns'][$col]['href'] = Config::$scripturl . '?action=mlist;sort=' . $col . ';start=' . $_REQUEST['start'];

		if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
			Utils::$context['columns'][$col]['href'] .= ';desc';

		Utils::$context['columns'][$col]['link'] = '<a href="' . Utils::$context['columns'][$col]['href'] . '" rel="nofollow">' . Utils::$context['columns'][$col]['label'] . '</a>';
		Utils::$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
	}

	// Don't offer website sort to guests
	if (User::$me->is_guest)
	{
		Utils::$context['columns']['website_url']['href'] = '';
		Utils::$context['columns']['website_url']['link'] = Utils::$context['columns']['website_url']['label'];
	}

	// Are we sorting the results
	Utils::$context['sort_by'] = $_REQUEST['sort'];
	Utils::$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

	// Construct the page index.
	Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], Utils::$context['num_members'], Config::$modSettings['defaultMaxMembers']);

	// Send the data to the template.
	Utils::$context['start'] = $_REQUEST['start'] + 1;
	Utils::$context['end'] = min($_REQUEST['start'] + Config::$modSettings['defaultMaxMembers'], Utils::$context['num_members']);

	Utils::$context['can_moderate_forum'] = allowedTo('moderate_forum');
	Utils::$context['page_title'] = sprintf(Lang::$txt['viewing_members'], Utils::$context['start'], Utils::$context['end']);
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . ';start=' . $_REQUEST['start'],
		'name' => &Utils::$context['page_title'],
		'extra_after' => '(' . sprintf(Lang::$txt['of_total_members'], Utils::$context['num_members']) . ')'
	);

	$limit = $_REQUEST['start'];
	$query_parameters = array(
		'regular_id_group' => 0,
		'is_activated' => 1,
		'sort' => Utils::$context['columns'][$_REQUEST['sort']]['sort'][Utils::$context['sort_direction']],
		'blank_string' => '',
	);

	// Using cache allows to narrow down the list to be retrieved.
	if ($use_cache && $_REQUEST['sort'] === 'real_name' && !isset($_REQUEST['desc']))
	{
		$first_offset = $_REQUEST['start'] - ($_REQUEST['start'] % $cache_step_size);
		if ($first_offset < 0)
			$first_offset = 0;
		$second_offset = ceil(($_REQUEST['start'] + Config::$modSettings['defaultMaxMembers']) / $cache_step_size) * $cache_step_size;
		if ($second_offset >= $memberlist_cache['num_members'])
			$second_offset = $memberlist_cache['num_members'] - 1;

		$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
		$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
		$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
		$limit -= $first_offset;
	}

	// Reverse sorting is a bit more complicated...
	elseif ($use_cache && $_REQUEST['sort'] === 'real_name')
	{
		$first_offset = floor(($memberlist_cache['num_members'] - Config::$modSettings['defaultMaxMembers'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;
		if ($first_offset < 0)
			$first_offset = 0;
		$second_offset = ceil(($memberlist_cache['num_members'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;
		if ($second_offset >= $memberlist_cache['num_members'])
			$second_offset = $memberlist_cache['num_members'] - 1;

		$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
		$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
		$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
		$limit = $second_offset - ($memberlist_cache['num_members'] - $_REQUEST['start']) - ($second_offset > $memberlist_cache['num_members'] ? $cache_step_size - ($memberlist_cache['num_members'] % $cache_step_size) : 0);
	}

	$custom_fields_qry = '';
	if (!empty(Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']]))
		$custom_fields_qry = Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']];

	// Select the members from the database.
	$request = Db::$db->query('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem' . ($_REQUEST['sort'] === 'is_online' ? '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)' : '') . ($_REQUEST['sort'] === 'id_group' ? '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' : '') . '
			' . $custom_fields_qry . '
		WHERE mem.is_activated = {int:is_activated}' . (empty($where) ? '' : '
			AND ' . $where) . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array_merge($query_parameters, array(
			'sort' => $query_parameters['sort'],
			'start' => $limit,
			'max' => Config::$modSettings['defaultMaxMembers'],
		))
	);
	printMemberListRows($request);
	Db::$db->free_result($request);

	// Add anchors at the start of each letter.
	if ($_REQUEST['sort'] == 'real_name')
	{
		$last_letter = '';
		foreach (Utils::$context['members'] as $i => $dummy)
		{
			$this_letter = Utils::strtolower(Utils::entitySubstr(Utils::$context['members'][$i]['name'], 0, 1));

			if ($this_letter != $last_letter && preg_match('~[a-z]~', $this_letter) === 1)
			{
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
 * - Calls printMemberListRows to retrieve the results of the query.
 */
function MLSearch()
{
	Utils::$context['page_title'] = Lang::$txt['mlist_search'];
	Utils::$context['can_moderate_forum'] = allowedTo('moderate_forum');

	// Can they search custom fields?
	$request = Db::$db->query('', '
		SELECT col_name, field_name, field_desc
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			' . (allowedTo('admin_forum') ? '' : ' AND private < {int:private_level}') . '
			AND can_search = {int:can_search}
			AND (field_type = {string:field_type_text} OR field_type = {string:field_type_textarea} OR field_type = {string:field_type_select})',
		array(
			'active' => 1,
			'can_search' => 1,
			'private_level' => 2,
			'field_type_text' => 'text',
			'field_type_textarea' => 'textarea',
			'field_type_select' => 'select',
		)
	);
	Utils::$context['custom_search_fields'] = array();
	while ($row = Db::$db->fetch_assoc($request))
		Utils::$context['custom_search_fields'][$row['col_name']] = array(
			'colname' => $row['col_name'],
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
		);
	Db::$db->free_result($request);

	// They're searching..
	if (isset($_REQUEST['search']) && isset($_REQUEST['fields']))
	{
		$_POST['search'] = trim(isset($_GET['search']) ? html_entity_decode(htmlspecialchars_decode($_GET['search'], ENT_QUOTES), ENT_QUOTES) : $_POST['search']);
		$_POST['fields'] = isset($_GET['fields']) ? explode(',', $_GET['fields']) : $_POST['fields'];

		$_POST['search'] = $_REQUEST['search'] = Utils::htmlspecialchars($_POST['search'], ENT_QUOTES);

		Utils::$context['old_search'] = $_POST['search'];
		Utils::$context['old_search_value'] = urlencode($_POST['search']);

		// No fields?  Use default...
		if (empty($_POST['fields']))
			$_POST['fields'] = array('name');

		$_POST['fields'] = array_intersect($_POST['fields'], array_merge(array('name', 'website', 'group', 'email'), array_keys(Utils::$context['custom_search_fields'])));

		// Set defaults for how the results are sorted
		if (!isset($_REQUEST['sort']) || !isset(Utils::$context['columns'][$_REQUEST['sort']]))
			$_REQUEST['sort'] = 'real_name';

		// Build the column link / sort information.
		foreach (Utils::$context['columns'] as $col => $column_details)
		{
			Utils::$context['columns'][$col]['href'] = Config::$scripturl . '?action=mlist;sa=search;start=' . (int) $_REQUEST['start'] . ';sort=' . $col;

			if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
				Utils::$context['columns'][$col]['href'] .= ';desc';

			if (isset($_POST['search']) && isset($_POST['fields']))
				Utils::$context['columns'][$col]['href'] .= ';search=' . urlencode($_POST['search']) . ';fields=' . implode(',', $_POST['fields']);

			Utils::$context['columns'][$col]['link'] = '<a href="' . Utils::$context['columns'][$col]['href'] . '" rel="nofollow">' . Utils::$context['columns'][$col]['label'] . '</a>';
			Utils::$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
		}

		// set up some things for use in the template
		Utils::$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';
		Utils::$context['sort_by'] = $_REQUEST['sort'];

		$query_parameters = array(
			'regular_id_group' => 0,
			'is_activated' => 1,
			'blank_string' => '',
			'search' => '%' . strtr($_POST['search'], array('_' => '\\_', '%' => '\\%', '*' => '%')) . '%',
			'sort' => Utils::$context['columns'][$_REQUEST['sort']]['sort'][Utils::$context['sort_direction']],
		);

		// Search for a name
		if (in_array('name', $_POST['fields']))
		{
			$fields = allowedTo('moderate_forum') ? array('member_name', 'real_name') : array('real_name');
			$search_fields[] = 'name';
		}
		else
		{
			$fields = array();
			$search_fields = array();
		}

		// Search for websites.
		if (in_array('website', $_POST['fields']))
		{
			$fields += array(7 => 'website_title', 'website_url');
			$search_fields[] = 'website';
		}
		// Search for groups.
		if (in_array('group', $_POST['fields']))
		{
			$fields += array(9 => 'COALESCE(group_name, {string:blank_string})');
			$search_fields[] = 'group';
		}
		// Search for an email address?
		if (in_array('email', $_POST['fields']) && allowedTo('moderate_forum'))
		{
			$fields += array(2 => 'email_address');
			$search_fields[] = 'email';
		}

		if (Db::$db->case_sensitive)
			foreach ($fields as $key => $field)
				$fields[$key] = 'LOWER(' . $field . ')';

		$customJoin = array();
		$customCount = 10;

		// Any custom fields to search for - these being tricky?
		foreach ($_POST['fields'] as $field)
		{
			if (substr($field, 0, 5) == 'cust_' && isset(Utils::$context['custom_search_fields'][$field]))
			{
				$customJoin[] = 'LEFT JOIN {db_prefix}themes AS t' . $field . ' ON (t' . $field . '.variable = {string:t' . $field . '} AND t' . $field . '.id_theme = 1 AND t' . $field . '.id_member = mem.id_member)';
				$query_parameters['t' . $field] = $field;
				$fields += array($customCount++ => 'COALESCE(t' . $field . '.value, {string:blank_string})');
				$search_fields[] = $field;
			}
		}

		// No search fields? That means you're trying to hack things
		if (empty($search_fields))
			fatal_lang_error('invalid_search_string', false);

		$query = $_POST['search'] == '' ? '= {string:blank_string}' : (Db::$db->case_sensitive ? 'LIKE LOWER({string:search})' : 'LIKE {string:search}');

		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)
				' . (empty($customJoin) ? '' : implode('
				', $customJoin)) . '
			WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . ')
				AND mem.is_activated = {int:is_activated}',
			$query_parameters
		);
		list ($numResults) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=mlist;sa=search;search=' . urlencode($_POST['search']) . ';fields=' . implode(',', $_POST['fields']), $_REQUEST['start'], $numResults, Config::$modSettings['defaultMaxMembers']);

		$custom_fields_qry = '';
		if (array_search($_REQUEST['sort'], $_POST['fields']) === false && !empty(Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']]))
			$custom_fields_qry = Utils::$context['custom_profile_fields']['join'][$_REQUEST['sort']];

		// Find the members from the database.
		$request = Db::$db->query('', '
			SELECT mem.id_member
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
			array_merge($query_parameters, array(
				'start' => $_REQUEST['start'],
				'max' => Config::$modSettings['defaultMaxMembers'],
			))
		);
		printMemberListRows($request);
		Db::$db->free_result($request);
	}
	else
	{
		// These are all the possible fields.
		Utils::$context['search_fields'] = array(
			'name' => Lang::$txt['mlist_search_name'],
			'email' => Lang::$txt['mlist_search_email'],
			'website' => Lang::$txt['mlist_search_website'],
			'group' => Lang::$txt['mlist_search_group'],
		);

		// Sorry, but you can't search by email unless you can view emails
		if (!allowedTo('moderate_forum'))
		{
			unset(Utils::$context['search_fields']['email']);
			Utils::$context['search_defaults'] = array('name');
		}
		else
		{
			Utils::$context['search_defaults'] = array('name', 'email');
		}

		foreach (Utils::$context['custom_search_fields'] as $field)
			Utils::$context['search_fields'][$field['colname']] = sprintf(Lang::$txt['mlist_search_by'], Lang::tokenTxtReplace($field['name']));

		Utils::$context['sub_template'] = 'search';
		Utils::$context['old_search'] = isset($_GET['search']) ? $_GET['search'] : (isset($_POST['search']) ? Utils::htmlspecialchars($_POST['search']) : '');

		// Since we're nice we also want to default focus on to the search field.
		Theme::addInlineJavaScript('
	$(\'input[name="search"]\').focus();', true);
	}

	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=mlist;sa=search',
		'name' => &Utils::$context['page_title']
	);

	// Highlight the correct button, too!
	unset(Utils::$context['memberlist_buttons']['view_all_members']['active']);
	Utils::$context['memberlist_buttons']['mlist_search']['active'] = true;
}

/**
 * Retrieves results of the request passed to it
 * Puts results of request into the context for the sub template.
 *
 * @param resource $request An SQL result resource
 */
function printMemberListRows($request)
{
	// Get the most posts.
	$result = Db::$db->query('', '
		SELECT MAX(posts)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($most_posts) = Db::$db->fetch_row($result);
	Db::$db->free_result($result);

	// Avoid division by zero...
	if ($most_posts == 0)
		$most_posts = 1;

	$members = array();
	while ($row = Db::$db->fetch_assoc($request))
		$members[] = $row['id_member'];

	// Load all the members for display.
	User::load($members);

	Utils::$context['members'] = array();
	foreach ($members as $member)
	{
		if (!isset(User::$loaded[$member]))
			continue;

		Utils::$context['members'][$member] = User::$loaded[$member]->format();
		Utils::$context['members'][$member]['post_percent'] = round((Utils::$context['members'][$member]['real_posts'] * 100) / $most_posts);
		Utils::$context['members'][$member]['registered_date'] = smf_strftime('%Y-%m-%d', Utils::$context['members'][$member]['registered_timestamp']);

		if (!empty(Utils::$context['custom_profile_fields']['columns']))
		{
			foreach (Utils::$context['custom_profile_fields']['columns'] as $key => $column)
			{
				// Don't show anything if there isn't anything to show.
				if (!isset(Utils::$context['members'][$member]['options'][$key]))
				{
					Utils::$context['members'][$member]['options'][$key] = isset($column['default_value']) ? $column['default_value'] : '';
					continue;
				}

				Utils::$context['members'][$member]['options'][$key] = Lang::tokenTxtReplace(Utils::$context['members'][$member]['options'][$key]);
				$currentKey = 0;
				if (!empty($column['options']))
				{
					$fieldOptions = explode(',', $column['options']);
					foreach ($fieldOptions as $k => $v)
					{
						if (empty($currentKey))
							$currentKey = $v === Utils::$context['members'][$member]['options'][$key] ? $k : 0;
					}
				}

				if ($column['bbc'] && !empty(Utils::$context['members'][$member]['options'][$key]))
					Utils::$context['members'][$member]['options'][$key] = strip_tags(BBCodeParser::load()->parse(Utils::$context['members'][$member]['options'][$key]));

				elseif ($column['type'] == 'check')
					Utils::$context['members'][$member]['options'][$key] = Utils::$context['members'][$member]['options'][$key] == 0 ? Lang::$txt['no'] : Lang::$txt['yes'];

				// Enclosing the user input within some other text?
				if (!empty($column['enclose']))
					Utils::$context['members'][$member]['options'][$key] = strtr($column['enclose'], array(
						'{SCRIPTURL}' => Config::$scripturl,
						'{IMAGES_URL}' => Theme::$current->settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => Theme::$current->settings['default_images_url'],
						'{INPUT}' => Lang::tokenTxtReplace(Utils::$context['members'][$member]['options'][$key]),
						'{KEY}' => $currentKey
					));
			}
		}
	}
}

/**
 * Sets the label, sort and join info for every custom field column.
 *
 * @return array An array of info about the custom fields for the member list
 */
function getCustFieldsMList()
{
	$cpf = array();

	$request = Db::$db->query('', '
		SELECT col_name, field_name, field_desc, field_type, field_options, bbc, enclose, default_value
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			AND show_mlist = {int:show}
			AND private < {int:private_level}',
		array(
			'active' => 1,
			'show' => 1,
			'private_level' => 2,
		)
	);

	while ($row = Db::$db->fetch_assoc($request))
	{
		// Get all the data we're gonna need.
		$cpf['columns'][$row['col_name']] = array(
			'label' => Lang::tokenTxtReplace($row['field_name']),
			'type' => $row['field_type'],
			'options' => Lang::tokenTxtReplace($row['field_options']),
			'bbc' => !empty($row['bbc']),
			'enclose' => $row['enclose'],
			'default_value' => Lang::tokenTxtReplace($row['default_value']),
		);

		// Get the right sort method depending on the cust field type.
		if ($row['field_type'] != 'check')
			$cpf['columns'][$row['col_name']]['sort'] = array(
				'down' => 'LENGTH(t' . $row['col_name'] . '.value) > 0 ASC, COALESCE(t' . $row['col_name'] . '.value, \'\') DESC',
				'up' => 'LENGTH(t' . $row['col_name'] . '.value) > 0 DESC, COALESCE(t' . $row['col_name'] . '.value, \'\') ASC'
			);

		else
			$cpf['columns'][$row['col_name']]['sort'] = array(
				'down' => 't' . $row['col_name'] . '.value DESC',
				'up' => 't' . $row['col_name'] . '.value ASC'
			);

		$cpf['join'][$row['col_name']] = 'LEFT JOIN {db_prefix}themes AS t' . $row['col_name'] . ' ON (t' . $row['col_name'] . '.variable = {literal:' . $row['col_name'] . '} AND t' . $row['col_name'] . '.id_theme = 1 AND t' . $row['col_name'] . '.id_member = mem.id_member)';
	}
	Db::$db->free_result($request);

	return $cpf;
}

?>