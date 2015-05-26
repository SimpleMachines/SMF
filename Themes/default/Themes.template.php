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

// The main sub template - for theme administration.
function template_main()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	// Theme install info.
	echo '
		<div class="cat_bar">
		<h3 class="catbg">
			<a href="', $scripturl, '?action=helpadmin;help=themes" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a>
			', $txt['themeadmin_title'], '
		</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_explain'], '
		</div>';

	echo '<br>
		<form action="', $scripturl, '?action=admin;area=theme;sa=admin" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">',
					$txt['settings'], '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<label for="options-theme_allow"> ', $txt['theme_allow'], '</label>
					</dt>
					<dd>
						<input type="hidden" value="0" name="options[theme_allow]">
						<input type="checkbox" name="options[theme_allow]" id="options-theme_allow" value="1"', !empty($modSettings['theme_allow']) ? ' checked' : '', ' class="input_check">
					</dd>
					<dt>
						<label for="known_themes_list">', $txt['themeadmin_selectable'], '</label>:
					</dt>
					<dd>
						<div id="known_themes_list">';

	foreach ($context['themes'] as $theme)
		echo '
							<label for="options-known_themes_', $theme['id'], '"><input type="checkbox" name="options[known_themes][]" id="options-known_themes_', $theme['id'], '" value="', $theme['id'], '"', $theme['known'] ? ' checked' : '', ' class="input_check"> ', $theme['name'], '</label><br>';

		echo '
						</div>
						<a href="javascript:void(0);" onclick="document.getElementById(\'known_themes_list\').style.display=\'block\'; document.getElementById(\'known_themes_link\').style.display = \'none\'; return false; " id="known_themes_link" style="display: none;">[ ', $txt['themeadmin_themelist_link'], ' ]</a>
						<script><!-- // --><![CDATA[
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
							<option value="', $theme['id'], '"', $modSettings['theme_guests'] == $theme['id'] ? ' selected' : '', '>', $theme['name'], '</option>';

	echo '
						</select>
						<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=-1;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
					</dd>
					<dt>
						<label for="theme_reset">', $txt['theme_reset'], '</label>:
					</dt>
					<dd>
						<select name="theme_reset" id="theme_reset">
							<option value="-1" selected>', $txt['theme_nochange'], '</option>
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
				<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-tm_token_var'], '" value="', $context['admin-tm_token'], '">
			</div>
		</form>';

	// Link to simplemachines.org for latest themes and info!
	echo '
		<br>
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['theme_adding_title'], '
			</h3>
		</div>
		<div class="windowbg2">
			', $txt['theme_adding'], '
		</div>';

	// All the install options.
	echo '
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['theme_install'], '
				</h3>
			</div>';

	if ($context['can_create_new'])
	{
		// From a file.
		echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['theme_install_file'], '
				</h3>
			</div>
			<form action="', $scripturl, '?action=admin;area=theme;sa=install;do=file" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data" class="windowbg2">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-t-file_token_var'], '" value="', $context['admin-t-file_token'], '">
				<input type="file" name="theme_gz" id="theme_gz" value="theme_gz" size="40" onchange="this.form.copy.disabled = this.value != \'\'; this.form.theme_dir.disabled = this.value != \'\';" class="input_file">
				<input type="submit" name="save_file" value="' . $txt['upload'] . '" class="button_submit">
			</form>';

		// Copied from the default.
		echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['theme_install_new'], '
				</h3>
			</div>
			<form action="', $scripturl, '?action=admin;area=theme;sa=install;do=copy" method="post" accept-charset="', $context['character_set'], '" class="windowbg2">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-t-copy_token_var'], '" value="', $context['admin-t-copy_token'], '">
				<input type="text" name="copy" id="copy" value="', $context['new_theme_name'], '" size="40" class="input_text">
				<input type="submit" name="save_copy" value="' . $txt['save'] . '" class="button_submit">
			</form>';
	}

	// From a dir.
	echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['theme_install_dir'], '
				</h3>
			</div>
			<form action="', $scripturl, '?action=admin;area=theme;sa=install;do=dir" method="post" accept-charset="', $context['character_set'], '" class="windowbg2">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-t-dir_token_var'], '" value="', $context['admin-t-dir_token'], '">
				<input type="text" name="theme_dir" id="theme_dir" value="', $context['new_theme_dir'], '" size="40" style="width: 40%;" class="input_text">
				<input type="submit" name="save_dir" value="' . $txt['save'] . '" class="button_submit">
			</form>';

	echo '
		</div>';

	echo '
	</div>';

	echo '
	<script><!-- // --><![CDATA[
		window.smfForum_scripturl = smf_scripturl;
		window.smfForum_sessionid = smf_session_id;
		window.smfForum_sessionvar = smf_session_var;
		window.smfThemes_writable = ', $context['can_create_new'] ? 'true' : 'false', ';
	// ]]></script>';
}

