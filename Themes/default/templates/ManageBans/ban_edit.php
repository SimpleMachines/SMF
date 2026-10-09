<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Add or edit a ban
 */
?>
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="<?= Utils::$context['form_url'] ?>" method="post" accept-charset="UTF-8" onsubmit="return confirmBan(this);"><?php /* If there were errors creating the ban, show them. */ ?><?php if (!empty(Utils::$context['error_messages'])): ?>
			<div class="errorbox">
				<strong><?= Lang::getTxt('ban_errors_detected', file: 'Admin') ?></strong>
				<ul><?php foreach (Utils::$context['error_messages'] as $error): ?>
					<li class="error"><?= $error ?></li><?php endforeach; ?>
				</ul>
			</div><?php endif; ?>
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Utils::$context['ban']['is_new'] ? Lang::getTxt('ban_add_new', file: 'Admin') : Lang::getTxt('ban_edit', Utils::$context['ban'], file: 'Admin') ?>
				</h3>
			</div><?php if (Utils::$context['ban']['is_new']): ?>
			<div class="information noup"><?= Lang::getTxt('ban_add_notes', file: 'Admin') ?></div><?php endif; ?>
			<div class="windowbg noup">
				<dl class="settings">
					<dt id="ban_name_label">
						<strong><?= Lang::getTxt('ban_name', file: 'Admin') ?>:</strong>
					</dt>
					<dd>
						<input type="text" id="ban_name" name="ban_name" value="<?= Utils::$context['ban']['name'] ?>" size="45" maxlength="60">
					</dd><?php if (isset(Utils::$context['ban']['reason'])): ?>
					<dt>
						<strong><label for="reason"><?= Lang::getTxt('ban_reason', file: 'General') ?></label></strong><br>
						<span class="smalltext"><?= Lang::getTxt('ban_reason_desc', file: 'Admin') ?></span>
					</dt>
					<dd>
						<textarea name="reason" id="reason" cols="40" rows="3"><?= Utils::$context['ban']['reason'] ?></textarea>
					</dd><?php endif; ?><?php if (isset(Utils::$context['ban']['notes'])): ?>
					<dt>
						<strong><label for="ban_notes"><?= Lang::getTxt('ban_notes', file: 'Admin') ?></label></strong><br>
						<span class="smalltext"><?= Lang::getTxt('ban_notes_desc', file: 'Admin') ?></span>
					</dt>
					<dd>
						<textarea name="notes" id="ban_notes" cols="40" rows="3"><?= Utils::$context['ban']['notes'] ?></textarea>
					</dd><?php endif; ?>
				</dl>
				<fieldset class="ban_settings floatleft">
					<legend>
						<?= Lang::getTxt('ban_expiration', file: 'Admin') ?>
					</legend>
					<input type="radio" name="expiration" value="never" id="never_expires" onclick="fUpdateStatus();"<?= Utils::$context['ban']['expiration']['status'] == 'never' ? ' checked' : '' ?>> <label for="never_expires"><?= Lang::getTxt('never', file: 'General') ?></label><br>
					<input type="radio" name="expiration" value="one_day" id="expires_one_day" onclick="fUpdateStatus();"<?= Utils::$context['ban']['expiration']['status'] == 'one_day' ? ' checked' : '' ?>> <label for="expires_one_day"><?= Lang::getTxt('ban_will_expire_within', file: 'Admin') ?></label> <input type="number" name="expire_date" id="expire_date" size="3" value="<?= Utils::$context['ban']['expiration']['days'] ?>"> <?= str_replace(Utils::$context['ban']['expiration']['days'], '', Lang::getTxt('number_of_days', [Utils::$context['ban']['expiration']['days']], file: 'General')) ?><br>
					<input type="radio" name="expiration" value="expired" id="already_expired" onclick="fUpdateStatus();"<?= Utils::$context['ban']['expiration']['status'] == 'expired' ? ' checked' : '' ?>> <label for="already_expired"><?= Lang::getTxt('ban_expired', file: 'Admin') ?></label>
				</fieldset>
				<fieldset class="ban_settings floatright">
					<legend>
						<?= Lang::getTxt('ban_restriction', file: 'Admin') ?>
					</legend>
					<input type="radio" name="full_ban" id="full_ban" value="1" onclick="fUpdateStatus();"<?= Utils::$context['ban']['cannot']['access'] ? ' checked' : '' ?>> <label for="full_ban"><?= Lang::getTxt('ban_full_ban', file: 'Admin') ?></label><br>
					<input type="radio" name="full_ban" id="partial_ban" value="0" onclick="fUpdateStatus();"<?= !Utils::$context['ban']['cannot']['access'] ? ' checked' : '' ?>> <label for="partial_ban"><?= Lang::getTxt('ban_partial_ban', file: 'Admin') ?></label><br>
					<input type="checkbox" name="cannot_post" id="cannot_post" value="1"<?= Utils::$context['ban']['cannot']['post'] ? ' checked' : '' ?> class="ban_restriction"> <label for="cannot_post"><?= Lang::getTxt('ban_cannot_post', file: 'Admin') ?></label> (<a href="<?= Config::$scripturl ?>?action=helpadmin;help=ban_cannot_post" onclick="return reqOverlayDiv(this.href);">?</a>)<br>
					<input type="checkbox" name="cannot_register" id="cannot_register" value="1"<?= Utils::$context['ban']['cannot']['register'] ? ' checked' : '' ?> class="ban_restriction"> <label for="cannot_register"><?= Lang::getTxt('ban_cannot_register', file: 'Admin') ?></label><br>
					<input type="checkbox" name="cannot_login" id="cannot_login" value="1"<?= Utils::$context['ban']['cannot']['login'] ? ' checked' : '' ?> class="ban_restriction"> <label for="cannot_login"><?= Lang::getTxt('ban_cannot_login', file: 'Admin') ?></label><br>
				</fieldset>
				<br class="clear_right"><?php if (!empty(Utils::$context['ban_suggestions'])): ?>
				<fieldset>
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, 'ban_suggestion');"> <?= Lang::getTxt('ban_triggers', file: 'Admin') ?>
					</legend>
					<dl class="settings">
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip"<?= !empty(Utils::$context['ban_suggestions']['main_ip']) ? ' checked' : '' ?>>
							<label for="main_ip_check"><?= Lang::getTxt('ban_on_ip', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="<?= Utils::$context['ban_suggestions']['main_ip'] ?>" size="44" onfocus="document.getElementById('main_ip_check').checked = true;">
						</dd><?php if (empty(Config::$modSettings['disableHostnameLookup'])): ?>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname"<?= !empty(Utils::$context['ban_suggestions']['hostname']) ? ' checked' : '' ?>>
							<label for="hostname_check"><?= Lang::getTxt('ban_on_hostname', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="<?= Utils::$context['ban_suggestions']['hostname'] ?>" size="44" onfocus="document.getElementById('hostname_check').checked = true;">
						</dd><?php endif; ?>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email"<?= !empty(Utils::$context['ban_suggestions']['email']) ? ' checked' : '' ?>>
							<label for="email_check"><?= Lang::getTxt('ban_on_email', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="text" name="email" value="<?= Utils::$context['ban_suggestions']['email'] ?>" size="44" onfocus="document.getElementById('email_check').checked = true;">
						</dd>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user"<?= !empty(Utils::$context['ban_suggestions']['user']) || isset(Utils::$context['ban']['from_user']) ? ' checked' : '' ?>>
							<label for="user_check"><?= Lang::getTxt('ban_on_username', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="text" <?= isset(Utils::$context['ban']['from_user']) ? 'readonly value="' . Utils::$context['ban_suggestions']['member']['name'] . '"' : ' value=""' ?> name="user" id="user" size="44">
						</dd>
					</dl><?php if (!empty(Utils::$context['ban_suggestions']['other_ips'])): ?><?php foreach (Utils::$context['ban_suggestions']['other_ips'] as $key => $ban_ips): ?><?php if (!empty($ban_ips)): ?>
					<div><?= Lang::getTxt($key, file: 'Profile') ?></div>
					<dl class="settings"><?php $count = 0; ?><?php foreach ($ban_ips as $ip): ?>
						<dt>
							<input type="checkbox" id="suggestions_<?= $key ?>_<?= $count ?>" name="ban_suggestions[<?= $key ?>][]"<?= !empty(Utils::$context['ban_suggestions']['saved_triggers'][$key]) && in_array($ip, Utils::$context['ban_suggestions']['saved_triggers'][$key]) ? ' checked' : '' ?> value="<?= $ip ?>">
						</dt>
						<dd>
							<label for="suggestions_<?= $key ?>_<?= $count++ ?>"><?= $ip ?></label>
						</dd><?php endforeach; ?>
					</dl><?php endif; ?><?php endforeach; ?><?php endif; ?>
				</fieldset><?php endif; ?>
				<input type="submit" name="<?= Utils::$context['ban']['is_new'] ? 'add_ban' : 'modify_ban' ?>" value="<?= Lang::getTxt(Utils::$context['ban']['is_new'] ? 'ban_add' : 'ban_modify', file: 'Admin') ?>" class="button">
				<input type="hidden" name="old_expire" value="<?= Utils::$context['ban']['expiration']['days'] ?>">
				<input type="hidden" name="bg" value="<?= Utils::$context['ban']['id'] ?>"><?= isset(Utils::$context['ban']['from_user']) ? '
				<input type="hidden" name="u" value="' . Utils::$context['ban_suggestions']['member']['id'] . '">' : '' ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-bet_token_var'] ?>" value="<?= Utils::$context['admin-bet_token'] ?>">
			</div><!-- .windowbg -->
		</form><?php if (!Utils::$context['ban']['is_new'] && empty(Utils::$context['ban_suggestions'])): ?>
		<br><?php $this->subTemplate('show_list', ['list_id' => 'ban_items']); ?><?php endif; ?>
	</div><!-- #manage_bans -->
	<script>
		var fUpdateStatus = function ()
		{
			document.getElementById("expire_date").disabled = !document.getElementById("expires_one_day").checked;
			document.getElementById("cannot_post").disabled = document.getElementById("full_ban").checked;
			document.getElementById("cannot_register").disabled = document.getElementById("full_ban").checked;
			document.getElementById("cannot_login").disabled = document.getElementById("full_ban").checked;
		}
		addLoadEvent(fUpdateStatus);<?php /* Auto suggest only needed for adding new bans, not editing */ ?><?php if (Utils::$context['ban']['is_new'] && empty($_REQUEST['u'])): ?>

		var oAddMemberSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'user',
			sControlId: 'user',
			sSearchType: 'member',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
		});

		function confirmBan(aForm)
		{
			if (aForm.ban_name.value == '')
			{
				alert('<?= Lang::getTxt('ban_name_empty', file: 'Errors') ?>');
				return false;
			}

			if (aForm.partial_ban.checked && !(aForm.cannot_post.checked || aForm.cannot_register.checked || aForm.cannot_login.checked))
			{
				alert('<?= Lang::getTxt('ban_restriction_empty', file: 'Admin') ?>');
				return false;
			}
		}
	</script><?php endif; ?>
