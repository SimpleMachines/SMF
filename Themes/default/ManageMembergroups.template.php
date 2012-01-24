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

function template_main()
{
	global $context, $settings, $options, $scripturl, $txt;

	template_show_list('regular_membergroups_list');
	echo '<br /><br />';
	template_show_list('post_count_membergroups_list');

}

function template_new_group()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=membergroups;sa=add" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_new_group'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="group_name_input"><strong>', $txt['membergroups_group_name'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="group_name" id="group_name_input" size="30" class="input_text" />
						</dd>';
	if ($context['undefined_group'])
	{
		echo '
						<dt>
							<label for="group_type"><strong>', $txt['membergroups_edit_group_type'], '</strong>:</label>
						</dt>
						<dd>
							<fieldset id="group_type">
								<legend>', $txt['membergroups_edit_select_group_type'], '</legend>
								<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0" checked="checked" class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_private'], '</label><br />';

		if ($context['allow_protected'])
			echo '
								<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1" class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_protected'], '</label><br />';

		echo '
								<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2" class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_request'], '</label><br />
								<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3" class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_free'], '</label><br />
								<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1" class="input_radio" onclick="swapPostGroup(1);" />', $txt['membergroups_group_type_post'], '</label><br />
							</fieldset>
						</dd>';
	}

	if ($context['post_group'] || $context['undefined_group'])
		echo '
						<dt id="min_posts_text">
							<strong>', $txt['membergroups_min_posts'], ':</strong>
						</dt>
						<dd>
							<input type="text" name="min_posts" id="min_posts_input" size="5" class="input_text" />
						</dd>';
	if (!$context['post_group'] || !empty($modSettings['permission_enable_postgroups']))
	{
		echo '
						<dt>
							<label for="permission_base"><strong>', $txt['membergroups_permissions'], ':</strong></label><br />
							<span class="smalltext">', $txt['membergroups_can_edit_later'], '</span>
						</dt>
						<dd>
							<fieldset id="permission_base">
								<legend>', $txt['membergroups_select_permission_type'], '</legend>
								<input type="radio" name="perm_type" id="perm_type_inherit" value="inherit" checked="checked" class="input_radio" />
								<label for="perm_type_inherit">', $txt['membergroups_new_as_inherit'], ':</label>
								<select name="inheritperm" id="inheritperm_select" onclick="document.getElementById(\'perm_type_inherit\').checked = true;">
									<option value="-1">', $txt['membergroups_guests'], '</option>
									<option value="0" selected="selected">', $txt['membergroups_members'], '</option>';
		foreach ($context['groups'] as $group)
			echo '
									<option value="', $group['id'], '">', $group['name'], '</option>';
		echo '
								</select><br />

								<input type="radio" name="perm_type" id="perm_type_copy" value="copy" class="input_radio" />
								<label for="perm_type_copy">', $txt['membergroups_new_as_copy'], ':</label>
								<select name="copyperm" id="copyperm_select" onclick="document.getElementById(\'perm_type_copy\').checked = true;">
									<option value="-1">', $txt['membergroups_guests'], '</option>
									<option value="0" selected="selected">', $txt['membergroups_members'], '</option>';
		foreach ($context['groups'] as $group)
			echo '
									<option value="', $group['id'], '">', $group['name'], '</option>';
		echo '
								</select><br />

								<input type="radio" name="perm_type" id="perm_type_predefined" value="predefined" class="input_radio" />
								<label for="perm_type_predefined">', $txt['membergroups_new_as_type'], ':</label>
								<select name="level" id="level_select" onclick="document.getElementById(\'perm_type_predefined\').checked = true;">
									<option value="restrict">', $txt['permitgroups_restrict'], '</option>
									<option value="standard" selected="selected">', $txt['permitgroups_standard'], '</option>
									<option value="moderator">', $txt['permitgroups_moderator'], '</option>
									<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
								</select>
							</fieldset>
						</dd>';
	}
	echo '
						<dt>
							<strong>', $txt['membergroups_new_board'], ':</strong>', $context['post_group'] ? '<br />
							<span class="smalltext" style="font-weight: normal">' . $txt['membergroups_new_board_post_groups'] . '</span>' : '', '
						</dt>
						<dd>
							<fieldset id="visible_boards">
								<legend>', $txt['membergroups_new_board_desc'], '</legend>';
	foreach ($context['boards'] as $board)
		echo '
								<div style="margin-left: ', $board['child_level'], 'em;"><input type="checkbox" name="boardaccess[]" id="boardaccess_', $board['id'], '" value="', $board['id'], '" ', $board['selected'] ? ' checked="checked" disabled="disabled"' : '', ' class="input_check" /> <label for="boardaccess_', $board['id'], '">', $board['name'], '</label></div>';

	echo '
								<br />
								<input type="checkbox" id="checkall_check" class="input_check" onclick="invertAll(this, this.form, \'boardaccess\');" /> <label for="checkall_check"><em>', $txt['check_all'], '</em></label>
							</fieldset>
						</dd>
					</dl>
					<div class="righttext">
						<input type="submit" value="', $txt['membergroups_add_group'], '" class="button_submit" />
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>';
	if ($context['undefined_group'])
	{
		echo '
			<script type="text/javascript"><!-- // --><![CDATA[
				function swapPostGroup(isChecked)
				{
					var min_posts_text = document.getElementById(\'min_posts_text\');
					document.getElementById(\'min_posts_input\').disabled = !isChecked;
					min_posts_text.style.color = isChecked ? "" : "#888888";
				}
				swapPostGroup(', $context['post_group'] ? 'true' : 'false', ');
			// ]]></script>';
	}
	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

function template_edit_group()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=membergroups;sa=edit;group=', $context['group']['id'], '" method="post" accept-charset="', $context['character_set'], '" name="groupForm" id="groupForm">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_edit_group'], ' - ', $context['group']['name'], '
				</h3>
			</div>
			<div class="windowbg2">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="group_name_input"><strong>', $txt['membergroups_edit_name'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="group_name" id="group_name_input" value="', $context['group']['editable_name'], '" size="30" class="input_text" />
						</dd>';

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '

						<dt id="group_desc_text">
							<label for="group_desc_input"><strong>', $txt['membergroups_edit_desc'], ':</strong></label>
						</dt>
						<dd>
							<textarea name="group_desc" id="group_desc_input" rows="4" cols="40">', $context['group']['description'], '</textarea>
						</dd>';

	// Group type...
	if ($context['group']['allow_post_group'])
	{
		echo '
						<dt>
							<label for="group_type"><strong>', $txt['membergroups_edit_group_type'], ':</strong></label>
						</dt>
						<dd>
							<fieldset id="group_type">
								<legend>', $txt['membergroups_edit_select_group_type'], '</legend>
								<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0" ', !$context['group']['is_post_group'] && $context['group']['type'] == 0 ? 'checked="checked"' : '', ' class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_private'], '</label><br />';

		if ($context['group']['allow_protected'])
			echo '
								<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1" ', $context['group']['type'] == 1 ? 'checked="checked"' : '', ' class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_protected'], '</label><br />';

		echo '
								<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2" ', $context['group']['type'] == 2 ? 'checked="checked"' : '', ' class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_request'], '</label><br />
								<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3" ', $context['group']['type'] == 3 ? 'checked="checked"' : '', ' class="input_radio" onclick="swapPostGroup(0);" />', $txt['membergroups_group_type_free'], '</label><br />
								<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1" ', $context['group']['is_post_group'] ? 'checked="checked"' : '', ' class="input_radio" onclick="swapPostGroup(1);" />', $txt['membergroups_group_type_post'], '</label><br />
							</fieldset>
						</dd>';
	}

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '
						<dt id="group_moderators_text">
							<label for="group_moderators"><strong>', $txt['moderators'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="group_moderators" id="group_moderators" value="', $context['group']['moderator_list'], '" size="30" class="input_text" />
							<div id="moderator_container"></div>
						</dd>
						<dt id="group_hidden_text">
							<label for="group_hidden_input"><strong>', $txt['membergroups_edit_hidden'], ':</strong></label>
						</dt>
						<dd>
							<select name="group_hidden" id="group_hidden_input" onchange="if (this.value == 2 &amp;&amp; !confirm(\'', $txt['membergroups_edit_hidden_warning'], '\')) this.value = 0;">
								<option value="0" ', $context['group']['hidden'] ? '' : 'selected="selected"', '>', $txt['membergroups_edit_hidden_no'], '</option>
								<option value="1" ', $context['group']['hidden'] == 1 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_hidden_boardindex'], '</option>
								<option value="2" ', $context['group']['hidden'] == 2 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_hidden_all'], '</option>
							</select>
						</dd>';

	// Can they inherit permissions?
	if ($context['group']['id'] > 1 && $context['group']['id'] != 3)
	{
		echo '
						<dt id="group_inherit_text">
							<label for="group_inherit_input"><strong>', $txt['membergroups_edit_inherit_permissions'], '</strong></label>:<br />
							<span class="smalltext">', $txt['membergroups_edit_inherit_permissions_desc'], '</span>
						</dt>
						<dd>
							<select name="group_inherit" id="group_inherit_input">
								<option value="-2">', $txt['membergroups_edit_inherit_permissions_no'], '</option>
								<option value="-1" ', $context['group']['inherited_from'] == -1 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_guests'], '</option>
								<option value="0" ', $context['group']['inherited_from'] == 0 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_members'], '</option>';

		// For all the inheritable groups show an option.
		foreach ($context['inheritable_groups'] as $id => $group)
			echo '
								<option value="', $id, '" ', $context['group']['inherited_from'] == $id ? 'selected="selected"' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $group, '</option>';

		echo '
							</select>
							<input type="hidden" name="old_inherit" value="', $context['group']['inherited_from'], '" />
						</dd>';
	}

	if ($context['group']['allow_post_group'])
		echo '

						<dt id="min_posts_text">
							<label for="min_posts_input"><strong>', $txt['membergroups_min_posts'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="min_posts" id="min_posts_input"', $context['group']['is_post_group'] ? ' value="' . $context['group']['min_posts'] . '"' : '', ' size="6" class="input_text" />
						</dd>';
	echo '
						<dt>
							<label for="online_color_input"><strong>', $txt['membergroups_online_color'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="online_color" id="online_color_input" value="', $context['group']['color'], '" size="20" class="input_text" />
						</dd>
						<dt>
							<label for="star_count_input"><strong>', $txt['membergroups_star_count'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="star_count" id="star_count_input" value="', $context['group']['star_count'], '" size="4" onkeyup="if (this.value.length > 2) this.value = 99;" onkeydown="this.onkeyup();" onchange="if (this.value != 0) this.form.star_image.onchange();" class="input_text" />
						</dd>
						<dt>
							<label for="star_image_input"><strong>', $txt['membergroups_star_image'], ':</strong></label><br />
							<span class="smalltext">', $txt['membergroups_star_image_note'], '</span>
						</dt>
						<dd>
							', $txt['membergroups_images_url'], '
							<input type="text" name="star_image" id="star_image_input" value="', $context['group']['star_image'], '" onchange="if (this.value &amp;&amp; this.form.star_count.value == 0) this.form.star_count.value = 1; else if (!this.value) this.form.star_count.value = 0; document.getElementById(\'star_preview\').src = smf_images_url + \'/\' + (this.value &amp;&amp; this.form.star_count.value > 0 ? this.value.replace(/\$language/g, \'', $context['user']['language'], '\') : \'blank.gif\');" size="20" class="input_text" />
							<img id="star_preview" src="', $settings['images_url'], '/', $context['group']['star_image'] == '' ? 'blank.gif' : $context['group']['star_image'], '" alt="*" />
						</dd>
						<dt>
							<label for="max_messages_input"><strong>', $txt['membergroups_max_messages'], ':</strong></label><br />
							<span class="smalltext">', $txt['membergroups_max_messages_note'], '</span>
						</dt>
						<dd>
							<input type="text" name="max_messages" id="max_messages_input" value="', $context['group']['id'] == 1 ? 0 : $context['group']['max_messages'], '" size="6"', $context['group']['id'] == 1 ? ' disabled="disabled"' : '', ' class="input_text" />
						</dd>';
	if (!empty($context['boards']))
	{
		echo '
						<dt>
							<strong>', $txt['membergroups_new_board'], ':</strong>', $context['group']['is_post_group'] ? '<br />
							<span class="smalltext">' . $txt['membergroups_new_board_post_groups'] . '</span>' : '', '
						</dt>
						<dd>
							<fieldset id="visible_boards" style="width: 95%;">
								<legend><a href="javascript:void(0);" onclick="document.getElementById(\'visible_boards\').style.display = \'none\';document.getElementById(\'visible_boards_link\').style.display = \'block\'; return false;">', $txt['membergroups_new_board_desc'], '</a></legend>';
		foreach ($context['boards'] as $board)
			echo '
								<div style="margin-left: ', $board['child_level'], 'em;"><input type="checkbox" name="boardaccess[]" id="boardaccess_', $board['id'], '" value="', $board['id'], '" ', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> <label for="boardaccess_', $board['id'], '">', $board['name'], '</label></div>';

		echo '
								<br />
								<input type="checkbox" id="checkall_check" class="input_check" onclick="invertAll(this, this.form, \'boardaccess\');" /> <label for="checkall_check"><em>', $txt['check_all'], '</em></label>
							</fieldset>
							<a href="javascript:void(0);" onclick="document.getElementById(\'visible_boards\').style.display = \'block\'; document.getElementById(\'visible_boards_link\').style.display = \'none\'; return false;" id="visible_boards_link" style="display: none;">[ ', $txt['membergroups_select_visible_boards'], ' ]</a>
							<script type="text/javascript"><!-- // --><![CDATA[
								document.getElementById("visible_boards_link").style.display = "";
								document.getElementById("visible_boards").style.display = "none";
							// ]]></script>
						</dd>';
	}
	echo '
					</dl>
					<div class="righttext">
						<input type="submit" name="submit" value="', $txt['membergroups_edit_save'], '" class="button_submit" />', $context['group']['allow_delete'] ? '
						<input type="submit" name="delete" value="' . $txt['membergroups_delete'] . '" onclick="return confirm(\'' . $txt['membergroups_confirm_delete'] . '\');" class="button_submit" />' : '', '
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var oModeratorSuggest = new smc_AutoSuggest({
				sSelf: \'oModeratorSuggest\',
				sSessionId: \'', $context['session_id'], '\',
				sSessionVar: \'', $context['session_var'], '\',
				sSuggestId: \'group_moderators\',
				sControlId: \'group_moderators\',
				sSearchType: \'member\',
				bItemList: true,
				sPostName: \'moderator_list\',
				sURLMask: \'action=profile;u=%item_id%\',
				sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
				sItemListContainerId: \'moderator_container\',
				aListItems: [';

			foreach ($context['group']['moderators'] as $id_member => $member_name)
				echo '
							{
								sItemId: ', JavaScriptEscape($id_member), ',
								sItemName: ', JavaScriptEscape($member_name), '
							}', $id_member == $context['group']['last_moderator_id'] ? '' : ',';

			echo '
				]
			});
		// ]]></script>';

	if ($context['group']['allow_post_group'])
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			function swapPostGroup(isChecked)
			{
				var min_posts_text = document.getElementById(\'min_posts_text\');
				var group_desc_text = document.getElementById(\'group_desc_text\');
				var group_hidden_text = document.getElementById(\'group_hidden_text\');
				var group_moderators_text = document.getElementById(\'group_moderators_text\');
				document.forms.groupForm.min_posts.disabled = !isChecked;
				min_posts_text.style.color = isChecked ? "" : "#888888";
				document.forms.groupForm.group_desc_input.disabled = isChecked;
				group_desc_text.style.color = !isChecked ? "" : "#888888";
				document.forms.groupForm.group_hidden_input.disabled = isChecked;
				group_hidden_text.style.color = !isChecked ? "" : "#888888";
				document.forms.groupForm.group_moderators.disabled = isChecked;
				group_moderators_text.style.color = !isChecked ? "" : "#888888";
			}
			swapPostGroup(', $context['group']['is_post_group'] ? 'true' : 'false', ');
		// ]]></script>';
}

// Templating for viewing the members of a group.
function template_group_members()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : '') , ';sa=members;group=', $context['group']['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['name'], ':</strong>
						</dt>
						<dd>
							<span ', $context['group']['online_color'] ? 'style="color: ' . $context['group']['online_color'] . ';"' : '', '>', $context['group']['name'], '</span> ', $context['group']['stars'], '
						</dd>';
	//Any description to show?
	if (!empty($context['group']['description']))
		echo '
						<dt>
							<strong>' . $txt['membergroups_members_description'] . ':</strong>
						</dt>
						<dd>
							', $context['group']['description'] ,'
						</dd>';

	echo '
						<dt>
							<strong>', $txt['membergroups_members_top'], ':</strong>
						</dt>
						<dd>
							', $context['total_members'] ,'
						</dd>';
	// Any group moderators to show?
	if (!empty($context['group']['moderators']))
	{
		$moderators = array();
		foreach ($context['group']['moderators'] as $moderator)
			$moderators[] = '<a href="' . $scripturl . '?action=profile;u=' . $moderator['id'] . '">' . $moderator['name'] . '</a>';

		echo '
						<dt>
							<strong>', $txt['membergroups_members_group_moderators'], ':</strong>
						</dt>
						<dd>
							', implode(', ', $moderators) ,'
						</dd>';
	}

	echo '
					</dl>
				</div>
				<span class="botslice"><span></span></span>
			</div>

			<br />
			<div class="title_bar">
				<h4 class="titlebg">', $txt['membergroups_members_group_members'], '</h4>
			</div>
			<br />
			<div class="pagesection">', $txt['pages'], ': ', $context['page_index'], '</div>
			<table width="100%" class="table_grid">
				<thead>
					<tr class="catbg">
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=name', $context['sort_by'] == 'name' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['name'], $context['sort_by'] == 'name' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=email', $context['sort_by'] == 'email' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['email'], $context['sort_by'] == 'email' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=active', $context['sort_by'] == 'active' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['membergroups_members_last_active'], $context['sort_by'] == 'active' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=registered', $context['sort_by'] == 'registered' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['date_registered'], $context['sort_by'] == 'registered' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>
						<th', empty($context['group']['assignable']) ? ' colspan="2"' : '', '><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=posts', $context['sort_by'] == 'posts' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['posts'], $context['sort_by'] == 'posts' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '', '</a></th>';
	if (!empty($context['group']['assignable']))
		echo '
						<td width="4%" align="center"><input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" /></td>';
	echo '
					</tr>
				</thead>
				<tbody>';

	if (empty($context['members']))
		echo '
					<tr class="windowbg2">
						<td colspan="6" align="center">', $txt['membergroups_members_no_members'], '</td>
					</tr>';

	foreach ($context['members'] as $member)
	{
		echo '
					<tr class="windowbg2">
						<td>', $member['name'], '</td>
						<td', $member['show_email'] == 'no_through_forum' && $settings['use_image_buttons'] ? ' align="center"' : '', '>';

		// Is it totally hidden?
		if ($member['show_email'] == 'no')
			echo '
							<em>', $txt['hidden'], '</em>';
		// ... otherwise they want it hidden but it's not to this person?
		elseif ($member['show_email'] == 'yes_permission_override')
			echo '
							<a href="mailto:', $member['email'], '"><em>', $member['email'], '</em></a>';
		// ... otherwise it's visible - but only via an image?
		elseif ($member['show_email'] == 'no_through_forum')
			echo '
							<a href="', $scripturl, '?action=emailuser;sa=email;uid=', $member['id'], '" rel="nofollow">', ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . '" />' : $txt['email']), '</a>';
		// ... otherwise it must be a 'yes', show it and show it fully.
		else
			echo '
							<a href="mailto:', $member['email'], '">', $member['email'], '</a>';

		echo '
						</td>
						<td class="windowbg">', $member['last_online'], '</td>
						<td class="windowbg">', $member['registered'], '</td>
						<td', empty($context['group']['assignable']) ? ' colspan="2"' : '', '>', $member['posts'], '</td>';
		if (!empty($context['group']['assignable']))
			echo '
						<td align="center" width="4%"><input type="checkbox" name="rem[]" value="', $member['id'], '" class="input_check" ', ($context['user']['id'] == $member['id'] && $context['group']['id'] == 1 ? 'onclick="if (this.checked) return confirm(\'' . $txt['membergroups_members_deadmin_confirm'] . '\')" ' : ''), '/></td>';
		echo '
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="pagesection flow_hidden">
				<div class="floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>';

	if (!empty($context['group']['assignable']))
		echo '
				<div class="floatright"><input type="submit" name="remove" value="', $txt['membergroups_members_remove'], '" class="button_submit" /></div>';
	echo '
			</div>
			<br />';

	if (!empty($context['group']['assignable']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_members_add_title'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<strong>', $txt['membergroups_members_add_desc'], ':</strong>
					<input type="text" name="toAdd" id="toAdd" value="" class="input_text" />
					<div id="toAddItemContainer"></div>
					<input type="submit" name="add" value="', $txt['membergroups_members_add'], '" class="button_submit" />
				</div>
				<span class="botslice"><span></span></span>
			</div>';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';

	if (!empty($context['group']['assignable']))
		echo '
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var oAddMemberSuggest = new smc_AutoSuggest({
				sSelf: \'oAddMemberSuggest\',
				sSessionId: \'', $context['session_id'], '\',
				sSessionVar: \'', $context['session_var'], '\',
				sSuggestId: \'to_suggest\',
				sControlId: \'toAdd\',
				sSearchType: \'member\',
				sPostName: \'member_add\',
				sURLMask: \'action=profile;u=%item_id%\',
				sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
				bItemList: true,
				sItemListContainerId: \'toAddItemContainer\'
			});
		// ]]></script>';
}

// Allow the moderator to enter a reason to each user being rejected.
function template_group_request_reason()
{
	global $settings, $options, $context, $txt, $scripturl;

	// Show a welcome message to the user.
	echo '
	<div id="moderationcenter">
		<form action="', $scripturl, '?action=groups;sa=requests" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_groups_reason_title'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">';

	// Loop through and print out a reason box for each...
	foreach ($context['group_requests'] as $request)
		echo '
						<dt>
							<strong>', sprintf($txt['mc_groupr_reason_desc'], $request['member_link'], $request['group_link']), ':</strong>
						</dt>
						<dd>
							<input type="hidden" name="groupr[]" value="', $request['id'], '" />
							<textarea name="groupreason[', $request['id'], ']" rows="3" cols="40" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . ';"></textarea>
						</dd>';

	echo '
					</dl>
					<input type="submit" name="go" value="', $txt['mc_groupr_submit'], '" class="button_submit" />
					<input type="hidden" name="req_action" value="got_reason" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

?>