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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for the member maintenance tasks.
 */
?>
	<script>
		var warningMessage = '';
		var membersSwap = false;

		function swapMembers()
		{
			membersSwap = !membersSwap;
			var membersForm = document.getElementById('membersForm');

			$("#membersPanel").slideToggle(300);

			document.getElementById("membersIcon").src = smf_images_url + (membersSwap ? "/selected_open.png" : "/selected.png");
			setInnerHTML(document.getElementById("membersText"), membersSwap ? "<?= Lang::getTxt('maintain_members_choose', file: 'ManageMaintenance') ?>" : "<?= Lang::getTxt('maintain_members_all', file: 'ManageMaintenance') ?>");

			for (var i = 0; i < membersForm.length; i++)
			{
				if (membersForm.elements[i].type.toLowerCase() == "checkbox")
					membersForm.elements[i].checked = !membersSwap;
			}
		}

		function checkAttributeValidity()
		{
			origText = '<?= Lang::getTxt('reattribute_confirm', file: 'ManageMaintenance') ?>';
			valid = true;

			// Do all the fields!
			if (!document.getElementById('to').value)
				valid = false;
			warningMessage = origText.replace(/%member_to%/, document.getElementById('to').value);

			if (document.getElementById('type_email').checked)
			{
				if (!document.getElementById('from_email').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, '<?= addcslashes(Lang::getTxt('reattribute_confirm_email', file: 'ManageMaintenance'), "'") ?>').replace(/%find%/, document.getElementById('from_email').value);
			}
			else
			{
				if (!document.getElementById('from_name').value)
					valid = false;
				warningMessage = warningMessage.replace(/%type%/, '<?= addcslashes(Lang::getTxt('reattribute_confirm_username', file: 'ManageMaintenance'), "'") ?>').replace(/%find%/, document.getElementById('from_name').value);
			}

			document.getElementById('do_attribute').disabled = valid ? '' : 'disabled';

			setTimeout("checkAttributeValidity();", 500);
			return valid;
		}
		setTimeout("checkAttributeValidity();", 500);
	</script>
	<div id="manage_maintenance">
<?php /* If maintenance has finished, tell the user. */ ?>
<?php if (!empty(Utils::$context['maintenance_finished'])): ?>
		<div class="infobox">
			<?= Lang::getTxt('maintain_done', ['task' => Utils::$context['maintenance_finished']], file: 'Admin') ?>
		</div>
<?php endif; ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('maintain_reattribute_posts', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
				<p><strong><?= Lang::getTxt('reattribute_guest_posts', file: 'ManageMaintenance') ?></strong></p>
				<dl class="settings">
					<dt>
						<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked><?= Lang::getTxt('reattribute_email', file: 'ManageMaintenance') ?></label>
					</dt>
					<dd>
						<input type="text" name="from_email" id="from_email" value="" onclick="document.getElementById('type_email').checked = 'checked'; document.getElementById('from_name').value = '';">
					</dd>
					<dt>
						<label for="type_name"><input type="radio" name="type" id="type_name" value="name"><?= Lang::getTxt('reattribute_username', file: 'ManageMaintenance') ?></label>
					</dt>
					<dd>
						<input type="text" name="from_name" id="from_name" value="" onclick="document.getElementById('type_name').checked = 'checked'; document.getElementById('from_email').value = '';">
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<label for="to"><strong><?= Lang::getTxt('reattribute_current_member', file: 'ManageMaintenance') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="to" id="to" value="">
					</dd>
				</dl>
				<p class="maintain_members">
					<input type="checkbox" name="posts" id="posts" checked>
					<label for="posts"><?= Lang::getTxt('reattribute_increase_posts', file: 'ManageMaintenance') ?></label>
				</p>
				<input type="submit" id="do_attribute" value="<?= Lang::getTxt('reattribute', file: 'ManageMaintenance') ?>" onclick="if (!checkAttributeValidity()) return false;
				return confirm(warningMessage);" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
			</form>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="<?= Config::$scripturl ?>?action=helpadmin;help=maintenance_members" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a> <?= Lang::getTxt('maintain_members', file: 'ManageMaintenance') ?>
			</h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
				<div class="padding">
					<a id="membersLink"></a><?= Lang::getTxt(
						'maintain_members_since',
						[
							'input_condition' => '<select name="del_type"><option value="activated" selected>' . Lang::getTxt('maintain_members_activated', file: 'ManageMaintenance') . '</option><option value="logged">' . Lang::getTxt('maintain_members_logged_in', file: 'ManageMaintenance') . '</option></select>',
							'input_number' => '<input type="number" name="maxdays" value="30" size="3">',
						],
						file: 'ManageMaintenance',
					) ?>
				</div>
				<div class="padding">
<?php if (!empty(Config::$modSettings['always_anonymize_deleted_accounts'])): ?>
					<?= Lang::getTxt('deleteAccount_anonymize_forced', file: 'Profile') ?>

<?php else: ?>
					<label for="anonymize">
						<input type="checkbox" name="anonymize" id="anonymize" value="1"> <?= Lang::getTxt('deleteAccount_anonymize', file: 'Profile') ?>
					</label>
<?php endif; ?>
				</div>
				<div class="padding">
					<a href="#membersLink" onclick="swapMembers();"><img src="<?= Theme::$current->settings['images_url'] ?>/selected.png" alt="+" id="membersIcon"></a> <a href="#membersLink" onclick="swapMembers();" id="membersText" style="font-weight: bold;"><?= Lang::getTxt('maintain_members_all', file: 'ManageMaintenance') ?></a>
				</div>
				<div style="display: none;" id="membersPanel">
<?php foreach (Utils::$context['membergroups'] as $group): ?>
					<label for="groups<?= $group['id'] ?>"><input type="checkbox" name="groups[<?= $group['id'] ?>]" id="groups<?= $group['id'] ?>" checked> <?= $group['name'] ?></label><br>
<?php endforeach; ?>
				</div>
				<input type="submit" value="<?= Lang::getTxt('maintain_old_remove', file: 'ManageMaintenance') ?>" data-confirm="<?= Lang::getTxt('maintain_members_confirm', file: 'ManageMaintenance') ?>" class="button you_sure">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
			</form>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('maintain_recountposts', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8" id="membersRecountForm">
				<p><?= Lang::getTxt('maintain_recountposts_info', file: 'ManageMaintenance') ?></p>
				<input type="submit" value="<?= Lang::getTxt('maintain_run_now', file: 'ManageMaintenance') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
			</form>
		</div>
	</div><!-- #manage_maintenance -->

	<script>
		var oAttributeMemberSuggest = new smc_AutoSuggest({
			sSelf: 'oAttributeMemberSuggest',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'attributeMember',
			sControlId: 'to',
			sSearchType: 'member',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			bItemList: false
		});
	</script>