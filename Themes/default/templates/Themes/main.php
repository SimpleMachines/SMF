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
 * The main sub template - for theme administration.
 */
?>

<?php /* Theme install info. */ ?>
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="<?= Config::$scripturl ?>?action=helpadmin;help=themes_manage" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
				<?= Lang::getTxt('themeadmin_install_title', file: 'Themes') ?>

			</h3>
		</div>
		<div class="information">
			<?= Lang::getTxt('themeadmin_explain', file: 'Themes') ?>

		</div>
		<form action="<?= Config::$scripturl ?>?action=admin;area=theme;sa=admin" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('settings', file: 'General') ?>

				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="options-theme_allow"> <?= Lang::getTxt('theme_allow', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="hidden" value="0" name="options[theme_allow]">
						<input type="checkbox" name="options[theme_allow]" id="options-theme_allow" value="1"<?= !empty(Config::$modSettings['theme_allow']) ? ' checked' : '' ?>>
					</dd>
					<dt>
						<label for="known_themes_list"><?= Lang::getTxt('themeadmin_selectable', file: 'Themes') ?></label>
					</dt>
					<dd>
						<div id="known_themes_list">
<?php foreach (Utils::$context['themes'] as $theme): ?>
							<label for="options-known_themes_<?= $theme['id'] ?>"><input type="checkbox" name="options[known_themes][]" id="options-known_themes_<?= $theme['id'] ?>" value="<?= $theme['id'] ?>"<?= $theme['known'] ? ' checked' : '' ?>> <?= $theme['name'] ?></label><br>
<?php endforeach; ?>
						</div>
						<a href="javascript:void(0);" onclick="document.getElementById('known_themes_list').classList.remove('hidden'); document.getElementById('known_themes_link').classList.add('hidden'); return false; " id="known_themes_link" class="hidden button floatnone"><?= Lang::getTxt('themeadmin_themelist_link', file: 'Themes') ?></a>
						<script>
							document.getElementById("known_themes_list").classList.add('hidden');
							document.getElementById("known_themes_link").classList.remove('hidden');
						</script>
					</dd>
					<dt>
						<label for="theme_guests"><?= Lang::getTxt('theme_guests', file: 'Themes') ?></label>
					</dt>
					<dd>
						<select name="options[theme_guests]" id="theme_guests">
<?php /* Put an option for each theme in the select box. */ ?>
<?php foreach (Utils::$context['themes'] as $theme): ?>
							<option value="<?= $theme['id'] ?>"<?= Config::$modSettings['theme_guests'] == $theme['id'] ? ' selected' : '' ?>><?= $theme['name'] ?></option>
<?php endforeach; ?>
						</select>
						<span class="smalltext pick_theme"><a href="<?= Config::$scripturl ?>?action=themechooser;u=-1;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="button floatnone"><?= Lang::getTxt('theme_select', file: 'Themes') ?></a></span>
					</dd>
					<dt>
						<label for="theme_reset"><?= Lang::getTxt('theme_reset', file: 'Themes') ?></label>
					</dt>
					<dd>
						<select name="theme_reset" id="theme_reset">
							<option value="-1" selected><?= Lang::getTxt('theme_nochange', file: 'Themes') ?></option>
							<option value="0"><?= Lang::getTxt('theme_forum_default', file: 'Themes') ?></option>
<?php /* Same thing, this time for changing the theme of everyone. */ ?>
<?php foreach (Utils::$context['themes'] as $theme): ?>
							<option value="<?= $theme['id'] ?>"><?= $theme['name'] ?></option>
<?php endforeach; ?>
						</select>
						<span class="smalltext pick_theme"><a href="<?= Config::$scripturl ?>?action=themechooser;u=0;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" class="button floatnone"><?= Lang::getTxt('theme_select', file: 'Themes') ?></a></span>
					</dd>
				</dl>
				<input type="submit" name="save" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-tm_token_var'] ?>" value="<?= Utils::$context['admin-tm_token'] ?>">
			</div><!-- .windowbg -->
		</form>
<?php /* Link to simplemachines.org for latest themes and info! */ ?>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt('theme_adding_title', file: 'Themes') ?>

			</h3>
		</div>
		<div class="windowbg">
			<?= Lang::getTxt('theme_adding', file: 'Themes') ?>

		</div>
<?php /* All the install options. */ ?>
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('theme_install', file: 'Themes') ?>

				</h3>
			</div>
			<div class="windowbg">
<?php if (Utils::$context['can_create_new']): ?>
<?php /* From a file. */ ?>
				<fieldset>
					<legend><?= Lang::getTxt('theme_install_file', file: 'Themes') ?></legend>
					<form action="<?= Config::$scripturl ?>?action=admin;area=theme;sa=install;do=file" method="post" accept-charset="UTF-8" enctype="multipart/form-data" class="padding">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="hidden" name="<?= Utils::$context['admin-t-file_token_var'] ?>" value="<?= Utils::$context['admin-t-file_token'] ?>">
						<input type="file" name="theme_gz" id="theme_gz" value="theme_gz" size="40" onchange="this.form.copy.disabled = this.value != ''; this.form.theme_dir.disabled = this.value != '';">
						<input type="submit" name="save_file" value="<?= Lang::getTxt('upload', file: 'General') ?>" class="button">
					</form>
				</fieldset>
<?php /* Copied from the default. */ ?>
				<fieldset>
					<legend><?= Lang::getTxt('theme_install_new', file: 'Themes') ?></legend>
					<form action="<?= Config::$scripturl ?>?action=admin;area=theme;sa=install;do=copy" method="post" accept-charset="UTF-8" class="padding">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="hidden" name="<?= Utils::$context['admin-t-copy_token_var'] ?>" value="<?= Utils::$context['admin-t-copy_token'] ?>">
						<input type="text" name="copy" id="copy" value="<?= Utils::$context['new_theme_name'] ?>" size="40">
						<input type="submit" name="save_copy" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
					</form>
				</fieldset>
<?php endif; ?>
<?php /* From a dir. */ ?>
				<fieldset>
					<legend><?= Lang::getTxt('theme_install_dir', file: 'Themes') ?></legend>
					<form action="<?= Config::$scripturl ?>?action=admin;area=theme;sa=install;do=dir" method="post" accept-charset="UTF-8" class="padding">
						<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						<input type="hidden" name="<?= Utils::$context['admin-t-dir_token_var'] ?>" value="<?= Utils::$context['admin-t-dir_token'] ?>">
						<input type="text" name="theme_dir" id="theme_dir" value="<?= Utils::$context['new_theme_dir'] ?>" size="40">
						<input type="submit" name="save_dir" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
					</form>
				</fieldset>
			</div><!-- .windowbg -->
		</div><!-- #admin_form_wrapper -->
	<script>
		window.smfForum_scripturl = smf_scripturl;
		window.smfForum_sessionid = smf_session_id;
		window.smfForum_sessionvar = smf_session_var;
		window.smfThemes_writable = <?= Utils::$context['can_create_new'] ? 'true' : 'false' ?>;
	</script>