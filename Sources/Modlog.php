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

/*	The moderation log is this file's only job.  It views it, and that's about
	all it does.

	void ViewModlog()
		- prepares the information from the moderation log for viewing.
		- disallows the deletion of events within twenty-four hours of now.
		- requires the admin_forum permission.
		- uses the Modlog template, main sub template.
		- is accessed via ?action=moderate;area=modlog.

	int list_getModLogEntries()
		//!!!

	array list_getModLogEntries($start, $items_per_page, $sort, $query_string = '', $query_params = array(), $log_type = 1)
		- Gets the moderation log entries that match the specified paramaters
		- limit can be an array with two values
		- search_param and order should be proper SQL strings or blank.  If blank they are not used.
*/

// Show the moderation log
function ViewModlog()
{
	global $txt, $modSettings, $context, $scripturl, $sourcedir, $user_info, $smcFunc, $settings;

	// Are we looking at the moderation log or the administration log.
	$context['log_type'] = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'adminlog' ? 3 : 1;
	if ($context['log_type'] == 3)
		isAllowedTo('admin_forum');

	// These change dependant on whether we are viewing the moderation or admin log.
	if ($context['log_type'] == 3 || $_REQUEST['action'] == 'admin')
		$context['url_start'] = '?action=admin;area=logs;sa=' . ($context['log_type'] == 3 ? 'adminlog' : 'modlog') . ';type=' . $context['log_type'];
	else
		$context['url_start'] = '?action=moderate;area=modlog;type=' . $context['log_type'];

	$context['can_delete'] = allowedTo('admin_forum');

	loadLanguage('Modlog');

	$context['page_title'] = $context['log_type'] == 3 ? $txt['modlog_admin_log'] : $txt['modlog_view'];

	// The number of entries to show per page of log file.
	$context['displaypage'] = 30;
	// Amount of hours that must pass before allowed to delete file.
	$context['hoursdisable'] = 24;

	// Handle deletion...
	if (isset($_POST['removeall']) && $context['can_delete'])
	{
		checkSession();

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:moderate_log}
				AND log_time < {int:twenty_four_hours_wait}',
			array(
				'twenty_four_hours_wait' => time() - $context['hoursdisable'] * 3600,
				'moderate_log' => $context['log_type'],
			)
		);
	}
	elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $context['can_delete'])
	{
		checkSession();
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:moderate_log}
				AND id_action IN ({array_string:delete_actions})
				AND log_time < {int:twenty_four_hours_wait}',
			array(
				'twenty_four_hours_wait' => time() - $context['hoursdisable'] * 3600,
				'delete_actions' => array_unique($_POST['delete']),
				'moderate_log' => $context['log_type'],
			)
		);
	}

	// Do the column stuff!
	$sort_types = array(
		'action' =>'lm.action',
		'time' => 'lm.log_time',
		'member' => 'mem.real_name',
		'group' => 'mg.group_name',
		'ip' => 'lm.ip',
	);

	// Setup the direction stuff...
	$context['order'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'time';

	// If we're coming from a search, get the variables.
	if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
	{
		$search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
		$search_params = @unserialize($search_params);
	}

	// This array houses all the valid search types.
	$searchTypes = array(
		'action' => array('sql' => 'lm.action', 'label' => $txt['modlog_action']),
		'member' => array('sql' => 'mem.real_name', 'label' => $txt['modlog_member']),
		'group' => array('sql' => 'mg.group_name', 'label' => $txt['modlog_position']),
		'ip' => array('sql' => 'lm.ip', 'label' => $txt['modlog_ip'])
	);

	if (!isset($search_params['string']) || (!empty($_REQUEST['search']) && $search_params['string'] != $_REQUEST['search']))
		$search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
	else
		$search_params_string = $search_params['string'];

	if (isset($_REQUEST['search_type']) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
		$search_params_type = isset($_REQUEST['search_type']) && isset($searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[$context['order']]) ? $context['order'] : 'member');
	else
		$search_params_type = $search_params['type'];

	$search_params_column = $searchTypes[$search_params_type]['sql'];
	$search_params = array(
		'string' => $search_params_string,
		'type' => $search_params_type,
	);

	// Setup the search context.
	$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(serialize($search_params));
	$context['search'] = array(
		'string' => $search_params['string'],
		'type' => $search_params['type'],
		'label' => $searchTypes[$search_params_type]['label'],
	);

	// If they are searching by action, then we must do some manual intervention to search in their language!
	if ($search_params['type'] == 'action' && !empty($search_params['string']))
	{
		// For the moment they can only search for ONE action!
		foreach ($txt as $key => $text)
		{
			if (substr($key, 0, 10) == 'modlog_ac_' && strpos($text, $search_params['string']) !== false)
			{
				$search_params['string'] = substr($key, 10);
				break;
			}
		}
	}

	require_once($sourcedir . '/Subs-List.php');

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'moderation_log_list',
		'title' => '<a href="' . $scripturl . '?action=helpadmin;help=' . ($context['log_type'] == 3 ? 'adminlog' : 'modlog') . '" onclick="return reqWin(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.gif" alt="' . $txt['help'] . '" align="top" /></a> ' . $txt['modlog_' . ($context['log_type'] == 3 ? 'admin' : 'moderation') . '_log'],
		'width' => '100%',
		'items_per_page' => $context['displaypage'],
		'no_items_label' => $txt['modlog_' . ($context['log_type'] == 3 ? 'admin_log_' : '') . 'no_entries_found'],
		'base_href' => $scripturl . $context['url_start'] . (!empty($context['search_params']) ? ';params=' . $context['search_params'] : ''),
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getModLogEntries',
			'params' => array(
				(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
				$context['log_type'],
			),
		),
		'get_count' => array(
			'function' => 'list_getModLogEntryCount',
			'params' => array(
				(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
				$context['log_type'],
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['modlog_action'],
					'class' => 'lefttext first_th',
				),
				'data' => array(
					'db' => 'action_text',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.action',
					'reverse' => 'lm.action DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['modlog_date'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'time',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.log_time DESC',
					'reverse' => 'lm.log_time',
				),
			),
			'moderator' => array(
				'header' => array(
					'value' => $txt['modlog_member'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'moderator_link',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'position' => array(
				'header' => array(
					'value' => $txt['modlog_position'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'position',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['modlog_ip'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'ip',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.ip',
					'reverse' => 'lm.ip DESC',
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" name="all" class="input_check" onclick="invertAll(this, this.form);" />',
				),
				'data' => array(
					'function' => create_function('$entry', '
						return \'<input type="checkbox" class="input_check" name="delete[]" value="\' . $entry[\'id\'] . \'"\' . ($entry[\'editable\'] ? \'\' : \' disabled="disabled"\') . \' />\';
					'),
					'style' => 'text-align: center;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
				'params' => $context['search_params']
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['modlog_' . ($context['log_type'] == 3 ? 'admin' : 'moderation') . '_log_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
			array(
				'position' => 'below_table_data',
				'value' => '
					' . $txt['modlog_search'] . ' (' . $txt['modlog_by'] . ': ' . $context['search']['label'] . '):
					<input type="text" name="search" size="18" value="' . $context['search']['string'] . '" class="input_text" /> <input type="submit" name="is_search" value="' . $txt['modlog_go'] . '" class="button_submit" />
					' . ($context['can_delete'] ? ' |
						<input type="submit" name="remove" value="' . $txt['modlog_remove'] . '" class="button_submit" />
						<input type="submit" name="removeall" value="' . $txt['modlog_removeall'] . '" class="button_submit" />' : ''),
			),
		),
	);

	// Create the watched user list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'moderation_log_list';
}

// Get the number of mod log entries.
function list_getModLogEntryCount($query_string = '', $query_params = array(), $log_type = 1)
{
	global $smcFunc, $user_info;

	$modlog_query = allowedTo('admin_forum') || $user_info['mod_cache']['bq'] == '1=1' ? '1=1' : ($user_info['mod_cache']['bq'] == '0=1' ? 'lm.id_board = 0 AND lm.id_topic = 0' : (strtr($user_info['mod_cache']['bq'], array('id_board' => 'b.id_board')) . ' AND ' . strtr($user_info['mod_cache']['bq'], array('id_board' => 't.id_board'))));

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_actions AS lm
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lm.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lm.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lm.id_topic)
		WHERE id_log = {int:log_type}
			AND {raw:modlog_query}'
			. (!empty($query_string) ? '
				AND ' . $query_string : ''),
		array_merge($query_params, array(
			'reg_group_id' => 0,
			'log_type' => $log_type,
			'modlog_query' => $modlog_query,
		))
	);
	list ($entry_count) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return $entry_count;
}

function list_getModLogEntries($start, $items_per_page, $sort, $query_string = '', $query_params = array(), $log_type = 1)
{
	global $context, $scripturl, $txt, $smcFunc, $user_info;

	$modlog_query = allowedTo('admin_forum') || $user_info['mod_cache']['bq'] == '1=1' ? '1=1' : ($user_info['mod_cache']['bq'] == '0=1' ? 'lm.id_board = 0 AND lm.id_topic = 0' : (strtr($user_info['mod_cache']['bq'], array('id_board' => 'b.id_board')) . ' AND ' . strtr($user_info['mod_cache']['bq'], array('id_board' => 't.id_board'))));

	// Do a little bit of self protection.
	if (!isset($context['hoursdisable']))
		$context['hoursdisable'] = 24;

	// Can they see the IP address?
	$seeIP = allowedTo('moderate_forum');

	// Here we have the query getting the log details.
	$result = $smcFunc['db_query']('', '
		SELECT
			lm.id_action, lm.id_member, lm.ip, lm.log_time, lm.action, lm.id_board, lm.id_topic, lm.id_msg, lm.extra,
			mem.real_name, mg.group_name
		FROM {db_prefix}log_actions AS lm
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lm.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lm.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lm.id_topic)
			WHERE id_log = {int:log_type}
				AND {raw:modlog_query}'
			. (!empty($query_string) ? '
				AND ' . $query_string : '') . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($query_params, array(
			'reg_group_id' => 0,
			'log_type' => $log_type,
			'modlog_query' => $modlog_query,
		))
	);

	// Arrays for decoding objects into.
	$topics = array();
	$boards = array();
	$members = array();
	$messages = array();
	$entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$row['extra'] = @unserialize($row['extra']);

		// Corrupt?
		$row['extra'] = is_array($row['extra']) ? $row['extra'] : array();

		// Add on some of the column stuff info
		if (!empty($row['id_board']))
		{
			if ($row['action'] == 'move')
				$row['extra']['board_to'] = $row['id_board'];
			else
				$row['extra']['board'] = $row['id_board'];
		}

		if (!empty($row['id_topic']))
			$row['extra']['topic'] = $row['id_topic'];
		if (!empty($row['id_msg']))
			$row['extra']['message'] = $row['id_msg'];

		// Is this associated with a topic?
		if (isset($row['extra']['topic']))
			$topics[(int) $row['extra']['topic']][] = $row['id_action'];
		if (isset($row['extra']['new_topic']))
			$topics[(int) $row['extra']['new_topic']][] = $row['id_action'];

		// How about a member?
		if (isset($row['extra']['member']))
		{
			// Guests don't have names!
			if (empty($row['extra']['member']))
				$row['extra']['member'] = $txt['modlog_parameter_guest'];
			else
			{
				// Try to find it...
				$members[(int) $row['extra']['member']][] = $row['id_action'];
			}
		}

		// Associated with a board?
		if (isset($row['extra']['board_to']))
			$boards[(int) $row['extra']['board_to']][] = $row['id_action'];
		if (isset($row['extra']['board_from']))
			$boards[(int) $row['extra']['board_from']][] = $row['id_action'];
		if (isset($row['extra']['board']))
			$boards[(int) $row['extra']['board']][] = $row['id_action'];

		// A message?
		if (isset($row['extra']['message']))
			$messages[(int) $row['extra']['message']][] = $row['id_action'];

		// IP Info?
		if (isset($row['extra']['ip_range']))
			if ($seeIP)
				$row['extra']['ip_range'] = '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['extra']['ip_range'] . '">' . $row['extra']['ip_range'] . '</a>';
			else
				$row['extra']['ip_range'] = $txt['logged'];

		// Email?
		if (isset($row['extra']['email']))
			$row['extra']['email'] = '<a href="mailto:' . $row['extra']['email'] . '">' . $row['extra']['email'] . '</a>';

		// Bans are complex.
		if ($row['action'] == 'ban')
		{
			$row['action_text'] = $txt['modlog_ac_ban'];
			foreach (array('member', 'email', 'ip_range', 'hostname') as $type)
				if (isset($row['extra'][$type]))
					$row['action_text'] .= $txt['modlog_ac_ban_trigger_' . $type];
		}

		// The array to go to the template. Note here that action is set to a "default" value of the action doesn't match anything in the descriptions. Allows easy adding of logging events with basic details.
		$entries[$row['id_action']] = array(
			'id' => $row['id_action'],
			'ip' => $seeIP ? $row['ip'] : $txt['logged'],
			'position' => empty($row['real_name']) && empty($row['group_name']) ? $txt['guest'] : $row['group_name'],
			'moderator_link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : (empty($row['real_name']) ? ($txt['guest'] . (!empty($row['extra']['member_acted']) ? ' (' . $row['extra']['member_acted'] . ')' : '')) : $row['real_name']),
			'time' => timeformat($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
			'editable' => time() > $row['log_time'] + $context['hoursdisable'] * 3600,
			'extra' => $row['extra'],
			'action' => $row['action'],
			'action_text' => isset($row['action_text']) ? $row['action_text'] : '',
		);
	}
	$smcFunc['db_free_result']($result);

	if (!empty($boards))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_board, name
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:board_list})
			LIMIT ' . count(array_keys($boards)),
			array(
				'board_list' => array_keys($boards),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			foreach ($boards[$row['id_board']] as $action)
			{
				// Make the board number into a link - dealing with moving too.
				if (isset($entries[$action]['extra']['board_to']) && $entries[$action]['extra']['board_to'] == $row['id_board'])
					$entries[$action]['extra']['board_to'] = '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
				elseif (isset($entries[$action]['extra']['board_from']) && $entries[$action]['extra']['board_from'] == $row['id_board'])
					$entries[$action]['extra']['board_from'] = '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
				elseif (isset($entries[$action]['extra']['board']) && $entries[$action]['extra']['board'] == $row['id_board'])
					$entries[$action]['extra']['board'] = '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
			}
		}
		$smcFunc['db_free_result']($request);
	}

	if (!empty($topics))
	{
		$request = $smcFunc['db_query']('', '
			SELECT ms.subject, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic IN ({array_int:topic_list})
			LIMIT ' . count(array_keys($topics)),
			array(
				'topic_list' => array_keys($topics),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			foreach ($topics[$row['id_topic']] as $action)
			{
				$this_action = &$entries[$action];

				// This isn't used in the current theme.
				$this_action['topic'] = array(
					'id' => $row['id_topic'],
					'subject' => $row['subject'],
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>'
				);

				// Make the topic number into a link - dealing with splitting too.
				if (isset($this_action['extra']['topic']) && $this_action['extra']['topic'] == $row['id_topic'])
					$this_action['extra']['topic'] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.' . (isset($this_action['extra']['message']) ? 'msg' . $this_action['extra']['message'] . '#msg' . $this_action['extra']['message'] : '0') . '">' . $row['subject'] . '</a>';
				elseif (isset($this_action['extra']['new_topic']) && $this_action['extra']['new_topic'] == $row['id_topic'])
					$this_action['extra']['new_topic'] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.' . (isset($this_action['extra']['message']) ? 'msg' . $this_action['extra']['message'] . '#msg' . $this_action['extra']['message'] : '0') . '">' . $row['subject'] . '</a>';
			}
		}
		$smcFunc['db_free_result']($request);
	}

	if (!empty($messages))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, subject
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:message_list})
			LIMIT ' . count(array_keys($messages)),
			array(
				'message_list' => array_keys($messages),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			foreach ($messages[$row['id_msg']] as $action)
			{
				$this_action = &$entries[$action];

				// This isn't used in the current theme.
				$this_action['message'] = array(
					'id' => $row['id_msg'],
					'subject' => $row['subject'],
					'href' => $scripturl . '?msg=' . $row['id_msg'],
					'link' => '<a href="' . $scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				);

				// Make the message number into a link.
				if (isset($this_action['extra']['message']) && $this_action['extra']['message'] == $row['id_msg'])
					$this_action['extra']['message'] = '<a href="' . $scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
			}
		}
		$smcFunc['db_free_result']($request);
	}

	if (!empty($members))
	{
		$request = $smcFunc['db_query']('', '
			SELECT real_name, id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT ' . count(array_keys($members)),
			array(
				'member_list' => array_keys($members),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			foreach ($members[$row['id_member']] as $action)
			{
				// Not used currently.
				$entries[$action]['member'] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
				);
				// Make the member number into a name.
				$entries[$action]['extra']['member'] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			}
		}
		$smcFunc['db_free_result']($request);
	}

	// Do some formatting of the action string.
	foreach ($entries as $k => $entry)
	{
		// Make any message info links so its easier to go find that message.
		if (isset($entry['extra']['message']) && (empty($entry['message']) || empty($entry['message']['id'])))
			$entries[$k]['extra']['message'] = '<a href="' . $scripturl . '?msg=' . $entry['extra']['message'] . '">' . $entry['extra']['message'] . '</a>';

		// Mark up any deleted members, topics and boards.
		foreach (array('board', 'board_from', 'board_to', 'member', 'topic', 'new_topic') as $type)
			if (!empty($entry['extra'][$type]) && is_numeric($entry['extra'][$type]))
				$entries[$k]['extra'][$type] = sprintf($txt['modlog_id'], $entry['extra'][$type]);

		if (empty($entries[$k]['action_text']))
			$entries[$k]['action_text'] = isset($txt['modlog_ac_' . $entry['action']]) ? $txt['modlog_ac_' . $entry['action']] : $entry['action'];
		$entries[$k]['action_text'] = preg_replace('~\{([A-Za-z\d_]+)\}~ie', 'isset($entries[$k][\'extra\'][\'$1\']) ? $entries[$k][\'extra\'][\'$1\'] : \'\'', $entries[$k]['action_text']);
	}

	// Back we go!
	return $entries;
}

?>