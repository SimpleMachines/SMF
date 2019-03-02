<?php

/**
 * Forum maintenance. Important stuff.
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
 * Main dispatcher, the maintenance access point.
 * This, as usual, checks permissions, loads language files, and forwards to the actual workers.
 */
function ManageMaintenance()
{
	global $txt, $context;

	// You absolutely must be an admin by here!
	isAllowedTo('admin_forum');

	// Need something to talk about?
	loadLanguage('ManageMaintenance');
	loadTemplate('ManageMaintenance');

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['maintain_title'],
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'routine' => array(),
			'database' => array(),
			'members' => array(),
			'topics' => array(),
		),
	);

	// So many things you can do - but frankly I won't let you - just these!
	$subActions = array(
		'routine' => array(
			'function' => 'MaintainRoutine',
			'template' => 'maintain_routine',
			'activities' => array(
				'version' => 'VersionDetail',
				'repair' => 'MaintainFindFixErrors',
				'recount' => 'AdminBoardRecount',
				'logs' => 'MaintainEmptyUnimportantLogs',
				'cleancache' => 'MaintainCleanCache',
			),
		),
		'database' => array(
			'function' => 'MaintainDatabase',
			'template' => 'maintain_database',
			'activities' => array(
				'optimize' => 'OptimizeTables',
				'convertentities' => 'ConvertEntities',
				'convertmsgbody' => 'ConvertMsgBody',
			),
		),
		'members' => array(
			'function' => 'MaintainMembers',
			'template' => 'maintain_members',
			'activities' => array(
				'reattribute' => 'MaintainReattributePosts',
				'purgeinactive' => 'MaintainPurgeInactiveMembers',
				'recountposts' => 'MaintainRecountPosts',
			),
		),
		'topics' => array(
			'function' => 'MaintainTopics',
			'template' => 'maintain_topics',
			'activities' => array(
				'massmove' => 'MaintainMassMoveTopics',
				'pruneold' => 'MaintainRemoveOldPosts',
				'olddrafts' => 'MaintainRemoveOldDrafts',
			),
		),
		'hooks' => array(
			'function' => 'list_integration_hooks',
		),
		'destroy' => array(
			'function' => 'Destroy',
			'activities' => array(),
		),
	);

	call_integration_hook('integrate_manage_maintenance', array(&$subActions));

	// Yep, sub-action time!
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$subAction = $_REQUEST['sa'];
	else
		$subAction = 'routine';

	// Doing something special?
	if (isset($_REQUEST['activity']) && isset($subActions[$subAction]['activities'][$_REQUEST['activity']]))
		$activity = $_REQUEST['activity'];

	// Set a few things.
	$context['page_title'] = $txt['maintain_title'];
	$context['sub_action'] = $subAction;
	$context['sub_template'] = !empty($subActions[$subAction]['template']) ? $subActions[$subAction]['template'] : '';

	// Finally fall through to what we are doing.
	call_helper($subActions[$subAction]['function']);

	// Any special activity?
	if (isset($activity))
		call_helper($subActions[$subAction]['activities'][$activity]);

	// Create a maintenance token.  Kinda hard to do it any other way.
	createToken('admin-maint');
}

/**
 * Supporting function for the database maintenance area.
 */
function MaintainDatabase()
{
	global $context, $db_type, $db_character_set, $modSettings, $smcFunc, $txt;

	// Show some conversion options?
	$context['convert_entities'] = isset($modSettings['global_character_set']) && $modSettings['global_character_set'] === 'UTF-8';

	if ($db_type == 'mysql')
	{
		db_extend('packages');

		$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
		foreach ($colData as $column)
			if ($column['name'] == 'body')
				$body_type = $column['type'];

		$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
		$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);
	}

	if (isset($_GET['done']) && $_GET['done'] == 'convertentities')
		$context['maintenance_finished'] = $txt['entity_convert_title'];
}

/**
 * Supporting function for the routine maintenance area.
 */
function MaintainRoutine()
{
	global $context, $txt;

	if (isset($_GET['done']) && $_GET['done'] == 'recount')
		$context['maintenance_finished'] = $txt['maintain_recount'];
}

/**
 * Supporting function for the members maintenance area.
 */
function MaintainMembers()
{
	global $context, $smcFunc, $txt;

	// Get membergroups - for deleting members and the like.
	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['membergroups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	$smcFunc['db_free_result']($result);

	if (isset($_GET['done']) && $_GET['done'] == 'recountposts')
		$context['maintenance_finished'] = $txt['maintain_recountposts'];

	loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
}

/**
 * Supporting function for the topics maintenance area.
 */
function MaintainTopics()
{
	global $context, $smcFunc, $txt, $sourcedir;

	// Let's load up the boards in case they are useful.
	$result = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
		)
	);
	$context['categories'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'name' => $row['cat_name'],
				'boards' => array()
			);

		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level']
		);
	}
	$smcFunc['db_free_result']($result);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($context['categories']);

	if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
		$context['maintenance_finished'] = $txt['maintain_old'];
	elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
		$context['maintenance_finished'] = $txt['move_topics_maintenance'];
}

/**
 * Find and fix all errors on the forum.
 */
function MaintainFindFixErrors()
{
	global $sourcedir;

	// Honestly, this should be done in the sub function.
	validateToken('admin-maint');

	require_once($sourcedir . '/RepairBoards.php');
	RepairBoards();
}

/**
 * Wipes the whole cache directory.
 * This only applies to SMF's own cache directory, though.
 */
function MaintainCleanCache()
{
	global $context, $txt;

	checkSession();
	validateToken('admin-maint');

	// Just wipe the whole cache directory!
	clean_cache();

	$context['maintenance_finished'] = $txt['maintain_cache'];
}

/**
 * Empties all uninmportant logs
 */
function MaintainEmptyUnimportantLogs()
{
	global $context, $smcFunc, $txt;

	checkSession();
	validateToken('admin-maint');

	// No one's online now.... MUHAHAHAHA :P.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online');

	// Dump the banning logs.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_banned');

	// Start id_error back at 0 and dump the error log.
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_errors');

	// Clear out the spam log.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol');

	// Last but not least, the search logs!
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_topics');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_messages');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_results');

	updateSettings(array('search_pointer' => 0));

	$context['maintenance_finished'] = $txt['maintain_logs'];
}

/**
 * Oh noes! I'd document this but that would give it away
 */
