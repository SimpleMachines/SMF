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
use SMF\User;

/**
 * The template for adding or editing a subscription.
 */
function template_modify_subscription()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', Utils::$context['sub_id'], '" method="post">';

	if (!empty(Utils::$context['disable_groups']))
		echo '
		<div class="noticebox">', Lang::$txt['paid_mod_edit_note'], '</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['paid_' . Utils::$context['action_type'] . '_subscription'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					', Lang::$txt['paid_mod_name'], ':
				</dt>
				<dd>
					<input type="text" name="name" value="', Utils::$context['sub']['name'], '" size="30">
				</dd>
				<dt>
					', Lang::$txt['paid_mod_desc'], ':
				</dt>
				<dd>
					<textarea name="desc" rows="3" cols="40">', Utils::$context['sub']['desc'], '</textarea>
				</dd>
				<dt>
					<label for="repeatable_check">', Lang::$txt['paid_mod_repeatable'], '</label>:
				</dt>
				<dd>
					<input type="checkbox" name="repeatable" id="repeatable_check"', empty(Utils::$context['sub']['repeatable']) ? '' : ' checked', '>
				</dd>
				<dt>
					<label for="activated_check">', Lang::$txt['paid_mod_active'], '</label>:<br><span class="smalltext">', Lang::$txt['paid_mod_active_desc'], '</span>
				</dt>
				<dd>
					<input type="checkbox" name="active" id="activated_check"', empty(Utils::$context['sub']['active']) ? '' : ' checked', '>
				</dd>
			</dl>
			<hr>
			<dl class="settings">
				<dt>
					', Lang::$txt['paid_mod_prim_group'], ':<br>
					<span class="smalltext">', Lang::$txt['paid_mod_prim_group_desc'], '</span>
				</dt>
				<dd>
					<select name="prim_group"', !empty(Utils::$context['disable_groups']) ? ' disabled' : '', '>
						<option value="0"', Utils::$context['sub']['prim_group'] == 0 ? ' selected' : '', '>', Lang::$txt['paid_mod_no_group'], '</option>';

	// Put each group into the box.
	foreach (Utils::$context['groups'] as $id => $name)
		echo '
						<option value="', $id, '"', Utils::$context['sub']['prim_group'] == $id ? ' selected' : '', '>', $name, '</option>';

	echo '
					</select>
				</dd>
				<dt>
					', Lang::$txt['paid_mod_add_groups'], ':<br>
					<span class="smalltext">', Lang::$txt['paid_mod_add_groups_desc'], '</span>
				</dt>
				<dd>';

	// Put a checkbox in for each group
	foreach (Utils::$context['groups'] as $id => $name)
		echo '
					<label for="addgroup_', $id, '">
						<input type="checkbox" id="addgroup_', $id, '" name="addgroup[', $id, ']"', in_array($id, Utils::$context['sub']['add_groups']) ? ' checked' : '', !empty(Utils::$context['disable_groups']) ? ' disabled' : '', '>
						<span class="smalltext">', $name, '</span>
					</label><br>';

	echo '
				</dd>
				<dt>
					', Lang::$txt['paid_mod_reminder'], ':<br>
					<span class="smalltext">', Lang::$txt['paid_mod_reminder_desc'], ' ', Lang::$txt['zero_to_disable'], '</span>
				</dt>
				<dd>
					<input type="number" name="reminder" value="', Utils::$context['sub']['reminder'], '" size="6">
				</dd>
				<dt>
					', Lang::$txt['paid_mod_email'], ':<br>
					<span class="smalltext">', Lang::$txt['paid_mod_email_desc'], '</span>
				</dt>
				<dd>
					<textarea name="emailcomplete" rows="6" cols="40">', Utils::$context['sub']['email_complete'], '</textarea>
				</dd>
			</dl>
			<hr>
			<input type="radio" name="duration_type" id="duration_type_fixed" value="fixed"', empty(Utils::$context['sub']['duration']) || Utils::$context['sub']['duration'] == 'fixed' ? ' checked' : '', ' onclick="toggleDuration(\'fixed\');">
			<strong><label for="duration_type_fixed">', Lang::$txt['paid_mod_fixed_price'], '</label></strong>
			<br>
			<div id="fixed_area" ', empty(Utils::$context['sub']['duration']) || Utils::$context['sub']['duration'] == 'fixed' ? '' : 'style="display: none;"', '>
				<fieldset>
					<dl class="settings">
						<dt>
							', Lang::$txt['paid_cost'], ' (', str_replace('%1.2f', '', Config::$modSettings['paid_currency_symbol']), '):
						</dt>
						<dd>
							<input type="number" step="0.01" name="cost" value="', empty(Utils::$context['sub']['cost']['fixed']) ? '' : Utils::$context['sub']['cost']['fixed'], '" placeholder="0.00" size="4">
						</dd>
						<dt>
							', Lang::$txt['paid_mod_span'], ':
						</dt>
						<dd>
							<input type="number" name="span_value" value="', Utils::$context['sub']['span']['value'], '" size="4">
							<select name="span_unit">
								<option value="D"', Utils::$context['sub']['span']['unit'] == 'D' ? ' selected' : '', '>', Lang::$txt['paid_mod_span_days'], '</option>
								<option value="W"', Utils::$context['sub']['span']['unit'] == 'W' ? ' selected' : '', '>', Lang::$txt['paid_mod_span_weeks'], '</option>
								<option value="M"', Utils::$context['sub']['span']['unit'] == 'M' ? ' selected' : '', '>', Lang::$txt['paid_mod_span_months'], '</option>
								<option value="Y"', Utils::$context['sub']['span']['unit'] == 'Y' ? ' selected' : '', '>', Lang::$txt['paid_mod_span_years'], '</option>
							</select>
						</dd>
					</dl>
				</fieldset>
			</div><!-- #fixed_area -->
			<input type="radio" name="duration_type" id="duration_type_flexible" value="flexible"', !empty(Utils::$context['sub']['duration']) && Utils::$context['sub']['duration'] == 'flexible' ? ' checked' : '', ' onclick="toggleDuration(\'flexible\');">
			<strong><label for="duration_type_flexible">', Lang::$txt['paid_mod_flexible_price'], '</label></strong>
			<br>
			<div id="flexible_area" ', !empty(Utils::$context['sub']['duration']) && Utils::$context['sub']['duration'] == 'flexible' ? '' : 'style="display: none;"', '>
				<fieldset>';

	echo '
					<div class="information">
						<strong>', Lang::$txt['paid_mod_price_breakdown'], '</strong><br>
						', Lang::$txt['paid_mod_price_breakdown_desc'], '
					</div>
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['paid_duration'], '</strong>
						</dt>
						<dd>
							<strong>', Lang::$txt['paid_cost'], ' (', preg_replace('~%[df\.\d]+~', '', Config::$modSettings['paid_currency_symbol']), ')</strong>
						</dd>
						<dt>
							', Lang::$txt['paid_per_day'], ':
						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_day" value="', empty(Utils::$context['sub']['cost']['day']) ? '0' : Utils::$context['sub']['cost']['day'], '" size="5">
						</dd>
						<dt>
							', Lang::$txt['paid_per_week'], ':
						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_week" value="', empty(Utils::$context['sub']['cost']['week']) ? '0' : Utils::$context['sub']['cost']['week'], '" size="5">
						</dd>
						<dt>
							', Lang::$txt['paid_per_month'], ':
						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_month" value="', empty(Utils::$context['sub']['cost']['month']) ? '0' : Utils::$context['sub']['cost']['month'], '" size="5">
						</dd>
						<dt>
							', Lang::$txt['paid_per_year'], ':
						</dt>
						<dd>
							<input type="number" step="0.01" name="cost_year" value="', empty(Utils::$context['sub']['cost']['year']) ? '0' : Utils::$context['sub']['cost']['year'], '" size="5">
						</dd>
					</dl>
				</fieldset>
			</div><!-- #flexible_area -->
			<input type="submit" name="save" value="', Lang::$txt['paid_settings_save'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-pms_token_var'], '" value="', Utils::$context['admin-pms_token'], '">
		</div><!-- .windowbg -->
	</form>';
}

