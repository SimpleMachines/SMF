<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

// The main sub template - for theme administration.
function template_main()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=helpadmin;help=themes" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics_hd.png" class="icon" alt="', $txt['help'], '" /></a>
				', $txt['themeadmin_title'], '
			</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_explain'], '
		</div>
		<div id="admin_form_wrapper">
			<form action="', $scripturl, '?action=admin;area=theme;sa=admin" method="post" accept-charset="', $context['character_set'], '">
				<div class="cat_bar">
					<h3 class="catbg">',
						$txt['settings'], '
					</h3>
				</div>
				<div class="windowbg2">
					<div class="content">
						<dl class="settings">
							<dt>
								<label for="options-theme_allow"> ', $txt['theme_allow'], '</label>
							</dt>
							<dd>
								<input type="checkbox" name="options[theme_allow]" id="options-theme_allow" value="1"', !empty($modSettings['theme_allow']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="known_themes_list">', $txt['themeadmin_selectable'], '</label>:
							</dt>
							<dd>
								<div id="known_themes_list">';
	foreach ($context['themes'] as $theme)
		echo '
									<label for="options-known_themes_', $theme['id'], '"><input type="checkbox" name="options[known_themes][]" id="options-known_themes_', $theme['id'], '" value="', $theme['id'], '"', $theme['known'] ? ' checked="checked"' : '', ' class="input_check" /> ', $theme['name'], '</label><br />';

		echo '
								</div>
								<a href="javascript:void(0);" onclick="document.getElementById(\'known_themes_list\').style.display=\'block\'; document.getElementById(\'known_themes_link\').style.display = \'none\'; return false; " id="known_themes_link" style="display: none;">[ ', $txt['themeadmin_themelist_link'], ' ]</a>
								<script type="text/javascript"><!-- // --><![CDATA[
									document.getElementById("known_themes_list").style.display = "none";
									document.getElementById("known_themes_link").style.display = "";
								// ]]></script>
							</dd>
							<dt>
								<label for="theme_guests">', $txt['theme_guests'], ':</label>
							</dt>
							<dd>
								<select name="options[theme_guests]" id="theme_guests">';

	// Put an option for each theme in the select box.
	foreach ($context['themes'] as $theme)
		echo '
									<option value="', $theme['id'], '"', $modSettings['theme_guests'] == $theme['id'] ? ' selected="selected"' : '', '>', $theme['name'], '</option>';

	echo '
								</select>
								<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=-1;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
							</dd>
							<dt>
								<label for="theme_reset">', $txt['theme_reset'], '</label>:
							</dt>
							<dd>
								<select name="theme_reset" id="theme_reset">
									<option value="-1" selected="selected">', $txt['theme_nochange'], '</option>
									<option value="0">', $txt['theme_forum_default'], '</option>';

	// Same thing, this time for changing the theme of everyone.
	foreach ($context['themes'] as $theme)
		echo '
									<option value="', $theme['id'], '">', $theme['name'], '</option>';

	echo '
								</select>
								<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=0;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
							</dd>
						</dl>
						<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-tm_token_var'], '" value="', $context['admin-tm_token'], '" />
						<input type="hidden" value="0" name="options[theme_allow]" />
					</div>
				</div>
			</form>';

	// Link to simplemachines.org for latest themes and info!
	echo '
			<br />
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=latest_themes" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics_hd.png" class="icon" alt="', $txt['help'], '" /></a> ', $txt['theme_latest'], '
				</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<div id="themeLatest">
						', $txt['theme_latest_fetch'], '
					</div>
				</div>
			</div>
			<br />';

	// Warn them if theme creation isn't possible!
	if (!$context['can_create_new'])
		echo '
			<div class="errorbox">', $txt['theme_install_writable'], '</div>';

		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=theme_install" onclick="return reqOverlayDiv(this.href);" class="help" id="theme_install"><img src="', $settings['images_url'], '/helptopics_hd.png" class="icon" alt="', $txt['help'], '" /></a> ', $txt['theme_install'], '
				</h3>
			</div>
			<form action="', $scripturl, '?action=admin;area=theme;sa=install" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data" onsubmit="return confirm(\'', $txt['theme_install_new_confirm'], '\');">
				<div class="windowbg">
					<div class="content">
						<dl class="settings">';

	// Here's a little box for installing a new theme.
	// @todo Should the value="theme_gz" be there?!
	if ($context['can_create_new'])
		echo '
							<dt>
								<label for="theme_gz">', $txt['theme_install_file'], '</label>:
							</dt>
							<dd>
								<input type="file" name="theme_gz" id="theme_gz" value="theme_gz" size="40" onchange="this.form.copy.disabled = this.value != \'\'; this.form.theme_dir.disabled = this.value != \'\';" class="input_file" />
							</dd>';

	echo '
							<dt>
								<label for="theme_dir">', $txt['theme_install_dir'], '</label>:
							</dt>
							<dd>
								<input type="text" name="theme_dir" id="theme_dir" value="', $context['new_theme_dir'], '" size="40" style="width: 70%;" class="input_text" />
							</dd>';

	if ($context['can_create_new'])
		echo '
							<dt>
								<label for="copy">', $txt['theme_install_new'], ':</label>
							</dt>
							<dd>
								<input type="text" name="copy" id="copy" value="', $context['new_theme_name'], '" size="40" class="input_text" />
							</dd>';

	echo '
						</dl>
						<input type="submit" name="save" value="', $txt['theme_install_go'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-tm_token_var'], '" value="', $context['admin-tm_token'], '" />
					</div>
				</div>
			</form>
		</div>
	</div>

	<script type="text/javascript"><!-- // --><![CDATA[
		window.smfForum_scripturl = smf_scripturl;
		window.smfForum_sessionid = smf_session_id;
		window.smfForum_sessionvar = smf_session_var;
		window.smfThemes_writable = ', $context['can_create_new'] ? 'true' : 'false', ';
	// ]]></script>';

	if (empty($modSettings['disable_smf_js']))
		echo '
		<script type="text/javascript" src="', $scripturl, '?action=viewsmfile;filename=latest-themes.js"></script>';

	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var tempOldOnload;
			smfSetLatestThemes();
		// ]]></script>';
}

