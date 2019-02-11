<?php

/**
 * This file contains all the functions used for the ban center.
 *
 * @todo refactor as controller-model
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Ban center. The main entrance point for all ban center functions.
 * It is accesssed by ?action=admin;area=ban.
 * It choses a function based on the 'sa' parameter, like many others.
 * The default sub-action is BanList().
 * It requires the ban_members permission.
 * It initializes the admin tabs.
 *
 * @uses ManageBans template.
 */
function Ban()
{
	global $context, $txt, $scripturl;

	isAllowedTo('manage_bans');

	loadTemplate('ManageBans');

	$subActions = array(
		'add' => 'BanEdit',
		'browse' => 'BanBrowseTriggers',
		'edittrigger' => 'BanEditTrigger',
		'edit' => 'BanEdit',
		'list' => 'BanList',
		'log' => 'BanLog',
	);

	// Default the sub-action to 'view ban list'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'list';

	$context['page_title'] = $txt['ban_title'];
	$context['sub_action'] = $_REQUEST['sa'];

	// Tabs for browsing the different ban functions.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['ban_title'],
		'help' => 'ban_members',
		'description' => $txt['ban_description'],
		'tabs' => array(
			'list' => array(
				'description' => $txt['ban_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=list',
				'is_selected' => $_REQUEST['sa'] == 'list' || $_REQUEST['sa'] == 'edit' || $_REQUEST['sa'] == 'edittrigger',
			),
			'add' => array(
				'description' => $txt['ban_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=add',
				'is_selected' => $_REQUEST['sa'] == 'add',
			),
			'browse' => array(
				'description' => $txt['ban_trigger_browse_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=browse',
				'is_selected' => $_REQUEST['sa'] == 'browse',
			),
			'log' => array(
				'description' => $txt['ban_log_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=log',
				'is_selected' => $_REQUEST['sa'] == 'log',
				'is_last' => true,
			),
		),
	);

	call_integration_hook('integrate_manage_bans', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Shows a list of bans currently set.
 * It is accessed by ?action=admin;area=ban;sa=list.
 * It removes expired bans.
 * It allows sorting on different criteria.
 * It also handles removal of selected ban items.
 *
 * @uses the main ManageBans template.
 */
function BanList()
{
	global $txt, $context, $scripturl;
	global $user_info, $sourcedir, $modSettings;

	// User pressed the 'remove selection button'.
	if (!empty($_POST['removeBans']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		// Make sure every entry is a proper integer.
		array_map('intval', $_POST['remove']);

		// Unban them all!
		removeBanGroups($_POST['remove']);
		removeBanTriggers($_POST['remove']);

		// No more caching this ban!
		updateSettings(array('banLastUpdated' => time()));

		// Some members might be unbanned now. Update the members table.
		updateBanMembers();
	}

	// Create a date string so we don't overload them with date info.
	if (preg_match('~%[AaBbCcDdeGghjmuYy](?:[^%]*%[AaBbCcDdeGghjmuYy])*~', $user_info['time_format'], $matches) == 0 || empty($matches[0]))
		$context['ban_time_format'] = $user_info['time_format'];
	else
		$context['ban_time_format'] = $matches[0];

	$listOptions = array(
		'id' => 'ban_list',
		'title' => $txt['ban_title'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=ban;sa=list',
		'default_sort_col' => 'added',
		'default_sort_dir' => 'desc',
		'get_items' => array(
			'function' => 'list_getBans',
		),
		'get_count' => array(
			'function' => 'list_getNumBans',
		),
		'no_items_label' => $txt['ban_no_entries'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['ban_name'],
				),
				'data' => array(
					'db' => 'name',
				),
				'sort' => array(
					'default' => 'bg.name',
					'reverse' => 'bg.name DESC',
				),
			),
			'notes' => array(
				'header' => array(
					'value' => $txt['ban_notes'],
				),
				'data' => array(
					'db' => 'notes',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'LENGTH(bg.notes) > 0 DESC, bg.notes',
					'reverse' => 'LENGTH(bg.notes) > 0, bg.notes DESC',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['ban_reason'],
				),
				'data' => array(
					'db' => 'reason',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'LENGTH(bg.reason) > 0 DESC, bg.reason',
					'reverse' => 'LENGTH(bg.reason) > 0, bg.reason DESC',
				),
			),
			'added' => array(
				'header' => array(
					'value' => $txt['ban_added'],
				),
				'data' => array(
					'function' => function($rowData) use ($context)
					{
						return timeformat($rowData['ban_time'], empty($context['ban_time_format']) ? true : $context['ban_time_format']);
					},
				),
				'sort' => array(
					'default' => 'bg.ban_time',
					'reverse' => 'bg.ban_time DESC',
				),
			),
			'expires' => array(
				'header' => array(
					'value' => $txt['ban_expires'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						// This ban never expires...whahaha.
						if ($rowData['expire_time'] === null)
							return $txt['never'];

						// This ban has already expired.
						elseif ($rowData['expire_time'] < time())
							return sprintf('<span class="red">%1$s</span>', $txt['ban_expired']);

						// Still need to wait a few days for this ban to expire.
						else
							return sprintf('%1$d&nbsp;%2$s', ceil(($rowData['expire_time'] - time()) / (60 * 60 * 24)), $txt['ban_days']);
					},
				),
				'sort' => array(
					'default' => 'COALESCE(bg.expire_time, 1=1) DESC, bg.expire_time DESC',
					'reverse' => 'COALESCE(bg.expire_time, 1=1), bg.expire_time',
				),
			),
			'num_triggers' => array(
				'header' => array(
					'value' => $txt['ban_triggers'],
				),
				'data' => array(
					'db' => 'num_triggers',
				),
				'sort' => array(
					'default' => 'num_triggers DESC',
					'reverse' => 'num_triggers',
				),
			),
			'actions' => array(
				'header' => array(
					'value' => $txt['ban_actions'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">' . $txt['modify'] . '</a>',
						'params' => array(
							'id_ban_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
						'params' => array(
							'id_ban_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=ban;sa=list',
		),
		'additional_rows' => array(
			array(
				'position' => 'top_of_list',
				'value' => '<input type="submit" name="removeBans" value="' . $txt['ban_remove_selected'] . '" class="button">',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="removeBans" value="' . $txt['ban_remove_selected'] . '" class="button">',
			),
		),
		'javascript' => '
		var removeBans = $("input[name=\'removeBans\']");

		removeBans.on( "click", function(e) {
			var removeItems = $("input[name=\'remove[]\']:checked").length;

			if (removeItems == 0)
			{
				e.preventDefault();
				return alert("' . $txt['select_item_check'] . '");
			}


			return confirm("' . $txt['ban_remove_selected_confirm'] . '");
		});',
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'ban_list';
}

/**
 * Get bans, what else? For the given options.
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string telling ORDER BY how to sort the results
 * @return array An array of information about the bans for the list
 */
function list_getBans($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, COUNT(bi.id_ban) AS num_triggers
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
		GROUP BY bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$bans = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$bans[] = $row;

	$smcFunc['db_free_result']($request);

	return $bans;
}

/**
 * Get the total number of ban from the ban group table
 *
 * @return int The total number of bans
 */
function list_getNumBans()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS num_bans
		FROM {db_prefix}ban_groups',
		array(
		)
	);
	list ($numBans) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $numBans;
}

/**
 * This function is behind the screen for adding new bans and modifying existing ones.
 * Adding new bans:
 * 	- is accessed by ?action=admin;area=ban;sa=add.
 * 	- uses the ban_edit sub template of the ManageBans template.
 * Modifying existing bans:
 *  - is accessed by ?action=admin;area=ban;sa=edit;bg=x
 *  - uses the ban_edit sub template of the ManageBans template.
 *  - shows a list of ban triggers for the specified ban.
 */
function BanEdit()
{
	global $txt, $modSettings, $context, $scripturl, $smcFunc, $sourcedir;

	if ((isset($_POST['add_ban']) || isset($_POST['modify_ban']) || isset($_POST['remove_selection'])) && empty($context['ban_errors']))
		BanEdit2();

	$ban_group_id = isset($context['ban']['id']) ? $context['ban']['id'] : (isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0);

	// Template needs this to show errors using javascript
	loadLanguage('Errors');
	createToken('admin-bet');
	$context['form_url'] = $scripturl . '?action=admin;area=ban;sa=edit';

	if (!empty($context['ban_errors']))
		foreach ($context['ban_errors'] as $error)
			$context['error_messages'][$error] = $txt[$error];

	else
	{
		// If we're editing an existing ban, get it from the database.
		if (!empty($ban_group_id))
		{
			$context['ban_group_id'] = $ban_group_id;

			// We're going to want this for making our list.
			require_once($sourcedir . '/Subs-List.php');

			$listOptions = array(
				'id' => 'ban_items',
				'base_href' => $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $ban_group_id,
				'no_items_label' => $txt['ban_no_triggers'],
				'items_per_page' => $modSettings['defaultMaxListItems'],
				'get_items' => array(
					'function' => 'list_getBanItems',
					'params' => array(
						'ban_group_id' => $ban_group_id,
					),
				),
				'get_count' => array(
					'function' => 'list_getNumBanItems',
					'params' => array(
						'ban_group_id' => $ban_group_id,
					),
				),
				'columns' => array(
					'type' => array(
						'header' => array(
							'value' => $txt['ban_banned_entity'],
							'style' => 'width: 60%;text-align: left;',
						),
						'data' => array(
							'function' => function($ban_item) use ($txt)
							{
								if (in_array($ban_item['type'], array('ip', 'hostname', 'email')))
									return '<strong>' . $txt[$ban_item['type']] . ':</strong>&nbsp;' . $ban_item[$ban_item['type']];
								elseif ($ban_item['type'] == 'user')
									return '<strong>' . $txt['username'] . ':</strong>&nbsp;' . $ban_item['user']['link'];
								else
									return '<strong>' . $txt['unknown'] . ':</strong>&nbsp;' . $ban_item['no_bantype_selected'];
							},
							'style' => 'text-align: left;',
						),
					),
					'hits' => array(
						'header' => array(
							'value' => $txt['ban_hits'],
							'style' => 'width: 15%; text-align: center;',
						),
						'data' => array(
							'db' => 'hits',
							'style' => 'text-align: center;',
						),
					),
					'id' => array(
						'header' => array(
							'value' => $txt['ban_actions'],
							'style' => 'width: 15%; text-align: center;',
						),
						'data' => array(
							'function' => function($ban_item) use ($txt, $context, $scripturl)
							{
								return '<a href="' . $scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $context['ban_group_id'] . ';bi=' . $ban_item['id'] . '">' . $txt['ban_edit_trigger'] . '</a>';
							},
							'style' => 'text-align: center;',
						),
					),
					'checkboxes' => array(
						'header' => array(
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form, \'ban_items\');">',
							'style' => 'width: 5%; text-align: center;',
						),
						'data' => array(
							'sprintf' => array(
								'format' => '<input type="checkbox" name="ban_items[]" value="%1$d">',
								'params' => array(
									'id' => false,
								),
							),
							'style' => 'text-align: center;',
						),
					),
				),
				'form' => array(
					'href' => $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $ban_group_id,
				),
				'additional_rows' => array(
					array(
						'position' => 'above_table_headers',
						'value' => '
						<input type="submit" name="remove_selection" value="' . $txt['ban_remove_selected_triggers'] . '" class="button"> <a class="button" href="' . $scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $ban_group_id . '">' . $txt['ban_add_trigger'] . '</a>',
						'style' => 'text-align: right;',
					),
					array(
						'position' => 'above_table_headers',
						'value' => '
						<input type="hidden" name="bg" value="' . $ban_group_id . '">
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
						<input type="hidden" name="' . $context['admin-bet_token_var'] . '" value="' . $context['admin-bet_token'] . '">',
					),
					array(
						'position' => 'below_table_data',
						'value' => '
						<input type="submit" name="remove_selection" value="' . $txt['ban_remove_selected_triggers'] . '" class="button"> <a class="button" href="' . $scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $ban_group_id . '">' . $txt['ban_add_trigger'] . '</a>',
						'style' => 'text-align: right;',
					),
					array(
						'position' => 'below_table_data',
						'value' => '
						<input type="hidden" name="bg" value="' . $ban_group_id . '">
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
						<input type="hidden" name="' . $context['admin-bet_token_var'] . '" value="' . $context['admin-bet_token'] . '">',
					),
				),
				'javascript' => '
		var removeBans = $("input[name=\'remove_selection\']");

		removeBans.on( "click", function(e) {
			var removeItems = $("input[name=\'ban_items[]\']:checked").length;

			if (removeItems == 0)
			{
				e.preventDefault();
				return alert("' . $txt['select_item_check'] . '");
			}


			return confirm("' . $txt['ban_remove_selected_confirm'] . '");
		});',
			);
			createList($listOptions);
		}
		// Not an existing one, then it's probably a new one.
		else
		{
			$context['ban'] = array(
				'id' => 0,
				'name' => '',
				'expiration' => array(
					'status' => 'never',
					'days' => 0
				),
				'reason' => '',
				'notes' => '',
				'ban_days' => 0,
				'cannot' => array(
					'access' => true,
					'post' => false,
					'register' => false,
					'login' => false,
				),
				'is_new' => true,
			);
			$context['ban_suggestions'] = array(
				'main_ip' => '',
				'hostname' => '',
				'email' => '',
				'member' => array(
					'id' => 0,
				),
			);

			// Overwrite some of the default form values if a user ID was given.
			if (!empty($_REQUEST['u']))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member, real_name, member_ip, email_address
					FROM {db_prefix}members
					WHERE id_member = {int:current_user}
					LIMIT 1',
					array(
						'current_user' => (int) $_REQUEST['u'],
					)
				);
				if ($smcFunc['db_num_rows']($request) > 0)
				{
					list ($context['ban_suggestions']['member']['id'], $context['ban_suggestions']['member']['name'], $context['ban_suggestions']['main_ip'], $context['ban_suggestions']['email']) = $smcFunc['db_fetch_row']($request);
					$context['ban_suggestions']['main_ip'] = inet_dtop($context['ban_suggestions']['main_ip']);
				}
				$smcFunc['db_free_result']($request);

				if (!empty($context['ban_suggestions']['member']['id']))
				{
					$context['ban_suggestions']['href'] = $scripturl . '?action=profile;u=' . $context['ban_suggestions']['member']['id'];
					$context['ban_suggestions']['member']['link'] = '<a href="' . $context['ban_suggestions']['href'] . '">' . $context['ban_suggestions']['member']['name'] . '</a>';

					// Default the ban name to the name of the banned member.
					$context['ban']['name'] = $context['ban_suggestions']['member']['name'];
					// @todo: there should be a better solution...used to lock the "Ban on Username" input when banning from profile
					$context['ban']['from_user'] = true;

					// Would be nice if we could also ban the hostname.
					if ((preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $context['ban_suggestions']['main_ip']) == 1 || isValidIPv6($context['ban_suggestions']['main_ip'])) && empty($modSettings['disableHostnameLookup']))
						$context['ban_suggestions']['hostname'] = host_from_ip($context['ban_suggestions']['main_ip']);

					$context['ban_suggestions']['other_ips'] = banLoadAdditionalIPs($context['ban_suggestions']['member']['id']);
				}
			}

			// We come from the mod center.
			elseif (isset($_GET['msg']) && !empty($_GET['msg']))
			{
				$request = $smcFunc['db_query']('', '
					SELECT poster_name, poster_ip, poster_email
					FROM {db_prefix}messages
					WHERE id_msg = {int:message}
					LIMIT 1',
					array(
						'message' => (int) $_REQUEST['msg'],
					)
				);
				if ($smcFunc['db_num_rows']($request) > 0)
				{
					list ($context['ban_suggestions']['member']['name'], $context['ban_suggestions']['main_ip'], $context['ban_suggestions']['email']) = $smcFunc['db_fetch_row']($request);
					$context['ban_suggestions']['main_ip'] = inet_dtop($context['ban_suggestions']['main_ip']);
				}
				$smcFunc['db_free_result']($request);

				// Can't hurt to ban base on the guest name...
				$context['ban']['name'] = $context['ban_suggestions']['member']['name'];
				$context['ban']['from_user'] = true;
			}
		}
	}

	loadJavaScriptFile('suggest.js', array('minimize' => true), 'smf_suggest');
	$context['sub_template'] = 'ban_edit';

}

/**
 * Retrieves all the ban items belonging to a certain ban group
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param int $sort Not used here
 * @param int $ban_group_id The ID of the group to get the bans for
 * @return array An array with information about the returned ban items
 */
function list_getBanItems($start = 0, $items_per_page = 0, $sort = 0, $ban_group_id = 0)
{
	global $context, $smcFunc, $scripturl;

	$ban_items = array();
	$request = $smcFunc['db_query']('', '
		SELECT
			bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
			bi.ip_low, bi.ip_high,
			bg.id_ban_group, bg.name, bg.ban_time, COALESCE(bg.expire_time, 0) AS expire_time, bg.reason, bg.notes, bg.cannot_access, bg.cannot_register, bg.cannot_login, bg.cannot_post,
			COALESCE(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bg.id_ban_group = {int:current_ban}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'current_ban' => $ban_group_id,
			'start' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('ban_not_found', false);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['ban']))
		{
			$context['ban'] = array(
				'id' => $row['id_ban_group'],
				'name' => $row['name'],
				'expiration' => array(
					'status' => empty($row['expire_time']) ? 'never' : ($row['expire_time'] < time() ? 'expired' : 'one_day'),
					'days' => $row['expire_time'] > time() ? ($row['expire_time'] - time() < 86400 ? 1 : ceil(($row['expire_time'] - time()) / 86400)) : 0
				),
				'reason' => $row['reason'],
				'notes' => $row['notes'],
				'cannot' => array(
					'access' => !empty($row['cannot_access']),
					'post' => !empty($row['cannot_post']),
					'register' => !empty($row['cannot_register']),
					'login' => !empty($row['cannot_login']),
				),
				'is_new' => false,
				'hostname' => '',
				'email' => '',
			);
		}

		if (!empty($row['id_ban']))
		{
			$ban_items[$row['id_ban']] = array(
				'id' => $row['id_ban'],
				'hits' => $row['hits'],
			);
			if (!empty($row['ip_high']))
			{
				$ban_items[$row['id_ban']]['type'] = 'ip';
				$ban_items[$row['id_ban']]['ip'] = range2ip($row['ip_low'], $row['ip_high']);
			}
			elseif (!empty($row['hostname']))
			{
				$ban_items[$row['id_ban']]['type'] = 'hostname';
				$ban_items[$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);
			}
			elseif (!empty($row['email_address']))
			{
				$ban_items[$row['id_ban']]['type'] = 'email';
				$ban_items[$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);
			}
			elseif (!empty($row['id_member']))
			{
				$ban_items[$row['id_ban']]['type'] = 'user';
				$ban_items[$row['id_ban']]['user'] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				);
			}
			// Invalid ban (member probably doesn't exist anymore).
			else
			{
				unset($ban_items[$row['id_ban']]);
				removeBanTriggers($row['id_ban']);
			}
		}
	}
	$smcFunc['db_free_result']($request);

	return $ban_items;
}

/**
 * Gets the number of ban items belonging to a certain ban group
 *
 * @return int The number of ban items
 */
function list_getNumBanItems()
{
	global $smcFunc, $context;

	$ban_group_id = isset($context['ban_group_id']) ? $context['ban_group_id'] : 0;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(bi.id_ban)
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bg.id_ban_group = {int:current_ban}',
		array(
			'current_ban' => $ban_group_id,
		)
	);
	list($banNumber) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $banNumber;
}

/**
 * Finds additional IPs related to a certain user
 *
 * @param int $member_id The ID of the member to get additional IPs for
 * @return array An containing two arrays - ips_in_messages (IPs used in posts) and ips_in_errors (IPs used in error messages)
 */
function banLoadAdditionalIPs($member_id)
{
	// Borrowing a few language strings from profile.
	loadLanguage('Profile');

	$search_list = array();
	call_integration_hook('integrate_load_addtional_ip_ban', array(&$search_list));
	$search_list += array('ips_in_messages' => 'banLoadAdditionalIPsMember', 'ips_in_errors' => 'banLoadAdditionalIPsError');

	$return = array();
	foreach ($search_list as $key => $callable)
		if (is_callable($callable))
			$return[$key] = call_user_func($callable, $member_id);

	return $return;
}

/**
 * @param int $member_id The ID of the member
 * @return array An array of IPs used in posts by this member
 */
function banLoadAdditionalIPsMember($member_id)
{
	global $smcFunc;

	// Find some additional IP's used by this member.
	$message_ips = array();
	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_user}
			AND poster_ip IS NOT NULL
		ORDER BY poster_ip',
		array(
			'current_user' => $member_id,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$message_ips[] = inet_dtop($row['poster_ip']);

	$smcFunc['db_free_result']($request);

	return $message_ips;
}

/**
 * @param int $member_id The ID of the member
 * @return array An array of IPs associated with error messages generated by this user
 */
function banLoadAdditionalIPsError($member_id)
{
	global $smcFunc;

	$error_ips = array();
	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_user}
			AND ip IS NOT NULL
		ORDER BY ip',
		array(
			'current_user' => $member_id,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$error_ips[] = inet_dtop($row['ip']);

	$smcFunc['db_free_result']($request);

	return $error_ips;
}

/**
 * This function handles submitted forms that add, modify or remove ban triggers.
 */
function banEdit2()
{
	global $smcFunc, $context;

	checkSession();
	validateToken('admin-bet');

	$context['ban_errors'] = array();

	// Adding or editing a ban group
	if (isset($_POST['add_ban']) || isset($_POST['modify_ban']))
	{
		// Let's collect all the information we need
		$ban_info['id'] = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
		$ban_info['is_new'] = empty($ban_info['id']);
		$ban_info['expire_date'] = !empty($_POST['expire_date']) ? (int) $_POST['expire_date'] : 0;
		$ban_info['expiration'] = array(
			'status' => isset($_POST['expiration']) && in_array($_POST['expiration'], array('never', 'one_day', 'expired')) ? $_POST['expiration'] : 'never',
			'days' => $ban_info['expire_date'],
		);
		$ban_info['db_expiration'] = $ban_info['expiration']['status'] == 'never' ? 'NULL' : ($ban_info['expiration']['status'] == 'one_day' ? time() + 24 * 60 * 60 * $ban_info['expire_date'] : 0);
		$ban_info['full_ban'] = empty($_POST['full_ban']) ? 0 : 1;
		$ban_info['reason'] = !empty($_POST['reason']) ? $smcFunc['htmlspecialchars']($_POST['reason'], ENT_QUOTES) : '';
		$ban_info['name'] = !empty($_POST['ban_name']) ? $smcFunc['htmlspecialchars']($_POST['ban_name'], ENT_QUOTES) : '';
		$ban_info['notes'] = isset($_POST['notes']) ? $smcFunc['htmlspecialchars']($_POST['notes'], ENT_QUOTES) : '';
		$ban_info['notes'] = str_replace(array("\r", "\n", '  '), array('', '<br>', '&nbsp; '), $ban_info['notes']);
		$ban_info['cannot']['access'] = empty($ban_info['full_ban']) ? 0 : 1;
		$ban_info['cannot']['post'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_post']) ? 0 : 1;
		$ban_info['cannot']['register'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_register']) ? 0 : 1;
		$ban_info['cannot']['login'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_login']) ? 0 : 1;

		// Adding a new ban group
		if (empty($_REQUEST['bg']))
			$ban_group_id = insertBanGroup($ban_info);
		// Editing an existing ban group
		else
			$ban_group_id = updateBanGroup($ban_info);

		if (is_numeric($ban_group_id))
		{
			$ban_info['id'] = $ban_group_id;
			$ban_info['is_new'] = false;
		}

		$context['ban'] = $ban_info;
	}

	if (isset($_POST['ban_suggestions']))
		// @TODO: is $_REQUEST['bi'] ever set?
		$saved_triggers = saveTriggers($_POST['ban_suggestions'], $ban_info['id'], isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0, isset($_REQUEST['bi']) ? (int) $_REQUEST['bi'] : 0);

	// Something went wrong somewhere... Oh well, let's go back.
	if (!empty($context['ban_errors']))
	{
		$context['ban_suggestions'] = !empty($saved_triggers) ? $saved_triggers : array();
		$context['ban']['from_user'] = true;
		$context['ban_suggestions'] = array_merge($context['ban_suggestions'], getMemberData((int) $_REQUEST['u']));

		// Not strictly necessary, but it's nice
		if (!empty($context['ban_suggestions']['member']['id']))
			$context['ban_suggestions']['other_ips'] = banLoadAdditionalIPs($context['ban_suggestions']['member']['id']);
		return BanEdit();
	}
	$context['ban_suggestions']['saved_triggers'] = !empty($saved_triggers) ? $saved_triggers : array();

	if (isset($_POST['ban_items']))
	{
		$ban_group_id = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
		array_map('intval', $_POST['ban_items']);

		removeBanTriggers($_POST['ban_items'], $ban_group_id);
	}

	// Register the last modified date.
	updateSettings(array('banLastUpdated' => time()));

	// Update the member table to represent the new ban situation.
	updateBanMembers();
	redirectexit('action=admin;area=ban;sa=edit;bg=' . $ban_group_id);
}

/**
 * Saves one or more ban triggers into a ban item: according to the suggestions
 * checks the $_POST variable to verify if the trigger is present
 *
 * @param array $suggestions An array of suggestedtriggers (IP, email, etc.)
 * @param int $ban_group The ID of the group we're saving bans for
 * @param int $member The ID of the member associated with this ban (if applicable)
 * @param int $ban_id The ID of the ban (0 if this is a new ban)
 *
 * @return array|bool An array with the triggers if there were errors or false on success
 */
function saveTriggers($suggestions = array(), $ban_group, $member = 0, $ban_id = 0)
{
	global $context;

	$triggers = array(
		'main_ip' => '',
		'hostname' => '',
		'email' => '',
		'member' => array(
			'id' => $member,
		)
	);

	foreach ($suggestions as $key => $value)
	{
		if (is_array($value))
			$triggers[$key] = $value;
		else
			$triggers[$value] = !empty($_POST[$value]) ? $_POST[$value] : '';
	}

	$ban_triggers = validateTriggers($triggers);

	// Time to save!
	if (!empty($ban_triggers['ban_triggers']) && empty($context['ban_errors']))
	{
		if (empty($ban_id))
			addTriggers($ban_group, $ban_triggers['ban_triggers'], $ban_triggers['log_info']);
		else
			updateTriggers($ban_id, $ban_group, array_shift($ban_triggers['ban_triggers']), $ban_triggers['log_info']);
	}
	if (!empty($context['ban_errors']))
		return $triggers;
	else
		return false;
}

/**
 * This function removes a bunch of triggers based on ids
 * Doesn't clean the inputs
 *
 * @param array $items_ids The items to remove
 * @param bool|int $group_id The ID of the group these triggers are associated with or false if deleting them from all groups
 * @return bool Always returns true
 */
function removeBanTriggers($items_ids = array(), $group_id = false)
{
	global $smcFunc, $scripturl;

	if ($group_id !== false)
		$group_id = (int) $group_id;

	if (empty($group_id) && empty($items_ids))
		return false;

	if (!is_array($items_ids))
		$items_ids = array($items_ids);

	$log_info = array();
	$ban_items = array();

	// First order of business: Load up the info so we can log this...
	$request = $smcFunc['db_query']('', '
		SELECT
			bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
			bi.ip_low, bi.ip_high,
			COALESCE(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
		FROM {db_prefix}ban_items AS bi
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bi.id_ban IN ({array_int:ban_list})',
		array(
			'ban_list' => $items_ids,
		)
	);

	// Get all the info for the log
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!empty($row['id_ban']))
		{
			$ban_items[$row['id_ban']] = array(
				'id' => $row['id_ban'],
			);
			if (!empty($row['ip_high']))
			{
				$ban_items[$row['id_ban']]['type'] = 'ip';
				$ban_items[$row['id_ban']]['ip'] = range2ip($row['ip_low'], $row['ip_high']);

				$is_range = (strpos($ban_items[$row['id_ban']]['ip'], '-') !== false || strpos($ban_items[$row['id_ban']]['ip'], '*') !== false);

				$log_info[] = array(
					'bantype' => ($is_range ? 'ip_range' : 'main_ip'),
					'value' => $ban_items[$row['id_ban']]['ip'],
				);
			}
			elseif (!empty($row['hostname']))
			{
				$ban_items[$row['id_ban']]['type'] = 'hostname';
				$ban_items[$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);
				$log_info[] = array(
					'bantype' => 'hostname',
					'value' => $row['hostname'],
				);
			}
			elseif (!empty($row['email_address']))
			{
				$ban_items[$row['id_ban']]['type'] = 'email';
				$ban_items[$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);
				$log_info[] = array(
					'bantype' => 'email',
					'value' => $ban_items[$row['id_ban']]['email'],
				);
			}
			elseif (!empty($row['id_member']))
			{
				$ban_items[$row['id_ban']]['type'] = 'user';
				$ban_items[$row['id_ban']]['user'] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				);
				$log_info[] = array(
					'bantype' => 'user',
					'value' => $row['id_member'],
				);
			}
		}
	}

	// Log this!
	logTriggersUpdates($log_info, false, true);

	$smcFunc['db_free_result']($request);

	if ($group_id !== false)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban IN ({array_int:ban_list})
				AND id_ban_group = {int:ban_group}',
			array(
				'ban_list' => $items_ids,
				'ban_group' => $group_id,
			)
		);
	}
	elseif (!empty($items_ids))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban IN ({array_int:ban_list})',
			array(
				'ban_list' => $items_ids,
			)
		);
	}

	return true;
}

