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

use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This shows the newsfader
 */
?><?php /* Show the news fader?  (assuming there are things to show...) */ ?><?php if (!empty(Theme::$current->settings['show_newsfader']) && !empty(Utils::$context['news_lines'])): ?>
		<ul id="smf_slider" class="roundframe"><?php foreach (Utils::$context['news_lines'] as $news): ?>
			<li><?= $news ?></li><?php endforeach; ?>
		</ul>
		<script>
			jQuery("#smf_slider").slippry({
				pause: <?= Theme::$current->settings['newsfader_time'] ?>,
				adaptiveHeight: 0,
				captions: 0,
				controls: 0,
			});
		</script><?php endif; ?>
