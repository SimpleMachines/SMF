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
 * This is the administration center home.
 */
function template_admin()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Welcome message for the admin.
	echo '
					<div id="admincenter">';

	// Is there an update available?
	echo '
						<div id="update_section"></div>';

	echo '
						<div id="admin_main_section">';

	// Display the "live news" from simplemachines.org.
	echo '
							<div id="live_news" class="floatleft">
								<div class="cat_bar">
									<h3 class="catbg">
										<a href="', $scripturl, '?action=helpadmin;help=live_news" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a> ', $txt['live'], '
									</h3>
								</div>
								<div class="windowbg nopadding">
									<div id="smfAnnouncements">', $txt['lfyi'], '</div>
								</div>
							</div>';

	// Show the user version information from their server.
	echo '
							<div id="supportVersionsTable" class="floatright">
								<div class="cat_bar">
									<h3 class="catbg">
										<a href="', $scripturl, '?action=admin;area=credits">', $txt['support_title'], '</a>
									</h3>
								</div>
								<div class="windowbg nopadding">
									<div id="version_details" class="padding">
										<strong>', $txt['support_versions'], ':</strong><br>
										', $txt['support_versions_forum'], ':
										<em id="yourVersion">', $context['forum_version'], '</em><br>
										', $txt['support_versions_current'], ':
										<em id="smfVersion">??</em><br>
										', $context['can_admin'] ? '<a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br>';

	// Display all the members who can administrate the forum.
	echo '
										<br>
										<strong>', $txt['administrators'], ':</strong>
										', implode(', ', $context['administrators']);
	// If we have lots of admins... don't show them all.
	if (!empty($context['more_admins_link']))
		echo '
							(', $context['more_admins_link'], ')';

	echo '
									</div>
								</div>
							</div>
						</div>';

	foreach ($context[$context['admin_menu_name']]['sections'] as $area_id => $area)
	{
		echo '
						<fieldset id="group_', $area_id, '" class="windowbg admin_group">
							<legend>', $area['title'], '</legend>';

		foreach ($area['areas'] as $item_id => $item)
		{
			// No point showing the 'home' page here, we're already on it!
			if ($area_id == 'forum' && $item_id == 'index')
				continue;

			$url = isset($item['url']) ? $item['url'] : $scripturl . '?action=admin;area=' . $item_id . (!empty($context[$context['admin_menu_name']]['extra_parameters']) ? $context[$context['admin_menu_name']]['extra_parameters'] : '');
			if (!empty($item['icon_file']))
				echo '
							<a href="', $url, '" class="admin_group', !empty($item['inactive']) ? ' inactive' : '', '"><img class="large_admin_menu_icon_file" src="', $item['icon_file'], '" alt="">', $item['label'], '</a>';
			else
				echo '
							<a href="', $url, '"><span class="large_', $item['icon_class'], !empty($item['inactive']) ? ' inactive' : '', '"></span>', $item['label'], '</a>';
		}

		echo '
						</fieldset>';
	}

	echo '
					</div>';

	// The below functions include all the scripts needed from the simplemachines.org site. The language and format are passed for internationalization.
	if (empty($modSettings['disable_smf_js']))
		echo '
					<script src="', $scripturl, '?action=viewsmfile;filename=current-version.js"></script>
					<script src="', $scripturl, '?action=viewsmfile;filename=latest-news.js"></script>';

	// This sets the announcements and current versions themselves ;).
	echo '
					<script><!-- // --><![CDATA[
						var oAdminIndex = new smf_AdminIndex({
							sSelf: \'oAdminCenter\',

							bLoadAnnouncements: true,
							sAnnouncementTemplate: ', JavaScriptEscape('
								<dl>
									%content%
								</dl>
							'), ',
							sAnnouncementMessageTemplate: ', JavaScriptEscape('
								<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
								<dd>
									%message%
								</dd>
							'), ',
							sAnnouncementContainerId: \'smfAnnouncements\',

							bLoadVersions: true,
							sSmfVersionContainerId: \'smfVersion\',
							sYourVersionContainerId: \'yourVersion\',
							sVersionOutdatedTemplate: ', JavaScriptEscape('
								<span class="alert">%currentVersion%</span>
							'), ',

							bLoadUpdateNotification: true,
							sUpdateNotificationContainerId: \'update_section\',
							sUpdateNotificationDefaultTitle: ', JavaScriptEscape($txt['update_available']), ',
							sUpdateNotificationDefaultMessage: ', JavaScriptEscape($txt['update_message']), ',
							sUpdateNotificationTemplate: ', JavaScriptEscape('
								<h3 id="update_title">
									%title%
								</h3>
								<div id="update_message" class="smalltext">
									%message%
								</div>
							'), ',
							sUpdateNotificationLink: smf_scripturl + ', JavaScriptEscape('?action=admin;area=packages;pgdownload;auto;package=%package%;' . $context['session_var'] . '=' . $context['session_id']), '

						});
					// ]]></script>';
}

/**
 * Show some support information and credits to those who helped make this.
 */