/**
 * This function removes a bunch of ban groups based on ids
 * Doesn't clean the inputs
 *
 * @param int[] $group_ids The IDs of the groups to remove
 * @return bool Returns ture if successful or false if $group_ids is empty
 */
function removeBanGroups($group_ids)
{
	global $smcFunc;

	if (!is_array($group_ids))
		$group_ids = array($group_ids);

	$group_ids = array_unique($group_ids);

	if (empty($group_ids))
		return false;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ban_groups
		WHERE id_ban_group IN ({array_int:ban_list})',
		array(
			'ban_list' => $group_ids,
		)
	);

	return true;
}

/**
 * Removes logs - by default truncate the table
 * Doesn't clean the inputs
 *
 * @param array $ids Empty array to clear the ban log or the IDs of the log entries to remove
 * @return bool Returns true if successful or false if $ids is invalid
 */
function removeBanLogs($ids = array())
{
	global $smcFunc;

	if (empty($ids))
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_banned',
			array(
			)
		);
	else
	{
		if (!is_array($ids))
			$ids = array($ids);

		$ids = array_unique($ids);

		if (empty($ids))
			return false;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_banned
			WHERE id_ban_log IN ({array_int:ban_list})',
			array(
				'ban_list' => $ids,
			)
		);
	}

	return true;
}

