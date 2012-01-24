<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

// The template for adding or editing a subscription.
function template_modify_subscription()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Javascript for the duration stuff.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			function toggleDuration(toChange)
			{
				if (toChange == \'fixed\')
				{
					document.getElementById("fixed_area").style.display = "inline";
					document.getElementById("flexible_area").style.display = "none";
				}
				else
				{
					document.getElementById("fixed_area").style.display = "none";
					document.getElementById("flexible_area").style.display = "inline";
				}
			}
		// ]]></script>';

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], '" method="post">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['paid_' . $context['action_type'] . '_subscription'], '</h3>
			</div>';

	if (!empty($context['disable_groups']))
		echo '
			<div class="information">
				<span class="alert">', $txt['paid_mod_edit_note'], '</span>
			</div>
			';
	echo '
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							', $txt['paid_mod_name'], ':
						</dt>
						<dd>
							<input type="text" name="name" value="', $context['sub']['name'], '" size="30" class="input_text" />
						</dd>
						<dt>
							', $txt['paid_mod_desc'], ':
						</dt>
						<dd>
							<textarea name="desc" rows="3" cols="40">', $context['sub']['desc'], '</textarea>
						</dd>
						<dt>
							<label for="repeatable_check">', $txt['paid_mod_repeatable'], '</label>:
						</dt>
						<dd>
							<input type="checkbox" name="repeatable" id="repeatable_check"', empty($context['sub']['repeatable']) ? '' : ' checked="checked"', ' class="input_check" />
						</dd>
						<dt>
							<label for="activated_check">', $txt['paid_mod_active'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_active_desc'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="active" id="activated_check"', empty($context['sub']['active']) ? '' : ' checked="checked"', ' class="input_check" />
						</dd>
					</dl>
					<hr class="hrcolor" />
					<dl class="settings">
						<dt>
							', $txt['paid_mod_prim_group'], ':<br /><span class="smalltext">', $txt['paid_mod_prim_group_desc'], '</span>
						</dt>
						<dd>
							<select name="prim_group" ', !empty($context['disable_groups']) ? 'disabled="disabled"' : '', '>
								<option value="0" ', $context['sub']['prim_group'] == 0 ? 'selected="selected"' : '', '>', $txt['paid_mod_no_group'], '</option>';

	// Put each group into the box.
	foreach ($context['groups'] as $id => $name)
		echo '
								<option value="', $id, '" ', $context['sub']['prim_group'] == $id ? 'selected="selected"' : '', '>', $name, '</option>';

	echo '
							</select>
						</dd>
						<dt>
							', $txt['paid_mod_add_groups'], ':<br /><span class="smalltext">', $txt['paid_mod_add_groups_desc'], '</span>
						</dt>
						<dd>';

	// Put a checkbox in for each group
	foreach ($context['groups'] as $id => $name)
		echo '
							<label for="addgroup_', $id, '"><input type="checkbox" id="addgroup_', $id, '" name="addgroup[', $id, ']"', in_array($id, $context['sub']['add_groups']) ? ' checked="checked"' : '', ' ', !empty($context['disable_groups']) ? ' disabled="disabled"' : '', ' class="input_check" />&nbsp;<span class="smalltext">', $name, '</span></label><br />';

	echo '
						</dd>
						<dt>
							', $txt['paid_mod_reminder'], ':<br /><span class="smalltext">', $txt['paid_mod_reminder_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="reminder" value="', $context['sub']['reminder'], '" size="6" class="input_text" />
						</dd>
						<dt>
							', $txt['paid_mod_email'], ':<br /><span class="smalltext">', $txt['paid_mod_email_desc'], '</span>
						</dt>
						<dd>
							<textarea name="emailcomplete" rows="6" cols="40">', $context['sub']['email_complete'], '</textarea>
						</dd>
					</dl>
					<hr class="hrcolor" />
					<input type="radio" name="duration_type" id="duration_type_fixed" value="fixed" ', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? 'checked="checked"' : '', ' class="input_radio" onclick="toggleDuration(\'fixed\');" />
					<strong>', $txt['paid_mod_fixed_price'], '</strong>
					<br />
					<div id="fixed_area" ', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? '' : 'style="display: none;"', '>
						<fieldset>
							<dl class="settings">
								<dt>
									', $txt['paid_cost'], ' (', str_replace('%1.2f', '', $modSettings['paid_currency_symbol']), '):
								</dt>
								<dd>
									<input type="text" name="cost" value="', empty($context['sub']['cost']['fixed']) ? '0' : $context['sub']['cost']['fixed'], '" size="4" class="input_text" />
								</dd>
								<dt>
									', $txt['paid_mod_span'], ':
								</dt>
								<dd>
									<input type="text" name="span_value" value="', $context['sub']['span']['value'], '" size="4" class="input_text" />
									<select name="span_unit">
										<option value="D" ', $context['sub']['span']['unit'] == 'D' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_days'], '</option>
										<option value="W" ', $context['sub']['span']['unit'] == 'W' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_weeks'], '</option>
										<option value="M" ', $context['sub']['span']['unit'] == 'M' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_months'], '</option>
										<option value="Y" ', $context['sub']['span']['unit'] == 'Y' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_years'], '</option>
									</select>
								</dd>
							</dl>
						</fieldset>
					</div>
					<input type="radio" name="duration_type" id="duration_type_flexible" value="flexible" ', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? 'checked="checked"' : '', ' class="input_radio" onclick="toggleDuration(\'flexible\');" />
					<strong>', $txt['paid_mod_flexible_price'], '</strong>
					<br />
					<div id="flexible_area" ', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? '' : 'style="display: none;"', '>
						<fieldset>';

	//!! Removed until implemented
	if (!empty($sdflsdhglsdjgs))
		echo '
							<dl class="settings">
								<dt>
									<label for="allow_partial_check">', $txt['paid_mod_allow_partial'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_allow_partial_desc'], '</span>
								</dt>
								<dd>
									<input type="checkbox" name="allow_partial" id="allow_partial_check"', empty($context['sub']['allow_partial']) ? '' : ' checked="checked"', ' class="input_check" />
								</dd>
							</dl>';

	echo '
							<div class="information">
								<strong>', $txt['paid_mod_price_breakdown'], '</strong><br />
								', $txt['paid_mod_price_breakdown_desc'], '
							</div>
							<dl class="settings">
								<dt>
									<strong>', $txt['paid_duration'], '</strong>
								</dt>
								<dd>
									<strong>', $txt['paid_cost'], ' (', preg_replace('~%[df\.\d]+~', '', $modSettings['paid_currency_symbol']), ')</strong>
								</dd>
								<dt>
									', $txt['paid_per_day'], ':
								</dt>
								<dd>
									<input type="text" name="cost_day" value="', empty($context['sub']['cost']['day']) ? '0' : $context['sub']['cost']['day'], '" size="5" class="input_text" />
								</dd>
								<dt>
									', $txt['paid_per_week'], ':
								</dt>
								<dd>
									<input type="text" name="cost_week" value="', empty($context['sub']['cost']['week']) ? '0' : $context['sub']['cost']['week'], '" size="5" class="input_text" />
								</dd>
								<dt>
									', $txt['paid_per_month'], ':
								</dt>
								<dd>
									<input type="text" name="cost_month" value="', empty($context['sub']['cost']['month']) ? '0' : $context['sub']['cost']['month'], '" size="5" class="input_text" />
								</dd>
								<dt>
									', $txt['paid_per_year'], ':
								</dt>
								<dd>
									<input type="text" name="cost_year" value="', empty($context['sub']['cost']['year']) ? '0' : $context['sub']['cost']['year'], '" size="5" class="input_text" />
								</dd>
							</dl>
						</fieldset>
					</div>
					<div class="righttext">
						<input type="submit" name="save" value="', $txt['paid_settings_save'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';

}