function template_credits()
{
	global $context, $settings, $scripturl, $txt;

	// Show the user version information from their server.
	echo '

					<div id="admincenter">
						<div id="support_credits" class="roundframe">
							<div class="sub_bar">
								<h3 class="subbg">
									', $txt['support_title'], ' <img src="', $settings['images_url'], '/smflogo.png" id="credits_logo" alt="">
								</h3>
							</div>
							<div class="padding">
								<strong>', $txt['support_versions'], ':</strong><br>
									', $txt['support_versions_forum'], ':
								<em id="yourVersion">', $context['forum_version'], '</em>', $context['can_admin'] ? ' <a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br>
									', $txt['support_versions_current'], ':
								<em id="smfVersion">??</em><br>';

	// Display all the variables we have server information for.
	foreach ($context['current_versions'] as $version)
	{
		echo '
									', $version['title'], ':
								<em>', $version['version'], '</em>';

		// more details for this item, show them a link
		if ($context['can_admin'] && isset($version['more']))
			echo
								' <a href="', $scripturl, $version['more'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['version_check_more'], '</a>';
		echo '
								<br>';
	}

	echo '
							</div>';

	// Point the admin to common support resources.
	echo '
							<div id="support_resources" class="sub_bar">
								<h3 class="subbg">
									', $txt['support_resources'], '
								</h3>
							</div>
							<div class="padding">
								<p>', $txt['support_resources_p1'], '</p>
								<p>', $txt['support_resources_p2'], '</p>
							</div>';

	// The most important part - the credits :P.
	echo '
							<div id="credits_sections" class="sub_bar">
								<h3 class="subbg">
									', $txt['admin_credits'], '
								</h3>
							</div>
							<div class="padding">';

	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
			echo '
								<p>', $section['pretext'], '</p><hr>';

		echo '
								<dl>';

		foreach ($section['groups'] as $group)
		{
			if (isset($group['title']))
				echo '
									<dt>
										<strong>', $group['title'], ':</strong>
									</dt>';

			echo '
									<dd>', implode(', ', $group['members']), '</dd>';
		}

		echo '
								</dl>';

		if (isset($section['posttext']))
			echo '
								<hr>
								<p>', $section['posttext'], '</p>';
	}

	echo '
							</div>
						</div>
					</div>';

	// This makes all the support information available to the support script...
	echo '
						<script><!-- // --><![CDATA[
							var smfSupportVersions = {};

							smfSupportVersions.forum = "', $context['forum_version'], '";';

	// Don't worry, none of this is logged, it's just used to give information that might be of use.
	foreach ($context['current_versions'] as $variable => $version)
		echo '
							smfSupportVersions.', $variable, ' = "', $version['version'], '";';

	// Now we just have to include the script and wait ;).
	echo '
						// ]]></script>
						<script src="', $scripturl, '?action=viewsmfile;filename=current-version.js"></script>
						<script src="', $scripturl, '?action=viewsmfile;filename=latest-news.js"></script>';

	// This sets the latest support stuff.
	echo '
						<script><!-- // --><![CDATA[
							function smfCurrentVersion()
							{
								var smfVer, yourVer;

								if (!window.smfVersion)
									return;

								smfVer = document.getElementById("smfVersion");
								yourVer = document.getElementById("yourVersion");

								setInnerHTML(smfVer, window.smfVersion);

								var currentVersion = getInnerHTML(yourVer);
								if (currentVersion != window.smfVersion)
									setInnerHTML(yourVer, "<span class=\"alert\">" + currentVersion + "</span>");
							}
							addLoadEvent(smfCurrentVersion)
						// ]]></script>';
}

/**
 * Displays information about file versions installed, and compares them to current version.
 */
function template_view_versions()
{
	global $context, $scripturl, $txt;

	echo '
					<div id="admincenter">
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								', $txt['admin_version_check'], '
							</h3>
						</div>
						<div class="information">', $txt['version_check_desc'], '</div>
						<div id="versions">
							<table class="table_grid">
								<thead>
									<tr class="title_bar">
										<th class="half_table">
											<strong>', $txt['admin_smffile'], '</strong>
										</th>
										<th class="quarter_table">
											<strong>', $txt['dvc_your'], '</strong>
										</th>
										<th class="quarter_table">
											<strong>', $txt['dvc_current'], '</strong>
										</th>
									</tr>
								</thead>
								<tbody>';

	// The current version of the core SMF package.
	echo '
									<tr class="windowbg">
										<td class="half_table">
											', $txt['admin_smfpackage'], '
										</td>
										<td class="quarter_table">
											<em id="yourSMF">', $context['forum_version'], '</em>
										</td>
										<td class="quarter_table">
											<em id="currentSMF">??</em>
										</td>
									</tr>';

	// Now list all the source file versions, starting with the overall version (if all match!).
	echo '
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Sources-link">', $txt['dvc_sources'], '</a>
										</td>
										<td class="quarter_table">
											<em id="yourSources">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentSources">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Sources" class="table_grid">
							<tbody>';

	// Loop through every source file displaying its version - using javascript.
	foreach ($context['file_versions'] as $filename => $version)
		echo '
								<tr class="windowbg">
									<td class="half_table">
										', $filename, '
									</td>
									<td class="quarter_table">
										<em id="yourSources', $filename, '">', $version, '</em>
									</td>
									<td class="quarter_table">
										<em id="currentSources', $filename, '">??</em>
									</td>
								</tr>';

	// Default template files.
	echo '
							</tbody>
							</table>

							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Default-link">', $txt['dvc_default'], '</a>
										</td>
										<td class="quarter_table">
											<em id="yourDefault">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentDefault">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Default" class="table_grid">
								<tbody>';

	foreach ($context['default_template_versions'] as $filename => $version)
		echo '
									<tr class="windowbg">
										<td class="half_table">
											', $filename, '
										</td>
										<td class="quarter_table">
											<em id="yourDefault', $filename, '">', $version, '</em>
										</td>
										<td class="quarter_table">
											<em id="currentDefault', $filename, '">??</em>
										</td>
									</tr>';

	// Now the language files...
	echo '
								</tbody>
							</table>

							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Languages-link">', $txt['dvc_languages'], '</a>
										</td>
										<td class="quarter_table">
											<em id="yourLanguages">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentLanguages">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Languages" class="table_grid">
								<tbody>';

	foreach ($context['default_language_versions'] as $language => $files)
	{
		foreach ($files as $filename => $version)
			echo '
									<tr class="windowbg">
										<td class="half_table">
											', $filename, '.<em>', $language, '</em>.php
										</td>
										<td class="quarter_table">
											<em id="your', $filename, '.', $language, '">', $version, '</em>
										</td>
										<td class="quarter_table">
											<em id="current', $filename, '.', $language, '">??</em>
										</td>
									</tr>';
	}

	echo '
								</tbody>
							</table>';

	// Display the version information for the currently selected theme - if it is not the default one.
	if (!empty($context['template_versions']))
	{
		echo '
							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Templates-link">', $txt['dvc_templates'], '</a>
										</td>
										<td class="quarter_table">
											<em id="yourTemplates">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentTemplates">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Templates" class="table_grid">
								<tbody>';

		foreach ($context['template_versions'] as $filename => $version)
			echo '
									<tr class="windowbg">
										<td class="half_table">
											', $filename, '
										</td>
										<td class="quarter_table">
											<em id="yourTemplates', $filename, '">', $version, '</em>
										</td>
										<td class="quarter_table">
											<em id="currentTemplates', $filename, '">??</em>
										</td>
									</tr>';

		echo '
								</tbody>
							</table>';
	}

	// Display the tasks files version.
	if (!empty($context['tasks_versions']))
	{
		echo '
							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Tasks-link">', $txt['dvc_tasks'] ,'</a>
										</td>
										<td class="quarter_table">
											<em id="yourTemplates">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentTemplates">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Tasks" class="table_grid">
								<tbody>';

		foreach ($context['tasks_versions'] as $filename => $version)
			echo '
									<tr class="windowbg">
										<td class="half_table">
											', $filename, '
										</td>
										<td class="quarter_table">
											<em id="yourTemplates', $filename, '">', $version, '</em>
										</td>
										<td class="quarter_table">
											<em id="currentTemplates', $filename, '">??</em>
										</td>
									</tr>';

		echo '
								</tbody>
							</table>';
	}

	echo '
						</div>
						</div>';

	/* Below is the hefty javascript for this. Upon opening the page it checks the current file versions with ones
	   held at simplemachines.org and works out if they are up to date.  If they aren't it colors that files number
	   red.  It also contains the function, swapOption, that toggles showing the detailed information for each of the
	   file categories. (sources, languages, and templates.) */
	echo '
						<script src="', $scripturl, '?action=viewsmfile;filename=detailed-version.js"></script>
						<script><!-- // --><![CDATA[
							var oViewVersions = new smf_ViewVersions({
								aKnownLanguages: [
									\'.', implode('\',
									\'.', $context['default_known_languages']), '\'
								],
								oSectionContainerIds: {
									Sources: \'Sources\',
									Default: \'Default\',
									Languages: \'Languages\',
									Templates: \'Templates\',
									Tasks: \'Tasks\'
								}
							});
						// ]]></script>';

}

