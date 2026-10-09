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
use SMF\User;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Outputs the board icon for a standard board.
 *
 * @param array $board Current board information.
 */
?>
		<a href="<?= (User::$me->is_guest ? $board['href'] : Config::$scripturl . '?action=unread;board=' . $board['id'] . '.0;children') ?>" class="board_<?= $board['board_class'] ?>"<?= !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '' ?>></a>