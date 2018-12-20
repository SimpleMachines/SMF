<?php

/**
 * Show a list of members or a selection of members.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main entrance point for the Manage Members screen.
 * As everyone else, it calls a function based on the given sub-action.
 * Called by ?action=admin;area=viewmembers.
 * Requires the moderate_forum permission.
 *
 * @uses ManageMembers template
 * @uses ManageMembers language file.
 */
function ViewMembers()
{
	global $txt, $scripturl, $context, $modSettings, $smcFunc;

	$subActions = array(
		'all' => array('ViewMemberlist', 'moderate_forum'),
		'approve' => array('AdminApprove', 'moderate_forum'),
		'browse' => array('MembersAwaitingActivation', 'moderate_forum'),
		'search' => array('SearchMembers', 'moderate_forum'),
		'query' => array('ViewMemberlist', 'moderate_forum'),
	);

	// Load the essentials.
	loadLanguage('ManageMembers');
	loadTemplate('ManageMembers');

	// Fetch our activation counts.
	GetMemberActivationCounts();

	// For the page header... do we show activation?
	$context['show_activate'] = (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1) || !empty($context['awaiting_activation']);

	// What about approval?
	$context['show_approve'] = (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($context['awaiting_approval']) || !empty($modSettings['approveAccountDeletion']);

	// Setup the admin tabs.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_members'],
		'help' => 'view_members',
		'description' => $txt['admin_members_list'],
		'tabs' => array(),
	);

	$context['tabs'] = array(
		'viewmembers' => array(
			'label' => $txt['view_all_members'],
			'description' => $txt['admin_members_list'],
			'url' => $scripturl . '?action=admin;area=viewmembers;sa=all',
			'selected_actions' => array('all'),
		),
		'search' => array(
			'label' => $txt['mlist_search'],
			'description' => $txt['admin_members_list'],
			'url' => $scripturl . '?action=admin;area=viewmembers;sa=search',
			'selected_actions' => array('search', 'query'),
		),
	);
	$context['last_tab'] = 'search';

	// Do we have approvals
	if ($context['show_approve'])
	{
		$context['tabs']['approve'] = array(
			'label' => sprintf($txt['admin_browse_awaiting_approval'], $context['awaiting_approval']),
			'description' => $txt['admin_browse_approve_desc'],
			'url' => $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve',
		);
		$context['last_tab'] = 'approve';
	}

	// Do we have activations to show?
	if ($context['show_activate'])
	{
		$context['tabs']['activate'] = array(
			'label' => sprintf($txt['admin_browse_awaiting_activate'], $context['awaiting_activation']),
			'description' => $txt['admin_browse_activate_desc'],
			'url' => $scripturl . '?action=admin;area=viewmembers;sa=browse;type=activate',
		);
		$context['last_tab'] = 'activate';
	}

	// Call our hook now, letting customizations add to the subActions and/or modify $context as needed.
	call_integration_hook('integrate_manage_members', array(&$subActions));

	// Default to sub action 'index' or 'settings' depending on permissions.
	$context['current_subaction'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'all';

	// We know the sub action, now we know what you're allowed to do.
	isAllowedTo($subActions[$context['current_subaction']][1]);

	// Set the last tab.
	$context['tabs'][$context['last_tab']]['is_last'] = true;

	// Find the active tab.
	if (isset($context['tabs'][$context['current_subaction']]))
		$context['tabs'][$context['current_subaction']]['is_selected'] = true;
	elseif (isset($context['current_subaction']))
		foreach ($context['tabs'] as $id_tab => $tab_data)
			if (!empty($tab_data['selected_actions']) && in_array($context['current_subaction'], $tab_data['selected_actions']))
				$context['tabs'][$id_tab]['is_selected'] = true;

	call_helper($subActions[$context['current_subaction']][0]);
}

/**
 * View all members list. It allows sorting on several columns, and deletion of
 * selected members. It also handles the search query sent by
 * ?action=admin;area=viewmembers;sa=search.
 * Called by ?action=admin;area=viewmembers;sa=all or ?action=admin;area=viewmembers;sa=query.
 * Requires the moderate_forum permission.
 *
 * @uses the view_members sub template of the ManageMembers template.
 */
function ViewMemberlist()
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir, $smcFunc, $user_info;

	// Are we performing a delete?
	if (isset($_POST['delete_members']) && !empty($_POST['delete']) && allowedTo('profile_remove_any'))
	{
		checkSession();

		// Clean the input.
		foreach ($_POST['delete'] as $key => $value)
		{
			// Don't delete yourself, idiot.
			if ($value != $user_info['id'])
				$delete[$key] = (int) $value;
		}

		if (!empty($delete))
		{
			// Delete all the selected members.
			require_once($sourcedir . '/Subs-Members.php');
			deleteMembers($delete, true);
		}
	}

	// Check input after a member search has been submitted.
	if ($context['current_subaction'] == 'query')
	{
		// Retrieving the membergroups and postgroups.
		$context['membergroups'] = array(
			array(
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'can_be_additional' => false
			)
		);
		$context['postgroups'] = array();

		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'newbie_group' => 4,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['min_posts'] == -1)
				$context['membergroups'][] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'can_be_additional' => true
				);
			else
				$context['postgroups'][] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name']
				);
		}
		$smcFunc['db_free_result']($request);

		// Some data about the form fields and how they are linked to the database.
		$params = array(
			'mem_id' => array(
				'db_fields' => array('id_member'),
				'type' => 'int',
				'range' => true
			),
			'age' => array(
				'db_fields' => array('birthdate'),
				'type' => 'age',
				'range' => true
			),
			'posts' => array(
				'db_fields' => array('posts'),
				'type' => 'int',
				'range' => true
			),
			'reg_date' => array(
				'db_fields' => array('date_registered'),
				'type' => 'date',
				'range' => true
			),
			'last_online' => array(
				'db_fields' => array('last_login'),
				'type' => 'date',
				'range' => true
			),
			'activated' => array(
				'db_fields' => array('CASE WHEN is_activated IN (1, 11) THEN 1 ELSE 0 END'),
				'type' => 'checkbox',
				'values' => array('0', '1'),
			),
			'membername' => array(
				'db_fields' => array('member_name', 'real_name'),
				'type' => 'string'
			),
			'email' => array(
				'db_fields' => array('email_address'),
				'type' => 'string'
			),
			'website' => array(
				'db_fields' => array('website_title', 'website_url'),
				'type' => 'string'
			),
			'ip' => array(
				'db_fields' => array('member_ip'),
				'type' => 'inet'
			),
			'membergroups' => array(
				'db_fields' => array('id_group'),
				'type' => 'groups'
			),
			'postgroups' => array(
				'db_fields' => array('id_group'),
				'type' => 'groups'
			)
		);
		$range_trans = array(
			'--' => '<',
			'-' => '<=',
			'=' => '=',
			'+' => '>=',
			'++' => '>'
		);

		call_integration_hook('integrate_view_members_params', array(&$params));

		$search_params = array();
		if ($context['current_subaction'] == 'query' && !empty($_REQUEST['params']) && empty($_POST['types']))
			$search_params = $smcFunc['json_decode'](base64_decode($_REQUEST['params']), true);
		elseif (!empty($_POST))
		{
			$search_params['types'] = $_POST['types'];
			foreach ($params as $param_name => $param_info)
				if (isset($_POST[$param_name]))
					$search_params[$param_name] = $_POST[$param_name];
		}

		$search_url_params = isset($search_params) ? base64_encode($smcFunc['json_encode']($search_params)) : null;

		// @todo Validate a little more.

		// Loop through every field of the form.
		$query_parts = array();
		$where_params = array();
		foreach ($params as $param_name => $param_info)
		{
			// Not filled in?
			if (!isset($search_params[$param_name]) || $search_params[$param_name] === '')
				continue;

			// Make sure numeric values are really numeric.
			if (in_array($param_info['type'], array('int', 'age')))
				$search_params[$param_name] = (int) $search_params[$param_name];
			// Date values have to match the specified format.
			elseif ($param_info['type'] == 'date')
			{
				// Check if this date format is valid.
				if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $search_params[$param_name]) == 0)
					continue;

				$search_params[$param_name] = strtotime($search_params[$param_name]);
			}
			elseif ($param_info['type'] == 'inet')
			{
				$search_params[$param_name] = ip2range($search_params[$param_name]);
				if (empty($search_params[$param_name]))
					continue;
			}

			// Those values that are in some kind of range (<, <=, =, >=, >).
			if (!empty($param_info['range']))
			{
				// Default to '=', just in case...
				if (empty($range_trans[$search_params['types'][$param_name]]))
					$search_params['types'][$param_name] = '=';

				// Handle special case 'age'.
				if ($param_info['type'] == 'age')
				{
					// All people that were born between $lowerlimit and $upperlimit are currently the specified age.
					$datearray = getdate(forum_time());
					$upperlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $search_params[$param_name], $datearray['mon'], $datearray['mday']);
					$lowerlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $search_params[$param_name] - 1, $datearray['mon'], $datearray['mday']);
					if (in_array($search_params['types'][$param_name], array('-', '--', '=')))
					{
						$query_parts[] = ($param_info['db_fields'][0]) . ' > {string:' . $param_name . '_minlimit}';
						$where_params[$param_name . '_minlimit'] = ($search_params['types'][$param_name] == '--' ? $upperlimit : $lowerlimit);
					}
					if (in_array($search_params['types'][$param_name], array('+', '++', '=')))
					{
						$query_parts[] = ($param_info['db_fields'][0]) . ' <= {string:' . $param_name . '_pluslimit}';
						$where_params[$param_name . '_pluslimit'] = ($search_params['types'][$param_name] == '++' ? $lowerlimit : $upperlimit);

						// Make sure that members that didn't set their birth year are not queried.
						$query_parts[] = ($param_info['db_fields'][0]) . ' > {date:dec_zero_date}';
						$where_params['dec_zero_date'] = '0004-12-31';
					}
				}
				// Special case - equals a date.
				elseif ($param_info['type'] == 'date' && $search_params['types'][$param_name] == '=')
				{
					$query_parts[] = $param_info['db_fields'][0] . ' > ' . $search_params[$param_name] . ' AND ' . $param_info['db_fields'][0] . ' < ' . ($search_params[$param_name] + 86400);
				}
				else
					$query_parts[] = $param_info['db_fields'][0] . ' ' . $range_trans[$search_params['types'][$param_name]] . ' ' . $search_params[$param_name];
			}
			// Checkboxes.
			elseif ($param_info['type'] == 'checkbox')
			{
				// Each checkbox or no checkbox at all is checked -> ignore.
				if (!is_array($search_params[$param_name]) || count($search_params[$param_name]) == 0 || count($search_params[$param_name]) == count($param_info['values']))
					continue;

				$query_parts[] = ($param_info['db_fields'][0]) . ' IN ({array_string:' . $param_name . '_check})';
				$where_params[$param_name . '_check'] = $search_params[$param_name];
			}
			// INET.
			elseif ($param_info['type'] == 'inet')
			{
				if (count($search_params[$param_name]) === 1)
				{
					$query_parts[] = '(' . $param_info['db_fields'][0] . ' = {inet:' . $param_name . '})';
					$where_params[$param_name] = $search_params[$param_name][0];
				}
				elseif (count($search_params[$param_name]) === 2)
				{
					$query_parts[] = '(' . $param_info['db_fields'][0] . ' <= {inet:' . $param_name . '_high} and ' . $param_info['db_fields'][0] . ' >= {inet:' . $param_name . '_low})';
					$where_params[$param_name . '_low'] = $search_params[$param_name]['low'];
					$where_params[$param_name . '_high'] = $search_params[$param_name]['high'];
				}

			}
			elseif ($param_info['type'] != 'groups')
			{
				// Replace the wildcard characters ('*' and '?') into MySQL ones.
				$parameter = strtolower(strtr($smcFunc['htmlspecialchars']($search_params[$param_name], ENT_QUOTES), array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_')));

				if ($smcFunc['db_case_sensitive'])
					$query_parts[] = '(LOWER(' . implode(') LIKE {string:' . $param_name . '_normal} OR LOWER(', $param_info['db_fields']) . ') LIKE {string:' . $param_name . '_normal})';
				else
					$query_parts[] = '(' . implode(' LIKE {string:' . $param_name . '_normal} OR ', $param_info['db_fields']) . ' LIKE {string:' . $param_name . '_normal})';
				$where_params[$param_name . '_normal'] = '%' . $parameter . '%';
			}
		}

		// Set up the membergroup query part.
		$mg_query_parts = array();

		// Primary membergroups, but only if at least was was not selected.
		if (!empty($search_params['membergroups'][1]) && count($context['membergroups']) != count($search_params['membergroups'][1]))
		{
			$mg_query_parts[] = 'mem.id_group IN ({array_int:group_check})';
			$where_params['group_check'] = $search_params['membergroups'][1];
		}

		// Additional membergroups (these are only relevant if not all primary groups where selected!).
		if (!empty($search_params['membergroups'][2]) && (empty($search_params['membergroups'][1]) || count($context['membergroups']) != count($search_params['membergroups'][1])))
			foreach ($search_params['membergroups'][2] as $mg)
			{
				$mg_query_parts[] = 'FIND_IN_SET({int:add_group_' . $mg . '}, mem.additional_groups) != 0';
				$where_params['add_group_' . $mg] = $mg;
			}

		// Combine the one or two membergroup parts into one query part linked with an OR.
		if (!empty($mg_query_parts))
			$query_parts[] = '(' . implode(' OR ', $mg_query_parts) . ')';

		// Get all selected post count related membergroups.
		if (!empty($search_params['postgroups']) && count($search_params['postgroups']) != count($context['postgroups']))
		{
			$query_parts[] = 'id_post_group IN ({array_int:post_groups})';
			$where_params['post_groups'] = $search_params['postgroups'];
		}

		// Construct the where part of the query.
		$where = empty($query_parts) ? '1=1' : implode('
			AND ', $query_parts);
	}
	else
		$search_url_params = null;

	// Construct the additional URL part with the query info in it.
	$context['params_url'] = $context['current_subaction'] == 'query' ? ';sa=query;params=' . $search_url_params : '';

	// Get the title and sub template ready..
	$context['page_title'] = $txt['admin_members'];

	$listOptions = array(
		'id' => 'member_list',
		'title' => $txt['members_list'],
		'items_per_page' => $modSettings['defaultMaxMembers'],
		'base_href' => $scripturl . '?action=admin;area=viewmembers' . $context['params_url'],
		'default_sort_col' => 'user_name',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Members.php',
			'function' => 'list_getMembers',
			'params' => array(
				isset($where) ? $where : '1=1',
				isset($where_params) ? $where_params : array(),
			),
		),
		'get_count' => array(
			'file' => $sourcedir . '/Subs-Members.php',
			'function' => 'list_getNumMembers',
			'params' => array(
				isset($where) ? $where : '1=1',
				isset($where_params) ? $where_params : array(),
			),
		),
		'columns' => array(
			'id_member' => array(
				'header' => array(
					'value' => $txt['member_id'],
				),
				'data' => array(
					'db' => 'id_member',
				),
				'sort' => array(
					'default' => 'id_member',
					'reverse' => 'id_member DESC',
				),
			),
			'user_name' => array(
				'header' => array(
					'value' => $txt['username'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'member_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'member_name',
					'reverse' => 'member_name DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => $txt['display_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'real_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'real_name',
					'reverse' => 'real_name DESC',
				),
			),
			'email' => array(
				'header' => array(
					'value' => $txt['email_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'email_address' => true,
						),
					),
				),
				'sort' => array(
					'default' => 'email_address',
					'reverse' => 'email_address DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'member_ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'member_ip',
					'reverse' => 'member_ip DESC',
				),
			),
			'last_active' => array(
				'header' => array(
					'value' => $txt['viewmembers_online'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						// Calculate number of days since last online.
						if (empty($rowData['last_login']))
							$difference = $txt['never'];
						else
						{
							$num_days_difference = jeffsdatediff($rowData['last_login']);

							// Today.
							if (empty($num_days_difference))
								$difference = $txt['viewmembers_today'];

							// Yesterday.
							elseif ($num_days_difference == 1)
								$difference = sprintf('1 %1$s', $txt['viewmembers_day_ago']);

							// X days ago.
							else
								$difference = sprintf('%1$d %2$s', $num_days_difference, $txt['viewmembers_days_ago']);
						}

						// Show it in italics if they're not activated...
						if ($rowData['is_activated'] % 10 != 1)
							$difference = sprintf('<em title="%1$s">%2$s</em>', $txt['not_activated'], $difference);

						return $difference;
					},
				),
				'sort' => array(
					'default' => 'last_login DESC',
					'reverse' => 'last_login',
				),
			),
			'posts' => array(
				'header' => array(
					'value' => $txt['member_postcount'],
				),
				'data' => array(
					'db' => 'posts',
				),
				'sort' => array(
					'default' => 'posts',
					'reverse' => 'posts DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData) use ($user_info)
					{
						return '<input type="checkbox" name="delete[]" value="' . $rowData['id_member'] . '"' . ($rowData['id_member'] == $user_info['id'] || $rowData['id_group'] == 1 || in_array(1, explode(',', $rowData['additional_groups'])) ? ' disabled' : '') . '>';
					},
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=viewmembers' . $context['params_url'],
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="delete_members" value="' . $txt['admin_delete_members'] . '" data-confirm="' . $txt['confirm_delete_members'] . '" class="button you_sure">',
			),
		),
	);

	// Without enough permissions, don't show 'delete members' checkboxes.
	if (!allowedTo('profile_remove_any'))
		unset($listOptions['cols']['check'], $listOptions['form'], $listOptions['additional_rows']);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'member_list';
}

