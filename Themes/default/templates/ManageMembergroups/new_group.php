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
 * Add a new membergroup.
 */
?>
		<form id="new_group" action="<?= Config::$scripturl ?>?action=admin;area=membergroups;sa=add" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('membergroups_new_group', file: 'Admin') ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong><?= Lang::getTxt('membergroups_group_name', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" size="30">
					</dd>
<?php if (Utils::$context['undefined_group']): ?>
					<dt>
						<label for="group_type"><strong><?= Lang::getTxt('membergroups_edit_group_type', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend><?= Lang::getTxt('membergroups_edit_select_group_type', file: 'ManageMembers') ?></legend>
							<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0" checked onclick="swapPostGroup(0);"><?= Lang::getTxt('membergroups_group_type_private', file: 'ManageMembers') ?></label><br>
<?php if (Utils::$context['allow_protected']): ?>
							<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1" onclick="swapPostGroup(0);"><?= Lang::getTxt('membergroups_group_type_protected', file: 'ManageMembers') ?></label><br>
<?php endif; ?>
							<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2" onclick="swapPostGroup(0);"><?= Lang::getTxt('membergroups_group_type_request', file: 'ManageMembers') ?></label><br>
							<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3" onclick="swapPostGroup(0);"><?= Lang::getTxt('membergroups_group_type_free', file: 'ManageMembers') ?></label><br>
							<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1" onclick="swapPostGroup(1);"><?= Lang::getTxt('membergroups_group_type_post', file: 'ManageMembers') ?></label><br>
						</fieldset>
					</dd>
<?php endif; ?>
<?php if (Utils::$context['post_group'] || Utils::$context['undefined_group']): ?>
					<dt id="min_posts_text">
						<label for="min_posts_input"><strong><?= Lang::getTxt('membergroups_min_posts', file: 'ManageMembers') ?></strong></label>
					</dt>
					<dd>
						<input type="number" name="min_posts" id="min_posts_input" size="5">
					</dd>
<?php endif; ?>
<?php if (!Utils::$context['post_group'] || !empty(Config::$modSettings['permission_enable_postgroups'])): ?>
					<dt>
						<label for="permission_base"><strong><?= Lang::getTxt('membergroups_permissions', file: 'Admin') ?></strong></label><br>
						<span class="smalltext"><?= Lang::getTxt('membergroups_can_edit_later', file: 'ManageMembers') ?></span>
					</dt>
					<dd>
						<fieldset id="permission_base">
							<legend><?= Lang::getTxt('membergroups_select_permission_type', file: 'ManageMembers') ?></legend>
							<input type="radio" name="perm_type" id="perm_type_inherit" value="inherit" checked>
							<label for="perm_type_inherit"><?= Lang::getTxt('membergroups_new_as_inherit', file: 'ManageMembers') ?></label>
							<select name="inheritperm" id="inheritperm_select" onclick="document.getElementById('perm_type_inherit').checked = true;">
								<option value="-1"><?= Lang::getTxt('membergroups_guests', file: 'Admin') ?></option>
								<option value="0" selected><?= Lang::getTxt('membergroups_members', file: 'Admin') ?></option>
<?php foreach (Utils::$context['groups'] as $group): ?>
								<option value="<?= $group['id'] ?>"><?= $group['name'] ?></option>
<?php endforeach; ?>
							</select>
							<br>
							<input type="radio" name="perm_type" id="perm_type_copy" value="copy">
							<label for="perm_type_copy"><?= Lang::getTxt('membergroups_new_as_copy', file: 'ManageMembers') ?></label>
							<select name="copyperm" id="copyperm_select" onclick="document.getElementById('perm_type_copy').checked = true;">
								<option value="-1"><?= Lang::getTxt('membergroups_guests', file: 'Admin') ?></option>
								<option value="0" selected><?= Lang::getTxt('membergroups_members', file: 'Admin') ?></option>
<?php foreach (Utils::$context['groups'] as $group): ?>
								<option value="<?= $group['id'] ?>"><?= $group['name'] ?></option>
<?php endforeach; ?>
							</select>
							<br>
							<input type="radio" name="perm_type" id="perm_type_predefined" value="predefined">
							<label for="perm_type_predefined"><?= Lang::getTxt('membergroups_new_as_type', file: 'ManageMembers') ?></label>
							<select name="level" id="level_select" onclick="document.getElementById('perm_type_predefined').checked = true;">
								<option value="restrict"><?= Lang::getTxt('permitgroups_restrict', file: 'Admin') ?></option>
								<option value="standard" selected><?= Lang::getTxt('permitgroups_standard', file: 'Admin') ?></option>
								<option value="moderator"><?= Lang::getTxt('permitgroups_moderator', file: 'Admin') ?></option>
								<option value="maintenance"><?= Lang::getTxt('permitgroups_maintenance', file: 'Admin') ?></option>
							</select>
						</fieldset>
					</dd>
<?php endif; ?>
					<dt>
						<strong><?= Lang::getTxt('membergroups_new_board', file: 'ManageMembers') ?></strong><?= Utils::$context['post_group'] ? '<br>
						<span class="smalltext">' . Lang::getTxt('membergroups_new_board_post_groups', file: 'ManageMembers') . '</span>' : '' ?>
					</dt>
					<dd><?php $this->subTemplate('add_edit_group_boards_list', ['collapse' => false]); ?>
					</dd>
				</dl>
				<input type="submit" value="<?= Lang::getTxt('membergroups_add_group', file: 'Admin') ?>" class="button">
			</div><!-- .windowbg -->
<?php if (Utils::$context['undefined_group']): ?>
			<script>
				function swapPostGroup(isChecked)
				{
					var min_posts_text = document.getElementById('min_posts_text');
					document.getElementById('min_posts_input').disabled = !isChecked;
					min_posts_text.style.color = isChecked ? "" : "#888888";
				}
				swapPostGroup(<?= Utils::$context['post_group'] ? 'true' : 'false' ?>);
			</script>
<?php endif; ?>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-mmg_token_var'] ?>" value="<?= Utils::$context['admin-mmg_token'] ?>">
		</form>