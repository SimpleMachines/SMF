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
 * Editing an individual message icon
 */
?>
	<form action="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=editicon;icon=<?= Utils::$context['new_icon'] ? '0' : Utils::$context['icon']['id'] ?>" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Utils::$context['new_icon'] ? Lang::getTxt('icons_new_icon', file: 'ManageSmileys') : Lang::getTxt('icons_edit_icon', file: 'ManageSmileys') ?>
			</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
<?php if (!Utils::$context['new_icon']): ?>
				<dt>
					<strong><?= Lang::getTxt('smiley_preview', file: 'ManageSmileys') ?></strong>
				</dt>
				<dd>
					<img src="<?= Utils::$context['icon']['image_url'] ?>" alt="<?= Utils::$context['icon']['title'] ?>">
				</dd>
<?php endif; ?>
				<dt>
					<strong><label for="icon_filename"><?= Lang::getTxt('smileys_filename', file: 'ManageSmileys') ?></label>: </strong><br><span class="smalltext"><?= Lang::getTxt('icons_extension_must_be', ['extension' => '.png'], file: 'ManageSmileys') ?></span>
				</dt>
				<dd>
					<input type="text" name="icon_filename" id="icon_filename" value="<?= !empty(Utils::$context['icon']['filename']) ? Utils::$context['icon']['filename'] . '.png' : '' ?>">
				</dd>
				<dt>
					<strong><label for="icon_description"><?= Lang::getTxt('smileys_description', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="icon_description" id="icon_description" value="<?= !empty(Utils::$context['icon']['title']) ? Utils::$context['icon']['title'] : '' ?>">
				</dd>
				<dt>
					<strong><label for="icon_board_select"><?= Lang::getTxt('icons_board', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<select name="icon_board" id="icon_board_select">
						<option value="0"<?= empty(Utils::$context['icon']['board_id']) ? ' selected' : '' ?>><?= Lang::getTxt('icons_edit_icons_all_boards', file: 'ManageSmileys') ?></option>
<?php foreach (Utils::$context['categories'] as $category): ?>
						<optgroup label="<?= $category['name'] ?>">
<?php foreach ($category['boards'] as $board): ?>
							<option value="<?= $board['id'] ?>"<?= $board['selected'] ? ' selected' : '' ?>><?= $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '' ?> <?= $board['name'] ?></option>
<?php endforeach; ?>
						</optgroup>
<?php endforeach; ?>
					</select>
				</dd>
				<dt>
					<strong><label for="icon_location"><?= Lang::getTxt('smileys_location', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<select name="icon_location" id="icon_location">
						<option value="0"<?= empty(Utils::$context['icon']['after']) ? ' selected' : '' ?>><?= Lang::getTxt('icons_location_first_icon', file: 'ManageSmileys') ?></option><?php /* Print the list of all the icons it can be put after... */ ?>

<?php foreach (Utils::$context['icons'] as $id => $data): ?>
<?php if (empty(Utils::$context['icon']['id']) || $id != Utils::$context['icon']['id']): ?>
						<option value="<?= $id ?>"<?= !empty(Utils::$context['icon']['after']) && $id == Utils::$context['icon']['after'] ? ' selected' : '' ?>><?= Lang::getTxt('icons_location_after', ['icon' => $data['title']], file: 'ManageSmileys') ?></option>
<?php endif; ?>
<?php endforeach; ?>
					</select>
				</dd>
			</dl>
<?php if (!Utils::$context['new_icon']): ?>
			<input type="hidden" name="icon" value="<?= Utils::$context['icon']['id'] ?>">
<?php endif; ?>
			<input type="submit" name="icons_save" value="<?= Lang::getTxt('smileys_save', file: 'ManageSmileys') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</div><!-- .windowbg -->
	</form>