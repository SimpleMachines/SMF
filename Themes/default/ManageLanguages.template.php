<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

/**
 * Download a new language file.
 */
function template_download_language()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// Actually finished?
	if (!empty($context['install_complete']))
	{
		echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['languages_download_complete'], '
			</h3>
		</div>
		<div class="windowbg">
			', $context['install_complete'], '
		</div>
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
	<div id="admincenter">
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

	// Now, all the images and the likes, hidden via javascript 'cause there are so fecking many.
	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['languages_download_theme_files'], '
				</h3>
			</div>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col">
							', $txt['languages_download_filename'], '
						</th>
						<th scope="col" style="width: 100px">
							', $txt['languages_download_writable'], '
						</th>
						<th scope="col" style="width: 100px">
							', $txt['languages_download_exists'], '
						</th>
						<th class="centercol" scope="col" style="width: 4%">
							', $txt['languages_download_copy'], '
						</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['files']['images'] as $theme => $group)
	{
		$count = 0;
		echo '
				<tr class="titlebg">
					<td colspan="4">
						<img class="sort" src="', $settings['images_url'], '/selected_open.png" id="toggle_image_', $theme, '" alt="*">&nbsp;', isset($context['theme_names'][$theme]) ? $context['theme_names'][$theme] : $theme, '
					</td>
				</tr>';

		foreach ($group as $file)
		{
			echo '
				<tr class="windowbg" id="', $theme, '-', $count++, '">
					<td>
						<strong>', $file['name'], '</strong><br>
						<span class="smalltext">', $txt['languages_download_dest'], ': ', $file['destination'], '</span>
					</td>
					<td>
						<span style="color: ', ($file['writable'] ? 'green' : 'red'), ';">', ($file['writable'] ? $txt['yes'] : $txt['no']), '</span>
					</td>
					<td>
						', $file['exists'] ? ($file['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'], '
					</td>
					<td class="centercol">
						<input type="checkbox" name="copy_file[]" value="', $file['generaldest'], '"', ($file['default_copy'] ? ' checked' : ''), ' class="input_check">
					</td>
				</tr>';
		}
	}

	echo '
			</tbody>
			</table>';

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
				<p>', $txt['package_ftp_why'],'</p>
				<dl class="settings">
					<dt
						<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright" style="margin-right: 1px;"><label for="ftp_port" style="padding-top: 2px; padding-right: 2ex;">', $txt['package_ftp_port'], ':&nbsp;</label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'), '" class="input_text"></div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'), '" style="width: 70%;" class="input_text">
					</dd>

					<dt>
						<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''), '" style="width: 99%;" class="input_text">
					</dd>

					<dt>
						<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_text">
					</dd>

					<dt>
						<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%;" class="input_text">
					</dd>
				</dl>
			</div>';
	}

	// Install?
	echo '
			<div class="righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-dlang_token_var'], '" value="', $context['admin-dlang_token'], '">
				<input type="submit" name="do_install" value="', $txt['add_language_smf_install'], '" class="button_submit">
			</div>
		</form>
	</div>';

	// The javascript for expand and collapse of sections.
	echo '
	<script><!-- // --><![CDATA[';

	// Each theme gets its own handler.
	foreach ($context['files']['images'] as $theme => $group)
	{
		$count = 0;
		echo '
			var oTogglePanel_', $theme, ' = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [';
		foreach ($group as $file)
			echo '
					', JavaScriptEscape($theme . '-' . $count++), ',';
		echo '
					null
				],
				aSwapImages: [
					{
						sId: \'toggle_image_', $theme, '\',
						srcExpanded: smf_images_url + \'/selected_open.png\',
						altExpanded: \'*\',
						srcCollapsed: smf_images_url + \'/selected.png\',
						altCollapsed: \'*\'
					}
				]
			});';
	}

	echo '
	// ]]></script>';
}

/**
 * Edit language entries.
 */
