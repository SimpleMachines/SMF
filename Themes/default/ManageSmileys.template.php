<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * Shows a list of smiley sets so you can edit them.
 */
function template_editsets()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	template_show_list('smiley_set_list');

	echo '
	</div>';
}

/**
 * Modifying a smiley set.
 */
function template_modifyset()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsets" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
				', $context['current_set']['is_new'] ? $txt['smiley_set_new'] : $txt['smiley_set_modify_existing'], '
				</h3>
			</div>';

		// If this is an existing set, and there are still un-added smileys - offer an import opportunity.
		if (!empty($context['current_set']['can_import']))
		{
			echo '
			<div class="information">
				', $context['current_set']['can_import'] == 1 ? sprintf($txt['smiley_set_import_single'], $context['current_set']['import_url']) : sprintf($txt['smiley_set_import_multiple'], $context['current_set']['can_import'], $context['current_set']['import_url']), '
			</div>';
		}

		echo '
			<div class="windowbg2 noup">
				<dl class="settings">
					<dt>
						<strong><label for="smiley_sets_name">', $txt['smiley_sets_name'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_sets_name" id="smiley_sets_name" value="', $context['current_set']['name'], '" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_sets_path">', $txt['smiley_sets_url'], '</label>: </strong>
					</dt>
					<dd>
						', $modSettings['smileys_url'], '/';
		if ($context['current_set']['id'] == 'default')
			echo '<strong>default</strong><input type="hidden" name="smiley_sets_path" id="smiley_sets_path" value="default">';
		elseif (empty($context['smiley_set_dirs']))
			echo '
						<input type="text" name="smiley_sets_path" id="smiley_sets_path" value="', $context['current_set']['path'], '" class="input_text"> ';
		else
		{
			echo '
						<select name="smiley_sets_path" id="smiley_sets_path">';
			foreach ($context['smiley_set_dirs'] as $smiley_set_dir)
				echo '
							<option value="', $smiley_set_dir['id'], '"', $smiley_set_dir['current'] ? ' selected' : '', $smiley_set_dir['selectable'] ? '' : ' disabled', '>', $smiley_set_dir['id'], '</option>';
			echo '
						</select> ';
		}
		echo '
						/..
					</dd>
					<dt>
						<strong><label for="smiley_sets_default">', $txt['smiley_set_select_default'], '</label>: </strong>
					</dt>
					<dd>
						<input type="checkbox" name="smiley_sets_default" id="smiley_sets_default" value="1"', $context['current_set']['selected'] ? ' checked' : '', ' class="input_check">
					</dd>';

		// If this is a new smiley set they have the option to import smileys already in the directory.
		if ($context['current_set']['is_new'] && !empty($modSettings['smiley_enable']))
			echo '
					<dt>
						<strong><label for="smiley_sets_import">', $txt['smiley_set_import_directory'], '</label>: </strong>
					</dt>
					<dd>
						<input type="checkbox" name="smiley_sets_import" id="smiley_sets_import" value="1" class="input_check">
					</dd>';

		echo '
				</dl>
				<input type="submit" name="smiley_save" value="', $txt['smiley_sets_save'], '" class="button_submit">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-mss_token_var'], '" value="', $context['admin-mss_token'], '">
			<input type="hidden" name="set" value="', $context['current_set']['id'], '">
		</form>
	</div>';
}

/**
 * Editing an individual smiley
 */
