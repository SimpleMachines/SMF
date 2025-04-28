<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * The main manage permissions page
 */
function template_permission_index()
{
	// Not allowed to edit?
	if (!Utils::$context['can_modify'])
		echo '
	<div class="errorbox">
		', Lang::getTxt('permission_cannot_edit', ['url' => Config::$scripturl . '?action=admin;area=permissions;sa=profiles'], file: 'ManagePermissions'), '
	</div>';

	echo '
	<div id="admin_form_wrapper">
		<form action="', Config::$scripturl, '?action=admin;area=permissions;sa=quick" method="post" accept-charset="UTF-8" name="permissionForm" id="permissionForm">';

	if (!empty(Utils::$context['profile']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('permissions_for_profile', Utils::$context['profile'], file: 'ManagePermissions'), '</h3>
			</div>';
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('permissions_title', file: 'ManagePermissions'), '</h3>
			</div>';

	echo '
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th>', Lang::getTxt('membergroups_name', file: 'ManageMembers'), '</th>
						<th class="small_table">', Lang::getTxt('membergroups_members_top', file: 'ManageMembers'), '</th>';

	if (empty(Config::$modSettings['permission_enable_deny']))
		echo '
						<th class="small_table">', Lang::getTxt('membergroups_permissions', file: 'Admin'), '</th>';
	else
		echo '
						<th class="small_table">', Lang::getTxt('permissions_allowed', file: 'ManagePermissions'), '</th>
						<th class="small_table">', Lang::getTxt('permissions_denied', file: 'ManagePermissions'), '</th>';

	echo '
						<th class="small_table">', Lang::getTxt(Utils::$context['can_modify'] ? 'permissions_modify' : 'permissions_view', file: 'ManagePermissions'), '</th>
						<th class="table_icon centercol">
							', Utils::$context['can_modify'] ? '<input type="checkbox" onclick="invertAll(this, this.form, \'group\');">' : '', '
						</th>
					</tr>
				</thead>
				<tbody>';

	foreach (Utils::$context['groups'] as $group)
	{
		echo '
					<tr class="windowbg">
						<td>
							', !empty($group['help']) ? ' <a class="help" href="' . Config::$scripturl . '?action=helpadmin;help=' . $group['help'] . '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="' . Lang::getTxt('help', file: 'General') . '"></span></a> ' : '<img class="icon" src="' . Theme::$current->settings['images_url'] . '/blank.png" alt="' . Lang::getTxt('help', file: 'General') . '">', '<span>', $group['name'], '</span>';

		if (!empty($group['children']))
			echo '
							<br>
							<span class="smalltext">', Lang::getTxt('permissions_includes_inherited', ['list' => Lang::sentenceList(array_map(fn ($grp) => '"' . $grp . '"', $group['children']))], file: 'ManagePermissions'), '</span>';

		echo '
						</td>
						<td>', $group['can_search'] ? $group['link'] : ($group['num_members'] ?? Lang::getTxt('not_applicable', file: 'General')), '</td>';

		if (empty(Config::$modSettings['permission_enable_deny']))
			echo '
						<td>', $group['num_permissions']['allowed'], '</td>';
		else
			echo '
						<td ', $group['id'] == 1 ? ' style="font-style: italic;"' : '', '>
							', $group['num_permissions']['allowed'], '
						</td>
						<td ', $group['id'] == 1 || $group['id'] == -1 ? ' style="font-style: italic;"' : (!empty($group['num_permissions']['denied']) ? ' class="red"' : ''), '>
							', $group['num_permissions']['denied'], '
						</td>';

		echo '
						<td>
							', $group['allow_modify'] ? '<a href="' . Config::$scripturl . '?action=admin;area=permissions;sa=modify;group=' . $group['id'] . (empty(Utils::$context['profile']) ? '' : ';pid=' . Utils::$context['profile']['id']) . '">' . Lang::getTxt(Utils::$context['can_modify'] ? 'permissions_modify' : 'permissions_view', file: 'ManagePermissions') . '</a>' : '', '
						</td>
						<td class="centercol">
							', $group['allow_modify'] && Utils::$context['can_modify'] ? '<input type="checkbox" name="group[]" value="' . $group['id'] . '">' : '', '
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<br>';

	// Advanced stuff...
	if (Utils::$context['can_modify'])
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="#" id="permissions_panel_link">', Lang::getTxt('permissions_advanced_options', file: 'ManagePermissions'), '</a>
				</h3>
				<span id="permissions_panel_toggle" class="', empty(Utils::$context['show_advanced_options']) ? 'toggle_down' : 'toggle_up', '" style="display: none;"></span>
			</div>
			<div id="permissions_panel_advanced" class="windowbg">
				<fieldset>
					<legend>', Lang::getTxt('permissions_with_selection', file: 'ManagePermissions'), '</legend>
					<dl class="settings">
						<dt>
							<a class="help" href="', Config::$scripturl, '?action=helpadmin;help=permissions_quickgroups" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="', Lang::getTxt('help', file: 'General'), '"></span></a>
							', Lang::getTxt('permissions_apply_pre_defined', file: 'ManagePermissions'), '
						</dt>
						<dd>
							<select name="predefined">
								<option value="">(', Lang::getTxt('permissions_select_pre_defined', file: 'ManagePermissions'), ')</option>
								<option value="restrict">', Lang::getTxt('permitgroups_restrict', file: 'Admin'), '</option>
								<option value="standard">', Lang::getTxt('permitgroups_standard', file: 'Admin'), '</option>
								<option value="moderator">', Lang::getTxt('permitgroups_moderator', file: 'Admin'), '</option>
								<option value="maintenance">', Lang::getTxt('permitgroups_maintenance', file: 'Admin'), '</option>
							</select>
						</dd>
						<dt>
							', Lang::getTxt('permissions_like_group', file: 'ManagePermissions'), '
						</dt>
						<dd>
							<select name="copy_from">
								<option value="empty">(', Lang::getTxt('permissions_select_membergroup', file: 'ManagePermissions'), ')</option>';

		foreach (Utils::$context['groups'] as $group)
			if ($group['id'] != 1)
				echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
						</dd>
						<dt>
							<select name="add_remove">
								<option value="add">', Lang::getTxt('permissions_add', file: 'ManagePermissions'), '</option>
								<option value="clear">', Lang::getTxt('permissions_remove', file: 'ManagePermissions'), '</option>';

		if (!empty(Config::$modSettings['permission_enable_deny']))
			echo '
								<option value="deny">', Lang::getTxt('permissions_deny', file: 'ManagePermissions'), '</option>';

		echo '
							</select>
						</dt>
						<dd style="overflow:auto;">
							<select name="permissions">
								<option value="">(', Lang::getTxt('permissions_select_permission', file: 'ManagePermissions'), ')</option>';

		foreach (Utils::$context['permissions'] as $permissionType)
		{
			if (($permissionType['id'] == 'global') == (!empty(Utils::$context['profile'])))
				continue;

			foreach ($permissionType['columns'] as $column)
			{
				foreach ($column as $permissionGroup)
				{
					if ($permissionGroup['hidden'])
						continue;

					echo '
								<option value="" disabled>[', $permissionGroup['name'], ']</option>';

					foreach ($permissionGroup['permissions'] as $perm)
					{
						if ($perm['hidden'])
							continue;

						if ($perm['has_own_any'])
							echo '
								<option value="', $permissionType['id'], '/', $perm['own']['id'], '">&nbsp;&nbsp;&nbsp;', $perm['name'], ' (', $perm['own']['name'], ')</option>
								<option value="', $permissionType['id'], '/', $perm['any']['id'], '">&nbsp;&nbsp;&nbsp;', $perm['name'], ' (', $perm['any']['name'], ')</option>';
						else
							echo '
								<option value="', $permissionType['id'], '/', $perm['id'], '">&nbsp;&nbsp;&nbsp;', $perm['name'], '</option>';
					}
				}
			}
		}
		echo '
							</select>
						</dd>
					</dl>
				</fieldset>
				<input type="submit" value="', Lang::getTxt('permissions_set_permissions', file: 'ManagePermissions'), '" onclick="return checkSubmit();" class="button">
			</div><!-- #permissions_panel_advanced -->';

		// Javascript for the advanced stuff.
		echo '
			<script>
				var oPermissionsPanelToggle = new smc_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: ', empty(Utils::$context['show_advanced_options']) ? 'true' : 'false', ',
					aSwappableContainers: [
						\'permissions_panel_advanced\'
					],
					aSwapImages: [
						{
							sId: \'permissions_panel_toggle\',
							altExpanded: ', Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')), ',
							altCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')), '
						}
					],
					aSwapLinks: [
						{
							sId: \'permissions_panel_link\',
							msgExpanded: ', Utils::escapeJavaScript(Lang::getTxt('permissions_advanced_options', file: 'ManagePermissions')), ',
							msgCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('permissions_advanced_options', file: 'ManagePermissions')), '
						}
					],
					oThemeOptions: {
						bUseThemeSettings: true,
						sOptionName: \'admin_preferences\',
						sSessionVar: smf_session_var,
						sSessionId: smf_session_id,
						sThemeId: \'1\',
						sAdditionalVars: \';admin_key=app\'
					}
				});

				function checkSubmit()
				{
					if ((document.forms.permissionForm.predefined.value != "" && (document.forms.permissionForm.copy_from.value != "empty" || document.forms.permissionForm.permissions.value != "")) || (document.forms.permissionForm.copy_from.value != "empty" && document.forms.permissionForm.permissions.value != ""))
					{
						alert("', Lang::getTxt('permissions_only_one_option', file: 'ManagePermissions'), '");
						return false;
					}
					if (document.forms.permissionForm.predefined.value == "" && document.forms.permissionForm.copy_from.value == "" && document.forms.permissionForm.permissions.value == "")
					{
						alert("', Lang::getTxt('permissions_no_action', file: 'ManagePermissions'), '");
						return false;
					}
					if (document.forms.permissionForm.permissions.value != "" && document.forms.permissionForm.add_remove.value == "deny")
						return confirm("', Lang::getTxt('permissions_deny_dangerous', file: 'ManagePermissions'), '");

					return true;
				}
			</script>';

		if (!empty(Utils::$context['profile']))
			echo '
			<input type="hidden" name="pid" value="', Utils::$context['profile']['id'], '">';

		echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-mpq_token_var'], '" value="', Utils::$context['admin-mpq_token'], '">';
	}

	echo '
		</form>
	</div><!-- #admin_form_wrapper -->';
}

