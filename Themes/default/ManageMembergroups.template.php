<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * The main page listing all the groups.
 */
function template_main()
{
	template_show_list('regular_membergroups_list');

	echo '<br><br>';

	template_show_list('post_count_membergroups_list');
}

/**
 * Add a new membergroup.
 */
function template_new_group()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
		<form id="new_group" action="', $scripturl, '?action=admin;area=membergroups;sa=add" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_new_group'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong>', $txt['membergroups_group_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" size="30">
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
							<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0" checked onclick="swapPostGroup(0);">', $txt['membergroups_group_type_private'], '</label><br>';

		if ($context['allow_protected'])
			echo '
							<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1" onclick="swapPostGroup(0);">', $txt['membergroups_group_type_protected'], '</label><br>';

		echo '
							<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2" onclick="swapPostGroup(0);">', $txt['membergroups_group_type_request'], '</label><br>
							<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3" onclick="swapPostGroup(0);">', $txt['membergroups_group_type_free'], '</label><br>
							<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1" onclick="swapPostGroup(1);">', $txt['membergroups_group_type_post'], '</label><br>
						</fieldset>
					</dd>';
	}

	if ($context['post_group'] || $context['undefined_group'])
		echo '
					<dt id="min_posts_text">
						<strong>', $txt['membergroups_min_posts'], ':</strong>
					</dt>
					<dd>
						<input type="number" name="min_posts" id="min_posts_input" size="5">
					</dd>';

	if (!$context['post_group'] || !empty($modSettings['permission_enable_postgroups']))
	{
		echo '
					<dt>
						<label for="permission_base"><strong>', $txt['membergroups_permissions'], ':</strong></label><br>
						<span class="smalltext">', $txt['membergroups_can_edit_later'], '</span>
					</dt>
					<dd>
						<fieldset id="permission_base">
							<legend>', $txt['membergroups_select_permission_type'], '</legend>
							<input type="radio" name="perm_type" id="perm_type_inherit" value="inherit" checked>
							<label for="perm_type_inherit">', $txt['membergroups_new_as_inherit'], ':</label>
							<select name="inheritperm" id="inheritperm_select" onclick="document.getElementById(\'perm_type_inherit\').checked = true;">
								<option value="-1">', $txt['membergroups_guests'], '</option>
								<option value="0" selected>', $txt['membergroups_members'], '</option>';

		foreach ($context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
							<br>
							<input type="radio" name="perm_type" id="perm_type_copy" value="copy">
							<label for="perm_type_copy">', $txt['membergroups_new_as_copy'], ':</label>
							<select name="copyperm" id="copyperm_select" onclick="document.getElementById(\'perm_type_copy\').checked = true;">
								<option value="-1">', $txt['membergroups_guests'], '</option>
								<option value="0" selected>', $txt['membergroups_members'], '</option>';

		foreach ($context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
							<br>
							<input type="radio" name="perm_type" id="perm_type_predefined" value="predefined">
							<label for="perm_type_predefined">', $txt['membergroups_new_as_type'], ':</label>
							<select name="level" id="level_select" onclick="document.getElementById(\'perm_type_predefined\').checked = true;">
								<option value="restrict">', $txt['permitgroups_restrict'], '</option>
								<option value="standard" selected>', $txt['permitgroups_standard'], '</option>
								<option value="moderator">', $txt['permitgroups_moderator'], '</option>
								<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
							</select>
						</fieldset>
					</dd>';
	}

	echo '
					<dt>
						<strong>', $txt['membergroups_new_board'], ':</strong>', $context['post_group'] ? '<br>
						<span class="smalltext">' . $txt['membergroups_new_board_post_groups'] . '</span>' : '', '
					</dt>
					<dd>';

	template_add_edit_group_boards_list(false);

	echo '
					</dd>
				</dl>
				<input type="submit" value="', $txt['membergroups_add_group'], '" class="button">
			</div><!-- .windowbg -->';

	if ($context['undefined_group'])
		echo '
			<script>
				function swapPostGroup(isChecked)
				{
					var min_posts_text = document.getElementById(\'min_posts_text\');
					document.getElementById(\'min_posts_input\').disabled = !isChecked;
					min_posts_text.style.color = isChecked ? "" : "#888888";
				}
				swapPostGroup(', $context['post_group'] ? 'true' : 'false', ');
			</script>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mmg_token_var'], '" value="', $context['admin-mmg_token'], '">
		</form>';
}

/**
 * Edit an existing membergroup.
 */
function template_edit_group()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
		<form action="', $scripturl, '?action=admin;area=membergroups;sa=edit;group=', $context['group']['id'], '" method="post" accept-charset="', $context['character_set'], '" name="groupForm" id="groupForm">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_edit_group'], ' - ', $context['group']['name'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong>', $txt['membergroups_edit_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" value="', $context['group']['editable_name'], '" size="30">
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
							<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0"', !$context['group']['is_post_group'] && $context['group']['type'] == 0 ? ' checked' : '', ' onclick="swapPostGroup(0);">', $txt['membergroups_group_type_private'], '</label><br>';

		if ($context['group']['allow_protected'])
			echo '
							<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1"', $context['group']['type'] == 1 ? ' checked' : '', ' onclick="swapPostGroup(0);">', $txt['membergroups_group_type_protected'], '</label><br>';

		echo '
							<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2"', $context['group']['type'] == 2 ? ' checked' : '', ' onclick="swapPostGroup(0);">', $txt['membergroups_group_type_request'], '</label><br>
							<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3"', $context['group']['type'] == 3 ? ' checked' : '', ' onclick="swapPostGroup(0);">', $txt['membergroups_group_type_free'], '</label><br>
							<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1"', $context['group']['is_post_group'] ? ' checked' : '', ' onclick="swapPostGroup(1);">', $txt['membergroups_group_type_post'], '</label><br>
						</fieldset>
					</dd>';
	}

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '
					<dt id="group_moderators_text">
						<label for="group_moderators"><strong>', $txt['moderators'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_moderators" id="group_moderators" value="', $context['group']['moderator_list'], '" size="30">
						<div id="moderator_container"></div>
					</dd>
					<dt id="group_hidden_text">
						<label for="group_hidden_input"><strong>', $txt['membergroups_edit_hidden'], ':</strong></label>
					</dt>
					<dd>
						<select name="group_hidden" id="group_hidden_input" onchange="if (this.value == 2 &amp;&amp; !confirm(\'', $txt['membergroups_edit_hidden_warning'], '\')) this.value = 0;">
							<option value="0"', $context['group']['hidden'] ? '' : ' selected', '>', $txt['membergroups_edit_hidden_no'], '</option>
							<option value="1"', $context['group']['hidden'] == 1 ? ' selected' : '', '>', $txt['membergroups_edit_hidden_boardindex'], '</option>
							<option value="2"', $context['group']['hidden'] == 2 ? ' selected' : '', '>', $txt['membergroups_edit_hidden_all'], '</option>
						</select>
					</dd>';

	// Can they inherit permissions?
	if ($context['group']['id'] > 1 && $context['group']['id'] != 3)
	{
		echo '
					<dt id="group_inherit_text">
						<label for="group_inherit_input"><strong>', $txt['membergroups_edit_inherit_permissions'], '</strong></label>:<br>
						<span class="smalltext">', $txt['membergroups_edit_inherit_permissions_desc'], '</span>
					</dt>
					<dd>
						<select name="group_inherit" id="group_inherit_input">
							<option value="-2">', $txt['membergroups_edit_inherit_permissions_no'], '</option>
							<option value="-1"', $context['group']['inherited_from'] == -1 ? ' selected' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_guests'], '</option>
							<option value="0"', $context['group']['inherited_from'] == 0 ? ' selected' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_members'], '</option>';

		// For all the inheritable groups show an option.
		foreach ($context['inheritable_groups'] as $id => $group)
			echo '
							<option value="', $id, '"', $context['group']['inherited_from'] == $id ? ' selected' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $group, '</option>';

		echo '
						</select>
						<input type="hidden" name="old_inherit" value="', $context['group']['inherited_from'], '">
					</dd>';
	}

	if ($context['group']['allow_post_group'])
		echo '

					<dt id="min_posts_text">
						<label for="min_posts_input"><strong>', $txt['membergroups_min_posts'], ':</strong></label>
					</dt>
					<dd>
						<input type="number" name="min_posts" id="min_posts_input"', $context['group']['is_post_group'] ? ' value="' . $context['group']['min_posts'] . '"' : '', ' size="6">
					</dd>';

	echo '
					<dt>
						<label for="online_color_input"><strong>', $txt['membergroups_online_color'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="online_color" id="online_color_input" value="', $context['group']['color'], '" size="20">
					</dd>
					<dt>
						<label for="icon_count_input"><strong>', $txt['membergroups_icon_count'], ':</strong></label>
					</dt>
					<dd>
						<input type="number" name="icon_count" id="icon_count_input" value="', $context['group']['icon_count'], '" size="4">
					</dd>';

	// Do we have any possible icons to select from?
	if (!empty($context['possible_icons']))
	{
		echo '
					<dt>
						<label for="icon_image_input"><strong>', $txt['membergroups_icon_image'], ':</strong></label><br>
						<span class="smalltext">', $txt['membergroups_icon_image_note'], '</span>
						<span class="smalltext">', $txt['membergroups_icon_image_size'], '</span>
					</dt>
					<dd>
						', $txt['membergroups_images_url'], '
						<select name="icon_image" id="icon_image_input">';

		// For every possible icon, create an option.
		foreach ($context['possible_icons'] as $icon)
			echo '
							<option value="', $icon, '"', $context['group']['icon_image'] == $icon ? ' selected' : '', '>', $icon, '</option>';

		echo '
						</select>
						<img id="icon_preview" src="" alt="*">
					</dd>';
	}

	// No? Hide the entire control.
	else
		echo '
					<input type="hidden" name="icon_image" value="">';

	echo '
					<dt>
						<label for="max_messages_input"><strong>', $txt['membergroups_max_messages'], ':</strong></label><br>
						<span class="smalltext">', $txt['membergroups_max_messages_note'], '</span>
					</dt>
					<dd>
						<input type="text" name="max_messages" id="max_messages_input" value="', $context['group']['id'] == 1 ? 0 : $context['group']['max_messages'], '" size="6"', $context['group']['id'] == 1 ? ' disabled' : '', '>
					</dd>';

	// Force 2FA for this membergroup?
	if (!empty($modSettings['tfa_mode']) && $modSettings['tfa_mode'] == 2)
		echo '
					<dt>
						<label for="group_tfa_force_input"><strong>', $txt['membergroups_tfa_force'], ':</strong></label><br>
						<span class="smalltext">', $txt['membergroups_tfa_force_note'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="group_tfa_force"', $context['group']['tfa_required'] ? ' checked' : '', '>
					</dd>';

	if (!empty($context['categories']))
	{
		echo '
					<dt>
						<strong>', $txt['membergroups_new_board'], ':</strong>', $context['group']['is_post_group'] ? '<br>
						<span class="smalltext">' . $txt['membergroups_new_board_post_groups'] . '</span>' : '', '
					</dt>
					<dd>';

		if (!empty($context['can_manage_boards']))
			echo $txt['membergroups_can_manage_access'];

		else
			template_add_edit_group_boards_list(true, 'groupForm');

		echo '
					</dd>';
	}

	echo '
				</dl>
				<input type="submit" name="save" value="', $txt['membergroups_edit_save'], '" class="button">', $context['group']['allow_delete'] ? '
				<input type="submit" name="delete" value="' . $txt['membergroups_delete'] . '" data-confirm="' . ($context['is_moderator_group'] ? $txt['membergroups_confirm_delete_mod'] : $txt['membergroups_confirm_delete']) . '" class="button you_sure">' : '', '
			</div><!-- .windowbg -->
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mmg_token_var'], '" value="', $context['admin-mmg_token'], '">
		</form>
	<script>
		var oModeratorSuggest = new smc_AutoSuggest({
			sSelf: \'oModeratorSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
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
	</script>';

	if ($context['group']['allow_post_group'])
		echo '
	<script>
		function swapPostGroup(isChecked)
		{
			var is_moderator_group = ', (int)$context['is_moderator_group'], ';
			var group_type = ', $context['group']['type'], ';
			var min_posts_text = document.getElementById(\'min_posts_text\');
			var group_desc_text = document.getElementById(\'group_desc_text\');
			var group_hidden_text = document.getElementById(\'group_hidden_text\');
			var group_moderators_text = document.getElementById(\'group_moderators_text\');

			// If it\'s a moderator group, warn of possible problems... and remember the group type
			if (isChecked && is_moderator_group && !confirm(\'', $txt['membergroups_swap_mod'], '\'))
			{
				isChecked = false;

				switch(group_type)
				{
					case 0:
						document.getElementById(\'group_type_private\').checked = true;
						break;
					case 1:
						document.getElementById(\'group_type_protected\').checked = true;
						break;
					case 2:
						document.getElementById(\'group_type_request\').checked = true;
						break;
					case 3:
						document.getElementById(\'group_type_free\').checked = true;
						break;
					default:
						document.getElementById(\'group_type_private\').checked = true;
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

		swapPostGroup(', $context['group']['is_post_group'] ? 'true' : 'false', ');
	</script>';
}

/**
 * The template for determining which boards a group has access to.
 *
 * @param bool $collapse Whether to collapse the list by default
 */
function template_add_edit_group_boards_list($collapse = true, $form_id = 'new_group')
{
	global $context, $txt, $modSettings;

	echo '
							<fieldset id="visible_boards"', !empty($modSettings['deny_boards_access']) ? ' class="denyboards_layout"' : '', '>
								<legend>', $txt['membergroups_new_board_desc'], '</legend>
								<ul class="padding floatleft">';

	foreach ($context['categories'] as $category)
	{
		if (empty($modSettings['deny_boards_access']))
			echo '
									<li class="category">
										<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \''.$form_id.'\'); return false;"><strong>', $category['name'], '</strong></a>
										<ul>';
		else
			echo '
									<li class="category clear">
										<strong>', $category['name'], '</strong>
										<span class="select_all_box floatright">
											<em class="all_boards_in_cat">', $txt['all_boards_in_cat'], ': </em>
											<select onchange="select_in_category(', $category['id'], ', this, [', implode(',', array_keys($category['boards'])), ']);">
												<option>---</option>
												<option value="allow">', $txt['board_perms_allow'], '</option>
												<option value="ignore">', $txt['board_perms_ignore'], '</option>
												<option value="deny">', $txt['board_perms_deny'], '</option>
											</select>
										</span>
										<ul id="boards_list_', $category['id'], '">';

		foreach ($category['boards'] as $board)
		{
			if (empty($modSettings['deny_boards_access']))
				echo '
											<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
												<input type="checkbox" name="boardaccess[', $board['id'], ']" id="brd', $board['id'], '" value="allow"', $board['allow'] ? ' checked' : '', '> <label for="brd', $board['id'], '">', $board['name'], '</label>
											</li>';
			else
				echo '
											<li class="board clear">
												<span style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">', $board['name'], ': </span>
												<span class="floatright">
													<input type="radio" name="boardaccess[', $board['id'], ']" id="allow_brd', $board['id'], '" value="allow"', $board['allow'] ? ' checked' : '', '> <label for="allow_brd', $board['id'], '">', $txt['permissions_option_on'], '</label>
													<input type="radio" name="boardaccess[', $board['id'], ']" id="ignore_brd', $board['id'], '" value="ignore"', !$board['allow'] && !$board['deny'] ? ' checked' : '', '> <label for="ignore_brd', $board['id'], '">', $txt['permissions_option_off'], '</label>
													<input type="radio" name="boardaccess[', $board['id'], ']" id="deny_brd', $board['id'], '" value="deny"', $board['deny'] ? ' checked' : '', '> <label for="deny_brd', $board['id'], '">', $txt['permissions_option_deny'], '</label>
												</span>
											</li>';
		}

		echo '
										</ul>
									</li>';
	}

	echo '
								</ul>';

	if (empty($modSettings['deny_boards_access']))
		echo '
								<br class="clear"><br>
								<input type="checkbox" id="checkall_check" onclick="invertAll(this, this.form, \'boardaccess\');">
								<label for="checkall_check"><em>', $txt['check_all'], '</em></label>
							</fieldset>';
	else
		echo '
								<br class="clear">
								<span class="select_all_box">
									<em>', $txt['all'], ': </em>
									<input type="radio" name="select_all" id="allow_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'allow\');"> <label for="allow_all">', $txt['board_perms_allow'], '</label>
									<input type="radio" name="select_all" id="ignore_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'ignore\');"> <label for="ignore_all">', $txt['board_perms_ignore'], '</label>
									<input type="radio" name="select_all" id="deny_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'deny\');"> <label for="deny_all">', $txt['board_perms_deny'], '</label>
								</span>
							</fieldset>
							<script>
								$(document).ready(function () {
									$(".select_all_box").each(function () {
										$(this).removeClass(\'select_all_box\');
									});
								});
							</script>';

	if ($collapse)
		echo '
							<a href="javascript:void(0);" onclick="document.getElementById(\'visible_boards\').classList.remove(\'hidden\'); document.getElementById(\'visible_boards_link\').classList.add(\'hidden\'); return false;" id="visible_boards_link" class="hidden">[ ', $txt['membergroups_select_visible_boards'], ' ]</a>
							<script>
								document.getElementById("visible_boards_link").classList.remove(\'hidden\');
								document.getElementById("visible_boards").classList.add(\'hidden\');
							</script>';
}

/**
 * Template for viewing the members of a group.
 */
function template_group_members()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;group=', $context['group']['id'], '" method="post" accept-charset="', $context['character_set'], '" id="view_group">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', $txt['name'], ':</strong>
					</dt>
					<dd>
						<span ', $context['group']['online_color'] ? 'style="color: ' . $context['group']['online_color'] . ';"' : '', '>', $context['group']['name'], '</span> ', $context['group']['icons'], '
					</dd>';

	// Any description to show?
	if (!empty($context['group']['description']))
		echo '
					<dt>
						<strong>' . $txt['membergroups_members_description'] . ':</strong>
					</dt>
					<dd>
						', $context['group']['description'], '
					</dd>';

	echo '
					<dt>
						<strong>', $txt['membergroups_members_top'], ':</strong>
					</dt>
					<dd>
						', $context['total_members'], '
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
						', implode(', ', $moderators), '
					</dd>';
	}

	echo '
				</dl>
			</div><!-- .windowbg -->
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_members_group_members'], '</h3>
			</div>
			<br>
			<div class="pagesection">', $context['page_index'], '</div>
			<table class="table_grid" id="group_members">
				<thead>
					<tr class="title_bar">
						<th class="user_name"><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=name', $context['sort_by'] == 'name' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['name'], $context['sort_by'] == 'name' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>';

	if ($context['can_send_email'])
		echo '
						<th class="email"><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=email', $context['sort_by'] == 'email' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['email'], $context['sort_by'] == 'email' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>';

	echo '
						<th class="last_active"><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=active', $context['sort_by'] == 'active' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['membergroups_members_last_active'], $context['sort_by'] == 'active' ? '<span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
						<th class="date_registered"><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=registered', $context['sort_by'] == 'registered' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['date_registered'], $context['sort_by'] == 'registered' ? '<span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
						<th class="posts"', empty($context['group']['assignable']) ? ' colspan="2"' : '', '>
							<a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=posts', $context['sort_by'] == 'posts' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['posts'], $context['sort_by'] == 'posts' ? ' <span class="main_icons sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
						</th>';

	if (!empty($context['group']['assignable']))
		echo '
						<th class="quick_moderation" style="width: 4%"><input type="checkbox" onclick="invertAll(this, this.form);"></th>';

	echo '
					</tr>
				</thead>
				<tbody>';

	if (empty($context['members']))
		echo '
					<tr class="windowbg">
						<td colspan="6">', $txt['membergroups_members_no_members'], '</td>
					</tr>';

	foreach ($context['members'] as $member)
	{
		echo '
					<tr class="windowbg">
						<td class="user_name">', $member['name'], '</td>';

		if ($context['can_send_email'])
			echo '
						<td class="email">
								<a href="mailto:', $member['email'], '">', $member['email'], '</a>
						</td>';

		echo '
						<td class="last_active">', $member['last_online'], '</td>
						<td class="date_registered">', $member['registered'], '</td>
						<td class="posts"', empty($context['group']['assignable']) ? ' colspan="2"' : '', '>', $member['posts'], '</td>';

		if (!empty($context['group']['assignable']))
			echo '
						<td class="quick_moderation" style="width: 4%"><input type="checkbox" name="rem[]" value="', $member['id'], '" ', ($context['user']['id'] == $member['id'] && $context['group']['id'] == 1 ? 'onclick="if (this.checked) return confirm(\'' . $txt['membergroups_members_deadmin_confirm'] . '\')" ' : ''), '/></td>';

		echo '
					</tr>';
	}

	echo '
				</tbody>
			</table>';

	if (!empty($context['group']['assignable']))
		echo '
			<div class="floatright">
				<input type="submit" name="remove" value="', $txt['membergroups_members_remove'], '" class="button ">
			</div>';

	echo '
			<div class="pagesection flow_hidden">
				<div class="floatleft">', $context['page_index'], '</div>
			</div>
			<br>';

	if (!empty($context['group']['assignable']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['membergroups_members_add_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong><label for="toAdd">', $txt['membergroups_members_add_desc'], ':</label></strong>
					</dt>
					<dd>
						<input type="text" name="toAdd" id="toAdd" value="">
						<div id="toAddItemContainer"></div>
					</dd>
				</dl>
				<input type="submit" name="add" value="', $txt['membergroups_members_add'], '" class="button">
			</div>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['mod-mgm_token_var'], '" value="', $context['mod-mgm_token'], '">
		</form>';

	if (!empty($context['group']['assignable']))
		echo '
	<script>
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
	</script>';
}

/**
 * Allow the moderator to enter a reason to each user being rejected.
 */
function template_group_request_reason()
{
	global $context, $txt, $scripturl;

	// Show a welcome message to the user.
	echo '
	<div id="moderationcenter">
		<form action="', $scripturl, '?action=groups;sa=requests" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mc_groups_reason_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Loop through and print out a reason box for each...
	foreach ($context['group_requests'] as $request)
		echo '
					<dt>
						<strong>', sprintf($txt['mc_groupr_reason_desc'], $request['member_link'], $request['group_link']), ':</strong>
					</dt>
					<dd>
						<input type="hidden" name="groupr[]" value="', $request['id'], '">
						<textarea name="groupreason[', $request['id'], ']" rows="3" cols="40"></textarea>
					</dd>';

	echo '
				</dl>
				<input type="submit" name="go" value="', $txt['mc_groupr_submit'], '" class="button">
				<input type="hidden" name="req_action" value="got_reason">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['mod-gr_token_var'], '" value="', $context['mod-gr_token'], '">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #moderationcenter -->';
}

?>