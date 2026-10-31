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
 * Template for browsing the mail queue.
 */
?>
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mailqueue_stats', file: 'ManageMail') ?></h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt><strong><?= Lang::getTxt('mailqueue_size', file: 'ManageMail') ?></strong></dt>
					<dd><?= Utils::$context['mail_queue_size'] ?></dd>
					<dt><strong><?= Lang::getTxt('mailqueue_oldest', file: 'ManageMail') ?></strong></dt>
					<dd><?= Utils::$context['oldest_mail'] ?></dd>
				</dl>
			</div>
		</div><?php $this->subTemplate('show_list', ['list_id' => 'mail_queue']); ?>
	</div>