function template_delete_subscription()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], ';delete" method="post">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['paid_delete_subscription'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>', $txt['paid_mod_delete_warning'], '</p>

					<input type="submit" name="delete_confirm" value="', $txt['paid_delete_subscription'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';

}

// Add or edit an existing subscriber.
function template_modify_user_subscription()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Some quickly stolen javascript from Post, could do with being more efficient :)
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
			var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

			function generateDays(offset)
			{
				var days = 0, selected = 0;
				var dayElement = document.getElementById("day" + offset), yearElement = document.getElementById("year" + offset), monthElement = document.getElementById("month" + offset);

				monthLength[1] = 28;
				if (yearElement.options[yearElement.selectedIndex].value % 4 == 0)
					monthLength[1] = 29;

				selected = dayElement.selectedIndex;
				while (dayElement.options.length)
					dayElement.options[0] = null;

				days = monthLength[monthElement.value - 1];

				for (i = 1; i <= days; i++)
					dayElement.options[dayElement.length] = new Option(i, i);

				if (selected < days)
					dayElement.selectedIndex = selected;
			}
		// ]]></script>';

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $context['sub_id'], ';lid=', $context['log_id'], '" method="post">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['paid_' . $context['action_type'] . '_subscription'], ' - ', $context['current_subscription']['name'], '
					', empty($context['sub']['username']) ? '' : ' (' . $txt['user'] . ': ' . $context['sub']['username'] . ')', '
				</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">';

	// Do we need a username?
	if ($context['action_type'] == 'add')
		echo '

						<dt>
							<strong>', $txt['paid_username'], ':</strong><br />
							<span class="smalltext">', $txt['one_username'], '</span>
						</dt>
						<dd>
							<input type="text" name="name" id="name_control" value="', $context['sub']['username'], '" size="30" class="input_text" />
						</dd>';

	echo '
						<dt>
							<strong>', $txt['paid_status'], ':</strong>
						</dt>
						<dd>
							<select name="status">
								<option value="0" ', $context['sub']['status'] == 0 ? 'selected="selected"' : '', '>', $txt['paid_finished'], '</option>
								<option value="1" ', $context['sub']['status'] == 1 ? 'selected="selected"' : '', '>', $txt['paid_active'], '</option>
							</select>
						</dd>
					</dl>
					<fieldset>
						<legend>', $txt['start_date_and_time'], '</legend>
						<select name="year" id="year" onchange="generateDays(\'\');">';

	// Show a list of all the years we allow...
	for ($year = 2005; $year <= 2030; $year++)
		echo '
							<option value="', $year, '"', $year == $context['sub']['start']['year'] ? ' selected="selected"' : '', '>', $year, '</option>';

	echo '
						</select>&nbsp;
						', (isset($txt['calendar_month']) ? $txt['calendar_month'] : $txt['calendar_month']), '&nbsp;
						<select name="month" id="month" onchange="generateDays(\'\');">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == $context['sub']['start']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '</option>';

	echo '
						</select>&nbsp;
						', (isset($txt['calendar_day']) ? $txt['calendar_day'] : $txt['calendar_day']), '&nbsp;
						<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['sub']['start']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == $context['sub']['start']['day'] ? ' selected="selected"' : '', '>', $day, '</option>';

	echo '
						</select>
						', $txt['hour'], ': <input type="text" name="hour" value="', $context['sub']['start']['hour'], '" size="2" class="input_text" />
						', $txt['minute'], ': <input type="text" name="minute" value="', $context['sub']['start']['min'], '" size="2" class="input_text" />
					</fieldset>
					<fieldset>
						<legend>', $txt['end_date_and_time'], '</legend>
						<select name="yearend" id="yearend" onchange="generateDays(\'end\');">';

	// Show a list of all the years we allow...
	for ($year = 2005; $year <= 2030; $year++)
		echo '
							<option value="', $year, '"', $year == $context['sub']['end']['year'] ? ' selected="selected"' : '', '>', $year, '</option>';

	echo '
						</select>&nbsp;
						', (isset($txt['calendar_month']) ? $txt['calendar_month'] : $txt['calendar_month']), '&nbsp;
						<select name="monthend" id="monthend" onchange="generateDays(\'end\');">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == $context['sub']['end']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '</option>';

	echo '
						</select>&nbsp;
						', (isset($txt['calendar_day']) ? $txt['calendar_day'] : $txt['calendar_day']), '&nbsp;
						<select name="dayend" id="dayend">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['sub']['end']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == $context['sub']['end']['day'] ? ' selected="selected"' : '', '>', $day, '</option>';

	echo '
						</select>
						', $txt['hour'], ': <input type="text" name="hourend" value="', $context['sub']['end']['hour'], '" size="2" class="input_text" />
						', $txt['minute'], ': <input type="text" name="minuteend" value="', $context['sub']['end']['min'], '" size="2" class="input_text" />
					</fieldset>
					<input type="submit" name="save_sub" value="', $txt['paid_settings_save'], '" class="button_submit" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: \'', $context['session_id'], '\',
			sSessionVar: \'', $context['session_var'], '\',
			sSuggestId: \'name_subscriber\',
			sControlId: \'name_control\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
			});
		// ]]></script>';

	if (!empty($context['pending_payments']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pending_payments'], '</h3>
		</div>
		<div class="information">
		', $txt['pending_payments_desc'], '
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pending_payments_value'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<ul class="pending_payments">';

		foreach ($context['pending_payments'] as $id => $payment)
		{
			echo '
					<li class="reset">
						', $payment['desc'], '
						<span class="floatleft"><a href="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;lid=', $context['log_id'], ';pending=', $id, ';accept">', $txt['pending_payments_accept'], '</a></span>
						<span class="floatright"><a href="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;lid=', $context['log_id'], ';pending=', $id, ';remove">', $txt['pending_payments_remove'], '</a></span>
					</li>';
		}

		echo '
				</ul>
			</div>
			<span class="botslice"><span></span></span>
		</div>';
	}

	echo '
		</div>
	<br class="clear" />';
}