/**
 * Search the member list, using one or more criteria.
 * Called by ?action=admin;area=viewmembers;sa=search.
 * Requires the moderate_forum permission.
 * form is submitted to action=admin;area=viewmembers;sa=query.
 *
 * @uses the search_members sub template of the ManageMembers template.
 */
function SearchMembers()
{
	global $context, $txt, $smcFunc;

	// Get a list of all the membergroups and postgroups that can be selected.
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'can_be_additional' => false
		)
	);
	$context['postgroups'] = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'moderator_group' => 3,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['min_posts'] == -1)
			$context['membergroups'][] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'can_be_additional' => true
			);
		else
			$context['postgroups'][] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name']
			);
	}
	$smcFunc['db_free_result']($request);

	$context['page_title'] = $txt['admin_members'];
	$context['sub_template'] = 'search_members';
}

/**
 * List all members who are awaiting approval / activation, sortable on different columns.
 * It allows instant approval or activation of (a selection of) members.
 * Called by ?action=admin;area=viewmembers;sa=browse;type=approve
 *  or ?action=admin;area=viewmembers;sa=browse;type=activate.
 * The form submits to ?action=admin;area=viewmembers;sa=approve.
 * Requires the moderate_forum permission.
 *
 * @uses the admin_browse sub template of the ManageMembers template.
 */
