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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Confirm package operation
 */
?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
		</div>
		<div class="windowbg">
			<p><?= Utils::$context['confirm_message'] ?></p>
			<a href="<?= Utils::$context['proceed_href'] ?>" class="button floatnone"><?= Lang::getTxt('package_confirm_proceed', file: 'Packages') ?></a> <a href="JavaScript:history.go(-1);" class="button floatnone"><?= Lang::getTxt('package_confirm_go_back', file: 'Packages') ?></a>
		</div>