function template_list_themes()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_list_heading'], '</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_list_tip'], '
		</div>
		
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=theme;', $context['session_var'], '=', $context['session_id'], ';sa=list" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_settings'], '</h3>
			</div>
			<br />';

	// Show each theme.... with X for delete and a link to settings.
	foreach ($context['themes'] as $theme)
	{
		echo '
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="floatleft"><strong><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=list">', $theme['name'], '</a></strong>', !empty($theme['version']) ? ' <em>(' . $theme['version'] . ')</em>' : '', '</span>';

			// You *cannot* delete the default theme. It's important!
			if ($theme['id'] != 1)
				echo '
					<span class="floatright"><a href="', $scripturl, '?action=admin;area=theme;sa=remove;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';', $context['admin-tr_token_var'], '=', $context['admin-tr_token'], '" onclick="return confirm(\'', $txt['theme_remove_confirm'], '\');"><img src="', $settings['images_url'], '/icons/delete.png" alt="', $txt['theme_remove'], '" title="', $txt['theme_remove'], '" /></a></span>';

			echo '
				</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings themes_list">
						<dt>', $txt['themeadmin_list_theme_dir'], ':</dt>
						<dd', $theme['valid_path'] ? '' : ' class="error"', '>', $theme['theme_dir'], $theme['valid_path'] ? '' : ' ' . $txt['themeadmin_list_invalid'], '</dd>
						<dt>', $txt['themeadmin_list_theme_url'], ':</dt>
						<dd>', $theme['theme_url'], '</dd>
						<dt>', $txt['themeadmin_list_images_url'], ':</dt>
						<dd>', $theme['images_url'], '</dd>
					</dl>
				</div>
			</div>';
	}

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['themeadmin_list_reset'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="reset_dir">', $txt['themeadmin_list_reset_dir'], '</label>:
						</dt>
						<dd>
							<input type="text" name="reset_dir" id="reset_dir" value="', $context['reset_dir'], '" size="40" style="width: 80%;" class="input_text" />
						</dd>
						<dt>
							<label for="reset_url">', $txt['themeadmin_list_reset_url'], '</label>:
						</dt>
						<dd>
							<input type="text" name="reset_url" id="reset_url" value="', $context['reset_url'], '" size="40" style="width: 80%;" class="input_text" />
						</dd>
					</dl>
					<input type="submit" name="save" value="', $txt['themeadmin_list_reset_go'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-tl_token_var'], '" value="', $context['admin-tl_token'], '" />
				</div>
			</div>

		</form>
	</div>';
}