/**
 * This function validates the ban triggers
 *
 * Errors in $context['ban_errors']
 *
 * @param array $triggers The triggers to validate
 * @return array An array of riggers and log info ready to be used
 */
function validateTriggers(&$triggers)
{
	global $context, $smcFunc;

	if (empty($triggers))
		$context['ban_erros'][] = 'ban_empty_triggers';

	$ban_triggers = array();
	$log_info = array();

	foreach ($triggers as $key => $value)
	{
		if (!empty($value))
		{
			if ($key == 'member')
				continue;

			if ($key == 'main_ip')
			{
				$value = trim($value);
				$ip_parts = ip2range($value);
				if (!checkExistingTriggerIP($ip_parts, $value))
					$context['ban_erros'][] = 'invalid_ip';
				else
				{
					$ban_triggers['main_ip'] = array(
						'ip_low' => $ip_parts['low'],
						'ip_high' => $ip_parts['high']
					);
				}
			}
			elseif ($key == 'hostname')
			{
				if (preg_match('/[^\w.\-*]/', $value) == 1)
					$context['ban_erros'][] = 'invalid_hostname';
				else
				{
					// Replace the * wildcard by a MySQL wildcard %.
					$value = substr(str_replace('*', '%', $value), 0, 255);

					$ban_triggers['hostname']['hostname'] = $value;
				}
			}
			elseif ($key == 'email')
			{
				if (preg_match('/[^\w.\-\+*@]/', $value) == 1)
					$context['ban_erros'][] = 'invalid_email';

				// Check the user is not banning an admin.
				$request = $smcFunc['db_query']('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
						AND email_address LIKE {string:email}
					LIMIT 1',
					array(
						'admin_group' => 1,
						'email' => $value,
					)
				);
				if ($smcFunc['db_num_rows']($request) != 0)
					$context['ban_erros'][] = 'no_ban_admin';
				$smcFunc['db_free_result']($request);

				$value = substr(strtolower(str_replace('*', '%', $value)), 0, 255);

				$ban_triggers['email']['email_address'] = $value;
			}
			elseif ($key == 'user')
			{
				$user = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $smcFunc['htmlspecialchars']($value, ENT_QUOTES));

				$request = $smcFunc['db_query']('', '
					SELECT id_member, (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0) AS isAdmin
					FROM {db_prefix}members
					WHERE member_name = {string:username} OR real_name = {string:username}
					LIMIT 1',
					array(
						'admin_group' => 1,
						'username' => $user,
					)
				);
				if ($smcFunc['db_num_rows']($request) == 0)
					$context['ban_erros'][] = 'invalid_username';
				list ($value, $isAdmin) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				if ($isAdmin && strtolower($isAdmin) != 'f')
				{
					unset($value);
					$context['ban_erros'][] = 'no_ban_admin';
				}
				else
					$ban_triggers['user']['id_member'] = $value;
			}
			elseif (in_array($key, array('ips_in_messages', 'ips_in_errors')))
			{
				// Special case, those two are arrays themselves
				$values = array_unique($value);
				// Don't add the main IP again.
				if (isset($triggers['main_ip']))
					$values = array_diff($values, array($triggers['main_ip']));
				unset($value);
				foreach ($values as $val)
				{
					$val = trim($val);
					$ip_parts = ip2range($val);
					if (!checkExistingTriggerIP($ip_parts, $val))
						$context['ban_erros'][] = 'invalid_ip';
					else
					{
						$ban_triggers[$key][] = array(
							'ip_low' => $ip_parts['low'],
							'ip_high' => $ip_parts['high'],
						);

						$log_info[] = array(
							'value' => $val,
							'bantype' => 'ip_range',
						);
					}
				}
			}
			else
				$context['ban_erros'][] = 'no_bantype_selected';

			if (isset($value) && !is_array($value))
				$log_info[] = array(
					'value' => $value,
					'bantype' => $key,
				);
		}
	}
	return array('ban_triggers' => $ban_triggers, 'log_info' => $log_info);
}