// Form for stopping people using naughty words, etc.
function template_edit_censored()
{
	global $context, $scripturl, $txt, $modSettings;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $txt['settings_saved'], '</div>';

	// First section is for adding/removing words from the censored list.
	echo '
					<div id="admincenter">
						<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=postsettings;sa=censor" method="post" accept-charset="', $context['character_set'], '">
							<div id="section_header" class="cat_bar">
								<h3 class="catbg">
									', $txt['admin_censored_words'], '
								</h3>
							</div>
							<div class="windowbg2">
								<p>', $txt['admin_censored_where'], '</p>';

	// Show text boxes for censoring [bad   ] => [good  ].
	foreach ($context['censored_words'] as $vulgar => $proper)
		echo '
								<div class="block">
									<input type="text" name="censor_vulgar[]" value="', $vulgar, '" size="30"> =&gt; <input type="text" name="censor_proper[]" value="', $proper, '" size="30">
								</div>';

	// Now provide a way to censor more words.
	echo '
								<div class="block">
									<input type="text" name="censor_vulgar[]" size="30" class="input_text"> =&gt; <input type="text" name="censor_proper[]" size="30" class="input_text">
								</div>
								<div id="moreCensoredWords"></div><div class="block" style="display: none;" id="moreCensoredWords_link">
									<a class="button_link" href="#;" onclick="addNewWord(); return false;">', $txt['censor_clickadd'], '</a><br>
								</div>
								<script><!-- // --><![CDATA[
									document.getElementById("moreCensoredWords_link").style.display = "";
								// ]]></script>
								<hr width="100%" size="1" class="hrcolor clear">
								<dl class="settings">
									<dt>
										<strong><label for="allow_no_censored">', $txt['allow_no_censored'], ':</label></strong>
									</dt>
									<dd>
										<input type="checkbox" name="allow_no_censored" value="1" id="allow_no_censored"', empty($modSettings['allow_no_censored']) ? '' : ' checked', ' class="input_check">
									</dd>
									<dt>
										<strong><label for="censorWholeWord_check">', $txt['censor_whole_words'], ':</label></strong>
									</dt>
									<dd>
										<input type="checkbox" name="censorWholeWord" value="1" id="censorWholeWord_check"', empty($modSettings['censorWholeWord']) ? '' : ' checked', ' class="input_check">
									</dd>
									<dt>
										<strong><label for="censorIgnoreCase_check">', $txt['censor_case'], ':</label></strong>
									</dt>
									<dd>
										<input type="checkbox" name="censorIgnoreCase" value="1" id="censorIgnoreCase_check"', empty($modSettings['censorIgnoreCase']) ? '' : ' checked', ' class="input_check">
									</dd>
								</dl>
								<input type="submit" name="save_censor" value="', $txt['save'], '" class="button_submit">
							</div>
							<br>';

	// This table lets you test out your filters by typing in rude words and seeing what comes out.
	echo '
							<div class="cat_bar">
								<h3 class="catbg">
									', $txt['censor_test'], '
								</h3>
							</div>
							<div class="windowbg2">
								<p class="centertext">
									<input type="text" name="censortest" value="', empty($context['censor_test']) ? '' : $context['censor_test'], '" class="input_text">
									<input type="submit" value="', $txt['censor_test_save'], '" class="button_submit">
								</p>
							</div>

							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="', $context['admin-censor_token_var'], '" value="', $context['admin-censor_token'], '">
						</form>
					</div>';
}

// Maintenance is a lovely thing, isn't it?
function template_not_done()
{
	global $context, $txt, $scripturl;

	echo '
					<div id="admincenter">
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								', $txt['not_done_title'], '
							</h3>
						</div>
						<div class="windowbg">
							', $txt['not_done_reason'];

	if (!empty($context['continue_percent']))
		echo '
							<div class="progress_bar">
								<div class="full_bar">', $context['continue_percent'], '%</div>
								<div class="green_percent" style="width: ', $context['continue_percent'], '%;">&nbsp;</div>
							</div>';

	if (!empty($context['substep_enabled']))
		echo '
							<div class="progress_bar">
								<div class="full_bar">', $context['substep_title'], ' (', $context['substep_continue_percent'], '%)</div>
								<div class="blue_percent" style="width: ', $context['substep_continue_percent'], '%;">&nbsp;</div>
							</div>';

	echo '
							<form action="', $scripturl, $context['continue_get_data'], '" method="post" accept-charset="', $context['character_set'], '" name="autoSubmit" id="autoSubmit">
								<input type="submit" name="cont" value="', $txt['not_done_continue'], '" class="button_submit">
								', $context['continue_post_data'], '
							</form>
						</div>
					</div>
					<script><!-- // --><![CDATA[
						var countdown = ', $context['continue_countdown'], ';
						doAutoSubmit();

						function doAutoSubmit()
						{
							if (countdown == 0)
								document.forms.autoSubmit.submit();
							else if (countdown == -1)
								return;

							document.forms.autoSubmit.cont.value = "', $txt['not_done_continue'], ' (" + countdown + ")";
							countdown--;

							setTimeout("doAutoSubmit();", 1000);
						}
					// ]]></script>';
}