function template_reset_list()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_reset_title'], '</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_reset_tip'], '
		</div>
		<div id="admin_form_wrapper">';

	// Show each theme.... with X for delete and a link to settings.
	$alternate = false;

	foreach ($context['themes'] as $theme)
	{
		$alternate = !$alternate;

		echo '
			<div class="title_bar">
				<h3 class="titlebg">', $theme['name'], '</h3>
			</div>
			<div class="windowbg', $alternate ? '' : '2','">
				<div class="content">
					<ul class="reset">
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset">', $txt['themeadmin_reset_defaults'], '</a> <em class="smalltext">(', $theme['num_default_options'], ' ', $txt['themeadmin_reset_defaults_current'], ')</em>
						</li>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=1">', $txt['themeadmin_reset_members'], '</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=2;', $context['admin-stor_token_var'], '=', $context['admin-stor_token'], '" onclick="return confirm(\'', $txt['themeadmin_reset_remove_confirm'], '\');">', $txt['themeadmin_reset_remove'], '</a> <em class="smalltext">(', $theme['num_members'], ' ', $txt['themeadmin_reset_remove_current'], ')</em>
						</li>
					</ul>
				</div>
			</div>';
	}

	echo '
		</div>
	</div>';
}

// This template allows for the selection of different themes ;).
function template_pick()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="pick_theme">
		<form action="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">';

	// Just go through each theme and show its information - thumbnail, etc.
	foreach ($context['available_themes'] as $theme)
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], !empty($theme['variants']) ? ';vrt=' . $theme['selected_variant'] : '', '">', $theme['name'], '</a>
				</h3>
			</div>
			<div class="', $theme['selected'] ? 'windowbg' : 'windowbg2', '">
				<div class="flow_hidden content">
					<div class="floatright"><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_thumb_preview_', $theme['id'], '" title="', $txt['theme_preview'], '"><img src="', $theme['thumbnail_href'], '" id="theme_thumb_', $theme['id'], '" alt="" class="padding" /></a></div>
					<p>', $theme['description'], '</p>';

		if (!empty($theme['variants']))
		{
			echo '
					<label for="variant', $theme['id'], '"><strong>', $theme['pick_label'], '</strong></label>:
					<select id="variant', $theme['id'], '" name="vrt[', $theme['id'], ']" onchange="changeVariant(this.value, ', $theme['id'], ');">';

			foreach ($theme['variants'] as $key => $variant)
			{
				echo '
						<option value="', $key, '" ', $theme['selected_variant'] == $key ? 'selected="selected"' : '', '>', $variant['label'], '</option>';
			}
			echo '
					</select>
					<noscript>
						<input type="submit" name="save[', $theme['id'], ']" value="', $txt['save'], '" class="button_submit" />
					</noscript>';
		}

		echo '
					<br />
					<p>
						<em class="smalltext">', $theme['num_users'], ' ', ($theme['num_users'] == 1 ? $txt['theme_user'] : $txt['theme_users']), '</em>
					</p>
					<br />
					<ul class="reset">
						<li>
							<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], !empty($theme['variants']) ? ';vrt=' . $theme['selected_variant'] : '', '" id="theme_use_', $theme['id'], '">[', $txt['theme_set'], ']</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_preview_', $theme['id'], '">[', $txt['theme_preview'], ']</a>
						</li>
					</ul>
				</div>
			</div>';

		if (!empty($theme['variants']))
		{
			echo '
			<script type="text/javascript"><!-- // --><![CDATA[
			var sBaseUseUrl', $theme['id'], ' = smf_prepareScriptUrl(smf_scripturl) + \'action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '\';
			var sBasePreviewUrl', $theme['id'], ' = smf_prepareScriptUrl(smf_scripturl) + \'action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '\';
			var oThumbnails', $theme['id'], ' = {';

			// All the variant thumbnails.
			$count = 1;
			foreach ($theme['variants'] as $key => $variant)
			{
				echo '
				\'', $key, '\': \'', $variant['thumbnail'], '\'', (count($theme['variants']) == $count ? '' : ',');
				$count++;
			}

			echo '
			}
			// ]]></script>';
		}
	}

	echo '
		</form>
	</div>';
}

