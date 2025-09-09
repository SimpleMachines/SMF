<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * Download a new language file.
 */
function template_download_language()
{
	// Actually finished?
	if (!empty(Utils::$context['install_complete']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', Lang::getTxt('languages_download_complete', file: 'ManageSettings'), '
			</h3>
		</div>
		<div class="windowbg">
			', Utils::$context['install_complete'], '
		</div>';
		return;
	}

	// An error?
	if (!empty(Utils::$context['error_message']))
		echo '
	<div class="errorbox">
		', Utils::$context['error_message'], '
	</div>';

	// Provide something of an introduction...
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=languages;sa=downloadlang;did=', Utils::$context['download_id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('languages_download', file: 'ManageSettings'), '
				</h3>
			</div>
			<div class="windowbg">
				<p>
					', Lang::getTxt('languages_download_note', file: 'ManageSettings'), '
				</p>
				<div class="smalltext">
					', Lang::getTxt('languages_download_info', file: 'ManageSettings'), '
				</div>
			</div>';

	// Show the main files.
	template_show_list('lang_main_files_list');

	// Do we want some FTP baby?
	// If the files are not writable, we might!
	if (!empty(Utils::$context['still_not_writable']))
	{
		if (!empty(Utils::$context['package_ftp']['error']))
			echo '
			<div class="errorbox">
				', Utils::$context['package_ftp']['error'], '
			</div>';

		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('package_ftp_necessary', file: 'Packages'), '
				</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::getTxt('package_ftp_why', file: 'Packages'), '</p>
				<dl class="settings">
					<dt
						<label for="ftp_server">', Lang::getTxt('package_ftp_server', file: 'Packages'), '</label>
					</dt>
					<dd>
						<div class="floatright">
							<label for="ftp_port">
								', Lang::getTxt('package_ftp_port', file: 'Packages'), '
							</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset(Utils::$context['package_ftp']['port']) ? Utils::$context['package_ftp']['port'] : (isset(Config::$modSettings['package_port']) ? Config::$modSettings['package_port'] : '21'), '">
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset(Utils::$context['package_ftp']['server']) ? Utils::$context['package_ftp']['server'] : (isset(Config::$modSettings['package_server']) ? Config::$modSettings['package_server'] : 'localhost'), '" style="width: 70%;">
					</dd>

					<dt>
						<label for="ftp_username">', Lang::getTxt('package_ftp_username', file: 'Packages'), '</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset(Utils::$context['package_ftp']['username']) ? Utils::$context['package_ftp']['username'] : (isset(Config::$modSettings['package_username']) ? Config::$modSettings['package_username'] : ''), '">
					</dd>

					<dt>
						<label for="ftp_password">', Lang::getTxt('package_ftp_password', file: 'Packages'), '</label>
					</dt>
					<dd>
						<input type="password" size="50" name="ftp_password" id="ftp_password">
					</dd>

					<dt>
						<label for="ftp_path">', Lang::getTxt('package_ftp_path', file: 'Packages'), '</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', Utils::$context['package_ftp']['path'], '">
					</dd>
				</dl>
			</div><!-- .windowbg -->';
	}

	// Install?
	echo '
			<div class="righttext padding">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-dlang_token_var'], '" value="', Utils::$context['admin-dlang_token'], '">
				<input type="submit" name="do_install" value="', Lang::getTxt('add_language_smf_install', file: 'ManageSettings'), '" class="button">
			</div>
		</form>';
}

/**
 * Edit language entries. Note that this doesn't always work because of PHP's max_post_vars setting.
 */
function template_modify_language_entries()
{
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=languages;sa=editlang;lid=', Utils::$context['lang_id'], '" id="primary_settings" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('edit_languages', file: 'ManageSettings'), '
				</h3>
			</div>
			<div id="editlang_desc" class="information">
				', Lang::getTxt('edit_language_entries_primary', file: 'ManageSettings'), '
			</div>';

	// Not writable? Oops, show an error for ya.
	if (!empty(Utils::$context['lang_file_not_writable_message']))
		echo '
			<div class="errorbox">
				', Utils::$context['lang_file_not_writable_message'], '
			</div>';

	// Show the language entries
	echo '
			<div class="windowbg">
				<fieldset>
					<legend>', Utils::$context['primary_settings']['native_name']['value'], '</legend>
					<dl class="settings">';

	foreach (Utils::$context['primary_settings'] as $setting => $setting_info)
	{
		if ($setting != 'name')
			echo '
						<dt>
							<a id="settings_', $setting, '_help" href="', Config::$scripturl, '?action=helpadmin;help=languages_', $setting_info['label'], '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="', Lang::getTxt('help', file: 'General'), '"></span></a>
							<label for="', $setting, '">', Lang::getTxt('languages_' . $setting_info['label'], file: 'ManageSettings'), ':</label>
						</dt>
						<dd>
							<input type="', (is_bool($setting_info['value']) ? 'checkbox' : 'text'), '" name="', $setting, '" id="', $setting_info['label'], '" size="20"', (is_bool($setting_info['value']) ? (!empty($setting_info['value']) ? ' checked' : '') : ' value="' . $setting_info['value'] . '"'), (!empty(Utils::$context['lang_file_not_writable_message']) ? ' disabled' : ''), ' data-orig="' . (is_bool($setting_info['value']) ? (!empty($setting_info['value']) ? 'true' : 'false') : $setting_info['value']) . '">
						</dd>';
	}

	echo '
					</dl>
				</fieldset>
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				<input type="hidden" name="', Utils::$context['admin-mlang_token_var'], '" value="', Utils::$context['admin-mlang_token'], '">
				<input type="submit" name="save_main" value="', Lang::getTxt('save', file: 'General'), '"', !empty(Utils::$context['lang_file_not_writable_message']) ? ' disabled' : '', ' class="button">
				<input type="reset" id="reset_main" value="', Lang::getTxt('reset', file: 'General'), '" class="button">';

	// Allow deleting entries. English can't be deleted though.
	if (Utils::$context['lang_id'] != 'english')
		echo '
				<input type="submit" name="delete_main" value="', Lang::getTxt('delete', file: 'General'), '"', !empty(Utils::$context['lang_file_not_writable_message']) ? ' disabled' : '', ' onclick="return confirm(\'', Lang::getTxt('languages_delete_confirm', file: 'ManageSettings'), '\');" class="button">';

	echo '
			</div><!-- .windowbg -->
		</form>

		<form action="', Config::$scripturl, '?action=admin;area=languages;sa=editlang;lid=', Utils::$context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('edit_language_entries', file: 'ManageSettings'), '
				</h3>
			</div>
			<div class="information">
				<div>
					', Lang::getTxt('edit_language_entries_desc', file: 'ManageSettings'), '
				</div>
				<br>
				<div id="taskpad" class="floatright">
					', Lang::getTxt('edit_language_entries_file', file: 'ManageSettings'), '
					<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">
						<option value="-1">&nbsp;</option>';

	foreach (Utils::$context['possible_files'] as $id_theme => $theme)
	{
		echo '
						<optgroup label="', $theme['name'], '">';

		foreach ($theme['files'] as $file)
			echo '
							<option value="', $id_theme, '+', $file['id'], '"', $file['selected'] ? ' selected' : '', '>', $file['name'], '</option>';

		echo '
						</optgroup>';
	}

	echo '
					</select>
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="', Utils::$context['admin-mlang_token_var'], '" value="', Utils::$context['admin-mlang_token'], '">
					<input type="submit" value="', Lang::getTxt('go', file: 'General'), '" class="button" style="float: none">
				</div><!-- #taskpad -->
			</div><!-- .information -->';

	// Is it not writable? Show an error.
	if (!empty(Utils::$context['entries_not_writable_message']))
		echo '
			<div class="errorbox">
				', Utils::$context['entries_not_writable_message'], '
			</div>';

	// Already have some file entries?
	if (!empty(Utils::$context['file_entries']))
	{
		echo '
			<div id="entry_fields" class="windowbg">';

		$entry_num = 0;
		foreach (Utils::$context['file_entries'] as $group => $entries)
		{
			echo '
				<fieldset>
					<legend>
						<a id="settings_language_', $group, '_help" href="', Config::$scripturl, '?action=helpadmin;help=languages_', $group, '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="', Lang::getTxt('help', file: 'General'), '"></span></a>
						<span>', Lang::getTxt('languages_' . $group, file: 'ManageSettings'), '</span>
					</legend>
					<dl class="settings" id="language_', $group, '">';

			foreach ($entries as $entry)
			{
				++$entry_num;

				echo '
						<dt>
							<span>', $entry['key'], isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '</span>
						</dt>
						<dd id="entry_', $entry_num, '">';

				if ($entry['can_remove'])
					echo '
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_', $entry_num, '_none" class="entry_toggle" type="radio" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="" data-target="#entry_', $entry_num, '" checked>
								<label for="entry_', $entry_num, '_none">', Lang::getTxt('no_change', file: 'General'), '</label>
							</span>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_', $entry_num, '_edit" class="entry_toggle" type="radio" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="edit" data-target="#entry_', $entry_num, '">
								<label for="entry_', $entry_num, '_edit">', Lang::getTxt('edit', file: 'General'), '</label>
							</span>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_', $entry_num, '_remove" class="entry_toggle" type="radio" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="remove" data-target="#entry_', $entry_num, '">
								<label for="entry_', $entry_num, '_remove">', Lang::getTxt('remove', file: 'General'), '</label>
							</span>';
				else
					echo '
							<input id="entry_', $entry_num, '_edit" class="entry_toggle" type="checkbox" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="edit" data-target="#entry_', $entry_num, '">
							<label for="entry_', $entry_num, '_edit">', Lang::getTxt('edit', file: 'General'), '</label>';

				echo '
							</span>
							<input type="hidden" class="entry_oldvalue" name="comp[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="', $entry['value'], '">
							<textarea name="entry[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" class="entry_textfield" cols="40" rows="', $entry['rows'] < 2 ? 2 : ($entry['rows'] > 25 ? 25 : $entry['rows']), '" style="width: 96%; margin-bottom: 2em;">', $entry['value'], '</textarea>
						</dd>';
			}

			echo '
					</dl>';

			if (!empty(Utils::$context['can_add_lang_entry'][$group]))
			{
				echo '
				<span class="add_lang_entry_button" style="display: none;">
					<a class="button" href="javascript:void(0);" onclick="add_lang_entry(\'', $group, '\'); return false;">' . Lang::getTxt('edit_language_entries_add', file: 'Admin') . '</a>
				</span>
				<script>
					entry_num = ', $entry_num, ';
				</script>';
			}

			echo '
				</fieldset>';
		}

		echo '
				<input type="submit" name="save_entries" value="', Lang::getTxt('save', file: 'General'), '"', !empty(Utils::$context['entries_not_writable_message']) ? ' disabled' : '', ' class="button">
			</div><!-- .windowbg -->';
	}

	echo '
		</form>';
}

/**
 * Add a new language
 *
 */
function template_add_language()
{
	echo '
		<form id="admin_form_wrapper"action="', Config::$scripturl, '?action=admin;area=languages;sa=add;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt('add_language', file: 'ManageSettings'), '
				</h3>
			</div>
			<div class="windowbg">
				<fieldset>
					<legend>', Lang::getTxt('add_language_smf', file: 'ManageSettings'), '</legend>
					<label class="smalltext">', Lang::getTxt('add_language_smf_browse', file: 'ManageSettings'), '</label>
					<input type="text" name="smf_add" size="40" value="', !empty(Utils::$context['smf_search_term']) ? Utils::$context['smf_search_term'] : '', '">';

	// Do we have some errors? Too bad. Display a little error box.
	if (!empty(Utils::$context['smf_error']))
		echo '
					<div>
						<br>
						<p class="errorbox">', Lang::getTxt('add_language_error_' . Utils::$context['smf_error'], file: 'ManageSettings'), '</p>
					</div>';

	echo '
				</fieldset>
				', BrowserDetector::isBrowser('is_ie') ? '<input type="text" name="ie_fix" style="display: none;"> ' : '', '
				<input type="submit" name="smf_add_sub" value="', Lang::getTxt('search', file: 'General'), '" class="button">
				<br>
			</div><!-- .windowbg -->';

	// Had some results?
	if (!empty(Utils::$context['smf_languages']['rows']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('add_language_found_title', file: 'ManageSettings'), '</h3>
			</div>
			<div class="information">', Lang::getTxt('add_language_smf_found', file: 'ManageSettings'), '</div>';

		template_show_list('smf_languages');
	}

	echo '
		</form>';
}
