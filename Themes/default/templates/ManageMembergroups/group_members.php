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
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for viewing the members of a group.
 */
?>

		<form action="<?= Config::$scripturl ?>?action=<?= Utils::$context['current_action'] ?><?= (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : '') ?>;sa=members;group=<?= Utils::$context['group']['id'] ?>" method="post" accept-charset="UTF-8" id="view_group">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong><?= Lang::getTxt('name', file: 'General') ?></strong>
					</dt>
					<dd>
						<span <?= Utils::$context['group']['online_color'] ? 'style="color: ' . Utils::$context['group']['online_color'] . ';"' : '' ?>><?= Utils::$context['group']['name'] ?></span> <?= Utils::$context['group']['icons'] ?>

					</dd><?php /* Any description to show? */ ?><?php if (!empty(Utils::$context['group']['description'])): ?>

					<dt>
						<strong><?= Lang::getTxt('membergroups_members_description', file: 'ManageMembers') ?></strong>
					</dt>
					<dd>
						<?= Utils::$context['group']['description'] ?>

					</dd><?php endif; ?>

					<dt>
						<strong><?= Lang::getTxt('membergroups_members_top', file: 'ManageMembers') ?></strong>
					</dt>
					<dd>
						<?= Utils::$context['total_members'] ?>

					</dd><?php /* Any group moderators to show? */ ?><?php if (!empty(Utils::$context['group']['moderators'])): ?><?php $moderators = []; ?><?php foreach (Utils::$context['group']['moderators'] as $moderator): ?><?php $moderators[] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $moderator['id'] . '">' . $moderator['name'] . '</a>'; ?><?php endforeach; ?>

					<dt>
						<strong><?= Lang::getTxt('membergroups_members_group_moderators', file: 'ManageMembers') ?></strong>
					</dt>
					<dd>
						<?= implode(', ', $moderators) ?>

					</dd><?php endif; ?>

				</dl>
			</div><!-- .windowbg -->
			<br>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('membergroups_members_group_members', file: 'ManageMembers') ?></h3>
			</div>
			<br>
			<div class="pagesection">
				<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
			</div>
			<table class="table_grid" id="group_members">
				<thead>
					<tr class="title_bar">
						<th class="user_name"><a href="<?= Config::$scripturl ?>?action=<?= Utils::$context['current_action'] ?><?= (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : '') ?>;sa=members;start=<?= Utils::$context['start'] ?>;sort=name<?= Utils::$context['sort_by'] == 'name' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?>;group=<?= Utils::$context['group']['id'] ?>"><?= Lang::getTxt('name', file: 'General') ?><?= Utils::$context['sort_by'] == 'name' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a></th><?php if (Utils::$context['can_send_email']): ?>

						<th class="email"><a href="<?= Config::$scripturl ?>?action=<?= Utils::$context['current_action'] ?><?= (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : '') ?>;sa=members;start=<?= Utils::$context['start'] ?>;sort=email<?= Utils::$context['sort_by'] == 'email' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?>;group=<?= Utils::$context['group']['id'] ?>"><?= Lang::getTxt('email', file: 'General') ?><?= Utils::$context['sort_by'] == 'email' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a></th><?php endif; ?>

						<th class="last_active"><a href="<?= Config::$scripturl ?>?action=<?= Utils::$context['current_action'] ?><?= (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : '') ?>;sa=members;start=<?= Utils::$context['start'] ?>;sort=active<?= Utils::$context['sort_by'] == 'active' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?>;group=<?= Utils::$context['group']['id'] ?>"><?= Lang::getTxt('membergroups_members_last_active', file: 'ManageMembers') ?><?= Utils::$context['sort_by'] == 'active' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a></th>
						<th class="date_registered"><a href="<?= Config::$scripturl ?>?action=<?= Utils::$context['current_action'] ?><?= (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : '') ?>;sa=members;start=<?= Utils::$context['start'] ?>;sort=registered<?= Utils::$context['sort_by'] == 'registered' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?>;group=<?= Utils::$context['group']['id'] ?>"><?= Lang::getTxt('date_registered', file: 'General') ?><?= Utils::$context['sort_by'] == 'registered' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a></th>
						<th class="posts"<?= empty(Utils::$context['can_add_remove']) ? ' colspan="2"' : '' ?>>
							<a href="<?= Config::$scripturl ?>?action=<?= Utils::$context['current_action'] ?><?= (isset(Utils::$context['admin_area']) ? ';area=' . Utils::$context['admin_area'] : '') ?>;sa=members;start=<?= Utils::$context['start'] ?>;sort=posts<?= Utils::$context['sort_by'] == 'posts' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?>;group=<?= Utils::$context['group']['id'] ?>"><?= Lang::getTxt('posts', file: 'General') ?><?= Utils::$context['sort_by'] == 'posts' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a>
						</th><?php if (!empty(Utils::$context['can_add_remove'])): ?>

						<th class="quick_moderation" style="width: 4%"><input type="checkbox" onclick="invertAll(this, this.form);"></th><?php endif; ?>

					</tr>
				</thead>
				<tbody><?php if (empty(Utils::$context['members'])): ?>

					<tr class="windowbg">
						<td colspan="6"><?= Lang::getTxt('membergroups_members_no_members', file: 'ManageMembers') ?></td>
					</tr><?php endif; ?><?php foreach (Utils::$context['members'] as $member): ?>

					<tr class="windowbg">
						<td class="user_name"><?= $member['link'] ?></td><?php if (Utils::$context['can_send_email']): ?>

						<td class="email">
								<a href="mailto:<?= $member['email'] ?>"><?= $member['email'] ?></a>
						</td><?php endif; ?>

						<td class="last_active"><?= $member['last_login'] ?></td>
						<td class="date_registered"><?= $member['registered'] ?></td>
						<td class="posts"<?= empty(Utils::$context['can_add_remove']) ? ' colspan="2"' : '' ?>><?= $member['posts'] ?></td><?php if (!empty(Utils::$context['can_add_remove'])): ?>

						<td class="quick_moderation" style="width: 4%"><input type="checkbox" name="rem[]" value="<?= $member['id'] ?>" <?= (User::$me->id == $member['id'] && Utils::$context['group']['id'] == 1 ? 'onclick="if (this.checked) return confirm(\'' . Lang::getTxt('membergroups_members_deadmin_confirm', file: 'ManageMembers') . '\')" ' : '') ?>/></td><?php endif; ?>

					</tr><?php endforeach; ?>

				</tbody>
			</table><?php if (!empty(Utils::$context['can_add_remove'])): ?>

			<div class="floatright">
				<input type="submit" name="remove" value="<?= Lang::getTxt('membergroups_members_remove', file: 'ManageMembers') ?>" class="button ">
			</div><?php endif; ?>

			<div class="pagesection">
				<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
			</div>
			<br><?php if (!empty(Utils::$context['can_add_remove'])): ?>

			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('membergroups_members_add_title', file: 'ManageMembers') ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<strong><label for="toAdd"><?= Lang::getTxt('membergroups_members_add_desc', file: 'ManageMembers') ?></label></strong>
					</dt>
					<dd>
						<input type="text" name="toAdd" id="toAdd" value="">
						<div id="toAddItemContainer"></div>
					</dd>
				</dl>
				<input type="submit" name="add" value="<?= Lang::getTxt('membergroups_members_add', file: 'ManageMembers') ?>" class="button">
			</div><?php endif; ?>

			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['mod-mgm_token_var'] ?>" value="<?= Utils::$context['mod-mgm_token'] ?>">
		</form><?php if (!empty(Utils::$context['can_add_remove'])): ?>

	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSessionId: '<?= Utils::$context['session_id'] ?>',
			sSessionVar: '<?= Utils::$context['session_var'] ?>',
			sSuggestId: 'to_suggest',
			sControlId: 'toAdd',
			sSearchType: 'member',
			sPostName: 'member_add',
			sURLMask: 'action=profile;u=%item_id%',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			bItemList: true,
			sItemListContainerId: 'toAddItemContainer'
		});
	</script><?php endif; ?>