function MembersAwaitingActivation()
{
	global $txt, $context, $scripturl, $modSettings;
	global $sourcedir;

	// Not a lot here!
	$context['page_title'] = $txt['admin_members'];
	$context['sub_template'] = 'admin_browse';
	$context['browse_type'] = isset($_REQUEST['type']) ? $_REQUEST['type'] : (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? 'activate' : 'approve');
	if (isset($context['tabs'][$context['browse_type']]))
		$context['tabs'][$context['browse_type']]['is_selected'] = true;

	// Allowed filters are those we can have, in theory.
	$context['allowed_filters'] = $context['browse_type'] == 'approve' ? array(3, 4, 5) : array(0, 2);
	$context['current_filter'] = isset($_REQUEST['filter']) && in_array($_REQUEST['filter'], $context['allowed_filters']) && !empty($context['activation_numbers'][$_REQUEST['filter']]) ? (int) $_REQUEST['filter'] : -1;

	// Sort out the different sub areas that we can actually filter by.
	$context['available_filters'] = array();
	foreach ($context['activation_numbers'] as $type => $amount)
	{
		// We have some of these...
		if (in_array($type, $context['allowed_filters']) && $amount > 0)
			$context['available_filters'][] = array(
				'type' => $type,
				'amount' => $amount,
				'desc' => isset($txt['admin_browse_filter_type_' . $type]) ? $txt['admin_browse_filter_type_' . $type] : '?',
				'selected' => $type == $context['current_filter']
			);
	}

	// If the filter was not sent, set it to whatever has people in it!
	if ($context['current_filter'] == -1 && !empty($context['available_filters'][0]['amount']))
		$context['current_filter'] = $context['available_filters'][0]['type'];

	// This little variable is used to determine if we should flag where we are looking.
	$context['show_filter'] = ($context['current_filter'] != 0 && $context['current_filter'] != 3) || count($context['available_filters']) > 1;

	// The columns that can be sorted.
	$context['columns'] = array(
		'id_member' => array('label' => $txt['admin_browse_id']),
		'member_name' => array('label' => $txt['admin_browse_username']),
		'email_address' => array('label' => $txt['admin_browse_email']),
		'member_ip' => array('label' => $txt['admin_browse_ip']),
		'date_registered' => array('label' => $txt['admin_browse_registered']),
	);

	// Are we showing duplicate information?
	if (isset($_GET['showdupes']))
		$_SESSION['showdupes'] = (int) $_GET['showdupes'];
	$context['show_duplicates'] = !empty($_SESSION['showdupes']);

	// Determine which actions we should allow on this page.
	if ($context['browse_type'] == 'approve')
	{
		// If we are approving deleted accounts we have a slightly different list... actually a mirror ;)
		if ($context['current_filter'] == 4)
			$context['allowed_actions'] = array(
				'reject' => $txt['admin_browse_w_approve_deletion'],
				'ok' => $txt['admin_browse_w_reject'],
			);
		else
			$context['allowed_actions'] = array(
				'ok' => $txt['admin_browse_w_approve'],
				'okemail' => $txt['admin_browse_w_approve'] . ' ' . $txt['admin_browse_w_email'],
				'require_activation' => $txt['admin_browse_w_approve_require_activate'],
				'reject' => $txt['admin_browse_w_reject'],
				'rejectemail' => $txt['admin_browse_w_reject'] . ' ' . $txt['admin_browse_w_email'],
			);
	}
	elseif ($context['browse_type'] == 'activate')
		$context['allowed_actions'] = array(
			'ok' => $txt['admin_browse_w_activate'],
			'okemail' => $txt['admin_browse_w_activate'] . ' ' . $txt['admin_browse_w_email'],
			'delete' => $txt['admin_browse_w_delete'],
			'deleteemail' => $txt['admin_browse_w_delete'] . ' ' . $txt['admin_browse_w_email'],
			'remind' => $txt['admin_browse_w_remind'] . ' ' . $txt['admin_browse_w_email'],
		);

	// Create an option list for actions allowed to be done with selected members.
	$allowed_actions = '
			<option selected value="">' . $txt['admin_browse_with_selected'] . ':</option>
			<option value="" disabled>-----------------------------</option>';
	foreach ($context['allowed_actions'] as $key => $desc)
		$allowed_actions .= '
			<option value="' . $key . '">' . $desc . '</option>';

	// Setup the Javascript function for selecting an action for the list.
	$javascript = '
		function onSelectChange()
		{
			if (document.forms.postForm.todo.value == "")
				return;

			var message = "";';

	// We have special messages for approving deletion of accounts - it's surprisingly logical - honest.
	if ($context['current_filter'] == 4)
		$javascript .= '
			if (document.forms.postForm.todo.value.indexOf("reject") != -1)
				message = "' . $txt['admin_browse_w_delete'] . '";
			else
				message = "' . $txt['admin_browse_w_reject'] . '";';
	// Otherwise a nice standard message.
	else
		$javascript .= '
			if (document.forms.postForm.todo.value.indexOf("delete") != -1)
				message = "' . $txt['admin_browse_w_delete'] . '";
			else if (document.forms.postForm.todo.value.indexOf("reject") != -1)
				message = "' . $txt['admin_browse_w_reject'] . '";
			else if (document.forms.postForm.todo.value == "remind")
				message = "' . $txt['admin_browse_w_remind'] . '";
			else
				message = "' . ($context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate']) . '";';
	$javascript .= '
			if (confirm(message + " ' . $txt['admin_browse_warn'] . '"))
				document.forms.postForm.submit();
		}';

	$listOptions = array(
		'id' => 'approve_list',
// 		'title' => $txt['members_approval_title'],
		'items_per_page' => $modSettings['defaultMaxMembers'],
		'base_href' => $scripturl . '?action=admin;area=viewmembers;sa=browse;type=' . $context['browse_type'] . (!empty($context['show_filter']) ? ';filter=' . $context['current_filter'] : ''),
		'default_sort_col' => 'date_registered',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Members.php',
			'function' => 'list_getMembers',
			'params' => array(
				'is_activated = {int:activated_status}',
				array('activated_status' => $context['current_filter']),
				$context['show_duplicates'],
			),
		),
		'get_count' => array(
			'file' => $sourcedir . '/Subs-Members.php',
			'function' => 'list_getNumMembers',
			'params' => array(
				'is_activated = {int:activated_status}',
				array('activated_status' => $context['current_filter']),
			),
		),
		'columns' => array(
			'id_member' => array(
				'header' => array(
					'value' => $txt['member_id'],
				),
				'data' => array(
					'db' => 'id_member',
				),
				'sort' => array(
					'default' => 'id_member',
					'reverse' => 'id_member DESC',
				),
			),
			'user_name' => array(
				'header' => array(
					'value' => $txt['username'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'member_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'member_name',
					'reverse' => 'member_name DESC',
				),
			),
			'email' => array(
				'header' => array(
					'value' => $txt['email_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'email_address' => true,
						),
					),
				),
				'sort' => array(
					'default' => 'email_address',
					'reverse' => 'email_address DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'member_ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'member_ip',
					'reverse' => 'member_ip DESC',
				),
			),
			'hostname' => array(
				'header' => array(
					'value' => $txt['hostname'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return host_from_ip(inet_dtop($rowData['member_ip']));
					},
					'class' => 'smalltext',
				),
			),
			'date_registered' => array(
				'header' => array(
					'value' => $context['current_filter'] == 4 ? $txt['viewmembers_online'] : $txt['date_registered'],
				),
				'data' => array(
					'function' => function($rowData) use ($context)
					{
						return timeformat($rowData['' . ($context['current_filter'] == 4 ? 'last_login' : 'date_registered') . '']);
					},
				),
				'sort' => array(
					'default' => $context['current_filter'] == 4 ? 'mem.last_login DESC' : 'date_registered DESC',
					'reverse' => $context['current_filter'] == 4 ? 'mem.last_login' : 'date_registered',
				),
			),
			'duplicates' => array(
				'header' => array(
					'value' => $txt['duplicates'],
					// Make sure it doesn't go too wide.
					'style' => 'width: 20%;',
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $txt)
					{
						$member_links = array();
						foreach ($rowData['duplicate_members'] as $member)
						{
							if ($member['id'])
								$member_links[] = '<a href="' . $scripturl . '?action=profile;u=' . $member['id'] . '" ' . (!empty($member['is_banned']) ? 'class="red"' : '') . '>' . $member['name'] . '</a>';
							else
								$member_links[] = $member['name'] . ' (' . $txt['guest'] . ')';
						}
						return implode(', ', $member_links);
					},
					'class' => 'smalltext',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="todoAction[]" value="%1$d">',
						'params' => array(
							'id_member' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'javascript' => $javascript,
		'form' => array(
			'href' => $scripturl . '?action=admin;area=viewmembers;sa=approve;type=' . $context['browse_type'],
			'name' => 'postForm',
			'include_start' => true,
			'include_sort' => true,
			'hidden_fields' => array(
				'orig_filter' => $context['current_filter'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					[<a href="' . $scripturl . '?action=admin;area=viewmembers;sa=browse;showdupes=' . ($context['show_duplicates'] ? 0 : 1) . ';type=' . $context['browse_type'] . (!empty($context['show_filter']) ? ';filter=' . $context['current_filter'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . ($context['show_duplicates'] ? $txt['dont_check_for_duplicate'] : $txt['check_for_duplicate']) . '</a>]
					<select name="todo" onchange="onSelectChange();">
						' . $allowed_actions . '
					</select>
					<noscript><input type="submit" value="' . $txt['go'] . '" class="button"><br class="clear_right"></noscript>
				',
				'class' => 'floatright',
			),
		),
	);

	// Pick what column to actually include if we're showing duplicates.
	if ($context['show_duplicates'])
		unset($listOptions['columns']['email']);
	else
		unset($listOptions['columns']['duplicates']);

	// Only show hostname on duplicates as it takes a lot of time.
	if (!$context['show_duplicates'] || !empty($modSettings['disableHostnameLookup']))
		unset($listOptions['columns']['hostname']);

	// Is there any need to show filters?
	if (isset($context['available_filters']) && count($context['available_filters']) > 1)
	{
		$filterOptions = '
			<strong>' . $txt['admin_browse_filter_by'] . ':</strong>
			<select name="filter" onchange="this.form.submit();">';
		foreach ($context['available_filters'] as $filter)
			$filterOptions .= '
				<option value="' . $filter['type'] . '"' . ($filter['selected'] ? ' selected' : '') . '>' . $filter['desc'] . ' - ' . $filter['amount'] . ' ' . ($filter['amount'] == 1 ? $txt['user'] : $txt['users']) . '</option>';
		$filterOptions .= '
			</select>
			<noscript><input type="submit" value="' . $txt['go'] . '" name="filter" class="button"></noscript>';
		$listOptions['additional_rows'][] = array(
			'position' => 'top_of_list',
			'value' => $filterOptions,
			'class' => 'righttext',
		);
	}

	// What about if we only have one filter, but it's not the "standard" filter - show them what they are looking at.
	if (!empty($context['show_filter']) && !empty($context['available_filters']))
		$listOptions['additional_rows'][] = array(
			'position' => 'above_column_headers',
			'value' => '<strong>' . $txt['admin_browse_filter_show'] . ':</strong> ' . ((isset($context['current_filter']) && isset($txt['admin_browse_filter_type_' . $context['current_filter']])) ? $txt['admin_browse_filter_type_' . $context['current_filter']] : $context['available_filters'][0]['desc']),
			'class' => 'filter_row generic_list_wrapper smalltext',
		);

	// Now that we have all the options, create the list.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);
}

/**
 * This function handles the approval, rejection, activation or deletion of members.
 * Called by ?action=admin;area=viewmembers;sa=approve.
 * Requires the moderate_forum permission.
 * Redirects to ?action=admin;area=viewmembers;sa=browse
 * with the same parameters as the calling page.
 */
function AdminApprove()
{
	global $scripturl, $modSettings, $sourcedir, $language, $user_info, $smcFunc;

	// First, check our session.
	checkSession();

	require_once($sourcedir . '/Subs-Post.php');

	// We also need to the login languages here - for emails.
	loadLanguage('Login');

	// Sort out where we are going...
	$current_filter = (int) $_REQUEST['orig_filter'];

	// If we are applying a filter do just that - then redirect.
	if (isset($_REQUEST['filter']) && $_REQUEST['filter'] != $_REQUEST['orig_filter'])
		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $_REQUEST['filter'] . ';start=' . $_REQUEST['start']);

	// Nothing to do?
	if (!isset($_POST['todoAction']) && !isset($_POST['time_passed']))
		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);

	// Are we dealing with members who have been waiting for > set amount of time?
	if (isset($_POST['time_passed']))
	{
		$timeBefore = time() - 86400 * (int) $_POST['time_passed'];
		$condition = '
			AND date_registered < {int:time_before}';
	}
	// Coming from checkboxes - validate the members passed through to us.
	else
	{
		$members = array();
		foreach ($_POST['todoAction'] as $id)
			$members[] = (int) $id;
		$condition = '
			AND id_member IN ({array_int:members})';
	}

	// Get information on each of the members, things that are important to us, like email address...
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, email_address, validation_code, lngfile
		FROM {db_prefix}members
		WHERE is_activated = {int:activated_status}' . $condition . '
		ORDER BY lngfile',
		array(
			'activated_status' => $current_filter,
			'time_before' => empty($timeBefore) ? 0 : $timeBefore,
			'members' => empty($members) ? array() : $members,
		)
	);

	$member_count = $smcFunc['db_num_rows']($request);

	// If no results then just return!
	if ($member_count == 0)
		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);

	$member_info = array();
	$members = array();
	// Fill the info array.
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$members[] = $row['id_member'];
		$member_info[] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'email' => $row['email_address'],
			'language' => empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'],
			'code' => $row['validation_code']
		);
	}
	$smcFunc['db_free_result']($request);

	// Are we activating or approving the members?
	if ($_POST['todo'] == 'ok' || $_POST['todo'] == 'okemail')
	{
		// Approve/activate this member.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET validation_code = {string:blank_string}, is_activated = {int:is_activated}
			WHERE is_activated = {int:activated_status}' . $condition,
			array(
				'is_activated' => 1,
				'time_before' => empty($timeBefore) ? 0 : $timeBefore,
				'members' => empty($members) ? array() : $members,
				'activated_status' => $current_filter,
				'blank_string' => '',
			)
		);

		// Do we have to let the integration code know about the activations?
		if (!empty($modSettings['integrate_activate']))
		{
			foreach ($member_info as $member)
				call_integration_hook('integrate_activate', array($member['username']));
		}

		// Check for email.
		if ($_POST['todo'] == 'okemail')
		{
			foreach ($member_info as $member)
			{
				$replacements = array(
					'NAME' => $member['name'],
					'USERNAME' => $member['username'],
					'PROFILELINK' => $scripturl . '?action=profile;u=' . $member['id'],
					'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
				);

				$emaildata = loadEmailTemplate('admin_approve_accept', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accapp' . $member['id'], $emaildata['is_html'], 0);
			}
		}
	}
	// Maybe we're sending it off for activation?
	elseif ($_POST['todo'] == 'require_activation')
	{
		require_once($sourcedir . '/Subs-Members.php');

		// We have to do this for each member I'm afraid.
		foreach ($member_info as $member)
		{
			// Generate a random activation code.
			$validation_code = generateValidationCode();

			// Set these members for activation - I know this includes two id_member checks but it's safer than bodging $condition ;).
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}members
				SET validation_code = {string:validation_code}, is_activated = {int:not_activated}
				WHERE is_activated = {int:activated_status}
					' . $condition . '
					AND id_member = {int:selected_member}',
				array(
					'not_activated' => 0,
					'activated_status' => $current_filter,
					'selected_member' => $member['id'],
					'validation_code' => $validation_code,
					'time_before' => empty($timeBefore) ? 0 : $timeBefore,
					'members' => empty($members) ? array() : $members,
				)
			);

			$replacements = array(
				'USERNAME' => $member['name'],
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $member['id'] . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $member['id'],
				'ACTIVATIONCODE' => $validation_code,
			);

			$emaildata = loadEmailTemplate('admin_approve_activation', $replacements, $member['language']);
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accact' . $member['id'], $emaildata['is_html'], 0);
		}
	}
	// Are we rejecting them?
	elseif ($_POST['todo'] == 'reject' || $_POST['todo'] == 'rejectemail')
	{
		require_once($sourcedir . '/Subs-Members.php');
		deleteMembers($members);

		// Send email telling them they aren't welcome?
		if ($_POST['todo'] == 'rejectemail')
		{
			foreach ($member_info as $member)
			{
				$replacements = array(
					'USERNAME' => $member['name'],
				);

				$emaildata = loadEmailTemplate('admin_approve_reject', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accrej', $emaildata['is_html'], 1);
			}
		}
	}
	// A simple delete?
	elseif ($_POST['todo'] == 'delete' || $_POST['todo'] == 'deleteemail')
	{
		require_once($sourcedir . '/Subs-Members.php');
		deleteMembers($members);

		// Send email telling them they aren't welcome?
		if ($_POST['todo'] == 'deleteemail')
		{
			foreach ($member_info as $member)
			{
				$replacements = array(
					'USERNAME' => $member['name'],
				);

				$emaildata = loadEmailTemplate('admin_approve_delete', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accdel', $emaildata['is_html'], 1);
			}
		}
	}
	// Remind them to activate their account?
	elseif ($_POST['todo'] == 'remind')
	{
		foreach ($member_info as $member)
		{
			$replacements = array(
				'USERNAME' => $member['name'],
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $member['id'] . ';code=' . $member['code'],
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $member['id'],
				'ACTIVATIONCODE' => $member['code'],
			);

			$emaildata = loadEmailTemplate('admin_approve_remind', $replacements, $member['language']);
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accrem' . $member['id'], $emaildata['is_html'], 1);
		}
	}

	// @todo current_language is never set, no idea what this is for. Remove?
	// Back to the user's language!
	if (isset($current_language) && $current_language != $user_info['language'])
	{
		loadLanguage('index');
		loadLanguage('ManageMembers');
	}

	// Log what we did?
	if (!empty($modSettings['modlog_enabled']) && in_array($_POST['todo'], array('ok', 'okemail', 'require_activation', 'remind')))
	{
		$log_action = $_POST['todo'] == 'remind' ? 'remind_member' : 'approve_member';

		require_once($sourcedir . '/Logging.php');
		foreach ($member_info as $member)
			logAction($log_action, array('member' => $member['id']), 'admin');
	}

	// Although updateStats *may* catch this, best to do it manually just in case (Doesn't always sort out unapprovedMembers).
	if (in_array($current_filter, array(3, 4, 5)))
		updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > $member_count ? $modSettings['unapprovedMembers'] - $member_count : 0)));

	// Update the member's stats. (but, we know the member didn't change their name.)
	updateStats('member', false);

	// If they haven't been deleted, update the post group statistics on them...
	if (!in_array($_POST['todo'], array('delete', 'deleteemail', 'reject', 'rejectemail', 'remind')))
		updateStats('postgroups', $members);

	redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);
}

