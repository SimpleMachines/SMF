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
 * Displays a message indicating the user's notification preferences were successfully changed
 */
?>

		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				<?= Lang::getTxt('notify', file: 'General') ?>

			</h3>
		</div>
		<div class="roundframe centertext">
			<p><?= Utils::$context['notify_success_msg'] ?></p>
		</div>