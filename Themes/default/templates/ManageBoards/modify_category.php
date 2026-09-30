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
 * Template for editing/adding a category on the forum.
 */
?><?php /* Print table header. */ ?>

	<div id="manage_boards">
		<form action="<?= Config::$scripturl ?>?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="<?= Utils::$context['category']['id'] ?>">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt(isset(Utils::$context['category']['is_new']) ? 'mboards_new_cat_name' : 'cat_edit', file: 'ManageBoards') ?>

				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings"><?php /* If this isn't the only category, let the user choose where this category should be positioned down the board index. */ ?><?php if (count(Utils::$context['category_order']) > 1): ?>

					<dt><strong><?= Lang::getTxt('order', file: 'ManageBoards') ?></strong></dt>
					<dd>
						<select name="cat_order"><?php /* Print every existing category into a select box. */ ?><?php foreach (Utils::$context['category_order'] as $order): ?>

							<option<?= $order['selected'] ? ' selected' : '' ?> value="<?= $order['id'] ?>"><?= $order['name'] ?></option><?php endforeach; ?>

						</select>
					</dd><?php endif; ?><?php /* Allow the user to edit the category name and/or choose whether you can collapse the category. */ ?>

					<dt>
						<strong><?= Lang::getTxt('full_name', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('name_on_display', file: 'ManageBoards') ?></span>
					</dt>
					<dd>
						<input type="text" name="cat_name" value="<?= Utils::$context['category']['editable_name'] ?>" size="30">
					</dd>
					<dt>
						<strong><?= Lang::getTxt('mboards_description', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('mboards_cat_description_desc', ['allowed_tags' => implode(', ', Utils::$context['description_allowed_tags'])], file: 'ManageBoards') ?></span>
					</dt>
					<dd>
						<textarea name="cat_desc" rows="3" cols="35"><?= Utils::$context['category']['description'] ?></textarea>
					</dd>
					<dt>
						<strong><?= Lang::getTxt('collapse_enable', file: 'ManageBoards') ?></strong><br>
						<span class="smalltext"><?= Lang::getTxt('collapse_desc', file: 'ManageBoards') ?></span>
					</dt>
					<dd>
						<input type="checkbox" name="collapse"<?= Utils::$context['category']['can_collapse'] ? ' checked' : '' ?>>
					</dd><?php /* Show any category settings added by mods using the 'integrate_edit_category' hook. */ ?><?php if (!empty(Utils::$context['custom_category_settings']) && is_array(Utils::$context['custom_category_settings'])): ?><?php foreach (Utils::$context['custom_category_settings'] as $catset_id => $catset): ?><?php if (!empty($catset['dt']) && !empty($catset['dd'])): ?>

					<dt class="clear<?= !is_numeric($catset_id) ? ' catset_' . $catset_id : '' ?>">
						<?= $catset['dt'] ?>

					</dt>
					<dd<?= !is_numeric($catset_id) ? ' class="catset_' . $catset_id . '"' : '' ?>>
						<?= $catset['dd'] ?>

					</dd><?php endif; ?><?php endforeach; ?><?php endif; ?><?php /* Table footer. */ ?>

				</dl><?php if (isset(Utils::$context['category']['is_new'])): ?>

				<input type="submit" name="add" value="<?= Lang::getTxt('mboards_add_cat_button', file: 'ManageBoards') ?>" onclick="return !isEmptyText(this.form.cat_name);" class="button"><?php else: ?>

				<input type="submit" name="edit" value="<?= Lang::getTxt('modify', file: 'General') ?>" onclick="return !isEmptyText(this.form.cat_name);" class="button">
				<input type="submit" name="delete" value="<?= Lang::getTxt('mboards_delete_cat', file: 'ManageBoards') ?>" data-confirm="<?= Lang::getTxt('cat_delete_confirm', file: 'ManageBoards') ?>" class="button you_sure"><?php endif; ?>

				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>"><?php /* If this category is empty we don't bother with the next confirmation screen. */ ?><?php if (Utils::$context['category']['is_empty']): ?>

				<input type="hidden" name="empty" value="1"><?php endif; ?>

			</div><!-- .windowbg -->
		</form>
	</div><!-- #manage_boards -->