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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for the topic maintenance tasks.
 */
?><?php /* If maintenance has finished tell the user. */ ?><?php if (!empty(Utils::$context['maintenance_finished'])): ?>
	<div class="infobox">
		<?= Lang::getTxt('maintain_done', ['task' => Utils::$context['maintenance_finished']], file: 'Admin') ?>
	</div><?php endif; ?><?php /* Bit of javascript for showing which boards to prune in an otherwise hidden list. */ ?>
	<script>
		var rotSwap = false;
		function swapRot()
		{
			rotSwap = !rotSwap;

			// Toggle icon
			document.getElementById("rotIcon").src = smf_images_url + (rotSwap ? "/selected_open.png" : "/selected.png");
			setInnerHTML(document.getElementById("rotText"), rotSwap ? <?= Utils::escapeJavaScript(Lang::getTxt('maintain_old_choose', file: 'ManageMaintenance')) ?> : <?= Utils::escapeJavaScript(Lang::getTxt('maintain_old_all', file: 'ManageMaintenance')) ?>);

			// Toggle panel
			$("#rotPanel").slideToggle(300);

			// Toggle checkboxes
			var rotPanel = document.getElementById('rotPanel');
			var oBoardCheckBoxes = rotPanel.getElementsByTagName('input');
			for (var i = 0; i < oBoardCheckBoxes.length; i++)
			{
				if (oBoardCheckBoxes[i].type.toLowerCase() == "checkbox")
					oBoardCheckBoxes[i].checked = !rotSwap;
			}
		}
	</script>
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('maintain_old', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<div class="flow_auto">
				<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8"><?php /* The otherwise hidden "choose which boards to prune". */ ?>
					<p>
						<a id="rotLink"></a><?= Lang::getTxt('maintain_old_since_days', ['input_number' => '<input type="number" name="maxdays" value="30" size="3">'], file: 'ManageMaintenance') ?>
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing"> <?= Lang::getTxt('maintain_old_nothing_else', file: 'ManageMaintenance') ?></label><br>
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved" checked> <?= Lang::getTxt('maintain_old_are_moved', file: 'ManageMaintenance') ?></label><br>
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked"> <?= Lang::getTxt('maintain_old_are_locked', file: 'ManageMaintenance') ?></label><br>
					</p>
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" checked> <?= Lang::getTxt('maintain_old_are_not_stickied', file: 'ManageMaintenance') ?></label><br>
					</p>
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="<?= Theme::$current->settings['images_url'] ?>/selected.png" alt="+" id="rotIcon"></a> <a href="#rotLink" onclick="swapRot();" id="rotText" style="font-weight: bold;"><?= Lang::getTxt('maintain_old_all', file: 'ManageMaintenance') ?></a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
						<div class="floatleft" style="width: 49%"><?php
// This is the "middle" of the list.
$middle = ceil(count(Utils::$context['categories']) / 2);
$i = 0;
?><?php foreach (Utils::$context['categories'] as $category): ?>
							<fieldset>
								<legend><?= $category['name'] ?></legend>
								<ul><?php /* Display a checkbox with every board. */ ?><?php foreach ($category['boards'] as $board): ?>
									<li style="margin-inline-start: <?= $board['child_level'] * 1.5 ?>em;">
										<label for="boards_<?= $board['id'] ?>"><input type="checkbox" name="boards[<?= $board['id'] ?>]" id="boards_<?= $board['id'] ?>" checked><?= $board['name'] ?></label>
									</li><?php endforeach; ?>
								</ul>
							</fieldset><?php /* Increase $i, and check if we're at the middle yet. */ ?><?php if (++$i == $middle): ?>
						</div><!-- .floatleft -->
						<div class="floatright" style="width: 49%;"><?php endif; ?><?php endforeach; ?>
						</div>
					</div><!-- #rotPanel -->
					<input type="submit" value="<?= Lang::getTxt('maintain_old_remove', file: 'ManageMaintenance') ?>" data-confirm="<?= Lang::getTxt('maintain_old_confirm', file: 'ManageMaintenance') ?>" class="button you_sure">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
				</form>
			</div><!-- .flow_auto -->
		</div><!-- .windowbg -->

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('maintain_old_drafts', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=topics;activity=olddrafts" method="post" accept-charset="UTF-8">
				<p>
					<?= Lang::getTxt('maintain_old_drafts_days', ['input_number' => '<input type="number" name="draftdays" value="' . (!empty(Config::$modSettings['drafts_keep_days']) ? Config::$modSettings['drafts_keep_days'] : 30) . '" size="3">'], file: 'ManageMaintenance') ?>
				</p>
				<input type="submit" value="<?= Lang::getTxt('maintain_old_remove', file: 'ManageMaintenance') ?>" data-confirm="<?= Lang::getTxt('maintain_old_drafts_confirm', file: 'ManageMaintenance') ?>" class="button you_sure">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('move_topics_maintenance', file: 'ManageMaintenance') ?></h3>
		</div>
		<div class="windowbg">
			<form action="<?= Config::$scripturl ?>?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
				<p>
					<?php
$board_select = [
	'<option disabled selected>(' . Lang::getTxt('move_topics_select_board', file: 'ManageMaintenance') . ')</option>',
];
?><?php foreach (Utils::$context['categories'] as $category): ?><?php $board_select[] = '<optgroup label="' . $category['name'] . '">'; ?><?php foreach ($category['boards'] as $board): ?><?php $board_select[] = "\t" . '<option value="' . $board['id'] . '"> ' . str_repeat('==', $board['child_level']) . '=&gt;&nbsp;' . $board['name'] . '</option>'; ?><?php endforeach; ?><?php $board_select[] = '</optgroup>'; ?><?php endforeach; ?><?= Lang::getTxt(
	'move_topics_from',
	[
		'old' => '
						<select name="id_board_from" id="id_board_from">
							' . implode("\n\t\t\t\t\t\t", $board_select) . '
						</select>',
		'new' => '
						<select name="id_board_to" id="id_board_to">
							' . implode("\n\t\t\t\t\t\t", $board_select) . '
						</select>',
	],
	file: 'ManageMaintenance',
) ?>
				</p>
				<p>
					<?= Lang::getTxt('move_topics_older_than', ['input_number' => '<input type="number" name="maxdays" value="30" size="3">'], file: 'ManageMaintenance') ?> (<?= Lang::getTxt('move_zero_all', file: 'ManageMaintenance') ?>)
				</p>
				<p>
					<label for="move_type_locked"><input type="checkbox" name="move_type_locked" id="move_type_locked" checked> <?= Lang::getTxt('move_type_locked', file: 'ManageMaintenance') ?></label><br>
					<label for="move_type_sticky"><input type="checkbox" name="move_type_sticky" id="move_type_sticky"> <?= Lang::getTxt('move_type_sticky', file: 'ManageMaintenance') ?></label><br>
				</p>
				<input type="submit" value="<?= Lang::getTxt('move_topics_now', file: 'ManageMaintenance') ?>" onclick="if (document.getElementById('id_board_from').options[document.getElementById('id_board_from').selectedIndex].disabled || document.getElementById('id_board_from').options[document.getElementById('id_board_to').selectedIndex].disabled) return false; var confirmText = '<?= Lang::getTxt('move_topics_confirm', file: 'ManageMaintenance') ?>'; return confirm(confirmText.replace(/%board_from%/, document.getElementById('id_board_from').options[document.getElementById('id_board_from').selectedIndex].text.replace(/^=+&gt;&nbsp;/, '')).replace(/%board_to%/, document.getElementById('id_board_to').options[document.getElementById('id_board_to').selectedIndex].text.replace(/^=+&gt;&nbsp;/, '')));" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-maint_token_var'] ?>" value="<?= Utils::$context['admin-maint_token'] ?>">
			</form>
		</div><!-- .windowbg -->
	</div><!-- #manage_maintenance -->