function template_list_themes()
{
	global $context, $scripturl, $txt;

	// Show a nice confirmation message.
	if (isset($_GET['done']))
		echo '
	<div class="infobox">
		', $txt['theme_confirmed_'. $_GET['done']] ,'
	</div>';

	echo '
	<div id="admincenter">';

	echo '
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
			<br>';

	// Show each theme.... with X for delete, an enable/disable link and a link to their own settings page.
	foreach ($context['themes'] as $theme)
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="floatleft">
						', (!empty($theme['enable']) || $theme['id'] == 1 ? '<a href="'. $scripturl .'?action=admin;area=theme;th='. $theme['id'] .';'. $context['session_var'] .'='. $context['session_id'] .';sa=list">'. $theme['name'] .'</a>' : $theme['name'] ),'', (!empty($theme['version']) ? ' <em>(' . $theme['version'] . ')</em>' : ''), '
					</span>';

			// You *cannot* disable/enable/delete the default theme. It's important!
			if ($theme['id'] != 1)
			{
				echo '
					<span class="floatright">';

				// Enable/Disable.
				echo '
					<a href="', $scripturl, '?action=admin;area=theme;sa=enable;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';', $context['admin-tre_token_var'], '=', $context['admin-tre_token'], '', (!empty($theme['enable']) ? ';disabled' : '') ,'" data-confirm="', $txt['theme_'. (!empty($theme['enable']) ? 'disable' : 'enable') .'_confirm'], '" class="you_sure"><span class="generic_icons ',!empty($theme['enable']) ? 'disable' : 'enable','" title="', $txt['theme_'. (!empty($theme['enable']) ? 'disable' : 'enable')] ,'"></span></a>';

				// Deleting.
				echo '
						<a href="', $scripturl, '?action=admin;area=theme;sa=remove;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';', $context['admin-tr_token_var'], '=', $context['admin-tr_token'], '" data-confirm="', $txt['theme_remove_confirm'], '" class="you_sure"><span class="generic_icons delete" title="', $txt['theme_remove'], '"></span></a>';

				echo '
					</span>';
			}

			echo '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings themes_list">
					<dt>', $txt['themeadmin_list_theme_dir'], ':</dt>
					<dd', $theme['valid_path'] ? '' : ' class="error"', '>', $theme['theme_dir'], $theme['valid_path'] ? '' : ' ' . $txt['themeadmin_list_invalid'], '</dd>
					<dt>', $txt['themeadmin_list_theme_url'], ':</dt>
					<dd>', $theme['theme_url'], '</dd>
					<dt>', $txt['themeadmin_list_images_url'], ':</dt>
					<dd>', $theme['images_url'], '</dd>
				</dl>
			</div>';
	}

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['themeadmin_list_reset'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<label for="reset_dir">', $txt['themeadmin_list_reset_dir'], '</label>:
					</dt>
					<dd>
						<input type="text" name="reset_dir" id="reset_dir" value="', $context['reset_dir'], '" size="40" style="width: 80%;" class="input_text">
					</dd>
					<dt>
						<label for="reset_url">', $txt['themeadmin_list_reset_url'], '</label>:
					</dt>
					<dd>
						<input type="text" name="reset_url" id="reset_url" value="', $context['reset_url'], '" size="40" style="width: 80%;" class="input_text">
					</dd>
				</dl>
				<input type="submit" name="save" value="', $txt['themeadmin_list_reset_go'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-tl_token_var'], '" value="', $context['admin-tl_token'], '">
			</div>

		</form>
	</div>';
}

