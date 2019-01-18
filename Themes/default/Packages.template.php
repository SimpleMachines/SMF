<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * The main template
 */
function template_main()
{
}

/**
 * View package details when installing/uninstalling
 */
function template_view_package()
{
	global $context, $settings, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt[($context['uninstalling'] ? 'un' : '') . 'install_mod'], '</h3>
		</div>
		<div class="information">';

	if ($context['is_installed'])
		echo '
			<strong>', $txt['package_installed_warning1'], '</strong><br>
			<br>
			', $txt['package_installed_warning2'], '<br>
			<br>';

	echo $txt['package_installed_warning3'], '
		</div>
		<br>';

	// Do errors exist in the install? If so light them up like a christmas tree.
	if ($context['has_failure'])
		echo '
		<div class="errorbox">
			', sprintf($txt['package_will_fail_title'], $txt['package_' . ($context['uninstalling'] ? 'uninstall' : 'install')]), '<br>
			', sprintf($txt['package_will_fail_warning'], $txt['package_' . ($context['uninstalling'] ? 'uninstall' : 'install')]),
			!empty($context['failure_details']) ? '<br><br><strong>' . $context['failure_details'] . '</strong>' : '', '
		</div>';

	// Display the package readme if one exists
	if (isset($context['package_readme']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_' . ($context['uninstalling'] ? 'un' : '') . 'install_readme'], '</h3>
		</div>
		<div class="windowbg">
			', $context['package_readme'], '
			<span class="floatright">', $txt['package_available_readme_language'], '
				<select name="readme_language" id="readme_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = smf_prepareScriptUrl(smf_scripturl + \'', '?action=admin;area=packages;sa=', $context['uninstalling'] ? 'uninstall' : 'install', ';package=', $context['filename'], ';readme=\' + this.options[this.selectedIndex].value + \';license=\' + get_selected(\'license_language\'));">';

		foreach ($context['readmes'] as $a => $b)
			echo '
					<option value="', $b, '"', $a === 'selected' ? ' selected' : '', '>', $b == 'default' ? $txt['package_readme_default'] : ucfirst($b), '</option>';

		echo '
				</select>
			</span>
		</div>
		<br>';
	}

	// Did they specify a license to display?
	if (isset($context['package_license']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_install_license'], '</h3>
		</div>
		<div class="windowbg">
			', $context['package_license'], '
			<span class="floatright">', $txt['package_available_license_language'], '
				<select name="license_language" id="license_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = smf_prepareScriptUrl(smf_scripturl + \'', '?action=admin;area=packages;sa=install', ';package=', $context['filename'], ';license=\' + this.options[this.selectedIndex].value + \';readme=\' + get_selected(\'readme_language\'));">';

		foreach ($context['licenses'] as $a => $b)
			echo '
					<option value="', $b, '"', $a === 'selected' ? ' selected' : '', '>', $b == 'default' ? $txt['package_license_default'] : ucfirst($b), '</option>';
		echo '
				</select>
			</span>
		</div>
		<br>';
	}

	echo '
		<form action="', !empty($context['post_url']) ? $context['post_url'] : '#', '" onsubmit="submitonce(this);" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['uninstalling'] ? $txt['package_uninstall_actions'] : $txt['package_install_actions'], ' &quot;', $context['package_name'], '&quot;
				</h3>
			</div>';

	// Are there data changes to be removed?
	if ($context['uninstalling'] && !empty($context['database_changes']))
	{
		// This is really a special case so we're adding style inline
		echo '
			<div class="windowbg" style="margin: 0; border-radius: 0;">
				<label for="do_db_changes"><input type="checkbox" name="do_db_changes" id="do_db_changes">', $txt['package_db_uninstall'], '</label> [<a href="#" onclick="return swap_database_changes();">', $txt['package_db_uninstall_details'], '</a>]
				<div id="db_changes_div">
					', $txt['package_db_uninstall_actions'], ':
					<ul>';

		foreach ($context['database_changes'] as $change)
			echo '
						<li>', $change, '</li>';

		echo '
					</ul>
				</div>
			</div>';
	}

	echo '
			<div class="information">';

	if (empty($context['actions']) && empty($context['database_changes']))
		echo '
				<br>
				<div class="errorbox">
					', $txt['corrupt_compatible'], '
				</div>
			</div><!-- .information -->';
	else
	{
		echo '
				', $txt['perform_actions'], '
			</div><!-- .information -->
			<br>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col" width="20"></th>
						<th scope="col" width="30"></th>
						<th scope="col" class="lefttext">', $txt['package_install_type'], '</th>
						<th scope="col" class="lefttext" width="50%">', $txt['package_install_action'], '</th>
						<th class="lefttext" scope="col" width="20%">', $txt['package_install_desc'], '</th>
					</tr>
				</thead>
				<tbody>';

		$i = 1;
		$action_num = 1;
		$js_operations = array();
		foreach ($context['actions'] as $packageaction)
		{
			// Did we pass or fail?  Need to now for later on.
			$js_operations[$action_num] = isset($packageaction['failed']) ? $packageaction['failed'] : 0;

			echo '
					<tr class="windowbg">
						<td style="width: 5%;">', isset($packageaction['operations']) ? '<img id="operation_img_' . $action_num . '" src="' . $settings['images_url'] . '/selected_open.png" alt="*" style="display: none;">' : '', '</td>
						<td style="width: 5%;">', $i++, '.</td>
						<td style="width: 20%;">', $packageaction['type'], '</td>
						<td style="width: 50%;">', $packageaction['action'], '</td>
						<td style="width: 20%";>', $packageaction['description'], '</td>
					</tr>';

			// Is there water on the knee? Operation!
			if (isset($packageaction['operations']))
			{
				echo '
					<tr id="operation_', $action_num, '">
						<td colspan="5" class="windowbg">
							<table class="table_grid">';

				// Show the operations.
				$operation_num = 1;
				foreach ($packageaction['operations'] as $operation)
				{
					// Determine the position text.
					$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');

					echo '
								<tr class="windowbg">
									<td class="smalltext" style="width: 5%;">
										<a href="' . $scripturl . '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], !empty($context['install_id']) ? ';install_id=' . $context['install_id'] : '', ';package=', $_REQUEST['package'], ';filename=', $operation['filename'], ($operation['is_boardmod'] ? ';boardmod' : ''), (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'uninstall' ? ';reverse' : ''), '" onclick="return reqWin(this.href, 680, 400, false);"><span class="main_icons package_ops"></span></a>
									</td>
									<td class="smalltext" style="width: 5%;">', $operation_num, '.</td>
									<td class="smalltext" style="width: 20%;">', $txt[$operation_text], '</td>
									<td class="smalltext" style="width: 50%;">', $operation['action'], '</td>
									<td class="smalltext" style="width: 20%;">', $operation['description'], !empty($operation['ignore_failure']) ? ' (' . $txt['operation_ignore'] . ')' : '', '</td>
								</tr>';

					$operation_num++;
				}

				echo '
							</table>
						</td>
					</tr>';

				// Increase it.
				$action_num++;
			}
		}
		echo '
				</tbody>
			</table>';

		// What if we have custom themes we can install into? List them too!
		if (!empty($context['theme_actions']))
		{
			echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['uninstalling'] ? $txt['package_other_themes_uninstall'] : $txt['package_other_themes'], '
				</h3>
			</div>
			<div id="custom_changes">
				<div class="information">
					', $txt['package_other_themes_desc'], '
				</div>
				<table class="table_grid">';

			// Loop through each theme and display it's name, and then it's details.
			foreach ($context['theme_actions'] as $id => $theme)
			{
				// Pass?
				$js_operations[$action_num] = !empty($theme['has_failure']);

				echo '
					<tr class="title_bar">
						<td></td>
						<td>';

				if (!empty($context['themes_locked']))
					echo '
							<input type="hidden" name="custom_theme[]" value="', $id, '">';
				echo '
							<input type="checkbox" name="custom_theme[]" id="custom_theme_', $id, '" value="', $id, '" onclick="', (!empty($theme['has_failure']) ? 'if (this.form.custom_theme_' . $id . '.checked && !confirm(\'' . $txt['package_theme_failure_warning'] . '\')) return false;' : ''), 'invertAll(this, this.form, \'dummy_theme_', $id, '\', true);"', !empty($context['themes_locked']) ? ' disabled checked' : '', '>
						</td>
						<td colspan="3">
							', $theme['name'], '
						</td>
					</tr>';

				foreach ($theme['actions'] as $action)
				{
					echo '
					<tr class="windowbg">
						<td>', isset($packageaction['operations']) ? '<img id="operation_img_' . $action_num . '" src="' . $settings['images_url'] . '/selected_open.png" alt="*" style="display: none;">' : '', '</td>
						<td width="30">
							<input type="checkbox" name="theme_changes[]" value="', !empty($action['value']) ? $action['value'] : '', '" id="dummy_theme_', $id, '"', (!empty($action['not_mod']) ? '' : ' disabled'), !empty($context['themes_locked']) ? ' checked' : '', '>
						</td>
						<td>', $action['type'], '</td>
						<td width="50%">', $action['action'], '</td>
						<td width="20%"><strong>', $action['description'], '</strong></td>
					</tr>';

					// Is there water on the knee? Operation!
					if (isset($action['operations']))
					{
						echo '
					<tr id="operation_', $action_num, '">
						<td colspan="5" class="windowbg">
							<table width="100%">';

						$operation_num = 1;
						foreach ($action['operations'] as $operation)
						{
							// Determine the position text.
							$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');

							echo '
								<tr class="windowbg">
									<td width="0"></td>
									<td width="30" class="smalltext"><a href="' . $scripturl . '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], !empty($context['install_id']) ? ';install_id=' . $context['install_id'] : '', ';package=', $_REQUEST['package'], ';filename=', $operation['filename'], ($operation['is_boardmod'] ? ';boardmod' : ''), (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'uninstall' ? ';reverse' : ''), '" onclick="return reqWin(this.href, 600, 400, false);"><span class="main_icons package_ops"></span></a></td>
									<td width="30" class="smalltext">', $operation_num, '.</td>
									<td width="23%" class="smalltext">', $txt[$operation_text], '</td>
									<td width="50%" class="smalltext">', $operation['action'], '</td>
									<td width="20%" class="smalltext">', $operation['description'], !empty($operation['ignore_failure']) ? ' (' . $txt['operation_ignore'] . ')' : '', '</td>
								</tr>';
							$operation_num++;
						}

						echo '
							</table>
						</td>
					</tr>';

						// Increase it.
						$action_num++;
					}
				}
			}

			echo '
				</table>
			</div><!-- #custom_changes -->';
		}
	}

	// Are we effectively ready to install?
	if (!$context['ftp_needed'] && (!empty($context['actions']) || !empty($context['database_changes'])))
		echo '
			<div class="righttext padding">
				<input type="submit" value="', $context['uninstalling'] ? $txt['package_uninstall_now'] : $txt['package_install_now'], '" onclick="return ', !empty($context['has_failure']) ? '(submitThisOnce(this) &amp;&amp; confirm(\'' . ($context['uninstalling'] ? $txt['package_will_fail_popup_uninstall'] : $txt['package_will_fail_popup']) . '\'))' : 'submitThisOnce(this)', ';" class="button">
			</div>';

	// If we need ftp information then demand it!
	elseif ($context['ftp_needed'])
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['package_ftp_necessary'], '</h3>
			</div>
			<div>
				', template_control_chmod(), '
			</div>';

	echo '

			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">', (isset($context['form_sequence_number']) && !$context['ftp_needed']) ? '
			<input type="hidden" name="seqnum" value="' . $context['form_sequence_number'] . '">' : '', '
		</form>';

	// Toggle options.
	echo '
	<script>
		var aOperationElements = new Array();';

	// Operations.
	if (!empty($js_operations))
	{
		foreach ($js_operations as $key => $operation)
			echo '
		aOperationElements[', $key, '] = new smc_Toggle({
			bToggleEnabled: true,
			bNoAnimate: true,
			bCurrentlyCollapsed: ', $operation ? 'false' : 'true', ',
			aSwappableContainers: [
				\'operation_', $key, '\'
			],
			aSwapImages: [
				{
					sId: \'operation_img_', $key, '\',
					srcExpanded: smf_images_url + \'/selected_open.png\',
					altExpanded: \'*\',
					srcCollapsed: smf_images_url + \'/selected.png\',
					altCollapsed: \'*\'
				}
			]
		});';
	}

	echo '
	</script>';

	// Get the currently selected item from a select list
	echo '
	<script>
		function get_selected(id)
		{
			var aSelected = document.getElementById(id);
			for (var i = 0; i < aSelected.options.length; i++)
			{
				if (aSelected.options[i].selected == true)
					return aSelected.options[i].value;
			}
			return aSelected.options[0];
		}
	</script>';

	// And a bit more for database changes.
	if (!empty($context['database_changes']))
		echo '
	<script>
		var database_changes_area = document.getElementById(\'db_changes_div\');
		var db_vis = false;
		database_changes_area.classList.add(\'hidden\');
	</script>';
}

/**
 * Extract package contents
 */
function template_extract_package()
{
	global $context, $txt, $scripturl;

	if (!empty($context['redirect_url']))
		echo '
	<script>
		setTimeout("doRedirect();", ', empty($context['redirect_timeout']) ? '5000' : $context['redirect_timeout'], ');

		function doRedirect()
		{
			window.location = "', $context['redirect_url'], '";
		}
	</script>';

	if (empty($context['redirect_url']))
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['uninstalling'] ? $txt['uninstall'] : $txt['extracting'], '</h3>
		</div>
		<div class="information">', $txt['package_installed_extract'], '</div>';
	else
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_installed_redirecting'], '</h3>
		</div>';

	echo '
		<div class="windowbg">';

	// If we are going to redirect we have a slightly different agenda.
	if (!empty($context['redirect_url']))
		echo '
			', $context['redirect_text'], '<br><br>
			<a href="', $context['redirect_url'], '">', $txt['package_installed_redirect_go_now'], '</a> | <a href="', $scripturl, '?action=admin;area=packages;sa=browse">', $txt['package_installed_redirect_cancel'], '</a>';

	elseif ($context['uninstalling'])
		echo '
			', $txt['package_uninstall_done'];

	elseif ($context['install_finished'])
	{
		if ($context['extract_type'] == 'avatar')
			echo '
			', $txt['avatars_extracted'];

		elseif ($context['extract_type'] == 'language')
			echo '
			', $txt['language_extracted'];

		else
			echo '
			', $txt['package_installed_done'];
	}
	else
		echo '
			', $txt['corrupt_compatible'];

	echo '
		</div><!-- .windowbg -->';

	// Show the "restore permissions" screen?
	if (function_exists('template_show_list') && !empty($context['restore_file_permissions']['rows']))
	{
		echo '<br>';
		template_show_list('restore_file_permissions');
	}
}

/**
 * List files in a package
 */
function template_list()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['list_file'], '</h3>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['files_archive'], ' ', $context['filename'], ':</h3>
		</div>
		<div class="windowbg">
			<ol>';

	foreach ($context['files'] as $fileinfo)
		echo '
				<li><a href="', $scripturl, '?action=admin;area=packages;sa=examine;package=', $context['filename'], ';file=', $fileinfo['filename'], '" title="', $txt['view'], '">', $fileinfo['filename'], '</a> (', $fileinfo['size'], ' ', $txt['package_bytes'], ')</li>';

	echo '
			</ol>
			<br>
			<a href="', $scripturl, '?action=admin;area=packages">[ ', $txt['back'], ' ]</a>
		</div>';
}

/**
 * Examine a single file within a package
 */
function template_examine()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_examine_file'], '</h3>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_file_contents'], ' ', $context['filename'], ':</h3>
		</div>
		<div class="windowbg">
			<pre class="file_content">', $context['filedata'], '</pre>
			<a href="', $scripturl, '?action=admin;area=packages;sa=list;package=', $context['package'], '">[ ', $txt['list_files'], ' ]</a>
		</div>';
}

/**
 * List all packages
 */
function template_browse()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
		<div id="update_section"></div>
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['packages_adding_title'], '
				</h3>
			</div>
			<div class="information">
				', $txt['packages_adding'], '
			</div>

			<script>
				window.smfForum_scripturl = smf_scripturl;
				window.smfForum_sessionid = smf_session_id;
				window.smfForum_sessionvar = smf_session_var;';

	// Make a list of already installed mods so nothing is listed twice ;).
	echo '
				window.smfInstalledPackages = ["', implode('", "', $context['installed_mods']), '"];
				window.smfVersion = "', $context['forum_version'], '";
			</script>
			<div id="yourVersion" style="display:none">', $context['forum_version'], '</div>';

	if (empty($modSettings['disable_smf_js']))
		echo '
			<script src="', $scripturl, '?action=viewsmfile;filename=latest-news.js"></script>';

	// This sets the announcements and current versions themselves ;).
	echo '
			<script>
				var oAdminIndex = new smf_AdminIndex({
					sSelf: \'oAdminCenter\',
					bLoadAnnouncements: false,
					bLoadVersions: false,
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
			</script>';

	echo '
		</div><!-- #admin_form_wrapper -->';

	$mods_available = false;
	foreach ($context['modification_types'] as $type)
	{
		if (!empty($context['available_' . $type]))
		{
			template_show_list('packages_lists_' . $type);
			$mods_available = true;
		}
	}

	if (!$mods_available)
		echo '
		<div class="noticebox">', $txt['no_packages'], '</div>';
	else
		echo '
		<br>';

	// The advanced (emulation) box, collapsed by default
	echo '
		<form action="', $scripturl, '?action=admin;area=packages;sa=browse" method="get">
			<div id="advanced_box">
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="advanced_panel_toggle" class="floatright" style="display: none;"></span>
						<a href="#" id="advanced_panel_link">', $txt['package_advanced_button'], '</a>
					</h3>
				</div>
				<div id="advanced_panel_div" class="windowbg">
					<p>
						', $txt['package_emulate_desc'], '
					</p>
					<dl class="settings">
						<dt>
							<strong>', $txt['package_emulate'], ':</strong><br>
							<span class="smalltext">
								<a href="#" onclick="return revert();">', $txt['package_emulate_revert'], '</a>
							</span>
						</dt>
						<dd>
							<a id="revert" name="revert"></a>
							<select name="version_emulate" id="ve">';

	foreach ($context['emulation_versions'] as $version)
		echo '
								<option value="', $version, '"', ($version == $context['selected_version'] ? ' selected="selected"' : ''), '>', $version, '</option>';

	echo '
							</select>
						</dd>
					</dl>
					<div class="righttext padding">
						<input type="submit" value="', $txt['package_apply'], '" class="button">
					</div>
				</div><!-- #advanced_panel_div -->
			</div><!-- #advanced_box -->
			<input type="hidden" name="action" value="admin">
			<input type="hidden" name="area" value="packages">
			<input type="hidden" name="sa" value="browse">
		</form>
	<script>
		var oAdvancedPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				\'advanced_panel_div\'
			],
			aSwapImages: [
				{
					sId: \'advanced_panel_toggle\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'advanced_panel_link\',
					msgExpanded: ', JavaScriptEscape($txt['package_advanced_button']), ',
					msgCollapsed: ', JavaScriptEscape($txt['package_advanced_button']), '
				}
			]
		});
		function revert()
		{
			var default_version = "', $context['default_version'], '";
			$("#ve").find("option").filter(function(index) {
				return default_version === $(this).text();
			}).attr("selected", "selected");
			return false;
		}
	</script>';
}

