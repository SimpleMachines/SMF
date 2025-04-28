<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * Template for listing all scheduled tasks.
 */
function template_view_scheduled_tasks()
{
	// We completed some tasks?
	if (!empty(Utils::$context['tasks_were_run']))
	{
		if (empty(Utils::$context['scheduled_errors']))
			echo '
	<div class="infobox">
		', Lang::getTxt('scheduled_tasks_were_run', file: 'ManageScheduledTasks'), '
	</div>';

		else
		{
			echo '
	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious">', Lang::getTxt('scheduled_tasks_were_run_errors', file: 'ManageScheduledTasks'), '</strong>
			</dt>';

			foreach (Utils::$context['scheduled_errors'] as $task => $errors)
				echo '
			<dd class="error">
				<strong>', Lang::txtExists('scheduled_task_' . $task, file: 'ManageScheduledTasks') ? Lang::getTxt('scheduled_task_' . $task, file: 'ManageScheduledTasks') : $task, '</strong>
				<ul>
					<li>', implode('</li><li>', $errors), '</li>
				</ul>
			</dd>';

			echo '
		</dl>
	</div>';
		}
	}

	template_show_list('scheduled_tasks');
}

/**
 * A template for, you guessed it, editing a task!
 */
function template_edit_scheduled_tasks()
{
	// Starts off with general maintenance procedures.
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=scheduledtasks;sa=taskedit;save;tid=', Utils::$context['task']['id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('scheduled_task_edit', file: 'ManageScheduledTasks'), '</h3>
			</div>
			<div class="information">
				<em>', Lang::getTxt('scheduled_task_time_offset', Utils::$context, file: 'ManageScheduledTasks'), ' </em>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', Lang::getTxt('scheduled_tasks_name', file: 'ManageScheduledTasks'), '</strong>
					</dt>
					<dd>
						', Utils::$context['task']['name'], '<br>
						<span class="smalltext">', Utils::$context['task']['desc'], '</span>
					</dd>
					<dt>
						<strong><label for="regularity">', Lang::getTxt('scheduled_task_edit_interval', file: 'ManageScheduledTasks'), '</label></strong>
					</dt>
					<dd>
						', Lang::getTxt('scheduled_task_edit_repeat', file: 'ManageScheduledTasks'), '
						<input type="number" name="regularity" id="regularity" value="', empty(Utils::$context['task']['regularity']) ? 1 : Utils::$context['task']['regularity'], '" onchange="if (this.value < 1) this.value = 1;" size="2" min="0">
						<select name="unit">
							<option value="m"', empty(Utils::$context['task']['unit']) || Utils::$context['task']['unit'] == 'm' ? ' selected' : '', '>', Lang::getTxt('scheduled_task_reg_unit_m', file: 'ManageScheduledTasks'), '</option>
							<option value="h"', Utils::$context['task']['unit'] == 'h' ? ' selected' : '', '>', Lang::getTxt('scheduled_task_reg_unit_h', file: 'ManageScheduledTasks'), '</option>
							<option value="d"', Utils::$context['task']['unit'] == 'd' ? ' selected' : '', '>', Lang::getTxt('scheduled_task_reg_unit_d', file: 'ManageScheduledTasks'), '</option>
							<option value="w"', Utils::$context['task']['unit'] == 'w' ? ' selected' : '', '>', Lang::getTxt('scheduled_task_reg_unit_w', file: 'ManageScheduledTasks'), '</option>
						</select>
					</dd>
					<dt>
						<strong><label for="start_time">', Lang::getTxt('scheduled_task_edit_start_time', file: 'ManageScheduledTasks'), '</label></strong><br>
						<span class="smalltext">', Lang::getTxt('scheduled_task_edit_start_time_desc', file: 'ManageScheduledTasks'), '</span>
					</dt>
					<dd>
						<input type="time" name="offset" id="start_time" value="', Utils::$context['task']['offset_formatted'], '">
					</dd>
					<dt>
						<strong><label for="enabled">', Lang::getTxt('scheduled_tasks_enabled', file: 'ManageScheduledTasks'), '</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="enabled" id="enabled"', !Utils::$context['task']['disabled'] ? ' checked' : '', '>
					</dd>
				</dl>
				<div class="righttext">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-st_token_var'], '" value="', Utils::$context['admin-st_token'], '">
					<input type="submit" name="save" value="', Lang::getTxt('scheduled_tasks_save_changes', file: 'ManageScheduledTasks'), '" class="button">
				</div>
			</div><!-- .windowbg -->
		</form>';
}
