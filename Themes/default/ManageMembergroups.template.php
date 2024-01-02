<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;
use SMF\User;

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
	echo '
		<form id="new_group" action="', Config::$scripturl, '?action=admin;area=membergroups;sa=add" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['membergroups_new_group'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong>', Lang::$txt['membergroups_group_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" size="30">
					</dd>';

	if (Utils::$context['undefined_group'])
	{
		echo '
					<dt>
						<label for="group_type"><strong>', Lang::$txt['membergroups_edit_group_type'], '</strong>:</label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend>', Lang::$txt['membergroups_edit_select_group_type'], '</legend>
							<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0" checked onclick="swapPostGroup(0);">', Lang::$txt['membergroups_group_type_private'], '</label><br>';

		if (Utils::$context['allow_protected'])
			echo '
							<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1" onclick="swapPostGroup(0);">', Lang::$txt['membergroups_group_type_protected'], '</label><br>';

		echo '
							<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2" onclick="swapPostGroup(0);">', Lang::$txt['membergroups_group_type_request'], '</label><br>
							<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3" onclick="swapPostGroup(0);">', Lang::$txt['membergroups_group_type_free'], '</label><br>
							<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1" onclick="swapPostGroup(1);">', Lang::$txt['membergroups_group_type_post'], '</label><br>
						</fieldset>
					</dd>';
	}

	if (Utils::$context['post_group'] || Utils::$context['undefined_group'])
		echo '
					<dt id="min_posts_text">
						<strong>', Lang::$txt['membergroups_min_posts'], ':</strong>
					</dt>
					<dd>
						<input type="number" name="min_posts" id="min_posts_input" size="5">
					</dd>';

	if (!Utils::$context['post_group'] || !empty(Config::$modSettings['permission_enable_postgroups']))
	{
		echo '
					<dt>
						<label for="permission_base"><strong>', Lang::$txt['membergroups_permissions'], ':</strong></label><br>
						<span class="smalltext">', Lang::$txt['membergroups_can_edit_later'], '</span>
					</dt>
					<dd>
						<fieldset id="permission_base">
							<legend>', Lang::$txt['membergroups_select_permission_type'], '</legend>
							<input type="radio" name="perm_type" id="perm_type_inherit" value="inherit" checked>
							<label for="perm_type_inherit">', Lang::$txt['membergroups_new_as_inherit'], ':</label>
							<select name="inheritperm" id="inheritperm_select" onclick="document.getElementById(\'perm_type_inherit\').checked = true;">
								<option value="-1">', Lang::$txt['membergroups_guests'], '</option>
								<option value="0" selected>', Lang::$txt['membergroups_members'], '</option>';

		foreach (Utils::$context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
							<br>
							<input type="radio" name="perm_type" id="perm_type_copy" value="copy">
							<label for="perm_type_copy">', Lang::$txt['membergroups_new_as_copy'], ':</label>
							<select name="copyperm" id="copyperm_select" onclick="document.getElementById(\'perm_type_copy\').checked = true;">
								<option value="-1">', Lang::$txt['membergroups_guests'], '</option>
								<option value="0" selected>', Lang::$txt['membergroups_members'], '</option>';

		foreach (Utils::$context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
							<br>
							<input type="radio" name="perm_type" id="perm_type_predefined" value="predefined">
							<label for="perm_type_predefined">', Lang::$txt['membergroups_new_as_type'], ':</label>
							<select name="level" id="level_select" onclick="document.getElementById(\'perm_type_predefined\').checked = true;">
								<option value="restrict">', Lang::$txt['permitgroups_restrict'], '</option>
								<option value="standard" selected>', Lang::$txt['permitgroups_standard'], '</option>
								<option value="moderator">', Lang::$txt['permitgroups_moderator'], '</option>
								<option value="maintenance">', Lang::$txt['permitgroups_maintenance'], '</option>
							</select>
						</fieldset>
					</dd>';
	}

	echo '
					<dt>
						<strong>', Lang::$txt['membergroups_new_board'], ':</strong>', Utils::$context['post_group'] ? '<br>
						<span class="smalltext">' . Lang::$txt['membergroups_new_board_post_groups'] . '</span>' : '', '
					</dt>
					<dd>';

	template_add_edit_group_boards_list(false);

	echo '
					</dd>
				</dl>
				<input type="submit" value="', Lang::$txt['membergroups_add_group'], '" class="button">
			</div><!-- .windowbg -->';

	if (Utils::$context['undefined_group'])
		echo '
			<script>
				function swapPostGroup(isChecked)
				{
					var min_posts_text = document.getElementById(\'min_posts_text\');
					document.getElementById(\'min_posts_input\').disabled = !isChecked;
					min_posts_text.style.color = isChecked ? "" : "#888888";
				}
				swapPostGroup(', Utils::$context['post_group'] ? 'true' : 'false', ');
			</script>';

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-mmg_token_var'], '" value="', Utils::$context['admin-mmg_token'], '">
		</form>';
}

/**
 * Edit an existing membergroup.
 */
function template_edit_group()
{
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=membergroups;sa=edit;group=', Utils::$context['group']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '" name="groupForm" id="groupForm">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['membergroups_edit_group'], ' - ', Utils::$context['group']['name'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong>', Lang::$txt['membergroups_edit_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" value="', Utils::$context['group']['editable_name'], '" size="30">
					</dd>';

	if (Utils::$context['group']['id'] != 3 && Utils::$context['group']['id'] != 4)
		echo '
					<dt id="group_desc_text">
						<label for="group_desc_input"><strong>', Lang::$txt['membergroups_edit_desc'], ':</strong></label>
					</dt>
					<dd>
						<textarea name="group_desc" id="group_desc_input" rows="4" cols="40">', Utils::$context['group']['description'], '</textarea>
					</dd>';

	// Group type...
	if (Utils::$context['group']['can_change_type'])
	{
		echo '
					<dt>
						<label for="group_type"><strong>', Lang::$txt['membergroups_edit_group_type'], ':</strong></label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend>', Lang::$txt['membergroups_edit_select_group_type'], '</legend>
							<label for="group_type_private"><input type="radio" name="group_type" id="group_type_private" value="0"', !Utils::$context['group']['is_post_group'] && Utils::$context['group']['type'] == 0 ? ' checked' : '', (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : ''), '>', Lang::$txt['membergroups_group_type_private'], '</label><br>';

		if (Utils::$context['group']['allow_protected'])
			echo '
							<label for="group_type_protected"><input type="radio" name="group_type" id="group_type_protected" value="1"', Utils::$context['group']['type'] == 1 ? ' checked' : '', (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : ''), '>', Lang::$txt['membergroups_group_type_protected'], '</label><br>';

		echo '
							<label for="group_type_request"><input type="radio" name="group_type" id="group_type_request" value="2"', Utils::$context['group']['type'] == 2 ? ' checked' : '', (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : ''), '>', Lang::$txt['membergroups_group_type_request'], '</label><br>
							<label for="group_type_free"><input type="radio" name="group_type" id="group_type_free" value="3"', Utils::$context['group']['type'] == 3 ? ' checked' : '', (Utils::$context['group']['allow_post_group'] ? ' onclick="swapPostGroup(0);"' : ''), '>', Lang::$txt['membergroups_group_type_free'], '</label><br>';

		if (Utils::$context['group']['allow_post_group'])
			echo '

							<label for="group_type_post"><input type="radio" name="group_type" id="group_type_post" value="-1"', Utils::$context['group']['is_post_group'] ? ' checked' : '', ' onclick="swapPostGroup(1);">', Lang::$txt['membergroups_group_type_post'], '</label><br>';

		echo '
						</fieldset>
					</dd>';
	}

	if (Utils::$context['group']['id'] != 3 && Utils::$context['group']['id'] != 4)
		echo '
					<dt id="group_moderators_text">
						<label for="group_moderators"><strong>', Lang::$txt['moderators'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_moderators" id="group_moderators" value="', Utils::$context['group']['moderator_list'], '" size="30">
						<div id="moderator_container"></div>
					</dd>
					<dt id="group_hidden_text">
						<label for="group_hidden_input"><strong>', Lang::$txt['membergroups_edit_hidden'], ':</strong></label>
					</dt>
					<dd>
						<select name="group_hidden" id="group_hidden_input" onchange="if (this.value == 2 &amp;&amp; !confirm(\'', Lang::$txt['membergroups_edit_hidden_warning'], '\')) this.value = 0;">
							<option value="0"', Utils::$context['group']['hidden'] ? '' : ' selected', '>', Lang::$txt['membergroups_edit_hidden_no'], '</option>
							<option value="1"', Utils::$context['group']['hidden'] == 1 ? ' selected' : '', '>', Lang::$txt['membergroups_edit_hidden_boardindex'], '</option>
							<option value="2"', Utils::$context['group']['hidden'] == 2 ? ' selected' : '', '>', Lang::$txt['membergroups_edit_hidden_all'], '</option>
						</select>
					</dd>';

	// Can they inherit permissions?
	if (Utils::$context['group']['id'] > 1 && Utils::$context['group']['id'] != 3)
	{
		echo '
					<dt id="group_inherit_text">
						<label for="group_inherit_input"><strong>', Lang::$txt['membergroups_edit_inherit_permissions'], '</strong></label>:<br>
						<span class="smalltext">', Lang::$txt['membergroups_edit_inherit_permissions_desc'], '</span>
					</dt>
					<dd>
						<select name="group_inherit" id="group_inherit_input">
							<option value="-2">', Lang::$txt['membergroups_edit_inherit_permissions_no'], '</option>
							<option value="-1"', Utils::$context['group']['inherited_from'] == -1 ? ' selected' : '', '>', Lang::$txt['membergroups_edit_inherit_permissions_from'], ': ', Lang::$txt['membergroups_guests'], '</option>
							<option value="0"', Utils::$context['group']['inherited_from'] == 0 ? ' selected' : '', '>', Lang::$txt['membergroups_edit_inherit_permissions_from'], ': ', Lang::$txt['membergroups_members'], '</option>';

		// For all the inheritable groups show an option.
		foreach (Utils::$context['inheritable_groups'] as $id => $group)
			echo '
							<option value="', $id, '"', Utils::$context['group']['inherited_from'] == $id ? ' selected' : '', '>', Lang::$txt['membergroups_edit_inherit_permissions_from'], ': ', $group, '</option>';

		echo '
						</select>
						<input type="hidden" name="old_inherit" value="', Utils::$context['group']['inherited_from'], '">
					</dd>';
	}

	if (Utils::$context['group']['allow_post_group'])
		echo '

					<dt id="min_posts_text">
						<label for="min_posts_input"><strong>', Lang::$txt['membergroups_min_posts'], ':</strong></label>
					</dt>
					<dd>
						<input type="number" name="min_posts" id="min_posts_input"', Utils::$context['group']['is_post_group'] ? ' value="' . Utils::$context['group']['min_posts'] . '"' : '', ' size="6">
					</dd>';

	echo '
					<dt>
						<label for="online_color_input"><strong>', Lang::$txt['membergroups_online_color'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="online_color" id="online_color_input" value="', Utils::$context['group']['color'], '" size="20">
					</dd>
					<dt>
						<label for="icon_count_input"><strong>', Lang::$txt['membergroups_icon_count'], ':</strong></label>
					</dt>
					<dd>
						<input type="number" name="icon_count" id="icon_count_input" value="', Utils::$context['group']['icon_count'], '" size="4">
					</dd>';

	// Do we have any possible icons to select from?
	if (!empty(Utils::$context['possible_icons']))
	{
		echo '
					<dt>
						<label for="icon_image_input"><strong>', Lang::$txt['membergroups_icon_image'], ':</strong></label><br>
						<span class="smalltext">', Lang::$txt['membergroups_icon_image_note'], '</span>
						<span class="smalltext">', Lang::$txt['membergroups_icon_image_size'], '</span>
					</dt>
					<dd>
						', Lang::$txt['membergroups_images_url'], '
						<select name="icon_image" id="icon_image_input">';

		// For every possible icon, create an option.
		foreach (Utils::$context['possible_icons'] as $icon)
			echo '
							<option value="', $icon, '"', Utils::$context['group']['icon_image'] == $icon ? ' selected' : '', '>', $icon, '</option>';

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
						<label for="max_messages_input"><strong>', Lang::$txt['membergroups_max_messages'], ':</strong></label><br>
						<span class="smalltext">', Lang::$txt['membergroups_max_messages_note'], '</span>
					</dt>
					<dd>
						<input type="text" name="max_messages" id="max_messages_input" value="', Utils::$context['group']['id'] == 1 ? 0 : Utils::$context['group']['max_messages'], '" size="6"', Utils::$context['group']['id'] == 1 ? ' disabled' : '', '>
					</dd>';

	// Force 2FA for this membergroup?
	if (!empty(Config::$modSettings['tfa_mode']) && Config::$modSettings['tfa_mode'] == 2)
		echo '
					<dt>
						<label for="group_tfa_force_input"><strong>', Lang::$txt['membergroups_tfa_force'], ':</strong></label><br>
						<span class="smalltext">', Lang::$txt['membergroups_tfa_force_note'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="group_tfa_force"', Utils::$context['group']['tfa_required'] ? ' checked' : '', '>
					</dd>';

	if (!empty(Utils::$context['categories']))
	{
		echo '
					<dt>
						<strong>', Lang::$txt['membergroups_new_board'], ':</strong>', Utils::$context['group']['is_post_group'] ? '<br>
						<span class="smalltext">' . Lang::$txt['membergroups_new_board_post_groups'] . '</span>' : '', '
					</dt>
					<dd>';

		if (!empty(Utils::$context['can_manage_boards']))
			echo Lang::$txt['membergroups_can_manage_access'];

		else
			template_add_edit_group_boards_list(true, 'groupForm');

		echo '
					</dd>';
	}

	echo '
				</dl>
				<input type="submit" name="save" value="', Lang::$txt['membergroups_edit_save'], '" class="button">', Utils::$context['group']['allow_delete'] ? '
				<input type="submit" name="delete" value="' . Lang::$txt['membergroups_delete'] . '" data-confirm="' . (Utils::$context['is_moderator_group'] ? Lang::$txt['membergroups_confirm_delete_mod'] : Lang::$txt['membergroups_confirm_delete']) . '" class="button you_sure">' : '', '
			</div><!-- .windowbg -->
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-mmg_token_var'], '" value="', Utils::$context['admin-mmg_token'], '">
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
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			sItemListContainerId: \'moderator_container\',
			aListItems: [';

	foreach (Utils::$context['group']['moderators'] as $id_member => $member_name)
		echo '
				{
					sItemId: ', Utils::JavaScriptEscape($id_member), ',
					sItemName: ', Utils::JavaScriptEscape($member_name), '
				}', $id_member == Utils::$context['group']['last_moderator_id'] ? '' : ',';

	echo '
			]
		});
	</script>';

	if (Utils::$context['group']['allow_post_group'])
		echo '
	<script>
		function swapPostGroup(isChecked)
		{
			var is_moderator_group = ', (int)Utils::$context['is_moderator_group'], ';
			var group_type = ', Utils::$context['group']['type'], ';
			var min_posts_text = document.getElementById(\'min_posts_text\');
			var group_desc_text = document.getElementById(\'group_desc_text\');
			var group_hidden_text = document.getElementById(\'group_hidden_text\');
			var group_moderators_text = document.getElementById(\'group_moderators_text\');

			// If it\'s a moderator group, warn of possible problems... and remember the group type
			if (isChecked && is_moderator_group && !confirm(\'', Lang::$txt['membergroups_swap_mod'], '\'))
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

		swapPostGroup(', Utils::$context['group']['is_post_group'] ? 'true' : 'false', ');
	</script>';
}

/**
 * The template for determining which boards a group has access to.
 *
 * @param bool $collapse Whether to collapse the list by default
 */
function template_add_edit_group_boards_list($collapse = true, $form_id = 'new_group')
{
	echo '
							<fieldset id="visible_boards"', !empty(Config::$modSettings['deny_boards_access']) ? ' class="denyboards_layout"' : '', '>
								<legend>', Lang::$txt['membergroups_new_board_desc'], '</legend>
								<ul class="padding floatleft">';

	foreach (Utils::$context['categories'] as $category)
	{
		if (empty(Config::$modSettings['deny_boards_access']))
			echo '
									<li class="category">
										<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \''.$form_id.'\'); return false;"><strong>', $category['name'], '</strong></a>
										<ul>';
		else
			echo '
									<li class="category clear">
										<strong>', $category['name'], '</strong>
										<span class="select_all_box floatright">
											<em class="all_boards_in_cat">', Lang::$txt['all_boards_in_cat'], ': </em>
											<select onchange="select_in_category(', $category['id'], ', this, [', implode(',', array_keys($category['boards'])), ']);">
												<option>---</option>
												<option value="allow">', Lang::$txt['board_perms_allow'], '</option>
												<option value="ignore">', Lang::$txt['board_perms_ignore'], '</option>
												<option value="deny">', Lang::$txt['board_perms_deny'], '</option>
											</select>
										</span>
										<ul id="boards_list_', $category['id'], '">';

		foreach ($category['boards'] as $board)
		{
			if (empty(Config::$modSettings['deny_boards_access']))
				echo '
											<li class="board" style="margin-', Utils::$context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
												<input type="checkbox" name="boardaccess[', $board['id'], ']" id="brd', $board['id'], '" value="allow"', $board['allow'] ? ' checked' : '', '> <label for="brd', $board['id'], '">', $board['name'], '</label>
											</li>';
			else
				echo '
											<li class="board clear">
												<span style="margin-', Utils::$context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">', $board['name'], ': </span>
												<span class="floatright">
													<input type="radio" name="boardaccess[', $board['id'], ']" id="allow_brd', $board['id'], '" value="allow"', $board['allow'] ? ' checked' : '', '> <label for="allow_brd', $board['id'], '">', Lang::$txt['permissions_option_on'], '</label>
													<input type="radio" name="boardaccess[', $board['id'], ']" id="ignore_brd', $board['id'], '" value="ignore"', !$board['allow'] && !$board['deny'] ? ' checked' : '', '> <label for="ignore_brd', $board['id'], '">', Lang::$txt['permissions_option_off'], '</label>
													<input type="radio" name="boardaccess[', $board['id'], ']" id="deny_brd', $board['id'], '" value="deny"', $board['deny'] ? ' checked' : '', '> <label for="deny_brd', $board['id'], '">', Lang::$txt['permissions_option_deny'], '</label>
												</span>
											</li>';
		}

		echo '
										</ul>
									</li>';
	}

	echo '
								</ul>';

	if (empty(Config::$modSettings['deny_boards_access']))
		echo '
								<br class="clear"><br>
								<input type="checkbox" id="checkall_check" onclick="invertAll(this, this.form, \'boardaccess\');">
								<label for="checkall_check"><em>', Lang::$txt['check_all'], '</em></label>
							</fieldset>';
	else
		echo '
								<br class="clear">
								<span class="select_all_box">
									<em>', Lang::$txt['all'], ': </em>
									<input type="radio" name="select_all" id="allow_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'allow\');"> <label for="allow_all">', Lang::$txt['board_perms_allow'], '</label>
									<input type="radio" name="select_all" id="ignore_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'ignore\');"> <label for="ignore_all">', Lang::$txt['board_perms_ignore'], '</label>
									<input type="radio" name="select_all" id="deny_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'deny\');"> <label for="deny_all">', Lang::$txt['board_perms_deny'], '</label>
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
							<a href="javascript:void(0);" onclick="document.getElementById(\'visible_boards\').classList.remove(\'hidden\'); document.getElementById(\'visible_boards_link\').classList.add(\'hidden\'); return false;" id="visible_boards_link" class="hidden">[ ', Lang::$txt['membergroups_select_visible_boards'], ' ]</a>
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
	echo '
		<form action="', Config::$scripturl, '?action=', Utils::$context['current_action'], (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : ''), ';sa=members;group=', Utils::$context['group']['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '" id="view_group">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong>', Lang::$txt['name'], ':</strong>
					</dt>
					<dd>
						<span ', Utils::$context['group']['online_color'] ? 'style="color: ' . Utils::$context['group']['online_color'] . ';"' : '', '>', Utils::$context['group']['name'], '</span> ', Utils::$context['group']['icons'], '
					</dd>';

	// Any description to show?
	if (!empty(Utils::$context['group']['description']))
		echo '
					<dt>
						<strong>' . Lang::$txt['membergroups_members_description'] . ':</strong>
					</dt>
					<dd>
						', Utils::$context['group']['description'], '
					</dd>';

	echo '
					<dt>
						<strong>', Lang::$txt['membergroups_members_top'], ':</strong>
					</dt>
					<dd>
						', Utils::$context['total_members'], '
					</dd>';

	// Any group moderators to show?
	if (!empty(Utils::$context['group']['moderators']))
	{
		$moderators = array();
		foreach (Utils::$context['group']['moderators'] as $moderator)
			$moderators[] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $moderator['id'] . '">' . $moderator['name'] . '</a>';

		echo '
					<dt>
						<strong>', Lang::$txt['membergroups_members_group_moderators'], ':</strong>
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
				<h3 class="catbg">', Lang::$txt['membergroups_members_group_members'], '</h3>
			</div>
			<br>
			<div class="pagesection">
				<div class="pagelinks">', Utils::$context['page_index'], '</div>
			</div>
			<table class="table_grid" id="group_members">
				<thead>
					<tr class="title_bar">
						<th class="user_name"><a href="', Config::$scripturl, '?action=', Utils::$context['current_action'], (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : ''), ';sa=members;start=', Utils::$context['start'], ';sort=name', Utils::$context['sort_by'] == 'name' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', ';group=', Utils::$context['group']['id'], '">', Lang::$txt['name'], Utils::$context['sort_by'] == 'name' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a></th>';

	if (Utils::$context['can_send_email'])
		echo '
						<th class="email"><a href="', Config::$scripturl, '?action=', Utils::$context['current_action'], (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : ''), ';sa=members;start=', Utils::$context['start'], ';sort=email', Utils::$context['sort_by'] == 'email' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', ';group=', Utils::$context['group']['id'], '">', Lang::$txt['email'], Utils::$context['sort_by'] == 'email' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a></th>';

	echo '
						<th class="last_active"><a href="', Config::$scripturl, '?action=', Utils::$context['current_action'], (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : ''), ';sa=members;start=', Utils::$context['start'], ';sort=active', Utils::$context['sort_by'] == 'active' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', ';group=', Utils::$context['group']['id'], '">', Lang::$txt['membergroups_members_last_active'], Utils::$context['sort_by'] == 'active' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a></th>
						<th class="date_registered"><a href="', Config::$scripturl, '?action=', Utils::$context['current_action'], (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : ''), ';sa=members;start=', Utils::$context['start'], ';sort=registered', Utils::$context['sort_by'] == 'registered' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', ';group=', Utils::$context['group']['id'], '">', Lang::$txt['date_registered'], Utils::$context['sort_by'] == 'registered' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a></th>
						<th class="posts"', empty(Utils::$context['group']['assignable']) ? ' colspan="2"' : '', '>
							<a href="', Config::$scripturl, '?action=', Utils::$context['current_action'], (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : ''), ';sa=members;start=', Utils::$context['start'], ';sort=posts', Utils::$context['sort_by'] == 'posts' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '', ';group=', Utils::$context['group']['id'], '">', Lang::$txt['posts'], Utils::$context['sort_by'] == 'posts' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a>
						</th>';

	if (!empty(Utils::$context['group']['assignable']))
		echo '
						<th class="quick_moderation" style="width: 4%"><input type="checkbox" onclick="invertAll(this, this.form);"></th>';

	echo '
					</tr>
				</thead>
				<tbody>';

	if (empty(Utils::$context['members']))
		echo '
					<tr class="windowbg">
						<td colspan="6">', Lang::$txt['membergroups_members_no_members'], '</td>
					</tr>';

	foreach (Utils::$context['members'] as $member)
	{
		echo '
					<tr class="windowbg">
						<td class="user_name">', $member['link'], '</td>';

		if (Utils::$context['can_send_email'])
			echo '
						<td class="email">
								<a href="mailto:', $member['email'], '">', $member['email'], '</a>
						</td>';

		echo '
						<td class="last_active">', $member['last_login'], '</td>
						<td class="date_registered">', $member['registered'], '</td>
						<td class="posts"', empty(Utils::$context['group']['assignable']) ? ' colspan="2"' : '', '>', $member['posts'], '</td>';

		if (!empty(Utils::$context['group']['assignable']))
			echo '
						<td class="quick_moderation" style="width: 4%"><input type="checkbox" name="rem[]" value="', $member['id'], '" ', (User::$me->id == $member['id'] && Utils::$context['group']['id'] == 1 ? 'onclick="if (this.checked) return confirm(\'' . Lang::$txt['membergroups_members_deadmin_confirm'] . '\')" ' : ''), '/></td>';

		echo '
					</tr>';
	}

	echo '
				</tbody>
			</table>';

	if (!empty(Utils::$context['group']['assignable']))
		echo '
			<div class="floatright">
				<input type="submit" name="remove" value="', Lang::$txt['membergroups_members_remove'], '" class="button ">
			</div>';

	echo '
			<div class="pagesection">
				<div class="pagelinks">', Utils::$context['page_index'], '</div>
			</div>
			<br>';

	if (!empty(Utils::$context['group']['assignable']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['membergroups_members_add_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong><label for="toAdd">', Lang::$txt['membergroups_members_add_desc'], ':</label></strong>
					</dt>
					<dd>
						<input type="text" name="toAdd" id="toAdd" value="">
						<div id="toAddItemContainer"></div>
					</dd>
				</dl>
				<input type="submit" name="add" value="', Lang::$txt['membergroups_members_add'], '" class="button">
			</div>';

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['mod-mgm_token_var'], '" value="', Utils::$context['mod-mgm_token'], '">
		</form>';

	if (!empty(Utils::$context['group']['assignable']))
		echo '
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: \'', Utils::$context['session_id'], '\',
			sSessionVar: \'', Utils::$context['session_var'], '\',
			sSuggestId: \'to_suggest\',
			sControlId: \'toAdd\',
			sSearchType: \'member\',
			sPostName: \'member_add\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
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
	// Show a welcome message to the user.
	echo '
	<div id="moderationcenter">
		<form action="', Config::$scripturl, '?action=groups;sa=requests" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mc_groups_reason_title'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Loop through and print out a reason box for each...
	foreach (Utils::$context['group_requests'] as $request)
		echo '
					<dt>
						<strong>', sprintf(Lang::$txt['mc_groupr_reason_desc'], $request['member_link'], $request['group_link']), ':</strong>
					</dt>
					<dd>
						<input type="hidden" name="groupr[]" value="', $request['id'], '">
						<textarea name="groupreason[', $request['id'], ']" rows="3" cols="40"></textarea>
					</dd>';

	echo '
				</dl>
				<input type="submit" name="go" value="', Lang::$txt['mc_groupr_submit'], '" class="button">
				<input type="hidden" name="req_action" value="got_reason">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['mod-gr_token_var'], '" value="', Utils::$context['mod-gr_token'], '">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #moderationcenter -->';
}

?>