// Template for showing settings (Of any kind really!)
function template_show_settings()
{
	global $context, $txt, $settings, $scripturl;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $txt['settings_saved'], '</div>';
	elseif (!empty($context['saved_failed']))
		echo '
					<div class="errorbox">', sprintf($txt['settings_not_saved'], $context['saved_failed']), '</div>';

	if (!empty($context['settings_pre_javascript']))
		echo '
					<script><!-- // --><![CDATA[', $context['settings_pre_javascript'], '// ]]></script>';

	if (!empty($context['settings_insert_above']))
		echo $context['settings_insert_above'];

	echo '
					<div id="admincenter">
						<form id="admin_form_wrapper" action="', $context['post_url'], '" method="post" accept-charset="', $context['character_set'], '"', !empty($context['force_form_onsubmit']) ? ' onsubmit="' . $context['force_form_onsubmit'] . '"' : '', '>';

	// Is there a custom title?
	if (isset($context['settings_title']))
		echo '
							<div class="cat_bar">
								<h3 class="catbg">', $context['settings_title'], '</h3>
							</div>';

	// Have we got a message to display?
	if (!empty($context['settings_message']))
		echo '
							<div class="information">', $context['settings_message'], '</div>';

	// Now actually loop through all the variables.
	$is_open = false;
	foreach ($context['config_vars'] as $config_var)
	{
		// Is it a title or a description?
		if (is_array($config_var) && ($config_var['type'] == 'title' || $config_var['type'] == 'desc'))
		{
			// Not a list yet?
			if ($is_open)
			{
				$is_open = false;
				echo '
									</dl>
							</div>';
			}

			// A title?
			if ($config_var['type'] == 'title')
			{
				echo '
							<div class="cat_bar">
								<h3 class="', !empty($config_var['class']) ? $config_var['class'] : 'catbg', '"', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>
									', ($config_var['help'] ? '<a href="' . $scripturl . '?action=helpadmin;help=' . $config_var['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="'. $txt['help'].'"></span></a>' : ''), '
									', $config_var['label'], '
								</h3>
							</div>';
			}
			// A description?
			else
			{
				echo '
							<div class="information winfo">
								', $config_var['label'], '
							</div>';
			}

			continue;
		}

		// Not a list yet?
		if (!$is_open)
		{
			$is_open = true;
			echo '
							<div class="windowbg2">
								<dl class="settings">';
		}

		// Hang about? Are you pulling my leg - a callback?!
		if (is_array($config_var) && $config_var['type'] == 'callback')
		{
			if (function_exists('template_callback_' . $config_var['name']))
				call_user_func('template_callback_' . $config_var['name']);

			continue;
		}

		if (is_array($config_var))
		{
			// First off, is this a span like a message?
			if (in_array($config_var['type'], array('message', 'warning')))
			{
				echo '
									<dd', $config_var['type'] == 'warning' ? ' class="alert"' : '', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>
										', $config_var['label'], '
									</dd>';
			}
			// Otherwise it's an input box of some kind.
			else
			{
				echo '
									<dt', is_array($config_var) && !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>';

				// Some quick helpers...
				$javascript = $config_var['javascript'];
				$disabled = !empty($config_var['disabled']) ? ' disabled' : '';
				$subtext = !empty($config_var['subtext']) ? '<br><span class="smalltext"> ' . $config_var['subtext'] . '</span>' : '';

				// Various HTML5 input types that are basically enhanced textboxes
				$text_types = array('color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time');

				// Show the [?] button.
				if ($config_var['help'])
					echo '
							<a id="setting_', $config_var['name'], '" href="', $scripturl, '?action=helpadmin;help=', $config_var['help'], '" onclick="return reqOverlayDiv(this.href);"><span class="generic_icons help" title="', $txt['help'],'"></span></a> ';

				echo '
										<a id="setting_', $config_var['name'], '"></a> <span', ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')), '><label for="', $config_var['name'], '">', $config_var['label'], '</label>', $subtext, ($config_var['type'] == 'password' ? '<br><em>' . $txt['admin_confirm_password'] . '</em>' : ''), '</span>
									</dt>
									<dd', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>',
										$config_var['preinput'];

				// Show a check box.
				if ($config_var['type'] == 'check')
					echo '
										<input type="checkbox"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '"', ($config_var['value'] ? ' checked' : ''), ' value="1" class="input_check">';
				// Escape (via htmlspecialchars.) the text box.
				elseif ($config_var['type'] == 'password')
					echo '
										<input type="password"', $disabled, $javascript, ' name="', $config_var['name'], '[0]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' value="*#fakepass#*" onfocus="this.value = \'\'; this.form.', $config_var['name'], '.disabled = false;" class="input_password"><br>
										<input type="password" disabled id="', $config_var['name'], '" name="', $config_var['name'], '[1]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_password">';
				// Show a selection box.
				elseif ($config_var['type'] == 'select')
				{
					echo '
										<select name="', $config_var['name'], '" id="', $config_var['name'], '" ', $javascript, $disabled, (!empty($config_var['multiple']) ? ' multiple="multiple"' : ''), '>';
					foreach ($config_var['data'] as $option)
						echo '
											<option value="', $option[0], '"', (!empty($config_var['value']) && ($option[0] == $config_var['value'] || (!empty($config_var['multiple']) && in_array($option[0], $config_var['value']))) ? ' selected' : ''), '>', $option[1], '</option>';
					echo '
										</select>';
				}
				// List of boards? This requires getBoardList() having been run and the results in $context['board_list'].
				elseif ($config_var['type'] == 'boards')
				{
					$board_list = true;
					$first = true;
					echo '
										<a href="#" class="board_selector">[ ', $txt['select_boards_from_list'], ' ]</a>
										<fieldset>
												<legend class="board_selector"><a href="#">', $txt['select_boards_from_list'], '</a></legend>';
					foreach ($context['board_list'] as $id_cat => $cat)
					{
						if (!$first)
							echo '
											<hr>';
						echo '
											<strong>', $cat['name'], '</strong>
											<ul>';
						foreach ($cat['boards'] as $id_board => $brd)
							echo '
												<li><label><input type="checkbox" name="', $config_var['name'], '[', $brd['id'], ']" value="1" class="input_check"', in_array($brd['id'], $config_var['value']) ? ' checked' : '', '> ', $brd['child_level'] > 0 ? str_repeat('&nbsp; &nbsp;', $brd['child_level']) : '', $brd['name'], '</label></li>';

						echo '
											</ul>';
						$first = false;
					}
					echo '
											</fieldset>';
				}
				// Text area?
				elseif ($config_var['type'] == 'large_text')
					echo '
											<textarea rows="', (!empty($config_var['size']) ? $config_var['size'] : (!empty($config_var['rows']) ? $config_var['rows'] : 4)), '" cols="', (!empty($config_var['cols']) ? $config_var['cols'] : 30), '" ', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '">', $config_var['value'], '</textarea>';
				// Permission group?
				elseif ($config_var['type'] == 'permissions')
					theme_inline_permissions($config_var['name']);
				// BBC selection?
				elseif ($config_var['type'] == 'bbc')
				{
					echo '
											<fieldset id="', $config_var['name'], '">
												<legend>', $txt['bbcTagsToUse_select'], '</legend>
													<ul class="reset">';

					foreach ($context['bbc_columns'] as $bbcColumn)
					{
						foreach ($bbcColumn as $bbcTag)
							echo '
														<li class="list_bbc floatleft">
															<input type="checkbox" name="', $config_var['name'], '_enabledTags[]" id="tag_', $config_var['name'], '_', $bbcTag['tag'], '" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked' : '', ' class="input_check"> <label for="tag_', $config_var['name'], '_', $bbcTag['tag'], '">', $bbcTag['tag'], '</label>', $bbcTag['show_help'] ? ' (<a href="' . $scripturl . '?action=helpadmin;help=tag_' . $bbcTag['tag'] . '" onclick="return reqOverlayDiv(this.href);">?</a>)' : '', '
														</li>';
					}
					echo '							</ul>
												<input type="checkbox" id="bbc_', $config_var['name'], '_select_all" onclick="invertAll(this, this.form, \'', $config_var['name'], '_enabledTags\');"', $context['bbc_sections'][$config_var['name']]['all_selected'] ? ' checked' : '', ' class="input_check"> <label for="bbc_', $config_var['name'], '_select_all"><em>', $txt['bbcTagsToUse_select_all'], '</em></label>
											</fieldset>';
				}
				// A simple message?
				elseif ($config_var['type'] == 'var_message')
					echo '
											<div', !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '', '>', $config_var['var_message'], '</div>';
				// Assume it must be a text box
				else
				{
					// Figure out the exact type - use "number" for "float" and "int".
					$type = in_array($config_var['type'], $text_types) ? $config_var['type'] : ($config_var['type'] == 'int' || $config_var['type'] == 'float' ? 'number' : 'text');

					// Extra options for float/int values - how much to decrease/increase by, the min value and the max value
					// The step - only set if incrementing by something other than 1 for int or 0.1 for float
					$step = isset($config_var['step']) ? ' step="' . $config_var['step'] . '"' : ($config_var['type'] == 'float' ? ' step="0.1"' : '');

					// Minimum allowed value for this setting. SMF forces a default of 0 if not specified in the settings
					$min = isset($config_var['min']) ? ' min="' . $config_var['min'] . '"' : '';

					// Maximum allowed value for this setting.
					$max = isset($config_var['max']) ? ' max="' . $config_var['max'] . '"' : '';

					echo '
											<input type="', $type ,'"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_text"', $min . $max . $step, '>';
				}

				echo isset($config_var['postinput']) ? '
											' . $config_var['postinput'] : '',
										'</dd>';
			}
		}

		else
		{
			// Just show a separator.
			if ($config_var == '')
				echo '
								</dl>
								<hr class="hrcolor clear">
								<dl class="settings">';
			else
				echo '
									<dd>
										<strong>' . $config_var . '</strong>
									</dd>';
		}
	}

	if ($is_open)
		echo '
								</dl>';

	if (empty($context['settings_save_dont_show']))
		echo '
								<input type="submit" value="', $txt['save'], '"', (!empty($context['save_disabled']) ? ' disabled' : ''), (!empty($context['settings_save_onclick']) ? ' onclick="' . $context['settings_save_onclick'] . '"' : ''), ' class="button_submit">';

	if ($is_open)
		echo '
							</div>';


	// At least one token has to be used!
	if (isset($context['admin-ssc_token']))
		echo '
							<input type="hidden" name="', $context['admin-ssc_token_var'], '" value="', $context['admin-ssc_token'], '">';

	if (isset($context['admin-dbsc_token']))
		echo '
							<input type="hidden" name="', $context['admin-dbsc_token_var'], '" value="', $context['admin-dbsc_token'], '">';

	if (isset($context['admin-mp_token']))
		echo '
							<input type="hidden" name="', $context['admin-mp_token_var'], '" value="', $context['admin-mp_token'], '">';

	echo '
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						</form>
					</div>';

	if (!empty($context['settings_post_javascript']))
		echo '
					<script><!-- // --><![CDATA[
					', $context['settings_post_javascript'], '
					// ]]></script>';

	if (!empty($context['settings_insert_below']))
		echo $context['settings_insert_below'];

	// We may have added a board listing. If we did, we need to make it work.
	addInlineJavascript('
	$("legend.board_selector").closest("fieldset").hide();
	$("a.board_selector").click(function(e) {
		e.preventDefault();
		$(this).hide().next("fieldset").show();
	});
	$("fieldset legend.board_selector a").click(function(e) {
		e.preventDefault();
		$(this).closest("fieldset").hide().prev("a").show();
	});
	', true);
}

