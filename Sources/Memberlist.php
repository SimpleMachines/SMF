<?php

/**
 * This file contains the functions for displaying and searching in the
 * members list.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

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
 * @uses Memberlist template, main sub template.
 */
function Memberlist()
{
	global $scripturl, $txt, $modSettings, $context;

	// Make sure they can view the memberlist.
	isAllowedTo('view_mlist');

	loadTemplate('Memberlist');

	$context['listing_by'] = !empty($_GET['sa']) ? $_GET['sa'] : 'all';

	// $subActions array format:
	// 'subaction' => array('label', 'function', 'is_selected')
	$subActions = array(
		'all' => array($txt['view_all_members'], 'MLAll', $context['listing_by'] == 'all'),
		'search' => array($txt['mlist_search'], 'MLSearch', $context['listing_by'] == 'search'),
	);

	// Set up the sort links.
	$context['sort_links'] = array();
	foreach ($subActions as $act => $text)
	{
		$context['sort_links'][] = array(
			'label' => $text[0],
			'action' => $act,
			'selected' => $text[2],
		);
	}

	$context['num_members'] = $modSettings['totalMembers'];

	// Set up the columns...
	$context['columns'] = array(
		'is_online' => array(
			'label' => $txt['status'],
			'sort' => array(
				'down' => allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) ASC, real_name ASC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END ASC, real_name ASC',
				'up' => allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) DESC, real_name DESC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END DESC, real_name DESC'
			),
		),
		'real_name' => array(
			'label' => $txt['name'],
			'class' => 'lefttext',
			'sort' => array(
				'down' => 'mem.real_name DESC',
				'up' => 'mem.real_name ASC'
			),
		),
		'website_url' => array(
			'label' => $txt['website'],
			'link_with' => 'website',
			'sort' => array(
				'down' => 'LENGTH(mem.website_url) > 0 ASC, COALESCE(mem.website_url, 1=1) DESC, mem.website_url DESC',
				'up' => 'LENGTH(mem.website_url) > 0 DESC, COALESCE(mem.website_url, 1=1) ASC, mem.website_url ASC'
			),
		),
		'id_group' => array(
			'label' => $txt['position'],
			'sort' => array(
				'down' => 'COALESCE(mg.group_name, 1=1) DESC, mg.group_name DESC',
				'up' => 'COALESCE(mg.group_name, 1=1) ASC, mg.group_name ASC'
			),
		),
		'registered' => array(
			'label' => $txt['date_registered'],
			'sort' => array(
				'down' => 'mem.date_registered DESC',
				'up' => 'mem.date_registered ASC'
			),
		),
		'post_count' => array(
			'label' => $txt['posts'],
			'default_sort_rev' => true,
			'sort' => array(
				'down' => 'mem.posts DESC',
				'up' => 'mem.posts ASC'
			),
		)
	);

	$context['custom_profile_fields'] = getCustFieldsMList();

	if (!empty($context['custom_profile_fields']['columns']))
		$context['columns'] += $context['custom_profile_fields']['columns'];

	$context['colspan'] = 0;
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();
	foreach ($context['columns'] as $key => $column)
	{
		if (isset($context['disabled_fields'][$key]) || (isset($column['link_with']) && isset($context['disabled_fields'][$column['link_with']])))
		{
			unset($context['columns'][$key]);
			continue;
		}

		$context['colspan'] += isset($column['colspan']) ? $column['colspan'] : 1;
	}

	// Aesthetic stuff.
	end($context['columns']);

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=mlist',
		'name' => $txt['members_list']
	);

	$context['can_send_pm'] = allowedTo('pm_send');
	$context['can_send_email'] = allowedTo('moderate_forum');

	// Build the memberlist button array.
	$context['memberlist_buttons'] = array(
		'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.png', 'url' => $scripturl . '?action=mlist' . ';sa=all', 'active' => true),
		'mlist_search' => array('text' => 'mlist_search', 'image' => 'mlist.png', 'url' => $scripturl . '?action=mlist' . ';sa=search'),
	);

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_memberlist_buttons');

	// Jump to the sub action.
	if (isset($subActions[$context['listing_by']]))
		call_helper($subActions[$context['listing_by']][1]);

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
	global $txt, $scripturl;
	global $modSettings, $context, $smcFunc;

	// The chunk size for the cached index.
	$cache_step_size = 500;

	// Only use caching if:
	// 1. there are at least 2k members,
	// 2. the default sorting method (real_name) is being used,
	// 3. the page shown is high enough to make a DB filesort unprofitable.
	$use_cache = $modSettings['totalMembers'] > 2000 && (!isset($_REQUEST['sort']) || $_REQUEST['sort'] === 'real_name') && isset($_REQUEST['start']) && $_REQUEST['start'] > $cache_step_size;

	if ($use_cache)
	{
		// Maybe there's something cached already.
		if (!empty($modSettings['memberlist_cache']))
			$memberlist_cache = $smcFunc['json_decode']($modSettings['memberlist_cache'], true);

		// The chunk size for the cached index.
		$cache_step_size = 500;

		// Only update the cache if something changed or no cache existed yet.
		if (empty($memberlist_cache) || empty($modSettings['memberlist_updated']) || $memberlist_cache['last_update'] < $modSettings['memberlist_updated'])
		{
			$request = $smcFunc['db_query']('', '
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
				'num_members' => $smcFunc['db_num_rows']($request),
				'index' => array(),
			);

			for ($i = 0, $n = $smcFunc['db_num_rows']($request); $i < $n; $i += $cache_step_size)
			{
				$smcFunc['db_data_seek']($request, $i);
				list($memberlist_cache['index'][$i]) = $smcFunc['db_fetch_row']($request);
			}
			$smcFunc['db_data_seek']($request, $memberlist_cache['num_members'] - 1);
			list ($memberlist_cache['index'][$i]) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// Now we've got the cache...store it.
			updateSettings(array('memberlist_cache' => $smcFunc['json_encode']($memberlist_cache)));
		}

		$context['num_members'] = $memberlist_cache['num_members'];
	}

	// Without cache we need an extra query to get the amount of members.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
			)
		);
		list ($context['num_members']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Set defaults for sort (real_name) and start. (0)
	if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
		$_REQUEST['sort'] = 'real_name';

	if (!is_numeric($_REQUEST['start']))
	{
		if (preg_match('~^[^\'\\\\/]~' . ($context['utf8'] ? 'u' : ''), $smcFunc['strtolower']($_REQUEST['start']), $match) === 0)
			fatal_error('Hacker?', false);

		$_REQUEST['start'] = $match[0];

		$request = $smcFunc['db_query']('substring', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE LOWER(SUBSTRING(real_name, 1, 1)) < {string:first_letter}
				AND is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
				'first_letter' => $_REQUEST['start'],
			)
		);
		list ($_REQUEST['start']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	$context['letter_links'] = '';
	for ($i = 97; $i < 123; $i++)
		$context['letter_links'] .= '<a href="' . $scripturl . '?action=mlist;sa=all;start=' . chr($i) . '#letter' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=mlist;sort=' . $col . ';start=0';

		if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
	}

	// Are we sorting the results
	$context['sort_by'] = $_REQUEST['sort'];
	$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $context['num_members'], $modSettings['defaultMaxMembers']);

	// Send the data to the template.
	$context['start'] = $_REQUEST['start'] + 1;
	$context['end'] = min($_REQUEST['start'] + $modSettings['defaultMaxMembers'], $context['num_members']);

	$context['can_moderate_forum'] = allowedTo('moderate_forum');
	$context['page_title'] = sprintf($txt['viewing_members'], $context['start'], $context['end']);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . ';start=' . $_REQUEST['start'],
		'name' => &$context['page_title'],
		'extra_after' => '(' . sprintf($txt['of_total_members'], $context['num_members']) . ')'
	);

	$limit = $_REQUEST['start'];
	$query_parameters = array(
		'regular_id_group' => 0,
		'is_activated' => 1,
		'sort' => $context['columns'][$_REQUEST['sort']]['sort'][$context['sort_direction']],
		'blank_string' => '',
	);

	// Using cache allows to narrow down the list to be retrieved.
	if ($use_cache && $_REQUEST['sort'] === 'real_name' && !isset($_REQUEST['desc']))
	{
		$first_offset = $_REQUEST['start'] - ($_REQUEST['start'] % $cache_step_size);
		$second_offset = ceil(($_REQUEST['start'] + $modSettings['defaultMaxMembers']) / $cache_step_size) * $cache_step_size;

		$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
		$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
		$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
		$limit -= $first_offset;
	}

	// Reverse sorting is a bit more complicated...
	elseif ($use_cache && $_REQUEST['sort'] === 'real_name')
	{
		$first_offset = floor(($memberlist_cache['num_members'] - $modSettings['defaultMaxMembers'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;
		if ($first_offset < 0)
			$first_offset = 0;
		$second_offset = ceil(($memberlist_cache['num_members'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;

		$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
		$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
		$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
		$limit = $second_offset - ($memberlist_cache['num_members'] - $_REQUEST['start']) - ($second_offset > $memberlist_cache['num_members'] ? $cache_step_size - ($memberlist_cache['num_members'] % $cache_step_size) : 0);
	}

	$custom_fields_qry = '';
	if (!empty($context['custom_profile_fields']['join'][$_REQUEST['sort']]))
		$custom_fields_qry = $context['custom_profile_fields']['join'][$_REQUEST['sort']];

	// Select the members from the database.
	$request = $smcFunc['db_query']('', '
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
			'max' => $modSettings['defaultMaxMembers'],
		))
	);
	printMemberListRows($request);
	$smcFunc['db_free_result']($request);

	// Add anchors at the start of each letter.
	if ($_REQUEST['sort'] == 'real_name')
	{
		$last_letter = '';
		foreach ($context['members'] as $i => $dummy)
		{
			$this_letter = $smcFunc['strtolower']($smcFunc['substr']($context['members'][$i]['name'], 0, 1));

			if ($this_letter != $last_letter && preg_match('~[a-z]~', $this_letter) === 1)
			{
				$context['members'][$i]['sort_letter'] = $smcFunc['htmlspecialchars']($this_letter);
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
	global $txt, $scripturl, $context, $modSettings, $smcFunc;

	$context['page_title'] = $txt['mlist_search'];
	$context['can_moderate_forum'] = allowedTo('moderate_forum');

	// Can they search custom fields?
	$request = $smcFunc['db_query']('', '
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
	$context['custom_search_fields'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['custom_search_fields'][$row['col_name']] = array(
			'colname' => $row['col_name'],
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
		);
	$smcFunc['db_free_result']($request);

	// They're searching..
	if (isset($_REQUEST['search']) && isset($_REQUEST['fields']))
	{
		$_POST['search'] = trim(isset($_GET['search']) ? $_GET['search'] : $_POST['search']);
		$_POST['fields'] = isset($_GET['fields']) ? explode(',', $_GET['fields']) : $_POST['fields'];

		$context['old_search'] = $_REQUEST['search'];
		$context['old_search_value'] = urlencode($_REQUEST['search']);

		// No fields?  Use default...
		if (empty($_POST['fields']))
			$_POST['fields'] = array('name');

		// Set defaults for how the results are sorted
		if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
			$_REQUEST['sort'] = 'real_name';

		// Build the column link / sort information.
		foreach ($context['columns'] as $col => $column_details)
		{
			$context['columns'][$col]['href'] = $scripturl . '?action=mlist;sa=search;start=0;sort=' . $col;

			if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
				$context['columns'][$col]['href'] .= ';desc';

			if (isset($_POST['search']) && isset($_POST['fields']))
				$context['columns'][$col]['href'] .= ';search=' . $_POST['search'] . ';fields=' . implode(',', $_POST['fields']);

			$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
			$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
		}

		// set up some things for use in the template
		$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';
		$context['sort_by'] = $_REQUEST['sort'];

		$query_parameters = array(
			'regular_id_group' => 0,
			'is_activated' => 1,
			'blank_string' => '',
			'search' => '%' . strtr($smcFunc['htmlspecialchars']($_POST['search'], ENT_QUOTES), array('_' => '\\_', '%' => '\\%', '*' => '%')) . '%',
			'sort' => $context['columns'][$_REQUEST['sort']]['sort'][$context['sort_direction']],
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

		if ($smcFunc['db_case_sensitive'])
			foreach ($fields as $key => $field)
				$fields[$key] = 'LOWER(' . $field . ')';

		$customJoin = array();
		$customCount = 10;

		// Any custom fields to search for - these being tricky?
		foreach ($_POST['fields'] as $field)
		{
			$row['col_name'] = substr($field, 5);
			if (substr($field, 0, 5) == 'cust_' && isset($context['custom_search_fields'][$row['col_name']]))
			{
				$customJoin[] = 'LEFT JOIN {db_prefix}themes AS t' . $row['col_name'] . ' ON (t' . $row['col_name'] . '.variable = {string:t' . $row['col_name'] . '} AND t' . $row['col_name'] . '.id_theme = 1 AND t' . $row['col_name'] . '.id_member = mem.id_member)';
				$query_parameters['t' . $row['col_name']] = $row['col_name'];
				$fields += array($customCount++ => 'COALESCE(t' . $row['col_name'] . '.value, {string:blank_string})');
				$search_fields[] = $field;
			}
		}

		// No search fields? That means you're trying to hack things
		if (empty($search_fields))
			fatal_lang_error('invalid_search_string', false);

		$query = $_POST['search'] == '' ? '= {string:blank_string}' : ($smcFunc['db_case_sensitive'] ? 'LIKE LOWER({string:search})' : 'LIKE {string:search}');

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
				(empty($customJoin) ? '' : implode('
				', $customJoin)) . '
			WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . ')
				AND mem.is_activated = {int:is_activated}',
			$query_parameters
		);
		list ($numResults) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['page_index'] = constructPageIndex($scripturl . '?action=mlist;sa=search;search=' . $_POST['search'] . ';fields=' . implode(',', $_POST['fields']), $_REQUEST['start'], $numResults, $modSettings['defaultMaxMembers']);

		// Find the members from the database.
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
				(empty($customJoin) ? '' : implode('
				', $customJoin)) . '
			WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . ')
				AND mem.is_activated = {int:is_activated}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			array_merge($query_parameters, array(
				'start' => $_REQUEST['start'],
				'max' => $modSettings['defaultMaxMembers'],
			))
		);
		printMemberListRows($request);
		$smcFunc['db_free_result']($request);
	}
	else
	{
		// These are all the possible fields.
		$context['search_fields'] = array(
			'name' => $txt['mlist_search_name'],
			'email' => $txt['mlist_search_email'],
			'website' => $txt['mlist_search_website'],
			'group' => $txt['mlist_search_group'],
		);

		// Sorry, but you can't search by email unless you can view emails
		if (!allowedTo('moderate_forum'))
		{
			unset($context['search_fields']['email']);
			$context['search_defaults'] = array('name');
		}
		else
		{
			$context['search_defaults'] = array('name', 'email');
		}

		foreach ($context['custom_search_fields'] as $field)
			$context['search_fields']['cust_' . $field['colname']] = sprintf($txt['mlist_search_by'], $field['name']);

		$context['sub_template'] = 'search';
		$context['old_search'] = isset($_GET['search']) ? $_GET['search'] : (isset($_POST['search']) ? $smcFunc['htmlspecialchars']($_POST['search']) : '');

		// Since we're nice we also want to default focus on to the search field.
		addInlineJavaScript('
	$(\'input[name="search"]\').focus();', true);
	}

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=mlist;sa=search',
		'name' => &$context['page_title']
	);

	// Highlight the correct button, too!
	unset($context['memberlist_buttons']['view_all_members']['active']);
	$context['memberlist_buttons']['mlist_search']['active'] = true;
}

/**
 * Retrieves results of the request passed to it
 * Puts results of request into the context for the sub template.
 *
 * @param resource $request An SQL result resource
 */
function printMemberListRows($request)
{
	global $context, $memberContext, $smcFunc, $txt;
	global $scripturl, $settings;

	// Get the most posts.
	$result = $smcFunc['db_query']('', '
		SELECT MAX(posts)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($most_posts) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Avoid division by zero...
	if ($most_posts == 0)
		$most_posts = 1;

	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = $row['id_member'];

	// Load all the members for display.
	loadMemberData($members);

	$context['members'] = array();
	foreach ($members as $member)
	{
		if (!loadMemberContext($member))
			continue;

		$context['members'][$member] = $memberContext[$member];
		$context['members'][$member]['post_percent'] = round(($context['members'][$member]['real_posts'] * 100) / $most_posts);
		$context['members'][$member]['registered_date'] = strftime('%Y-%m-%d', $context['members'][$member]['registered_timestamp']);

		if (!empty($context['custom_profile_fields']['columns']))
		{
			foreach ($context['custom_profile_fields']['columns'] as $key => $column)
			{
				// Don't show anything if there isn't anything to show.
				if (!isset($context['members'][$member]['options'][$key]))
				{
					$context['members'][$member]['options'][$key] = '';
					continue;
				}

				$currentKey = 0;
				if (!empty($column['options']))
				{
					$fieldOptions = explode(',', $column['options']);
					foreach ($fieldOptions as $k => $v)
					{
						if (empty($currentKey))
							$currentKey = $v === $context['members'][$member]['options'][$key] ? $k : 0;
					}
				}

				if ($column['bbc'] && !empty($context['members'][$member]['options'][$key]))
					$context['members'][$member]['options'][$key] = strip_tags(parse_bbc($context['members'][$member]['options'][$key]));

				elseif ($column['type'] == 'check')
					$context['members'][$member]['options'][$key] = $context['members'][$member]['options'][$key] == 0 ? $txt['no'] : $txt['yes'];

				// Enclosing the user input within some other text?
				if (!empty($column['enclose']))
					$context['members'][$member]['options'][$key] = strtr($column['enclose'], array(
						'{SCRIPTURL}' => $scripturl,
						'{IMAGES_URL}' => $settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
						'{INPUT}' => $context['members'][$member]['options'][$key],
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
	global $smcFunc;

	$cpf = array();

	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_desc, field_type, field_options, bbc, enclose
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

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Get all the data we're gonna need.
		$cpf['columns'][$row['col_name']] = array(
			'label' => $row['field_name'],
			'type' => $row['field_type'],
			'options' => $row['field_options'],
			'bbc' => !empty($row['bbc']),
			'enclose' => $row['enclose'],
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
	$smcFunc['db_free_result']($request);

	return $cpf;
}

?>