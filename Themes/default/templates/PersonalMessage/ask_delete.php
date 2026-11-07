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
 * This template asks the user whether they wish to empty out their folder/messages.
 */
?>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt(Utils::$context['delete_all'] ? 'delete_message' : 'delete_all', file: 'PersonalMessage') ?>
			</h3>
		</div>
		<div class="windowbg">
			<p><?= Lang::getTxt('delete_all_confirm', file: 'PersonalMessage') ?></p>
			<br>
			<strong><a href="<?= Config::$scripturl ?>?action=pm;sa=removeall2;f=<?= Utils::$context['folder'] ?>;<?= Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '' ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><?= Lang::getTxt('yes', file: 'General') ?></a> - <a href="javascript:history.go(-1);"><?= Lang::getTxt('no', file: 'General') ?></a></strong>
		</div>