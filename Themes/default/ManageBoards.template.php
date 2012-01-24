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

// Template for listing all the current categories and boards.
function template_main()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Table header.
	echo '
	<div id="manage_boards">
		<div class="title_bar">
			<h3 class="titlebg">', $txt['boardsEdit'], '</h3>
		</div>';

	if (!empty($context['move_board']))
		echo '
		<div class="information">
			<p>', $context['move_title'], ' [<a href="', $scripturl, '?action=admin;area=manageboards">', $txt['mboards_cancel_moving'], '</a>]', '</p>
		</div>';

	// No categories so show a label.
	if (empty($context['categories']))
		echo '
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="content centertext">
				', $txt['mboards_no_cats'], '
			</div>
			<span class="botslice"><span></span></span>
		</div>';

	// Loop through every category, listing the boards in each as we go.
	foreach ($context['categories'] as $category)
	{
		// Link to modify the category.
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="' . $scripturl . '?action=admin;area=manageboards;sa=cat;cat=' . $category['id'] . '">', $category['name'], '</a> <a href="' . $scripturl . '?action=admin;area=manageboards;sa=cat;cat=' . $category['id'] . '">', $txt['catModify'], '</a>
				</h3>
			</div>';

		// Boards table header.
		echo '
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=newboard;cat=', $category['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<ul id="category_', $category['id'], '" style="float:left; width:100%;">';

		if (!empty($category['move_link']))
			echo '
						<li><a href="', $category['move_link']['href'], '" title="', $category['move_link']['label'], '"><img src="', $settings['images_url'], '/smiley_select_spot.gif" alt="', $category['move_link']['label'], '" /></a></li>';

		$alternate = false;

		// List through every board in the category, printing its name and link to modify the board.
		foreach ($category['boards'] as $board)
		{
			$alternate = !$alternate;

			echo '
						<li', !empty($modSettings['recycle_board']) && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board['id'] ? ' id="recycle_board"' : ' ', ' class="windowbg', $alternate ? '' : '2', '" style="padding-' . ($context['right_to_left'] ? 'right' : 'left') . ': ', 5 + 30 * $board['child_level'], 'px;', $board['move'] ? 'color: red;' : '', '"><span class="floatleft"><a href="', $scripturl, '?board=', $board['id'], '">', $board['name'], '</a>', !empty($modSettings['recycle_board']) && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board['id'] ? '<a href="' . $scripturl . '?action=admin;area=manageboards;sa=settings"> <img src="' . $settings['images_url'] . '/post/recycled.gif" alt="' . $txt['recycle_board'] . '" /></a></span>' : '</span>', '
							<span class="floatright">', $context['can_manage_permissions'] ? '<span class="modify_boards"><a href="' . $scripturl . '?action=admin;area=permissions;sa=index;pid=' . $board['permission_profile'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['mboards_permissions'] . '</a></span>' : '', '
							<span class="modify_boards"><a href="', $scripturl, '?action=admin;area=manageboards;move=', $board['id'], '">', $txt['mboards_move'], '</a></span>
							<span class="modify_boards"><a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], '">', $txt['mboards_modify'], '</a></span></span><br style="clear: right;" />
						</li>';

			if (!empty($board['move_links']))
			{
				$alternate = !$alternate;

				echo '
						<li class="windowbg', $alternate ? '' : '2', '" style="padding-', $context['right_to_left'] ? 'right' : 'left', ': ', 5 + 30 * $board['move_links'][0]['child_level'], 'px;">';

				foreach ($board['move_links'] as $link)
					echo '
							<a href="', $link['href'], '" class="move_links" title="', $link['label'], '"><img src="', $settings['images_url'], '/board_select_spot', $link['child_level'] > 0 ? '_child' : '', '.gif" alt="', $link['label'], '" style="padding: 0px; margin: 0px;" /></a>';

				echo '
						</li>';
			}
		}

		// Button to add a new board.
		echo '
					</ul>
					<div class="righttext">
						<input type="submit" value="', $txt['mboards_new_board'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>';
	}
	echo '
	</div>
	<br class="clear" />';
}