// Template for a user to edit/pick their subscriptions.
function template_user_subscription()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<div id="paid_subscription">
		<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=subscriptions;confirm" method="post">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['subscriptions'], '</h3>
			</div>';

	if (empty($context['subscriptions']))
	{
		echo '
			<div class="information">
				', $txt['paid_subs_none'], '
			</div>';
	}
	else
	{
		echo '
			<div class="information">
				', $txt['paid_subs_desc'], '
			</div>';

		// Print out all the subscriptions.
		$alternate = false;
		foreach ($context['subscriptions'] as $id => $subscription)
		{
			$alternate = !$alternate;

			// Ignore the inactive ones...
			if (empty($subscription['active']))
				continue;

			echo '
			<div class="cat_bar">
				<h3 class="catbg">', $subscription['name'], '</h3>
			</div>
			<div class="windowbg', $alternate ? '' : '2', '">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p><strong>', $subscription['name'], '</strong></p>
					<p class="smalltext">', $subscription['desc'], '</p>';

			if (!$subscription['flexible'])
				echo '
					<div><strong>', $txt['paid_duration'], ':</strong> ', $subscription['length'], '</div>';

			if ($context['user']['is_owner'])
			{
				echo '
					<strong>', $txt['paid_cost'], ':</strong>';

				if ($subscription['flexible'])
				{
					echo '
					<select name="cur[', $subscription['id'], ']">';

					// Print out the costs for this one.
					foreach ($subscription['costs'] as $duration => $value)
						echo '
						<option value="', $duration, '">', sprintf($modSettings['paid_currency_symbol'], $value), '/', $txt[$duration], '</option>';

					echo '
					</select>';
				}
				else
					echo '
					', sprintf($modSettings['paid_currency_symbol'], $subscription['costs']['fixed']);

				echo '
					<br />
					<input type="submit" name="sub_id[', $subscription['id'], ']" value="', $txt['paid_order'], '" class="button_submit" />';
			}
			else
				echo '
					<a href="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $subscription['id'], ';uid=', $context['member']['id'], (empty($context['current'][$subscription['id']]) ? '' : ';lid=' . $context['current'][$subscription['id']]['id']), '">', empty($context['current'][$subscription['id']]) ? $txt['paid_admin_add'] : $txt['paid_edit_subscription'], '</a>';

			echo '
				</div>
				<span class="botslice"><span></span></span>
			</div>';
		}
	}

	echo '
		</form>
		<br />
		<div class="title_bar">
			<h3 class="titlebg">', $txt['paid_current'], '</h3>
		</div>
		<div class="information">
			', $txt['paid_current_desc'], '
		</div>
		<table width="100%" class="table_grid">
			<thead>
				<tr class="catbg">
					<th class="first_th" width="30%">', $txt['paid_name'], '</th>
					<th align="center">', $txt['paid_status'], '</th>
					<th align="center">', $txt['start_date'], '</th>
					<th class="last_th" align="center">', $txt['end_date'], '</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['current']))
		echo '
				<tr class="windowbg">
					<td align="center" colspan="4">
						', $txt['paid_none_yet'], '
					</td>
				</tr>';

	$alternate = false;
	foreach ($context['current'] as $sub)
	{
		$alternate = !$alternate;

		if (!$sub['hide'])
			echo '
				<tr class="windowbg', $alternate ? '' : '2', '">
					<td>
						', (allowedTo('admin_forum') ? '<a href="' . $scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $sub['id'] . '">' . $sub['name'] . '</a>' : $sub['name']), '
					</td><td>
						<span style="color: ', ($sub['status'] == 2 ? 'green' : ($sub['status'] == 1 ? 'red' : 'orange')), '"><strong>', $sub['status_text'], '</strong></span>
					</td><td>
						', $sub['start'], '
					</td><td>
						', $sub['end'], '
					</td>
				</tr>';
	}
	echo '
			</tbody>
		</table>
	</div>
	<br class="clear" />';
}