// Template for showing custom profile fields.
function template_show_custom_profile()
{
	global $context, $txt;

	if (!empty($context['saved_successful']))
		echo '
					<div class="infobox">', $txt['settings_saved'], '</div>';

	// Standard fields.
	template_show_list('standard_profile_fields');

	echo '
					<script><!-- // --><![CDATA[
						var iNumChecks = document.forms.standardProfileFields.length;
						for (var i = 0; i < iNumChecks; i++)
							if (document.forms.standardProfileFields[i].id.indexOf(\'reg_\') == 0)
								document.forms.standardProfileFields[i].disabled = document.forms.standardProfileFields[i].disabled || !document.getElementById(\'active_\' + document.forms.standardProfileFields[i].id.substr(4)).checked;
					// ]]></script><br>';

	// Custom fields.
	template_show_list('custom_profile_fields');
}

// Edit a profile field?
function template_edit_profile_field()
{
	global $context, $txt, $settings, $scripturl;

	// All the javascript for this page - quite a bit in script.js!
	echo '
					<script><!-- // --><![CDATA[
						var startOptID = ', count($context['field']['options']), ';
					// ]]></script>';

	// any errors messages to show?
	if (isset($_GET['msg']))
	{
		loadLanguage('Errors');
		if (isset($txt['custom_option_' . $_GET['msg']]))
			echo '
					<div class="errorbox">',
						$txt['custom_option_' . $_GET['msg']], '
					</div>';
	}

	echo '
					<div id="admincenter">
						<form action="', $scripturl, '?action=admin;area=featuresettings;sa=profileedit;fid=', $context['fid'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
							<div id="section_header" class="cat_bar">
								<h3 class="catbg">', $context['page_title'], '</h3>
							</div>
							<div class="windowbg">
								<fieldset>
									<legend>', $txt['custom_edit_general'], '</legend>

									<dl class="settings">
										<dt>
											<strong><label for="field_name">', $txt['custom_edit_name'], ':</label></strong>
										</dt>
										<dd>
											<input type="text" name="field_name" id="field_name" value="', $context['field']['name'], '" size="20" maxlength="40" class="input_text">
										</dd>
										<dt>
											<strong><label for="field_desc">', $txt['custom_edit_desc'], ':</label></strong>
										</dt>
										<dd>
											<textarea name="field_desc" id="field_desc" rows="3" cols="40">', $context['field']['desc'], '</textarea>
										</dd>
										<dt>
											<strong><label for="profile_area">', $txt['custom_edit_profile'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_profile_desc'], '</span>
										</dt>
										<dd>
											<select name="profile_area" id="profile_area">
												<option value="none"', $context['field']['profile_area'] == 'none' ? ' selected' : '', '>', $txt['custom_edit_profile_none'], '</option>
												<option value="account"', $context['field']['profile_area'] == 'account' ? ' selected' : '', '>', $txt['account'], '</option>
												<option value="forumprofile"', $context['field']['profile_area'] == 'forumprofile' ? ' selected' : '', '>', $txt['forumprofile'], '</option>
												<option value="theme"', $context['field']['profile_area'] == 'theme' ? ' selected' : '', '>', $txt['theme'], '</option>
											</select>
										</dd>
										<dt>
											<strong><label for="reg">', $txt['custom_edit_registration'], ':</label></strong>
										</dt>
										<dd>
											<select name="reg" id="reg">
												<option value="0"', $context['field']['reg'] == 0 ? ' selected' : '', '>', $txt['custom_edit_registration_disable'], '</option>
												<option value="1"', $context['field']['reg'] == 1 ? ' selected' : '', '>', $txt['custom_edit_registration_allow'], '</option>
												<option value="2"', $context['field']['reg'] == 2 ? ' selected' : '', '>', $txt['custom_edit_registration_require'], '</option>
											</select>
										</dd>
										<dt>
											<strong><label for="display">', $txt['custom_edit_display'], ':</label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="display" id="display"', $context['field']['display'] ? ' checked' : '', ' class="input_check">
										</dd>
										<dt>
											<strong><label for="mlist">', $txt['custom_edit_mlist'], ':</label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="mlist" id="show_mlist"', $context['field']['mlist'] ? ' checked' : '', ' class="input_check">
										</dd>
										<dt>
											<strong><label for="placement">', $txt['custom_edit_placement'], ':</label></strong>
										</dt>
										<dd>
											<select name="placement" id="placement">';

	foreach ($context['cust_profile_fields_placement'] as $order => $name)
		echo '
												<option value="', $order ,'"', $context['field']['placement'] == $order ? ' selected' : '', '>', $txt['custom_profile_placement_'. $name], '</option>';

	echo '
											</select>
										</dd>
										<dt>
											<a id="field_show_enclosed" href="', $scripturl, '?action=helpadmin;help=field_show_enclosed" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a>
											<strong><label for="enclose">', $txt['custom_edit_enclose'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_enclose_desc'], '</span>
										</dt>
										<dd>
											<textarea name="enclose" id="enclose" rows="10" cols="50">', @$context['field']['enclose'], '</textarea>
										</dd>
									</dl>
								</fieldset>
								<fieldset>
									<legend>', $txt['custom_edit_input'], '</legend>
									<dl class="settings">
										<dt>
										<strong><label for="field_type">', $txt['custom_edit_picktype'], ':</label></strong>
										</dt>
										<dd>
											<select name="field_type" id="field_type" onchange="updateInputBoxes();">';
	foreach (array('text', 'textarea', 'select', 'radio', 'check') as $field_type)
		echo '
												<option value="', $field_type, '"', $context['field']['type'] == $field_type ? ' selected' : '', '>', $txt['custom_profile_type_' . $field_type], '</option>';

	echo '
											</select>
										</dd>
										<dt id="max_length_dt">
											<strong><label for="max_length_dd">', $txt['custom_edit_max_length'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_max_length_desc'], '</span>
										</dt>
										<dd>
											<input type="text" name="max_length" id="max_length_dd" value="', $context['field']['max_length'], '" size="7" maxlength="6" class="input_text">
										</dd>
										<dt id="dimension_dt">
											<strong><label for="dimension_dd">', $txt['custom_edit_dimension'], ':</label></strong>
										</dt>
										<dd id="dimension_dd">
											<strong>', $txt['custom_edit_dimension_row'], ':</strong> <input type="text" name="rows" value="', $context['field']['rows'], '" size="5" maxlength="3" class="input_text">
											<strong>', $txt['custom_edit_dimension_col'], ':</strong> <input type="text" name="cols" value="', $context['field']['cols'], '" size="5" maxlength="3" class="input_text">
										</dd>
										<dt id="bbc_dt">
											<strong><label for="bbc_dd">', $txt['custom_edit_bbc'], '</label></strong>
										</dt>
										<dd >
											<input type="checkbox" name="bbc" id="bbc_dd"', $context['field']['bbc'] ? ' checked' : '', ' class="input_check">
										</dd>
										<dt id="options_dt">
											<a href="', $scripturl, '?action=helpadmin;help=customoptions" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a>
											<strong><label for="options_dd">', $txt['custom_edit_options'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_options_desc'], '</span>
										</dt>
										<dd id="options_dd">
											<div>';

	foreach ($context['field']['options'] as $k => $option)
	{
		echo '
											', $k == 0 ? '' : '<br>', '<input type="radio" name="default_select" value="', $k, '"', $context['field']['default_select'] == $option ? ' checked' : '', ' class="input_radio"><input type="text" name="select_option[', $k, ']" value="', $option, '" class="input_text">';
	}
	echo '
											<span id="addopt"></span>
											[<a href="" onclick="addOption(); return false;">', $txt['custom_edit_options_more'], '</a>]
											</div>
										</dd>
										<dt id="default_dt">
											<strong><label for="default_dd">', $txt['custom_edit_default'], ':</label></strong>
										</dt>
										<dd>
											<input type="checkbox" name="default_check" id="default_dd"', $context['field']['default_check'] ? ' checked' : '', ' class="input_check">
										</dd>
									</dl>
								</fieldset>
								<fieldset>
									<legend>', $txt['custom_edit_advanced'], '</legend>
									<dl class="settings">
										<dt id="mask_dt">
											<a id="custom_mask" href="', $scripturl, '?action=helpadmin;help=custom_mask" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a>
											<strong><label for="mask">', $txt['custom_edit_mask'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_mask_desc'], '</span>
										</dt>
										<dd>
											<select name="mask" id="mask" onchange="updateInputBoxes();">
												<option value="nohtml"', $context['field']['mask'] == 'nohtml' ? ' selected' : '', '>', $txt['custom_edit_mask_nohtml'], '</option>
												<option value="email"', $context['field']['mask'] == 'email' ? ' selected' : '', '>', $txt['custom_edit_mask_email'], '</option>
												<option value="number"', $context['field']['mask'] == 'number' ? ' selected' : '', '>', $txt['custom_edit_mask_number'], '</option>
												<option value="regex"', strpos($context['field']['mask'], 'regex') === 0 ? ' selected' : '', '>', $txt['custom_edit_mask_regex'], '</option>
											</select>
											<br>
											<span id="regex_div">
												<input type="text" name="regex" value="', $context['field']['regex'], '" size="30" class="input_text">
											</span>
										</dd>
										<dt>
											<strong><label for="private">', $txt['custom_edit_privacy'], ':</label></strong>
											<span class="smalltext">', $txt['custom_edit_privacy_desc'], '</span>
										</dt>
										<dd>
											<select name="private" id="private" onchange="updateInputBoxes();">
												<option value="0"', $context['field']['private'] == 0 ? ' selected' : '', '>', $txt['custom_edit_privacy_all'], '</option>
												<option value="1"', $context['field']['private'] == 1 ? ' selected' : '', '>', $txt['custom_edit_privacy_see'], '</option>
												<option value="2"', $context['field']['private'] == 2 ? ' selected' : '', '>', $txt['custom_edit_privacy_owner'], '</option>
												<option value="3"', $context['field']['private'] == 3 ? ' selected' : '', '>', $txt['custom_edit_privacy_none'], '</option>
											</select>
										</dd>
										<dt id="can_search_dt">
											<strong><label for="can_search_dd">', $txt['custom_edit_can_search'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_can_search_desc'], '</span>
										</dt>
										<dd>
											<input type="checkbox" name="can_search" id="can_search_dd"', $context['field']['can_search'] ? ' checked' : '', ' class="input_check">
										</dd>
										<dt>
											<strong><label for="can_search_check">', $txt['custom_edit_active'], ':</label></strong><br>
											<span class="smalltext">', $txt['custom_edit_active_desc'], '</span>
										</dt>
										<dd>
											<input type="checkbox" name="active" id="can_search_check"', $context['field']['active'] ? ' checked' : '', ' class="input_check">
										</dd>
									</dl>
								</fieldset>
									<input type="submit" name="save" value="', $txt['save'], '" class="button_submit">';

	if ($context['fid'])
		echo '
									<input type="submit" name="delete" value="', $txt['delete'], '" data-confirm="', $txt['custom_edit_delete_sure'], '" class="button_submit you_sure">';

	echo '
							</div>
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="', $context['admin-ecp_token_var'], '" value="', $context['admin-ecp_token'], '">
						</form>
					</div>';

	// Get the javascript bits right!
	echo '
					<script><!-- // --><![CDATA[
						updateInputBoxes();
					// ]]></script>';
}

