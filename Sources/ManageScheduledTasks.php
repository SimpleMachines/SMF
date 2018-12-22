<?php

/**
 * This file concerns itself with scheduled tasks management.
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
 * Scheduled tasks management dispatcher. This function checks permissions and delegates
 * to the appropriate function based on the sub-action.
 * Everything here requires admin_forum permission.
 *
 * @uses ManageScheduledTasks template file
 * @uses ManageScheduledTasks language file
 */
function ManageScheduledTasks()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadLanguage('ManageScheduledTasks');
	loadTemplate('ManageScheduledTasks');

	$subActions = array(
		'taskedit' => 'EditTask',
		'tasklog' => 'TaskLog',
		'tasks' => 'ScheduledTasks',
		'settings' => 'TaskSettings',
	);

	// We need to find what's the action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'tasks';

	// Now for the lovely tabs. That we all love.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['scheduled_tasks_title'],
		'help' => '',
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'tasks' => array(
				'description' => $txt['maintain_tasks_desc'],
			),
			'tasklog' => array(
				'description' => $txt['scheduled_log_desc'],
			),
			'settings' => array(
				'description' => $txt['scheduled_tasks_settings_desc'],
			),
		),
	);

	call_integration_hook('integrate_manage_scheduled_tasks', array(&$subActions));

	// Call it.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * List all the scheduled task in place on the forum.
 *
 * @uses ManageScheduledTasks template, view_scheduled_tasks sub-template
 */
