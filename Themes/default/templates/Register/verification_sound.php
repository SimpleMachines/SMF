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

use SMF\BrowserDetector;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Show a window containing the spoken verification code.
 */
?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Lang::getTxt('visual_verification_sound', file: 'General') ?></title>
		<meta name="robots" content="noindex">
		<?= Theme::template_css() ?>

		<style><?php /* Just show the help text and a "close window" link. */ ?>

		</style>
	</head>
	<body style="margin: 1ex;">
		<div class="windowbg description" style="text-align: center;"><?php if (BrowserDetector::isBrowser('is_ie') || BrowserDetector::isBrowser('is_ie11')): ?>

			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="<?= Utils::$context['verification_sound_href'] ?>">
			</object><?php else: ?>

			<audio src="<?= Utils::$context['verification_sound_href'] ?>" controls>
				<object type="audio/x-wav" data="<?= Utils::$context['verification_sound_href'] ?>">
					<a href="<?= Utils::$context['verification_sound_href'] ?>" rel="nofollow"><?= Utils::$context['verification_sound_href'] ?></a>
				</object>
			</audio><?php endif; ?>

			<br>
			<a href="<?= Utils::$context['verification_sound_href'] ?>;sound" rel="nofollow"><?= Lang::getTxt('visual_verification_sound_again', file: 'Login') ?></a><br>
			<a href="<?= Utils::$context['verification_sound_href'] ?>" rel="nofollow"><?= Lang::getTxt('visual_verification_sound_direct', file: 'Login') ?></a><br><br>
			<a href="javascript:self.close();"><?= Lang::getTxt('visual_verification_sound_close', file: 'Login') ?></a><br>
		</div><!-- .description -->
	</body>
</html>