function template_modify_language_entries()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_languages'], '
				</h3>
			</div>
			<div id="editlang_desc" class="information">
				', $txt['edit_language_entries_primary'], '
			</div>';

	// Not writable?
	if (!empty($context['lang_file_not_writable_message']))
	{
		// Oops, show an error for ya.
		echo '
			<div class="errorbox">
				', $context['lang_file_not_writable_message'], '
			</div>';
	}

	// Show the language entries
	echo '
			<div class="windowbg">
				<fieldset>
					<legend>', $context['primary_settings']['name'], '</legend>
					<dl class="settings">
						<dt>
							<label for="character_set">', $txt['languages_character_set'], ':</label>
						</dt>
						<dd>
							<input type="text" name="character_set" id="character_set" size="20" value="', $context['primary_settings']['character_set'], '"', (empty($context['file_entries']) ? '' : ' disabled'), ' class="input_text">
						</dd>
						<dt>
							<label for="locale">', $txt['languages_locale'], ':</label>
						</dt>
						<dd>
							<input type="text" name="locale" id="locale" size="20" value="', $context['primary_settings']['locale'], '"', (empty($context['file_entries']) ? '' : ' disabled'), ' class="input_text">
						</dd>
						<dt>
							<label for="dictionary">', $txt['languages_dictionary'], ':</label>
						</dt>
						<dd>
							<input type="text" name="dictionary" id="dictionary" size="20" value="', $context['primary_settings']['dictionary'], '"', (empty($context['file_entries']) ? '' : ' disabled'), ' class="input_text">
						</dd>
						<dt>
							<label for="spelling">', $txt['languages_spelling'], ':</label>
						</dt>
						<dd>
							<input type="text" name="spelling" id="spelling" size="20" value="', $context['primary_settings']['spelling'], '"', (empty($context['file_entries']) ? '' : ' disabled'), ' class="input_text">
						</dd>
						<dt>
							<label for="rtl">', $txt['languages_rtl'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="rtl" id="rtl"', $context['primary_settings']['rtl'] ? ' checked' : '', ' class="input_check"', (empty($context['file_entries']) ? '' : ' disabled'), '>
						</dd>
					</dl>
				</fieldset>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '">
				<input type="submit" name="save_main" value="', $txt['save'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled' : '', ' class="button_submit">';

	// Allow deleting entries.
	if ($context['lang_id'] != 'english')
	{
		// English can't be deleted though.
		echo '
					<input type="submit" name="delete_main" value="', $txt['delete'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled' : '', ' onclick="confirm(\'', $txt['languages_delete_confirm'], '\');" class="button_submit">';
	}

	echo '
			</div>
		</form>

		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_language_entries'], '
				</h3>
			</div>
			<div id="taskpad" class="floatright">
				', $txt['edit_language_entries_file'], ':
					<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">
						<option value="-1">&nbsp;</option>';
	foreach ($context['possible_files'] as $id_theme => $theme)
	{
		echo '
						<optgroup label="', $theme['name'], '">';

		foreach ($theme['files'] as $file)
		{
			echo '
							<option value="', $id_theme, '+', $file['id'], '"', $file['selected'] ? ' selected' : '', '> =&gt; ', $file['name'], '</option>';
		}

		echo '
						</optgroup>';
	}

	echo '
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '">
					<input type="submit" value="', $txt['go'], '" class="button_submit" style="float: none"/>
			</div>
			<br class="clear">';

	// Is it not writable?
	// Show an error.
	if (!empty($context['entries_not_writable_message']))
		echo '
			<div class="errorbox">
				', $context['entries_not_writable_message'], '
			</div>';

	// Already have some file entries?
	if (!empty($context['file_entries']))
	{
		echo '
			<div class="windowbg2">
				<dl class="settings">';

		$cached = array();
		foreach ($context['file_entries'] as $entry)
		{
			// Do it in two's!
			if (empty($cached))
			{
				$cached = $entry;
				continue;
			}

			echo '
					<dt>
						<span class="smalltext">', $cached['key'], '</span>
					</dt>
					<dd>
						<span class="smalltext">', $entry['key'], '</span>
					</dd>
					<dt>
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '">
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="', $cached['rows'] < 2 ? 2 : $cached['rows'], '" style="width: 96%;">', $cached['value'], '</textarea>
					</dt>
					<dd>
						<input type="hidden" name="comp[', $entry['key'], ']" value="', $entry['value'], '">
						<textarea name="entry[', $entry['key'], ']" cols="40" rows="', $entry['rows'] < 2 ? 2 : $entry['rows'], '" style="width: 96%;">', $entry['value'], '</textarea>
					</dd>';
			$cached = array();
		}

		// Odd number?
		if (!empty($cached))
		{
			// Alternative time
			echo '

					<dt>
						<span class="smalltext">', $cached['key'], '</span>
					</dt>
					<dd>
					</dd>
					<dt>
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '">
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="2" style="width: 96%;">', $cached['value'], '</textarea>
					</dt>
					<dd>
					</dd>';
		}

		echo '
				</dl>
				<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled' : '', ' class="button_submit">';

		echo '
			</div>';
	}
	echo '
		</form>
	</div>';
}

/**
 * Add a new language
 *
 */
function template_add_language()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper"action="', $scripturl, '?action=admin;area=languages;sa=add;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['add_language'], '
				</h3>
			</div>
			<div class="windowbg2">
				<fieldset>
					<legend>', $txt['add_language_smf'], '</legend>
					<label class="smalltext">', $txt['add_language_smf_browse'], '</label>
					<input type="text" name="smf_add" size="40" value="', !empty($context['smf_search_term']) ? $context['smf_search_term'] : '', '" class="input_text">';

	// Do we have some errors? Too bad.
	if (!empty($context['smf_error']))
	{
		// Display a little error box.
		echo '
					<div><br><p class="errorbox">', $txt['add_language_error_' . $context['smf_error']], '</p></div>';
	}

	echo '
				</fieldset>', isBrowser('is_ie') ? '<input type="text" name="ie_fix" style="display: none;" class="input_text"> ' : '', '
				<input type="submit" name="smf_add_sub" value="', $txt['search'], '" class="button_submit">
				<br>
			</div>';

	// Had some results?
	if (!empty($context['smf_languages']['rows']))
	{
		echo '
			<div class="cat_bar"><h3 class="catbg">', $txt['add_language_found_title'], '</div><div class="information">', $txt['add_language_smf_found'], '</div>';

		template_show_list('smf_languages');
	}

	echo '
		</form>
	</div>';
}

?>