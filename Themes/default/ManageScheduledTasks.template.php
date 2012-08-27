<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

// Template for listing all scheduled tasks.
function template_view_scheduled_tasks()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// We completed some tasks?
	if (!empty($context['tasks_were_run']))
		echo '
	<div id="task_completed">
		', $txt['scheduled_tasks_were_run'], '
	</div>';

	template_show_list('scheduled_tasks');
}

// A template for, you guessed it, editing a task!
function template_edit_scheduled_tasks()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Starts off with general maintenance procedures.
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=scheduledtasks;sa=taskedit;save;tid=', $context['task']['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['scheduled_task_edit'], '</h3>
			</div>
			<div class="information">
				<em>', sprintf($txt['scheduled_task_time_offset'], $context['server_time']), ' </em>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['scheduled_tasks_name'], ':</strong>
						</dt>
						<dd>
							', $context['task']['name'], '<br />
							<span class="smalltext">', $context['task']['desc'], '</span>
						</dd>
						<dt>
							<strong><label for="regularity">', $txt['scheduled_task_edit_interval'], ':</label></strong>
						</dt>
						<dd>
							', $txt['scheduled_task_edit_repeat'], '
							<input type="text" name="regularity" id="regularity" value="', empty($context['task']['regularity']) ? 1 : $context['task']['regularity'], '" onchange="if (this.value < 1) this.value = 1;" size="2" maxlength="2" class="input_text" />
							<select name="unit">
								<option value="0">', $txt['scheduled_task_edit_pick_unit'], '</option>
								<option value="0">---------------------</option>
								<option value="m" ', empty($context['task']['unit']) || $context['task']['unit'] == 'm' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_m'], '</option>
								<option value="h" ', $context['task']['unit'] == 'h' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_h'], '</option>
								<option value="d" ', $context['task']['unit'] == 'd' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_d'], '</option>
								<option value="w" ', $context['task']['unit'] == 'w' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_w'], '</option>
							</select>
						</dd>
						<dt>
							<strong><label for="start_time">', $txt['scheduled_task_edit_start_time'], ':</label></strong><br />
							<span class="smalltext">', $txt['scheduled_task_edit_start_time_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="offset" id="start_time" value="', $context['task']['offset_formatted'], '" size="6" maxlength="5" class="input_text" />
						</dd>
						<dt>
							<strong><label for="enabled">', $txt['scheduled_tasks_enabled'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="enabled" id="enabled" ', !$context['task']['disabled'] ? 'checked="checked"' : '', ' class="input_check" />
						</dd>
					</dl>
					<div class="righttext">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-st_token_var'], '" value="', $context['admin-st_token'], '" />
						<input type="submit" name="save" value="', $txt['scheduled_tasks_save_changes'], '" class="button_submit" />
					</div>
				</div>
			</div>
		</form>
	</div>';
}

?>