// Okay, that theme was installed successfully!
function template_installed()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Not much to show except a link back...
	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<p>
					<a href="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['installed_theme']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $context['installed_theme']['name'], '</a> ', $txt['theme_installed_message'], '
				</p>
				<p>
					<a href="', $scripturl, '?action=admin;area=theme;sa=admin;', $context['session_var'], '=', $context['session_id'], '">', $txt['back'], '</a>
				</p>
			</div>
		</div>
	</div>';
}

function template_edit_list()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_edit_title'], '</h3>
		</div>
		<br />';

	$alternate = false;

	foreach ($context['themes'] as $theme)
	{
		$alternate = !$alternate;

		echo '
		<div class="title_bar">
			<h3 class="titlebg">
				<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit">', $theme['name'], '</a>', !empty($theme['version']) ? '
				<em>(' . $theme['version'] . ')</em>' : '', '
			</h3>
		</div>
		<div class="windowbg', $alternate ? '' : '2','">
			<div class="content">
				<ul class="reset">
					<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit">', $txt['themeadmin_edit_browse'], '</a></li>', $theme['can_edit_style'] ? '
					<li><a href="' . $scripturl . '?action=admin;area=theme;th=' . $theme['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=css">' . $txt['themeadmin_edit_style'] . '</a></li>' : '', '
					<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy">', $txt['themeadmin_edit_copy_template'], '</a></li>
				</ul>
			</div>
		</div>';
	}

	echo '
	</div>';
}

function template_copy_template()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_edit_filename'], '</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_edit_copy_warning'], '
		</div>
		<div class="windowbg">
			<div class="content">
				<ul class="theme_options">';

	$alternate = false;
	foreach ($context['available_templates'] as $template)
	{
		$alternate = !$alternate;

		echo '
					<li class="reset flow_hidden windowbg', $alternate ? '2' : '', '">
						<span class="floatleft">', $template['filename'], $template['already_exists'] ? ' <span class="error">(' . $txt['themeadmin_edit_exists'] . ')</span>' : '', '</span>
						<span class="floatright">';

		if ($template['can_copy'])
			echo '<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy;template=', $template['value'], '" onclick="return confirm(\'', $template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm'], '\');">', $txt['themeadmin_edit_do_copy'], '</a>';
		else
			echo $txt['themeadmin_edit_no_copy'];

		echo '
						</span>
					</li>';
	}

	echo '
				</ul>
			</div>
		</div>
	</div>';
}

