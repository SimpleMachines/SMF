<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

/**
 * Download a new language file.
 */
function template_download_language()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

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
			<span class="topslice"><span></span></span>
			<div class="content">
				', $context['install_complete'], '
			</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>
	<br class="clear" />';
		return;
	}

	// An error?
	if (!empty($context['error_message']))
		echo '
	<div id="errorbox">
		<p>', $context['error_message'], '</p>
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
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>
						', $txt['languages_download_note'], '
					</p>
					<div class="smalltext">
						', $txt['languages_download_info'], '
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>';

	// Show the main files.
	template_show_list('lang_main_files_list');

	// Now, all the images and the likes, hidden via javascript 'cause there are so fecking many.
	echo '
			<br />
			<div class="title_bar">
				<h3 class="titlebg">
					', $txt['languages_download_theme_files'], '
				</h3>
			</div>
			<table class="table_grid" cellspacing="0" width="100%">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">
							', $txt['languages_download_filename'], '
						</th>
						<th scope="col" width="100">
							', $txt['languages_download_writable'], '
						</th>
						<th scope="col" width="100">
							', $txt['languages_download_exists'], '
						</th>
						<th class="last_th" scope="col" width="50">
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
						<img class="sort" src="', $settings['images_url'], '/sort_down.png" id="toggle_image_', $theme, '" alt="*" />&nbsp;', isset($context['theme_names'][$theme]) ? $context['theme_names'][$theme] : $theme, '
					</td>
				</tr>';

		$alternate = false;
		foreach ($group as $file)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="', $theme, '-', $count++, '">
					<td>
						<strong>', $file['name'], '</strong><br />
						<span class="smalltext">', $txt['languages_download_dest'], ': ', $file['destination'], '</span>
					</td>
					<td>
						<span style="color: ', ($file['writable'] ? 'green' : 'red'), ';">', ($file['writable'] ? $txt['yes'] : $txt['no']), '</span>
					</td>
					<td>
						', $file['exists'] ? ($file['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'], '
					</td>
					<td>
						<input type="checkbox" name="copy_file[]" value="', $file['generaldest'], '"', ($file['default_copy'] ? ' checked="checked"' : ''), ' class="input_check" />
					</td>
				</tr>';
			$alternate = !$alternate;
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
			<div id="errorbox">
				<tt>', $context['package_ftp']['error'], '</tt>
			</div>';

		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['package_ftp_necessary'], '
				</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>', $txt['package_ftp_why'],'</p>
					<dl class="settings">
						<dt
							<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
						</dt>
						<dd>
							<div class="floatright" style="margin-right: 1px;"><label for="ftp_port" style="padding-top: 2px; padding-right: 2ex;">', $txt['package_ftp_port'], ':&nbsp;</label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'), '" class="input_text" /></div>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'), '" style="width: 70%;" class="input_text" />
						</dd>

						<dt>
							<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''), '" style="width: 99%;" class="input_text" />
						</dd>

						<dt>
							<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_text" />
						</dd>

						<dt>
							<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%;" class="input_text" />
						</dd>
					</dl>
				</div>
				<span class="botslice"><span></span></span>
			</div>';
	}

	// Install?
	echo '
			<div class="righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-dlang_token_var'], '" value="', $context['admin-dlang_token'], '" />
				<input type="submit" name="do_install" value="', $txt['add_language_smf_install'], '" class="button_submit" />
			</div>
		</form>
	</div>
	<br class="clear" />';

	// The javascript for expand and collapse of sections.
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[';

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
						srcExpanded: smf_images_url + \'/sort_down.png\',
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
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['edit_languages'], '
				</h3>
			</div>';

	// Not writable?
	if ($context['lang_file_not_writable_message'])
	{
		// Oops, show an error for ya.
		echo '
			<div id="errorbox">
				<p class="alert">', $context['lang_file_not_writable_message'], '</p>
			</div>';
	}

	// Show the language entries
	echo '
			<div class="information">
				', $txt['edit_language_entries_primary'], '
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<fieldset>
						<legend>', $context['primary_settings']['name'], '</legend>
					<dl class="settings">
						<dt>
							<label for="character_set">', $txt['languages_character_set'], ':</label>
						</dt>
						<dd>
							<input type="text" name="character_set" id="character_set" size="20" value="', $context['primary_settings']['character_set'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
						</dd>
						<dt>
							<label for="locale>', $txt['languages_locale'], ':</label>
						</dt>
						<dd>
							<input type="text" name="locale" id="locale" size="20" value="', $context['primary_settings']['locale'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
						</dd>
						<dt>
							<label for="dictionary">', $txt['languages_dictionary'], ':</label>
						</dt>
						<dd>
							<input type="text" name="dictionary" id="dictionary" size="20" value="', $context['primary_settings']['dictionary'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
						</dd>
						<dt>
							<label for="spelling">', $txt['languages_spelling'], ':</label>
						</dt>
						<dd>
							<input type="text" name="spelling" id="spelling" size="20" value="', $context['primary_settings']['spelling'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
						</dd>
						<dt>
							<label for="rtl">', $txt['languages_rtl'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="rtl" id="rtl" ', $context['primary_settings']['rtl'] ? ' checked="checked"' : '', ' class="input_check"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' />
						</dd>
					</dl>
					</fieldset>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '" />
					<input type="submit" name="save_main" value="', $txt['save'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled="disabled"' : '', ' class="button_submit" />';

	// Allow deleting entries.
	if ($context['lang_id'] != 'english')
	{
		// English can't be deleted though.
		echo '
						<input type="submit" name="delete_main" value="', $txt['delete'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled="disabled"' : '', ' onclick="confirm(\'', $txt['languages_delete_confirm'], '\');" class="button_submit" />';
	}

	echo '
					<br class="clear_right" />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>

		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">
					', $txt['edit_language_entries'], '
				</h3>
			</div>
			<div id="taskpad" class="floatright">
				', $txt['edit_language_entries_file'], ':
					<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">';
	foreach ($context['possible_files'] as $id_theme => $theme)
	{
		echo '
						<option value="-1">', $theme['name'], '</option>';

		foreach ($theme['files'] as $file)
		{
			echo '
						<option value="', $id_theme, '+', $file['id'], '"', $file['selected'] ? ' selected="selected"' : '', '> =&gt; ', $file['name'], '</option>';
		}
	}

	echo '
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '" />
					<input type="submit" value="', $txt['go'], '" class="button_submit" />
			</div>';

	// Is it not writable?
	// Show an error.
	if (!empty($context['entries_not_writable_message']))
		echo '
			<div id="errorbox">
				<span class="alert">', $context['entries_not_writable_message'], '</span>
			</div>';

	// Already have some file entries?
	if (!empty($context['file_entries']))
	{
		echo '
			<div class="windowbg2">
				<span class="topslice"><span></span></span>
				<div class="content">
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
							<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '" />
							<textarea name="entry[', $cached['key'], ']" cols="40" rows="', $cached['rows'] < 2 ? 2 : $cached['rows'], '" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . ';">', $cached['value'], '</textarea>
						</dt>
						<dd>
							<input type="hidden" name="comp[', $entry['key'], ']" value="', $entry['value'], '" />
							<textarea name="entry[', $entry['key'], ']" cols="40" rows="', $entry['rows'] < 2 ? 2 : $entry['rows'], '" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . ';">', $entry['value'], '</textarea>
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
							<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '" />
							<textarea name="entry[', $cached['key'], ']" cols="40" rows="2" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . ';">', $cached['value'], '</textarea>
						</dt>
						<dd>
						</dd>';
		}

		echo '
					</dl>
					<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled="disabled"' : '', ' class="button_submit" />';

		echo '
				</div>
				<span class="botslice"><span></span></span>
			</div>';
	}
	echo '
		</form>
	</div>
	<br class="clear" />';
}