/**
 * List package servers
 */
function template_servers()
{
	global $context, $txt, $scripturl;

	if (!empty($context['package_ftp']['error']))
		echo '
	<div class="errorbox">
		<pre>', $context['package_ftp']['error'], '</pre>
	</div>';

	echo '
	<div id="admin_form_wrapper">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_upload_title'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', $scripturl, '?action=admin;area=packages;get;sa=upload" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data">
				<dl class="settings">
					<dt>
						<strong>', $txt['package_upload_select'], ':</strong>
					</dt>
					<dd>
						<input type="file" name="package" size="38">
					</dd>
				</dl>
				<input type="submit" value="', $txt['upload'], '" class="button">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">
				<a class="download_new_package">
					<span class="toggle_down floatright" alt="*" title="', $txt['show'], '"></span>
					', $txt['download_new_package'], '
				</a>
			</h3>
		</div>
		<div class="new_package_content">';

	if ($context['package_download_broken'])
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['package_ftp_necessary'], '</h3>
			</div>
			<div class="windowbg">
				<p>
					', $txt['package_ftp_why_download'], '
				</p>
				<form action="', $scripturl, '?action=admin;area=packages;get" method="post" accept-charset="', $context['character_set'], '">
					<dl class="settings">
						<dt>
							<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $context['package_ftp']['server'], '">
							<label for="ftp_port">', $txt['package_ftp_port'], ':</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', $context['package_ftp']['port'], '">
						</dd>
						<dt>
							<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $context['package_ftp']['username'], '">
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
					<div class="righttext">
						<input type="submit" value="', $txt['package_proceed'], '" class="button">
					</div>
				</form>
			</div><!-- .windowbg -->';
	}

	echo '
			<div class="windowbg">
				<fieldset>
					<legend>' . $txt['package_servers'] . '</legend>
					<ul class="package_servers">';

	foreach ($context['servers'] as $server)
		echo '
						<li class="flow_auto">
							<span class="floatleft">' . $server['name'] . '</span>
							<span class="package_server floatright"><a href="' . $scripturl . '?action=admin;area=packages;get;sa=remove;server=' . $server['id'] . ';', $context['session_var'], '=', $context['session_id'], '">[ ' . $txt['delete'] . ' ]</a></span>
							<span class="package_server floatright"><a href="' . $scripturl . '?action=admin;area=packages;get;sa=browse;server=' . $server['id'] . '">[ ' . $txt['package_browse'] . ' ]</a></span>
						</li>';
	echo '
					</ul>
				</fieldset>
				<fieldset>
					<legend>' . $txt['add_server'] . '</legend>
					<form action="' . $scripturl . '?action=admin;area=packages;get;sa=add" method="post" accept-charset="', $context['character_set'], '">
						<dl class="settings">
							<dt>
								<strong>' . $txt['server_name'] . ':</strong>
							</dt>
							<dd>
								<input type="text" name="servername" size="44" value="SMF">
							</dd>
							<dt>
								<strong>' . $txt['serverurl'] . ':</strong>
							</dt>
							<dd>
								<input type="text" name="serverurl" size="44" value="https://">
							</dd>
						</dl>
						<div class="righttext">
							<input type="submit" value="' . $txt['add_server'] . '" class="button">
							<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
						</div>
					</form>
				</fieldset>
				<fieldset>
					<legend>', $txt['package_download_by_url'], '</legend>
					<form action="', $scripturl, '?action=admin;area=packages;get;sa=download;byurl;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
						<dl class="settings">
							<dt>
								<strong>' . $txt['serverurl'] . ':</strong>
							</dt>
							<dd>
								<input type="text" name="package" size="44" value="https://">
							</dd>
							<dt>
								<strong>', $txt['package_download_filename'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="filename" size="44"><br>
								<span class="smalltext">', $txt['package_download_filename_info'], '</span>
							</dd>
						</dl>
						<input type="submit" value="', $txt['download'], '" class="button">
					</form>
				</fieldset>
			</div><!-- .windowbg -->
		</div><!-- .new_package_content -->
	</div><!-- #admin_form_wrapper -->';
}