/**
 * THe page that shows which permissions profile applies to each board
 */
function template_by_board()
{
	echo '
		<form id="admin_form_wrapper" action="', Config::$scripturl, '?action=admin;area=permissions;sa=board" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('permissions_boards', file: 'Admin'), '</h3>
			</div>
			<div class="information">
				', Lang::getTxt('permissions_boards_desc', file: 'ManagePermissions'), '
			</div>

			<div class="cat_bar">
				<h3 id="board_permissions" class="catbg flow_hidden">
					<span class="perm_name floatleft">', Lang::getTxt('board_name', file: 'General'), '</span>
					<span class="perm_profile floatleft">', Lang::getTxt('permission_profile', file: 'ManagePermissions'), '</span>';
	echo '
				</h3>
			</div>
			<div class="windowbg">';

	foreach (Utils::$context['categories'] as $category)
	{
		echo '
				<div class="sub_bar">
					<h3 class="subbg">', $category['name'], '</h3>
				</div>';

		if (!empty($category['boards']))
			echo '
				<ul class="perm_boards flow_hidden">';

		foreach ($category['boards'] as $board)
		{
			echo '
					<li class="flow_hidden">
						<span class="perm_board floatleft">
							<a href="', Config::$scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], ';rid=permissions;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', str_repeat('-', $board['child_level']), ' ', $board['name'], '</a>
						</span>
						<span class="perm_boardprofile floatleft">';

			if (Utils::$context['edit_all'])
			{
				echo '
							<select name="boardprofile[', $board['id'], ']">';

				foreach (Utils::$context['profiles'] as $id => $profile)
					echo '
								<option value="', $id, '"', $id == $board['profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

				echo '
							</select>';
			}
			else
				echo '
							<a href="', Config::$scripturl, '?action=admin;area=permissions;sa=index;pid=', $board['profile'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', $board['profile_name'], '</a>';

			echo '
						</span>
					</li>';
		}

		if (!empty($category['boards']))
			echo '
				</ul>';
	}

	if (Utils::$context['edit_all'])
		echo '
				<input type="submit" name="save_changes" value="', Lang::getTxt('save', file: 'General'), '" class="button">';
	else
		echo '
				<a class="button" href="', Config::$scripturl, '?action=admin;area=permissions;sa=board;edit;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', Lang::getTxt('permissions_board_all', file: 'ManagePermissions'), '</a>';

	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-mpb_token_var'], '" value="', Utils::$context['admin-mpb_token'], '">
			</div><!-- .windowbg -->
		</form>';
}