/**
 * This function actually inserts the ban triggers into the database
 *
 * Errors in $context['ban_errors']
 *
 * @param int $group_id The ID of the group to add the triggers to (0 to create a new one)
 * @param array $triggers The triggers to add
 * @param array $logs The log data
 * @return bool Whether or not the action was successful
 */
function addTriggers($group_id = 0, $triggers = array(), $logs = array())
{
	global $smcFunc, $context;

	if (empty($group_id))
		$context['ban_errors'][] = 'ban_id_empty';

	// Preset all values that are required.
	$values = array(
		'id_ban_group' => $group_id,
		'hostname' => '',
		'email_address' => '',
		'id_member' => 0,
		'ip_low' => 'null',
		'ip_high' => 'null',
	);

	$insertKeys = array(
		'id_ban_group' => 'int',
		'hostname' => 'string',
		'email_address' => 'string',
		'id_member' => 'int',
		'ip_low' => 'inet',
		'ip_high' => 'inet',
	);

	$insertTriggers = array();
	foreach ($triggers as $key => $trigger)
	{
		// Exceptions, exceptions, exceptions...always exceptions... :P
		if (in_array($key, array('ips_in_messages', 'ips_in_errors')))
			foreach ($trigger as $real_trigger)
				$insertTriggers[] = array_merge($values, $real_trigger);
		else
			$insertTriggers[] = array_merge($values, $trigger);
	}

	if (empty($insertTriggers))
		$context['ban_errors'][] = 'ban_no_triggers';

	if (!empty($context['ban_errors']))
		return false;

	$smcFunc['db_insert']('',
		'{db_prefix}ban_items',
		$insertKeys,
		$insertTriggers,
		array('id_ban')
	);

	logTriggersUpdates($logs, true);

	return true;
}