function Destroy()
{
	global $context;

	echo '<!DOCTYPE html>
		<html', $context['right_to_left'] ? ' dir="rtl"' : '', '><head><title>', $context['forum_name_html_safe'], ' deleted!</title></head>
		<body style="background-color: orange; font-family: arial, sans-serif; text-align: center;">
		<div style="margin-top: 8%; font-size: 400%; color: black;">Oh my, you killed ', $context['forum_name_html_safe'], '!</div>
		<div style="margin-top: 7%; font-size: 500%; color: red;"><strong>You lazy bum!</strong></div>
		</body></html>';
	obExit(false);
}

/**
 * Convert the column "body" of the table {db_prefix}messages from TEXT to MEDIUMTEXT and vice versa.
 * It requires the admin_forum permission.
 * This is needed only for MySQL.
 * During the conversion from MEDIUMTEXT to TEXT it check if any of the posts exceed the TEXT length and if so it aborts.
 * This action is linked from the maintenance screen (if it's applicable).
 * Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
 *
 * @uses the convert_msgbody sub template of the Admin template.
 */
function ConvertMsgBody()
{
	global $scripturl, $context, $txt, $db_type;
	global $modSettings, $smcFunc, $time_start;

	// Show me your badge!
	isAllowedTo('admin_forum');

	if ($db_type != 'mysql')
		return;

	db_extend('packages');

	$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
	foreach ($colData as $column)
		if ($column['name'] == 'body')
			$body_type = $column['type'];

	$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';

	if ($body_type == 'text' || ($body_type != 'text' && isset($_POST['do_conversion'])))
	{
		checkSession();
		validateToken('admin-maint');

		// Make it longer so we can do their limit.
		if ($body_type == 'text')
			$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => 'mediumtext'));
		// Shorten the column so we can have a bit (literally per record) less space occupied
		else
			$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => 'text'));

		// 3rd party integrations may be interested in knowning about this.
		call_integration_hook('integrate_convert_msgbody', array($body_type));

		$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
		foreach ($colData as $column)
			if ($column['name'] == 'body')
				$body_type = $column['type'];

		$context['maintenance_finished'] = $txt[$context['convert_to'] . '_title'];
		$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
		$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);

		return;
	}
	elseif ($body_type != 'text' && (!isset($_POST['do_conversion']) || isset($_POST['cont'])))
	{
		checkSession();
		if (empty($_REQUEST['start']))
			validateToken('admin-maint');
		else
			validateToken('admin-convertMsg');

		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = 3;
		$context['sub_template'] = 'not_done';
		$increment = 500;
		$id_msg_exceeding = isset($_POST['id_msg_exceeding']) ? explode(',', $_POST['id_msg_exceeding']) : array();

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) as count
			FROM {db_prefix}messages',
			array()
		);
		list($max_msgs) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Try for as much time as possible.
		@set_time_limit(600);

		while ($_REQUEST['start'] < $max_msgs)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ id_msg
				FROM {db_prefix}messages
				WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
					AND LENGTH(body) > 65535',
				array(
					'start' => $_REQUEST['start'],
					'increment' => $increment - 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$id_msg_exceeding[] = $row['id_msg'];
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-convertMsg');
				$context['continue_post_data'] = '
					<input type="hidden" name="' . $context['admin-convertMsg_token_var'] . '" value="' . $context['admin-convertMsg_token'] . '">
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
					<input type="hidden" name="id_msg_exceeding" value="' . implode(',', $id_msg_exceeding) . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertmsgbody;start=' . $_REQUEST['start'];
				$context['continue_percent'] = round(100 * $_REQUEST['start'] / $max_msgs);

				return;
			}
		}
		createToken('admin-maint');
		$context['page_title'] = $txt[$context['convert_to'] . '_title'];
		$context['sub_template'] = 'convert_msgbody';

		if (!empty($id_msg_exceeding))
		{
			if (count($id_msg_exceeding) > 100)
			{
				$query_msg = array_slice($id_msg_exceeding, 0, 100);
				$context['exceeding_messages_morethan'] = sprintf($txt['exceeding_messages_morethan'], count($id_msg_exceeding));
			}
			else
				$query_msg = $id_msg_exceeding;

			$context['exceeding_messages'] = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_msg, id_topic, subject
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:messages})',
				array(
					'messages' => $query_msg,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['exceeding_messages'][] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
			$smcFunc['db_free_result']($request);
		}
	}
}

/**
 * Converts HTML-entities to their UTF-8 character equivalents.
 * This requires the admin_forum permission.
 * Pre-condition: UTF-8 has been set as database and global character set.
 *
 * It is divided in steps of 10 seconds.
 * This action is linked from the maintenance screen (if applicable).
 * It is accessed by ?action=admin;area=maintain;sa=database;activity=convertentities.
 *
 * @uses Admin template, convert_entities sub-template.
 */
