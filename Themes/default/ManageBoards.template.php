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
use SMF\Theme;
use SMF\Utils;

/**
 * Template for listing all the current categories and boards.
 */
function template_main()
{
	// Table header.
	echo '
	<div id="manage_boards">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['boards_edit'], '</h3>
		</div>
		<div class="windowbg">';

	if (!empty(Utils::$context['move_board']))
		echo '
			<div class="noticebox">
				', Utils::$context['move_title'], ' [<a href="', Config::$scripturl, '?action=admin;area=manageboards">', Lang::$txt['mboards_cancel_moving'], '</a>]', '
			</div>';

	// No categories so show a label.
	if (empty(Utils::$context['categories']))
		echo '
			<div class="windowbg centertext">
				', Lang::$txt['mboards_no_cats'], '
			</div>';

	// Loop through every category, listing the boards in each as we go.
	foreach (Utils::$context['categories'] as $category)
	{
		// Link to modify the category.
		echo '
			<div class="sub_bar">
				<h3 class="subbg">
					<a href="', Config::$scripturl, '?action=admin;area=manageboards;sa=cat;cat=', $category['id'], '">', $category['name'], '</a> <a href="', Config::$scripturl, '?action=admin;area=manageboards;sa=cat;cat=', $category['id'], '">', Lang::$txt['cat_modify'], '</a>
				</h3>
			</div>';

		// Boards table header.
		echo '
			<form action="', Config::$scripturl, '?action=admin;area=manageboards;sa=newboard;cat=', $category['id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
				<ul id="category_', $category['id'], '" class="nolist">';

		if (!empty($category['move_link']))
			echo '
					<li><a href="', $category['move_link']['href'], '" title="', $category['move_link']['label'], '"><span class="main_icons select_above"></span></a></li>';

		$recycle_board = '<a href="' . Config::$scripturl . '?action=admin;area=manageboards;sa=settings"> <img src="' . Theme::$current->settings['images_url'] . '/post/recycled.png" alt="' . Lang::$txt['recycle_board'] . '" title="' . Lang::$txt['recycle_board'] . '"></a>';
		$redirect_board = '<img src="' . Theme::$current->settings['images_url'] . '/new_redirect.png" alt="' . Lang::$txt['redirect_board_desc'] . '" title="' . Lang::$txt['redirect_board_desc'] . '">';

		// List through every board in the category, printing its name and link to modify the board.
		foreach ($category['boards'] as $board)
		{
			echo '
					<li', !empty(Config::$modSettings['recycle_board']) && !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $board['id'] ? ' id="recycle_board"' : ' ', ' class="windowbg', $board['is_redirect'] ? ' redirect_board' : '', '" style="padding-' . (Utils::$context['right_to_left'] ? 'right' : 'left') . ': ', 5 + 30 * $board['child_level'], 'px;">
						<span class="floatleft"><a', $board['move'] ? ' class="red"' : '', ' href="', Config::$scripturl, '?board=', $board['id'], '.0">', $board['name'], '</a>', !empty(Config::$modSettings['recycle_board']) && !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] == $board['id'] ? $recycle_board : '', $board['is_redirect'] ? $redirect_board : '', '</span>
						<span class="floatright">
							', Utils::$context['can_manage_permissions'] ? '<a href="' . Config::$scripturl . '?action=admin;area=permissions;sa=index;pid=' . $board['permission_profile'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" class="button">' . Lang::$txt['mboards_permissions'] . '</a>' : '', '
							<a href="', Config::$scripturl, '?action=admin;area=manageboards;move=', $board['id'], '" class="button">', Lang::$txt['mboards_move'], '</a>
							<a href="', Config::$scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], '" class="button">', Lang::$txt['mboards_modify'], '</a>
						</span><br style="clear: right;">
					</li>';

			if (!empty($board['move_links']))
			{
				echo '
					<li class="windowbg" style="padding-', Utils::$context['right_to_left'] ? 'right' : 'left', ': ', 5 + 30 * $board['move_links'][0]['child_level'], 'px;">';

				foreach ($board['move_links'] as $link)
					echo '
						<a href="', $link['href'], '" class="move_links" title="', $link['label'], '"><span class="main_icons select_', $link['class'], '" title="', $link['label'], '"></span></a>';

				echo '
					</li>';
			}
		}

		// Button to add a new board.
		echo '
				</ul>
				<input type="submit" value="', Lang::$txt['mboards_new_board'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</form>';
	}

	echo '
		</div><!-- .windowbg -->
	</div><!-- #manage_boards -->';
}

