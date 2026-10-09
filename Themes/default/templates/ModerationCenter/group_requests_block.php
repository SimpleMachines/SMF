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
 * Show all the group requests the user can see.
 */
?>
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="<?= Config::$scripturl ?>?action=groups;sa=requests" id="group_requests_link"><?= Lang::getTxt('mc_group_requests', file: 'ModerationCenter') ?></a>
			</h3>
			<span id="group_requests_toggle" class="<?= !empty(Utils::$context['admin_prefs']['mcgr']) ? 'toggle_down' : 'toggle_up' ?>" title="<?= Lang::getTxt(empty(Utils::$context['admin_prefs']['mcgr']) ? 'hide' : 'show', file: 'General') ?>" style="display: none;"></span>
		</div>
		<div class="windowbg" id="group_requests_panel">
			<ul>
<?php foreach (Utils::$context['group_requests'] as $request): ?>
				<li class="smalltext">
					<?= Lang::getTxt('mc_groupr_by', ['group_link' => '<a href="' . $request['request_href'] . '">' . $request['group']['name'] . '</a>', 'member_link' => $request['member']['link']], file: 'ModerationCenter') ?>
				</li>
<?php endforeach; ?>
<?php /* Don't have any watched users right now? */ ?>
<?php if (empty(Utils::$context['group_requests'])): ?>
				<li>
					<strong class="smalltext"><?= Lang::getTxt('mc_group_requests_none', file: 'ModerationCenter') ?></strong>
				</li>
<?php endif; ?>
			</ul>
		</div><!-- #group_requests_panel -->

		<script>
			var oGroupRequestsPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: <?= !empty(Utils::$context['admin_prefs']['mcgr']) ? 'true' : 'false' ?>,
				aSwappableContainers: [
					'group_requests_panel'
				],
				aSwapImages: [
					{
						sId: 'group_requests_toggle',
						altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
						altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

					}
				],
				aSwapLinks: [
					{
						sId: 'group_requests_link',
						msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('mc_group_requests', file: 'ModerationCenter')) ?>,
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('mc_group_requests', file: 'ModerationCenter')) ?>

					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: 'admin_preferences',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: '1',
					sAdditionalVars: ';admin_key=mcgr'
				}
			});
		</script>