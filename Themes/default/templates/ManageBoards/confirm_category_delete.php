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
 * A template to confirm if a user wishes to delete a category - and whether they want to save the boards.
 */
?><?php /* Print table header. */ ?>
	<div id="manage_boards" class="roundframe">
		<form action="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="<?= Utils::$context['category']['id'] ?>">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mboards_delete_cat', file: 'ManageBoards') ?></h3>
			</div>
			<div class="windowbg">
				<p><?= Lang::getTxt('mboards_delete_cat_contains', file: 'ManageBoards') ?></p>
				<ul><?php foreach (Utils::$context['category']['children'] as $child): ?>
					<li><?= $child ?></li><?php endforeach; ?>
				</ul>
			</div>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('mboards_delete_what_do', file: 'ManageBoards') ?></h3>
			</div>
			<div class="windowbg">
				<p>
					<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" checked><?= Lang::getTxt('mboards_delete_option1', file: 'ManageBoards') ?></label><br>
					<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1"<?= count(Utils::$context['category_order']) == 1 ? ' disabled' : '' ?>><?= Lang::getTxt('mboards_delete_option2', file: 'ManageBoards') ?></label>
					<select name="cat_to"<?= count(Utils::$context['category_order']) == 1 ? ' disabled' : '' ?>><?php foreach (Utils::$context['category_order'] as $cat): ?><?php if ($cat['id'] != 0): ?>
						<option value="<?= $cat['id'] ?>"><?= $cat['true_name'] ?></option><?php endif; ?><?php endforeach; ?>
					</select>
				</p>
				<input type="submit" name="delete" value="<?= Lang::getTxt('mboards_delete_confirm', file: 'ManageBoards') ?>" class="button">
				<input type="submit" name="cancel" value="<?= Lang::getTxt('mboards_delete_cancel', file: 'ManageBoards') ?>" class="button">
				<input type="hidden" name="confirmation" value="1">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->