function template_modifysmiley()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="', $context['character_set'], '" name="smileyForm" id="smileyForm">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['smiley_modify_existing'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<strong>', $txt['smiley_preview'], ': </strong>
					</dt>
					<dd>
						<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['current_smiley']['filename'], '" id="preview" alt=""> (', $txt['smiley_preview_using'], ': <select name="set" onchange="updatePreview();">';

		foreach ($context['smiley_sets'] as $smiley_set)
			echo '
						<option value="', $smiley_set['path'], '"', $context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

		echo '
						</select>)
					</dd>
					<dt>
						<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_code" id="smiley_code" value="', $context['current_smiley']['code'], '" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_filename">', $txt['smileys_filename'], '</label>: </strong>
					</dt>
					<dd>';
			if (empty($context['filenames']))
				echo '
						<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '" class="input_text">';
			else
			{
				echo '
						<select name="smiley_filename" id="smiley_filename" onchange="updatePreview();">';
				foreach ($context['filenames'] as $filename)
					echo '
							<option value="', $filename['id'], '"', $filename['selected'] ? ' selected' : '', '>', $filename['id'], '</option>';
				echo '
						</select>';
			}
			echo '
					</dd>
					<dt>
						<strong><label for="smiley_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_description" id="smiley_description" value="', $context['current_smiley']['description'], '" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="smiley_location" id="smiley_location">
							<option value="0"', $context['current_smiley']['location'] == 0 ? ' selected' : '', '>
								', $txt['smileys_location_form'], '
							</option>
							<option value="1"', $context['current_smiley']['location'] == 1 ? ' selected' : '', '>
								', $txt['smileys_location_hidden'], '
							</option>
							<option value="2"', $context['current_smiley']['location'] == 2 ? ' selected' : '', '>
								', $txt['smileys_location_popup'], '
							</option>
						</select>
					</dd>
				</dl>
				<input type="submit" name="smiley_save" value="', $txt['smileys_save'], '" class="button_submit">
				<input type="submit" name="deletesmiley" value="', $txt['smileys_delete'], '" data-confirm="', $txt['smileys_delete_confirm'], '" class="button_submit you_sure">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="smiley" value="', $context['current_smiley']['id'], '">
		</form>
	</div>';
}

/**
 * Adding a new smiley.
 */
function template_addsmiley()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=addsmiley" method="post" accept-charset="', $context['character_set'], '" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['smileys_add_method'], '</h3>
			</div>
			<div class="windowbg2">
				<ul>
					<li>
						<label for="method-existing"><input type="radio" onclick="switchType();" name="method" id="method-existing" value="existing" checked class="input_radio"> ', $txt['smileys_add_existing'], '</label>
					</li>
					<li>
						<label for="method-upload"><input type="radio" onclick="switchType();" name="method" id="method-upload" value="upload" class="input_radio"> ', $txt['smileys_add_upload'], '</label>
					</li>
				</ul>
				<br>
				<fieldset id="ex_settings">
					<dl class="settings">
						<dt>
							<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['filenames'][0]['id'], '" id="preview" alt="">
						</dt>
						<dd>
							', $txt['smiley_preview_using'], ': <select name="set" onchange="updatePreview();selectMethod(\'existing\');">';

		foreach ($context['smiley_sets'] as $smiley_set)
			echo '
							<option value="', $smiley_set['path'], '"', $context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

		echo '
							</select>
						</dd>
						<dt>
							<strong><label for="smiley_filename">', $txt['smileys_filename'], '</label>: </strong>
						</dt>
						<dd>';
	if (empty($context['filenames']))
		echo '
							<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '" onchange="selectMethod(\'existing\');" class="input_text">';
	else
	{
		echo '
								<select name="smiley_filename" id="smiley_filename" onchange="updatePreview();selectMethod(\'existing\');">';
		foreach ($context['filenames'] as $filename)
			echo '
								<option value="', $filename['id'], '"', $filename['selected'] ? ' selected' : '', '>', $filename['id'], '</option>';
		echo '
							</select>';
	}

	echo '
						</dd>
					</dl>
				</fieldset>
				<fieldset id="ul_settings" style="display: none;">
					<dl class="settings">
						<dt>
							<strong>', $txt['smileys_add_upload_choose'], ':</strong><br>
							<span class="smalltext">', $txt['smileys_add_upload_choose_desc'], '</span>
						</dt>
						<dd>
							<input type="file" name="uploadSmiley" id="uploadSmiley" onchange="selectMethod(\'upload\');" class="input_file">
						</dd>
						<dt>
							<strong><label for="sameall">', $txt['smileys_add_upload_all'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="sameall" id="sameall" checked class="input_check" onclick="swapUploads(); selectMethod(\'upload\');">
						</dd>
					</dl>
				</fieldset>

				<dl id="uploadMore" style="display: none;" class="settings">';
	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
					<dt>
						', $txt['smileys_add_upload_for1'], ' <strong>', $smiley_set['name'], '</strong> ', $txt['smileys_add_upload_for2'], ':
					</dt>
					<dd>
						<input type="file" name="individual_', $smiley_set['name'], '" onchange="selectMethod(\'upload\');" class="input_file">
					</dd>';
	echo '
				</dl>
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['smiley_new'], '</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">
					<dt>
						<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_code" id="smiley_code" value="" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_description" id="smiley_description" value="" class="input_text">
					</dd>
					<dt>
						<strong><label for="smiley_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="smiley_location" id="smiley_location">
							<option value="0" selected>
								', $txt['smileys_location_form'], '
							</option>
							<option value="1">
								', $txt['smileys_location_hidden'], '
							</option>
							<option value="2">
								', $txt['smileys_location_popup'], '
							</option>
						</select>
					</dd>
				</dl>
				<input type="submit" name="smiley_save" value="', $txt['smileys_save'], '" class="button_submit">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}

