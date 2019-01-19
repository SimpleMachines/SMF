<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * The main manage permissions page
 */
function template_permission_index()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Not allowed to edit?
	if (!$context['can_modify'])
		echo '
	<div class="errorbox">
		', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
	</div>';

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=quick" method="post" accept-charset="', $context['character_set'], '" name="permissionForm" id="permissionForm">';

	if (!empty($context['profile']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_for_profile'], ': &quot;', $context['profile']['name'], '&quot;</h3>
			</div>';
	else
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_title'], '</h3>
			</div>';

	echo '
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th>', $txt['membergroups_name'], '</th>
						<th class="small_table">', $txt['membergroups_members_top'], '</th>';

	if (empty($modSettings['permission_enable_deny']))
		echo '
						<th class="small_table">', $txt['membergroups_permissions'], '</th>';
	else
		echo '
						<th class="small_table">', $txt['permissions_allowed'], '</th>
						<th class="small_table">', $txt['permissions_denied'], '</th>';

	echo '
						<th class="small_table">', $context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view'], '</th>
						<th class="table_icon centercol">
							', $context['can_modify'] ? '<input type="checkbox" onclick="invertAll(this, this.form, \'group\');">' : '', '
						</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['groups'] as $group)
	{
		echo '
					<tr class="windowbg">
						<td>
							', !empty($group['help']) ? ' <a class="help" href="' . $scripturl . '?action=helpadmin;help=' . $group['help'] . '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="' . $txt['help'] . '"></span></a> ' : '<img class="icon" src="' . $settings['images_url'] . '/blank.png" alt="' . $txt['help'] . '">', '<span>', $group['name'], '</span>';

		if (!empty($group['children']))
			echo '
							<br>
							<span class="smalltext">', $txt['permissions_includes_inherited'], ': &quot;', implode('&quot;, &quot;', $group['children']), '&quot;</span>';

		echo '
						</td>
						<td>', $group['can_search'] ? $group['link'] : $group['num_members'], '</td>';

		if (empty($modSettings['permission_enable_deny']))
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
							', $group['allow_modify'] ? '<a href="' . $scripturl . '?action=admin;area=permissions;sa=modify;group=' . $group['id'] . (empty($context['profile']) ? '' : ';pid=' . $context['profile']['id']) . '">' . ($context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view']) . '</a>' : '', '
						</td>
						<td class="centercol">
							', $group['allow_modify'] && $context['can_modify'] ? '<input type="checkbox" name="group[]" value="' . $group['id'] . '">' : '', '
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<br>';

	// Advanced stuff...
	if ($context['can_modify'])
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<span id="permissions_panel_toggle" class="', empty($context['show_advanced_options']) ? 'toggle_down' : 'toggle_up', ' floatright" style="display: none;"></span>
					<a href="#" id="permissions_panel_link">', $txt['permissions_advanced_options'], '</a>
				</h3>
			</div>
			<div id="permissions_panel_advanced" class="windowbg">
				<fieldset>
					<legend>', $txt['permissions_with_selection'], '</legend>
					<dl class="settings">
						<dt>
							<a class="help" href="', $scripturl, '?action=helpadmin;help=permissions_quickgroups" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="', $txt['help'], '"></span></a>
							', $txt['permissions_apply_pre_defined'], ':
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
			if ($group['id'] != 1)
				echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

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
				<input type="submit" value="', $txt['permissions_set_permissions'], '" onclick="return checkSubmit();" class="button">
			</div><!-- #permissions_panel_advanced -->';

		// Javascript for the advanced stuff.
		echo '
			<script>
				var oPermissionsPanelToggle = new smc_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: ', empty($context['show_advanced_options']) ? 'true' : 'false', ',
					aSwappableContainers: [
						\'permissions_panel_advanced\'
					],
					aSwapImages: [
						{
							sId: \'permissions_panel_toggle\',
							altExpanded: ', JavaScriptEscape($txt['hide']), ',
							altCollapsed: ', JavaScriptEscape($txt['show']), '
						}
					],
					aSwapLinks: [
						{
							sId: \'permissions_panel_link\',
							msgExpanded: ', JavaScriptEscape($txt['permissions_advanced_options']), ',
							msgCollapsed: ', JavaScriptEscape($txt['permissions_advanced_options']), '
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
			</script>';

		if (!empty($context['profile']))
			echo '
			<input type="hidden" name="pid" value="', $context['profile']['id'], '">';

		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mpq_token_var'], '" value="', $context['admin-mpq_token'], '">';
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
	global $context, $scripturl, $txt;

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=permissions;sa=board" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_boards'], '</h3>
			</div>
			<div class="information">
				', $txt['permissions_boards_desc'], '
			</div>

			<div class="cat_bar">
				<h3 id="board_permissions" class="catbg flow_hidden">
					<span class="perm_name floatleft">', $txt['board_name'], '</span>
					<span class="perm_profile floatleft">', $txt['permission_profile'], '</span>';
	echo '
				</h3>
			</div>
			<div class="windowbg">';

	foreach ($context['categories'] as $category)
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
							<a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], ';rid=permissions;', $context['session_var'], '=', $context['session_id'], '">', str_repeat('-', $board['child_level']), ' ', $board['name'], '</a>
						</span>
						<span class="perm_boardprofile floatleft">';

			if ($context['edit_all'])
			{
				echo '
							<select name="boardprofile[', $board['id'], ']">';

				foreach ($context['profiles'] as $id => $profile)
					echo '
								<option value="', $id, '"', $id == $board['profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

				echo '
							</select>';
			}
			else
				echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $board['profile'], ';', $context['session_var'], '=', $context['session_id'], '">', $board['profile_name'], '</a>';

			echo '
						</span>
					</li>';
		}

		if (!empty($category['boards']))
			echo '
				</ul>';
	}

	if ($context['edit_all'])
		echo '
				<input type="submit" name="save_changes" value="', $txt['save'], '" class="button">';
	else
		echo '
				<a class="button" href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '">', $txt['permissions_board_all'], '</a>';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mpb_token_var'], '" value="', $context['admin-mpb_token'], '">
			</div><!-- .windowbg -->
		</form>';
}