/**
 * The page for deleting a subscription.
 */
function template_delete_subscription()
{
	echo '
	<form action="', Config::$scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', Utils::$context['sub_id'], ';delete" method="post">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['paid_delete_subscription'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['paid_mod_delete_warning'], '</p>
			<input type="submit" name="delete_confirm" value="', Lang::$txt['paid_delete_subscription'], '" class="button">
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-pmsd_token_var'], '" value="', Utils::$context['admin-pmsd_token'], '">
		</div>
	</form>';
}

/**
 * Add or edit an existing subscriber.
 */
function template_modify_user_subscription()
{
	// Some quickly stolen javascript from Post, could do with being more efficient :)
	echo '
	<script>
		var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
	</script>';

	echo '
	<form action="', Config::$scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;sid=', Utils::$context['sub_id'], ';lid=', Utils::$context['log_id'], '" method="post">
		<div class="cat_bar">
			<h3 class="catbg">
				', Lang::$txt['paid_' . Utils::$context['action_type'] . '_subscription'], ' - ', Utils::$context['current_subscription']['name'], '
				', empty(Utils::$context['sub']['username']) ? '' : ' (' . Lang::$txt['user'] . ': ' . Utils::$context['sub']['username'] . ')', '
			</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">';

	// Do we need a username?
	if (Utils::$context['action_type'] == 'add')
		echo '
				<dt>
					<strong>', Lang::$txt['paid_username'], ':</strong><br>
					<span class="smalltext">', Lang::$txt['one_username'], '</span>
				</dt>
				<dd>
					<input type="text" name="name" id="name_control" value="', Utils::$context['sub']['username'], '" size="30">
				</dd>';

	echo '
				<dt>
					<strong>', Lang::$txt['paid_status'], ':</strong>
				</dt>
				<dd>
					<select name="status">
						<option value="0"', Utils::$context['sub']['status'] == 0 ? ' selected' : '', '>', Lang::$txt['paid_finished'], '</option>
						<option value="1"', Utils::$context['sub']['status'] == 1 ? ' selected' : '', '>', Lang::$txt['paid_active'], '</option>
					</select>
				</dd>
			</dl>
			<fieldset>
				<legend>', Lang::$txt['start_date_and_time'], '</legend>
				<select name="year" id="year" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = 2005; $year <= 2030; $year++)
		echo '
					<option value="', $year, '"', $year == Utils::$context['sub']['start']['year'] ? ' selected' : '', '>', $year, '</option>';

	echo '
				</select>&nbsp;
				', (isset(Lang::$txt['calendar_month']) ? Lang::$txt['calendar_month'] : Lang::$txt['calendar_month']), '&nbsp;
				<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
					<option value="', $month, '"', $month == Utils::$context['sub']['start']['month'] ? ' selected' : '', '>', Lang::$txt['months'][$month], '</option>';

	echo '
				</select>&nbsp;
				', (isset(Lang::$txt['calendar_day']) ? Lang::$txt['calendar_day'] : Lang::$txt['calendar_day']), '&nbsp;
				<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= Utils::$context['sub']['start']['last_day']; $day++)
		echo '
					<option value="', $day, '"', $day == Utils::$context['sub']['start']['day'] ? ' selected' : '', '>', $day, '</option>';

	echo '
				</select>
				', Lang::$txt['hour'], ': <input type="text" name="hour" value="', Utils::$context['sub']['start']['hour'], '" size="2">
				', Lang::$txt['minute'], ': <input type="text" name="minute" value="', Utils::$context['sub']['start']['min'], '" size="2">
			</fieldset>
			<fieldset>
				<legend>', Lang::$txt['end_date_and_time'], '</legend>
				<select name="yearend" id="yearend" onchange="generateDays(\'end\');">';

	// Show a list of all the years we allow...
	for ($year = 2005; $year <= 2030; $year++)
		echo '
					<option value="', $year, '"', $year == Utils::$context['sub']['end']['year'] ? ' selected' : '', '>', $year, '</option>';

	echo '
				</select>&nbsp;
				', (isset(Lang::$txt['calendar_month']) ? Lang::$txt['calendar_month'] : Lang::$txt['calendar_month']), '&nbsp;
				<select name="monthend" id="monthend" onchange="generateDays(\'end\');">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
					<option value="', $month, '"', $month == Utils::$context['sub']['end']['month'] ? ' selected' : '', '>', Lang::$txt['months'][$month], '</option>';

	echo '
				</select>&nbsp;
				', (isset(Lang::$txt['calendar_day']) ? Lang::$txt['calendar_day'] : Lang::$txt['calendar_day']), '&nbsp;
				<select name="dayend" id="dayend">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= Utils::$context['sub']['end']['last_day']; $day++)
		echo '
					<option value="', $day, '"', $day == Utils::$context['sub']['end']['day'] ? ' selected' : '', '>', $day, '</option>';

	echo '
				</select>
				', Lang::$txt['hour'], ': <input type="number" name="hourend" value="', Utils::$context['sub']['end']['hour'], '" size="2">
				', Lang::$txt['minute'], ': <input type="number" name="minuteend" value="', Utils::$context['sub']['end']['min'], '" size="2">
			</fieldset>
			<input type="submit" name="save_sub" value="', Lang::$txt['paid_settings_save'], '" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
	</form>
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'name_subscriber\',
			sControlId: \'name_control\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			bItemList: false
			});
	</script>';

	if (!empty(Utils::$context['pending_payments']))
	{
		echo '
	<div class="cat_bar">
		<h3 class="catbg">', Lang::$txt['pending_payments'], '</h3>
	</div>
	<div class="information">
		', Lang::$txt['pending_payments_desc'], '
	</div>
	<div class="cat_bar">
		<h3 class="catbg">', Lang::$txt['pending_payments_value'], '</h3>
	</div>
	<div class="windowbg">
		<ul>';

		foreach (Utils::$context['pending_payments'] as $id => $payment)
			echo '
			<li>
				', $payment['desc'], '
				<span class="floatleft">
					<a href="', Config::$scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;lid=', Utils::$context['log_id'], ';pending=', $id, ';accept">', Lang::$txt['pending_payments_accept'], '</a>
				</span>
				<span class="floatright">
					<a href="', Config::$scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;lid=', Utils::$context['log_id'], ';pending=', $id, ';remove">', Lang::$txt['pending_payments_remove'], '</a>
				</span>
			</li>';

		echo '
		</ul>';
	}

	echo '
	</div>';
}