/**
 * Confirm package operation
 */
function template_package_confirm()
{
	global $context, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', $context['confirm_message'], '</p>
			<a href="', $context['proceed_href'], '">[ ', $txt['package_confirm_proceed'], ' ]</a> <a href="JavaScript:history.go(-1);">[ ', $txt['package_confirm_go_back'], ' ]</a>
		</div>';
}

/**
 * List packages.
 */
function template_package_list()
{
	global $context, $txt, $smcFunc;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">';

	// No packages, as yet.
	if (empty($context['package_list']))
		echo '
			<ul>
				<li>', $txt['no_packages'], '</li>
			</ul>';

	// List out the packages...
	else
	{
		echo '
			<ul id="package_list">';

		foreach ($context['package_list'] as $i => $packageSection)
		{
			echo '
				<li>
					<strong><span id="ps_img_', $i, '" class="toggle_up" alt="*" style="display: none;"></span> ', $packageSection['title'], '</strong>';

			if (!empty($packageSection['text']))
				echo '
					<div class="sub_bar">
						<h3 class="subbg">', $packageSection['text'], '</h3>
					</div>';

			echo '
					<', $context['list_type'], ' id="package_section_', $i, '" class="packages">';

			foreach ($packageSection['items'] as $id => $package)
			{
				echo '
						<li>';

				// Textual message. Could be empty just for a blank line...
				if ($package['is_text'])
					echo '
							', empty($package['name']) ? '&nbsp;' : $package['name'];

				// This is supposed to be a rule..
				elseif ($package['is_line'])
					echo '
							<hr>';

				// A remote link.
				elseif ($package['is_remote'])
					echo '
							<strong>', $package['link'], '</strong>';

				// A title?
				elseif ($package['is_heading'] || $package['is_title'])
					echo '
							<strong>', $package['name'], '</strong>';

				// Otherwise, it's a package.
				else
				{
					// 1. Some mod [ Download ].
					echo '
						<strong><span id="ps_img_', $i, '_pkg_', $id, '" class="toggle_up" alt="*" style="display: none;"></span> ', $package['can_install'] || !empty($package['can_emulate_install']) ? '<strong>' . $package['name'] . '</strong> <a href="' . $package['download']['href'] . '">[ ' . $txt['download'] . ' ]</a>' : $package['name'], '</strong>
						<ul id="package_section_', $i, '_pkg_', $id, '" class="package_section">';

					// Show the mod type?
					if ($package['type'] != '')
						echo '
							<li class="package_section">
								', $txt['package_type'], ':&nbsp; ', $smcFunc['ucwords']($smcFunc['strtolower']($package['type'])), '
							</li>';

					// Show the version number?
					if ($package['version'] != '')
						echo '
							<li class="package_section">
								', $txt['mod_version'], ':&nbsp; ', $package['version'], '
							</li>';

					// How 'bout the author?
					if (!empty($package['author']) && $package['author']['name'] != '' && isset($package['author']['link']))
						echo '
							<li class="package_section">
								', $txt['mod_author'], ':&nbsp; ', $package['author']['link'], '
							</li>';

					// The homepage...
					if ($package['author']['website']['link'] != '')
						echo '
							<li class="package_section">
								', $txt['author_website'], ':&nbsp; ', $package['author']['website']['link'], '
							</li>';

					// Description: bleh bleh!
					// Location of file: http://someplace/.
					echo '
							<li class="package_section">
								', $txt['file_location'], ':&nbsp; <a href="', $package['href'], '">', $package['href'], '</a>
							</li>
							<li class="package_section">
								<div class="information">
									', $txt['package_description'], ':&nbsp; ', $package['description'], '
								</div>
							</li>
						</ul>';
				}

				echo '
					</li>';
			}
			echo '
				</', $context['list_type'], '>
				</li>';
		}
		echo '
			</ul>';
	}

	echo '
		</div><!-- .windowbg -->';

	// Now go through and turn off all the sections.
	if (!empty($context['package_list']))
	{
		$section_count = count($context['package_list']);

		echo '
	<script>';

		foreach ($context['package_list'] as $section => $ps)
		{
			echo '
		var oPackageServerToggle_', $section, ' = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', count($ps['items']) == 1 || $section_count == 1 ? 'false' : 'true', ',
			aSwappableContainers: [
				\'package_section_', $section, '\'
			],
			aSwapImages: [
				{
					sId: \'ps_img_', $section, '\',
					altExpanded: \'*\',
					altCollapsed: \'*\'
				}
			]
		});';

			foreach ($ps['items'] as $id => $package)
			{
				if (!$package['is_text'] && !$package['is_line'] && !$package['is_remote'])
					echo '
		var oPackageToggle_', $section, '_pkg_', $id, ' = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				\'package_section_', $section, '_pkg_', $id, '\'
			],
			aSwapImages: [
				{
					sId: \'ps_img_', $section, '_pkg_', $id, '\',
					altExpanded: \'*\',
					altCollapsed: \'*\'
				}
			]
		});';
			}
		}

		echo '
	</script>';
	}
}