/**
 * Template for editing/adding a category on the forum.
 */
function template_modify_category()
{
	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', Config::$scripturl, '?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="hidden" name="cat" value="', Utils::$context['category']['id'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', isset(Utils::$context['category']['is_new']) ? Lang::$txt['mboards_new_cat_name'] : Lang::$txt['cat_edit'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// If this isn't the only category, let the user choose where this category should be positioned down the board index.
	if (count(Utils::$context['category_order']) > 1)
	{
		echo '
					<dt><strong>', Lang::$txt['order'], ':</strong></dt>
					<dd>
						<select name="cat_order">';

		// Print every existing category into a select box.
		foreach (Utils::$context['category_order'] as $order)
			echo '
							<option', $order['selected'] ? ' selected' : '', ' value="', $order['id'], '">', $order['name'], '</option>';
		echo '
						</select>
					</dd>';
	}

	// Allow the user to edit the category name and/or choose whether you can collapse the category.
	echo '
					<dt>
						<strong>', Lang::$txt['full_name'], ':</strong><br>
						<span class="smalltext">', Lang::$txt['name_on_display'], '</span>
					</dt>
					<dd>
						<input type="text" name="cat_name" value="', Utils::$context['category']['editable_name'], '" size="30" tabindex="', Utils::$context['tabindex']++, '">
					</dd>
					<dt>
						<strong>', Lang::$txt['mboards_description'], '</strong><br>
						<span class="smalltext">', str_replace('{allowed_tags}', implode(', ', Utils::$context['description_allowed_tags']), Lang::$txt['mboards_cat_description_desc']), '</span>
					</dt>
					<dd>
						<textarea name="cat_desc" rows="3" cols="35">', Utils::$context['category']['description'], '</textarea>
					</dd>
					<dt>
						<strong>', Lang::$txt['collapse_enable'], '</strong><br>
						<span class="smalltext">', Lang::$txt['collapse_desc'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="collapse"', Utils::$context['category']['can_collapse'] ? ' checked' : '', ' tabindex="', Utils::$context['tabindex']++, '">
					</dd>';

	// Show any category settings added by mods using the 'integrate_edit_category' hook.
	if (!empty(Utils::$context['custom_category_settings']) && is_array(Utils::$context['custom_category_settings']))
	{
		foreach (Utils::$context['custom_category_settings'] as $catset_id => $catset)
		{
			if (!empty($catset['dt']) && !empty($catset['dd']))
				echo '
					<dt class="clear', !is_numeric($catset_id) ? ' catset_' . $catset_id : '', '">
						', $catset['dt'], '
					</dt>
					<dd', !is_numeric($catset_id) ? ' class="catset_' . $catset_id . '"' : '', '>
						', $catset['dd'], '
					</dd>';
		}
	}

	// Table footer.
	echo '
				</dl>';

	if (isset(Utils::$context['category']['is_new']))
		echo '
				<input type="submit" name="add" value="', Lang::$txt['mboards_add_cat_button'], '" onclick="return !isEmptyText(this.form.cat_name);" tabindex="', Utils::$context['tabindex']++, '" class="button">';
	else
		echo '
				<input type="submit" name="edit" value="', Lang::$txt['modify'], '" onclick="return !isEmptyText(this.form.cat_name);" tabindex="', Utils::$context['tabindex']++, '" class="button">
				<input type="submit" name="delete" value="', Lang::$txt['mboards_delete_cat'], '" data-confirm="', Lang::$txt['cat_delete_confirm'], '" class="button you_sure">';
	echo '
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">';

	// If this category is empty we don't bother with the next confirmation screen.
	if (Utils::$context['category']['is_empty'])
		echo '
				<input type="hidden" name="empty" value="1">';

	echo '
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->';
}