/**
 * This function updates an existing ban trigger into the database
 *
 * Errors in $context['ban_errors']
 *
 * @param int $ban_item The ID of the ban item
 * @param int $group_id The ID of the ban group
 * @param array $trigger An array of triggers
 * @param array $logs An array of log info
 */
function updateTriggers($ban_item = 0, $group_id = 0, $trigger = array(), $logs = array())
{
	global $smcFunc, $context;

	if (empty($ban_item))
		$context['ban_errors'][] = 'ban_ban_item_empty';
	if (empty($group_id))
		$context['ban_errors'][] = 'ban_id_empty';
	if (empty($trigger))
		$context['ban_errors'][] = 'ban_no_triggers';

	if (!empty($context['ban_errors']))
		return;

	// Preset all values that are required.
	$values = array(
		'id_ban_group' => $group_id,
		'hostname' => '',
		'email_address' => '',
		'id_member' => 0,
		'ip_low' => 'null',
		'ip_high' => 'null',
	);

	$trigger = array_merge($values, $trigger);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}ban_items
		SET
			hostname = {string:hostname}, email_address = {string:email_address}, id_member = {int:id_member},
			ip_low = {inet:ip_low}, ip_high = {inet:ip_high}
		WHERE id_ban = {int:ban_item}
			AND id_ban_group = {int:id_ban_group}',
		array_merge($trigger, array(
			'id_ban_group' => $group_id,
			'ban_item' => $ban_item,
		))
	);

	logTriggersUpdates($logs, false);
}

/**
 * A small function to unify logging of triggers (updates and new)
 *
 * @param array $logs an array of logs, each log contains the following keys:
 *                - bantype: a known type of ban (ip_range, hostname, email, user, main_ip)
 *                - value: the value of the bantype (e.g. the IP or the email address banned)
 * @param bool $new Whether the trigger is new or an update of an existing one
 * @param bool $removal Whether the trigger is being deleted
 */
function logTriggersUpdates($logs, $new = true, $removal = false)
{
	if (empty($logs))
		return;

	$log_name_map = array(
		'main_ip' => 'ip_range',
		'hostname' => 'hostname',
		'email' => 'email',
		'user' => 'member',
		'ip_range' => 'ip_range',
	);

	// Log the addion of the ban entries into the moderation log.
	foreach ($logs as $log)
		logAction('ban' . ($removal == true ? 'remove' : ''), array(
			$log_name_map[$log['bantype']] => $log['value'],
			'new' => empty($new) ? 0 : 1,
			'remove' => empty($removal) ? 0 : 1,
			'type' => $log['bantype'],
		));
}

/**
 * Updates an existing ban group
 *
 * Errors in $context['ban_errors']
 *
 * @param array $ban_info An array of info about the ban group. Should have name and may also have an id.
 * @return int The ban group's ID
 */
