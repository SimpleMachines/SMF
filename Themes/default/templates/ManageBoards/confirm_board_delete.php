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
 * A template used when a user is deleting a board with child boards in it - to see what they want to do with them.
 */
?><?php /* Print table header. */ ?>

	<div id="manage_boards" class="roundframe">
		<form action="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=board2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="boardid" value="<?= Utils::$context['board']['id'] ?>">

			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mboards_delete_board', file: 'ManageBoards') ?></h3>
			</div>
			<div class="windowbg">
				<p><?= Lang::getTxt('mboards_delete_board_contains', file: 'ManageBoards') ?></p>
				<ul><?php foreach (Utils::$context['children'] as $child): ?>

					<li><?= $child['node']['name'] ?></li><?php endforeach; ?>

				</ul>
			</div>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mboards_delete_what_do', file: 'ManageBoards') ?></h3>
			</div>
			<div class="windowbg">
				<p>
					<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" checked><?= Lang::getTxt('mboards_delete_board_option1', file: 'ManageBoards') ?></label><br>
					<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1"<?= empty(Utils::$context['can_move_children']) ? ' disabled' : '' ?>><?= Lang::getTxt('mboards_delete_board_option2', file: 'ManageBoards') ?></label>:
					<select name="board_to"<?= empty(Utils::$context['can_move_children']) ? ' disabled' : '' ?>><?php foreach (Utils::$context['board_order'] as $board): ?><?php if ($board['id'] != Utils::$context['board']['id'] && empty($board['is_child'])): ?>

						<option value="<?= $board['id'] ?>"><?= $board['name'] ?></option><?php endif; ?><?php endforeach; ?>

					</select>
				</p>
				<input type="submit" name="delete" value="<?= Lang::getTxt('mboards_delete_confirm', file: 'ManageBoards') ?>" class="button">
				<input type="submit" name="cancel" value="<?= Lang::getTxt('mboards_delete_cancel', file: 'ManageBoards') ?>" class="button">
				<input type="hidden" name="confirmation" value="1">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token_var'] ?>" value="<?= Utils::$context['admin-be-' . Utils::$context['board']['id'] . '_token'] ?>">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->