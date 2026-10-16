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
 * Editing an individual smiley
 */
?>
	<form action="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="UTF-8" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('smiley_modify_existing', file: 'ManageSmileys') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><?= Lang::getTxt('smiley_preview_using_set', file: 'ManageSmileys') ?></strong>
					<select id="set" onchange="updatePreview($('#smiley_filename_' + $('#set').val()).val(), $('#set').val());">
<?php foreach (Utils::$context['smiley_sets'] as $smiley_set): ?>
					<option value="<?= $smiley_set['path'] ?>"<?= Utils::$context['selected_set'] == $smiley_set['path'] ? ' selected' : '' ?>><?= $smiley_set['name'] ?></option>
<?php endforeach; ?>
					</select>
				</dt>
				<dd>
					<img src="<?= Config::$modSettings['smileys_url'] ?>/<?= Config::$modSettings['smiley_sets_default'] ?>/<?= Utils::$context['current_smiley']['filename'] ?>" id="preview" alt="">
				</dd>
				<dt>
					<strong><label for="smiley_filename"><?= Lang::getTxt('smileys_filename', file: 'ManageSmileys') ?></label></strong>
				</dt>
<?php if (empty(Utils::$context['filenames'])): ?>
				<dd>
					<input type="text" name="smiley_filename" id="smiley_filename" value="<?= Utils::$context['current_smiley']['filename'] ?>">
				</dd>
<?php else: ?>
<?php foreach (Utils::$context['smiley_sets'] as $set => $smiley_set): ?>
				<dt>
					<?= $smiley_set['name'] ?>
				</dt>
				<dd<?= in_array($set, Utils::$context['missing_sets']) ? ' class="errorbox"' : '' ?>>
					<select name="smiley_filename[<?= $set ?>]" id="smiley_filename_<?= $set ?>" onchange="$('#set').val('<?= $set ?>');updatePreview($('#smiley_filename_' + $('#set').val()).val(), $('#set').val());">
<?php foreach (Utils::$context['filenames'][$set] as $filename): ?>
						<option value="<?= $filename['id'] ?>"<?= $filename['selected'] ? ' selected' : '' ?><?= $filename['disabled'] ? ' disabled' : '' ?>><?= $filename['id'] ?></option>
<?php endforeach; ?>
					</select>
					<input type="file" name="smiley_upload[<?= $set ?>]" id="smiley_upload_<?= $set ?>">
				</dd>
<?php endforeach; ?>
<?php endif; ?>
			</dl>
			<dl class="settings">
				<dt>
					<strong><label for="smiley_code"><?= Lang::getTxt('smileys_code', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="smiley_code" id="smiley_code" value="<?= Utils::$context['current_smiley']['code'] ?>">
				</dd>
				<dt>
					<strong><label for="smiley_description"><?= Lang::getTxt('smileys_description', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="smiley_description" id="smiley_description" value="<?= Utils::$context['current_smiley']['description'] ?>">
				</dd>
				<dt>
					<strong><label for="smiley_location"><?= Lang::getTxt('smileys_location', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<select name="smiley_location" id="smiley_location">
						<option value="0"<?= Utils::$context['current_smiley']['location'] == 0 ? ' selected' : '' ?>>
							<?= Lang::getTxt('smileys_location_form', file: 'ManageSmileys') ?>
						</option>
						<option value="1"<?= Utils::$context['current_smiley']['location'] == 1 ? ' selected' : '' ?>>
							<?= Lang::getTxt('smileys_location_hidden', file: 'ManageSmileys') ?>
						</option>
						<option value="2"<?= Utils::$context['current_smiley']['location'] == 2 ? ' selected' : '' ?>>
							<?= Lang::getTxt('smileys_location_popup', file: 'ManageSmileys') ?>
						</option>
					</select>
				</dd>
			</dl>
			<input type="submit" name="smiley_save" value="<?= Lang::getTxt('smileys_save', file: 'ManageSmileys') ?>" class="button">
			<input type="submit" name="deletesmiley" value="<?= Lang::getTxt('smileys_delete', file: 'ManageSmileys') ?>" data-confirm="<?= Lang::getTxt('smileys_delete_confirm', file: 'ManageSmileys') ?>" class="button you_sure">
		</div><!-- .windowbg -->
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		<input type="hidden" name="smiley" value="<?= Utils::$context['current_smiley']['id'] ?>">
	</form>