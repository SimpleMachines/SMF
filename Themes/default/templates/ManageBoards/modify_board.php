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
 * Below is the template for adding/editing a board on the forum.
 */
?>

<?php /* The main table header. */ ?>
	<div id="manage_boards">
		<form action="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=board2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="boardid" value="<?= Utils::$context['board']['id'] ?>">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= isset(Utils::$context['board']['is_new']) ? Lang::getTxt('mboards_new_board_name', file: 'ManageBoards') : Lang::getTxt('boards_edit', file: 'Admin') ?>

				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
<?php /* Option for choosing the category the board lives in. */ ?>
					<dt>
						<strong><?= Lang::getTxt('mboards_category', file: 'ManageBoards') ?></strong>
					</dt>
					<dd>
						<select name="new_cat" onchange="if (this.form.order) {this.form.order.disabled = this.options[this.selectedIndex].value != 0; this.form.board_order.disabled = this.options[this.selectedIndex].value != 0 || this.form.order.options[this.form.order.selectedIndex].value == '';}">
<?php foreach (Utils::$context['categories'] as $category): ?>
							<option<?= $category['selected'] ? ' selected' : '' ?> value="<?= $category['id'] ?>"><?= $category['name'] ?></option>
<?php endforeach; ?>
						</select>
					</dd>
<?php /* If this isn't the only board in this category let the user choose where the board is to live. */ ?>
<?php if ((isset(Utils::$context['board']['is_new']) && count(Utils::$context['board_order']) > 0) || count(Utils::$context['board_order']) > 1): ?>
					<dt>
						<strong><?= Lang::getTxt('order', file: 'ManageBoards') ?></strong>
					</dt>
					<dd>
<?php /* The first select box gives the user the option to position it before, after or as a child of another board. */ ?>
						<select id="order" name="placement" onchange="this.form.board_order.disabled = this.options[this.selectedIndex].value == '';">
							<?= !isset(Utils::$context['board']['is_new']) ? '<option value="">(' . Lang::getTxt('mboards_unchanged', file: 'ManageBoards') . ')</option>' : '' ?>

							<option value="after"><?= Lang::getTxt('mboards_order_after', ['name' => '...'], file: 'ManageBoards') ?></option>
							<option value="child"><?= Lang::getTxt('mboards_order_child_of', ['name' => '...'], file: 'ManageBoards') ?></option>
							<option value="before"><?= Lang::getTxt('mboards_order_before', ['name' => '...'], file: 'ManageBoards') ?></option>
						</select>
<?php /* The second select box lists all the boards in the category. */ ?>
						<select id="board_order" name="board_order"<?= !isset(Utils::$context['board']['is_new']) ? ' disabled' : '' ?>>
							<?= !isset(Utils::$context['board']['is_new']) ? '<option value="">(' . Lang::getTxt('mboards_unchanged', file: 'ManageBoards') . ')</option>' : '' ?>

<?php foreach (Utils::$context['board_order'] as $order): ?>
							<option<?= $order['selected'] ? ' selected' : '' ?> value="<?= $order['id'] ?>"><?= $order['name'] ?></option>
<?php endforeach; ?>
						</select>
					</dd>
