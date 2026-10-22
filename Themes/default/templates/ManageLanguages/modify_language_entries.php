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
 * Edit language entries. Note that this doesn't always work because of PHP's max_post_vars setting.
 */
?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=languages;sa=editlang;lid=<?= Utils::$context['lang_id'] ?>" id="primary_settings" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('edit_languages', file: 'ManageSettings') ?>
				</h3>
			</div>
			<div id="editlang_desc" class="information">
				<?= Lang::getTxt('edit_language_entries_primary', file: 'ManageSettings') ?>
			</div>
<?php /* Not writable? Oops, show an error for ya. */ ?>
<?php if (!empty(Utils::$context['lang_file_not_writable_message'])): ?>
			<div class="errorbox">
				<?= Utils::$context['lang_file_not_writable_message'] ?>
			</div>
<?php endif; ?>
<?php /* Show the language entries */ ?>
			<div class="windowbg">
				<fieldset>
					<legend><?= Utils::$context['primary_settings']['native_name']['value'] ?></legend>
					<dl class="settings">
<?php foreach (Utils::$context['primary_settings'] as $setting => $setting_info): ?>
<?php if ($setting != 'name'): ?>
						<dt>
							<a id="settings_<?= $setting ?>_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=languages_<?= $setting_info['label'] ?>" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
							<label for="<?= $setting ?>"><?= Lang::getTxt('languages_' . $setting_info['label'], file: 'ManageSettings') ?>:</label>
						</dt>
						<dd>
							<input type="<?= (is_bool($setting_info['value']) ? 'checkbox' : 'text') ?>" name="<?= $setting ?>" id="<?= $setting_info['label'] ?>" size="20"<?= (is_bool($setting_info['value']) ? (!empty($setting_info['value']) ? ' checked' : '') : ' value="' . $setting_info['value'] . '"') ?><?= (!empty(Utils::$context['lang_file_not_writable_message']) ? ' disabled' : '') ?> data-orig="<?= (is_bool($setting_info['value']) ? (!empty($setting_info['value']) ? 'true' : 'false') : $setting_info['value']) ?>">
						</dd>
<?php endif; ?>
<?php endforeach; ?>
					</dl>
				</fieldset>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-mlang_token_var'] ?>" value="<?= Utils::$context['admin-mlang_token'] ?>">
				<input type="submit" name="save_main" value="<?= Lang::getTxt('save', file: 'General') ?>"<?= !empty(Utils::$context['lang_file_not_writable_message']) ? ' disabled' : '' ?> class="button">
				<input type="reset" id="reset_main" value="<?= Lang::getTxt('reset', file: 'General') ?>" class="button">
<?php /* Allow deleting entries. The default language and en_US can't go. */ ?>
<?php if (Utils::$context['can_delete_language']): ?>
				<input type="submit" name="delete_main" value="<?= Lang::getTxt('delete', file: 'General') ?>"<?= !empty(Utils::$context['lang_file_not_writable_message']) ? ' disabled' : '' ?> data-confirm="<?= Lang::getTxt('languages_delete_confirm', file: 'ManageSettings') ?>" class="button you_sure">
<?php endif; ?>
			</div><!-- .windowbg -->
		</form>

		<form action="<?= Config::$scripturl ?>?action=admin;area=languages;sa=editlang;lid=<?= Utils::$context['lang_id'] ?>;entries" id="entry_form" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('edit_language_entries', file: 'ManageSettings') ?>
				</h3>
			</div>
			<div class="information">
				<div>
					<?= Lang::getTxt('edit_language_entries_desc', file: 'ManageSettings') ?>
				</div>
				<br>
				<div id="taskpad" class="floatright">
					<?= Lang::getTxt('edit_language_entries_file', file: 'ManageSettings') ?>
					<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">
						<option value="-1">&nbsp;</option>
<?php foreach (Utils::$context['possible_files'] as $id_theme => $theme): ?>
						<optgroup label="<?= $theme['name'] ?>">
<?php foreach ($theme['files'] as $file): ?>
							<option value="<?= $id_theme ?>+<?= $file['id'] ?>"<?= $file['selected'] ? ' selected' : '' ?>><?= $file['name'] ?></option>
<?php endforeach; ?>
						</optgroup>
