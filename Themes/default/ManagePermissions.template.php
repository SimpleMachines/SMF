<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

function template_permission_index()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Not allowed to edit?
	if (!$context['can_modify'])
		echo '
	<div class="errorbox">
		', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
	</div>';

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=quick" method="post" accept-charset="', $context['character_set'], '" name="permissionForm" id="permissionForm">';

		if (!empty($context['profile']))
			echo '
			<div class="title_bar">
				<h3 class="titlebg">', $txt['permissions_for_profile'], ': &quot;', $context['profile']['name'], '&quot;</h3>
			</div>';

		echo '
			<table width="100%" class="table_grid">
				<thead>
					<tr class="catbg">
						<th class="first_th">', $txt['membergroups_name'], '</th>
						<th width="10%" align="center" valign="middle">', $txt['membergroups_members_top'], '</th>';

			if (empty($modSettings['permission_enable_deny']))
				echo '
						<th width="16%" align="center">', $txt['membergroups_permissions'], '</th>';
			else
				echo '
						<th width="8%" align="center">', $txt['permissions_allowed'], '</th>
						<th width="8%" align="center">', $txt['permissions_denied'], '</th>';

			echo '
						<th width="10%" align="center" valign="middle">', $context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view'], '</th>
						<th class="last_th" width="4%" align="center" valign="middle">
							', $context['can_modify'] ? '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form, \'group\');" />' : '', '
						</th>
					</tr>
				</thead>
				<tbody>';

	$alternate = false;
	foreach ($context['groups'] as $group)
	{
		$alternate = !$alternate;
		echo '
					<tr class="windowbg', $alternate ? '2' : '', '">
						<td>
							', $group['name'], $group['id'] == -1 ? ' (<a href="' . $scripturl . '?action=helpadmin;help=membergroup_guests" onclick="return reqWin(this.href);">?</a>)' : ($group['id'] == 0 ? ' (<a href="' . $scripturl . '?action=helpadmin;help=membergroup_regular_members" onclick="return reqWin(this.href);">?</a>)' : ($group['id'] == 1 ? ' (<a href="' . $scripturl . '?action=helpadmin;help=membergroup_administrator" onclick="return reqWin(this.href);">?</a>)' : ($group['id'] == 3 ? ' (<a href="' . $scripturl . '?action=helpadmin;help=membergroup_moderator" onclick="return reqWin(this.href);">?</a>)' : '')));

		if (!empty($group['children']))
			echo '
							<br /><span class="smalltext">', $txt['permissions_includes_inherited'], ': &quot;', implode('&quot;, &quot;', $group['children']), '&quot;</span>';

		echo '
						</td>
						<td align="center">', $group['can_search'] ? $group['link'] : $group['num_members'], '</td>';

		if (empty($modSettings['permission_enable_deny']))
			echo '
						<td width="16%" align="center">', $group['num_permissions']['allowed'], '</td>';
		else
			echo '
						<td width="8%" align="center"', $group['id'] == 1 ? ' style="font-style: italic;"' : '', '>', $group['num_permissions']['allowed'], '</td>
						<td width="8%" align="center"', $group['id'] == 1 || $group['id'] == -1 ? ' style="font-style: italic;"' : (!empty($group['num_permissions']['denied']) ? ' style="color: red;"' : ''), '>', $group['num_permissions']['denied'], '</td>';

		echo '
						<td align="center">', $group['allow_modify'] ? '<a href="' . $scripturl . '?action=admin;area=permissions;sa=modify;group=' . $group['id'] . (empty($context['profile']) ? '' : ';pid=' . $context['profile']['id']) . '">' . ($context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view']). '</a>' : '', '</td>
						<td align="center">', $group['allow_modify'] && $context['can_modify'] ? '<input type="checkbox" name="group[]" value="' . $group['id'] . '" class="input_check" />' : '', '</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<br />';

	// Advanced stuff...
	if ($context['can_modify'])
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="ie6_header floatleft">
						<img src="', $settings['images_url'], '/', empty($context['show_advanced_options']) ? 'selected' : 'sort_down', '.gif" id="permissions_panel_toggle" alt="*" /> ', $txt['permissions_advanced_options'], '
					</span>
				</h3>
			</div>
			<div id="permissions_panel_advanced" class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<fieldset>
						<legend>', $txt['permissions_with_selection'], '</legend>
						<dl class="settings admin_permissions">
							<dt>
								', $txt['permissions_apply_pre_defined'], ' <a href="', $scripturl, '?action=helpadmin;help=permissions_quickgroups" onclick="return reqWin(this.href);">(?)</a>:
							</dt>
							<dd>
								<select name="predefined">
									<option value="">(', $txt['permissions_select_pre_defined'], ')</option>
									<option value="restrict">', $txt['permitgroups_restrict'], '</option>
									<option value="standard">', $txt['permitgroups_standard'], '</option>
									<option value="moderator">', $txt['permitgroups_moderator'], '</option>
									<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
								</select>
							</dd>
							<dt>
								', $txt['permissions_like_group'], ':
							</dt>
							<dd>
								<select name="copy_from">
									<option value="empty">(', $txt['permissions_select_membergroup'], ')</option>';
		foreach ($context['groups'] as $group)
		{
			if ($group['id'] != 1)
				echo '
									<option value="', $group['id'], '">', $group['name'], '</option>';
		}

		echo '
								</select>
							</dd>
							<dt>
								<select name="add_remove">
									<option value="add">', $txt['permissions_add'], '...</option>
									<option value="clear">', $txt['permissions_remove'], '...</option>';
		if (!empty($modSettings['permission_enable_deny']))
			echo '
									<option value="deny">', $txt['permissions_deny'], '...</option>';
		echo '
								</select>
							</dt>
							<dd style="overflow:auto;">
								<select name="permissions">
									<option value="">(', $txt['permissions_select_permission'], ')</option>';
		foreach ($context['permissions'] as $permissionType)
		{
			if ($permissionType['id'] == 'membergroup' && !empty($context['profile']))
				continue;

			foreach ($permissionType['columns'] as $column)
			{
				foreach ($column as $permissionGroup)
				{
					if ($permissionGroup['hidden'])
						continue;

					echo '
									<option value="" disabled="disabled">[', $permissionGroup['name'], ']</option>';
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
					<div class="righttext">
						<input type="submit" value="', $txt['permissions_set_permissions'], '" onclick="return checkSubmit();" class="button_submit" />
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>';

		// Javascript for the advanced stuff.
		echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var oPermissionsPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($context['show_advanced_options']) ? 'true' : 'false', ',
			aSwappableContainers: [
				\'permissions_panel_advanced\'
			],
			aSwapImages: [
				{
					sId: \'permissions_panel_toggle\',
					srcExpanded: smf_images_url + \'/sort_down.gif\',
					altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
					srcCollapsed: smf_images_url + \'/selected.gif\',
					altCollapsed: ', JavaScriptEscape($txt['upshrink_description']), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
				sOptionName: \'admin_preferences\',
				sSessionVar: ', JavaScriptEscape($context['session_var']), ',
				sSessionId: ', JavaScriptEscape($context['session_id']), ',
				sThemeId: \'1\',
				sAdditionalVars: \';admin_key=app\'
			}
		});';

		echo '

		function checkSubmit()
		{
			if ((document.forms.permissionForm.predefined.value != "" && (document.forms.permissionForm.copy_from.value != "empty" || document.forms.permissionForm.permissions.value != "")) || (document.forms.permissionForm.copy_from.value != "empty" && document.forms.permissionForm.permissions.value != ""))
			{
				alert("', $txt['permissions_only_one_option'], '");
				return false;
			}
			if (document.forms.permissionForm.predefined.value == "" && document.forms.permissionForm.copy_from.value == "" && document.forms.permissionForm.permissions.value == "")
			{
				alert("', $txt['permissions_no_action'], '");
				return false;
			}
			if (document.forms.permissionForm.permissions.value != "" && document.forms.permissionForm.add_remove.value == "deny")
				return confirm("', $txt['permissions_deny_dangerous'], '");

			return true;
		}
	// ]]></script>';

		if (!empty($context['profile']))
			echo '
			<input type="hidden" name="pid" value="', $context['profile']['id'], '" />';

		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';
	}
	else
		echo '
			</table>';

	echo '
		</form>
	</div>
	<br class="clear" />';
}

