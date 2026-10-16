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

use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Displays the info center
 */
?><?php if (empty(Utils::$context['info_center'])): ?><?php return; ?><?php endif; ?><?php /* Here's where the "Info Center" starts... */ ?>
	<div class="roundframe" id="info_center">
		<div class="title_bar">
			<h3 class="titlebg">
				<a href="#" id="upshrink_link"><?= Lang::getTxt('info_center_title', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'General') ?></a>
			</h3>
			<span class="toggle_up" id="upshrink_ic" title="<?= Lang::getTxt('hide_infocenter', file: 'General') ?>" style="display: none;"></span>
		</div>
		<div id="upshrink_stats"<?= empty(Theme::$current->options['collapse_header_ic']) ? '' : ' style="display: none;"' ?>><?php foreach (Utils::$context['info_center'] as $block): ?><?php $this->subTemplate('ic_block_' . $block['tpl']); ?><?php endforeach; ?>
		</div><!-- #upshrink_stats -->
	</div><!-- #info_center --><?php /* Info center collapse object. */ ?>
	<script>
		var oInfoCenterToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: <?= empty(Theme::$current->options['collapse_header_ic']) ? 'false' : 'true' ?>,
			aSwappableContainers: [
				'upshrink_stats'
			],
			aSwapImages: [
				{
					sId: 'upshrink_ic',
					altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide_infocenter', file: 'General')) ?>,
					altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show_infocenter', file: 'General')) ?>

				}
			],
			aSwapLinks: [
				{
					sId: 'upshrink_link',
					msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('info_center_title', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'General')) ?>,
					msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('info_center_title', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'General')) ?>

				}
			],
			oThemeOptions: {
				bUseThemeSettings: <?= User::$me->is_guest ? 'false' : 'true' ?>,
				sOptionName: 'collapse_header_ic',
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
			},
			oCookieOptions: {
				bUseCookie: <?= User::$me->is_guest ? 'true' : 'false' ?>,
				sCookieName: 'upshrinkIC'
			}
		});
	</script>