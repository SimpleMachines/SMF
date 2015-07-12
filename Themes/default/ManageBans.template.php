<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

function template_ban_edit()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="', $context['form_url'], '" method="post" accept-charset="', $context['character_set'], '" onsubmit="return confirmBan(this);">
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['ban']['is_new'] ? $txt['ban_add_new'] : $txt['ban_edit'] . ' \'' . $context['ban']['name'] . '\'', '
				</h3>
			</div>';

	if ($context['ban']['is_new'])
		echo '
			<div class="information winfo">', $txt['ban_add_notes'], '</div>';

	// If there were errors creating the ban, show them.
	if (!empty($context['error_messages']))
	{
		echo '
			<div class="errorbox">
				<strong>', $txt['ban_errors_detected'], '</strong>
				<ul>';

		foreach ($context['error_messages'] as $error)
			echo '
					<li class="error">', $error, '</li>';

		echo '
				</ul>
			</div>';
	}

	echo '
		<div class="windowbg2">
			<dl class="settings">
				<dt id="ban_name_label">
					<strong>', $txt['ban_name'], ':</strong>
				</dt>
				<dd>
					<input type="text" id="ban_name" name="ban_name" value="', $context['ban']['name'], '" size="45" maxlength="60" class="input_text">
				</dd>';

	if (isset($context['ban']['reason']))
		echo '
				<dt>
					<strong><label for="reason">', $txt['ban_reason'], ':</label></strong><br>
					<span class="smalltext">', $txt['ban_reason_desc'], '</span>
				</dt>
				<dd>
					<textarea name="reason" id="reason" cols="40" rows="3" style="min-height: 64px; max-height: 64px; min-width: 50%; max-width: 99%;">', $context['ban']['reason'], '</textarea>
				</dd>';

	if (isset($context['ban']['notes']))
		echo '
				<dt>
					<strong><label for="ban_notes">', $txt['ban_notes'], ':</label></strong><br>
					<span class="smalltext">', $txt['ban_notes_desc'], '</span>
				</dt>
				<dd>
					<textarea name="notes" id="ban_notes" cols="40" rows="3" style="min-height: 64px; max-height: 64px; min-width: 50%; max-width: 99%;">', $context['ban']['notes'], '</textarea>
				</dd>';

	echo '
				</dl>
				<fieldset class="ban_settings floatleft">
					<legend>
						', $txt['ban_expiration'], '
					</legend>
					<input type="radio" name="expiration" value="never" id="never_expires" onclick="fUpdateStatus();"', $context['ban']['expiration']['status'] == 'never' ? ' checked' : '', ' class="input_radio"> <label for="never_expires">', $txt['never'], '</label><br>
					<input type="radio" name="expiration" value="one_day" id="expires_one_day" onclick="fUpdateStatus();"', $context['ban']['expiration']['status'] == 'one_day' ? ' checked' : '', ' class="input_radio"> <label for="expires_one_day">', $txt['ban_will_expire_within'], '</label>: <input type="number" name="expire_date" id="expire_date" size="3" value="', $context['ban']['expiration']['days'], '" class="input_text"> ', $txt['ban_days'], '<br>
					<input type="radio" name="expiration" value="expired" id="already_expired" onclick="fUpdateStatus();"', $context['ban']['expiration']['status'] == 'expired' ? ' checked' : '', ' class="input_radio"> <label for="already_expired">', $txt['ban_expired'], '</label>
				</fieldset>
				<fieldset class="ban_settings floatright">
					<legend>
						', $txt['ban_restriction'], '
					</legend>
					<input type="radio" name="full_ban" id="full_ban" value="1" onclick="fUpdateStatus();"', $context['ban']['cannot']['access'] ? ' checked' : '', ' class="input_radio"> <label for="full_ban">', $txt['ban_full_ban'], '</label><br>
					<input type="radio" name="full_ban" id="partial_ban" value="0" onclick="fUpdateStatus();"', !$context['ban']['cannot']['access'] ? ' checked' : '', ' class="input_radio"> <label for="partial_ban">', $txt['ban_partial_ban'], '</label><br>
					<input type="checkbox" name="cannot_post" id="cannot_post" value="1"', $context['ban']['cannot']['post'] ? ' checked' : '', ' class="ban_restriction input_radio"> <label for="cannot_post">', $txt['ban_cannot_post'], '</label> (<a href="', $scripturl, '?action=helpadmin;help=ban_cannot_post" onclick="return reqOverlayDiv(this.href);">?</a>)<br>
					<input type="checkbox" name="cannot_register" id="cannot_register" value="1"', $context['ban']['cannot']['register'] ? ' checked' : '', ' class="ban_restriction input_radio"> <label for="cannot_register">', $txt['ban_cannot_register'], '</label><br>
					<input type="checkbox" name="cannot_login" id="cannot_login" value="1"', $context['ban']['cannot']['login'] ? ' checked' : '', ' class="ban_restriction input_radio"> <label for="cannot_login">', $txt['ban_cannot_login'], '</label><br>
				</fieldset>
				<br class="clear_right">';

	if (!empty($context['ban_suggestions']))
	{
		echo '
				<fieldset>
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, \'ban_suggestion\');" class="input_check"> ', $txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip" class="input_check"', !empty($context['ban_suggestions']['main_ip']) ? ' checked' : '', '>
							<label for="main_ip_check">', $txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', $context['ban_suggestions']['main_ip'], '" size="44" onfocus="document.getElementById(\'main_ip_check\').checked = true;" class="input_text">
						</dd>';

		if (empty($modSettings['disableHostnameLookup']))
			echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname" class="input_check"', !empty($context['ban_suggestions']['hostname']) ? ' checked' : '', '>
							<label for="hostname_check">', $txt['ban_on_hostname'], '</label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="', $context['ban_suggestions']['hostname'], '" size="44" onfocus="document.getElementById(\'hostname_check\').checked = true;" class="input_text">
						</dd>';

		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email" class="input_check"', !empty($context['ban_suggestions']['email']) ? ' checked' : '', '>
							<label for="email_check">', $txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="text" name="email" value="', $context['ban_suggestions']['email'], '" size="44" onfocus="document.getElementById(\'email_check\').checked = true;" class="input_text">
						</dd>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user" class="input_check"', !empty($context['ban_suggestions']['user']) ||  isset($context['ban']['from_user']) ? ' checked' : '', '>
							<label for="user_check">', $txt['ban_on_username'], '</label>:
						</dt>
						<dd>
							<input type="text" ', isset($context['ban']['from_user']) ? 'readonly value="' . $context['ban_suggestions']['member']['name'] . '"' : ' value=""', ' name="user" id="user" size="44" class="input_text">
						</dd>
					</dl>';

		if (!empty($context['ban_suggestions']['other_ips']))
		{
			foreach ($context['ban_suggestions']['other_ips'] as $key => $ban_ips)
			{
				if (!empty($ban_ips))
				{
					echo '
					<div>', $txt[$key], ':</div>
					<dl class="settings">';

					$count = 0;
					foreach ($ban_ips as $ip)
						echo '
						<dt>
							<input type="checkbox" id="suggestions_', $key ,'_', $count, '" name="ban_suggestions[', $key ,'][]"', !empty($context['ban_suggestions']['saved_triggers'][$key]) && in_array($ip, $context['ban_suggestions']['saved_triggers'][$key]) ? ' checked' : '', ' value="', $ip, '" class="input_check">
						</dt>
						<dd>
							<label for="suggestions_', $key ,'_', $count++, '">', $ip, '</label>
						</dd>';

					echo '
					</dl>';
				}
			}
		}

		echo '
				</fieldset>';
	}

	echo '
				<input type="submit" name="', $context['ban']['is_new'] ? 'add_ban' : 'modify_ban', '" value="', $context['ban']['is_new'] ? $txt['ban_add'] : $txt['ban_modify'], '" class="button_submit">
				<input type="hidden" name="old_expire" value="', $context['ban']['expiration']['days'], '">
				<input type="hidden" name="bg" value="', $context['ban']['id'], '">', isset($context['ban']['from_user']) ? '
				<input type="hidden" name="u" value="' . $context['ban_suggestions']['member']['id'] . '">' : '', '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-bet_token_var'], '" value="', $context['admin-bet_token'], '">
			</div>
		</form>';

	if (!$context['ban']['is_new'] && empty($context['ban_suggestions']))
	{
		echo '
		<br>';
		template_show_list('ban_items');
	}

	echo '
	</div>
	<script><!-- // --><![CDATA[
		var fUpdateStatus = function ()
		{
			document.getElementById("expire_date").disabled = !document.getElementById("expires_one_day").checked;
			document.getElementById("cannot_post").disabled = document.getElementById("full_ban").checked;
			document.getElementById("cannot_register").disabled = document.getElementById("full_ban").checked;
			document.getElementById("cannot_login").disabled = document.getElementById("full_ban").checked;
		}
		addLoadEvent(fUpdateStatus);';

	// Auto suggest only needed for adding new bans, not editing
	if ($context['ban']['is_new'] && empty($_REQUEST['u']))
		echo '
			var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'user\',
			sControlId: \'user\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});

		function onUpdateName(oAutoSuggest)
		{
			document.getElementById(\'user_check\').checked = true;
			return true;
		}
		oAddMemberSuggest.registerCallback(\'onBeforeUpdate\', \'onUpdateName\');';

	echo '
		function confirmBan(aForm)
		{
			if (aForm.ban_name.value == \'\')
			{
				alert(\'', $txt['ban_name_empty'], '\');
				return false;
			}

			if (aForm.partial_ban.checked && !(aForm.cannot_post.checked || aForm.cannot_register.checked || aForm.cannot_login.checked))
			{
				alert(\'', $txt['ban_restriction_empty'], '\');
				return false;
			}
		}// ]]></script>';
}

function template_ban_edit_trigger()
{
	global $context, $txt, $modSettings;

	echo '
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="', $context['form_url'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['ban_trigger']['is_new'] ? $txt['ban_add_trigger'] : $txt['ban_edit_trigger_title'], '
				</h3>
			</div>
			<div class="windowbg">
				<fieldset>
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, \'ban_suggestion\');" class="input_check"> ', $txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip" class="input_check"', $context['ban_trigger']['ip']['selected'] ? ' checked' : '', '>
							<label for="main_ip_check">', $txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', $context['ban_trigger']['ip']['value'], '" size="44" onfocus="document.getElementById(\'main_ip_check\').checked = true;" class="input_text">
						</dd>';

				if (empty($modSettings['disableHostnameLookup']))
					echo '
							<dt>
								<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname" class="input_check"', $context['ban_trigger']['hostname']['selected'] ? ' checked' : '', '>
								<label for="hostname_check">', $txt['ban_on_hostname'], '</label>
							</dt>
							<dd>
								<input type="text" name="hostname" value="', $context['ban_trigger']['hostname']['value'], '" size="44" onfocus="document.getElementById(\'hostname_check\').checked = true;" class="input_text">
							</dd>';

				echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email" class="input_check"', $context['ban_trigger']['email']['selected'] ? ' checked' : '', '>
							<label for="email_check">', $txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="text" name="email" value="', $context['ban_trigger']['email']['value'], '" size="44" onfocus="document.getElementById(\'email_check\').checked = true;" class="input_text">
						</dd>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user" class="input_check"', $context['ban_trigger']['banneduser']['selected'] ? ' checked' : '', '>
							<label for="user_check">', $txt['ban_on_username'], '</label>:
						</dt>
						<dd>
							<input type="text" value="' . $context['ban_trigger']['banneduser']['value'] . '" name="user" id="user" size="44"  onfocus="document.getElementById(\'user_check\').checked = true;"class="input_text">
						</dd>
					</dl>
				</fieldset>
				<input type="submit" name="', $context['ban_trigger']['is_new'] ? 'add_new_trigger' : 'edit_trigger', '" value="', $context['ban_trigger']['is_new'] ? $txt['ban_add_trigger_submit'] : $txt['ban_edit_trigger_submit'], '" class="button_submit">
			</div>
			<input type="hidden" name="bi" value="' . $context['ban_trigger']['id'] . '">
			<input type="hidden" name="bg" value="' . $context['ban_trigger']['group'] . '">
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
			<input type="hidden" name="', $context['admin-bet_token_var'], '" value="', $context['admin-bet_token'], '">
		</form>
	</div>
	<script><!-- // --><![CDATA[
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'username\',
			sControlId: \'user\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});

		function onUpdateName(oAutoSuggest)
		{
			document.getElementById(\'user_check\').checked = true;
			return true;
		}
		oAddMemberSuggest.registerCallback(\'onBeforeUpdate\', \'onUpdateName\');
	// ]]></script>';
}

?>