function ScheduledTasks()
{
	global $context, $txt, $sourcedir, $smcFunc, $scripturl;

	// Mama, setup the template first - cause it's like the most important bit, like pickle in a sandwich.
	// ... ironically I don't like pickle. </grudge>
	$context['sub_template'] = 'view_scheduled_tasks';
	$context['page_title'] = $txt['maintain_tasks'];

	// Saving changes?
	if (isset($_REQUEST['save']) && isset($_POST['enable_task']))
	{
		checkSession();

		// We'll recalculate the dates at the end!
		require_once($sourcedir . '/ScheduledTasks.php');

		// Enable and disable as required.
		$enablers = array(0);
		foreach ($_POST['enable_task'] as $id => $enabled)
			if ($enabled)
				$enablers[] = (int) $id;

		// Do the update!
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
			array(
				'id_task_enable' => $enablers,
			)
		);

		// Update the "allow_expire_redirect" setting...
		$get_info = $smcFunc['db_query']('', '
			SELECT disabled
			FROM {db_prefix}scheduled_tasks
			WHERE task = {string:remove_redirect}',
			array(
				'remove_redirect' => 'remove_topic_redirect'
			)
		);

		$temp = $smcFunc['db_fetch_assoc']($get_info);
		$task_disabled = !empty($temp['disabled']) ? 0 : 1;
		$smcFunc['db_free_result']($get_info);

		updateSettings(array('allow_expire_redirect' => $task_disabled));

		// Pop along...
		CalculateNextTrigger();
	}

	// Want to run any of the tasks?
	if (isset($_REQUEST['run']) && isset($_POST['run_task']))
	{
		$task_string = '';

		// Lets figure out which ones they want to run.
		$tasks = array();
		foreach ($_POST['run_task'] as $task => $dummy)
			$tasks[] = (int) $task;

		// Load up the tasks.
		$request = $smcFunc['db_query']('', '
			SELECT id_task, task, callable
			FROM {db_prefix}scheduled_tasks
			WHERE id_task IN ({array_int:tasks})
			LIMIT {int:limit}',
			array(
				'tasks' => $tasks,
				'limit' => count($tasks),
			)
		);

		// Lets get it on!
		require_once($sourcedir . '/ScheduledTasks.php');
		ignore_user_abort(true);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// What kind of task are we handling?
			if (!empty($row['callable']))
				$task_string = $row['callable'];

			// Default SMF task or old mods?
			elseif (function_exists('scheduled_' . $row['task']))
				$task_string = 'scheduled_' . $row['task'];

			// One last resource, the task name.
			elseif (!empty($row['task']))
				$task_string = $row['task'];

			$start_time = microtime(true);
			// The functions got to exist for us to use it.
			if (empty($task_string))
				continue;

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			// Get the callable.
			$callable_task = call_helper($task_string, true);

			// Perform the task.
			if (!empty($callable_task))
				$completed = call_user_func($callable_task);

			else
				$completed = false;

			// Log that we did it ;)
			if ($completed)
			{
				$total_time = round(microtime(true) - $start_time, 3);
				$smcFunc['db_insert']('',
					'{db_prefix}log_scheduled_tasks',
					array('id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float'),
					array($row['id_task'], time(), $total_time),
					array('id_task')
				);
			}
		}
		$smcFunc['db_free_result']($request);

		// If we had any errors, push them to session so we can pick them up next time to tell the user.
		if (!empty($context['scheduled_errors']))
			$_SESSION['st_error'] = $context['scheduled_errors'];

		redirectexit('action=admin;area=scheduledtasks;done');
	}

	if (isset($_SESSION['st_error']))
	{
		$context['scheduled_errors'] = $_SESSION['st_error'];
		unset ($_SESSION['st_error']);
	}

	$listOptions = array(
		'id' => 'scheduled_tasks',
		'title' => $txt['maintain_tasks'],
		'base_href' => $scripturl . '?action=admin;area=scheduledtasks',
		'get_items' => array(
			'function' => 'list_getScheduledTasks',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_name'],
					'style' => 'width: 40%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '
							<a href="' . $scripturl . '?action=admin;area=scheduledtasks;sa=taskedit;tid=%1$d">%2$s</a><br><span class="smalltext">%3$s</span>',
						'params' => array(
							'id' => false,
							'name' => false,
							'desc' => false,
						),
					),
				),
			),
			'next_due' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_next_time'],
				),
				'data' => array(
					'db' => 'next_time',
					'class' => 'smalltext',
				),
			),
			'regularity' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_regularity'],
				),
				'data' => array(
					'db' => 'regularity',
					'class' => 'smalltext',
				),
			),
			'run_now' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_run_now'],
					'style' => 'width: 12%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' =>
							'<input type="checkbox" name="run_task[%1$d]" id="run_task_%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'class' => 'centercol',
				),
			),
			'enabled' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_enabled'],
					'style' => 'width: 6%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' =>
							'<input type="hidden" name="enable_task[%1$d]" id="task_%1$d" value="0"><input type="checkbox" name="enable_task[%1$d]" id="task_check_%1$d" %2$s>',
						'params' => array(
							'id' => false,
							'checked_state' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=scheduledtasks',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="save" value="' . $txt['scheduled_tasks_save_changes'] . '" class="button">
					<input type="submit" name="run" value="' . $txt['scheduled_tasks_run_now'] . '" class="button">',
			),
			array(
				'position' => 'after_title',
				'value' => $txt['scheduled_tasks_time_offset'],
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'view_scheduled_tasks';

	$context['tasks_were_run'] = isset($_GET['done']);
}

/**
 * Callback function for createList() in ScheduledTasks().
 *
 * @param int $start The item to start with (not used here)
 * @param int $items_per_page The number of items to display per page (not used here)
 * @param string $sort A string indicating how to sort things (not used here)
 * @return array An array of information about available scheduled tasks
 */
function list_getScheduledTasks($start, $items_per_page, $sort)
{
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks',
		array(
		)
	);
	$known_tasks = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Find the next for regularity - don't offset as it's always server time!
		$offset = sprintf($txt['scheduled_task_reg_starting'], date('H:i', $row['time_offset']));
		$repeating = sprintf($txt['scheduled_task_reg_repeating'], $row['time_regularity'], $txt['scheduled_task_reg_unit_' . $row['time_unit']]);

		$known_tasks[] = array(
			'id' => $row['id_task'],
			'function' => $row['task'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat(($row['next_time'] == 0 ? time() : $row['next_time']), true, 'server'),
			'disabled' => $row['disabled'],
			'checked_state' => $row['disabled'] ? '' : 'checked',
			'regularity' => $offset . ', ' . $repeating,
		);
	}
	$smcFunc['db_free_result']($request);

	return $known_tasks;
}

