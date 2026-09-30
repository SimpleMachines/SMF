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
 * The template for sending newsletters
 */
?><?php /* Are we done sending the newsletter? */ ?><?php if (!empty(Utils::$context['newsletter_sent'])): ?>

	<div class="infobox"><?= Lang::getTxt('admin_news_newsletter_' . Utils::$context['newsletter_sent'], file: 'Admin') ?></div><?php endif; ?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=news;sa=mailingcompose" method="post" id="admin_newsletters" class="flow_hidden" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('admin_newsletters', file: 'Admin') ?></h3>
			</div>
			<div class="information noup">
				<?= Lang::getTxt('admin_news_select_recipients', file: 'Admin') ?>

			</div>
			<div class="windowbg noup">
				<dl class="settings">
					<dt>
						<strong><?= Lang::getTxt('admin_news_select_group', file: 'Admin') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('admin_news_select_group_desc', file: 'Admin') ?></span>
					</dt>
					<dd><?php foreach (Utils::$context['groups'] as $group): ?>

						<label for="groups_<?= $group['id'] ?>"><input type="checkbox" name="groups[<?= $group['id'] ?>]" id="groups_<?= $group['id'] ?>" value="<?= $group['id'] ?>" checked> <?= $group['name'] ?></label> <em>(<?= $group['member_count'] ?? Lang::getTxt('not_applicable', file: 'General') ?>)</em><br><?php endforeach; ?>

						<br>
						<label for="checkAllGroups"><input type="checkbox" id="checkAllGroups" checked onclick="invertAll(this, this.form, 'groups');"> <em><?= Lang::getTxt('check_all', file: 'General') ?></em></label>
					</dd>
				</dl>
				<div id="advanced_panel_header" class="title_bar">
					<h3 class="titlebg">
						<a href="#" id="advanced_panel_link"><?= Lang::getTxt('advanced', file: 'Admin') ?></a>
					</h3>
					<span id="advanced_panel_toggle" class="toggle_down" style="display: none;"></span>
				</div>
				<div id="advanced_panel_div" class="padding">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('admin_news_select_email', file: 'Admin') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('admin_news_select_email_desc', file: 'Admin') ?></span>
						</dt>
						<dd>
							<textarea name="emails" rows="5" cols="30" style="width: 98%;"></textarea>
						</dd>
						<dt>
							<strong><?= Lang::getTxt('admin_news_select_members', file: 'Admin') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('admin_news_select_members_desc', file: 'Admin') ?></span>
						</dt>
						<dd>
							<input type="text" name="members" id="members" value="" size="30">
							<span id="members_container"></span>
						</dd>
					</dl>
					<hr class="bordercolor">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('admin_news_select_excluded_groups', file: 'Admin') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('admin_news_select_excluded_groups_desc', file: 'Admin') ?></span>
						</dt>
						<dd><?php foreach (Utils::$context['groups'] as $group): ?>

							<label for="exclude_groups_<?= $group['id'] ?>"><input type="checkbox" name="exclude_groups[<?= $group['id'] ?>]" id="exclude_groups_<?= $group['id'] ?>" value="<?= $group['id'] ?>"> <?= $group['name'] ?></label> <em>(<?= $group['member_count'] ?>)</em><br><?php endforeach; ?>

							<br>
							<label for="checkAllGroupsExclude"><input type="checkbox" id="checkAllGroupsExclude" onclick="invertAll(this, this.form, 'exclude_groups');"> <em><?= Lang::getTxt('check_all', file: 'General') ?></em></label><br>
						</dd>
						<dt>
							<strong><?= Lang::getTxt('admin_news_select_excluded_members', file: 'Admin') ?></strong><br>
							<span class="smalltext"><?= Lang::getTxt('admin_news_select_excluded_members_desc', file: 'Admin') ?></span>
						</dt>
							<dd>
							<input type="text" name="exclude_members" id="exclude_members" value="" size="30">
							<span id="exclude_members_container"></span>
						</dd>
					</dl>
					<hr class="bordercolor">
					<dl class="settings">
						<dt>
							<label for="email_force"><strong><?= Lang::getTxt('admin_news_select_override_notify', file: 'Admin') ?></strong></label><br>
							<span class="smalltext"><?= Lang::getTxt('email_force', file: 'Admin') ?></span>
						</dt>
						<dd>
							<input type="checkbox" name="email_force" id="email_force" value="1">
						</dd>
					</dl>
				</div><!-- #advanced_panel_div -->
				<br>
				<input type="submit" value="<?= Lang::getTxt('admin_next', file: 'Admin') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</div><!-- .windowbg -->
		</form><?php /* This is some javascript for the simple/advanced toggling and member suggest */ ?>

	<script>
		var oAdvancedPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				'advanced_panel_div'
			],
			aSwapImages: [
				{
					sId: 'advanced_panel_toggle',
					altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
					altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

				}
			],
			aSwapLinks: [
				{
					sId: 'advanced_panel_link',
					msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('advanced', file: 'Admin')) ?>,
					msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('advanced', file: 'Admin')) ?>

				}
			]
		});
	</script>
	<script>
		var oMemberSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: 'members',
			sControlId: 'members',
			sSearchType: 'member',
			bItemList: true,
			sPostName: 'member_list',
			sURLMask: 'action=profile;u=%item_id%',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			sItemListContainerId: 'members_container',
			aListItems: []
		});
		var oExcludeMemberSuggest = new smc_AutoSuggest({
			sSessionId: '<?= Utils::$context['session_id'] ?>',
			sSessionVar: '<?= Utils::$context['session_var'] ?>',
			sSuggestId: 'exclude_members',
			sControlId: 'exclude_members',
			sSearchType: 'member',
			bItemList: true,
			sPostName: 'exclude_member_list',
			sURLMask: 'action=profile;u=%item_id%',
			sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
			sItemListContainerId: 'exclude_members_container',
			aListItems: []
		});
	</script>