function template_by_board()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<form id="admincenter" action="', $scripturl, '?action=admin;area=permissions;sa=board" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['permissions_boards'], '</h3>
		</div>
		<div class="information">
			', $txt['permissions_boards_desc'], '
		</div>
		<div class="title_bar">
			<h3 id="board_permissions" class="titlebg flow_hidden">
				<span class="perm_name floatleft">', $txt['board_name'], '</span>
				<span class="perm_profile floatleft">', $txt['permission_profile'], '</span>
			</h3>
		</div>';

	if (!$context['edit_all'])
		echo '
		<div class="righttext">
			<a href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '">[', $txt['permissions_board_all'], ']</a>
		</div>';

	foreach ($context['categories'] as $category)
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg"><strong>', $category['name'], '</strong></h3>
		</div>';

		if (!empty($category['boards']))
			echo '
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content">
				<ul class="perm_boards flow_hidden">';

		$alternate = false;

		foreach ($category['boards'] as $board)
		{
			$alternate = !$alternate;

			echo '

					<li class="flow_hidden' ,' windowbg', $alternate ? '' : '2','">
						<span class="perm_board floatleft">
							<a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], ';rid=permissions;', $context['session_var'], '=', $context['session_id'], '">', str_repeat('-', $board['child_level']), ' ', $board['name'], '</a>
						</span>
						<span class="perm_boardprofile floatleft">';
			if ($context['edit_all'])
			{
				echo '
							<select name="boardprofile[', $board['id'], ']">';

				foreach ($context['profiles'] as $id => $profile)
					echo '
								<option value="', $id, '" ', $id == $board['profile'] ? 'selected="selected"' : '', '>', $profile['name'], '</option>';

				echo '
							</select>';
			}
			else
				echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $board['profile'], ';', $context['session_var'], '=', $context['session_id'], '"> [', $board['profile_name'], ']</a>';

			echo '
						</span>
					</li>';
		}

		if (!empty($category['boards']))
			echo '
				</ul>
			</div>
			<span class="botslice"><span></span></span>
		</div>';
	}

	echo '
		<div class="righttext">';

	if ($context['edit_all'])
		echo '
			<input type="submit" name="save_changes" value="', $txt['save'], '" class="button_submit" />';
	else
		echo '
			<a href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '">[', $txt['permissions_board_all'], ']</a>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</div>
	</form>
	<br class="clear" />';
}