// Template for editing/adding a category on the forum.
function template_modify_category()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="cat" value="', $context['category']['id'], '" />
				<div class="cat_bar">
					<h3 class="catbg">
					', isset($context['category']['is_new']) ? $txt['mboards_new_cat_name'] : $txt['catEdit'], '
					</h3>
				</div>
				<div class="windowbg">
					<span class="topslice"><span></span></span>
					<div class="content">
						<dl class="settings">';
	// If this isn't the only category, let the user choose where this category should be positioned down the board index.
	if (count($context['category_order']) > 1)
	{
		echo '
						<dt><strong>', $txt['order'], ':</strong></dt>
						<dd>
							<select name="cat_order">';
		// Print every existing category into a select box.
		foreach ($context['category_order'] as $order)
			echo '
								<option', $order['selected'] ? ' selected="selected"' : '', ' value="', $order['id'], '">', $order['name'], '</option>';
		echo '
							</select>
						</dd>';
	}
	// Allow the user to edit the category name and/or choose whether you can collapse the category.
	echo '
						<dt>
							<strong>', $txt['full_name'], ':</strong><br />
							<span class="smalltext">', $txt['name_on_display'], '</span>
						</dt>
						<dd>
							<input type="text" name="cat_name" value="', $context['category']['editable_name'], '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" />
						</dd>
						<dt>
							<strong>' . $txt['collapse_enable'] . '</strong><br />
							<span class="smalltext">' . $txt['collapse_desc'] . '</span>
						</dt>
						<dd>
							<input type="checkbox" name="collapse"', $context['category']['can_collapse'] ? ' checked="checked"' : '', ' tabindex="', $context['tabindex']++, '" class="input_check" />
						</dd>';

	// Table footer.
	echo '
					</dl>
					<div class="righttext">';

	if (isset($context['category']['is_new']))
		echo '
						<input type="submit" name="add" value="', $txt['mboards_add_cat_button'], '" onclick="return !isEmptyText(this.form.cat_name);" tabindex="', $context['tabindex']++, '" class="button_submit" />';
	else
		echo '
						<input type="submit" name="edit" value="', $txt['modify'], '" onclick="return !isEmptyText(this.form.cat_name);" tabindex="', $context['tabindex']++, '" class="button_submit" />
						<input type="submit" name="delete" value="', $txt['mboards_delete_cat'], '" onclick="return confirm(\'', $txt['catConfirm'], '\');" class="button_submit" />';
	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	// If this category is empty we don't bother with the next confirmation screen.
	if ($context['category']['is_empty'])
		echo '
						<input type="hidden" name="empty" value="1" />';

	echo '
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

// A template to confirm if a user wishes to delete a category - and whether they want to save the boards.
function template_confirm_category_delete()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="cat" value="', $context['category']['id'], '" />
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mboards_delete_cat'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>', $txt['mboards_delete_cat_contains'], ':</p>
					<ul>';

	foreach ($context['category']['children'] as $child)
		echo '
						<li>', $child, '</li>';

	echo '
					</ul>
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mboards_delete_what_do'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>
						<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" class="input_radio" checked="checked" />', $txt['mboards_delete_option1'], '</label><br />
						<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1" class="input_radio"', count($context['category_order']) == 1 ? ' disabled="disabled"' : '', ' />', $txt['mboards_delete_option2'], '</label>:
						<select name="cat_to" ', count($context['category_order']) == 1 ? 'disabled="disabled"' : '', '>';

	foreach ($context['category_order'] as $cat)
		if ($cat['id'] != 0)
			echo '
							<option value="', $cat['id'], '">', $cat['true_name'], '</option>';

	echo '
						</select>
					</p>
					<input type="submit" name="delete" value="', $txt['mboards_delete_confirm'], '" class="button_submit" />
					<input type="submit" name="cancel" value="', $txt['mboards_delete_cancel'], '" class="button_submit" />
					<input type="hidden" name="confirmation" value="1" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

