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

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing which boards you're subscribed to
 */
?>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt('watched_boards', file: 'Profile') ?>
			</h3>
		</div>
		<p class="information"><?= Lang::getTxt('watched_boards_desc', file: 'Profile') ?></p>
		<br><?php $this->subTemplate('show_list', ['list_id' => 'board_notification_list']); ?>