/**
 * Nifty function to calculate the number of days ago a given date was.
 * Requires a unix timestamp as input, returns an integer.
 * Named in honour of Jeff Lewis, the original creator of...this function.
 *
 * @param int $old The timestamp of the old date
 * @return int The number of days since $old, based on the forum time
 */
function jeffsdatediff($old)
{
	// Get the current time as the user would see it...
	$forumTime = forum_time();

	// Calculate the seconds that have passed since midnight.
	$sinceMidnight = date('H', $forumTime) * 60 * 60 + date('i', $forumTime) * 60 + date('s', $forumTime);

	// Take the difference between the two times.
	$dis = time() - $old;

	// Before midnight?
	if ($dis < $sinceMidnight)
		return 0;
	else
		$dis -= $sinceMidnight;

	// Divide out the seconds in a day to get the number of days.
	return ceil($dis / (24 * 60 * 60));
}

/**
 * Fetches all the activation counts for ViewMembers.
 *
 */
function GetMemberActivationCounts()
{
	global $smcFunc, $context;

	// Get counts on every type of activation - for sections and filtering alike.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS total_members, is_activated
		FROM {db_prefix}members
		WHERE is_activated != {int:is_activated}
		GROUP BY is_activated',
		array(
			'is_activated' => 1,
		)
	);
	$context['activation_numbers'] = array();
	$context['awaiting_activation'] = 0;
	$context['awaiting_approval'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['activation_numbers'][$row['is_activated']] = $row['total_members'];
	$smcFunc['db_free_result']($request);

	foreach ($context['activation_numbers'] as $activation_type => $total_members)
	{
		if (in_array($activation_type, array(0, 2)))
			$context['awaiting_activation'] += $total_members;
		elseif (in_array($activation_type, array(3, 4, 5)))
			$context['awaiting_approval'] += $total_members;
	}

}

?>