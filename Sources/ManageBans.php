<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/* This file contains all the functions used for the ban center.

	void Ban()
		- the main entrance point for all ban center functions.
		- is accesssed by ?action=admin;area=ban.
		- choses a function based on the 'sa' parameter.
		- defaults to BanList().
		- requires the ban_members permission.
		- initializes the admin tabs.
		- load the ManageBans template.

	void BanList()
		- shows a list of bans currently set.
		- is accesssed by ?action=admin;area=ban;sa=list.
		- uses the main ManageBans template.
		- removes expired bans.
		- allows sorting on different criteria.
		- also handles removal of selected ban items.

	void BanEdit()
		- the screen for adding new bans and modifying existing ones.
		- adding new bans:
			- is accesssed by ?action=admin;area=ban;sa=add.
			- uses the ban_edit sub template of the ManageBans template.
		- modifying existing bans:
			- is accesssed by ?action=admin;area=ban;sa=edit;bg=x
			- uses the ban_edit sub template of the ManageBans template.
			- shows a list of ban triggers for the specified ban.
		- handles submitted forms that add, modify or remove ban triggers.

	void BanEditTrigger()
		- the screen for adding new ban triggers or modifying existing ones.
		- adding new ban triggers:
			- is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x
			- uses the ban_edit_trigger sub template of ManageBans.
		- editing existing ban triggers:
			- is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x;bi=y
			- uses the ban_edit_trigger sub template of ManageBans.

	void BanBrowseTriggers()
		- screen for showing the banned enities
		- is accessed by ?action=admin;area=ban;sa=browse
		- uses the browse_triggers sub template of the ManageBans template.
		- uses sub-tabs for browsing by IP, hostname, email or username.

	array BanLog()
		- show a list of logged access attempts by banned users.
		- is accessed by ?action=admin;area=ban;sa=log.
		- allows sorting of several columns.
		- also handles deletion of (a selection of) log entries.

	string range2ip(array $low, array $high)
		- reverse function of ip2range().
		- converts a given array of IP numbers to a single string
		- range2ip(array(10, 10, 10, 0), array(10, 10, 20, 255)) returns
		   '10.10.10-20.*

	array checkExistingTriggerIP(array $ip_array, string $fullip)
		- checks whether a given IP range already exists in the trigger list.
		- if yes, it returns an error message. Otherwise, it returns
		  an array optimized for the database.

	void updateBanMembers()
		- updates the members table to match the new bans.
		- is_activated >= 10: a member is banned.
*/

// Ban center.
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

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

