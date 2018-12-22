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
 * Shows a list of smiley sets so you can edit them.
 */
function template_editsets()
{
	template_show_list('smiley_set_list');
}

/**
 * Modifying a smiley set.
 */
function template_modifyset()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsets" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
			', $context['current_set']['is_new'] ? $txt['smiley_set_new'] : $txt['smiley_set_modify_existing'], '
			</h3>
		</div>';

	// If this is an existing set, and there are still un-added smileys - offer an import opportunity.
	if (!empty($context['current_set']['can_import']))
		echo '
		<div class="information noup">
			', $context['current_set']['can_import'] == 1 ? sprintf($txt['smiley_set_import_single'], $context['current_set']['import_url']) : sprintf($txt['smiley_set_import_multiple'], $context['current_set']['can_import'], $context['current_set']['import_url']), '
		</div>';

	echo '
		<div class="windowbg noup">
			<dl class="settings">
				<dt>
					<strong><label for="smiley_sets_name">', $txt['smiley_sets_name'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_sets_name" id="smiley_sets_name" value="', $context['current_set']['name'], '">
				</dd>
				<dt>
					<strong><label for="smiley_sets_ext">', $txt['smiley_sets_ext'], '</label>: </strong>
				</dt>
				<dd>
					<select name="smiley_sets_ext" id="smiley_sets_ext">
						<option value=".png"', $context['current_set']['ext'] == '.png' ? 'selected' : '', '>.png</option>
						<option value=".gif"', $context['current_set']['ext'] == '.gif' ? 'selected' : '', '>.gif</option>
						<option value=".jpg"', $context['current_set']['ext'] == '.jpg' ? 'selected' : '', '>.jpg</option>
						<option value=".jpeg"', $context['current_set']['ext'] == '.jpeg' ? 'selected' : '', '>.jpeg</option>
						<option value=".svg"', $context['current_set']['ext'] == '.svg' ? 'selected' : '', '>.svg</option>
					</select>
				</dd>
				<dt>
					<strong><label for="smiley_sets_path">', $txt['smiley_sets_url'], '</label>: </strong>
				</dt>
				<dd>
					', $modSettings['smileys_url'], '/';

	if (empty($context['smiley_set_dirs']))
		echo '
					<input type="text" name="smiley_sets_path" id="smiley_sets_path" value="', $context['current_set']['path'], '"> ';
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
					<input type="checkbox" name="smiley_sets_default" id="smiley_sets_default" value="1"', $context['current_set']['selected'] ? ' checked' : '', '>
				</dd>';

	// If this is a new smiley set they have the option to import smileys already in the directory.
	if ($context['current_set']['is_new'] && !empty($modSettings['smiley_enable']))
		echo '
				<dt>
					<strong><label for="smiley_sets_import">', $txt['smiley_set_import_directory'], '</label>: </strong>
				</dt>
				<dd>
					<input type="checkbox" name="smiley_sets_import" id="smiley_sets_import" value="1">
				</dd>';

	echo '
			</dl>
			<input type="submit" name="smiley_save" value="', $txt['smiley_sets_save'], '" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="', $context['admin-mss_token_var'], '" value="', $context['admin-mss_token'], '">
		<input type="hidden" name="set" value="', $context['current_set']['id'], '">
	</form>';
}

/**
 * Editing an individual smiley
 */
function template_modifysmiley()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<form action="', $scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="', $context['character_set'], '" name="smileyForm" id="smileyForm">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['smiley_modify_existing'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong>', $txt['smiley_preview'], ': </strong>
				</dt>
				<dd>
					<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['current_smiley']['filename'] . $context['user']['smiley_set_default_ext'], '" id="preview" alt=""> ', $txt['smiley_preview_using'], ': <select id="set" onchange="updatePreview();">';

	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
					<option value="', $smiley_set['path'], '"', $context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

	echo '
					</select>
				</dd>
				<dt>
					<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_code" id="smiley_code" value="', $context['current_smiley']['code'], '">
				</dd>
				<dt>
					<strong><label for="smiley_filename">', $txt['smileys_filename'], '</label>: </strong>
				</dt>
				<dd>';

	if (empty($context['filenames']))
		echo '
					<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '">';
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
					<input type="text" name="smiley_description" id="smiley_description" value="', $context['current_smiley']['description'], '">
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
			<input type="submit" name="smiley_save" value="', $txt['smileys_save'], '" class="button">
			<input type="submit" name="deletesmiley" value="', $txt['smileys_delete'], '" data-confirm="', $txt['smileys_delete_confirm'], '" class="button you_sure">
		</div><!-- .windowbg -->
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="smiley" value="', $context['current_smiley']['id'], '">
	</form>';
}