function updateBanGroup($ban_info = array())
{
	global $smcFunc, $context;

	if (empty($ban_info['name']))
		$context['ban_errors'][] = 'ban_name_empty';
	if (empty($ban_info['id']))
		$context['ban_errors'][] = 'ban_id_empty';
	if (empty($ban_info['cannot']['access']) && empty($ban_info['cannot']['register']) && empty($ban_info['cannot']['post']) && empty($ban_info['cannot']['login']))
		$context['ban_errors'][] = 'ban_unknown_restriction_type';

	if (!empty($ban_info['id']))
	{
		// Verify the ban group exists.
		$request = $smcFunc['db_query']('', '
			SELECT id_ban_group
			FROM {db_prefix}ban_groups
			WHERE id_ban_group = {int:ban_group}
			LIMIT 1',
			array(
				'ban_group' => $ban_info['id']
			)
		);

		if ($smcFunc['db_num_rows']($request) == 0)
			$context['ban_errors'][] = 'ban_not_found';
		$smcFunc['db_free_result']($request);
	}

	if (!empty($ban_info['name']))
	{
		// Make sure the name does not already exist (Of course, if it exists in the ban group we are editing, proceed.)
		$request = $smcFunc['db_query']('', '
			SELECT id_ban_group
			FROM {db_prefix}ban_groups
			WHERE name = {string:new_ban_name}
				AND id_ban_group != {int:ban_group}
			LIMIT 1',
			array(
				'ban_group' => empty($ban_info['id']) ? 0 : $ban_info['id'],
				'new_ban_name' => $ban_info['name'],
			)
		);
		if ($smcFunc['db_num_rows']($request) != 0)
			$context['ban_errors'][] = 'ban_name_exists';
		$smcFunc['db_free_result']($request);
	}

	if (!empty($context['ban_errors']))
		return $ban_info['id'];

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}ban_groups
		SET
			name = {string:ban_name},
			reason = {string:reason},
			notes = {string:notes},
			expire_time = {raw:expiration},
			cannot_access = {int:cannot_access},
			cannot_post = {int:cannot_post},
			cannot_register = {int:cannot_register},
			cannot_login = {int:cannot_login}
		WHERE id_ban_group = {int:id_ban_group}',
		array(
			'expiration' => $ban_info['db_expiration'],
			'cannot_access' => $ban_info['cannot']['access'],
			'cannot_post' => $ban_info['cannot']['post'],
			'cannot_register' => $ban_info['cannot']['register'],
			'cannot_login' => $ban_info['cannot']['login'],
			'id_ban_group' => $ban_info['id'],
			'ban_name' => $ban_info['name'],
			'reason' => $ban_info['reason'],
			'notes' => $ban_info['notes'],
		)
	);

	return $ban_info['id'];
}

/**
 * Creates a new ban group
 * If the group is successfully created the ID is returned
 * On error the error code is returned or false
 *
 * Errors in $context['ban_errors']
 *
 * @param array $ban_info An array containing 'name', which is the name of the ban group
 * @return int The ban group's ID
 */
function insertBanGroup($ban_info = array())
{
	global $smcFunc, $context;

	if (empty($ban_info['name']))
		$context['ban_errors'][] = 'ban_name_empty';
	if (empty($ban_info['cannot']['access']) && empty($ban_info['cannot']['register']) && empty($ban_info['cannot']['post']) && empty($ban_info['cannot']['login']))
		$context['ban_errors'][] = 'ban_unknown_restriction_type';

	if (!empty($ban_info['name']))
	{
		// Check whether a ban with this name already exists.
		$request = $smcFunc['db_query']('', '
			SELECT id_ban_group
			FROM {db_prefix}ban_groups
			WHERE name = {string:new_ban_name}' . '
			LIMIT 1',
			array(
				'new_ban_name' => $ban_info['name'],
			)
		);

		if ($smcFunc['db_num_rows']($request) == 1)
			$context['ban_errors'][] = 'ban_name_exists';
		$smcFunc['db_free_result']($request);
	}

	if (!empty($context['ban_errors']))
		return;

	// Yes yes, we're ready to add now.
	$ban_info['id'] = $smcFunc['db_insert']('',
		'{db_prefix}ban_groups',
		array(
			'name' => 'string-20', 'ban_time' => 'int', 'expire_time' => 'raw', 'cannot_access' => 'int', 'cannot_register' => 'int',
			'cannot_post' => 'int', 'cannot_login' => 'int', 'reason' => 'string-255', 'notes' => 'string-65534',
		),
		array(
			$ban_info['name'], time(), $ban_info['db_expiration'], $ban_info['cannot']['access'], $ban_info['cannot']['register'],
			$ban_info['cannot']['post'], $ban_info['cannot']['login'], $ban_info['reason'], $ban_info['notes'],
		),
		array('id_ban_group'),
		1
	);

	if (empty($ban_info['id']))
		$context['ban_errors'][] = 'impossible_insert_new_bangroup';

	return $ban_info['id'];
}

/**
 * This function handles the ins and outs of the screen for adding new ban
 * triggers or modifying existing ones.
 * Adding new ban triggers:
 * 	- is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x
 * 	- uses the ban_edit_trigger sub template of ManageBans.
 * Editing existing ban triggers:
 *  - is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x;bi=y
 *  - uses the ban_edit_trigger sub template of ManageBans.
 */