/**
 * Edit permission profiles (predefined).
 */
function template_edit_profiles()
{
	echo '
	<div id="admin_form_wrapper">
		<form action="', Config::$scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('permissions_profile_edit', file: 'ManagePermissions'), '</h3>
			</div>

			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th>', Lang::getTxt('permissions_profile_name', file: 'ManagePermissions'), '</th>
						<th>', Lang::getTxt('permissions_profile_used_by', file: 'ManagePermissions'), '</th>
						<th class="table_icon"', !empty(Utils::$context['show_rename_boxes']) ? ' style="display:none"' : '', '>', Lang::getTxt('delete', file: 'General'), '</th>
					</tr>
				</thead>
				<tbody>';

	foreach (Utils::$context['profiles'] as $profile)
	{
		echo '
					<tr class="windowbg">
						<td>';

		if (!empty(Utils::$context['show_rename_boxes']) && $profile['can_rename'])
			echo '
							<input type="text" name="rename_profile[', $profile['id'], ']" value="', $profile['name'], '">';
		else
			echo '
							<a href="', Config::$scripturl, '?action=admin;area=permissions;sa=index;pid=', $profile['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '">', $profile['name'], '</a>';

		echo '
						</td>
						<td>
							', !empty($profile['boards_text']) ? $profile['boards_text'] : Lang::getTxt('permissions_profile_used_by_count', [0], file: 'ManagePermissions'), '
						</td>
						<td', !empty(Utils::$context['show_rename_boxes']) ? ' style="display:none"' : '', '>
							<input type="checkbox" name="delete_profile[]" value="', $profile['id'], '" ', $profile['can_delete'] ? '' : 'disabled', '>
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="flow_auto righttext padding">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-mpp_token_var'], '" value="', Utils::$context['admin-mpp_token'], '">';

	if (Utils::$context['can_rename_something'])
		echo '
				<input type="submit" name="rename" value="', Lang::getTxt(empty(Utils::$context['show_rename_boxes']) ? 'permissions_profile_rename' : 'permissions_commit', file: 'ManagePermissions'), '" class="button">';

	echo '
				<input type="submit" name="delete" value="', Lang::getTxt('quickmod_delete_selected', file: 'General'), '" class="button" ', !empty(Utils::$context['show_rename_boxes']) ? ' style="display:none"' : '', '>
			</div>
		</form>
		<br>
		<form action="', Config::$scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('permissions_profile_new', file: 'ManagePermissions'), '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', Lang::getTxt('permissions_profile_name', file: 'ManagePermissions'), '</strong>
					</dt>
					<dd>
						<input type="text" name="profile_name" value="">
					</dd>
					<dt>
						<strong>', Lang::getTxt('permissions_profile_copy_from', file: 'ManagePermissions'), '</strong>
					</dt>
					<dd>
						<select name="copy_from">';

	foreach (Utils::$context['profiles'] as $id => $profile)
		echo '
							<option value="', $id, '">', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
				</dl>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-mpp_token_var'], '" value="', Utils::$context['admin-mpp_token'], '">
				<input type="submit" name="create" value="', Lang::getTxt('permissions_profile_new_create', file: 'ManagePermissions'), '" class="button">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #admin_form_wrapper -->';
}

/**
 * Modify a group's permissions
 */
function template_modify_group()
{
	// Cannot be edited?
	if (!Utils::$context['profile']['can_modify'])
		echo '
	<div class="errorbox">
		', Lang::getTxt('permission_cannot_edit', ['url' => Config::$scripturl . '?action=admin;area=permissions;sa=profiles'], file: 'ManagePermissions'), '
	</div>';
	else
		echo '
	<script>
		window.smf_usedDeny = false;

		function warnAboutDeny()
		{
			if (window.smf_usedDeny)
				return confirm("', Lang::getTxt('permissions_deny_dangerous', file: 'ManagePermissions'), '");
			else
				return true;
		}
	</script>';

	echo '
		<form id="permissions" action="', Config::$scripturl, '?action=admin;area=permissions;sa=modify2;group=', Utils::$context['group']['id'], ';pid=', Utils::$context['profile']['id'], '" method="post" accept-charset="UTF-8" name="permissionForm" onsubmit="return warnAboutDeny();">';

	if (!empty(Config::$modSettings['permission_enable_deny']) && Utils::$context['group']['id'] != -1)
		echo '
			<div class="noticebox">
				', Lang::getTxt('permissions_option_desc', file: 'ManagePermissions'), '
			</div>';

	echo '
			<div class="cat_bar">
				<h3 class="catbg">';

	if (Utils::$context['permission_type'] == 'board')
		echo '
				', Lang::getTxt('permissions_for_in', ['group' => Utils::$context['group']['name'], 'profile' => Utils::$context['profile']['name']], file: 'ManagePermissions');
	else
		echo '
				', Lang::getTxt(Utils::$context['permission_type'] == 'global' ? 'permissions_general_for' : 'permissions_board_for', ['name' => Utils::$context['group']['name']], file: 'ManagePermissions');

	echo '
				</h3>
			</div>';

	// Draw out the main bits.
	template_modify_group_display(Utils::$context['permission_type']);

	if (Utils::$context['profile']['can_modify'])
		echo '
			<div class="padding">
				<input type="submit" value="', Lang::getTxt('permissions_commit', file: 'ManagePermissions'), '" class="button">
			</div>';

	foreach (Utils::$context['hidden_perms'] as $hidden_perm)
		echo '
			<input type="hidden" name="perm[', $hidden_perm[0], '][', $hidden_perm[1], ']" value="', $hidden_perm[2], '">';

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-mp_token_var'], '" value="', Utils::$context['admin-mp_token'], '">
		</form>';
}

/**
 * The way of looking at permissions.
 *
 * @param string $type The permissions type
 */
function template_modify_group_display($type)
{
	$permission_type = &Utils::$context['permissions'][$type];
	$disable_field = Utils::$context['profile']['can_modify'] ? '' : 'disabled ';

	foreach ($permission_type['columns'] as $column)
	{
		echo '
					<table class="table_grid half_content">';

		foreach ($column as $permissionGroup)
		{
			if (empty($permissionGroup['permissions']))
				continue;

			// Are we likely to have something in this group to display or is it all hidden?
			$has_display_content = false;
			if (!$permissionGroup['hidden'])
			{
				// Before we go any further check we are going to have some data to print otherwise we just have a silly heading.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$has_display_content = true;

				if ($has_display_content)
				{
					echo '
						<tr class="title_bar">
							<th></th>
							<th', Utils::$context['group']['id'] == -1 ? ' colspan="2"' : '', ' class="smalltext">', $permissionGroup['name'], '</th>';

					if (Utils::$context['group']['id'] != -1)
						echo '
							<th>', Lang::getTxt('permissions_option_own', file: 'ManagePermissions'), '</th>
							<th>', Lang::getTxt('permissions_option_any', file: 'ManagePermissions'), '</th>';

					echo '
						</tr>';
				}
			}

			foreach ($permissionGroup['permissions'] as $permission)
			{
				if (!$permission['hidden'] && !$permissionGroup['hidden'])
				{
					echo '
						<tr class="windowbg">
							<td>
								', $permission['show_help'] ? '<a href="' . Config::$scripturl . '?action=helpadmin;help=permissionhelp_' . $permission['id'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="' . Lang::getTxt('help', file: 'General') . '"></span></a>' : '', '
							</td>
							<td class="lefttext full_width">
								', $permission['name'], (!empty($permission['note']) ? '<br>
								<strong class="smalltext">' . $permission['note'] . '</strong>' : ''), '
							</td>
							<td>';

					if ($permission['has_own_any'])
					{
						// Guests can't do their own thing.
						if (Utils::$context['group']['id'] != -1)
						{
							if (empty(Config::$modSettings['permission_enable_deny']))
								echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" id="', $permission['own']['id'], '_on" ', $disable_field, '>';
							else
							{
								echo '
								<select name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']" ', $disable_field, '>';

								foreach (array('on', 'off', 'deny') as $c) {
									echo '
									<option ', $permission['own']['select'] == $c ? ' selected' : '', ' value="', $c, '">', Lang::getTxt('permissions_option_' . $c, file: 'ManagePermissions'), '</option>';
								}

								echo '
								</select>';
							}

							echo '
							</td>
							<td>';
						}

						if (empty(Config::$modSettings['permission_enable_deny']) || Utils::$context['group']['id'] == -1)
							echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '>';
						else
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c) {
								echo '
									<option ', $permission['any']['select'] == $c ? ' selected' : '', ' value="', $c, '">', Lang::getTxt('permissions_option_' . $c, file: 'ManagePermissions'), '</option>';
							}

							echo '
								</select>';
						}
					}
					else
					{
						if (Utils::$context['group']['id'] != -1)
							echo '
							</td>
							<td>';

						if (empty(Config::$modSettings['permission_enable_deny']) || Utils::$context['group']['id'] == -1)
							echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '>';
						else
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c) {
								echo '
									<option ', $permission['select'] == $c ? ' selected' : '', ' value="', $c, '">', Lang::getTxt('permissions_option_' . $c, file: 'ManagePermissions'), '</option>';
							}

							echo '
								</select>';
						}
					}
					echo '
							</td>
						</tr>';
				}
			}
		}

		echo '
					</table>';
	}

	echo '
					<br class="clear">';
}

