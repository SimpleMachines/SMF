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
 * A list of reported posts
 */
?>
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="<?= Config::$scripturl ?>?action=moderate;area=reportedposts" id="reported_posts_link"><?= Lang::getTxt('mc_recent_reports', file: 'ModerationCenter') ?></a>
			</h3>
			<span id="reported_posts_toggle" class="<?= !empty(Utils::$context['admin_prefs']['mcrp']) ? 'toggle_down' : 'toggle_up' ?>" title="<?= Lang::getTxt(empty(Utils::$context['admin_prefs']['mcrp']) ? 'hide' : 'show', file: 'General') ?>" style="display: none;"></span>
		</div>
		<div class="windowbg" id="reported_posts_panel">
			<ul>
<?php foreach (Utils::$context['reported_posts'] as $post): ?>
				<li>
					<span class="smalltext"><?= Lang::getTxt('mc_post_report', ['report_link' => $post['report_link'], 'author_link' => $post['author']['link']], file: 'ModerationCenter') ?></span>
				</li>
<?php endforeach; ?>
<?php /* Don't have any watched users right now? */ ?>
<?php if (empty(Utils::$context['reported_posts'])): ?>
				<li>
					<strong class="smalltext"><?= Lang::getTxt('mc_recent_reports_none', file: 'ModerationCenter') ?></strong>
				</li>
<?php endif; ?>
			</ul>
		</div><!-- #reported_posts_panel -->

		<script>
			var oReportedPostsToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: <?= !empty(Utils::$context['admin_prefs']['mcrp']) ? 'true' : 'false' ?>,
				aSwappableContainers: [
					'reported_posts_panel'
				],
				aSwapImages: [
					{
						sId: 'reported_posts_toggle',
						altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
						altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

					}
				],
				aSwapLinks: [
					{
						sId: 'reported_posts_link',
						msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('mc_recent_reports', file: 'ModerationCenter')) ?>,
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('mc_recent_reports', file: 'ModerationCenter')) ?>

					}
				],
				oThemeOptions: {
					bUseThemeSettings: true,
					sOptionName: 'admin_preferences',
					sSessionVar: smf_session_var,
					sSessionId: smf_session_id,
					sThemeId: '1',
					sAdditionalVars: ';admin_key=mcrp'
				}
			});
		</script>