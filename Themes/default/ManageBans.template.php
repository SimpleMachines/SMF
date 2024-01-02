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
 * Add or edit a ban
 */
function template_ban_edit()
{
	echo '
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="', Utils::$context['form_url'], '" method="post" accept-charset="', Utils::$context['character_set'], '" onsubmit="return confirmBan(this);">';

	// If there were errors creating the ban, show them.
	if (!empty(Utils::$context['error_messages']))
	{
		echo '
			<div class="errorbox">
				<strong>', Lang::$txt['ban_errors_detected'], '</strong>
				<ul>';

		foreach (Utils::$context['error_messages'] as $error)
			echo '
					<li class="error">', $error, '</li>';

		echo '
				</ul>
			</div>';
	}

	echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', Utils::$context['ban']['is_new'] ? Lang::$txt['ban_add_new'] : Lang::$txt['ban_edit'] . ' \'' . Utils::$context['ban']['name'] . '\'', '
				</h3>
			</div>';

	if (Utils::$context['ban']['is_new'])
		echo '
			<div class="information noup">', Lang::$txt['ban_add_notes'], '</div>';

	echo '
			<div class="windowbg noup">
				<dl class="settings">
					<dt id="ban_name_label">
						<strong>', Lang::$txt['ban_name'], ':</strong>
					</dt>
					<dd>
						<input type="text" id="ban_name" name="ban_name" value="', Utils::$context['ban']['name'], '" size="45" maxlength="60">
					</dd>';

	if (isset(Utils::$context['ban']['reason']))
		echo '
					<dt>
						<strong><label for="reason">', Lang::$txt['ban_reason'], ':</label></strong><br>
						<span class="smalltext">', Lang::$txt['ban_reason_desc'], '</span>
					</dt>
					<dd>
						<textarea name="reason" id="reason" cols="40" rows="3">', Utils::$context['ban']['reason'], '</textarea>
					</dd>';

	if (isset(Utils::$context['ban']['notes']))
		echo '
					<dt>
						<strong><label for="ban_notes">', Lang::$txt['ban_notes'], ':</label></strong><br>
						<span class="smalltext">', Lang::$txt['ban_notes_desc'], '</span>
					</dt>
					<dd>
						<textarea name="notes" id="ban_notes" cols="40" rows="3">', Utils::$context['ban']['notes'], '</textarea>
					</dd>';

	echo '
				</dl>
				<fieldset class="ban_settings floatleft">
					<legend>
						', Lang::$txt['ban_expiration'], '
					</legend>
					<input type="radio" name="expiration" value="never" id="never_expires" onclick="fUpdateStatus();"', Utils::$context['ban']['expiration']['status'] == 'never' ? ' checked' : '', '> <label for="never_expires">', Lang::$txt['never'], '</label><br>
					<input type="radio" name="expiration" value="one_day" id="expires_one_day" onclick="fUpdateStatus();"', Utils::$context['ban']['expiration']['status'] == 'one_day' ? ' checked' : '', '> <label for="expires_one_day">', Lang::$txt['ban_will_expire_within'], '</label>: <input type="number" name="expire_date" id="expire_date" size="3" value="', Utils::$context['ban']['expiration']['days'], '"> ', Lang::$txt['ban_days'], '<br>
					<input type="radio" name="expiration" value="expired" id="already_expired" onclick="fUpdateStatus();"', Utils::$context['ban']['expiration']['status'] == 'expired' ? ' checked' : '', '> <label for="already_expired">', Lang::$txt['ban_expired'], '</label>
				</fieldset>
				<fieldset class="ban_settings floatright">
					<legend>
						', Lang::$txt['ban_restriction'], '
					</legend>
					<input type="radio" name="full_ban" id="full_ban" value="1" onclick="fUpdateStatus();"', Utils::$context['ban']['cannot']['access'] ? ' checked' : '', '> <label for="full_ban">', Lang::$txt['ban_full_ban'], '</label><br>
					<input type="radio" name="full_ban" id="partial_ban" value="0" onclick="fUpdateStatus();"', !Utils::$context['ban']['cannot']['access'] ? ' checked' : '', '> <label for="partial_ban">', Lang::$txt['ban_partial_ban'], '</label><br>
					<input type="checkbox" name="cannot_post" id="cannot_post" value="1"', Utils::$context['ban']['cannot']['post'] ? ' checked' : '', ' class="ban_restriction"> <label for="cannot_post">', Lang::$txt['ban_cannot_post'], '</label> (<a href="', Config::$scripturl, '?action=helpadmin;help=ban_cannot_post" onclick="return reqOverlayDiv(this.href);">?</a>)<br>
					<input type="checkbox" name="cannot_register" id="cannot_register" value="1"', Utils::$context['ban']['cannot']['register'] ? ' checked' : '', ' class="ban_restriction"> <label for="cannot_register">', Lang::$txt['ban_cannot_register'], '</label><br>
					<input type="checkbox" name="cannot_login" id="cannot_login" value="1"', Utils::$context['ban']['cannot']['login'] ? ' checked' : '', ' class="ban_restriction"> <label for="cannot_login">', Lang::$txt['ban_cannot_login'], '</label><br>
				</fieldset>
				<br class="clear_right">';

	if (!empty(Utils::$context['ban_suggestions']))
	{
		echo '
				<fieldset>
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, \'ban_suggestion\');"> ', Lang::$txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip"', !empty(Utils::$context['ban_suggestions']['main_ip']) ? ' checked' : '', '>
							<label for="main_ip_check">', Lang::$txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', Utils::$context['ban_suggestions']['main_ip'], '" size="44" onfocus="document.getElementById(\'main_ip_check\').checked = true;">
						</dd>';

		if (empty(Config::$modSettings['disableHostnameLookup']))
			echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname"', !empty(Utils::$context['ban_suggestions']['hostname']) ? ' checked' : '', '>
							<label for="hostname_check">', Lang::$txt['ban_on_hostname'], '</label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="', Utils::$context['ban_suggestions']['hostname'], '" size="44" onfocus="document.getElementById(\'hostname_check\').checked = true;">
						</dd>';

		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email"', !empty(Utils::$context['ban_suggestions']['email']) ? ' checked' : '', '>
							<label for="email_check">', Lang::$txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="email" name="email" value="', Utils::$context['ban_suggestions']['email'], '" size="44" onfocus="document.getElementById(\'email_check\').checked = true;">
						</dd>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user"', !empty(Utils::$context['ban_suggestions']['user']) || isset(Utils::$context['ban']['from_user']) ? ' checked' : '', '>
							<label for="user_check">', Lang::$txt['ban_on_username'], '</label>:
						</dt>
						<dd>
							<input type="text" ', isset(Utils::$context['ban']['from_user']) ? 'readonly value="' . Utils::$context['ban_suggestions']['member']['name'] . '"' : ' value=""', ' name="user" id="user" size="44">
						</dd>
					</dl>';

		if (!empty(Utils::$context['ban_suggestions']['other_ips']))
		{
			foreach (Utils::$context['ban_suggestions']['other_ips'] as $key => $ban_ips)
			{
				if (!empty($ban_ips))
				{
					echo '
					<div>', Lang::$txt[$key], ':</div>
					<dl class="settings">';

					$count = 0;
					foreach ($ban_ips as $ip)
						echo '
						<dt>
							<input type="checkbox" id="suggestions_', $key, '_', $count, '" name="ban_suggestions[', $key, '][]"', !empty(Utils::$context['ban_suggestions']['saved_triggers'][$key]) && in_array($ip, Utils::$context['ban_suggestions']['saved_triggers'][$key]) ? ' checked' : '', ' value="', $ip, '">
						</dt>
						<dd>
							<label for="suggestions_', $key, '_', $count++, '">', $ip, '</label>
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
				<input type="submit" name="', Utils::$context['ban']['is_new'] ? 'add_ban' : 'modify_ban', '" value="', Utils::$context['ban']['is_new'] ? Lang::$txt['ban_add'] : Lang::$txt['ban_modify'], '" class="button">
				<input type="hidden" name="old_expire" value="', Utils::$context['ban']['expiration']['days'], '">
				<input type="hidden" name="bg" value="', Utils::$context['ban']['id'], '">', isset(Utils::$context['ban']['from_user']) ? '
				<input type="hidden" name="u" value="' . Utils::$context['ban_suggestions']['member']['id'] . '">' : '', '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-bet_token_var'], '" value="', Utils::$context['admin-bet_token'], '">
			</div><!-- .windowbg -->
		</form>';

	if (!Utils::$context['ban']['is_new'] && empty(Utils::$context['ban_suggestions']))
	{
		echo '
		<br>';
		template_show_list('ban_items');
	}

	echo '
	</div><!-- #manage_bans -->
	<script>
		var fUpdateStatus = function ()
		{
			document.getElementById("expire_date").disabled = !document.getElementById("expires_one_day").checked;
			document.getElementById("cannot_post").disabled = document.getElementById("full_ban").checked;
			document.getElementById("cannot_register").disabled = document.getElementById("full_ban").checked;
			document.getElementById("cannot_login").disabled = document.getElementById("full_ban").checked;
		}
		addLoadEvent(fUpdateStatus);';

	// Auto suggest only needed for adding new bans, not editing
	if (Utils::$context['ban']['is_new'] && empty($_REQUEST['u']))
		echo '
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'user\',
			sControlId: \'user\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
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
				alert(\'', Lang::$txt['ban_name_empty'], '\');
				return false;
			}

			if (aForm.partial_ban.checked && !(aForm.cannot_post.checked || aForm.cannot_register.checked || aForm.cannot_login.checked))
			{
				alert(\'', Lang::$txt['ban_restriction_empty'], '\');
				return false;
			}
		}
	</script>';
}

/**
 * Add or edit a ban trigger
 */
function template_ban_edit_trigger()
{
	echo '
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="', Utils::$context['form_url'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', Utils::$context['ban_trigger']['is_new'] ? Lang::$txt['ban_add_trigger'] : Lang::$txt['ban_edit_trigger_title'], '
				</h3>
			</div>
			<div class="windowbg">
				<fieldset>';
	if (Utils::$context['ban_trigger']['is_new'])
		echo '
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, \'ban_suggestion\');"> ', Lang::$txt['ban_triggers'], '
					</legend>';
	echo '
					<dl class="settings">';
	if (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['ip']['selected'])
		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip"', Utils::$context['ban_trigger']['ip']['selected'] ? ' checked' : '', '>
							<label for="main_ip_check">', Lang::$txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', Utils::$context['ban_trigger']['ip']['value'], '" size="44" onfocus="document.getElementById(\'main_ip_check\').checked = true;">
						</dd>';

	if (empty(Config::$modSettings['disableHostnameLookup']) && (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['hostname']['selected']))
		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname"', Utils::$context['ban_trigger']['hostname']['selected'] ? ' checked' : '', '>
							<label for="hostname_check">', Lang::$txt['ban_on_hostname'], '</label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="', Utils::$context['ban_trigger']['hostname']['value'], '" size="44" onfocus="document.getElementById(\'hostname_check\').checked = true;">
						</dd>';
	if (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['email']['selected'])
		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email"', Utils::$context['ban_trigger']['email']['selected'] ? ' checked' : '', '>
							<label for="email_check">', Lang::$txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="email" name="email" value="', Utils::$context['ban_trigger']['email']['value'], '" size="44" onfocus="document.getElementById(\'email_check\').checked = true;">
						</dd>';
	if (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['banneduser']['selected'])
		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user"', Utils::$context['ban_trigger']['banneduser']['selected'] ? ' checked' : '', '>
							<label for="user_check">', Lang::$txt['ban_on_username'], '</label>:
						</dt>
						<dd>
							<input type="text" value="' . Utils::$context['ban_trigger']['banneduser']['value'] . '" name="user" id="user" size="44"  onfocus="document.getElementById(\'user_check\').checked = true;">
						</dd>';
	echo '
					</dl>
				</fieldset>
				<input type="submit" name="', Utils::$context['ban_trigger']['is_new'] ? 'add_new_trigger' : 'edit_trigger', '" value="', Utils::$context['ban_trigger']['is_new'] ? Lang::$txt['ban_add_trigger_submit'] : Lang::$txt['ban_edit_trigger_submit'], '" class="button">
			</div><!-- .windowbg -->
			<input type="hidden" name="bi" value="' . Utils::$context['ban_trigger']['id'] . '">
			<input type="hidden" name="bg" value="' . Utils::$context['ban_trigger']['group'] . '">
			<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
			<input type="hidden" name="', Utils::$context['admin-bet_token_var'], '" value="', Utils::$context['admin-bet_token'], '">
		</form>
	</div><!-- #manage_bans -->
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'username\',
			sControlId: \'user\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			bItemList: false
		});

		function onUpdateName(oAutoSuggest)
		{
			document.getElementById(\'user_check\').checked = true;
			return true;
		}
		oAddMemberSuggest.registerCallback(\'onBeforeUpdate\', \'onUpdateName\');
	</script>';
}

?>