/**
 * A form for displaying inline permissions, such as on a settings page.
 */
function template_inline_permissions()
{
	// This looks really weird, but it keeps things nested properly...
	echo '
											<fieldset id="', Utils::$context['current_permission'], '">
												<legend><a href="javascript:void(0);" onclick="document.getElementById(\'', Utils::$context['current_permission'], '\').style.display = \'none\';document.getElementById(\'', Utils::$context['current_permission'], '_groups_link\').style.display = \'block\'; return false;" class="toggle_up"> ', Lang::getTxt('avatar_select_permission', file: 'Admin'), '</a></legend>';

	if (empty(Config::$modSettings['permission_enable_deny']))
		echo '
												<ul>';
	else
		echo '
												<div class="information">', Lang::getTxt('permissions_option_desc', file: 'ManagePermissions'), '</div>
												<dl class="settings">
													<dt>
														<span class="perms"><strong>', Lang::getTxt('permissions_option_on', file: 'ManagePermissions'), '</strong></span>
														<span class="perms"><strong>', Lang::getTxt('permissions_option_off', file: 'ManagePermissions'), '</strong></span>
														<span class="perms red"><strong>', Lang::getTxt('permissions_option_deny', file: 'ManagePermissions'), '</strong></span>
													</dt>
													<dd>
													</dd>';

	foreach (Utils::$context['member_groups'] as $group)
	{
		if (!empty(Config::$modSettings['permission_enable_deny']))
			echo '
													<dt>';
		else
			echo '
													<li>';

		if (empty(Config::$modSettings['permission_enable_deny']))
			echo '
														<input type="checkbox" name="', Utils::$context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', '>';
		else
			echo '
														<span class="perms"><input type="radio" name="', Utils::$context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', '></span>
														<span class="perms"><input type="radio" name="', Utils::$context['current_permission'], '[', $group['id'], ']" value="off"', $group['status'] == 'off' ? ' checked' : '', '></span>
														<span class="perms"><input type="radio" name="', Utils::$context['current_permission'], '[', $group['id'], ']" value="deny"', $group['status'] == 'deny' ? ' checked' : '', '></span>';

		if (!empty(Config::$modSettings['permission_enable_deny']))
			echo '
													</dt>
													<dd>
														<span', $group['is_postgroup'] ? ' style="font-style: italic;"' : '', '>', $group['name'], '</span>
													</dd>';
		else
			echo '
														<span', $group['is_postgroup'] ? ' style="font-style: italic;"' : '', '>', $group['name'], '</span>
													</li>';
	}

	if (empty(Config::$modSettings['permission_enable_deny']))
		echo '
													<li>
														<input type="checkbox" onclick="invertAll(this, this.form, \'' . Utils::$context['current_permission'] . '[\');">
														<span>', Lang::getTxt('check_all', file: 'General'), '</span>
													</li>
												</ul>';
	else
		echo '
												</dl>';

	echo '
											</fieldset>

											<a href="javascript:void(0);" onclick="document.getElementById(\'', Utils::$context['current_permission'], '\').style.display = \'block\'; document.getElementById(\'', Utils::$context['current_permission'], '_groups_link\').style.display = \'none\'; return false;" id="', Utils::$context['current_permission'], '_groups_link" style="display: none;" class="toggle_down"> ', Lang::getTxt('avatar_select_permission', file: 'Admin'), '</a>

											<script>
												document.getElementById("', Utils::$context['current_permission'], '").style.display = "none";
												document.getElementById("', Utils::$context['current_permission'], '_groups_link").style.display = "";
											</script>';
}