/**
 * Confirmation page showing a package was uploaded/downloaded successfully.
 */
function template_downloaded()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>
				', (empty($context['package_server']) ? $txt['package_uploaded_successfully'] : $txt['package_downloaded_successfully']), '
			</p>
			<ul>
				<li>
					<span class="floatleft"><strong>', $context['package']['name'], '</strong></span>
					<span class="package_server floatright">', $context['package']['list_files']['link'], '</span>
					<span class="package_server floatright">', $context['package']['install']['link'], '</span>
				</li>
			</ul>
			<br><br>
			<p><a href="', $scripturl, '?action=admin;area=packages;get', (isset($context['package_server']) ? ';sa=browse;server=' . $context['package_server'] : ''), '">[ ', $txt['back'], ' ]</a></p>
		</div>';
}

/**
 * Installation options - FTP info and backup settings
 */
function template_install_options()
{
	global $context, $txt, $scripturl;

	if (!empty($context['saved_successful']))
		echo '
	<div class="infobox">', $txt['settings_saved'], '</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_install_options'], '</h3>
		</div>
		<div class="information noup">
			', $txt['package_install_options_ftp_why'], '
		</div>
		<div class="windowbg noup">
			<form action="', $scripturl, '?action=admin;area=packages;sa=options" method="post" accept-charset="', $context['character_set'], '">
				<dl class="settings">
					<dt>
						<label for="pack_server"><strong>', $txt['package_install_options_ftp_server'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_server" id="pack_server" value="', $context['package_ftp_server'], '" size="30">
					</dd>
					<dt>
						<label for="pack_port"><strong>', $txt['package_install_options_ftp_port'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_port" id="pack_port" size="3" value="', $context['package_ftp_port'], '">
					</dd>
					<dt>
						<label for="pack_user"><strong>', $txt['package_install_options_ftp_user'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_user" id="pack_user" value="', $context['package_ftp_username'], '" size="30">
					</dd>
					<dt>
						<label for="package_make_backups">', $txt['package_install_options_make_backups'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="package_make_backups" id="package_make_backups" value="1"', $context['package_make_backups'] ? ' checked' : '', '>
					</dd>
					<dt>
						<label for="package_make_full_backups">', $txt['package_install_options_make_full_backups'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="package_make_full_backups" id="package_make_full_backups" value="1"', $context['package_make_full_backups'] ? ' checked' : '', '>
					</dd>
				</dl>

				<input type="submit" name="save" value="', $txt['save'], '" class="button">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div><!-- .windowbg -->';
}