// Results page for an admin search.
function template_admin_search_results()
{
	global $context, $txt, $settings, $scripturl;

	echo '
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								<div id="quick_search">
									<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="', $context['character_set'], '" class="floatright">
										<input type="search" name="search_term" value="', $context['search_term'], '" class="input_text">
										<input type="hidden" name="search_type" value="', $context['search_type'], '">
										<input type="submit" name="search_go" value="', $txt['admin_search_results_again'], '" class="button_submit">
									</form>
								</div>
								<span class="generic_icons filter"></span>&nbsp;', sprintf($txt['admin_search_results_desc'], $context['search_term']), '
							</h3>
						</div>
					<div class="windowbg2 generic_list_wrapper">';

	if (empty($context['search_results']))
	{
		echo '
						<p class="centertext"><strong>', $txt['admin_search_results_none'], '</strong></p>';
	}
	else
	{
		echo '
						<ol class="search_results">';
		foreach ($context['search_results'] as $result)
		{
			// Is it a result from the online manual?
			if ($context['search_type'] == 'online')
			{
				echo '
							<li>
								<p>
									<a href="', $context['doc_scripturl'], str_replace(' ', '_', $result['title']), '" target="_blank" class="new_win"><strong>', $result['title'], '</strong></a>
								</p>
								<p class="double_height">
									', $result['snippet'], '
								</p>
							</li>';
			}
			// Otherwise it's... not!
			else
			{
				echo '
							<li>
								<a href="', $result['url'], '"><strong>', $result['name'], '</strong></a> [', isset($txt['admin_search_section_' . $result['type']]) ? $txt['admin_search_section_' . $result['type']] : $result['type'] , ']';

				if ($result['help'])
					echo '
								<p class="double_height">', $result['help'], '</p>';

				echo '
							</li>';
			}
		}
		echo '
						</ol>';
	}

	echo '
					</div>';
}

