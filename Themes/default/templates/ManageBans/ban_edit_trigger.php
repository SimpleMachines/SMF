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
 * Add or edit a ban trigger
 */
?>

	<div id="manage_bans">
		<form id="admin_form_wrapper" action="<?= Utils::$context['form_url'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt(Utils::$context['ban_trigger']['is_new'] ? 'ban_add_trigger' : 'ban_edit_trigger_title', file: 'Admin') ?>

				</h3>
			</div>
			<div class="windowbg">
				<fieldset>
<?php if (Utils::$context['ban_trigger']['is_new']): ?>
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, 'ban_suggestion');"> <?= Lang::getTxt('ban_triggers', file: 'Admin') ?>

					</legend>
<?php endif; ?>
					<dl class="settings">
<?php if (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['ip']['selected']): ?>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip"<?= Utils::$context['ban_trigger']['ip']['selected'] ? ' checked' : '' ?>>
							<label for="main_ip_check"><?= Lang::getTxt('ban_on_ip', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="<?= Utils::$context['ban_trigger']['ip']['value'] ?>" size="44" onfocus="document.getElementById('main_ip_check').checked = true;">
						</dd>
<?php endif; ?>
<?php if (empty(Config::$modSettings['disableHostnameLookup']) && (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['hostname']['selected'])): ?>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname"<?= Utils::$context['ban_trigger']['hostname']['selected'] ? ' checked' : '' ?>>
							<label for="hostname_check"><?= Lang::getTxt('ban_on_hostname', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="<?= Utils::$context['ban_trigger']['hostname']['value'] ?>" size="44" onfocus="document.getElementById('hostname_check').checked = true;">
						</dd>
<?php endif; ?>
<?php if (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['email']['selected']): ?>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email"<?= Utils::$context['ban_trigger']['email']['selected'] ? ' checked' : '' ?>>
							<label for="email_check"><?= Lang::getTxt('ban_on_email', file: 'Admin') ?></label>
						</dt>
						<dd>
							<input type="email" name="email" value="<?= Utils::$context['ban_trigger']['email']['value'] ?>" size="44" onfocus="document.getElementById('email_check').checked = true;">
						</dd>
<?php endif; ?>
<?php if (Utils::$context['ban_trigger']['is_new'] || Utils::$context['ban_trigger']['banneduser']['selected']): ?>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user"<?= Utils::$context['ban_trigger']['banneduser']['selected'] ? ' checked' : '' ?>>
							<label for="user_check"><?= Lang::getTxt('ban_on_username', file: 'Admin') ?></label>:
						</dt>
						<dd>
							<input type="text" value="<?= Utils::$context['ban_trigger']['banneduser']['value'] ?>" name="user" id="user" size="44"  onfocus="document.getElementById('user_check').checked = true;">
						</dd>
<?php endif; ?>
					</dl>
				</fieldset>
				<input type="submit" name="<?= Utils::$context['ban_trigger']['is_new'] ? 'add_new_trigger' : 'edit_trigger' ?>" value="<?= Lang::getTxt(Utils::$context['ban_trigger']['is_new'] ? 'ban_add_trigger_submit' : 'ban_edit_trigger_submit', file: 'Admin') ?>" class="button">
			</div><!-- .windowbg -->
			<input type="hidden" name="bi" value="<?= Utils::$context['ban_trigger']['id'] ?>">
			<input type="hidden" name="bg" value="<?= Utils::$context['ban_trigger']['group'] ?>">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-bet_token_var'] ?>" value="<?= Utils::$context['admin-bet_token'] ?>">
		</form>
	</div><!-- #manage_bans -->
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'username',
			sControlId: 'user',
			sSearchType: 'member',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
		});

		function onUpdateName(oAutoSuggest)
		{
			document.getElementById('user_check').checked = true;
			return true;
		}
		oAddMemberSuggest.registerCallback('onBeforeUpdate', 'onUpdateName');
	</script>