/**
 * Function for editing a task.
 *
 * @uses ManageScheduledTasks template, edit_scheduled_tasks sub-template
 */
function EditTask()
{
	global $context, $txt, $sourcedir, $smcFunc;

	// Just set up some lovely context stuff.
	$context[$context['admin_menu_name']]['current_subsection'] = 'tasks';
	$context['sub_template'] = 'edit_scheduled_tasks';
	$context['page_title'] = $txt['scheduled_task_edit'];
	$context['server_time'] = timeformat(time(), false, 'server');

	// Cleaning...
	if (!isset($_GET['tid']))
		fatal_lang_error('no_access', false);
	$_GET['tid'] = (int) $_GET['tid'];

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();
		validateToken('admin-st');

		// We'll need this for calculating the next event.
		require_once($sourcedir . '/ScheduledTasks.php');

		// Do we have a valid offset?
		preg_match('~(\d{1,2}):(\d{1,2})~', $_POST['offset'], $matches);

		// If a half is empty then assume zero offset!
		if (!isset($matches[2]) || $matches[2] > 59)
			$matches[2] = 0;
		if (!isset($matches[1]) || $matches[1] > 23)
			$matches[1] = 0;

		// Now the offset is easy; easy peasy - except we need to offset by a few hours...
		$offset = $matches[1] * 3600 + $matches[2] * 60 - date('Z');

		// The other time bits are simple!
		$interval = max((int) $_POST['regularity'], 1);
		$unit = in_array(substr($_POST['unit'], 0, 1), array('m', 'h', 'd', 'w')) ? substr($_POST['unit'], 0, 1) : 'd';

		// Don't allow one minute intervals.
		if ($interval == 1 && $unit == 'm')
			$interval = 2;

		// Is it disabled?
		$disabled = !isset($_POST['enabled']) ? 1 : 0;

		// Do the update!
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = {int:disabled}, time_offset = {int:time_offset}, time_unit = {string:time_unit},
				time_regularity = {int:time_regularity}
			WHERE id_task = {int:id_task}',
			array(
				'disabled' => $disabled,
				'time_offset' => $offset,
				'time_regularity' => $interval,
				'id_task' => $_GET['tid'],
				'time_unit' => $unit,
			)
		);

		// Check the next event.
		CalculateNextTrigger($_GET['tid'], true);

		// Return to the main list.
		redirectexit('action=admin;area=scheduledtasks');
	}

	// Load the task, understand? Que? Que?
	$request = $smcFunc['db_query']('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task = {int:id_task}',
		array(
			'id_task' => $_GET['tid'],
		)
	);

	// Should never, ever, happen!
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['task'] = array(
			'id' => $row['id_task'],
			'function' => $row['task'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat($row['next_time'] == 0 ? time() : $row['next_time'], true, 'server'),
			'disabled' => $row['disabled'],
			'offset' => $row['time_offset'],
			'regularity' => $row['time_regularity'],
			'offset_formatted' => date('H:i', $row['time_offset']),
			'unit' => $row['time_unit'],
		);
	}
	$smcFunc['db_free_result']($request);

	createToken('admin-st');
}

/**
 * Show the log of all tasks that have taken place.
 *
 * @uses ManageScheduledTasks language file
 */
