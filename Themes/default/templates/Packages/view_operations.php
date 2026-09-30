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
 * View operation details.
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Lang::getTxt('operation_title', file: 'Packages') ?></title>
		<?= Theme::template_css() ?><?php Theme::template_javascript(); ?>

	</head>
	<body>
		<div class="padding windowbg">
			<div class="padding">
				<?= Utils::$context['operations']['search'] ?>

			</div>
			<div class="padding">
				<?= Utils::$context['operations']['replace'] ?>

			</div>
		</div>
	</body>
</html>