function template_edit_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<table width="100%" class="table_grid tborder">
		<thead>
			<tr class="catbg">
				<th class="lefttext first_th" scope="col" width="50%">', $txt['themeadmin_edit_filename'], '</th>
				<th scope="col" width="35%">', $txt['themeadmin_edit_modified'], '</th>
				<th class="last_th" scope="col" width="15%">', $txt['themeadmin_edit_size'], '</th>
			</tr>
		</thead>
		<tbody>';

	$alternate = false;

	foreach ($context['theme_files'] as $file)
	{
		$alternate = !$alternate;

		echo '
			<tr class="windowbg', $alternate ? '2' : '', '">
				<td>';

		if ($file['is_editable'])
			echo '<a href="', $file['href'], '"', $file['is_template'] ? ' style="font-weight: bold;"' : '', '>', $file['filename'], '</a>';

		elseif ($file['is_directory'])
			echo '<a href="', $file['href'], '" class="is_directory">', $file['filename'], '</a>';

		else
			echo $file['filename'];

		echo '
				</td>
				<td class="righttext">', !empty($file['last_modified']) ? $file['last_modified'] : '', '</td>
				<td class="righttext">', $file['size'], '</td>
			</tr>';
	}

	echo '
		</tbody>
		</table>
	</div>';
}

