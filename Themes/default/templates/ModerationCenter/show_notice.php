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
 * Show a notice sent to a user.
 */
?><?php /* We do all the HTML for this one! */ ?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Utils::$context['page_title'] ?></title>
		<?= Theme::template_css() ?>

	</head>
	<body>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('show_notice', file: 'ModerationCenter') ?></h3>
		</div>
		<div class="title_bar">
			<h3 class="titlebg"><?= Lang::getTxt('show_notice_subject', ['subject' => Utils::$context['notice_subject']], file: 'ModerationCenter') ?></h3>
		</div>
		<div class="windowbg">
			<dl>
				<dt>
					<strong><?= Lang::getTxt('show_notice_text', file: 'ModerationCenter') ?></strong>
				</dt>
				<dd>
					<?= Utils::adjustHeadingLevels(Utils::$context['notice_body'], 3) ?>

				</dd>
			</dl>
		</div>
	</body>
</html>