function ConvertEntities()
{
	global $db_character_set, $modSettings, $context, $smcFunc, $db_type, $db_prefix;

	isAllowedTo('admin_forum');

	// Check to see if UTF-8 is currently the default character set.
	if ($modSettings['global_character_set'] !== 'UTF-8')
		fatal_lang_error('entity_convert_only_utf8');

	// Some starting values.
	$context['table'] = empty($_REQUEST['table']) ? 0 : (int) $_REQUEST['table'];
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	$context['start_time'] = time();

	$context['first_step'] = !isset($_REQUEST[$context['session_var']]);
	$context['last_step'] = false;

	// The first step is just a text screen with some explanation.
	if ($context['first_step'])
	{
		validateToken('admin-maint');
		createToken('admin-maint');

		$context['sub_template'] = 'convert_entities';
		return;
	}
	// Otherwise use the generic "not done" template.
	$context['sub_template'] = 'not_done';
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;

	// Now we're actually going to convert...
	checkSession('request');
	validateToken('admin-maint');
	createToken('admin-maint');
	$context['not_done_token'] = 'admin-maint';

	// A list of tables ready for conversion.
	$tables = array(
		'ban_groups',
		'ban_items',
		'boards',
		'calendar',
		'calendar_holidays',
		'categories',
		'log_errors',
		'log_search_subjects',
		'membergroups',
		'members',
		'message_icons',
		'messages',
		'package_servers',
		'personal_messages',
		'pm_recipients',
		'polls',
		'poll_choices',
		'smileys',
		'themes',
	);
	$context['num_tables'] = count($tables);

	// Loop through all tables that need converting.
	for (; $context['table'] < $context['num_tables']; $context['table']++)
	{
		$cur_table = $tables[$context['table']];
		$primary_key = '';
		// Make sure we keep stuff unique!
		$primary_keys = array();

		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Get a list of text columns.
		$columns = array();
		if ($db_type == 'postgresql')
			$request = $smcFunc['db_query']('', '
				SELECT column_name "Field", data_type "Type"
				FROM information_schema.columns
				WHERE table_name = {string:cur_table}
					AND (data_type = \'character varying\' or data_type = \'text\')',
				array(
					'cur_table' => $db_prefix . $cur_table,
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW FULL COLUMNS
				FROM {db_prefix}{raw:cur_table}',
				array(
					'cur_table' => $cur_table,
				)
			);
		while ($column_info = $smcFunc['db_fetch_assoc']($request))
			if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
				$columns[] = strtolower($column_info['Field']);

		// Get the column with the (first) primary key.
		if ($db_type == 'postgresql')
			$request = $smcFunc['db_query']('', '
				SELECT a.attname "Column_name", \'PRIMARY\' "Key_name", attnum "Seq_in_index"
				FROM   pg_index i
				JOIN   pg_attribute a ON a.attrelid = i.indrelid
					AND a.attnum = ANY(i.indkey)
				WHERE  i.indrelid = {string:cur_table}::regclass
					AND    i.indisprimary',
				array(
					'cur_table' => $db_prefix . $cur_table,
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW KEYS
				FROM {db_prefix}{raw:cur_table}',
				array(
					'cur_table' => $cur_table,
				)
			);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['Key_name'] === 'PRIMARY')
			{
				if ((empty($primary_key) || $row['Seq_in_index'] == 1) && !in_array(strtolower($row['Column_name']), $columns))
					$primary_key = $row['Column_name'];

				$primary_keys[] = $row['Column_name'];
			}
		}
		$smcFunc['db_free_result']($request);

		// No primary key, no glory.
		// Same for columns. Just to be sure we've work to do!
		if (empty($primary_key) || empty($columns))
			continue;

		// Get the maximum value for the primary key.
		$request = $smcFunc['db_query']('', '
			SELECT MAX({identifier:key})
			FROM {db_prefix}{raw:cur_table}',
			array(
				'key' => $primary_key,
				'cur_table' => $cur_table,
			)
		);
		list($max_value) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($max_value))
			continue;

		while ($context['start'] <= $max_value)
		{
			// Retrieve a list of rows that has at least one entity to convert.
			$request = $smcFunc['db_query']('', '
				SELECT {raw:primary_keys}, {raw:columns}
				FROM {db_prefix}{raw:cur_table}
				WHERE {raw:primary_key} BETWEEN {int:start} AND {int:start} + 499
					AND {raw:like_compare}
				LIMIT 500',
				array(
					'primary_keys' => implode(', ', $primary_keys),
					'columns' => implode(', ', $columns),
					'cur_table' => $cur_table,
					'primary_key' => $primary_key,
					'start' => $context['start'],
					'like_compare' => '(' . implode(' LIKE \'%&#%\' OR ', $columns) . ' LIKE \'%&#%\')',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$insertion_variables = array();
				$changes = array();
				foreach ($row as $column_name => $column_value)
					if ($column_name !== $primary_key && strpos($column_value, '&#') !== false)
					{
						$changes[] = $column_name . ' = {string:changes_' . $column_name . '}';
						$insertion_variables['changes_' . $column_name] = preg_replace_callback('~&#(\d{1,5}|x[0-9a-fA-F]{1,4});~', 'fixchardb__callback', $column_value);
					}

				$where = array();
				foreach ($primary_keys as $key)
				{
					$where[] = $key . ' = {string:where_' . $key . '}';
					$insertion_variables['where_' . $key] = $row[$key];
				}

				// Update the row.
				if (!empty($changes))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}' . $cur_table . '
						SET
							' . implode(',
							', $changes) . '
						WHERE ' . implode(' AND ', $where),
						$insertion_variables
					);
			}
			$smcFunc['db_free_result']($request);
			$context['start'] += 500;

			// After ten seconds interrupt.
			if (time() - $context['start_time'] > 10)
			{
				// Calculate an approximation of the percentage done.
				$context['continue_percent'] = round(100 * ($context['table'] + ($context['start'] / $max_value)) / $context['num_tables'], 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertentities;table=' . $context['table'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				return;
			}
		}
		$context['start'] = 0;
	}

	// If we're here, we must be done.
	$context['continue_percent'] = 100;
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;done=convertentities';
	$context['last_step'] = true;
	$context['continue_countdown'] = 3;
}

/**
 * Optimizes all tables in the database and lists how much was saved.
 * It requires the admin_forum permission.
 * It shows as the maintain_forum admin area.
 * It is accessed from ?action=admin;area=maintain;sa=database;activity=optimize.
 * It also updates the optimize scheduled task such that the tables are not automatically optimized again too soon.
 *
 * @uses the optimize sub template
 */
function OptimizeTables()
{
	global $db_prefix, $txt, $context, $smcFunc, $time_start;

	isAllowedTo('admin_forum');

	checkSession('request');

	if (!isset($_SESSION['optimized_tables']))
		validateToken('admin-maint');
	else
		validateToken('admin-optimize', 'post', false);

	ignore_user_abort(true);
	db_extend();

	$context['page_title'] = $txt['database_optimize'];
	$context['sub_template'] = 'optimize';
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;

	// Only optimize the tables related to this smf install, not all the tables in the db
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = $smcFunc['db_list_tables'](false, $real_prefix . '%');
	$tables = array();
	foreach ($temp_tables as $table)
		$tables[] = array('table_name' => $table);

	// If there aren't any tables then I believe that would mean the world has exploded...
	$context['num_tables'] = count($tables);
	if ($context['num_tables'] == 0)
		fatal_error('You appear to be running SMF in a flat file mode... fantastic!', false);

	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	// Try for extra time due to large tables.
	@set_time_limit(100);

	// For each table....
	$_SESSION['optimized_tables'] = !empty($_SESSION['optimized_tables']) ? $_SESSION['optimized_tables'] : array();
	for ($key = $_REQUEST['start']; $context['num_tables'] - 1; $key++)
	{
		if (empty($tables[$key]))
			break;

		// Continue?
		if (microtime(true) - $time_start > 10)
		{
			$_REQUEST['start'] = $key;
			$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=optimize;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
			$context['continue_percent'] = round(100 * $_REQUEST['start'] / $context['num_tables']);
			$context['sub_template'] = 'not_done';
			$context['page_title'] = $txt['not_done_title'];

			createToken('admin-optimize');
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-optimize_token_var'] . '" value="' . $context['admin-optimize_token'] . '">';

			if (function_exists('apache_reset_timeout'))
				apache_reset_timeout();

			return;
		}

		// Optimize the table!  We use backticks here because it might be a custom table.
		$data_freed = $smcFunc['db_optimize_table']($tables[$key]['table_name']);

		if ($data_freed > 0)
			$_SESSION['optimized_tables'][] = array(
				'name' => $tables[$key]['table_name'],
				'data_freed' => $data_freed,
			);
	}

	// Number of tables, etc...
	$txt['database_numb_tables'] = sprintf($txt['database_numb_tables'], $context['num_tables']);
	$context['num_tables_optimized'] = count($_SESSION['optimized_tables']);
	$context['optimized_tables'] = $_SESSION['optimized_tables'];
	unset($_SESSION['optimized_tables']);
}