function TaskLog()
{
	global $scripturl, $context, $txt, $smcFunc, $sourcedir;

	// Lets load the language just incase we are outside the Scheduled area.
	loadLanguage('ManageScheduledTasks');

	// Empty the log?
	if (!empty($_POST['removeAll']))
	{
		checkSession();
		validateToken('admin-tl');

		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_scheduled_tasks',
			array(
			)
		);
	}

	// Setup the list.
	$listOptions = array(
		'id' => 'task_log',
		'items_per_page' => 30,
		'title' => $txt['scheduled_log'],
		'no_items_label' => $txt['scheduled_log_empty'],
		'base_href' => $context['admin_area'] == 'scheduledtasks' ? $scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : $scripturl . '?action=admin;area=logs;sa=tasklog',
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getTaskLogEntries',
		),
		'get_count' => array(
			'function' => 'list_getNumTaskLogEntries',
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['scheduled_tasks_name'],
				),
				'data' => array(
					'db' => 'name'
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['scheduled_log_time_run'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return timeformat($rowData['time_run'], true);
					},
				),
				'sort' => array(
					'default' => 'lst.id_log DESC',
					'reverse' => 'lst.id_log',
				),
			),
			'time_taken' => array(
				'header' => array(
					'value' => $txt['scheduled_log_time_taken'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => $txt['scheduled_log_time_taken_seconds'],
						'params' => array(
							'time_taken' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'lst.time_taken',
					'reverse' => 'lst.time_taken DESC',
				),
			),
		),
		'form' => array(
			'href' => $context['admin_area'] == 'scheduledtasks' ? $scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : $scripturl . '?action=admin;area=logs;sa=tasklog',
			'token' => 'admin-tl',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<input type="submit" name="removeAll" value="' . $txt['scheduled_log_empty_log'] . '" data-confirm="' . $txt['scheduled_log_empty_log_confirm'] . '" class="button you_sure">',
			),
			array(
				'position' => 'after_title',
				'value' => $txt['scheduled_tasks_time_offset'],
			),
		),
	);

	createToken('admin-tl');

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'task_log';

	// Make it all look tify.
	$context[$context['admin_menu_name']]['current_subsection'] = 'tasklog';
	$context['page_title'] = $txt['scheduled_log'];
}

/**
 * Callback function for createList() in TaskLog().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to display per page
 * @param string $sort A string indicating how to sort the results
 * @return array An array of info about task log entries
 */
function list_getTaskLogEntries($start, $items_per_page, $sort)
{
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.task
		FROM {db_prefix}log_scheduled_tasks AS lst
			INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items}',
		array(
			'sort' => $sort,
			'start' => $start,
			'items' => $items_per_page,
		)
	);
	$log_entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$log_entries[] = array(
			'id' => $row['id_log'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'time_run' => $row['time_run'],
			'time_taken' => $row['time_taken'],
		);
	$smcFunc['db_free_result']($request);

	return $log_entries;
}

/**
 * Callback function for createList() in TaskLog().
 *
 * @return int The number of log entries
 */
function list_getNumTaskLogEntries()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_scheduled_tasks',
		array(
		)
	);
	list ($num_entries) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_entries;
}

function TaskSettings($return_config = false)
{
	global $sourcedir, $txt, $context, $scripturl;

	// We will need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	loadLanguage('Help');

	$config_vars = array(
		array('check', 'cron_is_real_cron', 'subtext' => $txt['cron_is_real_cron_desc'], 'help' => 'cron_is_real_cron'),
	);

	call_integration_hook('integrate_scheduled_tasks_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Set up the template.
	$context['page_title'] = $txt['scheduled_tasks_settings'];
	$context['sub_template'] = 'show_settings';

	$context['post_url'] = $scripturl . '?action=admin;area=scheduledtasks;save;sa=settings';
	$context['settings_title'] = $txt['scheduled_tasks_settings'];

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$save_vars = $config_vars;

		call_integration_hook('integrate_save_scheduled_tasks_settings', array(&$save_vars));

		saveDBSettings($save_vars);

		$_SESSION['adm-save'] = true;

		redirectexit('action=admin;area=scheduledtasks;sa=settings');
	}

	prepareDBSettingContext($config_vars);
}

?>