// Below is the template for adding/editing an board on the forum.
function template_modify_board()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// The main table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=board2" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="boardid" value="', $context['board']['id'], '" />
			<div class="cat_bar">
				<h3 class="catbg">
					', isset($context['board']['is_new']) ? $txt['mboards_new_board_name'] : $txt['boardsEdit'], '
				</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="settings">';

	// Option for choosing the category the board lives in.
	echo '

						<dt>
							<strong>', $txt['mboards_category'], ':</strong>

						</dt>
						<dd>
							<select name="new_cat" onchange="if (this.form.order) {this.form.order.disabled = this.options[this.selectedIndex].value != 0; this.form.board_order.disabled = this.options[this.selectedIndex].value != 0 || this.form.order.options[this.form.order.selectedIndex].value == \'\';}">';
		foreach ($context['categories'] as $category)
			echo '
								<option', $category['selected'] ? ' selected="selected"' : '', ' value="', $category['id'], '">', $category['name'], '</option>';
		echo '
							</select>
						</dd>';

	// If this isn't the only board in this category let the user choose where the board is to live.
	if ((isset($context['board']['is_new']) && count($context['board_order']) > 0) || count($context['board_order']) > 1)
	{
		echo '
						<dt>
							<strong>', $txt['order'], ':</strong>
						</dt>
						<dd>';

	// The first select box gives the user the option to position it before, after or as a child of another board.
	echo '
							<select id="order" name="placement" onchange="this.form.board_order.disabled = this.options[this.selectedIndex].value == \'\';">
								', !isset($context['board']['is_new']) ? '<option value="">(' . $txt['mboards_unchanged'] . ')</option>' : '', '
								<option value="after">' . $txt['mboards_order_after'] . '...</option>
								<option value="child">' . $txt['mboards_order_child_of'] . '...</option>
								<option value="before">' . $txt['mboards_order_before'] . '...</option>
							</select>';

	// The second select box lists all the boards in the category.
	echo '
							<select id="board_order" name="board_order" ', isset($context['board']['is_new']) ? '' : 'disabled="disabled"', '>
								', !isset($context['board']['is_new']) ? '<option value="">(' . $txt['mboards_unchanged'] . ')</option>' : '';
	foreach ($context['board_order'] as $order)
		echo '
								<option', $order['selected'] ? ' selected="selected"' : '', ' value="', $order['id'], '">', $order['name'], '</option>';
	echo '
							</select>
						</dd>';
	}

	// Options for board name and description.
	echo '
						<dt>
							<strong>', $txt['full_name'], ':</strong><br />
							<span class="smalltext">', $txt['name_on_display'], '</span>
						</dt>
						<dd>
							<input type="text" name="board_name" value="', $context['board']['name'], '" size="30" class="input_text" />
						</dd>
						<dt>
							<strong>', $txt['mboards_description'], ':</strong><br />
							<span class="smalltext">', $txt['mboards_description_desc'], '</span>
						</dt>
						<dd>
							<textarea name="desc" rows="3" cols="35" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 99%; min-width: 99%' : 'width: 99%') . ';">', $context['board']['description'], '</textarea>
						</dd>
						<dt>
							<strong>', $txt['permission_profile'], ':</strong><br />
							<span class="smalltext">', $context['can_manage_permissions'] ? sprintf($txt['permission_profile_desc'], $scripturl . '?action=admin;area=permissions;sa=profiles;' . $context['session_var'] . '=' . $context['session_id']) : strip_tags($txt['permission_profile_desc']), '</span>
						</dt>
						<dd>
							<select name="profile">';

	if (isset($context['board']['is_new']))
		echo '
								<option value="-1">[', $txt['permission_profile_inherit'], ']</option>';

	foreach ($context['profiles'] as $id => $profile)
		echo '
								<option value="', $id, '" ', $id == $context['board']['profile'] ? 'selected="selected"' : '', '>', $profile['name'], '</option>';

	echo '
							</select>
						</dd>
						<dt>
							<strong>', $txt['mboards_groups'], ':</strong><br />
							<span class="smalltext">', $txt['mboards_groups_desc'], '</span>
						</dt>
						<dd>';

	// List all the membergroups so the user can choose who may access this board.
	foreach ($context['groups'] as $group)
		echo '
							<label for="groups_', $group['id'], '">
								<input type="checkbox" name="groups[]" value="', $group['id'], '" id="groups_', $group['id'], '"', $group['checked'] ? ' checked="checked"' : '', ' class="input_check" />
								<span', $group['is_post_group'] ? ' class="post_group" title="' . $txt['mboards_groups_post_group'] . '"' : '', $group['id'] == 0 ? ' class="regular_members" title="' . $txt['mboards_groups_regular_members'] . '"' : '', '>
									', $group['name'], '
								</span>
							</label><br />';
	echo '
							<em>', $txt['check_all'], '</em> <input type="checkbox" class="input_check" onclick="invertAll(this, this.form, \'groups[]\');" /><br />
							<br />
						</dd>';

	// Options to choose moderators, specifiy as announcement board and choose whether to count posts here.
	echo '
						<dt>
							<strong>', $txt['mboards_moderators'], ':</strong><br />
							<span class="smalltext">', $txt['mboards_moderators_desc'], '</span><br />
						</dt>
						<dd>
							<input type="text" name="moderators" id="moderators" value="', $context['board']['moderator_list'], '" size="30" class="input_text" />
							<div id="moderator_container"></div>
						</dd>
					</dl>
					<hr class="hrcolor" />';

	if (empty($context['board']['is_recycle']) && empty($context['board']['topics']))
		echo '
					<dl class="settings">
						<dt>
							<strong', $context['board']['topics'] ? ' style="color: gray;"' : '', '>', $txt['mboards_redirect'], ':</strong><br />
							<span class="smalltext">', $txt['mboards_redirect_desc'], '</span><br />
						</dt>
						<dd>
							<input type="checkbox" id="redirect_enable" name="redirect_enable"', $context['board']['topics'] ? ' disabled="disabled"' : '', $context['board']['redirect'] != '' ? ' checked="checked"' : '', ' onclick="refreshOptions();" class="input_check" />
						</dd>
					</dl>';

	if (!empty($context['board']['is_recycle']))
		echo '
					<div class="information">', $txt['mboards_redirect_disabled_recycle'], '</div>';

	if (empty($context['board']['is_recycle']) && !empty($context['board']['topics']))
		echo '
					<div class="information">
						<strong>', $txt['mboards_redirect'],'</strong><br />
						', $txt['mboards_redirect_disabled'], '
					</div>';

	if (!$context['board']['topics'] && empty($context['board']['is_recycle']))
	{
		echo '
					<div id="redirect_address_div">
						<dl class="settings">
							<dt>
								<strong>', $txt['mboards_redirect_url'], ':</strong><br />
								<span class="smalltext">', $txt['mboards_redirect_url_desc'], '</span><br />
							</dt>
							<dd>
								<input type="text" name="redirect_address" value="', $context['board']['redirect'], '" size="40" class="input_text" />
							</dd>
						</dl>
					</div>';

		if ($context['board']['redirect'])
			echo '
					<div id="reset_redirect_div">
						<dl class="settings">
							<dt>
								<strong>', $txt['mboards_redirect_reset'], ':</strong><br />
								<span class="smalltext">', $txt['mboards_redirect_reset_desc'], '</span><br />
							</dt>
							<dd>
								<input type="checkbox" name="reset_redirect" class="input_check" />
								<em>(', sprintf($txt['mboards_current_redirects'], $context['board']['posts']), ')</em>
							</dd>
						</dl>
					</div>';
	}

	echo '
					<div id="count_posts_div">
						<dl class="settings">
							<dt>
								<strong>', $txt['mboards_count_posts'], ':</strong><br />
								<span class="smalltext">', $txt['mboards_count_posts_desc'], '</span><br />
							</dt>
							<dd>
								<input type="checkbox" name="count" ', $context['board']['count_posts'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
						</dl>
					</div>';

	// Here the user can choose to force this board to use a theme other than the default theme for the forum.
	echo '
					<div id="board_theme_div">
						<dl class="settings">
							<dt>
								<strong>', $txt['mboards_theme'], ':</strong><br />
								<span class="smalltext">', $txt['mboards_theme_desc'], '</span><br />
							</dt>
							<dd>
								<select name="boardtheme" id="boardtheme" onchange="refreshOptions();">
									<option value="0"', $context['board']['theme'] == 0 ? ' selected="selected"' : '', '>', $txt['mboards_theme_default'], '</option>';

	foreach ($context['themes'] as $theme)
		echo '
									<option value="', $theme['id'], '"', $context['board']['theme'] == $theme['id'] ? ' selected="selected"' : '', '>', $theme['name'], '</option>';

	echo '
								</select>
							</dd>
						</dl>
					</div>
					<div id="override_theme_div">
						<dl class="settings">
							<dt>
								<strong>', $txt['mboards_override_theme'], ':</strong><br />
								<span class="smalltext">', $txt['mboards_override_theme_desc'], '</span><br />
							</dt>
							<dd>
								<input type="checkbox" name="override_theme"', $context['board']['override_theme'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
						</dl>
					</div>';

	if (!empty($context['board']['is_recycle']))
		echo '<div class="information">', $txt['mboards_recycle_disabled_delete'], '</div>';

	echo '
					<input type="hidden" name="rid" value="', $context['redirect_location'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	// If this board has no children don't bother with the next confirmation screen.
	if ($context['board']['no_children'])
		echo '
					<input type="hidden" name="no_children" value="1" />';

	if (isset($context['board']['is_new']))
		echo '
					<input type="hidden" name="cur_cat" value="', $context['board']['category'], '" />
					<input type="submit" name="add" value="', $txt['mboards_new_board'], '" onclick="return !isEmptyText(this.form.board_name);" class="button_submit" />';
	else
		echo '
					<input type="submit" name="edit" value="', $txt['modify'], '" onclick="return !isEmptyText(this.form.board_name);" class="button_submit" />';

	if (!isset($context['board']['is_new']) && empty($context['board']['is_recycle']))
		echo '
					<span', $context['board']['is_recycle'] ? ' style="visibility:hidden">' : '>', '<input type="submit" name="delete" value="', $txt['mboards_delete_board'], '" onclick="return confirm(\'', $txt['boardConfirm'], '\');"', ' class="button_submit" /></span>';
	echo '
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />
<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
<script type="text/javascript"><!-- // --><![CDATA[
	var oModeratorSuggest = new smc_AutoSuggest({
		sSelf: \'oModeratorSuggest\',
		sSessionId: \'', $context['session_id'], '\',
		sSessionVar: \'', $context['session_var'], '\',
		sSuggestId: \'moderators\',
		sControlId: \'moderators\',
		sSearchType: \'member\',
		bItemList: true,
		sPostName: \'moderator_list\',
		sURLMask: \'action=profile;u=%item_id%\',
		sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
		sItemListContainerId: \'moderator_container\',
		aListItems: [';

	foreach ($context['board']['moderators'] as $id_member => $member_name)
		echo '
					{
						sItemId: ', JavaScriptEscape($id_member), ',
						sItemName: ', JavaScriptEscape($member_name), '
					}', $id_member == $context['board']['last_moderator_id'] ? '' : ',';

	echo '
		]
	});