function template_reset_list()
{
	global $context, $scripturl, $txt;

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
	foreach ($context['themes'] as $theme)
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $theme['name'], '</h3>
			</div>
			<div class="windowbg">
				<ul class="reset">
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset">', $txt['themeadmin_reset_defaults'], '</a> <em class="smalltext">(', $theme['num_default_options'], ' ', $txt['themeadmin_reset_defaults_current'], ')</em>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=1">', $txt['themeadmin_reset_members'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=2;', $context['admin-stor_token_var'], '=', $context['admin-stor_token'], '" data-confirm="', $txt['themeadmin_reset_remove_confirm'], '" class="you_sure">', $txt['themeadmin_reset_remove'], '</a> <em class="smalltext">(', $theme['num_members'], ' ', $txt['themeadmin_reset_remove_current'], ')</em>
					</li>
				</ul>
			</div>';
	}

	echo '
		</div>
	</div>';
}

function template_set_options()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';sa=reset" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="who" value="', $context['theme_options_reset'] ? 1 : 0, '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_options_title'], ' - ', $context['theme_settings']['name'], '</h3>
			</div>
			<div class="information">
				', $context['theme_options_reset'] ? $txt['themeadmin_reset_options_info'] : $txt['theme_options_defaults'], '
			</div>
			<div class="windowbg2">';
	echo '
				<dl class="settings">';

	foreach ($context['options'] as $setting)
	{
		echo '
					<dt ', $context['theme_options_reset'] ? 'style="width:50%"' : '', '>';

		// Show the change option box ?
		if ($context['theme_options_reset'])
			echo '
						<span class="floatleft"><select name="', !empty($setting['default']) ? 'default_' : '', 'options_master[', $setting['id'], ']" onchange="this.form.options_', $setting['id'], '.disabled = this.selectedIndex != 1;">
							<option value="0" selected>', $txt['themeadmin_reset_options_none'], '</option>
							<option value="1">', $txt['themeadmin_reset_options_change'], '</option>
							<option value="2">', $txt['themeadmin_reset_options_default'], '</option>
						</select>&nbsp;</span>';

		// display checkbox options
		if ($setting['type'] == 'checkbox')
		{
			echo '
						<label for="options_', $setting['id'], '">', $setting['label'], '</label>';
			if (isset($setting['description']))
				echo '
						<br><span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>
					<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
						<input type="hidden" name="' . (!empty($setting['default']) ? 'default_' : '') . 'options[' . $setting['id'] . ']" value="0">
						<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', !empty($setting['value']) ? ' checked' : '', $context['theme_options_reset'] ? ' disabled' : '', ' value="1" class="input_check floatleft">';
		}
		// how about selection lists, we all love them
		elseif ($setting['type'] == 'list')
		{
			echo '
						<label for="options_', $setting['id'], '">', $setting['label'], '</label>';
			if (isset($setting['description']))
				echo '
						<br><span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>
					<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
						&nbsp;<select class="floatleft" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', $context['theme_options_reset'] ? ' disabled' : '', '>';

			foreach ($setting['options'] as $value => $label)
			{
				echo '
							<option value="', $value, '"', $value == $setting['value'] ? ' selected' : '', '>', $label, '</option>';
			}

			echo '
						</select>';
		}
		// a textbox it is then
		else
		{
			echo '
						<label for="options_', $setting['id'], '">', $setting['label'], '</label>';
			if (isset($setting['description']))
				echo '
						<br><span class="smalltext">', $setting['description'], '</span>';
		echo '
					</dt>
					<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>';

			if (isset($setting['type']) && $setting['type'] == 'number')
			{
				$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
				$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
				$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';

				echo '
						<input type="number"', $min . $max . $step;
			}
			else
			{
				echo '
						<input type="text"';
			}


			echo ' name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', $context['theme_options_reset'] ? ' disabled' : '', ' class="input_text">';
		}

		// end of this defintion
		echo '
					</dd>';
	}

	// close the option page up
	echo '
				</dl>
				<input type="submit" name="submit" value="', $txt['save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-sto_token_var'], '" value="', $context['admin-sto_token'], '">
			</div>
		</form>
	</div>';
}