/**
 * Edit post moderation permissions.
 */
function template_postmod_permissions()
{
	echo '
					<div id="admin_form_wrapper">
						<form action="', Config::$scripturl, '?action=admin;area=permissions;sa=postmod;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" name="postmodForm" id="postmodForm" accept-charset="UTF-8">
							<div class="cat_bar">
								<h3 class="catbg">', Lang::getTxt('permissions_post_moderation', file: 'Admin'), '</h3>
							</div>';

	// First, we have the bit where we can enable or disable this bad boy.
	echo '
							<div class="windowbg">
								<dl class="settings">
									<dt>', Lang::getTxt('permissions_post_moderation_enable', file: 'ManagePermissions'), '</dt>
									<dd><input type="checkbox" name="postmod_active"', !empty(Config::$modSettings['postmod_active']) ? ' checked' : '', '></dd>
								</dl>
							</div>';

	// If we're not active, there's a bunch of stuff we don't need to show.
	if (!empty(Config::$modSettings['postmod_active']))
	{
		// Got advanced permissions - if so warn!
		if (!empty(Config::$modSettings['permission_enable_deny']))
			echo '
							<div class="information">', Lang::getTxt('permissions_post_moderation_deny_note', file: 'ManagePermissions'), '</div>';

		echo '
							<div class="padding">
								<strong>', Lang::getTxt('permissions_post_moderation_legend', file: 'ManagePermissions'), '</strong>
								<ul class="floatleft smalltext block">
									<li><span class="main_icons post_moderation_allow"></span>', Lang::getTxt('permissions_post_moderation_allow', file: 'ManagePermissions'), '</li>
									<li><span class="main_icons post_moderation_moderate"></span>', Lang::getTxt('permissions_post_moderation_moderate', file: 'ManagePermissions'), '</li>
									<li><span class="main_icons post_moderation_deny"></span>', Lang::getTxt('permissions_post_moderation_disallow', file: 'ManagePermissions'), '</li>
								</ul>
								<br><br><br>
								<p class="righttext floatright block">
									', Lang::getTxt('permissions_post_moderation_select', file: 'ManagePermissions'), '
									<select name="pid" onchange="document.forms.postmodForm.submit();">';

		foreach (Utils::$context['profiles'] as $profile)
			if ($profile['can_modify'])
				echo '
										<option value="', $profile['id'], '"', $profile['id'] == Utils::$context['current_profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

		echo '
									</select>
									<input type="submit" value="', Lang::getTxt('go', file: 'General'), '" class="button">
								</p>
							</div><!-- .padding -->
							<table class="table_grid" id="postmod">
								<thead>
									<tr class="title_bar">
										<th></th>
										<th class="centercol" colspan="3">
											', Lang::getTxt('permissions_post_moderation_new_topics', file: 'ManagePermissions'), '
										</th>
										<th class="centercol" colspan="3">
											', Lang::getTxt('permissions_post_moderation_replies_own', file: 'ManagePermissions'), '
										</th>
										<th class="centercol" colspan="3">
											', Lang::getTxt('permissions_post_moderation_replies_any', file: 'ManagePermissions'), '
										</th>';

		if (Config::$modSettings['attachmentEnable'] == 1)
			echo '
										<th class="centercol" colspan="3">
											', Lang::getTxt('permissions_post_moderation_attachments', file: 'ManagePermissions'), '
										</th>';

		echo '
									</tr>
									<tr class="windowbg">
										<th class="quarter_table">
											', Lang::getTxt('permissions_post_moderation_group', file: 'ManagePermissions'), '
										</th>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>';

		if (Config::$modSettings['attachmentEnable'] == 1)
			echo '
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>';

		echo '
									</tr>
								</thead>
								<tbody>';

		foreach (Utils::$context['profile_groups'] as $group)
		{
			echo '
									<tr class="windowbg">
										<td class="half_table">
											<span ', ($group['color'] ? 'style="color: ' . $group['color'] . '"' : ''), '>', $group['name'], '</span>';

			if (!empty($group['children']))
				echo '
											<br>
											<span class="smalltext">', Lang::getTxt('permissions_includes_inherited', ['list' => Lang::sentenceList(array_map(fn ($grp) => '"' . $grp . '"', $group['children']))], file: 'ManagePermissions'), '</span>';

			echo '
										</td>
										<td class="centercol">
											<input type="radio" name="new_topic[', $group['id'], ']" value="allow"', $group['new_topic'] == 'allow' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="new_topic[', $group['id'], ']" value="moderate"', $group['new_topic'] == 'moderate' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="new_topic[', $group['id'], ']" value="disallow"', $group['new_topic'] == 'disallow' ? ' checked' : '', '>
										</td>';

			// Guests can't have "own" permissions
			if ($group['id'] == '-1')
				echo '
										<td colspan="3"></td>';
			else
				echo '
										<td class="centercol">
											<input type="radio" name="replies_own[', $group['id'], ']" value="allow"', $group['replies_own'] == 'allow' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_own[', $group['id'], ']" value="moderate"', $group['replies_own'] == 'moderate' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_own[', $group['id'], ']" value="disallow"', $group['replies_own'] == 'disallow' ? ' checked' : '', '>
										</td>';

			echo '
										<td class="centercol">
											<input type="radio" name="replies_any[', $group['id'], ']" value="allow"', $group['replies_any'] == 'allow' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_any[', $group['id'], ']" value="moderate"', $group['replies_any'] == 'moderate' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="replies_any[', $group['id'], ']" value="disallow"', $group['replies_any'] == 'disallow' ? ' checked' : '', '>
										</td>';

			if (Config::$modSettings['attachmentEnable'] == 1)
				echo '
										<td class="centercol">
											<input type="radio" name="attachment[', $group['id'], ']" value="allow"', $group['attachment'] == 'allow' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="attachment[', $group['id'], ']" value="moderate"', $group['attachment'] == 'moderate' ? ' checked' : '', '>
										</td>
										<td class="centercol">
											<input type="radio" name="attachment[', $group['id'], ']" value="disallow"', $group['attachment'] == 'disallow' ? ' checked' : '', '>
										</td>';

			echo '
									</tr>';
		}

		echo '
								</tbody>
							</table>';
	}

	echo '
								<input type="submit" name="save_changes" value="', Lang::getTxt('permissions_commit', file: 'ManagePermissions'), '" class="button">
								<input type="hidden" name="', Utils::$context['admin-mppm_token_var'], '" value="', Utils::$context['admin-mppm_token'], '">
						</form>
					</div><!-- #admin_form_wrapper -->';
}