/**
 * Edit permission profiles (predefined).
 */
function template_edit_profiles()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_profile_edit'], '</h3>
			</div>

			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th>', $txt['permissions_profile_name'], '</th>
						<th>', $txt['permissions_profile_used_by'], '</th>
						<th class="table_icon"', !empty($context['show_rename_boxes']) ? ' style="display:none"' : '', '>', $txt['delete'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['profiles'] as $profile)
	{
		echo '
					<tr class="windowbg">
						<td>';

		if (!empty($context['show_rename_boxes']) && $profile['can_edit'])
			echo '
							<input type="text" name="rename_profile[', $profile['id'], ']" value="', $profile['name'], '">';
		else
			echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $profile['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $profile['name'], '</a>';

		echo '
						</td>
						<td>
							', !empty($profile['boards_text']) ? $profile['boards_text'] : $txt['permissions_profile_used_by_none'], '
						</td>
						<td', !empty($context['show_rename_boxes']) ? ' style="display:none"' : '', '>
							<input type="checkbox" name="delete_profile[]" value="', $profile['id'], '" ', $profile['can_delete'] ? '' : 'disabled', '>
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="flow_auto righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mpp_token_var'], '" value="', $context['admin-mpp_token'], '">';

	if ($context['can_edit_something'])
		echo '
				<input type="submit" name="rename" value="', empty($context['show_rename_boxes']) ? $txt['permissions_profile_rename'] : $txt['permissions_commit'], '" class="button">';

	echo '
				<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" class="button" ', !empty($context['show_rename_boxes']) ? ' style="display:none"' : '', '>
			</div>
		</form>
		<br>
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_profile_new'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', $txt['permissions_profile_name'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="profile_name" value="">
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
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mpp_token_var'], '" value="', $context['admin-mpp_token'], '">
				<input type="submit" name="create" value="', $txt['permissions_profile_new_create'], '" class="button">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #admin_form_wrapper -->';
}

/**
 * Modify a group's permissions
 */
function template_modify_group()
{
	global $context, $scripturl, $txt, $modSettings;

	// Cannot be edited?
	if (!$context['profile']['can_modify'])
		echo '
	<div class="errorbox">
		', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
	</div>';
	else
		echo '
	<script>
		window.smf_usedDeny = false;

		function warnAboutDeny()
		{
			if (window.smf_usedDeny)
				return confirm("', $txt['permissions_deny_dangerous'], '");
			else
				return true;
		}
	</script>';

	echo '
		<form id="permissions" action="', $scripturl, '?action=admin;area=permissions;sa=modify2;group=', $context['group']['id'], ';pid=', $context['profile']['id'], '" method="post" accept-charset="', $context['character_set'], '" name="permissionForm" onsubmit="return warnAboutDeny();">';

	if (!empty($modSettings['permission_enable_deny']) && $context['group']['id'] != -1)
		echo '
			<div class="noticebox">
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
			</div>';

	// Draw out the main bits.
	template_modify_group_display($context['permission_type']);

	// If this is general permissions also show the default profile.
	if ($context['permission_type'] == 'membergroup')
	{
		echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['permissions_board'], '</h3>
			</div>
			<div class="information">
				', $txt['permissions_board_desc'], '
			</div>';

		template_modify_group_display('board');
	}

	if ($context['profile']['can_modify'])
		echo '
			<div class="padding">
				<input type="submit" value="', $txt['permissions_commit'], '" class="button">
			</div>';

	foreach ($context['hidden_perms'] as $hidden_perm)
		echo '
			<input type="hidden" name="perm[', $hidden_perm[0], '][', $hidden_perm[1], ']" value="', $hidden_perm[2], '">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mp_token_var'], '" value="', $context['admin-mp_token'], '">
		</form>';
}

/**
 * The way of looking at permissions.
 *
 * @param string $type The permissions type
 */
function template_modify_group_display($type)
{
	global $context, $scripturl, $txt, $modSettings;

	$permission_type = &$context['permissions'][$type];
	$disable_field = $context['profile']['can_modify'] ? '' : 'disabled ';

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
							<th', $context['group']['id'] == -1 ? ' colspan="2"' : '', ' class="smalltext">', $permissionGroup['name'], '</th>';

					if ($context['group']['id'] != -1)
						echo '
							<th>', $txt['permissions_option_own'], '</th>
							<th>', $txt['permissions_option_any'], '</th>';

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
								', $permission['show_help'] ? '<a href="' . $scripturl . '?action=helpadmin;help=permissionhelp_' . $permission['id'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="' . $txt['help'] . '"></span></a>' : '', '
							</td>
							<td class="lefttext full_width">
								', $permission['name'], (!empty($permission['note']) ? '<br>
								<strong class="smalltext">' . $permission['note'] . '</strong>' : ''), '
							</td>
							<td>';

					if ($permission['has_own_any'])
					{
						// Guests can't do their own thing.
						if ($context['group']['id'] != -1)
						{
							if (empty($modSettings['permission_enable_deny']))
								echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" id="', $permission['own']['id'], '_on" ', $disable_field, '>';
							else
							{
								echo '
								<select name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']" ', $disable_field, '>';

								foreach (array('on', 'off', 'deny') as $c)
									echo '
									<option ', $permission['own']['select'] == $c ? ' selected' : '', ' value="', $c, '">', $txt['permissions_option_' . $c], '</option>';
								echo '
								</select>';
							}

							echo '
							</td>
							<td>';
						}

						if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '>';
						else
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c)
								echo '
									<option ', $permission['any']['select'] == $c ? ' selected' : '', ' value="', $c, '">', $txt['permissions_option_' . $c], '</option>';
							echo '
								</select>';
						}
					}
					else
					{
						if ($context['group']['id'] != -1)
							echo '
							</td>
							<td>';

						if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '>';
						else
						{
							echo '
								<select name="perm[', $permission_type['id'], '][', $permission['id'], ']" ', $disable_field, '>';

							foreach (array('on', 'off', 'deny') as $c)
								echo '
									<option ', $permission['select'] == $c ? ' selected' : '', ' value="', $c, '">', $txt['permissions_option_' . $c], '</option>';
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
	global $context, $txt, $modSettings;

	// This looks really weird, but it keeps things nested properly...
	echo '
											<fieldset id="', $context['current_permission'], '">
												<legend><a href="javascript:void(0);" onclick="document.getElementById(\'', $context['current_permission'], '\').style.display = \'none\';document.getElementById(\'', $context['current_permission'], '_groups_link\').style.display = \'block\'; return false;" class="toggle_up"> ', $txt['avatar_select_permission'], '</a></legend>';

	if (empty($modSettings['permission_enable_deny']))
		echo '
												<ul>';
	else
		echo '
												<div class="information">', $txt['permissions_option_desc'], '</div>
												<dl class="settings">
													<dt>
														<span class="perms"><strong>', $txt['permissions_option_on'], '</strong></span>
														<span class="perms"><strong>', $txt['permissions_option_off'], '</strong></span>
														<span class="perms red"><strong>', $txt['permissions_option_deny'], '</strong></span>
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
														<input type="checkbox" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', '>';
		else
			echo '
														<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', '></span>
														<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="off"', $group['status'] == 'off' ? ' checked' : '', '></span>
														<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="deny"', $group['status'] == 'deny' ? ' checked' : '', '></span>';

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
													<li>
														<input type="checkbox" onclick="invertAll(this, this.form, \'' . $context['current_permission'] . '[\');">
														<span>', $txt['check_all'], '</span>
													</li>
												</ul>';
	else
		echo '
												</dl>';

	echo '
											</fieldset>

											<a href="javascript:void(0);" onclick="document.getElementById(\'', $context['current_permission'], '\').style.display = \'block\'; document.getElementById(\'', $context['current_permission'], '_groups_link\').style.display = \'none\'; return false;" id="', $context['current_permission'], '_groups_link" style="display: none;" class="toggle_down"> ', $txt['avatar_select_permission'], '</a>

											<script>
												document.getElementById("', $context['current_permission'], '").style.display = "none";
												document.getElementById("', $context['current_permission'], '_groups_link").style.display = "";
											</script>';
}

/**
 * Edit post moderation permissions.
 */
function template_postmod_permissions()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
					<div id="admin_form_wrapper">
						<form action="', $scripturl, '?action=admin;area=permissions;sa=postmod;', $context['session_var'], '=', $context['session_id'], '" method="post" name="postmodForm" id="postmodForm" accept-charset="', $context['character_set'], '">
							<div class="cat_bar">
								<h3 class="catbg">', $txt['permissions_post_moderation'], '</h3>
							</div>';

	// First, we have the bit where we can enable or disable this bad boy.
	echo '
							<div class="windowbg">
								<dl class="settings">
									<dt>', $txt['permissions_post_moderation_enable'], '</dt>
									<dd><input type="checkbox" name="postmod_active"', !empty($modSettings['postmod_active']) ? ' checked' : '', '></dd>
								</dl>
							</div>';

	// If we're not active, there's a bunch of stuff we don't need to show.
	if (!empty($modSettings['postmod_active']))
	{
		// Got advanced permissions - if so warn!
		if (!empty($modSettings['permission_enable_deny']))
			echo '
							<div class="information">', $txt['permissions_post_moderation_deny_note'], '</div>';

		echo '
							<div class="padding">
								<ul class="floatleft smalltext block">
									<strong>', $txt['permissions_post_moderation_legend'], ':</strong>
									<li><span class="main_icons post_moderation_allow"></span>', $txt['permissions_post_moderation_allow'], '</li>
									<li><span class="main_icons post_moderation_moderate"></span>', $txt['permissions_post_moderation_moderate'], '</li>
									<li><span class="main_icons post_moderation_deny"></span>', $txt['permissions_post_moderation_disallow'], '</li>
								</ul>
								<p class="righttext floatright block">
									<br><br><br>
									', $txt['permissions_post_moderation_select'], ':
									<select name="pid" onchange="document.forms.postmodForm.submit();">';

		foreach ($context['profiles'] as $profile)
			if ($profile['can_modify'])
				echo '
										<option value="', $profile['id'], '"', $profile['id'] == $context['current_profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

		echo '
									</select>
									<input type="submit" value="', $txt['go'], '" class="button">
								</p>
							</div><!-- .padding -->
							<table class="table_grid" id="postmod">
								<thead>
									<tr class="title_bar">
										<th></th>
										<th class="centercol" colspan="3">
											', $txt['permissions_post_moderation_new_topics'], '
										</th>
										<th class="centercol" colspan="3">
											', $txt['permissions_post_moderation_replies_own'], '
										</th>
										<th class="centercol" colspan="3">
											', $txt['permissions_post_moderation_replies_any'], '
										</th>';

		if ($modSettings['attachmentEnable'] == 1)
			echo '
										<th class="centercol" colspan="3">
											', $txt['permissions_post_moderation_attachments'], '
										</th>';

		echo '
									</tr>
									<tr class="windowbg">
										<th class="quarter_table">
											', $txt['permissions_post_moderation_group'], '
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

		if ($modSettings['attachmentEnable'] == 1)
			echo '
										<th><span class="main_icons post_moderation_allow"></span></th>
										<th><span class="main_icons post_moderation_moderate"></span></th>
										<th><span class="main_icons post_moderation_deny"></span></th>';

		echo '
									</tr>
								</thead>
								<tbody>';

		foreach ($context['profile_groups'] as $group)
		{
			echo '
									<tr class="windowbg">
										<td class="half_table">
											<span ', ($group['color'] ? 'style="color: ' . $group['color'] . '"' : ''), '>', $group['name'], '</span>';

			if (!empty($group['children']))
				echo '
											<br>
											<span class="smalltext">', $txt['permissions_includes_inherited'], ': &quot;', implode('&quot;, &quot;', $group['children']), '&quot;</span>';

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

			if ($modSettings['attachmentEnable'] == 1)
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
								<input type="submit" name="save_changes" value="', $txt['permissions_commit'], '" class="button">
								<input type="hidden" name="', $context['admin-mppm_token_var'], '" value="', $context['admin-mppm_token'], '">
						</form>
					</div><!-- #admin_form_wrapper -->';
}

?>