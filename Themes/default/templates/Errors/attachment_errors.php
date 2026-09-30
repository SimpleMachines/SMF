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
 * This template handles showing attachment-related errors
 */
?>
	<div>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Utils::$context['error_title'] ?>
			</h3>
		</div>
		<div class="windowbg">
			<div class="padding">
				<div class="noticebox"><?= Utils::$context['error_message'] ?>
				</div>
<?php if (!empty(Utils::$context['back_link'])): ?>
				<a class="button" href="<?= Config::$scripturl ?><?= Utils::$context['back_link'] ?>"><?= Lang::getTxt('back', file: 'General') ?></a>
<?php endif; ?>
				<span style="float: right; margin:.5em;"></span>
				<a class="button" href="<?= Config::$scripturl ?><?= Utils::$context['redirect_link'] ?>"><?= Lang::getTxt('continue', file: 'General') ?></a>
			</div>
		</div>
	</div>