function BanEditTrigger()
{
	global $context, $smcFunc, $scripturl;

	$context['sub_template'] = 'ban_edit_trigger';
	$context['form_url'] = $scripturl . '?action=admin;area=ban;sa=edittrigger';

	$ban_group = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
	$ban_id = isset($_REQUEST['bi']) ? (int) $_REQUEST['bi'] : 0;

	if (empty($ban_group))
		fatal_lang_error('ban_not_found', false);

	if (isset($_POST['add_new_trigger']) && !empty($_POST['ban_suggestions']))
	{
		saveTriggers($_POST['ban_suggestions'], $ban_group, 0, $ban_id);
		redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
	}
	elseif (isset($_POST['edit_trigger']) && !empty($_POST['ban_suggestions']))
	{
		// The first replaces the old one, the others are added new (simplification, otherwise it would require another query and some work...)
		saveTriggers(array_shift($_POST['ban_suggestions']), $ban_group, 0, $ban_id);
		if (!empty($_POST['ban_suggestions']))
			saveTriggers($_POST['ban_suggestions'], $ban_group);

		redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
	}
	elseif (isset($_POST['edit_trigger']))
	{
		removeBanTriggers($ban_id);
		redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
	}

	loadJavaScriptFile('suggest.js', array('minimize' => true), 'smf_suggest');

	if (empty($ban_id))
	{
		$context['ban_trigger'] = array(
			'id' => 0,
			'group' => $ban_group,
			'ip' => array(
				'value' => '',
				'selected' => true,
			),
			'hostname' => array(
				'selected' => false,
				'value' => '',
			),
			'email' => array(
				'value' => '',
				'selected' => false,
			),
			'banneduser' => array(
				'value' => '',
				'selected' => false,
			),
			'is_new' => true,
		);
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				bi.id_ban, bi.id_ban_group, bi.hostname, bi.email_address, bi.id_member,
				bi.ip_low, bi.ip_high,
				mem.member_name, mem.real_name
			FROM {db_prefix}ban_items AS bi
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bi.id_ban = {int:ban_item}
				AND bi.id_ban_group = {int:ban_group}
			LIMIT 1',
			array(
				'ban_item' => $ban_id,
				'ban_group' => $ban_group,
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('ban_not_found', false);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$context['ban_trigger'] = array(
			'id' => $row['id_ban'],
			'group' => $row['id_ban_group'],
			'ip' => array(
				'value' => empty($row['ip_low']) ? '' : range2ip($row['ip_low'], $row['ip_high']),
				'selected' => !empty($row['ip_low']),
			),
			'hostname' => array(
				'value' => str_replace('%', '*', $row['hostname']),
				'selected' => !empty($row['hostname']),
			),
			'email' => array(
				'value' => str_replace('%', '*', $row['email_address']),
				'selected' => !empty($row['email_address'])
			),
			'banneduser' => array(
				'value' => $row['member_name'],
				'selected' => !empty($row['member_name'])
			),
			'is_new' => false,
		);
	}

	createToken('admin-bet');
}

/**
 * This handles the screen for showing the banned entities
 * It is accessed by ?action=admin;area=ban;sa=browse
 * It uses sub-tabs for browsing by IP, hostname, email or username.
 *
 * @uses ManageBans template, browse_triggers sub template.
 */
function BanBrowseTriggers()
{
	global $modSettings, $context, $scripturl, $smcFunc, $txt;
	global $sourcedir, $settings;

	if (!empty($_POST['remove_triggers']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		removeBanTriggers($_POST['remove']);

		// Rehabilitate some members.
		if ($_REQUEST['entity'] == 'member')
			updateBanMembers();

		// Make sure the ban cache is refreshed.
		updateSettings(array('banLastUpdated' => time()));
	}

	$context['selected_entity'] = isset($_REQUEST['entity']) && in_array($_REQUEST['entity'], array('ip', 'hostname', 'email', 'member')) ? $_REQUEST['entity'] : 'ip';

	$listOptions = array(
		'id' => 'ban_trigger_list',
		'title' => $txt['ban_trigger_browse'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=' . $context['selected_entity'],
		'default_sort_col' => 'banned_entity',
		'no_items_label' => $txt['ban_no_triggers'],
		'get_items' => array(
			'function' => 'list_getBanTriggers',
			'params' => array(
				$context['selected_entity'],
			),
		),
		'get_count' => array(
			'function' => 'list_getNumBanTriggers',
			'params' => array(
				$context['selected_entity'],
			),
		),
		'columns' => array(
			'banned_entity' => array(
				'header' => array(
					'value' => $txt['ban_banned_entity'],
				),
			),
			'ban_name' => array(
				'header' => array(
					'value' => $txt['ban_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">%2$s</a>',
						'params' => array(
							'id_ban_group' => false,
							'name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'bg.name',
					'reverse' => 'bg.name DESC',
				),
			),
			'hits' => array(
				'header' => array(
					'value' => $txt['ban_hits'],
				),
				'data' => array(
					'db' => 'hits',
				),
				'sort' => array(
					'default' => 'bi.hits DESC',
					'reverse' => 'bi.hits',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
						'params' => array(
							'id_ban' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=' . $context['selected_entity'],
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'above_column_headers',
				'value' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=ip">' . ($context['selected_entity'] == 'ip' ? '<img src="' . $settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . $txt['ip'] . '</a>&nbsp;|&nbsp;<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=hostname">' . ($context['selected_entity'] == 'hostname' ? '<img src="' . $settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . $txt['hostname'] . '</a>&nbsp;|&nbsp;<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=email">' . ($context['selected_entity'] == 'email' ? '<img src="' . $settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . $txt['email'] . '</a>&nbsp;|&nbsp;<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=member">' . ($context['selected_entity'] == 'member' ? '<img src="' . $settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . $txt['username'] . '</a>',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="remove_triggers" value="' . $txt['ban_remove_selected_triggers'] . '" data-confirm="' . $txt['ban_remove_selected_triggers_confirm'] . '" class="button you_sure">',
			),
		),
	);

	// Specific data for the first column depending on the selected entity.
	if ($context['selected_entity'] === 'ip')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => function($rowData)
			{
				return range2ip(
					$rowData['ip_low']
					,
					$rowData['ip_high']
				);
			},
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.ip_low, bi.ip_high, bi.ip_low',
			'reverse' => 'bi.ip_low DESC, bi.ip_high DESC',
		);
	}
	elseif ($context['selected_entity'] === 'hostname')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => function($rowData) use ($smcFunc)
			{
				return strtr($smcFunc['htmlspecialchars']($rowData['hostname']), array('%' => '*'));
			},
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.hostname',
			'reverse' => 'bi.hostname DESC',
		);
	}
	elseif ($context['selected_entity'] === 'email')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => function($rowData) use ($smcFunc)
			{
				return strtr($smcFunc['htmlspecialchars']($rowData['email_address']), array('%' => '*'));
			},
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.email_address',
			'reverse' => 'bi.email_address DESC',
		);
	}
	elseif ($context['selected_entity'] === 'member')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'sprintf' => array(
				'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
				'params' => array(
					'id_member' => false,
					'real_name' => false,
				),
			),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'mem.real_name',
			'reverse' => 'mem.real_name DESC',
		);
	}

	// Create the list.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// The list is the only thing to show, so make it the default sub template.
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'ban_trigger_list';
}

/**
 * Get ban triggers for the given parameters. Callback from $listOptions['get_items'] in BanBrowseTriggers()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string telling ORDER BY how to sort the results
 * @param string $trigger_type The trigger type - can be 'ip', 'hostname' or 'email'
 * @return array An array of ban trigger info for the list
 */
function list_getBanTriggers($start, $items_per_page, $sort, $trigger_type)
{
	global $smcFunc;

	$where = array(
		'ip' => 'bi.ip_low is not null',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	$request = $smcFunc['db_query']('', '
		SELECT
			bi.id_ban, bi.ip_low, bi.ip_high, bi.hostname, bi.email_address, bi.hits,
			bg.id_ban_group, bg.name' . ($trigger_type === 'member' ? ',
			mem.id_member, mem.real_name' : '') . '
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]) . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'blank_string' => '',
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		)
	);
	$ban_triggers = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ban_triggers[] = $row;
	$smcFunc['db_free_result']($request);

	return $ban_triggers;
}

/**
 * This returns the total number of ban triggers of the given type. Callback for $listOptions['get_count'] in BanBrowseTriggers().
 *
 * @param string $trigger_type The trigger type. Can be 'ip', 'hostname' or 'email'
 * @return int The number of triggers of the specified type
 */
function list_getNumBanTriggers($trigger_type)
{
	global $smcFunc;

	$where = array(
		'ip' => 'bi.ip_low is not null',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}ban_items AS bi' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]),
		array(
			'blank_string' => '',
		)
	);
	list ($num_triggers) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_triggers;
}

/**
 * This handles the listing of ban log entries, and allows their deletion.
 * Shows a list of logged access attempts by banned users.
 * It is accessed by ?action=admin;area=ban;sa=log.
 * How it works:
 *  - allows sorting of several columns.
 *  - also handles deletion of (a selection of) log entries.
 */