<?php endforeach; ?>
					</select>
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="<?= Utils::$context['admin-mlang_token_var'] ?>" value="<?= Utils::$context['admin-mlang_token'] ?>">
					<input type="submit" value="<?= Lang::getTxt('go', file: 'General') ?>" class="button" style="float: none">
				</div><!-- #taskpad -->
			</div><!-- .information -->
<?php /* Is it not writable? Show an error. */ ?>
<?php if (!empty(Utils::$context['entries_not_writable_message'])): ?>
			<div class="errorbox">
				<?= Utils::$context['entries_not_writable_message'] ?>
			</div>
<?php endif; ?>
<?php /* Already have some file entries? */ ?>
<?php if (!empty(Utils::$context['file_entries'])): ?>
			<div id="entry_fields" class="windowbg">
<?php $entry_num = 0; ?>
<?php foreach (Utils::$context['file_entries'] as $group => $entries): ?>
				<fieldset>
					<legend>
						<a id="settings_language_<?= $group ?>_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=languages_<?= $group ?>" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
						<span><?= Lang::getTxt('languages_' . $group, file: 'ManageSettings') ?></span>
					</legend>
					<dl class="settings" id="language_<?= $group ?>">
<?php foreach ($entries as $entry): ?>
<?php ++$entry_num; ?>
						<dt>
							<span><?= $entry['key'] ?><?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?></span>
						</dt>
						<dd id="entry_<?= $entry_num ?>">
<?php if ($entry['can_remove']): ?>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_<?= $entry_num ?>_none" class="entry_toggle" type="radio" name="edit[<?= $entry['key'] ?>]<?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?>" value="" data-target="#entry_<?= $entry_num ?>" checked>
								<label for="entry_<?= $entry_num ?>_none"><?= Lang::getTxt('no_change', file: 'General') ?></label>
							</span>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_<?= $entry_num ?>_edit" class="entry_toggle" type="radio" name="edit[<?= $entry['key'] ?>]<?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?>" value="edit" data-target="#entry_<?= $entry_num ?>">
								<label for="entry_<?= $entry_num ?>_edit"><?= Lang::getTxt('edit', file: 'General') ?></label>
							</span>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_<?= $entry_num ?>_remove" class="entry_toggle" type="radio" name="edit[<?= $entry['key'] ?>]<?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?>" value="remove" data-target="#entry_<?= $entry_num ?>">
								<label for="entry_<?= $entry_num ?>_remove"><?= Lang::getTxt('remove', file: 'General') ?></label>
							</span>
<?php else: ?>
							<input id="entry_<?= $entry_num ?>_edit" class="entry_toggle" type="checkbox" name="edit[<?= $entry['key'] ?>]<?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?>" value="edit" data-target="#entry_<?= $entry_num ?>">
							<label for="entry_<?= $entry_num ?>_edit"><?= Lang::getTxt('edit', file: 'General') ?></label>
<?php endif; ?>
							</span>
							<input type="hidden" class="entry_oldvalue" name="comp[<?= $entry['key'] ?>]<?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?>" value="<?= $entry['value'] ?>">
							<textarea name="entry[<?= $entry['key'] ?>]<?= isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '' ?>" class="entry_textfield" cols="40" rows="<?= $entry['rows'] < 2 ? 2 : ($entry['rows'] > 25 ? 25 : $entry['rows']) ?>" style="width: 96%; margin-bottom: 2em;"><?= $entry['value'] ?></textarea>
						</dd>
<?php endforeach; ?>
					</dl>
<?php if (!empty(Utils::$context['can_add_lang_entry'][$group])): ?>
				<span class="add_lang_entry_button" style="display: none;">
					<a class="button" href="javascript:void(0);" onclick="add_lang_entry('<?= $group ?>'); return false;"><?= Lang::getTxt('edit_language_entries_add', file: 'ManageSettings') ?></a>
				</span>
				<script>
					entry_num = <?= $entry_num ?>;
				</script>
<?php endif; ?>
				</fieldset>
<?php endforeach; ?>
				<input type="submit" name="save_entries" value="<?= Lang::getTxt('save', file: 'General') ?>"<?= !empty(Utils::$context['entries_not_writable_message']) ? ' disabled' : '' ?> class="button">
			</div><!-- .windowbg -->
<?php endif; ?>
		</form>