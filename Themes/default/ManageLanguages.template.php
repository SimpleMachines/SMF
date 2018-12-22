<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * Download a new language file.
 */
function template_download_language()
{
	global $context, $txt, $scripturl, $modSettings;

	// Actually finished?
	if (!empty($context['install_complete']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['languages_download_complete'], '
			</h3>
		</div>
		<div class="windowbg">
			', $context['install_complete'], '
		</div>';
		return;
	}

	// An error?
	if (!empty($context['error_message']))
		echo '
	<div class="errorbox">
		', $context['error_message'], '
	</div>';

	// Provide something of an introduction...
	echo '
		<form action="', $scripturl, '?action=admin;area=languages;sa=downloadlang;did=', $context['download_id'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['languages_download'], '
				</h3>
			</div>
			<div class="windowbg">
				<p>
					', $txt['languages_download_note'], '
				</p>
				<div class="smalltext">
					', $txt['languages_download_info'], '
				</div>
			</div>';

	// Show the main files.
	template_show_list('lang_main_files_list');

	// Do we want some FTP baby?
	// If the files are not writable, we might!
	if (!empty($context['still_not_writable']))
	{
		if (!empty($context['package_ftp']['error']))
			echo '
			<div class="errorbox">
				', $context['package_ftp']['error'], '
			</div>';

		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['package_ftp_necessary'], '
				</h3>
			</div>
			<div class="windowbg">
				<p>', $txt['package_ftp_why'], '</p>
				<dl class="settings">
					<dt
						<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright">
							<label for="ftp_port">
								', $txt['package_ftp_port'], ':
							</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'), '">
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'), '" style="width: 70%;">
					</dd>

					<dt>
						<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''), '">
					</dd>

					<dt>
						<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="50" name="ftp_password" id="ftp_password">
					</dd>

					<dt>
						<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '">
					</dd>
				</dl>
			</div><!-- .windowbg -->';
	}

	// Install?
	echo '
			<div class="righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-dlang_token_var'], '" value="', $context['admin-dlang_token'], '">
				<input type="submit" name="do_install" value="', $txt['add_language_smf_install'], '" class="button">
			</div>
		</form>';
}

/**
 * Edit language entries. Note that this doesn't always work because of PHP's max_post_vars setting.
 */
