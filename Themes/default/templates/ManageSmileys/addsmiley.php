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
 * Adding a new smiley.
 */
?>
	<form action="<?= Config::$scripturl ?>?action=admin;area=smileys;sa=addsmiley" method="post" accept-charset="UTF-8" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('smileys_add_method', file: 'ManageSmileys') ?></h3>
		</div>
		<div class="windowbg">
			<ul>
				<li>
					<label for="method-existing"><input type="radio" onclick="switchType();" name="method" id="method-existing" value="existing" checked> <?= Lang::getTxt('smileys_add_existing', file: 'ManageSmileys') ?></label>
				</li>
				<li>
					<label for="method-upload"><input type="radio" onclick="switchType();" name="method" id="method-upload" value="upload"> <?= Lang::getTxt('smileys_add_upload', file: 'ManageSmileys') ?></label>
				</li>
			</ul>
			<br>
			<fieldset id="ex_settings">
				<dl class="settings">
					<dt>
						<strong><label for="preview"><?= Lang::getTxt('smiley_preview', file: 'ManageSmileys') ?></label></strong>
					</dt>
					<dd>
						<img src="<?= Config::$modSettings['smileys_url'] ?>/<?= Config::$modSettings['smiley_sets_default'] ?>/<?= Utils::$context['filenames'][Utils::$context['selected_set']]['smiley']['id'] ?>" id="preview" alt="">
					</dd>
					<dt>
						<strong><label for="smiley_filename"><?= Lang::getTxt('smileys_filename', file: 'ManageSmileys') ?></label></strong>
					</dt>
					<dd>
<?php if (empty(Utils::$context['filenames'])): ?>
						<input type="text" name="smiley_filename" id="smiley_filename" value="<?= Utils::$context['current_smiley']['filename'] ?>" onchange="selectMethod('existing');">
<?php else: ?>
						<select name="smiley_filename" id="smiley_filename" onchange="updatePreview($('#smiley_filename').val());selectMethod('existing');">
<?php foreach (Utils::$context['smiley_sets'] as $smiley_set): ?>
							<optgroup label="<?= $smiley_set['name'] ?>">
<?php if (!empty(Utils::$context['filenames'][$smiley_set['path']])): ?>
<?php foreach (Utils::$context['filenames'][$smiley_set['path']] as $filename): ?>
								<option value="<?= $smiley_set['path'] ?>/<?= $filename['id'] ?>"<?= $filename['selected'] ? ' selected' : '' ?>><?= $filename['id'] ?></option>
<?php endforeach; ?>
<?php endif; ?>
							</optgroup>
<?php endforeach; ?>
						</select>
<?php endif; ?>
					</dd>
				</dl>
			</fieldset>
			<fieldset id="ul_settings" style="display: none;">
				<dl class="settings">
					<dt>
						<a href="<?= Config::$scripturl ?>?action=helpadmin;help=smiley_sameall" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
						<strong><label for="sameall"><?= Lang::getTxt('smileys_add_upload_all', file: 'ManageSmileys') ?></label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="sameall" id="sameall" onclick="swapUploads(); selectMethod('upload');" checked>
					</dd>
					<dt>
						<strong><?= Lang::getTxt('smileys_add_upload_choose', file: 'ManageSmileys') ?></strong>
					</dt>
					<dt class="upload_sameall">
						<?= Lang::getTxt('smileys_add_upload_choose_desc', file: 'ManageSmileys') ?>
					</dt>
					<dd class="upload_sameall">
						<input type="file" name="uploadSmiley" id="uploadSmiley" onchange="selectMethod('upload');">
					</dd>
<?php foreach (Utils::$context['smiley_sets'] as $smiley_set): ?>
					<dt class="upload_more" style="display: none;">
						<?= Lang::getTxt('smileys_add_upload_for', ['name' => '<strong>' . $smiley_set['name'] . '</strong>'], file: 'ManageSmileys') ?>:
					</dt>
					<dd class="upload_more" style="display: none;">
						<input type="file" name="individual_<?= $smiley_set['path'] ?>" disabled onchange="selectMethod('upload');">
					</dd>
<?php endforeach; ?>
				</dl>
			</fieldset>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('smiley_new', file: 'ManageSmileys') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><label for="smiley_code"><?= Lang::getTxt('smileys_code', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="smiley_code" id="smiley_code" value="">
				</dd>
				<dt>
					<strong><label for="smiley_description"><?= Lang::getTxt('smileys_description', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="smiley_description" id="smiley_description" value="">
				</dd>
				<dt>
					<strong><label for="smiley_location"><?= Lang::getTxt('smileys_location', file: 'ManageSmileys') ?></label></strong>
				</dt>
				<dd>
					<select name="smiley_location" id="smiley_location">
						<option value="0" selected>
							<?= Lang::getTxt('smileys_location_form', file: 'ManageSmileys') ?>
						</option>
						<option value="1">
							<?= Lang::getTxt('smileys_location_hidden', file: 'ManageSmileys') ?>
						</option>
						<option value="2">
							<?= Lang::getTxt('smileys_location_popup', file: 'ManageSmileys') ?>
						</option>
					</select>
				</dd>
			</dl>
			<input type="submit" name="smiley_save" value="<?= Lang::getTxt('smileys_save', file: 'ManageSmileys') ?>" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>