// Edit permission profiles (predefined).
function template_edit_profiles()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $txt['permissions_profile_edit'], '</h3>
			</div>

			<table width="100%" class="table_grid">
				<thead>
					<tr class="catbg">
						<th class="first_th">', $txt['permissions_profile_name'], '</th>
						<th>', $txt['permissions_profile_used_by'], '</th>
						<th class="last_th" width="5%">', $txt['delete'], '</th>
					</tr>
				</thead>
				<tbody>';
	$alternate = false;
	foreach ($context['profiles'] as $profile)
	{
		echo '
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
						<td>';

		if (!empty($context['show_rename_boxes']) && $profile['can_edit'])
			echo '
							<input type="text" name="rename_profile[', $profile['id'], ']" value="', $profile['name'], '" class="input_text" />';
		else
			echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $profile['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $profile['name'], '</a>';

		echo '
						</td>
						<td>
							', !empty($profile['boards_text']) ? $profile['boards_text'] : $txt['permissions_profile_used_by_none'], '
						</td>
						<td align="center">
							<input type="checkbox" name="delete_profile[]" value="', $profile['id'], '" ', $profile['can_delete'] ? '' : 'disabled="disabled"', ' class="input_check" />
						</td>
					</tr>';
		$alternate = !$alternate;
	}

	echo '
				</tbody>
			</table>
			<div class="righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	if ($context['can_edit_something'])
		echo '
				<input type="submit" name="rename" value="', empty($context['show_rename_boxes']) ? $txt['permissions_profile_rename'] : $txt['permissions_commit'], '" class="button_submit" />';

	echo '
				<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" class="button_submit" />
			</div>
		</form>
		<br />
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_profile_new'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['permissions_profile_name'], ':</strong>
						</dt>
						<dd>
							<input type="text" name="profile_name" value="" class="input_text" />
						</dd>
						<dt>
							<strong>', $txt['permissions_profile_copy_from'], ':</strong>
						</dt>
						<dd>
							<select name="copy_from">';

	foreach ($context['profiles'] as $id => $profile)
		echo '
								<option value="', $id, '">', $profile['name'], '</option>';

	echo '
							</select>
						</dd>
					</dl>
					<div class="righttext">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="submit" name="create" value="', $txt['permissions_profile_new_create'], '" class="button_submit" />
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

function template_modify_group()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Cannot be edited?
	if (!$context['profile']['can_modify'])
	{
		echo '
		<div class="errorbox">
			', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
		</div>';
	}
	else
	{
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			window.smf_usedDeny = false;

			function warnAboutDeny()
			{
				if (window.smf_usedDeny)
					return confirm("', $txt['permissions_deny_dangerous'], '");
				else
					return true;
			}
		// ]]></script>';
	}

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=modify2;group=', $context['group']['id'], ';pid=', $context['profile']['id'], '" method="post" accept-charset="', $context['character_set'], '" name="permissionForm" id="permissionForm" onsubmit="return warnAboutDeny();">';

	if (!empty($modSettings['permission_enable_deny']) && $context['group']['id'] != -1)
		echo '
			<div class="information">
				', $txt['permissions_option_desc'], '
			</div>';

	echo '
			<div class="cat_bar">
				<h3 class="catbg">';
	if ($context['permission_type'] == 'board')
		echo '
				', $txt['permissions_local_for'], ' &quot;', $context['group']['name'], '&quot; ', $txt['permissions_on'], ' &quot;', $context['profile']['name'], '&quot;';
	else
		echo '
				', $context['permission_type'] == 'membergroup' ? $txt['permissions_general'] : $txt['permissions_board'], ' - &quot;', $context['group']['name'], '&quot;';
	echo '
				</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					', $txt['permissions_change_view'], ': ', ($context['view_type'] == 'simple' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="*" />' : ''), '<a href="', $scripturl, '?action=admin;area=permissions;sa=modify;group=', $context['group']['id'], ($context['permission_type'] == 'board' ? ';pid=' . $context['profile']['id'] : ''), ';view=simple">', $txt['permissions_view_simple'], '</a> |
					', ($context['view_type'] == 'classic' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="*" />' : ''), '<a href="', $scripturl, '?action=admin;area=permissions;sa=modify;group=', $context['group']['id'], ($context['permission_type'] == 'board' ? ';pid=' . $context['profile']['id'] : ''), ';view=classic">', $txt['permissions_view_classic'], '</a>
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<div class="flow_hidden">';

	// Draw out the main bits.
	if ($context['view_type'] == 'simple')
		template_modify_group_simple($context['permission_type']);
	else
		template_modify_group_classic($context['permission_type']);

	// If this is general permissions also show the default profile.
	if ($context['permission_type'] == 'membergroup')
	{
		echo '
			</div>
			<br />
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_board'], '</h3>
			</div>
			<div class="information">
				', $txt['permissions_board_desc'], '
			</div>
			<div class="flow_hidden">';

		if ($context['view_type'] == 'simple')
			template_modify_group_simple('board');
		else
			template_modify_group_classic('board');

		echo '
			</div>';
	}

	if ($context['profile']['can_modify'])
		echo '
			<div class="righttext padding">
				<input type="submit" value="', $txt['permissions_commit'], '" class="button_submit" />
			</div>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';

}