<?php endif; ?>
<?php /* Options for board name and description. */ ?>
					<dt>
						<strong><?= Lang::getTxt('full_name', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('name_on_display', file: 'ManageBoards') ?></span>
					</dt>
					<dd>
						<input type="text" name="board_name" value="<?= Utils::$context['board']['name'] ?>" size="30">
					</dd>
					<dt>
						<strong><?= Lang::getTxt('mboards_description', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('mboards_description_desc', ['allowed_tags' => implode(', ', Utils::$context['description_allowed_tags'])], file: 'ManageBoards') ?></span>
					</dt>
					<dd>
						<textarea name="desc" rows="3" cols="35"><?= Utils::$context['board']['description'] ?></textarea>
					</dd>
					<dt>
						<strong><?= Lang::getTxt('permission_profile', file: 'ManagePermissions') ?></strong><br>
						<span class="smalltext"><?= Utils::$context['can_manage_permissions'] ? Lang::getTxt('permission_profile_desc', ['url' => Config::$scripturl . '?action=admin;area=permissions;sa=profiles;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']], file: 'ManagePermissions') : strip_tags(Lang::getTxt('permission_profile_desc', file: 'ManagePermissions')) ?></span>
					</dt>
					<dd>
						<select name="profile">
<?php if (isset(Utils::$context['board']['is_new'])): ?>
							<option value="-1">[<?= Lang::getTxt('permission_profile_inherit', file: 'ManagePermissions') ?>]</option>
<?php endif; ?>
<?php foreach (Utils::$context['profiles'] as $id => $profile): ?>
							<option value="<?= $id ?>"<?= $id == Utils::$context['board']['profile'] ? ' selected' : '' ?>><?= $profile['name'] ?></option>
<?php endforeach; ?>
						</select>
					</dd>
					<dt>
						<strong><?= Lang::getTxt('mboards_groups', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt(empty(Config::$modSettings['deny_boards_access']) ? 'mboards_groups_desc' : 'boardsaccess_option_desc', file: 'ManageBoards') ?></span>
					</dt>
					<dd>
<?php if (!empty(Config::$modSettings['deny_boards_access'])): ?>
						<table>
							<tr>
								<td></td>
								<th><?= Lang::getTxt('permissions_option_on', file: 'ManagePermissions') ?></th>
								<th><?= Lang::getTxt('permissions_option_off', file: 'ManagePermissions') ?></th>
								<th><?= Lang::getTxt('permissions_option_deny', file: 'ManagePermissions') ?></th>
							</tr>
<?php endif; ?>
<?php /* List all the membergroups so the user can choose who may access this board. */ ?>
<?php foreach (Utils::$context['groups'] as $group): ?>
<?php if (empty(Config::$modSettings['deny_boards_access'])): ?>
						<label for="groups_<?= $group['id'] ?>">
							<input type="checkbox" name="groups[<?= $group['id'] ?>]" value="allow" id="groups_<?= $group['id'] ?>"<?= in_array($group['id'], Utils::$context['board_managers']) ? ' checked disabled' : ($group['allow'] ? ' checked' : '') ?>>
							<span<?= $group['is_post_group'] ? ' class="post_group" title="' . Lang::getTxt('mboards_groups_post_group', file: 'ManageBoards') . '"' : ($group['id'] == 0 ? ' class="regular_members" title="' . Lang::getTxt('mboards_groups_regular_members', file: 'ManageBoards') . '"' : '') ?>>
								<?= $group['name'] ?>

							</span>
						</label><br>
<?php else: ?>
							<tr>
								<td>
									<label for="groups_<?= $group['id'] ?>_a">
										<span<?= $group['is_post_group'] ? ' class="post_group" title="' . Lang::getTxt('mboards_groups_post_group', file: 'ManageBoards') . '"' : ($group['id'] == 0 ? ' class="regular_members" title="' . Lang::getTxt('mboards_groups_regular_members', file: 'ManageBoards') . '"' : '') ?>>
											<?= $group['name'] ?>

										</span>
									</label>
								</td>
								<td>
									<input type="radio" name="groups[<?= $group['id'] ?>]" value="allow" id="groups_<?= $group['id'] ?>_a"<?= in_array($group['id'], Utils::$context['board_managers']) ? ' checked disabled' : ($group['allow'] ? ' checked' : '') ?>>
								</td>
								<td>
									<input type="radio" name="groups[<?= $group['id'] ?>]" value="ignore" id="groups_<?= $group['id'] ?>_x"<?= in_array($group['id'], Utils::$context['board_managers']) ? ' disabled' : (!$group['allow'] && !$group['deny'] ? ' checked' : '') ?>>
								</td>
								<td>
									<input type="radio" name="groups[<?= $group['id'] ?>]" value="deny" id="groups_<?= $group['id'] ?>_d"<?= in_array($group['id'], Utils::$context['board_managers']) ? ' disabled' : ($group['deny'] ? ' checked' : '') ?>>
								</td>
								<td></td>
							</tr>
<?php endif; ?>
<?php endforeach; ?>
<?php if (empty(Config::$modSettings['deny_boards_access'])): ?>
						<span class="select_all_box">
							<em><?= Lang::getTxt('check_all', file: 'General') ?></em> <input type="checkbox" onclick="invertAll(this, this.form, 'groups[');">
						</span>
						<br><br>
					</dd>
<?php else: ?>
							<tr class="select_all_box">
								<td>
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, 'groups', 'allow');">
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, 'groups', 'ignore');">
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, 'groups', 'deny');">
								</td>
								<td>
									<em><?= Lang::getTxt('check_all', file: 'General') ?></em>
								</td>
							</tr>
						</table>
					</dd>
