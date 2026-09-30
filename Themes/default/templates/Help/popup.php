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
 * This displays a help popup thingy
 */
?><?php
// reqOverlayDiv() asks for this with ';ajax' on the end and shows whatever
// comes back inside an overlay on the page it was called from. There is no
// window to close in that case, and no use for a document of its own.
?><?php if (isset($_REQUEST['ajax'])): ?>

		<div class="windowbg description">
			<?= Utils::$context['help_text'] ?>

		</div><?php return; ?><?php endif; ?><?php /* Otherwise this really is a window of its own, so it needs the html. */ ?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<meta name="robots" content="noindex">
		<title><?= Utils::$context['page_title'] ?></title>
		<?= Theme::template_css() ?>

		<script src="<?= Theme::$current->settings['default_theme_url'] ?>/scripts/script.js<?= Utils::$context['browser_cache'] ?>"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			<?= Utils::$context['help_text'] ?><br>
			<br>
			<a href="javascript:self.close();"><?= Lang::getTxt('close_window', file: 'Help') ?></a>
		</div>
	</body>
</html>