function template_modify_language_entries()
{
	global $context, $txt, $scripturl;

	echo '
		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" id="primary_settings" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_languages'], '
				</h3>
			</div>
			<div id="editlang_desc" class="information">
				', $txt['edit_language_entries_primary'], '
			</div>';

	// Not writable? Oops, show an error for ya.
	if (!empty($context['lang_file_not_writable_message']))
		echo '
			<div class="errorbox">
				', $context['lang_file_not_writable_message'], '
			</div>';

	// Show the language entries
	echo '
			<div class="windowbg">
				<fieldset>
					<legend>', $context['primary_settings']['name'], '</legend>
					<dl class="settings">';

	foreach ($context['primary_settings'] as $setting => $setting_info)
	{
		if ($setting != 'name')
			echo '
						<dt>
							<a id="settings_', $setting, '_help" href="', $scripturl, '?action=helpadmin;help=languages_', $setting_info['label'], '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="', $txt['help'], '"></span></a>
							<label for="', $setting, '">', $txt['languages_' . $setting_info['label']], ':</label>
						</dt>
						<dd>
							<input type="', (is_bool($setting_info['value']) ? 'checkbox' : 'text'), '" name="', $setting, '" id="', $setting_info['label'], '" size="20"', (is_bool($setting_info['value']) ? (!empty($setting_info['value']) ? ' checked' : '') : ' value="' . $setting_info['value'] . '"'), (!empty($context['lang_file_not_writable_message']) ? ' disabled' : ''), ' data-orig="' . (is_bool($setting_info['value']) ? (!empty($setting_info['value']) ? 'true' : 'false') : $setting_info['value']) . '">
						</dd>';
	}

	echo '
					</dl>
				</fieldset>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '">
				<input type="submit" name="save_main" value="', $txt['save'], '"', !empty($context['lang_file_not_writable_message']) ? ' disabled' : '', ' class="button">
				<input type="reset" id="reset_main" value="', $txt['reset'], '" class="button">';

	// Allow deleting entries. English can't be deleted though.
	if ($context['lang_id'] != 'english')
		echo '
				<input type="submit" name="delete_main" value="', $txt['delete'], '"', !empty($context['lang_file_not_writable_message']) ? ' disabled' : '', ' onclick="confirm(\'', $txt['languages_delete_confirm'], '\');" class="button">';

	echo '
			</div><!-- .windowbg -->
		</form>

		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_language_entries'], '
				</h3>
			</div>
			<div class="information">
				<div>
					', $txt['edit_language_entries_desc'], '
				</div>
				<br>
				<div id="taskpad" class="floatright">
					', $txt['edit_language_entries_file'], ':
					<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">
						<option value="-1">&nbsp;</option>';

	foreach ($context['possible_files'] as $id_theme => $theme)
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
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '">
					<input type="submit" value="', $txt['go'], '" class="button" style="float: none">
				</div><!-- #taskpad -->
			</div><!-- .information -->';

	// Is it not writable? Show an error.
	if (!empty($context['entries_not_writable_message']))
		echo '
			<div class="errorbox">
				', $context['entries_not_writable_message'], '
			</div>';

	// Already have some file entries?
	if (!empty($context['file_entries']))
	{
		echo '
			<div id="entry_fields" class="windowbg">';

		$entry_num = 0;
		foreach ($context['file_entries'] as $group => $entries)
		{
			echo '
				<fieldset>
					<legend>
						<a id="settings_language_', $group, '_help" href="', $scripturl, '?action=helpadmin;help=languages_', $group, '" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="', $txt['help'], '"></span></a>
						<span>', $txt['languages_' . $group], '</span>
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
								<label for="entry_', $entry_num, '_none">', $txt['no_change'], '</label>
							</span>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_', $entry_num, '_edit" class="entry_toggle" type="radio" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="edit" data-target="#entry_', $entry_num, '">
								<label for="entry_', $entry_num, '_edit">', $txt['edit'], '</label>
							</span>
							<span style="margin-right: 1ch; white-space: nowrap">
								<input id="entry_', $entry_num, '_remove" class="entry_toggle" type="radio" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="remove" data-target="#entry_', $entry_num, '">
								<label for="entry_', $entry_num, '_remove">', $txt['remove'], '</label>
							</span>';
				else
					echo '
							<input id="entry_', $entry_num, '_edit" class="entry_toggle" type="checkbox" name="edit[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="edit" data-target="#entry_', $entry_num, '">
							<label for="entry_', $entry_num, '_edit">', $txt['edit'], '</label>';

				echo '
							</span>
							<input type="hidden" class="entry_oldvalue" name="comp[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" value="', $entry['value'], '">
							<textarea name="entry[', $entry['key'], ']', isset($entry['subkey']) ? '[' . $entry['subkey'] . ']' : '', '" class="entry_textfield" cols="40" rows="', $entry['rows'] < 2 ? 2 : ($entry['rows'] > 25 ? 25 : $entry['rows']), '" style="width: 96%; margin-bottom: 2em;">', $entry['value'], '</textarea>
						</dd>';
			}

			echo '
					</dl>';

			if (!empty($context['can_add_lang_entry'][$group]))
			{
				echo '
				<span class="add_lang_entry_button" style="display: none;">
					<a class="button" href="javascript:void(0);" onclick="add_lang_entry(\'', $group, '\'); return false;">' . $txt['editnews_clickadd'] . '</a>
				</span>
				<script>
					entry_num = ', $entry_num, ';
				</script>';
			}

			echo '
				</fieldset>';
		}

		echo '
				<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled' : '', ' class="button">
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
	global $context, $txt, $scripturl;

	echo '
		<form id="admin_form_wrapper"action="', $scripturl, '?action=admin;area=languages;sa=add;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['add_language'], '
				</h3>
			</div>
			<div class="windowbg">
				<fieldset>
					<legend>', $txt['add_language_smf'], '</legend>
					<label class="smalltext">', $txt['add_language_smf_browse'], '</label>
					<input type="text" name="smf_add" size="40" value="', !empty($context['smf_search_term']) ? $context['smf_search_term'] : '', '">';

	// Do we have some errors? Too bad. Display a little error box.
	if (!empty($context['smf_error']))
		echo '
					<div>
						<br>
						<p class="errorbox">', $txt['add_language_error_' . $context['smf_error']], '</p>
					</div>';

	echo '
				</fieldset>
				', isBrowser('is_ie') ? '<input type="text" name="ie_fix" style="display: none;"> ' : '', '
				<input type="submit" name="smf_add_sub" value="', $txt['search'], '" class="button">
				<br>
			</div><!-- .windowbg -->';

	// Had some results?
	if (!empty($context['smf_languages']['rows']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['add_language_found_title'], '</div>
				<div class="information">', $txt['add_language_smf_found'], '
			</div>';

		template_show_list('smf_languages');
	}

	echo '
		</form>';
}

?>