/**
 * Ordering smileys.
 */
function template_setorder()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	foreach ($context['smileys'] as $location)
	{
		echo '
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $location['title'], '</h3>
			</div>
			<div class="information noup">
				', $location['description'], '
			</div>
			<div class="windowbg2">
				<strong>', empty($context['move_smiley']) ? $txt['smileys_move_select_smiley'] : $txt['smileys_move_select_destination'], '...</strong><br>';
		foreach ($location['rows'] as $row)
		{
			if (!empty($context['move_smiley']))
				echo '
					<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $row[0]['row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';

			foreach ($row as $smiley)
			{
				if (empty($context['move_smiley']))
					echo '<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;move=', $smiley['id'], '"><img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" style="padding: 2px; border: 0px solid black;" alt="', $smiley['description'], '"></a>';
				else
					echo '<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" style="padding: 2px;', $smiley['selected'] ? ' border: 2px solid red' : '', ';" alt="', $smiley['description'], '"><a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';after=', $smiley['id'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '" title="', $txt['smileys_move_here'], '"><span class="generic_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';
			}

			echo '
				<br>';
		}
		if (!empty($context['move_smiley']))
			echo '
				<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $location['last_row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><span class="generic_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';
		echo '
			</div>
		<input type="hidden" name="reorder" value="1">
	</form>';
	}

	echo '
	</div>';
}

/**
 * Editing Message Icons
 */
function template_editicons()
{
	template_show_list('message_icon_list');
}

/**
 * Editing an individual message icon
 */
function template_editicon()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editicon;icon=', $context['new_icon'] ? '0' : $context['icon']['id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['new_icon'] ? $txt['icons_new_icon'] : $txt['icons_edit_icon'], '
				</h3>
			</div>
			<div class="windowbg2">
				<dl class="settings">';
	if (!$context['new_icon'])
		echo '
					<dt>
						<strong>', $txt['smiley_preview'], ': </strong>
					</dt>
					<dd>
						<img src="', $context['icon']['image_url'], '" alt="', $context['icon']['title'], '">
					</dd>';
	echo '
					<dt>
						<strong><label for="icon_filename">', $txt['smileys_filename'], '</label>: </strong><br><span class="smalltext">', $txt['icons_filename_all_png'], '</span>
					</dt>
					<dd>
						<input type="text" name="icon_filename" id="icon_filename" value="', !empty($context['icon']['filename']) ? $context['icon']['filename'] . '.png' : '', '" class="input_text">
					</dd>
					<dt>
						<strong><label for="icon_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="icon_description" id="icon_description" value="', !empty($context['icon']['title']) ? $context['icon']['title'] : '', '" class="input_text">
					</dd>
					<dt>
						<strong><label for="icon_board_select">', $txt['icons_board'], '</label>: </strong>
					</dt>
					<dd>
						<select name="icon_board" id="icon_board_select">
							<option value="0"', empty($context['icon']['board_id']) ? ' selected' : '', '>', $txt['icons_edit_icons_all_boards'], '</option>';

	foreach ($context['categories'] as $category)
	{
		echo '
							<optgroup label="', $category['name'], '">';
		foreach ($category['boards'] as $board)
			echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
		echo '
							</optgroup>';
	}
	echo '
						</select>
					</dd>
					<dt>
						<strong><label for="icon_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="icon_location" id="icon_location">
							<option value="0"', empty($context['icon']['after']) ? ' selected' : '', '>', $txt['icons_location_first_icon'], '</option>';

	// Print the list of all the icons it can be put after...
	foreach ($context['icons'] as $id => $data)
		if (empty($context['icon']['id']) || $id != $context['icon']['id'])
			echo '
							<option value="', $id, '"', !empty($context['icon']['after']) && $id == $context['icon']['after'] ? ' selected' : '', '>', $txt['icons_location_after'], ': ', $data['title'], '</option>';

	echo '
						</select>
					</dd>
				</dl>';

	if (!$context['new_icon'])
		echo '
				<input type="hidden" name="icon" value="', $context['icon']['id'], '">';

	echo '
				<input type="submit" name="icons_save" value="', $txt['smileys_save'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>';
}

?>