/**
 * Add a new language
 *
 */
function template_add_language()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=add;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['add_language'], '
				</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<fieldset>
						<legend>', $txt['add_language_smf'], '</legend>
						<label class="smalltext">', $txt['add_language_smf_browse'], '</label>
						<input type="text" name="smf_add" size="40" value="', !empty($context['smf_search_term']) ? $context['smf_search_term'] : '', '" class="input_text" />';

	// Do we have some errors? Too bad.
	if (!empty($context['smf_error']))
	{
		// Display a little error box.
		echo '
						<div><br /><p class="errorbox">', $txt['add_language_error_' . $context['smf_error']], '</p></div>';
	}

	echo '
					</fieldset>', isBrowser('is_ie') ? '<input type="text" name="ie_fix" style="display: none;" class="input_text" /> ' : '', '
					<input type="submit" name="smf_add_sub" value="', $txt['search'], '" class="button_submit" />
					<br />
				</div>
				<span class="botslice"><span></span></span>
			</div>
		';

	// Had some results?
	if (!empty($context['smf_languages']))
	{
		echo '
			<div class="information">', $txt['add_language_smf_found'], '</div>

				<table class="table_grid" cellspacing="0" width="100%">
					<thead>
						<tr class="catbg">
							<th class="first_th" scope="col">', $txt['name'], '</th>
							<th scope="col">', $txt['add_language_smf_desc'], '</th>
							<th scope="col">', $txt['add_language_smf_version'], '</th>
							<th scope="col">', $txt['add_language_smf_utf8'], '</th>
							<th class="last_th" scope="col">', $txt['add_language_smf_install'], '</th>
						</tr>
					</thead>
					<tbody>';

		foreach ($context['smf_languages'] as $language)
		{
			// Write each language information out.
			echo '
						<tr class="windowbg2">
							<td align="left">', $language['name'], '</td>
							<td align="left">', $language['description'], '</td>
							<td align="left">', $language['version'], '</td>
							<td align="center">', $language['utf8'] ? $txt['yes'] : $txt['no'], '</td>
							<td align="left"><a href="', $language['link'], '">', $txt['add_language_smf_install'], '</a></td>
						</tr>';
		}

		echo '
					</tbody>
					</table>';
	}

	echo '
		</form>
	</div>
	<br class="clear" />';
}

?>