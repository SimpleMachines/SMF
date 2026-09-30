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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This shows the popup that shows who likes a particular post.
 */
?><?php /* Since this is a popup of its own we need to start the html, etc. */ ?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<meta name="robots" content="noindex">
		<title><?= Utils::$context['page_title'] ?></title>
		<?= Theme::template_css() ?>
		<script src="<?= Theme::$current->settings['default_theme_url'] ?>/scripts/script.js<?= Utils::$context['browser_cache'] ?>"></script>
	</head>
	<body id="likes_popup">
		<div class="windowbg">
			<ul id="likes"><?php foreach (Utils::$context['likers'] as $liker => $like_details): ?>
				<li>
					<?= $like_details['profile']['avatar']['image'] ?>
					<span class="like_profile">
						<?= $like_details['profile']['link_color'] ?>
						<span class="description"><?= $like_details['profile']['group'] ?></span>
					</span>
					<span class="floatright like_time"><?= $like_details['time'] ?></span>
				</li><?php endforeach; ?>
			</ul>
			<br class="clear">
			<a href="javascript:self.close();"><?= Lang::getTxt('close_window', file: 'Help') ?></a>
		</div><!-- .windowbg -->
	</body>
</html>