function BanLog()
{
	global $scripturl, $context, $sourcedir, $txt, $modSettings;

	// Delete one or more entries.
	if (!empty($_POST['removeAll']) || (!empty($_POST['removeSelected']) && !empty($_POST['remove'])))
	{
		checkSession();
		validateToken('admin-bl');

		// 'Delete all entries' button was pressed.
		if (!empty($_POST['removeAll']))
			removeBanLogs();
		// 'Delete selection' button was pressed.
		else
		{
			array_map('intval', $_POST['remove']);
			removeBanLogs($_POST['remove']);
		}
	}

	$listOptions = array(
		'id' => 'ban_log',
		'title' => $txt['ban_log'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $context['admin_area'] == 'ban' ? $scripturl . '?action=admin;area=ban;sa=log' : $scripturl . '?action=admin;area=logs;sa=banlog',
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getBanLogEntries',
		),
		'get_count' => array(
			'function' => 'list_getNumBanLogEntries',
		),
		'no_items_label' => $txt['ban_log_no_entries'],
		'columns' => array(
			'ip' => array(
				'header' => array(
					'value' => $txt['ban_log_ip'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'lb.ip',
					'reverse' => 'lb.ip DESC',
				),
			),
			'email' => array(
				'header' => array(
					'value' => $txt['ban_log_email'],
				),
				'data' => array(
					'db_htmlsafe' => 'email',
				),
				'sort' => array(
					'default' => 'lb.email = \'\', lb.email',
					'reverse' => 'lb.email != \'\', lb.email DESC',
				),
			),
			'member' => array(
				'header' => array(
					'value' => $txt['ban_log_member'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'real_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'COALESCE(mem.real_name, 1=1), mem.real_name',
					'reverse' => 'COALESCE(mem.real_name, 1=1) DESC, mem.real_name DESC',
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['ban_log_date'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return timeformat($rowData['log_time']);
					},
				),
				'sort' => array(
					'default' => 'lb.log_time DESC',
					'reverse' => 'lb.log_time',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
						'params' => array(
							'id_ban_log' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $context['admin_area'] == 'ban' ? $scripturl . '?action=admin;area=ban;sa=log' : $scripturl . '?action=admin;area=logs;sa=banlog',
			'include_start' => true,
			'include_sort' => true,
			'token' => 'admin-bl',
		),
		'additional_rows' => array(
			array(
				'position' => 'top_of_list',
				'value' => '
					<input type="submit" name="removeSelected" value="' . $txt['ban_log_remove_selected'] . '" data-confirm="' . $txt['ban_log_remove_selected_confirm'] . '" class="button you_sure">
					<input type="submit" name="removeAll" value="' . $txt['ban_log_remove_all'] . '" data-confirm="' . $txt['ban_log_remove_all_confirm'] . '" class="button you_sure">',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="removeSelected" value="' . $txt['ban_log_remove_selected'] . '" data-confirm="' . $txt['ban_log_remove_selected_confirm'] . '" class="button you_sure">
					<input type="submit" name="removeAll" value="' . $txt['ban_log_remove_all'] . '" data-confirm="' . $txt['ban_log_remove_all_confirm'] . '" class="button you_sure">',
			),
		),
	);

	createToken('admin-bl');

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['page_title'] = $txt['ban_log'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'ban_log';
}

/**
 * Load a list of ban log entries from the database.
 * (no permissions check). Callback for $listOptions['get_items'] in BanLog()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string telling ORDER BY how to sort the results
 * @return array An array of info about the ban log entries for the list.
 */
function list_getBanLogEntries($start, $items_per_page, $sort)
{
	global $smcFunc;

	$dash = '-';

	$request = $smcFunc['db_query']('', '
		SELECT lb.id_ban_log, lb.id_member, lb.ip AS ip, COALESCE(lb.email, {string:dash}) AS email, lb.log_time, COALESCE(mem.real_name, {string:blank_string}) AS real_name
		FROM {db_prefix}log_banned AS lb
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items}',
		array(
			'blank_string' => '',
			'dash' => $dash,
			'sort' => $sort,
			'start' => $start,
			'items' => $items_per_page,
		)
	);
	$log_entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['ip'] = $row['ip'] === null ? $dash : inet_dtop($row['ip']);
		$log_entries[] = $row;
	}
	$smcFunc['db_free_result']($request);

	return $log_entries;
}

/**
 * This returns the total count of ban log entries. Callback for $listOptions['get_count'] in BanLog().
 *
 * @return int The total number of ban log entries.
 */
function list_getNumBanLogEntries()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_banned AS lb',
		array(
		)
	);
	list ($num_entries) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_entries;
}

/**
 * Convert a range of given IP number into a single string.
 * It's practically the reverse function of ip2range().
 *
 * @example
 * range2ip(array(10, 10, 10, 0), array(10, 10, 20, 255)) returns '10.10.10-20.*
 *
 * @param array $low The low end of the range in IPv4 format
 * @param array $high The high end of the range in IPv4 format
 * @return string A string indicating the range
 */
function range2ip($low, $high)
{
	$low = inet_dtop($low);
	$high = inet_dtop($high);

	if ($low == '255.255.255.255') return 'unknown';
	if ($low == $high)
		return $low;
	else
		return $low . '-' . $high;
}

/**
 * Checks whether a given IP range already exists in the trigger list.
 * If yes, it returns an error message. Otherwise, it returns an array
 *  optimized for the database.
 *
 * @param array $ip_array An array of IP trigger data
 * @param string $fullip The full IP
 * @return boolean|array False if the trigger array is invalid or the passed array if the value doesn't exist in the database
 */
function checkExistingTriggerIP($ip_array, $fullip = '')
{
	global $smcFunc, $scripturl;

	$values = array(
		'ip_low' => $ip_array['low'],
		'ip_high' => $ip_array['high']
	);

	$request = $smcFunc['db_query']('', '
		SELECT bg.id_ban_group, bg.name
		FROM {db_prefix}ban_groups AS bg
		INNER JOIN {db_prefix}ban_items AS bi ON
			(bi.id_ban_group = bg.id_ban_group)
			AND ip_low = {inet:ip_low} AND ip_high = {inet:ip_high}
		LIMIT 1',
		$values
	);
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		list ($error_id_ban, $error_ban_name) = $smcFunc['db_fetch_row']($request);
		fatal_lang_error('ban_trigger_already_exists', false, array(
			$fullip,
			'<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $error_id_ban . '">' . $error_ban_name . '</a>',
		));
	}
	$smcFunc['db_free_result']($request);

	return $values;
}

/**
 * As it says... this tries to review the list of banned members, to match new bans.
 * Note: is_activated >= 10: a member is banned.
 */
function updateBanMembers()
{
	global $smcFunc;

	$updates = array();
	$allMembers = array();
	$newMembers = array();

	// Start by getting all active bans - it's quicker doing this in parts...
	$request = $smcFunc['db_query']('', '
		SELECT bi.id_member, bi.email_address
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE (bi.id_member > {int:no_member} OR bi.email_address != {string:blank_string})
			AND bg.cannot_access = {int:cannot_access_on}
			AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})',
		array(
			'no_member' => 0,
			'cannot_access_on' => 1,
			'current_time' => time(),
			'blank_string' => '',
		)
	);
	$memberIDs = array();
	$memberEmails = array();
	$memberEmailWild = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_member'])
			$memberIDs[$row['id_member']] = $row['id_member'];
		if ($row['email_address'])
		{
			// Does it have a wildcard - if so we can't do a IN on it.
			if (strpos($row['email_address'], '%') !== false)
				$memberEmailWild[$row['email_address']] = $row['email_address'];
			else
				$memberEmails[$row['email_address']] = $row['email_address'];
		}
	}
	$smcFunc['db_free_result']($request);

	// Build up the query.
	$queryPart = array();
	$queryValues = array();
	if (!empty($memberIDs))
	{
		$queryPart[] = 'mem.id_member IN ({array_string:member_ids})';
		$queryValues['member_ids'] = $memberIDs;
	}
	if (!empty($memberEmails))
	{
		$queryPart[] = 'mem.email_address IN ({array_string:member_emails})';
		$queryValues['member_emails'] = $memberEmails;
	}
	$count = 0;
	foreach ($memberEmailWild as $email)
	{
		$queryPart[] = 'mem.email_address LIKE {string:wild_' . $count . '}';
		$queryValues['wild_' . $count++] = $email;
	}

	// Find all banned members.
	if (!empty($queryPart))
	{
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.is_activated
			FROM {db_prefix}members AS mem
			WHERE ' . implode(' OR ', $queryPart),
			$queryValues
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!in_array($row['id_member'], $allMembers))
			{
				$allMembers[] = $row['id_member'];
				// Do they need an update?
				if ($row['is_activated'] < 10)
				{
					$updates[($row['is_activated'] + 10)][] = $row['id_member'];
					$newMembers[] = $row['id_member'];
				}
			}
		}
		$smcFunc['db_free_result']($request);
	}

	// We welcome our new members in the realm of the banned.
	if (!empty($newMembers))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member IN ({array_int:new_banned_members})',
			array(
				'new_banned_members' => $newMembers,
			)
		);

	// Find members that are wrongfully marked as banned.
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.is_activated - 10 AS new_value
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_member = mem.id_member OR mem.email_address LIKE bi.email_address)
			LEFT JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND bg.cannot_access = {int:cannot_access_activated} AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
		WHERE (bi.id_ban IS NULL OR bg.id_ban_group IS NULL)
			AND mem.is_activated >= {int:ban_flag}',
		array(
			'cannot_access_activated' => 1,
			'current_time' => time(),
			'ban_flag' => 10,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't do this twice!
		if (!in_array($row['id_member'], $allMembers))
		{
			$updates[$row['new_value']][] = $row['id_member'];
			$allMembers[] = $row['id_member'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (!empty($updates))
		foreach ($updates as $newStatus => $members)
			updateMemberData($members, array('is_activated' => $newStatus));

	// Update the latest member and our total members as banning may change them.
	updateStats('member');
}

/**
 * Gets basic member data for the ban
 *
 * @param int $id The ID of the member to get data for
 * @return array An aray containing the ID, name, main IP and email address of the specified user
 */
function getMemberData($id)
{
	global $smcFunc;

	$suggestions = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, member_ip, email_address
		FROM {db_prefix}members
		WHERE id_member = {int:current_user}
		LIMIT 1',
		array(
			'current_user' => $id,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		list ($suggestions['member']['id'], $suggestions['member']['name'], $suggestions['main_ip'], $suggestions['email']) = $smcFunc['db_fetch_row']($request);
		$suggestions['main_ip'] = inet_dtop($suggestions['main_ip']);
	}
	$smcFunc['db_free_result']($request);

	return $suggestions;
}

?>