// This little beauty shows questions and answer from the captcha type feature.
function template_callback_question_answer_list()
{
	global $txt, $context;

	foreach ($context['languages'] as $lang_id => $lang)
	{
		$lang_id = strtr($lang_id, array('-utf8' => ''));
		$lang['name'] = strtr($lang['name'], array('-utf8' => ''));

		echo '
						<dt id="qa_dt_', $lang_id, '" class="qa_link">
							<a href="javascript:void(0);">[ ', $lang['name'], ' ]</a>
						</dt>
						<fieldset id="qa_fs_', $lang_id, '" class="qa_fieldset">
							<legend><a href="javascript:void(0);">', $lang['name'], '</a></legend>
							<dl class="settings">
								<dt>
									<strong>', $txt['setup_verification_question'], '</strong>
								</dt>
								<dd>
									<strong>', $txt['setup_verification_answer'], '</strong>
								</dd>';

		if (!empty($context['qa_by_lang'][$lang_id]))
			foreach ($context['qa_by_lang'][$lang_id] as $q_id)
			{
				$question = $context['question_answers'][$q_id];
				echo '
								<dt>
									<input type="text" name="question[', $lang_id, '][', $q_id, ']" value="', $question['question'], '" size="50" class="input_text verification_question">
								</dt>
								<dd>';
				foreach ($question['answers'] as $answer)
					echo '
									<input type="text" name="answer[', $lang_id, '][', $q_id, '][]" value="', $answer, '" size="50" class="input_text verification_answer">';

				echo '
									<div class="qa_add_answer"><a href="javascript:void(0);" onclick="return addAnswer(this);">[ ', $txt['setup_verification_add_answer'], ' ]</a></div>
								</dd>';
			}

		echo '
								<dt class="qa_add_question"><a href="javascript:void(0);">[ ', $txt['setup_verification_add_more'], ' ]</a></dt>
							</dl>
						</fieldset>';
	}
}