function template_set_settings()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['theme_settings']['theme_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=theme_settings" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a> ', $txt['theme_settings'], ' - ', $context['theme_settings']['name'], '
				</h3>
			</div>
			<br>';

	// @todo Why can't I edit the default theme popup.
	if ($context['theme_settings']['theme_id'] != 1)
		echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_edit'], '
				</h3>
			</div>
			<div class="windowbg2">
				<ul class="reset">
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;filename=index.template.php">', $txt['theme_edit_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;directory=css">', $txt['theme_edit_style'], '</a>
					</li>
				</ul>
			</div>';

	echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_url_config'], '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<label for="theme_name">', $txt['actual_theme_name'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_name" name="options[name]" value="', $context['theme_settings']['name'], '" size="32" class="input_text">
					</dd>
					<dt>
						<label for="theme_url">', $txt['actual_theme_url'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_url" name="options[theme_url]" value="', $context['theme_settings']['actual_theme_url'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text">
					</dd>
					<dt>
						<label for="images_url">', $txt['actual_images_url'], '</label>
					</dt>
					<dd>
						<input type="text" id="images_url" name="options[images_url]" value="', $context['theme_settings']['actual_images_url'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text">
					</dd>
					<dt>
						<label for="theme_dir">', $txt['actual_theme_dir'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_dir" name="options[theme_dir]" value="', $context['theme_settings']['actual_theme_dir'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text">
					</dd>
				</dl>
			</div>';

	// Do we allow theme variants?
	if (!empty($context['theme_variants']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_variants'], '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<label for="variant">', $txt['theme_variants_default'], '</label>:
					</dt>
					<dd>
						<select id="variant" name="options[default_variant]" onchange="changeVariant(this.value)">';

		foreach ($context['theme_variants'] as $key => $variant)
			echo '
							<option value="', $key, '"', $context['default_variant'] == $key ? ' selected' : '', '>', $variant['label'], '</option>';

		echo '
						</select>
					</dd>
					<dt>
						<label for="disable_user_variant">', $txt['theme_variants_user_disable'], '</label>:
					</dt>
					<dd>
						<input type="hidden" name="options[disable_user_variant]" value="0">
						<input type="checkbox" name="options[disable_user_variant]" id="disable_user_variant"', !empty($context['theme_settings']['disable_user_variant']) ? ' checked' : '', ' value="1" class="input_check">
					</dd>
				</dl>
				<img src="', $context['theme_variants'][$context['default_variant']]['thumbnail'], '" id="variant_preview" alt="">
			</div>';
	}

	echo '
			<div class="cat_bar">
				<h3 class="catbg config_hd">
					', $txt['theme_options'], '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings flow_auto">';

	foreach ($context['settings'] as $setting)
	{
		// Is this a separator?
		if (empty($setting))
		{
			echo '
				</dl>
				<hr class="hrcolor">
				<dl class="settings flow_auto">';
		}
		// A checkbox?
		elseif ($setting['type'] == 'checkbox')
		{
			echo '
					<dt>
						<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br>
						<span class="smalltext">', $setting['description'], '</span>';

			echo '
					</dt>
					<dd>
						<input type="hidden" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" value="0">
						<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '"', !empty($setting['value']) ? ' checked' : '', ' value="1" class="input_check">
					</dd>';
		}
		// A list with options?
		elseif ($setting['type'] == 'list')
		{
			echo '
					<dt>
						<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br>
						<span class="smalltext">', $setting['description'], '</span>';

			echo '
					</dt>
					<dd>
						<select name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '">';

			foreach ($setting['options'] as $value => $label)
				echo '
							<option value="', $value, '"', $value == $setting['value'] ? ' selected' : '', '>', $label, '</option>';

			echo '
						</select>
					</dd>';
		}
		// A regular input box, then?
		else
		{
			echo '
					<dt>
						<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br>
						<span class="smalltext">', $setting['description'], '</span>';

			echo '
					</dt>
					<dd>';

			if (isset($setting['type']) && $setting['type'] == 'number')
			{
				$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
				$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
				$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';

				echo '
						<input type="number"', $min . $max . $step;
			}
			else
			{
				echo '
						<input type="text"';
			}

			echo ' name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : (empty($settings['size']) ? ' size="40"' : ' size="' . $setting['size'] . '"'), ' class="input_text">
					</dd>';
		}
	}

	echo '
				</dl>
				<input type="submit" name="save" value="', $txt['save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['admin-sts_token_var'], '" value="', $context['admin-sts_token'], '">
			</div>
		</form>
	</div>';

	if (!empty($context['theme_variants']))
	{
		echo '
		<script><!-- // --><![CDATA[
		var oThumbnails = {';

		// All the variant thumbnails.
		$count = 1;
		foreach ($context['theme_variants'] as $key => $variant)
		{
			echo '
			\'', $key, '\': \'', $variant['thumbnail'], '\'', (count($context['theme_variants']) == $count ? '' : ',');
			$count++;
		}

		echo '
		}
		// ]]></script>';
	}
}

// This template allows for the selection of different themes ;).
function template_pick()
{
	global $context, $scripturl, $txt;

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
				<div class="flow_hidden">
					<div class="floatright"><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_thumb_preview_', $theme['id'], '" title="', $txt['theme_preview'], '"><img src="', $theme['thumbnail_href'], '" id="theme_thumb_', $theme['id'], '" alt="" class="padding"></a></div>
					<p>', $theme['description'], '</p>';

		if (!empty($theme['variants']))
		{
			echo '
					<label for="variant', $theme['id'], '"><strong>', $theme['pick_label'], '</strong></label>:
					<select id="variant', $theme['id'], '" name="vrt[', $theme['id'], ']" onchange="changeVariant', $theme['id'], '(this.value);">';

			foreach ($theme['variants'] as $key => $variant)
			{
				echo '
						<option value="', $key, '"', $theme['selected_variant'] == $key ? ' selected' : '', '>', $variant['label'], '</option>';
			}
			echo '
					</select>
					<noscript>
						<input type="submit" name="save[', $theme['id'], ']" value="', $txt['save'], '" class="button_submit">
					</noscript>';
		}

		echo '
					<br>
					<p>
						<em class="smalltext">', $theme['num_users'], ' ', ($theme['num_users'] == 1 ? $txt['theme_user'] : $txt['theme_users']), '</em>
					</p>
					<br>
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
			<script><!-- // --><![CDATA[
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

			function changeVariant', $theme['id'], '(sVariant)
			{
				document.getElementById(\'theme_thumb_', $theme['id'], '\').src = oThumbnails', $theme['id'], '[sVariant];
				document.getElementById(\'theme_use_', $theme['id'], '\').href = sBaseUseUrl', $theme['id'] == 0 ? $context['default_theme_id'] : $theme['id'], ' + \';vrt=\' + sVariant;
				document.getElementById(\'theme_thumb_preview_', $theme['id'], '\').href = sBasePreviewUrl', $theme['id'], ' + \';vrt=\' + sVariant + \';variant=\' + sVariant;
				document.getElementById(\'theme_preview_', $theme['id'], '\').href = sBasePreviewUrl', $theme['id'], ' + \';vrt=\' + sVariant + \';variant=\' + sVariant;
			}
			// ]]></script>';
		}
	}

	echo '
		</form>
	</div>';
}

// Okay, that theme was installed/updated successfully!
function template_installed()
{
	global $context, $scripturl, $txt;

	// The aftermath.
	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">';

	// Oops! there was an error :(
	if (!empty($context['error_message']))
		echo '
			<p>
				', $context['error_message'] ,'
			</p>';

	// Not much to show except a link back...
	else
		echo '
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['installed_theme']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $context['installed_theme']['name'], '</a> ', $txt['theme_'. (isset($context['installed_theme']['updated']) ? 'updated' : 'installed') .'_message'], '
			</p>
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=admin;', $context['session_var'], '=', $context['session_id'], '">', $txt['back'], '</a>
			</p>';

	echo '
		</div>
	</div>';
}

function template_edit_list()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_edit_title'], '</h3>
		</div>
		<br>';

	foreach ($context['themes'] as $theme)
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit">', $theme['name'], '</a>', !empty($theme['version']) ? '
				<em>(' . $theme['version'] . ')</em>' : '', '
			</h3>
		</div>
		<div class="windowbg">
			<ul class="reset">
				<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit">', $txt['themeadmin_edit_browse'], '</a></li>', $theme['can_edit_style'] ? '
				<li><a href="' . $scripturl . '?action=admin;area=theme;th=' . $theme['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=css">' . $txt['themeadmin_edit_style'] . '</a></li>' : '', '
				<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy">', $txt['themeadmin_edit_copy_template'], '</a></li>
			</ul>
		</div>';
	}

	echo '
	</div>';
}

function template_copy_template()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['themeadmin_edit_filename'], '</h3>
		</div>
		<div class="information">
			', $txt['themeadmin_edit_copy_warning'], '
		</div>
		<div class="windowbg">
			<ul class="theme_options">';

	foreach ($context['available_templates'] as $template)
	{
		echo '
				<li class="reset flow_hidden windowbg">
					<span class="floatleft">', $template['filename'], $template['already_exists'] ? ' <span class="error">(' . $txt['themeadmin_edit_exists'] . ')</span>' : '', '</span>
					<span class="floatright">';

		if ($template['can_copy'])
			echo '<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy;template=', $template['value'], '" data-confirm="', $template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm'], '" class="you_sure">', $txt['themeadmin_edit_do_copy'], '</a>';
		else
			echo $txt['themeadmin_edit_no_copy'];

		echo '
					</span>
				</li>';
	}

	echo '
			</ul>
		</div>
	</div>';
}

function template_edit_browse()
{
	global $context, $txt;

	echo '
	<div id="admincenter">';

	if (!empty($context['browse_title']))
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['browse_title'], '</h3>
		</div>';

	echo '
		<table class="table_grid tborder">
		<thead>
			<tr class="title_bar">
				<th class="lefttext" scope="col" width="50%">', $txt['themeadmin_edit_filename'], '</th>
				<th scope="col" width="35%">', $txt['themeadmin_edit_modified'], '</th>
				<th scope="col" width="15%">', $txt['themeadmin_edit_size'], '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($context['theme_files'] as $file)
	{

		echo '
			<tr class="windowbg">
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
	global $context, $settings, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	// From now on no one can complain that editing css is difficult. If you disagree, go to www.w3schools.com.
	echo '
	<div id="admincenter">
		<script><!-- // --><![CDATA[
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
			<div class="windowbg">';

	if (!$context['allow_save'])
		echo '
				', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br>';

	echo '
				<textarea name="entire_file" cols="80" rows="20" style="width: 96%; font-family: monospace; margin-top: 1ex; white-space: pre;" onkeyup="setPreviewTimeout();" onchange="refreshPreview(true);">', $context['entire_file'], '</textarea><br>
				<div class="padding righttext">
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' style="margin-top: 1ex;" class="button_submit">
					<input type="button" value="', $txt['themeadmin_edit_preview'], '" onclick="refreshPreview(false);" class="button_submit">
				</div>
			</div>
			<input type="hidden" name="filename" value="', $context['edit_filename'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	// Hopefully it exists.
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
			<input type="hidden" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '">';

	echo '
		</form>
	</div>';
}

// This edits the template...
function template_edit_template()
{
	global $context, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	if (isset($context['parse_error']))
		echo '
	<div class="errorbox">
		', $txt['themeadmin_edit_error'], '
			<div><pre>', $context['parse_error'], '</pre></div>
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h3>
			</div>
			<div class="windowbg">';

	if (!$context['allow_save'])
		echo '
				', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br>';

	foreach ($context['file_parts'] as $part)
		echo '
				<label for="on_line', $part['line'], '">', $txt['themeadmin_edit_on_line'], ' ', $part['line'], '</label>:<br>
				<div class="centertext">
					<textarea id="on_line', $part['line'] ,'" name="entire_file[]" cols="80" rows="', $part['lines'] > 14 ? '14' : $part['lines'], '" class="edit_file">', $part['data'], '</textarea>
				</div>';

	echo '
				<div class="padding righttext">
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' class="button_submit">
					<input type="hidden" name="filename" value="', $context['edit_filename'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	// Hopefully it exists.
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
					<input type="hidden" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '">';

	echo '
				</div>
			</div>
		</form>
	</div>';
}

function template_edit_file()
{
	global $context, $scripturl, $txt;

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
				<textarea name="entire_file" id="entire_file" cols="80" rows="20" class="edit_file">', $context['entire_file'], '</textarea><br>
				<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' class="button_submit">
				<input type="hidden" name="filename" value="', $context['edit_filename'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	// Hopefully it exists.
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
				<input type="hidden" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '">';

	echo '
			</div>

		</form>
	</div>';
}

?>