/**
 * CHMOD control form
 *
 * @return bool False if nothing to do.
 */
function template_control_chmod()
{
	global $context, $txt;

	// Nothing to do? Brilliant!
	if (empty($context['package_ftp']))
		return false;

	if (empty($context['package_ftp']['form_elements_only']))
	{
		echo '
				', sprintf($txt['package_ftp_why'], 'document.getElementById(\'need_writable_list\').style.display = \'\'; return false;'), '<br>
				<div id="need_writable_list" class="smalltext">
					', $txt['package_ftp_why_file_list'], '
					<ul style="display: inline;">';

		if (!empty($context['notwritable_files']))
			foreach ($context['notwritable_files'] as $file)
				echo '
						<li>', $file, '</li>';

		echo '
					</ul>';

		if (!$context['server']['is_windows'])
			echo '
					<hr>
					', $txt['package_chmod_linux'], '<br>
					<samp># chmod a+w ', implode(' ', $context['notwritable_files']), '</samp>';

		echo '
				</div><!-- #need_writable_list -->';
	}

	echo '
				<div class="bordercolor" id="ftp_error_div" style="', (!empty($context['package_ftp']['error']) ? '' : 'display:none;'), 'padding: 1px; margin: 1ex;">
					<div class="windowbg" id="ftp_error_innerdiv" style="padding: 1ex;">
						<samp id="ftp_error_message">', !empty($context['package_ftp']['error']) ? $context['package_ftp']['error'] : '', '</samp>
					</div>
				</div>';

	if (!empty($context['package_ftp']['destination']))
		echo '
				<form action="', $context['package_ftp']['destination'], '" method="post" accept-charset="', $context['character_set'], '">';

	echo '
					<fieldset>
					<dl class="settings">
						<dt>
							<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $context['package_ftp']['server'], '">
							<label for="ftp_port">', $txt['package_ftp_port'], ':</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', $context['package_ftp']['port'], '">
						</dd>
						<dt>
							<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $context['package_ftp']['username'], '">
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
					</fieldset>';

	if (empty($context['package_ftp']['form_elements_only']))
		echo '
					<div class="righttext" style="margin: 1ex;">
						<span id="test_ftp_placeholder_full"></span>
						<input type="submit" value="', $txt['package_proceed'], '" class="button">
					</div>';

	if (!empty($context['package_ftp']['destination']))
		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</form>';

	// Hide the details of the list.
	if (empty($context['package_ftp']['form_elements_only']))
		echo '
				<script>
					document.getElementById(\'need_writable_list\').style.display = \'none\';
				</script>';

	// Quick generate the test button.
	echo '
				<script>
					// Generate a "test ftp" button.
					var generatedButton = false;
					function generateFTPTest()
					{
						// Don\'t ever call this twice!
						if (generatedButton)
							return false;
						generatedButton = true;

						// No XML?
						if (!window.XMLHttpRequest || (!document.getElementById("test_ftp_placeholder") && !document.getElementById("test_ftp_placeholder_full")))
							return false;

						var ftpTest = document.createElement("input");
						ftpTest.type = "button";
						ftpTest.onclick = testFTP;

						if (document.getElementById("test_ftp_placeholder"))
						{
							ftpTest.value = "', $txt['package_ftp_test'], '";
							document.getElementById("test_ftp_placeholder").appendChild(ftpTest);
						}
						else
						{
							ftpTest.value = "', $txt['package_ftp_test_connection'], '";
							document.getElementById("test_ftp_placeholder_full").appendChild(ftpTest);
						}
					}
					function testFTPResults(oXMLDoc)
					{
						ajax_indicator(false);

						// This assumes it went wrong!
						var wasSuccess = false;
						var message = "', addcslashes($txt['package_ftp_test_failed'], "'"), '";

						var results = oXMLDoc.getElementsByTagName(\'results\')[0].getElementsByTagName(\'result\');
						if (results.length > 0)
						{
							if (results[0].getAttribute(\'success\') == 1)
								wasSuccess = true;
							message = results[0].firstChild.nodeValue;
						}

						document.getElementById("ftp_error_div").style.display = "";
						document.getElementById("ftp_error_div").style.backgroundColor = wasSuccess ? "green" : "red";
						document.getElementById("ftp_error_innerdiv").style.backgroundColor = wasSuccess ? "#DBFDC7" : "#FDBDBD";

						setInnerHTML(document.getElementById("ftp_error_message"), message);
					}
					generateFTPTest();
				</script>';

	// Make sure the button gets generated last.
	$context['insert_after_template'] .= '
				<script>
					generateFTPTest();
				</script>';
}

/**
 * Wrapper for the above template function showing that FTP is required
 */
function template_ftp_required()
{
	global $txt;

	echo '
		<fieldset>
			<legend>
				', $txt['package_ftp_necessary'], '
			</legend>
			<div class="ftp_details">
				', template_control_chmod(), '
			</div>
		</fieldset>';
}

/**
 * View operation details.
 */
function template_view_operations()
{
	global $context, $txt, $settings, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $txt['operation_title'], '</title>
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'], '">
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/admin.css', $modSettings['browser_cache'], '">
		<script src="', $settings['default_theme_url'], '/scripts/script.js', $modSettings['browser_cache'], '"></script>
		<script src="', $settings['default_theme_url'], '/scripts/theme.js', $modSettings['browser_cache'], '"></script>
	</head>
	<body>
		<div class="padding windowbg">
			<div class="padding">
				', $context['operations']['search'], '
			</div>
			<div class="padding">
				', $context['operations']['replace'], '
			</div>
		</div>
	</body>
</html>';
}

/**
 * The file permissions page.
 */
function template_file_permissions()
{
	global $txt, $scripturl, $context;

	// This will handle expanding the selection.
	echo '
	<script>
		var oRadioValues = {
			0: "read",
			1: "writable",
			2: "execute",
			3: "custom",
			4: "no_change"
		}
		function dynamicAddMore()
		{
			ajax_indicator(true);

			getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=admin;area=packages;fileoffset=\' + (parseInt(this.offset) + ', $context['file_limit'], ') + \';onlyfind=\' + escape(this.path) + \';sa=perms;xml;', $context['session_var'], '=', $context['session_id'], '\', onNewFolderReceived);
		}

		// Getting something back?
		function onNewFolderReceived(oXMLDoc)
		{
			ajax_indicator(false);

			var fileItems = oXMLDoc.getElementsByTagName(\'folders\')[0].getElementsByTagName(\'folder\');

			// No folders, no longer worth going further.
			if (fileItems.length < 1)
			{
				if (oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0])
				{
					var rootName = oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0].firstChild.nodeValue;
					var itemLink = document.getElementById(\'link_\' + rootName);

					// Move the children up.
					for (i = 0; i <= itemLink.childNodes.length; i++)
						itemLink.parentNode.insertBefore(itemLink.childNodes[0], itemLink);

					// And remove the link.
					itemLink.parentNode.removeChild(itemLink);
				}
				return false;
			}
			var tableHandle = false;
			var isMore = false;
			var ident = "";
			var my_ident = "";
			var curLevel = 0;

			for (var i = 0; i < fileItems.length; i++)
			{
				if (fileItems[i].getAttribute(\'more\') == 1)
				{
					isMore = true;
					var curOffset = fileItems[i].getAttribute(\'offset\');
				}

				if (fileItems[i].getAttribute(\'more\') != 1 && document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\')))
				{
					ident = fileItems[i].getAttribute(\'ident\');
					my_ident = fileItems[i].getAttribute(\'my_ident\');
					curLevel = fileItems[i].getAttribute(\'level\') * 5;
					curPath = fileItems[i].getAttribute(\'path\');

					// Get where we\'re putting it next to.
					tableHandle = document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\'));

					var curRow = document.createElement("tr");
					curRow.className = "windowbg";
					curRow.id = "content_" + my_ident;
					curRow.style.display = "";
					var curCol = document.createElement("td");
					curCol.className = "smalltext";
					curCol.width = "40%";

					// This is the name.
					var fileName = document.createTextNode(fileItems[i].firstChild.nodeValue);

					// Start by wacking in the spaces.
					setInnerHTML(curCol, repeatString("&nbsp;", curLevel));

					// Create the actual text.
					if (fileItems[i].getAttribute(\'folder\') == 1)
					{
						var linkData = document.createElement("a");
						linkData.name = "fol_" + my_ident;
						linkData.id = "link_" + my_ident;
						linkData.href = \'#\';
						linkData.path = curPath + "/" + fileItems[i].firstChild.nodeValue;
						linkData.ident = my_ident;
						linkData.onclick = dynamicExpandFolder;

						var folderImage = document.createElement("span");
						folderImage.className = "main_icons folder";
						linkData.appendChild(folderImage);

						linkData.appendChild(fileName);
						curCol.appendChild(linkData);
					}
					else
						curCol.appendChild(fileName);

					curRow.appendChild(curCol);

					// Right, the permissions.
					curCol = document.createElement("td");
					curCol.className = "smalltext";

					var writeSpan = document.createElement("span");
					writeSpan.className = fileItems[i].getAttribute(\'writable\') ? "green" : "red";
					setInnerHTML(writeSpan, fileItems[i].getAttribute(\'writable\') ? \'', $txt['package_file_perms_writable'], '\' : \'', $txt['package_file_perms_not_writable'], '\');
					curCol.appendChild(writeSpan);

					if (fileItems[i].getAttribute(\'permissions\'))
					{
						var permData = document.createTextNode("\u00a0(', $txt['package_file_perms_chmod'], ': " + fileItems[i].getAttribute(\'permissions\') + ")");
						curCol.appendChild(permData);
					}

					curRow.appendChild(curCol);

					// Now add the five radio buttons.
					for (j = 0; j < 5; j++)
					{
						curCol = document.createElement("td");
						curCol.className = "centertext perm_" + oRadioValues[j];
						curCol.align = "center";

						var curInput = createNamedElement("input", "permStatus[" + curPath + "/" + fileItems[i].firstChild.nodeValue + "]", j == 4 ? "checked" : "");
						curInput.type = "radio";
						curInput.checked = "checked";
						curInput.value = oRadioValues[j];

						curCol.appendChild(curInput);
						curRow.appendChild(curCol);
					}

					// Put the row in.
					tableHandle.parentNode.insertBefore(curRow, tableHandle);

					// Put in a new dummy section?
					if (fileItems[i].getAttribute(\'folder\') == 1)
					{
						var newRow = document.createElement("tr");
						newRow.id = "insert_div_loc_" + my_ident;
						newRow.style.display = "none";
						tableHandle.parentNode.insertBefore(newRow, tableHandle);
						var newCol = document.createElement("td");
						newCol.colspan = 2;
						newRow.appendChild(newCol);
					}
				}
			}

			// Is there some more to remove?
			if (document.getElementById("content_" + ident + "_more"))
			{
				document.getElementById("content_" + ident + "_more").parentNode.removeChild(document.getElementById("content_" + ident + "_more"));
			}

			// Add more?
			if (isMore && tableHandle)
			{
				// Create the actual link.
				var linkData = document.createElement("a");
				linkData.href = \'#fol_\' + my_ident;
				linkData.path = curPath;
				linkData.offset = curOffset;
				linkData.onclick = dynamicAddMore;

				linkData.appendChild(document.createTextNode(\'', $txt['package_file_perms_more_files'], '\'));

				curRow = document.createElement("tr");
				curRow.className = "windowbg";
				curRow.id = "content_" + ident + "_more";
				tableHandle.parentNode.insertBefore(curRow, tableHandle);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curCol.width = "40%";

				setInnerHTML(curCol, repeatString("&nbsp;", curLevel));
				curCol.appendChild(document.createTextNode(\'\\u00ab \'));
				curCol.appendChild(linkData);
				curCol.appendChild(document.createTextNode(\' \\u00bb\'));

				curRow.appendChild(curCol);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curRow.appendChild(curCol);
			}

			// Keep track of it.
			var curInput = createNamedElement("input", "back_look[]");
			curInput.type = "hidden";
			curInput.value = curPath;

			curCol.appendChild(curInput);
		}
	</script>';

	echo '
	<div class="noticebox">
		<div>
			<strong>', $txt['package_file_perms_warning'], ':</strong>
			<div class="smalltext">
				<ol style="margin-top: 2px; margin-bottom: 2px">
					', $txt['package_file_perms_warning_desc'], '
				</ol>
			</div>
		</div>
	</div>

	<form action="', $scripturl, '?action=admin;area=packages;sa=perms;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft">', $txt['package_file_perms'], '</span><span class="perms_status floatright">', $txt['package_file_perms_new_status'], '</span>
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext" width="30%">', $txt['package_file_perms_name'], '</th>
					<th width="30%" class="lefttext">', $txt['package_file_perms_status'], '</th>
					<th width="8%"><span class="file_permissions">', $txt['package_file_perms_status_read'], '</span></th>
					<th width="8%"><span class="file_permissions">', $txt['package_file_perms_status_write'], '</span></th>
					<th width="8%"><span class="file_permissions">', $txt['package_file_perms_status_execute'], '</span></th>
					<th width="8%"><span class="file_permissions">', $txt['package_file_perms_status_custom'], '</span></th>
					<th width="8%"><span class="file_permissions">', $txt['package_file_perms_status_no_change'], '</span></th>
				</tr>
			</thead>
			<tbody>';

	foreach ($context['file_tree'] as $name => $dir)
	{
		echo '
				<tr class="windowbg">
					<td width="30%">
						<strong>';

		if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
			echo '
							<span class="main_icons folder"></span>';

		echo '
							', $name, '
						</strong>
					</td>
					<td width="30%">
						<span style="color: ', ($dir['perms']['chmod'] ? 'green' : 'red'), '">', ($dir['perms']['chmod'] ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']), '</span>
						', ($dir['perms']['perms'] ? ' (' . $txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
					</td>
					<td class="centertext perm_read">
						<input type="radio" name="permStatus[', $name, ']" value="read" class="centertext">
					</td>
					<td class="centertext perm_writable">
						<input type="radio" name="permStatus[', $name, ']" value="writable" class="centertext">
					</td>
					<td class="centertext perm_execute">
						<input type="radio" name="permStatus[', $name, ']" value="execute" class="centertext">
					</td>
					<td class="centertext perm_custom">
						<input type="radio" name="permStatus[', $name, ']" value="custom" class="centertext">
					</td>
					<td class="centertext perm_no_change">
						<input type="radio" name="permStatus[', $name, ']" value="no_change" checked class="centertext">
					</td>
				</tr>';

		if (!empty($dir['contents']))
			template_permission_show_contents($name, $dir['contents'], 1);
	}

	echo '
			</tbody>
		</table>
		<br>
		<div class="cat_bar">
			<h3 class="catbg">', $txt['package_file_perms_change'], '</h3>
		</div>
		<div class="windowbg">
			<fieldset>
				<dl>
					<dt>
						<input type="radio" name="method" value="individual" checked id="method_individual">
						<label for="method_individual"><strong>', $txt['package_file_perms_apply'], '</strong></label>
					</dt>
					<dd>
						<em class="smalltext">', $txt['package_file_perms_custom'], ': <input type="text" name="custom_value" value="0755" maxlength="4" size="5"> <a href="', $scripturl, '?action=helpadmin;help=chmod_flags" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a></em>
					</dd>
					<dt>
						<input type="radio" name="method" value="predefined" id="method_predefined">
						<label for="method_predefined"><strong>', $txt['package_file_perms_predefined'], ':</strong></label>
						<select name="predefined" onchange="document.getElementById(\'method_predefined\').checked = \'checked\';">
							<option value="restricted" selected>', $txt['package_file_perms_pre_restricted'], '</option>
							<option value="standard">', $txt['package_file_perms_pre_standard'], '</option>
							<option value="free">', $txt['package_file_perms_pre_free'], '</option>
						</select>
					</dt>
					<dd>
						<em class="smalltext">', $txt['package_file_perms_predefined_note'], '</em>
					</dd>
				</dl>
			</fieldset>';

	// Likely to need FTP?
	if (empty($context['ftp_connected']))
		echo '
			<p>
				', $txt['package_file_perms_ftp_details'], ':
			</p>
			', template_control_chmod(), '
			<div class="noticebox">', $txt['package_file_perms_ftp_retain'], '</div>';

	echo '
			<span id="test_ftp_placeholder_full"></span>
			<input type="hidden" name="action_changes" value="1">
			<input type="submit" value="', $txt['package_file_perms_go'], '" name="go" class="button">
		</div><!-- .windowbg -->';

	// Any looks fors we've already done?
	foreach ($context['look_for'] as $path)
		echo '
		<input type="hidden" name="back_look[]" value="', $path, '">';

	echo '
	</form>
	<br>';
}

/**
 * Shows permissions for items within a directory (called from template_file_permissions)
 *
 * @param string $ident A unique ID - typically the directory name
 * @param array $contents An array of items within the directory
 * @param int $level How far to go inside the directory
 * @param bool $has_more Whether there are more files to display besides what's in $contents
 */
function template_permission_show_contents($ident, $contents, $level, $has_more = false)
{
	global $txt, $scripturl, $context;
	$js_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident);

	// Have we actually done something?
	$drawn_div = false;

	foreach ($contents as $name => $dir)
	{
		if (isset($dir['perms']))
		{
			if (!$drawn_div)
			{
				$drawn_div = true;
				echo '
			</tbody>
			<tbody class="table_grid" id="', $js_ident, '">';
			}

			$cur_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident . '/' . $name);

			echo '
				<tr class="windowbg" id="content_', $cur_ident, '">
					<td class="smalltext" width="30%">' . str_repeat('&nbsp;', $level * 5), '
					', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '<a id="link_' . $cur_ident . '" href="' . $scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident . '/' . $name) . ';back_look=' . $context['back_look_data'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '#fol_' . $cur_ident . '" onclick="return expandFolder(\'' . $cur_ident . '\', \'' . addcslashes($ident . '/' . $name, "'\\") . '\');">' : '';

			if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
				echo '
						<span class="main_icons folder"></span>';

			echo '
						', $name, '
						', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '</a>' : '', '
					</td>
					<td class="smalltext">
						<span class="', ($dir['perms']['chmod'] ? 'success' : 'error'), '">', ($dir['perms']['chmod'] ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']), '</span>
						', ($dir['perms']['perms'] ? ' (' . $txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
					</td>
					<td class="centertext perm_read"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="read"></td>
					<td class="centertext perm_writable"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="writable"></td>
					<td class="centertext perm_execute"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="execute"></td>
					<td class="centertext perm_custom"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="custom"></td>
					<td class="centertext perm_no_change"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="no_change" checked></td>
				</tr>
				<tr id="insert_div_loc_' . $cur_ident . '" style="display: none;"><td></td></tr>';

			if (!empty($dir['contents']))
				template_permission_show_contents($ident . '/' . $name, $dir['contents'], $level + 1, !empty($dir['more_files']));
		}
	}

	// We have more files to show?
	if ($has_more)
		echo '
				<tr class="windowbg" id="content_', $js_ident, '_more">
					<td class="smalltext" width="40%">' . str_repeat('&nbsp;', $level * 5), '
						&#171; <a href="' . $scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident) . ';fileoffset=', ($context['file_offset'] + $context['file_limit']), ';' . $context['session_var'] . '=' . $context['session_id'] . '#fol_' . preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident) . '">', $txt['package_file_perms_more_files'], '</a> &#187;
					</td>
					<td colspan="6"></td>
				</tr>';

	if ($drawn_div)
	{
		// Hide anything too far down the tree.
		$isFound = false;
		foreach ($context['look_for'] as $tree)
			if (substr($tree, 0, strlen($ident)) == $ident)
				$isFound = true;

		if ($level > 1 && !$isFound)
			echo '
		<script>
			expandFolder(\'', $js_ident, '\', \'\');
		</script>';
	}
}

/**
 * A progress page showing what permissions changes are being applied
 */
function template_action_permissions()
{
	global $txt, $scripturl, $context;

	$countDown = 3;

	echo '
		<form action="', $scripturl, '?action=admin;area=packages;sa=perms;', $context['session_var'], '=', $context['session_id'], '" id="perm_submit" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['package_file_perms_applying'], '</h3>
			</div>';

	if (!empty($context['skip_ftp']))
		echo '
			<div class="errorbox">
				', $txt['package_file_perms_skipping_ftp'], '
			</div>';

	// How many have we done?
	$remaining_items = count($context['method'] == 'individual' ? $context['to_process'] : $context['directory_list']);
	$progress_message = sprintf($context['method'] == 'individual' ? $txt['package_file_perms_items_done'] : $txt['package_file_perms_dirs_done'], $context['total_items'] - $remaining_items, $context['total_items']);
	$progress_percent = round(($context['total_items'] - $remaining_items) / $context['total_items'] * 100, 1);

	echo '
			<div class="windowbg">
				<div>
					<strong>', $progress_message, '</strong><br>
					<div class="progress_bar progress_blue">
						<span>', $progress_percent, '%</span>
						<div class="bar" style="width: ', $progress_percent, '%;"></div>
					</div>
				</div>';

	// Second progress bar for a specific directory?
	if ($context['method'] != 'individual' && !empty($context['total_files']))
	{
		$file_progress_message = sprintf($txt['package_file_perms_files_done'], $context['file_offset'], $context['total_files']);
		$file_progress_percent = round($context['file_offset'] / $context['total_files'] * 100, 1);

		echo '
				<br>
				<div>
					<strong>', $file_progress_message, '</strong><br>
					<div class="progress_bar">
						<span>', $file_progress_percent, '%</span>
						<div class="bar" style="width: ', $file_progress_percent, '%;"></div>
					</div>
				</div>';
	}

	echo '
				<br>';

	// Put out the right hidden data.
	if ($context['method'] == 'individual')
		echo '
				<input type="hidden" name="custom_value" value="', $context['custom_value'], '">
				<input type="hidden" name="totalItems" value="', $context['total_items'], '">
				<input type="hidden" name="toProcess" value="', $context['to_process_encode'], '">';
	else
		echo '
				<input type="hidden" name="predefined" value="', $context['predefined_type'], '">
				<input type="hidden" name="fileOffset" value="', $context['file_offset'], '">
				<input type="hidden" name="totalItems" value="', $context['total_items'], '">
				<input type="hidden" name="dirList" value="', $context['directory_list_encode'], '">
				<input type="hidden" name="specialFiles" value="', $context['special_files_encode'], '">';

	// Are we not using FTP for whatever reason.
	if (!empty($context['skip_ftp']))
		echo '
				<input type="hidden" name="skip_ftp" value="1">';

	// Retain state.
	foreach ($context['back_look_data'] as $path)
		echo '
				<input type="hidden" name="back_look[]" value="', $path, '">';

	echo '
				<input type="hidden" name="method" value="', $context['method'], '">
				<input type="hidden" name="action_changes" value="1">
				<div class="righttext padding">
					<input type="submit" name="go" id="cont" value="', $txt['not_done_continue'], '" class="button">
				</div>
			</div><!-- .windowbg -->
		</form>';

	// Just the countdown stuff
	echo '
	<script>
		var countdown = ', $countDown, ';
		doAutoSubmit();

		function doAutoSubmit()
		{
			if (countdown == 0)
				document.forms.perm_submit.submit();
			else if (countdown == -1)
				return;

			document.getElementById(\'cont\').value = "', $txt['not_done_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
	</script>';
}

?>