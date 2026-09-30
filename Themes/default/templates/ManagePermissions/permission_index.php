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
 * The main manage permissions page
 */
?><?php /* Not allowed to edit? */ ?><?php if (!Utils::$context['can_modify']): ?>
	<div class="errorbox">
		<?= Lang::getTxt('permission_cannot_edit', ['url' => Config::$scripturl . '?action=admin;area=permissions;sa=profiles'], file: 'ManagePermissions') ?>
	</div><?php endif; ?>
	<div id="admin_form_wrapper">
		<form action="<?= Config::$scripturl ?>?action=admin;area=permissions;sa=quick" method="post" accept-charset="UTF-8" name="permissionForm" id="permissionForm"><?php if (!empty(Utils::$context['profile'])): ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('permissions_for_profile', Utils::$context['profile'], file: 'ManagePermissions') ?></h3>
			</div><?php else: ?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('permissions_title', file: 'ManagePermissions') ?></h3>
			</div><?php endif; ?>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th><?= Lang::getTxt('membergroups_name', file: 'ManageMembers') ?></th>
						<th class="small_table"><?= Lang::getTxt('membergroups_members_top', file: 'ManageMembers') ?></th><?php if (empty(Config::$modSettings['permission_enable_deny'])): ?>
						<th class="small_table"><?= Lang::getTxt('membergroups_permissions', file: 'Admin') ?></th><?php else: ?>
						<th class="small_table"><?= Lang::getTxt('permissions_allowed', file: 'ManagePermissions') ?></th>
						<th class="small_table"><?= Lang::getTxt('permissions_denied', file: 'ManagePermissions') ?></th><?php endif; ?>
						<th class="small_table"><?= Lang::getTxt(Utils::$context['can_modify'] ? 'permissions_modify' : 'permissions_view', file: 'ManagePermissions') ?></th>
						<th class="table_icon centercol">
							<?= Utils::$context['can_modify'] ? '<input type="checkbox" onclick="invertAll(this, this.form, \'group\');">' : '' ?>
						</th>
					</tr>
				</thead>
				<tbody><?php foreach (Utils::$context['groups'] as $group): ?>
					<tr class="windowbg">
						<td>
							<?= !empty($group['help']) ? ' <a class="help" href="' . Config::$scripturl . '?action=helpadmin;help=' . $group['help'] . '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="' . Lang::getTxt('help', file: 'General') . '"></span></a> ' : '<img class="icon" src="' . Theme::$current->settings['images_url'] . '/blank.png" alt="' . Lang::getTxt('help', file: 'General') . '">' ?><span><?= $group['name'] ?></span><?php if (!empty($group['children'])): ?>
							<br>
							<span class="smalltext"><?= Lang::getTxt('permissions_includes_inherited', ['list' => Lang::sentenceList(array_map(fn($grp) => '"' . $grp . '"', $group['children']))], file: 'ManagePermissions') ?></span><?php endif; ?>
						</td>
						<td><?= $group['can_search'] ? $group['link'] : ($group['num_members'] ?? Lang::getTxt('not_applicable', file: 'General')) ?></td><?php if (empty(Config::$modSettings['permission_enable_deny'])): ?>
						<td><?= $group['num_permissions']['allowed'] ?></td><?php else: ?>
						<td <?= $group['id'] == 1 ? ' style="font-style: italic;"' : '' ?>>
							<?= $group['num_permissions']['allowed'] ?>
						</td>
						<td <?= $group['id'] == 1 || $group['id'] == -1 ? ' style="font-style: italic;"' : (!empty($group['num_permissions']['denied']) ? ' class="red"' : '') ?>>
							<?= $group['num_permissions']['denied'] ?>
						</td><?php endif; ?>
						<td>
							<?= $group['allow_modify'] ? '<a href="' . Config::$scripturl . '?action=admin;area=permissions;sa=modify;group=' . $group['id'] . (empty(Utils::$context['profile']) ? '' : ';pid=' . Utils::$context['profile']['id']) . '">' . Lang::getTxt(Utils::$context['can_modify'] ? 'permissions_modify' : 'permissions_view', file: 'ManagePermissions') . '</a>' : '' ?>
						</td>
						<td class="centercol">
							<?= $group['allow_modify'] && Utils::$context['can_modify'] ? '<input type="checkbox" name="group[]" value="' . $group['id'] . '">' : '' ?>
						</td>
					</tr><?php endforeach; ?>
				</tbody>
			</table>
			<br><?php /* Advanced stuff... */ ?><?php if (Utils::$context['can_modify']): ?>
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="#" id="permissions_panel_link"><?= Lang::getTxt('permissions_advanced_options', file: 'ManagePermissions') ?></a>
				</h3>
				<span id="permissions_panel_toggle" class="<?= empty(Utils::$context['show_advanced_options']) ? 'toggle_down' : 'toggle_up' ?>" style="display: none;"></span>
			</div>
			<div id="permissions_panel_advanced" class="windowbg">
				<fieldset>
					<legend><?= Lang::getTxt('permissions_with_selection', file: 'ManagePermissions') ?></legend>
					<dl class="settings">
						<dt>
							<a class="help" href="<?= Config::$scripturl ?>?action=helpadmin;help=permissions_quickgroups" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
							<?= Lang::getTxt('permissions_apply_pre_defined', file: 'ManagePermissions') ?>
						</dt>
						<dd>
							<select name="predefined">
								<option value="">(<?= Lang::getTxt('permissions_select_pre_defined', file: 'ManagePermissions') ?>)</option>
								<option value="restrict"><?= Lang::getTxt('permitgroups_restrict', file: 'Admin') ?></option>
								<option value="standard"><?= Lang::getTxt('permitgroups_standard', file: 'Admin') ?></option>
								<option value="moderator"><?= Lang::getTxt('permitgroups_moderator', file: 'Admin') ?></option>
								<option value="maintenance"><?= Lang::getTxt('permitgroups_maintenance', file: 'Admin') ?></option>
							</select>
						</dd>
						<dt>
							<?= Lang::getTxt('permissions_like_group', file: 'ManagePermissions') ?>
						</dt>
						<dd>
							<select name="copy_from">
								<option value="empty">(<?= Lang::getTxt('permissions_select_membergroup', file: 'ManagePermissions') ?>)</option><?php foreach (Utils::$context['groups'] as $group): ?><?php if ($group['id'] != 1): ?>
								<option value="<?= $group['id'] ?>"><?= $group['name'] ?></option><?php endif; ?><?php endforeach; ?>
							</select>
						</dd>
						<dt>
							<select name="add_remove">
								<option value="add"><?= Lang::getTxt('permissions_add', file: 'ManagePermissions') ?></option>
								<option value="clear"><?= Lang::getTxt('permissions_remove', file: 'ManagePermissions') ?></option><?php if (!empty(Config::$modSettings['permission_enable_deny'])): ?>
								<option value="deny"><?= Lang::getTxt('permissions_deny', file: 'ManagePermissions') ?></option><?php endif; ?>
							</select>
						</dt>
						<dd style="overflow:auto;">
							<select name="permissions">
								<option value="">(<?= Lang::getTxt('permissions_select_permission', file: 'ManagePermissions') ?>)</option><?php foreach (Utils::$context['permissions'] as $permissionType): ?><?php if (($permissionType['id'] == 'global') == (!empty(Utils::$context['profile']))): ?><?php continue; ?><?php endif; ?><?php foreach ($permissionType['columns'] as $column): ?><?php foreach ($column as $permissionGroup): ?><?php if ($permissionGroup['hidden']): ?><?php continue; ?><?php endif; ?>
								<option value="" disabled>[<?= $permissionGroup['name'] ?>]</option><?php foreach ($permissionGroup['permissions'] as $perm): ?><?php if ($perm['hidden']): ?><?php continue; ?><?php endif; ?><?php if ($perm['has_own_any']): ?>
								<option value="<?= $permissionType['id'] ?>/<?= $perm['own']['id'] ?>">&nbsp;&nbsp;&nbsp;<?= $perm['name'] ?> (<?= $perm['own']['name'] ?>)</option>
								<option value="<?= $permissionType['id'] ?>/<?= $perm['any']['id'] ?>">&nbsp;&nbsp;&nbsp;<?= $perm['name'] ?> (<?= $perm['any']['name'] ?>)</option><?php else: ?>
								<option value="<?= $permissionType['id'] ?>/<?= $perm['id'] ?>">&nbsp;&nbsp;&nbsp;<?= $perm['name'] ?></option><?php endif; ?><?php endforeach; ?><?php endforeach; ?><?php endforeach; ?><?php endforeach; ?>
							</select>
						</dd>
					</dl>
				</fieldset>
				<input type="submit" value="<?= Lang::getTxt('permissions_set_permissions', file: 'ManagePermissions') ?>" onclick="return checkSubmit();" class="button">
			</div><!-- #permissions_panel_advanced --><?php /* Javascript for the advanced stuff. */ ?>
			<script>
				var oPermissionsPanelToggle = new smc_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: <?= empty(Utils::$context['show_advanced_options']) ? 'true' : 'false' ?>,
					aSwappableContainers: [
						'permissions_panel_advanced'
					],
					aSwapImages: [
						{
							sId: 'permissions_panel_toggle',
							altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
							altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

						}
					],
					aSwapLinks: [
						{
							sId: 'permissions_panel_link',
							msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('permissions_advanced_options', file: 'ManagePermissions')) ?>,
							msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('permissions_advanced_options', file: 'ManagePermissions')) ?>

						}
					],
					oThemeOptions: {
						bUseThemeSettings: true,
						sOptionName: 'admin_preferences',
						sSessionVar: smf_session_var,
						sSessionId: smf_session_id,
						sThemeId: '1',
						sAdditionalVars: ';admin_key=app'
					}
				});

				function checkSubmit()
				{
					if ((document.forms.permissionForm.predefined.value != "" && (document.forms.permissionForm.copy_from.value != "empty" || document.forms.permissionForm.permissions.value != "")) || (document.forms.permissionForm.copy_from.value != "empty" && document.forms.permissionForm.permissions.value != ""))
					{
						alert("<?= Lang::getTxt('permissions_only_one_option', file: 'ManagePermissions') ?>");
						return false;
					}
					if (document.forms.permissionForm.predefined.value == "" && document.forms.permissionForm.copy_from.value == "" && document.forms.permissionForm.permissions.value == "")
					{
						alert("<?= Lang::getTxt('permissions_no_action', file: 'ManagePermissions') ?>");
						return false;
					}
					if (document.forms.permissionForm.permissions.value != "" && document.forms.permissionForm.add_remove.value == "deny")
						return confirm("<?= Lang::getTxt('permissions_deny_dangerous', file: 'ManagePermissions') ?>");

					return true;
				}
			</script><?php if (!empty(Utils::$context['profile'])): ?>
			<input type="hidden" name="pid" value="<?= Utils::$context['profile']['id'] ?>"><?php endif; ?>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-mpq_token_var'] ?>" value="<?= Utils::$context['admin-mpq_token'] ?>"><?php endif; ?>
		</form>
	</div><!-- #admin_form_wrapper -->