// Wanna edit the stylesheet?
function template_edit_style()
{
	global $context, $settings, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	// From now on no one can complain that editing css is difficult. If you disagree, go to www.w3schools.com.
	echo '
	<div id="admincenter">
		<script type="text/javascript"><!-- // --><![CDATA[
			var previewData = "";
			var previewTimeout;
			var editFilename = ', JavaScriptEscape($context['edit_filename']), ';

			// Load up a page, but apply our stylesheet.
			function navigatePreview(url)
			{
				var myDoc = new XMLHttpRequest();
				myDoc.onreadystatechange = function ()
				{
					if (myDoc.readyState != 4)
						return;

					if (myDoc.responseText != null && myDoc.status == 200)
					{
						previewData = myDoc.responseText;
						document.getElementById("css_preview_box").style.display = "";

						// Revert to the theme they actually use ;).
						var tempImage = new Image();
						tempImage.src = smf_prepareScriptUrl(smf_scripturl) + "action=admin;area=theme;sa=edit;theme=', $settings['theme_id'], ';preview;" + (new Date().getTime());

						refreshPreviewCache = null;
						refreshPreview(false);
					}
				};

				var anchor = "";
				if (url.indexOf("#") != -1)
				{
					anchor = url.substr(url.indexOf("#"));
					url = url.substr(0, url.indexOf("#"));
				}

				myDoc.open("GET", url + (url.indexOf("?") == -1 ? "?" : ";") + "theme=', $context['theme_id'], '" + anchor, true);
				myDoc.send(null);
			}
			navigatePreview(smf_scripturl);

			var refreshPreviewCache;
			function refreshPreview(check)
			{
				var identical = document.forms.stylesheetForm.entire_file.value == refreshPreviewCache;

				// Don\'t reflow the whole thing if nothing changed!!
				if (check && identical)
					return;
				refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;
				// Replace the paths for images.
				refreshPreviewCache = refreshPreviewCache.replace(/url\(\.\.\/images/gi, "url(" + smf_images_url);

				// Try to do it without a complete reparse.
				if (identical)
				{
					try
					{
					';
	if (isBrowser('is_ie'))
		echo '
						var sheets = frames["css_preview_box"].document.styleSheets;
						for (var j = 0; j < sheets.length; j++)
						{
							if (sheets[j].id == "css_preview_box")
								sheets[j].cssText = document.forms.stylesheetForm.entire_file.value;
						}';
	else
		echo '
						setInnerHTML(frames["css_preview_box"].document.getElementById("css_preview_sheet"), document.forms.stylesheetForm.entire_file.value);';
	echo '
					}
					catch (e)
					{
						identical = false;
					}
				}

				// This will work most of the time... could be done with an after-apply, maybe.
				if (!identical)
				{
					var data = previewData + "";
					var preview_sheet = document.forms.stylesheetForm.entire_file.value;
					var stylesheetMatch = new RegExp(\'<link rel="stylesheet"[^>]+href="[^"]+\' + editFilename + \'[^>]*>\');

					// Replace the paths for images.
					preview_sheet = preview_sheet.replace(/url\(\.\.\/images/gi, "url(" + smf_images_url);
					data = data.replace(stylesheetMatch, "<style type=\"text/css\" id=\"css_preview_sheet\">" + preview_sheet + "<" + "/style>");

					frames["css_preview_box"].document.open();
					frames["css_preview_box"].document.write(data);
					frames["css_preview_box"].document.close();

					// Next, fix all its links so we can handle them and reapply the new css!
					frames["css_preview_box"].onload = function ()
					{
						var fixLinks = frames["css_preview_box"].document.getElementsByTagName("a");
						for (var i = 0; i < fixLinks.length; i++)
						{
							if (fixLinks[i].onclick)
								continue;
							fixLinks[i].onclick = function ()
							{
								window.parent.navigatePreview(this.href);
								return false;
							};
						}
					};
				}
			}
		// ]]></script>
		<iframe id="css_preview_box" name="css_preview_box" src="about:blank" width="99%" height="300" frameborder="0" style="display: none; margin-bottom: 2ex; border: 1px solid black;"></iframe>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="', $context['character_set'], '" name="stylesheetForm" id="stylesheetForm">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">';

	if (!$context['allow_save'])
		echo '
					', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br />';

	echo '
					<textarea name="entire_file" cols="80" rows="20" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . '; font-family: monospace; margin-top: 1ex; white-space: pre;" onkeyup="setPreviewTimeout();" onchange="refreshPreview(true);">', $context['entire_file'], '</textarea><br />
					<div class="padding righttext">
						<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled="disabled"', ' style="margin-top: 1ex;" class="button_submit" />
						<input type="button" value="', $txt['themeadmin_edit_preview'], '" onclick="refreshPreview(false);" class="button_submit" />
					</div>
				</div>
			</div>
			<input type="hidden" name="filename" value="', $context['edit_filename'], '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>';
}

// This edits the template...
function template_edit_template()
{
	global $context, $settings, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	if (isset($context['parse_error']))
		echo '
	<div class="errorbox">
		', $txt['themeadmin_edit_error'], '
			<div><tt>', $context['parse_error'], '</tt></div>
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">';

	if (!$context['allow_save'])
		echo '
					', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br />';

	foreach ($context['file_parts'] as $part)
		echo '
					<label for="on_line', $part['line'], '">', $txt['themeadmin_edit_on_line'], ' ', $part['line'], '</label>:<br />
					<div class="centertext">
						<textarea id="on_line', $part['line'] ,'" name="entire_file[]" cols="80" rows="', $part['lines'] > 14 ? '14' : $part['lines'], '" class="edit_file">', $part['data'], '</textarea>
					</div>';

	echo '
					<div class="padding righttext">
						<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled="disabled"', ' class="button_submit" />
						<input type="hidden" name="filename" value="', $context['edit_filename'], '" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
				</div>
			</div>
		</form>
	</div>';
}

function template_edit_file()
{
	global $context, $settings, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	//Is this file writeable?
	if (!$context['allow_save'])
		echo '
	<div class="errorbox">
		', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<textarea name="entire_file" id="entire_file" cols="80" rows="20" class="edit_file">', $context['entire_file'], '</textarea><br />
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled="disabled"', ' class="button_submit" />
					<input type="hidden" name="filename" value="', $context['edit_filename'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	// Hopefully it exists.
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
					<input type="text" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '" />';

	echo '
				</div>
			</div>

		</form>
	</div>';
}

?>