/**
 * Adding a new smiley.
 */
function template_addsmiley()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<form action="', $scripturl, '?action=admin;area=smileys;sa=addsmiley" method="post" accept-charset="', $context['character_set'], '" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['smileys_add_method'], '</h3>
		</div>
		<div class="windowbg">
			<ul>
				<li>
					<label for="method-existing"><input type="radio" onclick="switchType();" name="method" id="method-existing" value="existing" checked> ', $txt['smileys_add_existing'], '</label>
				</li>
				<li>
					<label for="method-upload"><input type="radio" onclick="switchType();" name="method" id="method-upload" value="upload"> ', $txt['smileys_add_upload'], '</label>
				</li>
			</ul>
			<br>
			<fieldset id="ex_settings">
				<dl class="settings">
					<dt>
						<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['filenames'][0]['id'] . $context['user']['smiley_set_default_ext'], '" id="preview" alt="">
					</dt>
					<dd>
						', $txt['smiley_preview_using'], ': <select id="set" onchange="updatePreview();selectMethod(\'existing\');">';

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
						<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '" onchange="selectMethod(\'existing\');">';
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

			<dl id="uploadMore" class="settings">';

	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
				<dt>
					', sprintf($txt['smileys_add_upload_for'], '<strong>' . $smiley_set['name'] . '</strong>'), ':
				</dt>
				<dd>
					<input type="file" name="individual_', $smiley_set['name'], '" onchange="selectMethod(\'upload\');">
				</dd>';

	echo '
			</dl>
			</fieldset>
		</div><!-- .windowbg -->
		<div class="cat_bar">
			<h3 class="catbg">', $txt['smiley_new'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt>
					<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_code" id="smiley_code" value="">
				</dd>
				<dt>
					<strong><label for="smiley_description">', $txt['smileys_description'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="smiley_description" id="smiley_description" value="">
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
			<input type="submit" name="smiley_save" value="', $txt['smileys_save'], '" class="button">
		</div><!-- .windowbg -->
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

/**
 * Ordering smileys.
 */
function template_setorder()
{
	global $context, $scripturl, $txt, $modSettings;

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
		<div class="move_smileys windowbg noup">
			<strong>', empty($context['move_smiley']) ? $txt['smileys_move_select_smiley'] : $txt['smileys_move_select_destination'], '...</strong><br>';

		foreach ($location['rows'] as $row)
		{
			if (!empty($context['move_smiley']))
				echo '
			<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $row[0]['row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><span class="main_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';

			foreach ($row as $smiley)
			{
				if (empty($context['move_smiley']))
					echo '
			<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;move=', $smiley['id'], '"><img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" alt="', $smiley['description'], '"></a>';
				else
					echo '
			<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" alt="', $smiley['description'], '" ', $smiley['selected'] ? 'class="selected_item"' : '', '>
			<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';after=', $smiley['id'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '" title="', $txt['smileys_move_here'], '"><span class="main_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';
			}

			echo '
			<br>';
		}
		if (!empty($context['move_smiley']))
			echo '
			<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $location['last_row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><span class="main_icons select_below" title="', $txt['smileys_move_here'], '"></span></a>';
		echo '
		</div><!-- .windowbg -->
		<input type="hidden" name="reorder" value="1">
	</form>';
	}
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
	<form action="', $scripturl, '?action=admin;area=smileys;sa=editicon;icon=', $context['new_icon'] ? '0' : $context['icon']['id'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['new_icon'] ? $txt['icons_new_icon'] : $txt['icons_edit_icon'], '
			</h3>
		</div>
		<div class="windowbg">
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
					<input type="text" name="icon_filename" id="icon_filename" value="', !empty($context['icon']['filename']) ? $context['icon']['filename'] . '.png' : '', '">
				</dd>
				<dt>
					<strong><label for="icon_description">', $txt['smileys_description'], '</label>: </strong>
				</dt>
				<dd>
					<input type="text" name="icon_description" id="icon_description" value="', !empty($context['icon']['title']) ? $context['icon']['title'] : '', '">
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
			<input type="submit" name="icons_save" value="', $txt['smileys_save'], '" class="button">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</div><!-- .windowbg -->
	</form>';
}

?>