// The "choose payment" dialog.
function template_choose_payment()
{
	global $context, $txt, $modSettings, $scripturl;

	echo '
	<div id="paid_subscription">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['paid_confirm_payment'], '</h3>
		</div>
		<div class="information">
			', $txt['paid_confirm_desc'], '
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['subscription'], ':</strong>
					</dt>
					<dd>
						', $context['sub']['name'], '
					</dd>
					<dt>
						<strong>', $txt['paid_cost'], ':</strong>
					</dt>
					<dd>
						', $context['cost'], '
					</dd>
				</dl>
			</div>
			<span class="botslice"><span></span></span>
		</div>';

	// Do all the gateway options.
	foreach ($context['gateways'] as $gateway)
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $gateway['title'], '</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				', $gateway['desc'], '<br />
					<form action="', $gateway['form'], '" method="post">';

		if (!empty($gateway['javascript']))
			echo '
						<script type="text/javascript"><!-- // --><![CDATA[
							', $gateway['javascript'], '
						// ]]></script>';

		foreach ($gateway['hidden'] as $name => $value)
			echo '
						<input type="hidden" id="', $gateway['id'], '_', $name, '" name="', $name, '" value="', $value, '" />';

		echo '
						<br /><input type="submit" value="', $gateway['submit'], '" class="button_submit" />
					</form>
			</div>
			<span class="botslice"><span></span></span>
		</div>';
	}

	echo '
	</div>
	<br class="clear" />';
}

// The "thank you" bit...
function template_paid_done()
{
	global $context, $txt, $modSettings, $scripturl;

	echo '
	<div id="paid_subscription">
		<div class="title_bar">
			<h3 class="titlebg">', $txt['paid_done'], '</h3>
		</div>
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<p>', $txt['paid_done_desc'], '</p>
				<br />
				<a href="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=subscriptions">', $txt['paid_sub_return'], '</a>
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
}

?>