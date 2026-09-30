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
 * Board notification bar.
 */
?>

		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				<?= Lang::getTxt('notify', file: 'General') ?>

			</h3>
		</div>
		<div class="roundframe centertext">
			<p><?= Lang::getTxt('notify_board_prompt', file: 'General') ?></p>
			<p>
				<strong><a href="<?= Config::$scripturl ?>?action=notifyboard;sa=on;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>;<?= (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']) ?>"><?= Lang::getTxt('yes', file: 'General') ?></a> - <a href="<?= Config::$scripturl ?>?action=notifyboard;sa=off;board=<?= Utils::$context['current_board'] ?>.<?= Utils::$context['start'] ?>;<?= (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']) ?>"><?= Lang::getTxt('no', file: 'General') ?></a></strong>
			</p>
		</div>