// A javascript enabled clean permissions view.
function template_modify_group_simple($type)
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Simple only has one column so we only need bother ourself with that one.
	$permission_data = &$context['permissions'][$type]['columns'][0];

	// Short cut for disabling fields we can't change.
	$disable_field = $context['profile']['can_modify'] ? '' : 'disabled="disabled" ';

	echo '
			<table width="100%" class="table_grid">
				<thead>
					<tr class="catbg">
						<th colspan="2" width="100%" align="left" class="first_th"></th>';
				if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
					echo '
						<th colspan="3" width="9" class="last_th">&nbsp;</th>';
				else
					echo '
						<th>', $txt['permissions_option_on'], '</th>
						<th>', $txt['permissions_option_off'], '</th>
						<th class="last_th">', $txt['permissions_option_deny'], '</th>';
					echo '
					</tr>
				</thead>
				<tbody>';

	foreach ($permission_data as $id_group => $permissionGroup)
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
					<tr class="windowbg">
						<td colspan="2" width="100%" align="left">
							<a href="#" onclick="return toggleBreakdown(\'', $id_group, '\');">
								<img src="', $settings['images_url'], '/sort_down.gif" id="group_toggle_img_', $id_group, '" alt="*" />&nbsp;<strong>', $permissionGroup['name'], '</strong>
							</a>
						</td>';
				if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
					echo '
						<td colspan="3" width="10">
							<div id="group_select_div_', $id_group, '">
								<input type="checkbox" id="group_select_', $id_group, '" name="group_select_', $id_group, '" class="input_check" onclick="determineGroupState(\'', $id_group, '\', this.checked ? \'on\' : \'off\');" style="display: none;" ', $disable_field, '/>
							</div>
						</td>';
				else
					echo '
						<td align="center">
							<div id="group_select_div_on_', $id_group, '">
								<input type="radio" id="group_select_on_', $id_group, '" name="group_select_', $id_group, '" value="on" onclick="determineGroupState(\'', $id_group, '\', \'on\');" style="display: none;" ', $disable_field, ' class="input_radio" />
							</div>
						</td>
						<td align="center">
							<div id="group_select_div_off_', $id_group, '">
								<input type="radio" id="group_select_off_', $id_group, '" name="group_select_', $id_group, '" value="off" onclick="determineGroupState(\'', $id_group, '\', \'off\');" style="display: none;" ', $disable_field, ' class="input_radio" />
							</div>
						</td>
						<td align="center">
							<div id="group_select_div_deny_', $id_group, '">
								<input type="radio" id="group_select_deny_', $id_group, '" name="group_select_', $id_group, '" value="deny" onclick="determineGroupState(\'', $id_group, '\', \'deny\');" style="display: none;" ', $disable_field, ' class="input_radio" />
							</div>
						</td>';
					echo '
					</tr>';
			}
		}

		$alternate = false;
		foreach ($permissionGroup['permissions'] as $permission)
		{
			// If it's hidden keep the last value.
			if ($permission['hidden'] || $permissionGroup['hidden'])
			{
				echo '
					<tr style="display: none;">
						<td>
							<input type="hidden" name="perm[', $type, '][', $permission['id'], ']" value="', $permission['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['select'], '" />
						</td>
					</tr>';
			}
			else
			{
				echo '
					<tr id="perm_div_', $id_group, '_', $permission['id'], '" class="', $alternate ? 'windowbg' : 'windowbg2', '">
						<td valign="top" width="10" style="padding-right: 1ex;">
							', $permission['help_index'] ? '<a href="' . $scripturl . '?action=helpadmin;help=' . $permission['help_index'] . '" onclick="return reqWin(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.gif" alt="' . $txt['help'] . '" /></a>' : '', '
						</td>
						<td valign="top" width="100%" align="left" style="padding-bottom: 2px;">', $permission['name'], '</td>';

					if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
						echo '
						<td valign="top" style="padding-bottom: 2px;"><input type="checkbox" id="select_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' onclick="determineGroupState(\'', $id_group, '\');" value="on" class="input_check" ', $disable_field, '/></td>';
					else
						echo '
						<td valign="top" width="10" style="padding-bottom: 2px;"><input type="radio" id="select_on_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" onclick="determineGroupState(\'', $id_group, '\');" class="input_radio" ', $disable_field, '/></td>
						<td valign="top" width="10" style="padding-bottom: 2px;"><input type="radio" id="select_off_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'off' ? ' checked="checked"' : '', ' value="off" onclick="determineGroupState(\'', $id_group, '\');" class="input_radio" ', $disable_field, '/></td>
						<td valign="top" width="10" style="padding-bottom: 2px;"><input type="radio" id="select_deny_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'denied' ? ' checked="checked"' : '', ' value="deny" onclick="window.smf_usedDeny = true; determineGroupState(\'', $id_group, '\');" class="input_radio" ', $disable_field, '/></td>';

					echo '
					</tr>';
			}
				$alternate = !$alternate;
		}

		if (!$permissionGroup['hidden'] && $has_display_content)
			echo '
					<tr id="group_hr_div_', $id_group, '" class="windowbg2 perm_groups">
						<td colspan="5" width="100%"></td>
					</tr>';
	}
	echo '
				</tbody>
			</table>
	<script type="text/javascript"><!-- // --><![CDATA[';

	if ($context['profile']['can_modify'] && empty($context['simple_javascript_displayed']))
	{
		// Only show this once.
		$context['simple_javascript_displayed'] = true;

		// Manually toggle the breakdown.
		echo '
	function toggleBreakdown(id_group, forcedisplayType)
	{
		displayType = document.getElementById("group_hr_div_" + id_group).style.display == "none" ? "" : "none";
		if (typeof(forcedisplayType) != "undefined")
			displayType = forcedisplayType;

		for (i = 0; i < groupPermissions[id_group].length; i++)
		{
			document.getElementById("perm_div_" + id_group + "_" + groupPermissions[id_group][i]).style.display = displayType
		}
		document.getElementById("group_hr_div_" + id_group).style.display = displayType
		document.getElementById("group_toggle_img_" + id_group).src = "', $settings['images_url'], '/" + (displayType == "none" ? "selected" : "sort_down") + ".gif";

		return false;
	}';

		// This function decides what to do when ANYTHING is touched!
		echo '
		var groupPermissions = new Array();
		function determineGroupState(id_group, forceState)
		{
			if (typeof(forceState) != "undefined")
				thisState = forceState;

			// Cycle through this groups elements.
			var curState = false, thisState;
			for (var i = 0; i < groupPermissions[id_group].length; i++)
			{';

		if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
			echo '
				if (typeof(forceState) != "undefined")
				{
					document.getElementById(\'select_\' + groupPermissions[id_group][i]).checked = forceState == \'on\' ? 1 : 0;
				}

				thisState = document.getElementById(\'select_\' + groupPermissions[id_group][i]).checked ? \'on\' : \'off\';';
		else
			echo '
				if (typeof(forceState) != "undefined")
				{
					document.getElementById(\'select_on_\' + groupPermissions[id_group][i]).checked = forceState == \'on\' ? 1 : 0;
					document.getElementById(\'select_off_\' + groupPermissions[id_group][i]).checked = forceState == \'off\' ? 1 : 0;
					document.getElementById(\'select_deny_\' + groupPermissions[id_group][i]).checked = forceState == \'deny\' ? 1 : 0;
				}

				if (document.getElementById(\'select_on_\' + groupPermissions[id_group][i]).checked)
					thisState = \'on\';
				else if (document.getElementById(\'select_off_\' + groupPermissions[id_group][i]).checked)
					thisState = \'off\';
				else
					thisState = \'deny\';';

		echo '
				// Unless this is the first element, or it\'s the same state as the last we\'re buggered.
				if (curState == false || thisState == curState)
				{
					curState = thisState;
				}
				else
				{
					curState = \'fudged\';
					i = 999;
				}
			}

			// First check the right master is selected!';
		if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
			echo '
			document.getElementById("group_select_" + id_group).checked = curState == \'on\' ? 1 : 0;';
		else
			echo '
			document.getElementById("group_select_on_" + id_group).checked = curState == \'on\' ? 1 : 0;
			document.getElementById("group_select_off_" + id_group).checked = curState == \'off\' ? 1 : 0;
			document.getElementById("group_select_deny_" + id_group).checked = curState == \'deny\' ? 1 : 0;';

		// Force the display?
		echo '
			if (curState != \'fudged\')
				toggleBreakdown(id_group, "none");';
		echo '
		}';
	}

	// Some more javascript to be displayed as long as we are editing.
	if ($context['profile']['can_modify'])
	{
		foreach ($permission_data as $id_group => $permissionGroup)
		{
			if (empty($permissionGroup['permissions']))
				continue;

			// As before...
			$has_display_content = false;
			if (!$permissionGroup['hidden'])
			{
				// Make sure we can show it.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$has_display_content = true;

				// Make all the group indicators visible on JS only.
				if ($has_display_content)
				{
					if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
						echo '
			document.getElementById("group_select_div_', $id_group, '").parentNode.className = "lockedbg";
			document.getElementById("group_select_', $id_group, '").style.display = "";';
					else
						echo '
			document.getElementById("group_select_div_on_', $id_group, '").parentNode.className = "lockedbg";
			document.getElementById("group_select_div_off_', $id_group, '").parentNode.className = "lockedbg";
			document.getElementById("group_select_div_deny_', $id_group, '").parentNode.className = "lockedbg";
			document.getElementById("group_select_on_', $id_group, '").style.display = "";
			document.getElementById("group_select_off_', $id_group, '").style.display = "";
			document.getElementById("group_select_deny_', $id_group, '").style.display = "";';
				}

				$perm_ids = array();
				$count = 0;
				foreach ($permissionGroup['permissions'] as $permission)
				{
					if (!$permission['hidden'])
					{
						// Need this for knowing what can be tweaked.
						$perm_ids[] = "'$permission[id]'";
					}
				}
				// Declare this groups permissions into an array.
				if (!empty($perm_ids))
					echo '
			groupPermissions[\'', $id_group, '\'] = new Array(', count($perm_ids), ');';
				foreach ($perm_ids as $count => $id)
					echo '
			groupPermissions[\'', $id_group, '\'][', $count, '] = ', $id, ';';

				// Show the group as required.
				if ($has_display_content)
				echo '
			determineGroupState(\'', $id_group, '\');';
			}
		}
	}

	echo '
		// ]]></script>';
}

// The SMF 1.x way of looking at permissions.
function template_modify_group_classic($type)
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	$permission_type = &$context['permissions'][$type];
	$disable_field = $context['profile']['can_modify'] ? '' : 'disabled="disabled" ';

	echo '
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div class="content">';

	foreach ($permission_type['columns'] as $column)
	{
		echo '
						<table width="49%" class="table_grid perm_classic floatleft">';

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
							<tr class="catbg">
								<th colspan="2" width="100%" align="left"><strong class="smalltext">', $permissionGroup['name'], '</strong></th>';
					if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
						echo '
								<th colspan="3" width="10"></th>';
					else
						echo '
								<th align="center"><div>', $txt['permissions_option_on'], '</div></th>
								<th align="center"><div>', $txt['permissions_option_off'], '</div></th>
								<th align="center"><div>', $txt['permissions_option_deny'], '</div></th>';
					echo '
							</tr>';
				}
			}

			$alternate = false;
			foreach ($permissionGroup['permissions'] as $permission)
			{
				// If it's hidden keep the last value.
				if ($permission['hidden'] || $permissionGroup['hidden'])
				{
					echo '
							<tr style="display: none;">
								<td>';

					if ($permission['has_own_any'])
					{
						// Guests can't have own permissions.
						if ($context['group']['id'] != -1)
							echo '
									<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']" value="', $permission['own']['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['own']['select'], '" />';

						echo '
									<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']" value="', $permission['any']['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['any']['select'], '" />';
					}
					else
						echo '
									<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['id'], ']" value="', $permission['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['select'], '" />';
					echo '
								</td>
							</tr>';
				}
				else
				{
					echo '
							<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
								<td width="10">
									', $permission['show_help'] ? '<a href="' . $scripturl . '?action=helpadmin;help=permissionhelp_' . $permission['id'] . '" onclick="return reqWin(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.gif" alt="' . $txt['help'] . '" /></a>' : '', '
								</td>';

					if ($permission['has_own_any'])
					{
						echo '
								<td colspan="4" width="100%" align="left">', $permission['name'], '</td>
							</tr><tr class="', $alternate ? 'windowbg' : 'windowbg2', '">';

						// Guests can't do their own thing.
						if ($context['group']['id'] != -1)
						{
							echo '
								<td></td>
								<td width="100%" class="smalltext" align="right">', $permission['own']['name'], ':</td>';

							if (empty($modSettings['permission_enable_deny']))
								echo '
								<td colspan="3"><input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" id="', $permission['own']['id'], '_on" class="input_check" ', $disable_field, '/></td>';
							else
								echo '
								<td width="10"><input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" id="', $permission['own']['id'], '_on" class="input_radio" ', $disable_field, '/></td>
								<td width="10"><input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'off' ? ' checked="checked"' : '', ' value="off" class="input_radio" ', $disable_field, '/></td>
								<td width="10"><input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'denied' ? ' checked="checked"' : '', ' value="deny" class="input_radio" ', $disable_field, '/></td>';

							echo '
							</tr><tr class="', $alternate ? 'windowbg' : 'windowbg2', '">';
						}

						echo '
								<td></td>
								<td width="100%" class="smalltext" align="right">', $permission['any']['name'], ':</td>';

						if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
								<td colspan="3"><input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" class="input_check" ', $disable_field, '/></td>';
						else
							echo '
								<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" onclick="document.forms.permissionForm.', $permission['own']['id'], '_on.checked = true;" class="input_radio" ', $disable_field, '/></td>
								<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'off' ? ' checked="checked"' : '', ' value="off" class="input_radio" ', $disable_field, '/></td>
								<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select']== 'denied' ? ' checked="checked"' : '', ' value="deny" id="', $permission['any']['id'], '_deny" onclick="window.smf_usedDeny = true;" class="input_radio" ', $disable_field, '/></td>';

						echo '
							</tr>';
					}
					else
					{
						echo '
								<td width="100%" align="left">', $permission['name'], '</td>';

						if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
								<td><input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" class="input_check" ', $disable_field, '/></td>';
						else
							echo '
								<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" class="input_radio" ', $disable_field, '/></td>
								<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'off' ? ' checked="checked"' : '', ' value="off" class="input_radio" ', $disable_field, '/></td>
								<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'denied' ? ' checked="checked"' : '', ' value="deny" onclick="window.smf_usedDeny = true;" class="input_radio" ', $disable_field, '/></td>';

						echo '
							</tr>';
					}
				}
				$alternate = !$alternate;
			}

			if (!$permissionGroup['hidden'] && $has_display_content)
				echo '
							<tr class="windowbg2">
								<td colspan="5" width="100%"><!--separator--></td>
							</tr>';
		}
	echo '
						</table>';
	}
	echo '
				<br class="clear" />
				</div>
				<span class="botslice"><span></span></span>
			</div>';
}

function template_inline_permissions()
{
	global $context, $settings, $options, $txt, $modSettings;

	echo '
		<fieldset id="', $context['current_permission'], '">
			<legend><a href="javascript:void(0);" onclick="document.getElementById(\'', $context['current_permission'], '\').style.display = \'none\';document.getElementById(\'', $context['current_permission'], '_groups_link\').style.display = \'block\'; return false;">', $txt['avatar_select_permission'], '</a></legend>';
	if (empty($modSettings['permission_enable_deny']))
		echo '
			<ul class="permission_groups">';
	else
		echo '
			<div class="information">', $txt['permissions_option_desc'], '</div>
			<dl class="settings">
				<dt>
					<span class="perms"><strong>', $txt['permissions_option_on'], '</strong></span>
					<span class="perms"><strong>', $txt['permissions_option_off'], '</strong></span>
					<span class="perms" style="color: red;"><strong>', $txt['permissions_option_deny'], '</strong></span>
				</dt>
				<dd>
				</dd>';
	foreach ($context['member_groups'] as $group)
	{
		if (!empty($modSettings['permission_enable_deny']))
			echo '
				<dt>';
		else
			echo '
				<li>';

		if (empty($modSettings['permission_enable_deny']))
			echo '
					<input type="checkbox" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' class="input_check" />';
		else
			echo '
					<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' class="input_radio" /></span>
					<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="off"', $group['status'] == 'off' ? ' checked="checked"' : '', ' class="input_radio" /></span>
					<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="deny"', $group['status'] == 'deny' ? ' checked="checked"' : '', ' class="input_radio" /></span>';

		if (!empty($modSettings['permission_enable_deny']))
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

	if (empty($modSettings['permission_enable_deny']))
		echo '
			</ul>';
	else
		echo '
			</dl>';

	echo '
		</fieldset>

		<a href="javascript:void(0);" onclick="document.getElementById(\'', $context['current_permission'], '\').style.display = \'block\'; document.getElementById(\'', $context['current_permission'], '_groups_link\').style.display = \'none\'; return false;" id="', $context['current_permission'], '_groups_link" style="display: none;">[ ', $txt['avatar_select_permission'], ' ]</a>

		<script type="text/javascript"><!-- // --><![CDATA[
			document.getElementById("', $context['current_permission'], '").style.display = "none";
			document.getElementById("', $context['current_permission'], '_groups_link").style.display = "";
		// ]]></script>';
}

// Edit post moderation permissions.
function template_postmod_permissions()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=postmod;', $context['session_var'], '=', $context['session_id'], '" method="post" name="postmodForm" id="postmodForm" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $txt['permissions_post_moderation'], '</h3>
			</div>';

	// Got advanced permissions - if so warn!
	if (!empty($modSettings['permission_enable_deny']))
		echo '
				<div class="information">', $txt['permissions_post_moderation_deny_note'], '</div>';

	echo '
				<div class="righttext padding">
					', $txt['permissions_post_moderation_select'], ':
					<select name="pid" onchange="document.forms.postmodForm.submit();">';

	foreach ($context['profiles'] as $profile)
		if ($profile['can_modify'])
			echo '
						<option value="', $profile['id'], '" ', $profile['id'] == $context['current_profile'] ? 'selected="selected"' : '', '>', $profile['name'], '</option>';

	echo '
					</select>
					<input type="submit" value="', $txt['go'], '" class="button_submit" />
			</div>
			<table width="100%" class="table_grid">
				<thead>
					<tr class="catbg">
						<th class="first_th"></th>
						<th align="center" colspan="3">
							', $txt['permissions_post_moderation_new_topics'], '
						</th>
						<th align="center" colspan="3">
							', $txt['permissions_post_moderation_replies_own'], '
						</th>
						<th align="center" colspan="3">
							', $txt['permissions_post_moderation_replies_any'], '
						</th>
						<th class="last_th" align="center" colspan="3">
							', $txt['permissions_post_moderation_attachments'], '
						</th>
					</tr>
					<tr class="titlebg">
						<th width="30%">
							', $txt['permissions_post_moderation_group'], '
						</th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_allow.gif" alt="', $txt['permissions_post_moderation_allow'], '" title="', $txt['permissions_post_moderation_allow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_moderate.gif" alt="', $txt['permissions_post_moderation_moderate'], '" title="', $txt['permissions_post_moderation_moderate'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_deny.gif" alt="', $txt['permissions_post_moderation_disallow'], '" title="', $txt['permissions_post_moderation_disallow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_allow.gif" alt="', $txt['permissions_post_moderation_allow'], '" title="', $txt['permissions_post_moderation_allow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_moderate.gif" alt="', $txt['permissions_post_moderation_moderate'], '" title="', $txt['permissions_post_moderation_moderate'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_deny.gif" alt="', $txt['permissions_post_moderation_disallow'], '" title="', $txt['permissions_post_moderation_disallow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_allow.gif" alt="', $txt['permissions_post_moderation_allow'], '" title="', $txt['permissions_post_moderation_allow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_moderate.gif" alt="', $txt['permissions_post_moderation_moderate'], '" title="', $txt['permissions_post_moderation_moderate'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_deny.gif" alt="', $txt['permissions_post_moderation_disallow'], '" title="', $txt['permissions_post_moderation_disallow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_allow.gif" alt="', $txt['permissions_post_moderation_allow'], '" title="', $txt['permissions_post_moderation_allow'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_moderate.gif" alt="', $txt['permissions_post_moderation_moderate'], '" title="', $txt['permissions_post_moderation_moderate'], '" /></th>
						<th align="center"><img src="', $settings['default_images_url'], '/admin/post_moderation_deny.gif" alt="', $txt['permissions_post_moderation_disallow'], '" title="', $txt['permissions_post_moderation_disallow'], '" /></th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['profile_groups'] as $group)
	{
		echo '
					<tr>
						<td width="40%" class="windowbg">
							<span ', ($group['color'] ? 'style="color: ' . $group['color'] . '"' : ''), '>', $group['name'], '</span>';
			if (!empty($group['children']))
				echo '
							<br /><span class="smalltext">', $txt['permissions_includes_inherited'], ': &quot;', implode('&quot;, &quot;', $group['children']), '&quot;</span>';

			echo '
						</td>
						<td align="center" class="windowbg2"><input type="radio" name="new_topic[', $group['id'], ']" value="allow" ', $group['new_topic'] == 'allow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg2"><input type="radio" name="new_topic[', $group['id'], ']" value="moderate" ', $group['new_topic'] == 'moderate' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg2"><input type="radio" name="new_topic[', $group['id'], ']" value="disallow" ', $group['new_topic'] == 'disallow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg"><input type="radio" name="replies_own[', $group['id'], ']" value="allow" ', $group['replies_own'] == 'allow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg"><input type="radio" name="replies_own[', $group['id'], ']" value="moderate" ', $group['replies_own'] == 'moderate' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg"><input type="radio" name="replies_own[', $group['id'], ']" value="disallow" ', $group['replies_own'] == 'disallow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg2"><input type="radio" name="replies_any[', $group['id'], ']" value="allow" ', $group['replies_any'] == 'allow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg2"><input type="radio" name="replies_any[', $group['id'], ']" value="moderate" ', $group['replies_any'] == 'moderate' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg2"><input type="radio" name="replies_any[', $group['id'], ']" value="disallow" ', $group['replies_any'] == 'disallow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg"><input type="radio" name="attachment[', $group['id'], ']" value="allow" ', $group['attachment'] == 'allow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg"><input type="radio" name="attachment[', $group['id'], ']" value="moderate" ', $group['attachment'] == 'moderate' ? 'checked="checked"' : '', ' class="input_radio" /></td>
						<td align="center" class="windowbg"><input type="radio" name="attachment[', $group['id'], ']" value="disallow" ', $group['attachment'] == 'disallow' ? 'checked="checked"' : '', ' class="input_radio" /></td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="righttext padding">
				<input type="submit" name="save_changes" value="', $txt['permissions_commit'], '" class="button_submit" />
			</div>
		</form>
		<p class="smalltext" style="padding-left: 10px;">
			<strong>', $txt['permissions_post_moderation_legend'], ':</strong><br />
			<img src="', $settings['default_images_url'], '/admin/post_moderation_allow.gif" alt="', $txt['permissions_post_moderation_allow'], '" /> - ', $txt['permissions_post_moderation_allow'], '<br />
			<img src="', $settings['default_images_url'], '/admin/post_moderation_moderate.gif" alt="', $txt['permissions_post_moderation_moderate'], '" /> - ', $txt['permissions_post_moderation_moderate'], '<br />
			<img src="', $settings['default_images_url'], '/admin/post_moderation_deny.gif" alt="', $txt['permissions_post_moderation_disallow'], '" /> - ', $txt['permissions_post_moderation_disallow'], '
		</p>
	</div>
	<br class="clear" />';
}

?>