/**
 * A template to confirm if a user wishes to delete a category - and whether they want to save the boards.
 */
function template_confirm_category_delete()
{
	// Print table header.
	echo '
	<div id="manage_boards" class="roundframe">
		<form action="', Config::$scripturl, '?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="hidden" name="cat" value="', Utils::$context['category']['id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mboards_delete_cat'], '</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::$txt['mboards_delete_cat_contains'], ':</p>
				<ul>';

	foreach (Utils::$context['category']['children'] as $child)
		echo '
					<li>', $child, '</li>';

	echo '
				</ul>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mboards_delete_what_do'], '</h3>
			</div>
			<div class="windowbg">
				<p>
					<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" checked>', Lang::$txt['mboards_delete_option1'], '</label><br>
					<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1"', count(Utils::$context['category_order']) == 1 ? ' disabled' : '', '>', Lang::$txt['mboards_delete_option2'], '</label>:
					<select name="cat_to"', count(Utils::$context['category_order']) == 1 ? ' disabled' : '', '>';

	foreach (Utils::$context['category_order'] as $cat)
		if ($cat['id'] != 0)
			echo '
						<option value="', $cat['id'], '">', $cat['true_name'], '</option>';

	echo '
					</select>
				</p>
				<input type="submit" name="delete" value="', Lang::$txt['mboards_delete_confirm'], '" class="button">
				<input type="submit" name="cancel" value="', Lang::$txt['mboards_delete_cancel'], '" class="button">
				<input type="hidden" name="confirmation" value="1">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context[Utils::$context['token_check'] . '_token_var'], '" value="', Utils::$context[Utils::$context['token_check'] . '_token'], '">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->';
}

/**
 * Below is the template for adding/editing a board on the forum.
 */
