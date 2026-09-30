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
 * Edit an existing membergroup.
 */
?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=membergroups;sa=edit;group=<?= Utils::$context['group']['id'] ?>" method="post" accept-charset="UTF-8" name="groupForm" id="groupForm">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('membergroups_edit_group', ['name' => Utils::$context['group']['name']], file: 'ManageMembers') ?>

				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong><?= Lang::getTxt('membergroups_edit_name', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" value="<?= Utils::$context['group']['editable_name'] ?>" size="30">
					</dd><?php if (Utils::$context['group']['id'] != 3 && Utils::$context['group']['id'] != 4): ?>

					<dt id="group_desc_text">
						<label for="group_desc_input"><strong><?= Lang::getTxt('membergroups_edit_desc', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<textarea name="group_desc" id="group_desc_input" rows="4" cols="40"><?= Utils::$context['group']['description'] ?></textarea>
					</dd><?php endif; ?><?php /* Group type... */ ?><?php if (Utils::$context['group']['can_change_type']): ?>

					<dt>
						<label for="group_type"><strong><?= Lang::getTxt('membergroups_edit_group_type', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend><?= Lang::getTxt('membergroups_edit_select_group_type', file: 'ManageMembers') ?></legend>
							<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0"<?= !Utils::$context['group']['is_post_group'] && Utils::$context['group']['type'] == 0 ? ' checked' : '' ?><?= (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : '') ?>><?= Lang::getTxt('membergroups_group_type_private', file: 'ManageMembers') ?></label><br><?php if (Utils::$context['group']['allow_protected']): ?>

							<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1"<?= Utils::$context['group']['type'] == 1 ? ' checked' : '' ?><?= (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : '') ?>><?= Lang::getTxt('membergroups_group_type_protected', file: 'ManageMembers') ?></label><br><?php endif; ?>

							<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2"<?= Utils::$context['group']['type'] == 2 ? ' checked' : '' ?><?= (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : '') ?>><?= Lang::getTxt('membergroups_group_type_request', file: 'ManageMembers') ?></label><br>
							<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3"<?= Utils::$context['group']['type'] == 3 ? ' checked' : '' ?><?= (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : '') ?>><?= Lang::getTxt('membergroups_group_type_free', file: 'ManageMembers') ?></label><br><?php if (Utils::$context['group']['allow_post_group']): ?>


							<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1"<?= Utils::$context['group']['is_post_group'] ? ' checked' : '' ?> onclick="swapPostGroup(1);"><?= Lang::getTxt('membergroups_group_type_post', file: 'ManageMembers') ?></label><br><?php endif; ?>

						</fieldset>
					</dd><?php endif; ?><?php if (Utils::$context['group']['id'] != 3 && Utils::$context['group']['id'] != 4): ?>

					<dt id="group_moderators_text">
						<label for="group_moderators"><strong><?= Lang::getTxt('moderators', file: 'General') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="group_moderators" id="group_moderators" value="<?= Utils::$context['group']['moderator_list'] ?>" size="30">
						<div id="moderator_container"></div>
					</dd>
					<dt id="group_hidden_text">
						<label for="group_hidden_input"><strong><?= Lang::getTxt('membergroups_edit_hidden', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<select name="group_hidden" id="group_hidden_input" onchange="if (this.value == 2 &amp;&amp; !confirm('<?= Lang::getTxt('membergroups_edit_hidden_warning', file: 'ManageMembers') ?>')) this.value = 0;">
							<option value="0"<?= Utils::$context['group']['hidden'] ? '' : ' selected' ?>><?= Lang::getTxt('membergroups_edit_hidden_no', file: 'ManageMembers') ?></option>
							<option value="1"<?= Utils::$context['group']['hidden'] == 1 ? ' selected' : '' ?>><?= Lang::getTxt('membergroups_edit_hidden_boardindex', file: 'ManageMembers') ?></option>
							<option value="2"<?= Utils::$context['group']['hidden'] == 2 ? ' selected' : '' ?>><?= Lang::getTxt('membergroups_edit_hidden_all', file: 'ManageMembers') ?></option>
						</select>
					</dd><?php endif; ?><?php /* Can they inherit permissions? */ ?><?php if (Utils::$context['group']['id'] > 1 && Utils::$context['group']['id'] != 3): ?>

					<dt id="group_inherit_text">
						<label for="group_inherit_input"><strong><?= Lang::getTxt('membergroups_edit_inherit_permissions', file: 'ManageMembers') ?></strong></label><br>
						<span class="smalltext"><?= Lang::getTxt('membergroups_edit_inherit_permissions_desc', file: 'ManageMembers') ?></span>
					</dt>
					<dd>
						<select name="group_inherit" id="group_inherit_input">
							<option value="-2"><?= Lang::getTxt('membergroups_edit_inherit_permissions_no', file: 'ManageMembers') ?></option>
							<option value="-1"<?= Utils::$context['group']['inherited_from'] == -1 ? ' selected' : '' ?>><?= Lang::getTxt('membergroups_edit_inherit_permissions_from', ['group' => Lang::getTxt('membergroups_guests', file: 'Admin')]) ?></option>
							<option value="0"<?= Utils::$context['group']['inherited_from'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('membergroups_edit_inherit_permissions_from', ['group' => Lang::getTxt('membergroups_members', file: 'Admin')]) ?></option><?php /* For all the inheritable groups show an option. */ ?><?php foreach (Utils::$context['inheritable_groups'] as $id => $group): ?>

							<option value="<?= $id ?>"<?= Utils::$context['group']['inherited_from'] == $id ? ' selected' : '' ?>><?= Lang::getTxt('membergroups_edit_inherit_permissions_from', ['group' => $group], file: 'ManageMembers') ?></option><?php endforeach; ?>

						</select>
						<input type="hidden" name="old_inherit" value="<?= Utils::$context['group']['inherited_from'] ?>">
					</dd><?php endif; ?><?php if (Utils::$context['group']['allow_post_group']): ?>


					<dt id="min_posts_text">
						<label for="min_posts_input"><strong><?= Lang::getTxt('membergroups_min_posts', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<input type="number" name="min_posts" id="min_posts_input"<?= Utils::$context['group']['is_post_group'] ? ' value="' . Utils::$context['group']['min_posts'] . '"' : '' ?> size="6">
					</dd><?php endif; ?>

					<dt>
						<label for="online_color_input"><strong><?= Lang::getTxt('membergroups_online_color', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="online_color" id="online_color_input" value="<?= Utils::$context['group']['color'] ?>" size="20">
					</dd>
					<dt>
						<label for="icon_count_input"><strong><?= Lang::getTxt('membergroups_icon_count', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<input type="number" name="icon_count" id="icon_count_input" value="<?= Utils::$context['group']['icon_count'] ?>" size="4">
					</dd><?php /* Do we have any possible icons to select from? */ ?><?php if (!empty(Utils::$context['possible_icons'])): ?>

					<dt>
						<label for="icon_image_input"><strong><?= Lang::getTxt('membergroups_icon_image', file: 'ManageMembers') ?></strong></label><br>
						<span class="smalltext"><?= Lang::getTxt('membergroups_icon_image_note', file: 'ManageMembers') ?></span>
						<span class="smalltext"><?= Lang::getTxt('membergroups_icon_image_size', file: 'ManageMembers') ?></span>
					</dt>
					<dd>
						<?= Lang::getTxt('membergroups_images_url', file: 'ManageMembers') ?>

						<select name="icon_image" id="icon_image_input"><?php /* For every possible icon, create an option. */ ?><?php foreach (Utils::$context['possible_icons'] as $icon): ?>

							<option value="<?= $icon ?>"<?= Utils::$context['group']['icon_image'] == $icon ? ' selected' : '' ?>><?= $icon ?></option><?php endforeach; ?>

						</select>
						<img id="icon_preview" src="" alt="*">
					</dd><?php /* No? Hide the entire control. */ ?><?php else: ?>

					<input type="hidden" name="icon_image" value=""><?php endif; ?>

					<dt>
						<label for="max_messages_input"><strong><?= Lang::getTxt('membergroups_max_messages', file: 'ManageMembers') ?></strong></label><br>
						<span class="smalltext"><?= Lang::getTxt('membergroups_max_messages_note', file: 'ManageMembers') ?></span>
					</dt>
					<dd>
						<input type="text" name="max_messages" id="max_messages_input" value="<?= Utils::$context['group']['id'] == 1 ? 0 : Utils::$context['group']['max_messages'] ?>" size="6"<?= Utils::$context['group']['id'] == 1 ? ' disabled' : '' ?>>
					</dd><?php /* Force 2FA for this membergroup? */ ?><?php if (!empty(Config::$modSettings['tfa_mode']) && Config::$modSettings['tfa_mode'] == 2): ?>

					<dt>
						<label for="group_tfa_force_input"><strong><?= Lang::getTxt('membergroups_tfa_force', file: 'ManageMembers') ?></strong></label><br>
						<span class="smalltext"><?= Lang::getTxt('membergroups_tfa_force_note', file: 'ManageMembers') ?></span>
					</dt>
					<dd>
						<input type="checkbox" name="group_tfa_force"<?= Utils::$context['group']['tfa_required'] ? ' checked' : '' ?>>
					</dd><?php endif; ?><?php if (!empty(Utils::$context['categories'])): ?>

					<dt>
						<strong><?= Lang::getTxt('membergroups_new_board', file: 'ManageMembers') ?></strong><?= Utils::$context['group']['is_post_group'] ? '<br>
						<span class="smalltext">' . Lang::getTxt('membergroups_new_board_post_groups', file: 'ManageMembers') . '</span>' : '' ?>

					</dt>
					<dd><?php if (!empty(Utils::$context['can_manage_boards'])): ?><?= Lang::getTxt('membergroups_can_manage_access', file: 'ManageMembers') ?><?php else: ?><?php $this->subTemplate('add_edit_group_boards_list', ['collapse' => true, 'form_id' => 'groupForm']); ?><?php endif; ?>

					</dd><?php endif; ?>

				</dl>
				<input type="submit" name="save" value="<?= Lang::getTxt('membergroups_edit_save', file: 'ManageMembers') ?>" class="button"><?= Utils::$context['group']['allow_delete'] ? '
				<input type="submit" name="delete" value="' . Lang::getTxt('membergroups_delete', file: 'ManageMembers') . '" data-confirm="' . Lang::getTxt(Utils::$context['is_moderator_group'] ? 'membergroups_confirm_delete_mod' : 'membergroups_confirm_delete', file: 'ManageMembers') . '" class="button you_sure">' : '' ?>

			</div><!-- .windowbg -->
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-mmg_token_var'] ?>" value="<?= Utils::$context['admin-mmg_token'] ?>">
		</form>
	<script>
		var oModeratorSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'group_moderators',
			sControlId: 'group_moderators',
			sSearchType: 'member',
			bItemList: true,
			sPostName: 'moderator_list',
			sURLMask: 'action=profile;u=%item_id%',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			sItemListContainerId: 'moderator_container',
			aListItems: [<?php foreach (Utils::$context['group']['moderators'] as $id_member => $member_name): ?>

				{
					sItemId: <?= Utils::escapeJavaScript($id_member) ?>,
					sItemName: <?= Utils::escapeJavaScript($member_name) ?>

				}<?= $id_member == Utils::$context['group']['last_moderator_id'] ? '' : ',' ?><?php endforeach; ?>

			]
		});
	</script><?php if (Utils::$context['group']['allow_post_group']): ?>

	<script>
		function swapPostGroup(isChecked)
		{
			var is_moderator_group = <?= (int) Utils::$context['is_moderator_group'] ?>;
			var group_type = <?= Utils::$context['group']['type'] ?>;
			var min_posts_text = document.getElementById('min_posts_text');
			var group_desc_text = document.getElementById('group_desc_text');
			var group_hidden_text = document.getElementById('group_hidden_text');
			var group_moderators_text = document.getElementById('group_moderators_text');

			// If it's a moderator group, warn of possible problems... and remember the group type
			if (isChecked && is_moderator_group && !confirm('<?= Lang::getTxt('membergroups_swap_mod', file: 'ManageMembers') ?>'))
			{
				isChecked = false;

				switch(group_type)
				{
					case 0:
						document.getElementById('group_type_private').checked = true;
						break;
					case 1:
						document.getElementById('group_type_protected').checked = true;
						break;
					case 2:
						document.getElementById('group_type_request').checked = true;
						break;
					case 3:
						document.getElementById('group_type_free').checked = true;
						break;
					default:
						document.getElementById('group_type_private').checked = true;
						break;
				}
			}

			document.forms.groupForm.min_posts.disabled = !isChecked;
			min_posts_text.style.color = isChecked ? "" : "#888888";
			document.forms.groupForm.group_desc_input.disabled = isChecked;
			group_desc_text.style.color = !isChecked ? "" : "#888888";
			document.forms.groupForm.group_hidden_input.disabled = isChecked;
			group_hidden_text.style.color = !isChecked ? "" : "#888888";
			document.forms.groupForm.group_moderators.disabled = isChecked;
			group_moderators_text.style.color = !isChecked ? "" : "#888888";
		}

		swapPostGroup(<?= Utils::$context['group']['is_post_group'] ? 'true' : 'false' ?>);
	</script><?php endif; ?>