<?php endif; ?>
<?php /* Options to choose moderators, specify as announcement board and choose whether to count posts here. */ ?>
					<dt>
						<strong><?= Lang::getTxt('mboards_moderators', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('mboards_moderators_desc', file: 'ManageBoards') ?></span><br>
					</dt>
					<dd>
						<input type="text" name="moderators" id="moderators" value="<?= Utils::$context['board']['moderator_list'] ?>" size="30">
						<div id="moderator_container"></div>
					</dd>
					<dt>
						<strong><?= Lang::getTxt('mboards_moderator_groups', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('mboards_moderator_groups_desc', file: 'ManageBoards') ?></span><br>
					</dt>
					<dd>
						<input type="text" name="moderator_groups" id="moderator_groups" value="<?= Utils::$context['board']['moderator_groups_list'] ?>" size="30">
						<div id="moderator_group_container"></div>
					</dd>
				</dl>
				<script>
					$(document).ready(function () {
						$(".select_all_box").each(function () {
							$(this).removeClass('select_all_box');
						});
					});
				</script>
				<hr>
<?php if (empty(Utils::$context['board']['is_recycle']) && empty(Utils::$context['board']['topics'])): ?>
				<dl class="settings">
					<dt>
						<strong<?= Utils::$context['board']['topics'] ? ' style="color: gray;"' : '' ?>><?= Lang::getTxt('mboards_redirect', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('mboards_redirect_desc', file: 'ManageBoards') ?></span><br>
					</dt>
					<dd>
						<input type="checkbox" id="redirect_enable" name="redirect_enable"<?= Utils::$context['board']['redirect'] != '' ? ' checked' : '' ?> onclick="refreshOptions();">
					</dd>
				</dl>

				<div id="redirect_address_div">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('mboards_redirect_url', file: 'ManageBoards') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('mboards_redirect_url_desc', file: 'ManageBoards') ?></span><br>
						</dt>
						<dd>
							<input type="text" name="redirect_address" value="<?= Utils::$context['board']['redirect'] ?>" size="40">
						</dd>
					</dl>
				</div>
<?php if (Utils::$context['board']['redirect']): ?>
				<div id="reset_redirect_div">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('mboards_redirect_reset', file: 'ManageBoards') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('mboards_redirect_reset_desc', file: 'ManageBoards') ?></span><br>
						</dt>
						<dd>
							<input type="checkbox" name="reset_redirect">
							<em>(<?= Lang::getTxt('mboards_current_redirects', [Utils::$context['board']['posts']], file: 'ManageBoards') ?>)</em>
						</dd>
					</dl>
				</div>
<?php endif; ?>
<?php endif; ?>
				<div id="count_posts_div">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('mboards_count_posts', file: 'ManageBoards') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('mboards_count_posts_desc', file: 'ManageBoards') ?></span><br>
						</dt>
						<dd>
							<input type="checkbox" name="count"<?= Utils::$context['board']['posts_count'] ? ' checked' : '' ?>>
						</dd>
					</dl>
				</div>
<?php /* Here the user can choose to force this board to use a theme other than the default theme for the forum. */ ?>
				<div id="board_theme_div">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('mboards_theme', file: 'ManageBoards') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('mboards_theme_desc', file: 'ManageBoards') ?></span><br>
						</dt>
						<dd>
							<select name="boardtheme" id="boardtheme" onchange="refreshOptions();">
								<option value="0"<?= Utils::$context['board']['theme'] == 0 ? ' selected' : '' ?>><?= Lang::getTxt('mboards_theme_default', file: 'ManageBoards') ?></option>
<?php foreach (Utils::$context['themes'] as $theme): ?>
									<option value="<?= $theme['id'] ?>"<?= Utils::$context['board']['theme'] == $theme['id'] ? ' selected' : '' ?>><?= $theme['name'] ?></option>
<?php endforeach; ?>
							</select>
						</dd>
					</dl>
				</div><!-- #board_theme_div -->
				<div id="override_theme_div">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('mboards_override_theme', file: 'ManageBoards') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('mboards_override_theme_desc', file: 'ManageBoards') ?></span><br>
						</dt>
						<dd>
							<input type="checkbox" name="override_theme"<?= Utils::$context['board']['override_theme'] ? ' checked' : '' ?>>
						</dd>
					</dl>
				</div>
<?php /* Show any board settings added by mods using the 'integrate_edit_board' hook. */ ?>
<?php if (!empty(Utils::$context['custom_board_settings']) && is_array(Utils::$context['custom_board_settings'])): ?>
				<hr>
				<div id="custom_board_settings">
					<dl class="settings">
<?php foreach (Utils::$context['custom_board_settings'] as $cbs_id => $cbs): ?>
<?php if (!empty($cbs['dt']) && !empty($cbs['dd'])): ?>
						<dt class="clear<?= !is_numeric($cbs_id) ? ' cbs_' . $cbs_id : '' ?>">
							<?= $cbs['dt'] ?>

						</dt>
						<dd<?= !is_numeric($cbs_id) ? ' class="cbs_' . $cbs_id . '"' : '' ?>>
							<?= $cbs['dd'] ?>

						</dd>
<?php endif; ?>
<?php endforeach; ?>
					</dl>
				</div>
<?php endif; ?>
<?php if (!empty(Utils::$context['board']['is_recycle'])): ?>
				<div class="noticebox"><?= Lang::getTxt('mboards_recycle_disabled_delete', file: 'ManageBoards') ?></div>
<?php endif; ?>
				<input type="hidden" name="rid" value="<?= Utils::$context['redirect_location'] ?>">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token_var'] ?>" value="<?= Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token'] ?>">
<?php /* If this board has no children don't bother with the next confirmation screen. */ ?>
<?php if (Utils::$context['board']['no_children']): ?>
				<input type="hidden" name="no_children" value="1">
<?php endif; ?>
<?php if (isset(Utils::$context['board']['is_new'])): ?>
				<input type="hidden" name="cur_cat" value="<?= Utils::$context['board']['category'] ?>">
				<input type="submit" name="add" value="<?= Lang::getTxt('mboards_new_board', file: 'ManageBoards') ?>" onclick="return !isEmptyText(this.form.board_name);" class="button">
<?php else: ?>
				<input type="submit" name="edit" value="<?= Lang::getTxt('modify', file: 'General') ?>" onclick="return !isEmptyText(this.form.board_name);" class="button">
<?php endif; ?>
<?php if (!isset(Utils::$context['board']['is_new']) && empty(Utils::$context['board']['is_recycle'])): ?>
				<input type="submit" name="delete" value="<?= Lang::getTxt('mboards_delete_board', file: 'ManageBoards') ?>" data-confirm="<?= Lang::getTxt('board_delete_confirm', file: 'ManageBoards') ?>" class="button you_sure">
<?php endif; ?>
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->

	<script>
		var oModeratorSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'moderators',
			sControlId: 'moderators',
			sSearchType: 'member',
			bItemList: true,
			sPostName: 'moderator_list',
			sURLMask: 'action=profile;u=%item_id%',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			sItemListContainerId: 'moderator_container',
			aListItems: [
<?php foreach (Utils::$context['board']['moderators'] as $id_member => $member_name): ?>
				{
					sItemId: <?= Utils::escapeJavaScript($id_member) ?>,
					sItemName: <?= Utils::escapeJavaScript($member_name) ?>

				}<?= $id_member == Utils::$context['board']['last_moderator_id'] ? '' : ',' ?>

<?php endforeach; ?>
			]
		});

		var oModeratorGroupSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'moderator_groups',
			sControlId: 'moderator_groups',
			sSearchType: 'membergroups',
			bItemList: true,
			sPostName: 'moderator_group_list',
			sURLMask: 'action=groups;sa=members;group=%item_id%',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			sItemListContainerId: 'moderator_group_container',
			aListItems: [
<?php foreach (Utils::$context['board']['moderator_groups'] as $id_group => $group_name): ?>
				{
					sItemId: <?= Utils::escapeJavaScript($id_group) ?>,
					sItemName: <?= Utils::escapeJavaScript($group_name) ?>

				}<?= $id_group == Utils::$context['board']['last_moderator_group_id'] ? '' : ',' ?>

<?php endforeach; ?>
			]
		});
	</script>