// List all the bans.
function BanList()
{
	global $txt, $context, $ban_request, $ban_counts, $scripturl;
	global $user_info, $smcFunc, $sourcedir;

	// User pressed the 'remove selection button'.
	if (!empty($_POST['removeBans']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		// Make sure every entry is a proper integer.
		foreach ($_POST['remove'] as $index => $ban_id)
			$_POST['remove'][(int) $index] = (int) $ban_id;

		// Unban them all!
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ban_groups
			WHERE id_ban_group IN ({array_int:ban_list})',
			array(
				'ban_list' => $_POST['remove'],
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban_group IN ({array_int:ban_list})',
			array(
				'ban_list' => $_POST['remove'],
			)
		);

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
		'items_per_page' => 20,
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
					'function' => create_function('$rowData', '
						global $context;

						return timeformat($rowData[\'ban_time\'], empty($context[\'ban_time_format\']) ? true : $context[\'ban_time_format\']);
					'),
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
					'function' => create_function('$rowData', '
						global $txt;

						// This ban never expires...whahaha.
						if ($rowData[\'expire_time\'] === null)
							return $txt[\'never\'];

						// This ban has already expired.
						elseif ($rowData[\'expire_time\'] < time())
							return sprintf(\'<span style="color: red">%1$s</span>\', $txt[\'ban_expired\']);

						// Still need to wait a few days for this ban to expire.
						else
							return sprintf(\'%1$d&nbsp;%2$s\', ceil(($rowData[\'expire_time\'] - time()) / (60 * 60 * 24)), $txt[\'ban_days\']);
					'),
				),
				'sort' => array(
					'default' => 'IFNULL(bg.expire_time, 1=1) DESC, bg.expire_time DESC',
					'reverse' => 'IFNULL(bg.expire_time, 1=1), bg.expire_time',
				),
			),
			'num_triggers' => array(
				'header' => array(
					'value' => $txt['ban_triggers'],
				),
				'data' => array(
					'db' => 'num_triggers',
					'style' => 'text-align: center;',
				),
				'sort' => array(
					'default' => 'num_triggers DESC',
					'reverse' => 'num_triggers',
				),
			),
			'actions' => array(
				'header' => array(
					'value' => $txt['ban_actions'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">' . $txt['modify'] . '</a>',
						'params' => array(
							'id_ban_group' => false,
						),
					),
					'style' => 'text-align: center;',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_ban_group' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=ban;sa=list',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="removeBans" value="' . $txt['ban_remove_selected'] . '" onclick="return confirm(\'' . $txt['ban_remove_selected_confirm'] . '\');" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'ban_list';
}

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

function BanEdit()
{
	global $txt, $modSettings, $context, $ban_request, $scripturl, $smcFunc;

	$_REQUEST['bg'] = empty($_REQUEST['bg']) ? 0 : (int) $_REQUEST['bg'];

	// Adding or editing a ban trigger?
	if (!empty($_POST['add_new_trigger']) || !empty($_POST['edit_trigger']))
	{
		checkSession();

		$newBan = !empty($_POST['add_new_trigger']);
		$values = array(
			'id_ban_group' => $_REQUEST['bg'],
			'hostname' => '',
			'email_address' => '',
			'id_member' => 0,
			'ip_low1' => 0,
			'ip_high1' => 0,
			'ip_low2' => 0,
			'ip_high2' => 0,
			'ip_low3' => 0,
			'ip_high3' => 0,
			'ip_low4' => 0,
			'ip_high4' => 0,
		);

		// Preset all values that are required.
		if ($newBan)
		{
			$insertKeys = array(
				'id_ban_group' => 'int',
				'hostname' => 'string',
				'email_address' => 'string',
				'id_member' => 'int',
				'ip_low1' => 'int',
				'ip_high1' => 'int',
				'ip_low2' => 'int',
				'ip_high2' => 'int',
				'ip_low3' => 'int',
				'ip_high3' => 'int',
				'ip_low4' => 'int',
				'ip_high4' => 'int',
			);
		}
		else
			$updateString = '
				hostname = {string:hostname}, email_address = {string:email_address}, id_member = {int:id_member},
				ip_low1 = {int:ip_low1}, ip_high1 = {int:ip_high1},
				ip_low2 = {int:ip_low2}, ip_high2 = {int:ip_high2},
				ip_low3 = {int:ip_low3}, ip_high3 = {int:ip_high3},
				ip_low4 = {int:ip_low4}, ip_high4 = {int:ip_high4}';

		if ($_POST['bantype'] == 'ip_ban')
		{
			$ip = trim($_POST['ip']);
			$ip_parts = ip2range($ip);
			$ip_check = checkExistingTriggerIP($ip_parts, $ip);
			if (!$ip_check)
				fatal_lang_error('invalid_ip', false);
			$values = array_merge($values, $ip_check);

			$modlogInfo['ip_range'] = $_POST['ip'];
		}
		elseif ($_POST['bantype'] == 'hostname_ban')
		{
			if (preg_match('/[^\w.\-*]/', $_POST['hostname']) == 1)
				fatal_lang_error('invalid_hostname', false);

			// Replace the * wildcard by a MySQL compatible wildcard %.
			$_POST['hostname'] = str_replace('*', '%', $_POST['hostname']);

			$values['hostname'] = $_POST['hostname'];

			$modlogInfo['hostname'] = $_POST['hostname'];
		}
		elseif ($_POST['bantype'] == 'email_ban')
		{
			if (preg_match('/[^\w.\-\+*@]/', $_POST['email']) == 1)
				fatal_lang_error('invalid_email', false);
			$_POST['email'] = strtolower(str_replace('*', '%', $_POST['email']));

			// Check the user is not banning an admin.
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND email_address LIKE {string:email}
				LIMIT 1',
				array(
					'admin_group' => 1,
					'email' => $_POST['email'],
				)
			);
			if ($smcFunc['db_num_rows']($request) != 0)
				fatal_lang_error('no_ban_admin', 'critical');
			$smcFunc['db_free_result']($request);

			$values['email_address'] = $_POST['email'];

			$modlogInfo['email'] = $_POST['email'];
		}
		elseif ($_POST['bantype'] == 'user_ban')
		{
			$_POST['user'] = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $smcFunc['htmlspecialchars']($_POST['user'], ENT_QUOTES));

			$request = $smcFunc['db_query']('', '
				SELECT id_member, (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0) AS isAdmin
				FROM {db_prefix}members
				WHERE member_name = {string:user_name} OR real_name = {string:user_name}
				LIMIT 1',
				array(
					'admin_group' => 1,
					'user_name' => $_POST['user'],
				)
			);
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('invalid_username', false);
			list ($memberid, $isAdmin) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if ($isAdmin && $isAdmin != 'f')
				fatal_lang_error('no_ban_admin', 'critical');

			$values['id_member'] = $memberid;

			$modlogInfo['member'] = $memberid;
		}
		else
			fatal_lang_error('no_bantype_selected', false);

		if ($newBan)
			$smcFunc['db_insert']('',
				'{db_prefix}ban_items',
				$insertKeys,
				$values,
				array('id_ban')
			);
		else
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}ban_items
				SET ' . $updateString . '
				WHERE id_ban = {int:ban_item}
					AND id_ban_group = {int:id_ban_group}',
				array_merge($values, array(
					'ban_item' => (int) $_REQUEST['bi'],
				))
			);

		// Log the addion of the ban entry into the moderation log.
		logAction('ban', $modlogInfo + array(
			'new' => $newBan,
			'type' => $_POST['bantype'],
		));

		// Register the last modified date.
		updateSettings(array('banLastUpdated' => time()));

		// Update the member table to represent the new ban situation.
		updateBanMembers();
	}

	// The user pressed 'Remove selected ban entries'.
	elseif (!empty($_POST['remove_selection']) && !empty($_POST['ban_items']) && is_array($_POST['ban_items']))
	{
		checkSession();

		// Making sure every deleted ban item is an integer.
		foreach ($_POST['ban_items'] as $key => $value)
			$_POST['ban_items'][$key] = (int) $value;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban IN ({array_int:ban_list})
				AND id_ban_group = {int:ban_group}',
			array(
				'ban_list' => $_POST['ban_items'],
				'ban_group' => $_REQUEST['bg'],
			)
		);

		// It changed, let the settings and the member table know.
		updateSettings(array('banLastUpdated' => time()));
		updateBanMembers();
	}

	// Modify OR add a ban.
	elseif (!empty($_POST['modify_ban']) || !empty($_POST['add_ban']))
	{
		checkSession();

		$addBan = !empty($_POST['add_ban']);
		if (empty($_POST['ban_name']))
			fatal_lang_error('ban_name_empty', false);

		// Let's not allow HTML in ban names, it's more evil than beneficial.
		$_POST['ban_name'] = $smcFunc['htmlspecialchars']($_POST['ban_name'], ENT_QUOTES);

		// Check whether a ban with this name already exists.
		$request = $smcFunc['db_query']('', '
			SELECT id_ban_group
			FROM {db_prefix}ban_groups
			WHERE name = {string:new_ban_name}' . ($addBan ? '' : '
				AND id_ban_group != {int:ban_group}') . '
			LIMIT 1',
			array(
				'ban_group' => $_REQUEST['bg'],
				'new_ban_name' => $_POST['ban_name'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 1)
			fatal_lang_error('ban_name_exists', false, array($_POST['ban_name']));
		$smcFunc['db_free_result']($request);

		$_POST['reason'] = $smcFunc['htmlspecialchars']($_POST['reason'], ENT_QUOTES);
		$_POST['notes'] = $smcFunc['htmlspecialchars']($_POST['notes'], ENT_QUOTES);
		$_POST['notes'] = str_replace(array("\r", "\n", '  '), array('', '<br />', '&nbsp; '), $_POST['notes']);
		$_POST['expiration'] = $_POST['expiration'] == 'never' ? 'NULL' : ($_POST['expiration'] == 'expired' ? '0' : ($_POST['expire_date'] != $_POST['old_expire'] ? time() + 24 * 60 * 60 * (int) $_POST['expire_date'] : 'expire_time'));
		$_POST['full_ban'] = empty($_POST['full_ban']) ? '0' : '1';
		$_POST['cannot_post'] = !empty($_POST['full_ban']) || empty($_POST['cannot_post']) ? '0' : '1';
		$_POST['cannot_register'] = !empty($_POST['full_ban']) || empty($_POST['cannot_register']) ? '0' : '1';
		$_POST['cannot_login'] = !empty($_POST['full_ban']) || empty($_POST['cannot_login']) ? '0' : '1';

		if ($addBan)
		{
			// Adding some ban triggers?
			if ($addBan && !empty($_POST['ban_suggestion']) && is_array($_POST['ban_suggestion']))
			{
				$ban_triggers = array();
				$ban_logs = array();
				if (in_array('main_ip', $_POST['ban_suggestion']) && !empty($_POST['main_ip']))
				{
					$ip = trim($_POST['main_ip']);
					$ip_parts = ip2range($ip);
					if (!checkExistingTriggerIP($ip_parts, $ip))
						fatal_lang_error('invalid_ip', false);

					$ban_triggers[] = array(
						$ip_parts[0]['low'],
						$ip_parts[0]['high'],
						$ip_parts[1]['low'],
						$ip_parts[1]['high'],
						$ip_parts[2]['low'],
						$ip_parts[2]['high'],
						$ip_parts[3]['low'],
						$ip_parts[3]['high'],
						'',
						'',
						0,
					);

					$ban_logs[] = array(
						'ip_range' => $_POST['main_ip'],
					);
				}
				if (in_array('hostname', $_POST['ban_suggestion']) && !empty($_POST['hostname']))
				{
					if (preg_match('/[^\w.\-*]/', $_POST['hostname']) == 1)
						fatal_lang_error('invalid_hostname', false);

					// Replace the * wildcard by a MySQL wildcard %.
					$_POST['hostname'] = str_replace('*', '%', $_POST['hostname']);

					$ban_triggers[] = array(
						0, 0, 0, 0, 0, 0, 0, 0,
						substr($_POST['hostname'], 0, 255),
						'',
						0,
					);
					$ban_logs[] = array(
						'hostname' => $_POST['hostname'],
					);
				}
				if (in_array('email', $_POST['ban_suggestion']) && !empty($_POST['email']))
				{
					if (preg_match('/[^\w.\-\+*@]/', $_POST['email']) == 1)
						fatal_lang_error('invalid_email', false);
					$_POST['email'] = strtolower(str_replace('*', '%', $_POST['email']));

					$ban_triggers[] = array(
						0, 0, 0, 0, 0, 0, 0, 0,
						'',
						substr($_POST['email'], 0, 255),
						0,
					);
					$ban_logs[] = array(
						'email' => $_POST['email'],
					);
				}
				if (in_array('user', $_POST['ban_suggestion']) && (!empty($_POST['bannedUser']) || !empty($_POST['user'])))
				{
					// We got a username, let's find its ID.
					if (empty($_POST['bannedUser']))
					{
						$_POST['user'] = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $smcFunc['htmlspecialchars']($_POST['user'], ENT_QUOTES));

						$request = $smcFunc['db_query']('', '
							SELECT id_member, (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0) AS isAdmin
							FROM {db_prefix}members
							WHERE member_name = {string:username} OR real_name = {string:username}
							LIMIT 1',
							array(
								'admin_group' => 1,
								'username' => $_POST['user'],
							)
						);
						if ($smcFunc['db_num_rows']($request) == 0)
							fatal_lang_error('invalid_username', false);
						list ($_POST['bannedUser'], $isAdmin) = $smcFunc['db_fetch_row']($request);
						$smcFunc['db_free_result']($request);

						if ($isAdmin && $isAdmin != 'f')
							fatal_lang_error('no_ban_admin', 'critical');
					}

					$ban_triggers[] = array(
						0, 0, 0, 0, 0, 0, 0, 0,
						'',
						'',
						(int) $_POST['bannedUser'],
					);
					$ban_logs[] = array(
						'member' => $_POST['bannedUser'],
					);
				}

				if (!empty($_POST['ban_suggestion']['ips']) && is_array($_POST['ban_suggestion']['ips']))
				{
					$_POST['ban_suggestion']['ips'] = array_unique($_POST['ban_suggestion']['ips']);

					// Don't add the main IP again.
					if (in_array('main_ip', $_POST['ban_suggestion']))
						$_POST['ban_suggestion']['ips'] = array_diff($_POST['ban_suggestion']['ips'], array($_POST['main_ip']));

					foreach ($_POST['ban_suggestion']['ips'] as $ip)
					{
						$ip_parts = ip2range($ip);

						// They should be alright, but just to be sure...
						if (count($ip_parts) != 4)
							fatal_lang_error('invalid_ip', false);

						$ban_triggers[] = array(
							$ip_parts[0]['low'],
							$ip_parts[0]['high'],
							$ip_parts[1]['low'],
							$ip_parts[1]['high'],
							$ip_parts[2]['low'],
							$ip_parts[2]['high'],
							$ip_parts[3]['low'],
							$ip_parts[3]['high'],
							'',
							'',
							0,
						);
						$ban_logs[] = array(
							'ip_range' => $ip,
						);
					}
				}
			}

			// Yes yes, we're ready to add now.
			$smcFunc['db_insert']('',
				'{db_prefix}ban_groups',
				array(
					'name' => 'string-20', 'ban_time' => 'int', 'expire_time' => 'raw', 'cannot_access' => 'int', 'cannot_register' => 'int',
					'cannot_post' => 'int', 'cannot_login' => 'int', 'reason' => 'string-255', 'notes' => 'string-65534',
				),
				array(
					$_POST['ban_name'], time(), $_POST['expiration'], $_POST['full_ban'], $_POST['cannot_register'],
					$_POST['cannot_post'], $_POST['cannot_login'], $_POST['reason'], $_POST['notes'],
				),
				array('id_ban_group')
			);
			$_REQUEST['bg'] = $smcFunc['db_insert_id']('{db_prefix}ban_groups', 'id_ban_group');

			// Now that the ban group is added, add some triggers as well.
			if (!empty($ban_triggers) && !empty($_REQUEST['bg']))
			{
				// Put in the ban group ID.
				foreach ($ban_triggers as $k => $trigger)
					array_unshift($ban_triggers[$k], $_REQUEST['bg']);

				// Log what we are doing!
				foreach ($ban_logs as $log_details)
					logAction('ban', $log_details + array('new' => 1));

				$smcFunc['db_insert']('',
					'{db_prefix}ban_items',
					array(
						'id_ban_group' => 'int', 'ip_low1' => 'int', 'ip_high1' => 'int', 'ip_low2' => 'int', 'ip_high2' => 'int',
						'ip_low3' => 'int', 'ip_high3' => 'int', 'ip_low4' => 'int', 'ip_high4' => 'int', 'hostname' => 'string-255',
						'email_address' => 'string-255', 'id_member' => 'int',
					),
					$ban_triggers,
					array('id_ban')
				);
			}
		}
		else
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
					'expiration' => $_POST['expiration'],
					'cannot_access' => $_POST['full_ban'],
					'cannot_post' => $_POST['cannot_post'],
					'cannot_register' => $_POST['cannot_register'],
					'cannot_login' => $_POST['cannot_login'],
					'id_ban_group' => $_REQUEST['bg'],
					'ban_name' => $_POST['ban_name'],
					'reason' => $_POST['reason'],
					'notes' => $_POST['notes'],
				)
			);

		// No more caching, we have something new here.
		updateSettings(array('banLastUpdated' => time()));
		updateBanMembers();
	}

	// If we're editing an existing ban, get it from the database.
	if (!empty($_REQUEST['bg']))
	{
		$context['ban_items'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT
				bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
				bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4,
				bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, bg.cannot_access, bg.cannot_register, bg.cannot_login, bg.cannot_post,
				IFNULL(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
			FROM {db_prefix}ban_groups AS bg
				LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bg.id_ban_group = {int:current_ban}',
			array(
				'current_ban' => $_REQUEST['bg'],
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
						'status' => $row['expire_time'] === null ? 'never' : ($row['expire_time'] < time() ? 'expired' : 'still_active_but_we_re_counting_the_days'),
						'days' => $row['expire_time'] > time() ? floor(($row['expire_time'] - time()) / 86400) : 0
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
				);
			}
			if (!empty($row['id_ban']))
			{
				$context['ban_items'][$row['id_ban']] = array(
					'id' => $row['id_ban'],
					'hits' => $row['hits'],
				);
				if (!empty($row['ip_high1']))
				{
					$context['ban_items'][$row['id_ban']]['type'] = 'ip';
					$context['ban_items'][$row['id_ban']]['ip'] = range2ip(array($row['ip_low1'], $row['ip_low2'], $row['ip_low3'], $row['ip_low4']), array($row['ip_high1'], $row['ip_high2'], $row['ip_high3'], $row['ip_high4']));
				}
				elseif (!empty($row['hostname']))
				{
					$context['ban_items'][$row['id_ban']]['type'] = 'hostname';
					$context['ban_items'][$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);
				}
				elseif (!empty($row['email_address']))
				{
					$context['ban_items'][$row['id_ban']]['type'] = 'email';
					$context['ban_items'][$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);
				}
				elseif (!empty($row['id_member']))
				{
					$context['ban_items'][$row['id_ban']]['type'] = 'user';
					$context['ban_items'][$row['id_ban']]['user'] = array(
						'id' => $row['id_member'],
						'name' => $row['real_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					);
				}
				// Invalid ban (member probably doesn't exist anymore).
				else
				{
					unset($context['ban_items'][$row['id_ban']]);
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}ban_items
						WHERE id_ban = {int:current_ban}',
						array(
							'current_ban' => $row['id_ban'],
						)
					);
				}
			}
		}
		$smcFunc['db_free_result']($request);
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
				list ($context['ban_suggestions']['member']['id'], $context['ban_suggestions']['member']['name'], $context['ban_suggestions']['main_ip'], $context['ban_suggestions']['email']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($context['ban_suggestions']['member']['id']))
			{
				$context['ban_suggestions']['href'] = $scripturl . '?action=profile;u=' . $context['ban_suggestions']['member']['id'];
				$context['ban_suggestions']['member']['link'] = '<a href="' . $context['ban_suggestions']['href'] . '">' . $context['ban_suggestions']['member']['name'] . '</a>';

				// Default the ban name to the name of the banned member.
				$context['ban']['name'] = $context['ban_suggestions']['member']['name'];

				// Would be nice if we could also ban the hostname.
				if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $context['ban_suggestions']['main_ip']) == 1 && empty($modSettings['disableHostnameLookup']))
					$context['ban_suggestions']['hostname'] = host_from_ip($context['ban_suggestions']['main_ip']);

				// Find some additional IP's used by this member.
				$context['ban_suggestions']['message_ips'] = array();
				$request = $smcFunc['db_query']('ban_suggest_message_ips', '
					SELECT DISTINCT poster_ip
					FROM {db_prefix}messages
					WHERE id_member = {int:current_user}
						AND poster_ip RLIKE {string:poster_ip_regex}
					ORDER BY poster_ip',
					array(
						'current_user' => (int) $_REQUEST['u'],
						'poster_ip_regex' => '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$',
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$context['ban_suggestions']['message_ips'][] = $row['poster_ip'];
				$smcFunc['db_free_result']($request);

				$context['ban_suggestions']['error_ips'] = array();
				$request = $smcFunc['db_query']('ban_suggest_error_ips', '
					SELECT DISTINCT ip
					FROM {db_prefix}log_errors
					WHERE id_member = {int:current_user}
						AND ip RLIKE {string:poster_ip_regex}
					ORDER BY ip',
					array(
						'current_user' => (int) $_REQUEST['u'],
						'poster_ip_regex' => '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$',
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$context['ban_suggestions']['error_ips'][] = $row['ip'];
				$smcFunc['db_free_result']($request);

				// Borrowing a few language strings from profile.
				loadLanguage('Profile');
			}
		}
	}

	// Template needs this to show errors using javascript
	loadLanguage('Errors');

	// If we're in wireless mode remove the admin template layer and use a special template.
	if (WIRELESS && WIRELESS_PROTOCOL != 'wap')
	{
		$context['sub_template'] = WIRELESS_PROTOCOL . '_ban_edit';
		foreach ($context['template_layers'] as $k => $v)
			if (strpos($v, 'generic_menu') === 0)
				unset($context['template_layers'][$k]);
	}
	else
		$context['sub_template'] = 'ban_edit';
}

function BanEditTrigger()
{
	global $context, $smcFunc;

	$context['sub_template'] = 'ban_edit_trigger';

	if (empty($_REQUEST['bg']))
		fatal_lang_error('ban_not_found', false);

	if (empty($_REQUEST['bi']))
	{
		$context['ban_trigger'] = array(
			'id' => 0,
			'group' => (int) $_REQUEST['bg'],
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
				bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4,
				mem.member_name, mem.real_name
			FROM {db_prefix}ban_items AS bi
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bi.id_ban = {int:ban_item}
				AND bi.id_ban_group = {int:ban_group}
			LIMIT 1',
			array(
				'ban_item' => (int) $_REQUEST['bi'],
				'ban_group' => (int) $_REQUEST['bg'],
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
				'value' => empty($row['ip_low1']) ? '' : range2ip(array($row['ip_low1'], $row['ip_low2'], $row['ip_low3'], $row['ip_low4']), array($row['ip_high1'], $row['ip_high2'], $row['ip_high3'], $row['ip_high4'])),
				'selected' => !empty($row['ip_low1']),
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
}

function BanBrowseTriggers()
{
	global $modSettings, $context, $scripturl, $smcFunc, $txt;
	global $sourcedir, $settings;

	if (!empty($_POST['remove_triggers']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		// Clean the integers.
		foreach ($_POST['remove'] as $key => $value)
			$_POST['remove'][$key] = $value;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban IN ({array_int:ban_list})',
			array(
				'ban_list' => $_POST['remove'],
			)
		);

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
		'items_per_page' => $modSettings['defaultMaxMessages'],
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
					'style' => 'text-align: center;',
				),
				'sort' => array(
					'default' => 'bi.hits DESC',
					'reverse' => 'bi.hits',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_ban' => false,
						),
					),
					'style' => 'text-align: center',
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
				'value' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=ip">' . ($context['selected_entity'] == 'ip' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '') . $txt['ip'] . '</a>&nbsp;|&nbsp;<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=hostname">' . ($context['selected_entity'] == 'hostname' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '') . $txt['hostname'] . '</a>&nbsp;|&nbsp;<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=email">' . ($context['selected_entity'] == 'email' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '') . $txt['email'] . '</a>&nbsp;|&nbsp;<a href="' . $scripturl . '?action=admin;area=ban;sa=browse;entity=member">' . ($context['selected_entity'] == 'member' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;" /> ' : '') . $txt['username'] . '</a>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="remove_triggers" value="' . $txt['ban_remove_selected_triggers'] . '" onclick="return confirm(\'' . $txt['ban_remove_selected_triggers_confirm'] . '\');" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);

	// Specific data for the first column depending on the selected entity.
	if ($context['selected_entity'] === 'ip')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => create_function('$rowData', '
				return range2ip(array(
					$rowData[\'ip_low1\'],
					$rowData[\'ip_low2\'],
					$rowData[\'ip_low3\'],
					$rowData[\'ip_low4\']
				), array(
					$rowData[\'ip_high1\'],
					$rowData[\'ip_high2\'],
					$rowData[\'ip_high3\'],
					$rowData[\'ip_high4\']
				));
			'),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4',
			'reverse' => 'bi.ip_low1 DESC, bi.ip_high1 DESC, bi.ip_low2 DESC, bi.ip_high2 DESC, bi.ip_low3 DESC, bi.ip_high3 DESC, bi.ip_low4 DESC, bi.ip_high4 DESC',
		);
	}
	elseif ($context['selected_entity'] === 'hostname')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => create_function('$rowData', '
				global $smcFunc;
				return strtr($smcFunc[\'htmlspecialchars\']($rowData[\'hostname\']), array(\'%\' => \'*\'));
			'),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.hostname',
			'reverse' => 'bi.hostname DESC',
		);
	}
	elseif ($context['selected_entity'] === 'email')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => create_function('$rowData', '
				global $smcFunc;
				return strtr($smcFunc[\'htmlspecialchars\']($rowData[\'email_address\']), array(\'%\' => \'*\'));
			'),
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

function list_getBanTriggers($start, $items_per_page, $sort, $trigger_type)
{
	global $smcFunc;

	$where = array(
		'ip' => 'bi.ip_low1 > 0',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	$request = $smcFunc['db_query']('', '
		SELECT
			bi.id_ban, bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4, bi.hostname, bi.email_address, bi.hits,
			bg.id_ban_group, bg.name' . ($trigger_type === 'member' ? ',
			mem.id_member, mem.real_name' : '') . '
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]) . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'blank_string' => '',
		)
	);
	$ban_triggers = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ban_triggers[] = $row;
	$smcFunc['db_free_result']($request);

	return $ban_triggers;
}

function list_getNumBanTriggers($trigger_type)
{
	global $smcFunc;

	$where = array(
		'ip' => 'bi.ip_low1 > 0',
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

function BanLog()
{
	global $scripturl, $context, $smcFunc, $sourcedir, $txt;
	global $context;

	// Delete one or more entries.
	if (!empty($_POST['removeAll']) || (!empty($_POST['removeSelected']) && !empty($_POST['remove'])))
	{
		checkSession();

		// 'Delete all entries' button was pressed.
		if (!empty($_POST['removeAll']))
			$smcFunc['db_query']('truncate_table', '
				TRUNCATE {db_prefix}log_banned',
				array(
				)
			);

		// 'Delte selection' button was pressed.
		else
		{
			// Make sure every entry is integer.
			foreach ($_POST['remove'] as $index => $log_id)
				$_POST['remove'][$index] = (int) $log_id;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_banned
				WHERE id_ban_log IN ({array_int:ban_list})',
				array(
					'ban_list' => $_POST['remove'],
				)
			);
		}
	}

	$listOptions = array(
		'id' => 'ban_log',
		'items_per_page' => 30,
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
					'default' => 'IFNULL(mem.real_name, 1=1), mem.real_name',
					'reverse' => 'IFNULL(mem.real_name, 1=1) DESC, mem.real_name DESC',
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['ban_log_date'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return timeformat($rowData[\'log_time\']);
					'),
				),
				'sort' => array(
					'default' => 'lb.log_time DESC',
					'reverse' => 'lb.log_time',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_ban_log' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'form' => array(
			'href' => $context['admin_area'] == 'ban' ? $scripturl . '?action=admin;area=ban;sa=log' : $scripturl . '?action=admin;area=logs;sa=banlog',
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="removeSelected" value="' . $txt['ban_log_remove_selected'] . '" onclick="return confirm(\'' . $txt['ban_log_remove_selected_confirm'] . '\');" class="button_submit" />
					<input type="submit" name="removeAll" value="' . $txt['ban_log_remove_all'] . '" onclick="return confirm(\'' . $txt['ban_log_remove_all_confirm'] . '\');" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['page_title'] = $txt['ban_log'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'ban_log';
}

function list_getBanLogEntries($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT lb.id_ban_log, lb.id_member, IFNULL(lb.ip, {string:dash}) AS ip, IFNULL(lb.email, {string:dash}) AS email, lb.log_time, IFNULL(mem.real_name, {string:blank_string}) AS real_name
		FROM {db_prefix}log_banned AS lb
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'blank_string' => '',
			'dash' => '-',
		)
	);
	$log_entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$log_entries[] = $row;
	$smcFunc['db_free_result']($request);

	return $log_entries;
}

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

function range2ip($low, $high)
{
	if (count($low) != 4 || count($high) != 4)
		return '';

	$ip = array();
	for ($i = 0; $i < 4; $i++)
	{
		if ($low[$i] == $high[$i])
			$ip[$i] = $low[$i];
		elseif ($low[$i] == '0' && $high[$i] == '255')
			$ip[$i] = '*';
		else
			$ip[$i] = $low[$i] . '-' . $high[$i];
	}

	// Pretending is fun... the IP can't be this, so use it for 'unknown'.
	if ($ip == array(255, 255, 255, 255))
		return 'unknown';

	return implode('.', $ip);
}

function checkExistingTriggerIP($ip_array, $fullip = '')
{
	global $smcFunc, $scripturl;

	if (count($ip_array) == 4)
		$values = array(
			'ip_low1' => $ip_array[0]['low'],
			'ip_high1' => $ip_array[0]['high'],
			'ip_low2' => $ip_array[1]['low'],
			'ip_high2' => $ip_array[1]['high'],
			'ip_low3' => $ip_array[2]['low'],
			'ip_high3' => $ip_array[2]['high'],
			'ip_low4' => $ip_array[3]['low'],
			'ip_high4' => $ip_array[3]['high'],
		);
	else
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT bg.id_ban_group, bg.name
		FROM {db_prefix}ban_groups AS bg
		INNER JOIN {db_prefix}ban_items AS bi ON
			(bi.id_ban_group = bg.id_ban_group)
			AND ip_low1 = {int:ip_low1} AND ip_high1 = {int:ip_high1}
			AND ip_low2 = {int:ip_low2} AND ip_high2 = {int:ip_high2}
			AND ip_low3 = {int:ip_low3} AND ip_high3 = {int:ip_high3}
			AND ip_low4 = {int:ip_low4} AND ip_high4 = {int:ip_high4}
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
			WHERE ' . implode( ' OR ', $queryPart),
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

?>