/**
 * Template for a user to edit/pick their subscriptions.
 */
function template_user_subscription()
{
	echo '
	<div id="paid_subscription">
		<form action="', Config::$scripturl, '?action=profile;u=', Utils::$context['id_member'], ';area=subscriptions;confirm" method="post">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['subscriptions'], '</h3>
			</div>';

	if (empty(Utils::$context['subscriptions']))
		echo '
			<div class="information">
				', Lang::$txt['paid_subs_none'], '
			</div>';
	else
	{
		echo '
			<div class="information">
				', Lang::$txt['paid_subs_desc'], '
			</div>';

		// Print out all the subscriptions.
		foreach (Utils::$context['subscriptions'] as $id => $subscription)
		{
			// Ignore the inactive ones...
			if (empty($subscription['active']))
				continue;

			echo '
			<div class="cat_bar">
				<h3 class="catbg">', $subscription['name'], '</h3>
			</div>
			<div class="windowbg">
				<p><strong>', $subscription['name'], '</strong></p>
				<p class="smalltext">', $subscription['desc'], '</p>';

			if (!$subscription['flexible'])
				echo '
				<div><strong>', Lang::$txt['paid_duration'], ':</strong> ', $subscription['length'], '</div>';

			if (User::$me->is_owner)
			{
				echo '
				<strong>', Lang::$txt['paid_cost'], ':</strong>';

				if ($subscription['flexible'])
				{
					echo '
				<select name="cur[', $subscription['id'], ']">';

					// Print out the costs for this one.
					foreach ($subscription['costs'] as $duration => $value)
						echo '
					<option value="', $duration, '">', sprintf(Config::$modSettings['paid_currency_symbol'], $value), '/', Lang::$txt[$duration], '</option>';

					echo '
				</select>';
				}
				else
					echo '
				', sprintf(Config::$modSettings['paid_currency_symbol'], $subscription['costs']['fixed']);

				echo '
				<hr>
				<input type="submit" name="sub_id[', $subscription['id'], ']" value="', Lang::$txt['paid_order'], '" class="button">';
			}
			else
				echo '
				<a href="', Config::$scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $subscription['id'], ';uid=', Utils::$context['member']['id'], (empty(Utils::$context['current'][$subscription['id']]) ? '' : ';lid=' . Utils::$context['current'][$subscription['id']]['id']), '">', empty(Utils::$context['current'][$subscription['id']]) ? Lang::$txt['paid_admin_add'] : Lang::$txt['paid_edit_subscription'], '</a>';

			echo '
			</div><!-- .windowbg -->';
		}
	}

	echo '
		</form>
		<br class="clear">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['paid_current'], '</h3>
		</div>
		<div class="information">
			', Lang::$txt['paid_current_desc'], '
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th style="width: 30%">', Lang::$txt['paid_name'], '</th>
					<th>', Lang::$txt['paid_status'], '</th>
					<th>', Lang::$txt['start_date'], '</th>
					<th>', Lang::$txt['end_date'], '</th>
				</tr>
			</thead>
			<tbody>';

	if (empty(Utils::$context['current']))
		echo '
				<tr class="windowbg">
					<td colspan="4">
						', Lang::$txt['paid_none_yet'], '
					</td>
				</tr>';

	foreach (Utils::$context['current'] as $sub)
	{
		if (!$sub['hide'])
			echo '
				<tr class="windowbg">
					<td>
						', (User::$me->is_admin ? '<a href="' . Config::$scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $sub['id'] . '">' . $sub['name'] . '</a>' : $sub['name']), '
					</td>
					<td>
						<span style="color: ', ($sub['status'] == 2 ? 'green' : ($sub['status'] == 1 ? 'red' : 'orange')), '"><strong>', $sub['status_text'], '</strong></span>
					</td>
					<td>', $sub['start'], '</td>
					<td>', $sub['end'], '</td>
				</tr>';
	}

	echo '
			</tbody>
		</table>
	</div><!-- #paid_subscription -->';
}