<?php /* Javascript for deciding what to show. */ ?>
	<script>
		function refreshOptions()
		{
			var redirect = document.getElementById("redirect_enable");
			var redirectEnabled = redirect ? redirect.checked : false;
			var nonDefaultTheme = document.getElementById("boardtheme").value == 0 ? false : true;

			// What to show?

			if(redirectEnabled || !nonDefaultTheme)
				document.getElementById("override_theme_div").classList.add('hidden');
			else
				document.getElementById("override_theme_div").classList.remove('hidden');

			if(redirectEnabled) {
				document.getElementById("board_theme_div").classList.add('hidden');
				document.getElementById("count_posts_div").classList.add('hidden');
			} else {
				document.getElementById("board_theme_div").classList.remove('hidden');
				document.getElementById("count_posts_div").classList.remove('hidden');
			}
<?php if (!Utils::$context['board']['topics'] && empty(Utils::$context['board']['is_recycle'])): ?>
			if(redirectEnabled)
				document.getElementById("redirect_address_div").classList.remove('hidden');
			else
				document.getElementById("redirect_address_div").classList.add('hidden');
<?php if (Utils::$context['board']['redirect']): ?>
			if(redirectEnabled)
				document.getElementById("reset_redirect_div").classList.remove('hidden');
			else
				document.getElementById("reset_redirect_div").classList.add('hidden');
<?php endif; ?>
<?php endif; ?>
<?php /* Include any JavaScript added by mods using the 'integrate_edit_board' hook. */ ?>
<?php if (!empty(Utils::$context['custom_refreshOptions']) && is_array(Utils::$context['custom_refreshOptions'])): ?>
<?php foreach (Utils::$context['custom_refreshOptions'] as $refreshOption): ?>
			<?= $refreshOption ?>

<?php endforeach; ?>
<?php endif; ?>
		}
		refreshOptions();
	</script>