// Repairing boards.
function template_repair_boards()
{
	global $context, $txt, $scripturl;

	echo '
					<div id="admincenter">
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">',
								$context['error_search'] ? $txt['errors_list'] : $txt['errors_fixing'] , '
							</h3>
						</div>
						<div class="windowbg">';

	// Are we actually fixing them, or is this just a prompt?
	if ($context['error_search'])
	{
		if (!empty($context['to_fix']))
		{
			echo '
							', $txt['errors_found'], ':
							<ul>';

			foreach ($context['repair_errors'] as $error)
				echo '
								<li>
									', $error, '
								</li>';

			echo '
							</ul>
							<p>
								', $txt['errors_fix'], '
							</p>
							<p class="padding">
								<strong><a href="', $scripturl, '?action=admin;area=repairboards;fixErrors;', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=admin;area=maintain">', $txt['no'], '</a></strong>
							</p>';
		}
		else
			echo '
							<p>', $txt['maintain_no_errors'], '</p>
							<p class="padding">
								<a href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
							</p>';

	}
	else
	{
		if (!empty($context['redirect_to_recount']))
		{
			echo '
							<p>
								', $txt['errors_do_recount'], '
							</p>
							<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=recount" id="recount_form" method="post">
								<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
								<input type="submit" name="recount" id="recount_now" value="', $txt['errors_recount_now'], '">
							</form>';
		}
		else
		{
			echo '
							<p>', $txt['errors_fixed'], '</p>
							<p class="padding">
								<a href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
							</p>';
		}
	}

	echo '
						</div>
					</div>';

	if (!empty($context['redirect_to_recount']))
	{
		echo '
					<script><!-- // --><![CDATA[
						var countdown = 5;
						doAutoSubmit();

						function doAutoSubmit()
						{
							if (countdown == 0)
								document.forms.recount_form.submit();
							else if (countdown == -1)
								return;

							document.forms.recount_form.recount_now.value = "', $txt['errors_recount_now'], ' (" + countdown + ")";
							countdown--;

							setTimeout("doAutoSubmit();", 1000);
						}
					// ]]></script>';
	}
}

// Retrieves info from the php_info function, scrubs and preps it for display
function template_php_info()
{
	global $context, $txt;

	echo '
					<div id="admin_form_wrapper">
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">',
								$txt['phpinfo_settings'], '
							</h3>
						</div>';

	// for each php info area
	foreach ($context['pinfo'] as $area => $php_area)
	{
		echo '
						<table id="', str_replace(' ', '_', $area), '" class="table_grid">
							<thead>
								<tr class="title_bar">
									<th class="equal_table" scope="col"></th>
									<th class="centercol equal_table" scope="col"><strong>', $area, '</strong></th>
									<th class="equal_table" scope="col"></th>
								</tr>
							</thead>
							<tbody>';

		$localmaster = true;

		// and for each setting in this category
		foreach ($php_area as $key => $setting)
		{
			// start of a local / master setting (3 col)
			if (is_array($setting))
			{
				if ($localmaster)
				{
					// heading row for the settings section of this categorys settings
					echo '
								<tr class="title_bar">
									<td class="equal_table"><strong>', $txt['phpinfo_itemsettings'], '</strong></td>
									<td class="equal_table"><strong>', $txt['phpinfo_localsettings'], '</strong></td>
									<td class="equal_table"><strong>', $txt['phpinfo_defaultsettings'], '</strong></td>
								</tr>';
					$localmaster = false;
				}

				echo '
								<tr class="windowbg">
									<td align="left" class="equal_table">', $key, '</td>';

				foreach ($setting as $key_lm => $value)
				{
					echo '
									<td align="left" class="equal_table">', $value, '</td>';
				}
				echo '
								</tr>';
			}
			// just a single setting (2 col)
			else
			{
				echo '
								<tr class="windowbg">
									<td align="left" class="equal_table">', $key,  '</td>
									<td align="left" colspan="2">', $setting, '</td>
								</tr>';
			}
		}
		echo '
							</tbody>
						</table>
						<br>';
	}

	echo '
					</div>';
}

function template_clean_cache_button_above()
{
}

function template_clean_cache_button_below()
{
	global $txt, $scripturl, $context;

	echo '
					<div class="cat_bar">
						<h3 class="catbg">', $txt['maintain_cache'], '</h3>
					</div>
					<div class="windowbg">
						<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="', $context['character_set'], '">
							<p>', $txt['maintain_cache_info'], '</p>
							<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit"></span>
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '">
						</form>
					</div>';
}

function template_admin_quick_search()
{
	global $context, $txt, $scripturl;
	if ($context['user']['is_admin'])
		echo '
							<object id="quick_search">
								<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="', $context['character_set'], '" class="floatright">
									<span class="generic_icons filter centericon"></span>
									<input type="text" name="search_term" value="', $txt['admin_search'], '" onclick="if (this.value == \'', $txt['admin_search'], '\') this.value = \'\';" class="input_text">
									<select name="search_type">
										<option value="internal"', (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] == 'internal' ? ' selected' : ''), '>', $txt['admin_search_type_internal'], '</option>
										<option value="member"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'member' ? ' selected' : ''), '>', $txt['admin_search_type_member'], '</option>
										<option value="online"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'online' ? ' selected' : ''), '>', $txt['admin_search_type_online'], '</option>
									</select>
									<input type="submit" name="search_go" id="search_go" value="', $txt['admin_search_go'], '" class="button_submit">
								</form>
							</object>';
}

?>