/**
 * The "choose payment" dialog.
 */
function template_choose_payment()
{
	echo '
	<div id="paid_subscription">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['paid_confirm_payment'], '</h3>
		</div>
		<div class="information">
			', Lang::$txt['paid_confirm_desc'], '
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong>', Lang::$txt['subscription'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['sub']['name'], '
				</dd>
				<dt>
					<strong>', Lang::$txt['paid_cost'], ':</strong>
				</dt>
				<dd>
					', Utils::$context['cost'], '
				</dd>
			</dl>
		</div>';

	// Do all the gateway options.
	foreach (Utils::$context['gateways'] as $gateway)
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $gateway['title'], '</h3>
		</div>
		<div class="windowbg">
			', $gateway['desc'], '<br>
			<form action="', $gateway['form'], '" method="post">';

		if (!empty($gateway['javascript']))
			echo '
				<script>
					', $gateway['javascript'], '
				</script>';

		foreach ($gateway['hidden'] as $name => $value)
			echo '
				<input type="hidden" id="', $gateway['id'], '_', $name, '" name="', $name, '" value="', $value, '">';

		echo '
				<br>
				<input type="submit" value="', $gateway['submit'], '" class="button">
			</form>
		</div>';
	}

	echo '
	</div><!-- #paid_subscription -->
	<br class="clear">';
}

/**
 * The "thank you" bit...
 */
function template_paid_done()
{
	echo '
	<div id="paid_subscription">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['paid_done'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['paid_done_desc'], '</p>
			<br>
			<a href="', Config::$scripturl, '?action=profile;u=', Utils::$context['member']['id'], ';area=subscriptions">', Lang::$txt['paid_sub_return'], '</a>
		</div>
	</div>';
}

?>