/**
 * Recount many forum totals that can be recounted automatically without harm.
 * it requires the admin_forum permission.
 * It shows the maintain_forum admin area.
 *
 * Totals recounted:
 * - fixes for topics with wrong num_replies.
 * - updates for num_posts and num_topics of all boards.
 * - recounts instant_messages but not unread_messages.
 * - repairs messages pointing to boards with topics pointing to other boards.
 * - updates the last message posted in boards and children.
 * - updates member count, latest member, topic count, and message count.
 *
 * The function redirects back to ?action=admin;area=maintain when complete.
 * It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
 */
function AdminBoardRecount()
{
	global $txt, $context, $modSettings, $sourcedir;
	global $time_start, $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('request');

	// validate the request or the loop
	if (!isset($_REQUEST['step']))
		validateToken('admin-maint');
	else
		validateToken('admin-boardrecount');

	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;
	$context['sub_template'] = 'not_done';

	// Try for as much time as possible.
	@set_time_limit(600);

	// Step the number of topics at a time so things don't time out...
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($max_topics) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$increment = min(max(50, ceil($max_topics / 4)), 2000);
	if (empty($_REQUEST['start']))
		$_REQUEST['start'] = 0;

	$total_steps = 8;

	// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
	if (empty($_REQUEST['step']))
	{
		$_REQUEST['step'] = 0;

		while ($_REQUEST['start'] < $max_topics)
		{
			// Recount approved messages
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
					CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
				array(
					'is_approved' => 1,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET num_replies = {int:num_replies}
					WHERE id_topic = {int:id_topic}',
					array(
						'num_replies' => $row['real_num_replies'],
						'id_topic' => $row['id_topic'],
					)
				);
			$smcFunc['db_free_result']($request);

			// Recount unapproved messages
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
					COUNT(mu.id_msg) AS real_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
				array(
					'not_approved' => 0,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:unapproved_posts}
					WHERE id_topic = {int:id_topic}',
					array(
						'unapproved_posts' => $row['real_unapproved_posts'],
						'id_topic' => $row['id_topic'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the post count of each board.
	if ($_REQUEST['step'] <= 1)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET num_posts = {int:num_posts}
				WHERE redirect = {string:redirect}',
				array(
					'num_posts' => 0,
					'redirect' => '',
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the topic count of each board.
	if ($_REQUEST['step'] <= 2)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET num_topics = {int:num_topics}',
				array(
					'num_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 1,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved post count of each board.
	if ($_REQUEST['step'] <= 3)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET unapproved_posts = {int:unapproved_posts}',
				array(
					'unapproved_posts' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved topic count of each board.
	if ($_REQUEST['step'] <= 4)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET unapproved_topics = {int:unapproved_topics}',
				array(
					'unapproved_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 0,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Get all members with wrong number of personal messages.
	if ($_REQUEST['step'] <= 5)
	{
		$request = $smcFunc['db_query']('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.instant_messages) AS instant_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
			array(
				'is_not_deleted' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('instant_messages' => $row['real_num']));
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.unread_messages) AS unread_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
			array(
				'is_not_deleted' => 0,
				'is_not_read' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
		$smcFunc['db_free_result']($request);

		if (microtime(true) - $time_start > 3)
		{
			createToken('admin-boardrecount');
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

			$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . $context['session_var'] . '=' . $context['session_id'];
			$context['continue_percent'] = round(700 / $total_steps);

			return;
		}
	}

	// Any messages pointing to the wrong board?
	if ($_REQUEST['step'] <= 6)
	{
		while ($_REQUEST['start'] < $modSettings['maxMsgID'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
				WHERE m.id_msg > {int:id_msg_min}
					AND m.id_msg <= {int:id_msg_max}',
				array(
					'id_msg_min' => $_REQUEST['start'],
					'id_msg_max' => $_REQUEST['start'] + $increment,
				)
			);
			$boards = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$boards[$row['id_board']][] = $row['id_msg'];

			$smcFunc['db_free_result']($request);

			foreach ($boards as $board_id => $messages)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET id_board = {int:id_board}
					WHERE id_msg IN ({array_int:id_msg_array})',
					array(
						'id_msg_array' => $messages,
						'id_board' => $board_id,
					)
				);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $modSettings['maxMsgID']) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the latest message of each board.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	$smcFunc['db_free_result']($request);

	krsort($resort_me);

	$lastModifiedMsg = array();
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];

			// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					array(
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					)
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}

	// Update all the basic statistics.
	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// Finally, update the latest event times.
	require_once($sourcedir . '/ScheduledTasks.php');
	CalculateNextTrigger();

	redirectexit('action=admin;area=maintain;sa=routine;done=recount');
}

/**
 * Perform a detailed version check.  A very good thing ;).
 * The function parses the comment headers in all files for their version information,
 * and outputs that for some javascript to check with simplemachines.org.
 * It does not connect directly with simplemachines.org, but rather expects the client to.
 *
 * It requires the admin_forum permission.
 * Uses the view_versions admin area.
 * Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
 *
 * @uses Admin template, view_versions sub-template.
 */
function VersionDetail()
{
	global $txt, $sourcedir, $context;

	isAllowedTo('admin_forum');

	// Call the function that'll get all the version info we need.
	require_once($sourcedir . '/Subs-Admin.php');
	$versionOptions = array(
		'include_ssi' => true,
		'include_subscriptions' => true,
		'include_tasks' => true,
		'sort_results' => true,
	);
	$version_info = getFileVersions($versionOptions);

	// Add the new info to the template context.
	$context += array(
		'file_versions' => $version_info['file_versions'],
		'default_template_versions' => $version_info['default_template_versions'],
		'template_versions' => $version_info['template_versions'],
		'default_language_versions' => $version_info['default_language_versions'],
		'default_known_languages' => array_keys($version_info['default_language_versions']),
		'tasks_versions' => $version_info['tasks_versions'],
	);

	// Make it easier to manage for the template.
	$context['forum_version'] = SMF_FULL_VERSION;

	$context['sub_template'] = 'view_versions';
	$context['page_title'] = $txt['admin_version_check'];
}

/**
 * Re-attribute posts.
 */
function MaintainReattributePosts()
{
	global $sourcedir, $context, $txt;

	checkSession();

	// Find the member.
	require_once($sourcedir . '/Subs-Auth.php');
	$members = findMembers($_POST['to']);

	if (empty($members))
		fatal_lang_error('reattribute_cannot_find_member');

	$memID = array_shift($members);
	$memID = $memID['id'];

	$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
	$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

	// Now call the reattribute function.
	require_once($sourcedir . '/Subs-Members.php');
	reattributePosts($memID, $email, $membername, !empty($_POST['posts']));

	$context['maintenance_finished'] = $txt['maintain_reattribute_posts'];
}

/**
 * Removing old members. Done and out!
 *
 * @todo refactor
 */
function MaintainPurgeInactiveMembers()
{
	global $sourcedir, $context, $smcFunc, $txt;

	$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
	if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
	{
		checkSession();
		validateToken('admin-maint');

		$groups = array();
		foreach ($_POST['groups'] as $id => $dummy)
			$groups[] = (int) $id;
		$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
		$where_vars = array(
			'time_limit' => $time_limit,
		);
		if ($_POST['del_type'] == 'activated')
		{
			$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
			$where_vars['is_activated'] = 0;
		}
		else
			$where = 'mem.last_login < {int:time_limit} AND (mem.last_login != 0 OR mem.date_registered < {int:time_limit})';

		// Need to get *all* groups then work out which (if any) we avoid.
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Avoid this one?
			if (!in_array($row['id_group'], $groups))
			{
				// Post group?
				if ($row['min_posts'] != -1)
				{
					$where .= ' AND mem.id_post_group != {int:id_post_group_' . $row['id_group'] . '}';
					$where_vars['id_post_group_' . $row['id_group']] = $row['id_group'];
				}
				else
				{
					$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
					$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// If we have ungrouped unselected we need to avoid those guys.
		if (!in_array(0, $groups))
		{
			$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
			$where_vars['blank_add_groups'] = '';
		}

		// Select all the members we're about to murder/remove...
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, COALESCE(m.id_member, 0) AS is_mod
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
			WHERE ' . $where,
			$where_vars
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!$row['is_mod'] || !in_array(3, $groups))
				$members[] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		require_once($sourcedir . '/Subs-Members.php');
		deleteMembers($members);
	}

	$context['maintenance_finished'] = $txt['maintain_members'];
	createToken('admin-maint');
}

/**
 * Removing old posts doesn't take much as we really pass through.
 */
function MaintainRemoveOldPosts()
{
	global $sourcedir;

	validateToken('admin-maint');

	// Actually do what we're told!
	require_once($sourcedir . '/RemoveTopic.php');
	RemoveOldTopics2();
}

/**
 * Removing old drafts
 */
function MaintainRemoveOldDrafts()
{
	global $sourcedir, $smcFunc;

	validateToken('admin-maint');

	$drafts = array();

	// Find all of the old drafts
	$request = $smcFunc['db_query']('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * $_POST['draftdays']),
		)
	);

	while ($row = $smcFunc['db_fetch_row']($request))
		$drafts[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	// If we have old drafts, remove them
	if (count($drafts) > 0)
	{
		require_once($sourcedir . '/Drafts.php');
		DeleteDraft($drafts, false);
	}
}

/**
 * Moves topics from one board to another.
 *
 * @uses not_done template to pause the process.
 */
function MaintainMassMoveTopics()
{
	global $smcFunc, $sourcedir, $context, $txt;

	// Only admins.
	isAllowedTo('admin_forum');

	checkSession('request');
	validateToken('admin-maint');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_post_data'] = '';
	$context['continue_get_data'] = '';
	$context['sub_template'] = 'not_done';
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['start_time'] = time();

	// First time we do this?
	$id_board_from = isset($_REQUEST['id_board_from']) ? (int) $_REQUEST['id_board_from'] : 0;
	$id_board_to = isset($_REQUEST['id_board_to']) ? (int) $_REQUEST['id_board_to'] : 0;
	$max_days = isset($_REQUEST['maxdays']) ? (int) $_REQUEST['maxdays'] : 0;
	$locked = isset($_POST['move_type_locked']) || isset($_GET['locked']);
	$sticky = isset($_POST['move_type_sticky']) || isset($_GET['sticky']);

	// No boards then this is your stop.
	if (empty($id_board_from) || empty($id_board_to))
		return;

	// The big WHERE clause
	$conditions = 'WHERE t.id_board = {int:id_board_from}
		AND m.icon != {string:moved}';

	// DB parameters
	$params = array(
		'id_board_from' => $id_board_from,
		'moved' => 'moved',
	);

	// Only moving topics not posted in for x days?
	if (!empty($max_days))
	{
		$conditions .= '
			AND m.poster_time < {int:poster_time}';
		$params['poster_time'] = time() - 3600 * 24 * $max_days;
	}

	// Moving locked topics?
	if ($locked)
	{
		$conditions .= '
			AND t.locked = {int:locked}';
		$params['locked'] = 1;
	}

	// What about sticky topics?
	if ($sticky)
	{
		$conditions .= '
			AND t.is_sticky = {int:sticky}';
		$params['sticky'] = 1;
	}

	// How many topics are we converting?
	if (!isset($_REQUEST['totaltopics']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)' .
			$conditions,
			$params
		);
		list ($total_topics) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	else
		$total_topics = (int) $_REQUEST['totaltopics'];

	// Seems like we need this here.
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';max_days=' . $max_days;

	if ($locked)
		$context['continue_get_data'] .= ';locked';

	if ($sticky)
		$context['continue_get_data'] .= ';sticky';

	$context['continue_get_data'] .= ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

	// We have topics to move so start the process.
	if (!empty($total_topics))
	{
		while ($context['start'] <= $total_topics)
		{
			// Lets get the topics.
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
				' . $conditions . '
				LIMIT 10',
				$params
			);

			// Get the ids.
			$topics = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$topics[] = $row['id_topic'];

			// Just return if we don't have any topics left to move.
			if (empty($topics))
			{
				cache_put_data('board-' . $id_board_from, null, 120);
				cache_put_data('board-' . $id_board_to, null, 120);
				redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
			}

			// Lets move them.
			require_once($sourcedir . '/MoveTopic.php');
			moveTopics($topics, $id_board_to);

			// We've done at least ten more topics.
			$context['start'] += 10;

			// Lets wait a while.
			if (time() - $context['start_time'] > 3)
			{
				// What's the percent?
				$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

				// Let the template system do it's thang.
				return;
			}
		}
	}

	// Don't confuse admins by having an out of date cache.
	cache_put_data('board-' . $id_board_from, null, 120);
	cache_put_data('board-' . $id_board_to, null, 120);

	redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
}

/**
 * Recalculate all members post counts
 * it requires the admin_forum permission.
 *
 * - recounts all posts for members found in the message table
 * - updates the members post count record in the members table
 * - honors the boards post count flag
 * - does not count posts in the recycle bin
 * - zeros post counts for all members with no posts in the message table
 * - runs as a delayed loop to avoid server overload
 * - uses the not_done template in Admin.template
 *
 * The function redirects back to action=admin;area=maintain;sa=members when complete.
 * It is accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
 */
function MaintainRecountPosts()
{
	global $txt, $context, $modSettings, $smcFunc;

	// You have to be allowed in here
	isAllowedTo('admin_forum');
	checkSession('request');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_get_data'] = '';
	$context['sub_template'] = 'not_done';

	// init
	$increment = 200;
	$_REQUEST['start'] = !isset($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	// Ask for some extra time, on big boards this may take a bit
	@set_time_limit(600);

	// Only run this query if we don't have the total number of members that have posted
	if (!isset($_SESSION['total_members']))
	{
		validateToken('admin-maint');

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(DISTINCT m.id_member)
			FROM {db_prefix}messages AS m
			JOIN {db_prefix}boards AS b on m.id_board = b.id_board
			WHERE m.id_member != 0
				AND b.count_posts = 0',
			array(
			)
		);

		// save it so we don't do this again for this task
		list ($_SESSION['total_members']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	else
		validateToken('admin-recountposts');

	// Lets get a group of members and determine their post count (from the boards that have post count enabled of course).
	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ m.id_member, COUNT(m.id_member) AS posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON m.id_board = b.id_board
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			' . (!empty($modSettings['recycle_enable']) ? ' AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member
		LIMIT {int:start}, {int:number}',
		array(
			'start' => $_REQUEST['start'],
			'number' => $increment,
			'recycle' => $modSettings['recycle_board'],
			'zero' => 0,
		)
	);
	$total_rows = $smcFunc['db_num_rows']($request);

	// Update the post count for this group
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET posts = {int:posts}
			WHERE id_member = {int:row}',
			array(
				'row' => $row['id_member'],
				'posts' => $row['posts'],
			)
		);
	}
	$smcFunc['db_free_result']($request);

	// Continue?
	if ($total_rows == $increment)
	{
		$_REQUEST['start'] += $increment;
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['continue_percent'] = round(100 * $_REQUEST['start'] / $_SESSION['total_members']);

		createToken('admin-recountposts');
		$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-recountposts_token_var'] . '" value="' . $context['admin-recountposts_token'] . '">';

		if (function_exists('apache_reset_timeout'))
			apache_reset_timeout();
		return;
	}

	// final steps ... made more difficult since we don't yet support sub-selects on joins
	// place all members who have posts in the message table in a temp table
	$createTemporary = $smcFunc['db_query']('', '
		CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountposts (
			id_member mediumint(8) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_member)
		)
		SELECT m.id_member
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON m.id_board = b.id_board
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			' . (!empty($modSettings['recycle_enable']) ? ' AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member',
		array(
			'zero' => 0,
			'string_zero' => '0',
			'db_error_skip' => true,
			'recycle' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
		)
	) !== false;

	if ($createTemporary)
	{
		// outer join the members table on the temporary table finding the members that have a post count but no posts in the message table
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.posts
			FROM {db_prefix}members AS mem
				LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
				ON res.id_member = mem.id_member
			WHERE res.id_member IS null
				AND mem.posts != {int:zero}',
			array(
				'zero' => 0,
			)
		);

		// set the post count to zero for any delinquents we may have found
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}members
				SET posts = {int:zero}
				WHERE id_member = {int:row}',
				array(
					'row' => $row['id_member'],
					'zero' => 0,
				)
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// all done
	unset($_SESSION['total_members']);
	$context['maintenance_finished'] = $txt['maintain_recountposts'];
	redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
}

/**
 * Generates a list of integration hooks for display
 * Accessed through ?action=admin;area=maintain;sa=hooks;
 * Allows for removal or disabling of selected hooks
 */
function list_integration_hooks()
{
	global $sourcedir, $scripturl, $context, $txt;

	$context['filter_url'] = '';
	$context['current_filter'] = '';
	$currentHooks = get_integration_hooks();
	if (isset($_GET['filter']) && in_array($_GET['filter'], array_keys($currentHooks)))
	{
		$context['filter_url'] = ';filter=' . $_GET['filter'];
		$context['current_filter'] = $_GET['filter'];
	}

	if (!empty($_REQUEST['do']) && isset($_REQUEST['hook']) && isset($_REQUEST['function']))
	{
		checkSession('request');
		validateToken('admin-hook', 'request');

		if ($_REQUEST['do'] == 'remove')
			remove_integration_function($_REQUEST['hook'], urldecode($_REQUEST['function']));

		else
		{
			$function_remove = urldecode($_REQUEST['function']) . (($_REQUEST['do'] == 'disable') ? '' : '!');
			$function_add = urldecode($_REQUEST['function']) . (($_REQUEST['do'] == 'disable') ? '!' : '');

			remove_integration_function($_REQUEST['hook'], $function_remove);
			add_integration_function($_REQUEST['hook'], $function_add);

			redirectexit('action=admin;area=maintain;sa=hooks' . $context['filter_url']);
		}
	}

	createToken('admin-hook', 'request');

	$list_options = array(
		'id' => 'list_integration_hooks',
		'title' => $txt['hooks_title_list'],
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=maintain;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		'default_sort_col' => 'hook_name',
		'get_items' => array(
			'function' => 'get_integration_hooks_data',
		),
		'get_count' => array(
			'function' => 'get_integration_hooks_count',
		),
		'no_items_label' => $txt['hooks_no_hooks'],
		'columns' => array(
			'hook_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_hook_name'],
				),
				'data' => array(
					'db' => 'hook_name',
				),
				'sort' => array(
					'default' => 'hook_name',
					'reverse' => 'hook_name DESC',
				),
			),
			'function_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_function_name'],
				),
				'data' => array(
					'function' => function($data) use ($txt)
					{
						// Show a nice icon to indicate this is an instance.
						$instance = (!empty($data['instance']) ? '<span class="main_icons news" title="' . $txt['hooks_field_function_method'] . '"></span> ' : '');

						if (!empty($data['included_file']))
							return $instance . $txt['hooks_field_function'] . ': ' . $data['real_function'] . '<br>' . $txt['hooks_field_included_file'] . ': ' . $data['included_file'];

						else
							return $instance . $data['real_function'];
					},
				),
				'sort' => array(
					'default' => 'function_name',
					'reverse' => 'function_name DESC',
				),
			),
			'file_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_file_name'],
				),
				'data' => array(
					'db' => 'file_name',
				),
				'sort' => array(
					'default' => 'file_name',
					'reverse' => 'file_name DESC',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['hooks_field_hook_exists'],
					'style' => 'width:3%;',
				),
				'data' => array(
					'function' => function($data) use ($txt, $scripturl, $context)
					{
						$change_status = array('before' => '', 'after' => '');

						$change_status['before'] = '<a href="' . $scripturl . '?action=admin;area=maintain;sa=hooks;do=' . ($data['enabled'] ? 'disable' : 'enable') . ';hook=' . $data['hook_name'] . ';function=' . urlencode($data['function_name']) . $context['filter_url'] . ';' . $context['admin-hook_token_var'] . '=' . $context['admin-hook_token'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['quickmod_confirm'] . '" class="you_sure">';
						$change_status['after'] = '</a>';

						return $change_status['before'] . '<span class="main_icons post_moderation_' . $data['status'] . '" title="' . $data['img_text'] . '"></span>';
					},
					'class' => 'centertext',
				),
				'sort' => array(
					'default' => 'status',
					'reverse' => 'status DESC',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['hooks_disable_instructions'] . '<br>
					' . $txt['hooks_disable_legend'] . ':
				<ul style="list-style: none;">
					<li><span class="main_icons post_moderation_allow"></span> ' . $txt['hooks_disable_legend_exists'] . '</li>
					<li><span class="main_icons post_moderation_moderate"></span> ' . $txt['hooks_disable_legend_disabled'] . '</li>
					<li><span class="main_icons post_moderation_deny"></span> ' . $txt['hooks_disable_legend_missing'] . '</li>
				</ul>'
			),
		),
	);

	$list_options['columns']['remove'] = array(
		'header' => array(
			'value' => $txt['hooks_button_remove'],
			'style' => 'width:3%',
		),
		'data' => array(
			'function' => function($data) use ($txt, $scripturl, $context)
			{
				if (!$data['hook_exists'])
					return '
					<a href="' . $scripturl . '?action=admin;area=maintain;sa=hooks;do=remove;hook=' . $data['hook_name'] . ';function=' . urlencode($data['function_name']) . $context['filter_url'] . ';' . $context['admin-hook_token_var'] . '=' . $context['admin-hook_token'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['quickmod_confirm'] . '" class="you_sure">
						<span class="main_icons delete" title="' . $txt['hooks_button_remove'] . '"></span>
					</a>';
			},
			'class' => 'centertext',
		),
	);
	$list_options['form'] = array(
		'href' => $scripturl . '?action=admin;area=maintain;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		'name' => 'list_integration_hooks',
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($list_options);

	$context['page_title'] = $txt['hooks_title_list'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'list_integration_hooks';
}

/**
 * Gets all of the files in a directory and its children directories
 *
 * @param string $dir_path The path to the directory
 * @return array An array containing information about the files found in the specified directory and its children
 */
function get_files_recursive($dir_path)
{
	$files = array();

	if ($dh = opendir($dir_path))
	{
		while (($file = readdir($dh)) !== false)
		{
			if ($file != '.' && $file != '..')
			{
				if (is_dir($dir_path . '/' . $file))
					$files = array_merge($files, get_files_recursive($dir_path . '/' . $file));
				else
					$files[] = array('dir' => $dir_path, 'name' => $file);
			}
		}
	}
	closedir($dh);

	return $files;
}

/**
 * Callback function for the integration hooks list (list_integration_hooks)
 * Gets all of the hooks in the system and their status
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $per_page How many items to display on each page
 * @param string $sort A string indicating how to sort things
 * @return array An array of information about the integration hooks
 */
function get_integration_hooks_data($start, $per_page, $sort)
{
	global $boarddir, $sourcedir, $settings, $txt, $context, $scripturl;

	$hooks = $temp_hooks = get_integration_hooks();
	$hooks_data = $temp_data = $hook_status = array();

	$files = get_files_recursive($sourcedir);
	if (!empty($files))
	{
		foreach ($files as $file)
		{
			if (is_file($file['dir'] . '/' . $file['name']) && substr($file['name'], -4) === '.php')
			{
				$fp = fopen($file['dir'] . '/' . $file['name'], 'rb');
				$fc = fread($fp, filesize($file['dir'] . '/' . $file['name']));
				fclose($fp);

				foreach ($temp_hooks as $hook => $allFunctions)
				{
					foreach ($allFunctions as $rawFunc)
					{
						// Get the hook info.
						$hookParsedData = get_hook_info_from_raw($rawFunc);

						if (substr($hook, -8) === '_include')
						{
							$hook_status[$hook][$hookParsedData['pureFunc']]['exists'] = file_exists(strtr(trim($rawFunc), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir'])));
							// I need to know if there is at least one function called in this file.
							$temp_data['include'][$hookParsedData['pureFunc']] = array('hook' => $hook, 'function' => $hookParsedData['pureFunc']);
							unset($temp_hooks[$hook][$rawFunc]);
						}
						elseif (strpos(str_replace(' (', '(', $fc), 'function ' . trim($hookParsedData['pureFunc']) . '(') !== false)
						{
							$hook_status[$hook][$hookParsedData['pureFunc']] = $hookParsedData;
							$hook_status[$hook][$hookParsedData['pureFunc']]['exists'] = true;
							$hook_status[$hook][$hookParsedData['pureFunc']]['in_file'] = (!empty($file['name']) ? $file['name'] : (!empty($hookParsedData['hookFile']) ? $hookParsedData['hookFile'] : ''));

							// Does the hook has its own file?
							if (!empty($hookParsedData['hookFile']))
								$temp_data['include'][$hookParsedData['pureFunc']] = array('hook' => $hook, 'function' => $hookParsedData['pureFunc']);

							// I want to remember all the functions called within this file (to check later if they are enabled or disabled and decide if the integrare_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][$hookParsedData['pureFunc']] = $hookParsedData['enabled'];
							unset($temp_hooks[$hook][$rawFunc]);
						}
					}
				}
			}
		}
	}

	$sort_types = array(
		'hook_name' => array('hook', SORT_ASC),
		'hook_name DESC' => array('hook', SORT_DESC),
		'function_name' => array('function', SORT_ASC),
		'function_name DESC' => array('function', SORT_DESC),
		'file_name' => array('file_name', SORT_ASC),
		'file_name DESC' => array('file_name', SORT_DESC),
		'status' => array('status', SORT_ASC),
		'status DESC' => array('status', SORT_DESC),
	);

	$sort_options = $sort_types[$sort];
	$sort = array();
	$hooks_filters = array();

	foreach ($hooks as $hook => $functions)
		$hooks_filters[] = '<option' . ($context['current_filter'] == $hook ? ' selected ' : '') . ' value="' . $hook . '">' . $hook . '</option>';

	if (!empty($hooks_filters))
		$context['insert_after_template'] .= '
		<script>
			var hook_name_header = document.getElementById(\'header_list_integration_hooks_hook_name\');
			hook_name_header.innerHTML += ' . JavaScriptEscape('<select style="margin-left:15px;" onchange="window.location=(\'' . $scripturl . '?action=admin;area=maintain;sa=hooks\' + (this.value ? \';filter=\' + this.value : \'\'));"><option value="">' . $txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '</select>') . ';
		</script>';

	$temp_data = array();
	$id = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
		{
			foreach ($functions as $rawFunc)
			{
				// Get the hook info.
				$hookParsedData = get_hook_info_from_raw($rawFunc);

				$hook_exists = !empty($hook_status[$hook][$hookParsedData['pureFunc']]['exists']);
				$sort[] = $sort_options[0];

				$temp_data[] = array(
					'id' => 'hookid_' . $id++,
					'hook_name' => $hook,
					'function_name' => $hookParsedData['rawData'],
					'real_function' => $hookParsedData['pureFunc'],
					'included_file' => !empty($hookParsedData['absPath']) ? $hookParsedData['absPath'] : '',
					'file_name' => (isset($hook_status[$hook][$hookParsedData['pureFunc']]['in_file']) ? $hook_status[$hook][$hookParsedData['pureFunc']]['in_file'] : (!empty($hookParsedData['hookFile']) ? $hookParsedData['hookFile'] : '')),
					'instance' => $hookParsedData['object'],
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($hookParsedData['enabled'] ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($hookParsedData['enabled'] ? 'active' : 'disabled') : 'missing')],
					'enabled' => $hookParsedData['enabled'],
					'can_be_disabled' => !isset($hook_status[$hook][$hookParsedData['pureFunc']]['enabled']),
				);
			}
		}
	}

	array_multisort($sort, $sort_options[1], $temp_data);

	$counter = 0;
	$start++;

	foreach ($temp_data as $data)
	{
		if (++$counter < $start)
			continue;
		elseif ($counter == $start + $per_page)
			break;

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

/**
 * Simply returns the total count of integration hooks
 * Used by the integration hooks list function (list_integration_hooks)
 *
 * @return int The number of hooks currently in use
 */
function get_integration_hooks_count()
{
	global $context;

	$hooks = get_integration_hooks();
	$hooks_count = 0;

	$context['filter'] = false;
	if (isset($_GET['filter']))
		$context['filter'] = $_GET['filter'];

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
			$hooks_count += count($functions);
	}

	return $hooks_count;
}

/**
 * Parses modSettings to create integration hook array
 *
 * @return array An array of information about the integration hooks
 */
function get_integration_hooks()
{
	global $modSettings;
	static $integration_hooks;

	if (!isset($integration_hooks))
	{
		$integration_hooks = array();
		foreach ($modSettings as $key => $value)
		{
			if (!empty($value) && substr($key, 0, 10) === 'integrate_')
				$integration_hooks[$key] = explode(',', $value);
		}
	}

	return $integration_hooks;
}

/**
 * Parses each hook data and returns an array.
 *
 * @param string $rawData A string as it was saved to the DB.
 * @return array everything found in the string itself
 */
function get_hook_info_from_raw($rawData)
{
	global $boarddir, $settings, $sourcedir;

	// A single string can hold tons of info!
	$hookData = array(
		'object' => false,
		'enabled' => true,
		'fileExists' => false,
		'absPath' => '',
		'hookFile' => '',
		'pureFunc' => '',
		'method' => '',
		'class' => '',
		'rawData' => $rawData,
	);

	// Meh...
	if (empty($rawData))
		return $hookData;

	// For convenience purposes only!
	$modFunc = $rawData;

	// Any files?
	if (strpos($modFunc, '|') !== false)
	{
		list ($hookData['hookFile'], $modFunc) = explode('|', $modFunc);

		// Does the file exists? who knows!
		if (empty($settings['theme_dir']))
			$hookData['absPath'] = strtr(trim($hookData['hookFile']), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir));

		else
			$hookData['absPath'] = strtr(trim($hookData['hookFile']), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));

		$hookData['fileExists'] = file_exists($hookData['absPath']);
		$hookData['hookFile'] = basename($hookData['hookFile']);
	}

	// Hook is an instance.
	if (strpos($modFunc, '#') !== false)
	{
		$modFunc = str_replace('#', '', $modFunc);
		$hookData['object'] = true;
	}

	// Hook is "disabled"
	if (strpos($modFunc, '!') !== false)
	{
		$modFunc = str_replace('!', '', $modFunc);
		$hookData['enabled'] = false;
	}

	// Handling methods?
	if (strpos($modFunc, '::') !== false)
	{
		list ($hookData['class'], $hookData['method']) = explode('::', $modFunc);
		$hookData['pureFunc'] = $hookData['method'];
	}

	else
		$hookData['pureFunc'] = $modFunc;

	return $hookData;
}

/**
 * Converts html entities to utf8 equivalents
 * special db wrapper for mysql based on the limitation of mysql/mb3
 *
 * Callback function for preg_replace_callback
 * Uses capture group 1 in the supplied array
 * Does basic checks to keep characters inside a viewable range.
 *
 * @param array $matches An array of matches (relevant info should be the 2nd item in the array)
 * @return string The fixed string or return the old when limitation of mysql is hit
 */
function fixchardb__callback($matches)
{
	global $smcFunc;
	if (!isset($matches[1]))
		return '';

	$num = $matches[1][0] === 'x' ? hexdec(substr($matches[1], 1)) : (int) $matches[1];

	// it's to big for mb3?
	if ($num > 0xFFFF && !$smcFunc['db_mb4'])
		return $matches[0];
	else
		return fixchar__callback($matches);
}

?>