// ]]></script>';

	// Javascript for deciding what to show.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		function refreshOptions()
		{
			var redirect = document.getElementById("redirect_enable");
			var redirectEnabled = redirect ? redirect.checked : false;
			var nonDefaultTheme = document.getElementById("boardtheme").value == 0 ? false : true;

			// What to show?
			document.getElementById("override_theme_div").style.display = redirectEnabled || !nonDefaultTheme ? "none" : "";
			document.getElementById("board_theme_div").style.display = redirectEnabled ? "none" : "";
			document.getElementById("count_posts_div").style.display = redirectEnabled ? "none" : "";';

	if (!$context['board']['topics'] && empty($context['board']['is_recycle']))
	{
		echo '
			document.getElementById("redirect_address_div").style.display = redirectEnabled ? "" : "none";';

		if ($context['board']['redirect'])
			echo '
			document.getElementById("reset_redirect_div").style.display = redirectEnabled ? "" : "none";';
	}

	echo '
		}
		refreshOptions();
	// ]]></script>';
}

// A template used when a user is deleting a board with child boards in it - to see what they want to do with them.
function template_confirm_board_delete()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=board2" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="boardid" value="', $context['board']['id'], '" />

			<div class="cat_bar">
				<h3 class="catbg">', $txt['mboards_delete_board'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>', $txt['mboards_delete_board_contains'], '</p>
						<ul>';

	foreach ($context['children'] as $child)
		echo '
							<li>', $child['node']['name'], '</li>';

	echo '
						</ul>
				</div>
				<span class="botslice"><span></span></span>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mboards_delete_what_do'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>
						<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" class="input_radio" checked="checked" />', $txt['mboards_delete_board_option1'], '</label><br />
						<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1" class="input_radio"', empty($context['can_move_children']) ? ' disabled="disabled"' : '', ' />', $txt['mboards_delete_board_option2'], '</label>:
						<select name="board_to" ', empty($context['can_move_children']) ? 'disabled="disabled"' : '', '>';

	foreach ($context['board_order'] as $board)
		if ($board['id'] != $context['board']['id'] && empty($board['is_child']))
			echo '
							<option value="', $board['id'], '">', $board['name'], '</option>';

	echo '
						</select>
					</p>
					<input type="submit" name="delete" value="', $txt['mboards_delete_confirm'], '" class="button_submit" />
					<input type="submit" name="cancel" value="', $txt['mboards_delete_cancel'], '" class="button_submit" />
					<input type="hidden" name="confirmation" value="1" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

?>