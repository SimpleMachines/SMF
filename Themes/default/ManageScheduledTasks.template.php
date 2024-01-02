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
		', Lang::$txt['scheduled_tasks_were_run'], '
	</div>';

		else
		{
			echo '
	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious">', Lang::$txt['scheduled_tasks_were_run_errors'], '</strong>
			</dt>';

			foreach (Utils::$context['scheduled_errors'] as $task => $errors)
				echo '
			<dd class="error">
				<strong>', isset(Lang::$txt['scheduled_task_' . $task]) ? Lang::$txt['scheduled_task_' . $task] : $task, '</strong>
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
		<form action="', Config::$scripturl, '?action=admin;area=scheduledtasks;sa=taskedit;save;tid=', Utils::$context['task']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['scheduled_task_edit'], '</h3>
			</div>
			<div class="information">
				<em>', sprintf(Lang::$txt['scheduled_task_time_offset'], Utils::$context['server_time']), ' </em>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', Lang::$txt['scheduled_tasks_name'], ':</strong>
					</dt>
					<dd>
						', Utils::$context['task']['name'], '<br>
						<span class="smalltext">', Utils::$context['task']['desc'], '</span>
					</dd>
					<dt>
						<strong><label for="regularity">', Lang::$txt['scheduled_task_edit_interval'], ':</label></strong>
					</dt>
					<dd>
						', Lang::$txt['scheduled_task_edit_repeat'], '
						<input type="text" name="regularity" id="regularity" value="', empty(Utils::$context['task']['regularity']) ? 1 : Utils::$context['task']['regularity'], '" onchange="if (this.value < 1) this.value = 1;" size="2" maxlength="2">
						<select name="unit">
							<option value="m"', empty(Utils::$context['task']['unit']) || Utils::$context['task']['unit'] == 'm' ? ' selected' : '', '>', Lang::$txt['scheduled_task_reg_unit_m'], '</option>
							<option value="h"', Utils::$context['task']['unit'] == 'h' ? ' selected' : '', '>', Lang::$txt['scheduled_task_reg_unit_h'], '</option>
							<option value="d"', Utils::$context['task']['unit'] == 'd' ? ' selected' : '', '>', Lang::$txt['scheduled_task_reg_unit_d'], '</option>
							<option value="w"', Utils::$context['task']['unit'] == 'w' ? ' selected' : '', '>', Lang::$txt['scheduled_task_reg_unit_w'], '</option>
						</select>
					</dd>
					<dt>
						<strong><label for="start_time">', Lang::$txt['scheduled_task_edit_start_time'], ':</label></strong><br>
						<span class="smalltext">', Lang::$txt['scheduled_task_edit_start_time_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="offset" id="start_time" value="', Utils::$context['task']['offset_formatted'], '" size="6" maxlength="5">
					</dd>
					<dt>
						<strong><label for="enabled">', Lang::$txt['scheduled_tasks_enabled'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="enabled" id="enabled"', !Utils::$context['task']['disabled'] ? ' checked' : '', '>
					</dd>
				</dl>
				<div class="righttext">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-st_token_var'], '" value="', Utils::$context['admin-st_token'], '">
					<input type="submit" name="save" value="', Lang::$txt['scheduled_tasks_save_changes'], '" class="button">
				</div>
			</div><!-- .windowbg -->
		</form>';
}

?>