function template_modify_board()
{
	// The main table header.
	echo '
	<div id="manage_boards">
		<form action="', Config::$scripturl, '?action=admin;area=manageboards;sa=board2" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="hidden" name="boardid" value="', Utils::$context['board']['id'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', isset(Utils::$context['board']['is_new']) ? Lang::$txt['mboards_new_board_name'] : Lang::$txt['boards_edit'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Option for choosing the category the board lives in.
	echo '
					<dt>
						<strong>', Lang::$txt['mboards_category'], ':</strong>
					</dt>
					<dd>
						<select name="new_cat" onchange="if (this.form.order) {this.form.order.disabled = this.options[this.selectedIndex].value != 0; this.form.board_order.disabled = this.options[this.selectedIndex].value != 0 || this.form.order.options[this.form.order.selectedIndex].value == \'\';}">';

	foreach (Utils::$context['categories'] as $category)
		echo '
							<option', $category['selected'] ? ' selected' : '', ' value="', $category['id'], '">', $category['name'], '</option>';
	echo '
						</select>
					</dd>';

	// If this isn't the only board in this category let the user choose where the board is to live.
	if ((isset(Utils::$context['board']['is_new']) && count(Utils::$context['board_order']) > 0) || count(Utils::$context['board_order']) > 1)
	{
		echo '
					<dt>
						<strong>', Lang::$txt['order'], ':</strong>
					</dt>
					<dd>';

		// The first select box gives the user the option to position it before, after or as a child of another board.
		echo '
						<select id="order" name="placement" onchange="this.form.board_order.disabled = this.options[this.selectedIndex].value == \'\';">
							', !isset(Utils::$context['board']['is_new']) ? '<option value="">(' . Lang::$txt['mboards_unchanged'] . ')</option>' : '', '
							<option value="after">' . Lang::$txt['mboards_order_after'] . '...</option>
							<option value="child">' . Lang::$txt['mboards_order_child_of'] . '...</option>
							<option value="before">' . Lang::$txt['mboards_order_before'] . '...</option>
						</select>';

		// The second select box lists all the boards in the category.
		echo '
						<select id="board_order" name="board_order"', !isset(Utils::$context['board']['is_new']) ? ' disabled' : '', '>
							', !isset(Utils::$context['board']['is_new']) ? '<option value="">(' . Lang::$txt['mboards_unchanged'] . ')</option>' : '';

		foreach (Utils::$context['board_order'] as $order)
			echo '
							<option', $order['selected'] ? ' selected' : '', ' value="', $order['id'], '">', $order['name'], '</option>';
		echo '
						</select>
					</dd>';
	}

	// Options for board name and description.
	echo '
					<dt>
						<strong>', Lang::$txt['full_name'], ':</strong><br>
						<span class="smalltext">', Lang::$txt['name_on_display'], '</span>
					</dt>
					<dd>
						<input type="text" name="board_name" value="', Utils::$context['board']['name'], '" size="30">
					</dd>
					<dt>
						<strong>', Lang::$txt['mboards_description'], ':</strong><br>
						<span class="smalltext">', str_replace('{allowed_tags}', implode(', ', Utils::$context['description_allowed_tags']), Lang::$txt['mboards_description_desc']), '</span>
					</dt>
					<dd>
						<textarea name="desc" rows="3" cols="35">', Utils::$context['board']['description'], '</textarea>
					</dd>
					<dt>
						<strong>', Lang::$txt['permission_profile'], ':</strong><br>
						<span class="smalltext">', Utils::$context['can_manage_permissions'] ? sprintf(Lang::$txt['permission_profile_desc'], Config::$scripturl . '?action=admin;area=permissions;sa=profiles;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']) : strip_tags(Lang::$txt['permission_profile_desc']), '</span>
					</dt>
					<dd>
						<select name="profile">';

	if (isset(Utils::$context['board']['is_new']))
		echo '
							<option value="-1">[', Lang::$txt['permission_profile_inherit'], ']</option>';

	foreach (Utils::$context['profiles'] as $id => $profile)
		echo '
							<option value="', $id, '"', $id == Utils::$context['board']['profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<strong>', Lang::$txt['mboards_groups'], ':</strong><br>
						<span class="smalltext">', empty(Config::$modSettings['deny_boards_access']) ? Lang::$txt['mboards_groups_desc'] : Lang::$txt['boardsaccess_option_desc'], '</span>';

	echo '
					</dt>
					<dd>';

	if (!empty(Config::$modSettings['deny_boards_access']))
		echo '
						<table>
							<tr>
								<td></td>
								<th>', Lang::$txt['permissions_option_on'], '</th>
								<th>', Lang::$txt['permissions_option_off'], '</th>
								<th>', Lang::$txt['permissions_option_deny'], '</th>
							</tr>';

	// List all the membergroups so the user can choose who may access this board.
	foreach (Utils::$context['groups'] as $group)
		if (empty(Config::$modSettings['deny_boards_access']))
			echo '
						<label for="groups_', $group['id'], '">
							<input type="checkbox" name="groups[', $group['id'], ']" value="allow" id="groups_', $group['id'], '"', in_array($group['id'], Utils::$context['board_managers']) ? ' checked disabled' : ($group['allow'] ? ' checked' : ''), '>
							<span', $group['is_post_group'] ? ' class="post_group" title="' . Lang::$txt['mboards_groups_post_group'] . '"' : ($group['id'] == 0 ? ' class="regular_members" title="' . Lang::$txt['mboards_groups_regular_members'] . '"' : ''), '>
								', $group['name'], '
							</span>
						</label><br>';
		else
			echo '
							<tr>
								<td>
									<label for="groups_', $group['id'], '_a">
										<span', $group['is_post_group'] ? ' class="post_group" title="' . Lang::$txt['mboards_groups_post_group'] . '"' : ($group['id'] == 0 ? ' class="regular_members" title="' . Lang::$txt['mboards_groups_regular_members'] . '"' : ''), '>
											', $group['name'], '
										</span>
									</label>
								</td>
								<td>
									<input type="radio" name="groups[', $group['id'], ']" value="allow" id="groups_', $group['id'], '_a"', in_array($group['id'], Utils::$context['board_managers']) ? ' checked disabled' : ($group['allow'] ? ' checked' : ''), '>
								</td>
								<td>
									<input type="radio" name="groups[', $group['id'], ']" value="ignore" id="groups_', $group['id'], '_x"', in_array($group['id'], Utils::$context['board_managers']) ? ' disabled' : (!$group['allow'] && !$group['deny'] ? ' checked' : ''), '>
								</td>
								<td>
									<input type="radio" name="groups[', $group['id'], ']" value="deny" id="groups_', $group['id'], '_d"', in_array($group['id'], Utils::$context['board_managers']) ? ' disabled' : ($group['deny'] ? ' checked' : ''), '>
								</td>
								<td></td>
							</tr>';

	if (empty(Config::$modSettings['deny_boards_access']))
		echo '
						<span class="select_all_box">
							<em>', Lang::$txt['check_all'], '</em> <input type="checkbox" onclick="invertAll(this, this.form, \'groups[\');">
						</span>
						<br><br>
					</dd>';
	else
		echo '
							<tr class="select_all_box">
								<td>
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, \'groups\', \'allow\');">
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, \'groups\', \'ignore\');">
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, \'groups\', \'deny\');">
								</td>
								<td>
									<em>', Lang::$txt['check_all'], '</em>
								</td>
							</tr>
						</table>
					</dd>';

	// Options to choose moderators, specify as announcement board and choose whether to count posts here.
	echo '
					<dt>
						<strong>', Lang::$txt['mboards_moderators'], ':</strong><br>
						<span class="smalltext">', Lang::$txt['mboards_moderators_desc'], '</span><br>
					</dt>
					<dd>
						<input type="text" name="moderators" id="moderators" value="', Utils::$context['board']['moderator_list'], '" size="30">
						<div id="moderator_container"></div>
					</dd>
					<dt>
						<strong>', Lang::$txt['mboards_moderator_groups'], ':</strong><br>
						<span class="smalltext">', Lang::$txt['mboards_moderator_groups_desc'], '</span><br>
					</dt>
					<dd>
						<input type="text" name="moderator_groups" id="moderator_groups" value="', Utils::$context['board']['moderator_groups_list'], '" size="30">
						<div id="moderator_group_container"></div>
					</dd>
				</dl>
				<script>
					$(document).ready(function () {
						$(".select_all_box").each(function () {
							$(this).removeClass(\'select_all_box\');
						});
					});
				</script>
				<hr>';

	if (empty(Utils::$context['board']['is_recycle']) && empty(Utils::$context['board']['topics']))
	{
		echo '
				<dl class="settings">
					<dt>
						<strong', Utils::$context['board']['topics'] ? ' style="color: gray;"' : '', '>', Lang::$txt['mboards_redirect'], ':</strong><br>
						<span class="smalltext">', Lang::$txt['mboards_redirect_desc'], '</span><br>
					</dt>
					<dd>
						<input type="checkbox" id="redirect_enable" name="redirect_enable"', Utils::$context['board']['redirect'] != '' ? ' checked' : '', ' onclick="refreshOptions();">
					</dd>
				</dl>

				<div id="redirect_address_div">
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['mboards_redirect_url'], ':</strong><br>
							<span class="smalltext">', Lang::$txt['mboards_redirect_url_desc'], '</span><br>
						</dt>
						<dd>
							<input type="text" name="redirect_address" value="', Utils::$context['board']['redirect'], '" size="40">
						</dd>
					</dl>
				</div>';

		if (Utils::$context['board']['redirect'])
			echo '
				<div id="reset_redirect_div">
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['mboards_redirect_reset'], ':</strong><br>
							<span class="smalltext">', Lang::$txt['mboards_redirect_reset_desc'], '</span><br>
						</dt>
						<dd>
							<input type="checkbox" name="reset_redirect">
							<em>(', sprintf(Lang::$txt['mboards_current_redirects'], Utils::$context['board']['posts']), ')</em>
						</dd>
					</dl>
				</div>';
	}

	echo '
				<div id="count_posts_div">
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['mboards_count_posts'], ':</strong><br>
							<span class="smalltext">', Lang::$txt['mboards_count_posts_desc'], '</span><br>
						</dt>
						<dd>
							<input type="checkbox" name="count"', Utils::$context['board']['count_posts'] ? ' checked' : '', '>
						</dd>
					</dl>
				</div>';

	// Here the user can choose to force this board to use a theme other than the default theme for the forum.
	echo '
				<div id="board_theme_div">
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['mboards_theme'], ':</strong><br>
							<span class="smalltext">', Lang::$txt['mboards_theme_desc'], '</span><br>
						</dt>
						<dd>
							<select name="boardtheme" id="boardtheme" onchange="refreshOptions();">
								<option value="0"', Utils::$context['board']['theme'] == 0 ? ' selected' : '', '>', Lang::$txt['mboards_theme_default'], '</option>';

	foreach (Utils::$context['themes'] as $theme)
		echo '
									<option value="', $theme['id'], '"', Utils::$context['board']['theme'] == $theme['id'] ? ' selected' : '', '>', $theme['name'], '</option>';

	echo '
							</select>
						</dd>
					</dl>
				</div><!-- #board_theme_div -->
				<div id="override_theme_div">
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['mboards_override_theme'], ':</strong><br>
							<span class="smalltext">', Lang::$txt['mboards_override_theme_desc'], '</span><br>
						</dt>
						<dd>
							<input type="checkbox" name="override_theme"', Utils::$context['board']['override_theme'] ? ' checked' : '', '>
						</dd>
					</dl>
				</div>';

	// Show any board settings added by mods using the 'integrate_edit_board' hook.
	if (!empty(Utils::$context['custom_board_settings']) && is_array(Utils::$context['custom_board_settings']))
	{
		echo '
				<hr>
				<div id="custom_board_settings">
					<dl class="settings">';

		foreach (Utils::$context['custom_board_settings'] as $cbs_id => $cbs)
		{
			if (!empty($cbs['dt']) && !empty($cbs['dd']))
				echo '
						<dt class="clear', !is_numeric($cbs_id) ? ' cbs_' . $cbs_id : '', '">
							', $cbs['dt'], '
						</dt>
						<dd', !is_numeric($cbs_id) ? ' class="cbs_' . $cbs_id . '"' : '', '>
							', $cbs['dd'], '
						</dd>';
		}

		echo '
					</dl>
				</div>';
	}

	if (!empty(Utils::$context['board']['is_recycle']))
		echo '
				<div class="noticebox">', Lang::$txt['mboards_recycle_disabled_delete'], '</div>';

	echo '
				<input type="hidden" name="rid" value="', Utils::$context['redirect_location'], '">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token_var'], '" value="', Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token'], '">';

	// If this board has no children don't bother with the next confirmation screen.
	if (Utils::$context['board']['no_children'])
		echo '
				<input type="hidden" name="no_children" value="1">';

	if (isset(Utils::$context['board']['is_new']))
		echo '
				<input type="hidden" name="cur_cat" value="', Utils::$context['board']['category'], '">
				<input type="submit" name="add" value="', Lang::$txt['mboards_new_board'], '" onclick="return !isEmptyText(this.form.board_name);" class="button">';
	else
		echo '
				<input type="submit" name="edit" value="', Lang::$txt['modify'], '" onclick="return !isEmptyText(this.form.board_name);" class="button">';

	if (!isset(Utils::$context['board']['is_new']) && empty(Utils::$context['board']['is_recycle']))
		echo '
				<input type="submit" name="delete" value="', Lang::$txt['mboards_delete_board'], '" data-confirm="', Lang::$txt['board_delete_confirm'], '" class="button you_sure">';
	echo '
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->

	<script>
		var oModeratorSuggest = new smc_AutoSuggest({
			sSelf: \'oModeratorSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'moderators\',
			sControlId: \'moderators\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'moderator_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			sItemListContainerId: \'moderator_container\',
			aListItems: [';

	foreach (Utils::$context['board']['moderators'] as $id_member => $member_name)
		echo '
				{
					sItemId: ', Utils::JavaScriptEscape($id_member), ',
					sItemName: ', Utils::JavaScriptEscape($member_name), '
				}', $id_member == Utils::$context['board']['last_moderator_id'] ? '' : ',';

	echo '
			]
		});

		var oModeratorGroupSuggest = new smc_AutoSuggest({
			sSelf: \'oModeratorGroupSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'moderator_groups\',
			sControlId: \'moderator_groups\',
			sSearchType: \'membergroups\',
			bItemList: true,
			sPostName: \'moderator_group_list\',
			sURLMask: \'action=groups;sa=members;group=%item_id%\',
			sTextDeleteItem: \'', Lang::$txt['autosuggest_delete_item'], '\',
			sItemListContainerId: \'moderator_group_container\',
			aListItems: [';

	foreach (Utils::$context['board']['moderator_groups'] as $id_group => $group_name)
		echo '
				{
					sItemId: ', Utils::JavaScriptEscape($id_group), ',
					sItemName: ', Utils::JavaScriptEscape($group_name), '
				}', $id_group == Utils::$context['board']['last_moderator_group_id'] ? '' : ',';

	echo '
			]
		});
	</script>';

	// Javascript for deciding what to show.
	echo '
	<script>
		function refreshOptions()
		{
			var redirect = document.getElementById("redirect_enable");
			var redirectEnabled = redirect ? redirect.checked : false;
			var nonDefaultTheme = document.getElementById("boardtheme").value == 0 ? false : true;

			// What to show?

			if(redirectEnabled || !nonDefaultTheme)
				document.getElementById("override_theme_div").classList.add(\'hidden\');
			else
				document.getElementById("override_theme_div").classList.remove(\'hidden\');

			if(redirectEnabled) {
				document.getElementById("board_theme_div").classList.add(\'hidden\');
				document.getElementById("count_posts_div").classList.add(\'hidden\');
			} else {
				document.getElementById("board_theme_div").classList.remove(\'hidden\');
				document.getElementById("count_posts_div").classList.remove(\'hidden\');
			}';

	if (!Utils::$context['board']['topics'] && empty(Utils::$context['board']['is_recycle']))
	{
		echo '
			if(redirectEnabled)
				document.getElementById("redirect_address_div").classList.remove(\'hidden\');
			else
				document.getElementById("redirect_address_div").classList.add(\'hidden\');';

		if (Utils::$context['board']['redirect'])
			echo '
			if(redirectEnabled)
				document.getElementById("reset_redirect_div").classList.remove(\'hidden\');
			else
				document.getElementById("reset_redirect_div").classList.add(\'hidden\');';
	}

	// Include any JavaScript added by mods using the 'integrate_edit_board' hook.
	if (!empty(Utils::$context['custom_refreshOptions']) && is_array(Utils::$context['custom_refreshOptions']))
	{
		foreach (Utils::$context['custom_refreshOptions'] as $refreshOption)
			echo '
			', $refreshOption;
	}

	echo '
		}
		refreshOptions();
	</script>';
}

/**
 * A template used when a user is deleting a board with child boards in it - to see what they want to do with them.
 */
function template_confirm_board_delete()
{
	// Print table header.
	echo '
	<div id="manage_boards" class="roundframe">
		<form action="', Config::$scripturl, '?action=admin;area=manageboards;sa=board2" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="hidden" name="boardid" value="', Utils::$context['board']['id'], '">

			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mboards_delete_board'], '</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::$txt['mboards_delete_board_contains'], '</p>
				<ul>';

	foreach (Utils::$context['children'] as $child)
		echo '
					<li>', $child['node']['name'], '</li>';

	echo '
				</ul>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mboards_delete_what_do'], '</h3>
			</div>
			<div class="windowbg">
				<p>
					<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" checked>', Lang::$txt['mboards_delete_board_option1'], '</label><br>
					<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1"', empty(Utils::$context['can_move_children']) ? ' disabled' : '', '>', Lang::$txt['mboards_delete_board_option2'], '</label>:
					<select name="board_to"', empty(Utils::$context['can_move_children']) ? ' disabled' : '', '>';

	foreach (Utils::$context['board_order'] as $board)
		if ($board['id'] != Utils::$context['board']['id'] && empty($board['is_child']))
			echo '
						<option value="', $board['id'], '">', $board['name'], '</option>';

	echo '
					</select>
				</p>
				<input type="submit" name="delete" value="', Lang::$txt['mboards_delete_confirm'], '" class="button">
				<input type="submit" name="cancel" value="', Lang::$txt['mboards_delete_cancel'], '" class="button">
				<input type="hidden" name="confirmation" value="1">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token_var'], '" value="', Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token'], '">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->';
}

?>