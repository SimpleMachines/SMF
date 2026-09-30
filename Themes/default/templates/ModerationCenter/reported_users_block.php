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
 * A list of reported users
 */
?>

		<div class="cat_bar">
			<h3 class="catbg">
				<a href="<?= Config::$scripturl ?>?action=moderate;area=reportedmembers" id="reported_users_link"><?= Lang::getTxt('mc_recent_user_reports', file: 'ModerationCenter') ?></a>
			</h3>
			<span id="reported_users_toggle" class="<?= !empty(Utils::$context['admin_prefs']['mcur']) ? 'toggle_down' : 'toggle_up' ?>" title="<?= Lang::getTxt(empty(Utils::$context['admin_prefs']['mcur']) ? 'hide' : 'show', file: 'General') ?>" style="display: none;"></span>
		</div>
		<div class="windowbg" id="reported_users_panel">
			<ul>
<?php foreach (Utils::$context['reported_users'] as $user): ?>
				<li>
					<span class="smalltext"><?= $user['user']['link'] ?></span>
				</li>
<?php endforeach; ?>
<?php /* Don't have any watched users right now? */ ?>
<?php if (empty(Utils::$context['reported_users'])): ?>
				<li>
					<strong class="smalltext"><?= Lang::getTxt('mc_reported_users_none', file: 'ModerationCenter') ?></strong>
				</li>
<?php endif; ?>
			</ul>
		</div><!-- #reported_users_panel -->

		<script>
			var oReportedUsersToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: <?= !empty(Utils::$context['admin_prefs']['mcur']) ? 'true' : 'false' ?>,
				aSwappableContainers: [
					'reported_users_panel'
				],
				aSwapImages: [
					{
						sId: 'reported_users_toggle',
						altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
						altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

					}
				],
				aSwapLinks: [
					{
						sId: 'reported_users_link',
						msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('mc_recent_user_reports', file: 'ModerationCenter')) ?>,
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('mc_recent_user_reports', file: 'ModerationCenter')) ?>

					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: 'admin_preferences',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: '1',
					sAdditionalVars: ';admin_key=mcur'
				}
			});
		</script>